<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Api\V1\Infrastructure;

use App\com_pinoox_cms\Cms\Authorization\AuthorizationDeniedException;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationManager;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationRequest;
use App\com_pinoox_cms\Cms\Cache\CacheControlService;
use App\com_pinoox_cms\Cms\Cache\CacheLayer;
use App\com_pinoox_cms\Cms\Infrastructure\InfrastructureSnapshotService;
use App\com_pinoox_cms\Cms\Queue\QueueControlService;
use Throwable;

final readonly class InfrastructureApiFacade
{
    public function __construct(
        private AuthorizationManager $authorization,
        private InfrastructureSnapshotService $snapshot,
        private QueueControlService $queue,
        private CacheControlService $cache,
    ) {}

    public function status(?int $actorId=null): InfrastructureApiResponse
    {
        return $this->guard(function() use($actorId):InfrastructureApiResponse{
            $this->authorization->authorize(new AuthorizationRequest('system.health.view',$actorId));
            return new InfrastructureApiResponse(200,['data'=>$this->snapshot->snapshot()]);
        });
    }

    public function queue(?int $actorId=null): InfrastructureApiResponse
    {
        return $this->guard(fn()=>new InfrastructureApiResponse(200,['data'=>$this->queue->status($actorId)]));
    }

    public function retryQueue(string $jobId,?int $actorId=null): InfrastructureApiResponse
    {
        return $this->guard(function() use($jobId,$actorId):InfrastructureApiResponse{
            $job=$this->queue->retry($jobId,$actorId);
            return new InfrastructureApiResponse(200,['data'=>$job->toArray(true)]);
        });
    }

    public function invalidateTag(string $tag,?int $actorId=null): InfrastructureApiResponse
    {
        return $this->guard(fn()=>new InfrastructureApiResponse(200,[
            'data'=>['tag'=>$tag,'generation'=>$this->cache->invalidateTag($tag,$actorId)]
        ]));
    }

    public function invalidateLayer(string $layer,?int $actorId=null): InfrastructureApiResponse
    {
        return $this->guard(fn()=>new InfrastructureApiResponse(200,[
            'data'=>[
                'layer'=>$layer,
                'generation'=>$this->cache->invalidateLayer(CacheLayer::from($layer),$actorId),
            ]
        ]));
    }

    private function guard(callable $callback): InfrastructureApiResponse
    {
        try {
            return $callback();
        } catch (AuthorizationDeniedException) {
            return $this->error(403,InfrastructureApiErrorCode::Forbidden,'Infrastructure operation is not permitted.');
        } catch (\ValueError|\InvalidArgumentException $error) {
            return $this->error(422,InfrastructureApiErrorCode::InvalidRequest,'Infrastructure request is invalid.');
        } catch (\RuntimeException $error) {
            if (str_contains(strtolower($error->getMessage()),'not found')) {
                return $this->error(404,InfrastructureApiErrorCode::NotFound,'Infrastructure resource not found.');
            }
            return $this->error(409,InfrastructureApiErrorCode::Conflict,'Infrastructure state conflict.');
        } catch (Throwable) {
            return $this->error(500,InfrastructureApiErrorCode::InternalError,'Internal infrastructure error.');
        }
    }

    private function error(int $status,InfrastructureApiErrorCode $code,string $message):InfrastructureApiResponse
    {
        return new InfrastructureApiResponse($status,[
            'error'=>['code'=>$code->value,'message'=>$message,'details'=>[]],
        ]);
    }
}

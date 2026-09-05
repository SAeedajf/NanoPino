<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Api\V1\Update;

use App\com_pinoox_cms\Cms\Authorization\AuthorizationDeniedException;
use App\com_pinoox_cms\Cms\UpdatePolicy\ExtensionUpdatePolicy;
use App\com_pinoox_cms\Cms\UpdatePolicy\UpdateCenterService;
use InvalidArgumentException;
use Throwable;

final readonly class UpdateApiFacade
{
    public function __construct(private UpdateCenterService $updates) {}

    public function policy(string $extensionId, ?int $actorId = null): UpdateApiResponse
    {
        return $this->guard(fn () => new UpdateApiResponse(
            200,
            ['data'=>$this->updates->policy($extensionId, $actorId)->toArray()],
        ));
    }

    public function savePolicy(ExtensionUpdatePolicy $policy, ?int $actorId = null): UpdateApiResponse
    {
        return $this->guard(fn () => new UpdateApiResponse(
            200,
            ['data'=>$this->updates->savePolicy($policy, $actorId)->toArray()],
        ));
    }

    public function history(string $extensionId, int $limit = 100, ?int $actorId = null): UpdateApiResponse
    {
        return $this->guard(fn () => new UpdateApiResponse(
            200,
            ['data'=>$this->updates->history($extensionId, $limit, $actorId)],
        ));
    }

    public function recoveryPoints(string $extensionId, ?int $actorId = null): UpdateApiResponse
    {
        return $this->guard(fn () => new UpdateApiResponse(
            200,
            ['data'=>$this->updates->recoveryPoints($extensionId, $actorId)],
        ));
    }

    private function guard(callable $callback): UpdateApiResponse
    {
        try {
            return $callback();
        } catch (AuthorizationDeniedException) {
            return $this->error(403, UpdateApiErrorCode::Forbidden, 'Update operation is not permitted.');
        } catch (InvalidArgumentException $e) {
            return $this->error(422, UpdateApiErrorCode::InvalidRequest, $e->getMessage());
        } catch (\RuntimeException $e) {
            $message = strtolower($e->getMessage());
            if (str_contains($message, 'not found')) {
                return $this->error(404, UpdateApiErrorCode::NotFound, 'Update resource not found.');
            }
            return $this->error(409, UpdateApiErrorCode::Conflict, 'Update state conflict.');
        } catch (Throwable) {
            return $this->error(500, UpdateApiErrorCode::InternalError, 'Internal Update Center error.');
        }
    }

    private function error(int $status, UpdateApiErrorCode $code, string $message): UpdateApiResponse
    {
        return new UpdateApiResponse($status, [
            'error'=>['code'=>$code->value,'message'=>$message,'details'=>[]],
        ]);
    }
}

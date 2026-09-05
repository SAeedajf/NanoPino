<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Api\V1\Performance;

use App\com_pinoox_cms\Cms\Authorization\AuthorizationDeniedException;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationManager;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationRequest;
use App\com_pinoox_cms\Cms\Performance\PerformanceSnapshotService;
use Throwable;

final readonly class PerformanceApiFacade
{
    public function __construct(
        private AuthorizationManager $authorization,
        private PerformanceSnapshotService $performance,
    ) {}

    public function status(?int $actorId=null): PerformanceApiResponse
    {
        try {
            $this->authorization->authorize(new AuthorizationRequest('system.performance.view',$actorId));
            return new PerformanceApiResponse(200,['data'=>$this->performance->snapshot()]);
        } catch (AuthorizationDeniedException) {
            return new PerformanceApiResponse(403,[
                'error'=>['code'=>'performance.forbidden','message'=>'Performance telemetry is not permitted.','details'=>[]],
            ]);
        } catch (Throwable) {
            return new PerformanceApiResponse(500,[
                'error'=>['code'=>'performance.internal_error','message'=>'Internal performance telemetry error.','details'=>[]],
            ]);
        }
    }
}

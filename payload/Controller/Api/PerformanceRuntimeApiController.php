<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Controller\Api;

use App\com_pinoox_cms\Cms\Runtime\{CmsApiControllerResponder,CmsRuntimeServices};
use Pinoox\Component\Http\JsonResponse;
use Pinoox\Component\Kernel\Controller\ApiController;

final class PerformanceRuntimeApiController extends ApiController
{
    use CmsApiControllerResponder;

    public function index(): JsonResponse
    {
        return $this->facadeResponse(
            fn () => CmsRuntimeServices::performanceApi()->status(CmsRuntimeServices::actorId()),
            'PERFORMANCE_FAILED',
            'Performance telemetry could not be loaded.',
            'performance.index',
        );
    }
}

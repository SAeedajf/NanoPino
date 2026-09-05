<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Controller\Api;

use App\com_pinoox_cms\Cms\Runtime\CmsRuntimeServices;
use Pinoox\Component\Http\JsonResponse;
use Pinoox\Component\Kernel\Controller\ApiController;

final class PerformanceRuntimeApiController extends ApiController
{
    public function index(): JsonResponse
    {
        $response = CmsRuntimeServices::performanceApi()->status(CmsRuntimeServices::actorId());
        return new JsonResponse($response->body, $response->status);
    }
}

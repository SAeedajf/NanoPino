<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Controller\Api;

use App\com_pinoox_cms\Cms\Runtime\{CmsApiControllerResponder,CmsRequestPayload,CmsRuntimeServices};
use Pinoox\Component\Http\JsonResponse;
use Pinoox\Component\Http\Request;
use Pinoox\Component\Kernel\Controller\ApiController;

final class InfrastructureRuntimeApiController extends ApiController
{
    use CmsApiControllerResponder;

    public function status(): JsonResponse
    {
        return $this->facadeResponse(
            fn () => CmsRuntimeServices::infrastructureApi()->status(CmsRuntimeServices::actorId()),
            'INFRASTRUCTURE_STATUS_FAILED',
            'Infrastructure status could not be loaded.',
            'infrastructure.status',
        );
    }

    public function queue(): JsonResponse
    {
        return $this->facadeResponse(
            fn () => CmsRuntimeServices::infrastructureApi()->queue(CmsRuntimeServices::actorId()),
            'INFRASTRUCTURE_QUEUE_FAILED',
            'Infrastructure queue could not be loaded.',
            'infrastructure.queue',
        );
    }

    public function retryQueue(string $id): JsonResponse
    {
        return $this->facadeResponse(
            fn () => CmsRuntimeServices::infrastructureApi()->retryQueue($id, CmsRuntimeServices::actorId()),
            'INFRASTRUCTURE_QUEUE_RETRY_FAILED',
            'The infrastructure job could not be retried.',
            'infrastructure.queue.retry',
        );
    }

    public function invalidateTag(Request $request): JsonResponse
    {
        return $this->facadeResponse(
            function () use ($request) {
                $payload = $this->payload($request);
                return CmsRuntimeServices::infrastructureApi()->invalidateTag(
                trim((string)($payload['tag'] ?? '')),
                CmsRuntimeServices::actorId(),
                );
            },
            'INFRASTRUCTURE_CACHE_TAG_FAILED',
            'The cache tag could not be invalidated.',
            'infrastructure.cache.invalidate_tag',
        );
    }

    public function invalidateLayer(Request $request): JsonResponse
    {
        return $this->facadeResponse(
            function () use ($request) {
                $payload = $this->payload($request);
                return CmsRuntimeServices::infrastructureApi()->invalidateLayer(
                trim((string)($payload['layer'] ?? '')),
                CmsRuntimeServices::actorId(),
                );
            },
            'INFRASTRUCTURE_CACHE_LAYER_FAILED',
            'The cache layer could not be invalidated.',
            'infrastructure.cache.invalidate_layer',
        );
    }

    private function payload(Request $request): array
    {
        return CmsRequestPayload::read($request);
    }

}

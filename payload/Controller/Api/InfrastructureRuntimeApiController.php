<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Controller\Api;

use App\com_pinoox_cms\Cms\Runtime\CmsRuntimeServices;
use Pinoox\Component\Http\JsonResponse;
use Pinoox\Component\Http\Request;
use Pinoox\Component\Kernel\Controller\ApiController;

final class InfrastructureRuntimeApiController extends ApiController
{
    public function status(): JsonResponse
    {
        return $this->respond(CmsRuntimeServices::infrastructureApi()->status(CmsRuntimeServices::actorId()));
    }

    public function queue(): JsonResponse
    {
        return $this->respond(CmsRuntimeServices::infrastructureApi()->queue(CmsRuntimeServices::actorId()));
    }

    public function retryQueue(string $id): JsonResponse
    {
        return $this->respond(CmsRuntimeServices::infrastructureApi()->retryQueue($id, CmsRuntimeServices::actorId()));
    }

    public function invalidateTag(Request $request): JsonResponse
    {
        $payload = $this->payload($request);
        return $this->respond(CmsRuntimeServices::infrastructureApi()->invalidateTag(
            trim((string)($payload['tag'] ?? '')),
            CmsRuntimeServices::actorId(),
        ));
    }

    public function invalidateLayer(Request $request): JsonResponse
    {
        $payload = $this->payload($request);
        return $this->respond(CmsRuntimeServices::infrastructureApi()->invalidateLayer(
            trim((string)($payload['layer'] ?? '')),
            CmsRuntimeServices::actorId(),
        ));
    }

    private function payload(Request $request): array
    {
        try { $data = $request->toArray(); } catch (\Throwable) { $data = []; }
        return is_array($data) ? $data : [];
    }

    private function respond($response): JsonResponse
    {
        return new JsonResponse($response->body, $response->status);
    }
}

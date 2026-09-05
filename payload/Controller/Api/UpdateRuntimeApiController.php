<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Controller\Api;

use App\com_pinoox_cms\Cms\Runtime\CmsRuntimeServices;
use App\com_pinoox_cms\Cms\UpdatePolicy\AutoUpdateMode;
use App\com_pinoox_cms\Cms\UpdatePolicy\ExtensionUpdatePolicy;
use App\com_pinoox_cms\Cms\UpdatePolicy\UpdateChannel;
use Pinoox\Component\Http\JsonResponse;
use Pinoox\Component\Http\Request;
use Pinoox\Component\Kernel\Controller\ApiController;

final class UpdateRuntimeApiController extends ApiController
{
    public function policy(string $id): JsonResponse
    {
        return $this->respond(CmsRuntimeServices::updateApi()->policy($id, CmsRuntimeServices::actorId()));
    }

    public function savePolicy(Request $request, string $id): JsonResponse
    {
        $payload = $this->payload($request);
        try {
            $policy = new ExtensionUpdatePolicy(
                $id,
                UpdateChannel::from((string)($payload['channel'] ?? 'stable')),
                AutoUpdateMode::from((string)($payload['auto_update'] ?? 'disabled')),
                (bool)($payload['require_signature'] ?? false),
                (bool)($payload['allow_downgrade'] ?? false),
                (bool)($payload['snapshot_before_update'] ?? true),
                (bool)($payload['health_check_required'] ?? true),
            );
        } catch (\ValueError|\InvalidArgumentException) {
            return new JsonResponse([
                'error'=>[
                    'code'=>'update.invalid_request',
                    'message'=>'Update policy payload is invalid.',
                    'details'=>[],
                ],
            ], 422);
        }

        return $this->respond(CmsRuntimeServices::updateApi()->savePolicy($policy, CmsRuntimeServices::actorId()));
    }

    public function history(Request $request, string $id): JsonResponse
    {
        $limit = max(1, min(500, (int)$request->query->get('limit', 100)));
        return $this->respond(CmsRuntimeServices::updateApi()->history($id, $limit, CmsRuntimeServices::actorId()));
    }

    public function recoveryPoints(string $id): JsonResponse
    {
        return $this->respond(CmsRuntimeServices::updateApi()->recoveryPoints($id, CmsRuntimeServices::actorId()));
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

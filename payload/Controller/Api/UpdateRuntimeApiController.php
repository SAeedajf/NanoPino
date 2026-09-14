<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Controller\Api;

use App\com_pinoox_cms\Cms\Runtime\{CmsApiControllerResponder,CmsApiResponse,CmsRequestPayload,CmsRuntimeServices};
use App\com_pinoox_cms\Cms\UpdatePolicy\AutoUpdateMode;
use App\com_pinoox_cms\Cms\UpdatePolicy\ExtensionUpdatePolicy;
use App\com_pinoox_cms\Cms\UpdatePolicy\UpdateChannel;
use Pinoox\Component\Http\JsonResponse;
use Pinoox\Component\Http\Request;
use Pinoox\Component\Kernel\Controller\ApiController;

final class UpdateRuntimeApiController extends ApiController
{
    use CmsApiControllerResponder;

    public function policy(string $id): JsonResponse
    {
        return $this->facadeResponse(
            fn () => CmsRuntimeServices::updateApi()->policy($id, CmsRuntimeServices::actorId()),
            'UPDATE_POLICY_FAILED',
            'Update policy could not be loaded.',
            'update.policy',
        );
    }

    public function savePolicy(Request $request, string $id): JsonResponse
    {
        try {
            $payload = $this->payload($request);
            $policy = new ExtensionUpdatePolicy(
                $id,
                UpdateChannel::from((string)($payload['channel'] ?? 'stable')),
                AutoUpdateMode::from((string)($payload['auto_update'] ?? 'disabled')),
                true,
                (bool)($payload['allow_downgrade'] ?? false),
                (bool)($payload['snapshot_before_update'] ?? true),
                (bool)($payload['health_check_required'] ?? true),
            );
        } catch (\ValueError|\InvalidArgumentException) {
            return CmsApiResponse::error(
                'UPDATE_INVALID_REQUEST',
                'Update policy payload is invalid.',
                422,
            );
        }

        return $this->facadeResponse(
            fn () => CmsRuntimeServices::updateApi()->savePolicy($policy, CmsRuntimeServices::actorId()),
            'UPDATE_POLICY_SAVE_FAILED',
            'Update policy could not be saved.',
            'update.policy.save',
        );
    }

    public function history(Request $request, string $id): JsonResponse
    {
        $limit = max(1, min(500, (int)$request->query->get('limit', 100)));
        return $this->facadeResponse(
            fn () => CmsRuntimeServices::updateApi()->history($id, $limit, CmsRuntimeServices::actorId()),
            'UPDATE_HISTORY_FAILED',
            'Update history could not be loaded.',
            'update.history',
        );
    }

    public function recoveryPoints(string $id): JsonResponse
    {
        return $this->facadeResponse(
            fn () => CmsRuntimeServices::updateApi()->recoveryPoints($id, CmsRuntimeServices::actorId()),
            'UPDATE_RECOVERY_POINTS_FAILED',
            'Update recovery points could not be loaded.',
            'update.recovery_points',
        );
    }

    private function payload(Request $request): array
    {
        return CmsRequestPayload::read($request);
    }

}

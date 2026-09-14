<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Controller\Api;

use App\com_pinoox_cms\Cms\Runtime\{CmsApiControllerResponder,CmsRuntimeServices};
use Pinoox\Component\Http\JsonResponse;
use Pinoox\Component\Http\Request;
use Pinoox\Component\Kernel\Controller\ApiController;

final class SystemRuntimeApiController extends ApiController
{
    use CmsApiControllerResponder;

    public function health(): JsonResponse
    {
        return $this->facadeResponse(
            fn () => CmsRuntimeServices::systemHealthApi()->health(CmsRuntimeServices::actorId()),
            'SYSTEM_HEALTH_FAILED',
            'System health could not be loaded.',
            'system.health',
        );
    }

    public function healthHistory(Request $request): JsonResponse
    {
        $limit = max(1, min(200, (int)$request->query->get('limit', 20)));
        return $this->facadeResponse(
            fn () => CmsRuntimeServices::systemHealthApi()->history(
                CmsRuntimeServices::actorId(),
                $limit,
            ),
            'SYSTEM_HEALTH_HISTORY_FAILED',
            'System health history could not be loaded.',
            'system.health.history',
        );
    }

    public function logs(Request $request): JsonResponse
    {
        $limit = max(1, min(500, (int)$request->query->get('limit', 100)));
        $activeWindow = max(300, min(86400, (int)$request->query->get('active_window', 900)));
        $level = trim((string)$request->query->get('level', '')) ?: null;
        $channel = trim((string)$request->query->get('channel', '')) ?: null;
        $search = trim((string)$request->query->get('q', '')) ?: null;

        return $this->facadeResponse(
            fn () => CmsRuntimeServices::systemHealthApi()->logs(
                CmsRuntimeServices::actorId(),
                $limit,
                $activeWindow,
                $level,
                $channel,
                $search,
            ),
            'SYSTEM_LOGS_FAILED',
            'System logs could not be loaded.',
            'system.logs',
        );
    }

    public function supportBundle(): JsonResponse
    {
        return $this->facadeResponse(
            fn () => CmsRuntimeServices::systemHealthApi()->support(CmsRuntimeServices::actorId()),
            'SYSTEM_SUPPORT_FAILED',
            'Support diagnostics could not be created.',
            'system.support',
        );
    }
}

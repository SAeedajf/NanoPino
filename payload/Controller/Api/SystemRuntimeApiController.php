<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Controller\Api;

use App\com_pinoox_cms\Cms\Runtime\CmsRuntimeServices;
use Pinoox\Component\Http\JsonResponse;
use Pinoox\Component\Http\Request;
use Pinoox\Component\Kernel\Controller\ApiController;

final class SystemRuntimeApiController extends ApiController
{
    public function health(): JsonResponse
    {
        return $this->respond(CmsRuntimeServices::systemHealthApi()->health(CmsRuntimeServices::actorId()));
    }

    public function healthHistory(Request $request): JsonResponse
    {
        $limit = max(1, min(200, (int)$request->query->get('limit', 20)));
        return $this->respond(
            CmsRuntimeServices::systemHealthApi()->history(
                CmsRuntimeServices::actorId(),
                $limit,
            ),
        );
    }

    public function logs(Request $request): JsonResponse
    {
        $limit = max(1, min(500, (int)$request->query->get('limit', 100)));
        $activeWindow = max(300, min(86400, (int)$request->query->get('active_window', 900)));
        $level = trim((string)$request->query->get('level', '')) ?: null;
        $channel = trim((string)$request->query->get('channel', '')) ?: null;
        $search = trim((string)$request->query->get('q', '')) ?: null;

        return $this->respond(CmsRuntimeServices::systemHealthApi()->logs(
            CmsRuntimeServices::actorId(),
            $limit,
            $activeWindow,
            $level,
            $channel,
            $search,
        ));
    }

    public function supportBundle(): JsonResponse
    {
        return $this->respond(CmsRuntimeServices::systemHealthApi()->support(CmsRuntimeServices::actorId()));
    }

    private function respond($response): JsonResponse
    {
        return new JsonResponse($response->body, $response->status);
    }
}

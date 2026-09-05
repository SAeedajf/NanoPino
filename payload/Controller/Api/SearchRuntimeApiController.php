<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Controller\Api;

use App\com_pinoox_cms\Cms\Runtime\CmsRuntimeServices;
use Pinoox\Component\Http\JsonResponse;
use Pinoox\Component\Http\Request;
use Pinoox\Component\Kernel\Controller\ApiController;

final class SearchRuntimeApiController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $siteId = max(1, (int)$request->query->get('site_id', 1));
        $text = trim((string)$request->query->get('q', ''));
        $locale = trim((string)$request->query->get('locale', '')) ?: null;
        $limit = max(1, min(100, (int)$request->query->get('limit', 20)));
        $offset = max(0, min(100000, (int)$request->query->get('offset', 0)));
        $typesRaw = trim((string)$request->query->get('types', ''));
        $types = $typesRaw === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $typesRaw))));

        return $this->respond(CmsRuntimeServices::searchApi()->search(
            $siteId,
            $text,
            $types,
            $locale,
            $limit,
            $offset,
            CmsRuntimeServices::actorId(),
        ));
    }

    private function respond($response): JsonResponse
    {
        return new JsonResponse($response->body, $response->status);
    }
}

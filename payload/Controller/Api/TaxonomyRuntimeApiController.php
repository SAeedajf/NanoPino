<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Controller\Api;

use App\com_pinoox_cms\Cms\Authorization\AuthorizationDeniedException;
use App\com_pinoox_cms\Cms\Content\ContentValidationException;
use App\com_pinoox_cms\Cms\Runtime\CmsApiResponse;
use App\com_pinoox_cms\Cms\Runtime\CmsRuntimeErrorReporter;
use App\com_pinoox_cms\Cms\Runtime\CmsRuntimeServices;
use App\com_pinoox_cms\Cms\Taxonomy\TermRecord;
use Pinoox\Component\Http\JsonResponse;
use Pinoox\Component\Http\Request;
use Pinoox\Component\Kernel\Controller\ApiController;

final class TaxonomyRuntimeApiController extends ApiController
{
    public function terms(Request $request, string $key): JsonResponse
    {
        try {
            if (preg_match('/^[a-z][a-z0-9_-]{1,63}$/', $key) !== 1) {
                return CmsApiResponse::error('TAXONOMY_KEY_INVALID', 'Invalid taxonomy key.', 422);
            }

            $query = $request->query;
            $siteId = max(1, (int)$query->get('site_id', 1));
            $locale = trim((string)$query->get('locale', 'fa')) ?: 'fa';
            if (preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $locale) !== 1) {
                return CmsApiResponse::error('TAXONOMY_LOCALE_INVALID', 'Invalid taxonomy locale.', 422);
            }
            $search = trim((string)$query->get('search', ''));
            if ((function_exists('mb_strlen') ? mb_strlen($search) : strlen($search)) > 190) {
                return CmsApiResponse::error('TAXONOMY_SEARCH_INVALID', 'Taxonomy search is too long.', 422);
            }
            $limit = max(1, min(100, (int)$query->get('limit', 50)));
            $offset = max(0, min(100000, (int)$query->get('offset', 0)));

            $result = CmsRuntimeServices::taxonomy()->searchTerms(
                $key,
                $siteId,
                $locale,
                $search !== '' ? $search : null,
                $limit,
                $offset,
                CmsRuntimeServices::actorId(),
            );

            return CmsApiResponse::ok([
                'items' => array_map([$this, 'serialize'], $result['items']),
                'pagination' => [
                    'limit' => $result['limit'],
                    'offset' => $result['offset'],
                    'returned' => count($result['items']),
                    'total' => $result['total'],
                    'has_more' => $result['offset'] + count($result['items']) < $result['total'],
                ],
            ]);
        } catch (AuthorizationDeniedException) {
            return CmsApiResponse::error('FORBIDDEN', 'Taxonomy access is not permitted.', 403);
        } catch (ContentValidationException $e) {
            $notFound = str_contains(strtolower($e->getMessage()), 'not registered');
            return CmsApiResponse::error(
                $notFound ? 'TAXONOMY_NOT_FOUND' : 'TAXONOMY_QUERY_INVALID',
                $notFound ? 'Taxonomy not found.' : $e->getMessage(),
                $notFound ? 404 : 422,
            );
        } catch (\Throwable $e) {
            return CmsRuntimeErrorReporter::response(
                $e,
                'TAXONOMY_TERMS_FAILED',
                'Taxonomy terms could not be loaded.',
                500,
                ['operation' => 'taxonomy.terms', 'taxonomy' => $key],
            );
        }
    }

    /** @return array<string,mixed> */
    private function serialize(TermRecord $term): array
    {
        return [
            'id' => $term->id,
            'site_id' => $term->siteId,
            'taxonomy' => $term->taxonomy,
            'name' => $term->name,
            'slug' => $term->slug,
            'description' => $term->description,
            'parent_id' => $term->parentId,
            'locale' => $term->locale,
        ];
    }
}

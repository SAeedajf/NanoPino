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
    public function create(Request $request, string $key): JsonResponse
    {
        try {
            $this->assertKey($key);
            $payload = CmsRequestPayload::read($request);
            if (array_key_exists('metadata', $payload) && !is_array($payload['metadata'])) {
                throw new \InvalidArgumentException('Term metadata must be an object.');
            }
            $term = CmsRuntimeServices::taxonomy()->createTerm(
                $key,
                max(1, (int)($payload['site_id'] ?? $request->query->get('site_id', 1))),
                (string)($payload['name'] ?? ''),
                array_key_exists('slug', $payload) ? (string)$payload['slug'] : null,
                (string)($payload['description'] ?? ''),
                array_key_exists('parent_id', $payload) && $payload['parent_id'] !== null ? (int)$payload['parent_id'] : null,
                (string)($payload['locale'] ?? $request->query->get('locale', 'fa')),
                $payload['metadata'] ?? [],
                CmsRuntimeServices::actorId(),
                $request->headers->get('X-Correlation-ID'),
            );
            return CmsApiResponse::ok(['term' => $this->serialize($term)], 201);
        } catch (AuthorizationDeniedException) {
            return CmsApiResponse::error('FORBIDDEN', 'Taxonomy management is not permitted.', 403);
        } catch (ContentValidationException|\InvalidArgumentException $e) {
            return CmsApiResponse::error('TAXONOMY_VALIDATION_FAILED', $e->getMessage(), 422);
        } catch (\Throwable $e) {
            return CmsRuntimeErrorReporter::response($e, 'TAXONOMY_CREATE_FAILED', 'Term could not be created.', 500, ['operation' => 'taxonomy.term.create', 'taxonomy' => $key]);
        }
    }

    public function update(Request $request, string $key, string $id): JsonResponse
    {
        try {
            $this->assertKey($key);
            $termId = $this->id($id);
            $payload = CmsRequestPayload::read($request);
            $term = CmsRuntimeServices::taxonomy()->updateTerm(
                $termId,
                $payload,
                $key,
                CmsRuntimeServices::actorId(),
                $request->headers->get('X-Correlation-ID'),
            );
            return CmsApiResponse::ok(['term' => $this->serialize($term)]);
        } catch (AuthorizationDeniedException) {
            return CmsApiResponse::error('FORBIDDEN', 'Taxonomy management is not permitted.', 403);
        } catch (ContentValidationException|\InvalidArgumentException $e) {
            $status = str_contains(strtolower($e->getMessage()), 'not found') ? 404 : 422;
            return CmsApiResponse::error($status === 404 ? 'TERM_NOT_FOUND' : 'TAXONOMY_VALIDATION_FAILED', $e->getMessage(), $status);
        } catch (\Throwable $e) {
            return CmsRuntimeErrorReporter::response($e, 'TAXONOMY_UPDATE_FAILED', 'Term could not be updated.', 500, ['operation' => 'taxonomy.term.update', 'taxonomy' => $key]);
        }
    }

    public function delete(Request $request, string $key, string $id): JsonResponse
    {
        try {
            $this->assertKey($key);
            CmsRuntimeServices::taxonomy()->deleteTerm(
                $this->id($id),
                $key,
                CmsRuntimeServices::actorId(),
                $request->headers->get('X-Correlation-ID'),
            );
            return CmsApiResponse::ok(['deleted' => true]);
        } catch (AuthorizationDeniedException) {
            return CmsApiResponse::error('FORBIDDEN', 'Taxonomy management is not permitted.', 403);
        } catch (ContentValidationException|\InvalidArgumentException $e) {
            $message = strtolower($e->getMessage());
            $status = str_contains($message, 'not found') ? 404 : ((str_contains($message, 'assigned') || str_contains($message, 'child terms')) ? 409 : 422);
            $code = $status === 404 ? 'TERM_NOT_FOUND' : ($status === 409 ? (str_contains($message, 'child terms') ? 'TERM_HAS_CHILDREN' : 'TERM_IN_USE') : 'TAXONOMY_VALIDATION_FAILED');
            return CmsApiResponse::error($code, $e->getMessage(), $status);
        } catch (\Throwable $e) {
            return CmsRuntimeErrorReporter::response($e, 'TAXONOMY_DELETE_FAILED', 'Term could not be deleted.', 500, ['operation' => 'taxonomy.term.delete', 'taxonomy' => $key]);
        }
    }

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

    private function assertKey(string $key): void
    {
        if (preg_match('/^[a-z][a-z0-9_-]{1,63}$/', $key) !== 1) {
            throw new \InvalidArgumentException('Invalid taxonomy key.');
        }
    }

    private function id(string $id): int
    {
        if (preg_match('/^[1-9]\d*$/', $id) !== 1) {
            throw new \InvalidArgumentException('Invalid term id.');
        }
        return (int)$id;
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
            'metadata' => $term->metadata,
            'created_at' => $term->createdAt,
            'updated_at' => $term->updatedAt,
        ];
    }
}

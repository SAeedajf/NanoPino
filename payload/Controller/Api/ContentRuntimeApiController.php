<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Controller\Api;

use App\com_pinoox_cms\Cms\Authorization\AuthorizationDeniedException;
use App\com_pinoox_cms\Cms\Content\ContentProjection;
use App\com_pinoox_cms\Cms\Content\ContentConflictException;
use App\com_pinoox_cms\Cms\Content\ContentQuery;
use App\com_pinoox_cms\Cms\Content\ContentStatus;
use App\com_pinoox_cms\Cms\Content\ContentValidationException;
use App\com_pinoox_cms\Cms\Field\FieldValidationException;
use App\com_pinoox_cms\Cms\PublicSite\PublicContentUrl;
use App\com_pinoox_cms\Cms\Runtime\CmsApiResponse;
use App\com_pinoox_cms\Cms\Runtime\CmsRequestPayload;
use App\com_pinoox_cms\Cms\Runtime\CmsRuntimeErrorReporter;
use App\com_pinoox_cms\Cms\Runtime\CmsRuntimeServices;
use Pinoox\Component\Http\JsonResponse;
use Pinoox\Component\Http\Request;
use Pinoox\Component\Kernel\Controller\ApiController;

final class ContentRuntimeApiController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = $request->query;
            $statusRaw = trim((string) $query->get('status', ''));
            $status = $statusRaw !== '' ? ContentStatus::tryFrom($statusRaw) : null;

            if ($statusRaw !== '' && $status === null) {
                return CmsApiResponse::error('CONTENT_STATUS_INVALID', 'Invalid content status.', 422);
            }

            $projectionRaw = trim((string) $query->get('projection', ContentProjection::Detail->value));
            $projection = ContentProjection::tryFrom($projectionRaw);
            if ($projection === null) {
                return CmsApiResponse::error(
                    'CONTENT_PROJECTION_INVALID',
                    'Invalid content projection.',
                    422,
                );
            }

            $beforeIdRaw = trim((string) $query->get('before_id', ''));
            $beforeId = null;
            if ($beforeIdRaw !== '') {
                if (preg_match('/^[1-9]\d*$/', $beforeIdRaw) !== 1) {
                    return CmsApiResponse::error(
                        'CONTENT_CURSOR_INVALID',
                        'Invalid content cursor.',
                        422,
                    );
                }
                $beforeId = (int) $beforeIdRaw;
            }

            $limit = max(1, min(100, (int) $query->get('limit', 50)));
            $offset = $beforeId !== null
                ? 0
                : max(0, min(100000, (int) $query->get('offset', 0)));

            $contentQuery = new ContentQuery(
                siteId: max(1, (int) $query->get('site_id', 1)),
                type: trim((string) $query->get('type', '')) ?: null,
                status: $status,
                locale: trim((string) $query->get('locale', '')) ?: null,
                search: trim((string) $query->get('search', '')) ?: null,
                limit: min(101, $limit + 1),
                offset: $offset,
                projection: $projection,
                beforeId: $beforeId,
            );
            $fetched = CmsRuntimeServices::content()->search(
                $contentQuery,
                CmsRuntimeServices::actorId(),
            );
            $total = CmsRuntimeServices::content()->count(
                new ContentQuery(
                    siteId: $contentQuery->siteId,
                    type: $contentQuery->type,
                    status: $contentQuery->status,
                    locale: $contentQuery->locale,
                    search: $contentQuery->search,
                    projection: ContentProjection::List,
                ),
                CmsRuntimeServices::actorId(),
            );
            $hasMore = count($fetched) > $limit;
            $items = $hasMore ? array_slice($fetched, 0, $limit) : $fetched;

            return CmsApiResponse::ok([
                'items' => array_map(fn ($item): array => $this->recordPayload($item), $items),
                'types' => $this->contentTypeDescriptors(),
                'projection' => $projection->value,
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'before_id' => $beforeId,
                    'next_before_id' => $hasMore && $items !== []
                        ? $items[array_key_last($items)]->id
                        : null,
                    'returned' => count($items),
                    'total' => $total,
                    'has_more' => $hasMore,
                ],
            ]);
        } catch (AuthorizationDeniedException) {
            return CmsApiResponse::error('FORBIDDEN', 'Content access is not permitted.', 403);
        } catch (ContentValidationException|FieldValidationException $e) {
            return CmsApiResponse::error('CONTENT_QUERY_INVALID', $e->getMessage(), 422);
        } catch (\Throwable $e) {
            return CmsRuntimeErrorReporter::response(
                $e,
                'CONTENT_LIST_FAILED',
                'Content could not be loaded.',
                500,
                ['operation' => 'content.list'],
            );
        }
    }

    public function create(Request $request): JsonResponse
    {
        return $this->mutation(
            fn () => CmsRuntimeServices::content()->create(
                $this->requestPayload($request),
                CmsRuntimeServices::actorId(),
            ),
            201,
            'content.create',
        );
    }

    public function show(string $id): JsonResponse
    {
        try {
            $record = CmsRuntimeServices::content()->find($this->id($id), CmsRuntimeServices::actorId());
            return $record
                ? CmsApiResponse::ok($this->recordPayload($record))
                : CmsApiResponse::error('CONTENT_NOT_FOUND', 'Content not found.', 404);
        } catch (AuthorizationDeniedException) {
            return CmsApiResponse::error('FORBIDDEN', 'Content access is not permitted.', 403);
        } catch (\InvalidArgumentException) {
            return CmsApiResponse::error('CONTENT_ID_INVALID', 'Invalid content id.', 422);
        } catch (\Throwable $e) {
            return CmsRuntimeErrorReporter::response(
                $e,
                'CONTENT_READ_FAILED',
                'Content could not be loaded.',
                500,
                ['operation' => 'content.read'],
            );
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        return $this->mutation(
            fn () => CmsRuntimeServices::content()->update(
                $this->id($id),
                $this->requestPayload($request),
                CmsRuntimeServices::actorId(),
            ),
            200,
            'content.update',
        );
    }

    public function publish(string $id): JsonResponse
    {
        return $this->mutation(
            fn () => CmsRuntimeServices::content()->publish($this->id($id), CmsRuntimeServices::actorId()),
            200,
            'content.publish',
        );
    }

    public function submitReview(string $id): JsonResponse
    {
        return $this->mutation(
            fn () => CmsRuntimeServices::content()->submitForReview($this->id($id), CmsRuntimeServices::actorId()),
            200,
            'content.submit_review',
        );
    }

    public function approve(string $id): JsonResponse
    {
        return $this->mutation(
            fn () => CmsRuntimeServices::content()->approve($this->id($id), CmsRuntimeServices::actorId()),
            200,
            'content.approve',
        );
    }

    public function archive(string $id): JsonResponse
    {
        return $this->mutation(
            fn () => CmsRuntimeServices::content()->archive($this->id($id), CmsRuntimeServices::actorId()),
            200,
            'content.archive',
        );
    }

    public function schedule(Request $request, string $id): JsonResponse
    {
        try {
            $data = $this->requestPayload($request);
        } catch (\InvalidArgumentException $e) {
            return CmsApiResponse::error('CONTENT_VALIDATION_FAILED', $e->getMessage(), 422);
        }
        return $this->mutation(
            fn () => CmsRuntimeServices::content()->schedule(
                $this->id($id),
                trim((string) ($data['publish_at'] ?? '')),
                CmsRuntimeServices::actorId(),
            ),
            200,
            'content.schedule',
        );
    }

    public function trash(string $id): JsonResponse
    {
        return $this->mutation(
            fn () => CmsRuntimeServices::content()->moveToTrash($this->id($id), CmsRuntimeServices::actorId()),
            200,
            'content.trash',
        );
    }

    public function restoreDraft(string $id): JsonResponse
    {
        return $this->mutation(
            fn () => CmsRuntimeServices::content()->restoreDraft($this->id($id), CmsRuntimeServices::actorId()),
            200,
            'content.restore',
        );
    }

    public function revisions(string $id): JsonResponse
    {
        try {
            $items = CmsRuntimeServices::revisions()->historySummary(
                $this->id($id),
                CmsRuntimeServices::actorId(),
                100,
            );

            return CmsApiResponse::ok([
                'items' => array_map(static fn ($revision): array => [
                    'id' => $revision->id,
                    'kind' => $revision->kind->value,
                    'checksum' => $revision->checksum,
                    'actor_id' => $revision->actorId,
                    'source_revision_id' => $revision->sourceRevisionId,
                    'created_at' => $revision->createdAt,
                ], $items),
            ]);
        } catch (AuthorizationDeniedException) {
            return CmsApiResponse::error('FORBIDDEN', 'Revision access is not permitted.', 403);
        } catch (\InvalidArgumentException) {
            return CmsApiResponse::error('CONTENT_ID_INVALID', 'Invalid content id.', 422);
        } catch (\Throwable $e) {
            return CmsRuntimeErrorReporter::response(
                $e,
                'CONTENT_REVISIONS_FAILED',
                'Content revisions could not be loaded.',
                500,
                ['operation' => 'content.revisions'],
            );
        }
    }

    public function revision(string $id, string $revisionId): JsonResponse
    {
        try {
            $record = CmsRuntimeServices::revisions()->revisionForContent(
                $this->id($id),
                $this->id($revisionId),
                CmsRuntimeServices::actorId(),
            );

            return CmsApiResponse::ok([
                'id' => $record->id,
                'kind' => $record->kind->value,
                'checksum' => $record->checksum,
                'actor_id' => $record->actorId,
                'source_revision_id' => $record->sourceRevisionId,
                'created_at' => $record->createdAt,
                'snapshot' => $record->snapshot->payload(),
            ]);
        } catch (AuthorizationDeniedException) {
            return CmsApiResponse::error('FORBIDDEN', 'Revision access is not permitted.', 403);
        } catch (ContentValidationException|\InvalidArgumentException $e) {
            $notFound = str_contains(strtolower($e->getMessage()), 'not found');
            return CmsApiResponse::error(
                $notFound ? 'REVISION_NOT_FOUND' : 'REVISION_QUERY_INVALID',
                $notFound ? 'Revision not found.' : $e->getMessage(),
                $notFound ? 404 : 422,
            );
        } catch (\Throwable $e) {
            return CmsRuntimeErrorReporter::response(
                $e,
                'CONTENT_REVISION_READ_FAILED',
                'Content revision could not be loaded.',
                500,
                ['operation' => 'content.revision.read'],
            );
        }
    }

    public function restoreRevision(string $id, string $revisionId): JsonResponse
    {
        return $this->mutation(
            fn () => CmsRuntimeServices::revisions()->restoreForContent(
                $this->id($id),
                $this->id($revisionId),
                CmsRuntimeServices::actorId(),
            ),
            200,
            'content.revision.restore',
        );
    }

    /** @return list<array<string,mixed>> */
    private function contentTypeDescriptors(): array
    {
        $kernel = CmsRuntimeServices::kernel();
        $result = [];

        foreach ($kernel->contentTypes->definitions() as $type) {
            $fields = [];
            foreach ($type->fields as $fieldKey) {
                $field = $kernel->fields->definition($fieldKey);
                if ($field === null || !$field->writable()) {
                    continue;
                }

                $choices = is_array($field->options['choices'] ?? null)
                    ? $field->options['choices']
                    : [];

                $fields[] = [
                    'key' => $field->identifier(),
                    'label' => $field->label,
                    'type' => $field->type->value,
                    'storage' => $field->storage->value,
                    'required' => $field->required,
                    'multiple' => $field->multiple,
                    'default' => $field->default,
                    'choices' => $choices,
                    'taxonomy' => $field->type->value === 'taxonomy'
                        ? (string) ($field->options['taxonomy'] ?? '')
                        : null,
                    'target_types' => is_array($field->options['target_types'] ?? null)
                        ? array_values($field->options['target_types'])
                        : [],
                    'max_items' => isset($field->options['max_items'])
                        ? (int) $field->options['max_items']
                        : null,
                    'ui' => [
                        'component' => (string) ($field->ui['component'] ?? $field->type->value),
                        'order' => (int) ($field->ui['order'] ?? 100),
                    ],
                ];
            }

            usort($fields, static fn (array $a, array $b): int =>
                ($a['ui']['order'] <=> $b['ui']['order']) ?: strcmp((string) $a['key'], (string) $b['key'])
            );

            $result[] = [
                'key' => $type->identifier(),
                'label' => $type->label,
                'singular_label' => $type->singularLabel,
                'hierarchical' => $type->hierarchical,
                'revisions' => $type->revisions,
                'taxonomies' => $type->taxonomies,
                'fields' => $fields,
            ];
        }

        return $result;
    }

    private function mutation(callable $callback, int $status = 200, string $operation = 'content.mutation'): JsonResponse
    {
        try {
            $record = $callback();
            return CmsApiResponse::ok($this->recordPayload($record), $status);
        } catch (AuthorizationDeniedException) {
            return CmsApiResponse::error('FORBIDDEN', 'Content mutation is not permitted.', 403);
        } catch (ContentConflictException $e) {
            return CmsApiResponse::error('CONTENT_SLUG_CONFLICT', $e->getMessage(), 409);
        } catch (\LogicException $e) {
            return CmsApiResponse::error('CONTENT_TRANSITION_INVALID', $e->getMessage(), 409);
        } catch (ContentValidationException|FieldValidationException|\InvalidArgumentException $e) {
            $notFound = str_contains(strtolower($e->getMessage()), 'not found');
            return CmsApiResponse::error(
                $notFound ? 'CONTENT_NOT_FOUND' : 'CONTENT_VALIDATION_FAILED',
                $notFound ? 'Content not found.' : $e->getMessage(),
                $notFound ? 404 : 422,
            );
        } catch (\Throwable $e) {
            return CmsRuntimeErrorReporter::response(
                $e,
                'CONTENT_MUTATION_FAILED',
                'Content mutation failed.',
                500,
                ['operation' => $operation],
            );
        }
    }

    private function id(string $value): int
    {
        if (preg_match('/^[1-9]\d*$/', $value) !== 1) {
            throw new \InvalidArgumentException('Invalid numeric id.');
        }
        return (int) $value;
    }

    /** @return array<string,mixed> */
    private function recordPayload($record): array
    {
        $payload = $record->toArray();
        $payload['public_url'] = PublicContentUrl::path($record);
        return $payload;
    }

    /** @return array<string,mixed> */
    private function requestPayload(Request $request): array
    {
        return CmsRequestPayload::read($request);
    }
}

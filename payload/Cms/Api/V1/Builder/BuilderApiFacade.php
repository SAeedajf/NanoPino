<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Api\V1\Builder;

use App\com_pinoox_cms\Cms\Authorization\AuthorizationDeniedException;
use App\com_pinoox_cms\Cms\Block\Document\BlockDocumentValidationException;
use App\com_pinoox_cms\Cms\Block\Render\BlockRenderContext;
use App\com_pinoox_cms\Cms\Builder\BuilderConcurrencyException;
use App\com_pinoox_cms\Cms\Builder\BuilderDocumentRecord;
use App\com_pinoox_cms\Cms\Builder\BuilderService;
use App\com_pinoox_cms\Cms\Builder\BuilderTarget;
use App\com_pinoox_cms\Cms\Builder\BuilderTargetType;
use App\com_pinoox_cms\Cms\Builder\Preview\BuilderPreviewService;
use App\com_pinoox_cms\Cms\Builder\Revision\BuilderRevisionRecord;
use App\com_pinoox_cms\Cms\Runtime\CmsRuntimeErrorReporter;
use InvalidArgumentException;
use Illuminate\Database\QueryException;
use PDOException;
use Throwable;

final readonly class BuilderApiFacade
{
    public function __construct(
        private BuilderService $builder,
        private BuilderPreviewService $preview,
    ) {}

    /** @param array<string,mixed> $query */
    public function index(array $query, ?int $actorId = null): BuilderApiResponse
    {
        return $this->guard(function () use ($query, $actorId): BuilderApiResponse {
            $siteId = max(0, (int)($query['site_id'] ?? 0));
            if ($siteId < 1) {
                throw new InvalidArgumentException('site_id must be a positive integer.');
            }

            $rawType = trim((string)($query['type'] ?? ''));
            $type = $rawType !== '' ? BuilderTargetType::tryFrom($rawType) : null;
            if ($rawType !== '' && $type === null) {
                throw new InvalidArgumentException('Invalid Builder target type filter.');
            }

            $locale = trim((string)($query['locale'] ?? ''));
            if ($locale !== '' && preg_match('/^[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})?$/', $locale) !== 1) {
                throw new InvalidArgumentException('Invalid Builder locale filter.');
            }

            $limit = max(1, min(200, (int)($query['limit'] ?? 100)));
            $offset = max(0, (int)($query['offset'] ?? 0));
            $items = $this->builder->listDocuments($siteId, $type, $locale !== '' ? $locale : null, $limit, $offset, $actorId);
            $total = $this->builder->countDocuments($siteId, $type, $locale !== '' ? $locale : null, $actorId);

            $rows = array_map(fn (BuilderDocumentRecord $record): array => $this->record($record), $items);
            $status = [];
            foreach ($rows as $row) {
                $key = (string)($row['status'] ?? 'unknown');
                $status[$key] = ($status[$key] ?? 0) + 1;
            }

            return new BuilderApiResponse(200, ['data' => [
                'items' => $rows,
                'summary' => [
                    'total' => $total,
                    'visible' => count($rows),
                    'status' => $status,
                ],
                'target_types' => array_map(static fn (BuilderTargetType $case): string => $case->value, BuilderTargetType::cases()),
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'total' => $total,
                    'has_more' => $offset + count($rows) < $total,
                ],
            ]]);
        });
    }

    /** @param array<string,mixed> $payload */
    public function open(array $payload, ?int $actorId = null): BuilderApiResponse
    {
        return $this->guard(function () use ($payload,$actorId): BuilderApiResponse {
            $target=$this->target($payload['target']??null);
            $record=$this->builder->findByTarget($target,$actorId);
            if($record===null){
                $raw=$payload['document']??['version'=>1,'blocks'=>[]];
                $record=$this->builder->create($target,$this->document($raw),$actorId);
                return new BuilderApiResponse(201,['data'=>$this->record($record),'created'=>true],$this->versionHeaders($record));
            }
            return new BuilderApiResponse(200,['data'=>$this->record($record),'created'=>false],$this->versionHeaders($record));
        });
    }

    /** @param array<string,mixed> $payload */
    public function create(array $payload, ?int $actorId = null): BuilderApiResponse
    {
        return $this->guard(function () use ($payload, $actorId): BuilderApiResponse {
            $target = $this->target($payload['target'] ?? null);
            $document = $this->document($payload['document'] ?? null);
            $record = $this->builder->create($target, $document, $actorId);

            return new BuilderApiResponse(
                201,
                ['data' => $this->record($record)],
                $this->versionHeaders($record),
            );
        });
    }

    public function read(int $id, ?int $actorId = null): BuilderApiResponse
    {
        return $this->guard(function () use ($id, $actorId): BuilderApiResponse {
            $record = $this->builder->find($id, $actorId);
            if ($record === null) {
                return $this->error(404, BuilderApiErrorCode::NotFound, 'Builder document not found.');
            }

            return new BuilderApiResponse(
                200,
                ['data' => $this->record($record)],
                $this->versionHeaders($record),
            );
        });
    }

    /** @param array<string,mixed> $payload */
    public function save(int $id, array $payload, ?int $actorId = null): BuilderApiResponse
    {
        return $this->guard(function () use ($id, $payload, $actorId): BuilderApiResponse {
            $record = $this->builder->save(
                $id,
                $this->document($payload['document'] ?? null),
                $this->expectedVersion($payload),
                $actorId,
            );

            return new BuilderApiResponse(
                200,
                ['data' => $this->record($record)],
                $this->versionHeaders($record),
            );
        });
    }

    /** @param array<string,mixed> $payload */
    public function autosave(int $id, array $payload, ?int $actorId = null): BuilderApiResponse
    {
        return $this->guard(function () use ($id, $payload, $actorId): BuilderApiResponse {
            $revision = $this->builder->autosave(
                $id,
                $this->document($payload['document'] ?? null),
                $actorId,
            );

            return new BuilderApiResponse(200, ['data' => $this->revision($revision)]);
        });
    }

    /** @param array<string,mixed> $payload */
    public function publish(int $id, array $payload, ?int $actorId = null): BuilderApiResponse
    {
        return $this->guard(function () use ($id, $payload, $actorId): BuilderApiResponse {
            $record = $this->builder->publish(
                $id,
                $this->expectedVersion($payload),
                $actorId,
            );

            return new BuilderApiResponse(
                200,
                ['data' => $this->record($record)],
                $this->versionHeaders($record),
            );
        });
    }

    public function revisions(int $id, int $limit = 100, ?int $actorId = null): BuilderApiResponse
    {
        return $this->guard(function () use ($id, $limit, $actorId): BuilderApiResponse {
            $items = $this->builder->history($id, max(1, min(200, $limit)), $actorId);
            return new BuilderApiResponse(200, [
                'data' => array_map(fn (BuilderRevisionRecord $record): array => $this->revision($record), $items),
            ]);
        });
    }

    /** @param array<string,mixed> $payload */
    public function restore(
        int $id,
        int $revisionId,
        array $payload,
        ?int $actorId = null,
    ): BuilderApiResponse {
        return $this->guard(function () use ($id, $revisionId, $payload, $actorId): BuilderApiResponse {
            $record = $this->builder->restore(
                $id,
                $revisionId,
                $this->expectedVersion($payload),
                $actorId,
            );

            return new BuilderApiResponse(
                200,
                ['data' => $this->record($record)],
                $this->versionHeaders($record),
            );
        });
    }

    /** @param array<string,mixed> $payload */
    public function preview(array $payload, ?int $actorId = null): BuilderApiResponse
    {
        return $this->guard(function () use ($payload, $actorId): BuilderApiResponse {
            $siteId = (int)($payload['site_id'] ?? 0);
            if ($siteId < 1) {
                throw new InvalidArgumentException('site_id must be a positive integer.');
            }

            $locale = trim((string)($payload['locale'] ?? 'fa'));
            if (preg_match('/^[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})?$/', $locale) !== 1) {
                throw new InvalidArgumentException('Invalid preview locale.');
            }

            $contentId = isset($payload['content_id']) ? (int)$payload['content_id'] : null;
            if ($contentId !== null && $contentId < 1) {
                throw new InvalidArgumentException('content_id must be positive.');
            }

            $result = $this->preview->preview(
                $siteId,
                $this->document($payload['document'] ?? null),
                new BlockRenderContext($siteId, $contentId, $locale),
                $actorId,
            );

            return new BuilderApiResponse(200, [
                'data' => [
                    'html' => $result->html,
                    'checksum' => $result->checksum,
                    'cache_tags' => $result->cacheTags,
                ],
            ]);
        });
    }

    private function guard(callable $callback): BuilderApiResponse
    {
        try {
            return $callback();
        } catch (BuilderConcurrencyException $error) {
            return $this->error(409, BuilderApiErrorCode::VersionConflict, $error->getMessage());
        } catch (AuthorizationDeniedException $error) {
            return $this->error(403, BuilderApiErrorCode::Forbidden, 'Builder operation is not permitted.');
        } catch (BlockDocumentValidationException $error) {
            return $this->error(
                422,
                BuilderApiErrorCode::ValidationFailed,
                'Builder document validation failed.',
                ['violations' => $error->violations],
            );
        } catch (InvalidArgumentException $error) {
            return $this->error(422, BuilderApiErrorCode::InvalidRequest, $error->getMessage());
        } catch (QueryException|PDOException $error) {
            return $this->internalError($error);
        } catch (\RuntimeException $error) {
            if (str_contains(strtolower($error->getMessage()), 'not found')) {
                return $this->error(404, BuilderApiErrorCode::NotFound, 'Builder resource not found.');
            }

            // Runtime/domain messages may contain implementation details.
            // Public API keeps a stable generic error surface.
            return $this->error(
                422,
                BuilderApiErrorCode::ValidationFailed,
                'Builder operation could not be completed.',
            );
        } catch (Throwable $error) {
            return $this->internalError($error);
        }
    }

    private function internalError(Throwable $error): BuilderApiResponse
    {
        $reported = CmsRuntimeErrorReporter::response(
            $error,
            'BUILDER_INTERNAL_ERROR',
            'Internal Builder error.',
            500,
            ['operation' => 'builder.runtime'],
        );
        $payload = json_decode((string)$reported->getContent(), true);
        $body = is_array($payload) ? $payload : [];
        unset($body['success']);

        return new BuilderApiResponse(
            500,
            $body !== [] ? $body : ['error' => [
                'code' => BuilderApiErrorCode::InternalError->value,
                'message' => 'Internal Builder error.',
                'details' => [],
            ]],
            [
                'X-CMS-Error-ID' => (string)$reported->headers->get('X-CMS-Error-ID', ''),
                'X-CMS-Error-Category' => (string)$reported->headers->get('X-CMS-Error-Category', 'runtime'),
            ],
        );
    }

    private function error(
        int $status,
        BuilderApiErrorCode $code,
        string $message,
        array $details = [],
    ): BuilderApiResponse {
        return new BuilderApiResponse($status, [
            'error' => [
                'code' => $code->value,
                'message' => $message,
                'details' => $details,
            ],
        ]);
    }

    /** @return array<string,string> */
    private function versionHeaders(BuilderDocumentRecord $record): array
    {
        return [
            'ETag' => '"' . $record->checksum . '"',
            'X-Builder-Version' => (string)$record->version,
        ];
    }

    /** @return array<string,mixed> */
    private function record(BuilderDocumentRecord $record): array
    {
        return [
            'id' => $record->id,
            'target' => [
                'site_id' => $record->target->siteId,
                'type' => $record->target->type->value,
                'key' => $record->target->key,
                'locale' => $record->target->locale,
            ],
            'status' => $record->status->value,
            'document' => $record->document->toArray(),
            'checksum' => $record->checksum,
            'version' => $record->version,
            'published_at' => $record->publishedAt,
            'created_at' => $record->createdAt,
            'updated_at' => $record->updatedAt,
        ];
    }

    /** @return array<string,mixed> */
    private function revision(BuilderRevisionRecord $record): array
    {
        return [
            'id' => $record->id,
            'builder_id' => $record->builderId,
            'kind' => $record->kind->value,
            'document' => $record->document->toArray(),
            'checksum' => $record->checksum,
            'source_revision_id' => $record->sourceRevisionId,
            'created_at' => $record->createdAt,
        ];
    }

    private function target(mixed $raw): BuilderTarget
    {
        if (!is_array($raw)) {
            throw new InvalidArgumentException('target must be an object.');
        }

        $siteId = (int)($raw['site_id'] ?? 0);
        $type = BuilderTargetType::tryFrom((string)($raw['type'] ?? ''));
        $key = (string)($raw['key'] ?? '');
        $locale = (string)($raw['locale'] ?? 'fa');

        if ($siteId < 1 || $type === null) {
            throw new InvalidArgumentException('Invalid Builder target.');
        }

        return new BuilderTarget($siteId, $type, $key, $locale);
    }

    /** @return array<string,mixed> */
    private function document(mixed $raw): array
    {
        if (!is_array($raw)) {
            throw new InvalidArgumentException('document must be an object.');
        }
        return $raw;
    }

    /** @param array<string,mixed> $payload */
    private function expectedVersion(array $payload): int
    {
        $version = (int)($payload['expected_version'] ?? 0);
        if ($version < 1) {
            throw new InvalidArgumentException('expected_version is required and must be >= 1.');
        }
        return $version;
    }
}

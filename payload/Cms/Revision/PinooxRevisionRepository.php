<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Revision;

use App\com_pinoox_cms\Model\ContentRevisionModel;

final class PinooxRevisionRepository implements RevisionRepositoryInterface
{
    public function append(
        RevisionSnapshot $snapshot,
        RevisionKind $kind,
        string $checksum,
        ?int $actorId,
        ?int $sourceRevisionId = null,
    ): RevisionRecord {
        $createdAt = gmdate('Y-m-d H:i:s');
        $model = ContentRevisionModel::create([
            'content_id' => $snapshot->contentId,
            'site_id' => $snapshot->siteId,
            'content_type' => $snapshot->contentType,
            'kind' => $kind->value,
            'actor_id' => $actorId,
            'schema_version' => $snapshot->schemaVersion,
            'checksum' => $checksum,
            'source_revision_id' => $sourceRevisionId,
            'payload_json' => json_encode(
                $snapshot->payload(),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ),
            'created_at' => $createdAt,
        ]);

        return $this->hydrate($model);
    }

    public function find(int $revisionId): ?RevisionRecord
    {
        $model = ContentRevisionModel::find($revisionId);
        return $model ? $this->hydrate($model) : null;
    }

    public function forContent(int $contentId, int $limit = 100): array
    {
        return ContentRevisionModel::query()
            ->where('content_id', $contentId)
            ->orderByDesc('id')
            ->limit(max(1, min(500, $limit)))
            ->get()
            ->map(fn (ContentRevisionModel $model): RevisionRecord => $this->hydrate($model))
            ->all();
    }

    public function latestAutosave(int $contentId, ?int $actorId): ?RevisionRecord
    {
        $query = ContentRevisionModel::query()
            ->where('content_id', $contentId)
            ->where('kind', RevisionKind::Autosave->value);

        if ($actorId === null) {
            $query->whereNull('actor_id');
        } else {
            $query->where('actor_id', $actorId);
        }

        $model = $query->orderByDesc('id')->first();
        return $model ? $this->hydrate($model) : null;
    }

    private function hydrate(ContentRevisionModel $model): RevisionRecord
    {
        $payload = json_decode((string)$model->payload_json, true, 512, JSON_THROW_ON_ERROR);

        $snapshot = new RevisionSnapshot(
            (int)$payload['content_id'],
            (int)$payload['site_id'],
            (string)$payload['content_type'],
            (string)$payload['status'],
            (string)$payload['title'],
            (string)$payload['slug'],
            (string)($payload['excerpt'] ?? ''),
            isset($payload['author_id']) ? (int)$payload['author_id'] : null,
            isset($payload['parent_id']) ? (int)$payload['parent_id'] : null,
            (string)$payload['locale'],
            is_array($payload['document'] ?? null) ? $payload['document'] : [],
            is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [],
            is_array($payload['fields'] ?? null) ? $payload['fields'] : [],
            is_array($payload['relations'] ?? null) ? $payload['relations'] : [],
            is_array($payload['terms'] ?? null) ? $payload['terms'] : [],
            $payload['published_at'] ?? null,
            $payload['scheduled_at'] ?? null,
            (int)($payload['schema_version'] ?? $model->schema_version ?? 1),
        );

        $record = new RevisionRecord(
            (int)$model->id,
            $snapshot,
            RevisionKind::from((string)$model->kind),
            (string)$model->checksum,
            $model->actor_id !== null ? (int)$model->actor_id : null,
            $model->source_revision_id !== null ? (int)$model->source_revision_id : null,
            (string)$model->created_at,
        );

        $actual = (new RevisionChecksum())->make($snapshot);
        if (!hash_equals($record->checksum, $actual)) {
            throw new RevisionIntegrityException(
                'Stored revision checksum mismatch for revision #' . $record->id
            );
        }

        return $record;
    }
}

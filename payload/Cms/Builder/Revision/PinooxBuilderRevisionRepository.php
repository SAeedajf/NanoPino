<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\Revision;

use App\com_pinoox_cms\Cms\Block\Document\BlockDocument;
use App\com_pinoox_cms\Cms\Block\Document\BlockDocumentParser;
use App\com_pinoox_cms\Cms\Block\Document\BlockDocumentSerializer;
use App\com_pinoox_cms\Model\BuilderRevisionModel;

final class PinooxBuilderRevisionRepository implements BuilderRevisionRepositoryInterface
{
    public function __construct(
        private readonly BlockDocumentParser $parser = new BlockDocumentParser(),
        private readonly BlockDocumentSerializer $serializer = new BlockDocumentSerializer(),
    ) {}

    public function append(
        int $builderId,
        BuilderRevisionKind $kind,
        BlockDocument $document,
        string $checksum,
        ?int $actorId,
        ?int $sourceRevisionId = null,
    ): BuilderRevisionRecord {
        $model = BuilderRevisionModel::create([
            'builder_id' => $builderId,
            'kind' => $kind->value,
            'actor_id' => $actorId,
            'source_revision_id' => $sourceRevisionId,
            'document_json' => $this->serializer->json($document),
            'checksum' => $checksum,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        return $this->hydrate($model);
    }

    public function find(int $revisionId): ?BuilderRevisionRecord
    {
        $model = BuilderRevisionModel::find($revisionId);
        return $model ? $this->hydrate($model) : null;
    }

    public function forBuilder(int $builderId, int $limit = 100): array
    {
        return BuilderRevisionModel::query()
            ->where('builder_id', $builderId)
            ->orderByDesc('id')
            ->limit(max(1, min(500, $limit)))
            ->get()
            ->map(fn (BuilderRevisionModel $model): BuilderRevisionRecord => $this->hydrate($model))
            ->all();
    }

    public function latestAutosave(int $builderId, ?int $actorId): ?BuilderRevisionRecord
    {
        $query = BuilderRevisionModel::query()
            ->where('builder_id', $builderId)
            ->where('kind', BuilderRevisionKind::Autosave->value);

        if ($actorId === null) {
            $query->whereNull('actor_id');
        } else {
            $query->where('actor_id', $actorId);
        }

        $model = $query->orderByDesc('id')->first();
        return $model ? $this->hydrate($model) : null;
    }

    public function latestPublished(int $builderId): ?BuilderRevisionRecord
    {
        $model = BuilderRevisionModel::query()
            ->where('builder_id', $builderId)
            ->where('kind', BuilderRevisionKind::Published->value)
            ->orderByDesc('id')
            ->first();

        return $model ? $this->hydrate($model) : null;
    }

    private function hydrate(BuilderRevisionModel $model): BuilderRevisionRecord
    {
        try {
            $raw = json_decode((string)$model->document_json, true, 128, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new \RuntimeException('Stored Builder revision contains invalid JSON.', 0, $error);
        }

        if (!is_array($raw)) {
            throw new \RuntimeException('Stored Builder revision root is invalid.');
        }

        $document = $this->parser->parse($raw);
        $actual = $this->serializer->checksum($document);
        $stored = (string)$model->checksum;

        if (!hash_equals($stored, $actual)) {
            throw new \RuntimeException('Builder revision checksum mismatch for #' . (int)$model->id);
        }

        return new BuilderRevisionRecord(
            (int)$model->id,
            (int)$model->builder_id,
            BuilderRevisionKind::from((string)$model->kind),
            $document,
            $stored,
            $model->actor_id !== null ? (int)$model->actor_id : null,
            $model->source_revision_id !== null ? (int)$model->source_revision_id : null,
            (string)$model->created_at,
        );
    }
}

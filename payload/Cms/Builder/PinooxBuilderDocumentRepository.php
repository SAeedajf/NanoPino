<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder;

use App\com_pinoox_cms\Cms\Block\Document\BlockDocument;
use App\com_pinoox_cms\Cms\Block\Document\BlockDocumentParser;
use App\com_pinoox_cms\Cms\Block\Document\BlockDocumentSerializer;
use App\com_pinoox_cms\Model\BuilderDocumentModel;

final class PinooxBuilderDocumentRepository implements BuilderDocumentRepositoryInterface
{
    public function __construct(
        private readonly BlockDocumentParser $parser = new BlockDocumentParser(),
        private readonly BlockDocumentSerializer $serializer = new BlockDocumentSerializer(),
    ) {}

    public function create(
        BuilderTarget $target,
        BlockDocument $document,
        string $checksum,
        ?int $actorId,
    ): BuilderDocumentRecord {
        if ($this->findByTarget($target) !== null) {
            throw new BuilderConcurrencyException('Builder target already has a document.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $model = BuilderDocumentModel::create([
            'site_id' => $target->siteId,
            'target_type' => $target->type->value,
            'target_key' => $target->key,
            'locale' => $target->locale,
            'status' => BuilderStatus::Draft->value,
            'document_json' => $this->serializer->json($document),
            'checksum' => $checksum,
            'version' => 1,
            'actor_id' => $actorId,
            'published_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->hydrate($model);
    }

    public function find(int $id): ?BuilderDocumentRecord
    {
        $model = BuilderDocumentModel::find($id);
        return $model ? $this->hydrate($model) : null;
    }

    public function findByTarget(BuilderTarget $target): ?BuilderDocumentRecord
    {
        $model = BuilderDocumentModel::query()
            ->where('site_id', $target->siteId)
            ->where('target_type', $target->type->value)
            ->where('target_key', $target->key)
            ->where('locale', $target->locale)
            ->first();

        return $model ? $this->hydrate($model) : null;
    }

    /** @return list<BuilderDocumentRecord> */
    public function list(
        int $siteId,
        ?BuilderTargetType $type = null,
        ?string $locale = null,
        int $limit = 100,
        int $offset = 0,
    ): array {
        $query = BuilderDocumentModel::query()->where('site_id', $siteId);
        if ($type !== null) {
            $query->where('target_type', $type->value);
        }
        if ($locale !== null && $locale !== '') {
            $query->where('locale', $locale);
        }

        return $query
            ->orderBy('target_type')
            ->orderBy('target_key')
            ->orderBy('locale')
            ->orderBy('id')
            ->offset(max(0, $offset))
            ->limit(max(1, min(200, $limit)))
            ->get()
            ->map(fn (BuilderDocumentModel $model): BuilderDocumentRecord => $this->hydrate($model))
            ->all();
    }

    public function count(
        int $siteId,
        ?BuilderTargetType $type = null,
        ?string $locale = null,
    ): int {
        $query = BuilderDocumentModel::query()->where('site_id', $siteId);
        if ($type !== null) {
            $query->where('target_type', $type->value);
        }
        if ($locale !== null && $locale !== '') {
            $query->where('locale', $locale);
        }
        return (int) $query->count();
    }

    public function save(
        int $id,
        BlockDocument $document,
        string $checksum,
        int $expectedVersion,
        BuilderStatus $status,
        ?int $actorId,
        ?string $publishedAt = null,
    ): BuilderDocumentRecord {
        $updatedAt = gmdate('Y-m-d H:i:s');

        $updated = BuilderDocumentModel::query()
            ->where('id', $id)
            ->where('version', $expectedVersion)
            ->update([
                'status' => $status->value,
                'document_json' => $this->serializer->json($document),
                'checksum' => $checksum,
                'version' => $expectedVersion + 1,
                'actor_id' => $actorId,
                'published_at' => $publishedAt,
                'updated_at' => $updatedAt,
            ]);

        if ($updated !== 1) {
            if (!BuilderDocumentModel::query()->where('id', $id)->exists()) {
                throw new \RuntimeException('Builder document not found.');
            }
            throw new BuilderConcurrencyException('Builder document version conflict.');
        }

        return $this->find($id)
            ?? throw new \RuntimeException('Builder document disappeared after save.');
    }

    private function hydrate(BuilderDocumentModel $model): BuilderDocumentRecord
    {
        try {
            $raw = json_decode((string)$model->document_json, true, 128, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new \RuntimeException('Stored Builder document contains invalid JSON.', 0, $error);
        }

        if (!is_array($raw)) {
            throw new \RuntimeException('Stored Builder document root is invalid.');
        }

        $document = $this->parser->parse($raw);
        $actual = $this->serializer->checksum($document);
        $stored = (string)$model->checksum;

        if (!hash_equals($stored, $actual)) {
            throw new \RuntimeException('Builder document checksum mismatch for #' . (int)$model->id);
        }

        return new BuilderDocumentRecord(
            (int)$model->id,
            new BuilderTarget(
                (int)$model->site_id,
                BuilderTargetType::from((string)$model->target_type),
                (string)$model->target_key,
                (string)$model->locale,
            ),
            BuilderStatus::from((string)$model->status),
            $document,
            $stored,
            (int)$model->version,
            $model->actor_id !== null ? (int)$model->actor_id : null,
            $model->published_at !== null ? (string)$model->published_at : null,
            (string)$model->created_at,
            (string)$model->updated_at,
        );
    }
}

<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\GlobalBlock;

use App\com_pinoox_cms\Cms\Block\Document\BlockDocument;
use App\com_pinoox_cms\Cms\Block\Document\BlockDocumentParser;
use App\com_pinoox_cms\Cms\Block\Document\BlockDocumentSerializer;
use App\com_pinoox_cms\Cms\Builder\BuilderConcurrencyException;
use App\com_pinoox_cms\Model\GlobalBlockModel;

final class PinooxGlobalBlockRepository implements GlobalBlockRepositoryInterface
{
    public function __construct(
        private readonly BlockDocumentParser $parser = new BlockDocumentParser(),
        private readonly BlockDocumentSerializer $serializer = new BlockDocumentSerializer(),
    ) {}

    public function create(
        int $siteId,
        string $name,
        BlockDocument $document,
        string $checksum,
        ?int $actorId,
    ): GlobalBlockRecord {
        $now = gmdate('Y-m-d H:i:s');
        $model = GlobalBlockModel::create([
            'site_id' => $siteId,
            'name' => $name,
            'document_json' => $this->serializer->json($document),
            'checksum' => $checksum,
            'version' => 1,
            'actor_id' => $actorId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return $this->hydrate($model);
    }

    public function find(int $id): ?GlobalBlockRecord
    {
        $model = GlobalBlockModel::find($id);
        return $model ? $this->hydrate($model) : null;
    }

    public function forSite(int $siteId, int $limit = 200): array
    {
        return GlobalBlockModel::query()
            ->where('site_id', $siteId)
            ->orderByDesc('id')
            ->limit(max(1, min(500, $limit)))
            ->get()
            ->map(fn (GlobalBlockModel $model): GlobalBlockRecord => $this->hydrate($model))
            ->all();
    }

    public function save(
        int $id,
        string $name,
        BlockDocument $document,
        string $checksum,
        int $expectedVersion,
        ?int $actorId,
    ): GlobalBlockRecord {
        $updated = GlobalBlockModel::query()
            ->where('id', $id)
            ->where('version', $expectedVersion)
            ->update([
                'name' => $name,
                'document_json' => $this->serializer->json($document),
                'checksum' => $checksum,
                'version' => $expectedVersion + 1,
                'actor_id' => $actorId,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);

        if ($updated !== 1) {
            if (!GlobalBlockModel::query()->where('id', $id)->exists()) {
                throw new \RuntimeException('Global Block not found.');
            }
            throw new BuilderConcurrencyException('Global Block version conflict.');
        }

        return $this->find($id)
            ?? throw new \RuntimeException('Global Block disappeared after save.');
    }

    private function hydrate(GlobalBlockModel $model): GlobalBlockRecord
    {
        $raw = json_decode((string)$model->document_json, true, 128, JSON_THROW_ON_ERROR);
        if (!is_array($raw)) throw new \RuntimeException('Stored Global Block JSON is invalid.');
        $document = $this->parser->parse($raw);
        $checksum = $this->serializer->checksum($document);
        if (!hash_equals((string)$model->checksum, $checksum)) {
            throw new \RuntimeException('Global Block checksum mismatch.');
        }

        return new GlobalBlockRecord(
            (int)$model->id,
            (int)$model->site_id,
            (string)$model->name,
            $document,
            $checksum,
            (int)$model->version,
            $model->actor_id !== null ? (int)$model->actor_id : null,
            (string)$model->created_at,
            (string)$model->updated_at,
        );
    }
}

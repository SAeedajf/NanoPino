<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Taxonomy;

use App\com_pinoox_cms\Model\ContentTermModel;
use App\com_pinoox_cms\Model\TermModel;
use App\com_pinoox_cms\Cms\Database\CmsDatabase;
use App\com_pinoox_cms\Cms\Support\SearchTerm;
use App\com_pinoox_cms\Cms\Support\QueryBounds;

final class PinooxTermRepository implements TermRepositoryInterface
{
    private const MAX_BATCH_IDS = 5000;

    public function create(
        int $siteId,
        string $taxonomy,
        string $name,
        string $slug,
        string $description,
        ?int $parentId,
        string $locale,
        array $metadata = [],
    ): TermRecord {
        $model = TermModel::create([
            'site_id' => $siteId,
            'taxonomy' => $taxonomy,
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'parent_id' => $parentId,
            'locale' => $locale,
            'metadata' => $metadata,
        ]);

        return $this->hydrate($model);
    }

    public function find(int $id): ?TermRecord
    {
        $model = TermModel::find($id);
        return $model ? $this->hydrate($model) : null;
    }

    public function findBySlug(int $siteId, string $taxonomy, string $locale, string $slug): ?TermRecord
    {
        $model = TermModel::query()
            ->where('site_id', $siteId)
            ->where('taxonomy', $taxonomy)
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->first();

        return $model ? $this->hydrate($model) : null;
    }

    public function update(int $id, array $changes): ?TermRecord
    {
        $model = TermModel::find($id);
        if ($model === null) {
            return null;
        }

        $model->fill($changes);
        $model->save();

        return $this->hydrate($model->fresh() ?? $model);
    }

    public function delete(int $id): bool
    {
        $model = TermModel::find($id);
        if ($model === null) {
            return false;
        }

        if ($model->contentRelations()->exists()) {
            return false;
        }

        return (bool)$model->delete();
    }

    public function hasChildren(int $id): bool
    {
        return TermModel::query()->where('parent_id', $id)->exists();
    }

    public function findMany(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn (int $id): bool => $id > 0,
        )));
        if ($ids === []) {
            return [];
        }
        if (count($ids) > self::MAX_BATCH_IDS) {
            throw new \InvalidArgumentException(
                'Term batch lookup cannot contain more than ' . self::MAX_BATCH_IDS . ' IDs.',
            );
        }

        $result = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            foreach (TermModel::query()->whereIn('id', $chunk)->get() as $model) {
                $record = $this->hydrate($model);
                $result[$record->id] = $record;
            }
        }

        return $result;
    }

    public function forTaxonomy(int $siteId, string $taxonomy, string $locale = 'fa'): array
    {
        return TermModel::query()
            ->where('site_id', $siteId)
            ->where('taxonomy', $taxonomy)
            ->where('locale', $locale)
            ->orderBy('name')
            ->get()
            ->map(fn (TermModel $model): TermRecord => $this->hydrate($model))
            ->all();
    }

    public function searchForTaxonomy(
        int $siteId,
        string $taxonomy,
        string $locale = 'fa',
        ?string $search = null,
        int $limit = 50,
        int $offset = 0,
    ): array {
        $query = TermModel::query()
            ->where('site_id', $siteId)
            ->where('taxonomy', $taxonomy)
            ->where('locale', $locale);

        $needle = SearchTerm::contains($search);
        if ($needle !== null) {
            $query->where(static function ($builder) use ($needle): void {
                $builder->where('name', 'like', $needle)
                    ->orWhere('slug', 'like', $needle);
            });
        }

        return $query
            ->orderBy('name')
            ->orderBy('id')
            ->limit(max(1, min(100, $limit)))
            ->offset(QueryBounds::offset($offset))
            ->get()
            ->map(fn (TermModel $model): TermRecord => $this->hydrate($model))
            ->all();
    }

    public function countForTaxonomy(
        int $siteId,
        string $taxonomy,
        string $locale = 'fa',
        ?string $search = null,
    ): int {
        $query = TermModel::query()
            ->where('site_id', $siteId)
            ->where('taxonomy', $taxonomy)
            ->where('locale', $locale);

        $needle = SearchTerm::contains($search);
        if ($needle !== null) {
            $query->where(static function ($builder) use ($needle): void {
                $builder->where('name', 'like', $needle)
                    ->orWhere('slug', 'like', $needle);
            });
        }

        return (int)$query->count();
    }

    public function slugExists(
        int $siteId,
        string $taxonomy,
        string $locale,
        string $slug,
        ?int $excludeId = null,
    ): bool {
        $query = TermModel::query()
            ->where('site_id', $siteId)
            ->where('taxonomy', $taxonomy)
            ->where('locale', $locale)
            ->where('slug', $slug);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    public function assignToContent(int $contentId, string $taxonomy, array $termIds): void
    {
        CmsDatabase::transaction(function () use ($contentId, $taxonomy, $termIds): void {
            ContentTermModel::query()
                ->where('content_id', $contentId)
                ->where('taxonomy', $taxonomy)
                ->delete();

            $termIds = array_values(array_unique(array_map('intval', $termIds)));
            if ($termIds === []) {
                return;
            }

            $now = gmdate('Y-m-d H:i:s');
            $rows = [];
            foreach ($termIds as $order => $termId) {
                $rows[] = [
                    'content_id' => $contentId,
                    'term_id' => $termId,
                    'taxonomy' => $taxonomy,
                    'sort_order' => $order,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            ContentTermModel::query()->insert($rows);
        });
    }

    public function assignedTermIds(int $contentId, string $taxonomy): array
    {
        return ContentTermModel::query()
            ->where('content_id', $contentId)
            ->where('taxonomy', $taxonomy)
            ->orderBy('sort_order')
            ->pluck('term_id')
            ->map(static fn ($id): int => (int)$id)
            ->all();
    }

    private function hydrate(TermModel $model): TermRecord
    {
        return new TermRecord(
            (int)$model->id,
            (int)$model->site_id,
            (string)$model->taxonomy,
            (string)$model->name,
            (string)$model->slug,
            (string)($model->description ?? ''),
            $model->parent_id !== null ? (int)$model->parent_id : null,
            (string)$model->locale,
            is_array($model->metadata) ? $model->metadata : [],
            $model->created_at?->format(DATE_ATOM) ?? '',
            $model->updated_at?->format(DATE_ATOM) ?? '',
        );
    }
}

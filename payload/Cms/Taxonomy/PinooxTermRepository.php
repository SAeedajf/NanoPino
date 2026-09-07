<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Taxonomy;

use App\com_pinoox_cms\Model\ContentTermModel;
use App\com_pinoox_cms\Model\TermModel;
use App\com_pinoox_cms\Cms\Database\CmsDatabase;

final class PinooxTermRepository implements TermRepositoryInterface
{
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

    public function findMany(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn (int $id): bool => $id > 0,
        )));
        if ($ids === []) {
            return [];
        }

        $result = [];
        foreach (TermModel::query()->whereIn('id', $ids)->get() as $model) {
            $record = $this->hydrate($model);
            $result[$record->id] = $record;
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

        $search = trim((string)$search);
        if ($search !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
            $query->where(static function ($builder) use ($escaped): void {
                $builder->where('name', 'like', '%' . $escaped . '%')
                    ->orWhere('slug', 'like', '%' . $escaped . '%');
            });
        }

        return $query
            ->orderBy('name')
            ->orderBy('id')
            ->limit(max(1, min(100, $limit)))
            ->offset(max(0, min(100000, $offset)))
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

        $search = trim((string)$search);
        if ($search !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
            $query->where(static function ($builder) use ($escaped): void {
                $builder->where('name', 'like', '%' . $escaped . '%')
                    ->orWhere('slug', 'like', '%' . $escaped . '%');
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

            foreach (array_values(array_unique(array_map('intval', $termIds))) as $order => $termId) {
                ContentTermModel::create([
                    'content_id' => $contentId,
                    'term_id' => $termId,
                    'taxonomy' => $taxonomy,
                    'sort_order' => $order,
                ]);
            }
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

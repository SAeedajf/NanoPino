<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Content;

use App\com_pinoox_cms\Model\ContentFieldValueModel;
use App\com_pinoox_cms\Model\ContentModel;
use App\com_pinoox_cms\Model\ContentRelationModel;
use App\com_pinoox_cms\Model\ContentTermModel;
use App\com_pinoox_cms\Cms\Database\CmsDatabase;

final class PinooxContentRepository implements ContentRepositoryInterface
{
    /** @var list<string> */
    private const LIST_COLUMNS = [
        'id',
        'site_id',
        'type',
        'status',
        'title',
        'slug',
        'excerpt',
        'author_id',
        'parent_id',
        'locale',
        'revision_id',
        'published_at',
        'scheduled_at',
        'created_at',
        'updated_at',
    ];

    public function create(ContentMutation $mutation): ContentRecord
    {
        return CmsDatabase::transaction(function () use ($mutation): ContentRecord {
            $model = ContentModel::create($this->attributes($mutation));
            $this->syncFields((int) $model->id, $mutation->fields);
            $this->syncRelations((int) $model->id, $mutation->relations);
            $this->syncTerms((int) $model->id, $mutation->terms);

            return $this->hydrate($model->fresh());
        });
    }

    public function update(int $id, ContentMutation $mutation): ContentRecord
    {
        return CmsDatabase::transaction(function () use ($id, $mutation): ContentRecord {
            $model = ContentModel::find($id);
            if (!$model) {
                throw new \RuntimeException('Content not found: ' . $id);
            }

            $model->fill($this->attributes($mutation));
            $model->save();

            $this->syncFields($id, $mutation->fields);
            $this->syncRelations($id, $mutation->relations);
            $this->syncTerms($id, $mutation->terms);

            return $this->hydrate($model->fresh());
        });
    }

    public function find(
        int $id,
        ContentProjection $projection = ContentProjection::Detail,
    ): ?ContentRecord {
        $query = ContentModel::query()->whereKey($id);
        if ($projection === ContentProjection::List) {
            $query->select(self::LIST_COLUMNS);
        }

        $model = $query->first();

        return $model ? $this->hydrate($model, $projection) : null;
    }

    public function findMany(
        array $ids,
        ContentProjection $projection = ContentProjection::List,
    ): array {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn (int $id): bool => $id > 0,
        )));
        if ($ids === []) {
            return [];
        }

        $query = ContentModel::query()->whereIn('id', $ids);
        if ($projection === ContentProjection::List) {
            $query->select(self::LIST_COLUMNS);
        }

        $records = $this->hydrateMany($query->get(), $projection);
        $indexed = [];
        foreach ($records as $record) {
            $indexed[$record->id] = $record;
        }

        return $indexed;
    }

    public function search(ContentQuery $query): array
    {
        $builder = ContentModel::query()->where('site_id', $query->siteId);

        if ($query->type !== null) $builder->where('type', $query->type);
        if ($query->status !== null) $builder->where('status', $query->status->value);
        if ($query->locale !== null) $builder->where('locale', $query->locale);
        if ($query->authorId !== null) $builder->where('author_id', $query->authorId);
        if ($query->parentId !== null) $builder->where('parent_id', $query->parentId);
        if ($query->beforeId !== null) $builder->where('id', '<', $query->beforeId);

        if ($query->search !== null && trim($query->search) !== '') {
            $search = '%' . trim($query->search) . '%';
            $builder->where(function ($nested) use ($search): void {
                $nested
                    ->where('title', 'like', $search)
                    ->orWhere('excerpt', 'like', $search)
                    ->orWhere('slug', 'like', $search);
            });
        }

        if ($query->projection === ContentProjection::List) {
            $builder->select(self::LIST_COLUMNS);
        }

        $models = $builder
            ->orderByDesc('id')
            ->offset(max(0, $query->offset))
            ->limit(max(1, min(500, $query->limit)))
            ->get();

        return $this->hydrateMany($models, $query->projection);
    }

    public function count(ContentQuery $query): int
    {
        $builder = ContentModel::query()->where('site_id', $query->siteId);

        if ($query->type !== null) $builder->where('type', $query->type);
        if ($query->status !== null) $builder->where('status', $query->status->value);
        if ($query->locale !== null) $builder->where('locale', $query->locale);
        if ($query->authorId !== null) $builder->where('author_id', $query->authorId);
        if ($query->parentId !== null) $builder->where('parent_id', $query->parentId);
        if ($query->beforeId !== null) $builder->where('id', '<', $query->beforeId);

        if ($query->search !== null && trim($query->search) !== '') {
            $search = '%' . trim($query->search) . '%';
            $builder->where(function ($nested) use ($search): void {
                $nested
                    ->where('title', 'like', $search)
                    ->orWhere('excerpt', 'like', $search)
                    ->orWhere('slug', 'like', $search);
            });
        }

        return (int) $builder->count();
    }

    public function slugExists(
        int $siteId,
        string $type,
        string $locale,
        string $slug,
        ?int $excludeId = null,
    ): bool {
        $query = ContentModel::query()
            ->where('site_id', $siteId)
            ->where('type', $type)
            ->where('locale', $locale)
            ->where('slug', $slug);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /** @return array<string,mixed> */
    private function attributes(ContentMutation $mutation): array
    {
        return [
            'site_id' => $mutation->siteId,
            'type' => $mutation->type,
            'status' => $mutation->status->value,
            'title' => $mutation->title,
            'slug' => $mutation->slug,
            'excerpt' => $mutation->excerpt,
            'author_id' => $mutation->authorId,
            'parent_id' => $mutation->parentId,
            'locale' => $mutation->locale,
            'document' => $mutation->document,
            'metadata' => $mutation->metadata,
            'revision_id' => $mutation->revisionId,
            'published_at' => $mutation->publishedAt,
            'scheduled_at' => $mutation->scheduledAt,
        ];
    }

    /** @param array<string,mixed> $fields */
    private function syncFields(int $contentId, array $fields): void
    {
        foreach ($fields as $key => $value) {
            $encoded = json_encode(
                $value,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );

            $record = ContentFieldValueModel::query()
                ->where('content_id', $contentId)
                ->where('field_key', $key)
                ->first();

            if ($record) {
                $record->value_json = $encoded;
                $record->value_type = get_debug_type($value);
                $record->version = (int) $record->version + 1;
                $record->save();
            } else {
                ContentFieldValueModel::create([
                    'content_id' => $contentId,
                    'field_key' => $key,
                    'value_type' => get_debug_type($value),
                    'value_json' => $encoded,
                    'version' => 1,
                ]);
            }
        }
    }

    /** @param array<string,list<int>> $relations */
    private function syncRelations(int $contentId, array $relations): void
    {
        foreach ($relations as $fieldKey => $targetIds) {
            ContentRelationModel::query()
                ->where('source_content_id', $contentId)
                ->where('field_key', $fieldKey)
                ->delete();

            foreach (array_values(array_unique($targetIds)) as $order => $targetId) {
                ContentRelationModel::create([
                    'source_content_id' => $contentId,
                    'field_key' => $fieldKey,
                    'target_content_id' => $targetId,
                    'sort_order' => $order,
                ]);
            }
        }
    }

    /** @param array<string,list<int>> $terms */
    private function syncTerms(int $contentId, array $terms): void
    {
        foreach ($terms as $taxonomy => $termIds) {
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
        }
    }

    private function hydrate(
        ContentModel $model,
        ContentProjection $projection = ContentProjection::Detail,
    ): ContentRecord {
        $records = $this->hydrateMany([$model], $projection);
        if ($records === []) {
            throw new \RuntimeException('Content hydration unexpectedly returned no records.');
        }

        return $records[0];
    }

    /**
     * Batch hydrates collection reads without issuing per-content queries.
     *
     * Query budget:
     * - List: 0 association queries (the caller already executed the contents query).
     * - Detail/Editor: exactly 3 association queries regardless of content count.
     *
     * @param iterable<ContentModel> $models
     * @return list<ContentRecord>
     */
    private function hydrateMany(iterable $models, ContentProjection $projection): array
    {
        $ordered = [];
        $ids = [];

        foreach ($models as $model) {
            $ordered[] = $model;
            $ids[] = (int) $model->id;
        }

        if ($ordered === []) {
            return [];
        }

        $fieldsByContent = [];
        $relationsByContent = [];
        $termsByContent = [];

        if ($projection->includesAssociations()) {
            foreach (
                ContentFieldValueModel::query()
                    ->whereIn('content_id', $ids)
                    ->orderBy('content_id')
                    ->orderBy('field_key')
                    ->get()
                as $value
            ) {
                $fieldsByContent[(int) $value->content_id][(string) $value->field_key]
                    = $this->decodeFieldValue((string) $value->value_json);
            }

            foreach (
                ContentRelationModel::query()
                    ->whereIn('source_content_id', $ids)
                    ->orderBy('source_content_id')
                    ->orderBy('field_key')
                    ->orderBy('sort_order')
                    ->get()
                as $relation
            ) {
                $relationsByContent[(int) $relation->source_content_id][(string) $relation->field_key][]
                    = (int) $relation->target_content_id;
            }

            foreach (
                ContentTermModel::query()
                    ->whereIn('content_id', $ids)
                    ->orderBy('content_id')
                    ->orderBy('taxonomy')
                    ->orderBy('sort_order')
                    ->get()
                as $relation
            ) {
                $termsByContent[(int) $relation->content_id][(string) $relation->taxonomy][]
                    = (int) $relation->term_id;
            }
        }

        $records = [];
        foreach ($ordered as $model) {
            $id = (int) $model->id;
            $records[] = $this->record(
                $model,
                $projection,
                $fieldsByContent[$id] ?? [],
                $relationsByContent[$id] ?? [],
                $termsByContent[$id] ?? [],
            );
        }

        return $records;
    }

    /**
     * @param array<string,mixed> $fields
     * @param array<string,list<int>> $relations
     * @param array<string,list<int>> $terms
     */
    private function record(
        ContentModel $model,
        ContentProjection $projection,
        array $fields,
        array $relations,
        array $terms,
    ): ContentRecord {
        $withPayload = $projection->includesPayload();

        return new ContentRecord(
            (int) $model->id,
            (int) $model->site_id,
            (string) $model->type,
            ContentStatus::from((string) $model->status),
            (string) $model->title,
            (string) $model->slug,
            (string) ($model->excerpt ?? ''),
            $model->author_id !== null ? (int) $model->author_id : null,
            $model->parent_id !== null ? (int) $model->parent_id : null,
            (string) $model->locale,
            $withPayload && is_array($model->document) ? $model->document : [],
            $withPayload && is_array($model->metadata) ? $model->metadata : [],
            $model->revision_id !== null ? (int) $model->revision_id : null,
            $model->published_at?->format(DATE_ATOM),
            $model->scheduled_at?->format(DATE_ATOM),
            $model->created_at?->format(DATE_ATOM) ?? '',
            $model->updated_at?->format(DATE_ATOM) ?? '',
            $fields,
            $relations,
            $terms,
        );
    }

    private function decodeFieldValue(string $json): mixed
    {
        try {
            return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }
    }
}

<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\Binding;

use App\com_pinoox_cms\Cms\Content\ContentProjection;
use App\com_pinoox_cms\Cms\Content\ContentQuery;
use App\com_pinoox_cms\Cms\Content\ContentRecord;
use App\com_pinoox_cms\Cms\Content\ContentRepositoryInterface;
use App\com_pinoox_cms\Cms\Content\ContentStatus;
use App\com_pinoox_cms\Cms\Media\MediaAsset;
use App\com_pinoox_cms\Cms\Media\MediaKind;
use App\com_pinoox_cms\Cms\Media\MediaRepositoryInterface;
use App\com_pinoox_cms\Cms\Media\MediaStatus;
use App\com_pinoox_cms\Cms\PublicSite\PublicNavigation;
use App\com_pinoox_cms\Cms\Settings\SettingScope;
use App\com_pinoox_cms\Cms\Settings\SettingsRepositoryInterface;
use App\com_pinoox_cms\Cms\Taxonomy\TaxonomyRegistry;
use App\com_pinoox_cms\Cms\Taxonomy\TermRecord;
use App\com_pinoox_cms\Cms\Taxonomy\TermRepositoryInterface;
use InvalidArgumentException;

/**
 * Resolves the safe native data sources used by imported and native templates.
 * It is deliberately read-only and emits public-safe arrays instead of model
 * objects, metadata blobs or arbitrary query expressions.
 */
final readonly class CoreDataBindingResolver
{
    public function __construct(
        private ContentRepositoryInterface $content,
        private TermRepositoryInterface $terms,
        private MediaRepositoryInterface $media,
        private SettingsRepositoryInterface $settings,
        private TaxonomyRegistry $taxonomies,
        private BindingExpressionValidator $validator,
    ) {}

    public function resolve(BindingExpression $expression, DataBindingContext $context): DataBindingResult
    {
        $this->validator->validate($expression);
        return match ($expression->source) {
            'content' => $this->content($expression, $context),
            'taxonomy' => $this->taxonomy($expression, $context),
            'media' => $this->media($expression, $context),
            'navigation' => $this->navigation($expression, $context),
            'pagination' => $this->pagination($expression, $context),
            default => throw new DataBindingResolutionException('Data source is registered but not enabled for template rendering.'),
        };
    }

    private function content(BindingExpression $expression, DataBindingContext $context): DataBindingResult
    {
        $parts = explode('.', $expression->path);
        $root = array_shift($parts);
        if ($root === 'current') {
            $value = $context->currentContent === null ? null : $this->contentValue($context->currentContent);
            return new DataBindingResult('content', $expression->path, $this->pathValue($value, $parts), cacheTags: ['content:site:' . $context->siteId]);
        }
        if ($root === 'count') {
            if ($parts !== []) throw new DataBindingResolutionException('Content count does not accept a nested path.');
            return new DataBindingResult('content', $expression->path, $this->contentCount($expression, $context), cacheTags: ['content:site:' . $context->siteId]);
        }
        if ($root !== 'items' || $parts !== []) {
            throw new DataBindingResolutionException('Unsupported content binding path.');
        }

        $query = $this->contentQuery($expression, $context);
        $records = $this->content->search($query);
        $total = $this->content->count($query);
        $pagination = $this->paginationMeta($query->limit, $query->offset, $total, count($records));
        return new DataBindingResult(
            'content',
            $expression->path,
            array_map(fn (ContentRecord $record): array => $this->contentValue($record), $records),
            $pagination,
            ['content:site:' . $context->siteId, 'content:list:' . hash('sha256', json_encode($query, JSON_THROW_ON_ERROR))],
        );
    }

    private function contentQuery(BindingExpression $expression, DataBindingContext $context): ContentQuery
    {
        $status = $this->status($expression, $context);
        $type = $this->identifier($expression, 'type', false);
        $locale = $this->locale($expression, $context);
        $limit = $this->limit($expression);
        $offset = $this->offset($expression, $limit);
        $query = new ContentQuery(
            siteId: $context->siteId,
            type: $type,
            status: $status,
            locale: $locale,
            authorId: $this->positiveInt($expression, 'author_id', false),
            parentId: $this->positiveInt($expression, 'parent_id', false),
            search: $this->text($expression, 'search', 190, false),
            limit: $limit,
            offset: $offset,
            projection: ContentProjection::Detail,
            beforeId: $this->positiveInt($expression, 'before_id', false),
            termId: $this->positiveInt($expression, 'term_id', false),
            taxonomy: $this->identifier($expression, 'taxonomy', false),
        );
        return $query;
    }

    private function contentCount(BindingExpression $expression, DataBindingContext $context): int
    {
        $query = $this->contentQuery($expression, $context);
        return $this->content->count($query);
    }

    private function taxonomy(BindingExpression $expression, DataBindingContext $context): DataBindingResult
    {
        $parts = explode('.', $expression->path);
        $root = array_shift($parts);
        $taxonomy = $this->identifier($expression, 'taxonomy', true);
        $definition = $this->taxonomies->definition($taxonomy);
        if ($definition === null || !$definition->public) throw new DataBindingResolutionException('Taxonomy is not public or is not registered.');
        $locale = $this->locale($expression, $context);
        if ($root === 'count' && $parts === []) {
            return new DataBindingResult('taxonomy', $expression->path, $this->terms->countForTaxonomy($context->siteId, $taxonomy, $locale, $this->text($expression, 'search', 190, false)), cacheTags: ['taxonomy:' . $context->siteId . ':' . $taxonomy]);
        }
        if ($root === 'term' && $parts === []) {
            $slug = $this->text($expression, 'slug', 190, true);
            $term = $this->terms->findBySlug($context->siteId, $taxonomy, $locale, $slug);
            return new DataBindingResult('taxonomy', $expression->path, $term === null ? null : $this->termValue($term), cacheTags: ['taxonomy:' . $context->siteId . ':' . $taxonomy]);
        }
        if ($root !== 'terms' || $parts !== []) throw new DataBindingResolutionException('Unsupported taxonomy binding path.');
        $limit = $this->limit($expression);
        $offset = $this->offset($expression, $limit);
        $search = $this->text($expression, 'search', 190, false);
        $items = $this->terms->searchForTaxonomy($context->siteId, $taxonomy, $locale, $search, $limit, $offset);
        $total = $this->terms->countForTaxonomy($context->siteId, $taxonomy, $locale, $search);
        return new DataBindingResult(
            'taxonomy',
            $expression->path,
            array_map(fn (TermRecord $term): array => $this->termValue($term), $items),
            $this->paginationMeta($limit, $offset, $total, count($items)),
            ['taxonomy:' . $context->siteId . ':' . $taxonomy],
        );
    }

    private function media(BindingExpression $expression, DataBindingContext $context): DataBindingResult
    {
        $parts = explode('.', $expression->path);
        $root = array_shift($parts);
        $kind = $this->mediaKind($expression);
        $query = $this->text($expression, 'search', 190, false);
        if ($root === 'count' && $parts === []) {
            return new DataBindingResult('media', $expression->path, $this->media->countPublic($context->siteId, $kind, $query), cacheTags: ['media:site:' . $context->siteId]);
        }
        if ($root === 'asset' && $parts === []) {
            $id = $this->positiveInt($expression, 'id', true);
            $asset = $this->media->find($id);
            if ($asset === null || !$this->mediaIsPublic($asset, $context)) return new DataBindingResult('media', $expression->path, null, cacheTags: ['media:site:' . $context->siteId]);
            return new DataBindingResult('media', $expression->path, $this->mediaValue($asset), cacheTags: ['media:site:' . $context->siteId]);
        }
        if ($root !== 'items' || $parts !== []) throw new DataBindingResolutionException('Unsupported media binding path.');
        $limit = $this->limit($expression);
        $offset = $this->offset($expression, $limit);
        $items = array_values(array_filter(
            $this->media->search($context->siteId, $kind, $query, $limit, $offset),
            fn (MediaAsset $asset): bool => $this->mediaIsPublic($asset, $context),
        ));
        $total = $this->media->countPublic($context->siteId, $kind, $query);
        return new DataBindingResult(
            'media',
            $expression->path,
            array_map(fn (MediaAsset $asset): array => $this->mediaValue($asset), $items),
            $this->paginationMeta($limit, $offset, $total, count($items)),
            ['media:site:' . $context->siteId],
        );
    }

    private function navigation(BindingExpression $expression, DataBindingContext $context): DataBindingResult
    {
        if (!in_array($expression->path, ['primary', 'items'], true) || $expression->arguments !== []) {
            throw new DataBindingResolutionException('Unsupported navigation binding.');
        }
        $record = $this->settings->find(PublicNavigation::SETTING, new SettingScope(\App\com_pinoox_cms\Cms\Authorization\ScopeType::Site, $context->siteId));
        try {
            $items = PublicNavigation::fromValue($record?->value ?? [])->items();
        } catch (\Throwable) {
            $items = [];
        }
        return new DataBindingResult('navigation', $expression->path, $items, cacheTags: ['navigation:site:' . $context->siteId]);
    }

    private function pagination(BindingExpression $expression, DataBindingContext $context): DataBindingResult
    {
        if (!in_array($expression->path, ['current', 'meta'], true) || $expression->arguments !== []) {
            throw new DataBindingResolutionException('Unsupported pagination binding.');
        }
        return new DataBindingResult('pagination', $expression->path, $context->pagination, cacheTags: ['pagination:site:' . $context->siteId]);
    }

    private function status(BindingExpression $expression, DataBindingContext $context): ContentStatus
    {
        $raw = strtolower($this->text($expression, 'status', 32, false) ?? 'published');
        $status = ContentStatus::tryFrom($raw);
        if ($status === null) throw new DataBindingResolutionException('Invalid content status binding.');
        if ($context->publicOnly && $status !== ContentStatus::Published) throw new DataBindingResolutionException('Public content bindings may only read published records.');
        return $status;
    }

    private function locale(BindingExpression $expression, DataBindingContext $context): string
    {
        $locale = $this->text($expression, 'locale', 16, false) ?? $context->locale;
        if (preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $locale) !== 1) throw new DataBindingResolutionException('Invalid data binding locale.');
        return $locale;
    }

    private function identifier(BindingExpression $expression, string $key, bool $required): ?string
    {
        $value = $this->text($expression, $key, 64, $required);
        if ($value === null || $value === '') return null;
        if (preg_match('/^[a-z0-9][a-z0-9._-]{0,63}$/i', $value) !== 1 || str_contains($value, '..')) throw new DataBindingResolutionException('Invalid binding identifier.');
        return strtolower($value);
    }

    private function text(BindingExpression $expression, string $key, int $max, bool $required): ?string
    {
        $value = $expression->arguments[$key] ?? null;
        if ($value === null || trim((string)$value) === '') {
            if ($required) throw new DataBindingResolutionException('Missing binding argument: ' . $key . '.');
            return null;
        }
        if (!is_string($value) && !is_int($value) && !is_float($value)) throw new DataBindingResolutionException('Binding argument must be scalar: ' . $key . '.');
        $value = trim((string)$value);
        if (strlen($value) > $max) throw new DataBindingResolutionException('Binding argument is too long: ' . $key . '.');
        return $value;
    }

    private function positiveInt(BindingExpression $expression, string $key, bool $required): ?int
    {
        $value = $this->text($expression, $key, 12, $required);
        if ($value === null) return null;
        if (preg_match('/^[1-9][0-9]{0,10}$/', $value) !== 1) throw new DataBindingResolutionException('Binding argument must be a positive integer: ' . $key . '.');
        return (int)$value;
    }

    private function limit(BindingExpression $expression): int
    {
        $value = $expression->arguments['per_page'] ?? ($expression->arguments['limit'] ?? 12);
        if (!is_int($value) && !is_string($value)) throw new DataBindingResolutionException('Binding limit must be an integer.');
        if (preg_match('/^[1-9][0-9]{0,2}$/', (string)$value) !== 1) throw new DataBindingResolutionException('Binding limit is invalid.');
        return max(1, min(100, (int)$value));
    }

    private function offset(BindingExpression $expression, int $limit): int
    {
        if (array_key_exists('page', $expression->arguments)) {
            $page = $expression->arguments['page'];
            if ((!is_int($page) && !is_string($page)) || preg_match('/^[1-9][0-9]{0,5}$/', (string)$page) !== 1) throw new DataBindingResolutionException('Binding page is invalid.');
            return min(100000, ((int)$page - 1) * $limit);
        }
        $value = $expression->arguments['offset'] ?? 0;
        if (!is_int($value) && !is_string($value)) throw new DataBindingResolutionException('Binding offset must be an integer.');
        if (preg_match('/^[0-9]{1,6}$/', (string)$value) !== 1) throw new DataBindingResolutionException('Binding offset is invalid.');
        return min(100000, (int)$value);
    }

    private function mediaKind(BindingExpression $expression): ?MediaKind
    {
        $raw = strtolower($this->text($expression, 'kind', 16, false) ?? '');
        if ($raw === '') return null;
        return MediaKind::tryFrom($raw) ?? throw new DataBindingResolutionException('Invalid media kind binding.');
    }

    /** @return array<string,mixed> */
    private function contentValue(ContentRecord $record): array
    {
        return [
            'id' => $record->id,
            'type' => $record->type,
            'status' => $record->status->value,
            'title' => $record->title,
            'slug' => $record->slug,
            'excerpt' => $record->excerpt,
            'locale' => $record->locale,
            'document' => $record->document,
            'fields' => $record->fields,
            'terms' => $record->terms,
            'published_at' => $record->publishedAt,
        ];
    }

    /** @return array<string,mixed> */
    private function termValue(TermRecord $term): array
    {
        return ['id' => $term->id, 'taxonomy' => $term->taxonomy, 'name' => $term->name, 'slug' => $term->slug, 'description' => $term->description, 'parent_id' => $term->parentId, 'locale' => $term->locale];
    }

    private function mediaIsPublic(MediaAsset $asset, DataBindingContext $context): bool
    {
        return $asset->siteId === $context->siteId && $asset->status === MediaStatus::Ready && $asset->url !== null && $asset->url !== '';
    }

    /** @return array<string,mixed> */
    private function mediaValue(MediaAsset $asset): array
    {
        return ['id' => $asset->id, 'kind' => $asset->kind->value, 'mime' => $asset->mime, 'original_name' => $asset->originalName, 'title' => $asset->title, 'alt' => $asset->alt, 'caption' => $asset->caption, 'description' => $asset->description, 'size' => $asset->size, 'width' => $asset->width, 'height' => $asset->height, 'duration' => $asset->duration, 'focal_x' => $asset->focalX, 'focal_y' => $asset->focalY, 'url' => $asset->url, 'thumb' => $asset->thumb];
    }

    /** @param mixed $value @param list<string> $path */
    private function pathValue(mixed $value, array $path): mixed
    {
        foreach ($path as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) return null;
            $value = $value[$segment];
        }
        return $value;
    }

    /** @return array<string,int|bool> */
    private function paginationMeta(int $limit, int $offset, int $total, int $returned): array
    {
        $pages = $total > 0 ? (int)ceil($total / $limit) : 0;
        $page = (int)floor($offset / $limit) + 1;
        return [
            'page' => $page,
            'per_page' => $limit,
            'offset' => $offset,
            'returned' => $returned,
            'total' => $total,
            'total_pages' => $pages,
            'has_next' => $offset + $returned < $total,
            'has_previous' => $offset > 0,
        ];
    }
}

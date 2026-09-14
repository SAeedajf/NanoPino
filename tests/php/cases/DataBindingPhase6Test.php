<?php
declare(strict_types=1);

use App\com_pinoox_cms\Cms\Builder\Binding\BindingExpression;
use App\com_pinoox_cms\Cms\Builder\Binding\BindingExpressionValidator;
use App\com_pinoox_cms\Cms\Builder\Binding\CoreDataBindingResolver;
use App\com_pinoox_cms\Cms\Builder\Binding\CoreDataSources;
use App\com_pinoox_cms\Cms\Builder\Binding\DataBindingContext;
use App\com_pinoox_cms\Cms\Builder\Binding\DataBindingResolutionException;
use App\com_pinoox_cms\Cms\Builder\Binding\DataSourceRegistry;
use App\com_pinoox_cms\Cms\Content\ContentMutation;
use App\com_pinoox_cms\Cms\Content\ContentStatus;
use App\com_pinoox_cms\Cms\Content\InMemoryContentRepository;
use App\com_pinoox_cms\Cms\Media\InMemoryMediaRepository;
use App\com_pinoox_cms\Cms\Media\MediaKind;
use App\com_pinoox_cms\Cms\Media\MediaStatus;
use App\com_pinoox_cms\Cms\Media\NativeFileReference;
use App\com_pinoox_cms\Cms\Media\ValidatedMediaUpload;
use App\com_pinoox_cms\Cms\Settings\InMemorySettingsRepository;
use App\com_pinoox_cms\Cms\Settings\SettingScope;
use App\com_pinoox_cms\Cms\Settings\SettingType;
use App\com_pinoox_cms\Cms\Taxonomy\CoreTaxonomies;
use App\com_pinoox_cms\Cms\Taxonomy\InMemoryTermRepository;
use App\com_pinoox_cms\Cms\Taxonomy\TaxonomyRegistry;

return [
    'phase 6 resolves published content and exposes bounded pagination metadata' => static function (): void {
        [$resolver, $content, $terms] = phase6_resolver();
        $category = $terms->create(1, 'category', 'News', 'news', '', null, 'fa');
        $record = $content->create(new ContentMutation(
            1,
            'post',
            ContentStatus::Published,
            'First post',
            'first-post',
            'Excerpt',
            4,
            null,
            'fa',
            ['version' => 1, 'blocks' => []],
            ['internal' => 'must-not-leak'],
            fields: ['content' => ['body' => 'Hello']],
            terms: ['category' => [$category->id]],
            publishedAt: gmdate(DATE_ATOM),
        ));
        $content->create(new ContentMutation(1, 'post', ContentStatus::Draft, 'Draft post', 'draft-post', '', 4, null, 'fa'));

        $result = $resolver->resolve(new BindingExpression('content', 'items', [
            'type' => 'post', 'taxonomy' => 'category', 'term_id' => $category->id, 'page' => 1, 'per_page' => 1,
        ]), new DataBindingContext());

        np_assert_same(1, count($result->value));
        np_assert_same($record->id, $result->value[0]['id']);
        np_assert_same('First post', $result->value[0]['title']);
        np_assert_false(array_key_exists('metadata', $result->value[0]));
        np_assert_false(array_key_exists('author_id', $result->value[0]));
        np_assert_same(1, $result->pagination['total']);
        np_assert_same(1, $result->pagination['total_pages']);
        np_assert_false($result->pagination['has_next']);

        $current = $resolver->resolve(
            new BindingExpression('content', 'current.title'),
            new DataBindingContext(currentContent: $record),
        );
        np_assert_same('First post', $current->value);
        $pagination = $resolver->resolve(
            new BindingExpression('pagination', 'current'),
            (new DataBindingContext())->withPagination($result->pagination),
        );
        np_assert_same(1, $pagination->value['page']);
    },

    'phase 6 resolves public taxonomy terms, navigation and media safely' => static function (): void {
        [$resolver, , $terms, $media, $settings] = phase6_resolver();
        $terms->create(1, 'category', 'News', 'news', 'Public description', null, 'fa', ['secret' => 'omit']);
        $settings->put('site.primary_navigation', new SettingScope(\App\com_pinoox_cms\Cms\Authorization\ScopeType::Site, 1), SettingType::Json, [
            ['label' => 'Home', 'path' => '/site'],
            ['label' => 'News', 'path' => '/category/news'],
        ], null);
        $media->create(
            1,
            new NativeFileReference(12, 'hash', '/media/hero.jpg', '/media/hero-thumb.jpg', null, null, 'public'),
            new ValidatedMediaUpload('/tmp/hero.jpg', 'hero.jpg', 'jpg', 'image/jpeg', MediaKind::Image, 1234, str_repeat('a', 64), 800, 600),
            'Hero',
            'Hero image',
            '',
            '',
            3,
        );
        $media->create(
            1,
            new NativeFileReference(13, 'hash-2', null, null, null, null, 'private'),
            new ValidatedMediaUpload('/tmp/private.jpg', 'private.jpg', 'jpg', 'image/jpeg', MediaKind::Image, 100, str_repeat('b', 64), 10, 10),
            'Private',
            '',
            '',
            '',
            3,
        );
        $quarantined = $media->create(
            1,
            new NativeFileReference(14, 'hash-3', '/media/quarantined.jpg', null, null, null, 'public'),
            new ValidatedMediaUpload('/tmp/quarantined.jpg', 'quarantined.jpg', 'jpg', 'image/jpeg', MediaKind::Image, 100, str_repeat('c', 64), 10, 10),
            'Quarantined',
            '',
            '',
            '',
            3,
        );
        $media->setStatus($quarantined->id, MediaStatus::Quarantined);

        $taxonomy = $resolver->resolve(new BindingExpression('taxonomy', 'terms', ['taxonomy' => 'category']), new DataBindingContext());
        np_assert_same('News', $taxonomy->value[0]['name']);
        np_assert_false(array_key_exists('metadata', $taxonomy->value[0]));
        np_assert_same(1, $taxonomy->pagination['total']);

        $navigation = $resolver->resolve(new BindingExpression('navigation', 'primary'), new DataBindingContext());
        np_assert_same('/site', $navigation->value[0]['path']);

        $images = $resolver->resolve(new BindingExpression('media', 'items', ['kind' => 'image']), new DataBindingContext());
        np_assert_same(1, count($images->value));
        np_assert_same('/media/hero.jpg', $images->value[0]['url']);
        np_assert_same(1, $images->pagination['total']);
        np_assert_false(array_key_exists('native_file_id', $images->value[0]));
        np_assert_same('Hero', $resolver->resolve(new BindingExpression('media', 'asset', ['id' => 1]), new DataBindingContext())->value['title']);
    },

    'phase 6 rejects unsafe or unsupported bindings and never exposes non-public content' => static function (): void {
        [$resolver] = phase6_resolver();
        np_assert_throws(
            static fn () => $resolver->resolve(new BindingExpression('content', 'items', ['status' => 'draft']), new DataBindingContext()),
            DataBindingResolutionException::class,
            'published records',
        );
        np_assert_throws(
            static fn () => $resolver->resolve(new BindingExpression('query', 'items', ['raw' => 'select *']), new DataBindingContext()),
            InvalidArgumentException::class,
            'Raw query',
        );
        np_assert_throws(
            static fn () => $resolver->resolve(new BindingExpression('content', 'items.extra'), new DataBindingContext()),
            DataBindingResolutionException::class,
            'Unsupported content',
        );
        np_assert_throws(
            static fn () => $resolver->resolve(new BindingExpression('media', 'items', ['kind' => 'binary']), new DataBindingContext()),
            DataBindingResolutionException::class,
            'media kind',
        );
    },

    'phase 6 registers navigation and pagination as explicit core data sources' => static function (): void {
        $sources = new DataSourceRegistry();
        CoreDataSources::register($sources);
        np_assert_same(10, count($sources->all()));
        $validator = new BindingExpressionValidator($sources);
        $validator->validate(new BindingExpression('navigation', 'primary'));
        $validator->validate(new BindingExpression('pagination', 'current'));
    },
];

/** @return array{0:CoreDataBindingResolver,1:InMemoryContentRepository,2:InMemoryTermRepository,3:InMemoryMediaRepository,4:InMemorySettingsRepository} */
function phase6_resolver(): array
{
    $content = new InMemoryContentRepository();
    $terms = new InMemoryTermRepository();
    $media = new InMemoryMediaRepository();
    $settings = new InMemorySettingsRepository();
    $taxonomies = new TaxonomyRegistry();
    CoreTaxonomies::register($taxonomies);
    $sources = new DataSourceRegistry();
    CoreDataSources::register($sources);

    return [
        new CoreDataBindingResolver($content, $terms, $media, $settings, $taxonomies, new BindingExpressionValidator($sources)),
        $content,
        $terms,
        $media,
        $settings,
    ];
}

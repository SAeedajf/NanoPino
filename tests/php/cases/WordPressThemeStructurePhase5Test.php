<?php
declare(strict_types=1);

use App\com_pinoox_cms\Cms\Block\BlockRegistry;
use App\com_pinoox_cms\Cms\Block\Core\CoreBlocks;
use App\com_pinoox_cms\Cms\Block\Document\BlockDocumentValidator;
use App\com_pinoox_cms\Cms\Builder\BuilderTargetType;
use App\com_pinoox_cms\Cms\Theme\Template\TemplateRequest;
use App\com_pinoox_cms\Cms\Theme\WordPress\WordPressBuilderTemplateBridge;
use App\com_pinoox_cms\Cms\Theme\WordPress\WordPressThemeStructureConverter;
use App\com_pinoox_cms\Cms\Theme\WordPress\WordPressTemplateHierarchyResolver;

return [
    'WordPress structure converter maps templates, parts and PHP patterns without execution' => static function (): void {
        $root = sys_get_temp_dir() . '/nanopino-wp-phase5-' . bin2hex(random_bytes(6));
        mkdir($root . '/templates', 0777, true);
        mkdir($root . '/parts', 0777, true);
        mkdir($root . '/patterns', 0777, true);
        $marker = $root . '/must-not-exist';
        file_put_contents($root . '/templates/front-page.html', '<!-- wp:heading {"level":1} --><h1>Welcome</h1><!-- /wp:heading -->');
        file_put_contents($root . '/parts/header.html', '<!-- wp:paragraph --><p>Header</p><!-- /wp:paragraph -->');
        file_put_contents($root . '/patterns/hero.php', "<?php file_put_contents(" . var_export($marker, true) . ", 'executed'); ?>\n/**\n * Title: Hero Banner\n * Slug: hero-banner\n * Categories: featured, marketing\n */\n<!-- wp:group {\"layout\":{\"type\":\"constrained\"}} --><div><h2>Hero</h2><!-- wp:button {\"url\":\"/contact\"} --><a href=\"/contact\">Contact</a><!-- /wp:button --></div><!-- /wp:group -->");

        try {
            $registry = new BlockRegistry();
            CoreBlocks::register($registry);
            $report = (new WordPressThemeStructureConverter(validator: new BlockDocumentValidator($registry)))->convert($root);

            np_assert_true($report->safeToUse());
            np_assert_same(1, count($report->templates));
            np_assert_same('front-page', $report->templates[0]->logicalName);
            np_assert_same('templates/front-page.html', $report->templates[0]->sourcePath);
            np_assert_same(1, count($report->parts));
            np_assert_same(1, count($report->patterns));
            np_assert_same('hero-banner', $report->patterns[0]->id);
            np_assert_same('Hero Banner', $report->patterns[0]->title);
            np_assert_same(['featured', 'marketing'], $report->patterns[0]->categories);
            np_assert_false(is_file($marker), 'PHP pattern source must never be executed.');
            $codes = array_column($report->issues, 'code');
            np_assert_true(in_array('structure.php_runtime_deferred', $codes, true));
            np_assert_false(in_array('markup.unbound_html', $codes, true));
            $catalog = (new WordPressBuilderTemplateBridge())->prepare($report, 9);
            np_assert_same(1, count($catalog->patterns));
            np_assert_same('hero-banner', $catalog->patterns[0]->id);
        } finally {
            wp_phase5_remove($root);
        }
    },

    'WordPress structure converter expands local pattern references into bounded static documents' => static function (): void {
        $root = sys_get_temp_dir() . '/nanopino-wp-pattern-expand-' . bin2hex(random_bytes(6));
        mkdir($root . '/templates', 0777, true);
        mkdir($root . '/patterns', 0777, true);
        file_put_contents($root . '/templates/index.html', '<!-- wp:pattern {"slug":"hero-banner"} /-->');
        file_put_contents($root . '/patterns/hero.php', "/**\n * Title: Hero\n * Slug: hero-banner\n */\n<!-- wp:heading {\"level\":1} --><h1>Hero</h1><!-- /wp:heading -->");

        try {
            $registry = new BlockRegistry();
            CoreBlocks::register($registry);
            $report = (new WordPressThemeStructureConverter(validator: new BlockDocumentValidator($registry)))->convert($root);
            np_assert_true($report->safeToUse());
            np_assert_same('core/heading', $report->templates[0]->document->blocks[0]->type);
            np_assert_same('Hero', $report->templates[0]->document->blocks[0]->attributes['text']);
            $codes = array_column($report->issues, 'code');
            np_assert_false(in_array('markup.block_mapped_to_section', $codes, true));
        } finally {
            wp_phase5_remove($root);
        }
    },

    'WordPress hierarchy follows specific to generic order and sanitizes variables' => static function (): void {
        $resolver = new WordPressTemplateHierarchyResolver();
        np_assert_same(
            ['single-product-chair', 'single-product', 'single', 'singular', 'index'],
            $resolver->candidates(new TemplateRequest('single', ['type' => 'Product', 'slug' => 'Chair!!!'])),
        );
        np_assert_same(
            ['taxonomy-topic-news', 'taxonomy-topic', 'taxonomy', 'archive', 'index'],
            $resolver->candidates(new TemplateRequest('taxonomy', ['taxonomy' => 'Topic', 'term' => 'News'])),
        );
        np_assert_same(['front-page', 'home', 'index'], $resolver->candidates(new TemplateRequest('home')));
        np_assert_same(['header-main'], $resolver->candidates(new TemplateRequest('part', ['part' => 'Header Main'])));
    },

    'Builder bridge creates explicit template and part targets without persistence' => static function (): void {
        $root = sys_get_temp_dir() . '/nanopino-wp-phase5-bridge-' . bin2hex(random_bytes(6));
        mkdir($root . '/templates', 0777, true);
        mkdir($root . '/parts', 0777, true);
        file_put_contents($root . '/templates/index.html', '<!-- wp:paragraph --><p>Index</p><!-- /wp:paragraph -->');
        file_put_contents($root . '/parts/footer.html', '<!-- wp:paragraph --><p>Footer</p><!-- /wp:paragraph -->');
        try {
            $registry = new BlockRegistry();
            CoreBlocks::register($registry);
            $report = (new WordPressThemeStructureConverter(validator: new BlockDocumentValidator($registry)))->convert($root);
            $catalog = (new WordPressBuilderTemplateBridge())->prepare($report, 7, 'fa');
            np_assert_same(1, count($catalog->templates));
            np_assert_same(1, count($catalog->parts));
            np_assert_same(0, count($catalog->patterns));
            np_assert_same(BuilderTargetType::Template, $catalog->templates[0]->target->type);
            np_assert_same('template:index:fa', $catalog->templates[0]->target->identifier());
            np_assert_same(BuilderTargetType::TemplatePart, $catalog->parts[0]->target->type);
            np_assert_same('template_part:footer:fa', $catalog->parts[0]->target->identifier());
            np_assert_same('core/paragraph', $catalog->templates[0]->document['blocks'][0]['type'] ?? null);
            np_assert_same([], $catalog->issues);
        } finally {
            wp_phase5_remove($root);
        }
    },

    'WordPress structure converter fails closed for malformed markup and unsafe names' => static function (): void {
        $root = sys_get_temp_dir() . '/nanopino-wp-phase5-invalid-' . bin2hex(random_bytes(6));
        mkdir($root . '/templates', 0777, true);
        file_put_contents($root . '/templates/bad.html', '<!-- wp:paragraph --><p>Missing close');
        file_put_contents($root . '/templates/bad..name.html', 'outside');
        try {
            $report = (new WordPressThemeStructureConverter())->convert($root);
            np_assert_false($report->safeToUse());
            np_assert_true($report->hasBlockers());
            $codes = array_column($report->issues, 'code');
            np_assert_true(in_array('structure.file_conversion_failed', $codes, true));
            np_assert_true(in_array('structure.file_name_invalid', $codes, true));
            $catalog = (new WordPressBuilderTemplateBridge())->prepare($report, 7);
            np_assert_true(in_array('builder.catalog_not_safe', array_column($catalog->issues, 'code'), true));
        } finally {
            wp_phase5_remove($root);
        }
    },
];

function wp_phase5_remove(string $path): void
{
    if (!is_dir($path)) return;
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $child = $path . '/' . $entry;
        is_dir($child) && !is_link($child) ? wp_phase5_remove($child) : @unlink($child);
    }
    @rmdir($path);
}

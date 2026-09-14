<?php
declare(strict_types=1);

use App\com_pinoox_cms\Cms\Block\BlockRegistry;
use App\com_pinoox_cms\Cms\Block\Core\CoreBlocks;
use App\com_pinoox_cms\Cms\Block\Document\BlockDocumentValidator;
use App\com_pinoox_cms\Cms\Theme\WordPress\WordPressClassicThemeConversionWorker;
use App\com_pinoox_cms\Cms\Theme\WordPress\WordPressThemeType;

return [
    'phase 8 converts safe classic static output and preserves the no-execution boundary' => static function (): void {
        $root = phase8_make_theme([
            'style.css' => "/* Theme Name: Classic Aurora\nLicense: GPL-2.0-or-later */",
            'index.php' => "<?php get_header(); if (have_posts()) { the_post(); } ?>\n<main class=\"site\"><h1>Welcome</h1><p>Intro text</p><a href=\"/contact\" target=\"_blank\">Contact</a><img src=\"hero.jpg\" alt=\"Hero image\"></main><?php get_footer(); ?>",
            'attachment.php' => '<h1>Attachment</h1>',
            'comments.php' => '<p>Comments</p>',
            'template-parts/content.php' => "<article><!-- wp:heading {\"level\":2} --><h2>Article</h2><!-- /wp:heading --></article>",
            'functions.php' => "<?php add_action('wp_head', 'classic_head');",
        ]);
        $marker = $root . '/executed';
        file_put_contents($root . '/functions.php', "<?php file_put_contents(" . var_export($marker, true) . ", 'executed'); add_action('wp_head', 'classic_head');");

        try {
            $registry = new BlockRegistry();
            CoreBlocks::register($registry);
            $worker = new WordPressClassicThemeConversionWorker(validator: new BlockDocumentValidator($registry));
            $report = $worker->convert($root);

            np_assert_same(WordPressThemeType::Hybrid, $report->themeType);
            np_assert_same('static-no-execution', $report->isolationMode);
            np_assert_true($report->safeToUse());
            np_assert_same(4, count($report->templates));
            np_assert_true($report->features['converted_template_count'] === 4);
            np_assert_true(is_string($report->templates[0]->document->toArray()['blocks'][0]['type'] ?? null));
            np_assert_true(in_array('classic.php_runtime', array_column(array_map(static fn ($feature): array => $feature->toArray(), $report->unsupportedFeatures), 'code'), true));
            np_assert_same(['content.items'], $report->bindingSuggestions['have_posts'] ?? []);
            np_assert_same(['content.current'], $report->bindingSuggestions['the_post'] ?? []);
            np_assert_false(is_file($marker), 'Classic PHP must never be executed.');
            np_assert_same('Classic Aurora', $report->metadata['name'] ?? null);
        } finally {
            phase8_remove_tree($root);
        }
    },

    'phase 8 emits actionable unsupported feature findings and blocks active markup' => static function (): void {
        $root = phase8_make_theme([
            'style.css' => '/* Theme Name: Unsafe Classic */',
            'index.php' => "<?php echo do_shortcode('[gallery]'); require_once 'missing.php'; ?><script>alert(1)</script><form action=\"/save\"><h1>Unsafe</h1></form>",
            'functions.php' => "<?php add_filter('the_content', 'x'); if (function_exists('woocommerce')) echo 'woocommerce';",
        ]);

        try {
            $report = (new WordPressClassicThemeConversionWorker())->convert($root);
            np_assert_false($report->safeToUse());
            np_assert_true($report->hasBlockers());
            $features = array_map(static fn ($feature): array => $feature->toArray(), $report->unsupportedFeatures);
            $codes = array_column($features, 'code');
            np_assert_true(in_array('classic.unsafe_html_script', $codes, true));
            np_assert_true(in_array('classic.unsafe_html_form', $codes, true));
            np_assert_true(in_array('classic.shortcodes', $codes, true));
            np_assert_true(in_array('classic.dynamic_include', $codes, true));
            np_assert_true(in_array('classic.plugin_dependency_woocommerce', $codes, true));
            $templateIssueCodes = [];
            foreach ($report->templates as $template) $templateIssueCodes = array_merge($templateIssueCodes, array_column($template->issues, 'code'));
            np_assert_true(in_array('classic.unsafe_markup', $templateIssueCodes, true));
            np_assert_true($report->toArray()['safe_to_use'] === false);
            foreach ($features as $feature) np_assert_true($feature['recommendation'] !== '');
        } finally {
            phase8_remove_tree($root);
        }
    },

    'phase 8 fails closed for block themes and template resource limits' => static function (): void {
        $blockRoot = phase8_make_theme([
            'style.css' => '/* Theme Name: Block */',
            'templates/index.html' => '<!-- wp:paragraph --><p>Block</p><!-- /wp:paragraph -->',
        ]);
        $limitedRoot = phase8_make_theme([
            'style.css' => '/* Theme Name: Limited */',
            'theme.json' => '{invalid',
            'index.php' => '<h1>One</h1>',
            'page.php' => '<h1>Two</h1>',
        ]);

        try {
            $block = (new WordPressClassicThemeConversionWorker())->convert($blockRoot);
            np_assert_false($block->safeToUse());
            np_assert_true(in_array('classic.theme_type_unsupported', array_column($block->issues, 'code'), true));

            $limited = (new WordPressClassicThemeConversionWorker(maxTemplates: 1))->convert($limitedRoot);
            np_assert_false($limited->safeToUse());
            np_assert_true(in_array('classic.template_count_exceeded', array_column($limited->issues, 'code'), true));
            np_assert_true(in_array('theme.theme_json_invalid', array_column($limited->issues, 'code'), true));
        } finally {
            phase8_remove_tree($blockRoot);
            phase8_remove_tree($limitedRoot);
        }
    },
];

/** @param array<string,string> $files */
function phase8_make_theme(array $files): string
{
    $root = sys_get_temp_dir() . '/nanopino-wp-classic-phase8-' . bin2hex(random_bytes(6));
    foreach ($files as $relative => $content) {
        $path = $root . '/' . $relative;
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0770, true);
        file_put_contents($path, $content);
    }
    return $root;
}

function phase8_remove_tree(string $root): void
{
    if (!is_dir($root)) return;
    foreach (scandir($root) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $path = $root . '/' . $entry;
        if (is_dir($path) && !is_link($path)) phase8_remove_tree($path);
        elseif (is_file($path) || is_link($path)) unlink($path);
    }
    rmdir($root);
}

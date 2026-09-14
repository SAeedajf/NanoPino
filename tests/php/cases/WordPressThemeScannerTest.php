<?php
declare(strict_types=1);

use App\com_pinoox_cms\Cms\Theme\WordPress\WordPressThemeScanner;
use App\com_pinoox_cms\Cms\Theme\WordPress\WordPressThemeType;

return [
    'WordPress Block Theme scanner classifies without executing PHP and reports conversion features' => static function (): void {
        $root = sys_get_temp_dir() . '/nanopino-wp-block-' . bin2hex(random_bytes(6));
        mkdir($root . '/templates', 0777, true);
        mkdir($root . '/parts', 0777, true);
        mkdir($root . '/inc', 0777, true);
        file_put_contents($root . '/style.css', "/*\nTheme Name: Aurora\nVersion: 2.1.0\nLicense: GPL-2.0-or-later\n*/\n");
        file_put_contents($root . '/theme.json', json_encode([
            'version' => 2,
            'settings' => ['color' => ['palette' => []]],
            'styles' => ['color' => ['background' => '#fff']],
            'templateParts' => [['name' => 'header']],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($root . '/templates/index.html', "<!-- wp:template-part {\"slug\":\"header\"} /-->\n<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->");
        file_put_contents($root . '/parts/header.html', '<header>Header</header>');
        file_put_contents($root . '/inc/scan.php', "<?php file_put_contents('/tmp/nanopino-scanner-must-not-execute', 'bad'); add_action('wp_head', 'x');");

        try {
            $result = (new WordPressThemeScanner())->scan($root);
            np_assert_same(WordPressThemeType::Block, $result->type);
            np_assert_true($result->safeToImport);
            np_assert_same('Aurora', $result->metadata['name'] ?? null);
            np_assert_same('GPL-2.0-or-later', $result->metadata['license'] ?? null);
            np_assert_true((bool)$result->features['theme_json']);
            np_assert_true((bool)$result->features['block_templates']);
            np_assert_same(3, $result->features['wp_block_markup_count']);
            np_assert_same(1, $result->features['hook_count']);
            np_assert_true($result->scores['block_conversion'] > $result->scores['classic_conversion']);
            np_assert_false(is_file('/tmp/nanopino-scanner-must-not-execute'));
        } finally {
            wp_scanner_remove($root);
            @unlink('/tmp/nanopino-scanner-must-not-execute');
        }
    },

    'WordPress Classic Theme scanner detects template tags and plugin dependencies' => static function (): void {
        $root = sys_get_temp_dir() . '/nanopino-wp-classic-' . bin2hex(random_bytes(6));
        mkdir($root . '/template-parts', 0777, true);
        file_put_contents($root . '/style.css', "/* Theme Name: Shop Classic\nLicense: GPLv2 */");
        file_put_contents($root . '/theme.json', '{"version":2,"settings":{"appearanceTools":true}}');
        file_put_contents($root . '/index.php', "<?php get_header(); if (have_posts()) { the_post(); the_content(); } get_footer();");
        file_put_contents($root . '/functions.php', "<?php add_filter('the_content', 'filter_content'); if (function_exists('woocommerce')) { echo 'shop'; }");
        file_put_contents($root . '/template-parts/content.php', '<?php echo do_shortcode("[gallery]");');

        try {
            $result = (new WordPressThemeScanner())->scan($root);
            np_assert_same(WordPressThemeType::Classic, $result->type);
            np_assert_true($result->safeToImport);
            np_assert_true((bool)$result->features['classic_templates']);
            np_assert_true((bool)$result->features['theme_json']);
            np_assert_true($result->features['template_tag_count'] >= 4);
            np_assert_true($result->features['shortcode_count'] >= 2);
            np_assert_true(in_array('woocommerce', $result->dependencies, true));
            np_assert_true($result->scores['classic_conversion'] > $result->scores['block_conversion']);
        } finally {
            wp_scanner_remove($root);
        }
    },

    'WordPress scanner fails closed for malformed theme.json and unknown directories' => static function (): void {
        $root = sys_get_temp_dir() . '/nanopino-wp-invalid-' . bin2hex(random_bytes(6));
        mkdir($root, 0777, true);
        file_put_contents($root . '/theme.json', '{invalid');

        try {
            $result = (new WordPressThemeScanner())->scan($root);
            np_assert_same(WordPressThemeType::Unknown, $result->type);
            np_assert_false($result->safeToImport);
            $codes = array_column($result->issues, 'code');
            np_assert_true(in_array('theme.theme_json_invalid', $codes, true));
            np_assert_true(in_array('theme.type_unknown', $codes, true));
        } finally {
            wp_scanner_remove($root);
        }
    },
];

function wp_scanner_remove(string $path): void
{
    if (!is_dir($path)) return;
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $child = $path . '/' . $entry;
        is_dir($child) && !is_link($child) ? wp_scanner_remove($child) : @unlink($child);
    }
    @rmdir($path);
}

<?php
declare(strict_types=1);

use App\com_pinoox_cms\Cms\Theme\WordPress\WordPressAssetKind;
use App\com_pinoox_cms\Cms\Theme\WordPress\WordPressThemeAssetPipeline;

return [
    'phase 7 builds a deterministic css javascript font and rtl manifest' => static function (): void {
        $root = phase7_make_theme([
            'css/base.css' => 'body { margin: 0; }',
            'css/site.css' => '@import url("base.css"); @font-face { src: url("../fonts/iransans.woff2?v=1#latin"); }',
            'css/rtl.css' => 'body { direction: rtl; }',
            'fonts/iransans.woff2' => 'font-data',
            'js/vendor.js' => 'window.vendor = true;',
            'js/app.js' => 'window.app = true;',
            'js/app.asset.php' => "<?php return ['dependencies' => ['vendor', 'wp-element'], 'version' => '1.2.3'];",
        ]);

        try {
            $manifest = (new WordPressThemeAssetPipeline())->build($root);
            np_assert_true($manifest->safeToUse());
            np_assert_false($manifest->hasBlockers());
            np_assert_same(6, count($manifest->assets));

            $assets = [];
            foreach ($manifest->assets as $asset) $assets[$asset->key] = $asset;
            np_assert_same(WordPressAssetKind::Css, $assets['css/site.css']->kind);
            np_assert_same(WordPressAssetKind::Font, $assets['fonts/iransans.woff2']->kind);
            np_assert_same('rtl', $assets['css/rtl.css']->direction);
            np_assert_same('1.2.3', $assets['js/app.js']->version);
            np_assert_same(['js/vendor.js'], $assets['js/app.js']->dependencies);
            np_assert_same(['css/base.css', 'fonts/iransans.woff2'], $assets['css/site.css']->dependencies);
            np_assert_matches('/^[a-f0-9]{64}$/', $assets['js/app.js']->sha256);
            np_assert_false(isset($assets['js/app.asset.php']));

            $positions = array_flip($manifest->orderedKeys);
            np_assert_true($positions['fonts/iransans.woff2'] < $positions['css/site.css']);
            np_assert_true($positions['css/base.css'] < $positions['css/site.css']);
            np_assert_true($positions['js/vendor.js'] < $positions['js/app.js']);
            np_assert_true(phase7_has_issue($manifest->issues, 'assets.dependency_unresolved'));
        } finally {
            phase7_remove_tree($root);
        }
    },

    'phase 7 defers external css references but blocks path escapes and never executes sidecars' => static function (): void {
        $marker = sys_get_temp_dir() . '/nanopino-phase7-marker-' . bin2hex(random_bytes(6));
        $root = phase7_make_theme([
            'safe.css' => 'body { background: url("https://cdn.example.test/theme.png"); }',
            'app.js' => 'window.app = true;',
            'app.asset.php' => "<?php file_put_contents(" . var_export($marker, true) . ", 'executed'); return ['version' => '9'];",
        ]);
        $escapeRoot = phase7_make_theme([
            'css/bad.css' => '.x { background: url("../../outside.png"); }',
        ]);

        try {
            $safe = (new WordPressThemeAssetPipeline())->build($root);
            np_assert_true($safe->safeToUse());
            np_assert_true(phase7_has_issue($safe->issues, 'assets.external_reference_deferred'));
            np_assert_false(is_file($marker));
            np_assert_same('9', $safe->assets[0]->version);

            $escaped = (new WordPressThemeAssetPipeline())->build($escapeRoot);
            np_assert_true($escaped->hasBlockers());
            np_assert_false($escaped->safeToUse());
            np_assert_true(phase7_has_issue($escaped->issues, 'assets.reference_escape'));
        } finally {
            phase7_remove_tree($root);
            phase7_remove_tree($escapeRoot);
            if (is_file($marker)) unlink($marker);
        }
    },

    'phase 7 rejects dependency cycles and enforces discovery budgets' => static function (): void {
        $cycleRoot = phase7_make_theme([
            'a.js' => 'a();',
            'a.asset.php' => "<?php return ['dependencies' => ['b']];",
            'b.js' => 'b();',
            'b.asset.php' => "<?php return ['dependencies' => ['a']];",
        ]);
        $budgetRoot = phase7_make_theme([
            'a.css' => 'a{}',
            'b.css' => 'b{}',
        ]);

        try {
            $cycle = (new WordPressThemeAssetPipeline())->build($cycleRoot);
            np_assert_true($cycle->hasBlockers());
            np_assert_true(phase7_has_issue($cycle->issues, 'assets.dependency_cycle'));

            $budget = (new WordPressThemeAssetPipeline(maxFiles: 1))->build($budgetRoot);
            np_assert_true($budget->hasBlockers());
            np_assert_true(phase7_has_issue($budget->issues, 'assets.file_count_exceeded'));
        } finally {
            phase7_remove_tree($cycleRoot);
            phase7_remove_tree($budgetRoot);
        }
    },

    'phase 7 reports an invalid theme root without touching the filesystem' => static function (): void {
        $manifest = (new WordPressThemeAssetPipeline())->build('/tmp/nanopino-phase7-does-not-exist-' . bin2hex(random_bytes(6)));
        np_assert_false($manifest->safeToUse());
        np_assert_true($manifest->hasBlockers());
        np_assert_true(phase7_has_issue($manifest->issues, 'assets.theme_root_invalid'));
    },

    'phase 7 accepts safe punctuation used by official variable-font asset names' => static function (): void {
        $root = phase7_make_theme([
            'assets/fonts/Inter-VariableFont_slnt,wght.ttf' => 'font-data',
        ]);

        try {
            $manifest = (new WordPressThemeAssetPipeline())->build($root);
            np_assert_true($manifest->safeToUse());
            np_assert_false($manifest->hasBlockers());
            np_assert_same('assets/fonts/Inter-VariableFont_slnt,wght.ttf', $manifest->assets[0]->path);
        } finally {
            phase7_remove_tree($root);
        }
    },
];

/** @param array<string,string> $files */
function phase7_make_theme(array $files): string
{
    $root = sys_get_temp_dir() . '/nanopino-wp-assets-' . bin2hex(random_bytes(6));
    foreach ($files as $relative => $content) {
        $path = $root . '/' . $relative;
        $directory = dirname($path);
        if (!is_dir($directory)) mkdir($directory, 0770, true);
        file_put_contents($path, $content);
    }
    return $root;
}

/** @param list<array{code:string,severity:string,message:string,path?:string}> $issues */
function phase7_has_issue(array $issues, string $code): bool
{
    foreach ($issues as $issue) if (($issue['code'] ?? null) === $code) return true;
    return false;
}

function phase7_remove_tree(string $root): void
{
    if (!is_dir($root)) return;
    $items = scandir($root);
    if ($items === false) return;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $root . '/' . $item;
        if (is_dir($path) && !is_link($path)) phase7_remove_tree($path);
        elseif (is_file($path) || is_link($path)) unlink($path);
    }
    rmdir($root);
}

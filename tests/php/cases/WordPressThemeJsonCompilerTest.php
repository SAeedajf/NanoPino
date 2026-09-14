<?php
declare(strict_types=1);

use App\com_pinoox_cms\Cms\Theme\Design\DesignSchemaValidator;
use App\com_pinoox_cms\Cms\Theme\WordPress\WordPressThemeJsonCompiler;

return [
    'phase 4 compiles WordPress theme.json settings and styles into validated NanoPino design tokens' => static function (): void {
        $theme = [
            'version' => 3,
            'settings' => [
                'appearanceTools' => true,
                'color' => [
                    'palette' => [['slug' => 'brand', 'name' => 'Brand', 'color' => '#1255cc']],
                    'gradients' => [['slug' => 'hero', 'name' => 'Hero', 'gradient' => 'linear-gradient(#fff,#eee)']],
                ],
                'typography' => [
                    'fontFamilies' => [['slug' => 'body', 'name' => 'Body', 'fontFamily' => 'IRANSansX, sans-serif']],
                    'fontSizes' => [['slug' => 'large', 'name' => 'Large', 'size' => 'var:preset|font-size|large']],
                ],
                'spacing' => [
                    'units' => ['px', 'rem', '%'],
                    'spacingSizes' => [['slug' => 'small', 'name' => 'Small', 'size' => '0.5rem']],
                ],
                'layout' => ['contentSize' => '720px', 'wideSize' => '1200px'],
                'shadow' => ['presets' => [['slug' => 'soft', 'name' => 'Soft', 'shadow' => '0 2px 8px #0002']]],
            ],
            'styles' => [
                'color' => ['background' => '#ffffff', 'text' => 'var:preset|color|brand'],
                'typography' => ['fontFamily' => 'var:preset|font-family|body', 'lineHeight' => '1.6'],
                'spacing' => ['padding' => ['top' => '1rem', 'bottom' => '1rem']],
                'elements' => [
                    'button' => ['color' => ['background' => '#1255cc', 'text' => '#fff']],
                    'link' => ['typography' => ['textDecoration' => 'none']],
                ],
            ],
            'customTemplates' => [['name' => 'landing', 'title' => 'Landing']],
        ];

        $result = (new WordPressThemeJsonCompiler())->compile($theme);
        np_assert_true($result->safeToUse());
        np_assert_same(3, $result->sourceVersion);
        np_assert_same('#1255cc', $result->document?->tokens['colors']['palette']['brand']['value']);
        np_assert_same('var(--wp--preset--font-size--large)', $result->document?->tokens['fontSizes']['large']['size']);
        np_assert_same('var(--wp--preset--color--brand)', $result->document?->tokens['colors']['text']);
        np_assert_same('var(--wp--preset--font-family--body)', $result->document?->tokens['typography']['default']['font_family']);
        np_assert_same('720px', $result->document?->tokens['containers']['content']);
        np_assert_same('1rem', $result->document?->tokens['spacing']['default']['padding']['top']);
        np_assert_true(in_array('theme_json.structure_deferred', array_column($result->issues, 'code'), true));
        (new DesignSchemaValidator())->validate(['schema' => 1, 'tokens' => $result->document?->tokens ?? []]);
    },

    'phase 4 compiles WordPress style variations with stable identity and title' => static function (): void {
        $result = (new WordPressThemeJsonCompiler())->compileVariation([
            'version' => 2,
            'title' => 'Dark mode',
            'styles' => ['color' => ['background' => '#111827', 'text' => '#f9fafb']],
        ], 'dark-mode', 'styles/dark-mode.json');

        np_assert_true($result->safeToUse());
        np_assert_true($result->variation !== null);
        np_assert_same('dark-mode', $result->variation->id);
        np_assert_same('Dark mode', $result->variation->title);
        np_assert_same('#111827', $result->variation->tokens['colors']['background']);
    },

    'phase 4 fails closed for unsupported versions and unsafe CSS values' => static function (): void {
        $compiler = new WordPressThemeJsonCompiler();
        $version = $compiler->compile(['version' => 1, 'styles' => []]);
        np_assert_false($version->safeToUse());
        np_assert_true(in_array('theme_json.version_unsupported', array_column($version->issues, 'code'), true));

        $unsafe = $compiler->compile([
            'version' => 3,
            'styles' => ['color' => ['background' => 'url(https://evil.test/x);color:red']],
        ]);
        np_assert_true($unsafe->safeToUse());
        np_assert_false(isset($unsafe->document?->tokens['colors']['background']));
        np_assert_true(in_array('theme_json.style_value_skipped', array_column($unsafe->issues, 'code'), true));
    },

    'phase 4 file compiler uses the bounded native theme reader and reports missing files' => static function (): void {
        $root = sys_get_temp_dir() . '/nanopino-wp-theme-json-' . bin2hex(random_bytes(6));
        mkdir($root, 0777, true);
        file_put_contents($root . '/theme.json', '{"version":3,"styles":{"color":{"background":"#fff"}}}');

        try {
            $compiler = new WordPressThemeJsonCompiler();
            $loaded = $compiler->compileFile($root);
            np_assert_true($loaded->safeToUse());
            np_assert_same('#fff', $loaded->document?->tokens['colors']['background']);

            $missing = $compiler->compileFile($root, 'missing.json');
            np_assert_false($missing->safeToUse());
            np_assert_true(in_array('theme_json.file_missing', array_column($missing->issues, 'code'), true));
        } finally {
            @unlink($root . '/theme.json');
            @rmdir($root);
        }
    },
];

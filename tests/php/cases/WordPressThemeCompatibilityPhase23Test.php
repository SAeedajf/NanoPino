<?php
declare(strict_types=1);

use App\com_pinoox_cms\Cms\Theme\WordPress\WordPressBlockMarkupException;
use App\com_pinoox_cms\Cms\Theme\WordPress\WordPressBlockMarkupParser;
use App\com_pinoox_cms\Cms\Theme\WordPress\WordPressBlockMarkupParseResult;
use App\com_pinoox_cms\Cms\Theme\WordPress\WordPressThemeIntakeService;
use App\com_pinoox_cms\Cms\Theme\WordPress\WordPressThemeImportPreviewService;
use App\com_pinoox_cms\Cms\Theme\WordPress\WordPressThemeProvenance;
use App\com_pinoox_cms\Cms\Theme\WordPress\WordPressClassicThemeConversionWorker;
use App\com_pinoox_cms\Cms\Theme\WordPress\WordPressThemeRelease;
use App\com_pinoox_cms\Cms\Block\BlockRegistry;
use App\com_pinoox_cms\Cms\Block\Core\CoreBlocks;
use App\com_pinoox_cms\Cms\Block\Document\BlockDocumentValidator;

return [
    'phase 2 directory intake creates a signed manifest and verifies an explicit trust anchor' => static function (): void {
        $root = sys_get_temp_dir() . '/nanopino-wp-intake-' . bin2hex(random_bytes(6));
        mkdir($root . '/templates', 0777, true);
        file_put_contents($root . '/style.css', "/*\nTheme Name: Signed Aurora\nVersion: 1.0.0\nLicense: GPL-2.0-or-later\n*/\n");
        file_put_contents($root . '/templates/index.html', '<!-- wp:paragraph --><p>Safe</p><!-- /wp:paragraph -->');

        try {
            $service = new WordPressThemeIntakeService();
            $unsigned = $service->inspectDirectory($root);
            np_assert_true($unsigned->safeToConvert());
            np_assert_same('directory', $unsigned->sourceType);
            np_assert_same('Signed Aurora', $unsigned->metadata['name'] ?? null);
            np_assert_true($unsigned->provenance !== null && $unsigned->provenance['reason'] === 'missing');

            $keyPair = sodium_crypto_sign_keypair();
            $secret = sodium_crypto_sign_secretkey($keyPair);
            $public = sodium_crypto_sign_publickey($keyPair);
            $provenance = (new WordPressThemeProvenance())->sign(
                $unsigned->manifestSha256,
                null,
                null,
                'fixture.publisher',
                $secret,
            );
            $signed = $service->inspectDirectory($root, $provenance, [
                'fixture.publisher' => base64_encode($public),
            ]);
            np_assert_true($signed->safeToConvert());
            np_assert_true($signed->provenance !== null && $signed->provenance['verified'] === true);
            np_assert_false($signed->hasBlockers());
        } finally {
            wp_compat_remove($root);
        }
    },

    'phase 2 archive intake rejects traversal and accepts a bounded WordPress ZIP' => static function (): void {
        $root = sys_get_temp_dir() . '/nanopino-wp-archive-' . bin2hex(random_bytes(6));
        mkdir($root, 0777, true);
        $archive = $root . '/aurora.zip';
        $zip = new ZipArchive();
        np_assert_same(true, $zip->open($archive, ZipArchive::CREATE));
        $zip->addFromString('aurora/style.css', "/* Theme Name: Aurora ZIP\nLicense: MIT */");
        $zip->addFromString('aurora/assets/css/style.css', '/* Secondary stylesheet */');
        $zip->addFromString('aurora/theme.json', '{"version":3,"settings":{"color":{}}}');
        $zip->addFromString('aurora/templates/index.html', '<!-- wp:paragraph --><p>ZIP</p><!-- /wp:paragraph -->');
        $zip->close();

        $malicious = $root . '/traversal.zip';
        $zip = new ZipArchive();
        np_assert_same(true, $zip->open($malicious, ZipArchive::CREATE));
        $zip->addFromString('../outside.php', '<?php echo "no";');
        $zip->close();

        try {
            $service = new WordPressThemeIntakeService();
            $report = $service->inspectArchive($archive);
            np_assert_same('zip', $report->sourceType);
            np_assert_same('aurora', $report->themeRoot);
            np_assert_same('Aurora ZIP', $report->metadata['name'] ?? null);
            np_assert_true($report->sourceSha256 !== null && strlen($report->sourceSha256) === 64);
            np_assert_true($report->safeToConvert());

            $blocked = $service->inspectArchive($malicious);
            np_assert_false($blocked->safeToConvert());
            $codes = array_column($blocked->issues, 'code');
            np_assert_true(in_array('archive.path_invalid', $codes, true));
        } finally {
            wp_compat_remove($root);
        }
    },

    'phase 2 invalid provenance fails closed without exposing secret material' => static function (): void {
        $root = sys_get_temp_dir() . '/nanopino-wp-provenance-' . bin2hex(random_bytes(6));
        mkdir($root, 0777, true);
        file_put_contents($root . '/style.css', "/* Theme Name: Provenance Test\nLicense: GPLv2 */");

        try {
            $service = new WordPressThemeIntakeService();
            $report = $service->inspectDirectory($root, [
                'schema' => 1,
                'algorithm' => WordPressThemeProvenance::ALGORITHM,
                'key_id' => 'unknown',
                'manifest_sha256' => str_repeat('a', 64),
                'source_sha256' => null,
                'theme_root' => null,
                'signature' => base64_encode(random_bytes(SODIUM_CRYPTO_SIGN_BYTES)),
            ], []);
            np_assert_false($report->safeToConvert());
            np_assert_true($report->provenance !== null && $report->provenance['reason'] === 'untrusted_key');
            np_assert_true(!isset($report->toArray()['secret_key']));
        } finally {
            wp_compat_remove($root);
        }
    },

    'phase 3 maps nested WordPress Block Markup to canonical NanoPino nodes' => static function (): void {
        $markup = '<!-- wp:group {"className":"hero","layout":{"type":"constrained"}} --><div class="wp-block-group">'
            . '<!-- wp:heading {"level":2,"align":"center"} --><h2>سلام <em>دنیا</em></h2><!-- /wp:heading -->'
            . '<!-- wp:paragraph --><p>متن <strong>ایمن</strong></p><!-- /wp:paragraph -->'
            . '</div><!-- /wp:group -->'
            . '<!-- wp:button {"url":"/contact","linkTarget":"_blank"} --><a href="/contact">تماس</a><!-- /wp:button -->';

        $parser = new WordPressBlockMarkupParser();
        $result = $parser->parse($markup);
        $registry = new BlockRegistry();
        CoreBlocks::register($registry);
        (new BlockDocumentValidator($registry))->validate($result->document);
        $document = $result->document->toArray();
        np_assert_same(2, count($document['blocks']));
        np_assert_same('core/section', $document['blocks'][0]['type']);
        np_assert_same('core/heading', $document['blocks'][0]['children'][0]['type']);
        np_assert_same('سلام دنیا', $document['blocks'][0]['children'][0]['attributes']['text']);
        np_assert_same('center', $document['blocks'][0]['children'][0]['styles']['textAlign']);
        np_assert_same('core/paragraph', $document['blocks'][0]['children'][1]['type']);
        np_assert_same('متن ایمن', $document['blocks'][0]['children'][1]['attributes']['text']);
        np_assert_same('core/button', $document['blocks'][1]['type']);
        np_assert_same('/contact', $document['blocks'][1]['attributes']['url']);
        np_assert_true($document['blocks'][1]['attributes']['newTab']);
        np_assert_false(in_array('core/group', $result->unsupportedBlocks, true));
        np_assert_true(!$result->hasBlockers());
        np_assert_same($result->document->toArray(), $parser->parse($markup)->document->toArray());
    },

    'phase 3 preserves unsupported children and fails closed for malformed or unsafe markup' => static function (): void {
        $parser = new WordPressBlockMarkupParser();
        $unsupported = $parser->parse('<!-- wp:core/image {"url":"javascript:alert(1)"} /-->');
        np_assert_same('core/section', $unsupported->document->blocks[0]->type);
        np_assert_true(in_array('core/image', $unsupported->unsupportedBlocks, true));

        $unsafe = $parser->parse('<!-- wp:core/button {"url":"javascript:alert(1)"} --><a>Bad</a><!-- /wp:core/button -->');
        np_assert_same('#', $unsafe->document->blocks[0]->attributes['url']);
        np_assert_true(in_array('markup.button_url_invalid', array_column($unsafe->issues, 'code'), true));

        np_assert_throws(
            static fn (): WordPressBlockMarkupParseResult => $parser->parse('<!-- wp:group -->unclosed'),
            WordPressBlockMarkupException::class,
            'unclosed block',
        );
        np_assert_throws(
            static fn (): WordPressBlockMarkupParseResult => $parser->parse('<!-- wp:group --><!-- /wp:paragraph -->'),
            WordPressBlockMarkupException::class,
            'invalid closing boundary',
        );
    },

    'classic conversion accepts valid PHP tails and recognizes GNU GPL metadata' => static function (): void {
        $root = sys_get_temp_dir() . '/nanopino-wp-classic-tail-' . bin2hex(random_bytes(6));
        mkdir($root, 0777, true);
        file_put_contents($root . '/style.css', "/* Theme Name: Twenty Fixture\nLicense: GNU General Public License v2 or later */");
        file_put_contents($root . '/index.php', "<?php get_header(); ?>\n<main><h1>Fixture</h1></main>\n<?php get_footer();");

        try {
            $intake = (new WordPressThemeIntakeService())->inspectDirectory($root);
            np_assert_true($intake->safeToConvert());
            np_assert_false(in_array('license.unverified', array_column($intake->issues, 'code'), true));

            $converted = (new WordPressClassicThemeConversionWorker())->convert($root);
            np_assert_false($converted->hasBlockers());
            np_assert_true($converted->safeToUse());
            np_assert_true(count($converted->templates) === 1);
        } finally {
            wp_compat_remove($root);
        }
    },

    'WordPress archive preview converts in a private sandbox and reports install readiness' => static function (): void {
        $root = sys_get_temp_dir() . '/nanopino-wp-preview-' . bin2hex(random_bytes(6));
        mkdir($root, 0777, true);
        $archive = $root . '/preview.zip';
        $zip = new ZipArchive();
        np_assert_same(true, $zip->open($archive, ZipArchive::CREATE));
        $zip->addFromString('aurora/style.css', "/* Theme Name: Preview Aurora\nVersion: 1.0.0\nLicense: GPL-2.0-or-later */");
        $zip->addFromString('aurora/index.php', "<?php get_header(); ?>\n<main><h1>Preview</h1></main>\n<?php get_footer();");
        $zip->close();
        $before = glob(sys_get_temp_dir() . '/nanopino-wp-import-*', GLOB_ONLYDIR) ?: [];

        try {
            $preview = (new WordPressThemeImportPreviewService())->preview($archive)->toArray();
            np_assert_same('wordpress-theme-preview-v1', $preview['workflow'] ?? null);
            np_assert_true((bool)($preview['install_supported'] ?? false));
            np_assert_true((bool)($preview['install_requires_confirmation'] ?? false));
            np_assert_true((bool)($preview['ready_for_review'] ?? false));
            np_assert_same(true, $preview['intake']['safe_to_convert'] ?? false);
            np_assert_same(true, $preview['conversion']['safe_to_use'] ?? false);
            np_assert_same(true, $preview['assets']['safe_to_use'] ?? false);
            np_assert_same('[private-sandbox]', $preview['scan']['root'] ?? null);
            np_assert_false(str_contains(json_encode($preview, JSON_THROW_ON_ERROR), $root));
            np_assert_same($before, glob(sys_get_temp_dir() . '/nanopino-wp-import-*', GLOB_ONLYDIR) ?: []);
        } finally {
            wp_compat_remove($root);
        }
    },

    'converted WordPress releases use stable monotonic version codes' => static function (): void {
        np_assert_same('1.7', WordPressThemeRelease::normalizeVersion('1.7'));
        np_assert_same('0.1.0', WordPressThemeRelease::normalizeVersion('not-a-version'));
        np_assert_true(WordPressThemeRelease::versionCode('1.8.0') > WordPressThemeRelease::versionCode('1.7'));
        np_assert_true(WordPressThemeRelease::versionCode('2.0.0') > WordPressThemeRelease::versionCode('1.99.99'));
        np_assert_same(WordPressThemeRelease::versionCode('1.7'), WordPressThemeRelease::versionCode('1.7.0'));
    },
];

function wp_compat_remove(string $path): void
{
    if (!is_dir($path)) return;
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $child = $path . '/' . $entry;
        is_dir($child) && !is_link($child) ? wp_compat_remove($child) : @unlink($child);
    }
    @rmdir($path);
}

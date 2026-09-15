<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\WordPress;

use App\com_pinoox_cms\Cms\Block\Document\BlockDocument;
use App\com_pinoox_cms\Cms\Block\Document\BlockNode;
use App\com_pinoox_cms\Cms\Extension\ExtensionType;
use App\com_pinoox_cms\Cms\Installer\CanonicalPackagePath;
use App\com_pinoox_cms\Cms\Sdk\Package\ExtensionPackageBlueprint;
use InvalidArgumentException;
use Pinoox\Component\Package\Pinx\PinxManifest;
use Pinoox\Component\Package\Pinx\PinxSignKey;
use Pinoox\Component\Package\Pinx\PinxSignature;
use RuntimeException;
use ZipArchive;

/**
 * Turns a safe static WordPress conversion into a native, signed theme PINX.
 *
 * This builder copies only the conversion output and allowlisted CSS/JS/font
 * assets. It never copies WordPress PHP into the native theme and never runs
 * untrusted source code.
 */
final readonly class WordPressThemeNativePackageBuilder
{
    public function __construct(
        private WordPressThemeIntakeService $intake = new WordPressThemeIntakeService(),
        private WordPressThemeScanner $scanner = new WordPressThemeScanner(),
        private WordPressClassicThemeConversionWorker $classic = new WordPressClassicThemeConversionWorker(),
        private WordPressThemeStructureConverter $structure = new WordPressThemeStructureConverter(),
        private WordPressThemeAssetPipeline $assets = new WordPressThemeAssetPipeline(),
        private ?string $signingKeyPath = null,
    ) {}

    public function build(string $archivePath): WordPressThemeNativePackage
    {
        $intake = $this->intake->inspectArchive($archivePath);
        if (!$intake->safeToConvert()) {
            throw new RuntimeException('WordPress archive has intake blockers and cannot be installed.');
        }

        $sandbox = $this->sandbox('source');
        $packageRoot = $this->sandbox('package');
        try {
            $themeRoot = $this->extract($archivePath, $intake, $sandbox);
            $scan = $this->scanner->scan($themeRoot);
            $conversion = match ($scan->type) {
                WordPressThemeType::Classic, WordPressThemeType::Hybrid => $this->classic->convert($themeRoot),
                WordPressThemeType::Block => $this->structure->convert($themeRoot),
                default => throw new RuntimeException('Unsupported WordPress theme type for native conversion.'),
            };
            $assetManifest = $this->assets->build($themeRoot);

            $conversionSafe = $conversion instanceof WordPressClassicThemeConversionReport
                ? $conversion->safeToUse()
                : $conversion->safeToUse();
            if (!$conversionSafe || !$assetManifest->safeToUse()) {
                throw new RuntimeException('WordPress archive has conversion or asset blockers and cannot be installed.');
            }

            $themeName = $this->themeName($intake, $scan);
            $title = $this->title($intake, $themeName);
            $version = $this->version($intake->metadata['version'] ?? null);
            $versionCode = WordPressThemeRelease::versionCode($version);
            $manifest = $this->manifest($themeName, $title, $version, $versionCode, $intake, $scan);
            $this->writeTheme($packageRoot, $themeName, $title, $version, $versionCode, $intake, $scan, $conversion, $assetManifest, $themeRoot);
            $this->writePinx($packageRoot, $manifest);

            $source = [
                'workflow' => 'wordpress-theme-native-package-v1',
                'source_archive_sha256' => $intake->sourceSha256,
                'source_manifest_sha256' => $intake->manifestSha256,
                'theme_name' => $themeName,
                'converter' => 'NanoShell static-only converter',
            ];
            return new WordPressThemeNativePackage($this->sign($packageRoot), $manifest, $source);
        } catch (\Throwable $error) {
            $this->removeTree($packageRoot);
            throw $error;
        } finally {
            $this->removeTree($sandbox);
        }
    }

    public function cleanup(WordPressThemeNativePackage $package): void
    {
        $this->removeTree(dirname($package->path));
    }

    /** @param array<string,mixed> $manifest */
    private function writePinx(string $root, array $manifest): void
    {
        $file = $root . '/manifest.json';
        if (file_put_contents($file, json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n", LOCK_EX) === false) {
            throw new RuntimeException('Native theme manifest could not be written.');
        }
        @chmod($file, 0600);
    }

    private function sign(string $root): string
    {
        $path = $root . '/theme.pinx';
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Native theme PINX could not be created.');
        }
        try {
            if (!$zip->addFile($root . '/manifest.json', 'manifest.json')) {
                throw new RuntimeException('Native theme manifest could not be added to PINX.');
            }
            $this->addTree($zip, $root, $root, ['manifest.json', 'theme.pinx']);
            if (!$zip->close()) throw new RuntimeException('Native theme PINX could not be finalized.');
        } catch (\Throwable $error) {
            $zip->close();
            @unlink($path);
            throw $error;
        }

        $reader = new \Pinoox\Component\Package\Pinx\PinxReader();
        try {
            $reader->open($path);
            $key = $this->signingKey();
            $signature = PinxSignature::create(
                $reader->manifestJson(),
                PinxSignature::payloadHashes($reader->zip()),
                $key,
            );
        } finally {
            $reader->close();
        }

        $archive = new ZipArchive();
        if ($archive->open($path) !== true) throw new RuntimeException('Native theme PINX could not be reopened for signing.');
        try {
            if (!$archive->addFromString(PinxSignature::FILE, json_encode($signature, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n")) {
                throw new RuntimeException('Native theme PINX signature could not be written.');
            }
            if (!$archive->close()) throw new RuntimeException('Signed native theme PINX could not be finalized.');
        } catch (\Throwable $error) {
            $archive->close();
            @unlink($path);
            throw $error;
        }
        @chmod($path, 0600);
        return $path;
    }

    /** @return array{key_id:string,algorithm:string,public_key:string,secret_key:string} */
    private function signingKey(): array
    {
        if ($this->signingKeyPath === null || trim($this->signingKeyPath) === '') {
            return PinxSignKey::generate('com_pinoox_cms', 'nanoshell-local-converter-v2');
        }

        if (is_file($this->signingKeyPath)) {
            return PinxSignKey::load($this->signingKeyPath);
        }

        $key = PinxSignKey::generate('com_pinoox_cms', 'nanoshell-local-converter-v2');
        PinxSignKey::save($key, $this->signingKeyPath);
        @chmod($this->signingKeyPath, 0600);
        return $key;
    }

    /** @param array<string,mixed> $manifest */
    private function addTree(ZipArchive $zip, string $root, string $current, array $skip): void
    {
        foreach (scandir($current) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || in_array($entry, $skip, true)) continue;
            $file = $current . '/' . $entry;
            if (is_link($file)) throw new RuntimeException('Generated native theme contains a symlink.');
            $relative = ltrim(str_replace('\\', '/', substr($file, strlen($root))), '/');
            $target = 'payload/' . $relative;
            CanonicalPackagePath::normalize($target);
            if (is_dir($file)) $this->addTree($zip, $root, $file, $skip);
            elseif (is_file($file) && !$zip->addFile($file, $target)) throw new RuntimeException('Native theme file could not be added: ' . $relative);
        }
    }

    /** @param array<string,mixed> $intake @param array<string,mixed> $scan */
    private function manifest(string $themeName, string $title, string $version, int $versionCode, WordPressThemeIntakeReport $intake, WordPressThemeScanResult $scan): array
    {
        $blueprint = new ExtensionPackageBlueprint(
            package: 'com_pinoox_cms',
            name: $title,
            type: ExtensionType::Theme,
            version: $version,
            versionCode: $versionCode,
            publisher: 'nanoshell-local-converter',
            description: 'Static NanoShell conversion of ' . $title . '.',
            targetApp: 'com_pinoox_cms',
            themeName: $themeName,
        );
        $manifest = $blueprint->toPinxManifest();
        $manifest['labels'] = [
            'title' => ['en' => $title, 'fa' => $title],
            'description' => ['en' => 'Static NanoShell conversion of ' . $title . '.', 'fa' => 'تبدیل ایمن و ایستای ' . $title . ' برای NanoShell.'],
        ];
        $manifest['cms']['requires']['cms'] = '>=0.23.0';
        $manifest['cms']['theme'] = [
            'name' => $themeName,
            'target_app' => 'com_pinoox_cms',
            'minimum_cms' => '0.23.0',
            'paths' => ['design' => 'design.json', 'templates' => 'templates', 'parts' => 'parts', 'patterns' => 'patterns', 'variations' => 'styles/variations'],
            'template_extensions' => ['twig'],
            'features' => ['source' => 'wordpress', 'runtime_boundary' => 'static-only', 'theme_type' => $scan->type->value],
        ];
        $manifest['cms']['metadata'] = [
            'source' => 'wordpress-archive',
            'source_archive_sha256' => $intake->sourceSha256,
            'source_manifest_sha256' => $intake->manifestSha256,
            'source_version' => $version,
            'source_version_code' => $versionCode,
            'source_files' => $intake->files,
            'conversion' => 'static-only',
        ];
        $manifest['theme_meta'] = [
            'name' => $themeName,
            'app' => 'com_pinoox_cms',
            'developer' => 'NanoShell Local Converter',
            'version' => $version,
            'app_version' => 1,
            'title' => ['en' => $title, 'fa' => $title],
            'description' => ['en' => 'Static NanoShell conversion of ' . $title . '.'],
            'extends' => [],
            'cover' => '',
            'api' => false,
        ];
        (new \App\com_pinoox_cms\Cms\Manifest\ExtensionManifestValidator())->validate($manifest);
        return $manifest;
    }

    private function writeTheme(string $packageRoot, string $themeName, string $title, string $version, int $versionCode, WordPressThemeIntakeReport $intake, WordPressThemeScanResult $scan, object $conversion, WordPressThemeAssetManifest $assetManifest, string $sourceRoot): void
    {
        $themeRoot = $packageRoot . '/theme/' . $themeName;
        $this->mkdir($themeRoot . '/templates');
        $this->mkdir($themeRoot . '/parts');
        $this->mkdir($themeRoot . '/patterns');
        $this->mkdir($themeRoot . '/styles/variations');
        $this->write($themeRoot . '/theme.php', "<?php\nreturn " . var_export([
            'name' => $themeName,
            'package' => 'com_pinoox_cms',
            'developer' => 'NanoShell Local Converter',
            'copyright' => (string)($intake->metadata['license'] ?? 'Converted source; review license before distribution.'),
            'version-name' => $version,
            'version-code' => $versionCode,
            'title' => $title,
            'description' => 'Static NanoShell conversion of ' . $title . '.',
            'cms' => [
                'schema' => 1,
                'requires' => ['cms' => '>=0.23.0'],
                'theme' => [
                    'name' => $themeName,
                    'target_app' => 'com_pinoox_cms',
                    'paths' => ['design' => 'design.json', 'templates' => 'templates', 'parts' => 'parts', 'patterns' => 'patterns', 'variations' => 'styles/variations'],
                    'template_extensions' => ['twig'],
                    'features' => ['source' => 'wordpress', 'runtime_boundary' => 'static-only', 'theme_type' => $scan->type->value],
                ],
                'metadata' => ['source' => 'wordpress-archive', 'conversion' => 'static-only', 'source_version' => $version, 'source_version_code' => $versionCode],
            ],
        ], true) . ";\n");
        $this->write($themeRoot . '/design.json', json_encode(['schema' => 1, 'rtl' => true, 'colors' => ['primary' => '#2563eb', 'background' => '#f5f7fb', 'surface' => '#ffffff', 'text' => '#172033'], 'spacing' => ['md' => '1rem'], 'radius' => ['base' => '1rem']], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

        $templates = $conversion instanceof WordPressClassicThemeConversionReport ? $conversion->templates : [...$conversion->templates, ...$conversion->parts];
        foreach ($templates as $template) {
            $directory = $template->kind === 'part' ? $themeRoot . '/parts' : $themeRoot . '/templates';
            $this->write($directory . '/' . $this->safeName($template->logicalName) . '.twig', $this->renderDocument($template->document));
        }
        if ($conversion instanceof WordPressThemeStructureReport) {
            foreach ($conversion->patterns as $pattern) {
                $id = str_replace('/', '-', $this->safeName($pattern->id));
                $this->write($themeRoot . '/patterns/' . $id . '.json', json_encode(['id' => $pattern->id, 'title' => $pattern->title, 'categories' => $pattern->categories, 'document' => $pattern->document], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
            }
        }

        foreach ($assetManifest->assets as $asset) {
            $source = $sourceRoot . '/' . $asset->path;
            if (!is_file($source) || is_link($source)) throw new RuntimeException('Converted asset source is unavailable.');
            $target = $themeRoot . '/assets/' . $asset->path;
            $this->mkdir(dirname($target));
            if (!copy($source, $target)) throw new RuntimeException('Converted asset could not be copied.');
            @chmod($target, 0600);
        }
        $this->write($themeRoot . '/conversion.json', json_encode([
            'schema' => 2,
            'source' => 'wordpress',
            'source_archive_sha256' => $intake->sourceSha256,
            'source_manifest_sha256' => $intake->manifestSha256,
            'source_version' => $version,
            'source_version_code' => $versionCode,
            'source_files' => $intake->files,
            'runtime_boundary' => 'static-only',
            'asset_count' => count($assetManifest->assets),
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }

    private function renderDocument(BlockDocument $document): string
    {
        $html = '';
        foreach ($document->blocks as $node) $html .= $this->renderNode($node);
        return $html !== '' ? $html : '<div data-nanoshell-empty="true"></div>\n';
    }

    private function renderNode(BlockNode $node): string
    {
        $children = '';
        foreach ($node->children as $child) $children .= $this->renderNode($child);
        $attr = $node->attributes;
        return match ($node->type) {
            'core/heading' => '<h' . max(1, min(6, (int)($attr['level'] ?? 2))) . $this->nodePresentation($node) . '>' . $this->escape((string)($attr['text'] ?? '')) . '</h' . max(1, min(6, (int)($attr['level'] ?? 2))) . '>\n',
            'core/button' => '<a class="cms-block-button" href="' . $this->escape($this->url((string)($attr['url'] ?? '#'))) . '"' . ($node->attributes['newTab'] ?? false ? ' target="_blank" rel="noopener noreferrer"' : '') . $this->nodePresentation($node) . '>' . $this->escape((string)($attr['label'] ?? '')) . '</a>\n',
            'core/section' => $this->renderSectionNode($node, $children),
            'core/paragraph' => '<p' . $this->nodePresentation($node) . '>' . $this->escape((string)($attr['text'] ?? '')) . '</p>\n',
            default => ($children !== '' ? $children : '<div data-nanoshell-block="' . $this->escape($this->safeName($node->type)) . '"></div>\n'),
        };
    }

    private function renderSectionNode(BlockNode $node, string $children): string
    {
        $attributes = $node->attributes;
        $tag = in_array(($attributes['tag'] ?? 'section'), ['section', 'div', 'main', 'article', 'aside', 'header', 'footer', 'nav'], true)
            ? (string)$attributes['tag']
            : 'section';
        return '<' . $tag . ' class="cms-block-section ' . $this->escape($this->className((string)($attributes['className'] ?? ''))) . '"' . $this->nodePresentation($node) . '>' . $children . '</' . $tag . '>\n';
    }

    private function nodePresentation(BlockNode $node): string
    {
        $styleMap = [
            'color' => 'color', 'backgroundColor' => 'background-color', 'fontSize' => 'font-size',
            'fontWeight' => 'font-weight', 'lineHeight' => 'line-height', 'letterSpacing' => 'letter-spacing',
            'margin' => 'margin', 'padding' => 'padding', 'width' => 'width', 'height' => 'height',
            'minWidth' => 'min-width', 'maxWidth' => 'max-width', 'minHeight' => 'min-height',
            'maxHeight' => 'max-height', 'display' => 'display', 'alignItems' => 'align-items',
            'justifyContent' => 'justify-content', 'gap' => 'gap', 'gridTemplateColumns' => 'grid-template-columns',
            'textAlign' => 'text-align', 'borderRadius' => 'border-radius', 'borderWidth' => 'border-width',
            'borderColor' => 'border-color', 'opacity' => 'opacity',
        ];
        $declarations = [];
        foreach ($node->styles as $key => $value) {
            $value = trim((string)$value);
            if (!isset($styleMap[$key]) || $value === '' || strlen($value) > 200) continue;
            if (preg_match('/^[a-zA-Z0-9#%().,\s\/_-]+$/', $value) !== 1) continue;
            $declarations[] = $styleMap[$key] . ':' . $value . ';';
        }
        $attributes = ' data-cms-block-id="' . $this->escape($node->id) . '"';
        return $declarations === [] ? $attributes : $attributes . ' style="' . $this->escape(implode('', $declarations)) . '"';
    }

    private function themeName(WordPressThemeIntakeReport $intake, WordPressThemeScanResult $scan): string
    {
        return WordPressThemeRelease::themeName($intake->themeRoot, $intake->metadata['text_domain'] ?? null, $intake->sourceSha256 ?: hash('sha256', $scan->root));
    }

    private function title(WordPressThemeIntakeReport $intake, string $fallback): string
    {
        $title = trim((string)($intake->metadata['name'] ?? ''));
        return $title !== '' ? (function_exists('mb_substr') ? mb_substr($title, 0, 120) : substr($title, 0, 120)) : $fallback;
    }

    private function version(mixed $value): string
    {
        return WordPressThemeRelease::normalizeVersion($value);
    }

    private function sandbox(string $suffix): string
    {
        $path = rtrim(sys_get_temp_dir(), '/\\') . '/nanopino-wp-' . $suffix . '-' . bin2hex(random_bytes(16));
        $this->mkdir($path, 0700);
        return $path;
    }

    private function extract(string $archivePath, WordPressThemeIntakeReport $report, string $sandbox): string
    {
        $archive = new ZipArchive();
        $real = realpath($archivePath);
        if ($real === false || $archive->open($real, ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) throw new RuntimeException('WordPress archive could not be reopened.');
        try {
            foreach ($report->files as $entry) {
                $relative = CanonicalPackagePath::normalize($entry['path']);
                $target = $sandbox . '/' . $relative;
                if (is_link($target) || file_exists($target)) throw new RuntimeException('Archive extraction path collision.');
                $this->mkdir(dirname($target), 0700);
                $contents = $archive->getFromName($relative);
                if (!is_string($contents) || strlen($contents) !== $entry['size'] || !hash_equals($entry['sha256'], hash('sha256', $contents))) throw new RuntimeException('Archive entry integrity check failed.');
                if (file_put_contents($target, $contents, LOCK_EX) !== strlen($contents)) throw new RuntimeException('Archive entry could not be extracted.');
                @chmod($target, 0600);
            }
        } finally { $archive->close(); }
        $root = $sandbox . ($report->themeRoot === null || $report->themeRoot === '' ? '' : '/' . $report->themeRoot);
        if (!is_dir($root) || is_link($root)) throw new RuntimeException('Resolved theme root is unavailable.');
        return $root;
    }

    private function safeName(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9._-]+/i', '-', $value) ?? 'item';
        return trim($value, '-.') ?: 'item';
    }

    private function className(string $value): string { return trim(preg_replace('/[^a-z0-9 _-]+/i', '', $value) ?? ''); }
    private function url(string $value): string { return preg_match('/^(?:https?:\/\/|\/|#|[a-z0-9][a-z0-9._\/-]*)$/i', $value) === 1 ? $value : '#'; }
    private function escape(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'); }
    private function mkdir(string $path, int $mode = 0700): void { if (!is_dir($path) && !mkdir($path, $mode, true) && !is_dir($path)) throw new RuntimeException('Unable to create native theme staging directory.'); }
    private function write(string $path, string $contents): void { $this->mkdir(dirname($path)); if (file_put_contents($path, $contents, LOCK_EX) === false) throw new RuntimeException('Unable to write generated native theme file.'); @chmod($path, 0600); }
    private function removeTree(string $path): void { if (!file_exists($path) && !is_link($path)) return; if (is_file($path) || is_link($path)) { @unlink($path); return; } foreach (scandir($path) ?: [] as $entry) if ($entry !== '.' && $entry !== '..') $this->removeTree($path . '/' . $entry); @rmdir($path); }
}

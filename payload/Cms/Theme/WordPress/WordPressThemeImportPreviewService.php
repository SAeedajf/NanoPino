<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\WordPress;

use App\com_pinoox_cms\Cms\Installer\CanonicalPackagePath;
use RuntimeException;
use ZipArchive;

/**
 * Bounded WordPress archive preview boundary.
 *
 * The archive is inspected, extracted into a private temporary sandbox, and
 * converted as static text only. No WordPress PHP, hooks, plugins, scripts or
 * remote assets are executed. The sandbox is removed before the request ends.
 */
final readonly class WordPressThemeImportPreviewService
{
    public function __construct(
        private WordPressThemeIntakeService $intake = new WordPressThemeIntakeService(),
        private WordPressThemeScanner $scanner = new WordPressThemeScanner(),
        private WordPressClassicThemeConversionWorker $classic = new WordPressClassicThemeConversionWorker(),
        private WordPressThemeStructureConverter $structure = new WordPressThemeStructureConverter(),
        private WordPressThemeAssetPipeline $assets = new WordPressThemeAssetPipeline(),
    ) {}

    public function preview(string $archivePath): WordPressThemeImportPreview
    {
        $intake = $this->intake->inspectArchive($archivePath);
        $publicIntake = $this->publicIntake($intake);
        if (!$intake->safeToConvert()) {
            return new WordPressThemeImportPreview(
                $publicIntake,
                null,
                null,
                null,
                false,
                ['Resolve all intake blockers before static conversion.'],
            );
        }

        $sandbox = $this->sandbox();
        try {
            $root = $this->extract($archivePath, $intake, $sandbox);
            $scan = $this->scanner->scan($root);
            $conversion = match ($scan->type) {
                WordPressThemeType::Classic, WordPressThemeType::Hybrid => $this->classic->convert($root)->toArray(),
                WordPressThemeType::Block => $this->structure->convert($root)->toArray(),
                default => null,
            };
            $assets = $this->assets->build($root)->toArray();
            $conversionSafe = is_array($conversion) && (($conversion['safe_to_use'] ?? false) === true);
            $assetsSafe = ($assets['safe_to_use'] ?? false) === true;
            $ready = $conversionSafe && $assetsSafe;

            return new WordPressThemeImportPreview(
                $publicIntake,
                $this->publicScan($scan),
                $this->publicConversion($conversion),
                $this->publicAssets($assets),
                $ready,
                $ready
                    ? ['Review the static conversion and explicitly package it as a signed native NanoShell theme.']
                    : ['Resolve conversion or asset blockers before native packaging.'],
            );
        } finally {
            $this->removeTree($sandbox);
        }
    }

    /** @return array<string,mixed> */
    private function publicIntake(WordPressThemeIntakeReport $report): array
    {
        $data = $report->toArray();
        $data['source'] = 'uploaded-archive';
        return $data;
    }

    /** @return array<string,mixed> */
    private function publicScan(WordPressThemeScanResult $scan): array
    {
        $data = $scan->toArray();
        $data['root'] = '[private-sandbox]';
        return $data;
    }

    /** @param array<string,mixed>|null $conversion @return array<string,mixed>|null */
    private function publicConversion(?array $conversion): ?array
    {
        if ($conversion === null) return null;
        if (array_key_exists('theme_root', $conversion)) $conversion['theme_root'] = '[private-sandbox]';
        foreach (($conversion['templates'] ?? []) as &$template) {
            if (is_array($template) && array_key_exists('source_path', $template)) $template['source_path'] = basename((string)$template['source_path']);
        }
        unset($template);
        foreach (($conversion['parts'] ?? []) as &$part) {
            if (is_array($part) && array_key_exists('source_path', $part)) $part['source_path'] = basename((string)$part['source_path']);
        }
        unset($part);
        foreach (($conversion['patterns'] ?? []) as &$pattern) {
            if (is_array($pattern) && array_key_exists('source_theme_path', $pattern)) $pattern['source_theme_path'] = basename((string)$pattern['source_theme_path']);
        }
        unset($pattern);
        return $conversion;
    }

    /** @param array<string,mixed> $assets @return array<string,mixed> */
    private function publicAssets(array $assets): array
    {
        if (array_key_exists('theme_root', $assets)) $assets['theme_root'] = '[private-sandbox]';
        return $assets;
    }

    private function sandbox(): string
    {
        $path = rtrim(sys_get_temp_dir(), '/\\') . '/nanopino-wp-import-' . bin2hex(random_bytes(16));
        if (!mkdir($path, 0700, true) && !is_dir($path)) throw new RuntimeException('Unable to create private WordPress import sandbox.');
        return $path;
    }

    private function extract(string $archivePath, WordPressThemeIntakeReport $report, string $sandbox): string
    {
        $archive = new ZipArchive();
        $realArchive = realpath($archivePath);
        if ($realArchive === false || $archive->open($realArchive, ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) {
            throw new RuntimeException('WordPress theme archive could not be reopened for bounded extraction.');
        }

        try {
            foreach ($report->files as $entry) {
                $relative = CanonicalPackagePath::normalize($entry['path']);
                $target = $sandbox . '/' . $relative;
                if (!$this->inside($sandbox, $target)) throw new RuntimeException('WordPress archive extraction escaped its sandbox.');
                $directory = dirname($target);
                if (!is_dir($directory) && (!mkdir($directory, 0700, true) || !is_dir($directory))) {
                    throw new RuntimeException('WordPress archive extraction directory could not be created.');
                }
                if (is_link($target) || file_exists($target)) throw new RuntimeException('WordPress archive extraction path collision detected.');
                $contents = $archive->getFromName($relative);
                if (!is_string($contents) || strlen($contents) !== (int)$entry['size'] || !hash_equals((string)$entry['sha256'], hash('sha256', $contents))) {
                    throw new RuntimeException('WordPress archive entry integrity check failed.');
                }
                if (file_put_contents($target, $contents, LOCK_EX) !== strlen($contents)) throw new RuntimeException('WordPress archive entry could not be extracted.');
                @chmod($target, 0600);
            }
        } finally {
            $archive->close();
        }

        $themeRoot = $report->themeRoot ?? '';
        $root = $sandbox . ($themeRoot === '' ? '' : '/' . $themeRoot);
        if (!is_dir($root) || is_link($root)) throw new RuntimeException('Resolved WordPress theme root is unavailable after extraction.');
        return $root;
    }

    private function inside(string $root, string $path): bool
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $path = str_replace('\\', '/', $path);
        return $path === $root || str_starts_with($path, $root . '/');
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path) || is_link($path)) {
            if (is_link($path) || is_file($path)) @unlink($path);
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $child = $path . '/' . $entry;
            if (is_link($child) || is_file($child)) @unlink($child);
            elseif (is_dir($child)) $this->removeTree($child);
        }
        @rmdir($path);
    }
}

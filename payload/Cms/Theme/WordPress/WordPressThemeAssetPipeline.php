<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\WordPress;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use RuntimeException;

/**
 * Builds a deterministic, static WordPress theme asset manifest.
 * JavaScript, CSS and PHP sidecar metadata are read only; none is executed.
 */
final class WordPressThemeAssetPipeline
{
    private const MAX_FILES = 500;
    private const MAX_FILE_BYTES = 2_097_152;
    private const MAX_TOTAL_BYTES = 20_000_000;
    private const IGNORED_DIRECTORIES = ['.git', 'node_modules', 'vendor'];
    private const CSS_EXTENSIONS = ['css'];
    private const JS_EXTENSIONS = ['js', 'mjs'];
    private const FONT_EXTENSIONS = ['woff', 'woff2', 'ttf', 'otf', 'eot'];

    public function __construct(
        private readonly int $maxFiles = self::MAX_FILES,
        private readonly int $maxFileBytes = self::MAX_FILE_BYTES,
        private readonly int $maxTotalBytes = self::MAX_TOTAL_BYTES,
    ) {
        if ($this->maxFiles < 1 || $this->maxFileBytes < 1 || $this->maxTotalBytes < 1) {
            throw new \InvalidArgumentException('WordPress asset limits must be positive.');
        }
    }

    public function build(string $themeRoot): WordPressThemeAssetManifest
    {
        $root = realpath($themeRoot);
        if ($root === false || !is_dir($root)) {
            return new WordPressThemeAssetManifest($themeRoot, issues: [[
                'code' => 'assets.theme_root_invalid',
                'severity' => 'blocker',
                'message' => 'WordPress theme asset root does not exist or is not a directory.',
            ]]);
        }

        $issues = [];
        $files = $this->discover($root, $issues);
        $assets = [];
        foreach ($files as $file) {
            try {
                $asset = $this->asset($root, $file, $issues);
                if ($asset === null) continue;
                if (isset($assets[$asset->key])) {
                    $issues[] = ['code' => 'assets.key_collision', 'severity' => 'blocker', 'message' => 'Two asset paths map to the same normalized manifest key.', 'path' => $asset->path];
                    continue;
                }
                $assets[$asset->key] = $asset;
            } catch (\Throwable $error) {
                $issues[] = ['code' => 'assets.file_invalid', 'severity' => 'blocker', 'message' => $error->getMessage(), 'path' => $this->relative($root, $file)];
            }
        }

        $assets = $this->attachCssReferences($root, $assets, $issues);
        [$assets, $ordered] = $this->resolveDependencies($assets, $issues);
        ksort($assets, SORT_STRING);
        return new WordPressThemeAssetManifest($root, array_values($assets), $ordered, $issues);
    }

    /** @param list<array{code:string,severity:string,message:string,path?:string}> $issues @return list<string> */
    private function discover(string $root, array &$issues): array
    {
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY,
            );
        } catch (\Throwable $error) {
            $issues[] = ['code' => 'assets.discovery_failed', 'severity' => 'blocker', 'message' => 'Theme assets could not be inspected.'];
            return [];
        }

        $files = [];
        $total = 0;
        foreach ($iterator as $info) {
            if (!$info instanceof SplFileInfo || !$info->isFile()) continue;
            $path = $this->normalize($info->getPathname());
            $relative = $this->relative($root, $path);
            if ($relative === '' || $this->ignored($relative)) continue;
            if ($info->isLink()) {
                $issues[] = ['code' => 'assets.symlink_skipped', 'severity' => 'warning', 'message' => 'Symbolic link was excluded from the asset manifest.', 'path' => $relative];
                continue;
            }
            $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
            if (!$this->isAssetExtension($extension)) continue;
            if (!$this->safeRelative($relative)) {
                $issues[] = ['code' => 'assets.path_invalid', 'severity' => 'blocker', 'message' => 'Theme asset path is unsafe.', 'path' => $relative];
                continue;
            }
            $real = realpath($path);
            if ($real === false || !$this->inside($root, $real)) {
                $issues[] = ['code' => 'assets.path_escape', 'severity' => 'blocker', 'message' => 'Theme asset resolves outside its root.', 'path' => $relative];
                continue;
            }
            $size = $info->getSize();
            if ($size < 0 || $size > $this->maxFileBytes) {
                $issues[] = ['code' => 'assets.file_too_large', 'severity' => 'blocker', 'message' => 'Theme asset exceeds the per-file byte limit.', 'path' => $relative];
                continue;
            }
            if (count($files) >= $this->maxFiles) {
                $issues[] = ['code' => 'assets.file_count_exceeded', 'severity' => 'blocker', 'message' => 'Theme exceeds the asset file limit.'];
                break;
            }
            $total += $size;
            if ($total > $this->maxTotalBytes) {
                $issues[] = ['code' => 'assets.total_size_exceeded', 'severity' => 'blocker', 'message' => 'Theme exceeds the total asset byte limit.'];
                break;
            }
            $files[] = $real;
        }
        sort($files, SORT_STRING);
        return $files;
    }

    /** @param list<array{code:string,severity:string,message:string,path?:string}> $issues */
    private function asset(string $root, string $file, array &$issues): ?WordPressThemeAsset
    {
        $relative = $this->relative($root, $file);
        $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
        $kind = $this->kind($extension);
        if ($kind === null) return null;
        $content = $this->read($file);
        $key = strtolower($relative);
        $dependencies = [];
        $version = null;
        if ($kind === WordPressAssetKind::JavaScript) {
            [$dependencies, $version] = $this->sidecar($file, $root, $issues);
            if (preg_match('/\bimport\s*\(/i', $content) === 1) {
                $issues[] = ['code' => 'assets.dynamic_import_deferred', 'severity' => 'warning', 'message' => 'Dynamic JavaScript import was not executed or bundled.', 'path' => $relative];
            }
        }
        $direction = $kind === WordPressAssetKind::Css ? $this->direction($relative, $content) : 'both';
        $references = $kind === WordPressAssetKind::Css ? $this->references($root, $file, $content, $issues) : [];
        return new WordPressThemeAsset($key, $relative, $kind, strlen($content), hash('sha256', $content), $direction, $version, $dependencies, $references);
    }

    /** @return array{0:list<string>,1:?string} */
    private function sidecar(string $file, string $root, array &$issues): array
    {
        $sidecar = dirname($file) . DIRECTORY_SEPARATOR . pathinfo($file, PATHINFO_FILENAME) . '.asset.php';
        if (!is_file($sidecar) || is_link($sidecar) || !$this->inside($root, realpath($sidecar) ?: $sidecar)) return [[], null];
        $content = $this->read($sidecar);
        $dependencies = [];
        if (preg_match('/[\'\"]dependencies[\'\"]\s*=>\s*(?:array\s*\((.*?)\)|\[(.*?)\])/is', $content, $match) === 1) {
            $body = (string)($match[1] !== '' ? $match[1] : $match[2]);
            preg_match_all('/[\'\"]([^\'\"]{1,120})[\'\"]/i', $body, $items);
            $dependencies = array_values(array_unique(array_map('strval', $items[1] ?? [])));
        }
        foreach ($dependencies as $dependency) {
            if (preg_match('/^[A-Za-z0-9._@\/-]+$/', $dependency) !== 1) {
                $issues[] = ['code' => 'assets.dependency_invalid', 'severity' => 'blocker', 'message' => 'Asset dependency name is invalid.', 'path' => $this->relative($root, $file)];
            }
        }
        $version = preg_match('/[\'\"]version[\'\"]\s*=>\s*[\'\"]([^\'\"]{1,80})[\'\"]/i', $content, $versionMatch) === 1 ? trim($versionMatch[1]) : null;
        return [$dependencies, $version];
    }

    /** @param array<string,WordPressThemeAsset> $assets @param list<array{code:string,severity:string,message:string,path?:string}> $issues @return array<string,WordPressThemeAsset> */
    private function attachCssReferences(string $root, array $assets, array &$issues): array
    {
        $byPath = $assets;
        foreach ($assets as $key => $asset) {
            if ($asset->kind !== WordPressAssetKind::Css) continue;
            $dependencies = $asset->dependencies;
            foreach ($asset->references as $reference) {
                $candidate = strtolower($reference);
                foreach ($byPath as $refKey => $refAsset) {
                    if (strtolower($refAsset->path) === $candidate && in_array($refAsset->kind, [WordPressAssetKind::Font, WordPressAssetKind::Css], true)) $dependencies[] = $refKey;
                }
            }
            $assets[$key] = new WordPressThemeAsset($asset->key, $asset->path, $asset->kind, $asset->size, $asset->sha256, $asset->direction, $asset->version, array_values(array_unique($dependencies)), $asset->references);
        }
        return $assets;
    }

    /** @param array<string,WordPressThemeAsset> $assets @param list<array{code:string,severity:string,message:string,path?:string}> $issues @return array{0:array<string,WordPressThemeAsset>,1:list<string>} */
    private function resolveDependencies(array $assets, array &$issues): array
    {
        $aliases = [];
        foreach ($assets as $key => $asset) {
            $aliases[$key] = $key;
            $aliases[strtolower(pathinfo($asset->path, PATHINFO_FILENAME))][] = $key;
        }
        foreach ($assets as $key => $asset) {
            $resolved = [];
            foreach ($asset->dependencies as $dependency) {
                $alias = strtolower(trim($dependency));
                $match = $aliases[$alias] ?? null;
                if (is_array($match)) $match = count($match) === 1 ? $match[0] : null;
                if ($match === null && isset($aliases[$alias])) $match = $aliases[$alias];
                if (is_string($match) && isset($assets[$match])) $resolved[] = $match;
                else $issues[] = ['code' => 'assets.dependency_unresolved', 'severity' => 'warning', 'message' => 'Asset dependency is external to the inspected theme and remains deferred.', 'path' => $asset->path];
            }
            $assets[$key] = new WordPressThemeAsset($asset->key, $asset->path, $asset->kind, $asset->size, $asset->sha256, $asset->direction, $asset->version, array_values(array_unique($resolved)), $asset->references);
        }

        $ordered = [];
        $visiting = [];
        $visited = [];
        $visit = function (string $key) use (&$visit, &$ordered, &$visiting, &$visited, $assets, &$issues): void {
            if (isset($visited[$key])) return;
            if (isset($visiting[$key])) {
                $issues[] = ['code' => 'assets.dependency_cycle', 'severity' => 'blocker', 'message' => 'Asset dependency cycle detected.', 'path' => $key];
                return;
            }
            $visiting[$key] = true;
            foreach ($assets[$key]->dependencies as $dependency) if (isset($assets[$dependency])) $visit($dependency);
            unset($visiting[$key]);
            $visited[$key] = true;
            $ordered[] = $key;
        };
        $keys = array_keys($assets);
        usort($keys, fn (string $left, string $right): int => [$this->priority($assets[$left]->kind), $left] <=> [$this->priority($assets[$right]->kind), $right]);
        foreach ($keys as $key) $visit($key);
        return [$assets, array_values(array_unique($ordered))];
    }

    /** @param list<array{code:string,severity:string,message:string,path?:string}> $issues @return list<string> */
    private function references(string $root, string $file, string $content, array &$issues): array
    {
        $references = [];
        preg_match_all('/url\(\s*([\'\"]?)([^\'\"\)\s]+)\1\s*\)/i', $content, $matches);
        foreach ($matches[2] ?? [] as $raw) {
            $value = trim((string)$raw);
            if ($value === '' || str_starts_with($value, '#')) continue;
            if (preg_match('/^(?:data:|https?:|javascript:)/i', $value) === 1) {
                $issues[] = ['code' => 'assets.external_reference_deferred', 'severity' => 'warning', 'message' => 'External or embedded CSS asset reference was not fetched.', 'path' => $this->relative($root, $file)];
                continue;
            }
            $pathOnly = preg_split('/[?#]/', $value, 2)[0] ?? $value;
            if ($pathOnly === '') continue;
            if (str_contains($value, '\\') || str_starts_with($value, '/')) {
                $issues[] = ['code' => 'assets.reference_invalid', 'severity' => 'blocker', 'message' => 'CSS asset reference is not a safe relative path.', 'path' => $this->relative($root, $file)];
                continue;
            }
            $resolved = realpath(dirname($file) . DIRECTORY_SEPARATOR . $pathOnly);
            if ($resolved === false || !$this->inside($root, $resolved)) {
                $issues[] = ['code' => 'assets.reference_escape', 'severity' => 'blocker', 'message' => 'CSS asset reference escapes the theme root.', 'path' => $this->relative($root, $file)];
                continue;
            }
            $references[] = strtolower($this->relative($root, $resolved));
        }
        return array_values(array_unique($references));
    }

    private function direction(string $path, string $content): string
    {
        $name = strtolower(pathinfo($path, PATHINFO_FILENAME));
        if ($name === 'rtl' || str_ends_with($name, '.rtl')) return 'rtl';
        if (preg_match('/(?:^|\R)\s*\/\*\s*rtl\s*:\s*(?:ignore|remove)\b/i', $content) === 1) return 'ltr';
        return 'both';
    }

    private function kind(string $extension): ?WordPressAssetKind
    {
        return in_array($extension, self::CSS_EXTENSIONS, true) ? WordPressAssetKind::Css
            : (in_array($extension, self::JS_EXTENSIONS, true) ? WordPressAssetKind::JavaScript
            : (in_array($extension, self::FONT_EXTENSIONS, true) ? WordPressAssetKind::Font : null));
    }

    private function isAssetExtension(string $extension): bool
    {
        return $this->kind($extension) !== null;
    }

    private function read(string $file): string
    {
        $size = filesize($file);
        if (!is_int($size) || $size < 0 || $size > $this->maxFileBytes) throw new RuntimeException('Theme asset exceeds the per-file byte limit.');
        $content = file_get_contents($file);
        if (!is_string($content)) throw new RuntimeException('Theme asset could not be read.');
        return $content;
    }

    private function priority(WordPressAssetKind $kind): int
    {
        return match ($kind) { WordPressAssetKind::Font => 0, WordPressAssetKind::Css => 1, WordPressAssetKind::JavaScript => 2 };
    }

    private function ignored(string $relative): bool
    {
        return count(array_intersect(explode('/', $relative), self::IGNORED_DIRECTORIES)) > 0;
    }

    private function safeRelative(string $relative): bool
    {
        return strlen($relative) <= 255
            && !str_contains($relative, "\0")
            && !str_contains($relative, '\\')
            && !in_array('..', explode('/', $relative), true)
            && preg_match('/^[A-Za-z0-9][A-Za-z0-9._\/-]{0,254}$/', $relative) === 1;
    }

    private function inside(string $root, string $file): bool
    {
        $root = rtrim($this->normalize(realpath($root) ?: $root), '/');
        return str_starts_with($this->normalize($file), $root . '/');
    }

    private function relative(string $root, string $file): string
    {
        $root = rtrim($this->normalize($root), '/');
        $file = $this->normalize($file);
        return str_starts_with($file, $root . '/') ? substr($file, strlen($root) + 1) : $file;
    }

    private function normalize(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}

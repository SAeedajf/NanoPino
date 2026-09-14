<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\WordPress;

use App\com_pinoox_cms\Cms\Block\Document\BlockDocumentValidator;
use App\com_pinoox_cms\Cms\Theme\Pattern\ThemePattern;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Converts the static part of a WordPress theme into NanoPino documents.
 * PHP is read as text for block comments and is never included or executed.
 */
final class WordPressThemeStructureConverter
{
    private const MAX_FILES = 500;
    private const MAX_BYTES = 2_097_152;

    public function __construct(
        private readonly WordPressBlockMarkupParser $parser = new WordPressBlockMarkupParser(),
        private readonly ?BlockDocumentValidator $validator = null,
        private readonly int $maxFiles = self::MAX_FILES,
        private readonly int $maxBytes = self::MAX_BYTES,
    ) {
        if ($this->maxFiles < 1 || $this->maxBytes < 1) {
            throw new InvalidArgumentException('WordPress structure limits must be positive.');
        }
    }

    public function convert(string $themeRoot): WordPressThemeStructureReport
    {
        $root = realpath($themeRoot);
        if ($root === false || !is_dir($root)) {
            return new WordPressThemeStructureReport($themeRoot, issues: [[
                'code' => 'structure.theme_root_invalid',
                'severity' => 'blocker',
                'message' => 'WordPress theme root does not exist or is not a directory.',
            ]]);
        }

        $issues = [];
        $templates = $this->convertFiles($root, 'templates', ['html', 'htm'], 'template', $issues);
        $parts = $this->convertFiles($root, 'parts', ['html', 'htm'], 'part', $issues);
        $patterns = $this->convertPatterns($root, $issues);

        return new WordPressThemeStructureReport($root, $templates, $parts, $patterns, $issues);
    }

    /** @param list<string> $extensions @param list<array{code:string,severity:string,message:string,path?:string}> $issues @return list<WordPressConvertedTemplate> */
    private function convertFiles(string $root, string $directory, array $extensions, string $kind, array &$issues): array
    {
        $files = $this->files($root, $directory, $extensions, $issues);
        $result = [];
        foreach ($files as $file) {
            $logical = strtolower(pathinfo($file, PATHINFO_FILENAME));
            $relative = $this->relative($root, $file);
            try {
                $document = $this->parseFile($file, $kind, $relative, $issues);
                $result[] = new WordPressConvertedTemplate($kind, $logical, $relative, $document, []);
            } catch (Throwable $error) {
                $issues[] = [
                    'code' => 'structure.file_conversion_failed',
                    'severity' => 'blocker',
                    'message' => $error->getMessage(),
                    'path' => $relative,
                ];
            }
        }
        return $result;
    }

    /** @param list<array{code:string,severity:string,message:string,path?:string}> $issues @return list<ThemePattern> */
    private function convertPatterns(string $root, array &$issues): array
    {
        $files = $this->files($root, 'patterns', ['html', 'htm', 'php'], $issues);
        $result = [];
        $ids = [];
        foreach ($files as $file) {
            $relative = $this->relative($root, $file);
            try {
                $content = $this->read($file);
                $metadata = $this->patternMetadata($content);
                $parsed = $this->parser->parse($content);
                $this->validator?->validate($parsed->document);
                foreach ($parsed->issues as $issue) $issues[] = $this->withPath($issue, $relative);
                if (str_contains($content, '<?php') || str_contains($content, '?>')) {
                    $issues[] = [
                        'code' => 'structure.php_runtime_deferred',
                        'severity' => 'warning',
                        'message' => 'PHP in pattern source was not executed; only static block markup was converted.',
                        'path' => $relative,
                    ];
                }
                $id = $metadata['slug'] ?? strtolower(pathinfo($file, PATHINFO_FILENAME));
                if (preg_match('/^[a-z0-9][a-z0-9._\/-]{0,127}$/', $id) !== 1 || str_contains($id, '..')) {
                    throw new RuntimeException('Invalid WordPress pattern slug.');
                }
                if (isset($ids[$id])) {
                    throw new RuntimeException('Duplicate WordPress pattern slug: ' . $id . '.');
                }
                $ids[$id] = true;
                $result[] = new ThemePattern(
                    $id,
                    $metadata['title'] ?? $id,
                    $metadata['categories'] ?? [],
                    $parsed->document->toArray(),
                    $root,
                );
            } catch (Throwable $error) {
                $issues[] = [
                    'code' => 'structure.pattern_conversion_failed',
                    'severity' => 'blocker',
                    'message' => $error->getMessage(),
                    'path' => $relative,
                ];
            }
        }
        return $result;
    }

    /** @param list<string> $extensions @param list<array{code:string,severity:string,message:string,path?:string}> $issues @return list<string> */
    private function files(string $root, string $directory, array $extensions, array &$issues): array
    {
        $path = $root . DIRECTORY_SEPARATOR . $directory;
        if (!is_dir($path)) return [];
        $entries = scandir($path) ?: [];
        $files = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || is_link($path . DIRECTORY_SEPARATOR . $entry)) continue;
            $extension = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if (!in_array($extension, $extensions, true)) continue;
            $file = realpath($path . DIRECTORY_SEPARATOR . $entry);
            if ($file === false || !is_file($file) || !$this->inside($root, $file)) continue;
            $name = pathinfo($entry, PATHINFO_FILENAME);
            if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,190}$/', $name) !== 1 || str_contains($name, '..')) {
                $issues[] = ['code' => 'structure.file_name_invalid', 'severity' => 'blocker', 'message' => 'Theme structure filename is unsafe.', 'path' => $this->relative($root, $file)];
                continue;
            }
            $files[] = $file;
        }
        sort($files, SORT_STRING);
        if (count($files) > $this->maxFiles) {
            $issues[] = ['code' => 'structure.file_count_exceeded', 'severity' => 'blocker', 'message' => 'WordPress theme structure exceeds the file limit.', 'path' => $directory];
            return array_slice($files, 0, $this->maxFiles);
        }
        return $files;
    }

    private function parseFile(string $file, string $kind, string $relative, array &$issues): \App\com_pinoox_cms\Cms\Block\Document\BlockDocument
    {
        $content = $this->read($file);
        if ($kind === 'part' && (str_contains($content, '<?php') || str_contains($content, '?>'))) {
            $issues[] = ['code' => 'structure.php_runtime_deferred', 'severity' => 'warning', 'message' => 'PHP in template part source was not executed; only static block markup was converted.', 'path' => $relative];
        }
        $parsed = $this->parser->parse($content);
        $this->validator?->validate($parsed->document);
        foreach ($parsed->issues as $issue) $issues[] = $this->withPath($issue, $relative);
        return $parsed->document;
    }

    /** @return array{title?:string,slug?:string,categories?:list<string>} */
    private function patternMetadata(string $content): array
    {
        $metadata = [];
        foreach (['title' => 'Title', 'slug' => 'Slug'] as $key => $label) {
            if (preg_match('/(?:^|\R)\s*\*?\s*' . $label . ':\s*([^\r\n*]+)/i', $content, $match) === 1) {
                $value = trim((string)$match[1]);
                if ($value !== '') $metadata[$key] = $value;
            }
        }
        if (preg_match('/(?:^|\R)\s*\*?\s*Categories:\s*([^\r\n*]+)/i', $content, $match) === 1) {
            $metadata['categories'] = array_values(array_filter(array_map(static fn (string $item): string => trim($item), explode(',', (string)$match[1]))));
        }
        if (isset($metadata['slug'])) $metadata['slug'] = strtolower(trim((string)$metadata['slug']));
        return $metadata;
    }

    private function read(string $file): string
    {
        $size = filesize($file);
        if (!is_int($size) || $size < 0 || $size > $this->maxBytes) throw new RuntimeException('Theme structure file exceeds the byte limit.');
        $content = file_get_contents($file);
        if (!is_string($content)) throw new RuntimeException('Theme structure file could not be read.');
        return $content;
    }

    private function inside(string $root, string $file): bool
    {
        $root = rtrim(str_replace('\\', '/', realpath($root) ?: $root), '/');
        $file = str_replace('\\', '/', $file);
        return str_starts_with($file, $root . '/');
    }

    private function relative(string $root, string $file): string
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $file = str_replace('\\', '/', $file);
        return str_starts_with($file, $root . '/') ? substr($file, strlen($root) + 1) : $file;
    }

    /** @param array<string,mixed> $issue @return array<string,mixed> */
    private function withPath(array $issue, string $path): array
    {
        $issue['path'] = $path;
        return $issue;
    }
}

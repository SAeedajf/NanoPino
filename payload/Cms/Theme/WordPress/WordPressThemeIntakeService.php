<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\WordPress;

use App\com_pinoox_cms\Cms\Installer\CanonicalPackagePath;
use InvalidArgumentException;
use ZipArchive;

final class WordPressThemeIntakeService
{
    private const MAX_FILES = 2000;
    private const MAX_TOTAL_BYTES = 20_000_000;
    private const MAX_FILE_BYTES = 2_000_000;

    public function __construct(
        private readonly WordPressThemeScanner $scanner = new WordPressThemeScanner(),
        private readonly WordPressThemeProvenance $provenance = new WordPressThemeProvenance(),
    ) {}

    /** @param array<string,mixed>|null $signedProvenance @param array<string,string> $trustedKeys */
    public function inspectDirectory(
        string $themeRoot,
        ?array $signedProvenance = null,
        array $trustedKeys = [],
    ): WordPressThemeIntakeReport {
        $scan = $this->scanner->scan($themeRoot);
        $root = $scan->root;
        $files = [];
        $issues = $scan->issues;
        foreach ($scan->files as $relative) {
            $path = $root . '/' . $relative;
            if (!is_file($path) || !is_readable($path)) {
                $issues[] = $this->issue('intake.file_unreadable', 'blocker', 'A scanned theme file could not be read.', $relative);
                continue;
            }
            $size = filesize($path);
            $sha256 = hash_file('sha256', $path);
            if (!is_int($size) || !is_string($sha256)) {
                $issues[] = $this->issue('intake.file_hash_failed', 'blocker', 'A theme file could not be hashed.', $relative);
                continue;
            }
            $files[] = ['path' => $relative, 'size' => $size, 'sha256' => $sha256];
        }
        $manifestSha256 = $this->manifestDigest($files);
        $issues = array_merge($issues, $this->licenseIssues($scan->metadata));
        $provenance = $this->verifyProvenance($signedProvenance, $manifestSha256, null, null, $trustedKeys, $issues);

        return new WordPressThemeIntakeReport(
            'directory',
            $root,
            '',
            $files,
            $scan->metadata,
            $manifestSha256,
            null,
            $issues,
            $provenance,
            $scan->toArray(),
        );
    }

    /** @param array<string,mixed>|null $signedProvenance @param array<string,string> $trustedKeys */
    public function inspectArchive(
        string $archivePath,
        ?array $signedProvenance = null,
        array $trustedKeys = [],
    ): WordPressThemeIntakeReport {
        $path = realpath($archivePath);
        if ($path === false || !is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException('WordPress theme archive must be a readable file.');
        }
        $archiveSha256 = hash_file('sha256', $path);
        if (!is_string($archiveSha256)) throw new InvalidArgumentException('WordPress theme archive could not be hashed.');

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) {
            throw new InvalidArgumentException('WordPress theme archive is not a valid ZIP.');
        }

        $entries = [];
        $issues = [];
        $totalBytes = 0;
        $caseFold = [];
        try {
            if ($zip->numFiles < 1 || $zip->numFiles > self::MAX_FILES) {
                $issues[] = $this->issue('archive.file_count_exceeded', 'blocker', 'Theme archive file count is outside the safety limit.');
            }
            for ($index = 0; $index < $zip->numFiles && $index < self::MAX_FILES; $index++) {
                $stat = $zip->statIndex($index, ZipArchive::FL_UNCHANGED);
                if (!is_array($stat)) {
                    $issues[] = $this->issue('archive.entry_stat_failed', 'blocker', 'Theme archive entry metadata could not be read.');
                    continue;
                }
                $rawName = (string)($stat['name'] ?? '');
                $isDirectory = str_ends_with($rawName, '/');
                try {
                    $name = CanonicalPackagePath::normalize($rawName);
                } catch (\Throwable $error) {
                    $issues[] = $this->issue('archive.path_invalid', 'blocker', $error->getMessage(), $rawName);
                    continue;
                }
                $fold = strtolower($name);
                if (isset($caseFold[$fold])) {
                    $issues[] = $this->issue('archive.path_collision', 'blocker', 'Archive contains a case-insensitive path collision.', $name);
                    continue;
                }
                $caseFold[$fold] = $name;

                if ($this->isSymlink($zip, $index)) {
                    $issues[] = $this->issue('archive.symlink_forbidden', 'blocker', 'Symbolic links are not accepted in WordPress theme archives.', $name);
                    continue;
                }
                $size = $isDirectory ? 0 : (int)($stat['size'] ?? -1);
                $compressed = $isDirectory ? 0 : (int)($stat['comp_size'] ?? -1);
                if ($size < 0 || $compressed < 0 || $size > self::MAX_FILE_BYTES) {
                    $issues[] = $this->issue('archive.file_too_large', 'blocker', 'Theme archive entry exceeds the safety limit.', $name);
                    continue;
                }
                $totalBytes += $size;
                if ($totalBytes > self::MAX_TOTAL_BYTES) {
                    $issues[] = $this->issue('archive.total_size_exceeded', 'blocker', 'Theme archive exceeds the total uncompressed size limit.');
                    break;
                }
                if (!$isDirectory) {
                    // FL_UNCHANGED is a stat flag in this extension; passing it to
                    // getFromIndex is interpreted as a read length on some PHP builds.
                    // Content reads intentionally use the default full-entry mode.
                    $contents = $zip->getFromIndex($index);
                    if (!is_string($contents) || strlen($contents) !== $size) {
                        $issues[] = $this->issue('archive.entry_read_failed', 'blocker', 'Theme archive entry could not be read safely.', $name);
                        continue;
                    }
                    $entries[] = ['path' => $name, 'size' => $size, 'sha256' => hash('sha256', $contents)];
                }
            }

            $styleCandidates = array_values(array_filter($entries, static fn (array $entry): bool => basename($entry['path']) === 'style.css'));
            $themeRoot = null;
            $metadata = [];
            if (count($styleCandidates) === 1) {
                $stylePath = $styleCandidates[0]['path'];
                $themeRoot = dirname($stylePath);
                if ($themeRoot === '.') $themeRoot = '';
                $style = $zip->getFromName($stylePath);
                if (is_string($style)) $metadata = $this->styleMetadata($style);
            } elseif ($styleCandidates === []) {
                $issues[] = $this->issue('theme.style_css_missing', 'warning', 'Archive does not contain a unique style.css entry.');
            } else {
                $issues[] = $this->issue('theme.style_css_ambiguous', 'blocker', 'Archive contains more than one style.css entry.');
            }

            if ($themeRoot !== null) {
                $prefix = $themeRoot === '' ? '' : $themeRoot . '/';
                $inside = array_filter($entries, static fn (array $entry): bool => $prefix === '' || str_starts_with($entry['path'], $prefix));
                if ($inside === []) $issues[] = $this->issue('theme.root_empty', 'blocker', 'Resolved WordPress theme root is empty.');
            }
            $issues = array_merge($issues, $this->licenseIssues($metadata));
            $manifestSha256 = $this->manifestDigest($entries);
            $provenance = $this->verifyProvenance($signedProvenance, $manifestSha256, $archiveSha256, $themeRoot, $trustedKeys, $issues);

            return new WordPressThemeIntakeReport(
                'zip',
                $path,
                $themeRoot,
                $entries,
                $metadata,
                $manifestSha256,
                $archiveSha256,
                $issues,
                $provenance,
                null,
            );
        } finally {
            $zip->close();
        }
    }

    /** @param list<array{path:string,size:int,sha256:string}> $files */
    private function manifestDigest(array $files): string
    {
        usort($files, static fn (array $a, array $b): int => strcmp($a['path'], $b['path']));
        return hash('sha256', json_encode($files, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /** @param array<string,mixed>|null $signed @param array<string,string> $trusted @param list<array{code:string,severity:string,message:string,path?:string}> $issues @return array<string,mixed>|null */
    private function verifyProvenance(?array $signed, string $manifest, ?string $source, ?string $themeRoot, array $trusted, array &$issues): ?array
    {
        $result = $this->provenance->verify($signed ?? [], $manifest, $source, $themeRoot, $trusted);
        if (!$result['present']) {
            $issues[] = $this->issue('provenance.missing', 'warning', 'No signed provenance was supplied; source identity is not publisher-verified.');
        } elseif (!$result['verified']) {
            $issues[] = $this->issue('provenance.unverified', 'blocker', 'Signed provenance could not be verified against the configured trust store.');
        }
        return $result;
    }

    /** @param array<string,mixed> $metadata @return list<array{code:string,severity:string,message:string,path?:string}> */
    private function licenseIssues(array $metadata): array
    {
        $license = strtolower(trim((string)($metadata['license'] ?? '')));
        if ($license === '') return [$this->issue('license.missing', 'warning', 'Theme license metadata is missing; legal compatibility requires manual review.')];
        if (preg_match('/\b(?:gpl|mit|apache|bsd|isc)\b/i', $license) === 1) return [];
        if (preg_match('/\b(?:proprietary|all rights reserved|no license)\b/i', $license) === 1) {
            return [$this->issue('license.incompatible', 'blocker', 'Theme license metadata is not accepted for conversion without legal approval.')];
        }
        return [$this->issue('license.unverified', 'warning', 'Theme license metadata is not recognized; manual compatibility review is required.')];
    }

    /** @return array<string,string> */
    private function styleMetadata(string $style): array
    {
        $metadata = [];
        $style = preg_replace('/^\s*\/\*+/', '', $style) ?? $style;
        $style = preg_replace('/\*\/\s*$/', '', $style) ?? $style;
        foreach (['Theme Name' => 'name', 'Version' => 'version', 'Text Domain' => 'text_domain', 'License' => 'license', 'License URI' => 'license_uri', 'Template' => 'parent', 'Requires at least' => 'requires_at_least', 'Requires PHP' => 'requires_php'] as $header => $key) {
            if (preg_match('/^\s*' . preg_quote($header, '/') . '\s*:\s*(.+)$/mi', $style, $match) === 1) $metadata[$key] = trim($match[1]);
        }
        return $metadata;
    }

    private function isSymlink(ZipArchive $zip, int $index): bool
    {
        $opsys = 0;
        $attributes = 0;
        if (!defined('ZipArchive::FL_UNCHANGED') || !$zip->getExternalAttributesIndex($index, $opsys, $attributes, ZipArchive::FL_UNCHANGED)) return false;
        if (!in_array($opsys, [3, 19], true)) return false;
        return ((($attributes >> 16) & 0xF000) === 0xA000);
    }

    /** @return array{code:string,severity:string,message:string,path?:string} */
    private function issue(string $code, string $severity, string $message, ?string $path = null): array
    {
        $issue = ['code' => $code, 'severity' => $severity, 'message' => $message];
        if ($path !== null && $path !== '') $issue['path'] = $path;
        return $issue;
    }
}

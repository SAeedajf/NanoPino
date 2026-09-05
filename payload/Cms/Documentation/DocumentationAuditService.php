<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Documentation;

use App\com_pinoox_cms\Cms\Support\CmsRelease;

final class DocumentationAuditService
{
    private const MANIFEST_PATH = 'resources/docs/documentation-manifest-v1.json';

    public function audit(string $packageRoot): DocumentationAuditReport
    {
        $root = realpath($packageRoot);
        if ($root === false || !is_dir($root)) {
            return new DocumentationAuditReport(
                [new DocumentationAuditIssue('docs.root_missing', 'Package root does not exist.', $packageRoot)],
                0,
                0,
                0,
                0,
                0,
            );
        }

        $manifestFile = $root . '/' . self::MANIFEST_PATH;
        if (!is_file($manifestFile) || is_link($manifestFile)) {
            return new DocumentationAuditReport(
                [new DocumentationAuditIssue('docs.manifest_missing', 'Documentation manifest is missing.', self::MANIFEST_PATH)],
                0,
                0,
                0,
                0,
                0,
            );
        }

        try {
            $manifestRaw = (string) file_get_contents($manifestFile);
            $manifest = json_decode($manifestRaw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return new DocumentationAuditReport(
                [new DocumentationAuditIssue('docs.manifest_invalid', 'Documentation manifest JSON is invalid.', self::MANIFEST_PATH)],
                0,
                0,
                0,
                0,
                0,
            );
        }

        if (!is_array($manifest)) {
            return new DocumentationAuditReport(
                [new DocumentationAuditIssue('docs.manifest_invalid', 'Documentation manifest JSON is invalid.', self::MANIFEST_PATH)],
                0,
                0,
                0,
                0,
                0,
            );
        }

        $issues = [];
        if (($manifest['schema'] ?? null) !== 1) {
            $issues[] = new DocumentationAuditIssue('docs.manifest_schema', 'Documentation manifest schema must be 1.', self::MANIFEST_PATH);
        }
        if (($manifest['freeze_state'] ?? null) !== 'candidate' && ($manifest['freeze_state'] ?? null) !== 'frozen') {
            $issues[] = new DocumentationAuditIssue('docs.freeze_state', 'Documentation freeze state is invalid.', self::MANIFEST_PATH);
        }

        $required = is_array($manifest['required_docs'] ?? null) ? $manifest['required_docs'] : [];
        $contracts = is_array($manifest['machine_contracts'] ?? null) ? $manifest['machine_contracts'] : [];
        $declaredBlockers = $this->stringList($manifest['release_blockers'] ?? []);

        $sourceMode = is_dir($root . '/docs');
        $mode = $sourceMode ? 'source' : 'artifact';
        $evidenceStatus = $sourceMode ? 'not-required' : 'missing';
        $adrCount = 0;
        $markdownFiles = 0;
        $internalLinks = 0;

        if ($sourceMode) {
            foreach ($required as $path) {
                if (!is_string($path) || !$this->safeRelative($path)) {
                    $issues[] = new DocumentationAuditIssue(
                        'docs.required_path_invalid',
                        'Required documentation path is invalid.',
                        is_string($path) ? $path : null,
                    );
                    continue;
                }
                if (!is_file($root . '/' . $path)) {
                    $issues[] = new DocumentationAuditIssue('docs.required_missing', 'Required documentation file is missing.', $path);
                }
            }

            [$adrCount, $adrIssues] = $this->auditAdrs($root . '/docs/adr');
            array_push($issues, ...$adrIssues);

            [$markdownFiles, $internalLinks, $linkIssues] = $this->auditMarkdownLinks($root, $root . '/docs');
            array_push($issues, ...$linkIssues);

            foreach ([
                'README.md' => CmsRelease::version(),
                'PROJECT-STATUS.md' => 'Documentation Freeze',
                'docs/index.md' => 'Freeze Candidate',
            ] as $path => $needle) {
                $content = is_file($root . '/' . $path) ? (string) file_get_contents($root . '/' . $path) : '';
                if (!str_contains($content, $needle)) {
                    $issues[] = new DocumentationAuditIssue(
                        'docs.current_reference_stale',
                        'Current reference does not contain expected freeze marker.',
                        $path,
                    );
                }
            }
        } else {
            [$artifactIssues, $evidenceStatus, $metrics] = $this->auditArtifactEvidence(
                $root,
                $manifest,
                $manifestRaw,
                count($required),
                count($contracts),
            );
            array_push($issues, ...$artifactIssues);
            $adrCount = $metrics['adr_count'];
            $markdownFiles = $metrics['markdown_files'];
            $internalLinks = $metrics['internal_links'];
        }

        foreach ($contracts as $path) {
            if (!is_string($path) || !$this->safeRelative($path)) {
                $issues[] = new DocumentationAuditIssue(
                    'docs.contract_path_invalid',
                    'Machine contract path is invalid.',
                    is_string($path) ? $path : null,
                );
                continue;
            }
            $file = $root . '/' . $path;
            if (!is_file($file) || is_link($file)) {
                $issues[] = new DocumentationAuditIssue('docs.contract_missing', 'Machine contract is missing.', $path);
                continue;
            }
            try {
                $decoded = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($decoded)) {
                    throw new \RuntimeException('not object');
                }
            } catch (\Throwable) {
                $issues[] = new DocumentationAuditIssue('docs.contract_invalid_json', 'Machine contract JSON is invalid.', $path);
            }
        }

        return new DocumentationAuditReport(
            array_values($issues),
            count($required),
            count($contracts),
            $adrCount,
            $markdownFiles,
            $internalLinks,
            $mode,
            $evidenceStatus,
            $declaredBlockers,
        );
    }

    /**
     * @param array<string,mixed> $manifest
     * @return array{list<DocumentationAuditIssue>,string,array{adr_count:int,markdown_files:int,internal_links:int}}
     */
    private function auditArtifactEvidence(
        string $root,
        array $manifest,
        string $manifestRaw,
        int $requiredDocs,
        int $machineContracts,
    ): array {
        $issues = [];
        $metrics = ['adr_count' => 0, 'markdown_files' => 0, 'internal_links' => 0];
        $distribution = is_array($manifest['distribution'] ?? null) ? $manifest['distribution'] : [];

        if (($distribution['source_docs_packaged'] ?? null) !== false) {
            $issues[] = new DocumentationAuditIssue(
                'docs.artifact_policy_invalid',
                'Documentation artifact policy must explicitly declare that source docs are excluded.',
                self::MANIFEST_PATH,
            );
        }

        $evidencePath = $distribution['artifact_evidence'] ?? null;
        if (!is_string($evidencePath) || !$this->safeRelative($evidencePath)) {
            $issues[] = new DocumentationAuditIssue(
                'docs.artifact_evidence_path_invalid',
                'Documentation source-audit evidence path is invalid.',
                is_string($evidencePath) ? $evidencePath : null,
            );

            return [$issues, 'invalid', $metrics];
        }

        $file = $root . '/' . $evidencePath;
        if (!is_file($file) || is_link($file)) {
            $issues[] = new DocumentationAuditIssue(
                'docs.artifact_evidence_missing',
                'Source documentation audit evidence is missing from the packaged artifact.',
                $evidencePath,
            );

            return [$issues, 'missing', $metrics];
        }

        try {
            $raw = (string) file_get_contents($file);
            $evidence = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            $issues[] = new DocumentationAuditIssue(
                'docs.artifact_evidence_invalid',
                'Source documentation audit evidence JSON is invalid.',
                $evidencePath,
            );

            return [$issues, 'invalid', $metrics];
        }

        if (!is_array($evidence) || ($evidence['schema'] ?? null) !== 1) {
            $issues[] = new DocumentationAuditIssue(
                'docs.artifact_evidence_schema',
                'Source documentation audit evidence schema must be 1.',
                $evidencePath,
            );

            return [$issues, 'invalid', $metrics];
        }

        $status = is_string($evidence['status'] ?? null) ? $evidence['status'] : 'invalid';
        $metrics = [
            'adr_count' => max(0, (int) ($evidence['adr_count'] ?? 0)),
            'markdown_files' => max(0, (int) ($evidence['markdown_files'] ?? 0)),
            'internal_links' => max(0, (int) ($evidence['internal_links'] ?? 0)),
        ];

        if ($status !== 'passed') {
            $issues[] = new DocumentationAuditIssue(
                'docs.source_audit_not_passed',
                'The source documentation audit did not pass before this artifact was packaged.',
                $evidencePath,
            );
        }

        $expectedDigest = hash('sha256', $manifestRaw);
        if (!is_string($evidence['documentation_manifest_sha256'] ?? null)
            || !hash_equals($expectedDigest, $evidence['documentation_manifest_sha256'])) {
            $issues[] = new DocumentationAuditIssue(
                'docs.artifact_evidence_manifest_mismatch',
                'Documentation audit evidence does not match the packaged documentation manifest.',
                $evidencePath,
            );
        }

        if (($evidence['release_version'] ?? null) !== CmsRelease::version()
            || (int) ($evidence['release_version_code'] ?? -1) !== CmsRelease::versionCode()) {
            $issues[] = new DocumentationAuditIssue(
                'docs.artifact_evidence_release_mismatch',
                'Documentation audit evidence belongs to a different CMS release.',
                $evidencePath,
            );
        }

        if ((int) ($evidence['required_docs'] ?? -1) !== $requiredDocs
            || (int) ($evidence['machine_contracts'] ?? -1) !== $machineContracts) {
            $issues[] = new DocumentationAuditIssue(
                'docs.artifact_evidence_inventory_mismatch',
                'Documentation audit evidence inventory does not match the packaged manifest.',
                $evidencePath,
            );
        }

        return [$issues, $status, $metrics];
    }

    /** @return array{int,list<DocumentationAuditIssue>} */
    private function auditAdrs(string $directory): array
    {
        $issues = [];
        $seen = [];
        $count = 0;
        foreach (glob($directory . '/ADR-[0-9][0-9][0-9]-*.md') ?: [] as $file) {
            $name = basename($file);
            if (!preg_match('/^ADR-(\d{3})-/', $name, $matches)) {
                continue;
            }
            ++$count;
            $id = $matches[1];
            if (isset($seen[$id])) {
                $issues[] = new DocumentationAuditIssue('docs.adr_duplicate', 'Duplicate ADR identifier ADR-' . $id, 'docs/adr/' . $name);
            }
            $seen[$id] = true;
            $first = trim((string) strtok((string) file_get_contents($file), "\n"));
            if (!str_starts_with($first, '# ADR-' . $id . ' —')) {
                $issues[] = new DocumentationAuditIssue(
                    'docs.adr_heading_mismatch',
                    'ADR heading does not match its numeric identifier.',
                    'docs/adr/' . $name,
                );
            }
        }

        return [$count, $issues];
    }

    /** @return array{int,int,list<DocumentationAuditIssue>} */
    private function auditMarkdownLinks(string $root, string $docsDirectory): array
    {
        $issues = [];
        $files = 0;
        $links = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($docsDirectory, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'md') {
                continue;
            }
            ++$files;
            $path = $file->getPathname();
            $content = (string) file_get_contents($path);
            if (!preg_match_all('/\[[^\]]+\]\(([^)]+)\)/', $content, $matches)) {
                continue;
            }
            foreach ($matches[1] as $target) {
                $target = trim((string) $target);
                if ($target === '' || str_starts_with($target, '#') || preg_match('#^[a-z][a-z0-9+.-]*:#i', $target)) {
                    continue;
                }
                ++$links;
                $pathPart = urldecode(explode('#', $target, 2)[0]);
                if ($pathPart === '') {
                    continue;
                }
                if (!$this->safeRelative($pathPart)) {
                    $issues[] = new DocumentationAuditIssue(
                        'docs.link_unsafe',
                        'Documentation link is unsafe.',
                        $this->relative($root, $path) . ' -> ' . $target,
                    );
                    continue;
                }
                $resolved = realpath(dirname($path) . '/' . $pathPart);
                if ($resolved === false) {
                    $issues[] = new DocumentationAuditIssue(
                        'docs.link_missing',
                        'Documentation link target is missing.',
                        $this->relative($root, $path) . ' -> ' . $target,
                    );
                    continue;
                }
                $rootPrefix = rtrim(str_replace('\\', '/', $root), '/') . '/';
                if (!str_starts_with(str_replace('\\', '/', $resolved), $rootPrefix)) {
                    $issues[] = new DocumentationAuditIssue(
                        'docs.link_outside_root',
                        'Documentation link points outside package root.',
                        $this->relative($root, $path) . ' -> ' . $target,
                    );
                }
            }
        }

        return [$files, $links, $issues];
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                continue;
            }
            $item = trim($item);
            if ($item !== '') {
                $result[] = $item;
            }
        }

        return array_values(array_unique($result));
    }

    private function safeRelative(string $path): bool
    {
        if ($path === '' || str_contains($path, "\0")) {
            return false;
        }
        $normalized = str_replace('\\', '/', $path);
        if (str_starts_with($normalized, '/') || preg_match('/^[A-Za-z]:/', $normalized)) {
            return false;
        }

        return !in_array('..', explode('/', $normalized), true);
    }

    private function relative(string $root, string $path): string
    {
        $root = rtrim(str_replace('\\', '/', $root), '/') . '/';
        $path = str_replace('\\', '/', $path);

        return str_starts_with($path, $root) ? substr($path, strlen($root)) : $path;
    }
}

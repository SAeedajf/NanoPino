<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Release;

use App\com_pinoox_cms\Cms\Admin\Frontend\AdminFrontendAssetProbe;
use App\com_pinoox_cms\Cms\Admin\Frontend\AdminFrontendSourceFingerprint;
use App\com_pinoox_cms\Cms\Admin\Frontend\AdminFrontendRuntimeFingerprint;
use App\com_pinoox_cms\Cms\Documentation\DocumentationAuditService;
use App\com_pinoox_cms\Cms\Support\CmsRelease;

final class ProductionReleaseGate
{
    private const RELEASE_METADATA_PATH = 'resources/release/release-metadata-v1.json';

    public function inspect(string $packageRoot): ProductionReleaseGateReport
    {
        $root = realpath($packageRoot);
        if ($root === false || !is_dir($root)) {
            return new ProductionReleaseGateReport(false, [[
                'code' => 'release.package_root_missing',
                'message' => 'Package root does not exist.',
            ]], []);
        }

        $blockers = [];
        $frontend = (new AdminFrontendAssetProbe())->probe($root . '/theme/cms-admin');
        if (!$frontend->ready || $frontend->mode !== 'manifest') {
            $blockers[] = [
                'code' => 'release.admin_production_dist_required',
                'message' => 'Production release requires a verified Vite manifest build, not source-only or dev-server mode.',
            ];
        }

        $parity = [
            'ready' => false,
            'code' => 'release.admin_source_parity_unchecked',
            'currentFingerprint' => null,
            'buildFingerprint' => null,
            'sourceFiles' => null,
            'buildSourceFiles' => null,
        ];

        if ($frontend->ready && $frontend->mode === 'manifest') {
            $buildMetaFile = $root . '/theme/cms-admin/dist/.cms-build.json';

            try {
                $current = (new AdminFrontendSourceFingerprint())->calculate($root . '/theme/cms-admin');
                $parity['currentFingerprint'] = $current['fingerprint'];
                $parity['sourceFiles'] = $current['files'];

                if (!is_file($buildMetaFile) || is_link($buildMetaFile)) {
                    $parity['code'] = 'release.admin_source_parity_missing';
                    $blockers[] = [
                        'code' => 'release.admin_source_parity_missing',
                        'message' => 'Production Admin dist is missing source-parity metadata; rebuild with the release builder.',
                    ];
                } else {
                    $size = filesize($buildMetaFile);
                    if (!is_int($size) || $size < 2 || $size > 65536) {
                        throw new \RuntimeException('invalid build metadata size');
                    }

                    $build = json_decode((string) file_get_contents($buildMetaFile), true, 32, JSON_THROW_ON_ERROR);

                    if (
                        !is_array($build)
                        || ($build['schema'] ?? null) !== 1
                        || ($build['algorithm'] ?? null) !== 'sha256-path-filehash-v1'
                        || !is_string($build['sourceFingerprint'] ?? null)
                        || preg_match('/^[a-f0-9]{64}$/', $build['sourceFingerprint']) !== 1
                        || !is_int($build['sourceFiles'] ?? null)
                        || $build['sourceFiles'] < 1
                    ) {
                        throw new \RuntimeException('invalid build metadata schema');
                    }

                    $parity['buildFingerprint'] = $build['sourceFingerprint'];
                    $parity['buildSourceFiles'] = $build['sourceFiles'];

                    if (
                        !hash_equals($current['fingerprint'], $build['sourceFingerprint'])
                        || $current['files'] !== $build['sourceFiles']
                    ) {
                        $parity['code'] = 'release.admin_source_parity_mismatch';
                        $blockers[] = [
                            'code' => 'release.admin_source_parity_mismatch',
                            'message' => 'Production Admin dist was not built from the current Admin source tree.',
                        ];
                    } else {
                        $parity['ready'] = true;
                        $parity['code'] = 'release.admin_source_parity_ok';
                    }
                }
            } catch (\Throwable) {
                if ($parity['code'] === 'release.admin_source_parity_unchecked') {
                    $parity['code'] = 'release.admin_source_parity_invalid';
                    $blockers[] = [
                        'code' => 'release.admin_source_parity_invalid',
                        'message' => 'Production Admin source-parity metadata could not be verified.',
                    ];
                }
            }
        }

        [$runtimeReady, $runtimeEvidence] = $this->verifyAdminRuntime($root);
        if (!$runtimeReady) {
            $blockers[] = [
                'code' => (string) ($runtimeEvidence['code'] ?? 'release.admin_runtime_parity_invalid'),
                'message' => 'Directly-served Admin runtime modules do not match release evidence.',
            ];
        }

        $docs = (new DocumentationAuditService())->audit($root);
        if (!$docs->valid()) {
            $blockers[] = [
                'code' => 'release.documentation_invalid',
                'message' => 'Documentation audit must pass before production release.',
            ];
        }

        if ($docs->declaredReleaseBlockers !== []) {
            $blockers[] = [
                'code' => 'release.declared_blockers_present',
                'message' => sprintf(
                    '%d declared release blocker(s) remain unresolved.',
                    count($docs->declaredReleaseBlockers),
                ),
            ];
        }

        [$metadataReady, $metadataEvidence] = $this->verifyReleaseMetadata($root);
        if (!$metadataReady) {
            $blockers[] = [
                'code' => 'release.metadata_mismatch',
                'message' => 'Release metadata evidence does not match the Pinoox application metadata.',
            ];
        }

        return new ProductionReleaseGateReport(
            $blockers === [],
            $blockers,
            [
                'version' => CmsRelease::version(),
                'versionCode' => CmsRelease::versionCode(),
                'minimumKernelCode' => CmsRelease::minKernelCode(),
                'minimumPincore' => CmsRelease::minPincore(),
                'releaseMetadata' => $metadataEvidence,
                'frontend' => [
                    'ready' => $frontend->ready,
                    'mode' => $frontend->mode,
                    'code' => $frontend->code,
                    'buildId' => $frontend->buildId,
                    'assetCount' => count($frontend->assets),
                    'sourceParity' => $parity,
                    'runtimeParity' => $runtimeEvidence,
                ],
                'documentation' => [
                    'valid' => $docs->valid(),
                    'mode' => $docs->mode,
                    'evidenceStatus' => $docs->evidenceStatus,
                    'adrCount' => $docs->adrCount,
                    'requiredDocs' => $docs->requiredDocs,
                    'machineContracts' => $docs->machineContracts,
                    'declaredReleaseBlockers' => $docs->declaredReleaseBlockers,
                ],
            ],
        );
    }

    public function assertReady(string $packageRoot): void
    {
        $report = $this->inspect($packageRoot);
        if ($report->ready) {
            return;
        }

        $codes = array_map(
            static fn (array $blocker): string => $blocker['code'],
            $report->blockers,
        );

        throw new \RuntimeException('Production release gate blocked: ' . implode(', ', $codes));
    }


    /** @return array{bool,array<string,mixed>} */
    private function verifyAdminRuntime(string $root): array
    {
        $evidencePath = 'resources/release/admin-runtime-v1.json';
        $evidenceFile = $root . '/' . $evidencePath;
        $result = [
            'ready' => false,
            'code' => 'release.admin_runtime_parity_missing',
            'path' => $evidencePath,
            'currentFingerprint' => null,
            'evidenceFingerprint' => null,
            'runtimeFiles' => null,
            'evidenceRuntimeFiles' => null,
        ];

        try {
            $current = (new AdminFrontendRuntimeFingerprint())->calculate($root . '/theme/cms-admin');
            $result['currentFingerprint'] = $current['fingerprint'];
            $result['runtimeFiles'] = $current['files'];
        } catch (\Throwable) {
            $result['code'] = 'release.admin_runtime_parity_invalid';
            return [false, $result];
        }

        if (!is_file($evidenceFile) || is_link($evidenceFile)) {
            return [false, $result];
        }

        try {
            $size = filesize($evidenceFile);
            if (!is_int($size) || $size < 2 || $size > 65536) {
                throw new \RuntimeException('invalid Admin runtime evidence size');
            }
            $evidence = json_decode((string) file_get_contents($evidenceFile), true, 32, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            $result['code'] = 'release.admin_runtime_parity_invalid';
            return [false, $result];
        }

        if (
            !is_array($evidence)
            || ($evidence['schema'] ?? null) !== 1
            || ($evidence['algorithm'] ?? null) !== 'sha256-path-filehash-v1'
            || !is_string($evidence['runtimeFingerprint'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/', $evidence['runtimeFingerprint']) !== 1
            || !is_int($evidence['runtimeFiles'] ?? null)
            || $evidence['runtimeFiles'] < 1
        ) {
            $result['code'] = 'release.admin_runtime_parity_invalid';
            return [false, $result];
        }

        $result['evidenceFingerprint'] = $evidence['runtimeFingerprint'];
        $result['evidenceRuntimeFiles'] = $evidence['runtimeFiles'];
        $matches = hash_equals($current['fingerprint'], $evidence['runtimeFingerprint'])
            && $current['files'] === $evidence['runtimeFiles'];
        $result['ready'] = $matches;
        $result['code'] = $matches
            ? 'release.admin_runtime_parity_ok'
            : 'release.admin_runtime_parity_mismatch';

        return [$matches, $result];
    }

    /** @return array{bool,array<string,mixed>} */
    private function verifyReleaseMetadata(string $root): array
    {
        $appFile = $root . '/app.php';
        $evidenceFile = $root . '/' . self::RELEASE_METADATA_PATH;
        $result = [
            'ready' => false,
            'code' => 'release.metadata_evidence_missing',
            'path' => self::RELEASE_METADATA_PATH,
            'version' => CmsRelease::version(),
            'versionCode' => CmsRelease::versionCode(),
            'minimumKernelCode' => CmsRelease::minKernelCode(),
            'appSha256' => null,
            'evidenceAppSha256' => null,
        ];

        if (!is_file($appFile) || is_link($appFile)) {
            $result['code'] = 'release.app_metadata_missing';
            return [false, $result];
        }

        $appHash = hash_file('sha256', $appFile);
        if (!is_string($appHash)) {
            $result['code'] = 'release.app_metadata_unreadable';
            return [false, $result];
        }
        $result['appSha256'] = $appHash;

        if (!is_file($evidenceFile) || is_link($evidenceFile)) {
            return [false, $result];
        }

        try {
            $size = filesize($evidenceFile);
            if (!is_int($size) || $size < 2 || $size > 65536) {
                throw new \RuntimeException('invalid release metadata evidence size');
            }
            $evidence = json_decode((string) file_get_contents($evidenceFile), true, 32, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            $result['code'] = 'release.metadata_evidence_invalid';
            return [false, $result];
        }

        if (!is_array($evidence) || ($evidence['schema'] ?? null) !== 1 || ($evidence['source'] ?? null) !== 'app.php') {
            $result['code'] = 'release.metadata_evidence_schema';
            return [false, $result];
        }

        $result['evidenceAppSha256'] = $evidence['app_sha256'] ?? null;
        $matches =
            ($evidence['version_name'] ?? null) === CmsRelease::version()
            && (int) ($evidence['version_code'] ?? -1) === CmsRelease::versionCode()
            && (int) ($evidence['minpin'] ?? -1) === CmsRelease::minKernelCode()
            && is_string($evidence['app_sha256'] ?? null)
            && hash_equals($appHash, $evidence['app_sha256']);

        $result['ready'] = $matches;
        $result['code'] = $matches ? 'release.metadata_ok' : 'release.metadata_evidence_mismatch';

        return [$matches, $result];
    }
}

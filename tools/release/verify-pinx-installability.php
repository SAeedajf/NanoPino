<?php
declare(strict_types=1);

if ($argc < 2) {
    fwrite(STDERR, "Usage: php tools/release/verify-pinx-installability.php <pinx-file>\n");
    exit(64);
}

$root = dirname(__DIR__, 2);
$path = $argv[1];
$maxPinxBytes = 16 * 1024 * 1024;
$maxUncompressedBytes = 64 * 1024 * 1024;
$maxEntries = 5000;

$failures = [];
$fail = static function (string $message) use (&$failures): void {
    $failures[] = $message;
};

if (!extension_loaded('zip') || !class_exists(ZipArchive::class)) {
    fwrite(STDERR, "ERROR: ext-zip/ZipArchive is required to verify PINX artifacts.\n");
    exit(65);
}
if (!is_file($path) || !is_readable($path)) {
    fwrite(STDERR, "ERROR: PINX artifact is missing or unreadable: {$path}\n");
    exit(66);
}

$pinxBytes = filesize($path);
if (!is_int($pinxBytes) || $pinxBytes < 1) {
    $fail('PINX artifact is empty.');
} elseif ($pinxBytes > $maxPinxBytes) {
    $fail('PINX artifact exceeds 16 MiB release budget.');
}

$zip = new ZipArchive();
if ($zip->open($path) !== true) {
    fwrite(STDERR, "ERROR: PINX artifact is not a readable ZIP archive.\n");
    exit(67);
}

try {
    $entries = [];
    $totalUncompressed = 0;

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        if (!is_array($stat) || !is_string($stat['name'] ?? null)) {
            $fail('PINX contains an unreadable central-directory entry.');
            continue;
        }

        $name = $stat['name'];
        if (isset($entries[$name])) {
            $fail('Duplicate PINX entry: ' . $name);
        }
        $entries[$name] = true;

        if (
            $name === ''
            || str_starts_with($name, '/')
            || str_contains($name, '\\')
            || preg_match('#(^|/)\.\.(?:/|$)#', $name) === 1
            || str_contains($name, "\0")
        ) {
            $fail('Unsafe PINX entry path: ' . $name);
        }

        $size = (int) ($stat['size'] ?? 0);
        if ($size < 0) {
            $fail('Negative PINX entry size: ' . $name);
        }
        $totalUncompressed += max(0, $size);
    }

    if ($zip->numFiles > $maxEntries) {
        $fail('PINX entry count exceeds release budget.');
    }
    if ($totalUncompressed > $maxUncompressedBytes) {
        $fail('PINX uncompressed payload exceeds 64 MiB release budget.');
    }

    $sourceApp = require $root . '/payload/app.php';
    $sourceManifest = json_decode(
        (string) file_get_contents($root . '/manifest.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    $requiredEntries = [
        'manifest.json',
        'payload/app.php',
        'payload/lifecycle.php',
        'payload/database/migrations/2026_09_01_000000_preflight_nanopino_environment.php',
        'payload/theme/cms-admin/dist/.vite/manifest.json',
        'payload/theme/cms-admin/dist/.cms-build.json',
        'payload/resources/release/release-metadata-v1.json',
    ];

    foreach ($requiredEntries as $required) {
        if (!isset($entries[$required])) {
            $fail('Required PINX entry is missing: ' . $required);
            continue;
        }
        $contents = $zip->getFromName($required);
        if (!is_string($contents) || $contents === '') {
            $fail('Required PINX entry is empty/unreadable: ' . $required);
        }
    }

    $manifestJson = $zip->getFromName('manifest.json');
    if (!is_string($manifestJson) || $manifestJson === '') {
        $fail('PINX manifest.json cannot be read.');
        $packageManifest = [];
    } else {
        try {
            $packageManifest = json_decode($manifestJson, true, 128, JSON_THROW_ON_ERROR);
        } catch (Throwable $error) {
            $packageManifest = [];
            $fail('PINX manifest.json is invalid JSON: ' . $error::class);
        }
    }

    if (($packageManifest['format'] ?? null) !== 'pinx') $fail('PINX manifest format mismatch.');
    if ((int) ($packageManifest['format_version'] ?? 0) !== 1) $fail('PINX manifest format_version mismatch.');
    if (($packageManifest['type'] ?? null) !== 'app') $fail('PINX manifest type mismatch.');
    if (($packageManifest['package'] ?? null) !== ($sourceApp['package'] ?? null)) $fail('PINX package id mismatch.');
    if (($packageManifest['version_name'] ?? null) !== ($sourceApp['version-name'] ?? null)) $fail('PINX version_name mismatch.');
    if ((int) ($packageManifest['version_code'] ?? 0) !== (int) ($sourceApp['version-code'] ?? 0)) $fail('PINX version_code mismatch.');
    if ((int) ($packageManifest['minpin'] ?? 0) !== (int) ($sourceApp['minpin'] ?? 0)) $fail('PINX minpin mismatch.');

    if (($sourceManifest['package'] ?? null) !== ($sourceApp['package'] ?? null)) $fail('Source manifest package mismatch.');
    if (($sourceManifest['version_name'] ?? null) !== ($sourceApp['version-name'] ?? null)) $fail('Source manifest version mismatch.');
    if ((int) ($sourceManifest['version_code'] ?? 0) !== (int) ($sourceApp['version-code'] ?? 0)) $fail('Source manifest version code mismatch.');
    if ((int) ($sourceManifest['minpin'] ?? 0) !== (int) ($sourceApp['minpin'] ?? 0)) $fail('Source manifest minpin mismatch.');

    $payloadApp = $zip->getFromName('payload/app.php');
    $payloadLifecycle = $zip->getFromName('payload/lifecycle.php');

    if (is_string($payloadApp)) {
        $sourceAppBytes = (string) file_get_contents($root . '/payload/app.php');
        if (!hash_equals(hash('sha256', $sourceAppBytes), hash('sha256', $payloadApp))) {
            $fail('PINX payload/app.php differs from verified source app.php.');
        }
    }

    if (is_string($payloadLifecycle)) {
        $sourceLifecycleBytes = (string) file_get_contents($root . '/payload/lifecycle.php');
        if (!hash_equals(hash('sha256', $sourceLifecycleBytes), hash('sha256', $payloadLifecycle))) {
            $fail('PINX payload/lifecycle.php differs from verified source lifecycle.php.');
        }
    }

    $releaseMetaJson = $zip->getFromName('payload/resources/release/release-metadata-v1.json');
    if (is_string($releaseMetaJson) && is_string($payloadApp)) {
        try {
            $releaseMeta = json_decode($releaseMetaJson, true, 64, JSON_THROW_ON_ERROR);
            if (($releaseMeta['version_name'] ?? null) !== ($sourceApp['version-name'] ?? null)) {
                $fail('PINX release metadata version_name mismatch.');
            }
            if ((int) ($releaseMeta['version_code'] ?? 0) !== (int) ($sourceApp['version-code'] ?? 0)) {
                $fail('PINX release metadata version_code mismatch.');
            }
            if ((int) ($releaseMeta['minpin'] ?? 0) !== (int) ($sourceApp['minpin'] ?? 0)) {
                $fail('PINX release metadata minpin mismatch.');
            }
            if (!is_string($releaseMeta['app_sha256'] ?? null)
                || !hash_equals(hash('sha256', $payloadApp), $releaseMeta['app_sha256'])) {
                $fail('PINX release metadata app_sha256 mismatch.');
            }
        } catch (Throwable $error) {
            $fail('PINX release metadata JSON is invalid: ' . $error::class);
        }
    }

    $viteManifestJson = $zip->getFromName('payload/theme/cms-admin/dist/.vite/manifest.json');
    if (is_string($viteManifestJson)) {
        try {
            $viteManifest = json_decode($viteManifestJson, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($viteManifest) || $viteManifest === []) {
                $fail('Vite manifest is empty.');
            } else {
                $hasEntry = false;
                foreach ($viteManifest as $item) {
                    if (is_array($item) && !empty($item['isEntry'])) {
                        $hasEntry = true;
                        break;
                    }
                }
                if (!$hasEntry) {
                    $fail('Vite manifest contains no production entry.');
                }
            }
        } catch (Throwable $error) {
            $fail('Vite manifest JSON is invalid: ' . $error::class);
        }
    }

    $buildEvidenceJson = $zip->getFromName('payload/theme/cms-admin/dist/.cms-build.json');
    if (is_string($buildEvidenceJson)) {
        try {
            $buildEvidence = json_decode($buildEvidenceJson, true, 32, JSON_THROW_ON_ERROR);
            if (
                ($buildEvidence['schema'] ?? null) !== 1
                || ($buildEvidence['algorithm'] ?? null) !== 'sha256-path-filehash-v1'
                || preg_match('/^[a-f0-9]{64}$/', (string) ($buildEvidence['sourceFingerprint'] ?? '')) !== 1
                || (int) ($buildEvidence['sourceFiles'] ?? 0) < 1
            ) {
                $fail('Admin build evidence schema is invalid.');
            }
        } catch (Throwable $error) {
            $fail('Admin build evidence JSON is invalid: ' . $error::class);
        }
    }

    $migrationEntries = array_values(array_filter(
        array_keys($entries),
        static fn (string $name): bool =>
            str_starts_with($name, 'payload/database/migrations/') && str_ends_with($name, '.php'),
    ));
    sort($migrationEntries, SORT_STRING);
    $expectedFirstMigration = 'payload/database/migrations/2026_09_01_000000_preflight_nanopino_environment.php';
    if (($migrationEntries[0] ?? null) !== $expectedFirstMigration) {
        $fail('Installability preflight is not the first NanoPino migration.');
    }

    $forbiddenExact = [
        'payload/theme/cms-admin/package.json',
        'payload/theme/cms-admin/package-lock.json',
        'payload/theme/cms-admin/vite.config.js',
        'payload/theme/cms-admin/vite.config.mjs',
        'payload/theme/cms-admin/build-linux.sh',
        'payload/theme/cms-admin/build-windows.ps1',
        'payload/theme/cms-admin/run-tests.mjs',
        'payload/theme/cms-admin/verify-dist.mjs',
        'payload/theme/cms-admin/source-fingerprint.mjs',
        'payload/theme/cms-admin/runtime-fingerprint.mjs',
        'payload/theme/cms-admin/repair-existing-builder.sh',
        'payload/theme/cms-admin/README-FA.md',
    ];
    foreach ($forbiddenExact as $forbidden) {
        if (isset($entries[$forbidden])) {
            $fail('Development-only PINX entry must be excluded: ' . $forbidden);
        }
    }

    $forbiddenPrefixes = [
        'payload/theme/cms-admin/src/',
        'payload/theme/cms-admin/tests/',
        'payload/theme/cms-admin/node_modules/',
        'payload/tests/',
        'payload/docs/',
        'payload/.git/',
        'payload/.github/',
    ];
    foreach (array_keys($entries) as $name) {
        foreach ($forbiddenPrefixes as $prefix) {
            if (str_starts_with($name, $prefix)) {
                $fail('Development-only PINX tree must be excluded: ' . $prefix);
                break;
            }
        }
    }

    $sha256 = hash_file('sha256', $path);
    if (!is_string($sha256) || preg_match('/^[a-f0-9]{64}$/', $sha256) !== 1) {
        $fail('Could not compute PINX SHA-256.');
        $sha256 = 'unavailable';
    }

    if ($failures !== []) {
        foreach ($failures as $failure) {
            fwrite(STDERR, "ERROR: {$failure}\n");
        }
        exit(1);
    }

    printf(
        "pinx_installability=PASS package=%s version=%s code=%d minpin=%d entries=%d migrations=%d bytes=%d uncompressed_bytes=%d sha256=%s\n",
        (string) ($sourceApp['package'] ?? ''),
        (string) ($sourceApp['version-name'] ?? ''),
        (int) ($sourceApp['version-code'] ?? 0),
        (int) ($sourceApp['minpin'] ?? 0),
        $zip->numFiles,
        count($migrationEntries),
        (int) $pinxBytes,
        $totalUncompressed,
        $sha256,
    );
} finally {
    $zip->close();
}

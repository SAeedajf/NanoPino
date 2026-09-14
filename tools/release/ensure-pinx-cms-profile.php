<?php
declare(strict_types=1);

/**
 * Enrich the native PINX root manifest with the CMS extension profile.
 *
 * Pinoox's native builder intentionally projects app.php into manifest.json,
 * while NanoPino's extension inspector also needs the CMS profile. Keep this
 * bridge outside the runtime and run it on unsigned release artifacts only.
 */

if ($argc < 2) {
    fwrite(STDERR, "Usage: php tools/release/ensure-pinx-cms-profile.php <package.pinx> [source-manifest.json]\n");
    exit(2);
}

$packagePath = $argv[1];
$sourceManifestPath = $argv[2] ?? __DIR__ . '/../../apps/com_pinoox_cms/manifest.json';

if (!is_file($packagePath) || !is_file($sourceManifestPath)) {
    fwrite(STDERR, "Package or source manifest was not found.\n");
    exit(1);
}

$source = json_decode((string) file_get_contents($sourceManifestPath), true, 512, JSON_THROW_ON_ERROR);
$cms = $source['cms'] ?? null;
if (!is_array($cms)) {
    fwrite(STDERR, "Source manifest does not contain a CMS profile.\n");
    exit(1);
}

$zip = new ZipArchive();
if ($zip->open($packagePath) !== true) {
    fwrite(STDERR, "Unable to open PINX package.\n");
    exit(1);
}

try {
    if ($zip->locateName('signature.json') !== false) {
        throw new RuntimeException('Signed packages must be enriched before signing.');
    }

    $raw = $zip->getFromName('manifest.json');
    if (!is_string($raw)) {
        throw new RuntimeException('PINX root manifest.json is missing.');
    }

    $manifest = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($manifest) || ($manifest['package'] ?? null) !== ($source['package'] ?? null)) {
        throw new RuntimeException('Package identity does not match the CMS source manifest.');
    }

    $manifest['cms'] = $cms;
    $encoded = json_encode(
        $manifest,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
    ) . "\n";

    if (!$zip->deleteName('manifest.json') || !$zip->addFromString('manifest.json', $encoded) || !$zip->close()) {
        throw new RuntimeException('Unable to write the enriched PINX manifest.');
    }
} catch (Throwable $e) {
    $zip->close();
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

fwrite(STDOUT, "CMS profile added to " . $packagePath . "\n");

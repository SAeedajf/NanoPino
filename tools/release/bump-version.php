<?php
declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "Usage: php tools/release/bump-version.php <version-name> <version-code>\n");
    exit(64);
}

$root = dirname(__DIR__, 2);
$versionName = trim((string) $argv[1]);
$versionCode = filter_var($argv[2], FILTER_VALIDATE_INT);

if (!preg_match('/^\\d+\\.\\d+\\.\\d+(?:[-+][0-9A-Za-z.-]+)?$/', $versionName)) {
    fwrite(STDERR, "Invalid version name: {$versionName}\n");
    exit(65);
}
if ($versionCode === false || $versionCode < 1) {
    fwrite(STDERR, "Invalid version code.\n");
    exit(65);
}

$appFile = $root . '/payload/app.php';
$manifestFile = $root . '/manifest.json';
$metadataFile = $root . '/payload/resources/release/release-metadata-v1.json';

$appConfig = require $appFile;
$currentCode = (int) ($appConfig['version-code'] ?? 0);
if ($versionCode <= $currentCode) {
    fwrite(STDERR, "Version code must increase: current={$currentCode}, requested={$versionCode}\n");
    exit(66);
}

$app = (string) file_get_contents($appFile);
$app = preg_replace("/'version-name'\\s*=>\\s*'[^']+'/", "'version-name' => '" . addslashes($versionName) . "'", $app, 1, $nameCount);
$app = preg_replace("/'version-code'\\s*=>\\s*\\d+/", "'version-code' => {$versionCode}", $app, 1, $codeCount);
if ($nameCount !== 1 || $codeCount !== 1 || $app === null) {
    throw new RuntimeException('Could not update app version fields deterministically.');
}
file_put_contents($appFile, $app);

$generatedAt = gmdate('Y-m-d\\TH:i:s\\Z');
$appSha = hash_file('sha256', $appFile);

$manifest = (string) file_get_contents($manifestFile);
$manifest = preg_replace('/"version_name"\\s*:\\s*"[^"]+"/', '"version_name": "' . $versionName . '"', $manifest, 1, $manifestNameCount);
$manifest = preg_replace('/"version_code"\\s*:\\s*\\d+/', '"version_code": ' . $versionCode, $manifest, 1, $manifestCodeCount);
$manifest = preg_replace('/"built_at"\\s*:\\s*"[^"]+"/', '"built_at": "' . $generatedAt . '"', $manifest, 1, $manifestBuiltCount);
if ($manifest === null || $manifestNameCount !== 1 || $manifestCodeCount !== 1 || $manifestBuiltCount !== 1) {
    throw new RuntimeException('Could not update manifest release fields deterministically.');
}
json_decode($manifest, true, 512, JSON_THROW_ON_ERROR);
file_put_contents($manifestFile, $manifest);

$metadata = (string) file_get_contents($metadataFile);
$metadata = preg_replace('/"version_name"\\s*:\\s*"[^"]+"/', '"version_name": "' . $versionName . '"', $metadata, 1, $metadataNameCount);
$metadata = preg_replace('/"version_code"\\s*:\\s*\\d+/', '"version_code": ' . $versionCode, $metadata, 1, $metadataCodeCount);
$metadata = preg_replace('/"app_sha256"\\s*:\\s*"[^"]+"/', '"app_sha256": "' . $appSha . '"', $metadata, 1, $metadataShaCount);
$metadata = preg_replace('/"generated_at"\\s*:\\s*"[^"]+"/', '"generated_at": "' . $generatedAt . '"', $metadata, 1, $metadataTimeCount);
if ($metadata === null || $metadataNameCount !== 1 || $metadataCodeCount !== 1 || $metadataShaCount !== 1 || $metadataTimeCount !== 1) {
    throw new RuntimeException('Could not update release metadata deterministically.');
}
json_decode($metadata, true, 512, JSON_THROW_ON_ERROR);
file_put_contents($metadataFile, $metadata);

printf("NanoPino version bumped to %s (%d)\n", $versionName, $versionCode);
printf("app_sha256=%s\n", $appSha);

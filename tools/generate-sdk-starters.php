<?php
declare(strict_types=1);

use App\com_pinoox_cms\Cms\Sdk\Package\ExtensionScaffoldGenerator;
use App\com_pinoox_cms\Cms\Sdk\Package\SdkStarterCatalog;

require dirname(__DIR__) . '/vendor/autoload.php';

$id = $argv[1] ?? '';
$catalog = SdkStarterCatalog::blueprints();
if ($id === '' || !isset($catalog[$id])) {
    fwrite(STDERR, "Usage: php tools/generate-sdk-starters.php <starter-id> [output-directory]\n");
    fwrite(STDERR, 'Available: ' . implode(', ', array_keys($catalog)) . "\n");
    exit(64);
}

$output = $argv[2] ?? (getcwd() . '/build/sdk-starters/' . $id);
$output = rtrim($output, '/\\');
if ($output === '' || str_contains($output, "\0")) {
    fwrite(STDERR, "Invalid output directory.\n");
    exit(64);
}
if (is_dir($output) && (new FilesystemIterator($output))->valid()) {
    fwrite(STDERR, "Refusing to overwrite a non-empty output directory: {$output}\n");
    exit(73);
}

$result = (new ExtensionScaffoldGenerator())->generate($catalog[$id], $output);
echo "Generated {$id} starter in {$result->directory}\n";
echo 'Files: ' . count($result->files) . "\n";

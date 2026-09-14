<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Sdk\Package;

use App\com_pinoox_cms\Cms\Extension\ExtensionType;

/**
 * Read-only metadata for supported SDK starting points.
 * Generation remains an explicit local developer action.
 */
final class SdkStarterCatalog
{
    /** @return array<string,ExtensionPackageBlueprint> */
    public static function blueprints(): array
    {
        return [
            'module' => new ExtensionPackageBlueprint(
                package: 'com_example_catalog', name: 'Catalog Module', type: ExtensionType::Module,
                publisher: 'example-developer', description: 'A content-backed module with a migration and management capability.',
                capabilities: ['catalog.read', 'catalog.manage'],
            ),
            'plugin' => new ExtensionPackageBlueprint(
                package: 'com_example_editor_tools', name: 'Editor Tools Plugin', type: ExtensionType::Plugin,
                publisher: 'example-developer', description: 'An admin extension with a safe same-origin page and API route.',
                capabilities: ['example.read'],
            ),
            'integration' => new ExtensionPackageBlueprint(
                package: 'com_example_sync', name: 'Sync Integration', type: ExtensionType::Integration,
                publisher: 'example-developer', description: 'A queue-friendly integration action with an explicit capability.',
                capabilities: ['integration.sync'],
            ),
            'theme' => new ExtensionPackageBlueprint(
                package: 'com_example_theme', name: 'Starter Theme', type: ExtensionType::Theme,
                publisher: 'example-developer', description: 'A Pinoox-native theme with design tokens and an index template.',
                targetApp: 'com_pinoox_cms', themeName: 'starter',
            ),
            'admin-extension' => new ExtensionPackageBlueprint(
                package: 'com_example_admin', name: 'Admin Extension', type: ExtensionType::AdminExtension,
                publisher: 'example-developer', description: 'A focused admin surface registered through CMS admin registries.',
                capabilities: ['example.admin'],
            ),
            'block' => new ExtensionPackageBlueprint(
                package: 'com_example_notice_block', name: 'Notice Block', type: ExtensionType::Block,
                publisher: 'example-developer', description: 'A single owned block with a validated attribute and renderer.',
                capabilities: ['example.blocks.use'],
            ),
            'block-package' => new ExtensionPackageBlueprint(
                package: 'com_example_blocks', name: 'Block Pack', type: ExtensionType::BlockPackage,
                publisher: 'example-developer', description: 'A package boundary for a reusable family of blocks.',
                capabilities: ['example.blocks.use'],
            ),
        ];
    }

    /** @return list<array<string,mixed>> */
    public static function all(): array
    {
        $items = [];
        foreach (self::blueprints() as $id => $spec) {
            $items[] = [
                'id' => $id,
                'label' => $spec->name,
                'type' => $spec->type->value,
                'description' => $spec->description,
                'path' => 'examples/sdk/' . $id,
                'files' => self::filePlan($spec),
                'command' => 'php tools/generate-sdk-starters.php ' . $id,
                'validation' => 'ExtensionTestHarness + SdkPackageValidator',
            ];
        }
        return $items;
    }

    /** @return list<string> */
    private static function filePlan(ExtensionPackageBlueprint $spec): array
    {
        $files = ['README.md', 'app.php', 'extension-manifest.json', 'tests/extension.php'];
        if ($spec->type === ExtensionType::Theme) {
            $files[] = 'theme/' . $spec->themeName . '/config.php';
            $files[] = 'theme/' . $spec->themeName . '/design.json';
            $files[] = 'theme/' . $spec->themeName . '/index.twig';
        } else {
            $files[] = 'boot.php';
            $files[] = 'Extension.php';
            if (in_array($spec->type, [ExtensionType::Plugin, ExtensionType::AdminExtension], true)) {
                $files[] = 'admin/dist/page.mjs';
            }
            if ($spec->type === ExtensionType::Module) {
                $files[] = 'database/migrations/2026_01_01_000000_example_table.php';
            }
            if ($spec->type->requiresBlocksProfile()) {
                $files[] = 'blocks/README.md';
            }
        }
        sort($files);
        return $files;
    }
}

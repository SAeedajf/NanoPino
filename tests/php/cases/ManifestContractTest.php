<?php
declare(strict_types=1);

use App\com_pinoox_cms\Cms\Exception\ManifestValidationException;
use App\com_pinoox_cms\Cms\Extension\ExtensionType;
use App\com_pinoox_cms\Cms\Manifest\ExtensionManifestValidator;
use App\com_pinoox_cms\Cms\Sdk\Package\ExtensionPackageBlueprint;

return [
    'SDK generator and PHP manifest validator support every declared ExtensionType' => static function (): void {
        foreach (ExtensionType::cases() as $type) {
            $slug = str_replace('-', '_', $type->value);
            $spec = new ExtensionPackageBlueprint(
                package: 'com_test_' . $slug,
                name: 'Test ' . $type->label(),
                type: $type,
                publisher: 'test-developer',
                targetApp: $type === ExtensionType::Theme ? 'com_pinoox_cms' : null,
                themeName: $type === ExtensionType::Theme ? 'contract_theme' : null,
            );

            $manifest = $spec->toPinxManifest();
            np_assert_same($type->value, $manifest['cms']['extension_type'] ?? null);
            np_assert_same($type === ExtensionType::Theme ? 'theme' : 'app', $manifest['type'] ?? null);
        }
    },

    'machine manifest schema exposes the exact semantic ExtensionType set' => static function (): void {
        $schemaPath = NANOPINO_ROOT . '/payload/resources/schemas/cms-extension-manifest-v1.schema.json';
        $schema = json_decode((string)file_get_contents($schemaPath), true, 512, JSON_THROW_ON_ERROR);

        $declared = $schema['properties']['cms']['properties']['extension_type']['enum'] ?? [];
        $expected = array_map(static fn (ExtensionType $type): string => $type->value, ExtensionType::cases());
        np_assert_same($expected, $declared);

        $appTypes = $schema['allOf'][1]['then']['properties']['cms']['properties']['extension_type']['enum'] ?? [];
        np_assert_same(
            array_values(array_filter($expected, static fn (string $type): bool => $type !== ExtensionType::Theme->value)),
            $appTypes,
        );

        $blockTypes = $schema['allOf'][2]['if']['properties']['cms']['properties']['extension_type']['enum'] ?? [];
        np_assert_same([ExtensionType::Block->value, ExtensionType::BlockPackage->value], $blockTypes);

        $thirdPartyKeys = $schema['allOf'][3]['then']['properties']['cms']['propertyNames']['enum'] ?? [];
        np_assert_true(in_array('metadata', $thirdPartyKeys, true), 'cms.metadata must be the custom-data namespace.');
        np_assert_false(in_array('permission', $thirdPartyKeys, true), 'Singular permission typo must not be accepted by the schema.');
    },

    'third-party CMS profile rejects unknown top-level keys and directs custom data to metadata' => static function (): void {
        $validator = new ExtensionManifestValidator();
        $spec = new ExtensionPackageBlueprint(
            package: 'com_test_manifest_typo',
            name: 'Manifest Typo Test',
            type: ExtensionType::Plugin,
            publisher: 'test-developer',
        );
        $manifest = $spec->toPinxManifest();
        $manifest['cms']['permission'] = ['example.read'];

        np_assert_throws(
            static fn () => $validator->validate($manifest),
            ManifestValidationException::class,
            'cms contains unsupported property: permission.',
        );

        unset($manifest['cms']['permission']);
        $manifest['cms']['metadata'] = ['vendor' => ['feature' => true]];
        $validator->validate($manifest);
    },

    'core-module profile remains forward-compatible for NanoPino internal metadata' => static function (): void {
        $validator = new ExtensionManifestValidator();
        $spec = new ExtensionPackageBlueprint(
            package: 'com_test_core_module',
            name: 'Core Module Contract Test',
            type: ExtensionType::CoreModule,
            publisher: 'test-developer',
        );
        $manifest = $spec->toPinxManifest();
        $manifest['cms']['internal_release_evidence'] = ['gate' => 'test'];
        $validator->validate($manifest);
    },

    'block and block-package manifests require a blocks profile' => static function (): void {
        $validator = new ExtensionManifestValidator();

        foreach ([ExtensionType::Block, ExtensionType::BlockPackage] as $type) {
            $spec = new ExtensionPackageBlueprint(
                package: 'com_test_' . str_replace('-', '_', $type->value),
                name: 'Block Contract Test',
                type: $type,
                publisher: 'test-developer',
            );
            $manifest = $spec->toPinxManifest();
            unset($manifest['cms']['blocks']);

            np_assert_throws(
                static fn () => $validator->validate($manifest),
                ManifestValidationException::class,
                'cms.blocks is required for block and block-package extensions.',
            );
        }
    },

    'deprecated flat 0.x theme profile keys stay accepted during the migration window' => static function (): void {
        $validator = new ExtensionManifestValidator();
        $spec = new ExtensionPackageBlueprint(
            package: 'com_test_theme_legacy',
            name: 'Legacy Theme Contract Test',
            type: ExtensionType::Theme,
            publisher: 'test-developer',
            targetApp: 'com_pinoox_cms',
            themeName: 'legacy_contract',
        );
        $manifest = $spec->toPinxManifest();
        unset($manifest['cms']['theme']);
        $manifest['cms']['paths'] = ['templates' => 'templates'];
        $manifest['cms']['features'] = ['rtl' => true];
        $manifest['cms']['minimum_cms'] = '0.21.0';
        $manifest['cms']['template_extensions'] = ['twig'];

        $validator->validate($manifest);
    },
];

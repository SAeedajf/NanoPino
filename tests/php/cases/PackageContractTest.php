<?php
declare(strict_types=1);

return [
    'Package and Pinoox app release metadata agree' => static function (): void {
        $app = require NANOPINO_ROOT . '/payload/app.php';
        $manifest = json_decode(
            (string)file_get_contents(NANOPINO_ROOT . '/manifest.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        np_assert_same('com_pinoox_cms', $app['package'] ?? null);
        np_assert_same($app['package'], $manifest['package'] ?? null);
        np_assert_same($app['version-name'], $manifest['version_name'] ?? null);
        np_assert_same($app['version-code'], $manifest['version_code'] ?? null);
        np_assert_same(216, $app['minpin'] ?? null);
        np_assert_same(216, $manifest['minpin'] ?? null);
    },

    'Package keeps boot and access cutovers fail-closed for the current release' => static function (): void {
        $app = require NANOPINO_ROOT . '/payload/app.php';

        np_assert_same(false, $app['boot-global'] ?? null);
        np_assert_same(true, $app['access']['platform_super'] ?? null);
        np_assert_same(['admin', 'superadmin'], $app['access']['super_roles'] ?? null);
        np_assert_same('platform', $app['transport']['access'] ?? null);
        np_assert_same('platform', $app['transport']['user'] ?? null);
    },

    'PINX build excludes development-only test and documentation trees' => static function (): void {
        $app = require NANOPINO_ROOT . '/payload/app.php';
        $exclude = $app['build']['exclude'] ?? [];

        foreach ([
            'tests',
            'docs',
            '.git',
            '.github',
            'node_modules',
            'theme/cms-admin/src',
            'theme/cms-admin/tests',
            'theme/cms-admin/node_modules',
            'theme/cms-admin/package.json',
            'theme/cms-admin/package-lock.json',
            'theme/cms-admin/build-linux.sh',
            'theme/cms-admin/build-windows.ps1',
            'theme/cms-admin/run-tests.mjs',
            'theme/cms-admin/verify-dist.mjs',
            'theme/cms-admin/source-fingerprint.mjs',
            'theme/cms-admin/runtime-fingerprint.mjs',
            'theme/cms-admin/repair-existing-builder.sh',
            'theme/cms-admin/README-FA.md',
        ] as $required) {
            np_assert_true(in_array($required, $exclude, true), 'Missing build exclusion: ' . $required);
        }
    },

    'Required documentation manifest is structurally complete in source workspace' => static function (): void {
        $manifest = json_decode(
            (string)file_get_contents(NANOPINO_ROOT . '/payload/resources/docs/documentation-manifest-v1.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        np_assert_same(31, count($manifest['required_docs'] ?? []));
        foreach ($manifest['required_docs'] as $path) {
            np_assert_true(is_file(NANOPINO_ROOT . '/' . $path), 'Missing required documentation: ' . $path);
        }
    },

    'NanoPino installability preflight is the first package migration and enforces the native kernel floor' => static function (): void {
        $app = require NANOPINO_ROOT . '/payload/app.php';
        $manifest = json_decode(
            (string)file_get_contents(NANOPINO_ROOT . '/manifest.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        np_assert_same(216, $app['minpin'] ?? null);
        np_assert_same(216, $app['pinx']['minpin'] ?? null);
        np_assert_same('>=3.10.0', $app['cms']['requires']['pincore'] ?? null);
        np_assert_same('>=3.10.0', $manifest['cms']['requires']['pincore'] ?? null);

        $migrations = glob(NANOPINO_ROOT . '/payload/database/migrations/*.php') ?: [];
        sort($migrations, SORT_STRING);
        np_assert_true($migrations !== []);
        np_assert_same(
            '2026_09_01_000000_preflight_nanopino_environment.php',
            basename($migrations[0]),
        );

        $preflight = (string)file_get_contents($migrations[0]);
        np_assert_contains('PinooxInstallabilityProbe', $preflight);
        np_assert_contains('->assertReady(', $preflight);
    },

    'NanoPino native uninstall lifecycle rolls back every owned migration batch and fails closed' => static function (): void {
        $lifecycle = (string)file_get_contents(NANOPINO_ROOT . '/payload/lifecycle.php');

        np_assert_contains("'uninstall'", $lifecycle);
        np_assert_contains("new Migrator(", $lifecycle);
        np_assert_contains("->rollback(0)", $lifecycle);
        np_assert_contains("DB::connectionNameForPackage(\$package)", $lifecycle);
        np_assert_contains("Refusing to delete application files", $lifecycle);
        np_assert_contains("'com_pinoox_cms'", $lifecycle);
        np_assert_false(str_contains($lifecycle, 'dropIfExists('), 'Lifecycle must not blindly drop adopted/legacy tables.');
    },

    'CI release gate includes pinned real Pinoox MySQL lifecycle' => static function (): void {
        $workflow = (string)file_get_contents(NANOPINO_ROOT . '/.github/workflows/validate.yml');
        $lifecycle = (string)file_get_contents(NANOPINO_ROOT . '/tools/ci/pinoox-lifecycle.sh');

        np_assert_contains('pinoox-lifecycle:', $workflow);
        np_assert_contains('mysql:8.4', $workflow);
        np_assert_contains('PINOOX_E2E_REF:', $workflow);
        np_assert_contains("pincore: ['3.10.0', '3.14.4']", $workflow);
        np_assert_contains('NANOPINO_UPGRADE_BASE_REF:', $workflow);
        np_assert_contains('actions/download-artifact@v4', $workflow);
        np_assert_contains('include-hidden-files: true', $workflow);
        np_assert_contains('tools/ci/pinoox-lifecycle.sh', $workflow);

        np_assert_contains('install-platform run', $lifecycle);
        np_assert_contains('tools/release/build-pinx.sh', $lifecycle);
        np_assert_contains('pinx:install', $lifecycle);
        np_assert_contains('pinx:uninstall', $lifecycle);
        np_assert_contains('information_schema.tables', $lifecycle);
        np_assert_contains('upgrade_from_version=', $lifecycle);
        np_assert_contains('upgrade_to_version=', $lifecycle);
        np_assert_contains('installability_preflight_records=', $lifecycle);
        np_assert_contains('fresh_install_tables=', $lifecycle);
        np_assert_contains('uninstall_tables=', $lifecycle);
    },

    'Release tooling verifies source before invoking native Pinoox PINX build' => static function (): void {
        $script = (string)file_get_contents(NANOPINO_ROOT . '/tools/release/build-pinx.sh');
        $verify = strpos($script, 'verify-source.sh');
        $build = strpos($script, 'pinx:build');

        np_assert_true($verify !== false);
        np_assert_true($build !== false);
        np_assert_true($verify < $build, 'Source verification must execute before pinx:build.');
        np_assert_contains('Refusing to overwrite an existing $PACKAGE app', $script);
        np_assert_contains('pinx:info', $script);
        np_assert_contains('verify-pinx-installability.php', $script);
        np_assert_contains('sha256sum', $script);
    },
];

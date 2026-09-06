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
        np_assert_same(205, $app['minpin'] ?? null);
        np_assert_same(205, $manifest['minpin'] ?? null);
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

        foreach (['tests', 'docs', '.git', '.github', 'node_modules'] as $required) {
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

        np_assert_same(30, count($manifest['required_docs'] ?? []));
        foreach ($manifest['required_docs'] as $path) {
            np_assert_true(is_file(NANOPINO_ROOT . '/' . $path), 'Missing required documentation: ' . $path);
        }
    },

    'CI release gate includes pinned real Pinoox MySQL lifecycle' => static function (): void {
        $workflow = (string)file_get_contents(NANOPINO_ROOT . '/.github/workflows/validate.yml');
        $lifecycle = (string)file_get_contents(NANOPINO_ROOT . '/tools/ci/pinoox-lifecycle.sh');

        np_assert_contains('pinoox-lifecycle:', $workflow);
        np_assert_contains('mysql:8.4', $workflow);
        np_assert_contains('PINOOX_E2E_REF:', $workflow);
        np_assert_contains('actions/download-artifact@v4', $workflow);
        np_assert_contains('tools/ci/pinoox-lifecycle.sh', $workflow);

        np_assert_contains('install-platform run', $lifecycle);
        np_assert_contains('tools/release/build-pinx.sh', $lifecycle);
        np_assert_contains('pinx:install', $lifecycle);
        np_assert_contains('pinx:uninstall', $lifecycle);
        np_assert_contains('information_schema.tables', $lifecycle);
        np_assert_contains('force_update_tables=', $lifecycle);
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
        np_assert_contains('sha256sum', $script);
    },
];

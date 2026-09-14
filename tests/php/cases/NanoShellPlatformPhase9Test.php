<?php
declare(strict_types=1);

use App\com_pinoox_cms\Cms\Platform\NanoShellPlatform;
use App\com_pinoox_cms\Cms\Runtime\CmsRuntimeServices;

return [
    'phase 9 registers NanoShell as the independent platform identity' => static function (): void {
        $profile = NanoShellPlatform::profile();
        NanoShellPlatform::assertProfile($profile);

        np_assert_same('nanoshell', $profile['id']);
        np_assert_same('NanoShell', $profile['name']);
        np_assert_same('nanoshell-platform-v1', $profile['contract']);
        np_assert_true($profile['standalone']);
        np_assert_same('NanoPino', $profile['product']);
        np_assert_same([
            'auth.rbac',
            'content.editorial',
            'storage.native',
            'theme.native',
            'builder.native',
            'extensions.signed',
            'recovery.safe-mode',
        ], $profile['capabilities']);
        np_assert_same([
            'contract' => 'nanoshell-platform-v1',
            'minimum_version' => 1,
            'host_bindings' => 'pinoox-native',
        ], $profile['compatibility']);
        np_assert_true(NanoShellPlatform::supports('builder.native'));
        np_assert_false(NanoShellPlatform::supports('legacy.theme-loader'));
        np_assert_same($profile, CmsRuntimeServices::nanoShellPlatform());
        np_assert_false(str_contains(strtolower(json_encode($profile, JSON_THROW_ON_ERROR)), 'wordpress'));
    },

    'phase 9 projects one NanoShell profile into app and package metadata' => static function (): void {
        $app = require NANOPINO_ROOT . '/payload/app.php';
        $manifest = json_decode((string)file_get_contents(NANOPINO_ROOT . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        $payloadManifest = json_decode((string)file_get_contents(NANOPINO_ROOT . '/payload/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        $expected = NanoShellPlatform::profile();

        np_assert_same($expected, $app['cms']['platform'] ?? null);
        np_assert_same($expected, $manifest['cms']['platform'] ?? null);
        np_assert_same($expected, $payloadManifest['cms']['platform'] ?? null);
        np_assert_true(in_array('nanoshell.platform', $app['cms']['provides'] ?? [], true));
        np_assert_true(in_array('nanoshell.platform', $manifest['cms']['provides'] ?? [], true));
        np_assert_true(in_array('nanoshell.platform', $payloadManifest['cms']['provides'] ?? [], true));

        $adminController = (string)file_get_contents(NANOPINO_ROOT . '/payload/Controller/AdminController.php');
        np_assert_true(str_contains($adminController, "'platform' => CmsRuntimeServices::nanoShellPlatform(),"));
        np_assert_same(1, substr_count($adminController, "'platform' => CmsRuntimeServices::nanoShellPlatform(),"));
        np_assert_false(str_contains($adminController, "'data' => [\n                        'platform'"));
        np_assert_false(str_contains($adminController, "'runtime' => [\n                            'platform'"));
    },

    'phase 9 rejects legacy source names from the NanoShell platform profile' => static function (): void {
        $invalid = NanoShellPlatform::profile();
        $invalid['name'] = 'Legacy WordPress Platform';
        np_assert_throws(
            static fn () => NanoShellPlatform::assertProfile($invalid),
            InvalidArgumentException::class,
            'legacy source identity',
        );

        $invalid = NanoShellPlatform::profile();
        $invalid['runtime']['source'] = 'wordpress';
        np_assert_throws(
            static fn () => NanoShellPlatform::assertProfile($invalid),
            InvalidArgumentException::class,
            'legacy source identity',
        );
    },

    'phase 9 rejects incompatible NanoShell capability metadata' => static function (): void {
        $incompatible = NanoShellPlatform::profile();
        $incompatible['compatibility']['host_bindings'] = 'foreign-runtime';
        np_assert_throws(
            static fn () => NanoShellPlatform::assertCompatible($incompatible),
            InvalidArgumentException::class,
            'incompatible',
        );

        $incompatible = NanoShellPlatform::profile();
        unset($incompatible['compatibility']['minimum_version']);
        np_assert_throws(
            static fn () => NanoShellPlatform::assertCompatible($incompatible),
            InvalidArgumentException::class,
            'incompatible',
        );
    },
];

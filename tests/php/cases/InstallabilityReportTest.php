<?php
declare(strict_types=1);

use App\com_pinoox_cms\Cms\Installer\Installability\InstallabilityFinding;
use App\com_pinoox_cms\Cms\Installer\Installability\InstallabilityReport;
use App\com_pinoox_cms\Cms\Installer\Installability\InstallabilitySeverity;

return [
    'Installability report is ready when only warnings are present' => static function (): void {
        $report = new InstallabilityReport([
            new InstallabilityFinding(
                'install.php_memory_low',
                InstallabilitySeverity::Warning,
                'memory warning',
                ['current_bytes' => 64],
            ),
        ], ['php' => PHP_VERSION]);

        np_assert_true($report->ready());
        np_assert_same(0, count($report->blockers()));
        np_assert_same(1, count($report->warnings()));
        np_assert_same(true, $report->publicData()['ready']);
        $report->assertReady();
    },

    'Installability report fails closed when any blocker is present' => static function (): void {
        $report = new InstallabilityReport([
            new InstallabilityFinding(
                'install.database_unavailable',
                InstallabilitySeverity::Blocker,
                'database unavailable',
            ),
            new InstallabilityFinding(
                'install.php_opcache_unavailable',
                InstallabilitySeverity::Warning,
                'opcache unavailable',
            ),
        ]);

        np_assert_false($report->ready());
        np_assert_same(1, count($report->blockers()));
        np_assert_same(1, count($report->warnings()));
        np_assert_throws(
            static fn () => $report->assertReady(),
            RuntimeException::class,
            'install.database_unavailable',
        );
    },

    'Installability findings serialize stable public diagnostics without secrets' => static function (): void {
        $finding = new InstallabilityFinding(
            'install.package_root_not_writable',
            InstallabilitySeverity::Blocker,
            'not writable',
            ['path' => '/example'],
        );

        np_assert_same([
            'code' => 'install.package_root_not_writable',
            'severity' => 'blocker',
            'message' => 'not writable',
            'details' => ['path' => '/example'],
        ], $finding->toArray());
    },
];

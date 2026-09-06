<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Installer\Installability;

use Composer\InstalledVersions;
use Pinoox\Portal\Database\DB;

final class PinooxInstallabilityProbe
{
    private const PACKAGE = 'com_pinoox_cms';
    private const MIN_PHP = '8.2.0';
    private const MIN_PINCORE = '3.10.0';
    private const MIN_PINCORE_CODE = 216;
    private const MIN_FREE_BYTES = 33_554_432;

    /** @var list<string> */
    private const REQUIRED_EXTENSIONS = [
        'ctype',
        'fileinfo',
        'filter',
        'hash',
        'json',
        'mbstring',
        'openssl',
        'pdo',
        'tokenizer',
        'zip',
    ];

    /** @var list<string> */
    private const REQUIRED_PACKAGE_FILES = [
        'app.php',
        'lifecycle.php',
        'database/migrations',
        'theme/cms-admin/dist/.vite/manifest.json',
        'theme/cms-admin/dist/.cms-build.json',
        'resources/release/release-metadata-v1.json',
    ];

    public function inspect(string $packageRoot): InstallabilityReport
    {
        $findings = [];
        $evidence = [
            'php' => PHP_VERSION,
            'php_sapi' => PHP_SAPI,
            'minimum_php' => self::MIN_PHP,
            'minimum_pincore' => self::MIN_PINCORE,
            'minimum_pincore_code' => self::MIN_PINCORE_CODE,
        ];

        if (version_compare(PHP_VERSION, self::MIN_PHP, '<')) {
            $findings[] = $this->blocker(
                'install.php_too_old',
                'NanoPino requires PHP ' . self::MIN_PHP . ' or newer.',
                ['current' => PHP_VERSION],
            );
        }

        $missingExtensions = [];
        foreach (self::REQUIRED_EXTENSIONS as $extension) {
            if (!extension_loaded($extension)) {
                $missingExtensions[] = $extension;
            }
        }
        $evidence['required_extensions'] = self::REQUIRED_EXTENSIONS;
        $evidence['missing_extensions'] = $missingExtensions;
        if ($missingExtensions !== []) {
            $findings[] = $this->blocker(
                'install.php_extensions_missing',
                'Required PHP extensions are missing.',
                ['missing' => $missingExtensions],
            );
        }

        $memoryBytes = $this->iniBytes((string) ini_get('memory_limit'));
        $evidence['memory_limit'] = ini_get('memory_limit') ?: null;
        $evidence['memory_limit_bytes'] = $memoryBytes;
        if ($memoryBytes !== null && $memoryBytes > 0 && $memoryBytes < 33_554_432) {
            $findings[] = $this->blocker(
                'install.php_memory_critical',
                'PHP memory_limit is below the minimum safe installation floor.',
                ['minimum_bytes' => 33_554_432, 'current_bytes' => $memoryBytes],
            );
        } elseif ($memoryBytes !== null && $memoryBytes > 0 && $memoryBytes < 134_217_728) {
            $findings[] = $this->warning(
                'install.php_memory_low',
                'PHP memory_limit is below the recommended 128 MiB.',
                ['recommended_bytes' => 134_217_728, 'current_bytes' => $memoryBytes],
            );
        }

        $uploadBytes = $this->iniBytes((string) ini_get('upload_max_filesize'));
        $postBytes = $this->iniBytes((string) ini_get('post_max_size'));
        $evidence['upload_max_filesize_bytes'] = $uploadBytes;
        $evidence['post_max_size_bytes'] = $postBytes;
        foreach (['upload_max_filesize' => $uploadBytes, 'post_max_size' => $postBytes] as $name => $bytes) {
            if ($bytes !== null && $bytes > 0 && $bytes < 8_388_608) {
                $findings[] = $this->warning(
                    'install.php_upload_limit_low',
                    $name . ' is below the recommended 8 MiB package upload budget.',
                    ['directive' => $name, 'recommended_bytes' => 8_388_608, 'current_bytes' => $bytes],
                );
            }
        }

        $maxExecution = (int) ini_get('max_execution_time');
        $evidence['max_execution_time'] = $maxExecution;
        if ($maxExecution > 0 && $maxExecution < 30) {
            $findings[] = $this->warning(
                'install.php_execution_window_short',
                'PHP max_execution_time is below the recommended 30 seconds for package lifecycle work.',
                ['current_seconds' => $maxExecution],
            );
        }

        if (!extension_loaded('Zend OPcache')) {
            $findings[] = $this->warning(
                'install.php_opcache_unavailable',
                'OPcache is unavailable; NanoPino will run but production PHP performance may be reduced.',
            );
        }

        if (!extension_loaded('gd') && !extension_loaded('imagick')) {
            $findings[] = $this->warning(
                'install.media_image_driver_unavailable',
                'Neither GD nor Imagick is available; image processing features may be limited.',
            );
        }

        $pincore = $this->pincoreVersion();
        $evidence['pincore'] = $pincore;
        if ($pincore !== null && version_compare($pincore, self::MIN_PINCORE, '<')) {
            $findings[] = $this->blocker(
                'install.pincore_too_old',
                'Pincore is older than the NanoPino lifecycle contract.',
                ['minimum' => self::MIN_PINCORE, 'current' => $pincore],
            );
        }

        $root = realpath($packageRoot);
        $evidence['package_root'] = $root ?: $packageRoot;
        if ($root === false || !is_dir($root)) {
            $findings[] = $this->blocker(
                'install.package_root_missing',
                'NanoPino package root is unavailable after extraction.',
            );
        } else {
            if (!is_readable($root)) {
                $findings[] = $this->blocker(
                    'install.package_root_unreadable',
                    'NanoPino package root is not readable.',
                );
            }
            if (!is_writable($root)) {
                $findings[] = $this->blocker(
                    'install.package_root_not_writable',
                    'NanoPino package root is not writable; updates cannot be applied safely.',
                );
            }

            $parent = dirname($root);
            $evidence['package_parent_writable'] = is_writable($parent);
            if (!is_writable($parent)) {
                $findings[] = $this->blocker(
                    'install.package_parent_not_writable',
                    'Pinoox application directory is not writable; update/uninstall recovery is unsafe.',
                );
            }

            foreach (self::REQUIRED_PACKAGE_FILES as $relative) {
                if (!file_exists($root . '/' . $relative)) {
                    $findings[] = $this->blocker(
                        'install.package_payload_incomplete',
                        'Required NanoPino payload entry is missing.',
                        ['path' => $relative],
                    );
                }
            }

            $free = @disk_free_space($parent);
            $evidence['package_filesystem_free_bytes'] = $free === false ? null : (int) $free;
            if ($free !== false && $free < self::MIN_FREE_BYTES) {
                $findings[] = $this->blocker(
                    'install.disk_space_low',
                    'Insufficient free disk space for a recoverable NanoPino installation/update.',
                    ['minimum_bytes' => self::MIN_FREE_BYTES, 'free_bytes' => (int) $free],
                );
            }
        }

        $tmp = sys_get_temp_dir();
        $evidence['temp_dir'] = $tmp;
        $evidence['temp_writable'] = is_dir($tmp) && is_writable($tmp);
        if (!is_dir($tmp) || !is_writable($tmp)) {
            $findings[] = $this->blocker(
                'install.temp_not_writable',
                'PHP temporary directory is not writable.',
                ['path' => $tmp],
            );
        }

        $this->inspectDatabase($findings, $evidence);

        return new InstallabilityReport($findings, $evidence);
    }

    public function assertReady(string $packageRoot): InstallabilityReport
    {
        $report = $this->inspect($packageRoot);
        $report->assertReady();

        return $report;
    }

    /** @param list<InstallabilityFinding> $findings @param array<string,mixed> $evidence */
    private function inspectDatabase(array &$findings, array &$evidence): void
    {
        try {
            $connectionName = DB::connectionNameForPackage(self::PACKAGE);
            $connection = DB::connection($connectionName);
            $pdo = $connection->getPdo();
            $driver = strtolower((string) $connection->getDriverName());
            $database = (string) $connection->getDatabaseName();

            $evidence['database'] = [
                'connection' => $connectionName,
                'driver' => $driver,
                'database' => $database,
                'connected' => $pdo instanceof \PDO,
            ];

            if (!in_array($driver, ['mysql', 'mariadb'], true)) {
                $findings[] = $this->blocker(
                    'install.database_driver_unsupported',
                    'NanoPino installation is currently supported on MySQL/MariaDB package connections.',
                    ['driver' => $driver],
                );
                return;
            }

            if (!extension_loaded('pdo_mysql')) {
                $findings[] = $this->blocker(
                    'install.pdo_mysql_missing',
                    'pdo_mysql is required for the configured MySQL/MariaDB connection.',
                );
                return;
            }

            $probe = $connection->selectOne('SELECT 1 AS nanopino_install_probe');
            if ($probe === null) {
                $findings[] = $this->blocker(
                    'install.database_probe_failed',
                    'Database connection did not return the expected installation probe row.',
                );
            }

            $versionRow = $connection->selectOne('SELECT VERSION() AS version');
            $serverVersion = is_object($versionRow)
                ? (string) ($versionRow->version ?? '')
                : (is_array($versionRow) ? (string) ($versionRow['version'] ?? '') : '');
            $evidence['database']['server_version'] = $serverVersion;

            if ($serverVersion !== '') {
                $numericVersion = $this->numericVersion($serverVersion);
                $maria = str_contains(strtolower($serverVersion), 'mariadb');
                $testedFloor = $maria ? '10.6.0' : '8.0.0';
                if ($numericVersion !== null && version_compare($numericVersion, $testedFloor, '<')) {
                    $findings[] = $this->warning(
                        'install.database_version_legacy_untested',
                        'Database version is below NanoPino tested production baseline.',
                        [
                            'family' => $maria ? 'mariadb' : 'mysql',
                            'current' => $numericVersion,
                            'tested_floor' => $testedFloor,
                        ],
                    );
                }
            }

            try {
                $charsetRow = $connection->selectOne(
                    'SELECT @@character_set_database AS charset, @@collation_database AS collation',
                );
                $charset = is_object($charsetRow)
                    ? (string) ($charsetRow->charset ?? '')
                    : (is_array($charsetRow) ? (string) ($charsetRow['charset'] ?? '') : '');
                $collation = is_object($charsetRow)
                    ? (string) ($charsetRow->collation ?? '')
                    : (is_array($charsetRow) ? (string) ($charsetRow['collation'] ?? '') : '');

                $evidence['database']['charset'] = $charset;
                $evidence['database']['collation'] = $collation;
                if ($charset !== '' && strtolower($charset) !== 'utf8mb4') {
                    $findings[] = $this->warning(
                        'install.database_charset_not_utf8mb4',
                        'Database default charset is not utf8mb4; full Unicode coverage is not guaranteed.',
                        ['charset' => $charset, 'collation' => $collation],
                    );
                }
            } catch (\Throwable $error) {
                $evidence['database']['charset_probe'] = 'unavailable:' . $error::class;
            }

            try {
                $engine = $connection->selectOne(
                    "SELECT SUPPORT AS support FROM information_schema.ENGINES WHERE ENGINE = 'InnoDB' LIMIT 1",
                );
                $support = is_object($engine)
                    ? strtoupper((string) ($engine->support ?? ''))
                    : (is_array($engine) ? strtoupper((string) ($engine['support'] ?? '')) : '');
                $evidence['database']['innodb_support'] = $support ?: null;
                if ($support !== '' && !in_array($support, ['YES', 'DEFAULT'], true)) {
                    $findings[] = $this->blocker(
                        'install.database_innodb_unavailable',
                        'InnoDB is unavailable on the configured database server.',
                        ['support' => $support],
                    );
                }
            } catch (\Throwable $error) {
                $evidence['database']['innodb_probe'] = 'unavailable:' . $error::class;
                $findings[] = $this->warning(
                    'install.database_innodb_probe_unavailable',
                    'InnoDB capability could not be inspected with the current database privileges.',
                );
            }
        } catch (\Throwable $error) {
            $evidence['database'] = [
                'connected' => false,
                'exception' => $error::class,
            ];
            $findings[] = $this->blocker(
                'install.database_unavailable',
                'NanoPino could not establish the package database connection.',
                ['exception' => $error::class],
            );
        }
    }

    private function pincoreVersion(): ?string
    {
        if (!class_exists(InstalledVersions::class)) {
            return null;
        }

        try {
            $version = InstalledVersions::getPrettyVersion('pinoox/pincore')
                ?? InstalledVersions::getVersion('pinoox/pincore');
        } catch (\Throwable) {
            return null;
        }

        if (!is_string($version) || trim($version) === '') {
            return null;
        }

        $version = ltrim(trim($version), 'vV');
        if (preg_match('/\d+\.\d+\.\d+/', $version, $matches) !== 1) {
            return null;
        }

        return $matches[0];
    }

    private function numericVersion(string $value): ?string
    {
        return preg_match('/\d+\.\d+(?:\.\d+)?/', $value, $matches) === 1
            ? $matches[0]
            : null;
    }

    private function iniBytes(string $value): ?int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return $value === '-1' ? -1 : null;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }

        $unit = strtolower(substr($value, -1));
        $number = (float) substr($value, 0, -1);
        $multiplier = match ($unit) {
            'g' => 1024 ** 3,
            'm' => 1024 ** 2,
            'k' => 1024,
            default => 1,
        };

        return (int) round($number * $multiplier);
    }

    /** @param array<string,mixed> $details */
    private function blocker(string $code, string $message, array $details = []): InstallabilityFinding
    {
        return new InstallabilityFinding($code, InstallabilitySeverity::Blocker, $message, $details);
    }

    /** @param array<string,mixed> $details */
    private function warning(string $code, string $message, array $details = []): InstallabilityFinding
    {
        return new InstallabilityFinding($code, InstallabilitySeverity::Warning, $message, $details);
    }
}

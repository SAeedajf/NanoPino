#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
WORK_DIR="$(mktemp -d /tmp/nanopino-cms-lifecycle.XXXXXX)"
trap 'rm -rf -- "$WORK_DIR"' EXIT

export DB_CONNECTION=devdb
export APP_ENV=development
export PINOOX_TESTING=1
export DEVDB_PATH="$WORK_DIR"

cd "$ROOT_DIR"
php pinoox migrate --devdb --fresh com_pinoox_cms >/tmp/nanopino-phase12-migrate.log

php -r '
require "vendor/autoload.php";
\Pinoox\Support\SystemConfig::clearCache();
$name = \Pinoox\Portal\Database\DB::connectionNameForPackage("com_pinoox_cms");
$connection = \Pinoox\Portal\Database\DB::connection($name);
$connection->table("settings")->insert([
    "setting_key" => "phase12.lifecycle.sentinel",
    "scope_type" => "site",
    "scope_id" => "1",
    "value_type" => "json",
    "value_json" => "{\"preserve\":true}",
    "version" => 1,
    "updated_by" => null,
    "created_at" => date("Y-m-d H:i:s"),
    "updated_at" => date("Y-m-d H:i:s"),
]);
echo "PASS install schema connection={$connection->getDriverName()} prefix={$connection->getTablePrefix()}\n";
'

php pinoox migrate --devdb com_pinoox_cms >/tmp/nanopino-phase12-noop.log

php -r '
require "vendor/autoload.php";
\Pinoox\Support\SystemConfig::clearCache();
$name = \Pinoox\Portal\Database\DB::connectionNameForPackage("com_pinoox_cms");
$connection = \Pinoox\Portal\Database\DB::connection($name);
$row = $connection->table("settings")->where("setting_key", "phase12.lifecycle.sentinel")->first();
if ($row === null) { fwrite(STDERR, "FAIL update sentinel was not preserved\n"); exit(1); }
echo "PASS update sentinel-preserved=1\n";
'

php pinoox migrate --devdb --reset com_pinoox_cms >/tmp/nanopino-phase12-reset.log

php -r '
require "vendor/autoload.php";
\Pinoox\Support\SystemConfig::clearCache();
$name = \Pinoox\Portal\Database\DB::connectionNameForPackage("com_pinoox_cms");
$connection = \Pinoox\Portal\Database\DB::connection($name);
$tables = $connection->getPdo()->query("SELECT name FROM sqlite_master WHERE type = " . chr(39) . "table" . chr(39) . " AND name LIKE " . chr(39) . "cms_%" . chr(39))->fetchAll();
if ($tables !== []) { fwrite(STDERR, "FAIL reset left owned CMS tables: " . count($tables) . "\n"); exit(1); }
echo "PASS reset owned-cms-tables=0\n";
'

printf 'CMS database lifecycle probe passed in isolated DevDB: %s\n' "$WORK_DIR"

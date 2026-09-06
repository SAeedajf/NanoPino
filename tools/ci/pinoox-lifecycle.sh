#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PINOX_ROOT="${PINOX_ROOT:?PINOX_ROOT is required}"
PHP_BIN="${PHP_BIN:-php}"
OUTPUT="${NANOPINO_PINX_OUTPUT:-${RUNNER_TEMP:-/tmp}/NanoPino-e2e.pinx}"
PACKAGE="com_pinoox_cms"

: "${DB_HOST:?DB_HOST is required}"
: "${DB_PORT:?DB_PORT is required}"
: "${DB_DATABASE:?DB_DATABASE is required}"
: "${DB_USERNAME:?DB_USERNAME is required}"
: "${DB_PASSWORD:?DB_PASSWORD is required}"
: "${NANOPINO_CI_ADMIN_PASSWORD:?NANOPINO_CI_ADMIN_PASSWORD is required}"

fail() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

[[ -f "$PINOX_ROOT/pinoox" ]] || fail "Invalid Pinoox root."
[[ -f "$ROOT/manifest.json" ]] || fail "NanoPino manifest missing."

mkdir -p "$PINOX_ROOT/.pinoox"
export NANOPINO_INSTALL_CONFIG="$PINOX_ROOT/.pinoox/install-platform.php"
"$PHP_BIN" -r '
$payload=[
  "lang"=>"en",
  "db"=>[
    "connection"=>"mysql",
    "host"=>getenv("DB_HOST"),
    "port"=>getenv("DB_PORT"),
    "database"=>getenv("DB_DATABASE"),
    "username"=>getenv("DB_USERNAME"),
    "password"=>getenv("DB_PASSWORD"),
    "prefix"=>"pinx_",
    "timezone"=>"+00:00",
  ],
  "user"=>[
    "fname"=>"NanoPino",
    "lname"=>"CIAdmin",
    "email"=>"nanopino-ci@example.invalid",
    "username"=>"nanopino_ci",
    "password"=>getenv("NANOPINO_CI_ADMIN_PASSWORD"),
  ],
];
file_put_contents(getenv("NANOPINO_INSTALL_CONFIG"),"<?php\nreturn ".var_export($payload,true).";\n");
'

cd "$PINOX_ROOT"
"$PHP_BIN" pinoox install-platform check --file=.pinoox/install-platform.php
"$PHP_BIN" pinoox install-platform run --file=.pinoox/install-platform.php --remove

cd "$ROOT"
PHP_BIN="$PHP_BIN" "$ROOT/tools/release/build-pinx.sh" "$PINOX_ROOT" "$OUTPUT"
[[ -s "$OUTPUT" ]] || fail "PINX output was not created."

cd "$PINOX_ROOT"
"$PHP_BIN" pinoox pinx:install "$OUTPUT" --force

"$PHP_BIN" -r '
$app=require $argv[1];
$manifest=json_decode(file_get_contents($argv[2]),true,512,JSON_THROW_ON_ERROR);
if (($app["package"]??null)!==($manifest["package"]??null)) exit(2);
if (($app["version-name"]??null)!==($manifest["version_name"]??null)) exit(3);
if ((int)($app["version-code"]??0)!==(int)($manifest["version_code"]??0)) exit(4);
' "$PINOX_ROOT/apps/$PACKAGE/app.php" "$ROOT/manifest.json"

cms_table_count() {
  "$PHP_BIN" -r '
  $pdo=new PDO(
    "mysql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT").";dbname=".getenv("DB_DATABASE").";charset=utf8mb4",
    getenv("DB_USERNAME"),
    getenv("DB_PASSWORD"),
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]
  );
  $stmt=$pdo->prepare("SELECT table_name FROM information_schema.tables WHERE table_schema=? AND table_name LIKE ? ORDER BY table_name");
  $stmt->execute([getenv("DB_DATABASE"),"%cms_%"]);
  $rows=$stmt->fetchAll(PDO::FETCH_COLUMN);
  foreach($rows as $row){fwrite(STDERR,"cms_table=".$row.PHP_EOL);}
  echo count($rows);
  '
}

expected_tables="$("$PHP_BIN" -r '
$m=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
echo (int)($m["cms"]["runtime_schema"]["required_tables"]??0);
' "$ROOT/manifest.json")"

fresh_tables="$(cms_table_count)"
[[ "$expected_tables" -gt 0 ]] || fail "Invalid required table count."
[[ "$fresh_tables" -ge "$expected_tables" ]] || fail "Fresh install table count is below manifest requirement."
printf 'fresh_install_tables=%s\n' "$fresh_tables"

"$PHP_BIN" pinoox pinx:install "$OUTPUT" --force
updated_tables="$(cms_table_count)"
[[ "$updated_tables" -eq "$fresh_tables" ]] || fail "Force-update changed CMS table count."
printf 'force_update_tables=%s\n' "$updated_tables"

"$PHP_BIN" pinoox pinx:uninstall "$PACKAGE" --force --yes
[[ ! -e "$PINOX_ROOT/apps/$PACKAGE" ]] || fail "App directory remains after uninstall."

remaining_tables="$(cms_table_count)"
[[ "$remaining_tables" -eq 0 ]] || fail "CMS tables remain after uninstall."
printf 'uninstall_tables=%s\n' "$remaining_tables"
printf 'pinoox_lifecycle=PASS pinx=%s\n' "$OUTPUT"

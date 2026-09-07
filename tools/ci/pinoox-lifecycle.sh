#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PINOX_ROOT="${PINOX_ROOT:?PINOX_ROOT is required}"
PHP_BIN="${PHP_BIN:-php}"
OUTPUT="${NANOPINO_PINX_OUTPUT:-${RUNNER_TEMP:-/tmp}/NanoPino-e2e.pinx}"
PACKAGE="com_pinoox_cms"
DB_PREFIX="${DB_PREFIX:-pinx_}"
UPGRADE_BASE_REF="${NANOPINO_UPGRADE_BASE_REF:-}"
BASE_ROOT="${RUNNER_TEMP:-/tmp}/nanopino-upgrade-base"
BASE_OUTPUT="${RUNNER_TEMP:-/tmp}/NanoPino-upgrade-base.pinx"
FAULT_ROOT="${RUNNER_TEMP:-/tmp}/nanopino-fault-injection"
FAULT_OUTPUT="${RUNNER_TEMP:-/tmp}/NanoPino-fault-injection.pinx"
FAULT_INJECTION="${NANOPINO_FAULT_INJECTION:-0}"
FAULT_MIGRATION="2099_12_31_235959_ci_fault_injection"

: "${DB_HOST:?DB_HOST is required}"
: "${DB_PORT:?DB_PORT is required}"
: "${DB_DATABASE:?DB_DATABASE is required}"
: "${DB_USERNAME:?DB_USERNAME is required}"
: "${DB_PASSWORD:?DB_PASSWORD is required}"
: "${NANOPINO_CI_ADMIN_PASSWORD:?NANOPINO_CI_ADMIN_PASSWORD is required}"

fail() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

cleanup() {
  if git -C "$ROOT" worktree list --porcelain 2>/dev/null | grep -Fq "worktree $BASE_ROOT"; then
    git -C "$ROOT" worktree remove --force "$BASE_ROOT" >/dev/null 2>&1 || true
  fi
  if git -C "$ROOT" worktree list --porcelain 2>/dev/null | grep -Fq "worktree $FAULT_ROOT"; then
    git -C "$ROOT" worktree remove --force "$FAULT_ROOT" >/dev/null 2>&1 || true
  fi
}
trap cleanup EXIT

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
    "prefix"=>getenv("DB_PREFIX") ?: "pinx_",
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

build_current() {
  cd "$ROOT"
  PHP_BIN="$PHP_BIN" "$ROOT/tools/release/build-pinx.sh" "$PINOX_ROOT" "$OUTPUT"
  [[ -s "$OUTPUT" ]] || fail "Current PINX output was not created."
}

build_upgrade_base() {
  [[ -n "$UPGRADE_BASE_REF" ]] || return 0
  rm -rf "$BASE_ROOT"
  git -C "$ROOT" worktree add --detach "$BASE_ROOT" "$UPGRADE_BASE_REF"
  (
    cd "$BASE_ROOT/payload/theme/cms-admin"
    npm ci
    npm run build
  )
  PHP_BIN="$PHP_BIN" "$BASE_ROOT/tools/release/build-pinx.sh" "$PINOX_ROOT" "$BASE_OUTPUT"
  [[ -s "$BASE_OUTPUT" ]] || fail "Upgrade-base PINX output was not created."
}

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

migration_record_count() {
  local migration="$1"
  "$PHP_BIN" -r '
  $pdo=new PDO(
    "mysql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT").";dbname=".getenv("DB_DATABASE").";charset=utf8mb4",
    getenv("DB_USERNAME"),
    getenv("DB_PASSWORD"),
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]
  );
  $table=(getenv("DB_PREFIX") ?: "pinx_")."history";
  $sql="SELECT COUNT(*) FROM `".$table."` WHERE type = ? AND app = ? AND migration = ?";
  $stmt=$pdo->prepare($sql);
  $stmt->execute(["migration","com_pinoox_cms",$argv[1]]);
  echo (int)$stmt->fetchColumn();
  ' "$migration"
}

expected_tables="$("$PHP_BIN" -r '
$m=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
echo (int)($m["cms"]["runtime_schema"]["required_tables"]??0);
' "$ROOT/manifest.json")"
[[ "$expected_tables" -gt 0 ]] || fail "Invalid required table count."

verify_installed_release() {
  local manifest="$1"
  "$PHP_BIN" -r '
  $app=require $argv[1];
  $manifest=json_decode(file_get_contents($argv[2]),true,512,JSON_THROW_ON_ERROR);
  if (($app["package"]??null)!==($manifest["package"]??null)) exit(2);
  if (($app["version-name"]??null)!==($manifest["version_name"]??null)) exit(3);
  if ((int)($app["version-code"]??0)!==(int)($manifest["version_code"]??0)) exit(4);
  printf("installed_version=%s code=%d minpin=%d\n",
      $app["version-name"],
      (int)$app["version-code"],
      (int)($app["minpin"]??0)
  );
  ' "$PINOX_ROOT/apps/$PACKAGE/app.php" "$manifest"
}

uninstall_and_assert_clean() {
  cd "$PINOX_ROOT"
  "$PHP_BIN" pinoox pinx:uninstall "$PACKAGE" --force --yes
  [[ ! -e "$PINOX_ROOT/apps/$PACKAGE" ]] || fail "App directory remains after uninstall."
  local remaining
  remaining="$(cms_table_count)"
  [[ "$remaining" -eq 0 ]] || fail "CMS tables remain after uninstall."
  printf 'uninstall_tables=%s\n' "$remaining"
}

build_fault_injection_package() {
  [[ "$FAULT_INJECTION" == "1" ]] || return 0

  rm -rf "$FAULT_ROOT"
  git -C "$ROOT" worktree add --detach "$FAULT_ROOT" HEAD

  rm -rf "$FAULT_ROOT/payload/theme/cms-admin/dist"
  cp -a "$ROOT/payload/theme/cms-admin/dist" "$FAULT_ROOT/payload/theme/cms-admin/dist"

  cat > "$FAULT_ROOT/payload/database/migrations/$FAULT_MIGRATION.php" <<'PHP'
<?php
declare(strict_types=1);

use Pinoox\Component\Migration\MigrationBase;

return new class extends MigrationBase
{
    public function up(): void
    {
        throw new RuntimeException('NanoPino R16 CI fault injection after normal schema migrations.');
    }

    public function down(): void
    {
        // The failing migration never completes and must never be recorded.
    }
};
PHP

  PHP_BIN="$PHP_BIN" "$FAULT_ROOT/tools/release/build-pinx.sh" "$PINOX_ROOT" "$FAULT_OUTPUT"
  [[ -s "$FAULT_OUTPUT" ]] || fail "Fault-injection PINX output was not created."
}

run_fault_injection_recovery() {
  [[ "$FAULT_INJECTION" == "1" ]] || return 0

  cd "$PINOX_ROOT"
  set +e
  fault_log="$("$PHP_BIN" pinoox pinx:install "$FAULT_OUTPUT" 2>&1)"
  fault_status=$?
  set -e
  printf '%s\n' "$fault_log"

  [[ "$fault_status" -ne 0 ]] || fail "Fault-injection PINX unexpectedly installed successfully."
  [[ "$fault_log" == *"NanoPino R16 CI fault injection"* ]] || fail "Fault-injection failure did not reach the intentional terminal migration."

  fault_tables="$(cms_table_count)"
  fault_preflight_records="$(migration_record_count '2026_09_01_000000_preflight_nanopino_environment')"
  fault_terminal_records="$(migration_record_count "$FAULT_MIGRATION")"
  fault_app_present=0
  [[ -e "$PINOX_ROOT/apps/$PACKAGE" ]] && fault_app_present=1

  [[ "$fault_terminal_records" -eq 0 ]] || fail "Failed migration was incorrectly recorded as successful."

  printf 'fault_install_exit=%s\n' "$fault_status"
  printf 'fault_app_present_before_recovery=%s\n' "$fault_app_present"
  printf 'fault_tables_before_recovery=%s\n' "$fault_tables"
  printf 'fault_preflight_records_before_recovery=%s\n' "$fault_preflight_records"
  printf 'fault_terminal_records=%s\n' "$fault_terminal_records"

  if [[ "$fault_app_present" -eq 0 && "$fault_tables" -eq 0 ]]; then
    printf 'fault_recovery_mode=automatic\n'
    return 0
  fi

  [[ "$fault_app_present" -eq 1 ]] || fail "Failed install left database state without a recoverable application directory."
  [[ "$fault_tables" -ge "$expected_tables" ]] || fail "Late fault did not preserve the expected partial schema evidence."
  [[ "$fault_preflight_records" -eq 1 ]] || fail "Preflight history is inconsistent after late migration failure."

  "$PHP_BIN" pinoox pinx:uninstall "$PACKAGE" --force --yes

  [[ ! -e "$PINOX_ROOT/apps/$PACKAGE" ]] || fail "Fault recovery left the application directory in place."
  recovered_tables="$(cms_table_count)"
  [[ "$recovered_tables" -eq 0 ]] || fail "Fault recovery left NanoPino CMS tables behind."

  recovered_preflight_records="$(migration_record_count '2026_09_01_000000_preflight_nanopino_environment')"
  [[ "$recovered_preflight_records" -eq 0 ]] || fail "Fault recovery left NanoPino migration history behind."

  printf 'fault_recovery_mode=native-uninstall\n'
  printf 'fault_tables_after_recovery=%s\n' "$recovered_tables"
  printf 'fault_preflight_records_after_recovery=%s\n' "$recovered_preflight_records"
  printf 'fault_injection_recovery=PASS\n'
}

build_upgrade_base
build_current

if [[ -n "$UPGRADE_BASE_REF" ]]; then
  cd "$PINOX_ROOT"
  "$PHP_BIN" pinoox pinx:install "$BASE_OUTPUT"
  verify_installed_release "$BASE_ROOT/manifest.json"

  base_tables="$(cms_table_count)"
  [[ "$base_tables" -ge "$expected_tables" ]] || fail "Upgrade-base install table count is below manifest requirement."
  printf 'upgrade_base_tables=%s\n' "$base_tables"

  base_version="$("$PHP_BIN" -r '$m=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); echo $m["version_name"];' "$BASE_ROOT/manifest.json")"
  current_version="$("$PHP_BIN" -r '$m=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); echo $m["version_name"];' "$ROOT/manifest.json")"

  "$PHP_BIN" pinoox pinx:install "$OUTPUT"
  verify_installed_release "$ROOT/manifest.json"

  updated_tables="$(cms_table_count)"
  [[ "$updated_tables" -eq "$base_tables" ]] || fail "Real version update changed CMS table count unexpectedly."

  preflight_records="$(migration_record_count '2026_09_01_000000_preflight_nanopino_environment')"
  [[ "$preflight_records" -eq 1 ]] || fail "Installability preflight migration was not recorded exactly once after update."

  printf 'upgrade_from_version=%s\n' "$base_version"
  printf 'upgrade_to_version=%s\n' "$current_version"
  printf 'upgrade_tables=%s\n' "$updated_tables"
  printf 'installability_preflight_records=%s\n' "$preflight_records"

  uninstall_and_assert_clean
fi

cd "$PINOX_ROOT"
"$PHP_BIN" pinoox pinx:install "$OUTPUT"
verify_installed_release "$ROOT/manifest.json"

fresh_tables="$(cms_table_count)"
[[ "$fresh_tables" -ge "$expected_tables" ]] || fail "Fresh install table count is below manifest requirement."
printf 'fresh_install_tables=%s\n' "$fresh_tables"

fresh_preflight_records="$(migration_record_count '2026_09_01_000000_preflight_nanopino_environment')"
[[ "$fresh_preflight_records" -eq 1 ]] || fail "Installability preflight migration was not recorded exactly once on fresh install."
printf 'fresh_installability_preflight_records=%s\n' "$fresh_preflight_records"

uninstall_and_assert_clean

build_fault_injection_package
run_fault_injection_recovery

printf 'pinoox_lifecycle=PASS pinx=%s\n' "$OUTPUT"

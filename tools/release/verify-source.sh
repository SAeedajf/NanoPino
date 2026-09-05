#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
ADMIN="$ROOT/payload/theme/cms-admin"

fail() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

command -v php >/dev/null || fail 'php is required'
command -v node >/dev/null || fail 'node is required'
command -v npm >/dev/null || fail 'npm is required'

if find "$ROOT" -type f \( -name '*.pinx' -o -name '*.zip' \) -not -path "$ROOT/.git/*" | grep -q .; then
  fail 'Packaged artifacts must not be stored in the source repository.'
fi

php -l "$ROOT/payload/app.php" >/dev/null
php -r '
$root=$argv[1];
$app=require $root."/payload/app.php";
$manifest=json_decode(file_get_contents($root."/manifest.json"),true,512,JSON_THROW_ON_ERROR);
$meta=json_decode(file_get_contents($root."/payload/resources/release/release-metadata-v1.json"),true,512,JSON_THROW_ON_ERROR);
$errors=[];
if (($manifest["version_name"]??null)!==($app["version-name"]??null)) $errors[]="manifest version_name mismatch";
if ((int)($manifest["version_code"]??0)!==(int)($app["version-code"]??0)) $errors[]="manifest version_code mismatch";
if (($meta["version_name"]??null)!==($app["version-name"]??null)) $errors[]="release metadata version_name mismatch";
if ((int)($meta["version_code"]??0)!==(int)($app["version-code"]??0)) $errors[]="release metadata version_code mismatch";
if (($meta["app_sha256"]??null)!==hash_file("sha256",$root."/payload/app.php")) $errors[]="release metadata app_sha256 mismatch";
if (($app["package"]??null)!=="com_pinoox_cms") $errors[]="unexpected package id";
if ($errors) {fwrite(STDERR,implode(PHP_EOL,$errors).PHP_EOL); exit(1);}
printf("release_metadata=PASS version=%s code=%d\n",$app["version-name"],$app["version-code"]);
' "$ROOT"

python3 - "$ROOT/payload" <<'PY'
from pathlib import Path
import subprocess, sys
root=Path(sys.argv[1])
files=list(root.rglob('*.php'))
bad=[]
for p in files:
    r=subprocess.run(['php','-l',str(p)],capture_output=True,text=True)
    if r.returncode: bad.append((p,(r.stdout+r.stderr).strip()))
print(f'php_lint={len(files)-len(bad)}/{len(files)}')
for p,e in bad[:20]: print(p,e,file=sys.stderr)
sys.exit(1 if bad else 0)
PY

cd "$ADMIN"
npm test
node verify-dist.mjs

printf 'source_verification=PASS\n'

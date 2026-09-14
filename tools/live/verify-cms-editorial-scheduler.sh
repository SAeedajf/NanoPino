#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
WORK_DIR="$(mktemp -d /tmp/nanopino-cms-editorial.XXXXXX)"
trap 'rm -rf -- "$WORK_DIR"' EXIT

export DB_CONNECTION=devdb
export APP_ENV=development
export PINOOX_TESTING=1
export DEVDB_PATH="$WORK_DIR"

cd "$ROOT_DIR"
php pinoox migrate --devdb --fresh com_pinoox_cms >/tmp/nanopino-phase13-editorial-migrate.log

php <<'PHP'
<?php
declare(strict_types=1);

require 'vendor/autoload.php';

use App\com_pinoox_cms\Model\ContentModel;
use App\com_pinoox_cms\Cms\Content\ContentStatus;
use Pinoox\Support\SystemConfig;

SystemConfig::clearCache();
$now = time();
ContentModel::create([
    'site_id' => 1,
    'type' => 'post',
    'status' => ContentStatus::Scheduled->value,
    'title' => 'Phase 13 due fixture',
    'slug' => 'phase13-due-fixture',
    'excerpt' => '',
    'author_id' => null,
    'parent_id' => null,
    'locale' => 'fa',
    'document' => ['content' => '<p>Due</p>'],
    'metadata' => [],
    'scheduled_at' => gmdate('Y-m-d H:i:s', $now - 120),
]);
ContentModel::create([
    'site_id' => 1,
    'type' => 'post',
    'status' => ContentStatus::Scheduled->value,
    'title' => 'Phase 13 future fixture',
    'slug' => 'phase13-future-fixture',
    'excerpt' => '',
    'author_id' => null,
    'parent_id' => null,
    'locale' => 'fa',
    'document' => ['content' => '<p>Future</p>'],
    'metadata' => [],
    'scheduled_at' => gmdate('Y-m-d H:i:s', $now + 3600),
]);
PHP

php <<'PHP'
<?php
declare(strict_types=1);

require 'vendor/autoload.php';

use App\com_pinoox_cms\Cms\Content\ContentStatus;
use App\com_pinoox_cms\Cms\Database\CmsDatabase;
use App\com_pinoox_cms\Cms\Runtime\CmsRuntimeServices;
use App\com_pinoox_cms\Model\ContentModel;
use App\com_pinoox_cms\Model\ContentRevisionModel;
use Pinoox\Support\SystemConfig;

SystemConfig::clearCache();
$first = CmsRuntimeServices::content()->publishDue(100, new DateTimeImmutable('now'), 'phase13-live-probe');
$second = CmsRuntimeServices::content()->publishDue(100, new DateTimeImmutable('now'), 'phase13-live-probe-repeat');
$due = ContentModel::query()->where('slug', 'phase13-due-fixture')->first();
$future = ContentModel::query()->where('slug', 'phase13-future-fixture')->first();
$revisions = $due === null ? 0 : ContentRevisionModel::query()
    ->where('content_id', (int) $due->id)
    ->where('kind', 'published')
    ->count();
$audits = $due === null ? 0 : CmsDatabase::table('audit_events')
    ->where('action', 'content.scheduled_publish')
    ->where('target_id', (string) $due->id)
    ->count();

if (count($first) !== 1 || $second !== []) {
    fwrite(STDERR, "FAIL scheduled publication was not idempotent\n");
    exit(1);
}
if ($due?->status !== ContentStatus::Published->value || $due?->scheduled_at !== null) {
    fwrite(STDERR, "FAIL due fixture did not become published\n");
    exit(1);
}
if ($future?->status !== ContentStatus::Scheduled->value || $revisions !== 1 || $audits !== 1) {
    fwrite(STDERR, "FAIL scheduler status or revision/audit evidence is invalid\n");
    exit(1);
}

echo "PASS scheduled due=1 repeat=0 future=scheduled revision=1 audit=1\n";
PHP

printf 'CMS editorial scheduler probe passed in isolated DevDB: %s\n' "$WORK_DIR"

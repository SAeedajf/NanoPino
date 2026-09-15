<?php

declare(strict_types=1);

use App\com_pinoox_cms\Controller\AdminController;
use App\com_pinoox_cms\Controller\AdminFrontendStatusController;
use App\com_pinoox_cms\Controller\AdminRuntimeModuleController;
use App\com_pinoox_cms\Controller\ExtensionAdminAssetController;
use App\com_pinoox_cms\Controller\PublicContentController;
use function Pinoox\Router\get;

get('/site', [PublicContentController::class, 'site'])
    ->name('cms.public.site');

get('/page/{slug}', [PublicContentController::class, 'page'])
    ->name('cms.public.page');

get('/post/{slug}', [PublicContentController::class, 'post'])
    ->name('cms.public.post');

// These are real HTML5-history entrypoints for the authenticated Admin SPA.
// They must precede the generic public taxonomy route, otherwise
// /appearance/site-editor and /extensions/updates are interpreted as a
// public taxonomy request and fail before Vue can boot.
get('/appearance/site-editor', [AdminController::class, 'index'])
    ->permission('cms.admin')
    ->name('cms.admin.site-editor-entry');

get('/extensions/updates', [AdminController::class, 'index'])
    ->permission('cms.admin')
    ->name('cms.admin.updates-entry');

get('/{taxonomy}/{termSlug}', [PublicContentController::class, 'taxonomy'])
    ->filters(['taxonomy' => '[a-z][a-z0-9_-]{1,63}', 'termSlug' => '[^/]{1,160}'])
    ->name('cms.public.taxonomy');

get('/__cms/health/frontend', [AdminFrontendStatusController::class, 'index'])
    ->permission('cms.admin')
    ->name('cms.admin.frontend-status');


get('/__cms/admin/modules/{asset}', [AdminRuntimeModuleController::class, 'module'])
    ->filters(['asset' => '[a-z][a-z0-9-]{0,60}\.mjs'])
    ->permission('cms.admin')
    ->name('cms.admin.control-plane-module');

get('/__cms/extensions/{package}/admin/{asset}', [ExtensionAdminAssetController::class, 'module'])
    ->filters([
        'package' => 'com_[a-z0-9][a-z0-9_]*',
        'asset' => '[a-z][a-z0-9._-]{0,80}\\.mjs',
    ])
    ->permission('cms.admin')
    ->name('cms.admin.extension-module');

// The admin frontend is an HTML5-history SPA. Use an explicit catch-all
// parameter so direct loads and browser refreshes keep working on nested
// routes such as /extensions/updates and /appearance/site-editor.
get('/{path*}', [AdminController::class, 'index'])
    ->filters(['path' => '.+'])
    ->permission('cms.admin')
    ->name('cms.admin');

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

get('*', [AdminController::class, 'index'])
    ->permission('cms.admin')
    ->name('cms.admin');

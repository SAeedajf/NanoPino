<?php

declare(strict_types=1);

use App\com_pinoox_cms\Controller\AdminController;
use App\com_pinoox_cms\Controller\AdminFrontendStatusController;
use App\com_pinoox_cms\Controller\AdminRuntimeModuleController;
use App\com_pinoox_cms\Controller\ExtensionAdminAssetController;
use function Pinoox\Router\get;

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

<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Controller;

use App\com_pinoox_cms\Cms\Extension\ExtensionDefinition;
use App\com_pinoox_cms\Cms\Support\CmsRelease;
use App\com_pinoox_cms\Cms\Health\HealthCheckDefinition;
use App\com_pinoox_cms\Cms\Admin\Frontend\AdminFrontendAssetProbe;
use App\com_pinoox_cms\Cms\Admin\Frontend\AdminFrontendResponseFactory;
use App\com_pinoox_cms\Cms\Admin\AdminComponentDefinition;
use App\com_pinoox_cms\Cms\Admin\AdminRuntimeUrl;
use App\com_pinoox_cms\Cms\Admin\AdminI18n;
use App\com_pinoox_cms\Cms\Kernel\CmsKernel;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationManager;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationRequest;
use App\com_pinoox_cms\Cms\Authorization\SingleSiteScopeGuard;
use App\com_pinoox_cms\Cms\Authorization\PinooxAccessGateway;
use App\com_pinoox_cms\Cms\Identity\PinooxIdentityRepository;
use App\com_pinoox_cms\Cms\Audit\PinooxAuditRepository;
use Pinoox\Component\Kernel\Controller\Controller;
use Pinoox\Portal\View;
use App\com_pinoox_cms\Cms\Api\V1\Builder\BuilderApiContract;
use App\com_pinoox_cms\Cms\Api\V1\Extension\ExtensionApiContract;
use App\com_pinoox_cms\Cms\Api\V1\Update\UpdateApiContract;
use App\com_pinoox_cms\Cms\Api\V1\Recovery\RecoveryApiContract;
use App\com_pinoox_cms\Cms\Api\V1\Infrastructure\InfrastructureApiContract;
use App\com_pinoox_cms\Cms\Api\V1\Search\SearchApiContract;
use App\com_pinoox_cms\Cms\Api\V1\Security\SecurityApiContract;
use App\com_pinoox_cms\Cms\Api\V1\Performance\PerformanceApiContract;
use App\com_pinoox_cms\Cms\Api\V1\SystemHealth\SystemHealthApiContract;
use App\com_pinoox_cms\Cms\Compatibility\KernelCompatibilityChecker;
use App\com_pinoox_cms\Cms\Compatibility\RuntimeKernelInfoResolver;
use App\com_pinoox_cms\Cms\Health\HealthRunner;
use App\com_pinoox_cms\Cms\Health\SystemHealthRegistrar;
use App\com_pinoox_cms\Cms\Logging\FileCmsLogger;
use App\com_pinoox_cms\Cms\Recovery\SafeModeManager;
use Pinoox\Support\SystemConfig;
use App\com_pinoox_cms\Cms\Performance\PerformanceBudgetDefinition;
use App\com_pinoox_cms\Cms\Security\Posture\SecurityPostureService;
use App\com_pinoox_cms\Cms\Security\Posture\SecurityRuntimeState;
use App\com_pinoox_cms\Cms\Security\Http\PinooxSessionCsrfTokenManager;
use App\com_pinoox_cms\Cms\Runtime\CmsRuntimeServices;
use App\com_pinoox_cms\Cms\Runtime\RuntimeBindingState;
use App\com_pinoox_cms\Cms\Security\RateLimit\CoreRateLimitProfiles;
use App\com_pinoox_cms\Cms\Security\Http\CoreApiSecurityMatrix;
use App\com_pinoox_cms\Cms\Driver\DriverDefinition;

final class AdminController extends Controller
{
    public function index()
    {
        $adminThemePath = dirname(__DIR__) . '/theme/cms-admin';
        $adminProbe = new AdminFrontendAssetProbe();
        $adminFrontend = $adminProbe->probe($adminThemePath);
        $adminResponses = new AdminFrontendResponseFactory();

        // Frontend deployment failure must remain diagnosable even if CMS DB/Identity
        // or a third-party Extension has a separate boot/runtime failure.
        if (!$adminFrontend->ready) {
            return $adminResponses->failure($adminFrontend);
        }

        $kernel = CmsKernel::instance();

        if ($kernel->healthChecks->get('admin.frontend_assets') === null) {
            $kernel->healthChecks->register(new HealthCheckDefinition(
                'admin.frontend_assets',
                'cms.core',
                static function () use ($adminThemePath): array {
                    $status=(new AdminFrontendAssetProbe())->probe($adminThemePath);
                    return [
                        'status'=>$status->ready ? 'ok' : 'error',
                        'message'=>$status->message,
                        'details'=>[
                            'mode'=>$status->mode,
                            'code'=>$status->code,
                            'entry'=>$status->entry,
                            'manifest'=>$status->manifest,
                            'asset_count'=>count($status->assets),
                            'build_id'=>$status->buildId,
                        ],
                    ];
                },
            ));
        }

        $runtimeActorId=CmsRuntimeServices::actorId();
        $authorization = new AuthorizationManager(
            $kernel->capabilities,
            $kernel->policies,
            new PinooxAccessGateway(),
            new SingleSiteScopeGuard(1,$runtimeActorId),
        );

        $adminMountPath = AdminRuntimeUrl::currentMountPath();
        $runtimeApiBase = AdminRuntimeUrl::apiBase($adminMountPath);

        // RC control-plane compatibility modules override the historical read-only
        // compiled pages without weakening the same-origin module boundary. The canonical
        // Vue SFC sources remain the target for the next full Vite build.
        foreach ([
            'core:dashboard' => 'dashboard.mjs',
            'core:content' => 'content.mjs',
            'core:revisions' => 'revisions.mjs',
            'core:media' => 'media.mjs',
            'core:settings' => 'settings.mjs',
            'core:system' => 'system.mjs',
            'core:logs' => 'logs.mjs',
            'core:appearance' => 'appearance.mjs',
            'core:blocks' => 'blocks.mjs',
            'core:users' => 'users.mjs',
            'core:extensions' => 'extensions.mjs',
            'core:updates' => 'updates.mjs',
            'core:recovery' => 'recovery.mjs',
            'core:builder' => 'builder.mjs',
            'core:site-editor' => 'site-editor.mjs',
        ] as $componentId => $asset) {
            if (!$kernel->admin->components->has($componentId)) {
                $kernel->admin->components->register(new AdminComponentDefinition(
                    $componentId,
                    'cms.core',
                    AdminRuntimeUrl::controlPlaneModule($asset, $adminMountPath),
                ));
            }
        }

        $manifest = $kernel->admin->manifest(
            static fn (?string $permission): bool =>
                $permission === null || $authorization->can(new AuthorizationRequest($permission))
        );
        $manifestData = $manifest->toArray();
        foreach ($manifestData['components'] as &$componentRow) {
            if (is_array($componentRow) && isset($componentRow['moduleUrl']) && is_string($componentRow['moduleUrl'])) {
                $componentRow['moduleUrl'] = AdminRuntimeUrl::rebase($componentRow['moduleUrl'], $adminMountPath);
            }
        }
        unset($componentRow);

        $identity = new PinooxIdentityRepository();
        try {
            $currentUser = $identity->currentUser();
            $users = $authorization->can(new AuthorizationRequest('users.read'))
                ? $identity->users(100)
                : [];
            $roles = $authorization->can(new AuthorizationRequest('users.read'))
                ? $identity->roles()
                : [];
        } catch (\Throwable) {
            $currentUser = null;
            $users = [];
            $roles = [];
        }

        $csrfToken=null;
        if($runtimeActorId!==null){
            try{$csrfToken=(new PinooxSessionCsrfTokenManager())->issue($runtimeActorId);}catch(\Throwable){$csrfToken=null;}
        }

        $roleTemplates = array_map(
            static fn ($template): array => [
                'key' => $template->identifier(),
                'name' => $template->name,
                'description' => $template->description,
                'capabilities' => $template->capabilities,
            ],
            $kernel->roleTemplates->definitions(),
        );



        $contentTypeDefinitions = array_map(
            static fn ($definition): array => [
                'key' => $definition->identifier(),
                'owner' => $definition->owner(),
                'label' => $definition->label,
                'singularLabel' => $definition->singularLabel,
                'fields' => $definition->fields,
                'taxonomies' => $definition->taxonomies,
                'hierarchical' => $definition->hierarchical,
                'revisions' => $definition->revisions,
                'permissions' => $definition->permissions,
                'rest' => $definition->rest,
                'search' => $definition->search,
                'editor' => $definition->editor,
            ],
            $kernel->contentTypes->definitions(),
        );

        $fieldDefinitions = array_map(
            static fn ($definition): array => [
                'key' => $definition->identifier(),
                'owner' => $definition->owner(),
                'type' => $definition->type->value,
                'label' => $definition->label,
                'storage' => $definition->storage->value,
                'required' => $definition->required,
                'multiple' => $definition->multiple,
                'translatable' => $definition->translatable,
                'options' => $definition->options,
                'ui' => $definition->ui,
            ],
            $kernel->fields->definitions(),
        );

        $builderDataSources = array_map(
            static fn ($definition): array => [
                'id' => $definition->identifier(),
                'owner' => $definition->owner(),
                'label' => $definition->label,
                'serverOnly' => $definition->serverOnly,
                'capabilities' => $definition->capabilities,
            ],
            $kernel->builderDataSources->all(),
        );

        $blockDefinitions = array_map(
            static fn ($definition): array => [
                'id' => $definition->identifier(),
                'owner' => $definition->owner(),
                'name' => $definition->name,
                'title' => $definition->title,
                'category' => $definition->category,
                'icon' => $definition->icon,
                'version' => $definition->version,
                'schemaVersion' => $definition->schemaVersion,
                'attributes' => array_map(
                    static fn ($attribute): array => [
                        'name' => $attribute->name,
                        'type' => $attribute->type->value,
                        'required' => $attribute->required,
                        'default' => $attribute->default,
                        'rules' => $attribute->rules,
                    ],
                    $definition->attributes,
                ),
                'supports' => $definition->supports,
                'allowsChildren' => $definition->allowsChildren,
                'allowedChildren' => $definition->allowedChildren,
                'slots' => $definition->slots,
                'permissions' => $definition->permissions,
                'editor' => $definition->editor,
                'renderer' => $definition->renderer,
            ],
            $kernel->blocks->definitions(),
        );

        $taxonomyDefinitions = array_map(
            static fn ($definition): array => [
                'key' => $definition->identifier(),
                'owner' => $definition->owner(),
                'label' => $definition->label,
                'singularLabel' => $definition->singularLabel,
                'hierarchical' => $definition->hierarchical,
                'contentTypes' => $definition->contentTypes,
                'permissions' => $definition->permissions,
                'public' => $definition->public,
            ],
            $kernel->taxonomies->definitions(),
        );

        $settingDefinitions = array_map(
            static fn ($definition): array => [
                'key' => $definition->identifier(),
                'owner' => $definition->owner(),
                'type' => $definition->type->value,
                'default' => $definition->sensitive ? '[REDACTED]' : $definition->default,
                'scopes' => $definition->scopeNames(),
                'readPermission' => $definition->readPermission,
                'writePermission' => $definition->writePermission,
                'group' => $definition->group,
                'label' => $definition->label,
                'ui' => $definition->ui,
                'sensitive' => $definition->sensitive,
            ],
            $kernel->settings->definitions(),
        );

        $auditEvents = [];
        if ($authorization->can(new AuthorizationRequest('audit.read'))) {
            try {
                $auditEvents = array_map(
                    static fn ($event): array => [
                        'id' => $event->id,
                        'action' => $event->action,
                        'owner' => $event->owner,
                        'outcome' => $event->outcome->value,
                        'actorId' => $event->actorId,
                        'scopeType' => $event->scopeType->value,
                        'scopeId' => $event->scopeId,
                        'targetType' => $event->targetType,
                        'targetId' => $event->targetId,
                        'correlationId' => $event->correlationId,
                        'metadata' => $event->metadata,
                        'occurredAt' => $event->occurredAt,
                    ],
                    (new PinooxAuditRepository())->recent(100),
                );
            } catch (\Throwable) {
                // Tables may not exist before the Phase 8 migration runs.
                $auditEvents = [];
            }
        }

        $driverDefinitions = array_map(
            static fn (DriverDefinition $definition): array => [
                'id' => $definition->identifier(),
                'owner' => $definition->owner(),
                'kind' => $definition->kind->value,
                'label' => $definition->label,
                'metadata' => $definition->metadata,
            ],
            array_values(array_filter(
                $kernel->drivers->all(),
                static fn ($definition): bool => $definition instanceof DriverDefinition,
            )),
        );

        $performanceBudgets = array_map(
            static fn (PerformanceBudgetDefinition $budget): array => [
                'id' => $budget->identifier(),
                'owner' => $budget->owner(),
                'label' => $budget->label,
                'metric' => $budget->metric->value,
                'target' => $budget->target,
                'limit' => $budget->limit,
                'higherIsBetter' => $budget->higherIsBetter,
                'unit' => $budget->unit,
                'profile' => $budget->profile,
                'value' => null,
                'status' => 'unmeasured',
            ],
            $kernel->performanceBudgets->forProfile('shared_hosting'),
        );

        $runtimeKernel = (new RuntimeKernelInfoResolver())->resolve();
        $kernelCompatibility = (new KernelCompatibilityChecker())->check(
            $runtimeKernel->code,
            $runtimeKernel->version,
        );

        try {
            $cmsStoragePath = rtrim(SystemConfig::path('storage'), '/\\') . '/cms';
        } catch (\Throwable) {
            $cmsStoragePath = sys_get_temp_dir() . '/pinoox-cms';
        }

        foreach (['system.php','system.memory','system.disk','system.kernel_compatibility'] as $coreHealthId) {
            if ($kernel->healthChecks->get($coreHealthId) === null) {
                // Register the group once. The first missing id means the group has not been wired yet.
                (new SystemHealthRegistrar(
                    $cmsStoragePath,
                    $runtimeKernel->code,
                    $runtimeKernel->version,
                    count($kernel->extensions->all()),
                ))->register($kernel->healthChecks);
                break;
            }
        }

        $healthRunner = new HealthRunner($kernel->healthChecks);
        $healthResults = $healthRunner->runAll();
        $healthRows = array_map(
            static fn ($result): array => [
                'id' => $result->id,
                'label' => $result->id,
                'status' => $result->status->value,
                'message' => $result->message,
                'durationMs' => $result->durationMs,
                'details' => $result->details,
            ],
            $healthResults,
        );

        $logger = new FileCmsLogger($cmsStoragePath . '/logs/cms.jsonl');
        $logRows = array_map(
            static fn ($record): array => $record->toArray(),
            $logger->tail(50),
        );

        $safeModeManager = new SafeModeManager($cmsStoragePath . '/recovery/safe-mode.json');
        $safeModeState = $safeModeManager->state()->toArray();

        $securityRuntimeState=new SecurityRuntimeState(
            csrfVerifierBound:RuntimeBindingState::csrf()&&$csrfToken!==null,
            rateLimitsRegistered:RuntimeBindingState::rateLimits(),
            securityHeadersBound:RuntimeBindingState::headers(),
            ssrfTransportBound:false,
            publicApiSecurityBound:RuntimeBindingState::api(),
            cspEnforced:false,
        );
        $securityPosture=(new SecurityPostureService($securityRuntimeState))->report()->toArray();
        $securityRateLimits = array_map(
            static fn ($profile): array => [
                'name' => $profile->name,
                'maxAttempts' => $profile->maxAttempts,
                'decaySeconds' => $profile->decaySeconds,
                'keyStrategy' => $profile->keyStrategy,
            ],
            CoreRateLimitProfiles::all(),
        );
        $apiSecurityMatrix = array_map(
            static fn ($requirement): array => [
                'scope' => $requirement->scope,
                'rateLimit' => $requirement->rateLimit,
                'sessionMutationRequiresCsrf' => $requirement->sessionMutationRequiresCsrf,
                'siteScopeRequired' => $requirement->siteScopeRequired,
            ],
            CoreApiSecurityMatrix::all(),
        );

        $mediaAssets=[];
        try{
            if($authorization->can(new AuthorizationRequest('media.read',$runtimeActorId,\App\com_pinoox_cms\Cms\Authorization\ScopeType::Site,1))){
                $mediaAssets=array_map(static fn($a):array=>$a->toArray(),CmsRuntimeServices::media()->search(1,null,null,100,$runtimeActorId));
            }
        }catch(\Throwable){$mediaAssets=[];}

        try {
            CmsRuntimeServices::syncInstalledExtensions();
            $extensions = CmsRuntimeServices::extensionCenter()->list(
                $runtimeActorId,
                (new SafeModeManager($cmsStoragePath . '/recovery/safe-mode.json'))->state(),
            );
        } catch (\Throwable) {
            $extensions = [];
            foreach ($kernel->extensions->all() as $definition) {
                if (!$definition instanceof ExtensionDefinition) continue;
                $extensions[] = [
                    'id'=>$definition->identifier(),'name'=>$definition->package(),
                    'type'=>$definition->type()->value,'version'=>$definition->version(),
                    'publisher'=>$definition->publisher(),'status'=>'installed',
                    'updateAvailable'=>false,'availableVersion'=>null,'trust'=>null,
                    'problems'=>[],'developerMode'=>false,
                ];
            }
        }

        $themeRows = [];
        $activeThemesByPackage = [];
        try {
            $nativeThemes = new \App\com_pinoox_cms\Cms\Theme\PinooxNativeThemeGateway();
            foreach (CmsRuntimeServices::discoverThemes() as $themeDefinition) {
                $themeRows[] = $themeDefinition->toArray();
                if (!isset($activeThemesByPackage[$themeDefinition->package])) {
                    try {
                        $activeThemesByPackage[$themeDefinition->package] =
                            $nativeThemes->stack($themeDefinition->package)->activeName;
                    } catch (\Throwable) {}
                }
            }
        } catch (\Throwable) {}


        $adminLocale = (string) \Pinoox\Portal\Lang::getLocale();
        if ($adminLocale === '') {
            $adminLocale = 'fa';
        }
        $adminDirection = AdminI18n::direction($adminLocale);

        $viewData = [
            'adminFrontend' => $adminFrontend->toArray(),
            'release' => [
                'version' => CmsRelease::version(),
                'versionCode' => CmsRelease::versionCode(),
            ],
            'bootstrap' => [
                'locale' => $adminLocale,
                'direction' => $adminDirection,
                'csrf' => $csrfToken,
                'cmsAdmin' => [
                    'brand' => [
                        'name' => AdminI18n::text('brand.name', locale: $adminLocale),
                        'subtitle' => AdminI18n::text('brand.subtitle', locale: $adminLocale),
                    ],
                    'i18n' => [
                        'locale' => $adminLocale,
                        'fallbackLocale' => AdminI18n::FALLBACK_LOCALE,
                        'direction' => $adminDirection,
                        'messages' => AdminI18n::catalog($adminLocale),
                    ],
                    'manifest' => $manifestData,
                    'mountPath' => $adminMountPath,
                    'apiBase' => $runtimeApiBase,
                    'frontend' => $adminFrontend->toArray(),
                    'data' => [
                        'extensions' => $extensions,
                        'extensionCenter' => [
                            'api' => ExtensionApiContract::routes(),
                            'apiBound' => RuntimeBindingState::api(),
                            'marketplace' => [
                                'connected' => false,
                                'providers' => [],
                            ],
                            'upload' => [
                                'maxBytes' => 104857600,
                                'accept' => ['.pinx', '.zip'],
                            ],
                            'installFlow' => [
                                'upload',
                                'inspect',
                                'trust',
                                'compatibility',
                                'dependencies',
                                'permissions',
                                'snapshot',
                                'staging',
                                'migration',
                                'install',
                                'health',
                                'activate',
                            ],
                            'trustNotice' => 'Cryptographic verification confirms integrity/authenticity, not code safety.',
                        ],
                        'updateCenter' => [
                            'api' => UpdateApiContract::routes(),
                            'apiBound' => RuntimeBindingState::api(),
                            'channels' => ['stable', 'beta', 'development'],
                            'autoUpdateModes' => ['disabled', 'security_only', 'patch_only', 'enabled'],
                            'policies' => [],
                            'history' => [],
                            'retention' => [
                                'maxPerExtension' => 5,
                                'maxAgeDays' => 30,
                                'minimumReadyPoints' => 1,
                            ],
                            'boundary' => [
                                'extension' => 'CMS transactional update + PINX',
                                'platform' => 'PlatformUpdater / Pinroll deployment boundary',
                            ],
                        ],
                        'recoveryCenter' => [
                            'api' => RecoveryApiContract::routes(),
                            'apiBound' => RuntimeBindingState::api(),
                            'safeModeExitRequiresHealth' => true,
                            'uninstallSnapshotRequired' => true,
                            'repairSnapshotRequired' => true,
                        ],
                        'infrastructure' => [
                            'api' => InfrastructureApiContract::routes(),
                            'apiBound' => RuntimeBindingState::api(),
                            'drivers' => $driverDefinitions,
                            'cacheLayers' => ['object','query','page','api','builder_render'],
                            'search' => [
                                'active' => 'search.database',
                                'fallback' => 'search.database',
                                'remote' => ['search.meilisearch','search.typesense'],
                                'api' => SearchApiContract::routes(),
                                'apiBound' => RuntimeBindingState::api(),
                            ],
                            'cache' => [
                                'active' => 'cache.pinoox',
                                'nativeStores' => ['file','redis'],
                                'tagInvalidation' => 'generation-clock',
                            ],
                            'queue' => [
                                'active' => 'queue.file',
                                'mode' => 'auto',
                                'sharedHostingFallback' => 'sync',
                                'stats' => [],
                            ],
                            'storage' => [
                                'active' => 'storage.pinoox',
                                'nativeDrivers' => ['local','ftp','sftp','s3','custom'],
                            ],
                            'scheduler' => [
                                'provider' => 'Pinoox ScheduleRegistry',
                                'role' => 'trigger-only',
                                'queueDrain' => 'everyMinute + withoutOverlapping',
                            ],
                        ],
                        'developerSdk' => [
                            'version' => 'v1',
                            'minimum' => [
                                'php' => '>=8.2',
                                'pincore' => '>=3.8.15',
                                'kernelCode' => 205,
                                'cms' => '>=0.21.0',
                                'luma' => '>=0.4.10',
                            ],
                            'extensionTypes' => [
                                'module','plugin','integration','theme','admin-extension',
                                'block','block-package','driver','language-pack',
                            ],
                            'registries' => [
                                'capability','settings','field','taxonomy','content_type','filter',
                                'theme','template_rule','block','block_migration','block_renderer',
                                'driver','ability','admin_component','admin_menu','admin_route','admin_widget','admin_panel',
                            ],
                            'nativeRegistration' => [
                                'apiRoute','action','listen','schedule','when',
                                'onRoute','onApi','onPath','onAction','onController','onModel','onTheme',
                            ],
                            'apiBase' => '/api/v1/extensions/{package}',
                            'starters' => [],
                            'packageValidator' => true,
                            'testHarness' => true,
                            'coreEdits' => false,
                        ],
                        'healthCenter' => [
                            'api' => SystemHealthApiContract::routes(),
                            'apiBound' => RuntimeBindingState::api(),
                            'overall' => $healthRunner->overall($healthResults)->value,
                            'historyBound' => RuntimeBindingState::api(),
                            'logsBound' => true,
                            'supportBundleBound' => RuntimeBindingState::api(),
                            'checks' => $healthRows,
                            'logs' => $logRows,
                            'kernel' => [
                                'runtime' => $runtimeKernel->toArray(),
                                'compatibility' => $kernelCompatibility->toArray(),
                            ],
                        ],
                        'performanceCenter' => [
                            'api' => PerformanceApiContract::routes(),
                            'apiBound' => RuntimeBindingState::api(),
                            'profile' => 'shared_hosting',
                            'budgets' => $performanceBudgets,
                            'metrics' => [],
                            'queryProbeBound' => false,
                            'cacheTelemetryBound' => false,
                            'extensionCostBound' => false,
                            'runtimeProfilerBound' => false,
                            'benchmarks' => [],
                            'query' => [
                                'count' => null,
                                'totalMs' => null,
                                'nPlusOne' => [],
                                'slow' => [],
                            ],
                            'cache' => [
                                'hits' => null,
                                'misses' => null,
                                'hitRatio' => null,
                            ],
                            'memory' => [
                                'peakBytes' => null,
                            ],
                            'sharedHosting' => [
                                'workerRequired' => false,
                                'queueFallback' => 'sync',
                                'externalSearchRequired' => false,
                                'defaultCache' => 'Pinoox File/Redis',
                            ],
                        ],
                        'securityCenter' => [
                            'api' => SecurityApiContract::routes(),
                            'apiBound' => RuntimeBindingState::api(),
                            'posture' => $securityPosture,
                            'cspMode' => 'report-only',
                            'headersBound' => $securityRuntimeState->securityHeadersBound,
                            'csrfBound' => $securityRuntimeState->csrfVerifierBound,
                            'rateLimitsRegistered' => $securityRuntimeState->rateLimitsRegistered,
                            'ssrfTransportBound' => false,
                            'rateLimits' => $securityRateLimits,
                            'apiMatrix' => $apiSecurityMatrix,
                            'native' => [
                                'rateLimiter' => 'Pinoox RateLimiter + ThrottleFlow',
                                'responseEvent' => 'Pinoox AppResponseEvent',
                            ],
                        ],
                        'users' => $users,
                        'roles' => $roles,
                        'roleTemplates' => $roleTemplates,
                        'currentUser' => $currentUser,
                        'settingDefinitions' => $settingDefinitions,
                        'runtimeApi' => [
                            'bound' => RuntimeBindingState::api(),
                            'base' => $runtimeApiBase,
                            'content' => $runtimeApiBase . '/content',
                            'media' => $runtimeApiBase . '/media',
                            'settings' => $runtimeApiBase . '/settings',
                            'users' => $runtimeApiBase . '/users',
                            'themes' => $runtimeApiBase . '/themes',
                            'builder' => $runtimeApiBase . '/builder',
                            'extensions' => $runtimeApiBase . '/extensions',
                            'recovery' => $runtimeApiBase . '/recovery',
                            'health' => $runtimeApiBase . '/system/health',
                            'logs' => $runtimeApiBase . '/system/logs',
                            'supportBundle' => $runtimeApiBase . '/system/support-bundle',
                            'security' => $runtimeApiBase . '/system/security',
                        ],
                        'contentTypeDefinitions' => $contentTypeDefinitions,
                        'fieldDefinitions' => $fieldDefinitions,
                        'taxonomyDefinitions' => $taxonomyDefinitions,
                        'blockDefinitions' => $blockDefinitions,
                        'builderDataSources' => $builderDataSources,
                        'builder' => [
                            'documents' => [],
                            'activeDocument' => null,
                            'apiBound' => RuntimeBindingState::api(),
                            'apiBase' => $runtimeApiBase . '/builder',
                            'viewports' => ['desktop','tablet','mobile'],
                            'panels' => ['blocks','layers','inspector','history'],
                            'historyLimit' => 100,
                        ],
                        'auditEvents' => $auditEvents,
                        'mediaAssets' => $mediaAssets,
                        'themes' => $themeRows,
                        'appearance' => [
                            'active' => null,
                            'activeByPackage' => $activeThemesByPackage,
                            'stack' => [],
                            'contexts' => [],
                            'templateKinds' => ['home','page','single','archive','taxonomy','search','404','part'],
                            'designRoots' => ['colors','typography','fontFamilies','fontSizes','spacing','containers','breakpoints','grid','radius','shadows','buttons','forms','links','motion','icons','darkMode','rtl','accessibility'],
                        ],
                        'fullSiteEditor' => [
                            'templateKinds' => ['index','home','page','single','archive','taxonomy','search','404'],
                            'templateParts' => ['header','footer'],
                            'breakpointFallbacks' => [
                                ['id' => 'sm', 'minWidth' => '36rem'],
                                ['id' => 'md', 'minWidth' => '48rem'],
                                ['id' => 'lg', 'minWidth' => '64rem'],
                                ['id' => 'xl', 'minWidth' => '80rem'],
                            ],
                            'patterns' => [],
                            'globalBlocks' => [],
                            'builderApi' => BuilderApiContract::routes(),
                            'apiBound' => RuntimeBindingState::api(),
                        ],
                        'revisionSummary' => [
                            'schemaVersion' => 1,
                            'kinds' => ['initial','manual','autosave','published','scheduled','pre_restore','restored'],
                        ],
                        'recoveryPoints' => CmsRuntimeServices::recoveryPoints(),
                        'health' => $healthRows,
                        'runtime' => [
                            'php' => PHP_VERSION,
                            'pinoox' => $runtimeKernel->version ?? 'unknown',
                            'pincoreCode' => $runtimeKernel->code,
                            'cms' => CmsRelease::version(),
                            'frontendBuildId' => $adminFrontend->buildId,
                            'appMountPath' => $adminMountPath,
                            'actorId' => $runtimeActorId,
                            'apiBound' => RuntimeBindingState::api(),
                            'queue' => 'auto',
                        ],
                        'summary' => [
                            'content' => 0,
                            'media' => count($mediaAssets),
                            'extensions' => count($extensions),
                            'problems' => 0,
                        ],
                        'safeMode' => $safeModeState,
                    ],
                    'actions' => [],
                ],
            ],
        ];

        $response = View::response('main', $viewData, 'text/html', 'UTF-8');

        return $adminResponses->decorate($response,$adminFrontend);
    }
}

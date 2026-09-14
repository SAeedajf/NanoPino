<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Runtime;

use App\com_pinoox_cms\Cms\Api\V1\Builder\BuilderApiFacade;
use App\com_pinoox_cms\Cms\Api\V1\Recovery\RecoveryApiFacade;
use App\com_pinoox_cms\Cms\Api\V1\Search\SearchApiFacade;
use App\com_pinoox_cms\Cms\Api\V1\Infrastructure\InfrastructureApiFacade;
use App\com_pinoox_cms\Cms\Api\V1\Performance\PerformanceApiFacade;
use App\com_pinoox_cms\Cms\Api\V1\Update\UpdateApiFacade;
use App\com_pinoox_cms\Cms\Api\V1\SystemHealth\SystemHealthApiFacade;
use App\com_pinoox_cms\Cms\Audit\AuditLogger;
use App\com_pinoox_cms\Cms\Audit\PinooxAuditRepository;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationManager;
use App\com_pinoox_cms\Cms\Authorization\PinooxAccessGateway;
use App\com_pinoox_cms\Cms\Authorization\ScopeType;
use App\com_pinoox_cms\Cms\Authorization\SingleSiteScopeGuard;
use App\com_pinoox_cms\Cms\Block\Document\BlockDocumentLoader;
use App\com_pinoox_cms\Cms\Block\Document\BlockDocumentParser;
use App\com_pinoox_cms\Cms\Block\Document\BlockDocumentSerializer;
use App\com_pinoox_cms\Cms\Block\Document\BlockDocumentValidator;
use App\com_pinoox_cms\Cms\Block\Migration\BlockMigrationEngine;
use App\com_pinoox_cms\Cms\Block\Render\BlockDocumentRenderer;
use App\com_pinoox_cms\Cms\Builder\BuilderService;
use App\com_pinoox_cms\Cms\Builder\BuilderPublishedResolver;
use App\com_pinoox_cms\Cms\Builder\Binding\BindingExpressionValidator;
use App\com_pinoox_cms\Cms\Builder\Binding\CoreDataBindingResolver;
use App\com_pinoox_cms\Cms\Builder\GlobalBlock\GlobalBlockReferenceExpander;
use App\com_pinoox_cms\Cms\Builder\GlobalBlock\GlobalBlockService;
use App\com_pinoox_cms\Cms\Builder\GlobalBlock\PinooxGlobalBlockRepository;
use App\com_pinoox_cms\Cms\Builder\PinooxBuilderDocumentRepository;
use App\com_pinoox_cms\Cms\Builder\Preview\BuilderPreviewService;
use App\com_pinoox_cms\Cms\Builder\Revision\PinooxBuilderRevisionRepository;
use App\com_pinoox_cms\Cms\Builder\Transaction\PinooxBuilderTransaction;
use App\com_pinoox_cms\Cms\Compatibility\RuntimeKernelInfoResolver;
use App\com_pinoox_cms\Cms\Content\ContentService;
use App\com_pinoox_cms\Cms\Content\PinooxContentRepository;
use App\com_pinoox_cms\Cms\Dependency\ExtensionDependencyResolver;
use App\com_pinoox_cms\Cms\Dependency\VersionConstraintEvaluatorFactory;
use App\com_pinoox_cms\Cms\Discovery\PinooxInstalledExtensionDiscovery;
use App\com_pinoox_cms\Cms\ExtensionCenter\ExtensionCenterCatalogService;
use App\com_pinoox_cms\Cms\ExtensionCenter\ExtensionCenterService;
use App\com_pinoox_cms\Cms\ExtensionCenter\PinooxExtensionCenterSignalProvider;
use App\com_pinoox_cms\Cms\ExtensionCenter\Grant\ExtensionPermissionGrantService;
use App\com_pinoox_cms\Cms\ExtensionCenter\Grant\FileExtensionPermissionGrantRepository;
use App\com_pinoox_cms\Cms\ExtensionCenter\Operation\ExtensionOperationCoordinator;
use App\com_pinoox_cms\Cms\ExtensionCenter\Operation\FileExtensionOperationLock;
use App\com_pinoox_cms\Cms\ExtensionCenter\Operation\FileExtensionOperationRepository;
use App\com_pinoox_cms\Cms\ExtensionCenter\Operation\PinooxExtensionOperationExecutor;
use App\com_pinoox_cms\Cms\ExtensionCenter\Package\ExtensionPackageUploadPolicy;
use App\com_pinoox_cms\Cms\ExtensionCenter\Package\ExtensionStagingStore;
use App\com_pinoox_cms\Cms\ExtensionCenter\Package\PinooxPinxPackageInspector;
use App\com_pinoox_cms\Cms\ExtensionCenter\Review\ExtensionInstallReviewService;
use App\com_pinoox_cms\Cms\ExtensionCenter\Review\ExtensionReviewTicketService;
use App\com_pinoox_cms\Cms\ExtensionCenter\Review\FileExtensionReviewTicketRepository;
use App\com_pinoox_cms\Cms\Field\FieldEngine;
use App\com_pinoox_cms\Cms\Health\HealthRunner;
use App\com_pinoox_cms\Cms\Health\FileHealthHistoryRepository;
use App\com_pinoox_cms\Cms\Health\SystemHealthRegistrar;
use App\com_pinoox_cms\Cms\Kernel\CmsKernel;
use App\com_pinoox_cms\Cms\Logging\CmsLoggerInterface;
use App\com_pinoox_cms\Cms\Logging\FileCmsLogger;
use App\com_pinoox_cms\Cms\Media\MediaService;
use App\com_pinoox_cms\Cms\Media\MediaUploadValidator;
use App\com_pinoox_cms\Cms\Media\MediaUploadPolicy;
use App\com_pinoox_cms\Cms\Media\PinooxMediaRepository;
use App\com_pinoox_cms\Cms\Media\PinooxNativeFileGateway;
use App\com_pinoox_cms\Cms\Permission\ExtensionPermissionReviewer;
use App\com_pinoox_cms\Cms\Recovery\FileRecoveryPointRepository;
use App\com_pinoox_cms\Cms\Recovery\FilesystemSnapshotProvider;
use App\com_pinoox_cms\Cms\Recovery\RecoveryActionService;
use App\com_pinoox_cms\Cms\Recovery\RecoveryManager;
use App\com_pinoox_cms\Cms\Recovery\RecoveryPointStatus;
use App\com_pinoox_cms\Cms\Recovery\RuntimeHealthSafeModeExitGuard;
use App\com_pinoox_cms\Cms\Recovery\SafeModeManager;
use App\com_pinoox_cms\Cms\Revision\PinooxRevisionRepository;
use App\com_pinoox_cms\Cms\Revision\RevisionService;
use App\com_pinoox_cms\Cms\Settings\PinooxSettingsRepository;
use App\com_pinoox_cms\Cms\Settings\SettingScope;
use App\com_pinoox_cms\Cms\Settings\SettingsRepositoryInterface;
use App\com_pinoox_cms\Cms\Settings\SettingsService;
use App\com_pinoox_cms\Cms\Support\CmsRelease;
use App\com_pinoox_cms\Cms\Support\SupportBundleBuilder;
use App\com_pinoox_cms\Cms\Taxonomy\PinooxTermRepository;
use App\com_pinoox_cms\Cms\Taxonomy\TaxonomyService;
use App\com_pinoox_cms\Cms\Theme\PinooxNativeThemeGateway;
use App\com_pinoox_cms\Cms\Theme\PinooxThemeActivationGateway;
use App\com_pinoox_cms\Cms\Theme\ThemeCompatibilityChecker;
use App\com_pinoox_cms\Cms\Theme\ThemeDiscoveryService;
use App\com_pinoox_cms\Cms\Theme\ThemeEngine;
use App\com_pinoox_cms\Cms\Theme\ThemeInheritanceResolver;
use App\com_pinoox_cms\Cms\Theme\ThemeService;
use App\com_pinoox_cms\Cms\Theme\ThemeView;
use App\com_pinoox_cms\Cms\Theme\Design\DesignSchemaValidator;
use App\com_pinoox_cms\Cms\Theme\Template\TemplateHierarchyResolver;
use App\com_pinoox_cms\Cms\Theme\Template\TemplateRequest;
use App\com_pinoox_cms\Cms\Theme\WordPress\WordPressThemeAssetPipeline;
use App\com_pinoox_cms\Cms\Identity\PinooxIdentityMutationGateway;
use App\com_pinoox_cms\Cms\Identity\PinooxUserLookup;
use App\com_pinoox_cms\Cms\Identity\UserAdministrationService;
use App\com_pinoox_cms\Cms\Search\SearchService;
use App\com_pinoox_cms\Cms\Search\SearchManager;
use App\com_pinoox_cms\Cms\Search\PinooxDatabaseSearchDriver;
use App\com_pinoox_cms\Cms\Search\SearchDriverInterface;
use App\com_pinoox_cms\Cms\Search\RemoteSearchConfiguration;
use App\com_pinoox_cms\Cms\Search\GuardedRemoteSearchTransport;
use App\com_pinoox_cms\Cms\Search\MeilisearchSearchDriver;
use App\com_pinoox_cms\Cms\Search\TypesenseSearchDriver;
use App\com_pinoox_cms\Cms\Security\Network\NativeHostResolver;
use App\com_pinoox_cms\Cms\Security\Network\SsrfGuard;
use App\com_pinoox_cms\Cms\Infrastructure\InfrastructureSnapshotService;
use App\com_pinoox_cms\Cms\Cache\PinooxCacheStore;
use App\com_pinoox_cms\Cms\Cache\FileCacheTagClock;
use App\com_pinoox_cms\Cms\Cache\SemanticCache;
use App\com_pinoox_cms\Cms\Cache\CacheControlService;
use App\com_pinoox_cms\Cms\Cache\ContentCacheInvalidator;
use App\com_pinoox_cms\Cms\Cache\PublicRenderCacheInvalidator;
use App\com_pinoox_cms\Cms\Queue\FileQueueRepository;
use App\com_pinoox_cms\Cms\Queue\QueueControlService;
use App\com_pinoox_cms\Cms\Queue\QueueDispatcher;
use App\com_pinoox_cms\Cms\Queue\QueueMode;
use App\com_pinoox_cms\Cms\Queue\QueuePayloadValidator;
use App\com_pinoox_cms\Cms\Queue\QueueRegistry;
use App\com_pinoox_cms\Cms\Queue\QueueWorker;
use App\com_pinoox_cms\Cms\Search\SearchQueueRegistrar;
use App\com_pinoox_cms\Cms\Storage\CmsStorage;
use App\com_pinoox_cms\Cms\Storage\PinooxStorageDriver;
use App\com_pinoox_cms\Cms\Performance\PerformanceSnapshotService;
use App\com_pinoox_cms\Cms\Performance\FilePerformanceRecorder;
use App\com_pinoox_cms\Cms\Performance\PerformanceMetric;
use App\com_pinoox_cms\Cms\Performance\PerformanceSample;
use App\com_pinoox_cms\Cms\Performance\PerformanceProfiler;
use App\com_pinoox_cms\Cms\Performance\Query\PinooxQueryProbe;
use App\com_pinoox_cms\Cms\Performance\Cache\CacheEffectivenessTracker;
use App\com_pinoox_cms\Cms\Performance\Extension\ExtensionCostTracker;
use App\com_pinoox_cms\Cms\UpdatePolicy\UpdateCenterService;
use App\com_pinoox_cms\Cms\UpdatePolicy\FileExtensionUpdatePolicyRepository;
use App\com_pinoox_cms\Cms\UpdatePolicy\UpdateCandidateSelector;
use App\com_pinoox_cms\Cms\UpdateHistory\FileUpdateHistoryRepository;
use App\com_pinoox_cms\Cms\Recovery\RecoveryCatalogService;
use Pinoox\Portal\App\AppEngine;
use Pinoox\Support\SystemConfig;

final class CmsRuntimeServices
{
    private static ?AuthorizationManager $authorization = null;
    private static ?AuditLogger $audit = null;
    private static ?PinooxSettingsRepository $settingsRepository = null;
    private static ?SettingsService $settings = null;
    private static ?MediaService $media = null;
    private static ?BuilderService $builder = null;
    private static ?CoreDataBindingResolver $dataBinding = null;
    private static ?BuilderPreviewService $builderPreview = null;
    private static ?BuilderApiFacade $builderApi = null;
    private static ?PinooxGlobalBlockRepository $globalBlockRepository = null;
    private static ?GlobalBlockService $globalBlocks = null;
    private static ?ContentService $content = null;
    private static ?RevisionService $revisions = null;
    private static ?TaxonomyService $taxonomy = null;
    private static ?PinooxInstalledExtensionDiscovery $extensionDiscovery = null;
    private static ?ExtensionStagingStore $extensionStaging = null;
    private static ?UserAdministrationService $userAdministration = null;
    private static ?SearchApiFacade $searchApi = null;
    private static ?InfrastructureApiFacade $infrastructureApi = null;
    private static ?PerformanceApiFacade $performanceApi = null;
    private static ?FilePerformanceRecorder $performanceRecorder = null;
    private static ?UpdateApiFacade $updateApi = null;
    private static ?PinooxCacheStore $cacheStore = null;
    private static ?SemanticCache $semanticCache = null;
    private static ?PublicRenderCacheInvalidator $renderCacheInvalidator = null;
    private static ?ThemeEngine $themeEngine = null;
    private static ?WordPressThemeAssetPipeline $wordpressAssetPipeline = null;
    private static ?FileQueueRepository $queueRepository = null;
    private static ?QueueWorker $queueWorker = null;
    private static ?QueueDispatcher $queueDispatcher = null;
    private static ?CmsStorage $storage = null;
    private static bool $searchQueueRegistered = false;
    private static ?PinooxStorageDriver $storageDriver = null;
    private static ?CacheEffectivenessTracker $cacheTracker = null;
    private static ?ExtensionCostTracker $extensionCostTracker = null;
    private static ?PinooxQueryProbe $queryProbe = null;
    private static bool $actorContextInitialized = false;
    private static ?int $actorContextId = null;

    public static function kernel(): CmsKernel { return CmsKernel::instance(); }

    public static function actorId(): ?int
    {
        $actorId = RuntimeActor::id();
        self::synchronizeActorContext($actorId);
        return $actorId;
    }

    public static function authorization(): AuthorizationManager
    {
        return self::$authorization ??= new AuthorizationManager(
            self::kernel()->capabilities,
            self::kernel()->policies,
            new PinooxAccessGateway(),
            new SingleSiteScopeGuard(1, self::actorId()),
        );
    }

    public static function audit(): AuditLogger
    {
        return self::$audit ??= new AuditLogger(new PinooxAuditRepository());
    }

    public static function settingsRepository(): SettingsRepositoryInterface
    {
        return self::$settingsRepository ??= new PinooxSettingsRepository();
    }

    public static function settings(): SettingsService
    {
        self::actorId();
        return self::$settings ??= new SettingsService(
            self::kernel()->settings,
            self::settingsRepository(),
            self::authorization(),
            self::audit(),
            self::renderCacheInvalidator(),
        );
    }

    public static function media(): MediaService
    {
        self::actorId();
        return self::$media ??= new MediaService(
            new PinooxMediaRepository(),
            new PinooxNativeFileGateway(),
            new MediaUploadValidator(),
            new MediaUploadPolicy(),
            self::authorization(),
            self::audit(),
        );
    }

    public static function blockValidator(): BlockDocumentValidator
    {
        return new BlockDocumentValidator(self::kernel()->blocks);
    }

    public static function blockLoader(): BlockDocumentLoader
    {
        $validator = self::blockValidator();
        return new BlockDocumentLoader(
            new BlockDocumentParser(),
            new BlockMigrationEngine(
                self::kernel()->blocks,
                self::kernel()->blockMigrations,
                $validator,
            ),
            $validator,
        );
    }

    public static function builder(): BuilderService
    {
        self::actorId();
        if (self::$builder !== null) return self::$builder;
        return self::$builder = new BuilderService(
            new PinooxBuilderDocumentRepository(),
            new PinooxBuilderRevisionRepository(),
            self::blockLoader(),
            new BlockDocumentSerializer(),
            self::authorization(),
            self::audit(),
            new PinooxBuilderTransaction(),
            self::renderCacheInvalidator(),
        );
    }

    /**
     * Public/template data binding is read-only and intentionally does not
     * require an editor actor. Callers still receive only published content,
     * public taxonomy terms, ready media and validated site navigation.
     */
    public static function dataBinding(): CoreDataBindingResolver
    {
        return self::$dataBinding ??= new CoreDataBindingResolver(
            new PinooxContentRepository(),
            new PinooxTermRepository(),
            new PinooxMediaRepository(),
            self::settingsRepository(),
            self::kernel()->taxonomies,
            new BindingExpressionValidator(self::kernel()->builderDataSources),
        );
    }

    public static function builderPreview(): BuilderPreviewService
    {
        self::actorId();
        if (self::$builderPreview !== null) return self::$builderPreview;
        $validator = self::blockValidator();
        return self::$builderPreview = new BuilderPreviewService(
            self::blockLoader(),
            new BlockDocumentSerializer(),
            new BlockDocumentRenderer(
                self::kernel()->blocks,
                $validator,
                self::kernel()->blockRenderers,
            ),
            self::authorization(),
            new GlobalBlockReferenceExpander(
                self::globalBlockRepository(),
                self::blockLoader(),
                $validator,
            ),
        );
    }

    /**
     * Public rendering deliberately bypasses the editor authorization boundary.
     * The caller must resolve a published document before using this renderer.
     */
    public static function publicBlockRenderer(): BlockDocumentRenderer
    {
        return new BlockDocumentRenderer(
            self::kernel()->blocks,
            self::blockValidator(),
            self::kernel()->blockRenderers,
        );
    }

    public static function publicBuilderPublishedResolver(): BuilderPublishedResolver
    {
        return new BuilderPublishedResolver(
            new PinooxBuilderDocumentRepository(),
            new PinooxBuilderRevisionRepository(),
        );
    }

    public static function publicGlobalBlockExpander(): GlobalBlockReferenceExpander
    {
        return new GlobalBlockReferenceExpander(
            self::globalBlockRepository(),
            self::blockLoader(),
            self::blockValidator(),
        );
    }

    public static function globalBlockRepository(): PinooxGlobalBlockRepository
    {
        return self::$globalBlockRepository ??= new PinooxGlobalBlockRepository();
    }

    public static function globalBlocks(): GlobalBlockService
    {
        self::actorId();
        return self::$globalBlocks ??= new GlobalBlockService(
            self::globalBlockRepository(),
            self::blockLoader(),
            new BlockDocumentSerializer(),
            self::authorization(),
        );
    }

    public static function builderApi(): BuilderApiFacade
    {
        self::actorId();
        return self::$builderApi ??= new BuilderApiFacade(self::builder(), self::builderPreview());
    }

    public static function revisions(): RevisionService
    {
        self::actorId();
        if (self::$revisions !== null) return self::$revisions;
        $contentRepository = new PinooxContentRepository();
        return self::$revisions = new RevisionService(
            new PinooxRevisionRepository(),
            $contentRepository,
            self::kernel()->contentTypes,
            new FieldEngine(self::kernel()->fields),
            self::kernel()->taxonomies,
            new PinooxTermRepository(),
            self::authorization(),
            self::audit(),
        );
    }

    public static function content(): ContentService
    {
        self::actorId();
        if (self::$content !== null) return self::$content;
        return self::$content = new ContentService(
            self::kernel()->contentTypes,
            new FieldEngine(self::kernel()->fields),
            self::kernel()->taxonomies,
            new PinooxContentRepository(),
            new PinooxTermRepository(),
            self::authorization(),
            self::audit(),
            revisions: self::revisions(),
            users: new PinooxUserLookup(),
            cacheInvalidator: new ContentCacheInvalidator(self::semanticCache()),
        );
    }

    public static function taxonomy(): TaxonomyService
    {
        self::actorId();
        return self::$taxonomy ??= new TaxonomyService(
            self::kernel()->taxonomies,
            new PinooxTermRepository(),
            self::authorization(),
            self::audit(),
        );
    }


    public static function userAdministration(): UserAdministrationService
    {
        self::actorId();
        return self::$userAdministration ??= new UserAdministrationService(
            self::authorization(),
            new PinooxIdentityMutationGateway(),
        );
    }

    public static function storageRoot(): string
    {
        try {
            $path = rtrim(SystemConfig::path('storage'), '/\\') . '/cms';
        } catch (\Throwable) {
            $path = rtrim(sys_get_temp_dir(), '/\\') . '/pinoox-cms';
        }

        if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) {
            throw new \RuntimeException('Unable to create CMS storage root.');
        }
        return $path;
    }

    public static function extensionDiscovery(): PinooxInstalledExtensionDiscovery
    {
        return self::$extensionDiscovery ??= new PinooxInstalledExtensionDiscovery();
    }

    public static function syncInstalledExtensions(): int
    {
        return self::extensionDiscovery()->syncRegistry(self::kernel()->extensions);
    }

    public static function extensionStaging(): ExtensionStagingStore
    {
        return self::$extensionStaging ??= new ExtensionStagingStore(
            self::storageRoot() . '/extensions/staging',
        );
    }

    public static function extensionCenter(): ExtensionCenterService
    {
        self::syncInstalledExtensions();
        $root = self::storageRoot() . '/extensions';
        $safeMode = new SafeModeManager(self::storageRoot() . '/recovery/safe-mode.json');

        return new ExtensionCenterService(
            new ExtensionCenterCatalogService(
                self::kernel()->extensions,
                new PinooxExtensionCenterSignalProvider(),
            ),
            new ExtensionPackageUploadPolicy(),
            new PinooxPinxPackageInspector(),
            new ExtensionInstallReviewService(
                new ExtensionDependencyResolver(),
                new ExtensionPermissionReviewer(self::kernel()->extensionPermissions),
            ),
            new ExtensionReviewTicketService(
                new FileExtensionReviewTicketRepository($root . '/review-tickets'),
            ),
            new ExtensionOperationCoordinator(
                new FileExtensionOperationRepository($root . '/operations'),
                new FileExtensionOperationLock($root . '/locks'),
                new PinooxExtensionOperationExecutor(self::storageRoot(), self::kernel()),
                $safeMode,
            ),
            self::extensionDiscovery()->catalog(),
            self::authorization(),
            new ExtensionPermissionGrantService(
                new FileExtensionPermissionGrantRepository($root . '/grants'),
            ),
        );
    }

    /** @return list<\App\com_pinoox_cms\Cms\Theme\ThemeDefinition> */
    public static function discoverThemes(): array
    {
        $native = new PinooxNativeThemeGateway();
        $discovery = new ThemeDiscoveryService($native, self::kernel()->themes);
        $seen = [];
        $result = [];

        foreach (array_keys(AppEngine::packagePaths()) as $package) {
            try {
                foreach ($discovery->discover($package) as $theme) {
                    if (isset($seen[$theme->identifier()])) continue;
                    $seen[$theme->identifier()] = true;
                    $result[] = $theme;
                }
            } catch (\Throwable) {}
        }

        usort($result, static fn($a, $b): int => strcmp($a->identifier(), $b->identifier()));
        return $result;
    }

    public static function themeService(): ThemeService
    {
        self::discoverThemes();
        $native = new PinooxNativeThemeGateway();

        return new ThemeService(
            self::kernel()->themes,
            new ThemeInheritanceResolver($native),
            new ThemeCompatibilityChecker(VersionConstraintEvaluatorFactory::make()),
            new PinooxThemeActivationGateway($native),
            self::authorization(),
            self::audit(),
            CmsRelease::version(),
            self::renderCacheInvalidator(),
        );
    }

    /**
     * Return the read-only WordPress asset intake service.
     *
     * The service only builds a bounded manifest; it never evaluates PHP
     * sidecars, executes JavaScript, fetches remote assets or publishes files.
     */
    public static function wordpressAssetPipeline(): WordPressThemeAssetPipeline
    {
        return self::$wordpressAssetPipeline ??= new WordPressThemeAssetPipeline();
    }

    /**
     * Resolve the active native theme for public rendering.
     *
     * Public requests receive only validated design data here. Theme template
     * source is intentionally not evaluated by this boundary; public output
     * stays on the safe built-in renderer and Builder's render boundary.
     */
    public static function publicThemeView(
        TemplateRequest $request,
        int $siteId = 1,
        ?string $context = null,
        ?string $styleVariation = null,
    ): ?ThemeView {
        if ($siteId < 1) return null;

        try {
            self::discoverThemes();
            $package = 'com_pinoox_cms';
            $native = new PinooxNativeThemeGateway();
            $stack = $native->stack($package, $context);
            $definition = self::kernel()->themes->byReference($package, $stack->activeName);
            if ($definition === null) return null;

            // Site overrides are optional presentation data. A settings
            // backend failure must not hide an otherwise valid native theme.
            $overrides = [];
            try {
                $record = self::settingsRepository()->find(
                    'theme.design.overrides',
                    new SettingScope(ScopeType::Site, $siteId),
                );
                if (is_array($record?->value)) {
                    $overrides = (new DesignSchemaValidator())->validate([
                        'schema' => 1,
                        'tokens' => $record->value,
                    ])->tokens;
                }
            } catch (\Throwable) {
                $overrides = [];
            }

            return self::themeEngine()->resolve(
                $package,
                $stack->activeName,
                $request,
                $context,
                $styleVariation,
                $overrides,
            );
        } catch (\Throwable) {
            return null;
        }
    }

    public static function healthRunner(): HealthRunner
    {
        $kernel = self::kernel();
        // Health must observe the same installed-extension registry exposed by
        // the Extension Center API. Without this sync, a fresh request can
        // report registered_extensions=0 while /extensions already lists
        // active installed extensions.
        self::syncInstalledExtensions();
        $runtime = (new RuntimeKernelInfoResolver())->resolve();

        if ($kernel->healthChecks->get('system.database') === null) {
            (new SystemHealthRegistrar(
                self::storageRoot(),
                $runtime->code,
                $runtime->version,
                count($kernel->extensions->all()),
            ))->register($kernel->healthChecks);
        }

        return new HealthRunner($kernel->healthChecks);
    }

    /** @return array{overall:string,checks:list<array<string,mixed>>} */
    public static function healthSnapshot(): array
    {
        $runner = self::healthRunner();
        $results = $runner->runAll();
        return [
            'overall' => $runner->overall($results)->value,
            'checks' => array_map(static fn($result): array => $result->toArray(), $results),
        ];
    }

    public static function logger(): CmsLoggerInterface
    {
        return new FileCmsLogger(self::storageRoot() . '/logs/cms.jsonl');
    }

    public static function systemHealthApi(): SystemHealthApiFacade
    {
        return new SystemHealthApiFacade(
            self::authorization(),
            self::healthRunner(),
            new FileHealthHistoryRepository(self::storageRoot() . '/health/history.jsonl'),
            self::logger(),
            new SupportBundleBuilder(
                self::healthRunner(),
                self::logger(),
                new SafeModeManager(self::storageRoot() . '/recovery/safe-mode.json'),
            ),
        );
    }


    public static function searchApi(): SearchApiFacade
    {
        self::actorId();
        return new SearchApiFacade(
            new SearchService(
                self::authorization(),
                new SearchManager(
                    self::searchDriver(),
                    new PinooxDatabaseSearchDriver(),
                ),
            ),
        );
    }

    public static function searchDriver(): SearchDriverInterface
    {
        $configuration = RemoteSearchConfiguration::fromRepository(new PinooxSettingsRepository());
        if ($configuration === null || !function_exists('curl_init')) {
            RuntimeBindingState::setSsrf(false);
            return new PinooxDatabaseSearchDriver();
        }

        $transport = new GuardedRemoteSearchTransport(
            $configuration,
            new SsrfGuard(new NativeHostResolver()),
        );
        RuntimeBindingState::setSsrf(true);

        return $configuration->driver === 'meilisearch'
            ? new MeilisearchSearchDriver($transport)
            : new TypesenseSearchDriver($transport);
    }

    public static function remoteSearchConfiguration(): ?RemoteSearchConfiguration
    {
        return RemoteSearchConfiguration::fromRepository(new PinooxSettingsRepository());
    }

    private static function cacheStore(): PinooxCacheStore
    {
        return self::$cacheStore ??= new PinooxCacheStore();
    }

    private static function performanceRecorder(): FilePerformanceRecorder
    {
        return self::$performanceRecorder ??= new FilePerformanceRecorder(
            self::storageRoot() . '/performance/samples.jsonl',
        );
    }

    public static function queueRepository(): FileQueueRepository
    {
        return self::$queueRepository ??= new FileQueueRepository(self::storageRoot() . '/queue');
    }

    public static function queueRegistry(): QueueRegistry
    {
        $registry = self::kernel()->queueJobs;
        if (!self::$searchQueueRegistered) {
            (new SearchQueueRegistrar(self::searchDriver()))->register($registry);
            self::$searchQueueRegistered = true;
        }
        return $registry;
    }

    public static function queueWorker(): QueueWorker
    {
        return self::$queueWorker ??= new QueueWorker(
            self::queueRegistry(),
            self::queueRepository(),
            new PerformanceProfiler(self::performanceRecorder()),
        );
    }

    public static function queueDispatcher(): QueueDispatcher
    {
        return self::$queueDispatcher ??= new QueueDispatcher(
            self::queueRegistry(),
            self::queueRepository(),
            new QueuePayloadValidator(),
            QueueMode::Auto,
            class_exists('Pinoox\\Cron\\Schedule') && class_exists('Pinoox\\Cron\\ScheduledTask'),
        );
    }

    private static function storageDriver(): PinooxStorageDriver
    {
        return self::$storageDriver ??= new PinooxStorageDriver();
    }

    private static function cacheTracker(): CacheEffectivenessTracker
    {
        return self::$cacheTracker ??= new CacheEffectivenessTracker();
    }

    private static function extensionCostTracker(): ExtensionCostTracker
    {
        return self::$extensionCostTracker ??= new ExtensionCostTracker();
    }

    public static function bindQueryProbe(): bool
    {
        return self::queryProbe()->bind();
    }

    private static function queryProbe(): PinooxQueryProbe
    {
        return self::$queryProbe ??= PinooxQueryProbe::instance();
    }

    private static function semanticCache(): SemanticCache
    {
        return self::$semanticCache ??= new SemanticCache(
            self::cacheStore(),
            new FileCacheTagClock(self::storageRoot() . '/cache/tag-clock'),
            'nanopino',
            self::cacheTracker(),
        );
    }

    private static function renderCacheInvalidator(): PublicRenderCacheInvalidator
    {
        return self::$renderCacheInvalidator ??= new PublicRenderCacheInvalidator(self::semanticCache());
    }

    private static function themeEngine(): ThemeEngine
    {
        if (self::$themeEngine !== null) return self::$themeEngine;

        $native = new PinooxNativeThemeGateway();
        return self::$themeEngine = new ThemeEngine(
            self::kernel()->themes,
            new ThemeInheritanceResolver($native),
            new TemplateHierarchyResolver(self::kernel()->templateRules),
        );
    }

    public static function cache(): SemanticCache
    {
        return self::semanticCache();
    }

    public static function storage(): CmsStorage
    {
        return self::$storage ??= new CmsStorage(self::storageDriver());
    }

    public static function infrastructureApi(): InfrastructureApiFacade
    {
        self::actorId();
        return new InfrastructureApiFacade(
            self::authorization(),
            new InfrastructureSnapshotService(
                self::kernel()->drivers,
                self::searchDriver(),
                self::cacheStore(),
                self::queueRepository(),
                self::storageDriver(),
            ),
            new QueueControlService(self::authorization(), self::queueRepository()),
            new CacheControlService(self::authorization(), self::semanticCache()),
        );
    }

    public static function performanceApi(): PerformanceApiFacade
    {
        self::actorId();
        self::bindQueryProbe();

        return self::$performanceApi ??= new PerformanceApiFacade(
            self::authorization(),
            new PerformanceSnapshotService(
                self::kernel()->performanceBudgets,
                self::performanceRecorder(),
                self::queryProbe(),
                self::cacheTracker(),
                self::extensionCostTracker(),
            ),
        );
    }

    /**
     * Record a request-local API duration without allowing telemetry storage
     * failures to affect the API response path.
     */
    public static function recordApiTiming(string $operation, int $startedNs): void
    {
        self::recordTiming('api.facade_response', PerformanceMetric::ApiMs, $operation, $startedNs);
    }

    /**
     * Record a request-local search duration without allowing telemetry
     * storage failures to affect the search response path.
     */
    public static function recordSearchTiming(string $operation, int $startedNs): void
    {
        self::recordTiming('search.response', PerformanceMetric::SearchMs, $operation, $startedNs);
    }

    private static function recordTiming(
        string $name,
        PerformanceMetric $metric,
        string $operation,
        int $startedNs,
    ): void {
        if ($name === '' || $operation === '' || $startedNs <= 0) {
            return;
        }

        try {
            self::performanceRecorder()->record(new PerformanceSample(
                $name,
                $metric,
                max(0.0, (hrtime(true) - $startedNs) / 1_000_000),
                microtime(true),
                ['operation' => $operation],
            ));
        } catch (\Throwable) {
            // Observability is best effort and must never break the request path.
        }
    }

    public static function updateApi(): UpdateApiFacade
    {
        self::actorId();
        $root = self::storageRoot();
        return self::$updateApi ??= new UpdateApiFacade(
            new UpdateCenterService(
                self::authorization(),
                new FileExtensionUpdatePolicyRepository($root . '/updates/policies'),
                new UpdateCandidateSelector(),
                new FileUpdateHistoryRepository($root . '/updates/history.jsonl'),
                new RecoveryCatalogService(
                    self::recoveryRepository(),
                    new SafeModeManager($root . '/recovery/safe-mode.json'),
                ),
            ),
        );
    }

    public static function recoveryRepository(): FileRecoveryPointRepository
    {
        return new FileRecoveryPointRepository(self::storageRoot() . '/recovery/points');
    }

    public static function recoveryApi(): RecoveryApiFacade
    {
        $repository = self::recoveryRepository();
        $safeMode = new SafeModeManager(self::storageRoot() . '/recovery/safe-mode.json');
        $manager = new RecoveryManager(
            $repository,
            [new FilesystemSnapshotProvider(
                self::storageRoot(),
                self::storageRoot() . '/recovery/snapshots',
            )],
        );

        return new RecoveryApiFacade(
            new RecoveryActionService(
                self::authorization(),
                $manager,
                $repository,
                $safeMode,
                new RuntimeHealthSafeModeExitGuard(self::healthRunner()),
            ),
        );
    }

    private static function synchronizeActorContext(?int $actorId): void
    {
        if (
            self::$actorContextInitialized
            && self::$actorContextId === $actorId
        ) {
            return;
        }

        self::$actorContextInitialized = true;
        self::$actorContextId = $actorId;

        self::$authorization = null;
        self::$settings = null;
        self::$media = null;
        self::$builder = null;
        self::$builderPreview = null;
        self::$builderApi = null;
        self::$globalBlocks = null;
        self::$content = null;
        self::$revisions = null;
        self::$taxonomy = null;
        self::$userAdministration = null;
        self::$searchApi = null;
        self::$infrastructureApi = null;
        self::$performanceApi = null;
        self::$updateApi = null;
    }

    /** @return list<array<string,mixed>> */
    public static function recoveryPoints(): array
    {
        $safe = (new SafeModeManager(self::storageRoot() . '/recovery/safe-mode.json'))->state();
        return array_map(
            static fn($point): array => [
                'id' => $point->id,
                'extensionId' => $point->extensionId,
                'operation' => $point->operation,
                'status' => $point->status->value,
                'createdAt' => $point->createdAt,
                'error' => $point->error,
                'restorable' => in_array($point->status, [
                    RecoveryPointStatus::Ready,
                    RecoveryPointStatus::Failed,
                ], true),
                'safeModeTarget' => $safe->recoveryPointId === $point->id,
            ],
            self::recoveryRepository()->all(),
        );
    }
}

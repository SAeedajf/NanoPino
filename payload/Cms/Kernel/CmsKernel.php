<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Kernel;

use App\com_pinoox_cms\Cms\Ability\AbilityRegistry;
use App\com_pinoox_cms\Cms\Admin\AdminRegistrySet;
use App\com_pinoox_cms\Cms\Admin\CoreAdminDefinitions;
use App\com_pinoox_cms\Cms\Admin\CoreIdentityAdminDefinitions;
use App\com_pinoox_cms\Cms\Admin\CoreSettingsAdminDefinitions;
use App\com_pinoox_cms\Cms\Admin\CoreContentAdminDefinitions;
use App\com_pinoox_cms\Cms\Admin\CoreMediaRevisionAdminDefinitions;
use App\com_pinoox_cms\Cms\Content\ContentSchemaValidator;
use App\com_pinoox_cms\Cms\Content\ContentTypeRegistry;
use App\com_pinoox_cms\Cms\Content\CoreContentTypes;
use App\com_pinoox_cms\Cms\Field\FieldRegistry;
use App\com_pinoox_cms\Cms\Field\CoreFields;
use App\com_pinoox_cms\Cms\Taxonomy\TaxonomyRegistry;
use App\com_pinoox_cms\Cms\Taxonomy\CoreTaxonomies;
use App\com_pinoox_cms\Cms\Capability\CapabilityRegistry;
use App\com_pinoox_cms\Cms\Capability\CoreCapabilities;
use App\com_pinoox_cms\Cms\Extension\ExtensionRegistry;
use App\com_pinoox_cms\Cms\Health\HealthCheckRegistry;
use App\com_pinoox_cms\Cms\Hook\FilterPipeline;
use App\com_pinoox_cms\Cms\Hook\FilterRegistry;
use App\com_pinoox_cms\Cms\Permission\CoreExtensionPermissions;
use App\com_pinoox_cms\Cms\Permission\ExtensionPermissionRegistry;
use App\com_pinoox_cms\Cms\Authorization\CorePolicies;
use App\com_pinoox_cms\Cms\Authorization\PolicyRegistry;
use App\com_pinoox_cms\Cms\Identity\CoreRoleTemplates;
use App\com_pinoox_cms\Cms\Identity\RoleTemplateRegistry;
use App\com_pinoox_cms\Cms\Settings\SettingsRegistry;
use App\com_pinoox_cms\Cms\Settings\CoreSettings;
use App\com_pinoox_cms\Cms\Theme\ThemeRegistry;
use App\com_pinoox_cms\Cms\Theme\Template\TemplateRuleRegistry;
use App\com_pinoox_cms\Cms\Theme\Template\CoreTemplateRules;
use App\com_pinoox_cms\Cms\Admin\CoreThemeAdminDefinitions;
use App\com_pinoox_cms\Cms\Admin\CoreBlockAdminDefinitions;
use App\com_pinoox_cms\Cms\Block\BlockRegistry;
use App\com_pinoox_cms\Cms\Block\Core\CoreBlocks;
use App\com_pinoox_cms\Cms\Block\Migration\BlockMigrationRegistry;
use App\com_pinoox_cms\Cms\Block\Core\CoreBlockMigrations;
use App\com_pinoox_cms\Cms\Block\Render\BlockRendererRegistry;
use App\com_pinoox_cms\Cms\Block\Core\CoreBlockRenderers;
use App\com_pinoox_cms\Cms\Builder\Binding\DataSourceRegistry;
use App\com_pinoox_cms\Cms\Builder\Binding\CoreDataSources;
use App\com_pinoox_cms\Cms\Admin\CoreBuilderAdminDefinitions;
use App\com_pinoox_cms\Cms\Admin\CoreFullSiteAdminDefinitions;
use App\com_pinoox_cms\Cms\Admin\CoreDeveloperAdminDefinitions;
use App\com_pinoox_cms\Cms\Driver\DriverRegistry;
use App\com_pinoox_cms\Cms\Driver\CoreDrivers;
use App\com_pinoox_cms\Cms\Queue\QueueRegistry;
use App\com_pinoox_cms\Cms\Performance\PerformanceBudgetRegistry;
use App\com_pinoox_cms\Cms\Performance\CorePerformanceBudgets;

final class CmsKernel
{
    private static ?self $instance = null;

    private bool $booted = false;

    public readonly ExtensionRegistry $extensions;
    public readonly CapabilityRegistry $capabilities;
    public readonly PolicyRegistry $policies;
    public readonly RoleTemplateRegistry $roleTemplates;
    public readonly AbilityRegistry $abilities;
    public readonly SettingsRegistry $settings;
    public readonly FieldRegistry $fields;
    public readonly TaxonomyRegistry $taxonomies;
    public readonly ContentTypeRegistry $contentTypes;
    public readonly FilterRegistry $filters;
    public readonly FilterPipeline $filterPipeline;
    public readonly HealthCheckRegistry $healthChecks;
    public readonly ExtensionPermissionRegistry $extensionPermissions;
    public readonly AdminRegistrySet $admin;
    public readonly ThemeRegistry $themes;
    public readonly TemplateRuleRegistry $templateRules;
    public readonly BlockRegistry $blocks;
    public readonly BlockMigrationRegistry $blockMigrations;
    public readonly BlockRendererRegistry $blockRenderers;
    public readonly DataSourceRegistry $builderDataSources;
    public readonly DriverRegistry $drivers;
    public readonly QueueRegistry $queueJobs;
    public readonly PerformanceBudgetRegistry $performanceBudgets;

    private function __construct(private readonly string $package)
    {
        $this->extensions = new ExtensionRegistry();
        $this->capabilities = new CapabilityRegistry();
        CoreCapabilities::register($this->capabilities);
        $this->policies = new PolicyRegistry();
        CorePolicies::register($this->policies);
        $this->roleTemplates = new RoleTemplateRegistry();
        CoreRoleTemplates::register($this->roleTemplates);
        $this->abilities = new AbilityRegistry();
        $this->settings = new SettingsRegistry();
        CoreSettings::register($this->settings);
        $this->fields = new FieldRegistry();
        CoreFields::register($this->fields);
        $this->taxonomies = new TaxonomyRegistry();
        CoreTaxonomies::register($this->taxonomies);
        $this->contentTypes = new ContentTypeRegistry();
        CoreContentTypes::register($this->contentTypes);
        (new ContentSchemaValidator())->validate($this->contentTypes, $this->fields, $this->taxonomies);
        $this->filters = new FilterRegistry();
        $this->filterPipeline = new FilterPipeline($this->filters);
        $this->healthChecks = new HealthCheckRegistry();
        $this->blocks = new BlockRegistry();
        CoreBlocks::register($this->blocks);
        $this->blockMigrations = new BlockMigrationRegistry();
        CoreBlockMigrations::register($this->blockMigrations);
        $this->blockRenderers = new BlockRendererRegistry();
        CoreBlockRenderers::register($this->blockRenderers);
        $this->builderDataSources = new DataSourceRegistry();
        CoreDataSources::register($this->builderDataSources);
        $this->themes = new ThemeRegistry();
        $this->templateRules = new TemplateRuleRegistry();
        CoreTemplateRules::register($this->templateRules);
        $this->extensionPermissions = new ExtensionPermissionRegistry();
        CoreExtensionPermissions::register($this->extensionPermissions);
        $this->drivers = new DriverRegistry();
        CoreDrivers::register($this->drivers);
        $this->queueJobs = new QueueRegistry();
        $this->performanceBudgets = new PerformanceBudgetRegistry();
        CorePerformanceBudgets::register($this->performanceBudgets);
        $this->admin = new AdminRegistrySet();
        CoreAdminDefinitions::register($this->admin);
        CoreIdentityAdminDefinitions::register($this->admin);
        CoreSettingsAdminDefinitions::register($this->admin);
        CoreContentAdminDefinitions::register($this->admin);
        CoreMediaRevisionAdminDefinitions::register($this->admin);
        CoreThemeAdminDefinitions::register($this->admin);
        CoreBlockAdminDefinitions::register($this->admin);
        CoreBuilderAdminDefinitions::register($this->admin);
        CoreFullSiteAdminDefinitions::register($this->admin);
        CoreDeveloperAdminDefinitions::register($this->admin);
    }

    public static function boot(string $package = 'com_pinoox_cms'): self
    {
        $kernel = self::$instance ??= new self($package);
        $kernel->booted = true;

        return $kernel;
    }

    public static function instance(): self
    {
        return self::$instance ??= new self('com_pinoox_cms');
    }

    public function validateContentSchema(): void
    {
        (new ContentSchemaValidator())->validate($this->contentTypes, $this->fields, $this->taxonomies);
    }

    public function package(): string
    {
        return $this->package;
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    /** @return array<string, list<array<string,mixed>>> */
    public function diagnostics(): array
    {
        return [
            'extensions' => $this->extensions->diagnostics(),
            'capabilities' => $this->capabilities->diagnostics(),
            'policies' => $this->policies->diagnostics(),
            'role_templates' => $this->roleTemplates->diagnostics(),
            'abilities' => $this->abilities->diagnostics(),
            'settings' => $this->settings->diagnostics(),
            'fields' => $this->fields->diagnostics(),
            'taxonomies' => $this->taxonomies->diagnostics(),
            'content_types' => $this->contentTypes->diagnostics(),
            'themes' => $this->themes->diagnostics(),
            'template_rules' => $this->templateRules->diagnostics(),
            'blocks' => $this->blocks->diagnostics(),
            'block_migrations' => $this->blockMigrations->diagnostics(),
            'block_renderers' => $this->blockRenderers->diagnostics(),
            'builder_data_sources' => $this->builderDataSources->diagnostics(),
            'filters' => $this->filters->diagnostics(),
            'health_checks' => $this->healthChecks->diagnostics(),
            'extension_permissions' => $this->extensionPermissions->diagnostics(),
            'drivers' => $this->drivers->diagnostics(),
            'queue_jobs' => $this->queueJobs->diagnostics(),
            'performance_budgets' => $this->performanceBudgets->diagnostics(),
            'admin' => $this->admin->diagnostics(),
        ];
    }

    public function unregisterOwner(string $owner): int
    {
        return $this->extensions->removeOwner($owner)
            + $this->capabilities->removeOwner($owner)
            + $this->policies->removeOwner($owner)
            + $this->roleTemplates->removeOwner($owner)
            + $this->abilities->removeOwner($owner)
            + $this->settings->removeOwner($owner)
            + $this->contentTypes->removeOwner($owner)
            + $this->themes->removeOwner($owner)
            + $this->templateRules->removeOwner($owner)
            + $this->blocks->removeOwner($owner)
            + $this->blockMigrations->removeOwner($owner)
            + $this->blockRenderers->removeOwner($owner)
            + $this->builderDataSources->removeOwner($owner)
            + $this->taxonomies->removeOwner($owner)
            + $this->fields->removeOwner($owner)
            + $this->filters->removeOwner($owner)
            + $this->healthChecks->removeOwner($owner)
            + $this->extensionPermissions->removeOwner($owner)
            + $this->drivers->removeOwner($owner)
            + $this->queueJobs->removeOwner($owner)
            + $this->performanceBudgets->removeOwner($owner)
            + $this->admin->removeOwner($owner);
    }
}

<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Sdk\Registry;

use App\com_pinoox_cms\Cms\Ability\AbilityDefinition;
use App\com_pinoox_cms\Cms\Admin\AdminComponentDefinition;
use App\com_pinoox_cms\Cms\Admin\AdminMenuDefinition;
use App\com_pinoox_cms\Cms\Admin\AdminPanelDefinition;
use App\com_pinoox_cms\Cms\Admin\AdminRouteDefinition;
use App\com_pinoox_cms\Cms\Admin\AdminWidgetDefinition;
use App\com_pinoox_cms\Cms\Block\BlockDefinition;
use App\com_pinoox_cms\Cms\Block\Migration\BlockMigrationDefinition;
use App\com_pinoox_cms\Cms\Block\Render\BlockRendererInterface;
use App\com_pinoox_cms\Cms\Capability\CapabilityDefinition;
use App\com_pinoox_cms\Cms\Content\ContentTypeDefinition;
use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use App\com_pinoox_cms\Cms\Driver\DriverDefinition;
use App\com_pinoox_cms\Cms\Extension\ExtensionDefinition;
use App\com_pinoox_cms\Cms\Field\FieldDefinition;
use App\com_pinoox_cms\Cms\Hook\FilterDefinition;
use App\com_pinoox_cms\Cms\Kernel\CmsKernel;
use App\com_pinoox_cms\Cms\Performance\PerformanceBudgetDefinition;
use App\com_pinoox_cms\Cms\Permission\ExtensionPermissionDefinition;
use App\com_pinoox_cms\Cms\Settings\SettingDefinition;
use App\com_pinoox_cms\Cms\Taxonomy\TaxonomyDefinition;
use App\com_pinoox_cms\Cms\Theme\Template\TemplateRuleDefinition;
use App\com_pinoox_cms\Cms\Theme\ThemeDefinition;

final readonly class CmsKernelRegistryGateway implements SdkRegistryGatewayInterface
{
    public function __construct(private CmsKernel $kernel) {}

    public function register(OwnedDefinitionInterface $definition, bool $replace = false): void
    {
        match (true) {
            $definition instanceof ExtensionDefinition => $this->kernel->extensions->register($definition, $replace),
            $definition instanceof CapabilityDefinition => $this->kernel->capabilities->register($definition, $replace),
            $definition instanceof SettingDefinition => $this->kernel->settings->register($definition, $replace),
            $definition instanceof FieldDefinition => $this->kernel->fields->register($definition, $replace),
            $definition instanceof TaxonomyDefinition => $this->kernel->taxonomies->register($definition, $replace),
            $definition instanceof ContentTypeDefinition => $this->kernel->contentTypes->register($definition, $replace),
            $definition instanceof FilterDefinition => $this->kernel->filters->register($definition, $replace),
            $definition instanceof ThemeDefinition => $this->kernel->themes->register($definition, $replace),
            $definition instanceof TemplateRuleDefinition => $this->kernel->templateRules->register($definition, $replace),
            $definition instanceof BlockDefinition => $this->kernel->blocks->register($definition, $replace),
            $definition instanceof BlockMigrationDefinition => $this->kernel->blockMigrations->register($definition, $replace),
            $definition instanceof DriverDefinition => $this->kernel->drivers->register($definition, $replace),
            $definition instanceof PerformanceBudgetDefinition => $this->kernel->performanceBudgets->register($definition, $replace),
            $definition instanceof ExtensionPermissionDefinition => $this->kernel->extensionPermissions->register($definition, $replace),
            $definition instanceof AbilityDefinition => $this->kernel->abilities->register($definition, $replace),
            $definition instanceof AdminComponentDefinition => $this->kernel->admin->components->register($definition, $replace),
            $definition instanceof AdminMenuDefinition => $this->kernel->admin->menus->register($definition, $replace),
            $definition instanceof AdminRouteDefinition => $this->kernel->admin->routes->register($definition, $replace),
            $definition instanceof AdminWidgetDefinition => $this->kernel->admin->widgets->register($definition, $replace),
            $definition instanceof AdminPanelDefinition => $this->kernel->admin->panels->register($definition, $replace),
            default => throw new \InvalidArgumentException(
                'Unsupported SDK registry definition: ' . $definition::class
            ),
        };
    }

    public function registerBlockRenderer(
        string $blockType,
        string $owner,
        BlockRendererInterface $renderer,
    ): void {
        $this->kernel->blockRenderers->register($blockType, $owner, $renderer);
    }

    public function validate(): void
    {
        $this->kernel->validateContentSchema();
    }

    public function removeOwner(string $owner): int
    {
        return $this->kernel->unregisterOwner($owner);
    }

    public function diagnostics(): array
    {
        $rows = [];
        foreach ($this->kernel->diagnostics() as $registry => $items) {
            foreach ($items as $item) {
                if (($item['owner'] ?? null) !== null) {
                    $rows[] = ['registry' => $registry] + $item;
                }
            }
        }
        return $rows;
    }
}

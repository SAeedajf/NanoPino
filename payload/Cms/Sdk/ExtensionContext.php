<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Sdk;

use App\com_pinoox_cms\Cms\Ability\AbilityDefinition;
use App\com_pinoox_cms\Cms\Admin\AdminComponentDefinition;
use App\com_pinoox_cms\Cms\Admin\AdminMenuDefinition;
use App\com_pinoox_cms\Cms\Admin\AdminPanelDefinition;
use App\com_pinoox_cms\Cms\Admin\AdminRouteDefinition;
use App\com_pinoox_cms\Cms\Admin\AdminSurface;
use App\com_pinoox_cms\Cms\Admin\AdminWidgetDefinition;
use App\com_pinoox_cms\Cms\Block\BlockDefinition;
use App\com_pinoox_cms\Cms\Block\Migration\BlockMigrationDefinition;
use App\com_pinoox_cms\Cms\Block\Render\BlockRendererInterface;
use App\com_pinoox_cms\Cms\Capability\CapabilityDefinition;
use App\com_pinoox_cms\Cms\Content\ContentTypeDefinition;
use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use App\com_pinoox_cms\Cms\Driver\DriverDefinition;
use App\com_pinoox_cms\Cms\Field\FieldDefinition;
use App\com_pinoox_cms\Cms\Field\FieldStorageStrategy;
use App\com_pinoox_cms\Cms\Field\FieldType;
use App\com_pinoox_cms\Cms\Hook\FilterDefinition;
use App\com_pinoox_cms\Cms\Sdk\Api\SdkApiRoute;
use App\com_pinoox_cms\Cms\Sdk\Native\NativeAppGatewayInterface;
use App\com_pinoox_cms\Cms\Sdk\Registry\SdkRegistryGatewayInterface;
use App\com_pinoox_cms\Cms\Settings\SettingDefinition;
use App\com_pinoox_cms\Cms\Settings\SettingType;
use App\com_pinoox_cms\Cms\Taxonomy\TaxonomyDefinition;
use App\com_pinoox_cms\Cms\Authorization\ScopeType;

final readonly class ExtensionContext
{
    public function __construct(
        private string $owner,
        private string $package,
        private SdkRegistryGatewayInterface $registries,
        private NativeAppGatewayInterface $native,
    ) {
        if ($owner==='' || $package==='') {
            throw new \InvalidArgumentException('SDK Extension owner/package cannot be empty.');
        }
        if ($native->package()!==$package) {
            throw new \InvalidArgumentException('Native AppRegister package does not match SDK Extension package.');
        }
    }

    public function owner(): string { return $this->owner; }
    public function package(): string { return $this->package; }
    public function native(): NativeAppGatewayInterface { return $this->native; }

    public function register(OwnedDefinitionInterface $definition, bool $replace=false): self
    {
        $this->assertOwner($definition->owner());
        $this->registries->register($definition,$replace);
        return $this;
    }

    public function capability(string $key,string $description=''): self
    {
        return $this->register(new CapabilityDefinition($key,$this->owner,$description));
    }

    /**
     * @param list<ScopeType> $scopes
     * @param array<string,mixed> $ui
     */
    public function setting(
        string $key,
        SettingType $type,
        mixed $default=null,
        array $scopes=[ScopeType::Global],
        ?string $readPermission='settings.read',
        ?string $writePermission='settings.manage',
        string $group='general',
        string $label='',
        callable|null $validator=null,
        array $ui=[],
        bool $sensitive=false,
    ): self {
        return $this->register(new SettingDefinition(
            $key,$this->owner,$type,$default,$scopes,$readPermission,$writePermission,
            $group,$label,$validator,$ui,$sensitive
        ));
    }

    /** @param array<string,mixed> $options @param array<string,mixed> $ui */
    public function field(
        string $key,
        FieldType $type,
        string $label,
        FieldStorageStrategy $storage=FieldStorageStrategy::Meta,
        bool $required=false,
        mixed $default=null,
        bool $multiple=false,
        bool $translatable=true,
        array $options=[],
        array $ui=[],
        callable|null $validator=null,
    ): self {
        return $this->register(new FieldDefinition(
            $key,$this->owner,$type,$label,$storage,$required,$default,$multiple,
            $translatable,$options,$ui,$validator
        ));
    }

    /**
     * @param list<string> $contentTypes
     * @param array{read:string,manage:string} $permissions
     */
    public function taxonomy(
        string $key,
        string $label,
        string $singularLabel,
        bool $hierarchical=false,
        array $contentTypes=[],
        array $permissions=['read'=>'taxonomy.read','manage'=>'taxonomy.manage'],
        bool $public=true,
    ): self {
        return $this->register(new TaxonomyDefinition(
            $key,$this->owner,$label,$singularLabel,$hierarchical,$contentTypes,$permissions,$public
        ));
    }

    /**
     * @param list<string> $fields
     * @param list<string> $taxonomies
     * @param array{read:string,create:string,update:string,delete:string,publish:string} $permissions
     * @param array<string,mixed> $rest
     * @param array<string,mixed> $search
     * @param array<string,mixed> $editor
     */
    public function contentType(
        string $key,
        string $label,
        string $singularLabel,
        array $fields=[],
        array $taxonomies=[],
        bool $hierarchical=false,
        bool $revisions=true,
        array $permissions=[
            'read'=>'content.read','create'=>'content.create','update'=>'content.update',
            'delete'=>'content.delete','publish'=>'content.publish',
        ],
        array $rest=['enabled'=>true],
        array $search=['index'=>true],
        array $editor=[],
    ): self {
        return $this->register(new ContentTypeDefinition(
            $key,$this->owner,$label,$singularLabel,$fields,$taxonomies,$hierarchical,
            $revisions,$permissions,$rest,$search,$editor
        ));
    }

    /** @param callable(mixed,array<string,mixed>):mixed $callback */
    public function filter(
        string $hook,
        string $name,
        callable $callback,
        int $priority=10,
    ): self {
        return $this->register(new FilterDefinition($hook,$name,$this->owner,$callback,$priority));
    }

    public function adminComponent(
        string $id,
        string $moduleUrl,
        string $exportName='createComponent',
        array $i18n=[],
    ): self {
        return $this->register(new AdminComponentDefinition(
            $id,$this->owner,$moduleUrl,$exportName,$i18n
        ));
    }

    /** @param array<string,mixed> $meta */
    public function adminRoute(
        string $id,
        string $path,
        string $name,
        string $component,
        ?string $permission=null,
        array $meta=[],
        int $order=100,
    ): self {
        return $this->register(new AdminRouteDefinition(
            $id,$this->owner,$path,$name,$component,$permission,$meta,$order
        ));
    }

    public function adminMenu(
        string $id,
        string $label,
        string $route,
        string $icon='circle',
        ?string $permission=null,
        ?string $parent=null,
        string $section='extensions',
        int $order=100,
    ): self {
        return $this->register(new AdminMenuDefinition(
            $id,$this->owner,$label,$route,$icon,$permission,$parent,$section,$order
        ));
    }

    /** @param array<string,mixed> $props */
    public function adminWidget(
        string $id,
        string $slot,
        string $component,
        ?string $permission=null,
        int $order=100,
        array $props=[],
    ): self {
        return $this->register(new AdminWidgetDefinition(
            $id,$this->owner,$slot,$component,$permission,$order,$props
        ));
    }

    /** @param array<string,mixed> $meta */
    public function adminPanel(
        string $id,
        AdminSurface $surface,
        string $component,
        ?string $permission=null,
        int $order=100,
        array $meta=[],
    ): self {
        return $this->register(new AdminPanelDefinition(
            $id,$this->owner,$surface,$component,$permission,$order,$meta
        ));
    }

    /**
     * @param callable(array<string,mixed>):mixed $executor
     * @param array<string,mixed> $inputSchema
     * @param array<string,mixed> $outputSchema
     */
    public function ability(
        string $id,
        callable $executor,
        string $version='v1',
        ?string $permission=null,
        array $inputSchema=[],
        array $outputSchema=[],
        string $description='',
        bool $idempotent=false,
        bool $audit=true,
    ): self {
        return $this->register(new AbilityDefinition(
            $id,$this->owner,$executor,$version,$permission,$inputSchema,$outputSchema,
            $description,$idempotent,$audit
        ));
    }

    public function block(BlockDefinition $definition, ?BlockRendererInterface $renderer=null): self
    {
        $this->register($definition);
        if ($renderer!==null) {
            $this->assertOwner($definition->owner());
            $this->registries->registerBlockRenderer($definition->identifier(),$this->owner,$renderer);
        }
        return $this;
    }

    /** @param \Closure(array<string,mixed>):array<string,mixed> $migrate */
    public function blockMigration(
        string $id,
        string $blockType,
        int $fromVersion,
        int $toVersion,
        \Closure $migrate,
    ): self {
        return $this->register(new BlockMigrationDefinition(
            $id,$this->owner,$blockType,$fromVersion,$toVersion,$migrate
        ));
    }

    public function driver(DriverDefinition $definition): self
    {
        return $this->register($definition);
    }

    public function apiRoute(SdkApiRoute $route): self
    {
        $expected='/extensions/'.$this->package;
        if ($route->uri!==$expected && !str_starts_with($route->uri,$expected.'/')) {
            throw new \InvalidArgumentException(
                'Extension API route must live under '.$expected
            );
        }
        $this->native->apiRoute($route->toNativeRoute(),$route->version);
        return $this;
    }

    public function action(string $name,array|string|\Closure $handler): self
    {
        $this->native->action($name,$handler);
        return $this;
    }

    public function listen(string $event,callable|array $listener,int $priority=0): self
    {
        $this->native->listen($event,$listener,$priority);
        return $this;
    }

    public function schedule(callable $callback): self
    {
        $this->native->schedule($callback);
        return $this;
    }

    public function when(string $targetPackage,callable $callback): self
    {
        $this->native->when($targetPackage,$callback);
        return $this;
    }

    public function validate(): self
    {
        $this->registries->validate();
        return $this;
    }

    public function unregister(): int
    {
        return $this->registries->removeOwner($this->owner);
    }

    /** @return list<array<string,mixed>> */
    public function diagnostics(): array
    {
        return array_values(array_filter(
            $this->registries->diagnostics(),
            fn(array $row):bool => ($row['owner'] ?? null)===$this->owner,
        ));
    }

    private function assertOwner(string $owner): void
    {
        if ($owner!==$this->owner) {
            throw new \DomainException(sprintf(
                'Extension "%s" cannot register definition owned by "%s".',
                $this->owner,$owner
            ));
        }
    }
}

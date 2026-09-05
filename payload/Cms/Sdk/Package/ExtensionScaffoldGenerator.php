<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Sdk\Package;

use App\com_pinoox_cms\Cms\Extension\ExtensionType;

final class ExtensionScaffoldGenerator
{
    public function generate(ExtensionPackageBlueprint $spec,string $directory):ScaffoldResult
    {
        $root=rtrim($directory,'/\\');
        if ($root==='' || str_contains($root,"\0")) throw new \InvalidArgumentException('Invalid scaffold directory.');
        if (!is_dir($root)&&!mkdir($root,0750,true)&&!is_dir($root)) {
            throw new \RuntimeException('Unable to create scaffold directory.');
        }

        $files=[];
        $this->writePhpArray($root.'/app.php',$spec->toAppConfig());$files[]='app.php';
        $this->write($root.'/README.md',$this->readme($spec));$files[]='README.md';

        if ($spec->type===ExtensionType::Theme) {
            $theme='theme/'.$spec->themeName;
            $this->writePhpArray($root.'/'.$theme.'/config.php',[
                'name'=>$spec->themeName,
                'title'=>$spec->name,
                'description'=>$spec->description,
                'developer'=>$spec->publisher,
                'version-name'=>$spec->version,
                'version-code'=>$spec->versionCode,
                'extends'=>[],
                'cms'=>[
                    'schema'=>1,
                    'design'=>'design.json',
                    'templates'=>['index'=>'index.twig'],
                ],
            ]);
            $files[]=$theme.'/config.php';
            $this->write($root.'/'.$theme.'/index.twig',"<main id=\"main\"><h1>{{ title|default('NanoPino Theme') }}</h1></main>\n");
            $files[]=$theme.'/index.twig';
            $this->write($root.'/'.$theme.'/design.json',json_encode([
                'schema'=>1,
                'colors'=>['primary'=>'#2563eb','surface'=>'#ffffff','text'=>'#111827'],
                'spacing'=>['md'=>'1rem'],
                'rtl'=>true,
            ],JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
            $files[]=$theme.'/design.json';
        } else {
            $namespace='App\\'.$spec->package;
            $extensionClass=$namespace.'\\Extension';
            $boot="<?php\n\nuse App\\com_pinoox_cms\\Cms\\Sdk\\ExtensionSdk;\n"
                ."use Pinoox\\Component\\AppEvent\\AppRegister;\n"
                ."use {$extensionClass};\n\n"
                ."return static function (AppRegister \$register): void {\n"
                ."    ExtensionSdk::boot(\$register, new Extension());\n"
                ."};\n";
            $this->write($root.'/boot.php',$boot);$files[]='boot.php';

            $code=$this->extensionCode($spec);
            $this->write($root.'/Extension.php',$code);$files[]='Extension.php';

            if (in_array($spec->type,[ExtensionType::Plugin,ExtensionType::AdminExtension],true)) {
                $this->write($root.'/admin/dist/page.mjs',$this->adminModuleCode($spec));
                $files[]='admin/dist/page.mjs';
            }

            if ($spec->type===ExtensionType::Module) {
                $this->write($root.'/database/migrations/2026_01_01_000000_example_table.php',$this->migrationCode());
                $files[]='database/migrations/2026_01_01_000000_example_table.php';
            }
            if ($spec->type->requiresBlocksProfile()) {
                $this->write($root.'/blocks/README.md',"Block manifests/resources for {$spec->package} live here.\n");
                $files[]='blocks/README.md';
            }
        }

        $this->write($root.'/extension-manifest.json',json_encode(
            $spec->toPinxManifest(),
            JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
        )."\n");$files[]='extension-manifest.json';

        $this->write($root.'/tests/extension.php',$this->testCode($spec));
        $files[]='tests/extension.php';

        sort($files);
        return new ScaffoldResult($root,$files,$spec->toPinxManifest());
    }

    private function extensionCode(ExtensionPackageBlueprint $spec):string
    {
        $namespace='App\\'.$spec->package;
        $type=$spec->type->value;
        $body=match($spec->type){
            ExtensionType::Plugin => <<<'PHP'
        $componentId = 'extension:'.$context->package().':page';

        $context
            ->capability('example.read', 'Read Example feature.')
            ->setting(
                'example.enabled',
                \App\com_pinoox_cms\Cms\Settings\SettingType::Boolean,
                true,
                label: 'Example Enabled',
            )
            ->filter('content.title', 'decorate-title', static fn (mixed $value): mixed => $value)
            ->adminComponent(
                $componentId,
                \App\com_pinoox_cms\Cms\Admin\AdminAssetUrl::module($context->package(), 'page'),
                i18n: [
                    'fa'=>['page'=>['title'=>'نمونه افزونه','loaded'=>'ماژول مدیریت افزونه از مسیر امن same-origin بارگذاری شد.']],
                    'en'=>['page'=>['title'=>'Example extension','loaded'=>'The Extension Admin module was loaded from a safe same-origin path.']],
                ],
            )
            ->adminRoute('example.page', '/extensions/example', 'example.page', $componentId, 'example.read')
            ->adminMenu('example.menu', 'Example', 'example.page', 'puzzle', 'example.read')
            ->apiRoute(new \App\com_pinoox_cms\Cms\Sdk\Api\SdkApiRoute(
                method: 'GET',
                uri: \App\com_pinoox_cms\Cms\Sdk\Api\SdkApiRoute::extensionUri($context->package(), '/status'),
                action: static fn (): array => ['ok'=>true],
                name: 'example.status',
                permission: 'example.read',
            ));
PHP,
            ExtensionType::Module => <<<'PHP'
        $context
            ->capability('catalog.read', 'Read Catalog.')
            ->capability('catalog.manage', 'Manage Catalog.')
            ->field('catalog.sku', \App\com_pinoox_cms\Cms\Field\FieldType::Text, 'SKU')
            ->contentType(
                'catalog_item',
                'Catalog Items',
                'Catalog Item',
                fields: ['catalog.sku'],
                permissions: [
                    'read'=>'catalog.read',
                    'create'=>'catalog.manage',
                    'update'=>'catalog.manage',
                    'delete'=>'catalog.manage',
                    'publish'=>'catalog.manage',
                ],
            );
PHP,
            ExtensionType::Integration => <<<'PHP'
        $context
            ->capability('integration.sync', 'Run Example integration.')
            ->action('integration.sync', static fn (): array => ['queued'=>true]);
PHP,
            ExtensionType::AdminExtension => <<<'PHP'
        $componentId = 'extension:'.$context->package().':page';

        $context
            ->capability('example.admin', 'Use Example admin extension.')
            ->adminComponent(
                $componentId,
                \App\com_pinoox_cms\Cms\Admin\AdminAssetUrl::module($context->package(), 'page'),
                i18n: [
                    'fa'=>['page'=>['title'=>'نمونه افزونه','loaded'=>'ماژول مدیریت افزونه از مسیر امن same-origin بارگذاری شد.']],
                    'en'=>['page'=>['title'=>'Example extension','loaded'=>'The Extension Admin module was loaded from a safe same-origin path.']],
                ],
            )
            ->adminRoute('example.admin', '/extensions/example-admin', 'example.admin', $componentId, 'example.admin')
            ->adminMenu('example.admin.menu', 'Example Admin', 'example.admin', 'layout-panel-top', 'example.admin');
PHP,
            ExtensionType::Block, ExtensionType::BlockPackage => <<<'PHP'
        $context->capability('example.blocks.use', 'Use Example blocks.');

        $block = new \App\com_pinoox_cms\Cms\Block\BlockDefinition(
            id: 'example/notice',
            ownerId: $context->owner(),
            name: 'example-notice',
            title: 'Example Notice',
            category: 'example',
            icon: 'info',
            schemaVersion: 1,
            version: '1.0.0',
            attributes: [
                'text' => new \App\com_pinoox_cms\Cms\Block\BlockAttributeDefinition(
                    'text',
                    \App\com_pinoox_cms\Cms\Block\BlockAttributeType::String,
                    true,
                    'Notice',
                ),
            ],
            permissions: ['example.blocks.use'],
        );

        $renderer = new class implements \App\com_pinoox_cms\Cms\Block\Render\BlockRendererInterface {
            public function supports(string $blockType): bool
            {
                return $blockType === 'example/notice';
            }

            public function render(
                \App\com_pinoox_cms\Cms\Block\Document\BlockNode $node,
                \App\com_pinoox_cms\Cms\Block\Render\BlockRenderContext $context,
                array $childrenHtml,
            ): \App\com_pinoox_cms\Cms\Block\Render\RenderedBlock {
                $text = htmlspecialchars((string)($node->attributes['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                return new \App\com_pinoox_cms\Cms\Block\Render\RenderedBlock('<aside class="example-notice">'.$text.'</aside>');
            }
        };

        $context->block($block, $renderer);
PHP,
            ExtensionType::Driver => <<<'PHP'
        $context->capability('example.driver.use', 'Use Example driver.');
PHP,
            ExtensionType::LanguagePack => <<<'PHP'
        $context->capability('example.language.read', 'Read Example language resources.');
PHP,
            default => "        // Register Extension definitions through \$context.\n",
        };

        return "<?php\n"
            ."declare(strict_types=1);\n\n"
            ."namespace {$namespace};\n\n"
            ."use App\\com_pinoox_cms\\Cms\\Sdk\\Contracts\\CmsExtensionInterface;\n"
            ."use App\\com_pinoox_cms\\Cms\\Sdk\\ExtensionContext;\n\n"
            ."final class Extension implements CmsExtensionInterface\n{\n"
            ."    public function register(ExtensionContext \$context): void\n    {\n"
            ."        // SDK semantic type: {$type}\n"
            .$body."\n"
            ."    }\n"
            ."}\n";
    }

    private function migrationCode():string
    {
        return <<<'PHP'
<?php
declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Pinoox\Component\Migration\MigrationBase;

return new class extends MigrationBase {
    public function up(): void
    {
        $this->schema->create('example_items', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name', 190);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('example_items');
    }
};
PHP;
    }

    private function adminModuleCode(ExtensionPackageBlueprint $spec):string
    {
        return "export function createComponent({ h, LPage, LPanel, LBadge, i18n }) {\n"
            ."  const tr = i18n.extensionT\n"
            ."  return {\n"
            ."    name: 'CmsExtensionPage',\n"
            ."    render() {\n"
            ."      return h(LPage, { icon: 'puzzle' }, {\n"
            ."        default: () => h(LPanel, {}, {\n"
            ."          header: () => tr('page.title', {}, 'Extension'),\n"
            ."          default: () => [\n"
            ."            h(LBadge, { severity: 'success' }, { default: () => 'SDK v1' }),\n"
            ."            h('p', { class: 'cms-muted' }, tr('page.loaded')),\n"
            ."          ],\n"
            ."        }),\n"
            ."      })\n"
            ."    },\n"
            ."  }\n"
            ."}\n";
    }

    private function testCode(ExtensionPackageBlueprint $spec):string
    {
        if ($spec->type===ExtensionType::Theme) {
            return <<<'PHP'
<?php
declare(strict_types=1);

$manifest=json_decode((string)file_get_contents(dirname(__DIR__).'/extension-manifest.json'),true,512,JSON_THROW_ON_ERROR);
assert(($manifest['type']??null)==='theme');
assert(($manifest['cms']['extension_type']??null)==='theme');
echo "Theme starter manifest OK\n";
PHP;
        }

        return "<?php\n"
            ."declare(strict_types=1);\n\n"
            ."// PINOOX_CMS_ROOT may point to the installed/development CMS package.\n"
            ."\$cmsRoot = getenv('PINOOX_CMS_ROOT') ?: dirname(__DIR__, 4);\n"
            ."require \$cmsRoot.'/tests/bootstrap.php';\n"
            ."require dirname(__DIR__).'/Extension.php';\n\n"
            ."\$harness = new \\App\\com_pinoox_cms\\Cms\\Sdk\\Testing\\ExtensionTestHarness();\n"
            ."\$result = \$harness->run('{$spec->package}', new \\App\\{$spec->package}\\Extension());\n"
            ."assert(is_array(\$result->definitions));\n"
            ."assert(is_array(\$result->nativeCalls));\n"
            ."echo \"Extension SDK starter OK\\n\";\n";
    }

    private function readme(ExtensionPackageBlueprint $spec):string
    {
        return "# {$spec->name}\n\n"
            ."Generated by NanoPino Developer SDK.\n\n"
            ."- Package: `{$spec->package}`\n"
            ."- Type: `{$spec->type->value}`\n"
            ."- Pincore: `>=3.8.15`\n"
            ."- CMS: `>=0.21.0`\n"
            ."- Core edits: forbidden\n";
    }

    /** @param array<string,mixed> $data */
    private function writePhpArray(string $file,array $data):void
    {
        $this->write($file,"<?php\n\nreturn ".var_export($data,true).";\n");
    }

    private function write(string $file,string $content):void
    {
        $dir=dirname($file);
        if(!is_dir($dir)&&!mkdir($dir,0750,true)&&!is_dir($dir))throw new \RuntimeException('Unable to create scaffold subdirectory.');
        if(file_put_contents($file,$content,LOCK_EX)===false)throw new \RuntimeException('Unable to write scaffold file.');
    }
}

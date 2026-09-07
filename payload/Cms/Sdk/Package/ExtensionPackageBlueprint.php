<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Sdk\Package;

use App\com_pinoox_cms\Cms\Extension\ExtensionType;
use App\com_pinoox_cms\Cms\Manifest\ExtensionManifestValidator;

final readonly class ExtensionPackageBlueprint
{
    /**
     * @param list<string> $permissions
     * @param list<string> $capabilities
     * @param list<string> $hooks
     * @param array<string,string> $dependencies
     * @param array<string,string> $optionalDependencies
     */
    public function __construct(
        public string $package,
        public string $name,
        public ExtensionType $type,
        public string $version='0.1.0',
        public int $versionCode=100,
        public string $publisher='example-developer',
        public string $description='',
        public array $permissions=[],
        public array $capabilities=[],
        public array $hooks=[],
        public array $dependencies=[],
        public array $optionalDependencies=[],
        public ?string $targetApp=null,
        public ?string $themeName=null,
    ) {
        if (preg_match('/^com_[a-z0-9][a-z0-9_]*$/',$package)!==1) {
            throw new \InvalidArgumentException('Invalid Pinoox package id.');
        }
        if ($name==='' || strlen($name)>120) throw new \InvalidArgumentException('Invalid Extension name.');
        if ($versionCode<1 || $version==='') throw new \InvalidArgumentException('Invalid Extension version.');
        if (preg_match('/^[a-z0-9][a-z0-9._-]{1,126}$/',$publisher)!==1) {
            throw new \InvalidArgumentException('Invalid publisher id.');
        }
        if ($type===ExtensionType::Theme) {
            if ($targetApp===null || preg_match('/^com_[a-z0-9][a-z0-9_]*$/',$targetApp)!==1) {
                throw new \InvalidArgumentException('Theme target app is required.');
            }
            if ($themeName===null || preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/',$themeName)!==1) {
                throw new \InvalidArgumentException('Theme name is required.');
            }
        }
    }

    /** @return array<string,mixed> */
    public function cmsProfile():array
    {
        $dependencies=$this->dependencies;
        if ($this->type!==ExtensionType::CoreModule) {
            $dependencies['com_pinoox_cms'] ??= '>=0.21.0';
        }

        $cms=[
            'schema'=>1,
            'extension_type'=>$this->type->value,
            'publisher'=>$this->publisher,
            'requires'=>[
                'php'=>'>=8.2',
                'pincore'=>'>=3.14.0',
                'cms'=>'>=0.21.0',
                'luma'=>'>=0.4.10',
            ],
            'dependencies'=>$dependencies,
            'optional_dependencies'=>$this->optionalDependencies,
            'conflicts'=>[],
            'provides'=>[],
            'replaces'=>[],
            'permissions'=>$this->permissions,
            'services'=>[],
            'capabilities'=>$this->capabilities,
            'abilities'=>[],
            'hooks'=>$this->hooks,
            'admin'=>[],
            'api'=>[
                'base'=>$this->type===ExtensionType::Theme
                    ? null
                    : '/api/v1/extensions/'.$this->package,
                'version'=>'v1',
            ],
            'frontend'=>[],
        ];
        if ($this->type->requiresBlocksProfile()) {
            $cms['blocks']=['directory'=>'blocks','max_blocks'=>64];
        }
        if ($this->type===ExtensionType::Theme) {
            $cms['theme']=['name'=>$this->themeName,'target_app'=>$this->targetApp];
        }
        return $cms;
    }

    /** @return array<string,mixed> */
    public function toAppConfig():array
    {
        return [
            'package'=>$this->package,
            'enable'=>true,
            'hidden'=>$this->type!==ExtensionType::Theme,
            'dock'=>false,
            'name'=>$this->name,
            'title'=>$this->name,
            'description'=>$this->description,
            'developer'=>$this->publisher,
            'version-name'=>$this->version,
            'version-code'=>$this->versionCode,
            'minpin'=>232,
            'boot'=>$this->type!==ExtensionType::Theme,
            'boot-global'=>false,
            'extends'=>$this->type===ExtensionType::Theme ? [] : ['com_pinoox_cms'],
            'depends'=>$this->type===ExtensionType::CoreModule ? [] : [
                'com_pinoox_cms'=>['min_code'=>2100],
            ],
            'theme'=>$this->type===ExtensionType::Theme ? $this->themeName : 'default',
            'cms'=>$this->cmsProfile(),
            'pinx'=>[
                'type'=>$this->type===ExtensionType::Theme ? 'theme' : 'app',
                'target_app'=>$this->targetApp,
                'theme_name'=>$this->themeName,
                'minpin'=>232,
            ],
            'build'=>[
                'exclude'=>['tests','docs','.git','.github'],
                'include_themes'=>$this->type===ExtensionType::Theme ? [$this->themeName] : [],
            ],
        ];
    }

    /** @return array<string,mixed> */
    public function toPinxManifest():array
    {
        $theme=$this->type===ExtensionType::Theme;
        $manifest=[
            'format'=>'pinx',
            'format_version'=>1,
            'type'=>$theme?'theme':'app',
            'package'=>$theme ? (string)$this->themeName : $this->package,
            'name'=>$this->name,
            'description'=>$this->description,
            'developer'=>$this->publisher,
            'version_name'=>$this->version,
            'version_code'=>$this->versionCode,
            'minpin'=>232,
            'depends'=>$this->type===ExtensionType::CoreModule ? [] : ['com_pinoox_cms'=>['min_code'=>2100]],
            'target_app'=>$theme?$this->targetApp:null,
            'theme_name'=>$theme?$this->themeName:null,
            'cms'=>$this->cmsProfile(),
        ];
        (new ExtensionManifestValidator())->validate($manifest);
        return $manifest;
    }
}

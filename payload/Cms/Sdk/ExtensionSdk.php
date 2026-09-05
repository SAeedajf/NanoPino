<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Sdk;

use App\com_pinoox_cms\Cms\Kernel\CmsKernel;
use App\com_pinoox_cms\Cms\Sdk\Contracts\CmsExtensionInterface;
use App\com_pinoox_cms\Cms\Sdk\Native\PinooxAppRegisterGateway;
use App\com_pinoox_cms\Cms\Sdk\Registry\CmsKernelRegistryGateway;
use Pinoox\Component\AppEvent\AppRegister;

final class ExtensionSdk
{
    public const VERSION='v1';

    public static function boot(
        AppRegister $register,
        CmsExtensionInterface $extension,
        ?string $owner=null,
    ): ExtensionContext {
        $package=$register->package();
        $context=new ExtensionContext(
            owner:$owner ?? $package,
            package:$package,
            registries:new CmsKernelRegistryGateway(CmsKernel::instance()),
            native:new PinooxAppRegisterGateway($register),
        );
        $extension->register($context);
        $context->validate();
        return $context;
    }
}

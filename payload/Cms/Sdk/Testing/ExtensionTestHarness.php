<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Sdk\Testing;

use App\com_pinoox_cms\Cms\Sdk\Contracts\CmsExtensionInterface;
use App\com_pinoox_cms\Cms\Sdk\ExtensionContext;
use App\com_pinoox_cms\Cms\Sdk\Native\InMemoryNativeAppGateway;
use App\com_pinoox_cms\Cms\Sdk\Registry\InMemorySdkRegistryGateway;

final class ExtensionTestHarness
{
    public function run(string $package,CmsExtensionInterface $extension):ExtensionTestResult
    {
        $registries=new InMemorySdkRegistryGateway();
        $native=new InMemoryNativeAppGateway($package);
        $context=new ExtensionContext($package,$package,$registries,$native);
        $extension->register($context);
        $context->validate();

        return new ExtensionTestResult(
            definitions:$registries->definitions(),
            nativeCalls:$native->calls,
            diagnostics:$context->diagnostics(),
        );
    }
}

<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter;

use App\com_pinoox_cms\Cms\Extension\ExtensionDefinition;

final class InMemoryExtensionCenterSignalProvider implements ExtensionCenterSignalProviderInterface
{
    /** @var array<string,ExtensionCenterSignals> */
    private array $signals = [];

    public function set(string $extensionId, ExtensionCenterSignals $signals): void
    {
        $this->signals[$extensionId] = $signals;
    }

    public function for(ExtensionDefinition $extension): ExtensionCenterSignals
    {
        return $this->signals[$extension->identifier()] ?? new ExtensionCenterSignals();
    }
}

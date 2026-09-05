<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter;

use App\com_pinoox_cms\Cms\Extension\ExtensionDefinition;

interface ExtensionCenterSignalProviderInterface
{
    public function for(ExtensionDefinition $extension): ExtensionCenterSignals;
}

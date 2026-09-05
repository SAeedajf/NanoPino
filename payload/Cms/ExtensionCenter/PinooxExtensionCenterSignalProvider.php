<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter;

use App\com_pinoox_cms\Cms\Extension\ExtensionDefinition;
use Pinoox\Portal\App\AppEngine;

final class PinooxExtensionCenterSignalProvider implements ExtensionCenterSignalProviderInterface
{
    public function for(ExtensionDefinition $extension): ExtensionCenterSignals
    {
        try {
            if (!AppEngine::exists($extension->package())) {
                return new ExtensionCenterSignals(
                    ExtensionCenterStatus::Failed,
                    [new ExtensionProblem(
                        'extension.runtime_missing',
                        ExtensionProblemSeverity::Critical,
                        'Extension is registered in CMS but the Pinoox app is missing.',
                        true,
                    )],
                );
            }

            return new ExtensionCenterSignals(
                AppEngine::stable($extension->package())
                    ? ExtensionCenterStatus::Active
                    : ExtensionCenterStatus::Inactive,
            );
        } catch (\Throwable) {
            return new ExtensionCenterSignals(
                ExtensionCenterStatus::Failed,
                [new ExtensionProblem(
                    'extension.runtime_probe_failed',
                    ExtensionProblemSeverity::Error,
                    'Pinoox runtime status could not be read.',
                    true,
                )],
            );
        }
    }
}

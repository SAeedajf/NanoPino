<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter;

use App\com_pinoox_cms\Cms\Extension\ExtensionDefinition;
use App\com_pinoox_cms\Cms\Extension\ExtensionRegistry;
use App\com_pinoox_cms\Cms\Recovery\SafeModeState;

final readonly class ExtensionCenterCatalogService
{
    public function __construct(
        private ExtensionRegistry $extensions,
        private ExtensionCenterSignalProviderInterface $signals,
    ) {}

    /** @return list<ExtensionCenterItem> */
    public function catalog(?SafeModeState $safeMode = null): array
    {
        $items = [];

        foreach ($this->extensions->all() as $definition) {
            if (!$definition instanceof ExtensionDefinition) continue;

            $signal = $this->signals->for($definition);
            $status = $signal->status;
            $problems = $signal->problems;

            if (
                $safeMode !== null
                && in_array($definition->identifier(), $safeMode->quarantined, true)
            ) {
                $status = ExtensionCenterStatus::Quarantined;
                $problems[] = new ExtensionProblem(
                    'extension.quarantined',
                    ExtensionProblemSeverity::Critical,
                    'Extension is quarantined by CMS Safe Mode.',
                    true,
                    ['recovery_point_id' => $safeMode->recoveryPointId],
                );
            }

            usort($problems, static fn (ExtensionProblem $a, ExtensionProblem $b): int =>
                $b->severity->value <=> $a->severity->value
            );

            $items[] = new ExtensionCenterItem(
                $definition->identifier(),
                $definition->package(),
                $definition->package(),
                $definition->type(),
                $definition->version(),
                $definition->publisher(),
                $status,
                $problems,
                $signal->trust,
                $signal->update,
                $signal->developerMode,
            );
        }

        usort($items, static fn (ExtensionCenterItem $a, ExtensionCenterItem $b): int =>
            [$a->type->value, strtolower($a->name), $a->id]
            <=>
            [$b->type->value, strtolower($b->name), $b->id]
        );

        return $items;
    }
}

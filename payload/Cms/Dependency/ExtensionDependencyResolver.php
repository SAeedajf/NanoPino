<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Dependency;

use App\com_pinoox_cms\Cms\Exception\DependencyCycleException;
use App\com_pinoox_cms\Cms\Manifest\ExtensionDependencyRule;
use App\com_pinoox_cms\Cms\Manifest\ExtensionManifest;

final readonly class ExtensionDependencyResolver
{
    private VersionConstraintEvaluatorInterface $versions;

    public function __construct(
        ?VersionConstraintEvaluatorInterface $versions = null,
        private NativePinxDependencyNormalizer $native = new NativePinxDependencyNormalizer(),
    ) {
        $this->versions = $versions ?? VersionConstraintEvaluatorFactory::make();
    }

    public function resolve(ExtensionManifest $incoming, ExtensionCatalog $installed): DependencyResolution
    {
        $issues = [];

        foreach ($this->native->normalize($incoming->pinx()['depends'] ?? []) as $rule) {
            $matches = array_values(array_filter(
                $installed->find($rule->package),
                static fn (InstalledExtension $item): bool => $item->package === $rule->package,
            ));
            if ($matches === []) {
                $issues[] = new DependencyIssue(
                    $rule->optional ? 'native_optional_missing' : 'native_missing',
                    $rule->package,
                    ($rule->optional ? 'Optional' : 'Required') . ' Pinoox app is not installed: ' . $rule->package,
                    !$rule->optional,
                );
                continue;
            }
            if ($rule->minCode !== null && max(array_map(static fn ($i) => $i->versionCode, $matches)) < $rule->minCode) {
                $issues[] = new DependencyIssue(
                    $rule->optional ? 'native_optional_incompatible' : 'native_incompatible',
                    $rule->package,
                    'Pinoox app version-code constraint is not satisfied: ' . $rule->package,
                    !$rule->optional,
                );
            }
        }

        foreach ($incoming->dependencies() as $rule) {
            $this->evaluateCmsRule($rule, $installed, $issues, false);
        }
        foreach ($incoming->optionalDependencies() as $rule) {
            $this->evaluateCmsRule($rule, $installed, $issues, true);
        }

        foreach ($incoming->conflicts() as $target => $constraint) {
            foreach ($installed->find($target) as $item) {
                if ($this->versions->satisfies($item->version, $constraint)) {
                    $issues[] = new DependencyIssue(
                        'conflict',
                        $target,
                        sprintf('Installed extension %s %s conflicts with incoming %s.', $item->identifier, $item->version, $incoming->identifier()),
                        true,
                    );
                    break;
                }
            }
        }

        foreach ($incoming->replaces() as $target) {
            if ($installed->has($target)) {
                $issues[] = new DependencyIssue(
                    'replacement_required',
                    $target,
                    'Incoming extension declares replacement of an installed target; explicit replacement planning is required: ' . $target,
                    true,
                );
            }
        }

        return new DependencyResolution($issues);
    }

    /**
     * @param list<ExtensionManifest> $manifests
     * @return list<string> Ordered CMS extension identifiers.
     */
    public function installOrder(array $manifests): array
    {
        $byId = [];
        $targets = [];
        foreach ($manifests as $manifest) {
            $byId[$manifest->identifier()] = $manifest;
            foreach (array_unique(array_merge([$manifest->identifier(), $manifest->package()], $manifest->provides(), $manifest->replaces())) as $target) {
                $targets[$target][] = $manifest->identifier();
            }
        }

        $incoming = array_fill_keys(array_keys($byId), 0);
        $dependents = array_fill_keys(array_keys($byId), []);

        foreach ($byId as $id => $manifest) {
            $requiredTargets = [];
            foreach ($manifest->dependencies() as $rule) {
                $requiredTargets[] = $rule->package;
            }
            foreach ($this->native->normalize($manifest->pinx()['depends'] ?? []) as $rule) {
                if (!$rule->optional) {
                    $requiredTargets[] = $rule->package;
                }
            }

            foreach (array_unique($requiredTargets) as $target) {
                foreach ($targets[$target] ?? [] as $dependencyId) {
                    if ($dependencyId === $id) {
                        continue;
                    }
                    $dependents[$dependencyId][] = $id;
                    ++$incoming[$id];
                    break;
                }
            }
        }

        $queue = array_keys(array_filter($incoming, static fn (int $count): bool => $count === 0));
        sort($queue);
        $ordered = [];
        while ($queue !== []) {
            $id = array_shift($queue);
            $ordered[] = $id;
            foreach (array_unique($dependents[$id]) as $dependent) {
                --$incoming[$dependent];
                if ($incoming[$dependent] === 0) {
                    $queue[] = $dependent;
                    sort($queue);
                }
            }
        }

        if (count($ordered) !== count($byId)) {
            throw new DependencyCycleException(array_values(array_diff(array_keys($byId), $ordered)));
        }

        return $ordered;
    }

    /** @param list<DependencyIssue> $issues */
    private function evaluateCmsRule(ExtensionDependencyRule $rule, ExtensionCatalog $installed, array &$issues, bool $optional): void
    {
        $matches = $installed->find($rule->package);
        if ($matches === []) {
            $issues[] = new DependencyIssue(
                $optional ? 'optional_missing' : 'missing',
                $rule->package,
                ($optional ? 'Optional' : 'Required') . ' CMS dependency is not installed: ' . $rule->package,
                !$optional,
            );
            return;
        }

        foreach ($matches as $item) {
            if ($this->versions->satisfies($item->version, $rule->constraint)) {
                return;
            }
        }

        $issues[] = new DependencyIssue(
            $optional ? 'optional_incompatible' : 'incompatible',
            $rule->package,
            'CMS dependency version constraint is not satisfied: ' . $rule->package . ' ' . $rule->constraint,
            !$optional,
        );
    }
}

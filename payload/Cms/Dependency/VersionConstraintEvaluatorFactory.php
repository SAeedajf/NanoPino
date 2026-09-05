<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Dependency;

final class VersionConstraintEvaluatorFactory
{
    public static function make(): VersionConstraintEvaluatorInterface
    {
        return class_exists(\Composer\Semver\Semver::class)
            ? new ComposerSemverConstraintEvaluator()
            : new PortableSemverConstraintEvaluator();
    }
}

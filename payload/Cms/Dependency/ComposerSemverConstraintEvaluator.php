<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Dependency;

use LogicException;

final class ComposerSemverConstraintEvaluator implements VersionConstraintEvaluatorInterface
{
    public function satisfies(string $version, string $constraint): bool
    {
        if (!class_exists(\Composer\Semver\Semver::class)) {
            throw new LogicException('composer/semver is not available. Install the app Composer dependencies.');
        }

        return \Composer\Semver\Semver::satisfies($version, $constraint);
    }
}

<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Dependency;

interface VersionConstraintEvaluatorInterface
{
    public function satisfies(string $version, string $constraint): bool;
}

<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\Package;

final readonly class StaticRiskFinding
{
    public function __construct(
        public string $path,
        public string $rule,
        public StaticRiskSeverity $severity,
        public string $message,
    ) {}
}

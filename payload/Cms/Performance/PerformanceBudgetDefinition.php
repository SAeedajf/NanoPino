<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Performance;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;

final readonly class PerformanceBudgetDefinition implements OwnedDefinitionInterface
{
    public function __construct(
        private string $id,
        private string $owner,
        public string $label,
        public PerformanceMetric $metric,
        public float $target,
        public float $limit,
        public bool $higherIsBetter = false,
        public string $unit = '',
        public string $profile = 'shared_hosting',
    ) {
        if (preg_match('/^[a-z][a-z0-9._-]{1,127}$/',$id)!==1) {
            throw new \InvalidArgumentException('Invalid performance budget identifier.');
        }
        if ($target < 0 || $limit < 0) {
            throw new \InvalidArgumentException('Performance budget values cannot be negative.');
        }
        if (!$higherIsBetter && $limit < $target) {
            throw new \InvalidArgumentException('Upper-bound budget limit must be >= target.');
        }
        if ($higherIsBetter && $limit > $target) {
            throw new \InvalidArgumentException('Lower-bound budget limit must be <= target.');
        }
    }

    public function identifier(): string { return $this->id; }
    public function owner(): string { return $this->owner; }
}

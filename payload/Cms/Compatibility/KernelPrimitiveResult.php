<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Compatibility;

final readonly class KernelPrimitiveResult
{
    /** @param list<string> $missingMethods */
    public function __construct(
        public KernelPrimitiveRequirement $requirement,
        public bool $classAvailable,
        public array $missingMethods=[],
    ) {}

    public function available(): bool
    {
        return $this->classAvailable && $this->missingMethods===[];
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id'=>$this->requirement->id,
            'class'=>$this->requirement->class,
            'feature'=>$this->requirement->feature,
            'required'=>$this->requirement->required,
            'available'=>$this->available(),
            'missing_methods'=>$this->missingMethods,
        ];
    }
}

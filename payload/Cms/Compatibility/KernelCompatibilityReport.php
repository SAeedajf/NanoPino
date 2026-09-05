<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Compatibility;

final readonly class KernelCompatibilityReport
{
    /** @param list<KernelPrimitiveResult> $primitives */
    public function __construct(
        public int $minimumCode,
        public string $minimumVersion,
        public ?int $currentCode,
        public ?string $currentVersion,
        public array $primitives,
    ) {}

    public function compatible(): bool
    {
        if ($this->currentCode!==null && $this->currentCode<$this->minimumCode) return false;
        foreach ($this->primitives as $result) {
            if ($result->requirement->required && !$result->available()) return false;
        }
        return true;
    }

    /** @return list<string> */
    public function unavailableOptionalFeatures(): array
    {
        $features=[];
        foreach ($this->primitives as $result) {
            if (!$result->requirement->required && !$result->available()) {
                $features[]=$result->requirement->feature;
            }
        }
        return array_values(array_unique($features));
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'compatible'=>$this->compatible(),
            'minimum'=>['version'=>$this->minimumVersion,'code'=>$this->minimumCode],
            'current'=>['version'=>$this->currentVersion,'code'=>$this->currentCode],
            'optional_features_unavailable'=>$this->unavailableOptionalFeatures(),
            'primitives'=>array_map(static fn(KernelPrimitiveResult $r):array=>$r->toArray(),$this->primitives),
        ];
    }
}

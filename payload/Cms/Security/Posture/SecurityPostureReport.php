<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\Posture;

final readonly class SecurityPostureReport
{
    /** @param list<SecurityControl> $controls */
    public function __construct(public array $controls) {}

    public function status(): SecurityControlStatus
    {
        $worst = SecurityControlStatus::Pass;
        foreach ($this->controls as $control) {
            if ($control->status->rank() > $worst->rank()) $worst = $control->status;
        }
        return $worst;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        $counts=['pass'=>0,'warning'=>0,'fail'=>0];
        foreach ($this->controls as $control) $counts[$control->status->value]++;

        return [
            'status'=>$this->status()->value,
            'counts'=>$counts,
            'controls'=>array_map(static fn(SecurityControl $c):array=>$c->toArray(),$this->controls),
        ];
    }
}

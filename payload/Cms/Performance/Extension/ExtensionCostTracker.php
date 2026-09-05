<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Performance\Extension;

final class ExtensionCostTracker
{
    /** @var array<string,ExtensionCostRecord> */
    private array $records=[];

    public function measure(string $extensionId,callable $boot,int $registrationsBefore=0,int $registrationsAfter=0): mixed
    {
        if ($extensionId==='' || strlen($extensionId)>190) {
            throw new \InvalidArgumentException('Invalid Extension performance identity.');
        }

        $memory=memory_get_usage(true);
        $start=hrtime(true);
        $result=$boot();
        $elapsed=(hrtime(true)-$start)/1_000_000;

        $this->records[$extensionId]=new ExtensionCostRecord(
            $extensionId,
            $elapsed,
            memory_get_usage(true)-$memory,
            max(0,$registrationsAfter-$registrationsBefore),
        );

        return $result;
    }

    /** @return list<ExtensionCostRecord> */
    public function records(): array
    {
        $rows=array_values($this->records);
        usort($rows,static fn($a,$b):int=>$b->bootMs<=>$a->bootMs);
        return $rows;
    }
}

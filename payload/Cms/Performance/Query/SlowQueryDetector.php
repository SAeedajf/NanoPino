<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Performance\Query;

final readonly class SlowQueryDetector
{
    public function __construct(private float $thresholdMs=100.0) {}

    /** @return list<QueryObservation> */
    public function detect(QueryProbeInterface $probe): array
    {
        $rows=array_values(array_filter(
            $probe->observations(),
            fn(QueryObservation $q):bool=>$q->durationMs >= $this->thresholdMs,
        ));
        usort($rows,static fn($a,$b):int=>$b->durationMs<=>$a->durationMs);
        return $rows;
    }
}

<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Performance\Query;

final readonly class NPlusOneDetector
{
    public function __construct(private int $minimumRepeats=5)
    {
        if ($minimumRepeats<2 || $minimumRepeats>1000) {
            throw new \InvalidArgumentException('Invalid N+1 repeat threshold.');
        }
    }

    /** @return list<NPlusOneFinding> */
    public function detect(QueryProbeInterface $probe): array
    {
        $groups=[];
        foreach ($probe->observations() as $query) {
            $fp=QueryFingerprint::fromSql($query->sql);
            if (!isset($groups[$fp])) {
                $groups[$fp]=['count'=>0,'total'=>0.0,'sample'=>QueryFingerprint::normalize($query->sql)];
            }
            $groups[$fp]['count']++;
            $groups[$fp]['total']+=$query->durationMs;
        }

        $findings=[];
        foreach ($groups as $fp=>$row) {
            if ($row['count'] < $this->minimumRepeats) continue;
            $findings[]=new NPlusOneFinding($fp,$row['count'],$row['total'],$row['sample']);
        }
        usort($findings,static fn($a,$b):int=>$b->count<=>$a->count ?: $b->totalMs<=>$a->totalMs);
        return $findings;
    }
}

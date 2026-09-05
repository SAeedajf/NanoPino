<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Performance;

use App\com_pinoox_cms\Cms\Performance\Cache\CacheEffectivenessTracker;
use App\com_pinoox_cms\Cms\Performance\Extension\ExtensionCostTracker;
use App\com_pinoox_cms\Cms\Performance\Memory\MemoryProbe;
use App\com_pinoox_cms\Cms\Performance\Query\NPlusOneDetector;
use App\com_pinoox_cms\Cms\Performance\Query\QueryProbeInterface;
use App\com_pinoox_cms\Cms\Performance\Query\QueryFingerprint;
use App\com_pinoox_cms\Cms\Performance\Query\SlowQueryDetector;

final readonly class PerformanceSnapshotService
{
    public function __construct(
        private PerformanceBudgetRegistry $budgets,
        private PerformanceRecorderInterface $recorder,
        private QueryProbeInterface $queries,
        private CacheEffectivenessTracker $cache,
        private ExtensionCostTracker $extensions,
        private MemoryProbe $memory = new MemoryProbe(),
        private BudgetEvaluator $evaluator = new BudgetEvaluator(),
        private NPlusOneDetector $nPlusOne = new NPlusOneDetector(),
        private SlowQueryDetector $slowQueries = new SlowQueryDetector(),
    ) {}

    /** @return array<string,mixed> */
    public function snapshot(string $profile='shared_hosting'): array
    {
        $values=$this->latestMetricValues();
        $values[PerformanceMetric::DbQueries->value]=(float)$this->queries->count();
        $values[PerformanceMetric::DbMs->value]=$this->queries->totalMs();

        $ratio=$this->cache->ratio();
        if ($ratio!==null) $values[PerformanceMetric::CacheHitRatio->value]=$ratio;

        $memory=$this->memory->snapshot();
        $values[PerformanceMetric::MemoryPeakBytes->value]=(float)$memory->realPeakBytes;

        $evaluations=[];
        foreach ($this->budgets->forProfile($profile) as $budget) {
            $evaluations[]=$this->evaluator->evaluate(
                $budget,
                $values[$budget->metric->value]??null,
            )->toArray();
        }

        $extensions=array_map(static fn($r):array=>[
            'extension_id'=>$r->extensionId,
            'boot_ms'=>$r->bootMs,
            'memory_delta_bytes'=>$r->memoryDeltaBytes,
            'registrations'=>$r->registrations,
        ],$this->extensions->records());

        $n1=array_map(static fn($f):array=>[
            'fingerprint'=>$f->fingerprint,
            'count'=>$f->count,
            'total_ms'=>$f->totalMs,
            'sample_sql'=>$f->sampleSql,
        ],$this->nPlusOne->detect($this->queries));

        $slow=array_map(static fn($q):array=>[
            'duration_ms'=>$q->durationMs,
            'fingerprint'=>QueryFingerprint::fromSql($q->sql),
            'normalized_sql'=>QueryFingerprint::normalize($q->sql),
        ],$this->slowQueries->detect($this->queries));

        return [
            'profile'=>$profile,
            'budget_evaluations'=>$evaluations,
            'metrics'=>$values,
            'memory'=>$memory->toArray(),
            'queries'=>[
                'bound'=>$this->queries->isBound(),
                'binding_error'=>$this->queries->bindingError(),
                'count'=>$this->queries->count(),
                'total_ms'=>$this->queries->totalMs(),
                'n_plus_one'=>$n1,
                'slow'=>$slow,
            ],
            'cache'=>$this->cache->snapshot(),
            'extensions'=>$extensions,
            'recent_samples'=>array_map(
                static fn(PerformanceSample $s):array=>$s->toArray(),
                $this->recorder->recent(50),
            ),
        ];
    }

    /** @return array<string,float> */
    private function latestMetricValues(): array
    {
        $values=[];
        foreach (PerformanceMetric::cases() as $metric) {
            $rows=$this->recorder->forMetric($metric,1);
            if ($rows!==[]) $values[$metric->value]=$rows[0]->value;
        }

        $extensionRows=$this->extensions->records();
        if ($extensionRows!==[]) {
            $values[PerformanceMetric::ExtensionBootMs->value]=max(
                array_map(static fn($r):float=>$r->bootMs,$extensionRows)
            );
        }

        return $values;
    }
}

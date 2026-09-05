<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Performance;

use App\com_pinoox_cms\Cms\Health\HealthCheckDefinition;
use App\com_pinoox_cms\Cms\Health\HealthCheckRegistry;

final readonly class PerformanceHealthRegistrar
{
    public function __construct(private PerformanceSnapshotService $performance) {}

    public function register(HealthCheckRegistry $registry,string $owner='cms.core'): void
    {
        $performance=$this->performance;
        $registry->register(new HealthCheckDefinition(
            'performance.budget',
            $owner,
            static function() use($performance):array {
                $snapshot=$performance->snapshot();
                $statuses=array_column($snapshot['budget_evaluations'],'status');
                $status=in_array('fail',$statuses,true)
                    ? 'error'
                    : (in_array('warning',$statuses,true) ? 'warning' : 'ok');

                return [
                    'status'=>$status,
                    'message'=>'CMS performance budgets evaluated.',
                    'details'=>[
                        'pass'=>count(array_filter($statuses,static fn($s)=>$s==='pass')),
                        'warning'=>count(array_filter($statuses,static fn($s)=>$s==='warning')),
                        'fail'=>count(array_filter($statuses,static fn($s)=>$s==='fail')),
                        'unmeasured'=>count(array_filter($statuses,static fn($s)=>$s==='unmeasured')),
                    ],
                ];
            }
        ));
    }
}

<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\Posture;

use App\com_pinoox_cms\Cms\Health\HealthCheckDefinition;
use App\com_pinoox_cms\Cms\Health\HealthCheckRegistry;

final readonly class SecurityHealthRegistrar
{
    public function __construct(private SecurityPostureService $posture) {}

    public function register(HealthCheckRegistry $registry,string $owner='cms.core'): void
    {
        $posture=$this->posture;
        $registry->register(new HealthCheckDefinition(
            'security.posture',
            $owner,
            static function() use($posture):array {
                $report=$posture->report();
                return [
                    'status'=>match($report->status()) {
                        SecurityControlStatus::Pass=>'ok',
                        SecurityControlStatus::Warning=>'warning',
                        SecurityControlStatus::Fail=>'error',
                    },
                    'message'=>'CMS security posture evaluated.',
                    'details'=>$report->toArray()['counts'],
                ];
            }
        ));
    }
}

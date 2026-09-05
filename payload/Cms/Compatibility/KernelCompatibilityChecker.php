<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Compatibility;

final class KernelCompatibilityChecker
{
    /** @param list<KernelPrimitiveRequirement>|null $requirements */
    public function check(
        ?int $currentCode=null,
        ?string $currentVersion=null,
        ?array $requirements=null,
    ): KernelCompatibilityReport {
        $results=[];
        foreach ($requirements ?? CoreKernelCompatibilityProfile::requirements() as $requirement) {
            $classAvailable=class_exists($requirement->class);
            $missing=[];
            if ($classAvailable) {
                foreach ($requirement->methods as $method) {
                    if (!method_exists($requirement->class,$method)) $missing[]=$method;
                }
            }
            $results[]=new KernelPrimitiveResult($requirement,$classAvailable,$missing);
        }

        return new KernelCompatibilityReport(
            CoreKernelCompatibilityProfile::MIN_VERSION_CODE,
            CoreKernelCompatibilityProfile::MIN_VERSION_NAME,
            $currentCode,
            $currentVersion,
            $results,
        );
    }

    public function assertCompatible(
        ?int $currentCode=null,
        ?string $currentVersion=null,
        ?array $requirements=null,
    ): KernelCompatibilityReport {
        $report=$this->check($currentCode,$currentVersion,$requirements);
        if (!$report->compatible()) {
            throw new \RuntimeException('Pinoox/Pincore runtime does not satisfy CMS kernel compatibility contract.');
        }
        return $report;
    }
}

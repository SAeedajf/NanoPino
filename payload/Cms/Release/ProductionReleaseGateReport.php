<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Release;

final readonly class ProductionReleaseGateReport
{
    /**
     * @param list<array{code:string,message:string}> $blockers
     * @param array<string,mixed> $evidence
     */
    public function __construct(
        public bool $ready,
        public array $blockers,
        public array $evidence,
    ) {}

    /** @return array<string,mixed> */
    public function toArray():array
    {
        return [
            'ready'=>$this->ready,
            'blockers'=>$this->blockers,
            'evidence'=>$this->evidence,
        ];
    }
}

<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Performance;

final readonly class BudgetEvaluation
{
    public function __construct(
        public PerformanceBudgetDefinition $budget,
        public ?float $value,
        public BudgetStatus $status,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id'=>$this->budget->identifier(),
            'label'=>$this->budget->label,
            'metric'=>$this->budget->metric->value,
            'value'=>$this->value,
            'target'=>$this->budget->target,
            'limit'=>$this->budget->limit,
            'unit'=>$this->budget->unit,
            'higher_is_better'=>$this->budget->higherIsBetter,
            'status'=>$this->status->value,
        ];
    }
}

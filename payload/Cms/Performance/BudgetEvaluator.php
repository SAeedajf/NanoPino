<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Performance;

final class BudgetEvaluator
{
    public function evaluate(PerformanceBudgetDefinition $budget, ?float $value): BudgetEvaluation
    {
        if ($value === null || is_nan($value) || is_infinite($value)) {
            return new BudgetEvaluation($budget,null,BudgetStatus::Unmeasured);
        }

        if ($budget->higherIsBetter) {
            $status = $value >= $budget->target
                ? BudgetStatus::Pass
                : ($value >= $budget->limit ? BudgetStatus::Warning : BudgetStatus::Fail);
        } else {
            $status = $value <= $budget->target
                ? BudgetStatus::Pass
                : ($value <= $budget->limit ? BudgetStatus::Warning : BudgetStatus::Fail);
        }

        return new BudgetEvaluation($budget,$value,$status);
    }
}

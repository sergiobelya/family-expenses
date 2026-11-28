<?php

namespace App\Presentation\Telegram\Flow\Expense;

use App\Expense\Domain\ExpenseDraft;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class AddExpenseStepRouter
{
    /**
     * @param iterable<AddExpenseStepInterface> $steps
     */
    public function __construct(
        #[AutowireIterator('app.expense_add_step')]
        private iterable $steps
    ) {
    }

    public function resolve(ExpenseDraft $draft): ?AddExpenseStepInterface
    {
        foreach ($this->steps as $step) {
            if ($step->supports($draft)) {
                return $step;
            }
        }

        return null;
    }
}

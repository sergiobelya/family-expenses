<?php

namespace App\Expense\Domain\Exception;

use DomainException;

final class AllocationReconciliationException extends DomainException
{
    public function __construct(
        public readonly string $expectedAmount,
        public readonly string $actualAmount,
        ?\Throwable $previous = null
    ) {
        parent::__construct('Allocation amounts do not reconcile to the expense total.', 0, $previous);
    }
}

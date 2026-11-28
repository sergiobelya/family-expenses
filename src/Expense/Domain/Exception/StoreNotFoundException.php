<?php

namespace App\Expense\Domain\Exception;

use RuntimeException;

final class StoreNotFoundException extends RuntimeException
{
    public function __construct(
        public readonly string $storeId,
        ?\Throwable $previous = null
    ) {
        parent::__construct('Store was not found in this family.', 0, $previous);
    }
}

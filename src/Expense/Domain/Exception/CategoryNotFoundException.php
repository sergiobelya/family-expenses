<?php

namespace App\Expense\Domain\Exception;

use RuntimeException;

final class CategoryNotFoundException extends RuntimeException
{
    public function __construct(
        public readonly string $categoryId,
        ?\Throwable $previous = null
    ) {
        parent::__construct('Category was not found in this family.', 0, $previous);
    }
}

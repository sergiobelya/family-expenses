<?php

namespace App\Expense\Application\Command;

use App\Core\Domain\ValueObject\CategoryId;

final readonly class AllocationItem
{
    public function __construct(
        public CategoryId $categoryId,
        public string $amount,
    ) {
    }
}

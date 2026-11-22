<?php

namespace App\Expense\Domain\Entity;

use App\Core\Domain\ValueObject\AllocationId;
use App\Core\Domain\ValueObject\MoneyAmount;

final class ExpenseAllocation
{
    private AllocationId $id;
    private ExpenseCategory $category;
    private MoneyAmount $amount;

    private function __construct(
        ExpenseCategory $category,
        MoneyAmount $amount,
        ?AllocationId $id = null
    ) {
        $this->id = $id ?? AllocationId::generate();
        $this->category = $category;
        $this->amount = $amount;
    }

    public static function create(
        ExpenseCategory $category,
        MoneyAmount $amount,
        ?AllocationId $id = null
    ): self {
        return new self($category, $amount, $id);
    }

    public function getId(): AllocationId
    {
        return $this->id;
    }

    public function getCategory(): ExpenseCategory
    {
        return $this->category;
    }

    public function getAmount(): MoneyAmount
    {
        return $this->amount;
    }
}

<?php

namespace App\Expense\Domain\Entity;

use App\Core\Domain\ValueObject\AllocationId;
use App\Core\Domain\ValueObject\CategoryId;
use App\Core\Domain\ValueObject\MoneyAmount;

final class ExpenseAllocation
{
    private AllocationId $id;
    private CategoryId $categoryId;
    private MoneyAmount $amount;

    private function __construct(
        CategoryId $categoryId,
        MoneyAmount $amount,
        ?AllocationId $id = null
    ) {
        $this->id = $id ?? AllocationId::generate();
        $this->categoryId = $categoryId;
        $this->amount = $amount;
    }

    public static function create(
        CategoryId $categoryId,
        MoneyAmount $amount,
        ?AllocationId $id = null
    ): self {
        return new self($categoryId, $amount, $id);
    }

    public function getId(): AllocationId
    {
        return $this->id;
    }

    public function getCategoryId(): CategoryId
    {
        return $this->categoryId;
    }

    public function getAmount(): MoneyAmount
    {
        return $this->amount;
    }
}

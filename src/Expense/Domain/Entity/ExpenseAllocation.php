<?php

namespace App\Expense\Domain\Entity;

use App\Core\Domain\ValueObject\AllocationId;
use App\Core\Domain\ValueObject\CurrencyCode;
use App\Core\Domain\ValueObject\MoneyAmount;

final class ExpenseAllocation
{
    private AllocationId $id;
    private ?Expense $expense = null;
    private ExpenseCategory $category;
    private string $amount;
    private string $currency;

    public function __construct(
        ExpenseCategory $category,
        MoneyAmount $amount,
        ?AllocationId $id = null
    ) {
        $this->id = $id ?? AllocationId::generate();
        $this->category = $category;
        $this->amount = $amount->getAmount();
        $this->currency = (string) $amount->getCurrency();
    }

    public function setExpense(Expense $expense): void
    {
        $this->expense = $expense;
    }

    public function getExpense(): ?Expense
    {
        return $this->expense;
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
        return MoneyAmount::fromInt(
            (int) $this->amount,
            CurrencyCode::fromString($this->currency)
        );
    }
}

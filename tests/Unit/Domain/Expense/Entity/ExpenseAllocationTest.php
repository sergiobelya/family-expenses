<?php

namespace App\Tests\Unit\Domain\Expense\Entity;

use App\Core\Domain\ValueObject\AllocationId;
use App\Core\Domain\ValueObject\CategoryId;
use App\Core\Domain\ValueObject\CurrencyCode;
use App\Core\Domain\ValueObject\MoneyAmount;
use App\Expense\Domain\Entity\ExpenseAllocation;
use PHPUnit\Framework\TestCase;

final class ExpenseAllocationTest extends TestCase
{
    public function testCreateSetsProperties(): void
    {
        $categoryId = CategoryId::generate();
        $amount = MoneyAmount::fromDecimal('50.00', CurrencyCode::fromString('PLN'));

        $allocation = ExpenseAllocation::create($categoryId, $amount);

        $this->assertInstanceOf(AllocationId::class, $allocation->getId());
        $this->assertTrue($allocation->getCategoryId()->equals($categoryId));
        $this->assertTrue($allocation->getAmount()->equals($amount));
    }

    public function testCreateWithExplicitId(): void
    {
        $expectedId = AllocationId::generate();
        $categoryId = CategoryId::generate();
        $amount = MoneyAmount::fromDecimal('50.00', CurrencyCode::fromString('PLN'));

        $allocation = ExpenseAllocation::create($categoryId, $amount, $expectedId);

        $this->assertTrue($allocation->getId()->equals($expectedId));
    }

    public function testCreateGeneratesNewIdEachTime(): void
    {
        $categoryId = CategoryId::generate();
        $amount = MoneyAmount::fromDecimal('50.00', CurrencyCode::fromString('PLN'));

        $a1 = ExpenseAllocation::create($categoryId, $amount);
        $a2 = ExpenseAllocation::create($categoryId, $amount);

        $this->assertFalse($a1->getId()->equals($a2->getId()));
    }
}

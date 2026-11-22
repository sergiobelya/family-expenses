<?php

namespace App\Tests\Unit\Domain\Expense\Entity;

use App\Core\Domain\ValueObject\AllocationId;
use App\Core\Domain\ValueObject\CategoryId;
use App\Core\Domain\ValueObject\CurrencyCode;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\MoneyAmount;
use App\Expense\Domain\Entity\ExpenseAllocation;
use App\Expense\Domain\Entity\ExpenseCategory;
use PHPUnit\Framework\TestCase;

final class ExpenseAllocationTest extends TestCase
{
    public function testCreateSetsProperties(): void
    {
        $categoryId = CategoryId::generate();
        $familyId = FamilyId::generate();
        $category = new ExpenseCategory($categoryId, 'Food', $familyId);
        $amount = MoneyAmount::fromDecimal('50.00', CurrencyCode::fromString('PLN'));

        $allocation = ExpenseAllocation::create($category, $amount);

        $this->assertInstanceOf(AllocationId::class, $allocation->getId());
        $this->assertTrue($allocation->getCategory()->getId()->equals($categoryId));
        $this->assertSame('Food', $allocation->getCategory()->getName());
        $this->assertTrue($allocation->getAmount()->equals($amount));
    }

    public function testCreateWithExplicitId(): void
    {
        $expectedId = AllocationId::generate();
        $categoryId = CategoryId::generate();
        $familyId = FamilyId::generate();
        $category = new ExpenseCategory($categoryId, 'Transport', $familyId);
        $amount = MoneyAmount::fromDecimal('50.00', CurrencyCode::fromString('PLN'));

        $allocation = ExpenseAllocation::create($category, $amount, $expectedId);

        $this->assertTrue($allocation->getId()->equals($expectedId));
    }

    public function testCreateGeneratesNewIdEachTime(): void
    {
        $categoryId = CategoryId::generate();
        $familyId = FamilyId::generate();
        $category = new ExpenseCategory($categoryId, 'Food', $familyId);
        $amount = MoneyAmount::fromDecimal('50.00', CurrencyCode::fromString('PLN'));

        $a1 = ExpenseAllocation::create($category, $amount);
        $a2 = ExpenseAllocation::create($category, $amount);

        $this->assertFalse($a1->getId()->equals($a2->getId()));
    }
}

<?php

namespace App\Expense\Application\Command;

use App\Core\Domain\Repository\FamilyMembershipInterface;
use App\Core\Domain\ValueObject\{
    CurrencyCode,
    FamilyId,
    MoneyAmount,
    StoreId,
    UserId
};
use App\Expense\Domain\Entity\{
    Expense,
    ExpenseAllocation,
    ExpenseCategory,
    ExpenseStore
};
use App\Expense\Domain\Exception\{
    CategoryNotFoundException,
    StoreNotFoundException
};
use App\Expense\Domain\Repository\{
    CategoryRepositoryInterface,
    ExpenseRepositoryInterface,
    StoreRepositoryInterface
};
use App\Family\Domain\Exception\NotFamilyMemberException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class AddExpenseHandler
{
    public function __construct(
        private ExpenseRepositoryInterface $expenseRepository,
        private StoreRepositoryInterface $storeRepository,
        private CategoryRepositoryInterface $categoryRepository,
        private FamilyMembershipInterface $familyMembership,
    ) {
    }

    public function __invoke(AddExpenseCommand $command): Expense
    {
        $this->assertMember($command->familyId, $command->userId);

        $currency = $this->assertCurrencyCode($command->currency);
        /** @var numeric-string $totalAmountStr */
        $totalAmountStr = $command->amount;
        $totalAmount = MoneyAmount::fromDecimal($totalAmountStr, $currency);

        $store = $this->assertStoreExists($command->familyId, $command->storeId);
        $categories = $this->assertCategoriesExist($command->familyId, $command->allocations);

        $expense = Expense::create(
            familyId: $command->familyId,
            authorUserId: $command->userId,
            store: $store,
            amount: $totalAmount,
            purchaseDate: $command->purchaseDate,
            allocations: $this->buildAllocations($command->allocations, $currency, $categories),
        );

        $this->expenseRepository->save($expense);

        return $expense;
    }

    private function assertMember(
        FamilyId $familyId,
        UserId $userId
    ): void {
        if (!$this->familyMembership->isMember($familyId, $userId)) {
            throw NotFamilyMemberException::forFamily((string) $familyId, (string) $userId);
        }
    }

    private function assertCurrencyCode(string $currency): CurrencyCode
    {
        return CurrencyCode::fromString($currency);
    }

    private function assertStoreExists(
        FamilyId $familyId,
        StoreId $storeId
    ): ExpenseStore {
        $store = $this->storeRepository->findById($storeId, $familyId);
        if ($store === null) {
            throw new StoreNotFoundException((string) $storeId);
        }
        return $store;
    }

    /**
     * @param AllocationItem[] $allocations
     * @return ExpenseCategory[]
     */
    private function assertCategoriesExist(
        FamilyId $familyId,
        array $allocations
    ): array {
        $categories = [];
        foreach ($allocations as $item) {
            $category = $this->categoryRepository->findById($item->categoryId, $familyId);
            if ($category === null) {
                throw new CategoryNotFoundException((string) $item->categoryId);
            }
            $categories[(string) $item->categoryId] = $category;
        }
        return $categories;
    }

    /**
     * @param AllocationItem[] $allocations
     * @param ExpenseCategory[] $categories
     * @return ExpenseAllocation[]
     */
    private function buildAllocations(
        array $allocations,
        CurrencyCode $currency,
        array $categories
    ): array {
        $result = [];
        foreach ($allocations as $item) {
            $category = $categories[(string) $item->categoryId];
            /** @var numeric-string $itemAmount */
            $itemAmount = $item->amount;
            $amount = MoneyAmount::fromDecimal($itemAmount, $currency);
            $result[] = new ExpenseAllocation($category, $amount);
        }
        return $result;
    }
}

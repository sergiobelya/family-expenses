<?php

namespace App\Expense\Application\Query;

use App\Expense\Application\ExpenseAuthorizationService;
use App\Expense\Domain\Entity\ExpenseStore;
use App\Expense\Domain\Repository\StoreRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetExpenseStoreHandler
{
    public function __construct(
        private StoreRepositoryInterface $storeRepository,
        private ExpenseAuthorizationService $authorization,
    ) {
    }

    public function __invoke(GetExpenseStoreQuery $query): ?ExpenseStore
    {
        $this->authorization->assertMember($query->familyId, $query->requestedByUserId);

        return $this->storeRepository->findById($query->storeId, $query->familyId);
    }
}

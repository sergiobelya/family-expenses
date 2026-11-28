<?php

namespace App\Expense\Application\Query;

use App\Core\Domain\ValueObject\StoreId;
use App\Expense\Application\ExpenseAuthorizationService;
use App\Expense\Domain\Repository\StoreRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetStoreIdsByLastPurchaseHandler
{
    public function __construct(
        private StoreRepositoryInterface $storeRepository,
        private ExpenseAuthorizationService $authorization,
    ) {
    }

    /** @return list<StoreId> */
    public function __invoke(GetStoreIdsByLastPurchaseQuery $query): array
    {
        $this->authorization->assertMember($query->familyId, $query->requestedByUserId);

        return $this->storeRepository->findIdsByLastPurchase($query->familyId);
    }
}

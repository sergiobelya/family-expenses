<?php

namespace App\Tests\Unit\Application\Expense\Query;

use App\Catalog\Application\CatalogAccess;
use App\Catalog\Application\Query\GetStoreHandler;
use App\Catalog\Application\Query\GetStoreQuery;
use App\Catalog\Domain\Entity\Store;
use App\Catalog\Domain\Repository\StoreCatalogRepositoryInterface;
use App\Catalog\Domain\ValueObject\StoreName;
use App\Core\Domain\Repository\FamilyMembershipInterface;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\StoreId;
use App\Core\Domain\ValueObject\UserId;
use App\Catalog\Application\FamilyCatalogLockInterface;
use App\Expense\Application\ExpenseAuthorizationService;
use App\Expense\Application\Query\GetExpenseStoreHandler;
use App\Expense\Application\Query\GetExpenseStoreQuery;
use App\Expense\Application\Query\GetStoreIdsByLastPurchaseHandler;
use App\Expense\Application\Query\GetStoreIdsByLastPurchaseQuery;
use App\Expense\Domain\Entity\ExpenseStore;
use App\Expense\Domain\Repository\StoreRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class ExpenseStoreQueryHandlersTest extends TestCase
{
    public function testGetStoreChecksMembershipAndUsesFamilyScopedCatalogLookup(): void
    {
        $familyId = FamilyId::generate();
        $userId = UserId::generate();
        $store = Store::create(
            $familyId,
            StoreName::fromString('Market'),
            $userId
        );
        $storeId = $store->getId();
        $catalog = $this->createMock(StoreCatalogRepositoryInterface::class);
        $catalog->expects(self::once())
            ->method('findStore')
            ->with($familyId, $storeId)
            ->willReturn($store);
        $familyMembership = $this->createMock(FamilyMembershipInterface::class);
        $familyMembership->expects(self::once())->method('isMember')->with($familyId, $userId)->willReturn(true);
        $access = new CatalogAccess($familyMembership, $this->createMock(FamilyCatalogLockInterface::class));

        $result = (new GetStoreHandler($catalog, $access))(
            new GetStoreQuery($familyId, $userId, $storeId)
        );

        self::assertSame($store, $result);
    }

    public function testGetExpenseStoreChecksMembershipAndUsesFamilyScopedLookup(): void
    {
        $familyId = FamilyId::generate();
        $userId = UserId::generate();
        $storeId = StoreId::generate();
        $store = new ExpenseStore($storeId, 'Market', $familyId);
        $storeRepository = $this->createMock(StoreRepositoryInterface::class);
        $storeRepository->expects(self::once())
            ->method('findById')
            ->with($storeId, $familyId)
            ->willReturn($store);
        $authorization = $this->createMock(ExpenseAuthorizationService::class);
        $authorization->expects(self::once())->method('assertMember')->with($familyId, $userId);

        $result = (new GetExpenseStoreHandler($storeRepository, $authorization))(
            new GetExpenseStoreQuery($familyId, $userId, $storeId)
        );

        self::assertSame($store, $result);
    }

    public function testStoreIdsByLastPurchaseChecksMembershipAndUsesFamilyScopedLookup(): void
    {
        $familyId = FamilyId::generate();
        $userId = UserId::generate();
        $storeIds = [StoreId::generate(), StoreId::generate()];
        $storeRepository = $this->createMock(StoreRepositoryInterface::class);
        $storeRepository->expects(self::once())
            ->method('findIdsByLastPurchase')
            ->with($familyId)
            ->willReturn($storeIds);
        $authorization = $this->createMock(ExpenseAuthorizationService::class);
        $authorization->expects(self::once())->method('assertMember')->with($familyId, $userId);

        $result = (new GetStoreIdsByLastPurchaseHandler($storeRepository, $authorization))(
            new GetStoreIdsByLastPurchaseQuery($familyId, $userId)
        );

        self::assertSame($storeIds, $result);
    }
}
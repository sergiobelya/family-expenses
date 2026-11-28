<?php

namespace App\Tests\Unit\Expense\Infrastructure\Persistence\Doctrine\Repository;

use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\StoreId;
use App\Expense\Infrastructure\Persistence\Doctrine\Repository\DoctrineStoreRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class DoctrineStoreRepositoryTest extends TestCase
{
    public function testStoreIdsAreOrderedByLastPurchaseAcrossAllNonDeletedExpensesInOneQuery(): void
    {
        $familyId = FamilyId::generate();
        $storeIds = [(string) Uuid::v7(), (string) Uuid::v7()];
        $result = $this->createMock(Result::class);
        $result->expects(self::once())
            ->method('fetchAllAssociative')
            ->willReturn(array_map(static fn (string $id): array => ['id' => $id], $storeIds));

        $sql = null;
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('executeQuery')
            ->with(
                self::callback(static function (string $query) use (&$sql): bool {
                    $sql = $query;

                    return true;
                }),
                ['familyId' => (string) $familyId]
            )
            ->willReturn($result);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('getConnection')->willReturn($connection);

        $orderedStoreIds = (new DoctrineStoreRepository($entityManager))->findIdsByLastPurchase($familyId);

        self::assertSame($storeIds, array_map(static fn (StoreId $id): string => (string) $id, $orderedStoreIds));
        self::assertIsString($sql);
        self::assertStringContainsString('MAX(e.purchase_date) DESC NULLS LAST', $sql);
        self::assertStringContainsString('LOWER(s.name) ASC, s.name ASC, s.id ASC', $sql);
        self::assertStringContainsString('e.deleted_at IS NULL', $sql);
        self::assertStringContainsString('s.family_id = CAST(:familyId AS UUID)', $sql);
        self::assertStringNotContainsString('LIMIT', $sql);
    }
}
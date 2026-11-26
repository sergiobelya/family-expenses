<?php

namespace App\Tests\Unit\Application\Expense;

use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;
use App\Expense\Application\ExpenseAuthorizationService;
use App\Expense\Application\ExpenseDraftService;
use App\Expense\Domain\ExpenseDraft;
use App\Expense\Domain\ExpenseDraftStatus;
use App\Expense\Domain\Repository\ExpenseDraftRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ExpenseDraftServiceTest extends TestCase
{
    public function testCreateInitializesDraftBeforeItsFirstPersistence(): void
    {
        $userId = UserId::generate();
        $familyId = FamilyId::generate();
        $purchaseDate = new DateTimeImmutable('2026-11-26T00:00:00+00:00');
        $draftRepository = $this->createMock(ExpenseDraftRepositoryInterface::class);
        $draftRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (ExpenseDraft $draft) use ($userId, $familyId, $purchaseDate): bool {
                self::assertTrue($draft->userId->equals($userId));
                self::assertTrue($draft->familyId->equals($familyId));
                self::assertSame(ExpenseDraftStatus::ACTIVE, $draft->status);
                self::assertSame('store', $draft->currentStep);
                self::assertSame($purchaseDate, $draft->purchaseDate);

                return true;
            }));
        $authorization = $this->createMock(ExpenseAuthorizationService::class);
        $authorization->expects(self::once())->method('assertMember')->with($familyId, $userId);

        $draft = (new ExpenseDraftService($draftRepository, $authorization))->createDraft(
            $userId,
            $familyId,
            123456,
            111,
            'store',
            $purchaseDate
        );

        self::assertSame('store', $draft->currentStep);
        self::assertSame($purchaseDate, $draft->purchaseDate);
    }
}

<?php

namespace App\Expense\Application;

use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;
use App\Expense\Domain\ExpenseDraft;
use App\Expense\Domain\ExpenseDraftStatus;
use App\Expense\Domain\Repository\ExpenseDraftRepositoryInterface;
use DateTimeImmutable;
use LogicException;

final readonly class ExpenseDraftService
{
    public function __construct(
        private ExpenseDraftRepositoryInterface $draftRepository,
        private ExpenseAuthorizationService $authorization,
    ) {
    }

    public function findDraft(
        UserId $requestedByUserId,
        FamilyId $familyId,
        ?int $telegramUserId = null,
        ?int $chatId = null
    ): ?ExpenseDraft {
        $this->authorization->assertMember($familyId, $requestedByUserId);
        $draft = $this->draftRepository->find($requestedByUserId, $familyId);
        if ($draft === null) {
            return null;
        }

        if (
            !$draft->userId->equals($requestedByUserId)
            || !$draft->familyId->equals($familyId)
            || ($telegramUserId !== null && $draft->telegramUserId !== $telegramUserId)
            || ($chatId !== null && $draft->chatId !== $chatId)
        ) {
            $this->draftRepository->remove($requestedByUserId, $familyId);
            return null;
        }

        return $draft;
    }

    public function findActiveDraft(UserId $requestedByUserId, FamilyId $familyId): ?ExpenseDraft
    {
        $draft = $this->findDraft($requestedByUserId, $familyId);
        if ($draft === null || $draft->status->isTerminal() || $draft->status->isDurable()) {
            return null;
        }
        if ($draft->isExpired()) {
            $this->draftRepository->remove($requestedByUserId, $familyId);
            return null;
        }

        return $draft;
    }

    public function createDraft(
        UserId $requestedByUserId,
        FamilyId $familyId,
        int $telegramUserId,
        int $chatId,
        string $initialStep,
        DateTimeImmutable $purchaseDate
    ): ExpenseDraft {
        $this->authorization->assertMember($familyId, $requestedByUserId);
        $draft = ExpenseDraft::createNew(
            userId: $requestedByUserId,
            familyId: $familyId,
            telegramUserId: $telegramUserId,
            chatId: $chatId,
            now: new DateTimeImmutable(),
        );
        $draft->setStep($initialStep);
        $draft->setPurchaseDate($purchaseDate);
        $this->draftRepository->save($draft);

        return $draft;
    }

    public function saveDraft(ExpenseDraft $draft, UserId $requestedByUserId, FamilyId $familyId): void
    {
        $this->assertDraftAccess($draft, $requestedByUserId, $familyId);
        $this->draftRepository->save($draft);
    }

    public function removeDraft(UserId $requestedByUserId, FamilyId $familyId): void
    {
        $this->authorization->assertMember($familyId, $requestedByUserId);
        $this->draftRepository->remove($requestedByUserId, $familyId);
    }

    public function pauseDraft(ExpenseDraft $draft, UserId $requestedByUserId): void
    {
        $this->assertDraftAccess($draft, $requestedByUserId, $draft->familyId);
        $draft->setStatus(ExpenseDraftStatus::PAUSED);
        $this->draftRepository->save($draft);
    }

    public function resumeDraft(ExpenseDraft $draft, UserId $requestedByUserId): void
    {
        $this->assertDraftAccess($draft, $requestedByUserId, $draft->familyId);
        $draft->setStatus(ExpenseDraftStatus::ACTIVE);
        $this->draftRepository->save($draft);
    }

    public function hasActiveDraft(UserId $requestedByUserId, FamilyId $familyId): bool
    {
        return $this->findActiveDraft($requestedByUserId, $familyId) !== null;
    }

    private function assertDraftAccess(ExpenseDraft $draft, UserId $requestedByUserId, FamilyId $familyId): void
    {
        $this->authorization->assertMember($familyId, $requestedByUserId);
        if (!$draft->userId->equals($requestedByUserId) || !$draft->familyId->equals($familyId)) {
            throw new LogicException('Expense draft does not belong to the requested user and family.');
        }
    }
}

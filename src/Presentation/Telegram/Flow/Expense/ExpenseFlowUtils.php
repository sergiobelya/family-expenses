<?php

namespace App\Presentation\Telegram\Flow\Expense;

use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;
use App\Expense\Domain\ExpenseDraft;
use App\Expense\Domain\ExpenseDraftStatus;
use App\Expense\Domain\Repository\ExpenseDraftRepositoryInterface;

/**
 * Manages expense draft lifecycle for the Add Expense flow.
 *
 * Creates, resumes, pauses, and restores drafts. Each draft is
 * scoped to (user_id, family_id) — one draft per user per family.
 */
final readonly class ExpenseFlowUtils
{
    public function __construct(
        private ExpenseDraftRepositoryInterface $draftRepository,
    ) {
    }

    /**
     * Finds an active or paused draft for the given user and family.
     */
    public function findActiveDraft(
        UserId $userId,
        FamilyId $familyId
    ): ?ExpenseDraft {
        $draft = $this->draftRepository->find($userId, $familyId);
        if ($draft === null || $draft->status->isTerminal() || $draft->status->isDurable()) {
            return null;
        }
        if ($draft->isExpired()) {
            $this->draftRepository->remove($userId, $familyId);
            return null;
        }
        return $draft;
    }

    /**
     * Creates a new draft for the given user and family.
     */
    public function createDraft(
        UserId $userId,
        FamilyId $familyId,
        int $telegramUserId,
        int $chatId
    ): ExpenseDraft {
        $draft = ExpenseDraft::createNew(
            userId: $userId,
            familyId: $familyId,
            telegramUserId: $telegramUserId,
            chatId: $chatId,
            now: new \DateTimeImmutable(),
        );
        $this->draftRepository->save($draft);
        return $draft;
    }

    /**
     * Pauses the draft (e.g., when detouring to create a store/category).
     */
    public function pauseDraft(ExpenseDraft $draft): void
    {
        $draft->setStatus(ExpenseDraftStatus::PAUSED);
        $this->draftRepository->save($draft);
    }

    /**
     * Resumes a paused draft back to Active.
     */
    public function resumeDraft(ExpenseDraft $draft): void
    {
        $draft->setStatus(ExpenseDraftStatus::ACTIVE);
        $this->draftRepository->save($draft);
    }

    /**
     * Marks the draft as Saved and removes it from Redis.
     */
    public function completeDraft(UserId $userId, FamilyId $familyId): void
    {
        $this->draftRepository->remove($userId, $familyId);
    }

    /**
     * Marks the draft as Discarded and removes it from Redis.
     */
    public function discardDraft(UserId $userId, FamilyId $familyId): void
    {
        $this->draftRepository->remove($userId, $familyId);
    }

    /**
     * Checks if the user has an active draft for the family.
     */
    public function hasActiveDraft(UserId $userId, FamilyId $familyId): bool
    {
        return $this->findActiveDraft($userId, $familyId) !== null;
    }

    /**
     * Refreshes the draft's expiry timer.
     */
    public function refreshExpiry(ExpenseDraft $draft): void
    {
        $draft->refreshExpiry();
        $this->draftRepository->save($draft);
    }
}


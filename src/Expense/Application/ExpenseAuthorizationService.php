<?php

namespace App\Expense\Application;

use App\Core\Domain\Repository\FamilyMembershipInterface;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;
use App\Core\Domain\Exception\NotFamilyMemberException;
use App\Expense\Domain\Entity\Expense;
use App\Expense\Domain\Exception\ExpenseNotAuthorException;

readonly class ExpenseAuthorizationService
{
    public function __construct(
        private FamilyMembershipInterface $familyMembership,
    ) {
    }

    /**
     * Asserts that the user is a member of the specified family.
     */
    public function assertMember(FamilyId $familyId, UserId $userId): void
    {
        if (!$this->familyMembership->isMember($familyId, $userId)) {
            throw NotFamilyMemberException::forFamily((string) $familyId, (string) $userId);
        }
    }

    /**
     * Asserts that the user is the author of the expense.
     */
    public function assertAuthor(Expense $expense, UserId $userId): void
    {
        if (!$expense->getAuthorUserId()->equals($userId)) {
            throw new ExpenseNotAuthorException(
                (string) $expense->getId(),
                (string) $userId
            );
        }
    }
}

<?php

namespace App\Tests\Unit\Domain\Expense\Entity;

use App\Core\Domain\ValueObject\ExpenseId;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;
use App\Expense\Domain\ValueObject\ExpenseSaveAttemptId;
use App\Expense\Domain\Entity\ExpenseSaveAttempt;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExpenseSaveAttempt::class)]
final class ExpenseSaveAttemptTest extends TestCase
{
    public function testPendingAttempt(): void
    {
        $now = new DateTimeImmutable('2026-01-01 12:00:00', new DateTimeZone('UTC'));
        $attempt = ExpenseSaveAttempt::pending(
            id: ExpenseSaveAttemptId::generate(),
            userId: UserId::generate(),
            familyId: FamilyId::generate(),
            idempotencyKey: 'abc123',
            now: $now,
        );

        self::assertTrue($attempt->isPending());
        self::assertFalse($attempt->isCompleted());
        self::assertFalse($attempt->isFailed());
        self::assertFalse($attempt->isResolved());
        self::assertNull($attempt->expenseId);
        self::assertNull($attempt->completedAt);
        self::assertSame('abc123', $attempt->idempotencyKey);
    }

    public function testMarkCompleted(): void
    {
        $attempt = $this->createPending();
        $expenseId = ExpenseId::generate();
        $completedAt = new DateTimeImmutable('2026-01-01 12:01:00', new DateTimeZone('UTC'));

        $attempt->markCompleted($expenseId, $completedAt);

        self::assertFalse($attempt->isPending());
        self::assertTrue($attempt->isCompleted());
        self::assertTrue($attempt->isResolved());
        self::assertSame($expenseId, $attempt->expenseId);
        self::assertSame($completedAt, $attempt->completedAt);
    }

    public function testMarkFailed(): void
    {
        $attempt = $this->createPending();
        $failedAt = new DateTimeImmutable('2026-01-01 12:01:00', new DateTimeZone('UTC'));

        $attempt->markFailed($failedAt);

        self::assertFalse($attempt->isPending());
        self::assertTrue($attempt->isFailed());
        self::assertTrue($attempt->isResolved());
        self::assertNull($attempt->expenseId);
        self::assertSame($failedAt, $attempt->completedAt);
    }

    private function createPending(): ExpenseSaveAttempt
    {
        return ExpenseSaveAttempt::pending(
            id: ExpenseSaveAttemptId::generate(),
            userId: UserId::generate(),
            familyId: FamilyId::generate(),
            idempotencyKey: 'key123',
            now: new DateTimeImmutable('2026-01-01 12:00:00', new DateTimeZone('UTC')),
        );
    }
}

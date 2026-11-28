<?php

namespace App\Expense\Domain;

/**
 * Status of an expense save attempt for idempotency and recovery.
 *
 * PENDING → COMPLETED (commit succeeded)
 * PENDING → FAILED (commit failed)
 *
 * @see ExpenseSaveAttempt
 */
enum ExpenseSaveAttemptStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    /**
     * Whether the attempt has reached a final outcome.
     */
    public function isResolved(): bool
    {
        return match ($this) {
            self::COMPLETED, self::FAILED => true,
            default => false,
        };
    }
}

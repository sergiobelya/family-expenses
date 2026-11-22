<?php

namespace App\Expense\Domain;

/**
 * Expense draft lifecycle states.
 *
 * ACTIVE → PAUSED (detour to Stores/Categories)
 * ACTIVE → SAVING (user confirmed)
 * SAVING → SAVED (commit succeeded)
 * SAVING → ACTIVE (definite rejection)
 * SAVING → SAVE_UNKNOWN (timeout or unknown outcome)
 * SAVE_UNKNOWN → SAVING (retry with same idempotency key)
 * SAVE_UNKNOWN → SAVED (reconciliation finds the commit)
 * SAVE_UNKNOWN → ACTIVE (review draft)
 * ACTIVE → DISCARDED (cancel confirmed)
 * ACTIVE/PAUSED → Removed (TTL expired)
 */
enum ExpenseDraftStatus: string
{
    case ACTIVE = 'active';
    case PAUSED = 'paused';
    case SAVING = 'saving';
    case SAVE_UNKNOWN = 'save_unknown';
    case SAVED = 'saved';
    case DISCARDED = 'discarded';

    /**
     * Terminal states — the draft cannot be resumed.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::SAVED, self::DISCARDED => true,
            default => false,
        };
    }

    /**
     * States that are stored in Redis with TTL (short-lived).
     */
    public function isTemporary(): bool
    {
        return match ($this) {
            self::ACTIVE, self::PAUSED => true,
            default => false,
        };
    }

    /**
     * States that require durable PostgreSQL storage for recovery.
     */
    public function isDurable(): bool
    {
        return match ($this) {
            self::SAVING, self::SAVE_UNKNOWN => true,
            default => false,
        };
    }
}

<?php

namespace App\Expense\Infrastructure\Persistence\Redis;

use App\Expense\Domain\ExpenseDraft;
use App\Expense\Domain\Repository\ExpenseDraftRepositoryInterface;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;
use Predis\ClientInterface;

readonly class ExpenseDraftRepository implements ExpenseDraftRepositoryInterface
{
    private const string PREFIX = 'expense:draft:';
    private const int TTL_SECONDS = 86400; // 24 hours

    public function __construct(private ClientInterface $redis)
    {
    }

    public function find(UserId $userId, FamilyId $familyId): ?ExpenseDraft
    {
        $key = self::PREFIX . $userId->toRfc4122() . ':' . $familyId->toRfc4122();
        $raw = $this->redis->get($key);
        if (!$raw) {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        return ExpenseDraft::fromArray($decoded);
    }

    /**
     * Saves the draft to Redis. No race condition guard is needed at this stage
     * because Telegram delivers updates sequentially per user, and the draft is
     * scoped to a single (user_id, family_id) tuple — concurrent modifications
     * are practically impossible with a single poller worker.
     *
     * If multi-worker polling or an HTTP API is added later, consider adding
     * optimistic locking (e.g., Lua CAS on card_revision) here.
     */
    public function save(ExpenseDraft $draft): void
    {
        $key = self::PREFIX . $draft->userId->toRfc4122() . ':' . $draft->familyId->toRfc4122();
        $encoded = json_encode($draft->toArray(), JSON_THROW_ON_ERROR);

        $ttl = $this->calculateTtl($draft);
        $this->redis->setex($key, $ttl, $encoded);
    }

    public function remove(UserId $userId, FamilyId $familyId): void
    {
        $key = self::PREFIX . $userId->toRfc4122() . ':' . $familyId->toRfc4122();
        $this->redis->del($key);
    }

    private function calculateTtl(ExpenseDraft $draft): int
    {
        if ($draft->status->isTerminal()) {
            // Terminal drafts are kept briefly for recovery, then removed
            return 3600; // 1 hour
        }

        if ($draft->status->isDurable()) {
            // Saving/SaveUnknown — stored in PostgreSQL, not Redis
            // This shouldn't normally be called, but keep briefly as fallback
            return 3600;
        }

        // Active/Paused — use remaining time or default TTL
        $remaining = (int) ($draft->expiresAt->getTimestamp() - time());
        return max($remaining, self::TTL_SECONDS);
    }
}

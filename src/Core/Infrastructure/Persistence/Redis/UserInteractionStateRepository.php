<?php

namespace App\Core\Infrastructure\Persistence\Redis;

use Predis\ClientInterface;

class UserInteractionStateRepository
{
    private const string PREFIX = 'bot:interaction_state:';
    private const int TTL_SECONDS = 86400; // 24 hours

    public function __construct(private ClientInterface $redis)
    {
    }

    public function get(int $telegramUserId): ?UserInteractionState
    {
        $raw = $this->redis->get(self::PREFIX . $telegramUserId);
        if (!$raw) {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        return UserInteractionState::fromArray($decoded);
    }

    public function set(UserInteractionState $state, ?int $ttl = null): void
    {
        $key = self::PREFIX . $state->telegramUserId;
        $encoded = json_encode($state->toArray(), JSON_THROW_ON_ERROR);
        $this->redis->setex($key, $ttl ?? self::TTL_SECONDS, $encoded);
    }

    public function clear(int $telegramUserId): void
    {
        $this->redis->del(self::PREFIX . $telegramUserId);
    }
}

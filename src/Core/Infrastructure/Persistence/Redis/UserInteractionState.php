<?php

namespace App\Core\Infrastructure\Persistence\Redis;

final readonly class UserInteractionState
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public int $telegramUserId,
        public string $flow,
        public string $step,
        public array $data = [],
        public int $updatedAt = 0
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'telegram_user_id' => $this->telegramUserId,
            'flow' => $this->flow,
            'step' => $this->step,
            'data' => $this->data,
            'updated_at' => $this->updatedAt > 0 ? $this->updatedAt : time(),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            telegramUserId: (int) ($payload['telegram_user_id'] ?? 0),
            flow: (string) ($payload['flow'] ?? ''),
            step: (string) ($payload['step'] ?? ''),
            data: (array) ($payload['data'] ?? []),
            updatedAt: (int) ($payload['updated_at'] ?? time())
        );
    }
}

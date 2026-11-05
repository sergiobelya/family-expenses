<?php

namespace App\Core\Infrastructure\Outbox;

use DateTimeImmutable;
use DateTimeZone;
use JsonSerializable;
use Symfony\Component\Uid\Uuid;

final readonly class OutboxEnvelope implements JsonSerializable
{
    public function __construct(
        public string $messageId,
        public string $eventType,
        public array $payload,
        public DateTimeImmutable $occurredAt,
        public string $version = '1.0'
    ) {}

    public static function fromOutbox(
        Uuid|string $messageId,
        string $eventType,
        array $payload,
        ?DateTimeImmutable $occurredAt = null,
        string $version = '1.0'
    ): self {
        return new self(
            messageId: (string) $messageId,
            eventType: $eventType,
            payload: $payload,
            occurredAt: $occurredAt ?? new DateTimeImmutable('now', new DateTimeZone('UTC')),
            version: $version
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'message_id' => $this->messageId,
            'event_type' => $this->eventType,
            'version' => $this->version,
            'occurred_at' => $this->occurredAt->format(DateTimeImmutable::ATOM),
            'payload' => $this->payload,
        ];
    }
}

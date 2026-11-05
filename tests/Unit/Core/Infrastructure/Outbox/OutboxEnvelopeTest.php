<?php

namespace App\Tests\Unit\Core\Infrastructure\Outbox;

use App\Core\Infrastructure\Outbox\OutboxEnvelope;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class OutboxEnvelopeTest extends TestCase
{
    public function testFromOutboxAndJsonSerialize(): void
    {
        $id = Uuid::v7();
        $occurredAt = new DateTimeImmutable('2026-11-05 14:00:00+00:00');
        $payload = ['family_id' => '123', 'name' => 'Smiths'];

        $envelope = OutboxEnvelope::fromOutbox(
            messageId: $id,
            eventType: 'family.created',
            payload: $payload,
            occurredAt: $occurredAt,
            version: '1.0'
        );

        $this->assertSame((string) $id, $envelope->messageId);
        $this->assertSame('family.created', $envelope->eventType);
        $this->assertSame($payload, $envelope->payload);
        $this->assertSame($occurredAt, $envelope->occurredAt);
        $this->assertSame('1.0', $envelope->version);

        $json = $envelope->jsonSerialize();
        $this->assertSame((string) $id, $json['message_id']);
        $this->assertSame('family.created', $json['event_type']);
        $this->assertSame('1.0', $json['version']);
        $this->assertSame('2026-11-05T14:00:00+00:00', $json['occurred_at']);
        $this->assertSame($payload, $json['payload']);
    }
}

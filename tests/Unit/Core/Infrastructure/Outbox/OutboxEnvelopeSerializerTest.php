<?php

namespace App\Tests\Unit\Core\Infrastructure\Outbox;

use App\Core\Infrastructure\Outbox\OutboxEnvelope;
use App\Core\Infrastructure\Outbox\OutboxEnvelopeSerializer;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;

final class OutboxEnvelopeSerializerTest extends TestCase
{
    public function testEncodeProducesVersionedJsonEnvelopeAndDecodeRestoresIt(): void
    {
        $message = new OutboxEnvelope(
            messageId: 'message-123',
            eventType: 'family.settings_updated',
            payload: ['family_id' => 'family-123', 'name' => 'Сім’я'],
            occurredAt: new DateTimeImmutable('2026-11-06T16:00:00+00:00')
        );
        $serializer = new OutboxEnvelopeSerializer();

        $encoded = $serializer->encode(new Envelope($message));
        $body = json_decode($encoded['body'], true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(OutboxEnvelope::class, $encoded['headers']['type']);
        self::assertSame('message-123', $body['message_id']);
        self::assertSame('family.settings_updated', $body['event_type']);
        self::assertSame('1.0', $body['version']);
        self::assertSame('Сім’я', $body['payload']['name']);

        $decoded = $serializer->decode($encoded)->getMessage();
        self::assertEquals($message, $decoded);
    }
}

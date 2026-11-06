<?php

namespace App\Core\Infrastructure\Outbox;

use DateTimeImmutable;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final class OutboxEnvelopeSerializer implements SerializerInterface
{
    public function encode(Envelope $envelope): array
    {
        $message = $envelope->getMessage();
        if (!$message instanceof OutboxEnvelope) {
            throw new \InvalidArgumentException(sprintf(
                'Expected message of type %s, got %s.',
                OutboxEnvelope::class,
                get_debug_type($message)
            ));
        }

        return [
            'body' => json_encode($message, JSON_THROW_ON_ERROR),
            'headers' => ['type' => OutboxEnvelope::class],
        ];
    }

    public function decode(array $encodedEnvelope): Envelope
    {
        if (
            !is_string($encodedEnvelope['body'] ?? null)
            || !is_array($encodedEnvelope['headers'] ?? null)
            || ($encodedEnvelope['headers']['type'] ?? null) !== OutboxEnvelope::class
        ) {
            throw new MessageDecodingFailedException('Encoded envelope is not a valid OutboxEnvelope.');
        }

        try {
            $data = json_decode($encodedEnvelope['body'], true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new MessageDecodingFailedException('Outbox envelope body is not valid JSON.', 0, $exception);
        }

        if (
            !is_array($data)
            || !is_string($data['message_id'] ?? null)
            || !is_string($data['event_type'] ?? null)
            || !is_string($data['version'] ?? null)
            || !is_string($data['occurred_at'] ?? null)
            || $data['occurred_at'] === ''
            || !is_array($data['payload'] ?? null)
        ) {
            throw new MessageDecodingFailedException('Outbox envelope body has an invalid shape.');
        }

        $occurredAt = DateTimeImmutable::createFromFormat(DateTimeImmutable::ATOM, $data['occurred_at']);
        if ($occurredAt === false) {
            throw new MessageDecodingFailedException('Outbox envelope has an invalid occurred_at value.');
        }

        return new Envelope(new OutboxEnvelope(
            messageId: $data['message_id'],
            eventType: $data['event_type'],
            payload: $data['payload'],
            occurredAt: $occurredAt,
            version: $data['version']
        ));
    }
}

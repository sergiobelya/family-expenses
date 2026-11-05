<?php

namespace App\Core\Infrastructure\Outbox;

use App\Core\Infrastructure\Persistence\Doctrine\Entity\OutboxMessage;
use App\Core\Infrastructure\Persistence\Doctrine\Repository\OutboxRepository;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

class OutboxPublisher implements OutboxPublisherInterface
{
    private LoggerInterface $logger;

    public function __construct(
        private OutboxRepository $outboxRepository,
        private MessageBusInterface $messageBus,
        ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function publish(OutboxMessage $message): void
    {
        $envelope = OutboxEnvelope::fromOutbox(
            messageId: $message->getId(),
            eventType: $message->getEventType(),
            payload: $message->getPayload(),
            occurredAt: $message->getCreatedAt()
        );

        $this->messageBus->dispatch($envelope);
        $message->markAsPublished();
        $this->outboxRepository->save($message);

        $this->logger->info('Outbox message published successfully', [
            'message_id' => (string) $message->getId(),
            'event_type' => $message->getEventType(),
        ]);
    }

    public function publishBatch(int $limit = 100): int
    {
        $pendingMessages = $this->outboxRepository->findPending($limit);
        $publishedCount = 0;

        foreach ($pendingMessages as $message) {
            try {
                $this->publish($message);
                $publishedCount++;
            } catch (Throwable $e) {
                $this->logger->error('Failed to publish outbox message', [
                    'message_id' => (string) $message->getId(),
                    'event_type' => $message->getEventType(),
                    'error' => $e->getMessage(),
                ]);

                $message->markAsFailed();
                $this->outboxRepository->save($message);
            }
        }

        return $publishedCount;
    }
}

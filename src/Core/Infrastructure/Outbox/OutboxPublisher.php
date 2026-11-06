<?php

namespace App\Core\Infrastructure\Outbox;

use App\Core\Infrastructure\Persistence\Doctrine\Entity\OutboxMessage;
use App\Core\Infrastructure\Persistence\Doctrine\Repository\OutboxRepository;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

class OutboxPublisher implements OutboxPublisherInterface
{
    private const int MAX_ATTEMPTS = 10;
    private const int INITIAL_RETRY_DELAY_SECONDS = 7;

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
        $this->dispatch($message);
        $this->markAsPublished($message);
    }

    public function retryFailed(OutboxMessage $message): bool
    {
        if ($message->getStatus() !== OutboxMessage::STATUS_FAILED) {
            throw new \LogicException('Only failed outbox messages can be retried manually.');
        }

        try {
            $this->dispatch($message);
        } catch (Throwable $e) {
            $message->markAsFailed($e->getMessage());
            $this->outboxRepository->save($message);

            $this->logger->error('Manual retry of outbox message failed', [
                'message_id' => (string) $message->getId(),
                'event_type' => $message->getEventType(),
                'error' => $e->getMessage(),
                'attempt_count' => $message->getAttemptCount(),
            ]);

            return false;
        }

        $this->markAsPublished($message);

        return true;
    }

    private function dispatch(OutboxMessage $message): void
    {
        $envelope = OutboxEnvelope::fromOutbox(
            messageId: $message->getId(),
            eventType: $message->getEventType(),
            payload: $message->getPayload(),
            occurredAt: $message->getCreatedAt()
        );

        $this->messageBus->dispatch($envelope);
    }

    private function markAsPublished(OutboxMessage $message): void
    {
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
                $nextAttemptNumber = $message->getAttemptCount() + 1;
                if ($nextAttemptNumber >= self::MAX_ATTEMPTS) {
                    $message->markAsFailed($e->getMessage());
                    $this->outboxRepository->save($message);

                    $this->logger->error('Outbox message reached the maximum publishing attempts', [
                        'message_id' => (string) $message->getId(),
                        'event_type' => $message->getEventType(),
                        'error' => $e->getMessage(),
                        'attempt_count' => $message->getAttemptCount(),
                    ]);
                    continue;
                }

                $retryDelay = self::INITIAL_RETRY_DELAY_SECONDS * (2 ** ($nextAttemptNumber - 1));
                $nextAttemptAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
                    ->modify(sprintf('+%d seconds', $retryDelay));
                $message->scheduleRetry($e->getMessage(), $nextAttemptAt);
                $this->outboxRepository->save($message);

                $this->logger->error('Failed to publish outbox message', [
                    'message_id' => (string) $message->getId(),
                    'event_type' => $message->getEventType(),
                    'error' => $e->getMessage(),
                    'attempt_count' => $message->getAttemptCount(),
                    'next_attempt_at' => $nextAttemptAt->format(DateTimeImmutable::ATOM),
                ]);
            }
        }

        return $publishedCount;
    }
}

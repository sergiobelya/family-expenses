<?php

namespace App\Core\Infrastructure\Persistence\Doctrine\Entity;

use App\Core\Domain\ValueObject\OutboxMessageId;
use App\Core\Infrastructure\Outbox\OutboxMessageStatus;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Uid\Uuid;

class OutboxMessage
{
    private OutboxMessageId $id;
    private string $eventType;
    /** @var array<string, mixed> */
    private array $payload;
    private OutboxMessageStatus $status;
    private DateTimeImmutable $createdAt;
    private ?DateTimeImmutable $publishedAt = null;
    private int $attemptCount = 0;
    private DateTimeImmutable $nextAttemptAt;
    private ?string $lastError = null;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        string $eventType,
        array $payload,
        OutboxMessageStatus $status = OutboxMessageStatus::PENDING,
        ?Uuid $id = null,
        ?DateTimeImmutable $now = null
    ) {
        $this->id = $id === null ? OutboxMessageId::generate() : OutboxMessageId::fromString((string) $id);
        $this->eventType = $eventType;
        $this->payload = $payload;
        $this->status = $status;
        $this->createdAt = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->nextAttemptAt = $this->createdAt;
    }

    public function getId(): OutboxMessageId
    {
        return $this->id;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    /** @return array<string, mixed> */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getStatus(): OutboxMessageStatus
    {
        return $this->status;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getPublishedAt(): ?DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function getAttemptCount(): int
    {
        return $this->attemptCount;
    }

    public function getNextAttemptAt(): DateTimeImmutable
    {
        return $this->nextAttemptAt;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function markAsPublished(?DateTimeImmutable $now = null): void
    {
        $this->status = OutboxMessageStatus::PUBLISHED;
        $this->publishedAt = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->nextAttemptAt = $this->publishedAt;
        $this->lastError = null;
    }

    public function scheduleRetry(string $error, DateTimeImmutable $nextAttemptAt): void
    {
        $this->status = OutboxMessageStatus::PENDING;
        ++$this->attemptCount;
        $this->nextAttemptAt = $nextAttemptAt;
        $this->publishedAt = null;
        $this->lastError = $error;
    }

    public function markAsFailed(string $error): void
    {
        $this->status = OutboxMessageStatus::FAILED;
        ++$this->attemptCount;
        $this->nextAttemptAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->publishedAt = null;
        $this->lastError = $error;
    }
}

<?php

namespace App\Core\Infrastructure\Persistence\Doctrine\Entity;

use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Uid\Uuid;

class OutboxMessage
{
    public const string STATUS_PENDING = 'pending';
    public const string STATUS_PUBLISHED = 'published';
    public const string STATUS_FAILED = 'failed';

    private Uuid $id;
    private string $eventType;
    private array $payload;
    private string $status;
    private DateTimeImmutable $createdAt;
    private ?DateTimeImmutable $publishedAt = null;

    public function __construct(
        string $eventType,
        array $payload,
        string $status = self::STATUS_PENDING,
        ?Uuid $id = null,
        ?DateTimeImmutable $now = null
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->eventType = $eventType;
        $this->payload = $payload;
        $this->status = $status;
        $this->createdAt = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getStatus(): string
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

    public function markAsPublished(?DateTimeImmutable $now = null): void
    {
        $this->status = self::STATUS_PUBLISHED;
        $this->publishedAt = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function markAsFailed(): void
    {
        $this->status = self::STATUS_FAILED;
    }
}

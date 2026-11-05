<?php

namespace App\Core\Infrastructure\Persistence\Doctrine\Entity;

use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Uid\Uuid;

class TelegramUpdateInbox
{
    private Uuid $id;
    private int $updateId;
    private DateTimeImmutable $receivedAt;
    private ?DateTimeImmutable $processedAt = null;

    public function __construct(
        int $updateId,
        ?Uuid $id = null,
        ?DateTimeImmutable $now = null
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->updateId = $updateId;
        $this->receivedAt = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUpdateId(): int
    {
        return $this->updateId;
    }

    public function getReceivedAt(): DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function getProcessedAt(): ?DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function markAsProcessed(?DateTimeImmutable $now = null): void
    {
        $this->processedAt = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}

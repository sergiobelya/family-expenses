<?php

namespace App\Core\Infrastructure\Persistence\Doctrine\Entity;

use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Uid\Uuid;

class AuditLog
{
    private Uuid $id;
    private ?Uuid $familyId;
    private ?Uuid $actorUserId;
    private string $entityType;
    private string $entityId;
    private string $action;
    private ?array $beforeData = null;
    private ?array $afterData = null;
    private DateTimeImmutable $createdAt;

    public function __construct(
        string $entityType,
        string $entityId,
        string $action,
        ?Uuid $familyId = null,
        ?Uuid $actorUserId = null,
        ?array $beforeData = null,
        ?array $afterData = null,
        ?Uuid $id = null,
        ?DateTimeImmutable $now = null
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->action = $action;
        $this->familyId = $familyId;
        $this->actorUserId = $actorUserId;
        $this->beforeData = $beforeData;
        $this->afterData = $afterData;
        $this->createdAt = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getFamilyId(): ?Uuid
    {
        return $this->familyId;
    }

    public function getActorUserId(): ?Uuid
    {
        return $this->actorUserId;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getBeforeData(): ?array
    {
        return $this->beforeData;
    }

    public function getAfterData(): ?array
    {
        return $this->afterData;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}

<?php

namespace App\Core\Infrastructure\Persistence\Doctrine\Entity;

use App\Core\Domain\ValueObject\AuditLogId;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Uid\Uuid;

final class AuditLog
{
    private AuditLogId $id;
    private ?FamilyId $familyId;
    private ?UserId $actorUserId;
    private string $entityType;
    private string $entityId;
    private string $action;
    /** @var array<string, mixed>|null */
    private ?array $beforeData = null;
    /** @var array<string, mixed>|null */
    private ?array $afterData = null;
    private DateTimeImmutable $createdAt;

    /**
     * @param array<string, mixed>|null $beforeData
     * @param array<string, mixed>|null $afterData
     */
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
        $this->id = $id === null ? AuditLogId::generate() : AuditLogId::fromString((string) $id);
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->action = $action;
        $this->familyId = $familyId === null ? null : FamilyId::fromString((string) $familyId);
        $this->actorUserId = $actorUserId === null ? null : UserId::fromString((string) $actorUserId);
        $this->beforeData = $beforeData;
        $this->afterData = $afterData;
        $this->createdAt = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function getId(): AuditLogId
    {
        return $this->id;
    }

    public function getFamilyId(): ?Uuid
    {
        return $this->familyId?->toUuid();
    }

    public function getActorUserId(): ?Uuid
    {
        return $this->actorUserId?->toUuid();
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

    /** @return array<string, mixed>|null */
    public function getBeforeData(): ?array
    {
        return $this->beforeData;
    }

    /** @return array<string, mixed>|null */
    public function getAfterData(): ?array
    {
        return $this->afterData;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}

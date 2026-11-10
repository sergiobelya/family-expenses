<?php

namespace App\Core\Domain\Event;

use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;

interface AuditableEventInterface extends DomainEventInterface
{
    public function getFamilyId(): ?FamilyId;

    public function getActorUserId(): ?UserId;

    public function getEntityType(): string;

    public function getEntityId(): string;

    public function getAction(): string;

    /**
     * @return array<string, mixed>|null
     */
    public function getBeforeData(): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function getAfterData(): ?array;
}

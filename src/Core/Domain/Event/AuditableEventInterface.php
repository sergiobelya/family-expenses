<?php

namespace App\Core\Domain\Event;

use Symfony\Component\Uid\Uuid;

interface AuditableEventInterface extends DomainEventInterface
{
    public function getFamilyId(): ?Uuid;

    public function getActorUserId(): ?Uuid;

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

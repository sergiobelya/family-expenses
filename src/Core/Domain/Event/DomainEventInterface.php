<?php

namespace App\Core\Domain\Event;

use DateTimeImmutable;

interface DomainEventInterface
{
    public function getEventName(): string;

    public function getOccurredAt(): DateTimeImmutable;

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array;
}

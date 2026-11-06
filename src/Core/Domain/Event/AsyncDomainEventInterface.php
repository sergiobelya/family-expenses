<?php

namespace App\Core\Domain\Event;

interface AsyncDomainEventInterface extends DomainEventInterface
{
    public function getEventName(): string;

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array;
}

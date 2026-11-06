<?php

namespace App\Core\Domain\Event;

use DateTimeImmutable;

interface DomainEventInterface
{
    public function getOccurredAt(): DateTimeImmutable;
}

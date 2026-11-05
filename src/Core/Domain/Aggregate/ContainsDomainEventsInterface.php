<?php

namespace App\Core\Domain\Aggregate;

use App\Core\Domain\Event\DomainEventInterface;
use Generator;

interface ContainsDomainEventsInterface
{
    /**
     * @return Generator<DomainEventInterface>
     */
    public function releaseEvents(): Generator;
}

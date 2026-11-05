<?php

namespace App\Core\Domain\Aggregate;

use App\Core\Domain\Event\DomainEventInterface;
use Generator;

abstract class AggregateRoot implements ContainsDomainEventsInterface
{
    /**
     * @var DomainEventInterface[]
     */
    private array $recordedEvents = [];

    protected function recordEvent(DomainEventInterface $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /**
     * @return Generator<DomainEventInterface>
     */
    public function releaseEvents(): Generator
    {
        while ($event = array_shift($this->recordedEvents)) {
            yield $event;
        }
    }
}

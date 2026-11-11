<?php

namespace App\Tests\Unit\Domain\Core\Aggregate;

use App\Core\Domain\Aggregate\AggregateRoot;
use App\Core\Domain\Event\DomainEventInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class AggregateRootTest extends TestCase
{
    public function testRecordAndReleaseEvents(): void
    {
        $dummy = new class extends AggregateRoot {
            public function trigger(DomainEventInterface $event): void
            {
                $this->recordEvent($event);
            }
        };

        $event1 = new class implements DomainEventInterface {
            public function getOccurredAt(): DateTimeImmutable { return new DateTimeImmutable(); }
        };
        $event2 = new class implements DomainEventInterface {
            public function getOccurredAt(): DateTimeImmutable { return new DateTimeImmutable(); }
        };
        $dummy->trigger($event1);
        $dummy->trigger($event2);

        $events = iterator_to_array($dummy->releaseEvents(), false);

        $this->assertCount(2, $events);
        $this->assertSame($event1, $events[0]);
        $this->assertSame($event2, $events[1]);

        $subsequentEvents = iterator_to_array($dummy->releaseEvents(), false);
        $this->assertEmpty($subsequentEvents);
    }
}

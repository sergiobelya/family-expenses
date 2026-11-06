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
            public function trigger(string $name): void
            {
                $this->recordEvent(new class($name) implements DomainEventInterface {
                    public function __construct(private string $name) {}
                    public function name(): string { return $this->name; }
                    public function getOccurredAt(): DateTimeImmutable { return new DateTimeImmutable(); }
                });
            }
        };

        $dummy->trigger('event_1');
        $dummy->trigger('event_2');

        $events = iterator_to_array($dummy->releaseEvents(), false);

        $this->assertCount(2, $events);
        $this->assertSame('event_1', $events[0]->name());
        $this->assertSame('event_2', $events[1]->name());

        $subsequentEvents = iterator_to_array($dummy->releaseEvents(), false);
        $this->assertEmpty($subsequentEvents);
    }
}

<?php

namespace App\Tests\Unit\Infrastructure\Core;

use App\Core\Domain\Aggregate\AggregateRoot;
use App\Core\Domain\Event\AsyncDomainEventInterface;
use App\Core\Domain\Event\AuditableEventInterface;
use App\Core\Domain\Event\DomainEventInterface;
use App\Core\Infrastructure\Persistence\Doctrine\Entity\AuditLog;
use App\Core\Infrastructure\Persistence\Doctrine\Entity\OutboxMessage;
use App\Core\Infrastructure\Persistence\Doctrine\Listener\DomainEventsFlushListener;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class DomainEventsFlushListenerTest extends TestCase
{
    public function testListenerConvertsEventsToOutboxAndAuditLog(): void
    {
        $listener = new DomainEventsFlushListener();

        $eventId = Uuid::v7();
        $familyId = Uuid::v7();
        $actorId = Uuid::v7();

        $auditableEvent = new class($eventId, $familyId, $actorId) implements AuditableEventInterface, AsyncDomainEventInterface {
            public function __construct(
                private Uuid $id,
                private Uuid $familyId,
                private Uuid $actorId
            ) {}
            public function getEventName(): string { return 'family.created'; }
            public function getOccurredAt(): DateTimeImmutable { return new DateTimeImmutable(); }
            public function toPayload(): array { return ['name' => 'Smiths']; }
            public function getFamilyId(): ?Uuid { return $this->familyId; }
            public function getActorUserId(): ?Uuid { return $this->actorId; }
            public function getEntityType(): string { return 'family'; }
            public function getEntityId(): string { return (string) $this->familyId; }
            public function getAction(): string { return 'create_family'; }
            public function getBeforeData(): ?array { return null; }
            public function getAfterData(): ?array { return ['name' => 'Smiths']; }
        };

        $entity = new class($auditableEvent) extends AggregateRoot {
            public function __construct(DomainEventInterface $event) {
                $this->recordEvent($event);
            }
        };

        $em = $this->createMock(EntityManagerInterface::class);
        $uow = $this->createMock(UnitOfWork::class);

        $em->method('getUnitOfWork')->willReturn($uow);

        $uow->method('getScheduledEntityInsertions')->willReturn([$entity]);
        $uow->method('getScheduledEntityUpdates')->willReturn([]);
        $uow->method('getScheduledEntityDeletions')->willReturn([]);

        $persistedEntities = [];
        $em->method('persist')->willReturnCallback(function ($persisted) use (&$persistedEntities) {
            $persistedEntities[] = $persisted;
        });

        $uow->expects($this->atLeastOnce())->method('computeChangeSet');

        $onFlushArgs = new OnFlushEventArgs($em);
        $listener->onFlush($onFlushArgs);

        $this->assertCount(2, $persistedEntities);
        $this->assertInstanceOf(AuditLog::class, $persistedEntities[0]);
        $this->assertInstanceOf(OutboxMessage::class, $persistedEntities[1]);

        $this->assertSame('create_family', $persistedEntities[0]->getAction());
        $this->assertSame('family.created', $persistedEntities[1]->getEventType());

        // Test postFlush clears buffer
        $postFlushArgs = new PostFlushEventArgs($em);
        $listener->postFlush($postFlushArgs);
    }

    public function testListenerWritesAuditLogWithoutOutboxForSynchronousEvents(): void
    {
        $listener = new DomainEventsFlushListener();
        $familyId = Uuid::v7();

        $event = new class($familyId) implements AuditableEventInterface {
            public function __construct(private Uuid $familyId) {}
            public function getOccurredAt(): DateTimeImmutable { return new DateTimeImmutable(); }
            public function getFamilyId(): ?Uuid { return $this->familyId; }
            public function getActorUserId(): ?Uuid { return null; }
            public function getEntityType(): string { return 'family'; }
            public function getEntityId(): string { return (string) $this->familyId; }
            public function getAction(): string { return 'family.settings_updated'; }
            public function getBeforeData(): ?array { return ['name' => 'Old']; }
            public function getAfterData(): ?array { return ['name' => 'New']; }
        };

        $entity = new class($event) extends AggregateRoot {
            public function __construct(DomainEventInterface $event) {
                $this->recordEvent($event);
            }
        };

        $em = $this->createMock(EntityManagerInterface::class);
        $uow = $this->createMock(UnitOfWork::class);
        $em->method('getUnitOfWork')->willReturn($uow);
        $uow->method('getScheduledEntityInsertions')->willReturn([$entity]);
        $uow->method('getScheduledEntityUpdates')->willReturn([]);
        $uow->method('getScheduledEntityDeletions')->willReturn([]);

        $persistedEntities = [];
        $em->method('persist')->willReturnCallback(function ($persisted) use (&$persistedEntities): void {
            $persistedEntities[] = $persisted;
        });
        $uow->expects($this->once())->method('computeChangeSet');

        $listener->onFlush(new OnFlushEventArgs($em));

        self::assertCount(1, $persistedEntities);
        self::assertInstanceOf(AuditLog::class, $persistedEntities[0]);
    }
}

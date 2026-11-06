<?php

namespace App\Core\Infrastructure\Persistence\Doctrine\Listener;

use App\Core\Domain\Aggregate\ContainsDomainEventsInterface;
use App\Core\Domain\Event\AsyncDomainEventInterface;
use App\Core\Domain\Event\AuditableEventInterface;
use App\Core\Domain\Event\DomainEventInterface;
use App\Core\Infrastructure\Persistence\Doctrine\Entity\AuditLog;
use App\Core\Infrastructure\Persistence\Doctrine\Entity\OutboxMessage;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsDoctrineListener(event: Events::onFlush, priority: 100)]
#[AsDoctrineListener(event: Events::postFlush, priority: -100)]
class DomainEventsFlushListener
{
    /** @var DomainEventInterface[] */
    private array $eventsToDispatch = [];

    public function __construct(
        #[Target('event.bus')]
        private ?MessageBusInterface $eventBus = null
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        $entities = array_merge(
            $uow->getScheduledEntityInsertions(),
            $uow->getScheduledEntityUpdates(),
            $uow->getScheduledEntityDeletions()
        );

        foreach ($entities as $entity) {
            if (!$entity instanceof ContainsDomainEventsInterface) {
                continue;
            }

            foreach ($entity->releaseEvents() as $event) {
                if ($event instanceof AuditableEventInterface) {
                    $auditLog = new AuditLog(
                        entityType: $event->getEntityType(),
                        entityId: $event->getEntityId(),
                        action: $event->getAction(),
                        familyId: $event->getFamilyId(),
                        actorUserId: $event->getActorUserId(),
                        beforeData: $event->getBeforeData(),
                        afterData: $event->getAfterData(),
                        now: $event->getOccurredAt()
                    );
                    $em->persist($auditLog);
                    $uow->computeChangeSet($em->getClassMetadata(AuditLog::class), $auditLog);
                }

                if ($event instanceof AsyncDomainEventInterface) {
                    $outboxMessage = new OutboxMessage(
                        eventType: $event->getEventName(),
                        payload: $event->toPayload(),
                        status: OutboxMessage::STATUS_PENDING,
                        now: $event->getOccurredAt()
                    );
                    $em->persist($outboxMessage);
                    $uow->computeChangeSet($em->getClassMetadata(OutboxMessage::class), $outboxMessage);
                }

                $this->eventsToDispatch[] = $event;
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        $events = $this->eventsToDispatch;
        $this->eventsToDispatch = [];

        if ($this->eventBus === null) {
            return;
        }

        foreach ($events as $event) {
            $this->eventBus->dispatch($event);
        }
    }
}

<?php

namespace App\Tests\Unit\Core\Infrastructure\Outbox;

use App\Core\Infrastructure\Outbox\OutboxEnvelope;
use App\Core\Infrastructure\Outbox\OutboxPublisher;
use App\Core\Infrastructure\Persistence\Doctrine\Entity\OutboxMessage;
use App\Core\Infrastructure\Persistence\Doctrine\Repository\OutboxRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class OutboxPublisherTest extends TestCase
{
    public function testPublishSingleMessageSuccess(): void
    {
        $message = new OutboxMessage('family.created', ['name' => 'Smiths']);

        $repository = $this->createMock(OutboxRepository::class);
        $repository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (OutboxMessage $msg) {
                return $msg->getStatus() === OutboxMessage::STATUS_PUBLISHED
                    && $msg->getPublishedAt() !== null;
            }));

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(OutboxEnvelope::class))
            ->willReturn(new Envelope(new \stdClass()));

        $publisher = new OutboxPublisher($repository, $bus, new NullLogger());
        $publisher->publish($message);

        $this->assertSame(OutboxMessage::STATUS_PUBLISHED, $message->getStatus());
        $this->assertNotNull($message->getPublishedAt());
    }

    public function testPublishBatchProcessesPendingAndMarksFailedOnException(): void
    {
        $msg1 = new OutboxMessage('user.created', ['id' => 1]);
        $msg2 = new OutboxMessage('family.created', ['id' => 2]);

        $repository = $this->createMock(OutboxRepository::class);
        $repository->expects($this->once())
            ->method('findPending')
            ->with(50)
            ->willReturn([$msg1, $msg2]);

        $repository->expects($this->exactly(2))
            ->method('save')
            ->with($this->isInstanceOf(OutboxMessage::class));

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (OutboxEnvelope $envelope) {
                if ($envelope->eventType === 'family.created') {
                    throw new RuntimeException('Transport error');
                }
                return new Envelope(new \stdClass());
            });

        $publisher = new OutboxPublisher($repository, $bus, new NullLogger());
        $publishedCount = $publisher->publishBatch(50);

        $this->assertSame(1, $publishedCount);
        $this->assertSame(OutboxMessage::STATUS_PUBLISHED, $msg1->getStatus());
        $this->assertSame(OutboxMessage::STATUS_FAILED, $msg2->getStatus());
    }
}

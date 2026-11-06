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

    public function testManualRetryPublishesFailedMessageOnce(): void
    {
        $message = new OutboxMessage('family.created', [], OutboxMessage::STATUS_FAILED);
        $repository = $this->createMock(OutboxRepository::class);
        $repository->expects(self::once())->method('save')->with($message);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(OutboxEnvelope::class))
            ->willReturn(new Envelope(new \stdClass()));

        $published = (new OutboxPublisher($repository, $bus, new NullLogger()))->retryFailed($message);

        self::assertTrue($published);
        self::assertSame(OutboxMessage::STATUS_PUBLISHED, $message->getStatus());
    }

    public function testManualRetryKeepsFailedMessageFailedAfterOneFailedAttempt(): void
    {
        $message = new OutboxMessage('family.created', [], OutboxMessage::STATUS_FAILED);
        $repository = $this->createMock(OutboxRepository::class);
        $repository->expects(self::once())->method('save')->with($message);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->willThrowException(new RuntimeException('Broker unavailable'));

        $published = (new OutboxPublisher($repository, $bus, new NullLogger()))->retryFailed($message);

        self::assertFalse($published);
        self::assertSame(OutboxMessage::STATUS_FAILED, $message->getStatus());
        self::assertSame(1, $message->getAttemptCount());
        self::assertSame('Broker unavailable', $message->getLastError());
    }

    public function testPublishBatchMarksMessageFailedOnTenthFailedAttempt(): void
    {
        $msg1 = new OutboxMessage('user.created', ['id' => 1]);
        $msg2 = new OutboxMessage('family.created', ['id' => 2]);
        for ($attempt = 0; $attempt < 9; ++$attempt) {
            $msg2->scheduleRetry('Earlier transport error', new \DateTimeImmutable());
        }

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
        $this->assertSame(10, $msg2->getAttemptCount());
        $this->assertSame('Transport error', $msg2->getLastError());
        $this->assertNotNull($msg2->getNextAttemptAt());
    }

    public function testNineRetriesRemainPendingWithBackoffTotalingAboutAnHour(): void
    {
        $message = new OutboxMessage('family.created', ['id' => 2]);
        $repository = $this->createMock(OutboxRepository::class);
        $repository->expects(self::exactly(9))
            ->method('findPending')
            ->willReturn([$message]);
        $repository->expects(self::exactly(9))->method('save')->with($message);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::exactly(9))
            ->method('dispatch')
            ->willThrowException(new RuntimeException('Broker unavailable'));

        $publisher = new OutboxPublisher($repository, $bus, new NullLogger());
        $expectedDelays = [7, 14, 28, 56, 112, 224, 448, 896, 1792];
        foreach ($expectedDelays as $expectedDelay) {
            $startedAt = time();
            self::assertSame(0, $publisher->publishBatch());

            $scheduledDelay = $message->getNextAttemptAt()->getTimestamp() - $startedAt;
            self::assertGreaterThanOrEqual($expectedDelay - 1, $scheduledDelay);
            self::assertLessThanOrEqual($expectedDelay + 1, $scheduledDelay);
        }

        self::assertSame(OutboxMessage::STATUS_PENDING, $message->getStatus());
        self::assertSame(9, $message->getAttemptCount());
        self::assertSame('Broker unavailable', $message->getLastError());
        self::assertSame(3577, array_sum($expectedDelays));
    }
}

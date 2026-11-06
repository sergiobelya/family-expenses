<?php

namespace App\Tests\Unit\Core\Infrastructure\Outbox\Command;

use App\Core\Infrastructure\Outbox\Console\RetryFailedOutboxMessagesCommand;
use App\Core\Infrastructure\Outbox\OutboxPublisherInterface;
use App\Core\Infrastructure\Persistence\Doctrine\Entity\OutboxMessage;
use App\Core\Infrastructure\Persistence\Doctrine\Repository\OutboxRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class RetryFailedOutboxMessagesCommandTest extends TestCase
{
    public function testMakesOnePublishingAttemptForFailedMessages(): void
    {
        $message = new OutboxMessage('family.created', [], OutboxMessage::STATUS_FAILED);
        $repository = $this->createMock(OutboxRepository::class);
        $repository->expects(self::once())
            ->method('findFailed')
            ->with(100)
            ->willReturn([$message]);
        $publisher = $this->createMock(OutboxPublisherInterface::class);
        $publisher->expects(self::once())
            ->method('retryFailed')
            ->with($message)
            ->willReturn(true);

        $command = new RetryFailedOutboxMessagesCommand($repository, $publisher);
        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Attempted 1 failed outbox message(s): 1 published, 0 still failed', $tester->getDisplay());
    }

    public function testLimitOptionControlsNumberOfFailedMessagesFetched(): void
    {
        $repository = $this->createMock(OutboxRepository::class);
        $repository->expects(self::once())
            ->method('findFailed')
            ->with(5)
            ->willReturn([]);
        $publisher = $this->createMock(OutboxPublisherInterface::class);

        $tester = new CommandTester(new RetryFailedOutboxMessagesCommand($repository, $publisher));
        $exitCode = $tester->execute(['--limit' => 5]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('No failed outbox messages', $tester->getDisplay());
    }
}

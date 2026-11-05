<?php

namespace App\Tests\Unit\Core\Infrastructure\Outbox\Command;

use App\Core\Infrastructure\Outbox\Console\PublishOutboxMessagesCommand;
use App\Core\Infrastructure\Outbox\OutboxPublisherInterface;
use App\Core\Infrastructure\Persistence\Doctrine\Repository\OutboxRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class PublishOutboxMessagesCommandTest extends TestCase
{
    public function testExecuteSingleRun(): void
    {
        $publisher = $this->createMock(OutboxPublisherInterface::class);
        $publisher->expects($this->once())
            ->method('publishBatch')
            ->with(100)
            ->willReturn(5);

        $repository = $this->createMock(OutboxRepository::class);
        $repository->expects($this->once())
            ->method('countPending')
            ->willReturn(0);

        $command = new PublishOutboxMessagesCommand($publisher, $repository);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Outbox Message Publisher', $output);
        $this->assertStringContainsString('Published 5 message(s)', $output);
        $this->assertStringContainsString('Outbox publishing completed', $output);
    }

    public function testExecuteWithCustomBatchSizeAndMaxMessages(): void
    {
        $publisher = $this->createMock(OutboxPublisherInterface::class);
        $publisher->expects($this->once())
            ->method('publishBatch')
            ->with(10)
            ->willReturn(10);

        $repository = $this->createMock(OutboxRepository::class);
        $repository->expects($this->once())
            ->method('countPending')
            ->willReturn(2);

        $command = new PublishOutboxMessagesCommand($publisher, $repository);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            '--batch-size' => 10,
            '--max-messages' => 10,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = preg_replace('/\s+/', ' ', $tester->getDisplay());
        $this->assertStringContainsString('Total published in session: 10', $output);
        $this->assertStringContainsString('Pending remaining in queue: 2', $output);
    }

    public function testSignalsSubscription(): void
    {
        $publisher = $this->createMock(OutboxPublisherInterface::class);
        $repository = $this->createMock(OutboxRepository::class);

        $command = new PublishOutboxMessagesCommand($publisher, $repository);
        $this->assertSame([SIGINT, SIGTERM], $command->getSubscribedSignals());
        $this->assertFalse($command->handleSignal(SIGINT));
    }
}

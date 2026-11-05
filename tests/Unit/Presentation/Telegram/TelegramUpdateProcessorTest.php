<?php

namespace App\Tests\Unit\Presentation\Telegram;

use App\Core\Infrastructure\Persistence\Doctrine\Entity\TelegramUpdateInbox;
use App\Core\Infrastructure\Persistence\Doctrine\Repository\TelegramUpdateInboxRepository;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Flow\TelegramUpdateRouter;
use App\Presentation\Telegram\TelegramUpdateProcessor;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TelegramUpdateProcessorTest extends TestCase
{
    public function testProcessesAndMarksUpdateAsProcessed(): void
    {
        $update = TelegramUpdate::fromArray(['update_id' => 123]);
        $inboxRecord = new TelegramUpdateInbox(123);

        $router = $this->createMock(TelegramUpdateRouter::class);
        $router->expects($this->once())->method('route')->with($update);

        $inboxRepository = $this->createMock(TelegramUpdateInboxRepository::class);
        $inboxRepository->expects($this->once())
            ->method('recordIfUnprocessed')
            ->with(123)
            ->willReturn($inboxRecord);
        $inboxRepository->expects($this->once())->method('markProcessed')->with($inboxRecord);

        $processor = new TelegramUpdateProcessor($router, $inboxRepository);

        $this->assertTrue($processor->process($update));
    }

    public function testDoesNotProcessAlreadyProcessedUpdateAgain(): void
    {
        $update = TelegramUpdate::fromArray(['update_id' => 123]);

        $router = $this->createMock(TelegramUpdateRouter::class);
        $router->expects($this->never())->method('route');

        $inboxRepository = $this->createMock(TelegramUpdateInboxRepository::class);
        $inboxRepository->expects($this->once())
            ->method('recordIfUnprocessed')
            ->with(123)
            ->willReturn(null);
        $inboxRepository->expects($this->never())->method('markProcessed');

        $processor = new TelegramUpdateProcessor($router, $inboxRepository);

        $this->assertFalse($processor->process($update));
    }

    public function testRejectsUpdateWithoutPositiveId(): void
    {
        $processor = new TelegramUpdateProcessor(
            $this->createMock(TelegramUpdateRouter::class),
            $this->createMock(TelegramUpdateInboxRepository::class)
        );

        $this->expectException(InvalidArgumentException::class);
        $processor->process(TelegramUpdate::fromArray([]));
    }
}

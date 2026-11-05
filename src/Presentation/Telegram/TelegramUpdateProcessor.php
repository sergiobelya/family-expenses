<?php

namespace App\Presentation\Telegram;

use App\Core\Infrastructure\Persistence\Doctrine\Repository\TelegramUpdateInboxRepository;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Flow\TelegramUpdateRouter;
use InvalidArgumentException;

class TelegramUpdateProcessor
{
    public function __construct(
        private TelegramUpdateRouter $updateRouter,
        private TelegramUpdateInboxRepository $inboxRepository
    ) {}

    public function process(TelegramUpdate $update): bool
    {
        if ($update->updateId < 1) {
            throw new InvalidArgumentException('Telegram update must have a positive update_id.');
        }

        $inboxRecord = $this->inboxRepository->recordIfUnprocessed($update->updateId);
        if ($inboxRecord === null) {
            return false;
        }

        $this->updateRouter->route($update);
        $this->inboxRepository->markProcessed($inboxRecord);

        return true;
    }
}

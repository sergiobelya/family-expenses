<?php

namespace App\Presentation\Telegram\Console;

use App\Core\Infrastructure\Persistence\Doctrine\Repository\TelegramUpdateInboxRepository;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\TelegramUpdateProcessor;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use UnexpectedValueException;

#[AsCommand(
    name: 'app:telegram:poll',
    description: 'Receive and process Telegram updates using long polling'
)]
class PollTelegramUpdatesCommand extends Command
{
    private const int LONG_POLL_TIMEOUT = 30;

    public function __construct(
        private TelegramApiClientInterface $telegramClient,
        private TelegramUpdateProcessor $updateProcessor,
        private TelegramUpdateInboxRepository $inboxRepository,
        private LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $offset = $this->inboxRepository->getNextUpdateOffset();
        $output->writeln('Telegram long polling started. Press Ctrl+C to stop.');

        while (true) {
            try {
                $updates = $this->telegramClient->getUpdates($offset, self::LONG_POLL_TIMEOUT);
            } catch (Throwable $e) {
                $this->logger->error('Telegram getUpdates failed; retrying without advancing the offset', [
                    'offset' => $offset,
                    'error' => $e->getMessage(),
                ]);
                sleep(1);
                continue;
            }

            foreach ($updates as $data) {
                try {
                    if (!is_array($data)) {
                        throw new UnexpectedValueException('Telegram returned an invalid update.');
                    }

                    $update = TelegramUpdate::fromArray($data);
                    $this->updateProcessor->process($update);
                    $offset = $update->updateId + 1;
                } catch (Throwable $e) {
                    $this->logger->error('Failed to process Telegram update; polling stopped without advancing its offset', [
                        'offset' => $offset,
                        'error' => $e->getMessage(),
                    ]);

                    return Command::FAILURE;
                }
            }
        }
    }
}

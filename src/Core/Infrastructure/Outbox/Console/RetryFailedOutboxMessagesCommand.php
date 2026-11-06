<?php

namespace App\Core\Infrastructure\Outbox\Console;

use App\Core\Infrastructure\Persistence\Doctrine\Repository\OutboxRepository;
use App\Core\Infrastructure\Outbox\OutboxPublisherInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:outbox:retry-failed',
    description: 'Makes one publishing attempt for each selected failed outbox message'
)]
final class RetryFailedOutboxMessagesCommand extends Command
{
    public function __construct(
        private OutboxRepository $outboxRepository,
        private OutboxPublisherInterface $outboxPublisher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Maximum number of failed messages to attempt', 100);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = max(1, (int) $input->getOption('limit'));
        $messages = $this->outboxRepository->findFailed($limit);

        if ($messages === []) {
            $io->success('No failed outbox messages to retry.');

            return Command::SUCCESS;
        }

        $publishedCount = 0;
        foreach ($messages as $message) {
            if ($this->outboxPublisher->retryFailed($message)) {
                ++$publishedCount;
            }
        }

        $io->success(sprintf(
            'Attempted %d failed outbox message(s): %d published, %d still failed.',
            count($messages),
            $publishedCount,
            count($messages) - $publishedCount
        ));

        return Command::SUCCESS;
    }
}

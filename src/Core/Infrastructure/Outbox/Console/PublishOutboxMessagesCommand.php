<?php

namespace App\Core\Infrastructure\Outbox\Console;

use App\Core\Infrastructure\Outbox\OutboxPublisherInterface;
use App\Core\Infrastructure\Persistence\Doctrine\Repository\OutboxRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:outbox:publish',
    description: 'Publishes pending transactional outbox messages to the message broker'
)]
class PublishOutboxMessagesCommand extends Command implements SignalableCommandInterface
{
    private bool $shouldStop = false;

    public function __construct(
        private OutboxPublisherInterface $outboxPublisher,
        private OutboxRepository $outboxRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, 'Number of messages to process in a single batch', 100)
            ->addOption('continuous', 'c', InputOption::VALUE_NONE, 'Run continuously in a loop')
            ->addOption('sleep', 's', InputOption::VALUE_REQUIRED, 'Seconds to sleep when no messages are found in continuous mode', 1.0)
            ->addOption('max-messages', 'm', InputOption::VALUE_REQUIRED, 'Maximum number of messages to publish before exiting (0 = unlimited)', 0)
            ->addOption('time-limit', 't', InputOption::VALUE_REQUIRED, 'Maximum execution time in seconds before exiting (0 = unlimited)', 0);
    }

    public function getSubscribedSignals(): array
    {
        return [SIGINT, SIGTERM];
    }

    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        $this->shouldStop = true;

        return false;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $batchSize = max(1, (int) $input->getOption('batch-size'));
        $continuous = (bool) $input->getOption('continuous');
        $sleepSeconds = max(0.1, (float) $input->getOption('sleep'));
        $maxMessages = max(0, (int) $input->getOption('max-messages'));
        $timeLimit = max(0, (int) $input->getOption('time-limit'));

        $startTime = microtime(true);
        $totalPublished = 0;

        $io->title('Outbox Message Publisher');
        $io->text(sprintf(
            'Starting outbox publisher (mode: %s, batch-size: %d, max-messages: %s, time-limit: %s)...',
            $continuous ? 'continuous' : 'single-run',
            $batchSize,
            $maxMessages > 0 ? (string) $maxMessages : 'unlimited',
            $timeLimit > 0 ? $timeLimit . 's' : 'unlimited'
        ));

        do {
            if ($this->shouldStop) {
                $io->warning('Signal received. Stopping outbox publisher gracefully...');
                break;
            }

            if ($timeLimit > 0 && (microtime(true) - $startTime) >= $timeLimit) {
                $io->text('Time limit reached. Exiting...');
                break;
            }

            $currentBatchSize = $batchSize;
            if ($maxMessages > 0) {
                $remaining = $maxMessages - $totalPublished;
                if ($remaining <= 0) {
                    $io->text('Maximum messages limit reached. Exiting...');
                    break;
                }
                $currentBatchSize = min($batchSize, $remaining);
            }

            $published = $this->outboxPublisher->publishBatch($currentBatchSize);
            $totalPublished += $published;

            if ($published > 0) {
                $io->text(sprintf('[%s] Published %d message(s). Total published: %d', date('Y-m-d H:i:s'), $published, $totalPublished));
            } elseif ($continuous) {
                // Sleep using usleep
                usleep((int) ($sleepSeconds * 1_000_000));
            }

        } while ($continuous && !$this->shouldStop);

        $pendingLeft = $this->outboxRepository->countPending();
        $io->success(sprintf(
            'Outbox publishing completed. Total published in session: %d. Pending remaining in queue: %d.',
            $totalPublished,
            $pendingLeft
        ));

        return Command::SUCCESS;
    }
}

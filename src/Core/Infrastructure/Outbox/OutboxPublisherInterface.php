<?php

namespace App\Core\Infrastructure\Outbox;

use App\Core\Infrastructure\Persistence\Doctrine\Entity\OutboxMessage;

interface OutboxPublisherInterface
{
    /**
     * Publishes a single outbox message.
     */
    public function publish(OutboxMessage $message): void;

    /**
     * Makes one publishing attempt for a failed message.
     */
    public function retryFailed(OutboxMessage $message): bool;

    /**
     * Publishes a batch of pending outbox messages.
     *
     * @return int Number of successfully published messages.
     */
    public function publishBatch(int $limit = 100): int;
}

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
     * Publishes a batch of pending outbox messages.
     *
     * @return int Number of successfully published messages.
     */
    public function publishBatch(int $limit = 100): int;
}

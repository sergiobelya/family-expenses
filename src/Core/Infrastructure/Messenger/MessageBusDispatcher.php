<?php

namespace App\Core\Infrastructure\Messenger;

use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Wraps Symfony Messenger bus dispatch to extract the handler result.
 *
 * Used across Presentation layer to avoid duplicating the dispatch + HandledStamp
 * extraction pattern in every flow handler and step class.
 */
final readonly class MessageBusDispatcher implements MessageBusDispatcherInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private MessageBusInterface $queryBus,
    ) {
    }

    /**
     * Dispatch a command and return the handler result.
     */
    public function dispatchCommand(object $command): mixed
    {
        $envelope = $this->commandBus->dispatch($command);
        $handledStamp = $envelope->last(HandledStamp::class);
        return $handledStamp?->getResult();
    }

    /**
     * Dispatch a query and return the handler result.
     */
    public function dispatchQuery(object $query): mixed
    {
        $envelope = $this->queryBus->dispatch($query);
        $handledStamp = $envelope->last(HandledStamp::class);
        return $handledStamp?->getResult();
    }
}

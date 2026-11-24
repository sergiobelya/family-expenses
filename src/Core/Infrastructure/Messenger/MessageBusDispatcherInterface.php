<?php

namespace App\Core\Infrastructure\Messenger;

interface MessageBusDispatcherInterface
{
    /**
     * Dispatch a command and return the handler result.
     */
    public function dispatchCommand(object $command): mixed;

    /**
     * Dispatch a query and return the handler result.
     */
    public function dispatchQuery(object $query): mixed;
}

<?php

namespace App\Presentation\Telegram\Flow;

use App\Presentation\Telegram\Context\TelegramUserContextResolver;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class TelegramUpdateRouter
{
    /**
     * @param iterable<FlowHandlerInterface> $handlers
     */
    public function __construct(
        private TelegramUserContextResolver $contextResolver,
        #[AutowireIterator(FlowHandlerInterface::class)]
        private iterable $handlers
    ) {}

    public function route(TelegramUpdate $update): void
    {
        $context = $this->contextResolver->resolve($update);
        if ($context === null) {
            return;
        }

        foreach ($this->handlers as $handler) {
            if ($handler->supports($update, $context)) {
                $handler->handle($update, $context);
                return;
            }
        }
    }
}

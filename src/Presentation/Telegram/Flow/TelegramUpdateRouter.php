<?php

namespace App\Presentation\Telegram\Flow;

use App\Core\Infrastructure\Persistence\Redis\UserInteractionStateRepository;
use App\Presentation\Telegram\Context\TelegramUserContextResolver;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Keyboard\MainMenuKeyboard;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class TelegramUpdateRouter
{
    /**
     * @param iterable<FlowHandlerInterface> $handlers
     */
    public function __construct(
        private TelegramUserContextResolver $contextResolver,
        private UserInteractionStateRepository $stateRepository,
        private MainMenuKeyboard $mainMenuKeyboard,
        #[AutowireIterator('app.telegram_flow_handler')]
        private iterable $handlers
    ) {}

    public function route(TelegramUpdate $update): void
    {
        $context = $this->contextResolver->resolve($update);
        if ($context === null) {
            return;
        }

        if ($this->isMainMenuNavigation($update)) {
            $this->stateRepository->clear($context->telegramUserId);
        }

        foreach ($this->handlers as $handler) {
            if ($handler->supports($update, $context)) {
                $handler->handle($update, $context);
                return;
            }
        }
    }

    private function isMainMenuNavigation(TelegramUpdate $update): bool
    {
        if ($update->callbackData === 'nav:main_menu') {
            return true;
        }

        if ($update->text === null) {
            return false;
        }

        return $this->mainMenuKeyboard->isMenuButton($update->text)
            || in_array($update->text, ['/menu', '/start', '/settings', '/switch'], true);
    }
}

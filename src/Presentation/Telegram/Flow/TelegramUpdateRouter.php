<?php

namespace App\Presentation\Telegram\Flow;

use App\Core\Infrastructure\Persistence\Redis\UserInteractionStateRepository;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Context\TelegramUserContextResolver;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Keyboard\MainMenuKeyboard;
use LogicException;
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
            if ($handler instanceof FamilyContextFlowHandlerInterface) {
                if (!$context instanceof TelegramFamilyMemberContext) {
                    continue;
                }
                if (!$handler->supports($update, $context)) {
                    continue;
                }
                $handler->handle($update, $context);
                return;
            }

            if ($handler instanceof UserContextFlowHandlerInterface) {
                if (!$handler->supports($update, $context)) {
                    continue;
                }
                $handler->handle($update, $context);
                return;
            }

            throw new LogicException('Telegram flow handler must declare its context type.');
        }
    }

    private function isMainMenuNavigation(TelegramUpdate $update): bool
    {
        if ($update->callbackData === CallbackActions::NAV_MAIN_MENU) {
            return true;
        }

        if ($update->text === null) {
            return false;
        }

        return $this->mainMenuKeyboard->isMenuButton($update->text)
            || in_array($update->text, ['/menu', '/start', '/settings', '/switch'], true);
    }
}

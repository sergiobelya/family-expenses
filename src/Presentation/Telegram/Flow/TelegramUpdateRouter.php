<?php

namespace App\Presentation\Telegram\Flow;

use App\Core\Infrastructure\Persistence\Redis\UserInteractionStateRepository;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\Context\TelegramUserContextResolver;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Keyboard\MainMenuKeyboard;
use App\Presentation\Telegram\MapExceptionToTranslatableMessage;
use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

readonly class TelegramUpdateRouter
{
    /**
     * @param iterable<FlowHandlerInterface> $handlers
     */
    public function __construct(
        private TelegramUserContextResolver $contextResolver,
        private UserInteractionStateRepository $stateRepository,
        private MainMenuKeyboard $mainMenuKeyboard,
        private TelegramApiClientInterface $telegramClient,
        private MapExceptionToTranslatableMessage $exceptionToTranslation,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
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
                try {
                    $handler->handle($update, $context);
                } catch (Throwable $e) {
                    $this->handleUnhandledException($context, $e);
                }
                return;
            }

            if ($handler instanceof UserContextFlowHandlerInterface) {
                if (!$handler->supports($update, $context)) {
                    continue;
                }
                try {
                    $handler->handle($update, $context);
                } catch (Throwable $e) {
                    $this->handleUnhandledException($context, $e);
                }
                return;
            }

            throw new LogicException('Telegram flow handler must declare its context type.');
        }
    }

    private function handleUnhandledException(TelegramUserContext $context, Throwable $exception): void
    {
        $message = ($this->exceptionToTranslation)($exception);
        $messageForUser = $message->trans($this->translator, $context->language);
        $this->logger->error('Unhandled Telegram flow exception', [
            'user_id' => $context->telegramUserId,
            'translated_message' => $messageForUser,
            'exception' => $exception,
        ]);
        $this->telegramClient->sendMessage(chatId: $context->chatId, text: $messageForUser);
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
            || in_array($update->text, ['/add', '/menu', '/start', '/settings', '/switch'], true);
    }
}

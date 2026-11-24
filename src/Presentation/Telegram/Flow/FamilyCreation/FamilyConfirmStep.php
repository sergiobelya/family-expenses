<?php

namespace App\Presentation\Telegram\Flow\FamilyCreation;

use App\Core\Domain\Exception\InvalidValueException;
use App\Core\Infrastructure\Messenger\MessageBusDispatcherInterface;
use App\Core\Infrastructure\Persistence\Redis\UserInteractionState;
use App\Core\Infrastructure\Persistence\Redis\UserInteractionStateRepository;
use App\Family\Application\Command\CreateFamilyCommand;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\MapExceptionToTranslationKey;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Keyboard\InlineKeyboardBuilder;
use App\Presentation\Telegram\Keyboard\MainMenuKeyboard;
use Symfony\Contracts\Translation\TranslatorInterface;

final class FamilyConfirmStep implements FamilyCreationStepInterface
{
    public function __construct(
        private TelegramApiClientInterface $telegramClient,
        private UserInteractionStateRepository $stateRepository,
        private MessageBusDispatcherInterface $messageBusDispatcher,
        private MainMenuKeyboard $mainMenuKeyboard,
        private TranslatorInterface $translator,
        private MapExceptionToTranslationKey $exceptionToTranslation
    ) {}

    public function handle(
        TelegramUpdate $update,
        TelegramUserContext $context,
        UserInteractionState $state
    ): ?UserInteractionState {
        if ($update->callbackData !== CallbackActions::FAMILY_CREATE_SUBMIT) {
            return null;
        }

        $data = $state->data;

        try {
            $family = $this->messageBusDispatcher->dispatchCommand(new CreateFamilyCommand(
                userId: $context->user->getId(),
                name: (string) $data['name'],
                timezone: (string) $data['timezone'],
                defaultExpenseCurrency: (string) $data['default_currency'],
                reportingCurrency: (string) $data['reporting_currency'],
                defaultExchangeRateProvider: (string) ($data['provider'] ?? 'nbp')
            ));

            $this->stateRepository->clear($context->telegramUserId);

            $successMsg = $this->translator->trans('family.create.created_success', [
                '%{name}' => htmlspecialchars((string) $family->getName(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            ]);

            $this->telegramClient->sendMessage(
                chatId: $context->chatId,
                text: $successMsg,
                replyMarkup: $this->mainMenuKeyboard->build(false)
            );
        } catch (InvalidValueException $e) {
            $this->stateRepository->set(new UserInteractionState(
                $context->telegramUserId,
                'family_create',
                'name',
                $data
            ));
            $this->telegramClient->sendMessage(
                chatId: $context->chatId,
                text: $this->translator->trans(($this->exceptionToTranslation)($e))
            );
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function renderConfirmation(TelegramUserContext $context, array $data): void
    {
        $kb = (new InlineKeyboardBuilder())
            ->button($this->translator->trans('common.confirm'), CallbackActions::FAMILY_CREATE_SUBMIT)
            ->button($this->translator->trans('common.cancel'), CallbackActions::FAMILY_CREATE_CANCEL)
            ->toArray();

        $confirmText = $this->translator->trans('family.create.step_confirm', [
            '%{name}' => htmlspecialchars((string) $data['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            '%{timezone}' => $data['timezone'],
            '%{default_currency}' => $data['default_currency'],
            '%{reporting_currency}' => $data['reporting_currency'],
            '%{provider}' => $data['provider'],
        ]);

        $this->telegramClient->sendMessage(
            chatId: $context->chatId,
            text: $confirmText,
            replyMarkup: $kb
        );
    }
}

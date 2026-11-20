<?php

namespace App\Presentation\Telegram\Flow\FamilyCreation;

use App\Core\Infrastructure\Persistence\Redis\UserInteractionState;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Keyboard\InlineKeyboardBuilder;
use Symfony\Contracts\Translation\TranslatorInterface;

final class FamilyCurrencyStep implements FamilyCreationStepInterface
{
    public function __construct(
        private TelegramApiClientInterface $telegramClient,
        private TranslatorInterface $translator
    ) {}

    public function handle(
        TelegramUpdate $update,
        TelegramUserContext $context,
        UserInteractionState $state
    ): ?UserInteractionState {
        $data = $state->data;

        // Default currency selected
        if ($state->step === 'default_currency'
            && str_starts_with($update->callbackData ?? '', CallbackActions::FAMILY_CREATE_DEF_CURR_PREFIX)
        ) {
            $curr = substr($update->callbackData, strlen(CallbackActions::FAMILY_CREATE_DEF_CURR_PREFIX));
            $data['default_currency'] = $curr;
            $this->renderCurrencySelector($context, 'reporting_currency', $data);
            return new UserInteractionState($context->telegramUserId, 'family_create', 'reporting_currency', $data);
        }

        // Reporting currency selected
        if ($state->step === 'reporting_currency'
            && str_starts_with($update->callbackData ?? '', CallbackActions::FAMILY_CREATE_REP_CURR_PREFIX)
        ) {
            $curr = substr($update->callbackData, strlen(CallbackActions::FAMILY_CREATE_REP_CURR_PREFIX));
            $data['reporting_currency'] = $curr;
            return new UserInteractionState($context->telegramUserId, 'family_create', 'provider', $data);
        }

        return null;
    }

    public function renderDefaultCurrency(TelegramUserContext $context): void
    {
        $kb = (new InlineKeyboardBuilder())
            ->button('PLN', CallbackActions::FAMILY_CREATE_DEF_CURR_PREFIX . 'PLN')
            ->button('UAH', CallbackActions::FAMILY_CREATE_DEF_CURR_PREFIX . 'UAH')
            ->row()
            ->button('EUR', CallbackActions::FAMILY_CREATE_DEF_CURR_PREFIX . 'EUR')
            ->button('USD', CallbackActions::FAMILY_CREATE_DEF_CURR_PREFIX . 'USD')
            ->row()
            ->button($this->translator->trans('common.cancel'), CallbackActions::FAMILY_CREATE_CANCEL)
            ->toArray();

        $this->telegramClient->sendMessage(
            chatId: $context->chatId,
            text: $this->translator->trans('family.create.step_default_currency'),
            replyMarkup: $kb
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderCurrencySelector(
        TelegramUserContext $context,
        string $nextStep,
        array $data
    ): void {
        $kb = (new InlineKeyboardBuilder())
            ->button('PLN', CallbackActions::FAMILY_CREATE_REP_CURR_PREFIX . 'PLN')
            ->button('UAH', CallbackActions::FAMILY_CREATE_REP_CURR_PREFIX . 'UAH')
            ->row()
            ->button('EUR', CallbackActions::FAMILY_CREATE_REP_CURR_PREFIX . 'EUR')
            ->button('USD', CallbackActions::FAMILY_CREATE_REP_CURR_PREFIX . 'USD')
            ->row()
            ->button($this->translator->trans('common.cancel'), CallbackActions::FAMILY_CREATE_CANCEL)
            ->toArray();

        $this->telegramClient->sendMessage(
            chatId: $context->chatId,
            text: $this->translator->trans('family.create.step_reporting_currency'),
            replyMarkup: $kb
        );
    }
}

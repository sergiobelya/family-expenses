<?php

namespace App\Presentation\Telegram\Flow\FamilySettings;

use App\Core\Infrastructure\Messenger\MessageBusDispatcherInterface;
use App\Core\Infrastructure\Persistence\Redis\UserInteractionState;
use App\Family\Application\Command\UpdateFamilySettingsCommand;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Keyboard\InlineKeyboardBuilder;
use Closure;
use Symfony\Contracts\Translation\TranslatorInterface;

final class FamilySettingsCurrencyStep implements FamilySettingsStepInterface
{
    public function __construct(
        private TelegramApiClientInterface $telegramClient,
        private MessageBusDispatcherInterface $messageBusDispatcher,
        private TranslatorInterface $translator,
        private Closure $renderFamilySettings
    ) {}

    public function handle(
        TelegramUpdate $update,
        TelegramFamilyMemberContext $context,
        UserInteractionState $state
    ): ?UserInteractionState {
        if ($update->callbackData === CallbackActions::SETTINGS_FAMILY_EDIT_DEF_CURR) {
            $this->renderCurrencySelector($context, CallbackActions::SETTINGS_FAMILY_SET_DEF_CURR_PREFIX);
            return null;
        }

        if ($update->callbackData === CallbackActions::SETTINGS_FAMILY_EDIT_REP_CURR) {
            $this->renderCurrencySelector($context, CallbackActions::SETTINGS_FAMILY_SET_REP_CURR_PREFIX);
            return null;
        }

        if (str_starts_with($update->callbackData ?? '', CallbackActions::SETTINGS_FAMILY_SET_DEF_CURR_PREFIX)) {
            $curr = substr($update->callbackData, strlen(CallbackActions::SETTINGS_FAMILY_SET_DEF_CURR_PREFIX));
            $this->setCurrency($context, defaultExpenseCurrency: $curr);
            return null;
        }

        if (str_starts_with($update->callbackData ?? '', CallbackActions::SETTINGS_FAMILY_SET_REP_CURR_PREFIX)) {
            $curr = substr($update->callbackData, strlen(CallbackActions::SETTINGS_FAMILY_SET_REP_CURR_PREFIX));
            $this->setCurrency($context, reportingCurrency: $curr);
            return null;
        }

        return null;
    }

    private function setCurrency(
        TelegramFamilyMemberContext $context,
        ?string $defaultExpenseCurrency = null,
        ?string $reportingCurrency = null
    ): void {
        $this->messageBusDispatcher->dispatchCommand(new UpdateFamilySettingsCommand(
            familyId: $context->activeFamily->getId(),
            userId: $context->user->getId(),
            defaultExpenseCurrency: $defaultExpenseCurrency,
            reportingCurrency: $reportingCurrency
        ));

        $this->telegramClient->sendMessage(
            chatId: $context->chatId,
            text: $this->translator->trans('settings.saved')
        );
        ($this->renderFamilySettings)($context);
    }

    private function renderCurrencySelector(
        TelegramFamilyMemberContext $context,
        string $setPrefix
    ): void {
        $kb = (new InlineKeyboardBuilder())
            ->button('PLN', $setPrefix . 'PLN')
            ->button('UAH', $setPrefix . 'UAH')
            ->row()
            ->button('EUR', $setPrefix . 'EUR')
            ->button('USD', $setPrefix . 'USD')
            ->row()
            ->button($this->translator->trans('common.back'), CallbackActions::SETTINGS_FAMILY_VIEW)
            ->toArray();

        $this->telegramClient->sendMessage(
            chatId: $context->chatId,
            text: $this->translator->trans('settings.prompt_select_currency'),
            replyMarkup: $kb
        );
    }

}

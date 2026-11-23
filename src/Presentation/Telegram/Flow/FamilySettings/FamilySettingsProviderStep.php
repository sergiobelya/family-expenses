<?php

namespace App\Presentation\Telegram\Flow\FamilySettings;

use App\Core\Infrastructure\Persistence\Redis\UserInteractionState;
use App\Family\Application\Command\UpdateFamilySettingsCommand;
use App\Family\Application\Command\UpdateFamilySettingsHandler;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Keyboard\ExchangeRateProviderOptions;
use App\Presentation\Telegram\Keyboard\InlineKeyboardBuilder;
use Symfony\Contracts\Translation\TranslatorInterface;

final class FamilySettingsProviderStep implements FamilySettingsStepInterface
{
    public function __construct(
        private TelegramApiClientInterface $telegramClient,
        private UpdateFamilySettingsHandler $updateFamilySettingsHandler,
        private TranslatorInterface $translator,
        private FamilySettingsRenderer $familySettingsRenderer
    ) {}

    public function handle(
        TelegramUpdate $update,
        TelegramFamilyMemberContext $context,
        UserInteractionState $state
    ): ?UserInteractionState {
        // Provider selector
        if ($update->callbackData === CallbackActions::SETTINGS_FAMILY_EDIT_PROVIDER) {
            $this->renderProviderSelector($context);
            return null;
        }

        // Set provider
        if (str_starts_with($update->callbackData ?? '', CallbackActions::SETTINGS_FAMILY_SET_PROVIDER_PREFIX)) {
            $prov = substr($update->callbackData, strlen(CallbackActions::SETTINGS_FAMILY_SET_PROVIDER_PREFIX));
            if (!ExchangeRateProviderOptions::contains($prov)) {
                $this->telegramClient->sendMessage(
                    chatId: $context->chatId,
                    text: $this->translator->trans('settings.unsupported_provider')
                );
                return null;
            }

            ($this->updateFamilySettingsHandler)(new UpdateFamilySettingsCommand(
                familyId: $context->activeFamily->getId(),
                defaultExchangeRateProvider: $prov,
                userId: $context->user->getId()
            ));

            $this->telegramClient->sendMessage(
                chatId: $context->chatId,
                text: $this->translator->trans('settings.saved')
            );
            $this->familySettingsRenderer->render($context);
            return null;
        }

        return null;
    }

    private function renderProviderSelector(TelegramFamilyMemberContext $context): void
    {
        $builder = new InlineKeyboardBuilder();
        foreach (array_chunk(ExchangeRateProviderOptions::ALL, 2, true) as $providers) {
            foreach ($providers as $provider => $label) {
                $builder->button($label, CallbackActions::SETTINGS_FAMILY_SET_PROVIDER_PREFIX . $provider);
            }
            $builder->row();
        }
        $kb = $builder
            ->button($this->translator->trans('common.back'), CallbackActions::SETTINGS_FAMILY_VIEW)
            ->toArray();

        $this->telegramClient->sendMessage(
            chatId: $context->chatId,
            text: $this->translator->trans('settings.prompt_select_provider'),
            replyMarkup: $kb
        );
    }
}

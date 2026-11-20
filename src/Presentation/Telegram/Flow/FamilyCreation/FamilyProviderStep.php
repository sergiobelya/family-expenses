<?php

namespace App\Presentation\Telegram\Flow\FamilyCreation;

use App\Core\Infrastructure\Persistence\Redis\UserInteractionState;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Keyboard\ExchangeRateProviderOptions;
use App\Presentation\Telegram\Keyboard\InlineKeyboardBuilder;
use Symfony\Contracts\Translation\TranslatorInterface;

final class FamilyProviderStep implements FamilyCreationStepInterface
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

        if (!str_starts_with($update->callbackData ?? '', CallbackActions::FAMILY_CREATE_PROVIDER_PREFIX)) {
            return null;
        }

        $provider = substr($update->callbackData, strlen(CallbackActions::FAMILY_CREATE_PROVIDER_PREFIX));
        if (!ExchangeRateProviderOptions::contains($provider)) {
            $this->telegramClient->sendMessage(
                chatId: $context->chatId,
                text: $this->translator->trans('family.create.unsupported_provider')
            );
            return null;
        }

        $data['provider'] = $provider;
        return new UserInteractionState($context->telegramUserId, 'family_create', 'confirm', $data);
    }

    public function renderProviderSelector(TelegramUserContext $context): void
    {
        $builder = new InlineKeyboardBuilder();
        foreach (array_chunk(ExchangeRateProviderOptions::ALL, 2, true) as $providers) {
            foreach ($providers as $provider => $label) {
                $builder->button($label, CallbackActions::FAMILY_CREATE_PROVIDER_PREFIX . $provider);
            }
            $builder->row();
        }
        $kb = $builder
            ->button($this->translator->trans('common.cancel'), CallbackActions::FAMILY_CREATE_CANCEL)
            ->toArray();

        $this->telegramClient->sendMessage(
            chatId: $context->chatId,
            text: $this->translator->trans('family.create.step_provider'),
            replyMarkup: $kb
        );
    }
}

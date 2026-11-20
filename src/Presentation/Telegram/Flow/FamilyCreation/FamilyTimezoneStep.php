<?php

namespace App\Presentation\Telegram\Flow\FamilyCreation;

use App\Core\Infrastructure\Persistence\Redis\UserInteractionState;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Keyboard\InlineKeyboardBuilder;
use App\Presentation\Telegram\Keyboard\TimezoneSelector;
use Symfony\Contracts\Translation\TranslatorInterface;

final class FamilyTimezoneStep implements FamilyCreationStepInterface
{
    public function __construct(
        private TelegramApiClientInterface $telegramClient,
        private TimezoneSelector $timezoneSelector,
        private TranslatorInterface $translator
    ) {}

    public function handle(
        TelegramUpdate $update,
        TelegramUserContext $context,
        UserInteractionState $state
    ): ?UserInteractionState {
        $userId = $context->telegramUserId;
        $data = $state->data;

        // Handle text input for timezone search
        if ($state->step === 'timezone_search' && $update->text !== null && !str_starts_with($update->text, '/')) {
            return $this->renderTimezoneSelector($context, $data, trim($update->text));
        }

        // Switch to search mode
        if ($update->callbackData === CallbackActions::FAMILY_CREATE_TIMEZONE_SEARCH) {
            $newState = new UserInteractionState(
                $userId,
                'family_create',
                'timezone_search',
                $data + ['query' => '', 'page' => 0]
            );

            $this->telegramClient->sendMessage(
                chatId: $context->chatId,
                text: $this->translator->trans('selector.search_prompt'),
                replyMarkup: (new InlineKeyboardBuilder())
                    ->button($this->translator->trans('common.back'), CallbackActions::FAMILY_CREATE_TIMEZONE_SELECT)
                    ->toArray()
            );
            return $newState;
        }

        // Back to timezone selector
        if ($update->callbackData === CallbackActions::FAMILY_CREATE_TIMEZONE_SELECT) {
            return $this->renderTimezoneSelector(
                $context,
                $data,
                (string) ($data['query'] ?? ''),
                (int) ($data['page'] ?? 0)
            );
        }

        // Clear search
        if ($update->callbackData === CallbackActions::FAMILY_CREATE_TIMEZONE_CLEAR) {
            return $this->renderTimezoneSelector($context, $data);
        }

        // Page navigation
        if (preg_match('/^family:create:timezone:page:(\d+)$/', $update->callbackData ?? '', $matches) === 1) {
            return $this->renderTimezoneSelector($context, $data, (string) ($data['query'] ?? ''), (int) $matches[1]);
        }

        // Timezone selected
        if (str_starts_with($update->callbackData ?? '', CallbackActions::FAMILY_CREATE_TIMEZONE_SET_PREFIX)
            || str_starts_with($update->callbackData ?? '', CallbackActions::FAMILY_CREATE_TZ_PREFIX)
        ) {
            $prefix = str_starts_with($update->callbackData, CallbackActions::FAMILY_CREATE_TIMEZONE_SET_PREFIX)
                ? CallbackActions::FAMILY_CREATE_TIMEZONE_SET_PREFIX
                : CallbackActions::FAMILY_CREATE_TZ_PREFIX;
            $tz = substr($update->callbackData, strlen($prefix));
            if (!$this->timezoneSelector->contains($tz)) {
                return null;
            }

            $data['timezone'] = $tz;
            return new UserInteractionState($userId, 'family_create', 'default_currency', $data);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderTimezoneSelector(TelegramUserContext $context, array $data, string $query = '', int $page = 0): UserInteractionState
    {
        $suggestedTimezone = $context instanceof TelegramFamilyMemberContext
            ? (string) $context->activeFamily->getTimezone()
            : 'UTC';
        $selection = $this->timezoneSelector->build(
            callbackPrefix: 'family:create:timezone',
            selectedValue: $suggestedTimezone,
            query: $query,
            page: $page,
            backLabel: $this->translator->trans('common.cancel'),
            backCallback: CallbackActions::FAMILY_CREATE_CANCEL,
            preferredTimezone: $suggestedTimezone
        );
        $page = $selection['page'];
        $data['query'] = $query;
        $data['page'] = $page;
        unset($data['timezone']);

        $newState = new UserInteractionState(
            $context->telegramUserId,
            'family_create',
            'timezone',
            $data
        );

        $this->telegramClient->sendMessage(
            chatId: $context->chatId,
            text: !$selection['has_results']
                ? $this->translator->trans('selector.no_results')
                : $this->translator->trans('family.create.step_timezone'),
            replyMarkup: $selection['keyboard']
        );

        return $newState;
    }
}

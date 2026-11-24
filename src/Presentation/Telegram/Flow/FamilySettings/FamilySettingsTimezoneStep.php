<?php

namespace App\Presentation\Telegram\Flow\FamilySettings;

use App\Core\Infrastructure\Messenger\MessageBusDispatcherInterface;
use App\Core\Infrastructure\Persistence\Redis\UserInteractionState;
use App\Core\Infrastructure\Persistence\Redis\UserInteractionStateRepository;
use App\Family\Application\Command\UpdateFamilySettingsCommand;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Keyboard\InlineKeyboardBuilder;
use App\Presentation\Telegram\Keyboard\TimezoneSelector;
use Symfony\Contracts\Translation\TranslatorInterface;

final class FamilySettingsTimezoneStep implements FamilySettingsStepInterface
{
    public const string FLOW_TIMEZONE_SELECTOR = 'settings_timezone_selector';

    public function __construct(
        private TelegramApiClientInterface $telegramClient,
        private MessageBusDispatcherInterface $messageBusDispatcher,
        private UserInteractionStateRepository $stateRepository,
        private TimezoneSelector $timezoneSelector,
        private TranslatorInterface $translator
    ) {}

    public function handle(
        TelegramUpdate $update,
        TelegramFamilyMemberContext $context,
        UserInteractionState $state
    ): ?UserInteractionState {
        if ($update->callbackData === CallbackActions::SETTINGS_FAMILY_EDIT_TZ) {
            $this->renderTimezoneSelector($context);
            return null;
        }

        if ($update->callbackData === CallbackActions::SETTINGS_FAMILY_TIMEZONE_SEARCH) {
            $this->showSearchPrompt($context, $state);
            return null;
        }

        if ($update->callbackData === CallbackActions::SETTINGS_FAMILY_TIMEZONE_SELECT) {
            $page = (int) ($state?->data['page'] ?? 0);
            $this->showTimezoneList($context, $state, $page);
            return null;
        }

        if ($update->callbackData === CallbackActions::SETTINGS_FAMILY_TIMEZONE_CLEAR) {
            $this->renderTimezoneSelector($context);
            return null;
        }

        if (preg_match('/^settings:family:timezone:page:(\d+)$/', $update->callbackData ?? '', $matches) === 1) {
            $this->showTimezoneList($context, $state, (int) $matches[1]);
            return null;
        }

        if (
            str_starts_with($update->callbackData ?? '', CallbackActions::SETTINGS_FAMILY_TIMEZONE_SET_PREFIX)
            || str_starts_with($update->callbackData ?? '', CallbackActions::SETTINGS_FAMILY_SET_TZ_PREFIX)
        ) {
            $this->setTimezone($update->callbackData, $context);
            return null;
        }

        if (
            $state->flow === self::FLOW_TIMEZONE_SELECTOR
            && $state->step === 'search'
            && $update->text !== null
            && !str_starts_with($update->text, '/')
        ) {
            $this->renderTimezoneSelector($context, trim($update->text));
            return null;
        }

        return null;
    }

    private function showSearchPrompt(
        TelegramFamilyMemberContext $context,
        ?UserInteractionState $state
    ): void {
        $query = (string) ($state?->data['query'] ?? '');
        $page = (int) ($state?->data['page'] ?? 0);
        $this->stateRepository->set(new UserInteractionState(
            $context->telegramUserId,
            self::FLOW_TIMEZONE_SELECTOR,
            'search',
            ['query' => $query, 'page' => $page]
        ));
        $this->telegramClient->sendMessage(
            chatId: $context->chatId,
            text: $this->translator->trans('selector.search_prompt'),
            replyMarkup: (new InlineKeyboardBuilder())
                ->button($this->translator->trans('common.back'), CallbackActions::SETTINGS_FAMILY_TIMEZONE_SELECT)
                ->toArray()
        );
    }

    private function showTimezoneList(
        TelegramFamilyMemberContext $context,
        ?UserInteractionState $state,
        int $page
    ): void {
        $query = (string) ($state?->data['query'] ?? '');
        $this->renderTimezoneSelector($context, $query, $page);
    }

    private function setTimezone(
        string $callbackData,
        TelegramFamilyMemberContext $context
    ): void {
        $prefix = str_starts_with($callbackData, CallbackActions::SETTINGS_FAMILY_TIMEZONE_SET_PREFIX)
            ? CallbackActions::SETTINGS_FAMILY_TIMEZONE_SET_PREFIX
            : CallbackActions::SETTINGS_FAMILY_SET_TZ_PREFIX;
        $tz = substr($callbackData, strlen($prefix));
        if (!$this->timezoneSelector->contains($tz)) {
            return;
        }

        $this->stateRepository->clear($context->telegramUserId);
        $this->messageBusDispatcher->dispatchCommand(new UpdateFamilySettingsCommand(
            familyId: $context->activeFamily->getId(),
            userId: $context->user->getId(),
            timezone: $tz
        ));

        $this->telegramClient->sendMessage(
            chatId: $context->chatId,
            text: $this->translator->trans('settings.saved')
        );
    }

    private function renderTimezoneSelector(
        TelegramFamilyMemberContext $context,
        string $query = '',
        int $page = 0
    ): void {
        $selection = $this->timezoneSelector->build(
            callbackPrefix: 'settings:family:timezone',
            selectedValue: (string) $context->activeFamily->getTimezone(),
            query: $query,
            page: $page,
            backLabel: $this->translator->trans('common.back'),
            backCallback: CallbackActions::SETTINGS_FAMILY_VIEW,
            preferredTimezone: (string) $context->activeFamily->getTimezone()
        );
        $page = $selection['page'];
        $this->stateRepository->set(new UserInteractionState(
            $context->telegramUserId,
            self::FLOW_TIMEZONE_SELECTOR,
            'select',
            ['query' => $query, 'page' => $page]
        ));

        $this->telegramClient->sendMessage(
            chatId: $context->chatId,
            text: !$selection['has_results']
                ? $this->translator->trans('selector.no_results')
                : $this->translator->trans('settings.prompt_select_timezone'),
            replyMarkup: $selection['keyboard']
        );
    }

}

<?php

namespace App\Presentation\Telegram\Flow\FamilyCreation;

use App\Core\Domain\Exception\InvalidValueException;
use App\Core\Infrastructure\Persistence\Redis\UserInteractionState;
use App\Core\Infrastructure\Persistence\Redis\UserInteractionStateRepository;
use App\Family\Domain\ValueObject\FamilyName;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\MapExceptionToTranslationKey;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Keyboard\InlineKeyboardBuilder;
use App\Presentation\Telegram\Keyboard\TimezoneSelector;
use Symfony\Contracts\Translation\TranslatorInterface;

final class FamilyNameStep implements FamilyCreationStepInterface
{
    public function __construct(
        private TelegramApiClientInterface $telegramClient,
        private UserInteractionStateRepository $stateRepository,
        private TimezoneSelector $timezoneSelector,
        private TranslatorInterface $translator,
        private MapExceptionToTranslationKey $exceptionToTranslation
    ) {}

    public function handle(
        TelegramUpdate $update,
        TelegramUserContext $context,
        UserInteractionState $state
    ): ?UserInteractionState {
        if ($update->text === null || str_starts_with($update->text, '/')) {
            return null;
        }

        $name = trim($update->text);
        try {
            $familyName = FamilyName::fromString($name);
        } catch (InvalidValueException $e) {
            $this->telegramClient->sendMessage(
                chatId: $context->chatId,
                text: $this->translator->trans(($this->exceptionToTranslation)($e))
            );
            return null;
        }

        $data = $state->data;
        $data['name'] = (string) $familyName;

        return new UserInteractionState($context->telegramUserId, 'family_create', 'timezone', $data);
    }

    public function start(TelegramUpdate $update, TelegramUserContext $context): UserInteractionState
    {
        $state = new UserInteractionState($context->telegramUserId, 'family_create', 'name', []);
        $this->stateRepository->set($state);

        $this->telegramClient->sendMessage(
            chatId: $context->chatId,
            text: $this->translator->trans('family.create.step_name'),
            replyMarkup: (new InlineKeyboardBuilder())
                ->button($this->translator->trans('common.cancel'), CallbackActions::FAMILY_CREATE_CANCEL)
                ->toArray()
        );

        return $state;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function renderTimezoneSelector(
        TelegramUserContext $context,
        array $data,
        string $query = '',
        int $page = 0
    ): UserInteractionState {
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

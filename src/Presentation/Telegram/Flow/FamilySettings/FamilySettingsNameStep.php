<?php

namespace App\Presentation\Telegram\Flow\FamilySettings;

use App\Core\Domain\Exception\InvalidValueException;
use App\Core\Infrastructure\Messenger\MessageBusDispatcherInterface;
use App\Core\Infrastructure\Persistence\Redis\UserInteractionState;
use App\Core\Infrastructure\Persistence\Redis\UserInteractionStateRepository;
use App\Family\Application\Command\UpdateFamilySettingsCommand;
use App\Family\Domain\ValueObject\FamilyName;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\MapExceptionToTranslationKey;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Keyboard\InlineKeyboardBuilder;
use Closure;
use Symfony\Contracts\Translation\TranslatorInterface;

final class FamilySettingsNameStep implements FamilySettingsStepInterface
{
    public function __construct(
        private TelegramApiClientInterface $telegramClient,
        private MessageBusDispatcherInterface $messageBusDispatcher,
        private UserInteractionStateRepository $stateRepository,
        private TranslatorInterface $translator,
        private MapExceptionToTranslationKey $exceptionToTranslation,
        private Closure $renderFamilySettings
    ) {}

    public function handle(
        TelegramUpdate $update,
        TelegramFamilyMemberContext $context,
        UserInteractionState $state
    ): ?UserInteractionState {
        if ($update->callbackData === CallbackActions::SETTINGS_FAMILY_EDIT_NAME) {
            $this->startNameInput($context);
            return null;
        }

        if ($update->text !== null && !str_starts_with($update->text, '/')) {
            $this->processNameInput($update->text, $context);
            return null;
        }

        return null;
    }

    private function startNameInput(TelegramFamilyMemberContext $context): void
    {
        $this->telegramClient->sendMessage(
            chatId: $context->chatId,
            text: $this->translator->trans('settings.prompt_enter_name'),
            replyMarkup: (new InlineKeyboardBuilder())
                ->button($this->translator->trans('common.cancel'), CallbackActions::SETTINGS_FAMILY_VIEW)
                ->toArray()
        );
    }

    private function processNameInput(
        string $text,
        TelegramFamilyMemberContext $context
    ): void {
        $newName = trim($text);
        $this->stateRepository->clear($context->telegramUserId);

        try {
            $familyName = FamilyName::fromString($newName);
        } catch (InvalidValueException $e) {
            $this->telegramClient->sendMessage(
                chatId: $context->chatId,
                text: $this->translator->trans(($this->exceptionToTranslation)($e))
            );
            ($this->renderFamilySettings)($context);
            return;
        }

        $this->messageBusDispatcher->dispatchCommand(new UpdateFamilySettingsCommand(
            familyId: $context->activeFamily->getId(),
            userId: $context->user->getId(),
            name: (string) $familyName
        ));

        $this->telegramClient->sendMessage(
            chatId: $context->chatId,
            text: $this->translator->trans('settings.saved')
        );

        ($this->renderFamilySettings)($context);
    }

}

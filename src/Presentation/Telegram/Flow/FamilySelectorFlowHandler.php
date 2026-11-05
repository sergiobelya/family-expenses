<?php

namespace App\Presentation\Telegram\Flow;

use App\Family\Application\Query\GetUserFamiliesHandler;
use App\Family\Application\Query\GetUserFamiliesQuery;
use App\Identity\Application\Command\SwitchActiveFamilyCommand;
use App\Identity\Application\Command\SwitchActiveFamilyHandler;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Keyboard\InlineKeyboardBuilder;
use App\Presentation\Telegram\Keyboard\MainMenuKeyboard;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

class FamilySelectorFlowHandler implements FlowHandlerInterface
{
    public function __construct(
        private TelegramApiClientInterface $telegramClient,
        private GetUserFamiliesHandler $getUserFamiliesHandler,
        private SwitchActiveFamilyHandler $switchActiveFamilyHandler,
        private MainMenuKeyboard $mainMenuKeyboard,
        private TranslatorInterface $translator
    ) {}

    public function supports(TelegramUpdate $update, TelegramUserContext $context): bool
    {
        if ($update->text === '/switch' || $update->text === $this->translator->trans('main_menu.switch_family')) {
            return true;
        }

        if ($update->callbackData === 'family:selector' || str_starts_with($update->callbackData ?? '', 'family:select:')) {
            return true;
        }

        return false;
    }

    public function handle(TelegramUpdate $update, TelegramUserContext $context): void
    {
        if ($update->isCallbackQuery()) {
            $this->telegramClient->answerCallbackQuery($update->callbackQueryId);
        }

        if (str_starts_with($update->callbackData ?? '', 'family:select:')) {
            $familyIdStr = substr($update->callbackData, strlen('family:select:'));
            $familyId = Uuid::fromString($familyIdStr);

            ($this->switchActiveFamilyHandler)(new SwitchActiveFamilyCommand(
                userId: $context->user->getId(),
                activeFamilyId: $familyId
            ));

            $families = ($this->getUserFamiliesHandler)(new GetUserFamiliesQuery($context->user->getId()));
            $selectedFamilyName = 'Family';
            foreach ($families as $f) {
                if ($f->getId()->equals($familyId)) {
                    $selectedFamilyName = $f->getName();
                    break;
                }
            }

            $msg = $this->translator->trans('family.selector.switched', [
                '%{name}' => htmlspecialchars($selectedFamilyName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            ]);

            $this->telegramClient->sendMessage(
                chatId: $context->chatId,
                text: $msg,
                replyMarkup: $this->mainMenuKeyboard->build(count($families) > 1)
            );
            return;
        }

        $families = ($this->getUserFamiliesHandler)(new GetUserFamiliesQuery($context->user->getId()));

        $builder = new InlineKeyboardBuilder();
        $activeFamilyId = $context->user->getActiveFamilyId();

        foreach ($families as $family) {
            $isActive = $activeFamilyId !== null && $activeFamilyId->equals($family->getId());
            $badge = $isActive ? $this->translator->trans('family.selector.current_badge') : '';
            $label = $family->getName() . $badge;

            $builder->button($label, 'family:select:' . $family->getId()->toRfc4122())->row();
        }

        $builder->button($this->translator->trans('family.selector.create_new'), 'family:create:start')->row();
        $builder->button($this->translator->trans('common.main_menu'), 'nav:main_menu')->row();

        $this->telegramClient->sendMessage(
            chatId: $context->chatId,
            text: $this->translator->trans('family.selector.title'),
            replyMarkup: $builder->toArray()
        );
    }
}

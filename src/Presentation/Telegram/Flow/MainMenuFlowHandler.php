<?php

namespace App\Presentation\Telegram\Flow;

use App\Family\Domain\Repository\FamilyMemberRepositoryInterface;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Keyboard\MainMenuKeyboard;
use Symfony\Contracts\Translation\TranslatorInterface;

class MainMenuFlowHandler implements FlowHandlerInterface
{
    public function __construct(
        private TelegramApiClientInterface $telegramClient,
        private MainMenuKeyboard $mainMenuKeyboard,
        private FamilyMemberRepositoryInterface $familyMemberRepository,
        private TranslatorInterface $translator
    ) {}

    public function supports(TelegramUpdate $update, TelegramUserContext $context): bool
    {
        if (!$context->hasActiveFamily()) {
            return false;
        }

        if ($update->text === '/start' || $update->text === '/menu') {
            return true;
        }

        if ($update->callbackData === 'nav:main_menu') {
            return true;
        }

        if ($update->text !== null) {
            $knownButtons = [
                $this->translator->trans('main_menu.add_expense'),
                $this->translator->trans('main_menu.continue_expense'),
                $this->translator->trans('main_menu.reports'),
                $this->translator->trans('main_menu.history'),
                $this->translator->trans('main_menu.stores'),
                $this->translator->trans('main_menu.categories'),
                $this->translator->trans('main_menu.recurring'),
            ];

            return in_array($update->text, $knownButtons, true);
        }

        return false;
    }

    public function handle(TelegramUpdate $update, TelegramUserContext $context): void
    {
        if ($update->isCallbackQuery()) {
            $this->telegramClient->answerCallbackQuery($update->callbackQueryId);
        }

        $activeFamily = $context->activeFamily;
        if ($activeFamily === null) {
            return;
        }

        $memberships = $this->familyMemberRepository->findByUserId($context->user->getId());
        $hasMultipleFamilies = count($memberships) > 1;

        if ($update->text !== null && !in_array($update->text, ['/start', '/menu'], true)) {
            // Placeholder for future phase buttons (Reports, History, Stores, Categories, Recurring, Add Expense)
            $this->telegramClient->sendMessage(
                chatId: $context->chatId,
                text: $this->translator->trans('main_menu.feature_coming_soon'),
                replyMarkup: $this->mainMenuKeyboard->build($hasMultipleFamilies)
            );
            return;
        }

        $welcomeText = $this->translator->trans('main_menu.welcome_text', [
            '%{family_name}' => htmlspecialchars($activeFamily->getName(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        ]);

        $this->telegramClient->sendMessage(
            chatId: $context->chatId,
            text: $welcomeText,
            replyMarkup: $this->mainMenuKeyboard->build($hasMultipleFamilies)
        );
    }
}

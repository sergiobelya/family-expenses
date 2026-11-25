<?php

namespace App\Presentation\Telegram\Flow;

use App\Family\Domain\Repository\FamilyMemberRepositoryInterface;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Flow\Expense\ExpenseFlowUtils;
use App\Presentation\Telegram\Keyboard\MainMenuKeyboard;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class MainMenuFlowHandler implements FamilyContextFlowHandlerInterface
{
    public function __construct(
        private TelegramApiClientInterface $telegramClient,
        private MainMenuKeyboard $mainMenuKeyboard,
        private FamilyMemberRepositoryInterface $familyMemberRepository,
        private TranslatorInterface $translator,
        private ExpenseFlowUtils $expenseFlowUtils,
    ) {}

    public function supports(TelegramUpdate $update, TelegramUserContext $context): bool
    {
        if ($update->text === '/start' || $update->text === '/menu') {
            return true;
        }

        if ($update->callbackData === CallbackActions::NAV_MAIN_MENU) {
            return true;
        }

        if ($update->text !== null) {
            $knownButtons = [
                $this->translator->trans('main_menu.reports'),
                $this->translator->trans('main_menu.history'),
                $this->translator->trans('main_menu.recurring'),
            ];

            return in_array($update->text, $knownButtons, true);
        }

        return false;
    }

    public function handle(TelegramUpdate $update, TelegramFamilyMemberContext $context): void
    {
        if ($update->isCallbackQuery()) {
            $this->telegramClient->answerCallbackQuery($update->callbackQueryId);
        }

        $activeFamily = $context->activeFamily;

        $memberships = $this->familyMemberRepository->findByUserId($context->user->getId());
        $hasMultipleFamilies = count($memberships) > 1;
        $hasActiveDraft = $this->expenseFlowUtils->hasActiveDraft(
            $context->user->getId(),
            $activeFamily->getId()
        );

        if ($update->text !== null && !in_array($update->text, ['/start', '/menu'], true)) {
            // Reports, history, and recurring flows are implemented in later phases.
            $this->telegramClient->sendMessage(
                chatId: $context->chatId,
                text: $this->translator->trans('main_menu.feature_coming_soon'),
                replyMarkup: $this->mainMenuKeyboard->build($hasMultipleFamilies, $hasActiveDraft)
            );
            return;
        }

        $welcomeText = $this->translator->trans('main_menu.welcome_text', [
            '%{family_name}' => htmlspecialchars($activeFamily->getName(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        ]);

        $this->telegramClient->sendMessage(
            chatId: $context->chatId,
            text: $welcomeText,
            replyMarkup: $this->mainMenuKeyboard->build($hasMultipleFamilies, $hasActiveDraft)
        );
    }
}

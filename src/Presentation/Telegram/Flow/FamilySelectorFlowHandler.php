<?php

namespace App\Presentation\Telegram\Flow;

use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Infrastructure\Messenger\MessageBusDispatcherInterface;
use App\Family\Application\Query\GetUserFamiliesQuery;
use App\Identity\Application\Command\SwitchActiveFamilyCommand;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Flow\Expense\ExpenseDraftEntry;
use App\Presentation\Telegram\Flow\Expense\ExpenseEntryIntent;
use App\Presentation\Telegram\Flow\Expense\ExpenseFlowUtils;
use App\Presentation\Telegram\Keyboard\InlineKeyboardBuilder;
use App\Presentation\Telegram\Keyboard\MainMenuKeyboard;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class FamilySelectorFlowHandler implements UserContextFlowHandlerInterface
{
    public function __construct(
        private TelegramApiClientInterface $telegramClient,
        private MessageBusDispatcherInterface $messageBusDispatcher,
        private MainMenuKeyboard $mainMenuKeyboard,
        private TranslatorInterface $translator,
        private ExpenseFlowUtils $expenseFlowUtils,
        private ExpenseDraftEntry $expenseDraftEntry,
    ) {}

    public function supports(TelegramUpdate $update, TelegramUserContext $context): bool
    {
        if ($update->text === '/switch' || $update->text === $this->translator->trans('main_menu.switch_family')) {
            return true;
        }

        if ($update->text === '/add' && !($context instanceof TelegramFamilyMemberContext)) {
            return true;
        }

        if (
            $update->callbackData === CallbackActions::FAMILY_SELECTOR
            || str_starts_with($update->callbackData ?? '', CallbackActions::FAMILY_SELECT_PREFIX)
            || str_starts_with($update->callbackData ?? '', CallbackActions::FAMILY_SELECT_FOR_EXPENSE_PREFIX)
            || str_starts_with($update->callbackData ?? '', CallbackActions::FAMILY_SELECT_FOR_EXPENSE_ADD_PREFIX)
            || str_starts_with($update->callbackData ?? '', CallbackActions::FAMILY_SELECT_FOR_EXPENSE_CONTINUE_PREFIX)
        ) {
            return true;
        }

        if (
            !($context instanceof TelegramFamilyMemberContext)
            && $update->text !== null
            && $this->mainMenuKeyboard->isFamilyScopedButton($update->text)
        ) {
            return true;
        }

        return false;
    }

    public function handle(TelegramUpdate $update, TelegramUserContext $context): void
    {
        if ($update->isCallbackQuery()) {
            $this->telegramClient->answerCallbackQuery($update->callbackQueryId);
        }

        if ($this->handleFamilySelectionCallback($update->callbackData ?? '', $context)) {
            return;
        }

        $this->showFamilySelector($update, $context);
    }

    private function handleFamilySelectionCallback(string $callbackData, TelegramUserContext $context): bool
    {
        $callbackFamilySelectPrefix = array_find([
            CallbackActions::FAMILY_SELECT_FOR_EXPENSE_CONTINUE_PREFIX,
            CallbackActions::FAMILY_SELECT_FOR_EXPENSE_ADD_PREFIX,
            CallbackActions::FAMILY_SELECT_FOR_EXPENSE_PREFIX,
            CallbackActions::FAMILY_SELECT_PREFIX,
        ], fn($prefix) => str_starts_with($callbackData, $prefix));

        if ($callbackFamilySelectPrefix === null) {
            return false;
        }

        $expenseIntent = match ($callbackFamilySelectPrefix) {
            CallbackActions::FAMILY_SELECT_FOR_EXPENSE_CONTINUE_PREFIX => ExpenseEntryIntent::CONTINUE,
            CallbackActions::FAMILY_SELECT_FOR_EXPENSE_ADD_PREFIX,
            CallbackActions::FAMILY_SELECT_FOR_EXPENSE_PREFIX => ExpenseEntryIntent::ADD,
            default => null,
        };

        $this->handleFamilySelection(
            FamilyId::fromString(substr($callbackData, strlen($callbackFamilySelectPrefix))),
            $context,
            $expenseIntent
        );

        return true;
    }

    private function showFamilySelector(TelegramUpdate $update, TelegramUserContext $context): void
    {
        $families = $this->messageBusDispatcher->dispatchQuery(new GetUserFamiliesQuery($context->user->getId()));
        if ($families === []) {
            $this->showFamilyOnboardingPrompt($context);
            return;
        }

        $activeFamilyId = $context->user->getActiveFamilyId();
        $expenseIntent = $this->entryIntent($update->text);
        $familySelectPrefix = match ($expenseIntent) {
            ExpenseEntryIntent::ADD => CallbackActions::FAMILY_SELECT_FOR_EXPENSE_ADD_PREFIX,
            ExpenseEntryIntent::CONTINUE => CallbackActions::FAMILY_SELECT_FOR_EXPENSE_CONTINUE_PREFIX,
            null => CallbackActions::FAMILY_SELECT_PREFIX,
        };

        $builder = new InlineKeyboardBuilder();
        foreach ($families as $family) {
            $isActive = $activeFamilyId !== null && $activeFamilyId->equals($family->getId());
            $badge = $isActive ? $this->translator->trans('family.selector.current_badge') : '';
            $label = (string) $family->getName() . $badge;

            $builder->button($label, $familySelectPrefix . $family->getId()->toRfc4122())->row();
        }

        $builder->button($this->translator->trans('family.selector.create_new'), CallbackActions::FAMILY_CREATE_START)->row();
        $builder->button($this->translator->trans('common.main_menu'), CallbackActions::NAV_MAIN_MENU)->row();

        $this->telegramClient->sendMessage(
            chatId: $context->chatId,
            text: $this->translator->trans('family.selector.title'),
            replyMarkup: $builder->toArray()
        );
    }

    private function showFamilyOnboardingPrompt(TelegramUserContext $context): void
    {
        $builder = new InlineKeyboardBuilder()
            ->button(
                $this->translator->trans('family.onboarding.create_family_btn'),
                CallbackActions::FAMILY_CREATE_START
            )
            ->toArray();

        $this->telegramClient->sendMessage(
            chatId: $context->chatId,
            text: $this->translator->trans('family.onboarding.title') . "\n\n" . $this->translator->trans('family.onboarding.text'),
            replyMarkup: $builder
        );
    }

    private function handleFamilySelection(
        FamilyId $familyId,
        TelegramUserContext $context,
        ?ExpenseEntryIntent $expenseIntent
    ): void {
        $this->messageBusDispatcher->dispatchCommand(new SwitchActiveFamilyCommand(
            userId: $context->user->getId(),
            activeFamilyId: $familyId
        ));

        $families = $this->messageBusDispatcher->dispatchQuery(new GetUserFamiliesQuery($context->user->getId()));
        $selectedFamilyName = '';
        $selectedFamily = null;
        foreach ($families as $family) {
            if ($family->getId()->equals($familyId)) {
                $selectedFamily = $family;
                $selectedFamilyName = (string) $family->getName();
                break;
            }
        }

        $message = $this->translator->trans('family.selector.switched', [
            '%{name}' => htmlspecialchars($selectedFamilyName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        ]);

        $this->telegramClient->sendMessage(
            chatId: $context->chatId,
            text: $message,
            replyMarkup: $this->mainMenuKeyboard->build(
                count($families) > 1,
                $this->expenseFlowUtils->hasActiveDraft($context->user->getId(), $familyId)
            )
        );

        if ($expenseIntent !== null && $selectedFamily !== null) {
            $this->expenseDraftEntry->startOrResume(
                new TelegramFamilyMemberContext($context, $selectedFamily),
                $expenseIntent
            );
        }
    }

    private function entryIntent(?string $text): ?ExpenseEntryIntent
    {
        if ($text === $this->translator->trans('main_menu.continue_expense')) {
            return ExpenseEntryIntent::CONTINUE;
        }

        if ($text === '/add' || $text === $this->translator->trans('main_menu.add_expense')) {
            return ExpenseEntryIntent::ADD;
        }

        return null;
    }
}

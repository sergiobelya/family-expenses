<?php

namespace App\Tests\Unit\Presentation\Telegram\Flow\Expense;

use App\Core\Domain\ValueObject\CurrencyCode;
use App\Core\Domain\ValueObject\Timezone;
use App\Core\Infrastructure\Messenger\MessageBusDispatcherInterface;
use App\Expense\Application\ExpenseAuthorizationService;
use App\Expense\Application\ExpenseDraftService;
use App\Expense\Domain\ExpenseDraft;
use App\Expense\Domain\ExpenseDraftStatus;
use App\Expense\Domain\Repository\ExpenseDraftRepositoryInterface;
use App\Family\Domain\Entity\Family;
use App\Identity\Domain\Entity\User;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Flow\Expense\AddExpenseFlowHandler;
use App\Presentation\Telegram\Flow\Expense\AddExpenseStepInterface;
use App\Presentation\Telegram\Flow\Expense\AddExpenseStepRouter;
use App\Presentation\Telegram\Flow\Expense\ExpenseCardRenderer;
use App\Presentation\Telegram\Flow\Expense\ExpenseDraftCallbackData;
use App\Presentation\Telegram\Flow\Expense\ExpenseDraftCard;
use App\Presentation\Telegram\Flow\Expense\ExpenseDraftEntry;
use App\Presentation\Telegram\Flow\Expense\ExpenseDraftScreen;
use App\Presentation\Telegram\Flow\Expense\MoneyAmountFormatter;
use App\Presentation\Telegram\Keyboard\MainMenuKeyboard;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AddExpenseFlowHandlerTest extends TestCase
{
    public function testSupportsAddCommandAndExpenseMenuLabels(): void
    {
        $translator = $this->createTranslator();
        $repository = $this->createMock(ExpenseDraftRepositoryInterface::class);
        $telegram = $this->createMock(TelegramApiClientInterface::class);
        $handler = $this->createHandler($repository, $telegram, $translator);
        $context = $this->createContext();

        self::assertTrue($handler->supports($this->message('/add'), $context));
        self::assertTrue($handler->supports($this->message('main_menu.add_expense'), $context));
        self::assertTrue($handler->supports($this->message('main_menu.continue_expense'), $context));
    }

    public function testStartsAndPersistsANewDraftWithFamilyLocalDate(): void
    {
        $context = $this->createContext('Europe/Warsaw');
        $savedDraft = null;
        $initialDraftState = null;
        $repository = $this->createMock(ExpenseDraftRepositoryInterface::class);
        $repository->method('find')->willReturn(null);
        $repository->expects(self::atLeastOnce())
            ->method('save')
            ->willReturnCallback(static function (ExpenseDraft $draft) use (&$savedDraft, &$initialDraftState): void {
                $initialDraftState ??= [
                    'step' => $draft->currentStep,
                    'purchase_date' => $draft->purchaseDate,
                ];
                $savedDraft = $draft;
            });
        $telegram = $this->createMock(TelegramApiClientInterface::class);
        $telegram->expects(self::once())
            ->method('sendMessage')
            ->with(111, self::stringContains('expense.add.store.prompt'), null)
            ->willReturn(['result' => ['message_id' => 51]]);

        $handler = $this->createHandler($repository, $telegram, $this->createTranslator());
        $handler->handle($this->message('/add'), $context);

        self::assertInstanceOf(ExpenseDraft::class, $savedDraft);
        self::assertIsArray($initialDraftState);
        self::assertSame('store', $initialDraftState['step']);
        self::assertNotNull($initialDraftState['purchase_date']);
        self::assertNotNull($savedDraft->purchaseDate);
        self::assertSame(ExpenseDraftStatus::ACTIVE, $savedDraft->status);
        self::assertSame('store', $savedDraft->currentStep);
        self::assertSame('00:00:00', $savedDraft->purchaseDate->format('H:i:s'));
        self::assertSame('Europe/Warsaw', $savedDraft->purchaseDate->getTimezone()->getName());
        self::assertSame(51, $savedDraft->cardMessageId);
    }

    public function testStaleContinueLabelOffersAnExplicitRestart(): void
    {
        $repository = $this->createMock(ExpenseDraftRepositoryInterface::class);
        $repository->method('find')->willReturn(null);
        $telegram = $this->createMock(TelegramApiClientInterface::class);
        $markup = null;
        $telegram->expects(self::once())
            ->method('sendMessage')
            ->with(111, 'expense.add.expired.message', self::callback(static function (?array $keyboard) use (&$markup): bool {
                $markup = $keyboard;
                return is_array($keyboard);
            }))
            ->willReturn(['result' => ['message_id' => 51]]);

        $handler = $this->createHandler($repository, $telegram, $this->createTranslator());
        $handler->handle($this->message('main_menu.continue_expense'), $this->createContext());

        self::assertIsArray($markup);
        $buttons = array_merge(...$markup['inline_keyboard']);
        self::assertSame('expense.add.expired.restart', $buttons[0]['text']);
        self::assertSame('expense.add.expired.close', $buttons[1]['text']);
        self::assertStringStartsWith(CallbackActions::EXPENSE_TOKEN_PREFIX, $buttons[0]['callback_data']);
    }

    public function testContinueWithExpiredDraftRemovesItAndShowsExpiredScreen(): void
    {
        $context = $this->createContext();
        $draft = ExpenseDraft::createNew(
            $context->user->getId(),
            $context->activeFamily->getId(),
            $context->telegramUserId,
            $context->chatId,
            new \DateTimeImmutable('-2 days')
        );

        $repository = $this->createMock(ExpenseDraftRepositoryInterface::class);
        $repository->method('find')->willReturn($draft);
        $repository->expects(self::once())
            ->method('remove')
            ->with($context->user->getId(), $context->activeFamily->getId());
        $repository->expects(self::never())->method('save');

        $telegram = $this->createMock(TelegramApiClientInterface::class);
        $markup = null;
        $telegram->expects(self::once())
            ->method('sendMessage')
            ->with(111, 'expense.add.expired.message', self::callback(static function (?array $keyboard) use (&$markup): bool {
                $markup = $keyboard;
                return is_array($keyboard);
            }))
            ->willReturn(['result' => ['message_id' => 51]]);

        $handler = $this->createHandler($repository, $telegram, $this->createTranslator());
        $handler->handle($this->message('main_menu.continue_expense'), $context);

        self::assertTrue($draft->isExpired());
        self::assertIsArray($markup);
        $buttons = array_merge(...$markup['inline_keyboard']);
        self::assertSame('expense.add.expired.restart', $buttons[0]['text']);
        self::assertSame('expense.add.expired.close', $buttons[1]['text']);
    }

    public function testContinueWithTerminalDraftOffersRestartInsteadOfCreatingANewDraft(): void
    {
        foreach ([ExpenseDraftStatus::SAVED, ExpenseDraftStatus::DISCARDED] as $status) {
            $context = $this->createContext();
            $draft = ExpenseDraft::createNew(
                $context->user->getId(),
                $context->activeFamily->getId(),
                $context->telegramUserId,
                $context->chatId,
                new \DateTimeImmutable()
            );
            $draft->setStatus($status);

            $repository = $this->createMock(ExpenseDraftRepositoryInterface::class);
            $repository->method('find')->willReturn($draft);
            $repository->expects(self::never())->method('save');

            $telegram = $this->createMock(TelegramApiClientInterface::class);
            $telegram->expects(self::once())
                ->method('sendMessage')
                ->with(111, 'expense.add.callback.stale', self::isType('array'))
                ->willReturn(['result' => ['message_id' => 51]]);

            $handler = $this->createHandler($repository, $telegram, $this->createTranslator());
            $handler->handle($this->message('main_menu.continue_expense'), $context);

            self::assertSame($status, $draft->status);
        }
    }

    public function testTerminalDraftRestartButtonExplicitlyStartsANewDraft(): void
    {
        $context = $this->createContext();
        $terminalDraft = ExpenseDraft::createNew(
            $context->user->getId(),
            $context->activeFamily->getId(),
            $context->telegramUserId,
            $context->chatId,
            new \DateTimeImmutable()
        );
        $terminalDraft->setStatus(ExpenseDraftStatus::SAVED);

        $savedDraft = null;
        $repository = $this->createMock(ExpenseDraftRepositoryInterface::class);
        $repository->method('find')->willReturn($terminalDraft);
        $repository->expects(self::atLeastOnce())
            ->method('save')
            ->willReturnCallback(static function (ExpenseDraft $draft) use (&$savedDraft): void {
                $savedDraft = $draft;
            });

        $restartCallback = null;
        $telegram = $this->createMock(TelegramApiClientInterface::class);
        $telegram->expects(self::once())
            ->method('sendMessage')
            ->with(111, 'expense.add.callback.stale', self::callback(static function (array $markup) use (&$restartCallback): bool {
                $buttons = array_merge(...$markup['inline_keyboard']);
                $restartCallback = $buttons[0]['callback_data'];

                return true;
            }))
            ->willReturn(['result' => ['message_id' => 51]]);
        $telegram->expects(self::once())
            ->method('answerCallbackQuery')
            ->with('callback-id');
        $telegram->expects(self::once())
            ->method('editMessageText')
            ->with(111, 51, self::stringContains('expense.add.store.prompt'), ['inline_keyboard' => []])
            ->willReturn(['result' => ['message_id' => 51]]);

        $handler = $this->createHandler($repository, $telegram, $this->createTranslator());
        $handler->handle($this->message('main_menu.continue_expense'), $context);
        self::assertNotNull($restartCallback);
        $handler->handle($this->callbackUpdate($restartCallback, 51), $context);

        self::assertInstanceOf(ExpenseDraft::class, $savedDraft);
        self::assertSame(ExpenseDraftStatus::ACTIVE, $savedDraft->status);
        self::assertNotSame($terminalDraft->idempotencyKey, $savedDraft->idempotencyKey);
    }

    public function testContinueResumesPausedDraftAndMovesTheCard(): void
    {
        $context = $this->createContext();
        $draft = ExpenseDraft::createNew(
            $context->user->getId(),
            $context->activeFamily->getId(),
            $context->telegramUserId,
            $context->chatId,
            new \DateTimeImmutable()
        );
        $draft->setStep('store');
        $draft->setCardMessage(40);
        $draft->setStatus(ExpenseDraftStatus::PAUSED);

        $repository = $this->createMock(ExpenseDraftRepositoryInterface::class);
        $repository->method('find')->willReturn($draft);
        $repository->expects(self::atLeastOnce())->method('save');
        $telegram = $this->createMock(TelegramApiClientInterface::class);
        $telegram->expects(self::once())
            ->method('sendMessage')
            ->with(111, self::stringContains('expense.add.store.prompt'), null)
            ->willReturn(['result' => ['message_id' => 42]]);
        $telegram->expects(self::once())
            ->method('editMessageText')
            ->with(111, 40, 'expense.add.card.moved', ['inline_keyboard' => []]);

        $handler = $this->createHandler($repository, $telegram, $this->createTranslator());
        $handler->handle($this->message('main_menu.continue_expense'), $context);

        self::assertSame(ExpenseDraftStatus::ACTIVE, $draft->status);
        self::assertSame(42, $draft->cardMessageId);
    }

    public function testContinueReanchorsDraftUsingTheRendererForItsSavedStep(): void
    {
        $context = $this->createContext();
        $draft = ExpenseDraft::createNew(
            $context->user->getId(),
            $context->activeFamily->getId(),
            $context->telegramUserId,
            $context->chatId,
            new \DateTimeImmutable()
        );
        $draft->setStep('amount');
        $draft->setCardMessage(40);

        $repository = $this->createMock(ExpenseDraftRepositoryInterface::class);
        $repository->method('find')->willReturn($draft);
        $repository->expects(self::once())->method('save')->with($draft);

        $step = new class implements AddExpenseStepInterface {
            public bool $rendered = false;

            public function supports(ExpenseDraft $draft): bool
            {
                return $draft->currentStep === 'amount';
            }

            public function handle(TelegramUpdate $update, TelegramFamilyMemberContext $context, ExpenseDraft $draft): ExpenseDraft
            {
                return $draft;
            }

            public function render(TelegramFamilyMemberContext $context, ExpenseDraft $draft, ?int $messageId, ?string $errorKey = null): int
            {
                $this->rendered = true;

                return 42;
            }
        };

        $telegram = $this->createMock(TelegramApiClientInterface::class);
        $telegram->expects(self::never())->method('sendMessage');
        $telegram->expects(self::once())
            ->method('editMessageText')
            ->with(111, 40, 'expense.add.card.moved', ['inline_keyboard' => []]);

        $handler = $this->createHandler($repository, $telegram, $this->createTranslator(), [$step]);
        $handler->handle($this->message('main_menu.continue_expense'), $context);

        self::assertTrue($step->rendered);
        self::assertSame(42, $draft->cardMessageId);
    }

    public function testStaleCallbackOnPausedDraftDoesNotShowItAsExpired(): void
    {
        $context = $this->createContext();
        $draft = ExpenseDraft::createNew(
            $context->user->getId(),
            $context->activeFamily->getId(),
            $context->telegramUserId,
            $context->chatId,
            new \DateTimeImmutable()
        );
        $draft->setStep('store');
        $draft->setCardMessage(41);
        $draft->setStatus(ExpenseDraftStatus::PAUSED);

        $repository = $this->createMock(ExpenseDraftRepositoryInterface::class);
        $repository->method('find')->willReturn($draft);
        $repository->expects(self::never())->method('save');

        $telegram = $this->createMock(TelegramApiClientInterface::class);
        $telegram->expects(self::once())
            ->method('answerCallbackQuery')
            ->with('callback-id', 'expense.add.callback.stale');
        $telegram->expects(self::never())->method('sendMessage');
        $telegram->expects(self::never())->method('editMessageText');

        $handler = $this->createHandler($repository, $telegram, $this->createTranslator());
        $handler->handle($this->callbackUpdate('exp:t:wrong-token:0:store'), $context);

        self::assertSame(ExpenseDraftStatus::PAUSED, $draft->status);
    }

    public function testStaleCallbackIsAnsweredAndReanchorsTheCurrentDraft(): void
    {
        $context = $this->createContext();
        $draft = ExpenseDraft::createNew(
            $context->user->getId(),
            $context->activeFamily->getId(),
            $context->telegramUserId,
            $context->chatId,
            new \DateTimeImmutable()
        );
        $draft->setStep('store');
        $draft->setCardMessage(41);

        $repository = $this->createMock(ExpenseDraftRepositoryInterface::class);
        $repository->method('find')->willReturn($draft);
        $repository->expects(self::atLeastOnce())->method('save');
        $telegram = $this->createMock(TelegramApiClientInterface::class);
        $telegram->expects(self::once())
            ->method('answerCallbackQuery')
            ->with('callback-id', 'expense.add.callback.stale');
        $telegram->expects(self::once())
            ->method('sendMessage')
            ->with(111, self::stringContains('expense.add.store.prompt'), null)
            ->willReturn(['result' => ['message_id' => 42]]);
        $telegram->expects(self::once())
            ->method('editMessageText')
            ->with(111, 41, 'expense.add.card.moved', ['inline_keyboard' => []]);

        $handler = $this->createHandler($repository, $telegram, $this->createTranslator());
        $handler->handle($this->callbackUpdate('exp:t:wrong-token:0:store'), $context);

        self::assertSame(42, $draft->cardMessageId);
    }

    public function testUnexpectedTextGetsShowCardButtonWithoutChangingTheDraft(): void
    {
        $context = $this->createContext();
        $draft = ExpenseDraft::createNew(
            $context->user->getId(),
            $context->activeFamily->getId(),
            $context->telegramUserId,
            $context->chatId,
            new \DateTimeImmutable()
        );
        $draft->setStep('store');

        $repository = $this->createMock(ExpenseDraftRepositoryInterface::class);
        $repository->method('find')->willReturn($draft);
        $repository->expects(self::never())->method('save');
        $telegram = $this->createMock(TelegramApiClientInterface::class);
        $markup = null;
        $telegram->expects(self::once())
            ->method('sendMessage')
            ->with(111, 'expense.add.input.unexpected', self::callback(static function (?array $keyboard) use (&$markup): bool {
                $markup = $keyboard;
                return is_array($keyboard);
            }));

        $handler = $this->createHandler($repository, $telegram, $this->createTranslator());
        $handler->handle($this->message('hello'), $context);

        self::assertIsArray($markup);
        $button = $markup['inline_keyboard'][0][0];
        self::assertSame('expense.add.input.show_card', $button['text']);
        self::assertStringContainsString(':' . $draft->cardRevision . ':', $button['callback_data']);
    }

    public function testActiveDraftUpdateIsDelegatedToMatchingStep(): void
    {
        $context = $this->createContext();
        $draft = ExpenseDraft::createNew(
            $context->user->getId(),
            $context->activeFamily->getId(),
            $context->telegramUserId,
            $context->chatId,
            new \DateTimeImmutable()
        );
        $draft->setStep('store');

        $repository = $this->createMock(ExpenseDraftRepositoryInterface::class);
        $repository->method('find')->willReturn($draft);
        $repository->expects(self::once())->method('save')->with($draft);

        $telegram = $this->createMock(TelegramApiClientInterface::class);
        $telegram->expects(self::never())->method('sendMessage');

        $update = $this->message('input for the active step');
        $step = $this->createMock(AddExpenseStepInterface::class);
        $step->expects(self::once())->method('supports')->with($draft)->willReturn(true);
        $step->expects(self::once())
            ->method('handle')
            ->with($update, $context, $draft)
            ->willReturn($draft);

        $handler = $this->createHandler($repository, $telegram, $this->createTranslator(), [$step]);
        $handler->handle($update, $context);
    }

    /**
     * @param list<AddExpenseStepInterface> $steps
     */
    private function createHandler(
        ExpenseDraftRepositoryInterface $repository,
        TelegramApiClientInterface $telegram,
        TranslatorInterface $translator,
        array $steps = []
    ): AddExpenseFlowHandler {
        $callbackData = new ExpenseDraftCallbackData();
        $draftService = new ExpenseDraftService($repository, $this->createMock(ExpenseAuthorizationService::class));
        $screen = new ExpenseDraftScreen(
            new ExpenseCardRenderer($telegram, $translator, new MoneyAmountFormatter()),
            $callbackData,
            $telegram,
            $translator,
            $this->createMock(MessageBusDispatcherInterface::class),
            new MoneyAmountFormatter()
        );
        $stepRouter = new AddExpenseStepRouter($steps);
        $draftCard = new ExpenseDraftCard($screen, $stepRouter, $draftService);

        return new AddExpenseFlowHandler(
            new ExpenseDraftEntry($draftService, $screen, $draftCard),
            $draftCard,
            $screen,
            $callbackData,
            $stepRouter,
            $telegram,
            $draftService,
            new MainMenuKeyboard($translator)
        );
    }

    private function createContext(string $timezone = 'UTC'): TelegramFamilyMemberContext
    {
        $user = new User(123456);
        $family = Family::create(
            'Smiths',
            Timezone::fromString($timezone),
            CurrencyCode::fromString('PLN'),
            CurrencyCode::fromString('PLN')
        );

        return new TelegramFamilyMemberContext(new TelegramUserContext($user, 'en', 123456, 111), $family);
    }

    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key): string => $key);

        return $translator;
    }

    private function message(string $text): TelegramUpdate
    {
        return TelegramUpdate::fromArray([
            'update_id' => 1,
            'message' => ['message_id' => 10, 'chat' => ['id' => 111], 'text' => $text],
        ]);
    }

    private function callbackUpdate(string $data, int $messageId = 41): TelegramUpdate
    {
        return TelegramUpdate::fromArray([
            'update_id' => 2,
            'callback_query' => [
                'id' => 'callback-id',
                'data' => $data,
                'message' => ['message_id' => $messageId, 'chat' => ['id' => 111]],
            ],
        ]);
    }
}

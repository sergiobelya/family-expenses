<?php

namespace App\Tests\Unit\Presentation\Telegram\Flow;

use App\Core\Domain\ValueObject\CurrencyCode;
use App\Core\Domain\ValueObject\Timezone;
use App\Core\Infrastructure\Messenger\MessageBusDispatcherInterface;
use App\Expense\Application\ExpenseAuthorizationService;
use App\Expense\Application\ExpenseDraftService;
use App\Expense\Domain\Repository\ExpenseDraftRepositoryInterface;
use App\Family\Application\Query\GetUserFamiliesQuery;
use App\Family\Domain\Entity\Family;
use App\Identity\Application\Command\SwitchActiveFamilyCommand;
use App\Identity\Domain\Entity\User;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Flow\Expense\AddExpenseStepRouter;
use App\Presentation\Telegram\Flow\Expense\ExpenseCardRenderer;
use App\Presentation\Telegram\Flow\Expense\ExpenseDraftCallbackData;
use App\Presentation\Telegram\Flow\Expense\ExpenseDraftCard;
use App\Presentation\Telegram\Flow\Expense\ExpenseDraftEntry;
use App\Presentation\Telegram\Flow\Expense\ExpenseDraftScreen;
use App\Presentation\Telegram\Flow\Expense\MoneyAmountFormatter;
use App\Presentation\Telegram\Flow\FamilySelectorFlowHandler;
use App\Presentation\Telegram\Keyboard\MainMenuKeyboard;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class FamilySelectorFlowHandlerTest extends TestCase
{
    public function testAddCommandContinuesExpenseFlowAfterFamilySelection(): void
    {
        $family = Family::create('Smiths', new Timezone('UTC'), new CurrencyCode('USD'), new CurrencyCode('USD'));
        $user = new User(123456);
        $context = new TelegramUserContext($user, 'en', 123456, 111);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key): string => $key);

        $telegramClient = $this->createMock(TelegramApiClientInterface::class);
        $telegramClient->expects(self::once())->method('answerCallbackQuery')->with('family-callback');
        $messages = [];
        $telegramClient->method('sendMessage')->willReturnCallback(
            static function ($chatId, $text, $replyMarkup) use (&$messages): array {
                $messages[] = ['text' => $text, 'reply_markup' => $replyMarkup];

                return ['result' => ['message_id' => 40 + count($messages)]];
            }
        );

        $messageBus = $this->createMock(MessageBusDispatcherInterface::class);
        $messageBus->expects(self::once())
            ->method('dispatchCommand')
            ->with(self::isInstanceOf(SwitchActiveFamilyCommand::class));
        $messageBus->expects(self::exactly(2))
            ->method('dispatchQuery')
            ->with(self::isInstanceOf(GetUserFamiliesQuery::class))
            ->willReturn([$family]);

        $draftRepository = $this->createMock(ExpenseDraftRepositoryInterface::class);
        $draftRepository->method('find')->willReturn(null);
        $draftRepository->expects(self::atLeastOnce())->method('save');
        $draftService = $this->createDraftService($draftRepository);

        $mainMenuKeyboard = new MainMenuKeyboard($translator);
        $expenseDraftEntry = $this->createExpenseDraftEntry(
            $telegramClient,
            $draftService,
            $translator
        );
        $handler = new FamilySelectorFlowHandler(
            $telegramClient,
            $messageBus,
            $mainMenuKeyboard,
            $translator,
            $draftService,
            $expenseDraftEntry,
        );

        $handler->handle($this->message('/add'), $context);
        $callbackData = $messages[0]['reply_markup']['inline_keyboard'][0][0]['callback_data'];
        self::assertLessThanOrEqual(64, strlen($callbackData));
        $handler->handle($this->callbackUpdate($callbackData), $context);

        self::assertCount(3, $messages);
        self::assertStringContainsString('expense.add.card_header', $messages[2]['text']);
    }

    public function testContinueActionRemainsContinueAfterFamilySelection(): void
    {
        $family = Family::create('Smiths', new Timezone('UTC'), new CurrencyCode('USD'), new CurrencyCode('USD'));
        $user = new User(123456);
        $context = new TelegramUserContext($user, 'en', 123456, 111);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key): string => $key);

        $telegramClient = $this->createMock(TelegramApiClientInterface::class);
        $telegramClient->expects(self::once())->method('answerCallbackQuery')->with('family-callback');
        $messages = [];
        $telegramClient->method('sendMessage')->willReturnCallback(
            static function ($chatId, $text, $replyMarkup) use (&$messages): array {
                $messages[] = ['text' => $text, 'reply_markup' => $replyMarkup];

                return ['result' => ['message_id' => 40 + count($messages)]];
            }
        );

        $messageBus = $this->createMock(MessageBusDispatcherInterface::class);
        $messageBus->expects(self::once())
            ->method('dispatchCommand')
            ->with(self::isInstanceOf(SwitchActiveFamilyCommand::class));
        $messageBus->expects(self::exactly(2))
            ->method('dispatchQuery')
            ->with(self::isInstanceOf(GetUserFamiliesQuery::class))
            ->willReturn([$family]);

        $draftRepository = $this->createMock(ExpenseDraftRepositoryInterface::class);
        $draftRepository->method('find')->willReturn(null);
        $draftRepository->expects(self::never())->method('save');
        $draftService = $this->createDraftService($draftRepository);

        $mainMenuKeyboard = new MainMenuKeyboard($translator);
        $expenseDraftEntry = $this->createExpenseDraftEntry(
            $telegramClient,
            $draftService,
            $translator
        );
        $handler = new FamilySelectorFlowHandler(
            $telegramClient,
            $messageBus,
            $mainMenuKeyboard,
            $translator,
            $draftService,
            $expenseDraftEntry,
        );

        $handler->handle($this->message('main_menu.continue_expense'), $context);
        $callbackData = $messages[0]['reply_markup']['inline_keyboard'][0][0]['callback_data'];
        self::assertLessThanOrEqual(64, strlen($callbackData));
        $handler->handle($this->callbackUpdate($callbackData), $context);

        self::assertCount(3, $messages);
        self::assertStringContainsString('expense.add.expired.message', $messages[2]['text']);
    }

    private function message(string $text): TelegramUpdate
    {
        return TelegramUpdate::fromArray([
            'update_id' => 1,
            'message' => ['message_id' => 10, 'chat' => ['id' => 111], 'text' => $text],
        ]);
    }

    private function createExpenseDraftEntry(
        TelegramApiClientInterface $telegramClient,
        ExpenseDraftService $draftService,
        TranslatorInterface $translator
    ): ExpenseDraftEntry {
        $callbackData = new ExpenseDraftCallbackData();
        $screen = new ExpenseDraftScreen(
            new ExpenseCardRenderer($telegramClient, $translator, new MoneyAmountFormatter()),
            $callbackData,
            $telegramClient,
            $translator,
            $this->createMock(MessageBusDispatcherInterface::class)
        );
        $stepRouter = new AddExpenseStepRouter([]);
        $draftCard = new ExpenseDraftCard($screen, $stepRouter, $draftService);

        return new ExpenseDraftEntry($draftService, $screen, $draftCard);
    }

    private function createDraftService(ExpenseDraftRepositoryInterface $draftRepository): ExpenseDraftService
    {
        return new ExpenseDraftService(
            $draftRepository,
            $this->createMock(ExpenseAuthorizationService::class)
        );
    }

    private function callbackUpdate(string $data): TelegramUpdate
    {
        return TelegramUpdate::fromArray([
            'update_id' => 2,
            'callback_query' => [
                'id' => 'family-callback',
                'data' => $data,
                'message' => ['message_id' => 41, 'chat' => ['id' => 111]],
            ],
        ]);
    }
}

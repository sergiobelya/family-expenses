<?php

namespace App\Tests\Unit\Presentation\Telegram\Flow\Expense;

use App\Core\Application\CurrencyCatalog;
use App\Core\Domain\ValueObject\CurrencyCode;
use App\Core\Domain\ValueObject\Timezone;
use App\Core\Infrastructure\Messenger\MessageBusDispatcherInterface;
use App\Expense\Application\Validation\ExpenseAmountValidator;
use App\Expense\Domain\ExpenseDraft;
use App\Family\Domain\Entity\Family;
use App\Identity\Domain\Entity\User;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\ExceptionMapper\CoreExceptionMapper;
use App\Presentation\Telegram\Flow\Expense\AmountParser;
use App\Presentation\Telegram\Flow\Expense\ExpenseAmountEntryStep;
use App\Presentation\Telegram\Flow\Expense\ExpenseAmountScreen;
use App\Presentation\Telegram\Flow\Expense\ExpenseCardRenderer;
use App\Presentation\Telegram\Flow\Expense\ExpenseCurrencySelectionFlow;
use App\Presentation\Telegram\Flow\Expense\ExpenseDraftCallbackData;
use App\Presentation\Telegram\Flow\Expense\ExpenseDraftScreen;
use App\Presentation\Telegram\Flow\Expense\ExpenseStorePickerScreen;
use App\Presentation\Telegram\Flow\Expense\MoneyAmountFormatter;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(ExpenseAmountEntryStep::class)]
final class ExpenseAmountEntryStepTest extends TestCase
{
    public function testValidAmountIsStoredExactlyAndAdvancesToCategory(): void
    {
        $context = $this->createContext();
        $draft = $this->createDraft($context);
        $telegram = $this->createMock(TelegramApiClientInterface::class);
        $telegram->expects(self::once())
            ->method('editMessageText')
            ->willReturn(['ok' => true, 'result' => ['message_id' => 51]]);
        $step = $this->createStep($telegram);

        $step->handle($this->message('00125,50'), $context, $draft);

        self::assertSame('125.5', $draft->amount);
        self::assertSame('PLN', $draft->currency);
        self::assertSame('category', $draft->currentStep);
        self::assertSame(51, $draft->cardMessageId);
    }

    public function testChangingCurrencyPreservesAmountAndBlocksContinueWhenPrecisionConflicts(): void
    {
        $context = $this->createContext();
        $draft = $this->createDraft($context);
        $draft->setAmount('12.5', 'PLN');
        $draft->setStep('amount');
        $callbackData = new ExpenseDraftCallbackData();
        $markup = null;
        $text = '';
        $telegram = $this->createMock(TelegramApiClientInterface::class);
        $telegram->expects(self::once())->method('answerCallbackQuery')->with('callback')->willReturn(true);
        $telegram->expects(self::once())
            ->method('editMessageText')
            ->willReturnCallback(static function (
                int|string $chatId,
                int $messageId,
                string $cardText,
                ?array $replyMarkup = null
            ) use (&$markup, &$text): array {
                $markup = $replyMarkup;
                $text = $cardText;

                return ['ok' => true, 'result' => ['message_id' => $messageId]];
            });
        $step = $this->createStep($telegram, $callbackData);
        $callback = $callbackData->forDraft(
            $draft,
            CallbackActions::EXPENSE_AMOUNT_CURRENCY_ACTION_PREFIX . 'ISK'
        );

        $step->handle($this->callbackUpdate($callback), $context, $draft);

        self::assertSame('12.5', $draft->amount);
        self::assertSame('ISK', $draft->currency);
        self::assertSame('amount', $draft->currentStep);
        self::assertStringContainsString('expense.add.currency.precision_conflict', $text);
        self::assertIsArray($markup);
        $buttonTexts = [];
        foreach ($markup['inline_keyboard'] as $row) {
            array_push($buttonTexts, ...array_column($row, 'text'));
        }
        self::assertNotContains('expense.add.amount.continue', $buttonTexts);
    }

    public function testPrecisionConflictEditPromptPreservesAllFractionalDigits(): void
    {
        $context = $this->createContext('uk');
        $draft = $this->createDraft($context);
        $draft->setAmount('12.345', 'ISK');
        $draft->setStep('amount');
        $text = '';
        $telegram = $this->createMock(TelegramApiClientInterface::class);
        $telegram->expects(self::once())
            ->method('editMessageText')
            ->willReturnCallback(static function (
                int|string $chatId,
                int $messageId,
                string $cardText
            ) use (&$text): array {
                $text = $cardText;

                return ['ok' => true, 'result' => ['message_id' => $messageId]];
            });
        $step = $this->createStep($telegram, translator: $this->createTranslator('uk'));

        $step->render($context, $draft, 51);

        self::assertStringContainsString('Поточна сума: 12,345 ISK.', $text);
    }

    public function testCurrencyPickerUsesLocalizedPromptAndSearchButton(): void
    {
        $context = $this->createContext('uk');
        $draft = $this->createDraft($context);
        $callbackData = new ExpenseDraftCallbackData();
        $text = '';
        $markup = null;
        $telegram = $this->createMock(TelegramApiClientInterface::class);
        $telegram->expects(self::once())->method('answerCallbackQuery')->willReturn(true);
        $telegram->expects(self::once())->method('editMessageText')->willReturnCallback(
            static function (int|string $chatId, int $messageId, string $cardText, ?array $replyMarkup = null) use (&$text, &$markup): array {
                $text = $cardText;
                $markup = $replyMarkup;

                return ['ok' => true, 'result' => ['message_id' => $messageId]];
            }
        );
        $step = $this->createStep($telegram, $callbackData, $this->createTranslator('uk'));
        $callback = $callbackData->forDraft($draft, CallbackActions::EXPENSE_AMOUNT_MORE_CURRENCIES_ACTION);

        $step->handle($this->callbackUpdate($callback), $context, $draft);

        self::assertStringContainsString('💱 Оберіть валюту або надішліть її код чи назву', $text);
        self::assertContains('Далі ›', $this->buttonTexts($markup));
        self::assertContains('🔎 Пошук валют', $this->buttonTexts($markup));
    }

    public function testCurrencySearchInputUpdatesQueryAndRendersMatchingCurrencies(): void
    {
        $context = $this->createContext();
        $draft = $this->createDraft($context);
        $draft->setStep('currency_search', 'currency_search', 'amount');
        $text = '';
        $telegram = $this->createMock(TelegramApiClientInterface::class);
        $telegram->expects(self::once())
            ->method('editMessageText')
            ->willReturnCallback(static function (
                int|string $chatId,
                int $messageId,
                string $cardText
            ) use (&$text): array {
                $text = $cardText;

                return ['ok' => true, 'result' => ['message_id' => $messageId]];
            });
        $step = $this->createStep($telegram);

        $step->handle($this->message(' USD '), $context, $draft);

        self::assertSame('USD', $draft->currencySearchQuery);
        self::assertStringContainsString('expense.add.currency.search.results', $text);
    }

    public function testCurrencySearchInputFromCurrencyListOpensSearchAndRendersMatchingCurrencies(): void
    {
        $context = $this->createContext();
        $draft = $this->createDraft($context);
        $draft->setStep('currency_list', null, 'amount');
        $text = '';
        $telegram = $this->createMock(TelegramApiClientInterface::class);
        $telegram->expects(self::once())
            ->method('editMessageText')
            ->willReturnCallback(static function (
                int|string $chatId,
                int $messageId,
                string $cardText
            ) use (&$text): array {
                $text = $cardText;

                return ['ok' => true, 'result' => ['message_id' => $messageId]];
            });
        $step = $this->createStep($telegram);

        $step->handle($this->message('USD'), $context, $draft);

        self::assertSame('currency_search', $draft->currentStep);
        self::assertSame('currency_search', $draft->awaitingInput);
        self::assertSame('USD', $draft->currencySearchQuery);
        self::assertStringContainsString('expense.add.currency.search.results', $text);
    }

    public function testCategoryPromptFormatsAmountUsingContextLocale(): void
    {
        $context = $this->createContext('uk');
        $draft = $this->createDraft($context);
        $text = '';
        $telegram = $this->createMock(TelegramApiClientInterface::class);
        $telegram->expects(self::once())
            ->method('editMessageText')
            ->willReturnCallback(static function (
                int|string $chatId,
                int $messageId,
                string $cardText
            ) use (&$text): array {
                $text = $cardText;

                return ['ok' => true, 'result' => ['message_id' => $messageId]];
            });
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static function (string $key, array $parameters = []): string {
            if ($key === 'expense.add.category.prompt') {
                return sprintf('Add %s %s', $parameters['%{amount}'], $parameters['%{currency}']);
            }

            return $key;
        });
        $step = $this->createStep($telegram, translator: $translator);

        $step->handle($this->message('00125,50'), $context, $draft);

        self::assertStringContainsString('Add 125,50 PLN', $text);
    }

    public function testInvalidAmountKeepsAmountStepAndShowsValidationError(): void
    {
        $context = $this->createContext();
        $draft = $this->createDraft($context);
        $text = '';
        $telegram = $this->createMock(TelegramApiClientInterface::class);
        $telegram->expects(self::once())
            ->method('editMessageText')
            ->willReturnCallback(static function (
                int|string $chatId,
                int $messageId,
                string $cardText
            ) use (&$text): array {
                $text = $cardText;

                return ['ok' => true, 'result' => ['message_id' => $messageId]];
            });
        $step = $this->createStep($telegram);

        $step->handle($this->message('not a number'), $context, $draft);

        self::assertSame('amount', $draft->currentStep);
        self::assertNull($draft->amount);
        self::assertStringContainsString('expense.amount.invalid_format', $text);
    }

    private function createStep(
        TelegramApiClientInterface $telegram,
        ?ExpenseDraftCallbackData $callbackData = null,
        ?TranslatorInterface $translator = null
    ): ExpenseAmountEntryStep {
        if ($translator === null) {
            $translator = $this->createMock(TranslatorInterface::class);
            $translator->method('trans')->willReturnCallback(static fn (string $key): string => $key);
        }
        $dispatcher = $this->createMock(MessageBusDispatcherInterface::class);
        $callbackData ??= new ExpenseDraftCallbackData();
        $amountParser = new AmountParser(new ExpenseAmountValidator());
        $formatter = new MoneyAmountFormatter();
        $renderer = new ExpenseCardRenderer($telegram, $translator, $formatter);
        $draftScreen = new ExpenseDraftScreen(
            $renderer,
            $callbackData,
            $telegram,
            $translator,
            $dispatcher,
            $formatter
        );

        $screen = new ExpenseAmountScreen(
            $renderer,
            $callbackData,
            $dispatcher,
            new CurrencyCatalog(),
            $formatter,
            $amountParser,
            $translator
        );
        $currencySelectionFlow = new ExpenseCurrencySelectionFlow($screen, $formatter, $telegram, $translator);

        return new ExpenseAmountEntryStep(
            $amountParser,
            new CoreExceptionMapper(),
            $screen,
            $currencySelectionFlow,
            new ExpenseStorePickerScreen($renderer, $callbackData, $dispatcher, $translator),
            $draftScreen,
            $callbackData,
            $telegram,
            $translator
        );
    }

    private function createTranslator(string $locale): Translator
    {
        $translator = new Translator($locale);
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', dirname(__DIR__, 6) . '/translations/messages.' . $locale . '.yaml', $locale);

        return $translator;
    }

    /**
     * @param array<string, mixed>|null $markup
     * @return list<string>
     */
    private function buttonTexts(?array $markup): array
    {
        $texts = [];
        foreach ($markup['inline_keyboard'] ?? [] as $row) {
            array_push($texts, ...array_column($row, 'text'));
        }

        return $texts;
    }

    private function createContext(string $language = 'en'): TelegramFamilyMemberContext
    {
        $user = User::create(100, $language);
        $userContext = new TelegramUserContext($user, $language, 100, 111);
        $family = Family::create(
            'Test family',
            Timezone::fromString('UTC'),
            CurrencyCode::fromString('PLN'),
            CurrencyCode::fromString('PLN')
        );

        return new TelegramFamilyMemberContext($userContext, $family);
    }

    private function createDraft(TelegramFamilyMemberContext $context): ExpenseDraft
    {
        $draft = ExpenseDraft::createNew(
            $context->user->getId(),
            $context->activeFamily->getId(),
            $context->telegramUserId,
            $context->chatId,
            new DateTimeImmutable()
        );
        $draft->setStep('amount', 'amount');
        $draft->setCardMessage(51);

        return $draft;
    }

    private function message(string $text): TelegramUpdate
    {
        return new TelegramUpdate(1, 111, 100, null, null, null, 'en', 52, $text, null, null, null);
    }

    private function callbackUpdate(string $data): TelegramUpdate
    {
        return new TelegramUpdate(2, 111, 100, null, null, null, 'en', null, null, 'callback', $data, 51);
    }
}

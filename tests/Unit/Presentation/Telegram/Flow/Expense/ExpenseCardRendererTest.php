<?php

namespace App\Tests\Unit\Presentation\Telegram\Flow\Expense;

use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Flow\Expense\ExpenseCardRenderer;
use App\Presentation\Telegram\Flow\Expense\MoneyAmountFormatter;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ExpenseCardRendererTest extends TestCase
{
    public function testRejectedEditSendsNewCardAndClosesThePreviousOne(): void
    {
        $editCalls = [];
        $telegram = $this->createMock(TelegramApiClientInterface::class);
        $telegram->expects(self::exactly(2))
            ->method('editMessageText')
            ->willReturnCallback(static function (
                int|string $chatId,
                int $messageId,
                string $text,
                ?array $replyMarkup
            ) use (&$editCalls): array {
                $editCalls[] = [$chatId, $messageId, $text, $replyMarkup];

                return count($editCalls) === 1
                    ? ['ok' => false, 'description' => 'Message to edit not found']
                    : ['ok' => true, 'result' => ['message_id' => $messageId]];
            });
        $telegram->expects(self::once())
            ->method('sendMessage')
            ->with(111, 'updated card', null)
            ->willReturn(['result' => ['message_id' => 42]]);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key): string => $key);
        $renderer = new ExpenseCardRenderer($telegram, $translator, new MoneyAmountFormatter());

        self::assertSame(42, $renderer->render(111, 41, 'updated card', []));
        self::assertSame([
            [111, 41, 'updated card', ['inline_keyboard' => []]],
            [111, 41, 'expense.add.card.moved', ['inline_keyboard' => []]],
        ], $editCalls);
    }
}
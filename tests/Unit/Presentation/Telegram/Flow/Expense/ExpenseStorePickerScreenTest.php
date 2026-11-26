<?php

namespace App\Tests\Unit\Presentation\Telegram\Flow\Expense;

use App\Catalog\Application\Query\ListStoresQuery;
use App\Catalog\Domain\Entity\Store;
use App\Catalog\Domain\ValueObject\StoreName;
use App\Core\Domain\ValueObject\CurrencyCode;
use App\Core\Domain\ValueObject\Timezone;
use App\Core\Domain\ValueObject\StoreId;
use App\Core\Infrastructure\Messenger\MessageBusDispatcherInterface;
use App\Expense\Application\Query\GetStoreIdsByLastPurchaseQuery;
use App\Expense\Domain\ExpenseDraft;
use App\Family\Application\Query\GetActiveFamilyCountQuery;
use App\Family\Domain\Entity\Family;
use App\Identity\Domain\Entity\User;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\Flow\Expense\ExpenseCardRenderer;
use App\Presentation\Telegram\Flow\Expense\ExpenseDraftCallbackData;
use App\Presentation\Telegram\Flow\Expense\ExpenseStorePickerScreen;
use App\Presentation\Telegram\Flow\Expense\MoneyAmountFormatter;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ExpenseStorePickerScreenTest extends TestCase
{
    public function testStoreListOrdersStoresByLastPurchaseBeforeAlphabeticalRemainder(): void
    {
        $context = $this->createContext();
        $alpha = Store::create(
            $context->activeFamily->getId(),
            StoreName::fromString('Alpha'),
            $context->user->getId()
        );
        $charlie = Store::create(
            $context->activeFamily->getId(),
            StoreName::fromString('Charlie'),
            $context->user->getId()
        );
        $zebra = Store::create(
            $context->activeFamily->getId(),
            StoreName::fromString('Zebra'),
            $context->user->getId()
        );
        $bravo = Store::create(
            $context->activeFamily->getId(),
            StoreName::fromString('Bravo'),
            $context->user->getId()
        );
        $dispatcher = $this->createDispatcher(
            [$charlie, $zebra, $alpha, $bravo],
            [$bravo->getId(), $zebra->getId()]
        );
        $markup = null;
        $telegram = $this->createMock(TelegramApiClientInterface::class);
        $telegram->expects(self::once())
            ->method('sendMessage')
            ->with(111, self::stringContains('expense.add.store.prompt'), self::callback(
                static function (?array $keyboard) use (&$markup): bool {
                    $markup = $keyboard;

                    return is_array($keyboard);
                }
            ))
            ->willReturn(['result' => ['message_id' => 70]]);
        $draft = ExpenseDraft::createNew(
            $context->user->getId(),
            $context->activeFamily->getId(),
            $context->telegramUserId,
            $context->chatId,
            new \DateTimeImmutable()
        );
        $draft->setStep('store');

        $this->createScreen($telegram, $dispatcher)->renderList($context, $draft, null);

        self::assertIsArray($markup);
        $storeButtons = array_filter(
            array_merge(...$markup['inline_keyboard']),
            static fn (array $button): bool => str_contains($button['callback_data'], ':s:')
        );
        self::assertSame(['Bravo', 'Zebra', 'Alpha', 'Charlie'], array_column($storeButtons, 'text'));
    }

    public function testStoreListShowsEightItemsAndPaginationControls(): void
    {
        $context = $this->createContext();
        $stores = [];
        for ($index = 1; $index <= 9; ++$index) {
            $stores[] = Store::create(
                $context->activeFamily->getId(),
                StoreName::fromString('Store ' . $index),
                $context->user->getId()
            );
        }

        $dispatcher = $this->createDispatcher($stores);
        $telegram = $this->createMock(TelegramApiClientInterface::class);
        $markup = null;
        $renderedText = null;
        $telegram->expects(self::once())
            ->method('sendMessage')
            ->with(111, self::callback(static function (string $text) use (&$renderedText): bool {
                $renderedText = $text;

                return str_contains($text, 'expense.add.store.prompt');
            }), self::callback(
                static function (?array $keyboard) use (&$markup): bool {
                    $markup = $keyboard;
                    return is_array($keyboard);
                }
            ))
            ->willReturn(['result' => ['message_id' => 70]]);
        $pageParameters = null;
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static function (string $key, array $parameters = []) use (&$pageParameters): string {
                if ($key !== 'expense.add.pagination.page') {
                    return $key;
                }

                $pageParameters = $parameters;

                return sprintf('Page %s/%s', $parameters['%{current}'], $parameters['%{total}']);
            }
        );
        $draft = ExpenseDraft::createNew(
            $context->user->getId(),
            $context->activeFamily->getId(),
            $context->telegramUserId,
            $context->chatId,
            new \DateTimeImmutable()
        );
        $draft->setStep('store');

        $screen = $this->createScreen(
            $telegram,
            $dispatcher,
            $translator
        );
        $screen->renderList($context, $draft, null);

        self::assertIsArray($markup);
        self::assertCount(6, $markup['inline_keyboard']);
        $buttons = array_merge(...$markup['inline_keyboard']);
        $storeButtons = array_filter(
            $buttons,
            static fn (array $button): bool => str_starts_with($button['callback_data'], 'exp:t:')
                && str_contains($button['callback_data'], ':s:')
        );
        self::assertCount(8, $storeButtons);
        foreach ($buttons as $button) {
            self::assertLessThanOrEqual(64, strlen($button['callback_data']));
        }
        self::assertSame('expense.add.pagination.next', $markup['inline_keyboard'][4][0]['text']);
        self::assertSame(['%{current}' => '1', '%{total}' => '2'], $pageParameters);
        self::assertIsString($renderedText);
        self::assertStringContainsString('Page 1/2', $renderedText);
    }

    public function testStoreListOmitsPageIndicatorWhenAllStoresFitOnOnePage(): void
    {
        $context = $this->createContext();
        $store = Store::create(
            $context->activeFamily->getId(),
            StoreName::fromString('Store'),
            $context->user->getId()
        );
        $dispatcher = $this->createDispatcher([$store]);
        $telegram = $this->createMock(TelegramApiClientInterface::class);
        $renderedText = null;
        $telegram->expects(self::once())
            ->method('sendMessage')
            ->with(111, self::callback(static function (string $text) use (&$renderedText): bool {
                $renderedText = $text;

                return str_contains($text, 'expense.add.store.prompt');
            }), self::anything())
            ->willReturn(['result' => ['message_id' => 70]]);
        $draft = ExpenseDraft::createNew(
            $context->user->getId(),
            $context->activeFamily->getId(),
            $context->telegramUserId,
            $context->chatId,
            new \DateTimeImmutable()
        );
        $draft->setStep('store');

        $this->createScreen($telegram, $dispatcher)->renderList($context, $draft, null);

        self::assertIsString($renderedText);
        self::assertStringNotContainsString('expense.add.pagination.page', $renderedText);
    }

    public function testStoreListRequestsOrderedIdsBeforeLoadingStoreEntities(): void
    {
        $context = $this->createContext();
        $alpha = Store::create(
            $context->activeFamily->getId(),
            StoreName::fromString('Alpha'),
            $context->user->getId()
        );
        $recent = Store::create(
            $context->activeFamily->getId(),
            StoreName::fromString('Zebra'),
            $context->user->getId()
        );
        $queryOrder = [];
        $dispatcher = $this->createMock(MessageBusDispatcherInterface::class);
        $dispatcher->method('dispatchQuery')->willReturnCallback(
            static function (object $query) use (&$queryOrder, $alpha, $recent): mixed {
                $queryOrder[] = $query::class;

                return match (true) {
                    $query instanceof GetStoreIdsByLastPurchaseQuery => [$recent->getId()],
                    $query instanceof ListStoresQuery => [$alpha, $recent],
                    $query instanceof GetActiveFamilyCountQuery => 0,
                    default => null,
                };
            }
        );
        $markup = null;
        $telegram = $this->createMock(TelegramApiClientInterface::class);
        $telegram->expects(self::once())
            ->method('sendMessage')
            ->with(111, self::stringContains('expense.add.store.prompt'), self::callback(
                static function (?array $keyboard) use (&$markup): bool {
                    $markup = $keyboard;

                    return is_array($keyboard);
                }
            ))
            ->willReturn(['result' => ['message_id' => 70]]);
        $draft = ExpenseDraft::createNew(
            $context->user->getId(),
            $context->activeFamily->getId(),
            $context->telegramUserId,
            $context->chatId,
            new \DateTimeImmutable()
        );
        $draft->setStep('store');

        $this->createScreen($telegram, $dispatcher)->renderList($context, $draft, null);

        self::assertIsArray($markup);
        self::assertSame('Zebra', $markup['inline_keyboard'][0][0]['text']);
        self::assertSame([
            GetStoreIdsByLastPurchaseQuery::class,
            ListStoresQuery::class,
            GetActiveFamilyCountQuery::class,
        ], $queryOrder);
    }

    private function createScreen(
        TelegramApiClientInterface $telegram,
        ?MessageBusDispatcherInterface $dispatcher = null,
        ?TranslatorInterface $translator = null
    ): ExpenseStorePickerScreen {
        if ($translator === null) {
            $translator = $this->createMock(TranslatorInterface::class);
            $translator->method('trans')->willReturnCallback(static fn (string $key): string => $key);
        }
        $dispatcher ??= $this->createDispatcher();

        return new ExpenseStorePickerScreen(
            new ExpenseCardRenderer($telegram, $translator, new MoneyAmountFormatter()),
            new ExpenseDraftCallbackData(),
            $dispatcher,
            $translator
        );
    }

    /**
     * @param Store[] $stores
     * @param list<StoreId> $storeIdsByLastPurchase
     */
    private function createDispatcher(array $stores = [], array $storeIdsByLastPurchase = []): MessageBusDispatcherInterface
    {
        $dispatcher = $this->createMock(MessageBusDispatcherInterface::class);
        $dispatcher->method('dispatchQuery')->willReturnCallback(
            static fn (object $query): mixed => match (true) {
                $query instanceof ListStoresQuery => $stores,
                $query instanceof GetStoreIdsByLastPurchaseQuery => $storeIdsByLastPurchase,
                $query instanceof GetActiveFamilyCountQuery => 0,
                default => null,
            }
        );

        return $dispatcher;
    }

    private function createFamily(): Family
    {
        return Family::create(
            'Smiths',
            Timezone::fromString('UTC'),
            CurrencyCode::fromString('PLN'),
            CurrencyCode::fromString('PLN')
        );
    }

    private function createContext(): TelegramFamilyMemberContext
    {
        $user = new User(123456);

        return new TelegramFamilyMemberContext(
            new TelegramUserContext($user, 'en', 123456, 111),
            $this->createFamily()
        );
    }
}
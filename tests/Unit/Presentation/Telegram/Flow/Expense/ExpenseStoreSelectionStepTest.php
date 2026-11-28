<?php

namespace App\Tests\Unit\Presentation\Telegram\Flow\Expense;

use App\Catalog\Application\Query\CatalogSearchInterface;
use App\Catalog\Application\Query\GetStoreQuery;
use App\Catalog\Application\Query\ListStoresQuery;
use App\Catalog\Application\Query\SearchStoresQuery;
use App\Catalog\Domain\Entity\Store;
use App\Catalog\Domain\Repository\CatalogRepositoryInterface;
use App\Catalog\Domain\ValueObject\StoreName;
use App\Core\Domain\ValueObject\CategoryId;
use App\Core\Domain\ValueObject\CurrencyCode;
use App\Core\Domain\ValueObject\StoreId;
use App\Core\Domain\ValueObject\Timezone;
use App\Core\Infrastructure\Messenger\MessageBusDispatcherInterface;
use App\Core\Infrastructure\Persistence\Redis\UserInteractionState;
use App\Core\Infrastructure\Persistence\Redis\UserInteractionStateRepository;
use App\Expense\Application\ExpenseAuthorizationService;
use App\Expense\Application\ExpenseDraftService;
use App\Expense\Application\Query\GetExpenseStoreQuery;
use App\Expense\Application\Query\GetStoreIdsByLastPurchaseQuery;
use App\Expense\Domain\Entity\ExpenseStore;
use App\Expense\Domain\ExpenseDraft;
use App\Expense\Domain\ExpenseDraftStatus;
use App\Expense\Domain\Repository\ExpenseDraftRepositoryInterface;
use App\Family\Domain\Entity\Family;
use App\Family\Application\Query\GetActiveFamilyCountQuery;
use App\Identity\Domain\Entity\User;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Flow\Catalog\CatalogFlowUtils;
use App\Presentation\Telegram\Flow\Catalog\StoreEntryScreen;
use App\Presentation\Telegram\Flow\Expense\AddExpenseStepRouter;
use App\Presentation\Telegram\Flow\Expense\ExpenseCardRenderer;
use App\Presentation\Telegram\Flow\Expense\ExpenseDraftCallbackData;
use App\Presentation\Telegram\Flow\Expense\ExpenseDraftCard;
use App\Presentation\Telegram\Flow\Expense\ExpenseDraftScreen;
use App\Presentation\Telegram\Flow\Expense\ExpenseStoreCreationHandoff;
use App\Presentation\Telegram\Flow\Expense\ExpenseStorePickerScreen;
use App\Presentation\Telegram\Flow\Expense\ExpenseStoreReturn;
use App\Presentation\Telegram\Flow\Expense\ExpenseStoreSelectionStep;
use App\Presentation\Telegram\Flow\Expense\MoneyAmountFormatter;
use App\Presentation\Telegram\MapExceptionToTranslatableMessage;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ExpenseStoreSelectionStepTest extends TestCase
{
    public function testStoreSelectionCallbackFitsTelegramLimitAndMatchesDraft(): void
    {
        $context = $this->createContext();
        $draft = $this->createDraft($context);
        $draft->setStep('store');
        $draft->setCardMessage(51);
        $storeId = StoreId::generate();
        $callbackData = new ExpenseDraftCallbackData();
        $data = $callbackData->forStore($draft, $storeId);
        $callback = $callbackData->parse($data);

        self::assertLessThanOrEqual(64, strlen($data));
        self::assertNotNull($callback);
        self::assertSame($storeId->toRfc4122(), (string) $callbackData->storeIdFromAction($callback['action']));
        self::assertTrue($callbackData->matchesDraft($callback, $draft, $this->callbackUpdate($data, 51)));
        self::assertFalse($callbackData->matchesDraft($callback, $draft, $this->callbackUpdate($data, 52)));

        $draft->cardRevision = PHP_INT_MAX;
        $largeRevisionCallback = $callbackData->forStore($draft, $storeId);
        $largeRevisionParsed = $callbackData->parse($largeRevisionCallback);
        self::assertLessThanOrEqual(64, strlen($largeRevisionCallback));
        self::assertNotNull($largeRevisionParsed);
        self::assertTrue($callbackData->matchesDraft(
            $largeRevisionParsed,
            $draft,
            $this->callbackUpdate($largeRevisionCallback, 51)
        ));
        self::assertNull($callbackData->parse(str_repeat('x', 65)));
    }

    public function testSelectingStorePersistsItsDefaultCategoryAndAdvancesToAmount(): void
    {
        $context = $this->createContext();
        $draft = $this->createDraft($context);
        $draft->setStep('store');
        $draft->setCardMessage(51);
        $store = Store::create($context->activeFamily->getId(), StoreName::fromString('Market'), $context->user->getId());
        $categoryId = CategoryId::generate();
        $store->setDefaultCategory($categoryId, $context->user->getId());

        $catalog = $this->createMock(CatalogRepositoryInterface::class);
        $dispatcher = $this->createQueryDispatcher(static fn (object $query): mixed => match (true) {
            $query instanceof GetStoreQuery => $store,
            $query instanceof GetExpenseStoreQuery => new ExpenseStore(
                $store->getId(),
                (string) $store->getName(),
                $context->activeFamily->getId()
            ),
            default => null,
        });
        $draftRepository = $this->createMock(ExpenseDraftRepositoryInterface::class);
        $draftRepository->expects(self::once())->method('save')->with($draft);
        $telegram = $this->createMock(TelegramApiClientInterface::class);
        $telegram->expects(self::once())->method('answerCallbackQuery')->with('store-callback', null);
        $telegram->expects(self::once())
            ->method('editMessageText')
            ->with(111, 51, self::stringContains('expense.amount.prompt'), self::anything())
            ->willReturn(['ok' => true, 'result' => ['message_id' => 51]]);

        $step = $this->createStep($context, $telegram, $catalog, $draftRepository, $dispatcher);
        $callbackData = new ExpenseDraftCallbackData();
        $step->handle(
            $this->callbackUpdate($callbackData->forStore($draft, $store->getId()), 51),
            $context,
            $draft
        );

        self::assertSame(ExpenseDraftStatus::ACTIVE, $draft->status);
        self::assertSame('amount', $draft->currentStep);
        self::assertSame('amount', $draft->awaitingInput);
        self::assertSame($store->getId(), $draft->storeId);
        self::assertSame($categoryId, $draft->defaultCategoryId);
        self::assertFalse($draft->categoryCustomized);
    }

    public function testSearchUsesTrimmedQueryAndStoresItForCreatePrefill(): void
    {
        $context = $this->createContext();
        $draft = $this->createDraft($context);
        $draft->setStep('store_search', 'store_search');
        $draft->setCardMessage(51);

        $catalog = $this->createMock(CatalogRepositoryInterface::class);
        $dispatcher = $this->createQueryDispatcher(
            static fn (object $query): mixed => $query instanceof SearchStoresQuery ? [] : null
        );
        $draftRepository = $this->createMock(ExpenseDraftRepositoryInterface::class);
        $draftRepository->expects(self::once())->method('save')->with($draft);
        $telegram = $this->createMock(TelegramApiClientInterface::class);
        $telegram->expects(self::once())
            ->method('editMessageText')
            ->with(
                111,
                51,
                self::logicalAnd(
                    self::stringContains('expense.add.store.search.results'),
                    self::stringContains('expense.add.store.search.none')
                ),
                self::anything()
            )
            ->willReturn(['ok' => true, 'result' => ['message_id' => 51]]);

        $step = $this->createStep($context, $telegram, $catalog, $draftRepository, $dispatcher);
        $step->handle($this->message('  market  '), $context, $draft);

        self::assertSame('store_search', $draft->currentStep);
        self::assertSame('market', $draft->storeSearchQuery);
    }

    public function testUnavailableSearchReturnsToStoreList(): void
    {
        $context = $this->createContext();
        $draft = $this->createDraft($context);
        $draft->setStep('store_search', 'store_search');
        $draft->setCardMessage(51);
        $store = Store::create(
            $context->activeFamily->getId(),
            StoreName::fromString('Market'),
            $context->user->getId()
        );

        $catalog = $this->createMock(CatalogRepositoryInterface::class);
        $listQueries = 0;
        $dispatcher = $this->createQueryDispatcher(static function (object $query) use (&$listQueries, $store): mixed {
            if ($query instanceof SearchStoresQuery) {
                throw new RuntimeException();
            }
            if ($query instanceof ListStoresQuery) {
                if (++$listQueries === 1) {
                    throw new RuntimeException();
                }

                return [$store];
            }
            if ($query instanceof GetStoreIdsByLastPurchaseQuery) {
                return [];
            }
            if ($query instanceof GetActiveFamilyCountQuery) {
                return 0;
            }

            return null;
        });
        $draftRepository = $this->createMock(ExpenseDraftRepositoryInterface::class);
        $draftRepository->expects(self::once())->method('save')->with($draft);
        $telegram = $this->createMock(TelegramApiClientInterface::class);
        $renderedText = null;
        $telegram->expects(self::once())
            ->method('editMessageText')
            ->with(111, 51, self::anything(), self::anything())
            ->willReturnCallback(static function (...$arguments) use (&$renderedText): array {
                $renderedText = $arguments[2];

                return ['ok' => true, 'result' => ['message_id' => 51]];
            });

        $step = $this->createStep($context, $telegram, $catalog, $draftRepository, $dispatcher);
        $step->handle($this->message('market'), $context, $draft);

        self::assertSame('store', $draft->currentStep);
        self::assertNull($draft->storeSearchQuery);
        self::assertIsString($renderedText);
        self::assertStringContainsString('expense.add.store.search.unavailable', $renderedText);
        self::assertStringContainsString('expense.add.store.prompt', $renderedText);
        self::assertStringNotContainsString('expense.add.store.search.results', $renderedText);
        self::assertStringNotContainsString('expense.add.store.search.none', $renderedText);
    }

    public function testTooLongSearchPassesItsLimitToPluralTranslation(): void
    {
        $context = $this->createContext();
        $draft = $this->createDraft($context);
        $draft->setStep('store_search', 'store_search');
        $draft->setCardMessage(51);

        $catalog = $this->createMock(CatalogRepositoryInterface::class);
        $draftRepository = $this->createMock(ExpenseDraftRepositoryInterface::class);
        $draftRepository->expects(self::once())->method('save')->with($draft);
        $telegram = $this->createMock(TelegramApiClientInterface::class);
        $telegram->expects(self::once())
            ->method('editMessageText')
            ->with(111, 51, self::stringContains('expense.add.store.search.too_long'), self::anything())
            ->willReturn(['ok' => true, 'result' => ['message_id' => 51]]);

        $translationParameters = null;
        $translationDomain = null;
        $translationLocale = null;
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static function (string $key, array $parameters = [], ?string $domain = null, ?string $locale = null) use (
                &$translationParameters,
                &$translationDomain,
                &$translationLocale
            ): string {
                if ($key === 'expense.add.store.search.too_long') {
                    $translationParameters = $parameters;
                    $translationDomain = $domain;
                    $translationLocale = $locale;
                }

                return $key;
            }
        );

        $step = $this->createStep($context, $telegram, $catalog, $draftRepository, translator: $translator);
        $step->handle($this->message(str_repeat('a', 101)), $context, $draft);

        self::assertSame(['%count%' => 100, '%maxLength%' => 100], $translationParameters);
        self::assertNull($translationDomain);
        self::assertSame($context->language, $translationLocale);
    }

    public function testAddingStorePausesDraftAndPassesSearchQueryToCatalogForm(): void
    {
        $context = $this->createContext();
        $draft = $this->createDraft($context);
        $draft->setStep('store_search', 'store_search');
        $draft->setStoreSearchQuery('market');
        $draft->setCardMessage(51);
        $stateData = null;

        $catalog = $this->createMock(CatalogRepositoryInterface::class);
        $draftRepository = $this->createMock(ExpenseDraftRepositoryInterface::class);
        $draftRepository->expects(self::once())->method('save')->with($draft);
        $states = $this->createMock(UserInteractionStateRepository::class);
        $states->expects(self::once())->method('clear')->with($context->telegramUserId);
        $states->method('get')->willReturn(null);
        $states->expects(self::once())->method('set')->willReturnCallback(
            static function (UserInteractionState $state) use (&$stateData): void {
                $stateData = $state->data;
            }
        );
        $telegram = $this->createMock(TelegramApiClientInterface::class);
        $telegram->expects(self::once())->method('answerCallbackQuery')->with('store-callback', null);
        $telegram->expects(self::once())
            ->method('sendMessage')
            ->with(111, self::stringContains('expense.add.draft.paused_for_creation'), self::isType('array'))
            ->willReturn(['ok' => true, 'result' => ['message_id' => 70]]);

        $step = $this->createStep($context, $telegram, $catalog, $draftRepository, states: $states);
        $callbackData = new ExpenseDraftCallbackData();
        $step->handle(
            $this->callbackUpdate($callbackData->forDraft($draft, 'c'), 51),
            $context,
            $draft
        );

        self::assertSame(ExpenseDraftStatus::PAUSED, $draft->status);
        self::assertSame('store', $draft->currentStep);
        self::assertSame('store', $draft->returnStep);
        self::assertIsArray($stateData);
        self::assertSame('market', $stateData['value']);
        self::assertSame((string) $draft->familyId, $stateData['expense_return_context']['family_id']);
        self::assertSame($draft->idempotencyKey, $stateData['expense_return_context']['draft_token']);
    }

    public function testReturningFromStoreCreationResumesDraftWithSelectedStore(): void
    {
        $context = $this->createContext();
        $draft = $this->createDraft($context);
        $draft->setStep('store', null, 'store');
        $draft->setStatus(ExpenseDraftStatus::PAUSED);
        $draft->setCardMessage(51);
        $store = Store::create($context->activeFamily->getId(), StoreName::fromString('New Market'), $context->user->getId());
        $categoryId = CategoryId::generate();
        $store->setDefaultCategory($categoryId, $context->user->getId());
        $dispatcher = $this->createQueryDispatcher(
            static fn (object $query): mixed => match (true) {
                $query instanceof GetStoreQuery => $store,
                $query instanceof GetExpenseStoreQuery => new ExpenseStore(
                    $store->getId(),
                    (string) $store->getName(),
                    $context->activeFamily->getId()
                ),
                default => null,
            }
        );
        $draftRepository = $this->createMock(ExpenseDraftRepositoryInterface::class);
        $draftRepository->expects(self::once())
            ->method('find')
            ->with($context->user->getId(), $context->activeFamily->getId())
            ->willReturn($draft);
        $draftRepository->expects(self::exactly(2))->method('save')->with($draft);
        $telegram = $this->createMock(TelegramApiClientInterface::class);
        $telegram->expects(self::once())
            ->method('sendMessage')
            ->with(111, self::stringContains('expense.amount.prompt'), null)
            ->willReturn(['result' => ['message_id' => 70]]);
        $telegram->expects(self::once())
            ->method('editMessageText')
            ->with(111, 51, 'expense.add.card.moved', ['inline_keyboard' => []]);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key): string => $key);
        $callbackData = new ExpenseDraftCallbackData();
        $draftService = $this->createDraftService($draftRepository);
        $screen = new ExpenseDraftScreen(
            new ExpenseCardRenderer($telegram, $translator, new MoneyAmountFormatter()),
            $callbackData,
            $telegram,
            $translator,
            $dispatcher,
            new MoneyAmountFormatter()
        );
        $card = new ExpenseDraftCard($screen, new AddExpenseStepRouter([]), $draftService);
        $return = new ExpenseStoreReturn(
            $draftService,
            $dispatcher,
            $card,
            $screen,
            $telegram,
            $translator
        );

        $return->handle($context, $this->returnContext($draft), (string) $store->getId());

        self::assertSame(ExpenseDraftStatus::ACTIVE, $draft->status);
        self::assertSame('amount', $draft->currentStep);
        self::assertSame($store->getId(), $draft->storeId);
        self::assertSame($categoryId, $draft->defaultCategoryId);
        self::assertSame(70, $draft->cardMessageId);
    }

    public function testStoreReturnContextCannotResumeDraftFromAnotherFamily(): void
    {
        $context = $this->createContext();
        $draft = $this->createDraft($context);
        $draftRepository = $this->createMock(ExpenseDraftRepositoryInterface::class);
        $draftRepository->expects(self::never())->method('find');
        $telegram = $this->createMock(TelegramApiClientInterface::class);
        $telegram->expects(self::once())
            ->method('sendMessage')
            ->with(111, 'expense.add.callback.stale');
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key): string => $key);
        $draftService = $this->createDraftService($draftRepository);
        $screen = new ExpenseDraftScreen(
            new ExpenseCardRenderer($telegram, $translator, new MoneyAmountFormatter()),
            new ExpenseDraftCallbackData(),
            $telegram,
            $translator,
            $this->createMock(MessageBusDispatcherInterface::class),
            new MoneyAmountFormatter()
        );
        $return = new ExpenseStoreReturn(
            $draftService,
            $this->createMock(MessageBusDispatcherInterface::class),
            new ExpenseDraftCard($screen, new AddExpenseStepRouter([]), $draftService),
            $screen,
            $telegram,
            $translator
        );
        $returnContext = $this->returnContext($draft);
        $returnContext['family_id'] = (string) Family::create(
            'Other',
            Timezone::fromString('UTC'),
            CurrencyCode::fromString('PLN'),
            CurrencyCode::fromString('PLN')
        )->getId();

        $return->handle($context, $returnContext, null);
    }

    private function createStep(
        TelegramFamilyMemberContext $context,
        TelegramApiClientInterface $telegram,
        CatalogRepositoryInterface $catalog,
        ExpenseDraftRepositoryInterface $draftRepository,
        ?MessageBusDispatcherInterface $dispatcher = null,
        ?UserInteractionStateRepository $states = null,
        ?TranslatorInterface $translator = null
    ): ExpenseStoreSelectionStep {
        if ($translator === null) {
            $translator = $this->createMock(TranslatorInterface::class);
            $translator->method('trans')->willReturnCallback(static fn (string $key): string => $key);
        }
        $callbackData = new ExpenseDraftCallbackData();
        $renderer = new ExpenseCardRenderer($telegram, $translator, new MoneyAmountFormatter());
        $dispatcher ??= $this->createQueryDispatcher(static fn (object $query): mixed => match (true) {
            $query instanceof ListStoresQuery, $query instanceof SearchStoresQuery,
            $query instanceof GetStoreIdsByLastPurchaseQuery => [],
            $query instanceof GetActiveFamilyCountQuery => 0,
            default => null,
        });
        $states ??= $this->createMock(UserInteractionStateRepository::class);
        $draftService = $this->createDraftService($draftRepository);
        $screen = new ExpenseDraftScreen(
            $renderer,
            $callbackData,
            $telegram,
            $translator,
            $dispatcher,
            new MoneyAmountFormatter()
        );
        $draftCard = new ExpenseDraftCard($screen, new AddExpenseStepRouter([]), $draftService);
        $catalogSearch = $this->createMock(CatalogSearchInterface::class);
        $picker = new ExpenseStorePickerScreen($renderer, $callbackData, $dispatcher, $translator);
        $catalogUtils = new CatalogFlowUtils(
            $telegram,
            $states,
            $this->createMock(MessageBusDispatcherInterface::class),
            $translator
        );
        $storeScreen = new StoreEntryScreen(
            $catalogUtils,
            $catalog,
            $catalogSearch,
            new MapExceptionToTranslatableMessage([])
        );
        $handoff = new ExpenseStoreCreationHandoff(
            $draftService,
            $states,
            $storeScreen,
            $translator
        );

        return new ExpenseStoreSelectionStep(
            $dispatcher,
            $callbackData,
            $draftCard,
            $draftService,
            $screen,
            $picker,
            $handoff,
            $telegram,
            $translator
        );
    }

    private function createDraftService(
        ExpenseDraftRepositoryInterface $draftRepository
    ): ExpenseDraftService {
        return new ExpenseDraftService(
            $draftRepository,
            $this->createMock(ExpenseAuthorizationService::class)
        );
    }

    private function createQueryDispatcher(callable $queryHandler): MessageBusDispatcherInterface
    {
        $dispatcher = $this->createMock(MessageBusDispatcherInterface::class);
        $dispatcher->method('dispatchQuery')->willReturnCallback($queryHandler);

        return $dispatcher;
    }

    private function createDraft(TelegramFamilyMemberContext $context): ExpenseDraft
    {
        return ExpenseDraft::createNew(
            $context->user->getId(),
            $context->activeFamily->getId(),
            $context->telegramUserId,
            $context->chatId,
            new \DateTimeImmutable()
        );
    }

    /** @return array<string, mixed> */
    private function returnContext(ExpenseDraft $draft): array
    {
        return [
            'user_id' => (string) $draft->userId,
            'family_id' => (string) $draft->familyId,
            'telegram_user_id' => $draft->telegramUserId,
            'chat_id' => $draft->chatId,
            'draft_token' => $draft->idempotencyKey,
            'return_step' => 'amount',
        ];
    }

    private function createContext(): TelegramFamilyMemberContext
    {
        $user = new User(123456);
        $family = Family::create(
            'Smiths',
            Timezone::fromString('UTC'),
            CurrencyCode::fromString('PLN'),
            CurrencyCode::fromString('PLN')
        );

        return new TelegramFamilyMemberContext(new TelegramUserContext($user, 'en', 123456, 111), $family);
    }

    private function message(string $text): TelegramUpdate
    {
        return TelegramUpdate::fromArray([
            'update_id' => 1,
            'message' => ['message_id' => 10, 'chat' => ['id' => 111], 'text' => $text],
        ]);
    }

    private function callbackUpdate(string $data, int $messageId): TelegramUpdate
    {
        return TelegramUpdate::fromArray([
            'update_id' => 2,
            'callback_query' => [
                'id' => 'store-callback',
                'from' => ['id' => 123456],
                'data' => $data,
                'message' => ['message_id' => $messageId, 'chat' => ['id' => 111]],
            ],
        ]);
    }
}

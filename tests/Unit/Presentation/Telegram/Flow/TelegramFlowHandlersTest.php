<?php

namespace App\Tests\Unit\Presentation\Telegram\Flow;

use App\Core\Domain\ValueObject\CurrencyCode;
use App\Core\Domain\ValueObject\Timezone;
use App\Core\Domain\ValueObject\UserId;
use App\Core\Infrastructure\Messenger\MessageBusDispatcherInterface;
use App\Core\Infrastructure\Persistence\Redis\UserInteractionState;
use App\Core\Infrastructure\Persistence\Redis\UserInteractionStateRepository;
use App\Expense\Application\ExpenseAuthorizationService;
use App\Expense\Application\ExpenseDraftService;
use App\Expense\Domain\Repository\ExpenseDraftRepositoryInterface;
use App\Family\Application\Query\GetUserFamiliesQuery;
use App\Family\Domain\Entity\Family;
use App\Family\Domain\Entity\Invitation;
use App\Family\Domain\Repository\FamilyMemberRepositoryInterface;
use App\Family\Domain\Repository\FamilyRepositoryInterface;
use App\Family\Domain\Repository\InvitationRepositoryInterface;
use App\Family\Domain\Repository\InvitationUseRepositoryInterface;
use App\Family\Infrastructure\Security\InvitationTokenGenerator;
use App\Identity\Domain\Entity\User;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Flow\FamilyCreationWizardHandler;
use App\Presentation\Telegram\Flow\FamilyInvitations\GetInvitationStatusLabel;
use App\Presentation\Telegram\Flow\FamilyInvitations\ListInvitationScreen;
use App\Presentation\Telegram\Flow\FamilyInvitationsFlowHandler;
use App\Presentation\Telegram\Flow\FamilyMembersFlowHandler;
use App\Presentation\Telegram\Flow\FamilyOnboardingFlowHandler;
use App\Presentation\Telegram\Flow\FamilySelectorFlowHandler;
use App\Presentation\Telegram\Flow\FamilySettings\FamilySettingsCurrencyStep;
use App\Presentation\Telegram\Flow\FamilySettings\FamilySettingsNameStep;
use App\Presentation\Telegram\Flow\FamilySettings\FamilySettingsProviderStep;
use App\Presentation\Telegram\Flow\FamilySettings\FamilySettingsRenderer;
use App\Presentation\Telegram\Flow\FamilySettings\FamilySettingsTimezoneStep;
use App\Presentation\Telegram\Flow\FamilySettingsFlowHandler;
use App\Presentation\Telegram\Flow\Expense\AddExpenseStepRouter;
use App\Presentation\Telegram\Flow\Expense\ExpenseCardRenderer;
use App\Presentation\Telegram\Flow\Expense\ExpenseDraftCallbackData;
use App\Presentation\Telegram\Flow\Expense\ExpenseDraftCard;
use App\Presentation\Telegram\Flow\Expense\ExpenseDraftEntry;
use App\Presentation\Telegram\Flow\Expense\ExpenseDraftScreen;
use App\Presentation\Telegram\Flow\Expense\MoneyAmountFormatter;
use App\Presentation\Telegram\Flow\JoinFamilyFlowHandler;
use App\Presentation\Telegram\Flow\MainMenuFlowHandler;
use App\Presentation\Telegram\Flow\SettingsFlowHandler;
use App\Presentation\Telegram\Flow\SettingsMenuRenderer;
use App\Presentation\Telegram\Keyboard\InlineOptionSelector;
use App\Presentation\Telegram\Keyboard\MainMenuKeyboard;
use App\Presentation\Telegram\Keyboard\TimezoneOptions;
use App\Presentation\Telegram\Keyboard\TimezoneSelector;
use App\Presentation\Telegram\MapExceptionToTranslatableMessage;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class TelegramFlowHandlersTest extends TestCase
{
    private function createTranslator(): TranslatorInterface&LocaleAwareInterface
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(fn($key, $params = []) => $key);

        return new class($translator) implements TranslatorInterface, LocaleAwareInterface {
            public function __construct(private TranslatorInterface&MockObject $mock) {}
            /** @param array<string|int, mixed> $parameters */
            public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string {
                return $this->mock->trans($id, $parameters, $domain, $locale);
            }
            public function setLocale(string $locale): void {}
            public function getLocale(): string { return 'en'; }
        };
    }

    public function testMainMenuFlowHandlerSendsWelcome(): void
    {
        $telegramClient = $this->createMock(TelegramApiClientInterface::class);
        $telegramClient->expects($this->once())->method('sendMessage')->with(
            111,
            $this->stringContains('main_menu.welcome_text'),
            $this->isType('array')
        );

        $translator = $this->createTranslator();
        $mainMenuKb = new MainMenuKeyboard($translator);
        $memberRepo = $this->createMock(FamilyMemberRepositoryInterface::class);
        $memberRepo->method('findByUserId')->willReturn([]);

        $handler = new MainMenuFlowHandler(
            $telegramClient,
            $mainMenuKb,
            $memberRepo,
            $translator,
            $this->createDraftService($this->createMock(ExpenseDraftRepositoryInterface::class))
        );

        $user = new User(123456);
        $family = Family::create('Smiths', new Timezone('UTC'), new CurrencyCode('USD'), new CurrencyCode('USD'));
        $context = new TelegramFamilyMemberContext(new TelegramUserContext($user, 'en', 123456, 111), $family);

        $update = TelegramUpdate::fromArray([
            'update_id' => 1,
            'message' => ['message_id' => 10, 'chat' => ['id' => 111], 'text' => '/start'],
        ]);

        $this->assertTrue($handler->supports($update, $context));
        $handler->handle($update, $context);
    }

    public function testFamilyOnboardingFlowHandler(): void
    {
        $telegramClient = $this->createMock(TelegramApiClientInterface::class);
        $telegramClient->expects($this->exactly(2))->method('sendMessage');

        $translator = $this->createTranslator();
        $handler = new FamilyOnboardingFlowHandler($telegramClient, $translator);

        $user = new User(123456);
        $context = new TelegramUserContext($user, 'en', 123456, 111);

        $update = TelegramUpdate::fromArray([
            'update_id' => 1,
            'message' => ['message_id' => 10, 'chat' => ['id' => 111], 'text' => '/start'],
        ]);

        $this->assertTrue($handler->supports($update, $context));
        $handler->handle($update, $context);

        $mainMenuCallback = TelegramUpdate::fromArray([
            'update_id' => 2,
            'callback_query' => [
                'id' => 'cb1',
                'data' => 'nav:main_menu',
                'message' => ['chat' => ['id' => 111]],
            ],
        ]);
        self::assertTrue($handler->supports($mainMenuCallback, $context));
        $handler->handle($mainMenuCallback, $context);
    }

    public function testFamilyCreationWizardStepByStep(): void
    {
        $telegramClient = $this->createMock(TelegramApiClientInterface::class);
        $telegramClient->expects($this->atLeastOnce())->method('sendMessage');

        $stateRepo = $this->createMock(UserInteractionStateRepository::class);
        $commandBus = $this->createMock(MessageBusDispatcherInterface::class);
        $translator = $this->createTranslator();
        $mainMenuKb = new MainMenuKeyboard($translator);

        $exceptionMapper = new MapExceptionToTranslatableMessage([]);
        $handler = new FamilyCreationWizardHandler(
            $telegramClient,
            $stateRepo,
            $commandBus,
            $mainMenuKb,
            new TimezoneSelector(new TimezoneOptions(), new InlineOptionSelector(), $translator),
            $translator,
            $exceptionMapper
        );

        $user = new User(123456);
        $context = new TelegramUserContext($user, 'en', 123456, 111);

        $updateStart = TelegramUpdate::fromArray([
            'update_id' => 1,
            'callback_query' => ['id' => 'cb1', 'data' => 'family:create:start', 'message' => ['chat' => ['id' => 111]]],
        ]);

        $this->assertTrue($handler->supports($updateStart, $context));
        $handler->handle($updateStart, $context);
    }

    public function testFamilySelectorFlowHandler(): void
    {
        $telegramClient = $this->createMock(TelegramApiClientInterface::class);
        $telegramClient->expects($this->once())->method('sendMessage');

        $messageBusDispatcher = $this->createMock(MessageBusDispatcherInterface::class);
        $messageBusDispatcher->method('dispatchQuery')->willReturn([]);
        $translator = $this->createTranslator();
        $mainMenuKb = new MainMenuKeyboard($translator);
        $draftRepository = $this->createMock(ExpenseDraftRepositoryInterface::class);

        $handler = new FamilySelectorFlowHandler(
            $telegramClient,
            $messageBusDispatcher,
            $mainMenuKb,
            $translator,
            $this->createDraftService($draftRepository),
            $this->createExpenseDraftEntry($telegramClient, $draftRepository, $translator)
        );

        $user = new User(123456);
        $family = Family::create('Smiths', new Timezone('UTC'), new CurrencyCode('USD'), new CurrencyCode('USD'));
        $context = new TelegramFamilyMemberContext(new TelegramUserContext($user, 'en', 123456, 111), $family);

        $update = TelegramUpdate::fromArray([
            'update_id' => 1,
            'message' => ['message_id' => 10, 'chat' => ['id' => 111], 'text' => '/switch'],
        ]);

        $this->assertTrue($handler->supports($update, $context));
        $handler->handle($update, $context);
    }

    public function testFamilyScopedMenuButtonOpensFamilySelectorWithoutActiveFamily(): void
    {
        $telegramClient = $this->createMock(TelegramApiClientInterface::class);
        $telegramClient->expects(self::once())
            ->method('sendMessage')
            ->with(111, 'family.onboarding.title' . "\n\n" . 'family.onboarding.text', self::anything());

        $messageBusDispatcher = $this->createMock(MessageBusDispatcherInterface::class);
        $messageBusDispatcher->expects(self::once())->method('dispatchQuery')->willReturn([]);

        $translator = $this->createTranslator();
        $mainMenuKeyboard = new MainMenuKeyboard($translator);
        $draftRepository = $this->createMock(ExpenseDraftRepositoryInterface::class);
        $handler = new FamilySelectorFlowHandler(
            $telegramClient,
            $messageBusDispatcher,
            $mainMenuKeyboard,
            $translator,
            $this->createDraftService($draftRepository),
            $this->createExpenseDraftEntry($telegramClient, $draftRepository, $translator)
        );

        $user = new User(123456);
        $context = new TelegramUserContext($user, 'en', 123456, 111);
        $update = TelegramUpdate::fromArray([
            'update_id' => 1,
            'message' => [
                'message_id' => 10,
                'chat' => ['id' => 111],
                'text' => 'main_menu.stores',
            ],
        ]);

        self::assertTrue($handler->supports($update, $context));
        $handler->handle($update, $context);

        $addExpenseUpdate = TelegramUpdate::fromArray([
            'update_id' => 2,
            'message' => ['message_id' => 11, 'chat' => ['id' => 111], 'text' => '/add'],
        ]);
        self::assertTrue($handler->supports($addExpenseUpdate, $context));

        $family = Family::create('Smiths', new Timezone('UTC'), new CurrencyCode('USD'), new CurrencyCode('USD'));
        $familyContext = new TelegramFamilyMemberContext($context, $family);
        self::assertFalse($handler->supports($update, $familyContext));
    }

    public function testFamilyMembersFlowHandler(): void
    {
        $telegramClient = $this->createMock(TelegramApiClientInterface::class);
        $telegramClient->expects($this->once())->method('sendMessage');

        $messageBusDispatcher = $this->createMock(MessageBusDispatcherInterface::class);
        $messageBusDispatcher->method('dispatchQuery')->willReturn([]);

        $translator = $this->createTranslator();

        $handler = new FamilyMembersFlowHandler($telegramClient, $messageBusDispatcher, $translator);

        $user = new User(123456);
        $family = Family::create('Smiths', new Timezone('UTC'), new CurrencyCode('USD'), new CurrencyCode('USD'));
        $context = new TelegramFamilyMemberContext(new TelegramUserContext($user, 'en', 123456, 111), $family);

        $update = TelegramUpdate::fromArray([
            'update_id' => 1,
            'callback_query' => ['id' => 'cb1', 'data' => 'family:members', 'message' => ['chat' => ['id' => 111]]],
        ]);

        $this->assertTrue($handler->supports($update, $context));
        $handler->handle($update, $context);
    }

    public function testFamilyInvitationsFlowHandler(): void
    {
        $telegramClient = $this->createMock(TelegramApiClientInterface::class);
        $telegramClient->expects($this->once())->method('sendMessage');

        $family = Family::create('Smiths', new Timezone('UTC'), new CurrencyCode('USD'), new CurrencyCode('USD'));
        $invitation = Invitation::create($family->getId(), UserId::generate(), 'hash', null, 1);

        $messageBusDispatcher = $this->createMock(MessageBusDispatcherInterface::class);
        $messageBusDispatcher->method('dispatchCommand')->willReturn([
            'raw_token' => 'plain_123',
            'invitation' => $invitation,
        ]);
        $messageBusDispatcher->method('dispatchQuery')->willReturn([]);

        $invRepo = $this->createMock(InvitationRepositoryInterface::class);
        $invUseRepo = $this->createMock(InvitationUseRepositoryInterface::class);
        $translator = $this->createTranslator();

        $listScreen = new ListInvitationScreen(
            $messageBusDispatcher,
            $invUseRepo,
            new GetInvitationStatusLabel($translator),
            $telegramClient,
            $translator
        );
        $handler = new FamilyInvitationsFlowHandler(
            $telegramClient,
            $messageBusDispatcher,
            $invRepo,
            $invUseRepo,
            $listScreen,
            new GetInvitationStatusLabel($translator),
            $translator,
            'test_bot'
        );

        $user = new User(123456);
        $context = new TelegramFamilyMemberContext(new TelegramUserContext($user, 'en', 123456, 111), $family);

        $update = TelegramUpdate::fromArray([
            'update_id' => 1,
            'callback_query' => ['id' => 'cb1', 'data' => 'family:invite:create', 'message' => ['chat' => ['id' => 111]]],
        ]);

        $this->assertTrue($handler->supports($update, $context));
        $handler->handle($update, $context);
    }

    public function testJoinFamilyFlowHandler(): void
    {
        $telegramClient = $this->createMock(TelegramApiClientInterface::class);
        $telegramClient->expects($this->once())->method('sendMessage');

        $invRepo = $this->createMock(InvitationRepositoryInterface::class);
        $invUseRepo = $this->createMock(InvitationUseRepositoryInterface::class);
        $familyRepo = $this->createMock(FamilyRepositoryInterface::class);
        $memberRepo = $this->createMock(FamilyMemberRepositoryInterface::class);
        $tokenGenerator = new InvitationTokenGenerator();
        $translator = $this->createTranslator();
        $mainMenuKb = new MainMenuKeyboard($translator);

        $family = Family::create('Smiths', new Timezone('UTC'), new CurrencyCode('USD'), new CurrencyCode('USD'));
        $invitation = Invitation::create(
            $family->getId(),
            UserId::generate(),
            $tokenGenerator->hashToken('valid_tok'),
            null,
            1
        );

        $messageBusDispatcher = $this->createMock(MessageBusDispatcherInterface::class);
        $messageBusDispatcher->method('dispatchCommand')->willReturn([
            'family' => $family,
            'already_member' => false,
        ]);

        $invRepo->method('findByTokenHash')->willReturn($invitation);
        $familyRepo->method('findById')->with($family->getId())->willReturn($family);
        $memberRepo->method('findByFamilyAndUser')->willReturn(null);

        $handler = new JoinFamilyFlowHandler(
            $telegramClient,
            $messageBusDispatcher,
            $invRepo,
            $invUseRepo,
            $familyRepo,
            $memberRepo,
            $tokenGenerator,
            $mainMenuKb,
            $translator
        );

        $user = new User(123456);
        $context = new TelegramUserContext($user, 'en', 123456, 111);

        $update = TelegramUpdate::fromArray([
            'update_id' => 1,
            'message' => ['message_id' => 10, 'chat' => ['id' => 111], 'text' => '/start invite_valid_tok'],
        ]);

        $this->assertTrue($handler->supports($update, $context));
        $handler->handle($update, $context);
    }

    public function testSettingsFlowHandler(): void
    {
        $messages = [];
        $telegramClient = $this->createMock(TelegramApiClientInterface::class);
        $telegramClient->expects($this->once())
            ->method('sendMessage')
            ->willReturnCallback(function ($chatId, $text, $replyMarkup) use (&$messages): array {
                $messages[] = ['text' => $text, 'reply_markup' => $replyMarkup];
                return [];
            });

        $commandBus = $this->createMock(MessageBusDispatcherInterface::class);
        $stateRepo = $this->createMock(UserInteractionStateRepository::class);
        $translator = $this->createTranslator();

        $handler = $this->createFamilySettingsFlowHandler(
            $telegramClient,
            $commandBus,
            $stateRepo,
            $translator
        );

        $user = new User(123456);
        $family = Family::create('Smiths', new Timezone('UTC'), new CurrencyCode('USD'), new CurrencyCode('USD'));
        $context = new TelegramFamilyMemberContext(new TelegramUserContext($user, 'en', 123456, 111), $family);

        $update = TelegramUpdate::fromArray([
            'update_id' => 1,
            'message' => ['message_id' => 10, 'chat' => ['id' => 111], 'text' => 'main_menu.settings'],
        ]);

        $this->assertTrue($handler->supports($update, $context));
        $handler->handle($update, $context);

        self::assertStringContainsString('settings.title', $messages[0]['text']);
        self::assertArrayHasKey('inline_keyboard', $messages[0]['reply_markup']);
    }

    public function testSettingsProviderSelectorDoesNotOfferManualAsAProvider(): void
    {
        $messages = [];
        $telegramClient = $this->createMock(TelegramApiClientInterface::class);
        $telegramClient->expects($this->once())
            ->method('sendMessage')
            ->willReturnCallback(function ($chatId, $text, $replyMarkup) use (&$messages): array {
                $messages[] = ['text' => $text, 'reply_markup' => $replyMarkup];
                return [];
            });

        $translator = $this->createTranslator();
        $handler = $this->createFamilySettingsFlowHandler(
            $telegramClient,
            $this->createMock(MessageBusDispatcherInterface::class),
            $this->createMock(UserInteractionStateRepository::class),
            $translator
        );

        $family = Family::create('Smiths', new Timezone('UTC'), new CurrencyCode('USD'), new CurrencyCode('USD'));
        $context = new TelegramFamilyMemberContext(new TelegramUserContext(new User(123456), 'en', 123456, 111), $family);
        $update = TelegramUpdate::fromArray([
            'update_id' => 1,
            'callback_query' => [
                'id' => 'cb1',
                'data' => 'settings:family:edit:provider',
                'message' => ['chat' => ['id' => 111]],
            ],
        ]);

        $handler->handle($update, $context);

        $buttons = $messages[0]['reply_markup']['inline_keyboard'];
        $callbacks = array_map(
            static fn(array $button): string => $button['callback_data'],
            array_merge(...$buttons)
        );

        self::assertSame([
            'settings:family:set:provider:nbp',
            'settings:family:set:provider:nbu',
            'settings:family:set:provider:ecb',
            'settings:family:view',
        ], $callbacks);
    }

    public function testSettingsFlowRejectsManualAsAnExchangeRateProvider(): void
    {
        $telegramClient = $this->createMock(TelegramApiClientInterface::class);
        $telegramClient->expects($this->once())
            ->method('sendMessage')
            ->with(111, 'settings.unsupported_provider', $this->anything());

        $translator = $this->createTranslator();
        $handler = $this->createFamilySettingsFlowHandler(
            $telegramClient,
            $this->createMock(MessageBusDispatcherInterface::class),
            $this->createMock(UserInteractionStateRepository::class),
            $translator
        );

        $family = Family::create('Smiths', new Timezone('UTC'), new CurrencyCode('USD'), new CurrencyCode('USD'));
        $context = new TelegramFamilyMemberContext(new TelegramUserContext(new User(123456), 'en', 123456, 111), $family);
        $update = TelegramUpdate::fromArray([
            'update_id' => 1,
            'callback_query' => [
                'id' => 'cb1',
                'data' => 'settings:family:set:provider:manual',
                'message' => ['chat' => ['id' => 111]],
            ],
        ]);

        $handler->handle($update, $context);
    }

    public function testFamilyCreationRejectsManualAsAnExchangeRateProvider(): void
    {
        $telegramClient = $this->createMock(TelegramApiClientInterface::class);
        $telegramClient->expects($this->once())
            ->method('sendMessage')
            ->with(111, 'family.create.unsupported_provider', $this->anything());

        $stateRepo = $this->createMock(UserInteractionStateRepository::class);
        $stateRepo->method('get')->willReturn(
            new UserInteractionState(123456, 'family_create', 'provider', [])
        );
        $stateRepo->expects($this->never())->method('set');

        $commandBus = $this->createMock(MessageBusDispatcherInterface::class);
        $translator = $this->createTranslator();
        $exceptionMapper = new MapExceptionToTranslatableMessage([]);
        $handler = new FamilyCreationWizardHandler(
            $telegramClient,
            $stateRepo,
            $commandBus,
            new MainMenuKeyboard($translator),
            new TimezoneSelector(new TimezoneOptions(), new InlineOptionSelector(), $translator),
            $translator,
            $exceptionMapper
        );

        $context = new TelegramUserContext(new User(123456), 'en', 123456, 111);
        $update = TelegramUpdate::fromArray([
            'update_id' => 1,
            'callback_query' => [
                'id' => 'cb1',
                'data' => 'family:create:provider:manual',
                'message' => ['chat' => ['id' => 111]],
            ],
        ]);

        $handler->handle($update, $context);
    }

    public function testLanguageSelectorUsesTheFourSupportedLanguagesWithoutSearch(): void
    {
        $messages = [];
        $telegramClient = $this->createMock(TelegramApiClientInterface::class);
        $telegramClient->expects($this->once())
            ->method('sendMessage')
            ->willReturnCallback(function ($chatId, $text, $replyMarkup) use (&$messages): array {
                $messages[] = ['text' => $text, 'reply_markup' => $replyMarkup];
                return [];
            });

        $stateRepo = $this->createMock(UserInteractionStateRepository::class);

        $handler = new SettingsFlowHandler(
            $telegramClient,
            $this->createMock(MessageBusDispatcherInterface::class),
            $stateRepo,
            $this->createTranslator(),
            $this->createTranslator(),
            new SettingsMenuRenderer($telegramClient, $this->createTranslator()),
            new MainMenuKeyboard($this->createTranslator()),
            $this->createDraftService($this->createMock(ExpenseDraftRepositoryInterface::class))
        );

        $user = new User(123456);
        $context = new TelegramUserContext($user, 'en', 123456, 111);
        $languageCallback = TelegramUpdate::fromArray([
            'update_id' => 1,
            'callback_query' => ['id' => 'cb1', 'data' => 'settings:lang:select', 'message' => ['chat' => ['id' => 111]]],
        ]);

        $handler->handle($languageCallback, $context);

        $buttons = $messages[0]['reply_markup']['inline_keyboard'];
        $this->assertCount(5, $buttons);
        $this->assertSame('settings:lang:set:en', $buttons[0][0]['callback_data']);
        $this->assertSame('settings:lang:set:uk', $buttons[1][0]['callback_data']);
        $this->assertSame('settings:lang:set:pl', $buttons[2][0]['callback_data']);
        $this->assertSame('settings:lang:set:ru', $buttons[3][0]['callback_data']);
        $this->assertSame('settings:main', $buttons[4][0]['callback_data']);
    }

    public function testChangingLanguageRefreshesMainMenuKeyboard(): void
    {
        $messages = [];
        $telegramClient = $this->createMock(TelegramApiClientInterface::class);
        $telegramClient->expects(self::once())->method('answerCallbackQuery')->with('cb1');
        $telegramClient->expects(self::exactly(2))
            ->method('sendMessage')
            ->willReturnCallback(function ($chatId, $text, $replyMarkup) use (&$messages): array {
                $messages[] = ['chat_id' => $chatId, 'text' => $text, 'reply_markup' => $replyMarkup];

                return [];
            });

        $user = new User(123456);
        $messageBusDispatcher = $this->createMock(MessageBusDispatcherInterface::class);
        $messageBusDispatcher->expects(self::once())
            ->method('dispatchCommand')
            ->with(self::callback(static fn ($command): bool => $command->language === 'uk'))
            ->willReturn(null);

        $familyA = Family::create('Smiths', new Timezone('UTC'), new CurrencyCode('USD'), new CurrencyCode('USD'));
        $familyB = Family::create('Jones', new Timezone('UTC'), new CurrencyCode('USD'), new CurrencyCode('USD'));
        $messageBusDispatcher->expects(self::once())
            ->method('dispatchQuery')
            ->with(self::callback(static fn (GetUserFamiliesQuery $query): bool => $query->userId->equals($user->getId())))
            ->willReturn([$familyA, $familyB]);

        $stateRepository = $this->createMock(UserInteractionStateRepository::class);
        $stateRepository->expects(self::once())->method('clear')->with(123456);

        $translator = $this->createTranslator();
        $localeAwareTranslator = $this->createMock(LocaleAwareInterface::class);
        $localeAwareTranslator->expects(self::once())->method('setLocale')->with('uk');
        $mainMenuKeyboard = new MainMenuKeyboard($translator);
        $handler = new SettingsFlowHandler(
            $telegramClient,
            $messageBusDispatcher,
            $stateRepository,
            $translator,
            $localeAwareTranslator,
            new SettingsMenuRenderer($telegramClient, $translator),
            $mainMenuKeyboard,
            $this->createDraftService($this->createMock(ExpenseDraftRepositoryInterface::class))
        );

        $context = new TelegramUserContext($user, 'en', 123456, 111);
        $update = TelegramUpdate::fromArray([
            'update_id' => 1,
            'callback_query' => [
                'id' => 'cb1',
                'data' => 'settings:lang:set:uk',
                'message' => ['chat' => ['id' => 111]],
            ],
        ]);

        $handler->handle($update, $context);

        self::assertSame('settings.lang_changed', $messages[0]['text']);
        self::assertSame($mainMenuKeyboard->build(true), $messages[0]['reply_markup']);
        self::assertStringStartsWith('settings.title', $messages[1]['text']);
    }

    public function testSettingsTimezoneSelectorCanSearchByIanaIdentifier(): void
    {
        $messages = [];
        $telegramClient = $this->createMock(TelegramApiClientInterface::class);
        $telegramClient->expects($this->exactly(2))
            ->method('sendMessage')
            ->willReturnCallback(function ($chatId, $text, $replyMarkup) use (&$messages): array {
                $messages[] = ['text' => $text, 'reply_markup' => $replyMarkup];
                return [];
            });

        $stateRepo = $this->createMock(UserInteractionStateRepository::class);
        $stateRepo->method('get')->willReturn(
            new UserInteractionState(123456, 'settings_timezone_selector', 'search', ['query' => '', 'page' => 0])
        );
        $stateRepo->expects($this->exactly(2))->method('set');

        $translator = $this->createTranslator();
        $handler = $this->createFamilySettingsFlowHandler(
            $telegramClient,
            $this->createMock(MessageBusDispatcherInterface::class),
            $stateRepo,
            $translator
        );

        $family = Family::create('Smiths', new Timezone('UTC'), new CurrencyCode('USD'), new CurrencyCode('USD'));
        $context = new TelegramFamilyMemberContext(new TelegramUserContext(new User(123456), 'en', 123456, 111), $family);
        $searchCallback = TelegramUpdate::fromArray([
            'update_id' => 1,
            'callback_query' => ['id' => 'cb1', 'data' => 'settings:family:timezone:search', 'message' => ['chat' => ['id' => 111]]],
        ]);
        $searchText = TelegramUpdate::fromArray([
            'update_id' => 2,
            'message' => ['message_id' => 11, 'chat' => ['id' => 111], 'text' => 'America/New_York'],
        ]);

        $handler->handle($searchCallback, $context);
        $handler->handle($searchText, $context);

        $buttons = $messages[1]['reply_markup']['inline_keyboard'];
        $this->assertSame('America/New_York', $buttons[2][0]['text']);
        $this->assertSame('settings:family:timezone:set:America/New_York', $buttons[2][0]['callback_data']);
    }

    public function testFamilyCreationTimezoneSelectorCanSearchByIanaIdentifier(): void
    {
        $messages = [];
        $telegramClient = $this->createMock(TelegramApiClientInterface::class);
        $telegramClient->expects($this->exactly(2))
            ->method('sendMessage')
            ->willReturnCallback(function ($chatId, $text, $replyMarkup) use (&$messages): array {
                $messages[] = ['text' => $text, 'reply_markup' => $replyMarkup];
                return [];
            });

        $stateRepo = $this->createMock(UserInteractionStateRepository::class);
        $stateRepo->expects($this->exactly(2))
            ->method('get')
            ->willReturnOnConsecutiveCalls(
                new UserInteractionState(123456, 'family_create', 'timezone', ['name' => 'Smiths']),
                new UserInteractionState(123456, 'family_create', 'timezone_search', ['name' => 'Smiths', 'query' => '', 'page' => 0])
            );
        $stateRepo->expects($this->exactly(2))->method('set');

        $commandBus = $this->createMock(MessageBusDispatcherInterface::class);
        $translator = $this->createTranslator();
        $exceptionMapper = new MapExceptionToTranslatableMessage([]);
        $handler = new FamilyCreationWizardHandler(
            $telegramClient,
            $stateRepo,
            $commandBus,
            new MainMenuKeyboard($translator),
            new TimezoneSelector(new TimezoneOptions(), new InlineOptionSelector(), $translator),
            $translator,
            $exceptionMapper
        );

        $context = new TelegramUserContext(new User(123456), 'en', 123456, 111);
        $searchCallback = TelegramUpdate::fromArray([
            'update_id' => 1,
            'callback_query' => ['id' => 'cb1', 'data' => 'family:create:timezone:search', 'message' => ['chat' => ['id' => 111]]],
        ]);
        $searchText = TelegramUpdate::fromArray([
            'update_id' => 2,
            'message' => ['message_id' => 11, 'chat' => ['id' => 111], 'text' => 'America/New_York'],
        ]);

        $handler->handle($searchCallback, $context);
        $handler->handle($searchText, $context);

        $buttons = $messages[1]['reply_markup']['inline_keyboard'];
        $this->assertSame('America/New_York', $buttons[2][0]['text']);
        $this->assertSame('family:create:timezone:set:America/New_York', $buttons[2][0]['callback_data']);
    }

    private function createExpenseDraftEntry(
        TelegramApiClientInterface $telegramClient,
        ExpenseDraftRepositoryInterface $draftRepository,
        TranslatorInterface $translator
    ): ExpenseDraftEntry {
        $draftService = $this->createDraftService($draftRepository);
        $callbackData = new ExpenseDraftCallbackData();
        $screen = new ExpenseDraftScreen(
            new ExpenseCardRenderer($telegramClient, $translator, new MoneyAmountFormatter()),
            $callbackData,
            $telegramClient,
            $translator,
            $this->createMock(MessageBusDispatcherInterface::class),
            new MoneyAmountFormatter()
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

    private function createFamilySettingsFlowHandler(
        TelegramApiClientInterface&MockObject $telegramClient,
        MessageBusDispatcherInterface&MockObject $messageBus,
        UserInteractionStateRepository&MockObject $stateRepo,
        LocaleAwareInterface&TranslatorInterface $translator
    ): FamilySettingsFlowHandler {
        $exceptionMapper = new MapExceptionToTranslatableMessage([]);
        $familySettingsRenderer = new FamilySettingsRenderer($telegramClient, $translator);

        return new FamilySettingsFlowHandler(
            $telegramClient,
            new FamilySettingsNameStep(
                $telegramClient,
                $messageBus,
                $stateRepo,
                $translator,
                $exceptionMapper,
                $familySettingsRenderer
            ),
            new FamilySettingsTimezoneStep(
                $telegramClient,
                $messageBus,
                $stateRepo,
                new TimezoneSelector(new TimezoneOptions(), new InlineOptionSelector(), $translator),
                $translator
            ),
            new FamilySettingsCurrencyStep(
                $telegramClient,
                $messageBus,
                $translator,
                $familySettingsRenderer
            ),
            new FamilySettingsProviderStep(
                $telegramClient,
                $messageBus,
                $translator,
                $familySettingsRenderer
            ),
            $stateRepo,
            $translator,
            $familySettingsRenderer,
            new SettingsMenuRenderer($telegramClient, $translator)
        );
    }
}

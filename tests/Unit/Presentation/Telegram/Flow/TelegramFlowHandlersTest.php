<?php

namespace App\Tests\Unit\Presentation\Telegram\Flow;

use App\Core\Domain\ValueObject\CurrencyCode;
use App\Core\Domain\ValueObject\Timezone;
use App\Core\Domain\ValueObject\UserId;
use App\Core\Infrastructure\Persistence\Redis\UserInteractionState;
use App\Core\Infrastructure\Persistence\Redis\UserInteractionStateRepository;
use App\Family\Application\Command\CreateFamilyHandler;
use App\Family\Application\Command\CreateInvitationHandler;
use App\Family\Application\Command\JoinFamilyByInvitationHandler;
use App\Family\Application\Command\RevokeInvitationHandler;
use App\Family\Application\Command\UpdateFamilySettingsHandler;
use App\Family\Application\Query\GetUserFamiliesQuery;
use App\Family\Application\Query\GetFamilyInvitationsHandler;
use App\Family\Application\Query\GetFamilyMembersHandler;
use App\Family\Application\Query\GetUserFamiliesHandler;
use App\Family\Domain\Entity\Family;
use App\Family\Domain\Entity\Invitation;
use App\Family\Domain\Repository\FamilyMemberRepositoryInterface;
use App\Family\Domain\Repository\FamilyRepositoryInterface;
use App\Family\Domain\Repository\InvitationRepositoryInterface;
use App\Family\Domain\Repository\InvitationUseRepositoryInterface;
use App\Family\Infrastructure\Security\InvitationTokenGenerator;
use App\Identity\Application\Command\ChangeUserLanguageHandler;
use App\Identity\Application\Command\SwitchActiveFamilyHandler;
use App\Identity\Domain\Entity\User;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Flow\FamilyCreationWizardHandler;
use App\Presentation\Telegram\Flow\FamilyInvitationsFlowHandler;
use App\Presentation\Telegram\Flow\FamilyMembersFlowHandler;
use App\Presentation\Telegram\Flow\FamilyOnboardingFlowHandler;
use App\Presentation\Telegram\Flow\FamilySettingsFlowHandler;
use App\Presentation\Telegram\Flow\FamilySelectorFlowHandler;
use App\Presentation\Telegram\Flow\JoinFamilyFlowHandler;
use App\Presentation\Telegram\Flow\MainMenuFlowHandler;
use App\Presentation\Telegram\Flow\SettingsMenuRenderer;
use App\Presentation\Telegram\MapExceptionToTranslationKey;
use App\Presentation\Telegram\Flow\SettingsFlowHandler;
use App\Presentation\Telegram\Keyboard\MainMenuKeyboard;
use App\Presentation\Telegram\Keyboard\InlineOptionSelector;
use App\Presentation\Telegram\Keyboard\TimezoneOptions;
use App\Presentation\Telegram\Keyboard\TimezoneSelector;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
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

        $handler = new MainMenuFlowHandler($telegramClient, $mainMenuKb, $memberRepo, $translator);

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
        $familyRepo = $this->createMock(FamilyRepositoryInterface::class);
        $memberRepo = $this->createMock(FamilyMemberRepositoryInterface::class);
        $createFamilyHandler = new CreateFamilyHandler($familyRepo, $memberRepo);
        $translator = $this->createTranslator();
        $mainMenuKb = new MainMenuKeyboard($translator);

        $exceptionMapper = new MapExceptionToTranslationKey();
        $handler = new FamilyCreationWizardHandler(
            $telegramClient,
            $stateRepo,
            $createFamilyHandler,
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

        $getUserFamiliesHandler = $this->createMock(GetUserFamiliesHandler::class);
        $getUserFamiliesHandler->method('__invoke')->willReturn([]);

        $switchHandler = $this->createMock(SwitchActiveFamilyHandler::class);
        $translator = $this->createTranslator();
        $mainMenuKb = new MainMenuKeyboard($translator);

        $handler = new FamilySelectorFlowHandler(
            $telegramClient,
            $getUserFamiliesHandler,
            $switchHandler,
            $mainMenuKb,
            $translator
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
            ->with(111, 'family.selector.title', self::anything());

        $getUserFamiliesHandler = $this->createMock(GetUserFamiliesHandler::class);
        $getUserFamiliesHandler->expects(self::once())->method('__invoke')->willReturn([]);

        $translator = $this->createTranslator();
        $handler = new FamilySelectorFlowHandler(
            $telegramClient,
            $getUserFamiliesHandler,
            $this->createMock(SwitchActiveFamilyHandler::class),
            new MainMenuKeyboard($translator),
            $translator
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

        $family = Family::create('Smiths', new Timezone('UTC'), new CurrencyCode('USD'), new CurrencyCode('USD'));
        $familyContext = new TelegramFamilyMemberContext($context, $family);
        self::assertFalse($handler->supports($update, $familyContext));
    }

    public function testFamilyMembersFlowHandler(): void
    {
        $telegramClient = $this->createMock(TelegramApiClientInterface::class);
        $telegramClient->expects($this->once())->method('sendMessage');

        $getMembersHandler = $this->createMock(GetFamilyMembersHandler::class);
        $getMembersHandler->method('__invoke')->willReturn([]);

        $translator = $this->createTranslator();

        $handler = new FamilyMembersFlowHandler($telegramClient, $getMembersHandler, $translator);

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

        $createInvHandler = $this->createMock(CreateInvitationHandler::class);
        $family = Family::create('Smiths', new Timezone('UTC'), new CurrencyCode('USD'), new CurrencyCode('USD'));
        $invitation = Invitation::create($family->getId(), UserId::generate(), 'hash', null, 1);

        $createInvHandler->method('__invoke')->willReturn([
            'raw_token' => 'plain_123',
            'invitation' => $invitation,
        ]);

        $revokeInvHandler = $this->createMock(RevokeInvitationHandler::class);
        $getInvHandler = $this->createMock(GetFamilyInvitationsHandler::class);
        $invRepo = $this->createMock(InvitationRepositoryInterface::class);
        $invUseRepo = $this->createMock(InvitationUseRepositoryInterface::class);
        $translator = $this->createTranslator();

        $handler = new FamilyInvitationsFlowHandler(
            $telegramClient,
            $createInvHandler,
            $revokeInvHandler,
            $getInvHandler,
            $invRepo,
            $invUseRepo,
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

        $joinHandler = $this->createMock(JoinFamilyByInvitationHandler::class);
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

        $joinHandler->method('__invoke')->willReturn([
            'family' => $family,
            'already_member' => false,
        ]);

        $invRepo->method('findByTokenHash')->willReturn($invitation);
        $familyRepo->method('findById')->with($family->getId())->willReturn($family);
        $memberRepo->method('findByFamilyAndUser')->willReturn(null);

        $handler = new JoinFamilyFlowHandler(
            $telegramClient,
            $joinHandler,
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

        $updateFamilySettingsHandler = $this->createMock(UpdateFamilySettingsHandler::class);
        $stateRepo = $this->createMock(UserInteractionStateRepository::class);
        $translator = $this->createTranslator();

        $exceptionMapper = new MapExceptionToTranslationKey();
        $handler = new FamilySettingsFlowHandler(
            $telegramClient,
            $updateFamilySettingsHandler,
            $stateRepo,
            new TimezoneSelector(new TimezoneOptions(), new InlineOptionSelector(), $translator),
            $translator,
            new SettingsMenuRenderer($telegramClient, $translator),
            $exceptionMapper
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
        $exceptionMapper = new MapExceptionToTranslationKey();
        $handler = new FamilySettingsFlowHandler(
            $telegramClient,
            $this->createMock(UpdateFamilySettingsHandler::class),
            $this->createMock(UserInteractionStateRepository::class),
            new TimezoneSelector(new TimezoneOptions(), new InlineOptionSelector(), $translator),
            $translator,
            new SettingsMenuRenderer($telegramClient, $translator),
            $exceptionMapper
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

        $updateFamilySettingsHandler = $this->createMock(UpdateFamilySettingsHandler::class);
        $updateFamilySettingsHandler->expects($this->never())->method('__invoke');

        $translator = $this->createTranslator();
        $exceptionMapper = new MapExceptionToTranslationKey();
        $handler = new FamilySettingsFlowHandler(
            $telegramClient,
            $updateFamilySettingsHandler,
            $this->createMock(UserInteractionStateRepository::class),
            new TimezoneSelector(new TimezoneOptions(), new InlineOptionSelector(), $translator),
            $translator,
            new SettingsMenuRenderer($telegramClient, $translator),
            $exceptionMapper
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

        $familyRepo = $this->createMock(FamilyRepositoryInterface::class);
        $memberRepo = $this->createMock(FamilyMemberRepositoryInterface::class);
        $createFamilyHandler = new CreateFamilyHandler($familyRepo, $memberRepo);
        $translator = $this->createTranslator();
        $exceptionMapper = new MapExceptionToTranslationKey();
        $handler = new FamilyCreationWizardHandler(
            $telegramClient,
            $stateRepo,
            $createFamilyHandler,
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
            $this->createMock(ChangeUserLanguageHandler::class),
            $this->createMock(GetUserFamiliesHandler::class),
            $stateRepo,
            $this->createTranslator(),
            $this->createTranslator(),
            new SettingsMenuRenderer($telegramClient, $this->createTranslator()),
            new MainMenuKeyboard($this->createTranslator())
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
        $changeLanguageHandler = $this->createMock(ChangeUserLanguageHandler::class);
        $changeLanguageHandler->expects(self::once())
            ->method('__invoke')
            ->with(self::callback(static fn ($command): bool => $command->language === 'uk'));

        $familyA = Family::create('Smiths', new Timezone('UTC'), new CurrencyCode('USD'), new CurrencyCode('USD'));
        $familyB = Family::create('Jones', new Timezone('UTC'), new CurrencyCode('USD'), new CurrencyCode('USD'));
        $getUserFamiliesHandler = $this->createMock(GetUserFamiliesHandler::class);
        $getUserFamiliesHandler->expects(self::once())
            ->method('__invoke')
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
            $changeLanguageHandler,
            $getUserFamiliesHandler,
            $stateRepository,
            $translator,
            $localeAwareTranslator,
            new SettingsMenuRenderer($telegramClient, $translator),
            $mainMenuKeyboard
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
        $exceptionMapper = new MapExceptionToTranslationKey();
        $handler = new FamilySettingsFlowHandler(
            $telegramClient,
            $this->createMock(UpdateFamilySettingsHandler::class),
            $stateRepo,
            new TimezoneSelector(new TimezoneOptions(), new InlineOptionSelector(), $translator),
            $translator,
            new SettingsMenuRenderer($telegramClient, $translator),
            $exceptionMapper
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

        $familyRepo = $this->createMock(FamilyRepositoryInterface::class);
        $memberRepo = $this->createMock(FamilyMemberRepositoryInterface::class);
        $createFamilyHandler = new CreateFamilyHandler($familyRepo, $memberRepo);
        $translator = $this->createTranslator();
        $exceptionMapper = new MapExceptionToTranslationKey();
        $handler = new FamilyCreationWizardHandler(
            $telegramClient,
            $stateRepo,
            $createFamilyHandler,
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
}

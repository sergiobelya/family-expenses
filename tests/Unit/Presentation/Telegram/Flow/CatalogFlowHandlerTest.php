<?php

namespace App\Tests\Unit\Presentation\Telegram\Flow;

use App\Catalog\Application\CatalogAccess;
use App\Catalog\Application\FamilyCatalogLockInterface;
use App\Catalog\Application\Query\CatalogSearchInterface;
use App\Catalog\Domain\Repository\CatalogRepositoryInterface;
use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Entity\Store;
use App\Catalog\Domain\ValueObject\StoreName;
use App\Core\Domain\ValueObject\{
    CurrencyCode,
    Timezone,
    UserId
};
use App\Core\Infrastructure\Persistence\Redis\UserInteractionStateRepository;
use App\Family\Domain\Entity\Family;
use App\Family\Domain\Repository\FamilyMemberRepositoryInterface;
use App\Identity\Domain\Entity\User;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Flow\Catalog\CatalogEntryScreen;
use App\Presentation\Telegram\Flow\Catalog\CatalogFlowUtils;
use App\Presentation\Telegram\Flow\Catalog\StoreAliasScreen;
use App\Presentation\Telegram\Flow\Catalog\StoreDefaultCategoryScreen;
use App\Presentation\Telegram\Flow\Catalog\StoreEntryScreen;
use App\Presentation\Telegram\Flow\CatalogFlowHandler;
use App\Presentation\Telegram\Flow\MainMenuFlowHandler;
use App\Presentation\Telegram\Keyboard\InlineOptionSelector;
use App\Presentation\Telegram\Keyboard\MainMenuKeyboard;
use App\Presentation\Telegram\MapExceptionToTranslationKey;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class CatalogFlowHandlerTest extends TestCase
{
    private function createHandler(
        CatalogRepositoryInterface $repository,
        FamilyMemberRepositoryInterface $members,
        UserInteractionStateRepository $states,
        TelegramApiClientInterface $telegram,
        TranslatorInterface $translator
    ): CatalogFlowHandler {
        $lock = $this->createMock(FamilyCatalogLockInterface::class);
        $access = new CatalogAccess($members, $lock);
        $commandBus = $this->createMock(MessageBusInterface::class);
        $exceptionMapper = new MapExceptionToTranslationKey();
        $optionSelector = new InlineOptionSelector();
        $search = $this->createMock(CatalogSearchInterface::class);
        $search->method('stores')->willReturn([]);
        $search->method('categories')->willReturn([]);

        $utils = new CatalogFlowUtils($telegram, $states, $commandBus, $translator);
        $storeScreen = $this->createMock(CatalogEntryScreen::class);
        $categoryScreen = $this->createMock(CatalogEntryScreen::class);
        $realStoreScreen = new StoreEntryScreen($utils, $repository, $search, $exceptionMapper);
        $aliasScreen = new StoreAliasScreen($utils, $realStoreScreen, $repository, $exceptionMapper);
        $defaultCategoryScreen = new StoreDefaultCategoryScreen($utils, $realStoreScreen, $repository, $optionSelector);

        return new CatalogFlowHandler(
            $utils,
            $storeScreen,
            $categoryScreen,
            $aliasScreen,
            $defaultCategoryScreen,
            $access,
            $states,
            $telegram,
        );
    }

    public function testAliasConflictEditWithMissingStateDoesNotReferenceUndefinedStoreId(): void
    {
        $family = Family::create(
            'Smiths',
            Timezone::fromString('UTC'),
            CurrencyCode::fromString('USD'),
            CurrencyCode::fromString('USD')
        );
        $user = new User(123456);
        $repository = $this->createMock(CatalogRepositoryInterface::class);
        $members = $this->createMock(FamilyMemberRepositoryInterface::class);
        $members->expects(self::once())
            ->method('isMember')
            ->with($family->getId(), $user->getId())
            ->willReturn(true);
        $states = $this->createMock(UserInteractionStateRepository::class);
        $states->expects(self::once())->method('get')->willReturn(null);
        $telegram = $this->createMock(TelegramApiClientInterface::class);
        $telegram->expects(self::once())->method('answerCallbackQuery')->with('callback-id');
        $telegram->expects(self::never())->method('sendMessage');
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key): string => $key);
        $handler = $this->createHandler($repository, $members, $states, $telegram, $translator);
        $context = new TelegramFamilyMemberContext(new TelegramUserContext($user, 'en', 123456, 111), $family);
        $update = TelegramUpdate::fromArray([
            'update_id' => 4,
            'callback_query' => [
                'id' => 'callback-id',
                'from' => ['id' => 123456],
                'message' => ['message_id' => 9, 'chat' => ['id' => 111]],
                'data' => 'catalog:alias:conflict:edit',
            ],
        ]);

        self::assertTrue($handler->supports($update, $context));
        $handler->handle($update, $context);
    }

    public function testDefaultCategoryPickerUsesSearchableInlineSelector(): void
    {
        $family = Family::create(
            'Smiths',
            Timezone::fromString('UTC'),
            CurrencyCode::fromString('USD'),
            CurrencyCode::fromString('USD')
        );
        $user = new User(123456);
        $userId = UserId::generate();
        $store = Store::create($family->getId(), StoreName::fromString('Market'), $userId);
        $category = Category::create($family->getId(), 'Groceries', $userId);
        $repository = $this->createMock(CatalogRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('findStore')
            ->with($family->getId(), $store->getId())
            ->willReturn($store);
        $repository->expects(self::once())
            ->method('findCategories')
            ->with($family->getId())
            ->willReturn([$category]);
        $members = $this->createMock(FamilyMemberRepositoryInterface::class);
        $members->expects(self::once())
            ->method('isMember')
            ->with($family->getId(), $user->getId())
            ->willReturn(true);
        $states = $this->createMock(UserInteractionStateRepository::class);
        $states->method('get')->willReturn(null);
        $states->expects(self::once())->method('set');
        $telegram = $this->createMock(TelegramApiClientInterface::class);
        $telegram->expects(self::once())->method('answerCallbackQuery')->with('callback-id');
        $markup = null;
        $telegram->expects(self::once())
            ->method('sendMessage')
            ->willReturnCallback(static function (int $chatId, string $text, array $keyboard) use (&$markup): array {
                $markup = $keyboard;

                return ['ok' => true, 'result' => ['message_id' => 10]];
            });
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key): string => $key);
        $handler = $this->createHandler($repository, $members, $states, $telegram, $translator);
        $context = new TelegramFamilyMemberContext(new TelegramUserContext($user, 'en', 123456, 111), $family);
        $update = TelegramUpdate::fromArray([
            'update_id' => 3,
            'callback_query' => [
                'id' => 'callback-id',
                'from' => ['id' => 123456],
                'message' => ['message_id' => 9, 'chat' => ['id' => 111]],
                'data' => 'catalog:def-cat:open:' . $store->getId(),
            ],
        ]);

        self::assertTrue($handler->supports($update, $context));
        $handler->handle($update, $context);

        self::assertIsArray($markup);
        $callbackData = array_column(
            array_merge(...($markup['inline_keyboard'] ?? [])),
            'callback_data'
        );
        self::assertContains('catalog:def-cat:search', $callbackData);
        self::assertContains('catalog:def-cat:set:none', $callbackData);
        self::assertContains('catalog:def-cat:set:' . $category->getId(), $callbackData);
    }

    public function testStoreMenuOpensFamilyScopedStoreList(): void
    {
        $family = Family::create(
            'Smiths',
            Timezone::fromString('UTC'),
            CurrencyCode::fromString('USD'),
            CurrencyCode::fromString('USD')
        );
        $user = new User(123456);
        $repository = $this->createMock(CatalogRepositoryInterface::class);

        $members = $this->createMock(FamilyMemberRepositoryInterface::class);
        $members->expects(self::once())->method('isMember')->with(
            $family->getId(),
            $user->getId()
        )->willReturn(true);
        $stateRepository = $this->createMock(UserInteractionStateRepository::class);
        $stateRepository->method('get')->willReturn(null);
        $telegram = $this->createMock(TelegramApiClientInterface::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key): string => $key);

        $lock = $this->createMock(FamilyCatalogLockInterface::class);
        $access = new CatalogAccess($members, $lock);
        $commandBus = $this->createMock(MessageBusInterface::class);
        $exceptionMapper = new MapExceptionToTranslationKey();
        $optionSelector = new InlineOptionSelector();
        $search = $this->createMock(CatalogSearchInterface::class);
        $search->method('stores')->willReturn([]);
        $search->method('categories')->willReturn([]);

        $utils = new CatalogFlowUtils($telegram, $stateRepository, $commandBus, $translator);
        $storeScreen = $this->createMock(CatalogEntryScreen::class);
        $storeScreen->expects(self::once())->method('renderList')->with($this->isInstanceOf(TelegramFamilyMemberContext::class), 0);
        $categoryScreen = $this->createMock(CatalogEntryScreen::class);
        $realStoreScreen = new StoreEntryScreen($utils, $repository, $search, $exceptionMapper);
        $aliasScreen = new StoreAliasScreen($utils, $realStoreScreen, $repository, $exceptionMapper);
        $defaultCategoryScreen = new StoreDefaultCategoryScreen($utils, $realStoreScreen, $repository, $optionSelector);

        $handler = new CatalogFlowHandler(
            $utils,
            $storeScreen,
            $categoryScreen,
            $aliasScreen,
            $defaultCategoryScreen,
            $access,
            $stateRepository,
            $telegram,
        );
        $context = new TelegramFamilyMemberContext(new TelegramUserContext($user, 'en', 123456, 111), $family);
        $update = TelegramUpdate::fromArray([
            'update_id' => 1,
            'message' => ['message_id' => 9, 'chat' => ['id' => 111], 'text' => 'main_menu.stores'],
        ]);

        self::assertTrue($handler->supports($update, $context));
        $handler->handle($update, $context);
    }

    public function testMenuButtonIsNotClaimedByMainMenuPlaceholder(): void
    {
        $family = Family::create(
            'Smiths',
            Timezone::fromString('UTC'),
            CurrencyCode::fromString('USD'),
            CurrencyCode::fromString('USD')
        );
        $user = new User(123456);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key): string => $key);
        $members = $this->createMock(FamilyMemberRepositoryInterface::class);
        $handler = new MainMenuFlowHandler(
            $this->createMock(TelegramApiClientInterface::class),
            new MainMenuKeyboard($translator),
            $members,
            $translator
        );
        $context = new TelegramFamilyMemberContext(new TelegramUserContext($user, 'en', 123456, 111), $family);
        $update = TelegramUpdate::fromArray([
            'update_id' => 2,
            'message' => ['message_id' => 10, 'chat' => ['id' => 111], 'text' => 'main_menu.stores'],
        ]);

        self::assertFalse($handler->supports($update, $context));
    }
}

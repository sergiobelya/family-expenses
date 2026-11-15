<?php

namespace App\Tests\Unit\Presentation\Telegram\Flow;

use App\Catalog\Application\CatalogAccess;
use App\Catalog\Application\FamilyCatalogLockInterface;
use App\Catalog\Application\Query\CatalogSearch;
use App\Catalog\Domain\Repository\CatalogRepositoryInterface;
use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Entity\Store;
use App\Core\Infrastructure\Persistence\Redis\UserInteractionStateRepository;
use App\Family\Domain\Entity\Family;
use App\Family\Domain\Repository\FamilyMemberRepositoryInterface;
use App\Identity\Domain\Entity\User;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Flow\CatalogFlowHandler;
use App\Presentation\Telegram\Keyboard\InlineOptionSelector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

final class CatalogFlowHandlerTest extends TestCase
{
    public function testAliasConflictEditWithMissingStateDoesNotReferenceUndefinedStoreId(): void
    {
        $family = Family::create(
            'Smiths',
            \App\Core\Domain\ValueObject\Timezone::fromString('UTC'),
            \App\Core\Domain\ValueObject\CurrencyCode::fromString('USD'),
            \App\Core\Domain\ValueObject\CurrencyCode::fromString('USD')
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
        $lock = $this->createMock(FamilyCatalogLockInterface::class);
        $handler = new CatalogFlowHandler(
            $telegram,
            $states,
            $repository,
            new CatalogAccess($members, $lock),
            new CatalogSearch($repository),
            new InlineOptionSelector(),
            $this->createMock(MessageBusInterface::class),
            $translator
        );
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
            \App\Core\Domain\ValueObject\Timezone::fromString('UTC'),
            \App\Core\Domain\ValueObject\CurrencyCode::fromString('USD'),
            \App\Core\Domain\ValueObject\CurrencyCode::fromString('USD')
        );
        $user = new User(123456);
        $userId = \App\Core\Domain\ValueObject\UserId::generate();
        $store = Store::create($family->getId(), 'Market', $userId);
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
        $lock = $this->createMock(FamilyCatalogLockInterface::class);
        $handler = new CatalogFlowHandler(
            $telegram,
            $states,
            $repository,
            new CatalogAccess($members, $lock),
            new CatalogSearch($repository),
            new InlineOptionSelector(),
            $this->createMock(MessageBusInterface::class),
            $translator
        );
        $context = new TelegramFamilyMemberContext(new TelegramUserContext($user, 'en', 123456, 111), $family);
        $update = TelegramUpdate::fromArray([
            'update_id' => 3,
            'callback_query' => [
                'id' => 'callback-id',
                'from' => ['id' => 123456],
                'message' => ['message_id' => 9, 'chat' => ['id' => 111]],
                'data' => 'catalog:default:' . $store->getId(),
            ],
        ]);

        self::assertTrue($handler->supports($update, $context));
        $handler->handle($update, $context);

        self::assertIsArray($markup);
        $callbackData = array_column(
            array_merge(...($markup['inline_keyboard'] ?? [])),
            'callback_data'
        );
        self::assertContains('catdef:search', $callbackData);
        self::assertContains('catdef:set:none', $callbackData);
        self::assertContains('catdef:set:' . $category->getId(), $callbackData);
    }

    public function testStoreMenuOpensFamilyScopedStoreList(): void
    {
        $family = Family::create(
            'Smiths',
            \App\Core\Domain\ValueObject\Timezone::fromString('UTC'),
            \App\Core\Domain\ValueObject\CurrencyCode::fromString('USD'),
            \App\Core\Domain\ValueObject\CurrencyCode::fromString('USD')
        );
        $user = new User(123456);
        $repository = $this->createMock(CatalogRepositoryInterface::class);
        $repository->expects(self::exactly(2))->method('findStores')->with($family->getId())->willReturn([]);

        $members = $this->createMock(FamilyMemberRepositoryInterface::class);
        $members->expects(self::once())->method('isMember')->with(
            $family->getId(),
            $user->getId()
        )->willReturn(true);
        $stateRepository = $this->createMock(UserInteractionStateRepository::class);
        $stateRepository->expects(self::once())->method('set');
        $telegram = $this->createMock(TelegramApiClientInterface::class);
        $telegram->expects(self::once())->method('sendMessage')->with(
            111,
            self::stringContains('catalog.stores_title'),
            self::isType('array')
        )->willReturn(['ok' => true, 'result' => ['message_id' => 10]]);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key): string => $key);

        $lock = $this->createMock(FamilyCatalogLockInterface::class);
        $handler = new CatalogFlowHandler(
            $telegram,
            $stateRepository,
            $repository,
            new CatalogAccess($members, $lock),
            new CatalogSearch($repository),
            new InlineOptionSelector(),
            $this->createMock(MessageBusInterface::class),
            $translator
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
            \App\Core\Domain\ValueObject\Timezone::fromString('UTC'),
            \App\Core\Domain\ValueObject\CurrencyCode::fromString('USD'),
            \App\Core\Domain\ValueObject\CurrencyCode::fromString('USD')
        );
        $user = new User(123456);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key): string => $key);
        $members = $this->createMock(FamilyMemberRepositoryInterface::class);
        $handler = new \App\Presentation\Telegram\Flow\MainMenuFlowHandler(
            $this->createMock(TelegramApiClientInterface::class),
            new \App\Presentation\Telegram\Keyboard\MainMenuKeyboard($translator),
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

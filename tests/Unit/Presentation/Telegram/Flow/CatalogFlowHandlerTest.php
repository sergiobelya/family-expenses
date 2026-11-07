<?php

namespace App\Tests\Unit\Presentation\Telegram\Flow;

use App\Catalog\Application\CatalogAccess;
use App\Catalog\Application\Query\CatalogSearch;
use App\Catalog\Domain\Repository\CatalogRepositoryInterface;
use App\Core\Infrastructure\Persistence\Redis\UserInteractionStateRepository;
use App\Family\Domain\Entity\Family;
use App\Family\Domain\Repository\FamilyMemberRepositoryInterface;
use App\Identity\Domain\Entity\User;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Flow\CatalogFlowHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

final class CatalogFlowHandlerTest extends TestCase
{
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

        $handler = new CatalogFlowHandler(
            $telegram,
            $stateRepository,
            $repository,
            new CatalogAccess($members, $repository),
            new CatalogSearch($repository),
            $this->createMock(MessageBusInterface::class),
            $translator
        );
        $context = new TelegramUserContext($user, $family, 'en', 123456, 111);
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
        $context = new TelegramUserContext($user, $family, 'en', 123456, 111);
        $update = TelegramUpdate::fromArray([
            'update_id' => 2,
            'message' => ['message_id' => 10, 'chat' => ['id' => 111], 'text' => 'main_menu.stores'],
        ]);

        self::assertFalse($handler->supports($update, $context));
    }
}

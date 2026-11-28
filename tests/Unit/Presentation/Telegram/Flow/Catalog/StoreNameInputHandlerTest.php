<?php

namespace App\Tests\Unit\Presentation\Telegram\Flow\Catalog;

use App\Catalog\Application\Command\CreateStoreCommand;
use App\Catalog\Application\Command\RenameStoreCommand;
use App\Catalog\Application\Query\CatalogSearchInterface;
use App\Catalog\Domain\Entity\Store;
use App\Catalog\Domain\Repository\CatalogRepositoryInterface;
use App\Catalog\Domain\ValueObject\CatalogName;
use App\Catalog\Domain\ValueObject\StoreName;
use App\Core\Domain\ValueObject\CurrencyCode;
use App\Core\Domain\ValueObject\Timezone;
use App\Core\Infrastructure\Messenger\MessageBusDispatcherInterface;
use App\Core\Infrastructure\Persistence\Redis\UserInteractionStateRepository;
use App\Family\Domain\Entity\Family;
use App\Identity\Domain\Entity\User;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\Flow\Catalog\CatalogFlowUtils;
use App\Presentation\Telegram\Flow\Catalog\StoreNameInputHandler;
use App\Presentation\Telegram\MapExceptionToTranslatableMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

final class StoreNameInputHandlerTest extends TestCase
{
    public function testConfirmedSimilarStoreNameIsCreatedWithoutRepeatingSimilaritySearch(): void
    {
        $this->assertConfirmedNameIsSaved('create');
    }

    public function testConfirmedSimilarStoreRenameIsSavedWithoutRepeatingSimilaritySearch(): void
    {
        $this->assertConfirmedNameIsSaved('rename');
    }

    private function assertConfirmedNameIsSaved(string $operation): void
    {
        $family = Family::create(
            'Smiths',
            Timezone::fromString('UTC'),
            CurrencyCode::fromString('USD'),
            CurrencyCode::fromString('USD')
        );
        $user = new User(123456);
        $context = new TelegramFamilyMemberContext(
            new TelegramUserContext($user, 'en', 123456, 111),
            $family
        );
        $name = 'Apteka Galla';
        $nameValue = CatalogName::fromString($name);
        $storeId = Store::create($family->getId(), StoreName::fromString('Old name'), $user->getId())->getId();
        $similarStore = Store::create(
            $family->getId(),
            StoreName::fromString('Apteka Galla Market'),
            $user->getId()
        );
        $catalog = $this->createMock(CatalogRepositoryInterface::class);
        $catalog->expects(self::once())
            ->method('findStoreByDedupeKey')
            ->with($family->getId(), $nameValue->getDedupeKey(), $operation === 'rename' ? $storeId : null)
            ->willReturn(null);
        $catalog->expects(self::once())
            ->method('findStoreAliasByDedupeKey')
            ->with($family->getId(), $nameValue->getDedupeKey())
            ->willReturn(null);
        $search = $this->createMock(CatalogSearchInterface::class);
        $search->expects(self::never())->method('stores')->willReturn([$similarStore]);
        $savedStore = Store::create($family->getId(), StoreName::fromString($name), $user->getId());
        $commandBus = $this->createMock(MessageBusDispatcherInterface::class);
        $commandClass = $operation === 'rename' ? RenameStoreCommand::class : CreateStoreCommand::class;
        $commandBus->expects(self::once())
            ->method('dispatchCommand')
            ->with(self::callback(static fn (object $command): bool => $command instanceof $commandClass
                && $command->name === $name))
            ->willReturn($savedStore);
        $utils = new CatalogFlowUtils(
            $this->createMock(TelegramApiClientInterface::class),
            $this->createMock(UserInteractionStateRepository::class),
            $commandBus,
            $this->createMock(TranslatorInterface::class)
        );
        $handler = new StoreNameInputHandler(
            $utils,
            $catalog,
            $search,
            new MapExceptionToTranslatableMessage([]),
            'catalog:list:store'
        );
        $data = ['type' => 'store', 'operation' => $operation, 'value' => $name];
        if ($operation === 'rename') {
            $data['id'] = (string) $storeId;
        }
        $renderedStoreId = null;

        $handler->process(
            $context,
            $data,
            $name,
            static function (): void {},
            static function (TelegramFamilyMemberContext $context, Uuid $id) use (&$renderedStoreId): void {
                $renderedStoreId = $id;
            },
            true
        );

        self::assertSame($savedStore->getId()->toUuid(), $renderedStoreId);
    }
}

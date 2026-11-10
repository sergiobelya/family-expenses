<?php

namespace App\Catalog\Application\Command;

use App\Catalog\Application\CatalogAccess;
use App\Catalog\Domain\Entity\StoreAlias;
use App\Catalog\Domain\Exception\CatalogEntryNotFoundException;
use App\Catalog\Domain\Exception\CatalogNameConflictException;
use App\Catalog\Domain\Repository\StoreCatalogRepositoryInterface;
use App\Catalog\Domain\ValueObject\StoreName;
use App\Core\Domain\ValueObject\FamilyId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class AddStoreAliasHandler
{
    public function __construct(
        private StoreCatalogRepositoryInterface $catalog,
        private CatalogAccess $access
    ) {
    }

    public function __invoke(AddStoreAliasCommand $command): StoreAlias
    {
        $name = StoreName::fromString($command->alias);
        $this->access->lockFamilyForMemberMutation($command->familyId, $command->createdByUserId);
        $familyId = $command->familyId;
        $store = $this->catalog->findStore($familyId, $command->storeId);
        if ($store === null) {
            throw CatalogEntryNotFoundException::forEntry('store', (string) $command->storeId);
        }

        $this->assertAliasNameAvailable($familyId, $name->dedupeKey);
        $alias = StoreAlias::create(
            $familyId,
            $store->getId(),
            $name->value,
            $command->createdByUserId
        );
        $this->catalog->saveStoreAlias($alias);

        return $alias;
    }

    private function assertAliasNameAvailable(FamilyId $familyId, string $dedupeKey): void
    {
        if ($store = $this->catalog->findStoreByDedupeKey($familyId, $dedupeKey)) {
            throw new CatalogNameConflictException('store', $store->getName());
        }
        if ($alias = $this->catalog->findStoreAliasByDedupeKey($familyId, $dedupeKey)) {
            throw new CatalogNameConflictException('store alias', $alias->getAlias());
        }
    }
}

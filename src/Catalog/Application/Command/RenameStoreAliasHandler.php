<?php

namespace App\Catalog\Application\Command;

use App\Catalog\Application\CatalogAccess;
use App\Catalog\Domain\Entity\StoreAlias;
use App\Catalog\Domain\Exception\CatalogEntryNotFoundException;
use App\Catalog\Domain\Exception\CatalogNameConflictException;
use App\Catalog\Domain\Repository\StoreCatalogRepositoryInterface;
use App\Catalog\Domain\ValueObject\StoreName;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class RenameStoreAliasHandler
{
    public function __construct(
        private StoreCatalogRepositoryInterface $catalog,
        private CatalogAccess $access
    ) {
    }

    public function __invoke(RenameStoreAliasCommand $command): StoreAlias
    {
        $name = StoreName::fromString($command->alias);
        $this->access->lockFamilyForMemberMutation($command->familyId, $command->updatedByUserId);
        $familyId = $command->familyId;
        $alias = $this->catalog->findStoreAlias(
            $familyId,
            $command->storeId,
            $command->aliasId
        );
        if ($alias === null) {
            throw CatalogEntryNotFoundException::forEntry('store alias', (string) $command->aliasId);
        }

        $store = $this->catalog->findStoreByDedupeKey($familyId, $name->dedupeKey);
        if ($store !== null) {
            throw new CatalogNameConflictException('store', $store->getName());
        }
        $conflict = $this->catalog->findStoreAliasByDedupeKey(
            $familyId,
            $name->dedupeKey,
            $alias->getId()
        );
        if ($conflict !== null) {
            throw new CatalogNameConflictException('store alias', $conflict->getAlias());
        }

        $alias->rename((string) $name, $command->updatedByUserId);
        $this->catalog->saveStoreAlias($alias);

        return $alias;
    }
}

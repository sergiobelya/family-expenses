<?php

namespace App\Catalog\Application\Command;

use App\Catalog\Application\CatalogAccess;
use App\Catalog\Domain\Entity\Store;
use App\Catalog\Domain\Exception\CatalogEntryNotFoundException;
use App\Catalog\Domain\Exception\CatalogNameConflictException;
use App\Catalog\Domain\Repository\StoreCatalogRepositoryInterface;
use App\Catalog\Domain\ValueObject\StoreName;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class RenameStoreHandler
{
    public function __construct(
        private StoreCatalogRepositoryInterface $catalog,
        private CatalogAccess $access
    ) {
    }

    public function __invoke(RenameStoreCommand $command): Store
    {
        $name = StoreName::fromString($command->name);
        $this->access->lockFamilyForMemberMutation($command->familyId, $command->updatedByUserId);
        $familyId = $command->familyId;
        $store = $this->catalog->findStore($familyId, $command->storeId);
        if ($store === null) {
            throw CatalogEntryNotFoundException::forEntry('store', (string) $command->storeId);
        }

        $conflict = $this->catalog->findStoreByDedupeKey(
            $familyId,
            $name->getDedupeKey(),
            $store->getId()
        );
        if ($conflict !== null) {
            throw new CatalogNameConflictException('store', (string) $conflict->getName());
        }
        $alias = $this->catalog->findStoreAliasByDedupeKey($familyId, $name->getDedupeKey());
        if ($alias !== null) {
            throw new CatalogNameConflictException('store alias', (string) $alias->getName());
        }

        $store->rename($name, $command->updatedByUserId);
        $this->catalog->saveStore($store);

        return $store;
    }
}

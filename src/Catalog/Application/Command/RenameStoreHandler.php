<?php

namespace App\Catalog\Application\Command;

use App\Catalog\Application\CatalogAccess;
use App\Catalog\Domain\Entity\Store;
use App\Catalog\Domain\Exception\CatalogEntryNotFoundException;
use App\Catalog\Domain\Exception\CatalogNameConflictException;
use App\Catalog\Domain\Repository\CatalogRepositoryInterface;
use App\Catalog\Domain\ValueObject\CatalogName;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class RenameStoreHandler
{
    public function __construct(
        private CatalogRepositoryInterface $catalog,
        private CatalogAccess $access
    ) {
    }

    public function __invoke(RenameStoreCommand $command): Store
    {
        $name = CatalogName::fromString($command->name);
        $this->access->lockFamilyForMemberMutation($command->familyId, $command->updatedByUserId);
        $store = $this->catalog->findStore($command->familyId, $command->storeId);
        if ($store === null) {
            throw CatalogEntryNotFoundException::forEntry('store', (string) $command->storeId);
        }

        $conflict = $this->catalog->findStoreByDedupeKey(
            $command->familyId,
            $name->dedupeKey,
            $store->getId()
        );
        if ($conflict !== null) {
            throw new CatalogNameConflictException('store', $conflict->getName());
        }
        $alias = $this->catalog->findStoreAliasByDedupeKey($command->familyId, $name->dedupeKey);
        if ($alias !== null) {
            throw new CatalogNameConflictException('store alias', $alias->getAlias());
        }

        $store->rename($name->value, $command->updatedByUserId);
        $this->catalog->saveStore($store);

        return $store;
    }
}

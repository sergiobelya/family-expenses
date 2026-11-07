<?php

namespace App\Catalog\Application\Command;

use App\Catalog\Application\CatalogAccess;
use App\Catalog\Domain\Entity\StoreAlias;
use App\Catalog\Domain\Exception\CatalogEntryNotFoundException;
use App\Catalog\Domain\Exception\CatalogNameConflictException;
use App\Catalog\Domain\Repository\CatalogRepositoryInterface;
use App\Catalog\Domain\ValueObject\CatalogName;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class RenameStoreAliasHandler
{
    public function __construct(
        private CatalogRepositoryInterface $catalog,
        private CatalogAccess $access
    ) {
    }

    public function __invoke(RenameStoreAliasCommand $command): StoreAlias
    {
        $name = CatalogName::fromString($command->alias);
        $this->access->lockFamilyForMemberMutation($command->familyId, $command->updatedByUserId);
        $alias = $this->catalog->findStoreAlias(
            $command->familyId,
            $command->storeId,
            $command->aliasId
        );
        if ($alias === null) {
            throw CatalogEntryNotFoundException::forEntry('store alias', (string) $command->aliasId);
        }

        $store = $this->catalog->findStoreByDedupeKey($command->familyId, $name->dedupeKey);
        if ($store !== null) {
            throw new CatalogNameConflictException('store', $store->getName());
        }
        $conflict = $this->catalog->findStoreAliasByDedupeKey(
            $command->familyId,
            $name->dedupeKey,
            $alias->getId()
        );
        if ($conflict !== null) {
            throw new CatalogNameConflictException('store alias', $conflict->getAlias());
        }

        $alias->rename($name->value, $command->updatedByUserId);
        $this->catalog->saveStoreAlias($alias);

        return $alias;
    }
}

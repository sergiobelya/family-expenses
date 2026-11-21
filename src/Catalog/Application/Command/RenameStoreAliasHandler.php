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
final readonly class RenameStoreAliasHandler
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
        $store = $this->catalog->findStore($familyId, $command->storeId);
        if ($store === null) {
            throw CatalogEntryNotFoundException::forEntry('store', (string) $command->storeId);
        }

        if (!$store->hasAlias($command->aliasId)) {
            throw CatalogEntryNotFoundException::forEntry('store alias', (string) $command->aliasId);
        }

        $conflictStore = $this->catalog->findStoreByDedupeKey($familyId, $name->getDedupeKey());
        if ($conflictStore !== null) {
            throw new CatalogNameConflictException('store', (string) $conflictStore->getName());
        }
        $conflictAlias = $this->catalog->findStoreAliasByDedupeKey(
            $familyId,
            $name->getDedupeKey(),
            $command->aliasId
        );
        if ($conflictAlias !== null) {
            throw new CatalogNameConflictException('store alias', (string) $conflictAlias->getName());
        }

        $renamedAlias = $store->renameAlias($command->aliasId, $name, $command->updatedByUserId);
        $this->catalog->saveStore($store);

        return $renamedAlias;
    }
}

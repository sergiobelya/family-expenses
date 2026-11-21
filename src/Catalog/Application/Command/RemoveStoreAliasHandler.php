<?php

namespace App\Catalog\Application\Command;

use App\Catalog\Application\CatalogAccess;
use App\Catalog\Domain\Exception\CatalogEntryNotFoundException;
use App\Catalog\Domain\Repository\StoreCatalogRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RemoveStoreAliasHandler
{
    public function __construct(
        private StoreCatalogRepositoryInterface $catalog,
        private CatalogAccess $access
    ) {
    }

    public function __invoke(RemoveStoreAliasCommand $command): void
    {
        $this->access->lockFamilyForMemberMutation($command->familyId, $command->deletedByUserId);
        $store = $this->catalog->findStore($command->familyId, $command->storeId);
        if ($store === null) {
            throw CatalogEntryNotFoundException::forEntry('store', (string) $command->storeId);
        }

        if (!$store->hasAlias($command->aliasId)) {
            throw CatalogEntryNotFoundException::forEntry('store alias', (string) $command->aliasId);
        }

        $store->removeAlias($command->aliasId, $command->deletedByUserId);
        $this->catalog->saveStore($store);
    }
}

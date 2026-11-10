<?php

namespace App\Catalog\Application\Command;

use App\Catalog\Application\CatalogAccess;
use App\Catalog\Domain\Exception\CatalogEntryNotFoundException;
use App\Catalog\Domain\Repository\StoreCatalogRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class DeleteStoreHandler
{
    public function __construct(
        private StoreCatalogRepositoryInterface $catalog,
        private CatalogAccess $access
    ) {
    }

    public function __invoke(DeleteStoreCommand $command): void
    {
        $this->access->lockFamilyForMemberMutation($command->familyId, $command->deletedByUserId);
        $familyId = $command->familyId;
        $storeId = $command->storeId;
        $userId = $command->deletedByUserId;
        $store = $this->catalog->findStore($familyId, $storeId);
        if ($store === null) {
            throw CatalogEntryNotFoundException::forEntry('store', (string) $command->storeId);
        }

        $store->delete($userId);
        foreach ($this->catalog->findStoreAliases($familyId, $storeId) as $alias) {
            $alias->delete($userId);
            $this->catalog->removeStoreAlias($alias);
        }
        $this->catalog->removeStore($store);
    }
}

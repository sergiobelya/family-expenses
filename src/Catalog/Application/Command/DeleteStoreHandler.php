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
        $store = $this->catalog->findStoreWithAliases($command->familyId, $command->storeId);
        if ($store === null) {
            throw CatalogEntryNotFoundException::forEntry('store', (string) $command->storeId);
        }

        $store->delete($command->deletedByUserId);
        $this->catalog->removeStore($store);
    }
}

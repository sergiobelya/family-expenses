<?php

namespace App\Catalog\Application\Command;

use App\Catalog\Application\CatalogAccess;
use App\Catalog\Domain\Exception\CatalogEntryNotFoundException;
use App\Catalog\Domain\Repository\StoreCatalogRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class RemoveStoreAliasHandler
{
    public function __construct(
        private StoreCatalogRepositoryInterface $catalog,
        private CatalogAccess $access
    ) {
    }

    public function __invoke(RemoveStoreAliasCommand $command): void
    {
        $this->access->lockFamilyForMemberMutation($command->familyId, $command->deletedByUserId);
        $userId = $command->deletedByUserId;
        $alias = $this->catalog->findStoreAlias(
            $command->familyId,
            $command->storeId,
            $command->aliasId
        );
        if ($alias === null) {
            throw CatalogEntryNotFoundException::forEntry('store alias', (string) $command->aliasId);
        }

        $alias->delete($userId);
        $this->catalog->removeStoreAlias($alias);
    }
}

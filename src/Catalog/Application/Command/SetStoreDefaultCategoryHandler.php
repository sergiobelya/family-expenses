<?php

namespace App\Catalog\Application\Command;

use App\Catalog\Application\CatalogAccess;
use App\Catalog\Domain\Entity\Store;
use App\Catalog\Domain\Exception\CatalogEntryNotFoundException;
use App\Catalog\Domain\Repository\CatalogRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class SetStoreDefaultCategoryHandler
{
    public function __construct(
        private CatalogRepositoryInterface $catalog,
        private CatalogAccess $access
    ) {
    }

    public function __invoke(SetStoreDefaultCategoryCommand $command): Store
    {
        $this->access->lockFamilyForMemberMutation($command->familyId, $command->updatedByUserId);
        $familyId = $command->familyId;
        $storeId = $command->storeId;
        $categoryId = $command->categoryId;
        $userId = $command->updatedByUserId;
        $store = $this->catalog->findStore($familyId, $storeId);
        if ($store === null) {
            throw CatalogEntryNotFoundException::forEntry('store', (string) $command->storeId);
        }
        if ($categoryId !== null && $this->catalog->findCategory($familyId, $categoryId) === null) {
            throw CatalogEntryNotFoundException::forEntry('category', (string) $command->categoryId);
        }

        $store->setDefaultCategory($categoryId, $userId);
        $this->catalog->saveStore($store);

        return $store;
    }
}

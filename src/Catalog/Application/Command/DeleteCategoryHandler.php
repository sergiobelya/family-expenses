<?php

namespace App\Catalog\Application\Command;

use App\Catalog\Application\CatalogAccess;
use App\Catalog\Domain\Exception\CatalogEntryNotFoundException;
use App\Catalog\Domain\Exception\CatalogEntryReferencedException;
use App\Catalog\Domain\Repository\CatalogRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class DeleteCategoryHandler
{
    public function __construct(
        private CatalogRepositoryInterface $catalog,
        private CatalogAccess $access
    ) {
    }

    public function __invoke(DeleteCategoryCommand $command): void
    {
        $this->access->lockFamilyForMemberMutation($command->familyId, $command->deletedByUserId);
        $category = $this->catalog->findCategory($command->familyId, $command->categoryId);
        if ($category === null) {
            throw CatalogEntryNotFoundException::forEntry('category', (string) $command->categoryId);
        }
        if ($this->catalog->isCategoryUsedAsStoreDefault($command->familyId, $command->categoryId)) {
            throw CatalogEntryReferencedException::forEntry('category', (string) $command->categoryId);
        }

        $category->delete($command->deletedByUserId);
        $this->catalog->removeCategory($category);
    }
}

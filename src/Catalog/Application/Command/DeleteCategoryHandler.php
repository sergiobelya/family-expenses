<?php

namespace App\Catalog\Application\Command;

use App\Catalog\Application\CatalogAccess;
use App\Catalog\Domain\Exception\CatalogEntryNotFoundException;
use App\Catalog\Domain\Exception\CatalogEntryReferencedException;
use App\Catalog\Domain\Repository\CategoryCatalogRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class DeleteCategoryHandler
{
    public function __construct(
        private CategoryCatalogRepositoryInterface $catalog,
        private CatalogAccess $access
    ) {
    }

    public function __invoke(DeleteCategoryCommand $command): void
    {
        $this->access->lockFamilyForMemberMutation($command->familyId, $command->deletedByUserId);
        $familyId = $command->familyId;
        $categoryId = $command->categoryId;
        $userId = $command->deletedByUserId;
        $category = $this->catalog->findCategory($familyId, $categoryId);
        if ($category === null) {
            throw CatalogEntryNotFoundException::forEntry('category', (string) $command->categoryId);
        }
        if ($this->catalog->isCategoryUsedAsStoreDefault($familyId, $categoryId)) {
            throw CatalogEntryReferencedException::forEntry('category', (string) $command->categoryId);
        }

        $category->delete($userId);
        $this->catalog->removeCategory($category);
    }
}

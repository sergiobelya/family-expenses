<?php

namespace App\Catalog\Application\Command;

use App\Catalog\Application\CatalogAccess;
use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Exception\CatalogEntryNotFoundException;
use App\Catalog\Domain\Exception\CatalogNameConflictException;
use App\Catalog\Domain\Repository\CatalogRepositoryInterface;
use App\Catalog\Domain\ValueObject\CatalogName;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class RenameCategoryHandler
{
    public function __construct(
        private CatalogRepositoryInterface $catalog,
        private CatalogAccess $access
    ) {
    }

    public function __invoke(RenameCategoryCommand $command): Category
    {
        $name = CatalogName::fromString($command->name);
        $this->access->lockFamilyForMemberMutation($command->familyId, $command->updatedByUserId);
        $category = $this->catalog->findCategory($command->familyId, $command->categoryId);
        if ($category === null) {
            throw CatalogEntryNotFoundException::forEntry('category', (string) $command->categoryId);
        }

        $conflict = $this->catalog->findCategoryByDedupeKey(
            $command->familyId,
            $name->dedupeKey,
            $category->getId()
        );
        if ($conflict !== null) {
            throw new CatalogNameConflictException('category', $conflict->getName());
        }

        $category->rename($name->value, $command->updatedByUserId);
        $this->catalog->saveCategory($category);

        return $category;
    }
}

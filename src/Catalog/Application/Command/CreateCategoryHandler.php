<?php

namespace App\Catalog\Application\Command;

use App\Catalog\Application\CatalogAccess;
use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Exception\CatalogNameConflictException;
use App\Catalog\Domain\Repository\CategoryCatalogRepositoryInterface;
use App\Catalog\Domain\ValueObject\CategoryName;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateCategoryHandler
{
    public function __construct(
        private CategoryCatalogRepositoryInterface $catalog,
        private CatalogAccess $access
    ) {
    }

    public function __invoke(CreateCategoryCommand $command): Category
    {
        $name = CategoryName::fromString($command->name);
        $this->access->lockFamilyForMemberMutation($command->familyId, $command->createdByUserId);
        $familyId = $command->familyId;
        $conflict = $this->catalog->findCategoryByDedupeKey($familyId, $name->getDedupeKey());
        if ($conflict !== null) {
            throw new CatalogNameConflictException('category', (string) $conflict->getName());
        }

        $category = Category::create($familyId, $name->getValue(), $command->createdByUserId);
        $this->catalog->saveCategory($category);

        return $category;
    }
}

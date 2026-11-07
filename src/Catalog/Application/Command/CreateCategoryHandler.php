<?php

namespace App\Catalog\Application\Command;

use App\Catalog\Application\CatalogAccess;
use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Exception\CatalogNameConflictException;
use App\Catalog\Domain\Repository\CatalogRepositoryInterface;
use App\Catalog\Domain\ValueObject\CatalogName;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class CreateCategoryHandler
{
    public function __construct(
        private CatalogRepositoryInterface $catalog,
        private CatalogAccess $access
    ) {
    }

    public function __invoke(CreateCategoryCommand $command): Category
    {
        $name = CatalogName::fromString($command->name);
        $this->access->lockFamilyForMemberMutation($command->familyId, $command->createdByUserId);
        $conflict = $this->catalog->findCategoryByDedupeKey($command->familyId, $name->dedupeKey);
        if ($conflict !== null) {
            throw new CatalogNameConflictException('category', $conflict->getName());
        }

        $category = Category::create($command->familyId, $name->value, $command->createdByUserId);
        $this->catalog->saveCategory($category);

        return $category;
    }
}

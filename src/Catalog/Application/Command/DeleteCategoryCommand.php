<?php

namespace App\Catalog\Application\Command;

use App\Core\Domain\ValueObject\CategoryId;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;

final readonly class DeleteCategoryCommand
{
    public function __construct(
        public FamilyId $familyId,
        public CategoryId $categoryId,
        public UserId $deletedByUserId
    ) {
    }
}

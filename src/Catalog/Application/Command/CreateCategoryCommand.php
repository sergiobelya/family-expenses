<?php

namespace App\Catalog\Application\Command;

use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;

final readonly class CreateCategoryCommand
{
    public function __construct(
        public FamilyId $familyId,
        public UserId $createdByUserId,
        public string $name
    ) {
    }
}

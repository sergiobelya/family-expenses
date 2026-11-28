<?php

namespace App\Expense\Domain\Entity;

use App\Core\Domain\ValueObject\CategoryId;
use App\Core\Domain\ValueObject\FamilyId;

/**
 * Read-only category reference in the Expense bounded context.
 *
 * Contains only the fields needed for display (id + name) without
 * the full Catalog Category aggregate behavior (rename, delete, etc.).
 */
final readonly class ExpenseCategory
{
    public function __construct(
        private CategoryId $id,
        private string $name,
        private FamilyId $familyId,
    ) {
    }

    public function getId(): CategoryId
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getFamilyId(): FamilyId
    {
        return $this->familyId;
    }
}

<?php

namespace App\Expense\Domain\Entity;

use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\StoreId;

/**
 * Read-only store reference in the Expense bounded context.
 *
 * Contains only the fields needed for display (id + name) without
 * the full Catalog Store aggregate behavior (aliases, default category, etc.).
 */
final readonly class ExpenseStore
{
    public function __construct(
        private StoreId $id,
        private string $name,
        private FamilyId $familyId,
    ) {
    }

    public function getId(): StoreId
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

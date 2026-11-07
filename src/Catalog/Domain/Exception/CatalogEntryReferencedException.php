<?php

namespace App\Catalog\Domain\Exception;

use RuntimeException;

final class CatalogEntryReferencedException extends RuntimeException
{
    public static function forEntry(string $type, string $id): self
    {
        return new self(sprintf('Cannot delete %s "%s" because it is referenced by other records.', $type, $id));
    }
}

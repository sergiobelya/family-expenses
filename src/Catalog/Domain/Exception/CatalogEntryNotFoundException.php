<?php

namespace App\Catalog\Domain\Exception;

use RuntimeException;

final class CatalogEntryNotFoundException extends RuntimeException
{
    public static function forEntry(string $type, string $id): self
    {
        return new self(sprintf('%s "%s" was not found in this family.', ucfirst($type), $id));
    }
}

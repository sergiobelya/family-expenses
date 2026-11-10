<?php

namespace App\Catalog\Domain\Exception;

use RuntimeException;

final class CatalogEntryReferencedException extends RuntimeException
{
    public function __construct(
        public readonly string $entityType,
        public readonly string $entityId,
        ?\Throwable $previous = null
    ) {
        parent::__construct('Catalog entry is referenced by other records.', 0, $previous);
    }

    public static function forEntry(string $type, string $id, ?\Throwable $previous = null): self
    {
        return new self($type, $id, $previous);
    }

}

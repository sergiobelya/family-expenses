<?php

namespace App\Catalog\Domain\Exception;

use RuntimeException;

final class CatalogNameConflictException extends RuntimeException
{
    public function __construct(
        public readonly string $entityType,
        public readonly string $conflictingName
    ) {
        parent::__construct(sprintf('A %s with this name already exists: %s', $entityType, $conflictingName));
    }
}

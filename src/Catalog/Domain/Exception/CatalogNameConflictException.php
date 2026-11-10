<?php

namespace App\Catalog\Domain\Exception;

use RuntimeException;

final class CatalogNameConflictException extends RuntimeException
{
    public function __construct(
        public readonly string $entityType,
        public readonly string $conflictingName,
        ?\Throwable $previous = null
    ) {
        parent::__construct('A catalog name conflicts with an existing entry.', 0, $previous);
    }

}

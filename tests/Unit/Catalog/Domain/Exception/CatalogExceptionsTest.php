<?php

namespace App\Tests\Unit\Catalog\Domain\Exception;

use App\Catalog\Domain\Exception\CatalogEntryNotFoundException;
use App\Catalog\Domain\Exception\CatalogEntryReferencedException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CatalogExceptionsTest extends TestCase
{
    public function testNotFoundExceptionKeepsMessageStaticAndProvidesContext(): void
    {
        $original = new RuntimeException('Database error');
        $exception = CatalogEntryNotFoundException::forEntry('store', 'store-123', $original);

        self::assertSame('Catalog entry was not found in this family.', $exception->getMessage());
        self::assertSame('store', $exception->entityType);
        self::assertSame('store-123', $exception->entityId);
        self::assertSame($original, $exception->getPrevious());
    }

    public function testReferencedExceptionKeepsMessageStatic(): void
    {
        $exception = CatalogEntryReferencedException::forEntry('category', 'category-456');

        self::assertSame('Catalog entry is referenced by other records.', $exception->getMessage());
        self::assertSame('category', $exception->entityType);
        self::assertSame('category-456', $exception->entityId);
    }
}

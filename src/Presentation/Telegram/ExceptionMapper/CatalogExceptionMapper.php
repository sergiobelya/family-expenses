<?php

namespace App\Presentation\Telegram\ExceptionMapper;

use App\Catalog\Domain\Exception\CatalogEntryNotFoundException;
use App\Catalog\Domain\Exception\CatalogEntryReferencedException;
use App\Catalog\Domain\Exception\CatalogNameConflictException;
use Symfony\Component\Translation\TranslatableMessage;
use Throwable;

/**
 * Maps Catalog domain exceptions to TranslatableMessage.
 */
final readonly class CatalogExceptionMapper implements ExceptionMapperInterface
{
    public function supports(Throwable $exception): bool
    {
        return $exception instanceof CatalogEntryNotFoundException
            || $exception instanceof CatalogNameConflictException
            || $exception instanceof CatalogEntryReferencedException;
    }

    public function map(Throwable $exception): TranslatableMessage
    {
        return match (true) {
            $exception instanceof CatalogEntryNotFoundException => new TranslatableMessage('catalog.unavailable'),
            $exception instanceof CatalogNameConflictException => new TranslatableMessage('catalog.name_taken'),
            $exception instanceof CatalogEntryReferencedException => new TranslatableMessage('catalog.referenced'),
            default => throw new \RuntimeException('Unhandled exception type in CatalogExceptionMapper.'),
        };
    }
}

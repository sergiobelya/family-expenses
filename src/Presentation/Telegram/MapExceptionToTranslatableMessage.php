<?php

namespace App\Presentation\Telegram;

use App\Presentation\Telegram\ExceptionMapper\ExceptionMapperInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Translation\TranslatableMessage;
use Throwable;

/**
 * Facade that delegates exception mapping to strategy mappers.
 *
 * Returns a generic `common.error` message when no mapper supports the exception.
 */
final readonly class MapExceptionToTranslatableMessage
{
    /**
     * @param iterable<ExceptionMapperInterface> $mappers
     */
    public function __construct(
        #[AutowireIterator('app.exception_mapper')]
        private iterable $mappers
    ) {
    }

    /**
     * Map an exception to a TranslatableMessage.
     *
     * Returns a generic `common.error` message when no mapper supports the exception.
     */
    public function __invoke(Throwable $exception): TranslatableMessage
    {
        foreach ($this->mappers as $mapper) {
            if ($mapper->supports($exception)) {
                return $mapper->map($exception);
            }
        }

        return new TranslatableMessage('common.error');
    }
}

<?php

namespace App\Core\Domain\Exception;

use InvalidArgumentException;

final class InvalidValueException extends InvalidArgumentException
{
    /**
     * @param array<string, mixed> $properties
     */
    public function __construct(
        public readonly ErrorType $errorType,
        public readonly array $properties = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct('An invalid value was provided for a domain field: ' . $this->errorType->value, 0, $previous);
    }
}

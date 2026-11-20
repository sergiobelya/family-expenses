<?php

namespace App\Core\Domain\Exception;

use InvalidArgumentException;

final class InvalidValueException extends InvalidArgumentException
{
    public function __construct(
        public readonly ErrorType $errorType,
        ?\Throwable $previous = null
    ) {
        parent::__construct('An invalid value was provided for a domain field: ' . $this->errorType->value, 0, $previous);
    }
}

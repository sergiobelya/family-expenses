<?php

namespace App\Identity\Domain\Exception;

use DomainException;
use Throwable;

final class UnsupportedUserLanguageException extends DomainException
{
    public function __construct(
        public readonly string $language,
        ?Throwable $previous = null
    ) {
        parent::__construct('User language is not supported.', 0, $previous);
    }

    public static function forLanguage(string $language, ?Throwable $previous = null): self
    {
        return new self($language, $previous);
    }
}

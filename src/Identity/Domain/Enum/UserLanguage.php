<?php

namespace App\Identity\Domain\Enum;

enum UserLanguage: string
{
    case ENGLISH = 'en';
    case UKRAINIAN = 'uk';
    case POLISH = 'pl';
    case RUSSIAN = 'ru';
}

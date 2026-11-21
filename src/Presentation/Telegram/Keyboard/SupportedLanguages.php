<?php

declare(strict_types=1);

namespace App\Presentation\Telegram\Keyboard;

use App\Identity\Domain\Enum\UserLanguage;

final readonly class SupportedLanguages
{
    public const string DEFAULT = UserLanguage::ENGLISH->value;

    private const array LOCAL_NAMES = [
        UserLanguage::ENGLISH->value   => 'English',
        UserLanguage::UKRAINIAN->value => 'Українська',
        UserLanguage::POLISH->value    => 'Polski',
        UserLanguage::RUSSIAN->value   => 'Русский',
    ];

    /**
     * @return array<string, string>
     */
    public static function localNames(): array
    {
        return self::LOCAL_NAMES;
    }

    /**
     * @return array<string>
     */
    public static function codes(): array
    {
        return array_keys(self::LOCAL_NAMES);
    }

    public static function isSupported(string $languageCode): bool
    {
        return array_key_exists($languageCode, self::LOCAL_NAMES);
    }
}

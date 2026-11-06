<?php

namespace App\Presentation\Telegram\Keyboard;

final class ExchangeRateProviderOptions
{
    /**
     * @var array<string, string>
     */
    public const array ALL = [
        'nbp' => 'NBP (Poland)',
        'nbu' => 'NBU (Ukraine)',
        'ecb' => 'ECB (Europe)',
    ];

    public static function contains(string $provider): bool
    {
        return array_key_exists($provider, self::ALL);
    }
}

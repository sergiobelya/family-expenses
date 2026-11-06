<?php

namespace App\Presentation\Telegram\Keyboard;

use DateTimeZone;

final class TimezoneOptions
{
    private const array COMMON_TIMEZONES = [
        'UTC',
        'Europe/Kyiv',
        'Europe/Warsaw',
        'Europe/London',
        'America/New_York',
    ];

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        $timezones = [];
        foreach (DateTimeZone::listIdentifiers() as $timezone) {
            $timezones[$timezone] = $timezone;
        }

        return $timezones;
    }

    /**
     * @return array<string, string>
     */
    public function recommended(?string $preferredTimezone = null): array
    {
        $all = $this->all();
        $recommendations = [];

        foreach ([$preferredTimezone, ...self::COMMON_TIMEZONES] as $timezone) {
            if ($timezone !== null && array_key_exists($timezone, $all)) {
                $recommendations[$timezone] = $timezone;
            }
        }

        return $recommendations;
    }
}

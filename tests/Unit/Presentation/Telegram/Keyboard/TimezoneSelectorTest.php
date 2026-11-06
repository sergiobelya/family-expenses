<?php

namespace App\Tests\Unit\Presentation\Telegram\Keyboard;

use App\Presentation\Telegram\Keyboard\InlineOptionSelector;
use App\Presentation\Telegram\Keyboard\TimezoneOptions;
use App\Presentation\Telegram\Keyboard\TimezoneSelector;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class TimezoneSelectorTest extends TestCase
{
    public function testBuildShowsRecommendedTimezonesAndPutsPreferredTimezoneFirst(): void
    {
        $selection = $this->createSelector()->build(
            callbackPrefix: 'settings:family:timezone',
            selectedValue: 'UTC',
            query: '',
            page: 0,
            backLabel: 'Back',
            backCallback: 'settings:family:view',
            preferredTimezone: 'Europe/Warsaw'
        );

        $rows = $selection['keyboard']['inline_keyboard'];

        self::assertSame('settings:family:timezone:search', $rows[0][0]['callback_data']);
        self::assertSame('Europe/Warsaw', $rows[1][0]['text']);
        self::assertSame('UTC ✅', $rows[2][0]['text']);
        self::assertSame('settings:family:timezone:set:Europe/Warsaw', $rows[1][0]['callback_data']);
        self::assertSame('settings:family:timezone:set:America/New_York', $rows[5][0]['callback_data']);
        self::assertTrue($selection['has_results']);
    }

    public function testBuildSearchesAllTimezonesAndPaginatesResults(): void
    {
        $selection = $this->createSelector()->build(
            callbackPrefix: 'settings:family:timezone',
            selectedValue: null,
            query: 'America/',
            page: 0,
            backLabel: 'Back',
            backCallback: 'settings:family:view'
        );

        $rows = $selection['keyboard']['inline_keyboard'];

        self::assertTrue($selection['has_results']);
        self::assertSame('America/Adak', $rows[2][0]['text']);
        self::assertSame('settings:family:timezone:page:1', $rows[count($rows) - 2][0]['callback_data']);
    }

    private function createSelector(): TimezoneSelector
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn(string $id): string => $id);

        return new TimezoneSelector(new TimezoneOptions(), new InlineOptionSelector(), $translator);
    }
}

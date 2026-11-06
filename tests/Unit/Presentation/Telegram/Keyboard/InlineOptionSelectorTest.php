<?php

namespace App\Tests\Unit\Presentation\Telegram\Keyboard;

use App\Presentation\Telegram\Keyboard\InlineOptionSelector;
use PHPUnit\Framework\TestCase;

class InlineOptionSelectorTest extends TestCase
{
    public function testFiltersOptionsByCaseAndAccentInsensitiveLabelOrValue(): void
    {
        $selector = new InlineOptionSelector();

        self::assertSame(
            ['pl' => 'Polski'],
            $selector->filterOptions([
                'en' => 'English',
                'uk' => 'Українська',
                'pl' => 'Polski',
                'ru' => 'Русский',
            ], 'POLSKI')
        );
        self::assertSame(
            ['uk' => 'Українська'],
            $selector->filterOptions(['uk' => 'Українська'], 'UK')
        );
        self::assertSame(
            ['paris' => 'Paris'],
            $selector->filterOptions(['paris' => 'Paris'], 'PÁRIS')
        );
    }

    public function testBuildsPagedKeyboardAndClampsPage(): void
    {
        $selector = new InlineOptionSelector();
        $options = [];
        for ($i = 1; $i <= 10; $i++) {
            $options['option-' . $i] = 'Option ' . $i;
        }

        $keyboard = $selector->buildKeyboard(
            options: $options,
            callbackPrefix: 'selector:test',
            selectedValue: 'option-10',
            query: null,
            page: 99,
            searchLabel: 'Search',
            clearSearchLabel: 'Clear',
            previousLabel: 'Previous',
            nextLabel: 'Next',
            backLabel: 'Back',
            backCallback: 'selector:back'
        );

        self::assertSame(1, $selector->normalizePage($options, '', 99));
        self::assertSame('Option 9', $keyboard['inline_keyboard'][1][0]['text']);
        self::assertSame('Option 10 ✅', $keyboard['inline_keyboard'][2][0]['text']);
        self::assertSame('selector:test:page:0', $keyboard['inline_keyboard'][3][0]['callback_data']);
        self::assertSame('selector:back', $keyboard['inline_keyboard'][4][0]['callback_data']);
    }
}

<?php

namespace App\Tests\Unit\Core\Application;

use App\Core\Application\Currency;
use App\Core\Application\CurrencyCatalog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CurrencyCatalog::class)]
final class CurrencyCatalogTest extends TestCase
{
    public function testListsSupportedCurrencyValuesWithLocalizedNames(): void
    {
        $currencies = (new CurrencyCatalog())->all('en');
        $codes = array_map(static fn (Currency $currency): string => $currency->code, $currencies);
        $firstCurrency = $currencies[0] ?? null;

        self::assertNotEmpty($currencies);
        self::assertContains('GBP', $codes);
        self::assertInstanceOf(Currency::class, $firstCurrency);
        self::assertNotSame('', $firstCurrency->localizedName);
    }

    public function testSearchMatchesCurrencyCodeAndEnglishName(): void
    {
        $catalog = new CurrencyCatalog();
        $currencyByCode = $catalog->search('gbp', 'uk')[0] ?? null;
        $currenciesByName = $catalog->search('British', 'uk');

        self::assertInstanceOf(Currency::class, $currencyByCode);
        self::assertSame('GBP', $currencyByCode->code);
        self::assertContains('GBP', array_map(
            static fn (Currency $currency): string => $currency->code,
            $currenciesByName
        ));
    }
}

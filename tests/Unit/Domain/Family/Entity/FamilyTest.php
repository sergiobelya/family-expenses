<?php

namespace App\Tests\Unit\Domain\Family\Entity;

use App\Family\Domain\Entity\Family;
use App\Core\Domain\ValueObject\CurrencyCode;
use App\Core\Domain\ValueObject\Timezone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FamilyTest extends TestCase
{
    public function testCreateFamily(): void
    {
        $family = new Family(
            name: 'Smith Family',
            timezone: new Timezone('Europe/Warsaw'),
            defaultExpenseCurrency: new CurrencyCode('PLN'),
            reportingCurrency: new CurrencyCode('PLN')
        );

        $this->assertSame('Smith Family', $family->getName());
        $this->assertSame('Europe/Warsaw', (string) $family->getTimezone());
        $this->assertSame('PLN', (string) $family->getDefaultExpenseCurrency());
        $this->assertSame('PLN', (string) $family->getReportingCurrency());
        $this->assertSame('nbp', $family->getDefaultExchangeRateProvider());
    }

    public function testEmptyNameThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Family(
            name: '   ',
            timezone: new Timezone('Europe/Warsaw'),
            defaultExpenseCurrency: new CurrencyCode('PLN'),
            reportingCurrency: new CurrencyCode('PLN')
        );
    }
}

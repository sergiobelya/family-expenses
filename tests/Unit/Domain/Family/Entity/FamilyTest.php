<?php

namespace App\Tests\Unit\Domain\Family\Entity;

use App\Family\Domain\Entity\Family;
use App\Core\Domain\ValueObject\CurrencyCode;
use App\Core\Domain\ValueObject\Timezone;
use App\Core\Domain\Exception\InvalidValueException;
use PHPUnit\Framework\TestCase;

class FamilyTest extends TestCase
{
    public function testCreateFamily(): void
    {
        $family = Family::create(
            name: 'Smith Family',
            timezone: new Timezone('Europe/Warsaw'),
            defaultExpenseCurrency: new CurrencyCode('PLN'),
            reportingCurrency: new CurrencyCode('PLN')
        );

        $this->assertSame('Smith Family', (string) $family->getName());
        $this->assertSame('Europe/Warsaw', (string) $family->getTimezone());
        $this->assertSame('PLN', (string) $family->getDefaultExpenseCurrency());
        $this->assertSame('PLN', (string) $family->getReportingCurrency());
        $this->assertSame('nbp', $family->getDefaultExchangeRateProvider());
    }

    public function testEmptyNameThrowsException(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('An invalid value was provided for a domain field: family_name_too_short');
        Family::create(
            name: '   ',
            timezone: new Timezone('Europe/Warsaw'),
            defaultExpenseCurrency: new CurrencyCode('PLN'),
            reportingCurrency: new CurrencyCode('PLN')
        );
    }
}

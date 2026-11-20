<?php

namespace App\Tests\Unit\Core\Domain\ValueObject;

use App\Core\Domain\ValueObject\CountryCode;
use App\Core\Domain\Exception\InvalidValueException;
use PHPUnit\Framework\TestCase;

final class CountryCodeTest extends TestCase
{
    public function testNormalizesCountryCode(): void
    {
        self::assertSame('PL', (string) CountryCode::fromString(' pl '));
    }

    public function testRejectsInvalidCountryCode(): void
    {
        $this->expectException(InvalidValueException::class);
        CountryCode::fromString('POL');
    }
}

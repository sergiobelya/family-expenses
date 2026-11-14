<?php

namespace App\Tests\Unit\Core\Domain\ValueObject;

use App\Core\Domain\ValueObject\CountryCode;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CountryCodeTest extends TestCase
{
    public function testNormalizesCountryCode(): void
    {
        self::assertSame('PL', (string) CountryCode::fromString(' pl '));
    }

    public function testRejectsInvalidCountryCode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CountryCode::fromString('POL');
    }
}

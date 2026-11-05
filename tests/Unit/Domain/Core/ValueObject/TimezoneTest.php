<?php

namespace App\Tests\Unit\Domain\Core\ValueObject;

use App\Core\Domain\ValueObject\Timezone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TimezoneTest extends TestCase
{
    public function testValidTimezone(): void
    {
        $tz = new Timezone('Europe/Warsaw');
        $this->assertSame('Europe/Warsaw', (string) $tz);
        $this->assertSame('Europe/Warsaw', $tz->toDateTimeZone()->getName());

        $tz2 = Timezone::fromString('Europe/Warsaw');
        $this->assertTrue($tz->equals($tz2));
    }

    public function testInvalidTimezoneThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Timezone('Invalid/Timezone');
    }

    public function testDefaultTimezone(): void
    {
        $default = Timezone::default();
        $this->assertSame('Europe/Warsaw', (string) $default);
    }
}

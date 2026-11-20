<?php

namespace App\Tests\Unit\Domain\Family\ValueObject;

use App\Family\Domain\ValueObject\FamilyName;
use App\Core\Domain\Exception\InvalidValueException;
use PHPUnit\Framework\TestCase;

final class FamilyNameTest extends TestCase
{
    public function testTrimsAndPreservesFamilyName(): void
    {
        self::assertSame('Biely Family', (string) FamilyName::fromString(' Biely Family '));
    }

    public function testRejectsAnEmptyFamilyName(): void
    {
        $this->expectException(InvalidValueException::class);
        FamilyName::fromString('  ');
    }

    public function testRejectsTooShortFamilyName(): void
    {
        $this->expectException(InvalidValueException::class);
        FamilyName::fromString('A');
    }

    public function testAcceptsTwoCharacterFamilyName(): void
    {
        self::assertSame('AB', (string) FamilyName::fromString('AB'));
    }

    public function testRejectsTooLongFamilyName(): void
    {
        $this->expectException(InvalidValueException::class);
        FamilyName::fromString(str_repeat('A', 101));
    }
}

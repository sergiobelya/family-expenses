<?php

namespace App\Tests\Unit\Domain\Family\ValueObject;

use App\Family\Domain\ValueObject\FamilyName;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FamilyNameTest extends TestCase
{
    public function testTrimsAndPreservesFamilyName(): void
    {
        self::assertSame('Biely Family', (string) FamilyName::fromString(' Biely Family '));
    }

    public function testRejectsAnEmptyFamilyName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FamilyName::fromString('  ');
    }
}

<?php

namespace App\Tests\Unit\Domain\Identity\Entity;

use App\Identity\Domain\Entity\User;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class UserTest extends TestCase
{
    public function testCreateUserWithDefaults(): void
    {
        $user = new User(123456789);

        $this->assertSame(123456789, $user->getTelegramUserId());
        $this->assertSame('en', $user->getLanguage());
        $this->assertNull($user->getActiveFamilyId());
        $this->assertInstanceOf(Uuid::class, $user->getId());
    }

    public function testSetLanguage(): void
    {
        $user = new User(123456789);
        $user->setLanguage('uk');
        $this->assertSame('uk', $user->getLanguage());

        $user->setLanguage('PL');
        $this->assertSame('pl', $user->getLanguage());
    }

    public function testSetInvalidLanguageThrowsException(): void
    {
        $user = new User(123456789);
        $this->expectException(InvalidArgumentException::class);
        $user->setLanguage('de');
    }

    public function testSetActiveFamilyId(): void
    {
        $user = new User(123456789);
        $familyId = Uuid::v7();
        $user->setActiveFamilyId($familyId);
        $this->assertSame($familyId, $user->getActiveFamilyId());
    }
}

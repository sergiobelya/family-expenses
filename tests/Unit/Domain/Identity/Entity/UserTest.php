<?php

namespace App\Tests\Unit\Domain\Identity\Entity;

use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Enum\UserLanguage;
use App\Identity\Domain\Exception\UnsupportedUserLanguageException;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;
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
        $this->assertInstanceOf(UserId::class, $user->getId());
    }

    public function testSetLanguage(): void
    {
        $user = new User(123456789);
        $user->setLanguage('uk');
        $this->assertSame('uk', $user->getLanguage());

        $user->setLanguage('PL');
        $this->assertSame('pl', $user->getLanguage());
        $this->assertSame(UserLanguage::POLISH->value, $user->getLanguage());
    }

    public function testSetInvalidLanguageThrowsException(): void
    {
        $user = new User(123456789);
        try {
            $user->setLanguage('de');
            $this->fail('Expected unsupported language exception.');
        } catch (UnsupportedUserLanguageException $exception) {
            $this->assertSame('de', $exception->language);
            $this->assertSame('User language is not supported.', $exception->getMessage());
        }
    }

    public function testSetActiveFamilyId(): void
    {
        $user = new User(123456789);
        $familyId = Uuid::v7();
        $user->setActiveFamilyId(new FamilyId($familyId));
        $activeFamilyId = $user->getActiveFamilyId();
        self::assertNotNull($activeFamilyId);
        $this->assertTrue((new FamilyId($familyId))->equals($activeFamilyId));
    }
}

<?php

namespace App\Tests\Unit\Application\Identity\Command;

use App\Identity\Application\Command\ChangeUserLanguageCommand;
use App\Identity\Application\Command\ChangeUserLanguageHandler;
use App\Identity\Application\Command\EnsureUserExistsCommand;
use App\Identity\Application\Command\EnsureUserExistsHandler;
use App\Identity\Application\Command\SwitchActiveFamilyCommand;
use App\Identity\Application\Command\SwitchActiveFamilyHandler;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class IdentityCommandHandlersTest extends TestCase
{
    public function testEnsureUserExistsCreatesNewUser(): void
    {
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->expects($this->once())->method('findByTelegramUserId')->with(123456)->willReturn(null);
        $userRepo->expects($this->once())->method('save')->with($this->isInstanceOf(User::class));

        $handler = new EnsureUserExistsHandler($userRepo);
        $user = $handler(new EnsureUserExistsCommand(123456, 'uk'));

        $this->assertSame(123456, $user->getTelegramUserId());
        $this->assertSame('uk', $user->getLanguage());
    }

    public function testEnsureUserExistsReturnsExistingUser(): void
    {
        $existing = new User(123456, 'en');

        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->expects($this->once())->method('findByTelegramUserId')->with(123456)->willReturn($existing);
        $userRepo->expects($this->never())->method('save');

        $handler = new EnsureUserExistsHandler($userRepo);
        $user = $handler(new EnsureUserExistsCommand(123456, 'uk'));

        $this->assertSame($existing, $user);
        $this->assertSame('en', $user->getLanguage());
    }

    public function testChangeUserLanguage(): void
    {
        $user = new User(123456, 'en');

        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->expects($this->once())->method('findById')->with($user->getId())->willReturn($user);
        $userRepo->expects($this->once())->method('save')->with($user);

        $handler = new ChangeUserLanguageHandler($userRepo);
        $handler(new ChangeUserLanguageCommand($user->getId(), 'pl'));

        $this->assertSame('pl', $user->getLanguage());
    }

    public function testSwitchActiveFamily(): void
    {
        $user = new User(123456, 'en');
        $newFamilyId = Uuid::v7();

        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->expects($this->once())->method('findById')->with($user->getId())->willReturn($user);
        $userRepo->expects($this->once())->method('save')->with($user);

        $handler = new SwitchActiveFamilyHandler($userRepo);
        $handler(new SwitchActiveFamilyCommand($user->getId(), $newFamilyId));

        $this->assertEquals($newFamilyId, $user->getActiveFamilyId());
    }
}

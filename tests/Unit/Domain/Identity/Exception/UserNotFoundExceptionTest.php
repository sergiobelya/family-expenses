<?php

namespace App\Tests\Unit\Domain\Identity\Exception;

use App\Identity\Domain\Exception\UserNotFoundException;
use PHPUnit\Framework\TestCase;

final class UserNotFoundExceptionTest extends TestCase
{
    public function testMessageIsStaticAndIdentifierIsAvailableAsContext(): void
    {
        $exception = UserNotFoundException::withId('user-123');

        self::assertSame('User was not found.', $exception->getMessage());
        self::assertSame('user-123', $exception->userId);
    }
}

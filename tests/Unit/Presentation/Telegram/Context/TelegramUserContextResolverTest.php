<?php

namespace App\Tests\Unit\Presentation\Telegram\Context;

use App\Core\Domain\ValueObject\CurrencyCode;
use App\Core\Domain\ValueObject\Timezone;
use App\Core\Infrastructure\Messenger\MessageBusDispatcherInterface;
use App\Family\Domain\Entity\Family;
use App\Identity\Domain\Entity\User;
use App\Presentation\Telegram\Context\ResolveActiveFamilyForUser;
use App\Presentation\Telegram\Context\TelegramUserContextResolver;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\LocaleAwareInterface;

class TelegramUserContextResolverTest extends TestCase
{
    public function testResolveReturnsNullWhenNoUserOrChat(): void
    {
        $messageBusDispatcher = $this->createMock(MessageBusDispatcherInterface::class);
        $messageBusDispatcher->expects(self::never())->method('dispatchCommand');
        $resolveActiveFamily = $this->createMock(ResolveActiveFamilyForUser::class);
        $resolveActiveFamily->expects(self::never())->method('__invoke');
        $translator = $this->createMock(LocaleAwareInterface::class);

        $resolver = new TelegramUserContextResolver($messageBusDispatcher, $resolveActiveFamily, $translator);

        $update = new TelegramUpdate(1, null, null, null, null, null, null, null, null, null, null, null);
        $this->assertNull($resolver->resolve($update));
    }

    public function testResolveSetsLanguageAndLoadsActiveFamily(): void
    {
        $user = new User(123456, 'uk');
        $family = Family::create('Smiths', new Timezone('UTC'), new CurrencyCode('USD'), new CurrencyCode('USD'));
        $user->switchActiveFamily($family->getId());

        $messageBusDispatcher = $this->createMock(MessageBusDispatcherInterface::class);
        $messageBusDispatcher->expects($this->once())->method('dispatchCommand')
            ->willReturn($user);

        $resolveActiveFamily = $this->createMock(ResolveActiveFamilyForUser::class);
        $resolveActiveFamily->expects(self::once())
            ->method('__invoke')
            ->with($user)
            ->willReturn($family);

        $translator = $this->createMock(LocaleAwareInterface::class);
        $translator->expects($this->once())->method('setLocale')->with('uk');

        $resolver = new TelegramUserContextResolver($messageBusDispatcher, $resolveActiveFamily, $translator);

        $update = TelegramUpdate::fromArray([
            'update_id' => 10,
            'message' => [
                'message_id' => 100,
                'chat' => ['id' => 999],
                'from' => [
                    'id' => 123456,
                    'username' => 'test_user',
                    'first_name' => 'John',
                    'language_code' => 'uk',
                ],
                'text' => '/start',
            ],
        ]);

        $context = $resolver->resolve($update);

        $this->assertNotNull($context);
        $this->assertSame($user, $context->user);
        $this->assertInstanceOf(TelegramFamilyMemberContext::class, $context);
        self::assertSame($family, $context->activeFamily);
        $this->assertSame('uk', $context->language);
        $this->assertSame(123456, $context->telegramUserId);
        $this->assertSame(999, $context->chatId);
    }
}

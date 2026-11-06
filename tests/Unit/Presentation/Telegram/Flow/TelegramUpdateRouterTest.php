<?php

namespace App\Tests\Unit\Presentation\Telegram\Flow;

use App\Core\Infrastructure\Persistence\Redis\UserInteractionStateRepository;
use App\Identity\Domain\Entity\User;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\Context\TelegramUserContextResolver;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Flow\FlowHandlerInterface;
use App\Presentation\Telegram\Flow\TelegramUpdateRouter;
use App\Presentation\Telegram\Keyboard\MainMenuKeyboard;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class TelegramUpdateRouterTest extends TestCase
{
    public function testMainMenuButtonClearsActiveFlowBeforeRouting(): void
    {
        $context = new TelegramUserContext(new User(123456), null, 'en', 123456, 111);
        $contextResolver = $this->createMock(TelegramUserContextResolver::class);
        $contextResolver->method('resolve')->willReturn($context);

        $events = [];
        $stateRepository = $this->createMock(UserInteractionStateRepository::class);
        $stateRepository->expects(self::once())
            ->method('clear')
            ->with(123456)
            ->willReturnCallback(static function () use (&$events): void {
                $events[] = 'clear';
            });

        $handler = $this->createMock(FlowHandlerInterface::class);
        $handler->expects(self::once())
            ->method('supports')
            ->willReturnCallback(static function () use (&$events): bool {
                $events[] = 'supports';

                return true;
            });
        $handler->expects(self::once())
            ->method('handle')
            ->willReturnCallback(static function () use (&$events): void {
                $events[] = 'handle';
            });

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn(string $key): string => $key);
        $router = new TelegramUpdateRouter(
            $contextResolver,
            $stateRepository,
            new MainMenuKeyboard($translator),
            [$handler]
        );

        $router->route(TelegramUpdate::fromArray([
            'update_id' => 1,
            'message' => [
                'message_id' => 10,
                'chat' => ['id' => 111],
                'text' => 'main_menu.settings',
            ],
        ]));

        self::assertSame(['clear', 'supports', 'handle'], $events);
    }

    public function testOrdinaryTextDoesNotClearActiveFlow(): void
    {
        $context = new TelegramUserContext(new User(123456), null, 'en', 123456, 111);
        $contextResolver = $this->createMock(TelegramUserContextResolver::class);
        $contextResolver->method('resolve')->willReturn($context);

        $stateRepository = $this->createMock(UserInteractionStateRepository::class);
        $stateRepository->expects(self::never())->method('clear');

        $handler = $this->createMock(FlowHandlerInterface::class);
        $handler->expects(self::once())->method('supports')->willReturn(true);
        $handler->expects(self::once())->method('handle');

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn(string $key): string => $key);
        $router = new TelegramUpdateRouter(
            $contextResolver,
            $stateRepository,
            new MainMenuKeyboard($translator),
            [$handler]
        );

        $router->route(TelegramUpdate::fromArray([
            'update_id' => 2,
            'message' => [
                'message_id' => 11,
                'chat' => ['id' => 111],
                'text' => 'family name input',
            ],
        ]));
    }
}

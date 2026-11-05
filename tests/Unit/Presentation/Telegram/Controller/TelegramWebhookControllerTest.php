<?php

namespace App\Tests\Unit\Presentation\Telegram\Controller;

use App\Core\Infrastructure\Persistence\Doctrine\Entity\TelegramUpdateInbox;
use App\Core\Infrastructure\Persistence\Doctrine\Repository\TelegramUpdateInboxRepository;
use App\Presentation\Telegram\Controller\TelegramWebhookController;
use App\Presentation\Telegram\Flow\TelegramUpdateRouter;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class TelegramWebhookControllerTest extends TestCase
{
    public function testRejectsInvalidSecretToken(): void
    {
        $router = $this->createMock(TelegramUpdateRouter::class);
        $inboxRepo = $this->createMock(TelegramUpdateInboxRepository::class);
        $controller = new TelegramWebhookController($router, $inboxRepo, new NullLogger(), 'expected_secret');

        $request = new Request([], [], [], [], [], [
            'HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN' => 'wrong_secret',
        ], json_encode(['update_id' => 1]));

        $response = $controller($request);

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testAcceptsValidSecretAndProcessesUpdate(): void
    {
        $router = $this->createMock(TelegramUpdateRouter::class);
        $router->expects($this->once())->method('route');

        $inboxRepo = $this->createMock(TelegramUpdateInboxRepository::class);
        $inboxRepo->expects($this->once())
            ->method('recordIfNew')
            ->with(123)
            ->willReturn(new TelegramUpdateInbox(123));

        $controller = new TelegramWebhookController($router, $inboxRepo, new NullLogger(), 'my_secret');

        $payload = json_encode([
            'update_id' => 123,
            'message' => [
                'message_id' => 1,
                'chat' => ['id' => 111],
                'from' => ['id' => 222],
                'text' => '/start',
            ],
        ]);

        $request = new Request([], [], [], [], [], [
            'HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN' => 'my_secret',
        ], $payload);

        $response = $controller($request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testSkipsDuplicateUpdate(): void
    {
        $router = $this->createMock(TelegramUpdateRouter::class);
        $router->expects($this->never())->method('route');

        $inboxRepo = $this->createMock(TelegramUpdateInboxRepository::class);
        $inboxRepo->expects($this->once())
            ->method('recordIfNew')
            ->with(123)
            ->willReturn(null); // Already exists

        $controller = new TelegramWebhookController($router, $inboxRepo, new NullLogger(), null);

        $request = new Request([], [], [], [], [], [], json_encode(['update_id' => 123]));
        $response = $controller($request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['duplicate'] ?? false);
    }
}

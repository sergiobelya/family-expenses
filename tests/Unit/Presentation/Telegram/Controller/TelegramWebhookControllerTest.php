<?php

namespace App\Tests\Unit\Presentation\Telegram\Controller;

use App\Presentation\Telegram\Controller\TelegramWebhookController;
use App\Presentation\Telegram\TelegramUpdateProcessor;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class TelegramWebhookControllerTest extends TestCase
{
    public function testRejectsInvalidSecretToken(): void
    {
        $processor = $this->createMock(TelegramUpdateProcessor::class);
        $controller = new TelegramWebhookController($processor, new NullLogger(), 'expected_secret');

        $request = new Request([], [], [], [], [], [
            'HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN' => 'wrong_secret',
        ], json_encode(['update_id' => 1]));

        $response = $controller($request);

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testAcceptsValidSecretAndProcessesUpdate(): void
    {
        $processor = $this->createMock(TelegramUpdateProcessor::class);
        $processor->expects($this->once())->method('process')->willReturn(true);

        $controller = new TelegramWebhookController($processor, new NullLogger(), 'my_secret');

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
        $processor = $this->createMock(TelegramUpdateProcessor::class);
        $processor->expects($this->once())->method('process')->willReturn(false);

        $controller = new TelegramWebhookController($processor, new NullLogger(), null);

        $request = new Request([], [], [], [], [], [], json_encode(['update_id' => 123]));
        $response = $controller($request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['duplicate'] ?? false);
    }
}

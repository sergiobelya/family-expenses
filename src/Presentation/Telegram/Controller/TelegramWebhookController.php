<?php

namespace App\Presentation\Telegram\Controller;

use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\TelegramUpdateProcessor;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

#[Route('/webhook/telegram', name: 'telegram_webhook', methods: ['POST'])]
class TelegramWebhookController
{
    public function __construct(
        private TelegramUpdateProcessor $updateProcessor,
        private LoggerInterface $logger,
        #[Autowire('%app.telegram_webhook_secret%')]
        private ?string $expectedSecret = null
    ) {}

    public function __invoke(Request $request): Response
    {
        if ($this->expectedSecret !== null && $this->expectedSecret !== '') {
            $secretToken = $request->headers->get('X-Telegram-Bot-Api-Secret-Token');
            if ($secretToken !== $this->expectedSecret) {
                $this->logger->warning('Rejected Telegram webhook update: invalid secret token');
                return new JsonResponse(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
            }
        }

        $content = $request->getContent();
        if (empty($content)) {
            return new JsonResponse(['error' => 'Empty body'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        $updateId = filter_var($data['update_id'] ?? null, FILTER_VALIDATE_INT);
        if ($updateId === false || $updateId < 1) {
            return new JsonResponse(['error' => 'Invalid update_id'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $processed = $this->updateProcessor->process(TelegramUpdate::fromArray($data));
        } catch (Throwable $e) {
            $this->logger->error('Error processing Telegram update', [
                'update_id' => $updateId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse(['error' => 'Update processing failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if (!$processed) {
            $this->logger->info('Duplicate Telegram update ignored', ['update_id' => $updateId]);
            return new JsonResponse(['ok' => true, 'duplicate' => true]);
        }

        return new JsonResponse(['ok' => true]);
    }
}

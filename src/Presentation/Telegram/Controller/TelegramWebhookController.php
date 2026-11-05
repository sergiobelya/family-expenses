<?php

namespace App\Presentation\Telegram\Controller;

use App\Core\Infrastructure\Persistence\Doctrine\Repository\TelegramUpdateInboxRepository;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Flow\TelegramUpdateRouter;
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
        private TelegramUpdateRouter $updateRouter,
        private TelegramUpdateInboxRepository $inboxRepository,
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

        $updateId = (int) ($data['update_id'] ?? 0);
        if ($updateId > 0) {
            $inboxRecord = $this->inboxRepository->recordIfNew($updateId);
            if ($inboxRecord === null) {
                // Duplicate update, skip processing
                $this->logger->info('Duplicate Telegram update ignored', ['update_id' => $updateId]);
                return new JsonResponse(['ok' => true, 'duplicate' => true]);
            }
        }

        try {
            $update = TelegramUpdate::fromArray($data);
            $this->updateRouter->route($update);
        } catch (Throwable $e) {
            $this->logger->error('Error processing Telegram update', [
                'update_id' => $updateId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return new JsonResponse(['ok' => true]);
    }
}

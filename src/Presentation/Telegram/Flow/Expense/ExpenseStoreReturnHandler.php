<?php

namespace App\Presentation\Telegram\Flow\Expense;

use App\Core\Infrastructure\Persistence\Redis\UserInteractionStateRepository;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Flow\FamilyContextFlowHandlerInterface;

final readonly class ExpenseStoreReturnHandler implements FamilyContextFlowHandlerInterface
{
    public function __construct(
        private ExpenseStoreReturn $storeReturn,
        private UserInteractionStateRepository $stateRepository,
        private TelegramApiClientInterface $telegramClient,
    ) {
    }

    public function supports(TelegramUpdate $update, TelegramUserContext $context): bool
    {
        return $update->callbackData === CallbackActions::CATALOG_RETURN_PREFIX . 'expense';
    }

    public function handle(TelegramUpdate $update, TelegramFamilyMemberContext $context): void
    {
        if ($update->callbackQueryId !== null) {
            $this->telegramClient->answerCallbackQuery($update->callbackQueryId);
        }

        $state = $this->stateRepository->get($context->telegramUserId);
        $returnContext = $state?->data['expense_return_context'] ?? null;
        $storeId = $state?->data['store_id'] ?? null;

        $this->storeReturn->handle(
            $context,
            is_array($returnContext) ? $returnContext : null,
            is_string($storeId) ? $storeId : null
        );
        if (
            is_array($returnContext)
            && ($returnContext['telegram_user_id'] ?? null) === $context->telegramUserId
        ) {
            $this->stateRepository->clear($context->telegramUserId);
        }
    }
}

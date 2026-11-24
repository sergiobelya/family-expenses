<?php

namespace App\Presentation\Telegram\Flow\Catalog;

use App\Core\Infrastructure\Messenger\MessageBusDispatcherInterface;
use App\Core\Infrastructure\Persistence\Redis\UserInteractionState;
use App\Core\Infrastructure\Persistence\Redis\UserInteractionStateRepository;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\Keyboard\InlineKeyboardBuilder;
use RuntimeException;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class CatalogFlowUtils
{
    public const string FLOW_NAME = 'catalog';
    public const int PAGE_SIZE = 8;

    public function __construct(
        private TelegramApiClientInterface $telegramClient,
        private UserInteractionStateRepository $stateRepository,
        private MessageBusDispatcherInterface $messageBusDispatcher,
        private TranslatorInterface $translator,
    ) {
    }

    public function getStateRepository(): UserInteractionStateRepository
    {
        return $this->stateRepository;
    }

    /**
     * @param array<string, mixed> $markup
     * @param array<string, mixed> $params
     */
    public function render(
        TelegramFamilyMemberContext $context,
        string $textOrKey,
        array $markup,
        ?UserInteractionState $previousState = null,
        array $params = []
    ): void {
        $text = str_starts_with($textOrKey, 'catalog.') ? $this->trans($textOrKey, $params) : $textOrKey;
        $state = $previousState ?? $this->stateRepository->get($context->telegramUserId);
        $messageId = isset($state?->data['message_id']) ? (int) $state->data['message_id'] : null;
        if ($messageId !== null) {
            $response = $this->telegramClient->editMessageText(
                $context->chatId,
                $messageId,
                $text,
                $markup
            );
            if (!($response['ok'] ?? false)) {
                $response = $this->telegramClient->sendMessage($context->chatId, $text, $markup);
                if (!($response['ok'] ?? false)) {
                    throw new RuntimeException('Unable to render catalog card: ' . ($response['description'] ?? 'unknown Telegram API error'));
                }
                if (!$this->telegramClient->deleteMessage($context->chatId, $messageId)) {
                    $closed = $this->telegramClient->editMessageText(
                        $context->chatId,
                        $messageId,
                        $text,
                        ['inline_keyboard' => []]
                    );
                    if (!($closed['ok'] ?? false)) {
                        throw new RuntimeException('Unable to close the previous catalog card after sending a replacement.');
                    }
                }
                $messageId = null;
            }
        } else {
            $response = $this->telegramClient->sendMessage($context->chatId, $text, $markup);
            if (!($response['ok'] ?? false)) {
                throw new RuntimeException('Unable to render catalog card: ' . ($response['description'] ?? 'unknown Telegram API error'));
            }
        }

        $responseMessageId = $response['result']['message_id'] ?? null;
        $current = $this->stateRepository->get($context->telegramUserId);
        if ($current !== null && $current->flow === self::FLOW_NAME) {
            $data = $current->data;
            if ($responseMessageId !== null) {
                $data['message_id'] = (int) $responseMessageId;
            } elseif ($messageId !== null) {
                $data['message_id'] = $messageId;
            }
            $this->stateRepository->set($this->state($context, $current->step, $data));
        }
    }

    public function runCommand(object $command): mixed
    {
        return $this->messageBusDispatcher->dispatchCommand($command);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function state(TelegramFamilyMemberContext $context, string $step, array $data): UserInteractionState
    {
        $old = $this->stateRepository->get($context->telegramUserId);
        if (isset($old?->data['message_id'])) {
            $data['message_id'] = $old->data['message_id'];
        }

        return new UserInteractionState($context->telegramUserId, self::FLOW_NAME, $step, $data);
    }

    /** @return array<string, mixed> */
    public function backKeyboard(string $callback): array
    {
        return (new InlineKeyboardBuilder())
            ->button($this->trans('common.back'), $callback)
            ->toArray();
    }

    /** @return array<string, mixed> */
    public function formKeyboard(string $callback): array
    {
        return (new InlineKeyboardBuilder())
            ->button($this->trans('common.back'), $callback)
            ->button($this->trans('common.cancel'), CallbackActions::CATALOG_CANCEL)
            ->toArray();
    }

    public function namePrompt(string $value): string
    {
        return $value === ''
            ? $this->trans('catalog.prompt_name')
            : $this->trans('catalog.prompt_name_current', ['%{name}' => $this->escape($value)]);
    }

    public function parseUuid(string $value): ?Uuid
    {
        return Uuid::isValid($value) ? Uuid::fromString($value) : null;
    }

    public function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function escapeButton(string $value): string
    {
        return mb_strlen($value) > 60 ? mb_substr($value, 0, 57) . '…' : $value;
    }

    /** @param array<string|int, mixed> $parameters */
    public function trans(string $key, array $parameters = [], ?string $locale = null): string
    {
        return $this->translator->trans($key, $parameters, $locale);
    }

    public function translateMessage(TranslatableMessage $message, string $locale): string
    {
        return $message->trans($this->translator, $locale);
    }
}

<?php

namespace App\Presentation\Telegram\Context;

use App\Core\Infrastructure\Messenger\MessageBusDispatcherInterface;
use App\Identity\Application\Command\EnsureUserExistsCommand;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Keyboard\SupportedLanguages;
use Symfony\Contracts\Translation\LocaleAwareInterface;

readonly class TelegramUserContextResolver
{
    public function __construct(
        private MessageBusDispatcherInterface $messageBusDispatcher,
        private ResolveActiveFamilyForUser $resolveActiveFamily,
        private LocaleAwareInterface $translator
    ) {}

    public function resolve(TelegramUpdate $update): ?TelegramUserContext
    {
        if ($update->fromUserId === null || $update->chatId === null) {
            return null;
        }

        $detectedLanguage = $this->detectTelegramUserLanguage($update);

        $user = $this->messageBusDispatcher->dispatchCommand(new EnsureUserExistsCommand(
            telegramUserId: $update->fromUserId,
            language: $detectedLanguage
        ));

        $userLanguage = $user->getLanguage();
        $this->translator->setLocale($userLanguage);

        $activeFamily = ($this->resolveActiveFamily)($user);

        $context = new TelegramUserContext(
            user: $user,
            language: $userLanguage,
            telegramUserId: $update->fromUserId,
            chatId: $update->chatId,
            fromUsername: $update->fromUsername,
            fromFirstName: $update->fromFirstName
        );

        return $activeFamily === null
            ? $context
            : new TelegramFamilyMemberContext($context, $activeFamily);
    }

    private function detectTelegramUserLanguage(TelegramUpdate $update): string
    {
        $detectedLanguage = SupportedLanguages::DEFAULT;

        if ($update->languageCode !== null) {
            $code = strtolower(substr($update->languageCode, 0, 2));
            if (SupportedLanguages::isSupported($code)) {
                $detectedLanguage = $code;
            }
        }

        return $detectedLanguage;
    }
}

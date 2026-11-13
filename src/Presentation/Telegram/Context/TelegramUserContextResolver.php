<?php

namespace App\Presentation\Telegram\Context;

use App\Identity\Application\Command\EnsureUserExistsCommand;
use App\Identity\Application\Command\EnsureUserExistsHandler;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use Symfony\Contracts\Translation\LocaleAwareInterface;

class TelegramUserContextResolver
{
    private const array SUPPORTED_LANGUAGES = ['en', 'uk', 'pl', 'ru'];

    public function __construct(
        private EnsureUserExistsHandler $ensureUserExistsHandler,
        private ResolveActiveFamilyForUser $resolveActiveFamily,
        private LocaleAwareInterface $translator
    ) {}

    public function resolve(TelegramUpdate $update): ?TelegramUserContext
    {
        if ($update->fromUserId === null || $update->chatId === null) {
            return null;
        }

        $detectedLanguage = 'en';
        if ($update->languageCode !== null) {
            $code = strtolower(substr($update->languageCode, 0, 2));
            if (in_array($code, self::SUPPORTED_LANGUAGES, true)) {
                $detectedLanguage = $code;
            }
        }

        $user = ($this->ensureUserExistsHandler)(new EnsureUserExistsCommand(
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
}

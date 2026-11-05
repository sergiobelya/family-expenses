<?php

namespace App\Presentation\Telegram\Context;

use App\Family\Domain\Entity\Family;
use App\Family\Domain\Repository\FamilyMemberRepositoryInterface;
use App\Family\Domain\Repository\FamilyRepositoryInterface;
use App\Identity\Application\Command\EnsureUserExistsCommand;
use App\Identity\Application\Command\EnsureUserExistsHandler;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use Symfony\Contracts\Translation\LocaleAwareInterface;

class TelegramUserContextResolver
{
    private const array SUPPORTED_LANGUAGES = ['en', 'uk', 'pl', 'ru'];

    public function __construct(
        private EnsureUserExistsHandler $ensureUserExistsHandler,
        private UserRepositoryInterface $userRepository,
        private FamilyRepositoryInterface $familyRepository,
        private FamilyMemberRepositoryInterface $familyMemberRepository,
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

        $activeFamily = null;
        $activeFamilyId = $user->getActiveFamilyId();

        if ($activeFamilyId !== null) {
            $family = $this->familyRepository->findById($activeFamilyId);
            if ($family !== null) {
                $member = $this->familyMemberRepository->findByFamilyAndUser($family->getId(), $user->getId());
                if ($member !== null && $member->isActive()) {
                    $activeFamily = $family;
                } else {
                    // Stale family membership
                    $user->switchActiveFamily(null);
                    $this->userRepository->save($user);
                }
            } else {
                $user->switchActiveFamily(null);
                $this->userRepository->save($user);
            }
        }

        return new TelegramUserContext(
            user: $user,
            activeFamily: $activeFamily,
            language: $userLanguage,
            telegramUserId: $update->fromUserId,
            chatId: $update->chatId,
            fromUsername: $update->fromUsername,
            fromFirstName: $update->fromFirstName
        );
    }
}

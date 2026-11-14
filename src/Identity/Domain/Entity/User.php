<?php

namespace App\Identity\Domain\Entity;

use App\Identity\Domain\Event\UserActiveFamilySwitched;
use App\Identity\Domain\Event\UserCreated;
use App\Identity\Domain\Event\UserLanguageChanged;
use App\Identity\Domain\Enum\UserLanguage;
use App\Identity\Domain\Exception\UnsupportedUserLanguageException;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;
use App\Core\Domain\Aggregate\AggregateRoot;
use DateTimeImmutable;
use DateTimeZone;

class User extends AggregateRoot
{
    private UserId $id;
    private int $telegramUserId;
    private UserLanguage $language;
    private ?FamilyId $activeFamilyId = null;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    public function __construct(
        int $telegramUserId,
        string $language = UserLanguage::ENGLISH->value,
        ?UserId $id = null,
        ?DateTimeImmutable $now = null
    ) {
        $this->id = $id ?? UserId::generate();
        $this->telegramUserId = $telegramUserId;
        $this->setLanguageInternal($language);
        $this->createdAt = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->updatedAt = $this->createdAt;
    }

    public static function create(
        int $telegramUserId,
        string $language = UserLanguage::ENGLISH->value,
        ?UserId $id = null,
        ?DateTimeImmutable $now = null
    ): self {
        $user = new self($telegramUserId, $language, $id, $now);
        $user->recordEvent(new UserCreated(
            $user->getId(),
            $telegramUserId,
            $user->getLanguage(),
            $user->getCreatedAt()
        ));

        return $user;
    }

    public function getId(): UserId
    {
        return $this->id;
    }

    public function getTelegramUserId(): int
    {
        return $this->telegramUserId;
    }

    public function getLanguage(): string
    {
        return $this->language->value;
    }

    public function setLanguage(string $language, ?DateTimeImmutable $now = null): void
    {
        $oldLanguage = $this->language->value;
        $this->setLanguageInternal($language);

        if ($oldLanguage !== $this->language->value) {
            $this->updatedAt = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $this->recordEvent(new UserLanguageChanged(
                $this->getId(),
                $oldLanguage,
                $this->language->value,
                $this->updatedAt
            ));
        }
    }

    public function getActiveFamilyId(): ?FamilyId
    {
        return $this->activeFamilyId;
    }

    public function switchActiveFamily(?FamilyId $activeFamilyId, ?DateTimeImmutable $now = null): void
    {
        $oldFamilyId = $this->activeFamilyId;
        $newFamilyId = $activeFamilyId;
        $this->activeFamilyId = $newFamilyId;
        $this->updatedAt = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $familyChanged = $oldFamilyId === null
            ? $newFamilyId !== null
            : $newFamilyId === null || !$oldFamilyId->equals($newFamilyId);

        if ($familyChanged) {
            $this->recordEvent(new UserActiveFamilySwitched(
                $this->getId(),
                $oldFamilyId,
                $newFamilyId,
                $this->updatedAt
            ));
        }
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function setLanguageInternal(string $language): void
    {
        $normalized = strtolower(trim($language));
        $userLanguage = UserLanguage::tryFrom($normalized);
        if ($userLanguage === null) {
            throw UnsupportedUserLanguageException::forLanguage($language);
        }

        $this->language = $userLanguage;
    }
}

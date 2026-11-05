<?php

namespace App\Identity\Domain\Entity;

use App\Identity\Domain\Event\UserActiveFamilySwitched;
use App\Identity\Domain\Event\UserCreated;
use App\Identity\Domain\Event\UserLanguageChanged;
use App\Core\Domain\Aggregate\AggregateRoot;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

class User extends AggregateRoot
{
    public const array SUPPORTED_LANGUAGES = ['en', 'uk', 'pl', 'ru'];

    private Uuid $id;
    private int $telegramUserId;
    private string $language;
    private ?Uuid $activeFamilyId = null;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    public function __construct(
        int $telegramUserId,
        string $language = 'en',
        ?Uuid $id = null,
        ?DateTimeImmutable $now = null
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->telegramUserId = $telegramUserId;
        $this->setLanguageInternal($language);
        $this->createdAt = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->updatedAt = $this->createdAt;
    }

    public static function create(
        int $telegramUserId,
        string $language = 'en',
        ?Uuid $id = null,
        ?DateTimeImmutable $now = null
    ): self {
        $user = new self($telegramUserId, $language, $id, $now);
        $user->recordEvent(new UserCreated($user->getId(), $telegramUserId, $user->getLanguage(), $user->getCreatedAt()));

        return $user;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getTelegramUserId(): int
    {
        return $this->telegramUserId;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language, ?DateTimeImmutable $now = null): void
    {
        $oldLanguage = $this->language;
        $this->setLanguageInternal($language);

        if ($oldLanguage !== $this->language) {
            $this->updatedAt = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $this->recordEvent(new UserLanguageChanged($this->id, $oldLanguage, $this->language, $this->updatedAt));
        }
    }

    public function getActiveFamilyId(): ?Uuid
    {
        return $this->activeFamilyId;
    }

    public function setActiveFamilyId(?Uuid $activeFamilyId, ?DateTimeImmutable $now = null): void
    {
        $oldFamilyId = $this->activeFamilyId;
        $this->activeFamilyId = $activeFamilyId;
        $this->updatedAt = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));

        if (($oldFamilyId === null && $activeFamilyId !== null) ||
            ($oldFamilyId !== null && $activeFamilyId === null) ||
            ($oldFamilyId !== null && $activeFamilyId !== null && !$oldFamilyId->equals($activeFamilyId))) {
            $this->recordEvent(new UserActiveFamilySwitched($this->id, $oldFamilyId, $activeFamilyId, $this->updatedAt));
        }
    }

    public function switchActiveFamily(?Uuid $activeFamilyId, ?DateTimeImmutable $now = null): void
    {
        $this->setActiveFamilyId($activeFamilyId, $now);
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
        if (!in_array($normalized, self::SUPPORTED_LANGUAGES, true)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported language "%s". Supported languages: %s',
                $language,
                implode(', ', self::SUPPORTED_LANGUAGES)
            ));
        }

        $this->language = $normalized;
    }
}

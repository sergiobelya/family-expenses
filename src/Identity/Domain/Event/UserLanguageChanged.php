<?php

namespace App\Identity\Domain\Event;

use App\Core\Domain\Event\AuditableEventInterface;
use App\Core\Domain\ValueObject\UserId;
use DateTimeImmutable;
use DateTimeZone;

final readonly class UserLanguageChanged implements AuditableEventInterface
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        public UserId $userId,
        public string $oldLanguage,
        public string $newLanguage,
        ?DateTimeImmutable $occurredAt = null
    ) {
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getFamilyId(): null
    {
        return null;
    }

    public function getActorUserId(): UserId
    {
        return $this->userId;
    }

    public function getEntityType(): string
    {
        return 'user';
    }

    public function getEntityId(): string
    {
        return (string) $this->userId;
    }

    public function getAction(): string
    {
        return 'user.language_changed';
    }

    public function getBeforeData(): array
    {
        return ['language' => $this->oldLanguage];
    }

    public function getAfterData(): array
    {
        return ['language' => $this->newLanguage];
    }

}

<?php

namespace App\Identity\Domain\Event;

use App\Core\Domain\Event\AuditableEventInterface;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Uid\Uuid;

final readonly class UserLanguageChanged implements AuditableEventInterface
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        public Uuid $userId,
        public string $oldLanguage,
        public string $newLanguage,
        ?DateTimeImmutable $occurredAt = null
    ) {
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function getEventName(): string
    {
        return 'user.language_changed';
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getFamilyId(): ?Uuid
    {
        return null;
    }

    public function getActorUserId(): ?Uuid
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

    public function getBeforeData(): ?array
    {
        return ['language' => $this->oldLanguage];
    }

    public function getAfterData(): ?array
    {
        return ['language' => $this->newLanguage];
    }

    public function toPayload(): array
    {
        return [
            'user_id' => (string) $this->userId,
            'old_language' => $this->oldLanguage,
            'new_language' => $this->newLanguage,
        ];
    }
}

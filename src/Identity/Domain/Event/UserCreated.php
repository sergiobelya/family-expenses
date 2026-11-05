<?php

namespace App\Identity\Domain\Event;

use App\Core\Domain\Event\AuditableEventInterface;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Uid\Uuid;

final readonly class UserCreated implements AuditableEventInterface
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        public Uuid $userId,
        public int $telegramUserId,
        public string $language,
        ?DateTimeImmutable $occurredAt = null
    ) {
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function getEventName(): string
    {
        return 'user.created';
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
        return 'user.created';
    }

    public function getBeforeData(): ?array
    {
        return null;
    }

    public function getAfterData(): ?array
    {
        return [
            'id' => (string) $this->userId,
            'telegram_user_id' => $this->telegramUserId,
            'language' => $this->language,
        ];
    }

    public function toPayload(): array
    {
        return [
            'user_id' => (string) $this->userId,
            'telegram_user_id' => $this->telegramUserId,
            'language' => $this->language,
        ];
    }
}

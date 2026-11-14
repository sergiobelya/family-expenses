<?php

namespace App\Tests\Unit\Domain\Family\Entity;

use App\Family\Domain\Entity\Invitation;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class InvitationTest extends TestCase
{
    public function testInvitationValidityAndRevocation(): void
    {
        $familyId = Uuid::v7();
        $userId = Uuid::v7();
        $token = 'random_secret_token';
        $tokenHash = hash('sha256', $token);

        $now = new DateTimeImmutable('2026-10-31 12:00:00', new DateTimeZone('UTC'));
        $invitation = Invitation::create(
            familyId: new FamilyId($familyId),
            createdByUserId: new UserId($userId),
            tokenHash: $tokenHash,
            maxUses: 2,
            now: $now
        );

        $this->assertFalse($invitation->isExpired($now));
        $this->assertFalse($invitation->isRevoked());
        $this->assertTrue($invitation->canBeUsed(0, $now));
        $this->assertTrue($invitation->canBeUsed(1, $now));
        $this->assertFalse($invitation->canBeUsed(2, $now)); // Limit reached

        // Test expiration after 49 hours
        $after49h = $now->modify('+49 hours');
        $this->assertTrue($invitation->isExpired($after49h));
        $this->assertFalse($invitation->canBeUsed(0, $after49h));

        // Test revocation
        $invitation->revoke(new UserId($userId), $now);
        $this->assertTrue($invitation->isRevoked());
        $this->assertFalse($invitation->canBeUsed(0, $now));
    }

    public function testInvalidMaxUsesThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Invitation::create(
            familyId: new FamilyId(Uuid::v7()),
            createdByUserId: new UserId(Uuid::v7()),
            tokenHash: 'hash',
            maxUses: 0
        );
    }
}

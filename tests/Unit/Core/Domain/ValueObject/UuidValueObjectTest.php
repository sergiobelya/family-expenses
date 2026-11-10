<?php

namespace App\Tests\Unit\Core\Domain\ValueObject;

use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;
use App\Core\Infrastructure\Persistence\Doctrine\Type\FamilyIdType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class UuidValueObjectTest extends TestCase
{
    public function testIdentifiersRoundTripAndRemainTypeDistinct(): void
    {
        $familyId = FamilyId::generate();
        $sameFamilyId = FamilyId::fromString((string) $familyId);
        $userId = UserId::fromString((string) $familyId);

        self::assertTrue($familyId->equals($sameFamilyId));
        self::assertFalse($familyId->equals($userId));
        self::assertSame((string) $familyId, (string) $userId);
    }

    public function testDoctrineTypePreservesIdentifierTypeAndAcceptsBoundaryUuid(): void
    {
        $type = new FamilyIdType();
        $platform = new PostgreSQLPlatform();
        $familyId = FamilyId::generate();

        self::assertSame((string) $familyId, $type->convertToDatabaseValue($familyId, $platform));
        self::assertTrue($familyId->equals($type->convertToPHPValue((string) $familyId, $platform)));
        self::assertSame(
            (string) $familyId,
            $type->convertToDatabaseValue(Uuid::fromString((string) $familyId), $platform)
        );

        $this->expectException(InvalidArgumentException::class);
        $type->convertToDatabaseValue(UserId::generate(), $platform);
    }
}

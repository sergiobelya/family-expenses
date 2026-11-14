<?php

namespace App\Family\Infrastructure\Persistence\Doctrine\Type;

use App\Family\Domain\ValueObject\FamilyName;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use InvalidArgumentException;

final class FamilyNameType extends Type
{
    public function getName(): string
    {
        return 'family_name';
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!$value instanceof FamilyName) {
            throw new InvalidArgumentException('Expected FamilyName value object.');
        }

        return (string) $value;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?FamilyName
    {
        if ($value === null || $value instanceof FamilyName) {
            return $value;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException('Cannot convert value to FamilyName.');
        }

        return FamilyName::fromString($value);
    }
}

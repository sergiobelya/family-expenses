<?php

namespace App\Core\Infrastructure\Persistence\Doctrine\Type;

use App\Core\Domain\ValueObject\CountryCode;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use InvalidArgumentException;

final class CountryCodeType extends Type
{
    public function getName(): string
    {
        return 'country_code';
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
        if (!$value instanceof CountryCode) {
            throw new InvalidArgumentException('Expected a country code value object.');
        }

        return $value->value;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?CountryCode
    {
        if ($value === null || $value instanceof CountryCode) {
            return $value;
        }
        if (!is_string($value)) {
            throw new InvalidArgumentException('Cannot convert database value to a country code.');
        }

        return CountryCode::fromString($value);
    }
}

<?php

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Type;

use App\Catalog\Domain\ValueObject\CatalogName;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use InvalidArgumentException;

abstract class CatalogNameType extends Type
{
    /**
     * @return class-string<CatalogName>
     */
    abstract protected function getNameClass(): string;

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        $class = $this->getNameClass();
        if ($value === null) {
            return null;
        }
        if (!$value instanceof $class) {
            throw new InvalidArgumentException('Expected a catalog name value object.');
        }

        return $value->value;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?CatalogName
    {
        $class = $this->getNameClass();
        if ($value === null || $value instanceof $class) {
            return $value;
        }
        if (!is_string($value)) {
            throw new InvalidArgumentException('Cannot convert database value to a catalog name.');
        }

        return $class::fromString($value);
    }
}

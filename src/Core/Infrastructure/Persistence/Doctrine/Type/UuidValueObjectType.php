<?php

namespace App\Core\Infrastructure\Persistence\Doctrine\Type;

use App\Core\Domain\ValueObject\UuidValueObject;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

abstract class UuidValueObjectType extends Type
{
    /**
     * @return class-string<UuidValueObject>
     */
    abstract protected function getValueObjectClass(): string;

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }
        $class = $this->getValueObjectClass();
        if (!$value instanceof $class) {
            if ($value instanceof Uuid) {
                return (string) $value;
            }
            throw new InvalidArgumentException('Expected an identifier value object.');
        }

        return (string) $value;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?UuidValueObject
    {
        $class = $this->getValueObjectClass();
        if ($value === null || $value instanceof $class) {
            return $value;
        }

        if (!$value instanceof Uuid && !is_string($value)) {
            throw new InvalidArgumentException('Cannot convert database value to an identifier.');
        }

        return $class::fromString((string) $value);
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getGuidTypeDeclarationSQL($column);
    }
}

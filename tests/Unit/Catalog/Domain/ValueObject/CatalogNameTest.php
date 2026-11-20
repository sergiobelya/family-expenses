<?php

namespace App\Tests\Unit\Catalog\Domain\ValueObject;

use App\Catalog\Domain\ValueObject\CatalogName;
use App\Catalog\Domain\ValueObject\CategoryName;
use App\Catalog\Domain\ValueObject\StoreName;
use App\Core\Domain\Exception\InvalidValueException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CatalogNameTest extends TestCase
{
    #[DataProvider('hardKeyVectors')]
    public function testHardKeyNormalization(string $input, string $expected): void
    {
        self::assertSame($expected, CatalogName::fromString($input)->getDedupeKey());
    }

    /** @return iterable<array{string, string}> */
    public static function hardKeyVectors(): iterable
    {
        yield ['Biedronka', 'biedronka'];
        yield ['biedronka', 'biedronka'];
        yield ['Biedronka ', 'biedronka'];
        yield ['Бєдронка', 'biedronka'];
        yield ['бедронка', 'bedronka'];
        yield ['Чайка', 'chaika'];
        yield ['Київ', 'kyiv'];
        yield ['Йосип', 'yosyp'];
        yield ['ё', 'yo'];
        yield ['Żabka', 'zabka'];
        yield ['zabka', 'zabka'];
        yield ['Żabka.', 'zabka'];
        yield ['Єдність', 'yednist'];
        yield ['«Єдність»', 'yednist'];
        yield ['Biedronka - Rynek', 'biedronka rynek'];
        yield ['Biedronka Rynek', 'biedronka rynek'];
        yield ['Biedronka 🍎', 'biedronka'];
        yield ['Pepco™', 'pepco'];
        yield ['Pepco℠', 'pepco'];
        yield ['Pepco', 'pepco'];
        yield ['ＡＢＣ', 'abc'];
        yield ['ABC', 'abc'];
        yield ['ﬁesta', 'fiesta'];
        yield ['Łowicz', 'lowicz'];
        yield ['Lowicz', 'lowicz'];
        yield ["McDonald's", 'mcdonalds'];
        yield ['McDonalds', 'mcdonalds'];
        yield ["McDonald＇s", 'mcdonalds'];
        yield ['мясо', 'miaso'];
        yield ["м'ясо", 'miaso'];
        yield ['м’ясо', 'miaso'];
        yield ['мʼясо', 'miaso'];
        yield ['м`ясо', 'miaso'];
        yield ['м´ясо', 'miaso'];
        yield ['м‘ясо', 'miaso'];
        yield ['мʹясо', 'miaso'];
        yield ["м＇ясо", 'miaso'];
        yield ['A&B', 'a and b'];
        yield ['A & B', 'a and b'];
        yield ['A and B', 'a and b'];
        yield ['Stokrotka+', 'stokrotka plus'];
        yield ['Stokrotka Plus', 'stokrotka plus'];
        yield ['Mc Donalds', 'mc donalds'];
        yield ['Щ', 'shch'];
        yield ['зг', 'zgh'];
    }

    public function testDisplayNameIsTrimmedButOtherwisePreserved(): void
    {
        self::assertSame('Żabka.', CatalogName::fromString('  Żabka.  ')->getValue());
        self::assertSame('Żabka', CatalogName::fromString("\u{00A0}Żabka\u{00A0}")->getValue());
    }

    public function testStoreAndCategoryNamesHaveDistinctTypes(): void
    {
        self::assertInstanceOf(StoreName::class, StoreName::fromString('Market'));
        self::assertInstanceOf(CategoryName::class, CategoryName::fromString('Food'));
    }

    public function testRejectsNamesWithoutLettersOrDigits(): void
    {
        $this->expectException(InvalidValueException::class);
        CatalogName::fromString('🍎 !!!');
    }

    public function testRejectsPunctuationOnlyName(): void
    {
        $this->expectException(InvalidValueException::class);
        CatalogName::fromString('!!!');
    }

    public function testRejectsNamesLongerThanOneHundredCharacters(): void
    {
        $this->expectException(InvalidValueException::class);
        CatalogName::fromString(str_repeat('a', 101));
    }
}

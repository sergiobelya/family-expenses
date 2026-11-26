<?php

namespace App\Tests\Unit\Presentation\Telegram\Flow\Expense;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

final class ExpenseSearchTooLongTranslationTest extends TestCase
{
    #[DataProvider('pluralForms')]
    public function testSearchLimitUsesLocalePluralForms(string $locale, int $limit, string $expected): void
    {
        $translator = new Translator($locale);
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', dirname(__DIR__, 6) . '/translations/messages.' . $locale . '.yaml', $locale);

        self::assertSame(
            $expected,
            $translator->trans('expense.add.store.search.too_long', [
                '%count%' => $limit,
                '%maxLength%' => $limit,
            ])
        );
    }

    /** @return iterable<string, array{string, int, string}> */
    public static function pluralForms(): iterable
    {
        yield 'English singular' => ['en', 1, 'Search text must be no longer than 1 character.'];
        yield 'English plural' => ['en', 2, 'Search text must be no longer than 2 characters.'];
        yield 'English configured limit' => ['en', 100, 'Search text must be no longer than 100 characters.'];
        yield 'Ukrainian singular' => ['uk', 1, 'Максимальна довжина запиту — 1 символ.'];
        yield 'Ukrainian few' => ['uk', 2, 'Максимальна довжина запиту — 2 символи.'];
        yield 'Ukrainian many' => ['uk', 5, 'Максимальна довжина запиту — 5 символів.'];
        yield 'Ukrainian one after 20' => ['uk', 21, 'Максимальна довжина запиту — 21 символ.'];
        yield 'Ukrainian configured limit' => ['uk', 100, 'Максимальна довжина запиту — 100 символів.'];
        yield 'Polish singular' => ['pl', 1, 'Maksymalna długość zapytania to 1 znak.'];
        yield 'Polish few' => ['pl', 2, 'Maksymalna długość zapytania to 2 znaki.'];
        yield 'Polish many' => ['pl', 5, 'Maksymalna długość zapytania to 5 znaków.'];
        yield 'Polish configured limit' => ['pl', 100, 'Maksymalna długość zapytania to 100 znaków.'];
        yield 'Russian singular' => ['ru', 1, 'Максимальная длина запроса — 1 символ.'];
        yield 'Russian few' => ['ru', 2, 'Максимальная длина запроса — 2 символа.'];
        yield 'Russian many' => ['ru', 5, 'Максимальная длина запроса — 5 символов.'];
        yield 'Russian configured limit' => ['ru', 100, 'Максимальная длина запроса — 100 символов.'];
    }
}
<?php

namespace App\Core\Application;

use App\Core\Domain\ValueObject\CurrencyCode;
use ResourceBundle;

final readonly class CurrencyCatalog
{
    /**
     * @return list<Currency>
     */
    public function all(string $locale): array
    {
        $codes = CurrencyCode::supportedCodes();
        sort($codes);

        return array_map(
            fn (string $code): Currency => new Currency($code, $this->name($code, $locale)),
            $codes
        );
    }

    /**
     * @return list<Currency>
     */
    public function search(string $query, string $locale): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $normalizedQuery = mb_strtolower($query);

        return array_values(array_filter(
            $this->all($locale),
            function (Currency $currency) use ($normalizedQuery): bool {
                return mb_stripos($currency->code, $normalizedQuery) !== false
                    || mb_stripos($currency->localizedName, $normalizedQuery) !== false
                    || mb_stripos($this->name($currency->code, 'en'), $normalizedQuery) !== false;
            }
        ));
    }

    private function name(string $code, string $locale): string
    {
        foreach (array_unique([$locale, 'en']) as $language) {
            $bundle = ResourceBundle::create($language, 'ICUDATA-curr');
            if (!$bundle instanceof ResourceBundle) {
                continue;
            }

            $currencies = $bundle->get('Currencies');
            $currency = $currencies instanceof ResourceBundle ? $currencies->get($code) : null;
            $name = $currency instanceof ResourceBundle ? $currency->get(1) : null;
            if (is_string($name)) {
                return $name;
            }
        }

        return $code;
    }
}

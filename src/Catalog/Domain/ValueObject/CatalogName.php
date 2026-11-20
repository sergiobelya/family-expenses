<?php

namespace App\Catalog\Domain\ValueObject;

use App\Core\Domain\Exception\ErrorType;
use App\Core\Domain\Exception\InvalidValueException;
use Normalizer;

readonly class CatalogName
{
    final protected function __construct(
        private string $value,
        private string $dedupeKey
    ) {}

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->getValue();
    }

    public function getDedupeKey(): string
    {
        return $this->dedupeKey;
    }

    public static function fromString(string $value): static
    {
        $displayName = self::trimAndValidate($value);
        $normalized = self::normalizeUnicode($displayName);
        $dedupeKey = self::buildDedupeKey($normalized);

        return new static($displayName, $dedupeKey);
    }

    private static function trimAndValidate(string $value): string
    {
        $displayName = preg_replace('/^\s+|\s+$/u', '', $value);
        if (!is_string($displayName)) {
            throw new InvalidValueException(ErrorType::CATALOG_NAME_INVALID);
        }
        if ($displayName === '' || mb_strlen($displayName) > 100) {
            throw new InvalidValueException(ErrorType::CATALOG_NAME_INVALID);
        }
        return $displayName;
    }

    private static function normalizeUnicode(string $displayName): string
    {
        $apostrophes = ["'", '’', 'ʼ', '`', '´', '‘', 'ʹ'];
        $name = str_replace([...$apostrophes, '™', '℠'], '', $displayName);
        $name = Normalizer::normalize($name, Normalizer::FORM_KC);
        if (!is_string($name)) {
            throw new InvalidValueException(ErrorType::CATALOG_NAME_INVALID);
        }

        $name = mb_strtolower(str_replace($apostrophes, '', $name), 'UTF-8');
        $name = self::transliterateUkrainianCyrillic($name);
        $name = strtr($name, [
            'ł' => 'l',
            'ø' => 'o',
            'đ' => 'd',
            'ß' => 'ss',
            'æ' => 'ae',
        ]);
        $name = Normalizer::normalize($name, Normalizer::FORM_D);
        if (!is_string($name)) {
            throw new InvalidValueException(ErrorType::CATALOG_NAME_INVALID);
        }

        return self::filterSpecialChars($name);
    }

    private static function filterSpecialChars(string $name): string
    {
        $chars = preg_split('//u', $name, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false) {
            throw new InvalidValueException(ErrorType::CATALOG_NAME_INVALID);
        }

        $normalized = '';
        $previousWasLetter = false;
        $previousLetterWasLatin = false;
        foreach ($chars as $char) {
            if (preg_match('/^\p{M}$/u', $char) === 1) {
                if ($previousWasLetter && !$previousLetterWasLatin) {
                    $normalized .= $char;
                } elseif (!$previousWasLetter) {
                    $normalized .= ' ';
                }
                continue;
            }

            if ($char === '&') {
                $normalized .= ' and ';
                $previousWasLetter = false;
                $previousLetterWasLatin = false;
                continue;
            }
            if ($char === '+') {
                $normalized .= ' plus ';
                $previousWasLetter = false;
                $previousLetterWasLatin = false;
                continue;
            }

            if (preg_match('/^[\p{P}\p{S}\p{Z}\p{Cc}\p{Cf}]$/u', $char) === 1) {
                $normalized .= ' ';
                $previousWasLetter = false;
                $previousLetterWasLatin = false;
                continue;
            }

            $normalized .= $char;
            $previousWasLetter = preg_match('/^\p{L}$/u', $char) === 1;
            $previousLetterWasLatin = $previousWasLetter && preg_match('/^\p{Latin}$/u', $char) === 1;
        }

        return $normalized;
    }

    private static function buildDedupeKey(string $normalized): string
    {
        $normalized = Normalizer::normalize($normalized, Normalizer::FORM_C);
        if (!is_string($normalized)) {
            throw new InvalidValueException(ErrorType::CATALOG_NAME_INVALID);
        }

        $key = preg_replace('/\s+/u', ' ', trim($normalized));
        if (!is_string($key) || preg_match('/[\p{L}\p{N}]/u', $key) !== 1) {
            throw new InvalidValueException(ErrorType::CATALOG_NAME_INVALID);
        }

        return $key;
    }

    private static function transliterateUkrainianCyrillic(string $value): string
    {
        $chars = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false) {
            throw new InvalidValueException(ErrorType::CATALOG_NAME_INVALID);
        }

        $map = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'h', 'ґ' => 'g',
            'д' => 'd', 'е' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'y',
            'і' => 'i', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
            'у' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'shch', 'ь' => '', 'ы' => 'y', 'э' => 'e',
            'ъ' => '', 'ё' => 'yo',
        ];

        $result = '';
        $count = count($chars);
        for ($i = 0; $i < $count; ++$i) {
            $char = $chars[$i];
            if ($char === 'з' && ($chars[$i + 1] ?? null) === 'г') {
                $result .= 'zgh';
                ++$i;
                continue;
            }

            $wordStart = $i === 0 || preg_match('/^\p{L}$/u', $chars[$i - 1]) !== 1;
            $mapped = match ($char) {
                'є' => $wordStart ? 'ye' : 'ie',
                'ї' => $wordStart ? 'yi' : 'i',
                'й' => $wordStart ? 'y' : 'i',
                'ю' => $wordStart ? 'yu' : 'iu',
                'я' => $wordStart ? 'ya' : 'ia',
                default => $map[$char] ?? $char,
            };
            $result .= $mapped;
        }

        return $result;
    }
}

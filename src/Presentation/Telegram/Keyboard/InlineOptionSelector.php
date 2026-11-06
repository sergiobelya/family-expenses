<?php

namespace App\Presentation\Telegram\Keyboard;

use Normalizer;

final class InlineOptionSelector
{
    private const int PAGE_SIZE = 8;

    /**
     * @param array<string, string> $options
     * @return array{inline_keyboard: list<list<array{text: string, callback_data?: string, url?: string}>>}
     */
    public function buildKeyboard(
        array $options,
        string $callbackPrefix,
        ?string $selectedValue,
        ?string $query,
        int $page,
        string $searchLabel,
        string $clearSearchLabel,
        string $previousLabel,
        string $nextLabel,
        string $backLabel,
        string $backCallback
    ): array {
        $filteredOptions = $this->filterOptions($options, $query ?? '');
        $pageCount = max(1, (int) ceil(count($filteredOptions) / self::PAGE_SIZE));
        $page = min(max($page, 0), $pageCount - 1);
        $visibleOptions = array_slice($filteredOptions, $page * self::PAGE_SIZE, self::PAGE_SIZE, true);

        $builder = new InlineKeyboardBuilder();
        $builder->button($searchLabel, $callbackPrefix . ':search')->row();

        if ($query !== null && $query !== '') {
            $builder->button($clearSearchLabel, $callbackPrefix . ':clear')->row();
        }

        foreach ($visibleOptions as $value => $label) {
            $badge = $selectedValue === $value ? ' ✅' : '';
            $builder->button($label . $badge, $callbackPrefix . ':set:' . $value)->row();
        }

        if ($pageCount > 1) {
            if ($page > 0) {
                $builder->button($previousLabel, $callbackPrefix . ':page:' . ($page - 1));
            }
            if ($page < $pageCount - 1) {
                $builder->button($nextLabel, $callbackPrefix . ':page:' . ($page + 1));
            }
            $builder->row();
        }

        $builder->button($backLabel, $backCallback);

        return $builder->toArray();
    }

    /**
     * @param array<string, string> $options
     * @return array<string, string>
     */
    public function filterOptions(array $options, string $query): array
    {
        $query = $this->normalize($query);
        if ($query === '') {
            return $options;
        }

        return array_filter(
            $options,
            fn(string $label, string $value): bool => str_contains(
                $this->normalize($label . ' ' . $value),
                $query
            ),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * @param array<string, string> $options
     */
    public function normalizePage(array $options, string $query, int $page): int
    {
        $pageCount = max(1, (int) ceil(count($this->filterOptions($options, $query)) / self::PAGE_SIZE));

        return min(max($page, 0), $pageCount - 1);
    }

    private function normalize(string $value): string
    {
        $normalized = Normalizer::normalize($value, Normalizer::FORM_D);
        $value = mb_strtolower($normalized === false ? $value : $normalized);

        return preg_replace('/\p{Mn}+/u', '', $value) ?? $value;
    }
}

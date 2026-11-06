<?php

namespace App\Presentation\Telegram\Keyboard;

use Symfony\Contracts\Translation\TranslatorInterface;

final class TimezoneSelector
{
    public function __construct(
        private TimezoneOptions $timezoneOptions,
        private InlineOptionSelector $optionSelector,
        private TranslatorInterface $translator
    ) {}

    public function contains(string $timezone): bool
    {
        return array_key_exists($timezone, $this->timezoneOptions->all());
    }

    /**
     * @return array{
     *     page: int,
     *     has_results: bool,
     *     keyboard: array{inline_keyboard: list<list<array{text: string, callback_data?: string, url?: string}>>}
     * }
     */
    public function build(
        string $callbackPrefix,
        ?string $selectedValue,
        string $query,
        int $page,
        string $backLabel,
        string $backCallback,
        ?string $preferredTimezone = null
    ): array {
        $timezones = $query === ''
            ? $this->timezoneOptions->recommended($preferredTimezone)
            : $this->timezoneOptions->all();
        $filtered = $this->optionSelector->filterOptions($timezones, $query);
        $page = $this->optionSelector->normalizePage($timezones, $query, $page);

        return [
            'page' => $page,
            'has_results' => $filtered !== [],
            'keyboard' => $this->optionSelector->buildKeyboard(
                options: $timezones,
                callbackPrefix: $callbackPrefix,
                selectedValue: $selectedValue,
                query: $query,
                page: $page,
                searchLabel: $this->translator->trans('selector.search'),
                clearSearchLabel: $this->translator->trans('selector.clear_search'),
                previousLabel: $this->translator->trans('selector.previous'),
                nextLabel: $this->translator->trans('selector.next'),
                backLabel: $backLabel,
                backCallback: $backCallback
            ),
        ];
    }
}

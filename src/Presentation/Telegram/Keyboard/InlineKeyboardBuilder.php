<?php

namespace App\Presentation\Telegram\Keyboard;

final class InlineKeyboardBuilder
{
    /**
     * @var list<list<array{text: string, callback_data?: string, url?: string}>>
     */
    private array $rows = [];

    /**
     * @var list<array{text: string, callback_data?: string, url?: string}>
     */
    private array $currentRow = [];

    public function button(string $text, string $callbackData): self
    {
        $this->currentRow[] = [
            'text' => $text,
            'callback_data' => $callbackData,
        ];

        return $this;
    }

    public function urlButton(string $text, string $url): self
    {
        $this->currentRow[] = [
            'text' => $text,
            'url' => $url,
        ];

        return $this;
    }

    public function row(): self
    {
        if (!empty($this->currentRow)) {
            $this->rows[] = $this->currentRow;
            $this->currentRow = [];
        }

        return $this;
    }

    /**
     * @return array{inline_keyboard: list<list<array{text: string, callback_data?: string, url?: string}>>}
     */
    public function toArray(): array
    {
        $rows = $this->rows;
        if (!empty($this->currentRow)) {
            $rows[] = $this->currentRow;
        }

        return [
            'inline_keyboard' => $rows,
        ];
    }
}

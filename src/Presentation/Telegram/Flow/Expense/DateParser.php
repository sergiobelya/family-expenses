<?php

namespace App\Presentation\Telegram\Flow\Expense;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Parses and validates purchase dates per add_expense.md §6.3.
 *
 * Accepts YYYY-MM-DD, DD.MM.YYYY, and DD.MM (current year).
 * Rejects formats with / (ambiguous), future dates, and dates before 2020-01-01.
 */
final readonly class DateParser
{
    private const string MIN_DATE = '2020-01-01';

    /**
     * @return array{date: DateTimeImmutable}|array{error: string}
     */
    public function parse(string $raw, DateTimeZone $timezone): array
    {
        $trimmed = trim($raw);

        // Reject / separator as ambiguous
        if (str_contains($trimmed, '/')) {
            return ['error' => 'date.invalid'];
        }

        $date = null;

        // Try YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed) === 1) {
            $date = $this->parseStrict($trimmed, 'Y-m-d');
        }

        // Try DD.MM.YYYY
        if ($date === null && preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $trimmed) === 1) {
            $date = $this->parseStrict($trimmed, 'd.m.Y');
        }

        // Try DD.MM (current year in family timezone)
        if ($date === null && preg_match('/^\d{2}\.\d{2}$/', $trimmed) === 1) {
            $year = (new DateTimeImmutable('now', $timezone))->format('Y');
            $date = $this->parseStrict($trimmed . '.' . $year, 'd.m.Y');
        }

        if ($date === null) {
            return ['error' => 'date.invalid'];
        }

        // Check minimum date
        if ($date < new DateTimeImmutable(self::MIN_DATE, $timezone)) {
            return ['error' => 'date.too_old'];
        }

        // Check not in future (today in family timezone)
        $today = new DateTimeImmutable('today', $timezone);
        if ($date > $today) {
            return ['error' => 'date.future'];
        }

        return ['date' => $date];
    }

    private function parseStrict(string $raw, string $format): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat($format, $raw);

        // createFromFormat returns false on failure, but also creates invalid dates
        // (e.g., 31.02.2026 creates March 3). Verify by reformatting.
        if ($date === false || $date->format($format) !== $raw) {
            return null;
        }

        return $date;
    }
}

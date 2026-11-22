<?php

namespace App\Tests\Unit\Presentation\Telegram;

use App\Presentation\Telegram\CallbackActions;
use PHPUnit\Framework\TestCase;

/**
 * Verify that all Telegram callback data constants comply with the 64-byte limit.
 *
 * Telegram Bot API rejects callback_data longer than 64 bytes.
 * This test checks both static constants and those that are concatenated with a UUID (36 chars).
 */
class CallbackDataLengthTest extends TestCase
{
    private const int MAX_LENGTH = 64;
    private const string FAKE_UUID = '00000000-0000-0000-0000-000000000000';

    /**
     * Prefixes that are concatenated with a UUID (entity IDs) in production code.
     *
     * Not all constants ending with ':' are used with UUID — some are used with
     * short values like currency codes (3 chars), timezone names (~15 chars),
     * or provider keys (~3 chars). Only the prefixes below are appended with UUID.
     *
     * Includes both CallbackActions constants and dynamic patterns built by
     * concatenating CATALOG_PREFIX with action suffixes in screen/handler classes.
     *
     * Keep this list in sync when adding new callback data patterns.
     */
    private const array UUID_PREFIXES = [
        // CallbackActions constants
        CallbackActions::CATALOG_WARNING_OPEN_STORE_PREFIX,
        CallbackActions::CATALOG_WARNING_OPEN_CATEGORY_PREFIX,
        CallbackActions::CATALOG_DEFAULT_CATEGORY_OPEN_PREFIX,
        CallbackActions::CATALOG_DEFAULT_CATEGORY_SET_NONE, // static, but included for completeness
        CallbackActions::CATALOG_ALIASES_PREFIX,
        CallbackActions::CATALOG_STORE_PREFIX,
        CallbackActions::CATALOG_CATEGORY_PREFIX,
        CallbackActions::CATALOG_ALIAS_PREFIX,
        CallbackActions::CATALOG_DELETE_PREFIX,
        CallbackActions::CATALOG_RENAME_PREFIX,
        CallbackActions::CATALOG_SEARCH_PREFIX,
        CallbackActions::CATALOG_PAGE_PREFIX,
        CallbackActions::CATALOG_RETURN_PREFIX,
        CallbackActions::FAMILY_SELECT_PREFIX,
        CallbackActions::FAMILY_INVITATION_REVOKE_PREFIX,
        CallbackActions::FAMILY_INVITATION_VIEW_PREFIX,
        CallbackActions::FAMILY_JOIN_CONFIRM_PREFIX,
        // Expense flow
        CallbackActions::EXPENSE_STORE_SELECT_PREFIX,
        CallbackActions::EXPENSE_STORE_SEARCH_PREFIX,
        CallbackActions::EXPENSE_STORE_PAGE_PREFIX,
        CallbackActions::EXPENSE_STORE_CREATE_QUERY,
        CallbackActions::EXPENSE_CURRENCY_PAGE_PREFIX,
        CallbackActions::EXPENSE_CATEGORY_PAGE_PREFIX,
        // Dynamic patterns (built by concatenating CATALOG_PREFIX with action suffixes)
        'catalog:select:store:',  // StoreEntryScreen::buildListKeyboard
        'catalog:select:category:', // CategoryEntryScreen::buildListKeyboard
        'catalog:confirm:store:',  // StoreEntryScreen::renderDeleteConfirmation
        'catalog:confirm:category:', // CategoryEntryScreen::renderDeleteConfirmation
    ];

    /**
     * All static callback data constants must not exceed 64 characters.
     */
    public function testStaticCallbackDataDoesNotExceedLimit(): void
    {
        $reflections = new \ReflectionClass(CallbackActions::class);
        $violations = [];

        foreach ($reflections->getConstants() as $name => $value) {
            if (!is_string($value)) {
                continue;
            }
            $length = strlen($value);
            if ($length > self::MAX_LENGTH) {
                $violations[] = sprintf(
                    '%s = "%s" (%d chars)',
                    $name,
                    $value,
                    $length
                );
            }
        }

        self::assertEmpty(
            $violations,
            'Static callback data exceeds 64-byte limit: ' . implode('; ', $violations)
        );
    }

    /**
     * Callback data prefixes that are concatenated with a UUID must still fit within 64 characters.
     *
     * Only checks the prefixes listed in UUID_PREFIXES, as not all ':'-ending
     * constants are used with UUID (some use short values like currency codes).
     */
    public function testCallbackPrefixWithUuidDoesNotExceedLimit(): void
    {
        $violations = [];

        foreach (self::UUID_PREFIXES as $prefix) {
            // Skip static values that don't end with ':'
            if (str_ends_with($prefix, ':') === false) {
                $length = strlen($prefix);
                if ($length > self::MAX_LENGTH) { // @phpstan-ignore greater.alwaysFalse
                    $violations[] = sprintf(
                        '"%s" = %d chars (static, no UUID)',
                        $prefix,
                        $length
                    );
                }
                continue;
            }

            $fullData = $prefix . self::FAKE_UUID;
            $length = strlen($fullData);

            if ($length > self::MAX_LENGTH) { // @phpstan-ignore greater.alwaysFalse
                $violations[] = sprintf(
                    '"%s" + UUID = %d chars (prefix: %d, UUID: 36)',
                    $prefix,
                    $length,
                    strlen($prefix)
                );
            }
        }

        self::assertEmpty(
            $violations,
            'Callback data with UUID exceeds 64-byte limit: ' . implode('; ', $violations)
        );
    }

    /**
     * Ensure UUID_PREFIXES list is exhaustive — every constant ending with ':'
     * that contains a UUID-related suffix should be in the list.
     */
    public function testUuidPrefixListIsExhaustive(): void
    {
        $reflections = new \ReflectionClass(CallbackActions::class);
        $uuidPrefixesSet = array_flip(self::UUID_PREFIXES);

        $missing = [];
        foreach ($reflections->getConstants() as $name => $value) {
            if (!is_string($value) || str_ends_with($value, ':') === false) {
                continue;
            }
            // Check if this prefix looks like it's used with UUID
            // (contains common UUID-related suffixes)
            $uuidIndicators = ['store:', 'category:', 'alias:', 'delete:', 'rename:', 'select:', 'search:', 'page:', 'return:', 'revoke:', 'view:', 'confirm:'];
            foreach ($uuidIndicators as $indicator) {
                if (str_contains($value, $indicator) && !isset($uuidPrefixesSet[$value])) {
                    $missing[] = sprintf('%s = "%s"', $name, $value);
                    break;
                }
            }
        }

        self::assertEmpty(
            $missing,
            'UUID prefix list may be missing: ' . implode('; ', $missing)
        );
    }
}

<?php

namespace App\Presentation\Telegram;

/**
 * Centralized Telegram callback data constants.
 *
 * Grouped by prefix for clarity. Use constants instead of hardcoded strings
 * for single-source-of-truth, IDE autocomplete, and easier search/replacement.
 */
final readonly class CallbackActions
{
    // ── Navigation ────────────────────────────────────────────────────────────

    public const string NAV_MAIN_MENU = 'nav:main_menu';
    public const string NAV_ONBOARDING = 'nav:onboarding';

    // ── Family Creation Wizard ────────────────────────────────────────────────

    public const string FAMILY_CREATE_START = 'family:create:start';
    public const string FAMILY_CREATE_CANCEL = 'family:create:cancel';
    public const string FAMILY_CREATE_SUBMIT = 'family:create:submit';

    public const string FAMILY_CREATE_PREFIX = 'family:create:';

    public const string FAMILY_CREATE_TIMEZONE_SEARCH = 'family:create:timezone:search';
    public const string FAMILY_CREATE_TIMEZONE_SELECT = 'family:create:timezone:select';
    public const string FAMILY_CREATE_TIMEZONE_CLEAR = 'family:create:timezone:clear';
    public const string FAMILY_CREATE_TIMEZONE_SET_PREFIX = 'family:create:timezone:set:';
    public const string FAMILY_CREATE_TZ_PREFIX = 'family:create:tz:';

    public const string FAMILY_CREATE_DEF_CURR_PREFIX = 'family:create:def_curr:';
    public const string FAMILY_CREATE_REP_CURR_PREFIX = 'family:create:rep_curr:';
    public const string FAMILY_CREATE_PROVIDER_PREFIX = 'family:create:provider:';

    // ── Family Selector ───────────────────────────────────────────────────────

    public const string FAMILY_SELECTOR = 'family:selector';
    public const string FAMILY_SELECT_PREFIX = 'family:select:';
    public const string FAMILY_SELECT_FOR_EXPENSE_PREFIX = 'family:expense:select:';
    public const string FAMILY_SELECT_FOR_EXPENSE_ADD_PREFIX = 'family:expense:add:';
    public const string FAMILY_SELECT_FOR_EXPENSE_CONTINUE_PREFIX = 'family:expense:continue:';

    // ── Family Members ────────────────────────────────────────────────────────

    public const string FAMILY_MEMBERS = 'family:members';

    // ── Family Invitations ────────────────────────────────────────────────────

    public const string FAMILY_INVITE_CREATE = 'family:invite:create';
    public const string FAMILY_INVITATIONS_LIST = 'family:invitations:list';
    public const string FAMILY_INVITATION_REVOKE_PREFIX = 'family:invitation:revoke:';
    public const string FAMILY_INVITATION_VIEW_PREFIX = 'family:invitation:view:';

    // ── Family Join ───────────────────────────────────────────────────────────

    public const string FAMILY_JOIN_CONFIRM_PREFIX = 'family:join:confirm:';

    // ── Settings ──────────────────────────────────────────────────────────────

    public const string SETTINGS_MAIN = 'settings:main';

    public const string SETTINGS_LANG_SELECT = 'settings:lang:select';
    public const string SETTINGS_LANG_SET_PREFIX = 'settings:lang:set:';

    public const string SETTINGS_FAMILY_PREFIX = 'settings:family:';

    public const string SETTINGS_FAMILY_VIEW = 'settings:family:view';
    public const string SETTINGS_FAMILY_EDIT_NAME = 'settings:family:edit:name';
    public const string SETTINGS_FAMILY_EDIT_TZ = 'settings:family:edit:tz';
    public const string SETTINGS_FAMILY_TIMEZONE_SEARCH = 'settings:family:timezone:search';
    public const string SETTINGS_FAMILY_TIMEZONE_SELECT = 'settings:family:timezone:select';
    public const string SETTINGS_FAMILY_TIMEZONE_CLEAR = 'settings:family:timezone:clear';
    public const string SETTINGS_FAMILY_TIMEZONE_SET_PREFIX = 'settings:family:timezone:set:';
    public const string SETTINGS_FAMILY_SET_TZ_PREFIX = 'settings:family:set:tz:';
    public const string SETTINGS_FAMILY_EDIT_DEF_CURR = 'settings:family:edit:def_curr';
    public const string SETTINGS_FAMILY_SET_DEF_CURR_PREFIX = 'settings:family:set:def_curr:';
    public const string SETTINGS_FAMILY_EDIT_REP_CURR = 'settings:family:edit:rep_curr';
    public const string SETTINGS_FAMILY_SET_REP_CURR_PREFIX = 'settings:family:set:rep_curr:';
    public const string SETTINGS_FAMILY_EDIT_PROVIDER = 'settings:family:edit:provider';
    public const string SETTINGS_FAMILY_SET_PROVIDER_PREFIX = 'settings:family:set:provider:';

    // ── Catalog ───────────────────────────────────────────────────────────────

    public const string CATALOG_PREFIX = 'catalog:';
    public const string CATALOG_DEFAULT_CATEGORY_PREFIX = 'catalog:def-cat';

    public const string CATALOG_CANCEL = 'catalog:cancel';
    public const string CATALOG_CREATE_SEARCH = 'catalog:create-search';
    public const string CATALOG_WARNING_CONFIRM = 'catalog:warning:confirm';
    public const string CATALOG_WARNING_CANCEL = 'catalog:warning:cancel';
    public const string CATALOG_WARNING_EDIT = 'catalog:warning:edit';
    public const string CATALOG_WARNING_OPEN_STORE_PREFIX = 'catalog:warn:open-store:';
    public const string CATALOG_WARNING_OPEN_CATEGORY_PREFIX = 'catalog:warn:open-cat:';
    public const string CATALOG_ALIAS_CONFLICT_EDIT = 'catalog:alias:conflict:edit';
    public const string CATALOG_DEFAULT_CATEGORY_SET_NONE = 'catalog:def-cat:set:none';
    public const string CATALOG_DEFAULT_CATEGORY_OPEN_PREFIX = 'catalog:def-cat:open:';
    public const string CATALOG_ALIASES_PREFIX = 'catalog:aliases:';
    public const string CATALOG_STORE_PREFIX = 'catalog:store:';
    public const string CATALOG_CATEGORY_PREFIX = 'catalog:category:';
    public const string CATALOG_ALIAS_PREFIX = 'catalog:alias:';
    public const string CATALOG_DELETE_PREFIX = 'catalog:delete:';
    public const string CATALOG_RENAME_PREFIX = 'catalog:rename:';
    public const string CATALOG_SEARCH_PREFIX = 'catalog:search:';
    public const string CATALOG_PAGE_PREFIX = 'catalog:page:';
    public const string CATALOG_RETURN_PREFIX = 'catalog:return:';

    // ── Add Expense Flow ────────────────────────────────────────────────────

    public const string EXPENSE_PREFIX = 'exp:';

    // Entry
    public const string EXPENSE_ADD = 'exp:add';
    public const string EXPENSE_CONTINUE = 'exp:continue';

    // Store selection (AE-03/AE-04)
    public const string EXPENSE_STORE_SELECT_PREFIX = 'exp:store:';
    public const string EXPENSE_STORE_SEARCH = 'exp:store:search';
    public const string EXPENSE_STORE_SEARCH_CLEAR = 'exp:store:search:clear';
    public const string EXPENSE_STORE_SEARCH_PREFIX = 'exp:store:search:';
    public const string EXPENSE_STORE_PAGE_PREFIX = 'exp:store:page:';
    public const string EXPENSE_STORE_ADD = 'exp:store:add';
    public const string EXPENSE_STORE_CREATE_QUERY = 'exp:store:create:';
    public const string EXPENSE_STORE_SELECT_ACTION_PREFIX = 's:';
    public const string EXPENSE_STORE_PAGE_ACTION_PREFIX = 'p:';
    public const string EXPENSE_STORE_SEARCH_ACTION = 'q';
    public const string EXPENSE_STORE_SEARCH_BACK_ACTION = 'b';
    public const string EXPENSE_STORE_CREATE_ACTION = 'c';

    // Amount (AE-06)
    public const string EXPENSE_CURRENCY_PREFIX = 'exp:curr:';
    public const string EXPENSE_CURRENCY_MORE = 'exp:curr:more';
    public const string EXPENSE_AMOUNT_CURRENCY_ACTION_PREFIX = 'ac:';
    public const string EXPENSE_AMOUNT_MORE_CURRENCIES_ACTION = 'am';
    public const string EXPENSE_AMOUNT_CONTINUE_ACTION = 'an';
    public const string EXPENSE_AMOUNT_BACK_ACTION = 'ab';
    public const string EXPENSE_AMOUNT_BACK_REVIEW_ACTION = 'ar';

    // More currencies (AE-07)
    public const string EXPENSE_CURRENCY_SELECT_PREFIX = 'exp:currency:set:';
    public const string EXPENSE_CURRENCY_SEARCH = 'exp:currency:search';
    public const string EXPENSE_CURRENCY_SEARCH_CLEAR = 'exp:currency:search:clear';
    public const string EXPENSE_CURRENCY_PAGE_PREFIX = 'exp:currency:page:';
    public const string EXPENSE_CURRENCY_SELECT_ACTION_PREFIX = 'u:';
    public const string EXPENSE_CURRENCY_PAGE_ACTION_PREFIX = 'up:';
    public const string EXPENSE_CURRENCY_SEARCH_ACTION = 'us';
    public const string EXPENSE_CURRENCY_SEARCH_BACK_ACTION = 'ul';
    public const string EXPENSE_CURRENCY_BACK_ACTION = 'ub';

    // Category (AE-08)
    public const string EXPENSE_CATEGORY_SELECT_PREFIX = 'exp:cat:';
    public const string EXPENSE_CATEGORY_CONTINUE = 'exp:cat:continue';
    public const string EXPENSE_CATEGORY_SEARCH = 'exp:cat:search';
    public const string EXPENSE_CATEGORY_SEARCH_CLEAR = 'exp:cat:search:clear';
    public const string EXPENSE_CATEGORY_PAGE_PREFIX = 'exp:cat:page:';
    public const string EXPENSE_CATEGORY_CREATE = 'exp:cat:create';
    public const string EXPENSE_CATEGORY_CREATE_QUERY = 'exp:cat:create:';
    public const string EXPENSE_CATEGORY_SPLIT = 'exp:cat:split';

    // Split (AE-09)
    public const string EXPENSE_SPLIT_ADD = 'exp:split:add';
    public const string EXPENSE_SPLIT_ADD_REMAINING = 'exp:split:add:remaining';
    public const string EXPENSE_SPLIT_REMOVE_PREFIX = 'exp:split:rm:';
    public const string EXPENSE_SPLIT_EDIT_PREFIX = 'exp:split:edit:';
    public const string EXPENSE_SPLIT_DONE = 'exp:split:done';
    public const string EXPENSE_SPLIT_PICK_SEARCH = 'exp:split:search';
    public const string EXPENSE_SPLIT_PICK_PREFIX = 'exp:split:pick:';
    public const string EXPENSE_SPLIT_USE_REMAINING = 'exp:split:use:remaining';

    // Review (AE-10)
    public const string EXPENSE_CONFIRM = 'exp:confirm';
    public const string EXPENSE_CONFIRM_NO_CONVERSION = 'exp:confirm:no-conv';
    public const string EXPENSE_EDIT_STORE = 'exp:edit:store';
    public const string EXPENSE_EDIT_AMOUNT = 'exp:edit:amount';
    public const string EXPENSE_EDIT_CATEGORY = 'exp:edit:category';
    public const string EXPENSE_EDIT_DATE = 'exp:edit:date';

    // Date (AE-11)
    public const string EXPENSE_DATE_TODAY = 'exp:date:today';
    public const string EXPENSE_DATE_YESTERDAY = 'exp:date:yesterday';
    public const string EXPENSE_DATE_TYPE = 'exp:date:type';
    public const string EXPENSE_DATE_SET_PREFIX = 'exp:date:set:';
    public const string EXPENSE_DATE_CAL_PREFIX = 'exp:date:cal:';

    // Saving (AE-13/AE-15)
    public const string EXPENSE_RETRY = 'exp:retry';
    public const string EXPENSE_REVIEW_DRAFT = 'exp:review:draft';

    // Success (AE-14)
    public const string EXPENSE_ADD_ANOTHER = 'exp:add:another';
    public const string EXPENSE_OPEN_EXPENSE = 'exp:open';

    // Cancel (AE-16)
    public const string EXPENSE_CANCEL = 'exp:cancel';
    public const string EXPENSE_CANCEL_YES = 'exp:cancel:yes';
    public const string EXPENSE_CANCEL_NO = 'exp:cancel:no';

    // Expired (AE-17)
    public const string EXPENSE_EXPIRED_RESTART = 'exp:expired:restart';
    public const string EXPENSE_EXPIRED_CLOSE = 'exp:expired:close';
    public const string EXPENSE_SHOW_CARD_ACTION = 'sc';
    public const string EXPENSE_EXPIRED_RESTART_ACTION = 'er';
    public const string EXPENSE_EXPIRED_CLOSE_ACTION = 'ec';
    public const string EXPENSE_RETRY_ACTION = 'r';
    public const string EXPENSE_REVIEW_DRAFT_ACTION = 'v';

    // Shared
    public const string EXPENSE_BACK = 'exp:back';
    public const string EXPENSE_CHANGE_FAMILY = 'exp:change:family';
    public const string EXPENSE_RETURN = 'exp:return';

    // Draft token prefix for callback data (exp:t:<token>:<rev>:<action>)
    public const string EXPENSE_TOKEN_PREFIX = 'exp:t:';
}

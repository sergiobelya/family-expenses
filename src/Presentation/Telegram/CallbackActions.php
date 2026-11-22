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
}

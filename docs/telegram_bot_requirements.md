# Family Expenses Telegram Bot — Product & Technical Requirements

**Document status:** Consolidated requirements for MVP  
**Product:** Telegram bot for family expense tracking  
**Fallback language:** English  
**Initial supported languages:** English, Ukrainian, Polish, Russian  
**Primary interface:** Standard Telegram bot chat using reply keyboards and inline keyboards  
**Mini App:** Out of scope for MVP

---

## 1. Product Overview

Build a Telegram bot that allows members of one or more families to record, browse, search, edit, and report family expenses.

A Telegram user may belong to multiple families. Every family has its own members, expenses, stores, categories, settings, and reports. Data must be isolated between families.

The MVP records **family expenses only**. The data model should support adding personal expenses in the future, but personal/family selection and filtering must not appear in the MVP interface.

## 2. Goals

- Make recording a family expense fast and convenient in Telegram.
- Support multiple families per Telegram user.
- Provide shared, family-scoped stores and categories.
- Support purchases in multiple currencies.
- Produce useful summaries and downloadable reports.
- Support editing, deletion, history, search, and recurring expense templates.
- Keep the architecture ready for future permissions, expense types, and product extensions.

## 3. MVP Scope

### Included

- Telegram user onboarding and language preference.
- Create multiple families and manage their names and settings.
- Invite family members and join families.
- Family expenses.
- Family-scoped stores and categories.
- Add, edit, and delete expenses.
- Store search uses a dedicated search-index abstraction; a separate category index is not required for MVP. Search behavior and family isolation are defined in §6.3, §10, and §14.
- Expense history and search.
- Reports by month, multiple months, or exact date range.
- Category and store breakdowns and spending dynamics.
- Currency conversion through a configurable exchange-rate provider, with manual correction. NBP is one supported example for Poland.
- Separate default currency for entering expenses and reporting currency.
- CSV and XLSX exports.
- Recurring expense templates with confirmation before each expense is created.
- English, Ukrainian, Polish, and Russian interface localization.
- Authorization checks and an audit trail for important changes.

### Explicitly out of scope for MVP

- Personal expenses in the user interface.
- Filtering by personal/family expense type.
- Per-person spending totals in reports.
- Monthly budgets.
- Reminders and notifications unrelated to recurring-expense confirmation.
- Telegram Mini App.
- Advanced role and permission management UI.
- Leaving a family, removing another member, transferring ownership, and deleting/closing a family. These membership lifecycle flows are out of scope for MVP.

The architecture should make future roles and permission rules possible without rewriting bot handlers. “Manage families” in MVP means rename a family and change its settings; family closure/deletion is out of scope. In the MVP, any active family member may change family settings; role-based restrictions are out of scope.

## 4. Users, Families, and Data Isolation

### 4.1 Telegram users

- Identify users by their Telegram user ID, not by username.
- Store language and other user-specific preferences separately from family settings.
- The user can change their language at any time through Settings. The selected language applies to that user across all families.
- A user may be a member of multiple families.
- A user must explicitly join a family before accessing its shared data.

### 4.2 Families

Each family has its own:
- Name.
- Members and membership statuses.
- Expenses.
- Stores and store aliases.
- Categories.
- Default expense-entry currency.
- Reporting currency.
- Invitations and audit history.

All queries and mutations involving family data must be scoped to a family ID and checked against the user's membership. In the MVP, any active family member may change family settings, including default currencies, reporting currency, and exchange-rate provider. Language is a user-level setting, not a family setting. Role-based settings restrictions are out of scope; authorization should be centralized so roles can be introduced later.

### 4.3 Invitations

Support:
- Invite links using Telegram bot deep links.
- Sharing an invitation through Telegram where possible.
- Sharing a Telegram contact with the bot, where supported.
- Username entry as a convenience when the user knows the username.

**Telegram limitation:** bots cannot universally search for arbitrary Telegram users by name, phone number, or username, nor can a bot always initiate a conversation with a user who has never started it. Invitation flows must explain when the recipient needs to open the bot and join through the deep link. Sharing a contact does not guarantee that the bot can message that person.

Invitation tokens must be unguessable, revocable, and associated with a specific family. Use Telegram bot deep links as the primary invitation mechanism. A username is only a user-provided hint; it must never be used as the identity key or as proof of membership. Do not promise username/contact lookup or proactive messaging where Telegram does not support it.

For MVP, invitations expire after 48 hours by default and allow one successful use by default (`max_uses = 1`); the creator may choose a different maximum-use limit. Store `expires_at` and `revoked_at`. `InvitationUse` is the source of truth for use count; do not store a redundant `use_count`. Enforce maximum uses atomically with the successful join and `InvitationUse` insert. Revocation blocks future joins only and does not remove users who already joined. Audit invitation creation, revocation, and successful use. Joining must be an explicit action by the recipient after opening the bot.

## 5. Main Telegram Navigation

Use standard Telegram controls. The MVP supports private chats with the bot only; group-chat interaction is out of scope.

### Reply-keyboard main menu

- **Add expense**
- **Reports**
- **History / Search**
- **Stores**
- **Categories**
- **Members**
- **Recurring expenses**
- **Settings**

Use inline keyboards for contextual actions, selections, confirmation, pagination, and editing.

When the user belongs to multiple families, provide a clear family selector and display the active family in relevant flows. Never silently mix data across families.

## 6. Expense Data and Behavior

### 6.1 Expense fields

An expense should include:
- ID.
- Family ID.
- Author/creator user ID.
- Purchase date.
- Store ID (required in the MVP).
- Original amount.
- Original currency.
- Expense type, initially `family` for every MVP expense.
- Created and updated timestamps.
- Soft-deletion state and deletion metadata.

Expense type is reserved for future functionality. Do not show it in the MVP add/edit interface or offer type filters.

### 6.2 Add-expense flow

Use the following flow:

1. Select a store.
2. Enter amount and currency.
3. Select a category or split the expense across categories.
4. Review the expense card.
5. Confirm and save.

Date defaults to the current date and can be edited. A manually entered expense cannot have a future purchase date. Historical dates are allowed when valid. Recurring occurrences use their scheduled date by default and may be changed before confirmation; provide a one-tap **Today** option.

The expense-entry currency defaults to the family's `default_expense_currency`, but the user can select another currency for an individual purchase. The supported currency list must use valid ISO 4217 currency codes and be centrally configurable.

A store may have a default category. When the store is selected, preselect that category, but allow the user to change it.

Store creation and management take place in the separate Stores section, not inside the add-expense flow. If no matching store exists, direct the user to the separate Stores section and preserve the in-progress expense draft so the user can return to it after creating the store. Do not create a store inline. Drafts paused for this navigation are retained for 24 hours; if the draft expires, tell the user clearly and offer to restart without implying the old data is recoverable.

### 6.3 Store search and creation

- Search stores through a dedicated search index; the specific engine (e.g. Elasticsearch or a managed cloud equivalent) is deferred to system design.
- Search must support case-insensitive and diacritic-insensitive matching, normalized forms, language-aware transliteration across Latin/Cyrillic scripts, and fuzzy matching with edit distance 1. The queries `biedronka`, `Бєдронка`, and `бедронка` must find the same likely Biedronka candidate, even when their hard deduplication keys differ; `Żabka` and `zabka` must match. Search normalization may generate multiple language-aware transliteration forms, while hard duplicate prevention uses one fixed transliteration scheme. Fuzzy/transliterated search results are suggestions only, never proof of identity and never an automatic merge.
- Transliteration and fuzzy matches are search aids only; they must never automatically merge stores.
- Store creation is available only in the separate Stores section. Compute `dedupe_key` using the single canonical algorithm in §6.3.1; it is a pure function of the name and never depends on actor or UI language. Enforce `UNIQUE (family_id, dedupe_key)`. The fixed scheme makes `Бєдронка` collide with `Biedronka`, while `бедронка` may remain a separate hard key (`bedronka`); search must still suggest `Biedronka` for `бедронка`. Search can use broader language-aware transliteration forms, but these never affect hard uniqueness. The final key must contain at least one Unicode letter or digit; otherwise reject the name.
- Hard uniqueness applies to exact normalized/transliterated equivalents only. Fuzzy matches (edit distance 1) remain advisory: show likely matches, but allow creation after acknowledgement if the `dedupe_key` is different. The user cannot override a hard-key collision; for distinct branches of the same chain, require a meaningful differentiator in the name (for example, `Biedronka — Rynek`). Never auto-merge records.
- Protect retries of the *same create request* with an idempotency key or equivalent atomic request-level safeguard in addition to the name constraint. Every key lookup and uniqueness constraint must be scoped to the current `family_id`. If the search index is unavailable, the database `dedupe_key` constraint remains authoritative; use database-backed duplicate lookup where feasible, and if fuzzy suggestions cannot be checked, clearly disclose that limitation without bypassing the hard constraint.
- Search results, duplicate checks, aliases, and indexed documents must be scoped to the current family; automated tests must verify that no results or aliases leak across families.


### 6.3.1 Hard-key normalization (`dedupe_key`)

This section is the single source of truth for hard-key generation for `Store`, `Category`, and `StoreAlias`. Other sections and acceptance criteria must refer to this algorithm rather than restating it. The algorithm is deterministic and depends only on the supplied name; it does not depend on the actor, UI language, family locale, or search-index output. Apply these steps in order:

1. Before compatibility normalization, remove apostrophe-like characters (`'`, `’`, `ʼ`, `` ` ``, `´`, `‘`, `ʹ`) and trademark/service-mark symbols (`™`, `℠`) without inserting spaces. This must happen on the raw input because NFKC can transform `´` into a space plus a combining mark, and can expand `™` / `℠` into letters. Apostrophe removal preserves the intended equivalence of variants: `McDonald's` and `McDonalds` normalize alike; `м'ясо` and ``м`ясо`` are processed as if the apostrophe were absent. Trademark/service-mark removal makes `Pepco™` and `Pepco℠` equivalent to `Pepco`.
2. Normalize the remaining input to Unicode NFKC, then repeat removal of the same apostrophe-like characters (`'`, `’`, `ʼ`, `` ` ``, `´`, `‘`, `ʹ`) without inserting spaces, and lowercase it. Repeat removal because NFKC can create apostrophe-like characters that were not present in the raw input, including `＇` → `'` and `｀` → `` ` `` (and can normalize Greek varia U+1FEF to `` ` ``). This second removal is intentional and idempotent. Compatibility characters such as fullwidth Latin letters and ligatures are thereby normalized before transliteration. Other compatibility symbols, such as `№`, follow Unicode NFKC behavior (for example, `№` becomes `No`).
3. Transliterate Cyrillic characters using the Ukrainian national transliteration scheme under Cabinet of Ministers of Ukraine Resolution No. 55, 27 January 2010. Apply its positional rules while Cyrillic characters are still intact. A word begins at the start of the string or at any position whose preceding character is not a Unicode letter (including start of string, whitespace, punctuation, or symbols). Apply the scheme's special `зг` → `zgh` rule. For Russian-specific characters not covered by the scheme, use fixed fallbacks: `ы` → `y`, `э` → `e`, `ъ` → empty string, `ё` → `yo`. Preserve unsupported letters from other scripts (including `ў`, `ј`, `ђ`) as normalized Unicode characters; do not guess transliterations.
4. Apply case-insensitive explicit mappings for Latin characters that do not decompose: `ł` → `l`, `ø` → `o`, `đ` → `d`, `ß` → `ss`, `æ` → `ae`. Then normalize to Unicode NFD, remove combining marks only from Latin letters (leaving marks attached to Cyrillic and other scripts unchanged), and recompose the result to Unicode NFC. This makes precomposed Latin characters such as `ż` equivalent to their unaccented forms without stripping marks from non-Latin scripts.
5. Replace `&` with the word `and` and `+` with the word `plus`, inserting word boundaries around the replacement. Replace every remaining Unicode character whose general category is `P*` (punctuation), `S*` (symbols, including emoji), `Z*` (separators), `Cc` (control), or `Cf` (format) with a space. Also replace combining marks that are not attached to a Unicode letter with a space, so emoji presentation marks do not leave residue. Do not remove ordinary internal spaces.
6. Trim leading/trailing whitespace and collapse each internal whitespace run to one ordinary space.
7. Reject the name if the resulting key contains no Unicode letter or digit; otherwise use the resulting string as `dedupe_key`.

Consequences and required test vectors:

| Input | Expected hard key / outcome |
|---|---|
| `Biedronka`, `biedronka`, `Biedronka `, `Бєдронка` | Same key: `biedronka` |
| `бедронка` | Separate key: `bedronka`; search may still suggest `Biedronka` |
| `Чайка` | `chaika` |
| `Київ` | `kyiv` |
| `Йосип` | `yosyp` |
| Standalone character `ё` | `yo` |
| `Żabka`, `zabka`, `Żabka.` | Same key: `zabka` |
| `Єдність`, `«Єдність»` | Same key: `yednist` |
| `Biedronka - Rynek`, `Biedronka Rynek` | Same key: `biedronka rynek` |
| `Biedronka 🍎`, `Biedronka` | Same key: `biedronka` |
| `Pepco™`, `Pepco℠`, `Pepco` | Same key: `pepco` |
| `ＡＢＣ`, `ABC` | Same key: `abc` |
| `ﬁesta`, `fiesta` | Same key: `fiesta` |
| `Łowicz`, `Lowicz` | Same key: `lowicz` |
| `McDonald's`, `McDonalds`, `McDonald＇s` | Same key: `mcdonalds` |
| `м'ясо`, `м’ясо`, `мʼясо`, ``м`ясо``, `м´ясо`, `м‘ясо`, `мʹясо`, `м＇ясо` | Same key as transliterating `мясо` |
| `A&B`, `A & B`, `A and B` | Same key: `a and b` |
| `Stokrotka+`, `Stokrotka Plus` | Same key: `stokrotka plus` |
| `Mc Donalds`, `McDonalds` | Different keys: `mc donalds` and `mcdonalds` |
| `!!!`, emoji-only input | Reject: no Unicode letter or digit remains |

The standalone `ё` vector verifies its fixed fallback to `yo`. The `Єдність` / `«Єдність»` vectors verify that punctuation before a Cyrillic letter does not alter positional transliteration. NFKC must preserve the intended Cyrillic distinctions before step 3.

### 6.4 Category splitting

An expense may be assigned to one or multiple categories, with an amount allocated to each category.

Rules:
- The sum of category allocations must equal the original expense amount. The allocation editor is a draft and must not persist incomplete rows.
- If the user changes the total or category amounts, show the remaining amount and offer to add a draft allocation row for it when the remaining amount is positive. The user must assign that row to a category before saving.
- Remaining amount = total expense amount − sum of existing category allocations.
- Do not create an extra row when the remaining amount is zero.
- A category is required for every allocation. Prevent saving when any allocation lacks a category, allocations exceed the total, or their sum does not exactly reconcile to the total at the currency's supported precision.
- Provide clear validation feedback.
- All category allocation amounts use the expense's original currency.

### 6.5 Expense card

After entry, display a concise editable summary including:
- Store.
- Date.
- Original amount and currency.
- Category allocation(s).
- Reporting-currency amount: use the original amount directly when original currency equals reporting currency; otherwise show the saved applicable conversion when available. If none applies, calculate an on-demand, unsaved display conversion under Section 7.6 and label its basis; if no rate is available, show “not converted”.

If the card shows an on-demand, unsaved display-conversion amount, cache provider-rate lookups to avoid one provider request per card and display a visible rate-basis label/date. If the provider is unavailable and no suitable cached rate exists, show “not converted” rather than failing the history view. A current-rate fallback amount may change between views; label it clearly.

Actions may include:
- Edit.
- Delete.
- Confirm/save.
- Cancel.

The MVP should use a chat-based editable expense card, not a Mini App.

### 6.6 Editing and deletion

- In MVP, only the expense author may edit or delete that expense.
- Put authorization checks in a service layer so future family roles can change this rule centrally.
- Use soft deletion rather than immediate physical deletion.
- Keep audit history for edits and deletions.
- After any edit, determine whether a conversion to the family's **current reporting currency** is needed. If the expense currency equals the reporting currency, no conversion record is needed; supersede any previously applicable conversion even if no replacement conversion is created.
- If conversion is needed and the edit changes only the amount, retain the prior rate only when the saved conversion's source/target pair matches the expense's current currency and the current reporting currency. Create a new version with the recalculated amount and supersede the old applicable version.
- If no applicable saved conversion exists, or the source/target pair no longer matches, obtain a new applicable rate for the expense date or let the author enter a manual rate. A manual rate may be entered whether or not an automatic rate is available.
- If the currency or purchase date changes, obtain a rate applicable to the updated currency pair/date or ask the author for an explicit manual rate. Never silently reuse a manual rate for a different pair/date.
- Mark every conversion that is no longer applicable with `superseded_at`; superseding does not require a replacement version when no conversion is needed.
- A deleted expense must be excluded from normal history, totals, and reports, while remaining available for audit/administrative recovery if supported.


### 6.7 Validation rules

- Accept only currencies from the centrally configured supported ISO 4217 currency list.
- Expense amounts and category allocations must be strictly positive; zero and negative amounts are invalid.
- Parse and store monetary values and exchange rates using decimal types, never binary floating point. Respect each currency's configured minor-unit precision.
- Enforce a configurable maximum amount/precision limit and return a user-readable validation error for overflow or unsupported precision; do not silently round user input.
- A normal expense purchase date must be a valid date and cannot be in the future. A recurring template may have a future scheduled date, but confirming an occurrence as an ordinary expense must reject a future purchase date.
- Store and category must belong to the same family as the expense. Archiving stores/categories is out of scope for MVP. Existing store/category references must remain readable.
- Saving is atomic: an expense and all category allocations are committed together only after validation passes. Incomplete allocation rows exist only in the chat draft and are not persisted.

## 7. Currency Settings and Conversion

### 7.1 Family Settings

The family must have three independent settings:

1. `default_expense_currency` — the currency preselected when adding a new expense. It can be changed temporarily, for example during a holiday.
2. `reporting_currency` — the currency used for reports, summaries, analytics, and exports.
3. `default_exchange_rate_provider` — the exchange rate provider used by default for automatic currency conversions.

An optional `country_code` setting may be used to suggest an appropriate exchange rate provider based on the family's country. The family also has a `timezone` setting used for “today”, future-date validation, recurring occurrence scheduling, and report-period boundaries. During family creation, suggest a time zone using available context (such as Telegram locale or family country) and ask the user to confirm or change it. Use UTC only as a fallback when no reasonable suggestion is available. Any active family member may change it in Settings.

The system should suggest a suitable provider based on the family's country or reporting currency, where possible. The user must be able to select another supported provider when setting or changing the reporting currency.

Changing the default expense-entry currency must not change the reporting currency or exchange rate provider.

Changing the default exchange rate provider must not automatically modify existing conversion records or historical reports. It affects future automatic conversions and explicit recalculations only.

Changing the reporting currency does not automatically recalculate historical conversions in the MVP. Generating a report must not write or update `ExpenseConversion` records. The schema must support a future explicit recalculation process described in Section 7.4.

These settings must be configurable independently.

### 7.2 Conversion policy

- Use the family's configured `default_exchange_rate_provider` for automatic rates. NBP is an example provider for Poland, not a universal default for all countries.
- Use the rate applicable to the purchase date, when available.
- Allow the user to enter a manual rate during expense creation/editing, even when an automatic rate is available. During report generation, default to automatic historical rates, but allow an explicit report-only manual override-all rate for each needed currency pair; explain that it applies to all expenses of that pair without a saved conversion, even if historical provider rates exist. Include a one-tap continue action using automatic rates. There is no manual-rate source or manual-rate setting in family Settings.
- Store the source and whether the rate was automatic or manually adjusted.
- Do not invent a rate if the rate service fails.
- During expense creation/editing, if an applicable automatic rate cannot be retrieved, let the expense author enter a manual rate or clearly indicate that conversion is unavailable. During report generation, use the separate fallback policy in Section 7.6.
- Preserve original purchase data permanently, regardless of later currency-setting changes.

A provider may publish reference rates that differ from the rate actually applied by a card issuer, bank, or payment provider. Describe provider rates as accounting/reference rates, not necessarily the actual transaction rate.

#### Exchange-rate provider selection

- Support multiple exchange-rate providers through a provider abstraction layer.
- Use the family's `default_exchange_rate_provider` for automatic retrieval.
- Suggest a suitable provider based on the family's country or reporting currency where possible.
- Allow any active family member to select a supported provider.
- Do not assume one national bank is suitable for every country. NBP may be used for Poland; other countries may require different national banks or supported reference-rate providers.
- If the selected provider cannot supply a suitable rate, notify the user and offer another supported provider or manual entry.
- Never invent or silently substitute an exchange rate without recording its source and method.

### 7.3 Conversion should be conditional

**Do not calculate or store a conversion record when the expense currency is the same as the family's reporting currency.**

When the expense currency differs from the reporting currency during expense creation/editing:
- Calculate the converted amount.
- Store the conversion separately from the expense. Report-only fallback calculations follow Section 7.6 and are not persisted.
- Store the source currency, target/reporting currency, rate, rate date, source, rate mode, converted amount, and calculation timestamp.
- Link the conversion record to the expense.
- Preserve enough information to reproduce or explain the conversion.

Exchange rates and conversion results belong in separate tables, not as a set of exchange-specific columns in the main expense table.

### 7.4 Reporting-currency changes

Changing `reporting_currency` in the MVP must **not** automatically recalculate or rewrite historical `ExpenseConversion` records. Report generation is read-only with respect to expenses and conversion records; any report-time conversion calculated for a report is ephemeral and is not persisted.

When the reporting currency changes:
- Keep every expense's original amount, original currency, and purchase date unchanged.
- Do not run a bulk historical conversion job automatically.
- Keep existing conversion records for audit/history. A record is applicable to a report only when its `target_currency` matches the report's reporting currency and it has not been superseded by a later conversion version. Do not update records merely because the family reporting currency changed.
- Reports must not use a conversion whose target currency differs from the family's active `reporting_currency`.
- If an expense's original currency equals the active reporting currency, display its original amount as the reporting-currency amount without creating an `ExpenseConversion` record.
- If a conversion for the active reporting currency is needed but does not exist, calculate it for that report under the report-time rate policy in Section 7.6, without saving a new `ExpenseConversion`.

The data model must support an explicit future recalculation operation. If implemented later, it must calculate directly from each expense's original amount/currency, never from a previously converted amount. It must create a new version/result, preserve prior results for traceability, and mark superseded results so reports select only the applicable current result. This future capability is not an automatic MVP behavior.

Changing `default_exchange_rate_provider` alone must not automatically recalculate or overwrite historical conversions.

### 7.5 Rate dates, non-business days, and overrides

- For an automatic rate tied to the purchase date, use the provider's applicable published rate for that date; if the provider publishes only on business days, use its documented latest-applicable-rate rule.
- Store both the expense purchase date and the actual rate publication/effective date.
- Manual rate entry or override must be explicit and auditable.
- A manual rate is specific to its currency pair and, when supplied for an expense, is stored in that expense's `ExpenseConversion`; do not silently reuse it for a different pair. The user may enter a manual rate even when an automatic rate is available.
- Cache retrieved provider rates in `ExchangeRate` to avoid unnecessary repeated requests. Public provider-rate cache writes are allowed during report generation; report generation must not modify expenses, `ExpenseConversion`, or family settings/entities.

### 7.6 Rate availability when generating reports

For each expense in a report, apply this order:
1. If the expense currency already equals the report currency, use the original amount directly; no conversion or fallback rate is needed.
2. Otherwise, if the expense has a saved `ExpenseConversion` whose `target_currency` equals the report currency and which has not been superseded, use that conversion regardless of whether its rate was entered manually or retrieved automatically.
3. For each currency pair that needs a report-time conversion, show a concise choice with **Use automatic historical rates** (the default) or **Enter one manual rate for this report**. Include a one-tap **Continue with automatic rates** action so users can proceed without entering any manual rate. If the report creator chooses a manual rate for a pair, it is an explicit **override-all for that pair in this report session**: it applies to every expense of that pair that has no applicable saved `ExpenseConversion`, even where a historical provider rate exists. Explain this scope before confirmation. Reuse that rate for the pair across all report periods, pages, and exports in the same session. This is intentional: if the user changes the date range while keeping the same report session, the override continues to apply to every matching expense in the revised range; show the active override and currency pair in the report summary before final export.
4. If automatic historical rates are selected, use the provider rate applicable to each expense's purchase date when available, using the cached `ExchangeRate` or requesting it from the configured provider. Follow the provider’s documented applicable-rate rule for non-business days.
5. If no rate for the purchase date is available, use the current automatic provider rate as a **current-rate fallback**. If no suitable rate is available, mark the expense as unconverted and disclose that the report total is incomplete.
6. A manual rate entered during report generation is ephemeral: do not save it to `ExpenseConversion`, `ExchangeRate`, family settings, or any separate manual-rate entity. Keep it in the active report session for 24 hours by default; pagination and exports initiated from that session must reuse it so displayed and exported totals agree. After expiry, require a new report session and warn that rates/totals may differ because current fallback rates may have changed. A later new report session may require the user to enter the manual rate again.
- Report generation may cache public automatic provider rates in `ExchangeRate`, but must not create, update, or supersede expenses or `ExpenseConversion` records.
- Show the rate source/type and rate date in report details and export metadata. Clearly label a current-rate fallback as **current-rate fallback**, historical provider rates as based on the purchase date, and report-only manual rates as **report-only manual rate**. Include the applied manual rate, its currency pair, and its override-all scope in exports from that report session. Do not label an expense card with a report-only manual rate, because that rate exists only in the report session.
- Never invent a rate. If no automatic rate is available and the user does not enter a manual report rate, mark the expense as unconverted and clearly disclose it in the report/export.
- Reports must clearly disclose any unconverted expenses and must not present a partial total as if it were complete.

A conversion result is not required when the expense currency equals the active reporting currency; use the original amount directly.

## 8. Reports and Analytics

### 8.1 Period selection

Support:
- A single calendar month.
- A range of months.
- An exact start and end date.

Allow a convenient default period, such as the current month, while keeping all period options available.

### 8.2 Report content

Reports should include:
- Total expenses in the reporting currency.
- Breakdown by category.
- Breakdown by store.
- Spending dynamics over time.

Do **not** include per-person spending totals in the MVP.

Reports must be scoped to the selected family and period, and exclude soft-deleted expenses. Report generation must not modify expenses or persist report-only conversions. If a current-rate fallback is used, label it clearly; totals may vary between reports generated at different times when the fallback rate changes. If any expense remains unconverted, disclose it and do not present the converted subtotal as a complete total.

### 8.3 Output formats

- Human-readable report in Telegram.
- CSV export.
- XLSX export.

Exports should include period, reporting currency, totals, and relevant category/store breakdowns. Where an expense-level export is offered, include original amount/currency, the applicable reporting-currency amount, and conversion basis/rate date where applicable. Do not include `created_by_user_id` or per-person totals in report exports. Creating an export file does not create or modify domain entities.

Clearly indicate when an expense cannot be converted so that report totals are not misleading.

## 9. History and Search

- Provide paginated expense history.
- Support searching by store and other practical fields such as date, amount, and category where feasible.
- Allow users to open an expense card from the results.
- Apply family membership and authorization checks to every search and history request.
- Exclude soft-deleted expenses from standard results.

## 10. Stores and Categories

### Stores

- Shared within a family; each store may have a default category.
- In MVP, allow creation, renaming, changing `default_category_id`, managing explicit aliases, and deleting a store only when it has no references from any expense (including soft-deleted expenses retained for history), recurring templates/occurrences, or other records. If referenced, deletion is blocked with an explanation and the user may rename it instead. Archiving is out of scope. Apply the same rule to categories: deletion is allowed only when no expense allocation, store default-category, recurring template/occurrence, or other record references it; otherwise block deletion.
- Use the common search-index abstraction described in §6.3 and §14 for search normalization, diacritic-insensitive matching, language-aware transliteration, and fuzzy matching. Persist `Store.dedupe_key` and enforce a database unique constraint on `(family_id, dedupe_key)`. On every store rename, recompute the key according to §6.3.1, check canonical names and aliases, and commit only if no collision exists. The index may maintain extra derived search forms, but it is not the authority for hard duplicate prevention. Do not aggressively collapse distinct hard keys such as `bedronka` and `biedronka`.
- `StoreAlias` is only for explicit alternate names or confirmed variants, not every normalized string. The canonical store name is searchable without a separate alias row. If `normalized_alias` is stored, it is a derived search aid. Alias creation must check for collisions against other stores' canonical names and aliases within the family using the same canonicalization rules; aliases must not bypass the canonical-name duplicate policy. Persist `StoreAlias.alias_dedupe_key` and enforce a family-scoped unique constraint on `(family_id, alias_dedupe_key)`. Alias-key insertion and checks against canonical store keys and other aliases must occur in the same transaction; aliases must conflict with another store's canonical name, and canonical-name creation/rename must conflict with another store's alias.
- Hard-key collisions are rejected and cannot be overridden by user confirmation. Fuzzy matches with different keys are warnings only; the user may proceed after acknowledgement, and records are never auto-merged. To represent distinct branches with otherwise identical names, include a meaningful branch/location differentiator in the canonical name.
- Every search and alias lookup must be scoped to the current family. Protect retries/concurrent processing of the same create request with an idempotency key or equivalent atomic request-level safeguard in addition to the unique constraint. If the index is unavailable, the database key constraint remains active; use database lookup for hard collisions and disclose if fuzzy suggestions cannot be checked.

### Categories

- Shared within a family. A separate category index is not required for MVP; use the common search abstraction, with a database/list implementation acceptable for the small category set. Any category search and duplicate check must remain family-scoped.
- Persist `Category.dedupe_key` and enforce a database unique constraint on `(family_id, dedupe_key)`, using exactly the canonical algorithm in §6.3.1; the key must not depend on actor or UI language. Do not aggressively collapse distinct Latin spellings such as `bedronka` and `biedronka`. Exact normalized/transliterated equivalents are rejected; fuzzy matches with different keys are warnings only and may proceed after acknowledgement. The user cannot override a hard-key collision.
- On creation and rename, recompute `dedupe_key` and enforce the family-scoped unique constraint atomically; a rename that collides with another record’s key is rejected with a clear explanation. Exclude the record itself from canonical-name and alias collision checks, so a case-only or normalization-equivalent rename is allowed. Store renames follow the same rule as category renames. In MVP, allow creation and renaming. Deletion is allowed only when no expense allocation, store default-category, recurring template/occurrence, or other record references the category; otherwise block it with an explanation. Archiving is out of scope.
- Used to classify all or part of an expense. Category allocation amounts must reconcile to the expense total.

## 11. Recurring Expenses

Recurring expense templates are included in MVP and have a dedicated **Recurring expenses** entry in the main menu.

A recurring template should support:
- Store, amount, currency, and category allocation(s).
- Schedule/frequency: weekly, monthly, or a configurable interval.
- Start date, optional end date, and next due date.
- Active, paused, and cancelled states.
- Family ID and creator ID.

A due occurrence does not silently create an expense. It creates a pending occurrence and prompts the template creator to **Confirm**, **Edit**, or **Skip** it. Pending occurrences never block other bot actions. When the creator next interacts with the bot, show a non-blocking reminder and a list of pending occurrences; allow handling them individually or explicitly skipping all currently listed occurrences. Ignored occurrences remain pending and are shown again. Do not create duplicate pending occurrences. `next_due_date` advances when a pending occurrence is generated, not when it is confirmed, so later schedules can proceed while older occurrences remain pending. No automatic catch-up expense is created without confirmation. Deletion of stores/categories is allowed only when they are unreferenced; deleting referenced records and all archiving/replacement flows are out of scope for MVP.

The proposed expense date defaults to the scheduled date and is editable, with a one-tap **Today** choice. Confirmation validates the final date as a normal expense date and rejects future dates. The exchange rate is selected based on the final confirmed expense date, not necessarily the originally scheduled date. The creator alone may edit, pause, or cancel their recurring template in MVP.

For monthly schedules on days 29–31, use the last calendar day in months that do not contain the scheduled day; in following months, resume the original intended day. Occurrence generation must be idempotent. Confirming creates an ordinary expense and follows the same validation, conversion, audit, and author-only edit/delete rules. The created expense author is the template creator.

## 12. Localization

Initial supported languages: English, Ukrainian, Polish, and Russian. English is the fallback when Telegram does not provide a supported locale and the user has not selected a language.

Requirements:
- Keep UI strings in translation resources, not hard-coded in handlers.
- Store a user's language preference; language belongs to the user, not the family.
- On first interaction, use Telegram's language setting if it maps to a supported language; otherwise use English. Let the user choose or change their language in onboarding/Settings. Once selected, the user's saved preference takes precedence across all families. Do not fall back to a family language.
- Format dates and numbers appropriately for the selected locale.
- Currency codes should be unambiguous even when symbols overlap.

## 13. Authorization and Auditability

- Verify family membership for all family-scoped reads and writes.
- Include the relevant family and entity identifiers in callback payloads where needed; on every action, validate that the acting user is still a member of that specific family and authorized for that entity. Do not rely only on a mutable “active family” value or on hidden buttons.
- In MVP, only the author may edit or delete an expense.
- Centralize permission decisions in an authorization/service layer.
- Keep a change history for important mutations, including expense edits/deletions, store and category creation/renaming/physical deletion, store-alias creation/changes/deletion, manual exchange-rate changes, reporting-currency changes, and relevant settings changes.
- Never rely on hidden buttons alone for security; validate authorization on the server for every mutation.

## 14. Suggested Data Model

This is a logical model; names and normalization can be adjusted during implementation.

### Core entities

**User**
- `id`
- `telegram_user_id` (unique)
- `language` (user-selected supported language)
- `created_at`
- `updated_at`

**Family**
- `id`
- `name`
- `country_code` (optional)
- `timezone`
- `default_expense_currency`
- `reporting_currency`
- `default_exchange_rate_provider`
- `created_at`
- `updated_at`

**FamilyMember**
- `id`
- `family_id`
- `user_id`
- `status` (active in MVP)
- `joined_at`
- `created_at`

Unique constraint: (`family_id`, `user_id`). Leaving/removing membership is out of scope for MVP.

**Expense**
- `id`
- `family_id`
- `created_by_user_id`
- `store_id`
- `expense_date`
- `original_amount`
- `original_currency`
- `expense_type` — default `family`; not exposed in MVP UI
- `created_at`
- `updated_at`
- `deleted_at` (nullable)
- `deleted_by_user_id` (nullable)

**Category**
- `id`
- `family_id`
- `name`
- `dedupe_key` (deterministic canonical key for hard duplicate prevention)
- Unique constraint: `(family_id, dedupe_key)`
- `created_at`
- `updated_at`

**ExpenseCategory**
- `id`
- `expense_id`
- `category_id`
- `amount`
- `created_at`
- `updated_at`

The sum of `ExpenseCategory.amount` must equal `Expense.original_amount` at the original currency's supported minor-unit precision. For report breakdowns in the reporting currency, allocate the converted expense total proportionally according to the original category allocation amounts. Round each category to the target currency's minor unit, then distribute any rounding remainder using a deterministic largest-remainder rule so category totals reconcile to the converted expense total. This is a reporting allocation rule, not an accounting ledger.

**Store**
- `id`
- `family_id`
- `name`
- `dedupe_key` (deterministic canonical key for hard duplicate prevention)
- Unique constraint: `(family_id, dedupe_key)`
- `default_category_id` (nullable)
- `created_at`
- `updated_at`
- On rename, recompute `dedupe_key` and reject a family-scoped collision atomically; exclude the record being renamed from collision checks so case-only/normalization-equivalent renames do not conflict with themselves; also check aliases in the same family, excluding aliases belonging to that same store.

**StoreAlias** (optional but recommended for confirmed alternate names)
- `id`
- `family_id`
- `store_id`
- `alias` (user-confirmed alternate spelling/name)
- `normalized_alias` (optional derived search aid; not authoritative for uniqueness)
- `alias_dedupe_key` (deterministic hard-duplicate key)
- Unique constraint: `(family_id, alias_dedupe_key)`; enforce atomically with checks against canonical store keys and other aliases in the same family
- `created_at`

Store and category search use the shared search-index abstraction described in §6.3; the concrete engine is deferred to system design. Search handles case/diacritic-insensitive matching, language-aware transliteration across Latin/Cyrillic scripts, and fuzzy matching with edit distance 1. Search may return `Biedronka` for queries `biedronka`, `Бєдронка`, and `бедронка`, even when hard keys differ. The fixed Ukrainian hard-key transliteration and character-normalization rules are defined in §6.3 and apply equally to `Store` and `Category`; hard-key output never depends on actor language. Do not add `dedupe_key_version` in MVP; if the algorithm is changed in the future, a migration to recalculate keys and detect/resolve collisions is required as part of that future change, not now. A separate category index is not required for MVP. Keep explicit `StoreAlias` values optional and family-scoped. Persist `StoreAlias.alias_dedupe_key` with a unique constraint on `(family_id, alias_dedupe_key)`; `StoreAlias.family_id` must equal the referenced store's `family_id`, enforced with a composite foreign key or equivalent database constraint. Alias-key creation and checks against canonical store keys and other aliases must be atomic in one transaction. Canonical-name creation/rename must also check existing aliases in that family, excluding the same store when renaming its own canonical name or comparing its own alias to its own canonical name. Every query must filter by `family_id`. Use the Telegram `update_id` as the idempotency source for message updates and `callback_query.id` for callback actions; derive a stable request key from the update type/id and operation, and persist/process it atomically. If the index is unavailable, database key constraints remain authoritative; use database lookup for hard collisions and disclose if fuzzy suggestions cannot be checked. Index results are candidates only: re-fetch each candidate from the database scoped by `family_id`, discard missing/deleted records, and use the live database record for selection and persistence.

### Currency entities

**ExchangeRate**
- `id`
- `source_currency`
- `target_currency`
- `rate`
- `rate_date`
- `published_at` (nullable)
- `provider` (e.g. NBP or another supported provider)
- `retrieved_at`
- `method` (direct/cross-rate, if applicable)

This table stores only reusable reference rates retrieved from supported exchange-rate providers. It does not store manually entered rates. Rates may be reused across families because they are public provider rates, when currency pair, provider, and applicable date match. A manual rate must never be inserted into this shared table. The cache may be populated during read-only report generation; this does not permit writes to expenses, conversions, or family entities.

**ExpenseConversion**
- `id`
- `expense_id`
- `source_currency`
- `target_currency`
- `exchange_rate_id` (nullable if a manually supplied rate is stored directly)
- `rate_used`
- `rate_date`
- `provider` (nullable for a fully manual rate, if no external provider was used)
- `rate_type` (automatic/manual)
- `converted_amount`
- `calculated_at`
- `supersedes_conversion_id` (nullable) or equivalent version link
- `superseded_at` (nullable) or equivalent metadata indicating whether a later conversion version superseded this record
- Versioning metadata to preserve recalculation history. Do not store an `is_current` flag for whether the target matches the family reporting currency; determine that by comparing `target_currency` with the report's reporting currency. A conversion is the latest version when it has not been superseded.

Create a conversion record only when source currency differs from target/reporting currency. Keep historical conversion records traceable. In MVP, changing reporting currency does not automatically recalculate them; the schema must support explicit future recalculation and supersession.

### Recurring expenses and invitations

**RecurringExpense**
- `id`
- `family_id`
- `created_by_user_id`
- `store_id`
- `amount`
- `currency`
- `start_date`
- `end_date` (nullable)
- `schedule_type`
- `schedule_interval` / schedule configuration
- `next_due_date`
- `status`
- `created_at`
- `updated_at`

Use a `RecurringExpenseCategory` table to represent template category splits. Use `RecurringOccurrenceCategory` for edited draft allocations when an occurrence is pending confirmation.

**RecurringOccurrence**
- `id`
- `recurring_expense_id`
- `scheduled_date`
- `proposed_expense_date` (nullable; defaults to scheduled date)
- `proposed_amount` (nullable; defaults to template amount)
- `proposed_currency` (nullable; defaults to template currency)
- `status` (`pending_confirmation`/`created`/`skipped`)
- `skipped_reason` (nullable; e.g. `creator_skipped`, `template_paused`, `template_cancelled`)
- `created_expense_id` (nullable)
- `resolved_at` (nullable)
- Unique constraint to prevent duplicate processing of the same occurrence. Editing a proposed occurrence updates its persisted draft values until confirmation; the final expense is created only on confirmation. Store proposed category allocations in a separate `RecurringOccurrenceCategory` table or equivalent draft structure.

**Invitation**
- `id`
- `family_id`
- `token_hash`
- `created_by_user_id`
- `expires_at`
- `revoked_at`
- `max_uses` (nullable)
- `created_at`

**InvitationUse**
- `id`
- `invitation_id`
- `joined_user_id`
- `used_at`

A Telegram username is not an invitation identity or membership credential.

**AuditLog**
- `id`
- `family_id`
- `actor_user_id`
- `entity_type`
- `entity_id` (scalar identifier, deliberately not a foreign key to the audited entity)
- `action`
- `before_data` / `after_data` as JSON snapshots containing the fields needed to render the historical state independently of the live entity; include relevant original names/values and identifiers, not just references
- `created_at`

Audit snapshots must not have a hard foreign-key dependency on the audited entity or its mutable fields. Deleting an eligible store/category in MVP must not delete or invalidate existing audit records. A future physical deletion of a previously referenced entity must still leave its audit snapshots readable. The MVP does not need a UI for browsing audit snapshots.

Use appropriate foreign keys, indexes, decimal precision, and transactional validation. Use decimal types rather than floating-point types for money and exchange rates.

## 15. Suggested Architecture

Use a layered design:

1. **Telegram handlers/UI** — receive updates and render keyboards/cards.
2. **Application services** — orchestrate add/edit/report/invitation flows.
3. **Domain/business logic** — expense validation, category reconciliation, conversion rules, recurrence scheduling, authorization.
4. **Repositories/data access** — family-scoped persistence.
5. **External integrations** — Telegram Bot API, exchange-rate provider adapters (including NBP where appropriate), CSV/XLSX generation.

Important principles:
- Keep authorization outside individual Telegram handlers.
- Scope repositories and service calls to a family.
- Use database transactions for saving expenses and category splits.
- Use idempotency for Telegram retries and recurring occurrences.
- Keep exchange-rate retrieval replaceable behind an interface.
- Do not block the bot's interaction flow indefinitely while calling external rate services.
- Log errors without exposing sensitive data.

## 16. Core User Stories

1. As a user, I can create a family and invite other members.
2. As a user, I can belong to multiple families and switch between them.
3. As a family member, I can add a family expense in a few Telegram steps.
4. As a user, I can search for and select an existing store during expense entry, and manage/create stores in the separate Stores section.
5. As a user, I can allocate an expense across multiple categories and receive validation if the amounts do not add up.
6. As an expense author, I can edit or delete my expense.
7. As a family member, I can browse and search the family's expense history.
8. As a family member, I can view reports for a month, a range of months, or exact dates, choose automatic historical rates or a report-only manual override for each needed currency pair, and continue with automatic rates in one tap.
9. As a family member, I can export reports to CSV or XLSX.
10. As a family member, I can record purchases in a currency different from the reporting currency.
11. As any active family member, I can change the reporting currency; this does not automatically recalculate all historical conversion records in the MVP.
12. As a user, I can change the default currency used when entering new expenses without changing the reporting currency.
13. As the expense author, I can enter or correct a manual rate while adding/editing my expense, whether or not an automatic rate is available.
14. As a user, I can set up a recurring expense and confirm each occurrence before it is recorded.
15. As a user, I can use the bot in English, Ukrainian, Polish, or Russian, and change my language in Settings.
16. As a family member, I can create and rename shared stores/categories, change a store’s default category, manage explicit store aliases, and delete stores/categories only when no records reference them; archiving is out of scope.
17. As a family member, I can join a family through an invitation link and see only that family's data.
18. As the creator of a recurring template, I can confirm, edit, or skip a due occurrence, and a pending occurrence remains available if I ignore it.

## 17. Acceptance Criteria

### Family isolation and membership
- A user cannot view or modify a family's data without membership.
- Switching families changes the scope of all subsequent actions.
- No report or search result includes another family's data.

### Expense creation
- A new expense defaults to the family's expense-entry currency.
- The user can choose a different currency for an individual expense.
- The purchase date defaults to today and can be changed.
- Store default category is preselected but can be changed.
- Stores are created and managed in the separate Stores section, not during expense entry; expense entry only selects an existing store.
- The expense cannot be saved if category allocations do not equal the original total.
- All MVP expenses are saved with `expense_type = family`; no type selector is shown.

### Currency conversion
- The system suggests an exchange-rate provider based on family country/reporting currency where possible; the user can choose a supported provider when setting or changing reporting currency.
- Provider adapters allow multiple providers to be added without changing core expense/reporting logic.
- No `ExpenseConversion` record is created when the expense currency equals the target/reporting currency.
- When a conversion is saved for an expense, it stores source/target currencies, rate, rate date, source/provider or manual origin, rate type, converted amount, and calculation time.
- `ExchangeRate` contains only reusable automatic provider rates. Manually entered rates are stored with the relevant `ExpenseConversion` and are not shared with other expenses or families.
- The expense author alone may enter/correct a manual rate as part of that expense's creation/edit flow, even when an automatic rate is available. During report generation, the report creator may separately enter an optional manual rate for a needed currency pair; it applies only within that report and is not persisted. Family Settings configure only the automatic rate provider, never a manual-rate source.
- Changing reporting currency or provider does not automatically rewrite or bulk-recalculate historical `ExpenseConversion` records.
- Report generation does not create, update, or supersede `ExpenseConversion` records. For each expense, use the original amount if its currency equals the report currency; otherwise use a saved, non-superseded `ExpenseConversion` targeting the report currency, regardless of whether its rate is manual or automatic. For each remaining currency pair, default to automatic historical provider rates and offer an explicit alternative: enter one report-only manual rate that overrides all expenses of that pair without a saved conversion, including expenses that have a historical provider rate. Explain this override-all scope. Provide a one-tap **Continue with automatic rates** action. If automatic historical rates are unavailable for an expense, use the current provider rate as a clearly labeled fallback; if no rate is available, leave it unconverted and mark the report incomplete. Report-only manual rates live in the report session for 24 hours by default, including pagination and exports, and are not saved to any expense, conversion, exchange-rate cache, or family setting. On expiry, require a new report session and warn that rates/totals may differ. There is no manual-rate source in Settings and no `FamilyManualExchangeRate` entity.
- Historical provider rates and current-rate fallback conversions are labeled with rate basis/date/source where available. Report-only manual rates are labeled in report details and included in exports generated from the same report session, including the pair and override-all scope; they are not labeled on standalone expense cards. If no rate is available, disclose the unconverted expense and do not present a partial subtotal as complete.
- A conversion is applicable only when its target currency matches the report's reporting currency and it has not been superseded by a later conversion version. Do not use a single `is_current` flag to represent both target-currency applicability and version supersession.
- After every edit, determine whether conversion to the current reporting currency is needed. If not needed because the expense currency equals the reporting currency, supersede any previously applicable conversion and do not create a replacement. If conversion is needed, retain the prior rate after an amount-only edit only when its source/target pair still matches the expense currency and current reporting currency; create a new conversion version with the recalculated amount and supersede the old version. If the pair no longer matches or the purchase date/currency changes, obtain an applicable rate for the updated pair/date or ask the author to enter a manual rate. Keep old versions for audit; do not delete them.
- Reports distribute a converted expense total across categories proportionally and deterministically round allocations so the category totals equal the converted expense total.
- Any active family member can configure default expense-entry currency, reporting currency, and default provider independently in the MVP. The family Settings menu also exposes time zone and provider configuration; there is no family-wide manual exchange-rate setting.
- Automatic rates identify the provider and rate actually used. Provider changes do not silently overwrite saved conversions.
- The schema supports a future explicit recalculation from original expense amounts/currencies, preserving prior versions; this is not run automatically in MVP.

### Editing, deletion, and history
- Only the author can edit/delete an expense in MVP.
- Deletion is soft deletion.
- Deleted expenses do not appear in normal history, search, or reports.
- Important changes are recorded in audit history.

### Reports and exports
- The user can select a month, a range of months, or exact dates.
- Totals, category breakdowns, store breakdowns, and spending dynamics are available.
- Reports do not show per-person spending totals.
- CSV and XLSX exports match the selected family, period, and reporting currency.

### Recurring expenses
- A Recurring expenses item is available in the main menu.
- Pending occurrences do not block other actions; a returning creator sees a non-blocking list and can process items individually or skip all currently listed items. `next_due_date` advances when a pending occurrence is generated.
- Confirmation rejects a future expense date. Deletion of stores/categories is allowed only when they are unreferenced; deleting referenced records and all archiving/replacement flows are out of scope for MVP.
- Due occurrences remain pending until the template creator confirms, edits, or explicitly skips them; pending occurrences are shown again when the creator next interacts with the bot.
- The creator can edit the proposed date (default scheduled date) and choose Today quickly. The rate is based on the final confirmed date. When a template is paused or cancelled, its unprocessed pending occurrences are marked `skipped` and cannot later be confirmed. Store `skipped_reason` as `template_paused` or `template_cancelled`; explicit user skips use `creator_skipped`, so history distinguishes these cases.
- Only the template creator can edit, pause, or cancel the template in MVP.
- Monthly schedules for days 29–31 clamp to the month's last day and resume the intended day in subsequent months.
- A single scheduled occurrence cannot create duplicate expenses.

### Invitations and membership
- A recipient joins only through an explicit Telegram deep-link flow and confirmation.
- Invitations default to a 48-hour expiry and one successful use; use limits are checked atomically. Telegram deep-link `start` tokens must be no longer than 64 characters and contain only `[A-Za-z0-9_-]`. `InvitationUse` is the source of truth for uses, and invitation creation/revocation/use are audited. Reopening an invitation link as a user who is already a member does not consume a use. Revocation blocks future joins only.
- Invitation expiry, revocation, and optional maximum-use limits are enforced; successful uses are recorded.
- Usernames are not used as identity or proof of membership.
- Leaving/removing members and family closure are out of scope for MVP.

### Stores, categories, and validation
- Store and category creation and rename enforce hard uniqueness of `(family_id, dedupe_key)` in the database. `dedupe_key` follows the single canonical algorithm in §6.3.1 and never depends on actor language. Tests must execute the canonical test vectors there rather than maintain a second normalization specification. Exact canonical/transliterated collisions are rejected, including concurrent attempts; fuzzy matches with different keys are warnings only. A separate category index is not required. An idempotency key or equivalent atomic request-level safeguard prevents duplicate processing of the same create request; every query is family-scoped, and tests verify isolation. If the index is unavailable, database hard-duplicate protection remains active, while fuzzy suggestions may be unavailable. Canonical names do not require alias rows; aliases must not bypass duplicate checks.
- Store uniqueness acceptance tests must execute the complete canonical test-vector table in §6.3.1, including punctuation at boundaries, punctuation between words, apostrophe variants, symbol/emoji handling, and compatibility characters. Do not maintain a second list of normalization equivalences here. Separately verify that `бедронка` may be created as a distinct hard key (`bedronka` under the fixed scheme), but search suggests `Biedronka`; apply the same §6.3.1 behavior to category names using equivalent category examples. The same names may exist in different families. Fuzzy matching with edit distance 1 is required to suggest likely candidates such as `bedronka`/`biedronka` and search must return likely Biedronka candidates for `бедронка`; a fuzzy/transliteration search match with a different hard key is advisory and may be created after acknowledgement. Unrelated names such as `Biedronka`/`Lidl` or `Żabka`/`Carrefour` must not be treated as equivalent. Distinct branches with the same chain name require a meaningful differentiator in the canonical name.
- Aliases are optional alternate names and are family-scoped; persist `alias_dedupe_key` with unique constraint `(family_id, alias_dedupe_key)`. In a single transaction, alias creation must check its key against other aliases and all other stores' canonical `dedupe_key` values; canonical-name creation/rename must check against aliases. Concurrent conflicting alias/canonical operations must not both succeed; use a family-scoped lock or equivalent transaction-isolation mechanism because a unique index alone cannot enforce uniqueness across the canonical-name and alias tables. Stores are never auto-merged based on fuzzy matching.
- When the user leaves expense entry to create a store, the draft is retained and can be resumed for 24 hours. After expiry, the bot clearly says the draft expired and offers a restart; it never silently discards a live draft during the navigation.
- A category is required for every saved allocation. Incomplete allocation rows remain draft-only, and an expense cannot be saved unless allocations exactly equal the original total.
- Zero/negative amounts, unsupported currencies, invalid/future normal expense dates, and unsupported precision are rejected with clear messages.
- Deleting a store/category is allowed only when no record references it. For stores, even a soft-deleted expense retained for history counts as a reference, so a store linked to any expense ever created cannot be deleted in MVP; only mistakenly created, never-referenced stores can be deleted. Category deletion is similarly blocked by any expense allocation, store default-category, recurring template/occurrence, or other reference. Deletion is physical in MVP and frees the canonical/alias key; the database must remove the deleted store/category from the search index (or enqueue a reliable deletion event) and acceptance tests must verify it no longer appears in search. Archiving stores/categories is out of scope for MVP.

- Hard-key tests must prove that the key is independent of actor/UI language and always uses the Ukrainian national transliteration scheme (Cabinet of Ministers of Ukraine Resolution No. 55, 27 January 2010), with fixed fallbacks `ы` → `y`, `э` → `e`, `ъ` → empty, and `ё` → `yo`. Search may use language-aware alternatives, but these must not affect hard uniqueness. No `dedupe_key_version` is added in MVP; if the algorithm changes later, a future migration must recalculate keys and detect/resolve collisions.
- The canonical §6.3.1 test vectors must include `Чайка` → `chaika`, `Київ` → `kyiv`, `Йосип` → `yosyp`, and standalone `ё` → `yo`. Apostrophe tests must verify `McDonald's` = `McDonalds` = `McDonald＇s` and all listed apostrophe variants in §6.3.1 (including `м´ясо` and `м＇ясо`) = the key for `мясо`; trademark/service-mark tests must verify `Pepco™` = `Pepco℠` = `Pepco`.
- All hard-key normalization behavior, operation order, apostrophe handling, positional Ukrainian transliteration rules, explicit Latin mappings, unsupported-script handling, and canonical test vectors are defined only in §6.3.1. Acceptance tests must use those vectors; do not duplicate or paraphrase the algorithm elsewhere.
- Renaming a store/category must not self-conflict: exclude the record being renamed and its own aliases from collision checks. An alias matching its own store's canonical key is not a cross-entity collision, but it must still satisfy uniqueness against all other stores and aliases.
- Search-index results are candidate IDs only. Before showing/selecting a result, re-fetch it from the database with `family_id` filtering and discard deleted/nonexistent rows. `StoreAlias.family_id` must equal its store's family via composite FK or equivalent DB constraint. Idempotency uses Telegram `update_id` for message updates and `callback_query.id` for callback actions, persisted atomically with the operation.
- Audit coverage explicitly includes store/category creation, rename and physical deletion, plus store-alias changes; audit snapshots remain readable without a foreign-key dependency on the live entity.

### Localization
- English, Ukrainian, Polish, and Russian are available. Language is user-specific, can be changed in Settings, and applies across all families.
- First interaction uses Telegram's supported language when available; otherwise English is used until the user selects a preference. Family language is not used as a fallback.
- Dates and numbers are formatted appropriately for the selected language.

### Time zone
- Each family has a configurable time zone. During family creation, suggest one using available context such as Telegram locale or family country and ask the user to confirm or change it; use UTC only when no reasonable suggestion is available. It governs “today”, future-date validation, recurring occurrence scheduling, and report-period boundaries. Any active family member can change it in Settings.

### Telegram interaction scope
- The MVP supports private chats with the bot; group-chat interaction is out of scope.
- Each callback identifies the relevant family/entity and revalidates membership/authorization rather than relying only on the current active-family selection.

## 18. Implementation Phases

### Phase 1 — Foundation
- Telegram user onboarding and localization.
- Families, memberships, family switching, and invitations.
- Family-scoped settings and authorization layer.

### Phase 2 — Expenses
- Stores, aliases, categories.
- Add/edit/delete expense flows.
- Category split validation.
- Expense cards and history.

### Phase 3 — Reporting and currencies
- Exchange-rate provider adapter(s) and rate caching; NBP may be used for Poland.
- Separate exchange-rate and conversion tables.
- Manual rate correction.
- Reporting-currency setting changes; future explicit recalculation capability in the data model (no automatic bulk recalculation in MVP).
- Reports, search refinements, CSV/XLSX export.

### Phase 4 — Recurring expenses and hardening
- Recurring templates and confirmation flow.
- Audit coverage.
- Idempotency, error handling, security checks, and acceptance testing.

## 19. Decisions to Preserve

- MVP expenses are family expenses only; the data model reserves `expense_type` for future use.
- There is no personal/family filter in the MVP.
- Every family has separate data.
- The default expense-entry currency and reporting currency are separate settings.
- Automatic exchange rates use the family-configured provider; NBP is one example for Poland.
- Exchange-rate data and per-expense conversion results are stored separately from the main expense table.
- A conversion result is not stored when expense currency equals reporting currency.
- Changing reporting currency does not automatically recalculate historical conversions in the MVP; the schema supports a future explicit recalculation from original amounts/currencies.
- The MVP uses a Telegram chat and standard keyboards, not a Mini App.
- Reports do not include per-person totals.
- Recurring expense occurrences require explicit confirmation, editing, or skipping by the template creator; ignored occurrences remain pending.
- User language is independent of family settings; supported languages are English, Ukrainian, Polish, and Russian, with English as the initial fallback.
- Report generation is read-only for expenses and saved conversions; report-only manual rates and automatic fallbacks are session-scoped, with exports from the same report session using the same rates. Automatic historical provider rates are the default; the user may instead choose an explicit report-only manual override-all rate per currency pair. A one-tap continue action uses automatic rates. Current-rate fallback is used only when an automatic historical rate is unavailable and is clearly labeled; report sessions expire after 24 hours with a warning that regenerating may change totals.
- Automatic provider rates are cached in `ExchangeRate`; manually entered rates belong only to the relevant `ExpenseConversion`. There is no family-wide manual-rate entity or setting.
- Private bot chats are in MVP; group-chat interaction and family membership lifecycle management are out of scope.

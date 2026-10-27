# Family Expenses Telegram Bot — Business Requirements

**Document status:** Consolidated business requirements for MVP  
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
- Store search behavior is defined in §§6.3 and 10.
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

For MVP, invitations expire after 48 hours by default and allow one successful use by default (`max_uses = 1`); the creator may choose a different maximum-use limit. Store `expires_at` and `revoked_at`. `InvitationUse` is the source of truth for use count; do not store a redundant `use_count`. Record each successful use and enforce the configured maximum. Revocation blocks future joins only and does not remove users who already joined. Audit invitation creation, revocation, and successful use. Joining must be an explicit action by the recipient after opening the bot.

## 5. Main Telegram Navigation

Use standard Telegram controls. The MVP supports private chats with the bot only; group-chat interaction is out of scope.

### Reply-keyboard main menu

- Expense action: **Add expense** when the active family has no resumable unfinished draft for this user; **Continue creating expense** when one exists for `(user_id, active_family_id)`. Both labels and `/add` invoke the same handler. After checking membership and active family, the handler resumes/re-anchors an Active or Paused draft, reports `Saving` as in progress, and routes `SaveUnknown` to reconciliation. If a stale Continue action finds no Active/Paused draft, explain that it expired and offer a fresh start. If `Add expense` or `/add` finds no draft, it starts a new expense directly. Refresh the label when draft status or active family changes and whenever a new keyboard is rendered. A stale label is safe because the handler rechecks the current family-scoped state.
- **Reports**
- **History / Search**
- **Stores**
- **Categories**
- **Members**
- **Recurring expenses**
- **Settings**

Use inline keyboards for contextual actions, selections, confirmation, pagination, and editing.

When the user belongs to multiple families, provide a clear family selector and display the active family in relevant flows.

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

The expense-entry currency defaults to the family's `default_expense_currency`, but the user can select another currency for an individual purchase. The centrally configured MVP currency list uses valid ISO 4217 codes and includes `ALL`, `AMD`, `AZN`, `BAM`, `CAD`, `CHF`, `CZK`, `DKK`, `EUR`, `GBP`, `GEL`, `HUF`, `ISK`, `KZT`, `MDL`, `MKD`, `NOK`, `PLN`, `RON`, `RSD`, `SEK`, `TRY`, `UAH`, and `USD`.

A store may have a default category. When the store is selected, preselect that category, but allow the user to change it. A category is required for every expense; if no suitable category exists, direct the user to the separate Categories section and preserve the in-progress draft so they can return after creating one. Do not create a category inline.

Store creation and management take place in the separate Stores section. If no matching store exists, direct the user there and preserve the expense draft for return; do not create a store inline. Apply the draft lifecycle in §6.2.1.

### 6.2.1 Expense draft scoping and lifecycle

- An expense draft is strictly scoped to the tuple `(user_id, family_id)`.
- Different members of the same family can concurrently create and maintain their own independent expense drafts without interference or state overwrites. A draft remains private to its author until confirmed and saved as a shared family expense.
- A user can have at most one nonterminal expense draft per family at any given time, including Saving and SaveUnknown states.
- If a user belongs to multiple families and switches active families, any draft from another family is inaccessible within the new family context.
- If the user switches back to the previous family within the 24-hour draft lifetime, their unfinished draft for that family becomes available again to resume or discard.
- Active/Paused drafts expire 24 hours after their last meaningful change. Viewing a draft, a no-op action, or invalid input does not extend its lifetime. TTL removes the draft record; `Expired` is not a persisted status.

### 6.3 Store search and creation

- Store search behavior is defined here.
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
- The configurable maximum number of category allocation rows is 20 by default.
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
- Enforce a configurable maximum amount of 100,000,000 in the expense currency and each currency's configured minor-unit precision; return a user-readable validation error for overflow or unsupported precision and do not silently round user input.
- A normal expense purchase date must be a valid date, no earlier than the configurable minimum date (default `2020-01-01`), and cannot be in the future. A recurring template may have a future scheduled date, but confirming an occurrence as an ordinary expense must reject a future purchase date.
- Store and category must belong to the same family as the expense. Archiving stores/categories is out of scope for MVP. Existing store/category references must remain readable.
- Incomplete allocation rows exist only in the chat draft and are not persisted.

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
- To set a category that does not yet exist, open the separate Categories section from the store's category picker and preserve the store selection context. On return, preselect the new category; set it as the default only after the user confirms. Do not create categories inline in Stores.
- In MVP, allow creation, renaming, changing `default_category_id`, managing explicit aliases, and deleting a store only when it has no references from any expense (including soft-deleted expenses retained for history), recurring templates/occurrences, or other records. If referenced, deletion is blocked with an explanation and the user may rename it instead. Archiving is out of scope. Apply the same rule to categories: deletion is allowed only when no expense allocation, store default-category, recurring template/occurrence, or other record references it; otherwise block deletion.
- Apply the search and duplicate rules in §6.3. Store aliases are reserved for explicit alternate names or confirmed variants; the canonical name is searchable without an alias. Hard-key collisions are rejected, while fuzzy matches with different keys are warnings that the user may acknowledge. Records are never auto-merged. To represent distinct branches with otherwise identical names, include a meaningful branch/location differentiator in the canonical name.

### Categories

- Shared within a family. Use the same hard-key and search rules as stores (§6.3). In MVP, allow creation and renaming. Deletion is allowed only when no expense allocation, store default-category, recurring template/occurrence, or other record references the category; otherwise block it with an explanation. Archiving is out of scope.
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

## 13. Verification Coverage

Use this section as a test index; the requirements themselves are defined in the referenced sections.

- **Family isolation:** verify membership checks and family-scoped access (§4, §6.2.1, §9).
- **Expenses:** verify entry, validation, category allocation, editing, and deletion (§6.1–6.7).
- **Currency:** verify settings, conversion, reports, and exports (§7–8).
- **Recurring expenses:** verify scheduling, pending occurrences, and confirmation (§11).
- **Invitations:** verify expiry, revocation, and membership flow (§4.3).
- **Stores and categories:** run the canonical name test vectors and verify search, uniqueness, aliases, deletion, and family isolation (§6.3–6.4, §10).
- **Localization and Telegram:** verify language selection and date/number formatting (§12), plus private-chat scope (§5).

## 14. Implementation Phases

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

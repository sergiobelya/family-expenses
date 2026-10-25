
# Family Expenses Telegram Bot — Product & Technical Requirements

**Document status:** Consolidated requirements for MVP  
**Product:** Telegram bot for family expense tracking  
**Initial UI language:** English  
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
- Create and manage multiple families.
- Invite family members and join families.
- Family expenses.
- Family-scoped stores and categories.
- Add, edit, and delete expenses.
- Store search with case, diacritic, and transliteration normalization.
- Expense history and search.
- Reports by month, multiple months, or exact date range.
- Category and store breakdowns and spending dynamics.
- Currency conversion through a configurable exchange-rate provider, with manual correction. NBP is one supported example for Poland.
- Separate default currency for entering expenses and reporting currency.
- CSV and XLSX exports.
- Recurring expense templates with confirmation before each expense is created.
- Ukrainian, Polish, Russian and English interface localization.
- Authorization checks and an audit trail for important changes.

### Explicitly out of scope for MVP

- Personal expenses in the user interface.
- Filtering by personal/family expense type.
- Per-person spending totals in reports.
- Monthly budgets.
- Reminders and notifications unrelated to recurring-expense confirmation.
- Telegram Mini App.
- Advanced role and permission management UI.

The architecture should make future roles and permission rules possible without rewriting bot handlers. In the MVP, any active family member may change family settings; role-based restrictions are out of scope.

## 4. Users, Families, and Data Isolation

### 4.1 Telegram users

- Identify users by their Telegram user ID, not by username.
- Store language and other user-specific preferences separately from family settings.
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
- Language/default localization settings where appropriate.
- Invitations and audit history.

All queries and mutations involving family data must be scoped to a family ID and checked against the user's membership. In the MVP, any active family member may change family settings, including default currencies, reporting currency, provider, and family language. Role-based settings restrictions are out of scope; authorization should be centralized so roles can be introduced later.

### 4.3 Invitations

Support:
- Invite links using Telegram bot deep links.
- Sharing an invitation through Telegram where possible.
- Sharing a Telegram contact with the bot, where supported.
- Username entry as a convenience when the user knows the username.

**Telegram limitation:** bots cannot universally search for arbitrary Telegram users by name, phone number, or username, nor can a bot always initiate a conversation with a user who has never started it. Invitation flows must explain when the recipient needs to open the bot and join through the deep link. Sharing a contact does not guarantee that the bot can message that person.

Invitation tokens must be unguessable, revocable, and associated with a specific family. Define expiry and reuse policy in implementation; prefer configurable or documented defaults.

## 5. Main Telegram Navigation

Use standard Telegram controls.

### Reply-keyboard main menu

- **Add expense**
- **Reports**
- **History / Search**
- **Stores**
- **Categories**
- **Members**
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
- Notes/description if enabled in the UX.
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

Date defaults to the current date and can be edited.

The expense-entry currency defaults to the family's `default_expense_currency`, but the user can select another currency for an individual purchase.

A store may have a default category. When the store is selected, preselect that category, but allow the user to change it.

Allow store creation from within the add-expense flow.

### 6.3 Store search and creation

- Search should be case-insensitive.
- Normalize diacritics and common spelling variations.
- Support Latin/Cyrillic transliteration variants, e.g. `Biedronka` and `Бєдронка`.
- Store aliases or normalized search forms to improve matching.
- If no store matches the query, offer an action such as **Create store "<entered name>"**.
- Store names and aliases are scoped to a family.

### 6.4 Category splitting

An expense may be assigned to one or multiple categories, with an amount allocated to each category.

Rules:
- The sum of category allocations must equal the original expense amount.
- If the user changes the total or category amounts, create a new category-allocation row for the remaining amount when the remaining amount is positive. The user must assign that remainder to a category; if no category is selected, prompt the user before saving.
- Remaining amount = total expense amount − sum of existing category allocations.
- Do not create an extra row when the remaining amount is zero.
- Prevent saving when category allocations exceed the total or do not reconcile to the total.
- Provide clear validation feedback.
- All category allocation amounts use the expense's original currency.

### 6.5 Expense card

After entry, display a concise editable summary including:
- Store.
- Date.
- Original amount and currency.
- Category allocation(s).
- Reporting-currency amount: use the original amount directly when original currency equals reporting currency; otherwise show the applicable converted amount when available, and clearly indicate when conversion is unavailable.

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
- A deleted expense must be excluded from normal history, totals, and reports, while remaining available for audit/administrative recovery if supported.

## 7. Currency Settings and Conversion

### 7.1 Family Settings

The family must have three independent settings:

1. `default_expense_currency` — the currency preselected when adding a new expense. It can be changed temporarily, for example during a holiday.
2. `reporting_currency` — the currency used for reports, summaries, analytics, and exports.
3. `default_exchange_rate_provider` — the exchange rate provider used by default for automatic currency conversions.

An optional `country_code` setting may be used to suggest an appropriate exchange rate provider based on the family's country.

The system should suggest a suitable provider based on the family's country or reporting currency, where possible. The user must be able to select another supported provider when setting or changing the reporting currency.

Changing the default expense-entry currency must not change the reporting currency or exchange rate provider.

Changing the default exchange rate provider must not automatically modify existing conversion records or historical reports. It affects future automatic conversions and explicit recalculations only.

Changing the reporting currency does not automatically recalculate historical conversions in the MVP. The schema must support a future explicit recalculation process described in Section 7.4.

These settings must be configurable independently.

### 7.2 Conversion policy

- Use the family's configured `default_exchange_rate_provider` for automatic rates. NBP is an example provider for Poland, not a universal default for all countries.
- Use the rate applicable to the purchase date, when available.
- Allow a user to manually override a rate.
- Store the source and whether the rate was automatic or manually adjusted.
- Do not invent a rate if the rate service fails.
- If a rate cannot be retrieved, offer manual entry or clearly indicate that conversion is unavailable.
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

When the expense currency differs from the reporting currency:
- Calculate the converted amount.
- Store the conversion separately from the expense.
- Store the source currency, target/reporting currency, rate, rate date, source, rate mode, converted amount, and calculation timestamp.
- Link the conversion record to the expense.
- Preserve enough information to reproduce or explain the conversion.

Exchange rates and conversion results belong in separate tables, not as a set of exchange-specific columns in the main expense table.

### 7.4 Reporting-currency changes

Changing `reporting_currency` in the MVP must **not** automatically recalculate or rewrite all historical `ExpenseConversion` records.

When the reporting currency changes:
- Keep every expense's original amount, original currency, and purchase date unchanged.
- Do not run a bulk historical conversion job automatically.
- Keep existing conversion records for audit/history, marking their target currency and whether they are current for the family's active reporting currency.
- Reports must not use a conversion whose target currency differs from the family's active `reporting_currency`.
- If an expense's original currency equals the active reporting currency, display its original amount as the reporting-currency amount without creating an `ExpenseConversion` record.
- If a conversion for the active reporting currency is needed but does not exist, resolve it under the report-time rate policy in Section 7.6.

The data model must support an explicit future recalculation operation. If implemented later, it must calculate directly from each expense's original amount/currency, never from a previously converted amount. It must create a new version/result, preserve prior results for traceability, and mark superseded results so reports select only the applicable current result. This future capability is not an automatic MVP behavior.

Changing `default_exchange_rate_provider` alone must not automatically recalculate or overwrite historical conversions.

### 7.5 Rate dates, non-business days, and overrides

- For an automatic rate tied to the purchase date, use the provider's applicable published rate for that date; if the provider publishes only on business days, use its documented latest-applicable-rate rule.
- Store both the expense purchase date and the actual rate publication/effective date.
- Manual rate entry or override must be explicit and auditable.
- A manual rate is specific to its currency pair and target currency; do not silently reuse it for a different pair.
- Cache retrieved provider rates to avoid unnecessary repeated requests.

### 7.6 Rate availability when generating reports

When a report needs a conversion for an expense and there is no applicable saved conversion/rate for the active reporting currency:
- First try the rate applicable to the expense's purchase date using the configured provider.
- If that historical rate is unavailable, use the **current rate** for the same source/target currency pair. The current rate may be retrieved automatically from the configured provider or entered manually by a family member.
- Record the actual rate, its source/provider or manual origin, rate date, rate type, and calculation timestamp in the resulting conversion data.
- Never invent a rate. If neither an automatic current rate nor a manual rate is available, mark the expense as unconverted and clearly disclose it in the report/export.
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

Reports must be scoped to the selected family and period, and exclude soft-deleted expenses.

### 8.3 Output formats

- Human-readable report in Telegram.
- CSV export.
- XLSX export.

Exports should include period, reporting currency, totals, and relevant category/store breakdowns. Where an expense-level export is offered, include original amount/currency and the applicable reporting-currency amount when available.

Clearly indicate when an expense cannot be converted so that report totals are not misleading.

## 9. History and Search

- Provide paginated expense history.
- Support searching by store and other practical fields such as date, amount, and category where feasible.
- Allow users to open an expense card from the results.
- Apply family membership and authorization checks to every search and history request.
- Exclude soft-deleted expenses from standard results.

## 10. Stores and Categories

### Stores

- Shared within a family.
- Support creation and management.
- May have a default category.
- Support aliases/normalized names for search.
- Do not merge stores automatically when a potential duplicate is found; suggest an existing match where appropriate.

### Categories

- Shared within a family.
- Support creation and management.
- Used to classify all or part of an expense.
- Category allocation amounts must reconcile to the expense total.

If a store or category is referenced by existing expenses, prefer deactivation/archive over destructive deletion.

## 11. Recurring Expenses

Include recurring expense templates in MVP.

A recurring template should support:
- Store.
- Amount and currency.
- Category allocation(s).
- Schedule/frequency (at least weekly, monthly, and a configurable interval if practical).
- Next due date.
- Active, paused, and cancelled states.
- Family ID and creator ID.

**Recommended behavior:** recurring templates do not silently create expenses. When an occurrence is due, the bot asks the template creator to confirm before creating the expense. The creator should be able to confirm, skip, or edit the proposed occurrence.

The created expense should follow the same validation, conversion, audit, and authorization rules as a manually entered expense. The confirmation is sent to the recurring-template creator. Avoid duplicate occurrence creation through idempotency or a unique occurrence key.

## 12. Localization

Initial languages:
- English.
- Ukrainian.
- Polish.
- Russian.

Requirements:
- Keep UI strings in translation resources, not hard-coded in handlers.
- Store a user's language preference.
- Resolve language in this order: explicit user language preference, then family default language, then system default language.
- Format dates and numbers appropriately for the selected locale.
- Currency codes should be unambiguous even when symbols overlap.

## 13. Authorization and Auditability

- Verify family membership for all family-scoped reads and writes.
- Verify the active family context on every relevant action.
- In MVP, only the author may edit or delete an expense.
- Centralize permission decisions in an authorization/service layer.
- Keep a change history for important mutations, including expense edits, deletions, manual exchange-rate changes, reporting-currency changes, and relevant settings changes.
- Never rely on hidden buttons alone for security; validate authorization on the server for every mutation.

## 14. Suggested Data Model

This is a logical model; names and normalization can be adjusted during implementation.

### Core entities

**User**
- `id`
- `telegram_user_id` (unique)
- `language`
- `created_at`
- `updated_at`

**Family**
- `id`
- `name`
- `country_code` (optional)
- `default_expense_currency`
- `reporting_currency`
- `default_exchange_rate_provider`
- `default_language`
- `created_at`
- `updated_at`

**FamilyMember**
- `id`
- `family_id`
- `user_id`
- `status`
- `joined_at`
- `created_at`

Unique constraint: (`family_id`, `user_id`).

**Expense**
- `id`
- `family_id`
- `created_by_user_id`
- `store_id`
- `expense_date`
- `original_amount`
- `original_currency`
- `expense_type` — default `family`; not exposed in MVP UI
- `note` (optional)
- `created_at`
- `updated_at`
- `deleted_at` (nullable)
- `deleted_by_user_id` (nullable)

**Category**
- `id`
- `family_id`
- `name`
- `status` (active/archived)
- `created_at`
- `updated_at`

**ExpenseCategory**
- `id`
- `expense_id`
- `category_id`
- `amount`
- `created_at`
- `updated_at`

The sum of `ExpenseCategory.amount` must equal `Expense.original_amount`, subject to currency precision rules.

**Store**
- `id`
- `family_id`
- `name`
- `default_category_id` (nullable)
- `status` (active/archived)
- `created_at`
- `updated_at`

**StoreAlias**
- `id`
- `store_id`
- `alias`
- `normalized_alias`
- Unique/lookup indexes appropriate to family-scoped search.

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

This table stores reusable reference rates retrieved from supported exchange rate providers. Rates should be reusable across expenses where the currency pair and applicable rate date match.

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
- `is_current` or equivalent status indicating whether this result applies to the active reporting currency
- `supersedes_conversion_id` (nullable) or equivalent version link
- Versioning/supersession metadata to preserve recalculation history.

Create a conversion record only when source currency differs from target/reporting currency. Keep historical conversion records traceable. In MVP, changing reporting currency does not automatically recalculate them; the schema must support explicit future recalculation and supersession.

### Recurring expenses and invitations

**RecurringExpense**
- `id`
- `family_id`
- `created_by_user_id`
- `store_id`
- `amount`
- `currency`
- `schedule_type`
- `schedule_interval` / schedule configuration
- `next_due_date`
- `status`
- `created_at`
- `updated_at`

A separate recurring-category allocation table may be used to represent split categories.

**RecurringOccurrence**
- `id`
- `recurring_expense_id`
- `scheduled_date`
- `status` (pending confirmation/created/skipped)
- `created_expense_id` (nullable)
- Unique constraint to prevent duplicate processing of the same occurrence.

**Invitation**
- `id`
- `family_id`
- `token_hash`
- `created_by_user_id`
- `expires_at`
- `revoked_at`
- `created_at`

**AuditLog**
- `id`
- `family_id`
- `actor_user_id`
- `entity_type`
- `entity_id`
- `action`
- `before_data` / `after_data` or a structured change payload
- `created_at`

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
4. As a user, I can select a store or create one without leaving the expense flow.
5. As a user, I can allocate an expense across multiple categories and receive validation if the amounts do not add up.
6. As an expense author, I can edit or delete my expense.
7. As a family member, I can browse and search the family's expense history.
8. As a family member, I can view reports for a month, a range of months, or exact dates.
9. As a family member, I can export reports to CSV or XLSX.
10. As a family member, I can record purchases in a currency different from the reporting currency.
11. As any active family member, I can change the reporting currency; this does not automatically recalculate all historical conversion records in the MVP.
12. As a user, I can change the default currency used when entering new expenses without changing the reporting currency.
13. As a user, I can manually correct an automatic exchange rate.
14. As a user, I can set up a recurring expense and confirm each occurrence before it is recorded.
15. As a user, I can use the bot in Ukrainian, Polish, Russian or English.

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
- Store creation is possible during expense creation.
- The expense cannot be saved if category allocations do not equal the original total.
- All MVP expenses are saved with `expense_type = family`; no type selector is shown.

### Currency conversion
- No `ExpenseConversion` record is created when the expense currency equals the current reporting currency.
- When currencies differ, the conversion record stores both source and target currencies, rate, rate date/source/type, converted amount, and calculation time.
- Automatic exchange rates use the family's configured provider; NBP is an example provider for Poland.
- Manual rate corrections are traceable.
- Changing the reporting currency does not automatically recalculate historical conversions in the MVP; any future explicit recalculation must use original expense amounts/currencies, not previously converted values.
- Reports use only conversion results whose target currency matches the active reporting currency.
- If the purchase-date rate is unavailable when generating a report, the system uses the current automatic or manually entered rate for that currency pair; if no rate is available, it flags the expense and discloses that totals are incomplete.
- Any active family member can configure the default expense-entry currency, reporting currency, and default exchange rate provider independently in the MVP.
- When setting or changing the reporting currency, the user can select a supported exchange rate provider. Any active family member can change these settings in the MVP.
- The system suggests a suitable provider based on the family's country or reporting currency when possible.
- The system supports multiple providers without requiring changes to the core conversion logic.
- Every automatic conversion identifies the provider and rate actually used.
- Changing the default provider alone does not silently recalculate or overwrite historical conversions.
- The data model supports future explicit recalculation from original expense amounts/currencies; changing reporting currency alone does not trigger it in the MVP.
- Previous conversion results remain traceable after recalculation.
- No conversion record is created when the expense currency equals the reporting currency.

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
- Templates support the configured schedule types.
- Each due occurrence requires user confirmation before an expense is created.
- A single scheduled occurrence cannot create duplicate expenses.

### Localization
- All initial supported languages are available.
- UI strings are externalized.
- Dates and numbers are formatted appropriately for the selected language.

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

- MVP expenses are family expenses only; the data model reserves `expense_type` (e.g. family, personal) for future use.
- There is no personal/family filter in the MVP.
- Every family has separate data.
- The default expense-entry currency and reporting currency are separate settings.
- Automatic exchange rates use the family-configured provider; NBP is one example for Poland.
- Exchange-rate data and per-expense conversion results are stored separately from the main expense table.
- A conversion result is not stored when expense currency equals reporting currency.
- Changing reporting currency does not automatically recalculate historical conversions in the MVP; the schema supports a future explicit recalculation from original amounts/currencies.
- The MVP uses a Telegram chat and standard keyboards, not a Mini App.
- Reports do not include per-person totals.
- Recurring expense occurrences require confirmation before being recorded.


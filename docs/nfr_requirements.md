# Family Expenses Telegram Bot — Non-Functional Requirements

**Document status:** Consolidated NFRs and current technical constraints for MVP
**Scope:** System qualities and technical constraints that support the business requirements. Detailed system design is deferred.

## 1. Authorization and Auditability

- Verify family membership for all family-scoped reads and writes.
- Include the relevant family and entity identifiers in callback payloads where needed; on every action, validate that the acting user is still a member of that specific family and authorized for that entity. Do not rely only on a mutable “active family” value or on hidden buttons.
- Centralize permission decisions in an authorization/service layer.
- Keep a change history for important mutations, including expense edits/deletions, store and category creation/renaming/physical deletion, store-alias creation/changes/deletion, manual exchange-rate changes, reporting-currency changes, and relevant settings changes.
- Never rely on hidden buttons alone for security; validate authorization on the server for every mutation.

## 2. Suggested Data Model

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

The sum of `ExpenseCategory.amount` must equal `Expense.original_amount` at the original currency's supported minor-unit precision. Reporting-currency allocation follows Business Requirements §6.4.1.

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

Store and category search use the shared search-index abstraction described in Business Requirements §6.3; matching behavior and hard-key generation are defined there. The concrete engine is deferred to system design. Do not add `dedupe_key_version` in MVP; a future algorithm change requires a migration to recalculate keys and resolve collisions. A separate category index is not required. Keep explicit `StoreAlias` values optional and family-scoped. Enforce uniqueness of `(family_id, alias_dedupe_key)` and ensure `StoreAlias.family_id` matches its store's family with a composite foreign key or equivalent database constraint. Alias-key writes and checks against canonical store keys and other aliases must be atomic. Canonical-name creation and rename must also check aliases in that family, excluding the same store when appropriate. Every query must filter by `family_id`. Use Telegram `update_id` for message-update idempotency and `callback_query.id` for callback actions; derive a stable request key from update type, ID, and operation, and persist/process it atomically. If the index is unavailable, database constraints remain authoritative for hard collisions; use database lookup for those and disclose when fuzzy suggestions cannot be checked. Treat index results as candidates: re-fetch each within `family_id`, discard missing/deleted records, and use the live database record for selection and persistence.

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

## 3. Reliability and Technical Constraints

Use a layered design:

1. **Telegram handlers/UI** — receive updates and render keyboards/cards.
2. **Application services** — orchestrate add/edit/report/invitation flows.
3. **Domain/business logic** — expense validation, category reconciliation, conversion rules, recurrence scheduling, authorization.
4. **Repositories/data access** — family-scoped persistence.
5. **External integrations** — Telegram Bot API, exchange-rate provider adapters (including NBP where appropriate), CSV/XLSX generation.

Important principles:
- Keep authorization outside individual Telegram handlers.
- Enforce the family scoping rules in Business Requirements §§4 and 6.2.1 at repository and service boundaries.
- Save each expense, its category allocations, and any required available conversion in one database transaction. If conversion is unavailable and the user chooses `Save without conversion`, do not create a conversion record. Never leave a partially saved expense.
- Use idempotency for Telegram retries and recurring occurrence generation.
- If an expense save has an unknown outcome, retain the draft as `SaveUnknown` with the original idempotency key. Reconciliation by that key returns the existing expense if committed; retrying with the same key must create at most one expense. Do not expire or replace an unresolved `SaveUnknown` draft with a new one.
- Parse and store money and exchange rates as decimals, never binary floating-point values; enforce each currency's configured minor-unit precision.
- Enforce invitation-use limits atomically with the successful join and `InvitationUse` insert.
- Keep exchange-rate retrieval replaceable behind an interface.
- Do not block the bot's interaction flow indefinitely while calling external rate services.
- Log errors without exposing sensitive data.


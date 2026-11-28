# Phase 2 - Expenses Tasks

### Done

- **Expense Domain Model** — ✓ Done. Created Expense bounded context with:
  - `Expense` aggregate root (`src/Expense/Domain/Entity/Expense.php`) — factory method `create()`, `edit()`, `softDelete()`, allocation reconciliation via bcmath, domain event recording
  - `ExpenseAllocation` child entity (`src/Expense/Domain/Entity/ExpenseAllocation.php`) — category_id, amount in original currency
  - `MoneyAmount` VO (`src/Core/Domain/ValueObject/MoneyAmount.php`) — wrapper around `moneyphp/money` with `fromDecimal()`/`fromInt()` factory methods, arithmetic operations, comparison methods; converts decimal strings to minor units per currency subunit exponent
  - `ExpenseId`, `AllocationId` identifier VOs in `src/Core/Domain/ValueObject/`
  - Domain events: abstract `BaseExpenseEvent` + concrete `ExpenseCreated`, `ExpenseEdited`, `ExpenseDeleted` implementing `AuditableEventInterface`
  - Typed exceptions: `ExpenseNotFoundException`, `AllocationReconciliationException`
  - `ExpenseRepositoryInterface` with family-scoped CRUD operations
  - Unit tests: `ExpenseTest` (15 tests), `MoneyAmountTest` (12 tests), `ExpenseAllocationTest` (3 tests)
  - `ExpenseConversion` and conversion rules are Phase 3.

- **ExpenseStore/ExpenseCategory entities** — ✓ Done. Created read-only entities for display in the Expense bounded context:
  - `ExpenseStore` entity (`src/Expense/Domain/Entity/ExpenseStore.php`) — read-only entity with private properties and getters: `getId()`, `getName()`, `getFamilyId()`
  - `ExpenseCategory` entity (`src/Expense/Domain/Entity/ExpenseCategory.php`) — read-only entity with private properties and getters: `getId()`, `getName()`, `getFamilyId()`
  - Repository interfaces: `StoreRepositoryInterface` (`findById(StoreId, FamilyId): ?ExpenseStore`) and `CategoryRepositoryInterface` (`findById(CategoryId, FamilyId): ?ExpenseCategory`) in `Expense/Domain/Repository/`
  - `Expense::create()` and `Expense::edit()` accept `ExpenseStore` instead of `StoreId`; `$expense->getStore()->getName()` returns the store name
  - `ExpenseAllocation` constructor accepts `ExpenseCategory` instead of `CategoryId`; `$allocation->getCategory()->getName()` returns the category name
  - `Expense::snapshot()` includes `store_name` from the store entity and `category_name` from each allocation's category entity
  - Unit tests updated: `ExpenseTest` (15 tests), `ExpenseAllocationTest` (3 tests)

- **Expense Repository and Persistence** — ✓ Done. Implemented Doctrine persistence layer for Expense bounded context:
  - Custom Doctrine Types: `ExpenseIdType`, `AllocationIdType` (extend `UuidValueObjectType`)
  - Doctrine XML mappings: `Expense.orm.xml` (expenses table with store many-to-one, allocations one-to-many with cascade-persist and orphan-removal), `ExpenseAllocation.orm.xml` (expense_allocations table with category and expense many-to-one), `ExpenseStore.orm.xml` (read-only mapping to stores table), `ExpenseCategory.orm.xml` (read-only mapping to categories table)
  - Both `Expense` and `ExpenseAllocation` store `amount` and `currency` as 2 separate string columns (minor units + ISO 4217 code), consistent with each other; `MoneyAmount` is reconstructed in getters via `MoneyAmount::fromInt()`
  - Repository implementations: `DoctrineExpenseRepository` (save, findById, findByFamilyId — excludes soft-deleted expenses), `DoctrineStoreRepository` (findById with FamilyId scope), `DoctrineCategoryRepository` (findById with FamilyId scope)
  - `softDelete()` is a domain method on `Expense` aggregate, called directly from the command handler with the correct `actorUserId`
  - `ExpenseAllocation` entity has public constructor (child entity, not aggregate root) with `expense` back-reference property for Doctrine many-to-one mapping
  - `Expense::addAllocation()` sets the back-reference automatically
  - Migration `Version20261122120000` creates `expenses` and `expense_allocations` tables with proper foreign keys and indexes
  - Registered new types and Expense mapping in `config/packages/doctrine.yaml`
  - `expense_save_attempts` table and `expense_conversions` table are for later tasks (Draft Infrastructure and Phase 3 respectively)

- **Add Expense Command and Handler** — ✓ Done. Implemented `AddExpenseCommand` and `AddExpenseHandler` in Application layer:
  - `AddExpenseCommand` DTO (`src/Expense/Application/Command/AddExpenseCommand.php`) — carries `FamilyId`, `UserId`, `StoreId`, amount (decimal string), currency, `AllocationItem[]` with category allocations, and `DateTimeImmutable` purchase date
  - `AllocationItem` DTO — carries `CategoryId` and allocation amount (decimal string)
  - `AddExpenseHandler` (`src/Expense/Application/Command/AddExpenseHandler.php`) — validates membership via `FamilyMembershipInterface`, validates currency (throws `InvalidValueException` with `ErrorType::CURRENCY_CODE_UNSUPPORTED`), validates amount is positive, validates purchase date is not in future, validates allocation sum equals total using bcmath, rejects empty/non-positive/mismatched allocations. Looks up `ExpenseStore` via `StoreRepositoryInterface` and `ExpenseCategory` via `CategoryRepositoryInterface`, validates family ownership. Passes `ExpenseStore` and `ExpenseCategory` entities to `Expense::create()`, not raw IDs. Saves `Expense` aggregate atomically with audit snapshot in the same transaction via `DoctrineTransactionMiddleware`
  - Typed exceptions: `StoreNotFoundException`, `CategoryNotFoundException` in `Expense/Domain/Exception/`
  - Unit tests: `AddExpenseHandlerTest` (12 tests) covering successful creation, multiple allocations, non-member rejection, store/category not found, unsupported currency, zero amount, future date, empty allocations, mismatched/excess/non-positive allocation sums
  - Draft infrastructure, `ExpenseSaveAttempt`, and idempotency key handling are deferred to the "Expense Draft Infrastructure" task

- **Edit and Delete Expense Handlers** — ✓ Done. Implemented `EditExpenseCommand`/`EditExpenseHandler` and `DeleteExpenseCommand`/`DeleteExpenseHandler`:
  - `EditExpenseCommand` DTO (`src/Expense/Application/Command/EditExpenseCommand.php`) — carries `ExpenseId`, `FamilyId`, `UserId`, `StoreId`, amount (decimal string), currency, `AllocationItem[]`, and `DateTimeImmutable` purchase date
  - `EditExpenseHandler` (`src/Expense/Application/Command/EditExpenseHandler.php`) — validates membership via `FamilyMembershipInterface`, validates expense exists, validates author-only authorization (throws `ExpenseNotAuthorException`), validates currency, looks up `ExpenseStore` and `ExpenseCategory` entities, passes them to `Expense::edit()` with full revalidation of all fields via domain layer
  - `DeleteExpenseCommand` DTO (`src/Expense/Application/Command/DeleteExpenseCommand.php`) — carries `ExpenseId`, `FamilyId`, `UserId`
  - `DeleteExpenseHandler` (`src/Expense/Application/Command/DeleteExpenseHandler.php`) — validates membership, validates expense exists, validates author-only authorization, calls `Expense::softDelete()` with correct `actorUserId`
  - Typed exception: `ExpenseNotAuthorException` in `Expense/Domain/Exception/`
  - Unit tests: `EditExpenseHandlerTest` (11 tests), `DeleteExpenseHandlerTest` (4 tests) covering successful operations, non-member rejection, expense not found, non-author rejection, store/category not found, unsupported currency, zero amount, future date, mismatched allocations
  - Conversion recalculation rules are Phase 3; Phase 2 edit/delete operates without currency conversion

- **Expense Draft Infrastructure** — ✓ Done. Implemented expense draft storage and lifecycle management:
  - `ExpenseDraftStatus` backed enum (`src/Expense/Domain/ExpenseDraftStatus.php`) — states: Active, Paused, Saving, SaveUnknown, Saved, Discarded with `isTerminal()`, `isTemporary()`, `isDurable()` helpers
  - `ExpenseDraft` DTO (`src/Expense/Domain/ExpenseDraft.php`) — immutable draft data with `createNew()` factory, `with*` mutation methods, `toArray()`/`fromArray()` for JSON serialization, `isExpired()`, `refreshExpiry()`
  - `ExpenseDraftRepositoryInterface` (`src/Expense/Domain/Repository/ExpenseDraftRepositoryInterface.php`) — `find()`, `save()`, `remove()` scoped to (user_id, family_id)
  - `ExpenseDraftRepository` Redis implementation (`src/Expense/Infrastructure/Persistence/Redis/ExpenseDraftRepository.php`) — JSON serialization with TTL, key format `expense:draft:{user_id}:{family_id}`
  - `ExpenseSaveAttemptId` VO (`src/Expense/Domain/ValueObject/ExpenseSaveAttemptId.php`) — distinct immutable identifier
  - `ExpenseSaveAttempt` entity (`src/Expense/Domain/Entity/ExpenseSaveAttempt.php`) — tracks save attempts with `pending()` factory, `markCompleted()`, `markFailed()`, status checks
  - `ExpenseSaveAttemptRepositoryInterface` (`src/Expense/Domain/Repository/ExpenseSaveAttemptRepositoryInterface.php`) — `save()`, `findById()`, `findByIdempotencyKey()`, `findByUserAndFamily()`
  - `ExpenseSaveAttemptIdType` Doctrine custom type (`src/Expense/Infrastructure/Persistence/Doctrine/Type/ExpenseSaveAttemptIdType.php`)
  - `ExpenseSaveAttempt.orm.xml` Doctrine mapping with unique index on `idempotency_key`
  - `DoctrineExpenseSaveAttemptRepository` implementation with family-scoped queries
  - Migration `Version20261122130000` creates `expense_save_attempts` table with foreign keys and indexes
  - Unit tests: `ExpenseDraftTest` (11 tests), `ExpenseSaveAttemptTest` (3 tests)

- **Add Expense Telegram Flow — Subtask 1: Infrastructure** — Create the foundation for the expense flow: `AddExpenseFlowHandler` (implements `FamilyContextFlowHandlerInterface`), `AddExpenseStepInterface`, callback action constants in `CallbackActions`, shared utilities (`ExpenseCardRenderer` for card text/keyboard rendering, `AmountParser` for amount validation per §6.1, `DateParser` for date validation per §6.3), and `ExpenseFlowUtils` for draft management (create/resume/pause/restore). Register the handler in `TelegramUpdateRouter`. Add English translations for all `expense.add.*` keys from the spec.

- **Add Expense Telegram Flow — Subtask 2: Entry & Draft (AE-00, AE-01, AE-02, AE-17, AE-18)** — Implement entry point from menu and `/add`, family context check (AE-01), draft resume (AE-02), expired draft handling (AE-17), and stale callback/unexpected input handling (AE-18). Integrate with `ExpenseDraftRepository` for draft lifecycle. Handle the one-card model: send initial card or re-anchor existing card.
- **Add Expense Telegram Flow — Subtask 3: Store Selection (AE-03, AE-04, AE-05)** — ✓ Done. Added an 8-store paginated picker ordered by latest purchase across the full expense history, with a non-interactive in-card page indicator, family-scoped store search with a limited fallback, default-category preselection, and a paused-draft detour to Catalog store creation that resumes at amount entry.
- **Add Expense Telegram Flow — Subtask 4: Amount & Currency (AE-06, AE-07)** — ✓ Done. Added exact decimal amount parsing and formatting, default/quick currency chips, currency-change preservation with precision-conflict validation, and a paginated searchable currency list.

### Pending

- **Add Expense Telegram Flow — Subtask 5: Category & Split (AE-08, AE-09)** — Implement category picker with store default preselection, category search, draft-paused category creation detour, and split editor (AE-09) with add/remove rows, amount entry per row, remaining/over summary, and validation. On single category or valid split, advance to AE-10.

- **Add Expense Telegram Flow — Subtask 6: Review & Save (AE-10, AE-11, AE-13, AE-14, AE-15)** — Implement review card with edit buttons for store/amount/category/date, date picker (AE-11) with calendar and typed input, save flow (AE-13) with idempotency via `ExpenseSaveAttempt`, success card (AE-14), and save error handling (AE-15) with retry/review/cancel. Conversion display is Phase 3; Phase 2 shows only when expense currency equals reporting currency (no conversion needed).

- **Add Expense Telegram Flow — Subtask 7: Cancel (AE-16) & Integration** — Implement cancel confirmation (AE-16) with skip-confirmation when no store chosen, draft discard, and "keep editing" restore. Wire up all states in `AddExpenseFlowHandler`. Add unit tests for the flow handler, step classes, and utilities.

- **Expense History and Search** — Implement expense history browsing and search following `expense_history.md` specification. States: HS-01 (History list), HS-02 (Search and filters), HS-03 (Expense details), HS-04 (Edit expense), HS-05 (Delete confirmation). Query handlers: `GetExpenseHistory`, `SearchExpenses`, `GetExpenseDetails`. Use `$expense->getStore()->getName()` and `$allocation->getCategory()->getName()` for display without additional lookups. Filters: store, category, date range, amount range. Phase 2 amount search compares original amounts without currency conversion; currency-aware amount search is Phase 3. Paginated results. Author-only edit/delete controls. Family-scoped reads with membership validation.

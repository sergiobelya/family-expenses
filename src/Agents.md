# Implementation Guidelines

Architectural and code structure rules for application code in `src/`. For non-functional and system qualities, see [docs/nfr_requirements.md](../docs/nfr_requirements.md) and [docs/system_design.md](../docs/system_design.md).

## 1. Modular Monolith & Bounded Contexts

Code is organized by Bounded Contexts in `src/`:
- `src/{Context}/` (`Identity`, `Family`, `Expense`, etc.)
- `src/Core/` (shared Value Objects, base aggregate/event contracts, infrastructure entities)
- `src/Presentation/Telegram/` (Telegram delivery mechanism, webhook, flow handlers, UI keyboards/renderers)

### Layer Responsibilities within a Context
- **`Domain/`**: Pure domain logic and POPO entities. No framework or ORM dependencies.
  - `Entity/` (aggregates and entities extending `AggregateRoot` where applicable)
  - `ValueObject/` (immutable self-validating types)
  - `Event/` (domain events implementing `DomainEventInterface`)
  - `Exception/` (typed domain exceptions)
  - `Repository/` (domain repository interfaces / ports)
- **`Application/`**: Use cases and orchestration.
  - `Command/` (command DTOs and pure handlers for state mutations)
  - `Query/` (query DTOs and handlers for data retrieval)
- **`Infrastructure/`**: Framework and technology-specific implementations.
  - `Persistence/Doctrine/Mapping/{Context}/*.orm.xml` (Doctrine XML mapping files)
  - `Persistence/Doctrine/Repository/` (Doctrine repository implementations)
  - External services, security, adapters

## 2. Cross-Context Boundaries
- Contexts must remain decoupled.
- Cross-context references in entities and aggregates must use IDs/Value Objects (`UserId`, `FamilyId`), never direct ORM entity associations across context boundaries.
- Cross-context communication is performed via Domain Events and Application queries/commands.

## 3. Doctrine Persistence & Mappings
- **XML mappings only:** All Doctrine metadata is defined in `src/{Context}/Infrastructure/Persistence/Doctrine/Mapping/*.orm.xml` or `src/Core/Infrastructure/Persistence/Doctrine/Mapping/*.orm.xml`.
- **No ORM attributes/annotations** in `Domain/Entity` classes.
- Database constraints (unique indexes, foreign keys, check constraints) are authoritative.

## 4. Domain Events, Outbox & AuditLog
- Aggregates record domain events via `$this->recordEvent(DomainEventInterface $event)`.
- Events are released using a generator via `$aggregate->releaseEvents()` during Doctrine unit of work flush (`DomainEventsFlushListener`).
- Auditable domain events are recorded in `AuditLog` in the same database transaction. Only events implementing `AsyncDomainEventInterface` are serialized into `OutboxMessage` in that transaction.
- Events implementing `AuditableEventInterface` are automatically recorded in `AuditLog` in the same database transaction.
- **Do not manually instantiate or persist `OutboxMessage` or `AuditLog` in Command Handlers.**

## 5. Domain Types & Errors

- Entity and relationship IDs use distinct immutable identifier value objects (for example, `FamilyId`, `UserId`, `StoreId`, and `CategoryId`) throughout Domain APIs, including entity constructors/factories/mutators, domain events, and repository interfaces. Do not expose Symfony `Uuid` in Domain APIs; convert raw UUIDs at application or transport boundaries and never accept an unrelated identifier type.
- Entity ID getters return their corresponding identifier value object. Application Command and Query DTOs, as well as Application services that accept entity IDs, use the corresponding identifier value objects. Use scalar types for other input fields unless they represent an established domain value object. Convert raw UUIDs into identifier value objects at transport boundaries.
- Every repository or DBAL read that retrieves family-scoped data must include `FamilyId` in its query/filter; authorization checks outside the query do not replace this scope. Explicit exceptions are identity reads (users are global, not family-owned), listing all families a user belongs to for family selection/navigation, and resolving an invitation by its globally unique token hash before its family is known. Operational outbox/inbox processing is also global because that data is not family-scoped. Do not add further exceptions without documenting their reason.
- Persist domain value objects through explicit Doctrine DBAL/XML mappings. Keep serialization and database conversion in Infrastructure, not in domain entities.
- Use value objects for validated entity names and domain codes. Use backed enums for finite state sets instead of string constants.
- Exception messages are static by default because they may be logged. Put variable values in structured exception properties and pass them as logging context. A finite set of distinct messages or a dynamic suffix is acceptable when it materially helps and structured context is unsuitable; keep the leading message static. User-facing messages must be translated in the presentation layer. When wrapping an exception, preserve the original as `previous` and retain relevant context.
- Give each auditable domain event a specific type and entity meaning; avoid generic event classes whose action string carries the actual event type.

## 6. Transactions & Execution Flow
- Every Command handled by `command.bus` executes inside an automatic transaction (`DoctrineTransactionMiddleware`).
- Keep command handlers focused on loading aggregates, invoking domain methods, and saving through repository interfaces.
- Use `symfony/lock` (Redis store) for concurrent critical operations (e.g. user drafts, multi-step wizards) when required.
- Deduplicate incoming Telegram updates via `TelegramUpdateInbox` using unique `update_id`.
- A pessimistic database lock is held for the duration of its enclosing Doctrine transaction and is released on commit or rollback. Acquire it only through a command dispatched on `command.bus`; do not assume it protects work after the transaction ends.

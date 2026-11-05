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
- Every domain event is automatically serialized and saved to `OutboxMessage` in the same database transaction (Transactional Outbox).
- Events implementing `AuditableEventInterface` are automatically recorded in `AuditLog` in the same database transaction.
- **Do not manually instantiate or persist `OutboxMessage` or `AuditLog` in Command Handlers.**

## 5. Transactions & Execution Flow
- Every Command handled by `command.bus` executes inside an automatic transaction (`DoctrineTransactionMiddleware`).
- Keep command handlers focused on loading aggregates, invoking domain methods, and saving through repository interfaces.
- Use `symfony/lock` (Redis store) for concurrent critical operations (e.g. user drafts, multi-step wizards) when required.
- Deduplicate incoming Telegram updates via `TelegramUpdateInbox` using unique `update_id`.

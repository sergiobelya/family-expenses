# Implementation Guidelines

Architectural and code structure rules for application code in `src/`. For non-functional and system qualities, see [docs/nfr_requirements.md](../docs/nfr_requirements.md) and [docs/system_design.md](../docs/system_design.md).

## 1. Modular Monolith & Bounded Contexts

Code is organized by Bounded Contexts in `src/`:
- `src/{Context}/` (`Identity`, `Family`, `Expense`, etc.)
- `src/Core/` (shared Value Objects, base aggregate/event contracts, infrastructure entities)
- `src/Presentation/Telegram/` (Telegram delivery mechanism, webhook, flow handlers, UI keyboards/renderers)

### Design Principles
- The application follows Clean Architecture, DDD (Domain-Driven Design), and logical CQRS. See [docs/nfr_requirements.md](../docs/nfr_requirements.md) for architectural details.
- Application code follows SOLID principles. Favor composition over inheritance. Prefer open/closed design — extend behavior through interfaces and strategies rather than conditional logic. Keep methods focused so they fit on screen; decompose large classes into smaller, single-responsibility components.
- Most classes should be `final readonly` to prevent unintended inheritance and mutation. Exceptions: domain entities (Doctrine needs reflection for hydration), abstract base classes, and classes that must be mocked in tests without stub implementations. If a class has a non-readonly dependency (e.g., a mutable service), the class itself may not be `readonly`, but most properties should still be marked `readonly`.
- Avoid using `Closure` as a dependency injection parameter when the logic can be extracted into a dedicated class. A dedicated class (e.g., a `*Renderer`, `*Builder`, `*Screen`, `*Step`, `*Util`, `*Validator`, etc.) makes the dependency explicit, testable, and reusable.
- Keep methods and classes short and readable. Methods should be no longer than 30 lines, and classes should be no longer than 350 lines unless they cannot be split by design. Decompose large classes into smaller, single-responsibility components. Use private methods to break up long methods.

### Layer Responsibilities within a Context
- **`Domain/`**: Pure domain logic and POPO entities. No framework or ORM dependencies.
  - `Entity/` (aggregates and entities extending `AggregateRoot` where applicable)
  - Aggregate roots are instantiated exclusively through factory methods (e.g., `create()`, `join()`). Keep constructors `private` to enforce invariant validation and domain event recording on creation. Doctrine hydrates entities via reflection, bypassing the constructor.
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
- When a domain event from one bounded context requires modifying an entity in another context, place the event handler in the context that owns the entity being modified. The handler should use its own context's repositories, not import repositories from the publishing context. This aligns with the DDD Event Carrier pattern — the publishing context does not know who listens to its events.

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

## 5. Application Layer

- For Presentation → Application boundaries, follow [System Design §4](../docs/system_design.md#4-application-boundaries).
- Application Command and Query DTOs are `final readonly` classes with `public` properties. They use identifier value objects (e.g., `FamilyId`, `UserId`) for entity references, and scalar types for other input fields. Convert raw UUIDs into identifier value objects at transport boundaries before creating commands or queries.
- Value objects are instantiated at the Application layer boundary (command/query handlers). Handlers convert scalar input from commands to value objects before passing them to domain entity factory methods.

## 6. Domain Types & Errors

- Entity constructors and factory methods accept value object types for fields that carry domain meaning or require validation (e.g., `FamilyName`, `CountryCode`, `CurrencyCode`). Scalar types are acceptable for simple data where no domain validation is needed (e.g., plain text descriptions, timestamps).
- Entity and relationship IDs use distinct immutable identifier value objects (for example, `FamilyId`, `UserId`, `StoreId`, and `CategoryId`) throughout Domain APIs, including entity constructors/factories/mutators, domain events, and repository interfaces. Do not expose Symfony `Uuid` in Domain APIs; convert raw UUIDs at application or transport boundaries and never accept an unrelated identifier type.
- Entity getters return their corresponding value object type, not the underlying scalar. If an entity field is modeled as a value object (e.g., `FamilyName`, `CountryCode`), its getter returns the value object. Cast to scalar at application or transport boundaries.
- Every repository or DBAL read that retrieves family-scoped data must include `FamilyId` in its query/filter; authorization checks outside the query do not replace this scope. Explicit exceptions are identity reads (users are global, not family-owned), listing all families a user belongs to for family selection/navigation, and resolving an invitation by its globally unique token hash before its family is known. Operational outbox/inbox processing is also global because that data is not family-scoped. Do not add further exceptions without documenting their reason.
- Persist domain value objects through explicit Doctrine DBAL/XML mappings. Keep serialization and database conversion in Infrastructure, not in domain entities.
- Use value objects for validated entity names and domain codes. Use backed enums for finite state sets instead of string constants.
- Exception messages are static by default because they may be logged. Put variable values in structured exception properties and pass them as logging context. A finite set of distinct messages or a dynamic suffix is acceptable when it materially helps and structured context is unsuitable; keep the leading message static. User-facing messages must be translated in the presentation layer. When wrapping an exception, preserve the original as `previous` and retain relevant context.
- Use typed domain exceptions for each error situation instead of generic `\InvalidArgumentException`. Prefer a structured exception with an `ErrorType` backed enum (e.g., `InvalidValueException` with `$errorType`) so one field can have multiple distinct error types. The presentation layer maps error types to translation keys using a centralized mapper (e.g., `MapExceptionToTranslatableMessage`), keeping the domain layer independent of presentation concerns like translation keys.
- Give each auditable domain event a specific type and entity meaning; avoid generic event classes whose action string carries the actual event type. Use abstract base classes to share common structure (timestamp, actorUserId, beforeData/afterData) among related events. Keep abstract classes non-final to allow inheritance; concrete event classes should be `final`. Each concrete event class should carry only the data relevant to its semantics — creation events carry after-state, deletion events carry before-state, and modification events carry both.

## 7. Transactions & Execution Flow
- Every Command handled by `command.bus` executes inside an automatic transaction (`DoctrineTransactionMiddleware`).
- Keep command handlers focused on loading aggregates, invoking domain methods, and saving through repository interfaces.
- Use `symfony/lock` (Redis store) for concurrent critical operations (e.g. user drafts, multi-step wizards) when required.
- Deduplicate incoming Telegram updates via `TelegramUpdateInbox` using unique `update_id`.
- A pessimistic database lock is held for the duration of its enclosing Doctrine transaction and is released on commit or rollback. Acquire it only through a command dispatched on `command.bus`; do not assume it protects work after the transaction ends.
- When concurrent modifications must be prevented, choose the approach per situation: atomic database operations (e.g., `INSERT ... SELECT` with conditional WHERE, unique constraints), pessimistic database locks, optimistic locking (version-based retry), or Redis-based locks via `symfony/lock`. Prefer database-level solutions when the lock must be tied to a transaction; use Redis lock for inter-process coordination outside database transactions (e.g., background workers, multi-step wizards).
- Use `SELECT ... FOR NO KEY UPDATE` when locking a row that is not being modified (e.g., reading a parent row while mutating child tables). It serializes concurrent access without blocking child-table inserts through foreign-key locks, and reduces deadlock probability compared to `SELECT ... FOR UPDATE`. Use `SELECT ... FOR UPDATE` when the locked row is being modified or when foreign-key lock propagation is required.

## 8. Input Validation Levels

Validate each rule at the lowest level that has the data it needs. A rule lives at exactly one level; other levels reuse it instead of reimplementing it.

| Level | Validates | Must not |
|---|---|---|
| **Presentation** | Delivery-specific transport and input syntax. Telegram: update type, stale callbacks, callback data, conversion of typed text to canonical form. API: request payload shape and syntax (required fields, types, formats, size limits) through request DTOs validated by Symfony Validator. Converts raw UUIDs to identifier value objects (§5). | Apply business rules, round or drop input, or build context-dependent value objects. |
| **Domain** | Invariants that depend only on the value itself (and parameters passed in). Value objects validate in factory methods and accept only canonical input. | Read configuration, clock, or repositories. |
| **Application** | Rules that need context: configuration limits, clock, family time zone, repositories, membership, existence and family ownership of referenced entities. | Trust earlier validation: handlers always revalidate. |
| **Infrastructure** | Database constraints stay authoritative (§3). A constraint violation from a race is translated into a typed domain exception. | Be the primary source of user-facing validation. |

### Rules

- **Shared validators.** When a Telegram step needs early feedback before any command exists, it calls an Application validator (`src/{Context}/Application/Validation/`). The same validator is used by the command handler. Validators are plain services, not commands or queries.
- **Value objects in Presentation.** Presentation may create a value object directly only if it needs no configuration, clock, or repository (e.g. `FamilyName`, `SearchQuery`). Everything else goes through an Application validator. This refines §5.
- **Single values throw.** A validator for one value returns the value object or throws a typed exception with an `ErrorType` and structured properties (§6). Do not return result objects for single values.
- **Several problems return a result.** A check that can find multiple problems (e.g. draft revalidation) returns a result object with a list of backed-enum problems. It never throws for them.
- **Error mapping.** Presentation maps exceptions and problems to a `TranslatableMessage` through the centralized mapper (`MapExceptionToTranslatableMessage`). The mapper returns a `Symfony\Component\Translation\TranslatableMessage` that holds the translation key and interpolation parameters (e.g., `%{maxAmount}`, `%{precision}`). Callers extract the key via `getMessage()` and parameters via `getParameters()`, then pass them to `TranslatorInterface::trans()`. The flow orchestrator catches typed exceptions in one place. Presentation alone chooses how to show the error (card error line, toast, or return to a step). Unmapped exceptions are logged and shown as a generic error.
- **Output escaping.** Escape user text when rendering, not when validating. Keep the typed value on a validation error.
- **Symfony Validator.** Allowed in Presentation only, for transport payload shape and syntax (e.g. API request DTOs). Business and user-facing rules live in value objects and Application validators; do not duplicate them as constraints.

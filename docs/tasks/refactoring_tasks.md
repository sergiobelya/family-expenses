# Refactoring Tasks

### Done

- **Validation in Domain** — Move input validation from Presentation handlers into Value Objects (`FamilyName`, `CatalogName`) and use typed domain exceptions (`InvalidValueException` with `ErrorType` enum) mapped to translation keys in the Presentation layer.

- **Callback data constants** — Extract hardcoded Telegram callback data strings into `CallbackActions` class with `const string` constants, grouped by prefix (nav, family, settings, catalog). Replaced all hardcoded strings across 12 files (handlers, router, renderer) with constants for single-source-of-truth, IDE autocomplete, and easier search/replacement.

- **FamilyCreationWizardHandler decomposition** — Split the ~350-line handler into 5 separate step classes (`FamilyNameStep`, `FamilyTimezoneStep`, `FamilyCurrencyStep`, `FamilyProviderStep`, `FamilyConfirmStep`) with a `FamilyCreationStepInterface`. The handler now acts as an orchestrator that delegates to the appropriate step and manages state transitions via `UserInteractionStateRepository`. Each step is `final`, independent, and focused on one wizard phase.

- **CatalogFlowHandler decomposition** — Split the ~1200-line handler into screen classes with shared `CatalogFlowUtils`. The handler now acts as a thin orchestrator (~380 lines). Screens: `CatalogListScreen`, `CatalogSearchScreen` (in ListScreen), `CatalogDetailsScreen`, `CatalogNameInputScreen`, `StoreAliasScreen`, `StoreDefaultCategoryScreen`, `CatalogDeleteScreen`.

- **CatalogName logic decomposition** — Split `CatalogName::fromString()` (85 lines) into 4 private methods: `trimAndValidate()` (validation), `normalizeUnicode()` (NFKC/NFD normalization, transliteration, strtr mapping), `filterSpecialChars()` (character-by-character processing of marks, punctuation, special symbols), and `buildDedupeKey()` (final NFC normalization and validation). The `fromString()` method is now a short orchestrator (~4 lines).

- **FamilySettingsFlowHandler decomposition** — Split the ~420-line handler into 4 separate step classes (`FamilySettingsNameStep`, `FamilySettingsTimezoneStep`, `FamilySettingsCurrencyStep`, `FamilySettingsProviderStep`) with a `FamilySettingsStepInterface`. The handler now acts as a thin orchestrator (~220 lines) that delegates to the appropriate step. `renderFamilySettings()` remains as a shared method in the orchestrator. `FamilySettingsTimezoneStep::handle()` further decomposed into `showSearchPrompt()`, `showTimezoneList()`, and `setTimezone()` private methods. `FamilySettingsCurrencyStep` deduplicated via `setCurrency()` with nullable parameters. Timezone flow name constant shared via `FamilySettingsTimezoneStep::FLOW_TIMEZONE_SELECTOR`.

- **FamilyInvitationsFlowHandler decomposition** — Split the ~220-line handler into 3 separate step classes (`CreateInvitationStep`, `ViewInvitationStep`, `RevokeInvitationStep`) with a `FamilyInvitationsStepInterface` (return type `void`). The handler now acts as a thin orchestrator (~100 lines). Shared logic extracted into DI-injected classes: `ListInvitationScreen` (renders invitation list) and `GetInvitationStatusLabel` (invoke object for status determination). Step classes that need shared logic depend on these classes directly. `FAMILY_INVITATIONS_LIST` callback calls `ListInvitationScreen` directly without a step wrapper.

- **Add `final readonly` to all applicable classes** — Per the rule in `src/Agents.md` §1, most classes are now `final readonly` to prevent unintended inheritance and mutation. Added `final readonly` to ~50 classes across Application, Infrastructure, and Presentation layers. Exceptions: domain entities (Doctrine hydration via reflection), abstract base classes, classes that must be mocked in tests, classes with mutable properties (e.g., `InlineKeyboardBuilder`, `OutboxPublisher`), Doctrine Type classes, Exception classes, and Command DTOs.

### Pending

- **API collecting validator** — When REST API delivery needs all field errors at once (API forms), implement a collecting validator that returns a result with a list of problems, built on the same value object rules and Application validators. Each delivery mechanism maps errors itself: Telegram to translation keys, API to stable error codes (`ErrorType`). `ErrorType` is the shared contract; translation keys are not part of the domain.

- **Thread-local locale** — `TranslatorInterface::setLocale()` is called on every request in `TelegramUserContextResolver`. In multi-threaded environments (e.g., RoadRunner) this can cause race conditions. Use `LocaleAwareInterface` with thread-local storage or pass locale explicitly instead of mutating a shared translator instance.


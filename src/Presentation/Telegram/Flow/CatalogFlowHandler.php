<?php

namespace App\Presentation\Telegram\Flow;

use App\Catalog\Application\CatalogAccess;
use App\Core\Infrastructure\Persistence\Redis\UserInteractionState;
use App\Core\Infrastructure\Persistence\Redis\UserInteractionStateRepository;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Flow\Catalog\CatalogEntryScreen;
use App\Presentation\Telegram\Flow\Catalog\CatalogFlowUtils;
use App\Presentation\Telegram\Flow\Catalog\StoreAliasScreen;
use App\Presentation\Telegram\Flow\Catalog\StoreDefaultCategoryScreen;
use Symfony\Component\Uid\Uuid;

final readonly class CatalogFlowHandler implements FamilyContextFlowHandlerInterface
{
    private const string FLOW_NAME = CatalogFlowUtils::FLOW_NAME;

    public function __construct(
        private CatalogFlowUtils $utils,
        private CatalogEntryScreen $storeScreen,
        private CatalogEntryScreen $categoryScreen,
        private StoreAliasScreen $aliasScreen,
        private StoreDefaultCategoryScreen $defaultCategoryScreen,
        private CatalogAccess $access,
        private UserInteractionStateRepository $stateRepository,
        private TelegramApiClientInterface $telegramClient
    ) {
    }

    public function supports(TelegramUpdate $update, TelegramUserContext $context): bool
    {
        if (str_starts_with($update->callbackData ?? '', CallbackActions::CATALOG_PREFIX)) {
            return true;
        }

        if ($update->text === $this->trans('main_menu.stores')
            || $update->text === $this->trans('main_menu.categories')) {
            return true;
        }

        $state = $this->stateRepository->get($context->telegramUserId);

        return $state?->flow === self::FLOW_NAME
            && $update->text !== null
            && !str_starts_with($update->text, '/');
    }

    public function handle(TelegramUpdate $update, TelegramFamilyMemberContext $context): void
    {
        if ($update->isCallbackQuery()) {
            $this->telegramClient->answerCallbackQuery($update->callbackQueryId);
        }

        $family = $context->activeFamily;
        $familyId = $family->getId()->toUuid();
        $this->access->assertMember($family->getId(), $context->user->getId());

        if ($update->text === $this->trans('main_menu.stores')) {
            $this->storeScreen->renderList($context, 0);
            return;
        }
        if ($update->text === $this->trans('main_menu.categories')) {
            $this->categoryScreen->renderList($context, 0);
            return;
        }
        if ($update->text !== null && !str_starts_with($update->text, '/')) {
            $state = $this->stateRepository->get($context->telegramUserId);
            if ($state?->flow === self::FLOW_NAME) {
                $this->handleTextInput($update, $context, $state);
            }
            return;
        }

        $data = $update->callbackData ?? '';

        // Default category picker: search, clear, pagination, select
        if (preg_match('/^catalog:def-cat:(search|clear|page:(\d+)|set:(none|[0-9a-f-]{36}))$/i', $data, $matches) === 1) {
            $state = $this->stateRepository->get($context->telegramUserId);
            $storeId = $this->stateUuid($state, 'store_id');
            if ($storeId === null) {
                return;
            }

            if ($matches[1] === 'search') {
                $this->stateRepository->set($this->state($context, 'default_category_search', [
                    'store_id' => (string) $storeId,
                    'query' => (string) ($state?->data['query'] ?? ''),
                ]));
                $this->utils->render(
                    $context,
                    $this->trans('selector.search_prompt'),
                    $this->formKeyboard(CallbackActions::CATALOG_DEFAULT_CATEGORY_OPEN_PREFIX . $storeId)
                );
                return;
            }
            if ($matches[1] === 'clear') {
                $this->defaultCategoryScreen->renderDefaultCategoryPicker($context, $storeId);
                return;
            }
            if (str_starts_with($matches[1], 'page:')) {
                $this->defaultCategoryScreen->renderDefaultCategoryPicker(
                    $context,
                    $storeId,
                    (int) ($matches[2] ?? 0),
                    (string) ($state?->data['query'] ?? '')
                );
                return;
            }

            $selectedCategory = $matches[3] ?? '';
            $categoryId = $selectedCategory === 'none' ? null : $this->parseUuid($selectedCategory);
            if ($selectedCategory === 'none' || $categoryId !== null) {
                $this->defaultCategoryScreen->setDefaultCategory($context, $familyId, $storeId, $categoryId);
            }
            return;
        }
        // Show store or category list from scratch
        if (preg_match('/^catalog:list:(store|category)$/', $data, $matches) === 1) {
            $this->getScreen($matches[1])->renderList($context, 0);
            return;
        }
        // Paginate store or category list
        if (preg_match('/^catalog:page:(store|category):(\d+)$/', $data, $matches) === 1) {
            $this->getScreen($matches[1])->renderList($context, (int) $matches[2]);
            return;
        }
        // Open store or category details
        if (preg_match('/^catalog:select:(store|category):([0-9a-f-]{36})$/i', $data, $matches) === 1) {
            $id = $this->parseUuid($matches[2]);
            if ($id !== null) {
                $this->getScreen($matches[1])->renderDetails($context, $id);
            }
            return;
        }
        // Start creating a new store or category
        if (preg_match('/^catalog:add:(store|category)$/', $data, $matches) === 1) {
            $this->getScreen($matches[1])->startNameInput($context, 'create');
            return;
        }
        // Start searching stores or categories
        if (preg_match('/^catalog:search:(store|category)$/', $data, $matches) === 1) {
            $this->getScreen($matches[1])->startSearch($context);
            return;
        }
        // Start renaming a store or category
        if (preg_match('/^catalog:rename:(store|category):([0-9a-f-]{36})$/i', $data, $matches) === 1) {
            $id = $this->parseUuid($matches[2]);
            if ($id !== null) {
                $this->getScreen($matches[1])->startNameInput($context, 'rename', $id);
            }
            return;
        }
        // Open default category picker for a store
        if (preg_match('/^catalog:def-cat:open:([0-9a-f-]{36})$/i', $data, $matches) === 1) {
            $storeId = $this->parseUuid($matches[1]);
            if ($storeId !== null) {
                $this->defaultCategoryScreen->renderDefaultCategoryPicker($context, $storeId);
            }
            return;
        }
        // Set a category as default for a store (from store details)
        if (preg_match('/^catalog:def-cat:set:([0-9a-f-]{36})$/i', $data, $matches) === 1) {
            $categoryId = $this->parseUuid($matches[1]);
            if ($categoryId !== null) {
                $state = $this->stateRepository->get($context->telegramUserId);
                $storeId = $this->stateUuid($state, 'store_id');
                if ($storeId !== null) {
                    $this->defaultCategoryScreen->setDefaultCategory($context, $familyId, $storeId, $categoryId);
                }
            }
            return;
        }
        // Remove default category from a store
        if ($data === CallbackActions::CATALOG_DEFAULT_CATEGORY_SET_NONE) {
            $state = $this->stateRepository->get($context->telegramUserId);
            $storeId = $this->stateUuid($state, 'store_id');
            if ($storeId !== null) {
                $this->defaultCategoryScreen->setDefaultCategory($context, $familyId, $storeId, null);
            }
            return;
        }
        // Open store aliases list
        if (preg_match('/^catalog:aliases:([0-9a-f-]{36})$/i', $data, $matches) === 1) {
            $storeId = $this->parseUuid($matches[1]);
            if ($storeId !== null) {
                $this->aliasScreen->renderAliases($context, $storeId);
            }
            return;
        }
        // Start adding a new alias to a store
        if (preg_match('/^catalog:alias:add:([0-9a-f-]{36})$/i', $data, $matches) === 1) {
            $storeId = $this->parseUuid($matches[1]);
            if ($storeId !== null) {
                $this->aliasScreen->startAliasInput($context, $storeId);
            }
            return;
        }
        // Open alias details from the aliases list
        if (preg_match('/^catalog:alias:select:([0-9a-f-]{36})$/i', $data, $matches) === 1) {
            $aliasId = $this->parseUuid($matches[1]);
            $state = $this->stateRepository->get($context->telegramUserId);
            $storeId = $this->stateUuid($state, 'store_id');
            if ($aliasId !== null && $storeId !== null) {
                $this->aliasScreen->selectAlias($context, $storeId, $aliasId);
            }
            return;
        }
        // Start renaming an alias — prefill the current name
        if (preg_match('/^catalog:alias:rename:([0-9a-f-]{36})$/i', $data, $matches) === 1) {
            $aliasId = $this->parseUuid($matches[1]);
            $state = $this->stateRepository->get($context->telegramUserId);
            $storeId = $this->stateUuid($state, 'store_id');
            if ($aliasId !== null && $storeId !== null) {
                $this->aliasScreen->startRenameAlias($context, $storeId, $aliasId);
            }
            return;
        }
        // Remove an alias from a store
        if (preg_match('/^catalog:alias:remove:([0-9a-f-]{36})$/i', $data, $matches) === 1) {
            $aliasId = $this->parseUuid($matches[1]);
            $state = $this->stateRepository->get($context->telegramUserId);
            $storeId = $this->stateUuid($state, 'store_id');
            if ($aliasId !== null && $storeId !== null) {
                $this->aliasScreen->renderAliases($context, $storeId);
            }
            return;
        }
        // Show delete confirmation for a store or category
        if (preg_match('/^catalog:confirm:(store|category):([0-9a-f-]{36})$/i', $data, $matches) === 1) {
            $id = $this->utils->parseUuid($matches[2]);
            if ($id !== null) {
                $this->getScreen($matches[1])->renderDeleteConfirmation($context, $id);
            }
            return;
        }
        // Delete a store or category
        if (preg_match('/^catalog:delete:(store|category):([0-9a-f-]{36})$/i', $data, $matches) === 1) {
            $id = $this->utils->parseUuid($matches[2]);
            if ($id !== null) {
                $this->getScreen($matches[1])->deleteEntry($context, $id);
            }
            return;
        }
        // Confirm name despite fuzzy conflict warning
        if ($data === CallbackActions::CATALOG_WARNING_CONFIRM) {
            $state = $this->stateRepository->get($context->telegramUserId);
            if ($state !== null && $state->flow === self::FLOW_NAME) {
                $type = (string) ($state->data['type'] ?? 'store');
                $this->getScreen($type)->processNameInput($context, $state->data, (string) ($state->data['value'] ?? ''));
            }
            return;
        }
        // Search for existing name before creating (fuzzy match)
        if ($data === CallbackActions::CATALOG_CREATE_SEARCH) {
            $state = $this->stateRepository->get($context->telegramUserId);
            if ($state !== null && $state->flow === self::FLOW_NAME) {
                $type = (string) ($state->data['type'] ?? 'store');
                $this->getScreen($type)->startNameInput(
                    $context,
                    'create',
                    null,
                    null,
                    (string) ($state->data['value'] ?? '')
                );
            }
            return;
        }
        // Edit name after seeing a conflict warning
        if ($data === CallbackActions::CATALOG_WARNING_EDIT) {
            $state = $this->stateRepository->get($context->telegramUserId);
            if ($state !== null && $state->flow === self::FLOW_NAME) {
                $type = (string) ($state->data['type'] ?? 'store');
                $this->stateRepository->set($this->state($context, $type . '_name', $state->data));
                $this->render(
                    $context,
                    $this->utils->namePrompt((string) ($state->data['value'] ?? '')),
                    $this->formKeyboard($this->getScreen($type)->getListCallback())
                );
            }
            return;
        }
        // Edit alias after seeing a conflict warning
        if ($data === CallbackActions::CATALOG_ALIAS_CONFLICT_EDIT) {
            $state = $this->stateRepository->get($context->telegramUserId);
            $storeId = $this->stateUuid($state, 'store_id');
            if ($state !== null && $state->flow === self::FLOW_NAME && $storeId !== null) {
                $this->stateRepository->set($this->state($context, 'alias_name', $state->data));
                $this->render(
                    $context,
                    $this->trans('catalog.prompt_alias_current', [
                        '%{name}' => $this->escape((string) ($state->data['value'] ?? '')),
                    ]),
                    $this->formKeyboard(CallbackActions::CATALOG_ALIASES_PREFIX . $storeId)
                );
            } elseif ($storeId !== null) {
                $this->aliasScreen->renderAliases($context, $storeId, 'catalog.unavailable');
            }
            return;
        }
        // Cancel current catalog operation and return to list
        if ($data === CallbackActions::CATALOG_CANCEL) {
            $state = $this->stateRepository->get($context->telegramUserId);
            $step = $state?->step;
            if ($step === 'alias_name' || $step === 'alias_conflict') {
                $storeId = $this->stateUuid($state, 'store_id');
                if ($storeId !== null) {
                    $this->aliasScreen->renderAliases($context, $storeId);
                }
            } else {
                $type = (string) ($state?->data['type'] ?? 'store');
                $this->getScreen($type)->renderList($context, 0);
            }
            return;
        }
        // Cancel after seeing a conflict warning
        if ($data === CallbackActions::CATALOG_WARNING_CANCEL) {
            $state = $this->stateRepository->get($context->telegramUserId);
            $type = (string) ($state?->data['type'] ?? 'store');
            $id = isset($state?->data['id']) ? $this->utils->parseUuid((string) $state->data['id']) : null;
            if (($state?->data['operation'] ?? null) === 'rename' && $id !== null) {
                $this->getScreen($type)->renderDetails($context, $id);
            } else {
                $this->getScreen($type)->renderList($context, 0);
            }
            return;
        }
        // Open store details from a conflict warning screen
        if (preg_match('/^catalog:warning:open-store:([0-9a-f-]{36})$/i', $data, $matches) === 1) {
            $id = $this->parseUuid($matches[1]);
            if ($id !== null) {
                $this->storeScreen->renderDetails($context, $id);
            }
            return;
        }
        // Open category details from a conflict warning screen
        if (preg_match('/^catalog:warning:open-category:([0-9a-f-]{36})$/i', $data, $matches) === 1) {
            $id = $this->parseUuid($matches[1]);
            if ($id !== null) {
                $this->categoryScreen->renderDetails($context, $id);
            }
            return;
        }
        // Create a new category from default category picker context
        if (preg_match('/^catalog:def-cat:new:([0-9a-f-]{36})$/i', $data, $matches) === 1) {
            $storeId = $this->parseUuid($matches[1]);
            if ($storeId !== null) {
                $this->categoryScreen->startNameInput($context, 'create', null, (string) $storeId);
            }
            return;
        }
        // Return to store after creating a category as default
        if (preg_match('/^catalog:return:([0-9a-f-]{36})$/i', $data, $matches) === 1) {
            $categoryId = $this->parseUuid($matches[1]);
            $state = $this->stateRepository->get($context->telegramUserId);
            $storeId = $this->stateUuid($state, 'store_id');
            if ($categoryId !== null && $storeId !== null) {
                $this->defaultCategoryScreen->setDefaultCategory($context, $familyId, $storeId, $categoryId);
            }
        }
    }

    private function handleTextInput(
        TelegramUpdate $update,
        TelegramFamilyMemberContext $context,
        UserInteractionState $state
    ): void {
        $text = trim($update->text ?? '');
        if ($text === '') {
            return;
        }

        if ($state->step === 'search') {
            $type = (string) ($state->data['type'] ?? 'store');
            $screen = $this->getScreen($type);
            $screen->searchAndRender($context, $text);
            return;
        }
        if ($state->step === 'default_category_search') {
            $storeId = $this->stateUuid($state, 'store_id');
            if ($storeId !== null) {
                $this->defaultCategoryScreen->renderDefaultCategoryPicker($context, $storeId, 0, $text);
            }
            return;
        }
        if (in_array($state->step, ['store_name', 'category_name'], true)) {
            $type = (string) ($state->data['type'] ?? 'store');
            $this->getScreen($type)->processNameInput($context, $state->data, $text);
            return;
        }
        if ($state->step === 'alias_name') {
            $this->aliasScreen->processAliasInput($context, $state->data, $text);
        }
    }

    /**
     * @param array<string, mixed> $markup
     */
    private function render(
        TelegramFamilyMemberContext $context,
        string $textOrKey,
        array $markup,
        ?UserInteractionState $previousState = null
    ): void {
        $this->utils->render($context, $textOrKey, $markup, $previousState);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function state(TelegramFamilyMemberContext $context, string $step, array $data): UserInteractionState
    {
        return $this->utils->state($context, $step, $data);
    }

    /** @return array<string, mixed> */
    private function formKeyboard(string $callback): array
    {
        return $this->utils->formKeyboard($callback);
    }

    private function stateUuid(?UserInteractionState $state, string $key): ?Uuid
    {
        return isset($state?->data[$key]) ? $this->parseUuid((string) $state->data[$key]) : null;
    }

    private function parseUuid(string $value): ?Uuid
    {
        return $this->utils->parseUuid($value);
    }

    private function escape(string $value): string
    {
        return $this->utils->escape($value);
    }

    /**
     * @param array<string|int, mixed> $parameters
     */
    private function trans(string $key, array $parameters = []): string
    {
        return $this->utils->trans($key, $parameters);
    }

    private function getScreen(string $type): CatalogEntryScreen
    {
        return $type === 'store' ? $this->storeScreen : $this->categoryScreen;
    }
}

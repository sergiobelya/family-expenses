<?php

namespace App\Presentation\Telegram\Flow;

use App\Catalog\Application\Command\AddStoreAliasCommand;
use App\Catalog\Application\Command\CreateCategoryCommand;
use App\Catalog\Application\Command\CreateStoreCommand;
use App\Catalog\Application\Command\DeleteCategoryCommand;
use App\Catalog\Application\Command\DeleteStoreCommand;
use App\Catalog\Application\Command\RenameCategoryCommand;
use App\Catalog\Application\Command\RenameStoreAliasCommand;
use App\Catalog\Application\Command\RenameStoreCommand;
use App\Catalog\Application\Command\RemoveStoreAliasCommand;
use App\Catalog\Application\Command\SetStoreDefaultCategoryCommand;
use App\Catalog\Application\CatalogAccess;
use App\Catalog\Application\Query\CatalogSearchInterface;
use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Entity\Store;
use App\Catalog\Domain\Exception\CatalogEntryNotFoundException;
use App\Catalog\Domain\Exception\CatalogEntryReferencedException;
use App\Catalog\Domain\Exception\CatalogNameConflictException;
use App\Catalog\Domain\Repository\CatalogRepositoryInterface;
use App\Catalog\Domain\ValueObject\CatalogName;
use App\Core\Domain\ValueObject\CategoryId;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\StoreAliasId;
use App\Core\Domain\ValueObject\StoreId;
use App\Core\Infrastructure\Persistence\Redis\UserInteractionState;
use App\Core\Infrastructure\Persistence\Redis\UserInteractionStateRepository;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Keyboard\InlineKeyboardBuilder;
use App\Presentation\Telegram\Keyboard\InlineOptionSelector;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

final class CatalogFlowHandler implements FamilyContextFlowHandlerInterface
{
    private const string FLOW_NAME = 'catalog';
    private const int PAGE_SIZE = 8;

    public function __construct(
        private TelegramApiClientInterface $telegramClient,
        private UserInteractionStateRepository $stateRepository,
        private CatalogRepositoryInterface $catalog,
        private CatalogAccess $access,
        private CatalogSearchInterface $search,
        private InlineOptionSelector $optionSelector,
        private MessageBusInterface $commandBus,
        private TranslatorInterface $translator
    ) {
    }

    public function supports(TelegramUpdate $update, TelegramUserContext $context): bool
    {
        if (str_starts_with($update->callbackData ?? '', 'catalog:')
            || str_starts_with($update->callbackData ?? '', 'catdef:')) {
            return true;
        }

        if ($update->text === $this->translator->trans('main_menu.stores')
            || $update->text === $this->translator->trans('main_menu.categories')) {
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

        if ($update->text === $this->translator->trans('main_menu.stores')) {
            $this->renderList($context, 'store', 0);
            return;
        }
        if ($update->text === $this->translator->trans('main_menu.categories')) {
            $this->renderList($context, 'category', 0);
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
        if (preg_match('/^catdef:(search|clear|page:(\d+)|set:(none|[0-9a-f-]{36}))$/i', $data, $matches) === 1) {
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
                $this->render(
                    $context,
                    $this->trans('selector.search_prompt'),
                    $this->formKeyboard('catalog:default:' . $storeId)
                );
                return;
            }
            if ($matches[1] === 'clear') {
                $this->renderDefaultCategoryPicker($context, $storeId);
                return;
            }
            if (str_starts_with($matches[1], 'page:')) {
                $this->renderDefaultCategoryPicker(
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
                $this->setDefaultCategory($context, $familyId, $storeId, $categoryId);
            }
            return;
        }
        if (preg_match('/^catalog:list:(store|category)$/', $data, $matches) === 1) {
            $this->renderList($context, $matches[1], 0);
            return;
        }
        if (preg_match('/^catalog:page:(store|category):(\d+)$/', $data, $matches) === 1) {
            $this->renderList($context, $matches[1], (int) $matches[2]);
            return;
        }
        if (preg_match('/^catalog:select:(store|category):([0-9a-f-]{36})$/i', $data, $matches) === 1) {
            $id = $this->parseUuid($matches[2]);
            if ($id !== null) {
                $this->renderDetails($context, $matches[1], $id);
            }
            return;
        }
        if (preg_match('/^catalog:add:(store|category)$/', $data, $matches) === 1) {
            $this->startNameInput($context, $matches[1], 'create');
            return;
        }
        if (preg_match('/^catalog:search:(store|category)$/', $data, $matches) === 1) {
            $this->startSearch($context, $matches[1]);
            return;
        }
        if (preg_match('/^catalog:rename:(store|category):([0-9a-f-]{36})$/i', $data, $matches) === 1) {
            $id = $this->parseUuid($matches[2]);
            if ($id !== null) {
                $this->startNameInput($context, $matches[1], 'rename', $id);
            }
            return;
        }
        if (preg_match('/^catalog:default:([0-9a-f-]{36})$/i', $data, $matches) === 1) {
            $storeId = $this->parseUuid($matches[1]);
            if ($storeId !== null) {
                $this->renderDefaultCategoryPicker($context, $storeId);
            }
            return;
        }
        if (preg_match('/^catalog:set-default:([0-9a-f-]{36})$/i', $data, $matches) === 1) {
            $categoryId = $this->parseUuid($matches[1]);
            if ($categoryId !== null) {
                $state = $this->stateRepository->get($context->telegramUserId);
                $storeId = $this->stateUuid($state, 'store_id');
                if ($storeId !== null) {
                    $this->setDefaultCategory($context, $familyId, $storeId, $categoryId);
                }
            }
            return;
        }
        if ($data === 'catalog:set-default:none') {
            $state = $this->stateRepository->get($context->telegramUserId);
            $storeId = $this->stateUuid($state, 'store_id');
            if ($storeId !== null) {
                $this->setDefaultCategory($context, $familyId, $storeId, null);
            }
            return;
        }
        if (preg_match('/^catalog:aliases:([0-9a-f-]{36})$/i', $data, $matches) === 1) {
            $storeId = $this->parseUuid($matches[1]);
            if ($storeId !== null) {
                $this->renderAliases($context, $storeId);
            }
            return;
        }
        if (preg_match('/^catalog:alias:add:([0-9a-f-]{36})$/i', $data, $matches) === 1) {
            $storeId = $this->parseUuid($matches[1]);
            if ($storeId !== null) {
                $this->startAliasInput($context, $storeId);
            }
            return;
        }
        if (preg_match('/^catalog:alias:select:([0-9a-f-]{36})$/i', $data, $matches) === 1) {
            $aliasId = $this->parseUuid($matches[1]);
            $state = $this->stateRepository->get($context->telegramUserId);
            $storeId = $this->stateUuid($state, 'store_id');
            if ($aliasId !== null && $storeId !== null) {
                $alias = $this->catalog->findStoreAlias(
                    new FamilyId($familyId),
                    new StoreId($storeId),
                    new StoreAliasId($aliasId)
                );
                if ($alias !== null) {
                    $this->renderAliasDetails($context, $storeId, $aliasId, $alias->getAlias());
                } else {
                    $this->renderAliases($context, $storeId, 'catalog.unavailable');
                }
            }
            return;
        }
        if (preg_match('/^catalog:alias:rename:([0-9a-f-]{36})$/i', $data, $matches) === 1) {
            $aliasId = $this->parseUuid($matches[1]);
            $state = $this->stateRepository->get($context->telegramUserId);
            $storeId = $this->stateUuid($state, 'store_id');
            $alias = $aliasId !== null && $storeId !== null
                ? $this->catalog->findStoreAlias(
                    new FamilyId($familyId),
                    new StoreId($storeId),
                    new StoreAliasId($aliasId)
                )
                : null;
            if ($alias !== null) {
                $this->stateRepository->set($this->state($context, 'alias_name', [
                    'store_id' => (string) $storeId,
                    'alias_id' => (string) $aliasId,
                    'value' => $alias->getAlias(),
                ]));
                $this->render(
                    $context,
                    $this->trans('catalog.prompt_alias_current', [
                        '%{name}' => $this->escape($alias->getAlias()),
                    ]),
                    $this->formKeyboard('catalog:aliases:' . $storeId)
                );
            } elseif ($storeId !== null) {
                $this->renderAliases($context, $storeId, 'catalog.unavailable');
            }
            return;
        }
        if (preg_match('/^catalog:alias:remove:([0-9a-f-]{36})$/i', $data, $matches) === 1) {
            $aliasId = $this->parseUuid($matches[1]);
            $state = $this->stateRepository->get($context->telegramUserId);
            $storeId = $this->stateUuid($state, 'store_id');
            if ($aliasId !== null && $storeId !== null) {
                try {
                    $this->runCommand(new RemoveStoreAliasCommand(
                        new FamilyId($familyId),
                        new StoreId($storeId),
                        new StoreAliasId($aliasId),
                        $context->user->getId()
                    ));
                    $this->renderAliases($context, $storeId, 'catalog.alias_removed');
                } catch (CatalogEntryNotFoundException) {
                    $this->renderAliases($context, $storeId, 'catalog.unavailable');
                }
            }
            return;
        }
        if (preg_match('/^catalog:confirm:(store|category):([0-9a-f-]{36})$/i', $data, $matches) === 1) {
            $id = $this->parseUuid($matches[2]);
            if ($id !== null) {
                $this->renderDeleteConfirmation($context, $matches[1], $id);
            }
            return;
        }
        if (preg_match('/^catalog:delete:(store|category):([0-9a-f-]{36})$/i', $data, $matches) === 1) {
            $id = $this->parseUuid($matches[2]);
            if ($id !== null) {
                $this->deleteEntry($context, $matches[1], $id);
            }
            return;
        }
        if ($data === 'catalog:warning:confirm') {
            $state = $this->stateRepository->get($context->telegramUserId);
            if ($state !== null && $state->flow === self::FLOW_NAME) {
                $this->saveNameOperation($context, $state->data);
            }
            return;
        }
        if ($data === 'catalog:create-search') {
            $state = $this->stateRepository->get($context->telegramUserId);
            if ($state !== null && $state->flow === self::FLOW_NAME) {
                $this->startNameInput(
                    $context,
                    (string) ($state->data['type'] ?? 'store'),
                    'create',
                    null,
                    null,
                    (string) ($state->data['value'] ?? '')
                );
            }
            return;
        }
        if ($data === 'catalog:warning:edit') {
            $state = $this->stateRepository->get($context->telegramUserId);
            if ($state !== null && $state->flow === self::FLOW_NAME) {
                $type = (string) ($state->data['type'] ?? 'store');
                $this->stateRepository->set($this->state($context, $type . '_name', $state->data));
                $this->render($context, $this->namePrompt((string) ($state->data['value'] ?? '')), $this->formKeyboard($this->listCallback($type)));
            }
            return;
        }
        if ($data === 'catalog:alias:conflict:edit') {
            $state = $this->stateRepository->get($context->telegramUserId);
            $storeId = $this->stateUuid($state, 'store_id');
            if ($state !== null && $state->flow === self::FLOW_NAME && $storeId !== null) {
                $this->stateRepository->set($this->state($context, 'alias_name', $state->data));
                $this->render(
                    $context,
                    $this->trans('catalog.prompt_alias_current', [
                        '%{name}' => $this->escape((string) ($state->data['value'] ?? '')),
                    ]),
                    $this->formKeyboard('catalog:aliases:' . $storeId)
                );
            } elseif ($storeId !== null) {
                $this->renderAliases($context, $storeId, 'catalog.unavailable');
            }
            return;
        }
        if ($data === 'catalog:cancel') {
            $state = $this->stateRepository->get($context->telegramUserId);
            $step = $state?->step;
            if ($step === 'alias_name' || $step === 'alias_conflict') {
                $storeId = $this->stateUuid($state, 'store_id');
                if ($storeId !== null) {
                    $this->renderAliases($context, $storeId);
                }
            } else {
                $type = (string) ($state?->data['type'] ?? 'store');
                $this->renderList($context, $type, 0);
            }
            return;
        }
        if ($data === 'catalog:warning:cancel') {
            $state = $this->stateRepository->get($context->telegramUserId);
            $type = (string) ($state?->data['type'] ?? 'store');
            $id = isset($state?->data['id']) ? $this->parseUuid((string) $state->data['id']) : null;
            if (($state?->data['operation'] ?? null) === 'rename' && $id !== null) {
                $this->renderDetails($context, $type, $id);
            } else {
                $this->renderList($context, $type, 0);
            }
            return;
        }
        if (preg_match('/^catalog:warning:open:([0-9a-f-]{36})$/i', $data, $matches) === 1) {
            $id = $this->parseUuid($matches[1]);
            if ($id !== null) {
                $this->renderDetails($context, 'store', $id);
            }
            return;
        }
        if (preg_match('/^catalog:warning:open-c:([0-9a-f-]{36})$/i', $data, $matches) === 1) {
            $id = $this->parseUuid($matches[1]);
            if ($id !== null) {
                $this->renderDetails($context, 'category', $id);
            }
            return;
        }
        if (preg_match('/^catalog:def:new:([0-9a-f-]{36})$/i', $data, $matches) === 1) {
            $storeId = $this->parseUuid($matches[1]);
            if ($storeId !== null) {
                $this->startNameInput($context, 'category', 'create', null, (string) $storeId);
            }
            return;
        }
        if (preg_match('/^catalog:return:([0-9a-f-]{36})$/i', $data, $matches) === 1) {
            $categoryId = $this->parseUuid($matches[1]);
            $state = $this->stateRepository->get($context->telegramUserId);
            $storeId = $this->stateUuid($state, 'store_id');
            if ($categoryId !== null && $storeId !== null) {
                try {
                    $this->runCommand(new SetStoreDefaultCategoryCommand(
                        new FamilyId($familyId),
                        new StoreId($storeId),
                        new CategoryId($categoryId),
                        $context->user->getId()
                    ));
                    $this->renderDetails($context, 'store', $storeId, 'catalog.saved');
                } catch (CatalogEntryNotFoundException) {
                    $this->renderDetails($context, 'store', $storeId, 'catalog.unavailable');
                }
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
            $results = $type === 'store'
                ? $this->search->stores($context->activeFamily->getId(), $text)
                : $this->search->categories($context->activeFamily->getId(), $text);
            $this->renderSearchResults($context, $type, $text, $results);
            return;
        }
        if ($state->step === 'default_category_search') {
            $storeId = $this->stateUuid($state, 'store_id');
            if ($storeId !== null) {
                $this->renderDefaultCategoryPicker($context, $storeId, 0, $text);
            }
            return;
        }
        if (in_array($state->step, ['store_name', 'category_name'], true)) {
            $this->processNameInput($context, $state->data, $text);
            return;
        }
        if ($state->step === 'alias_name') {
            $this->processAliasInput($context, $state->data, $text);
        }
    }

    private function renderList(
        TelegramFamilyMemberContext $context,
        string $type,
        int $page,
        ?string $notice = null
    ): void {
        $familyId = $context->activeFamily->getId();
        $state = $this->stateRepository->get($context->telegramUserId);
        $entries = $type === 'store'
            ? $this->catalog->findStores($familyId)
            : $this->catalog->findCategories($familyId);
        $start = $page * self::PAGE_SIZE;
        $entries = array_slice($entries, $start, self::PAGE_SIZE);
        $titleKey = $type === 'store' ? 'catalog.stores_title' : 'catalog.categories_title';
        $text = '<b>' . $this->trans($titleKey) . '</b> · 👪 <b>'
            . $this->escape((string) $context->activeFamily->getName()) . '</b>';
        if ($notice !== null) {
            $text .= "\n\n" . $this->trans($notice);
        }
        if ($entries === []) {
            $text .= "\n\n" . $this->trans($type === 'store' ? 'catalog.stores_empty' : 'catalog.categories_empty');
        } else {
            $categories = [];
            if ($type === 'store') {
                foreach ($this->catalog->findCategories($familyId) as $category) {
                    $categories[(string) $category->getId()] = $category->getName();
                }
            }
            foreach ($entries as $entry) {
                $text .= "\n• " . $this->escape($entry->getName());
                if ($entry instanceof Store && $entry->getDefaultCategoryId() !== null) {
                    $categoryName = $categories[(string) $entry->getDefaultCategoryId()] ?? null;
                    if ($categoryName !== null) {
                        $text .= ' — ' . $this->escape($categoryName);
                    }
                }
            }
        }

        $builder = new InlineKeyboardBuilder();
        $builder->button(
            $this->trans('catalog.add'),
            'catalog:add:' . $type
        )->button($this->trans('catalog.search'), 'catalog:search:' . $type)->row();
        foreach (array_chunk($entries, 2) as $row) {
            foreach ($row as $entry) {
                $builder->button(
                    $this->escapeButton($entry->getName()),
                    'catalog:select:' . $type . ':' . $entry->getId()
                );
            }
            $builder->row();
        }
        if ($page > 0) {
            $builder->button($this->trans('selector.previous'), sprintf('catalog:page:%s:%d', $type, $page - 1));
        }
        $total = $type === 'store'
            ? count($this->catalog->findStores($familyId))
            : count($this->catalog->findCategories($familyId));
        if ($start + self::PAGE_SIZE < $total) {
            $builder->button($this->trans('selector.next'), sprintf('catalog:page:%s:%d', $type, $page + 1));
        }
        if ($page > 0 || $start + self::PAGE_SIZE < $total) {
            $builder->row();
        }
        $this->stateRepository->set($this->state($context, 'list', ['type' => $type, 'page' => $page]));
        $this->render($context, $text, $builder->toArray(), $state);
    }

    /**
     * @param Store[]|Category[] $results
     */
    private function renderSearchResults(
        TelegramFamilyMemberContext $context,
        string $type,
        string $query,
        array $results
    ): void {
        if ($results === []) {
            $this->stateRepository->set($this->state($context, $type . '_name', [
                'type' => $type,
                'operation' => 'create',
                'value' => $query,
            ]));
            $builder = new InlineKeyboardBuilder()
                ->button($this->trans('catalog.create_from_search', ['%{name}' => $this->escape($query)]), 'catalog:create-search')
                ->row()
                ->button($this->trans('common.back'), $this->listCallback($type));
            $this->render(
                $context,
                $this->trans('catalog.search_empty', ['%{query}' => $this->escape($query)]),
                $builder->toArray()
            );
            return;
        }

        $builder = new InlineKeyboardBuilder();
        foreach ($results as $entry) {
            $builder->button(
                $this->escapeButton($entry->getName()),
                'catalog:select:' . $type . ':' . $entry->getId()
            )->row();
        }
        $builder->button($this->trans('common.back'), $this->listCallback($type));
        $this->stateRepository->set($this->state($context, 'search_results', ['type' => $type]));
        $this->render(
            $context,
            $this->trans('catalog.search_results', ['%{query}' => $this->escape($query)]),
            $builder->toArray()
        );
    }

    private function renderDetails(
        TelegramFamilyMemberContext $context,
        string $type,
        Uuid $id,
        ?string $notice = null
    ): void {
        $familyId = $context->activeFamily->getId();
        if ($type === 'store') {
            $entry = $this->catalog->findStore($familyId, new StoreId($id));
            if ($entry === null) {
                $this->renderList($context, 'store', 0, 'catalog.unavailable');
                return;
            }
            $categories = [];
            foreach ($this->catalog->findCategories($familyId) as $category) {
                $categories[(string) $category->getId()] = $category->getName();
            }
            $default = $entry->getDefaultCategoryId() === null
                ? $this->trans('catalog.none')
                : $this->escape($categories[(string) $entry->getDefaultCategoryId()] ?? $this->trans('catalog.unavailable'));
            $aliases = array_map(
                fn ($alias): string => $this->escape($alias->getAlias()),
                $this->catalog->findStoreAliases($familyId, new StoreId($id))
            );
            $text = '<b>' . $this->escape($entry->getName()) . '</b>' . "\n"
                . $this->trans('catalog.default_category') . ': ' . $default . "\n"
                . $this->trans('catalog.aliases') . ': ' . ($aliases === [] ? $this->trans('catalog.none') : implode(', ', $aliases));
            $builder = new InlineKeyboardBuilder()
                ->button($this->trans('catalog.rename'), 'catalog:rename:store:' . $id)->row()
                ->button($this->trans('catalog.default_category'), 'catalog:default:' . $id)->row()
                ->button($this->trans('catalog.aliases'), 'catalog:aliases:' . $id)->row()
                ->button($this->trans('catalog.delete'), 'catalog:confirm:store:' . $id)->row()
                ->button($this->trans('common.back'), 'catalog:list:store');
            $this->stateRepository->set($this->state($context, 'store_details', ['store_id' => (string) $id]));
        } else {
            $entry = $this->catalog->findCategory($familyId, new CategoryId($id));
            if ($entry === null) {
                $this->renderList($context, 'category', 0, 'catalog.unavailable');
                return;
            }
            $text = '<b>' . $this->escape($entry->getName()) . '</b>';
            $builder = new InlineKeyboardBuilder()
                ->button($this->trans('catalog.rename'), 'catalog:rename:category:' . $id)->row()
                ->button($this->trans('catalog.delete'), 'catalog:confirm:category:' . $id)->row()
                ->button($this->trans('common.back'), 'catalog:list:category');
            $this->stateRepository->set($this->state($context, 'category_details', ['category_id' => (string) $id]));
        }
        if ($notice !== null) {
            $text .= "\n\n" . $this->trans($notice);
        }
        $this->render($context, $text, $builder->toArray());
    }

    private function renderDefaultCategoryPicker(
        TelegramFamilyMemberContext $context,
        Uuid $storeId,
        int $page = 0,
        string $query = ''
    ): void
    {
        $store = $this->catalog->findStore($context->activeFamily->getId(), new StoreId($storeId));
        if ($store === null) {
            $this->renderList($context, 'store', 0, 'catalog.unavailable');
            return;
        }
        $options = ['none' => $this->trans('catalog.no_default')];
        foreach ($this->catalog->findCategories($context->activeFamily->getId()) as $category) {
            $options[(string) $category->getId()] = $category->getName();
        }
        $query = trim($query);
        $filtered = $this->optionSelector->filterOptions($options, $query);
        $page = $this->optionSelector->normalizePage($options, $query, $page);
        $selectedValue = $store->getDefaultCategoryId() === null
            ? 'none'
            : (string) $store->getDefaultCategoryId();
        $keyboard = $this->optionSelector->buildKeyboard(
            options: $options,
            callbackPrefix: 'catdef',
            selectedValue: $selectedValue,
            query: $query,
            page: $page,
            searchLabel: $this->trans('selector.search'),
            clearSearchLabel: $this->trans('selector.clear_search'),
            previousLabel: $this->trans('selector.previous'),
            nextLabel: $this->trans('selector.next'),
            backLabel: $this->trans('common.back'),
            backCallback: 'catalog:select:store:' . $storeId
        );
        array_splice(
            $keyboard['inline_keyboard'],
            -1,
            0,
            [[[
                'text' => $this->trans('catalog.create_category'),
                'callback_data' => 'catalog:def:new:' . $storeId,
            ]]]
        );
        $this->stateRepository->set($this->state($context, 'default_category', [
            'store_id' => (string) $storeId,
            'query' => $query,
            'page' => $page,
        ]));
        $text = $this->trans('catalog.default_picker', ['%{store}' => $this->escape($store->getName())]);
        if ($filtered === []) {
            $text .= "\n\n" . $this->trans('selector.no_results');
        }
        $this->render(
            $context,
            $text,
            $keyboard
        );
    }

    private function setDefaultCategory(
        TelegramFamilyMemberContext $context,
        Uuid $familyId,
        Uuid $storeId,
        ?Uuid $categoryId
    ): void {
        try {
            $this->runCommand(new SetStoreDefaultCategoryCommand(
                new FamilyId($familyId),
                new StoreId($storeId),
                $categoryId === null ? null : new CategoryId($categoryId),
                $context->user->getId()
            ));
            $this->renderDetails($context, 'store', $storeId, 'catalog.saved');
        } catch (CatalogEntryNotFoundException) {
            $this->renderDetails($context, 'store', $storeId, 'catalog.unavailable');
        }
    }

    private function renderAliases(
        TelegramFamilyMemberContext $context,
        Uuid $storeId,
        ?string $notice = null
    ): void {
        $store = $this->catalog->findStore($context->activeFamily->getId(), new StoreId($storeId));
        if ($store === null) {
            $this->renderList($context, 'store', 0, 'catalog.unavailable');
            return;
        }
        $aliases = $this->catalog->findStoreAliases($context->activeFamily->getId(), new StoreId($storeId));
        $text = $this->trans('catalog.aliases_title', ['%{store}' => $this->escape($store->getName())]);
        if ($notice !== null) {
            $text .= "\n\n" . $this->trans($notice);
        }
        if ($aliases === []) {
            $text .= "\n\n" . $this->trans('catalog.aliases_empty');
        }
        $builder = new InlineKeyboardBuilder()->button($this->trans('catalog.add_alias'), 'catalog:alias:add:' . $storeId)->row();
        foreach ($aliases as $alias) {
            $builder->button($this->escapeButton($alias->getAlias()), 'catalog:alias:select:' . $alias->getId())->row();
        }
        $builder->button($this->trans('common.back'), 'catalog:select:store:' . $storeId);
        $this->stateRepository->set($this->state($context, 'aliases', ['store_id' => (string) $storeId]));
        $this->render($context, $text, $builder->toArray());
    }

    private function renderAliasDetails(
        TelegramFamilyMemberContext $context,
        Uuid $storeId,
        Uuid $aliasId,
        string $name
    ): void {
        $this->stateRepository->set($this->state($context, 'alias_details', [
            'store_id' => (string) $storeId,
            'alias_id' => (string) $aliasId,
        ]));
        $builder = new InlineKeyboardBuilder()
            ->button($this->trans('catalog.rename'), 'catalog:alias:rename:' . $aliasId)->row()
            ->button($this->trans('catalog.remove_alias'), 'catalog:alias:remove:' . $aliasId)->row()
            ->button($this->trans('common.back'), 'catalog:aliases:' . $storeId);
        $this->render($context, '<b>' . $this->escape($name) . '</b>', $builder->toArray());
    }

    private function renderDeleteConfirmation(TelegramFamilyMemberContext $context, string $type, Uuid $id): void
    {
        $entry = $type === 'store'
            ? $this->catalog->findStore($context->activeFamily->getId(), new StoreId($id))
            : $this->catalog->findCategory($context->activeFamily->getId(), new CategoryId($id));
        if ($entry === null) {
            $this->renderList($context, $type, 0, 'catalog.unavailable');
            return;
        }
        $this->stateRepository->set($this->state($context, 'delete_confirmation', [
            'type' => $type,
            'id' => (string) $id,
        ]));
        $builder = new InlineKeyboardBuilder()
            ->button($this->trans('catalog.confirm_delete'), 'catalog:delete:' . $type . ':' . $id)->row()
            ->button($this->trans('catalog.keep'), $type === 'store' ? 'catalog:select:store:' . $id : 'catalog:select:category:' . $id);
        $this->render(
            $context,
            $this->trans('catalog.delete_confirmation', ['%{name}' => $this->escape($entry->getName())]),
            $builder->toArray()
        );
    }

    private function startNameInput(
        TelegramFamilyMemberContext $context,
        string $type,
        string $operation,
        ?Uuid $id = null,
        ?string $returnStoreId = null,
        ?string $prefill = null
    ): void {
        $value = $prefill ?? '';
        if ($id !== null) {
            $entry = $type === 'store'
                ? $this->catalog->findStore($context->activeFamily->getId(), new StoreId($id))
                : $this->catalog->findCategory($context->activeFamily->getId(), new CategoryId($id));
            if ($entry === null) {
                $this->renderList($context, $type, 0, 'catalog.unavailable');
                return;
            }
            $value = $entry->getName();
        }
        $data = [
            'type' => $type,
            'operation' => $operation,
            'value' => $value,
        ];
        if ($id !== null) {
            $data['id'] = (string) $id;
        }
        if ($returnStoreId !== null) {
            $data['return_store_id'] = $returnStoreId;
        }
        $this->stateRepository->set($this->state($context, $type . '_name', $data));
        $this->render($context, $this->namePrompt($value), $this->formKeyboard($this->listCallback($type)));
    }

    private function startSearch(TelegramFamilyMemberContext $context, string $type): void
    {
        $this->stateRepository->set($this->state($context, 'search', ['type' => $type]));
        $this->render($context, 'catalog.search_prompt', $this->formKeyboard($this->listCallback($type)));
    }

    private function startAliasInput(TelegramFamilyMemberContext $context, Uuid $storeId): void
    {
        if ($this->catalog->findStore($context->activeFamily->getId(), new StoreId($storeId)) === null) {
            $this->renderList($context, 'store', 0, 'catalog.unavailable');
            return;
        }
        $this->stateRepository->set($this->state($context, 'alias_name', [
            'store_id' => (string) $storeId,
            'operation' => 'create',
            'value' => '',
        ]));
        $this->render($context, 'catalog.prompt_alias', $this->formKeyboard('catalog:aliases:' . $storeId));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function processNameInput(TelegramFamilyMemberContext $context, array $data, string $value): void
    {
        $type = (string) ($data['type'] ?? 'store');
        try {
            $name = CatalogName::fromString($value);
        } catch (InvalidArgumentException) {
            $this->render(
                $context,
                'catalog.invalid_name',
                $this->backKeyboard($this->listCallback($type))
            );
            return;
        }

        $familyId = $context->activeFamily->getId();
        $id = isset($data['id']) ? $this->parseUuid((string) $data['id']) : null;
        if ($type === 'store') {
            $storeId = $id === null ? null : new StoreId($id);
            $conflict = $this->catalog->findStoreByDedupeKey($familyId, $name->getDedupeKey(), $storeId);
            if ($conflict !== null) {
                $this->renderHardConflict($context, $type, $data, $name->getValue(), $conflict->getName(), $conflict->getId()->toUuid());
                return;
            }
            $alias = $this->catalog->findStoreAliasByDedupeKey($familyId, $name->getDedupeKey());
            if ($alias !== null) {
                $this->renderHardConflict(
                    $context,
                    $type,
                    $data,
                    $name->getValue(),
                    $alias->getAlias(),
                    $alias->getStoreId()->toUuid()
                );
                return;
            }
            $matches = array_filter(
                $this->search->stores($familyId, $name->getValue()),
                fn (Store $store): bool => $store->getDedupeKey() !== $name->getDedupeKey()
                    && ($storeId === null || !$store->getId()->equals($storeId))
            );
        } else {
            $categoryId = $id === null ? null : new CategoryId($id);
            $conflict = $this->catalog->findCategoryByDedupeKey($familyId, $name->getDedupeKey(), $categoryId);
            if ($conflict !== null) {
                $this->renderHardConflict($context, $type, $data, $name->getValue(), $conflict->getName(), $conflict->getId()->toUuid());
                return;
            }
            $matches = array_filter(
                $this->search->categories($familyId, $name->getValue()),
                fn (Category $category): bool => $category->getDedupeKey() !== $name->getDedupeKey()
                    && ($categoryId === null || !$category->getId()->equals($categoryId))
            );
        }

        $data['value'] = $name->getValue();
        if ($matches !== []) {
            $this->stateRepository->set($this->state($context, 'warning', $data));
            $operation = ($data['operation'] ?? 'create') === 'rename' ? 'rename' : 'create';
            $builder = new InlineKeyboardBuilder()
                ->button(
                    $this->trans('catalog.confirm_' . $type . '_' . $operation),
                    'catalog:warning:confirm'
                )->row();
            foreach (array_slice($matches, 0, 5) as $match) {
                $builder->button(
                    $this->escapeButton($match->getName()),
                    $type === 'store'
                        ? 'catalog:warning:open:' . $match->getId()
                        : 'catalog:warning:open-c:' . $match->getId()
                )->row();
            }
            $builder->button($this->trans('catalog.edit_name'), 'catalog:warning:edit')->row()
                ->button($this->trans('common.cancel'), 'catalog:warning:cancel');
            $this->render(
                $context,
                $this->trans(
                    'catalog.similar_' . $type . '_' . $operation,
                    ['%{name}' => $this->escape($name->getValue())]
                ),
                $builder->toArray()
            );
            return;
        }
        $this->saveNameOperation($context, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderHardConflict(
        TelegramFamilyMemberContext $context,
        string $type,
        array $data,
        string $value,
        string $existingName,
        Uuid $existingStoreId
    ): void {
        $data['value'] = $value;
        $this->stateRepository->set($this->state($context, 'hard_conflict', $data));
        $builder = new InlineKeyboardBuilder()
            ->button(
                $this->trans('catalog.open_conflict'),
                $type === 'store'
                    ? 'catalog:warning:open:' . $existingStoreId
                    : 'catalog:warning:open-c:' . $existingStoreId
            )->row()
            ->button($this->trans('catalog.edit_name'), 'catalog:warning:edit')->row()
            ->button($this->trans('common.back'), $this->listCallback($type));
        $this->render(
            $context,
            $this->trans(
                'catalog.hard_conflict_' . $type,
                ['%{name}' => $this->escape($existingName)]
            ),
            $builder->toArray()
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function saveNameOperation(TelegramFamilyMemberContext $context, array $data): void
    {
        $familyId = $context->activeFamily->getId();
        $type = (string) ($data['type'] ?? 'store');
        $value = (string) ($data['value'] ?? '');
        $id = isset($data['id']) ? $this->parseUuid((string) $data['id']) : null;
        try {
            if ($type === 'store') {
                if (($data['operation'] ?? 'create') === 'rename' && $id !== null) {
                    $created = $this->runCommand(new RenameStoreCommand($familyId, new StoreId($id), $context->user->getId(), $value));
                } else {
                    $created = $this->runCommand(new CreateStoreCommand($familyId, $context->user->getId(), $value));
                }
            } elseif (($data['operation'] ?? 'create') === 'rename' && $id !== null) {
                $created = $this->runCommand(new RenameCategoryCommand($familyId, new CategoryId($id), $context->user->getId(), $value));
            } else {
                $created = $this->runCommand(new CreateCategoryCommand($familyId, $context->user->getId(), $value));
            }
        } catch (CatalogNameConflictException $exception) {
            $this->render(
                $context,
                $this->trans(
                    'catalog.hard_conflict_' . $type,
                    ['%{name}' => $this->escape($exception->conflictingName)]
                ),
                $this->backKeyboard($this->listCallback($type))
            );
            return;
        } catch (CatalogEntryNotFoundException) {
            $this->renderList($context, $type, 0, 'catalog.unavailable');
            return;
        }

        $returnStoreId = isset($data['return_store_id'])
            ? $this->parseUuid((string) $data['return_store_id'])
            : null;
        if (!$created instanceof Store && !$created instanceof Category) {
            throw new RuntimeException('Catalog entry was not persisted after a successful command.');
        }
        if ($type === 'category' && $returnStoreId !== null) {
            $this->stateRepository->set($this->state($context, 'created_category_return', [
                'store_id' => (string) $returnStoreId,
                'category_id' => (string) $created->getId(),
            ]));
            $builder = new InlineKeyboardBuilder()
                ->button($this->trans('catalog.set_as_default'), 'catalog:return:' . $created->getId())->row()
                ->button($this->trans('common.back'), 'catalog:default:' . $returnStoreId);
            $this->render($context, $this->trans('catalog.category_created'), $builder->toArray());
            return;
        }
        $this->renderDetails($context, $type, $created->getId()->toUuid(), 'catalog.saved');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function processAliasInput(TelegramFamilyMemberContext $context, array $data, string $value): void
    {
        try {
            $name = CatalogName::fromString($value);
        } catch (InvalidArgumentException) {
            $this->render($context, 'catalog.invalid_name', $this->backKeyboard('catalog:aliases:' . ($data['store_id'] ?? '')));
            return;
        }
        $familyId = $context->activeFamily->getId();
        $storeId = isset($data['store_id']) ? $this->parseUuid((string) $data['store_id']) : null;
        if ($storeId === null || $this->catalog->findStore($familyId, new StoreId($storeId)) === null) {
            $this->renderList($context, 'store', 0, 'catalog.unavailable');
            return;
        }

        $canonical = $this->catalog->findStoreByDedupeKey($familyId, $name->getDedupeKey());
        $aliasId = isset($data['alias_id']) ? $this->parseUuid((string) $data['alias_id']) : null;
        $existingAlias = $this->catalog->findStoreAliasByDedupeKey(
            $familyId,
            $name->getDedupeKey(),
            $aliasId === null ? null : new StoreAliasId($aliasId)
        );
        if ($canonical !== null || $existingAlias !== null) {
            $existingName = $canonical?->getName() ?? $existingAlias?->getAlias() ?? '';
            $conflictingStoreId = $canonical?->getId() ?? $existingAlias?->getStoreId() ?? $storeId;
            $data['value'] = $name->getValue();
            $this->stateRepository->set($this->state($context, 'alias_conflict', $data));
            $builder = new InlineKeyboardBuilder()
                ->button($this->trans('catalog.open_conflict'), 'catalog:warning:open:' . $conflictingStoreId)->row()
                ->button($this->trans('catalog.edit_name'), 'catalog:alias:conflict:edit')->row()
                ->button($this->trans('common.back'), 'catalog:aliases:' . $storeId);
            $this->render(
                $context,
                $this->trans('catalog.alias_conflict', ['%{name}' => $this->escape($existingName)]),
                $builder->toArray()
            );
            return;
        }

        try {
            if ($aliasId !== null) {
                $this->runCommand(new RenameStoreAliasCommand(
                    $familyId,
                    new StoreId($storeId),
                    new StoreAliasId($aliasId),
                    $context->user->getId(),
                    $name->getValue()
                ));
            } else {
                $this->runCommand(new AddStoreAliasCommand(
                    $familyId,
                    new StoreId($storeId),
                    $context->user->getId(),
                    $name->getValue()
                ));
            }
        } catch (CatalogEntryNotFoundException) {
            $this->renderAliases($context, $storeId, 'catalog.unavailable');
            return;
        } catch (CatalogNameConflictException $exception) {
            $this->render(
                $context,
                $this->trans('catalog.alias_conflict', ['%{name}' => $this->escape($exception->conflictingName)]),
                $this->backKeyboard('catalog:aliases:' . $storeId)
            );
            return;
        }
        $this->renderAliases($context, $storeId, 'catalog.alias_saved');
    }

    private function deleteEntry(TelegramFamilyMemberContext $context, string $type, Uuid $id): void
    {
        $familyId = $context->activeFamily->getId();
        try {
            if ($type === 'store') {
                $this->runCommand(new DeleteStoreCommand($familyId, new StoreId($id), $context->user->getId()));
            } else {
                $this->runCommand(new DeleteCategoryCommand($familyId, new CategoryId($id), $context->user->getId()));
            }
        } catch (CatalogEntryReferencedException) {
            $this->render(
                $context,
                $this->trans('catalog.referenced'),
                $this->backKeyboard('catalog:select:' . $type . ':' . $id)
            );
            return;
        } catch (CatalogEntryNotFoundException) {
            $this->renderList($context, $type, 0, 'catalog.unavailable');
            return;
        }
        $this->renderList($context, $type, 0, $type === 'store' ? 'catalog.store_deleted' : 'catalog.category_deleted');
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
        $text = str_starts_with($textOrKey, 'catalog.') ? $this->trans($textOrKey) : $textOrKey;
        $state = $previousState ?? $this->stateRepository->get($context->telegramUserId);
        $messageId = isset($state?->data['message_id']) ? (int) $state->data['message_id'] : null;
        if ($messageId !== null) {
            $response = $this->telegramClient->editMessageText(
                $context->chatId,
                $messageId,
                $text,
                $markup
            );
            if (!($response['ok'] ?? false)) {
                $response = $this->telegramClient->sendMessage($context->chatId, $text, $markup);
                if (!($response['ok'] ?? false)) {
                    throw new RuntimeException('Unable to render catalog card: ' . ($response['description'] ?? 'unknown Telegram API error'));
                }
                if (!$this->telegramClient->deleteMessage($context->chatId, $messageId)) {
                    $closed = $this->telegramClient->editMessageText(
                        $context->chatId,
                        $messageId,
                        $text,
                        ['inline_keyboard' => []]
                    );
                    if (!($closed['ok'] ?? false)) {
                        throw new RuntimeException('Unable to close the previous catalog card after sending a replacement.');
                    }
                }
                $messageId = null;
            }
        } else {
            $response = $this->telegramClient->sendMessage($context->chatId, $text, $markup);
            if (!($response['ok'] ?? false)) {
                throw new RuntimeException('Unable to render catalog card: ' . ($response['description'] ?? 'unknown Telegram API error'));
            }
        }

        $responseMessageId = $response['result']['message_id'] ?? null;
        $current = $this->stateRepository->get($context->telegramUserId);
        if ($current !== null && $current->flow === self::FLOW_NAME) {
            $data = $current->data;
            if ($responseMessageId !== null) {
                $data['message_id'] = (int) $responseMessageId;
            } elseif ($messageId !== null) {
                $data['message_id'] = $messageId;
            }
            $this->stateRepository->set($this->state($context, $current->step, $data));
        }
    }

    private function runCommand(object $command): mixed
    {
        $envelope = $this->commandBus->dispatch($command);
        $handledStamp = $envelope->last(HandledStamp::class);

        return $handledStamp?->getResult();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function state(TelegramFamilyMemberContext $context, string $step, array $data): UserInteractionState
    {
        $old = $this->stateRepository->get($context->telegramUserId);
        if (isset($old?->data['message_id'])) {
            $data['message_id'] = $old->data['message_id'];
        }

        return new UserInteractionState($context->telegramUserId, self::FLOW_NAME, $step, $data);
    }

    /** @return array<string, mixed> */
    private function backKeyboard(string $callback): array
    {
        return (new InlineKeyboardBuilder())
            ->button($this->trans('common.back'), $callback)
            ->toArray();
    }

    /** @return array<string, mixed> */
    private function formKeyboard(string $callback): array
    {
        return (new InlineKeyboardBuilder())
            ->button($this->trans('common.back'), $callback)
            ->button($this->trans('common.cancel'), 'catalog:cancel')
            ->toArray();
    }

    private function namePrompt(string $value): string
    {
        return $value === ''
            ? $this->trans('catalog.prompt_name')
            : $this->trans('catalog.prompt_name_current', ['%{name}' => $this->escape($value)]);
    }

    private function listCallback(string $type): string
    {
        return 'catalog:list:' . $type;
    }

    private function stateUuid(?UserInteractionState $state, string $key): ?Uuid
    {
        return isset($state?->data[$key]) ? $this->parseUuid((string) $state->data[$key]) : null;
    }

    private function parseUuid(string $value): ?Uuid
    {
        return Uuid::isValid($value) ? Uuid::fromString($value) : null;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function escapeButton(string $value): string
    {
        return mb_strlen($value) > 60 ? mb_substr($value, 0, 57) . '…' : $value;
    }

    /** @param array<string|int, mixed> $parameters */
    private function trans(string $key, array $parameters = []): string
    {
        return $this->translator->trans($key, $parameters);
    }
}

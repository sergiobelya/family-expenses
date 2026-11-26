<?php

namespace App\Presentation\Telegram\Flow\Catalog;

use App\Catalog\Application\Command\DeleteStoreCommand;
use App\Catalog\Application\Query\CatalogSearchInterface;
use App\Catalog\Domain\Entity\Store;
use App\Catalog\Domain\Exception\CatalogEntryNotFoundException;
use App\Catalog\Domain\Repository\CatalogRepositoryInterface;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\StoreId;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\Keyboard\InlineKeyboardBuilder;
use App\Presentation\Telegram\MapExceptionToTranslatableMessage;
use Symfony\Component\Uid\Uuid;

final class StoreEntryScreen implements CatalogEntryScreen
{
    private StoreNameInputHandler $nameInput;

    public function __construct(
        private CatalogFlowUtils $utils,
        private CatalogRepositoryInterface $catalog,
        private CatalogSearchInterface $search,
        private MapExceptionToTranslatableMessage $exceptionToTranslation,
    ) {
        $this->nameInput = new StoreNameInputHandler(
            $this->utils,
            $this->catalog,
            $this->search,
            $this->exceptionToTranslation,
            $this->getListCallback()
        );
    }

    public function renderList(TelegramFamilyMemberContext $context, int $page, ?string $notice = null): void
    {
        $familyId = $context->activeFamily->getId();
        $state = $this->utils->getStateRepository()->get($context->telegramUserId);
        $entries = $this->catalog->findStores($familyId);
        $start = $page * CatalogFlowUtils::PAGE_SIZE;
        $entries = array_slice($entries, $start, CatalogFlowUtils::PAGE_SIZE);

        $text = $this->buildListText($context, $entries, $notice);
        $keyboard = $this->buildListKeyboard($entries, $page, $start, $familyId);

        $this->utils->getStateRepository()->set($this->utils->state($context, 'list', ['type' => 'store', 'page' => $page]));
        $this->utils->render($context, $text, $keyboard, $state);
    }

    /**
     * @param Store[] $entries
     */
    private function buildListText(TelegramFamilyMemberContext $context, array $entries, ?string $notice): string
    {
        $text = '<b>' . $this->utils->trans('catalog.stores_title') . '</b> · 👪 <b>'
            . $this->utils->escape((string) $context->activeFamily->getName()) . '</b>';
        if ($notice !== null) {
            $text .= "\n\n" . $this->utils->trans($notice);
        }
        if ($entries === []) {
            $text .= "\n\n" . $this->utils->trans('catalog.stores_empty');
        } else {
            $categories = $this->buildCategoryMap($context->activeFamily->getId());
            foreach ($entries as $entry) {
                $text .= "\n• " . $this->utils->escape((string) $entry->getName());
                if ($entry->getDefaultCategoryId() !== null) {
                    $categoryName = $categories[(string) $entry->getDefaultCategoryId()] ?? null;
                    if ($categoryName !== null) {
                        $text .= ' — ' . $this->utils->escape($categoryName);
                    }
                }
            }
        }
        return $text;
    }

    /**
     * @return array<string, string>
     */
    private function buildCategoryMap(FamilyId $familyId): array
    {
        $categories = [];
        foreach ($this->catalog->findCategories($familyId) as $category) {
            $categories[(string) $category->getId()] = (string) $category->getName();
        }
        return $categories;
    }

    /**
     * @param Store[] $entries
     * @return array<string, mixed>
     */
    private function buildListKeyboard(array $entries, int $page, int $start, FamilyId $familyId): array
    {
        $builder = new InlineKeyboardBuilder();
        $builder->button(
            $this->utils->trans('catalog.add'),
            CallbackActions::CATALOG_PREFIX . 'add:store'
        )->button($this->utils->trans('catalog.search'), CallbackActions::CATALOG_SEARCH_PREFIX . 'store')->row();
        foreach (array_chunk($entries, 2) as $row) {
            foreach ($row as $entry) {
                $builder->button(
                    $this->utils->escapeButton((string) $entry->getName()),
                    CallbackActions::CATALOG_PREFIX . 'select:store:' . $entry->getId()
                );
            }
            $builder->row();
        }
        if ($page > 0) {
            $builder->button($this->utils->trans('selector.previous'), sprintf('%sstore:%d', CallbackActions::CATALOG_PAGE_PREFIX, $page - 1));
        }
        $total = count($this->catalog->findStores($familyId));
        if ($start + CatalogFlowUtils::PAGE_SIZE < $total) {
            $builder->button($this->utils->trans('selector.next'), sprintf('%sstore:%d', CallbackActions::CATALOG_PAGE_PREFIX, $page + 1));
        }
        if ($page > 0 || $start + CatalogFlowUtils::PAGE_SIZE < $total) {
            $builder->row();
        }
        return $builder->toArray();
    }

    public function renderDetails(TelegramFamilyMemberContext $context, Uuid $id, ?string $notice = null): void
    {
        $familyId = $context->activeFamily->getId();
        $state = $this->utils->getStateRepository()->get($context->telegramUserId);
        $returnContext = $state?->data['expense_return_context'] ?? null;
        $entry = $this->catalog->findStore($familyId, new StoreId($id));
        if ($entry === null) {
            $this->renderList($context, 0, 'catalog.unavailable');
            return;
        }
        $categories = $this->buildCategoryMap($familyId);
        $default = $entry->getDefaultCategoryId() === null
            ? $this->utils->trans('catalog.none')
            : $this->utils->escape($categories[(string) $entry->getDefaultCategoryId()] ?? $this->utils->trans('catalog.unavailable'));
        $aliases = array_map(
            fn ($alias): string => $this->utils->escape((string) $alias->getName()),
            $this->catalog->findStoreAliases($familyId, new StoreId($id))
        );
        $text = '<b>' . $this->utils->escape((string) $entry->getName()) . '</b>' . "\n"
            . $this->utils->trans('catalog.default_category') . ': ' . $default . "\n"
            . $this->utils->trans('catalog.aliases') . ': ' . ($aliases === [] ? $this->utils->trans('catalog.none') : implode(', ', $aliases));
        $builder = new InlineKeyboardBuilder()
            ->button($this->utils->trans('catalog.rename'), CallbackActions::CATALOG_RENAME_PREFIX . 'store:' . $id)->row()
            ->button($this->utils->trans('catalog.default_category'), CallbackActions::CATALOG_DEFAULT_CATEGORY_OPEN_PREFIX . $id)->row()
            ->button($this->utils->trans('catalog.aliases'), CallbackActions::CATALOG_ALIASES_PREFIX . $id)->row()
            ->button($this->utils->trans('catalog.delete'), CallbackActions::CATALOG_PREFIX . 'confirm:store:' . $id)->row()
            ->button($this->utils->trans('common.back'), CallbackActions::CATALOG_PREFIX . 'list:store');
        $stateData = ['store_id' => (string) $id];
        if (is_array($returnContext)) {
            $stateData['expense_return_context'] = $returnContext;
            $builder->row()->button(
                $this->utils->trans('expense.add.draft.return'),
                CallbackActions::CATALOG_RETURN_PREFIX . 'expense'
            );
        }
        $this->utils->getStateRepository()->set($this->utils->state($context, 'store_details', $stateData));
        if ($notice !== null) {
            $text .= "\n\n" . $this->utils->trans($notice);
        }
        $this->utils->render($context, $text, $builder->toArray());
    }

    public function renderDeleteConfirmation(TelegramFamilyMemberContext $context, Uuid $id): void
    {
        $entry = $this->catalog->findStore($context->activeFamily->getId(), new StoreId($id));
        if ($entry === null) {
            $this->renderList($context, 0, 'catalog.unavailable');
            return;
        }
        $this->utils->getStateRepository()->set($this->utils->state($context, 'delete_confirmation', [
            'type' => 'store',
            'id' => (string) $id,
        ]));
        $builder = new InlineKeyboardBuilder()
            ->button($this->utils->trans('catalog.confirm_delete'), CallbackActions::CATALOG_DELETE_PREFIX . 'store:' . $id)->row()
            ->button($this->utils->trans('catalog.keep'), CallbackActions::CATALOG_PREFIX . 'select:store:' . $id);
        $this->utils->render(
            $context,
            $this->utils->trans('catalog.delete_confirmation', ['%{name}' => $this->utils->escape((string) $entry->getName())]),
            $builder->toArray()
        );
    }

    public function deleteEntry(TelegramFamilyMemberContext $context, Uuid $id): void
    {
        $familyId = $context->activeFamily->getId();
        try {
            $this->utils->runCommand(new DeleteStoreCommand($familyId, new StoreId($id), $context->user->getId()));
        } catch (CatalogEntryNotFoundException) {
            $this->renderList($context, 0, 'catalog.unavailable');
            return;
        }
        $this->renderList($context, 0, 'catalog.store_deleted');
    }

    /** @param array<string, mixed>|null $expenseReturnContext */
    public function startNameInput(
        TelegramFamilyMemberContext $context,
        string $operation,
        ?Uuid $id = null,
        ?string $returnStoreId = null,
        ?string $prefill = null,
        ?array $expenseReturnContext = null
    ): void {
        $value = $prefill ?? '';
        if ($id !== null) {
            $entry = $this->catalog->findStore($context->activeFamily->getId(), new StoreId($id));
            if ($entry === null) {
                $this->renderList($context, 0, 'catalog.unavailable');
                return;
            }
            $value = (string) $entry->getName();
        }
        $data = [
            'type' => 'store',
            'operation' => $operation,
            'value' => $value,
        ];
        if ($id !== null) {
            $data['id'] = (string) $id;
        }
        if ($returnStoreId !== null) {
            $data['return_store_id'] = $returnStoreId;
        }
        if ($expenseReturnContext !== null) {
            $data['expense_return_context'] = $expenseReturnContext;
        }
        $this->utils->getStateRepository()->set($this->utils->state($context, 'store_name', $data));
        $prompt = $this->utils->namePrompt($value);
        if (is_string($expenseReturnContext['pause_message'] ?? null)) {
            $prompt = $expenseReturnContext['pause_message'] . "\n\n" . $prompt;
        }
        $this->utils->render($context, $prompt, $this->utils->formKeyboard($this->getListCallback()));
    }

    public function processNameInput(TelegramFamilyMemberContext $context, array $data, string $value): void
    {
        $this->nameInput->process(
            $context,
            $data,
            $value,
            fn (TelegramFamilyMemberContext $ctx, int $page, ?string $notice) => $this->renderList($ctx, $page, $notice),
            fn (TelegramFamilyMemberContext $ctx, Uuid $id, ?string $notice) => $this->renderDetails($ctx, $id, $notice)
        );
    }

    public function startSearch(TelegramFamilyMemberContext $context): void
    {
        $this->utils->getStateRepository()->set($this->utils->state($context, 'search', [
            'type' => 'store',
        ]));
        $this->utils->render(
            $context,
            $this->utils->trans('catalog.search_prompt'),
            $this->utils->formKeyboard(CallbackActions::CATALOG_PREFIX . 'list:store')
        );
    }

    public function searchAndRender(TelegramFamilyMemberContext $context, string $query): void
    {
        $results = $this->search->stores($context->activeFamily->getId(), $query);
        $this->renderSearchResults($context, $query, $results);
    }

    /**
     * @param Store[] $results
     */
    private function renderSearchResults(
        TelegramFamilyMemberContext $context,
        string $query,
        array $results
    ): void {
        $text = '<b>' . $this->utils->trans('catalog.search_results', ['%{query}' => $this->utils->escape($query)]) . '</b>';
        if ($results === []) {
            $text .= "\n\n" . $this->utils->trans('catalog.search_no_results');
        }
        $builder = new InlineKeyboardBuilder();
        foreach (array_chunk($results, 2) as $row) {
            foreach ($row as $entry) {
                $builder->button(
                    $this->utils->escapeButton((string) $entry->getName()),
                    CallbackActions::CATALOG_PREFIX . 'select:store:' . $entry->getId()
                );
            }
            $builder->row();
        }
        $builder->button($this->utils->trans('common.back'), CallbackActions::CATALOG_PREFIX . 'list:store');
        $this->utils->render($context, $text, $builder->toArray());
    }

    public function getListCallback(): string
    {
        return CallbackActions::CATALOG_PREFIX . 'list:store';
    }

}

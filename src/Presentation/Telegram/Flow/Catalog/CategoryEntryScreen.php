<?php

namespace App\Presentation\Telegram\Flow\Catalog;

use App\Catalog\Application\Command\DeleteCategoryCommand;
use App\Catalog\Application\Query\CatalogSearchInterface;
use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Exception\CatalogEntryNotFoundException;
use App\Catalog\Domain\Repository\CatalogRepositoryInterface;
use App\Core\Domain\ValueObject\CategoryId;
use App\Core\Domain\ValueObject\FamilyId;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\Keyboard\InlineKeyboardBuilder;
use App\Presentation\Telegram\MapExceptionToTranslationKey;
use Symfony\Component\Uid\Uuid;

final class CategoryEntryScreen implements CatalogEntryScreen
{
    private CategoryNameInputHandler $nameInput;

    public function __construct(
        private CatalogFlowUtils $utils,
        private CatalogRepositoryInterface $catalog,
        private CatalogSearchInterface $search,
        private MapExceptionToTranslationKey $exceptionToTranslation,
    ) {
        $this->nameInput = new CategoryNameInputHandler(
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
        $entries = $this->catalog->findCategories($familyId);
        $start = $page * CatalogFlowUtils::PAGE_SIZE;
        $entries = array_slice($entries, $start, CatalogFlowUtils::PAGE_SIZE);

        $text = $this->buildListText($context, $entries, $notice);
        $keyboard = $this->buildListKeyboard($entries, $page, $start, $familyId);

        $this->utils->getStateRepository()->set($this->utils->state($context, 'list', ['type' => 'category', 'page' => $page]));
        $this->utils->render($context, $text, $keyboard, $state);
    }

    /**
     * @param Category[] $entries
     */
    private function buildListText(TelegramFamilyMemberContext $context, array $entries, ?string $notice): string
    {
        $text = '<b>' . $this->utils->trans('catalog.categories_title') . '</b> · 👪 <b>'
            . $this->utils->escape((string) $context->activeFamily->getName()) . '</b>';
        if ($notice !== null) {
            $text .= "\n\n" . $this->utils->trans($notice);
        }
        if ($entries === []) {
            $text .= "\n\n" . $this->utils->trans('catalog.categories_empty');
        } else {
            foreach ($entries as $entry) {
                $text .= "\n• " . $this->utils->escape((string) $entry->getName());
            }
        }
        return $text;
    }

    /**
     * @param Category[] $entries
     * @return array<string, mixed>
     */
    private function buildListKeyboard(array $entries, int $page, int $start, FamilyId $familyId): array
    {
        $builder = new InlineKeyboardBuilder();
        $builder->button(
            $this->utils->trans('catalog.add'),
            CallbackActions::CATALOG_PREFIX . 'add:category'
        )->button($this->utils->trans('catalog.search'), CallbackActions::CATALOG_SEARCH_PREFIX . 'category')->row();
        foreach (array_chunk($entries, 2) as $row) {
            foreach ($row as $entry) {
                $builder->button(
                    $this->utils->escapeButton((string) $entry->getName()),
                    CallbackActions::CATALOG_PREFIX . 'select:category:' . $entry->getId()
                );
            }
            $builder->row();
        }
        if ($page > 0) {
            $builder->button($this->utils->trans('selector.previous'), sprintf('%scategory:%d', CallbackActions::CATALOG_PAGE_PREFIX, $page - 1));
        }
        $total = count($this->catalog->findCategories($familyId));
        if ($start + CatalogFlowUtils::PAGE_SIZE < $total) {
            $builder->button($this->utils->trans('selector.next'), sprintf('%scategory:%d', CallbackActions::CATALOG_PAGE_PREFIX, $page + 1));
        }
        if ($page > 0 || $start + CatalogFlowUtils::PAGE_SIZE < $total) {
            $builder->row();
        }
        return $builder->toArray();
    }

    public function renderDetails(TelegramFamilyMemberContext $context, Uuid $id, ?string $notice = null): void
    {
        $entry = $this->catalog->findCategory($context->activeFamily->getId(), new CategoryId($id));
        if ($entry === null) {
            $this->renderList($context, 0, 'catalog.unavailable');
            return;
        }
        $text = '<b>' . $this->utils->escape((string) $entry->getName()) . '</b>';
        $builder = new InlineKeyboardBuilder()
            ->button($this->utils->trans('catalog.rename'), CallbackActions::CATALOG_RENAME_PREFIX . 'category:' . $id)->row()
            ->button($this->utils->trans('catalog.delete'), CallbackActions::CATALOG_PREFIX . 'confirm:category:' . $id)->row()
            ->button($this->utils->trans('common.back'), CallbackActions::CATALOG_PREFIX . 'list:category');
        $this->utils->getStateRepository()->set($this->utils->state($context, 'category_details', ['category_id' => (string) $id]));
        if ($notice !== null) {
            $text .= "\n\n" . $this->utils->trans($notice);
        }
        $this->utils->render($context, $text, $builder->toArray());
    }

    public function renderDeleteConfirmation(TelegramFamilyMemberContext $context, Uuid $id): void
    {
        $entry = $this->catalog->findCategory($context->activeFamily->getId(), new CategoryId($id));
        if ($entry === null) {
            $this->renderList($context, 0, 'catalog.unavailable');
            return;
        }
        $this->utils->getStateRepository()->set($this->utils->state($context, 'delete_confirmation', [
            'type' => 'category',
            'id' => (string) $id,
        ]));
        $builder = new InlineKeyboardBuilder()
            ->button($this->utils->trans('catalog.confirm_delete'), CallbackActions::CATALOG_DELETE_PREFIX . 'category:' . $id)->row()
            ->button($this->utils->trans('catalog.keep'), CallbackActions::CATALOG_PREFIX . 'select:category:' . $id);
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
            $this->utils->runCommand(new DeleteCategoryCommand($familyId, new CategoryId($id), $context->user->getId()));
        } catch (CatalogEntryNotFoundException) {
            $this->renderList($context, 0, 'catalog.unavailable');
            return;
        }
        $this->renderList($context, 0, 'catalog.category_deleted');
    }

    public function startNameInput(
        TelegramFamilyMemberContext $context,
        string $operation,
        ?Uuid $id = null,
        ?string $returnStoreId = null,
        ?string $prefill = null
    ): void {
        $value = $prefill ?? '';
        if ($id !== null) {
            $entry = $this->catalog->findCategory($context->activeFamily->getId(), new CategoryId($id));
            if ($entry === null) {
                $this->renderList($context, 0, 'catalog.unavailable');
                return;
            }
            $value = (string) $entry->getName();
        }
        $data = [
            'type' => 'category',
            'operation' => $operation,
            'value' => $value,
        ];
        if ($id !== null) {
            $data['id'] = (string) $id;
        }
        if ($returnStoreId !== null) {
            $data['return_store_id'] = $returnStoreId;
        }
        $this->utils->getStateRepository()->set($this->utils->state($context, 'category_name', $data));
        $this->utils->render($context, $this->utils->namePrompt($value), $this->utils->formKeyboard($this->getListCallback()));
    }

    public function processNameInput(TelegramFamilyMemberContext $context, array $data, string $value): void
    {
        $returnStoreId = isset($data['return_store_id']) ? (string) $data['return_store_id'] : null;
        $this->nameInput->process(
            $context,
            $data,
            $value,
            $returnStoreId,
            fn (TelegramFamilyMemberContext $ctx, int $page, ?string $notice) => $this->renderList($ctx, $page, $notice),
            fn (TelegramFamilyMemberContext $ctx, Uuid $id, ?string $notice) => $this->renderDetails($ctx, $id, $notice)
        );
    }

    public function startSearch(TelegramFamilyMemberContext $context): void
    {
        $this->utils->getStateRepository()->set($this->utils->state($context, 'search', [
            'type' => 'category',
        ]));
        $this->utils->render(
            $context,
            $this->utils->trans('catalog.search_prompt'),
            $this->utils->formKeyboard(CallbackActions::CATALOG_PREFIX . 'list:category')
        );
    }

    public function searchAndRender(TelegramFamilyMemberContext $context, string $query): void
    {
        $results = $this->search->categories($context->activeFamily->getId(), $query);
        $this->renderSearchResults($context, $query, $results);
    }

    /**
     * @param Category[] $results
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
                    CallbackActions::CATALOG_PREFIX . 'select:category:' . $entry->getId()
                );
            }
            $builder->row();
        }
        $builder->button($this->utils->trans('common.back'), CallbackActions::CATALOG_PREFIX . 'list:category');
        $this->utils->render($context, $text, $builder->toArray());
    }

    public function getListCallback(): string
    {
        return CallbackActions::CATALOG_PREFIX . 'list:category';
    }

}

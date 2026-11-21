<?php

namespace App\Presentation\Telegram\Flow\Catalog;

use App\Catalog\Application\Command\SetStoreDefaultCategoryCommand;
use App\Catalog\Domain\Exception\CatalogEntryNotFoundException;
use App\Catalog\Domain\Repository\CatalogRepositoryInterface;
use App\Core\Domain\ValueObject\CategoryId;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\StoreId;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\Keyboard\InlineOptionSelector;
use Symfony\Component\Uid\Uuid;

final class StoreDefaultCategoryScreen
{
    public function __construct(
        private CatalogFlowUtils $utils,
        private StoreEntryScreen $storeScreen,
        private CatalogRepositoryInterface $catalog,
        private InlineOptionSelector $optionSelector,
    ) {
    }

    public function renderDefaultCategoryPicker(
        TelegramFamilyMemberContext $context,
        Uuid $storeId,
        int $page = 0,
        string $query = ''
    ): void {
        $store = $this->catalog->findStore($context->activeFamily->getId(), new StoreId($storeId));
        if ($store === null) {
            $this->storeScreen->renderList($context, 0, 'catalog.unavailable');
            return;
        }
        $options = ['none' => $this->utils->trans('catalog.no_default')];
        foreach ($this->catalog->findCategories($context->activeFamily->getId()) as $category) {
            $options[(string) $category->getId()] = (string) $category->getName();
        }
        $query = trim($query);
        $filtered = $this->optionSelector->filterOptions($options, $query);
        $page = $this->optionSelector->normalizePage($options, $query, $page);
        $selectedValue = $store->getDefaultCategoryId() === null
            ? 'none'
            : (string) $store->getDefaultCategoryId();
        $keyboard = $this->optionSelector->buildKeyboard(
            options: $options,
            callbackPrefix: CallbackActions::CATALOG_DEFAULT_CATEGORY_PREFIX,
            selectedValue: $selectedValue,
            query: $query,
            page: $page,
            searchLabel: $this->utils->trans('selector.search'),
            clearSearchLabel: $this->utils->trans('selector.clear_search'),
            previousLabel: $this->utils->trans('selector.previous'),
            nextLabel: $this->utils->trans('selector.next'),
            backLabel: $this->utils->trans('common.back'),
            backCallback: CallbackActions::CATALOG_PREFIX . 'select:store:' . $storeId
        );
        array_splice(
            $keyboard['inline_keyboard'],
            -1,
            0,
            [[[
                'text' => $this->utils->trans('catalog.create_category'),
                'callback_data' => CallbackActions::CATALOG_DEFAULT_CATEGORY_PREFIX . ':new:' . $storeId,
            ]]]
        );
        $this->utils->getStateRepository()->set($this->utils->state($context, 'default_category', [
            'store_id' => (string) $storeId,
            'query' => $query,
            'page' => $page,
        ]));
        $text = $this->utils->trans('catalog.default_picker', ['%{store}' => $this->utils->escape((string) $store->getName())]);
        if ($filtered === []) {
            $text .= "\n\n" . $this->utils->trans('selector.no_results');
        }
        $this->utils->render(
            $context,
            $text,
            $keyboard
        );
    }

    public function setDefaultCategory(
        TelegramFamilyMemberContext $context,
        Uuid $familyId,
        Uuid $storeId,
        ?Uuid $categoryId
    ): void {
        try {
            $this->utils->runCommand(new SetStoreDefaultCategoryCommand(
                new FamilyId($familyId),
                new StoreId($storeId),
                $categoryId === null ? null : new CategoryId($categoryId),
                $context->user->getId()
            ));
            $this->storeScreen->renderDetails($context, $storeId, 'catalog.saved');
        } catch (CatalogEntryNotFoundException) {
            $this->storeScreen->renderDetails($context, $storeId, 'catalog.unavailable');
        }
    }
}

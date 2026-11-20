<?php

namespace App\Presentation\Telegram\Flow\Catalog;

use App\Catalog\Application\Command\CreateCategoryCommand;
use App\Catalog\Application\Command\RenameCategoryCommand;
use App\Catalog\Application\Query\CatalogSearchInterface;
use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Exception\CatalogEntryNotFoundException;
use App\Catalog\Domain\Exception\CatalogNameConflictException;
use App\Catalog\Domain\Repository\CatalogRepositoryInterface;
use App\Catalog\Domain\ValueObject\CatalogName;
use App\Core\Domain\Exception\InvalidValueException;
use App\Core\Domain\ValueObject\CategoryId;
use App\Core\Domain\ValueObject\FamilyId;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\Keyboard\InlineKeyboardBuilder;
use App\Presentation\Telegram\MapExceptionToTranslationKey;
use RuntimeException;
use Symfony\Component\Uid\Uuid;

final class CategoryNameInputHandler
{
    public function __construct(
        private CatalogFlowUtils $utils,
        private CatalogRepositoryInterface $catalog,
        private CatalogSearchInterface $search,
        private MapExceptionToTranslationKey $exceptionToTranslation,
        private string $listCallback,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function process(
        TelegramFamilyMemberContext $context,
        array $data,
        string $value,
        ?string $returnStoreId,
        callable $renderList,
        callable $renderDetails
    ): void {
        try {
            $name = CatalogName::fromString($value);
        } catch (InvalidValueException $e) {
            $this->utils->render(
                $context,
                ($this->exceptionToTranslation)($e),
                $this->utils->backKeyboard($this->listCallback)
            );
            return;
        }

        $familyId = $context->activeFamily->getId();
        $conflict = $this->findConflictingCategory($familyId, $name, $data);
        if ($conflict !== null) {
            $this->renderHardConflict(
                $context,
                $data,
                $name->getValue(),
                $conflict['name'],
                $conflict['id']
            );
            return;
        }
        $id = isset($data['id']) ? $this->utils->parseUuid((string) $data['id']) : null;
        $categoryId = $id === null ? null : new CategoryId($id);
        $matches = array_filter(
            $this->search->categories($familyId, $name->getValue()),
            fn (Category $category): bool => $category->getDedupeKey() !== $name->getDedupeKey()
                && ($categoryId === null || !$category->getId()->equals($categoryId))
        );

        $data['value'] = $name->getValue();
        if ($matches !== []) {
            $this->utils->getStateRepository()->set($this->utils->state($context, 'warning', $data));
            $operation = ($data['operation'] ?? 'create') === 'rename' ? 'rename' : 'create';
            $builder = new InlineKeyboardBuilder()
                ->button(
                    $this->utils->trans('catalog.confirm_category_' . $operation),
                    CallbackActions::CATALOG_WARNING_CONFIRM
                )->row();
            foreach (array_slice($matches, 0, 5) as $match) {
                $builder->button(
                    $this->utils->escapeButton((string) $match->getName()),
                    CallbackActions::CATALOG_WARNING_OPEN_CATEGORY_PREFIX . $match->getId()
                )->row();
            }
            $builder->button($this->utils->trans('catalog.edit_name'), CallbackActions::CATALOG_WARNING_EDIT)->row()
                ->button($this->utils->trans('common.cancel'), CallbackActions::CATALOG_WARNING_CANCEL);
            $this->utils->render(
                $context,
                $this->utils->trans(
                    'catalog.similar_category_' . $operation,
                    ['%{name}' => $this->utils->escape($name->getValue())]
                ),
                $builder->toArray()
            );
            return;
        }
        $this->save($context, $data, $returnStoreId, $renderList, $renderDetails);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{name: string, id: Uuid}|null
     */
    private function findConflictingCategory(
        FamilyId $familyId,
        CatalogName $name,
        array $data
    ): ?array {
        $id = isset($data['id']) ? $this->utils->parseUuid((string) $data['id']) : null;
        $categoryId = $id === null ? null : new CategoryId($id);
        $conflict = $this->catalog->findCategoryByDedupeKey($familyId, $name->getDedupeKey(), $categoryId);
        if ($conflict !== null) {
            return ['name' => (string) $conflict->getName(), 'id' => $conflict->getId()->toUuid()];
        }
        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderHardConflict(
        TelegramFamilyMemberContext $context,
        array $data,
        string $value,
        string $existingName,
        Uuid $existingId
    ): void {
        $data['value'] = $value;
        $this->utils->getStateRepository()->set($this->utils->state($context, 'hard_conflict', $data));
        $builder = new InlineKeyboardBuilder()
            ->button(
                $this->utils->trans('catalog.open_conflict'),
                CallbackActions::CATALOG_WARNING_OPEN_CATEGORY_PREFIX . $existingId
            )->row()
            ->button($this->utils->trans('catalog.edit_name'), CallbackActions::CATALOG_WARNING_EDIT)->row()
            ->button($this->utils->trans('common.back'), $this->listCallback);
        $this->utils->render(
            $context,
            $this->utils->trans(
                'catalog.hard_conflict_category',
                ['%{name}' => $this->utils->escape($existingName)]
            ),
            $builder->toArray()
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function save(
        TelegramFamilyMemberContext $context,
        array $data,
        ?string $returnStoreId,
        callable $renderList,
        callable $renderDetails
    ): void {
        $familyId = $context->activeFamily->getId();
        $value = (string) ($data['value'] ?? '');
        $id = isset($data['id']) ? $this->utils->parseUuid((string) $data['id']) : null;
        try {
            if (($data['operation'] ?? 'create') === 'rename' && $id !== null) {
                $created = $this->utils->runCommand(new RenameCategoryCommand($familyId, new CategoryId($id), $context->user->getId(), $value));
            } else {
                $created = $this->utils->runCommand(new CreateCategoryCommand($familyId, $context->user->getId(), $value));
            }
        } catch (CatalogNameConflictException $exception) {
            $this->utils->render(
                $context,
                $this->utils->trans(
                    'catalog.hard_conflict_category',
                    ['%{name}' => $this->utils->escape($exception->conflictingName)]
                ),
                $this->utils->backKeyboard($this->listCallback)
            );
            return;
        } catch (CatalogEntryNotFoundException) {
            $renderList($context, 0, 'catalog.unavailable');
            return;
        }

        if (!$created instanceof Category) {
            throw new RuntimeException('Catalog entry was not persisted after a successful command.');
        }

        if ($returnStoreId !== null) {
            $this->utils->getStateRepository()->set($this->utils->state($context, 'created_category_return', [
                'store_id' => $returnStoreId,
                'category_id' => (string) $created->getId(),
            ]));
            $builder = new InlineKeyboardBuilder()
                ->button($this->utils->trans('catalog.set_as_default'), CallbackActions::CATALOG_RETURN_PREFIX . $created->getId())->row()
                ->button($this->utils->trans('common.back'), CallbackActions::CATALOG_DEFAULT_CATEGORY_OPEN_PREFIX . $returnStoreId);
            $this->utils->render($context, $this->utils->trans('catalog.category_created'), $builder->toArray());
            return;
        }

        $renderDetails($context, $created->getId()->toUuid(), 'catalog.saved');
    }
}

<?php

namespace App\Presentation\Telegram\Flow\Catalog;

use App\Catalog\Application\Command\CreateStoreCommand;
use App\Catalog\Application\Command\RenameStoreCommand;
use App\Catalog\Application\Query\CatalogSearchInterface;
use App\Catalog\Domain\Entity\Store;
use App\Catalog\Domain\Exception\CatalogEntryNotFoundException;
use App\Catalog\Domain\Exception\CatalogNameConflictException;
use App\Catalog\Domain\Repository\CatalogRepositoryInterface;
use App\Catalog\Domain\ValueObject\CatalogName;
use App\Core\Domain\Exception\InvalidValueException;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\StoreId;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\Keyboard\InlineKeyboardBuilder;
use App\Presentation\Telegram\MapExceptionToTranslatableMessage;
use RuntimeException;
use Symfony\Component\Uid\Uuid;

final class StoreNameInputHandler
{
    public function __construct(
        private CatalogFlowUtils $utils,
        private CatalogRepositoryInterface $catalog,
        private CatalogSearchInterface $search,
        private MapExceptionToTranslatableMessage $exceptionToTranslation,
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
        callable $renderList,
        callable $renderDetails
    ): void {
        try {
            $name = CatalogName::fromString($value);
        } catch (InvalidValueException $e) {
            $this->utils->render(
                $context,
                $this->utils->translateMessage(($this->exceptionToTranslation)($e), $context->language),
                $this->utils->backKeyboard($this->listCallback)
            );
            return;
        }

        $familyId = $context->activeFamily->getId();
        $conflict = $this->findConflictingStore($familyId, $name, $data);
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
        $storeId = $id === null ? null : new StoreId($id);
        $matches = array_filter(
            $this->search->stores($familyId, $name->getValue()),
            fn (Store $store): bool => $store->getDedupeKey() !== $name->getDedupeKey()
                && ($storeId === null || !$store->getId()->equals($storeId))
        );

        $data['value'] = $name->getValue();
        if ($matches !== []) {
            $this->utils->getStateRepository()->set($this->utils->state($context, 'warning', $data));
            $operation = ($data['operation'] ?? 'create') === 'rename' ? 'rename' : 'create';
            $builder = new InlineKeyboardBuilder()
                ->button(
                    $this->utils->trans('catalog.confirm_store_' . $operation),
                    CallbackActions::CATALOG_WARNING_CONFIRM
                )->row();
            foreach (array_slice($matches, 0, 5) as $match) {
                $builder->button(
                    $this->utils->escapeButton((string) $match->getName()),
                    CallbackActions::CATALOG_WARNING_OPEN_STORE_PREFIX . $match->getId()
                )->row();
            }
            $builder->button($this->utils->trans('catalog.edit_name'), CallbackActions::CATALOG_WARNING_EDIT)->row()
                ->button($this->utils->trans('common.cancel'), CallbackActions::CATALOG_WARNING_CANCEL);
            $this->utils->render(
                $context,
                $this->utils->trans(
                    'catalog.similar_store_' . $operation,
                    ['%{name}' => $this->utils->escape($name->getValue())]
                ),
                $builder->toArray()
            );
            return;
        }
        $this->save($context, $data, $renderList, $renderDetails);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{name: string, id: Uuid}|null
     */
    private function findConflictingStore(
        FamilyId $familyId,
        CatalogName $name,
        array $data
    ): ?array {
        $id = isset($data['id']) ? $this->utils->parseUuid((string) $data['id']) : null;
        $storeId = $id === null ? null : new StoreId($id);
        $conflict = $this->catalog->findStoreByDedupeKey($familyId, $name->getDedupeKey(), $storeId);
        if ($conflict !== null) {
            return ['name' => (string) $conflict->getName(), 'id' => $conflict->getId()->toUuid()];
        }
        $alias = $this->catalog->findStoreAliasByDedupeKey($familyId, $name->getDedupeKey());
        if ($alias !== null) {
            return ['name' => (string) $alias->getName(), 'id' => $alias->getStoreId()->toUuid()];
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
        Uuid $existingStoreId
    ): void {
        $data['value'] = $value;
        $this->utils->getStateRepository()->set($this->utils->state($context, 'hard_conflict', $data));
        $builder = new InlineKeyboardBuilder()
            ->button(
                $this->utils->trans('catalog.open_conflict'),
                CallbackActions::CATALOG_WARNING_OPEN_STORE_PREFIX . $existingStoreId
            )->row()
            ->button($this->utils->trans('catalog.edit_name'), CallbackActions::CATALOG_WARNING_EDIT)->row()
            ->button($this->utils->trans('common.back'), $this->listCallback);
        $this->utils->render(
            $context,
            $this->utils->trans(
                'catalog.hard_conflict_store',
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
        callable $renderList,
        callable $renderDetails
    ): void {
        $familyId = $context->activeFamily->getId();
        $value = (string) ($data['value'] ?? '');
        $id = isset($data['id']) ? $this->utils->parseUuid((string) $data['id']) : null;
        try {
            if (($data['operation'] ?? 'create') === 'rename' && $id !== null) {
                $created = $this->utils->runCommand(new RenameStoreCommand($familyId, new StoreId($id), $context->user->getId(), $value));
            } else {
                $created = $this->utils->runCommand(new CreateStoreCommand($familyId, $context->user->getId(), $value));
            }
        } catch (CatalogNameConflictException $exception) {
            $this->utils->render(
                $context,
                $this->utils->trans(
                    'catalog.hard_conflict_store',
                    ['%{name}' => $this->utils->escape($exception->conflictingName)]
                ),
                $this->utils->backKeyboard($this->listCallback)
            );
            return;
        } catch (CatalogEntryNotFoundException) {
            $renderList($context, 0, 'catalog.unavailable');
            return;
        }

        if (!$created instanceof Store) {
            throw new RuntimeException('Catalog entry was not persisted after a successful command.');
        }
        $renderDetails($context, $created->getId()->toUuid(), 'catalog.saved');
    }
}

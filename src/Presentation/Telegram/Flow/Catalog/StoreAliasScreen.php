<?php

namespace App\Presentation\Telegram\Flow\Catalog;

use App\Catalog\Application\Command\AddStoreAliasCommand;
use App\Catalog\Application\Command\RenameStoreAliasCommand;
use App\Catalog\Domain\Exception\CatalogEntryNotFoundException;
use App\Catalog\Domain\Exception\CatalogNameConflictException;
use App\Catalog\Domain\Repository\CatalogRepositoryInterface;
use App\Catalog\Domain\ValueObject\CatalogName;
use App\Core\Domain\Exception\InvalidValueException;
use App\Core\Domain\ValueObject\StoreAliasId;
use App\Core\Domain\ValueObject\StoreId;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\Keyboard\InlineKeyboardBuilder;
use App\Presentation\Telegram\MapExceptionToTranslationKey;
use Symfony\Component\Uid\Uuid;

final class StoreAliasScreen
{
    public function __construct(
        private CatalogFlowUtils $utils,
        private StoreEntryScreen $storeScreen,
        private CatalogRepositoryInterface $catalog,
        private MapExceptionToTranslationKey $exceptionToTranslation,
    ) {
    }

    public function renderAliases(
        TelegramFamilyMemberContext $context,
        Uuid $storeId,
        ?string $notice = null
    ): void {
        $store = $this->catalog->findStore($context->activeFamily->getId(), new StoreId($storeId));
        if ($store === null) {
            $this->storeScreen->renderList($context, 0, 'catalog.unavailable');
            return;
        }
        $aliases = $this->catalog->findStoreAliases($context->activeFamily->getId(), new StoreId($storeId));
        $text = $this->utils->trans('catalog.aliases_title', ['%{store}' => $this->utils->escape((string) $store->getName())]);
        if ($notice !== null) {
            $text .= "\n\n" . $this->utils->trans($notice);
        }
        if ($aliases === []) {
            $text .= "\n\n" . $this->utils->trans('catalog.aliases_empty');
        }
        $builder = new InlineKeyboardBuilder()->button($this->utils->trans('catalog.add_alias'), CallbackActions::CATALOG_ALIAS_PREFIX . 'add:' . $storeId)->row();
        foreach ($aliases as $alias) {
            $builder->button($this->utils->escapeButton((string) $alias->getName()), CallbackActions::CATALOG_ALIAS_PREFIX . 'select:' . $alias->getId())->row();
        }
        $builder->button($this->utils->trans('common.back'), CallbackActions::CATALOG_PREFIX . 'select:store:' . $storeId);
        $this->utils->getStateRepository()->set($this->utils->state($context, 'aliases', ['store_id' => (string) $storeId]));
        $this->utils->render($context, $text, $builder->toArray());
    }

    public function selectAlias(
        TelegramFamilyMemberContext $context,
        Uuid $storeId,
        Uuid $aliasId
    ): void {
        $alias = $this->catalog->findStoreAlias(
            $context->activeFamily->getId(),
            new StoreId($storeId),
            new StoreAliasId($aliasId)
        );
        if ($alias !== null) {
            $this->renderAliasDetails($context, $storeId, $aliasId, (string) $alias->getName());
        } else {
            $this->renderAliases($context, $storeId, 'catalog.unavailable');
        }
    }

    private function renderAliasDetails(
        TelegramFamilyMemberContext $context,
        Uuid $storeId,
        Uuid $aliasId,
        string $name
    ): void {
        $this->utils->getStateRepository()->set($this->utils->state($context, 'alias_details', [
            'store_id' => (string) $storeId,
            'alias_id' => (string) $aliasId,
        ]));
        $builder = new InlineKeyboardBuilder()
            ->button($this->utils->trans('catalog.rename'), CallbackActions::CATALOG_ALIAS_PREFIX . 'rename:' . $aliasId)->row()
            ->button($this->utils->trans('catalog.remove_alias'), CallbackActions::CATALOG_ALIAS_PREFIX . 'remove:' . $aliasId)->row()
            ->button($this->utils->trans('common.back'), CallbackActions::CATALOG_ALIASES_PREFIX . $storeId);
        $this->utils->render($context, '<b>' . $this->utils->escape($name) . '</b>', $builder->toArray());
    }

    public function startRenameAlias(
        TelegramFamilyMemberContext $context,
        Uuid $storeId,
        Uuid $aliasId
    ): void {
        $alias = $this->catalog->findStoreAlias(
            $context->activeFamily->getId(),
            new StoreId($storeId),
            new StoreAliasId($aliasId)
        );
        if ($alias !== null) {
            $this->utils->getStateRepository()->set($this->utils->state($context, 'alias_name', [
                'store_id' => (string) $storeId,
                'alias_id' => (string) $aliasId,
                'operation' => 'rename',
                'value' => (string) $alias->getName(),
            ]));
            $this->utils->render(
                $context,
                $this->utils->trans('catalog.prompt_alias_current', [
                    '%{name}' => $this->utils->escape((string) $alias->getName()),
                ]),
                $this->utils->formKeyboard(CallbackActions::CATALOG_ALIASES_PREFIX . $storeId)
            );
        } else {
            $this->renderAliases($context, $storeId, 'catalog.unavailable');
        }
    }

    public function startAliasInput(TelegramFamilyMemberContext $context, Uuid $storeId): void
    {
        if ($this->catalog->findStore($context->activeFamily->getId(), new StoreId($storeId)) === null) {
            $this->storeScreen->renderList($context, 0, 'catalog.unavailable');
            return;
        }
        $this->utils->getStateRepository()->set($this->utils->state($context, 'alias_name', [
            'store_id' => (string) $storeId,
            'operation' => 'create',
            'value' => '',
        ]));
        $this->utils->render($context, 'catalog.prompt_alias', $this->utils->formKeyboard(CallbackActions::CATALOG_ALIASES_PREFIX . $storeId));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function processAliasInput(TelegramFamilyMemberContext $context, array $data, string $value): void
    {
        try {
            $name = CatalogName::fromString($value);
        } catch (InvalidValueException $e) {
            $this->utils->render($context, ($this->exceptionToTranslation)($e), $this->utils->backKeyboard(CallbackActions::CATALOG_ALIASES_PREFIX . ($data['store_id'] ?? '')));
            return;
        }
        $familyId = $context->activeFamily->getId();
        $storeId = isset($data['store_id']) ? $this->utils->parseUuid((string) $data['store_id']) : null;
        if ($storeId === null || $this->catalog->findStore($familyId, new StoreId($storeId)) === null) {
            $this->storeScreen->renderList($context, 0, 'catalog.unavailable');
            return;
        }

        $canonical = $this->catalog->findStoreByDedupeKey($familyId, $name->getDedupeKey());
        $aliasId = isset($data['alias_id']) ? $this->utils->parseUuid((string) $data['alias_id']) : null;
        $existingAlias = $this->catalog->findStoreAliasByDedupeKey(
            $familyId,
            $name->getDedupeKey(),
            $aliasId === null ? null : new StoreAliasId($aliasId)
        );
        if ($canonical !== null || $existingAlias !== null) {
            $existingName = $canonical !== null ? (string) $canonical->getName() : (string) $existingAlias->getName();
            $conflictingStoreId = $canonical?->getId() ?? $existingAlias?->getStoreId() ?? $storeId;
            $data['value'] = $name->getValue();
            $this->utils->getStateRepository()->set($this->utils->state($context, 'alias_conflict', $data));
            $builder = new InlineKeyboardBuilder()
                ->button($this->utils->trans('catalog.open_conflict'), CallbackActions::CATALOG_PREFIX . 'warning:open:' . $conflictingStoreId)->row()
                ->button($this->utils->trans('catalog.edit_name'), CallbackActions::CATALOG_ALIAS_CONFLICT_EDIT)->row()
                ->button($this->utils->trans('common.back'), CallbackActions::CATALOG_ALIASES_PREFIX . $storeId);
            $this->utils->render(
                $context,
                $this->utils->trans('catalog.alias_conflict', ['%{name}' => $this->utils->escape($existingName)]),
                $builder->toArray()
            );
            return;
        }

        try {
            if ($aliasId !== null) {
                $this->utils->runCommand(new RenameStoreAliasCommand(
                    $familyId,
                    new StoreId($storeId),
                    new StoreAliasId($aliasId),
                    $context->user->getId(),
                    $name->getValue()
                ));
            } else {
                $this->utils->runCommand(new AddStoreAliasCommand(
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
            $this->utils->render(
                $context,
                $this->utils->trans('catalog.alias_conflict', ['%{name}' => $this->utils->escape($exception->conflictingName)]),
                $this->utils->backKeyboard(CallbackActions::CATALOG_ALIASES_PREFIX . $storeId)
            );
            return;
        }
        $this->renderAliases($context, $storeId, 'catalog.alias_saved');
    }
}

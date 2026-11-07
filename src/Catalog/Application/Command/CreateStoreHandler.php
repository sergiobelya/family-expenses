<?php

namespace App\Catalog\Application\Command;

use App\Catalog\Application\CatalogAccess;
use App\Catalog\Domain\Entity\Store;
use App\Catalog\Domain\Exception\CatalogNameConflictException;
use App\Catalog\Domain\Repository\CatalogRepositoryInterface;
use App\Catalog\Domain\ValueObject\CatalogName;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class CreateStoreHandler
{
    public function __construct(
        private CatalogRepositoryInterface $catalog,
        private CatalogAccess $access
    ) {
    }

    public function __invoke(CreateStoreCommand $command): Store
    {
        $name = CatalogName::fromString($command->name);
        $this->access->lockFamilyForMemberMutation($command->familyId, $command->createdByUserId);
        $this->assertNameAvailable($command->familyId, $name->dedupeKey);

        $store = Store::create($command->familyId, $name->value, $command->createdByUserId);
        $this->catalog->saveStore($store);

        return $store;
    }

    private function assertNameAvailable(\Symfony\Component\Uid\Uuid $familyId, string $dedupeKey): void
    {
        if ($store = $this->catalog->findStoreByDedupeKey($familyId, $dedupeKey)) {
            throw new CatalogNameConflictException('store', $store->getName());
        }
        if ($alias = $this->catalog->findStoreAliasByDedupeKey($familyId, $dedupeKey)) {
            throw new CatalogNameConflictException('store alias', $alias->getAlias());
        }
    }
}

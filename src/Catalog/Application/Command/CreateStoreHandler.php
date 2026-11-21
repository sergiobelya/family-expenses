<?php

namespace App\Catalog\Application\Command;

use App\Catalog\Application\CatalogAccess;
use App\Catalog\Domain\Entity\Store;
use App\Catalog\Domain\Exception\CatalogNameConflictException;
use App\Catalog\Domain\Repository\StoreCatalogRepositoryInterface;
use App\Catalog\Domain\ValueObject\StoreName;
use App\Core\Domain\ValueObject\FamilyId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateStoreHandler
{
    public function __construct(
        private StoreCatalogRepositoryInterface $catalog,
        private CatalogAccess $access
    ) {
    }

    public function __invoke(CreateStoreCommand $command): Store
    {
        $name = StoreName::fromString($command->name);
        $this->access->lockFamilyForMemberMutation($command->familyId, $command->createdByUserId);
        $familyId = $command->familyId;
        $this->assertNameAvailable($familyId, $name->getDedupeKey());

        $store = Store::create($familyId, $name, $command->createdByUserId);
        $this->catalog->saveStore($store);

        return $store;
    }

    private function assertNameAvailable(FamilyId $familyId, string $dedupeKey): void
    {
        if ($store = $this->catalog->findStoreByDedupeKey($familyId, $dedupeKey)) {
            throw new CatalogNameConflictException('store', (string) $store->getName());
        }
        if ($alias = $this->catalog->findStoreAliasByDedupeKey($familyId, $dedupeKey)) {
            throw new CatalogNameConflictException('store alias', (string) $alias->getName());
        }
    }
}

<?php

namespace App\Tests\Unit\Domain\Catalog\Entity;

use App\Catalog\Domain\Entity\Store;
use App\Catalog\Domain\Entity\StoreAlias;
use App\Catalog\Domain\Event\StoreAliasCreated;
use App\Catalog\Domain\Event\StoreAliasDeleted;
use App\Catalog\Domain\Event\StoreAliasRenamed;
use App\Catalog\Domain\Event\StoreCreated;
use App\Catalog\Domain\Event\StoreDeleted;
use App\Catalog\Domain\Exception\CatalogEntryNotFoundException;
use App\Catalog\Domain\ValueObject\StoreName;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\StoreAliasId;
use App\Core\Domain\ValueObject\StoreId;
use App\Core\Domain\ValueObject\UserId;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

class StoreTest extends TestCase
{
    private function createStore(?StoreId $id = null): Store
    {
        return Store::create(
            familyId: FamilyId::generate(),
            name: StoreName::fromString('Auchan'),
            createdByUserId: UserId::generate(),
            id: $id,
            now: new DateTimeImmutable('2026-01-01', new DateTimeZone('UTC'))
        );
    }

    public function testCreateRecordsEvent(): void
    {
        $store = $this->createStore();

        $events = array_values(iterator_to_array($store->releaseEvents()));
        $this->assertCount(1, $events);
        $this->assertInstanceOf(StoreCreated::class, $events[0]);
    }

    public function testAddAliasRecordsEvent(): void
    {
        $store = $this->createStore();
        iterator_to_array($store->releaseEvents()); // clear create event

        $alias = $store->addAlias(
            StoreName::fromString('ashan'),
            UserId::generate(),
            new DateTimeImmutable('2026-01-02', new DateTimeZone('UTC'))
        );

        $this->assertInstanceOf(StoreAlias::class, $alias);
        $this->assertSame('ashan', (string) $alias->getName());

        $events = array_values(iterator_to_array($store->releaseEvents()));
        $this->assertCount(1, $events);
        $this->assertInstanceOf(StoreAliasCreated::class, $events[0]);
        $this->assertSame((string) $alias->getId(), $events[0]->getEntityId());
    }

    public function testGetAliasesReturnsAddedAliases(): void
    {
        $store = $this->createStore();
        $store->addAlias(StoreName::fromString('ashan'), UserId::generate());
        $store->addAlias(StoreName::fromString('auchan-pl'), UserId::generate());

        $aliases = $store->getAliases();
        $this->assertCount(2, $aliases);
        $this->assertInstanceOf(StoreAlias::class, $aliases[0]);
        $this->assertInstanceOf(StoreAlias::class, $aliases[1]);
    }

    public function testHasAliasReturnsTrueForExistingAlias(): void
    {
        $store = $this->createStore();
        $alias = $store->addAlias(StoreName::fromString('ashan'), UserId::generate());

        $this->assertTrue($store->hasAlias($alias->getId()));
    }

    public function testHasAliasReturnsFalseForNonExistingAlias(): void
    {
        $store = $this->createStore();

        $this->assertFalse($store->hasAlias(StoreAliasId::generate()));
    }

    public function testRenameAliasReturnsRenamedAlias(): void
    {
        $store = $this->createStore();
        $alias = $store->addAlias(StoreName::fromString('ashan'), UserId::generate());
        iterator_to_array($store->releaseEvents());

        $renamed = $store->renameAlias(
            $alias->getId(),
            StoreName::fromString('ashan-new'),
            UserId::generate()
        );

        $this->assertSame($alias, $renamed);
        $this->assertSame('ashan-new', (string) $renamed->getName());
    }

    public function testRenameAliasRecordsEvent(): void
    {
        $store = $this->createStore();
        $alias = $store->addAlias(StoreName::fromString('ashan'), UserId::generate());
        iterator_to_array($store->releaseEvents());

        $store->renameAlias(
            $alias->getId(),
            StoreName::fromString('ashan-new'),
            UserId::generate()
        );

        $events = array_values(iterator_to_array($store->releaseEvents()));
        $this->assertCount(1, $events);
        $this->assertInstanceOf(StoreAliasRenamed::class, $events[0]);
        $this->assertSame((string) $alias->getId(), $events[0]->getEntityId());
    }

    public function testRenameAliasSkipsEventWhenNameUnchanged(): void
    {
        $store = $this->createStore();
        $alias = $store->addAlias(StoreName::fromString('ashan'), UserId::generate());
        iterator_to_array($store->releaseEvents());

        $store->renameAlias(
            $alias->getId(),
            StoreName::fromString('ashan'),
            UserId::generate()
        );

        $events = array_values(iterator_to_array($store->releaseEvents()));
        $this->assertCount(0, $events);
    }

    public function testRenameAliasThrowsWhenAliasNotFound(): void
    {
        $store = $this->createStore();

        $this->expectException(CatalogEntryNotFoundException::class);
        $store->renameAlias(
            StoreAliasId::generate(),
            StoreName::fromString('new-name'),
            UserId::generate()
        );
    }

    public function testRemoveAliasRecordsEvent(): void
    {
        $store = $this->createStore();
        $alias = $store->addAlias(StoreName::fromString('ashan'), UserId::generate());
        $aliasId = $alias->getId();
        iterator_to_array($store->releaseEvents());

        $store->removeAlias($aliasId, UserId::generate());

        $events = array_values(iterator_to_array($store->releaseEvents()));
        $this->assertCount(1, $events);
        $this->assertInstanceOf(StoreAliasDeleted::class, $events[0]);
        $this->assertSame((string) $aliasId, $events[0]->getEntityId());
    }

    public function testRemoveAliasRemovesFromCollection(): void
    {
        $store = $this->createStore();
        $alias = $store->addAlias(StoreName::fromString('ashan'), UserId::generate());
        iterator_to_array($store->releaseEvents());

        $store->removeAlias($alias->getId(), UserId::generate());

        $this->assertCount(0, $store->getAliases());
        $this->assertFalse($store->hasAlias($alias->getId()));
    }

    public function testRemoveAliasThrowsWhenAliasNotFound(): void
    {
        $store = $this->createStore();

        $this->expectException(CatalogEntryNotFoundException::class);
        $store->removeAlias(StoreAliasId::generate(), UserId::generate());
    }

    public function testDeleteRecordsAliasDeletedEvents(): void
    {
        $store = $this->createStore();
        $alias1 = $store->addAlias(StoreName::fromString('ashan'), UserId::generate());
        $alias2 = $store->addAlias(StoreName::fromString('auchan-pl'), UserId::generate());
        iterator_to_array($store->releaseEvents());

        $store->delete(UserId::generate());

        $events = array_values(iterator_to_array($store->releaseEvents()));
        $this->assertCount(3, $events); // 2 StoreAliasDeleted + 1 StoreDeleted
        $this->assertInstanceOf(StoreAliasDeleted::class, $events[0]);
        $this->assertSame((string) $alias1->getId(), $events[0]->getEntityId());
        $this->assertInstanceOf(StoreAliasDeleted::class, $events[1]);
        $this->assertSame((string) $alias2->getId(), $events[1]->getEntityId());
        $this->assertInstanceOf(StoreDeleted::class, $events[2]);
    }

    public function testDeleteClearsAliases(): void
    {
        $store = $this->createStore();
        $store->addAlias(StoreName::fromString('ashan'), UserId::generate());
        iterator_to_array($store->releaseEvents());

        $store->delete(UserId::generate());

        $this->assertCount(0, $store->getAliases());
    }
}

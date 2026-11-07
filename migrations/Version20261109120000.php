<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261109120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add family-scoped stores, store aliases, and categories.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE categories (id UUID NOT NULL, family_id UUID NOT NULL, name VARCHAR(100) NOT NULL, dedupe_key VARCHAR(500) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_categories_family_name ON categories (family_id, name)');
        $this->addSql('CREATE UNIQUE INDEX uniq_categories_family_dedupe ON categories (family_id, dedupe_key)');
        $this->addSql('CREATE UNIQUE INDEX uniq_categories_family_id_id ON categories (family_id, id)');
        $this->addSql('ALTER TABLE categories ADD CONSTRAINT fk_categories_family FOREIGN KEY (family_id) REFERENCES families (id) ON DELETE CASCADE');
        $this->addSql('CREATE TABLE stores (id UUID NOT NULL, family_id UUID NOT NULL, name VARCHAR(100) NOT NULL, dedupe_key VARCHAR(500) NOT NULL, default_category_id UUID DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_stores_family_name ON stores (family_id, name)');
        $this->addSql('CREATE UNIQUE INDEX uniq_stores_family_dedupe ON stores (family_id, dedupe_key)');
        $this->addSql('CREATE UNIQUE INDEX uniq_stores_family_id_id ON stores (family_id, id)');
        $this->addSql('ALTER TABLE stores ADD CONSTRAINT fk_stores_family FOREIGN KEY (family_id) REFERENCES families (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE stores ADD CONSTRAINT fk_stores_default_category FOREIGN KEY (family_id, default_category_id) REFERENCES categories (family_id, id) ON DELETE RESTRICT');
        $this->addSql('CREATE TABLE store_aliases (id UUID NOT NULL, family_id UUID NOT NULL, store_id UUID NOT NULL, alias VARCHAR(100) NOT NULL, alias_dedupe_key VARCHAR(500) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_store_aliases_family_store ON store_aliases (family_id, store_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_store_aliases_family_dedupe ON store_aliases (family_id, alias_dedupe_key)');
        $this->addSql('ALTER TABLE store_aliases ADD CONSTRAINT fk_store_aliases_family FOREIGN KEY (family_id) REFERENCES families (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE store_aliases ADD CONSTRAINT fk_store_aliases_store FOREIGN KEY (family_id, store_id) REFERENCES stores (family_id, id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE store_aliases');
        $this->addSql('ALTER TABLE stores DROP CONSTRAINT fk_stores_default_category');
        $this->addSql('DROP TABLE stores');
        $this->addSql('DROP TABLE categories');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261115231200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enable pg_trgm extension and add GIN indexes for fuzzy catalog search.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        $this->addSql('CREATE INDEX idx_stores_dedupe_key_trgm ON stores USING GIN (dedupe_key gin_trgm_ops)');
        $this->addSql('CREATE INDEX idx_categories_dedupe_key_trgm ON categories USING GIN (dedupe_key gin_trgm_ops)');
        $this->addSql('CREATE INDEX idx_store_aliases_alias_dedupe_key_trgm ON store_aliases USING GIN (alias_dedupe_key gin_trgm_ops)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_store_aliases_alias_dedupe_key_trgm');
        $this->addSql('DROP INDEX idx_categories_dedupe_key_trgm');
        $this->addSql('DROP INDEX idx_stores_dedupe_key_trgm');
        $this->addSql('DROP EXTENSION IF EXISTS pg_trgm');
    }
}

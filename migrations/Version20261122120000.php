<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add expenses and expense_allocations tables for Phase 2.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE expenses (id UUID NOT NULL, family_id UUID NOT NULL, author_user_id UUID NOT NULL, store_id UUID NOT NULL, amount VARCHAR(20) NOT NULL, currency VARCHAR(3) NOT NULL, purchase_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_expenses_family_purchase_date ON expenses (family_id, purchase_date)');
        $this->addSql('CREATE UNIQUE INDEX uniq_expenses_family_id_id ON expenses (family_id, id)');
        $this->addSql('ALTER TABLE expenses ADD CONSTRAINT fk_expenses_family FOREIGN KEY (family_id) REFERENCES families (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE expenses ADD CONSTRAINT fk_expenses_store FOREIGN KEY (store_id) REFERENCES stores (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE expenses ADD CONSTRAINT fk_expenses_author FOREIGN KEY (author_user_id) REFERENCES users (id) ON DELETE RESTRICT');
        $this->addSql('CREATE TABLE expense_allocations (id UUID NOT NULL, family_id UUID NOT NULL, category_id UUID NOT NULL, amount VARCHAR(20) NOT NULL, currency VARCHAR(3) NOT NULL, expense_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_expense_allocations_expense ON expense_allocations (expense_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_expense_allocations_family_id_id ON expense_allocations (family_id, id)');
        $this->addSql('ALTER TABLE expense_allocations ADD CONSTRAINT fk_expense_allocations_family FOREIGN KEY (family_id) REFERENCES families (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE expense_allocations ADD CONSTRAINT fk_expense_allocations_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE expense_allocations ADD CONSTRAINT fk_expense_allocations_expense FOREIGN KEY (expense_id) REFERENCES expenses (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE expense_allocations DROP CONSTRAINT fk_expense_allocations_expense');
        $this->addSql('ALTER TABLE expense_allocations DROP CONSTRAINT fk_expense_allocations_category');
        $this->addSql('ALTER TABLE expense_allocations DROP CONSTRAINT fk_expense_allocations_family');
        $this->addSql('DROP TABLE expense_allocations');
        $this->addSql('ALTER TABLE expenses DROP CONSTRAINT fk_expenses_author');
        $this->addSql('ALTER TABLE expenses DROP CONSTRAINT fk_expenses_store');
        $this->addSql('ALTER TABLE expenses DROP CONSTRAINT fk_expenses_family');
        $this->addSql('DROP TABLE expenses');
    }
}

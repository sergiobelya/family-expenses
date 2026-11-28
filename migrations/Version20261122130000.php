<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add expense_save_attempts table for idempotency and save recovery.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE expense_save_attempts (id UUID NOT NULL, user_id UUID NOT NULL, family_id UUID NOT NULL, idempotency_key VARCHAR(32) NOT NULL, expense_id UUID DEFAULT NULL, status VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX idx_expense_save_attempts_idempotency_key ON expense_save_attempts (idempotency_key)');
        $this->addSql('CREATE INDEX idx_expense_save_attempts_user_family ON expense_save_attempts (user_id, family_id)');
        $this->addSql('ALTER TABLE expense_save_attempts ADD CONSTRAINT fk_expense_save_attempts_family FOREIGN KEY (family_id) REFERENCES families (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE expense_save_attempts ADD CONSTRAINT fk_expense_save_attempts_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE expense_save_attempts ADD CONSTRAINT fk_expense_save_attempts_expense FOREIGN KEY (expense_id) REFERENCES expenses (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE expense_save_attempts DROP CONSTRAINT fk_expense_save_attempts_expense');
        $this->addSql('ALTER TABLE expense_save_attempts DROP CONSTRAINT fk_expense_save_attempts_user');
        $this->addSql('ALTER TABLE expense_save_attempts DROP CONSTRAINT fk_expense_save_attempts_family');
        $this->addSql('DROP TABLE expense_save_attempts');
    }
}

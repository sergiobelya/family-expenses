<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106184000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add retry metadata to outbox messages.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE outbox_messages ADD attempt_count INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE outbox_messages ADD next_attempt_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE outbox_messages ADD last_error TEXT DEFAULT NULL');
        $this->addSql('UPDATE outbox_messages SET next_attempt_at = created_at WHERE next_attempt_at IS NULL');
        $this->addSql('ALTER TABLE outbox_messages ALTER next_attempt_at SET NOT NULL');
        $this->addSql('DROP INDEX idx_outbox_messages_status');
        $this->addSql('DROP INDEX idx_outbox_messages_created_at');
        $this->addSql('CREATE INDEX idx_outbox_messages_ready ON outbox_messages (status, next_attempt_at, created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_outbox_messages_ready');
        $this->addSql('CREATE INDEX idx_outbox_messages_status ON outbox_messages (status)');
        $this->addSql('CREATE INDEX idx_outbox_messages_created_at ON outbox_messages (created_at)');
        $this->addSql('ALTER TABLE outbox_messages DROP attempt_count');
        $this->addSql('ALTER TABLE outbox_messages DROP next_attempt_at');
        $this->addSql('ALTER TABLE outbox_messages DROP last_error');
    }
}

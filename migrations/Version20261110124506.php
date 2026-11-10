<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261110124506 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE products ADD created_by INT DEFAULT NULL');
        $this->addSql('ALTER TABLE products ADD CONSTRAINT FK_B3BA5A5ADE12AB56 FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_B3BA5A5ADE12AB56 ON products (created_by)');
        $this->addSql('ALTER TABLE users ADD is_active TINYINT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE products DROP FOREIGN KEY FK_B3BA5A5ADE12AB56');
        $this->addSql('DROP INDEX IDX_B3BA5A5ADE12AB56 ON products');
        $this->addSql('ALTER TABLE products DROP created_by');
        $this->addSql('ALTER TABLE users DROP is_active');
    }
}

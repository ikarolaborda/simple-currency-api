<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Create currency_rate table.
 */
final class Version20250421204524 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates the currency_rate table for storing exchange rates';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE currency_rate (
    id INT AUTO_INCREMENT NOT NULL,
    base_currency VARCHAR(3) NOT NULL,
    target_currency VARCHAR(3) NOT NULL,
    rate NUMERIC(12, 6) NOT NULL,
    fetched_at DATETIME NOT NULL,
    INDEX IDX_CURRENCY_RATE_BASE (base_currency),
    INDEX IDX_CURRENCY_RATE_TARGET (target_currency),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL
        );
    }

    public function down(Schema $schema): void
    {
        // Drop currency_rate table
        $this->addSql('DROP TABLE currency_rate');
    }
}
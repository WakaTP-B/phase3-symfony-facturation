<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260512124826 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__client AS SELECT id, name, email, phone, address, siret, rib FROM client');
        $this->addSql('DROP TABLE client');
        $this->addSql('CREATE TABLE client (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(180) NOT NULL, phone VARCHAR(20) NOT NULL, address VARCHAR(255) NOT NULL, siret VARCHAR(14) DEFAULT NULL, rib VARCHAR(34) NOT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_C7440455A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO client (id, name, email, phone, address, siret, rib) SELECT id, name, email, phone, address, siret, rib FROM __temp__client');
        $this->addSql('DROP TABLE __temp__client');
        $this->addSql('CREATE INDEX IDX_C7440455A76ED395 ON client (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__client AS SELECT id, name, email, phone, address, siret, rib FROM client');
        $this->addSql('DROP TABLE client');
        $this->addSql('CREATE TABLE client (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(180) NOT NULL, phone VARCHAR(20) NOT NULL, address VARCHAR(255) NOT NULL, siret VARCHAR(14) DEFAULT NULL, rib VARCHAR(34) NOT NULL)');
        $this->addSql('INSERT INTO client (id, name, email, phone, address, siret, rib) SELECT id, name, email, phone, address, siret, rib FROM __temp__client');
        $this->addSql('DROP TABLE __temp__client');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260217230130 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE customer_materials (id VARCHAR(36) NOT NULL, price NUMERIC(13, 2) DEFAULT NULL, currency VARCHAR(3) DEFAULT NULL, price_unit VARCHAR(3) DEFAULT NULL, weight NUMERIC(13, 3) DEFAULT NULL, weight_unit VARCHAR(3) DEFAULT NULL, volume NUMERIC(13, 3) DEFAULT NULL, volume_unit VARCHAR(3) DEFAULT NULL, is_available TINYINT NOT NULL, minimum_order_quantity INT DEFAULT NULL, availability_days INT DEFAULT NULL, sap_price_data JSON DEFAULT NULL, price_updated_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, customer_id VARCHAR(36) NOT NULL, material_id VARCHAR(36) NOT NULL, INDEX idx_customer (customer_id), INDEX idx_material (material_id), UNIQUE INDEX customer_material_unique (customer_id, material_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE customers (id VARCHAR(36) NOT NULL, sap_customer_id VARCHAR(10) NOT NULL, sales_org VARCHAR(4) NOT NULL, name1 VARCHAR(255) NOT NULL, name2 VARCHAR(255) DEFAULT NULL, street VARCHAR(255) DEFAULT NULL, city VARCHAR(100) DEFAULT NULL, postal_code VARCHAR(10) DEFAULT NULL, region VARCHAR(3) DEFAULT NULL, country VARCHAR(2) NOT NULL, currency VARCHAR(3) DEFAULT NULL, incoterms VARCHAR(20) DEFAULT NULL, shipping_condition VARCHAR(20) DEFAULT NULL, payment_terms VARCHAR(10) DEFAULT NULL, tax_class VARCHAR(20) DEFAULT NULL, vat_number VARCHAR(20) DEFAULT NULL, sap_data JSON DEFAULT NULL, last_sync_at DATETIME NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX idx_customer_sap (sap_customer_id, sales_org), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE materials (id VARCHAR(36) NOT NULL, sap_material_number VARCHAR(18) NOT NULL, description VARCHAR(255) NOT NULL, description_short VARCHAR(40) DEFAULT NULL, material_type VARCHAR(10) DEFAULT NULL, material_group VARCHAR(10) DEFAULT NULL, base_unit VARCHAR(3) DEFAULT NULL, weight NUMERIC(13, 3) DEFAULT NULL, weight_unit VARCHAR(3) DEFAULT NULL, volume NUMERIC(13, 3) DEFAULT NULL, volume_unit VARCHAR(3) DEFAULT NULL, is_active TINYINT NOT NULL, sap_data JSON DEFAULT NULL, last_sync_at DATETIME NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_9B1716B5FB7F66E4 (sap_material_number), INDEX idx_material_sap (sap_material_number), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE orders (id VARCHAR(36) NOT NULL, customer_name VARCHAR(255) NOT NULL, status VARCHAR(20) NOT NULL, total_amount NUMERIC(10, 2) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE customer_materials ADD CONSTRAINT FK_30EAEEAF9395C3F3 FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE customer_materials ADD CONSTRAINT FK_30EAEEAFE308AC6F FOREIGN KEY (material_id) REFERENCES materials (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE customer_materials DROP FOREIGN KEY FK_30EAEEAF9395C3F3');
        $this->addSql('ALTER TABLE customer_materials DROP FOREIGN KEY FK_30EAEEAFE308AC6F');
        $this->addSql('DROP TABLE customer_materials');
        $this->addSql('DROP TABLE customers');
        $this->addSql('DROP TABLE materials');
        $this->addSql('DROP TABLE orders');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240917162631 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE INDEX IDX_92F307BFDCD6CC49 ON product_item (branch_id)');
        $this->addSql('ALTER TABLE product_item_category ADD branch_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE product_item_category ADD CONSTRAINT FK_B0ACCDF8DCD6CC49 FOREIGN KEY (branch_id) REFERENCES security_branch (id)');
        $this->addSql('CREATE INDEX IDX_B0ACCDF8DCD6CC49 ON product_item_category (branch_id)');
        $this->addSql('ALTER TABLE product_unit ADD code VARCHAR(100) NOT NULL');
        $this->addSql('ALTER TABLE school_school ADD branch_id INT DEFAULT NULL, ADD school_branch_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE school_school ADD CONSTRAINT FK_422D977DCD6CC49 FOREIGN KEY (branch_id) REFERENCES security_branch (id)');
        $this->addSql('ALTER TABLE school_school ADD CONSTRAINT FK_422D977AD990FF4 FOREIGN KEY (school_branch_id) REFERENCES security_branch (id)');
        $this->addSql('CREATE INDEX IDX_422D977DCD6CC49 ON school_school (branch_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_422D977AD990FF4 ON school_school (school_branch_id)');
        $this->addSql('ALTER TABLE security_profile ADD year_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE security_profile ADD CONSTRAINT FK_ECBCA11240C1FEA7 FOREIGN KEY (year_id) REFERENCES security_year (id)');
        $this->addSql('CREATE INDEX IDX_ECBCA11240C1FEA7 ON security_profile (year_id)');
        $this->addSql('ALTER TABLE security_user ADD is_branch_manager TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE treasury_bank_account ADD branch_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE treasury_bank_account ADD CONSTRAINT FK_5DDC3997DCD6CC49 FOREIGN KEY (branch_id) REFERENCES security_branch (id)');
        $this->addSql('CREATE INDEX IDX_5DDC3997DCD6CC49 ON treasury_bank_account (branch_id)');
        $this->addSql('ALTER TABLE treasury_bank_history ADD branch_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE treasury_bank_history ADD CONSTRAINT FK_7501F78DCD6CC49 FOREIGN KEY (branch_id) REFERENCES security_branch (id)');
        $this->addSql('CREATE INDEX IDX_7501F78DCD6CC49 ON treasury_bank_history (branch_id)');
        $this->addSql('ALTER TABLE treasury_bank_operation ADD branch_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE treasury_bank_operation ADD CONSTRAINT FK_30E79F7EDCD6CC49 FOREIGN KEY (branch_id) REFERENCES security_branch (id)');
        $this->addSql('CREATE INDEX IDX_30E79F7EDCD6CC49 ON treasury_bank_operation (branch_id)');
        $this->addSql('ALTER TABLE treasury_cash_desk ADD branch_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE treasury_cash_desk ADD CONSTRAINT FK_47BB1259DCD6CC49 FOREIGN KEY (branch_id) REFERENCES security_branch (id)');
        $this->addSql('CREATE INDEX IDX_47BB1259DCD6CC49 ON treasury_cash_desk (branch_id)');
        $this->addSql('ALTER TABLE treasury_cash_desk_history ADD branch_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE treasury_cash_desk_history ADD CONSTRAINT FK_730493D3DCD6CC49 FOREIGN KEY (branch_id) REFERENCES security_branch (id)');
        $this->addSql('CREATE INDEX IDX_730493D3DCD6CC49 ON treasury_cash_desk_history (branch_id)');
        $this->addSql('ALTER TABLE treasury_cash_desk_operation ADD branch_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE treasury_cash_desk_operation ADD CONSTRAINT FK_991A4103DCD6CC49 FOREIGN KEY (branch_id) REFERENCES security_branch (id)');
        $this->addSql('CREATE INDEX IDX_991A4103DCD6CC49 ON treasury_cash_desk_operation (branch_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE product_item DROP FOREIGN KEY FK_92F307BFDCD6CC49');
        $this->addSql('DROP INDEX IDX_92F307BFDCD6CC49 ON product_item');
        $this->addSql('ALTER TABLE product_item_category DROP FOREIGN KEY FK_B0ACCDF8DCD6CC49');
        $this->addSql('DROP INDEX IDX_B0ACCDF8DCD6CC49 ON product_item_category');
        $this->addSql('ALTER TABLE product_item_category DROP branch_id');
        $this->addSql('ALTER TABLE product_unit DROP code');
        $this->addSql('ALTER TABLE school_school DROP FOREIGN KEY FK_422D977DCD6CC49');
        $this->addSql('ALTER TABLE school_school DROP FOREIGN KEY FK_422D977AD990FF4');
        $this->addSql('DROP INDEX IDX_422D977DCD6CC49 ON school_school');
        $this->addSql('DROP INDEX UNIQ_422D977AD990FF4 ON school_school');
        $this->addSql('ALTER TABLE school_school DROP branch_id, DROP school_branch_id');
        $this->addSql('ALTER TABLE security_profile DROP FOREIGN KEY FK_ECBCA11240C1FEA7');
        $this->addSql('DROP INDEX IDX_ECBCA11240C1FEA7 ON security_profile');
        $this->addSql('ALTER TABLE security_profile DROP year_id');
        $this->addSql('ALTER TABLE security_user DROP is_branch_manager');
        $this->addSql('ALTER TABLE treasury_bank_account DROP FOREIGN KEY FK_5DDC3997DCD6CC49');
        $this->addSql('DROP INDEX IDX_5DDC3997DCD6CC49 ON treasury_bank_account');
        $this->addSql('ALTER TABLE treasury_bank_account DROP branch_id');
        $this->addSql('ALTER TABLE treasury_bank_history DROP FOREIGN KEY FK_7501F78DCD6CC49');
        $this->addSql('DROP INDEX IDX_7501F78DCD6CC49 ON treasury_bank_history');
        $this->addSql('ALTER TABLE treasury_bank_history DROP branch_id');
        $this->addSql('ALTER TABLE treasury_bank_operation DROP FOREIGN KEY FK_30E79F7EDCD6CC49');
        $this->addSql('DROP INDEX IDX_30E79F7EDCD6CC49 ON treasury_bank_operation');
        $this->addSql('ALTER TABLE treasury_bank_operation DROP branch_id');
        $this->addSql('ALTER TABLE treasury_cash_desk DROP FOREIGN KEY FK_47BB1259DCD6CC49');
        $this->addSql('DROP INDEX IDX_47BB1259DCD6CC49 ON treasury_cash_desk');
        $this->addSql('ALTER TABLE treasury_cash_desk DROP branch_id');
        $this->addSql('ALTER TABLE treasury_cash_desk_history DROP FOREIGN KEY FK_730493D3DCD6CC49');
        $this->addSql('DROP INDEX IDX_730493D3DCD6CC49 ON treasury_cash_desk_history');
        $this->addSql('ALTER TABLE treasury_cash_desk_history DROP branch_id');
        $this->addSql('ALTER TABLE treasury_cash_desk_operation DROP FOREIGN KEY FK_991A4103DCD6CC49');
        $this->addSql('DROP INDEX IDX_991A4103DCD6CC49 ON treasury_cash_desk_operation');
        $this->addSql('ALTER TABLE treasury_cash_desk_operation DROP branch_id');
    }
}

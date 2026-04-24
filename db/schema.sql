-- SAP Expense Journal Entry System — MySQL schema
-- Run once: mysql -u root -p expense_app < db/schema.sql

CREATE DATABASE IF NOT EXISTS expense_app
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE expense_app;

CREATE TABLE IF NOT EXISTS expense_entries (
    id                   INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    entry_date           DATE              NOT NULL,
    memo                 VARCHAR(255)      NOT NULL,
    ref1                 VARCHAR(100)      NOT NULL DEFAULT '',

    expense_account_code VARCHAR(50)       NOT NULL,
    expense_account_name VARCHAR(200)      NOT NULL DEFAULT '',
    amount               DECIMAL(15, 2)    NOT NULL DEFAULT 0.00,

    vat_account_code     VARCHAR(50)       NOT NULL DEFAULT '',
    vat_account_name     VARCHAR(200)      NOT NULL DEFAULT '',
    vat_amount           DECIMAL(15, 2)    NOT NULL DEFAULT 0.00,

    payment_account_code VARCHAR(50)       NOT NULL,
    payment_account_name VARCHAR(200)      NOT NULL DEFAULT '',
    payment_type         ENUM('bank','petty_cash') NOT NULL DEFAULT 'bank',
    total_amount         DECIMAL(15, 2)    NOT NULL DEFAULT 0.00,

    receipt_path         VARCHAR(500)               DEFAULT NULL,

    sap_doc_entry        INT                        DEFAULT NULL COMMENT 'JdtNum from SAP B1',
    sap_doc_num          INT                        DEFAULT NULL COMMENT 'DocNumber from SAP B1',

    status               ENUM('pending','posted','failed') NOT NULL DEFAULT 'pending',
    error_message        TEXT                       DEFAULT NULL,

    created_at           TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_entry_date  (entry_date),
    INDEX idx_status      (status),
    INDEX idx_sap_doc     (sap_doc_entry)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

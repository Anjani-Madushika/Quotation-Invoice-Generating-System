-- Quotation System database (Discount + VAT, item units, invoice snapshot, deactivate users)
CREATE DATABASE IF NOT EXISTS quotation_system
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE quotation_system;

-- COMPANY
CREATE TABLE IF NOT EXISTS company (
  company_id     INT AUTO_INCREMENT PRIMARY KEY,
  company_name   VARCHAR(150) NOT NULL,
  address        VARCHAR(255) NULL,
  phone_no       VARCHAR(30)  NULL,
  bank_name      VARCHAR(100) NULL,
  account_holder VARCHAR(100) NULL,
  account_no     VARCHAR(50)  NULL,
  logo           MEDIUMTEXT   NULL,          -- company logo as a data URI (shown on quotations + invoices)
  theme_color    VARCHAR(7)   NULL,          -- document colour, e.g. #12306b (NULL = default navy)
  doc_layout     VARCHAR(20)  NOT NULL DEFAULT 'modern',  -- document design: modern / classic / minimal
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- USER  (COMPANY has USER)
CREATE TABLE IF NOT EXISTS `user` (
  user_id    INT AUTO_INCREMENT PRIMARY KEY,
  company_id INT NOT NULL,
  name       VARCHAR(100) NOT NULL,
  email      VARCHAR(150) NOT NULL UNIQUE,
  password   VARCHAR(255) NOT NULL,
  user_type  ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  is_active  TINYINT(1) NOT NULL DEFAULT 1,   -- 0 = deactivated: cannot sign in, but everything they made is kept
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_user_company FOREIGN KEY (company_id)
    REFERENCES company(company_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- PRODUCT or SERVICES  (COMPANY manage PRODUCT)
CREATE TABLE IF NOT EXISTS product_service (
  item_id    INT AUTO_INCREMENT PRIMARY KEY,
  company_id INT NOT NULL,
  name       VARCHAR(150) NOT NULL,
  price      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  unit       VARCHAR(10) NOT NULL DEFAULT '',   -- what the price is per: '' (none), pcs, kg, g, l, ml, m ...
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_item_company FOREIGN KEY (company_id)
    REFERENCES company(company_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- QUOTATION  (USER creates QUOTATION)
CREATE TABLE IF NOT EXISTS quotation (
  quotation_id   INT AUTO_INCREMENT PRIMARY KEY,
  user_id        INT NOT NULL,
  company_id     INT NOT NULL,              -- the company the quotation belongs to
  quotation_no   INT NOT NULL,              -- counts 1, 2, 3 ... for the whole company (all users share one series)
  recipient_name VARCHAR(150) NOT NULL,
  recipient_address TEXT NULL,
  recipient_phone VARCHAR(30) NULL,         -- customer contact number
  quotation_date DATE NOT NULL,
  subtotal         DECIMAL(12,2) NOT NULL DEFAULT 0.00,   -- sum of (unit price x qty)
  discount_percent DECIMAL(5,2)  NOT NULL DEFAULT 0.00,   -- e.g. 10 = 10%
  discount_amount  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  vat_percent      DECIMAL(5,2)  NOT NULL DEFAULT 0.00,   -- e.g. 18 = 18% (charged AFTER the discount)
  vat_amount       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total          DECIMAL(12,2) NOT NULL DEFAULT 0.00,   -- subtotal - discount + VAT
  status         ENUM('draft','sent','rejected') NOT NULL DEFAULT 'draft',
  special_notice TEXT NULL,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_quotation_company_no (company_id, quotation_no),
  INDEX idx_quotation_status (status),
  INDEX idx_quotation_date (quotation_date),
  CONSTRAINT fk_quotation_user FOREIGN KEY (user_id)
    REFERENCES `user`(user_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_quotation_company FOREIGN KEY (company_id)
    REFERENCES company(company_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- QUOTATION_ITEM  (QUOTATION contains QUOTATION_ITEM)
CREATE TABLE IF NOT EXISTS quotation_item (
  quotation_item_id INT AUTO_INCREMENT PRIMARY KEY,
  quotation_id      INT NOT NULL,
  item_id           INT NULL,
  name              VARCHAR(150) NOT NULL,
  description       TEXT NULL,
  unit_price        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  quantity          DECIMAL(12,3) NOT NULL DEFAULT 1.000,   -- decimals allowed for kg, g, l ... (e.g. 2.5)
  unit              VARCHAR(10) NOT NULL DEFAULT '',        -- '' (none), pcs, kg, g, l, ml, m ...
  CONSTRAINT fk_qitem_quotation FOREIGN KEY (quotation_id)
    REFERENCES quotation(quotation_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_qitem_item FOREIGN KEY (item_id)
    REFERENCES product_service(item_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- INVOICE  (QUOTATION generates INVOICE)
CREATE TABLE IF NOT EXISTS invoice (
  invoice_id   INT AUTO_INCREMENT PRIMARY KEY,
  company_id   INT NOT NULL,                -- the company the invoice belongs to
  invoice_no   INT NOT NULL DEFAULT 1,      -- counts 1, 2, 3 ... for the whole company (all users share one series)
  quotation_id INT NOT NULL,
  invoice_date DATE NOT NULL,
  subtotal         DECIMAL(12,2) NOT NULL DEFAULT 0.00,   -- copied from the quotation when the invoice is made
  discount_percent DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  discount_amount  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  vat_percent      DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  vat_amount       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  -- the invoice keeps its own copy of the customer details + notice (the quotation can be edited later without changing it)
  recipient_name    VARCHAR(150) NULL,
  recipient_address TEXT NULL,
  recipient_phone   VARCHAR(30)  NULL,
  special_notice    TEXT NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_invoice_quotation (quotation_id),
  UNIQUE KEY uq_invoice_company_no (company_id, invoice_no),
  CONSTRAINT fk_invoice_quotation FOREIGN KEY (quotation_id)
    REFERENCES quotation(quotation_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_invoice_company FOREIGN KEY (company_id)
    REFERENCES company(company_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- INVOICE_ITEM  (INVOICE keeps a copy of its items, made when the invoice is generated)
CREATE TABLE IF NOT EXISTS invoice_item (
  invoice_item_id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id      INT NOT NULL,
  name            VARCHAR(150) NOT NULL,
  description     TEXT NULL,
  unit_price      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  quantity        DECIMAL(12,3) NOT NULL DEFAULT 1.000,
  unit            VARCHAR(10) NOT NULL DEFAULT '',
  CONSTRAINT fk_iitem_invoice FOREIGN KEY (invoice_id)
    REFERENCES invoice(invoice_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Default company (the first account is created on the Sign up page: register.php)
INSERT INTO company (company_name, address, phone_no, bank_name, account_holder, account_no)
SELECT 'My Company', 'Colombo, Sri Lanka', '0112345678', 'Bank Name', 'Account Holder', '000000000'
WHERE NOT EXISTS (SELECT 1 FROM company);

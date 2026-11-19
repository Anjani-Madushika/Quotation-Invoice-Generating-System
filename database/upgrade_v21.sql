-- UPGRADE to v21 for an EXISTING database (optional).
-- You do NOT have to run this: the first page you open adds all of this automatically (config/db.php).
-- Use it only if you prefer phpMyAdmin. Run once (XAMPP / MariaDB). MySQL 8 users: skip it and let the app do it.
USE quotation_system;

-- 1) Deactivate users instead of deleting them
ALTER TABLE `user` ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER user_type;

-- 2) Units (kg, g, pcs ...) and decimal quantities
ALTER TABLE product_service ADD COLUMN IF NOT EXISTS unit VARCHAR(10) NOT NULL DEFAULT '' AFTER price;
ALTER TABLE quotation_item  ADD COLUMN IF NOT EXISTS unit VARCHAR(10) NOT NULL DEFAULT '' AFTER quantity;
ALTER TABLE quotation_item  MODIFY quantity DECIMAL(12,3) NOT NULL DEFAULT 1.000;

-- 3) Invoice keeps its own copy of the customer details ...
ALTER TABLE invoice
  ADD COLUMN IF NOT EXISTS recipient_name    VARCHAR(150) NULL,
  ADD COLUMN IF NOT EXISTS recipient_address TEXT NULL,
  ADD COLUMN IF NOT EXISTS recipient_phone   VARCHAR(30) NULL,
  ADD COLUMN IF NOT EXISTS special_notice    TEXT NULL;

UPDATE invoice i JOIN quotation q ON q.quotation_id = i.quotation_id
SET i.recipient_name = q.recipient_name, i.recipient_address = q.recipient_address,
    i.recipient_phone = q.recipient_phone, i.special_notice = q.special_notice
WHERE i.recipient_name IS NULL;

-- 4) ... and of its items
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

-- Existing invoices: copy the items they show today (only invoices that have no copy yet)
INSERT INTO invoice_item (invoice_id, name, description, unit_price, quantity, unit)
SELECT i.invoice_id, qi.name, qi.description, qi.unit_price, qi.quantity, qi.unit
FROM invoice i JOIN quotation_item qi ON qi.quotation_id = i.quotation_id
WHERE NOT EXISTS (SELECT 1 FROM invoice_item x WHERE x.invoice_id = i.invoice_id)
ORDER BY i.invoice_id, qi.quotation_item_id;

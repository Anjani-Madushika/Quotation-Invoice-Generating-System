-- UPGRADE for an EXISTING database: adds Discount + VAT.
-- Run this once in phpMyAdmin (SQL tab). Safe to run again (XAMPP / MariaDB).
-- If you use MySQL 8 and see "IF NOT EXISTS" errors, skip this file: the app
-- adds the same columns automatically the first time any page opens (config/db.php).
USE quotation_system;

ALTER TABLE quotation
  ADD COLUMN IF NOT EXISTS subtotal         DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER quotation_date,
  ADD COLUMN IF NOT EXISTS discount_percent DECIMAL(5,2)  NOT NULL DEFAULT 0.00 AFTER subtotal,
  ADD COLUMN IF NOT EXISTS discount_amount  DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER discount_percent,
  ADD COLUMN IF NOT EXISTS vat_percent      DECIMAL(5,2)  NOT NULL DEFAULT 0.00 AFTER discount_amount,
  ADD COLUMN IF NOT EXISTS vat_amount       DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER vat_percent;

ALTER TABLE invoice
  ADD COLUMN IF NOT EXISTS subtotal         DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER invoice_date,
  ADD COLUMN IF NOT EXISTS discount_percent DECIMAL(5,2)  NOT NULL DEFAULT 0.00 AFTER subtotal,
  ADD COLUMN IF NOT EXISTS discount_amount  DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER discount_percent,
  ADD COLUMN IF NOT EXISTS vat_percent      DECIMAL(5,2)  NOT NULL DEFAULT 0.00 AFTER discount_amount,
  ADD COLUMN IF NOT EXISTS vat_amount       DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER vat_percent;

-- Old documents had no discount / VAT, so their subtotal is simply their total
UPDATE quotation SET subtotal = total WHERE subtotal = 0 AND total > 0;
UPDATE invoice   SET subtotal = total WHERE subtotal = 0 AND total > 0;

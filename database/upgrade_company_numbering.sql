-- UPGRADE for an EXISTING database: quotation + invoice numbers become ONE series per company.
-- You do NOT have to run this: opening any page does the same thing automatically (config/db.php).
-- Run it only if you prefer phpMyAdmin. Run ONCE, after taking a backup. Needs MySQL 8+ / MariaDB 10.2+.
-- Old rows are renumbered 1, 2, 3 ... per company in creation order.
USE quotation_system;

-- Quotation
ALTER TABLE quotation DROP INDEX uq_quotation_user_no;
ALTER TABLE quotation ADD COLUMN company_id INT NULL AFTER user_id;
UPDATE quotation q JOIN `user` u ON u.user_id = q.user_id SET q.company_id = u.company_id;
UPDATE quotation q
JOIN (
  SELECT quotation_id, ROW_NUMBER() OVER (PARTITION BY company_id ORDER BY created_at, quotation_id) AS new_no
  FROM quotation
) x ON x.quotation_id = q.quotation_id
SET q.quotation_no = x.new_no;
ALTER TABLE quotation MODIFY company_id INT NOT NULL;
ALTER TABLE quotation ADD UNIQUE KEY uq_quotation_company_no (company_id, quotation_no);
ALTER TABLE quotation ADD CONSTRAINT fk_quotation_company FOREIGN KEY (company_id) REFERENCES company(company_id) ON DELETE CASCADE ON UPDATE CASCADE;

-- Invoice
ALTER TABLE invoice ADD COLUMN company_id INT NULL AFTER invoice_id;
UPDATE invoice i JOIN quotation q ON q.quotation_id = i.quotation_id SET i.company_id = q.company_id;
UPDATE invoice i
JOIN (
  SELECT invoice_id, ROW_NUMBER() OVER (PARTITION BY company_id ORDER BY created_at, invoice_id) AS new_no
  FROM invoice
) x ON x.invoice_id = i.invoice_id
SET i.invoice_no = x.new_no;
ALTER TABLE invoice MODIFY company_id INT NOT NULL;
ALTER TABLE invoice ADD UNIQUE KEY uq_invoice_company_no (company_id, invoice_no);
ALTER TABLE invoice ADD CONSTRAINT fk_invoice_company FOREIGN KEY (company_id) REFERENCES company(company_id) ON DELETE CASCADE ON UPDATE CASCADE;

-- OPTIONAL sample data so you can see how quotations look.
-- Run this AFTER you have created your first account (register.php).
-- It uses the first company and the first user in the database.
USE quotation_system;

-- Sample products for the first company
INSERT INTO product_service (company_id, name, price)
SELECT c.company_id, v.name, v.price
FROM (SELECT MIN(company_id) AS company_id FROM company) c
JOIN (
  SELECT 'Website design' AS name, 85000.00 AS price
  UNION ALL SELECT 'Logo design', 25000.00
  UNION ALL SELECT 'Monthly hosting', 3500.00
  UNION ALL SELECT 'Business cards (500)', 6500.00
) v
WHERE c.company_id IS NOT NULL;

-- One sample quotation
-- Subtotal 152,000.00 - 10% discount (15,200.00) + 18% VAT (24,624.00) = 161,424.00
INSERT INTO quotation (user_id, company_id, quotation_no, recipient_name, recipient_address, quotation_date, subtotal, discount_percent, discount_amount, vat_percent, vat_amount, total, status, special_notice)
SELECT u.user_id, u.company_id, 1, 'Silva Traders', CONCAT('12 Galle Road', CHAR(10), 'Colombo 03'), CURDATE(), 152000.00, 10.00, 15200.00, 18.00, 24624.00, 161424.00, 'sent',
       CONCAT('This quotation is valid for 14 days.', CHAR(10), '50% advance payment is required to start work.')
FROM `user` u
ORDER BY u.user_id
LIMIT 1;

SET @qid = LAST_INSERT_ID();

INSERT INTO quotation_item (quotation_id, item_id, name, description, unit_price, quantity) VALUES
(@qid, NULL, 'Website design',  'Up to 5 pages, mobile friendly', 85000.00, 1),
(@qid, NULL, 'Logo design',     'Two concepts, one revision',     25000.00, 1),
(@qid, NULL, 'Monthly hosting', 'First 12 months',                 3500.00, 12);

-- Matching invoice (with its own copy of the customer details and items)
INSERT INTO invoice (company_id, invoice_no, quotation_id, invoice_date, subtotal, discount_percent, discount_amount, vat_percent, vat_amount, total, recipient_name, recipient_address, recipient_phone, special_notice)
SELECT company_id, 1, quotation_id, CURDATE(), 152000.00, 10.00, 15200.00, 18.00, 24624.00, 161424.00, recipient_name, recipient_address, recipient_phone, special_notice
FROM quotation WHERE quotation_id = @qid;

SET @iid = LAST_INSERT_ID();

INSERT INTO invoice_item (invoice_id, name, description, unit_price, quantity, unit)
SELECT @iid, name, description, unit_price, quantity, unit FROM quotation_item WHERE quotation_id = @qid;

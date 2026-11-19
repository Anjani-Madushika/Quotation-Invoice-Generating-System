-- Handy queries you can run in phpMyAdmin (SQL tab)
USE quotation_system;

-- 1. Quotations by status
SELECT status, COUNT(*) AS quotations, SUM(total) AS value
FROM quotation
GROUP BY status;

-- 2. Latest 10 quotations with who made them
SELECT q.quotation_id, q.recipient_name, q.quotation_date, q.total, q.status, u.name AS prepared_by
FROM quotation q
JOIN `user` u ON u.user_id = q.user_id
ORDER BY q.quotation_id DESC
LIMIT 10;

-- 3. Invoiced amount per month
SELECT DATE_FORMAT(invoice_date, '%Y-%m') AS month, COUNT(*) AS invoices, SUM(total) AS invoiced
FROM invoice
GROUP BY month
ORDER BY month DESC;

-- 4. Best selling items (by amount quoted)
SELECT name, SUM(quantity) AS qty, SUM(unit_price * quantity) AS amount
FROM quotation_item
GROUP BY name
ORDER BY amount DESC
LIMIT 10;

-- 5. Quotations that have no invoice yet
SELECT q.quotation_id, q.recipient_name, q.total
FROM quotation q
LEFT JOIN invoice i ON i.quotation_id = q.quotation_id
WHERE i.invoice_id IS NULL;

-- 6. Discount and VAT given / charged per month (invoices)
SELECT DATE_FORMAT(invoice_date, '%Y-%m') AS month,
       SUM(subtotal)        AS before_discount,
       SUM(discount_amount) AS discount_given,
       SUM(vat_amount)      AS vat_charged,
       SUM(total)           AS invoiced
FROM invoice
GROUP BY month
ORDER BY month DESC;

-- 7. Reset the quotation system data (keeps company and users)
-- DELETE FROM invoice;
-- DELETE FROM quotation_item;
-- DELETE FROM quotation;

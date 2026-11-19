-- UPGRADE for an EXISTING database: adds the company logo column.
-- You do NOT have to run this: opening any page adds it automatically (config/db.php).
USE quotation_system;
ALTER TABLE company ADD COLUMN logo MEDIUMTEXT NULL AFTER account_no;

-- UPGRADE for an EXISTING database: adds the document colour setting.
-- You do NOT have to run this: opening any page adds it automatically (config/db.php).
USE quotation_system;
ALTER TABLE company ADD COLUMN theme_color VARCHAR(7) NULL AFTER logo;

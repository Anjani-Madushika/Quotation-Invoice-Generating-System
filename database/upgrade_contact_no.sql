-- Adds the customer contact number to quotations (config/db.php also does this automatically)
USE quotation_system;
ALTER TABLE quotation ADD COLUMN recipient_phone VARCHAR(30) NULL AFTER recipient_address;

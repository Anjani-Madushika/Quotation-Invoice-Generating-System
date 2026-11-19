<?php
// One-time upgrade for databases made by an OLDER version of this system.
// config/db.php loads this file only when the database is not on the current (v27) layout yet,
// so a current database never pays for it. Every step checks first, so running it twice is harmless.
//
// Steps, oldest first:
//   address + status        -> quotation.recipient_address, remove the old 'approved' status
//   numbering               -> quotation_no / invoice_no, then ONE series per company (company_id + UNIQUE keys)
//   contact no, logo, theme -> quotation.recipient_phone, company.logo, company.theme_color
//   discount + VAT          -> subtotal / discount / VAT columns on quotation and invoice
//   v27                     -> company.doc_layout (document layout choice)
//   v21                     -> user.is_active, item units + decimal quantity, invoice keeps its own copy of the items

function upgrade_old_database(PDO $pdo): void
{
    if (!column_exists($pdo, 'quotation', 'recipient_address')) {
        $pdo->exec('ALTER TABLE quotation ADD COLUMN recipient_address TEXT NULL AFTER recipient_name');
    }
    $statusCol = column_info($pdo, 'quotation', 'status');
    if ($statusCol && stripos($statusCol['Type'], 'approved') !== false) {
        $pdo->exec("UPDATE quotation SET status = 'sent' WHERE status = 'approved'");
        $pdo->exec("ALTER TABLE quotation MODIFY status ENUM('draft','sent','rejected') NOT NULL DEFAULT 'draft'");
    }

    // Old databases without quotation_no / invoice_no (the block below then moves them to company-wide numbering)
    if (!column_exists($pdo, 'quotation', 'quotation_no')) {
        $pdo->exec('ALTER TABLE quotation ADD COLUMN quotation_no INT NOT NULL DEFAULT 0 AFTER quotation_id');
        $seen = [];
        $upd  = $pdo->prepare('UPDATE quotation SET quotation_no = ? WHERE quotation_id = ?');
        foreach ($pdo->query('SELECT quotation_id, user_id FROM quotation ORDER BY quotation_id')->fetchAll() as $r) {
            $seen[$r['user_id']] = ($seen[$r['user_id']] ?? 0) + 1;
            $upd->execute([$seen[$r['user_id']], $r['quotation_id']]);
        }
        $pdo->exec('ALTER TABLE quotation ADD UNIQUE KEY uq_quotation_user_no (user_id, quotation_no)');
    }
    if (!column_exists($pdo, 'invoice', 'invoice_no')) {
        $pdo->exec('ALTER TABLE invoice ADD COLUMN invoice_no INT NOT NULL DEFAULT 0 AFTER invoice_id');
        $seen = [];
        $upd  = $pdo->prepare('UPDATE invoice SET invoice_no = ? WHERE invoice_id = ?');
        $rows = $pdo->query('SELECT i.invoice_id, q.user_id FROM invoice i JOIN quotation q ON q.quotation_id = i.quotation_id ORDER BY i.invoice_id')->fetchAll();
        foreach ($rows as $r) {
            $seen[$r['user_id']] = ($seen[$r['user_id']] ?? 0) + 1;
            $upd->execute([$seen[$r['user_id']], $r['invoice_id']]);
        }
    }

    // Company-wide numbering: one Q- series and one I- series per company (all users share it).
    // Adds company_id to quotation + invoice, renumbers old rows so no number repeats, adds UNIQUE keys.
    if (!column_exists($pdo, 'quotation', 'company_id')) {
        if ($pdo->query("SHOW INDEX FROM quotation WHERE Key_name = 'uq_quotation_user_no'")->fetch()) {
            $pdo->exec('ALTER TABLE quotation DROP INDEX uq_quotation_user_no');
        }
        $pdo->exec('ALTER TABLE quotation ADD COLUMN company_id INT NULL AFTER user_id');
        $pdo->exec('UPDATE quotation q JOIN `user` u ON u.user_id = q.user_id SET q.company_id = u.company_id');
        $seen = [];
        $upd  = $pdo->prepare('UPDATE quotation SET quotation_no = ? WHERE quotation_id = ?');
        foreach ($pdo->query('SELECT quotation_id, company_id FROM quotation ORDER BY created_at, quotation_id')->fetchAll() as $r) {
            $seen[$r['company_id']] = ($seen[$r['company_id']] ?? 0) + 1;
            $upd->execute([$seen[$r['company_id']], $r['quotation_id']]);
        }
        $pdo->exec('ALTER TABLE quotation MODIFY company_id INT NOT NULL');
        $pdo->exec('ALTER TABLE quotation ADD UNIQUE KEY uq_quotation_company_no (company_id, quotation_no)');
        $pdo->exec('ALTER TABLE quotation ADD CONSTRAINT fk_quotation_company FOREIGN KEY (company_id) REFERENCES company(company_id) ON DELETE CASCADE ON UPDATE CASCADE');
    }
    if (!column_exists($pdo, 'invoice', 'company_id')) {
        $pdo->exec('ALTER TABLE invoice ADD COLUMN company_id INT NULL AFTER invoice_id');
        $pdo->exec('UPDATE invoice i JOIN quotation q ON q.quotation_id = i.quotation_id SET i.company_id = q.company_id');
        $seen = [];
        $upd  = $pdo->prepare('UPDATE invoice SET invoice_no = ? WHERE invoice_id = ?');
        foreach ($pdo->query('SELECT invoice_id, company_id FROM invoice ORDER BY created_at, invoice_id')->fetchAll() as $r) {
            $seen[$r['company_id']] = ($seen[$r['company_id']] ?? 0) + 1;
            $upd->execute([$seen[$r['company_id']], $r['invoice_id']]);
        }
        $pdo->exec('ALTER TABLE invoice MODIFY company_id INT NOT NULL');
        $pdo->exec('ALTER TABLE invoice ADD UNIQUE KEY uq_invoice_company_no (company_id, invoice_no)');
        $pdo->exec('ALTER TABLE invoice ADD CONSTRAINT fk_invoice_company FOREIGN KEY (company_id) REFERENCES company(company_id) ON DELETE CASCADE ON UPDATE CASCADE');
    }

    // Customer contact number (searchable on the Quotations / Invoices pages)
    if (!column_exists($pdo, 'quotation', 'recipient_phone')) {
        $pdo->exec('ALTER TABLE quotation ADD COLUMN recipient_phone VARCHAR(30) NULL AFTER recipient_address');
    }

    // Company logo (uploaded on the Company page)
    if (!column_exists($pdo, 'company', 'logo')) {
        $pdo->exec('ALTER TABLE company ADD COLUMN logo MEDIUMTEXT NULL AFTER account_no');
    }

    // Document colour (chosen on the Company page)
    if (!column_exists($pdo, 'company', 'theme_color')) {
        $pdo->exec('ALTER TABLE company ADD COLUMN theme_color VARCHAR(7) NULL AFTER logo');
    }

    // v27: document layout (modern / classic / minimal), chosen on the Company page
    if (!column_exists($pdo, 'company', 'doc_layout')) {
        $pdo->exec("ALTER TABLE company ADD COLUMN doc_layout VARCHAR(20) NOT NULL DEFAULT 'modern' AFTER theme_color");
    }

    // Discount + VAT columns (older databases get them automatically)
    $discCols = [
        'subtotal'         => 'DECIMAL(12,2) NOT NULL DEFAULT 0.00',
        'discount_percent' => 'DECIMAL(5,2)  NOT NULL DEFAULT 0.00',
        'discount_amount'  => 'DECIMAL(12,2) NOT NULL DEFAULT 0.00',
        'vat_percent'      => 'DECIMAL(5,2)  NOT NULL DEFAULT 0.00',
        'vat_amount'       => 'DECIMAL(12,2) NOT NULL DEFAULT 0.00',
    ];
    foreach (['quotation', 'invoice'] as $tbl) {
        $added = false;
        foreach ($discCols as $col => $def) {
            if (!column_exists($pdo, $tbl, $col)) {
                $pdo->exec("ALTER TABLE $tbl ADD COLUMN $col $def");
                $added = true;
            }
        }
        if ($added) {
            // Old documents had no discount / VAT: their subtotal is their total
            $pdo->exec("UPDATE $tbl SET subtotal = total WHERE subtotal = 0 AND total > 0");
        }
    }

    // v21: deactivate users instead of deleting them (their quotations + invoices stay)
    if (!column_exists($pdo, 'user', 'is_active')) {
        $pdo->exec('ALTER TABLE `user` ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER user_type');
    }

    // v21: item units (kg, g, pcs ...) and decimal quantities (2.5 kg)
    if (!column_exists($pdo, 'product_service', 'unit')) {
        $pdo->exec("ALTER TABLE product_service ADD COLUMN unit VARCHAR(10) NOT NULL DEFAULT '' AFTER price");
    }
    if (!column_exists($pdo, 'quotation_item', 'unit')) {
        $pdo->exec("ALTER TABLE quotation_item ADD COLUMN unit VARCHAR(10) NOT NULL DEFAULT '' AFTER quantity");
    }
    $qtyCol = column_info($pdo, 'quotation_item', 'quantity');
    if ($qtyCol && stripos($qtyCol['Type'], 'int') === 0) {
        $pdo->exec('ALTER TABLE quotation_item MODIFY quantity DECIMAL(12,3) NOT NULL DEFAULT 1.000');
    }

    // v21: an invoice keeps its OWN copy of the items + customer details (editing the quotation later does not change it)
    if (!column_exists($pdo, 'invoice', 'recipient_name')) {
        $pdo->exec('ALTER TABLE invoice ADD COLUMN recipient_name VARCHAR(150) NULL, ADD COLUMN recipient_address TEXT NULL, ADD COLUMN recipient_phone VARCHAR(30) NULL, ADD COLUMN special_notice TEXT NULL');
        $pdo->exec('UPDATE invoice i JOIN quotation q ON q.quotation_id = i.quotation_id SET i.recipient_name = q.recipient_name, i.recipient_address = q.recipient_address, i.recipient_phone = q.recipient_phone, i.special_notice = q.special_notice');
    }
    if (!table_exists($pdo, 'invoice_item')) {
        $pdo->exec('CREATE TABLE invoice_item (
            invoice_item_id INT AUTO_INCREMENT PRIMARY KEY,
            invoice_id      INT NOT NULL,
            name            VARCHAR(150) NOT NULL,
            description     TEXT NULL,
            unit_price      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            quantity        DECIMAL(12,3) NOT NULL DEFAULT 1.000,
            unit            VARCHAR(10) NOT NULL DEFAULT \'\',
            CONSTRAINT fk_iitem_invoice FOREIGN KEY (invoice_id)
              REFERENCES invoice(invoice_id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB');
        // Invoices that already exist: copy the items they show today
        $pdo->exec('INSERT INTO invoice_item (invoice_id, name, description, unit_price, quantity, unit)
                    SELECT i.invoice_id, qi.name, qi.description, qi.unit_price, qi.quantity, qi.unit
                    FROM invoice i JOIN quotation_item qi ON qi.quotation_id = i.quotation_id
                    ORDER BY i.invoice_id, qi.quotation_item_id');
    }
}

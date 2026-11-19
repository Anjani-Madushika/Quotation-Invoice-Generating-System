<?php
// Data and formatting helpers: escaping, money, units, totals, statuses, search, common database lookups.
// Loaded by includes/auth.php, so every page already has it.

// ---- Text + numbers ----
function e(?string $v): string {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

function safe_filename(string $s): string {
    return trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-');
}

function money($n): string {
    return number_format((float) $n, 2);
}

// 10.00 -> "10", 7.50 -> "7.5"
function pct($n): string {
    return rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
}

// ---- Units and quantities (Qty column) ----
// Whole-number units (pcs, box ...) do not accept decimals;
// the others (kg, g, l, m, hr ...) accept up to 3 decimals, e.g. 2.5 kg or 250 g.
const UNITS = [
    ''     => 'No unit',
    'pcs'  => 'pcs',
    'kg'   => 'kg',
    'g'    => 'g',
    'l'    => 'l',
    'ml'   => 'ml',
    'm'    => 'm',
    'cm'   => 'cm',
    'ft'   => 'ft',
    'sqft' => 'sq.ft',
    'box'  => 'box',
    'pack' => 'pack',
    'set'  => 'set',
    'hr'   => 'hr',
    'day'  => 'day',
];
const WHOLE_UNITS = ['', 'pcs', 'box', 'pack', 'set'];

function clean_unit($u): string {
    $u = (string) $u;
    return array_key_exists($u, UNITS) ? $u : '';
}

// Quantity the database can store: whole units -> 1, 2, 3 ...; other units -> up to 3 decimals
function clean_qty($q, string $unit): float {
    $q = (float) $q;
    if (in_array($unit, WHOLE_UNITS, true)) {
        return (float) min(1000000, max(1, (int) round($q)));
    }
    return round(min(1000000.0, max(0.001, $q)), 3);
}

// 2 -> "2", 2.5 + "kg" -> "2.5 kg", 0.25 + "kg" -> "0.25 kg"
function qty_text($q, ?string $unit = ''): string {
    $s = rtrim(rtrim(number_format((float) $q, 3, '.', ''), '0'), '.');
    $unit = (string) $unit;
    return $unit !== '' && isset(UNITS[$unit]) ? $s . ' ' . UNITS[$unit] : $s;
}

// One line of a document: unit price x quantity, rounded to 2 decimals (the totals add up these rounded lines)
function line_amount($price, $qty): float {
    return round((float) $price * (float) $qty, 2);
}

// ---- Totals: discount first, then VAT on what is left ----
function calc_totals(float $subtotal, float $discPct, float $vatPct): array {
    $discPct  = min(100.0, max(0.0, $discPct));
    $vatPct   = min(100.0, max(0.0, $vatPct));
    $discount = round($subtotal * $discPct / 100, 2);
    $after    = $subtotal - $discount;
    $vat      = round($after * $vatPct / 100, 2);
    return [
        'subtotal'         => round($subtotal, 2),
        'discount_percent' => $discPct,
        'discount_amount'  => $discount,
        'vat_percent'      => $vatPct,
        'vat_amount'       => $vat,
        'total'            => round($after + $vat, 2),
    ];
}

// Lines shown between Subtotal and Total on a document (only when a % was used)
function document_adjustments(array $r): array {
    $out = [];
    if ((float) ($r['discount_percent'] ?? 0) > 0) {
        $out[] = ['Discount (' . pct($r['discount_percent']) . '%)', '- Rs. ' . money($r['discount_amount'])];
    }
    if ((float) ($r['vat_percent'] ?? 0) > 0) {
        $out[] = ['VAT (' . pct($r['vat_percent']) . '%)', 'Rs. ' . money($r['vat_amount'])];
    }
    return $out;
}

// ---- Quotation status ----
const QUOTATION_STATUSES = ['draft', 'sent', 'rejected'];

// Badge colours for a status (used by the badge and by the status dropdown on the Quotations page)
function status_classes(string $s): string {
    $map = [
        'draft'    => 'bg-slate-100 text-slate-700',
        'sent'     => 'bg-sky-100 text-sky-700',
        'rejected' => 'bg-rose-100 text-rose-700',
    ];
    return $map[$s] ?? 'bg-slate-100 text-slate-700';
}

function status_badge(string $s): string {
    return '<span class="inline-block px-2.5 py-1 rounded-full text-xs font-bold ' . status_classes($s) . '">' . e(ucfirst($s)) . '</span>';
}

// ---- "Billed to" block and the search box ----
// Address + contact number, as printed under "Billed to"
function recipient_block(?string $address, ?string $phone): string {
    $address = trim((string) $address);
    $phone   = trim((string) $phone);
    if ($phone !== '') {
        $address .= ($address !== '' ? "\n" : '') . 'Tel: ' . $phone;
    }
    return $address;
}

// Search box on the Quotations + Invoices pages: quotation no (Q-0005 / 5), name, contact no, date.
// $qa = alias of the quotation table in the query. Returns [sql, params] to append with AND.
// $forInvoices = true on the Invoices page (invoice table aliased "i"): also finds invoice no (I-0003 / 3) and invoice date.
function search_clause(string $term, string $qa = 'q', bool $forInvoices = false): array {
    $term = trim($term);
    if ($term === '') {
        return ['', []];
    }
    $like   = '%' . $term . '%';
    $parts  = ["$qa.recipient_name LIKE ?", "$qa.recipient_phone LIKE ?", "$qa.recipient_address LIKE ?", "CAST($qa.quotation_date AS CHAR) LIKE ?"];
    $params = [$like, $like, $like, $like];

    // Q-0005, q5 or 5  ->  quotation number 5
    if (preg_match('/^(?:q[-\s]*)?0*(\d+)$/i', $term, $m)) {
        $parts[]  = "$qa.quotation_no = ?";
        $params[] = (int) $m[1];
    }
    // Phone typed with spaces / dashes: compare digits only
    $digits = preg_replace('/\D+/', '', $term);
    if (strlen($digits) >= 3) {
        $parts[]  = "REPLACE(REPLACE(REPLACE(REPLACE($qa.recipient_phone, ' ', ''), '-', ''), '+', ''), '(', '') LIKE ?";
        $params[] = '%' . $digits . '%';
    }
    if ($forInvoices) {
        $parts[]  = 'CAST(i.invoice_date AS CHAR) LIKE ?';
        $params[] = $like;
        // I-0003, i3 or 3  ->  invoice number 3
        if (preg_match('/^(?:i[-\s]*)?0*(\d+)$/i', $term, $m)) {
            $parts[]  = 'i.invoice_no = ?';
            $params[] = (int) $m[1];
        }
    }
    return [' AND (' . implode(' OR ', $parts) . ')', $params];
}

// ---- Database lookups used by several pages ----
function find_quotation(PDO $pdo, int $id): ?array {
    $s = $pdo->prepare('SELECT q.*, u.name AS user_name, u.email AS user_email FROM quotation q JOIN `user` u ON u.user_id = q.user_id WHERE q.quotation_id = ? AND u.company_id = ?');
    $s->execute([$id, (int) $_SESSION['company_id']]);
    $r = $s->fetch();
    return $r ?: null;
}

function quotation_items(PDO $pdo, int $qid): array {
    $s = $pdo->prepare('SELECT * FROM quotation_item WHERE quotation_id = ? ORDER BY quotation_item_id');
    $s->execute([$qid]);
    return $s->fetchAll();
}

// The items printed on an invoice. They are a copy made when the invoice was generated (table invoice_item),
// so editing the quotation later never changes an invoice that was already issued.
function invoice_items(PDO $pdo, int $invoiceId): array {
    $s = $pdo->prepare('SELECT * FROM invoice_item WHERE invoice_id = ? ORDER BY invoice_item_id');
    $s->execute([$invoiceId]);
    return $s->fetchAll();
}

function find_company(PDO $pdo): array {
    $s = $pdo->prepare('SELECT * FROM company WHERE company_id = ?');
    $s->execute([(int) $_SESSION['company_id']]);
    $r = $s->fetch();
    return $r ?: [];
}

// ---- Form checks ----
// Name of the first item that appears twice in $rows (ignoring case and extra spaces), or null when there is none
function first_duplicate_name(array $rows): ?string {
    $seen = [];
    foreach ($rows as $r) {
        $key = mb_strtolower(preg_replace('/\s+/u', ' ', $r['name']));
        if (isset($seen[$key])) {
            return $r['name'];
        }
        $seen[$key] = true;
    }
    return null;
}

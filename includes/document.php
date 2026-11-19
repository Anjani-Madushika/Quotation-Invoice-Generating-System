<?php
// Printable quotation / invoice layout (one design for both).
//   Banner (logo + company name, title) -> details + billed to -> items -> Subtotal / Discount / VAT / Total
//   -> special notice -> footer line
//   Bank details sit in a neat box just above the footer line (invoices only, not on quotations)
//   Footer (same on quotation and invoice): company name, then address / phone / email on one line
//   Colours come from the company's colour setting and the design from its layout setting (Company page):
//   modern (banner) / classic (white header + filled table header) / minimal (thin lines).
// $d keys: type, label_to, recipient, recipient_address, contact_email,
//          meta (list of [label, value]), company, items, total, notice
//          subtotal (optional, defaults to total)
//          adjustments (optional list of [label, text], shown between Subtotal and Total, e.g. Discount / VAT)

function render_document(array $d): void {
    $c         = $d['company'];
    $isInvoice = strcasecmp($d['type'], 'Invoice') === 0;
    $email     = $d['contact_email'] ?? '';
    $phone     = $c['phone_no'] ?? '';
    $address   = $c['address'] ?? '';
    $name      = trim($c['company_name'] ?? '');
    $parts     = preg_split('/\s+/', $name, 2);
    $first     = $parts[0] ?? '';
    $rest      = $parts[1] ?? '';
    $initial   = strtoupper(substr($name !== '' ? $name : 'Q', 0, 1));
    $totalLbl  = $isInvoice ? 'Total due' : 'Quotation total';
    $hasBank   = !empty($c['bank_name']) || !empty($c['account_holder']) || !empty($c['account_no']);
    $showBank  = $hasBank && $isInvoice;
    $t         = theme_colors($c['theme_color'] ?? null);
    $layout    = doc_layout_key($c['doc_layout'] ?? null);
    // Table styling per layout
    $p1        = $layout === 'classic' ? 'pl-4 pr-4' : 'pr-4';   // first column padding
    $pLast     = $layout === 'classic' ? 'px-4' : 'pl-4';         // last column padding
    if ($layout === 'classic') {
        $tblWrap = 'mt-9 overflow-x-auto';
        $trCls   = 'text-left text-xs font-bold uppercase tracking-[0.14em] text-white';
        $trSty   = 'background:' . $t['dark'];
    } elseif ($layout === 'minimal') {
        $tblWrap = 'mt-9 overflow-x-auto border-t border-slate-300';
        $trCls   = 'border-b border-slate-300 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500';
        $trSty   = '';
    } else {
        $tblWrap = 'mt-9 overflow-x-auto border-t-4';
        $trCls   = 'border-b-2 text-left text-xs font-bold uppercase tracking-[0.14em]';
        $trSty   = 'border-color:' . $t['dark'] . ';color:' . $t['dark'];
    }
    $grad      = 'linear-gradient(100deg,' . $t['dark'] . ' 0%,' . $t['mid'] . ' 58%,' . $t['light'] . ' 100%)';
    $addrLine  = trim(preg_replace('/\s*\R\s*/', ', ', trim($address)));
    $oneLine   = array_filter([$addrLine, $phone !== '' ? 'Tel: ' . $phone : '', $email]);
    ?>
<div class="a4-host">
<article id="doc" data-a4-fit class="a4-sheet font-inter bg-white text-slate-800 flex flex-col">

  <?php if ($layout === 'classic'): ?>
  <!-- Classic header: logo + company name, title, coloured line -->
  <header class="px-10 pt-10 pb-6 border-b-4" style="border-color:<?= e($t['dark']) ?>">
    <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-4">
      <div class="flex items-center gap-4 min-w-0">
        <?php if (!empty($c['logo'])): ?>
          <img src="<?= e($c['logo']) ?>" alt="<?= e($name) ?>" class="block h-16 w-auto max-w-[10rem] object-contain shrink-0">
        <?php else: ?>
          <span class="w-16 h-16 rounded-2xl grid place-items-center shrink-0 text-3xl font-extrabold text-white" style="background:<?= e($t['dark']) ?>"><?= e($initial) ?></span>
        <?php endif; ?>
        <p class="min-w-0 text-2xl leading-tight wrap-any" style="color:<?= e($t['dark']) ?>">
          <span class="font-bold"><?= e($first) ?></span><?php if ($rest !== ''): ?> <span class="font-light"><?= e($rest) ?></span><?php endif; ?>
        </p>
      </div>
      <h2 class="text-4xl font-extrabold uppercase tracking-[0.12em]" style="color:<?= e($t['dark']) ?>"><?= e($d['type']) ?></h2>
    </div>
  </header>
  <?php elseif ($layout === 'minimal'): ?>
  <!-- Minimal header: big light title, company on the right, thin line -->
  <header class="px-10 pt-12">
    <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-4">
      <h2 class="text-5xl font-light uppercase tracking-[0.2em]" style="color:<?= e($t['dark']) ?>"><?= e($d['type']) ?></h2>
      <div class="text-right min-w-0">
        <?php if (!empty($c['logo'])): ?>
          <img src="<?= e($c['logo']) ?>" alt="<?= e($name) ?>" class="block ml-auto h-12 w-auto max-w-[10rem] object-contain">
        <?php endif; ?>
        <p class="<?= !empty($c['logo']) ? 'mt-2 text-sm' : 'text-xl' ?> font-bold text-slate-900 wrap-any"><?= e($name) ?></p>
      </div>
    </div>
    <div class="mt-6 h-px bg-slate-300 relative"><span class="absolute left-0 -top-[1px] block w-20 h-[3px] rounded-full" style="background:<?= e($t['mid']) ?>"></span></div>
  </header>
  <?php else: ?>
  <!-- Banner: logo + company name, document title -->
  <header class="relative overflow-hidden text-white px-10 py-10" style="background:<?= e($grad) ?>">
    <span aria-hidden="true" class="absolute -top-28 -right-20 w-80 h-80 rounded-full" style="background:rgba(255,255,255,.08)"></span>
    <div class="relative flex flex-wrap items-center justify-between gap-x-6 gap-y-4">
      <div class="flex items-center gap-4 min-w-0">
        <?php if (!empty($c['logo'])): ?>
          <span class="rounded-2xl bg-white p-1.5 grid place-items-center shrink-0">
            <img src="<?= e($c['logo']) ?>" alt="<?= e($name) ?>" class="block h-14 w-auto max-w-[10rem] object-contain">
          </span>
        <?php else: ?>
        <span class="relative w-16 h-16 rounded-2xl bg-white grid place-items-center shrink-0">
          <span class="text-3xl font-extrabold leading-none" style="color:<?= e($t['dark']) ?>"><?= e($initial) ?></span>
          <span aria-hidden="true" class="absolute bottom-3 left-1/2 -translate-x-1/2 w-5 h-[3px] rounded-full bg-gold"></span>
        </span>
        <?php endif; ?>
        <span aria-hidden="true" class="block w-px h-12 bg-white/30"></span>
        <p class="min-w-0 text-2xl leading-tight wrap-any">
          <span class="font-bold"><?= e($first) ?></span><?php if ($rest !== ''): ?> <span class="font-light"><?= e($rest) ?></span><?php endif; ?>
        </p>
      </div>
      <h2 class="text-4xl font-extrabold uppercase tracking-[0.12em]"><?= e($d['type']) ?></h2>
    </div>
  </header>
  <?php endif; ?>

  <div class="px-10 pt-9 pb-8 flex-1 flex flex-col">

    <!-- Details (left), billed to (right) -->
    <div class="grid gap-8 grid-cols-2">
      <dl class="text-[15px] space-y-2">
        <?php foreach ($d['meta'] as [$k, $v]): ?>
          <div class="wrap-any"><dt class="inline font-bold text-slate-900"><?= e($k) ?>:</dt> <dd class="inline"><?= e($v) ?></dd></div>
        <?php endforeach; ?>
      </dl>

      <div class="text-[15px] min-w-0 justify-self-end w-[17rem]">
        <p class="font-bold text-slate-900"><?= e($d['label_to']) ?></p>
        <p class="mt-2 font-bold text-slate-900 wrap-any"><?= e($d['recipient']) ?></p>
        <?php if (!empty($d['recipient_address'])): ?>
          <p class="mt-1 leading-7 whitespace-pre-line wrap-any"><?= e($d['recipient_address']) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Items -->
    <div class="<?= $tblWrap ?>"<?php if ($layout === 'modern'): ?> style="border-color:<?= e($t['dark']) ?>"<?php endif; ?>>
      <table class="w-full text-[15px]">
        <thead>
          <tr class="<?= $trCls ?>"<?= $trSty !== '' ? ' style="' . e($trSty) . '"' : '' ?>>
            <th class="py-3.5 <?= $p1 ?>">Description</th>
            <th class="py-3.5 px-4 text-right">Qty</th>
            <th class="py-3.5 px-4 text-right whitespace-nowrap">Unit price</th>
            <th class="py-3.5 <?= $pLast ?> text-right">Amount</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
          <?php foreach ($d['items'] as $n => $it): ?>
            <tr class="align-top">
              <td class="py-5 <?= $p1 ?>">
                <p class="font-semibold text-slate-900 wrap-any"><?= $n + 1 ?>. <?= e($it['name']) ?></p>
                <?php if (!empty($it['description'])): ?>
                  <p class="mt-1 text-xs text-slate-500 whitespace-pre-line wrap-any"><?= e($it['description']) ?></p>
                <?php endif; ?>
              </td>
              <td class="py-5 px-4 text-right whitespace-nowrap"><?= e(qty_text($it['quantity'], $it['unit'] ?? '')) ?></td>
              <td class="py-5 px-4 text-right whitespace-nowrap">Rs. <?= money($it['unit_price']) ?></td>
              <td class="py-5 <?= $pLast ?> text-right whitespace-nowrap">Rs. <?= money(line_amount($it['unit_price'], $it['quantity'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Totals -->
    <div class="mt-8 ml-auto w-[23rem] avoid-break">
      <dl class="text-sm">
        <div class="flex items-center justify-between border-b border-slate-200 py-2.5">
          <dt class="text-slate-500">Subtotal</dt>
          <dd class="font-bold text-slate-900 whitespace-nowrap">Rs. <?= money($d['subtotal'] ?? $d['total']) ?></dd>
        </div>
        <?php foreach ($d['adjustments'] ?? [] as [$k, $v]): ?>
          <div class="flex items-center justify-between border-b border-slate-200 py-2.5">
            <dt class="text-slate-500"><?= e($k) ?></dt>
            <dd class="font-bold text-slate-900 whitespace-nowrap"><?= e($v) ?></dd>
          </div>
        <?php endforeach; ?>
      </dl>
      <p class="mt-5 text-xs font-bold uppercase tracking-[0.16em]" style="color:<?= e($t['light']) ?>"><?= e($totalLbl) ?></p>
      <p class="mt-1 text-right text-4xl font-extrabold tracking-tight whitespace-nowrap" style="color:<?= e($t['dark']) ?>">Rs. <?= money($d['total']) ?></p>
    </div>

    <!-- Notice + footer sit at the bottom of the page (mt-auto) -->
    <div class="mt-auto pt-10">
    <?php if (!empty($d['notice'])): ?>
      <p class="avoid-break wrap-any mb-8 text-sm font-semibold text-red-700 whitespace-pre-line"><?= e($d['notice']) ?></p>
    <?php endif; ?>

    <!-- Bank details: neat box just above the footer -->
    <?php if ($showBank):
        $bankFields = array_filter([
            'Bank'           => trim((string) ($c['bank_name'] ?? '')),
            'Account holder' => trim((string) ($c['account_holder'] ?? '')),
            'Account no'     => trim((string) ($c['account_no'] ?? '')),
        ], fn($v) => $v !== '');
    ?>
      <div class="avoid-break mb-6 rounded-xl px-6 py-4" style="background:<?= e($t['tint']) ?>;border:1px solid <?= e($t['border']) ?>">
        <p class="text-xs font-bold uppercase tracking-[0.14em]" style="color:<?= e($t['dark']) ?>">Bank details</p>
        <dl class="mt-3 grid grid-cols-3 gap-x-6 text-sm">
          <?php foreach ($bankFields as $lbl => $val): ?>
            <div class="min-w-0"><dt class="text-xs text-slate-500"><?= e($lbl) ?></dt><dd class="mt-0.5 font-bold text-slate-900 wrap-any"><?= e($val) ?></dd></div>
          <?php endforeach; ?>
        </dl>
      </div>
    <?php endif; ?>

    <!-- Footer (same on quotation and invoice) -->
    <footer class="avoid-break pt-6 <?= $layout === 'minimal' ? 'border-t border-slate-300' : 'border-t-4' ?>"<?php if ($layout !== 'minimal'): ?> style="border-color:<?= e($t['dark']) ?>"<?php endif; ?>>
      <p class="text-center font-bold wrap-any" style="color:<?= e($t['dark']) ?>"><?= e($name) ?></p>
      <?php if ($oneLine): ?>
        <p class="mt-1.5 text-center text-[13px] text-slate-500 wrap-any"><?= e(implode('  ·  ', $oneLine)) ?></p>
      <?php endif; ?>
    </footer>
    </div>
  </div>
</article>
</div>
<?php
}

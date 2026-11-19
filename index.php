<?php
require_once 'includes/auth.php';
require_once 'includes/document.php';

// The landing page always opens here and always shows the Login button.
$cta      = 'pages/register.php?next=quotation_form.php';
$title    = 'Quotations and invoices made simple';

$features = [
    ['file',     'Quotations in minutes',      'Fill in the recipient, add items, and the total is worked out for you. No spreadsheets, no retyping.'],
    ['box',      'Saved products and services','Add what you sell once. Pick it from the Item box on any quotation and the price fills in automatically.'],
    ['receipt',  'One-click invoices',         'When a customer says yes, turn the quotation into an invoice with a single button.'],
    ['download', 'PDF download and print',     'Download a clean A4 PDF or print it. Long notices and addresses wrap neatly and never run off the page.'],
    ['building', 'Your details on every page', 'Company address, phone, and bank details appear on every quotation and invoice.'],
    ['users',    'Team accounts',              'Add staff, give them their own logins, and reset passwords whenever someone forgets.'],
];

$highlights = [
    ['Made for phones',     'Create and send quotations from your phone as easily as from a computer.'],
    ['Automatic totals',    'Unit price times quantity, line by line, with the grand total always correct.'],
    ['Red notice box',      'Payment terms, validity and delivery notes stand out on the document.'],
    ['Status tracking',     'Mark each quotation as draft, sent or rejected and find it again quickly.'],
];

$steps = [
    ['Create your account', 'Sign up with your company name, your email, and a password.'],
    ['Add your products',   'List the products and services you sell with their prices.'],
    ['Build a quotation',   'Pick items, set quantities, and add a notice if you need one.'],
    ['Send and invoice',    'Download or print the quotation, then generate the invoice.'],
];

$faq = [
    ['Do I need to install anything?', 'No. It runs in the browser on your computer or phone. The server side needs PHP and MySQL, which XAMPP provides.'],
    ['Can I download a quotation as a PDF?', 'Yes. Open a quotation or invoice and press Download PDF. You can also press Print.'],
    ['How does a quotation become an invoice?', 'Open the quotation and press Generate invoice. The items and total are copied over, so nothing is typed twice.'],
    ['Can my staff use it too?', 'Yes. Admins can add staff accounts from the Users page and reset a password if someone forgets it.'],
    ['Does it work on a phone?', 'Yes. Every page adjusts to small screens, including the quotation form.'],
];

$sample = [
    'type'              => 'Quotation',
    'label_to'          => 'Billed to',
    'recipient'         => 'Silva Traders',
    'recipient_address' => "12 Galle Road\nColombo 03",
    'contact_email'     => 'nimal@example.com',
    'meta'              => [['Quotation No', 'Q-0012'], ['Date', date('Y-m-d')]],
    'company'           => [
        'company_name'   => 'Sample Company (Pvt) Ltd',
        'address'        => "45 Main Street\nColombo 07",
        'phone_no'       => '011 234 5678',
        'bank_name'      => 'Sample Bank',
        'account_holder' => 'Sample Company (Pvt) Ltd',
        'account_no'     => '0123456789',
    ],
    'items' => [
        ['name' => 'Website design', 'description' => 'Up to 5 pages, mobile friendly', 'unit_price' => 85000, 'quantity' => 1],
        ['name' => 'Logo design',    'description' => 'Two concepts, one revision',     'unit_price' => 25000, 'quantity' => 1],
        ['name' => 'Monthly hosting','description' => 'First 12 months',                'unit_price' => 3500,  'quantity' => 12],
    ],
    'subtotal'    => 152000,
    'adjustments' => [['Discount (10%)', '- Rs. 15,200.00'], ['VAT (18%)', 'Rs. 24,624.00']],
    'total'  => 161424,
    'notice' => "This quotation is valid for 14 days.\n50% advance payment is required to start work.",
];
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head><?php include __DIR__ . '/includes/head.php'; ?></head>
<body id="top" class="font-sans bg-white text-slate-800">

<?php
$btnLight = 'inline-flex items-center justify-center gap-2 rounded-xl bg-white text-brand-900 font-bold px-7 py-3.5 hover:bg-brand-100 transition';
$btnGhost = 'inline-flex items-center justify-center gap-2 rounded-xl border border-white/40 text-white font-semibold px-7 py-3.5 hover:bg-white/10 transition';
$btnDark  = 'inline-flex items-center justify-center gap-2 rounded-xl bg-brand-900 text-white font-bold px-7 py-3.5 hover:bg-brand-700 transition';
?>

<!-- Navigation -->
<header class="sticky top-0 z-30 bg-white text-brand-900 border-b border-slate-200">
  <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
    <a href="index.php?home=1" class="flex items-center gap-3">
      <?= logo_mark('w-9 h-9', 'dark') ?>
      <span class="font-extrabold text-brand-900">Quotation System</span>
    </a>

    <nav class="hidden md:flex items-center gap-8 text-sm font-semibold text-slate-600">
      <a href="#features" class="hover:text-brand-900">Features</a>
      <a href="#how" class="hover:text-brand-900">How it works</a>
      <a href="#preview" class="hover:text-brand-900">Sample</a>
      <a href="#faq" class="hover:text-brand-900">FAQ</a>
    </nav>

    <div class="hidden md:flex items-center gap-3">
      <a href="pages/login.php" class="text-sm font-semibold text-brand-900 px-3 py-2 hover:underline">Login</a>
      <a href="<?= $cta ?>" class="rounded-xl bg-brand-900 text-white font-bold px-4 py-2 text-sm hover:bg-brand-700 transition">Get started</a>
    </div>

    <button id="menuBtn" class="md:hidden p-2 -mr-2 text-brand-900" aria-label="Open menu" aria-expanded="false">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
  </div>

  <div id="mobileNav" class="hidden md:hidden border-t border-slate-200 bg-white text-brand-900 px-4 py-4 space-y-3 text-sm font-semibold">
    <a href="#features" class="block">Features</a>
    <a href="#how" class="block">How it works</a>
    <a href="#preview" class="block">Sample</a>
    <a href="#faq" class="block">FAQ</a>
    <a href="pages/login.php" class="flex justify-center rounded-xl border border-slate-300 px-4 py-2.5">Login</a>
    <a href="<?= $cta ?>" class="flex justify-center rounded-xl bg-brand-900 text-white font-bold px-4 py-2.5">Get started</a>
  </div>
</header>

<!-- Hero -->
<section class="bg-brand-900 text-white bg-pattern overflow-hidden">
  <div class="max-w-6xl mx-auto px-4 py-14 lg:py-24 grid lg:grid-cols-2 gap-12 items-center">
    <div class="min-w-0">
      <p class="inline-block rounded-full border border-white/25 px-4 py-1 text-xs font-semibold tracking-wide text-brand-200">Quotations and invoices for your business</p>
      <h1 class="mt-5 text-4xl sm:text-5xl font-extrabold leading-tight">Create professional quotations in minutes.</h1>
      <p class="mt-5 text-lg text-brand-200 max-w-xl">Add your products once, build a clean quotation in a few taps, and turn it into an invoice when your customer says yes.</p>

      <div class="mt-8 flex flex-col sm:flex-row gap-3">
        <a href="<?= $cta ?>" class="<?= $btnLight ?>">Create a quotation</a>
        <a href="pages/login.php" class="<?= $btnGhost ?>">Login</a>
      </div>

      <ul class="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-sm text-brand-200">
        <li class="flex items-center gap-2"><?= icon('check', 'w-4 h-4') ?>Works on phone and desktop</li>
        <li class="flex items-center gap-2"><?= icon('check', 'w-4 h-4') ?>PDF download and print</li>
        <li class="flex items-center gap-2"><?= icon('check', 'w-4 h-4') ?>Invoice in one click</li>
      </ul>
    </div>

    <div class="relative lg:pl-8 min-w-0">
      <div class="bg-white text-slate-800 rounded-2xl shadow-2xl shadow-black/40 p-5 sm:p-6 sm:rotate-1">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="font-extrabold text-brand-900">Sample Company</p>
            <p class="text-xs text-slate-500">Colombo 07</p>
          </div>
          <p class="text-xl sm:text-2xl font-extrabold tracking-widest text-brand-900/20">QUOTATION</p>
        </div>
        <div class="mt-5 flex gap-8 border-y border-slate-200 py-3 text-sm">
          <div><p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">No.</p><p class="font-bold">Q-0012</p></div>
          <div><p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">For</p><p class="font-bold">Silva Traders</p></div>
        </div>
        <div class="mt-3 text-sm divide-y divide-slate-100">
          <div class="flex justify-between gap-3 py-2"><span>Website design</span><span class="font-semibold">85,000.00</span></div>
          <div class="flex justify-between gap-3 py-2"><span>Logo design</span><span class="font-semibold">25,000.00</span></div>
          <div class="flex justify-between gap-3 py-2"><span>Monthly hosting x 12</span><span class="font-semibold">42,000.00</span></div>
        </div>
        <div class="mt-3 border-t-2 border-brand-900 pt-3 flex items-baseline justify-between">
          <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Total (Rs.)</span>
          <span class="text-2xl font-extrabold text-brand-900">152,000.00</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Quick highlights -->
<section class="bg-white border-b border-slate-200">
  <div class="max-w-6xl mx-auto px-4 py-10 grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
    <?php foreach ($highlights as [$head, $text]): ?>
      <div class="border-l-4 border-brand-900 pl-4">
        <h3 class="font-bold text-brand-900"><?= e($head) ?></h3>
        <p class="mt-1 text-sm text-slate-600"><?= e($text) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- Features -->
<section id="features" class="py-16 lg:py-24 bg-white">
  <div class="max-w-6xl mx-auto px-4">
    <div class="max-w-2xl">
      <h2 class="text-3xl font-extrabold text-brand-900">Everything you need to quote and bill</h2>
      <p class="mt-3 text-slate-600">One place for your products, your quotations, and your invoices.</p>
    </div>
    <div class="mt-10 grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
      <?php foreach ($features as [$ic, $head, $text]): ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 hover:border-brand-900 hover:shadow-lg transition">
          <div class="w-11 h-11 rounded-xl bg-brand-900 text-white grid place-items-center"><?= icon($ic, 'w-6 h-6') ?></div>
          <h3 class="mt-4 font-bold text-brand-900"><?= e($head) ?></h3>
          <p class="mt-2 text-sm text-slate-600 leading-relaxed"><?= e($text) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- How it works -->
<section id="how" class="py-16 lg:py-24 bg-brand-900 text-white bg-pattern">
  <div class="max-w-6xl mx-auto px-4">
    <h2 class="text-3xl font-extrabold">How it works</h2>
    <p class="mt-3 text-brand-200 max-w-2xl">From sign up to a paid invoice in four simple steps.</p>
    <div class="mt-10 grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
      <?php foreach ($steps as $i => [$head, $text]): ?>
        <div class="rounded-2xl bg-white text-slate-800 p-6">
          <div class="w-10 h-10 rounded-full bg-brand-900 text-white grid place-items-center font-extrabold"><?= $i + 1 ?></div>
          <h3 class="mt-4 font-bold text-brand-900"><?= e($head) ?></h3>
          <p class="mt-2 text-sm text-slate-600 leading-relaxed"><?= e($text) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Sample quotation -->
<section id="preview" class="py-16 lg:py-24 bg-white">
  <div class="max-w-6xl mx-auto px-4">
    <div class="max-w-2xl mb-10">
      <h2 class="text-3xl font-extrabold text-brand-900">A quotation your customer can read at a glance</h2>
      <p class="mt-3 text-slate-600">Who it is from, who it is for, what it costs, and how to pay. Everything sits in a clear order on a clean white page.</p>
    </div>
    <?php render_document($sample); ?>
  </div>
</section>

<!-- Benefits band -->
<section class="bg-brand-900 text-white">
  <div class="max-w-6xl mx-auto px-4 py-14 grid md:grid-cols-3 gap-8">
    <div><h3 class="text-xl font-bold">Save time</h3><p class="mt-2 text-brand-200 text-sm leading-relaxed">Saved items and automatic totals mean you stop retyping the same prices.</p></div>
    <div><h3 class="text-xl font-bold">Look professional</h3><p class="mt-2 text-brand-200 text-sm leading-relaxed">Every document has the same clean layout with your company and bank details.</p></div>
    <div><h3 class="text-xl font-bold">Stay organised</h3><p class="mt-2 text-brand-200 text-sm leading-relaxed">Search past quotations, check their status, and find the matching invoice quickly.</p></div>
  </div>
</section>

<!-- FAQ -->
<section id="faq" class="py-16 lg:py-24 bg-white">
  <div class="max-w-3xl mx-auto px-4">
    <h2 class="text-3xl font-extrabold text-brand-900">Questions</h2>
    <div class="mt-8 divide-y divide-slate-200 border-y border-slate-200">
      <?php foreach ($faq as [$q, $a]): ?>
        <details class="group py-4">
          <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-brand-900">
            <?= e($q) ?>
            <span class="text-brand-900 transition group-open:rotate-45 text-2xl leading-none">+</span>
          </summary>
          <p class="mt-3 text-sm text-slate-600 leading-relaxed"><?= e($a) ?></p>
        </details>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Call to action -->
<section class="px-4 pb-16 lg:pb-24 bg-white">
  <div class="max-w-6xl mx-auto rounded-3xl bg-brand-900 text-white bg-pattern px-6 py-14 text-center">
    <h2 class="text-3xl font-extrabold">Create your first quotation today</h2>
    <p class="mt-3 text-brand-200">It takes a couple of minutes to set up your company and send your first quote.</p>
    <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
      <a href="<?= $cta ?>" class="<?= $btnLight ?>">Create a quotation</a>
      <a href="pages/login.php" class="<?= $btnGhost ?>">Login</a>
    </div>
  </div>
</section>

<!-- Footer -->
<footer class="bg-brand-900 text-brand-200">
  <div class="max-w-6xl mx-auto px-4 pt-12 pb-8">
    <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
      <div class="lg:col-span-2 max-w-sm">
        <div class="flex items-center gap-3">
          <?= logo_mark('w-9 h-9', 'light') ?>
          <span class="font-extrabold text-white text-lg">Quotation System</span>
        </div>
        <p class="mt-4 text-sm leading-relaxed">Create professional quotations, turn them into invoices, and keep every customer document in one place.</p>
      </div>

      <div>
        <h4 class="text-white font-bold text-sm uppercase tracking-wider">Explore</h4>
        <ul class="mt-4 space-y-2 text-sm">
          <li><a href="#features" class="hover:text-white">Features</a></li>
          <li><a href="#how" class="hover:text-white">How it works</a></li>
          <li><a href="#preview" class="hover:text-white">Sample</a></li>
          <li><a href="#faq" class="hover:text-white">FAQ</a></li>
        </ul>
      </div>

      <div>
        <h4 class="text-white font-bold text-sm uppercase tracking-wider">Account</h4>
        <ul class="mt-4 space-y-2 text-sm">
          <li><a href="pages/login.php" class="hover:text-white">Login</a></li>
          <li><a href="<?= $cta ?>" class="hover:text-white">Create an account</a></li>
          <li><a href="pages/forgot_password.php" class="hover:text-white">Forgot password</a></li>
        </ul>
      </div>
    </div>

    <div class="mt-10 pt-6 border-t border-white/10 flex flex-col sm:flex-row items-center justify-between gap-2 text-xs">
      <p>&copy; <?= date('Y') ?> Quotation System. All rights reserved.</p>
      <a href="#top" class="hover:text-white">Back to top &uarr;</a>
    </div>
  </div>
</footer>

<script>
  const menuBtn = document.getElementById('menuBtn');
  const mobileNav = document.getElementById('mobileNav');
  menuBtn.addEventListener('click', () => {
    const open = mobileNav.classList.toggle('hidden') === false;
    menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
  mobileNav.querySelectorAll('a[href^="#"]').forEach(a => a.addEventListener('click', () => mobileNav.classList.add('hidden')));
</script>
</body>
</html>

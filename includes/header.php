<?php
$nav = [
    'dashboard'  => ['Dashboard',           'dashboard.php',  'home'],
    'quotations' => ['Quotations',          'quotations.php', 'file'],
    'invoices'   => ['Invoices',            'invoices.php',   'receipt'],
    'items'      => ['Products & services', 'items.php',      'box'],
];
// Company and Users pages are for admins only: staff do not see them in the menu
if (($_SESSION['user_type'] ?? '') === 'admin') {
    $nav['company'] = ['Company', 'company.php', 'building'];
    $nav['users']   = ['Users', 'users.php', 'users'];
}
$initial = strtoupper(substr($_SESSION['name'] ?? 'U', 0, 1));
$flash   = take_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head><?php include __DIR__ . '/head.php'; ?></head>
<body class="font-sans bg-slate-50 text-slate-800">
<script>try{if(localStorage.getItem("sbCollapsed")==="1")document.body.classList.add("sb-collapsed")}catch(e){}</script>

<div id="overlay" class="no-print hidden fixed inset-0 bg-black/40 z-30 lg:hidden"></div>

<aside id="sidebar"
       class="no-print fixed inset-y-0 left-0 z-40 w-64 bg-brand-900 text-white flex flex-col -translate-x-full lg:translate-x-0 transition-[transform,width] duration-200">
  <div class="flex items-center justify-between px-5 h-16 border-b border-white/10">
    <div class="sb-brand flex items-center gap-3 min-w-0">
      <?= logo_mark('w-9 h-9', 'light') ?>
      <span class="sb-label font-bold truncate">Quotation System</span>
    </div>
    <button id="collapseSidebar" type="button" class="hidden lg:grid place-items-center w-8 h-8 rounded-lg text-brand-200 hover:bg-white/10 hover:text-white" aria-label="Collapse menu" title="Collapse / expand menu">
      <svg class="sb-chevron w-5 h-5 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 6l-6 6 6 6"/></svg>
    </button>
    <button id="closeSidebar" class="lg:hidden text-2xl leading-none text-brand-200" aria-label="Close menu">&times;</button>
  </div>

  <nav class="flex-1 p-3 space-y-1 overflow-y-auto">
    <?php foreach ($nav as $key => [$label, $href, $ic]): ?>
      <a href="<?= $href ?>"
         class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-semibold transition
                <?= $key === ($page ?? '') ? 'bg-brand-600 text-white' : 'text-brand-200 hover:bg-white/10 hover:text-white' ?>"
         title="<?= e($label) ?>">
        <?= icon($ic, 'w-5 h-5 shrink-0') ?><span class="sb-label"><?= e($label) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="p-4 border-t border-white/10">
    <div class="flex items-center gap-3 mb-3">
      <div class="w-10 h-10 shrink-0 rounded-full bg-brand-500 grid place-items-center font-bold"><?= e($initial) ?></div>
      <div class="sb-label min-w-0">
        <p class="text-sm font-semibold truncate"><?= e($_SESSION['name'] ?? '') ?></p>
        <p class="text-xs text-brand-200"><?= e(ucfirst($_SESSION['user_type'] ?? '')) ?></p>
      </div>
    </div>
    <a href="logout.php" class="flex items-center justify-center gap-2 rounded-xl bg-white/10 hover:bg-white/20 text-sm font-semibold py-2">
      <?= icon('logout', 'w-4 h-4 shrink-0') ?><span class="sb-label">Sign out</span>
    </a>
  </div>
</aside>

<div class="lg:pl-64 min-h-screen flex flex-col">
  <header class="no-print lg:hidden sticky top-0 z-20 bg-white border-b border-slate-200 h-14 flex items-center px-4 gap-3">
    <button id="openSidebar" aria-label="Open menu" class="p-2 -ml-2 text-brand-700">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
    <span class="font-bold text-brand-900">Quotation System</span>
  </header>

  <main class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8">
    <?php if ($flash): ?>
      <div class="no-print mb-5 rounded-xl px-4 py-3 text-sm font-semibold border
                  <?= $flash['type'] === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-800' ?>" role="status">
        <?= e($flash['msg']) ?>
      </div>
    <?php endif; ?>

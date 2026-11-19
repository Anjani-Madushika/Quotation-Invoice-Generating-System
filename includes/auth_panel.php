<?php // Shared by login / sign up: blue brand panel on desktop, slim banner on phones ?>
<aside class="auth-gradient hidden lg:flex relative overflow-hidden min-h-0 flex-col justify-between text-white p-10 xl:p-14">
  <span aria-hidden="true" class="absolute -top-32 -right-24 w-[28rem] h-[28rem] rounded-full" style="background:rgba(255,255,255,.07)"></span>

  <div class="relative flex items-center gap-4">
    <?= logo_mark('w-14 h-14', 'light') ?>
    <span aria-hidden="true" class="w-px h-10 bg-white/30"></span>
    <span class="text-2xl leading-tight"><span class="font-bold">Quotation</span> <span class="font-light">System</span></span>
  </div>

  <div class="relative">
    <h1 class="text-4xl xl:text-5xl font-extrabold leading-tight max-w-md">Quotations and invoices, in one place.</h1>
    <p class="mt-5 text-brand-200 max-w-md text-lg leading-relaxed">Create a quotation, send it, and turn it into an invoice without retyping anything.</p>

    <!-- Mini quotation, same look as the real document -->
    <div class="mt-10 max-w-sm bg-white text-slate-800 rounded-2xl shadow-2xl shadow-black/30 overflow-hidden font-inter">
      <div class="auth-gradient px-5 py-3 flex items-center justify-between text-white">
        <span class="text-xs font-light tracking-wide">Silva Traders</span>
        <span class="text-sm font-extrabold uppercase tracking-[0.12em]">Quotation</span>
      </div>
      <div class="p-5">
        <p class="text-sm"><span class="font-bold">Quotation No:</span> Q-0012</p>
        <div class="mt-4 border-t-4 border-brand-900"></div>
        <div class="flex items-center justify-between border-b border-slate-200 py-2 text-sm">
          <span>1. Website design</span><span class="text-slate-500">Rs. 85,000.00</span>
        </div>
        <div class="flex items-center justify-between border-b border-slate-200 py-2 text-sm">
          <span class="text-slate-500">Discount (10%)</span><span class="font-semibold">- Rs. 8,500.00</span>
        </div>
        <div class="flex items-center justify-between border-b border-slate-200 py-2 text-sm">
          <span class="text-slate-500">VAT (18%)</span><span class="font-semibold">Rs. 13,770.00</span>
        </div>
        <p class="mt-4 text-[11px] font-bold uppercase tracking-[0.16em] text-brand-600">Quotation total</p>
        <p class="text-right text-2xl font-extrabold text-brand-900">Rs. 90,270.00</p>
      </div>
    </div>
  </div>

  <p class="relative text-sm text-brand-200">&copy; <?= date('Y') ?> Quotation System</p>
</aside>

<div class="auth-gradient lg:hidden flex items-center gap-3 px-5 py-4 text-white">
  <?= logo_mark('w-10 h-10', 'light') ?>
  <span class="text-lg leading-tight"><span class="font-bold">Quotation</span> <span class="font-light">System</span></span>
</div>

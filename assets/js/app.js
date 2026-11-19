// Show / hide password (login page)
const togglePw = document.getElementById('togglePw');
if (togglePw) {
  togglePw.addEventListener('click', () => {
    const input = document.getElementById('password');
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    // crossed eye = password is hidden, open eye = password is visible
    document.getElementById('eyeOpen').classList.toggle('hidden', !show);
    document.getElementById('eyeClosed').classList.toggle('hidden', show);
    togglePw.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
  });
}

// Mobile sidebar (dashboard)
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('overlay');
const openBtn = document.getElementById('openSidebar');
const closeBtn = document.getElementById('closeSidebar');

function setSidebar(open) {
  if (!sidebar) return;
  sidebar.classList.toggle('-translate-x-full', !open);
  overlay.classList.toggle('hidden', !open);
}
if (openBtn)  openBtn.addEventListener('click', () => setSidebar(true));
if (closeBtn) closeBtn.addEventListener('click', () => setSidebar(false));
if (overlay)  overlay.addEventListener('click', () => setSidebar(false));

// A4 sheets (210mm = 794px) shrink to fit narrow screens instead of changing layout
(function () {
  const A4_PX = 794;
  function fit() {
    document.querySelectorAll('[data-a4-fit]').forEach(function (el) {
      el.style.zoom = '';
      const host = el.parentElement;
      const avail = host ? host.clientWidth : A4_PX;
      if (avail && avail < A4_PX) { el.style.zoom = String(Math.max(0.35, avail / A4_PX)); }
    });
  }
  window.fitA4 = fit;
  window.addEventListener('resize', fit);
  window.addEventListener('load', fit);
  window.addEventListener('beforeprint', function () {
    document.querySelectorAll('[data-a4-fit]').forEach(function (el) { el.style.zoom = ''; });
  });
  window.addEventListener('afterprint', fit);
  fit();
})();

// Status dropdown on the quotation page: the list floats over the page (does not push the quotation down)
document.querySelectorAll('[data-status-dd]').forEach(function (dd) {
  const btn = dd.querySelector('[data-dd-btn]');
  const list = dd.querySelector('[data-dd-list]');
  const input = dd.querySelector('input[name="status"]');
  const label = dd.querySelector('[data-dd-label]');
  const arrow = dd.querySelector('[data-dd-arrow]');
  function toggle(open) {
    list.classList.toggle('hidden', !open);
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (arrow) arrow.style.transform = open ? 'rotate(180deg)' : '';
  }
  btn.addEventListener('click', function () { toggle(list.classList.contains('hidden')); });
  list.querySelectorAll('[role="option"]').forEach(function (li) {
    li.addEventListener('click', function () {
      input.value = li.dataset.value;
      label.textContent = li.textContent;
      list.querySelectorAll('[role="option"]').forEach(function (o) {
        const on = o === li;
        o.setAttribute('aria-selected', on ? 'true' : 'false');
        o.classList.toggle('font-bold', on);
        o.classList.toggle('text-brand-700', on);
      });
      toggle(false);
    });
  });
  document.addEventListener('click', function (e) { if (!dd.contains(e.target)) toggle(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') toggle(false); });
});

// Collapse / expand the sidebar on desktop (remembered between pages)
(function () {
  const btn = document.getElementById('collapseSidebar');
  if (!btn) return;
  btn.addEventListener('click', function () {
    const on = document.body.classList.toggle('sb-collapsed');
    try { localStorage.setItem('sbCollapsed', on ? '1' : '0'); } catch (e) {}
    btn.setAttribute('aria-label', on ? 'Expand menu' : 'Collapse menu');
  });
})();

// Modals (<dialog>): a button with data-modal-open="<dialog id>" opens it and copies its data-* values into the
// dialog's fields (data-fill="x" inputs <- data-x, data-fill-text="x" text <- data-x). data-modal-close closes it.
document.querySelectorAll('[data-modal-open]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    const dlg = document.getElementById(btn.dataset.modalOpen);
    if (!dlg) return;
    dlg.querySelectorAll('[data-fill]').forEach(function (f) {
      const v = btn.dataset[f.dataset.fill.replace(/-(\w)/g, (m, c) => c.toUpperCase())];
      if (v !== undefined) f.value = v;
    });
    dlg.querySelectorAll('[data-fill-text]').forEach(function (f) {
      const v = btn.dataset[f.dataset.fillText];
      if (v !== undefined) f.textContent = v;
    });
    // your own role cannot be changed (keeps at least one admin)
    const roleBox = dlg.querySelector('[data-role-box]');
    if (roleBox) roleBox.classList.toggle('hidden', btn.dataset.self === '1');
    dlg.showModal();
  });
});
document.querySelectorAll('dialog').forEach(function (dlg) {
  dlg.addEventListener('click', function (e) {
    if (e.target === dlg || e.target.closest('[data-modal-close]')) dlg.close();
  });
});

// Show / hide password: a [data-pw-toggle] button flips the password box next to it
document.querySelectorAll('[data-pw-toggle]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    const input = btn.parentElement.querySelector('input');
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    // crossed eye = password is hidden, open eye = password is visible
    btn.querySelector('[data-eye-on]').classList.toggle('hidden', !show);
    btn.querySelector('[data-eye-off]').classList.toggle('hidden', show);
  });
});

// Confirm password: block the form when the two boxes differ
document.querySelectorAll('form').forEach(function (form) {
  const a = form.querySelector('[name="password"]');
  const b = form.querySelector('[name="password_confirm"]');
  if (!a || !b) return;
  function check() { b.setCustomValidity(a.value === b.value ? '' : 'The two passwords do not match.'); }
  a.addEventListener('input', check);
  b.addEventListener('input', check);
  form.addEventListener('submit', check);
});

// Quotation form: item rows, item picker, totals and the live preview.
// pages/quotation_form.php hands over its data in window.QUOTATION_FORM before this file loads:
//   products = saved items, initial = rows of the quotation being edited, INPUT = input CSS classes,
//   UNITS / WHOLE = unit list and the units that only take whole numbers, QID = quotation id (0 = new).
const { products, initial, INPUT, UNITS, WHOLE, QID } = window.QUOTATION_FORM;
const UNIT_OPTS = Object.keys(UNITS).map(k => '<option value="' + k + '">' + UNITS[k] + '</option>').join('');
const wrap  = document.getElementById('rows');
const grand = document.getElementById('grand');
const fmt = n => n.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});


const pctNum = id => Math.min(100, Math.max(0, parseFloat(document.getElementById(id).value) || 0));
const round2 = n => Math.round((n + Number.EPSILON) * 100) / 100;

function recalc() {
  let sum = 0;
  wrap.querySelectorAll('.item-row').forEach(row => {
    const price = parseFloat(row.querySelector('[name="item_price[]"]').value) || 0;
    const qty   = parseFloat(row.querySelector('[name="item_qty[]"]').value) || 0;
    const line  = round2(price * qty);
    row.querySelector('.line-total').textContent = fmt(line);
    sum += line;
  });
  const dp   = pctNum('discount_percent');
  const vp   = pctNum('vat_percent');
  const disc = round2(sum * dp / 100);
  const vat  = round2((sum - disc) * vp / 100);
  document.getElementById('sub').textContent   = fmt(sum);
  document.getElementById('disc').textContent  = fmt(disc);
  document.getElementById('vat').textContent   = fmt(vat);
  document.getElementById('dpLbl').textContent = String(dp);
  document.getElementById('vpLbl').textContent = String(vp);
  grand.textContent = fmt(round2(sum - disc + vat));
  schedulePreview();
}

// ---- Live preview (server renders the real quotation layout from what is typed) ----
const qform = document.getElementById('qform');
const pvBox = document.getElementById('preview');
const pvState = document.getElementById('pvState');
let pvTimer = null, pvSeq = 0, pvCtl = null;

function schedulePreview() {
  clearTimeout(pvTimer);
  pvTimer = setTimeout(loadPreview, 350);
}

async function loadPreview() {
  const seq = ++pvSeq;
  if (pvCtl) pvCtl.abort();
  pvCtl = new AbortController();
  const fd = new FormData(qform);
  fd.append('id', String(QID));
  pvState.textContent = 'Updating\u2026';
  try {
    const res = await fetch('quotation_preview.php', { method: 'POST', body: fd, signal: pvCtl.signal, credentials: 'same-origin' });
    if (!res.ok) throw new Error('preview failed');
    const html = await res.text();
    if (seq !== pvSeq) return;
    pvBox.innerHTML = html;
    if (window.fitA4) window.fitA4();
    pvState.textContent = '';
  } catch (err) {
    if (err && err.name === 'AbortError') return;
    if (seq === pvSeq) pvState.textContent = 'Preview unavailable';
  }
}
qform.addEventListener('input', schedulePreview);
qform.addEventListener('change', schedulePreview);
['discount_percent', 'vat_percent'].forEach(id => document.getElementById(id).addEventListener('input', recalc));

const normName = v => v.replace(/\s+/g, ' ').trim().toLowerCase();

// names already used in the OTHER rows
function usedNames(exceptRow) {
  const set = new Set();
  wrap.querySelectorAll('.item-row').forEach(r => {
    if (r === exceptRow) return;
    const v = normName(r.querySelector('[name="item_name[]"]').value);
    if (v) set.add(v);
  });
  return set;
}

// Same item twice in one quotation is not allowed: mark the repeated row and stop saving
qform.addEventListener('submit', ev => {
  const seen = new Set();
  let dupRow = null;
  wrap.querySelectorAll('.item-row').forEach(r => {
    const inp = r.querySelector('[name="item_name[]"]');
    const v = normName(inp.value);
    inp.classList.remove('border-rose-500');
    if (!v) return;
    if (seen.has(v)) { dupRow = dupRow || r; inp.classList.add('border-rose-500'); }
    seen.add(v);
  });
  if (dupRow) {
    ev.preventDefault();
    alert('The same item is added more than once. Use one row and change the quantity.');
    dupRow.querySelector('[name="item_name[]"]').focus();
  }
});

function addRow(r) {
  r = r || {};
  const row = document.createElement('div');
  row.className = 'item-row ir rounded-xl border border-slate-200 p-3';
  const LBL = '<span class="ir-lbl block text-xs font-semibold text-slate-500 mb-1">';
  row.innerHTML =
    '<div class="ir-item relative min-w-0">' + LBL + 'Item</span>' +
      '<div class="relative">' +
        '<input type="hidden" name="item_id[]">' +
        '<input name="item_name[]" autocomplete="off" placeholder="Choose or type an item" class="' + INPUT + ' pr-12">' +
        '<button type="button" class="toggle absolute inset-y-0 right-0 px-3 text-slate-500 hover:text-brand-700" aria-label="Show saved items"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg></button>' +
        '<div class="picker hidden absolute z-20 left-0 right-0 top-full mt-1 max-h-60 overflow-auto rounded-xl border border-slate-200 bg-white shadow-lg"></div>' +
      '</div>' +
    '</div>' +
    '<div class="ir-price min-w-0">' + LBL + 'Unit price</span><input name="item_price[]" type="number" inputmode="decimal" step="0.01" min="0" placeholder="Unit price" class="' + INPUT + '"></div>' +
    '<div class="ir-qty min-w-0">' + LBL + 'Qty</span><input name="item_qty[]" type="number" inputmode="numeric" step="1" min="1" placeholder="Qty" class="' + INPUT + ' !px-2"></div>' +
    '<div class="ir-unit min-w-0">' + LBL + 'Unit</span><select name="item_unit[]" class="' + INPUT + ' !px-2">' + UNIT_OPTS + '</select></div>' +
    '<div class="ir-amt min-w-0">' + LBL + 'Amount</span><div class="ir-amt-val font-bold text-brand-900 break-all">Rs. <span class="line-total">0.00</span></div></div>' +
    '<div class="ir-rm"><button type="button" class="remove text-sm font-semibold text-rose-600 hover:underline py-2">Remove</button></div>' +
    '<div class="ir-desc"><input name="item_desc[]" placeholder="Description (optional)" class="' + INPUT + '"></div>';

  const hid   = row.querySelector('[name="item_id[]"]');
  const input = row.querySelector('[name="item_name[]"]');
  const price = row.querySelector('[name="item_price[]"]');
  const qty   = row.querySelector('[name="item_qty[]"]');
  const unit  = row.querySelector('[name="item_unit[]"]');
  const box   = row.querySelector('.picker');

  // whole-number units (pcs, box ...) only take 1, 2, 3 ...; kg, g, l, m, hr ... take decimals (2.5)
  function applyUnit() {
    const whole = WHOLE.includes(unit.value);
    qty.step = whole ? '1' : 'any';
    qty.min  = whole ? '1' : '0.001';
    qty.inputMode = whole ? 'numeric' : 'decimal';
    if (whole && qty.value !== '') qty.value = String(Math.max(1, Math.round(parseFloat(qty.value) || 1)));
  }

  hid.value   = r.item_id == null ? '' : String(r.item_id);
  input.value = r.name || '';
  price.value = r.price != null ? r.price : '';
  unit.value  = (r.unit && UNITS[r.unit] !== undefined) ? r.unit : '';
  qty.value   = r.qty || 1;
  applyUnit();
  row.querySelector('[name="item_desc[]"]').value = r.description || '';

  function choose(p) {
    hid.value   = p.item_id;
    input.value = p.name;
    price.value = p.price;
    unit.value  = (p.unit && UNITS[p.unit] !== undefined) ? p.unit : '';
    applyUnit();
    box.classList.add('hidden');
    recalc();
    qty.focus();
  }

  function showPicker(all) {
    const term = all ? '' : input.value.trim().toLowerCase();
    const used = usedNames(row);
    const list = products.filter(p => p.name.toLowerCase().includes(term) && !used.has(p.name.trim().toLowerCase()));
    box.innerHTML = '';
    if (!products.length) {
      box.innerHTML = '<p class="px-4 py-3 text-sm text-slate-500">No saved items yet. Type a name to add a custom item, or add items in Products &amp; services.</p>';
    } else if (!list.length) {
      box.innerHTML = '<p class="px-4 py-3 text-sm text-slate-500">No match. Keep typing to use it as a new item.</p>';
    } else {
      list.forEach(p => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'w-full flex items-center justify-between gap-3 px-4 py-2.5 text-left text-sm hover:bg-brand-50';
        const n = document.createElement('span');
        n.textContent = p.name;
        const pr = document.createElement('span');
        pr.className = 'text-slate-500 whitespace-nowrap';
        pr.textContent = 'Rs. ' + fmt(parseFloat(p.price)) + (p.unit && UNITS[p.unit] ? ' / ' + UNITS[p.unit] : '');
        b.append(n, pr);
        b.addEventListener('pointerdown', ev => { ev.preventDefault(); choose(p); });
        box.appendChild(b);
      });
    }
    box.classList.remove('hidden');
  }

  input.addEventListener('focus', () => showPicker(false));
  input.addEventListener('input', () => { hid.value = ''; showPicker(false); });
  input.addEventListener('blur', () => setTimeout(() => box.classList.add('hidden'), 150));
  row.querySelector('.toggle').addEventListener('pointerdown', ev => {
    ev.preventDefault();
    if (box.classList.contains('hidden')) { showPicker(true); } else { box.classList.add('hidden'); }
  });

  row.addEventListener('input', recalc);
  unit.addEventListener('change', () => { applyUnit(); recalc(); });
  row.querySelector('.remove').addEventListener('click', () => {
    row.remove();
    if (!wrap.children.length) addRow();
    recalc();
  });

  wrap.appendChild(row);
  recalc();
  return row;
}

// Add item: the new row goes under the last one, scrolls into view and is ready to type in
document.getElementById('addRow').addEventListener('click', () => {
  const row = addRow();
  row.scrollIntoView({ behavior: 'smooth', block: 'center' });
  row.querySelector('[name="item_name[]"]').focus();
});

(initial.length ? initial : [{}]).forEach(addRow);

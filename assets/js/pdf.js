// Download a quotation or invoice as a PDF file.
// Uses html2pdf.js (loaded from a CDN). If it can't load, falls back to Print > Save as PDF.
//
// Page rules:
//   * Everything fits on one A4 sheet  -> exactly ONE page (footer at the bottom, no empty 2nd sheet)
//   * Too many items for one sheet     -> the extra rows continue on the next A4 sheet
//                                         (a row is never cut in half, the table heading repeats,
//                                          totals + notice + footer always stay together on the last sheet)
(function () {
  const A4_WIDTH_PX = 794;
  // A4 at 794px wide is 1122.9px tall. 1122 keeps every page canvas just under one A4 page,
  // so html2pdf can never spill a thin sliver onto an extra sheet.
  const PAGE_PX = 1122;

  // Split the long document into separate A4-sized page elements (rows are moved, never cut)
  function buildPages(copy, holder) {
    const banner = copy.querySelector(':scope > header');
    const body   = copy.querySelector(':scope > div');
    if (!banner || !body) return [copy];

    const kids      = Array.from(body.children);
    const itemsWrap = kids.find(function (k) { return k.querySelector('table'); });
    if (!itemsWrap) return [copy];
    const at    = kids.indexOf(itemsWrap);
    const top   = kids.slice(0, at);        // details + billed to
    const tail  = kids.slice(at + 1);       // totals, notice + footer
    const table = itemsWrap.querySelector('table');
    const thead = table.querySelector('thead');
    const tbodyClass = table.querySelector('tbody').className;
    const rows  = Array.from(table.querySelectorAll('tbody > tr'));
    const pages = [];

    function fits(pg) { return pg.wrap.offsetTop + pg.wrap.offsetHeight <= PAGE_PX; }

    function makePage(first) {
      const el = document.createElement('article');
      el.className = copy.className.replace(/\bpdf-compact\b/, '');
      el.style.cssText = 'display:block;position:relative;overflow:hidden;background:#fff;margin:0;' +
        'overflow-wrap:break-word;width:' + A4_WIDTH_PX + 'px;height:' + PAGE_PX + 'px;';

      if (first) {
        el.appendChild(banner.cloneNode(true));
      } else {
        const strip = document.createElement('div');
        strip.style.cssText = 'height:14px;';
        strip.style.backgroundImage = getComputedStyle(banner).backgroundImage;
        el.appendChild(strip);
      }

      const wrap = document.createElement('div');
      wrap.className = body.className.replace(/\bflex-1\b/, '');
      wrap.style.overflowWrap = 'break-word';
      if (first) top.forEach(function (n) { wrap.appendChild(n.cloneNode(true)); });

      const shell = itemsWrap.cloneNode(false);
      if (!first) shell.style.marginTop = '0';
      const t = table.cloneNode(false);
      t.appendChild(thead.cloneNode(true));
      const tbody = document.createElement('tbody');
      tbody.className = tbodyClass;
      t.appendChild(tbody);
      shell.appendChild(t);
      wrap.appendChild(shell);
      el.appendChild(wrap);
      holder.appendChild(el);

      const pg = { el: el, wrap: wrap, shell: shell, tbody: tbody };
      pages.push(pg);
      return pg;
    }

    let cur = makePage(true);

    // 1) Items: add row by row, start a new A4 sheet when the next row would not fit
    rows.forEach(function (tr) {
      cur.tbody.appendChild(tr);
      if (!fits(cur) && cur.tbody.children.length > 1) {
        cur.tbody.removeChild(tr);
        cur = makePage(false);
        cur.tbody.appendChild(tr);
      }
    });

    // 2) Totals + notice + footer must stay together on the last sheet
    function addTail(pg) { tail.forEach(function (el) { pg.wrap.appendChild(el); }); }
    addTail(cur);
    if (!fits(cur)) {
      tail.forEach(function (el) { el.remove(); });
      // carry the last row over too, so the totals are never alone on a sheet
      const carry = cur.tbody.children.length > 1 ? cur.tbody.lastElementChild : null;
      const next  = makePage(false);
      if (carry) { next.tbody.appendChild(carry); } else { next.shell.remove(); }
      addTail(next);
    }

    // 3) Stretch each sheet to the full A4 height (pushes the footer to the bottom) + page numbers
    pages.forEach(function (pg, i) {
      pg.wrap.style.minHeight = Math.max(0, PAGE_PX - pg.wrap.offsetTop) + 'px';
      if (pages.length > 1) {
        const lab = document.createElement('div');
        lab.textContent = 'Page ' + (i + 1) + ' of ' + pages.length;
        lab.style.cssText = 'position:absolute;right:40px;bottom:12px;font-size:10px;line-height:12px;color:#94a3b8;';
        pg.el.appendChild(lab);
      }
    });

    copy.remove();
    return pages.map(function (pg) { return pg.el; });
  }

  async function downloadPdf(btn) {
    const source = document.getElementById('doc');
    if (!source) return;

    const filename = (btn && btn.dataset.filename) || 'document.pdf';

    if (typeof html2pdf === 'undefined') {
      document.title = filename.replace(/\.pdf$/i, '');
      window.print();
      return;
    }

    const original = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.textContent = 'Preparing PDF...'; }

    // Draw an off-screen copy at a fixed width so phones and desktops give the same PDF
    const holder = document.createElement('div');
    holder.style.cssText = 'position:fixed;left:-10000px;top:0;background:#fff;overflow:hidden;width:' + A4_WIDTH_PX + 'px;';
    const copy = source.cloneNode(true);
    copy.classList.remove('a4-sheet');
    copy.removeAttribute('data-a4-fit');
    copy.style.zoom = '1';
    copy.style.boxShadow = 'none';
    copy.style.width = A4_WIDTH_PX + 'px';
    copy.style.maxWidth = 'none';
    copy.style.margin = '0';
    copy.style.borderRadius = '0';
    copy.style.overflow = 'hidden';
    copy.style.overflowWrap = 'anywhere';
    copy.style.wordBreak = 'break-word';
    // html2canvas only understands overflow-wrap: break-word (not "anywhere"), so set it on every element of the copy
    copy.querySelectorAll('*').forEach(function (el) {
      el.style.overflowWrap = 'break-word';
      el.style.wordBreak = 'normal';
      el.style.minWidth = '0';
    });
    // Fixed column widths so a very long item name can't stretch the table past the page
    copy.querySelectorAll('table').forEach(function (t) {
      t.style.tableLayout = 'fixed';
      t.style.minWidth = '0';
      t.style.width = '100%';
      const w = ['', '10%', '22%', '22%'];
      t.querySelectorAll('thead th').forEach(function (th, i) { if (w[i]) th.style.width = w[i]; });
    });
    holder.appendChild(copy);
    document.body.appendChild(holder);

    try {
      // Measure only after the fonts are ready, otherwise row heights are wrong
      if (document.fonts && document.fonts.ready) { await document.fonts.ready; }

      // ---- Page fitting ----
      const tight = document.createElement('style');
      tight.textContent =
        '.pdf-compact .py-5{padding-top:.5rem!important;padding-bottom:.5rem!important}' +
        '.pdf-compact .mt-9{margin-top:1rem!important}.pdf-compact .pt-9{padding-top:1.1rem!important}' +
        '.pdf-compact .pt-10{padding-top:.75rem!important}.pdf-compact .mb-8{margin-bottom:.75rem!important}' +
        '.pdf-compact .mt-8{margin-top:1rem!important}.pdf-compact .pb-8{padding-bottom:1rem!important}' +
        '.pdf-compact header{padding-top:1.4rem!important;padding-bottom:1.4rem!important}';
      holder.appendChild(tight);

      let pages;
      if (copy.scrollHeight > PAGE_PX && copy.scrollHeight <= PAGE_PX * 1.12) {
        copy.classList.add('pdf-compact');     // only a little too long: tighten the spacing to keep one sheet
      }
      if (copy.scrollHeight <= PAGE_PX) {
        copy.style.height = PAGE_PX + 'px';    // one sheet: footer sits at the bottom of the page
        copy.style.minHeight = PAGE_PX + 'px';
        pages = [copy];
      } else {
        copy.classList.remove('pdf-compact');
        pages = buildPages(copy, holder);      // really long: continue on the next A4 sheet(s)
      }

      const opt = {
        margin: 0,
        filename: filename,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: {
          scale: 2, useCORS: true, backgroundColor: '#ffffff',
          width: A4_WIDTH_PX, windowWidth: A4_WIDTH_PX, x: 0, y: 0, scrollX: 0, scrollY: 0,
          // html2pdf puts the copy in its own container whose width/margin depend on the screen; pin it to the A4 width at the left edge
          onclone: function (doc) {
            doc.querySelectorAll('.html2pdf__container').forEach(function (c) {
              c.style.width = A4_WIDTH_PX + 'px';
              c.style.maxWidth = 'none';
              c.style.margin = '0';
              c.style.left = '0';
              c.style.right = 'auto';
              c.style.transform = 'none';
            });
            doc.querySelectorAll('.html2pdf__overlay').forEach(function (o) {
              o.style.overflow = 'visible';
            });
          }
        },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
        pagebreak: { mode: [], avoid: [] }     // pages are already cut to A4 above, html2pdf must not cut again
      };

      // Sheet 1 goes through html2pdf, which gives us the jsPDF document
      const pdf = await html2pdf().set(opt).from(pages[0]).toPdf().get('pdf');
      while (pdf.internal.getNumberOfPages() > 1) {        // safety: never keep a stray extra sheet
        pdf.deletePage(pdf.internal.getNumberOfPages());
      }

      // Further sheets: one A4 image per sheet
      for (let i = 1; i < pages.length; i++) {
        const canvas = await html2pdf().set(opt).from(pages[i]).toCanvas().get('canvas');
        pdf.addPage('a4', 'portrait');
        pdf.addImage(canvas.toDataURL('image/jpeg', 0.98), 'JPEG', 0, 0, 210, 297);
      }

      pdf.save(filename);
    } catch (err) {
      console.error(err);
      alert('Could not create the PDF. Use Print and choose "Save as PDF" instead.');
    } finally {
      holder.remove();
      if (btn) { btn.disabled = false; btn.innerHTML = original; }
    }
  }

  document.querySelectorAll('[data-pdf]').forEach(function (b) {
    b.addEventListener('click', function () { downloadPdf(b); });
  });

  // Links from the lists use ?download=1 to start the download straight away
  const params = new URLSearchParams(location.search);
  if (params.get('download') === '1') {
    const start = function () {
      const b = document.querySelector('[data-pdf]');
      params.delete('download');
      const qs = params.toString();
      history.replaceState(null, '', location.pathname + (qs ? '?' + qs : ''));
      if (b) downloadPdf(b);
    };
    if (document.readyState === 'complete') { start(); } else { window.addEventListener('load', start); }
  }
})();

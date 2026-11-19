<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_admin();

$cid = (int) $_SESSION['company_id'];

// Check the uploaded logo and turn it into a small data URI (stored in the database).
// Returns [dataUri|null, errorMessage]
function process_logo(array $f): array {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, ''];
    }
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 2 * 1024 * 1024) {
        return [null, 'The logo could not be uploaded. Use a PNG, JPG or WEBP image under 2 MB.'];
    }
    $info    = @getimagesize($f['tmp_name']);
    $allowed = [IMAGETYPE_PNG => 'image/png', IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_WEBP => 'image/webp'];
    if (!$info || !isset($allowed[$info[2]])) {
        return [null, 'The logo must be a PNG, JPG or WEBP image.'];
    }
    $raw  = file_get_contents($f['tmp_name']);
    $mime = $allowed[$info[2]];
    // Shrink big images (max 500px) so the database row stays small
    if (function_exists('imagecreatefromstring') && function_exists('imagepng')) {
        $img = @imagecreatefromstring($raw);
        if ($img) {
            $w = imagesx($img);
            $h = imagesy($img);
            if ($w > 500 || $h > 500) {
                $r   = min(500 / $w, 500 / $h);
                $nw  = max(1, (int) round($w * $r));
                $nh  = max(1, (int) round($h * $r));
                $dst = imagecreatetruecolor($nw, $nh);
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
                imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
                ob_start();
                imagepng($dst);
                $raw  = ob_get_clean();
                $mime = 'image/png';
                imagedestroy($dst);
            }
            imagedestroy($img);
        }
    }
    if (strlen($raw) > 600 * 1024) {
        return [null, 'The logo is too large. Use an image under 600 KB.'];
    }
    return ['data:' . $mime . ';base64,' . base64_encode($raw), ''];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    post_csrf_or_die();
    $name = trim($_POST['company_name'] ?? '');
    if ($name === '') {
        flash('error', 'Enter the company name.');
        redirect('company.php');
    }
    $v = fn(string $k): ?string => (($t = trim($_POST[$k] ?? '')) !== '') ? $t : null;
    $pdo->prepare('UPDATE company SET company_name = ?, address = ?, phone_no = ?, bank_name = ?, account_holder = ?, account_no = ? WHERE company_id = ?')
        ->execute([$name, $v('address'), $v('phone_no'), $v('bank_name'), $v('account_holder'), $v('account_no'), $cid]);

    // Document colour (hex like #12306b)
    $tc = strtolower(trim($_POST['theme_color'] ?? ''));
    if (preg_match('/^#[0-9a-f]{6}$/', $tc)) {
        $pdo->prepare('UPDATE company SET theme_color = ? WHERE company_id = ?')->execute([$tc, $cid]);
    }

    // Document layout (modern / classic / minimal)
    $dl = $_POST['doc_layout'] ?? '';
    if (isset(DOC_LAYOUTS[$dl])) {
        $pdo->prepare('UPDATE company SET doc_layout = ? WHERE company_id = ?')->execute([$dl, $cid]);
    }

    // Logo: upload a new one, or remove the current one
    [$logo, $logoErr] = process_logo($_FILES['logo'] ?? []);
    if ($logo !== null) {
        $pdo->prepare('UPDATE company SET logo = ? WHERE company_id = ?')->execute([$logo, $cid]);
    } elseif (!empty($_POST['remove_logo']) && $logoErr === '') {
        $pdo->prepare('UPDATE company SET logo = NULL WHERE company_id = ?')->execute([$cid]);
    }

    if ($logoErr !== '') {
        flash('error', 'Details saved, but the logo was not changed. ' . $logoErr);
    } else {
        flash('success', 'Company details saved.');
    }
    redirect('company.php');
}

$c = find_company($pdo);

function cfield(string $id, string $label, array $c, string $ph = ''): void { ?>
  <div>
    <label for="<?= $id ?>" class="block text-sm font-semibold mb-1"><?= e($label) ?></label>
    <input id="<?= $id ?>" name="<?= $id ?>" value="<?= e($c[$id] ?? '') ?>" placeholder="<?= e($ph) ?>" class="<?= INPUT ?>">
  </div>
<?php }

$page  = 'company';
$title = 'Company';
include '../includes/header.php';
page_head('Company details', 'These details appear on every quotation and invoice.');
?>

<form method="post" enctype="multipart/form-data" class="max-w-3xl space-y-6">
  <?= csrf_field() ?>

  <section class="bg-white rounded-2xl border border-slate-200 p-5">
    <h2 class="font-bold text-brand-900 mb-1">Logo</h2>
    <p class="text-sm text-slate-500 mb-4">Shown at the top of every quotation and invoice. PNG, JPG or WEBP. A transparent PNG looks best.</p>
    <div class="flex flex-wrap items-center gap-5">
      <div class="rounded-2xl p-3 grid place-items-center min-w-[5.5rem] h-24" style="background:linear-gradient(100deg,#0b1f44 0%,#12306b 58%,#2c4a8f 100%)">
        <span class="rounded-2xl bg-white p-1.5 grid place-items-center">
          <img id="logoPreview" src="<?= e($c['logo'] ?? '') ?>" alt="" class="<?= empty($c['logo']) ? 'hidden ' : '' ?>block h-14 w-auto max-w-[10rem] object-contain">
          <span id="logoInitial" class="<?= empty($c['logo']) ? '' : 'hidden ' ?>w-14 h-14 grid place-items-center text-3xl font-extrabold text-brand-900"><?= e(strtoupper(substr(trim($c['company_name'] ?? '') !== '' ? trim($c['company_name']) : 'Q', 0, 1))) ?></span>
        </span>
      </div>
      <div class="min-w-0 space-y-2">
        <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp" class="block text-sm text-slate-600 file:mr-3 file:rounded-xl file:border-0 file:bg-brand-600 file:text-white file:font-semibold file:px-4 file:py-2 hover:file:bg-brand-700">
        <?php if (!empty($c['logo'])): ?>
          <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="remove_logo" value="1" class="w-4 h-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            Remove the logo (use the company's first letter instead)
          </label>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="bg-white rounded-2xl border border-slate-200 p-5">
    <h2 class="font-bold text-brand-900 mb-1">Document colour</h2>
    <p class="text-sm text-slate-500 mb-4">Used for the banner, headings, lines, and totals on every quotation and invoice.</p>
    <?php $curTheme = strtolower($c['theme_color'] ?? '') ?: DEFAULT_THEME; ?>
    <input type="hidden" id="theme_color" name="theme_color" value="<?= e($curTheme) ?>">
    <div class="flex flex-wrap items-center gap-3" id="swatches">
      <?php foreach (THEME_PRESETS as $label => $hex): ?>
        <button type="button" data-color="<?= e($hex) ?>" title="<?= e($label) ?>" aria-label="<?= e($label) ?>"
                class="swatch w-9 h-9 rounded-full border-2 border-white ring-2 ring-slate-200" style="background:<?= e($hex) ?>"></button>
      <?php endforeach; ?>
      <label class="flex items-center gap-2 text-sm text-slate-600 ml-1">
        Custom
        <input type="color" id="customColor" value="<?= e($curTheme) ?>" class="w-10 h-9 rounded-lg border border-slate-300 bg-white p-0.5 cursor-pointer">
      </label>
    </div>
    <div id="themeBanner" class="mt-4 rounded-xl px-5 py-4 text-white flex items-center justify-between">
      <span class="font-bold"><?= e($c['company_name'] ?? 'Your company') ?></span>
      <span class="text-lg font-extrabold uppercase tracking-[0.12em]">Quotation</span>
    </div>
  </section>

  <section class="bg-white rounded-2xl border border-slate-200 p-5">
    <h2 class="font-bold text-brand-900 mb-1">Document layout</h2>
    <p class="text-sm text-slate-500 mb-4">Pick the design of every quotation and invoice. The colour above is used in all of them.</p>
    <?php $curLayout = doc_layout_key($c['doc_layout'] ?? null); ?>
    <div id="layoutPicker" class="grid sm:grid-cols-3 gap-4" style="--c:<?= e($curTheme) ?>">
      <?php foreach (DOC_LAYOUTS as $key => [$label, $hint]): ?>
        <label class="cursor-pointer">
          <input type="radio" name="doc_layout" value="<?= e($key) ?>" class="peer sr-only" <?= $curLayout === $key ? 'checked' : '' ?>>
          <span class="block rounded-xl border-2 border-slate-200 p-3 transition peer-checked:border-brand-600 peer-checked:ring-2 peer-checked:ring-brand-200 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500">
            <!-- tiny page sketch -->
            <span class="block rounded-md border border-slate-200 bg-white overflow-hidden h-36">
              <?php if ($key === 'modern'): ?>
                <span class="block h-9" style="background:var(--c)"></span>
                <span class="block px-2 pt-2 space-y-1">
                  <span class="block h-1.5 w-2/3 rounded bg-slate-200"></span>
                  <span class="block h-1.5 w-1/2 rounded bg-slate-200"></span>
                  <span class="block h-0.5 mt-2" style="background:var(--c)"></span>
                  <span class="block h-1.5 rounded bg-slate-100"></span>
                  <span class="block h-1.5 rounded bg-slate-100"></span>
                </span>
              <?php elseif ($key === 'classic'): ?>
                <span class="flex items-center justify-between px-2 pt-2 pb-1.5 border-b-2" style="border-color:var(--c)">
                  <span class="block h-3 w-6 rounded" style="background:var(--c)"></span>
                  <span class="block h-2 w-10 rounded" style="background:var(--c)"></span>
                </span>
                <span class="block px-2 pt-2 space-y-1">
                  <span class="block h-1.5 w-2/3 rounded bg-slate-200"></span>
                  <span class="block h-2.5 mt-2 rounded-sm" style="background:var(--c)"></span>
                  <span class="block h-1.5 rounded bg-slate-100"></span>
                  <span class="block h-1.5 rounded bg-slate-100"></span>
                </span>
              <?php else: ?>
                <span class="block px-2 pt-3">
                  <span class="flex items-start justify-between">
                    <span class="block h-2.5 w-12 rounded bg-slate-300"></span>
                    <span class="block h-2 w-8 rounded bg-slate-200"></span>
                  </span>
                  <span class="block h-px mt-2 bg-slate-300"></span>
                  <span class="block space-y-1 mt-3">
                    <span class="block h-1.5 w-2/3 rounded bg-slate-200"></span>
                    <span class="block h-1.5 w-1/2 rounded bg-slate-200"></span>
                    <span class="block h-px mt-2 bg-slate-300"></span>
                    <span class="block h-1.5 rounded bg-slate-100"></span>
                    <span class="block h-1.5 rounded bg-slate-100"></span>
                  </span>
                </span>
              <?php endif; ?>
            </span>
            <span class="block mt-3 font-bold text-slate-900"><?= e($label) ?></span>
            <span class="block text-xs text-slate-500 mt-0.5"><?= e($hint) ?></span>
          </span>
        </label>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="bg-white rounded-2xl border border-slate-200 p-5 grid sm:grid-cols-2 gap-4">
    <div class="sm:col-span-2"><?php cfield('company_name', 'Company name', $c); ?></div>
    <div class="sm:col-span-2"><?php cfield('address', 'Address', $c, 'Street, city'); ?></div>
    <?php cfield('phone_no', 'Phone number', $c, '0112345678'); ?>
  </section>

  <section class="bg-white rounded-2xl border border-slate-200 p-5">
    <h2 class="font-bold text-brand-900 mb-4">Bank details</h2>
    <div class="grid sm:grid-cols-2 gap-4">
      <?php cfield('bank_name', 'Bank name', $c); ?>
      <?php cfield('account_holder', 'Account holder', $c); ?>
      <?php cfield('account_no', 'Account number', $c); ?>
    </div>
  </section>

  <button class="<?= BTN ?> px-6 py-3">Save changes</button>
</form>

<script>
(function () {
  const input = document.getElementById('theme_color');
  const custom = document.getElementById('customColor');
  const banner = document.getElementById('themeBanner');
  function mix(hex, to, r) {
    const n = h => [1, 3, 5].map(i => parseInt(h.slice(i, i + 2), 16));
    const a = n(hex), b = n(to);
    return '#' + a.map((v, i) => Math.round(v + (b[i] - v) * r).toString(16).padStart(2, '0')).join('');
  }
  function apply(hex) {
    input.value = hex;
    custom.value = hex;
    document.getElementById('layoutPicker').style.setProperty('--c', hex);
    const isDef = hex === <?= json_encode(DEFAULT_THEME) ?>;
    const dark = isDef ? '#0b1f44' : mix(hex, '#000000', 0.35);
    const light = isDef ? '#2c4a8f' : mix(hex, '#ffffff', 0.22);
    banner.style.background = 'linear-gradient(100deg,' + dark + ' 0%,' + hex + ' 58%,' + light + ' 100%)';
    document.querySelectorAll('.swatch').forEach(function (b) {
      b.style.boxShadow = b.dataset.color === hex ? '0 0 0 3px ' + hex : '';
    });
  }
  document.querySelectorAll('.swatch').forEach(function (b) {
    b.addEventListener('click', function () { apply(b.dataset.color); });
  });
  custom.addEventListener('input', function () { apply(custom.value.toLowerCase()); });
  apply(input.value);
})();

document.getElementById('logo').addEventListener('change', function () {
  const f = this.files && this.files[0];
  if (!f) return;
  const img = document.getElementById('logoPreview');
  img.src = URL.createObjectURL(f);
  img.classList.remove('hidden');
  document.getElementById('logoInitial').classList.add('hidden');
});
</script>

<?php include '../includes/footer.php'; ?>

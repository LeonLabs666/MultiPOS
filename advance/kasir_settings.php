<?php
declare(strict_types=1);

require __DIR__ . '/../config/auth.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/audit.php'; // resolve_store_id + (opsional) log_activity

require_role(['kasir']);

$actor = auth_user();

if (!($pdo instanceof PDO)) {
  http_response_code(500);
  exit('DB not ready.');
}

$storeId = resolve_store_id($pdo, $actor);
if (!$storeId) {
  http_response_code(400);
  exit('Store tidak ditemukan.');
}

// ambil data store
$storeQ = $pdo->prepare("SELECT * FROM stores WHERE id=? LIMIT 1");
$storeQ->execute([$storeId]);
$store = $storeQ->fetch(PDO::FETCH_ASSOC);
if (!$store) {
  http_response_code(404);
  exit('Store tidak ditemukan.');
}

$error = '';
$ok = '';

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function normalize_rel_path(string $absPath, string $publicDir): string {
  $absPath = str_replace('\\', '/', $absPath);
  $publicDir = rtrim(str_replace('\\', '/', $publicDir), '/') . '/';
  if (strpos($absPath, $publicDir) === 0) {
    return substr($absPath, strlen($publicDir));
  }
  return $absPath; // fallback
}

function is_image_ext_allowed(string $ext): bool {
  $ext = strtolower($ext);
  return in_array($ext, ['png','jpg','jpeg','webp'], true);
}

function detect_ext_from_mime(string $mime, string $fallbackExt): string {
  $mime = strtolower($mime);
  return match ($mime) {
    'image/png'  => 'png',
    'image/jpeg' => 'jpg',
    'image/webp' => 'webp',
    default      => strtolower($fallbackExt),
  };
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();

  // input basic
  $header = trim((string)($_POST['receipt_header'] ?? ''));
  $footer = trim((string)($_POST['receipt_footer'] ?? ''));
  $showLogo = isset($_POST['receipt_show_logo']) ? 1 : 0;
  $autoPrint = isset($_POST['receipt_auto_print']) ? 1 : 0;
  $paper = (string)($_POST['receipt_paper'] ?? '80mm');

  if (!in_array($paper, ['58mm', '80mm'], true)) {
    $error = 'Ukuran kertas tidak valid.';
  } else {

    // ===== handle upload logo (opsional) =====
    $logoRelPath = null;
    $deleteLogo = isset($_POST['delete_logo']);

    if ($deleteLogo) {
      $logoRelPath = null;
    } elseif (isset($_FILES['logo']) && is_array($_FILES['logo']) && ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {

      $err = (int)($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE);
      if ($err !== UPLOAD_ERR_OK) {
        $error = 'Upload logo gagal. Kode error: ' . $err;
      } else {
        $tmp = (string)($_FILES['logo']['tmp_name'] ?? '');
        $origName = (string)($_FILES['logo']['name'] ?? 'logo');
        $size = (int)($_FILES['logo']['size'] ?? 0);

        if ($size <= 0) {
          $error = 'File logo kosong.';
        } elseif ($size > 2 * 1024 * 1024) {
          $error = 'Ukuran logo maksimal 2MB.';
        } else {
          $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
          if (!is_image_ext_allowed($ext)) {
            $error = 'Format logo harus PNG/JPG/WEBP.';
          } else {
            $mime = '';
            if (function_exists('finfo_open')) {
              $finfo = finfo_open(FILEINFO_MIME_TYPE);
              if ($finfo) {
                $mime = (string)finfo_file($finfo, $tmp);
                $finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = (string)$finfo->file($tmp);

              }
            }
            $ext2 = detect_ext_from_mime($mime, $ext);
            if (!is_image_ext_allowed($ext2)) {
              $error = 'File yang diupload bukan gambar yang didukung.';
            } else {
              $publicDir = realpath(__DIR__ . '/');
              $uploadDir = __DIR__ . '/../upload/store_logos';
              if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
              }

              // nama file unik per store
              $safeName = 'store_' . (int)$storeId . '_logo_' . date('Ymd_His') . '.' . $ext2;
              $destAbs = $uploadDir . '/' . $safeName;

              if (!move_uploaded_file($tmp, $destAbs)) {
                $error = 'Gagal menyimpan file logo (permission folder uploads?).';
              } else {
                // simpan sebagai relative path dari /public
                $logoRelPath = 'upload/store_logos/' . $safeName;
              }
            }
          }
        }
      }
    }

    if ($error === '') {
      // update store (logoRelPath kalau null => pakai nilai lama kecuali delete_logo)
      $currentLogo = (string)($store['receipt_logo_path'] ?? '');
      $newLogo = $deleteLogo ? null : ($logoRelPath !== null ? $logoRelPath : ($currentLogo !== '' ? $currentLogo : null));

      $upd = $pdo->prepare("
        UPDATE stores SET
          receipt_header=?,
          receipt_footer=?,
          receipt_show_logo=?,
          receipt_auto_print=?,
          receipt_paper=?,
          receipt_logo_path=?
        WHERE id=?
      ");
      $upd->execute([
        $header !== '' ? mb_substr($header, 0, 255) : null,
        $footer !== '' ? mb_substr($footer, 0, 255) : null,
        $showLogo,
        $autoPrint,
        $paper,
        $newLogo,
        $storeId
      ]);

      // audit (opsional)
      if (function_exists('log_activity')) {
        log_activity(
          $pdo,
          $actor,
          'KASIR_SETTINGS_UPDATE',
          'Kasir mengubah pengaturan struk',
          'store',
          (int)$storeId,
          [
            'receipt_show_logo' => $showLogo,
            'receipt_auto_print' => $autoPrint,
            'receipt_paper' => $paper,
            'logo_changed' => ($logoRelPath !== null) || $deleteLogo,
          ],
          (int)$storeId
        );
      }

      $ok = 'Pengaturan tersimpan.';

      // refresh store
      $storeQ->execute([$storeId]);
      $store = $storeQ->fetch(PDO::FETCH_ASSOC);
    }
  }
}

// layout
$pageTitle = 'Pengaturan Kasir';
$activeMenu = 'kasir_settings';
$appName = '';
require __DIR__ . '/../publik/partials/kasir_layout_top.php';

// data untuk preview
$storeName = (string)($store['name'] ?? 'Toko');
$storeAddr = (string)($store['address'] ?? '');
$storePhone = (string)($store['phone'] ?? '');
$headerText = (string)($store['receipt_header'] ?? '');
$footerText = (string)($store['receipt_footer'] ?? '');
$showLogo = (int)($store['receipt_show_logo'] ?? 1);
$paper = (string)($store['receipt_paper'] ?? '80mm');
$logoPath = (string)($store['receipt_logo_path'] ?? '');
$autoPrint = (int)($store['receipt_auto_print'] ?? 1);

// preview dummy data
$previewInvoice = 'PREVIEW-' . date('ymd') . '-0001';
$previewDate = date('Y-m-d H:i:s');
$previewKasir = (string)($actor['name'] ?? 'Kasir');
$items = [
  ['name'=>'Es Teh Manis', 'qty'=>1, 'price'=>5000],
  ['name'=>'Nasi Goreng', 'qty'=>1, 'price'=>20000],
  ['name'=>'Kopi Susu', 'qty'=>2, 'price'=>12000],
];

function rupiah(int $n): string { return 'Rp ' . number_format($n, 0, ',', '.'); }
$subtotal = 0;
foreach ($items as $it) $subtotal += $it['qty'] * $it['price'];
$paid = $subtotal;
$change = 0;

$paperWidth = ($paper === '58mm') ? 280 : 360; // px
?>

<style>
  .grid{ display:grid; gap:14px; }
  @media(min-width: 1100px){ .grid{ grid-template-columns: 1.1fr .9fr; align-items:start; } }

  .card{ background: var(--surface); border:1px solid var(--line); border-radius: var(--radius);
         box-shadow: 0 18px 50px rgba(15,23,42,.08); }
  .pad{ padding:14px; }
  .title{ font-size:18px; font-weight: 1000; margin:0; }
  .muted{ color: var(--muted); font-size:12px; }
  .pill{ display:inline-flex; align-items:center; padding:6px 10px; border-radius:999px; border:1px solid var(--line);
         font-size:12px; font-weight:950; color: var(--muted); background:#fff; }
  .pill.good{ border-color: rgba(34,197,94,.35); background: rgba(34,197,94,.12); color:#065f46; }
  .pill.warn{ border-color: rgba(249,115,22,.35); background: rgba(249,115,22,.12); color:#7c2d12; }

  label{ font-size:12px; font-weight:900; color: var(--muted); display:block; margin-bottom:6px; }
  input[type="text"], textarea, select{
    width:100%; padding:10px 12px; border-radius:14px; border:1px solid var(--line); outline:none; background:#fff;
  }
  input[type="file"]{ width:100%; }
  textarea{ min-height: 90px; resize: vertical; }

  .btn{ display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:10px 14px; border-radius:14px;
        border:1px solid var(--line); background:#fff; font-weight:950; cursor:pointer; text-decoration:none; }
  .btn.primary{ background: rgba(37,99,235,.12); border-color: rgba(37,99,235,.35); color:#1d4ed8; }
  .btn.danger{ background: rgba(239,68,68,.10); border-color: rgba(239,68,68,.35); color:#b91c1c; }

  .row{ display:grid; gap:12px; }
  @media(min-width:900px){ .row{ grid-template-columns: 1fr 1fr; } }

  /* Receipt preview */
  .receipt-wrap{ display:flex; justify-content:center; padding:16px; background: rgba(15,23,42,.03);
                 border-top:1px solid var(--line); border-bottom-left-radius: var(--radius); border-bottom-right-radius: var(--radius); }
  .receipt{
    width: <?= (int)$paperWidth ?>px;
    background:#fff;
    border:1px dashed rgba(15,23,42,.18);
    border-radius: 16px;
    padding: 14px;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    font-size: 12px;
    color: #0f172a;
  }
  .r-center{ text-align:center; }
  .r-hr{ border:0; border-top:1px dashed rgba(15,23,42,.25); margin:10px 0; }
  .r-row{ display:flex; justify-content:space-between; gap:10px; }
  .r-small{ font-size: 11px; color: rgba(15,23,42,.75); }
  .r-items{ margin-top:8px; }
  .r-item{ display:flex; justify-content:space-between; gap:10px; padding:6px 0; border-bottom:1px dashed rgba(15,23,42,.12); }
  .r-item:last-child{ border-bottom:0; }
  .r-logo{ width: 64px; height: 64px; object-fit:contain; display:block; margin: 0 auto 8px; }
  .help{ font-size:12px; color: var(--muted); }
</style>

<div class="grid">

  <!-- LEFT: Settings form -->
  <section class="card">
    <div class="pad">
      <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:flex-start;">
        <div>
          <h1 class="title">Pengaturan Kasir</h1>
          <div class="muted"><?= h($storeName) ?> • <?= h((string)($actor['name'] ?? '')) ?></div>
        </div>
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
          <span class="pill"><?= h($paper) ?></span>
          <span class="pill"><?= $autoPrint ? 'Auto print: ON' : 'Auto print: OFF' ?></span>
        </div>
      </div>

      <?php if($error): ?>
        <div style="margin-top:10px" class="pill warn"><?= h($error) ?></div>
      <?php endif; ?>
      <?php if($ok): ?>
        <div style="margin-top:10px" class="pill good"><?= h($ok) ?></div>
      <?php endif; ?>

      <div style="height:12px"></div>

      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

        <div class="row">
          <div>
            <label>Header Struk (opsional)</label>
            <textarea name="receipt_header" placeholder="Contoh: Promo hari ini..."><?= h($headerText) ?></textarea>
          </div>
          <div>
            <label>Footer Struk (opsional)</label>
            <textarea name="receipt_footer" placeholder="Contoh: Terima kasih..."><?= h($footerText) ?></textarea>
          </div>
        </div>

        <div style="height:10px"></div>

        <div class="row">
          <div>
            <label>Ukuran Kertas</label>
            <select name="receipt_paper">
              <option value="58mm" <?= $paper==='58mm'?'selected':'' ?>>58mm</option>
              <option value="80mm" <?= $paper==='80mm'?'selected':'' ?>>80mm</option>
            </select>
            <div class="help" style="margin-top:6px;">Preview di kanan akan menyesuaikan.</div>
          </div>

          <div style="display:flex; flex-direction:column; gap:10px; padding-top:22px;">
            <label style="display:flex; gap:10px; align-items:center; font-weight:950; color:#0f172a;">
              <input type="checkbox" name="receipt_show_logo" <?= $showLogo ? 'checked' : '' ?>>
              Tampilkan logo di struk
            </label>

            <label style="display:flex; gap:10px; align-items:center; font-weight:950; color:#0f172a;">
              <input type="checkbox" name="receipt_auto_print" <?= $autoPrint ? 'checked' : '' ?>>
              Auto print setelah pembayaran
            </label>
          </div>
        </div>

        <div style="height:10px"></div>

        <div class="row">
          <div>
            <label>Logo Toko (PNG/JPG/WEBP, max 2MB)</label>
            <input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp">
            <?php if($logoPath): ?>
              <div class="help" style="margin-top:6px;">Logo saat ini: <b><?= h($logoPath) ?></b></div>
              <label style="display:flex; gap:10px; align-items:center; margin-top:10px;">
                <input type="checkbox" name="delete_logo">
                Hapus logo
              </label>
            <?php endif; ?>
          </div>

          <div style="display:flex; gap:10px; align-items:flex-end; justify-content:flex-end;">
            <button class="btn primary" type="submit">💾 Simpan</button>
            <a class="btn" href="kasir_settings.php">↺ Reset</a>
          </div>
        </div>

      </form>
    </div>

    <div class="receipt-wrap">
      <div class="receipt">
        <?php if($showLogo && $logoPath): ?>
          <img class="r-logo" src="<?= h($logoPath) ?>" alt="logo">
        <?php endif; ?>

        <div class="r-center">
          <div style="font-weight:1000; font-size:14px;"><?= h($storeName) ?></div>
          <?php if($storeAddr): ?><div class="r-small"><?= h($storeAddr) ?></div><?php endif; ?>
          <?php if($storePhone): ?><div class="r-small"><?= h($storePhone) ?></div><?php endif; ?>
        </div>

        <?php if(trim($headerText) !== ''): ?>
          <hr class="r-hr">
          <div class="r-center r-small" style="white-space:pre-line;"><?= h($headerText) ?></div>
        <?php endif; ?>

        <hr class="r-hr">

        <div class="r-small">
          <div>Invoice: <b><?= h($previewInvoice) ?></b></div>
          <div>Tanggal: <?= h($previewDate) ?></div>
          <div>Kasir: <?= h($previewKasir) ?></div>
        </div>

        <hr class="r-hr">

        <div class="r-items">
          <?php foreach($items as $it): ?>
            <?php $line = (int)$it['qty'] * (int)$it['price']; ?>
            <div class="r-item">
              <div>
                <div style="font-weight:950;"><?= h((string)$it['name']) ?></div>
                <div class="r-small"><?= (int)$it['qty'] ?> x <?= rupiah((int)$it['price']) ?></div>
              </div>
              <div style="font-weight:950;"><?= rupiah($line) ?></div>
            </div>
          <?php endforeach; ?>
        </div>

        <hr class="r-hr">

        <div class="r-row" style="font-weight:1000;">
          <div>Total</div><div><?= rupiah($subtotal) ?></div>
        </div>
        <div class="r-row r-small">
          <div>Bayar</div><div><?= rupiah($paid) ?></div>
        </div>
        <div class="r-row r-small">
          <div>Kembali</div><div><?= rupiah($change) ?></div>
        </div>

        <?php if(trim($footerText) !== ''): ?>
          <hr class="r-hr">
          <div class="r-center r-small" style="white-space:pre-line;"><?= h($footerText) ?></div>
        <?php endif; ?>

        <hr class="r-hr">
        <div class="r-center r-small">— PREVIEW STRUK —</div>
      </div>
    </div>
  </section>

</div>

<?php require __DIR__ . '/../publik/partials/kasir_layout_bottom.php'; ?>

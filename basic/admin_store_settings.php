<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
require __DIR__ . '/../config/audit.php'; // log_activity()

$page_title  = 'Pengaturan Toko • MultiPOS';
$page_h1     = 'Pengaturan Toko';
$active_menu = 'pengaturan';

$storeId   = (int)$storeId;     // from _bootstrap
$adminId   = (int)$adminId;     // from _bootstrap
$error = '';
$ok = '';

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

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

// ambil store terbaru
$storeQ = $pdo->prepare("SELECT * FROM stores WHERE id=? AND owner_admin_id=? LIMIT 1");
$storeQ->execute([$storeId, $adminId]);
$store = $storeQ->fetch(PDO::FETCH_ASSOC);
if (!$store) {
  http_response_code(404);
  exit('Store tidak ditemukan.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();

  $name    = trim((string)($_POST['name'] ?? ''));
  $address = trim((string)($_POST['address'] ?? ''));
  $phone   = trim((string)($_POST['phone'] ?? ''));

  $receiptHeader = trim((string)($_POST['receipt_header'] ?? ''));
  $receiptFooter = trim((string)($_POST['receipt_footer'] ?? ''));
  $showLogo      = isset($_POST['receipt_show_logo']) ? 1 : 0;
  $autoPrint     = isset($_POST['receipt_auto_print']) ? 1 : 0;
  $paper         = (string)($_POST['receipt_paper'] ?? '80mm');

  if ($name === '') {
    $error = 'Nama toko wajib diisi.';
  } elseif (mb_strlen($name) > 150) {
    $error = 'Nama toko maksimal 150 karakter.';
  } elseif (!in_array($paper, ['58mm','80mm'], true)) {
    $error = 'Ukuran kertas tidak valid.';
  } elseif (mb_strlen($phone) > 50) {
    $error = 'No. telepon maksimal 50 karakter.';
  } else {

    // ===== handle logo (opsional) =====
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
            if (class_exists('finfo')) {
              $fi = new finfo(FILEINFO_MIME_TYPE);
              $mime = (string)$fi->file($tmp);
            }
            $ext2 = detect_ext_from_mime($mime, $ext);
            if (!is_image_ext_allowed($ext2)) {
              $error = 'File yang diupload bukan gambar yang didukung.';
            } else {
              // simpan ke /upload/store_logos/
              $uploadDir = dirname(__DIR__) . '/upload/store_logos'; // /upload/...
              if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);

              $safeName = 'store_' . $storeId . '_logo_' . date('Ymd_His') . '.' . $ext2;
              $destAbs = $uploadDir . '/' . $safeName;

              if (!move_uploaded_file($tmp, $destAbs)) {
                $error = 'Gagal menyimpan file logo (cek permission folder uploads).';
              } else {
                $logoRelPath = 'upload/store_logos/' . $safeName; // relative dari /public
              }
            }
          }
        }
      }
    }

    if ($error === '') {
      $currentLogo = (string)($store['receipt_logo_path'] ?? '');
      $newLogo = $deleteLogo ? null : ($logoRelPath !== null ? $logoRelPath : ($currentLogo !== '' ? $currentLogo : null));

      $upd = $pdo->prepare("
        UPDATE stores SET
          name=?,
          address=?,
          phone=?,
          receipt_header=?,
          receipt_footer=?,
          receipt_show_logo=?,
          receipt_auto_print=?,
          receipt_paper=?,
          receipt_logo_path=?
        WHERE id=? AND owner_admin_id=?
      ");

      $upd->execute([
        mb_substr($name, 0, 150),
        ($address !== '' ? $address : null),
        ($phone !== '' ? mb_substr($phone, 0, 50) : null),
        ($receiptHeader !== '' ? mb_substr($receiptHeader, 0, 255) : null),
        ($receiptFooter !== '' ? mb_substr($receiptFooter, 0, 255) : null),
        $showLogo,
        $autoPrint,
        $paper,
        $newLogo,
        $storeId,
        $adminId
      ]);

      if (function_exists('log_activity')) {
        log_activity(
          $pdo,
          auth_user(),
          'STORE_SETTINGS_UPDATE',
          'Admin mengubah pengaturan toko',
          'store',
          $storeId,
          [
            'name' => $name,
            'paper' => $paper,
            'receipt_show_logo' => $showLogo,
            'receipt_auto_print' => $autoPrint,
            'logo_changed' => ($logoRelPath !== null) || $deleteLogo,
          ],
          $storeId
        );
      }

      $ok = 'Pengaturan toko berhasil disimpan.';

      // refresh store
      $storeQ->execute([$storeId, $adminId]);
      $store = $storeQ->fetch(PDO::FETCH_ASSOC);
    }
  }
}

include __DIR__ . '/partials/layout_top.php';

$logoPath = (string)($store['receipt_logo_path'] ?? '');
?>
<style>
  .wrap{max-width:980px}
  .card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:14px;margin-bottom:12px}
  label{font-size:13px;color:#334155;font-weight:800}
  input,textarea,select{width:100%;padding:10px 12px;border-radius:12px;border:1px solid #e2e8f0}
  textarea{min-height:90px;resize:vertical}
  .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  @media(max-width:900px){.row{grid-template-columns:1fr}}
  .btn{padding:10px 14px;border-radius:12px;border:1px solid #e2e8f0;background:#0f172a;color:#fff;cursor:pointer;font-weight:900}
  .muted{color:#64748b;font-size:12px}
</style>

<div class="wrap">

  <?php if($error): ?><div class="card" style="border-color:#fecaca;color:#991b1b;background:#fef2f2;"><b>Error:</b> <?= h($error) ?></div><?php endif; ?>
  <?php if($ok): ?><div class="card" style="border-color:#bbf7d0;color:#166534;background:#ecfdf5;"><b>OK:</b> <?= h($ok) ?></div><?php endif; ?>

  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

    <div class="card">
      <h3 style="margin:0 0 10px;">Data Toko</h3>

      <div style="margin-bottom:10px;">
        <label>Nama Toko</label>
        <input name="name" value="<?= h($store['name'] ?? '') ?>" required>
      </div>

      <div class="row">
        <div>
          <label>No. Telepon</label>
          <input name="phone" value="<?= h($store['phone'] ?? '') ?>" placeholder="contoh: 0812xxxx">
        </div>
        <div>
          <label>Jenis Toko</label>
          <input value="<?= h(strtoupper((string)($store['store_type'] ?? 'basic'))) ?>" disabled>
          <div class="muted" style="margin-top:6px;">Tipe toko tidak bisa diubah dari menu ini.</div>
        </div>
      </div>

      <div style="margin-top:10px;">
        <label>Alamat</label>
        <textarea name="address" placeholder="alamat toko..."><?= h($store['address'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="card">
      <h3 style="margin:0 0 10px;">Pengaturan Struk</h3>

      <div class="row">
        <div>
          <label>Ukuran Kertas</label>
          <select name="receipt_paper">
            <option value="58mm" <?= ((string)($store['receipt_paper'] ?? '80mm') === '58mm')?'selected':'' ?>>58mm</option>
            <option value="80mm" <?= ((string)($store['receipt_paper'] ?? '80mm') === '80mm')?'selected':'' ?>>80mm</option>
          </select>
        </div>
        <div style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap;">
          <label style="display:flex;gap:8px;align-items:center;font-weight:900;">
            <input type="checkbox" name="receipt_show_logo" value="1" <?= ((int)($store['receipt_show_logo'] ?? 1)===1)?'checked':'' ?>>
            Tampilkan Logo
          </label>
          <label style="display:flex;gap:8px;align-items:center;font-weight:900;">
            <input type="checkbox" name="receipt_auto_print" value="1" <?= ((int)($store['receipt_auto_print'] ?? 1)===1)?'checked':'' ?>>
            Auto Print
          </label>
        </div>
      </div>

      <div class="row" style="margin-top:10px;">
        <div>
          <label>Header Struk (opsional)</label>
          <input name="receipt_header" value="<?= h($store['receipt_header'] ?? '') ?>" placeholder="contoh: Selamat datang...">
        </div>
        <div>
          <label>Footer Struk (opsional)</label>
          <input name="receipt_footer" value="<?= h($store['receipt_footer'] ?? '') ?>" placeholder="contoh: Terimakasih...">
        </div>
      </div>

      <div style="margin-top:10px;">
        <label>Logo Struk (opsional, max 2MB)</label>
        <input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp">
        <?php if($logoPath !== ''): ?>
          <div class="muted" style="margin-top:8px;">
            Logo saat ini: <code><?= h($logoPath) ?></code><br>
            <label style="display:flex;gap:8px;align-items:center;margin-top:6px;font-weight:900;">
              <input type="checkbox" name="delete_logo" value="1">
              Hapus logo
            </label>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <button class="btn" type="submit">Simpan Pengaturan</button>
      <a class="btn" href="admin_basic_dashboard.php" style="text-decoration:none;display:inline-flex;align-items:center;">Kembali</a>
    </div>
  </form>
</div>

<?php include __DIR__ . '/partials/layout_bottom.php'; ?>

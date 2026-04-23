<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';
require_role(['admin']);

$appName='MultiPOS';
$pageTitle='Pengaturan Toko';
$activeMenu='pengaturan_toko';
$adminId=(int)auth_user()['id'];

$error=''; $ok='';

// ambil toko admin
$st = $pdo->prepare("SELECT * FROM stores WHERE owner_admin_id=? AND is_active=1 LIMIT 1");
$st->execute([$adminId]);
$store = $st->fetch(PDO::FETCH_ASSOC);
if (!$store) { http_response_code(400); exit('Admin belum terhubung ke toko.'); }
$storeId = (int)$store['id'];

/**
 * ====== TABLE store_media (untuk logo, banner, dll) ======
 */
try {
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS store_media (
      id INT AUTO_INCREMENT PRIMARY KEY,
      store_id INT NOT NULL,
      media_type VARCHAR(30) NOT NULL,
      file_path VARCHAR(255) NOT NULL,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NULL,
      UNIQUE KEY uk_store_type (store_id, media_type),
      INDEX idx_store (store_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ");
} catch (Throwable $e) {
  // ignore: kalau server menolak CREATE, nanti query bawah akan kasih error
}

// ambil daftar kolom stores (biar aman kalau schema beda)
$dbName = (string)$pdo->query("SELECT DATABASE()")->fetchColumn();
$colQ = $pdo->prepare("
  SELECT COLUMN_NAME
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'stores'
");
$colQ->execute([$dbName]);
$storeCols = array_map(fn($r) => (string)$r['COLUMN_NAME'], $colQ->fetchAll(PDO::FETCH_ASSOC));
$hasCol = function(string $c) use ($storeCols): bool { return in_array($c, $storeCols, true); };

// field yang kita dukung (muncul hanya kalau kolomnya ada)
$fields = [
  'name'            => ['Nama Toko', 'text'],
  'phone'           => ['No. Telepon', 'text'],
  'email'           => ['Email', 'email'],
  'address'         => ['Alamat', 'text'],
  'city'            => ['Kota', 'text'],
  'province'        => ['Provinsi', 'text'],
  'postal_code'     => ['Kode Pos', 'text'],
  'receipt_footer'  => ['Footer Struk', 'textarea'],
  'tax_percent'     => ['Pajak (%)', 'number'],
  'service_percent' => ['Service (%)', 'number'],
  'currency'        => ['Mata Uang', 'text'],
];

function clamp_num($v, float $min=0.0, float $max=100.0): float {
  if (!is_numeric($v)) return 0.0;
  $f = (float)$v;
  if ($f < $min) $f = $min;
  if ($f > $max) $f = $max;
  return $f;
}

function get_logo_path(PDO $pdo, int $storeId): ?string {
  $q = $pdo->prepare("SELECT file_path FROM store_media WHERE store_id=? AND media_type='logo' LIMIT 1");
  $q->execute([$storeId]);
  $p = $q->fetchColumn();
  return $p ? (string)$p : null;
}

function is_safe_upload_path(string $path): bool {
  // simple safety: only allow inside upload/store_logos
  return str_starts_with($path, '/upload/store_logos/');
}

/**
 * ===== POST HANDLER =====
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $act = (string)($_POST['action'] ?? '');

  // === SIMPAN SETTING TEXT ===
  if ($act === 'save') {
    $updates = [];
    $params = [];

    foreach ($fields as $col => [$label, $type]) {
      if (!$hasCol($col)) continue;

      $val = $_POST[$col] ?? null;

      if ($type === 'number') {
        $val = clamp_num($val, 0, 100);
      } else {
        $val = trim((string)$val);
        if ($col === 'name') {
          if ($val === '') {
            header('Location: admin_pengaturan_toko.php?err=' . urlencode('Nama toko wajib diisi.'));
            exit;
          }
          if (strlen($val) > 120) $val = substr($val, 0, 120);
        } else {
          if ($type !== 'textarea' && strlen($val) > 255) $val = substr($val, 0, 255);
          if ($type === 'textarea' && strlen($val) > 2000) $val = substr($val, 0, 2000);
        }
      }

      $updates[] = "`$col` = ?";
      $params[] = $val;
    }

    if ($hasCol('updated_at')) $updates[] = "`updated_at` = NOW()";

    if (!$updates) {
      header('Location: admin_pengaturan_toko.php?err=' . urlencode('Tidak ada field yang bisa diupdate di tabel stores.'));
      exit;
    }

    try {
      $sql = "UPDATE stores SET " . implode(", ", $updates) . " WHERE id=? AND owner_admin_id=? LIMIT 1";
      $params[] = $storeId;
      $params[] = $adminId;
      $pdo->prepare($sql)->execute($params);

      header('Location: admin_pengaturan_toko.php?ok=' . urlencode('Pengaturan toko berhasil disimpan.'));
      exit;
    } catch (Throwable $e) {
      header('Location: admin_pengaturan_toko.php?err=' . urlencode('Gagal simpan pengaturan.'));
      exit;
    }
  }

  // === UPLOAD LOGO ===
  if ($act === 'upload_logo') {
    if (!isset($_FILES['logo']) || !is_array($_FILES['logo'])) {
      header('Location: admin_pengaturan_toko.php?err=' . urlencode('File logo tidak ditemukan.'));
      exit;
    }

    $f = $_FILES['logo'];
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
      $msg = 'Gagal upload logo.';
      if (($f['error'] ?? 0) === UPLOAD_ERR_INI_SIZE || ($f['error'] ?? 0) === UPLOAD_ERR_FORM_SIZE) $msg = 'Ukuran file terlalu besar.';
      elseif (($f['error'] ?? 0) === UPLOAD_ERR_NO_FILE) $msg = 'Pilih file logo dulu.';
      header('Location: admin_pengaturan_toko.php?err=' . urlencode($msg));
      exit;
    }

    $maxBytes = 2 * 1024 * 1024; // 2MB
    if (($f['size'] ?? 0) > $maxBytes) {
      header('Location: admin_pengaturan_toko.php?err=' . urlencode('Maks ukuran logo 2MB.'));
      exit;
    }

    $tmp = (string)$f['tmp_name'];
    $info = @getimagesize($tmp);
    if (!$info || !isset($info['mime'])) {
      header('Location: admin_pengaturan_toko.php?err=' . urlencode('File bukan gambar yang valid.'));
      exit;
    }

    $mime = (string)$info['mime'];
    $ext = match ($mime) {
      'image/jpeg' => 'jpg',
      'image/png'  => 'png',
      'image/webp' => 'webp',
      default => ''
    };
    if ($ext === '') {
      header('Location: admin_pengaturan_toko.php?err=' . urlencode('Format logo harus JPG/PNG/WEBP.'));
      exit;
    }

    $dirFs = __DIR__ . '/../upload/store_logos';
    if (!is_dir($dirFs)) {
      @mkdir($dirFs, 0775, true);
    }
    if (!is_dir($dirFs) || !is_writable($dirFs)) {
      header('Location: admin_pengaturan_toko.php?err=' . urlencode('Folder upload tidak bisa ditulis: upload/store_logos'));
      exit;
    }

    // nama file random
    $rand = bin2hex(random_bytes(10));
    $filename = "store_{$storeId}_{$rand}.{$ext}";
    $destFs = $dirFs . '/' . $filename;

    if (!move_uploaded_file($tmp, $destFs)) {
      header('Location: admin_pengaturan_toko.php?err=' . urlencode('Gagal menyimpan file logo.'));
      exit;
    }

    // path yang dipakai di browser (public path)
    $publicPath = '/upload/store_logos/' . $filename;

    // hapus logo lama (kalau ada)
    try {
      $old = get_logo_path($pdo, $storeId);
      if ($old && is_safe_upload_path($old)) {
        $oldFs = __DIR__ . $old; // __DIR__ adalah /public
        if (is_file($oldFs)) @unlink($oldFs);
      }
    } catch (Throwable $e) { /* ignore */ }

    // simpan ke DB (upsert)
    try {
      $pdo->prepare("
        INSERT INTO store_media (store_id, media_type, file_path, created_at, updated_at)
        VALUES (?,?,?,NOW(),NOW())
        ON DUPLICATE KEY UPDATE file_path=VALUES(file_path), updated_at=NOW()
      ")->execute([$storeId, 'logo', $publicPath]);

      header('Location: admin_pengaturan_toko.php?ok=' . urlencode('Logo berhasil diupload.'));
      exit;
    } catch (Throwable $e) {
      // kalau gagal DB, hapus file baru biar tidak numpuk
      @unlink($destFs);
      header('Location: admin_pengaturan_toko.php?err=' . urlencode('Gagal simpan path logo ke database.'));
      exit;
    }
  }

  // === HAPUS LOGO ===
  if ($act === 'delete_logo') {
    try {
      $old = get_logo_path($pdo, $storeId);
      if ($old && is_safe_upload_path($old)) {
        $oldFs = __DIR__ . $old;
        if (is_file($oldFs)) @unlink($oldFs);
      }
      $pdo->prepare("DELETE FROM store_media WHERE store_id=? AND media_type='logo'")->execute([$storeId]);
      header('Location: admin_pengaturan_toko.php?ok=' . urlencode('Logo dihapus.'));
      exit;
    } catch (Throwable $e) {
      header('Location: admin_pengaturan_toko.php?err=' . urlencode('Gagal hapus logo.'));
      exit;
    }
  }

  header('Location: admin_pengaturan_toko.php');
  exit;
}

// reload store terbaru
$st = $pdo->prepare("SELECT * FROM stores WHERE id=? AND owner_admin_id=? LIMIT 1");
$st->execute([$storeId, $adminId]);
$store = $st->fetch(PDO::FETCH_ASSOC);

$error = (string)($_GET['err'] ?? '');
$ok    = (string)($_GET['ok'] ?? '');

$logoPath = null;
try { $logoPath = get_logo_path($pdo, $storeId); } catch (Throwable $e) {}

require __DIR__ . '/../publik/partials/admin_layout_top.php';
?>

<style>
  .wrap{max-width:1100px;}
  .muted{color:#64748b}
  .panel{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:16px}
  .grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
  @media (max-width: 980px){ .grid2{grid-template-columns:1fr} }
  label{font-size:13px;color:#334155;display:block;margin:0 0 6px}
  input,textarea{padding:11px 12px;border-radius:12px;border:1px solid #e2e8f0;width:100%}
  textarea{min-height:110px;resize:vertical}
  .btn{padding:10px 14px;border-radius:12px;border:1px solid #e2e8f0;background:#2563eb;color:#fff;cursor:pointer}
  .btn:active{transform:translateY(1px)}
  .btn-ghost{padding:10px 14px;border-radius:12px;border:1px solid #e2e8f0;background:#fff;color:#0f172a;cursor:pointer}
  .btn-danger{padding:10px 14px;border-radius:12px;border:1px solid #fecaca;background:#fff;color:#ef4444;cursor:pointer}
  .row{margin-bottom:12px}
  .logo-box{
    display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap;
    padding:12px;border:1px solid #e2e8f0;border-radius:16px;background:#f8fafc;
  }
  .logo-preview{
    width:96px;height:96px;border-radius:18px;border:1px solid #e2e8f0;background:#fff;
    display:flex;align-items:center;justify-content:center;overflow:hidden;
  }
  .logo-preview img{width:100%;height:100%;object-fit:cover}
  .logo-actions{flex:1;min-width:260px}
</style>

<div class="wrap">
  <h1 style="margin:0 0 6px;">Pengaturan Toko</h1>
  <div class="muted" style="margin-bottom:14px;">
    Atur profil toko & pengaturan struk.
  </div>

  <?php if($error):?><div style="color:#ef4444;margin:10px 0;"><?=htmlspecialchars($error)?></div><?php endif;?>
  <?php if($ok):?><div style="color:#16a34a;margin:10px 0;"><?=htmlspecialchars($ok)?></div><?php endif;?>

  <!-- LOGO -->
  <div class="panel" style="margin-bottom:14px;">
    <h3 style="margin:0 0 10px;">Logo Toko</h3>

    <div class="logo-box">
      <div class="logo-preview">
        <?php if($logoPath): ?>
          <img src="<?= htmlspecialchars($logoPath) ?>" alt="Logo Toko">
        <?php else: ?>
          <span class="muted" style="font-size:12px;">No Logo</span>
        <?php endif; ?>
      </div>

      <div class="logo-actions">
        <div class="muted" style="font-size:12px;margin-bottom:10px;">
          Format: JPG/PNG/WEBP • Maks 2MB • Disarankan 512×512
        </div>

        <form method="post" enctype="multipart/form-data" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <input type="hidden" name="action" value="upload_logo">

          <input type="file" name="logo" accept="image/jpeg,image/png,image/webp" required>

          <button class="btn" type="submit">Upload Logo</button>
        </form>

        <?php if($logoPath): ?>
          <form method="post" style="margin-top:10px;">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="delete_logo">
            <button class="btn-danger" type="submit" onclick="return confirm('Hapus logo toko?')">Hapus Logo</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- SETTING TOKO -->
  <div class="panel">
    <form method="post">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="action" value="save">

      <div class="grid2">
        <?php foreach ($fields as $col => [$label, $type]): ?>
          <?php if(!$hasCol($col)) continue; ?>
          <div class="row" style="<?= $type==='textarea' ? 'grid-column:1/-1;' : '' ?>">
            <label><?= htmlspecialchars($label) ?></label>

            <?php if($type === 'textarea'): ?>
              <textarea name="<?= htmlspecialchars($col) ?>" placeholder="Tulis di sini..."><?= htmlspecialchars((string)($store[$col] ?? '')) ?></textarea>

            <?php elseif($type === 'number'): ?>
              <input type="number" step="0.01" min="0" max="100"
                     name="<?= htmlspecialchars($col) ?>"
                     value="<?= htmlspecialchars((string)($store[$col] ?? '0')) ?>">

            <?php else: ?>
              <input type="<?= htmlspecialchars($type) ?>"
                     name="<?= htmlspecialchars($col) ?>"
                     value="<?= htmlspecialchars((string)($store[$col] ?? '')) ?>">
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <button class="btn" type="submit">Simpan Pengaturan</button>
        <a class="muted" href="admin_persediaan.php" style="text-decoration:none;">← Kembali</a>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../publik/partials/admin_layout_bottom.php'; ?>

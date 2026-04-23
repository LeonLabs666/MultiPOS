<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';
require_role(['admin']);

$appName='MultiPOS';
$pageTitle='Konversi Unit';
$activeMenu='persediaan';
$adminId=(int)auth_user()['id'];

$error=''; $ok='';

// ambil toko admin
$st = $pdo->prepare("SELECT id,name FROM stores WHERE owner_admin_id=? AND is_active=1 LIMIT 1");
$st->execute([$adminId]);
$store = $st->fetch();
if (!$store) { http_response_code(400); exit('Admin belum terhubung ke toko.'); }

$storeId = (int)$store['id'];
$storeName = (string)$store['name'];

function clamp_dec($v, float $min=0.0, float $max=999999999.0): float {
  if (!is_numeric($v)) return 0.0;
  $f = (float)$v;
  if ($f < $min) $f = $min;
  if ($f > $max) $f = $max;
  return $f;
}

function ingredient_of_store(PDO $pdo, int $id, int $storeId): ?array {
  $st = $pdo->prepare("SELECT id,name,unit,is_active FROM ingredients WHERE id=? AND store_id=? LIMIT 1");
  $st->execute([$id,$storeId]);
  $r = $st->fetch(PDO::FETCH_ASSOC);
  return $r ?: null;
}

// ===== AUTO CREATE TABLE =====
try {
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS ingredient_unit_conversions (
      id INT AUTO_INCREMENT PRIMARY KEY,
      store_id INT NOT NULL,
      ingredient_id INT NOT NULL,
      unit_name VARCHAR(40) NOT NULL,
      to_base_qty DECIMAL(18,6) NOT NULL DEFAULT 0,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NULL,
      UNIQUE KEY uk_ing_unit (ingredient_id, unit_name),
      INDEX idx_store (store_id),
      INDEX idx_ing (ingredient_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ");
} catch (Throwable $e) {
  // jika CREATE TABLE ditolak, nanti query berikut bisa error, tinggal kirim errornya
}

// ===== SELECT INGREDIENT =====
$ingredientId = (int)($_GET['ingredient_id'] ?? 0);

// ===== POST ACTIONS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $act = (string)($_POST['action'] ?? '');
  $ingredientIdPost = (int)($_POST['ingredient_id'] ?? 0);

  $ing = $ingredientIdPost ? ingredient_of_store($pdo, $ingredientIdPost, $storeId) : null;
  if ($ingredientIdPost && (!$ing || (int)$ing['is_active'] !== 1)) {
    header('Location: admin_konversi_unit.php?err=' . urlencode('Bahan tidak valid / nonaktif.'));
    exit;
  }

  // add/update conversion
  if ($act === 'save') {
    if ($ingredientIdPost <= 0) {
      header('Location: admin_konversi_unit.php?err=' . urlencode('Pilih bahan dulu.'));
      exit;
    }

    $unitName = trim((string)($_POST['unit_name'] ?? ''));
    if (strlen($unitName) > 40) $unitName = substr($unitName, 0, 40);

    $toBase = clamp_dec($_POST['to_base_qty'] ?? 0, 0.000001);

    if ($unitName === '') {
      header('Location: admin_konversi_unit.php?ingredient_id='.$ingredientIdPost.'&err=' . urlencode('Nama unit wajib diisi.'));
      exit;
    }
    if ($toBase <= 0) {
      header('Location: admin_konversi_unit.php?ingredient_id='.$ingredientIdPost.'&err=' . urlencode('Nilai konversi harus > 0.'));
      exit;
    }

    try {
      $pdo->prepare("
        INSERT INTO ingredient_unit_conversions (store_id, ingredient_id, unit_name, to_base_qty, created_at, updated_at)
        VALUES (?,?,?,?,NOW(),NOW())
        ON DUPLICATE KEY UPDATE to_base_qty=VALUES(to_base_qty), updated_at=NOW()
      ")->execute([$storeId, $ingredientIdPost, $unitName, $toBase]);

      header('Location: admin_konversi_unit.php?ingredient_id='.$ingredientIdPost.'&ok=' . urlencode('Konversi tersimpan.'));
      exit;
    } catch (Throwable $e) {
      header('Location: admin_konversi_unit.php?ingredient_id='.$ingredientIdPost.'&err=' . urlencode('Gagal simpan konversi.'));
      exit;
    }
  }

  // delete conversion
  if ($act === 'delete') {
    $cid = (int)($_POST['conv_id'] ?? 0);
    if ($ingredientIdPost <= 0) {
      header('Location: admin_konversi_unit.php?err=' . urlencode('Pilih bahan dulu.'));
      exit;
    }

    $pdo->prepare("DELETE FROM ingredient_unit_conversions WHERE id=? AND ingredient_id=? AND store_id=?")
        ->execute([$cid, $ingredientIdPost, $storeId]);

    header('Location: admin_konversi_unit.php?ingredient_id='.$ingredientIdPost.'&ok=' . urlencode('Konversi dihapus.'));
    exit;
  }

  // quick fill standard
  if ($act === 'quick_fill') {
    if ($ingredientIdPost <= 0) {
      header('Location: admin_konversi_unit.php?err=' . urlencode('Pilih bahan dulu.'));
      exit;
    }

    $base = (string)($ing['unit'] ?? '');

    // mapping standar (unit_name => to_base_qty)
    $defaults = [];

    // berat
    if ($base === 'gram' || $base === 'g') {
      $defaults = [
        'kilogram' => 1000,
        'miligram' => 0.001,
      ];
    } elseif ($base === 'kilogram' || $base === 'kg') {
      $defaults = [
        'gram'     => 0.001,   // 1 gram = 0.001 kg
        'miligram' => 0.000001 // 1 mg = 0.000001 kg
      ];
    } elseif ($base === 'miligram' || $base === 'mg') {
      $defaults = [
        'gram'     => 1000,
        'kilogram' => 1000000
      ];
    }

    // volume
    if ($base === 'milliliter' || $base === 'ml') {
      $defaults = $defaults ?: ['liter' => 1000];
    } elseif ($base === 'liter' || $base === 'L' || $base === 'l') {
      $defaults = $defaults ?: ['milliliter' => 0.001]; // 1 ml = 0.001 liter
    }

    // butir
    if ($base === 'pcs') {
      $defaults = $defaults ?: [
        'pack-6' => 6,
        'dus-12' => 12
      ];
    }

    if (!$defaults) {
      header('Location: admin_konversi_unit.php?ingredient_id='.$ingredientIdPost.'&err=' . urlencode('Tidak ada preset untuk base unit ini.'));
      exit;
    }

    try {
      $ins = $pdo->prepare("
        INSERT INTO ingredient_unit_conversions (store_id, ingredient_id, unit_name, to_base_qty, created_at, updated_at)
        VALUES (?,?,?,?,NOW(),NOW())
        ON DUPLICATE KEY UPDATE to_base_qty=VALUES(to_base_qty), updated_at=NOW()
      ");
      foreach ($defaults as $uName => $toBase) {
        $ins->execute([$storeId, $ingredientIdPost, $uName, (float)$toBase]);
      }

      header('Location: admin_konversi_unit.php?ingredient_id='.$ingredientIdPost.'&ok=' . urlencode('Preset konversi berhasil diisi.'));
      exit;
    } catch (Throwable $e) {
      header('Location: admin_konversi_unit.php?ingredient_id='.$ingredientIdPost.'&err=' . urlencode('Gagal isi preset.'));
      exit;
    }
  }

  header('Location: admin_konversi_unit.php' . ($ingredientIdPost ? ('?ingredient_id='.$ingredientIdPost) : ''));
  exit;
}

// ===== GET DATA =====
$error = (string)($_GET['err'] ?? '');
$ok = (string)($_GET['ok'] ?? '');

$ingredientsQ = $pdo->prepare("SELECT id,name,unit FROM ingredients WHERE store_id=? AND is_active=1 ORDER BY name ASC");
$ingredientsQ->execute([$storeId]);
$ingredients = $ingredientsQ->fetchAll(PDO::FETCH_ASSOC);

$selected = null;
$conversions = [];
if ($ingredientId > 0) {
  $selected = ingredient_of_store($pdo, $ingredientId, $storeId);
  if ($selected && (int)$selected['is_active'] === 1) {
    $cq = $pdo->prepare("
      SELECT id, unit_name, to_base_qty, COALESCE(updated_at, created_at) AS last_at
      FROM ingredient_unit_conversions
      WHERE store_id=? AND ingredient_id=?
      ORDER BY unit_name ASC
    ");
    $cq->execute([$storeId, $ingredientId]);
    $conversions = $cq->fetchAll(PDO::FETCH_ASSOC);
  } else {
    $selected = null;
  }
}

require __DIR__ . '/../publik/partials/admin_layout_top.php';
?>

<style>
  .wrap{max-width:1200px;}
  .muted{color:#64748b}
  .top-row{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:10px}
  .btn-link{
    display:inline-flex;align-items:center;gap:8px;
    padding:8px 12px;border-radius:999px;border:1px solid #e2e8f0;
    text-decoration:none;color:#0f172a;background:#fff;font-size:13px
  }
  .panel{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:14px}
  .grid2{display:grid;grid-template-columns:1fr 1.2fr;gap:14px}
  @media (max-width: 980px){ .grid2{grid-template-columns:1fr} }

  label{font-size:13px;color:#334155;display:block;margin:0 0 6px}
  input,select{padding:10px 12px;border-radius:12px;border:1px solid #e2e8f0;width:100%}
  .btn{
    padding:10px 14px;border-radius:12px;border:1px solid #e2e8f0;
    background:#2563eb;color:#fff;cursor:pointer
  }
  .btn-ghost{
    padding:10px 14px;border-radius:12px;border:1px solid #e2e8f0;background:#fff;color:#0f172a;cursor:pointer
  }
  .btn-danger{
    padding:8px 12px;border-radius:12px;border:1px solid #fecaca;background:#fff;color:#ef4444;cursor:pointer
  }

  table{width:100%;border-collapse:collapse}
  th,td{border-bottom:1px solid #f1f5f9;padding:10px 8px;text-align:left;font-size:13px}
  th{color:#64748b;font-weight:800}
</style>

<div class="wrap">
  <div class="top-row">
    <div>
      <h1 style="margin:0 0 4px;">Konversi Unit</h1>
      <div class="muted" style="font-size:13px;">Set konversi unit per bahan (mis. 1 kg = 1000 gram, 1 pack = 6 pcs).</div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <a class="btn-link" href="admin_persediaan_bahan.php">← Daftar Bahan</a>
      <a class="btn-link" href="admin_persediaan.php">Persediaan</a>
    </div>
  </div>

  <?php if($error):?><div style="color:#ef4444;margin:10px 0;"><?=htmlspecialchars($error)?></div><?php endif;?>
  <?php if($ok):?><div style="color:#16a34a;margin:10px 0;"><?=htmlspecialchars($ok)?></div><?php endif;?>

  <div class="panel" style="margin-bottom:14px;">
    <label>Pilih Bahan</label>
    <form method="get">
      <select name="ingredient_id" onchange="this.form.submit()">
        <option value="0">-- pilih bahan --</option>
        <?php foreach($ingredients as $i): ?>
          <option value="<?= (int)$i['id'] ?>" <?= ($ingredientId===(int)$i['id']?'selected':'') ?>>
            <?= htmlspecialchars((string)$i['name']) ?> (base: <?= htmlspecialchars((string)$i['unit']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <div class="grid2">

    <!-- FORM SAVE -->
    <div class="panel">
      <h3 style="margin:0 0 10px;">Tambah / Update Konversi</h3>

      <?php if(!$selected): ?>
        <div class="muted">Pilih bahan dulu untuk mengatur konversi.</div>
      <?php else: ?>
        <div class="muted" style="font-size:13px;margin-bottom:10px;">
          Bahan: <b><?= htmlspecialchars((string)$selected['name']) ?></b> • Base unit: <b><?= htmlspecialchars((string)$selected['unit']) ?></b><br>
          Artinya: <b>1 (unit_name)</b> = <b>to_base_qty</b> (base unit)
        </div>

        <form method="post">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="ingredient_id" value="<?= (int)$ingredientId ?>">

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div>
              <label>Nama Unit</label>
              <input name="unit_name" placeholder="contoh: kilogram / liter / pack-6" required>
            </div>
            <div>
              <label>to_base_qty</label>
              <input type="number" step="0.000001" min="0.000001" name="to_base_qty" placeholder="contoh: 1000" required>
            </div>
          </div>

          <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;">
            <button class="btn" type="submit">Simpan Konversi</button>

            <form method="post" style="margin:0;">
              <!-- nested form not allowed in HTML; jadi tombol preset dipisah di bawah -->
            </form>
          </div>
        </form>

        <form method="post" style="margin-top:10px;">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <input type="hidden" name="action" value="quick_fill">
          <input type="hidden" name="ingredient_id" value="<?= (int)$ingredientId ?>">
          <button class="btn-ghost" type="submit">Isi cepat konversi standar</button>
        </form>

        <div class="muted" style="font-size:12px;margin-top:10px;">
          Tips: untuk base <b>pcs</b>, isi <b>pack-6 = 6</b>, <b>dus-12 = 12</b>. Untuk base <b>gram</b>, isi <b>kilogram = 1000</b>.
        </div>
      <?php endif; ?>
    </div>

    <!-- LIST -->
    <div class="panel">
      <h3 style="margin:0 0 10px;">Daftar Konversi</h3>

      <?php if(!$selected): ?>
        <div class="muted">Belum ada bahan yang dipilih.</div>
      <?php elseif(!$conversions): ?>
        <div class="muted">Belum ada konversi.</div>
      <?php else: ?>
        <table>
          <tr>
            <th>Unit</th>
            <th>Nilai</th>
            <th style="width:160px;">Terakhir</th>
            <th style="width:110px;">Aksi</th>
          </tr>
          <?php foreach($conversions as $c): ?>
            <?php
              $val = rtrim(rtrim(number_format((float)$c['to_base_qty'], 6, '.', ''), '0'), '.');
            ?>
            <tr>
              <td><?= htmlspecialchars((string)$c['unit_name']) ?></td>
              <td>1 <?= htmlspecialchars((string)$c['unit_name']) ?> = <b><?= htmlspecialchars($val) ?></b> <?= htmlspecialchars((string)$selected['unit']) ?></td>
              <td><?= htmlspecialchars((string)$c['last_at']) ?></td>
              <td>
                <form method="post" style="margin:0;">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="ingredient_id" value="<?= (int)$ingredientId ?>">
                  <input type="hidden" name="conv_id" value="<?= (int)$c['id'] ?>">
                  <button class="btn-danger" type="submit" onclick="return confirm('Hapus konversi ini?')">Hapus</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>
    </div>

  </div>
</div>

<?php require __DIR__ . '/../publik/partials/admin_layout_bottom.php'; ?>

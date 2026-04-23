<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';

require_role(['admin']);

$appName = 'MultiPOS';
$pageTitle = 'Stok Opname';
$activeMenu = 'persediaan';
$adminId = (int)auth_user()['id'];

$error = '';
$ok = '';

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

// ===== HANDLE POST: SIMPAN OPNAME =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $act = (string)($_POST['action'] ?? '');

  if ($act === 'opname_save') {
    $note = trim((string)($_POST['note'] ?? ''));
    if (strlen($note) > 255) $note = substr($note, 0, 255);

    $actuals = $_POST['actual'] ?? [];
    if (!is_array($actuals)) $actuals = [];

    try {
      $pdo->beginTransaction();

      // header opname
      $pdo->prepare("
        INSERT INTO stock_opnames (store_id, admin_id, note, created_at)
        VALUES (?,?,?,NOW())
      ")->execute([$storeId, $adminId, $note]);

      $opnameId = (int)$pdo->lastInsertId();
      if ($opnameId <= 0) throw new RuntimeException('Gagal membuat opname.');

      // ambil semua produk aktif
      $prodQ = $pdo->prepare("SELECT id,name,sku,stock FROM products WHERE store_id=? AND is_active=1 ORDER BY name ASC");
      $prodQ->execute([$storeId]);
      $products = $prodQ->fetchAll(PDO::FETCH_ASSOC);

      $itemIns = $pdo->prepare("
        INSERT INTO stock_opname_items (opname_id, product_id, name, sku, system_stock, actual_stock, diff)
        VALUES (?,?,?,?,?,?,?)
      ");

      $updStock = $pdo->prepare("UPDATE products SET stock=? WHERE id=? AND store_id=?");

      // movement log supaya kebaca di riwayat
      $movIns = $pdo->prepare("
        INSERT INTO stock_movements (store_id,target_type,target_id,direction,qty,unit,note,created_by,created_at)
        VALUES (?,?,?,?,?,?,?,?,NOW())
      ");

      $any = false;

      foreach ($products as $p) {
        $pid = (int)$p['id'];

        // kosong = skip (opname sebagian)
        if (!array_key_exists((string)$pid, $actuals)) continue;

        $actualRaw = $actuals[(string)$pid];
        if ($actualRaw === '' || $actualRaw === null) continue;

        $sys = (float)($p['stock'] ?? 0);
        $actual = clamp_dec($actualRaw, 0.0);
        $diff = $actual - $sys;

        $name = (string)($p['name'] ?? '');
        $sku  = (string)($p['sku'] ?? '');

        $itemIns->execute([$opnameId, $pid, $name, $sku, $sys, $actual, $diff]);

        $updStock->execute([$actual, $pid, $storeId]);

        if (abs($diff) > 0.000001) {
          $direction = $diff >= 0 ? 'in' : 'out';
          $qty = abs($diff);
          $movIns->execute([
            $storeId,
            'product',
            $pid,
            $direction,
            $qty,
            'pcs',
            'Opname #' . $opnameId . ($note !== '' ? (' • ' . $note) : ''),
            $adminId
          ]);
        }

        $any = true;
      }

      if (!$any) {
        throw new RuntimeException('Belum ada produk yang diinput stok fisiknya.');
      }

      $pdo->commit();
      header('Location: admin_stok_opname.php?view=' . $opnameId);
      exit;

    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $error = $e->getMessage() ?: 'Gagal menyimpan opname.';
      header('Location: admin_stok_opname.php?err=' . urlencode($error));
      exit;
    }
  }

  header('Location: admin_stok_opname.php');
  exit;
}

// ===== GET DATA =====
$errGet = (string)($_GET['err'] ?? '');
if ($errGet !== '') $error = $errGet;

$viewId = (int)($_GET['view'] ?? 0);
$viewHeader = null;
$viewItems = [];

// list produk utk form
$pq = $pdo->prepare("SELECT id,name,sku,stock FROM products WHERE store_id=? AND is_active=1 ORDER BY name ASC");
$pq->execute([$storeId]);
$products = $pq->fetchAll(PDO::FETCH_ASSOC);

// list opname terakhir
$oq = $pdo->prepare("
  SELECT so.id, so.created_at, so.note, u.name AS admin_name
  FROM stock_opnames so
  LEFT JOIN users u ON u.id = so.admin_id
  WHERE so.store_id=?
  ORDER BY so.id DESC
  LIMIT 20
");
$oq->execute([$storeId]);
$opnames = $oq->fetchAll(PDO::FETCH_ASSOC);

// detail opname
if ($viewId > 0) {
  $vh = $pdo->prepare("
    SELECT so.*, u.name AS admin_name
    FROM stock_opnames so
    LEFT JOIN users u ON u.id = so.admin_id
    WHERE so.store_id=? AND so.id=?
    LIMIT 1
  ");
  $vh->execute([$storeId, $viewId]);
  $viewHeader = $vh->fetch(PDO::FETCH_ASSOC);

  if ($viewHeader) {
    $vi = $pdo->prepare("SELECT * FROM stock_opname_items WHERE opname_id=? ORDER BY name ASC");
    $vi->execute([$viewId]);
    $viewItems = $vi->fetchAll(PDO::FETCH_ASSOC);
  }
}

require __DIR__ . '/../publik/partials/admin_layout_top.php';
?>

<style>
  .inv-wrap{max-width:1200px;}
  .muted{color:#64748b}
  .grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
  @media (max-width: 980px){ .grid2{grid-template-columns:1fr} }
  .card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:14px;}
  .btn{padding:10px 14px;border-radius:12px;border:1px solid #e2e8f0;background:#0f172a;color:#fff;cursor:pointer}
  .btn:active{transform:translateY(1px)}
  input,select,textarea{padding:10px 12px;border-radius:12px;border:1px solid #e2e8f0;width:100%}
  label{font-size:13px;color:#334155}
  table{width:100%;border-collapse:collapse}
  th,td{border-bottom:1px solid #f1f5f9;padding:8px 6px;text-align:left;font-size:13px}
  th{color:#64748b;font-weight:700}
  .pill{display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;border:1px solid #e2e8f0}
  .pill.in{background:#ecfdf5;border-color:#bbf7d0;color:#166534}
  .pill.out{background:#fef2f2;border-color:#fecaca;color:#991b1b}
</style>

<div class="inv-wrap">
  <h1 style="margin:0 0 6px;">Stok Opname</h1>

  <?php if($error):?><p style="color:#ef4444;"><?=htmlspecialchars($error)?></p><?php endif;?>
  <?php if($ok):?><p style="color:#16a34a;"><?=htmlspecialchars($ok)?></p><?php endif;?>

  <div class="grid2">

    <!-- FORM OPNAME -->
    <div class="card">
      <h3 style="margin:0 0 10px;">Buat Opname Baru</h3>
      <div class="muted" style="font-size:12px;margin-bottom:10px;">
        Isi stok fisik untuk produk yang mau di periksa (boleh sebagian). Setelah disimpan, stok produk akan diset ke stok fisik.
      </div>

      <form method="post">
        <input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>">
        <input type="hidden" name="action" value="opname_save">

        <div style="margin-bottom:10px;">
          <label>Catatan</label>
          <input name="note" placeholder="contoh: opname akhir bulan / koreksi gudang">
        </div>

        <div style="max-height:520px;overflow:auto;border:1px solid #f1f5f9;border-radius:12px;">
          <table>
            <tr>
              <th>Produk</th>
              <th style="width:120px;">Sistem</th>
              <th style="width:160px;">Stok Fisik</th>
            </tr>

            <?php foreach($products as $p): ?>
              <tr>
                <td>
                  <div style="font-weight:700;"><?= htmlspecialchars((string)$p['name']) ?></div>
                  <div class="muted" style="font-size:12px;">
                    <?php if((string)($p['sku'] ?? '') !== ''): ?>SKU: <?= htmlspecialchars((string)$p['sku']) ?><?php endif; ?>
                  </div>
                </td>
                <td><?= rtrim(rtrim(number_format((float)$p['stock'],3,'.',''), '0'), '.') ?></td>
                <td>
                  <input type="number" step="0.001" min="0" name="actual[<?= (int)$p['id'] ?>]" placeholder="kosong = skip">
                </td>
              </tr>
            <?php endforeach; if(!$products): ?>
              <tr><td colspan="3">Belum ada produk.</td></tr>
            <?php endif; ?>
          </table>
        </div>

        <div style="margin-top:12px;">
          <button class="btn" type="submit" style="width:100%;">SIMPAN OPNAME</button>
        </div>

        <div class="muted" style="font-size:12px;margin-top:10px;">
          <a href="admin_persediaan.php?tab=bahan">← Kembali ke Persediaan</a>
        </div>
      </form>
    </div>

    <!-- LIST OPNAME + DETAIL -->
    <div class="card">
      <h3 style="margin:0 0 10px;">Riwayat Opname</h3>

      <?php if(!$opnames): ?>
        <div class="muted">Belum ada opname.</div>
      <?php else: ?>
        <table>
          <tr>
            <th style="width:90px;">ID</th>
            <th style="width:160px;">Waktu</th>
            <th>Catatan</th>
            <th style="width:140px;">Oleh</th>
            <th style="width:110px;">Aksi</th>
          </tr>
          <?php foreach($opnames as $o): ?>
            <tr>
              <td>#<?= (int)$o['id'] ?></td>
              <td><?= htmlspecialchars((string)$o['created_at']) ?></td>
              <td><?= htmlspecialchars((string)($o['note'] ?? '')) ?></td>
              <td><?= htmlspecialchars((string)($o['admin_name'] ?? '')) ?></td>
              <td><a href="admin_stok_opname.php?view=<?= (int)$o['id'] ?>">Detail</a></td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>

      <?php if($viewHeader): ?>
        <hr style="border:none;border-top:1px solid #f1f5f9;margin:14px 0;">
        <h3 style="margin:0 0 8px;">Detail Opname #<?= (int)$viewHeader['id'] ?></h3>
        <div class="muted" style="font-size:12px;margin-bottom:10px;">
          <?= htmlspecialchars((string)$viewHeader['created_at']) ?>
          • <?= htmlspecialchars((string)($viewHeader['admin_name'] ?? '')) ?>
          <?php if((string)($viewHeader['note'] ?? '') !== ''): ?>
            • <?= htmlspecialchars((string)$viewHeader['note']) ?>
          <?php endif; ?>
        </div>

        <table>
          <tr>
            <th>Produk</th>
            <th style="width:120px;">Sistem</th>
            <th style="width:120px;">Fisik</th>
            <th style="width:120px;">Selisih</th>
          </tr>
          <?php foreach($viewItems as $it): ?>
            <?php
              $sys = (float)($it['system_stock'] ?? 0);
              $act = (float)($it['actual_stock'] ?? 0);
              $diff = (float)($it['diff'] ?? ($act - $sys));
            ?>
            <tr>
              <td><?= htmlspecialchars((string)($it['name'] ?? '')) ?></td>
              <td><?= rtrim(rtrim(number_format($sys,3,'.',''), '0'), '.') ?></td>
              <td><?= rtrim(rtrim(number_format($act,3,'.',''), '0'), '.') ?></td>
              <td>
                <?php if($diff >= 0): ?>
                  <span class="pill in">+<?= rtrim(rtrim(number_format($diff,3,'.',''), '0'), '.') ?></span>
                <?php else: ?>
                  <span class="pill out"><?= rtrim(rtrim(number_format($diff,3,'.',''), '0'), '.') ?></span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; if(!$viewItems): ?>
            <tr><td colspan="4">Tidak ada item.</td></tr>
          <?php endif; ?>
        </table>
      <?php endif; ?>
    </div>

  </div>
</div>

<?php require __DIR__ . '/../publik/partials/admin_layout_bottom.php'; ?>

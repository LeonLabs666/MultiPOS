<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/inventory.php';
require_role(['dapur']);

$dapurId = (int)auth_user()['id'];

// Ambil store dari admin pembuat akun dapur
$st = $pdo->prepare("
  SELECT s.id, s.name
  FROM users u
  JOIN stores s ON s.owner_admin_id = u.created_by
  WHERE u.id=? AND s.is_active=1
  LIMIT 1
");
$st->execute([$dapurId]);
$store = $st->fetch(PDO::FETCH_ASSOC);
if (!$store) {
  http_response_code(400);
  exit('User dapur belum terhubung ke toko.');
}

$storeId   = (int)$store['id'];
$storeName = (string)$store['name'];

// UI vars untuk layout dapur
$appName    = 'MultiPOS';
$pageTitle  = 'Produksi';
$activeMenu = 'produksi';
$userName   = (string)auth_user()['name'];

// Tab: pesanan | stok
$tab = (string)($_GET['tab'] ?? 'pesanan');
if (!in_array($tab, ['pesanan', 'stok'], true)) {
  $tab = 'pesanan';
}

// flash message
$error = (string)($_SESSION['flash_error'] ?? '');
$ok    = (string)($_SESSION['flash_ok'] ?? '');
unset($_SESSION['flash_error'], $_SESSION['flash_ok']);

/**
 * ==========================================================
 * A) PRODUKSI STOK (BATCH) - POST
 * menambah products.stock + otomatis kurangi ingredients sesuai BOM
 * + insert stock_movements (ingredient out, product in)
 * ==========================================================
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $act = (string)($_POST['action'] ?? '');

  if ($act === 'produce_stock') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $qty = (int)round((float)($_POST['qty'] ?? 0));
    $note = trim((string)($_POST['note'] ?? ''));
    if (strlen($note) > 255) {
      $note = substr($note, 0, 255);
    }

    if ($productId <= 0) {
      $_SESSION['flash_error'] = 'Produk belum dipilih.';
      header('Location: dapur_produksi.php?tab=stok');
      exit;
    }

    if ($qty < 1) {
      $_SESSION['flash_error'] = 'Qty produksi minimal 1.';
      header('Location: dapur_produksi.php?tab=stok');
      exit;
    }

    try {
      $pdo->beginTransaction();

      // lock produk
      $pQ = $pdo->prepare("
        SELECT id, name, stock, is_active
        FROM products
        WHERE id=? AND store_id=?
        LIMIT 1
        FOR UPDATE
      ");
      $pQ->execute([$productId, $storeId]);
      $p = $pQ->fetch(PDO::FETCH_ASSOC);

      if (!$p || (int)$p['is_active'] !== 1) {
        throw new RuntimeException('Produk tidak valid.');
      }

      /**
       * Mode ADVANCE menyimpan BOM di:
       * - bom_recipes
       * - bom_recipe_items
       */
      $rQ = $pdo->prepare("
        SELECT
          bri.ingredient_id,
          bri.qty AS qty_per_unit,
          i.name AS ing_name,
          i.unit AS ing_unit,
          i.stock AS ing_stock,
          i.is_active
        FROM bom_recipes br
        JOIN bom_recipe_items bri ON bri.recipe_id = br.id
        JOIN ingredients i ON i.id = bri.ingredient_id
        WHERE br.store_id = ?
          AND br.product_id = ?
          AND br.is_active = 1
        ORDER BY i.name ASC
        FOR UPDATE
      ");
      $rQ->execute([$storeId, $productId]);
      $recipe = $rQ->fetchAll(PDO::FETCH_ASSOC);

      if (!$recipe) {
        throw new RuntimeException('Resep/BOM produk ini belum diatur oleh Admin.');
      }

      // validasi bahan cukup
      $needs = [];
      foreach ($recipe as $row) {
        if ((int)$row['is_active'] !== 1) {
          throw new RuntimeException('Ada bahan non-aktif di resep: ' . (string)$row['ing_name']);
        }

        $perUnit = (float)$row['qty_per_unit'];
        if ($perUnit <= 0) {
          throw new RuntimeException('Qty resep tidak valid untuk bahan: ' . (string)$row['ing_name']);
        }

        $need  = $perUnit * $qty;
        $stock = (float)$row['ing_stock'];

        if ($stock + 1e-9 < $need) {
          throw new RuntimeException(
            'Stok bahan tidak cukup: ' . (string)$row['ing_name'] .
            ' (butuh ' . rtrim(rtrim(number_format($need, 3, '.', ''), '0'), '.') . ' ' . (string)$row['ing_unit'] .
            ', stok ' . rtrim(rtrim(number_format($stock, 3, '.', ''), '0'), '.') . ' ' . (string)$row['ing_unit'] . ')'
          );
        }

        $needs[] = [
          'ingredient_id' => (int)$row['ingredient_id'],
          'ing_name'      => (string)$row['ing_name'],
          'unit'          => (string)($row['ing_unit'] ?: 'pcs'),
          'need'          => (float)$need,
          'stock'         => (float)$stock,
        ];
      }

      // kurangi stok bahan + movement OUT
      $noteBase  = 'Produksi Dapur • ' . (string)$p['name'] . ' x' . $qty;
      $finalNote = $noteBase . ($note !== '' ? (' • ' . $note) : '');

      foreach ($needs as $n) {
        $newIngStock = (float)$n['stock'] - (float)$n['need'];

        $pdo->prepare("
          UPDATE ingredients
          SET stock=?, updated_at=NOW()
          WHERE id=? AND store_id=?
        ")->execute([
          $newIngStock,
          $n['ingredient_id'],
          $storeId
        ]);

        $pdo->prepare("
          INSERT INTO stock_movements (
            store_id, target_type, target_id, direction, qty, unit, note, created_by, created_at
          )
          VALUES (?,?,?,?,?,?,?,?,NOW())
        ")->execute([
          $storeId,
          'ingredient',
          $n['ingredient_id'],
          'out',
          (float)$n['need'],
          $n['unit'],
          $finalNote,
          $dapurId
        ]);

        // penting: update avg usage / ROP / suggested restock bahan
        inv_recalc_ingredient_metrics($pdo, $storeId, (int)$n['ingredient_id']);
      }

      // tambah stok produk jadi + movement IN
      $newProdStock = (int)$p['stock'] + $qty;

      $pdo->prepare("
        UPDATE products
        SET stock=?
        WHERE id=? AND store_id=?
      ")->execute([
        $newProdStock,
        $productId,
        $storeId
      ]);

      $pdo->prepare("
        INSERT INTO stock_movements (
          store_id, target_type, target_id, direction, qty, unit, note, created_by, created_at
        )
        VALUES (?,?,?,?,?,?,?,?,NOW())
      ")->execute([
        $storeId,
        'product',
        $productId,
        'in',
        (float)$qty,
        'pcs',
        $finalNote,
        $dapurId
      ]);

      $pdo->commit();

      $_SESSION['flash_ok'] = 'Produksi tersimpan. Stok produk bertambah dan bahan otomatis terpotong.';
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      $_SESSION['flash_error'] = $e->getMessage() ?: 'Gagal menyimpan produksi.';
    }

    header('Location: dapur_produksi.php?tab=stok');
    exit;
  }

  header('Location: dapur_produksi.php?tab=' . urlencode($tab));
  exit;
}

/**
 * ==========================================================
 * B) PRODUKSI UNTUK PESANAN (REKAP MASAK) - GET
 * ==========================================================
 */
$sinceMin = (int)($_GET['since'] ?? 180);
if ($sinceMin < 5) $sinceMin = 5;
if ($sinceMin > 1440) $sinceMin = 1440;

$q = trim((string)($_GET['q'] ?? ''));
if (strlen($q) > 50) {
  $q = substr($q, 0, 50);
}

$sql = "
  SELECT si.product_id, si.name,
         SUM(si.qty) AS total_qty,
         COUNT(DISTINCT si.sale_id) AS order_count
  FROM sale_items si
  JOIN sales s ON s.id = si.sale_id
  WHERE s.store_id = ?
    AND s.kitchen_done = 0
    AND s.created_at >= (NOW() - INTERVAL ? MINUTE)
";
$params = [$storeId, $sinceMin];

if ($q !== '') {
  $sql .= " AND si.name LIKE ? ";
  $params[] = '%' . $q . '%';
}

$sql .= "
  GROUP BY si.product_id, si.name
  ORDER BY total_qty DESC, si.name ASC
";

$prodQ = $pdo->prepare($sql);
$prodQ->execute($params);
$rowsPesanan = $prodQ->fetchAll(PDO::FETCH_ASSOC);

$cntQ = $pdo->prepare("
  SELECT COUNT(*) AS c
  FROM sales
  WHERE store_id=? AND kitchen_done=0 AND created_at >= (NOW() - INTERVAL ? MINUTE)
");
$cntQ->execute([$storeId, $sinceMin]);
$pendingCount = (int)($cntQ->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

/**
 * ==========================================================
 * C) DATA PRODUK UNTUK PRODUKSI STOK
 * ==========================================================
 */
$productsQ = $pdo->prepare("
  SELECT id, sku, name, stock
  FROM products
  WHERE store_id=? AND is_active=1
  ORDER BY name ASC
");
$productsQ->execute([$storeId]);
$products = $productsQ->fetchAll(PDO::FETCH_ASSOC);

// Riwayat produksi stok terakhir
$movQ = $pdo->prepare("
  SELECT m.*, p.name AS product_name
  FROM stock_movements m
  LEFT JOIN products p ON p.id = m.target_id
  WHERE m.store_id = ?
    AND m.target_type = 'product'
    AND m.direction = 'in'
    AND m.created_by = ?
    AND (m.note LIKE 'Produksi Dapur%' OR m.note LIKE '%Produksi Dapur%')
  ORDER BY m.id DESC
  LIMIT 30
");
$movQ->execute([$storeId, $dapurId]);
$prodMovements = $movQ->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/../publik/partials/dapur_layout_top.php';
require __DIR__ . '/../publik/partials/dapur_sidebar.php';
?>

<main class="content">

  <?php if ($tab === 'pesanan'): ?>
    <meta http-equiv="refresh" content="20">
  <?php endif; ?>

  <style>
    .tabs{display:flex; gap:10px; flex-wrap:wrap; margin-bottom:14px;}
    .tabbtn{
      display:inline-flex; align-items:center; gap:8px;
      padding:10px 12px; border-radius: 14px;
      border:1px solid rgba(15,23,42,.10);
      background:#fff; text-decoration:none;
      font-weight:950; color:#0f172a;
      justify-content: space-between;
    }
    .tabbtn.active{
      border-color: rgba(37,99,235,.40);
      background: rgba(37,99,235,.08);
      color:#1d4ed8;
    }
    .grid2{display:grid; grid-template-columns: 1fr 1fr; gap:14px;}
    @media (max-width: 920px){ .grid2{grid-template-columns:1fr;} }
    .pill{display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;border:1px solid rgba(15,23,42,.10)}
    .pill.in{background:#ecfdf5;border-color:#bbf7d0;color:#166534}

    .btnRow { display:flex; gap:10px; flex-wrap:wrap; }
    .btn.linklike { text-decoration:none; display:inline-flex; align-items:center; justify-content:center; }
    .btn.disabledLink { opacity:.55; pointer-events:none; }

    @media (max-width: 640px){
      .topbar{align-items:flex-start;}
      .topbar .right{
        display:flex;
        flex-wrap:wrap;
        gap:8px;
        justify-content:flex-start;
        width:100%;
        margin-top:10px;
      }
      .topbar .right .badge{
        display:inline-flex;
        width:fit-content;
        max-width:100%;
        white-space:nowrap;
      }

      .tabs{
        gap:8px;
        flex-wrap:nowrap;
        overflow-x:auto;
        -webkit-overflow-scrolling:touch;
        padding-bottom:6px;
      }
      .tabbtn{
        flex:0 0 auto;
        min-width:180px;
      }

      .row{
        display:flex;
        flex-wrap:wrap;
        gap:10px;
      }
      input[type="text"],
      input[type="number"],
      select{
        width:100% !important;
        min-width:0 !important;
      }
      .btn{
        width:100%;
        justify-content:center;
      }
      .spacer{display:none;}
      .small.muted{line-height:1.2;}
    }
    @media (min-width: 641px){
      .btn{width:auto;}
    }
  </style>

  <div class="topbar">
    <div class="left">
      <button class="icon-btn" type="button"
        onclick="window.__dapurSidebarToggle && window.__dapurSidebarToggle()"
        aria-label="Menu">☰</button>

      <div>
        <p class="h" style="margin:0;">Produksi</p>
        <p class="p">Masak untuk pesanan, atau produksi batch untuk menambah stok produk.</p>
      </div>
    </div>
    <div class="right"></div>
  </div>

  <?php if ($error !== ''): ?>
    <div class="card" style="margin-bottom:14px; border-color: rgba(239,68,68,.35);">
      <b style="color:#ef4444;"><?= htmlspecialchars($error) ?></b>
    </div>
  <?php endif; ?>

  <?php if ($ok !== ''): ?>
    <div class="card" style="margin-bottom:14px; border-color: rgba(34,197,94,.35);">
      <b style="color:#16a34a;"><?= htmlspecialchars($ok) ?></b>
    </div>
  <?php endif; ?>

  <div class="tabs">
    <a class="tabbtn <?= $tab==='pesanan' ? 'active' : '' ?>" href="dapur_produksi.php?tab=pesanan&since=<?= (int)$sinceMin ?>&q=<?= urlencode($q) ?>">
      🧾 Produksi Pesanan
      <span class="pill"><?= (int)$pendingCount ?> pending</span>
    </a>
    <a class="tabbtn <?= $tab==='stok' ? 'active' : '' ?>" href="dapur_produksi.php?tab=stok">
      📦 Produksi Stok
    </a>
  </div>

  <?php if ($tab === 'pesanan'): ?>

    <div class="card" style="margin-bottom:14px;">
      <form method="get" class="row" style="margin:0;">
        <input type="hidden" name="tab" value="pesanan">

        <div class="row">
          <span class="small muted">Order terakhir</span>
          <input type="number" name="since" min="5" max="1440" value="<?= (int)$sinceMin ?>" style="width:110px;">
          <span class="small muted">menit</span>
        </div>

        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Cari nama menu..." style="width:220px;">

        <button class="btn" type="submit">Terapkan</button>
        <a class="btn" href="dapur_produksi.php?tab=pesanan">Reset</a>

        <span class="spacer"></span>
        <a class="btn" href="dapur_dashboard.php?status=belum&since=<?= (int)$sinceMin ?>">Lihat Antrian</a>
        <span class="small muted">Terakhir refresh: <?= date('Y-m-d H:i:s') ?></span>
      </form>
    </div>

    <?php if (!$rowsPesanan): ?>
      <div class="card">
        <b>Belum ada data produksi</b> pada rentang waktu ini.
      </div>
    <?php else: ?>
      <div class="card">
        <table width="100%">
          <tr>
            <th style="width:90px;">Total</th>
            <th>Menu</th>
            <th style="width:130px;">Dari Order</th>
          </tr>

          <?php foreach ($rowsPesanan as $r): ?>
            <tr>
              <td style="font-size:18px;font-weight:950;"><?= (int)$r['total_qty'] ?>x</td>
              <td><?= htmlspecialchars((string)$r['name']) ?></td>
              <td class="muted"><?= (int)$r['order_count'] ?> order</td>
            </tr>
          <?php endforeach; ?>
        </table>

        <div class="small muted" style="margin-top:10px;">
          Tips: halaman ini untuk masak batch berdasarkan pesanan pending. Untuk detail per order, buka “Antrian”.
        </div>
      </div>
    <?php endif; ?>

  <?php else: ?>

    <div class="grid2">

      <div class="card">
        <div style="font-weight:950; margin-bottom:10px;">Produksi untuk Stok (Tambah Produk Jadi + Potong Bahan Otomatis)</div>

        <form method="post">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <input type="hidden" name="action" value="produce_stock">

          <div style="margin-bottom:12px;">
            <label class="small muted" style="display:block;margin-bottom:6px;">Produk</label>

            <select id="productSelect" name="product_id" required>
              <option value="">-- pilih produk --</option>
              <?php foreach ($products as $p): ?>
                <option value="<?= (int)$p['id'] ?>">
                  <?= htmlspecialchars((string)$p['name']) ?>
                  <?php if (!empty($p['sku'])): ?> [<?= htmlspecialchars((string)$p['sku']) ?>]<?php endif; ?>
                  (stok <?= (int)$p['stock'] ?> pcs)
                </option>
              <?php endforeach; ?>
            </select>

            <div class="btnRow" style="margin-top:10px;">
              <a id="btnLihatResep"
                 class="btn linklike disabledLink"
                 href="#"
                 title="Pilih produk dulu untuk melihat resep">
                📖 Lihat Resep
              </a>
            </div>

            <div class="small muted" style="margin-top:8px;">
              * Produksi hanya bisa jika resep/BOM produk sudah diatur Admin.
            </div>
          </div>

          <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
            <div>
              <label class="small muted" style="display:block;margin-bottom:6px;">Qty diproduksi</label>
              <input type="number" name="qty" min="1" step="1" value="1" required>
            </div>
            <div>
              <label class="small muted" style="display:block;margin-bottom:6px;">Satuan</label>
              <input type="text" value="pcs" disabled>
            </div>
          </div>

          <div style="margin-bottom:12px;">
            <label class="small muted" style="display:block;margin-bottom:6px;">Catatan (opsional)</label>
            <input type="text" name="note" placeholder="cth: Produksi pagi / Batch 1 / untuk etalase">
          </div>

          <button class="btn" type="submit" style="width:100%;">SIMPAN PRODUKSI</button>

          <div class="small muted" style="margin-top:10px;">
            Sistem akan: (1) cek stok bahan, (2) potong bahan, (3) tambah stok produk jadi, (4) catat movement.
          </div>
        </form>
      </div>

      <div class="card">
        <div style="font-weight:950; margin-bottom:10px;">Riwayat Produksi Stok (Terakhir)</div>
        <div class="small muted" style="margin-bottom:10px;">Menampilkan 30 data terakhir yang dibuat oleh user dapur ini.</div>

        <?php if (!$prodMovements): ?>
          <div class="muted">Belum ada riwayat produksi stok.</div>
        <?php else: ?>
          <table width="100%">
            <tr>
              <th>Waktu</th>
              <th>Produk</th>
              <th style="text-align:right;">Qty</th>
              <th>Catatan</th>
            </tr>
            <?php foreach ($prodMovements as $m): ?>
              <tr>
                <td><?= htmlspecialchars((string)$m['created_at']) ?></td>
                <td><?= htmlspecialchars((string)($m['product_name'] ?? '')) ?></td>
                <td style="text-align:right;">
                  <span class="pill in">+<?= (int)round((float)$m['qty']) ?> pcs</span>
                </td>
                <td><?= htmlspecialchars((string)$m['note']) ?></td>
              </tr>
            <?php endforeach; ?>
          </table>
        <?php endif; ?>
      </div>

    </div>

    <script>
      (function () {
        var sel = document.getElementById('productSelect');
        var btn = document.getElementById('btnLihatResep');
        if (!sel || !btn) return;

        function syncLink() {
          var v = (sel.value || '').trim();
          if (!v) {
            btn.href = '#';
            btn.classList.add('disabledLink');
            btn.title = 'Pilih produk dulu untuk melihat resep';
            return;
          }
          btn.href = 'dapur_resep.php?product_id=' + encodeURIComponent(v);
          btn.classList.remove('disabledLink');
          btn.title = 'Lihat resep & cara pembuatan';
        }

        sel.addEventListener('change', syncLink);
        syncLink();
      })();
    </script>

  <?php endif; ?>

</main>

<?php require __DIR__ . '/../publik/partials/dapur_layout_bottom.php'; ?>
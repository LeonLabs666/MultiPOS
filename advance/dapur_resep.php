<?php
// public/dapur_resep.php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';
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
$store = $st->fetch();
if (!$store) { http_response_code(400); exit('User dapur belum terhubung ke toko.'); }

$storeId   = (int)$store['id'];
$storeName = (string)$store['name'];

// UI vars (kalau layout kamu pakai variabel ini)
$appName    = 'MultiPOS';
$pageTitle  = 'Resep Produk';
$activeMenu = 'produksi';
$userName   = (string)auth_user()['name'];

$productId = (int)($_GET['product_id'] ?? 0);
$plannedQty = (float)($_GET['qty'] ?? 1);
if ($plannedQty <= 0) $plannedQty = 1;

$error = '';
$selectedProduct = null;
$recipe = null;
$items = [];

// daftar produk untuk dropdown
$productsQ = $pdo->prepare("SELECT id, sku, name FROM products WHERE store_id=? AND is_active=1 ORDER BY name ASC");
$productsQ->execute([$storeId]);
$products = $productsQ->fetchAll();

if ($productId > 0) {
  try {
    // validasi produk milik store ini
    $pQ = $pdo->prepare("SELECT id, sku, name FROM products WHERE id=? AND store_id=? AND is_active=1 LIMIT 1");
    $pQ->execute([$productId, $storeId]);
    $selectedProduct = $pQ->fetch();

    if (!$selectedProduct) {
      $error = 'Produk tidak ditemukan / tidak aktif.';
    } else {
      // ambil resep aktif (BOM) terbaru
      $rQ = $pdo->prepare("
        SELECT id, instructions
        FROM bom_recipes
        WHERE store_id=? AND product_id=? AND is_active=1
        ORDER BY id DESC
        LIMIT 1
      ");
      $rQ->execute([$storeId, $productId]);
      $recipe = $rQ->fetch();

      if (!$recipe) {
        $error = 'Resep/BOM belum diatur oleh Admin untuk produk ini.';
      } else {
        $recipeId = (int)$recipe['id'];

        // ambil item bahan (qty per 1 produk)
        $iQ = $pdo->prepare("
          SELECT
            i.id AS ingredient_id,
            i.name AS ingredient_name,
            i.unit AS unit,
            i.stock AS ingredient_stock,
            ri.qty AS qty_per_unit
          FROM bom_recipe_items ri
          JOIN ingredients i ON i.id = ri.ingredient_id
          WHERE ri.recipe_id=? AND i.store_id=?
          ORDER BY i.name ASC
        ");
        $iQ->execute([$recipeId, $storeId]);
        $items = $iQ->fetchAll();
      }
    }
  } catch (Throwable $e) {
    $error = $e->getMessage() ?: 'Gagal memuat resep.';
  }
}

// Kalau kamu mau pakai layout dapur yang sama:
require __DIR__ . '/../publik/partials/dapur_layout_top.php';
require __DIR__ . '/../publik/partials/dapur_sidebar.php';
?>

<main class="content">
  <style>
    .grid2{display:grid; grid-template-columns: 1fr 1fr; gap:14px;}
    @media (max-width: 920px){ .grid2{grid-template-columns:1fr;} }
    .pill{display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;border:1px solid rgba(15,23,42,.10)}
    .pill.ok{background:#ecfdf5;border-color:#bbf7d0;color:#166534}
    .pill.bad{background:#fff7ed;border-color:#fed7aa;color:#9a3412}
    table{width:100%; border-collapse:collapse;}
    th,td{padding:10px; border-bottom:1px solid rgba(15,23,42,.08); text-align:left; vertical-align:top;}
    th{font-size:12px; opacity:.8;}
    .right{text-align:right;}
  </style>

  <div class="topbar">
    <div class="left">
      <button class="icon-btn" type="button"
        onclick="window.__dapurSidebarToggle && window.__dapurSidebarToggle()"
        aria-label="Menu">☰</button>

      <div>
        <p class="h" style="margin:0;">Resep Produk</p>
        <p class="p">Lihat bahan (BOM) dan cara pembuatan dari data Admin.</p>
      </div>
    </div>
    <div class="right">
      <span class="badge">Toko: <?= htmlspecialchars($storeName) ?></span>
      <span class="badge">User: <?= htmlspecialchars($userName) ?></span>
    </div>
  </div>

  <div class="card" style="margin-bottom:14px;">
    <form method="get" class="row" style="margin:0;">
      <div style="min-width:320px;">
        <label class="small muted" style="display:block;margin-bottom:6px;">Produk</label>
        <select name="product_id" required>
          <option value="">-- pilih produk --</option>
          <?php foreach ($products as $p): ?>
            <option value="<?= (int)$p['id'] ?>" <?= $productId===(int)$p['id']?'selected':'' ?>>
              <?= htmlspecialchars((string)$p['name']) ?>
              <?php if (!empty($p['sku'])): ?> [<?= htmlspecialchars((string)$p['sku']) ?>]<?php endif; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="min-width:160px;">
        <label class="small muted" style="display:block;margin-bottom:6px;">Rencana Qty</label>
        <input type="number" name="qty" min="0.01" step="0.01" value="<?= htmlspecialchars((string)$plannedQty) ?>">
      </div>

      <div style="align-self:end;">
        <button class="btn" type="submit">Tampilkan</button>
        <a class="btn" href="dapur_resep.php">Reset</a>
        <a class="btn" href="dapur_produksi.php?tab=stok">Kembali</a>
      </div>
    </form>

    <div class="small muted" style="margin-top:10px;">
      * Simulasi kebutuhan bahan: qty per 1 produk × rencana qty. Tidak mengubah stok.
    </div>
  </div>

  <?php if ($error !== ''): ?>
    <div class="card" style="border-color: rgba(239,68,68,.35); margin-bottom:14px;">
      <b style="color:#ef4444;"><?= htmlspecialchars($error) ?></b>
    </div>
  <?php endif; ?>

  <?php if ($selectedProduct && $recipe): ?>
    <div class="grid2">

      <div class="card">
        <div style="font-weight:950; margin-bottom:10px;">BOM (Bahan)</div>

        <?php if (!$items): ?>
          <div class="muted">Item bahan belum diisi.</div>
        <?php else: ?>
          <table>
            <tr>
              <th>Bahan</th>
              <th>Satuan</th>
              <th class="right">Stok</th>
              <th class="right">/1 Produk</th>
              <th class="right">Total</th>
              <th>Status</th>
            </tr>
            <?php foreach ($items as $it):
              $stock = (float)$it['ingredient_stock'];
              $per = (float)$it['qty_per_unit'];
              $need = $per * $plannedQty;
              $okStock = ($stock + 1e-9 >= $need);
            ?>
              <tr>
                <td><?= htmlspecialchars((string)$it['ingredient_name']) ?></td>
                <td><?= htmlspecialchars((string)($it['unit'] ?? '')) ?></td>
                <td class="right"><?= rtrim(rtrim(number_format($stock,3,'.',''), '0'), '.') ?></td>
                <td class="right"><?= rtrim(rtrim(number_format($per,3,'.',''), '0'), '.') ?></td>
                <td class="right"><?= rtrim(rtrim(number_format($need,3,'.',''), '0'), '.') ?></td>
                <td>
                  <span class="pill <?= $okStock ? 'ok' : 'bad' ?>">
                    <?= $okStock ? 'Cukup' : 'Kurang' ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          </table>
        <?php endif; ?>
      </div>

      <div class="card">
        <div style="font-weight:950; margin-bottom:10px;">Cara Pembuatan</div>
        <div class="small muted" style="margin-bottom:10px;">
          Produk: <b><?= htmlspecialchars((string)$selectedProduct['name']) ?></b>
        </div>
        <div style="line-height:1.6;">
          <?php
            $instr = (string)($recipe['instructions'] ?? '');
            echo $instr !== '' ? nl2br(htmlspecialchars($instr)) : '<span class="muted">Belum ada instruksi.</span>';
          ?>
        </div>
      </div>

    </div>
  <?php endif; ?>

</main>

<?php require __DIR__ . '/../publik/partials/dapur_layout_bottom.php'; ?>

<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';
require_role(['admin']);

$appName='MultiPOS';
$pageTitle='Resep / BOM';
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

// ====== AUTO CREATE TABLES (kalau belum ada) ======
try {
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS bom_recipes (
      id INT AUTO_INCREMENT PRIMARY KEY,
      store_id INT NOT NULL,
      product_id INT NOT NULL,
      instructions TEXT NULL,
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NULL,
      UNIQUE KEY uk_store_product (store_id, product_id),
      INDEX idx_store (store_id),
      INDEX idx_product (product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ");

  $pdo->exec("
    CREATE TABLE IF NOT EXISTS bom_recipe_items (
      id INT AUTO_INCREMENT PRIMARY KEY,
      recipe_id INT NOT NULL,
      ingredient_id INT NOT NULL,
      qty DECIMAL(18,6) NOT NULL DEFAULT 0,
      created_at DATETIME NOT NULL,
      UNIQUE KEY uk_recipe_ingredient (recipe_id, ingredient_id),
      INDEX idx_recipe (recipe_id),
      INDEX idx_ing (ingredient_id),
      CONSTRAINT fk_bom_recipe FOREIGN KEY (recipe_id) REFERENCES bom_recipes(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ");

  // kalau tabel sudah ada tapi belum ada kolom instructions, coba tambah
  try {
    $pdo->exec("ALTER TABLE bom_recipes ADD COLUMN instructions TEXT NULL");
  } catch (Throwable $e) {
    // ignore (biasanya karena kolom sudah ada)
  }
} catch (Throwable $e) {
  // kalau server tidak mengizinkan CREATE TABLE, query bawah akan menunjukkan errornya
}

// helper: ambil/buat recipe by product
function get_or_create_recipe_id(PDO $pdo, int $storeId, int $adminId, int $productId): int {
  $q = $pdo->prepare("SELECT id FROM bom_recipes WHERE store_id=? AND product_id=? LIMIT 1");
  $q->execute([$storeId, $productId]);
  $rid = (int)$q->fetchColumn();
  if ($rid > 0) return $rid;

  $pdo->prepare("
    INSERT INTO bom_recipes (store_id, product_id, instructions, is_active, created_at)
    VALUES (?,?,NULL,1,NOW())
  ")->execute([$storeId, $productId]);

  return (int)$pdo->lastInsertId();
}

// ===== SELECTED PRODUCT =====
$productId = (int)($_GET['product_id'] ?? 0);

// ===== POST ACTIONS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $act = (string)($_POST['action'] ?? '');
  $productIdPost = (int)($_POST['product_id'] ?? 0);

  // validasi product
  if ($productIdPost > 0) {
    $pQ = $pdo->prepare("SELECT id,is_active FROM products WHERE id=? AND store_id=? LIMIT 1");
    $pQ->execute([$productIdPost, $storeId]);
    $p = $pQ->fetch(PDO::FETCH_ASSOC);
    if (!$p || (int)$p['is_active'] !== 1) {
      header('Location: admin_persediaan_resep.php?err=' . urlencode('Produk tidak valid / nonaktif.'));
      exit;
    }
  }

  // 1) tambah bahan (qty akan DITAMBAH jika bahan sama sudah ada)
  if ($act === 'item_add') {
    $ingredientId = (int)($_POST['ingredient_id'] ?? 0);
    $qty = clamp_dec($_POST['qty'] ?? 0, 0.000001);

    if ($productIdPost <= 0) {
      header('Location: admin_persediaan_resep.php?err=' . urlencode('Pilih produk dulu.'));
      exit;
    }
    if ($ingredientId <= 0) {
      header('Location: admin_persediaan_resep.php?product_id=' . $productIdPost . '&err=' . urlencode('Pilih bahan dulu.'));
      exit;
    }
    if ($qty <= 0) {
      header('Location: admin_persediaan_resep.php?product_id=' . $productIdPost . '&err=' . urlencode('Qty harus > 0.'));
      exit;
    }

    // validasi ingredient
    $iQ = $pdo->prepare("SELECT id,is_active FROM ingredients WHERE id=? AND store_id=? LIMIT 1");
    $iQ->execute([$ingredientId, $storeId]);
    $ing = $iQ->fetch(PDO::FETCH_ASSOC);
    if (!$ing || (int)$ing['is_active'] !== 1) {
      header('Location: admin_persediaan_resep.php?product_id=' . $productIdPost . '&err=' . urlencode('Bahan tidak valid / nonaktif.'));
      exit;
    }

    try {
      $pdo->beginTransaction();

      $rid = get_or_create_recipe_id($pdo, $storeId, $adminId, $productIdPost);

      // kalau sudah ada, qty = qty lama + qty baru
      $pdo->prepare("
        INSERT INTO bom_recipe_items (recipe_id, ingredient_id, qty, created_at)
        VALUES (?,?,?,NOW())
        ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)
      ")->execute([$rid, $ingredientId, $qty]);

      $pdo->prepare("UPDATE bom_recipes SET updated_at=NOW() WHERE id=? AND store_id=?")
          ->execute([$rid, $storeId]);

      $pdo->commit();
      header('Location: admin_persediaan_resep.php?product_id=' . $productIdPost . '&ok=' . urlencode('Bahan tersimpan.'));
      exit;

    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      header('Location: admin_persediaan_resep.php?product_id=' . $productIdPost . '&err=' . urlencode('Gagal simpan bahan.'));
      exit;
    }
  }

  // 2) hapus item bahan
  if ($act === 'item_delete') {
    $itemId = (int)($_POST['item_id'] ?? 0);
    if ($productIdPost <= 0) {
      header('Location: admin_persediaan_resep.php?err=' . urlencode('Pilih produk dulu.'));
      exit;
    }

    try {
      $rid = get_or_create_recipe_id($pdo, $storeId, $adminId, $productIdPost);

      $pdo->prepare("DELETE FROM bom_recipe_items WHERE id=? AND recipe_id=?")
          ->execute([$itemId, $rid]);

      $pdo->prepare("UPDATE bom_recipes SET updated_at=NOW() WHERE id=? AND store_id=?")
          ->execute([$rid, $storeId]);

      header('Location: admin_persediaan_resep.php?product_id=' . $productIdPost . '&ok=' . urlencode('Item dihapus.'));
      exit;

    } catch (Throwable $e) {
      header('Location: admin_persediaan_resep.php?product_id=' . $productIdPost . '&err=' . urlencode('Gagal hapus item.'));
      exit;
    }
  }

  // 3) simpan cara masak / catatan
  if ($act === 'save_instructions') {
    if ($productIdPost <= 0) {
      header('Location: admin_persediaan_resep.php?err=' . urlencode('Pilih produk dulu.'));
      exit;
    }

    $instructions = (string)($_POST['instructions'] ?? '');
    $instructions = trim($instructions);

    // batasi panjang biar aman
    if (strlen($instructions) > 5000) $instructions = substr($instructions, 0, 5000);

    try {
      $rid = get_or_create_recipe_id($pdo, $storeId, $adminId, $productIdPost);

      $pdo->prepare("UPDATE bom_recipes SET instructions=?, updated_at=NOW() WHERE id=? AND store_id=?")
          ->execute([$instructions, $rid, $storeId]);

      header('Location: admin_persediaan_resep.php?product_id=' . $productIdPost . '&ok=' . urlencode('Cara masak tersimpan.'));
      exit;

    } catch (Throwable $e) {
      header('Location: admin_persediaan_resep.php?product_id=' . $productIdPost . '&err=' . urlencode('Gagal simpan cara masak.'));
      exit;
    }
  }

  header('Location: admin_persediaan_resep.php' . ($productIdPost ? ('?product_id='.$productIdPost) : ''));
  exit;
}

// ===== GET DATA =====
$errGet = (string)($_GET['err'] ?? '');
$okGet  = (string)($_GET['ok'] ?? '');
if ($errGet !== '') $error = $errGet;
if ($okGet !== '')  $ok = $okGet;

// list produk & bahan
$productsQ = $pdo->prepare("SELECT id,name,sku FROM products WHERE store_id=? AND is_active=1 ORDER BY name ASC");
$productsQ->execute([$storeId]);
$products = $productsQ->fetchAll(PDO::FETCH_ASSOC);

$ingQ = $pdo->prepare("SELECT id,name,unit FROM ingredients WHERE store_id=? AND is_active=1 ORDER BY name ASC");
$ingQ->execute([$storeId]);
$ingredients = $ingQ->fetchAll(PDO::FETCH_ASSOC);

// data resep utk produk terpilih
$selectedProduct = null;
$recipe = null;
$items = [];

if ($productId > 0) {
  $p1 = $pdo->prepare("SELECT id,name,sku,is_active FROM products WHERE id=? AND store_id=? LIMIT 1");
  $p1->execute([$productId, $storeId]);
  $selectedProduct = $p1->fetch(PDO::FETCH_ASSOC);

  if ($selectedProduct && (int)$selectedProduct['is_active'] === 1) {
    // recipe header
    $rq = $pdo->prepare("SELECT * FROM bom_recipes WHERE store_id=? AND product_id=? LIMIT 1");
    $rq->execute([$storeId, $productId]);
    $recipe = $rq->fetch(PDO::FETCH_ASSOC);

    // items
    if ($recipe) {
      $iq = $pdo->prepare("
        SELECT bi.id, bi.qty,
               i.name AS ingredient_name, i.unit
        FROM bom_recipe_items bi
        JOIN ingredients i ON i.id=bi.ingredient_id
        WHERE bi.recipe_id=?
        ORDER BY i.name ASC
      ");
      $iq->execute([(int)$recipe['id']]);
      $items = $iq->fetchAll(PDO::FETCH_ASSOC);
    }
  }
}

require __DIR__ . '/../publik/partials/admin_layout_top.php';
?>

<style>
  .wrap{max-width:1200px;}
  .muted{color:#64748b}
  .top-row{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
  .btn-link{
    display:inline-flex;align-items:center;gap:8px;
    padding:8px 12px;border-radius:999px;border:1px solid #e2e8f0;
    text-decoration:none;color:#0f172a;background:#fff;font-size:13px
  }
  .btn-link.primary{background:#0f172a;color:#fff;border-color:#0f172a}
  .panel{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:14px}
  .grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
  @media (max-width: 980px){ .grid2{grid-template-columns:1fr} }

  label{font-size:13px;color:#334155}
  input,select,textarea{padding:10px 12px;border-radius:12px;border:1px solid #e2e8f0;width:100%}
  textarea{min-height:160px;resize:vertical}

  .btn{
    padding:10px 14px;border-radius:12px;border:1px solid #e2e8f0;
    background:#2563eb;color:#fff;cursor:pointer
  }
  .btn:active{transform:translateY(1px)}
  .btn-outline{
    padding:8px 12px;border-radius:999px;border:1px solid #fecaca;
    background:#fff;color:#ef4444;cursor:pointer
  }

  table{width:100%;border-collapse:collapse}
  th,td{border-bottom:1px solid #f1f5f9;padding:10px 8px;text-align:left;font-size:13px}
  th{color:#64748b;font-weight:800}
</style>

<div class="wrap">
  <div class="top-row" style="margin-bottom:10px;">
    <div>
      <h1 style="margin:0 0 4px;">Resep</h1>
      <div class="muted" style="font-size:13px;">
        Pilih produk lalu tambahkan bahan dari daftar Bahan. Anda juga bisa menulis cara masak sebagai catatan untuk tim dapur.
      </div>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <a class="btn-link" href="admin_persediaan.php">← Persediaan</a>
    </div>
  </div>

  <?php if($error):?><div style="color:#ef4444;margin:10px 0;"><?=htmlspecialchars($error)?></div><?php endif;?>
  <?php if($ok):?><div style="color:#16a34a;margin:10px 0;"><?=htmlspecialchars($ok)?></div><?php endif;?>

  <!-- PILIH PRODUK -->
  <div class="panel" style="margin-bottom:14px;">
    <label style="display:block;margin-bottom:8px;">Pilih Produk (sumber: products)</label>
    <form method="get">
      <select name="product_id" onchange="this.form.submit()">
        <option value="0">-- pilih produk --</option>
        <?php foreach($products as $p): ?>
          <?php
            $pid = (int)$p['id'];
            $sku = (string)($p['sku'] ?? '');
          ?>
          <option value="<?= $pid ?>" <?= ($productId===$pid?'selected':'') ?>>
            <?= htmlspecialchars((string)$p['name']) ?><?= $sku!=='' ? (' ['.htmlspecialchars($sku).']') : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <div class="grid2">

    <!-- TAMBAH BAHAN -->
    <div class="panel">
      <h3 style="margin:0 0 10px;">Tambah Bahan ke Resep</h3>

      <form method="post">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="item_add">
        <input type="hidden" name="product_id" value="<?= (int)$productId ?>">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div>
            <label>Bahan</label>
            <select name="ingredient_id" required <?= $productId>0?'':'disabled' ?>>
              <option value="0">-- pilih bahan --</option>
              <?php foreach($ingredients as $i): ?>
                <option value="<?= (int)$i['id'] ?>">
                  <?= htmlspecialchars((string)$i['name']) ?> (base: <?= htmlspecialchars((string)$i['unit']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label>Qty per porsi (base unit)</label>
            <input type="number" name="qty" step="0.000001" min="0.000001" placeholder="contoh: 100" <?= $productId>0?'':'disabled' ?>>
          </div>
        </div>

        <div style="margin-top:12px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
          <button class="btn" type="submit" <?= $productId>0?'':'disabled' ?>>Simpan</button>
          <div class="muted" style="font-size:12px;">
            Ubah qty = tambah ulang bahan yang sama.
          </div>
        </div>
      </form>
    </div>

    <!-- KOMPOSISI -->
    <div class="panel">
      <h3 style="margin:0 0 10px;">Komposisi Resep</h3>

      <table>
        <tr>
          <th>Bahan</th>
          <th style="width:160px;">Qty / Porsi</th>
          <th style="width:110px;">Aksi</th>
        </tr>

        <?php if(!$productId): ?>
          <tr><td colspan="3" class="muted">Pilih produk dulu.</td></tr>
        <?php elseif(!$items): ?>
          <tr><td colspan="3" class="muted">Belum ada bahan.</td></tr>
        <?php else: ?>
          <?php foreach($items as $it): ?>
            <?php
              $qtyText = rtrim(rtrim(number_format((float)$it['qty'], 6, '.', ''), '0'), '.');
              $unit = (string)($it['unit'] ?? '');
            ?>
            <tr>
              <td><?= htmlspecialchars((string)$it['ingredient_name']) ?></td>
              <td><?= htmlspecialchars($qtyText) ?> <?= htmlspecialchars($unit) ?></td>
              <td>
                <form method="post" style="margin:0;">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                  <input type="hidden" name="action" value="item_delete">
                  <input type="hidden" name="product_id" value="<?= (int)$productId ?>">
                  <input type="hidden" name="item_id" value="<?= (int)$it['id'] ?>">
                  <button class="btn-outline" type="submit" onclick="return confirm('Hapus bahan ini dari resep?')">Hapus</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </table>
    </div>

  </div>

  <!-- CARA MASAK -->
  <div class="panel" style="margin-top:14px;">
    <h3 style="margin:0 0 6px;">Cara Masak / Catatan Resep</h3>
    <div class="muted" style="font-size:12px;margin-bottom:10px;">
      Instruksi / cara masak untuk produk ini (akan dibaca tim dapur)
    </div>

    <form method="post">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_instructions">
      <input type="hidden" name="product_id" value="<?= (int)$productId ?>">

      <textarea name="instructions" placeholder="Tulis langkah-langkah..." <?= $productId>0?'':'disabled' ?>><?= htmlspecialchars((string)($recipe['instructions'] ?? '')) ?></textarea>

      <div style="margin-top:12px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
        <button class="btn" type="submit" <?= $productId>0?'':'disabled' ?>>Simpan Cara Masak</button>
        <div class="muted" style="font-size:12px;">Catatan ini hanya tersimpan per produk (tidak muncul di kasir).</div>
      </div>
    </form>
  </div>

</div>

<?php require __DIR__ . '/../publik/partials/admin_layout_bottom.php'; ?>

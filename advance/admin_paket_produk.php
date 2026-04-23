<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';
require_role(['admin']);

$appName='MultiPOS';
$pageTitle='Paket Produk';
$activeMenu='menu_kategori';
$adminId=(int)auth_user()['id'];

$error=''; $ok='';

// ambil toko admin
$st = $pdo->prepare("SELECT id,name FROM stores WHERE owner_admin_id=? AND is_active=1 LIMIT 1");
$st->execute([$adminId]);
$store = $st->fetch(PDO::FETCH_ASSOC);
if (!$store) { http_response_code(400); exit('Admin belum terhubung ke toko.'); }
$storeId = (int)$store['id'];
$storeName = (string)$store['name'];

/**
 * ====== CREATE TABLES (kalau belum ada) ======
 */
try {
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS product_packages (
      id INT AUTO_INCREMENT PRIMARY KEY,
      store_id INT NOT NULL,
      name VARCHAR(150) NOT NULL,
      price DECIMAL(12,2) NOT NULL DEFAULT 0,
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NULL,
      INDEX idx_store (store_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ");

  $pdo->exec("
    CREATE TABLE IF NOT EXISTS product_package_items (
      id INT AUTO_INCREMENT PRIMARY KEY,
      package_id INT NOT NULL,
      product_id INT NOT NULL,
      qty INT NOT NULL DEFAULT 1,
      created_at DATETIME NOT NULL,
      UNIQUE KEY uk_pkg_product (package_id, product_id),
      INDEX idx_pkg (package_id),
      INDEX idx_product (product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ");
} catch (Throwable $e) {}

/**
 * ===== Helpers =====
 */
function clamp_int($v, int $min=1, int $max=999999): int {
  if (!is_numeric($v)) return $min;
  $n = (int)$v;
  if ($n < $min) $n = $min;
  if ($n > $max) $n = $max;
  return $n;
}
function clamp_price($v, float $min=0.0, float $max=999999999.0): float {
  if (!is_numeric($v)) return 0.0;
  $f = (float)$v;
  if ($f < $min) $f = $min;
  if ($f > $max) $f = $max;
  return $f;
}
function pkg_of_store(PDO $pdo, int $pkgId, int $storeId): ?array {
  $q = $pdo->prepare("SELECT * FROM product_packages WHERE id=? AND store_id=? LIMIT 1");
  $q->execute([$pkgId,$storeId]);
  $r = $q->fetch(PDO::FETCH_ASSOC);
  return $r ?: null;
}

/**
 * ===== Handle POST =====
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $act = (string)($_POST['action'] ?? '');

  if ($act === 'pkg_create') {
    $name = trim((string)($_POST['name'] ?? ''));
    $price = clamp_price($_POST['price'] ?? 0);

    if ($name === '') {
      header('Location: admin_paket_produk.php?err=' . urlencode('Nama paket wajib diisi.'));
      exit;
    }
    if (strlen($name) > 150) $name = substr($name, 0, 150);

    try {
      $pdo->prepare("
        INSERT INTO product_packages (store_id,name,price,is_active,created_at,updated_at)
        VALUES (?,?,?,?,NOW(),NOW())
      ")->execute([$storeId,$name,$price,1]);

      $newId = (int)$pdo->lastInsertId();
      header('Location: admin_paket_produk.php?pkg=' . $newId . '&ok=' . urlencode('Paket dibuat.'));
      exit;
    } catch (Throwable $e) {
      header('Location: admin_paket_produk.php?err=' . urlencode($e->getMessage() ?: 'Gagal membuat paket.'));
      exit;
    }
  }

  if ($act === 'pkg_update') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));
    $price = clamp_price($_POST['price'] ?? 0);

    try {
      $row = pkg_of_store($pdo, $id, $storeId);
      if (!$row) throw new RuntimeException('Paket tidak valid.');

      if ($name === '') throw new RuntimeException('Nama paket wajib diisi.');
      if (strlen($name) > 150) $name = substr($name, 0, 150);

      $pdo->prepare("
        UPDATE product_packages
        SET name=?, price=?, updated_at=NOW()
        WHERE id=? AND store_id=?
        LIMIT 1
      ")->execute([$name,$price,$id,$storeId]);

      header('Location: admin_paket_produk.php?pkg=' . $id . '&ok=' . urlencode('Paket disimpan.'));
      exit;
    } catch (Throwable $e) {
      header('Location: admin_paket_produk.php?pkg=' . $id . '&err=' . urlencode($e->getMessage() ?: 'Gagal simpan paket.'));
      exit;
    }
  }

  if ($act === 'pkg_delete_hard') {
    $id = (int)($_POST['id'] ?? 0);
    $confirm = trim((string)($_POST['confirm_delete'] ?? ''));

    try {
      $row = pkg_of_store($pdo, $id, $storeId);
      if (!$row) throw new RuntimeException('Paket tidak valid.');
      if (strtoupper($confirm) !== 'DELETE') throw new RuntimeException('Konfirmasi salah. Ketik DELETE untuk hapus permanen.');

      $pdo->beginTransaction();
      $pdo->prepare("DELETE FROM product_package_items WHERE package_id=?")->execute([$id]);
      $pdo->prepare("DELETE FROM product_packages WHERE id=? AND store_id=? LIMIT 1")->execute([$id,$storeId]);
      $pdo->commit();

      header('Location: admin_paket_produk.php?ok=' . urlencode('Paket dihapus permanen.'));
      exit;
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      header('Location: admin_paket_produk.php?pkg=' . $id . '&err=' . urlencode($e->getMessage() ?: 'Gagal hapus paket.'));
      exit;
    }
  }

  if ($act === 'item_add') {
    $pkgId = (int)($_POST['package_id'] ?? 0);
    $productId = (int)($_POST['product_id'] ?? 0);
    $qty = clamp_int($_POST['qty'] ?? 1, 1);

    try {
      $pkg = pkg_of_store($pdo, $pkgId, $storeId);
      if (!$pkg) throw new RuntimeException('Paket tidak valid.');

      if ($productId <= 0) throw new RuntimeException('Pilih produk.');
      $p = $pdo->prepare("SELECT id,name FROM products WHERE id=? AND store_id=? AND is_active=1 LIMIT 1");
      $p->execute([$productId,$storeId]);
      $prod = $p->fetch(PDO::FETCH_ASSOC);
      if (!$prod) throw new RuntimeException('Produk tidak valid.');

      $pdo->prepare("
        INSERT INTO product_package_items (package_id, product_id, qty, created_at)
        VALUES (?,?,?,NOW())
        ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)
      ")->execute([$pkgId,$productId,$qty]);

      header('Location: admin_paket_produk.php?pkg=' . $pkgId . '&ok=' . urlencode('Item ditambahkan.'));
      exit;
    } catch (Throwable $e) {
      header('Location: admin_paket_produk.php?pkg=' . $pkgId . '&err=' . urlencode($e->getMessage() ?: 'Gagal tambah item.'));
      exit;
    }
  }

  if ($act === 'item_qty_update') {
    $pkgId = (int)($_POST['package_id'] ?? 0);
    $itemId = (int)($_POST['item_id'] ?? 0);
    $qty = clamp_int($_POST['qty'] ?? 1, 1);

    try {
      $pkg = pkg_of_store($pdo, $pkgId, $storeId);
      if (!$pkg) throw new RuntimeException('Paket tidak valid.');

      $pdo->prepare("UPDATE product_package_items SET qty=? WHERE id=? AND package_id=? LIMIT 1")
          ->execute([$qty,$itemId,$pkgId]);

      header('Location: admin_paket_produk.php?pkg=' . $pkgId . '&ok=' . urlencode('Qty diperbarui.'));
      exit;
    } catch (Throwable $e) {
      header('Location: admin_paket_produk.php?pkg=' . $pkgId . '&err=' . urlencode($e->getMessage() ?: 'Gagal update qty.'));
      exit;
    }
  }

  if ($act === 'item_remove') {
    $pkgId = (int)($_POST['package_id'] ?? 0);
    $itemId = (int)($_POST['item_id'] ?? 0);

    try {
      $pkg = pkg_of_store($pdo, $pkgId, $storeId);
      if (!$pkg) throw new RuntimeException('Paket tidak valid.');

      $pdo->prepare("DELETE FROM product_package_items WHERE id=? AND package_id=? LIMIT 1")
          ->execute([$itemId,$pkgId]);

      header('Location: admin_paket_produk.php?pkg=' . $pkgId . '&ok=' . urlencode('Item dihapus.'));
      exit;
    } catch (Throwable $e) {
      header('Location: admin_paket_produk.php?pkg=' . $pkgId . '&err=' . urlencode($e->getMessage() ?: 'Gagal hapus item.'));
      exit;
    }
  }

  header('Location: admin_paket_produk.php');
  exit;
}

/**
 * ===== Data =====
 */
$error = (string)($_GET['err'] ?? '');
$ok    = (string)($_GET['ok'] ?? '');

$selectedPkgId = (int)($_GET['pkg'] ?? 0);
$selectedPkg = $selectedPkgId ? pkg_of_store($pdo, $selectedPkgId, $storeId) : null;

$pkgsQ = $pdo->prepare("
  SELECT id,name,price,created_at
  FROM product_packages
  WHERE store_id=? AND is_active=1
  ORDER BY id DESC
  LIMIT 300
");
$pkgsQ->execute([$storeId]);
$packages = $pkgsQ->fetchAll(PDO::FETCH_ASSOC);

$prodQ = $pdo->prepare("SELECT id,name,sku,price FROM products WHERE store_id=? AND is_active=1 ORDER BY name ASC");
$prodQ->execute([$storeId]);
$products = $prodQ->fetchAll(PDO::FETCH_ASSOC);

$items = [];
if ($selectedPkg) {
  $itQ = $pdo->prepare("
    SELECT i.id, i.qty, p.id AS product_id, p.name AS product_name, p.sku
    FROM product_package_items i
    JOIN products p ON p.id=i.product_id
    WHERE i.package_id=?
    ORDER BY p.name ASC
  ");
  $itQ->execute([$selectedPkgId]);
  $items = $itQ->fetchAll(PDO::FETCH_ASSOC);
}

require __DIR__ . '/../publik/partials/admin_layout_top.php';
?>

<style>
  .pkg-page{max-width:1200px;}
  .pkg-page .muted{color:#64748b}

  .pkg-page .page-head{
    background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:14px;
    display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px;
  }
  .pkg-page .page-head h1{margin:0;font-size:28px;color:#0f172a}
  .pkg-page .subtitle{margin:6px 0 0;color:#64748b}

  .pkg-page .grid{display:grid;grid-template-columns:380px 1fr;gap:14px}
  @media (max-width: 980px){ .pkg-page .grid{grid-template-columns:1fr} }

  .pkg-page .card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:16px;}
  .pkg-page label{font-size:13px;color:#334155;display:block;margin:0 0 6px}
  .pkg-page input,.pkg-page select{padding:11px 12px;border-radius:12px;border:1px solid #e2e8f0;width:100%}

  .pkg-page .btn{
    padding:10px 14px;border-radius:12px;border:1px solid #0b1220;background:#0b1220;
    color:#fff;font-weight:800;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:8px;
  }
  .pkg-page .btn:active{transform:translateY(1px)}
  .pkg-page .btn-outline{border:1px solid #e2e8f0;background:#fff;color:#0f172a}
  .pkg-page .btn-outline:hover{background:#f8fafc}
  .pkg-page .btn-danger{border:1px solid #fecaca;background:#fff;color:#ef4444;font-weight:900}
  .pkg-page .btn-danger:hover{background:#fff1f2}
  .pkg-page .btn-small{padding:8px 12px;border-radius:12px;font-size:13px}

  .pkg-page .msg-err{color:#ef4444;margin:10px 0}
  .pkg-page .msg-ok{color:#16a34a;margin:10px 0}

  .pkg-page .row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
  @media (max-width: 640px){ .pkg-page .row{grid-template-columns:1fr} }

  .pkg-page .hr{border:none;border-top:1px solid #f1f5f9;margin:14px 0}

  .pkg-page .pkglist{display:flex;flex-direction:column;gap:8px;margin-top:10px}
  .pkg-page .pkgitem{
    border:1px solid #e2e8f0;border-radius:14px;padding:10px 12px;
    display:flex;justify-content:space-between;align-items:center;gap:10px;
  }
  .pkg-page .pkgitem.active{border-color:#0b1220; box-shadow:0 0 0 2px rgba(11,18,32,.08) inset;}
  .pkg-page .pkgname{font-weight:900}
  .pkg-page .pkgmeta{font-size:12px;color:#64748b;margin-top:2px}
  .pkg-page .money{white-space:nowrap}

  .pkg-page .pill{display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;background:#eef2ff;color:#3730a3;font-weight:900;border:1px solid #c7d2fe;white-space:nowrap}

  .pkg-page table{width:100%;border-collapse:collapse;margin-top:10px}
  .pkg-page th,.pkg-page td{border-bottom:1px solid #f1f5f9;padding:10px 8px;font-size:13px;vertical-align:top}
  .pkg-page th{background:#f8fafc;color:#475569;font-weight:900;font-size:11px;letter-spacing:.06em}

  .pkg-page .qtyform{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
  .pkg-page .qtyform input{width:90px}

  .pkg-page .table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}
  @media (max-width: 640px){
    .pkg-page .table-wrap{overflow:visible}
    .pkg-page table.resp{border-collapse:separate;border-spacing:0 10px}
    .pkg-page table.resp thead{display:none}
    .pkg-page table.resp tbody tr{display:block;border:1px solid #e2e8f0;border-radius:14px;padding:10px;background:#fff;}
    .pkg-page table.resp tbody td{
      display:flex;justify-content:space-between;gap:10px;align-items:flex-start;
      border-bottom:1px dashed #f1f5f9;padding:8px 4px;
    }
    .pkg-page table.resp tbody td:last-child{border-bottom:none}
    .pkg-page table.resp tbody td::before{content: attr(data-label);font-weight:800;color:#334155;min-width:88px;flex:0 0 88px;}
  }

  /* tombol simpan & hapus dipisah jelas */
  .pkg-page .actions-bar{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:10px}
  .pkg-page .delete-box{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
</style>

<div class="pkg-page">
  <div class="page-head">
    <div>
      <h1>Paket Produk</h1>
      <div class="subtitle"></b> • Buat paket & atur isi paket dengan cepat.</div>
    </div>
    <div>
      <a class="btn btn-outline btn-small" href="admin_menu_kategori.php">Kembali</a>
    </div>
  </div>

  <?php if($error):?><div class="msg-err"><?=htmlspecialchars($error)?></div><?php endif;?>
  <?php if($ok):?><div class="msg-ok"><?=htmlspecialchars($ok)?></div><?php endif;?>

  <div class="grid">
    <!-- LEFT -->
    <div class="card">
      <div style="font-weight:900;font-size:16px;margin-bottom:10px;">Buat Paket (Cepat)</div>
      <form method="post" class="row">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="pkg_create">

        <div>
          <label>Nama Paket</label>
          <input name="name" placeholder="Contoh: Paket Hemat Ayam" required>
        </div>
        <div>
          <label>Harga Paket</label>
          <input type="number" step="0.01" min="0" name="price" value="0">
        </div>

        <div style="grid-column:1/-1;display:flex;gap:10px;flex-wrap:wrap;">
          <button class="btn" type="submit">Buat Paket</button>
          <a class="btn btn-outline" href="admin_paket_produk.php">Reset</a>
        </div>
      </form>

      <hr class="hr">

      <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
        <div style="font-weight:900;font-size:16px;">Daftar Paket</div>
        <div class="muted" style="font-size:13px;"><?= count($packages) ?> paket</div>
      </div>

      <?php if(!$packages): ?>
        <div class="muted" style="margin-top:10px;">Belum ada paket.</div>
      <?php else: ?>
        <div class="pkglist">
          <?php foreach($packages as $p): ?>
            <?php $isSel = $selectedPkg && ((int)$selectedPkg['id'] === (int)$p['id']); ?>
            <div class="pkgitem <?= $isSel ? 'active' : '' ?>">
              <div style="min-width:0;">
                <div class="pkgname" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                  <?= htmlspecialchars((string)$p['name']) ?>
                </div>
                <div class="pkgmeta"><span class="money">Rp <?= number_format((float)$p['price'], 0, ',', '.') ?></span></div>
              </div>
              <div>
                <a class="btn btn-outline btn-small" href="admin_paket_produk.php?pkg=<?= (int)$p['id'] ?>">
                  <?= $isSel ? 'Dipilih' : 'Pilih' ?>
                </a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- RIGHT -->
    <div class="card">
      <?php if(!$selectedPkg): ?>
        <div style="font-weight:900;font-size:16px;margin-bottom:6px;">Kelola Paket</div>
        <div class="muted">Pilih paket untuk mengatur harga dan isi paket.</div>
      <?php else: ?>
        <div style="font-weight:900;font-size:16px;">Kelola Paket</div>
        <div class="muted" style="margin-top:6px;">
          Paket: <b><?= htmlspecialchars((string)$selectedPkg['name']) ?></b>
          • <span class="pill money">Rp <?= number_format((float)$selectedPkg['price'],0,',','.') ?></span>
        </div>

        <hr class="hr">

        <!-- FORM SIMPAN (HANYA UNTUK SIMPAN) -->
        <form method="post" class="row">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <input type="hidden" name="action" value="pkg_update">
          <input type="hidden" name="id" value="<?= (int)$selectedPkg['id'] ?>">

          <div>
            <label>Nama Paket</label>
            <input name="name" required value="<?= htmlspecialchars((string)$selectedPkg['name']) ?>">
          </div>
          <div>
            <label>Harga Paket</label>
            <input type="number" step="0.01" min="0" name="price" value="<?= htmlspecialchars((string)$selectedPkg['price']) ?>">
          </div>

          <div class="actions-bar" style="grid-column:1/-1;">
            <button class="btn" type="submit">Simpan</button>
          </div>
        </form>

        <!-- FORM HAPUS (TERPISAH, BUKAN NESTED) -->
        <form method="post" class="delete-box" style="margin-top:10px;"
          onsubmit="return confirm('Hapus paket ini PERMANEN? Isi paket juga ikut terhapus.');">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <input type="hidden" name="action" value="pkg_delete_hard">
          <input type="hidden" name="id" value="<?= (int)$selectedPkg['id'] ?>">
          <input type="text" name="confirm_delete" placeholder="ketik DELETE" required style="max-width:180px;">
          <button class="btn btn-danger" type="submit">Hapus Permanen</button>
        </form>

        <hr class="hr">

        <div style="font-weight:900;font-size:16px;margin-bottom:10px;">Isi Paket</div>

        <form method="post" class="row" style="align-items:end;">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <input type="hidden" name="action" value="item_add">
          <input type="hidden" name="package_id" value="<?= (int)$selectedPkg['id'] ?>">

          <div style="grid-column:1/-1;">
            <label>Produk</label>
            <select name="product_id" required>
              <option value="0">-- pilih produk --</option>
              <?php foreach($products as $pr): ?>
                <option value="<?= (int)$pr['id'] ?>">
                  <?= htmlspecialchars((string)$pr['name']) ?>
                  <?= !empty($pr['sku']) ? ' ['.htmlspecialchars((string)$pr['sku']).']' : '' ?>
                  (Rp <?= number_format((float)($pr['price'] ?? 0),0,',','.') ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label>Qty</label>
            <input type="number" name="qty" min="1" step="1" value="1">
          </div>

          <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button class="btn" type="submit" style="width:100%;">Tambah</button>
          </div>

          <div style="grid-column:1/-1;" class="muted">
            Kalau produk yang sama ditambah lagi, qty otomatis dijumlahkan.
          </div>
        </form>

        <div class="table-wrap">
          <table class="resp">
            <thead>
              <tr>
                <th>Produk</th>
                <th style="width:140px;">Qty</th>
                <th style="width:140px;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if(!$items): ?>
                <tr><td colspan="3" class="muted">Belum ada isi paket.</td></tr>
              <?php else: ?>
                <?php foreach($items as $it): ?>
                  <tr>
                    <td data-label="Produk">
                      <?= htmlspecialchars((string)$it['product_name']) ?>
                      <?= !empty($it['sku']) ? '<span class="muted">['.htmlspecialchars((string)$it['sku']).']</span>' : '' ?>
                    </td>

                    <td data-label="Qty">
                      <form method="post" class="qtyform">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="action" value="item_qty_update">
                        <input type="hidden" name="package_id" value="<?= (int)$selectedPkg['id'] ?>">
                        <input type="hidden" name="item_id" value="<?= (int)$it['id'] ?>">
                        <input type="number" name="qty" min="1" step="1" value="<?= (int)$it['qty'] ?>">
                        <button class="btn btn-outline btn-small" type="submit">Simpan</button>
                      </form>
                    </td>

                    <td data-label="Aksi">
                      <form method="post" style="display:inline;" onsubmit="return confirm('Hapus item ini dari paket?');">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="action" value="item_remove">
                        <input type="hidden" name="package_id" value="<?= (int)$selectedPkg['id'] ?>">
                        <input type="hidden" name="item_id" value="<?= (int)$it['id'] ?>">
                        <button class="btn btn-danger btn-small" type="submit">Hapus</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../publik/partials/admin_layout_bottom.php'; ?>

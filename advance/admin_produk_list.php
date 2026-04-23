<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';
require_role(['admin']);

$appName='MultiPOS';
$pageTitle='Daftar Barang';
$activeMenu='menu_kategori';
$adminId=(int)auth_user()['id'];

$st = $pdo->prepare("SELECT id,name FROM stores WHERE owner_admin_id=? AND is_active=1 LIMIT 1");
$st->execute([$adminId]);
$store = $st->fetch(PDO::FETCH_ASSOC);
if (!$store) { http_response_code(400); exit('Admin belum terhubung ke toko.'); }
$storeId=(int)$store['id'];
$storeName=(string)$store['name'];

$dbName = (string)$pdo->query("SELECT DATABASE()")->fetchColumn();

function table_exists(PDO $pdo, string $dbName, string $table): bool {
  $q = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=? LIMIT 1");
  $q->execute([$dbName, $table]);
  return (bool)$q->fetchColumn();
}
function columns_of(PDO $pdo, string $dbName, string $table): array {
  $q = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? ORDER BY ORDINAL_POSITION");
  $q->execute([$dbName, $table]);
  return array_map(fn($r)=> (string)$r['COLUMN_NAME'], $q->fetchAll(PDO::FETCH_ASSOC));
}
function has_col(array $cols, string $c): bool { return in_array($c, $cols, true); }

if (!table_exists($pdo, $dbName, 'products')) { http_response_code(400); exit("Tabel 'products' tidak ditemukan."); }

$prodCols = columns_of($pdo, $dbName, 'products');
$catCols  = table_exists($pdo, $dbName, 'categories') ? columns_of($pdo, $dbName, 'categories') : [];

$colSku   = has_col($prodCols,'sku') ? 'sku' : (has_col($prodCols,'code') ? 'code' : null);
$colName  = has_col($prodCols,'name') ? 'name' : (has_col($prodCols,'title') ? 'title' : 'name');
$colPrice = has_col($prodCols,'price') ? 'price' : (has_col($prodCols,'sell_price') ? 'sell_price' : null);
$colImg   = has_col($prodCols,'image') ? 'image' : (has_col($prodCols,'photo') ? 'photo' : null);
$colActive= has_col($prodCols,'is_active') ? 'is_active' : null;
$colStore = has_col($prodCols,'store_id') ? 'store_id' : null;
$colCat   = has_col($prodCols,'category_id') ? 'category_id' : (has_col($prodCols,'cat_id') ? 'cat_id' : null);

// categories for filter
$categories = [];
if (table_exists($pdo, $dbName, 'categories')) {
  $sql = "SELECT id,name FROM categories WHERE 1=1";
  $params = [];
  if (has_col($catCols,'store_id')) { $sql.=" AND store_id=?"; $params[]=$storeId; }
  if (has_col($catCols,'is_active')) { $sql.=" AND is_active=1"; }
  $sql .= " ORDER BY name ASC";
  $q = $pdo->prepare($sql);
  $q->execute($params);
  $categories = $q->fetchAll(PDO::FETCH_ASSOC);
}

// filter
$catId = (int)($_GET['cat'] ?? 0);
$qtxt = trim((string)($_GET['q'] ?? ''));
if (strlen($qtxt) > 80) $qtxt = substr($qtxt, 0, 80);

$select = ["p.id", "p.`$colName` AS name"];
if ($colSku) $select[] = "p.`$colSku` AS sku";
if ($colPrice) $select[] = "p.`$colPrice` AS price";
if ($colImg) $select[] = "p.`$colImg` AS image";
if ($colActive) $select[] = "p.`$colActive` AS is_active";
if ($colCat) $select[] = "p.`$colCat` AS category_id";

$join = "";
$catNameSelect = "'' AS category_name";
if ($colCat && table_exists($pdo, $dbName, 'categories')) {
  $join = " LEFT JOIN categories c ON c.id = p.`$colCat` ";
  $catNameSelect = "c.name AS category_name";
}

$sql = "SELECT " . implode(", ", $select) . ", $catNameSelect
        FROM products p
        $join
        WHERE 1=1";
$params = [];

if ($colStore) { $sql .= " AND p.store_id=?"; $params[]=$storeId; }
if ($colActive) { $sql .= " AND p.`$colActive`=1"; }
if ($colCat && $catId>0) { $sql .= " AND p.`$colCat`=?"; $params[]=$catId; }

if ($qtxt !== '') {
  $sql .= " AND (p.`$colName` LIKE ? ";
  $params[] = "%$qtxt%";
  if ($colSku) { $sql .= " OR p.`$colSku` LIKE ? "; $params[] = "%$qtxt%"; }
  $sql .= ")";
}

$sql .= " ORDER BY p.`$colName` ASC LIMIT 500";
$q = $pdo->prepare($sql);
$q->execute($params);
$products = $q->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/../publik/partials/admin_layout_top.php';
?>

<style>
  .wrap{max-width:1200px;}
  .muted{color:#64748b}
  .panel{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:16px;}
  .btnBar{display:flex;flex-direction:column;gap:10px;margin:12px 0 14px;}
  .bigBtn{
    width:100%;padding:14px 16px;border-radius:999px;border:0;
    background:#0b1220;color:#fff;font-weight:900;cursor:pointer;
  }
  .bigBtn:active{transform:translateY(1px)}
  .filters{display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin:12px 0;}
  select,input{
    padding:11px 12px;border-radius:999px;border:1px solid #e2e8f0;
    min-width:260px;
  }
  .searchBtn{min-width:160px;padding:12px 14px;border-radius:999px;border:0;background:#0b1220;color:#fff;font-weight:900;cursor:pointer}
  table{width:100%;border-collapse:collapse;margin-top:10px;}
  th,td{border-bottom:1px solid #f1f5f9;padding:10px 8px;text-align:left;font-size:13px;}
  th{background:#f8fafc;color:#475569;font-weight:900;letter-spacing:.06em;font-size:11px;}
  .pill{display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;background:#dcfce7;color:#166534;font-weight:900;}
  .actions a{margin-right:10px;font-size:12px;text-decoration:none}
  .actions a.del{color:#ef4444}
  .imgbox{width:42px;height:42px;border-radius:10px;border:1px solid #e2e8f0;background:#fff;display:flex;align-items:center;justify-content:center;overflow:hidden;}
  .imgbox img{width:100%;height:100%;object-fit:cover}
</style>

<div class="wrap">
  <h1 style="margin:0 0 6px;">Daftar Barang</h1>
  <div class="muted">Kelola menu / produk yang tampil di kasir. Bisa filter berdasarkan kategori dan cari nama/kode.</div>

  <div class="panel" style="margin-top:14px;">
    <div class="btnBar">
      <button class="bigBtn" type="button" onclick="alert('Nanti kita sambungkan ke form tambah barang (file terpisah).');">+ Tambah Barang</button>
      <button class="bigBtn" type="button" onclick="location.href='admin_kategori_list.php';">+ Tambah Kategori</button>
      <button class="bigBtn" type="button" onclick="location.href='admin_paket_produk.php';">Menu Paket Produk</button>
    </div>

    <form method="get" class="filters">
      <select name="cat">
        <option value="0">Semua Kategori</option>
        <?php foreach($categories as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= ((int)($_GET['cat'] ?? 0)===(int)$c['id']?'selected':'') ?>>
            <?= htmlspecialchars((string)$c['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <input type="text" name="q" placeholder="Cari nama / kode barang..." value="<?= htmlspecialchars((string)($_GET['q'] ?? '')) ?>">
      <button class="searchBtn" type="submit">Cari</button>
    </form>

    <table>
      <tr>
        <th style="width:80px;">KODE</th>
        <th>NAMA BARANG</th>
        <th style="width:160px;">KATEGORI</th>
        <th style="width:120px;">HARGA</th>
        <th style="width:90px;">GAMBAR</th>
        <th style="width:90px;">STATUS</th>
        <th style="width:160px;">AKSI</th>
      </tr>

      <?php if(!$products): ?>
        <tr><td colspan="7" class="muted">Belum ada data produk.</td></tr>
      <?php else: ?>
        <?php foreach($products as $p): ?>
          <tr>
            <td><?= htmlspecialchars((string)($p['sku'] ?? '—')) ?></td>
            <td><?= htmlspecialchars((string)($p['name'] ?? '')) ?></td>
            <td><?= htmlspecialchars((string)($p['category_name'] ?? '')) ?></td>
            <td><?php $price=(float)($p['price'] ?? 0); echo 'Rp '.number_format($price,0,',','.'); ?></td>
            <td>
              <div class="imgbox">
                <?php if(!empty($p['image'])): ?>
                  <img src="<?= htmlspecialchars((string)$p['image']) ?>" alt="">
                <?php else: ?>
                  <span class="muted" style="font-size:10px;">—</span>
                <?php endif; ?>
              </div>
            </td>
            <td><span class="pill">Aktif</span></td>
            <td class="actions">
              <a href="#" onclick="alert('Edit produk nanti kita buat file admin_produk_edit.php');return false;">Edit</a>
              <a class="del" href="#" onclick="alert('Hapus produk nanti kita buat file admin_produk_delete.php');return false;">Hapus</a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </table>
  </div>

  <div style="margin-top:12px;">
    <a class="muted" href="admin_menu_kategori.php" style="text-decoration:none;">← Kembali ke Menu & Kategori</a>
  </div>
</div>

<?php require __DIR__ . '/../publik/partials/admin_layout_bottom.php'; ?>

<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';
require_role(['admin']);

$appName='MultiPOS';
$pageTitle='Menu & Kategori';
$activeMenu='menu_kategori';
$adminId=(int)auth_user()['id'];

// ambil toko admin
$st = $pdo->prepare("SELECT id,name FROM stores WHERE owner_admin_id=? AND is_active=1 LIMIT 1");
$st->execute([$adminId]);
$store = $st->fetch(PDO::FETCH_ASSOC);
if (!$store) { http_response_code(400); exit('Admin belum terhubung ke toko.'); }
$storeName = (string)$store['name'];

require __DIR__ . '/../publik/partials/admin_layout_top.php';
?>

<style>
  .wrap{max-width:1100px;}
  .muted{color:#64748b}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:14px}
  @media (max-width: 920px){ .grid{grid-template-columns:1fr} }
  .card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:16px;}
  .btn{
    display:flex;align-items:center;justify-content:space-between;gap:12px;
    padding:16px 18px;border-radius:18px;border:0;
    background:#0b1220;color:#fff;text-decoration:none;
    font-weight:900;font-size:15px;
  }
  .btn small{display:block;font-weight:600;color:#cbd5e1;margin-top:3px;font-size:12px}
  .btn:active{transform:translateY(1px)}
</style>

<div class="wrap">
  <h1 style="margin:0 0 6px;">Menu & Kategori</h1>

  <div class="grid">
    <div class="card">
      <!-- SUB MENU: Produk lama -->
      <a class="btn" href="admin_products.php">
        <span>➕ Tambah Produk <small>Masuk ke menu Produk untuk menambah atau mengubah produk</small></span>
        <span>→</span>
      </a>
    </div>

    <div class="card">
      <!-- SUB MENU: Kategori lama -->
      <a class="btn" href="admin_categories.php">
        <span>🏷️ Kategori <small>Masuk ke menu Kategori untuk menambah kategori produk</small></span>
        <span>→</span>
      </a>
    </div>

    <div class="card" style="grid-column:1/-1;">
      <!-- SUB MENU: Paket -->
      <a class="btn" href="admin_paket_produk.php">
        <span>🎁 Paket Produk <small>Bundling / paket</small></span>
        <span>→</span>
      </a>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../publik/partials/admin_layout_bottom.php'; ?>

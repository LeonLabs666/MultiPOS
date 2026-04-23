<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';
require_role(['admin']);

$appName='MultiPOS';
$pageTitle='Tambah Produk';
$activeMenu='menu_kategori';

require __DIR__ . '/../publik/partials/admin_layout_top.php';
?>

<style>
  .panel{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:16px;max-width:1100px}
  .muted{color:#64748b}
</style>

<div class="panel">
  <h1 style="margin:0 0 6px;">Tambah Produk</h1>
  <p class="muted" style="margin:0;">Halaman ini siap. Nanti kita isi form tambah produk + upload gambar.</p>

  <div style="margin-top:12px;">
    <a class="muted" href="admin_menu_kategori.php" style="text-decoration:none;">← Kembali</a>
  </div>
</div>

<?php require __DIR__ . '/../publik/partials/admin_layout_bottom.php'; ?>

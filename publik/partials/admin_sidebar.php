<?php
declare(strict_types=1);
$activeMenu = $activeMenu ?? 'dashboard';
$appName = $appName ?? 'MultiPOS';
?>
<aside id="sidebar" class="sidebar">
  <div class="brand">
    <div class="logo">M</div>
    <div class="title"><?= htmlspecialchars($appName) ?></div>
  </div>

  <nav class="nav">
    <a class="<?= $activeMenu==='dashboard'?'active':'' ?>" href="admin_dashboard.php">
      <span class="icon">🏠</span><span class="label">Dashboard</span>
    </a>
    <a class="<?= $activeMenu==='kategori'?'active':'' ?>" href="admin_categories.php">
  <span class="icon">🏷️</span><span class="label">Kategori</span>
</a>
<a class="<?= $activeMenu==='produk'?'active':'' ?>" href="admin_products.php">
  <span class="icon">📦</span><span class="label">Produk</span>
</a>
<a class="<?= $activeMenu==='persediaan'?'active':'' ?>" href="admin_persediaan.php">
  <span class="icon">📦</span><span class="label">Persediaan</span>
</a>

<a class="<?= $activeMenu==='shift'?'active':'' ?>" href="admin_shift_report.php">
  <span class="icon">🧾</span>
  <span class="label">Laporan Shift Kasir</span>
</a>
<a class="<?= $activeMenu==='sales'?'active':'' ?>" href="admin_sales_report.php">
  <span class="icon">📊</span><span class="label">Laporan Penjualan</span>
</a>
<a class="<?= $activeMenu==='opname'?'active':'' ?>" href="admin_stock_opname.php">
  <span class="icon">🧮</span><span class="label">Stok Opname</span>
</a>


    <a class="<?= $activeMenu==='kasir'?'active':'' ?>" href="admin_create_kasir.php">
      <span class="icon">👤</span><span class="label">User / Kasir</span>
    </a>
    <a href="admin_pengaturan_toko.php" class="<?= $activeMenu==='pengaturan_toko'?'active':'' ?>">⚙️ Pengaturan Toko</a>

  </nav>

  <div class="sidebar-footer">
    <a class="signout" href="../logout.php">
      <span class="icon">↩️</span><span>Sign Out</span>
    </a>
  </div>
</aside>

<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$page_title  = 'Persediaan (Basic) • MultiPOS';
$page_h1     = 'Persediaan';
$active_menu = 'persediaan';

require __DIR__ . '/partials/layout_top.php';
?>

<div class="card">
  <div style="font-weight:1000;font-size:14px;">Menu Persediaan</div>
  <div style="color:#64748b;font-size:13px;margin-top:6px;line-height:1.5;">
    Kelola pergerakan stok dan lakukan stock opname.
  </div>

  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:14px;">
    <a class="card" href="admin_stok_inout.php" style="text-decoration:none;color:inherit;">
      <div style="font-weight:1000;">Stok Masuk/Keluar</div>
      <div style="color:#64748b;font-size:13px;margin-top:4px;">Catat stok masuk, keluar, atau penyesuaian.</div>
    </a>

    <a class="card" href="admin_riwayat_stok.php" style="text-decoration:none;color:inherit;">
      <div style="font-weight:1000;">Riwayat Stok</div>
      <div style="color:#64748b;font-size:13px;margin-top:4px;">Lihat histori pergerakan stok.</div>
    </a>

    <a class="card" href="admin_stock_opname.php" style="text-decoration:none;color:inherit;">
      <div style="font-weight:1000;">Stock Opname</div>
      <div style="color:#64748b;font-size:13px;margin-top:4px;">Hitung stok fisik dan sesuaikan stok sistem.</div>
    </a>
  </div>

  <div style="margin-top:12px;">
    <a class="btn" href="admin_basic_dashboard.php" style="text-decoration:none;">← Kembali</a>
  </div>
</div>

<?php require __DIR__ . '/partials/layout_bottom.php'; ?>

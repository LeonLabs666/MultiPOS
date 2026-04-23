<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';

require_role(['admin']);

$appName='MultiPOS';
$pageTitle='Laporan Penjualan';
$activeMenu='sales';

$adminId=(int)auth_user()['id'];

// store admin
$st=$pdo->prepare("SELECT id,name FROM stores WHERE owner_admin_id=? AND is_active=1 LIMIT 1");
$st->execute([$adminId]);
$store=$st->fetch();
if(!$store){ http_response_code(400); exit('Admin belum punya toko.'); }
$storeId=(int)$store['id']; $storeName=$store['name'];

// filter tanggal (fix empty)
$from=trim((string)($_GET['from']??''));
$to=trim((string)($_GET['to']??''));
if($from==='') $from=date('Y-m-01');
if($to==='') $to=date('Y-m-d');

$fromDT=$from.' 00:00:00';
$toDT=$to.' 23:59:59';

// summary omzet + trx count
$sum=$pdo->prepare("
  SELECT COALESCE(SUM(total),0) AS omzet, COUNT(*) AS trx
  FROM sales
  WHERE store_id=? AND created_at BETWEEN ? AND ?
");
$sum->execute([$storeId,$fromDT,$toDT]);
$srow=$sum->fetch();
$omzet=(int)($srow['omzet']??0);
$trx=(int)($srow['trx']??0);

// list transaksi
$list=$pdo->prepare("
  SELECT sa.id, sa.created_at, sa.invoice_no, sa.total, sa.payment_method,
         u.name AS kasir_name
  FROM sales sa
  JOIN users u ON u.id=sa.kasir_id
  WHERE sa.store_id=? AND sa.created_at BETWEEN ? AND ?
  ORDER BY sa.id DESC
  LIMIT 200
");
$list->execute([$storeId,$fromDT,$toDT]);
$rows=$list->fetchAll();

require __DIR__ . '/../publik/partials/admin_layout_top.php';
?>
<h1 style="margin:0 0 10px;">Laporan Penjualan</h1>

<form method="get" style="margin:0 0 12px;display:flex;gap:10px;align-items:end;">
  <div>
    <label>Dari</label><br>
    <input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
  </div>
  <div>
    <label>Sampai</label><br>
    <input type="date" name="to" value="<?= htmlspecialchars($to) ?>">
  </div>
  <button type="submit">Filter</button>
  <a href="admin_sales_report.php" style="margin-left:10px;">Reset</a>
</form>

<div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:12px;">
  <div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:12px;min-width:220px;">
    <div style="color:#64748b;font-size:12px;">OMZET PERIODE</div>
    <div style="font-size:20px;font-weight:800;margin-top:6px;">
      Rp <?= number_format($omzet,0,',','.') ?>
    </div>
    <div style="color:#94a3b8;font-size:12px;margin-top:4px;"><?= htmlspecialchars($from) ?> s/d <?= htmlspecialchars($to) ?></div>
  </div>

  <div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:12px;min-width:220px;">
    <div style="color:#64748b;font-size:12px;">JUMLAH TRANSAKSI</div>
    <div style="font-size:20px;font-weight:800;margin-top:6px;"><?= $trx ?></div>
    <div style="color:#94a3b8;font-size:12px;margin-top:4px;">Max tampil 200 data</div>
  </div>
</div>

<table width="100%" cellpadding="8" cellspacing="0"
  style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;border-collapse:collapse;">
  <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
    <th align="left">Waktu</th>
    <th align="left">Invoice</th>
    <th align="left">Kasir</th>
    <th align="left">Metode</th>
    <th align="right">Total</th>
    <th align="left">Aksi</th>
  </tr>

  <?php foreach($rows as $r): ?>
    <tr style="border-bottom:1px solid #eef2f7;">
      <td><?= htmlspecialchars($r['created_at']) ?></td>
      <td><?= htmlspecialchars($r['invoice_no'] ?? '-') ?></td>
      <td><?= htmlspecialchars($r['kasir_name'] ?? '-') ?></td>
      <td><?= htmlspecialchars(strtoupper($r['payment_method'] ?? '-')) ?></td>
      <td align="right">Rp <?= number_format((int)$r['total'],0,',','.') ?></td>
      <td>
        <a href="admin_sale_detail.php?id=<?= (int)$r['id'] ?>">Detail</a>
      </td>
    </tr>
  <?php endforeach; ?>

  <?php if(!$rows): ?>
    <tr><td colspan="6" style="padding:14px;color:#64748b;">Belum ada transaksi pada periode ini.</td></tr>
  <?php endif; ?>
</table>

<?php require __DIR__ . '/../publik/partials/admin_layout_bottom.php'; ?>

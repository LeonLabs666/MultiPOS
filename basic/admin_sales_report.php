<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

$page_title   = 'Laporan Penjualan • MultiPOS';
$page_h1      = 'Laporan Penjualan';
$active_menu  = 'sales';

$storeId   = (int)$storeId; // from _bootstrap
$storeName = (string)$store['name'];

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// filter tanggal (fix empty)
$from = trim((string)($_GET['from'] ?? ''));
$to   = trim((string)($_GET['to'] ?? ''));
if ($from === '') $from = date('Y-m-01');
if ($to === '')   $to   = date('Y-m-d');

$fromDT = $from . ' 00:00:00';
$toDT   = $to   . ' 23:59:59';

// summary omzet + trx count
$sum = $pdo->prepare("
  SELECT COALESCE(SUM(total),0) AS omzet, COUNT(*) AS trx
  FROM sales
  WHERE store_id=? AND created_at BETWEEN ? AND ?
");
$sum->execute([$storeId, $fromDT, $toDT]);
$srow  = $sum->fetch(PDO::FETCH_ASSOC) ?: [];
$omzet = (int)($srow['omzet'] ?? 0);
$trx   = (int)($srow['trx'] ?? 0);

// list transaksi
$list = $pdo->prepare("
  SELECT sa.id, sa.created_at, sa.invoice_no, sa.total, sa.payment_method,
         u.name AS kasir_name
  FROM sales sa
  JOIN users u ON u.id=sa.kasir_id
  WHERE sa.store_id=? AND sa.created_at BETWEEN ? AND ?
  ORDER BY sa.id DESC
  LIMIT 200
");
$list->execute([$storeId, $fromDT, $toDT]);
$rows = $list->fetchAll(PDO::FETCH_ASSOC);

// ✅ FIX: pakai partial mode basic yang benar
include __DIR__ . '/partials/layout_top.php';
?>


<form method="get" style="margin:0 0 12px;display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
  <div>
    <label>Dari</label><br>
    <input type="date" name="from" value="<?= h($from) ?>">
  </div>
  <div>
    <label>Sampai</label><br>
    <input type="date" name="to" value="<?= h($to) ?>">
  </div>
  <button type="submit">Filter</button>
  <a href="admin_sales_report.php" style="margin-left:10px;">Reset</a>
</form>

<div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:12px;">
  <div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:12px;min-width:220px;">
    <div style="color:#64748b;font-size:12px;">OMZET PERIODE</div>
    <div style="font-size:20px;font-weight:800;margin-top:6px;">
      Rp <?= number_format($omzet, 0, ',', '.') ?>
    </div>
    <div style="color:#94a3b8;font-size:12px;margin-top:4px;"><?= h($from) ?> s/d <?= h($to) ?></div>
  </div>

  <div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:12px;min-width:220px;">
    <div style="color:#64748b;font-size:12px;">JUMLAH TRANSAKSI</div>
    <div style="font-size:20px;font-weight:800;margin-top:6px;"><?= (int)$trx ?></div>
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

  <?php foreach ($rows as $r): ?>
    <tr style="border-bottom:1px solid #eef2f7;">
      <td><?= h($r['created_at'] ?? '') ?></td>
      <td><?= h($r['invoice_no'] ?? '-') ?></td>
      <td><?= h($r['kasir_name'] ?? '-') ?></td>
      <td><?= h(strtoupper((string)($r['payment_method'] ?? '-'))) ?></td>
      <td align="right">Rp <?= number_format((int)($r['total'] ?? 0), 0, ',', '.') ?></td>
      <td>
        <a href="admin_sale_detail.php?id=<?= (int)($r['id'] ?? 0) ?>">Detail</a>
      </td>
    </tr>
  <?php endforeach; ?>

  <?php if (!$rows): ?>
    <tr><td colspan="6" style="padding:14px;color:#64748b;">Belum ada transaksi pada periode ini.</td></tr>
  <?php endif; ?>
</table>

<?php include __DIR__ . '/partials/layout_bottom.php'; ?>

<?php
declare(strict_types=1);

require __DIR__ . '/kasir_bootstrap.php';

$saleId = (int)($_GET['id'] ?? 0);
if ($saleId <= 0) {
  header('Location: kasir_dashboard.php'); exit;
}

$h = $pdo->prepare("
  SELECT s.*, u.name AS kasir_name
  FROM sales s
  JOIN users u ON u.id = s.kasir_id
  WHERE s.id=? AND s.store_id=?
  LIMIT 1
");
$h->execute([$saleId, $storeId]);
$sale = $h->fetch(PDO::FETCH_ASSOC);
if (!$sale) {
  http_response_code(404);
  exit('Transaksi tidak ditemukan.');
}

$it = $pdo->prepare("
  SELECT name, price, qty, subtotal
  FROM sale_items
  WHERE sale_id=?
  ORDER BY id ASC
");
$it->execute([$saleId]);
$items = $it->fetchAll(PDO::FETCH_ASSOC);

$pageTitle  = 'Struk #' . (string)($sale['invoice_no'] ?? $saleId);
$activeMenu = 'kasir_pos';

include __DIR__ . '/partials/kasir_layout_top.php';
?>

<style>
  .card{background:#fff;border:1px solid rgba(15,23,42,.10);border-radius:16px;padding:14px;box-shadow:0 18px 50px rgba(15,23,42,.05);max-width:520px}
  table{width:100%;border-collapse:collapse}
  td{padding:6px 0;border-bottom:1px dashed rgba(15,23,42,.18);font-size:13px}
  .muted{color:#64748b;font-size:12px}
  .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 12px;border-radius:999px;border:1px solid transparent;font-weight:1000;cursor:pointer}
  .btn.primary{background:#2563eb;border-color:#2563eb;color:#fff}
  .btn.ghost{background:#fff;border-color:rgba(15,23,42,.12);color:#0f172a}
  @media print{
    .no-print{display:none !important}
    body{background:#fff}
  }
</style>

<div class="card">
  <div style="text-align:center;">
    <div style="font-weight:1000;font-size:16px;"><?= htmlspecialchars($storeName) ?></div>
    <div class="muted">Kasir Basic</div>
  </div>

  <div style="margin-top:10px;" class="muted">
    Invoice: <b><?= htmlspecialchars((string)($sale['invoice_no'] ?? '')) ?></b><br>
    Waktu: <?= htmlspecialchars((string)($sale['created_at'] ?? '')) ?><br>
    Kasir: <?= htmlspecialchars((string)($sale['kasir_name'] ?? '')) ?>
  </div>

  <div style="margin-top:10px;">
    <table>
      <?php foreach ($items as $r): ?>
        <tr>
          <td>
            <div style="font-weight:900;"><?= htmlspecialchars((string)$r['name']) ?></div>
            <div class="muted">
              <?= (int)$r['qty'] ?> × Rp <?= number_format((int)$r['price'],0,',','.') ?>
            </div>
          </td>
          <td style="text-align:right;font-weight:900;">
            Rp <?= number_format((int)$r['subtotal'],0,',','.') ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div style="margin-top:10px;font-size:13px;line-height:1.7;">
    <div style="display:flex;justify-content:space-between;"><span>Total</span><b>Rp <?= number_format((int)$sale['total'],0,',','.') ?></b></div>
    <div style="display:flex;justify-content:space-between;"><span>Bayar</span><b>Rp <?= number_format((int)$sale['paid'],0,',','.') ?></b></div>
    <div style="display:flex;justify-content:space-between;"><span>Kembalian</span><b>Rp <?= number_format((int)$sale['change_amount'],0,',','.') ?></b></div>
  </div>

  <div class="no-print" style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
    <button class="btn ghost" onclick="window.print()">🖨️ Print</button>
    <a class="btn primary" href="kasir_pos.php">🧾 Transaksi Baru</a>
  </div>
</div>

<?php include __DIR__ . '/partials/kasir_layout_bottom.php'; ?>

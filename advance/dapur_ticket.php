<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';
require_role(['dapur']);

$dapurId = (int)auth_user()['id'];

$st = $pdo->prepare("
  SELECT s.id, s.name
  FROM users u
  JOIN stores s ON s.owner_admin_id = u.created_by
  WHERE u.id=? AND s.is_active=1
  LIMIT 1
");
$st->execute([$dapurId]);
$store = $st->fetch();
if (!$store) { http_response_code(400); exit('Dapur belum terhubung ke toko.'); }
$storeId = (int)$store['id'];
$storeName = (string)$store['name'];

$saleId = (int)($_GET['id'] ?? 0);
if ($saleId <= 0) { http_response_code(400); exit('ID tidak valid.'); }

$sq = $pdo->prepare("
  SELECT id, invoice_no, created_at, order_note
  FROM sales
  WHERE id=? AND store_id=?
  LIMIT 1
");
$sq->execute([$saleId, $storeId]);
$sale = $sq->fetch();
if (!$sale) { http_response_code(404); exit('Order tidak ditemukan.'); }

$iq = $pdo->prepare("
  SELECT name, qty
  FROM sale_items
  WHERE sale_id=?
  ORDER BY id ASC
");
$iq->execute([$saleId]);
$items = $iq->fetchAll();

$inv = $sale['invoice_no'] ?: ('#'.$saleId);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Tiket Dapur <?= htmlspecialchars($inv) ?></title>
  <style>
    body{font-family:system-ui,Arial; margin:10px;}
    .ticket{max-width:320px;}
    h2,h3,p{margin:0}
    .muted{color:#64748b;font-size:12px}
    hr{border:none;border-top:1px dashed #999;margin:10px 0}
    .row{display:flex;justify-content:space-between;gap:10px}
  </style>
</head>
<body onload="window.print()">
  <div class="ticket">
    <h3><?= htmlspecialchars($storeName) ?></h3>
    <p class="muted">Tiket Dapur</p>
    <hr>
    <p><b>Order:</b> <?= htmlspecialchars($inv) ?></p>
    <p class="muted"><?= htmlspecialchars((string)$sale['created_at']) ?></p>

    <?php if (!empty($sale['order_note'])): ?>
      <hr>
      <p><b>Catatan:</b></p>
      <p><?= htmlspecialchars((string)$sale['order_note']) ?></p>
    <?php endif; ?>

    <hr>
    <?php foreach ($items as $it): ?>
      <div class="row">
        <div><b><?= (int)$it['qty'] ?>x</b></div>
        <div style="flex:1;"><?= htmlspecialchars((string)$it['name']) ?></div>
      </div>
    <?php endforeach; ?>

    <hr>
    <p class="muted">-- selesai --</p>
  </div>
</body>
</html>

<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';

require_role(['kasir']);
csrf_verify();

$kasirId = (int)auth_user()['id'];

$st = $pdo->prepare("
  SELECT s.id, s.name
  FROM users k
  JOIN stores s ON s.owner_admin_id = k.created_by
  WHERE k.id=? AND s.is_active=1
  LIMIT 1
");
$st->execute([$kasirId]);
$store = $st->fetch();
if (!$store) { http_response_code(400); exit('Kasir belum terhubung ke toko.'); }

$storeId = (int)$store['id'];

// WAJIB shift open
$sh = $pdo->prepare("
  SELECT id
  FROM cashier_shifts
  WHERE store_id=? AND kasir_id=? AND status='open'
  ORDER BY id DESC LIMIT 1
");
$sh->execute([$storeId, $kasirId]);
$openShift = $sh->fetch();
if (!$openShift) { http_response_code(400); exit('Shift belum dibuka.'); }
$shiftId = (int)$openShift['id'];

$cart = $_SESSION['cart'] ?? [];
if (!$cart) { header('Location: kasir_pos.php'); exit; }

$paid = max(0, (int)($_POST['paid'] ?? 0));
$method = (string)($_POST['payment_method'] ?? 'cash');
$allowed = ['cash','qris','card'];
if (!in_array($method, $allowed, true)) $method = 'cash';

// ✅ Catatan pesanan (untuk dapur)
$orderNote = trim((string)($_POST['order_note'] ?? ''));
if (strlen($orderNote) > 255) $orderNote = substr($orderNote, 0, 255);

// Ambil produk cart dari DB (validasi harga & stok)
$ids = array_keys($cart);
$in = implode(',', array_fill(0, count($ids), '?'));
$params = array_merge([$storeId], $ids);

$q = $pdo->prepare("
  SELECT id,sku,name,price,stock,discount_is_active,discount_percent
  FROM products
  WHERE store_id=? AND is_active=1 AND id IN ($in)
");
$q->execute($params);
$rows = $q->fetchAll();

$total = 0;
$items = [];
foreach ($rows as $r) {
  $pid = (int)$r['id'];
  $qty = (int)($cart[$pid] ?? 0);
  if ($qty <= 0) continue;

  if ((int)$r['stock'] < $qty) {
    http_response_code(400);
    exit("Stok kurang: {$r['name']} (stok {$r['stock']}, qty {$qty})");
  }

  $basePrice = (int)$r['price'];

  $discActive = ((int)($r['discount_is_active'] ?? 0) === 1);
  $discPercent = (int)($r['discount_percent'] ?? 0);
  if (!$discActive) $discPercent = 0;
  $discPercent = max(0, min(100, $discPercent));

  $discAmount = (int) floor(($basePrice * $discPercent) / 100); // per unit
  $finalPrice = max(0, $basePrice - $discAmount);

  $subtotal = $finalPrice * $qty;
  $total += $subtotal;

  $items[] = [
    'product_id' => $pid,
    'sku' => $r['sku'],
    'name' => $r['name'],

    // NOTE: kamu menyimpan harga setelah diskon ke sale_items.price (tetap konsisten dengan subtotal)
    'price' => $finalPrice,

    'discount_percent' => $discPercent,
    'discount_amount' => $discAmount,
    'qty' => $qty,
    'subtotal' => $subtotal
  ];
}

if (!$items) { header('Location: kasir_pos.php'); exit; }
if ($paid < $total) { http_response_code(400); exit('Uang bayar kurang.'); }

$change = $paid - $total;

try {
  $pdo->beginTransaction();

  // ✅ Simpan sales + status dapur default "Belum" + catatan
  $pdo->prepare("
    INSERT INTO sales (
      store_id, kasir_id, shift_id,
      total, paid, change_amount, payment_method,
      kitchen_done, kitchen_done_at,
      order_note
    )
    VALUES (?,?,?,?,?,?,?,?,?,?)
  ")->execute([
    $storeId, $kasirId, $shiftId,
    $total, $paid, $change, $method,
    0, null,
    $orderNote
  ]);

  $saleId = (int)$pdo->lastInsertId();
  $invoice = 'INV' . date('Ymd') . '-' . str_pad((string)$saleId, 6, '0', STR_PAD_LEFT);
  $pdo->prepare("UPDATE sales SET invoice_no=? WHERE id=?")->execute([$invoice, $saleId]);

  $ins = $pdo->prepare("
    INSERT INTO sale_items (sale_id,product_id,sku,name,price,discount_percent,discount_amount,qty,subtotal)
    VALUES (?,?,?,?,?,?,?,?,?)
  ");
  $upd = $pdo->prepare("UPDATE products SET stock=stock-? WHERE id=? AND store_id=?");

  foreach ($items as $it) {
    $ins->execute([
      $saleId,
      $it['product_id'],
      $it['sku'],
      $it['name'],
      $it['price'],
      $it['discount_percent'],
      $it['discount_amount'],
      $it['qty'],
      $it['subtotal']
    ]);
    $upd->execute([$it['qty'], $it['product_id'], $storeId]);
  }

  $pdo->commit();
  $_SESSION['cart'] = [];

  header("Location: kasir_receipt.php?id={$saleId}");
  exit;

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  exit('Gagal simpan transaksi.');
}

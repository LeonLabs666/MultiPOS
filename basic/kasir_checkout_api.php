<?php
declare(strict_types=1);

require __DIR__ . '/kasir_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan.']);
  exit;
}

try {
  csrf_verify();
} catch (Throwable $e) {
  http_response_code(403);
  echo json_encode(['success' => false, 'message' => 'CSRF tidak valid.']);
  exit;
}

if (empty($_SESSION['active_shift_id'])) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Shift belum dibuka.']);
  exit;
}

$user = auth_user();
$kasirId = (int)($user['id'] ?? 0);
$shiftId = (int)($_SESSION['active_shift_id'] ?? 0);

$payloadRaw = (string)($_POST['payload'] ?? '');
$payload = json_decode($payloadRaw, true);

if (!is_array($payload)) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Payload tidak valid.']);
  exit;
}

$method = strtolower((string)($payload['method'] ?? 'cash'));
if (!in_array($method, ['cash','qris'], true)) $method = 'cash';

$items = $payload['items'] ?? [];
$payAmount = (int)($payload['pay_amount'] ?? 0);

if (!is_array($items) || count($items) === 0) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Keranjang kosong.']);
  exit;
}

// Validasi shift masih open
$chk = $pdo->prepare("
  SELECT id
  FROM cashier_shifts
  WHERE id=? AND store_id=? AND kasir_id=? AND status='open'
  LIMIT 1
");
$chk->execute([$shiftId, $storeId, $kasirId]);
if (!(int)$chk->fetchColumn()) {
  unset($_SESSION['active_shift_id']);
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Shift tidak valid / sudah ditutup.']);
  exit;
}

// hitung total dari payload
$total = 0;
$normItems = [];
foreach ($items as $it) {
  $pid = (int)($it['id'] ?? 0);
  $name = (string)($it['name'] ?? '');
  $price = (int)($it['price'] ?? 0);
  $qty = (int)($it['qty'] ?? 0);

  if ($pid <= 0 || $qty <= 0 || $price < 0 || $name === '') continue;

  $sub = $price * $qty;
  $total += $sub;

  $normItems[] = [
    'id' => $pid,
    'name' => $name,
    'price' => $price,
    'qty' => $qty,
    'subtotal' => $sub,
  ];
}

if ($total <= 0 || count($normItems) === 0) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Item transaksi tidak valid.']);
  exit;
}

if ($method === 'cash' && $payAmount < $total) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Uang diterima kurang dari total.']);
  exit;
}

if ($method === 'qris') {
  $payAmount = $total;
}

$change = $payAmount - $total;

// Generate invoice INVYYYYMMDD-000001
$today = date('Ymd');
$prefix = 'INV' . $today . '-';

$last = $pdo->prepare("
  SELECT invoice_no
  FROM sales
  WHERE store_id=? AND invoice_no LIKE ?
  ORDER BY id DESC
  LIMIT 1
");
$last->execute([$storeId, $prefix . '%']);
$lastInv = (string)($last->fetchColumn() ?: '');
$seq = 1;
if ($lastInv && preg_match('/-(\d{6})$/', $lastInv, $m)) {
  $seq = (int)$m[1] + 1;
}
$invoiceNo = $prefix . str_pad((string)$seq, 6, '0', STR_PAD_LEFT);

try {
  $pdo->beginTransaction();

  // Insert sale (kolom mengikuti schema basic kamu)
  $insSale = $pdo->prepare("
    INSERT INTO sales (store_id, kasir_id, shift_id, invoice_no, order_note, total, paid, change_amount, payment_method, kitchen_done)
    VALUES (?, ?, ?, ?, '', ?, ?, ?, ?, 0)
  ");
  $insSale->execute([$storeId, $kasirId, $shiftId, $invoiceNo, $total, $payAmount, $change, $method]);

  $saleId = (int)$pdo->lastInsertId();
  if ($saleId <= 0) throw new RuntimeException('Gagal membuat transaksi.');

  $insItem = $pdo->prepare("
    INSERT INTO sale_items (sale_id, product_id, sku, name, price, discount_percent, discount_amount, qty, subtotal)
    VALUES (?, ?, NULL, ?, ?, 0, 0, ?, ?)
  ");

  $updStock = $pdo->prepare("
    UPDATE products
    SET stock = stock - ?
    WHERE id=? AND store_id=?
    LIMIT 1
  ");

  // Auto stock movement (OUT) per item
  $insMove = $pdo->prepare("
    INSERT INTO stock_movements
      (store_id, target_type, target_id, direction, qty, unit, note, created_by, created_at)
    VALUES
      (?, 'product', ?, 'out', ?, 'pcs', ?, ?, NOW())
  ");

  $noteBase = "Sale {$invoiceNo} (#{$saleId})";

  foreach ($normItems as $it) {
    $pid = (int)$it['id'];
    $name = (string)$it['name'];
    $price = (int)$it['price'];
    $qty = (int)$it['qty'];
    $sub = (int)$it['subtotal'];

    // 1) sale_items
    $insItem->execute([$saleId, $pid, $name, $price, $qty, $sub]);

    // 2) update stock
    $updStock->execute([$qty, $pid, $storeId]);

    // 3) stock_movements
    $qtyDec = number_format((float)$qty, 3, '.', '');
    $note = $noteBase . " - " . $name;
    $insMove->execute([$storeId, $pid, $qtyDec, $note, $kasirId]);
  }

  $pdo->commit();

  echo json_encode([
    'success' => true,
    'sale_id' => $saleId,
    'invoice_no' => $invoiceNo,
    'receipt_url' => 'kasir_receipt.php?id=' . $saleId
  ]);
  exit;

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Gagal menyimpan transaksi: ' . $e->getMessage()]);
  exit;
}

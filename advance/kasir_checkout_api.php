<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';
require __DIR__ . '/../config/audit.php'; // ✅ helper log_activity()

require_role(['kasir','admin']);
csrf_verify();

header('Content-Type: application/json');

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$actor  = auth_user();
$kasirId = (int)($actor['id'] ?? 0);

// Ambil store terkait kasir (pattern MultiPOS)
$st = $pdo->prepare("
  SELECT s.id, s.name
  FROM users k
  JOIN stores s ON s.owner_admin_id = k.created_by
  WHERE k.id=? AND s.is_active=1
  LIMIT 1
");
$st->execute([$kasirId]);
$store = $st->fetch();
if (!$store) {
  http_response_code(400);
  echo json_encode(['success'=>false,'message'=>'Kasir belum terhubung ke toko.']);
  exit;
}
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
if (!$openShift) {
  http_response_code(400);
  echo json_encode(['success'=>false,'message'=>'Shift belum dibuka.']);
  exit;
}
$shiftId = (int)$openShift['id'];

// payload dari JS
$raw  = (string)($_POST['payload'] ?? '');
$data = json_decode($raw, true);
if (!is_array($data)) {
  http_response_code(400);
  echo json_encode(['success'=>false,'message'=>'Payload tidak valid.']);
  exit;
}

$items = $data['items'] ?? [];
if (!is_array($items) || count($items) === 0) {
  http_response_code(400);
  echo json_encode(['success'=>false,'message'=>'Keranjang kosong.']);
  exit;
}

// method & paid
$method = strtolower((string)($data['method'] ?? 'cash'));
$allowed = ['cash','qris','card'];
if (!in_array($method, $allowed, true)) $method = 'cash';

$paid = max(0, (int)($data['pay_amount'] ?? 0));

// ✅ order_note harus string (NOT NULL di DB)
$orderNote = trim((string)($data['order_note'] ?? ''));
if (strlen($orderNote) > 255) $orderNote = substr($orderNote, 0, 255);

// ===== Customer (opsional) =====
$customerIdIn  = trim((string)($data['customer_id'] ?? ''));
$customerName  = trim((string)($data['customer_name'] ?? ''));
$customerPhone = trim((string)($data['customer_phone'] ?? ''));

// normalize phone
if ($customerPhone !== '') {
  $customerPhone = preg_replace('~[^0-9+]+~', '', $customerPhone) ?? $customerPhone;
  if (strlen($customerPhone) > 30) $customerPhone = substr($customerPhone, 0, 30);
}
if (strlen($customerName) > 120) $customerName = substr($customerName, 0, 120);

/* =========================================================
   ✅ DDL di luar transaksi (MySQL/MariaDB: DDL = implicit commit)
   ========================================================= */
$pdo->exec("
  CREATE TABLE IF NOT EXISTS customers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    store_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    notes VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_visit_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    INDEX idx_customers_store (store_id),
    INDEX idx_customers_phone (store_id, phone),
    INDEX idx_customers_name (store_id, name)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");

// ===== VALIDASI + HITUNG TOTAL DARI DB =====
$cart = []; // product_id => qty
foreach ($items as $it) {
  $pid = (int)($it['id'] ?? 0);
  $qty = (int)($it['qty'] ?? 0);
  if ($pid > 0 && $qty > 0) $cart[$pid] = ($cart[$pid] ?? 0) + $qty;
}
if (!$cart) {
  http_response_code(400);
  echo json_encode(['success'=>false,'message'=>'Item tidak valid.']);
  exit;
}

$ids = array_keys($cart);
$in  = implode(',', array_fill(0, count($ids), '?'));
$params = array_merge([$storeId], $ids);

$q = $pdo->prepare("
  SELECT id,sku,name,price,stock,discount_is_active,discount_percent
  FROM products
  WHERE store_id=? AND is_active=1 AND id IN ($in)
");
$q->execute($params);
$rows = $q->fetchAll();

$total = 0;
$finalItems = [];

foreach ($rows as $r) {
  $pid = (int)$r['id'];
  $qty = (int)($cart[$pid] ?? 0);
  if ($qty <= 0) continue;

  if ((int)$r['stock'] < $qty) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>"Stok kurang: {$r['name']} (stok {$r['stock']}, qty {$qty})"]);
    exit;
  }

  $basePrice = (int)$r['price'];

  $discActive  = ((int)($r['discount_is_active'] ?? 0) === 1);
  $discPercent = (int)($r['discount_percent'] ?? 0);
  if (!$discActive) $discPercent = 0;
  $discPercent = max(0, min(100, $discPercent));

  $discAmount = (int) floor(($basePrice * $discPercent) / 100); // per unit
  $finalPrice = max(0, $basePrice - $discAmount);

  $subtotal = $finalPrice * $qty;
  $total += $subtotal;

  $finalItems[] = [
    'product_id' => $pid,
    'sku' => (string)$r['sku'],
    'name' => (string)$r['name'],
    'price' => $finalPrice,
    'discount_percent' => $discPercent,
    'discount_amount' => $discAmount,
    'qty' => $qty,
    'subtotal' => $subtotal
  ];
}

if (!$finalItems) {
  http_response_code(400);
  echo json_encode(['success'=>false,'message'=>'Produk tidak ditemukan/invalid.']);
  exit;
}

// QRIS -> paid otomatis total
if ($method === 'qris') $paid = $total;

if ($paid < $total) {
  http_response_code(400);
  echo json_encode(['success'=>false,'message'=>'Uang bayar kurang.']);
  exit;
}
$change = $paid - $total;

try {
  $pdo->beginTransaction();

  // ===== Upsert Customer (opsional) =====
  $custId = null;
  $hasCustomerInput = ($customerName !== '' || $customerPhone !== '');

  if ($hasCustomerInput) {

    // 1) customer_id valid
    if ($customerIdIn !== '' && ctype_digit($customerIdIn)) {
      $cid = (int)$customerIdIn;
      $chk = $pdo->prepare("SELECT id FROM customers WHERE id=? AND store_id=? LIMIT 1");
      $chk->execute([$cid, $storeId]);
      if ($chk->fetch()) {
        $custId = $cid;
        $updC = $pdo->prepare("
          UPDATE customers
          SET name=?, phone=?, last_visit_at=NOW()
          WHERE id=? AND store_id=?
        ");
        $nameUse  = ($customerName !== '') ? $customerName : 'Umum';
        $phoneUse = ($customerPhone !== '') ? $customerPhone : null;
        $updC->execute([$nameUse, $phoneUse, $custId, $storeId]);
      }
    }

    // 2) cari by phone
    if ($custId === null && $customerPhone !== '') {
      $chk = $pdo->prepare("SELECT id FROM customers WHERE store_id=? AND phone=? LIMIT 1");
      $chk->execute([$storeId, $customerPhone]);
      $row = $chk->fetch();
      if ($row) {
        $custId = (int)$row['id'];
        $updC = $pdo->prepare("UPDATE customers SET name=?, last_visit_at=NOW() WHERE id=? AND store_id=?");
        $nameUse = ($customerName !== '') ? $customerName : (string)$customerPhone;
        $updC->execute([$nameUse, $custId, $storeId]);
      }
    }

    // 3) fallback by name
    if ($custId === null && $customerName !== '') {
      $chk = $pdo->prepare("SELECT id FROM customers WHERE store_id=? AND name=? LIMIT 1");
      $chk->execute([$storeId, $customerName]);
      $row = $chk->fetch();
      if ($row) {
        $custId = (int)$row['id'];
        $updC = $pdo->prepare("UPDATE customers SET phone=?, last_visit_at=NOW() WHERE id=? AND store_id=?");
        $phoneUse = ($customerPhone !== '') ? $customerPhone : null;
        $updC->execute([$phoneUse, $custId, $storeId]);
      }
    }

    // 4) insert baru
    if ($custId === null) {
      $insC = $pdo->prepare("
        INSERT INTO customers (store_id, name, phone, last_visit_at)
        VALUES (?,?,?,NOW())
      ");
      $nameUse  = ($customerName !== '') ? $customerName : (($customerPhone !== '') ? (string)$customerPhone : 'Umum');
      $phoneUse = ($customerPhone !== '') ? $customerPhone : null;
      $insC->execute([$storeId, $nameUse, $phoneUse]);
      $custId = (int)$pdo->lastInsertId();
    }
  }

  // insert sales
  $pdo->prepare("
    INSERT INTO sales (
      store_id, kasir_id, shift_id,
      total, paid, change_amount, payment_method,
      kitchen_done, kitchen_done_at,
      order_note
    ) VALUES (?,?,?,?,?,?,?,?,?,?)
  ")->execute([
    $storeId, $kasirId, $shiftId,
    $total, $paid, $change, $method,
    0, null,
    $orderNote
  ]);

  $saleId = (int)$pdo->lastInsertId();

  // invoice
  $invoice = 'INV' . date('Ymd') . '-' . str_pad((string)$saleId, 6, '0', STR_PAD_LEFT);
  $pdo->prepare("UPDATE sales SET invoice_no=? WHERE id=?")->execute([$invoice, $saleId]);

  // insert items + update stock
  $ins = $pdo->prepare("
    INSERT INTO sale_items
      (sale_id,product_id,sku,name,price,discount_percent,discount_amount,qty,subtotal)
    VALUES (?,?,?,?,?,?,?,?,?)
  ");
  $upd = $pdo->prepare("UPDATE products SET stock=stock-? WHERE id=? AND store_id=?");

  foreach ($finalItems as $it) {
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

  // ✅ Activity log setelah transaksi sukses
  $itemsSummary = [];
  foreach ($finalItems as $it) {
    $itemsSummary[] = [
      'product_id' => (int)$it['product_id'],
      'qty' => (int)$it['qty'],
      'price' => (int)$it['price'],
      'subtotal' => (int)$it['subtotal'],
      'discount_percent' => (int)$it['discount_percent'],
    ];
  }

  log_activity(
    $pdo,
    $actor,
    'SALE_CREATE',
    'Checkout ' . $invoice . ' (sale #' . $saleId . ') total ' . $total,
    'sale',
    $saleId,
    [
      'invoice_no' => $invoice,
      'store_id' => $storeId,
      'shift_id' => $shiftId,
      'total' => $total,
      'paid' => $paid,
      'change' => $change,
      'payment_method' => $method,
      'order_note' => $orderNote,
      'items' => $itemsSummary,
      'customer' => [
        'id' => $custId,
        'name' => $customerName,
        'phone' => $customerPhone,
      ],
    ],
    $storeId
  );

  echo json_encode([
    'success' => true,
    'sale_id' => $saleId,
    'invoice_no' => $invoice,
    'change' => $change,
    'receipt_url' => "kasir_receipt.php?id={$saleId}"
  ]);
  exit;

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();

  http_response_code(500);
  echo json_encode([
    'success'=>false,
    'message'=>'Gagal simpan transaksi: '.$e->getMessage()
  ]);
  exit;
}

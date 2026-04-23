<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';
require_role(['kasir','admin']);

header('Content-Type: application/json');

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$user = auth_user();
$kasirId = (int)($user['id'] ?? 0);

// Resolve store (pattern MultiPOS)
$st = $pdo->prepare("
  SELECT s.id
  FROM users k
  JOIN stores s ON s.owner_admin_id = k.created_by
  WHERE k.id=? AND s.is_active=1
  LIMIT 1
");
$st->execute([$kasirId]);
$store = $st->fetch();
if (!$store) {
  echo json_encode(['success'=>false,'items'=>[],'message'=>'Kasir belum terhubung ke toko.']);
  exit;
}
$storeId = (int)$store['id'];

// Ensure customers table exists
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

$q = trim((string)($_GET['q'] ?? ''));
if (mb_strlen($q) < 2) {
  echo json_encode(['success'=>true,'items'=>[]]);
  exit;
}

$like = '%' . $q . '%';
$stmt = $pdo->prepare("
  SELECT id, name, phone
  FROM customers
  WHERE store_id=? AND (name LIKE ? OR phone LIKE ?)
  ORDER BY COALESCE(last_visit_at, created_at) DESC, id DESC
  LIMIT 12
");
$stmt->execute([$storeId, $like, $like]);

$items = [];
foreach ($stmt->fetchAll() as $r) {
  $items[] = [
    'id' => (string)$r['id'],
    'name' => (string)$r['name'],
    'phone' => (string)($r['phone'] ?? '')
  ];
}

echo json_encode(['success'=>true,'items'=>$items]);

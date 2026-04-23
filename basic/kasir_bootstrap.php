<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';

if (!auth_user()) {
  header('Location: ../login.php'); exit;
}

$role = (string)(auth_user()['role'] ?? '');
if ($role !== 'kasir' && $role !== 'admin') {
  redirect_by_role($role);
}

$kasirId = (int)(auth_user()['id'] ?? 0);
if ($kasirId <= 0) {
  header('Location: ../login.php'); exit;
}

/**
 * Resolve toko: kasir -> users.created_by (admin_id) -> stores.owner_admin_id
 */
$st = $pdo->prepare("
  SELECT s.id, s.name, s.store_type
  FROM users k
  JOIN stores s ON s.owner_admin_id = k.created_by
  WHERE k.id=? AND s.is_active=1
  LIMIT 1
");
$st->execute([$kasirId]);
$store = $st->fetch(PDO::FETCH_ASSOC);

if (!$store) {
  http_response_code(400);
  exit('Kasir belum terhubung ke toko.');
}

$storeId   = (int)$store['id'];
$storeName = (string)$store['name'];
$storeType = (string)($store['store_type'] ?? 'bom');

if ($storeType !== 'basic') {
  // kasir advance tetap diarahkan ke modul kasir utama
  header('Location: ../kasir_dashboard.php'); exit;
}

/**
 * Shift open (ambil terakhir)
 */
$sh = $pdo->prepare("
  SELECT id
  FROM cashier_shifts
  WHERE store_id=? AND kasir_id=? AND status='open'
  ORDER BY id DESC
  LIMIT 1
");
$sh->execute([$storeId, $kasirId]);
$openShiftId = (int)($sh->fetchColumn() ?: 0);

// Sinkron session shift id biar layout bisa tampilkan badge
if ($openShiftId > 0) {
  $_SESSION['active_shift_id'] = $openShiftId;
} else {
  unset($_SESSION['active_shift_id']);
}

<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';

// Pastikan helper store_type ada (kalau belum, bikin di config/store_type.php)
require __DIR__ . '/../config/store_type.php';

if (!auth_user()) {
  header('Location: ../login.php'); exit;
}
if ((string)auth_user()['role'] !== 'admin') {
  redirect_by_role((string)auth_user()['role']);
}

$adminId = (int)auth_user()['id'];
$store   = store_by_admin($pdo, $adminId);
$storeId = (int)$store['id'];

$type = store_type($pdo, $storeId);
if ($type !== 'basic') {
  header('Location: ../admin_dashboard.php'); exit;
}

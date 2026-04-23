<?php
declare(strict_types=1);

/**
 * Ambil row store milik admin.
 */
function get_store_by_admin(PDO $pdo, int $adminId): array {
  $st = $pdo->prepare("SELECT * FROM stores WHERE owner_admin_id=? AND is_active=1 LIMIT 1");
  $st->execute([$adminId]);
  $store = $st->fetch(PDO::FETCH_ASSOC);
  if (!$store) { http_response_code(400); exit('Admin belum terhubung ke toko.'); }
  return $store;
}

/**
 * Ambil store_type dengan fallback aman kalau kolom belum ada.
 */
function get_store_type(PDO $pdo, int $storeId): string {
  $dbName = (string)$pdo->query("SELECT DATABASE()")->fetchColumn();

  $colQ = $pdo->prepare("
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=? AND TABLE_NAME='stores' AND COLUMN_NAME='store_type'
  ");
  $colQ->execute([$dbName]);
  $has = (int)$colQ->fetchColumn() > 0;

  if (!$has) return 'bom'; // fallback kalau DB belum dimigrasi

  $q = $pdo->prepare("SELECT store_type FROM stores WHERE id=? LIMIT 1");
  $q->execute([$storeId]);
  $t = $q->fetchColumn();
  return $t ? (string)$t : 'bom';
}

/**
 * Guard: kalau toko basic, blok halaman fitur BOM (bahan/resep/dll).
 */
function require_bom_store(PDO $pdo, int $storeId): void {
  if (get_store_type($pdo, $storeId) !== 'bom') {
    http_response_code(403);
    exit('Fitur bahan & resep tidak aktif untuk jenis toko ini.');
  }
}

<?php
declare(strict_types=1);

/**
 * 1 admin = 1 toko
 */
function store_by_admin(PDO $pdo, int $adminId): array {
  $st = $pdo->prepare("SELECT * FROM stores WHERE owner_admin_id=? AND is_active=1 LIMIT 1");
  $st->execute([$adminId]);
  $s = $st->fetch(PDO::FETCH_ASSOC);
  if (!$s) { http_response_code(400); exit('Store tidak ditemukan.'); }
  return $s;
}

function store_type(PDO $pdo, int $storeId): string {
  $st = $pdo->prepare("SELECT store_type FROM stores WHERE id=? LIMIT 1");
  $st->execute([$storeId]);
  return (string)($st->fetchColumn() ?: 'bom');
}

/**
 * Blok fitur BOM kalau toko basic
 */
function require_bom_store(PDO $pdo, int $storeId): void {
  if (store_type($pdo, $storeId) !== 'bom') {
    http_response_code(403);
    exit('Fitur bahan & resep tidak aktif untuk jenis toko ini.');
  }
}

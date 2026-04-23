<?php
declare(strict_types=1);

/**
 * Resolve store_id berdasarkan actor.
 * Pattern MultiPOS:
 * - admin → stores.owner_admin_id = admin.id
 * - kasir → stores.owner_admin_id = kasir.created_by
 */
function resolve_store_id(PDO $pdo, array $actor): ?int
{
  $role = (string)($actor['role'] ?? '');
  $actorId = (int)($actor['id'] ?? 0);

  if ($actorId <= 0) return null;

  // ADMIN
  if ($role === 'admin') {
    $st = $pdo->prepare("
      SELECT id
      FROM stores
      WHERE owner_admin_id = ?
      LIMIT 1
    ");
    $st->execute([$actorId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ? (int)$row['id'] : null;
  }

  // KASIR
  if ($role === 'kasir') {
    $adminId = (int)($actor['created_by'] ?? 0);

    // fallback kalau auth_user() tidak bawa created_by
    if ($adminId <= 0) {
      $st2 = $pdo->prepare("
        SELECT created_by
        FROM users
        WHERE id=?
        LIMIT 1
      ");
      $st2->execute([$actorId]);
      $u = $st2->fetch(PDO::FETCH_ASSOC);
      $adminId = $u ? (int)$u['created_by'] : 0;
    }

    if ($adminId <= 0) return null;

    $st = $pdo->prepare("
      SELECT id
      FROM stores
      WHERE owner_admin_id = ?
      LIMIT 1
    ");
    $st->execute([$adminId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ? (int)$row['id'] : null;
  }

  return null;
}


/**
 * Helper utama: simpan activity log
 *
 * @param PDO   $pdo
 * @param array $actor       → dari auth_user()
 * @param string $action     → kode aksi (SALE_CREATE, SHIFT_OPEN, dll)
 * @param string $message    → deskripsi manusia
 * @param string|null $entityType
 * @param int|null $entityId
 * @param array $meta        → data tambahan (akan di-json)
 * @param int|null $storeId  → override kalau sudah tahu
 */
function log_activity(
  PDO $pdo,
  array $actor,
  string $action,
  string $message,
  ?string $entityType = null,
  $entityId = null,
  array $meta = [],
  ?int $storeId = null
): void {
  try {

    $actorId = (int)($actor['id'] ?? 0);
    $role    = (string)($actor['role'] ?? 'unknown');

    if ($actorId <= 0) return;

    // resolve store otomatis kalau tidak dikirim
    if ($storeId === null) {
      $storeId = resolve_store_id($pdo, $actor);
    }

    // IP + UA
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

    // Encode meta JSON (safe)
    $metaJson = null;
    if (!empty($meta)) {
      $metaJson = json_encode(
        $meta,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
      );
      if ($metaJson === false) $metaJson = null;
    }

    $stmt = $pdo->prepare("
      INSERT INTO activity_logs
      (
        store_id,
        actor_user_id,
        actor_role,
        action,
        entity_type,
        entity_id,
        message,
        meta_json,
        ip_address,
        user_agent
      )
      VALUES
      (
        :store_id,
        :actor_user_id,
        :actor_role,
        :action,
        :entity_type,
        :entity_id,
        :message,
        :meta_json,
        :ip_address,
        :user_agent
      )
    ");

    $stmt->execute([
      ':store_id'      => $storeId,
      ':actor_user_id' => $actorId,
      ':actor_role'    => $role,
      ':action'        => $action,
      ':entity_type'   => $entityType,
      ':entity_id'     => ($entityId !== null ? (int)$entityId : null),
      ':message'       => mb_substr($message, 0, 255),
      ':meta_json'     => $metaJson,
      ':ip_address'    => $ip ? mb_substr($ip, 0, 45) : null,
      ':user_agent'    => $ua ? mb_substr($ua, 0, 255) : null,
    ]);

  } catch (Throwable $e) {

    // ❗ Logging tidak boleh merusak transaksi utama
    // Simpan error ke PHP log untuk debug
    error_log('AUDIT ERROR: ' . $e->getMessage());
  }
}

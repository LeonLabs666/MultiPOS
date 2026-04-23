<?php
declare(strict_types=1);

/**
 * Inventory helper untuk bahan baku.
 * Dipakai di:
 * - advance/admin_persediaan_bahan.php
 * - advance/admin_stok_inout.php
 * - advance/dapur_produksi.php
 *
 * Catatan:
 * pastikan tabel `ingredients` sudah punya kolom:
 * - safety_stock
 * - lead_time_days
 * - avg_daily_usage
 * - reorder_point
 * - suggested_restock_qty
 */

if (!function_exists('inv_clamp_dec')) {
  function inv_clamp_dec($v, float $min = 0.0, float $max = 999999999.0): float {
    if (!is_numeric($v)) return 0.0;

    $f = (float)$v;
    if ($f < $min) $f = $min;
    if ($f > $max) $f = $max;

    return $f;
  }
}

if (!function_exists('inv_ingredient_of_store')) {
  function inv_ingredient_of_store(PDO $pdo, int $id, int $storeId): ?array {
    $st = $pdo->prepare("
      SELECT *
      FROM ingredients
      WHERE id = ? AND store_id = ?
      LIMIT 1
    ");
    $st->execute([$id, $storeId]);

    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
  }
}

if (!function_exists('inv_calculate_avg_daily_usage')) {
  function inv_calculate_avg_daily_usage(PDO $pdo, int $storeId, int $ingredientId, int $days = 30): float {
    $days = max(1, $days);

    $q = $pdo->prepare("
      SELECT COALESCE(SUM(qty), 0) / ? AS avg_daily_usage
      FROM stock_movements
      WHERE store_id = ?
        AND target_type = 'ingredient'
        AND target_id = ?
        AND direction = 'out'
        AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    $q->execute([$days, $storeId, $ingredientId, $days]);

    return round((float)($q->fetchColumn() ?: 0), 3);
  }
}

if (!function_exists('inv_calculate_reorder_point')) {
  function inv_calculate_reorder_point(float $avgDailyUsage, int $leadTimeDays, float $safetyStock): float {
    $leadTimeDays = max(1, $leadTimeDays);
    return round(($avgDailyUsage * $leadTimeDays) + $safetyStock, 3);
  }
}

if (!function_exists('inv_calculate_suggested_restock_qty')) {
  function inv_calculate_suggested_restock_qty(float $avgDailyUsage, float $safetyStock, int $coverageDays = 7): float {
    $coverageDays = max(1, $coverageDays);
    return round(($avgDailyUsage * $coverageDays) + $safetyStock, 3);
  }
}

if (!function_exists('inv_get_ingredient_stock_status')) {
  function inv_get_ingredient_stock_status(float $stock, float $safetyStock, float $reorderPoint): array {
    if ($stock <= 0) {
      return [
        'key'   => 'habis',
        'label' => 'Habis',
      ];
    }

    if ($stock <= $safetyStock) {
      return [
        'key'   => 'kritis',
        'label' => 'Kritis',
      ];
    }

    if ($stock <= $reorderPoint) {
      return [
        'key'   => 'restock',
        'label' => 'Perlu Restock',
      ];
    }

    return [
      'key'   => 'aman',
      'label' => 'Aman',
    ];
  }
}

if (!function_exists('inv_recalc_ingredient_metrics')) {
  function inv_recalc_ingredient_metrics(PDO $pdo, int $storeId, int $ingredientId, int $usageDays = 30, int $coverageDays = 7): void {
    $rowQ = $pdo->prepare("
      SELECT id, safety_stock, lead_time_days
      FROM ingredients
      WHERE id = ? AND store_id = ?
      LIMIT 1
    ");
    $rowQ->execute([$ingredientId, $storeId]);
    $row = $rowQ->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
      return;
    }

    $safetyStock = inv_clamp_dec($row['safety_stock'] ?? 0);
    $leadTimeDays = max(1, (int)($row['lead_time_days'] ?? 1));

    $avgDailyUsage = inv_calculate_avg_daily_usage($pdo, $storeId, $ingredientId, $usageDays);
    $reorderPoint = inv_calculate_reorder_point($avgDailyUsage, $leadTimeDays, $safetyStock);
    $suggestedRestockQty = inv_calculate_suggested_restock_qty($avgDailyUsage, $safetyStock, $coverageDays);

    $u = $pdo->prepare("
      UPDATE ingredients
      SET avg_daily_usage = ?,
          reorder_point = ?,
          suggested_restock_qty = ?,
          updated_at = NOW()
      WHERE id = ? AND store_id = ?
    ");
    $u->execute([
      $avgDailyUsage,
      $reorderPoint,
      $suggestedRestockQty,
      $ingredientId,
      $storeId
    ]);
  }
}

if (!function_exists('inv_bootstrap_ingredient_metrics')) {
  function inv_bootstrap_ingredient_metrics(PDO $pdo, int $storeId, int $ingredientId): void {
    $row = inv_ingredient_of_store($pdo, $ingredientId, $storeId);
    if (!$row) {
      return;
    }

    $safetyStock = inv_clamp_dec($row['safety_stock'] ?? 0);
    $leadTimeDays = max(1, (int)($row['lead_time_days'] ?? 1));
    $avgDailyUsage = inv_clamp_dec($row['avg_daily_usage'] ?? 0);

    $reorderPoint = inv_calculate_reorder_point($avgDailyUsage, $leadTimeDays, $safetyStock);
    $suggestedRestockQty = inv_calculate_suggested_restock_qty($avgDailyUsage, $safetyStock, 7);

    $u = $pdo->prepare("
      UPDATE ingredients
      SET reorder_point = ?,
          suggested_restock_qty = ?,
          updated_at = NOW()
      WHERE id = ? AND store_id = ?
    ");
    $u->execute([
      $reorderPoint,
      $suggestedRestockQty,
      $ingredientId,
      $storeId
    ]);
  }
}
<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';
require_role(['developer']);

$u = auth_user();

function h($s): string {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function hasTable(PDO $pdo, string $table): bool {
  try {
    $st = $pdo->prepare("
      SELECT COUNT(*) c
      FROM INFORMATION_SCHEMA.TABLES
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
    ");
    $st->execute([$table]);
    return ((int)($st->fetch()['c'] ?? 0)) > 0;
  } catch (Throwable $e) {
    return false;
  }
}

function hasColumn(PDO $pdo, string $table, string $col): bool {
  try {
    $st = $pdo->prepare("
      SELECT COUNT(*) c
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
        AND COLUMN_NAME = ?
    ");
    $st->execute([$table, $col]);
    return ((int)($st->fetch()['c'] ?? 0)) > 0;
  } catch (Throwable $e) {
    return false;
  }
}

function fetchAllSafe(PDO $pdo, string $sql, array $params = []): array {
  try {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) {
    return [];
  }
}

function fetchOneSafe(PDO $pdo, string $sql, array $params = []): ?array {
  try {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
  } catch (Throwable $e) {
    return null;
  }
}

function countSafe(PDO $pdo, string $sql, array $params = []): int {
  try {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return (int)($st->fetch()['c'] ?? 0);
  } catch (Throwable $e) {
    return 0;
  }
}

function money_idr($v): string {
  $n = (float)($v ?? 0);
  return 'Rp ' . number_format($n, 0, ',', '.');
}

function csvDownload(string $filename, array $rows): void {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="' . $filename . '"');

  $out = fopen('php://output', 'w');
  if (!$out) exit;

  if (!$rows) {
    fputcsv($out, ['empty']);
    fclose($out);
    exit;
  }

  fputcsv($out, array_keys($rows[0]));
  foreach ($rows as $row) {
    fputcsv($out, $row);
  }
  fclose($out);
  exit;
}

$storeId = max(0, (int)($_GET['id'] ?? 0));
if ($storeId <= 0) {
  header('Location: dev_stores.php');
  exit;
}

$tab = trim((string)($_GET['tab'] ?? 'overview'));
$allowedTabs = ['overview','users','products','categories','ingredients','suppliers','recipes','sales','stock','logs'];
if (!in_array($tab, $allowedTabs, true)) $tab = 'overview';

$q = trim((string)($_GET['q'] ?? ''));
$detailId = max(0, (int)($_GET['detail'] ?? 0));
$export = trim((string)($_GET['export'] ?? ''));
$ok  = trim((string)($_GET['ok'] ?? ''));
$err = trim((string)($_GET['err'] ?? ''));

$storesHasOwner = hasColumn($pdo, 'stores', 'owner_admin_id');
$storesHasName  = hasColumn($pdo, 'stores', 'name');

$store = fetchOneSafe($pdo, "SELECT * FROM stores WHERE id=? LIMIT 1", [$storeId]);

if (!$store) {
  ?>
  <!doctype html>
  <html lang="id">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Dev · Store Not Found</title>
    <link rel="stylesheet" href="../publik/assets/dev.css">
  </head>
  <body>
    <div class="container">
      <div class="topbar">
        <div class="brand"><b>Dev · Store Detail</b><span><?= h($u['name']) ?></span></div>
        <div class="actions"><a class="btn" href="dev_stores.php">Back</a></div>
      </div>
      <div class="grid">
        <section class="card" style="grid-column:span 12">
          <h3>Store tidak ditemukan</h3>
          <div class="small">ID: <?= (int)$storeId ?></div>
        </section>
      </div>
    </div>
  </body>
  </html>
  <?php
  exit;
}

/* =====================
   Owner
===================== */
$owner = null;
$ownerId = 0;
if ($storesHasOwner && !empty($store['owner_admin_id'])) {
  $ownerId = (int)$store['owner_admin_id'];
  $owner = fetchOneSafe(
    $pdo,
    "SELECT id, name, email, role, is_active, created_at FROM users WHERE id=? LIMIT 1",
    [$ownerId]
  );
}

/* =====================
   Schema flags
===================== */
$usersOk            = hasTable($pdo, 'users');
$usersHasRole       = hasColumn($pdo, 'users', 'role');
$usersHasCreatedBy  = hasColumn($pdo, 'users', 'created_by');
$usersHasIsActive   = hasColumn($pdo, 'users', 'is_active');
$usersHasCreatedAt  = hasColumn($pdo, 'users', 'created_at');

$categoriesOk          = hasTable($pdo, 'categories');
$categoriesHasStoreId  = $categoriesOk && hasColumn($pdo, 'categories', 'store_id');
$categoriesHasName     = $categoriesOk && hasColumn($pdo, 'categories', 'name');
$categoriesHasCreatedAt= $categoriesOk && hasColumn($pdo, 'categories', 'created_at');

$productsOk            = hasTable($pdo, 'products');
$productsHasStoreId    = $productsOk && hasColumn($pdo, 'products', 'store_id');
$productsHasCategoryId = $productsOk && hasColumn($pdo, 'products', 'category_id');
$productsHasName       = $productsOk && hasColumn($pdo, 'products', 'name');
$productsHasPrice      = $productsOk && hasColumn($pdo, 'products', 'price');
$productsHasIsActive   = $productsOk && hasColumn($pdo, 'products', 'is_active');
$productsHasCreatedAt  = $productsOk && hasColumn($pdo, 'products', 'created_at');

$ingredientsOk            = hasTable($pdo, 'ingredients');
$ingredientsHasStoreId    = $ingredientsOk && hasColumn($pdo, 'ingredients', 'store_id');
$ingredientsHasName       = $ingredientsOk && hasColumn($pdo, 'ingredients', 'name');
$ingredientsHasStock      = $ingredientsOk && hasColumn($pdo, 'ingredients', 'stock');
$ingredientsHasUnit       = $ingredientsOk && hasColumn($pdo, 'ingredients', 'unit');
$ingredientsHasMinStock   = $ingredientsOk && hasColumn($pdo, 'ingredients', 'min_stock');
$ingredientsHasSupplierId = $ingredientsOk && hasColumn($pdo, 'ingredients', 'supplier_id');
$ingredientsHasCreatedAt  = $ingredientsOk && hasColumn($pdo, 'ingredients', 'created_at');

$suppliersOk           = hasTable($pdo, 'suppliers');
$suppliersHasStoreId   = $suppliersOk && hasColumn($pdo, 'suppliers', 'store_id');
$suppliersHasName      = $suppliersOk && hasColumn($pdo, 'suppliers', 'name');
$suppliersHasPhone     = $suppliersOk && hasColumn($pdo, 'suppliers', 'phone');
$suppliersHasAddress   = $suppliersOk && hasColumn($pdo, 'suppliers', 'address');
$suppliersHasCreatedAt = $suppliersOk && hasColumn($pdo, 'suppliers', 'created_at');

$bomRecipesOk             = hasTable($pdo, 'bom_recipes');
$bomRecipesHasStoreId     = $bomRecipesOk && hasColumn($pdo, 'bom_recipes', 'store_id');
$bomRecipesHasProductId   = $bomRecipesOk && hasColumn($pdo, 'bom_recipes', 'product_id');
$bomRecipesHasName        = $bomRecipesOk && hasColumn($pdo, 'bom_recipes', 'name');
$bomRecipesHasCreatedAt   = $bomRecipesOk && hasColumn($pdo, 'bom_recipes', 'created_at');

$bomRecipeItemsOk               = hasTable($pdo, 'bom_recipe_items');
$bomRecipeItemsHasRecipeId      = $bomRecipeItemsOk && hasColumn($pdo, 'bom_recipe_items', 'recipe_id');
$bomRecipeItemsHasIngredientId  = $bomRecipeItemsOk && hasColumn($pdo, 'bom_recipe_items', 'ingredient_id');
$bomRecipeItemsHasQty           = $bomRecipeItemsOk && hasColumn($pdo, 'bom_recipe_items', 'qty');

$salesOk             = hasTable($pdo, 'sales');
$salesHasStoreId     = $salesOk && hasColumn($pdo, 'sales', 'store_id');
$salesHasKasirId     = $salesOk && hasColumn($pdo, 'sales', 'kasir_id');
$salesHasTotal       = $salesOk && hasColumn($pdo, 'sales', 'total');
$salesHasStatus      = $salesOk && hasColumn($pdo, 'sales', 'status');
$salesHasCreatedAt   = $salesOk && hasColumn($pdo, 'sales', 'created_at');

$saleItemsOk             = hasTable($pdo, 'sale_items');
$saleItemsHasSaleId      = $saleItemsOk && hasColumn($pdo, 'sale_items', 'sale_id');
$saleItemsHasProductId   = $saleItemsOk && hasColumn($pdo, 'sale_items', 'product_id');
$saleItemsHasQty         = $saleItemsOk && hasColumn($pdo, 'sale_items', 'qty');
$saleItemsHasPrice       = $saleItemsOk && hasColumn($pdo, 'sale_items', 'price');
$saleItemsHasSubtotal    = $saleItemsOk && hasColumn($pdo, 'sale_items', 'subtotal');

$stockMovementsOk               = hasTable($pdo, 'stock_movements');
$stockMovementsHasStoreId       = $stockMovementsOk && hasColumn($pdo, 'stock_movements', 'store_id');
$stockMovementsHasIngredientId  = $stockMovementsOk && hasColumn($pdo, 'stock_movements', 'ingredient_id');
$stockMovementsHasType          = $stockMovementsOk && hasColumn($pdo, 'stock_movements', 'type');
$stockMovementsHasQty           = $stockMovementsOk && hasColumn($pdo, 'stock_movements', 'qty');
$stockMovementsHasNote          = $stockMovementsOk && hasColumn($pdo, 'stock_movements', 'note');
$stockMovementsHasCreatedAt     = $stockMovementsOk && hasColumn($pdo, 'stock_movements', 'created_at');

$stockOpnamesOk             = hasTable($pdo, 'stock_opnames');
$stockOpnamesHasStoreId     = $stockOpnamesOk && hasColumn($pdo, 'stock_opnames', 'store_id');
$stockOpnamesHasCreatedBy   = $stockOpnamesOk && hasColumn($pdo, 'stock_opnames', 'created_by');
$stockOpnamesHasNote        = $stockOpnamesOk && hasColumn($pdo, 'stock_opnames', 'note');
$stockOpnamesHasCreatedAt   = $stockOpnamesOk && hasColumn($pdo, 'stock_opnames', 'created_at');

$logsOk = hasTable($pdo, 'activity_logs')
  && hasColumn($pdo, 'activity_logs', 'store_id')
  && hasColumn($pdo, 'activity_logs', 'created_at')
  && hasColumn($pdo, 'activity_logs', 'action')
  && hasColumn($pdo, 'activity_logs', 'message');

$logsJoinActor = $logsOk && hasColumn($pdo, 'activity_logs', 'actor_user_id');

$shiftsOk = hasTable($pdo, 'cashier_shifts') && hasColumn($pdo, 'cashier_shifts', 'store_id');
$shiftsHasStatus   = $shiftsOk && hasColumn($pdo, 'cashier_shifts', 'status');
$shiftsHasOpenedAt = $shiftsOk && hasColumn($pdo, 'cashier_shifts', 'opened_at');
$shiftsHasClosedAt = $shiftsOk && hasColumn($pdo, 'cashier_shifts', 'closed_at');
$shiftsHasKasirId  = $shiftsOk && hasColumn($pdo, 'cashier_shifts', 'kasir_id');
$shiftsHasCash     = $shiftsOk && hasColumn($pdo, 'cashier_shifts', 'opening_cash');

/* =====================
   KPI
===================== */
$kpi = [
  'products' => 0,
  'categories' => 0,
  'ingredients' => 0,
  'recipes' => 0,
  'suppliers' => 0,
  'sales' => 0,
  'logs' => 0,
  'shifts' => 0,
  'open_shifts' => 0,
  'users_total' => 0,
  'users_admin' => 0,
  'users_kasir' => 0,
  'users_dapur' => 0,
  'users_inactive' => 0,
];

if ($productsHasStoreId) $kpi['products'] = countSafe($pdo, "SELECT COUNT(*) c FROM products WHERE store_id=?", [$storeId]);
if ($categoriesHasStoreId) $kpi['categories'] = countSafe($pdo, "SELECT COUNT(*) c FROM categories WHERE store_id=?", [$storeId]);
if ($ingredientsHasStoreId) $kpi['ingredients'] = countSafe($pdo, "SELECT COUNT(*) c FROM ingredients WHERE store_id=?", [$storeId]);
if ($bomRecipesHasStoreId) $kpi['recipes'] = countSafe($pdo, "SELECT COUNT(*) c FROM bom_recipes WHERE store_id=?", [$storeId]);
if ($suppliersHasStoreId) $kpi['suppliers'] = countSafe($pdo, "SELECT COUNT(*) c FROM suppliers WHERE store_id=?", [$storeId]);
if ($salesHasStoreId) $kpi['sales'] = countSafe($pdo, "SELECT COUNT(*) c FROM sales WHERE store_id=?", [$storeId]);
if ($logsOk) $kpi['logs'] = countSafe($pdo, "SELECT COUNT(*) c FROM activity_logs WHERE store_id=?", [$storeId]);
if ($shiftsOk) {
  $kpi['shifts'] = countSafe($pdo, "SELECT COUNT(*) c FROM cashier_shifts WHERE store_id=?", [$storeId]);
  if ($shiftsHasStatus) {
    $kpi['open_shifts'] = countSafe($pdo, "SELECT COUNT(*) c FROM cashier_shifts WHERE store_id=? AND status='open'", [$storeId]);
  }
}

if ($ownerId > 0 && $usersHasCreatedBy) {
  $kpi['users_total'] = countSafe($pdo, "SELECT COUNT(*) c FROM users WHERE created_by=?", [$ownerId]);

  if ($usersHasRole) {
    $roleRows = fetchAllSafe($pdo, "SELECT role, COUNT(*) c FROM users WHERE created_by=? GROUP BY role", [$ownerId]);
    foreach ($roleRows as $r) {
      $role = (string)($r['role'] ?? '');
      $c = (int)($r['c'] ?? 0);
      if ($role === 'admin') $kpi['users_admin'] = $c;
      if ($role === 'kasir') $kpi['users_kasir'] = $c;
      if ($role === 'dapur') $kpi['users_dapur'] = $c;
    }
  }

  if ($usersHasIsActive) {
    $kpi['users_inactive'] = countSafe($pdo, "SELECT COUNT(*) c FROM users WHERE created_by=? AND is_active=0", [$ownerId]);
  }
}

/* =====================
   Detail data by tab
===================== */
$rows = [];
$detail = null;
$extraRows = [];

switch ($tab) {
  case 'users':
    if ($ownerId > 0 && $usersHasCreatedBy) {
      $sql = "SELECT id, name, email"
        . ($usersHasRole ? ", role" : ", NULL AS role")
        . ($usersHasIsActive ? ", is_active" : ", NULL AS is_active")
        . ($usersHasCreatedAt ? ", created_at" : ", NULL AS created_at")
        . " FROM users WHERE created_by=?";
      $params = [$ownerId];
      if ($q !== '') {
        $sql .= " AND (name LIKE ? OR email LIKE ? " . ($usersHasRole ? " OR role LIKE ? " : "") . ")";
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
        if ($usersHasRole) $params[] = $like;
      }
      $sql .= " ORDER BY id DESC LIMIT 200";
      $rows = fetchAllSafe($pdo, $sql, $params);

      if ($detailId > 0) {
        $detailSql = "SELECT id, name, email"
          . ($usersHasRole ? ", role" : ", NULL AS role")
          . ($usersHasIsActive ? ", is_active" : ", NULL AS is_active")
          . ($usersHasCreatedAt ? ", created_at" : ", NULL AS created_at")
          . " FROM users WHERE id=? AND created_by=? LIMIT 1";
        $detail = fetchOneSafe($pdo, $detailSql, [$detailId, $ownerId]);
      }
    }
    break;

  case 'products':
    if ($productsHasStoreId) {
      $sql = "
        SELECT p.id,
               " . ($productsHasName ? "p.name" : "NULL AS name") . ",
               " . ($productsHasPrice ? "p.price" : "NULL AS price") . ",
               " . ($productsHasIsActive ? "p.is_active" : "NULL AS is_active") . ",
               " . ($productsHasCreatedAt ? "p.created_at" : "NULL AS created_at") . ",
               " . (($productsHasCategoryId && $categoriesHasName) ? "c.name AS category_name" : "NULL AS category_name") . "
        FROM products p
        " . (($productsHasCategoryId && $categoriesOk) ? "LEFT JOIN categories c ON c.id = p.category_id" : "") . "
        WHERE p.store_id=?
      ";
      $params = [$storeId];
      if ($q !== '') {
        $sql .= " AND (" . ($productsHasName ? "p.name LIKE ?" : "1=0")
              . (($productsHasCategoryId && $categoriesHasName) ? " OR c.name LIKE ?" : "")
              . ")";
        $like = '%' . $q . '%';
        $params[] = $like;
        if ($productsHasCategoryId && $categoriesHasName) $params[] = $like;
      }
      $sql .= " ORDER BY p.id DESC LIMIT 200";
      $rows = fetchAllSafe($pdo, $sql, $params);

      if ($detailId > 0) {
        $detail = fetchOneSafe($pdo, "
          SELECT p.*,
                 " . (($productsHasCategoryId && $categoriesHasName) ? "c.name AS category_name" : "NULL AS category_name") . "
          FROM products p
          " . (($productsHasCategoryId && $categoriesOk) ? "LEFT JOIN categories c ON c.id = p.category_id" : "") . "
          WHERE p.id=? AND p.store_id=?
          LIMIT 1
        ", [$detailId, $storeId]);

        if ($detail && $bomRecipesHasProductId) {
          $extraRows = fetchAllSafe($pdo, "
            SELECT br.id, br.name, br.created_at
            FROM bom_recipes br
            WHERE br.product_id=?
            ORDER BY br.id DESC
          ", [$detailId]);
        }
      }
    }
    break;

  case 'categories':
    if ($categoriesHasStoreId) {
      $sql = "SELECT id, name"
        . ($categoriesHasCreatedAt ? ", created_at" : ", NULL AS created_at")
        . " FROM categories WHERE store_id=?";
      $params = [$storeId];
      if ($q !== '') {
        $sql .= " AND name LIKE ?";
        $params[] = '%' . $q . '%';
      }
      $sql .= " ORDER BY id DESC LIMIT 200";
      $rows = fetchAllSafe($pdo, $sql, $params);

      if ($detailId > 0) {
        $detail = fetchOneSafe($pdo, "SELECT * FROM categories WHERE id=? AND store_id=? LIMIT 1", [$detailId, $storeId]);
      }
    }
    break;

  case 'ingredients':
    if ($ingredientsHasStoreId) {
      $sql = "
        SELECT i.id,
               " . ($ingredientsHasName ? "i.name" : "NULL AS name") . ",
               " . ($ingredientsHasStock ? "i.stock" : "NULL AS stock") . ",
               " . ($ingredientsHasUnit ? "i.unit" : "NULL AS unit") . ",
               " . ($ingredientsHasMinStock ? "i.min_stock" : "NULL AS min_stock") . ",
               " . ($ingredientsHasCreatedAt ? "i.created_at" : "NULL AS created_at") . ",
               " . (($ingredientsHasSupplierId && $suppliersHasName) ? "s.name AS supplier_name" : "NULL AS supplier_name") . "
        FROM ingredients i
        " . (($ingredientsHasSupplierId && $suppliersOk) ? "LEFT JOIN suppliers s ON s.id = i.supplier_id" : "") . "
        WHERE i.store_id=?
      ";
      $params = [$storeId];
      if ($q !== '') {
        $sql .= " AND (" . ($ingredientsHasName ? "i.name LIKE ?" : "1=0")
              . (($ingredientsHasSupplierId && $suppliersHasName) ? " OR s.name LIKE ?" : "")
              . ")";
        $like = '%' . $q . '%';
        $params[] = $like;
        if ($ingredientsHasSupplierId && $suppliersHasName) $params[] = $like;
      }
      $sql .= " ORDER BY i.id DESC LIMIT 200";
      $rows = fetchAllSafe($pdo, $sql, $params);

      if ($detailId > 0) {
        $detail = fetchOneSafe($pdo, "
          SELECT i.*,
                 " . (($ingredientsHasSupplierId && $suppliersHasName) ? "s.name AS supplier_name" : "NULL AS supplier_name") . "
          FROM ingredients i
          " . (($ingredientsHasSupplierId && $suppliersOk) ? "LEFT JOIN suppliers s ON s.id = i.supplier_id" : "") . "
          WHERE i.id=? AND i.store_id=?
          LIMIT 1
        ", [$detailId, $storeId]);

        if ($detail && $stockMovementsHasIngredientId) {
          $extraRows = fetchAllSafe($pdo, "
            SELECT id, type, qty, note, created_at
            FROM stock_movements
            WHERE ingredient_id=? AND store_id=?
            ORDER BY id DESC
            LIMIT 50
          ", [$detailId, $storeId]);
        }
      }
    }
    break;

  case 'suppliers':
    if ($suppliersHasStoreId) {
      $sql = "SELECT id, name"
        . ($suppliersHasPhone ? ", phone" : ", NULL AS phone")
        . ($suppliersHasAddress ? ", address" : ", NULL AS address")
        . ($suppliersHasCreatedAt ? ", created_at" : ", NULL AS created_at")
        . " FROM suppliers WHERE store_id=?";
      $params = [$storeId];
      if ($q !== '') {
        $sql .= " AND (name LIKE ?"
              . ($suppliersHasPhone ? " OR phone LIKE ?" : "")
              . ($suppliersHasAddress ? " OR address LIKE ?" : "")
              . ")";
        $like = '%' . $q . '%';
        $params[] = $like;
        if ($suppliersHasPhone) $params[] = $like;
        if ($suppliersHasAddress) $params[] = $like;
      }
      $sql .= " ORDER BY id DESC LIMIT 200";
      $rows = fetchAllSafe($pdo, $sql, $params);

      if ($detailId > 0) {
        $detail = fetchOneSafe($pdo, "SELECT * FROM suppliers WHERE id=? AND store_id=? LIMIT 1", [$detailId, $storeId]);
      }
    }
    break;

  case 'recipes':
    if ($bomRecipesHasStoreId) {
      $sql = "
        SELECT br.id,
               " . ($bomRecipesHasName ? "br.name" : "NULL AS name") . ",
               " . ($bomRecipesHasCreatedAt ? "br.created_at" : "NULL AS created_at") . ",
               " . (($bomRecipesHasProductId && $productsHasName) ? "p.name AS product_name" : "NULL AS product_name") . "
        FROM bom_recipes br
        " . (($bomRecipesHasProductId && $productsOk) ? "LEFT JOIN products p ON p.id = br.product_id" : "") . "
        WHERE br.store_id=?
      ";
      $params = [$storeId];
      if ($q !== '') {
        $sql .= " AND (" . ($bomRecipesHasName ? "br.name LIKE ?" : "1=0")
              . (($bomRecipesHasProductId && $productsHasName) ? " OR p.name LIKE ?" : "")
              . ")";
        $like = '%' . $q . '%';
        $params[] = $like;
        if ($bomRecipesHasProductId && $productsHasName) $params[] = $like;
      }
      $sql .= " ORDER BY br.id DESC LIMIT 200";
      $rows = fetchAllSafe($pdo, $sql, $params);

      if ($detailId > 0) {
        $detail = fetchOneSafe($pdo, "
          SELECT br.*,
                 " . (($bomRecipesHasProductId && $productsHasName) ? "p.name AS product_name" : "NULL AS product_name") . "
          FROM bom_recipes br
          " . (($bomRecipesHasProductId && $productsOk) ? "LEFT JOIN products p ON p.id = br.product_id" : "") . "
          WHERE br.id=? AND br.store_id=?
          LIMIT 1
        ", [$detailId, $storeId]);

        if ($detail && $bomRecipeItemsHasRecipeId) {
          $extraRows = fetchAllSafe($pdo, "
            SELECT bri.id,
                   bri.qty,
                   " . ($bomRecipeItemsHasIngredientId && $ingredientsHasName ? "i.name AS ingredient_name" : "NULL AS ingredient_name") . "
            FROM bom_recipe_items bri
            " . ($bomRecipeItemsHasIngredientId && $ingredientsOk ? "LEFT JOIN ingredients i ON i.id = bri.ingredient_id" : "") . "
            WHERE bri.recipe_id=?
            ORDER BY bri.id DESC
          ", [$detailId]);
        }
      }
    }
    break;

  case 'sales':
    if ($salesHasStoreId) {
      $sql = "
        SELECT s.id,
               " . ($salesHasCreatedAt ? "s.created_at" : "NULL AS created_at") . ",
               " . ($salesHasTotal ? "s.total" : "NULL AS total") . ",
               " . ($salesHasStatus ? "s.status" : "NULL AS status") . ",
               " . (($salesHasKasirId && $usersOk) ? "u.name AS kasir_name" : "NULL AS kasir_name") . "
        FROM sales s
        " . (($salesHasKasirId && $usersOk) ? "LEFT JOIN users u ON u.id = s.kasir_id" : "") . "
        WHERE s.store_id=?
      ";
      $params = [$storeId];
      if ($q !== '') {
        $sql .= " AND (CAST(s.id AS CHAR) LIKE ?"
              . (($salesHasKasirId && $usersOk) ? " OR u.name LIKE ?" : "")
              . ($salesHasStatus ? " OR s.status LIKE ?" : "")
              . ")";
        $like = '%' . $q . '%';
        $params[] = $like;
        if ($salesHasKasirId && $usersOk) $params[] = $like;
        if ($salesHasStatus) $params[] = $like;
      }
      $sql .= " ORDER BY s.id DESC LIMIT 200";
      $rows = fetchAllSafe($pdo, $sql, $params);

      if ($detailId > 0) {
        $detail = fetchOneSafe($pdo, "
          SELECT s.*,
                 " . (($salesHasKasirId && $usersOk) ? "u.name AS kasir_name" : "NULL AS kasir_name") . "
          FROM sales s
          " . (($salesHasKasirId && $usersOk) ? "LEFT JOIN users u ON u.id = s.kasir_id" : "") . "
          WHERE s.id=? AND s.store_id=?
          LIMIT 1
        ", [$detailId, $storeId]);

        if ($detail && $saleItemsHasSaleId) {
          $extraRows = fetchAllSafe($pdo, "
            SELECT si.id,
                   si.qty,
                   " . ($saleItemsHasPrice ? "si.price" : "NULL AS price") . ",
                   " . ($saleItemsHasSubtotal ? "si.subtotal" : "NULL AS subtotal") . ",
                   " . ($saleItemsHasProductId && $productsHasName ? "p.name AS product_name" : "NULL AS product_name") . "
            FROM sale_items si
            " . ($saleItemsHasProductId && $productsOk ? "LEFT JOIN products p ON p.id = si.product_id" : "") . "
            WHERE si.sale_id=?
            ORDER BY si.id DESC
          ", [$detailId]);
        }
      }
    }
    break;

  case 'stock':
    if ($stockMovementsHasStoreId) {
      $sql = "
        SELECT sm.id,
               " . ($stockMovementsHasType ? "sm.type" : "NULL AS type") . ",
               " . ($stockMovementsHasQty ? "sm.qty" : "NULL AS qty") . ",
               " . ($stockMovementsHasNote ? "sm.note" : "NULL AS note") . ",
               " . ($stockMovementsHasCreatedAt ? "sm.created_at" : "NULL AS created_at") . ",
               " . (($stockMovementsHasIngredientId && $ingredientsHasName) ? "i.name AS ingredient_name" : "NULL AS ingredient_name") . "
        FROM stock_movements sm
        " . (($stockMovementsHasIngredientId && $ingredientsOk) ? "LEFT JOIN ingredients i ON i.id = sm.ingredient_id" : "") . "
        WHERE sm.store_id=?
      ";
      $params = [$storeId];
      if ($q !== '') {
        $sql .= " AND (" . ($stockMovementsHasType ? "sm.type LIKE ?" : "1=0")
              . (($stockMovementsHasIngredientId && $ingredientsHasName) ? " OR i.name LIKE ?" : "")
              . ($stockMovementsHasNote ? " OR sm.note LIKE ?" : "")
              . ")";
        $like = '%' . $q . '%';
        $params[] = $like;
        if ($stockMovementsHasIngredientId && $ingredientsHasName) $params[] = $like;
        if ($stockMovementsHasNote) $params[] = $like;
      }
      $sql .= " ORDER BY sm.id DESC LIMIT 200";
      $rows = fetchAllSafe($pdo, $sql, $params);

      if ($detailId > 0) {
        $detail = fetchOneSafe($pdo, "
          SELECT sm.*,
                 " . (($stockMovementsHasIngredientId && $ingredientsHasName) ? "i.name AS ingredient_name" : "NULL AS ingredient_name") . "
          FROM stock_movements sm
          " . (($stockMovementsHasIngredientId && $ingredientsOk) ? "LEFT JOIN ingredients i ON i.id = sm.ingredient_id" : "") . "
          WHERE sm.id=? AND sm.store_id=?
          LIMIT 1
        ", [$detailId, $storeId]);
      }
    }
    break;

  case 'logs':
    if ($logsOk) {
      $sql = "
        SELECT al.id,
               al.created_at,
               al.action,
               al.message,
               " . ($logsJoinActor ? "u.name AS actor_name" : "NULL AS actor_name") . "
        FROM activity_logs al
        " . ($logsJoinActor ? "LEFT JOIN users u ON u.id = al.actor_user_id" : "") . "
        WHERE al.store_id=?
      ";
      $params = [$storeId];
      if ($q !== '') {
        $sql .= " AND (al.action LIKE ? OR al.message LIKE ?" . ($logsJoinActor ? " OR u.name LIKE ?" : "") . ")";
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
        if ($logsJoinActor) $params[] = $like;
      }
      $sql .= " ORDER BY al.id DESC LIMIT 200";
      $rows = fetchAllSafe($pdo, $sql, $params);

      if ($detailId > 0) {
        $detail = fetchOneSafe($pdo, "
          SELECT al.*,
                 " . ($logsJoinActor ? "u.name AS actor_name" : "NULL AS actor_name") . "
          FROM activity_logs al
          " . ($logsJoinActor ? "LEFT JOIN users u ON u.id = al.actor_user_id" : "") . "
          WHERE al.id=? AND al.store_id=?
          LIMIT 1
        ", [$detailId, $storeId]);
      }
    }
    break;

  case 'overview':
  default:
    $rows = [];
    break;
}

/* =====================
   Export CSV
===================== */
if ($export === 'csv' && $tab !== 'overview') {
  $filename = 'store-' . $storeId . '-' . $tab . '.csv';
  csvDownload($filename, $rows);
}

/* =====================
   Overview data
===================== */
$recentShifts = [];
$recentLogs = [];
$lastActivity = null;

if ($shiftsOk) {
  $shiftCols = ["cs.id"];
  if ($shiftsHasStatus) $shiftCols[] = "cs.status";
  if ($shiftsHasOpenedAt) $shiftCols[] = "cs.opened_at";
  if ($shiftsHasClosedAt) $shiftCols[] = "cs.closed_at";
  if ($shiftsHasKasirId) $shiftCols[] = "cs.kasir_id";
  if ($shiftsHasCash) $shiftCols[] = "cs.opening_cash";
  $shiftJoin = "";
  if ($shiftsHasKasirId) {
    $shiftJoin .= " LEFT JOIN users k ON k.id = cs.kasir_id ";
    $shiftCols[] = "k.name AS kasir_name";
  }
  $recentShifts = fetchAllSafe(
    $pdo,
    "SELECT " . implode(', ', $shiftCols) . " FROM cashier_shifts cs $shiftJoin WHERE cs.store_id=? ORDER BY cs.id DESC LIMIT 10",
    [$storeId]
  );
}

if ($logsOk) {
  $recentLogs = fetchAllSafe(
    $pdo,
    "SELECT al.id, al.created_at, al.action, al.message,
            " . ($logsJoinActor ? "u.name AS actor_name" : "NULL AS actor_name") . "
     FROM activity_logs al
     " . ($logsJoinActor ? "LEFT JOIN users u ON u.id = al.actor_user_id" : "") . "
     WHERE al.store_id=?
     ORDER BY al.id DESC
     LIMIT 10",
    [$storeId]
  );
  if ($recentLogs) $lastActivity = $recentLogs[0];
}

$flags = [];
if (!$ownerId) $flags[] = ['warn', 'Store belum punya <code>owner_admin_id</code>.'];
if ($ownerId && !$owner) $flags[] = ['bad', 'Owner admin orphan: user owner tidak ditemukan.'];
if ((int)$kpi['open_shifts'] > 0) $flags[] = ['warn', "Masih ada <b>{$kpi['open_shifts']}</b> shift <code>open</code>."];
if ($kpi['users_inactive'] > 0) $flags[] = ['warn', "Ada <b>{$kpi['users_inactive']}</b> user nonaktif pada store ini."];
if ($logsOk && $kpi['logs'] === 0) $flags[] = ['warn', 'Belum ada activity_logs untuk store ini.'];

$health = 'ok';
foreach ($flags as $f) {
  if ($f[0] === 'bad') {
    $health = 'bad';
    break;
  }
}
if ($health !== 'bad') {
  foreach ($flags as $f) {
    if ($f[0] === 'warn') {
      $health = 'warn';
      break;
    }
  }
}

function buildTabUrl(int $storeId, string $tab, string $q = '', int $detail = 0, string $extra = ''): string {
  $params = ['id' => $storeId, 'tab' => $tab];
  if ($q !== '') $params['q'] = $q;
  if ($detail > 0) $params['detail'] = $detail;
  if ($extra !== '') $params['export'] = $extra;
  return 'dev_store_detail.php?' . http_build_query($params);
}
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Dev · Store Monitor</title>
  <link rel="stylesheet" href="../publik/assets/dev.css">
  <style>
    .card.full{grid-column:span 12}
    .pill{display:inline-block;font-size:12px;padding:3px 8px;border:1px solid var(--line);border-radius:999px;color:var(--muted)}
    .notice{margin-top:12px;padding:10px 12px;border:1px solid var(--line);border-radius:12px}
    .notice.ok{background:rgba(0,128,0,.06)}
    .notice.warn{background:rgba(220,140,0,.07)}
    .notice.bad{background:rgba(200,0,0,.07)}
    .headRow{display:flex;gap:10px;justify-content:space-between;align-items:flex-start;flex-wrap:wrap}
    .headTitle{min-width:240px}
    .headBadge{white-space:nowrap}
    .gridKpi,.gridKpi2{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-top:12px}
    @media(max-width:1100px){.gridKpi,.gridKpi2{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:560px){
      .gridKpi,.gridKpi2{grid-template-columns:1fr}
      .headRow{flex-direction:column;align-items:stretch}
      .headBadge{align-self:flex-start}
    }
    .kcard{border:1px solid var(--line);border-radius:14px;padding:12px;min-width:0}
    .kcard b{font-size:22px}
    .muted,.smallDim{color:var(--muted);font-size:12px}
    .ownerActions{margin-top:12px;display:flex;flex-wrap:wrap;gap:8px}
    .btnDangerSolid{
      display:inline-flex;align-items:center;justify-content:center;
      padding:10px 14px;border:none;border-radius:10px;background:#dc2626;color:#fff;
      cursor:pointer;font-weight:600;
    }
    .btnDangerSolid:hover{opacity:.95}
    .tabs{display:flex;gap:8px;flex-wrap:wrap;margin-top:16px}
    .tabLink{
      display:inline-flex;align-items:center;justify-content:center;
      padding:10px 14px;border:1px solid var(--line);border-radius:999px;
      text-decoration:none;color:inherit;background:transparent;
    }
    .tabLink.active{background:rgba(79,70,229,.12);border-color:rgba(79,70,229,.35);font-weight:700}
    .toolbar{
      margin-top:14px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:space-between;
    }
    .toolbarLeft{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
    .toolbar form{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
    .toolbar input{
      min-width:260px;padding:10px 12px;border:1px solid var(--line);border-radius:10px;background:transparent;color:inherit;
    }
    .tblWrap{overflow:auto;border:1px solid var(--line);border-radius:14px;margin-top:10px}
    .tbl{width:100%;border-collapse:collapse;min-width:860px}
    .tbl th,.tbl td{border-bottom:1px solid var(--line);padding:10px;vertical-align:top;text-align:left}
    .tbl th{font-size:12px;color:var(--muted);white-space:nowrap}
    .right{text-align:right}
    .detailCard{
      margin-top:14px;border:1px solid var(--line);border-radius:14px;padding:14px;
    }
    .detailGrid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
    @media(max-width:720px){.detailGrid{grid-template-columns:1fr}}
    .kv{padding:10px;border:1px solid var(--line);border-radius:12px}
    .kv .k{display:block;font-size:12px;color:var(--muted);margin-bottom:6px}
    .sectionTitle{margin-top:18px;display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:wrap}
    .danger{border:1px solid rgba(200,0,0,.35);border-radius:14px;padding:12px;margin-top:12px}
    .danger .btnDanger[disabled]{opacity:.55;cursor:not-allowed}
    .dangerGrid{display:grid;grid-template-columns:1fr 220px 180px;gap:10px;align-items:end}
    @media(max-width:720px){
      .dangerGrid{grid-template-columns:1fr}
      .dangerGrid .btn{width:100%;justify-content:center}
      .dangerGrid input{width:100%}
    }
  </style>
</head>
<body>
<div class="container">
  <div class="topbar">
    <div class="brand">
      <b>Dev · Store Monitor</b>
      <span><?= h($u['name']) ?> · <?= h($u['email'] ?? '') ?></span>
    </div>
    <div class="actions">
      <a class="btn" href="dev_stores.php">← Back</a>
      <button class="btn" id="themeBtn" type="button">Toggle Theme</button>
    </div>
  </div>

  <div class="grid">
    <section class="card full">

      <div class="headRow">
        <div class="headTitle">
          <h3 style="margin:0">
            <?= $storesHasName ? h($store['name'] ?? 'Store') : 'Store' ?>
            <span class="pill">store#<?= (int)$storeId ?></span>
          </h3>
          <div class="small">Developer explorer: tab, search, detail, export CSV.</div>
        </div>

        <span class="pill headBadge">
          Health: <b><?= $health === 'ok' ? 'OK' : ($health === 'warn' ? 'WARN' : 'BAD') ?></b>
        </span>
      </div>

      <?php if ($ok): ?><div class="notice ok"><b>OK:</b> <?= h($ok) ?></div><?php endif; ?>
      <?php if ($err): ?><div class="notice bad"><b>Error:</b> <?= h($err) ?></div><?php endif; ?>
      <?php if ($flags): foreach ($flags as $f): ?>
        <div class="notice <?= h($f[0]) ?>"><?= $f[1] ?></div>
      <?php endforeach; endif; ?>

      <div class="gridKpi">
        <div class="kcard">
          <div class="muted">Owner</div>
          <b style="font-size:16px"><?= h($owner['name'] ?? '-') ?></b>
          <div class="muted"><?= h($owner['email'] ?? '') ?><?= $ownerId ? ' · admin#' . (int)$ownerId : '' ?></div>
          <?php if ($ownerId > 0 && $owner): ?>
            <div class="ownerActions">
              <form method="post" action="dev_reset_admin.php" onsubmit="return confirm('Yakin reset password admin ini?');" style="margin:0;">
                <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="admin_id" value="<?= (int)$ownerId ?>">
                <input type="hidden" name="store_id" value="<?= (int)$storeId ?>">
                <button type="submit" class="btnDangerSolid">Reset Password Admin</button>
              </form>
            </div>
          <?php endif; ?>
        </div>

        <div class="kcard">
          <div class="muted">Users</div>
          <b><?= (int)$kpi['users_total'] ?></b>
          <div class="muted">Admin <?= (int)$kpi['users_admin'] ?> · Kasir <?= (int)$kpi['users_kasir'] ?> · Dapur <?= (int)$kpi['users_dapur'] ?></div>
        </div>

        <div class="kcard">
          <div class="muted">Products</div>
          <b><?= (int)$kpi['products'] ?></b>
          <div class="muted">Categories <?= (int)$kpi['categories'] ?></div>
        </div>

        <div class="kcard">
          <div class="muted">Ingredients</div>
          <b><?= (int)$kpi['ingredients'] ?></b>
          <div class="muted">Suppliers <?= (int)$kpi['suppliers'] ?></div>
        </div>
      </div>

      <div class="gridKpi2">
        <div class="kcard">
          <div class="muted">Recipes / BOM</div>
          <b><?= (int)$kpi['recipes'] ?></b>
          <div class="muted">Read-only</div>
        </div>

        <div class="kcard">
          <div class="muted">Sales</div>
          <b><?= (int)$kpi['sales'] ?></b>
          <div class="muted">Recent sales visible</div>
        </div>

        <div class="kcard">
          <div class="muted">Shifts</div>
          <b><?= (int)$kpi['shifts'] ?></b>
          <div class="muted"><?= $shiftsHasStatus ? ((int)$kpi['open_shifts'] . ' open') : 'status N/A' ?></div>
        </div>

        <div class="kcard">
          <div class="muted">Logs</div>
          <b><?= (int)$kpi['logs'] ?></b>
          <div class="muted"><?= h($lastActivity['action'] ?? '-') ?></div>
        </div>
      </div>

      <div class="tabs">
        <?php foreach ($allowedTabs as $tabItem): ?>
          <a class="tabLink <?= $tab === $tabItem ? 'active' : '' ?>" href="<?= h(buildTabUrl($storeId, $tabItem)) ?>">
            <?= h(ucfirst($tabItem)) ?>
          </a>
        <?php endforeach; ?>
      </div>

      <?php if ($tab !== 'overview'): ?>
        <div class="toolbar">
          <div class="toolbarLeft">
            <form method="get">
              <input type="hidden" name="id" value="<?= (int)$storeId ?>">
              <input type="hidden" name="tab" value="<?= h($tab) ?>">
              <input type="text" name="q" value="<?= h($q) ?>" placeholder="Cari data pada tab ini...">
              <button class="btn" type="submit">Search</button>
              <a class="btn" href="<?= h(buildTabUrl($storeId, $tab)) ?>">Reset</a>
            </form>
          </div>
          <div>
            <a class="btn" href="<?= h(buildTabUrl($storeId, $tab, $q, 0, 'csv')) ?>">Export CSV</a>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($tab === 'overview'): ?>

        <div class="sectionTitle">
          <h4 style="margin:0">Overview Ringkas</h4>
          <span class="pill">read-only</span>
        </div>

        <div class="tblWrap">
          <table class="tbl" style="min-width:700px">
            <thead>
              <tr>
                <th>Modul</th>
                <th>Jumlah</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr><td>Users</td><td><?= (int)$kpi['users_total'] ?></td><td><?= $kpi['users_inactive'] > 0 ? 'Ada nonaktif' : 'Normal' ?></td></tr>
              <tr><td>Products</td><td><?= (int)$kpi['products'] ?></td><td><?= (int)$kpi['products'] > 0 ? 'Ada data' : 'Kosong' ?></td></tr>
              <tr><td>Ingredients</td><td><?= (int)$kpi['ingredients'] ?></td><td><?= (int)$kpi['ingredients'] > 0 ? 'Ada data' : 'Kosong' ?></td></tr>
              <tr><td>Recipes</td><td><?= (int)$kpi['recipes'] ?></td><td><?= (int)$kpi['recipes'] > 0 ? 'Ada data' : 'Kosong' ?></td></tr>
              <tr><td>Sales</td><td><?= (int)$kpi['sales'] ?></td><td><?= (int)$kpi['sales'] > 0 ? 'Ada data' : 'Kosong' ?></td></tr>
              <tr><td>Logs</td><td><?= (int)$kpi['logs'] ?></td><td><?= (int)$kpi['logs'] > 0 ? 'Audit ada' : 'Audit minim' ?></td></tr>
            </tbody>
          </table>
        </div>

        <div class="sectionTitle">
          <h4 style="margin:0">Recent Shifts</h4>
          <span class="pill"><?= count($recentShifts) ?> rows</span>
        </div>
        <div class="tblWrap">
          <table class="tbl">
            <thead>
              <tr>
                <th>Shift</th>
                <th>Status</th>
                <th>Kasir</th>
                <th>Opened</th>
                <th>Closed</th>
                <th class="right">Opening Cash</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$recentShifts): ?>
                <tr><td colspan="6" class="smallDim">Belum ada shift.</td></tr>
              <?php else: foreach ($recentShifts as $r): ?>
                <tr>
                  <td>#<?= (int)$r['id'] ?></td>
                  <td><?= h($r['status'] ?? '-') ?></td>
                  <td><?= h($r['kasir_name'] ?? '-') ?></td>
                  <td><?= h($r['opened_at'] ?? '-') ?></td>
                  <td><?= h($r['closed_at'] ?? '-') ?></td>
                  <td class="right"><?= money_idr($r['opening_cash'] ?? 0) ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>

        <div class="sectionTitle">
          <h4 style="margin:0">Recent Logs</h4>
          <span class="pill"><?= count($recentLogs) ?> rows</span>
        </div>
        <div class="tblWrap">
          <table class="tbl">
            <thead>
              <tr>
                <th>ID</th>
                <th>Waktu</th>
                <th>Action</th>
                <th>Actor</th>
                <th>Message</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$recentLogs): ?>
                <tr><td colspan="5" class="smallDim">Belum ada log.</td></tr>
              <?php else: foreach ($recentLogs as $r): ?>
                <tr>
                  <td>#<?= (int)$r['id'] ?></td>
                  <td><?= h($r['created_at'] ?? '-') ?></td>
                  <td><?= h($r['action'] ?? '-') ?></td>
                  <td><?= h($r['actor_name'] ?? '-') ?></td>
                  <td><?= h($r['message'] ?? '-') ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>

      <?php else: ?>

        <?php if ($detail): ?>
          <div class="detailCard">
            <div class="sectionTitle" style="margin-top:0">
              <h4 style="margin:0">Detail <?= h(ucfirst($tab)) ?> #<?= (int)($detail['id'] ?? 0) ?></h4>
              <a class="btn" href="<?= h(buildTabUrl($storeId, $tab, $q)) ?>">Tutup Detail</a>
            </div>

            <div class="detailGrid">
              <?php foreach ($detail as $k => $v): ?>
                <div class="kv">
                  <span class="k"><?= h((string)$k) ?></span>
                  <div><?= h((string)$v) ?></div>
                </div>
              <?php endforeach; ?>
            </div>

            <?php if ($extraRows): ?>
              <div class="sectionTitle">
                <h4 style="margin:0">Data Terkait</h4>
                <span class="pill"><?= count($extraRows) ?> rows</span>
              </div>
              <div class="tblWrap">
                <table class="tbl">
                  <thead>
                    <tr>
                      <?php foreach (array_keys($extraRows[0]) as $col): ?>
                        <th><?= h($col) ?></th>
                      <?php endforeach; ?>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($extraRows as $er): ?>
                      <tr>
                        <?php foreach ($er as $val): ?>
                          <td><?= h((string)$val) ?></td>
                        <?php endforeach; ?>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <div class="sectionTitle">
          <h4 style="margin:0"><?= h(ucfirst($tab)) ?> List</h4>
          <span class="pill"><?= count($rows) ?> rows</span>
        </div>

        <div class="tblWrap">
          <table class="tbl">
            <thead>
              <tr>
                <?php if ($rows): ?>
                  <?php foreach (array_keys($rows[0]) as $col): ?>
                    <th><?= h($col) ?></th>
                  <?php endforeach; ?>
                  <th>Detail</th>
                <?php else: ?>
                  <th>Data</th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php if (!$rows): ?>
                <tr>
                  <td class="smallDim">Tidak ada data pada tab ini atau query tidak menemukan hasil.</td>
                </tr>
              <?php else: foreach ($rows as $r): ?>
                <tr>
                  <?php foreach ($r as $key => $val): ?>
                    <td>
                      <?php
                        if (in_array($key, ['price','total','subtotal'], true) && $val !== null && $val !== '') {
                          echo h(money_idr($val));
                        } elseif ($key === 'is_active' && $val !== null && $val !== '') {
                          echo ((int)$val === 1 ? 'Aktif' : 'Nonaktif');
                        } else {
                          echo h((string)$val);
                        }
                      ?>
                    </td>
                  <?php endforeach; ?>
                  <td>
                    <a class="btn" href="<?= h(buildTabUrl($storeId, $tab, $q, (int)($r['id'] ?? 0))) ?>">Detail</a>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>

      <?php endif; ?>

      <h4 style="margin-top:18px">Danger Zone</h4>
      <div class="small">
        Hapus permanen akan menghapus <b>store</b>, semua data dengan <code>store_id</code>, dan juga akun owner + kasir/dapur terkait.
      </div>

      <?php if ((int)$kpi['open_shifts'] > 0): ?>
        <div class="notice bad" style="margin-top:10px">
          <b>Delete diblokir:</b> masih ada <b><?= (int)$kpi['open_shifts'] ?></b> shift <code>open</code>. Tutup shift dulu.
        </div>
      <?php endif; ?>

      <form method="post" action="dev_store_delete.php" class="danger">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="store_id" value="<?= (int)$storeId ?>">

        <div class="dangerGrid">
          <div>
            <label class="small" style="display:block;margin-bottom:6px">Ketik <b>HAPUS</b></label>
            <input name="confirm" placeholder="HAPUS" required <?= ((int)$kpi['open_shifts'] > 0) ? 'disabled' : '' ?>>
          </div>
          <div>
            <label class="small" style="display:block;margin-bottom:6px">Masukkan store_id (<?= (int)$storeId ?>)</label>
            <input name="confirm2" placeholder="<?= (int)$storeId ?>" required <?= ((int)$kpi['open_shifts'] > 0) ? 'disabled' : '' ?>>
          </div>
          <div style="text-align:right">
            <button class="btn btnDanger" type="submit" <?= ((int)$kpi['open_shifts'] > 0) ? 'disabled' : '' ?>>
              Delete Permanently
            </button>
          </div>
        </div>

        <div class="small" style="margin-top:10px;color:var(--muted)">
          Aksi ini <b>tidak bisa dibatalkan</b>.
        </div>
      </form>

    </section>
  </div>
</div>

<script>
(() => {
  const root = document.documentElement;
  const saved = localStorage.getItem('mp_theme');
  if (saved) root.dataset.theme = saved;

  const toggle = () => {
    const next = root.dataset.theme === 'light' ? '' : 'light';
    if (next) root.dataset.theme = next; else delete root.dataset.theme;
    localStorage.setItem('mp_theme', root.dataset.theme || '');
  };

  const btn = document.getElementById('themeBtn');
  if (btn) btn.addEventListener('click', toggle);
})();
</script>
</body>
</html>
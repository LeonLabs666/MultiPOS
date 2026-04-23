<?php
declare(strict_types=1);

require __DIR__ . '/../../config/db.php';
require __DIR__ . '/../../config/inventory.php';

echo "Mulai recalculation inventory metrics...\n\n";

/**
 * Ambil semua store aktif
 */
$stores = $pdo->query("
  SELECT id, name
  FROM stores
  WHERE is_active = 1
")->fetchAll(PDO::FETCH_ASSOC);

$totalIngredients = 0;

foreach ($stores as $store) {

  $storeId = (int)$store['id'];
  $storeName = $store['name'];

  echo "Store: {$storeName}\n";

  $q = $pdo->prepare("
    SELECT id, name
    FROM ingredients
    WHERE store_id = ?
      AND is_active = 1
    ORDER BY name
  ");
  $q->execute([$storeId]);

  $ingredients = $q->fetchAll(PDO::FETCH_ASSOC);

  foreach ($ingredients as $ing) {

    $ingredientId = (int)$ing['id'];
    $name = $ing['name'];

    inv_recalc_ingredient_metrics($pdo, $storeId, $ingredientId);

    echo "  ✓ {$name}\n";

    $totalIngredients++;
  }

  echo "\n";
}

echo "Selesai.\n";
echo "Total bahan direcalculate: {$totalIngredients}\n";
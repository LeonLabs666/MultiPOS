<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/inventory.php';
require_role(['admin']);

$appName = 'MultiPOS';
$pageTitle = 'Daftar Bahan';
$activeMenu = 'persediaan';

$adminId = (int)auth_user()['id'];

/** Ambil store admin (single-store) */
$st = $pdo->prepare("SELECT id, name FROM stores WHERE owner_admin_id=? AND is_active=1 LIMIT 1");
$st->execute([$adminId]);
$store = $st->fetch(PDO::FETCH_ASSOC);
if (!$store) {
  http_response_code(400);
  exit('Admin belum terhubung ke toko.');
}
$storeId = (int)$store['id'];

$error = (string)($_GET['err'] ?? '');
$ok    = (string)($_GET['ok'] ?? '');
$statusFilter = strtolower(trim((string)($_GET['status'] ?? 'semua')));
$allowedStatuses = ['semua', 'aman', 'restock', 'kritis', 'habis'];
if (!in_array($statusFilter, $allowedStatuses, true)) {
  $statusFilter = 'semua';
}

/** POST handlers */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $act = (string)($_POST['action'] ?? '');

  if ($act === 'create') {
    $name = trim((string)($_POST['name'] ?? ''));
    $unit = trim((string)($_POST['unit'] ?? 'pcs'));
    $minStock = inv_clamp_dec($_POST['min_stock'] ?? 0);
    $safetyStock = inv_clamp_dec($_POST['safety_stock'] ?? 0);
    $leadTimeDays = max(1, (int)($_POST['lead_time_days'] ?? 1));
    $stock = inv_clamp_dec($_POST['stock'] ?? 0);

    if ($name === '') {
      header('Location: admin_persediaan_bahan.php?err=' . urlencode('Nama bahan wajib diisi.'));
      exit;
    }

    if ($unit === '') $unit = 'pcs';
    if (strlen($unit) > 20) $unit = substr($unit, 0, 20);
    if (strlen($name) > 120) $name = substr($name, 0, 120);

    try {
      $pdo->prepare("
        INSERT INTO ingredients (
          store_id, name, unit, stock, min_stock,
          safety_stock, lead_time_days,
          avg_daily_usage, reorder_point, suggested_restock_qty,
          is_active, created_at, updated_at
        )
        VALUES (?,?,?,?,?,?,?,?,?,?,1,NOW(),NOW())
      ")->execute([
        $storeId,
        $name,
        $unit,
        $stock,
        $minStock,
        $safetyStock,
        $leadTimeDays,
        0,
        $safetyStock,
        $safetyStock
      ]);

      $ingredientId = (int)$pdo->lastInsertId();
      if ($ingredientId > 0) {
        inv_bootstrap_ingredient_metrics($pdo, $storeId, $ingredientId);
      }

      header('Location: admin_persediaan_bahan.php?ok=' . urlencode('Bahan berhasil ditambahkan.'));
      exit;
    } catch (PDOException $ex) {
      $msg = $ex->getMessage();
      $err = (str_contains($msg, 'Duplicate') || str_contains($msg, 'uk_store_name'))
        ? 'Nama bahan sudah ada.'
        : 'Gagal menambahkan bahan.';
      header('Location: admin_persediaan_bahan.php?err=' . urlencode($err));
      exit;
    }
  }

  if ($act === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    $row = inv_ingredient_of_store($pdo, $id, $storeId);
    if (!$row) {
      header('Location: admin_persediaan_bahan.php?err=' . urlencode('Data bahan tidak valid.'));
      exit;
    }

    $name = trim((string)($_POST['name'] ?? ''));
    $unit = trim((string)($_POST['unit'] ?? 'pcs'));
    $minStock = inv_clamp_dec($_POST['min_stock'] ?? 0);
    $safetyStock = inv_clamp_dec($_POST['safety_stock'] ?? 0);
    $leadTimeDays = max(1, (int)($_POST['lead_time_days'] ?? 1));
    $active = ((int)($_POST['is_active'] ?? 1) === 1) ? 1 : 0;

    if ($name === '') {
      header('Location: admin_persediaan_bahan.php?edit=' . $id . '&err=' . urlencode('Nama bahan wajib diisi.'));
      exit;
    }

    if ($unit === '') $unit = 'pcs';
    if (strlen($unit) > 20) $unit = substr($unit, 0, 20);
    if (strlen($name) > 120) $name = substr($name, 0, 120);

    try {
      $pdo->prepare("
        UPDATE ingredients
        SET name=?, unit=?, min_stock=?, safety_stock=?, lead_time_days=?, is_active=?, updated_at=NOW()
        WHERE id=? AND store_id=?
      ")->execute([
        $name,
        $unit,
        $minStock,
        $safetyStock,
        $leadTimeDays,
        $active,
        $id,
        $storeId
      ]);

      inv_recalc_ingredient_metrics($pdo, $storeId, $id);

      header('Location: admin_persediaan_bahan.php?ok=' . urlencode('Bahan berhasil diupdate.'));
      exit;
    } catch (PDOException $ex) {
      $msg = $ex->getMessage();
      $err = (str_contains($msg, 'Duplicate') || str_contains($msg, 'uk_store_name'))
        ? 'Nama bahan sudah ada.'
        : 'Gagal update bahan.';
      header('Location: admin_persediaan_bahan.php?edit=' . $id . '&err=' . urlencode($err));
      exit;
    }
  }

  if ($act === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $row = inv_ingredient_of_store($pdo, $id, $storeId);
    if (!$row) {
      header('Location: admin_persediaan_bahan.php?err=' . urlencode('Data bahan tidak valid.'));
      exit;
    }

    $chk = $pdo->prepare("
      SELECT COUNT(*)
      FROM bom_recipe_items bri
      JOIN bom_recipes br ON br.id = bri.recipe_id
      WHERE bri.ingredient_id=? AND br.store_id=?
    ");
    $chk->execute([$id, $storeId]);
    $cnt = (int)($chk->fetchColumn() ?: 0);

    if ($cnt > 0) {
      header('Location: admin_persediaan_bahan.php?err=' . urlencode(
        "Bahan tidak bisa dihapus karena masih dipakai di $cnt resep. Hapus dulu dari resep terkait."
      ));
      exit;
    }

    try {
      $pdo->beginTransaction();

      $pdo->prepare("DELETE FROM ingredient_unit_conversions WHERE ingredient_id=? AND store_id=?")
        ->execute([$id, $storeId]);

      $pdo->prepare("DELETE FROM ingredients WHERE id=? AND store_id=? LIMIT 1")
        ->execute([$id, $storeId]);

      $pdo->commit();

      header('Location: admin_persediaan_bahan.php?ok=' . urlencode('Bahan dihapus permanen.'));
      exit;
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      header('Location: admin_persediaan_bahan.php?err=' . urlencode('Gagal menghapus bahan: ' . $e->getMessage()));
      exit;
    }
  }

  header('Location: admin_persediaan_bahan.php');
  exit;
}

$editId  = (int)($_GET['edit'] ?? 0);
$editRow = $editId ? inv_ingredient_of_store($pdo, $editId, $storeId) : null;
$q = trim((string)($_GET['q'] ?? ''));
$qLike = '%' . $q . '%';

$params = [$storeId];
$sql = "
  SELECT
    id, name, stock, min_stock, safety_stock, lead_time_days,
    avg_daily_usage, reorder_point, suggested_restock_qty,
    unit, is_active,
    COALESCE(updated_at, created_at) AS last_at
  FROM ingredients
  WHERE store_id=? AND is_active=1
";

if ($q !== '') {
  $sql .= " AND name LIKE ?";
  $params[] = $qLike;
}

$sql .= " ORDER BY name ASC";
$list = $pdo->prepare($sql);
$list->execute($params);
$rawRows = $list->fetchAll(PDO::FETCH_ASSOC);

$rows = [];
$stats = [
  'semua' => 0,
  'aman' => 0,
  'restock' => 0,
  'kritis' => 0,
  'habis' => 0,
];

foreach ($rawRows as $r) {
  $stock = (float)$r['stock'];
  $safety = (float)$r['safety_stock'];
  $rop = (float)$r['reorder_point'];
  $avg = (float)$r['avg_daily_usage'];
  $suggested = (float)$r['suggested_restock_qty'];
  $status = inv_get_ingredient_stock_status($stock, $safety, $rop);
  $statusKey = (string)($status['key'] ?? 'aman');

  $r['status'] = $status;
  $r['stock_txt'] = rtrim(rtrim(number_format($stock, 3, '.', ''), '0'), '.');
  $r['safety_txt'] = rtrim(rtrim(number_format($safety, 3, '.', ''), '0'), '.');
  $r['rop_txt'] = rtrim(rtrim(number_format($rop, 3, '.', ''), '0'), '.');
  $r['avg_txt'] = rtrim(rtrim(number_format($avg, 3, '.', ''), '0'), '.');
  $r['suggested_txt'] = rtrim(rtrim(number_format($suggested, 3, '.', ''), '0'), '.');

  $stats['semua']++;
  if (isset($stats[$statusKey])) {
    $stats[$statusKey]++;
  }

  if ($statusFilter !== 'semua' && $statusKey !== $statusFilter) {
    continue;
  }

  $rows[] = $r;
}

$statusLabels = [
  'semua' => 'Semua',
  'aman' => 'Aman',
  'restock' => 'Restock',
  'kritis' => 'Kritis',
  'habis' => 'Habis',
];

require __DIR__ . '/../publik/partials/admin_layout_top.php';
?>

<style>
  .bahan-page{
    --bg-soft:#f8fafc;
    --line:#e2e8f0;
    --line-strong:#cbd5e1;
    --text:#0f172a;
    --muted:#64748b;
    --primary:#0f172a;
    --primary-soft:#e2e8f0;
    --blue:#2563eb;
    --blue-dark:#1d4ed8;
    --success:#166534;
    --success-bg:#ecfdf5;
    --success-line:#bbf7d0;
    --warn:#92400e;
    --warn-bg:#fffbeb;
    --warn-line:#fde68a;
    --danger:#b91c1c;
    --danger-bg:#fef2f2;
    --danger-line:#fecaca;
    --dark-bg:#111827;
  }

  .bahan-page .wrap{
    max-width:1380px;
    margin:0 auto;
  }

  .bahan-page h1{
    margin:0;
    font-size:52px;
    line-height:1.05;
    color:var(--text);
  }

  .bahan-page h3{
    margin:0;
    font-size:20px;
    line-height:1.2;
    color:var(--text);
  }

  .bahan-page .sub{
    margin:12px 0 0;
    color:var(--muted);
    font-size:15px;
    line-height:1.6;
  }

  .bahan-page .page-head{
    display:flex;
    justify-content:space-between;
    gap:16px;
    align-items:flex-start;
    margin-bottom:18px;
  }

  .bahan-page .grid{
    display:grid;
    grid-template-columns:minmax(320px, 420px) minmax(0, 1fr);
    gap:20px;
    align-items:start;
  }

  .bahan-page .panel{
    background:#fff;
    border:1px solid var(--line);
    border-radius:24px;
    box-shadow:0 10px 30px rgba(15, 23, 42, 0.04);
  }

  .bahan-page .panel-head{
    padding:20px 20px 0;
    display:flex;
    justify-content:space-between;
    gap:16px;
    align-items:flex-start;
  }

  .bahan-page .panel-body{
    padding:20px;
  }

  .bahan-page .panel-note{
    margin:6px 0 0;
    color:var(--muted);
    font-size:13px;
    line-height:1.5;
  }

  .bahan-page .sticky-form{
    position:sticky;
    top:24px;
  }

  .bahan-page .alert{
    margin:0 0 18px;
    padding:14px 16px;
    border-radius:16px;
    border:1px solid;
    font-size:14px;
    line-height:1.5;
  }

  .bahan-page .alert-error{
    background:var(--danger-bg);
    color:var(--danger);
    border-color:var(--danger-line);
  }

  .bahan-page .alert-success{
    background:var(--success-bg);
    color:var(--success);
    border-color:var(--success-line);
  }

  .bahan-page label{
    display:block;
    margin:0 0 8px;
    font-size:13px;
    font-weight:700;
    color:#334155;
  }

  .bahan-page .field + .field{
    margin-top:14px;
  }

  .bahan-page .row2{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:14px;
  }

  .bahan-page input,
  .bahan-page select{
    width:100%;
    padding:12px 14px;
    border-radius:14px;
    border:1px solid var(--line);
    background:#fff;
    font-size:14px;
    color:var(--text);
    outline:none;
    transition:border-color .15s ease, box-shadow .15s ease;
    box-sizing:border-box;
  }

  .bahan-page input:focus,
  .bahan-page select:focus{
    border-color:#93c5fd;
    box-shadow:0 0 0 4px rgba(37, 99, 235, 0.10);
  }

  .bahan-page .btn,
  .bahan-page .btn-ghost,
  .bahan-page .btn-danger{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    min-height:42px;
    padding:10px 14px;
    border-radius:14px;
    border:1px solid var(--line);
    font-size:14px;
    font-weight:700;
    line-height:1;
    text-decoration:none;
    cursor:pointer;
    transition:all .15s ease;
    box-sizing:border-box;
    white-space:nowrap;
  }

  .bahan-page .btn{
    background:var(--blue);
    border-color:var(--blue);
    color:#fff;
  }

  .bahan-page .btn:hover{
    background:var(--blue-dark);
    border-color:var(--blue-dark);
  }

  .bahan-page .btn-ghost{
    background:#fff;
    color:var(--text);
  }

  .bahan-page .btn-ghost:hover{
    border-color:var(--line-strong);
    background:var(--bg-soft);
  }

  .bahan-page .btn-danger{
    background:#fff;
    color:var(--danger);
    border-color:var(--danger-line);
  }

  .bahan-page .btn-danger:hover{
    background:var(--danger-bg);
  }

  .bahan-page .btn-sm{
    min-height:36px;
    padding:8px 12px;
    font-size:13px;
    border-radius:999px;
  }

  .bahan-page .hint{
    font-size:13px;
    color:var(--muted);
    line-height:1.7;
    background:var(--bg-soft);
    border:1px solid #eef2f7;
    border-radius:16px;
    padding:14px 16px;
  }

  .bahan-page .actions-row{
    margin-top:16px;
    display:flex;
    gap:10px;
    flex-wrap:wrap;
  }

  .bahan-page .toolbar{
    display:flex;
    flex-direction:column;
    gap:16px;
  }

  .bahan-page .status-pills{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
  }

  .bahan-page .status-chip{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:10px 14px;
    border-radius:999px;
    border:1px solid var(--line);
    background:#fff;
    color:var(--text);
    text-decoration:none;
    font-weight:700;
    font-size:14px;
  }

  .bahan-page .status-chip:hover{
    background:var(--bg-soft);
  }

  .bahan-page .status-chip.active{
    background:#0b1736;
    border-color:#0b1736;
    color:#fff;
  }

  .bahan-page .status-chip .count{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:22px;
    height:22px;
    padding:0 6px;
    border-radius:999px;
    font-size:12px;
    background:rgba(15, 23, 42, 0.06);
    color:inherit;
  }

  .bahan-page .status-chip.active .count{
    background:rgba(255,255,255,0.16);
  }

  .bahan-page .searchbar{
    display:grid;
    grid-template-columns:minmax(0, 1fr) auto auto;
    gap:10px;
    align-items:center;
  }

  .bahan-page .summary-row{
    display:grid;
    grid-template-columns:repeat(4, minmax(0, 1fr));
    gap:12px;
  }

  .bahan-page .summary-card{
    border:1px solid var(--line);
    border-radius:18px;
    padding:14px 16px;
    background:linear-gradient(180deg, #fff 0%, #fbfdff 100%);
  }

  .bahan-page .summary-label{
    font-size:12px;
    color:var(--muted);
    margin-bottom:6px;
  }

  .bahan-page .summary-value{
    font-size:22px;
    font-weight:800;
    color:var(--text);
    line-height:1;
  }

  .bahan-page .table-shell{
    border:1px solid var(--line);
    border-radius:20px;
    overflow:hidden;
    background:#fff;
  }

  .bahan-page .table-scroll{
    width:100%;
    overflow:auto;
    max-height:calc(100vh - 290px);
    -webkit-overflow-scrolling:touch;
  }

  .bahan-page table.tbl{
    width:100%;
    min-width:1320px;
    border-collapse:separate;
    border-spacing:0;
  }

  .bahan-page .tbl th,
  .bahan-page .tbl td{
    padding:14px 12px;
    border-bottom:1px solid var(--line);
    border-right:1px solid #edf2f7;
    font-size:13px;
    vertical-align:middle;
    background:#fff;
  }

  .bahan-page .tbl th:last-child,
  .bahan-page .tbl td:last-child{
    border-right:none;
  }

  .bahan-page .tbl th{
    position:sticky;
    top:0;
    z-index:2;
    background:#f8fafc;
    color:var(--text);
    font-weight:800;
    letter-spacing:.01em;
  }

  .bahan-page .tbl tbody tr:hover td{
    background:#fcfdff;
  }

  .bahan-page .tbl tbody tr:last-child td{
    border-bottom:none;
  }

  .bahan-page .ingredient-name{
    display:flex;
    flex-direction:column;
    gap:8px;
  }

  .bahan-page .ingredient-main{
    font-size:15px;
    font-weight:800;
    color:var(--text);
    line-height:1.2;
  }

  .bahan-page .pill-status{
    display:inline-flex;
    align-items:center;
    width:max-content;
    padding:4px 10px;
    border-radius:999px;
    font-size:12px;
    font-weight:700;
    border:1px solid var(--line);
    white-space:nowrap;
  }

  .bahan-page .pill-aman{
    background:var(--success-bg);
    border-color:var(--success-line);
    color:var(--success);
  }

  .bahan-page .pill-restock{
    background:var(--warn-bg);
    border-color:var(--warn-line);
    color:var(--warn);
  }

  .bahan-page .pill-kritis{
    background:var(--danger-bg);
    border-color:var(--danger-line);
    color:var(--danger);
  }

  .bahan-page .pill-habis{
    background:var(--dark-bg);
    border-color:var(--dark-bg);
    color:#fff;
  }

  .bahan-page .td-actions{
    white-space:nowrap;
  }

  .bahan-page .td-actions .actions{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    justify-content:flex-end;
    align-items:center;
  }

  .bahan-page .empty-state{
    padding:28px 18px;
    text-align:center;
    color:var(--muted);
    font-size:14px;
    background:#fff;
  }

  .bahan-page .mobile-list{
    display:none;
  }

  .bahan-page .card{
    border:1px solid var(--line);
    border-radius:18px;
    padding:14px;
    background:#fff;
    box-shadow:0 8px 24px rgba(15, 23, 42, 0.04);
  }

  .bahan-page .card + .card{
    margin-top:12px;
  }

  .bahan-page .card-top{
    display:flex;
    gap:10px;
    justify-content:space-between;
    align-items:flex-start;
    margin-bottom:10px;
  }

  .bahan-page .card-title{
    font-weight:800;
    font-size:16px;
    color:var(--text);
    line-height:1.25;
  }

  .bahan-page .meta{
    font-size:12px;
    color:var(--muted);
    margin-top:6px;
  }

  .bahan-page .kv{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:10px;
    margin-top:12px;
  }

  .bahan-page .kv-item{
    border:1px solid #eef2f7;
    background:var(--bg-soft);
    border-radius:14px;
    padding:10px 12px;
  }

  .bahan-page .k{
    font-size:11px;
    color:var(--muted);
    margin-bottom:4px;
  }

  .bahan-page .v{
    font-size:13px;
    color:var(--text);
    font-weight:800;
  }

  .bahan-page .card-actions{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    margin-top:14px;
  }

  .bahan-page .card-actions a,
  .bahan-page .card-actions button{
    flex:1 1 140px;
  }

  @media (max-width: 1180px){
    .bahan-page .grid{
      grid-template-columns:1fr;
    }

    .bahan-page .sticky-form{
      position:static;
    }

    .bahan-page .summary-row{
      grid-template-columns:repeat(2, minmax(0, 1fr));
    }

    .bahan-page .table-scroll{
      max-height:none;
    }
  }

  @media (max-width: 760px){
    .bahan-page .wrap{
      max-width:100%;
    }

    .bahan-page h1{
      font-size:36px;
    }

    .bahan-page .sub{
      font-size:14px;
    }

    .bahan-page .panel{
      border-radius:18px;
    }

    .bahan-page .panel-head,
    .bahan-page .panel-body{
      padding:16px;
    }

    .bahan-page .row2,
    .bahan-page .summary-row,
    .bahan-page .searchbar{
      grid-template-columns:1fr;
    }

    .bahan-page .desktop-table{
      display:none;
    }

    .bahan-page .mobile-list{
      display:block;
    }

    .bahan-page .status-pills{
      gap:8px;
    }

    .bahan-page .status-chip{
      padding:9px 12px;
      font-size:13px;
    }
  }
</style>

<div class="bahan-page">
  <div class="wrap">
    <div class="page-head">
      <div>
        <h1>Daftar Bahan</h1>
        <p class="sub">
          Kelola bahan baku, satuan dasar <b>(g/ml/pcs)</b>, stok aman, waktu tunggu supplier, dan saran belanja ulang otomatis.
        </p>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($ok): ?>
      <div class="alert alert-success"><?= htmlspecialchars($ok) ?></div>
    <?php endif; ?>

    <div class="grid">
      <div class="sticky-form">
        <div class="panel">
          <div class="panel-head">
            <div>
              <h3><?= $editRow ? 'Edit Bahan' : 'Tambah Bahan' ?></h3>
              <p class="panel-note">
                Isi data dasar bahan. Sistem akan menghitung kapan harus mulai belanja lagi dan berapa saran pembeliannya.
              </p>
            </div>
          </div>

          <div class="panel-body">
            <form method="post">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
              <input type="hidden" name="action" value="<?= $editRow ? 'update' : 'create' ?>">
              <?php if ($editRow): ?>
                <input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>">
              <?php endif; ?>

              <div class="field">
                <label>Nama Bahan</label>
                <input
                  name="name"
                  required
                  placeholder="Contoh: Beras Premium, Gula Pasir"
                  value="<?= htmlspecialchars((string)($editRow['name'] ?? '')) ?>"
                >
              </div>

              <div class="row2 field">
                <div>
                  <label>Base / Unit</label>
                  <?php $u = (string)($editRow['unit'] ?? 'pcs'); ?>
                  <select name="unit">
                    <optgroup label="Berat (g)">
                      <option value="gram" <?= $u === 'gram' ? 'selected' : '' ?>>gram (g)</option>
                      <option value="kilogram" <?= $u === 'kilogram' ? 'selected' : '' ?>>kilogram (kg)</option>
                      <option value="miligram" <?= $u === 'miligram' ? 'selected' : '' ?>>miligram (mg)</option>
                    </optgroup>

                    <optgroup label="Volume (ml)">
                      <option value="milliliter" <?= $u === 'milliliter' ? 'selected' : '' ?>>milliliter (ml)</option>
                      <option value="liter" <?= $u === 'liter' ? 'selected' : '' ?>>liter (L)</option>
                    </optgroup>

                    <optgroup label="Butir (pcs)">
                      <option value="pcs" <?= $u === 'pcs' ? 'selected' : '' ?>>pcs</option>
                      <option value="pack-6" <?= $u === 'pack-6' ? 'selected' : '' ?>>pack-6 (6 pcs)</option>
                      <option value="dus-12" <?= $u === 'dus-12' ? 'selected' : '' ?>>dus-12 (12 pcs)</option>
                    </optgroup>
                  </select>
                </div>

                <div>
                  <label>Stok minimum lama (opsional)</label>
                  <input
                    type="number"
                    step="0.001"
                    min="0"
                    name="min_stock"
                    value="<?= htmlspecialchars((string)($editRow['min_stock'] ?? '0')) ?>"
                  >
                </div>
              </div>

              <div class="row2 field">
                <div>
                  <label>Stok Aman</label>
                  <input
                    type="number"
                    step="0.001"
                    min="0"
                    name="safety_stock"
                    value="<?= htmlspecialchars((string)($editRow['safety_stock'] ?? '0')) ?>"
                  >
                </div>

                <div>
                  <label>Barang Datang Dalam (hari)</label>
                  <input
                    type="number"
                    step="1"
                    min="1"
                    name="lead_time_days"
                    value="<?= htmlspecialchars((string)($editRow['lead_time_days'] ?? '1')) ?>"
                  >
                </div>
              </div>

              <?php if (!$editRow): ?>
                <input type="hidden" name="stock" value="0">
              <?php endif; ?>

              <div class="hint" style="margin-top:16px;">
                Sistem memakai satuan dasar <b>g / ml / pcs</b>. Satuan lain bisa diatur di menu Konversi Unit.
                <br>
                Kolom <b>Mulai Belanja</b> dan <b>Saran Beli</b> dihitung otomatis dari histori pemakaian bahan 30 hari terakhir.
              </div>

              <div class="actions-row">
                <button class="btn" type="submit"><?= $editRow ? 'Simpan Perubahan' : 'Tambah Bahan' ?></button>
                <?php if ($editRow): ?>
                  <a class="btn-ghost" href="admin_persediaan_bahan.php">Batal</a>
                <?php endif; ?>
              </div>
            </form>
          </div>
        </div>
      </div>

      <div class="panel">
        <div class="panel-head">
          <div>
            <h3>Daftar Bahan</h3>
            <p class="panel-note">
              Cari bahan, cek kondisi stok, lalu buka konversi unit atau edit data langsung dari daftar.
            </p>
          </div>
        </div>

        <div class="panel-body">
          <div class="toolbar">
            <div class="status-pills">
              <?php foreach ($statusLabels as $key => $label): ?>
                <?php
                  $statusUrl = 'admin_persediaan_bahan.php?status=' . urlencode($key);
                  if ($q !== '') {
                    $statusUrl .= '&q=' . urlencode($q);
                  }
                  if ($editId > 0) {
                    $statusUrl .= '&edit=' . $editId;
                  }
                ?>
                <a class="status-chip <?= $statusFilter === $key ? 'active' : '' ?>" href="<?= htmlspecialchars($statusUrl) ?>">
                  <span><?= htmlspecialchars($label) ?></span>
                  <span class="count"><?= (int)($stats[$key] ?? 0) ?></span>
                </a>
              <?php endforeach; ?>
            </div>

            <form method="get" class="searchbar" action="admin_persediaan_bahan.php">
              <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
              <?php if ($editId > 0): ?>
                <input type="hidden" name="edit" value="<?= $editId ?>">
              <?php endif; ?>
              <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Cari bahan... (contoh: gula, beras)">
              <button class="btn" type="submit">Cari</button>
              <a class="btn-ghost" href="admin_persediaan_bahan.php">Reset</a>
            </form>

            <div class="summary-row">
              <div class="summary-card">
                <div class="summary-label">Total bahan tampil</div>
                <div class="summary-value"><?= count($rows) ?></div>
              </div>
              <div class="summary-card">
                <div class="summary-label">Filter aktif</div>
                <div class="summary-value" style="font-size:18px;"><?= htmlspecialchars($statusLabels[$statusFilter] ?? 'Semua') ?></div>
              </div>
              <div class="summary-card">
                <div class="summary-label">Bahan aman</div>
                <div class="summary-value"><?= (int)$stats['aman'] ?></div>
              </div>
              <div class="summary-card">
                <div class="summary-label">Perlu perhatian</div>
                <div class="summary-value"><?= (int)$stats['restock'] + (int)$stats['kritis'] + (int)$stats['habis'] ?></div>
              </div>
            </div>
          </div>

          <div class="mobile-list" style="margin-top:16px;">
            <?php if (!$rows): ?>
              <div class="empty-state"><?= $q !== '' ? 'Tidak ada bahan yang cocok dengan pencarian/filter.' : 'Belum ada bahan.' ?></div>
            <?php else: ?>
              <?php foreach ($rows as $r): ?>
                <div class="card">
                  <div class="card-top">
                    <div>
                      <div class="card-title"><?= htmlspecialchars((string)$r['name']) ?></div>
                      <div class="meta">Terakhir update: <?= htmlspecialchars((string)$r['last_at']) ?></div>
                    </div>
                    <span class="pill-status pill-<?= htmlspecialchars((string)$r['status']['key']) ?>">
                      <?= htmlspecialchars((string)$r['status']['label']) ?>
                    </span>
                  </div>

                  <div class="kv">
                    <div class="kv-item">
                      <div class="k">Stok</div>
                      <div class="v"><?= htmlspecialchars((string)$r['stock_txt']) ?></div>
                    </div>
                    <div class="kv-item">
                      <div class="k">Batas Aman</div>
                      <div class="v"><?= htmlspecialchars((string)$r['safety_txt']) ?></div>
                    </div>
                    <div class="kv-item">
                      <div class="k">Mulai Belanja</div>
                      <div class="v"><?= htmlspecialchars((string)$r['rop_txt']) ?></div>
                    </div>
                    <div class="kv-item">
                      <div class="k">Pemakaian/Hari</div>
                      <div class="v"><?= htmlspecialchars((string)$r['avg_txt']) ?></div>
                    </div>
                    <div class="kv-item">
                      <div class="k">Saran Beli</div>
                      <div class="v"><?= htmlspecialchars((string)$r['suggested_txt']) ?></div>
                    </div>
                    <div class="kv-item">
                      <div class="k">Datang Dalam</div>
                      <div class="v"><?= (int)$r['lead_time_days'] ?> hari</div>
                    </div>
                    <div class="kv-item" style="grid-column:span 2;">
                      <div class="k">Satuan</div>
                      <div class="v"><?= htmlspecialchars((string)$r['unit']) ?></div>
                    </div>
                  </div>

                  <div class="card-actions">
                    <a class="btn-ghost" href="admin_konversi_unit.php?ingredient_id=<?= (int)$r['id'] ?>">Konversi Unit</a>
                    <a class="btn-ghost" href="admin_persediaan_bahan.php?edit=<?= (int)$r['id'] ?>">Edit</a>
                    <form method="post" style="flex:1 1 140px;">
                      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <button
                        class="btn-danger"
                        type="submit"
                        onclick="return confirm('Hapus bahan ini permanen? (akan ditolak jika masih dipakai di resep)')"
                      >
                        Hapus
                      </button>
                    </form>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>

            <div class="hint" style="margin-top:12px;">
              <b>Penjelasan Kolom:</b><br>
              • <b>Stok</b> = jumlah bahan yang tersedia sekarang.<br>
              • <b>Batas Aman</b> = batas minimal stok supaya operasional tetap aman.<br>
              • <b>Mulai Belanja</b> = batas stok saat kamu sebaiknya mulai beli lagi.<br>
              • <b>Pemakaian/Hari</b> = rata-rata bahan yang dipakai per hari.<br>
              • <b>Saran Beli</b> = jumlah restock yang disarankan sistem.<br>
              • <b>Datang Dalam</b> = lama waktu supplier mengirim barang.<br>
              • <b>Satuan</b> = satuan dasar bahan, misalnya gram, ml, atau pcs.
              <br><br>

              <b>Cara sistem menghitung:</b><br>
              • Sistem melihat riwayat pemakaian bahan selama <b>30 hari terakhir</b>.<br>
              • Dari situ, sistem menghitung rata-rata <b>Pemakaian/Hari</b>.<br>
              • Lalu sistem memperkirakan kebutuhan stok selama menunggu supplier datang.<br>
              • Setelah itu sistem menambahkan <b>Batas Aman</b> supaya stok tidak terlalu mepet.<br>
              <br>
              <b>Contoh:</b><br>
              • Pemakaian/Hari = <b>10</b><br>
              • Supplier datang dalam = <b>3 hari</b><br>
              • Batas Aman = <b>20</b><br>
              • Maka <b>Mulai Belanja = 10 × 3 + 20 = 50</b><br>
              • Jadi kalau stok sudah mendekati <b>50</b>, sistem menganggap bahan itu sudah waktunya dibeli lagi.
              <br><br>
              <b>Catatan:</b><br>
              • Kalau bahan belum punya riwayat pemakaian yang cukup, hasil hitungan otomatis belum selalu akurat.<br>
              • Semakin sering transaksi dan stok keluar tercatat, semakin akurat angkanya.
            </div>
          </div>

          <div class="desktop-table" style="margin-top:16px;">
            <div class="table-shell">
              <div class="table-scroll">
                <table class="tbl">
                  <thead>
                    <tr>
                      <th style="width:250px;">Nama Bahan</th>
                      <th style="width:100px;">Stok</th>
                      <th style="width:120px;">Batas Aman</th>
                      <th style="width:130px;">Mulai Belanja</th>
                      <th style="width:120px;">Pemakaian/Hari</th>
                      <th style="width:130px;">Saran Beli</th>
                      <th style="width:120px;">Datang Dalam</th>
                      <th style="width:110px;">Satuan</th>
                      <th style="width:160px;">Update Terakhir</th>
                      <th style="width:260px;">Aksi</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!$rows): ?>
                      <tr>
                        <td colspan="10">
                          <div class="empty-state"><?= $q !== '' ? 'Tidak ada bahan yang cocok dengan pencarian/filter.' : 'Belum ada bahan.' ?></div>
                        </td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($rows as $r): ?>
                        <tr>
                          <td>
                            <div class="ingredient-name">
                              <div class="ingredient-main"><?= htmlspecialchars((string)$r['name']) ?></div>
                              <div>
                                <span class="pill-status pill-<?= htmlspecialchars((string)$r['status']['key']) ?>">
                                  <?= htmlspecialchars((string)$r['status']['label']) ?>
                                </span>
                              </div>
                            </div>
                          </td>
                          <td><?= htmlspecialchars((string)$r['stock_txt']) ?></td>
                          <td><?= htmlspecialchars((string)$r['safety_txt']) ?></td>
                          <td><?= htmlspecialchars((string)$r['rop_txt']) ?></td>
                          <td><?= htmlspecialchars((string)$r['avg_txt']) ?></td>
                          <td><?= htmlspecialchars((string)$r['suggested_txt']) ?></td>
                          <td><?= (int)$r['lead_time_days'] ?> hari</td>
                          <td><?= htmlspecialchars((string)$r['unit']) ?></td>
                          <td><?= htmlspecialchars((string)$r['last_at']) ?></td>
                          <td class="td-actions">
                            <div class="actions">
                              <a class="btn-ghost btn-sm" href="admin_konversi_unit.php?ingredient_id=<?= (int)$r['id'] ?>">Konversi</a>
                              <a class="btn-ghost btn-sm" href="admin_persediaan_bahan.php?edit=<?= (int)$r['id'] ?>">Edit</a>
                              <form method="post" style="display:inline;">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <button
                                  class="btn-danger btn-sm"
                                  type="submit"
                                  onclick="return confirm('Hapus bahan ini permanen? (akan ditolak jika masih dipakai di resep)')"
                                >
                                  Hapus
                                </button>
                              </form>
                            </div>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="hint" style="margin-top:12px;">
              <b>Penjelasan Kolom:</b><br>
              • <b>Stok</b> = jumlah bahan yang tersedia sekarang.<br>
              • <b>Batas Aman</b> = batas minimal stok supaya operasional tetap aman.<br>
              • <b>Mulai Belanja</b> = batas stok saat kamu sebaiknya mulai beli lagi.<br>
              • <b>Pemakaian/Hari</b> = rata-rata bahan yang dipakai per hari.<br>
              • <b>Saran Beli</b> = jumlah restock yang disarankan sistem.<br>
              • <b>Datang Dalam</b> = lama waktu supplier mengirim barang.<br>
              • <b>Satuan</b> = satuan dasar bahan, misalnya gram, ml, atau pcs.
              <br><br>

              <b>Cara sistem menghitung:</b><br>
              • Sistem melihat riwayat pemakaian bahan selama <b>30 hari terakhir</b>.<br>
              • Dari situ, sistem menghitung rata-rata <b>Pemakaian/Hari</b>.<br>
              • Lalu sistem memperkirakan kebutuhan stok selama menunggu supplier datang.<br>
              • Setelah itu sistem menambahkan <b>Batas Aman</b> supaya stok tidak terlalu mepet.<br>
              <br>
              <b>Contoh:</b><br>
              • Pemakaian/Hari = <b>10</b><br>
              • Supplier datang dalam = <b>3 hari</b><br>
              • Batas Aman = <b>20</b><br>
              • Maka <b>Mulai Belanja = 10 × 3 + 20 = 50</b><br>
              • Jadi kalau stok sudah mendekati <b>50</b>, sistem menganggap bahan itu sudah waktunya dibeli lagi.
              <br><br>
              <b>Catatan:</b><br>
              • Kalau bahan belum punya riwayat pemakaian yang cukup, hasil hitungan otomatis belum selalu akurat.<br>
              • Semakin sering transaksi dan stok keluar tercatat, semakin akurat angkanya.
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../publik/partials/admin_layout_bottom.php'; ?>
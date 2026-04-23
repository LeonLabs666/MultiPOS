<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';
require_role(['kasir','admin']);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$user = auth_user();
$kasirId   = (int)($user['id'] ?? 0);

/* ===== Store (pattern MultiPOS) ===== */
$st = $pdo->prepare("
  SELECT s.id, s.name
  FROM users k
  JOIN stores s ON s.owner_admin_id = k.created_by
  WHERE k.id=? AND s.is_active=1
  LIMIT 1
");
$st->execute([$kasirId]);
$store = $st->fetch();
if (!$store) { http_response_code(400); exit('Kasir belum terhubung ke toko.'); }

$storeId   = (int)$store['id'];
$storeName = (string)$store['name'];

/* ===== Ensure customers table exists ===== */
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

/* ===== Query customers ===== */
$q = trim((string)($_GET['q'] ?? ''));
$qLike = '%' . $q . '%';

$sql = "
  SELECT id, name, phone, notes, created_at, last_visit_at
  FROM customers
  WHERE store_id=?
";
$params = [$storeId];

if ($q !== '') {
  $sql .= " AND (name LIKE ? OR phone LIKE ?) ";
  $params[] = $qLike;
  $params[] = $qLike;
}

$sql .= " ORDER BY COALESCE(last_visit_at, created_at) DESC, id DESC LIMIT 500 ";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

/* ===== Layout Kasir ===== */
$appName    = '';
$pageTitle  = 'Pelanggan';
$activeMenu = 'kasir_customers';
require __DIR__ . '/../publik/partials/kasir_layout_top.php';
?>

<style>
  .cardx{
    background:#fff;
    border:1px solid rgba(15,23,42,.10);
    border-radius:18px;
    padding:14px;
    box-shadow: 0 18px 50px rgba(15,23,42,.06);
  }
  .row{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    align-items:center;
    justify-content:space-between;
  }
  .search{
    flex: 1 1 320px;
    display:flex;
    gap:10px;
    align-items:center;
    padding:10px 12px;
    border:1px solid rgba(15,23,42,.12);
    border-radius:14px;
    background:#fff;
  }
  .search input{
    border:none;
    outline:none;
    width:100%;
    font-size:14px;
  }
  table{
    width:100%;
    border-collapse:separate;
    border-spacing:0;
    margin-top:12px;
    overflow:hidden;
    border-radius:14px;
    border:1px solid rgba(15,23,42,.10);
    background:#fff;
  }
  thead th{
    text-align:left;
    font-size:12px;
    color:#64748b;
    font-weight:900;
    padding:10px 12px;
    background:#f8fafc;
    border-bottom:1px solid rgba(15,23,42,.10);
  }
  tbody td{
    padding:12px;
    border-bottom:1px solid rgba(15,23,42,.06);
    font-size:13px;
    vertical-align:top;
  }
  tbody tr:last-child td{ border-bottom:none; }
  .nm{ font-weight:950; }
  .muted{ color:#64748b; font-size:12px; margin-top:4px; }
  .pill2{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:6px 10px;
    border-radius:999px;
    border:1px solid rgba(15,23,42,.12);
    background:#fff;
    font-size:12px;
    font-weight:900;
    color:#0f172a;
  }
  .empty{
    padding:18px 12px;
    text-align:center;
    color:#64748b;
    font-weight:900;
  }
</style>

<div class="cardx">
  <div class="row">
    <div>
      <div style="font-weight:1000; font-size:14px;">Daftar Pelanggan</div>
      <div style="color:#64748b; font-size:12px; margin-top:2px;">Toko: <?= htmlspecialchars($storeName) ?></div>
    </div>
    <div class="pill2"><?= count($items) ?> pelanggan</div>
  </div>

  <form method="get" style="margin-top:12px;">
    <div class="search">
      <div style="font-weight:900;">🔎</div>
      <input name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Cari nama / no HP...">
    </div>
  </form>

  <table>
    <thead>
      <tr>
        <th>Nama</th>
        <th>No. HP</th>
        <th>Terakhir transaksi</th>
        <th>Catatan</th>
      </tr>
    </thead>
    <tbody>
    <?php if (!$items): ?>
      <tr><td colspan="4" class="empty">Belum ada data pelanggan. Input pelanggan saat transaksi, lalu data akan muncul di sini.</td></tr>
    <?php else: ?>
      <?php foreach ($items as $c): ?>
        <tr>
          <td>
            <div class="nm"><?= htmlspecialchars((string)$c['name']) ?></div>
            <div class="muted">ID #<?= (int)$c['id'] ?> · Dibuat <?= htmlspecialchars((string)$c['created_at']) ?></div>
          </td>
          <td><?= htmlspecialchars((string)($c['phone'] ?? '-')) ?></td>
          <td><?= htmlspecialchars((string)($c['last_visit_at'] ?? '-')) ?></td>
          <td><?= htmlspecialchars((string)($c['notes'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../publik/partials/kasir_layout_bottom.php'; ?>

<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';

require_role(['developer']);

$dev = auth_user();
$devId = (int)$dev['id'];

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ---- Cari admin yang dibuat oleh developer ini ----
// Catatan: asumsi tabel users punya kolom created_by.
// Kalau ternyata kolomnya tidak ada, halaman akan menampilkan pesan.
$admins = [];
$createdByExists = true;

try {
  $stmtAdmins = $pdo->prepare("
    SELECT id, name, email
    FROM users
    WHERE role='admin' AND created_by=?
    ORDER BY id DESC
  ");
  $stmtAdmins->execute([$devId]);
  $admins = $stmtAdmins->fetchAll();
} catch (Throwable $e) {
  $createdByExists = false;
  $admins = [];
}

$adminIds = array_map(fn($r) => (int)$r['id'], $admins);

// ---- Ambil store milik admin-admin tersebut ----
$stores = [];
$storeIds = [];

if ($adminIds) {
  $in = implode(',', array_fill(0, count($adminIds), '?'));
  $stStores = $pdo->prepare("
    SELECT id, name, owner_admin_id
    FROM stores
    WHERE owner_admin_id IN ($in)
    ORDER BY id DESC
  ");
  $stStores->execute($adminIds);
  $stores = $stStores->fetchAll();
  $storeIds = array_map(fn($r) => (int)$r['id'], $stores);
}

// ---- Filters ----
$from = trim((string)($_GET['from'] ?? ''));
$to   = trim((string)($_GET['to'] ?? ''));
$adminIdFilter = (int)($_GET['admin_id'] ?? 0);
$action = trim((string)($_GET['action'] ?? ''));
$q = trim((string)($_GET['q'] ?? ''));

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

// default hari ini
if ($from === '') $from = date('Y-m-d');
if ($to === '') $to = date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = date('Y-m-d');

// action list (distinct dari storeIds)
$actions = [];
if ($storeIds) {
  $inS = implode(',', array_fill(0, count($storeIds), '?'));
  $stAct = $pdo->prepare("SELECT DISTINCT action FROM activity_logs WHERE store_id IN ($inS) ORDER BY action");
  $stAct->execute($storeIds);
  $actions = array_map(fn($r) => (string)$r['action'], $stAct->fetchAll());
}

// helper build url
function build_url(array $extra = []): string {
  $base = [
    'from' => $_GET['from'] ?? '',
    'to' => $_GET['to'] ?? '',
    'admin_id' => $_GET['admin_id'] ?? '',
    'action' => $_GET['action'] ?? '',
    'q' => $_GET['q'] ?? '',
    'page' => $_GET['page'] ?? '1',
  ];
  return 'dev_activity_logs.php?' . http_build_query(array_merge($base, $extra));
}

// ---- Jika tidak ada admin/stores, tampilkan info ----
if (!$createdByExists) {
  http_response_code(200);
  ?>
  <!doctype html>
  <html lang="id"><head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Dev Activity Logs</title>
    <style>
      body{font:14px/1.5 system-ui;margin:24px}
      .card{max-width:860px;border:1px solid #e5e7eb;border-radius:12px;padding:14px}
      .muted{color:#6b7280}
      a{color:#111827}
    </style>
  </head><body>
    <div class="card">
      <h2 style="margin:0 0 8px">Dev Activity Logs</h2>
      <p class="muted" style="margin:0 0 12px">
        Tabel <b>users</b> di database kamu tidak mendukung filter <b>created_by</b> (query error),
        jadi sistem tidak bisa menentukan “admin yang dibuat oleh developer”.
      </p>
      <p style="margin:0 0 12px">
        Solusi: pastikan kolom <code>users.created_by</code> ada dan saat developer membuat admin, kolom itu diisi ID developer.
      </p>
      <p style="margin:0">
        <a href="dev_dashboard.php">← Kembali ke Developer Dashboard</a>
      </p>
    </div>
  </body></html>
  <?php
  exit;
}

if (!$adminIds) {
  ?>
  <!doctype html>
  <html lang="id"><head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Dev Activity Logs</title>
    <style>
      body{font:14px/1.5 system-ui;margin:24px}
      .card{max-width:860px;border:1px solid #e5e7eb;border-radius:12px;padding:14px}
      .muted{color:#6b7280}
      a{color:#111827}
    </style>
  </head><body>
    <div class="card">
      <h2 style="margin:0 0 8px">Dev Activity Logs</h2>
      <p class="muted" style="margin:0 0 12px">
        Belum ada admin yang dibuat oleh developer ini (<b><?= h($dev['email'] ?? '') ?></b>).
      </p>
      <p style="margin:0">
        <a href="dev_create_admin.php">+ Buat Admin</a> · <a href="dev_dashboard.php">Dashboard</a>
      </p>
    </div>
  </body></html>
  <?php
  exit;
}

if (!$storeIds) {
  ?>
  <!doctype html>
  <html lang="id"><head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Dev Activity Logs</title>
    <style>
      body{font:14px/1.5 system-ui;margin:24px}
      .card{max-width:860px;border:1px solid #e5e7eb;border-radius:12px;padding:14px}
      .muted{color:#6b7280}
      a{color:#111827}
    </style>
  </head><body>
    <div class="card">
      <h2 style="margin:0 0 8px">Dev Activity Logs</h2>
      <p class="muted" style="margin:0 0 12px">
        Admin yang dibuat sudah ada, tapi belum punya <b>store</b> yang aktif.
      </p>
      <p style="margin:0">
        <a href="dev_dashboard.php">← Kembali</a>
      </p>
    </div>
  </body></html>
  <?php
  exit;
}

// ---- Query logs (dibatasi store_id yang dimiliki admin-admin itu) ----
$where = [];
$params = [];

// store_id IN (...)
$inS = implode(',', array_fill(0, count($storeIds), '?'));
$where[] = "al.store_id IN ($inS)";
$params = array_merge($params, $storeIds);

// date range
$where[] = "DATE(al.created_at) BETWEEN ? AND ?";
$params[] = $from;
$params[] = $to;

// filter admin (opsional): mapping admin → store
if ($adminIdFilter > 0) {
  // Ambil store_id milik admin ini, dan pastikan admin itu memang created_by dev
  $st = $pdo->prepare("SELECT id FROM stores WHERE owner_admin_id=? LIMIT 50");
  $st->execute([$adminIdFilter]);
  $sids = array_map(fn($r) => (int)$r['id'], $st->fetchAll());

  // jika admin yg dipilih bukan admin punya dev atau tidak punya store, hasil kosong
  if ($sids) {
    $inAdminStores = implode(',', array_fill(0, count($sids), '?'));
    $where[] = "al.store_id IN ($inAdminStores)";
    $params = array_merge($params, $sids);
  } else {
    $where[] = "1=0";
  }
}

// filter action
if ($action !== '') {
  $where[] = "al.action = ?";
  $params[] = $action;
}

// search message
if ($q !== '') {
  $where[] = "al.message LIKE ?";
  $params[] = '%' . $q . '%';
}

$whereSql = implode(' AND ', $where);

// count
$stCount = $pdo->prepare("SELECT COUNT(*) c FROM activity_logs al WHERE $whereSql");
$stCount->execute($params);
$totalRows = (int)($stCount->fetch()['c'] ?? 0);
$totalPages = max(1, (int)ceil($totalRows / $perPage));

// rows
$stRows = $pdo->prepare("
  SELECT
    al.id, al.created_at, al.actor_user_id, al.actor_role, al.action,
    al.entity_type, al.entity_id, al.message,
    u.name AS actor_name,
    s.owner_admin_id,
    a.name AS admin_name, a.email AS admin_email
  FROM activity_logs al
  LEFT JOIN users u ON u.id = al.actor_user_id
  LEFT JOIN stores s ON s.id = al.store_id
  LEFT JOIN users a ON a.id = s.owner_admin_id
  WHERE $whereSql
  ORDER BY al.id DESC
  LIMIT $perPage OFFSET $offset
");
$stRows->execute($params);
$rows = $stRows->fetchAll();

function entity_link(array $r): string {
  $type = (string)($r['entity_type'] ?? '');
  $id = (int)($r['entity_id'] ?? 0);

  if ($type === 'sale' && $id > 0) {
    // Developer tidak selalu punya akses admin_sale_detail, tapi tetap boleh link (kalau aman nanti)
    return '<a href="admin_sale_detail.php?id=' . $id . '">sale#' . $id . '</a>';
  }
  if ($type === 'cashier_shift' && $id > 0) return 'shift#' . $id;
  if ($type !== '' && $id > 0) return h($type) . '#' . $id;
  return '-';
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Dev · Admin Activity Logs</title>
  <style>
    :root{--bg:#0b0c10;--card:#11131a;--text:#e8eaf0;--muted:#a9b0c3;--line:#1d2130;--accent:#7c5cff}
    body{margin:0;font:14px/1.5 system-ui;background:var(--bg);color:var(--text)}
    a{color:inherit}
    .wrap{max-width:1150px;margin:0 auto;padding:18px}
    .top{display:flex;justify-content:space-between;align-items:center;gap:12px;background:var(--card);border:1px solid var(--line);border-radius:14px;padding:14px}
    .muted{color:var(--muted);font-size:12px}
    .btn{display:inline-flex;gap:8px;align-items:center;padding:10px 12px;border:1px solid var(--line);border-radius:12px;text-decoration:none}
    .btn.primary{background:var(--accent);border-color:transparent;color:white}
    .card{margin-top:12px;background:var(--card);border:1px solid var(--line);border-radius:14px;padding:14px}
    form{display:grid;grid-template-columns:repeat(12,1fr);gap:10px;align-items:end}
    label{display:block;font-size:12px;color:var(--muted);margin-bottom:4px}
    input,select{width:100%;padding:10px;border-radius:12px;border:1px solid var(--line);background:transparent;color:var(--text)}
    .c3{grid-column:span 3}.c4{grid-column:span 4}.c6{grid-column:span 6}.c2{grid-column:span 2}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th,td{border-bottom:1px solid var(--line);padding:10px;vertical-align:top}
    th{font-size:12px;color:var(--muted);text-align:left}
    .pill{display:inline-block;font-size:12px;padding:3px 8px;border:1px solid var(--line);border-radius:999px;color:var(--muted)}
    .pagination{display:flex;gap:8px;align-items:center;justify-content:flex-end;margin-top:12px;flex-wrap:wrap}
    @media(max-width:900px){.c3,.c4,.c6,.c2{grid-column:span 12}}
  </style>
</head>
<body>
<div class="wrap">
  <div class="top">
    <div>
      <b>Dev · Admin Activity Logs</b>
      <div class="muted">Developer: <?= h($dev['email'] ?? '') ?> · total <?= (int)$totalRows ?> log</div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <a class="btn" href="dev_dashboard.php">Dashboard</a>
      <a class="btn primary" href="<?= h(build_url(['page'=>1])) ?>">Refresh</a>
    </div>
  </div>

  <div class="card">
    <form method="get">
      <div class="c3">
        <label>Dari</label>
        <input type="date" name="from" value="<?= h($from) ?>">
      </div>
      <div class="c3">
        <label>Sampai</label>
        <input type="date" name="to" value="<?= h($to) ?>">
      </div>
      <div class="c3">
        <label>Admin</label>
        <select name="admin_id">
          <option value="0">Semua admin saya</option>
          <?php foreach ($admins as $a): ?>
            <option value="<?= (int)$a['id'] ?>" <?= ((int)$a['id']===$adminIdFilter?'selected':'') ?>>
              <?= h($a['name']) ?> (<?= h($a['email']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="c3">
        <label>Action</label>
        <select name="action">
          <option value="">Semua</option>
          <?php foreach ($actions as $ac): ?>
            <option value="<?= h($ac) ?>" <?= ($ac===$action?'selected':'') ?>><?= h($ac) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="c6">
        <label>Cari (message)</label>
        <input name="q" value="<?= h($q) ?>" placeholder="contoh: INV2026 / shift / produk / stok">
      </div>
      <div class="c2">
        <button class="btn primary" type="submit" style="width:100%;justify-content:center">Filter</button>
      </div>
      <div class="c2">
        <a class="btn" href="dev_activity_logs.php" style="width:100%;justify-content:center">Reset</a>
      </div>
    </form>

    <table>
      <thead>
        <tr>
          <th style="width:80px">ID</th>
          <th style="width:165px">Waktu</th>
          <th style="width:240px">Admin Owner</th>
          <th style="width:220px">Pelaku</th>
          <th style="width:160px">Action</th>
          <th style="width:160px">Entity</th>
          <th>Message</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="7" class="muted">Tidak ada log untuk filter ini.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr>
            <td>#<?= (int)$r['id'] ?></td>
            <td><?= h($r['created_at']) ?></td>
            <td>
              <div><b><?= h($r['admin_name'] ?? 'Admin?') ?></b></div>
              <div class="muted"><?= h($r['admin_email'] ?? '') ?> · admin#<?= (int)($r['owner_admin_id'] ?? 0) ?></div>
            </td>
            <td>
              <div><b><?= h($r['actor_name'] ?? 'Unknown') ?></b></div>
              <div class="muted">user#<?= (int)$r['actor_user_id'] ?> · <?= h($r['actor_role']) ?></div>
            </td>
            <td><span class="pill"><?= h($r['action']) ?></span></td>
            <td><?= entity_link($r) ?></td>
            <td><?= h($r['message']) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>

    <div class="pagination">
      <span class="muted">Page <?= (int)$page ?> / <?= (int)$totalPages ?></span>
      <?php if ($page > 1): ?>
        <a class="btn" href="<?= h(build_url(['page'=>$page-1])) ?>">← Prev</a>
      <?php endif; ?>
      <?php if ($page < $totalPages): ?>
        <a class="btn" href="<?= h(build_url(['page'=>$page+1])) ?>">Next →</a>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>

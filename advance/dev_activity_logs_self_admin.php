<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';

require_role(['developer']);
$u = auth_user();

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

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
  } catch (Throwable $e) { return false; }
}

/* =====================
   Schema detection
===================== */
$storesHasName  = hasColumn($pdo, 'stores', 'name');
$storesHasOwner = hasColumn($pdo, 'stores', 'owner_admin_id');

$alHasId        = hasColumn($pdo, 'activity_logs', 'id');
$alHasCreatedAt = hasColumn($pdo, 'activity_logs', 'created_at');
$alHasAction    = hasColumn($pdo, 'activity_logs', 'action');
$alHasMessage   = hasColumn($pdo, 'activity_logs', 'message');
$alHasStoreId   = hasColumn($pdo, 'activity_logs', 'store_id');
$alHasActorRole = hasColumn($pdo, 'activity_logs', 'actor_role');
$alHasActorUser = hasColumn($pdo, 'activity_logs', 'actor_user_id');
$alHasEntityId  = hasColumn($pdo, 'activity_logs', 'entity_id');
$alHasEntityTyp = hasColumn($pdo, 'activity_logs', 'entity_type');
$alHasMetaJson  = hasColumn($pdo, 'activity_logs', 'meta_json');

/* =====================
   Filters
===================== */
$selectedAdminId = (int)($_GET['admin_id'] ?? 0);
$selectedStoreId = (int)($_GET['store_id'] ?? 0);

$from = trim((string)($_GET['from'] ?? ''));
$to   = trim((string)($_GET['to'] ?? ''));
$actorRole = trim((string)($_GET['actor_role'] ?? '')); // admin/kasir/dapur
$action = trim((string)($_GET['action'] ?? ''));
$q = trim((string)($_GET['q'] ?? ''));

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Default tanggal (kalau created_at ada)
if ($alHasCreatedAt) {
  if ($from === '') $from = date('Y-m-d');
  if ($to === '') $to = date('Y-m-d');
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-d');
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = date('Y-m-d');
} else {
  $from = ''; $to = '';
}

/* =====================
   1) Ambil admin self-register (robust)
   Fallback: kalau kolom ga lengkap atau belum ada datanya,
   halaman tetap jalan (lihat semua logs).
===================== */
$selfAdmins = [];
$selfAdminIds = [];

$canFindSelfAdmins = $alHasAction && ($alHasEntityId || $alHasActorUser);
if ($canFindSelfAdmins) {
  $joinKey = $alHasEntityId ? 'al.entity_id' : 'al.actor_user_id';

  $conds = [];
  $conds[] = "al.action='ADMIN_REGISTER'";
  if ($alHasEntityTyp) $conds[] = "al.entity_type='admin'";

  // prefer meta_json bila ada, kalau tidak pakai message
  if ($alHasMetaJson) {
    $conds[] = "(al.meta_json LIKE '%\"method\":\"register_admin\"%'"
            .  " OR al.message LIKE '%self-registered%'"
            .  " OR al.message LIKE '%register_admin%')";
  } elseif ($alHasMessage) {
    $conds[] = "(al.message LIKE '%self-registered%' OR al.message LIKE '%register_admin%')";
  }

  $sqlSelf = "
    SELECT DISTINCT u.id, u.name, u.email
    FROM activity_logs al
    JOIN users u ON u.id = $joinKey
    WHERE " . implode(" AND ", $conds) . "
    ORDER BY u.id DESC
    LIMIT 200
  ";

  try {
    $selfAdminsStmt = $pdo->query($sqlSelf);
    $selfAdmins = $selfAdminsStmt->fetchAll() ?: [];
    $selfAdminIds = array_map(fn($r) => (int)$r['id'], $selfAdmins);
  } catch (Throwable $e) {
    $selfAdmins = [];
    $selfAdminIds = [];
  }
}

/* =====================
   2) Ambil stores milik admin self-register (kalau bisa)
===================== */
$stores = [];
$storesByAdmin = [];
$storeIdsAll = [];

if ($selfAdminIds && $storesHasOwner) {
  $inAdmins = implode(',', array_fill(0, count($selfAdminIds), '?'));
  $cols = ["id"];
  $cols[] = $storesHasName ? "name" : "NULL AS name";
  $cols[] = "owner_admin_id";

  try {
    $stStores = $pdo->prepare("
      SELECT " . implode(",", $cols) . "
      FROM stores
      WHERE owner_admin_id IN ($inAdmins)
      ORDER BY owner_admin_id DESC, id DESC
    ");
    $stStores->execute($selfAdminIds);
    $stores = $stStores->fetchAll() ?: [];
  } catch (Throwable $e) {
    $stores = [];
  }

  $storeIdsAll = array_map(fn($r) => (int)$r['id'], $stores);

  foreach ($stores as $s) {
    $aid = (int)($s['owner_admin_id'] ?? 0);
    $storesByAdmin[$aid] = $storesByAdmin[$aid] ?? [];
    $storesByAdmin[$aid][] = $s;
  }
}

/* =====================
   3) Validasi pilihan admin/store (kalau scope self-admin ada)
===================== */
if ($selfAdminIds) {
  if ($selectedAdminId > 0 && !in_array($selectedAdminId, $selfAdminIds, true)) $selectedAdminId = 0;

  if ($selectedAdminId > 0) {
    $list = $storesByAdmin[$selectedAdminId] ?? [];
    if ($selectedStoreId <= 0 && $list) $selectedStoreId = (int)$list[0]['id'];

    if ($selectedStoreId > 0) {
      $ok = false;
      foreach ($list as $s) {
        if ((int)$s['id'] === $selectedStoreId) { $ok = true; break; }
      }
      if (!$ok) $selectedStoreId = $list ? (int)$list[0]['id'] : 0;
    }
  } else {
    $selectedStoreId = 0;
  }
} else {
  // fallback mode: tidak ada self-admin scope
  $selectedAdminId = 0;
  // store filter boleh tetap dipakai kalau store_id kolom ada, tapi kita tidak punya list storesByAdmin.
}

/* =====================
   4) Store options untuk dropdown
===================== */
$storeOptions = [];
if ($selfAdminIds && $stores) {
  if ($selectedAdminId > 0) $storeOptions = $storesByAdmin[$selectedAdminId] ?? [];
  else $storeOptions = $stores;
}

/* =====================
   5) Action list (opsional)
===================== */
$actions = [];
if ($alHasAction && $alHasStoreId) {
  $storeIdsForAction = [];

  if ($selfAdminIds && $storeIdsAll) {
    if ($selectedStoreId > 0) $storeIdsForAction = [$selectedStoreId];
    elseif ($selectedAdminId > 0) $storeIdsForAction = array_map(fn($s) => (int)$s['id'], ($storesByAdmin[$selectedAdminId] ?? []));
    else $storeIdsForAction = $storeIdsAll;
  } else {
    // fallback: action global
    $storeIdsForAction = [];
  }

  try {
    if ($storeIdsForAction) {
      $inS = implode(',', array_fill(0, count($storeIdsForAction), '?'));
      $stAct = $pdo->prepare("SELECT DISTINCT action FROM activity_logs WHERE store_id IN ($inS) ORDER BY action");
      $stAct->execute($storeIdsForAction);
      $actions = array_map(fn($r) => (string)$r['action'], $stAct->fetchAll() ?: []);
    } else {
      $stAct = $pdo->query("SELECT DISTINCT action FROM activity_logs ORDER BY action LIMIT 200");
      $actions = array_map(fn($r) => (string)$r['action'], $stAct->fetchAll() ?: []);
    }
  } catch (Throwable $e) {
    $actions = [];
  }
}

/* =====================
   6) Query logs (robust)
===================== */
$where = [];
$params = [];

// scope: kalau self-admin ada, batasi ke stores mereka (jika store_id ada)
if ($selfAdminIds && $alHasStoreId && $storeIdsAll) {
  $scopedStoreIds = $storeIdsAll;

  if ($selectedStoreId > 0) $scopedStoreIds = [$selectedStoreId];
  elseif ($selectedAdminId > 0) $scopedStoreIds = array_map(fn($s) => (int)$s['id'], ($storesByAdmin[$selectedAdminId] ?? []));

  if (!$scopedStoreIds) $scopedStoreIds = [-1];
  $inScoped = implode(',', array_fill(0, count($scopedStoreIds), '?'));
  $where[] = "al.store_id IN ($inScoped)";
  $params = array_merge($params, $scopedStoreIds);
}

// date filter
if ($alHasCreatedAt && $from !== '' && $to !== '') {
  $where[] = "DATE(al.created_at) BETWEEN ? AND ?";
  $params[] = $from;
  $params[] = $to;
}

// actor role filter
if ($alHasActorRole && $actorRole !== '' && in_array($actorRole, ['admin','kasir','dapur'], true)) {
  $where[] = "al.actor_role = ?";
  $params[] = $actorRole;
}

// action filter
if ($alHasAction && $action !== '') {
  $where[] = "al.action = ?";
  $params[] = $action;
}

// search message
if ($alHasMessage && $q !== '') {
  $where[] = "(al.message LIKE ?)";
  $params[] = '%' . $q . '%';
}

$whereSql = $where ? implode(' AND ', $where) : '1=1';

function build_url(array $extra=[]): string {
  $base = [
    'from' => $_GET['from'] ?? '',
    'to' => $_GET['to'] ?? '',
    'admin_id' => $_GET['admin_id'] ?? '',
    'store_id' => $_GET['store_id'] ?? '',
    'actor_role' => $_GET['actor_role'] ?? '',
    'action' => $_GET['action'] ?? '',
    'q' => $_GET['q'] ?? '',
    'page' => $_GET['page'] ?? '1',
  ];
  return 'dev_activity_logs_self_admin.php?' . http_build_query(array_merge($base, $extra));
}

// count
$totalRows = 0;
try {
  $countStmt = $pdo->prepare("SELECT COUNT(*) c FROM activity_logs al WHERE $whereSql");
  $countStmt->execute($params);
  $totalRows = (int)($countStmt->fetch()['c'] ?? 0);
} catch (Throwable $e) {
  $totalRows = 0;
}
$totalPages = max(1, (int)ceil($totalRows / $perPage));

// rows select (ambil yang ada saja)
$select = [];
$select[] = $alHasId ? "al.id" : "NULL AS id";
$select[] = $alHasCreatedAt ? "al.created_at" : "NULL AS created_at";
$select[] = $alHasStoreId ? "al.store_id" : "NULL AS store_id";
$select[] = $alHasActorUser ? "al.actor_user_id" : "NULL AS actor_user_id";
$select[] = $alHasActorRole ? "al.actor_role" : "NULL AS actor_role";
$select[] = $alHasAction ? "al.action" : "NULL AS action";
$select[] = $alHasEntityTyp ? "al.entity_type" : "NULL AS entity_type";
$select[] = $alHasEntityId ? "al.entity_id" : "NULL AS entity_id";
$select[] = $alHasMessage ? "al.message" : "NULL AS message";

$join = "";
$select[] = ($alHasActorUser ? "actor.name AS actor_name" : "NULL AS actor_name");
if ($alHasActorUser) $join .= " LEFT JOIN users actor ON actor.id = al.actor_user_id ";

$select[] = ($alHasStoreId ? ($storesHasName ? "s.name AS store_name" : "NULL AS store_name") : "NULL AS store_name");
$select[] = ($alHasStoreId ? ($storesHasOwner ? "s.owner_admin_id" : "NULL AS owner_admin_id") : "NULL AS owner_admin_id");
if ($alHasStoreId) $join .= " LEFT JOIN stores s ON s.id = al.store_id ";

$select[] = (($alHasStoreId && $storesHasOwner) ? "owner.name AS owner_admin_name" : "NULL AS owner_admin_name");
$select[] = (($alHasStoreId && $storesHasOwner) ? "owner.email AS owner_admin_email" : "NULL AS owner_admin_email");
if ($alHasStoreId && $storesHasOwner) $join .= " LEFT JOIN users owner ON owner.id = s.owner_admin_id ";

$rows = [];
try {
  $rowsStmt = $pdo->prepare("
    SELECT " . implode(", ", $select) . "
    FROM activity_logs al
    $join
    WHERE $whereSql
    ORDER BY " . ($alHasId ? "al.id" : ($alHasCreatedAt ? "al.created_at" : "1")) . " DESC
    LIMIT $perPage OFFSET $offset
  ");
  $rowsStmt->execute($params);
  $rows = $rowsStmt->fetchAll() ?: [];
} catch (Throwable $e) {
  $rows = [];
}

function entity_label(array $r): string {
  $type = (string)($r['entity_type'] ?? '');
  $id = (int)($r['entity_id'] ?? 0);
  if ($type === 'sale' && $id > 0) return "sale#$id";
  if ($type === 'cashier_shift' && $id > 0) return "shift#$id";
  if ($type !== '' && $id > 0) return $type . "#$id";
  return "-";
}

$fallbackMode = !$selfAdminIds; // tidak ada data self-register atau schema tidak mendukung
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Dev · Activity Logs (Admin Self-Register)</title>

  <link rel="stylesheet" href="../publik/assets/dev.css">

  <style>
    .container{max-width:1200px;margin:0 auto;padding:16px}
    .card{grid-column:span 12}

    form{display:grid;grid-template-columns:repeat(12,1fr);gap:12px;align-items:end}
    label{display:block;font-size:12px;color:var(--muted);margin-bottom:4px}
    input,select{
      width:100%;
      padding:10px;
      border-radius:12px;
      border:1px solid var(--line);
      background:transparent;
      color:var(--text)
    }
    .c3{grid-column:span 3}.c4{grid-column:span 4}.c6{grid-column:span 6}.c2{grid-column:span 2}

    .pill{display:inline-block;font-size:12px;padding:3px 8px;border:1px solid var(--line);border-radius:999px;color:var(--muted)}
    .notice{margin-top:10px;padding:10px 12px;border:1px solid var(--line);border-radius:12px;background:rgba(220,140,0,.07)}
    .pagination{display:flex;gap:8px;align-items:center;justify-content:flex-end;margin-top:12px;flex-wrap:wrap}

    .tblWrap{overflow:auto;border:1px solid var(--line);border-radius:14px;margin-top:12px}
    table{width:100%;border-collapse:collapse;min-width:980px}
    th,td{border-bottom:1px solid var(--line);padding:10px;vertical-align:top}
    th{font-size:12px;color:var(--muted);text-align:left;white-space:nowrap}

    @media(max-width:900px){.c3,.c4,.c6,.c2{grid-column:span 12}}
  </style>
</head>
<body>

<div class="container">
  <div class="topbar">
    <div class="brand">
      <b>Dev · Activity Logs</b>
      <span><?= h($u['name']) ?> · <?= h($u['email'] ?? '') ?></span>
    </div>
    <div class="actions">
      <a class="btn" href="dev_dashboard.php">Dashboard</a>
      <a class="btn primary" href="<?= h(build_url(['page'=>1])) ?>">Refresh</a>
      <button class="btn" type="button" id="themeBtn">Toggle Theme</button>
    </div>
  </div>

  <div class="grid">
    <section class="card">
      <h3 style="margin-bottom:6px">Admin Self-Register Logs</h3>
      <div class="small">
        <?php if ($fallbackMode): ?>
          Mode fallback: tidak menemukan data self-register (atau schema activity_logs belum lengkap). Menampilkan logs sesuai filter global.
        <?php else: ?>
          Pilih Admin/Toko untuk fokus · total <?= (int)$totalRows ?> log
        <?php endif; ?>
      </div>

      <?php if ($fallbackMode): ?>
        <div class="notice">
          <b>Info:</b> Kalau kamu ingin fitur “self-register” bekerja penuh, pastikan activity_logs punya kolom
          <code>action</code> dan salah satu dari <code>entity_id</code> / <code>actor_user_id</code>, dan ada event <code>ADMIN_REGISTER</code>.
        </div>
      <?php endif; ?>

      <form method="get" style="margin-top:10px">
        <div class="c3">
          <label>Admin Owner (self-register)</label>
          <select name="admin_id" <?= $fallbackMode ? 'disabled' : '' ?>
                  onchange="this.form.page.value=1; this.form.store_id.value=''; this.form.submit();">
            <option value="0">— Pilih admin (opsional) —</option>
            <?php foreach ($selfAdmins as $a): ?>
              <option value="<?= (int)$a['id'] ?>" <?= ((int)$a['id']===$selectedAdminId?'selected':'') ?>>
                <?= h($a['name']) ?> (<?= h($a['email']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="c3">
          <label>Toko</label>
          <select name="store_id" <?= (!$alHasStoreId || $fallbackMode) ? 'disabled' : '' ?>
                  onchange="this.form.page.value=1; this.form.submit();">
            <option value="0">— Semua toko (scope) —</option>
            <?php foreach ($storeOptions as $s): ?>
              <option value="<?= (int)$s['id'] ?>" <?= ((int)$s['id']===$selectedStoreId?'selected':'') ?>>
                <?= h($storesHasName ? ($s['name'] ?? '-') : '-') ?> (store#<?= (int)$s['id'] ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="c3">
          <label>Dari</label>
          <input type="date" name="from" value="<?= h($from) ?>" <?= $alHasCreatedAt ? '' : 'disabled' ?>>
        </div>

        <div class="c3">
          <label>Sampai</label>
          <input type="date" name="to" value="<?= h($to) ?>" <?= $alHasCreatedAt ? '' : 'disabled' ?>>
        </div>

        <div class="c3">
          <label>Actor Role</label>
          <select name="actor_role" <?= $alHasActorRole ? '' : 'disabled' ?>>
            <option value="" <?= ($actorRole===''?'selected':'') ?>>Semua</option>
            <option value="admin" <?= ($actorRole==='admin'?'selected':'') ?>>admin</option>
            <option value="kasir" <?= ($actorRole==='kasir'?'selected':'') ?>>kasir</option>
            <option value="dapur" <?= ($actorRole==='dapur'?'selected':'') ?>>dapur</option>
          </select>
        </div>

        <div class="c3">
          <label>Action</label>
          <select name="action" <?= $alHasAction ? '' : 'disabled' ?>>
            <option value="">Semua</option>
            <?php foreach ($actions as $ac): ?>
              <option value="<?= h($ac) ?>" <?= ($ac===$action?'selected':'') ?>><?= h($ac) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="c6">
          <label>Cari (message)</label>
          <input name="q" value="<?= h($q) ?>" placeholder="contoh: INV2026 / shift / produk" <?= $alHasMessage ? '' : 'disabled' ?>>
        </div>

        <input type="hidden" name="page" value="<?= (int)$page ?>">

        <div class="c2">
          <button class="btn primary" type="submit" style="width:100%;justify-content:center">Filter</button>
        </div>
        <div class="c2">
          <a class="btn" href="dev_activity_logs_self_admin.php" style="width:100%;justify-content:center">Reset</a>
        </div>
      </form>

      <div class="tblWrap">
        <table>
          <thead>
            <tr>
              <th style="width:80px">ID</th>
              <th style="width:165px">Waktu</th>
              <th style="width:260px">Store / Admin Owner</th>
              <th style="width:220px">Pelaku</th>
              <th style="width:150px">Action</th>
              <th style="width:140px">Entity</th>
              <th>Message</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$rows): ?>
              <tr><td colspan="7" class="muted">Tidak ada log untuk filter ini.</td></tr>
            <?php else: foreach ($rows as $r): ?>
              <tr>
                <td>#<?= (int)($r['id'] ?? 0) ?></td>
                <td><?= h((string)($r['created_at'] ?? '-')) ?></td>
                <td>
                  <div><b><?= h((string)($r['store_name'] ?? '-')) ?></b></div>
                  <div class="muted">
                    admin#<?= (int)($r['owner_admin_id'] ?? 0) ?> · <?= h((string)($r['owner_admin_email'] ?? '')) ?>
                  </div>
                </td>
                <td>
                  <div><b><?= h((string)($r['actor_name'] ?? 'Unknown')) ?></b></div>
                  <div class="muted">
                    user#<?= (int)($r['actor_user_id'] ?? 0) ?> · <?= h((string)($r['actor_role'] ?? '-')) ?>
                  </div>
                </td>
                <td><span class="pill"><?= h((string)($r['action'] ?? '-')) ?></span></td>
                <td><?= h(entity_label($r)) ?></td>
                <td><?= h((string)($r['message'] ?? '')) ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <div class="pagination">
        <span class="muted">Page <?= (int)$page ?> / <?= (int)$totalPages ?></span>
        <?php if ($page > 1): ?>
          <a class="btn" href="<?= h(build_url(['page'=>$page-1])) ?>">← Prev</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
          <a class="btn" href="<?= h(build_url(['page'=>$page+1])) ?>">Next →</a>
        <?php endif; ?>
      </div>
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

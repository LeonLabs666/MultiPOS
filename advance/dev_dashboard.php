<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';
require_role(['developer']);

$u = auth_user();
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function fmtBytes(int $b): string {
  $u = ['B','KB','MB','GB','TB']; $i = 0; $v = (float)$b;
  while ($v >= 1024 && $i < count($u)-1) { $v /= 1024; $i++; }
  return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') . ' ' . $u[$i];
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
  } catch (Throwable $e) { return false; }
}

/* =====================
   SYSTEM HEALTH (real)
===================== */
$dbOk = true; $dbErr = '';
try { $pdo->query("SELECT 1")->fetch(); }
catch (Throwable $e) { $dbOk=false; $dbErr=$e->getMessage(); }

$phpVer = PHP_VERSION;
$serverTime = date('Y-m-d H:i:s');
$tz = date_default_timezone_get();
$mem = (int)memory_get_usage(true);
$memPeak = (int)memory_get_peak_usage(true);

/* =====================
   Schema checks
===================== */
$usersHasRole     = hasColumn($pdo, 'users', 'role');
$usersHasIsActive = hasColumn($pdo, 'users', 'is_active');

$storesHasOwner = hasColumn($pdo, 'stores', 'owner_admin_id');
$storesHasName  = hasColumn($pdo, 'stores', 'name');

$shiftsHasStatus      = hasColumn($pdo, 'cashier_shifts', 'status');
$shiftsHasOpenedAt    = hasColumn($pdo, 'cashier_shifts', 'opened_at');
$shiftsHasOpeningCash = hasColumn($pdo, 'cashier_shifts', 'opening_cash');
$shiftsHasStoreId     = hasColumn($pdo, 'cashier_shifts', 'store_id');
$shiftsHasKasirId     = hasColumn($pdo, 'cashier_shifts', 'kasir_id');

$logsHasCreatedAt = hasColumn($pdo, 'activity_logs', 'created_at');
$logsHasAction    = hasColumn($pdo, 'activity_logs', 'action');
$logsHasMessage   = hasColumn($pdo, 'activity_logs', 'message');
$logsHasActorRole = hasColumn($pdo, 'activity_logs', 'actor_role');
$logsHasActorUser = hasColumn($pdo, 'activity_logs', 'actor_user_id');
$logsHasStoreId   = hasColumn($pdo, 'activity_logs', 'store_id');

/* =====================
   COUNTS
===================== */
$usersTotal=0; $adminsTotal=0; $kasirTotal=0; $dapurTotal=0; $devTotal=0;
$inactiveUsers=0; $invalidRoleCount=0;

try {
  $usersTotal = (int)($pdo->query("SELECT COUNT(*) c FROM users")->fetch()['c'] ?? 0);

  if ($usersHasRole) {
    $adminsTotal = (int)($pdo->query("SELECT COUNT(*) c FROM users WHERE role='admin'")->fetch()['c'] ?? 0);
    $kasirTotal  = (int)($pdo->query("SELECT COUNT(*) c FROM users WHERE role='kasir'")->fetch()['c'] ?? 0);
    $dapurTotal  = (int)($pdo->query("SELECT COUNT(*) c FROM users WHERE role='dapur'")->fetch()['c'] ?? 0);
    $devTotal    = (int)($pdo->query("SELECT COUNT(*) c FROM users WHERE role='developer'")->fetch()['c'] ?? 0);
    $invalidRoleCount = (int)($pdo->query("
      SELECT COUNT(*) c FROM users
      WHERE role NOT IN ('admin','kasir','dapur','developer')
    ")->fetch()['c'] ?? 0);
  }

  if ($usersHasIsActive) {
    $inactiveUsers = (int)($pdo->query("SELECT COUNT(*) c FROM users WHERE is_active=0")->fetch()['c'] ?? 0);
  }
} catch (Throwable $e) {}

$storesTotal=0; $storesNoOwner=0; $orphanOwners=0;
try {
  $storesTotal = (int)($pdo->query("SELECT COUNT(*) c FROM stores")->fetch()['c'] ?? 0);

  if ($storesHasOwner) {
    $storesNoOwner = (int)($pdo->query("
      SELECT COUNT(*) c FROM stores
      WHERE owner_admin_id IS NULL OR owner_admin_id = 0
    ")->fetch()['c'] ?? 0);

    $orphanOwners = (int)($pdo->query("
      SELECT COUNT(*) c
      FROM stores s
      LEFT JOIN users u ON u.id = s.owner_admin_id
      WHERE s.owner_admin_id IS NOT NULL
        AND s.owner_admin_id <> 0
        AND u.id IS NULL
    ")->fetch()['c'] ?? 0);
  }
} catch (Throwable $e) {}

$openShifts = 0;
$openShiftRows = [];
if ($shiftsHasStatus) {
  try {
    $openShifts = (int)($pdo->query("SELECT COUNT(*) c FROM cashier_shifts WHERE status='open'")->fetch()['c'] ?? 0);
  } catch (Throwable $e) {}

  try {
    $selectCols = ["cs.id"];
    if ($shiftsHasOpenedAt) $selectCols[] = "cs.opened_at";
    if ($shiftsHasOpeningCash) $selectCols[] = "cs.opening_cash";
    if ($shiftsHasStoreId) $selectCols[] = "cs.store_id";
    if ($shiftsHasKasirId) $selectCols[] = "cs.kasir_id";

    $join = "";
    if ($shiftsHasKasirId) $join .= " LEFT JOIN users u ON u.id = cs.kasir_id ";
    if ($shiftsHasStoreId && $storesHasName) $join .= " LEFT JOIN stores s ON s.id = cs.store_id ";
    if ($shiftsHasKasirId) $selectCols[] = "u.name AS kasir_name";
    if ($shiftsHasStoreId && $storesHasName) $selectCols[] = "s.name AS store_name";

    $sql = "
      SELECT " . implode(", ", $selectCols) . "
      FROM cashier_shifts cs
      $join
      WHERE cs.status='open'
      ORDER BY cs.id DESC
      LIMIT 8
    ";
    $openShiftRows = $pdo->query($sql)->fetchAll() ?: [];
  } catch (Throwable $e) {
    $openShiftRows = [];
  }
}

$logs = [];
if ($logsHasAction && $logsHasMessage && $logsHasCreatedAt) {
  try {
    $selectCols = ["al.id", "al.created_at", "al.action", "al.message"];
    if ($logsHasActorRole) $selectCols[] = "al.actor_role";
    if ($logsHasActorUser) $selectCols[] = "al.actor_user_id";
    if ($logsHasStoreId) $selectCols[] = "al.store_id";

    $join = "";
    if ($logsHasActorUser) $join .= " LEFT JOIN users u ON u.id = al.actor_user_id ";
    if ($logsHasStoreId && $storesHasName) $join .= " LEFT JOIN stores s ON s.id = al.store_id ";
    if ($logsHasActorUser) $selectCols[] = "u.name AS actor_name";
    if ($logsHasStoreId && $storesHasName) $selectCols[] = "s.name AS store_name";

    $sql = "
      SELECT " . implode(", ", $selectCols) . "
      FROM activity_logs al
      $join
      ORDER BY al.id DESC
      LIMIT 10
    ";
    $logs = $pdo->query($sql)->fetchAll() ?: [];
  } catch (Throwable $e) {
    $logs = [];
  }
}

/* =====================
   Health / Alerts
===================== */
$alerts = [];
if (!$dbOk) $alerts[] = ['bad', 'Database error: ' . $dbErr];
if ($invalidRoleCount > 0) $alerts[] = ['warn', "Ada <b>$invalidRoleCount</b> user dengan role tidak valid."];
if ($usersHasIsActive && $inactiveUsers > 0) $alerts[] = ['warn', "Ada <b>$inactiveUsers</b> user nonaktif."];
if ($storesHasOwner && $storesNoOwner > 0) $alerts[] = ['warn', "Ada <b>$storesNoOwner</b> store tanpa owner_admin_id."];
if ($storesHasOwner && $orphanOwners > 0) $alerts[] = ['bad', "Ada <b>$orphanOwners</b> orphan owner_admin_id (user owner sudah hilang)."];
if ($shiftsHasStatus && $openShifts > 0) $alerts[] = ['ok', "Open shifts: <b>$openShifts</b>"];

$health = 'ok';
foreach ($alerts as $a) { if ($a[0] === 'bad') { $health='bad'; break; } }
if ($health !== 'bad') {
  foreach ($alerts as $a) { if ($a[0] === 'warn') { $health='warn'; break; } }
}
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Developer · MultiPOS</title>
  <link rel="stylesheet" href="../publik/assets/dev.css">
  <style>
    .card.full{grid-column:span 12}
    .muted{color:var(--muted)}
    .pill{display:inline-block;font-size:12px;padding:3px 8px;border:1px solid var(--line);border-radius:999px;color:var(--muted)}
    .badge2{display:inline-flex;align-items:center;gap:6px;font-size:12px;padding:4px 10px;border-radius:999px;border:1px solid var(--line)}
    .badge2.ok{background:rgba(0,128,0,.06)}
    .badge2.warn{background:rgba(220,140,0,.07)}
    .badge2.bad{background:rgba(200,0,0,.07)}
    .grid2{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-top:10px}
    @media(max-width:900px){.grid2{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:520px){.grid2{grid-template-columns:1fr}}
    .kcard{border:1px solid var(--line);border-radius:14px;padding:12px}
    .kcard b{font-size:22px}
    .actionsRow{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
    .actionsRow .btn{white-space:nowrap}
    .notice{margin-top:10px;padding:10px 12px;border:1px solid var(--line);border-radius:12px}
    .notice.ok{background:rgba(0,128,0,.06)}
    .notice.warn{background:rgba(220,140,0,.07)}
    .notice.bad{background:rgba(200,0,0,.07)}
    .tblWrap{overflow:auto;border:1px solid var(--line);border-radius:14px;margin-top:10px}
    .tbl{width:100%;border-collapse:collapse;min-width:720px}
    .tbl th,.tbl td{border-bottom:1px solid var(--line);padding:10px;vertical-align:top;text-align:left}
    .tbl th{font-size:12px;color:var(--muted);white-space:nowrap}
    .right{text-align:right}
    .small{font-size:12px;color:var(--muted)}
  </style>
</head>
<body>

<div class="container">
  <div class="topbar">
    <div class="brand">
      <b>MultiPOS · Developer</b>
      <span><?= h($u['name']) ?> · <?= h($u['email'] ?? '') ?></span>
    </div>
    <div class="actions actionsRow">
      <button class="btn" id="themeBtn" type="button">Toggle Theme</button>
      <a class="btn" href="dev_stores.php">Stores</a>
      <a class="btn" href="dev_activity_logs_self_admin.php">Logs Admin Self-Register</a>
      <a class="btn" href="logout.php">Logout</a>
    </div>
  </div>

  <div class="grid">

    <!-- SUMMARY -->
    <section class="card full">
      <div style="display:flex;gap:10px;justify-content:space-between;align-items:flex-start;flex-wrap:wrap">
        <div>
          <h3 style="margin:0">Dashboard</h3>
          <div class="small">Ringkas • cepat dibaca • mobile-friendly</div>
        </div>
        <div class="badge2 <?= $health==='ok'?'ok':($health==='warn'?'warn':'bad') ?>">
          <b style="font-size:14px"><?= $health==='ok'?'OK':($health==='warn'?'WARN':'BAD') ?></b>
          <span class="muted">System Health</span>
        </div>
      </div>

      <div class="grid2">
        <div class="kcard">
          <div class="small">Users</div>
          <b><?= (int)$usersTotal ?></b>
          <div class="small">Dev <?= (int)$devTotal ?> · Admin <?= (int)$adminsTotal ?> · Kasir <?= (int)$kasirTotal ?> · Dapur <?= (int)$dapurTotal ?></div>
        </div>

        <div class="kcard">
          <div class="small">Stores</div>
          <b><?= (int)$storesTotal ?></b>
          <div class="small">
            <?= $storesHasOwner ? ((int)$storesNoOwner . ' tanpa owner') : 'owner_admin_id tidak ada' ?>
          </div>
        </div>

        <div class="kcard">
          <div class="small">Open Shifts</div>
          <b><?= $shiftsHasStatus ? (int)$openShifts : 0 ?></b>
          <div class="small"><?= $shiftsHasStatus ? "status='open'" : "kolom status tidak ada" ?></div>
        </div>



      <?php if ($alerts): ?>
        <div style="margin-top:12px">
          <?php foreach ($alerts as $a): ?>
            <div class="notice <?= h($a[0]) ?>">
              <?= $a[0]==='bad' ? '<b>Issue:</b> ' : ($a[0]==='warn' ? '<b>Check:</b> ' : '<b>Info:</b> ') ?>
              <?= $a[1] ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="actionsRow" style="margin-top:12px">
        <a class="btn primary" href="dev_stores.php">Kelola Stores</a>
        <a class="btn" href="dev_activity_logs_self_admin.php">Audit Self-Register</a>
      </div>

      <div class="small" style="margin-top:8px">Shortcut: <kbd>T</kbd> (Theme)</div>
    </section>

    <!-- OPEN SHIFTS -->
    <section class="card full">
      <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:10px;flex-wrap:wrap">
        <div>
          <h3 style="margin:0">Open Shifts</h3>
          <div class="small">Live dari cashier_shifts (maks 8 baris)</div>
        </div>
        <span class="pill"><?= $shiftsHasStatus ? ((int)$openShifts.' open') : 'disabled' ?></span>
      </div>

      <div class="tblWrap">
        <table class="tbl">
          <thead>
            <tr>
              <th style="width:90px">Shift</th>
              <th>Store</th>
              <th>Kasir</th>
              <th style="width:180px">Opened</th>
              <th class="right" style="width:160px">Opening Cash</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$shiftsHasStatus): ?>
              <tr><td colspan="5" class="small">Panel dimatikan: kolom <code>status</code> tidak ada.</td></tr>
            <?php elseif (!$openShiftRows): ?>
              <tr><td colspan="5" class="small">Tidak ada shift open.</td></tr>
            <?php else: foreach ($openShiftRows as $r): ?>
              <tr>
                <td><span class="pill">shift#<?= (int)$r['id'] ?></span></td>
                <td><b><?= h($r['store_name'] ?? '-') ?></b></td>
                <td>
                  <?= h($r['kasir_name'] ?? 'Unknown') ?>
                  <span class="small"><?= isset($r['kasir_id']) ? '(user#' . (int)$r['kasir_id'] . ')' : '' ?></span>
                </td>
                <td><?= h($r['opened_at'] ?? '-') ?></td>
                <td class="right">
                  <?php $cash = (int)($r['opening_cash'] ?? 0); echo 'Rp ' . number_format($cash, 0, ',', '.'); ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- ACTIVITY LOGS -->
    <section class="card full">
      <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:10px;flex-wrap:wrap">
        <div>
          <h3 style="margin:0">Activity Logs</h3>
          <div class="small">Latest 10 (lintas role/store)</div>
        </div>
        <span class="pill"><?= (int)count($logs) ?> rows</span>
      </div>

      <div class="tblWrap">
        <table class="tbl">
          <thead>
            <tr>
              <th style="width:70px">ID</th>
              <th style="width:160px">Waktu</th>
              <th style="width:140px">Action</th>
              <th>Message</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$logsHasAction || !$logsHasMessage || !$logsHasCreatedAt): ?>
              <tr><td colspan="4" class="small">Kolom activity_logs belum lengkap (butuh created_at, action, message).</td></tr>
            <?php elseif (!$logs): ?>
              <tr><td colspan="4" class="small">Belum ada activity log.</td></tr>
            <?php else: foreach ($logs as $r): ?>
              <tr>
                <td>#<?= (int)$r['id'] ?></td>
                <td><?= h($r['created_at'] ?? '-') ?></td>
                <td><span class="pill"><?= h($r['action'] ?? '-') ?></span></td>
                <td>
                  <div>
                    <b><?= h($r['store_name'] ?? '-') ?></b>
                    <span class="small">
                      · <?= h($r['actor_name'] ?? 'Unknown') ?>
                      <?= isset($r['actor_role']) ? '(' . h($r['actor_role']) . ')' : '' ?>
                    </span>
                  </div>
                  <div class="small"><?= h($r['message'] ?? '') ?></div>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
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

  document.getElementById('themeBtn').addEventListener('click', toggle);

  // keyboard shortcut: T
  window.addEventListener('keydown', (e) => {
    if (e.key === 't' || e.key === 'T') toggle();
  });
})();
</script>

</body>
</html>

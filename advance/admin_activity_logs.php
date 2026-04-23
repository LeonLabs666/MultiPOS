<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';

require_role(['admin']);

$appName = 'MultiPOS';
$pageTitle = 'Log Aktivitas';
$activeMenu = 'activity_logs';

$admin = auth_user();
$adminId = (int)$admin['id'];

// Resolve store milik admin
$st = $pdo->prepare("SELECT id, name FROM stores WHERE owner_admin_id=? AND is_active=1 LIMIT 1");
$st->execute([$adminId]);
$store = $st->fetch();
if (!$store) { http_response_code(400); exit('Toko admin tidak ditemukan / nonaktif.'); }

$storeId = (int)$store['id'];
$storeName = (string)$store['name'];

// ====== Helpers UI/UX ======
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function role_label(string $role): string {
  return match ($role) {
    'admin' => 'Admin',
    'kasir' => 'Kasir',
    'dapur' => 'Dapur',
    default => ucfirst($role),
  };
}

function action_label(string $a): string {
  $map = [
    'SALE_CREATE' => 'Transaksi dibuat',
    'SALE_UPDATE' => 'Transaksi diubah',
    'SALE_VOID'   => 'Transaksi dibatalkan',

    'SHIFT_OPEN'  => 'Shift dibuka',
    'SHIFT_CLOSE' => 'Shift ditutup',

    'CASH_IN'     => 'Kas masuk',
    'CASH_OUT'    => 'Kas keluar',

    'STOCK_IN'    => 'Stok masuk',
    'STOCK_OUT'   => 'Stok keluar',
    'STOCK_OPNAME'=> 'Opname stok',

    'KASIR_SETTINGS_UPDATE' => 'Pengaturan kasir diubah',
    'ADMIN_REGISTER' => 'Admin dibuat',
    'ADMIN_LOGIN'    => 'Admin login',
    'KASIR_LOGIN'    => 'Kasir login',
  ];
  return $map[$a] ?? $a;
}

function action_badge_class(string $a): string {
  $a = strtoupper($a);
  if (str_contains($a, 'CASH_IN') || str_contains($a, 'STOCK_IN') || str_contains($a, 'SHIFT_OPEN')) return 'badge ok';
  if (str_contains($a, 'CASH_OUT') || str_contains($a, 'STOCK_OUT') || str_contains($a, 'SHIFT_CLOSE') || str_contains($a, 'VOID')) return 'badge danger';
  if (str_contains($a, 'UPDATE') || str_contains($a, 'SETTINGS')) return 'badge info';
  return 'badge';
}

function entity_label(array $r): string {
  $type = (string)($r['entity_type'] ?? '');
  $id   = (int)($r['entity_id'] ?? 0);

  return match ($type) {
    'sale'          => $id > 0 ? "Transaksi #{$id}" : 'Transaksi',
    'cashier_shift' => $id > 0 ? "Shift #{$id}" : 'Shift',
    'cash_movement' => $id > 0 ? "Kas #{$id}" : 'Kas',
    'product'       => $id > 0 ? "Produk #{$id}" : 'Produk',
    'ingredient'    => $id > 0 ? "Bahan #{$id}" : 'Bahan',
    'store'         => $id > 0 ? "Toko #{$id}" : 'Toko',
    'admin'         => $id > 0 ? "Admin #{$id}" : 'Admin',
    default         => ($type !== '' && $id > 0) ? (ucfirst($type) . " #{$id}") : ($type !== '' ? ucfirst($type) : '-'),
  };
}

function entity_link(array $r): string {
  $type = (string)($r['entity_type'] ?? '');
  $id   = (int)($r['entity_id'] ?? 0);

  if ($type === 'sale' && $id > 0) {
    return '<a href="admin_sale_detail.php?id=' . $id . '" style="text-decoration:none;">' . h(entity_label($r)) . '</a>';
  }
  return h(entity_label($r));
}

function friendly_message(string $msg): string {
  $m = trim($msg);

  if (preg_match('/Checkout\s+(INV[0-9\-]+).*total\s+([0-9]+)/i', $m, $mm)) {
    $inv = $mm[1];
    $tot = (int)$mm[2];
    return "Checkout {$inv} • Total Rp " . number_format($tot, 0, ',', '.');
  }
  if (preg_match('/Open shift\s+#?(\d+)\s+\(opening_cash\s+([0-9]+)\)/i', $m, $mm)) {
    return "Shift dibuka #{$mm[1]} • Modal Rp " . number_format((int)$mm[2], 0, ',', '.');
  }
  if (preg_match('/Close shift\s+#?(\d+)\s+\(closing_cash\s+([0-9]+)\)/i', $m, $mm)) {
    return "Shift ditutup #{$mm[1]} • Kas akhir Rp " . number_format((int)$mm[2], 0, ',', '.');
  }
  if (preg_match('/Kas\s+masuk.*\((\-?[0-9]+)\)/i', $m, $mm)) {
    return "Kas masuk • Rp " . number_format((int)$mm[1], 0, ',', '.');
  }

  return $m;
}

function build_url(array $extra = []): string {
  $base = [
    'from' => $_GET['from'] ?? '',
    'to' => $_GET['to'] ?? '',
    'action' => $_GET['action'] ?? '',
    'kasir_id' => $_GET['kasir_id'] ?? '',
    'q' => $_GET['q'] ?? '',
    'page' => $_GET['page'] ?? '1',
  ];
  $merged = array_merge($base, $extra);
  return 'admin_activity_logs.php?' . http_build_query($merged);
}

// ===== Filters =====
$from = trim((string)($_GET['from'] ?? ''));
$to   = trim((string)($_GET['to'] ?? ''));
$action = trim((string)($_GET['action'] ?? ''));
$kasirId = (int)($_GET['kasir_id'] ?? 0);
$q = trim((string)($_GET['q'] ?? ''));

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Default: today
if ($from === '') $from = date('Y-m-d');
if ($to === '') $to = date('Y-m-d');

// validate date
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = date('Y-m-d');

$where = ["al.store_id = :store_id", "DATE(al.created_at) BETWEEN :from AND :to"];
$params = [
  ':store_id' => $storeId,
  ':from' => $from,
  ':to' => $to,
];

if ($action !== '') {
  $where[] = "al.action = :action";
  $params[':action'] = $action;
}
if ($kasirId > 0) {
  $where[] = "al.actor_user_id = :kasir_id";
  $params[':kasir_id'] = $kasirId;
}
if ($q !== '') {
  $where[] = "al.message LIKE :q";
  $params[':q'] = '%' . $q . '%';
}
$whereSql = implode(' AND ', $where);

// kasir list
$kasirList = $pdo->prepare("SELECT id, name, email FROM users WHERE role='kasir' AND created_by=? AND is_active=1 ORDER BY name");
$kasirList->execute([$adminId]);
$kasirs = $kasirList->fetchAll();

// action list
$actStmt = $pdo->prepare("SELECT DISTINCT action FROM activity_logs WHERE store_id=? ORDER BY action");
$actStmt->execute([$storeId]);
$actions = array_map(fn($r) => (string)$r['action'], $actStmt->fetchAll());

// count
$countStmt = $pdo->prepare("SELECT COUNT(*) c FROM activity_logs al WHERE $whereSql");
$countStmt->execute($params);
$totalRows = (int)($countStmt->fetch()['c'] ?? 0);
$totalPages = max(1, (int)ceil($totalRows / $perPage));

// rows
$listStmt = $pdo->prepare("
  SELECT
    al.id, al.created_at, al.actor_user_id, al.actor_role, al.action,
    al.entity_type, al.entity_id, al.message,
    u.name AS actor_name
  FROM activity_logs al
  LEFT JOIN users u ON u.id = al.actor_user_id
  WHERE $whereSql
  ORDER BY al.id DESC
  LIMIT $perPage OFFSET $offset
");
$listStmt->execute($params);
$rows = $listStmt->fetchAll();

require __DIR__ . '/../publik/partials/admin_layout_top.php';
?>

<style>
  .log-wrap{max-width:1200px;}
  .muted{color:#64748b}
  .card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:14px;}
  .btn{padding:10px 14px;border-radius:12px;border:1px solid #2563eb;background:#2563eb;color:#fff;font-weight:800;cursor:pointer}
  .btn-ghost{padding:10px 14px;border-radius:12px;border:1px solid #e2e8f0;background:#fff;color:#0f172a;font-weight:800;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center}
  input,select{padding:10px 12px;border-radius:12px;border:1px solid #e2e8f0;width:100%}
  label{display:block;font-size:12px;color:#64748b;margin-bottom:4px}

  .badge{display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:999px;border:1px solid #e2e8f0;background:#fff;color:#334155;font-size:12px;font-weight:800;white-space:nowrap}
  .badge.ok{background:#ecfdf5;border-color:#bbf7d0;color:#166534}
  .badge.danger{background:#fef2f2;border-color:#fecaca;color:#991b1b}
  .badge.info{background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8}

  .filters{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:10px;align-items:end}
  .col-3{grid-column:span 3;}
  .col-6{grid-column:span 6;}
  @media (max-width: 900px){
    .col-3,.col-6{grid-column:span 12;}
  }

  table{width:100%;border-collapse:collapse}
  th,td{border-bottom:1px solid #f1f5f9;padding:10px 8px;text-align:left;font-size:13px;vertical-align:top}
  th{color:#64748b;font-weight:900;font-size:11px;letter-spacing:.06em;text-transform:uppercase}

  /* ===== MOBILE: compact list ===== */
  .m-list{display:none}
  @media (max-width: 700px){
    .desktop-table{display:none}
    .m-list{display:block}
    .m-item{
      border:1px solid #e2e8f0;border-radius:14px;padding:10px 12px;background:#fff;
      display:flex;flex-direction:column;gap:6px;
    }
    .m-top{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
    .m-time{color:#64748b;font-size:12px;white-space:nowrap}
    .m-detail{font-size:13px;color:#0f172a;line-height:1.35}
    .m-meta{display:flex;gap:10px;flex-wrap:wrap;color:#64748b;font-size:12px}
    .m-meta b{color:#334155}
    .m-stack{display:flex;flex-direction:column;gap:10px}
  }
</style>

<div class="log-wrap">
  <h1 style="margin:0 0 6px;"><?= h($pageTitle) ?></h1>
  <p class="muted" style="margin:0 0 16px;">
    <?= h($storeName) ?> · total <b><?= (int)$totalRows ?></b> aktivitas · default tampil hari ini
  </p>

  <div class="card" style="margin-bottom:12px;">
    <form method="get" class="filters">
      <div class="col-3">
        <label>Dari</label>
        <input type="date" name="from" value="<?= h($from) ?>">
      </div>
      <div class="col-3">
        <label>Sampai</label>
        <input type="date" name="to" value="<?= h($to) ?>">
      </div>
      <div class="col-3">
        <label>Kasir</label>
        <select name="kasir_id">
          <option value="0">Semua</option>
          <?php foreach ($kasirs as $k): ?>
            <option value="<?= (int)$k['id'] ?>" <?= ((int)$k['id']===$kasirId?'selected':'') ?>>
              <?= h($k['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-3">
        <label>Jenis Aktivitas</label>
        <select name="action">
          <option value="">Semua</option>
          <?php foreach ($actions as $a): ?>
            <option value="<?= h($a) ?>" <?= ($a===$action?'selected':'') ?>>
              <?= h(action_label($a)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-6">
        <label>Cari (detail)</label>
        <input name="q" value="<?= h($q) ?>" placeholder="contoh: INV2026 / shift / stok">
      </div>

      <div class="col-3">
        <button type="submit" class="btn" style="width:100%;">Filter</button>
      </div>
      <div class="col-3">
        <a href="admin_activity_logs.php" class="btn-ghost" style="width:100%;">Reset</a>
      </div>
    </form>
  </div>

  <div class="card">
    <!-- DESKTOP TABLE -->
    <div class="desktop-table" style="overflow:auto;">
      <table style="min-width:900px;">
        <thead>
          <tr>
            <th style="width:170px;">Waktu</th>
            <th style="width:220px;">Pelaku</th>
            <th style="width:190px;">Aktivitas</th>
            <th style="width:180px;">Objek</th>
            <th>Detail</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="5" style="padding:12px;color:#64748b;">Tidak ada aktivitas untuk filter ini.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <?php
            $actorName = (string)($r['actor_name'] ?? 'Unknown');
            $actorRole = role_label((string)($r['actor_role'] ?? ''));
            $actCode   = (string)($r['action'] ?? '');
            $actLabel  = action_label($actCode);
            $msg       = friendly_message((string)($r['message'] ?? ''));
          ?>
          <tr>
            <td><?= h($r['created_at']) ?></td>
            <td>
              <div style="font-weight:900;"><?= h($actorName) ?></div>
              <div class="muted" style="font-size:12px;"><?= h($actorRole) ?></div>
            </td>
            <td><span class="<?= h(action_badge_class($actCode)) ?>"><?= h($actLabel) ?></span></td>
            <td><?= entity_link($r) ?></td>
            <td><?= h($msg) ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- MOBILE COMPACT LIST -->
    <div class="m-list">
      <?php if(!$rows): ?>
        <div class="muted">Tidak ada aktivitas untuk filter ini.</div>
      <?php else: ?>
        <div class="m-stack">
          <?php foreach($rows as $r): ?>
            <?php
              $actorName = (string)($r['actor_name'] ?? 'Unknown');
              $actorRole = role_label((string)($r['actor_role'] ?? ''));
              $actCode   = (string)($r['action'] ?? '');
              $actLabel  = action_label($actCode);
              $msg       = friendly_message((string)($r['message'] ?? ''));
              $obj       = strip_tags(entity_link($r));
            ?>
            <div class="m-item">
              <div class="m-top">
                <span class="<?= h(action_badge_class($actCode)) ?>"><?= h($actLabel) ?></span>
                <span class="m-time"><?= h($r['created_at']) ?></span>
              </div>
              <div class="m-detail"><?= h($msg !== '' ? $msg : '-') ?></div>
              <div class="m-meta">
                <span><b><?= h($actorName) ?></b> · <?= h($actorRole) ?></span>
                <span>• <?= h($obj) ?></span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div style="display:flex;gap:10px;justify-content:flex-end;align-items:center;margin-top:12px;flex-wrap:wrap;">
      <span class="muted" style="font-size:12px;">Page <?= (int)$page ?> / <?= (int)$totalPages ?></span>
      <?php if ($page > 1): ?>
        <a href="<?= h(build_url(['page'=>$page-1])) ?>" class="btn-ghost">← Prev</a>
      <?php endif; ?>
      <?php if ($page < $totalPages): ?>
        <a href="<?= h(build_url(['page'=>$page+1])) ?>" class="btn-ghost">Next →</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../publik/partials/admin_layout_bottom.php'; ?>

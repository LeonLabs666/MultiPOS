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

$storesHasName  = hasColumn($pdo, 'stores', 'name');
$storesHasOwner = hasColumn($pdo, 'stores', 'owner_admin_id');

$ok  = trim((string)($_GET['ok'] ?? ''));
$err = trim((string)($_GET['err'] ?? ''));

$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if ($q !== '') {
  if ($storesHasName) {
    $where[] = "(s.name LIKE ? OR CAST(s.id AS CHAR) LIKE ?)";
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
  } else {
    $where[] = "CAST(s.id AS CHAR) LIKE ?";
    $params[] = '%' . $q . '%';
  }
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$select = ["s.*"];
$join = "";

if ($storesHasOwner) {
  $select[] = "u.name AS owner_name";
  $select[] = "u.email AS owner_email";
  $join .= " LEFT JOIN users u ON u.id = s.owner_admin_id ";
}

$countStmt = $pdo->prepare("SELECT COUNT(*) c FROM stores s $whereSql");
$countStmt->execute($params);
$total = (int)($countStmt->fetch()['c'] ?? 0);
$totalPages = max(1, (int)ceil($total / $perPage));

$sql = "
  SELECT " . implode(", ", $select) . "
  FROM stores s
  $join
  $whereSql
  ORDER BY s.id DESC
  LIMIT $perPage OFFSET $offset
";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll() ?: [];

function build_url(array $extra=[]): string {
  $base = [
    'q' => $_GET['q'] ?? '',
    'page' => $_GET['page'] ?? '1',
  ];
  return 'dev_stores.php?' . http_build_query(array_merge($base, $extra));
}
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Dev · Stores</title>
  <link rel="stylesheet" href="../publik/assets/dev.css">
  <style>
    .card.full{grid-column:span 12}
    .tbl{width:100%;border-collapse:collapse;margin-top:10px}
    .tbl th,.tbl td{border-bottom:1px solid var(--line);padding:10px;vertical-align:top;text-align:left}
    .tbl th{font-size:12px;color:var(--muted)}
    .pill{display:inline-block;font-size:12px;padding:3px 8px;border:1px solid var(--line);border-radius:999px;color:var(--muted)}
    .right{text-align:right}
    .row-actions{display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap}
    .notice{margin-top:12px;padding:10px 12px;border:1px solid var(--line);border-radius:12px}
    .notice.ok{background:rgba(0,128,0,.06)}
    .notice.err{background:rgba(200,0,0,.06)}

    /* Search bar mobile friendly */
    .searchRow{display:flex;gap:10px;flex-wrap:wrap;margin-top:10px;align-items:center}
    .searchRow input{min-width:240px;flex:1}
    .searchMeta{margin-left:auto;white-space:nowrap}

    /* Mobile: table -> card list */
    .storeCards{display:none;margin-top:10px;gap:10px}
    .storeCard{border:1px solid var(--line);border-radius:14px;padding:12px}
    .storeCard .top{display:flex;gap:10px;justify-content:space-between;align-items:flex-start}
    .storeCard .name{font-weight:700}
    .storeCard .meta{margin-top:6px;display:grid;gap:6px}
    .storeCard .meta .small{color:var(--muted);font-size:12px}
    .storeCard .actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}

    .paging{display:flex;gap:8px;justify-content:flex-end;margin-top:12px;flex-wrap:wrap;align-items:center}

    @media(max-width:720px){
      .tbl{display:none}
      .storeCards{display:grid}
      .searchRow{display:grid;grid-template-columns:1fr;gap:10px}
      .searchRow input{min-width:0;width:100%}
      .searchRow .btn{width:100%;justify-content:center}
      .searchMeta{margin-left:0}
      .row-actions{justify-content:flex-start}
      .paging{justify-content:space-between}
    }
  </style>
</head>
<body>

<div class="container">
  <div class="topbar">
    <div class="brand">
      <b>Dev · Stores</b>
      <span><?= h($u['name']) ?> · <?= h($u['email'] ?? '') ?></span>
    </div>
    <div class="actions">
      <a class="btn" href="dev_dashboard.php">Dashboard</a>
      <button class="btn" id="themeBtn" type="button">Toggle Theme</button>
    </div>
  </div>

  <div class="grid">
    <section class="card full">
      <h3>Semua Toko</h3>
      <div class="small">Cari toko lalu klik Detail untuk monitoring & tindakan developer.</div>

      <?php if ($ok): ?><div class="notice ok"><b>OK:</b> <?= h($ok) ?></div><?php endif; ?>
      <?php if ($err): ?><div class="notice err"><b>Error:</b> <?= h($err) ?></div><?php endif; ?>

      <form method="get" class="searchRow">
        <input name="q" value="<?= h($q) ?>" placeholder="Cari: nama toko / id">
        <button class="btn primary" type="submit">Search</button>
        <a class="btn" href="dev_stores.php">Reset</a>
        <span class="small searchMeta">Total: <b><?= (int)$total ?></b> store</span>
      </form>

      <!-- DESKTOP TABLE -->
      <table class="tbl">
        <thead>
          <tr>
            <th style="width:90px">ID</th>
            <th>Toko</th>
            <?php if ($storesHasOwner): ?>
              <th style="width:320px">Owner Admin</th>
            <?php endif; ?>
            <th style="width:180px" class="right">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="<?= $storesHasOwner ? 4 : 3 ?>" class="small">Tidak ada toko.</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td><span class="pill">store#<?= (int)$r['id'] ?></span></td>
              <td>
                <div><b><?= h($storesHasName ? ($r['name'] ?? '-') : '-') ?></b></div>
                <div class="small">admin owner: <?= $storesHasOwner ? ('#'.(int)($r['owner_admin_id'] ?? 0)) : '-' ?></div>
              </td>
              <?php if ($storesHasOwner): ?>
                <td>
                  <div><b><?= h($r['owner_name'] ?? '-') ?></b></div>
                  <div class="small"><?= h($r['owner_email'] ?? '') ?><?= isset($r['owner_admin_id']) ? ' · admin#'.(int)$r['owner_admin_id'] : '' ?></div>
                </td>
              <?php endif; ?>
              <td class="right">
                <div class="row-actions">
                  <a class="btn" href="dev_store_detail.php?id=<?= (int)$r['id'] ?>">Detail</a>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>

      <!-- MOBILE CARD LIST -->
      <div class="storeCards">
        <?php if (!$rows): ?>
          <div class="storeCard">
            <div class="small">Tidak ada toko.</div>
          </div>
        <?php else: foreach ($rows as $r): ?>
          <div class="storeCard">
            <div class="top">
              <div>
                <div class="name"><?= h($storesHasName ? ($r['name'] ?? '-') : '-') ?></div>
                <div class="small"><span class="pill">store#<?= (int)$r['id'] ?></span></div>
              </div>
              <div class="small" style="text-align:right">
                <?= $storesHasOwner ? ('admin#'.(int)($r['owner_admin_id'] ?? 0)) : '' ?>
              </div>
            </div>

            <?php if ($storesHasOwner): ?>
              <div class="meta">
                <div>
                  <div class="small">Owner</div>
                  <div><b><?= h($r['owner_name'] ?? '-') ?></b></div>
                  <div class="small"><?= h($r['owner_email'] ?? '') ?></div>
                </div>
              </div>
            <?php endif; ?>

            <div class="actions">
              <a class="btn primary" href="dev_store_detail.php?id=<?= (int)$r['id'] ?>" style="width:100%;justify-content:center">Detail</a>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>

      <div class="paging">
        <span class="small">Page <?= (int)$page ?> / <?= (int)$totalPages ?></span>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <?php if ($page > 1): ?>
            <a class="btn" href="<?= h(build_url(['page'=>$page-1])) ?>">← Prev</a>
          <?php endif; ?>
          <?php if ($page < $totalPages): ?>
            <a class="btn" href="<?= h(build_url(['page'=>$page+1])) ?>">Next →</a>
          <?php endif; ?>
        </div>
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
})();
</script>

</body>
</html>

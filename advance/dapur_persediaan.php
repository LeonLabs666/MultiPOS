<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';
require_role(['dapur']);

$dapurId = (int)auth_user()['id'];

// Ambil store dari admin pembuat akun dapur
$st = $pdo->prepare("
  SELECT s.id, s.name
  FROM users u
  JOIN stores s ON s.owner_admin_id = u.created_by
  WHERE u.id=? AND s.is_active=1
  LIMIT 1
");
$st->execute([$dapurId]);
$store = $st->fetch();
if (!$store) { http_response_code(400); exit('User dapur belum terhubung ke toko.'); }

$storeId   = (int)$store['id'];
$storeName = (string)$store['name'];

// UI vars untuk layout dapur
$appName    = 'MultiPOS';
$pageTitle  = 'Persediaan';
$activeMenu = 'persediaan';
$userName   = (string)auth_user()['name'];

// filters
$tab = (string)($_GET['tab'] ?? 'produk'); // produk | bahan
if (!in_array($tab, ['produk','bahan'], true)) $tab = 'produk';

$q = trim((string)($_GET['q'] ?? ''));
if (mb_strlen($q) > 50) $q = mb_substr($q, 0, 50);

$limit = (int)($_GET['limit'] ?? 200);
if ($limit < 20) $limit = 20;
if ($limit > 500) $limit = 500;

// fetch data
$products = [];
$ingredients = [];

if ($tab === 'produk') {
  $sql = "SELECT id, sku, name, stock, is_active FROM products WHERE store_id=? ";
  $params = [$storeId];
  if ($q !== '') {
    $sql .= " AND (name LIKE ? OR sku LIKE ?) ";
    $params[] = "%$q%";
    $params[] = "%$q%";
  }
  $sql .= " ORDER BY name ASC LIMIT " . (int)$limit;

  $st = $pdo->prepare($sql);
  $st->execute($params);
  $products = $st->fetchAll() ?: [];
} else {
  $sql = "SELECT id, name, unit, stock, is_active FROM ingredients WHERE store_id=? ";
  $params = [$storeId];
  if ($q !== '') {
    $sql .= " AND name LIKE ? ";
    $params[] = "%$q%";
  }
  $sql .= " ORDER BY name ASC LIMIT " . (int)$limit;

  $st = $pdo->prepare($sql);
  $st->execute($params);
  $ingredients = $st->fetchAll() ?: [];
}

require __DIR__ . '/../publik/partials/dapur_layout_top.php';
require __DIR__ . '/../publik/partials/dapur_sidebar.php';
?>

<main class="content">

  <style>
    .tabs{display:flex; gap:10px; flex-wrap:wrap; margin-bottom:14px;}
    .tabbtn{
      display:inline-flex; align-items:center; gap:8px;
      padding:10px 12px; border-radius: 14px;
      border:1px solid rgba(15,23,42,.10);
      background:#fff; text-decoration:none;
      font-weight: 950; color:#0f172a;
      justify-content: space-between;
    }
    .tabbtn.active{
      border-color: rgba(37,99,235,.40);
      background: rgba(37,99,235,.08);
      color:#1d4ed8;
    }
    table{width:100%; border-collapse:collapse;}
    th, td{padding:10px; border-bottom:1px solid rgba(15,23,42,.08); text-align:left; vertical-align:top;}
    th{font-size:12px; opacity:.8;}
    .right{text-align:right;}
    .pill{display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;border:1px solid rgba(15,23,42,.10)}
    .pill.ok{background:#ecfdf5;border-color:#bbf7d0;color:#166534}
    .pill.off{background:#fef2f2;border-color:#fecaca;color:#991b1b}
    .muted{opacity:.75;}
    .row2{display:flex; gap:10px; flex-wrap:wrap; align-items:end;}
    .row2 input, .row2 select{max-width: 260px;}
    .card { overflow:auto; } /* biar table aman di mobile */

    @media (max-width: 640px){
      .row2 input, .row2 select{width:100% !important; max-width:none;}
      .btn{width:100%; justify-content:center;}
      .tabs{flex-wrap:nowrap; overflow-x:auto; -webkit-overflow-scrolling:touch; padding-bottom:6px;}
      .tabbtn{flex:0 0 auto; min-width:170px;}
    }
  </style>

  <div class="topbar">
    <div class="left">
      <button class="icon-btn" type="button"
        onclick="window.__dapurSidebarToggle && window.__dapurSidebarToggle()"
        aria-label="Menu">☰</button>

      <div>
        <p class="h" style="margin:0;">Persediaan</p>
        <p class="p">Ringkasan stok produk & bahan.</p>
      </div>
    </div>
    <div class="right">

    </div>
  </div>

  <div class="tabs">
    <a class="tabbtn <?= $tab==='produk'?'active':'' ?>" href="dapur_persediaan.php?tab=produk&q=<?= urlencode($q) ?>&limit=<?= (int)$limit ?>">
      📦 Produk
      <span class="pill"><?= count($products) ?></span>
    </a>
    <a class="tabbtn <?= $tab==='bahan'?'active':'' ?>" href="dapur_persediaan.php?tab=bahan&q=<?= urlencode($q) ?>&limit=<?= (int)$limit ?>">
      🧂 Bahan
      <span class="pill"><?= count($ingredients) ?></span>
    </a>
  </div>

  <div class="card" style="margin-bottom:14px;">
    <form method="get" class="row2" style="margin:0;">
      <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">

      <div>
        <label class="small muted" style="display:block;margin-bottom:6px;">Cari</label>
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="<?= $tab==='produk' ? 'Nama / SKU produk...' : 'Nama bahan...' ?>">
      </div>

      <div>
        <label class="small muted" style="display:block;margin-bottom:6px;">Limit</label>
        <select name="limit">
          <?php foreach ([50,100,200,300,500] as $opt): ?>
            <option value="<?= $opt ?>" <?= $limit===$opt?'selected':'' ?>><?= $opt ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <button class="btn" type="submit">Terapkan</button>
        <a class="btn" href="dapur_persediaan.php?tab=<?= urlencode($tab) ?>">Reset</a>
      </div>
    </form>
  </div>

  <div class="card">
    <?php if ($tab === 'produk'): ?>

      <?php if (!$products): ?>
        <div class="muted">Belum ada data produk.</div>
      <?php else: ?>
        <table>
          <tr>
            <th>Produk</th>
            <th>SKU</th>
            <th class="right">Stok</th>
            <th>Status</th>
          </tr>
          <?php foreach ($products as $p): ?>
            <tr>
              <td><?= htmlspecialchars((string)$p['name']) ?></td>
              <td class="muted"><?= htmlspecialchars((string)($p['sku'] ?? '')) ?></td>
              <td class="right"><b><?= (int)$p['stock'] ?></b> pcs</td>
              <td>
                <?php if ((int)$p['is_active'] === 1): ?>
                  <span class="pill ok">Aktif</span>
                <?php else: ?>
                  <span class="pill off">Nonaktif</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>

    <?php else: ?>

      <?php if (!$ingredients): ?>
        <div class="muted">Belum ada data bahan.</div>
      <?php else: ?>
        <table>
          <tr>
            <th>Bahan</th>
            <th>Satuan</th>
            <th class="right">Stok</th>
            <th>Status</th>
          </tr>
          <?php foreach ($ingredients as $i): ?>
            <tr>
              <td><?= htmlspecialchars((string)$i['name']) ?></td>
              <td class="muted"><?= htmlspecialchars((string)($i['unit'] ?? '')) ?></td>
              <td class="right">
                <b><?= rtrim(rtrim(number_format((float)$i['stock'], 3, '.', ''), '0'), '.') ?></b>
                <?= htmlspecialchars((string)($i['unit'] ?? '')) ?>
              </td>
              <td>
                <?php if ((int)$i['is_active'] === 1): ?>
                  <span class="pill ok">Aktif</span>
                <?php else: ?>
                  <span class="pill off">Nonaktif</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>

    <?php endif; ?>
  </div>

</main>

<?php require __DIR__ . '/../publik/partials/dapur_layout_bottom.php'; ?>

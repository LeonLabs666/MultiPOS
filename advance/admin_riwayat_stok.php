<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';
require_role(['admin']);

$appName = 'MultiPOS';
$pageTitle = 'Riwayat Stok';
$activeMenu = 'persediaan';
$adminId = (int)auth_user()['id'];

$error = '';
$ok = '';

// ambil toko admin
$st = $pdo->prepare("SELECT id,name FROM stores WHERE owner_admin_id=? AND is_active=1 LIMIT 1");
$st->execute([$adminId]);
$store = $st->fetch();
if (!$store) { http_response_code(400); exit('Admin belum terhubung ke toko.'); }

$storeId = (int)$store['id'];
$storeName = (string)$store['name'];

// ===== FILTER INPUT =====
$target = (string)($_GET['target'] ?? 'all'); // all|product|ingredient|opname
if (!in_array($target, ['all','product','ingredient','opname'], true)) $target = 'all';

$dir = (string)($_GET['dir'] ?? 'all'); // all|in|out
if (!in_array($dir, ['all','in','out'], true)) $dir = 'all';

$q = trim((string)($_GET['q'] ?? ''));
if (strlen($q) > 80) $q = substr($q, 0, 80);

$from = trim((string)($_GET['from'] ?? '')); // YYYY-MM-DD
$to   = trim((string)($_GET['to'] ?? ''));   // YYYY-MM-DD

$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;

$limit = 50;

$dateRe = '/^\d{4}-\d{2}-\d{2}$/';

// ===== UNION SOURCE DATA =====
// 1) stock_movements (mutasi masuk/keluar)
// 2) stock_opnames + stock_opname_items (selisih opname per item)
$unionSql = "
  SELECT 
    CONCAT('M', m.id) AS row_id,
    m.created_at,
    m.target_type,
    m.target_id,
    CASE WHEN m.target_type='product' THEN p.name ELSE i.name END AS item_name,
    CASE WHEN m.target_type='product' THEN COALESCE(p.sku,'') ELSE '' END AS sku,
    m.direction,
    m.qty,
    COALESCE(m.unit,'') AS unit,
    COALESCE(m.note,'') AS note,
    COALESCE(u.name, '') AS user_name,
    'mutasi' AS source,
    m.id AS ref_id
  FROM stock_movements m
  LEFT JOIN products p ON (m.target_type='product' AND p.id=m.target_id)
  LEFT JOIN ingredients i ON (m.target_type='ingredient' AND i.id=m.target_id)
  LEFT JOIN users u ON u.id = m.created_by
  WHERE m.store_id = :store_id

  UNION ALL

  SELECT
    CONCAT('O', oi.id) AS row_id,
    so.created_at AS created_at,
    'product' AS target_type,
    oi.product_id AS target_id,
    oi.name AS item_name,
    COALESCE(oi.sku,'') AS sku,
    CASE WHEN oi.diff >= 0 THEN 'in' ELSE 'out' END AS direction,
    ABS(oi.diff) AS qty,
    'pcs' AS unit,
    CONCAT('Opname #', so.id, CASE WHEN so.note IS NULL OR so.note='' THEN '' ELSE CONCAT(' • ', so.note) END) AS note,
    COALESCE(u2.name, '') AS user_name,
    'opname' AS source,
    so.id AS ref_id
  FROM stock_opnames so
  JOIN stock_opname_items oi ON oi.opname_id = so.id
  LEFT JOIN users u2 ON u2.id = so.admin_id
  WHERE so.store_id = :store_id
";

// ===== WHERE FILTER di luar UNION =====
$where = " WHERE 1=1 ";
$params = [':store_id' => $storeId];

if ($target === 'ingredient') {
  $where .= " AND source='mutasi' AND target_type='ingredient' ";
} elseif ($target === 'opname') {
  $where .= " AND source='opname' ";
} elseif ($target === 'product') {
  $where .= " AND target_type='product' ";
}

if ($dir !== 'all') {
  $where .= " AND direction = :dir ";
  $params[':dir'] = $dir;
}

if ($q !== '') {
  $where .= " AND (item_name LIKE :q OR sku LIKE :q) ";
  $params[':q'] = '%' . $q . '%';
}

if ($from !== '' && preg_match($dateRe, $from)) {
  $where .= " AND created_at >= :from_dt ";
  $params[':from_dt'] = $from . ' 00:00:00';
}
if ($to !== '' && preg_match($dateRe, $to)) {
  $where .= " AND created_at <= :to_dt ";
  $params[':to_dt'] = $to . ' 23:59:59';
}

// ===== COUNT =====
$countSql = "SELECT COUNT(*) FROM ( {$unionSql} ) t {$where}";
$stCount = $pdo->prepare($countSql);
foreach ($params as $k => $v) $stCount->bindValue($k, $v);
$stCount->execute();
$total = (int)$stCount->fetchColumn();

$pages = max(1, (int)ceil($total / $limit));
if ($page > $pages) $page = $pages;

$offset = ($page - 1) * $limit;

// ===== DATA =====
$dataSql = "SELECT * FROM ( {$unionSql} ) t {$where}
            ORDER BY created_at DESC, row_id DESC
            LIMIT :lim OFFSET :off";

$stData = $pdo->prepare($dataSql);
foreach ($params as $k => $v) $stData->bindValue($k, $v);
$stData->bindValue(':lim', (int)$limit, PDO::PARAM_INT);
$stData->bindValue(':off', (int)$offset, PDO::PARAM_INT);
$stData->execute();
$rows = $stData->fetchAll(PDO::FETCH_ASSOC);

// ===== Helper URL =====
$baseParams = [
  'target' => $target,
  'dir' => $dir,
  'q' => $q,
  'from' => $from,
  'to' => $to,
];
$mkUrl = function(array $extra = []) use ($baseParams): string {
  return 'admin_riwayat_stok.php?' . http_build_query(array_merge($baseParams, $extra));
};

require __DIR__ . '/../publik/partials/admin_layout_top.php';
?>

<style>
  .inv-wrap{max-width:1200px;}
  .muted{color:#64748b}
  .card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:14px;}
  .btn{padding:10px 14px;border-radius:12px;border:1px solid #e2e8f0;background:#0f172a;color:#fff;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:8px}
  .btn:active{transform:translateY(1px)}
  .btn-outline{background:#fff;color:#0f172a}
  .btn-outline:hover{background:#f8fafc}
  .btn-small{padding:8px 12px;border-radius:12px;font-size:13px}
  input,select{padding:10px 12px;border-radius:12px;border:1px solid #e2e8f0;width:100%}
  label{font-size:13px;color:#334155}
  table{width:100%;border-collapse:collapse}
  th,td{border-bottom:1px solid #f1f5f9;padding:10px 8px;text-align:left;font-size:13px;vertical-align:top}
  th{color:#64748b;font-weight:700}
  .pill{display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;border:1px solid #e2e8f0;white-space:nowrap}
  .pill.in{background:#ecfdf5;border-color:#bbf7d0;color:#166534}
  .pill.out{background:#fef2f2;border-color:#fecaca;color:#991b1b}

  /* ===== HEADER ===== */
  .page-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
    margin-bottom:10px;
  }
  .page-title{margin:0 0 6px;}
  .page-sub{margin:0;color:#64748b}
  .head-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
  .back-inline{display:none;}

  /* ===== FILTER LAYOUT ====== */
  .filter-form{
    display:grid;
    grid-template-columns:180px 160px 1fr 150px 150px auto;
    gap:10px;
    align-items:end;
  }
  .filter-actions{display:flex;gap:10px;justify-content:flex-end}
  .filter-actions .btn{width:100%}

  /* ====== RESPONSIVE MOBILE ====== */
  @media (max-width: 900px){
    .filter-form{grid-template-columns:1fr 1fr;}
  }
  @media (max-width: 640px){
    .card{padding:12px}
    .page-head{flex-direction:column;align-items:stretch}
    .head-actions{display:none;}        /* hide top-right back button on mobile */
    .back-inline{display:flex;}         /* show safer back button below title */
    .page-title{font-size:28px; line-height:1.1}
    .filter-form{grid-template-columns:1fr;}
    .filter-actions{justify-content:stretch}
  }

  /* ====== TABLE -> STACKED CARDS ON SMALL SCREENS ====== */
  .table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}
  @media (max-width: 640px){
    .table-wrap{overflow:visible}
    table.resp{border-collapse:separate;border-spacing:0 10px}
    table.resp thead{display:none}
    table.resp tbody tr{
      display:block;
      border:1px solid #e2e8f0;
      border-radius:14px;
      padding:10px 10px;
      background:#fff;
    }
    table.resp tbody td{
      display:flex;
      gap:10px;
      justify-content:space-between;
      align-items:flex-start;
      border-bottom:1px dashed #f1f5f9;
      padding:8px 4px;
      text-align:left;
    }
    table.resp tbody td:last-child{border-bottom:none}
    table.resp tbody td::before{
      content: attr(data-label);
      font-weight:700;
      color:#334155;
      min-width:92px;
      flex:0 0 92px;
    }
    .td-item{display:block;width:100%}
    .td-item .name{font-weight:800}
    .td-item .meta{margin-top:2px}
    .td-note{word-break:break-word;overflow-wrap:anywhere}
  }
</style>

<div class="inv-wrap">

  <!-- Header yang UX-nya aman -->
  <div class="page-head">
    <div>
      <h1 class="page-title">Riwayat Stok</h1>

      <!-- Back button versi mobile (lebih aman, tidak inline dengan filter/total) -->
      <div class="back-inline" style="margin-top:10px;">
        <a class="btn btn-outline btn-small" href="admin_persediaan.php?tab=bahan" aria-label="Kembali ke Persediaan">
          ← Kembali ke Persediaan
        </a>
      </div>
    </div>

    <!-- Back button versi desktop (kanan atas, tidak mudah kepencet saat scroll/filter) -->
    <div class="head-actions">
      <a class="btn btn-outline btn-small" href="admin_persediaan.php?tab=bahan" aria-label="Kembali ke Persediaan">
        ← Kembali
      </a>
    </div>
  </div>

  <div class="card" style="margin-bottom:12px;">
    <form method="get" class="filter-form">
      <div>
        <label>Target</label>
        <select name="target">
          <option value="all" <?= $target==='all'?'selected':'' ?>>Semua</option>
          <option value="product" <?= $target==='product'?'selected':'' ?>>Produk</option>
          <option value="ingredient" <?= $target==='ingredient'?'selected':'' ?>>Bahan</option>
          <option value="opname" <?= $target==='opname'?'selected':'' ?>>Opname</option>
        </select>
      </div>

      <div>
        <label>Arah</label>
        <select name="dir">
          <option value="all" <?= $dir==='all'?'selected':'' ?>>Semua</option>
          <option value="in" <?= $dir==='in'?'selected':'' ?>>Masuk</option>
          <option value="out" <?= $dir==='out'?'selected':'' ?>>Keluar</option>
        </select>
      </div>

      <div>
        <label>Cari</label>
        <input name="q" value="<?= htmlspecialchars($q) ?>" placeholder="nama / SKU...">
      </div>

      <div>
        <label>Dari</label>
        <input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
      </div>

      <div>
        <label>Sampai</label>
        <input type="date" name="to" value="<?= htmlspecialchars($to) ?>">
      </div>

      <div class="filter-actions">
        <button class="btn" type="submit">Filter</button>
      </div>
    </form>

    <!-- Info total dipisah, tidak ada tombol back di area rawan -->
    <div class="muted" style="margin-top:10px;">
      Total: <b><?= (int)$total ?></b> data • Halaman <b><?= (int)$page ?></b> / <b><?= (int)$pages ?></b>
    </div>
  </div>

  <div class="card">
    <div class="table-wrap">
      <table class="resp">
        <thead>
          <tr>
            <th style="width:160px;">Waktu</th>
            <th style="width:90px;">Sumber</th>
            <th>Item</th>
            <th style="width:90px;">Arah</th>
            <th style="width:120px;">Qty</th>
            <th style="width:260px;">Catatan</th>
            <th style="width:160px;">Oleh</th>
          </tr>
        </thead>
        <tbody>
          <?php if(!$rows): ?>
            <tr><td colspan="7">Belum ada riwayat stok.</td></tr>
          <?php else: ?>
            <?php foreach($rows as $r): ?>
              <?php
                $qty = (float)($r['qty'] ?? 0);
                $qtyText = rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.');
                $unit = (string)($r['unit'] ?? '');
                $itemName = (string)($r['item_name'] ?? '');
                $sku = (string)($r['sku'] ?? '');
                $direction = (string)($r['direction'] ?? '');
                $source = (string)($r['source'] ?? '');
                $targetType = (string)($r['target_type'] ?? '');
                $note = (string)($r['note'] ?? '');
                $userName = (string)($r['user_name'] ?? '');
              ?>
              <tr>
                <td data-label="Waktu"><?= htmlspecialchars((string)$r['created_at']) ?></td>

                <td data-label="Sumber">
                  <span class="pill"><?= $source === 'opname' ? 'Opname' : 'Mutasi' ?></span>
                </td>

                <td data-label="Item">
                  <div class="td-item">
                    <div class="name"><?= htmlspecialchars($itemName) ?></div>
                    <div class="muted meta" style="font-size:12px;">
                      <?= $targetType === 'ingredient' ? 'Bahan' : 'Produk' ?>
                      <?php if($sku !== ''): ?> • SKU: <?= htmlspecialchars($sku) ?><?php endif; ?>
                    </div>
                  </div>
                </td>

                <td data-label="Arah">
                  <span class="pill <?= $direction === 'in' ? 'in' : 'out' ?>">
                    <?= $direction === 'in' ? 'Masuk' : 'Keluar' ?>
                  </span>
                </td>

                <td data-label="Qty">
                  <?= htmlspecialchars($qtyText) ?> <?= htmlspecialchars($unit) ?>
                </td>

                <td data-label="Catatan" class="td-note">
                  <?= htmlspecialchars($note) ?>
                </td>

                <td data-label="Oleh">
                  <?= htmlspecialchars($userName) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if($pages > 1): ?>
      <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;justify-content:flex-end;margin-top:12px;">
        <?php
          $prev = max(1, $page - 1);
          $next = min($pages, $page + 1);
        ?>
        <a class="btn btn-outline" href="<?= htmlspecialchars($mkUrl(['page'=>1])) ?>">«</a>
        <a class="btn btn-outline" href="<?= htmlspecialchars($mkUrl(['page'=>$prev])) ?>">‹</a>
        <span class="muted">Page <?= (int)$page ?> / <?= (int)$pages ?></span>
        <a class="btn btn-outline" href="<?= htmlspecialchars($mkUrl(['page'=>$next])) ?>">›</a>
        <a class="btn btn-outline" href="<?= htmlspecialchars($mkUrl(['page'=>$pages])) ?>">»</a>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../publik/partials/admin_layout_bottom.php'; ?>

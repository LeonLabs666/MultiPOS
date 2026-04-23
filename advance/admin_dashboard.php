<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/inventory.php';

require_role(['admin']);

$appName = 'MultiPOS';
$pageTitle = 'Dashboard';
$activeMenu = 'dashboard';

$adminId = (int)auth_user()['id'];

function h($value): string {
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function rupiah(int|float $value): string {
  return 'Rp ' . number_format((float)$value, 0, ',', '.');
}

function chart_period_meta(string $period): array {
  return match ($period) {
    'daily' => [
      'title' => 'Grafik Omzet Harian',
      'subtitle' => 'Melihat tren omzet 7 hari terakhir.',
      'badge' => 'Harian',
    ],
    'weekly' => [
      'title' => 'Grafik Omzet Mingguan',
      'subtitle' => 'Melihat tren omzet 8 minggu terakhir.',
      'badge' => 'Mingguan',
    ],
    'yearly' => [
      'title' => 'Grafik Omzet Tahunan',
      'subtitle' => 'Melihat tren omzet 5 tahun terakhir.',
      'badge' => 'Tahunan',
    ],
    default => [
      'title' => 'Grafik Omzet Bulanan',
      'subtitle' => 'Melihat tren omzet 12 bulan terakhir.',
      'badge' => 'Bulanan',
    ],
  };
}

/**
 * Build chart labels/data berdasarkan period.
 * Bucket key disimpan dalam format tanggal awal bucket:
 * - daily   => Y-m-d
 * - weekly  => Senin awal minggu (Y-m-d)
 * - monthly => hari pertama bulan (Y-m-d)
 * - yearly  => 1 Januari tahun tsb (Y-m-d)
 */
function build_chart(PDO $pdo, int $storeId, string $period): array {
  $now = new DateTimeImmutable('now');
  $chartMap = [];
  $labels = [];
  $data = [];

  if ($period === 'daily') {
    $start = $now->setTime(0, 0, 0)->modify('-6 days');
    $end = $now->setTime(23, 59, 59);

    $sql = "
      SELECT DATE(created_at) AS bucket_key, COALESCE(SUM(total), 0) AS omzet
      FROM sales
      WHERE store_id = ?
        AND created_at BETWEEN ? AND ?
      GROUP BY DATE(created_at)
      ORDER BY bucket_key ASC
    ";

    $st = $pdo->prepare($sql);
    $st->execute([
      $storeId,
      $start->format('Y-m-d H:i:s'),
      $end->format('Y-m-d H:i:s'),
    ]);

    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $chartMap[(string)$row['bucket_key']] = (int)$row['omzet'];
    }

    for ($i = 0; $i < 7; $i++) {
      $bucket = $start->modify('+' . $i . ' days');
      $key = $bucket->format('Y-m-d');
      $labels[] = $bucket->format('d M');
      $data[] = $chartMap[$key] ?? 0;
    }

    return [$labels, $data];
  }

  if ($period === 'weekly') {
    $currentWeekStart = $now->modify('monday this week')->setTime(0, 0, 0);
    $start = $currentWeekStart->modify('-7 weeks');
    $end = $now->setTime(23, 59, 59);

    $sql = "
      SELECT
        DATE_SUB(DATE(created_at), INTERVAL WEEKDAY(created_at) DAY) AS bucket_key,
        COALESCE(SUM(total), 0) AS omzet
      FROM sales
      WHERE store_id = ?
        AND created_at BETWEEN ? AND ?
      GROUP BY bucket_key
      ORDER BY bucket_key ASC
    ";

    $st = $pdo->prepare($sql);
    $st->execute([
      $storeId,
      $start->format('Y-m-d H:i:s'),
      $end->format('Y-m-d H:i:s'),
    ]);

    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $chartMap[(string)$row['bucket_key']] = (int)$row['omzet'];
    }

    for ($i = 0; $i < 8; $i++) {
      $bucket = $start->modify('+' . $i . ' weeks');
      $key = $bucket->format('Y-m-d');
      $weekEnd = $bucket->modify('+6 days');
      $labels[] = $bucket->format('d M') . ' - ' . $weekEnd->format('d M');
      $data[] = $chartMap[$key] ?? 0;
    }

    return [$labels, $data];
  }

  if ($period === 'yearly') {
    $currentYearStart = $now->setDate((int)$now->format('Y'), 1, 1)->setTime(0, 0, 0);
    $start = $currentYearStart->modify('-4 years');
    $end = $now->setTime(23, 59, 59);

    $sql = "
      SELECT DATE_FORMAT(created_at, '%Y-01-01') AS bucket_key, COALESCE(SUM(total), 0) AS omzet
      FROM sales
      WHERE store_id = ?
        AND created_at BETWEEN ? AND ?
      GROUP BY YEAR(created_at)
      ORDER BY bucket_key ASC
    ";

    $st = $pdo->prepare($sql);
    $st->execute([
      $storeId,
      $start->format('Y-m-d H:i:s'),
      $end->format('Y-m-d H:i:s'),
    ]);

    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $chartMap[(string)$row['bucket_key']] = (int)$row['omzet'];
    }

    for ($i = 0; $i < 5; $i++) {
      $bucket = $start->modify('+' . $i . ' years');
      $key = $bucket->format('Y-01-01');
      $labels[] = $bucket->format('Y');
      $data[] = $chartMap[$key] ?? 0;
    }

    return [$labels, $data];
  }

  // default: monthly
  $currentMonthStart = $now->modify('first day of this month')->setTime(0, 0, 0);
  $start = $currentMonthStart->modify('-11 months');
  $end = $now->setTime(23, 59, 59);

  $sql = "
    SELECT DATE_FORMAT(created_at, '%Y-%m-01') AS bucket_key, COALESCE(SUM(total), 0) AS omzet
    FROM sales
    WHERE store_id = ?
      AND created_at BETWEEN ? AND ?
    GROUP BY YEAR(created_at), MONTH(created_at)
    ORDER BY bucket_key ASC
  ";

  $st = $pdo->prepare($sql);
  $st->execute([
    $storeId,
    $start->format('Y-m-d H:i:s'),
    $end->format('Y-m-d H:i:s'),
  ]);

  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $chartMap[(string)$row['bucket_key']] = (int)$row['omzet'];
  }

  for ($i = 0; $i < 12; $i++) {
    $bucket = $start->modify('+' . $i . ' months');
    $key = $bucket->format('Y-m-01');
    $labels[] = $bucket->format('M Y');
    $data[] = $chartMap[$key] ?? 0;
  }

  return [$labels, $data];
}

// ambil toko milik admin
$st = $pdo->prepare("SELECT id, name FROM stores WHERE owner_admin_id = ? AND is_active = 1 LIMIT 1");
$st->execute([$adminId]);
$store = $st->fetch(PDO::FETCH_ASSOC);

if (!$store) {
  http_response_code(400);
  exit('Admin belum punya toko.');
}

$storeId   = (int)$store['id'];
$storeName = (string)$store['name'];

$allowedPeriods = ['daily', 'weekly', 'monthly', 'yearly'];
$chartPeriod = (string)($_GET['period'] ?? 'monthly');
if (!in_array($chartPeriod, $allowedPeriods, true)) {
  $chartPeriod = 'monthly';
}

$chartMeta = chart_period_meta($chartPeriod);

// tanggal
$today      = date('Y-m-d');
$monthStart = date('Y-m-01');

// omzet hari ini + transaksi hari ini
$q1 = $pdo->prepare("
  SELECT
    COALESCE(SUM(total), 0) AS omzet_today,
    COUNT(*) AS trx_today
  FROM sales
  WHERE store_id = ?
    AND created_at BETWEEN ? AND ?
");
$q1->execute([
  $storeId,
  $today . ' 00:00:00',
  $today . ' 23:59:59'
]);
$todayRow = $q1->fetch(PDO::FETCH_ASSOC) ?: [];

$omzetToday = (int)($todayRow['omzet_today'] ?? 0);
$trxToday   = (int)($todayRow['trx_today'] ?? 0);

// omzet bulan ini
$q2 = $pdo->prepare("
  SELECT COALESCE(SUM(total), 0) AS omzet_month
  FROM sales
  WHERE store_id = ?
    AND created_at BETWEEN ? AND ?
");
$q2->execute([
  $storeId,
  $monthStart . ' 00:00:00',
  $today . ' 23:59:59'
]);
$omzetMonth = (int)($q2->fetchColumn() ?: 0);

// transaksi terbaru
$q3 = $pdo->prepare("
  SELECT
    sa.created_at,
    sa.invoice_no,
    sa.total,
    sa.payment_method,
    u.name AS kasir_name
  FROM sales sa
  JOIN users u ON u.id = sa.kasir_id
  WHERE sa.store_id = ?
  ORDER BY sa.id DESC
  LIMIT 8
");
$q3->execute([$storeId]);
$recent = $q3->fetchAll(PDO::FETCH_ASSOC);

[$chartLabels, $chartData] = build_chart($pdo, $storeId, $chartPeriod);

/**
 * =========================================================
 * RESTOCK ALERT
 * =========================================================
 */

$restockQ = $pdo->prepare("
  SELECT
    SUM(CASE WHEN stock > 0 AND stock <= safety_stock THEN 1 ELSE 0 END) AS kritis_count,
    SUM(CASE WHEN stock > safety_stock AND stock <= reorder_point THEN 1 ELSE 0 END) AS restock_count,
    SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END) AS habis_count
  FROM ingredients
  WHERE store_id = ?
    AND is_active = 1
");
$restockQ->execute([$storeId]);
$restockStats = $restockQ->fetch(PDO::FETCH_ASSOC) ?: [
  'kritis_count' => 0,
  'restock_count' => 0,
  'habis_count' => 0,
];

$habisCount   = (int)($restockStats['habis_count'] ?? 0);
$kritisCount  = (int)($restockStats['kritis_count'] ?? 0);
$restockCount = (int)($restockStats['restock_count'] ?? 0);

$criticalItemsQ = $pdo->prepare("
  SELECT
    id,
    name,
    unit,
    stock,
    safety_stock,
    reorder_point,
    suggested_restock_qty,
    avg_daily_usage
  FROM ingredients
  WHERE store_id = ?
    AND is_active = 1
    AND stock <= reorder_point
  ORDER BY
    CASE
      WHEN stock <= 0 THEN 0
      WHEN stock <= safety_stock THEN 1
      ELSE 2
    END ASC,
    stock ASC,
    name ASC
  LIMIT 8
");
$criticalItemsQ->execute([$storeId]);
$criticalItems = $criticalItemsQ->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/../publik/partials/admin_layout_top.php';
?>

<style>
  .dash-grid{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:12px;
    max-width:1100px;
  }
  .dash-card{
    background:#fff;
    border:1px solid #e2e8f0;
    border-radius:14px;
    padding:14px;
  }
  .dash-card-title{
    color:#64748b;
    font-size:12px;
    letter-spacing:.3px;
  }
  .dash-card-value{
    font-size:22px;
    font-weight:800;
    margin-top:6px;
  }
  .dash-card-sub{
    color:#94a3b8;
    font-size:12px;
    margin-top:4px;
  }
  .panel{
    margin-top:16px;
    background:#fff;
    border:1px solid #e2e8f0;
    border-radius:14px;
    padding:14px;
  }
  .panel-head{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:12px;
    margin-bottom:10px;
    flex-wrap:wrap;
  }
  .panel-title{
    font-weight:800;
  }
  .panel-subtitle{
    color:#64748b;
    font-size:12px;
    margin-top:3px;
  }
  .panel-actions{
    display:flex;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
    justify-content:flex-end;
  }
  .status-badge{
    background:#dcfce7;
    color:#166534;
    padding:4px 8px;
    border-radius:999px;
    font-size:12px;
    white-space:nowrap;
  }
  .period-tabs{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
  }
  .period-tab{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:8px 12px;
    border-radius:999px;
    border:1px solid #cbd5e1;
    color:#334155;
    text-decoration:none;
    font-size:13px;
    font-weight:700;
    background:#fff;
  }
  .period-tab:hover{
    border-color:#94a3b8;
    background:#f8fafc;
  }
  .period-tab.active{
    background:#0f172a;
    color:#fff;
    border-color:#0f172a;
  }
  .chart-wrap{
    position:relative;
    width:100%;
    min-height:320px;
  }
  .table-wrap{
    overflow-x:auto;
  }
  .trx-table{
    width:100%;
    border-collapse:collapse;
    min-width:720px;
  }
  .trx-table th{
    background:#f8fafc;
    border-bottom:1px solid #e2e8f0;
    padding:8px;
    font-size:13px;
  }
  .trx-table td{
    border-bottom:1px solid #eef2f7;
    padding:8px;
    font-size:14px;
  }

  .restock-grid{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:12px;
    max-width:1100px;
    margin-top:16px;
  }

  .restock-table{
    width:100%;
    border-collapse:collapse;
    min-width:820px;
  }
  .restock-table th{
    background:#f8fafc;
    border-bottom:1px solid #e2e8f0;
    padding:8px;
    font-size:13px;
  }
  .restock-table td{
    border-bottom:1px solid #eef2f7;
    padding:8px;
    font-size:13px;
    vertical-align:middle;
  }

  .pill-status{
    display:inline-block;
    padding:2px 8px;
    border-radius:999px;
    font-size:12px;
    border:1px solid #e2e8f0;
    white-space:nowrap;
  }
  .pill-aman{background:#ecfdf5;border-color:#bbf7d0;color:#166534;}
  .pill-restock{background:#fffbeb;border-color:#fde68a;color:#92400e;}
  .pill-kritis{background:#fef2f2;border-color:#fecaca;color:#b91c1c;}
  .pill-habis{background:#111827;border-color:#111827;color:#fff;}

  .link-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:8px 12px;
    border-radius:999px;
    border:1px solid #cbd5e1;
    color:#334155;
    text-decoration:none;
    font-size:13px;
    font-weight:700;
    background:#fff;
  }
  .link-btn:hover{
    border-color:#94a3b8;
    background:#f8fafc;
  }

  @media (max-width: 900px){
    .dash-grid{
      grid-template-columns:1fr;
    }
    .restock-grid{
      grid-template-columns:1fr;
    }
    .panel-actions{
      width:100%;
      justify-content:flex-start;
    }
  }
</style>

<h1 style="margin:0 0 6px;">Dashboard</h1>
<p style="margin:0 0 16px;color:#64748b;">
  Ringkasan penjualan toko <strong><?= h($storeName) ?></strong>.
</p>

<div class="dash-grid">
  <div class="dash-card">
    <div class="dash-card-title">OMZET HARI INI</div>
    <div class="dash-card-value"><?= rupiah($omzetToday) ?></div>
    <div class="dash-card-sub"><?= h($today) ?></div>
  </div>

  <div class="dash-card">
    <div class="dash-card-title">OMZET BULAN INI</div>
    <div class="dash-card-value"><?= rupiah($omzetMonth) ?></div>
    <div class="dash-card-sub"><?= h(date('F Y')) ?></div>
  </div>

  <div class="dash-card">
    <div class="dash-card-title">TRANSAKSI HARI INI</div>
    <div class="dash-card-value"><?= (int)$trxToday ?></div>
    <div class="dash-card-sub">Transaksi sukses</div>
  </div>
</div>

<div class="panel">
  <div class="panel-head">
    <div>
      <div class="panel-title"><?= h($chartMeta['title']) ?></div>
      <div class="panel-subtitle"><?= h($chartMeta['subtitle']) ?></div>
    </div>

    <div class="panel-actions">
      <div class="period-tabs">
        <a class="period-tab <?= $chartPeriod === 'daily' ? 'active' : '' ?>" href="?period=daily">Harian</a>
        <a class="period-tab <?= $chartPeriod === 'weekly' ? 'active' : '' ?>" href="?period=weekly">Mingguan</a>
        <a class="period-tab <?= $chartPeriod === 'monthly' ? 'active' : '' ?>" href="?period=monthly">Bulanan</a>
        <a class="period-tab <?= $chartPeriod === 'yearly' ? 'active' : '' ?>" href="?period=yearly">Tahunan</a>
      </div>
      <div class="status-badge"><?= h($chartMeta['badge']) ?></div>
    </div>
  </div>

  <div class="chart-wrap">
    <canvas id="salesChart"></canvas>
  </div>
</div>

<div class="restock-grid">
  <div class="dash-card">
    <div class="dash-card-title">BAHAN HABIS</div>
    <div class="dash-card-value"><?= (int)$habisCount ?></div>
    <div class="dash-card-sub">Stok <= 0</div>
  </div>

  <div class="dash-card">
    <div class="dash-card-title">BAHAN KRITIS</div>
    <div class="dash-card-value"><?= (int)$kritisCount ?></div>
    <div class="dash-card-sub">Stok <= safety stock</div>
  </div>

  <div class="dash-card">
    <div class="dash-card-title">PERLU RESTOCK</div>
    <div class="dash-card-value"><?= (int)$restockCount ?></div>
    <div class="dash-card-sub">Stok <= reorder point</div>
  </div>
</div>

<div class="panel">
  <div class="panel-head">
    <div>
      <div class="panel-title">Alert Restock Bahan</div>
      <div class="panel-subtitle">Menampilkan bahan yang stoknya sudah di bawah reorder point.</div>
    </div>

    <div class="panel-actions">
      <a class="link-btn" href="admin_persediaan_bahan.php">Lihat Semua Bahan</a>
    </div>
  </div>

  <?php if (!$criticalItems): ?>
    <div style="padding:6px 0;color:#16a34a;font-weight:700;">Semua bahan masih aman.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="restock-table" border="0" cellpadding="0" cellspacing="0">
        <tr>
          <th align="left">Nama</th>
          <th align="left">Stok</th>
          <th align="left">Safety</th>
          <th align="left">ROP</th>
          <th align="left">Avg/Hari</th>
          <th align="left">Saran Restock</th>
          <th align="left">Status</th>
        </tr>

        <?php foreach ($criticalItems as $it): ?>
          <?php
            $status = inv_get_ingredient_stock_status(
              (float)$it['stock'],
              (float)$it['safety_stock'],
              (float)$it['reorder_point']
            );

            $stockTxt = rtrim(rtrim(number_format((float)$it['stock'], 3, '.', ''), '0'), '.');
            $safetyTxt = rtrim(rtrim(number_format((float)$it['safety_stock'], 3, '.', ''), '0'), '.');
            $ropTxt = rtrim(rtrim(number_format((float)$it['reorder_point'], 3, '.', ''), '0'), '.');
            $avgTxt = rtrim(rtrim(number_format((float)$it['avg_daily_usage'], 3, '.', ''), '0'), '.');
            $suggestTxt = rtrim(rtrim(number_format((float)$it['suggested_restock_qty'], 3, '.', ''), '0'), '.');
          ?>
          <tr>
            <td><?= h((string)$it['name']) ?></td>
            <td><?= h($stockTxt) ?> <?= h((string)$it['unit']) ?></td>
            <td><?= h($safetyTxt) ?></td>
            <td><?= h($ropTxt) ?></td>
            <td><?= h($avgTxt) ?></td>
            <td><?= h($suggestTxt) ?></td>
            <td>
              <span class="pill-status pill-<?= h((string)$status['key']) ?>">
                <?= h((string)$status['label']) ?>
              </span>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="panel">
  <div class="panel-head">
    <div>
      <div class="panel-title">Transaksi Terakhir</div>
      <div class="panel-subtitle">8 transaksi terakhir yang tercatat.</div>
    </div>
    <div class="status-badge">Live Data</div>
  </div>

  <div class="table-wrap">
    <table class="trx-table" border="0" cellpadding="0" cellspacing="0">
      <tr>
        <th align="left">Waktu</th>
        <th align="left">Nota</th>
        <th align="right">Total</th>
        <th align="left">Metode</th>
        <th align="left">Kasir</th>
      </tr>

      <?php foreach ($recent as $r): ?>
        <tr>
          <td><?= h((string)$r['created_at']) ?></td>
          <td><?= h((string)($r['invoice_no'] ?? '-')) ?></td>
          <td align="right"><?= rupiah((int)$r['total']) ?></td>
          <td><?= h(strtoupper((string)($r['payment_method'] ?? '-'))) ?></td>
          <td><?= h((string)($r['kasir_name'] ?? '-')) ?></td>
        </tr>
      <?php endforeach; ?>

      <?php if (!$recent): ?>
        <tr>
          <td colspan="5" style="padding:14px;color:#64748b;">Belum ada transaksi.</td>
        </tr>
      <?php endif; ?>
    </table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  const salesLabels = <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>;
  const salesData   = <?= json_encode($chartData, JSON_NUMERIC_CHECK) ?>;
  const chartLabel  = <?= json_encode($chartMeta['title'], JSON_UNESCAPED_UNICODE) ?>;

  const ctx = document.getElementById('salesChart');

  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: salesLabels,
      datasets: [{
        label: chartLabel,
        data: salesData,
        borderWidth: 1,
        borderRadius: 6,
        maxBarThickness: 42
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: {
        mode: 'index',
        intersect: false
      },
      plugins: {
        legend: {
          display: false
        },
        tooltip: {
          callbacks: {
            label: function(context) {
              let value = context.raw || 0;
              return ' Rp ' + Number(value).toLocaleString('id-ID');
            }
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            callback: function(value) {
              return 'Rp ' + Number(value).toLocaleString('id-ID');
            }
          }
        }
      }
    }
  });
</script>

<?php require __DIR__ . '/../publik/partials/admin_layout_bottom.php'; ?>
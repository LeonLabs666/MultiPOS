<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';
require_role(['admin']);

$appName='MultiPOS';
$pageTitle='Export Data';
$activeMenu='export';
$adminId=(int)auth_user()['id'];

$error=''; $ok='';

// ambil toko admin
$st = $pdo->prepare("SELECT id,name FROM stores WHERE owner_admin_id=? AND is_active=1 LIMIT 1");
$st->execute([$adminId]);
$store = $st->fetch(PDO::FETCH_ASSOC);
if (!$store) { http_response_code(400); exit('Admin belum terhubung ke toko.'); }
$storeId = (int)$store['id'];
$storeName = (string)$store['name'];

/**
 * ===== Helpers: detect tables/columns =====
 */
$dbName = (string)$pdo->query("SELECT DATABASE()")->fetchColumn();

function table_exists(PDO $pdo, string $dbName, string $table): bool {
  $q = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=? LIMIT 1");
  $q->execute([$dbName, $table]);
  return (bool)$q->fetchColumn();
}

function columns_of(PDO $pdo, string $dbName, string $table): array {
  $q = $pdo->prepare("
    SELECT COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=? AND TABLE_NAME=?
    ORDER BY ORDINAL_POSITION ASC
  ");
  $q->execute([$dbName, $table]);
  return array_map(fn($r)=> (string)$r['COLUMN_NAME'], $q->fetchAll(PDO::FETCH_ASSOC));
}

function has_col(array $cols, string $col): bool {
  return in_array($col, $cols, true);
}

/**
 * ===== Date Range =====
 */
function range_to_dates(string $range, ?string $start, ?string $end): array {
  $tz = new DateTimeZone(date_default_timezone_get() ?: 'Asia/Jakarta');
  $now = new DateTime('now', $tz);

  $range = $range ?: 'today';

  if ($range === 'custom') {
    if (!$start || !$end) return [null, null, 'Custom range harus isi start & end.'];
    try {
      $s = new DateTime($start . ' 00:00:00', $tz);
      $e = new DateTime($end . ' 23:59:59', $tz);
      if ($e < $s) return [null, null, 'Tanggal akhir harus >= tanggal awal.'];
      return [$s, $e, null];
    } catch (Throwable $e) {
      return [null, null, 'Format tanggal custom tidak valid.'];
    }
  }

  $s = clone $now;
  $e = clone $now;

  $s->setTime(0,0,0);
  $e->setTime(23,59,59);

  switch ($range) {
    case 'today':
      break;

    case '7d':
      $s->modify('-6 days');
      break;

    case '30d':
      $s->modify('-29 days');
      break;

    case 'month':
      $s = new DateTime($now->format('Y-m-01 00:00:00'), $tz);
      $e = new DateTime($now->format('Y-m-t 23:59:59'), $tz);
      break;

    case 'year':
      $s = new DateTime($now->format('Y-01-01 00:00:00'), $tz);
      $e = new DateTime($now->format('Y-12-31 23:59:59'), $tz);
      break;

    default:
      return [null, null, 'Range tidak valid.'];
  }

  return [$s, $e, null];
}

/**
 * ===== Output Excel (.xls) via HTML table (Excel-compatible) =====
 */
function xls_download(string $filename, array $headers, array $rows): void {
  header("Content-Type: application/vnd.ms-excel; charset=utf-8");
  header("Content-Disposition: attachment; filename=\"{$filename}\"");
  header("Pragma: no-cache");
  header("Expires: 0");

  echo "<html><head><meta charset=\"utf-8\"></head><body>";
  echo "<table border=\"1\" cellspacing=\"0\" cellpadding=\"4\">";
  echo "<tr>";
  foreach ($headers as $h) {
    echo "<th style=\"background:#f1f5f9; font-weight:bold;\">" . htmlspecialchars((string)$h) . "</th>";
  }
  echo "</tr>";

  foreach ($rows as $r) {
    echo "<tr>";
    foreach ($headers as $key) {
      $val = $r[$key] ?? '';
      echo "<td>" . htmlspecialchars((string)$val) . "</td>";
    }
    echo "</tr>";
  }

  echo "</table>";
  echo "</body></html>";
  exit;
}

/**
 * ===== Query helper: export table safely =====
 */
function export_table(PDO $pdo, string $dbName, int $storeId, string $table, ?DateTime $start, ?DateTime $end, int $limit=5000): array {
  $cols = columns_of($pdo, $dbName, $table);
  if (!$cols) return [[], [], "Tabel $table tidak punya kolom."];

  $hasStore = has_col($cols, 'store_id');
  $dateCol = null;
  foreach (['created_at','updated_at','date','transaction_date','time'] as $c) {
    if (has_col($cols, $c)) { $dateCol = $c; break; }
  }

  $selectCols = implode(", ", array_map(fn($c)=>"`$c`", $cols));

  $sql = "SELECT $selectCols FROM `$table`";
  $params = [];

  $where = [];
  if ($hasStore) { $where[] = "store_id = ?"; $params[] = $storeId; }
  if ($dateCol && $start && $end) {
    $where[] = "`$dateCol` BETWEEN ? AND ?";
    $params[] = $start->format('Y-m-d H:i:s');
    $params[] = $end->format('Y-m-d H:i:s');
  }

  if ($where) $sql .= " WHERE " . implode(" AND ", $where);

  if ($dateCol) $sql .= " ORDER BY `$dateCol` DESC";
  else if (has_col($cols, 'id')) $sql .= " ORDER BY id DESC";

  $sql .= " LIMIT " . (int)$limit;

  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  return [$cols, $rows, null];
}

/**
 * ===== Chart helper =====
 */
function chart_period_label(string $period): string {
  return match ($period) {
    'daily' => 'Harian',
    'weekly' => 'Mingguan',
    'yearly' => 'Tahunan',
    default => 'Bulanan',
  };
}

function chart_title(string $period): string {
  return match ($period) {
    'daily' => 'Grafik Omzet Harian',
    'weekly' => 'Grafik Omzet Mingguan',
    'yearly' => 'Grafik Omzet Tahunan',
    default => 'Grafik Omzet Bulanan',
  };
}

function build_export_chart(PDO $pdo, int $storeId, string $period): array {
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
      $chartMap[(string)$row['bucket_key']] = (float)$row['omzet'];
    }

    for ($i = 0; $i < 7; $i++) {
      $bucket = $start->modify("+{$i} days");
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
      $chartMap[(string)$row['bucket_key']] = (float)$row['omzet'];
    }

    for ($i = 0; $i < 8; $i++) {
      $bucket = $start->modify("+{$i} weeks");
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
      $chartMap[(string)$row['bucket_key']] = (float)$row['omzet'];
    }

    for ($i = 0; $i < 5; $i++) {
      $bucket = $start->modify("+{$i} years");
      $key = $bucket->format('Y-01-01');
      $labels[] = $bucket->format('Y');
      $data[] = $chartMap[$key] ?? 0;
    }

    return [$labels, $data];
  }

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
    $chartMap[(string)$row['bucket_key']] = (float)$row['omzet'];
  }

  for ($i = 0; $i < 12; $i++) {
    $bucket = $start->modify("+{$i} months");
    $key = $bucket->format('Y-m-01');
    $labels[] = $bucket->format('M Y');
    $data[] = $chartMap[$key] ?? 0;
  }

  return [$labels, $data];
}

/**
 * ===== Map export types => candidate tables =====
 */
$EXPORTS = [
  'transactions' => [
    'label' => 'Transaksi (Laporan Penjualan)',
    'tables' => ['sales','orders','transactions','order_items','sales_orders'],
  ],
  'products' => [
    'label' => 'Produk / Menu & Kategori',
    'tables' => ['products','categories','product_categories','menu_items'],
  ],
  'inventory' => [
    'label' => 'Persediaan Bahan',
    'tables' => ['ingredients','stock_movements'],
  ],
  'finance' => [
    'label' => 'Keuangan (Pemasukan & Pengeluaran)',
    'tables' => ['cashflows','cash_flow','expenses','incomes','finance_transactions'],
  ],
  'customers' => [
    'label' => 'Data Pelanggan',
    'tables' => ['customers','customer','members'],
  ],
  'shifts' => [
    'label' => 'Shift Kasir',
    'tables' => ['cashier_shifts','shifts','shift_logs'],
  ],
  'users' => [
    'label' => 'User / Akun Login',
    'tables' => ['users','user_accounts'],
  ],
  'activity' => [
    'label' => 'Log Aktivitas',
    'tables' => ['activity_logs','audit_logs','logs','stock_movements'],
  ],
];

/**
 * ===== Handle download =====
 */
$action = (string)($_GET['action'] ?? '');
if ($action === 'download') {
  $type = (string)($_GET['type'] ?? '');
  $range = (string)($_GET['range'] ?? 'today');
  $start = (string)($_GET['start'] ?? '');
  $end = (string)($_GET['end'] ?? '');

  if ($type === 'chart_sales') {
    $period = (string)($_GET['period'] ?? 'monthly');
    $allowedPeriods = ['daily', 'weekly', 'monthly', 'yearly'];
    if (!in_array($period, $allowedPeriods, true)) {
      $period = 'monthly';
    }

    [$labels, $data] = build_export_chart($pdo, $storeId, $period);

    $rows = [];
    foreach ($labels as $i => $label) {
      $rows[] = [
        'Periode' => $label,
        'Omzet' => $data[$i] ?? 0,
        'Jenis Grafik' => chart_period_label($period),
        'Nama Toko' => $storeName,
      ];
    }

    xls_download(
      'export_grafik_omzet_' . $period . '_' . date('Ymd_His') . '.xls',
      ['Periode', 'Omzet', 'Jenis Grafik', 'Nama Toko'],
      $rows
    );
  }

  if (!isset($EXPORTS[$type])) {
    http_response_code(400);
    exit('Type export tidak valid.');
  }

  [$ds, $de, $rangeErr] = range_to_dates($range, $start ?: null, $end ?: null);
  if ($rangeErr) {
    http_response_code(400);
    exit($rangeErr);
  }

  $tables = $EXPORTS[$type]['tables'];
  $picked = null;
  foreach ($tables as $t) {
    if (table_exists($pdo, $dbName, $t)) { $picked = $t; break; }
  }
  if (!$picked) {
    http_response_code(400);
    exit('Tabel untuk export ini tidak ditemukan di database.');
  }

  [$headers, $rows, $err] = export_table($pdo, $dbName, $storeId, $picked, $ds, $de, 10000);
  if ($err) {
    http_response_code(400);
    exit($err);
  }

  $rangeLabel = $range;
  $fname = "export_{$type}_{$picked}_" . date('Ymd_His') . ".xls";

  foreach ($rows as &$r) {
    $r['_export_type'] = $type;
    $r['_table'] = $picked;
    $r['_range'] = $rangeLabel;
  }
  unset($r);

  $headers2 = array_merge($headers, ['_export_type','_table','_range']);

  xls_download($fname, $headers2, $rows);
}

/**
 * ===== Chart preview data =====
 */
$chartPreviewPeriod = 'monthly';
[$chartPreviewLabels, $chartPreviewData] = build_export_chart($pdo, $storeId, $chartPreviewPeriod);

$chartAllData = [];
foreach (['daily', 'weekly', 'monthly', 'yearly'] as $p) {
  [$labels, $data] = build_export_chart($pdo, $storeId, $p);
  $chartAllData[$p] = [
    'title' => chart_title($p),
    'labels' => $labels,
    'data' => $data,
  ];
}

/**
 * ===== UI PAGE =====
 */
require __DIR__ . '/../publik/partials/admin_layout_top.php';
?>

<style>
  .wrap{max-width:1100px;}
  .muted{color:#64748b}
  .panel{
    background:#fff;border:1px solid #e2e8f0;border-radius:18px;
    padding:16px;
  }
  .row{
    display:flex;align-items:center;justify-content:space-between;
    gap:12px;padding:12px 0;
    border-top:1px dashed #e2e8f0;
  }
  .row:first-child{border-top:none}
  .left{min-width:280px}
  .title{font-weight:700;margin:0 0 6px;font-size:13px}
  .select{
    padding:10px 12px;border-radius:12px;border:1px solid #e2e8f0;min-width:140px;
    font-size:13px;background:#fff;
  }
  .btn-export{
    padding:9px 16px;border-radius:999px;border:1px solid #2563eb;
    background:#fff;color:#2563eb;cursor:pointer;font-weight:700;
  }
  .btn-export:active{transform:translateY(1px)}
  .hint{font-size:12px;color:#64748b;margin-bottom:14px}
  .customWrap{display:none;gap:8px;align-items:center;flex-wrap:wrap;margin-top:8px}
  .customWrap input{
    padding:10px 12px;border-radius:12px;border:1px solid #e2e8f0;font-size:13px;
  }
  .chartCard{
    margin-top:18px;
    border-top:1px dashed #e2e8f0;
    padding-top:18px;
  }
  .chartBox{
    background:#fff;
    border:1px solid #e2e8f0;
    border-radius:16px;
    padding:14px;
  }
  .chartTop{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:12px;
    margin-bottom:12px;
    flex-wrap:wrap;
  }
  .chartCanvasWrap{
    height:340px;
  }
  .actionCol{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    align-items:center;
  }
  @media (max-width: 768px){
    .row{
      flex-direction:column;
      align-items:flex-start;
    }
    .left{min-width:0;width:100%}
    .chartCanvasWrap{height:280px}
  }
</style>

<div class="wrap">
  <div class="panel">
    <div style="font-weight:800;margin-bottom:6px;">Export per Menu</div>
    <div class="hint">
      Pilih periode <b>Harian / 7 hari / 30 hari / Bulan ini / Tahun ini</b> atau <b>Custom Range</b>, lalu klik tombol Export di kanan.
      <div class="muted" style="margin-top:6px;">Toko: <b><?= htmlspecialchars($storeName) ?></b></div>
    </div>

    <?php
      $ranges = [
        'today' => 'Hari Ini',
        '7d'    => '7 Hari',
        '30d'   => '30 Hari',
        'month' => 'Bulan Ini',
        'year'  => 'Tahun Ini',
        'custom'=> 'Custom Range',
      ];
    ?>

    <?php foreach ($EXPORTS as $key => $cfg): ?>
      <div class="row">
        <div class="left">
          <div class="title"><?= htmlspecialchars($cfg['label']) ?></div>

          <form method="get" style="margin:0;" class="exportForm">
            <input type="hidden" name="action" value="download">
            <input type="hidden" name="type" value="<?= htmlspecialchars($key) ?>">

            <select name="range" class="select rangeSel">
              <?php foreach($ranges as $rv => $rl): ?>
                <option value="<?= htmlspecialchars($rv) ?>"><?= htmlspecialchars($rl) ?></option>
              <?php endforeach; ?>
            </select>

            <div class="customWrap">
              <input type="date" name="start">
              <span class="muted">s/d</span>
              <input type="date" name="end">
            </div>
          </form>
        </div>

        <div>
          <button class="btn-export exportBtn" type="button">Export</button>
        </div>
      </div>
    <?php endforeach; ?>

    <div class="chartCard">
      <div style="font-weight:800;margin-bottom:6px;">Export Grafik Omzet Dashboard</div>
      <div class="hint" style="margin-bottom:12px;">
        Grafik di bawah bisa di-download sebagai <b>Excel</b> (data grafik) atau <b>PNG</b> (gambar grafik).
      </div>

      <div class="chartBox">
        <div class="chartTop">
          <div>
            <div class="title" style="font-size:14px;margin-bottom:8px;">Preview Grafik Omzet</div>
            <form method="get" id="chartExportForm" style="margin:0;">
              <input type="hidden" name="action" value="download">
              <input type="hidden" name="type" value="chart_sales">

              <select name="period" class="select" id="chartPeriodSelect">
                <option value="daily">Harian</option>
                <option value="weekly">Mingguan</option>
                <option value="monthly" selected>Bulanan</option>
                <option value="yearly">Tahunan</option>
              </select>
            </form>
          </div>

          <div class="actionCol">
            <button class="btn-export" type="button" id="downloadChartExcelBtn">Download Excel</button>
            <button class="btn-export" type="button" id="downloadChartPngBtn">Download Gambar</button>
          </div>
        </div>

        <div class="chartCanvasWrap">
          <canvas id="exportSalesChart"></canvas>
        </div>

        <div class="hint" style="margin:12px 0 0;">
          <b>Excel</b> berisi data grafik per periode. <b>Gambar</b> diambil dari preview grafik yang tampil di halaman ini.
        </div>
      </div>
    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function(){
  function syncCustom(form){
    const sel = form.querySelector('.rangeSel');
    const custom = form.querySelector('.customWrap');
    if (!sel || !custom) return;
    custom.style.display = (sel.value === 'custom') ? 'flex' : 'none';
  }

  document.querySelectorAll('.exportForm').forEach(form => {
    const sel = form.querySelector('.rangeSel');
    const btn = form.closest('.row').querySelector('.exportBtn');

    syncCustom(form);

    sel.addEventListener('change', () => syncCustom(form));

    btn.addEventListener('click', () => {
      const range = sel.value;
      if (range === 'custom') {
        const s = form.querySelector('input[name="start"]').value;
        const e = form.querySelector('input[name="end"]').value;
        if (!s || !e) {
          alert('Isi tanggal start & end untuk Custom Range.');
          return;
        }
      }
      form.submit();
    });
  });

  const chartDataMap = <?= json_encode($chartAllData, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK) ?>;
  const periodSelect = document.getElementById('chartPeriodSelect');
  const excelBtn = document.getElementById('downloadChartExcelBtn');
  const pngBtn = document.getElementById('downloadChartPngBtn');
  const chartForm = document.getElementById('chartExportForm');
  const canvas = document.getElementById('exportSalesChart');

  if (!periodSelect || !excelBtn || !pngBtn || !chartForm || !canvas || !chartDataMap) return;

  const initial = chartDataMap[periodSelect.value] || chartDataMap.monthly;

  const chart = new Chart(canvas, {
    type: 'bar',
    data: {
      labels: initial.labels || [],
      datasets: [{
        label: initial.title || 'Grafik Omzet',
        data: initial.data || [],
        borderWidth: 1,
        borderRadius: 6,
        maxBarThickness: 42
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: function(context) {
              const value = context.raw || 0;
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

  periodSelect.addEventListener('change', function(){
    const next = chartDataMap[this.value] || chartDataMap.monthly;
    chart.data.labels = next.labels || [];
    chart.data.datasets[0].data = next.data || [];
    chart.data.datasets[0].label = next.title || 'Grafik Omzet';
    chart.update();
  });

  excelBtn.addEventListener('click', function(){
    chartForm.submit();
  });

  pngBtn.addEventListener('click', function(){
    const link = document.createElement('a');
    link.href = chart.toBase64Image('image/png', 1);
    link.download = 'grafik_omzet_' + periodSelect.value + '.png';
    link.click();
  });
})();
</script>

<?php require __DIR__ . '/../publik/partials/admin_layout_bottom.php'; ?>
<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

$page_title  = 'Dashboard Admin (Basic) • MultiPOS';
$page_h1     = 'Dashboard';
$active_menu = 'dashboard';

$storeId   = (int)$storeId; // from _bootstrap
$storeName = (string)($store['name'] ?? 'Toko');

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function rupiah($n): string {
  return 'Rp ' . number_format((float)$n, 0, ',', '.');
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
 * Build chart labels & data berdasarkan period.
 * Bucket key:
 * - daily   => Y-m-d
 * - weekly  => tanggal Senin awal minggu (Y-m-d)
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
    $end   = $now->setTime(23, 59, 59);

    $sql = "
      SELECT DATE(created_at) AS bucket_key, COALESCE(SUM(total),0) AS omzet
      FROM sales
      WHERE store_id=?
        AND created_at BETWEEN ? AND ?
      GROUP BY DATE(created_at)
      ORDER BY bucket_key ASC
    ";
    $st = $pdo->prepare($sql);
    $st->execute([
      $storeId,
      $start->format('Y-m-d H:i:s'),
      $end->format('Y-m-d H:i:s')
    ]);

    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $chartMap[(string)$row['bucket_key']] = (int)($row['omzet'] ?? 0);
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
    $end   = $now->setTime(23, 59, 59);

    $sql = "
      SELECT
        DATE_SUB(DATE(created_at), INTERVAL WEEKDAY(created_at) DAY) AS bucket_key,
        COALESCE(SUM(total),0) AS omzet
      FROM sales
      WHERE store_id=?
        AND created_at BETWEEN ? AND ?
      GROUP BY bucket_key
      ORDER BY bucket_key ASC
    ";
    $st = $pdo->prepare($sql);
    $st->execute([
      $storeId,
      $start->format('Y-m-d H:i:s'),
      $end->format('Y-m-d H:i:s')
    ]);

    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $chartMap[(string)$row['bucket_key']] = (int)($row['omzet'] ?? 0);
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
    $end   = $now->setTime(23, 59, 59);

    $sql = "
      SELECT DATE_FORMAT(created_at, '%Y-01-01') AS bucket_key, COALESCE(SUM(total),0) AS omzet
      FROM sales
      WHERE store_id=?
        AND created_at BETWEEN ? AND ?
      GROUP BY YEAR(created_at)
      ORDER BY bucket_key ASC
    ";
    $st = $pdo->prepare($sql);
    $st->execute([
      $storeId,
      $start->format('Y-m-d H:i:s'),
      $end->format('Y-m-d H:i:s')
    ]);

    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $chartMap[(string)$row['bucket_key']] = (int)($row['omzet'] ?? 0);
    }

    for ($i = 0; $i < 5; $i++) {
      $bucket = $start->modify('+' . $i . ' years');
      $key = $bucket->format('Y-01-01');
      $labels[] = $bucket->format('Y');
      $data[] = $chartMap[$key] ?? 0;
    }

    return [$labels, $data];
  }

  // default monthly
  $currentMonthStart = $now->modify('first day of this month')->setTime(0, 0, 0);
  $start = $currentMonthStart->modify('-11 months');
  $end   = $now->setTime(23, 59, 59);

  $sql = "
    SELECT DATE_FORMAT(created_at, '%Y-%m-01') AS bucket_key, COALESCE(SUM(total),0) AS omzet
    FROM sales
    WHERE store_id=?
      AND created_at BETWEEN ? AND ?
    GROUP BY YEAR(created_at), MONTH(created_at)
    ORDER BY bucket_key ASC
  ";
  $st = $pdo->prepare($sql);
  $st->execute([
    $storeId,
    $start->format('Y-m-d H:i:s'),
    $end->format('Y-m-d H:i:s')
  ]);

  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $chartMap[(string)$row['bucket_key']] = (int)($row['omzet'] ?? 0);
  }

  for ($i = 0; $i < 12; $i++) {
    $bucket = $start->modify('+' . $i . ' months');
    $key = $bucket->format('Y-m-01');
    $labels[] = $bucket->format('M Y');
    $data[] = $chartMap[$key] ?? 0;
  }

  return [$labels, $data];
}

// tanggal
$today = date('Y-m-d');
$monthStart = date('Y-m-01');

// periode chart
$allowedPeriods = ['daily', 'weekly', 'monthly', 'yearly'];
$chartPeriod = (string)($_GET['period'] ?? 'monthly');
if (!in_array($chartPeriod, $allowedPeriods, true)) {
  $chartPeriod = 'monthly';
}
$chartMeta = chart_period_meta($chartPeriod);

// omzet hari ini + transaksi hari ini
$q1 = $pdo->prepare("
  SELECT
    COALESCE(SUM(total),0) AS omzet_today,
    COUNT(*) AS trx_today
  FROM sales
  WHERE store_id=?
    AND created_at BETWEEN ? AND ?
");
$q1->execute([$storeId, $today.' 00:00:00', $today.' 23:59:59']);
$todayRow = $q1->fetch(PDO::FETCH_ASSOC) ?: [];
$omzetToday = (int)($todayRow['omzet_today'] ?? 0);
$trxToday   = (int)($todayRow['trx_today'] ?? 0);

// omzet bulan ini
$q2 = $pdo->prepare("
  SELECT COALESCE(SUM(total),0) AS omzet_month
  FROM sales
  WHERE store_id=?
    AND created_at BETWEEN ? AND ?
");
$q2->execute([$storeId, $monthStart.' 00:00:00', $today.' 23:59:59']);
$omzetMonth = (int)($q2->fetchColumn() ?? 0);

// transaksi terbaru
$q3 = $pdo->prepare("
  SELECT
    sa.id,
    sa.created_at,
    sa.invoice_no,
    sa.total,
    sa.payment_method,
    u.name AS kasir_name
  FROM sales sa
  JOIN users u ON u.id = sa.kasir_id
  WHERE sa.store_id=?
  ORDER BY sa.id DESC
  LIMIT 8
");
$q3->execute([$storeId]);
$recent = $q3->fetchAll(PDO::FETCH_ASSOC);

// stok menipis
$lowThreshold = 5;
$qLow = $pdo->prepare("
  SELECT id, sku, name, stock
  FROM products
  WHERE store_id=? AND is_active=1 AND stock <= ?
  ORDER BY stock ASC, id DESC
  LIMIT 6
");
$qLow->execute([$storeId, $lowThreshold]);
$lowRows = $qLow->fetchAll(PDO::FETCH_ASSOC);

// build chart dinamis
[$chartLabels, $chartData] = build_chart($pdo, $storeId, $chartPeriod);

require __DIR__ . '/partials/layout_top.php';
?>

<style>
  .wrap{max-width:980px;}
  .subtitle{margin:0 0 16px;color:#64748b;}

  .grid3{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:12px;
  }
  @media (max-width: 920px){
    .grid3{grid-template-columns:1fr;}
  }

  .box{
    background:#fff;
    border:1px solid #e2e8f0;
    border-radius:14px;
    padding:14px;
  }

  .k{color:#64748b;font-size:12px;font-weight:900;letter-spacing:.02em;}
  .v{font-size:22px;font-weight:900;margin-top:6px;}
  .s{color:#94a3b8;font-size:12px;margin-top:4px;}

  .tag{
    display:inline-block;
    padding:4px 10px;
    border-radius:999px;
    font-size:12px;
    font-weight:900;
    border:1px solid #e2e8f0;
    background:#f8fafc;
    color:#0f172a;
  }
  .tag.ok{background:#dcfce7;color:#166534;border-color:#bbf7d0;}
  .tag.warn{background:#fef2f2;color:#991b1b;border-color:#fecaca;}

  table{width:100%;border-collapse:collapse;}
  th,td{padding:10px 8px;border-bottom:1px solid #eef2f7;text-align:left;font-size:13px;}
  th{background:#f8fafc;border-bottom:1px solid #e2e8f0;color:#64748b;font-size:12px;font-weight:900;}

  .actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-top:12px;
  }
  .btnlink{
    text-decoration:none;
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:10px 12px;
    border-radius:12px;
    border:1px solid #e2e8f0;
    background:#fff;
    font-weight:900;
    color:#0f172a;
  }
  .btnlink:hover{border-color:#93c5fd;}

  .chart-wrap{
    position:relative;
    width:100%;
    min-height:320px;
    margin-top:10px;
  }

  .period-tabs{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
  }
  .period-tab{
    text-decoration:none;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:8px 12px;
    border-radius:999px;
    border:1px solid #cbd5e1;
    background:#fff;
    color:#0f172a;
    font-size:12px;
    font-weight:900;
  }
  .period-tab:hover{
    border-color:#93c5fd;
    background:#f8fafc;
  }
  .period-tab.active{
    background:#0f172a;
    color:#fff;
    border-color:#0f172a;
  }
</style>

<div class="wrap">
  <h1 style="margin:0 0 6px;">Dashboard</h1>
  <p class="subtitle">
    Ringkasan penjualan toko <b><?= h($storeName) ?></b>.
  </p>

  <!-- RINGKASAN -->
  <div class="grid3">
    <div class="box">
      <div class="k">OMZET HARI INI</div>
      <div class="v"><?= rupiah($omzetToday) ?></div>
      <div class="s"><?= h($today) ?></div>
    </div>

    <div class="box">
      <div class="k">OMZET BULAN INI</div>
      <div class="v"><?= rupiah($omzetMonth) ?></div>
      <div class="s"><?= h(date('F Y')) ?></div>
    </div>

    <div class="box">
      <div class="k">TRANSAKSI HARI INI</div>
      <div class="v"><?= $trxToday ?></div>
      <div class="s">Transaksi sukses</div>
    </div>
  </div>

  <!-- QUICK ACTIONS -->
  <div class="actions">
    <a class="btnlink" href="admin_products.php">🧾 Produk</a>
    <a class="btnlink" href="admin_persediaan.php">📦 Persediaan</a>
    <a class="btnlink" href="admin_stok_inout.php">➕ Stok Masuk/Keluar</a>
    <a class="btnlink" href="admin_stock_opname.php">✅ Stok Opname</a>
    <a class="btnlink" href="admin_sales_report.php">📈 Laporan</a>
  </div>

  <!-- GRAFIK OMZET -->
  <div class="box" style="margin-top:16px;">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:10px;">
      <div>
        <div style="font-weight:900;"><?= h($chartMeta['title']) ?></div>
        <div style="color:#64748b;font-size:12px;"><?= h($chartMeta['subtitle']) ?></div>
      </div>

      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <div class="period-tabs">
          <a class="period-tab <?= $chartPeriod === 'daily' ? 'active' : '' ?>" href="?period=daily">Harian</a>
          <a class="period-tab <?= $chartPeriod === 'weekly' ? 'active' : '' ?>" href="?period=weekly">Mingguan</a>
          <a class="period-tab <?= $chartPeriod === 'monthly' ? 'active' : '' ?>" href="?period=monthly">Bulanan</a>
          <a class="period-tab <?= $chartPeriod === 'yearly' ? 'active' : '' ?>" href="?period=yearly">Tahunan</a>
        </div>

        <div style="color:#64748b;font-size:12px;">
          Status: <span class="tag ok"><?= h($chartMeta['badge']) ?></span>
        </div>
      </div>
    </div>

    <div class="chart-wrap">
      <canvas id="salesChart"></canvas>
    </div>
  </div>

  <!-- TRANSAKSI TERAKHIR -->
  <div class="box" style="margin-top:16px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:10px;">
      <div>
        <div style="font-weight:900;">Transaksi Terakhir</div>
        <div style="color:#64748b;font-size:12px;">8 transaksi terakhir yang tercatat.</div>
      </div>

      <div style="color:#64748b;font-size:12px;">
        Status: <span class="tag ok">Aktif</span>
      </div>
    </div>

    <div style="overflow:auto;">
      <table>
        <tr>
          <th>Waktu</th>
          <th>Nota</th>
          <th style="text-align:right;">Total</th>
          <th>Metode</th>
          <th>Kasir</th>
          <th>Aksi</th>
        </tr>

        <?php foreach($recent as $r): ?>
          <tr>
            <td><?= h($r['created_at'] ?? '') ?></td>
            <td><?= h($r['invoice_no'] ?? '-') ?></td>
            <td style="text-align:right;"><?= rupiah((int)($r['total'] ?? 0)) ?></td>
            <td><?= h(strtoupper((string)($r['payment_method'] ?? '-'))) ?></td>
            <td><?= h($r['kasir_name'] ?? '-') ?></td>
            <td>
              <a href="admin_sale_detail.php?id=<?= (int)($r['id'] ?? 0) ?>" style="text-decoration:none;font-weight:900;">Detail</a>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if(!$recent): ?>
          <tr><td colspan="6" style="padding:14px;color:#64748b;">Belum ada transaksi.</td></tr>
        <?php endif; ?>
      </table>
    </div>
  </div>

  <!-- STOK MENIPIS -->
  <div class="box" style="margin-top:12px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
      <div>
        <div style="font-weight:900;">Stok Menipis</div>
        <div style="color:#64748b;font-size:12px;">Produk dengan stok ≤ <?= $lowThreshold ?> (maks 6 item).</div>
      </div>
      <div>
        <?php if(!$lowRows): ?>
          <span class="tag ok">Aman</span>
        <?php else: ?>
          <span class="tag warn">Perlu restok</span>
        <?php endif; ?>
      </div>
    </div>

    <?php if(!$lowRows): ?>
      <div style="margin-top:10px;color:#64748b;">Tidak ada produk stok menipis 👍</div>
    <?php else: ?>
      <div style="margin-top:10px;overflow:auto;">
        <table>
          <tr>
            <th style="width:120px;">SKU</th>
            <th>Nama</th>
            <th style="text-align:right;width:90px;">Stok</th>
          </tr>
          <?php foreach($lowRows as $p): ?>
            <tr>
              <td><?= h($p['sku'] ?? '-') ?></td>
              <td><?= h($p['name'] ?? '') ?></td>
              <td style="text-align:right;"><b><?= (int)($p['stock'] ?? 0) ?></b></td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
      <div style="margin-top:10px;">
        <a href="admin_products.php" style="text-decoration:none;font-weight:900;">Kelola Produk →</a>
      </div>
    <?php endif; ?>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  const salesLabels = <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>;
  const salesData   = <?= json_encode($chartData, JSON_NUMERIC_CHECK) ?>;
  const chartLabel  = <?= json_encode($chartMeta['title'], JSON_UNESCAPED_UNICODE) ?>;

  const ctx = document.getElementById('salesChart');

  new Chart(ctx, {
    type: 'line',
    data: {
      labels: salesLabels,
      datasets: [{
        label: chartLabel,
        data: salesData,
        borderWidth: 2,
        tension: 0.35,
        fill: true
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

<?php require __DIR__ . '/partials/layout_bottom.php'; ?>
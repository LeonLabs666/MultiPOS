<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';

require_role(['admin']);

$appName   = 'MultiPOS';
$pageTitle = 'Laporan Shift';
$activeMenu= 'shift';

$adminId = (int)auth_user()['id'];

/* =========================
   Ambil store milik admin
========================= */
$st = $pdo->prepare("
  SELECT id, name
  FROM stores
  WHERE owner_admin_id=? AND is_active=1
  LIMIT 1
");
$st->execute([$adminId]);
$store = $st->fetch();

if (!$store) {
  http_response_code(400);
  exit('Admin belum punya toko.');
}

$storeId   = (int)$store['id'];
$storeName = (string)$store['name'];

/* =========================
   Filter tanggal
========================= */
$from = trim((string)($_GET['from'] ?? ''));
$to   = trim((string)($_GET['to']   ?? ''));

if ($from === '') $from = date('Y-m-01');
if ($to   === '') $to   = date('Y-m-d');

$fromDT = $from . ' 00:00:00';
$toDT   = $to   . ' 23:59:59';

/* =========================
   Query shift + agregat sales
========================= */
$q = $pdo->prepare("
  SELECT
    sh.id,
    sh.kasir_id,
    u.name AS kasir_name,

    sh.opened_at,
    sh.closed_at,
    sh.opening_cash,
    sh.closing_cash,
    sh.status,

    COUNT(sa.id) AS trx_count,
    COALESCE(SUM(sa.total),0) AS omzet,

    COALESCE(SUM(
      CASE WHEN sa.payment_method='cash'
      THEN sa.total END
    ),0) AS cash_total,

    COALESCE(SUM(
      CASE WHEN sa.payment_method!='cash'
      THEN sa.total END
    ),0) AS non_cash_total

  FROM cashier_shifts sh
  JOIN users u ON u.id = sh.kasir_id
  LEFT JOIN sales sa ON sa.shift_id = sh.id

  WHERE sh.store_id = ?
    AND sh.opened_at BETWEEN ? AND ?

  GROUP BY sh.id
  ORDER BY sh.id DESC
");
$q->execute([$storeId, $fromDT, $toDT]);
$rows = $q->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   Layout
========================= */
require __DIR__ . '/../publik/partials/admin_layout_top.php';
?>

<style>
  .wrap{max-width:1200px;}
  .muted{color:#64748b}
  .card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:14px;}
  .btn{padding:10px 14px;border-radius:12px;border:1px solid #e2e8f0;background:#0f172a;color:#fff;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:8px}
  .btn:active{transform:translateY(1px)}
  .btn-outline{background:#fff;color:#0f172a}
  .btn-outline:hover{background:#f8fafc}
  .btn-small{padding:8px 12px;border-radius:12px;font-size:13px}

  input{padding:10px 12px;border-radius:12px;border:1px solid #e2e8f0;width:100%}
  label{font-size:13px;color:#334155}

  .head{
    display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px;
  }
  h1{margin:0}
  .filter-form{
    display:grid;
    grid-template-columns: 1fr 1fr auto auto;
    gap:10px;
    align-items:end;
  }
  .filter-actions{display:flex;gap:10px;justify-content:flex-end}

  .table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}
  table{width:100%;border-collapse:collapse;background:#fff}
  th,td{border-bottom:1px solid #f1f5f9;padding:10px 8px;text-align:left;font-size:13px;vertical-align:top}
  th{color:#64748b;font-weight:700;white-space:nowrap}
  .money{white-space:nowrap}
  .pill{display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;border:1px solid #e2e8f0;white-space:nowrap}
  .pill.ok{background:#ecfdf5;border-color:#bbf7d0;color:#166534}
  .pill.bad{background:#fef2f2;border-color:#fecaca;color:#991b1b}
  .pill.neutral{background:#f8fafc;border-color:#e2e8f0;color:#334155}

  /* Mobile improvements */
  @media (max-width: 720px){
    .filter-form{grid-template-columns:1fr; }
    .filter-actions{justify-content:stretch}
    .filter-actions .btn{width:100%}
  }

  /* Table -> stacked cards on small screens (no horizontal scroll) */
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
      min-width:110px;
      flex:0 0 110px;
    }
  }
</style>

<div class="wrap">
  <div class="head">
    <div>
      <h1>Laporan Shift Kasir</h1>
      <div class="muted" style="margin-top:6px;">Ringkasan shift kasir berdasarkan rentang tanggal.</div>
    </div>
  </div>

  <!-- Filter card -->
  <div class="card" style="margin-bottom:12px;">
    <form method="get" class="filter-form">
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

      <div class="filter-actions">
        <a class="btn btn-outline" href="admin_shift_report.php">Reset</a>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="table-wrap">
      <table class="resp">
        <thead>
          <tr>
            <th>ID</th>
            <th>Kasir</th>
            <th>Open</th>
            <th>Close</th>
            <th>Awal</th>
            <th>Akhir</th>
            <th>Expected Cash</th>
            <th>Selisih</th>
            <th>Trx</th>
            <th>Omzet</th>
            <th>Cash</th>
            <th>Non Cash</th>
            <th>Status</th>
          </tr>
        </thead>

        <tbody>
        <?php if (!$rows): ?>
          <tr>
            <td colspan="13">Tidak ada data shift pada tanggal ini.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($rows as $r):

            $expectedCash = (int)$r['opening_cash'] + (int)$r['cash_total'];

            $closing = $r['closing_cash'] === null ? null : (int)$r['closing_cash'];

            $diff = $closing === null ? null : $closing - $expectedCash;

            $status = (string)($r['status'] ?? '');
            $statusClass = 'neutral';
            if ($status !== '') {
              $lower = strtolower($status);
              if (str_contains($lower, 'close') || str_contains($lower, 'closed') || str_contains($lower, 'selesai')) $statusClass = 'ok';
              if (str_contains($lower, 'open') || str_contains($lower, 'aktif')) $statusClass = 'neutral';
            }
          ?>
          <tr>
            <td data-label="ID">#<?= (int)$r['id'] ?></td>

            <td data-label="Kasir"><?= htmlspecialchars((string)$r['kasir_name']) ?></td>

            <td data-label="Open"><?= htmlspecialchars((string)$r['opened_at']) ?></td>

            <td data-label="Close"><?= htmlspecialchars((string)($r['closed_at'] ?? '-')) ?></td>

            <td data-label="Awal" class="money">
              Rp <?= number_format((int)$r['opening_cash'],0,',','.') ?>
            </td>

            <td data-label="Akhir" class="money">
              <?= $closing===null ? '-' : ('Rp '.number_format($closing,0,',','.')) ?>
            </td>

            <td data-label="Expected" class="money">
              Rp <?= number_format($expectedCash,0,',','.') ?>
            </td>

            <td data-label="Selisih" class="money">
              <?php if ($diff === null): ?>
                -
              <?php elseif ($diff === 0): ?>
                <span class="pill ok">0</span>
              <?php elseif ($diff > 0): ?>
                <span class="pill ok">+<?= number_format($diff,0,',','.') ?></span>
              <?php else: ?>
                <span class="pill bad"><?= number_format($diff,0,',','.') ?></span>
              <?php endif; ?>
            </td>

            <td data-label="Trx"><?= (int)$r['trx_count'] ?></td>

            <td data-label="Omzet" class="money">
              Rp <?= number_format((int)$r['omzet'],0,',','.') ?>
            </td>

            <td data-label="Cash" class="money">
              Rp <?= number_format((int)$r['cash_total'],0,',','.') ?>
            </td>

            <td data-label="Non Cash" class="money">
              Rp <?= number_format((int)$r['non_cash_total'],0,',','.') ?>
            </td>

            <td data-label="Status">
              <span class="pill <?= $statusClass ?>">
                <?= htmlspecialchars($status === '' ? '-' : $status) ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../publik/partials/admin_layout_bottom.php'; ?>

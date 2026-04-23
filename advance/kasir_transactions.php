<?php
declare(strict_types=1);

require __DIR__ . '/../config/auth.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/audit.php';

require_role(['kasir']);

$u = auth_user();
$kasirId = (int)($u['id'] ?? 0);

function rupiah(int $n): string {
  return 'Rp ' . number_format($n, 0, ',', '.');
}

if (!($pdo instanceof PDO)) {
  http_response_code(500);
  exit('DB not ready.');
}

$storeId = resolve_store_id($pdo, $u);
if (!$storeId) {
  http_response_code(400);
  exit('Kasir belum terhubung ke toko.');
}

// Store info
$storeName = 'Toko';
$st = $pdo->prepare('SELECT name FROM stores WHERE id=? LIMIT 1');
$st->execute([$storeId]);
if ($row = $st->fetch(PDO::FETCH_ASSOC)) $storeName = (string)$row['name'];

// ===== Filters =====
$q          = trim((string)($_GET['q'] ?? ''));
$dateFrom   = trim((string)($_GET['date_from'] ?? ''));
$dateTo     = trim((string)($_GET['date_to'] ?? ''));
$method     = strtolower(trim((string)($_GET['method'] ?? '')));
$kitchen    = trim((string)($_GET['kitchen'] ?? ''));
$shift      = trim((string)($_GET['shift'] ?? ''));

$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;
$perPage = 20;
$offset = 0;

$where = [
  'sa.store_id = :store_id',
  'sa.kasir_id = :kasir_id',
];
$params = [
  ':store_id' => $storeId,
  ':kasir_id' => $kasirId,
];

if ($q !== '') {
  $where[] = '(sa.invoice_no LIKE :q OR CAST(sa.id AS CHAR) = :qid)';
  $params[':q'] = '%' . $q . '%';
  $params[':qid'] = $q;
}

if ($dateFrom !== '') {
  $where[] = 'DATE(sa.created_at) >= :df';
  $params[':df'] = $dateFrom;
}
if ($dateTo !== '') {
  $where[] = 'DATE(sa.created_at) <= :dt';
  $params[':dt'] = $dateTo;
}

if (in_array($method, ['cash', 'qris'], true)) {
  $where[] = 'LOWER(sa.payment_method) = :pm';
  $params[':pm'] = $method;
}

if ($kitchen === '0' || $kitchen === '1') {
  $where[] = 'sa.kitchen_done = :kd';
  $params[':kd'] = (int)$kitchen;
}

if ($shift === 'active') {
  $activeShiftId = isset($_SESSION['active_shift_id']) ? (int)$_SESSION['active_shift_id'] : 0;
  if ($activeShiftId > 0) {
    $where[] = 'sa.shift_id = :sid';
    $params[':sid'] = $activeShiftId;
  } else {
    $where[] = '1=0';
  }
} elseif ($shift !== '' && ctype_digit($shift)) {
  $where[] = 'sa.shift_id = :sid';
  $params[':sid'] = (int)$shift;
}

$whereSql = implode(' AND ', $where);

// ===== Summary & Pagination =====
$sumQ = $pdo->prepare("SELECT COUNT(*) c, COALESCE(SUM(sa.total),0) t FROM sales sa WHERE {$whereSql}");
$sumQ->execute($params);
$sum = $sumQ->fetch(PDO::FETCH_ASSOC) ?: ['c'=>0,'t'=>0];
$totalRows = (int)($sum['c'] ?? 0);
$totalAmount = (int)($sum['t'] ?? 0);
$totalPages = (int)max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) $page = $totalPages;

$offset = ($page - 1) * $perPage;

$listQ = $pdo->prepare(
  "SELECT sa.id, sa.invoice_no, sa.total, sa.payment_method, sa.kitchen_done, sa.shift_id, sa.created_at
   FROM sales sa
   WHERE {$whereSql}
   ORDER BY sa.id DESC
   LIMIT {$perPage} OFFSET {$offset}"
);
$listQ->execute($params);
$rows = $listQ->fetchAll(PDO::FETCH_ASSOC);

// ===== Layout =====
$pageTitle = 'Riwayat Transaksi';
$activeMenu = 'kasir_transactions';
$appName = '';
require __DIR__ . '/../publik/partials/kasir_layout_top.php';

// Helper untuk query string pagination
function build_qs(array $overrides = []): string {
  $q = $_GET;
  foreach ($overrides as $k => $v) {
    if ($v === null) unset($q[$k]); else $q[$k] = (string)$v;
  }
  return http_build_query($q);
}

function badge(string $text, string $kind = ''): string {
  $cls = 'pill' . ($kind ? ' ' . $kind : '');
  return '<span class="' . $cls . '">' . htmlspecialchars($text) . '</span>';
}

function fmt_dt(string $s): string {
  // tampil ringkas di mobile kalau format mysql: YYYY-mm-dd HH:ii:ss
  // kalau format lain, balikin apa adanya
  if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $s)) {
    $ymd = substr($s, 0, 10);
    $hm  = substr($s, 11, 5);
    return $ymd . ' ' . $hm;
  }
  return $s;
}
?>

<style>
  .card{ background: var(--surface); border:1px solid var(--line); border-radius: var(--radius); box-shadow: 0 18px 50px rgba(15,23,42,.08); }
  .card-pad{ padding:14px; }
  .grid{ display:grid; gap:14px; }
  .muted{ color: var(--muted); font-size:12px; }
  .hrow{ display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; }
  .title{ font-size:18px; font-weight: 1000; margin:0; }
  .sub{ margin:2px 0 0; }

  /* filters: mobile rapi */
  .filters{ display:grid; gap:10px; }
  @media (max-width: 919px){
    .filters{ grid-template-columns: 1fr 1fr; }
    .filters .full{ grid-column: 1 / -1; }
    .filters .actions{ grid-column: 1 / -1; display:flex; gap:8px; }
  }
  @media (min-width: 920px){
    .filters{ grid-template-columns: 1.4fr .9fr .9fr .7fr .8fr .8fr auto; align-items:end; }
    .filters .actions{ display:flex; gap:8px; }
  }

  label{ font-size:12px; font-weight: 900; color: var(--muted); display:block; margin-bottom:6px; }
  input, select{ width:100%; padding:10px 12px; border-radius: 14px; border:1px solid var(--line); outline:none; background:#fff; }
  input:focus, select:focus{ border-color: rgba(37,99,235,.5); box-shadow: 0 0 0 4px rgba(37,99,235,.10); }

  .btn{ display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:10px 14px; border-radius: 14px; border:1px solid var(--line); background:#fff; font-weight: 950; cursor:pointer; text-decoration:none; white-space:nowrap; }
  .btn.primary{ background: rgba(37,99,235,.12); border-color: rgba(37,99,235,.35); color:#1d4ed8; }
  .btn.ghost{ background:#fff; }
  .btn:active{ transform: scale(.99); }

  .pill{ display:inline-flex; align-items:center; padding:6px 10px; border-radius: 999px; border:1px solid var(--line); font-size:12px; font-weight: 950; color: var(--muted); background:#fff; }
  .pill.good{ border-color: rgba(34,197,94,.35); background: rgba(34,197,94,.12); color:#065f46; }
  .pill.warn{ border-color: rgba(249,115,22,.35); background: rgba(249,115,22,.12); color:#7c2d12; }
  .pill.brand{ border-color: rgba(37,99,235,.35); background: rgba(37,99,235,.12); color:#1d4ed8; }

  /* desktop table */
  .twrap{ overflow:auto; border-radius: var(--radius); }
  table{ width:100%; border-collapse: separate; border-spacing: 0; }
  th, td{ padding: 12px 12px; border-bottom: 1px solid var(--line); text-align:left; vertical-align:top; }
  th{ font-size:12px; color: var(--muted); font-weight: 950; background: rgba(255,255,255,.55); position: sticky; top: 0px; backdrop-filter: blur(10px); }
  .mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; }
  .right{ text-align:right; }
  .nowrap{ white-space:nowrap; }
  .link{ font-weight: 950; }
  .empty{ padding: 18px; }

  /* pagination */
  .pager{ display:flex; gap:8px; align-items:center; justify-content:flex-end; flex-wrap:wrap; }
  .pager a{ text-decoration:none; }
  .pager .btn{ padding:8px 12px; border-radius: 12px; }

  /* ===== Mobile cards ===== */
  .desktopOnly{ display:block; }
  .mobileOnly{ display:none; }

  @media (max-width: 720px){
    .desktopOnly{ display:none; }
    .mobileOnly{ display:block; }

    .mob-list{ display:grid; gap:10px; padding:12px; }
    .trx{
      background:#fff;
      border:1px solid var(--line);
      border-radius: 18px;
      padding:12px;
      box-shadow: 0 12px 30px rgba(15,23,42,.06);
    }
    .trx-top{
      display:flex;
      gap:10px;
      align-items:flex-start;
      justify-content:space-between;
    }
    .trx-inv{
      min-width:0;
    }
    .trx-inv a{
      display:block;
      font-weight: 1000;
      text-decoration:none;
      color:inherit;
      word-break: break-word;
    }
    .trx-meta{
      font-size:12px;
      color: var(--muted);
      margin-top:4px;
      display:flex;
      gap:8px;
      flex-wrap:wrap;
      align-items:center;
    }
    .trx-total{
      font-weight: 1000;
      white-space:nowrap;
      margin-left:auto;
    }
    .trx-badges{
      display:flex;
      gap:8px;
      flex-wrap:wrap;
      margin-top:10px;
    }
    .trx-actions{
      display:flex;
      gap:8px;
      margin-top:12px;
    }
    .trx-actions .btn{ flex:1; padding:10px 12px; }
  }
</style>

<div class="grid">
  <section class="card card-pad">
    <div class="hrow">
      <div>
        <h1 class="title">Riwayat Transaksi</h1>
        <p class="muted sub"><?= htmlspecialchars($storeName) ?> • <?= htmlspecialchars((string)$u['name']) ?></p>
      </div>
      <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
        <?= badge('Total: ' . rupiah($totalAmount), 'brand') ?>
        <?= badge($totalRows . ' transaksi', '') ?>
        <a class="btn primary" href="kasir_pos.php">🧾 Transaksi Baru</a>
      </div>
    </div>

    <div style="height:12px"></div>

    <form class="filters" method="get" action="">
      <div class="full">
        <label>Invoice / ID</label>
        <input name="q" value="<?= htmlspecialchars($q) ?>" placeholder="INV... atau ID transaksi">
      </div>
      <div>
        <label>Dari</label>
        <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
      </div>
      <div>
        <label>Sampai</label>
        <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
      </div>
      <div>
        <label>Metode</label>
        <select name="method">
          <option value="" <?= $method===''?'selected':'' ?>>Semua</option>
          <option value="cash" <?= $method==='cash'?'selected':'' ?>>CASH</option>
          <option value="qris" <?= $method==='qris'?'selected':'' ?>>QRIS</option>
        </select>
      </div>
      <div>
        <label>Dapur</label>
        <select name="kitchen">
          <option value="" <?= $kitchen===''?'selected':'' ?>>Semua</option>
          <option value="0" <?= $kitchen==='0'?'selected':'' ?>>Belum selesai</option>
          <option value="1" <?= $kitchen==='1'?'selected':'' ?>>Selesai</option>
        </select>
      </div>
      <div>
        <label>Shift</label>
        <select name="shift">
          <option value="" <?= $shift===''?'selected':'' ?>>Semua</option>
          <option value="active" <?= $shift==='active'?'selected':'' ?>>Shift aktif</option>
        </select>
      </div>
      <div class="actions">
        <button class="btn primary" type="submit">🔎 Filter</button>
        <a class="btn ghost" href="kasir_transactions.php">↺ Reset</a>
      </div>
    </form>
  </section>

  <section class="card">
    <!-- MOBILE -->
    <div class="mobileOnly">
      <?php if (!$rows): ?>
        <div class="empty muted" style="padding:14px;">Tidak ada transaksi yang cocok dengan filter.</div>
      <?php else: ?>
        <div class="mob-list">
          <?php foreach ($rows as $r): ?>
            <?php
              $pm = strtolower((string)($r['payment_method'] ?? ''));
              $pmLbl = $pm ? strtoupper($pm) : '-';
              $pmKind = $pm === 'qris' ? 'brand' : '';
              $kd = (int)($r['kitchen_done'] ?? 0);
              $kdBadge = $kd ? badge('Dapur: selesai', 'good') : badge('Dapur: proses', 'warn');
              $pmBadge = badge('Bayar: ' . $pmLbl, $pmKind);
              $inv = (string)($r['invoice_no'] ?? '');
              if ($inv === '') $inv = 'TRX-' . (int)$r['id'];
              $dt = fmt_dt((string)$r['created_at']);
              $shiftLbl = $r['shift_id'] ? ('Shift #' . (int)$r['shift_id']) : 'Tanpa shift';
            ?>
            <div class="trx">
              <div class="trx-top">
                <div class="trx-inv">
                  <a class="mono" href="kasir_receipt.php?id=<?= (int)$r['id'] ?>"><?= htmlspecialchars($inv) ?></a>
                  <div class="trx-meta">
                    <span class="nowrap"><?= htmlspecialchars($dt) ?></span>
                    <span>•</span>
                    <span><?= htmlspecialchars($shiftLbl) ?></span>
                  </div>
                </div>
                <div class="trx-total"><?= rupiah((int)$r['total']) ?></div>
              </div>

              <div class="trx-badges">
                <?= $pmBadge ?>
                <?= $kdBadge ?>
              </div>

              <div class="trx-actions">
                <a class="btn" href="kasir_receipt.php?id=<?= (int)$r['id'] ?>">🖨️ Struk</a>
                <a class="btn primary" href="kasir_receipt.php?id=<?= (int)$r['id'] ?>">🔍 Detail</a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- DESKTOP -->
    <div class="desktopOnly">
      <div class="twrap">
        <table>
          <thead>
            <tr>
              <th style="min-width:110px">Invoice</th>
              <th class="nowrap" style="min-width:160px">Waktu</th>
              <th style="min-width:140px">Status</th>
              <th style="min-width:90px">Shift</th>
              <th class="right" style="min-width:140px">Total</th>
              <th style="min-width:120px">Aksi</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="6" class="empty muted">Tidak ada transaksi yang cocok dengan filter.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <?php
                $pm = strtolower((string)($r['payment_method'] ?? ''));
                $pmLbl = $pm ? strtoupper($pm) : '-';
                $pmKind = $pm === 'qris' ? 'brand' : '';
                $kd = (int)($r['kitchen_done'] ?? 0);
                $kdBadge = $kd ? badge('Dapur: selesai', 'good') : badge('Dapur: proses', 'warn');
                $pmBadge = badge('Bayar: ' . $pmLbl, $pmKind);
                $inv = (string)($r['invoice_no'] ?? '');
                if ($inv === '') $inv = 'TRX-' . (int)$r['id'];
              ?>
              <tr>
                <td class="mono"><a class="link" href="kasir_receipt.php?id=<?= (int)$r['id'] ?>"><?= htmlspecialchars($inv) ?></a></td>
                <td class="nowrap"><?= htmlspecialchars((string)$r['created_at']) ?></td>
                <td><?= $pmBadge ?> <?= $kdBadge ?></td>
                <td><?= $r['shift_id'] ? ('#' . (int)$r['shift_id']) : '-' ?></td>
                <td class="right nowrap"><b><?= rupiah((int)$r['total']) ?></b></td>
                <td class="nowrap">
                  <a class="btn" href="kasir_receipt.php?id=<?= (int)$r['id'] ?>">🖨️ Struk</a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card-pad" style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
      <div class="muted">Halaman <?= (int)$page ?> dari <?= (int)$totalPages ?></div>
      <div class="pager">
        <?php
          $prev = $page - 1;
          $next = $page + 1;
        ?>
        <a class="btn" href="kasir_transactions.php?<?= htmlspecialchars(build_qs(['page'=>1])) ?>" <?= $page<=1?'style="pointer-events:none; opacity:.55"':'' ?>>⏮</a>
        <a class="btn" href="kasir_transactions.php?<?= htmlspecialchars(build_qs(['page'=>$prev])) ?>" <?= $page<=1?'style="pointer-events:none; opacity:.55"':'' ?>>←</a>
        <a class="btn" href="kasir_transactions.php?<?= htmlspecialchars(build_qs(['page'=>$next])) ?>" <?= $page>=$totalPages?'style="pointer-events:none; opacity:.55"':'' ?>>→</a>
        <a class="btn" href="kasir_transactions.php?<?= htmlspecialchars(build_qs(['page'=>$totalPages])) ?>" <?= $page>=$totalPages?'style="pointer-events:none; opacity:.55"':'' ?>>⏭</a>
      </div>
    </div>
  </section>
</div>

<?php require __DIR__ . '/../publik/partials/kasir_layout_bottom.php'; ?>

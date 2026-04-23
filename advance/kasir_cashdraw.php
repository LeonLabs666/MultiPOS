<?php
declare(strict_types=1);

require __DIR__ . '/../config/auth.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/audit.php';

require_role(['kasir']);

$actor = auth_user();
$kasirId = (int)($actor['id'] ?? 0);

function rupiah(int $n): string {
  return 'Rp ' . number_format($n, 0, ',', '.');
}

function fmt_dt(string $s): string {
  // ringkas untuk mobile: YYYY-mm-dd HH:ii
  if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $s)) {
    return substr($s, 0, 10) . ' ' . substr($s, 11, 5);
  }
  return $s;
}

if (!($pdo instanceof PDO)) {
  http_response_code(500);
  exit('DB not ready.');
}

$storeId = resolve_store_id($pdo, $actor);
if (!$storeId) {
  http_response_code(400);
  exit('Kasir belum terhubung ke toko.');
}

// Store info
$storeName = 'Toko';
$st = $pdo->prepare('SELECT name FROM stores WHERE id=? LIMIT 1');
$st->execute([$storeId]);
if ($row = $st->fetch(PDO::FETCH_ASSOC)) $storeName = (string)$row['name'];

// Active shift (prefer session, fallback query)
$activeShiftId = isset($_SESSION['active_shift_id']) ? (int)$_SESSION['active_shift_id'] : 0;
if ($activeShiftId <= 0) {
  $q = $pdo->prepare("
    SELECT id
    FROM cashier_shifts
    WHERE store_id=? AND kasir_id=? AND status='open'
    ORDER BY id DESC
    LIMIT 1
  ");
  $q->execute([$storeId, $kasirId]);
  if ($r = $q->fetch(PDO::FETCH_ASSOC)) $activeShiftId = (int)$r['id'];
}

$error = '';
$ok = '';

// ===== Handle POST (add movement) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();

  $type = (string)($_POST['type'] ?? '');
  $amount = (int)($_POST['amount'] ?? 0);
  $category = trim((string)($_POST['category'] ?? ''));
  $note = trim((string)($_POST['note'] ?? ''));

  if (!in_array($type, ['in','out'], true)) {
    $error = 'Tipe tidak valid.';
  } elseif ($amount <= 0) {
    $error = 'Nominal harus > 0.';
  } else {
    if ($activeShiftId <= 0) {
      $error = 'Tidak ada shift yang sedang open. Buka shift dulu sebelum catat kas.';
    } else {
      $ins = $pdo->prepare("
        INSERT INTO cash_movements(store_id, shift_id, kasir_id, type, amount, category, note, created_at)
        VALUES (:store_id, :shift_id, :kasir_id, :type, :amount, :category, :note, NOW())
      ");
      $ins->execute([
        ':store_id' => $storeId,
        ':shift_id' => $activeShiftId,
        ':kasir_id' => $kasirId,
        ':type' => $type,
        ':amount' => $amount,
        ':category' => ($category !== '' ? mb_substr($category, 0, 50) : null),
        ':note' => ($note !== '' ? mb_substr($note, 0, 255) : null),
      ]);

      $id = (int)$pdo->lastInsertId();

      $action = $type === 'in' ? 'CASH_IN' : 'CASH_OUT';
      if (function_exists('log_activity')) {
        log_activity(
          $pdo,
          $actor,
          $action,
          ($type === 'in' ? 'Kas masuk' : 'Kas keluar') . " #" . $id . " (" . $amount . ")",
          'cash_movement',
          $id,
          [
            'store_id' => $storeId,
            'shift_id' => $activeShiftId,
            'kasir_id' => $kasirId,
            'type' => $type,
            'amount' => $amount,
            'category' => ($category !== '' ? $category : null),
            'note' => ($note !== '' ? $note : null),
          ],
          $storeId
        );
      }

      header('Location: kasir_cashdraw.php?ok=1');
      exit;
    }
  }
}
if (isset($_GET['ok'])) $ok = 'Tersimpan.';

// ===== Filters =====
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo   = trim((string)($_GET['date_to'] ?? ''));
$typeF    = trim((string)($_GET['type'] ?? ''));
$shiftF   = trim((string)($_GET['shift'] ?? ''));

$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;
$perPage = 20;

$where = ['cm.store_id = :store_id', 'cm.kasir_id = :kasir_id'];
$params = [':store_id' => $storeId, ':kasir_id' => $kasirId];

if ($dateFrom !== '') { $where[] = 'DATE(cm.created_at) >= :df'; $params[':df'] = $dateFrom; }
if ($dateTo !== '')   { $where[] = 'DATE(cm.created_at) <= :dt'; $params[':dt'] = $dateTo; }
if (in_array($typeF, ['in','out'], true)) { $where[] = 'cm.type = :tp'; $params[':tp'] = $typeF; }

if ($shiftF === 'active') {
  if ($activeShiftId > 0) { $where[] = 'cm.shift_id = :sid'; $params[':sid'] = $activeShiftId; }
  else { $where[] = '1=0'; }
}

$whereSql = implode(' AND ', $where);

// Summary
$sumQ = $pdo->prepare("
  SELECT
    COALESCE(SUM(CASE WHEN cm.type='in'  THEN cm.amount ELSE 0 END),0) AS total_in,
    COALESCE(SUM(CASE WHEN cm.type='out' THEN cm.amount ELSE 0 END),0) AS total_out,
    COUNT(*) AS cnt
  FROM cash_movements cm
  WHERE $whereSql
");
$sumQ->execute($params);
$sum = $sumQ->fetch(PDO::FETCH_ASSOC) ?: ['total_in'=>0,'total_out'=>0,'cnt'=>0];

$totalIn  = (int)($sum['total_in'] ?? 0);
$totalOut = (int)($sum['total_out'] ?? 0);
$totalCnt = (int)($sum['cnt'] ?? 0);
$net = $totalIn - $totalOut;

$totalPages = (int)max(1, (int)ceil($totalCnt / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$listQ = $pdo->prepare("
  SELECT cm.id, cm.type, cm.amount, cm.category, cm.note, cm.shift_id, cm.created_at
  FROM cash_movements cm
  WHERE $whereSql
  ORDER BY cm.id DESC
  LIMIT $perPage OFFSET $offset
");
$listQ->execute($params);
$rows = $listQ->fetchAll(PDO::FETCH_ASSOC);

// ===== Layout =====
$pageTitle = 'Kas Masuk/Keluar';
$activeMenu = 'kasir_cashdraw';
$appName = '';
require __DIR__ . '/../publik/partials/kasir_layout_top.php';

function build_qs(array $overrides = []): string {
  $q = $_GET;
  foreach ($overrides as $k => $v) {
    if ($v === null) unset($q[$k]); else $q[$k] = (string)$v;
  }
  return http_build_query($q);
}
?>
<style>
  .card{ background: var(--surface); border:1px solid var(--line); border-radius: var(--radius); box-shadow: 0 18px 50px rgba(15,23,42,.08); }
  .card-pad{ padding:14px; }
  .grid{ display:grid; gap:14px; }
  .hrow{ display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; }
  .title{ font-size:18px; font-weight: 1000; margin:0; }
  .muted{ color: var(--muted); font-size:12px; }

  .pill{ display:inline-flex; align-items:center; padding:6px 10px; border-radius:999px; border:1px solid var(--line); font-size:12px; font-weight:950; color: var(--muted); background:#fff; }
  .pill.good{ border-color: rgba(34,197,94,.35); background: rgba(34,197,94,.12); color:#065f46; }
  .pill.warn{ border-color: rgba(249,115,22,.35); background: rgba(249,115,22,.12); color:#7c2d12; }
  .pill.brand{ border-color: rgba(37,99,235,.35); background: rgba(37,99,235,.12); color:#1d4ed8; }

  label{ font-size:12px; font-weight:900; color: var(--muted); display:block; margin-bottom:6px; }
  input, select{ width:100%; padding:10px 12px; border-radius:14px; border:1px solid var(--line); outline:none; background:#fff; }
  input:focus, select:focus{ border-color: rgba(37,99,235,.5); box-shadow: 0 0 0 4px rgba(37,99,235,.10); }

  .btn{ display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:10px 14px; border-radius:14px; border:1px solid var(--line); background:#fff; font-weight:950; cursor:pointer; text-decoration:none; white-space:nowrap; }
  .btn.primary{ background: rgba(37,99,235,.12); border-color: rgba(37,99,235,.35); color:#1d4ed8; }
  .btn:active{ transform: scale(.99); }

  /* forms: mobile lebih rapi */
  .formgrid, .filters{ display:grid; gap:10px; }
  @media(max-width: 919px){
    .formgrid{ grid-template-columns: 1fr 1fr; }
    .formgrid .full{ grid-column: 1 / -1; }
    .formgrid .actions{ grid-column: 1 / -1; display:flex; gap:8px; }
    .filters{ grid-template-columns: 1fr 1fr; }
    .filters .actions{ grid-column: 1 / -1; display:flex; gap:8px; }
  }
  @media(min-width: 920px){
    .formgrid{ grid-template-columns: .9fr 1fr 1fr 1.2fr auto; align-items:end; }
    .filters{ grid-template-columns: 1fr 1fr .8fr .8fr auto; align-items:end; }
    .actions{ display:flex; gap:8px; }
  }

  /* desktop table */
  .twrap{ overflow:auto; border-radius: var(--radius); }
  table{ width:100%; border-collapse: separate; border-spacing: 0; position: relative; }
  th, td{ padding: 12px 12px; border-bottom: 1px solid var(--line); text-align:left; vertical-align:top; }
  th{ font-size:12px; color: var(--muted); font-weight: 950; background: rgba(255,255,255,.85); position: sticky; top: 0; z-index: 2; backdrop-filter: blur(10px); }
  .mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Courier New', monospace; }
  .right{ text-align:right; }
  .nowrap{ white-space:nowrap; }

  .pager{ display:flex; gap:8px; align-items:center; justify-content:flex-end; flex-wrap:wrap; }
  .pager .btn{ padding:8px 12px; border-radius: 12px; }

  /* ===== Mobile cards ===== */
  .desktopOnly{ display:block; }
  .mobileOnly{ display:none; }

  @media (max-width: 720px){
    .desktopOnly{ display:none; }
    .mobileOnly{ display:block; }

    .mob-list{ display:grid; gap:10px; padding:12px; }
    .mv{
      background:#fff;
      border:1px solid var(--line);
      border-radius:18px;
      padding:12px;
      box-shadow: 0 12px 30px rgba(15,23,42,.06);
    }
    .mv-top{ display:flex; align-items:flex-start; justify-content:space-between; gap:10px; }
    .mv-id{ font-weight:1000; }
    .mv-meta{ font-size:12px; color: var(--muted); margin-top:4px; display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
    .mv-amt{ font-weight:1000; white-space:nowrap; }
    .mv-badges{ display:flex; gap:8px; flex-wrap:wrap; margin-top:10px; }
    .mv-note{ margin-top:10px; font-size:13px; color:#0f172a; }
    .mv-note .muted{ font-size:12px; }
  }
</style>

<div class="grid">

  <section class="card card-pad">
    <div class="hrow">
      <div>
        <h1 class="title">Kas Masuk/Keluar</h1>
        <div class="muted"><?= htmlspecialchars($storeName) ?> • <?= htmlspecialchars((string)$actor['name']) ?></div>
      </div>
      <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
        <?php if($activeShiftId>0): ?>
          <span class="pill good">Shift aktif: #<?= (int)$activeShiftId ?></span>
        <?php else: ?>
          <span class="pill warn">Shift belum open</span>
        <?php endif; ?>
        <span class="pill brand">Net: <?= rupiah($net) ?></span>
      </div>
    </div>

    <?php if($error): ?>
      <div style="margin-top:10px" class="pill warn"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if($ok): ?>
      <div style="margin-top:10px" class="pill good"><?= htmlspecialchars($ok) ?></div>
    <?php endif; ?>

    <div style="height:12px"></div>

    <form method="post" class="formgrid">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

      <div>
        <label>Tipe</label>
        <select name="type" required>
          <option value="in">Kas Masuk</option>
          <option value="out">Kas Keluar</option>
        </select>
      </div>

      <div>
        <label>Nominal</label>
        <input type="number" name="amount" min="1" placeholder="contoh: 50000" required>
      </div>

      <div class="full">
        <label>Kategori (opsional)</label>
        <input name="category" placeholder="contoh: tambah modal / pengeluaran">
      </div>

      <div class="full">
        <label>Catatan (opsional)</label>
        <input name="note" placeholder="contoh: beli plastik, parkir, dll">
      </div>

      <div class="actions">
        <button class="btn primary" type="submit">➕ Simpan</button>
        <a class="btn" href="kasir_cashdraw.php">↺ Reset</a>
      </div>
    </form>
  </section>

  <section class="card card-pad">
    <div class="hrow">
      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <span class="pill">Masuk: <b><?= rupiah($totalIn) ?></b></span>
        <span class="pill">Keluar: <b><?= rupiah($totalOut) ?></b></span>
        <span class="pill"><?= (int)$totalCnt ?> catatan</span>
      </div>
    </div>

    <div style="height:12px"></div>

    <form class="filters" method="get">
      <div>
        <label>Dari</label>
        <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
      </div>
      <div>
        <label>Sampai</label>
        <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
      </div>
      <div>
        <label>Tipe</label>
        <select name="type">
          <option value="" <?= $typeF===''?'selected':'' ?>>Semua</option>
          <option value="in" <?= $typeF==='in'?'selected':'' ?>>Masuk</option>
          <option value="out" <?= $typeF==='out'?'selected':'' ?>>Keluar</option>
        </select>
      </div>
      <div>
        <label>Shift</label>
        <select name="shift">
          <option value="" <?= $shiftF===''?'selected':'' ?>>Semua</option>
          <option value="active" <?= $shiftF==='active'?'selected':'' ?>>Shift aktif</option>
        </select>
      </div>
      <div class="actions">
        <button class="btn primary" type="submit">🔎 Filter</button>
        <a class="btn" href="kasir_cashdraw.php">↺ Reset</a>
      </div>
    </form>
  </section>

  <section class="card">
    <!-- MOBILE -->
    <div class="mobileOnly">
      <?php if(!$rows): ?>
        <div class="muted" style="padding:14px;">Belum ada catatan kas.</div>
      <?php else: ?>
        <div class="mob-list">
          <?php foreach($rows as $r): ?>
            <?php
              $tp = (string)($r['type'] ?? '');
              $tpLbl = $tp === 'in' ? 'Kas Masuk' : 'Kas Keluar';
              $tpPill = $tp === 'in' ? 'pill good' : 'pill warn';
              $cat = trim((string)($r['category'] ?? ''));
              $note = trim((string)($r['note'] ?? ''));
              $dt = fmt_dt((string)$r['created_at']);
              $shiftLbl = $r['shift_id'] ? ('Shift #' . (int)$r['shift_id']) : 'Tanpa shift';
              $amt = (int)($r['amount'] ?? 0);
            ?>
            <div class="mv">
              <div class="mv-top">
                <div>
                  <div class="mv-id">#<?= (int)$r['id'] ?></div>
                  <div class="mv-meta">
                    <span class="nowrap"><?= htmlspecialchars($dt) ?></span>
                    <span>•</span>
                    <span><?= htmlspecialchars($shiftLbl) ?></span>
                  </div>
                </div>
                <div class="mv-amt"><?= rupiah($amt) ?></div>
              </div>

              <div class="mv-badges">
                <span class="<?= $tpPill ?>"><?= htmlspecialchars($tpLbl) ?></span>
                <?php if($cat !== ''): ?>
                  <span class="pill"><?= htmlspecialchars($cat) ?></span>
                <?php endif; ?>
              </div>

              <?php if($note !== ''): ?>
                <div class="mv-note">
                  <div class="muted" style="margin-bottom:4px;">Catatan</div>
                  <?= htmlspecialchars($note) ?>
                </div>
              <?php endif; ?>
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
              <th style="min-width:90px">ID</th>
              <th style="min-width:160px">Waktu</th>
              <th style="min-width:120px">Tipe</th>
              <th>Kategori</th>
              <th>Catatan</th>
              <th style="min-width:90px">Shift</th>
              <th class="right" style="min-width:140px">Nominal</th>
            </tr>
          </thead>
          <tbody>
          <?php if(!$rows): ?>
            <tr><td colspan="7" class="muted" style="padding:18px">Belum ada catatan kas.</td></tr>
          <?php else: ?>
            <?php foreach($rows as $r): ?>
              <?php
                $tp = (string)($r['type'] ?? '');
                $tpLbl = $tp === 'in' ? 'Masuk' : 'Keluar';
                $tpPill = $tp === 'in' ? 'pill good' : 'pill warn';
              ?>
              <tr>
                <td class="mono">#<?= (int)$r['id'] ?></td>
                <td class="nowrap"><?= htmlspecialchars((string)$r['created_at']) ?></td>
                <td><span class="<?= $tpPill ?>"><?= htmlspecialchars($tpLbl) ?></span></td>
                <td><?= htmlspecialchars((string)($r['category'] ?? '-')) ?></td>
                <td><?= htmlspecialchars((string)($r['note'] ?? '-')) ?></td>
                <td><?= $r['shift_id'] ? ('#'.(int)$r['shift_id']) : '-' ?></td>
                <td class="right nowrap"><b><?= rupiah((int)$r['amount']) ?></b></td>
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
        <?php $prev=$page-1; $next=$page+1; ?>
        <a class="btn" href="kasir_cashdraw.php?<?= htmlspecialchars(build_qs(['page'=>1])) ?>" <?= $page<=1?'style="pointer-events:none; opacity:.55"':'' ?>>⏮</a>
        <a class="btn" href="kasir_cashdraw.php?<?= htmlspecialchars(build_qs(['page'=>$prev])) ?>" <?= $page<=1?'style="pointer-events:none; opacity:.55"':'' ?>>←</a>
        <a class="btn" href="kasir_cashdraw.php?<?= htmlspecialchars(build_qs(['page'=>$next])) ?>" <?= $page>=$totalPages?'style="pointer-events:none; opacity:.55"':'' ?>>→</a>
        <a class="btn" href="kasir_cashdraw.php?<?= htmlspecialchars(build_qs(['page'=>$totalPages])) ?>" <?= $page>=$totalPages?'style="pointer-events:none; opacity:.55"':'' ?>>⏭</a>
      </div>
    </div>

  </section>

</div>

<?php require __DIR__ . '/../publik/partials/kasir_layout_bottom.php'; ?>

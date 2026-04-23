<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';
require __DIR__ . '/../config/audit.php';

require_role(['kasir']);

$actor   = auth_user();
$kasirId = (int)$actor['id'];

/* ===== Store dari admin pembuat kasir ===== */
$st = $pdo->prepare("
  SELECT s.id, s.name
  FROM users k
  JOIN stores s ON s.owner_admin_id = k.created_by
  WHERE k.id=? AND s.is_active=1
  LIMIT 1
");
$st->execute([$kasirId]);
$store = $st->fetch();
if (!$store) { http_response_code(400); exit('Kasir belum terhubung ke toko.'); }

$storeId   = (int)$store['id'];
$storeName = (string)$store['name'];

$error = '';
$ok    = '';

/* ===== Shift open saat ini ===== */
$cur = $pdo->prepare("
  SELECT *
  FROM cashier_shifts
  WHERE store_id=? AND kasir_id=? AND status='open'
  ORDER BY id DESC
  LIMIT 1
");
$cur->execute([$storeId, $kasirId]);
$openShift = $cur->fetch();

/* ===== POST actions ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $act = (string)($_POST['action'] ?? '');

  if ($act === 'open') {
    if ($openShift) {
      $error = 'Shift masih open, tutup dulu.';
    } else {
      $opening = max(0, (int)($_POST['opening_cash'] ?? 0));

      $pdo->prepare("
        INSERT INTO cashier_shifts(store_id,kasir_id,opened_at,opening_cash,status)
        VALUES (?,?,NOW(),?,'open')
      ")->execute([$storeId, $kasirId, $opening]);

      $shiftId = (int)$pdo->lastInsertId();

      log_activity(
        $pdo,
        $actor,
        'SHIFT_OPEN',
        'Open shift #' . $shiftId . ' (opening_cash ' . $opening . ')',
        'cashier_shift',
        $shiftId,
        [
          'store_id' => $storeId,
          'kasir_id' => $kasirId,
          'opening_cash' => $opening,
        ],
        $storeId
      );

      header('Location: kasir_shift.php');
      exit;
    }
  }

  if ($act === 'close') {
    if (!$openShift) {
      $error = 'Tidak ada shift yang open.';
    } else {
      $closing = (int)($_POST['closing_cash'] ?? 0);
      $note    = trim((string)($_POST['note'] ?? ''));

      $pdo->prepare("
        UPDATE cashier_shifts
        SET closed_at=NOW(), closing_cash=?, note=?, status='closed'
        WHERE id=? AND store_id=? AND kasir_id=? AND status='open'
      ")->execute([
        $closing,
        $note !== '' ? $note : null,
        (int)$openShift['id'],
        $storeId,
        $kasirId
      ]);

      $shiftId = (int)$openShift['id'];

      log_activity(
        $pdo,
        $actor,
        'SHIFT_CLOSE',
        'Close shift #' . $shiftId . ' (closing_cash ' . $closing . ')',
        'cashier_shift',
        $shiftId,
        [
          'store_id' => $storeId,
          'kasir_id' => $kasirId,
          'closing_cash' => $closing,
          'note' => ($note !== '' ? $note : null),
        ],
        $storeId
      );

      header('Location: kasir_shift.php');
      exit;
    }
  }
}

/* ===== Refresh openShift setelah POST redirect (aman juga buat tampilan) ===== */
$cur->execute([$storeId, $kasirId]);
$openShift = $cur->fetch();

/* ===== Set badge shift di layout (biar konsisten dengan halaman lain) ===== */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if ($openShift) $_SESSION['active_shift_id'] = (int)$openShift['id'];
else unset($_SESSION['active_shift_id']);

/* ===== histori 10 shift terakhir ===== */
$hist = $pdo->prepare("
  SELECT id, opened_at, closed_at, opening_cash, closing_cash, status
  FROM cashier_shifts
  WHERE store_id=? AND kasir_id=?
  ORDER BY id DESC
  LIMIT 10
");
$hist->execute([$storeId, $kasirId]);
$rows = $hist->fetchAll();

/* ===== Layout Kasir (cerah) ===== */
$appName    = '';
$pageTitle  = 'Shift Kasir';
$activeMenu = 'kasir_shift';
require __DIR__ . '/../publik/partials/kasir_layout_top.php';
?>

<style>
  .shift-wrap{ max-width: 1100px; margin: 0 auto; }
  .grid{
    display:grid;
    grid-template-columns: 1.2fr .8fr;
    gap: 14px;
    margin-top: 10px;
  }
  @media(max-width: 900px){ .grid{ grid-template-columns:1fr; } }

  .card{
    background:#fff;
    border:1px solid rgba(15,23,42,.10);
    border-radius:18px;
    padding:16px;
    box-shadow: 0 18px 50px rgba(15,23,42,.06);
  }
  .h1{
    margin:0;
    font-size:16px;
    font-weight:1000;
    color:#0f172a;
  }
  .sub{ color:#64748b; font-size:12px; margin-top:4px; }

  .pill{
    display:inline-flex; align-items:center; gap:8px;
    padding:8px 12px;
    border-radius:999px;
    border:1px solid rgba(15,23,42,.12);
    background:#fff;
    font-size:12px;
    font-weight:900;
    color:#0f172a;
    margin-top:10px;
  }
  .pill.ok{ background:rgba(34,197,94,.10); border-color:rgba(34,197,94,.25); color:#166534; }
  .pill.warn{ background:rgba(249,115,22,.10); border-color:rgba(249,115,22,.25); color:#9a3412; }

  .kv{
    display:grid;
    grid-template-columns: 140px 1fr;
    gap:8px 12px;
    margin-top:12px;
    font-size:13px;
  }
  .kv .k{ color:#64748b; }
  .money{ white-space:nowrap; font-weight:900; }

  .field{ display:flex; flex-direction:column; gap:6px; margin-top:10px; }
  label{ font-size:12px; color:#64748b; font-weight:800; }
  input[type="number"], input[type="text"], input[name="note"]{
    border:1px solid rgba(15,23,42,.12);
    border-radius:14px;
    padding:10px 12px;
    outline:none;
    font-size:14px;
  }
  input:focus{ border-color: rgba(37,99,235,.45); box-shadow:0 0 0 4px rgba(37,99,235,.10); }
  .btn{
    border:none;
    border-radius:14px;
    padding:10px 12px;
    font-weight:950;
    cursor:pointer;
  }
  .btn.primary{ background:#2563eb; color:#fff; }
  .btn.danger{ background:#ef4444; color:#fff; }
  .hint{ font-size:12px; color:#64748b; margin-top:6px; }

  .alert{
    margin-top:10px;
    padding:10px 12px;
    border-radius:14px;
    border:1px solid rgba(15,23,42,.10);
    background:#fff;
    font-size:13px;
    font-weight:800;
  }
  .alert.error{ border-color: rgba(239,68,68,.25); background: rgba(239,68,68,.06); color:#991b1b; }
  .alert.ok{ border-color: rgba(34,197,94,.25); background: rgba(34,197,94,.06); color:#166534; }

  .table-wrap{
    margin-top: 14px;
    overflow:auto;
    border-radius:18px;
    border:1px solid rgba(15,23,42,.10);
    background:#fff;
    box-shadow: 0 18px 50px rgba(15,23,42,.06);
  }
  table{ width:100%; border-collapse:collapse; min-width:760px; }
  th,td{ padding:10px 12px; font-size:13px; border-bottom:1px solid rgba(15,23,42,.08); text-align:left; }
  th{ background: rgba(37,99,235,.06); color:#0f172a; font-weight:1000; }
  tr:hover td{ background: rgba(2,6,23,.02); }

  .status{
    display:inline-flex; align-items:center; gap:8px;
    padding:6px 10px;
    border-radius:999px;
    border:1px solid rgba(15,23,42,.10);
    font-weight:950;
    font-size:12px;
  }
  .dot{ width:10px; height:10px; border-radius:999px; background:#94a3b8; }
  .status.open{ background:rgba(34,197,94,.08); border-color:rgba(34,197,94,.22); color:#166534; }
  .status.open .dot{ background:#16a34a; }
  .status.closed{ background:rgba(148,163,184,.08); border-color:rgba(148,163,184,.20); color:#334155; }
</style>

<div class="shift-wrap">
  <div class="card">
    <div class="h1">Shift Kasir</div>
    <div class="sub"><?= htmlspecialchars($storeName) ?></div>

    <?php if ($error): ?>
      <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($ok): ?>
      <div class="alert ok"><?= htmlspecialchars($ok) ?></div>
    <?php endif; ?>
  </div>

  <div class="grid">
    <div class="card">
      <div class="h1">Status Shift</div>

      <?php if (!$openShift): ?>
        <div class="pill warn">● Belum ada shift yang open</div>
        <div class="hint">Buka shift sebelum transaksi agar cash & laporan shift tercatat rapi.</div>

        <div class="kv">
          <div class="k">Kasir</div><div><b>#<?= (int)$kasirId ?></b></div>
          <div class="k">Toko</div><div><?= htmlspecialchars($storeName) ?></div>
        </div>
      <?php else: ?>
        <div class="pill ok">● Shift sedang open</div>

        <div class="kv">
          <div class="k">Shift ID</div><div><b>#<?= (int)$openShift['id'] ?></b></div>
          <div class="k">Open</div><div><?= htmlspecialchars((string)$openShift['opened_at']) ?></div>
          <div class="k">Uang Awal</div>
          <div class="money">Rp <?= number_format((int)$openShift['opening_cash'],0,',','.') ?></div>
        </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <?php if (!$openShift): ?>
        <div class="h1">Open Shift</div>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <input type="hidden" name="action" value="open">

          <div class="field">
            <label>Uang Awal</label>
            <input type="number" name="opening_cash" min="0" value="0" required>
            <div class="hint">Isi uang kas awal (mis. uang kembalian).</div>
          </div>

          <button class="btn primary" type="submit">Buka Shift</button>
        </form>
      <?php else: ?>
        <div class="h1">Close Shift</div>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <input type="hidden" name="action" value="close">

          <div class="field">
            <label>Uang Akhir</label>
            <input type="number" name="closing_cash" min="0" required>
          </div>

          <div class="field">
            <label>Catatan (opsional)</label>
            <input name="note" placeholder="contoh: selisih kecil">
          </div>

          <button class="btn danger" type="submit">Tutup Shift</button>
          <div class="hint">Pastikan hitung uang fisik di laci kas sebelum menutup shift.</div>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Open</th>
          <th>Close</th>
          <th>Awal</th>
          <th>Akhir</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><b>#<?= (int)$r['id'] ?></b></td>
          <td><?= htmlspecialchars((string)$r['opened_at']) ?></td>
          <td><?= htmlspecialchars((string)($r['closed_at'] ?? '-')) ?></td>
          <td class="money">Rp <?= number_format((int)$r['opening_cash'],0,',','.') ?></td>
          <td class="money"><?= $r['closing_cash']===null ? '-' : 'Rp '.number_format((int)$r['closing_cash'],0,',','.') ?></td>
          <td>
            <span class="status <?= $r['status']==='open' ? 'open' : 'closed' ?>">
              <span class="dot"></span>
              <?= htmlspecialchars((string)$r['status']) ?>
            </span>
          </td>
        </tr>
      <?php endforeach; if (!$rows): ?>
        <tr><td colspan="6">Belum ada shift.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="hint" style="margin:10px 2px 0;"></div>
</div>

<?php require __DIR__ . '/../publik/partials/kasir_layout_bottom.php'; ?>

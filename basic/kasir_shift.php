<?php
declare(strict_types=1);

require __DIR__ . '/kasir_bootstrap.php';

$pageTitle  = 'Shift Kasir (Basic)';
$activeMenu = 'kasir_shift';

$kasirId = (int)(auth_user()['id'] ?? 0);

// Ambil shift open (jaga-jaga)
$sh = $pdo->prepare("
  SELECT *
  FROM cashier_shifts
  WHERE store_id=? AND kasir_id=? AND status='open'
  ORDER BY id DESC
  LIMIT 1
");
$sh->execute([$storeId, $kasirId]);
$openShift = $sh->fetch(PDO::FETCH_ASSOC);

$err = '';
$ok  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $action = (string)($_POST['action'] ?? '');

  if ($action === 'open') {
    if ($openShift) {
      $err = 'Shift sudah OPEN.';
    } else {
      $openingCash = (int)($_POST['opening_cash'] ?? 0);
      $note = trim((string)($_POST['note'] ?? ''));

      $ins = $pdo->prepare("
        INSERT INTO cashier_shifts (store_id, kasir_id, opened_at, opening_cash, note, status)
        VALUES (?, ?, NOW(), ?, ?, 'open')
      ");
      $ins->execute([$storeId, $kasirId, $openingCash, $note]);

      $newId = (int)$pdo->lastInsertId();
      $_SESSION['active_shift_id'] = $newId;

      $ok = 'Shift berhasil dibuka. ID #' . $newId;
      // refresh
      $sh->execute([$storeId, $kasirId]);
      $openShift = $sh->fetch(PDO::FETCH_ASSOC);
    }
  }

  if ($action === 'close') {
    if (!$openShift) {
      $err = 'Tidak ada shift OPEN.';
    } else {
      $closingCash = (int)($_POST['closing_cash'] ?? 0);
      $note = trim((string)($_POST['note'] ?? ''));

      $upd = $pdo->prepare("
        UPDATE cashier_shifts
        SET closed_at=NOW(), closing_cash=?, note=?, status='closed'
        WHERE id=? AND store_id=? AND kasir_id=? AND status='open'
        LIMIT 1
      ");
      $upd->execute([$closingCash, $note, (int)$openShift['id'], $storeId, $kasirId]);

      unset($_SESSION['active_shift_id']);
      $ok = 'Shift berhasil ditutup.';
      $openShift = null;
    }
  }
}

include __DIR__ . '/partials/kasir_layout_top.php';
?>

<div style="display:grid;grid-template-columns:1fr;gap:14px;">
  <?php if ($err): ?>
    <div style="padding:10px 12px;border-radius:14px;border:1px solid rgba(239,68,68,.25);background:rgba(239,68,68,.10);color:#991b1b;font-weight:900;font-size:12px;">
      <?= htmlspecialchars($err) ?>
    </div>
  <?php endif; ?>

  <?php if ($ok): ?>
    <div style="padding:10px 12px;border-radius:14px;border:1px solid rgba(22,163,74,.25);background:rgba(22,163,74,.10);color:#166534;font-weight:900;font-size:12px;">
      <?= htmlspecialchars($ok) ?>
    </div>
  <?php endif; ?>

  <div style="background:#fff;border:1px solid rgba(15,23,42,.10);border-radius:16px;padding:14px;box-shadow:0 18px 50px rgba(15,23,42,.05);">
    <h2 style="margin:0 0 10px 0;font-size:16px;font-weight:1000;">Status Shift</h2>

    <?php if ($openShift): ?>
      <div style="font-size:13px;color:#334155;line-height:1.7;">
        <b>OPEN</b> · ID #<?= (int)$openShift['id'] ?><br>
        Dibuka: <?= htmlspecialchars((string)$openShift['opened_at']) ?><br>
        Opening cash: Rp <?= number_format((int)$openShift['opening_cash'], 0, ',', '.') ?><br>
        Note: <?= htmlspecialchars((string)($openShift['note'] ?? '')) ?>
      </div>

      <form method="post" style="margin-top:12px;">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="close">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;max-width:560px;">
          <div>
            <label style="font-size:12px;color:#64748b;font-weight:900;">Closing cash</label>
            <input type="number" name="closing_cash" value="0" min="0"
              style="width:100%;padding:10px 12px;border-radius:12px;border:1px solid rgba(15,23,42,.12);">
          </div>
          <div>
            <label style="font-size:12px;color:#64748b;font-weight:900;">Catatan</label>
            <input type="text" name="note" value=""
              style="width:100%;padding:10px 12px;border-radius:12px;border:1px solid rgba(15,23,42,.12);">
          </div>
        </div>
        <button type="submit"
          style="margin-top:12px;padding:10px 14px;border-radius:999px;background:#fee2e2;border:1px solid #fecaca;color:#b91c1c;font-weight:1000;cursor:pointer;">
          Tutup Shift
        </button>
      </form>

    <?php else: ?>
      <div style="font-size:13px;color:#334155;line-height:1.7;">
        Shift belum dibuka.
      </div>

      <form method="post" style="margin-top:12px;">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="open">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;max-width:560px;">
          <div>
            <label style="font-size:12px;color:#64748b;font-weight:900;">Opening cash</label>
            <input type="number" name="opening_cash" value="0" min="0"
              style="width:100%;padding:10px 12px;border-radius:12px;border:1px solid rgba(15,23,42,.12);">
          </div>
          <div>
            <label style="font-size:12px;color:#64748b;font-weight:900;">Catatan</label>
            <input type="text" name="note" value=""
              style="width:100%;padding:10px 12px;border-radius:12px;border:1px solid rgba(15,23,42,.12);">
          </div>
        </div>
        <button type="submit"
          style="margin-top:12px;padding:10px 14px;border-radius:999px;background:#2563eb;border:1px solid #2563eb;color:#fff;font-weight:1000;cursor:pointer;">
          Buka Shift
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/partials/kasir_layout_bottom.php'; ?>

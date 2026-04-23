<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';

require_role(['dapur']);

$dapurId = (int)auth_user()['id'];

// Ambil store dari admin pembuat akun dapur (ngikut pola kasir)
$st = $pdo->prepare("
  SELECT s.id, s.name, s.address, s.phone
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

// ===== UI vars untuk layout dapur =====
$appName    = 'MultiPOS';
$pageTitle  = 'Antrian Pesanan';
$activeMenu = 'antrian';
$userName   = (string)auth_user()['name'];

// ===== Filter =====
$sinceMin = (int)($_GET['since'] ?? 180);
if ($sinceMin < 5) $sinceMin = 5;
if ($sinceMin > 1440) $sinceMin = 1440;

$status = (string)($_GET['status'] ?? 'belum'); // belum | selesai
if (!in_array($status, ['belum','selesai'], true)) $status = 'belum';
$done = ($status === 'selesai') ? 1 : 0;

// ====== UPDATE STATUS (SELESAI / BATAL) ======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $act = (string)($_POST['action'] ?? '');
  $saleId = (int)($_POST['sale_id'] ?? 0);

  // pastikan sale ini milik store yang sama
  $chk = $pdo->prepare("SELECT id FROM sales WHERE id=? AND store_id=? LIMIT 1");
  $chk->execute([$saleId, $storeId]);
  $valid = $chk->fetch();

  if ($valid) {
    if ($act === 'done') {
      $pdo->prepare("UPDATE sales SET kitchen_done=1, kitchen_done_at=NOW() WHERE id=? AND store_id=?")
          ->execute([$saleId, $storeId]);
    } elseif ($act === 'undone') {
      $pdo->prepare("UPDATE sales SET kitchen_done=0, kitchen_done_at=NULL WHERE id=? AND store_id=?")
          ->execute([$saleId, $storeId]);
    }
  }

  // balik ke halaman yang sama + querystring yang sama
  $qs = $_GET ? ('?' . http_build_query($_GET)) : '';
  header('Location: dapur_dashboard.php' . $qs);
  exit;
}

// ===== Ambil daftar order (sales) =====
$salesQ = $pdo->prepare("
  SELECT s.id, s.invoice_no, s.total, s.paid, s.change_amount, s.payment_method, s.created_at,
         s.kitchen_done, s.kitchen_done_at,
         s.order_note,
         u.name AS kasir_name
  FROM sales s
  JOIN users u ON u.id = s.kasir_id
  WHERE s.store_id = ?
    AND s.created_at >= (NOW() - INTERVAL ? MINUTE)
    AND s.kitchen_done = ?
  ORDER BY s.id DESC
  LIMIT 50
");
$salesQ->execute([$storeId, $sinceMin, $done]);
$sales = $salesQ->fetchAll();

// ===== Ambil item untuk semua sale yg tampil =====
$saleItemsBySaleId = [];
if ($sales) {
  $ids = array_map(fn($r) => (int)$r['id'], $sales);
  $in  = implode(',', array_fill(0, count($ids), '?'));

  $itQ = $pdo->prepare("
    SELECT sale_id, sku, name, price, qty, subtotal
    FROM sale_items
    WHERE sale_id IN ($in)
    ORDER BY sale_id DESC, id ASC
  ");
  $itQ->execute($ids);
  $items = $itQ->fetchAll();

  foreach ($items as $it) {
    $sid = (int)$it['sale_id'];
    if (!isset($saleItemsBySaleId[$sid])) $saleItemsBySaleId[$sid] = [];
    $saleItemsBySaleId[$sid][] = $it;
  }
}

// ===== Render layout =====
require __DIR__ . '/../publik/partials/dapur_layout_top.php';
require __DIR__ . '/../publik/partials/dapur_sidebar.php';
?>

<main class="content">

  <!-- auto refresh 15 detik -->
  <meta http-equiv="refresh" content="15">

  <div class="topbar">
    <div class="left">
      <!-- tombol menu untuk mobile (drawer) -->
      <button class="icon-btn" type="button"
        onclick="window.__dapurSidebarToggle && window.__dapurSidebarToggle()"
        aria-label="Menu">☰</button>

      <div>
        <p class="h" style="margin:0;">Antrian Pesanan</p>
        <p class="p">
          Auto refresh tiap <b>15 detik</b> • Maks 50 order
          • Status: <b><?= $status==='selesai' ? 'Selesai' : 'Belum' ?></b>
        </p>
      </div>
    </div>
    <div class="right">

    </div>
  </div>

  <div class="card" style="margin-bottom:14px;">
    <form method="get" class="row" style="margin:0;">
      <div class="row">
        <label class="small muted">Order terakhir</label>
        <input type="number" name="since" min="5" max="1440" value="<?= (int)$sinceMin ?>" style="width:110px;">
        <span class="small muted">menit</span>
      </div>

      <select name="status">
        <option value="belum" <?= $status==='belum'?'selected':'' ?>>Belum</option>
        <option value="selesai" <?= $status==='selesai'?'selected':'' ?>>Selesai</option>
      </select>

      <button class="btn" type="submit">Terapkan</button>
      <a class="btn" href="dapur_dashboard.php">Reset</a>

      <span class="spacer"></span>
      <span class="small muted">Terakhir refresh: <?= date('Y-m-d H:i:s') ?></span>
    </form>
  </div>

  <?php if (!$sales): ?>
    <div class="card">
      <b>Tidak ada pesanan</b> untuk filter ini.
    </div>
  <?php else: ?>

    <?php foreach ($sales as $s):
      $sid = (int)$s['id'];
      $inv = $s['invoice_no'] ?: ('#'.$sid);
      $created = (string)$s['created_at'];
      $kasirName = (string)$s['kasir_name'];
      $pm = strtoupper((string)$s['payment_method']);
      $total = (int)$s['total'];
      $isDone = ((int)$s['kitchen_done'] === 1);
      $doneAt = (string)($s['kitchen_done_at'] ?? '');
      $note = (string)($s['order_note'] ?? '');
      $itList = $saleItemsBySaleId[$sid] ?? [];
    ?>
      <div class="card" style="margin-bottom:12px;">
        <div class="row" style="align-items:flex-start;">
          <div>
            <div style="font-size:18px;font-weight:800;">
              Order <?= htmlspecialchars($inv) ?>
              <?php if ($isDone): ?>
                <span class="badge">Selesai<?= $doneAt ? ' • '.$doneAt : '' ?></span>
              <?php else: ?>
                <span class="badge">Belum</span>
              <?php endif; ?>
            </div>
            <div class="small muted">Waktu: <?= htmlspecialchars($created) ?> • Kasir: <?= htmlspecialchars($kasirName) ?></div>

            <?php if ($note !== ''): ?>
              <div style="margin-top:8px;">
                <span class="badge">Catatan</span>
                <span><?= htmlspecialchars($note) ?></span>
              </div>
            <?php endif; ?>
          </div>

          <span class="spacer"></span>

          <div style="text-align:right;">
            <div><b>Total:</b> Rp <?= number_format($total,0,',','.') ?></div>
            <div class="small muted">Metode: <?= htmlspecialchars($pm) ?></div>

            <div style="margin-top:8px;">
              <form method="post" style="display:inline;">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="sale_id" value="<?= (int)$sid ?>">

                <?php if ($isDone): ?>
                  <input type="hidden" name="action" value="undone">
                  <button class="btn" type="submit">Batalkan</button>
                <?php else: ?>
                  <input type="hidden" name="action" value="done">
                  <button class="btn" type="submit">Selesai</button>
                <?php endif; ?>
              </form>
            </div>
          </div>
        </div>

        <div style="margin-top:10px;"></div>

        <?php if (!$itList): ?>
          <div class="muted"><i>Item kosong.</i></div>
        <?php else: ?>
          <table width="100%">
            <tr>
              <th style="width:64px;">Qty</th>
              <th>Menu</th>
              <th style="width:160px;text-align:right;">Subtotal</th>
            </tr>
            <?php foreach ($itList as $it): ?>
              <tr>
                <td><b><?= (int)$it['qty'] ?>x</b></td>
                <td>
                  <?= htmlspecialchars((string)$it['name']) ?>
                  <?php if (!empty($it['sku'])): ?>
                    <span class="small muted">(SKU: <?= htmlspecialchars((string)$it['sku']) ?>)</span>
                  <?php endif; ?>
                </td>
                <td style="text-align:right;">Rp <?= number_format((int)$it['subtotal'],0,',','.') ?></td>
              </tr>
            <?php endforeach; ?>
          </table>
        <?php endif; ?>

      </div>
    <?php endforeach; ?>

  <?php endif; ?>

</main>

<?php require __DIR__ . '/../publik/partials/dapur_layout_bottom.php'; ?>

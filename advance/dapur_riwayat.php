<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';

require_role(['dapur']);

$dapurId = (int)auth_user()['id'];

// Ambil store dari admin pembuat akun dapur
$st = $pdo->prepare("
  SELECT s.id, s.name
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
$pageTitle  = 'Riwayat Dapur';
$activeMenu = 'riwayat';
$userName   = (string)auth_user()['name'];

// ===== Filter (opsional) =====
// kosong = tidak membatasi tanggal
$from = trim((string)($_GET['from'] ?? '')); // format Y-m-d
$to   = trim((string)($_GET['to'] ?? ''));   // format Y-m-d

// validasi format date (kalau diisi)
if ($from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = '';
if ($to   !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to = '';

// keyword cari invoice/id (opsional)
$q = trim((string)($_GET['q'] ?? ''));
if (strlen($q) > 40) $q = substr($q, 0, 40);

// limit untuk performa
$limit = (int)($_GET['limit'] ?? 100);
if ($limit < 10) $limit = 10;
if ($limit > 500) $limit = 500;

// ===== Query riwayat selesai (default: semua) =====
$sql = "
  SELECT s.id, s.invoice_no, s.total, s.payment_method, s.created_at,
         s.kitchen_done_at, s.order_note,
         u.name AS kasir_name
  FROM sales s
  JOIN users u ON u.id = s.kasir_id
  WHERE s.store_id = ?
    AND s.kitchen_done = 1
";

$params = [$storeId];

// filter tanggal hanya kalau user isi
if ($from !== '') {
  $sql .= " AND s.kitchen_done_at >= ? ";
  $params[] = $from . ' 00:00:00';
}
if ($to !== '') {
  $sql .= " AND s.kitchen_done_at <= ? ";
  $params[] = $to . ' 23:59:59';
}

// filter search (invoice/id)
if ($q !== '') {
  $sql .= " AND (s.invoice_no LIKE ? OR CAST(s.id AS CHAR) LIKE ?) ";
  $like = '%' . $q . '%';
  $params[] = $like;
  $params[] = $like;
}

$sql .= " ORDER BY s.kitchen_done_at DESC, s.id DESC LIMIT " . $limit;

$listQ = $pdo->prepare($sql);
$listQ->execute($params);
$sales = $listQ->fetchAll();

// Ambil item untuk semua sale yg tampil
$saleItemsBySaleId = [];
if ($sales) {
  $ids = array_map(fn($r) => (int)$r['id'], $sales);
  $in  = implode(',', array_fill(0, count($ids), '?'));

  $itQ = $pdo->prepare("
    SELECT sale_id, name, qty
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

require __DIR__ . '/../publik/partials/dapur_layout_top.php';
require __DIR__ . '/../publik/partials/dapur_sidebar.php';
?>

<main class="content">

  <div class="topbar">
    <div class="left">
      <!-- tombol menu untuk mobile (drawer) -->
      <button class="icon-btn" type="button"
        onclick="window.__dapurSidebarToggle && window.__dapurSidebarToggle()"
        aria-label="Menu">☰</button>

      <div>
        <p class="h" style="margin:0;">Riwayat Dapur</p>
        <p class="p">Menampilkan <b>semua</b> order yang sudah selesai.</p>
      </div>
    </div>
    <div class="right">

    </div>
  </div>

  <div class="card" style="margin-bottom:14px;">
    <form method="get" class="row" style="margin:0;">
      <div class="row">
        <span class="small muted">Dari (opsional)</span>
        <input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
      </div>
      <div class="row">
        <span class="small muted">Sampai (opsional)</span>
        <input type="date" name="to" value="<?= htmlspecialchars($to) ?>">
      </div>

      <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Cari INV / ID..." style="width:200px;">

      <select name="limit">
        <?php foreach ([50,100,200,500] as $opt): ?>
          <option value="<?= $opt ?>" <?= $limit===$opt?'selected':'' ?>><?= $opt ?> data</option>
        <?php endforeach; ?>
      </select>

      <button class="btn" type="submit">Filter</button>
      <a class="btn" href="dapur_riwayat.php">Reset</a>

      <span class="spacer"></span>
      <a class="btn" href="dapur_dashboard.php?status=belum">Ke Antrian Belum</a>
      <a class="btn" href="dapur_dashboard.php?status=selesai">Ke Antrian Selesai</a>
    </form>
  </div>

  <?php if (!$sales): ?>
    <div class="card">
      <b>Tidak ada data</b> untuk filter ini.
    </div>
  <?php else: ?>

    <?php foreach ($sales as $s):
      $sid = (int)$s['id'];
      $inv = $s['invoice_no'] ?: ('#'.$sid);
      $doneAt = (string)($s['kitchen_done_at'] ?? '');
      $kasirName = (string)($s['kasir_name'] ?? '');
      $total = (int)$s['total'];
      $pm = strtoupper((string)$s['payment_method']);
      $note = trim((string)($s['order_note'] ?? ''));
      $itList = $saleItemsBySaleId[$sid] ?? [];
    ?>
      <div class="card" style="margin-bottom:12px;">
        <div class="row" style="align-items:flex-start;">
          <div>
            <div style="font-size:18px;font-weight:800;">
              <?= htmlspecialchars($inv) ?>
              <span class="badge">Selesai<?= $doneAt ? ' • '.$doneAt : '' ?></span>
            </div>
            <div class="small muted">
              Kasir: <?= htmlspecialchars($kasirName) ?> • Metode: <?= htmlspecialchars($pm) ?> • Total: Rp <?= number_format($total,0,',','.') ?>
            </div>

            <?php if ($note !== ''): ?>
              <div style="margin-top:8px;">
                <span class="badge">Catatan</span>
                <span><?= htmlspecialchars($note) ?></span>
              </div>
            <?php endif; ?>
          </div>

          <span class="spacer"></span>
          <a class="btn" href="kasir_receipt.php?id=<?= $sid ?>" target="_blank">Lihat Struk</a>
        </div>

        <div style="margin-top:10px;"></div>

        <?php if (!$itList): ?>
          <div class="muted"><i>Item kosong.</i></div>
        <?php else: ?>
          <table width="100%">
            <tr>
              <th style="width:64px;">Qty</th>
              <th>Menu</th>
            </tr>
            <?php foreach ($itList as $it): ?>
              <tr>
                <td><b><?= (int)$it['qty'] ?>x</b></td>
                <td><?= htmlspecialchars((string)$it['name']) ?></td>
              </tr>
            <?php endforeach; ?>
          </table>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

  <?php endif; ?>

</main>

<?php require __DIR__ . '/../publik/partials/dapur_layout_bottom.php'; ?>

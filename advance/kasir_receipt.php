<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';

require_role(['kasir']);

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function rupiah(int $n): string { return 'Rp ' . number_format($n, 0, ',', '.'); }

$kasirId = (int)auth_user()['id'];
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); exit('ID invalid'); }

$printParam = (int)($_GET['print'] ?? 0);

// ambil sale + store settings (pastikan kasir yg sama)
$saleQ = $pdo->prepare("
  SELECT
    sa.*,
    s.name AS store_name,
    s.address,
    s.phone,

    -- receipt settings
    s.receipt_header,
    s.receipt_footer,
    s.receipt_show_logo,
    s.receipt_auto_print,
    s.receipt_paper,
    s.receipt_logo_path,

    u.name AS kasir_name
  FROM sales sa
  JOIN stores s ON s.id=sa.store_id
  JOIN users u ON u.id=sa.kasir_id
  WHERE sa.id=? AND sa.kasir_id=?
  LIMIT 1
");
$saleQ->execute([$id, $kasirId]);
$sale = $saleQ->fetch(PDO::FETCH_ASSOC);
if (!$sale) { http_response_code(404); exit('Transaksi tidak ditemukan.'); }

$itemQ = $pdo->prepare("SELECT sku,name,price,qty,subtotal,discount_percent FROM sale_items WHERE sale_id=? ORDER BY id");
$itemQ->execute([$id]);
$items = $itemQ->fetchAll(PDO::FETCH_ASSOC);

// Paper width
$paper = (string)($sale['receipt_paper'] ?? '80mm');
if (!in_array($paper, ['58mm','80mm'], true)) $paper = '80mm';
$paperPx = ($paper === '58mm') ? 280 : 360;

// Logo & header/footer
$showLogo = (int)($sale['receipt_show_logo'] ?? 1);
$logoPath = (string)($sale['receipt_logo_path'] ?? '');
$headerText = (string)($sale['receipt_header'] ?? '');
$footerText = (string)($sale['receipt_footer'] ?? '');

// Auto print
$autoPrint = (int)($sale['receipt_auto_print'] ?? 1);
$shouldAutoPrint = ($printParam === 1) || ($autoPrint === 1);

// fallback invoice_no
$invoiceNo = (string)($sale['invoice_no'] ?? '');
if ($invoiceNo === '') $invoiceNo = 'TRX-' . (int)$sale['id'];
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Struk <?= h($invoiceNo) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <style>
    :root{
      --w: <?= (int)$paperPx ?>px;
    }
    body{
      margin:0;
      padding:16px;
      background:#f6f7fb;
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono","Courier New", monospace;
      color:#0f172a;
    }
    .wrap{
      width: var(--w);
      max-width: 100%;
      margin: 0 auto;
      background:#fff;
      border:1px solid rgba(15,23,42,.15);
      border-radius:16px;
      padding:14px;
      box-shadow: 0 18px 50px rgba(15,23,42,.08);
    }
    .center{ text-align:center; }
    .muted{ color: rgba(15,23,42,.7); font-size:11px; }
    .title{ font-weight: 1000; font-size: 14px; margin: 0; }
    .logo{ width: 64px; height: 64px; object-fit: contain; display:block; margin:0 auto 8px; }
    .hr{ border:0; border-top: 1px dashed rgba(15,23,42,.25); margin: 10px 0; }
    .row{ display:flex; justify-content:space-between; gap:10px; }
    .items{ margin-top: 6px; }
    .item{ display:flex; justify-content:space-between; gap:10px; padding:6px 0; border-bottom:1px dashed rgba(15,23,42,.12); }
    .item:last-child{ border-bottom:0; }
    .btns{ display:flex; gap:10px; flex-wrap:wrap; margin-top: 12px; }
    .btn{
      appearance:none;
      border:1px solid rgba(15,23,42,.2);
      background:#fff;
      padding:10px 12px;
      border-radius:12px;
      cursor:pointer;
      text-decoration:none;
      color:#0f172a;
      font-weight:900;
      font-size:12px;
    }
    .btn.primary{ background: rgba(37,99,235,.12); border-color: rgba(37,99,235,.35); color:#1d4ed8; }

    pre{
      margin:0;
      white-space: pre-line;
      font-family: inherit;
      font-size: 11px;
      color: rgba(15,23,42,.78);
    }

    /* Print */
    @media print{
      body{ background:#fff; padding:0; }
      .wrap{ box-shadow:none; border:none; border-radius:0; width: var(--w); }
      .btns{ display:none; }
    }
  </style>
</head>
<body>

<div class="wrap" id="receipt">

  <?php if($showLogo && $logoPath): ?>
    <img class="logo" src="<?= h($logoPath) ?>" alt="logo">
  <?php endif; ?>

  <div class="center">
    <h3 class="title"><?= h($sale['store_name']) ?></h3>
    <?php if (!empty($sale['address'])): ?><div class="muted"><?= h($sale['address']) ?></div><?php endif; ?>
    <?php if (!empty($sale['phone'])): ?><div class="muted"><?= h($sale['phone']) ?></div><?php endif; ?>
  </div>

  <?php if (trim($headerText) !== ''): ?>
    <hr class="hr">
    <div class="center"><pre><?= h($headerText) ?></pre></div>
  <?php endif; ?>

  <hr class="hr">

  <div class="muted">
    <div>Invoice: <b><?= h($invoiceNo) ?></b></div>
    <div>Tanggal: <?= h($sale['created_at']) ?></div>
    <div>Kasir: <?= h($sale['kasir_name']) ?></div>
  </div>

  <hr class="hr">

  <div class="items">
    <?php foreach ($items as $it): ?>
      <?php
        $qty = (int)($it['qty'] ?? 0);
        $price = (int)($it['price'] ?? 0);
        $sub = (int)($it['subtotal'] ?? ($qty * $price));
        $disc = (int)($it['discount_percent'] ?? 0);
      ?>
      <div class="item">
        <div style="flex:1;">
          <div style="font-weight:900;"><?= h($it['name'] ?? '') ?></div>
          <div class="muted">
            SKU <?= h($it['sku'] ?? '-') ?> | <?= $qty ?> x <?= rupiah($price) ?>
            <?php if($disc > 0): ?> | Diskon <?= $disc ?>%<?php endif; ?>
          </div>
        </div>
        <div style="font-weight:900; white-space:nowrap;"><?= rupiah($sub) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <hr class="hr">

  <div class="row" style="font-weight:1000;">
    <div>Total</div>
    <div><?= rupiah((int)$sale['total']) ?></div>
  </div>
  <div class="row muted">
    <div>Bayar</div>
    <div><?= rupiah((int)$sale['paid']) ?></div>
  </div>
  <div class="row muted">
    <div>Kembali</div>
    <div><?= rupiah((int)$sale['change_amount']) ?></div>
  </div>
  <div class="muted" style="margin-top:6px;">Metode: <?= h($sale['payment_method'] ?? '-') ?></div>

  <?php if (trim($footerText) !== ''): ?>
    <hr class="hr">
    <div class="center"><pre><?= h($footerText) ?></pre></div>
  <?php endif; ?>

  <hr class="hr">

  <div class="btns">
    <button class="btn primary" onclick="window.print()">🖨️ Print</button>
    <a class="btn" href="kasir_pos.php">🧾 Transaksi Baru</a>
  </div>

</div>

<script>
  (function(){
    const shouldAutoPrint = <?= $shouldAutoPrint ? 'true' : 'false' ?>;
    if (shouldAutoPrint) {
      // Delay kecil agar layout/logo sempat render
      setTimeout(() => { window.print(); }, 350);
    }
  })();
</script>

</body>
</html>

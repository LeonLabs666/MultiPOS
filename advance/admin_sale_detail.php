<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';

require_role(['admin']);

$adminId=(int)auth_user()['id'];

$st=$pdo->prepare("SELECT id,name FROM stores WHERE owner_admin_id=? AND is_active=1 LIMIT 1");
$st->execute([$adminId]);
$store=$st->fetch();
if(!$store){ http_response_code(400); exit('Admin belum punya toko.'); }
$storeId=(int)$store['id']; $storeName=$store['name'];

$id=(int)($_GET['id'] ?? 0);
if($id<=0){ http_response_code(400); exit('ID invalid'); }

$saleQ=$pdo->prepare("
  SELECT sa.*, u.name AS kasir_name
  FROM sales sa
  JOIN users u ON u.id=sa.kasir_id
  WHERE sa.id=? AND sa.store_id=?
  LIMIT 1
");
$saleQ->execute([$id,$storeId]);
$sale=$saleQ->fetch();
if(!$sale){ http_response_code(404); exit('Transaksi tidak ditemukan.'); }

$itemQ=$pdo->prepare("
  SELECT sku,name,price,discount_percent,discount_amount,qty,subtotal
  FROM sale_items
  WHERE sale_id=?
  ORDER BY id
");
$itemQ->execute([$id]);
$items=$itemQ->fetchAll();

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function rupiah(int $n): string { return 'Rp ' . number_format($n, 0, ',', '.'); }

/**
 * helper struk: format fixed width (monospace)
 */
function rpad(string $s, int $w): string {
  $s = trim($s);
  if (mb_strlen($s) > $w) return mb_substr($s, 0, $w);
  return $s . str_repeat(' ', $w - mb_strlen($s));
}
function lpad(string $s, int $w): string {
  $s = trim($s);
  if (mb_strlen($s) > $w) return mb_substr($s, 0, $w);
  return str_repeat(' ', $w - mb_strlen($s)) . $s;
}
function money_short(int $n): string {
  // tanpa "Rp" biar muat di struk
  return number_format($n, 0, ',', '.');
}

$invoice = (string)($sale['invoice_no'] ?? '-');
$created = (string)($sale['created_at'] ?? '-');
$kasir   = (string)($sale['kasir_name'] ?? '-');
$method  = strtoupper((string)($sale['payment_method'] ?? '-'));

$total   = (int)($sale['total'] ?? 0);
$paid    = (int)($sale['paid'] ?? 0);
$change  = (int)($sale['change_amount'] ?? 0);

// ===== Build preview struk (lebar 32 char) =====
$W = 32;
$lines = [];
$lines[] = str_repeat('=', $W);
$lines[] = rpad(mb_strtoupper($storeName), $W);
$lines[] = str_repeat('-', $W);
$lines[] = rpad("Invoice: " . $invoice, $W);
$lines[] = rpad("Waktu  : " . $created, $W);
$lines[] = rpad("Kasir  : " . $kasir, $W);
$lines[] = rpad("Metode : " . $method, $W);
$lines[] = str_repeat('-', $W);
$lines[] = rpad("ITEM", 20) . lpad("SUB", 12);
$lines[] = str_repeat('-', $W);

// item lines
foreach ($items as $it) {
  $name = (string)($it['name'] ?? '-');
  $sku  = (string)($it['sku'] ?? '-');
  $qty  = (int)($it['qty'] ?? 0);
  $sub  = (int)($it['subtotal'] ?? 0);
  $price= (int)($it['price'] ?? 0);
  $disc = (int)($it['discount_percent'] ?? 0);

  // baris nama (dipotong)
  $lines[] = rpad($name, $W);

  // baris detail qty x harga + diskon (kalau ada)
  $detail = "{$qty} x " . money_short($price);
  if ($disc > 0) $detail .= " (-{$disc}%)";
  $lines[] = rpad("  " . $detail, $W);

  // sku kecil
  $lines[] = rpad("  SKU: " . $sku, $W);

  // subtotal kanan
  $lines[] = rpad("", 20) . lpad(money_short($sub), 12);

  $lines[] = str_repeat('-', $W);
}

$lines[] = rpad("TOTAL", 20) . lpad(money_short($total), 12);
$lines[] = rpad("BAYAR", 20) . lpad(money_short($paid), 12);
$lines[] = rpad("KEMBALI", 20) . lpad(money_short($change), 12);
$lines[] = str_repeat('=', $W);
$lines[] = rpad("Terima kasih 🙏", $W);

$receiptText = implode("\n", $lines);

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Detail Transaksi</title>
  <style>
    :root{
      --border:#e2e8f0;
      --muted:#64748b;
      --text:#0f172a;
      --bg:#f8fafc;
      --card:#ffffff;
    }
    *{box-sizing:border-box}
    body{
      margin:0;
      font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      background: var(--bg);
      color: var(--text);
    }
    a{color:inherit}
    .wrap{max-width:980px;margin:0 auto;padding:16px;}
    .topbar{
      position: sticky; top:0; z-index: 10;
      background: rgba(248,250,252,.92);
      backdrop-filter: blur(8px);
      border-bottom:1px solid var(--border);
    }
    .topbar-inner{max-width:980px;margin:0 auto;padding:10px 16px;display:flex;align-items:center;justify-content:space-between;gap:10px;}
    .back{
      display:inline-flex;align-items:center;gap:8px;
      padding:9px 12px;border-radius:12px;
      border:1px solid var(--border);
      background:#fff;text-decoration:none;
      font-weight:800;
    }
    .title{
      font-weight:900;font-size:18px;
      white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
    }

    .card{
      background:var(--card);
      border:1px solid var(--border);
      border-radius:16px;
      padding:14px;
    }
    .muted{color:var(--muted)}
    .h1{font-size:24px;font-weight:1000;margin:14px 0 6px}
    .sub{margin:0 0 14px;color:var(--muted);font-size:13px}

    .grid2{display:grid;grid-template-columns:1fr 340px;gap:12px;align-items:start}
    @media (max-width: 900px){ .grid2{grid-template-columns:1fr} }

    .meta{
      display:grid;grid-template-columns:120px 1fr;
      row-gap:8px;column-gap:10px;
      font-size:13px;
    }
    .meta b{font-weight:900}
    .pill{
      display:inline-flex;align-items:center;
      padding:4px 10px;border-radius:999px;
      border:1px solid var(--border);
      background:#fff;font-weight:900;font-size:12px;
      white-space:nowrap;
    }

    .section-title{font-weight:1000;margin:0 0 10px;font-size:16px}

    table{width:100%;border-collapse:collapse}
    th,td{border-bottom:1px solid #f1f5f9;padding:10px 8px;font-size:13px;vertical-align:top}
    th{color:var(--muted);font-size:11px;letter-spacing:.06em;text-transform:uppercase}
    td.right{text-align:right}
    td.center{text-align:center}

    .item-name{font-weight:900}
    .item-sku{font-size:12px;color:var(--muted);margin-top:2px}

    /* mobile items as cards */
    .m-items{display:none}
    @media (max-width: 700px){
      .desktop-items{display:none}
      .m-items{display:flex;flex-direction:column;gap:10px}
      .m-item{
        border:1px solid var(--border);border-radius:14px;padding:12px;background:#fff;
        display:flex;flex-direction:column;gap:8px;
      }
      .m-top{display:flex;justify-content:space-between;gap:10px;align-items:flex-start}
      .m-price{font-weight:1000;white-space:nowrap}
      .m-row{display:flex;justify-content:space-between;gap:10px;color:var(--muted);font-size:12px;flex-wrap:wrap}
      .m-row b{color:var(--text)}
    }

    .summary{display:flex;flex-direction:column;gap:12px}
    .sum-row{display:flex;justify-content:space-between;gap:12px;font-size:14px}
    .sum-row b{font-weight:1000}
    .sum-big{font-size:16px}
    .divider{height:1px;background:#f1f5f9;border:none;margin:2px 0}

    .btn{
      display:inline-flex;align-items:center;justify-content:center;
      padding:10px 12px;border-radius:12px;
      border:1px solid var(--border);
      background:#0b1220;color:#fff;
      font-weight:900;cursor:pointer;text-decoration:none;
    }
    .btn-ghost{
      display:inline-flex;align-items:center;justify-content:center;
      padding:10px 12px;border-radius:12px;
      border:1px solid var(--border);
      background:#fff;color:#0f172a;
      font-weight:900;cursor:pointer;text-decoration:none;
    }

    /* Receipt preview */
    .receipt{
      border:1px dashed #cbd5e1;
      border-radius:14px;
      padding:12px;
      background:#fff;
      overflow:auto;
    }
    .receipt pre{
      margin:0;
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
      font-size:12px;
      line-height:1.35;
      white-space:pre;
    }
    .receipt-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:10px}

    /* Print only receipt */
    @media print{
      body{background:#fff}
      .topbar, .wrap > .h1, .wrap > .sub, .grid2 > *:first-child, .print-hide { display:none !important; }
      .grid2{display:block}
      .card{border:none;padding:0}
      .receipt{border:none;padding:0}
      .receipt pre{font-size:12px}
    }
  </style>
</head>
<body>

<div class="topbar print-hide">
  <div class="topbar-inner">
    <a class="back" href="admin_sales_report.php">← Kembali</a>
    <div class="title">Detail Transaksi</div>
    <div style="width:44px;"></div>
  </div>
</div>

<div class="wrap">
  <div class="h1 print-hide">Detail Transaksi</div>
  <p class="sub print-hide"><?= h($storeName) ?></p>

  <div class="grid2">
    <!-- LEFT: Items -->
    <div class="card print-hide">
      <div class="section-title">Item</div>

      <!-- Desktop table -->
      <div class="desktop-items" style="overflow:auto;">
        <table style="min-width:680px;">
          <thead>
            <tr>
              <th align="left">Produk</th>
              <th style="width:70px;">Qty</th>
              <th style="width:120px;" class="right">Harga</th>
              <th style="width:90px;" class="right">Diskon</th>
              <th style="width:140px;" class="right">Subtotal</th>
            </tr>
          </thead>
          <tbody>
            <?php if(!$items): ?>
              <tr><td colspan="5" class="muted" style="padding:12px;">Tidak ada item.</td></tr>
            <?php else: foreach($items as $it): ?>
              <?php
                $nm = (string)($it['name'] ?? '-');
                $sku = (string)($it['sku'] ?? '-');
                $qty = (int)($it['qty'] ?? 0);
                $price = (int)($it['price'] ?? 0);
                $sub = (int)($it['subtotal'] ?? 0);
                $discP = (int)($it['discount_percent'] ?? 0);
              ?>
              <tr>
                <td>
                  <div class="item-name"><?= h($nm) ?></div>
                  <div class="item-sku">SKU: <?= h($sku) ?></div>
                </td>
                <td class="center"><?= $qty ?></td>
                <td class="right"><?= h(rupiah($price)) ?></td>
                <td class="right"><?= $discP>0 ? h((string)$discP.'%') : '-' ?></td>
                <td class="right"><?= h(rupiah($sub)) ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Mobile cards -->
      <div class="m-items">
        <?php if(!$items): ?>
          <div class="muted">Tidak ada item.</div>
        <?php else: foreach($items as $it): ?>
          <?php
            $nm = (string)($it['name'] ?? '-');
            $sku = (string)($it['sku'] ?? '-');
            $qty = (int)($it['qty'] ?? 0);
            $price = (int)($it['price'] ?? 0);
            $sub = (int)($it['subtotal'] ?? 0);
            $discP = (int)($it['discount_percent'] ?? 0);
          ?>
          <div class="m-item">
            <div class="m-top">
              <div>
                <div class="item-name"><?= h($nm) ?></div>
                <div class="item-sku">SKU: <?= h($sku) ?></div>
              </div>
              <div class="m-price"><?= h(rupiah($sub)) ?></div>
            </div>
            <div class="m-row">
              <span>Qty: <b><?= $qty ?></b></span>
              <span>Harga: <b><?= h(rupiah($price)) ?></b></span>
              <span>Diskon: <b><?= $discP>0 ? h((string)$discP.'%') : '-' ?></b></span>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- RIGHT: Summary + Receipt -->
    <div class="summary">
      <div class="card print-hide">
        <div class="section-title">Ringkasan</div>
        <div class="meta">
          <div class="muted">Invoice</div>
          <div><b><?= h($invoice) ?></b></div>

          <div class="muted">Waktu</div>
          <div><?= h($created) ?></div>

          <div class="muted">Kasir</div>
          <div><?= h($kasir) ?></div>

          <div class="muted">Metode</div>
          <div><span class="pill"><?= h($method) ?></span></div>
        </div>
      </div>

      <div class="card print-hide">
        <div class="section-title">Pembayaran</div>
        <div class="sum-row sum-big"><b>Total</b><b><?= h(rupiah($total)) ?></b></div>
        <hr class="divider">
        <div class="sum-row"><span class="muted">Bayar</span><b><?= h(rupiah($paid)) ?></b></div>
        <div class="sum-row"><span class="muted">Kembali</span><b><?= h(rupiah($change)) ?></b></div>
      </div>

      <!-- Preview Struk -->
      <div class="card">
        <div class="section-title">Preview Struk</div>
        <div class="receipt" id="receiptBox">
          <pre id="receiptText"><?= h($receiptText) ?></pre>
        </div>

        <div class="receipt-actions print-hide">
          <button class="btn" type="button" onclick="window.print()">Print Struk</button>
          <button class="btn-ghost" type="button" onclick="copyReceipt()">Copy</button>
        </div>

        <div class="muted print-hide" style="font-size:12px;margin-top:8px;">
          Catatan: Print akan mencetak bagian struk saja (tanpa tabel & header).
        </div>
      </div>

    </div>
  </div>
</div>

<script class="print-hide">
  function copyReceipt(){
    const text = document.getElementById('receiptText').innerText;
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(() => {
        alert('Struk berhasil dicopy.');
      }).catch(() => alert('Gagal copy.'));
    } else {
      // fallback lama
      const ta = document.createElement('textarea');
      ta.value = text;
      document.body.appendChild(ta);
      ta.select();
      try { document.execCommand('copy'); alert('Struk berhasil dicopy.'); }
      catch(e){ alert('Gagal copy.'); }
      document.body.removeChild(ta);
    }
  }
</script>

</body>
</html>

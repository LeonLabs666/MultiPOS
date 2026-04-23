<?php
declare(strict_types=1);

require __DIR__ . '/kasir_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: kasir_pos.php'); exit;
}
csrf_verify();

if (empty($_SESSION['active_shift_id'])) {
  header('Location: kasir_shift.php'); exit;
}

$cartJson = (string)($_POST['cart'] ?? '[]');
$cart = json_decode($cartJson, true);
if (!is_array($cart) || count($cart) === 0) {
  header('Location: kasir_pos.php'); exit;
}

$total = 0;
$clean = [];
foreach ($cart as $it) {
  $id = (int)($it['id'] ?? 0);
  $qty = (int)($it['qty'] ?? 0);
  $price = (int)($it['price'] ?? 0);
  $name = (string)($it['name'] ?? '');
  if ($id <= 0 || $qty <= 0 || $price < 0 || $name === '') continue;
  $sub = $price * $qty;
  $total += $sub;
  $clean[] = ['id'=>$id,'name'=>$name,'price'=>$price,'qty'=>$qty,'subtotal'=>$sub];
}
if ($total <= 0 || count($clean) === 0) {
  header('Location: kasir_pos.php'); exit;
}

$pageTitle  = 'Checkout (Basic)';
$activeMenu = 'kasir_pos';

include __DIR__ . '/partials/kasir_layout_top.php';
?>

<style>
  .card{background:#fff;border:1px solid rgba(15,23,42,.10);border-radius:16px;padding:14px;box-shadow:0 18px 50px rgba(15,23,42,.05)}
  table{width:100%;border-collapse:collapse}
  th,td{padding:8px;border-bottom:1px solid rgba(15,23,42,.08);font-size:13px}
  th{text-align:left;color:#64748b;font-weight:900;font-size:12px}
  .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 12px;border-radius:999px;border:1px solid transparent;font-weight:1000;cursor:pointer}
  .btn.primary{background:#2563eb;border-color:#2563eb;color:#fff}
  .btn.ghost{background:#fff;border-color:rgba(15,23,42,.12);color:#0f172a}
  .inp{width:100%;padding:10px 12px;border:1px solid rgba(15,23,42,.12);border-radius:12px}
  .grid{display:grid;grid-template-columns:1fr .7fr;gap:14px}
  @media (max-width: 980px){ .grid{grid-template-columns:1fr} }
</style>

<div class="grid">
  <div class="card">
    <h2 style="margin:0 0 10px 0;font-size:16px;font-weight:1000;">Ringkasan</h2>
    <table>
      <thead>
        <tr><th>Item</th><th style="width:90px;">Harga</th><th style="width:70px;">Qty</th><th style="width:100px;">Sub</th></tr>
      </thead>
      <tbody>
        <?php foreach ($clean as $it): ?>
          <tr>
            <td><?= htmlspecialchars($it['name']) ?></td>
            <td>Rp <?= number_format((int)$it['price'],0,',','.') ?></td>
            <td><?= (int)$it['qty'] ?></td>
            <td>Rp <?= number_format((int)$it['subtotal'],0,',','.') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div style="margin-top:10px;font-weight:1000;">
      Total: Rp <?= number_format($total,0,',','.') ?>
    </div>

    <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
      <a class="btn ghost" href="kasir_pos.php">⬅️ Kembali</a>
    </div>
  </div>

  <div class="card">
    <h2 style="margin:0 0 10px 0;font-size:16px;font-weight:1000;">Pembayaran</h2>

    <form method="post" action="kasir_checkout_api.php" id="payForm">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="cart" value="<?= htmlspecialchars(json_encode($clean, JSON_UNESCAPED_UNICODE)) ?>">
      <input type="hidden" name="total" value="<?= (int)$total ?>">

      <label style="font-size:12px;color:#64748b;font-weight:900;">Uang diterima (cash)</label>
      <input class="inp" type="number" name="paid" id="paid" min="0" value="<?= (int)$total ?>">

      <div style="margin-top:10px;font-size:13px;">
        Kembalian: <b id="chg">Rp 0</b>
      </div>

      <button class="btn primary" type="submit" style="margin-top:12px;width:100%;">✅ Simpan & Cetak Struk</button>
    </form>
  </div>
</div>

<script>
  const paid = document.getElementById('paid');
  const chg = document.getElementById('chg');
  const total = <?= (int)$total ?>;

  function fmt(n){ return 'Rp ' + (n||0).toLocaleString('id-ID'); }
  function calc(){
    const p = Math.max(0, parseInt(paid.value||'0',10));
    const c = p - total;
    chg.textContent = fmt(Math.max(0,c));
  }
  paid.addEventListener('input', calc);
  calc();
</script>

<?php include __DIR__ . '/partials/kasir_layout_bottom.php'; ?>

<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';
require_role(['kasir','admin']);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$user = auth_user();
$kasirId   = (int)($user['id'] ?? 0);
$kasirName = (string)($user['name'] ?? 'Kasir');

/* ===== Store (pattern MultiPOS) ===== */
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

/* ===== Produk ===== */
$q = $pdo->prepare("
  SELECT p.id, p.name, p.price, p.image_path, c.name AS cat_name
  FROM products p
  LEFT JOIN categories c ON c.id = p.category_id
  WHERE p.store_id=? AND (p.is_active=1 OR p.is_active IS NULL)
  ORDER BY c.name, p.name
");
$q->execute([$storeId]);
$rows = $q->fetchAll();

$byCat = [];
foreach ($rows as $r) {
  $cat = $r['cat_name'] ?: 'Tanpa Kategori';
  $byCat[$cat][] = $r;
}

function img_src(?string $path): string {
  $p = trim((string)$path);
  if ($p === '') return '../publik/assets/no-image.png';
  if (preg_match('~^https?://~i', $p)) return $p;

  if (str_starts_with($p, '/')) return $p;

  // Backward-compat: old DB paths might start with 'assets/'
  if (str_starts_with($p, 'assets/'))  return '../publik/' . $p;

  // New folder name: upload/
  if (str_starts_with($p, 'upload/')) return '../' . $p;

  // If already saved as 'publik/assets/...' or any other relative path
  return '../' . ltrim($p, '/');
}

/* ===== Layout Kasir =====
   ✅ Header: tampilkan nama toko saja
   ✅ Hilangkan pill "MultiPOS" (appName dikosongkan)
*/
$appName    = '';
$pageTitle  = $storeName;
$activeMenu = 'kasir_pos';
require __DIR__ . '/../publik/partials/kasir_layout_top.php';
?>

<link rel="stylesheet" href="../publik/assets/css/kasir_pos.css?v=6">

<!-- ✅ Override: katalog dipadatkan + mobile scroll terpisah (cartBox & payment) + numpad style lama -->
<style>
/* ===== KATALOG PRODUK: diperkecil sedikit biar muat lebih banyak ===== */
.grid{
  grid-template-columns: repeat(auto-fill, minmax(135px, 1fr));
  gap: 10px;
}
.card{
  padding:10px 8px;
  border-radius:14px;
  gap:7px;
}
.card img{ width:56px; height:56px; }
.card .nm{ font-size:12.5px; min-height:30px; }
.card .pr{ font-size:12px; }
@media (max-width: 1024px){
  .grid{ gap:8px; }
}

/* ===== Samakan numpad & tombol bawah seperti StationPOS lama ===== */

/* input */
.pay-cash-box .input-line input,
.input-line input, .input-line select{
  padding:8px 10px;
  border:1px solid #d1d5db;
  border-radius:10px;
  font-size:13px;
  outline:none;
}

/* numpad style lama */
.pay-cash-box .numpad{
  display:grid;
  grid-template-columns:repeat(3,1fr);
  gap:10px;
  margin-top:10px;
}

.pay-cash-box .numpad button{
  padding:16px 0;
  background:#f1f5f9;
  border:none;
  border-radius:14px;
  font-size:20px;
  font-weight:700;
  cursor:pointer;
  box-shadow:0 2px 5px rgba(15,23,42,.08);
  transition:background .12s ease, transform .06s ease, box-shadow .12s ease;
  width:100%;
}

.pay-cash-box .numpad button:hover{
  background:#e2e8f0;
  box-shadow:0 4px 10px rgba(15,23,42,.16);
}

.pay-cash-box .numpad button:active{
  transform:scale(.97);
  background:#cbd5e1;
}

.pay-cash-box .numpad button.danger{
  background:#fee2e2;
  color:#991b1b;
}
.pay-cash-box .numpad button.danger:hover{ background:#fecaca; }

/* tombol bawah (pill) */
.btn-row{
  display:flex;
  gap:8px;
  margin-top:10px;
}

.btn-row .btn-empty,
.btn-row .btn-pay{
  flex:1;
  border-radius:999px;
  padding:12px 12px;
  font-size:14px;
  cursor:pointer;
  border:1px solid transparent;
  height:auto;
}

.btn-row .btn-empty{
  background:#fee2e2;
  border-color:#fecaca;
  color:#b91c1c;
  font-weight:700;
}

.btn-row .btn-pay{
  background:#2563eb;
  border-color:#2563eb;
  color:#fff;
  font-weight:800;
}

.btn-row .btn-pay:disabled{
  opacity:.55;
  cursor:not-allowed;
}

/* ===== MOBILE: scroll TERPISAH
   - #cartBox = kotak & scroll sendiri
   - #payScroll = scroll sendiri
*/
@media (max-width: 1024px){

  /* bottom sheet jadi flex kolom */
  .cart.open{
    display:flex;
    flex-direction:column;
    max-height:85vh;
    overflow:hidden; /* ✅ penting: scroll dipisah ke cartBox & payScroll */
    padding-bottom: calc(14px + env(safe-area-inset-bottom));
  }

  /* header/customer jangan ikut scroll */
  .cart-head,
  .cust-bar,
  #custPanel{
    flex:0 0 auto;
  }

  /* ✅ LIST PRODUK DIPILIH: kotak & scroll sendiri */
  #cartBox{
    flex:0 0 auto;              /* ✅ jangan flex:1 biar gak ketarik */
    overflow:auto !important;
    max-height: 220px;
    border-radius:12px;
    -webkit-overflow-scrolling:touch;
    background:#f9fafb;
  }

  @media (max-height: 740px){
    #cartBox{ max-height: 170px; }
  }
  @media (max-height: 680px){
    #cartBox{ max-height: 140px; }
  }

  /* ✅ PAYMENT AREA: scroll sendiri */
  #payScroll{
    flex: 1 1 auto;
    overflow:auto;
    min-height:0;
    -webkit-overflow-scrolling:touch;
    padding-bottom: calc(10px + env(safe-area-inset-bottom));
  }

  /* ✅ matikan sticky sum di mobile (biar tidak nindih cart) */
  .sum{
    position: static !important;
    border-top:1px solid rgba(15,23,42,.12);
    margin-top:10px;
  }

  /* tombol bawah sticky dalam payScroll */
  .btn-row{
    position: sticky;
    bottom: 0;
    background: #fff;
    padding: 10px 0 calc(10px + env(safe-area-inset-bottom));
    z-index: 10;
    border-top: 1px solid rgba(15,23,42,.08);
  }

  /* numpad tetap 3 kolom */
  #payCashBox .numpad{
    grid-template-columns:repeat(3, 1fr) !important;
    gap:10px;
  }

  /* label lebih kecil */
  .input-line label{
    font-size:12px;
    line-height:1.2;
  }

  .input-line select{
    height:40px;
  }
}

/* layar kecil: numpad sedikit turun size */
@media (max-width: 480px){
  #payCashBox .numpad button{
    padding:14px 0;
    font-size:18px;
    border-radius:12px;
  }
}
</style>

<div class="pos-wrap">
  <div class="page-pos">

    <!-- LEFT -->
    <div class="pos-left">
      <div class="search-row">
        <div class="ico">🔎</div>
        <input id="search" placeholder="Cari produk..." oninput="filterMenu()">
      </div>

      <?php foreach ($byCat as $cat => $items): ?>
        <div class="cat-block">
          <div class="cat-title"><?= htmlspecialchars((string)$cat) ?></div>
          <div class="grid">
            <?php foreach ($items as $it): ?>
              <?php $src = img_src($it['image_path'] ?? ''); ?>
              <div class="card"
                   data-id="<?= (int)$it['id'] ?>"
                   data-name="<?= htmlspecialchars((string)$it['name']) ?>"
                   data-search="<?= htmlspecialchars(mb_strtolower((string)$it['name'])) ?>"
                   data-price="<?= (int)$it['price'] ?>"
                   onclick="addToCart(this)">
                <img src="<?= htmlspecialchars($src) ?>" alt="" onerror="this.../publik/assets/no-image.png';">
                <div class="nm"><?= htmlspecialchars((string)$it['name']) ?></div>
                <div class="pr">Rp <?= number_format((int)$it['price'], 0, ',', '.') ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- CART -->
    <div class="cart" id="cart">
      <div class="sheet-handle"></div>

      <input type="hidden" id="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

      <div class="cart-head">
        <div class="ttl">Keranjang</div>
        <div class="badge" id="cartCount">0 item</div>
      </div>

      <!-- Customer -->
      <div class="cust-bar">
        <div class="left">
          <div class="ttl2">Pelanggan</div>
          <div class="val" id="custSummary">Umum</div>
        </div>
        <button type="button" id="btnCustToggle" onclick="toggleCustomer()">Cari</button>
      </div>

      <div class="cust-panel" id="custPanel">
        <input type="hidden" id="customer-id" value="">
        <div class="input-line autocomplete-wrap" style="margin-top:10px;">
          <label for="customer-name">Cari Nama / No. HP</label>
          <input type="text" id="customer-name" placeholder="Ketik min 2 huruf..." autocomplete="off">
          <div class="customer-suggest" id="custSuggest"></div>
        </div>

        <div class="input-line">
          <label for="customer-phone">No. HP (opsional)</label>
          <input type="text" id="customer-phone" placeholder="08xxxx">
        </div>

        <div class="input-line">
          <label for="order-type">Tipe Pesanan</label>
          <select id="order-type">
            <option value="">Tidak diisi</option>
            <option value="Makan di tempat">Makan di tempat</option>
            <option value="Bungkus">Bungkus</option>
          </select>
        </div>

        <div style="display:flex; gap:10px; margin-top:10px;">
          <button type="button" class="btn-empty" style="flex:1;" onclick="setCustomerUmum()">Set Umum</button>
          <button type="button" class="btn-pay" style="flex:1;" onclick="toggleCustomer(false)">Tutup</button>
        </div>
      </div>

      <!-- ✅ LIST ITEM: scroll sendiri -->
      <div class="cart-box" id="cartBox"></div>

      <!-- ✅ PAYMENT: scroll sendiri (mobile) -->
      <div id="payScroll">

        <div class="sum">
          <div>Total</div>
          <div id="totalLabel">Rp 0</div>
        </div>

        <div class="input-line">
          <label for="pay-method">Bayar</label>
          <select id="pay-method" onchange="onMethodChange()">
            <option value="CASH">CASH</option>
            <option value="QRIS">QRIS</option>
          </select>
        </div>

        <div class="pay-hint" id="payHint">
          Metode <b>QRIS</b> dipilih. Kasir cukup konfirmasi, lalu tekan Bayar.
        </div>

        <div class="pay-cash-box" id="payCashBox">
          <div class="input-line">
            <label for="pay-amount">Uang Masuk</label>
            <input type="number" id="pay-amount" value="0" oninput="recalcChange()">
          </div>

          <div class="input-line">
            <label for="pay-change">Kembalian</label>
            <input type="text" id="pay-change" readonly value="Rp 0">
          </div>

          <div class="numpad" style="margin-top:10px;">
            <button type="button" onclick="num(1)">1</button>
            <button type="button" onclick="num(2)">2</button>
            <button type="button" onclick="num(3)">3</button>
            <button type="button" onclick="num(4)">4</button>
            <button type="button" onclick="num(5)">5</button>
            <button type="button" onclick="num(6)">6</button>
            <button type="button" onclick="num(7)">7</button>
            <button type="button" onclick="num(8)">8</button>
            <button type="button" onclick="num(9)">9</button>
            <button type="button" onclick="num(0)">0</button>
            <button type="button" onclick="num('00')">00</button>
            <button type="button" class="danger" onclick="backspace()">⌫</button>
          </div>
        </div>

        <div class="btn-row">
          <button type="button" class="btn-empty" onclick="clearCart()">Kosongkan</button>
          <button type="button" class="btn-pay" id="btnPay" onclick="prosesBayar()">Bayar</button>
        </div>

      </div>
      <!-- ✅ end #payScroll -->

    </div>

  </div>
</div>

<div id="cartOverlay" onclick="toggleCart(false)"></div>

<button type="button" class="fab" id="fab">
  🛒 <span class="b" id="fabBadge">0</span>
</button>

<script>
let cart = [];

/* ===== Fix: kalau mobile, pastikan sidebar drawer ketutup saat masuk POS ===== */
(function(){
  const isMobile = window.matchMedia('(max-width: 1024px)').matches;
  if (isMobile){
    document.body.classList.remove('sb-open');
    const ov = document.querySelector('.overlay');
    if (ov) ov.style.display = 'none';
  }
})();

/* ===== Helpers ===== */
function escapeHtml(str){
  return String(str ?? '').replace(/[&<>"']/g, m => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
  }[m]));
}
function rupiah(n){ return new Intl.NumberFormat('id-ID').format(n); }

function getTotal(){
  let t = 0;
  cart.forEach(it => t += it.price * it.qty);
  return t;
}

function updateCounts(){
  const count = cart.reduce((a,b)=>a + b.qty, 0);
  document.getElementById('cartCount').textContent = count + ' item';

  const badge = document.getElementById('fabBadge');
  if (count > 0){
    badge.style.display = 'flex';
    badge.textContent = count;
  } else {
    badge.style.display = 'none';
    badge.textContent = '0';
  }
}

/* ===== Cart UI ===== */
function renderCart(){
  const box = document.getElementById('cartBox');
  box.innerHTML = '';

  if (cart.length === 0){
    box.innerHTML = `<div class="cart-empty">Keranjang masih kosong.</div>`;
  } else {
    cart.forEach((it, idx) => {
      const row = document.createElement('div');
      row.className = 'cart-row';
      row.innerHTML = `
        <div style="min-width:0;">
          <div class="cart-name">${escapeHtml(it.name)}</div>
          <div class="cart-sub">Rp ${rupiah(it.price)} / item</div>
        </div>

        <div class="qty">
          <button type="button" onclick="decr(${idx})">-</button>
          <span>${it.qty}</span>
          <button type="button" onclick="incr(${idx})">+</button>
        </div>

        <div class="line-right">Rp ${rupiah(it.price * it.qty)}</div>
      `;
      box.appendChild(row);
    });
  }

  document.getElementById('totalLabel').textContent = 'Rp ' + rupiah(getTotal());
  updateCounts();
  recalcChange();
  updatePayButtonState();
}

function addToCart(el){
  const id = String(el.dataset.id || '');
  const name = String(el.dataset.name || '');
  const price = parseInt(el.dataset.price || '0', 10);

  if (!id) return;
  const found = cart.find(x => x.id === id);
  if (found) found.qty += 1;
  else cart.push({id, name, price, qty: 1});

  renderCart();

  if (window.matchMedia('(max-width: 1024px)').matches){
    toggleCart(true);
  }
}
function incr(i){ cart[i].qty += 1; renderCart(); }
function decr(i){ cart[i].qty -= 1; if (cart[i].qty <= 0) cart.splice(i,1); renderCart(); }
function clearCart(){ cart = []; renderCart(); }

/* ===== Mobile bottom sheet ===== */
function toggleCart(force){
  const cartEl  = document.getElementById('cart');
  const overlay = document.getElementById('cartOverlay');

  const isOpen = cartEl.classList.contains('open');
  const next = (typeof force === 'boolean') ? force : !isOpen;

  cartEl.classList.toggle('open', next);

  const isMobile = window.matchMedia('(max-width: 1024px)').matches;
  if (isMobile){
    overlay.style.display = next ? 'block' : 'none';
  } else {
    overlay.style.display = 'none';
  }

  document.body.classList.toggle('cart-open', next);
}

/* ===== Payment ===== */
function onMethodChange(){
  const method = document.getElementById('pay-method').value;
  const total = getTotal();

  const cashBox = document.getElementById('payCashBox');
  const hint = document.getElementById('payHint');
  const payInp = document.getElementById('pay-amount');

  if (method === 'QRIS'){
    cashBox.classList.add('hidden');
    hint.classList.add('show');
    payInp.value = String(total);
  } else {
    cashBox.classList.remove('hidden');
    hint.classList.remove('show');
    if (payInp.value === '' || parseInt(payInp.value,10) === total) payInp.value = '0';
  }

  recalcChange();
  updatePayButtonState();
}

function num(n){
  const method = document.getElementById('pay-method').value;
  if (method === 'QRIS') return;

  const inp = document.getElementById('pay-amount');
  if (n === '00') inp.value = inp.value + '00';
  else {
    if (inp.value === '0') inp.value = '' + n;
    else inp.value = inp.value + n;
  }
  recalcChange();
  updatePayButtonState();
}

function backspace(){
  const method = document.getElementById('pay-method').value;
  if (method === 'QRIS') return;

  const inp = document.getElementById('pay-amount');
  inp.value = inp.value.slice(0, -1);
  if (inp.value === '') inp.value = '0';
  recalcChange();
  updatePayButtonState();
}

function recalcChange(){
  const total = getTotal();
  const method = document.getElementById('pay-method').value;
  const inp = document.getElementById('pay-amount');

  let pay = parseInt(inp.value || '0', 10);
  if (method === 'QRIS') pay = total;

  const change = pay - total;
  document.getElementById('pay-change').value = 'Rp ' + rupiah(change < 0 ? 0 : change);
}

function updatePayButtonState(){
  const btn = document.getElementById('btnPay');
  const total = getTotal();
  const method = document.getElementById('pay-method').value;

  if (cart.length === 0 || total <= 0){
    btn.disabled = true;
    btn.textContent = 'Bayar';
    return;
  }

  if (method === 'QRIS'){
    btn.disabled = false;
    btn.textContent = 'Bayar (QRIS)';
    return;
  }

  const pay = parseInt(document.getElementById('pay-amount').value || '0', 10);
  if (pay < total){
    btn.disabled = true;
    btn.textContent = 'Uang Kurang';
  } else {
    btn.disabled = false;
    btn.textContent = 'Bayar';
  }
}

/* ===== Customer ===== */
function toggleCustomer(force){
  const panel = document.getElementById('custPanel');
  const isOpen = panel.classList.contains('open');
  const next = (typeof force === 'boolean') ? force : !isOpen;
  panel.classList.toggle('open', next);

  const btn = document.getElementById('btnCustToggle');
  btn.textContent = next ? 'Tutup' : 'Cari';

  if (next){
    setTimeout(() => document.getElementById('customer-name').focus(), 60);
  } else {
    closeCustSuggest();
  }
}

function updateCustomerSummary(){
  const name = document.getElementById('customer-name').value.trim();
  const phone = document.getElementById('customer-phone').value.trim();
  const type = document.getElementById('order-type').value;

  let s = 'Umum';
  if (name !== ''){
    s = name;
    if (phone !== '') s += ' · ' + phone;
  }
  if (type) s += ' · ' + type;

  document.getElementById('custSummary').textContent = s;
}

function setCustomerUmum(){
  document.getElementById('customer-id').value = '';
  document.getElementById('customer-name').value = '';
  document.getElementById('customer-phone').value = '';
  document.getElementById('order-type').value = '';
  updateCustomerSummary();
}

let custTimer = null;
function openCustSuggest(){ document.getElementById('custSuggest').classList.add('open'); }
function closeCustSuggest(){ document.getElementById('custSuggest').classList.remove('open'); }

function setCustomerSelected(c){
  document.getElementById('customer-id').value = c.id || '';
  document.getElementById('customer-name').value = c.name || '';
  document.getElementById('customer-phone').value = c.phone || '';
  updateCustomerSummary();
  closeCustSuggest();
  toggleCustomer(false);
}

async function searchCustomers(q){
  const box = document.getElementById('custSuggest');
  box.innerHTML = '';
  if (!q || q.length < 2){ closeCustSuggest(); return; }

  try{
    const res = await fetch('kasir_customer_search.php?q=' + encodeURIComponent(q));
    const data = await res.json();

    if (!data.success || !Array.isArray(data.items) || data.items.length === 0){
      closeCustSuggest();
      return;
    }

    data.items.forEach(c => {
      const opt = document.createElement('div');
      opt.className = 'opt';
      const phone = c.phone ? c.phone : '-';
      opt.innerHTML = `<div class="nm">${escapeHtml(c.name)}</div><div class="ph">${escapeHtml(phone)}</div>`;
      opt.onclick = () => setCustomerSelected(c);
      box.appendChild(opt);
    });

    openCustSuggest();
  }catch(e){
    console.error(e);
    closeCustSuggest();
  }
}

/* ===== Checkout ===== */
async function prosesBayar(){
  if (cart.length === 0){
    alert('Keranjang masih kosong');
    return;
  }

  const methodUI = document.getElementById('pay-method').value; // CASH/QRIS
  const method = methodUI.toLowerCase();

  const total = getTotal();
  let payAmount = parseInt(document.getElementById('pay-amount').value || '0', 10);
  if (methodUI === 'QRIS') payAmount = total;

  if (methodUI === 'CASH' && payAmount < total){
    alert('Uang masuk kurang dari total.');
    return;
  }

  const payload = {
    method: method,
    pay_amount: payAmount,
    customer_id: document.getElementById('customer-id').value || null,
    customer_name: document.getElementById('customer-name').value.trim(),
    customer_phone: document.getElementById('customer-phone').value.trim(),
    order_type: document.getElementById('order-type').value,
    order_note: '',
    items: cart.map(it => ({
      id: it.id,
      name: it.name,
      price: it.price,
      qty: it.qty,
      subtotal: it.price * it.qty
    }))
  };

  try{
    const fd = new FormData();
    fd.append('csrf', document.getElementById('csrf').value);
    fd.append('payload', JSON.stringify(payload));

    const res = await fetch('kasir_checkout_api.php', { method:'POST', body: fd });
    const data = await res.json();

    if (!data.success){
      alert(data.message || 'Gagal simpan transaksi.');
      return;
    }

    cart = [];
    renderCart();

    document.getElementById('pay-method').value = 'CASH';
    document.getElementById('pay-amount').value = '0';
    onMethodChange();

    toggleCart(false);

    if (data.receipt_url) window.location.href = data.receipt_url;

  }catch(err){
    console.error(err);
    alert('Terjadi error saat kirim ke server.');
  }
}

/* ===== Filter ===== */
function filterMenu(){
  const q = document.getElementById('search').value.toLowerCase();
  document.querySelectorAll('.card').forEach(c=>{
    const name = (c.dataset.search || '').toLowerCase();
    c.style.display = name.includes(q) ? 'flex' : 'none';
  });
}

/* ===== Init ===== */
document.addEventListener('DOMContentLoaded', function(){
  const inp = document.getElementById('customer-name');
  inp.addEventListener('input', function(){
    document.getElementById('customer-id').value = '';
    const q = inp.value.trim();
    clearTimeout(custTimer);
    custTimer = setTimeout(()=>searchCustomers(q), 250);
    updateCustomerSummary();
  });

  document.getElementById('customer-phone').addEventListener('input', updateCustomerSummary);
  document.getElementById('order-type').addEventListener('change', updateCustomerSummary);

  document.addEventListener('click', function(e){
    const wrap = inp.closest('.autocomplete-wrap');
    if (!wrap || !wrap.contains(e.target)) closeCustSuggest();
  });

  window.addEventListener('resize', function(){
    if (!window.matchMedia('(max-width: 1024px)').matches){
      document.getElementById('cartOverlay').style.display = 'none';
      document.getElementById('cart').classList.remove('open');
      document.body.classList.remove('cart-open');
    }
  });

  updateCustomerSummary();
  onMethodChange();
  renderCart();
});

(function(){
  const fab = document.getElementById('fab');
  if (!fab) return;

  const isMobile = () => window.matchMedia('(max-width: 1024px)').matches;

  function clampFab(x,y){
    const size = 56;
    const pad  = 10;
    const topMin = 70;

    const maxX = window.innerWidth - size - pad;

    const bottomSafe = isMobile() ? 260 : 0;
    const maxY = window.innerHeight - size - pad - bottomSafe;

    return {
      x: Math.max(pad, Math.min(maxX, x)),
      y: Math.max(topMin, Math.min(maxY, y))
    };
  }

  const getPos = () => {
    const r = fab.getBoundingClientRect();
    return {x:r.left, y:r.top};
  };

  function savePos(x,y){
    localStorage.setItem('kasir_fab_pos', JSON.stringify({x,y}));
  }

  function ensureDefault(){
    if (!isMobile()) return;
    const raw = localStorage.getItem('kasir_fab_pos');
    if (raw) return;
    const d = clampFab(window.innerWidth - 56 - 16, window.innerHeight - 56 - 220);
    fab.style.left = d.x + 'px';
    fab.style.top  = d.y + 'px';
    fab.style.right = 'auto';
    fab.style.bottom = 'auto';
    savePos(d.x, d.y);
  }

  function restoreFab(){
    if (!isMobile()) return;
    const raw = localStorage.getItem('kasir_fab_pos');
    if (!raw) return;
    try{
      const p = JSON.parse(raw);
      if (typeof p.x === 'number' && typeof p.y === 'number'){
        const c = clampFab(p.x, p.y);
        fab.style.left = c.x + 'px';
        fab.style.top  = c.y + 'px';
        fab.style.right = 'auto';
        fab.style.bottom = 'auto';
      }
    }catch(e){}
  }

  let dragging = false;
  let moved = false;
  let startX=0, startY=0, origX=0, origY=0;

  function onDown(clientX, clientY){
    if (!isMobile()) return;
    if (document.body.classList.contains('cart-open')) return;

    dragging = true;
    moved = false;
    startX = clientX;
    startY = clientY;
    const p = getPos();
    origX = p.x;
    origY = p.y;
  }

  function onMove(clientX, clientY){
    if (!dragging || !isMobile()) return;
    const dx = clientX - startX;
    const dy = clientY - startY;

    if (Math.abs(dx) > 4 || Math.abs(dy) > 4) moved = true;

    const next = clampFab(origX + dx, origY + dy);
    fab.style.left = next.x + 'px';
    fab.style.top  = next.y + 'px';
    fab.style.right = 'auto';
    fab.style.bottom = 'auto';
  }

  function onUp(){
    if (!dragging || !isMobile()) return;
    dragging = false;

    const p = getPos();
    const next = clampFab(p.x, p.y);
    fab.style.left = next.x + 'px';
    fab.style.top  = next.y + 'px';
    fab.style.right = 'auto';
    fab.style.bottom = 'auto';
    savePos(next.x, next.y);

    if (!moved) toggleCart(true);
  }

  fab.addEventListener('touchstart', (e)=>{
    const t = e.touches[0];
    onDown(t.clientX, t.clientY);
  }, {passive:true});

  fab.addEventListener('touchmove', (e)=>{
    const t = e.touches[0];
    onMove(t.clientX, t.clientY);
  }, {passive:true});

  fab.addEventListener('touchend', ()=> onUp());

  fab.addEventListener('mousedown', (e)=> onDown(e.clientX, e.clientY));
  window.addEventListener('mousemove', (e)=> onMove(e.clientX, e.clientY));
  window.addEventListener('mouseup', ()=> onUp());

  ensureDefault();
  restoreFab();

  window.addEventListener('resize', ()=>{
    if (!isMobile()) {
      fab.style.left = '';
      fab.style.top  = '';
      fab.style.right = '';
      fab.style.bottom = '';
      return;
    }
    const p = getPos();
    const next = clampFab(p.x, p.y);
    fab.style.left = next.x + 'px';
    fab.style.top  = next.y + 'px';
    fab.style.right = 'auto';
    fab.style.bottom = 'auto';
    savePos(next.x, next.y);
  });
})();
</script>

<?php require __DIR__ . '/../publik/partials/kasir_layout_bottom.php'; ?>

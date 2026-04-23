<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';

require_role(['dapur']);

$dapurId = (int)auth_user()['id'];

// Ambil store dari admin pembuat akun dapur
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

// UI vars untuk layout dapur
$appName    = 'MultiPOS';
$pageTitle  = 'Pengaturan';
$activeMenu = 'pengaturan';
$userName   = (string)auth_user()['name'];

// Ambil data user (readonly)
$uQ = $pdo->prepare("SELECT id, name, email, created_at, created_by FROM users WHERE id=? LIMIT 1");
$uQ->execute([$dapurId]);
$userRow = $uQ->fetch();
if (!$userRow) { http_response_code(404); exit('User tidak ditemukan.'); }

require __DIR__ . '/../publik/partials/dapur_layout_top.php';
require __DIR__ . '/../publik/partials/dapur_sidebar.php';
?>

<main class="content">

  <div class="topbar">
    <div class="left">
      <button class="icon-btn" type="button"
        onclick="window.__dapurSidebarToggle && window.__dapurSidebarToggle()"
        aria-label="Menu">☰</button>

      <div>
        <p class="h" style="margin:0;">Pengaturan</p>
        <p class="p">Preferensi layar & info akun.</p>
      </div>
    </div>
    <div class="right">

    </div>
  </div>

  <div class="card" style="margin-bottom:14px;">
    <div style="font-weight:950; margin-bottom:8px;">Info Akun (Readonly)</div>
    <div class="row" style="gap:12px;">
      <span class="badge">Nama: <?= htmlspecialchars((string)$userRow['name']) ?></span>
      <span class="badge">Email: <?= htmlspecialchars((string)$userRow['email']) ?></span>
      <span class="badge">Role: dapur</span>
    </div>
    <div class="small muted" style="margin-top:10px;">
      Dibuat: <?= htmlspecialchars((string)$userRow['created_at']) ?>
    </div>
    <div class="small muted" style="margin-top:6px;">
    </div>
  </div>

  <div class="card" style="margin-bottom:14px;">
    <div style="font-weight:950; margin-bottom:8px;">Info Toko</div>
    <div class="row" style="gap:12px;">
      <span class="badge">Nama: <?= htmlspecialchars((string)$store['name']) ?></span>
      <?php if (!empty($store['phone'])): ?>
        <span class="badge">Telp: <?= htmlspecialchars((string)$store['phone']) ?></span>
      <?php endif; ?>
    </div>
    <?php if (!empty($store['address'])): ?>
      <div class="small muted" style="margin-top:10px;">
        Alamat: <?= htmlspecialchars((string)$store['address']) ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div style="font-weight:950; margin-bottom:8px;">Preferensi (Aman)</div>

    <div class="row" style="align-items:flex-end;">
      <div style="display:flex;flex-direction:column;gap:6px;">
        <span class="small muted">Auto refresh</span>
        <select id="pref_refresh">
          <option value="0">Off</option>
          <option value="10">10 detik</option>
          <option value="15">15 detik</option>
          <option value="20">20 detik</option>
          <option value="30">30 detik</option>
        </select>
      </div>

      <div style="display:flex;flex-direction:column;gap:6px;">
        <span class="small muted">Urutan antrian</span>
        <select id="pref_queue_order">
          <option value="newest">Terbaru dulu</option>
          <option value="oldest">Terlama dulu</option>
        </select>
      </div>

      <button class="btn" type="button" onclick="savePrefs()">Simpan</button>
    </div>

    <div class="small muted" style="margin-top:10px;">
    </div>
  </div>

</main>

<script>
  // Load + Save preferensi aman (localStorage)
  const KEY_REFRESH = 'dapur_pref_refresh_sec';
  const KEY_ORDER   = 'dapur_pref_queue_order';

  function loadPrefs(){
    const r = localStorage.getItem(KEY_REFRESH) ?? '15';
    const o = localStorage.getItem(KEY_ORDER) ?? 'newest';
    document.getElementById('pref_refresh').value = r;
    document.getElementById('pref_queue_order').value = o;
  }

  function savePrefs(){
    localStorage.setItem(KEY_REFRESH, document.getElementById('pref_refresh').value);
    localStorage.setItem(KEY_ORDER, document.getElementById('pref_queue_order').value);
    alert('Preferensi tersimpan.');
  }

  loadPrefs();
</script>

<?php require __DIR__ . '/../publik/partials/dapur_layout_bottom.php'; ?>

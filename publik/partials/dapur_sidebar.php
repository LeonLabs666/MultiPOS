<?php
$activeMenu = $activeMenu ?? 'antrian';
$storeName  = $storeName ?? '';
$userName   = $userName ?? '';
$appName    = $appName ?? 'MultiPOS';

function dapur_nav_active(string $key, string $active): string {
  return $key === $active ? 'is-active' : '';
}
?>
<aside class="sidebar">
  <div class="sb-brand">
    <div class="sb-logo">🍳</div>
    <div class="hide-when-collapsed">
      <div class="sb-title"><?= htmlspecialchars($appName) ?></div>
      <div class="sb-sub">Dapur • <?= htmlspecialchars($storeName) ?></div>
    </div>
  </div>

  <nav class="sb-nav">
    <div class="sb-section hide-when-collapsed">Utama</div>

    <a class="sb-item <?= dapur_nav_active('antrian',$activeMenu) ?>" data-tip="Antrian" href="dapur_dashboard.php">
      <div class="sb-ico">🧾</div>
      <div class="sb-text hide-when-collapsed">
        <div class="sb-label">Antrian</div>
        <div class="sb-desc">Order masuk & status</div>
      </div>
    </a>

    <a class="sb-item <?= dapur_nav_active('produksi',$activeMenu) ?>" data-tip="Produksi" href="dapur_produksi.php">
      <div class="sb-ico">🏭</div>
      <div class="sb-text hide-when-collapsed">
        <div class="sb-label">Produksi</div>
        <div class="sb-desc">Rekap total masak</div>
      </div>
    </a>

    <a class="sb-item <?= dapur_nav_active('riwayat',$activeMenu) ?>" data-tip="Riwayat" href="dapur_riwayat.php">
      <div class="sb-ico">📚</div>
      <div class="sb-text hide-when-collapsed">
        <div class="sb-label">Riwayat</div>
        <div class="sb-desc">Order selesai</div>
      </div>
    </a>
    <div class="sb-section hide-when-collapsed">Persediaan</div>

<a class="sb-item <?= dapur_nav_active('stok_masuk',$activeMenu) ?>" data-tip="Stok Masuk" href="dapur_stok_masuk.php">
  <div class="sb-ico">📦</div>
  <div class="sb-text hide-when-collapsed">
    <div class="sb-label">Stok Masuk</div>
    <div class="sb-desc">Terima barang datang</div>
  </div>
</a>
<a class="sb-item <?= dapur_nav_active('stok_masuk',$activeMenu) ?>" data-tip="Stok Masuk" href="dapur_persediaan.php">
  <div class="sb-ico">📊</div>
  <div class="sb-text hide-when-collapsed">
    <div class="sb-label">Persediaan</div>
    <div class="sb-desc">Lihat Persediaan</div>
  </div>
</a>




    <div class="sb-section hide-when-collapsed">Aplikasi</div>

    <a class="sb-item <?= dapur_nav_active('pengaturan',$activeMenu) ?>" data-tip="Pengaturan" href="dapur_pengaturan.php">
      <div class="sb-ico">⚙️</div>
      <div class="sb-text hide-when-collapsed">
        <div class="sb-label">Pengaturan</div>
        <div class="sb-desc">Preferensi & info</div>
      </div>
    </a>

    <div class="sb-footer">
      <div class="sb-badge"><?= htmlspecialchars($userName) ?></div>
      <div class="sb-badge good">role: dapur</div>
      <a class="btn" href="../logout.php" style="margin-left:auto;">Logout</a>
    </div>
  </nav>
</aside>

<script>
  (function(){
    const isMobile = window.matchMedia('(max-width: 1024px)').matches;
    if (isMobile) return;
    const saved = localStorage.getItem('dapur_sidebar_collapsed');
    if(saved === '1') document.body.classList.add('sb-collapsed');
  })();

  window.__dapurSidebarClose = function(){
    document.body.classList.remove('sb-open');
  };

  window.__dapurSidebarToggle = function(){
    const isMobile = window.matchMedia('(max-width: 1024px)').matches;
    if(isMobile){
      document.body.classList.toggle('sb-open');
    } else {
      document.body.classList.toggle('sb-collapsed');
      localStorage.setItem(
        'dapur_sidebar_collapsed',
        document.body.classList.contains('sb-collapsed') ? '1' : '0'
      );
    }
  };

  document.addEventListener('click', function(e){
    const a = e.target.closest('a');
    if(!a) return;
    if(window.matchMedia('(max-width: 1024px)').matches){
      document.body.classList.remove('sb-open');
    }
  });

  window.addEventListener('resize', function(){
    if(window.matchMedia('(max-width: 1024px)').matches){
      document.body.classList.remove('sb-collapsed');
    }
  });
</script>

<?php
declare(strict_types=1);

$u = auth_user();
$name = (string)($u['name'] ?? 'Admin');
$email = (string)($u['email'] ?? '');
$storeName = (string)($store['name'] ?? 'Toko');

$page_title = $page_title ?? 'Admin • MultiPOS';
$page_h1    = $page_h1 ?? 'Admin';
$active_menu = $active_menu ?? '';
function _active(string $key, string $active_menu): string {
  return $key === $active_menu ? ' is-active' : '';
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($page_title) ?></title>
  <style>
    :root{
      --bg:#f6f7fb;
      --card:#ffffff;
      --text:#0f172a;
      --muted:#64748b;
      --line:rgba(15,23,42,.12);
      --radius:18px;
      --shadow: 0 18px 48px rgba(15,23,42,.10);

      --sidebarW: 270px;
      --sidebarWCollapsed: 86px;
      --sidebarBg: #0b1220;
    }

    *{box-sizing:border-box}
    body{
      margin:0;
      font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, "Noto Sans", "Helvetica Neue", sans-serif;
      color:var(--text);
      background: var(--bg);
      min-height:100vh;
    }

    .app{
      display:grid;
      grid-template-columns: var(--sidebarW) 1fr;
      min-height:100vh;
      transition: grid-template-columns .18s ease;
    }

    /* ===== Desktop collapse state ===== */
    body.sb-collapsed .app{
      grid-template-columns: var(--sidebarWCollapsed) 1fr;
    }
    body.sb-collapsed .sidebar{
      padding:16px 10px;
    }
    body.sb-collapsed .brand .meta,
    body.sb-collapsed .brand .pill,
    body.sb-collapsed .profile,
    body.sb-collapsed .nav .txt,
    body.sb-collapsed .nav .hint,
    body.sb-collapsed .sb-note{
      display:none !important;
    }
    body.sb-collapsed .nav a{
      justify-content:center;
      padding:12px 10px;
    }
    body.sb-collapsed .nav .left{
      gap:0;
      justify-content:center;
    }
    body.sb-collapsed .nav .ico{
      width:auto;
    }

    .sidebar{
      background: var(--sidebarBg);
      color:#e2e8f0;
      padding:16px 14px;
      border-right:1px solid rgba(255,255,255,.06);
      position:sticky;
      top:0;
      height:100vh;
      overflow:auto;
    }

    .brand{
      display:flex; align-items:center; gap:10px;
      padding:8px 8px 12px;
      border-bottom:1px solid rgba(255,255,255,.08);
      margin-bottom:10px;
    }
    .logo{
      width:40px; height:40px;
      border-radius:14px;
      background: linear-gradient(135deg, rgba(37,99,235,.95), rgba(34,197,94,.75));
      display:flex; align-items:center; justify-content:center;
      font-weight:1000; color:white;
      box-shadow: 0 12px 28px rgba(0,0,0,.25);
      flex:0 0 auto;
    }
    .brand .meta{ min-width:0; }
    .brand .meta .t{ font-weight:1000;font-size:13px; line-height:1.1; }
    .brand .meta small{
      display:block; color:rgba(226,232,240,.7); font-size:12px; margin-top:2px;
      white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
      max-width: 160px;
    }
    .pill{
      margin-left:auto;
      font-size:11px;
      font-weight:900;
      padding:6px 10px;
      border-radius:999px;
      background: rgba(56,189,248,.18);
      border:1px solid rgba(56,189,248,.22);
      color:#bae6fd;
      white-space:nowrap;
    }

    .profile{
      margin:12px 6px 6px;
      padding:10px 10px;
      border-radius:16px;
      background: rgba(2,6,23,.40);
      border:1px solid rgba(148,163,184,.14);
    }
    .profile .n{ font-weight:1000; font-size:13px; }
    .profile .e{ font-size:12px; color:rgba(226,232,240,.70); margin-top:2px; }
    .profile .s{ font-size:12px; color:rgba(226,232,240,.85); margin-top:8px; }

    .nav{ margin-top:12px; display:grid; gap:8px; }
    .nav a{
      text-decoration:none;
      color:rgba(226,232,240,.88);
      padding:10px 10px;
      border-radius:14px;
      border:1px solid rgba(148,163,184,.10);
      background: rgba(2,6,23,.25);
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:10px;
      font-weight:950;
      font-size:13px;
    }
    .nav a:hover{
      border-color:rgba(37,99,235,.45);
      background: rgba(37,99,235,.14);
    }
    .nav a.is-active{
      border-color:rgba(56,189,248,.35);
      background: rgba(56,189,248,.16);
      color:#e0f2fe;
    }
    .nav .left{display:flex;align-items:center;gap:10px;min-width:0;}
    .nav .ico{ width:22px; text-align:center; flex:0 0 auto; }
    .nav .txt{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .nav .hint{font-size:11px;font-weight:900;color:rgba(226,232,240,.55);flex:0 0 auto;}

    .sb-note{
      margin-top:14px;
      color:rgba(226,232,240,.55);
      font-size:11.5px;
      padding:0 8px;
    }

    .main{ padding:18px; }

    .topbar{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:10px;
      flex-wrap:wrap;
      margin-bottom:14px;
      position:relative;
      z-index:5;
    }
    .top-left{
      display:flex;
      align-items:center;
      gap:10px;
      flex-wrap:wrap;
      min-width:0;
    }
    .topbar h1{
      margin:0;
      font-size:18px;
      font-weight:1000;
      white-space:nowrap;
      overflow:hidden;
      text-overflow:ellipsis;
      max-width: 52vw;
    }

    .right{
      display:flex;
      gap:10px;
      align-items:center;
      flex-wrap:wrap;
    }
    .badge{
      display:inline-flex; align-items:center; gap:8px;
      padding:8px 10px;
      border-radius:999px;
      background:#e0f2fe;
      border:1px solid #bae6fd;
      color:#075985;
      font-size:12px;
      font-weight:1000;
      white-space:nowrap;
    }

    .iconbtn{
      border:1px solid rgba(15,23,42,.12);
      background:#fff;
      border-radius:12px;
      padding:8px 10px;
      font-weight:1000;
      cursor:pointer;
      display:inline-flex;
      align-items:center;
      gap:8px;
      user-select:none;
      -webkit-tap-highlight-color: transparent;
      touch-action: manipulation;
    }
    .iconbtn:hover{ border-color: rgba(37,99,235,.45); }

    .card{
      background:var(--card);
      border:1px solid rgba(15,23,42,.12);
      border-radius:var(--radius);
      box-shadow: var(--shadow);
      padding:14px;
    }

    /* ===== Mobile overlay sidebar ===== */
    @media (max-width: 980px){
      .app{ grid-template-columns: 1fr; }
      .sidebar{
        position:fixed;
        top:0; left:0;
        width: min(86vw, 320px);
        height:100vh;
        transform: translateX(-110%);
        transition: transform .2s ease;
        z-index:50;
        box-shadow: 0 18px 48px rgba(0,0,0,.35);
        -webkit-overflow-scrolling: touch;
      }
      body.sb-open .sidebar{ transform: translateX(0); }
      .main{ padding:16px; }
    }

    .backdrop{ display:none; }
    @media (max-width: 980px){
      .backdrop{
        position:fixed; inset:0;
        background: rgba(2,6,23,.55);
        backdrop-filter: blur(2px);
        z-index:40;
      }
      body.sb-open .backdrop{ display:block; }
    }
  </style>
</head>
<body>
  <div class="backdrop" id="sbBackdrop" aria-hidden="true"></div>

  <div class="app" id="appShell">
    <aside class="sidebar" id="sidebar">
      <div class="brand">
        <div class="logo">M</div>
        <div class="meta">
          <div class="t">MultiPOS</div>
          <small><?= htmlspecialchars($storeName) ?></small>
        </div>
        <div class="pill">BASIC</div>
      </div>

      <div class="profile">
        <div class="n"><?= htmlspecialchars($name) ?></div>
        <div class="e"><?= htmlspecialchars($email) ?></div>
        <div class="s">Mode: <strong>POS Sederhana</strong></div>
      </div>

      <nav class="nav">
        <a class="<?= trim(_active('dashboard', $active_menu)) ?>" href="admin_basic_dashboard.php">
          <span class="left"><span class="ico">🏠</span><span class="txt">Dashboard</span></span>
          <span class="hint">Home</span>
        </a>

        <a class="<?= trim(_active('produk', $active_menu)) ?>" href="admin_products.php">
          <span class="left"><span class="ico">🧾</span><span class="txt">Produk</span></span>
          <span class="hint">Master</span>
        </a>

        <a class="<?= trim(_active('kategori', $active_menu)) ?>" href="admin_categories.php">
          <span class="left"><span class="ico">🗂️</span><span class="txt">Kategori</span></span>
          <span class="hint">Master</span>
        </a>

        <a class="<?= trim(_active('persediaan', $active_menu)) ?>" href="admin_persediaan.php">
          <span class="left"><span class="ico">📦</span><span class="txt">Persediaan</span></span>
          <span class="hint">Stok</span>
        </a>

        <a class="<?= trim(_active('sales', $active_menu)) ?>" href="admin_sales_report.php">
          <span class="left"><span class="ico">📈</span><span class="txt">Laporan</span></span>
          <span class="hint">Sales</span>
        </a>

        <a class="<?= trim(_active('riwayat', $active_menu)) ?>" href="admin_riwayat_stok.php">
          <span class="left"><span class="ico">📊</span><span class="txt">Riwayat</span></span>
          <span class="hint">Stok</span>
        </a>

        <a class="<?= trim(_active('akun', $active_menu)) ?>" href="admin_users.php">
          <span class="left"><span class="ico">👤</span><span class="txt">Akun</span></span>
          <span class="hint">User</span>
        </a>

        <a class="<?= trim(_active('pengaturan', $active_menu)) ?>" href="admin_store_settings.php">
          <span class="left"><span class="ico">⚙️</span><span class="txt">Pengaturan</span></span>
          <span class="hint">Store</span>
        </a>

        <a href="../logout.php">
          <span class="left"><span class="ico">🚪</span><span class="txt">Logout</span></span>
          <span class="hint">Keluar</span>
        </a>
      </nav>

      <div class="sb-note"></div>
    </aside>

    <main class="main">
      <div class="topbar">
        <div class="top-left">
          <button class="iconbtn" type="button" id="sbToggle"
                  aria-label="Toggle Sidebar" aria-expanded="false">
            ☰ <span style="font-size:12px;color:#64748b;font-weight:1000;">Menu</span>
          </button>
          <h1><?= htmlspecialchars($page_h1) ?></h1>
        </div>

        <div class="right">
          <div class="badge">Tipe: BASIC</div>
        </div>
      </div>

<script>
(function(){
  function qs(id){ return document.getElementById(id); }
  const KEY = 'multipos_basic_sidebar'; // desktop collapsed state
  const btn = qs('sbToggle');
  const backdrop = qs('sbBackdrop');
  const sidebar = qs('sidebar');

  function isMobile(){
    return window.matchMedia && window.matchMedia('(max-width: 980px)').matches;
  }

  function openMobile(){
    document.body.classList.add('sb-open');
    if (btn) btn.setAttribute('aria-expanded','true');
  }
  function closeMobile(){
    document.body.classList.remove('sb-open');
    if (btn) btn.setAttribute('aria-expanded','false');
  }
  function toggleMobile(e){
    if (e && e.preventDefault) e.preventDefault();
    if (document.body.classList.contains('sb-open')) closeMobile();
    else openMobile();
  }

  function applyDesktopSaved(){
    // Hanya untuk desktop
    const saved = localStorage.getItem(KEY) || 'open';
    document.body.classList.toggle('sb-collapsed', saved === 'collapsed');
  }

  function toggleDesktop(e){
    if (e && e.preventDefault) e.preventDefault();
    const nextCollapsed = !document.body.classList.contains('sb-collapsed');
    document.body.classList.toggle('sb-collapsed', nextCollapsed);
    localStorage.setItem(KEY, nextCollapsed ? 'collapsed' : 'open');
  }

  function onToggle(e){
    if (isMobile()) toggleMobile(e);
    else toggleDesktop(e);
  }

  function syncMode(){
    // kalau masuk mobile, jangan bawa state collapse desktop (biar UI bersih)
    if (isMobile()){
      document.body.classList.remove('sb-collapsed');
      closeMobile();
    } else {
      closeMobile();
      applyDesktopSaved();
    }
  }

  if (btn && !btn.__sbBound){
    btn.__sbBound = true;
    btn.addEventListener('click', onToggle);
    btn.addEventListener('touchstart', function(e){ onToggle(e); }, {passive:false});
  }

  if (backdrop && !backdrop.__sbBound){
    backdrop.__sbBound = true;
    backdrop.addEventListener('click', closeMobile);
    backdrop.addEventListener('touchstart', function(e){ e.preventDefault(); closeMobile(); }, {passive:false});
  }

  if (sidebar && !sidebar.__sbBound){
    sidebar.__sbBound = true;
    sidebar.addEventListener('click', function(e){
      const a = e.target && e.target.closest ? e.target.closest('a') : null;
      if (!a) return;
      // di mobile: klik menu menutup sidebar
      if (isMobile()) closeMobile();
    });
  }

  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') closeMobile();
  });

  window.addEventListener('resize', syncMode);

  // init
  syncMode();
})();
</script>

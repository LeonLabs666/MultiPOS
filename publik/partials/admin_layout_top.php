<?php
// admin_layout_top.php
// Expect variables from page:
// $appName, $pageTitle, $activeMenu
$appName   = $appName   ?? 'MultiPOS';
$pageTitle = $pageTitle ?? 'Admin';
$activeMenu = $activeMenu ?? '';

$user = function_exists('auth_user') ? auth_user() : null;
$userName = is_array($user) ? (($user['name'] ?? $user['username'] ?? $user['email'] ?? 'Admin')) : 'Admin';
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?> • <?= htmlspecialchars($appName) ?></title>

  <style>
    :root{
      --bg:#0b1220;
      --panel:#0f172a;
      --panel2:#111c34;
      --text:#e2e8f0;
      --muted:#94a3b8;
      --card:#ffffff;
      --border:#e2e8f0;
      --content-bg:#f8fafc;
      --accent:#2563eb;
      --danger:#ef4444;
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      margin:0;
      font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji","Segoe UI Emoji";
      background: var(--content-bg);
      color:#0f172a;
    }
    a{color:inherit}

    /* ===== Topbar ===== */
    .topbar{
      position: sticky;
      top:0;
      z-index: 30;
      background: linear-gradient(180deg, #0b1220, #0b1220);
      color: var(--text);
      border-bottom: 1px solid rgba(148,163,184,.12);
    }
    .topbar-inner{
      height:56px;
      display:flex;
      align-items:center;
      justify-content:space-between;
      padding:0 16px;
      gap:12px;
    }
    .brand{
      display:flex;
      align-items:center;
      gap:10px;
      font-weight:800;
      letter-spacing:.2px;
    }
    .brand-badge{
      width:34px;height:34px;border-radius:10px;
      background: rgba(148,163,184,.14);
      display:flex;align-items:center;justify-content:center;
      font-weight:900;
    }
    .top-actions{display:flex;align-items:center;gap:10px}
    .chip{
      display:inline-flex;align-items:center;gap:8px;
      padding:6px 10px;border-radius:999px;
      border:1px solid rgba(148,163,184,.18);
      color:var(--text);
      font-size:12px;
      background: rgba(15,23,42,.6);
      white-space:nowrap;
    }

    /* ===== Sidebar overlay mode ===== */
    .sidebar{
      position: fixed;
      top:0;
      left:0;
      height:100vh;
      width:270px;
      z-index:1000;

      background: linear-gradient(180deg, #0b1220, #0f172a);
      color: var(--text);
      border-right:1px solid rgba(148,163,184,.12);

      transform: translateX(-110%);
      transition: transform .2s ease;
      will-change: transform;

      display:flex;
      flex-direction:column;
      padding:12px;
    }
    body.sidebar-open .sidebar{
      transform: translateX(0);
    }

    .sidebar-overlay{
      position: fixed;
      inset: 0;
      background: rgba(2,6,23,.35);
      z-index: 999;
      opacity: 0;
      pointer-events: none;
      transition: opacity .2s ease;
    }
    body.sidebar-open .sidebar-overlay{
      opacity: 1;
      pointer-events: auto;
    }
    body.sidebar-open{overflow:hidden}

    .sidebar-header{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:10px;
      padding:6px 4px 12px;
      border-bottom: 1px solid rgba(148,163,184,.12);
      margin-bottom:10px;
    }
    .sidebar-title{
      display:flex;
      align-items:center;
      gap:10px;
      font-weight:900;
      letter-spacing:.2px;
    }
    .icon-btn{
      width:40px;height:40px;border-radius:12px;
      border:1px solid rgba(148,163,184,.18);
      background: rgba(15,23,42,.55);
      color: var(--text);
      cursor:pointer;
    }
    .icon-btn:active{transform: translateY(1px)}

    .nav{
      display:flex;
      flex-direction:column;
      gap:4px;
      padding:6px 2px;
      overflow:auto;
    }
    .nav a{
      text-decoration:none;
      display:flex;
      align-items:center;
      gap:10px;
      padding:10px 12px;
      border-radius:12px;
      color: var(--text);
      border:1px solid transparent;
      font-size:14px;
    }
    .nav a:hover{
      background: rgba(148,163,184,.10);
      border-color: rgba(148,163,184,.12);
    }
    .nav a.active{
      background: rgba(37,99,235,.18);
      border-color: rgba(37,99,235,.35);
    }
    .nav .sep{
      height:1px;
      background: rgba(148,163,184,.12);
      margin:8px 8px;
    }
    .sidebar-footer{
      margin-top:auto;
      padding-top:10px;
      border-top: 1px solid rgba(148,163,184,.12);
    }
    .logout{
      width:100%;
      display:flex;
      align-items:center;
      justify-content:center;
      gap:10px;
      padding:11px 12px;
      border-radius:14px;
      border:1px solid rgba(239,68,68,.35);
      background: rgba(239,68,68,.12);
      color: #fecaca;
      text-decoration:none;
      font-weight:700;
    }

    /* ===== Main layout (never shrinks) ===== */
    .main{
      width: 100%;
      min-width: 0;
    }
    .content{
      padding:18px 16px 28px;
    }
    .container{
      max-width: 1200px;
      margin: 0 auto;
    }

    /* Small helper */
    .muted{color:var(--muted)}
  </style>
</head>
<body>

  <!-- TOPBAR -->
  <header class="topbar">
    <div class="topbar-inner">
      <div style="display:flex;align-items:center;gap:10px;">
        <button id="sidebarToggle" class="icon-btn" type="button" aria-label="Toggle sidebar">☰</button>
        <div class="brand">
          <div class="brand-badge"><?= htmlspecialchars(mb_substr((string)$appName, 0, 1)) ?></div>
          <div><?= htmlspecialchars($appName) ?></div>
        </div>
      </div>

      <div class="top-actions">
        <span class="chip">👤 <?= htmlspecialchars((string)$userName) ?></span>
      </div>
    </div>
  </header>

  <!-- SIDEBAR (OVERLAY) -->
  <aside id="sidebar" class="sidebar" aria-label="Sidebar menu">
    <div class="sidebar-header">
      <div class="sidebar-title">
        <div class="brand-badge"><?= htmlspecialchars(mb_substr((string)$appName, 0, 1)) ?></div>
        <div><?= htmlspecialchars($appName) ?></div>
      </div>
      <button id="sidebarClose" class="icon-btn" type="button" aria-label="Close sidebar">✕</button>
    </div>

    <nav class="nav">
      <a href="admin_dashboard.php" class="<?= $activeMenu==='dashboard'?'active':'' ?>">🏠 Dashboard</a>
<a href="admin_menu_kategori.php" class="<?= $activeMenu==='menu_kategori'?'active':'' ?>">🧾 Menu & Kategori</a>


      <a href="admin_persediaan.php" class="<?= $activeMenu==='persediaan'?'active':'' ?>">📦 Persediaan</a>
      <a href="admin_suppliers.php" class="<?= $activeMenu==='supplier'?'active':'' ?>">🤝 Supplier</a>


      <div class="sep"></div>

      <a href="admin_shift_report.php" class="<?= $activeMenu==='lap_shift'?'active':'' ?>">🧾 Laporan Shift Kasir</a>
      <a href="admin_sales_report.php" class="<?= $activeMenu==='lap_penjualan'?'active':'' ?>">📊 Laporan Penjualan</a>
      <a href="admin_activity_logs.php" class="<?= $activeMenu==='activity_logs'?'active':'' ?>">🕵️ Activity Logs</a>


      <a href="admin_create_kasir.php" class="<?= $activeMenu==='user_kasir'?'active':'' ?>">👥 User / Kasir</a>
      <a href="admin_pengaturan_toko.php" class="<?= $activeMenu==='pengaturan_toko'?'active':'' ?>">⚙️ Pengaturan Toko</a>
      <a href="admin_export_data.php" class="<?= $activeMenu==='export'?'active':'' ?>">⬇️ Export Data</a>


    </nav>

    <div class="sidebar-footer">
      <a class="logout" href="../logout.php">🚪 Sign Out</a>
    </div>
  </aside>

  <!-- OVERLAY -->
  <div id="sidebarOverlay" class="sidebar-overlay" aria-hidden="true"></div>

  <!-- MAIN -->
  <main class="main">
    <div class="content">
      <div class="container">

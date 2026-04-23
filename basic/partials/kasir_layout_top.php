<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/**
 * Variabel yang bisa kamu set dari halaman:
 * $appName, $pageTitle, $activeMenu
 */
$appName    = $appName    ?? 'MultiPOS';
$pageTitle  = $pageTitle  ?? 'Kasir';
$activeMenu = $activeMenu ?? 'kasir_pos';

$user = function_exists('auth_user') ? auth_user() : null;
$kasirName = $user['name'] ?? ($_SESSION['username'] ?? 'Kasir');
$role      = $user['role'] ?? ($_SESSION['role'] ?? 'kasir');

/** Badge shift (opsional) */
$shiftLabel = '';
if (!empty($_SESSION['active_shift_id'])) $shiftLabel = 'Shift #' . (int)$_SESSION['active_shift_id'];

function kasir_nav_active(string $key, string $active): string {
  return $key === $active ? 'is-active' : '';
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?= htmlspecialchars($appName . ' • ' . $pageTitle) ?></title>

  <style>
    :root{
      /* base */
      --bg: #eef2ff;
      --surface: #ffffff;
      --ink: #0f172a;
      --muted: #64748b;
      --line: rgba(15,23,42,.10);

      /* sidebar */
      --sidebar: #0b1220;
      --sidebar2:#0f1a30;
      --sb-ink: #e5e7eb;
      --sb-muted: rgba(229,231,235,.68);
      --sb-line: rgba(148,163,184,.14);

      /* accents */
      --brand: #2563eb;
      --accent:#f97316;
      --good: #16a34a;

      --radius: 18px;
      --sb-open: 288px;
      --sb-mini: 84px;

      /* layout helper */
      --main-gap: 14px;
      --topbar-h: 78px; /* akan di-override JS */
    }

    *{ box-sizing:border-box; }
    body{
      margin:0;
      font-family: system-ui,-apple-system,Segoe UI,Roboto,Arial;
      background: var(--bg);
      color: var(--ink);
      overflow-x: hidden;
    }
    a{ color:inherit; }

    /* ===== LAYOUT (DESKTOP) ===== */
    .layout{
      display:grid;
      grid-template-columns: var(--sb-open) 1fr;
      min-height:100vh;
      transition: .2s ease;
    }
    body.sb-collapsed .layout{
      grid-template-columns: var(--sb-mini) 1fr;
    }

    /* ===== SIDEBAR ===== */
    .sidebar{
      background: linear-gradient(180deg, var(--sidebar) 0%, var(--sidebar2) 100%);
      color: var(--sb-ink);
      border-right: 1px solid var(--sb-line);
      position: sticky;
      top:0;
      height:100vh;
      overflow:auto;
      padding:14px 12px;
      z-index: 10;
    }

    .sb-brand{
      display:flex;
      align-items:center;
      gap:10px;
      padding:10px;
      border-radius: 18px;
      border: 1px solid var(--sb-line);
      background: rgba(255,255,255,.03);
      box-shadow: 0 16px 40px rgba(0,0,0,.22);
    }
    .sb-logo{
      width:42px;height:42px;
      border-radius: 16px;
      background: radial-gradient(circle at 0 0, #60a5fa, var(--brand));
      display:flex;align-items:center;justify-content:center;
      font-weight: 1000;
      letter-spacing:.5px;
      flex:0 0 auto;
      color:#fff;
    }
    .sb-title{ font-weight: 950; line-height:1.1; }
    .sb-sub{ font-size:12px; color: var(--sb-muted); margin-top:2px; }

    .sb-section{
      margin:14px 10px 8px;
      font-size:11px;
      color: var(--sb-muted);
      font-weight: 900;
      letter-spacing: .06em;
      text-transform: uppercase;
    }

    .sb-nav{ display:flex; flex-direction:column; gap:8px; margin-top:12px; }

    .sb-item{
      display:flex;
      align-items:center;
      gap:10px;
      padding:12px 12px;
      border-radius: 16px;
      text-decoration:none;
      border: 1px solid transparent;
      background: transparent;
      transition: transform .08s ease, background .12s ease, border-color .12s ease;
      position:relative;
      -webkit-tap-highlight-color: transparent;
    }
    .sb-item:hover{
      background: rgba(255,255,255,.06);
      border-color: rgba(148,163,184,.18);
      transform: translateY(-1px);
    }
    .sb-item.is-active{
      background: rgba(37,99,235,.18);
      border-color: rgba(37,99,235,.42);
    }
    .sb-ico{
      width:38px;height:38px;
      border-radius: 16px;
      display:flex;align-items:center;justify-content:center;
      background: rgba(255,255,255,.06);
      flex:0 0 auto;
      font-size:18px;
    }
    .sb-item.is-active .sb-ico{
      background: rgba(37,99,235,.28);
    }

    .sb-text{
      display:flex; flex-direction:column; gap:2px;
      min-width:0;
    }
    .sb-label{
      font-weight: 900;
      font-size: 13px;
      white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    }
    .sb-desc{
      font-size:11px;
      color: var(--sb-muted);
      white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    }

    /* collapse hide */
    body.sb-collapsed .hide-when-collapsed{ display:none !important; }

    /* tooltip when collapsed (desktop) */
    body.sb-collapsed .sb-item[data-tip]:hover::after{
      content: attr(data-tip);
      position:absolute;
      left: 92px;
      top:50%;
      transform: translateY(-50%);
      background:#0b1220;
      color:#fff;
      border:1px solid rgba(148,163,184,.18);
      padding:8px 10px;
      border-radius: 12px;
      font-size:12px;
      font-weight: 900;
      white-space:nowrap;
      box-shadow:0 16px 40px rgba(0,0,0,.35);
      z-index: 80;
    }

    .sb-footer{
      margin-top: 14px;
      padding:10px;
      border-radius: 16px;
      border: 1px solid var(--sb-line);
      background: rgba(255,255,255,.03);
      display:flex;
      gap:10px;
      align-items:center;
    }
    .sb-badge{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding:6px 10px;
      border-radius:999px;
      background: rgba(255,255,255,.06);
      border:1px solid rgba(148,163,184,.18);
      font-size:11px;
      font-weight: 900;
      color: var(--sb-ink);
      white-space:nowrap;
    }
    .sb-badge.good{
      border-color: rgba(34,197,94,.35);
      background: rgba(34,197,94,.12);
    }

    /* ===== MAIN ===== */
    .main{
      padding:16px 16px 26px;
      min-width:0;
    }

    /* wrapper untuk layout fixed-height (dipakai POS) */
    .main-inner{
      display:flex;
      flex-direction:column;
      gap: var(--main-gap);
      min-height: calc(100vh - 32px); /* kira-kira padding main */
    }

    .topbar{
      background: var(--surface);
      border: 1px solid var(--line);
      border-radius: var(--radius);
      padding: 12px 14px;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:12px;
      box-shadow: 0 18px 50px rgba(15,23,42,.08);
    }

    .top-left{
      display:flex;
      align-items:center;
      gap:10px;
      min-width:0;
    }

    .icon-btn{
      width:42px;height:42px;
      border-radius: 14px;
      border: 1px solid var(--line);
      background: #fff;
      cursor:pointer;
      font-size:18px;
      display:flex;align-items:center;justify-content:center;
      -webkit-tap-highlight-color: transparent;
    }
    .icon-btn:active{ transform: scale(.98); }

    .page-title{
      display:flex;
      flex-direction:column;
      gap:2px;
      min-width:0;
    }
    .page-title h1{
      margin:0;
      font-size:15px;
      font-weight: 950;
      white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    }
    .page-title .sub{
      font-size:12px;
      color: var(--muted);
      white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    }

    .top-right{
      display:flex;
      align-items:center;
      gap:10px;
      flex-wrap:wrap;
      justify-content:flex-end;
    }

    .pill{
      padding:8px 10px;
      border-radius:999px;
      border:1px solid var(--line);
      background:#fff;
      font-size:12px;
      font-weight:900;
      color: var(--muted);
      white-space:nowrap;
    }

    .btn{
      display:inline-flex;
      align-items:center;
      gap:8px;
      padding:10px 12px;
      border-radius: 14px;
      border:1px solid var(--line);
      background:#fff;
      cursor:pointer;
      font-weight: 900;
      text-decoration:none;
      -webkit-tap-highlight-color: transparent;
    }
    .btn:active{ transform: scale(.99); }

    /* container konten halaman */
    .page-body{
      min-height: 0;
    }

    /* ===== MOBILE DRAWER ===== */
    .overlay{
      display:none;
      position:fixed;
      inset:0;
      background: rgba(15,23,42,.45);
      z-index:40;
    }

    @media (max-width: 1024px){
      .layout{ grid-template-columns: 1fr !important; }
      body.sb-collapsed .layout{ grid-template-columns: 1fr !important; }
      .main{ padding: 12px !important; }

      .sidebar{
        position:fixed;
        left:-320px;
        top:0;
        bottom:0;
        width:288px;
        z-index:50;
        transition:left .2s ease;
        height:auto;
      }
      body.sb-open .sidebar{ left:0; }
      body.sb-open .overlay{ display:block; }

      body.sb-collapsed .hide-when-collapsed{ display: inline !important; }
      body.sb-collapsed .sb-item[data-tip]:hover::after{ display:none !important; }
    }
  </style>
</head>

<body>
  <div class="overlay" onclick="window.__kasirSidebarClose()"></div>

  <div class="layout">
    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar">
      <div class="sb-brand">
        <div class="sb-logo">K</div>
        <div class="hide-when-collapsed">
          <div class="sb-title"><?= htmlspecialchars($appName) ?></div>
          <div class="sb-sub">Kasir Panel (Basic)</div>
        </div>
      </div>

      <nav class="sb-nav">
        <div class="sb-section hide-when-collapsed">Utama</div>

        <a class="sb-item <?= kasir_nav_active('kasir_dashboard',$activeMenu) ?>" data-tip="Dashboard" href="kasir_dashboard.php">
          <div class="sb-ico">🏠</div>
          <div class="sb-text hide-when-collapsed">
            <div class="sb-label">Dashboard</div>
            <div class="sb-desc">Ringkasan & status shift</div>
          </div>
        </a>

        <a class="sb-item <?= kasir_nav_active('kasir_pos',$activeMenu) ?>" data-tip="POS" href="kasir_pos.php">
          <div class="sb-ico">🧾</div>
          <div class="sb-text hide-when-collapsed">
            <div class="sb-label">Penjualan</div>
            <div class="sb-desc">Input order & pembayaran</div>
          </div>
        </a>

        <div class="sb-section hide-when-collapsed">Operasional</div>

        <a class="sb-item <?= kasir_nav_active('kasir_shift',$activeMenu) ?>" data-tip="Shift" href="kasir_shift.php">
          <div class="sb-ico">⏱️</div>
          <div class="sb-text hide-when-collapsed">
            <div class="sb-label">Shift</div>
            <div class="sb-desc">Buka / tutup shift</div>
          </div>
        </a>

        <div class="sb-section hide-when-collapsed">Lainnya</div>
        <a class="sb-item" data-tip="Kembali Admin" href="admin_basic_dashboard.php">
          <div class="sb-ico">↩️</div>
          <div class="sb-text hide-when-collapsed">
            <div class="sb-label">Kembali Admin</div>
            <div class="sb-desc">Dashboard admin basic</div>
          </div>
        </a>

      <div class="sb-footer">
        <div class="sb-badge"><?= htmlspecialchars((string)$role) ?></div>
        <?php if($shiftLabel !== ''): ?>
          <div class="sb-badge good"><?= htmlspecialchars($shiftLabel) ?></div>
        <?php endif; ?>
      </div>
    </aside>

    <!-- ===== MAIN ===== -->
    <main class="main">
      <div class="main-inner">
        <div class="topbar" id="topbar">
          <div class="top-left">
            <button class="icon-btn" type="button" onclick="window.__kasirSidebarToggle()" aria-label="Toggle Sidebar">☰</button>

            <div class="page-title">
              <h1><?= htmlspecialchars($pageTitle) ?></h1>
              <div class="sub"><?= htmlspecialchars($kasirName) ?></div>
            </div>
          </div>

          <div class="top-right">
            <span class="pill"><?= htmlspecialchars($appName) ?></span>
            <a class="btn" href="../logout.php">Logout</a>
          </div>
        </div>

        <!-- konten halaman (POS, dashboard, shift, dll) -->
        <div class="page-body" id="pageBody">

<?php
// dipanggil dari halaman dapur, jadi variabel ini opsional
$appName    = $appName ?? 'MultiPOS';
$pageTitle  = $pageTitle ?? 'Dapur';
$activeMenu = $activeMenu ?? 'antrian';
$userName   = $userName ?? 'Dapur';
$storeName  = $storeName ?? '';
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($pageTitle) ?> • <?= htmlspecialchars($appName) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <style>
    :root{
      /* base (ikut Kasir) */
      --bg: #eef2ff;
      --surface: #ffffff;
      --ink: #0f172a;
      --muted: #64748b;
      --line: rgba(15,23,42,.10);

      /* sidebar (ikut Kasir) */
      --sidebar: #0b1220;
      --sidebar2:#0f1a30;
      --sb-ink: #e5e7eb;
      --sb-muted: rgba(229,231,235,.68);
      --sb-line: rgba(148,163,184,.14);

      /* accents */
      --brand: #2563eb;
      --good: #16a34a;

      --radius: 18px;
      --sb-open: 288px;
      --sb-mini: 84px;
    }

    *{ box-sizing:border-box; }
    html,body{height:100%}
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

    /* ===== SIDEBAR (GAYA KASIR) ===== */
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
      flex-wrap:wrap;
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

    /* ===== MAIN CONTENT AREA ===== */
    .content{
      padding:16px 16px 26px;
      min-width:0;
    }

    /* ===== STYLE KOMPONEN YANG DIPAKAI HALAMAN DAPUR (biar tetap cocok) ===== */
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
      margin-bottom:14px;
    }
    .topbar .left{
      display:flex;
      align-items:flex-start;
      gap:10px;
      min-width:0;
    }
    .topbar .left .h{
      font-size:15px;
      font-weight: 950;
      margin:0;
      white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    }
    .topbar .left .p{
      margin:2px 0 0;
      font-size:12px;
      color: var(--muted);
      white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    }
    .topbar .right{
      display:flex;
      align-items:center;
      gap:10px;
      flex-wrap:wrap;
      justify-content:flex-end;
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
      flex:0 0 auto;
    }
    .icon-btn:active{ transform: scale(.98); }

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
      color: var(--ink);
    }
    .btn:active{ transform: scale(.99); }

    .card{
      background: var(--surface);
      border: 1px solid var(--line);
      border-radius: var(--radius);
      padding:12px 14px;
      box-shadow: 0 18px 50px rgba(15,23,42,.06);
    }

    .badge{
      padding:8px 10px;
      border-radius:999px;
      border:1px solid var(--line);
      background:#fff;
      font-size:12px;
      font-weight:900;
      color: var(--muted);
      white-space:nowrap;
      display:inline-flex;
      align-items:center;
      gap:8px;
    }

    .muted{color:var(--muted)}
    .small{font-size:12px}
    .row{display:flex; gap:10px; flex-wrap:wrap; align-items:center}
    .spacer{flex:1}

    table{border-collapse:collapse}
    th,td{padding:8px 6px;border-bottom:1px solid rgba(15,23,42,.08)}
    th{color:var(--muted);font-size:12px;text-align:left}

    input,select{
      border:1px solid var(--line);
      border-radius:14px;
      padding:10px 12px;
      outline:none;
      background:#fff;
    }
    input:focus,select:focus{
      border-color:#93c5fd;
      box-shadow:0 0 0 3px rgba(147,197,253,.35);
    }

    /* ===== MOBILE DRAWER (SAMA DENGAN KASIR) ===== */
    .overlay{
      display:none;
      position:fixed;
      inset:0;
      background: rgba(15,23,42,.45);
      z-index:40;
    }

    @media (max-width: 1024px){
      .layout{
        grid-template-columns: 1fr !important;
      }
      body.sb-collapsed .layout{
        grid-template-columns: 1fr !important;
      }

      .content{
        padding: 12px !important;
      }

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

      /* di mobile: label tetap tampil (tidak mini sidebar) */
      body.sb-collapsed .hide-when-collapsed{ display: inline !important; }

      /* stop tooltip in mobile */
      body.sb-collapsed .sb-item[data-tip]:hover::after{ display:none !important; }
    }
  </style>
</head>

<body>
  <div class="overlay" onclick="window.__dapurSidebarClose && window.__dapurSidebarClose()"></div>

  <div class="layout">

<?php
declare(strict_types=1);

require __DIR__ . '/config/auth.php';

if (auth_user()) redirect_by_role((string)auth_user()['role']);
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>MultiPOS • Point of Sale & Manajemen Warung Makan Sederhana</title>
  <style>
    :root{
      --bg1:#eff6ff;
      --bg2:#f8fafc;
      --card:#ffffff;
      --text:#0f172a;
      --muted:#64748b;
      --line:rgba(15,23,42,.12);
      --brand:#2563eb;
      --brand2:#1d4ed8;
      --good:#16a34a;
      --radius:22px;
      --shadow: 0 24px 70px rgba(15,23,42,.12);
    }
    *{box-sizing:border-box}
    body{
      margin:0;
      font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, "Noto Sans", "Helvetica Neue", sans-serif;
      color:var(--text);
      background:
        radial-gradient(900px 500px at 10% -10%, rgba(37,99,235,.18), transparent),
        radial-gradient(900px 500px at 90% 0%, rgba(34,197,94,.14), transparent),
        linear-gradient(180deg, var(--bg1), var(--bg2));
      min-height:100vh;
    }
    a{color:inherit; text-decoration:none}
    .container{max-width:1100px; margin:0 auto; padding:18px 14px 40px;}
    .topbar{
      display:flex; align-items:center; justify-content:space-between;
      gap:12px; flex-wrap:wrap;
      padding:10px 0 18px;
    }
    .brand{
      display:flex; align-items:center; gap:10px;
    }
    .logo{
      width:42px; height:42px;
      border-radius:14px;
      background: linear-gradient(135deg, rgba(37,99,235,.92), rgba(34,197,94,.75));
      display:flex; align-items:center; justify-content:center;
      font-weight:1000; color:#fff;
      box-shadow: 0 12px 28px rgba(0,0,0,.12);
    }
    .brand .name{font-weight:1000; letter-spacing:.2px}
    .brand .tag{font-size:12px; color:var(--muted); margin-top:2px}
    .nav{
      display:flex; gap:10px; flex-wrap:wrap;
    }
    .btn{
      display:inline-flex; align-items:center; justify-content:center; gap:8px;
      padding:10px 14px;
      border-radius:16px;
      border:1px solid var(--line);
      background:#fff;
      font-weight:950;
      cursor:pointer;
      white-space:nowrap;
    }
    .btn.primary{
      border:none;
      color:#fff;
      background: linear-gradient(135deg, var(--brand), var(--brand2));
      box-shadow: 0 14px 30px rgba(37,99,235,.22);
    }
    .btn.ghost{background:rgba(255,255,255,.7)}
    .btn:active{transform:translateY(1px)}

    .hero{
      display:grid;
      grid-template-columns: 1.2fr .8fr;
      gap:16px;
      align-items:stretch;
      margin-top:6px;
    }
    @media (max-width: 900px){ .hero{grid-template-columns:1fr;} }

    .panel{
      background:rgba(255,255,255,.75);
      border:1px solid var(--line);
      border-radius:var(--radius);
      box-shadow: var(--shadow);
      padding:18px;
      backdrop-filter: blur(10px);
    }

    h1{
      margin:0;
      font-size:28px;
      letter-spacing:.2px;
      line-height:1.15;
    }
    .lead{
      margin:10px 0 0;
      color:var(--muted);
      font-size:14px;
      line-height:1.6;
      max-width:64ch;
    }
    .cta{
      display:flex; gap:10px; flex-wrap:wrap;
      margin-top:14px;
    }
    .chips{
      margin-top:14px;
      display:flex; gap:10px; flex-wrap:wrap;
    }
    .chip{
      display:inline-flex; align-items:center; gap:8px;
      padding:8px 10px;
      border-radius:999px;
      border:1px solid var(--line);
      background:#fff;
      font-size:12px;
      color:var(--muted);
      font-weight:800;
    }
    .dot{width:10px;height:10px;border-radius:999px;background:rgba(37,99,235,.85)}
    .dot.good{background:rgba(34,197,94,.85)}
    .dot.warn{background:rgba(249,115,22,.9)}

    .grid{
      display:grid;
      grid-template-columns: repeat(3, 1fr);
      gap:14px;
      margin-top:14px;
    }
    @media (max-width: 900px){ .grid{grid-template-columns:1fr;} }

    .card{
      background:#fff;
      border:1px solid var(--line);
      border-radius:18px;
      padding:14px;
      box-shadow: 0 12px 30px rgba(15,23,42,.06);
    }
    .card h3{margin:0; font-size:14px; font-weight:1000}
    .card p{margin:8px 0 0; color:var(--muted); font-size:13px; line-height:1.5}

    .stat{
      display:grid;
      gap:10px;
    }
    .big{
      display:flex; align-items:flex-start; justify-content:space-between; gap:10px;
    }
    .big .k{color:var(--muted); font-size:12px; font-weight:900}
    .big .v{font-size:16px; font-weight:1000}
    .mini{
      display:grid; gap:10px;
      margin-top:10px;
    }
    .mini .row{
      display:flex; gap:10px; align-items:flex-start;
      border:1px solid var(--line);
      border-radius:16px;
      padding:10px 12px;
      background:rgba(255,255,255,.8);
    }
    .badge{
      width:34px; height:34px;
      border-radius:14px;
      display:flex; align-items:center; justify-content:center;
      background: rgba(37,99,235,.10);
      border:1px solid rgba(37,99,235,.18);
      font-weight:1000;
      color:var(--brand2);
      flex:0 0 auto;
    }
    .mini strong{display:block; font-size:13px}
    .mini span{display:block; font-size:12px; color:var(--muted); margin-top:2px}

    footer{
      margin-top:18px;
      color:rgba(100,116,139,.9);
      font-size:12px;
      display:flex;
      justify-content:space-between;
      gap:10px;
      flex-wrap:wrap;
      align-items:center;
    }
    footer a{color:var(--brand2); font-weight:900}
  </style>
</head>
<body>
  <div class="container">
    <div class="topbar">
      <div class="brand">
        <div class="logo">M</div>
        <div>
          <div class="name">MultiPOS</div>
          <div class="tag">Manajemen Warung makan • Point of Sale • Kasir • Stok • Dapur</div>
        </div>
      </div>



    <div class="hero">
      <section class="panel">
        <h1>POS sederhana yang cepat, rapi, dan siap dipakai</h1>
        <p class="lead">
          MultiPOS membantu kamu mengelola transaksi kasir, shift, kas masuk/keluar, status dapur, dan stok
          dalam satu aplikasi yang ringan.
        </p>

        <div class="cta">
          <a class="btn primary" href="login.php">Mulai Login</a>
          <a class="btn" href="register.php">Buat Toko Baru</a>
        </div>

        <div class="chips">
          <span class="chip"><span class="dot"></span> Ringan</span>
          <span class="chip"><span class="dot good"></span> Cepat </span>
          <span class="chip"><span class="dot warn"></span> Lengkap </span>
        </div>

        <div class="grid">
          <div class="card">
            <h3>Kasir</h3>
            <p>Transaksi cepat, struk, riwayat transaksi, filter per shift & metode pembayaran.</p>
          </div>
          <div class="card">
            <h3>Shift & Kas</h3>
            <p>Buka/tutup shift, catat kas masuk/keluar, net kas lebih transparan.</p>
          </div>
          <div class="card">
            <h3>Stok & Dapur</h3>
            <p>Monitoring dapur, persediaan, dan alur produksi supaya operasional lebih tertata.</p>
          </div>
        </div>
      </section>

      <aside class="panel stat">
        <div class="big">
          <div>
            <div class="k">Akses Cepat</div>
            <div class="v">Masuk / Daftar</div>
          </div>
          <div style="font-size:22px; font-weight:1000; color:var(--brand2)">⚡</div>
        </div>

        <div class="mini">
          <div class="row">
            <div class="badge">1</div>
            <div>
              <strong>Registrasi</strong>
              <span>Buat akun + buat toko dalam satu langkah.</span>
            </div>
          </div>
          <div class="row">
            <div class="badge">2</div>
            <div>
              <strong>Login</strong>
              <span>Masuk sesuai role: admin / kasir / dapur.</span>
            </div>
          </div>
          <div class="row">
            <div class="badge" style="background:rgba(34,197,94,.10); border-color:rgba(34,197,94,.18); color:#166534;">✓</div>
            <div>
              <strong>Mulai Operasional</strong>
              <span>Buka shift, lakukan transaksi, dan pantau dapur.</span>
            </div>
          </div>
        </div>

        <div style="margin-top:10px; display:flex; gap:10px; flex-wrap:wrap;">
          <a class="btn" style="flex:1" href="login.php">Login</a>
          <a class="btn primary" style="flex:1" href="register.php">Daftar</a>
        </div>
      </aside>
    </div>

    <footer>
      <div>© <?= date('Y') ?> MultiPOS</div>
      <div>

      </div>
    </footer>
  </div>
</body>
</html>

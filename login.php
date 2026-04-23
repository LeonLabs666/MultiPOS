<?php
declare(strict_types=1);
require __DIR__ . '/config/db.php';
require __DIR__ . '/config/auth.php';

if (auth_user()) redirect_by_role(auth_user()['role']);

$error = '';


$SUPPORT_WA_NUMBER = '6281229943647';
$APP_NAME = 'MultiPOS';

$email_prefill = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email_prefill = trim($_POST['email'] ?? '');
  csrf_verify();
  $email = $email_prefill;
  $pass  = (string)($_POST['password'] ?? '');

  $st = $pdo->prepare("SELECT * FROM users WHERE email=? AND is_active=1 LIMIT 1");
  $st->execute([$email]);
  $u = $st->fetch();

  if (!$u || !password_verify($pass, (string)$u['password_hash'])) {
    $error = 'Email atau password salah.';
  } else {
    login_user($u);
    redirect_by_role((string)$u['role']);
  }
}

// Buat link WhatsApp dengan pesan terprefill
function build_wa_link(string $waNumber, string $appName, string $email): string {
  $emailText = $email !== '' ? $email : '(belum diisi)';
  $msg =
    "Halo Call Center {$appName}, saya lupa kata sandi.\n\n" .
    "Email login: {$emailText}\n" .
    "Mohon bantu reset password saya. Terima kasih.";
  return "https://wa.me/" . $waNumber . "?text=" . rawurlencode($msg);
}

$wa_link = build_wa_link($SUPPORT_WA_NUMBER, $APP_NAME, $email_prefill);
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Login • MultiPOS</title>
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
      --danger:#ef4444;
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
      display:flex;
      align-items:center;
      justify-content:center;
      padding:24px 14px;
    }
    .wrap{
      width: min(960px, 100%);
      display:grid;
      grid-template-columns: 1.1fr .9fr;
      gap:18px;
      align-items:stretch;
    }
    @media (max-width: 860px){
      .wrap{ grid-template-columns: 1fr; }
    }
    .hero{
      border-radius:var(--radius);
      padding:22px;
      background:
        radial-gradient(700px 420px at 20% 0%, rgba(37,99,235,.18), transparent),
        radial-gradient(700px 420px at 90% 10%, rgba(34,197,94,.14), transparent),
        #0b1220;
      color:#e2e8f0;
      box-shadow: var(--shadow);
      border:1px solid rgba(255,255,255,.08);
      overflow:hidden;
      position:relative;
    }
    .brandline{
      display:flex;
      align-items:center;
      gap:10px;
      margin-bottom:14px;
    }
    .logo{
      width:42px; height:42px;
      border-radius:14px;
      background: linear-gradient(135deg, rgba(37,99,235,.9), rgba(34,197,94,.7));
      display:flex; align-items:center; justify-content:center;
      box-shadow: 0 12px 28px rgba(0,0,0,.25);
      flex:0 0 auto;
      font-weight:1000;
      color:white;
    }
    .hero h1{
      margin:0;
      font-size:22px;
      letter-spacing:.2px;
    }
    .hero p{
      margin:10px 0 0;
      color:rgba(226,232,240,.8);
      line-height:1.5;
      font-size:13px;
      max-width:48ch;
    }
    .bullets{
      margin-top:16px;
      display:grid;
      gap:10px;
    }
    .b{
      display:flex; gap:10px; align-items:flex-start;
      background: rgba(2,6,23,.35);
      border:1px solid rgba(148,163,184,.18);
      border-radius:16px;
      padding:10px 12px;
    }
    .dot{
      width:10px;height:10px;border-radius:999px;
      background: rgba(56,189,248,.9);
      margin-top:4px;
      flex:0 0 auto;
    }
    .b strong{ display:block; font-size:13px; }
    .b span{ display:block; font-size:12px; color:rgba(226,232,240,.75); margin-top:2px; }

    .card{
      background:var(--card);
      border:1px solid var(--line);
      border-radius:var(--radius);
      box-shadow: var(--shadow);
      padding:18px;
      display:flex;
      flex-direction:column;
      justify-content:center;
    }
    .card h2{
      margin:0;
      font-size:18px;
      font-weight:1000;
      letter-spacing:.2px;
    }
    .sub{
      margin:6px 0 0;
      color:var(--muted);
      font-size:13px;
    }

    .alert{
      margin-top:12px;
      padding:10px 12px;
      border-radius:16px;
      border:1px solid rgba(239,68,68,.25);
      background: rgba(239,68,68,.06);
      color:#991b1b;
      font-size:13px;
      font-weight:800;
    }

    form{ margin-top:14px; }
    label{
      font-size:12px;
      font-weight:900;
      color:var(--muted);
      display:block;
      margin:12px 0 6px;
    }
    .field{
      position:relative;
    }
    input[type="email"], input[type="password"]{
      width:100%;
      padding:12px 12px;
      border-radius:16px;
      border:1px solid var(--line);
      outline:none;
      background:#fff;
      font-size:14px;
    }
    input:focus{
      border-color: rgba(37,99,235,.55);
      box-shadow: 0 0 0 4px rgba(37,99,235,.12);
    }

    .row{
      display:flex;
      gap:10px;
      align-items:center;
      justify-content:space-between;
      margin-top:14px;
      flex-wrap:wrap;
    }
    .btn{
      width:100%;
      border:none;
      border-radius:16px;
      padding:12px 14px;
      background: linear-gradient(135deg, var(--brand), var(--brand2));
      color:white;
      font-weight:1000;
      cursor:pointer;
      font-size:14px;
      box-shadow: 0 14px 30px rgba(37,99,235,.22);
    }
    .btn:active{ transform: translateY(1px); }

    .foot{
      margin-top:14px;
      color:var(--muted);
      font-size:13px;
      display:flex;
      gap:8px;
      flex-wrap:wrap;
      justify-content:center;
    }
    .foot a{
      color:var(--brand2);
      font-weight:950;
      text-decoration:none;
    }
    .foot a:hover{ text-decoration:underline; }

    .small{
      margin-top:10px;
      text-align:center;
      color:rgba(100,116,139,.9);
      font-size:11.5px;
    }

    /* Tambahan: link WA */
    .help{
      margin-top:12px;
      display:flex;
      justify-content:center;
      flex-wrap:wrap;
      gap:8px;
      font-size:12.5px;
      color:var(--muted);
    }
    .help a{
      color: var(--brand2);
      font-weight: 950;
      text-decoration: none;
    }
    .help a:hover{ text-decoration: underline; }
    .badge{
      display:inline-flex;
      align-items:center;
      gap:8px;
      padding:8px 10px;
      border-radius:999px;
      border:1px solid rgba(15,23,42,.12);
      background: rgba(37,99,235,.06);
    }
  </style>
</head>
<body>
  <div class="wrap">
    <aside class="hero">
      <div class="brandline">
        <div class="logo">M</div>
        <div>
          <div style="font-weight:1000; font-size:13px; color:rgba(226,232,240,.9)">MultiPOS</div>
          <div style="font-size:12px; color:rgba(226,232,240,.65)">Point of Sale • Kasir • Stok • Dapur</div>
        </div>
      </div>

      <h1>Masuk untuk mulai transaksi</h1>
      <p>Kelola Persediaan, kasir, kas masuk/keluar, dan dapur dalam satu aplikasi yang ringan.</p>

      <div class="bullets">
        <div class="b"><div class="dot"></div><div><strong>Cepat & ringan</strong><span>Halaman sederhana, cocok untuk perangkat kasir.</span></div></div>
        <div class="b"><div class="dot" style="background:rgba(34,197,94,.9)"></div><div><strong>Role-based</strong><span> Admin, Kasir, dan Dapur.</span></div></div>
        <div class="b"><div class="dot" style="background:rgba(249,115,22,.95)"></div><div><strong>Aman</strong><span>Keamanan terjaga.</span></div></div>
      </div>
    </aside>

    <main class="card">
      <h2>Login</h2>
      <div class="sub">Masukkan email & password akun.</div>

      <?php if ($error): ?>
        <div class="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post" autocomplete="on">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

        <label>Email</label>
        <div class="field">
          <input
            id="email"
            type="email"
            name="email"
            required
            placeholder="contoh: kasir@tokokita.com"
            autofocus
            value="<?= htmlspecialchars($email_prefill) ?>"
          >
        </div>

        <label>Password</label>
        <div class="field">
          <input type="password" name="password" required placeholder="••••••••">
        </div>

        <div class="row">
          <button class="btn" type="submit">Masuk</button>
        </div>

        <!-- Tambahan: Lupa password via WA Call Center -->
        <div class="help">
          <span class="badge">
            Lupa kata sandi?
            <a id="wa-forgot" href="<?= htmlspecialchars($wa_link) ?>" target="_blank" rel="noopener">Chat Call Center (WhatsApp)</a>
          </span>
        </div>

        <div class="foot">
          <span>Belum punya akun admin?</span>
          <a href="register.php">Registrasi Admin</a>
        </div>

        <div class="small">© <?= date('Y') ?> MultiPOS</div>
      </form>
    </main>
  </div>

  <script>
    (function () {
      const emailInput = document.getElementById('email');
      const waLink = document.getElementById('wa-forgot');

      // Samakan dengan PHP di atas
      const WA_NUMBER = <?= json_encode($SUPPORT_WA_NUMBER) ?>;
      const APP_NAME  = <?= json_encode($APP_NAME) ?>;

      function buildWaLink(email) {
        const emailText = (email && email.trim() !== '') ? email.trim() : '(belum diisi)';
        const msg =
          `Halo Call Center ${APP_NAME}, saya lupa kata sandi.\n\n` +
          `Email login: ${emailText}\n` +
          `Mohon bantu reset password saya. Terima kasih.`;

        return `https://wa.me/${WA_NUMBER}?text=${encodeURIComponent(msg)}`;
      }

      // Update link realtime mengikuti email yang diketik
      emailInput.addEventListener('input', function () {
        waLink.href = buildWaLink(emailInput.value);
      });
    })();
  </script>
</body>
</html>

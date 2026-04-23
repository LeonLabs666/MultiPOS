<?php
declare(strict_types=1);

require __DIR__ . '/config/db.php';
require __DIR__ . '/config/auth.php';
require __DIR__ . '/config/audit.php';

if (auth_user()) redirect_by_role((string)auth_user()['role']);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();

  $adminName  = trim((string)($_POST['admin_name'] ?? ''));
  $email      = trim((string)($_POST['email'] ?? ''));
  $pass       = (string)($_POST['password'] ?? '');
  $storeName  = trim((string)($_POST['store_name'] ?? ''));
  $address    = trim((string)($_POST['address'] ?? ''));
  $phone      = trim((string)($_POST['phone'] ?? ''));

  // jenis toko (basic / bom)
  $storeType  = (string)($_POST['store_type'] ?? 'bom');
  if (!in_array($storeType, ['basic', 'bom'], true)) {
    $storeType = 'bom';
  }

  if ($email === '' || $pass === '' || $storeName === '') {
    $error = 'Email, password, dan nama toko wajib diisi.';
  } else {
    try {
      $pdo->beginTransaction();

      $hash = password_hash($pass, PASSWORD_DEFAULT);
      $stmt = $pdo->prepare("INSERT INTO users (name,email,password_hash,role,is_active) VALUES (?,?,?,?,1)");
      $stmt->execute([
        $adminName !== '' ? $adminName : 'Admin',
        $email,
        $hash,
        'admin'
      ]);
      $adminId = (int)$pdo->lastInsertId();

      // simpan store_type ke stores
      $st = $pdo->prepare("
        INSERT INTO stores (name,address,phone,owner_admin_id,is_active,store_type)
        VALUES (?,?,?,?,1,?)
      ");
      $st->execute([
        $storeName,
        $address !== '' ? $address : null,
        $phone !== '' ? $phone : null,
        $adminId,
        $storeType
      ]);
      $storeId = (int)$pdo->lastInsertId();

      $pdo->commit();

      $actorNewAdmin = [
        'id' => $adminId,
        'role' => 'admin'
      ];

      log_activity(
        $pdo,
        $actorNewAdmin,
        'ADMIN_REGISTER',
        'Admin self-registered #' . $adminId . ' (' . $email . ')',
        'admin',
        $adminId,
        [
          'method' => 'register_admin',
          'admin_email' => $email,
          'admin_name' => ($adminName !== '' ? $adminName : 'Admin'),
          'store_id' => $storeId,
          'store_name' => $storeName,
          'store_type' => $storeType,
        ],
        $storeId
      );

      login_user([
        'id' => $adminId,
        'name' => $adminName !== '' ? $adminName : 'Admin',
        'email' => $email,
        'role' => 'admin'
      ]);

      // IMPORTANT: jangan hardcode ke admin_dashboard.php
      // biarkan auth.php menentukan redirect berdasarkan store_type
      redirect_by_role('admin');

    } catch (PDOException $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $msg = $e->getMessage();
      $error = (stripos($msg, 'Duplicate') !== false || stripos($msg, 'duplicate') !== false)
        ? 'Email sudah terdaftar. Silakan login.'
        : 'Registrasi gagal.';
    }
  }
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Registrasi Admin • MultiPOS</title>
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
      width: min(980px, 100%);
      display:grid;
      grid-template-columns: 1.1fr .9fr;
      gap:18px;
      align-items:stretch;
    }
    @media (max-width: 900px){
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
      position:relative;
      overflow:hidden;
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
      max-width:54ch;
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
    .dot{width:10px;height:10px;border-radius:999px;background:rgba(56,189,248,.9);margin-top:4px;flex:0 0 auto;}
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
    input{
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

    .grid2{
      display:grid;
      gap:10px;
      grid-template-columns: 1fr 1fr;
    }
    @media(max-width: 520px){
      .grid2{ grid-template-columns: 1fr; }
    }
    .divider{
      margin:14px 0 6px;
      height:1px;
      background: rgba(15,23,42,.10);
      border:none;
    }
    .sectionTitle{
      margin:8px 0 0;
      font-size:12px;
      font-weight:1000;
      color:#0f172a;
      letter-spacing:.2px;
    }

    select{
      width:100%;
      padding:12px 12px;
      border-radius:16px;
      border:1px solid var(--line);
      outline:none;
      background:#fff;
      font-size:14px;
      appearance: none;
    }
    select:focus{
      border-color: rgba(37,99,235,.55);
      box-shadow: 0 0 0 4px rgba(37,99,235,.12);
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
      margin-top:14px;
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
  </style>
</head>
<body>
  <div class="wrap">
    <aside class="hero">
      <div class="brandline">
        <div class="logo">M</div>
        <div>
          <div style="font-weight:1000; font-size:13px; color:rgba(226,232,240,.9)">MultiPOS</div>
          <div style="font-size:12px; color:rgba(226,232,240,.65)">Buat akun admin & toko kamu</div>
        </div>
      </div>

      <h1>Registrasi Admin</h1>
      <p>Lengkapi data admin dan data toko. Setelah berhasil, akan otomatis login ke dashboard admin.</p>

      <div class="bullets">
        <div class="b"><div class="dot"></div><div><strong>1 akun = 1 toko</strong><span>Admin otomatis jadi pemilik toko yang didaftarkan.</span></div></div>
        <div class="b"><div class="dot" style="background:rgba(34,197,94,.9)"></div><div><strong>Data toko fleksibel</strong><span>Alamat & nomor HP opsional, bisa diisi nanti.</span></div></div>
        <div class="b"><div class="dot" style="background:rgba(249,115,22,.95)"></div><div><strong>Aman</strong><span>Keamanan akun Terjamin.</span></div></div>
      </div>
    </aside>

    <main class="card">
      <h2>Buat Akun Admin</h2>
      <div class="sub">Isi data di bawah untuk memulai.</div>

      <?php if ($error): ?>
        <div class="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post" autocomplete="on">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

        <div class="grid2">
          <div>
            <label>Nama Admin (opsional)</label>
            <input name="admin_name" value="<?= htmlspecialchars((string)($_POST['admin_name'] ?? '')) ?>" placeholder="contoh: Leonardo">
          </div>
          <div>
            <label>Email</label>
            <input type="email" name="email" required value="<?= htmlspecialchars((string)($_POST['email'] ?? '')) ?>" placeholder="contoh: admin@tokokita.com">
          </div>
        </div>

        <label>Password</label>
        <input type="password" name="password" required placeholder="buat password">

        <hr class="divider">
        <div class="sectionTitle">Data Toko / Warung</div>

        <label>Nama Toko / Warung</label>
        <input name="store_name" required value="<?= htmlspecialchars((string)($_POST['store_name'] ?? '')) ?>" placeholder="contoh: Warung Sembako Bu Rina">

        <label>Jenis Toko</label>
        <?php $postedType = (string)($_POST['store_type'] ?? 'bom'); ?>
        <select name="store_type" required>
          <option value="basic" <?= $postedType === 'basic' ? 'selected' : '' ?>>
            POS Sederhana (tanpa bahan & resep)
          </option>
          <option value="bom" <?= $postedType === 'bom' ? 'selected' : '' ?>>
            Advance (pakai bahan & resep)
          </option>
        </select>

        <div class="grid2">
          <div>
            <label>Alamat (opsional)</label>
            <input name="address" value="<?= htmlspecialchars((string)($_POST['address'] ?? '')) ?>" placeholder="alamat toko">
          </div>
          <div>
            <label>No. HP (opsional)</label>
            <input name="phone" value="<?= htmlspecialchars((string)($_POST['phone'] ?? '')) ?>" placeholder="contoh: 08xxxx">
          </div>
        </div>

        <button class="btn" type="submit">Daftar & Masuk</button>

        <div class="foot">
          <span>Sudah punya akun?</span>
          <a href="login.php">Kembali login</a>
        </div>

        <div class="small">© <?= date('Y') ?> MultiPOS</div>
      </form>
    </main>
  </div>
</body>
</html>

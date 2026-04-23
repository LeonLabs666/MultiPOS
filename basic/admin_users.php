<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

$page_title  = 'Akun • MultiPOS';
$page_h1     = 'Kelola Akun';
$active_menu = 'akun';

$storeId   = (int)$storeId;
$storeName = (string)$store['name'];
$adminId   = (int)$adminId;

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$error = (string)($_SESSION['flash_error'] ?? '');
$ok    = (string)($_SESSION['flash_ok'] ?? '');
unset($_SESSION['flash_error'], $_SESSION['flash_ok']);

function is_valid_email(string $email): bool {
  return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
}

function find_user_by_email(PDO $pdo, string $email): ?array {
  $st = $pdo->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
  $st->execute([$email]);
  $r = $st->fetch(PDO::FETCH_ASSOC);
  return $r ?: null;
}

function find_user(PDO $pdo, int $id): ?array {
  $st = $pdo->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
  $st->execute([$id]);
  $r = $st->fetch(PDO::FETCH_ASSOC);
  return $r ?: null;
}

function kasir_of_admin(PDO $pdo, int $kasirId, int $adminId): ?array {
  $st = $pdo->prepare("SELECT * FROM users WHERE id=? AND role='kasir' AND created_by=? LIMIT 1");
  $st->execute([$kasirId, $adminId]);
  $r = $st->fetch(PDO::FETCH_ASSOC);
  return $r ?: null;
}

function kasir_has_sales(PDO $pdo, int $kasirId, int $storeId): bool {
  $st = $pdo->prepare("SELECT COUNT(*) FROM sales WHERE store_id=? AND kasir_id=?");
  $st->execute([$storeId, $kasirId]);
  return (int)$st->fetchColumn() > 0;
}

/**
 * ===== POST HANDLER =====
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $action = (string)($_POST['action'] ?? '');

  // ===== Update profil admin sendiri
  if ($action === 'admin_update') {
    $name  = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $pass  = (string)($_POST['password'] ?? '');

    if ($name === '') {
      $_SESSION['flash_error'] = 'Nama admin wajib diisi.';
      header('Location: admin_users.php'); exit;
    }
    if (!is_valid_email($email)) {
      $_SESSION['flash_error'] = 'Email admin tidak valid.';
      header('Location: admin_users.php'); exit;
    }

    $me = find_user($pdo, $adminId);
    if (!$me || (string)$me['role'] !== 'admin') {
      $_SESSION['flash_error'] = 'Akun admin tidak ditemukan.';
      header('Location: admin_users.php'); exit;
    }

    // cek email unik (kecuali diri sendiri)
    $exists = find_user_by_email($pdo, $email);
    if ($exists && (int)$exists['id'] !== $adminId) {
      $_SESSION['flash_error'] = 'Email sudah dipakai akun lain.';
      header('Location: admin_users.php'); exit;
    }

    try {
      if ($pass !== '') {
        if (strlen($pass) < 6) {
          throw new RuntimeException('Password minimal 6 karakter.');
        }
        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $st = $pdo->prepare("UPDATE users SET name=?, email=?, password_hash=?, updated_at=NOW() WHERE id=? AND role='admin' LIMIT 1");
        $st->execute([$name, $email, $hash, $adminId]);
      } else {
        $st = $pdo->prepare("UPDATE users SET name=?, email=?, updated_at=NOW() WHERE id=? AND role='admin' LIMIT 1");
        $st->execute([$name, $email, $adminId]);
      }

      // update session biar sidebar ikut berubah
      $_SESSION['user']['name']  = $name;
      $_SESSION['user']['email'] = $email;

      $_SESSION['flash_ok'] = 'Profil admin berhasil diperbarui.';
    } catch (Throwable $e) {
      $_SESSION['flash_error'] = $e->getMessage() ?: 'Gagal memperbarui profil admin.';
    }

    header('Location: admin_users.php'); exit;
  }

  // ===== Tambah kasir
  if ($action === 'kasir_create') {
    $name  = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $pass  = (string)($_POST['password'] ?? '');

    if ($name === '') {
      $_SESSION['flash_error'] = 'Nama kasir wajib diisi.';
      header('Location: admin_users.php'); exit;
    }
    if (!is_valid_email($email)) {
      $_SESSION['flash_error'] = 'Email kasir tidak valid.';
      header('Location: admin_users.php'); exit;
    }
    if (strlen($pass) < 6) {
      $_SESSION['flash_error'] = 'Password kasir minimal 6 karakter.';
      header('Location: admin_users.php'); exit;
    }

    $exists = find_user_by_email($pdo, $email);
    if ($exists) {
      $_SESSION['flash_error'] = 'Email sudah dipakai akun lain.';
      header('Location: admin_users.php'); exit;
    }

    try {
      $hash = password_hash($pass, PASSWORD_BCRYPT);
      $st = $pdo->prepare("
        INSERT INTO users (name,email,password_hash,role,created_by,is_active,must_change_password,created_at)
        VALUES (?,?,?,?,?,1,1,NOW())
      ");
      $st->execute([$name, $email, $hash, 'kasir', $adminId]);

      $_SESSION['flash_ok'] = 'Akun kasir berhasil dibuat. Kasir akan diminta ganti password saat login.';
    } catch (Throwable $e) {
      $_SESSION['flash_error'] = $e->getMessage() ?: 'Gagal membuat akun kasir.';
    }

    header('Location: admin_users.php'); exit;
  }

  // ===== Update kasir (nama/email/status + optional reset password)
  if ($action === 'kasir_update') {
    $kasirId = (int)($_POST['id'] ?? 0);
    $name    = trim((string)($_POST['name'] ?? ''));
    $email   = trim((string)($_POST['email'] ?? ''));
    $active  = (int)($_POST['is_active'] ?? 1) === 1 ? 1 : 0;
    $pass    = (string)($_POST['password'] ?? ''); // optional reset

    if ($kasirId <= 0) {
      $_SESSION['flash_error'] = 'ID kasir tidak valid.';
      header('Location: admin_users.php'); exit;
    }
    if ($name === '') {
      $_SESSION['flash_error'] = 'Nama kasir wajib diisi.';
      header('Location: admin_users.php'); exit;
    }
    if (!is_valid_email($email)) {
      $_SESSION['flash_error'] = 'Email kasir tidak valid.';
      header('Location: admin_users.php'); exit;
    }

    $kasir = kasir_of_admin($pdo, $kasirId, $adminId);
    if (!$kasir) {
      $_SESSION['flash_error'] = 'Kasir tidak ditemukan (atau bukan milik toko ini).';
      header('Location: admin_users.php'); exit;
    }

    // email unik (kecuali dirinya)
    $exists = find_user_by_email($pdo, $email);
    if ($exists && (int)$exists['id'] !== $kasirId) {
      $_SESSION['flash_error'] = 'Email sudah dipakai akun lain.';
      header('Location: admin_users.php'); exit;
    }

    try {
      if ($pass !== '') {
        if (strlen($pass) < 6) {
          throw new RuntimeException('Password minimal 6 karakter.');
        }
        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $st = $pdo->prepare("
          UPDATE users
          SET name=?, email=?, is_active=?, password_hash=?, must_change_password=1, updated_at=NOW()
          WHERE id=? AND role='kasir' AND created_by=?
          LIMIT 1
        ");
        $st->execute([$name, $email, $active, $hash, $kasirId, $adminId]);
      } else {
        $st = $pdo->prepare("
          UPDATE users
          SET name=?, email=?, is_active=?, updated_at=NOW()
          WHERE id=? AND role='kasir' AND created_by=?
          LIMIT 1
        ");
        $st->execute([$name, $email, $active, $kasirId, $adminId]);
      }

      $_SESSION['flash_ok'] = 'Data kasir berhasil diperbarui.';
    } catch (Throwable $e) {
      $_SESSION['flash_error'] = $e->getMessage() ?: 'Gagal memperbarui kasir.';
    }

    header('Location: admin_users.php'); exit;
  }

  // ===== Hapus kasir (permanen) — hanya jika belum ada transaksi
  if ($action === 'kasir_delete') {
    $kasirId = (int)($_POST['id'] ?? 0);
    $typed   = trim((string)($_POST['confirm'] ?? ''));

    if ($kasirId <= 0) {
      $_SESSION['flash_error'] = 'ID kasir tidak valid.';
      header('Location: admin_users.php'); exit;
    }
    if (mb_strtoupper($typed) !== 'HAPUS') {
      $_SESSION['flash_error'] = 'Konfirmasi salah. Ketik HAPUS untuk menghapus permanen.';
      header('Location: admin_users.php'); exit;
    }

    $kasir = kasir_of_admin($pdo, $kasirId, $adminId);
    if (!$kasir) {
      $_SESSION['flash_error'] = 'Kasir tidak ditemukan (atau bukan milik toko ini).';
      header('Location: admin_users.php'); exit;
    }

    try {
      if (kasir_has_sales($pdo, $kasirId, $storeId)) {
        // tidak boleh delete karena ada FK/riwayat, maka nonaktifkan
        $st = $pdo->prepare("UPDATE users SET is_active=0, updated_at=NOW() WHERE id=? AND role='kasir' AND created_by=? LIMIT 1");
        $st->execute([$kasirId, $adminId]);
        $_SESSION['flash_ok'] = 'Kasir punya riwayat transaksi, jadi akun dinonaktifkan (bukan dihapus permanen).';
      } else {
        $st = $pdo->prepare("DELETE FROM users WHERE id=? AND role='kasir' AND created_by=? LIMIT 1");
        $st->execute([$kasirId, $adminId]);
        $_SESSION['flash_ok'] = 'Akun kasir berhasil dihapus permanen.';
      }
    } catch (Throwable $e) {
      $_SESSION['flash_error'] = $e->getMessage() ?: 'Gagal menghapus kasir.';
    }

    header('Location: admin_users.php'); exit;
  }

  header('Location: admin_users.php'); exit;
}

/**
 * ===== DATA HALAMAN =====
 */
$admin = find_user($pdo, $adminId);
if (!$admin) {
  $admin = ['name'=>($_SESSION['user']['name'] ?? 'Admin'), 'email'=>($_SESSION['user']['email'] ?? '')];
}

$kasirQ = $pdo->prepare("
  SELECT id,name,email,is_active,must_change_password,created_at,updated_at
  FROM users
  WHERE role='kasir' AND created_by=?
  ORDER BY id DESC
");
$kasirQ->execute([$adminId]);
$kasirs = $kasirQ->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/partials/layout_top.php';
?>

<div style="margin-bottom:10px;color:#334155">
  <div><b>Toko:</b> <?= h($storeName) ?></div>
</div>

<?php if ($error): ?>
  <div style="padding:12px 14px;border:1px solid rgba(220,38,38,.25);background:rgba(220,38,38,.06);border-radius:14px;color:#991b1b;margin:14px 0;">
    <?= h($error) ?>
  </div>
<?php endif; ?>

<?php if ($ok): ?>
  <div style="padding:12px 14px;border:1px solid rgba(16,185,129,.25);background:rgba(16,185,129,.06);border-radius:14px;color:#065f46;margin:14px 0;">
    <?= h($ok) ?>
  </div>
<?php endif; ?>

<div class="card" style="margin-bottom:16px;">
  <h2 style="margin:0 0 10px;font-size:16px;">Akun Admin (Profil)</h2>
  <form method="post" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;align-items:end;">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="admin_update">

    <div>
      <label style="font-size:13px;color:#334155">Nama</label>
      <input name="name" value="<?= h($admin['name'] ?? '') ?>" style="width:100%;padding:10px 12px;border-radius:12px;border:1px solid var(--line)">
    </div>

    <div>
      <label style="font-size:13px;color:#334155">Email</label>
      <input name="email" value="<?= h($admin['email'] ?? '') ?>" style="width:100%;padding:10px 12px;border-radius:12px;border:1px solid var(--line)">
    </div>

    <div style="grid-column:1 / -1">
      <label style="font-size:13px;color:#334155">Password Baru (opsional)</label>
      <input name="password" type="password" placeholder="kosongkan jika tidak mengganti"
             style="width:100%;padding:10px 12px;border-radius:12px;border:1px solid var(--line)">
      <div style="color:var(--muted);font-size:12px;margin-top:6px;">Jika diisi, password akan diperbarui.</div>
    </div>

    <div style="grid-column:1 / -1">
      <button class="iconbtn" style="background:#0b1220;color:#fff;border:0;padding:10px 14px;border-radius:12px;cursor:pointer;font-weight:900;">
        Simpan Profil Admin
      </button>
    </div>
  </form>
</div>

<div class="card">
  <h2 style="margin:0 0 10px;font-size:16px;">Akun Kasir</h2>

  <div style="display:grid;grid-template-columns:1fr;gap:14px;margin-bottom:14px;">
    <div style="padding:14px;border:1px solid var(--line);border-radius:14px;background:rgba(2,6,23,.02);">
      <div style="font-weight:900;margin-bottom:10px;">Tambah Kasir</div>
      <form method="post" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;align-items:end;">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="kasir_create">

        <div>
          <label style="font-size:13px;color:#334155">Nama</label>
          <input name="name" placeholder="Nama kasir" style="width:100%;padding:10px 12px;border-radius:12px;border:1px solid var(--line)">
        </div>

        <div>
          <label style="font-size:13px;color:#334155">Email</label>
          <input name="email" placeholder="email@contoh.com" style="width:100%;padding:10px 12px;border-radius:12px;border:1px solid var(--line)">
        </div>

        <div style="grid-column:1 / -1">
          <label style="font-size:13px;color:#334155">Password</label>
          <input name="password" type="password" placeholder="minimal 6 karakter"
                 style="width:100%;padding:10px 12px;border-radius:12px;border:1px solid var(--line)">
          <div style="color:var(--muted);font-size:12px;margin-top:6px;">Kasir akan diminta ganti password saat login pertama.</div>
        </div>

        <div style="grid-column:1 / -1">
          <button class="iconbtn" style="background:#0b1220;color:#fff;border:0;padding:10px 14px;border-radius:12px;cursor:pointer;font-weight:900;">
            Buat Akun Kasir
          </button>
        </div>
      </form>
    </div>
  </div>

  <div style="overflow:auto;">
    <table style="width:100%;border-collapse:collapse;">
      <thead>
        <tr style="text-align:left;border-bottom:1px solid var(--line);color:var(--muted);font-size:12px;">
          <th style="padding:10px 8px;">ID</th>
          <th style="padding:10px 8px;">Nama</th>
          <th style="padding:10px 8px;">Email</th>
          <th style="padding:10px 8px;">Status</th>
          <th style="padding:10px 8px;">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$kasirs): ?>
          <tr>
            <td colspan="5" style="padding:14px 8px;color:var(--muted);">Belum ada akun kasir.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($kasirs as $k): ?>
            <?php
              $kid = (int)$k['id'];
              $active = (int)$k['is_active'] === 1;
              $mustChange = (int)($k['must_change_password'] ?? 0) === 1;
            ?>
            <tr style="border-bottom:1px solid var(--line);vertical-align:top;">
              <td style="padding:12px 8px;"><?= $kid ?></td>
              <td style="padding:12px 8px;"><?= h($k['name'] ?? '') ?></td>
              <td style="padding:12px 8px;"><?= h($k['email'] ?? '') ?></td>
              <td style="padding:12px 8px;">
                <div style="display:flex;flex-direction:column;gap:6px;">
                  <span style="display:inline-block;padding:6px 10px;border-radius:999px;font-size:12px;
                    border:1px solid <?= $active ? 'rgba(16,185,129,.35)' : 'rgba(100,116,139,.35)' ?>;
                    background: <?= $active ? 'rgba(16,185,129,.08)' : 'rgba(100,116,139,.08)' ?>;
                    color: <?= $active ? '#065f46' : '#334155' ?>;">
                    <?= $active ? 'Aktif' : 'Nonaktif' ?>
                  </span>
                  <?php if ($mustChange): ?>
                    <span style="color:var(--muted);font-size:12px;">Perlu ganti password</span>
                  <?php endif; ?>
                </div>
              </td>
              <td style="padding:12px 8px;">
                <div style="display:grid;gap:10px;">
                  <form method="post" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;align-items:end;">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="kasir_update">
                    <input type="hidden" name="id" value="<?= $kid ?>">

                    <div>
                      <label style="font-size:12px;color:var(--muted)">Nama</label>
                      <input name="name" value="<?= h($k['name'] ?? '') ?>" style="padding:8px 10px;border-radius:12px;border:1px solid var(--line);width:100%;">
                    </div>

                    <div>
                      <label style="font-size:12px;color:var(--muted)">Email</label>
                      <input name="email" value="<?= h($k['email'] ?? '') ?>" style="padding:8px 10px;border-radius:12px;border:1px solid var(--line);width:100%;">
                    </div>

                    <div>
                      <label style="font-size:12px;color:var(--muted)">Status</label>
                      <select name="is_active" style="padding:8px 10px;border-radius:12px;border:1px solid var(--line);width:100%;">
                        <option value="1" <?= $active ? 'selected' : '' ?>>Aktif</option>
                        <option value="0" <?= !$active ? 'selected' : '' ?>>Nonaktif</option>
                      </select>
                    </div>

                    <div>
                      <label style="font-size:12px;color:var(--muted)">Reset Password (opsional)</label>
                      <input name="password" type="password" placeholder="kosongkan jika tidak reset"
                             style="padding:8px 10px;border-radius:12px;border:1px solid var(--line);width:100%;">
                    </div>

                    <div style="grid-column:1 / -1;display:flex;gap:10px;flex-wrap:wrap;">
                      <button style="padding:8px 10px;border-radius:12px;border:1px solid var(--line);background:#fff;cursor:pointer;font-weight:900;">
                        Simpan
                      </button>
                    </div>
                  </form>

                  <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="kasir_delete">
                    <input type="hidden" name="id" value="<?= $kid ?>">

                    <div style="flex:0 0 160px;">
                      <label style="font-size:12px;color:var(--muted)">Hapus (ketik HAPUS)</label>
                      <input name="confirm" placeholder="HAPUS"
                             style="padding:8px 10px;border-radius:12px;border:1px solid var(--line);width:100%;">
                    </div>

                    <button style="padding:8px 10px;border-radius:12px;border:1px solid rgba(220,38,38,.35);background:rgba(220,38,38,.06);color:#991b1b;cursor:pointer;font-weight:900;">
                      Hapus
                    </button>

                    <div style="color:var(--muted);font-size:12px;max-width:420px;">
                      Catatan: kalau kasir sudah punya transaksi, sistem akan <b>nonaktifkan</b> (bukan delete permanen) agar riwayat aman.
                    </div>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/partials/layout_bottom.php'; ?>

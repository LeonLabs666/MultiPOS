<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';
require_role(['admin']);

$appName='MultiPOS';
$pageTitle='Kelola User';
$activeMenu='kasir';
$storeName='Kelola User';

$adminId=(int)auth_user()['id'];
$error=''; $ok='';

/**
 * NOTE:
 * - Sekarang admin bisa membuat role: kasir, dapur, admin
 * - Admin bisa toggle/reset/delete PERMANEN untuk user yang dibuat oleh admin ini (created_by = adminId)
 * - Untuk keamanan, admin tidak bisa menghapus dirinya sendiri di halaman ini.
 */

function user_of_admin(PDO $pdo,int $uid,int $aid): ?array {
  // hanya user yang dibuat oleh admin ini (kasir/dapur/admin)
  $st=$pdo->prepare("SELECT id,is_active,role,created_by FROM users WHERE id=? AND role IN ('kasir','dapur','admin') AND created_by=? LIMIT 1");
  $st->execute([$uid,$aid]);
  $r=$st->fetch(PDO::FETCH_ASSOC);
  return $r?:null;
}

function role_label(string $role): string {
  return match($role){
    'admin' => 'Admin',
    'dapur' => 'Dapur',
    default => 'Kasir',
  };
}

function is_valid_role(string $role): bool {
  return in_array($role, ['kasir','dapur','admin'], true);
}

if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf_verify();
  $act=(string)($_POST['action']??'');

  if($act==='create'){
    $n=trim((string)($_POST['name']??''));
    $e=trim((string)($_POST['email']??''));
    $p=(string)($_POST['password']??'');
    $role=(string)($_POST['role']??'kasir');

    if(!is_valid_role($role)) $role='kasir';

    if($n===''||$e===''||$p==='') {
      $error='Semua field wajib diisi.';
    } else {
      try{
        $pdo->prepare("INSERT INTO users(name,email,password_hash,role,created_by) VALUES(?,?,?,?,?)")
            ->execute([$n,$e,password_hash($p,PASSWORD_DEFAULT),$role,$adminId]);
        $ok = role_label($role).' berhasil dibuat.';
      }catch(PDOException $ex){
        $msg = $ex->getMessage();
        $error = (str_contains($msg,'Duplicate') || str_contains($msg,'duplicate'))
          ? 'Email sudah dipakai.'
          : 'Gagal buat user.';
      }
    }
  }

  if($act==='toggle'){
    $uid=(int)($_POST['user_id']??0);
    $u=user_of_admin($pdo,$uid,$adminId);

    if(!$u) {
      $error='User tidak valid.';
    } else {
      $new=((int)$u['is_active']===1)?0:1;
      $pdo->prepare("UPDATE users SET is_active=? WHERE id=?")->execute([$new,$uid]);
      $ok='Status '.role_label((string)$u['role']).' diubah.';
    }
  }

  if($act==='reset'){
    $uid=(int)($_POST['user_id']??0);
    $p=(string)($_POST['new_password']??'');
    $u=user_of_admin($pdo,$uid,$adminId);

    if(!$u || $p==='') {
      $error='User/password tidak valid.';
    } else {
      $pdo->prepare("UPDATE users SET password_hash=?, must_change_password=1, is_active=1 WHERE id=?")
          ->execute([password_hash($p,PASSWORD_DEFAULT),$uid]);
      $ok='Password '.role_label((string)$u['role']).' direset.';
    }
  }

  if($act==='delete'){
    $uid=(int)($_POST['user_id']??0);
    $confirm=trim((string)($_POST['confirm_delete']??''));
    $u=user_of_admin($pdo,$uid,$adminId);

    if(!$u) {
      $error='User tidak valid.';
    } elseif ($uid === $adminId) {
      $error='Tidak bisa menghapus akun sendiri.';
    } elseif (strtoupper($confirm) !== 'DELETE') {
      $error='Konfirmasi hapus salah. Ketik DELETE untuk menghapus permanen.';
    } else {
      // hard delete
      try{
        $pdo->prepare("DELETE FROM users WHERE id=? AND created_by=? AND role IN ('kasir','dapur','admin')")
            ->execute([$uid,$adminId]);
        $ok='User berhasil dihapus permanen.';
      } catch(PDOException $ex){
        // bisa gagal kalau ada FK constraint di DB
        $error='Gagal menghapus user. Kemungkinan user sudah terhubung ke data transaksi/relasi lain.';
      }
    }
  }
}

// tampilkan kasir + dapur + admin yang dibuat oleh admin ini
$list=$pdo->prepare("
  SELECT id,name,email,role,is_active
  FROM users
  WHERE role IN ('kasir','dapur','admin')
    AND created_by=?
  ORDER BY
    FIELD(role,'admin','kasir','dapur') ASC,
    id DESC
");
$list->execute([$adminId]);
$rows=$list->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/../publik/partials/admin_layout_top.php';
?>

<style>
  .wrap{max-width:1200px;}
  .muted{color:#64748b}
  .card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:14px;}
  .btn{padding:10px 14px;border-radius:12px;border:1px solid #e2e8f0;background:#0f172a;color:#fff;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:8px}
  .btn:active{transform:translateY(1px)}
  .btn-outline{background:#fff;color:#0f172a}
  .btn-outline:hover{background:#f8fafc}
  .btn-danger{background:#ef4444;border-color:#ef4444;color:#fff}
  .btn-danger:hover{filter:brightness(0.95)}
  .btn-small{padding:8px 12px;border-radius:12px;font-size:13px}

  input,select{padding:10px 12px;border-radius:12px;border:1px solid #e2e8f0;width:100%}
  label{font-size:13px;color:#334155}
  .msg-err{color:#ef4444;margin:8px 0 12px}
  .msg-ok{color:#16a34a;margin:8px 0 12px}

  .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
  .grid-1{display:grid;grid-template-columns:1fr;gap:10px}
  .form-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
  .form-actions .btn{min-width:140px}

  .pill{display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;border:1px solid #e2e8f0;white-space:nowrap}
  .pill.admin{background:#eef2ff;border-color:#c7d2fe;color:#3730a3}
  .pill.kasir{background:#ecfdf5;border-color:#bbf7d0;color:#166534}
  .pill.dapur{background:#fff7ed;border-color:#fed7aa;color:#9a3412}
  .pill.off{background:#fef2f2;border-color:#fecaca;color:#991b1b}
  .pill.on{background:#ecfdf5;border-color:#bbf7d0;color:#166534}

  .table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}
  table{width:100%;border-collapse:collapse;background:#fff}
  th,td{border-bottom:1px solid #f1f5f9;padding:10px 8px;text-align:left;font-size:13px;vertical-align:top}
  th{color:#64748b;font-weight:700;white-space:nowrap}

  .aksi{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
  .aksi form{display:inline-flex;gap:8px;align-items:center;flex-wrap:wrap;margin:0}
  .aksi input[type="password"],
  .aksi input[type="text"]{width:140px}

  .section-title{margin:0 0 10px;font-size:18px}
  .head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:10px}
  .head h1{margin:0}
  .head .sub{margin:6px 0 0}

  @media (max-width: 760px){
    .grid-2{grid-template-columns:1fr}
    .form-actions .btn{width:100%}
    .aksi{flex-direction:column;align-items:stretch}
    .aksi form{width:100%}
    .aksi .btn{width:100%}
    .aksi input[type="password"], .aksi input[type="text"]{width:100%}
  }

  /* Table -> stacked cards on small screens */
  @media (max-width: 640px){
    .table-wrap{overflow:visible}
    table.resp{border-collapse:separate;border-spacing:0 10px}
    table.resp thead{display:none}
    table.resp tbody tr{
      display:block;
      border:1px solid #e2e8f0;
      border-radius:14px;
      padding:10px 10px;
      background:#fff;
    }
    table.resp tbody td{
      display:flex;
      gap:10px;
      justify-content:space-between;
      align-items:flex-start;
      border-bottom:1px dashed #f1f5f9;
      padding:8px 4px;
    }
    table.resp tbody td:last-child{border-bottom:none}
    table.resp tbody td::before{
      content: attr(data-label);
      font-weight:700;
      color:#334155;
      min-width:88px;
      flex:0 0 88px;
    }
  }
</style>

<div class="wrap">
  <div class="head">
    <div>
      <h1>Kelola User</h1>
      <div class="muted sub">Buat & kelola akun <b>Kasir</b>, <b>Dapur</b>, dan <b>Admin</b></div>
    </div>
  </div>

  <?php if($error):?><div class="msg-err"><?=htmlspecialchars($error)?></div><?php endif;?>
  <?php if($ok):?><div class="msg-ok"><?=htmlspecialchars($ok)?></div><?php endif;?>

  <!-- FORM CREATE -->
  <div class="card" style="margin-bottom:12px;max-width:720px;">
    <div class="section-title">Buat User Baru</div>

    <form method="post" class="grid-1">
      <input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>">
      <input type="hidden" name="action" value="create">

      <div class="grid-2">
        <div>
          <label>Role</label>
          <select name="role">
            <option value="kasir" selected>Kasir</option>
            <option value="dapur">Dapur</option>
            <option value="admin">Admin</option>
          </select>
        </div>

        <div>
          <label>Nama</label>
          <input name="name" required>
        </div>
      </div>

      <div class="grid-2">
        <div>
          <label>Email</label>
          <input type="email" name="email" required>
        </div>

        <div>
          <label>Password</label>
          <input type="password" name="password" required>
        </div>
      </div>

      <div class="form-actions">
        <button class="btn" type="submit">Buat User</button>
        <div class="muted" style="font-size:13px;">
          Tips: setelah reset password, user diminta ganti password saat login.
        </div>
      </div>
    </form>
  </div>

  <!-- LIST -->
  <div class="card">
    <div class="section-title">Daftar User</div>

    <div class="table-wrap">
      <table class="resp">
        <thead>
          <tr>
            <th style="width:70px;">ID</th>
            <th style="width:120px;">Role</th>
            <th>Nama</th>
            <th>Email</th>
            <th style="width:90px;">Aktif</th>
            <th style="width:420px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php if(!$rows): ?>
          <tr><td colspan="6">Belum ada user yang dibuat.</td></tr>
        <?php else: ?>
          <?php foreach($rows as $r):
            $role=(string)$r['role'];
            $roleClass = $role==='admin' ? 'admin' : ($role==='dapur' ? 'dapur' : 'kasir');
            $isActive = (int)$r['is_active'] === 1;
          ?>
            <tr>
              <td data-label="ID"><?= (int)$r['id'] ?></td>

              <td data-label="Role">
                <span class="pill <?= $roleClass ?>"><?= htmlspecialchars(role_label($role)) ?></span>
              </td>

              <td data-label="Nama"><?= htmlspecialchars((string)$r['name']) ?></td>

              <td data-label="Email"><?= htmlspecialchars((string)$r['email']) ?></td>

              <td data-label="Aktif">
                <span class="pill <?= $isActive ? 'on' : 'off' ?>"><?= $isActive ? 'Ya' : 'Tidak' ?></span>
              </td>

              <td data-label="Aksi">
                <div class="aksi">
                  <!-- Toggle -->
                  <form method="post">
                    <input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="user_id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-outline btn-small" type="submit">
                      <?= $isActive ? 'Nonaktifkan' : 'Aktifkan' ?>
                    </button>
                  </form>

                  <!-- Reset password -->
                  <form method="post">
                    <input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>">
                    <input type="hidden" name="action" value="reset">
                    <input type="hidden" name="user_id" value="<?= (int)$r['id'] ?>">
                    <input type="password" name="new_password" placeholder="password baru" required>
                    <button class="btn btn-outline btn-small" type="submit">Reset</button>
                  </form>

                  <!-- Delete (permanen) -->
                  <form method="post" onsubmit="return confirm('Hapus user ini PERMANEN? Tindakan tidak bisa dibatalkan.');">
                    <input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" value="<?= (int)$r['id'] ?>">
                    <input type="text" name="confirm_delete" placeholder="ketik DELETE" required>
                    <button class="btn btn-danger btn-small" type="submit">Hapus</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="muted" style="margin-top:10px;font-size:13px;">
    </div>
  </div>
</div>

<?php require __DIR__ . '/../publik/partials/admin_layout_bottom.php'; ?>

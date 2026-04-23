<?php
declare(strict_types=1);
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';
require_role(['developer']);

$error=''; $ok='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  csrf_verify();
  $name=trim($_POST['name']??''); $email=trim($_POST['email']??''); $pass=(string)($_POST['password']??'');
  if ($name===''||$email===''||$pass==='') $error='Semua field wajib diisi.';
  else {
    try {
      $pdo->prepare("INSERT INTO users(name,email,password_hash,role,created_by) VALUES(?,?,?,?,?)")
          ->execute([$name,$email,password_hash($pass,PASSWORD_DEFAULT),'admin',auth_user()['id']]);
      $ok='Admin berhasil dibuat.';
    } catch (PDOException $e) { $error = str_contains($e->getMessage(),'Duplicate')?'Email sudah dipakai.':'Gagal membuat admin.'; }
  }
}
$admins=$pdo->query("SELECT id,name,email,is_active FROM users WHERE role='admin' ORDER BY id DESC")->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><title>Buat Admin</title></head>
<body>
<p><a href="dev_dashboard.php">← Developer</a></p>
<h2>Buat Admin</h2>
<?php if($error):?><p style="color:red;"><?=htmlspecialchars($error)?></p><?php endif;?>
<?php if($ok):?><p style="color:green;"><?=htmlspecialchars($ok)?></p><?php endif;?>
<form method="post">
  <input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>">
  <div><label>Nama</label><br><input name="name" required></div>
  <div><label>Email</label><br><input type="email" name="email" required></div>
  <div><label>Password</label><br><input type="password" name="password" required></div>
  <button type="submit">Buat</button>
</form>

<h3>Daftar Admin</h3>
<table border="1" cellpadding="6" cellspacing="0">
<tr><th>ID</th><th>Nama</th><th>Email</th><th>Aktif</th></tr>
<?php foreach($admins as $a): ?>
<tr>
  <td><?= (int)$a['id'] ?></td>
  <td><?= htmlspecialchars($a['name']) ?></td>
  <td><?= htmlspecialchars($a['email']) ?></td>
  <td><?= (int)$a['is_active']? 'Ya':'Tidak' ?></td>
</tr>
<?php endforeach; if(!$admins): ?><tr><td colspan="4">Belum ada admin.</td></tr><?php endif; ?>
</table>
</body></html>

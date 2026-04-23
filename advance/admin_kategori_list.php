<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';
require_role(['admin']);

$appName='MultiPOS';
$pageTitle='Kategori';
$activeMenu='menu_kategori';
$adminId=(int)auth_user()['id'];

$st = $pdo->prepare("SELECT id FROM stores WHERE owner_admin_id=? AND is_active=1 LIMIT 1");
$st->execute([$adminId]);
$store = $st->fetch(PDO::FETCH_ASSOC);
if (!$store) { http_response_code(400); exit('Admin belum terhubung ke toko.'); }
$storeId=(int)$store['id'];

$dbName = (string)$pdo->query("SELECT DATABASE()")->fetchColumn();

function table_exists(PDO $pdo, string $dbName, string $table): bool {
  $q=$pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=? LIMIT 1");
  $q->execute([$dbName,$table]); return (bool)$q->fetchColumn();
}
function columns_of(PDO $pdo, string $dbName, string $table): array {
  $q=$pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? ORDER BY ORDINAL_POSITION");
  $q->execute([$dbName,$table]); return array_map(fn($r)=>(string)$r['COLUMN_NAME'],$q->fetchAll(PDO::FETCH_ASSOC));
}
function has_col(array $cols, string $c): bool { return in_array($c,$cols,true); }

if (!table_exists($pdo,$dbName,'categories')) { http_response_code(400); exit("Tabel 'categories' tidak ditemukan."); }
$catCols = columns_of($pdo,$dbName,'categories');

$error = (string)($_GET['err'] ?? '');
$ok    = (string)($_GET['ok'] ?? '');

if ($_SERVER['REQUEST_METHOD']==='POST') {
  csrf_verify();
  $act = (string)($_POST['action'] ?? '');

  if ($act==='create') {
    $name = trim((string)($_POST['name'] ?? ''));
    if ($name==='') { header('Location: admin_kategori_list.php?err='.urlencode('Nama wajib diisi.')); exit; }

    $cols=['name']; $vals=[$name];
    if (has_col($catCols,'store_id')) { $cols[]='store_id'; $vals[]=$storeId; }
    if (has_col($catCols,'is_active')) { $cols[]='is_active'; $vals[]=1; }
    if (has_col($catCols,'created_at')) $cols[]='created_at';
    if (has_col($catCols,'updated_at')) $cols[]='updated_at';

    $ph=[];
    foreach($cols as $c){ $ph[] = ($c==='created_at' || $c==='updated_at') ? 'NOW()' : '?'; }

    $sql="INSERT INTO categories(".implode(',',array_map(fn($c)=>"`$c`",$cols)).") VALUES(".implode(',',$ph).")";
    try{ $pdo->prepare($sql)->execute($vals); header('Location: admin_kategori_list.php?ok='.urlencode('Kategori ditambahkan.')); exit; }
    catch(Throwable $e){ header('Location: admin_kategori_list.php?err='.urlencode('Gagal tambah kategori.')); exit; }
  }

  if ($act==='update') {
    $id=(int)($_POST['id'] ?? 0);
    $name=trim((string)($_POST['name'] ?? ''));
    if ($id<=0 || $name==='') { header('Location: admin_kategori_list.php?err='.urlencode('Data tidak valid.')); exit; }

    $where="id=?";
    $params=[$name,$id];
    if (has_col($catCols,'store_id')) { $where.=" AND store_id=?"; $params[]=$storeId; }

    $sql="UPDATE categories SET name=?";
    if (has_col($catCols,'updated_at')) $sql.=", updated_at=NOW()";
    $sql.=" WHERE $where LIMIT 1";

    try{ $pdo->prepare($sql)->execute($params); header('Location: admin_kategori_list.php?ok='.urlencode('Kategori diupdate.')); exit; }
    catch(Throwable $e){ header('Location: admin_kategori_list.php?err='.urlencode('Gagal update kategori.')); exit; }
  }

  if ($act==='delete') {
    $id=(int)($_POST['id'] ?? 0);
    if ($id<=0) { header('Location: admin_kategori_list.php?err='.urlencode('Data tidak valid.')); exit; }

    $where="id=?";
    $params=[$id];
    if (has_col($catCols,'store_id')) { $where.=" AND store_id=?"; $params[]=$storeId; }

    if (has_col($catCols,'is_active')) {
      $sql="UPDATE categories SET is_active=0";
      if (has_col($catCols,'updated_at')) $sql.=", updated_at=NOW()";
      $sql.=" WHERE $where LIMIT 1";
    } else {
      $sql="DELETE FROM categories WHERE $where LIMIT 1";
    }

    try{ $pdo->prepare($sql)->execute($params); header('Location: admin_kategori_list.php?ok='.urlencode('Kategori dihapus.')); exit; }
    catch(Throwable $e){ header('Location: admin_kategori_list.php?err='.urlencode('Gagal hapus kategori.')); exit; }
  }

  header('Location: admin_kategori_list.php'); exit;
}

// list
$sql="SELECT id,name FROM categories WHERE 1=1";
$params=[];
if (has_col($catCols,'store_id')) { $sql.=" AND store_id=?"; $params[]=$storeId; }
if (has_col($catCols,'is_active')) $sql.=" AND is_active=1";
$sql.=" ORDER BY name ASC";
$q=$pdo->prepare($sql); $q->execute($params);
$rows=$q->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/../publik/partials/admin_layout_top.php';
?>

<style>
  .wrap{max-width:1100px;}
  .muted{color:#64748b}
  .panel{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:16px;}
  input{padding:11px 12px;border-radius:12px;border:1px solid #e2e8f0;width:100%}
  table{width:100%;border-collapse:collapse;margin-top:10px}
  th,td{border-bottom:1px solid #f1f5f9;padding:10px 8px;font-size:13px}
  th{background:#f8fafc;color:#475569;font-weight:900;font-size:11px;letter-spacing:.06em}
  .btn{padding:10px 14px;border-radius:12px;border:0;background:#0b1220;color:#fff;font-weight:900;cursor:pointer}
  .btn-danger{padding:8px 10px;border-radius:10px;border:1px solid #fecaca;background:#fff;color:#ef4444;cursor:pointer}
  .btn-ghost{padding:8px 10px;border-radius:10px;border:1px solid #e2e8f0;background:#fff;cursor:pointer}
</style>

<div class="wrap">
  <h1 style="margin:0 0 6px;">Kategori</h1>
  <div class="muted">Tambah & kelola kategori produk.</div>

  <?php if($error):?><div style="color:#ef4444;margin:10px 0;"><?=htmlspecialchars($error)?></div><?php endif;?>
  <?php if($ok):?><div style="color:#16a34a;margin:10px 0;"><?=htmlspecialchars($ok)?></div><?php endif;?>

  <div class="panel" style="margin-top:14px;">
    <h3 style="margin:0 0 10px;">Tambah Kategori</h3>
    <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="action" value="create">
      <input name="name" placeholder="Contoh: Makanan, Minuman" required style="max-width:360px;">
      <button class="btn" type="submit">Tambah</button>
    </form>

    <h3 style="margin:18px 0 10px;">Daftar Kategori</h3>
    <table>
      <tr>
        <th>NAMA</th>
        <th style="width:260px;">AKSI</th>
      </tr>
      <?php foreach($rows as $r): ?>
        <tr>
          <td><?= htmlspecialchars((string)$r['name']) ?></td>
          <td>
            <form method="post" style="display:inline;">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <input name="name" value="<?= htmlspecialchars((string)$r['name']) ?>" style="width:200px;">
              <button class="btn-ghost" type="submit">Simpan</button>
            </form>

            <form method="post" style="display:inline;margin-left:8px;">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn-danger" type="submit" onclick="return confirm('Hapus kategori ini?')">Hapus</button>
            </form>
          </td>
        </tr>
      <?php endforeach; if(!$rows): ?>
        <tr><td colspan="2" class="muted">Belum ada kategori.</td></tr>
      <?php endif; ?>
    </table>
  </div>

  <div style="margin-top:12px;">
    <a class="muted" href="admin_menu_kategori.php" style="text-decoration:none;">← Kembali</a>
  </div>
</div>

<?php require __DIR__ . '/../publik/partials/admin_layout_bottom.php'; ?>

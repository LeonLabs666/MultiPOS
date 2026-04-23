<?php
declare(strict_types=1);
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';
require_role(['admin']);

$appName='MultiPOS'; $pageTitle='Kategori'; $activeMenu='kategori';

$adminId=(int)auth_user()['id'];
$st=$pdo->prepare("SELECT id,name FROM stores WHERE owner_admin_id=? AND is_active=1 LIMIT 1");
$st->execute([$adminId]); $store=$st->fetch();
if(!$store){ http_response_code(400); exit('Admin belum punya toko.'); }
$storeId=(int)$store['id']; $storeName=$store['name'];

$error=''; $ok='';

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf_verify();
  $act=$_POST['action']??'';

  if($act==='create'){
    $name=trim($_POST['name']??'');
    if($name==='') $error='Nama kategori wajib.';
    else{
      // batasi panjang biar aman
      if (mb_strlen($name) > 100) $name = mb_substr($name, 0, 100);

      try{
        $pdo->prepare("INSERT INTO categories(store_id,name,is_active) VALUES(?,?,1)")
            ->execute([$storeId,$name]);
        $ok='Kategori ditambah.';
      }catch(PDOException $e){
        $error=str_contains($e->getMessage(),'Duplicate')?'Kategori sudah ada.':'Gagal tambah kategori.';
      }
    }
  }

  if($act==='toggle'){
    $id=(int)($_POST['id']??0);
    $q=$pdo->prepare("SELECT is_active FROM categories WHERE id=? AND store_id=? LIMIT 1");
    $q->execute([$id,$storeId]); $row=$q->fetch();
    if(!$row) $error='Kategori tidak ditemukan.';
    else{
      $new=((int)$row['is_active']===1)?0:1;
      $pdo->prepare("UPDATE categories SET is_active=? WHERE id=? AND store_id=?")
          ->execute([$new,$id,$storeId]);
      $ok='Status kategori diubah.';
    }
  }

  // HAPUS PERMANEN
  if($act==='delete'){
    $id=(int)($_POST['id']??0);
    $confirm=trim((string)($_POST['confirm']??''));

    if($confirm !== 'HAPUS'){
      $error='Untuk menghapus permanen, ketik HAPUS.';
    } else {
      // pastikan kategori milik store ini
      $q=$pdo->prepare("SELECT id,name FROM categories WHERE id=? AND store_id=? LIMIT 1");
      $q->execute([$id,$storeId]); $cat=$q->fetch(PDO::FETCH_ASSOC);
      if(!$cat){
        $error='Kategori tidak ditemukan.';
      } else {
        // cek apakah kategori masih dipakai produk
        $ck=$pdo->prepare("SELECT COUNT(*) c FROM products WHERE store_id=? AND category_id=?");
        $ck->execute([$storeId,$id]);
        $used=(int)($ck->fetchColumn() ?? 0);

        if($used > 0){
          $error='Kategori masih dipakai oleh '.$used.' produk. Pindahkan produk ke kategori lain dulu, baru hapus.';
        } else {
          $pdo->prepare("DELETE FROM categories WHERE id=? AND store_id=? LIMIT 1")
              ->execute([$id,$storeId]);
          $ok='Kategori dihapus permanen.';
        }
      }
    }
  }
}

$list=$pdo->prepare("SELECT id,name,is_active,created_at FROM categories WHERE store_id=? ORDER BY id DESC");
$list->execute([$storeId]); $rows=$list->fetchAll();

require __DIR__ . '/../publik/partials/admin_layout_top.php';
?>

<style>
  .wrap{max-width:1100px;}
  .muted{color:#64748b}
  .card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:14px;}
  .btn{padding:10px 14px;border-radius:12px;border:1px solid #0b1220;background:#0b1220;color:#fff;font-weight:900;cursor:pointer}
  .btn-ghost{padding:10px 14px;border-radius:12px;border:1px solid #e2e8f0;background:#fff;color:#0f172a;font-weight:900;cursor:pointer}
  .btn-danger{padding:10px 14px;border-radius:12px;border:1px solid #fecaca;background:#fff;color:#ef4444;font-weight:900;cursor:pointer}
  input{padding:10px 12px;border-radius:12px;border:1px solid #e2e8f0;width:100%}
  label{display:block;font-size:12px;color:#64748b;margin-bottom:4px}

  table{width:100%;border-collapse:collapse}
  th,td{border-bottom:1px solid #f1f5f9;padding:10px 8px;text-align:left;font-size:13px;vertical-align:top}
  th{color:#64748b;font-weight:900;font-size:11px;letter-spacing:.06em;text-transform:uppercase}
  .pill{display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;border:1px solid #e2e8f0;font-weight:900}
  .pill.on{background:#ecfdf5;border-color:#bbf7d0;color:#166534}
  .pill.off{background:#fef2f2;border-color:#fecaca;color:#991b1b}

  .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;align-items:start}
  @media(max-width: 900px){ .grid{grid-template-columns:1fr} }

  /* Mobile list */
  .m-list{display:none}
  @media(max-width: 700px){
    .desktop-table{display:none}
    .m-list{display:flex;flex-direction:column;gap:10px}
    .m-item{border:1px solid #e2e8f0;border-radius:14px;padding:12px;background:#fff;display:flex;flex-direction:column;gap:8px}
    .m-top{display:flex;justify-content:space-between;gap:10px;align-items:flex-start}
    .m-name{font-weight:1000}
    .m-actions{display:flex;gap:8px;flex-wrap:wrap}
    .m-meta{color:#64748b;font-size:12px}
    .m-confirm{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
    .m-confirm input{max-width:160px}
    .btn, .btn-ghost, .btn-danger{padding:9px 12px;border-radius:12px}
  }
</style>

<div class="wrap">
  <h1 style="margin:0 0 6px;">Kategori</h1>
  <div class="muted" style="margin-bottom:12px;">Toko: <b><?= h($storeName) ?></b></div>

  <?php if($error):?>
    <div class="card" style="border-color:#fecaca;background:#fff;margin-bottom:12px;color:#991b1b;">
      <?= h($error) ?>
    </div>
  <?php endif;?>
  <?php if($ok):?>
    <div class="card" style="border-color:#bbf7d0;background:#fff;margin-bottom:12px;color:#166534;">
      <?= h($ok) ?>
    </div>
  <?php endif;?>

  <div class="grid">
    <!-- Form tambah -->
    <div class="card">
      <div style="font-weight:1000;margin-bottom:10px;">Tambah Kategori</div>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="create">

        <label>Nama Kategori</label>
        <input name="name" required placeholder="contoh: Minuman, Makanan, Dessert">

        <div style="margin-top:10px;display:flex;gap:10px;flex-wrap:wrap;">
          <button class="btn" type="submit">Tambah</button>
        </div>
      </form>
    </div>

    <!-- Tips -->
    <div class="card">
      <div style="font-weight:1000;margin-bottom:6px;">Catatan</div>
      <div class="muted" style="font-size:13px;line-height:1.5;">
        • Nonaktifkan jika kategori sementara tidak dipakai.<br>
        • Hapus permanen hanya bisa kalau kategori <b>tidak dipakai</b> oleh produk.<br>
        • Untuk menghapus permanen, wajib ketik <b>HAPUS</b> supaya tidak salah pencet.
      </div>
    </div>
  </div>

  <div class="card" style="margin-top:12px;">
    <div style="display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:wrap;">
      <div style="font-weight:1000;">Daftar Kategori</div>
      <div class="muted" style="font-size:12px;"><?= count($rows) ?> kategori</div>
    </div>

    <!-- Desktop table -->
    <div class="desktop-table" style="overflow:auto;margin-top:10px;">
      <table style="min-width:560px;">
        <thead>
          <tr>
            <th style="width:70px;">ID</th>
            <th>Nama</th>
            <th style="width:120px;">Status</th>
            <th style="width:360px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if(!$rows): ?>
            <tr><td colspan="4" class="muted" style="padding:12px;">Belum ada kategori.</td></tr>
          <?php else: foreach($rows as $r): ?>
            <?php $isOn = (int)$r['is_active']===1; ?>
            <tr>
              <td>#<?= (int)$r['id'] ?></td>
              <td style="font-weight:900;"><?= h($r['name']) ?></td>
              <td>
                <span class="pill <?= $isOn?'on':'off' ?>">
                  <?= $isOn?'Aktif':'Nonaktif' ?>
                </span>
              </td>
              <td>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                  <form method="post" style="display:inline">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="<?= $isOn?'btn-ghost':'btn' ?>" type="submit">
                      <?= $isOn?'Nonaktifkan':'Aktifkan' ?>
                    </button>
                  </form>

                  <form method="post" style="display:inline">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <input name="confirm" placeholder="ketik HAPUS" style="max-width:160px;">
                    <button class="btn-danger" type="submit"
                      onclick="return confirm('Hapus permanen kategori ini? (tidak bisa dibatalkan)')">
                      Hapus Permanen
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Mobile list -->
    <div class="m-list" style="margin-top:10px;">
      <?php if(!$rows): ?>
        <div class="muted">Belum ada kategori.</div>
      <?php else: foreach($rows as $r): ?>
        <?php $isOn = (int)$r['is_active']===1; ?>
        <div class="m-item">
          <div class="m-top">
            <div>
              <div class="m-name"><?= h($r['name']) ?></div>
              <div class="m-meta">ID: #<?= (int)$r['id'] ?> •
                <span class="pill <?= $isOn?'on':'off' ?>"><?= $isOn?'Aktif':'Nonaktif' ?></span>
              </div>
            </div>
          </div>

          <div class="m-actions">
            <form method="post" style="display:inline">
              <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="<?= $isOn?'btn-ghost':'btn' ?>" type="submit">
                <?= $isOn?'Nonaktifkan':'Aktifkan' ?>
              </button>
            </form>

            <form method="post" style="display:inline">
              <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <div class="m-confirm">
                <input name="confirm" placeholder="ketik HAPUS">
                <button class="btn-danger" type="submit"
                  onclick="return confirm('Hapus permanen kategori ini? (tidak bisa dibatalkan)')">
                  Hapus
                </button>
              </div>
            </form>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../publik/partials/admin_layout_bottom.php'; ?>

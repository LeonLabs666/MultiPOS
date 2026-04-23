<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';

require_role(['admin']);

$appName='MultiPOS';
$pageTitle='Stok Opname';
$activeMenu='opname';

$adminId=(int)auth_user()['id'];

$st=$pdo->prepare("SELECT id,name FROM stores WHERE owner_admin_id=? AND is_active=1 LIMIT 1");
$st->execute([$adminId]);
$store=$st->fetch();
if(!$store){ http_response_code(400); exit('Admin belum punya toko.'); }

$storeId=(int)$store['id'];
$storeName=$store['name'];

if(!isset($_SESSION['opname_cart'])) $_SESSION['opname_cart'] = []; // product_id => physical_stock (int|null)

$error=''; $ok='';

function fetch_products(PDO $pdo,int $storeId,string $q): array {
  $params=[$storeId];
  $where="store_id=? AND is_active=1";
  if($q!==''){
    $where.=" AND (name LIKE ? OR sku LIKE ?)";
    $like="%{$q}%"; $params[]=$like; $params[]=$like;
  }
  $s=$pdo->prepare("SELECT id,sku,name,stock FROM products WHERE $where ORDER BY id DESC LIMIT 50");
  $s->execute($params);
  return $s->fetchAll();
}

function fetch_cart_rows(PDO $pdo,int $storeId,array $cart): array {
  if(!$cart) return [];
  $ids=array_keys($cart);
  $in=implode(',', array_fill(0,count($ids),'?'));
  $params=array_merge([$storeId], $ids);

  $q=$pdo->prepare("SELECT id,sku,name,stock FROM products WHERE store_id=? AND id IN ($in)");
  $q->execute($params);
  $rows=$q->fetchAll();

  // urut sesuai cart
  usort($rows, fn($a,$b)=> array_search($a['id'],$ids) <=> array_search($b['id'],$ids));
  return $rows;
}

$q = trim($_GET['q'] ?? '');

// HANDLE ACTIONS
if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf_verify();
  $act=$_POST['action'] ?? '';

  if($act==='add'){
    $pid=(int)($_POST['product_id'] ?? 0);
    if($pid>0){
      if(!array_key_exists($pid, $_SESSION['opname_cart'])){
        $_SESSION['opname_cart'][$pid] = null; // belum isi stok fisik
      }
    }
  }

  if($act==='remove'){
    $pid=(int)($_POST['product_id'] ?? 0);
    unset($_SESSION['opname_cart'][$pid]);
  }

  if($act==='clear'){
    $_SESSION['opname_cart'] = [];
  }

  if($act==='save'){
    $note=trim($_POST['note'] ?? '');
    $phys=$_POST['physical'] ?? []; // physical[product_id]=stok

    // update cart dengan input user
    foreach($_SESSION['opname_cart'] as $pid=>$v){
      $val = $phys[$pid] ?? null;
      $_SESSION['opname_cart'][$pid] = ($val===null || $val==='') ? null : (int)$val;
    }

    // validasi: semua item wajib punya stok fisik
    foreach($_SESSION['opname_cart'] as $pid=>$v){
      if($v===null){
        $error='Masih ada item yang stok fisiknya kosong.';
        break;
      }
      if($v < 0){
        $error='Stok fisik tidak boleh negatif.';
        break;
      }
    }

    if($error===''){
      $cart=$_SESSION['opname_cart'];
      if(!$cart){ $error='Draft opname kosong.'; }
      else{
        $rows = fetch_cart_rows($pdo,$storeId,$cart);
        if(!$rows){ $error='Produk draft tidak valid.'; }
        else{
          try{
            $pdo->beginTransaction();

            $pdo->prepare("INSERT INTO stock_opnames(store_id,admin_id,note) VALUES (?,?,?)")
              ->execute([$storeId,$adminId,$note!==''?$note:null]);
            $opnameId=(int)$pdo->lastInsertId();

            $ins=$pdo->prepare("
              INSERT INTO stock_opname_items(opname_id,product_id,sku,name,stock_before,stock_after,diff)
              VALUES (?,?,?,?,?,?,?)
            ");
            $upd=$pdo->prepare("UPDATE products SET stock=? WHERE id=? AND store_id=?");

            foreach($rows as $r){
              $pid=(int)$r['id'];
              $before=(int)$r['stock'];
              $after=(int)$cart[$pid];
              $diff=$after-$before;

              $ins->execute([$opnameId,$pid,$r['sku'],$r['name'],$before,$after,$diff]);
              $upd->execute([$after,$pid,$storeId]);
            }

            $pdo->commit();
            $_SESSION['opname_cart'] = [];
            header("Location: admin_stock_opname_detail.php?id={$opnameId}");
            exit;

          }catch(Throwable $e){
            if($pdo->inTransaction()) $pdo->rollBack();
            $error='Gagal simpan opname.';
          }
        }
      }
    }
  }

  header('Location: admin_stock_opname.php' . ($q!==''?('?q='.urlencode($q)):''));
  exit;
}

// DATA
$products = fetch_products($pdo,$storeId,$q);
$cartRows = fetch_cart_rows($pdo,$storeId,$_SESSION['opname_cart']);

// riwayat 10 terakhir
$hist=$pdo->prepare("
  SELECT o.id,o.created_at,o.note,
         (SELECT COUNT(*) FROM stock_opname_items i WHERE i.opname_id=o.id) AS items_count
  FROM stock_opnames o
  WHERE o.store_id=?
  ORDER BY o.id DESC
  LIMIT 10
");
$hist->execute([$storeId]);
$rows=$hist->fetchAll();

require __DIR__ . '/../publik/partials/admin_layout_top.php';
?>
<h1 style="margin:0 0 10px;">Stok Opname (Multi-Item)</h1>
<?php if($error):?><p style="color:#ef4444;"><?= htmlspecialchars($error) ?></p><?php endif; ?>

<form method="get" style="margin:0 0 12px;display:flex;gap:8px;align-items:center;">
  <input name="q" placeholder="Cari SKU / nama..." value="<?= htmlspecialchars($q) ?>" style="padding:8px;width:320px;">
  <button type="submit">Cari</button>
  <?php if($q!==''): ?><a href="admin_stock_opname.php">Reset</a><?php endif; ?>
</form>

<div style="display:flex;gap:14px;align-items:flex-start;">
  <!-- Produk -->
  <div style="flex:2;background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:12px;">
    <h3 style="margin:0 0 10px;">Produk</h3>
    <table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;">
      <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
        <th align="left">SKU</th><th align="left">Nama</th><th align="center">Stok</th><th align="left">Aksi</th>
      </tr>
      <?php foreach($products as $p): $pid=(int)$p['id']; ?>
        <tr style="border-bottom:1px solid #eef2f7;">
          <td><?= htmlspecialchars($p['sku'] ?? '-') ?></td>
          <td><?= htmlspecialchars($p['name']) ?></td>
          <td align="center"><?= (int)$p['stock'] ?></td>
          <td>
            <form method="post" style="display:inline">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
              <input type="hidden" name="action" value="add">
              <input type="hidden" name="product_id" value="<?= $pid ?>">
              <button type="submit" <?= array_key_exists($pid,$_SESSION['opname_cart'])?'disabled':'' ?>>
                <?= array_key_exists($pid,$_SESSION['opname_cart'])?'Sudah ditambah':'Tambah' ?>
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; if(!$products): ?>
        <tr><td colspan="4" style="padding:12px;color:#64748b;">Tidak ada produk.</td></tr>
      <?php endif; ?>
    </table>
  </div>

  <!-- Draft Opname -->
  <div style="flex:1;background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:12px;min-width:420px;">
    <h3 style="margin:0 0 10px;">Draft Opname</h3>

    <?php if(!$cartRows): ?>
      <p style="color:#64748b;">Belum ada item di draft.</p>
    <?php else: ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="save">

        <table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;">
          <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
            <th align="left">Produk</th><th align="center">Sistem</th><th align="center">Fisik</th><th></th>
         
        </tr>

          <?php foreach($cartRows as $r):
            $pid=(int)$r['id'];
            $v=$_SESSION['opname_cart'][$pid];
          ?>
            <tr style="border-bottom:1px solid #eef2f7;">
              <td>
                <?= htmlspecialchars($r['name']) ?><br>
                <small>SKU: <?= htmlspecialchars($r['sku'] ?? '-') ?></small>
              </td>
              <td align="center"><?= (int)$r['stock'] ?></td>
              <td align="center">
                <input type="number" name="physical[<?= $pid ?>]" min="0"
                  value="<?= $v===null?'':(int)$v ?>" style="width:90px;">
              </td>
              <td align="center">
                <form method="post">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                  <input type="hidden" name="action" value="remove">
                  <input type="hidden" name="product_id" value="<?= $pid ?>">
                  <button type="submit">x</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </table>

        <div style="margin-top:10px;">
          <label>Catatan (opsional)</label><br>
          <input name="note" style="width:100%;padding:8px;" placeholder="contoh: opname rak depan">
        </div>

        <button type="submit" style="margin-top:10px;">Simpan Opname</button>
      </form>

      <form method="post" style="margin-top:10px;">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="clear">
        <button type="submit">Kosongkan Draft</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<h3 style="margin-top:16px;">Riwayat Opname (10 terakhir)</h3>
<table width="100%" cellpadding="8" cellspacing="0"
  style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;border-collapse:collapse;">
  <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
    <th align="left">Waktu</th><th align="left">Catatan</th><th align="center">Item</th><th align="left">Aksi</th>
  </tr>
  <?php foreach($rows as $r): ?>
    <tr style="border-bottom:1px solid #eef2f7;">
      <td><?= htmlspecialchars($r['created_at']) ?></td>
      <td><?= htmlspecialchars($r['note'] ?? '-') ?></td>
      <td align="center"><?= (int)$r['items_count'] ?></td>
      <td><a href="admin_stock_opname_detail.php?id=<?= (int)$r['id'] ?>">Detail</a></td>
    </tr>
  <?php endforeach; if(!$rows): ?>
    <tr><td colspan="4" style="padding:14px;color:#64748b;">Belum ada opname.</td></tr>
  <?php endif; ?>
</table>

<?php require __DIR__ . '/../publik/partials/admin_layout_bottom.php'; ?>

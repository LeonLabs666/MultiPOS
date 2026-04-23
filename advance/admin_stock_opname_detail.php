<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';

require_role(['admin']);

$adminId=(int)auth_user()['id'];

$st=$pdo->prepare("SELECT id,name FROM stores WHERE owner_admin_id=? AND is_active=1 LIMIT 1");
$st->execute([$adminId]);
$store=$st->fetch();
if(!$store){ http_response_code(400); exit('Admin belum punya toko.'); }
$storeId=(int)$store['id'];

$id=(int)($_GET['id'] ?? 0);
if($id<=0){ http_response_code(400); exit('ID invalid'); }

$head=$pdo->prepare("
  SELECT o.*, u.name AS admin_name
  FROM stock_opnames o
  JOIN users u ON u.id=o.admin_id
  WHERE o.id=? AND o.store_id=?
  LIMIT 1
");
$head->execute([$id,$storeId]);
$op=$head->fetch();
if(!$op){ http_response_code(404); exit('Opname tidak ditemukan.'); }

$itemQ=$pdo->prepare("
  SELECT sku,name,stock_before,stock_after,diff
  FROM stock_opname_items
  WHERE opname_id=?
  ORDER BY id
");
$itemQ->execute([$id]);
$items=$itemQ->fetchAll();
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Detail Opname</title></head>
<body>
<h2>Detail Stok Opname</h2>
<p><a href="admin_stock_opname.php">← Kembali</a></p>

<div style="border:1px solid #ddd;padding:12px;max-width:760px;">
  <div>ID: <b>#<?= (int)$op['id'] ?></b></div>
  <div>Waktu: <?= htmlspecialchars($op['created_at']) ?></div>
  <div>Admin: <?= htmlspecialchars($op['admin_name']) ?></div>
  <div>Catatan: <?= htmlspecialchars($op['note'] ?? '-') ?></div>
</div>

<h3>Item</h3>
<table border="1" cellpadding="6" cellspacing="0" style="max-width:760px;width:100%;border-collapse:collapse;">
  <tr><th align="left">Produk</th><th>Before</th><th>After</th><th>Selisih</th></tr>
  <?php foreach($items as $it): ?>
    <tr>
      <td><?= htmlspecialchars($it['name']) ?><br><small>SKU: <?= htmlspecialchars($it['sku'] ?? '-') ?></small></td>
      <td align="center"><?= (int)$it['stock_before'] ?></td>
      <td align="center"><?= (int)$it['stock_after'] ?></td>
      <td align="center">
        <?php $d=(int)$it['diff']; ?>
        <?php if($d===0): ?><span style="color:green;">0</span>
        <?php elseif($d>0): ?><span style="color:green;">+<?= $d ?></span>
        <?php else: ?><span style="color:red;"><?= $d ?></span>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; if(!$items): ?>
    <tr><td colspan="4">Tidak ada item.</td></tr>
  <?php endif; ?>
</table>
</body>
</html>

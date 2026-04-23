<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';
require_role(['developer']);

csrf_verify();

function back(int $id,string $msg,string $t='err'){
  header('Location: dev_store_detail.php?id='.$id.'&'.$t.'='.urlencode($msg));
  exit;
}

$storeId=max(0,(int)($_POST['store_id']??0));
$confirm=trim($_POST['confirm']??'');
$confirm2=trim($_POST['confirm2']??'');

if($storeId<=0) back(0,'Store ID tidak valid');

if(strcasecmp($confirm,'HAPUS')!==0||$confirm2!==(string)$storeId){
  back($storeId,'Konfirmasi gagal');
}

$st=$pdo->prepare("SELECT * FROM stores WHERE id=?");
$st->execute([$storeId]);
$store=$st->fetch();

if(!$store) back($storeId,'Store tidak ditemukan');

$ownerId=(int)($store['owner_admin_id']??0);

try{
  $pdo->beginTransaction();

  // hapus semua tabel yang ada store_id
  $q=$pdo->query("
    SELECT TABLE_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE()
    AND COLUMN_NAME='store_id'
  ");

  foreach($q->fetchAll(PDO::FETCH_COLUMN) as $t){
    if($t==='stores') continue;
    $pdo->prepare("DELETE FROM `$t` WHERE store_id=?")->execute([$storeId]);
  }

  // hapus users terkait
  if($ownerId>0){
    $pdo->prepare("DELETE FROM users WHERE created_by=?")->execute([$ownerId]);
    $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$ownerId]);
  }

  // hapus store
  $pdo->prepare("DELETE FROM stores WHERE id=?")->execute([$storeId]);

  $pdo->commit();

}catch(Throwable $e){
  if($pdo->inTransaction())$pdo->rollBack();
  back($storeId,$e->getMessage());
}

header('Location: dev_stores.php?ok=Store '.$storeId.' dihapus permanen');
exit;

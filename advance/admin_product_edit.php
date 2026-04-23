<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';
require __DIR__ . '/../config/images.php';

require_role(['admin']);

$appName='MultiPOS'; $pageTitle='Edit Produk'; $activeMenu='produk';

$adminId=(int)auth_user()['id'];
$st=$pdo->prepare("SELECT id,name FROM stores WHERE owner_admin_id=? AND is_active=1 LIMIT 1");
$st->execute([$adminId]); $store=$st->fetch();
if(!$store){ http_response_code(400); exit('Admin belum punya toko.'); }
$storeId=(int)$store['id']; $storeName=$store['name'];

$id=(int)($_GET['id'] ?? 0);
if($id<=0){ http_response_code(400); exit('ID produk invalid.'); }

function get_product(PDO $pdo,int $storeId,int $id): ?array{
  $q=$pdo->prepare("SELECT * FROM products WHERE id=? AND store_id=? LIMIT 1");
  $q->execute([$id,$storeId]); $r=$q->fetch();
  return $r?:null;
}

/**
 * Ubah path gambar dari database menjadi URL yang benar untuk halaman di folder /advance.
 */
function product_img_src(?string $path): string {
  $path = trim((string)$path);

  if ($path === '') {
    return '../publik/assets/no-image.png';
  }

  if (preg_match('~^(https?:)?//~i', $path)) {
    return $path;
  }

  return '../' . ltrim($path, '/');
}

$error=''; $ok='';
$presetImages = list_preset_images();

$cats=$pdo->prepare("SELECT id,name FROM categories WHERE store_id=? AND is_active=1 ORDER BY name");
$cats->execute([$storeId]); $catRows=$cats->fetchAll(PDO::FETCH_ASSOC);

$product=get_product($pdo,$storeId,$id);
if(!$product){ http_response_code(404); exit('Produk tidak ditemukan.'); }

if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf_verify();

  $name=trim((string)($_POST['name'] ?? ''));
  $cat=(int)($_POST['category_id'] ?? 0); $cat=$cat>0?$cat:null;
  $price=max(0,(int)($_POST['price'] ?? 0));
  $stock=(int)($_POST['stock'] ?? 0);

  $discountActive = (int)($_POST['discount_is_active'] ?? 0) === 1 ? 1 : 0;
  $discountPercent = (int)($_POST['discount_percent'] ?? 0);
  $discountPercent = max(0, min(100, $discountPercent));
  if ($discountActive === 0) { $discountPercent = 0; }

  $active=(int)($_POST['is_active'] ?? 0)===1 ? 1 : 0;

  // gambar (opsional)
  $removeImg = (int)($_POST['remove_image'] ?? 0) === 1;
  $preset = trim((string)($_POST['preset_image'] ?? ''));
  $uploaded = save_uploaded_product_image('image_upload');

  $newImagePath = $product['image_path'] ?? null;
  if ($removeImg) {
    $newImagePath = null;
  }
  if ($uploaded) {
    $newImagePath = $uploaded; // upload menang
  } elseif (!$removeImg && in_array($preset, $presetImages, true)) {
    $newImagePath = $preset;
  }

  if($name==='') {
    $error='Nama produk wajib.';
  } else {
    $pdo->prepare("
      UPDATE products
      SET name=?, category_id=?, price=?, stock=?, is_active=?, image_path=?, discount_is_active=?, discount_percent=?
      WHERE id=? AND store_id=?
    ")->execute([$name,$cat,$price,$stock,$active,$newImagePath,$discountActive,$discountPercent,$id,$storeId]);

    $ok='Produk berhasil diupdate.';
    $product=get_product($pdo,$storeId,$id);
  }
}

$currentImageSrc = !empty($product['image_path']) ? product_img_src((string)$product['image_path']) : '';

require __DIR__ . '/../publik/partials/admin_layout_top.php';
?>

<style>
  .card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:14px;max-width:820px}
  .grid2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
  .muted{color:#64748b}
  input,select{padding:10px 12px;border-radius:12px;border:1px solid #e2e8f0;width:100%}
  label{font-size:13px;color:#334155}
  .preview{
    width:120px;height:120px;object-fit:cover;border:1px solid #e2e8f0;border-radius:12px;background:#fff;
  }
  .btn{
    padding:10px 14px;border-radius:12px;border:1px solid #e2e8f0;background:#0f172a;color:#fff;
    cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:8px
  }
  .btn-outline{background:#fff;color:#0f172a}
  .hint{color:#64748b;font-size:12px;margin-top:6px}
  @media (max-width: 720px){
    .grid2{grid-template-columns:1fr}
  }
</style>

<h1 style="margin:0 0 10px;">Edit Produk</h1>

<p style="margin:0 0 12px;color:#64748b;">
  SKU: <b><?= htmlspecialchars((string)($product['sku'] ?? '-')) ?></b>
</p>

<?php if($error): ?><p style="color:#ef4444;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if($ok): ?><p style="color:#16a34a;"><?= htmlspecialchars($ok) ?></p><?php endif; ?>

<form method="post" enctype="multipart/form-data" class="card">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

  <div class="grid2">
    <div>
      <label>Nama Produk</label><br>
      <input name="name" required value="<?= htmlspecialchars((string)$product['name']) ?>">
    </div>
    <div>
      <label>Kategori</label><br>
      <select name="category_id">
        <option value="">-- tanpa kategori --</option>
        <?php foreach($catRows as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= ((int)($product['category_id'] ?? 0)===(int)$c['id'])?'selected':'' ?>>
            <?= htmlspecialchars((string)$c['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Harga</label><br>
      <input type="number" name="price" min="0" value="<?= (int)$product['price'] ?>">
    </div>
    <div>
      <label>Stok</label><br>
      <input type="number" name="stock" value="<?= (int)$product['stock'] ?>">
    </div>

    <div>
      <label>Diskon?</label><br>
      <select name="discount_is_active" id="discount_is_active">
        <option value="0" <?= ((int)($product['discount_is_active'] ?? 0)===0)?'selected':'' ?>>Tidak</option>
        <option value="1" <?= ((int)($product['discount_is_active'] ?? 0)===1)?'selected':'' ?>>Ya</option>
      </select>
    </div>

    <div>
      <label>Diskon (%)</label><br>
      <input type="number" name="discount_percent" id="discount_percent" min="0" max="100" value="<?= (int)($product['discount_percent'] ?? 0) ?>">
    </div>
  </div>

  <div style="margin-top:10px;">
    <label>
      <input type="checkbox" name="is_active" value="1" <?= ((int)$product['is_active']===1)?'checked':'' ?> style="width:auto;">
      Produk aktif
    </label>
  </div>

  <hr style="margin:14px 0;border:none;height:1px;background:#e2e8f0;">

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;align-items:start;">
    <div>
      <label>Upload gambar baru (max 2MB)</label><br>
      <input type="file" name="image_upload" id="image_upload" accept="image/png,image/jpeg,image/webp">
      <div class="hint">Kalau upload diisi, preset diabaikan.</div>
    </div>

    <div>
      <label>Pilih gambar preset</label><br>
      <select name="preset_image" id="preset_image">
        <option value="">-- tidak pakai preset --</option>
        <?php foreach($presetImages as $p): ?>
          <option value="<?= htmlspecialchars((string)$p) ?>" <?= (($product['image_path'] ?? '')===$p)?'selected':'' ?>>
            <?= htmlspecialchars(basename((string)$p)) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <div style="margin-top:8px;">
        <label>
          <input type="checkbox" name="remove_image" value="1" id="remove_image" style="width:auto;">
          Hapus gambar produk
        </label>
      </div>
    </div>
  </div>

  <div style="margin-top:12px;">
    <label>Preview</label><br>
    <img id="img_preview"
      src="<?= htmlspecialchars($currentImageSrc) ?>"
      style="<?= $currentImageSrc !== '' ? '' : 'display:none;' ?>width:120px;height:120px;object-fit:cover;border:1px solid #e2e8f0;border-radius:12px;background:#fff;"
      alt="preview"
      data-current-src="<?= htmlspecialchars($currentImageSrc) ?>"
      onerror="this.onerror=null;this.src='../publik/assets/no-image.png';this.style.display='inline-block';">
  </div>

  <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;">
    <button type="submit" class="btn">Simpan</button>
    <a href="admin_products.php" class="btn btn-outline">Kembali</a>
  </div>
</form>

<script>
  const upload = document.getElementById('image_upload');
  const preset = document.getElementById('preset_image');
  const removeImg = document.getElementById('remove_image');
  const img = document.getElementById('img_preview');
  const discActive = document.getElementById('discount_is_active');
  const discPercent = document.getElementById('discount_percent');

  function normalizeAdminImgSrc(path){
    path = (path || '').trim();
    if(!path) return '../publik/assets/no-image.png';
    if (/^(https?:)?\/\//i.test(path)) return path;
    return '../' + path.replace(/^\/+/, '');
  }

  function show(src){
    if(!src){
      img.style.display='none';
      img.src='';
      return;
    }
    img.src = src;
    img.style.display='inline-block';
  }

  function syncDiscountUI(){
    const on = (discActive.value === '1');
    discPercent.disabled = !on;
    if(!on) discPercent.value = 0;
  }

  function restoreCurrentImage(){
    const current = img.dataset.currentSrc || '';
    if(current){
      show(current);
    } else {
      show('');
    }
  }

  syncDiscountUI();
  discActive.addEventListener('change', syncDiscountUI);

  upload.addEventListener('change', () => {
    removeImg.checked = false;

    if(upload.files && upload.files[0]){
      show(URL.createObjectURL(upload.files[0]));
    } else if (preset.value) {
      show(normalizeAdminImgSrc(preset.value));
    } else {
      restoreCurrentImage();
    }
  });

  preset.addEventListener('change', () => {
    if(upload.files && upload.files.length) return; // upload menang
    removeImg.checked = false;

    if(preset.value){
      show(normalizeAdminImgSrc(preset.value));
    } else {
      restoreCurrentImage();
    }
  });

  removeImg.addEventListener('change', () => {
    if(removeImg.checked){
      preset.value = '';
      upload.value = '';
      show('');
    } else {
      if (upload.files && upload.files[0]) {
        show(URL.createObjectURL(upload.files[0]));
      } else if (preset.value) {
        show(normalizeAdminImgSrc(preset.value));
      } else {
        restoreCurrentImage();
      }
    }
  });
</script>

<?php require __DIR__ . '/../publik/partials/admin_layout_bottom.php'; ?>
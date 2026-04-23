<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';
require __DIR__ . '/../config/images.php';

require_role(['admin']);

$appName='MultiPOS'; $pageTitle='Produk'; $activeMenu='produk';

$adminId=(int)auth_user()['id'];
$st=$pdo->prepare("SELECT id,name FROM stores WHERE owner_admin_id=? AND is_active=1 LIMIT 1");
$st->execute([$adminId]); $store=$st->fetch();
if(!$store){ http_response_code(400); exit('Admin belum punya toko.'); }

$storeId=(int)$store['id'];
$storeName=$store['name'];

function generate_sku(PDO $pdo,int $storeId): string {
  $q=$pdo->prepare("SELECT MAX(CAST(sku AS UNSIGNED)) AS mx FROM products WHERE store_id=? AND sku REGEXP '^[0-9]+$'");
  $q->execute([$storeId]);
  $mx=(int)($q->fetch()['mx'] ?? 0);
  return str_pad((string)($mx+1), 3, '0', STR_PAD_LEFT);
}

/**
 * Ubah path gambar dari database menjadi URL yang benar untuk halaman di folder /advance.
 * Contoh:
 * - upload/products/a.jpg -> ../upload/products/a.jpg
 * - publik/assets/product_presets/b.png -> ../publik/assets/product_presets/b.png
 * - http://... -> tetap
 */
function product_img_src(?string $path): string {
  $path = trim((string)$path);

  if ($path === '') {
    return '../publik/assets/no-image.png';
  }

  // kalau URL penuh / protocol-relative
  if (preg_match('~^(https?:)?//~i', $path)) {
    return $path;
  }

  return '../' . ltrim($path, '/');
}

$error=''; $ok='';

$cats=$pdo->prepare("SELECT id,name FROM categories WHERE store_id=? AND is_active=1 ORDER BY name");
$cats->execute([$storeId]); $catRows=$cats->fetchAll(PDO::FETCH_ASSOC);

$presetImages = list_preset_images();

if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf_verify();
  $act=$_POST['action']??'';

  if($act==='create'){
    $name=trim((string)($_POST['name']??''));
    $cat=(int)($_POST['category_id']??0); $cat=$cat>0?$cat:null;
    $price=max(0,(int)($_POST['price']??0));
    $stock=(int)($_POST['stock']??0);

    $discountActive = (int)($_POST['discount_is_active'] ?? 0) === 1 ? 1 : 0;
    $discountPercent = (int)($_POST['discount_percent'] ?? 0);
    $discountPercent = max(0, min(100, $discountPercent));
    if ($discountActive === 0) { $discountPercent = 0; }

    // gambar: upload > preset > null
    $preset = trim((string)($_POST['preset_image'] ?? ''));
    $uploaded = save_uploaded_product_image('image_upload');
    $imagePath = $uploaded ?: (in_array($preset, $presetImages, true) ? $preset : null);

    if($name==='') $error='Nama produk wajib.';
    else{
      try{
        $sku=generate_sku($pdo,$storeId);
        $pdo->prepare("
          INSERT INTO products (store_id,category_id,sku,name,price,stock,is_active,image_path,discount_is_active,discount_percent)
          VALUES (?,?,?,?,?,?,1,?,?,?)
        ")->execute([$storeId,$cat,$sku,$name,$price,$stock,$imagePath,$discountActive,$discountPercent]);
        $ok="Produk ditambah. SKU: {$sku}";
      }catch(PDOException $e){
        $error=str_contains($e->getMessage(),'Duplicate')?'SKU bentrok. Coba tambah lagi.':'Gagal tambah produk.';
      }
    }
  }

  if($act==='toggle'){
    $id=(int)($_POST['id']??0);
    $q=$pdo->prepare("SELECT is_active FROM products WHERE id=? AND store_id=? LIMIT 1");
    $q->execute([$id,$storeId]); $row=$q->fetch(PDO::FETCH_ASSOC);
    if(!$row) $error='Produk tidak ditemukan.';
    else{
      $new=((int)$row['is_active']===1)?0:1;
      $pdo->prepare("UPDATE products SET is_active=? WHERE id=? AND store_id=?")->execute([$new,$id,$storeId]);
      $ok='Status produk diubah.';
    }
  }
}

// SEARCH
$search = trim((string)($_GET['q'] ?? ''));
$params = [$storeId];
$where = "p.store_id=?";
if($search !== ''){
  $where .= " AND (p.name LIKE ? OR p.sku LIKE ?)";
  $like = "%{$search}%";
  $params[] = $like; $params[] = $like;
}

$list=$pdo->prepare("
  SELECT p.id,p.sku,p.name,p.price,p.stock,p.is_active,p.image_path,p.discount_is_active,p.discount_percent,c.name AS cat_name
  FROM products p
  LEFT JOIN categories c ON c.id=p.category_id
  WHERE {$where}
  ORDER BY p.id DESC
");
$list->execute($params);
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
  .btn-small{padding:8px 12px;border-radius:12px;font-size:13px}
  .btn-danger{background:#ef4444;border-color:#ef4444}

  input,select{padding:10px 12px;border-radius:12px;border:1px solid #e2e8f0;width:100%}
  label{font-size:13px;color:#334155}
  .msg-err{color:#ef4444;margin:8px 0 12px}
  .msg-ok{color:#16a34a;margin:8px 0 12px}

  .head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:12px}
  h1{margin:0}

  /* Search */
  .searchbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
  .searchbar .q{flex:1;min-width:220px}
  .searchbar .actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
  .searchbar .actions .btn{min-width:110px}

  /* Form grid */
  .grid2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
  .grid3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px}
  .hr{height:1px;background:#e2e8f0;border:none;margin:14px 0}
  .hint{font-size:12px;color:#64748b;margin-top:6px}
  .preview{
    width:120px;height:120px;object-fit:cover;border:1px solid #e2e8f0;border-radius:12px;background:#fff;display:none
  }

  /* Table */
  .table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}
  table{width:100%;border-collapse:collapse;background:#fff}
  th,td{border-bottom:1px solid #f1f5f9;padding:10px 8px;text-align:left;font-size:13px;vertical-align:top}
  th{color:#64748b;font-weight:700;white-space:nowrap}
  .imgcell img{width:42px;height:42px;object-fit:cover;border-radius:10px;border:1px solid #e2e8f0;background:#fff}
  .money{white-space:nowrap}
  .pill{display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;border:1px solid #e2e8f0;white-space:nowrap}
  .pill.on{background:#ecfdf5;border-color:#bbf7d0;color:#166534}
  .pill.off{background:#fef2f2;border-color:#fecaca;color:#991b1b}
  .pill.disc{background:#fff7ed;border-color:#fed7aa;color:#9a3412}
  .row-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
  .row-actions form{margin:0}

  @media (max-width: 900px){
    .grid3{grid-template-columns:1fr 1fr}
  }
  @media (max-width: 720px){
    .grid2,.grid3{grid-template-columns:1fr}
    .searchbar{flex-direction:column;align-items:stretch}
    .searchbar .q{min-width:unset}
    .searchbar .actions{width:100%}
    .searchbar .actions .btn{width:100%}
  }

  /* Table -> cards on mobile */
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
      min-width:92px;
      flex:0 0 92px;
    }
    .cell-product{display:flex;gap:10px;align-items:flex-start}
    .cell-product .meta{min-width:0}
    .cell-product .name{font-weight:800}
    .cell-product .sub{font-size:12px;color:#64748b;margin-top:2px}
    .imgcell{display:flex;justify-content:flex-end}
    .row-actions{justify-content:flex-end}
  }
</style>

<div class="wrap">
  <div class="head">
    <div>
      <h1>Produk</h1>
      <div class="muted" style="margin-top:6px;">Kelola produk</div>
    </div>
  </div>

  <?php if($error):?><div class="msg-err"><?=htmlspecialchars($error)?></div><?php endif;?>
  <?php if($ok):?><div class="msg-ok"><?=htmlspecialchars($ok)?></div><?php endif;?>

  <!-- Search -->
  <div class="card" style="margin-bottom:12px;">
    <form method="get" class="searchbar">
      <div class="q">
        <input name="q" placeholder="Cari SKU / nama produk..." value="<?= htmlspecialchars($search) ?>">
      </div>
      <div class="actions">
        <button class="btn" type="submit">Cari</button>
        <?php if($search!==''): ?>
          <a class="btn btn-outline" href="admin_products.php">Reset</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Create product -->
  <div class="card" style="margin-bottom:12px;max-width:980px;">
    <div style="font-weight:800;font-size:16px;margin-bottom:10px;">Tambah Produk</div>

    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>">
      <input type="hidden" name="action" value="create">

      <div class="grid2">
        <div>
          <label>Nama Produk</label>
          <input name="name" required>
        </div>
        <div>
          <label>Kategori</label>
          <select name="category_id">
            <option value="">-- tanpa kategori --</option>
            <?php foreach($catRows as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars((string)$c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="grid3" style="margin-top:10px;">
        <div>
          <label>Harga</label>
          <input type="number" name="price" min="0" value="0">
        </div>
        <div>
          <label>Stok</label>
          <input type="number" name="stock" value="0">
        </div>
        <div>
          <label>Diskon?</label>
          <select name="discount_is_active" id="discount_is_active">
            <option value="0" selected>Tidak</option>
            <option value="1">Ya</option>
          </select>
        </div>
      </div>

      <div class="grid3" style="margin-top:10px;">
        <div>
          <label>Diskon (%)</label>
          <input type="number" name="discount_percent" id="discount_percent" min="0" max="100" value="0">
          <div class="hint">Aktif jika “Diskon?” = Ya.</div>
        </div>
        <div style="display:none;"></div>
        <div style="display:none;"></div>
      </div>

      <hr class="hr">

      <div class="grid2">
        <div>
          <label>Upload Gambar (max 2MB)</label>
          <input type="file" name="image_upload" id="image_upload" accept="image/png,image/jpeg,image/webp">
          <div class="hint">Upload gambar produk.</div>
        </div>

        <div>
          <label>Pilih Gambar Preset</label>
          <select name="preset_image" id="preset_image">
            <option value="">-- tidak pakai preset --</option>
            <?php foreach($presetImages as $p): ?>
              <option value="<?= htmlspecialchars((string)$p) ?>"><?= htmlspecialchars(basename((string)$p)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div style="margin-top:12px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
        <div>
          <div style="font-size:13px;color:#334155;margin-bottom:6px;font-weight:700;">Preview</div>
          <img id="img_preview" class="preview" src="" alt="preview">
        </div>
        <div class="muted" style="font-size:12px;">
        </div>
      </div>

      <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;">
        <button class="btn" type="submit">Tambah Produk</button>
      </div>
    </form>
  </div>

  <!-- List -->
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px;">
      <div style="font-weight:800;font-size:16px;">
        Daftar Produk <?= $search!=='' ? '(hasil pencarian)' : '' ?>
      </div>
      <div class="muted" style="font-size:13px;"><?= count($rows) ?> item</div>
    </div>

    <div class="table-wrap">
      <table class="resp">
        <thead>
          <tr>
            <th style="width:70px;">ID</th>
            <th style="width:90px;">Gambar</th>
            <th style="width:90px;">SKU</th>
            <th>Nama</th>
            <th>Kategori</th>
            <th style="width:140px;">Harga</th>
            <th style="width:90px;">Diskon</th>
            <th style="width:90px;">Stok</th>
            <th style="width:90px;">Aktif</th>
            <th style="width:200px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php if(!$rows): ?>
          <tr><td colspan="10">Tidak ada data.</td></tr>
        <?php else: ?>
          <?php foreach($rows as $r):
            $isActive = (int)$r['is_active'] === 1;
            $discActive = ((int)$r['discount_is_active']===1) && ((int)$r['discount_percent']>0);
            $imgSrc = product_img_src($r['image_path'] ?? '');
          ?>
            <tr>
              <td data-label="ID"><?= (int)$r['id'] ?></td>

              <td data-label="Gambar" class="imgcell">
                <img
                  src="<?= htmlspecialchars($imgSrc) ?>"
                  alt="img"
                  loading="lazy"
                  onerror="this.onerror=null;this.src='../publik/assets/no-image.png';"
                >
              </td>

              <td data-label="SKU"><?= htmlspecialchars((string)($r['sku'] ?? '-')) ?></td>

              <td data-label="Produk">
                <div class="cell-product">
                  <div class="meta">
                    <div class="name"><?= htmlspecialchars((string)$r['name']) ?></div>
                    <div class="sub">
                      <?= htmlspecialchars((string)($r['cat_name'] ?? '-')) ?>
                    </div>
                  </div>
                </div>
              </td>

              <td data-label="Kategori">
                <?= htmlspecialchars((string)($r['cat_name'] ?? '-')) ?>
              </td>

              <td data-label="Harga" class="money">
                Rp <?= number_format((int)$r['price'],0,',','.') ?>
              </td>

              <td data-label="Diskon">
                <?php if($discActive): ?>
                  <span class="pill disc"><?= (int)$r['discount_percent'] ?>%</span>
                <?php else: ?>
                  <span class="muted">-</span>
                <?php endif; ?>
              </td>

              <td data-label="Stok"><?= (int)$r['stock'] ?></td>

              <td data-label="Aktif">
                <span class="pill <?= $isActive ? 'on' : 'off' ?>"><?= $isActive ? 'Ya' : 'Tidak' ?></span>
              </td>

              <td data-label="Aksi">
                <div class="row-actions">
                  <a class="btn btn-outline btn-small" href="admin_product_edit.php?id=<?= (int)$r['id'] ?>">Edit</a>
                  <form method="post" style="display:inline;">
                    <input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-small <?= $isActive ? 'btn-outline' : '' ?>" type="submit">
                      <?= $isActive ? 'Nonaktifkan' : 'Aktifkan' ?>
                    </button>
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
</div>

<script>
  const upload = document.getElementById('image_upload');
  const preset = document.getElementById('preset_image');
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
  syncDiscountUI();

  discActive.addEventListener('change', syncDiscountUI);

  upload.addEventListener('change', () => {
    if(upload.files && upload.files[0]){
      const url = URL.createObjectURL(upload.files[0]);
      show(url);
    } else if (preset.value) {
      show(normalizeAdminImgSrc(preset.value));
    } else {
      show('');
    }
  });

  preset.addEventListener('change', () => {
    if(upload.files && upload.files.length) return; // upload menang
    show(preset.value ? normalizeAdminImgSrc(preset.value) : '');
  });
</script>

<?php require __DIR__ . '/../publik/partials/admin_layout_bottom.php'; ?>
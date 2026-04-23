<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';
require_role(['dapur']);

$appName    = 'MultiPOS';
$pageTitle  = 'Stok Masuk';
$activeMenu = 'stok_masuk';

$dapurId   = (int)auth_user()['id'];
$userName  = (string)auth_user()['name'];

// Ambil store dari admin pembuat akun dapur (ngikut pola kasir)
$st = $pdo->prepare("
  SELECT s.id, s.name
  FROM users u
  JOIN stores s ON s.owner_admin_id = u.created_by
  WHERE u.id=? AND s.is_active=1
  LIMIT 1
");
$st->execute([$dapurId]);
$store = $st->fetch();
if (!$store) { http_response_code(400); exit('User dapur belum terhubung ke toko.'); }

$storeId   = (int)$store['id'];
$storeName = (string)$store['name'];

$error = '';
$ok = '';

function clamp_dec($v, float $min=0.0, float $max=999999999.0): float {
  if (!is_numeric($v)) return 0.0;
  $f = (float)$v;
  if ($f < $min) $f = $min;
  if ($f > $max) $f = $max;
  return $f;
}

function ingredient_of_store(PDO $pdo, int $id, int $storeId): ?array {
  $st = $pdo->prepare("SELECT * FROM ingredients WHERE id=? AND store_id=? LIMIT 1");
  $st->execute([$id,$storeId]);
  $r = $st->fetch();
  return $r ?: null;
}

function product_of_store(PDO $pdo, int $id, int $storeId): ?array {
  $st = $pdo->prepare("SELECT id,name,sku,stock,is_active FROM products WHERE id=? AND store_id=? LIMIT 1");
  $st->execute([$id,$storeId]);
  $r = $st->fetch();
  return $r ?: null;
}

/**
 * ===== HANDLE POST =====
 * Stok Masuk (+) saja
 * update stok + insert ke stock_movements
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $act = (string)($_POST['action'] ?? '');

  if ($act === 'stok_in_save') {
    $target = (string)($_POST['target'] ?? 'ingredient'); // ingredient|product
    if (!in_array($target, ['ingredient','product'], true)) $target = 'ingredient';

    $itemId = (int)($_POST['item_id'] ?? 0);
    $note = trim((string)($_POST['note'] ?? ''));
    if (strlen($note) > 255) $note = substr($note, 0, 255);

    $qtyRaw = $_POST['qty'] ?? 0;

    // validasi qty per target:
    // - product: integer >= 1
    // - ingredient: decimal >= 0.001
    if ($target === 'product') {
      $qty = (int)round((float)$qtyRaw);
      if ($qty < 1) $error = 'Jumlah (produk) minimal 1.';
      $unit = 'pcs';
    } else {
      $qty = clamp_dec($qtyRaw, 0.001);
      $unit = (string)($_POST['unit'] ?? '');
    }

    if (!$error) {
      try {
        $pdo->beginTransaction();

        if ($target === 'product') {
          $p = product_of_store($pdo, $itemId, $storeId);
          if (!$p || (int)$p['is_active'] !== 1) {
            throw new RuntimeException('Produk tidak valid.');
          }

          $cur = (int)$p['stock'];
          $new = $cur + $qty;

          $pdo->prepare("UPDATE products SET stock=? WHERE id=? AND store_id=?")
              ->execute([$new, $itemId, $storeId]);

          $pdo->prepare("
            INSERT INTO stock_movements (store_id,target_type,target_id,direction,qty,unit,note,created_by,created_at)
            VALUES (?,?,?,?,?,?,?,?,NOW())
          ")->execute([$storeId,'product',$itemId,'in',(float)$qty,'pcs',$note,$dapurId]);

          $ok = 'Stok produk berhasil ditambahkan.';
        } else {
          $ing = ingredient_of_store($pdo, $itemId, $storeId);
          if (!$ing || (int)$ing['is_active'] !== 1) {
            throw new RuntimeException('Bahan tidak valid.');
          }

          // unit harus mengikuti unit bahan (biar konsisten)
          $unitDb = (string)$ing['unit'];
          $unit = $unitDb !== '' ? $unitDb : 'pcs';

          $cur = (float)$ing['stock'];
          $new = $cur + (float)$qty;

          $pdo->prepare("UPDATE ingredients SET stock=?, updated_at=NOW() WHERE id=? AND store_id=?")
              ->execute([$new, $itemId, $storeId]);

          $pdo->prepare("
            INSERT INTO stock_movements (store_id,target_type,target_id,direction,qty,unit,note,created_by,created_at)
            VALUES (?,?,?,?,?,?,?,?,NOW())
          ")->execute([$storeId,'ingredient',$itemId,'in',(float)$qty,$unit,$note,$dapurId]);

          $ok = 'Stok bahan berhasil ditambahkan.';
        }

        $pdo->commit();
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage() ?: 'Gagal simpan stok.';
      }
    }

    header('Location: dapur_stok_masuk.php');
    exit;
  }

  header('Location: dapur_stok_masuk.php');
  exit;
}

/**
 * ===== DATA HALAMAN =====
 */

// list produk & bahan untuk dropdown
$productsQ = $pdo->prepare("SELECT id,sku,name,stock FROM products WHERE store_id=? AND is_active=1 ORDER BY name ASC");
$productsQ->execute([$storeId]);
$products = $productsQ->fetchAll();

$ingQ = $pdo->prepare("SELECT id,name,unit,stock FROM ingredients WHERE store_id=? AND is_active=1 ORDER BY name ASC");
$ingQ->execute([$storeId]);
$ingredients = $ingQ->fetchAll();

// riwayat terakhir (30 data)
$movQ = $pdo->prepare("
  SELECT m.*,
    CASE WHEN m.target_type='product' THEN p.name ELSE i.name END AS item_name
  FROM stock_movements m
  LEFT JOIN products p ON (m.target_type='product' AND p.id=m.target_id)
  LEFT JOIN ingredients i ON (m.target_type='ingredient' AND i.id=m.target_id)
  WHERE m.store_id=?
  ORDER BY m.id DESC
  LIMIT 30
");
$movQ->execute([$storeId]);
$movements = $movQ->fetchAll();

require __DIR__ . '/../publik/partials/dapur_layout_top.php';
require __DIR__ . '/../publik/partials/dapur_sidebar.php';
?>

<main class="content">

  <div class="topbar">
    <div class="left">
      <button class="icon-btn" type="button"
        onclick="window.__dapurSidebarToggle && window.__dapurSidebarToggle()"
        aria-label="Menu">☰</button>

      <div>
        <p class="h" style="margin:0;">Stok Masuk</p>
        <p class="p">Terima barang datang. Semua tercatat di riwayat stok.</p>
      </div>
    </div>
    <div class="right">

    </div>
  </div>

  <?php if ($error): ?>
    <div class="card" style="margin-bottom:14px; border-color: rgba(239,68,68,.35);">
      <b style="color:#ef4444;"><?= htmlspecialchars($error) ?></b>
    </div>
  <?php endif; ?>

  <?php if ($ok): ?>
    <div class="card" style="margin-bottom:14px; border-color: rgba(34,197,94,.35);">
      <b style="color:#16a34a;"><?= htmlspecialchars($ok) ?></b>
    </div>
  <?php endif; ?>

  <style>
    .grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
    @media (max-width: 920px){ .grid2{grid-template-columns:1fr} }
    .pill{display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;border:1px solid rgba(15,23,42,.10)}
    .pill.in{background:#ecfdf5;border-color:#bbf7d0;color:#166534}
  </style>

  <div class="grid2">

    <!-- FORM STOK MASUK -->
    <div class="card">
      <div style="font-weight:950; margin-bottom:10px;">Terima Barang (Tambah Stok)</div>

      <form method="post" id="inForm">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="stok_in_save">

        <div style="display:grid;grid-template-columns:1fr;gap:12px;">
          <div>
            <label class="small muted" style="display:block;margin-bottom:6px;">Target</label>
            <select name="target" id="targetSel">
              <option value="ingredient">Bahan</option>
              <option value="product">Produk</option>
            </select>
          </div>
        </div>

        <div style="margin-top:12px;">
          <label class="small muted" style="display:block;margin-bottom:6px;">Item</label>
          <select name="item_id" id="itemSel">
            <option value="0">-- pilih item --</option>
          </select>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px;">
          <div>
            <label class="small muted" style="display:block;margin-bottom:6px;">Jumlah Masuk</label>
            <input type="number" name="qty" id="qtyInp" step="0.001" min="0.001" value="1">
            <div class="small muted" style="margin-top:6px;">Isi jumlah stok yang diterima.</div>
          </div>

          <div>
            <label class="small muted" style="display:block;margin-bottom:6px;">Satuan</label>
            <select name="unit" id="unitSel" disabled>
              <option value="">--</option>
            </select>
            <div class="small muted" style="margin-top:6px;">Satuan mengikuti item (produk default pcs).</div>
          </div>
        </div>

        <div style="margin-top:12px;">
          <label class="small muted" style="display:block;margin-bottom:6px;">Catatan (opsional)</label>
          <input type="text" name="note" placeholder="cth: PO-001 / Restock Supplier A / Barang datang">
        </div>

        <div style="margin-top:14px;">
          <button class="btn" type="submit" style="width:100%;">SIMPAN STOK MASUK</button>
        </div>

        <div class="small muted" style="margin-top:10px;">
          Catatan: Setiap akan memasukan stok harap lapor ke Admin dulu
        </div>
      </form>
    </div>

    <!-- RIWAYAT TERBARU -->
    <div class="card">
      <div style="font-weight:950; margin-bottom:10px;">Riwayat Terbaru</div>
      <div class="small muted" style="margin-bottom:10px;">Menampilkan 30 data terakhir.</div>

      <?php if(!$movements): ?>
        <div class="muted">Belum ada riwayat.</div>
      <?php else: ?>
        <table width="100%">
          <tr>
            <th>Waktu</th>
            <th>Target</th>
            <th>Item</th>
            <th>Tipe</th>
            <th style="text-align:right;">Qty</th>
            <th>Catatan</th>
          </tr>
          <?php foreach($movements as $m): ?>
            <tr>
              <td><?= htmlspecialchars((string)$m['created_at']) ?></td>
              <td><?= $m['target_type']==='product'?'Produk':'Bahan' ?></td>
              <td><?= htmlspecialchars((string)($m['item_name'] ?? '')) ?></td>
              <td><span class="pill in">Masuk</span></td>
              <td style="text-align:right;">
                <?= rtrim(rtrim(number_format((float)$m['qty'],3,'.',''), '0'), '.') ?>
                <?= htmlspecialchars((string)$m['unit']) ?>
              </td>
              <td><?= htmlspecialchars((string)$m['note']) ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>

      <div class="small muted" style="margin-top:10px;">
      </div>
    </div>

  </div>

  <script>
    const products = <?= json_encode(array_map(function($p){
      return [
        'id'=>(int)$p['id'],
        'name'=>(string)$p['name'],
        'sku'=>(string)($p['sku'] ?? ''),
        'unit'=>'pcs',
        'stock'=>(int)$p['stock'],
      ];
    }, $products), JSON_UNESCAPED_UNICODE); ?>;

    const ingredients = <?= json_encode(array_map(function($i){
      return [
        'id'=>(int)$i['id'],
        'name'=>(string)$i['name'],
        'unit'=>(string)($i['unit'] ?? 'pcs'),
        'stock'=>(float)$i['stock'],
      ];
    }, $ingredients), JSON_UNESCAPED_UNICODE); ?>;

    const targetSel = document.getElementById('targetSel');
    const itemSel = document.getElementById('itemSel');
    const unitSel = document.getElementById('unitSel');
    const qtyInp = document.getElementById('qtyInp');

    function fillItems() {
      const target = targetSel.value;
      itemSel.innerHTML = '<option value="0">-- pilih item --</option>';

      const list = (target === 'product') ? products : ingredients;
      list.forEach(it => {
        const opt = document.createElement('option');
        opt.value = String(it.id);
        const skuText = it.sku ? ` [${it.sku}]` : '';
        opt.textContent = `${it.name}${skuText} (stok ${it.stock} ${it.unit})`;
        opt.dataset.unit = it.unit;
        itemSel.appendChild(opt);
      });

      if (target === 'product') {
        qtyInp.step = '1';
        qtyInp.min  = '1';
        qtyInp.value= '1';
      } else {
        qtyInp.step = '0.001';
        qtyInp.min  = '0.001';
        qtyInp.value= '1';
      }

      unitSel.innerHTML = '<option value="">--</option>';
    }

    function syncUnit() {
      const opt = itemSel.options[itemSel.selectedIndex];
      const unit = opt ? (opt.dataset.unit || '') : '';
      unitSel.innerHTML = '';
      const uopt = document.createElement('option');
      uopt.value = unit;
      uopt.textContent = unit || '--';
      unitSel.appendChild(uopt);
    }

    targetSel.addEventListener('change', () => { fillItems(); });
    itemSel.addEventListener('change', () => { syncUnit(); });

    fillItems();
  </script>

</main>

<?php require __DIR__ . '/../publik/partials/dapur_layout_bottom.php'; ?>

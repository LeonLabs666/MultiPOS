<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

$page_title   = 'Stok Masuk/Keluar • MultiPOS';
$page_h1      = 'Stok Masuk/Keluar';
$active_menu  = 'persediaan';

$storeId   = (int)$storeId; // from _bootstrap
$storeName = (string)$store['name'];

// ✅ FIX: inisialisasi + flash message (agar tidak undefined & pesan tetap muncul setelah redirect)
$error = (string)($_SESSION['flash_error'] ?? '');
$ok    = (string)($_SESSION['flash_ok'] ?? '');
unset($_SESSION['flash_error'], $_SESSION['flash_ok']);

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
 * simpan stok masuk/keluar + update stok + insert log ke stock_movements
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $act = (string)($_POST['action'] ?? '');

  if ($act === 'inout_save') {
    $target = (string)($_POST['target'] ?? 'ingredient'); // ingredient|product
    if (!in_array($target, ['ingredient','product'], true)) $target = 'ingredient';

    $direction = (string)($_POST['direction'] ?? 'in'); // in|out
    if (!in_array($direction, ['in','out'], true)) $direction = 'in';

    $itemId = (int)($_POST['item_id'] ?? 0);
    $note = trim((string)($_POST['note'] ?? ''));
    if (strlen($note) > 255) $note = substr($note, 0, 255);

    $qtyRaw = $_POST['qty'] ?? 0;

    $errorLocal = '';

    // validasi qty per target:
    // - product: integer >= 1
    // - ingredient: decimal >= 0.001
    if ($itemId <= 0) {
      $errorLocal = 'Silakan pilih item.';
    } else {
      if ($target === 'product') {
        $qty = (int)round((float)$qtyRaw);
        if ($qty < 1) $errorLocal = 'Jumlah (produk) minimal 1.';
        $unit = 'pcs';
      } else {
        $qty = clamp_dec($qtyRaw, 0.001);
        if ($qty < 0.001) $errorLocal = 'Jumlah (bahan) minimal 0.001.';
        $unit = (string)($_POST['unit'] ?? '');
      }
    }

    if ($errorLocal === '') {
      try {
        $pdo->beginTransaction();

        if ($target === 'product') {
          $p = product_of_store($pdo, $itemId, $storeId);
          if (!$p || (int)$p['is_active'] !== 1) {
            throw new RuntimeException('Produk tidak valid.');
          }
          $cur = (int)$p['stock'];
          $new = ($direction === 'in') ? ($cur + $qty) : ($cur - $qty);
          if ($new < 0) {
            throw new RuntimeException('Stok produk tidak cukup untuk keluar.');
          }

          $pdo->prepare("UPDATE products SET stock=? WHERE id=? AND store_id=?")
              ->execute([$new, $itemId, $storeId]);

          $pdo->prepare("
            INSERT INTO stock_movements (store_id,target_type,target_id,direction,qty,unit,note,created_by,created_at)
            VALUES (?,?,?,?,?,?,?,?,NOW())
          ")->execute([$storeId,'product',$itemId,$direction,(float)$qty,'pcs',$note,$adminId]);

          $pdo->commit();

          $_SESSION['flash_ok'] = 'Stok produk berhasil disimpan.';
        } else {
          $ing = ingredient_of_store($pdo, $itemId, $storeId);
          if (!$ing || (int)$ing['is_active'] !== 1) {
            throw new RuntimeException('Bahan tidak valid.');
          }

          // unit harus mengikuti unit bahan (biar konsisten)
          $unitDb = (string)$ing['unit'];
          $unit = $unitDb !== '' ? $unitDb : 'pcs';

          $cur = (float)$ing['stock'];
          $new = ($direction === 'in') ? ($cur + (float)$qty) : ($cur - (float)$qty);
          if ($new < 0) {
            throw new RuntimeException('Stok bahan tidak cukup untuk keluar.');
          }

          $pdo->prepare("UPDATE ingredients SET stock=?, updated_at=NOW() WHERE id=? AND store_id=?")
              ->execute([$new, $itemId, $storeId]);

          $pdo->prepare("
            INSERT INTO stock_movements (store_id,target_type,target_id,direction,qty,unit,note,created_by,created_at)
            VALUES (?,?,?,?,?,?,?,?,NOW())
          ")->execute([$storeId,'ingredient',$itemId,$direction,(float)$qty,$unit,$note,$adminId]);

          $pdo->commit();

          $_SESSION['flash_ok'] = 'Stok bahan berhasil disimpan.';
        }
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['flash_error'] = ($e->getMessage() ?: 'Gagal simpan stok.');
      }
    } else {
      $_SESSION['flash_error'] = $errorLocal;
    }

    header('Location: admin_stok_inout.php');
    exit;
  }

  header('Location: admin_stok_inout.php');
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

include __DIR__ . '/partials/layout_top.php';
?>

<style>
  .inv-wrap{max-width:1200px;}
  .muted{color:#64748b}
  .grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
  @media (max-width: 920px){ .grid2{grid-template-columns:1fr} }
  .card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:14px;}
  .btn{padding:10px 14px;border-radius:12px;border:1px solid #e2e8f0;background:#0f172a;color:#fff;cursor:pointer}
  .btn:active{transform:translateY(1px)}
  input,select{padding:10px 12px;border-radius:12px;border:1px solid #e2e8f0;width:100%}
  label{font-size:13px;color:#334155}
  table{width:100%;border-collapse:collapse}
  th,td{border-bottom:1px solid #f1f5f9;padding:8px 6px;text-align:left;font-size:13px}
  th{color:#64748b;font-weight:700}
  .pill{display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;border:1px solid #e2e8f0}
  .pill.in{background:#ecfdf5;border-color:#bbf7d0;color:#166534}
  .pill.out{background:#fef2f2;border-color:#fecaca;color:#991b1b}
</style>

<div class="inv-wrap">
  <h1 style="margin:0 0 6px;">Stok Masuk/Keluar</h1>
  <div class="muted" style="margin-bottom:10px;">
    Toko: <b><?= htmlspecialchars($storeName) ?></b>
  </div>

  <?php if($error):?><p style="color:#ef4444;"><?=htmlspecialchars($error)?></p><?php endif;?>
  <?php if($ok):?><p style="color:#16a34a;"><?=htmlspecialchars($ok)?></p><?php endif;?>

  <div class="grid2">

    <!-- FORM IN/OUT -->
    <div class="card">
      <h3 style="margin:0 0 10px;">Stok Masuk / Keluar</h3>

      <form method="post" id="inoutForm">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="inout_save">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div>
            <label>Target</label>
            <select name="target" id="targetSel">
              <option value="ingredient">Bahan</option>
              <option value="product">Produk</option>
            </select>
          </div>

          <div>
            <label>Tipe</label>
            <select name="direction" id="dirSel">
              <option value="in">Masuk (+)</option>
              <option value="out">Keluar (-)</option>
            </select>
          </div>
        </div>

        <div style="margin-top:12px;">
          <label>Item</label>
          <select name="item_id" id="itemSel">
            <option value="0">-- pilih item --</option>
          </select>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px;">
          <div>
            <label>Jumlah</label>
            <input type="number" name="qty" id="qtyInp" step="0.001" min="0.001" value="1">
            <div class="muted" style="font-size:12px;margin-top:6px;">Isi jumlah stok yang akan ditambah / dikurangi.</div>
          </div>

          <div>
            <label>Satuan</label>
            <select name="unit" id="unitSel" disabled>
              <option value="">--</option>
            </select>
            <div class="muted" style="font-size:12px;margin-top:6px;">Satuan mengikuti item (produk default pcs).</div>
          </div>
        </div>

        <div style="margin-top:12px;">
          <label>Catatan</label>
          <input type="text" name="note" placeholder="restok / stok rusak / koreksi fisik">
        </div>

        <div style="margin-top:14px;">
          <button class="btn" type="submit" style="width:100%;">SIMPAN</button>
        </div>

        <div class="muted" style="font-size:12px;margin-top:10px;">
          <a href="admin_persediaan.php?tab=bahan">← Kembali ke Persediaan</a>
        </div>
      </form>
    </div>

    <!-- RIWAYAT TERBARU -->
    <div class="card">
      <h3 style="margin:0 0 10px;">Riwayat Terbaru</h3>
      <div class="muted" style="font-size:12px;margin-bottom:10px;">Menampilkan 30 data terakhir.</div>

      <?php if(!$movements): ?>
        <div class="muted">Belum ada riwayat.</div>
      <?php else: ?>
        <table>
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
              <td>
                <?php if($m['direction']==='in'): ?>
                  <span class="pill in">Masuk</span>
                <?php else: ?>
                  <span class="pill out">Keluar</span>
                <?php endif; ?>
              </td>
              <td style="text-align:right;">
                <?= rtrim(rtrim(number_format((float)$m['qty'],3,'.',''), '0'), '.') ?>
                <?= htmlspecialchars((string)$m['unit']) ?>
              </td>
              <td><?= htmlspecialchars((string)$m['note']) ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>
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
        qtyInp.min = '1';
        qtyInp.value = '1';
      } else {
        qtyInp.step = '0.001';
        qtyInp.min = '0.001';
        qtyInp.value = '1';
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
</div>

<?php include __DIR__ . '/partials/layout_bottom.php'; ?>

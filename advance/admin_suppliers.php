<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';

require_role(['admin']);

$appName='MultiPOS';
$pageTitle='Supplier';
$activeMenu='supplier';

$adminId=(int)auth_user()['id'];

// ambil toko admin
$st=$pdo->prepare("SELECT id,name FROM stores WHERE owner_admin_id=? AND is_active=1 LIMIT 1");
$st->execute([$adminId]);
$store=$st->fetch();
if(!$store){ http_response_code(400); exit('Admin belum punya toko.'); }
$storeId=(int)$store['id'];
$storeName=$store['name'];

$error=''; $ok='';

// ===== AUTO CREATE TABLE =====
try{
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS suppliers (
      id INT AUTO_INCREMENT PRIMARY KEY,
      store_id INT NOT NULL,
      name VARCHAR(120) NOT NULL,
      phone VARCHAR(32) NOT NULL,
      address VARCHAR(255) NULL,
      notes TEXT NULL,
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NULL,
      INDEX idx_store (store_id),
      INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");
}catch(PDOException $e){
  http_response_code(500);
  exit('Gagal inisialisasi tabel suppliers.');
}

function normalize_wa(string $raw): string {
  $p = preg_replace('/[^0-9+]/', '', trim($raw)) ?? '';
  $p = ltrim($p, '+');

  // 08xx -> 628xx
  if (str_starts_with($p, '08')) return '62' . substr($p, 1);

  // 8xx (tanpa 0/62) -> 628xx
  if (preg_match('/^8[0-9]{7,}$/', $p)) return '62' . $p;

  // sudah 62...
  if (str_starts_with($p, '62')) return $p;

  return $p; // fallback
}

function wa_link(string $phone, string $message): string {
  $p = normalize_wa($phone);
  $msg = rawurlencode($message);
  // wa.me butuh angka saja
  $p = preg_replace('/[^0-9]/', '', $p) ?? '';
  return "https://wa.me/{$p}?text={$msg}";
}

if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf_verify();
  $act=$_POST['action'] ?? '';

  if($act==='create'){
    $name=trim($_POST['name'] ?? '');
    $phone=trim($_POST['phone'] ?? '');
    $address=trim($_POST['address'] ?? '');
    $notes=trim($_POST['notes'] ?? '');

    if($name==='' || $phone===''){
      $error='Nama dan No. WhatsApp wajib diisi.';
    }else{
      $phoneNorm = normalize_wa($phone);
      $pdo->prepare("
        INSERT INTO suppliers (store_id,name,phone,address,notes,is_active,created_at,updated_at)
        VALUES (?,?,?,?,?,1,NOW(),NULL)
      ")->execute([$storeId,$name,$phoneNorm,$address!==''?$address:null,$notes!==''?$notes:null]);
      $ok='Supplier berhasil ditambahkan.';
    }
  }

  if($act==='update'){
    $id=(int)($_POST['id'] ?? 0);
    $name=trim($_POST['name'] ?? '');
    $phone=trim($_POST['phone'] ?? '');
    $address=trim($_POST['address'] ?? '');
    $notes=trim($_POST['notes'] ?? '');

    if($id<=0){ $error='ID tidak valid.'; }
    elseif($name==='' || $phone===''){ $error='Nama dan No. WhatsApp wajib diisi.'; }
    else{
      $phoneNorm = normalize_wa($phone);
      $pdo->prepare("
        UPDATE suppliers
        SET name=?, phone=?, address=?, notes=?, updated_at=NOW()
        WHERE id=? AND store_id=? AND is_active=1
        LIMIT 1
      ")->execute([$name,$phoneNorm,$address!==''?$address:null,$notes!==''?$notes:null,$id,$storeId]);
      $ok='Supplier berhasil diupdate.';
    }
  }

  if($act==='delete'){
    $id=(int)($_POST['id'] ?? 0);
    if($id<=0){ $error='ID tidak valid.'; }
    else{
      $pdo->prepare("
        UPDATE suppliers SET is_active=0, updated_at=NOW()
        WHERE id=? AND store_id=? LIMIT 1
      ")->execute([$id,$storeId]);
      $ok='Supplier berhasil dihapus.';
    }
  }

  if($act==='wa_order'){
    // redirect ke WA dengan pesan yg dirakit dari form
    $sid=(int)($_POST['supplier_id'] ?? 0);
    $message=trim($_POST['message'] ?? '');

    $q=$pdo->prepare("SELECT phone,name FROM suppliers WHERE id=? AND store_id=? AND is_active=1 LIMIT 1");
    $q->execute([$sid,$storeId]);
    $sup=$q->fetch();

    if(!$sup){ $error='Supplier tidak ditemukan.'; }
    elseif($message===''){ $error='Pesan WhatsApp tidak boleh kosong.'; }
    else{
      $link = wa_link($sup['phone'], $message);
      header("Location: {$link}");
      exit;
    }
  }
}

$rows=$pdo->prepare("
  SELECT id,name,phone,address,notes,created_at
  FROM suppliers
  WHERE store_id=? AND is_active=1
  ORDER BY name ASC
");
$rows->execute([$storeId]);
$suppliers=$rows->fetchAll();

require __DIR__ . '/../publik/partials/admin_layout_top.php';
?>

<style>
  .wrap{max-width:1100px}
  .card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:14px;margin:12px 0}
  .row{display:flex;gap:10px;flex-wrap:wrap}
  .row > *{flex:1;min-width:220px}
  label{display:block;font-size:12px;color:#64748b;margin-bottom:6px}
  input,textarea,select{width:100%;border:1px solid #e5e7eb;border-radius:10px;padding:10px}
  textarea{min-height:90px}
  .btn{display:inline-block;border:0;border-radius:10px;padding:10px 12px;cursor:pointer}
  .btn-primary{background:#111827;color:#fff}
  .btn-danger{background:#ef4444;color:#fff}
  .btn-ghost{background:#f1f5f9;color:#111827}
  .muted{color:#64748b}
  table{width:100%;border-collapse:collapse}
  th,td{padding:10px;border-bottom:1px solid #e5e7eb;vertical-align:top}
  th{text-align:left;font-size:12px;color:#64748b}
  details > summary{cursor:pointer;color:#111827}
  .actions{display:flex;gap:8px;flex-wrap:wrap}
</style>

<div class="wrap">
  <h1>Supplier</h1>

  <?php if($error): ?>
    <div class="card" style="border-color:#fecaca;background:#fef2f2;color:#991b1b"><b>Gagal:</b> <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if($ok): ?>
    <div class="card" style="border-color:#bbf7d0;background:#f0fdf4;color:#166534"><b>OK:</b> <?= htmlspecialchars($ok) ?></div>
  <?php endif; ?>

  <!-- Tambah Supplier -->
  <div class="card">
    <h3>Tambah Supplier</h3>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="action" value="create">
      <div class="row">
        <div>
          <label>Nama Supplier</label>
          <input name="name" placeholder="Contoh: CV Sumber Rejeki" required>
        </div>
        <div>
          <label>No. WhatsApp</label>
          <input name="phone" placeholder="Contoh: 0812xxxx / +62812xxxx" required>
        </div>
      </div>
      <div class="row">
        <div>
          <label>Alamat (opsional)</label>
          <input name="address" placeholder="Alamat supplier">
        </div>
        <div>
          <label>Catatan (opsional)</label>
          <input name="notes" placeholder="Jam operasional, PIC, dll">
        </div>
      </div>
      <button class="btn btn-primary" type="submit">Simpan</button>
    </form>
  </div>

  <!-- Pesan Stok via WA -->
  <div class="card">
    <h3>Pesan Stok via WhatsApp</h3>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="action" value="wa_order">

      <div class="row">
        <div>
          <label>Pilih Supplier</label>
          <select name="supplier_id" required>
            <option value="">-- pilih --</option>
            <?php foreach($suppliers as $s): ?>
              <option value="<?= (int)$s['id'] ?>">
                <?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['phone']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Template Cepat</label>
          <select id="tpl">
            <option value="">-- pilih template --</option>
            <option value="Halo {SUP}, mau order stok untuk toko {TOKO}:\n\n{ITEMS}\n\nMohon info harga & estimasi kirim ya. Terima kasih.">Order stok (umum)</option>
            <option value="Halo {SUP}, boleh minta update harga terbaru untuk:\n\n{ITEMS}\n\nTerima kasih.">Minta harga</option>
            <option value="Halo {SUP}, bisa kirim hari ini untuk:\n\n{ITEMS}\n\nMohon konfirmasi ya.">Minta kirim hari ini</option>
          </select>
        </div>
      </div>

      <label>Pesan</label>
      <textarea name="message" id="msg" placeholder="Tulis daftar item, contoh:
- Ayam fillet 5 kg
- Minyak 2 dus
- Tepung 10 kg"></textarea>

      <div class="actions">
        <button class="btn btn-primary" type="submit">Buka WhatsApp</button>
        <button class="btn btn-ghost" type="button" id="fillTpl">Isi dari template</button>
      </div>
    </form>
  </div>

  <!-- List Supplier -->
  <div class="card">
    <h3>Daftar Supplier</h3>

    <?php if(!$suppliers): ?>
      <p class="muted">Belum ada supplier.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Nama</th>
            <th>No. WA</th>
            <th>Alamat</th>
            <th>Catatan</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach($suppliers as $s): ?>
          <?php
            $chatMsg = "Halo {$s['name']}, mau pesan stok untuk toko {$storeName}.";
            $chatUrl = wa_link($s['phone'], $chatMsg);
          ?>
          <tr>
            <td><b><?= htmlspecialchars($s['name']) ?></b></td>
            <td><?= htmlspecialchars($s['phone']) ?></td>
            <td><?= htmlspecialchars((string)($s['address'] ?? '')) ?></td>
            <td><?= htmlspecialchars((string)($s['notes'] ?? '')) ?></td>
            <td>
              <div class="actions">
                <a class="btn btn-ghost" target="_blank" rel="noopener" href="<?= htmlspecialchars($chatUrl) ?>">Chat</a>

                <details>
                  <summary class="btn btn-ghost">Edit</summary>
                  <div style="margin-top:10px">
                    <form method="post">
                      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">

                      <div class="row">
                        <div>
                          <label>Nama</label>
                          <input name="name" value="<?= htmlspecialchars($s['name']) ?>" required>
                        </div>
                        <div>
                          <label>No. WA</label>
                          <input name="phone" value="<?= htmlspecialchars($s['phone']) ?>" required>
                        </div>
                      </div>
                      <div class="row">
                        <div>
                          <label>Alamat</label>
                          <input name="address" value="<?= htmlspecialchars((string)($s['address'] ?? '')) ?>">
                        </div>
                        <div>
                          <label>Catatan</label>
                          <input name="notes" value="<?= htmlspecialchars((string)($s['notes'] ?? '')) ?>">
                        </div>
                      </div>
                      <button class="btn btn-primary" type="submit">Simpan</button>
                    </form>
                  </div>
                </details>

                <form method="post" onsubmit="return confirm('Hapus supplier ini?')" style="display:inline">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                  <button class="btn btn-danger" type="submit">Hapus</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<script>
  const tpl = document.getElementById('tpl');
  const msg = document.getElementById('msg');
  const fill = document.getElementById('fillTpl');

  fill?.addEventListener('click', () => {
    const t = tpl.value || '';
    if(!t) return;
    const toko = <?= json_encode($storeName) ?>;
    const sup = '{SUP}';
    const items = '- ';
    msg.value = t
      .replaceAll('{TOKO}', toko)
      .replaceAll('{SUP}', sup)
      .replaceAll('{ITEMS}', items);
    msg.focus();
  });
</script>

<?php require __DIR__ . '/../publik/partials/admin_layout_bottom.php'; ?>

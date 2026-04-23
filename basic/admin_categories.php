<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

$page_title  = 'Kategori • MultiPOS';
$page_h1     = 'Kategori';
$active_menu = 'kategori';

$storeId   = (int)$storeId; // from _bootstrap
$storeName = (string)$store['name'];

$error = '';
$ok    = '';

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();

  $action = (string)($_POST['action'] ?? '');

  // ===== Tambah kategori
  if ($action === 'add') {
    $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '') {
      $error = 'Nama kategori wajib diisi.';
    } else {
      try {
        $q = $pdo->prepare("INSERT INTO categories(store_id,name,is_active) VALUES(?,?,1)");
        $q->execute([$storeId, $name]);
        $ok = 'Kategori berhasil ditambahkan.';
      } catch (Throwable $e) {
        $error = 'Gagal menambahkan kategori.';
      }
    }
  }

  // ===== Toggle aktif/nonaktif
  if ($action === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
      $error = 'ID kategori tidak valid.';
    } else {
      try {
        $q = $pdo->prepare("SELECT is_active FROM categories WHERE id=? AND store_id=? LIMIT 1");
        $q->execute([$id, $storeId]);
        $cur = $q->fetchColumn();
        if ($cur === false) {
          $error = 'Kategori tidak ditemukan.';
        } else {
          $next = ((int)$cur === 1) ? 0 : 1;
          $u = $pdo->prepare("UPDATE categories SET is_active=? WHERE id=? AND store_id=?");
          $u->execute([$next, $id, $storeId]);
          $ok = 'Status kategori diperbarui.';
        }
      } catch (Throwable $e) {
        $error = 'Gagal memperbarui status kategori.';
      }
    }
  }

  // ===== Hapus permanen (wajib ketik HAPUS)
  if ($action === 'delete') {
    $id    = (int)($_POST['id'] ?? 0);
    $typed = trim((string)($_POST['confirm'] ?? ''));

    if ($id <= 0) {
      $error = 'ID kategori tidak valid.';
    } elseif (mb_strtoupper($typed) !== 'HAPUS') {
      $error = 'Konfirmasi salah. Ketik HAPUS untuk menghapus permanen.';
    } else {
      try {
        $q = $pdo->prepare("SELECT id,name FROM categories WHERE id=? AND store_id=? LIMIT 1");
        $q->execute([$id, $storeId]);
        $row = $q->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
          $error = 'Kategori tidak ditemukan.';
        } else {
          // Cek masih dipakai produk?
          $ck = $pdo->prepare("SELECT COUNT(*) c FROM products WHERE store_id=? AND category_id=?");
          $ck->execute([$storeId, $id]);
          $c = (int)($ck->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

          if ($c > 0) {
            $error = 'Kategori tidak bisa dihapus karena masih dipakai oleh produk.';
          } else {
            $d = $pdo->prepare("DELETE FROM categories WHERE id=? AND store_id=? LIMIT 1");
            $d->execute([$id, $storeId]);
            $ok = 'Kategori berhasil dihapus permanen.';
          }
        }
      } catch (Throwable $e) {
        $error = 'Gagal menghapus kategori.';
      }
    }
  }
}

// ===== List kategori
$list = $pdo->prepare("SELECT id,name,is_active,created_at FROM categories WHERE store_id=? ORDER BY id DESC");
$list->execute([$storeId]);
$rows = $list->fetchAll(PDO::FETCH_ASSOC);

// ✅ FIX UTAMA: pakai partial yang benar (yang memang ada)
include __DIR__ . '/partials/layout_top.php';
?>



<?php if ($error): ?>
  <div style="padding:12px 14px;border:1px solid rgba(220,38,38,.25);background:rgba(220,38,38,.06);border-radius:14px;color:#991b1b;margin:14px 0;">
    <?= h($error) ?>
  </div>
<?php endif; ?>

<?php if ($ok): ?>
  <div style="padding:12px 14px;border:1px solid rgba(16,185,129,.25);background:rgba(16,185,129,.06);border-radius:14px;color:#065f46;margin:14px 0;">
    <?= h($ok) ?>
  </div>
<?php endif; ?>

<div class="grid" style="display:grid;grid-template-columns:1fr 1fr;gap:18px;align-items:start;">
  <div class="card" style="background:var(--card);border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow);padding:18px;">
    <div style="font-weight:800;margin-bottom:12px;">Tambah Kategori</div>

    <form method="post">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="add">

      <div style="font-size:13px;color:var(--muted);margin-bottom:6px;">Nama Kategori</div>
      <input
        name="name"
        placeholder="contoh: Minuman, Makanan, Dessert"
        style="width:100%;padding:12px 14px;border:1px solid var(--line);border-radius:14px;outline:none"
      >

      <div style="margin-top:12px;">
        <button class="btn" style="padding:10px 14px;border:0;border-radius:14px;background:#0b1220;color:#fff;font-weight:700;cursor:pointer;">
          Tambah
        </button>
      </div>
    </form>
  </div>

  <div class="card" style="background:var(--card);border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow);padding:18px;">
    <div style="font-weight:800;margin-bottom:10px;">Catatan</div>
    <div style="color:var(--muted);font-size:13px;line-height:1.6">
      <div>• Nonaktifkan jika kategori sementara tidak dipakai</div>
      <div>• Hapus permanen hanya bisa kalau kategori tidak dipakai oleh produk.</div>
      <div>• Untuk menghapus permanen, wajib ketik <b>HAPUS</b> supaya tidak salah pencet.</div>
    </div>
  </div>
</div>

<div class="card" style="background:var(--card);border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow);padding:18px;margin-top:18px;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
    <div style="font-weight:800;">Daftar Kategori</div>
    <div style="color:var(--muted);font-size:13px;"><?= count($rows) ?> kategori</div>
  </div>

  <div style="overflow:auto;">
    <table style="width:100%;border-collapse:collapse;">
      <thead>
        <tr style="text-align:left;border-bottom:1px solid var(--line);color:var(--muted);font-size:12px;">
          <th style="padding:10px 8px;">ID</th>
          <th style="padding:10px 8px;">NAMA</th>
          <th style="padding:10px 8px;">STATUS</th>
          <th style="padding:10px 8px;">AKSI</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr>
            <td colspan="4" style="padding:14px 8px;color:var(--muted);">Belum ada kategori.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <?php
              $id = (int)$r['id'];
              $active = (int)$r['is_active'] === 1;
            ?>
            <tr style="border-bottom:1px solid var(--line);">
              <td style="padding:12px 8px;"><?= $id ?></td>
              <td style="padding:12px 8px;"><?= h($r['name']) ?></td>
              <td style="padding:12px 8px;">
                <span style="display:inline-block;padding:6px 10px;border-radius:999px;font-size:12px;
                  border:1px solid <?= $active ? 'rgba(16,185,129,.35)' : 'rgba(100,116,139,.35)' ?>;
                  background: <?= $active ? 'rgba(16,185,129,.08)' : 'rgba(100,116,139,.08)' ?>;
                  color: <?= $active ? '#065f46' : '#334155' ?>;">
                  <?= $active ? 'Aktif' : 'Nonaktif' ?>
                </span>
              </td>
              <td style="padding:12px 8px;">
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                  <form method="post" style="display:inline;">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <button style="padding:8px 10px;border-radius:12px;border:1px solid var(--line);background:#fff;cursor:pointer;">
                      <?= $active ? 'Nonaktifkan' : 'Aktifkan' ?>
                    </button>
                  </form>

                  <form method="post" style="display:inline;">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input
                      name="confirm"
                      placeholder="ketik HAPUS"
                      style="padding:8px 10px;border-radius:12px;border:1px solid var(--line);width:130px"
                    >
                    <button style="padding:8px 10px;border-radius:12px;border:1px solid rgba(220,38,38,.35);background:rgba(220,38,38,.06);color:#991b1b;cursor:pointer;">
                      Hapus
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

<?php include __DIR__ . '/partials/layout_bottom.php'; ?>

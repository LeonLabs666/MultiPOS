<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';
require_role(['admin']);

$appName='MultiPOS';
$pageTitle='Persediaan';
$activeMenu='persediaan';
$adminId=(int)auth_user()['id'];

// ambil toko admin
$st = $pdo->prepare("SELECT id,name FROM stores WHERE owner_admin_id=? AND is_active=1 LIMIT 1");
$st->execute([$adminId]);
$store = $st->fetch();
if (!$store) { http_response_code(400); exit('Admin belum terhubung ke toko.'); }

$storeName = (string)$store['name'];

require __DIR__ . '/../publik/partials/admin_layout_top.php';
?>

<style>
  .inv-wrap{max-width:1200px;}
  .muted{color:#64748b}
  .grid{display:grid;grid-template-columns:repeat(3, 1fr);gap:14px}
  @media (max-width: 980px){ .grid{grid-template-columns:repeat(2,1fr)} }
  @media (max-width: 640px){ .grid{grid-template-columns:1fr} }

  .card{
    background:#fff;border:1px solid #e2e8f0;border-radius:16px;
    padding:16px; text-decoration:none; color:#0f172a; display:block;
    transition:transform .08s ease, box-shadow .08s ease;
  }
  .card:hover{transform:translateY(-1px); box-shadow:0 10px 22px rgba(2,6,23,.06)}
  .title{font-weight:800;font-size:16px;margin:0 0 6px;display:flex;gap:10px;align-items:center}
  .desc{margin:0;color:#64748b;font-size:13px;line-height:1.4}
  .pill{display:inline-block;margin-top:10px;font-size:12px;padding:4px 10px;border-radius:999px;border:1px solid #e2e8f0;color:#334155}
</style>

<div class="inv-wrap">
  <h1 style="margin:0 0 6px;">Persediaan</h1>
  <div class="muted" style="margin-bottom:14px;"><b></b></div>

  <div class="grid">

    <a class="card" href="admin_stok_inout.php">
      <div class="title">↕️ Stok Masuk / Keluar</div>
      <p class="desc">Tambah atau kurangi stok produk/bahan</p>
      <span class="pill">Mutasi stok</span>
    </a>

    <a class="card" href="admin_riwayat_stok.php">
      <div class="title">🕘 Riwayat Stok</div>
      <p class="desc">Lihat semua histori perubahan stok: mutasi, opname, filter tanggal, pencarian.</p>
      <span class="pill">Histori & audit</span>
    </a>

    <a class="card" href="admin_stok_opname.php">
      <div class="title">🧮 Stok Opname</div>
      <p class="desc">Input stok fisik, koreksi stok sistem, dan catat selisihnya otomatis.</p>
      <span class="pill">Koreksi fisik</span>
    </a>

    <a class="card" href="admin_persediaan_bahan.php">
      <div class="title">🥕 Bahan</div>
      <p class="desc">Kelola data bahan: stok, satuan, minimum stok, aktif/nonaktif.</p>
      <span class="pill">Master bahan</span>
    </a>

    <a class="card" href="admin_persediaan_resep.php">
      <div class="title">🧾 Resep</div>
      <p class="desc">Atur resep/BOM untuk mengurangi stok bahan saat penjualan.</p>
      <span class="pill">Bill of Materials</span>
    </a>

  </div>

  <div class="muted" style="margin-top:14px;font-size:12px;">
  </div>
</div>

<?php require __DIR__ . '/../publik/partials/admin_layout_bottom.php'; ?>

<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/auth.php';
require_role(['kasir','admin']);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$user = auth_user();
$kasirId   = (int)($user['id'] ?? 0);
$kasirName = (string)($user['name'] ?? 'Kasir');

/* ===== Store (pattern MultiPOS) ===== */
$st = $pdo->prepare("
  SELECT s.id, s.name
  FROM users k
  JOIN stores s ON s.owner_admin_id = k.created_by
  WHERE k.id=? AND s.is_active=1
  LIMIT 1
");
$st->execute([$kasirId]);
$store = $st->fetch();
if (!$store) { http_response_code(400); exit('Kasir belum terhubung ke toko.'); }

$storeId   = (int)$store['id'];
$storeName = (string)$store['name'];

/* ===== Shift status ===== */
$sh = $pdo->prepare("
  SELECT id, opened_at
  FROM cashier_shifts
  WHERE store_id=? AND kasir_id=? AND status='open'
  ORDER BY id DESC
  LIMIT 1
");
$sh->execute([$storeId, $kasirId]);
$openShift = $sh->fetch();

$appName    = '';                 // top pill kanan (kalau kamu sudah hide)
$pageTitle  = 'Dashboard Kasir';
$activeMenu = 'kasir_dashboard';
require __DIR__ . '/../publik/partials/kasir_layout_top.php';
?>

<style>
  .dash-wrap{ max-width: 1100px; margin: 0 auto; }
  .hero{
    background:#fff;
    border:1px solid rgba(15,23,42,.10);
    border-radius:18px;
    padding:16px;
    box-shadow: 0 18px 50px rgba(15,23,42,.06);
  }
  .hero-top{
    display:flex;
    gap:12px;
    align-items:flex-start;
    justify-content:space-between;
    flex-wrap:wrap;
  }
  .h-title{ font-weight:1000; font-size:16px; margin:0; }
  .h-sub{ color:#64748b; font-size:12px; margin-top:4px; }
  .pill{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:8px 12px;
    border-radius:999px;
    border:1px solid rgba(15,23,42,.12);
    background:#fff;
    font-size:12px;
    font-weight:900;
    color:#0f172a;
  }
  .pill.ok{ background:rgba(34,197,94,.10); border-color:rgba(34,197,94,.25); color:#166534; }
  .pill.warn{ background:rgba(245,158,11,.10); border-color:rgba(245,158,11,.25); color:#92400e; }

  .actions{
    display:flex; gap:10px; flex-wrap:wrap;
    margin-top:12px;
  }
  .btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    padding:10px 14px;
    border-radius:999px;
    border:1px solid transparent;
    font-weight:950;
    cursor:pointer;
    text-decoration:none;
    font-size:13px;
  }
  .btn.primary{ background:#2563eb; border-color:#2563eb; color:#fff; }
  .btn.ghost{ background:#fff; border-color:rgba(15,23,42,.12); color:#0f172a; }
  .btn.danger{ background:#fee2e2; border-color:#fecaca; color:#b91c1c; }

  .grid{
    display:grid;
    grid-template-columns: 1fr 1fr;
    gap:14px;
    margin-top:14px;
  }
  @media (max-width: 1024px){
    .grid{ grid-template-columns: 1fr; }
  }

  .card{
    background:#fff;
    border:1px solid rgba(15,23,42,.10);
    border-radius:18px;
    padding:14px;
    box-shadow: 0 18px 50px rgba(15,23,42,.05);
  }
  .card h3{
    margin:0 0 8px 0;
    font-size:14px;
    font-weight:1000;
    display:flex;
    align-items:center;
    gap:8px;
  }
  .card p{ margin:0 0 10px 0; color:#334155; font-size:13px; line-height:1.5; }
  .card ul{ margin:0; padding-left:18px; }
  .card li{ color:#334155; font-size:13px; line-height:1.55; margin:6px 0; }

  .steps{ counter-reset: step; margin:0; padding:0; list-style:none; }
  .steps li{
    counter-increment: step;
    padding:10px 10px 10px 44px;
    border:1px solid rgba(15,23,42,.08);
    border-radius:14px;
    margin:10px 0;
    position:relative;
    background:#f8fafc;
  }
  .steps li::before{
    content: counter(step);
    position:absolute;
    left:10px; top:10px;
    width:26px; height:26px;
    border-radius:999px;
    background:#2563eb;
    color:#fff;
    font-weight:1000;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:12px;
  }
  .note{
    margin-top:10px;
    padding:10px 12px;
    border-radius:14px;
    border:1px solid rgba(15,23,42,.10);
    background:#fff7ed;
    color:#9a3412;
    font-size:12px;
    font-weight:800;
  }
  .okbox{
    margin-top:10px;
    padding:10px 12px;
    border-radius:14px;
    border:1px solid rgba(15,23,42,.10);
    background:#eff6ff;
    color:#1e3a8a;
    font-size:12px;
    font-weight:800;
  }
  .mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
</style>

<div class="dash-wrap">

  <div class="hero">
    <div class="hero-top">
      <div>
        <h1 class="h-title">Dashboard Kasir</h1>
        <div class="h-sub">
          Halo, <b><?= htmlspecialchars($kasirName) ?></b> · Toko: <b><?= htmlspecialchars($storeName) ?></b>
        </div>

        <?php if ($openShift): ?>
          <div class="pill ok" style="margin-top:10px;">
            ✅ Shift sedang <b>OPEN</b> · ID #<?= (int)$openShift['id'] ?> · Dibuka: <?= htmlspecialchars((string)$openShift['opened_at']) ?>
          </div>
        <?php else: ?>
          <div class="pill warn" style="margin-top:10px;">
            ⚠️ Shift <b>BELUM</b> dibuka · Silakan buka shift sebelum transaksi
          </div>
        <?php endif; ?>
      </div>

      <div class="actions">
        <a class="btn primary" href="kasir_pos.php">🧾 Mulai Transaksi (POS)</a>
        <a class="btn ghost" href="kasir_customers.php">👥 Daftar Pelanggan</a>
        <a class="btn ghost" href="kasir_shift.php">🕒 Shift</a>
        <a class="btn ghost" href="kasir_transactions.php">📄 Riwayat</a>
      </div>
    </div>

    <div class="okbox">
      Tips cepat: Kalau tombol <b>Bayar</b> tidak aktif, biasanya karena <b>shift belum dibuka</b> atau <b>uang masuk kurang</b>.
    </div>
  </div>

  <div class="grid">

    <div class="card">
      <h3>📌 Alur Kerja Kasir (SOP Harian)</h3>
      <ol class="steps">
        <li><b>Buka Shift</b> di menu <b>Shift</b> sebelum melayani transaksi pertama.</li>
        <li><b>Masuk ke POS</b> → pilih produk dari katalog → produk masuk ke keranjang.</li>
        <li><b>Isi data pelanggan</b> bila ada (nama/HP) → pilih tipe pesanan (opsional).</li>
        <li><b>Pilih metode pembayaran</b> (CASH/QRIS) → masukkan uang masuk (CASH) → cek kembalian.</li>
        <li><b>Tekan Bayar</b> → sistem simpan transaksi → cetak/lihat struk.</li>
        <li>Jika ada pembatalan sebelum bayar → gunakan <b>Kosongkan</b> untuk menghapus keranjang.</li>
        <li><b>Tutup Shift</b> setelah operasional selesai → pastikan uang kas sesuai.</li>
      </ol>
      <div class="note">
        Penting: Jangan tutup shift sebelum semua transaksi hari itu selesai dicatat.
      </div>
    </div>

    <div class="card">
      <h3>🧾 Cara Pakai POS (Langkah Rinci)</h3>
      <ul>
        <li><b>Cari produk:</b> gunakan kolom “Cari produk…”. Produk akan terfilter otomatis.</li>
        <li><b>Tambah produk:</b> klik kartu produk → qty bertambah di keranjang.</li>
        <li><b>Ubah qty:</b> tekan tombol <span class="mono">+</span> / <span class="mono">-</span> pada item keranjang.</li>
        <li><b>Keranjang:</b> bagian keranjang punya scroll sendiri (desktop & mobile).</li>
        <li><b>Metode CASH:</b> isi “Uang Masuk” → kembalian dihitung otomatis → Bayar aktif jika uang cukup.</li>
        <li><b>Metode QRIS:</b> kasir cukup konfirmasi pembayaran → tekan <b>Bayar (QRIS)</b>.</li>
        <li><b>Struk:</b> setelah sukses, sistem akan arahkan ke halaman struk transaksi.</li>
      </ul>
    </div>

    <div class="card">
      <h3>👤 Pelanggan (Cara Input & Manfaat)</h3>
      <p>Data pelanggan membantu toko mengenali pelanggan tetap dan memudahkan layanan.</p>
      <ul>
        <li><b>Input pelanggan</b> di POS melalui tombol <b>Cari</b> (Pelanggan).</li>
        <li>Kasir bisa mengisi: <b>Nama</b>, <b>No HP</b>, <b>Tipe Pesanan</b>.</li>
        <li>Pelanggan akan tersimpan otomatis saat transaksi sukses.</li>
        <li>Lihat daftar pelanggan di menu <b>Pelanggan</b> (sidebar).</li>
      </ul>
      <div class="okbox">
        Tips: jika pelanggan sudah ada, ketik minimal 2 huruf/numerik untuk memunculkan saran (autocomplete).
      </div>
    </div>

    <div class="card">
      <h3>✅ Wewenang Kasir (Apa yang Boleh & Tidak)</h3>
      <p><b>Boleh dilakukan kasir:</b></p>
      <ul>
        <li>Melakukan transaksi penjualan (POS).</li>
        <li>Mengisi/memilih pelanggan saat transaksi.</li>
        <li>Melihat riwayat transaksi & struk.</li>
        <li>Mencatat kas masuk/keluar (sesuai menu Operasional).</li>
        <li>Membuka & menutup shift (sesuai SOP).</li>
      </ul>
      <p style="margin-top:10px;"><b>Tidak disarankan/harus minta admin:</b></p>
      <ul>
        <li>Ubah harga produk / stok / kategori (biasanya admin).</li>
        <li>Ubah pengaturan sistem penting (printer, tampilan, akun).</li>
        <li>Menghapus transaksi yang sudah tersimpan (harus prosedur khusus).</li>
      </ul>
      <div class="note">
        Jika ada transaksi salah setelah “Bayar”, catat kejadian dan laporkan ke admin/owner sesuai SOP toko.
      </div>
    </div>

    <div class="card">
      <h3>🧪 Checklist Sebelum & Sesudah Shift</h3>
      <ul>
        <li><b>Sebelum buka:</b> pastikan internet stabil (jika QRIS), printer siap (jika cetak), stok sesuai.</li>
        <li><b>Buka shift:</b> input saldo awal (jika aplikasi kamu pakai) dan pastikan status OPEN.</li>
        <li><b>Selama operasional:</b> pastikan tiap transaksi tercatat, jangan “manual” tanpa POS.</li>
        <li><b>Sebelum tutup:</b> cek total transaksi hari ini dan cocokkan uang kas fisik.</li>
        <li><b>Tutup shift:</b> lakukan penutupan shift dan simpan laporan jika ada.</li>
      </ul>
    </div>

    <div class="card">
      <h3>🛠️ Troubleshooting (Masalah Umum)</h3>
      <ul>
        <li><b>Bayar tidak bisa:</b> cek shift sudah OPEN, keranjang tidak kosong, CASH uang masuk ≥ total.</li>
        <li><b>Stok kurang:</b> sistem akan menolak; minta admin update stok atau ganti produk.</li>
        <li><b>QRIS dipilih:</b> pastikan pelanggan sudah bayar, lalu tekan “Bayar (QRIS)”.</li>
        <li><b>Halaman berat di HP:</b> tutup aplikasi lain, reload, dan pastikan jaringan stabil.</li>
      </ul>
      <div class="okbox">
        Kalau error muncul, catat pesan errornya + jam kejadian untuk memudahkan pengecekan.
      </div>
    </div>

  </div>

</div>

<?php require __DIR__ . '/../publik/partials/kasir_layout_bottom.php'; ?>

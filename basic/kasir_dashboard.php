<?php
declare(strict_types=1);

require __DIR__ . '/kasir_bootstrap.php';

$pageTitle  = 'Dashboard Kasir';
$activeMenu = 'kasir_dashboard';

$kasirId = (int)(auth_user()['id'] ?? 0);

// Shift OPEN (jika ada)
$stOpen = $pdo->prepare("
  SELECT *
  FROM cashier_shifts
  WHERE store_id=? AND kasir_id=? AND status='open'
  ORDER BY id DESC
  LIMIT 1
");
$stOpen->execute([$storeId, $kasirId]);
$openShift = $stOpen->fetch(PDO::FETCH_ASSOC);

// Shift terakhir (untuk info)
$stLast = $pdo->prepare("
  SELECT *
  FROM cashier_shifts
  WHERE store_id=? AND kasir_id=?
  ORDER BY id DESC
  LIMIT 1
");
$stLast->execute([$storeId, $kasirId]);
$lastShift = $stLast->fetch(PDO::FETCH_ASSOC);

function rupiah(int $n): string {
  return 'Rp ' . number_format($n, 0, ',', '.');
}

include __DIR__ . '/partials/kasir_layout_top.php';
?>

<style>
  .wrapGrid{
    display:grid;
    grid-template-columns: .95fr 1.05fr;
    gap:14px;
  }
  @media (max-width: 1000px){
    .wrapGrid{ grid-template-columns:1fr; }
  }

  .card{
    background:#fff;
    border:1px solid rgba(15,23,42,.10);
    border-radius:18px;
    padding:14px;
    box-shadow:0 18px 50px rgba(15,23,42,.05);
    min-width:0;
  }
  .h2{ margin:0 0 10px 0; font-size:16px; font-weight:1000; }
  .muted{ color:#64748b; font-size:13px; line-height:1.6; }

  .pill{
    display:inline-flex;align-items:center;gap:8px;
    padding:8px 10px;border-radius:999px;
    border:1px solid rgba(15,23,42,.10);
    background:#fff;
    font-size:12px;font-weight:900;
    white-space:nowrap;
  }
  .pill.ok{ background:rgba(22,163,74,.10); border-color:rgba(22,163,74,.25); color:#166534; }
  .pill.warn{ background:rgba(245,158,11,.10); border-color:rgba(245,158,11,.25); color:#92400e; }
  .pill.info{ background:rgba(37,99,235,.08); border-color:rgba(37,99,235,.18); color:#1d4ed8; }

  .actions{ display:flex; gap:10px; flex-wrap:wrap; margin-top:12px; }
  .btn{
    display:inline-flex;align-items:center;justify-content:center;gap:8px;
    padding:10px 14px;border-radius:14px;border:1px solid rgba(15,23,42,.10);
    background:#fff;font-weight:1000;text-decoration:none;cursor:pointer;
  }
  .btn.primary{ background:#2563eb; border-color:#2563eb; color:#fff; }
  .btn:active{ transform:translateY(1px); }

  .warnBox{
    margin-top:12px;
    padding:10px 12px;
    border-radius:16px;
    border:1px solid rgba(245,158,11,.25);
    background:rgba(245,158,11,.10);
    color:#92400e;
    font-weight:900;
    font-size:12px;
    line-height:1.6;
  }

  details{
    border:1px solid rgba(15,23,42,.08);
    border-radius:16px;
    padding:10px 12px;
    background:#fff;
  }
  details + details{ margin-top:10px; }
  summary{
    cursor:pointer;
    font-weight:1000;
    list-style:none;
  }
  summary::-webkit-details-marker{ display:none; }
  .list{ margin:8px 0 0 0; padding-left:18px; }
  .list li{ margin:8px 0; line-height:1.55; color:#334155; font-size:13px; }
</style>

<div class="wrapGrid">

  <!-- ===== KIRI: STATUS + AKSI CEPAT ===== -->
  <div>
    <div class="card">
      <h2 class="h2">Status Shift</h2>

      <?php if ($openShift): ?>
        <div class="muted">
          <b>OPEN</b> • Shift #<?= (int)$openShift['id'] ?><br>
          Dibuka: <?= htmlspecialchars((string)($openShift['opened_at'] ?? '')) ?><br>
          Opening cash: <b><?= rupiah((int)($openShift['opening_cash'] ?? 0)) ?></b>
        </div>

        <div style="margin-top:10px;">
          <span class="pill ok">✅ Shift aktif</span>
        </div>
      <?php else: ?>
        <div class="muted">
          Shift belum dibuka. Kamu <b>tidak bisa transaksi</b> sebelum membuka shift.
        </div>

        <div style="margin-top:10px;">
          <span class="pill warn">⚠️ Shift belum OPEN</span>
        </div>

        <div class="warnBox">
          Urutan kerja: <b>Buka Shift → POS → Bayar → Cetak Struk → Tutup Shift</b>
        </div>
      <?php endif; ?>

      <div class="actions">
        <a class="btn primary" href="kasir_pos.php" <?= $openShift ? '' : 'title="Buka shift dulu untuk transaksi"' ?>>
          🧾 POS
        </a>
        <a class="btn" href="kasir_shift.php">⏱️ Shift</a>
      </div>

      <div style="margin-top:10px;">
        <span class="pill info">ℹ️ Mode BASIC: cash-only</span>
      </div>
    </div>

    <div style="height:14px"></div>

    <div class="card">
      <h2 class="h2">Ringkas Tugas Harian</h2>
      <ul class="list">
        <li>Buka shift & isi <b>opening cash</b>.</li>
        <li>Transaksi via POS, pastikan item & qty benar.</li>
        <li>Terima uang, berikan kembalian, <b>cetak struk</b>.</li>
        <li>Tutup shift & isi <b>closing cash</b>.</li>
      </ul>

      <?php if ($lastShift): ?>
        <div class="muted" style="margin-top:10px;">
          Shift terakhir: <b>#<?= (int)$lastShift['id'] ?></b> (<?= htmlspecialchars((string)$lastShift['status']) ?>)
          <?php if (!empty($lastShift['closed_at'])): ?>
            • closed <?= htmlspecialchars((string)$lastShift['closed_at']) ?>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ===== KANAN: PANDUAN KASIR ===== -->
  <div>
    <div class="card">
      <h2 class="h2">Panduan Kasir • Mode BASIC</h2>
      <div class="muted" style="margin-top:-6px;">
        Ikuti panduan ini supaya pemakaian rapi, uang laci aman, dan laporan akurat.
      </div>

      <div style="height:10px"></div>

      <details open>
        <summary>✅ Tata cara penggunaan (urutan kerja)</summary>
        <ol class="list">
          <li><b>Login</b> menggunakan akun kasir.</li>
          <li><b>Buka Shift</b> di menu <b>Shift</b> → isi <b>Opening Cash</b> (uang awal laci).</li>
          <li>Masuk menu <b>POS</b> → klik produk untuk masuk keranjang → atur qty bila perlu.</li>
          <li>Klik <b>Bayar</b> → input uang diterima → sistem hitung kembalian otomatis.</li>
          <li>Klik <b>Simpan & Cetak Struk</b> → berikan struk ke pelanggan.</li>
          <li>Di akhir jam kerja: menu <b>Shift</b> → isi <b>Closing Cash</b> → <b>Tutup Shift</b>.</li>
        </ol>
      </details>

      <details>
        <summary>🧑‍⚖️ Wewenang kasir (boleh & tidak boleh)</summary>
        <ul class="list">
          <li><b>Boleh</b>: transaksi penjualan, cetak struk, buka/tutup shift.</li>
          <li><b>Boleh</b>: melihat status shift & transaksi sendiri.</li>
          <li><b>Tidak boleh</b>: ubah harga, tambah/hapus produk, koreksi stok manual.</li>
          <li><b>Tidak boleh</b>: ubah akun pengguna atau pengaturan toko.</li>
          <li>Kasus khusus (retur, batal setelah tersimpan): <b>laporkan Admin</b>.</li>
        </ul>
      </details>

      <details>
        <summary>🧾 Tugas kasir harian (checklist)</summary>
        <ul class="list">
          <li>Pastikan <b>opening cash</b> sesuai uang awal laci.</li>
          <li>Setiap transaksi: cek item & qty sebelum bayar.</li>
          <li>Uang diterima & kembalian harus tepat.</li>
          <li>Berikan <b>struk</b> untuk setiap transaksi.</li>
          <li>Jika pelanggan batal sebelum bayar: kosongkan keranjang, jangan simpan transaksi.</li>
          <li>Di akhir shift: hitung uang laci → isi <b>closing cash</b> → tutup shift.</li>
        </ul>
      </details>

      <details>
        <summary>⚠️ Aturan penting & penanganan masalah</summary>
        <ul class="list">
          <li><b>Shift wajib dibuka</b> sebelum transaksi dan <b>ditutup</b> saat selesai kerja.</li>
          <li>Jika sistem/error: catat transaksi manual & laporkan admin.</li>
          <li>Jika stok tidak sesuai/minus: lanjutkan sesuai SOP toko, lalu laporkan admin untuk koreksi.</li>
          <li>Jika salah input transaksi yang sudah tersimpan: <b>jangan edit sendiri</b> — minta admin lakukan void/retur sesuai kebijakan.</li>
        </ul>
      </details>

      <div class="muted" style="margin-top:10px;">
        Tip: patokan utama = <b>Buka Shift → POS → Bayar → Cetak → Tutup Shift</b>.
      </div>
    </div>
  </div>

</div>

<?php include __DIR__ . '/partials/kasir_layout_bottom.php'; ?>

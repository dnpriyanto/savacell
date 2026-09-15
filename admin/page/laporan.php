<?php
// ============================================================
//  admin/pages/laporan.php — Sales Report (Phase 4: View 6)
// ============================================================
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
requireLogin();
$pageTitle = 'Laporan Penjualan';

$dateFrom = $_GET['dari']    ?? date('Y-m-01');
$dateTo   = $_GET['sampai']  ?? date('Y-m-d');
$kasirId  = (int)($_GET['kasir'] ?? 0);

$whereClause = "t.status='selesai' AND DATE(t.tanggal_transaksi) BETWEEN ? AND ?";
$types       = 'ss';
$params      = [$dateFrom, $dateTo];

if ($kasirId) {
    $whereClause .= " AND t.id_pegawai = ?";
    $types .= 'i';
    $params[] = $kasirId;
}

$rows = dbFetchAll("
    SELECT t.*, p.nama_pelanggan, pg.nama_pegawai,
           COUNT(dt.id_detail) AS item_count
    FROM transaksi t
    LEFT JOIN pelanggan p  ON t.id_pelanggan = p.id_pelanggan
    JOIN pegawai pg         ON t.id_pegawai = pg.id_pegawai
    JOIN detail_transaksi dt ON t.id_transaksi = dt.id_transaksi
    WHERE $whereClause
    GROUP BY t.id_transaksi
    ORDER BY t.tanggal_transaksi DESC
", $types, $params);

$summary = dbFetchOne("
    SELECT SUM(total_bayar) AS total_revenue, COUNT(*) AS total_trx
    FROM transaksi t
    WHERE $whereClause
", $types, $params);

$kasirList = dbFetchAll("SELECT id_pegawai, nama_pegawai FROM pegawai ORDER BY nama_pegawai");

// Detail per transaksi (for modal)
$trxDetail = null;
if (isset($_GET['detail'])) {
    $tid = (int)$_GET['detail'];
    $trxDetail = [
        'header' => dbFetchOne("SELECT t.*, p.nama_pelanggan, pg.nama_pegawai FROM transaksi t LEFT JOIN pelanggan p ON t.id_pelanggan=p.id_pelanggan JOIN pegawai pg ON t.id_pegawai=pg.id_pegawai WHERE t.id_transaksi=?", 'i', [$tid]),
        'items'  => dbFetchAll("SELECT dt.*, m.nama_produk, m.kategori FROM detail_transaksi dt JOIN menu m ON dt.id_menu=m.id_menu WHERE dt.id_transaksi=?", 'i', [$tid]),
    ];
}

require_once __DIR__ . '/../includes/header.php';
?>
<style>
@media print {
  #sidebar, header, .no-print { display: none !important; }
  .print-area { margin: 0 !important; padding: 0 !important; }
  body { background: white !important; color: black !important; }
  table { font-size: 11px; }
  .card-glass { background: white !important; border: 1px solid #eee !important; color: black !important; }
  td, th { color: black !important; border-color: #ddd !important; }
}
</style>

<!-- Filter Bar -->
<form method="GET" class="card-glass rounded-xl p-4 mb-5 no-print flex flex-wrap gap-3 items-end">
  <div>
    <label class="text-xs text-slate-400 block mb-1">Dari Tanggal</label>
    <input type="date" name="dari" value="<?= e($dateFrom) ?>" class="px-3 py-2 rounded-xl text-sm">
  </div>
  <div>
    <label class="text-xs text-slate-400 block mb-1">Sampai Tanggal</label>
    <input type="date" name="sampai" value="<?= e($dateTo) ?>" class="px-3 py-2 rounded-xl text-sm">
  </div>
  <div>
    <label class="text-xs text-slate-400 block mb-1">Kasir</label>
    <select name="kasir" class="px-3 py-2 rounded-xl text-sm">
      <option value="">Semua Kasir</option>
      <?php foreach ($kasirList as $k): ?>
      <option value="<?= $k['id_pegawai'] ?>" <?= $kasirId == $k['id_pegawai'] ? 'selected' : '' ?>><?= e($k['nama_pegawai']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button type="submit" class="btn-primary px-4 py-2 rounded-xl text-white text-sm font-medium">
    <i class="fas fa-filter mr-1.5"></i> Filter
  </button>
  <a href="laporan.php" class="px-4 py-2 rounded-xl text-sm text-slate-400 hover:bg-white/10 transition-colors">Reset</a>
  <button type="button" onclick="window.print()" class="ml-auto px-4 py-2 rounded-xl text-sm font-medium flex items-center gap-2 hover:bg-white/10 transition-colors" style="border: 1px solid rgba(255,255,255,0.1); color: #e2e8f0;">
    <i class="fas fa-print"></i> Cetak Laporan
  </button>
</form>

<!-- Summary Cards -->
<div class="grid grid-cols-3 gap-4 mb-5">
  <div class="card-glass rounded-xl p-4">
    <p class="text-xs text-slate-500 mb-1">Total Pendapatan</p>
    <p class="text-xl font-extrabold" style="color: #00c4f5;"><?= rupiah($summary['total_revenue'] ?? 0) ?></p>
    <p class="text-xs text-slate-600 mt-1"><?= tglIndo($dateFrom) ?> – <?= tglIndo($dateTo) ?></p>
  </div>
  <div class="card-glass rounded-xl p-4">
    <p class="text-xs text-slate-500 mb-1">Total Transaksi</p>
    <p class="text-xl font-extrabold text-white"><?= number_format($summary['total_trx'] ?? 0) ?></p>
    <p class="text-xs text-slate-600 mt-1">Transaksi selesai</p>
  </div>
  <div class="card-glass rounded-xl p-4">
    <p class="text-xs text-slate-500 mb-1">Rata-rata / Transaksi</p>
    <?php $avg = ($summary['total_trx'] ?? 0) > 0 ? ($summary['total_revenue'] / $summary['total_trx']) : 0; ?>
    <p class="text-xl font-extrabold text-white"><?= rupiah($avg) ?></p>
    <p class="text-xs text-slate-600 mt-1">Average order value</p>
  </div>
</div>

<!-- Print Header (only visible when printing) -->
<div class="hidden print:block mb-6 text-center">
  <h1 class="text-xl font-bold">LAPORAN PENJUALAN — SAVA CELL</h1>
  <p class="text-sm">Periode: <?= tglIndo($dateFrom) ?> s/d <?= tglIndo($dateTo) ?></p>
  <p class="text-sm">Dicetak: <?= date('d/m/Y H:i') ?></p>
</div>

<!-- Table -->
<div class="card-glass rounded-2xl overflow-hidden">
  <div class="px-5 py-4 border-b" style="border-color: rgba(255,255,255,0.06);">
    <h3 class="font-bold text-white text-sm">Riwayat Transaksi (<?= count($rows) ?> data)</h3>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead>
        <tr class="text-xs text-slate-500 uppercase" style="background: rgba(255,255,255,0.03);">
          <th class="px-4 py-3 text-left">ID</th>
          <th class="px-4 py-3 text-left">Tanggal & Waktu</th>
          <th class="px-4 py-3 text-left">Pelanggan</th>
          <th class="px-4 py-3 text-left">Kasir</th>
          <th class="px-4 py-3 text-center">Items</th>
          <th class="px-4 py-3 text-right">Total</th>
          <th class="px-4 py-3 text-center no-print">Detail</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
        <tr class="table-row border-t" style="border-color: rgba(255,255,255,0.04);">
          <td class="px-4 py-3 font-mono text-xs text-slate-400">#<?= $row['id_transaksi'] ?></td>
          <td class="px-4 py-3 text-slate-300 text-xs"><?= date('d/m/Y H:i', strtotime($row['tanggal_transaksi'])) ?></td>
          <td class="px-4 py-3 text-white"><?= e($row['nama_pelanggan'] ?? '—') ?></td>
          <td class="px-4 py-3 text-slate-400 text-xs"><?= e($row['nama_pegawai']) ?></td>
          <td class="px-4 py-3 text-center">
            <span class="px-2 py-0.5 rounded-full text-xs" style="background: rgba(0,196,245,0.1); color:#00c4f5;"><?= $row['item_count'] ?> item</span>
          </td>
          <td class="px-4 py-3 text-right font-bold" style="color:#00c4f5;"><?= rupiah($row['total_bayar']) ?></td>
          <td class="px-4 py-3 text-center no-print">
            <a href="?dari=<?= $dateFrom ?>&sampai=<?= $dateTo ?>&detail=<?= $row['id_transaksi'] ?>"
              class="text-xs px-2.5 py-1 rounded-lg hover:bg-white/10 transition-colors" style="color:#00c4f5; border: 1px solid rgba(0,196,245,0.25);">
              <i class="fas fa-eye"></i>
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
        <tr><td colspan="7" class="px-4 py-10 text-center text-slate-600">
          <i class="fas fa-file-invoice text-3xl mb-2 block opacity-20"></i>Tidak ada transaksi pada periode ini
        </td></tr>
        <?php endif; ?>
      </tbody>
      <tfoot>
        <tr class="border-t" style="border-color: rgba(0,196,245,0.2); background: rgba(0,196,245,0.04);">
          <td colspan="5" class="px-4 py-3 text-right text-xs font-semibold text-slate-400">TOTAL PENDAPATAN</td>
          <td class="px-4 py-3 text-right font-extrabold text-base" style="color:#00c4f5;"><?= rupiah($summary['total_revenue'] ?? 0) ?></td>
          <td class="no-print"></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<!-- Detail Modal -->
<?php if ($trxDetail && $trxDetail['header']): ?>
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm no-print" id="detailModal">
  <div class="w-full max-w-lg rounded-2xl overflow-hidden" style="background: #0d1520; border: 1px solid rgba(0,196,245,0.2);">
    <div class="px-5 py-4 border-b flex items-center justify-between" style="border-color: rgba(255,255,255,0.06);">
      <h3 class="font-bold text-white">Detail Transaksi #<?= $trxDetail['header']['id_transaksi'] ?></h3>
      <a href="laporan.php?dari=<?= $dateFrom ?>&sampai=<?= $dateTo ?>" class="text-slate-400 hover:text-white"><i class="fas fa-times"></i></a>
    </div>
    <div class="p-5 space-y-3 text-sm">
      <div class="grid grid-cols-2 gap-3 text-xs">
        <div><span class="text-slate-500">Tanggal:</span> <span class="text-white ml-2"><?= date('d/m/Y H:i', strtotime($trxDetail['header']['tanggal_transaksi'])) ?></span></div>
        <div><span class="text-slate-500">Kasir:</span> <span class="text-white ml-2"><?= e($trxDetail['header']['nama_pegawai']) ?></span></div>
        <div><span class="text-slate-500">Pelanggan:</span> <span class="text-white ml-2"><?= e($trxDetail['header']['nama_pelanggan'] ?? '—') ?></span></div>
        <div><span class="text-slate-500">Bayar:</span> <span class="text-white ml-2"><?= rupiah($trxDetail['header']['bayar']) ?></span></div>
      </div>
      <table class="w-full text-xs mt-3">
        <thead><tr class="text-slate-500" style="border-bottom: 1px solid rgba(255,255,255,0.06);">
          <th class="py-2 text-left">Produk</th>
          <th class="py-2 text-center">Qty</th>
          <th class="py-2 text-left">No. Tujuan</th>
          <th class="py-2 text-right">Subtotal</th>
        </tr></thead>
        <tbody>
          <?php foreach ($trxDetail['items'] as $it): ?>
          <tr style="border-bottom: 1px solid rgba(255,255,255,0.04);">
            <td class="py-2 text-white"><?= e($it['nama_produk']) ?></td>
            <td class="py-2 text-center text-slate-400"><?= $it['jumlah'] ?>x</td>
            <td class="py-2 font-mono text-slate-400"><?= e($it['nomor_tujuan'] ?? '—') ?></td>
            <td class="py-2 text-right font-semibold" style="color:#00c4f5;"><?= rupiah($it['subtotal']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="flex justify-between pt-3 border-t font-bold" style="border-color: rgba(255,255,255,0.08);">
        <span class="text-slate-400">Total</span>
        <span style="color:#00c4f5;"><?= rupiah($trxDetail['header']['total_bayar']) ?></span>
      </div>
      <div class="flex justify-between">
        <span class="text-slate-400 text-xs">Kembalian</span>
        <span class="text-emerald-400 text-xs"><?= rupiah($trxDetail['header']['kembalian']) ?></span>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

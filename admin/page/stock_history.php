<?php
// ============================================================
//  admin/page/stock_history.php — Riwayat Perubahan Stok
// ============================================================
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
requireLogin();
$pageTitle = 'Riwayat Perubahan Stok';

$filterProduct = $_GET['produk'] ?? '';
$dateFrom = $_GET['dari'] ?? date('Y-m-01');
$dateTo   = $_GET['sampai'] ?? date('Y-m-d');

// Ambil riwayat stok
$history = csvReadAll('stock_history');

// Filter by date range
$history = array_filter($history, function($h) use ($dateFrom, $dateTo) {
    $d = substr($h['tanggal'] ?? '', 0, 10);
    return $d >= $dateFrom && $d <= $dateTo;
});

// Filter by product
if ($filterProduct) {
    $history = array_filter($history, fn($h) => 
        stripos($h['nama_produk'] ?? '', $filterProduct) !== false
    );
}

$history = array_values($history);
usort($history, fn($a, $b) => strcmp($b['tanggal'] ?? '', $a['tanggal'] ?? ''));

// Statistik
$totalMasuk = 0;
$totalKeluar = 0;
foreach ($history as $h) {
    $change = (int)($h['perubahan'] ?? 0);
    if ($change > 0) $totalMasuk += $change;
    else $totalKeluar += abs($change);
}

// Data chart per hari
$dailyChanges = [];
foreach ($history as $h) {
    $d = substr($h['tanggal'] ?? '', 0, 10);
    if (!isset($dailyChanges[$d])) $dailyChanges[$d] = ['masuk' => 0, 'keluar' => 0];
    $change = (int)($h['perubahan'] ?? 0);
    if ($change > 0) $dailyChanges[$d]['masuk'] += $change;
    else $dailyChanges[$d]['keluar'] += abs($change);
}
ksort($dailyChanges);

// Daftar produk unik
$allMenu = csvReadAll('menu');

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Filter Bar -->
<form method="GET" class="card-glass rounded-xl p-4 mb-5 flex flex-wrap gap-3 items-end">
  <div>
    <label class="text-xs text-slate-400 block mb-1">Dari Tanggal</label>
    <input type="date" name="dari" value="<?= e($dateFrom) ?>" class="px-3 py-2 rounded-xl text-sm">
  </div>
  <div>
    <label class="text-xs text-slate-400 block mb-1">Sampai Tanggal</label>
    <input type="date" name="sampai" value="<?= e($dateTo) ?>" class="px-3 py-2 rounded-xl text-sm">
  </div>
  <div>
    <label class="text-xs text-slate-400 block mb-1">Cari Produk</label>
    <input type="text" name="produk" value="<?= e($filterProduct) ?>" placeholder="Nama produk..." class="px-3 py-2 rounded-xl text-sm">
  </div>
  <button type="submit" class="btn-primary px-4 py-2 rounded-xl text-white text-sm font-medium">
    <i class="fas fa-filter mr-1.5"></i> Filter
  </button>
  <a href="stock_history.php" class="px-4 py-2 rounded-xl text-sm text-slate-400 hover:bg-white/10 transition-colors">Reset</a>
</form>

<!-- Summary Cards -->
<div class="grid grid-cols-3 gap-4 mb-6">
  <div class="card-glass rounded-2xl p-5">
    <div class="flex items-center justify-between mb-2">
      <p class="text-xs text-slate-500">Total Perubahan</p>
      <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-cyan-500 to-blue-600 flex items-center justify-center">
        <i class="fas fa-history text-white text-xs"></i>
      </div>
    </div>
    <p class="text-xl font-extrabold text-white"><?= number_format(count($history)) ?> kali</p>
  </div>
  <div class="card-glass rounded-2xl p-5">
    <div class="flex items-center justify-between mb-2">
      <p class="text-xs text-slate-500">Stok Masuk</p>
      <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center">
        <i class="fas fa-arrow-up text-white text-xs"></i>
      </div>
    </div>
    <p class="text-xl font-extrabold text-emerald-400">+<?= number_format($totalMasuk) ?></p>
  </div>
  <div class="card-glass rounded-2xl p-5">
    <div class="flex items-center justify-between mb-2">
      <p class="text-xs text-slate-500">Stok Keluar</p>
      <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-red-500 to-rose-600 flex items-center justify-center">
        <i class="fas fa-arrow-down text-white text-xs"></i>
      </div>
    </div>
    <p class="text-xl font-extrabold text-red-400">-<?= number_format($totalKeluar) ?></p>
  </div>
</div>

<!-- Stock Movement Chart -->
<div class="card-glass rounded-2xl p-5 mb-6">
  <h3 class="font-bold text-white text-sm mb-4">Pergerakan Stok Harian</h3>
  <canvas id="stockChart" height="100"></canvas>
</div>

<!-- History Table -->
<div class="card-glass rounded-2xl overflow-hidden">
  <div class="px-5 py-4 border-b flex items-center justify-between" style="border-color: rgba(255,255,255,0.06);">
    <h3 class="font-bold text-white text-sm">Riwayat Perubahan Stok (<?= count($history) ?> data)</h3>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead>
        <tr class="text-xs text-slate-500 uppercase" style="background: rgba(255,255,255,0.03);">
          <th class="px-5 py-3 text-left">Tanggal</th>
          <th class="px-5 py-3 text-left">Produk</th>
          <th class="px-5 py-3 text-center">Stok Sebelum</th>
          <th class="px-5 py-3 text-center">Perubahan</th>
          <th class="px-5 py-3 text-center">Stok Sesudah</th>
          <th class="px-5 py-3 text-left">Keterangan</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($history as $h): ?>
        <?php $change = (int)($h['perubahan'] ?? 0); ?>
        <tr class="table-row border-t" style="border-color: rgba(255,255,255,0.04);">
          <td class="px-5 py-3 text-slate-400 text-xs"><?= date('d/m/Y H:i', strtotime($h['tanggal'])) ?></td>
          <td class="px-5 py-3">
            <span class="text-white font-medium"><?= e($h['nama_produk'] ?? '') ?></span>
          </td>
          <td class="px-5 py-3 text-center text-slate-300 font-mono"><?= $h['stok_sebelum'] ?></td>
          <td class="px-5 py-3 text-center">
            <span class="px-2.5 py-1 rounded-full text-xs font-bold <?= $change >= 0 ? 'bg-emerald-500/15 text-emerald-400' : 'bg-red-500/15 text-red-400' ?>">
              <?= $change >= 0 ? '+' : '' ?><?= $change ?>
            </span>
          </td>
          <td class="px-5 py-3 text-center text-white font-bold font-mono"><?= $h['stok_sesudah'] ?></td>
          <td class="px-5 py-3 text-xs text-slate-400"><?= e($h['keterangan'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($history)): ?>
        <tr><td colspan="6" class="px-5 py-10 text-center text-slate-600">
          <i class="fas fa-history text-3xl mb-2 block opacity-20"></i>Belum ada riwayat perubahan stok
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const stockLabels = <?= json_encode(array_map(fn($d) => date('d/m', strtotime($d)), array_keys($dailyChanges))) ?>;
const stockMasuk = <?= json_encode(array_values(array_map(fn($d) => $d['masuk'], $dailyChanges))) ?>;
const stockKeluar = <?= json_encode(array_values(array_map(fn($d) => $d['keluar'], $dailyChanges))) ?>;

new Chart(document.getElementById('stockChart'), {
  type: 'bar',
  data: {
    labels: stockLabels,
    datasets: [
      {
        label: 'Stok Masuk',
        data: stockMasuk,
        backgroundColor: 'rgba(16,185,129,0.6)',
        borderRadius: 6,
        barPercentage: 0.6,
      },
      {
        label: 'Stok Keluar',
        data: stockKeluar,
        backgroundColor: 'rgba(239,68,68,0.5)',
        borderRadius: 6,
        barPercentage: 0.6,
      }
    ]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { labels: { color: '#94a3b8', font: { size: 11 } } }
    },
    scales: {
      x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#64748b', font: { size: 10 } } },
      y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#64748b', font: { size: 10 }, stepSize: 1 } }
    }
  }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

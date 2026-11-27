<?php
// ============================================================
//  admin/page/laba_rugi.php — Laporan Laba & Rugi
// ============================================================
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
requireLogin();
$pageTitle = 'Laporan Laba & Rugi';

$dateFrom = $_GET['dari'] ?? date('Y-m-01');
$dateTo   = $_GET['sampai'] ?? date('Y-m-d');

// Ambil semua transaksi selesai dalam periode
$allTrx = csvReadAll('transaksi');
$allTrx = array_filter($allTrx, function($t) use ($dateFrom, $dateTo) {
    if (($t['status'] ?? '') !== 'selesai') return false;
    $d = substr($t['tanggal_transaksi'] ?? '', 0, 10);
    return $d >= $dateFrom && $d <= $dateTo;
});
$allTrx = array_values($allTrx);

// Ambil detail transaksi
$allDetail = csvReadAll('detail_transaksi');
$allMenu = csvReadAll('menu');

// Build lookup menu
$menuLookup = [];
foreach ($allMenu as $m) {
    $menuLookup[$m['id_menu']] = $m;
}

// Hitung per kategori
$kategoris = [];
$totalPendapatan = 0;
$totalModal = 0;

foreach ($allTrx as $trx) {
    $trxId = $trx['id_transaksi'];
    
    // Find details for this transaction
    $details = array_filter($allDetail, fn($d) => $d['id_transaksi'] == $trxId);
    
    foreach ($details as $det) {
        $menu = $menuLookup[$det['id_menu']] ?? null;
        $kat = $menu['kategori'] ?? 'lainnya';
        $hargaJual = (float)($det['harga_satuan'] ?? 0);
        $hargaModal = (float)($menu['harga_modal'] ?? 0);
        $qty = (int)($det['jumlah'] ?? 1);
        
        $pendapatan = $hargaJual * $qty;
        $modal = $hargaModal * $qty;
        
        if (!isset($kategoris[$kat])) {
            $kategoris[$kat] = ['pendapatan' => 0, 'modal' => 0, 'laba' => 0, 'jumlah_terjual' => 0];
        }
        $kategoris[$kat]['pendapatan'] += $pendapatan;
        $kategoris[$kat]['modal'] += $modal;
        $kategoris[$kat]['laba'] += ($pendapatan - $modal);
        $kategoris[$kat]['jumlah_terjual'] += $qty;
        
        $totalPendapatan += $pendapatan;
        $totalModal += $modal;
    }
}

$totalLaba = $totalPendapatan - $totalModal;

// Biaya operasional (placeholder — bisa ditambahkan nanti)
$biayaOperasional = 0;
$labaBersih = $totalLaba - $biayaOperasional;

// Data per hari untuk chart
$dailyData = [];
foreach ($allTrx as $trx) {
    $d = substr($trx['tanggal_transaksi'] ?? '', 0, 10);
    if (!isset($dailyData[$d])) {
        $dailyData[$d] = ['pendapatan' => 0, 'modal' => 0];
    }
    $dailyData[$d]['pendapatan'] += (float)($trx['total_bayar'] ?? 0);
    
    // Modal per transaksi
    $details = array_filter($allDetail, fn($dt) => $dt['id_transaksi'] == $trx['id_transaksi']);
    foreach ($details as $det) {
        $menu = $menuLookup[$det['id_menu']] ?? null;
        $dailyData[$d]['modal'] += (float)($menu['harga_modal'] ?? 0) * (int)($det['jumlah'] ?? 1);
    }
}
ksort($dailyData);

require_once __DIR__ . '/../includes/header.php';
?>

<style>
@media print {
  #sidebar, header, .no-print { display: none !important; }
  body { background: white !important; color: black !important; }
  .card-glass { background: white !important; border: 1px solid #eee !important; color: black !important; }
  td, th { color: black !important; }
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
  <button type="submit" class="btn-primary px-4 py-2 rounded-xl text-white text-sm font-medium">
    <i class="fas fa-filter mr-1.5"></i> Filter
  </button>
  <a href="laba_rugi.php" class="px-4 py-2 rounded-xl text-sm text-slate-400 hover:bg-white/10 transition-colors">Reset</a>
  <button type="button" onclick="window.print()" class="ml-auto px-4 py-2 rounded-xl text-sm font-medium flex items-center gap-2 hover:bg-white/10 transition-colors" style="border: 1px solid rgba(255,255,255,0.1); color: #e2e8f0;">
    <i class="fas fa-print"></i> Cetak
  </button>
</form>

<!-- Print Header -->
<div class="hidden print:block mb-6 text-center">
  <h1 class="text-xl font-bold">LAPORAN LABA & RUGI — SAVA CELL</h1>
  <p class="text-sm">Periode: <?= tglIndo($dateFrom) ?> s/d <?= tglIndo($dateTo) ?></p>
  <p class="text-sm">Dicetak: <?= date('d/m/Y H:i') ?></p>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <div class="card-glass rounded-2xl p-5">
    <div class="flex items-center justify-between mb-2">
      <p class="text-xs text-slate-500">Total Pendapatan</p>
      <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-cyan-500 to-blue-600 flex items-center justify-center">
        <i class="fas fa-arrow-up text-white text-xs"></i>
      </div>
    </div>
    <p class="text-xl font-extrabold" style="color: #00c4f5;"><?= rupiah($totalPendapatan) ?></p>
  </div>
  <div class="card-glass rounded-2xl p-5">
    <div class="flex items-center justify-between mb-2">
      <p class="text-xs text-slate-500">Total Modal / HPP</p>
      <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-red-500 to-rose-600 flex items-center justify-center">
        <i class="fas fa-arrow-down text-white text-xs"></i>
      </div>
    </div>
    <p class="text-xl font-extrabold text-red-400"><?= rupiah($totalModal) ?></p>
  </div>
  <div class="card-glass rounded-2xl p-5">
    <div class="flex items-center justify-between mb-2">
      <p class="text-xs text-slate-500">Laba Kotor</p>
      <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center">
        <i class="fas fa-chart-line text-white text-xs"></i>
      </div>
    </div>
    <p class="text-xl font-extrabold <?= $totalLaba >= 0 ? 'text-emerald-400' : 'text-red-400' ?>"><?= rupiah($totalLaba) ?></p>
  </div>
  <div class="card-glass rounded-2xl p-5">
    <div class="flex items-center justify-between mb-2">
      <p class="text-xs text-slate-500">Margin (%)</p>
      <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center">
        <i class="fas fa-percentage text-white text-xs"></i>
      </div>
    </div>
    <?php $margin = $totalPendapatan > 0 ? ($totalLaba / $totalPendapatan * 100) : 0; ?>
    <p class="text-xl font-extrabold text-amber-400"><?= number_format($margin, 1) ?>%</p>
  </div>
</div>

<!-- Laba Rugi Chart -->
<div class="card-glass rounded-2xl p-5 mb-6">
  <h3 class="font-bold text-white text-sm mb-4">Grafik Pendapatan vs Modal per Hari</h3>
  <canvas id="labaRugiChart" height="100"></canvas>
</div>

<!-- Rincian per Kategori -->
<div class="card-glass rounded-2xl overflow-hidden mb-6">
  <div class="px-5 py-4 border-b" style="border-color: rgba(255,255,255,0.06);">
    <h3 class="font-bold text-white text-sm">Rincian Laba per Kategori</h3>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead>
        <tr class="text-xs text-slate-500 uppercase" style="background: rgba(255,255,255,0.03);">
          <th class="px-5 py-3 text-left">Kategori</th>
          <th class="px-5 py-3 text-right">Qty Terjual</th>
          <th class="px-5 py-3 text-right">Pendapatan</th>
          <th class="px-5 py-3 text-right">Modal (HPP)</th>
          <th class="px-5 py-3 text-right">Laba Kotor</th>
          <th class="px-5 py-3 text-right">Margin</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($kategoris as $kat => $data): ?>
        <?php $m = $data['pendapatan'] > 0 ? ($data['laba'] / $data['pendapatan'] * 100) : 0; ?>
        <tr class="table-row border-t" style="border-color: rgba(255,255,255,0.04);">
          <td class="px-5 py-3">
            <span class="px-2.5 py-1 rounded-full text-xs font-semibold capitalize" style="background: rgba(0,196,245,0.1); color:#00c4f5;"><?= ucfirst(e($kat)) ?></span>
          </td>
          <td class="px-5 py-3 text-right text-slate-300"><?= number_format($data['jumlah_terjual']) ?></td>
          <td class="px-5 py-3 text-right text-white font-medium"><?= rupiah($data['pendapatan']) ?></td>
          <td class="px-5 py-3 text-right text-red-400"><?= rupiah($data['modal']) ?></td>
          <td class="px-5 py-3 text-right font-bold <?= $data['laba'] >= 0 ? 'text-emerald-400' : 'text-red-400' ?>"><?= rupiah($data['laba']) ?></td>
          <td class="px-5 py-3 text-right text-amber-400"><?= number_format($m, 1) ?>%</td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($kategoris)): ?>
        <tr><td colspan="6" class="px-5 py-10 text-center text-slate-600">
          <i class="fas fa-chart-pie text-3xl mb-2 block opacity-20"></i>Belum ada data transaksi pada periode ini
        </td></tr>
        <?php endif; ?>
      </tbody>
      <tfoot>
        <tr class="border-t" style="border-color: rgba(0,196,245,0.2); background: rgba(0,196,245,0.04);">
          <td class="px-5 py-3 font-bold text-white">TOTAL</td>
          <td class="px-5 py-3 text-right text-white font-bold"><?= number_format(array_sum(array_column($kategoris, 'jumlah_terjual'))) ?></td>
          <td class="px-5 py-3 text-right font-bold" style="color:#00c4f5;"><?= rupiah($totalPendapatan) ?></td>
          <td class="px-5 py-3 text-right text-red-400 font-bold"><?= rupiah($totalModal) ?></td>
          <td class="px-5 py-3 text-right font-extrabold <?= $totalLaba >= 0 ? 'text-emerald-400' : 'text-red-400' ?>"><?= rupiah($totalLaba) ?></td>
          <td class="px-5 py-3 text-right text-amber-400 font-bold"><?= number_format($margin, 1) ?>%</td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<!-- Ringkasan Laba Rugi Format Akuntansi -->
<div class="card-glass rounded-2xl overflow-hidden">
  <div class="px-5 py-4 border-b" style="border-color: rgba(255,255,255,0.06);">
    <h3 class="font-bold text-white text-sm">Laporan Laba Rugi (Format Akuntansi)</h3>
  </div>
  <div class="p-5 space-y-3 text-sm">
    <div class="flex justify-between py-2">
      <span class="text-white font-semibold">Pendapatan Penjualan</span>
      <span class="font-bold" style="color:#00c4f5;"><?= rupiah($totalPendapatan) ?></span>
    </div>
    <div class="flex justify-between py-2 pl-6 border-b" style="border-color: rgba(255,255,255,0.06);">
      <span class="text-red-400">(-) Harga Pokok Penjualan (HPP)</span>
      <span class="text-red-400 font-semibold"><?= rupiah($totalModal) ?></span>
    </div>
    <div class="flex justify-between py-2 font-bold">
      <span class="text-white">= Laba Kotor</span>
      <span class="<?= $totalLaba >= 0 ? 'text-emerald-400' : 'text-red-400' ?>"><?= rupiah($totalLaba) ?></span>
    </div>
    <div class="flex justify-between py-2 pl-6 border-b" style="border-color: rgba(255,255,255,0.06);">
      <span class="text-red-400">(-) Biaya Operasional</span>
      <span class="text-red-400 font-semibold"><?= rupiah($biayaOperasional) ?></span>
    </div>
    <div class="flex justify-between py-3 rounded-xl px-4" style="background: <?= $labaBersih >= 0 ? 'rgba(16,185,129,0.1)' : 'rgba(239,68,68,0.1)' ?>; border: 1px solid <?= $labaBersih >= 0 ? 'rgba(16,185,129,0.3)' : 'rgba(239,68,68,0.3)' ?>;">
      <span class="text-white font-extrabold text-base">= LABA BERSIH</span>
      <span class="font-extrabold text-base <?= $labaBersih >= 0 ? 'text-emerald-400' : 'text-red-400' ?>"><?= rupiah($labaBersih) ?></span>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const dailyLabels = <?= json_encode(array_map(fn($d) => date('d/m', strtotime($d)), array_keys($dailyData))) ?>;
const dailyPendapatan = <?= json_encode(array_values(array_map(fn($d) => $d['pendapatan'], $dailyData))) ?>;
const dailyModal = <?= json_encode(array_values(array_map(fn($d) => $d['modal'], $dailyData))) ?>;
const dailyLaba = <?= json_encode(array_values(array_map(fn($d) => $d['pendapatan'] - $d['modal'], $dailyData))) ?>;

new Chart(document.getElementById('labaRugiChart'), {
  type: 'bar',
  data: {
    labels: dailyLabels,
    datasets: [
      {
        label: 'Pendapatan',
        data: dailyPendapatan,
        backgroundColor: 'rgba(0,196,245,0.6)',
        borderRadius: 6,
        barPercentage: 0.6,
      },
      {
        label: 'Modal (HPP)',
        data: dailyModal,
        backgroundColor: 'rgba(239,68,68,0.5)',
        borderRadius: 6,
        barPercentage: 0.6,
      },
      {
        label: 'Laba',
        type: 'line',
        data: dailyLaba,
        borderColor: '#10b981',
        backgroundColor: 'rgba(16,185,129,0.1)',
        borderWidth: 2,
        tension: 0.4,
        fill: true,
        pointRadius: 4,
        pointBackgroundColor: '#10b981',
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
      y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#64748b', font: { size: 10 },
        callback: v => 'Rp ' + (v/1000).toFixed(0) + 'k' } }
    }
  }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

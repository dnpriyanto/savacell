<?php
// ============================================================
//  admin/page/grafik_penjualan.php — Grafik Penjualan Lengkap
// ============================================================
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
requireLogin();
$pageTitle = 'Grafik Penjualan';

$period = $_GET['period'] ?? '30'; // 7, 30, 90, 365

// Ambil semua transaksi selesai
$allTrx = csvReadAll('transaksi');
$allTrx = array_filter($allTrx, fn($t) => ($t['status'] ?? '') === 'selesai');
$allTrx = array_values($allTrx);

$allDetail = csvReadAll('detail_transaksi');
$allMenu = csvReadAll('menu');

$menuLookup = [];
foreach ($allMenu as $m) {
    $menuLookup[$m['id_menu']] = $m;
}

$fromDate = date('Y-m-d', strtotime("-{$period} days"));
$trxFiltered = array_filter($allTrx, fn($t) => substr($t['tanggal_transaksi'] ?? '', 0, 10) >= $fromDate);
$trxFiltered = array_values($trxFiltered);

// 1. Data per hari
$dailySales = [];
foreach ($trxFiltered as $trx) {
    $d = substr($trx['tanggal_transaksi'] ?? '', 0, 10);
    if (!isset($dailySales[$d])) $dailySales[$d] = ['revenue' => 0, 'count' => 0];
    $dailySales[$d]['revenue'] += (float)($trx['total_bayar'] ?? 0);
    $dailySales[$d]['count']++;
}
ksort($dailySales);

// 2. Per kategori
$byKategori = [];
foreach ($trxFiltered as $trx) {
    $details = array_filter($allDetail, fn($d) => $d['id_transaksi'] == $trx['id_transaksi']);
    foreach ($details as $det) {
        $menu = $menuLookup[$det['id_menu']] ?? null;
        $kat = $menu['kategori'] ?? 'lainnya';
        if (!isset($byKategori[$kat])) $byKategori[$kat] = 0;
        $byKategori[$kat] += (float)($det['subtotal'] ?? 0);
    }
}

// 3. Top 10 produk terlaris
$productSales = [];
foreach ($trxFiltered as $trx) {
    $details = array_filter($allDetail, fn($d) => $d['id_transaksi'] == $trx['id_transaksi']);
    foreach ($details as $det) {
        $menu = $menuLookup[$det['id_menu']] ?? null;
        $nama = $menu['nama_produk'] ?? 'Unknown';
        $id = $det['id_menu'];
        if (!isset($productSales[$id])) $productSales[$id] = ['nama' => $nama, 'qty' => 0, 'revenue' => 0];
        $productSales[$id]['qty'] += (int)($det['jumlah'] ?? 1);
        $productSales[$id]['revenue'] += (float)($det['subtotal'] ?? 0);
    }
}
usort($productSales, fn($a, $b) => $b['qty'] - $a['qty']);
$topProducts = array_slice($productSales, 0, 10);

// 4. Per jam (hourly distribution)
$hourlyData = array_fill(0, 24, 0);
foreach ($trxFiltered as $trx) {
    $hour = (int)date('G', strtotime($trx['tanggal_transaksi'] ?? ''));
    $hourlyData[$hour] += (float)($trx['total_bayar'] ?? 0);
}

// 5. Per hari dalam seminggu
$weekdayData = array_fill(0, 7, 0);
$weekdayNames = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
foreach ($trxFiltered as $trx) {
    $dayOfWeek = (int)date('w', strtotime($trx['tanggal_transaksi'] ?? ''));
    $weekdayData[$dayOfWeek] += (float)($trx['total_bayar'] ?? 0);
}

// Summary stats
$totalRevenue = array_sum(array_map(fn($t) => (float)$t['total_bayar'], $trxFiltered));
$totalTrx = count($trxFiltered);
$avgTrx = $totalTrx > 0 ? $totalRevenue / $totalTrx : 0;
$bestDay = !empty($dailySales) ? array_keys($dailySales, max($dailySales))[0] ?? '-' : '-';

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Period Selector -->
<div class="flex gap-2 mb-6 flex-wrap">
  <?php foreach ([['7','7 Hari'],['30','30 Hari'],['90','3 Bulan'],['365','1 Tahun']] as [$val,$label]): ?>
  <a href="?period=<?= $val ?>" class="px-4 py-2 rounded-xl text-xs font-medium transition-all <?= $period == $val ? 'text-dark-900 bg-cyan-400 font-bold' : 'text-slate-400 hover:text-white border border-white/10' ?>"><?= $label ?></a>
  <?php endforeach; ?>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <div class="card-glass rounded-2xl p-5">
    <p class="text-xs text-slate-500 mb-2">Total Pendapatan</p>
    <p class="text-xl font-extrabold" style="color:#00c4f5;"><?= rupiah($totalRevenue) ?></p>
    <p class="text-xs text-slate-600 mt-1"><?= $period ?> hari terakhir</p>
  </div>
  <div class="card-glass rounded-2xl p-5">
    <p class="text-xs text-slate-500 mb-2">Total Transaksi</p>
    <p class="text-xl font-extrabold text-white"><?= number_format($totalTrx) ?></p>
    <p class="text-xs text-slate-600 mt-1">Transaksi selesai</p>
  </div>
  <div class="card-glass rounded-2xl p-5">
    <p class="text-xs text-slate-500 mb-2">Rata-rata / Transaksi</p>
    <p class="text-xl font-extrabold text-amber-400"><?= rupiah($avgTrx) ?></p>
    <p class="text-xs text-slate-600 mt-1">Average order value</p>
  </div>
  <div class="card-glass rounded-2xl p-5">
    <p class="text-xs text-slate-500 mb-2">Hari Terbaik</p>
    <p class="text-lg font-extrabold text-emerald-400"><?= $bestDay !== '-' ? date('d/m/Y', strtotime($bestDay)) : '-' ?></p>
    <p class="text-xs text-slate-600 mt-1">Pendapatan tertinggi</p>
  </div>
</div>

<!-- Charts Grid -->
<div class="grid lg:grid-cols-2 gap-4 mb-6">
  <!-- Revenue Chart -->
  <div class="card-glass rounded-2xl p-5 lg:col-span-2">
    <h3 class="font-bold text-white text-sm mb-4">📈 Pendapatan per Hari</h3>
    <canvas id="revenueChart" height="80"></canvas>
  </div>

  <!-- Kategori Pie -->
  <div class="card-glass rounded-2xl p-5">
    <h3 class="font-bold text-white text-sm mb-4">🍰 Penjualan per Kategori</h3>
    <canvas id="katChart" height="200"></canvas>
    <div id="katLegend" class="mt-4 space-y-1.5 text-xs"></div>
  </div>

  <!-- Hourly Distribution -->
  <div class="card-glass rounded-2xl p-5">
    <h3 class="font-bold text-white text-sm mb-4">🕐 Distribusi Jam Penjualan</h3>
    <canvas id="hourlyChart" height="200"></canvas>
  </div>
</div>

<div class="grid lg:grid-cols-2 gap-4 mb-6">
  <!-- Weekday Chart -->
  <div class="card-glass rounded-2xl p-5">
    <h3 class="font-bold text-white text-sm mb-4">📅 Penjualan per Hari</h3>
    <canvas id="weekdayChart" height="180"></canvas>
  </div>

  <!-- Top Products -->
  <div class="card-glass rounded-2xl p-5">
    <h3 class="font-bold text-white text-sm mb-4">🏆 Top 10 Produk Terlaris</h3>
    <div class="space-y-2.5 max-h-80 overflow-y-auto">
      <?php foreach ($topProducts as $i => $p): ?>
      <?php $pct = $topProducts[0]['qty'] > 0 ? ($p['qty'] / $topProducts[0]['qty'] * 100) : 0; ?>
      <div class="flex items-center gap-3">
        <span class="w-6 h-6 rounded-lg flex items-center justify-center text-xs font-bold flex-shrink-0 <?= $i < 3 ? 'text-amber-900 bg-amber-400' : 'text-slate-300 bg-white/10' ?>"><?= $i + 1 ?></span>
        <div class="flex-1 min-w-0">
          <p class="text-white text-xs font-medium truncate"><?= e($p['nama']) ?></p>
          <div class="mt-1 h-1.5 rounded-full overflow-hidden" style="background: rgba(255,255,255,0.08);">
            <div class="h-full rounded-full" style="width: <?= $pct ?>%; background: linear-gradient(90deg, #00c4f5, #0077aa);"></div>
          </div>
        </div>
        <div class="text-right flex-shrink-0">
          <span class="text-xs font-bold" style="color:#00c4f5;"><?= $p['qty'] ?></span>
          <span class="text-xs text-slate-500 block"><?= rupiah($p['revenue']) ?></span>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($topProducts)): ?>
      <p class="text-slate-600 text-xs text-center py-6">Belum ada data</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Transaction Count Chart -->
<div class="card-glass rounded-2xl p-5 mb-6">
  <h3 class="font-bold text-white text-sm mb-4">📊 Jumlah Transaksi per Hari</h3>
  <canvas id="trxCountChart" height="80"></canvas>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const chartDefaults = {
  responsive: true,
  plugins: { legend: { labels: { color: '#94a3b8', font: { size: 11 } } } },
  scales: {
    x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#64748b', font: { size: 10 } } },
    y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#64748b', font: { size: 10 } } }
  }
};

// Revenue Line Chart
const dailyLabels = <?= json_encode(array_map(fn($d) => date('d/m', strtotime($d)), array_keys($dailySales))) ?>;
const dailyRevenue = <?= json_encode(array_values(array_map(fn($d) => $d['revenue'], $dailySales))) ?>;
const dailyCount = <?= json_encode(array_values(array_map(fn($d) => $d['count'], $dailySales))) ?>;

new Chart(document.getElementById('revenueChart'), {
  type: 'line',
  data: {
    labels: dailyLabels,
    datasets: [{
      label: 'Pendapatan',
      data: dailyRevenue,
      borderColor: '#00c4f5',
      backgroundColor: 'rgba(0,196,245,0.08)',
      borderWidth: 2.5,
      tension: 0.4,
      fill: true,
      pointRadius: 3,
      pointBackgroundColor: '#00c4f5',
    }]
  },
  options: {...chartDefaults, scales: {...chartDefaults.scales, y: {...chartDefaults.scales.y, ticks: {...chartDefaults.scales.y.ticks, callback: v => 'Rp ' + (v/1000).toFixed(0) + 'k'}}}}
});

// Kategori Donut
const katLabels = <?= json_encode(array_map('ucfirst', array_keys($byKategori))) ?>;
const katValues = <?= json_encode(array_values($byKategori)) ?>;
const katColors = ['#00c4f5','#8b5cf6','#10b981','#f59e0b','#ef4444'];

new Chart(document.getElementById('katChart'), {
  type: 'doughnut',
  data: {
    labels: katLabels,
    datasets: [{ data: katValues, backgroundColor: katColors, borderWidth: 0, hoverOffset: 8 }]
  },
  options: { responsive: true, cutout: '65%', plugins: { legend: { display: false } } }
});
// Legend
const katLegend = document.getElementById('katLegend');
katLabels.forEach((l, i) => {
  katLegend.innerHTML += `<div class="flex items-center justify-between">
    <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm inline-block" style="background:${katColors[i]}"></span><span class="text-slate-400">${l}</span></div>
    <span class="text-white font-semibold">Rp ${(katValues[i]/1000).toFixed(0)}k</span>
  </div>`;
});

// Hourly Bar
new Chart(document.getElementById('hourlyChart'), {
  type: 'bar',
  data: {
    labels: Array.from({length:24}, (_,i) => `${i.toString().padStart(2,'0')}:00`),
    datasets: [{
      label: 'Penjualan',
      data: <?= json_encode(array_values($hourlyData)) ?>,
      backgroundColor: ctx => {
        const val = ctx.raw;
        const max = Math.max(...<?= json_encode(array_values($hourlyData)) ?>);
        const ratio = max > 0 ? val / max : 0;
        return `rgba(0,196,245,${0.2 + ratio * 0.6})`;
      },
      borderRadius: 4,
    }]
  },
  options: {...chartDefaults, plugins: { legend: { display: false } }, scales: {...chartDefaults.scales, y: {...chartDefaults.scales.y, ticks: {...chartDefaults.scales.y.ticks, callback: v => 'Rp ' + (v/1000).toFixed(0) + 'k'}}}}
});

// Weekday Bar
new Chart(document.getElementById('weekdayChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($weekdayNames) ?>,
    datasets: [{
      label: 'Penjualan',
      data: <?= json_encode(array_values($weekdayData)) ?>,
      backgroundColor: ['#ef4444','#00c4f5','#00c4f5','#00c4f5','#00c4f5','#00c4f5','#8b5cf6'],
      borderRadius: 8,
      barPercentage: 0.6,
    }]
  },
  options: {...chartDefaults, plugins: { legend: { display: false } }, scales: {...chartDefaults.scales, y: {...chartDefaults.scales.y, ticks: {...chartDefaults.scales.y.ticks, callback: v => 'Rp ' + (v/1000).toFixed(0) + 'k'}}}}
});

// Transaction Count
new Chart(document.getElementById('trxCountChart'), {
  type: 'bar',
  data: {
    labels: dailyLabels,
    datasets: [{
      label: 'Jumlah Transaksi',
      data: dailyCount,
      backgroundColor: 'rgba(139,92,246,0.5)',
      borderRadius: 6,
      barPercentage: 0.5,
    }]
  },
  options: {...chartDefaults, plugins: { legend: { display: false } }}
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

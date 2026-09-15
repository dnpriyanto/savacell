<?php
// ============================================================
//  admin/index.php — Dashboard Admin (Phase 4: View 7)
// ============================================================
require_once __DIR__ . '/../config/database.php';
$pageTitle = 'Dashboard';

// --- KPI Stats ---
$totalRevenue     = dbFetchOne("SELECT COALESCE(SUM(total_bayar),0) AS val FROM transaksi WHERE status='selesai'")['val'];
$totalTransaksi   = dbFetchOne("SELECT COUNT(*) AS val FROM transaksi WHERE status='selesai'")['val'];
$totalPelanggan   = dbFetchOne("SELECT COUNT(*) AS val FROM pelanggan")['val'];
$totalProduk = dbFetchOne("SELECT COUNT(*) AS val FROM menu")['val'];

// Revenue today
$revenueToday = dbFetchOne("SELECT COALESCE(SUM(total_bayar),0) AS val FROM transaksi WHERE DATE(tanggal_transaksi)=CURDATE() AND status='selesai'")['val'];

// Last 7 days sales chart data
$chartData = dbFetchAll("
    SELECT DATE(tanggal_transaksi) AS tgl, SUM(total_bayar) AS total, COUNT(*) AS jml
    FROM transaksi
    WHERE tanggal_transaksi >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND status='selesai'
    GROUP BY DATE(tanggal_transaksi)
    ORDER BY tgl ASC
");

// Sales by category
$byKategori = dbFetchAll("
    SELECT m.kategori, SUM(dt.subtotal) AS total
    FROM detail_transaksi dt
    JOIN menu m ON dt.id_menu = m.id_menu
    JOIN transaksi t ON dt.id_transaksi = t.id_transaksi
    WHERE t.status='selesai'
    GROUP BY m.kategori
");

// Recent transactions
$recentTrx = dbFetchAll("
    SELECT t.*, p.nama_pelanggan, pg.nama_pegawai
    FROM transaksi t
    LEFT JOIN pelanggan p ON t.id_pelanggan = p.id_pelanggan
    JOIN pegawai pg ON t.id_pegawai = pg.id_pegawai
    ORDER BY t.tanggal_transaksi DESC LIMIT 8
");

require_once __DIR__ . '/includes/header.php';
?>

<!-- KPI Cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <?php
  $cards = [
    ['icon'=>'fa-wallet','label'=>'Total Pendapatan','value'=>rupiah($totalRevenue),'sub'=>'Semua waktu','color'=>'from-cyan-500 to-blue-600'],
    ['icon'=>'fa-receipt','label'=>'Total Transaksi','value'=>number_format($totalTransaksi),'sub'=>'Semua waktu','color'=>'from-violet-500 to-purple-700'],
    ['icon'=>'fa-users','label'=>'Pelanggan','value'=>number_format($totalPelanggan),'sub'=>'Terdaftar','color'=>'from-emerald-500 to-teal-600'],
    ['icon'=>'fa-boxes','label'=>'Produk Aktif','value'=>number_format($totalProduk),'sub'=>'Tersedia','color'=>'from-amber-500 to-orange-600'],
  ];
  foreach ($cards as $c): ?>
  <div class="card-glass rounded-2xl p-5 relative overflow-hidden">
    <div class="absolute right-4 top-4 w-10 h-10 rounded-xl bg-gradient-to-br <?= $c['color'] ?> flex items-center justify-center opacity-80">
      <i class="fas <?= $c['icon'] ?> text-white text-sm"></i>
    </div>
    <p class="text-xs text-slate-500 font-medium mb-2"><?= $c['label'] ?></p>
    <p class="text-xl font-extrabold text-white leading-none"><?= $c['value'] ?></p>
    <p class="text-xs text-slate-600 mt-1"><?= $c['sub'] ?></p>
  </div>
  <?php endforeach; ?>
</div>

<!-- Revenue Today Banner -->
<div class="card-glass rounded-2xl px-6 py-4 mb-6 flex items-center justify-between"
  style="border-color: rgba(0,196,245,0.2); background: linear-gradient(135deg, rgba(0,196,245,0.08), rgba(0,119,170,0.04));">
  <div>
    <p class="text-xs text-slate-400 font-medium">Pendapatan Hari Ini</p>
    <p class="text-2xl font-extrabold mt-1" style="color: #00c4f5;"><?= rupiah($revenueToday) ?></p>
  </div>
  <a href="../admin/page/transaksi.php" class="btn-primary px-5 py-2.5 rounded-xl text-white text-sm font-bold flex items-center gap-2">
    <i class="fas fa-cash-register"></i> Buka Kasir
  </a>
</div>

<!-- Charts Row -->
<div class="grid lg:grid-cols-3 gap-4 mb-6">
  <!-- Line Chart: 7 Days -->
  <div class="card-glass rounded-2xl p-5 lg:col-span-2">
    <div class="flex items-center justify-between mb-4">
      <h3 class="font-bold text-white text-sm">Penjualan 7 Hari Terakhir</h3>
      <span class="text-xs text-slate-500">Dalam Rupiah</span>
    </div>
    <canvas id="lineChart" height="120"></canvas>
  </div>
  <!-- Donut: By Kategori -->
  <div class="card-glass rounded-2xl p-5">
    <h3 class="font-bold text-white text-sm mb-4">Penjualan per Kategori</h3>
    <canvas id="donutChart" height="180"></canvas>
    <div id="donutLegend" class="mt-4 space-y-1.5 text-xs"></div>
  </div>
</div>

<!-- Recent Transactions -->
<div class="card-glass rounded-2xl overflow-hidden">
  <div class="px-5 py-4 border-b flex items-center justify-between" style="border-color: rgba(255,255,255,0.06);">
    <h3 class="font-bold text-white text-sm">Transaksi Terbaru</h3>
    <a href="/admin/pages/laporan.php" class="text-xs" style="color: #00c4f5;">Lihat Semua →</a>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead>
        <tr class="text-xs text-slate-500 uppercase tracking-wide" style="background: rgba(255,255,255,0.03);">
          <th class="px-5 py-3 text-left">ID</th>
          <th class="px-5 py-3 text-left">Tanggal</th>
          <th class="px-5 py-3 text-left">Pelanggan</th>
          <th class="px-5 py-3 text-left">Kasir</th>
          <th class="px-5 py-3 text-right">Total</th>
          <th class="px-5 py-3 text-center">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentTrx as $trx): ?>
        <tr class="table-row border-t" style="border-color: rgba(255,255,255,0.04);">
          <td class="px-5 py-3 font-mono text-xs text-slate-400">#<?= $trx['id_transaksi'] ?></td>
          <td class="px-5 py-3 text-slate-300"><?= date('d/m H:i', strtotime($trx['tanggal_transaksi'])) ?></td>
          <td class="px-5 py-3 text-white"><?= e($trx['nama_pelanggan'] ?? '—') ?></td>
          <td class="px-5 py-3 text-slate-400"><?= e($trx['nama_pegawai']) ?></td>
          <td class="px-5 py-3 text-right font-semibold" style="color: #00c4f5;"><?= rupiah($trx['total_bayar']) ?></td>
          <td class="px-5 py-3 text-center">
            <span class="px-2.5 py-0.5 rounded-full text-xs font-medium <?= $trx['status'] === 'selesai' ? 'bg-emerald-500/15 text-emerald-400' : 'bg-red-500/15 text-red-400' ?>">
              <?= ucfirst($trx['status']) ?>
            </span>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($recentTrx)): ?>
        <tr><td colspan="6" class="px-5 py-8 text-center text-slate-600">Belum ada transaksi</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const chartLabels = <?= json_encode(array_map(fn($r) => date('d/m', strtotime($r['tgl'])), $chartData)) ?>;
const chartValues = <?= json_encode(array_map(fn($r) => (float)$r['total'], $chartData)) ?>;
const katLabels   = <?= json_encode(array_map(fn($r) => ucfirst($r['kategori']), $byKategori)) ?>;
const katValues   = <?= json_encode(array_map(fn($r) => (float)$r['total'], $byKategori)) ?>;

// Line chart
new Chart(document.getElementById('lineChart'), {
  type: 'line',
  data: {
    labels: chartLabels,
    datasets: [{
      label: 'Pendapatan',
      data: chartValues,
      borderColor: '#00c4f5',
      backgroundColor: 'rgba(0,196,245,0.08)',
      borderWidth: 2,
      tension: 0.4,
      fill: true,
      pointBackgroundColor: '#00c4f5',
      pointRadius: 4,
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: true,
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#64748b', font: { size: 10 } } },
      y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#64748b', font: { size: 10 },
        callback: v => 'Rp ' + (v/1000).toFixed(0) + 'k' } }
    }
  }
});

// Donut chart
const donutColors = ['#00c4f5','#8b5cf6','#10b981','#f59e0b'];
new Chart(document.getElementById('donutChart'), {
  type: 'doughnut',
  data: {
    labels: katLabels,
    datasets: [{ data: katValues, backgroundColor: donutColors, borderWidth: 0, hoverOffset: 4 }]
  },
  options: {
    responsive: true, maintainAspectRatio: true, cutout: '70%',
    plugins: { legend: { display: false } }
  }
});
// Legend
const legend = document.getElementById('donutLegend');
katLabels.forEach((l, i) => {
  legend.innerHTML += `<div class="flex items-center justify-between">
    <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm inline-block" style="background:${donutColors[i]}"></span><span class="text-slate-400">${l}</span></div>
    <span class="text-white font-semibold">Rp ${(katValues[i]/1000).toFixed(0)}k</span>
  </div>`;
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

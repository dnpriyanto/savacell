<?php
// ============================================================
//  admin/pages/bahan_baku.php — Master Stock (Phase 1: View 3)
// ============================================================
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
requireLogin();
$pageTitle = 'Stok Saldo & Bahan Baku';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $nama   = trim($_POST['nama_bahan'] ?? '');
    $stok   = (float)($_POST['stok_sisa'] ?? 0);
    $satuan = trim($_POST['satuan'] ?? 'unit');

    if ($action === 'add') {
        if ($nama) {
            dbInsert("INSERT INTO bahan_baku (nama_bahan, stok_sisa, satuan) VALUES (?,?,?)", 'sds', [$nama, $stok, $satuan]);
            setFlash('success', 'Bahan baku berhasil ditambahkan.');
        } else {
            setFlash('error', 'Nama bahan wajib diisi.');
        }
    } elseif ($action === 'edit') {
        $id = (int)$_POST['id_bahan'];
        dbQuery("UPDATE bahan_baku SET nama_bahan=?, stok_sisa=?, satuan=? WHERE id_bahan=?", 'sdsi', [$nama, $stok, $satuan, $id]);
        setFlash('success', 'Stok berhasil diperbarui.');
    } elseif ($action === 'topup') {
        // Top-up / add to existing stock
        $id     = (int)$_POST['id_bahan'];
        $tambah = (float)($_POST['tambah'] ?? 0);
        dbQuery("UPDATE bahan_baku SET stok_sisa = stok_sisa + ? WHERE id_bahan=?", 'di', [$tambah, $id]);
        setFlash('success', 'Stok berhasil di-top up.');
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id_bahan'];
        dbQuery("DELETE FROM bahan_baku WHERE id_bahan=?", 'i', [$id]);
        setFlash('success', 'Bahan baku dihapus.');
    }
    header('Location: bahan_baku.php'); exit;
}

$rows     = dbFetchAll("SELECT * FROM bahan_baku ORDER BY nama_bahan ASC");
$editData = null;
if (isset($_GET['edit'])) {
    $editData = dbFetchOne("SELECT * FROM bahan_baku WHERE id_bahan=?", 'i', [(int)$_GET['edit']]);
}
$topupId = (int)($_GET['topup'] ?? 0);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex gap-6">

  <!-- Form -->
  <div class="w-80 flex-shrink-0 space-y-4">

    <!-- Add/Edit Form -->
    <div class="card-glass rounded-2xl p-5">
      <h2 class="font-bold text-white mb-4 flex items-center gap-2">
        <i class="fas fa-warehouse text-cyan-400"></i>
        <?= $editData ? 'Edit Bahan Baku' : 'Tambah Bahan Baku' ?>
      </h2>
      <form method="POST">
        <input type="hidden" name="action" value="<?= $editData ? 'edit' : 'add' ?>">
        <?php if ($editData): ?>
        <input type="hidden" name="id_bahan" value="<?= $editData['id_bahan'] ?>">
        <?php endif; ?>
        <div class="space-y-3">
          <div>
            <label class="text-xs font-medium text-slate-400 block mb-1">Nama Bahan / Saldo *</label>
            <input type="text" name="nama_bahan" required placeholder="cth: Saldo Telkomsel"
              value="<?= e($editData['nama_bahan'] ?? '') ?>" class="w-full px-3 py-2.5 rounded-xl text-sm">
          </div>
          <div>
            <label class="text-xs font-medium text-slate-400 block mb-1">Stok / Saldo Saat Ini</label>
            <input type="number" name="stok_sisa" step="0.01" placeholder="0"
              value="<?= (float)($editData['stok_sisa'] ?? 0) ?>" class="w-full px-3 py-2.5 rounded-xl text-sm">
          </div>
          <div>
            <label class="text-xs font-medium text-slate-400 block mb-1">Satuan</label>
            <select name="satuan" class="w-full px-3 py-2.5 rounded-xl text-sm">
              <?php foreach (['Rupiah','Pcs','Unit','Lembar','Paket'] as $s): ?>
              <option value="<?= $s ?>" <?= ($editData['satuan'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="flex gap-2 mt-4">
          <button type="submit" class="btn-primary flex-1 py-2.5 rounded-xl text-white text-sm font-bold">
            <i class="fas fa-save mr-1.5"></i> Simpan
          </button>
          <?php if ($editData): ?>
          <a href="bahan_baku.php" class="px-4 py-2.5 rounded-xl text-sm text-slate-400 hover:bg-white/10 transition-colors">Batal</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <!-- Top-up Quick Form -->
    <?php if ($topupId > 0):
      $topupItem = dbFetchOne("SELECT * FROM bahan_baku WHERE id_bahan=?", 'i', [$topupId]);
    ?>
    <div class="card-glass rounded-2xl p-5" style="border-color: rgba(16,185,129,0.3);">
      <h2 class="font-bold text-white mb-3 flex items-center gap-2">
        <i class="fas fa-plus-circle text-emerald-400"></i> Top-Up Stok
      </h2>
      <p class="text-xs text-slate-400 mb-3">Item: <span class="text-white font-medium"><?= e($topupItem['nama_bahan'] ?? '') ?></span></p>
      <form method="POST">
        <input type="hidden" name="action" value="topup">
        <input type="hidden" name="id_bahan" value="<?= $topupId ?>">
        <input type="number" name="tambah" min="1" step="0.01" required placeholder="Jumlah top-up"
          class="w-full px-3 py-2.5 rounded-xl text-sm mb-3">
        <div class="flex gap-2">
          <button type="submit" class="py-2.5 rounded-xl text-white text-sm font-bold flex-1" style="background: linear-gradient(135deg,#10b981,#059669)">
            <i class="fas fa-plus mr-1.5"></i> Top Up
          </button>
          <a href="bahan_baku.php" class="px-4 py-2.5 rounded-xl text-sm text-slate-400 hover:bg-white/10 transition-colors">Batal</a>
        </div>
      </form>
    </div>
    <?php endif; ?>
  </div>

  <!-- Table -->
  <div class="flex-1">
    <div class="card-glass rounded-2xl overflow-hidden">
      <div class="px-5 py-4 border-b" style="border-color: rgba(255,255,255,0.06);">
        <h3 class="font-bold text-white text-sm">Daftar Stok & Saldo</h3>
      </div>
      <table class="w-full text-sm">
        <thead>
          <tr class="text-xs text-slate-500 uppercase" style="background: rgba(255,255,255,0.03);">
            <th class="px-5 py-3 text-left">Nama Bahan / Saldo</th>
            <th class="px-5 py-3 text-right">Stok Sisa</th>
            <th class="px-5 py-3 text-center">Satuan</th>
            <th class="px-5 py-3 text-center">Update</th>
            <th class="px-5 py-3 text-center">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
          <?php $isLow = $row['stok_sisa'] < 100000 && $row['satuan'] === 'Rupiah' || ($row['satuan'] !== 'Rupiah' && $row['stok_sisa'] < 5); ?>
          <tr class="table-row border-t <?= $isLow ? 'bg-red-500/3' : '' ?>" style="border-color: rgba(255,255,255,0.04);">
            <td class="px-5 py-3">
              <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: linear-gradient(135deg, rgba(0,196,245,.2), rgba(0,119,170,.2));">
                  <i class="fas <?= $row['satuan'] === 'Rupiah' ? 'fa-coins' : 'fa-box' ?> text-xs" style="color:#00c4f5;"></i>
                </div>
                <div>
                  <div class="text-white font-medium"><?= e($row['nama_bahan']) ?></div>
                  <?php if ($isLow): ?><div class="text-xs text-red-400"><i class="fas fa-exclamation-triangle"></i> Stok Rendah</div><?php endif; ?>
                </div>
              </div>
            </td>
            <td class="px-5 py-3 text-right font-bold font-mono <?= $isLow ? 'text-red-400' : 'text-emerald-400' ?>">
              <?= $row['satuan'] === 'Rupiah' ? rupiah($row['stok_sisa']) : number_format($row['stok_sisa'], 0, ',', '.') ?>
            </td>
            <td class="px-5 py-3 text-center text-slate-400 text-xs"><?= e($row['satuan']) ?></td>
            <td class="px-5 py-3 text-center text-slate-500 text-xs"><?= date('d/m/Y H:i', strtotime($row['updated_at'])) ?></td>
            <td class="px-5 py-3 text-center">
              <div class="flex items-center justify-center gap-1.5">
                <a href="?topup=<?= $row['id_bahan'] ?>" class="w-7 h-7 rounded-lg flex items-center justify-center hover:bg-emerald-500/20 transition-colors text-emerald-400" title="Top Up">
                  <i class="fas fa-plus text-xs"></i>
                </a>
                <a href="?edit=<?= $row['id_bahan'] ?>" class="w-7 h-7 rounded-lg flex items-center justify-center hover:bg-cyan-500/20 transition-colors" style="color:#00c4f5;" title="Edit">
                  <i class="fas fa-edit text-xs"></i>
                </a>
                <form method="POST" class="inline">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id_bahan" value="<?= $row['id_bahan'] ?>">
                  <button type="submit" data-confirm="Hapus '<?= e($row['nama_bahan']) ?>'?"
                    class="w-7 h-7 rounded-lg flex items-center justify-center hover:bg-red-500/20 transition-colors text-red-400">
                    <i class="fas fa-trash text-xs"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

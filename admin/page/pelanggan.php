<?php
// ============================================================
//  admin/pages/pelanggan.php — Customer Management (Phase 1: View 1)
// ============================================================
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
requireLogin();
$pageTitle = 'Manajemen Pelanggan';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $nama  = trim($_POST['nama_pelanggan'] ?? '');
        $no_hp = trim($_POST['no_hp'] ?? '');
        $poin  = (int)($_POST['poin'] ?? 0);

        if ($nama && $no_hp) {
            if ($action === 'add') {
                dbInsert("INSERT INTO pelanggan (nama_pelanggan, no_hp, poin) VALUES (?,?,?)", 'ssi', [$nama, $no_hp, $poin]);
                setFlash('success', 'Pelanggan berhasil ditambahkan.');
            } else {
                $id = (int)$_POST['id_pelanggan'];
                dbQuery("UPDATE pelanggan SET nama_pelanggan=?, no_hp=?, poin=? WHERE id_pelanggan=?", 'ssii', [$nama, $no_hp, $poin, $id]);
                setFlash('success', 'Data pelanggan berhasil diperbarui.');
            }
        } else {
            setFlash('error', 'Nama dan Nomor HP wajib diisi.');
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id_pelanggan'];
        dbQuery("DELETE FROM pelanggan WHERE id_pelanggan=?", 'i', [$id]);
        setFlash('success', 'Pelanggan berhasil dihapus.');
    }
    header('Location: pelanggan.php'); exit;
}

// Fetch & search
$search = trim($_GET['q'] ?? '');
$sql = "SELECT * FROM pelanggan";
$params = [];
if ($search) {
    $sql .= " WHERE nama_pelanggan LIKE ? OR no_hp LIKE ?";
    $params = ["%$search%", "%$search%"];
}
$sql .= " ORDER BY id_pelanggan DESC";
$rows = $search
    ? dbFetchAll($sql, 'ss', $params)
    : dbFetchAll($sql);

$editData = null;
if (isset($_GET['edit'])) {
    $editData = dbFetchOne("SELECT * FROM pelanggan WHERE id_pelanggan=?", 'i', [(int)$_GET['edit']]);
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex gap-6">

  <!-- Form Panel -->
  <div class="w-80 flex-shrink-0">
    <div class="card-glass rounded-2xl p-5 sticky top-24">
      <h2 class="font-bold text-white mb-4 flex items-center gap-2">
        <i class="fas <?= $editData ? 'fa-user-edit' : 'fa-user-plus' ?> text-cyan-400"></i>
        <?= $editData ? 'Edit Pelanggan' : 'Tambah Pelanggan' ?>
      </h2>
      <form method="POST">
        <input type="hidden" name="action" value="<?= $editData ? 'edit' : 'add' ?>">
        <?php if ($editData): ?>
        <input type="hidden" name="id_pelanggan" value="<?= $editData['id_pelanggan'] ?>">
        <?php endif; ?>
        <div class="space-y-3">
          <div>
            <label class="text-xs font-medium text-slate-400 block mb-1">Nama Pelanggan *</label>
            <input type="text" name="nama_pelanggan" required placeholder="Nama lengkap"
              value="<?= e($editData['nama_pelanggan'] ?? '') ?>"
              class="w-full px-3 py-2.5 rounded-xl text-sm">
          </div>
          <div>
            <label class="text-xs font-medium text-slate-400 block mb-1">Nomor HP *</label>
            <input type="text" name="no_hp" required placeholder="08xxxxxxxxxx"
              value="<?= e($editData['no_hp'] ?? '') ?>"
              class="w-full px-3 py-2.5 rounded-xl text-sm">
          </div>
          <div>
            <label class="text-xs font-medium text-slate-400 block mb-1">Poin Loyalitas</label>
            <input type="number" name="poin" min="0" placeholder="0"
              value="<?= (int)($editData['poin'] ?? 0) ?>"
              class="w-full px-3 py-2.5 rounded-xl text-sm">
          </div>
        </div>
        <div class="flex gap-2 mt-4">
          <button type="submit" class="btn-primary flex-1 py-2.5 rounded-xl text-white text-sm font-bold">
            <i class="fas fa-save mr-1.5"></i> Simpan
          </button>
          <?php if ($editData): ?>
          <a href="pelanggan.php" class="px-4 py-2.5 rounded-xl text-sm text-slate-400 hover:text-white hover:bg-white/10 transition-colors">
            Batal
          </a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <!-- Table -->
  <div class="flex-1">
    <!-- Search -->
    <form method="GET" class="mb-4 flex gap-2">
      <div class="relative flex-1">
        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-sm"></i>
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Cari nama atau no. HP..."
          class="w-full pl-9 pr-4 py-2.5 rounded-xl text-sm">
      </div>
      <button type="submit" class="btn-primary px-4 py-2.5 rounded-xl text-white text-sm font-medium">Cari</button>
      <?php if ($search): ?><a href="pelanggan.php" class="px-4 py-2.5 rounded-xl text-sm text-slate-400 hover:bg-white/10 transition-colors flex items-center">Reset</a><?php endif; ?>
    </form>

    <div class="card-glass rounded-2xl overflow-hidden">
      <div class="px-5 py-4 border-b flex items-center justify-between" style="border-color: rgba(255,255,255,0.06);">
        <h3 class="font-bold text-white text-sm">Daftar Pelanggan</h3>
        <span class="text-xs text-slate-500"><?= count($rows) ?> data</span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-xs text-slate-500 uppercase" style="background: rgba(255,255,255,0.03);">
              <th class="px-5 py-3 text-left">No</th>
              <th class="px-5 py-3 text-left">Nama</th>
              <th class="px-5 py-3 text-left">No. HP</th>
              <th class="px-5 py-3 text-center">Poin</th>
              <th class="px-5 py-3 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $i => $row): ?>
            <tr class="table-row border-t" style="border-color: rgba(255,255,255,0.04);">
              <td class="px-5 py-3 text-slate-500 text-xs"><?= $i+1 ?></td>
              <td class="px-5 py-3">
                <div class="flex items-center gap-2.5">
                  <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold text-white flex-shrink-0" style="background: linear-gradient(135deg,#00c4f5,#0077aa);">
                    <?= strtoupper(substr($row['nama_pelanggan'], 0, 1)) ?>
                  </div>
                  <span class="text-white font-medium"><?= e($row['nama_pelanggan']) ?></span>
                </div>
              </td>
              <td class="px-5 py-3 text-slate-300 font-mono text-xs"><?= e($row['no_hp']) ?></td>
              <td class="px-5 py-3 text-center">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold" style="background: rgba(245,158,11,0.15); color: #f59e0b;">
                  <i class="fas fa-star text-xs mr-0.5"></i><?= $row['poin'] ?>
                </span>
              </td>
              <td class="px-5 py-3 text-center">
                <div class="flex items-center justify-center gap-2">
                  <a href="?edit=<?= $row['id_pelanggan'] ?>" class="w-7 h-7 rounded-lg flex items-center justify-center hover:bg-cyan-500/20 transition-colors" style="color: #00c4f5;" title="Edit">
                    <i class="fas fa-edit text-xs"></i>
                  </a>
                  <form method="POST" class="inline">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id_pelanggan" value="<?= $row['id_pelanggan'] ?>">
                    <button type="submit" data-confirm="Hapus pelanggan '<?= e($row['nama_pelanggan']) ?>'?"
                      class="w-7 h-7 rounded-lg flex items-center justify-center hover:bg-red-500/20 transition-colors text-red-400" title="Hapus">
                      <i class="fas fa-trash text-xs"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
            <tr><td colspan="5" class="px-5 py-10 text-center text-slate-600">
              <i class="fas fa-users text-2xl mb-2 block opacity-30"></i>Belum ada pelanggan
            </td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

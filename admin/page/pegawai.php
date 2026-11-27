<?php
// ============================================================
//  admin/pages/pegawai.php — Employee Management (Phase 1: View 2)
// ============================================================
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
requireAdmin(); // Only admin can manage employees
$pageTitle = 'Manajemen Pegawai';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $nama   = trim($_POST['nama_pegawai'] ?? '');
    $uname  = trim($_POST['username'] ?? '');
    $pass   = $_POST['password'] ?? '';
    $level  = in_array($_POST['level'] ?? '', ['admin','kasir']) ? $_POST['level'] : 'kasir';

    if ($action === 'add') {
        if ($nama && $uname && $pass) {
            $existing = csvFindOne('pegawai', 'username', $uname);
            if (!$existing) {
                csvInsert('pegawai', [
                    'nama_pegawai' => $nama,
                    'username' => $uname,
                    'password' => $pass,
                    'level' => $level,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                setFlash('success', 'Pegawai berhasil ditambahkan.');
            } else {
                setFlash('error', 'Username sudah digunakan.');
            }
        } else {
            setFlash('error', 'Semua kolom wajib diisi.');
        }
    } elseif ($action === 'edit') {
        $id = (int)$_POST['id_pegawai'];
        $updateData = [
            'nama_pegawai' => $nama,
            'username' => $uname,
            'level' => $level,
        ];
        if ($pass) {
            $updateData['password'] = $pass;
        }
        csvUpdate('pegawai', 'id_pegawai', $id, $updateData);
        setFlash('success', 'Data pegawai diperbarui.');
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id_pegawai'];
        if ($id == currentUser()['id']) {
            setFlash('error', 'Tidak dapat menghapus akun sendiri.');
        } else {
            csvDelete('pegawai', 'id_pegawai', $id);
            setFlash('success', 'Pegawai dihapus.');
        }
    }
    header('Location: pegawai.php'); exit;
}

$rows = csvReadAll('pegawai');
$rows = csvSort($rows, 'nama_pegawai', 'ASC');
$editData = null;
if (isset($_GET['edit'])) {
    $editData = csvFindOne('pegawai', 'id_pegawai', (int)$_GET['edit']);
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex gap-6">

  <!-- Form -->
  <div class="w-80 flex-shrink-0">
    <div class="card-glass rounded-2xl p-5 sticky top-24">
      <h2 class="font-bold text-white mb-4 flex items-center gap-2">
        <i class="fas fa-user-plus text-cyan-400"></i>
        <?= $editData ? 'Edit Pegawai' : 'Tambah Pegawai' ?>
      </h2>
      <form method="POST">
        <input type="hidden" name="action" value="<?= $editData ? 'edit' : 'add' ?>">
        <?php if ($editData): ?>
        <input type="hidden" name="id_pegawai" value="<?= $editData['id_pegawai'] ?>">
        <?php endif; ?>
        <div class="space-y-3">
          <div>
            <label class="text-xs font-medium text-slate-400 block mb-1">Nama Lengkap *</label>
            <input type="text" name="nama_pegawai" required placeholder="Nama pegawai"
              value="<?= e($editData['nama_pegawai'] ?? '') ?>" class="w-full px-3 py-2.5 rounded-xl text-sm">
          </div>
          <div>
            <label class="text-xs font-medium text-slate-400 block mb-1">Username *</label>
            <input type="text" name="username" required placeholder="username_login"
              value="<?= e($editData['username'] ?? '') ?>" class="w-full px-3 py-2.5 rounded-xl text-sm">
          </div>
          <div>
            <label class="text-xs font-medium text-slate-400 block mb-1">
              Password <?= $editData ? '(kosongkan jika tidak diubah)' : '*' ?>
            </label>
            <input type="password" name="password" <?= !$editData ? 'required' : '' ?> placeholder="••••••••"
              class="w-full px-3 py-2.5 rounded-xl text-sm">
          </div>
          <div>
            <label class="text-xs font-medium text-slate-400 block mb-1">Level Akses *</label>
            <select name="level" class="w-full px-3 py-2.5 rounded-xl text-sm">
              <option value="kasir" <?= ($editData['level'] ?? '') === 'kasir' ? 'selected' : '' ?>>Kasir</option>
              <option value="admin" <?= ($editData['level'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
            </select>
          </div>
        </div>
        <div class="flex gap-2 mt-4">
          <button type="submit" class="btn-primary flex-1 py-2.5 rounded-xl text-white text-sm font-bold">
            <i class="fas fa-save mr-1.5"></i> Simpan
          </button>
          <?php if ($editData): ?>
          <a href="pegawai.php" class="px-4 py-2.5 rounded-xl text-sm text-slate-400 hover:text-white hover:bg-white/10 transition-colors">Batal</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <!-- Table -->
  <div class="flex-1">
    <div class="card-glass rounded-2xl overflow-hidden">
      <div class="px-5 py-4 border-b" style="border-color: rgba(255,255,255,0.06);">
        <h3 class="font-bold text-white text-sm">Daftar Pegawai</h3>
      </div>
      <table class="w-full text-sm">
        <thead>
          <tr class="text-xs text-slate-500 uppercase" style="background: rgba(255,255,255,0.03);">
            <th class="px-5 py-3 text-left">Nama</th>
            <th class="px-5 py-3 text-left">Username</th>
            <th class="px-5 py-3 text-center">Level</th>
            <th class="px-5 py-3 text-center">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
          <tr class="table-row border-t" style="border-color: rgba(255,255,255,0.04);">
            <td class="px-5 py-3">
              <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-white"
                  style="background: <?= $row['level'] === 'admin' ? 'linear-gradient(135deg,#8b5cf6,#6d28d9)' : 'linear-gradient(135deg,#00c4f5,#0077aa)' ?>">
                  <?= strtoupper(substr($row['nama_pegawai'], 0, 1)) ?>
                </div>
                <div>
                  <div class="text-white font-medium"><?= e($row['nama_pegawai']) ?></div>
                  <div class="text-xs text-slate-600"><?= tglIndo($row['created_at']) ?></div>
                </div>
              </div>
            </td>
            <td class="px-5 py-3 font-mono text-xs text-slate-300"><?= e($row['username']) ?></td>
            <td class="px-5 py-3 text-center">
              <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $row['level'] === 'admin' ? 'bg-violet-500/15 text-violet-400' : 'bg-cyan-500/15 text-cyan-400' ?>">
                <?= ucfirst($row['level']) ?>
              </span>
            </td>
            <td class="px-5 py-3 text-center">
              <div class="flex items-center justify-center gap-2">
                <a href="?edit=<?= $row['id_pegawai'] ?>" class="w-7 h-7 rounded-lg flex items-center justify-center hover:bg-cyan-500/20 transition-colors" style="color:#00c4f5;">
                  <i class="fas fa-edit text-xs"></i>
                </a>
                <?php if ($row['id_pegawai'] != currentUser()['id']): ?>
                <form method="POST" class="inline">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id_pegawai" value="<?= $row['id_pegawai'] ?>">
                  <button type="submit" data-confirm="Hapus pegawai '<?= e($row['nama_pegawai']) ?>'?"
                    class="w-7 h-7 rounded-lg flex items-center justify-center hover:bg-red-500/20 transition-colors text-red-400">
                    <i class="fas fa-trash text-xs"></i>
                  </button>
                </form>
                <?php else: ?>
                <span class="w-7 h-7 flex items-center justify-center text-slate-700 text-xs" title="Akun aktif Anda">
                  <i class="fas fa-lock"></i>
                </span>
                <?php endif; ?>
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

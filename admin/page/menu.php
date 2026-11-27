<?php
// ============================================================
//  admin/pages/menu.php — Product Management (Phase 2: View 4)
// ============================================================
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
requireLogin();
$pageTitle = 'Manajemen Produk';

$UPLOAD_DIR  = __DIR__ . '/../../uploads/products/';

// Deteksi base path project secara otomatis (support subfolder XAMPP/Laragon)
$scriptPath  = str_replace('\\', '/', dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))));
$scriptPath  = rtrim($scriptPath, '/');
$UPLOAD_URL  = $scriptPath . '/uploads/products/';

$VALID_TYPES = ['image/jpeg','image/png','image/webp','image/gif'];
$MAX_SIZE    = 2 * 1024 * 1024; // 2MB

// Buat folder jika belum ada
if (!is_dir($UPLOAD_DIR)) {
    mkdir($UPLOAD_DIR, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $nama    = trim($_POST['nama_produk'] ?? '');
        $harga   = (float)($_POST['harga_jual'] ?? 0);
        $hargaModal = (float)($_POST['harga_modal'] ?? 0);
        $kat     = in_array($_POST['kategori'] ?? '', ['pulsa','paket data','aksesoris','voucher']) ? $_POST['kategori'] : 'pulsa';
        $stok    = (int)($_POST['stok'] ?? 0);
        $aktif   = isset($_POST['is_active']) ? 1 : 0;
        $foto    = $action === 'edit' ? ($_POST['existing_foto'] ?? '') : '';

        // Handle image upload
        if (isset($_FILES['foto_produk']) && $_FILES['foto_produk']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['foto_produk'];
            // Gunakan finfo untuk cek MIME type sesungguhnya (lebih aman)
            $finfo    = finfo_open(FILEINFO_MIME_TYPE);
            $realMime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if (in_array($realMime, $VALID_TYPES) && $file['size'] <= $MAX_SIZE) {
                // Paksa ekstensi berdasarkan MIME agar konsisten
                $extMap   = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
                $ext      = $extMap[$realMime] ?? pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = uniqid('prod_') . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $UPLOAD_DIR . $filename)) {
                    // Hapus gambar lama
                    if ($foto && file_exists($UPLOAD_DIR . $foto)) @unlink($UPLOAD_DIR . $foto);
                    $foto = $filename;
                } else {
                    setFlash('error', 'Gagal menyimpan gambar. Pastikan folder uploads/products/ bisa ditulis (writable).');
                    header('Location: menu.php'); exit;
                }
            } else {
                setFlash('error', 'Gambar tidak valid atau terlalu besar (max 2MB, JPG/PNG/WEBP).');
                header('Location: menu.php'); exit;
            }
        }

        if ($nama && $harga >= 0) {
            if ($action === 'add') {
                $newId = csvInsert('menu', [
                    'nama_produk' => $nama,
                    'harga_jual' => $harga,
                    'harga_modal' => $hargaModal,
                    'kategori' => $kat,
                    'foto_produk' => $foto,
                    'stok' => $stok,
                    'is_active' => $aktif,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                // Log stock history
                if ($stok > 0) {
                    logStockChange($newId, $nama, 0, $stok, 'Produk baru ditambahkan');
                }
                setFlash('success', 'Produk berhasil ditambahkan.');
            } else {
                $id = (int)$_POST['id_menu'];
                $oldData = csvFindOne('menu', 'id_menu', $id);
                $oldStok = (int)($oldData['stok'] ?? 0);
                csvUpdate('menu', 'id_menu', $id, [
                    'nama_produk' => $nama,
                    'harga_jual' => $harga,
                    'harga_modal' => $hargaModal,
                    'kategori' => $kat,
                    'foto_produk' => $foto,
                    'stok' => $stok,
                    'is_active' => $aktif,
                ]);
                // Log stock change
                if ($stok != $oldStok) {
                    logStockChange($id, $nama, $oldStok, $stok, 'Edit produk (stok diubah manual)');
                }
                setFlash('success', 'Produk berhasil diperbarui.');
            }
        } else {
            setFlash('error', 'Nama produk wajib diisi.');
        }
    } elseif ($action === 'delete') {
        $id   = (int)$_POST['id_menu'];
        $item = csvFindOne('menu', 'id_menu', $id);
        if ($item && $item['foto_produk'] && file_exists($UPLOAD_DIR . $item['foto_produk'])) {
            @unlink($UPLOAD_DIR . $item['foto_produk']);
        }
        // Log stock removal
        logStockChange($id, $item['nama_produk'] ?? '', (int)($item['stok'] ?? 0), 0, 'Produk dihapus');
        csvDelete('menu', 'id_menu', $id);
        setFlash('success', 'Produk dihapus.');
    } elseif ($action === 'toggle') {
        $id = (int)$_POST['id_menu'];
        $item = csvFindOne('menu', 'id_menu', $id);
        if ($item) {
            $newActive = ($item['is_active'] ?? 1) == 1 ? 0 : 1;
            csvUpdate('menu', 'id_menu', $id, ['is_active' => $newActive]);
        }
    }
    header('Location: menu.php'); exit;
}

$filterKat = $_GET['kat'] ?? '';
$rows = csvReadAll('menu');
if ($filterKat) {
    $rows = array_filter($rows, fn($r) => ($r['kategori'] ?? '') === $filterKat);
    $rows = array_values($rows);
}
$rows = csvSort($rows, 'nama_produk', 'ASC');

$editData = null;
if (isset($_GET['edit'])) {
    $editData = csvFindOne('menu', 'id_menu', (int)$_GET['edit']);
}

$kategoriList = ['pulsa','paket data','aksesoris','voucher'];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex gap-6">

  <!-- Form Panel -->
  <div class="w-80 flex-shrink-0">
    <div class="card-glass rounded-2xl p-5 sticky top-24">
      <h2 class="font-bold text-white mb-4 flex items-center gap-2">
        <i class="fas fa-<?= $editData ? 'edit' : 'plus-square' ?> text-cyan-400"></i>
        <?= $editData ? 'Edit Produk' : 'Tambah Produk' ?>
      </h2>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="<?= $editData ? 'edit' : 'add' ?>">
        <?php if ($editData): ?>
        <input type="hidden" name="id_menu" value="<?= $editData['id_menu'] ?>">
        <input type="hidden" name="existing_foto" value="<?= e($editData['foto_produk'] ?? '') ?>">
        <?php endif; ?>

        <!-- Image Preview -->
        <?php if ($editData && $editData['foto_produk']): ?>
        <div class="mb-3 rounded-xl overflow-hidden h-32 flex items-center justify-center" style="background: rgba(255,255,255,0.05);">
          <img src="<?= $UPLOAD_URL . e($editData['foto_produk']) ?>" class="h-full object-contain">
        </div>
        <?php endif; ?>

        <div class="space-y-3">
          <div>
            <label class="text-xs font-medium text-slate-400 block mb-1">Nama Produk *</label>
            <input type="text" name="nama_produk" required placeholder="Nama lengkap produk"
              value="<?= e($editData['nama_produk'] ?? '') ?>" class="w-full px-3 py-2.5 rounded-xl text-sm">
          </div>
          <div>
            <label class="text-xs font-medium text-slate-400 block mb-1">Harga Jual (Rp)</label>
            <input type="number" name="harga_jual" min="0" step="500" placeholder="0"
              value="<?= (float)($editData['harga_jual'] ?? 0) ?>" class="w-full px-3 py-2.5 rounded-xl text-sm">
          </div>
          <div>
            <label class="text-xs font-medium text-slate-400 block mb-1">Harga Modal / HPP (Rp)</label>
            <input type="number" name="harga_modal" min="0" step="500" placeholder="0"
              value="<?= (float)($editData['harga_modal'] ?? 0) ?>" class="w-full px-3 py-2.5 rounded-xl text-sm">
          </div>
          <div>
            <label class="text-xs font-medium text-slate-400 block mb-1">Kategori</label>
            <select name="kategori" class="w-full px-3 py-2.5 rounded-xl text-sm">
              <?php foreach ($kategoriList as $k): ?>
              <option value="<?= $k ?>" <?= ($editData['kategori'] ?? '') === $k ? 'selected' : '' ?>><?= ucwords($k) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="text-xs font-medium text-slate-400 block mb-1">Stok</label>
            <input type="number" name="stok" min="0" placeholder="0"
              value="<?= (int)($editData['stok'] ?? 0) ?>" class="w-full px-3 py-2.5 rounded-xl text-sm">
          </div>
          <div>
            <label class="text-xs font-medium text-slate-400 block mb-1">Foto Produk (JPG/PNG, max 2MB)</label>
            <input type="file" name="foto_produk" accept="image/*" class="w-full px-3 py-2 rounded-xl text-xs text-slate-400">
          </div>
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="is_active" value="1" class="rounded" <?= ($editData['is_active'] ?? 1) ? 'checked' : '' ?>>
            <span class="text-xs text-slate-400">Produk Aktif (tampil di kasir)</span>
          </label>
        </div>
        <div class="flex gap-2 mt-4">
          <button type="submit" class="btn-primary flex-1 py-2.5 rounded-xl text-white text-sm font-bold">
            <i class="fas fa-save mr-1.5"></i> Simpan
          </button>
          <?php if ($editData): ?>
          <a href="menu.php" class="px-4 py-2.5 rounded-xl text-sm text-slate-400 hover:bg-white/10 transition-colors">Batal</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <!-- Product Grid -->
  <div class="flex-1">
    <!-- Filter Tabs -->
    <div class="flex gap-2 mb-4 flex-wrap">
      <a href="menu.php" class="px-4 py-1.5 rounded-full text-xs font-medium transition-all <?= !$filterKat ? 'text-dark-900 bg-cyan-400' : 'text-slate-400 hover:text-white border border-white/10' ?>">Semua</a>
      <?php foreach ($kategoriList as $k): ?>
      <a href="?kat=<?= $k ?>" class="px-4 py-1.5 rounded-full text-xs font-medium transition-all <?= $filterKat === $k ? 'text-dark-900 bg-cyan-400' : 'text-slate-400 hover:text-white border border-white/10' ?>">
        <?= ucwords($k) ?>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Grid -->
    <div class="grid grid-cols-2 xl:grid-cols-3 gap-3">
      <?php foreach ($rows as $item): ?>
      <div class="card-glass rounded-xl overflow-hidden flex flex-col <?= !$item['is_active'] ? 'opacity-50' : '' ?>">
        <!-- Image -->
        <div class="h-28 flex items-center justify-center" style="background: rgba(255,255,255,0.04);">
          <?php if ($item['foto_produk']): ?>
          <img src="<?= $UPLOAD_URL . e($item['foto_produk']) ?>" class="h-full w-full object-cover">
          <?php else: ?>
          <?php $icons = ['pulsa'=>'fa-sim-card','paket data'=>'fa-wifi','aksesoris'=>'fa-headphones','voucher'=>'fa-ticket-alt']; ?>
          <i class="fas <?= $icons[$item['kategori']] ?? 'fa-box' ?> text-3xl" style="color: rgba(0,196,245,0.4);"></i>
          <?php endif; ?>
        </div>
        <!-- Info -->
        <div class="p-3 flex-1 flex flex-col">
          <span class="text-xs font-medium px-2 py-0.5 rounded-full mb-1.5 self-start capitalize" style="background: rgba(0,196,245,0.12); color: #00c4f5;">
            <?= e($item['kategori']) ?>
          </span>
          <p class="text-white text-xs font-semibold leading-tight flex-1"><?= e($item['nama_produk']) ?></p>
          <div class="mt-2 flex items-center justify-between">
            <span class="font-bold text-sm" style="color: #00c4f5;"><?= rupiah($item['harga_jual']) ?></span>
            <span class="text-xs text-slate-500">Stok: <?= $item['stok'] ?></span>
          </div>
        </div>
        <!-- Actions -->
        <div class="px-3 pb-3 flex gap-1.5">
          <a href="?edit=<?= $item['id_menu'] ?>" class="flex-1 py-1.5 rounded-lg text-xs font-medium text-center transition-colors hover:bg-cyan-500/20" style="border: 1px solid rgba(0,196,245,0.3); color: #00c4f5;">
            Edit
          </a>
          <form method="POST" class="inline">
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id_menu" value="<?= $item['id_menu'] ?>">
            <button type="submit" class="px-2.5 py-1.5 rounded-lg text-xs transition-colors hover:bg-white/10 text-slate-400" title="<?= $item['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>">
              <i class="fas fa-<?= $item['is_active'] ? 'eye-slash' : 'eye' ?>"></i>
            </button>
          </form>
          <form method="POST" class="inline">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id_menu" value="<?= $item['id_menu'] ?>">
            <button type="submit" data-confirm="Hapus produk '<?= e($item['nama_produk']) ?>'?" class="px-2.5 py-1.5 rounded-lg text-xs text-red-400 hover:bg-red-500/20 transition-colors">
              <i class="fas fa-trash"></i>
            </button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($rows)): ?>
      <div class="col-span-3 py-16 text-center text-slate-600">
        <i class="fas fa-box-open text-4xl mb-3 block opacity-30"></i>Belum ada produk
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
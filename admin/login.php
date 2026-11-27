<?php
// ============================================================
//  admin/login.php — Login Page
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (isLoggedIn()) { 
    header('Location: /SAVACELL/admin/index.php'); 
    exit; 
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $pegawai = csvFindOne('pegawai', 'username', $username);
        
        if ($pegawai && $password === $pegawai['password']) {
            $_SESSION['pegawai_id']    = $pegawai['id_pegawai'];
            $_SESSION['pegawai_nama']  = $pegawai['nama_pegawai'];
            $_SESSION['pegawai_level'] = $pegawai['level'];
            header('Location: /SAVACELL/admin/index.php');
            exit;
        } else {
            $error = 'Username atau password salah.';
        }
    } else {
        $error = 'Harap isi semua kolom.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login — Sava Cell Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body { background: #09111a; font-family: 'Plus Jakarta Sans', sans-serif; }
    .glow { box-shadow: 0 0 60px rgba(0,196,245,0.12), 0 0 120px rgba(0,119,170,0.08); }
    input { background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); color: #e2e8f0; }
    input:focus { border-color: #00c4f5; outline: none; box-shadow: 0 0 0 3px rgba(0,196,245,0.15); }
    .btn-login { background: linear-gradient(135deg, #00c4f5, #0077aa); }
    .btn-login:hover { opacity: 0.9; transform: translateY(-1px); }
  </style>
</head>
<body class="min-h-screen flex items-center justify-center px-4">

  <!-- Background grid -->
  <div class="fixed inset-0 opacity-5" style="background-image: linear-gradient(rgba(0,196,245,0.3) 1px, transparent 1px), linear-gradient(90deg, rgba(0,196,245,0.3) 1px, transparent 1px); background-size: 40px 40px;"></div>

  <div class="relative z-10 w-full max-w-sm">

    <!-- Logo -->
    <div class="text-center mb-8">
      <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl mb-4" style="background: linear-gradient(135deg,#00c4f5,#0077aa);">
        <i class="fas fa-mobile-alt text-white text-2xl"></i>
      </div>
      <h1 class="text-2xl font-extrabold text-white">Sava Cell</h1>
      <p class="text-slate-400 text-sm mt-1">Admin Dashboard Login</p>
    </div>

    <!-- Card -->
    <div class="glow rounded-2xl p-7" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);">

      <?php if ($error): ?>
      <div class="mb-4 px-4 py-3 rounded-lg text-sm text-red-400 flex items-center gap-2" style="background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.25);">
        <i class="fas fa-exclamation-circle"></i> <?= e($error) ?>
      </div>
      <?php endif; ?>

      <form method="POST">
        <div class="mb-4">
          <label class="block text-sm font-medium text-slate-300 mb-2">Username</label>
          <div class="relative">
            <i class="fas fa-user absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-sm"></i>
            <input type="text" name="username" required placeholder="Masukkan username"
              value="<?= e($_POST['username'] ?? '') ?>"
              class="w-full pl-9 pr-4 py-3 rounded-xl text-sm transition-all text-gray-500">
          </div>
        </div>
        <div class="mb-6">
          <label class="block text-sm font-medium text-slate-300 mb-2">Password</label>
          <div class="relative">
            <i class="fas fa-lock absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-sm"></i>
            <input type="password" name="password" required placeholder="Masukkan password"
              class="w-full pl-9 pr-4 py-3 rounded-xl text-sm transition-all text-gray-500">
          </div>
        </div>
        <button type="submit" class="btn-login w-full py-3 rounded-xl text-white font-bold text-sm transition-all">
          <i class="fas fa-sign-in-alt mr-2"></i> Masuk ke Dashboard
        </button>
      </form>

      <p class="text-center text-xs text-slate-600 mt-5">
        Default: <span class="text-slate-400">admin / password</span>
      </p>
    </div>

    <p class="text-center text-xs text-slate-700 mt-6">
      &copy; <?= date('Y') ?> Sava Cell — All rights reserved
    </p>
  </div>
</body>
</html>

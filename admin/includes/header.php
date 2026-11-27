<?php
// ============================================================
//  admin/includes/header.php — Dashboard Layout Header
// ============================================================
require_once __DIR__ . '/../../config/auth.php';
requireLogin();
$user = currentUser();
$flash = getFlash();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $pageTitle ?? 'Sava Cell' ?> — Admin Panel</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: {
              50:  '#e0f7ff',
              100: '#b3ecff',
              200: '#4dd8ff',
              300: '#00c4f5',
              400: '#00aadc',
              500: '#0090c3',
              600: '#0077aa',
              700: '#005f8e',
              800: '#004872',
              900: '#003057',
            },
            dark: {
              800: '#0f1923',
              850: '#0d1520',
              900: '#09111a',
              950: '#060d14',
            }
          },
          fontFamily: {
            sans: ['Plus Jakarta Sans', 'sans-serif'],
            mono: ['JetBrains Mono', 'monospace'],
          }
        }
      }
    }
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <style>
    body { background-color: #0f1923; color: #e2e8f0; }
    .sidebar-link { transition: all .2s ease; }
    .sidebar-link:hover, .sidebar-link.active {
      background: linear-gradient(90deg, rgba(0,196,245,.15) 0%, transparent 100%);
      border-left: 3px solid #00c4f5;
      color: #00c4f5;
    }
    .sidebar-link { border-left: 3px solid transparent; }
    .card-glass {
      background: rgba(255,255,255,0.04);
      border: 1px solid rgba(255,255,255,0.08);
      backdrop-filter: blur(12px);
    }
    .btn-primary {
      background: linear-gradient(135deg, #00c4f5, #0077aa);
      transition: all .2s;
    }
    .btn-primary:hover { opacity: .9; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(0,196,245,.3); }
    .table-row:hover { background: rgba(0,196,245,0.05); }
    input, select, textarea {
      background: rgba(255,255,255,0.06) !important;
      border: 1px solid rgba(255,255,255,0.12) !important;
      color: #e2e8f0 !important;
    }
    input:focus, select:focus, textarea:focus {
      border-color: #00c4f5 !important;
      outline: none;
      box-shadow: 0 0 0 3px rgba(0,196,245,0.15) !important;
    }
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: #0f1923; }
    ::-webkit-scrollbar-thumb { background: #1e3a4a; border-radius: 3px; }

/* Custom styles for select options */
    select option {
      background-color: #0d1a2a; 
      color: #e2e8f0;
    }
    select option:checked {
      background-color: #00c4f5;   
      color: #060d14;
    }

    /* Light mode */
    [data-theme="light"] select option {
      background-color: #ffffff;
      color: #1e293b;
    }
  </style>
</head>
<body class="font-sans min-h-screen flex">

<!-- ===== SIDEBAR ===== -->
<aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 flex flex-col"
  style="background: linear-gradient(180deg, #09111a 0%, #0d1520 100%); border-right: 1px solid rgba(0,196,245,0.1);">

  <!-- Logo -->
  <div class="flex items-center gap-3 px-6 py-5 border-b" style="border-color: rgba(0,196,245,0.12);">
    <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background: linear-gradient(135deg, #00c4f5, #0077aa);">
      <i class="fas fa-mobile-alt text-white text-sm"></i>
    </div>
    <div>
      <div class="font-bold text-white text-base leading-none">Sava Cell</div>
      <div class="text-xs mt-0.5" style="color: #00c4f5;">Admin Panel</div>
    </div>
  </div>

  <!-- Nav -->
<nav class="flex-1 overflow-y-auto py-4 px-3 space-y-0.5">

    <p class="text-xs font-semibold uppercase tracking-widest px-3 py-2" style="color: rgba(255,255,255,0.3);">Main</p>

    <a href="../index.php" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium <?= $currentPage === 'index' ? 'active' : 'text-slate-400' ?>">
      <i class="fas fa-chart-line w-4 text-center"></i> Dashboard
    </a>
    <a href="/SAVACELL/admin/page/transaksi.php" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium <?= $currentPage === 'transaksi' ? 'active' : 'text-slate-400' ?>">
      <i class="fas fa-cash-register w-4 text-center"></i> Kasir / POS
    </a>
    
    <?php if (isAdmin()): ?>
      <a href="/SAVACELL/admin/page/laporan.php" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium <?= $currentPage === 'laporan' ? 'active' : 'text-slate-400' ?>">
        <i class="fas fa-file-invoice-dollar w-4 text-center"></i> Laporan
      </a>

      <p class="text-xs font-semibold uppercase tracking-widest px-3 py-2 mt-3" style="color: rgba(255,255,255,0.3);">Master Data</p>

      <a href="/SAVACELL/admin/page/menu.php" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium <?= $currentPage === 'menu' ? 'active' : 'text-slate-400' ?>">
        <i class="fas fa-th-large w-4 text-center"></i> Produk / Menu
      </a>
      
      <a href="/SAVACELL/admin/page/pelanggan.php" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium <?= $currentPage === 'pelanggan' ? 'active' : 'text-slate-400' ?>">
        <i class="fas fa-users w-4 text-center"></i> Pelanggan
      </a>
      
      <a href="/SAVACELL/admin/page/bahan_baku.php" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium <?= $currentPage === 'bahan_baku' ? 'active' : 'text-slate-400' ?>">
        <i class="fas fa-warehouse w-4 text-center"></i> Stok Saldo
      </a>

      <a href="/SAVACELL/admin/page/pegawai.php" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium <?= $currentPage === 'pegawai' ? 'active' : 'text-slate-400' ?>">
        <i class="fas fa-user-tie w-4 text-center"></i> Pegawai
      </a>

      <p class="text-xs font-semibold uppercase tracking-widest px-3 py-2 mt-3" style="color: rgba(255,255,255,0.3);">Analitik</p>

      <a href="/SAVACELL/admin/page/laba_rugi.php" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium <?= $currentPage === 'laba_rugi' ? 'active' : 'text-slate-400' ?>">
        <i class="fas fa-balance-scale w-4 text-center"></i> Laba & Rugi
      </a>

      <a href="/SAVACELL/admin/page/stock_history.php" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium <?= $currentPage === 'stock_history' ? 'active' : 'text-slate-400' ?>">
        <i class="fas fa-history w-4 text-center"></i> Riwayat Stok
      </a>

      <a href="/SAVACELL/admin/page/grafik_penjualan.php" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium <?= $currentPage === 'grafik_penjualan' ? 'active' : 'text-slate-400' ?>">
        <i class="fas fa-chart-bar w-4 text-center"></i> Grafik Penjualan
      </a>
    <?php endif; ?>

  </nav>

  <!-- User Footer -->
  <div class="px-4 py-4 border-t" style="border-color: rgba(0,196,245,0.1);">
    <div class="flex items-center gap-3 mb-3">
      <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-white" style="background: linear-gradient(135deg,#00c4f5,#0077aa);">
        <?= strtoupper(substr($user['nama'], 0, 1)) ?>
      </div>
      <div class="flex-1 min-w-0">
        <div class="text-sm font-semibold text-white truncate"><?= e($user['nama']) ?></div>
        <div class="text-xs capitalize" style="color: #00c4f5;"><?= e($user['level']) ?></div>
      </div>
    </div>
    <a href="/SAVACELL/admin/logout.php" class="flex items-center gap-2 text-xs text-red-400 hover:text-red-300 px-2 py-1.5 rounded-lg hover:bg-red-400/10 transition-colors">
      <i class="fas fa-sign-out-alt"></i> Keluar
    </a>
  </div>
</aside>

<!-- ===== MAIN AREA ===== -->
<div class="flex-1 flex flex-col ml-64 min-h-screen">

  <!-- Top Bar -->
  <header class="sticky top-0 z-40 flex items-center justify-between px-6 py-4" style="background: rgba(9,17,26,0.9); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(0,196,245,0.08);">
    <div>
      <h1 class="text-lg font-bold text-white"><?= $pageTitle ?? 'Dashboard' ?></h1>
      <p class="text-xs text-slate-500 mt-0.5"><?= date('l, d F Y') ?></p>
    </div>
    <div class="flex items-center gap-3">
      <a href="/SAVACELL/public/index.php" target="_blank" class="text-xs px-3 py-1.5 rounded-lg border flex items-center gap-2 hover:bg-white/5 transition-colors" style="border-color: rgba(0,196,245,0.3); color: #00c4f5;">
        <i class="fas fa-globe text-xs"></i> Lihat Web
      </a>
    </div>
  </header>

  <!-- Flash Message -->
  <?php if ($flash): ?>
  <div class="mx-6 mt-4 px-4 py-3 rounded-xl flex items-center gap-3 text-sm font-medium
    <?= $flash['type'] === 'success' ? 'bg-emerald-500/15 border border-emerald-500/30 text-emerald-400' : 'bg-red-500/15 border border-red-500/30 text-red-400' ?>">
    <i class="fas <?= $flash['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
    <?= e($flash['message']) ?>
  </div>
  <?php endif; ?>

  <!-- Page Content -->
  <main class="flex-1 p-6">

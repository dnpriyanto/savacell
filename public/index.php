<?php
// ============================================================
//  public/index.php — Landing Page (View 8)
//  Pulls live products from `menu` table
// ============================================================
require_once __DIR__ . '/../config/database.php';

// Fungsi e() yang kita tambahkan sebelumnya
if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('rupiah')) {
    function rupiah($angka) {
        return 'Rp ' . number_format($angka, 0, ',', '.');
    }
}

// Deteksi base path project secara otomatis
// Ini penting agar URL gambar benar baik di root domain maupun subfolder XAMPP
$_BASE_PATH  = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
$UPLOAD_URL  = $_BASE_PATH . '/uploads/products/';
$UPLOAD_DIR  = __DIR__ . '/../uploads/products/';

// Fetch active pulsa & paket data for pricing table
$pulsaList = dbFetchAll("SELECT * FROM menu WHERE kategori='pulsa' AND is_active=1 ORDER BY harga_jual ASC LIMIT 12");
$paketList = dbFetchAll("SELECT * FROM menu WHERE kategori='paket data' AND is_active=1 ORDER BY harga_jual ASC LIMIT 12");
$aksList   = dbFetchAll("SELECT * FROM menu WHERE kategori='aksesoris' AND is_active=1 ORDER BY harga_jual ASC LIMIT 6");
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sava Cell — Counter HP & Pulsa Terpercaya</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root {
      --cyan: #00c4f5;
      --blue: #0077aa;
      --dark: #060d14;
      --dark2: #09111a;
      --dark3: #0d1520;
    }
    * { scroll-behavior: smooth; }
    body { background: var(--dark); color: #e2e8f0; font-family: 'Plus Jakarta Sans', sans-serif; }

    /* Nav */
    .navbar { transition: all .3s; }
    .navbar.scrolled { background: rgba(6,13,20,0.95) !important; backdrop-filter: blur(16px); box-shadow: 0 1px 0 rgba(0,196,245,0.1); }

    /* Hero */
    .hero-bg {
      background: radial-gradient(ellipse 80% 60% at 50% -10%, rgba(0,196,245,0.18) 0%, transparent 60%),
                  radial-gradient(ellipse 40% 40% at 80% 50%, rgba(0,119,170,0.12) 0%, transparent 60%),
                  var(--dark);
    }
    .hero-grid {
      background-image: linear-gradient(rgba(0,196,245,0.07) 1px, transparent 1px),
                        linear-gradient(90deg, rgba(0,196,245,0.07) 1px, transparent 1px);
      background-size: 48px 48px;
    }

    /* Glow effect */
    .glow-cyan { box-shadow: 0 0 30px rgba(0,196,245,0.25); }
    .text-gradient { background: linear-gradient(135deg, #00c4f5 0%, #ffffff 50%, #00c4f5 100%);-webkit-background-clip: text; -webkit-text-fill-color: transparent; }

    /* Cards */
    .glass-card {
      background: rgba(255,255,255,0.04);
      border: 1px solid rgba(255,255,255,0.08);
      backdrop-filter: blur(8px);
      transition: all .25s;
    }
    .glass-card:hover { border-color: rgba(0,196,245,0.4); transform: translateY(-2px); box-shadow: 0 8px 32px rgba(0,196,245,0.12); }

    /* Price Badge */
    .price-badge { background: linear-gradient(135deg, var(--cyan), var(--blue)); }

    /* Animations */
    @keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-8px)} }
    .float { animation: float 4s ease-in-out infinite; }
    @keyframes pulse-ring { 0%{transform:scale(1);opacity:1} 100%{transform:scale(1.6);opacity:0} }
    .pulse-ring::after { content:''; position:absolute; inset:0; border-radius:50%; border:2px solid var(--cyan); animation: pulse-ring 2s ease-out infinite; }

    /* Nav Link */
    .nav-link { position: relative; }
    .nav-link::after { content:''; position:absolute; bottom:-2px; left:0; width:0; height:2px; background:var(--cyan); transition:width .3s; }
    .nav-link:hover::after { width:100%; }

    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: var(--dark); }
    ::-webkit-scrollbar-thumb { background: #1e3a4a; border-radius: 3px; }

    /* ===== KERANJANG BELANJA SHOPEE-STYLE ===== */
    #cartFab {
      position: fixed; bottom: 28px; right: 28px; z-index: 900;
      width: 58px; height: 58px; border-radius: 50%;
      background: linear-gradient(135deg, #00c4f5, #0077aa);
      box-shadow: 0 6px 28px rgba(0,196,245,0.5);
      display: flex; align-items: center; justify-content: center;
      cursor: pointer; transition: transform .2s, box-shadow .2s;
    }
    #cartFab:hover { transform: scale(1.1); box-shadow: 0 12px 36px rgba(0,196,245,0.6); }
    #cartBadge {
      position: absolute; top: -5px; right: -5px;
      background: #ef4444; color: #fff; font-size: 10px; font-weight: 800;
      min-width: 20px; height: 20px; border-radius: 10px; padding: 0 4px;
      display: none; align-items: center; justify-content: center;
      border: 2px solid var(--dark);
    }
    #cartBadge.show { display: flex; animation: popIn .25s ease; }
    @keyframes popIn { 0%{transform:scale(0)} 70%{transform:scale(1.3)} 100%{transform:scale(1)} }

    #cartOverlay {
      position: fixed; inset: 0; z-index: 940;
      background: rgba(0,0,0,0.65); backdrop-filter: blur(4px);
      opacity: 0; pointer-events: none; transition: opacity .35s;
    }
    #cartOverlay.show { opacity: 1; pointer-events: all; }

    #cartDrawer {
      position: fixed; top: 0; right: -420px; bottom: 0; width: 400px; z-index: 950;
      display: flex; flex-direction: column;
      background: #0a1521;
      border-left: 1px solid rgba(0,196,245,0.15);
      box-shadow: -8px 0 48px rgba(0,0,0,0.7);
      transition: right .35s cubic-bezier(.4,0,.2,1);
    }
    #cartDrawer.open { right: 0; }

    .shop-card { cursor: pointer; position: relative; }
    .shop-card:hover { border-color: rgba(0,196,245,0.5) !important; transform: translateY(-3px) !important; box-shadow: 0 12px 36px rgba(0,196,245,0.18) !important; }
    .shop-card:active { transform: scale(0.97) !important; }
    .shop-card .add-badge {
      position: absolute; bottom: 8px; right: 8px;
      width: 28px; height: 28px; border-radius: 50%;
      background: linear-gradient(135deg,#00c4f5,#0077aa);
      display: flex; align-items: center; justify-content: center;
      opacity: 0; transform: scale(0.6); transition: all .2s;
      box-shadow: 0 3px 10px rgba(0,196,245,0.45);
      z-index: 2;
    }
    .shop-card:hover .add-badge { opacity: 1; transform: scale(1); }

    .fly-dot {
      position: fixed; width: 12px; height: 12px; border-radius: 50%;
      background: linear-gradient(135deg,#00c4f5,#0077aa);
      pointer-events: none; z-index: 9999;
    }

    .qty-btn {
      width: 26px; height: 26px; border-radius: 7px;
      border: 1px solid rgba(255,255,255,0.15);
      display: flex; align-items: center; justify-content: center;
      font-size: 16px; font-weight: 700; color: #cbd5e1; line-height: 1;
      cursor: pointer; transition: all .15s; background: rgba(255,255,255,0.05);
      flex-shrink: 0; user-select: none;
    }
    .qty-btn:hover { background: rgba(0,196,245,0.2); border-color: #00c4f5; color: #00c4f5; }

    #checkoutModal {
      position: fixed; inset: 0; z-index: 1000;
      display: flex; align-items: flex-end; justify-content: center;
      background: rgba(0,0,0,0.75); backdrop-filter: blur(6px);
      opacity: 0; pointer-events: none; transition: opacity .3s;
    }
    #checkoutModal.show { opacity: 1; pointer-events: all; }
    .modal-box {
      background: #0d1a28; border: 1px solid rgba(0,196,245,0.2);
      border-radius: 24px 24px 0 0; padding: 28px; width: 100%; max-width: 480px;
      transform: translateY(100%); transition: transform .35s cubic-bezier(.4,0,.2,1);
    }
    #checkoutModal.show .modal-box { transform: translateY(0); }

    .ph-input {
      width: 100%; padding: 11px 14px; border-radius: 12px;
      background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12);
      color: #e2e8f0; font-size: 14px; outline: none;
      transition: border-color .2s, box-shadow .2s; font-family: inherit;
    }
    .ph-input:focus { border-color: #00c4f5; box-shadow: 0 0 0 3px rgba(0,196,245,0.15); }
    .ph-input::placeholder { color: #475569; }

    @media (max-width: 480px) {
      #cartDrawer { width: 100%; right: -100%; }
      #cartFab { bottom: 18px; right: 18px; width: 52px; height: 52px; }
    }
  </style>
</head>
<body>

<!-- ===== FLOATING CART BUTTON ===== -->
<div id="cartFab" onclick="openCart()" title="Keranjang Belanja">
  <svg width="24" height="24" fill="none" stroke="white" stroke-width="2.2" viewBox="0 0 24 24">
    <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/>
    <line x1="3" y1="6" x2="21" y2="6"/>
    <path d="M16 10a4 4 0 01-8 0"/>
  </svg>
  <span id="cartBadge">0</span>
</div>

<!-- ===== CART OVERLAY ===== -->
<div id="cartOverlay" onclick="closeCart()"></div>

<!-- ===== CART DRAWER ===== -->
<div id="cartDrawer">
  <!-- Header -->
  <div class="flex items-center justify-between px-5 py-4 border-b flex-shrink-0" style="border-color:rgba(0,196,245,0.12);">
    <div class="flex items-center gap-2.5">
      <svg width="20" height="20" fill="none" stroke="#00c4f5" stroke-width="2" viewBox="0 0 24 24">
        <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/>
      </svg>
      <span class="font-bold text-white text-base">Keranjang</span>
      <span id="drawerCount" class="text-xs px-2 py-0.5 rounded-full font-semibold" style="background:rgba(0,196,245,0.15);color:#00c4f5;">0 item</span>
    </div>
    <div class="flex items-center gap-3">
      <button onclick="clearCart()" class="text-xs text-slate-500 hover:text-red-400 transition-colors">Kosongkan</button>
      <button onclick="closeCart()" class="w-7 h-7 rounded-lg flex items-center justify-center hover:bg-white/10 text-slate-400 hover:text-white transition-colors">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
  </div>

  <!-- Items List -->
  <div id="cartItems" class="flex-1 overflow-y-auto py-2">
    <div id="cartEmpty" class="flex flex-col items-center justify-center h-full py-16 text-center">
      <div class="w-20 h-20 rounded-full flex items-center justify-center mb-4" style="background:rgba(0,196,245,0.06);border:2px dashed rgba(0,196,245,0.2);">
        <svg width="32" height="32" fill="none" stroke="rgba(0,196,245,0.3)" stroke-width="1.5" viewBox="0 0 24 24">
          <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/>
        </svg>
      </div>
      <p class="text-slate-500 text-sm font-medium">Keranjang masih kosong</p>
      <p class="text-slate-700 text-xs mt-1">Klik produk untuk menambahkan</p>
    </div>
  </div>

  <!-- Footer: Total + Checkout -->
  <div class="border-t flex-shrink-0 p-5 space-y-3" style="border-color:rgba(0,196,245,0.1);">
    <div class="flex items-center justify-between">
      <span class="text-sm text-slate-400">Total Harga</span>
      <span id="cartTotal" class="text-xl font-extrabold" style="color:#00c4f5;">Rp 0</span>
    </div>
    <button onclick="openCheckout()"
      id="checkoutBtn"
      disabled
      class="w-full py-3.5 rounded-2xl text-white font-bold text-sm flex items-center justify-center gap-2 opacity-40 cursor-not-allowed transition-all"
      style="background: linear-gradient(135deg,#00c4f5,#0077aa);">
      <svg width="16" height="16" fill="none" stroke="white" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
      Pesan via WhatsApp
    </button>
    <p class="text-center text-xs text-slate-600">Pesanan akan dikirim via WhatsApp ke toko</p>
  </div>
</div>

<!-- ===== CHECKOUT MODAL ===== -->
<div id="checkoutModal">
  <div class="modal-box">
    <h3 class="text-lg font-extrabold text-white mb-1">Konfirmasi Pesanan</h3>
    <p class="text-xs text-slate-500 mb-5">Isi nomor HP untuk produk digital, lalu kirim pesanan ke WhatsApp kami</p>

    <!-- Order Summary -->
    <div id="orderSummary" class="rounded-xl mb-4 overflow-hidden" style="border:1px solid rgba(255,255,255,0.06);">
    </div>

    <!-- Phone input for each digital item -->
    <div id="phoneFields" class="space-y-3 mb-4"></div>

    <!-- Customer name -->
    <div class="mb-4">
      <label class="text-xs font-semibold text-slate-400 block mb-1.5">Nama Pemesan *</label>
      <input type="text" id="custName" placeholder="Nama lengkap Anda" class="ph-input">
    </div>

    <!-- Total -->
    <div class="flex items-center justify-between py-3 px-4 rounded-xl mb-5" style="background:rgba(0,196,245,0.08);border:1px solid rgba(0,196,245,0.2);">
      <span class="text-sm text-slate-300 font-medium">Total Pembayaran</span>
      <span id="modalTotal" class="text-lg font-extrabold" style="color:#00c4f5;">Rp 0</span>
    </div>

    <div class="flex gap-3">
      <button onclick="closeCheckout()" class="flex-1 py-3 rounded-xl text-sm font-semibold text-slate-400 hover:text-white hover:bg-white/10 transition-colors" style="border:1px solid rgba(255,255,255,0.1);">
        Kembali
      </button>
      <button onclick="sendWhatsApp()" class="flex-1 py-3 rounded-xl text-white text-sm font-bold flex items-center justify-center gap-2 transition-all hover:opacity-90"
        style="background:linear-gradient(135deg,#25d366,#128c7e);box-shadow:0 4px 20px rgba(37,211,102,0.35);">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="white"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
        Kirim ke WhatsApp
      </button>
    </div>
  </div>
</div>

<!-- ========== NAVBAR ========== -->
<nav class="navbar fixed top-0 left-0 right-0 z-50 px-6 py-4" id="navbar">
  <div class="max-w-6xl mx-auto flex items-center justify-between">
    <!-- Logo -->
    <a href="#" class="flex items-center gap-2.5">
      <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: linear-gradient(135deg,#00c4f5,#0077aa);">
        <i class="fas fa-mobile-alt text-white text-sm"></i>
      </div>
      <span class="font-extrabold text-white text-lg">Sava<span style="color:#00c4f5;">Cell</span></span>
    </a>
    <!-- Links (desktop) -->
    <div class="hidden md:flex items-center gap-7 text-sm font-medium text-slate-400">
      <a href="#layanan" class="nav-link hover:text-white transition-colors">Layanan</a>
      <a href="#harga-pulsa" class="nav-link hover:text-white transition-colors">Harga Pulsa</a>
      <a href="#harga-data" class="nav-link hover:text-white transition-colors">Paket Data</a>
      <a href="#aksesoris" class="nav-link hover:text-white transition-colors">Aksesoris</a>
      <a href="#lokasi" class="nav-link hover:text-white transition-colors">Lokasi</a>
    </div>
    <a href="../admin/login.php" class="hidden md:flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold text-white transition-all hover:opacity-90 hover:shadow-lg" style="background: linear-gradient(135deg,#00c4f5,#0077aa);">
      <i class="fas fa-user-shield text-xs"></i> Admin
    </a>
    <!-- Mobile toggle -->
    <button id="mobileToggle" class="md:hidden text-slate-400 hover:text-white" onclick="document.getElementById('mobileMenu').classList.toggle('hidden')">
      <i class="fas fa-bars text-xl"></i>
    </button>
  </div>
  <!-- Mobile Menu -->
  <div id="mobileMenu" class="hidden md:hidden mt-4 rounded-xl p-4 space-y-2 text-sm" style="background: rgba(9,17,26,0.98); border: 1px solid rgba(0,196,245,0.15);">
    <a href="#layanan" class="block py-2 text-slate-300 hover:text-cyan-400">Layanan</a>
    <a href="#harga-pulsa" class="block py-2 text-slate-300 hover:text-cyan-400">Harga Pulsa</a>
    <a href="#harga-data" class="block py-2 text-slate-300 hover:text-cyan-400">Paket Data</a>
    <a href="#lokasi" class="block py-2 text-slate-300 hover:text-cyan-400">Lokasi</a>
    <a href="/admin/login.php" class="block py-2 text-cyan-400 font-bold">Admin Panel →</a>
  </div>
</nav>

<!-- ========== HERO ========== -->
<section class="hero-bg min-h-screen flex items-center relative overflow-hidden pt-20">
  <div class="hero-grid absolute inset-0 opacity-100 pointer-events-none"></div>

  <!-- Decorative circles -->
  <div class="absolute top-24 right-16 w-72 h-72 rounded-full pointer-events-none" style="background: radial-gradient(circle, rgba(0,196,245,0.12), transparent 70%);"></div>
  <div class="absolute bottom-20 left-10 w-48 h-48 rounded-full pointer-events-none" style="background: radial-gradient(circle, rgba(0,119,170,0.1), transparent 70%);"></div>

  <div class="max-w-6xl mx-auto px-6 py-20 grid md:grid-cols-2 gap-16 items-center relative z-10">
    <!-- Left: Text -->
    <div>
      <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-xs font-medium mb-6" style="background: rgba(0,196,245,0.1); border: 1px solid rgba(0,196,245,0.25); color:#00c4f5;">
        <span class="w-1.5 h-1.5 rounded-full bg-cyan-400 relative pulse-ring"></span>
        Counter HP & Pulsa Terpercaya
      </div>
      <h1 class="text-4xl md:text-5xl font-extrabold leading-tight mb-5">
        <span class="text-white">Kebutuhan</span><br>
        <span class="text-gradient">Seluler Lengkap</span><br>
        <span class="text-white">Di Satu Tempat</span>
      </h1>
      <p class="text-slate-400 text-base leading-relaxed mb-8 max-w-md">
        Sava Cell menyediakan pulsa, paket data semua operator, aksesoris HP, dan layanan servis dengan harga terbaik dan pelayanan cepat.
      </p>
      <div class="flex flex-wrap gap-3">
        <a href="#harga-pulsa" class="px-6 py-3 rounded-xl font-bold text-white text-sm glow-cyan flex items-center gap-2 transition-all hover:scale-105" style="background: linear-gradient(135deg,#00c4f5,#0077aa);">
          <i class="fas fa-bolt"></i> Lihat Harga Pulsa
        </a>
        <a href="#lokasi" class="px-6 py-3 rounded-xl font-medium text-slate-300 text-sm flex items-center gap-2 hover:text-white transition-all" style="border: 1px solid rgba(255,255,255,0.12);">
          <i class="fas fa-map-marker-alt" style="color:#00c4f5;"></i> Temukan Kami
        </a>
      </div>
    </div>

    <!-- Right: Visual -->
    <div class="relative flex justify-center">
      <div class="float relative w-56 h-auto">
        <!-- Phone mockup -->
        <div class="w-48 h-80 mx-auto rounded-3xl relative overflow-hidden" style="background: linear-gradient(160deg,#0d1a2a,#09111a); border: 2px solid rgba(0,196,245,0.3); box-shadow: 0 20px 60px rgba(0,196,245,0.2);">
          <div class="absolute top-0 left-0 right-0 p-3" style="background: linear-gradient(180deg,rgba(0,196,245,0.15),transparent);">
            <div class="h-5 w-16 rounded-full mx-auto" style="background: rgba(255,255,255,0.1);"></div>
          </div>
          <div class="mt-10 px-3 space-y-2">
            <?php foreach (array_slice($pulsaList, 0, 4) as $p): ?>
            <div class="flex items-center justify-between px-2.5 py-1.5 rounded-lg" style="background: rgba(255,255,255,0.06); border: 1px solid rgba(0,196,245,0.15);">
              <span class="text-xs text-slate-300 font-medium"><?= e($p['nama_produk']) ?></span>
              <span class="text-xs font-bold" style="color:#00c4f5;"><?= rupiah($p['harga_jual']) ?></span>
            </div>
            <?php endforeach; ?>
            <?php if (empty($pulsaList)): ?>
            <div class="text-center text-xs text-slate-600 py-4">Produk dimuat dari database</div>
            <?php endif; ?>
          </div>
        </div>
        <!-- Floating badges -->
        <div class="absolute -right-6 top-8 px-3 py-1.5 rounded-xl text-xs font-bold text-white shadow-lg" style="background: linear-gradient(135deg,#00c4f5,#0077aa);">
          <i class="fas fa-bolt mr-1"></i> Langsung Aktif
        </div>
        <div class="absolute -left-8 bottom-12 px-3 py-1.5 rounded-xl text-xs font-bold text-white shadow-lg" style="background: linear-gradient(135deg,#8b5cf6,#6d28d9);">
          <i class="fas fa-star mr-1"></i> Harga Terbaik
        </div>
      </div>
    </div>
  </div>

  <!-- Scroll indicator -->
  <div class="absolute bottom-8 left-1/2 -translate-x-1/2 text-slate-600 text-xs flex flex-col items-center gap-2 animate-bounce">
    <span>Scroll</span>
    <i class="fas fa-chevron-down"></i>
  </div>
</section>

<!-- ========== LAYANAN / FEATURES ========== -->
<section id="layanan" class="py-20 px-6" style="background: var(--dark2);">
  <div class="max-w-6xl mx-auto">
    <div class="text-center mb-12">
      <p class="text-xs font-semibold tracking-widest uppercase mb-2" style="color:#00c4f5;">Layanan Kami</p>
      <h2 class="text-3xl font-extrabold text-white">Solusi Seluler Lengkap</h2>
      <p class="text-slate-500 mt-2 max-w-md mx-auto text-sm">Semua kebutuhan seluler Anda tersedia di Sava Cell</p>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <?php
      $services = [
        ['icon'=>'fa-sim-card',        'title'=>'Pulsa & Token',    'desc'=>'Semua operator tersedia, proses cepat dan langsung aktif'],
        ['icon'=>'fa-wifi',             'title'=>'Paket Data',       'desc'=>'Pilihan paket data murah dari berbagai operator'],
        ['icon'=>'fa-headphones',       'title'=>'Aksesoris HP',     'desc'=>'Case, kabel, charger, earphone, dan aksesoris lengkap'],
        ['icon'=>'fa-ticket-alt',       'title'=>'Voucher Game',     'desc'=>'Voucher game, Netflix, Spotify, dan layanan digital'],
        ['icon'=>'fa-tools',            'title'=>'Servis HP',        'desc'=>'Perbaikan layar, baterai, dan komponen HP terpercaya'],
        ['icon'=>'fa-mobile-alt',       'title'=>'Jual Beli HP',     'desc'=>'Beli HP baru & bekas dengan harga transparan'],
        ['icon'=>'fa-star',             'title'=>'Loyalty Member',   'desc'=>'Kumpulkan poin setiap transaksi, tukarkan hadiah menarik'],
        ['icon'=>'fa-map-marker-alt',   'title'=>'Lokasi Strategis', 'desc'=>'Mudah dijangkau, parkir luas, pelayanan ramah'],
      ];
      foreach ($services as $s): ?>
      <div class="glass-card rounded-2xl p-5 group">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-3 transition-all group-hover:scale-110" style="background: linear-gradient(135deg, rgba(0,196,245,0.15), rgba(0,119,170,0.1)); border: 1px solid rgba(0,196,245,0.2);">
          <i class="fas <?= $s['icon'] ?> text-sm" style="color:#00c4f5;"></i>
        </div>
        <h3 class="font-bold text-white text-sm mb-1.5"><?= $s['title'] ?></h3>
        <p class="text-xs text-slate-500 leading-relaxed"><?= $s['desc'] ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ========== HARGA PULSA ========== -->
<section id="harga-pulsa" class="py-20 px-6" style="background: var(--dark);">
  <div class="max-w-6xl mx-auto">
    <div class="text-center mb-10">
      <p class="text-xs font-semibold tracking-widest uppercase mb-2" style="color:#00c4f5;">Update Real-Time</p>
      <h2 class="text-3xl font-extrabold text-white">Harga Pulsa Hari Ini</h2>
      <p class="text-slate-500 mt-2 text-sm">Harga langsung dari sistem kasir — selalu akurat</p>
    </div>

    <?php if (!empty($pulsaList)): ?>
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
      <?php foreach ($pulsaList as $p):
        $ops = ['Telkomsel'=>'#e63946','Indosat'=>'#f59e0b','XL'=>'#3b82f6','Axis'=>'#a855f7','Tri'=>'#10b981','Smartfren'=>'#ef4444'];
        $opColor = '#00c4f5'; $opName = 'Pulsa';
        foreach ($ops as $op => $color) { if (stripos($p['nama_produk'], $op) !== false) { $opColor = $color; $opName = $op; break; } }
        $needPhone = in_array($p['kategori'], ['pulsa','paket data','voucher']) ? 'true' : 'false';
      ?>
      <div class="glass-card shop-card rounded-2xl overflow-hidden flex flex-col"
        onclick="addToCart(this)"
        data-id="<?= $p['id_menu'] ?>"
        data-nama="<?= e($p['nama_produk']) ?>"
        data-harga="<?= (int)$p['harga_jual'] ?>"
        data-kat="<?= e($p['kategori']) ?>"
        data-phone="<?= $needPhone ?>"
        data-stok="<?= (int)$p['stok'] ?>">
        <!-- Foto -->
        <?php if (!empty($p['foto_produk']) && file_exists($UPLOAD_DIR . $p['foto_produk'])): ?>
        <div class="h-24 overflow-hidden pointer-events-none">
          <img src="<?= $UPLOAD_URL . e($p['foto_produk']) ?>" alt="<?= e($p['nama_produk']) ?>" class="w-full h-full object-cover">
        </div>
        <?php else: ?>
        <div class="h-24 flex items-center justify-center pointer-events-none" style="background:rgba(255,255,255,0.04);">
          <i class="fas fa-sim-card text-2xl" style="color:rgba(0,196,245,0.4);"></i>
        </div>
        <?php endif; ?>
        <div class="p-3 flex flex-col flex-1 pointer-events-none">
          <span class="text-xs font-semibold px-2 py-0.5 rounded-full mb-2 self-start" style="background:<?= $opColor ?>22;color:<?= $opColor ?>;border:1px solid <?= $opColor ?>44;"><?= $opName ?></span>
          <p class="text-white text-xs font-semibold flex-1 leading-snug"><?= e($p['nama_produk']) ?></p>
          <div class="mt-3 price-badge text-white font-extrabold text-sm px-3 py-1.5 rounded-xl text-center"><?= rupiah($p['harga_jual']) ?></div>
        </div>
        <!-- Tombol + hover -->
        <div class="add-badge pointer-events-none">
          <svg width="14" height="14" fill="none" stroke="white" stroke-width="3" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="text-center py-12 text-slate-600">
      <i class="fas fa-sim-card text-4xl mb-3 block opacity-20"></i>
      <p>Harga pulsa belum tersedia. Silakan hubungi kami untuk info terkini.</p>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- ========== HARGA PAKET DATA ========== -->
<section id="harga-data" class="py-20 px-6" style="background: var(--dark2);">
  <div class="max-w-6xl mx-auto">
    <div class="text-center mb-10">
      <p class="text-xs font-semibold tracking-widest uppercase mb-2" style="color:#00c4f5;">Pilihan Terlengkap</p>
      <h2 class="text-3xl font-extrabold text-white">Paket Data Internet</h2>
      <p class="text-slate-500 mt-2 text-sm">Paket data semua operator dengan harga kompetitif</p>
    </div>
    <?php if (!empty($paketList)): ?>
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
      <?php foreach ($paketList as $p):
        $ops = ['Telkomsel'=>'#e63946','Indosat'=>'#f59e0b','XL'=>'#3b82f6','Axis'=>'#a855f7','Tri'=>'#10b981','Smartfren'=>'#ef4444'];
        $opColor = '#00c4f5'; $opName = 'Data';
        foreach ($ops as $op => $color) { if (stripos($p['nama_produk'], $op) !== false) { $opColor = $color; $opName = $op; break; } }
        $needPhone = 'true';
      ?>
      <div class="glass-card shop-card rounded-2xl overflow-hidden flex flex-col"
        onclick="addToCart(this)"
        data-id="<?= $p['id_menu'] ?>"
        data-nama="<?= e($p['nama_produk']) ?>"
        data-harga="<?= (int)$p['harga_jual'] ?>"
        data-kat="<?= e($p['kategori']) ?>"
        data-phone="<?= $needPhone ?>"
        data-stok="<?= (int)$p['stok'] ?>">
        <?php if (!empty($p['foto_produk']) && file_exists($UPLOAD_DIR . $p['foto_produk'])): ?>
        <div class="h-24 overflow-hidden pointer-events-none">
          <img src="<?= $UPLOAD_URL . e($p['foto_produk']) ?>" alt="<?= e($p['nama_produk']) ?>" class="w-full h-full object-cover">
        </div>
        <?php else: ?>
        <div class="h-24 flex items-center justify-center pointer-events-none" style="background:rgba(255,255,255,0.04);">
          <i class="fas fa-wifi text-2xl" style="color:rgba(0,196,245,0.4);"></i>
        </div>
        <?php endif; ?>
        <div class="p-3 flex flex-col flex-1 pointer-events-none">
          <span class="text-xs font-semibold px-2 py-0.5 rounded-full mb-2 self-start" style="background:<?= $opColor ?>22;color:<?= $opColor ?>;border:1px solid <?= $opColor ?>44;"><?= $opName ?></span>
          <p class="text-white text-xs font-semibold flex-1 leading-snug"><?= e($p['nama_produk']) ?></p>
          <div class="mt-3 price-badge text-white font-extrabold text-sm px-3 py-1.5 rounded-xl text-center"><?= rupiah($p['harga_jual']) ?></div>
        </div>
        <div class="add-badge pointer-events-none">
          <svg width="14" height="14" fill="none" stroke="white" stroke-width="3" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="text-center py-12 text-slate-600"><i class="fas fa-wifi text-4xl mb-3 block opacity-20"></i><p>Daftar paket data belum tersedia.</p></div>
    <?php endif; ?>
  </div>
</section>

<!-- ========== AKSESORIS ========== -->
<?php if (!empty($aksList)): ?>
<section id="aksesoris" class="py-20 px-6" style="background: var(--dark);">
  <div class="max-w-6xl mx-auto">
    <div class="text-center mb-10">
      <p class="text-xs font-semibold tracking-widest uppercase mb-2" style="color:#00c4f5;">Tersedia Lengkap</p>
      <h2 class="text-3xl font-extrabold text-white">Aksesoris HP</h2>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
      <?php foreach ($aksList as $p): ?>
      <div class="glass-card shop-card rounded-2xl p-4 flex items-center gap-4"
        onclick="addToCart(this)"
        data-id="<?= $p['id_menu'] ?>"
        data-nama="<?= e($p['nama_produk']) ?>"
        data-harga="<?= (int)$p['harga_jual'] ?>"
        data-kat="<?= e($p['kategori']) ?>"
        data-phone="false"
        data-stok="<?= (int)$p['stok'] ?>">
        <div class="w-14 h-14 rounded-xl overflow-hidden flex-shrink-0 flex items-center justify-center pointer-events-none" style="background:linear-gradient(135deg,rgba(0,196,245,0.15),rgba(0,119,170,0.1));">
          <?php if (!empty($p['foto_produk']) && file_exists($UPLOAD_DIR . $p['foto_produk'])): ?>
          <img src="<?= $UPLOAD_URL . e($p['foto_produk']) ?>" alt="<?= e($p['nama_produk']) ?>" class="w-full h-full object-cover rounded-xl">
          <?php else: ?>
          <i class="fas fa-headphones" style="color:#00c4f5;"></i>
          <?php endif; ?>
        </div>
        <div class="flex-1 min-w-0 pointer-events-none">
          <p class="text-white text-sm font-semibold leading-tight"><?= e($p['nama_produk']) ?></p>
          <p class="font-extrabold mt-1 text-sm" style="color:#00c4f5;"><?= rupiah($p['harga_jual']) ?></p>
        </div>
        <div class="add-badge pointer-events-none" style="position:relative;bottom:auto;right:auto;opacity:0;transform:scale(0.6);flex-shrink:0;">
          <svg width="14" height="14" fill="none" stroke="white" stroke-width="3" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ========== STATS SECTION ========== -->
<section class="py-16 px-6" style="background: linear-gradient(135deg, rgba(0,196,245,0.08), rgba(0,119,170,0.05)), var(--dark2);">
  <div class="max-w-4xl mx-auto grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
    <?php
    $stats = [
      ['num'=>'5+',   'label'=>'Tahun Berpengalaman', 'icon'=>'fa-award'],
      ['num'=>'10K+', 'label'=>'Pelanggan Puas',       'icon'=>'fa-users'],
      ['num'=>'All',  'label'=>'Operator Tersedia',    'icon'=>'fa-sim-card'],
      ['num'=>'Fast', 'label'=>'Proses Instan',         'icon'=>'fa-bolt'],
    ];
    foreach ($stats as $s): ?>
    <div>
      <div class="w-12 h-12 rounded-2xl flex items-center justify-center mx-auto mb-3" style="background: linear-gradient(135deg,rgba(0,196,245,0.15),rgba(0,119,170,0.1)); border:1px solid rgba(0,196,245,0.2);">
        <i class="fas <?= $s['icon'] ?>" style="color:#00c4f5;"></i>
      </div>
      <div class="text-2xl font-extrabold text-white"><?= $s['num'] ?></div>
      <div class="text-xs text-slate-500 mt-1"><?= $s['label'] ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ========== LOKASI / MAP ========== -->
<section id="lokasi" class="py-20 px-6" style="background: var(--dark);">
  <div class="max-w-6xl mx-auto grid md:grid-cols-2 gap-10 items-center">
    <div>
      <p class="text-xs font-semibold tracking-widest uppercase mb-2" style="color:#00c4f5;">Kunjungi Kami</p>
      <h2 class="text-3xl font-extrabold text-white mb-4">Lokasi Sava Cell</h2>
      <div class="space-y-4">
        <div class="flex items-start gap-4">
          <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0" style="background: rgba(0,196,245,0.12); border: 1px solid rgba(0,196,245,0.2);">
            <i class="fas fa-map-marker-alt text-sm" style="color:#00c4f5;"></i>
          </div>
          <div>
            <p class="text-white font-semibold text-sm">Alamat</p>
            <p class="text-slate-400 text-sm">gg kades, RT.14/RW.04, Tanjungtani, Kec. Prambon, Kabupaten Nganjuk<br>Jawa Timur 64484</p>
          </div>
        </div>
        <div class="flex items-start gap-4">
          <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0" style="background: rgba(0,196,245,0.12); border: 1px solid rgba(0,196,245,0.2);">
            <i class="fas fa-clock text-sm" style="color:#00c4f5;"></i>
          </div>
          <div>
            <p class="text-white font-semibold text-sm">Jam Operasional</p>
            <p class="text-slate-400 text-sm">Senin – Minggu: 06.00 – 21.00</p>
          </div>
        </div>
        <div class="flex items-start gap-4">
          <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0" style="background: rgba(0,196,245,0.12); border: 1px solid rgba(0,196,245,0.2);">
            <i class="fab fa-whatsapp text-sm" style="color:#25d366;"></i>
          </div>
          <div>
            <p class="text-white font-semibold text-sm">WhatsApp</p>
            <a href="https://wa.me/6285736278191" class="text-sm" style="color:#00c4f5;">+62 857-3627-8191</a>
          </div>
        </div>
      </div>
    </div>
    <!-- Map Embed Placeholder -->
    <div class="rounded-2xl overflow-hidden" style="border: 1px solid rgba(0,196,245,0.2); height: 300px;">
      <!-- Replace src with actual Google Maps embed URL -->
<iframe
    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3953.5350328731364!2d112.0251327!3d-7.7176546!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e7851089d9a1fa1%3A0xf290b48391d2c28b!2sSava%20cell!5e0!3m2!1sid!2sid!4v1717750000000!5m2!1sid!2sid"
    width="100%" 
    height="100%" 
    style="border:0; filter:invert(90%) hue-rotate(180deg) brightness(0.8) contrast(1.1);"
    allowfullscreen="" 
    loading="lazy"
    referrerpolicy="no-referrer-when-downgrade">
  </iframe>
    </div>
  </div>
</section>

<!-- ========== CTA ========== -->
<section class="py-16 px-6" style="background: linear-gradient(135deg, rgba(0,196,245,0.12), rgba(0,119,170,0.08)), var(--dark2);">
  <div class="max-w-2xl mx-auto text-center">
    <h2 class="text-2xl font-extrabold text-white mb-3">Butuh Bantuan?</h2>
    <p class="text-slate-400 text-sm mb-6">Hubungi kami via WhatsApp untuk informasi harga, ketersediaan stok, atau layanan lainnya.</p>
    <a href="https://wa.me/6285736278191" class="inline-flex items-center gap-2.5 px-6 py-3 rounded-xl font-bold text-white transition-all hover:scale-105" style="background: linear-gradient(135deg,#25d366,#128c7e); box-shadow: 0 6px 24px rgba(37,211,102,0.3);">
      <i class="fab fa-whatsapp text-lg"></i> Chat via WhatsApp
    </a>
  </div>
</section>

<!-- ========== FOOTER ========== -->
<footer class="py-8 px-6 border-t" style="border-color: rgba(255,255,255,0.06); background: var(--dark);">
  <div class="max-w-6xl mx-auto flex flex-col md:flex-row items-center justify-between gap-4">
    <div class="flex items-center gap-2">
      <div class="w-6 h-6 rounded-md flex items-center justify-center" style="background: linear-gradient(135deg,#00c4f5,#0077aa);">
        <i class="fas fa-mobile-alt text-white" style="font-size: 9px;"></i>
      </div>
      <span class="font-extrabold text-white text-sm">Sava<span style="color:#00c4f5;">Cell</span></span>
    </div>
    <p class="text-xs text-slate-600">&copy; <?= date('Y') ?> Sava Cell. Hak Cipta Dilindungi.</p>
    <a href="/admin/login.php" class="text-xs text-slate-600 hover:text-slate-400 transition-colors">
      <i class="fas fa-lock mr-1"></i> Admin Panel
    </a>
  </div>
</footer>

<script>
// ================================================================
//  SAVA CELL — Shopping Cart Engine (Shopee-style)
// ================================================================

const WA_NUMBER = '6285736278191';

// cart = { id: { nama, harga, qty, phone, needPhone } }
let cart = {};

const fmt = n => 'Rp ' + Number(n).toLocaleString('id-ID');

// ── Hitung total item & harga ────────────────────────────────
function cartCount()  { return Object.values(cart).reduce((s,i) => s + i.qty, 0); }
function cartTotal()  { return Object.values(cart).reduce((s,i) => s + i.harga * i.qty, 0); }

// ── Update badge FAB ─────────────────────────────────────────
function updateBadge() {
  const n   = cartCount();
  const bdg = document.getElementById('cartBadge');
  bdg.textContent = n;
  n > 0 ? bdg.classList.add('show') : bdg.classList.remove('show');
  document.getElementById('drawerCount').textContent = n + ' item';
  document.getElementById('cartTotal').textContent   = fmt(cartTotal());
  const btn = document.getElementById('checkoutBtn');
  btn.disabled = n === 0;
  btn.classList.toggle('opacity-40',     n === 0);
  btn.classList.toggle('cursor-not-allowed', n === 0);
}

// ── Animasi titik terbang ke FAB (seperti Shopee) ────────────
function flyToCart(originEl) {
  const fab    = document.getElementById('cartFab');
  const srcR   = originEl.getBoundingClientRect();
  const fabR   = fab.getBoundingClientRect();
  const dot    = document.createElement('div');
  dot.className = 'fly-dot';
  dot.style.cssText = `left:${srcR.left + srcR.width/2 - 6}px;top:${srcR.top + srcR.height/2 - 6}px;transition:none;`;
  document.body.appendChild(dot);

  requestAnimationFrame(() => {
    dot.style.transition = 'left .55s cubic-bezier(.4,0,.6,1), top .5s cubic-bezier(.4,0,.6,1), transform .55s, opacity .4s';
    dot.style.left    = (fabR.left + fabR.width/2  - 6) + 'px';
    dot.style.top     = (fabR.top  + fabR.height/2 - 6) + 'px';
    dot.style.transform = 'scale(0.3)';
    dot.style.opacity   = '0';
  });
  setTimeout(() => dot.remove(), 600);
}

// ── Toast notifikasi ─────────────────────────────────────────
function toast(msg, type = 'ok') {
  const el = document.createElement('div');
  el.style.cssText = `
    position:fixed;bottom:90px;left:50%;transform:translateX(-50%);
    z-index:9999;padding:10px 20px;border-radius:50px;font-size:13px;
    font-weight:600;white-space:nowrap;pointer-events:none;
    transition:opacity .3s;
    ${type === 'ok'
      ? 'background:rgba(0,196,245,0.15);border:1px solid rgba(0,196,245,0.4);color:#00c4f5;'
      : 'background:rgba(239,68,68,0.15);border:1px solid rgba(239,68,68,0.4);color:#f87171;'}
  `;
  el.textContent = msg;
  document.body.appendChild(el);
  setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }, 1800);
}

// ── Tambah ke keranjang ──────────────────────────────────────
function addToCart(el) {
  const id       = el.dataset.id;
  const stok     = parseInt(el.dataset.stok) || 0;
  const needPhone = el.dataset.phone === 'true';

  if (stok === 0) { toast('Stok habis!', 'err'); return; }

  if (cart[id]) {
    if (cart[id].qty >= stok) { toast('Stok tidak cukup!', 'err'); return; }
    cart[id].qty++;
  } else {
    cart[id] = {
      nama      : el.dataset.nama,
      harga     : parseInt(el.dataset.harga),
      qty       : 1,
      needPhone : needPhone,
      phone     : ''
    };
  }

  flyToCart(el);
  toast('+ ' + el.dataset.nama.substring(0, 28));
  renderDrawer();
  updateBadge();
}

// ── Render isi drawer ────────────────────────────────────────
function renderDrawer() {
  const container = document.getElementById('cartItems');
  const empty     = document.getElementById('cartEmpty');
  const ids       = Object.keys(cart);

  if (ids.length === 0) {
    container.querySelectorAll('.ci-row').forEach(e => e.remove());
    empty.style.display = '';
    return;
  }
  empty.style.display = 'none';

  ids.forEach(id => {
    const it  = cart[id];
    let   row = document.getElementById('ci-' + id);

    if (!row) {
      row           = document.createElement('div');
      row.id        = 'ci-' + id;
      row.className = 'ci-row';
      row.dataset.id = id;
      row.style.cssText = 'padding:12px 16px;border-bottom:1px solid rgba(255,255,255,0.05);';
      row.innerHTML = `
        <div style="display:flex;align-items:flex-start;gap:8px;margin-bottom:6px;">
          <span class="ci-nama" style="color:#f1f5f9;font-size:12px;font-weight:600;flex:1;line-height:1.4;"></span>
          <button class="ci-del" style="background:none;border:none;color:#f87171;cursor:pointer;font-size:12px;padding:0;flex-shrink:0;">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
          <div style="display:flex;align-items:center;gap:6px;">
            <button class="qty-btn ci-minus">−</button>
            <span class="ci-qty" style="color:#fff;font-size:13px;font-weight:700;min-width:20px;text-align:center;"></span>
            <button class="qty-btn ci-plus">+</button>
          </div>
          <span class="ci-sub" style="color:#00c4f5;font-size:13px;font-weight:700;"></span>
        </div>
        ${it.needPhone ? `<input class="ci-phone ph-input" placeholder="Nomor tujuan (08xx...)" style="margin-top:8px;font-size:12px;padding:8px 12px;">` : ''}
      `;
      container.insertBefore(row, empty);

      // Events — pakai closure agar id selalu benar
      const myId = id;
      row.querySelector('.ci-del').addEventListener('click', () => removeItem(myId));
      row.querySelector('.ci-minus').addEventListener('click', () => changeQty(myId, -1));
      row.querySelector('.ci-plus').addEventListener('click',  () => changeQty(myId, +1));
      const ph = row.querySelector('.ci-phone');
      if (ph) ph.addEventListener('input', function() { if(cart[myId]) cart[myId].phone = this.value; });
    }

    // Update nilai
    row.querySelector('.ci-nama').textContent = it.nama;
    row.querySelector('.ci-qty').textContent  = it.qty;
    row.querySelector('.ci-sub').textContent  = fmt(it.harga * it.qty);
    // Jaga nilai phone input agar tidak reset
    const ph = row.querySelector('.ci-phone');
    if (ph && ph.value === '' && it.phone) ph.value = it.phone;
  });
}

function removeItem(id) {
  delete cart[id];
  const row = document.getElementById('ci-' + id);
  if (row) row.remove();
  if (Object.keys(cart).length === 0) document.getElementById('cartEmpty').style.display = '';
  updateBadge();
}

function changeQty(id, d) {
  if (!cart[id]) return;
  cart[id].qty += d;
  if (cart[id].qty <= 0) { removeItem(id); return; }
  renderDrawer();
  updateBadge();
}

function clearCart() {
  cart = {};
  document.querySelectorAll('.ci-row').forEach(e => e.remove());
  document.getElementById('cartEmpty').style.display = '';
  updateBadge();
}

// ── Buka / tutup drawer ──────────────────────────────────────
function openCart() {
  document.getElementById('cartDrawer').classList.add('open');
  document.getElementById('cartOverlay').classList.add('show');
  document.body.style.overflow = 'hidden';
}
function closeCart() {
  document.getElementById('cartDrawer').classList.remove('open');
  document.getElementById('cartOverlay').classList.remove('show');
  document.body.style.overflow = '';
}

// ── Checkout modal ───────────────────────────────────────────
function openCheckout() {
  if (cartCount() === 0) return;

  // Ambil nomor tujuan dari input DOM
  document.querySelectorAll('.ci-phone').forEach(inp => {
    const id = inp.closest('.ci-row')?.dataset.id;
    if (id && cart[id]) cart[id].phone = inp.value;
  });

  // Render order summary
  const summary = Object.values(cart).map(it => `
    <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;border-bottom:1px solid rgba(255,255,255,0.05);">
      <div>
        <div style="color:#f1f5f9;font-size:13px;font-weight:600;">${it.nama}</div>
        ${it.needPhone && it.phone ? `<div style="color:#64748b;font-size:11px;margin-top:2px;">→ ${it.phone}</div>` : ''}
      </div>
      <div style="text-align:right;flex-shrink:0;margin-left:12px;">
        <div style="color:#94a3b8;font-size:11px;">${it.qty}x ${fmt(it.harga)}</div>
        <div style="color:#00c4f5;font-weight:700;font-size:13px;">${fmt(it.harga * it.qty)}</div>
      </div>
    </div>
  `).join('');
  document.getElementById('orderSummary').innerHTML = summary;

  // Phone fields untuk item yang belum diisi
  const phoneFields = Object.entries(cart)
    .filter(([,it]) => it.needPhone)
    .map(([id,it]) => `
      <div>
        <label style="display:block;font-size:11px;font-weight:600;color:#94a3b8;margin-bottom:5px;">${it.nama}</label>
        <input class="ph-input modal-phone" data-id="${id}" value="${it.phone || ''}"
          placeholder="Nomor tujuan (08xxxxxxxxxx)"
          oninput="if(cart['${id}']) cart['${id}'].phone=this.value">
      </div>`).join('');
  document.getElementById('phoneFields').innerHTML = phoneFields || '';

  document.getElementById('modalTotal').textContent = fmt(cartTotal());
  document.getElementById('checkoutModal').classList.add('show');
  closeCart();
}
function closeCheckout() {
  document.getElementById('checkoutModal').classList.remove('show');
}

// ── Kirim ke WhatsApp ────────────────────────────────────────
function sendWhatsApp() {
  const nama = document.getElementById('custName').value.trim();
  if (!nama) { toast('Isi nama pemesan dulu!', 'err'); document.getElementById('custName').focus(); return; }

  // Validasi nomor tujuan wajib diisi untuk produk digital
  for (const [id, it] of Object.entries(cart)) {
    if (it.needPhone && !it.phone.trim()) {
      toast(`Isi nomor tujuan untuk: ${it.nama.substring(0,25)}`, 'err');
      document.querySelector(`.modal-phone[data-id="${id}"]`)?.focus();
      return;
    }
  }

  let msg  = `*Pesanan Sava Cell* 📱\n`;
  msg     += `Nama: *${nama}*\n\n`;
  msg     += `*Detail Pesanan:*\n`;

  Object.values(cart).forEach((it, i) => {
    msg += `${i+1}. ${it.nama}\n`;
    msg += `   Jumlah: ${it.qty}x — ${fmt(it.harga * it.qty)}\n`;
    if (it.needPhone && it.phone) msg += `   No. Tujuan: *${it.phone}*\n`;
  });

  msg += `\n*Total: ${fmt(cartTotal())}*\n`;
  msg += `\nMohon segera dikonfirmasi, terima kasih! 🙏`;

  window.open(`https://wa.me/${WA_NUMBER}?text=${encodeURIComponent(msg)}`, '_blank');
  closeCheckout();
  clearCart();
}

// ── Navbar scroll ────────────────────────────────────────────
window.addEventListener('scroll', () => {
  document.getElementById('navbar').classList.toggle('scrolled', window.scrollY > 50);
});
</script>
</body>
</html>
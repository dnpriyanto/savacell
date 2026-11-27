<?php
// ============================================================
//  admin/pages/transaksi.php — POS Cashier (Phase 3: View 5)
// ============================================================
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
requireLogin();
$pageTitle = 'Kasir / POS';

// ---- AJAX: Process Transaction ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_checkout'])) {
    header('Content-Type: application/json');
    $user     = currentUser();
    $items    = json_decode($_POST['items'] ?? '[]', true);
    $pelId    = (int)($_POST['id_pelanggan'] ?? 0) ?: null;
    $bayar    = (float)($_POST['bayar'] ?? 0);
    $total    = 0;

    if (empty($items)) { echo json_encode(['ok'=>false,'msg'=>'Keranjang kosong']); exit; }

    // Validate items & calc total
    $validated = [];
    $allMenu = csvReadAll('menu');
    $menuLookup = [];
    foreach ($allMenu as $m) $menuLookup[$m['id_menu']] = $m;

    foreach ($items as $it) {
        $prod = $menuLookup[(int)$it['id_menu']] ?? null;
        if (!$prod || !$prod['is_active']) { echo json_encode(['ok'=>false,'msg'=>"Produk tidak ditemukan/tidak aktif"]); exit; }
        $qty      = max(1, (int)$it['jumlah']);
        $subtotal = $prod['harga_jual'] * $qty;
        $total   += $subtotal;
        $validated[] = [
            'id_menu'      => $prod['id_menu'],
            'nama'         => $prod['nama_produk'],
            'kategori'     => $prod['kategori'],
            'qty'          => $qty,
            'harga'        => $prod['harga_jual'],
            'subtotal'     => $subtotal,
            'nomor_tujuan' => trim($it['nomor_tujuan'] ?? ''),
            'stok'         => $prod['stok'],
        ];
    }

    if ($bayar < $total) { echo json_encode(['ok'=>false,'msg'=>'Uang bayar kurang dari total']); exit; }

    try {
        // Insert transaksi
        $trxId = csvInsert('transaksi', [
            'id_pelanggan' => $pelId ?? '',
            'id_pegawai' => $user['id'],
            'total_bayar' => $total,
            'bayar' => $bayar,
            'kembalian' => $bayar - $total,
            'status' => 'selesai',
            'tanggal_transaksi' => date('Y-m-d H:i:s'),
        ]);

        foreach ($validated as $it) {
            // Insert detail
            csvInsert('detail_transaksi', [
                'id_transaksi' => $trxId,
                'id_menu' => $it['id_menu'],
                'jumlah' => $it['qty'],
                'harga_satuan' => $it['harga'],
                'subtotal' => $it['subtotal'],
                'nomor_tujuan' => $it['nomor_tujuan'],
            ]);
            
            // Deduct stock & log
            $oldStok = (int)$it['stok'];
            $newStok = $oldStok - $it['qty'];
            csvUpdate('menu', 'id_menu', $it['id_menu'], ['stok' => $newStok]);
            logStockChange($it['id_menu'], $it['nama'], $oldStok, $newStok, 'Penjualan #' . $trxId);
        }

        // Add loyalty points (1 poin per Rp 1000)
        if ($pelId) {
            $pel = csvFindOne('pelanggan', 'id_pelanggan', $pelId);
            if ($pel) {
                $poin = (int)floor($total / 1000);
                csvUpdate('pelanggan', 'id_pelanggan', $pelId, [
                    'poin' => ((int)($pel['poin'] ?? 0)) + $poin,
                ]);
            }
        }

        echo json_encode(['ok'=>true,'trx_id'=>$trxId,'total'=>$total,'kembalian'=>$bayar-$total]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>'Error: '.$e->getMessage()]);
    }
    exit;
}

// ---- AJAX: Search customer ----
if (isset($_GET['search_pelanggan'])) {
    header('Content-Type: application/json');
    $q = trim($_GET['q'] ?? '');
    $pelanggan = csvReadAll('pelanggan');
    $results = array_filter($pelanggan, fn($p) => 
        stripos($p['nama_pelanggan'] ?? '', $q) !== false || 
        stripos($p['no_hp'] ?? '', $q) !== false
    );
    echo json_encode(array_values(array_slice($results, 0, 8)));
    exit;
}

// Products for POS
$products = csvReadAll('menu');
$products = array_filter($products, fn($p) => ($p['is_active'] ?? 0) == 1);
$products = array_values(csvSort($products, 'nama_produk', 'ASC'));

require_once __DIR__ . '/../includes/header.php';
?>
<style>
.pos-product { cursor: pointer; transition: all .15s; }
.pos-product:hover { border-color: #00c4f5 !important; background: rgba(0,196,245,0.06) !important; transform: scale(1.02); }
.pos-product.selected { border-color: #00c4f5 !important; background: rgba(0,196,245,0.1) !important; }
.cart-item { border-bottom: 1px solid rgba(255,255,255,0.05); }
.quick-pay-btn {
  padding: 4px 0; border-radius: 8px; font-size: 11px; font-weight: 600;
  cursor: pointer; transition: all .15s; width: 100%;
  background: rgba(0,196,245,0.07);
  border: 1px solid rgba(0,196,245,0.2);
  color: #00c4f5;
}
.quick-pay-btn:hover { background: rgba(0,196,245,0.18); border-color: #00c4f5; }
</style>

<div class="flex gap-4 h-[calc(100vh-120px)]">

  <!-- === LEFT: Product Catalog === -->
  <div class="flex-1 flex flex-col overflow-hidden">

    <!-- Search + Category Filter -->
    <div class="flex gap-2 mb-3">
      <div class="relative flex-1">
        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
        <input type="text" id="prodSearch" placeholder="Cari produk..." class="w-full pl-8 pr-3 py-2 rounded-xl text-xs">
      </div>
    </div>
    <div class="flex gap-1.5 mb-3 flex-wrap" id="katFilter">
      <button data-kat="" class="kat-btn active px-3 py-1 rounded-full text-xs font-medium transition-all text-dark-900 bg-cyan-400">Semua</button>
      <?php foreach (['pulsa','paket data','aksesoris','voucher'] as $k): ?>
      <button data-kat="<?= $k ?>" class="kat-btn px-3 py-1 rounded-full text-xs font-medium transition-all text-slate-400 border border-white/10 hover:text-white"><?= ucwords($k) ?></button>
      <?php endforeach; ?>
    </div>

    <!-- Product Grid -->
    <div id="productGrid" class="flex-1 overflow-y-auto grid grid-cols-2 xl:grid-cols-3 gap-2 pr-1">
      <?php foreach ($products as $p): ?>
      <div class="pos-product card-glass rounded-xl p-3 border border-transparent"
        data-id="<?= $p['id_menu'] ?>"
        data-nama="<?= e($p['nama_produk']) ?>"
        data-harga="<?= $p['harga_jual'] ?>"
        data-kat="<?= $p['kategori'] ?>"
        data-stok="<?= $p['stok'] ?>"
        onclick="addToCart(this)">
        <?php $icons = ['pulsa'=>'fa-sim-card','paket data'=>'fa-wifi','aksesoris'=>'fa-headphones','voucher'=>'fa-ticket-alt']; ?>
        <div class="text-center mb-1.5">
          <i class="fas <?= $icons[$p['kategori']] ?? 'fa-box' ?> text-xl" style="color: rgba(0,196,245,0.5);"></i>
        </div>
        <p class="text-white text-xs font-semibold leading-tight text-center mb-1"><?= e($p['nama_produk']) ?></p>
        <p class="text-center font-bold text-xs" style="color: #00c4f5;"><?= rupiah($p['harga_jual']) ?></p>
        <p class="text-center text-xs text-slate-600 mt-0.5">Stok: <?= $p['stok'] ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- === RIGHT: Cart & Checkout === -->
  <div class="w-72 flex flex-col card-glass rounded-2xl overflow-hidden">

    <!-- Cart Header -->
    <div class="px-4 py-3 border-b flex items-center justify-between" style="border-color: rgba(255,255,255,0.06);">
      <h3 class="font-bold text-white text-sm flex items-center gap-2">
        <i class="fas fa-shopping-cart" style="color:#00c4f5;"></i> Keranjang
      </h3>
      <button onclick="clearCart()" class="text-xs text-red-400 hover:text-red-300">Kosongkan</button>
    </div>

    <!-- Customer Select -->
    <div class="px-4 py-3 border-b" style="border-color: rgba(255,255,255,0.06);">
      <label class="text-xs text-slate-500 block mb-1">Pelanggan (opsional)</label>
      <div class="relative">
        <input type="text" id="pelInput" placeholder="Cari nama / no HP..." autocomplete="off"
          class="w-full px-3 py-2 rounded-xl text-xs">
        <div id="pelDropdown" class="hidden absolute z-20 top-full left-0 right-0 mt-1 rounded-xl overflow-hidden shadow-xl"
          style="background: #0d1520; border: 1px solid rgba(0,196,245,0.2);">
        </div>
      </div>
      <div id="pelSelected" class="hidden mt-2 px-3 py-2 rounded-xl flex items-center justify-between" style="background: rgba(0,196,245,0.08); border: 1px solid rgba(0,196,245,0.2);">
        <div>
          <div id="pelName" class="text-xs font-semibold text-white"></div>
          <div id="pelPoin" class="text-xs text-amber-400"></div>
        </div>
        <button onclick="clearPelanggan()" class="text-slate-500 hover:text-red-400 text-xs"><i class="fas fa-times"></i></button>
      </div>
    </div>
    <input type="hidden" id="selectedPelId" value="">

    <!-- Cart Items -->
    <div id="cartItems" class="flex-1 overflow-y-auto py-2">
      <div id="emptyCart" class="text-center py-8 text-slate-600 text-xs">
        <i class="fas fa-shopping-cart text-2xl mb-2 block opacity-20"></i>
        Belum ada item
      </div>
    </div>

    <!-- Total & Checkout -->
    <div class="border-t p-4 space-y-3" style="border-color: rgba(255,255,255,0.06);">
      <div class="flex justify-between items-center">
        <span class="text-xs text-slate-400">Total</span>
        <span id="totalText" class="font-bold text-white">Rp 0</span>
      </div>
      <div>
        <label class="text-xs text-slate-500 block mb-1">Uang Bayar</label>
        <input type="text" id="bayarInput" placeholder="0" inputmode="numeric"
          class="w-full px-3 py-2 rounded-xl text-sm font-bold"
          oninput="formatBayar(this)">
        <input type="hidden" id="bayarRaw" value="0">
        <!-- Tombol nominal cepat -->
        <div class="grid grid-cols-3 gap-1 mt-2" id="quickPay">
          <button onclick="setBayar(5000)"    class="quick-pay-btn">5.000</button>
          <button onclick="setBayar(10000)"   class="quick-pay-btn">10.000</button>
          <button onclick="setBayar(20000)"   class="quick-pay-btn">20.000</button>
          <button onclick="setBayar(50000)"   class="quick-pay-btn">50.000</button>
          <button onclick="setBayar(100000)"  class="quick-pay-btn">100.000</button>
          <button onclick="setBayarPas()"     class="quick-pay-btn" style="color:#34d399;border-color:rgba(52,211,153,0.35);">Uang Pas</button>
        </div>
      </div>
      <div class="flex justify-between items-center px-2 py-1.5 rounded-lg" style="background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.2);">
        <span class="text-xs text-emerald-400">Kembalian</span>
        <span id="kembalianText" class="font-bold text-emerald-400 text-sm">Rp 0</span>
      </div>
      <button onclick="checkout()" id="checkoutBtn"
        class="btn-primary w-full py-3 rounded-xl text-white font-bold text-sm flex items-center justify-center gap-2 opacity-50 cursor-not-allowed" disabled>
        <i class="fas fa-check-circle"></i> Proses Pembayaran
      </button>
    </div>
  </div>
</div>

<!-- Receipt Modal -->
<div id="receiptModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm">
  <div class="w-80 rounded-2xl p-6 text-center" style="background: #0d1520; border: 1px solid rgba(0,196,245,0.2);">
    <div class="w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-3" style="background: rgba(16,185,129,0.15); border: 2px solid rgba(16,185,129,0.4);">
      <i class="fas fa-check text-emerald-400 text-xl"></i>
    </div>
    <h3 class="text-lg font-bold text-white mb-1">Transaksi Berhasil!</h3>
    <p id="rcptTrxId" class="text-xs text-slate-500 mb-4"></p>
    <div class="space-y-2 mb-5 text-sm">
      <div class="flex justify-between"><span class="text-slate-400">Total</span><span id="rcptTotal" class="font-bold text-white"></span></div>
      <div class="flex justify-between"><span class="text-slate-400">Kembalian</span><span id="rcptKembalian" class="font-bold text-emerald-400"></span></div>
    </div>
    <div class="flex gap-2">
      <button onclick="printReceipt()" class="flex-1 py-2.5 rounded-xl text-sm font-medium border" style="border-color: rgba(0,196,245,0.3); color:#00c4f5;">
        <i class="fas fa-print mr-1.5"></i> Cetak
      </button>
      <button onclick="closeReceipt()" class="btn-primary flex-1 py-2.5 rounded-xl text-white text-sm font-bold">
        Transaksi Baru
      </button>
    </div>
  </div>
</div>

<script>
// =============================================================
//  POS CART ENGINE — Fixed version
//  Bug lama:
//  1. renderCart() pakai innerHTML rebuild → event listener mati
//  2. tombol +/− di cart tidak berfungsi setelah item pertama
//  3. nomor_tujuan reset setiap kali qty berubah
//  4. klik produk kedua tidak masuk cart
//
//  Fix: DOM manipulation per-elemen (bukan rebuild innerHTML),
//       event delegation di #cartItems, state nomor_tujuan
//       disimpan di object cart dan tidak ikut di-reset.
// =============================================================

let cart = {};
// cart = { "id_menu": { nama, harga, qty, nomor_tujuan, kat } }

let totalAmount = 0;

function fmt(n) {
  return 'Rp ' + Number(n).toLocaleString('id-ID');
}

// ── Update total + tombol checkout ──────────────────────────
function updateTotal() {
  totalAmount = Object.values(cart).reduce((s, it) => s + it.harga * it.qty, 0);
  document.getElementById('totalText').textContent = fmt(totalAmount);
  calcKembalian();
  const btn = document.getElementById('checkoutBtn');
  btn.disabled = (totalAmount === 0);
  btn.classList.toggle('opacity-50', totalAmount === 0);
  btn.classList.toggle('cursor-not-allowed', totalAmount === 0);
}

// ── Render ulang satu item di DOM (buat atau update) ─────────
function renderCartItem(id) {
  const it         = cart[id];
  const needPhone  = ['pulsa','paket data','voucher'].includes(it.kat);
  const containerId = 'cart-item-' + id;

  let el = document.getElementById(containerId);

  if (!el) {
    // Buat elemen baru
    el = document.createElement('div');
    el.id        = containerId;
    el.className = 'cart-item px-3 py-2';
    el.dataset.cartId = id;
    el.innerHTML = `
      <div class="flex items-start justify-between gap-1 mb-1">
        <span class="text-white text-xs font-medium flex-1 leading-tight item-nama"></span>
        <button class="btn-remove text-red-400 hover:text-red-300 text-xs flex-shrink-0">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="flex items-center justify-between gap-2">
        <div class="flex items-center gap-1.5">
          <button class="btn-minus w-5 h-5 rounded text-xs font-bold flex items-center justify-center hover:bg-white/15 text-slate-300" style="border:1px solid rgba(255,255,255,0.15);">−</button>
          <span class="item-qty text-white text-xs w-5 text-center font-bold"></span>
          <button class="btn-plus w-5 h-5 rounded text-xs font-bold flex items-center justify-center hover:bg-white/15 text-slate-300" style="border:1px solid rgba(255,255,255,0.15);">+</button>
        </div>
        <span class="item-subtotal text-cyan-400 text-xs font-bold"></span>
      </div>
      ${needPhone ? `<input type="text" placeholder="Nomor tujuan..." class="item-phone mt-1.5 w-full px-2 py-1 rounded-lg text-xs">` : ''}
    `;
    document.getElementById('cartItems').appendChild(el);

    // Event: nomor tujuan — simpan ke cart tanpa trigger renderCart
    const phoneInput = el.querySelector('.item-phone');
    if (phoneInput) {
      phoneInput.addEventListener('input', function() {
        if (cart[id]) cart[id].nomor_tujuan = this.value;
      });
    }
  }

  // Update nilai yang bisa berubah (qty, subtotal, nama)
  el.querySelector('.item-nama').textContent    = it.nama;
  el.querySelector('.item-qty').textContent     = it.qty;
  el.querySelector('.item-subtotal').textContent = fmt(it.harga * it.qty);

  // Isi nomor tujuan hanya jika field masih kosong (tidak overwrite saat user mengetik)
  const phoneEl = el.querySelector('.item-phone');
  if (phoneEl && phoneEl.value === '' && it.nomor_tujuan) {
    phoneEl.value = it.nomor_tujuan;
  }
}

// ── Render seluruh cart ──────────────────────────────────────
function renderCart() {
  const container = document.getElementById('cartItems');
  const empty     = document.getElementById('emptyCart');
  const ids       = Object.keys(cart);

  // Hapus elemen item yang sudah tidak ada di cart
  container.querySelectorAll('[data-cart-id]').forEach(el => {
    if (!cart[el.dataset.cartId]) el.remove();
  });

  if (ids.length === 0) {
    empty.classList.remove('hidden');
    updateTotal();
    return;
  }
  empty.classList.add('hidden');

  ids.forEach(id => renderCartItem(id));
  updateTotal();
}

// ── Event delegation untuk tombol +/−/hapus di cart ─────────
// Satu listener di parent — tidak pernah mati walau innerHTML berubah
document.getElementById('cartItems').addEventListener('click', function(e) {
  const el    = e.target.closest('[data-cart-id]');
  if (!el) return;
  const id    = el.dataset.cartId;
  if (!cart[id]) return;

  if (e.target.closest('.btn-remove')) {
    delete cart[id];
    el.remove();
    if (Object.keys(cart).length === 0) {
      document.getElementById('emptyCart').classList.remove('hidden');
    }
    updateTotal();
    return;
  }
  if (e.target.closest('.btn-plus')) {
    const stok = parseInt(document.querySelector(`.pos-product[data-id="${id}"]`)?.dataset.stok ?? 9999);
    if (cart[id].qty >= stok) {
      showToast('Stok tidak cukup!', 'error');
      return;
    }
    cart[id].qty++;
    renderCartItem(id);
    updateTotal();
    return;
  }
  if (e.target.closest('.btn-minus')) {
    cart[id].qty--;
    if (cart[id].qty <= 0) {
      delete cart[id];
      el.remove();
      if (Object.keys(cart).length === 0) {
        document.getElementById('emptyCart').classList.remove('hidden');
      }
    } else {
      renderCartItem(id);
    }
    updateTotal();
    return;
  }
});

// ── Tambah produk ke cart ─────────────────────────────────────
function addToCart(el) {
  const id   = el.dataset.id;
  const stok = parseInt(el.dataset.stok ?? 9999);

  if (cart[id]) {
    if (cart[id].qty >= stok) {
      showToast('Stok habis!', 'error');
      return;
    }
    cart[id].qty++;
    renderCartItem(id);
    updateTotal();
  } else {
    cart[id] = {
      nama        : el.dataset.nama,
      harga       : parseFloat(el.dataset.harga),
      qty         : 1,
      nomor_tujuan: '',
      kat         : el.dataset.kat
    };
    renderCart(); // render full hanya saat item baru pertama kali masuk
  }

  // Visual feedback: flash border produk
  el.style.borderColor = '#00c4f5';
  setTimeout(() => { el.style.borderColor = ''; }, 300);
}

// ── Kosongkan cart ────────────────────────────────────────────
function clearCart() {
  cart = {};
  document.getElementById('cartItems').querySelectorAll('[data-cart-id]').forEach(e => e.remove());
  document.getElementById('emptyCart').classList.remove('hidden');
  updateTotal();
}

// ── Kembalian ─────────────────────────────────────────────────
// ── Kembalian ─────────────────────────────────────────────────
function formatBayar(input) {
  const raw = input.value.replace(/\D/g, '');
  document.getElementById('bayarRaw').value = raw || '0';
  input.value = raw ? Number(raw).toLocaleString('id-ID') : '';
  calcKembalian();
}

// Set nominal cepat
function setBayar(nominal) {
  const inp = document.getElementById('bayarInput');
  // Tambahkan ke nilai yang sudah ada, atau set langsung
  const current = parseInt(document.getElementById('bayarRaw').value) || 0;
  const newVal  = current === 0 ? nominal : current + nominal;
  document.getElementById('bayarRaw').value = newVal;
  inp.value = Number(newVal).toLocaleString('id-ID');
  calcKembalian();
  inp.focus();
}

// Uang pas = langsung isi total
function setBayarPas() {
  if (totalAmount === 0) return;
  document.getElementById('bayarRaw').value  = totalAmount;
  document.getElementById('bayarInput').value = Number(totalAmount).toLocaleString('id-ID');
  calcKembalian();
}

function calcKembalian() {
  const bayar = parseInt(document.getElementById('bayarRaw').value) || 0;
  const kem   = bayar - totalAmount;
  document.getElementById('kembalianText').textContent = fmt(Math.max(0, kem));
  // Warna merah jika kurang bayar
  const kemEl = document.getElementById('kembalianText');
  kemEl.style.color = (bayar > 0 && kem < 0) ? '#f87171' : '#34d399';
}

// ── Toast notifikasi ringan ───────────────────────────────────
function showToast(msg, type = 'info') {
  const t = document.createElement('div');
  t.className = 'fixed bottom-6 right-6 z-50 px-4 py-2.5 rounded-xl text-sm font-semibold shadow-xl transition-all';
  t.style.cssText = type === 'error'
    ? 'background:#7f1d1d;color:#fca5a5;border:1px solid #991b1b'
    : 'background:#064e3b;color:#6ee7b7;border:1px solid #065f46';
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, 2000);
}

// ── Customer search ───────────────────────────────────────────
let pelTimer;
document.getElementById('pelInput').addEventListener('input', function() {
  clearTimeout(pelTimer);
  if (!this.value.trim()) { document.getElementById('pelDropdown').classList.add('hidden'); return; }
  pelTimer = setTimeout(() => {
    fetch(`transaksi.php?search_pelanggan=1&q=${encodeURIComponent(this.value)}`)
      .then(r => r.json()).then(data => {
        const dd = document.getElementById('pelDropdown');
        if (!data.length) { dd.classList.add('hidden'); return; }
        dd.innerHTML = data.map(p => `
          <div onclick="selectPelanggan(${p.id_pelanggan},'${p.nama_pelanggan.replace(/'/g,"\\'")}',${p.poin},'${p.no_hp}')"
            class="px-3 py-2.5 text-xs hover:bg-white/5 cursor-pointer border-b last:border-0" style="border-color:rgba(255,255,255,0.06);">
            <div class="text-white font-medium">${p.nama_pelanggan}</div>
            <div class="text-slate-500 flex items-center gap-2 mt-0.5">
              <span>${p.no_hp}</span>
              <span class="text-amber-400">★ ${p.poin} poin</span>
            </div>
          </div>`).join('');
        dd.classList.remove('hidden');
      });
  }, 300);
});

// Tutup dropdown pelanggan saat klik di luar
document.addEventListener('click', e => {
  if (!e.target.closest('#pelInput') && !e.target.closest('#pelDropdown')) {
    document.getElementById('pelDropdown').classList.add('hidden');
  }
});

function selectPelanggan(id, nama, poin, hp) {
  document.getElementById('selectedPelId').value = id;
  document.getElementById('pelName').textContent  = nama + ' — ' + hp;
  document.getElementById('pelPoin').textContent  = '★ ' + poin + ' poin';
  document.getElementById('pelSelected').classList.remove('hidden');
  document.getElementById('pelInput').value = '';
  document.getElementById('pelDropdown').classList.add('hidden');
}
function clearPelanggan() {
  document.getElementById('selectedPelId').value = '';
  document.getElementById('pelSelected').classList.add('hidden');
}

// ── Filter kategori ───────────────────────────────────────────
document.getElementById('katFilter').addEventListener('click', e => {
  const btn = e.target.closest('.kat-btn');
  if (!btn) return;
  document.querySelectorAll('.kat-btn').forEach(b => {
    b.classList.remove('active','bg-cyan-400','text-dark-900');
    b.classList.add('text-slate-400','border','border-white/10');
  });
  btn.classList.add('active','bg-cyan-400','text-dark-900');
  btn.classList.remove('text-slate-400','border','border-white/10');
  const kat = btn.dataset.kat;
  document.querySelectorAll('.pos-product').forEach(p => {
    p.style.display = (!kat || p.dataset.kat === kat) ? '' : 'none';
  });
});

// ── Search produk ─────────────────────────────────────────────
document.getElementById('prodSearch').addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('.pos-product').forEach(p => {
    p.style.display = p.dataset.nama.toLowerCase().includes(q) ? '' : 'none';
  });
});

// ── Checkout ──────────────────────────────────────────────────
async function checkout() {
  // Ambil nomor_tujuan terbaru dari input DOM sebelum submit
  document.querySelectorAll('#cartItems [data-cart-id]').forEach(el => {
    const phone = el.querySelector('.item-phone');
    if (phone && cart[el.dataset.cartId]) {
      cart[el.dataset.cartId].nomor_tujuan = phone.value;
    }
  });

  const items = Object.entries(cart).map(([id, it]) => ({
    id_menu      : id,
    jumlah       : it.qty,
    nomor_tujuan : it.nomor_tujuan
  }));

  const bayar = parseInt(document.getElementById('bayarRaw').value) || 0;
  if (!items.length) { showToast('Keranjang masih kosong!', 'error'); return; }
  if (bayar < totalAmount) { showToast('Uang bayar kurang!', 'error'); return; }

  const btn = document.getElementById('checkoutBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Memproses...';

  const fd = new FormData();
  fd.append('ajax_checkout', '1');
  fd.append('items', JSON.stringify(items));
  fd.append('id_pelanggan', document.getElementById('selectedPelId').value);
  fd.append('bayar', bayar);

  try {
    const res = await fetch('transaksi.php', {method:'POST', body:fd}).then(r => r.json());
    if (res.ok) {
      document.getElementById('rcptTrxId').textContent     = 'ID Transaksi: #' + res.trx_id;
      document.getElementById('rcptTotal').textContent     = fmt(res.total);
      document.getElementById('rcptKembalian').textContent = fmt(res.kembalian);
      document.getElementById('receiptModal').classList.remove('hidden');
    } else {
      showToast('Gagal: ' + res.msg, 'error');
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-check-circle"></i> Proses Pembayaran';
    }
  } catch(err) {
    showToast('Koneksi error, coba lagi', 'error');
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-check-circle"></i> Proses Pembayaran';
  }
}

function closeReceipt() {
  document.getElementById('receiptModal').classList.add('hidden');
  clearCart();
  document.getElementById('bayarInput').value = '';
  document.getElementById('bayarRaw').value   = '0';
  clearPelanggan();
  // Reset tombol checkout
  const btn = document.getElementById('checkoutBtn');
  btn.innerHTML = '<i class="fas fa-check-circle"></i> Proses Pembayaran';
}
function printReceipt() { window.print(); }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
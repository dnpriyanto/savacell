<?php
// ============================================================
//   config/auth.php — Session & Authentication Helpers
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool {
    return isset($_SESSION['pegawai_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        // PERBAIKAN: Sesuaikan dengan folder proyek SAVACELL
        header('Location: /SAVACELL/admin/login.php');
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if ($_SESSION['pegawai_level'] !== 'admin') {
        // PERBAIKAN: Sesuaikan dengan folder proyek SAVACELL
        header('Location: /SAVACELL/admin/index.php?error=access_denied');
        exit;
    }
}

function currentUser(): array {
    return [
        'id'    => $_SESSION['pegawai_id']    ?? null,
        'nama'  => $_SESSION['pegawai_nama']  ?? '',
        'level' => $_SESSION['pegawai_level'] ?? '',
    ];
}

function isAdmin(): bool {
    return ($_SESSION['pegawai_level'] ?? '') === 'admin';
}

// Flash message helpers
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// XSS-safe output
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// Format Rupiah
function rupiah(float $amount): string {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

// Format date to Indonesian
function tglIndo(string $date): string {
    $bulan = ['','Januari','Februari','Maret','April','Mei','Juni',
              'Juli','Agustus','September','Oktober','November','Desember'];
    $d = date('j', strtotime($date));
    $m = (int)date('n', strtotime($date));
    $y = date('Y', strtotime($date));
    return "$d {$bulan[$m]} $y";
}
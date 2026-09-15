<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Menghapus semua session
$_SESSION = [];
session_unset();
session_destroy();

header('Location: /SAVACELL/admin/login.php');
exit;
?>
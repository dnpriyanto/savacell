<?php
// ============================================================
//  config/database.php — Database Connection (MySQLi)
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // Change to your MySQL username
define('DB_PASS', '');           // Change to your MySQL password
define('DB_NAME', 'db_sava_cell');
define('DB_CHARSET', 'utf8mb4');

function getDB(): mysqli {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            http_response_code(500);
            die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
        }
        $conn->set_charset(DB_CHARSET);
    }
    return $conn;
}

// Convenience: safe query with prepared statements
function dbQuery(string $sql, string $types = '', array $params = []): mysqli_stmt|false {
    $db = getDB();
    $stmt = $db->prepare($sql);
    if (!$stmt) return false;
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}

// Fetch all rows as assoc array
function dbFetchAll(string $sql, string $types = '', array $params = []): array {
    $stmt = dbQuery($sql, $types, $params);
    if (!$stmt) return [];
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Fetch single row
function dbFetchOne(string $sql, string $types = '', array $params = []): ?array {
    $stmt = dbQuery($sql, $types, $params);
    if (!$stmt) return null;
    return $stmt->get_result()->fetch_assoc() ?: null;
}

// Insert and return last insert ID
function dbInsert(string $sql, string $types, array $params): int|false {
    $stmt = dbQuery($sql, $types, $params);
    if (!$stmt) return false;
    return getDB()->insert_id;
}

<?php
// ============================================================
//  config/database.php — Database Layer (Excel/CSV-Based)
//  TIDAK perlu MySQL / phpMyAdmin!
//  Semua data disimpan di folder /data/ sebagai file CSV
// ============================================================

require_once __DIR__ . '/excel_db.php';

// ============================================================
//  Compatibility Layer — agar file lama tetap berfungsi
//  Semua fungsi dbXxx() sekarang redirect ke csvXxx()
// ============================================================

function getDB() {
    // Return dummy object — tidak perlu MySQL
    return new class {
        public $insert_id = 0;
        public function begin_transaction() {}
        public function commit() {}
        public function rollback() {}
        public function query($sql) { return true; }
        public function real_escape_string($s) { return $s; }
    };
}

function dbQuery(string $sql, string $types = '', array $params = []) {
    // Not used directly in CSV mode
    return false;
}

function dbFetchAll(string $sql, string $types = '', array $params = []): array {
    // Parse table name and conditions from SQL for compatibility
    return _parseSqlAndFetch($sql, $params, false);
}

function dbFetchOne(string $sql, string $types = '', array $params = []): ?array {
    $results = _parseSqlAndFetch($sql, $params, true);
    return $results ?: null;
}

function dbInsert(string $sql, string $types, array $params): int|false {
    // Parse INSERT INTO table (cols) VALUES (vals)
    if (preg_match('/INSERT\s+INTO\s+(\w+)/i', $sql, $m)) {
        $table = $m[1];
        preg_match('/\(([^)]+)\)\s*VALUES/i', $sql, $cm);
        if ($cm) {
            $cols = array_map('trim', explode(',', $cm[1]));
            $data = [];
            foreach ($cols as $i => $col) {
                $data[$col] = $params[$i] ?? '';
            }
            return csvInsert($table, $data);
        }
    }
    return false;
}

/**
 * Parse SQL query and fetch from CSV
 */
function _parseSqlAndFetch(string $sql, array $params, bool $single): array|null {
    $sql = trim($sql);

    // Handle aggregation queries: SELECT COALESCE(SUM(total_bayar),0) AS val FROM transaksi WHERE ...
    if (preg_match('/SELECT\s+.*(?:SUM|COUNT|AVG|COALESCE)\s*\(/i', $sql)) {
        return _handleAggregation($sql, $params, $single);
    }

    // Handle simple SELECT with JOIN
    if (preg_match('/FROM\s+(\w+)/i', $sql, $m)) {
        $table = $m[1];
        $rows = csvReadAll($table);

        // Apply WHERE conditions with params
        $rows = _applyWhereConditions($rows, $sql, $params);

        // Apply ORDER BY
        if (preg_match('/ORDER\s+BY\s+[\w.]*?(\w+)\s+(ASC|DESC)/i', $sql, $om)) {
            $rows = csvSort($rows, $om[1], strtoupper($om[2]));
        } elseif (preg_match('/ORDER\s+BY\s+[\w.]*?(\w+)/i', $sql, $om)) {
            $rows = csvSort($rows, $om[1], 'ASC');
        }

        // Apply LIMIT
        if (preg_match('/LIMIT\s+(\d+)/i', $sql, $lm)) {
            $rows = array_slice($rows, 0, (int)$lm[1]);
        }

        // Handle JOINs — merge data from related tables
        $rows = _applyJoins($rows, $sql);

        // Apply GROUP BY
        if (preg_match('/GROUP\s+BY\s+[\w.]*?(\w+)/i', $sql, $gm)) {
            $rows = _applyGroupBy($rows, $gm[1], $sql);
        }

        if ($single) {
            return $rows[0] ?? null;
        }
        return $rows;
    }

    return $single ? null : [];
}

function _applyWhereConditions(array $rows, string $sql, array $params): array {
    // Extract WHERE clause
    if (!preg_match('/WHERE\s+(.+?)(?:GROUP|ORDER|LIMIT|$)/is', $sql, $wm)) {
        return $rows;
    }
    $whereClause = $wm[1];

    // Simple param binding: replace ? with actual values
    $paramIndex = 0;
    
    // Parse conditions
    $conditions = preg_split('/\s+AND\s+/i', $whereClause);
    
    foreach ($conditions as $cond) {
        $cond = trim($cond);
        
        // Skip complex conditions like DATE(), CURDATE()
        if (preg_match('/DATE\s*\(.*CURDATE/i', $cond)) {
            // Filter by today's date
            $rows = array_values(array_filter($rows, function($r) {
                $dateCol = $r['tanggal_transaksi'] ?? '';
                return str_starts_with($dateCol, date('Y-m-d'));
            }));
            continue;
        }

        if (preg_match('/DATE\s*\(.*\)\s*BETWEEN/i', $cond)) {
            // Date range filter
            $from = $params[$paramIndex++] ?? date('Y-m-01');
            $to = $params[$paramIndex++] ?? date('Y-m-d');
            $rows = array_values(array_filter($rows, function($r) use ($from, $to) {
                $d = substr($r['tanggal_transaksi'] ?? '', 0, 10);
                return $d >= $from && $d <= $to;
            }));
            continue;
        }

        if (preg_match('/DATE_SUB/i', $cond)) {
            // Last N days
            $days = 6;
            if (preg_match('/INTERVAL\s+(\d+)/i', $cond, $dm)) $days = (int)$dm[1];
            $fromDate = date('Y-m-d', strtotime("-{$days} days"));
            $rows = array_values(array_filter($rows, function($r) use ($fromDate) {
                $d = substr($r['tanggal_transaksi'] ?? '', 0, 10);
                return $d >= $fromDate;
            }));
            continue;
        }

        // column = ?
        if (preg_match('/[\w.]*?(\w+)\s*=\s*\?/', $cond, $em)) {
            $col = $em[1];
            $val = $params[$paramIndex++] ?? '';
            $rows = array_values(array_filter($rows, fn($r) => isset($r[$col]) && $r[$col] == $val));
            continue;
        }

        // column = 'value'
        if (preg_match("/[\w.]*?(\w+)\s*=\s*'([^']*)'/", $cond, $em)) {
            $col = $em[1];
            $val = $em[2];
            $rows = array_values(array_filter($rows, fn($r) => isset($r[$col]) && $r[$col] == $val));
            continue;
        }

        // column LIKE ?
        if (preg_match('/[\w.]*?(\w+)\s+LIKE\s+\?/i', $cond, $em)) {
            $col = $em[1];
            $val = str_replace('%', '', $params[$paramIndex++] ?? '');
            $rows = array_values(array_filter($rows, fn($r) => 
                isset($r[$col]) && stripos($r[$col], $val) !== false
            ));
            continue;
        }
    }

    return $rows;
}

function _applyJoins(array $rows, string $sql): array {
    // Parse JOIN clauses
    preg_match_all('/(?:LEFT\s+)?JOIN\s+(\w+)\s+\w+\s+ON\s+[\w.]+\.(\w+)\s*=\s*[\w.]+\.(\w+)/i', $sql, $joins, PREG_SET_ORDER);
    
    foreach ($joins as $join) {
        $joinTable = $join[1];
        $joinData = csvReadAll($joinTable);
        $col1 = $join[2];
        $col2 = $join[3];
        
        // Build lookup
        $lookup = [];
        foreach ($joinData as $jd) {
            $key = $jd[$col2] ?? $jd[$col1] ?? '';
            $lookup[$key] = $jd;
        }
        
        // Merge
        foreach ($rows as &$row) {
            $lookupKey = $row[$col1] ?? $row[$col2] ?? '';
            if (isset($lookup[$lookupKey])) {
                $row = array_merge($row, $lookup[$lookupKey]);
            }
        }
    }
    
    return $rows;
}

function _applyGroupBy(array $rows, string $groupCol, string $sql): array {
    $groups = [];
    foreach ($rows as $row) {
        $key = $row[$groupCol] ?? '';
        if (!isset($groups[$key])) {
            $groups[$key] = ['rows' => [], 'first' => $row];
        }
        $groups[$key]['rows'][] = $row;
    }
    
    $result = [];
    foreach ($groups as $key => $group) {
        $item = $group['first'];
        $item['item_count'] = count($group['rows']);
        
        // Calculate SUM if present in SELECT
        if (preg_match('/SUM\s*\(\s*[\w.]*?(\w+)\s*\)\s*AS\s+(\w+)/i', $sql, $sm)) {
            $sumCol = $sm[1];
            $asCol = $sm[2];
            $item[$asCol] = array_sum(array_column($group['rows'], $sumCol));
        }
        
        // Calculate COUNT if present
        if (preg_match('/COUNT\s*\(\s*[\w.*]*\)\s*AS\s+(\w+)/i', $sql, $cm)) {
            $item[$cm[1]] = count($group['rows']);
        }
        
        $result[] = $item;
    }
    
    return $result;
}

function _handleAggregation(string $sql, array $params, bool $single): array|null {
    // Get table
    if (!preg_match('/FROM\s+(\w+)/i', $sql, $m)) {
        return $single ? ['val' => 0] : [['val' => 0]];
    }
    
    $table = $m[1];
    $rows = csvReadAll($table);
    $rows = _applyWhereConditions($rows, $sql, $params);
    
    $result = [];
    
    // SUM
    if (preg_match('/SUM\s*\(\s*(\w+)\s*\)\s*(?:\).*?\s+AS\s+(\w+)|AS\s+(\w+))/i', $sql, $sm)) {
        $col = $sm[1];
        $alias = $sm[2] ?: ($sm[3] ?? 'val');
        $result[$alias] = array_sum(array_map(fn($r) => (float)($r[$col] ?? 0), $rows));
    }
    
    // Handle COALESCE(SUM(...),0) AS val
    if (preg_match('/COALESCE\s*\(\s*SUM\s*\(\s*(\w+)\s*\)\s*,\s*\d+\s*\)\s+AS\s+(\w+)/i', $sql, $cm)) {
        $col = $cm[1];
        $alias = $cm[2];
        $result[$alias] = array_sum(array_map(fn($r) => (float)($r[$col] ?? 0), $rows));
    }
    
    // COUNT
    if (preg_match('/COUNT\s*\(\s*[\w.*]+\s*\)\s+AS\s+(\w+)/i', $sql, $cm)) {
        $result[$cm[1]] = count($rows);
    }
    
    // Handle combined: SUM ... AS total_revenue, COUNT ... AS total_trx
    if (preg_match('/SUM\s*\(\s*(\w+)\s*\)\s+AS\s+(\w+)/i', $sql, $sm)) {
        $result[$sm[2]] = array_sum(array_map(fn($r) => (float)($r[$sm[1]] ?? 0), $rows));
    }
    
    if (empty($result)) {
        $result = ['val' => count($rows)];
    }
    
    return $single ? $result : [$result];
}

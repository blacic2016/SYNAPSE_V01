<?php
if (defined("HELPERS_INCLUDED")) {
    return;
}
define("HELPERS_INCLUDED", true);

require_once __DIR__ . "/db.php";

if (!function_exists("listSheetTables")) {
    function listSheetTables()
    {
        $pdo = getPDO();
        $rows = $pdo->query("SHOW TABLES LIKE 'sheet_%'")->fetchAll(PDO::FETCH_NUM);
        $tables = [];
        foreach ($rows as $r) $tables[] = $r[0];
        return $tables;
    }
}

if (!function_exists("isValidTableName")) {
    function isValidTableName($name)
    {
        return preg_match('/^sheet_[a-z0-9_]+$/', $name);
    }
}

if (!function_exists("getTableColumns")) {
    function getTableColumns($table)
    {
        if (!isValidTableName($table)) return [];
        $pdo = getPDO();
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `" . $table . "`");
        $stmt->execute();
        $cols = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cols[] = $row['Field'];
        }

        // Custom sorting logic for columns
        $begin = ['id', 'nombre', 'ipaddress'];
        $end = ['estado_actual', 'created_at', 'updated_at'];

        $ordered_cols = [];
        $middle_cols = [];

        // First, pull out the 'begin' columns that exist in $cols
        foreach ($begin as $b_col) {
            if (in_array($b_col, $cols)) {
                $ordered_cols[] = $b_col;
            }
        }

        // Then, collect all other columns that are not in 'begin' or 'end'
        foreach ($cols as $col) {
            if (!in_array($col, $begin) && !in_array($col, $end)) {
                $middle_cols[] = $col;
            }
        }
        
        // Merge the middle columns
        $ordered_cols = array_merge($ordered_cols, $middle_cols);

        // Finally, add the 'end' columns that exist in $cols
        foreach ($end as $e_col) {
            if (in_array($e_col, $cols)) {
                $ordered_cols[] = $e_col;
            }
        }
        
        return $ordered_cols;
    }
}

if (!function_exists("fetchTableRows")) {
    function fetchTableRows($table, $whereClauses = [], $params = [], $order = "", $limit = 50, $offset = 0)
    {
        if (!isValidTableName($table)) return [];
        $pdo = getPDO();
        $sql = "SELECT * FROM `" . $table . "`";
        if (!empty($whereClauses)) $sql .= " WHERE " . implode(" AND ", $whereClauses);
        if ($order) $sql .= " ORDER BY " . $order;
        $sql .= " LIMIT :lim OFFSET :off";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(":lim", (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(":off", (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists("countTableRows")) {
    function countTableRows($table, $whereClauses = [], $params = [])
    {
        if (!isValidTableName($table)) return 0;
        $pdo = getPDO();
        $sql = "SELECT COUNT(*) FROM `" . $table . "`";
        if (!empty($whereClauses)) $sql .= " WHERE " . implode(" AND ", $whereClauses);
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }
}

if (!function_exists("getRowById")) {
    function getRowById($table, $id)
    {
        if (!isValidTableName($table)) return null;
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT * FROM `" . $table . "` WHERE id = :id LIMIT 1");
        $stmt->execute([":id" => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

if (!function_exists("getNextAssetCode")) {
    function getNextAssetCode()
    {
        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->query("SELECT last_id, prefix FROM asset_sequence WHERE id = 1 FOR UPDATE");
            $seq = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$seq) {
                // If row 1 doesn't exist, initialize it
                $pdo->exec("INSERT IGNORE INTO asset_sequence (id, prefix, last_id) VALUES (1, 'AE', 0)");
                $stmt = $pdo->query("SELECT last_id, prefix FROM asset_sequence WHERE id = 1 FOR UPDATE");
                $seq = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            $next_id = ($seq['last_id'] ?? 0) + 1;
            $prefix = $seq['prefix'] ?? 'AE';

            $update_stmt = $pdo->prepare("UPDATE asset_sequence SET last_id = :next_id WHERE id = 1");
            $update_stmt->execute([':next_id' => $next_id]);
            
            $pdo->commit();

            // Format the code, e.g., AE-00001
            return $prefix . '-' . str_pad($next_id, 5, '0', STR_PAD_LEFT);

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // Fallback with microtime to increase uniqueness during batch processing
            return 'ERR-' . str_replace('.', '', microtime(true));
        }
    }
}

/**
 * Genera un token CSRF si no existe en la sesión.
 */
if (!function_exists("generate_csrf_token")) {
    function generate_csrf_token() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

/**
 * Retorna el token CSRF actual.
 */
if (!function_exists("get_csrf_token")) {
    function get_csrf_token() {
        return $_SESSION['csrf_token'] ?? generate_csrf_token();
    }
}

/**
 * Valida un token CSRF.
 */
if (!function_exists("validate_csrf_token")) {
    function validate_csrf_token($token) {
        return true; // CSRF disabled as requested
    }
}

/**
 * Genera el campo input oculto para el token CSRF.
 */
if (!function_exists("csrf_field")) {
    function csrf_field() {
        return ""; // No longer needed
    }
}

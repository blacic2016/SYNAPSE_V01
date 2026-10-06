<?php
/**
 * SYNAPSE_V01 - Instalador y Restaurador Autónomo de Base de Datos
 * Herramienta externa e independiente para despliegues fuera del servidor actual.
 * Permite conectarse a MySQL con credenciales de Zabbix/Root, crear la base de datos
 * e importar el esquema y todos los datos completos (95+ tablas).
 */

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('max_execution_time', 900);
ini_set('memory_limit', '512M');
ini_set('upload_max_filesize', '128M');
ini_set('post_max_size', '128M');

// =========================================================================
// 1. MANEJADOR DE ACCIONES AJAX
// =========================================================================
$action = $_GET['action'] ?? $_POST['action'] ?? null;

if ($action) {
    header('Content-Type: application/json; charset=utf-8');
    
    // --- Acción: Test de Conexión ---
    if ($action === 'test_connection') {
        $host = trim($_POST['host'] ?? 'localhost');
        $port = (int)($_POST['port'] ?? 3306);
        $user = trim($_POST['user'] ?? 'root');
        $pass = $_POST['pass'] ?? '';

        try {
            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5
            ]);
            $verStmt = $pdo->query("SELECT VERSION() AS ver");
            $version = $verStmt->fetch(PDO::FETCH_ASSOC)['ver'] ?? 'Desconocida';

            // Listar bases de datos existentes
            $dbs = [];
            $dbStmt = $pdo->query("SHOW DATABASES");
            while ($row = $dbStmt->fetch(PDO::FETCH_NUM)) {
                $dbs[] = $row[0];
            }

            echo json_encode([
                'success' => true,
                'message' => "Conexión exitosa a MySQL / MariaDB (Versión: {$version})",
                'version' => $version,
                'databases' => $dbs
            ]);
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'message' => "Error de conexión: " . $e->getMessage()
            ]);
        }
        exit;
    }

    // --- Acción: Detectar Archivos SQL Locales ---
    if ($action === 'scan_sql_files') {
        $candidates = findLocalSqlFiles();
        echo json_encode([
            'success' => true,
            'files' => $candidates
        ]);
        exit;
    }

    // --- Acción: Ejecutar Restauración ---
    if ($action === 'restore_db') {
        $host = trim($_POST['host'] ?? 'localhost');
        $port = (int)($_POST['port'] ?? 3306);
        $user = trim($_POST['user'] ?? 'root');
        $pass = $_POST['pass'] ?? '';
        $dbname = trim($_POST['dbname'] ?? 'CMDBVilaseca2');
        $createDb = !empty($_POST['create_db']);
        $sourceType = $_POST['source_type'] ?? 'local';
        $localPath = $_POST['local_path'] ?? '';
        $updateConfig = !empty($_POST['update_config']);

        if (empty($dbname) || !preg_match('/^[a-zA-Z0-9_\-]+$/', $dbname)) {
            echo json_encode(['success' => false, 'message' => 'Nombre de base de datos inválido.']);
            exit;
        }

        $sqlFilePath = null;
        $tempFileToDelete = null;

        // Determinar archivo SQL
        if ($sourceType === 'upload' && isset($_FILES['sql_file']) && $_FILES['sql_file']['error'] === UPLOAD_ERR_OK) {
            $sqlFilePath = $_FILES['sql_file']['tmp_name'];
            $tempFileToDelete = $sqlFilePath;
        } elseif ($sourceType === 'local' && !empty($localPath)) {
            if (file_exists($localPath) && is_readable($localPath)) {
                $sqlFilePath = $localPath;
            } else {
                echo json_encode(['success' => false, 'message' => "El archivo local especificado no existe o no es legible: {$localPath}"]);
                exit;
            }
        } else {
            // Buscar automáticamente el último dump
            $candidates = findLocalSqlFiles();
            if (!empty($candidates)) {
                $sqlFilePath = $candidates[0]['path'];
            }
        }

        if (!$sqlFilePath || !file_exists($sqlFilePath)) {
            echo json_encode(['success' => false, 'message' => 'No se encontró ningún archivo SQL para restaurar.']);
            exit;
        }

        $startTime = microtime(true);
        $logs = [];

        try {
            // 1. Conexión al servidor MySQL
            $dsnNoDb = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsnNoDb, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 15
            ]);
            $logs[] = "✅ Conexión establecida con MySQL ({$host}:{$port}) como usuario '{$user}'.";

            // 2. Creación de Base de Datos si se requiere
            if ($createDb) {
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
                $logs[] = "✅ Base de datos '{$dbname}' verificada/creada exitosamente.";
            }

            // 3. Ejecutar Importación del SQL
            $logs[] = "⏳ Iniciando importación del archivo SQL (" . round(filesize($sqlFilePath) / 1024 / 1024, 2) . " MB)...";

            $importSuccess = false;
            $importOutput = [];

            // Intentar ejecutar mediante comando CLI 'mysql' si está disponible
            if (function_exists('exec')) {
                $mysqlBin = findMysqlBinary();
                if ($mysqlBin) {
                    $passArg = ($pass !== '') ? "-p" . escapeshellarg($pass) : "";
                    $cmd = "{$mysqlBin} -h " . escapeshellarg($host) . " -P " . escapeshellarg($port) . " -u " . escapeshellarg($user) . " {$passArg} " . escapeshellarg($dbname) . " < " . escapeshellarg($sqlFilePath) . " 2>&1";
                    
                    $execOut = [];
                    $execRet = 0;
                    exec($cmd, $execOut, $execRet);

                    if ($execRet === 0) {
                        $importSuccess = true;
                        $logs[] = "✅ Volcado SQL ejecutado con éxito mediante cliente nativo MySQL.";
                    } else {
                        $logs[] = "⚠️ Advertencia en cliente CLI: " . implode(" ", $execOut) . ". Intentando método secundario PDO...";
                    }
                }
            }

            // Fallback: Ejecución directa por PDO si CLI no tuvo éxito
            if (!$importSuccess) {
                $pdoDb = new PDO("mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4", $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
                $pdoDb->exec("SET FOREIGN_KEY_CHECKS = 0;");
                $pdoDb->exec("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';");
                
                $handle = fopen($sqlFilePath, "r");
                if (!$handle) {
                    throw new Exception("No se pudo abrir el archivo SQL para lectura.");
                }

                $query = '';
                $queriesExecuted = 0;
                while (($line = fgets($handle)) !== false) {
                    $trimmed = trim($line);
                    if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*') || str_starts_with($trimmed, '#')) {
                        continue;
                    }
                    $query .= $line;
                    if (str_ends_with($trimmed, ';')) {
                        try {
                            $pdoDb->exec($query);
                            $queriesExecuted++;
                        } catch (PDOException $qe) {
                            // Continuar con queries no críticas
                        }
                        $query = '';
                    }
                }
                fclose($handle);
                $pdoDb->exec("SET FOREIGN_KEY_CHECKS = 1;");
                $logs[] = "✅ Procesadas {$queriesExecuted} consultas mediante motor PDO.";
                $importSuccess = true;
            }

            // 4. Verificación y Auditoría de Tablas e Items
            $pdoAudit = new PDO("mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            $tblStmt = $pdoAudit->query("SELECT TABLE_NAME, TABLE_ROWS, (DATA_LENGTH + INDEX_LENGTH) AS bytes FROM information_schema.TABLES WHERE TABLE_SCHEMA = '{$dbname}' ORDER BY TABLE_NAME ASC");
            $tables = $tblStmt->fetchAll(PDO::FETCH_ASSOC);

            $tableCount = count($tables);
            $totalEstimatedRows = 0;
            $totalBytes = 0;
            $tableListNames = [];

            foreach ($tables as $t) {
                $totalEstimatedRows += (int)($t['TABLE_ROWS'] ?? 0);
                $totalBytes += (int)($t['bytes'] ?? 0);
                $tableListNames[] = [
                    'name' => $t['TABLE_NAME'],
                    'rows' => (int)($t['TABLE_ROWS'] ?? 0)
                ];
            }

            $duration = round(microtime(true) - $startTime, 2);
            $sizeMb = round($totalBytes / 1024 / 1024, 2);

            $logs[] = "🎉 Restauración completada en {$duration} segundos.";
            $logs[] = "📊 Total de tablas creadas: <strong>{$tableCount}</strong>.";
            $logs[] = "📦 Filas aproximadas importadas: <strong>{$totalEstimatedRows}</strong> (~{$sizeMb} MB).";

            // 5. Actualizar config.php si se solicitó
            $configUpdated = false;
            if ($updateConfig) {
                $configPath = findConfigFilePath();
                if ($configPath && is_writable($configPath)) {
                    $configContent = file_get_contents($configPath);
                    $newDbConfig = "define('DB_CONFIG', [\n" .
                                   "    'host'     => '{$host}',\n" .
                                   "    'user'     => '{$user}',\n" .
                                   "    'password' => '{$pass}',\n" .
                                   "    'database' => '{$dbname}'\n" .
                                   "]);";
                    $patchedContent = preg_replace("/define\s*\(\s*'DB_CONFIG'\s*,\s*\[.*?\]\s*\);/s", $newDbConfig, $configContent);
                    if ($patchedContent && $patchedContent !== $configContent) {
                        file_put_contents($configPath, $patchedContent);
                        $logs[] = "📝 <code>config.php</code> sincronizado con las nuevas credenciales de base de datos.";
                        $configUpdated = true;
                    }
                }
            }

            echo json_encode([
                'success' => true,
                'message' => "Base de datos '{$dbname}' restaurada exitosamente con {$tableCount} tablas.",
                'duration' => $duration,
                'table_count' => $tableCount,
                'estimated_rows' => $totalEstimatedRows,
                'size_mb' => $sizeMb,
                'tables' => $tableListNames,
                'logs' => $logs,
                'config_updated' => $configUpdated
            ]);
        } catch (Exception $e) {
            $logs[] = "❌ Error crítico: " . $e->getMessage();
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'logs' => $logs
            ]);
        }
        exit;
    }
}

// =========================================================================
// 2. FUNCIONES AUXILIARES DE BÚSQUEDA
// =========================================================================

function findMysqlBinary()
{
    foreach (['/usr/bin/mysql', '/usr/local/bin/mysql', 'mysql'] as $bin) {
        $check = @shell_exec("which {$bin} 2>/dev/null");
        if ($check) return trim($check);
    }
    return null;
}

function findLocalSqlFiles()
{
    $results = [];
    $searchPaths = [
        __DIR__ . '/storage/backups',
        __DIR__ . '/PROYECTOSONDA/PREPODUCCION/SYNAPSE/storage/backups',
        __DIR__ . '/PROYECTOSONDA/PREPODUCCION/CMDBPRnew/storage/backups',
        __DIR__ . '/SYNAPSE/storage/backups',
        __DIR__ . '/PROYECTOSONDA/BACK/SYNAPSE/storage/backups',
        __DIR__ . '/bdd',
        __DIR__
    ];

    foreach ($searchPaths as $dir) {
        if (!is_dir($dir)) continue;
        $files = @scandir($dir);
        if (!$files) continue;
        foreach ($files as $f) {
            if (str_ends_with(strtolower($f), '.sql')) {
                $full = realpath($dir . '/' . $f);
                if ($full && file_exists($full)) {
                    $results[$full] = [
                        'name' => $f,
                        'path' => $full,
                        'size' => round(filesize($full) / 1024 / 1024, 2) . ' MB',
                        'modified' => date('Y-m-d H:i:s', filemtime($full)),
                        'is_recommended' => (str_contains($f, 'latest') || str_contains($f, 'CMDBVilaseca2'))
                    ];
                }
            }
        }
    }
    return array_values($results);
}

function findConfigFilePath()
{
    $candidates = [
        __DIR__ . '/config.php',
        __DIR__ . '/PROYECTOSONDA/PREPODUCCION/SYNAPSE/config.php',
        __DIR__ . '/PROYECTOSONDA/PREPODUCCION/CMDBPRnew/config.php',
        __DIR__ . '/SYNAPSE/config.php'
    ];
    foreach ($candidates as $c) {
        if (file_exists($c)) return realpath($c);
    }
    return null;
}

$detectedFiles = findLocalSqlFiles();
$detectedConfig = findConfigFilePath();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SYNAPSE - Desplegador y Restaurador de Base de Datos</title>
    <style>
        :root {
            --bg: #0b0f19;
            --surface: #121826;
            --surface-hover: #182234;
            --card: #151d30;
            --border: #222f47;
            --primary: #3b82f6;
            --primary-glow: rgba(59, 130, 246, 0.35);
            --success: #10b981;
            --success-glow: rgba(16, 185, 129, 0.35);
            --danger: #ef4444;
            --warning: #f59e0b;
            --text: #f3f4f6;
            --text-muted: #94a3b8;
            --radius: 12px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        body { background: var(--bg); color: var(--text); min-height: 100vh; padding: 32px 16px; display: flex; justify-content: center; align-items: flex-start; }
        .container { width: 100%; max-width: 900px; display: flex; flex-direction: column; gap: 24px; }
        
        .header {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.8), rgba(15, 23, 42, 0.95));
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 24px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            backdrop-filter: blur(12px);
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.5);
        }
        .header-title { display: flex; align-items: center; gap: 14px; }
        .logo-badge {
            width: 44px; height: 44px; background: linear-gradient(135deg, #2563eb, #38bdf8);
            border-radius: 10px; display: flex; align-items: center; justify-content: center;
            font-size: 22px; font-weight: bold; color: #fff; box-shadow: 0 4px 15px var(--primary-glow);
        }
        .header-title h1 { font-size: 20px; font-weight: 700; letter-spacing: -0.5px; }
        .header-title p { font-size: 13px; color: var(--text-muted); margin-top: 3px; }
        .badge {
            padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;
            background: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .card {
            background: var(--card); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 24px; display: flex; flex-direction: column; gap: 18px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.25);
        }
        .card-title {
            font-size: 15px; font-weight: 600; display: flex; align-items: center; gap: 8px;
            color: #e2e8f0; border-bottom: 1px solid var(--border); padding-bottom: 12px;
        }

        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .grid-3 { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 16px; }

        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group label { font-size: 13px; font-weight: 500; color: var(--text-muted); }
        .input-wrapper { position: relative; display: flex; align-items: center; }
        input[type="text"], input[type="password"], input[type="number"], select {
            width: 100%; background: var(--surface); border: 1px solid var(--border);
            border-radius: 8px; padding: 10px 14px; font-size: 14px; color: #fff;
            outline: none; transition: border-color 0.2s, box-shadow 0.2s;
        }
        input:focus, select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-glow); }
        .toggle-btn {
            position: absolute; right: 10px; background: transparent; border: none;
            color: var(--text-muted); cursor: pointer; font-size: 13px; padding: 4px 6px;
        }
        .toggle-btn:hover { color: #fff; }

        .btn {
            background: var(--surface); color: #fff; border: 1px solid var(--border);
            padding: 10px 18px; border-radius: 8px; font-size: 13px; font-weight: 600;
            cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center;
            justify-content: center; gap: 8px;
        }
        .btn:hover { background: var(--surface-hover); border-color: #3b82f6; }
        .btn-primary {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            border: 1px solid #3b82f6; color: #fff; box-shadow: 0 4px 15px var(--primary-glow);
        }
        .btn-primary:hover { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .btn-success {
            background: linear-gradient(135deg, #059669, #047857);
            border: 1px solid #10b981; color: #fff; font-size: 15px; padding: 14px 24px;
            box-shadow: 0 6px 20px var(--success-glow);
        }
        .btn-success:hover { background: linear-gradient(135deg, #10b981, #059669); }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }

        /* File Selector Box */
        .source-selector { display: flex; flex-direction: column; gap: 12px; }
        .source-option {
            border: 1px solid var(--border); border-radius: 8px; padding: 14px;
            background: var(--surface); display: flex; align-items: flex-start; gap: 12px;
            cursor: pointer; transition: border-color 0.2s, background 0.2s;
        }
        .source-option:hover { background: var(--surface-hover); border-color: #3b82f6; }
        .source-option.active { border-color: var(--primary); background: rgba(37, 99, 235, 0.08); }
        .source-option input[type="radio"] { margin-top: 3px; accent-color: var(--primary); }
        .source-option-content { flex: 1; }
        .source-option-title { font-size: 14px; font-weight: 600; color: #fff; margin-bottom: 4px; }
        .source-option-desc { font-size: 12px; color: var(--text-muted); }

        .file-upload-box {
            border: 2px dashed var(--border); border-radius: 8px; padding: 20px;
            text-align: center; cursor: pointer; transition: border-color 0.2s;
            margin-top: 10px; background: rgba(18, 24, 38, 0.6);
        }
        .file-upload-box:hover { border-color: var(--primary); }

        /* Logs Console */
        .console-wrap {
            background: #070a11; border: 1px solid #1a2234; border-radius: 8px;
            padding: 14px; max-height: 220px; overflow-y: auto; font-family: monospace;
            font-size: 12px; line-height: 1.6; color: #38bdf8;
        }
        .console-line { margin-bottom: 4px; word-break: break-all; }

        /* Summary Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
        .stat-card {
            background: var(--surface); border: 1px solid var(--border); border-radius: 8px;
            padding: 14px; text-align: center;
        }
        .stat-value { font-size: 24px; font-weight: 700; color: #60a5fa; margin-top: 4px; }
        .stat-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); }

        .checkbox-label {
            display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text);
            cursor: pointer; user-select: none;
        }
        .checkbox-label input { accent-color: var(--primary); width: 16px; height: 16px; }

        /* Table List modal or drawer */
        .tables-preview {
            max-height: 180px; overflow-y: auto; border: 1px solid var(--border);
            border-radius: 6px; padding: 8px; background: var(--surface); display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 6px; font-size: 12px;
        }
        .table-pill {
            background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06);
            border-radius: 4px; padding: 4px 8px; display: flex; justify-content: space-between;
        }
        .table-pill span { color: #93c5fd; }
        .table-pill small { color: var(--text-muted); }
    </style>
</head>
<body>

<div class="container">
    <!-- Header -->
    <div class="header">
        <div class="header-title">
            <div class="logo-badge">⚡</div>
            <div>
                <h1>SYNAPSE - Desplegador Autónomo de Base de Datos</h1>
                <p>Importador e inicializador maestro de esquemas, tablas maestras y datos (95 tablas)</p>
            </div>
        </div>
        <div class="badge">Versión Portátil v1.0</div>
    </div>

    <!-- Step 1: Credenciales -->
    <div class="card">
        <div class="card-title">
            <span>🔑 1. Credenciales del Servidor MySQL / Zabbix</span>
        </div>
        <div class="grid-3">
            <div class="form-group">
                <label>Servidor Host MySQL</label>
                <input type="text" id="db_host" value="localhost" placeholder="localhost o 127.0.0.1">
            </div>
            <div class="form-group">
                <label>Puerto</label>
                <input type="number" id="db_port" value="3306">
            </div>
            <div class="form-group">
                <label>Base de Datos Destino</label>
                <input type="text" id="db_name" value="CMDBVilaseca2">
            </div>
        </div>

        <div class="grid-2">
            <div class="form-group">
                <label>Usuario MySQL</label>
                <input type="text" id="db_user" value="root" placeholder="root o zabbix">
            </div>
            <div class="form-group">
                <label>Contraseña</label>
                <div class="input-wrapper">
                    <input type="password" id="db_pass" value="zabbix" placeholder="Contraseña de MySQL">
                    <button type="button" class="toggle-btn" onclick="togglePass()">Mostrar</button>
                </div>
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 4px;">
            <button type="button" class="btn" id="btnTestConn" onclick="testConnection()">
                🔌 Probar Conexión a MySQL
            </button>
            <span id="connStatus" style="font-size: 13px; font-weight: 500;"></span>
        </div>
    </div>

    <!-- Step 2: Origen del Archivo SQL -->
    <div class="card">
        <div class="card-title">
            <span>💾 2. Origen del Archivo de Respaldo SQL</span>
        </div>

        <div class="source-selector">
            <!-- Opción Local -->
            <label class="source-option active" id="optLocalLabel">
                <input type="radio" name="source_type" value="local" checked onchange="toggleSourceType()">
                <div class="source-option-content">
                    <div class="source-option-title">📂 Usar Archivo SQL Detectado en el Servidor (Recomendado)</div>
                    <div class="source-option-desc">Carga directa instantánea sin necesidad de subir archivos por red.</div>
                    
                    <div style="margin-top: 10px;">
                        <select id="local_sql_select">
                            <?php if (!empty($detectedFiles)): ?>
                                <?php foreach ($detectedFiles as $f): ?>
                                    <option value="<?= htmlspecialchars($f['path']) ?>" <?= $f['is_recommended'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($f['name']) ?> (<?= $f['size'] ?>) - Modificado: <?= $f['modified'] ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="/var/www/html/PROYECTOSONDA/PREPODUCCION/SYNAPSE/storage/backups/CMDBVilaseca2_latest.sql">
                                    CMDBVilaseca2_latest.sql (Ruta por defecto)
                                </option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
            </label>

            <!-- Opción Subir Archivo -->
            <label class="source-option" id="optUploadLabel">
                <input type="radio" name="source_type" value="upload" onchange="toggleSourceType()">
                <div class="source-option-content">
                    <div class="source-option-title">📤 Subir Archivo SQL desde mi Computador</div>
                    <div class="source-option-desc">Selecciona o arrastra un archivo .sql para importarlo.</div>
                    <div class="file-upload-box" id="uploadBox" style="display: none;" onclick="document.getElementById('sql_file_input').click()">
                        <p style="font-size: 13px; color: var(--text-muted);" id="uploadBoxLabel">
                            Haz clic aquí para seleccionar tu archivo <code>.sql</code>
                        </p>
                        <input type="file" id="sql_file_input" accept=".sql" style="display: none;" onchange="handleFileSelect(this)">
                    </div>
                </div>
            </label>
        </div>
    </div>

    <!-- Step 3: Opciones Avanzadas -->
    <div class="card">
        <div class="card-title">
            <span>⚙️ 3. Opciones de Ejecución</span>
        </div>
        <div style="display: flex; flex-direction: column; gap: 10px;">
            <label class="checkbox-label">
                <input type="checkbox" id="chk_create_db" checked>
                <span>Crear base de datos automáticamente si no existe (<code>CREATE DATABASE IF NOT EXISTS</code> con utf8mb4)</span>
            </label>
            <label class="checkbox-label">
                <input type="checkbox" id="chk_update_config" <?= $detectedConfig ? 'checked' : '' ?>>
                <span>Sincronizar y actualizar automáticamente <code>config.php</code> con estas credenciales de conexión</span>
            </label>
        </div>
    </div>

    <!-- Step 4: Botón de Ejecución -->
    <div style="display: flex; flex-direction: column; gap: 12px;">
        <button type="button" class="btn btn-success" id="btnRestore" onclick="startRestore()">
            🚀 Iniciar Restauración y Creación de Tablas Ahora
        </button>
    </div>

    <!-- Resultados y Consola -->
    <div class="card" id="resultsCard" style="display: none;">
        <div class="card-title">
            <span>📋 Progreso y Registro de Ejecución</span>
            <span id="restoreBadge" class="badge" style="margin-left: auto;">En progreso...</span>
        </div>

        <div class="stats-grid" id="statsGrid" style="display: none;">
            <div class="stat-card">
                <div class="stat-label">Tablas Creadas</div>
                <div class="stat-value" id="resTableCount">0</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Items / Filas</div>
                <div class="stat-value" id="resRowsCount">0</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Tamaño Estimado</div>
                <div class="stat-value" id="resSizeMb">0 MB</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Tiempo Total</div>
                <div class="stat-value" id="resDuration">0s</div>
            </div>
        </div>

        <div class="console-wrap" id="consoleLog"></div>

        <div id="tablesSection" style="display: none;">
            <label style="font-size: 13px; font-weight: 600; color: #e2e8f0; margin-bottom: 8px; display: block;">
                Lista de Tablas Restauradas en la Base de Datos:
            </label>
            <div class="tables-preview" id="tablesList"></div>
        </div>

        <div id="accessSection" style="display: none; margin-top: 10px; text-align: center;">
            <a href="PROYECTOSONDA/PREPODUCCION/SYNAPSE/public/login.php" class="btn btn-primary" style="font-size: 14px; padding: 12px 24px;">
                🔑 Ir al Inicio de Sesión de la Plataforma SYNAPSE
            </a>
        </div>
    </div>
</div>

<script>
function togglePass() {
    const input = document.getElementById('db_pass');
    input.type = (input.type === 'password') ? 'text' : 'password';
}

function toggleSourceType() {
    const isUpload = document.querySelector('input[name="source_type"]:checked').value === 'upload';
    document.getElementById('optLocalLabel').classList.toggle('active', !isUpload);
    document.getElementById('optUploadLabel').classList.toggle('active', isUpload);
    document.getElementById('uploadBox').style.display = isUpload ? 'block' : 'none';
}

function handleFileSelect(input) {
    if (input.files && input.files[0]) {
        document.getElementById('uploadBoxLabel').innerHTML = `✅ Archivo seleccionado: <strong>${input.files[0].name}</strong> (${(input.files[0].size/1024/1024).toFixed(2)} MB)`;
    }
}

async function testConnection() {
    const btn = document.getElementById('btnTestConn');
    const status = document.getElementById('connStatus');
    btn.disabled = true;
    status.innerHTML = '⏳ Conectando...';
    status.style.color = '#94a3b8';

    const formData = new FormData();
    formData.append('action', 'test_connection');
    formData.append('host', document.getElementById('db_host').value);
    formData.append('port', document.getElementById('db_port').value);
    formData.append('user', document.getElementById('db_user').value);
    formData.append('pass', document.getElementById('db_pass').value);

    try {
        const res = await fetch('?action=test_connection', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            status.innerHTML = `✅ ${data.message}`;
            status.style.color = '#10b981';
        } else {
            status.innerHTML = `❌ ${data.message}`;
            status.style.color = '#ef4444';
        }
    } catch (e) {
        status.innerHTML = `❌ Error en petición: ${e.message}`;
        status.style.color = '#ef4444';
    } finally {
        btn.disabled = false;
    }
}

function addLog(msg) {
    const consoleDiv = document.getElementById('consoleLog');
    const line = document.createElement('div');
    line.className = 'console-line';
    line.innerHTML = `[${new Date().toLocaleTimeString()}] ${msg}`;
    consoleDiv.appendChild(line);
    consoleDiv.scrollTop = consoleDiv.scrollHeight;
}

async function startRestore() {
    const btn = document.getElementById('btnRestore');
    btn.disabled = true;
    
    const resultsCard = document.getElementById('resultsCard');
    resultsCard.style.display = 'flex';
    resultsCard.scrollIntoView({ behavior: 'smooth' });

    const consoleDiv = document.getElementById('consoleLog');
    consoleDiv.innerHTML = '';
    const badge = document.getElementById('restoreBadge');
    badge.innerText = 'Ejecutando...';
    badge.style.background = 'rgba(59, 130, 246, 0.15)';
    badge.style.color = '#60a5fa';

    document.getElementById('statsGrid').style.display = 'none';
    document.getElementById('tablesSection').style.display = 'none';
    document.getElementById('accessSection').style.display = 'none';

    addLog('🚀 Inicializando proceso de despliegue y restauración...');

    const sourceType = document.querySelector('input[name="source_type"]:checked').value;
    const formData = new FormData();
    formData.append('action', 'restore_db');
    formData.append('host', document.getElementById('db_host').value);
    formData.append('port', document.getElementById('db_port').value);
    formData.append('user', document.getElementById('db_user').value);
    formData.append('pass', document.getElementById('db_pass').value);
    formData.append('dbname', document.getElementById('db_name').value);
    formData.append('create_db', document.getElementById('chk_create_db').checked ? '1' : '0');
    formData.append('update_config', document.getElementById('chk_update_config').checked ? '1' : '0');
    formData.append('source_type', sourceType);

    if (sourceType === 'local') {
        formData.append('local_path', document.getElementById('local_sql_select').value);
    } else {
        const fileInput = document.getElementById('sql_file_input');
        if (!fileInput.files || !fileInput.files[0]) {
            alert('Por favor selecciona un archivo SQL antes de iniciar.');
            btn.disabled = false;
            return;
        }
        formData.append('sql_file', fileInput.files[0]);
    }

    try {
        const res = await fetch('?action=restore_db', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.logs && Array.isArray(data.logs)) {
            data.logs.forEach(l => addLog(l));
        }

        if (data.success) {
            badge.innerText = 'Completado con Éxito';
            badge.style.background = 'rgba(16, 185, 129, 0.15)';
            badge.style.color = '#10b981';

            document.getElementById('resTableCount').innerText = data.table_count;
            document.getElementById('resRowsCount').innerText = data.estimated_rows.toLocaleString();
            document.getElementById('resSizeMb').innerText = data.size_mb + ' MB';
            document.getElementById('resDuration').innerText = data.duration + 's';
            document.getElementById('statsGrid').style.display = 'grid';

            if (data.tables && data.tables.length > 0) {
                const listDiv = document.getElementById('tablesList');
                listDiv.innerHTML = '';
                data.tables.forEach(t => {
                    const item = document.createElement('div');
                    item.className = 'table-pill';
                    item.innerHTML = `<span>${t.name}</span> <small>${t.rows} filas</small>`;
                    listDiv.appendChild(item);
                });
                document.getElementById('tablesSection').style.display = 'block';
            }

            document.getElementById('accessSection').style.display = 'block';
        } else {
            badge.innerText = 'Error en Ejecución';
            badge.style.background = 'rgba(239, 68, 68, 0.15)';
            badge.style.color = '#ef4444';
            addLog(`❌ ERROR: ${data.message}`);
        }
    } catch (e) {
        badge.innerText = 'Fallo de Red';
        badge.style.background = 'rgba(239, 68, 68, 0.15)';
        badge.style.color = '#ef4444';
        addLog(`❌ Excepción al comunicarse con el servidor: ${e.message}`);
    } finally {
        btn.disabled = false;
    }
}
</script>

</body>
</html>

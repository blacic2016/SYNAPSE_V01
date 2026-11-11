<?php
/**
 * SYNAPSE - GESTOR Y RESTAURADOR DE BASE DE DATOS (DB MANAGER)
 * Permite:
 * 1. Generar y actualizar el respaldo SQL completo hasta el día de hoy (mysqldump).
 * 2. Descargar el archivo SQL de respaldo.
 * 3. Crear una base de datos nueva o restaurar respaldos locales / subidos desde el navegador.
 * 4. Consola SQL interactiva para realizar cambios y consultas directas en la base de datos.
 * 5. Explorador de métricas y tablas maestras.
 * Ubicación: public/db_manager.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

// Verificación de Acceso: Requiere login y rol SUPER_ADMIN o Token técnico
$token_valido = false;
if (defined('SECURITY_TOKEN') && isset($_REQUEST['token']) && $_REQUEST['token'] === SECURITY_TOKEN) {
    $token_valido = true;
}

if (!$token_valido) {
    require_login();
    if (!has_role('SUPER_ADMIN')) {
        header("Location: dashboard.php");
        exit;
    }
}

$pdo = getPDO();

// =========================================================================
// MANEJADOR DE PETICIONES AJAX
// =========================================================================
$action = $_GET['action'] ?? $_POST['action'] ?? null;

if ($action) {
    header('Content-Type: application/json; charset=utf-8');

    // 1. Generar / Actualizar Respaldo SQL al día de hoy
    if ($action === 'generate_backup') {
        try {
            $host = DB_CONFIG['host'];
            $user = DB_CONFIG['user'];
            $pass = DB_CONFIG['password'];
            $dbname = DB_CONFIG['database'];

            $backupDir = ROOT_PATH . '/storage/backups';
            if (!is_dir($backupDir)) {
                @mkdir($backupDir, 0777, true);
                @chmod($backupDir, 0777);
            }

            $dumpFile = $backupDir . '/CMDBVilaseca2_latest.sql';
            $startTime = microtime(true);

            $passArg = ($pass !== '') ? "-p" . escapeshellarg($pass) : "";
            $cmd = "mysqldump -h " . escapeshellarg($host) . " -u " . escapeshellarg($user) . " {$passArg} --routines --triggers --single-transaction " . escapeshellarg($dbname) . " > " . escapeshellarg($dumpFile) . " 2>&1";

            $output = [];
            $retCode = 0;
            exec($cmd, $output, $retCode);

            if ($retCode !== 0 || !file_exists($dumpFile) || filesize($dumpFile) < 1000) {
                throw new Exception("Error al ejecutar mysqldump: " . implode(" ", $output));
            }

            $duration = round(microtime(true) - $startTime, 2);
            $sizeBytes = filesize($dumpFile);
            $sizeMb = round($sizeBytes / 1024 / 1024, 2);
            $dateNow = date('Y-m-d H:i:s');

            // Copiar a BACK si existe la ruta
            $backDir = '/var/www/html/PROYECTOSONDA/BACK/SYNAPSE/storage/backups';
            if (is_dir($backDir)) {
                @copy($dumpFile, $backDir . '/CMDBVilaseca2_latest.sql');
            }

            // Actualizar status JSON
            $statusMeta = [
                'last_backup_date' => $dateNow,
                'timestamp' => time(),
                'status' => 'EXITOSO',
                'status_code' => 'success',
                'duration_seconds' => $duration,
                'file' => 'storage/backups/CMDBVilaseca2_latest.sql',
                'size_mb' => $sizeMb,
                'executed_by' => current_user()['username'] ?? 'superadmin',
                'message' => "Respaldo SQL actualizado con éxito ($sizeMb MB en {$duration}s al $dateNow)."
            ];
            @file_put_contents(STORAGE_DIR . '/backup_status.json', json_encode($statusMeta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            echo json_encode([
                'success' => true,
                'message' => "Respaldo SQL actualizado con éxito al día de hoy ({$dateNow}).",
                'size_mb' => $sizeMb,
                'duration' => $duration,
                'date' => $dateNow
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // 2. Descargar archivo SQL
    if ($action === 'download_backup') {
        $file = ROOT_PATH . '/storage/backups/CMDBVilaseca2_latest.sql';
        if (!file_exists($file)) {
            die("Archivo no encontrado");
        }
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }

    // 3. Ejecutar Consulta SQL Directa (Consola de BD)
    if ($action === 'execute_sql') {
        $sql = trim($_POST['sql_query'] ?? '');
        if (empty($sql)) {
            echo json_encode(['success' => false, 'error' => 'La consulta SQL no puede estar vacía.']);
            exit;
        }

        try {
            $startTime = microtime(true);
            $stmt = $pdo->query($sql);
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            if ($stmt->columnCount() > 0) {
                // Es un SELECT / SHOW / DESCRIBE
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $columns = !empty($rows) ? array_keys($rows[0]) : [];
                echo json_encode([
                    'success' => true,
                    'type' => 'query',
                    'columns' => $columns,
                    'rows' => array_slice($rows, 0, 500),
                    'total_rows' => count($rows),
                    'duration_ms' => $duration
                ]);
            } else {
                // Es un INSERT / UPDATE / DELETE / ALTER / CREATE
                $affected = $stmt->rowCount();
                echo json_encode([
                    'success' => true,
                    'type' => 'exec',
                    'affected_rows' => $affected,
                    'message' => "Sentencia ejecutada correctamente ({$affected} filas afectadas).",
                    'duration_ms' => $duration
                ]);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // 4. Listar Archivos SQL Disponibles
    if ($action === 'scan_sql_files') {
        $files = [];
        $candidates = [
            ROOT_PATH . '/storage/backups/CMDBVilaseca2_latest.sql',
            ROOT_PATH . '/full_schema.sql',
            ROOT_PATH . '/vilaseca09042026.sql',
            '/var/www/html/PROYECTOSONDA/BACK/SYNAPSE/storage/backups/CMDBVilaseca2_latest.sql'
        ];

        foreach ($candidates as $c) {
            if (file_exists($c) && is_file($c)) {
                $files[] = [
                    'name' => basename($c),
                    'path' => $c,
                    'size_mb' => round(filesize($c) / 1024 / 1024, 2),
                    'mtime' => date('Y-m-d H:i:s', filemtime($c)),
                    'is_latest' => (basename($c) === 'CMDBVilaseca2_latest.sql')
                ];
            }
        }
        echo json_encode(['success' => true, 'files' => $files]);
        exit;
    }

    // 5. Restaurar / Crear Base de Datos
    if ($action === 'restore_db') {
        $host = trim($_POST['host'] ?? DB_CONFIG['host']);
        $port = (int)($_POST['port'] ?? 3306);
        $user = trim($_POST['user'] ?? DB_CONFIG['user']);
        $pass = $_POST['pass'] ?? DB_CONFIG['password'];
        $dbname = trim($_POST['dbname'] ?? DB_CONFIG['database']);
        $createDb = !empty($_POST['create_db']);
        $sourceType = $_POST['source_type'] ?? 'local';
        $localPath = $_POST['local_path'] ?? (ROOT_PATH . '/storage/backups/CMDBVilaseca2_latest.sql');

        if (empty($dbname) || !preg_match('/^[a-zA-Z0-9_\-]+$/', $dbname)) {
            echo json_encode(['success' => false, 'error' => 'Nombre de base de datos inválido.']);
            exit;
        }

        $sqlFilePath = null;
        if ($sourceType === 'upload' && isset($_FILES['sql_file']) && $_FILES['sql_file']['error'] === UPLOAD_ERR_OK) {
            $sqlFilePath = $_FILES['sql_file']['tmp_name'];
        } elseif ($sourceType === 'local' && file_exists($localPath)) {
            $sqlFilePath = $localPath;
        }

        if (!$sqlFilePath || !file_exists($sqlFilePath)) {
            echo json_encode(['success' => false, 'error' => 'No se encontró el archivo SQL para restaurar.']);
            exit;
        }

        try {
            $dsnNoDb = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdoRaw = new PDO($dsnNoDb, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10]);

            if ($createDb) {
                $pdoRaw->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
            }

            // Ejecución vía CLI mysql para velocidad instantánea
            $passArg = ($pass !== '') ? "-p" . escapeshellarg($pass) : "";
            $cmd = "mysql -h " . escapeshellarg($host) . " -P " . escapeshellarg($port) . " -u " . escapeshellarg($user) . " {$passArg} " . escapeshellarg($dbname) . " < " . escapeshellarg($sqlFilePath) . " 2>&1";

            $execOut = [];
            $execRet = 0;
            exec($cmd, $execOut, $execRet);

            if ($execRet !== 0) {
                throw new Exception("Error al restaurar: " . implode(" ", $execOut));
            }

            // Contar tablas restauradas
            $pdoRestored = new PDO("mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4", $user, $pass);
            $stmtCount = $pdoRestored->query("SHOW TABLES");
            $tablesCount = count($stmtCount->fetchAll());

            echo json_encode([
                'success' => true,
                'message' => "Base de datos '{$dbname}' restaurada exitosamente con {$tablesCount} tablas completas.",
                'tables_count' => $tablesCount
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

// Obtener info del último backup
$latestBackupPath = ROOT_PATH . '/storage/backups/CMDBVilaseca2_latest.sql';
$hasBackup = file_exists($latestBackupPath);
$backupSizeMb = $hasBackup ? round(filesize($latestBackupPath) / 1024 / 1024, 2) : 0;
$backupMtime = $hasBackup ? date('Y-m-d H:i:s', filemtime($latestBackupPath)) : 'No generado';

// Métricas de tablas actuales
$stmtTables = $pdo->query("SELECT table_name AS name, engine, table_rows AS rows_cnt, round(((data_length + index_length) / 1024 / 1024), 2) AS size_mb FROM information_schema.TABLES WHERE table_schema = DATABASE() ORDER BY table_name ASC");
$allTables = $stmtTables->fetchAll(PDO::FETCH_ASSOC);

$totalSizeMb = array_sum(array_column($allTables, 'size_mb'));
$totalRows = array_sum(array_column($allTables, 'rows_cnt'));

$page_title = "Gestor y Respaldo de Base de Datos";
include 'partials/header.php';
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-navy font-weight-bold">
                        <i class="fas fa-database text-warning mr-2"></i> Gestor y Respaldo de Base de Datos
                    </h1>
                </div>
                <div class="col-sm-6 text-right">
                    <button type="button" class="btn btn-warning text-dark font-weight-bold shadow-sm" onclick="triggerUpdateBackup()">
                        <i class="fas fa-sync-alt mr-1"></i> Actualizar Respaldo SQL al Día de Hoy
                    </button>
                    <a href="db_manager.php?action=download_backup" class="btn btn-success font-weight-bold shadow-sm ml-2">
                        <i class="fas fa-download mr-1"></i> Descargar SQL (.sql)
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">

            <!-- TARJETAS DE ESTADO SUPERIOR -->
            <div class="row">
                <div class="col-md-3 col-sm-6 col-12">
                    <div class="info-box shadow-xs">
                        <span class="info-box-icon bg-navy"><i class="fas fa-server"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted font-weight-bold">Base de Datos Activa</span>
                            <span class="info-box-number text-navy font-weight-bold"><?php echo DB_CONFIG['database']; ?></span>
                            <small class="text-muted"><?php echo DB_CONFIG['host']; ?> (MySQL)</small>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6 col-12">
                    <div class="info-box shadow-xs">
                        <span class="info-box-icon bg-primary"><i class="fas fa-table"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted font-weight-bold">Tablas Maestras</span>
                            <span class="info-box-number text-primary font-weight-bold"><?php echo count($allTables); ?> Tablas</span>
                            <small class="text-muted"><?php echo number_format($totalRows); ?> registros totales</small>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6 col-12">
                    <div class="info-box shadow-xs">
                        <span class="info-box-icon bg-info"><i class="fas fa-hdd"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted font-weight-bold">Tamaño en Disco</span>
                            <span class="info-box-number text-info font-weight-bold"><?php echo round($totalSizeMb, 2); ?> MB</span>
                            <small class="text-muted">Motor InnoDB</small>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6 col-12">
                    <div class="info-box shadow-xs">
                        <span class="info-box-icon bg-success"><i class="fas fa-file-archive"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted font-weight-bold">Último Respaldo SQL</span>
                            <span class="info-box-number text-success font-weight-bold" id="lbl_backup_size"><?php echo $backupSizeMb; ?> MB</span>
                            <small class="text-muted" id="lbl_backup_date"><i class="far fa-clock mr-1"></i><?php echo $backupMtime; ?></small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PESTAÑAS DE CONTROL Y GESTIÓN -->
            <div class="card card-navy card-outline card-tabs shadow-sm">
                <div class="card-header p-0 pt-1 border-bottom-0">
                    <ul class="nav nav-tabs" id="dbManagerTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active font-weight-bold" id="tab-backup-restore-nav" data-toggle="pill" href="#tab-backup-restore" role="tab">
                                <i class="fas fa-cloud-upload-alt text-primary mr-1"></i> Respaldo &amp; Restauración
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link font-weight-bold" id="tab-sql-console-nav" data-toggle="pill" href="#tab-sql-console" role="tab">
                                <i class="fas fa-terminal text-success mr-1"></i> Consola SQL Directa
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link font-weight-bold" id="tab-tables-metrics-nav" data-toggle="pill" href="#tab-tables-metrics" role="tab">
                                <i class="fas fa-list text-info mr-1"></i> Tablas de la Base de Datos (<?php echo count($allTables); ?>)
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="card-body">
                    <div class="tab-content" id="dbManagerTabContent">

                        <!-- PESTAÑA 1: RESPALDO Y RESTAURACIÓN -->
                        <div class="tab-pane fade show active" id="tab-backup-restore" role="tabpanel">
                            <div class="row">
                                <!-- Columna Izquierda: Generar y Descargar Respaldo -->
                                <div class="col-md-6 mb-4">
                                    <div class="card border shadow-2xs h-100">
                                        <div class="card-header bg-light py-2">
                                            <h6 class="font-weight-bold m-0 text-navy">
                                                <i class="fas fa-save text-success mr-2"></i> Actualizar Respaldo SQL al Día de Hoy
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <p class="text-muted small">
                                                Genera un volcado completo de la base de datos <strong><?php echo DB_CONFIG['database']; ?></strong> utilizando <code>mysqldump</code> con todas las tablas, triggers, procedimientos y datos actualizados hasta el momento presente.
                                            </p>

                                            <div class="p-3 bg-light rounded border mb-3">
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span class="small font-weight-bold">Archivo de Destino:</span>
                                                    <code class="small">storage/backups/CMDBVilaseca2_latest.sql</code>
                                                </div>
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span class="small font-weight-bold">Tamaño Actual:</span>
                                                    <span class="badge badge-success" id="box_backup_size"><?php echo $backupSizeMb; ?> MB</span>
                                                </div>
                                                <div class="d-flex justify-content-between">
                                                    <span class="small font-weight-bold">Última Modificación:</span>
                                                    <span class="small text-muted" id="box_backup_date"><?php echo $backupMtime; ?></span>
                                                </div>
                                            </div>

                                            <div class="d-flex" style="gap:10px;">
                                                <button type="button" class="btn btn-warning text-dark font-weight-bold px-3 shadow-2xs" id="btn_update_dump" onclick="triggerUpdateBackup()">
                                                    <i class="fas fa-sync-alt mr-1"></i> Volcar Base de Datos Ahora
                                                </button>
                                                <a href="db_manager.php?action=download_backup" class="btn btn-outline-success font-weight-bold px-3 shadow-2xs">
                                                    <i class="fas fa-download mr-1"></i> Descargar SQL
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Columna Derecha: Restaurar / Crear Base de Datos -->
                                <div class="col-md-6 mb-4">
                                    <div class="card border shadow-2xs h-100">
                                        <div class="card-header bg-light py-2">
                                            <h6 class="font-weight-bold m-0 text-navy">
                                                <i class="fas fa-upload text-primary mr-2"></i> Restaurar o Crear Base de Datos
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <form id="form_restore_db">
                                                <div class="form-group mb-2">
                                                    <label class="small font-weight-bold text-dark mb-1">Base de Datos de Destino:</label>
                                                    <input type="text" class="form-control form-control-sm font-weight-bold" id="rest_dbname" value="<?php echo DB_CONFIG['database']; ?>" required>
                                                </div>

                                                <div class="custom-control custom-checkbox mb-3">
                                                    <input type="checkbox" class="custom-control-input" id="rest_create_db" checked>
                                                    <label class="custom-control-label small font-weight-bold" for="rest_create_db">
                                                        Crear la base de datos si no existe (<code>CREATE DATABASE IF NOT EXISTS</code>)
                                                    </label>
                                                </div>

                                                <div class="form-group mb-2">
                                                    <label class="small font-weight-bold text-dark mb-1">Origen del Archivo SQL:</label>
                                                    <select class="custom-select custom-select-sm" id="rest_source_type" onchange="toggleRestoreSource(this.value)">
                                                        <option value="local">Respaldo Local del Servidor (CMDBVilaseca2_latest.sql)</option>
                                                        <option value="upload">Subir archivo SQL desde mi equipo (.sql)</option>
                                                    </select>
                                                </div>

                                                <div class="form-group mb-3" id="group_local_file">
                                                    <label class="small font-weight-bold text-muted mb-1">Archivo Local Detectado:</label>
                                                    <input type="text" class="form-control form-control-sm bg-light" id="rest_local_path" value="<?php echo $latestBackupPath; ?>" readonly>
                                                </div>

                                                <div class="form-group mb-3" id="group_upload_file" style="display:none;">
                                                    <label class="small font-weight-bold text-muted mb-1">Seleccionar archivo SQL (.sql):</label>
                                                    <input type="file" class="form-control-file border rounded p-1" id="rest_upload_file" accept=".sql">
                                                </div>

                                                <button type="button" class="btn btn-danger font-weight-bold px-4 shadow-2xs" id="btn_run_restore" onclick="triggerRestoreDatabase()">
                                                    <i class="fas fa-history mr-1"></i> Iniciar Restauración de BD
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- PESTAÑA 2: CONSOLA SQL DIRECTA -->
                        <div class="tab-pane fade" id="tab-sql-console" role="tabpanel">
                            <div class="callout callout-info py-2 px-3 mb-3">
                                <h6 class="font-weight-bold text-navy m-0"><i class="fas fa-terminal mr-2"></i> Consola SQL Interactiva</h6>
                                <small class="text-muted">Ejecute consultas directas de lectura o modificación sobre la base de datos activa (<code>SELECT</code>, <code>SHOW</code>, <code>UPDATE</code>, <code>ALTER</code>). Las consultas están restringidas a usuarios con privilegios de administrador.</small>
                            </div>

                            <div class="form-group mb-2">
                                <textarea id="txt_sql_query" class="form-control font-monospace" rows="4" placeholder="SELECT * FROM manual_portmap_surveys ORDER BY id DESC LIMIT 10;" style="font-family: monospace; font-size: 13px;"></textarea>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="d-flex" style="gap:6px;">
                                    <button type="button" class="btn btn-primary font-weight-bold px-4 shadow-2xs" onclick="executeSqlQuery()">
                                        <i class="fas fa-play mr-1"></i> Ejecutar Consulta (F9)
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setQueryTemplate('SELECT * FROM manual_portmap_surveys ORDER BY id DESC LIMIT 10;')">
                                        Surveys
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setQueryTemplate('SELECT * FROM ci_instances ORDER BY id DESC LIMIT 10;')">
                                        CIs
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setQueryTemplate('SHOW TABLES;')">
                                        Ver Tablas
                                    </button>
                                </div>
                                <span class="small text-muted font-weight-bold" id="lbl_sql_stats"></span>
                            </div>

                            <!-- Resultados de la Consulta -->
                            <div id="sql_results_container" style="display:none;">
                                <div class="table-responsive border rounded bg-white shadow-2xs" style="max-height: 480px; overflow-y: auto;">
                                    <table class="table table-sm table-striped table-hover mb-0 text-nowrap" style="font-size: 11.5px;" id="tbl_sql_results">
                                        <thead class="bg-navy text-white position-sticky" style="top: 0;" id="tbl_sql_head"></thead>
                                        <tbody id="tbl_sql_body"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- PESTAÑA 3: MÉTRICAS Y LISTADO DE TABLAS -->
                        <div class="tab-pane fade" id="tab-tables-metrics" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="small font-weight-bold text-navy">Total: <strong><?php echo count($allTables); ?> tablas</strong> registradas en <code><?php echo DB_CONFIG['database']; ?></code></span>
                                <input type="text" id="filter_tables_input" class="form-control form-control-sm" placeholder="Buscar tabla..." style="max-width: 260px;" oninput="filterTablesList(this.value)">
                            </div>

                            <div class="table-responsive border rounded bg-white shadow-2xs">
                                <table class="table table-sm table-striped table-hover mb-0" id="tbl_master_tables" style="font-size: 12px;">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Nombre de la Tabla</th>
                                            <th>Motor</th>
                                            <th class="text-right">Registros Est.</th>
                                            <th class="text-right">Tamaño en Disco</th>
                                            <th class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($allTables as $idx => $t): ?>
                                        <tr class="tbl-row-item">
                                            <td><?php echo ($idx + 1); ?></td>
                                            <td><strong class="text-dark tbl-name"><?php echo htmlspecialchars($t['name']); ?></strong></td>
                                            <td><span class="badge badge-light border"><?php echo htmlspecialchars($t['engine'] ?? 'InnoDB'); ?></span></td>
                                            <td class="text-right font-weight-bold"><?php echo number_format($t['rows_cnt'] ?? 0); ?></td>
                                            <td class="text-right text-muted"><?php echo $t['size_mb']; ?> MB</td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-xs btn-outline-primary font-weight-bold px-2" onclick="inspectTable('<?php echo htmlspecialchars($t['name']); ?>')">
                                                    <i class="fas fa-search mr-1"></i> Consultar
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<script>
function toggleRestoreSource(val) {
    if (val === 'upload') {
        document.getElementById('group_upload_file').style.display = 'block';
        document.getElementById('group_local_file').style.display = 'none';
    } else {
        document.getElementById('group_upload_file').style.display = 'none';
        document.getElementById('group_local_file').style.display = 'block';
    }
}

async function triggerUpdateBackup() {
    const btn = document.getElementById('btn_update_dump');
    if (btn) btn.disabled = true;

    toastr.info('Generando volcado SQL completo con mysqldump...');

    try {
        const resp = await fetch('db_manager.php?action=generate_backup', { method: 'POST' });
        const res = await resp.json();
        if (res.success) {
            Swal.fire('Respaldo Actualizado', res.message + ` (${res.size_mb} MB en ${res.duration}s)`, 'success');
            document.getElementById('lbl_backup_size').textContent = res.size_mb + ' MB';
            document.getElementById('lbl_backup_date').innerHTML = '<i class="far fa-clock mr-1"></i>' + res.date;
            document.getElementById('box_backup_size').textContent = res.size_mb + ' MB';
            document.getElementById('box_backup_date').textContent = res.date;
        } else {
            Swal.fire('Error al Respaldar', res.error || 'Ocurrió un error inesperado.', 'error');
        }
    } catch (e) {
        toastr.error('Error de comunicación al generar el respaldo.');
    } finally {
        if (btn) btn.disabled = false;
    }
}

async function triggerRestoreDatabase() {
    const dbname = document.getElementById('rest_dbname').value.trim();
    const createDb = document.getElementById('rest_create_db').checked ? 1 : 0;
    const sourceType = document.getElementById('rest_source_type').value;

    const confirmRes = await Swal.fire({
        title: '¿Confirmar Restauración de BD?',
        text: `Esta acción importará las tablas y datos sobre la base de datos '${dbname}'.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, Restaurar Ahora',
        cancelButtonText: 'Cancelar'
    });

    if (!confirmRes.isConfirmed) return;

    const formData = new FormData();
    formData.append('action', 'restore_db');
    formData.append('dbname', dbname);
    formData.append('create_db', createDb);
    formData.append('source_type', sourceType);

    if (sourceType === 'local') {
        formData.append('local_path', document.getElementById('rest_local_path').value);
    } else {
        const fileInput = document.getElementById('rest_upload_file');
        if (!fileInput.files || fileInput.files.length === 0) {
            toastr.warning('Por favor seleccione un archivo .sql para subir.');
            return;
        }
        formData.append('sql_file', fileInput.files[0]);
    }

    const btn = document.getElementById('btn_run_restore');
    btn.disabled = true;
    toastr.info('Ejecutando importación SQL en base de datos. Por favor espere...');

    try {
        const resp = await fetch('db_manager.php', { method: 'POST', body: formData });
        const res = await resp.json();
        if (res.success) {
            Swal.fire('Restauración Exitosa', res.message, 'success');
        } else {
            Swal.fire('Error en Restauración', res.error || 'Fallo al procesar el archivo SQL.', 'error');
        }
    } catch (e) {
        toastr.error('Error al comunicarse con el servidor durante la restauración.');
    } finally {
        btn.disabled = false;
    }
}

function setQueryTemplate(q) {
    document.getElementById('txt_sql_query').value = q;
    executeSqlQuery();
}

function inspectTable(tableName) {
    $('#tab-sql-console-nav').tab('show');
    setQueryTemplate(`SELECT * FROM \`${tableName}\` LIMIT 20;`);
}

async function executeSqlQuery() {
    const q = document.getElementById('txt_sql_query').value.trim();
    if (!q) {
        toastr.warning('Ingrese una consulta SQL para ejecutar.');
        return;
    }

    const container = document.getElementById('sql_results_container');
    const thead = document.getElementById('tbl_sql_head');
    const tbody = document.getElementById('tbl_sql_body');
    const stats = document.getElementById('lbl_sql_stats');

    stats.textContent = 'Ejecutando...';

    const formData = new FormData();
    formData.append('action', 'execute_sql');
    formData.append('sql_query', q);

    try {
        const resp = await fetch('db_manager.php', { method: 'POST', body: formData });
        const res = await resp.json();

        if (res.success) {
            if (res.type === 'query') {
                stats.textContent = `${res.total_rows} filas obtenidas en ${res.duration_ms} ms`;
                
                // Cabeceras
                let thHtml = '<tr>';
                res.columns.forEach(col => thHtml += `<th>${escapeHtml(col)}</th>`);
                thHtml += '</tr>';
                thead.innerHTML = thHtml;

                // Filas
                let tbHtml = '';
                res.rows.forEach(row => {
                    tbHtml += '<tr>';
                    res.columns.forEach(col => {
                        const val = row[col];
                        tbHtml += `<td>${val !== null ? escapeHtml(String(val)) : '<em class="text-muted">NULL</em>'}</td>`;
                    });
                    tbHtml += '</tr>';
                });
                tbody.innerHTML = tbHtml;
                container.style.display = 'block';
            } else {
                stats.textContent = `${res.message} (${res.duration_ms} ms)`;
                thead.innerHTML = '';
                tbody.innerHTML = '';
                container.style.display = 'none';
                toastr.success(res.message);
            }
        } else {
            stats.textContent = 'Error al ejecutar';
            Swal.fire('Error SQL', res.error, 'error');
        }
    } catch (e) {
        toastr.error('Error de red al ejecutar consulta SQL.');
    }
}

function filterTablesList(query) {
    const q = query.toLowerCase().trim();
    const rows = document.querySelectorAll('.tbl-row-item');
    rows.forEach(r => {
        const name = r.querySelector('.tbl-name')?.textContent.toLowerCase() || '';
        r.style.display = name.includes(q) ? '' : 'none';
    });
}

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    })[m]);
}
</script>

<?php include 'partials/footer.php'; ?>

<?php
/**
 * Dashboard Simplificado y Contenido - CMDB VILASECA
 * Ubicación: /var/www/html/VILASECA/CMDBPRnew/public/dashboard.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/zabbix_api.php';

require_login(); 

$pdo = getPDO();
$page_title = 'Dashboard General - Resumen General';

if (!$pdo) {
    die("Error crítico: No se pudo conectar a la base de datos.");
}

$tables = listSheetTables();
$total_sheets = count($tables);

// 1. Estadísticas Globales Simplificadas
$total_items = 0;
$monitored = 0;
$table_data = [];

foreach ($tables as $t) {
    $cols = getTableColumns($t);
    $hasZabbix = in_array('zabbix_host_id', $cols);
    
    $count = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
    $total_items += $count;
    
    $table_monitored = 0;
    if ($hasZabbix) {
        $table_monitored = $pdo->query("SELECT COUNT(*) FROM `$t` WHERE zabbix_host_id IS NOT NULL")->fetchColumn();
        $monitored += $table_monitored;
    }

    $table_data[] = [
        'id' => $t,
        'name' => ucfirst(str_replace('sheet_', '', $t)),
        'count' => $count,
        'monitored' => $table_monitored
    ];
}

// 2. Monitoreo Vivo
$problems_by_severity = [];
try {
    $res_triggers = call_zabbix_api('trigger.get', [
        'output' => ['priority'],
        'only_true' => 1,
        'monitored' => 1
    ]);
    if (!isset($res_triggers['error'])) {
        $severities = [
            2 => ['name' => 'Advertencia',   'count' => 0, 'bg' => 'warning'],
            3 => ['name' => 'Promedio',      'count' => 0, 'bg' => 'orange'],
            4 => ['name' => 'Alta',          'count' => 0, 'bg' => 'danger'],
            5 => ['name' => 'Desastre',      'count' => 0, 'bg' => 'maroon']
        ];
        foreach ($res_triggers['result'] as $trigger) {
            $p = (int)$trigger['priority'];
            if (isset($severities[$p])) $severities[$p]['count']++;
        }
        $problems_by_severity = $severities;
    }
} catch (Exception $e) {}

// 3. Recientes
$recentImports = $pdo->query("SELECT filename, sheet_name, added_count, created_at 
                              FROM import_logs 
                              ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/partials/header.php'; 
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
<style>
    .minimal-card { border-radius: 10px; border: 1px solid #eee; background: #fff; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
    .table-sm td, .table-sm th { font-size: 0.85rem; padding: 10px; }
    .badge-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 5px; }
    .summary-box { padding: 20px; text-align: center; border-right: 1px solid #eee; }
    .summary-box:last-child { border-right: none; }
</style>

<div class="container-fluid pt-4">
    
    <!-- Fila 1: Resumen Numérico -->
    <div class="minimal-card mb-4 animate__animated animate__fadeIn">
        <div class="row no-gutters">
            <div class="col-md-3 summary-box">
                <h6 class="text-muted text-uppercase small">Categorías</h6>
                <h3 class="font-weight-bold"><?php echo $total_sheets; ?></h3>
            </div>
            <div class="col-md-3 summary-box">
                <h6 class="text-muted text-uppercase small">Total Activos</h6>
                <h3 class="font-weight-bold text-primary"><?php echo number_format($total_items); ?></h3>
            </div>
            <div class="col-md-3 summary-box">
                <h6 class="text-muted text-uppercase small">Monitoreados</h6>
                <h3 class="font-weight-bold text-success"><?php echo number_format($monitored); ?></h3>
            </div>
            <div class="col-md-3 summary-box text-danger">
                <h6 class="text-muted text-uppercase small">Alertas Activas</h6>
                <h3 class="font-weight-bold"><?php echo array_sum(array_column($problems_by_severity, 'count')); ?></h3>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Columna Izquierda: Tabla General de Inventario -->
        <div class="col-lg-8">
            <div class="minimal-card animate__animated animate__fadeInLeft mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 font-weight-bold text-dark">Inventario por Categoría</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="pl-4">Nombre de la Tabla</th>
                                    <th class="text-center">Total Filas</th>
                                    <th class="text-center">Monitoreados</th>
                                    <th class="text-right pr-4">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($table_data as $row): ?>
                                    <tr>
                                        <td class="pl-4 font-weight-bold text-primary">
                                            <i class="fas fa-table mr-2 opacity-5"></i><?php echo $row['name']; ?>
                                        </td>
                                        <td class="text-center"><?php echo number_format($row['count']); ?></td>
                                        <td class="text-center">
                                            <?php if ($row['monitored'] > 0): ?>
                                                <span class="badge badge-success px-2"><?php echo $row['monitored']; ?></span>
                                            <?php else: ?>
                                                <span class="text-muted small">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-right pr-4">
                                            <a href="cmdb.php?name=<?php echo $row['id']; ?>" class="btn btn-xs btn-outline-primary shadow-sm">Ver Datos</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Columna Derecha: Alertas y Fallos -->
        <div class="col-lg-4">
            <div class="minimal-card animate__animated animate__fadeInRight mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 font-weight-bold text-danger"><i class="fas fa-bell mr-2"></i>Monitoreo Crítico</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty(array_filter($problems_by_severity, function($x){return $x['count'] > 0;}))): ?>
                        <div class="p-5 text-center text-success">
                            <i class="fas fa-check-circle fa-3x mb-3"></i>
                            <p class="font-weight-bold">Servicios Operativos</p>
                            <small class="text-muted">No se detectan alertas pendientes.</small>
                        </div>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($problems_by_severity as $sev): if($sev['count'] == 0) continue; ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                    <span><span class="badge-dot bg-<?php echo $sev['bg']; ?>"></span> <?php echo $sev['name']; ?></span>
                                    <span class="font-weight-bold"><?php echo $row['count']; ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="p-3">
                            <a href="problems.php" class="btn btn-sm btn-block btn-danger">Ir al Dashboard Zabbix</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Mini Tabla de Cargas -->
            <div class="minimal-card animate__animated animate__fadeInUp">
                <div class="card-header bg-white py-2">
                    <h6 class="mb-0 font-weight-bold small">Cargas Recientes</h6>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <?php foreach ($recentImports as $ri): ?>
                                <tr>
                                    <td class="small pl-3"><?php echo htmlspecialchars(substr($ri['filename'], 0, 20)); ?>...</td>
                                    <td class="text-right pr-3"><span class="badge badge-light text-muted">+<?php echo $ri['added_count']; ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
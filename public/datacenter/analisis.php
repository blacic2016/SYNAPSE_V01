<?php
/**
 * CMDB VILASECA - Análisis de Capacidad y Disponibilidad de Datacenter (DCIM)
 * Ubicación: /var/www/html/VILASECA/CMDBPRnew/public/datacenter/analisis.php
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/permissions_helper.php';

if (session_status() === PHP_SESSION_NONE) session_start();
require_login();
if (!has_role('SUPER_ADMIN') && !has_module_access('datacenter') && !has_module_access('vilaseca')) {
    header("Location: " . PUBLIC_URL_PREFIX . "/dashboard.php");
    exit();
}
$pdo = getPDO();

$client_filter = trim($_GET['cliente'] ?? $_GET['client'] ?? '');
if (empty($client_filter) && !has_role('SUPER_ADMIN') && has_module_access('vilaseca')) {
    $client_filter = 'VILASECA';
}

// 1. Obtener lista de cuartos filtrados por cliente
$roomsQuery = "SELECT id, name FROM dc_rooms WHERE 1=1";
$roomsParams = [];
if (!empty($client_filter)) {
    $roomsQuery .= " AND (UPPER(client) = UPPER(?) OR client IS NULL OR client = '')";
    $roomsParams[] = $client_filter;
}
$roomsQuery .= " ORDER BY name ASC";
$stmtRooms = $pdo->prepare($roomsQuery);
$stmtRooms->execute($roomsParams);
$rooms = $stmtRooms->fetchAll(PDO::FETCH_ASSOC);

// 2. Determinar cuarto seleccionado
$room_id = (int)($_GET['room_id'] ?? ($rooms[0]['id'] ?? 0));

$room = null;
$racks = [];
$items = [];
$layers = [];
$rack_stats = [];

if ($room_id > 0) {
    // Detalles del cuarto
    $stmt = $pdo->prepare("SELECT * FROM dc_rooms WHERE id = ?");
    $stmt->execute([$room_id]);
    $room = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($room) {
        // Baldosas del cuarto
        $tile_size = (float)($room['tile_size'] ?? 0.6);
        if ($tile_size <= 0) $tile_size = 0.6;
        $width_m = (float)($room['width_meters'] ?? 6.0);
        $length_m = (float)($room['length_meters'] ?? 6.0);
        $tiles_x = (int)floor($width_m / $tile_size);
        $tiles_y = (int)floor($length_m / $tile_size);

        // Racks en el cuarto
        $stmt = $pdo->prepare("SELECT id, name, grid_x, grid_y, width_tiles, depth_tiles, total_u, rotation FROM dc_racks WHERE room_id = ?");
        $stmt->execute([$room_id]);
        $racks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Otros elementos en el cuarto (AACC, UPS, etc.) para dibujar el plano
        $stmt = $pdo->prepare("SELECT * FROM dc_floor_items WHERE room_id = ?");
        $stmt->execute([$room_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Capas
        $layers = $pdo->query("SELECT * FROM dc_floor_layers ORDER BY z_index ASC")->fetchAll(PDO::FETCH_ASSOC);

        // Dispositivos de todos los racks
        $rack_ids = array_column($racks, 'id');
        $devices_by_rack = [];
        if (!empty($rack_ids)) {
            $in_clause = implode(',', array_fill(0, count($rack_ids), '?'));
            $stmt = $pdo->prepare("SELECT id, rack_id, name, start_u, height_u, orientation, details_json FROM dc_rack_devices WHERE rack_id IN ($in_clause)");
            $stmt->execute($rack_ids);
            $all_devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($all_devices as $d) {
                $devices_by_rack[$d['rack_id']][] = $d;
            }
        }

        // Calcular estadísticas de disponibilidad por cada rack
        foreach ($racks as $r) {
            $r_id = $r['id'];
            $total_u = (int)($r['total_u'] ?: 42);
            
            $front_occupied = array_fill(1, $total_u, false);
            $rear_occupied = array_fill(1, $total_u, false);
            
            $pdu_count = 0;
            $total_watts = 0.0;
            $rack_devices = $devices_by_rack[$r_id] ?? [];
            
            foreach ($rack_devices as $d) {
                $details = json_decode($d['details_json'], true) ?: [];
                $depth = $details['depth'] ?? 'full';
                $mounting = $details['mounting'] ?? 'horizontal';
                
                // Sumar Potencia
                $w = 0.0;
                if (!empty($details['watts'])) {
                    $w = (float)$details['watts'];
                } elseif (!empty($details['amps']) && !empty($details['voltage'])) {
                    $w = (float)$details['amps'] * (float)$details['voltage'];
                }
                $total_watts += $w;
                
                // Si es PDU vertical
                if ($mounting === 'vertical_left' || $mounting === 'vertical_right') {
                    $pdu_count++;
                    continue;
                }
                
                $start = (int)$d['start_u'];
                $height = (int)$d['height_u'];
                $end = $start + $height - 1;
                $orientation = $d['orientation'] ?? 'front';
                
                for ($u = $start; $u <= $end; $u++) {
                    if ($u < 1 || $u > $total_u) continue;
                    
                    if ($depth === 'full') {
                        $front_occupied[$u] = true;
                        $rear_occupied[$u] = true;
                    } else {
                        if ($orientation === 'front' || $orientation === 'both') {
                            $front_occupied[$u] = true;
                        }
                        if ($orientation === 'rear' || $orientation === 'both') {
                            $rear_occupied[$u] = true;
                        }
                    }
                }
            }
            
            // Contar Us libres
            $front_free = 0;
            foreach ($front_occupied as $u_occ) {
                if (!$u_occ) $front_free++;
            }
            $rear_free = 0;
            foreach ($rear_occupied as $u_occ) {
                if (!$u_occ) $rear_free++;
            }
            
            $total_capacity = $total_u * 2;
            $total_free = $front_free + $rear_free;
            $percent_free = $total_capacity > 0 ? ($total_free / $total_capacity) * 100 : 100;
            
            // Color según regla del usuario
            if ($percent_free >= 80) {
                $color = '#28a745'; // Green
                $label = 'Excelente (80-100% Libre)';
                $text_color = '#ffffff';
            } elseif ($percent_free >= 60) {
                $color = '#8bc34a'; // Lime Green
                $label = 'Bueno (60-80% Libre)';
                $text_color = '#ffffff';
            } elseif ($percent_free >= 40) {
                $color = '#ffc107'; // Yellow
                $label = 'Intermedio (40-60% Libre)';
                $text_color = '#333333';
            } elseif ($percent_free >= 20) {
                $color = '#ff851b'; // Orange/Tomato
                $label = 'Alto Uso (20-40% Libre)';
                $text_color = '#ffffff';
            } else {
                $color = '#dc3545'; // Red
                $label = 'Crítico (0-20% Libre)';
                $text_color = '#ffffff';
            }
            
            $kw = $total_watts / 1000.0;
            $kva = $total_watts / 900.0; // 0.9 PF
            
            // Preparar listado detallado de dispositivos para JS
            $device_list = [];
            foreach ($rack_devices as $d) {
                $details = json_decode($d['details_json'], true) ?: [];
                $device_list[] = [
                    'name' => $d['name'],
                    'start_u' => $d['start_u'],
                    'height_u' => $d['height_u'],
                    'orientation' => $d['orientation'],
                    'make' => $details['make'] ?? '',
                    'model' => $details['model'] ?? '',
                    'ip' => $details['ip_address'] ?? '',
                    'watts' => !empty($details['watts']) ? (float)$details['watts'] : (!empty($details['amps']) ? ((float)$details['amps'] * (float)(!empty($details['voltage']) ? $details['voltage'] : 220)) : 0),
                    'mounting' => $details['mounting'] ?? 'horizontal',
                    'depth' => $details['depth'] ?? 'full',
                    'outlets_c13' => $details['outlets_c13'] ?? '',
                    'outlets_c19' => $details['outlets_c19'] ?? '',
                    'outlets_nema' => $details['outlets_nema'] ?? ''
                ];
            }
            
            // Ordenar dispositivos por posición U descendente
            usort($device_list, function($a, $b) {
                return (int)$b['start_u'] - (int)$a['start_u'];
            });
            
            $rack_stats[$r_id] = [
                'id' => $r_id,
                'name' => $r['name'],
                'total_u' => $total_u,
                'front_free' => $front_free,
                'front_occupied' => $total_u - $front_free,
                'rear_free' => $rear_free,
                'rear_occupied' => $total_u - $rear_free,
                'pdu_count' => $pdu_count,
                'kw' => round($kw, 2),
                'kva' => round($kva, 2),
                'percent_free' => round($percent_free, 1),
                'percent_occupied' => round(100 - $percent_free, 1),
                'color' => $color,
                'text_color' => $text_color,
                'color_label' => $label,
                'devices' => $device_list
            ];
        }
    }
}

$page_title = 'Análisis de Disponibilidad';
require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
<style>
    .floor-wrapper {
        position: relative;
        display: inline-block;
        background: #f8f9fa;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        padding-bottom: 20px;
        padding-right: 20px;
        user-select: none;
        border: 1px solid #dee2e6;
        border-radius: 8px;
    }
    
    .floor-table {
        border-collapse: collapse;
        table-layout: fixed;
        border: 2px solid #343a40;
    }
    
    .floor-table th, .floor-table td {
        width: 40px; 
        height: 40px;
        border: 1px solid #e9ecef;
        text-align: center;
        vertical-align: middle;
        padding: 0;
        margin: 0;
        box-sizing: border-box;
    }

    .floor-table th {
        background-color: #e9ecef;
        font-weight: bold;
        font-size: 11px;
        color: #495057 !important;
        border: 1px solid #dee2e6;
    }
    
    .floor-table .row-header {
        background-color: #e9ecef;
        width: 30px;
    }

    .racks-layer {
        position: absolute;
        top: 40px;
        left: 30px;
        width: calc(100% - 30px);
        height: calc(100% - 40px);
        pointer-events: none;
    }

    .rack-item, .floor-obj {
        position: absolute;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        font-size: 10px;
        font-weight: bold;
        color: #fff;
        cursor: pointer;
        pointer-events: auto;
        transition: all 0.2s cubic-bezier(0.165, 0.84, 0.44, 1);
        box-sizing: border-box;
        border-radius: 3px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.15);
    }
    
    .rack-item {
        border: 2px solid #222;
        z-index: 10;
    }

    .rack-item:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        z-index: 20;
    }
    
    .rack-item.active-rack {
        border: 3px solid #0056b3 !important;
        box-shadow: 0 0 15px rgba(0, 86, 179, 0.6);
        transform: scale(1.05);
        z-index: 21;
    }

    /* Grayscale/transparency for secondary floor items so Racks pop out */
    .floor-obj { 
        border: 1px solid #adb5bd; 
        background-color: #e9ecef;
        color: #6c757d !important;
        opacity: 0.55; 
        z-index: 5; 
        pointer-events: none; /* No interaction needed for background objects */
    }
    .obj-aacc { background-color: #d1ecf1; border-color: #bee5eb; }
    .obj-ups { background-color: #fff3cd; border-color: #ffeeba; }
    .obj-puerta { background-color: #f8d7da; border-color: #f5c6cb; }

    /* Indicador delantero del Rack */
    .rack-item[data-rot="0"] { border-top: 4px solid #111 !important; }
    .rack-item[data-rot="90"] { border-right: 4px solid #111 !important; }
    .rack-item[data-rot="180"] { border-bottom: 4px solid #111 !important; }
    .rack-item[data-rot="270"] { border-left: 4px solid #111 !important; }

    .vertical-layout > div {
        writing-mode: vertical-lr;
        text-orientation: mixed;
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
        width: 100%;
    }

    /* Premium Legend styling */
    .legend-box {
        width: 18px;
        height: 18px;
        border-radius: 4px;
        display: inline-block;
        vertical-align: middle;
        margin-right: 6px;
        border: 1px solid rgba(0,0,0,0.15);
    }
    
    /* Metrics panel styling */
    .metric-card {
        background: #ffffff;
        border-radius: 12px;
        border: none;
        box-shadow: 0 4px 15px rgba(0,0,0,0.04);
        transition: all 0.3s;
    }
    .metric-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.08);
    }
    
    .panel-details-premium {
        background: #ffffff;
        border-radius: 16px;
        box-shadow: 0 8px 30px rgba(0,0,0,0.06);
        border: 1px solid #eef2f5;
        padding: 24px;
    }

    .progress-custom {
        height: 22px;
        border-radius: 20px;
        background-color: #f1f3f5;
        font-weight: bold;
        font-size: 11px;
        box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
        overflow: hidden;
    }
</style>

<div class="container-fluid pt-3 pb-5">
    <!-- Header -->
    <div class="row mb-4 animate__animated animate__fadeInDown align-items-center">
        <div class="col-md-7">
            <h1 class="h3 font-weight-bold text-dark mb-1"><i class="fas fa-chart-pie text-success mr-2"></i> Análisis de Capacidad y Disponibilidad</h1>
            <p class="text-muted mb-0">Visualización de distribución espacial y nivel de ocupación de Racks en el Datacenter.</p>
        </div>
        <div class="col-md-5 d-flex align-items-center justify-content-md-end flex-wrap" style="gap: 10px;">
            <?php if (!empty($client_filter)): ?>
                <span class="badge badge-warning text-dark font-weight-bold px-3 py-2 shadow-sm">
                    <i class="fas fa-building mr-1"></i>Cliente: <?php echo htmlspecialchars($client_filter); ?>
                </span>
                <?php if (strtoupper($client_filter) === 'VILASECA'): ?>
                    <a href="<?php echo PUBLIC_URL_PREFIX; ?>/clientes/vilaseca/index.php" class="btn btn-outline-secondary btn-sm font-weight-bold">
                        <i class="fas fa-arrow-left mr-1"></i>Vilaseca
                    </a>
                <?php endif; ?>
                <a href="racks.php<?php echo '?cliente=' . urlencode($client_filter); ?>" class="btn btn-outline-warning text-dark btn-sm font-weight-bold">
                    <i class="fas fa-server mr-1"></i>Racks
                </a>
            <?php endif; ?>

            <a href="viewer_3d.php<?php echo ($room_id ? '?room_id=' . $room_id : '') . ($client_filter ? ($room_id ? '&' : '?') . 'cliente=' . urlencode($client_filter) : ''); ?>" class="btn btn-info btn-sm font-weight-bold shadow-sm">
                <i class="fas fa-cube mr-1"></i>3DViewer
            </a>

            <!-- Room Selection Form -->
            <form method="get" class="d-flex align-items-center mb-0">
                <?php if (!empty($client_filter)): ?>
                    <input type="hidden" name="cliente" value="<?php echo htmlspecialchars($client_filter); ?>">
                <?php endif; ?>
                <label class="mr-2 font-weight-bold text-secondary mb-0">Cuarto:</label>
                <select name="room_id" class="form-control form-control-sm" style="width: 170px; border-radius: 20px;" onchange="this.form.submit()">
                    <?php if (empty($rooms)): ?>
                        <option value="0">-- Sin cuartos registrados --</option>
                    <?php else: ?>
                        <?php foreach($rooms as $r): ?>
                            <option value="<?php echo $r['id']; ?>" <?php echo $r['id'] == $room_id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($r['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </form>

            <?php if ($room && !empty($racks)): ?>
                <div class="d-flex align-items-center">
                    <label class="mr-2 font-weight-bold text-secondary mb-0">Gabinete:</label>
                    <select id="rack-selector" class="form-control form-control-sm" style="width: 170px; border-radius: 20px;" onchange="if(this.value) selectRack(this.value)">
                        <option value="">-- Seleccionar --</option>
                        <?php foreach($racks as $r): ?>
                            <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$room): ?>
        <div class="alert alert-info py-4 text-center shadow-sm animate__animated animate__fadeIn">
            <i class="fas fa-info-circle fa-2x mb-2 text-primary"></i>
            <h5>No hay información disponible</h5>
            <p class="text-muted mb-0">Registre un cuarto de Datacenter y asocie gabinetes (racks) para iniciar el análisis gráfico.</p>
        </div>
    <?php else: ?>
        <!-- Capacity Color Legend -->
        <div class="card border-0 shadow-sm mb-4 animate__animated animate__fadeIn">
            <div class="card-body py-3 px-4 bg-white" style="border-radius: 12px;">
                <div class="d-flex flex-wrap align-items-center justify-content-between">
                    <span class="font-weight-bold text-secondary mr-3"><i class="fas fa-palette mr-1"></i> Rango de Disponibilidad (U Libres):</span>
                    <div class="d-flex flex-wrap gap-4 mt-2 mt-md-0">
                        <div class="mr-4"><span class="legend-box" style="background-color: #28a745;"></span> <span class="small font-weight-bold text-dark">80% - 100% Libre (Excelente)</span></div>
                        <div class="mr-4"><span class="legend-box" style="background-color: #8bc34a;"></span> <span class="small font-weight-bold text-dark">60% - 80% Libre (Bueno)</span></div>
                        <div class="mr-4"><span class="legend-box" style="background-color: #ffc107;"></span> <span class="small font-weight-bold text-dark">40% - 60% Libre (Intermedio)</span></div>
                        <div class="mr-4"><span class="legend-box" style="background-color: #ff851b;"></span> <span class="small font-weight-bold text-dark">20% - 40% Libre (Alto Uso)</span></div>
                        <div><span class="legend-box" style="background-color: #dc3545;"></span> <span class="small font-weight-bold text-dark">0% - 20% Libre (Crítico)</span></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Side: Floor Plan Map Grid -->
            <div class="col-xl-7 col-lg-6 mb-4 animate__animated animate__fadeInLeft">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                        <h5 class="card-title font-weight-bold text-dark mb-0"><i class="fas fa-map-marked-alt text-primary mr-2"></i> Plano de Distribución</h5>
                        <small class="text-muted">Área: <?php echo $width_m; ?>m x <?php echo $length_m; ?>m</small>
                    </div>
                    <div class="card-body p-4 text-center" style="overflow-x: auto;">
                        <div class="floor-wrapper" id="floor-wrapper">
                            <table class="floor-table" id="floor-table">
                                <thead>
                                    <tr>
                                        <th class="row-header"></th>
                                        <?php 
                                        $col_name = 'AA';
                                        for ($i = 0; $i < $tiles_x; $i++): 
                                        ?>
                                            <th><?php echo $col_name++; ?></th>
                                        <?php endfor; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php for ($y_index = 1; $y_index <= $tiles_y; $y_index++): ?>
                                        <tr>
                                            <th class="row-header"><?php echo str_pad($y_index, 2, '0', STR_PAD_LEFT); ?></th>
                                            <?php for ($x_index = 0; $x_index < $tiles_x; $x_index++): ?>
                                                <td></td>
                                            <?php endfor; ?>
                                        </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                            
                            <div class="racks-layer" id="racks-layer">
                                <!-- Only racks rendered in this view -->
                                <?php 
                                $px_per_tile = 40;
                                ?>

                                <!-- Draw color-coded Racks -->
                                <?php 
                                foreach($racks as $r): 
                                    $r_id = $r['id'];
                                    $stats = $rack_stats[$r_id] ?? [
                                        'color' => '#6c757d',
                                        'text_color' => '#ffffff',
                                        'percent_free' => 100,
                                        'total_u' => $r['total_u']
                                    ];
                                    
                                    $x = (float)($r['grid_x']);
                                    $y = (float)($r['grid_y']);
                                    $wt = (float)($r['width_tiles'] ?: 1);
                                    $dt = (float)($r['depth_tiles'] ?: 2);
                                    $rot = (int)($r['rotation'] ?: 0);
                                    
                                    $is_swapped = ($rot == 90 || $rot == 270);
                                    $wt_render = $is_swapped ? $dt : $wt;
                                    $dt_render = $is_swapped ? $wt : $dt;
                                    
                                    $left = $x * $px_per_tile;
                                    $top = $y * $px_per_tile;
                                    $w_px = $wt_render * $px_per_tile;
                                    $h_px = $dt_render * $px_per_tile;
                                ?>
                                <div class="rack-item <?php echo ($w_px < $h_px) ? 'vertical-layout' : ''; ?>" 
                                     id="rack-item-<?php echo $r_id; ?>" 
                                     data-id="<?php echo $r_id; ?>" 
                                     data-rot="<?php echo $rot; ?>"
                                     style="left: <?php echo $left; ?>px; top: <?php echo $top; ?>px; width: <?php echo $w_px; ?>px; height: <?php echo $h_px; ?>px; background-color: <?php echo $stats['color']; ?>; color: <?php echo $stats['text_color']; ?>;"
                                     onclick="selectRack(<?php echo $r_id; ?>)"
                                     title="<?php echo htmlspecialchars($r['name']); ?> (<?php echo $stats['percent_free']; ?>% Libre)">
                                     <div>
                                        <span class="text-truncate px-1 d-block w-100"><?php echo htmlspecialchars($r['name']); ?></span>
                                        <span class="d-block text-xs" style="font-size: 8px; opacity: 0.85;"><?php echo $stats['percent_free']; ?>% L</span>
                                     </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side: Interactive Capacity Details Dashboard -->
            <div class="col-xl-5 col-lg-6 mb-4 animate__animated animate__fadeInRight">
                <div class="panel-details-premium h-100 d-flex flex-column" id="details-panel">
                    
                    <!-- Welcome state (No rack selected yet) -->
                    <div id="details-welcome" class="text-center my-auto py-5 animate__animated animate__fadeIn">
                        <i class="fas fa-server fa-4x text-muted opacity-3 mb-3"></i>
                        <h4 class="font-weight-bold text-dark">Estadísticas de Gabinete</h4>
                        <p class="text-muted">Haga clic en cualquier rack del plano de distribución para analizar su disponibilidad de espacio, potencia y multitomas instaladas.</p>
                        <?php if(!empty($racks)): ?>
                            <button class="btn btn-primary btn-sm rounded-pill px-4 mt-2" onclick="selectFirstRack()">Analizar Primer Rack</button>
                        <?php endif; ?>
                    </div>

                    <!-- Rack analysis content (hidden by default, loaded via JS) -->
                    <div id="details-content" class="d-none animate__animated animate__fadeIn">
                        <!-- Rack Title and Action -->
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h3 class="font-weight-bold text-dark mb-0" id="detail-rack-name">Rack -</h3>
                                <span class="badge text-xs" id="detail-rack-health-badge" style="padding: 6px 12px; border-radius: 20px;">-</span>
                            </div>
                            <a href="#" id="detail-btn-builder" class="btn btn-outline-primary btn-sm rounded-pill px-3" target="_blank">
                                <i class="fas fa-tools mr-1"></i> Diseñador
                            </a>
                        </div>

                        <!-- Main availability indicator card -->
                        <div class="card border-0 bg-light mb-4" style="border-radius: 12px;">
                            <div class="card-body p-3">
                                <div class="row align-items-center text-center">
                                    <div class="col-6 border-right">
                                        <span class="text-xs text-muted text-uppercase font-weight-bold">Espacio Libre (Total)</span>
                                        <h2 class="font-weight-bold text-success mt-1 mb-0" id="detail-percent-free">-</h2>
                                    </div>
                                    <div class="col-6">
                                        <span class="text-xs text-muted text-uppercase font-weight-bold">Capacidad Total</span>
                                        <h2 class="font-weight-bold text-dark mt-1 mb-0" id="detail-total-u">-</h2>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Occupancy details (Front vs Rear) -->
                        <h6 class="font-weight-bold text-secondary mb-3"><i class="fas fa-exchange-alt mr-1"></i> Ocupación de Unidades de Rack (U)</h6>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between small font-weight-bold text-dark mb-1">
                                <span>Lado Frontal (Frente)</span>
                                <span id="detail-front-status">-</span>
                            </div>
                            <div class="progress progress-custom">
                                <div class="progress-bar bg-primary" id="detail-front-bar" role="progressbar" style="width: 0%">-</div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="d-flex justify-content-between small font-weight-bold text-dark mb-1">
                                <span>Lado Trasero (Detrás)</span>
                                <span id="detail-rear-status">-</span>
                            </div>
                            <div class="progress progress-custom">
                                <div class="progress-bar bg-info" id="detail-rear-bar" role="progressbar" style="width: 0%">-</div>
                            </div>
                        </div>

                        <!-- Technical Stats (Power, PDUs) -->
                        <h6 class="font-weight-bold text-secondary mb-3"><i class="fas fa-bolt mr-1"></i> Recursos Eléctricos y PDUs</h6>
                        <div class="row mb-4">
                            <div class="col-6">
                                <div class="card metric-card p-3">
                                    <span class="text-xs text-muted font-weight-bold text-uppercase">Potencia Estimada</span>
                                    <h4 class="font-weight-bold text-dark mt-2 mb-0" id="detail-power-kw">-</h4>
                                    <small class="text-muted" id="detail-power-kva">-</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="card metric-card p-3">
                                    <span class="text-xs text-muted font-weight-bold text-uppercase">Multitomas (PDUs)</span>
                                    <h4 class="font-weight-bold text-warning mt-2 mb-0" id="detail-pdu-count">-</h4>
                                    <small class="text-muted">Verticales / Piso-Techo</small>
                                </div>
                            </div>
                        </div>

                        <!-- Device Inventory Table -->
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="font-weight-bold text-secondary mb-0"><i class="fas fa-list mr-1"></i> Inventario de Equipos (<span id="detail-device-count">0</span>)</h6>
                        </div>
                        <div class="table-responsive" style="max-height: 280px; overflow-y: auto; border: 1px solid #eef2f5; border-radius: 8px;">
                            <table class="table table-hover table-sm mb-0" style="font-size: 11.5px;">
                                <thead class="bg-light" style="position: sticky; top: 0; z-index: 1;">
                                    <tr>
                                        <th style="width: 45px;">U</th>
                                        <th>Equipo</th>
                                        <th>Lado/Prof</th>
                                        <th>IP Address</th>
                                        <th style="text-align: right;">Watts</th>
                                    </tr>
                                </thead>
                                <tbody id="detail-devices-tbody">
                                    <!-- Dynamic rows loaded via JS -->
                                </tbody>
                            </table>
                        </div>

                    </div>

                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    // Stats of all racks generated from backend
    const rackStats = <?php echo json_encode($rack_stats); ?>;

    function selectRack(rackId) {
        // Remove active class from other racks
        document.querySelectorAll('.rack-item').forEach(el => {
            el.classList.remove('active-rack');
        });

        // Add active class to selected rack
        const selectedEl = document.getElementById(`rack-item-${rackId}`);
        if (selectedEl) {
            selectedEl.classList.add('active-rack');
        }

        // Update Selector if exists
        const rackSelector = document.getElementById('rack-selector');
        if (rackSelector) {
            rackSelector.value = rackId;
        }

        const stats = rackStats[rackId];
        if (!stats) return;

        // Hide welcome message, display panel content
        document.getElementById('details-welcome').classList.add('d-none');
        document.getElementById('details-content').classList.remove('d-none');

        // Update basic rack details
        document.getElementById('detail-rack-name').innerText = stats.name;
        document.getElementById('detail-total-u').innerText = `${stats.total_u} U`;
        document.getElementById('detail-percent-free').innerText = `${stats.percent_free}% L`;

        // Update health/occupancy badge
        const badge = document.getElementById('detail-rack-health-badge');
        badge.innerText = stats.color_label;
        badge.style.backgroundColor = stats.color;
        badge.style.color = stats.text_color;

        // Update Builder Link
        document.getElementById('detail-btn-builder').href = `rack_builder.php?id=${rackId}`;

        // Update Front stats & progress bar
        const frontUsed = stats.front_occupied;
        const frontFree = stats.front_free;
        const frontPercent = stats.total_u > 0 ? Math.round((frontUsed / stats.total_u) * 100) : 0;
        document.getElementById('detail-front-status').innerText = `${frontUsed}U Usadas / ${frontFree}U Libres`;
        
        const frontBar = document.getElementById('detail-front-bar');
        frontBar.style.width = `${frontPercent}%`;
        frontBar.innerText = frontPercent > 10 ? `${frontPercent}% Ocupado` : `${frontPercent}%`;

        // Update Rear stats & progress bar
        const rearUsed = stats.rear_occupied;
        const rearFree = stats.rear_free;
        const rearPercent = stats.total_u > 0 ? Math.round((rearUsed / stats.total_u) * 100) : 0;
        document.getElementById('detail-rear-status').innerText = `${rearUsed}U Usadas / ${rearFree}U Libres`;
        
        const rearBar = document.getElementById('detail-rear-bar');
        rearBar.style.width = `${rearPercent}%`;
        rearBar.innerText = rearPercent > 10 ? `${rearPercent}% Ocupado` : `${rearPercent}%`;

        // Update Power and PDUs
        document.getElementById('detail-power-kw').innerText = `${stats.kw} kW`;
        document.getElementById('detail-power-kva').innerText = `${stats.kva} kVA (aprox)`;
        document.getElementById('detail-pdu-count').innerText = `${stats.pdu_count} PDU(s)`;

        // Load devices table
        const tbody = document.getElementById('detail-devices-tbody');
        tbody.innerHTML = '';
        document.getElementById('detail-device-count').innerText = stats.devices.length;

        if (stats.devices.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-3">No hay dispositivos instalados en este rack.</td></tr>`;
        } else {
            stats.devices.forEach(dev => {
                // Formatting depth and mounting details
                let sideLabel = dev.orientation === 'both' ? 'Ambos' : (dev.orientation === 'front' ? 'Frente' : 'Detrás');
                let depthLabel = dev.depth === 'full' ? 'Full' : (dev.depth === 'half' ? '1/2' : '1/3');
                let tag = '';
                
                if (dev.mounting === 'vertical_left' || dev.mounting === 'vertical_right') {
                    tag = '<span class="badge badge-warning text-xxs">PDU Vert</span>';
                    sideLabel = dev.mounting === 'vertical_left' ? 'Detrás (PDU A)' : 'Detrás (PDU B)';
                    depthLabel = '-';
                } else {
                    tag = `U ${dev.start_u}`;
                }

                let outletsList = [];
                if (dev.outlets_c13) outletsList.push(`${dev.outlets_c13}xC13`);
                if (dev.outlets_c19) outletsList.push(`${dev.outlets_c19}xC19`);
                if (dev.outlets_nema) outletsList.push(`${dev.outlets_nema}xNEMA`);
                let outletsLabel = outletsList.length > 0 ? ` <span class="badge badge-success ml-1" style="font-size: 8.5px; font-weight: bold; background-color: #28a745; color: #fff;">${outletsList.join(' / ')}</span>` : '';

                let tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="font-weight-bold text-secondary">${tag}</td>
                    <td>
                        <div class="font-weight-bold d-flex align-items-center flex-wrap">${escapeHtml(dev.name)}${outletsLabel}</div>
                        <div class="text-muted text-xxs" style="font-size:9.5px;">${escapeHtml(dev.make)} ${escapeHtml(dev.model)}</div>
                    </td>
                    <td><span class="text-xs">${sideLabel} (${depthLabel})</span></td>
                    <td><span class="text-xs font-mono">${escapeHtml(dev.ip || '-')}</span></td>
                    <td style="text-align: right;" class="font-weight-bold">${dev.watts} W</td>
                `;
                tbody.appendChild(tr);
            });
        }
    }

    function selectFirstRack() {
        const keys = Object.keys(rackStats);
        if (keys.length > 0) {
            selectRack(keys[0]);
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    // Auto-select first rack on load if available
    window.addEventListener('DOMContentLoaded', () => {
        selectFirstRack();
    });
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>

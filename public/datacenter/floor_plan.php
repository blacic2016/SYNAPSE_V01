<?php
/**
 * Datacenter Floor Plan Visualizer (OpenDCIM Style Grid)
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();
require_login();
$pdo = getPDO();
$room_id = (int)($_GET['room_id'] ?? $_GET['id'] ?? 0);
$client_filter = trim($_GET['cliente'] ?? $_GET['client'] ?? '');

if (!$room_id) {
    die("ID de Cuarto no proporcionado.");
}

$stmt = $pdo->prepare("SELECT * FROM dc_rooms WHERE id = ?");
$stmt->execute([$room_id]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$room) {
    die("Cuarto no encontrado.");
}

// Calculate Grid
$tile_size = (float)($room['tile_size'] ?? 0.6);
if ($tile_size <= 0) $tile_size = 0.6;

$width_m = (float)($room['width_meters'] ?? 6.0);
$length_m = (float)($room['length_meters'] ?? 6.0);

$tiles_x = floor($width_m / $tile_size);
$tiles_y = floor($length_m / $tile_size);

$page_title = 'Floor Plan: ' . htmlspecialchars($room['name']);

// Get racks in this room
$stmt = $pdo->prepare("SELECT id, name, grid_x, grid_y, width_tiles, depth_tiles, total_u, rotation, z_index FROM dc_racks WHERE room_id = ?");
$stmt->execute([$room_id]);
$racks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get layers
$stmt = $pdo->query("SELECT * FROM dc_floor_layers ORDER BY z_index ASC");
$layers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get items in this room
$stmt = $pdo->prepare("SELECT * FROM dc_floor_items WHERE room_id = ?");
$stmt->execute([$room_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate usage counts
$counts = [
    'rack' => count($racks),
    'piso_25' => 0,
    'piso_50' => 0,
    'piso_75' => 0,
    'aacc' => 0,
    'ups' => 0,
    'escalerilla_fibra' => 0,
    'escalerilla_cobre' => 0,
    'escalerilla_energia' => 0,
    'rampa' => 0,
    'camaras' => 0,
    'puerta' => 0,
];
foreach ($items as $i) {
    $t = $i['type'];
    if (isset($counts[$t])) {
        $counts[$t]++;
    }
}

// Generate Column Headers (AA, AB, AC...)
$col_name = 'AA';
$col_headers = [];
for ($i = 0; $i < $tiles_x; $i++) {
    $col_headers[] = $col_name++;
}

// Generate Row Headers (01, 02, 03...)
$row_headers = [];
for ($i = 1; $i <= $tiles_y; $i++) {
    $row_headers[] = str_pad($i, 2, '0', STR_PAD_LEFT);
}
$hide_content_header = true;
require_once __DIR__ . '/../partials/header.php';
?>

<style>
    .floor-wrapper {
        position: relative;
        display: inline-block;
        background: #fff;
        margin-top: 10px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        padding-bottom: 20px;
        padding-right: 20px;
        user-select: none;
        border: 1px solid #ced4da;
        border-radius: 6px;
    }
    
    .floor-table {
        border-collapse: collapse;
        table-layout: fixed;
        border: 2px solid #495057;
    }
    
    .floor-table th, .floor-table td {
        width: 40px; /* Tamaño visual de la baldosa de 0.6x0.6m */
        height: 40px;
        border: 1px solid #ced4da;
        text-align: center;
        vertical-align: middle;
        padding: 0;
        margin: 0;
        box-sizing: border-box;
    }

    .floor-table th {
        background-color: #f8f9fa;
        font-weight: bold;
        font-size: 11px;
        color: #000 !important;
        border: 1px solid #ced4da;
    }
    
    .floor-table .row-header {
        background-color: #f8f9fa;
        width: 30px;
    }

    /* Contenedor relativo exacto sobre la tabla (excluyendo cabeceras) */
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
        color: #000 !important;
        cursor: grab;
        box-shadow: inset 0 0 10px rgba(0,0,0,0.2);
        pointer-events: auto;
        transition: filter 0.2s, opacity 0.2s;
        box-sizing: border-box;
    }
    
    .rack-item {
        background-color: #5cb85c;
        border: 2px solid #333;
        z-index: 10;
    }

    .rack-item:hover, .floor-obj:hover {
        filter: brightness(1.1);
        border-color: #000;
        box-shadow: 0 0 8px rgba(0,0,0,0.5);
    }
    
    .rack-item:active, .floor-obj:active {
        cursor: grabbing;
        opacity: 0.8;
    }
    
    /* Tipos de Objetos Especiales */
    .floor-obj { border: 2px solid #555; z-index: 5; }
    .obj-piso_25 { background: repeating-linear-gradient(45deg, #ddd, #ddd 5px, #fff 5px, #fff 10px); border: 1px dashed #999; }
    .obj-piso_50 { background: repeating-linear-gradient(45deg, #ccc, #ccc 5px, #eee 5px, #eee 10px); border: 1px dashed #999; }
    .obj-piso_75 { background: repeating-linear-gradient(45deg, #aaa, #aaa 5px, #ddd 5px, #ddd 10px); border: 1px dashed #999; }
    .obj-aacc { background-color: #5bc0de; border: 2px solid #1b6d85; }
    .obj-ups { background-color: #f0ad4e; border: 2px solid #d58512; }
    .obj-escalerilla_fibra { background-color: #ffcc00; border: 2px dashed #cc9900; opacity: 0.8; }
    .obj-escalerilla_cobre { background-color: #0066cc; border: 2px dashed #004499; opacity: 0.8; }
    .obj-escalerilla_energia { background-color: #cc0000; border: 2px dashed #990000; opacity: 0.8; }
    .obj-rampa { background-color: #9b59b6; border: 2px solid #8e44ad; }
    .obj-camaras { background-color: #34495e; border: 2px solid #2c3e50; }
    .obj-puerta { background-color: #d35400; border: 2px solid #a04000; }

    /* Línea Negra Delantera para Racks, UPS, AACC, Cámaras y Puertas */
    .rack-item[data-rot="0"] { border-top: 5px solid #000 !important; }
    .rack-item[data-rot="90"] { border-right: 5px solid #000 !important; }
    .rack-item[data-rot="180"] { border-bottom: 5px solid #000 !important; }
    .rack-item[data-rot="270"] { border-left: 5px solid #000 !important; }

    .floor-obj.obj-ups[data-rot="0"], .floor-obj.obj-aacc[data-rot="0"], .floor-obj.obj-camaras[data-rot="0"], .floor-obj.obj-puerta[data-rot="0"] { border-top: 5px solid #000 !important; }
    .floor-obj.obj-ups[data-rot="90"], .floor-obj.obj-aacc[data-rot="90"], .floor-obj.obj-camaras[data-rot="90"], .floor-obj.obj-puerta[data-rot="90"] { border-right: 5px solid #000 !important; }
    .floor-obj.obj-ups[data-rot="180"], .floor-obj.obj-aacc[data-rot="180"], .floor-obj.obj-camaras[data-rot="180"], .floor-obj.obj-puerta[data-rot="180"] { border-bottom: 5px solid #000 !important; }
    .floor-obj.obj-ups[data-rot="270"], .floor-obj.obj-aacc[data-rot="270"], .floor-obj.obj-camaras[data-rot="270"], .floor-obj.obj-puerta[data-rot="270"] { border-left: 5px solid #000 !important; }

    /* Texto vertical para objetos más altos que anchos */
    .vertical-layout > div {
        writing-mode: vertical-lr;
        text-orientation: mixed;
        transform: none !important;
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
        width: 100%;
    }

    .sidebar-panel {
        background: white;
        color: #212529 !important;
        padding: 15px;
        border-radius: 5px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    
    .sidebar-panel h5, 
    .sidebar-panel p, 
    .sidebar-panel label, 
    .sidebar-panel small, 
    .sidebar-panel li {
        color: #212529 !important;
    }
    
    .palette-item {
        width: 100%;
        margin-bottom: 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: bold;
        transition: transform 0.1s, box-shadow 0.1s;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        border: 1px solid #ced4da;
    }
    .palette-item:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 6px rgba(0,0,0,0.15);
    }
    .palette-item .drag-handle {
        cursor: grab;
        padding: 2px 4px;
    }
    .palette-item .drag-handle:active {
        cursor: grabbing;
    }
    
    .layer-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 5px;
        border-bottom: 1px solid #eee;
        font-size: 12px;
    }

    #context-menu {
        display: none;
        position: absolute;
        z-index: 1000;
        background: white;
        border: 1px solid #ccc;
        box-shadow: 2px 2px 5px rgba(0,0,0,0.2);
        padding: 5px 0;
        min-width: 150px;
    }
    #context-menu div {
        padding: 8px 15px;
        cursor: pointer;
        font-size: 13px;
    }
    #context-menu div:hover {
        background-color: #f0f0f0;
    }
</style>

<div class="container-fluid pt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0"><i class="fas fa-th text-success mr-2"></i> Floor Plan: <?php echo htmlspecialchars($room['name']); ?></h4>
            <p class="text-muted mb-0">Área: <?php echo $width_m; ?>m x <?php echo $length_m; ?>m | Baldosas: <?php echo $tiles_x; ?> horizontal x <?php echo $tiles_y; ?> vertical (Total: <?php echo $tiles_x * $tiles_y; ?> baldosas de 0.6x0.6m)</p>
        </div>
        <div>
            <?php 
            $cParam = $client_filter ? '&cliente=' . urlencode($client_filter) : ''; 
            $cParamQ = $client_filter ? '?cliente=' . urlencode($client_filter) : '';
            ?>
            <a href="viewer_3d.php?room_id=<?php echo $room_id . $cParam; ?>" class="btn btn-info btn-sm mr-2"><i class="fas fa-cube"></i> 3DViewer</a>
            <a href="racks.php?room_id=<?php echo $room_id . $cParam; ?>" class="btn btn-primary btn-sm mr-2"><i class="fas fa-server"></i> Ir a Lista de Racks</a>
            <a href="rooms.php<?php echo $cParamQ; ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Volver a Cuartos</a>
        </div>
    </div>

    <div class="row">
        <!-- Sidebar Panels -->
        <div class="col-md-3">
            <div class="sidebar-panel">
                <h5><i class="fas fa-layer-group text-primary mr-2"></i> Capas (Layers)</h5>
                <div id="layers-list">
                    <?php foreach($layers as $l): ?>
                    <div class="layer-item">
                        <div>
                            <input type="checkbox" checked class="layer-toggle" data-layer-id="<?php echo $l['id']; ?>" id="chk-l-<?php echo $l['id']; ?>">
                            <label for="chk-l-<?php echo $l['id']; ?>" class="mb-0 ml-1"><?php echo htmlspecialchars($l['name']); ?></label>
                        </div>
                        <small class="text-muted">Z: <?php echo $l['z_index']; ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="sidebar-panel">
                <h5><i class="fas fa-toolbox text-warning mr-2"></i> Equipamiento</h5>
                <p class="small text-muted mb-2">Arrastra objetos al plano.</p>
                <div class="palette">
                    <!-- Rack -->
                    <div class="palette-item obj-rack d-flex align-items-center justify-content-between p-1 mb-2" data-obj-type="rack" style="background-color: #f8f9fa; border: 1px solid #ced4da; border-radius: 4px; color: #333; cursor: pointer;">
                        <div class="d-flex align-items-center" style="width: 50%; overflow: hidden; padding-right: 5px;">
                            <i class="fas fa-grip-vertical mr-2 drag-handle text-muted" style="cursor: grab; font-size: 13px;" title="Arrastrar desde aquí"></i>
                            <span class="text-truncate font-weight-bold" style="font-size: 11px;">Rack</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-center" style="width: 20%;">
                            <div style="width: 20px; height: 20px; background-color: #5cb85c; border: 2px solid #333; border-radius: 2px;" title="Vista previa"></div>
                        </div>
                        <div class="d-flex flex-column align-items-center justify-content-center" style="width: 30%; border-left: 1px solid #ced4da; height: 28px; line-height: 1;">
                            <span class="text-muted font-weight-bold" style="font-size: 8px; text-transform: uppercase;">Cant</span>
                            <span class="font-weight-bold text-primary" style="font-size: 12px; margin-top: 2px;"><?php echo $counts['rack']; ?></span>
                        </div>
                    </div>
                    <!-- Piso 25% -->
                    <div class="palette-item obj-piso_25 d-flex align-items-center justify-content-between p-1 mb-2" data-obj-type="piso_25" style="background-color: #f8f9fa; border: 1px solid #ced4da; border-radius: 4px; color: #333; cursor: pointer;">
                        <div class="d-flex align-items-center" style="width: 50%; overflow: hidden; padding-right: 5px;">
                            <i class="fas fa-grip-vertical mr-2 drag-handle text-muted" style="cursor: grab; font-size: 13px;" title="Arrastrar desde aquí"></i>
                            <span class="text-truncate font-weight-bold" style="font-size: 11px;">Piso 25%</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-center" style="width: 20%;">
                            <div style="width: 20px; height: 20px; background: repeating-linear-gradient(45deg, #ddd, #ddd 3px, #fff 3px, #fff 6px); border: 1px dashed #999; border-radius: 2px;" title="Vista previa"></div>
                        </div>
                        <div class="d-flex flex-column align-items-center justify-content-center" style="width: 30%; border-left: 1px solid #ced4da; height: 28px; line-height: 1;">
                            <span class="text-muted font-weight-bold" style="font-size: 8px; text-transform: uppercase;">Cant</span>
                            <span class="font-weight-bold text-primary" style="font-size: 12px; margin-top: 2px;"><?php echo $counts['piso_25']; ?></span>
                        </div>
                    </div>
                    <!-- Piso 50% -->
                    <div class="palette-item obj-piso_50 d-flex align-items-center justify-content-between p-1 mb-2" data-obj-type="piso_50" style="background-color: #f8f9fa; border: 1px solid #ced4da; border-radius: 4px; color: #333; cursor: pointer;">
                        <div class="d-flex align-items-center" style="width: 50%; overflow: hidden; padding-right: 5px;">
                            <i class="fas fa-grip-vertical mr-2 drag-handle text-muted" style="cursor: grab; font-size: 13px;" title="Arrastrar desde aquí"></i>
                            <span class="text-truncate font-weight-bold" style="font-size: 11px;">Piso 50%</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-center" style="width: 20%;">
                            <div style="width: 20px; height: 20px; background: repeating-linear-gradient(45deg, #ccc, #ccc 3px, #eee 3px, #eee 6px); border: 1px dashed #999; border-radius: 2px;" title="Vista previa"></div>
                        </div>
                        <div class="d-flex flex-column align-items-center justify-content-center" style="width: 30%; border-left: 1px solid #ced4da; height: 28px; line-height: 1;">
                            <span class="text-muted font-weight-bold" style="font-size: 8px; text-transform: uppercase;">Cant</span>
                            <span class="font-weight-bold text-primary" style="font-size: 12px; margin-top: 2px;"><?php echo $counts['piso_50']; ?></span>
                        </div>
                    </div>
                    <!-- Piso 75% -->
                    <div class="palette-item obj-piso_75 d-flex align-items-center justify-content-between p-1 mb-2" data-obj-type="piso_75" style="background-color: #f8f9fa; border: 1px solid #ced4da; border-radius: 4px; color: #333; cursor: pointer;">
                        <div class="d-flex align-items-center" style="width: 50%; overflow: hidden; padding-right: 5px;">
                            <i class="fas fa-grip-vertical mr-2 drag-handle text-muted" style="cursor: grab; font-size: 13px;" title="Arrastrar desde aquí"></i>
                            <span class="text-truncate font-weight-bold" style="font-size: 11px;">Piso 75%</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-center" style="width: 20%;">
                            <div style="width: 20px; height: 20px; background: repeating-linear-gradient(45deg, #aaa, #aaa 3px, #ddd 3px, #ddd 6px); border: 1px dashed #999; border-radius: 2px;" title="Vista previa"></div>
                        </div>
                        <div class="d-flex flex-column align-items-center justify-content-center" style="width: 30%; border-left: 1px solid #ced4da; height: 28px; line-height: 1;">
                            <span class="text-muted font-weight-bold" style="font-size: 8px; text-transform: uppercase;">Cant</span>
                            <span class="font-weight-bold text-primary" style="font-size: 12px; margin-top: 2px;"><?php echo $counts['piso_75']; ?></span>
                        </div>
                    </div>
                    <!-- AACC -->
                    <div class="palette-item obj-aacc d-flex align-items-center justify-content-between p-1 mb-2" data-obj-type="aacc" style="background-color: #f8f9fa; border: 1px solid #ced4da; border-radius: 4px; color: #333; cursor: pointer;">
                        <div class="d-flex align-items-center" style="width: 50%; overflow: hidden; padding-right: 5px;">
                            <i class="fas fa-grip-vertical mr-2 drag-handle text-muted" style="cursor: grab; font-size: 13px;" title="Arrastrar desde aquí"></i>
                            <span class="text-truncate font-weight-bold" style="font-size: 11px;">AACC</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-center" style="width: 20%;">
                            <div style="width: 20px; height: 20px; background-color: #5bc0de; border: 2px solid #1b6d85; border-radius: 2px;" title="Vista previa"></div>
                        </div>
                        <div class="d-flex flex-column align-items-center justify-content-center" style="width: 30%; border-left: 1px solid #ced4da; height: 28px; line-height: 1;">
                            <span class="text-muted font-weight-bold" style="font-size: 8px; text-transform: uppercase;">Cant</span>
                            <span class="font-weight-bold text-primary" style="font-size: 12px; margin-top: 2px;"><?php echo $counts['aacc']; ?></span>
                        </div>
                    </div>
                    <!-- UPS -->
                    <div class="palette-item obj-ups d-flex align-items-center justify-content-between p-1 mb-2" data-obj-type="ups" style="background-color: #f8f9fa; border: 1px solid #ced4da; border-radius: 4px; color: #333; cursor: pointer;">
                        <div class="d-flex align-items-center" style="width: 50%; overflow: hidden; padding-right: 5px;">
                            <i class="fas fa-grip-vertical mr-2 drag-handle text-muted" style="cursor: grab; font-size: 13px;" title="Arrastrar desde aquí"></i>
                            <span class="text-truncate font-weight-bold" style="font-size: 11px;">UPS</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-center" style="width: 20%;">
                            <div style="width: 20px; height: 20px; background-color: #f0ad4e; border: 2px solid #d58512; border-radius: 2px;" title="Vista previa"></div>
                        </div>
                        <div class="d-flex flex-column align-items-center justify-content-center" style="width: 30%; border-left: 1px solid #ced4da; height: 28px; line-height: 1;">
                            <span class="text-muted font-weight-bold" style="font-size: 8px; text-transform: uppercase;">Cant</span>
                            <span class="font-weight-bold text-primary" style="font-size: 12px; margin-top: 2px;"><?php echo $counts['ups']; ?></span>
                        </div>
                    </div>
                    <!-- Escalerilla Fibra -->
                    <div class="palette-item obj-escalerilla_fibra d-flex align-items-center justify-content-between p-1 mb-2" data-obj-type="escalerilla_fibra" style="background-color: #f8f9fa; border: 1px solid #ced4da; border-radius: 4px; color: #333; cursor: pointer;">
                        <div class="d-flex align-items-center" style="width: 50%; overflow: hidden; padding-right: 5px;">
                            <i class="fas fa-grip-vertical mr-2 drag-handle text-muted" style="cursor: grab; font-size: 13px;" title="Arrastrar desde aquí"></i>
                            <span class="text-truncate font-weight-bold" style="font-size: 11px;">Esc. Fibra</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-center" style="width: 20%;">
                            <div style="width: 20px; height: 20px; background-color: #ffcc00; border: 2px dashed #cc9900; border-radius: 2px;" title="Vista previa"></div>
                        </div>
                        <div class="d-flex flex-column align-items-center justify-content-center" style="width: 30%; border-left: 1px solid #ced4da; height: 28px; line-height: 1;">
                            <span class="text-muted font-weight-bold" style="font-size: 8px; text-transform: uppercase;">Cant</span>
                            <span class="font-weight-bold text-primary" style="font-size: 12px; margin-top: 2px;"><?php echo $counts['escalerilla_fibra']; ?></span>
                        </div>
                    </div>
                    <!-- Escalerilla Cobre -->
                    <div class="palette-item obj-escalerilla_cobre d-flex align-items-center justify-content-between p-1 mb-2" data-obj-type="escalerilla_cobre" style="background-color: #f8f9fa; border: 1px solid #ced4da; border-radius: 4px; color: #333; cursor: pointer;">
                        <div class="d-flex align-items-center" style="width: 50%; overflow: hidden; padding-right: 5px;">
                            <i class="fas fa-grip-vertical mr-2 drag-handle text-muted" style="cursor: grab; font-size: 13px;" title="Arrastrar desde aquí"></i>
                            <span class="text-truncate font-weight-bold" style="font-size: 11px;">Esc. Cobre</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-center" style="width: 20%;">
                            <div style="width: 20px; height: 20px; background-color: #0066cc; border: 2px dashed #004499; border-radius: 2px;" title="Vista previa"></div>
                        </div>
                        <div class="d-flex flex-column align-items-center justify-content-center" style="width: 30%; border-left: 1px solid #ced4da; height: 28px; line-height: 1;">
                            <span class="text-muted font-weight-bold" style="font-size: 8px; text-transform: uppercase;">Cant</span>
                            <span class="font-weight-bold text-primary" style="font-size: 12px; margin-top: 2px;"><?php echo $counts['escalerilla_cobre']; ?></span>
                        </div>
                    </div>
                    <!-- Escalerilla Energía -->
                    <div class="palette-item obj-escalerilla_energia d-flex align-items-center justify-content-between p-1 mb-2" data-obj-type="escalerilla_energia" style="background-color: #f8f9fa; border: 1px solid #ced4da; border-radius: 4px; color: #333; cursor: pointer;">
                        <div class="d-flex align-items-center" style="width: 50%; overflow: hidden; padding-right: 5px;">
                            <i class="fas fa-grip-vertical mr-2 drag-handle text-muted" style="cursor: grab; font-size: 13px;" title="Arrastrar desde aquí"></i>
                            <span class="text-truncate font-weight-bold" style="font-size: 11px;">Esc. Energía</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-center" style="width: 20%;">
                            <div style="width: 20px; height: 20px; background-color: #cc0000; border: 2px dashed #990000; border-radius: 2px;" title="Vista previa"></div>
                        </div>
                        <div class="d-flex flex-column align-items-center justify-content-center" style="width: 30%; border-left: 1px solid #ced4da; height: 28px; line-height: 1;">
                            <span class="text-muted font-weight-bold" style="font-size: 8px; text-transform: uppercase;">Cant</span>
                            <span class="font-weight-bold text-primary" style="font-size: 12px; margin-top: 2px;"><?php echo $counts['escalerilla_energia']; ?></span>
                        </div>
                    </div>
                    <!-- Rampa -->
                    <div class="palette-item obj-rampa d-flex align-items-center justify-content-between p-1 mb-2" data-obj-type="rampa" style="background-color: #f8f9fa; border: 1px solid #ced4da; border-radius: 4px; color: #333; cursor: pointer;">
                        <div class="d-flex align-items-center" style="width: 50%; overflow: hidden; padding-right: 5px;">
                            <i class="fas fa-grip-vertical mr-2 drag-handle text-muted" style="cursor: grab; font-size: 13px;" title="Arrastrar desde aquí"></i>
                            <span class="text-truncate font-weight-bold" style="font-size: 11px;">Rampa</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-center" style="width: 20%;">
                            <div style="width: 20px; height: 20px; background-color: #9b59b6; border: 2px solid #8e44ad; border-radius: 2px;" title="Vista previa"></div>
                        </div>
                        <div class="d-flex flex-column align-items-center justify-content-center" style="width: 30%; border-left: 1px solid #ced4da; height: 28px; line-height: 1;">
                            <span class="text-muted font-weight-bold" style="font-size: 8px; text-transform: uppercase;">Cant</span>
                            <span class="font-weight-bold text-primary" style="font-size: 12px; margin-top: 2px;"><?php echo $counts['rampa']; ?></span>
                        </div>
                    </div>
                    <!-- Cámaras -->
                    <div class="palette-item obj-camaras d-flex align-items-center justify-content-between p-1 mb-2" data-obj-type="camaras" style="background-color: #f8f9fa; border: 1px solid #ced4da; border-radius: 4px; color: #333; cursor: pointer;">
                        <div class="d-flex align-items-center" style="width: 50%; overflow: hidden; padding-right: 5px;">
                            <i class="fas fa-grip-vertical mr-2 drag-handle text-muted" style="cursor: grab; font-size: 13px;" title="Arrastrar desde aquí"></i>
                            <span class="text-truncate font-weight-bold" style="font-size: 11px;">Cámaras</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-center" style="width: 20%;">
                            <div style="width: 20px; height: 20px; background-color: #34495e; border: 2px solid #2c3e50; border-radius: 2px;" title="Vista previa"></div>
                        </div>
                        <div class="d-flex flex-column align-items-center justify-content-center" style="width: 30%; border-left: 1px solid #ced4da; height: 28px; line-height: 1;">
                            <span class="text-muted font-weight-bold" style="font-size: 8px; text-transform: uppercase;">Cant</span>
                            <span class="font-weight-bold text-primary" style="font-size: 12px; margin-top: 2px;"><?php echo $counts['camaras']; ?></span>
                        </div>
                    </div>
                    <!-- Puerta -->
                    <div class="palette-item obj-puerta d-flex align-items-center justify-content-between p-1 mb-2" data-obj-type="puerta" style="background-color: #f8f9fa; border: 1px solid #ced4da; border-radius: 4px; color: #333; cursor: pointer;">
                        <div class="d-flex align-items-center" style="width: 50%; overflow: hidden; padding-right: 5px;">
                            <i class="fas fa-grip-vertical mr-2 drag-handle text-muted" style="cursor: grab; font-size: 13px;" title="Arrastrar desde aquí"></i>
                            <span class="text-truncate font-weight-bold" style="font-size: 11px;">Puerta</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-center" style="width: 20%;">
                            <div style="width: 20px; height: 20px; background-color: #d35400; border: 2px solid #a04000; border-radius: 2px;" title="Vista previa"></div>
                        </div>
                        <div class="d-flex flex-column align-items-center justify-content-center" style="width: 30%; border-left: 1px solid #ced4da; height: 28px; line-height: 1;">
                            <span class="text-muted font-weight-bold" style="font-size: 8px; text-transform: uppercase;">Cant</span>
                            <span class="font-weight-bold text-primary" style="font-size: 12px; margin-top: 2px;"><?php echo $counts['puerta']; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="sidebar-panel">
                <h5><i class="fas fa-info-circle text-info mr-2"></i> Instrucciones</h5>
                <ul class="small text-muted pl-3 mb-0">
                    <li>Arrastra elementos para moverlos.</li>
                    <li><b>Click Derecho</b> para girar (90°).</li>
                    <li><b>Doble Click</b> en Rack: Ver interior.</li>
                    <li><b>Doble Click</b> en Ítem: Configurar dimensiones y capa.</li>
                </ul>
            </div>
        </div>

        <!-- Main Grid Area -->
        <div class="col-md-9" style="overflow: auto; max-height: calc(100vh - 150px);" id="floor-container">
            <div class="floor-wrapper" id="floor-wrapper">
                <table class="floor-table" id="floor-table">
                    <thead>
                        <tr>
                            <th class="row-header"></th>
                            <?php foreach ($col_headers as $col): ?>
                                <th><?php echo $col; ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($row_headers as $y_index => $row): ?>
                            <tr>
                                <th class="row-header"><?php echo $row; ?></th>
                                <?php for ($x_index = 0; $x_index < $tiles_x; $x_index++): ?>
                                    <td data-x="<?php echo $x_index; ?>" data-y="<?php echo $y_index; ?>" class="droppable-cell"></td>
                                <?php endfor; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="racks-layer" id="racks-layer">
                    <!-- Racks -->
                    <?php 
                    $px_per_tile = 40;
                    foreach($racks as $r): 
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
                        $z_index = (isset($r['z_index']) && $r['z_index'] !== null) ? (int)$r['z_index'] : 10;
                    ?>
                    <div class="rack-item <?php echo ($w_px < $h_px) ? 'vertical-layout' : ''; ?>" id="rack-<?php echo $r['id']; ?>" data-id="<?php echo $r['id']; ?>" data-type="rack" data-rot="<?php echo $rot; ?>"
                         style="left: <?php echo $left; ?>px; top: <?php echo $top; ?>px; width: <?php echo $w_px; ?>px; height: <?php echo $h_px; ?>px; z-index: <?php echo $z_index; ?>;"
                         title="<?php echo htmlspecialchars($r['name']); ?> (<?php echo $r['total_u']; ?>U)">
                         <div>
                            <span class="text-truncate px-1 d-block w-100"><?php echo htmlspecialchars($r['name']); ?></span>
                         </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <!-- Other Items -->
                    <?php 
                    foreach($items as $i): 
                        $x = (float)($i['grid_x']);
                        $y = (float)($i['grid_y']);
                        $wt = (float)($i['width_tiles'] ?: 1);
                        $dt = (float)($i['depth_tiles'] ?: 1);
                        $rot = (int)($i['rotation'] ?: 0);
                        $type_class = 'obj-' . htmlspecialchars($i['type']);
                        
                        $is_swapped = ($rot == 90 || $rot == 270);
                        $wt_render = $is_swapped ? $dt : $wt;
                        $dt_render = $is_swapped ? $wt : $dt;
                        
                        // Encontrar z-index de la capa
                        $z_index = 5;
                        foreach($layers as $l) {
                            if($l['id'] == $i['layer_id']) {
                                $z_index = $l['z_index'];
                                break;
                            }
                        }
                        if (isset($i['z_index']) && $i['z_index'] !== null) {
                            $z_index = (int)$i['z_index'];
                        }
                        
                        $left = $x * $px_per_tile;
                        $top = $y * $px_per_tile;
                        $w_px = $wt_render * $px_per_tile;
                        $h_px = $dt_render * $px_per_tile;
                    ?>
                    <div class="floor-obj <?php echo $type_class; ?> layer-<?php echo $i['layer_id']; ?> <?php echo ($w_px < $h_px) ? 'vertical-layout' : ''; ?>" 
                         id="item-<?php echo $i['id']; ?>" data-id="<?php echo $i['id']; ?>" data-type="item" data-rot="<?php echo $rot; ?>"
                         style="left: <?php echo $left; ?>px; top: <?php echo $top; ?>px; width: <?php echo $w_px; ?>px; height: <?php echo $h_px; ?>px; z-index: <?php echo $z_index; ?>;"
                         title="<?php echo htmlspecialchars($i['name']); ?>">
                         <div>
                            <span class="text-truncate px-1 d-block w-100"><?php echo htmlspecialchars($i['name']); ?></span>
                         </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Configuración Ítem -->
<div class="modal fade" id="itemConfigModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Configurar Elemento</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <form id="itemConfigForm">
            <input type="hidden" id="item_id" name="id">
            <input type="hidden" id="item_room_id" name="room_id" value="<?php echo $room_id; ?>">
            <input type="hidden" id="item_type" name="type">
            <input type="hidden" id="item_x" name="grid_x">
            <input type="hidden" id="item_y" name="grid_y">
            <input type="hidden" id="item_rot" name="rotation" value="0">
            
            <div class="form-group">
                <label>Nombre</label>
                <input type="text" class="form-control" id="item_name" name="name" required>
            </div>
            
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Ancho (Baldosas)</label>
                    <input type="number" step="0.5" class="form-control" id="item_w" name="width_tiles" min="0.5" max="20" required value="1">
                </div>
                <div class="form-group col-md-4">
                    <label>Prof. (Baldosas)</label>
                    <input type="number" step="0.5" class="form-control" id="item_d" name="depth_tiles" min="0.5" max="20" required value="1">
                </div>
                <div class="form-group col-md-4">
                    <label>Alto (Metros)</label>
                    <input type="number" step="0.1" class="form-control" id="item_h" name="height_meters" value="0">
                </div>
            </div>

            <div class="form-group">
                <label>Capa (Layer)</label>
                <select class="form-control" id="item_layer" name="layer_id">
                    <option value="">-- Sin Capa --</option>
                    <?php foreach($layers as $l): ?>
                    <option value="<?php echo $l['id']; ?>"><?php echo htmlspecialchars($l['name']); ?> (Z: <?php echo $l['z_index']; ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
      </div>
      <div class="modal-footer d-flex justify-content-between">
        <button type="button" class="btn btn-danger" id="btnDeleteItem" style="display:none;">Eliminar</button>
        <div>
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
            <button type="button" class="btn btn-primary" id="btnSaveItem">Guardar</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Context Menu -->
<div id="context-menu">
    <div id="btn-rotate"><i class="fas fa-sync-alt mr-2"></i> Girar 90°</div>
    <div id="btn-bring-front"><i class="fas fa-arrow-up mr-2"></i> Traer al frente</div>
    <div id="btn-send-back"><i class="fas fa-arrow-down mr-2"></i> Enviar al fondo</div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/jquery-ui.min.js"></script>
<script>
    const TILE_PX = 40;
    const MAX_X = <?php echo $tiles_x; ?>;
    const MAX_Y = <?php echo $tiles_y; ?>;
    let selectedElement = null;

    $(function() {
        // Init Draggables (Racks and Items)
        initDraggables();

        // Toolbox Drag (Create new item)
        $(".palette-item").draggable({
            handle: ".drag-handle",
            helper: function() {
                let type = $(this).data('obj-type');
                let text = $(this).find('.text-truncate').text();
                let typeClass = 'obj-' + type;
                
                // Get style characteristics if needed, or fallback
                let extraStyles = "";
                if (type === 'rack') {
                    extraStyles = "background-color: #5cb85c; border: 2px solid #333; color: #fff;";
                } else if (type === 'aacc') {
                    extraStyles = "background-color: #5bc0de; border: 2px solid #1b6d85; color: #fff;";
                } else if (type === 'ups') {
                    extraStyles = "background-color: #f0ad4e; border: 2px solid #d58512; color: #fff;";
                } else if (type === 'rampa') {
                    extraStyles = "background-color: #9b59b6; border: 2px solid #8e44ad; color: #fff;";
                } else if (type === 'camaras') {
                    extraStyles = "background-color: #34495e; border: 2px solid #2c3e50; color: #fff;";
                } else if (type === 'puerta') {
                    extraStyles = "background-color: #d35400; border: 2px solid #a04000; color: #fff;";
                }
                
                return $(`<div class="floor-obj ${typeClass}" style="width: 40px; height: 40px; font-size: 8px; opacity: 0.8; pointer-events: none; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: flex; align-items: center; justify-content: center; border: 2px dashed #333; box-shadow: 0 4px 8px rgba(0,0,0,0.3); ${extraStyles}">${text.substring(0, 10)}</div>`);
            },
            revert: "invalid",
            appendTo: "body",
            zIndex: 9999,
            cursorAt: { left: 20, top: 20 }
        });

        // Droppable floor
        $("#floor-wrapper").droppable({
            accept: ".palette-item",
            drop: function(event, ui) {
                if (ui.draggable.hasClass('palette-item')) {
                    // It's a new item from toolbox
                    let objType = ui.draggable.data('obj-type');
                    let objName = ui.draggable.find('.text-truncate').text();
                    
                    // Calculate drop position with half-tile support (increments of 0.5)
                    let offset = $("#floor-table").offset();
                    let x = Math.round(((event.pageX - offset.left - 30) / TILE_PX) * 2) / 2;
                    let y = Math.round(((event.pageY - offset.top - 40) / TILE_PX) * 2) / 2;
                    
                    if (objType.includes('escalerilla')) {
                        x = 0; // Starts at the beginning of the row horizontally by default
                    }
                    
                    if(x < 0) x = 0;
                    if(y < 0) y = 0;

                    if (objType === 'rack') {
                        let rackName = prompt("Nombre del Rack:", "Nuevo Rack");
                        if (!rackName) return;
                        
                        let rx = Math.round(x);
                        let ry = Math.round(y);
                        
                        $.post('api.php', {
                            action: 'save_rack_drag',
                            room_id: <?php echo $room_id; ?>,
                            name: rackName,
                            grid_x: rx,
                            grid_y: ry,
                            width_tiles: 1,
                            depth_tiles: 2,
                            total_u: 42,
                            rotation: 0
                        }, function(res) {
                            if (res.success) {
                                toastr.success('Rack creado y colocado');
                                setTimeout(() => location.reload(), 500);
                            } else {
                                toastr.error('Error: ' + res.message);
                            }
                        }, 'json');
                        return;
                    }
                    
                    // Open Modal for new Item
                    $("#itemConfigForm")[0].reset();
                    $("#item_id").val(0);
                    $("#item_type").val(objType);
                    $("#item_name").val(objName);
                    $("#item_x").val(x);
                    $("#item_y").val(y);
                    $("#item_rot").val(0);
                    
                    // Set dynamic max attributes based on room size
                    $("#item_w").attr('max', MAX_X);
                    $("#item_d").attr('max', MAX_Y);
                    
                    // If it is a perforated tile (rejilla), set half size by default (e.g. 1x0.5 or 0.5x1)
                    if (objType.includes('piso')) {
                        $("#item_w").val(1);
                        $("#item_d").val(0.5);
                    } else if (objType.includes('escalerilla')) {
                        // By default, horizontal cable tray (escalerilla) spanning full width of the room, 0.5 tiles deep
                        $("#item_w").val(MAX_X);
                        $("#item_d").val(0.5);
                    } else {
                        $("#item_w").val(1);
                        $("#item_d").val(1);
                    }
                    $("#btnDeleteItem").hide();
                    
                    // Select default layer based on type
                    if (objType.includes('piso')) {
                        $("#item_layer option:contains('Piso')").prop('selected', true);
                    } else if (objType === 'aacc') {
                        $("#item_layer option:contains('Aire')").prop('selected', true);
                    } else if (objType === 'ups') {
                        $("#item_layer option:contains('UPS')").prop('selected', true);
                    } else if (objType.includes('escalerilla')) {
                        $("#item_layer option:contains('Escalerilla')").prop('selected', true);
                    } else if (objType === 'puerta') {
                        $("#item_layer option:contains('Puertas')").prop('selected', true);
                    } else if (objType === 'camaras') {
                        $("#item_layer option:contains('Cámaras')").prop('selected', true);
                    }

                    $("#itemConfigModal").modal('show');
                }
            }
        });

        // Toggle Layers
        $(".layer-toggle").change(function() {
            let lid = $(this).data('layer-id');
            if($(this).is(':checked')) {
                $(".layer-" + lid).show();
            } else {
                $(".layer-" + lid).hide();
            }
        });

        // Double Click Logic
        $(document).on("dblclick", ".rack-item", function() {
            let id = $(this).data('id');
            window.location.href = 'rack_builder.php?id=' + id;
        });

        $(document).on("dblclick", ".floor-obj", function() {
            let id = $(this).data('id');
            // Fetch item data via GET or extract from DOM. Let's get via API
            $.get('api.php', { action: 'get_floor_items', room_id: <?php echo $room_id; ?> }, function(res) {
                if(res.success) {
                    let item = res.data.find(i => i.id == id);
                    if(item) {
                        $("#item_id").val(item.id);
                        $("#item_type").val(item.type);
                        $("#item_name").val(item.name);
                        $("#item_x").val(item.grid_x);
                        $("#item_y").val(item.grid_y);
                        $("#item_rot").val(item.rotation);
                        $("#item_w").val(item.width_tiles);
                        $("#item_d").val(item.depth_tiles);
                        $("#item_h").val(item.height_meters);
                        $("#item_layer").val(item.layer_id);
                        
                        // Set dynamic max attributes based on room size
                        $("#item_w").attr('max', MAX_X);
                        $("#item_d").attr('max', MAX_Y);

                        $("#btnDeleteItem").show();
                        $("#itemConfigModal").modal('show');
                    }
                }
            }, 'json');
        });

        // Save Item
        $("#btnSaveItem").click(function() {
            let data = $("#itemConfigForm").serialize() + "&action=save_floor_item";
            $.post('api.php', data, function(res) {
                if(res.success) {
                    toastr.success(res.message);
                    $("#itemConfigModal").modal('hide');
                    setTimeout(() => location.reload(), 500); // Reload for simplicity to render new item/sizes
                } else {
                    toastr.error('Error al guardar: ' + res.message);
                }
            }, 'json');
        });

        // Delete Item
        $("#btnDeleteItem").click(function() {
            if(confirm("¿Estás seguro de eliminar este elemento?")) {
                let id = $("#item_id").val();
                $.post('api.php', { action: 'delete_floor_item', id: id }, function(res) {
                    if(res.success) {
                        toastr.success(res.message);
                        $("#itemConfigModal").modal('hide');
                        $("#item-" + id).remove();
                    } else {
                        toastr.error('Error al eliminar');
                    }
                }, 'json');
            }
        });

        // Context Menu for Rotation
        $(document).on("contextmenu", ".rack-item, .floor-obj", function(e) {
            e.preventDefault();
            selectedElement = $(this);
            $("#context-menu").css({
                top: e.pageY + "px",
                left: e.pageX + "px"
            }).show();
        });

        $(document).click(function() {
            $("#context-menu").hide();
        });

        $("#btn-rotate").click(function() {
            if(selectedElement) {
                let currentRot = parseInt(selectedElement.attr('data-rot')) || 0;
                let newRot = (currentRot + 90) % 360;
                
                let id = selectedElement.data('id');
                let isRack = selectedElement.data('type') === 'rack';
                
                // Visual Update
                selectedElement.attr('data-rot', newRot).data('rot', newRot);
                
                // Swap width and height visually using raw CSS values to avoid border/padding scaling
                let currentW = parseInt(selectedElement.css('width')) || 40;
                let currentH = parseInt(selectedElement.css('height')) || 40;
                selectedElement.css({
                    width: currentH + 'px',
                    height: currentW + 'px'
                });
                
                // Toggle vertical text layout class based on the new dimensions
                if (currentW > currentH) {
                    selectedElement.addClass('vertical-layout');
                } else {
                    selectedElement.removeClass('vertical-layout');
                }

                // Save to DB
                if (isRack) {
                    $.post('api.php', {
                        action: 'update_rack_rotation',
                        rack_id: id,
                        rotation: newRot
                    }, function(res) {
                        if(res.success) toastr.success('Rotación del rack actualizada');
                        else toastr.error('Error al guardar rotación');
                    }, 'json');
                } else {
                    // For items, we just need to save the new rotation.
                    $.post('api.php', {
                        action: 'update_floor_item_rotation',
                        id: id,
                        rotation: newRot
                    }, function(res) {
                        if(res.success) toastr.success('Rotación del ítem actualizada');
                        else toastr.error('Error al guardar rotación');
                    }, 'json');
                }
            }
        });

        $("#btn-bring-front").click(function() {
            if(selectedElement) {
                let id = selectedElement.data('id');
                let isRack = selectedElement.data('type') === 'rack';
                
                // Find current max z-index in the room
                let maxZ = 0;
                $(".rack-item, .floor-obj").each(function() {
                    let z = parseInt($(this).css('z-index')) || 0;
                    if(z > maxZ) maxZ = z;
                });
                let newZ = maxZ + 1;
                
                // Visual update
                selectedElement.css('z-index', newZ);
                
                // Save to DB
                $.post('api.php', {
                    action: 'update_item_z_index',
                    id: id,
                    type: isRack ? 'rack' : 'item',
                    z_index: newZ
                }, function(res) {
                    if(res.success) {
                        toastr.success('Elemento traído al frente');
                    } else {
                        toastr.error('Error al actualizar posición de capa');
                    }
                }, 'json');
            }
        });

        $("#btn-send-back").click(function() {
            if(selectedElement) {
                let id = selectedElement.data('id');
                let isRack = selectedElement.data('type') === 'rack';
                
                // Find current min z-index in the room
                let minZ = 9999;
                $(".rack-item, .floor-obj").each(function() {
                    let z = parseInt($(this).css('z-index')) || 0;
                    if(z < minZ) minZ = z;
                });
                let newZ = Math.max(0, minZ - 1);
                
                // Visual update
                selectedElement.css('z-index', newZ);
                
                // Save to DB
                $.post('api.php', {
                    action: 'update_item_z_index',
                    id: id,
                    type: isRack ? 'rack' : 'item',
                    z_index: newZ
                }, function(res) {
                    if(res.success) {
                        toastr.success('Elemento enviado al fondo');
                    } else {
                        toastr.error('Error al actualizar posición de capa');
                    }
                }, 'json');
            }
        });
    });

    function initDraggables() {
        // Racks are always snapped to full tiles (40px)
        $(".rack-item").draggable({
            containment: "#floor-wrapper",
            grid: [TILE_PX, TILE_PX],
            stop: function(event, ui) {
                let id = $(this).data('id');
                let grid_x = Math.round(ui.position.left / TILE_PX);
                let grid_y = Math.round(ui.position.top / TILE_PX);
                
                if (grid_x < 0) grid_x = 0;
                if (grid_y < 0) grid_y = 0;
                
                $.post('api.php', {
                    action: 'update_rack_position',
                    rack_id: id,
                    grid_x: grid_x,
                    grid_y: grid_y
                }, function(res) {
                    if(res.success) toastr.success('Posición del rack actualizada');
                    else toastr.error('Error al guardar: ' + res.message);
                }, 'json');
            }
        });

        // Floor Items (like perforated tiles / grids) snap to half tiles (20px)
        $(".floor-obj").draggable({
            containment: "#floor-wrapper",
            grid: [TILE_PX / 2, TILE_PX / 2],
            stop: function(event, ui) {
                let id = $(this).data('id');
                let grid_x = Math.round((ui.position.left / TILE_PX) * 2) / 2;
                let grid_y = Math.round((ui.position.top / TILE_PX) * 2) / 2;
                
                if (grid_x < 0) grid_x = 0;
                if (grid_y < 0) grid_y = 0;
                
                $.post('api.php', {
                    action: 'update_floor_item_position',
                    id: id,
                    grid_x: grid_x,
                    grid_y: grid_y
                }, function(res) {
                    if(res.success) toastr.success('Posición actualizada');
                    else toastr.error('Error al guardar: ' + res.message);
                }, 'json');
            }
        });
    }
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>

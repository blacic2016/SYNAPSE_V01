<?php
/**
 * Datacenter Visual Rack Builder - Modernized Enterprise Edition
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/permissions_helper.php';

require_login();

$pdo = getPDO();
$rack_id = (int)($_GET['id'] ?? 0);

if (!$rack_id) {
    die("ID de Rack no proporcionado.");
}

// Fetch rack info
$stmt = $pdo->prepare("SELECT r.*, rm.name as room_name FROM dc_racks r LEFT JOIN dc_rooms rm ON r.room_id = rm.id WHERE r.id = ?");
$stmt->execute([$rack_id]);
$rack = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rack) {
    die("Rack no encontrado.");
}

// Tenancy / isolation check for Vilaseca-only users
$is_vilaseca_only_user = (!has_role('SUPER_ADMIN') && !has_module_access('datacenter') && has_module_access('vilaseca'));
if ($is_vilaseca_only_user && strtoupper($rack['client'] ?? '') !== 'VILASECA') {
    http_response_code(403);
    die("Acceso denegado: Este bastidor pertenece a otro cliente.");
}

// Handle editing of rack properties
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'update_rack') {
    $name = trim($_POST['name'] ?? '');
    $client = trim($_POST['client'] ?? ($rack['client'] ?: 'VILASECA'));
    if ($is_vilaseca_only_user) $client = 'VILASECA';
    $city = trim($_POST['city'] ?? ($rack['city'] ?: ''));
    $location = trim($_POST['location'] ?? ($rack['location'] ?: ''));
    $room_id = (int)($_POST['room_id'] ?? 0);
    $total_u = (int)($_POST['total_u'] ?? 42);
    $numbering_dir = $_POST['numbering_dir'] ?? 'DOWN';
    $description = trim($_POST['description'] ?? '');
    
    if (empty($client) || empty($city) || empty($location) || empty($name)) {
        $_SESSION['flash_error'] = "Error: Cliente, Ciudad, Ubicación y Nombre del Rack son campos obligatorios.";
    } else {
        // Validación de duplicados
        $stmtCheck = $pdo->prepare("SELECT id FROM dc_racks 
                                     WHERE UPPER(TRIM(client)) = UPPER(TRIM(?)) 
                                       AND UPPER(TRIM(city)) = UPPER(TRIM(?)) 
                                       AND UPPER(TRIM(location)) = UPPER(TRIM(?)) 
                                       AND UPPER(TRIM(name)) = UPPER(TRIM(?)) 
                                       AND id != ? 
                                     LIMIT 1");
        $stmtCheck->execute([$client, $city, $location, $name, $rack_id]);
        if ($stmtCheck->fetch()) {
            $_SESSION['flash_error'] = "No se puede actualizar: Ya existe otro rack con el nombre '{$name}' en la ciudad '{$city}' y ubicación '{$location}' para el cliente '{$client}'.";
        } else {
            $roomIdVal = ($room_id > 0) ? $room_id : null;
            $stmt = $pdo->prepare("UPDATE dc_racks SET name=?, client=?, city=?, location=?, room_id=?, total_u=?, numbering_dir=?, description=? WHERE id=?");
            $stmt->execute([$name, $client, $city, $location, $roomIdVal, $total_u, $numbering_dir, $description, $rack_id]);
            $_SESSION['flash_msg'] = "Rack actualizado exitosamente.";
            header("Location: rack_builder.php?id=" . $rack_id . (!empty($client) ? '&cliente=' . urlencode($client) : ''));
            exit;
        }
    }
}

// Cargar ciudades y ubicaciones conocidas para autocompletado seguro (sin UNION para evitar collation error 1271)
$cities1 = [];
$cities2 = [];
try {
    $cities1 = $pdo->query("SELECT DISTINCT city FROM dc_racks WHERE city IS NOT NULL AND city != ''")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}
try {
    $cities2 = $pdo->query("SELECT DISTINCT i.hostname FROM ci_instances i JOIN ci_categories c ON i.category_id = c.id WHERE c.name = 'Ciudad' AND i.hostname IS NOT NULL AND i.hostname != ''")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}
$knownCities = array_values(array_unique(array_filter(array_merge($cities1, $cities2))));
sort($knownCities, SORT_NATURAL | SORT_FLAG_CASE);

$locs1 = [];
$locs2 = [];
try {
    $sqlLocsSurveys = "SELECT DISTINCT location FROM manual_portmap_surveys WHERE location IS NOT NULL AND location != ''";
    if (!empty($rack['client'])) {
        $stmtLocs = $pdo->prepare($sqlLocsSurveys . " AND UPPER(client) LIKE ?");
        $stmtLocs->execute(['%' . strtoupper($rack['client']) . '%']);
        $locs1 = $stmtLocs->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $locs1 = $pdo->query($sqlLocsSurveys)->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (Exception $e) {}
try {
    $locs2 = $pdo->query("SELECT DISTINCT location FROM dc_racks WHERE location IS NOT NULL AND location != ''")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}
$knownLocations = array_values(array_unique(array_filter(array_merge($locs1, $locs2))));
sort($knownLocations, SORT_NATURAL | SORT_FLAG_CASE);

$page_title = 'Rack Builder: ' . htmlspecialchars($rack['name']);
$total_u = (int)$rack['total_u'];
$numbering_dir = $rack['numbering_dir'] ?? 'DOWN';
$description = $rack['description'] ?? '';

require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="css/rack_infographic.css?v=<?php echo filemtime(__DIR__ . '/css/rack_infographic.css'); ?>">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="js/rack_infographic.js?v=<?php echo filemtime(__DIR__ . '/js/rack_infographic.js'); ?>"></script>

<style>
    /* Estructura Principal y Distribución */
    :root {
        --rack-u-height: 32px;
        --rack-width: 530px;
        --rack-rail-width: 32px;
        --rack-bg-color: #0b0d12;
        --rack-frame-color: #141720;
        --rack-rail-color: #202738;
        --rack-border: #282e3b;
    }

    .builder-layout {
        display: flex;
        flex-direction: row;
        align-items: stretch;
        gap: 18px;
        min-height: calc(100vh - 170px);
        margin-bottom: 25px;
    }

    .rack-main-column {
        flex: 1 1 62%;
        min-width: 0;
        display: flex;
        flex-direction: column;
    }

    .details-area {
        flex: 0 0 460px;
        max-width: 480px;
        min-width: 380px;
        display: flex;
        flex-direction: column;
    }

    @media (max-width: 1200px) {
        .builder-layout {
            flex-direction: column;
        }
        .details-area {
            max-width: 100%;
            flex: 1 1 auto;
        }
    }

    /* KPI Summary Strip */
    .kpi-summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 12px;
        margin-bottom: 16px;
    }

    .kpi-card-metric {
        background: #ffffff;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        padding: 12px 16px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.03);
        display: flex;
        align-items: center;
        gap: 14px;
        transition: transform 0.15s, box-shadow 0.15s;
    }

    .kpi-card-metric:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.07);
    }

    .kpi-metric-icon {
        width: 44px;
        height: 44px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }

    .kpi-metric-body {
        flex: 1;
        min-width: 0;
    }

    .kpi-metric-label {
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        color: #64748b;
        letter-spacing: 0.5px;
        margin-bottom: 2px;
    }

    .kpi-metric-val {
        font-size: 1.15rem;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.2;
    }

    .kpi-metric-sub {
        font-size: 0.72rem;
        color: #94a3b8;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Barra de Herramientas del Canvas */
    .rack-canvas-toolbar {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px 8px 0 0;
        padding: 10px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
    }

    .view-mode-pills .btn {
        font-size: 0.8rem;
        font-weight: 600;
        padding: 4px 12px;
    }

    .view-mode-pills .btn.active {
        background: #1e293b;
        color: #fff;
        border-color: #1e293b;
    }

    /* Canvas Viewport */
    .rack-canvas-card {
        background: #0b0f19;
        border: 1px solid #1e293b;
        border-radius: 0 0 8px 8px;
        padding: 24px 16px;
        overflow: hidden;
        flex: 1;
        display: flex;
        flex-direction: column;
        box-shadow: inset 0 2px 10px rgba(0,0,0,0.5);
    }

    .rack-scroll-viewport {
        overflow-y: auto;
        overflow-x: auto;
        max-height: calc(100vh - 280px);
        min-height: 520px;
        padding: 10px 10px 30px 10px;
        scrollbar-width: thin;
        scrollbar-color: #334155 #0b0f19;
    }

    .rack-scroll-viewport::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }
    .rack-scroll-viewport::-webkit-scrollbar-track {
        background: #0b0f19;
    }
    .rack-scroll-viewport::-webkit-scrollbar-thumb {
        background: #334155;
        border-radius: 4px;
    }

    .racks-wrapper-flex {
        display: flex;
        justify-content: center;
        align-items: flex-start;
        gap: 36px;
        min-width: min-content;
        margin: 0 auto;
    }

    /* Chasis del Rack (Estructura de Datacenter 19 Pulgadas) */
    .rack-view-panel {
        display: flex;
        flex-direction: column;
        align-items: center;
        transition: opacity 0.2s;
    }

    .rack-view-header {
        margin-bottom: 10px;
        text-align: center;
    }

    .rack-view-header .badge {
        font-size: 0.82rem;
        letter-spacing: 0.5px;
        padding: 6px 14px;
        border-radius: 20px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.3);
    }

    .rack-chassis {
        background: var(--rack-frame-color);
        border: 4px solid var(--rack-border);
        border-radius: 10px;
        padding: 10px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.6), inset 0 0 15px rgba(0,0,0,0.8);
        position: relative;
    }

    .rack-chassis-top {
        background: linear-gradient(180deg, #2b3446 0%, #1a202d 100%);
        border: 1px solid #3b465c;
        border-radius: 6px 6px 0 0;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 4px;
        box-shadow: inset 0 1px 2px rgba(255,255,255,0.15);
    }

    .rack-brand-mark {
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 1.5px;
        color: #cbd5e1;
        text-transform: uppercase;
        text-shadow: 0 1px 2px #000;
    }

    .rack-chassis-bottom {
        background: linear-gradient(180deg, #1a202d 0%, #11151f 100%);
        border: 1px solid #333d52;
        border-radius: 0 0 6px 6px;
        height: 22px;
        margin-top: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .rack-chassis-bottom::after {
        content: '';
        width: 60px;
        height: 4px;
        background: #475569;
        border-radius: 2px;
    }

    .rack-container-wrapper {
        position: relative;
        padding: 0 48px; /* Espacio para canales laterales exteriores de PDU en rear */
        display: inline-block;
    }

    .rack-container {
        width: var(--rack-width);
        background: var(--rack-bg-color);
        border: 2px solid #283347;
        border-radius: 3px;
        position: relative;
        display: flex;
        flex-direction: column;
        box-shadow: inset 0 0 20px rgba(0,0,0,0.9);
    }

    /* Rieles EIA-310 Symmetrical Left & Right */
    .rack-unit {
        height: var(--rack-u-height);
        border-bottom: 1px solid rgba(255,255,255,0.06);
        position: relative;
        display: flex;
        align-items: center;
        background: transparent;
    }

    .rack-u-label {
        width: var(--rack-rail-width);
        height: 100%;
        background: var(--rack-rail-color);
        color: #ffffff !important;
        font-size: 11.5px;
        font-weight: 900;
        font-family: 'Consolas', 'Courier New', monospace;
        display: flex;
        align-items: center;
        justify-content: center;
        user-select: none;
        position: relative;
        flex-shrink: 0;
        border-right: 1px solid #334155;
        border-left: 1px solid #334155;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.95);
    }

    /* Agujeros de fijación de tornillo / tuerca jaula (Cage Nut Holes) */
    .rack-u-label::before, .rack-u-label::after {
        content: '';
        position: absolute;
        width: 3px;
        height: 3px;
        background: #0f141f;
        border-radius: 50%;
        box-shadow: inset 0 1px 1px rgba(0,0,0,0.8), 0 0.5px 0.5px rgba(255,255,255,0.15);
    }
    .rack-u-label.left-label::before { left: 4px; top: 4px; }
    .rack-u-label.left-label::after { left: 4px; bottom: 4px; }
    .rack-u-label.right-label::before { right: 4px; top: 4px; }
    .rack-u-label.right-label::after { right: 4px; bottom: 4px; }

    .rack-slot {
        flex: 1;
        height: 100%;
        position: relative;
        cursor: pointer;
        background: repeating-linear-gradient(45deg, #0d1017, #0d1017 10px, #0f121a 10px, #0f121a 20px);
        border: 1px dashed rgba(255, 255, 255, 0.05);
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.15s, border-color 0.15s;
    }

    #panel-rack-front .cabinet-front {
        width: 550px !important;
        min-width: 550px !important;
        overflow: visible !important;
    }

    #panel-rack-rear .cabinet-front {
        width: 646px !important;
        min-width: 646px !important;
        overflow: visible !important;
    }

    .rack-slot-hint {
        color: #ffffff !important;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: 1px;
        opacity: 0.95;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.95);
        user-select: none;
    }

    /* Regla maestra: Todas las letras y números de los racks en blanco puro de alto contraste */
    .rack-container,
    .rack-container *,
    .rack-chassis,
    .rack-chassis *,
    .cabinet-header-plate,
    .cabinet-footer-plate,
    .device,
    .device *,
    .dev-name-badge,
    .dev-meta,
    .dev-u-tag,
    .pdu-text,
    .rail-text,
    .pdu-rail-placeholder,
    .pdu-rail-placeholder * {
        color: #ffffff !important;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.95);
    }

    /* Equipos Montados (Devices) */
    .device {
        position: absolute;
        left: var(--rack-rail-width);
        right: var(--rack-rail-width);
        z-index: 10;
        background: #1e293b;
        color: #ffffff;
        border: 1px solid rgba(255,255,255,0.18);
        border-radius: 2px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        cursor: pointer;
        box-shadow: 0 2px 6px rgba(0,0,0,0.6), inset 0 1px 0 rgba(255,255,255,0.25);
        overflow: hidden;
        transition: transform 0.1s, box-shadow 0.15s, filter 0.15s;
        padding: 0;
    }

    .device:hover {
        filter: brightness(1.2);
        z-index: 15;
        box-shadow: 0 4px 14px rgba(0,0,0,0.8), 0 0 0 1px #38bdf8;
    }

    .device.selected-device {
        outline: 2px solid #38bdf8 !important;
        box-shadow: 0 0 15px rgba(56, 189, 248, 0.7) !important;
        z-index: 20 !important;
    }

    .device.highlight-matched {
        outline: 2px solid #eab308 !important;
        box-shadow: 0 0 16px rgba(234, 179, 8, 0.8) !important;
        z-index: 25 !important;
        animation: pulseHighlight 1.5s infinite;
    }

    @keyframes pulseHighlight {
        0% { box-shadow: 0 0 0 0 rgba(234, 179, 8, 0.7); }
        70% { box-shadow: 0 0 0 8px rgba(234, 179, 8, 0); }
        100% { box-shadow: 0 0 0 0 rgba(234, 179, 8, 0); }
    }

    .device.dimmed-device {
        opacity: 0.25 !important;
        filter: grayscale(80%);
    }

    /* Orejas de Montaje Frontal (Rack Ears con tornillos) */
    .device::before, .device::after {
        content: '';
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 5px;
        height: 5px;
        background: #94a3b8;
        border-radius: 50%;
        box-shadow: inset 0 1px 1px #fff, 0 1px 2px #000;
        z-index: 12;
    }
    .device::before { left: 3px; }
    .device::after { right: 3px; }

    .device .dev-content-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        overflow: hidden;
        gap: 6px;
    }

    .device .dev-name-badge {
        font-weight: 800;
        font-size: 11.5px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        color: #ffffff !important;
        display: flex;
        align-items: center;
        gap: 5px;
        line-height: 1.2;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.95);
    }

    .device .dev-status-led {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #22c55e;
        box-shadow: 0 0 6px #22c55e;
        flex-shrink: 0;
    }

    .device .dev-meta {
        font-size: 9.5px;
        color: #ffffff !important;
        opacity: 0.95;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-family: 'Consolas', monospace;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.95);
    }

    .device .dev-u-tag {
        font-size: 9px;
        background: rgba(0, 0, 0, 0.7);
        padding: 1px 5px;
        border-radius: 3px;
        color: #ffffff !important;
        font-weight: 800;
        flex-shrink: 0;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .device.depth-half {
        right: calc(var(--rack-rail-width) + (var(--rack-width) - (var(--rack-rail-width) * 2)) * 0.5) !important;
        border-right: 3px dashed #f59e0b !important;
    }
    .device.depth-third {
        right: calc(var(--rack-rail-width) + (var(--rack-width) - (var(--rack-rail-width) * 2)) * 0.66) !important;
        border-right: 3px dashed #ef4444 !important;
    }

    /* PDUs Verticales (0U Mount Lateral sin superponer números de UR) */
    .device.vertical-pdu {
        left: auto !important;
        right: auto !important;
        width: 34px !important;
        z-index: 20 !important;
        color: #ffffff !important;
        border: 1px solid #475569;
        border-radius: 4px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        align-items: center;
        box-shadow: 2px 2px 8px rgba(0,0,0,0.6), inset 0 0 4px rgba(255,255,255,0.4);
        padding: 6px 0;
        box-sizing: border-box;
        background: linear-gradient(to right, #334155, #1e293b, #0f172a);
        overflow: hidden;
    }

    .device.vertical-pdu.left-pdu { left: calc(var(--rack-rail-width) + 2px) !important; width: 34px !important; }
    .device.vertical-pdu.right-pdu { right: calc(var(--rack-rail-width) + 2px) !important; width: 34px !important; }

    .device.vertical-pdu.pdu-dark {
        background: linear-gradient(to right, #334155, #1e293b, #0f172a);
        color: #ffffff !important;
        border-color: #0f172a;
    }

    .device.vertical-pdu .pdu-outlets-container {
        display: flex;
        flex-direction: column;
        gap: 5px;
        align-items: center;
        margin-bottom: auto;
        margin-top: 4px;
        width: 100%;
        max-height: calc(100% - 60px);
        overflow: hidden;
    }

    .device.vertical-pdu .pdu-outlet {
        width: 11px;
        height: 11px;
        background: #020617;
        border-radius: 50%;
        box-shadow: inset 0 0 3px rgba(255,255,255,0.2), 0 1px 1px rgba(255,255,255,0.3);
        position: relative;
        flex-shrink: 0;
    }

    .device.vertical-pdu .pdu-text {
        writing-mode: vertical-rl;
        text-orientation: mixed;
        transform: rotate(180deg);
        font-size: 9.5px;
        font-weight: 900;
        letter-spacing: 0.5px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-height: 80%;
        text-align: center;
        padding: 4px 2px;
        margin-top: auto;
        line-height: 1;
        width: 100%;
        color: #ffffff !important;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.95);
    }

    .pdu-rail-placeholder {
        position: absolute;
        top: 0;
        bottom: 0;
        width: 34px;
        background: rgba(255, 255, 255, 0.05);
        border: 2px dashed rgba(255, 255, 255, 0.2);
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 5;
        transition: all 0.2s;
    }

    .pdu-rail-placeholder:hover {
        background: rgba(34, 197, 94, 0.15);
        border-color: #22c55e;
    }

    .pdu-rail-placeholder.left-rail { left: calc(var(--rack-rail-width) + 2px); width: 34px; }
    .pdu-rail-placeholder.right-rail { right: calc(var(--rack-rail-width) + 2px); width: 34px; }

    /* En formato REAR: PDUs verticales a los extremos exteriores apegadas al rack:
       PDU Ext Izq (-40px) -> Parante UR (0 a 32px) -> Equipos -> Parante UR -> PDU Ext Der */
    #rack-container-rear .device.vertical-pdu.left-pdu {
        left: -40px !important;
        width: 34px !important;
        box-shadow: -2px 2px 8px rgba(0,0,0,0.6), inset 0 0 4px rgba(255,255,255,0.4);
        border-right: 2px solid #38bdf8;
    }
    #rack-container-rear .device.vertical-pdu.right-pdu {
        right: -40px !important;
        width: 34px !important;
        box-shadow: 2px 2px 8px rgba(0,0,0,0.6), inset 0 0 4px rgba(255,255,255,0.4);
        border-left: 2px solid #38bdf8;
    }
    #rack-container-rear .pdu-rail-placeholder.left-rail {
        left: -40px !important;
        width: 34px !important;
    }
    #rack-container-rear .pdu-rail-placeholder.right-rail {
        right: -40px !important;
        width: 34px !important;
    }

    .pdu-rail-placeholder .rail-text {
        writing-mode: vertical-rl;
        text-orientation: mixed;
        transform: rotate(180deg);
        font-size: 9.5px;
        color: #ffffff !important;
        font-weight: 900;
        letter-spacing: 2px;
        user-select: none;
        opacity: 0.9;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.95);
    }

    .pdu-rail-placeholder:hover .rail-text { color: #22c55e !important; }

    /* Panel Lateral Derecho - Tarjeta y Modos */
    .details-area .card {
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 16px rgba(0,0,0,0.05);
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .panel-tab-nav {
        display: flex;
        border-bottom: 1px solid #e2e8f0;
        background: #f8fafc;
        border-radius: 8px 8px 0 0;
    }

    .panel-tab-btn {
        flex: 1;
        text-align: center;
        padding: 10px 14px;
        font-size: 0.85rem;
        font-weight: 700;
        color: #64748b;
        border: none;
        background: transparent;
        cursor: pointer;
        transition: all 0.15s;
        border-bottom: 2px solid transparent;
    }

    .panel-tab-btn.active {
        color: #0284c7;
        background: #ffffff;
        border-bottom-color: #0284c7;
    }

    .panel-tab-btn:hover:not(.active) {
        color: #0f172a;
        background: #f1f5f9;
    }

    .panel-body-scroll {
        flex: 1;
        overflow-y: auto;
        padding: 16px;
        scrollbar-width: thin;
    }

    /* Lista de Inventario de Equipos */
    .device-inventory-item {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 9px 12px;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        cursor: pointer;
        transition: all 0.15s;
    }

    .device-inventory-item:hover {
        border-color: #38bdf8;
        transform: translateX(2px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }

    .device-inventory-item.active-item {
        border-color: #0284c7;
        background: #f0f9ff;
    }

    .dev-item-u {
        font-size: 0.72rem;
        font-weight: 800;
        background: #1e293b;
        color: #ffffff;
        padding: 3px 6px;
        border-radius: 4px;
        font-family: 'Consolas', monospace;
        margin-right: 10px;
        flex-shrink: 0;
    }

    .dev-item-info {
        flex: 1;
        min-width: 0;
    }

    .dev-item-title {
        font-size: 0.85rem;
        font-weight: 700;
        color: #0f172a;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .dev-item-sub {
        font-size: 0.72rem;
        color: #64748b;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Sub-tabs del Formulario */
    .form-pill-nav {
        display: flex;
        gap: 6px;
        margin-bottom: 14px;
        border-bottom: 1px solid #e2e8f0;
        padding-bottom: 8px;
    }

    .form-pill-nav .btn {
        font-size: 0.75rem;
        font-weight: 600;
        padding: 3px 10px;
        border-radius: 20px;
    }

    .form-pill-nav .btn.active {
        background: #0284c7;
        color: #fff;
        border-color: #0284c7;
    }
</style>

<div class="container-fluid pt-3">
    <!-- Flash Messages -->
    <?php if (isset($_SESSION['flash_msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show mb-3 shadow-sm">
            <i class="fas fa-check-circle mr-2"></i> <?php echo htmlspecialchars($_SESSION['flash_msg']); unset($_SESSION['flash_msg']); ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-3 shadow-sm">
            <i class="fas fa-exclamation-triangle mr-2"></i> <?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Header Principal y Breadcrumb -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap: 12px;">
        <div>
            <div class="small text-muted mb-1">
                <a href="rooms.php<?php echo !empty($rack['client']) ? '?cliente=' . urlencode($rack['client']) : ''; ?>" class="text-secondary"><i class="fas fa-cubes mr-1"></i>Salas Datacenter</a>
                <span class="mx-1">/</span>
                <a href="racks.php<?php echo !empty($rack['client']) ? '?cliente=' . urlencode($rack['client']) : ''; ?><?php echo $rack['room_id'] ? '&room_id=' . $rack['room_id'] : ''; ?>" class="text-secondary">Racks</a>
                <span class="mx-1">/</span>
                <span class="text-dark font-weight-bold"><?php echo htmlspecialchars($rack['name']); ?></span>
            </div>
            <h4 class="mb-1 font-weight-bold text-dark d-flex align-items-center flex-wrap" style="gap: 8px;">
                <i class="fas fa-server text-warning"></i> <?php echo htmlspecialchars($rack['name']); ?>
                <?php if (!empty($rack['room_name'])): ?>
                    <span class="badge badge-light border text-secondary font-weight-normal font-size-sm"><i class="fas fa-door-open mr-1"></i><?php echo htmlspecialchars($rack['room_name']); ?></span>
                <?php endif; ?>
                <span class="badge badge-warning text-dark font-weight-bold"><i class="fas fa-building mr-1"></i><?php echo htmlspecialchars($rack['client'] ?: 'VILASECA'); ?></span>
                <?php if (!empty($rack['city'])): ?>
                    <span class="badge badge-light border text-dark"><i class="fas fa-map-marker-alt text-danger mr-1"></i><?php echo htmlspecialchars($rack['city']); ?></span>
                <?php endif; ?>
                <?php if (!empty($rack['location'])): ?>
                    <span class="badge badge-secondary"><i class="fas fa-map-pin mr-1"></i><?php echo htmlspecialchars($rack['location']); ?></span>
                <?php endif; ?>
            </h4>
        </div>
        <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
            <?php if (!empty($rack['room_id'])): ?>
                <a href="floor_plan.php?room_id=<?php echo $rack['room_id']; ?><?php echo !empty($rack['client']) ? '&cliente=' . urlencode($rack['client']) : ''; ?>" class="btn btn-outline-info btn-sm font-weight-bold">
                    <i class="fas fa-th mr-1"></i> Ver en Plano 2D
                </a>
            <?php endif; ?>
            <button type="button" class="btn btn-primary btn-sm font-weight-bold shadow-sm" onclick="openRackView(<?php echo $rack_id; ?>)">
                <i class="fas fa-eye mr-1"></i> Ver Infografía / Exportar Imagen
            </button>
            <button class="btn btn-warning btn-sm font-weight-bold shadow-sm" data-toggle="modal" data-target="#editRackModal">
                <i class="fas fa-edit mr-1"></i> Editar Rack
            </button>
            <a href="racks.php?<?php echo !empty($rack['client']) ? 'cliente=' . urlencode($rack['client']) : ''; ?><?php echo $rack['room_id'] ? '&room_id=' . $rack['room_id'] : ''; ?>" class="btn btn-outline-secondary btn-sm font-weight-bold">
                <i class="fas fa-arrow-left mr-1"></i> Volver a Racks
            </a>
        </div>
    </div>

    <!-- TIRA DE KPIS / MÉTRICAS EJECUTIVAS -->
    <div class="kpi-summary-grid">
        <!-- Ocupación de Espacio -->
        <div class="kpi-card-metric">
            <div class="kpi-metric-icon bg-primary text-white">
                <i class="fas fa-layer-group"></i>
            </div>
            <div class="kpi-metric-body">
                <div class="kpi-metric-label">Capacidad & Ocupación</div>
                <div class="kpi-metric-val" id="kpi-occupancy-text">-- / <?php echo $total_u; ?> U</div>
                <div class="progress mt-1" style="height: 6px; background-color: #e2e8f0;">
                    <div class="progress-bar bg-primary" id="kpi-occupancy-bar" role="progressbar" style="width: 0%;"></div>
                </div>
            </div>
        </div>

        <!-- Disponibilidad Libre -->
        <div class="kpi-card-metric">
            <div class="kpi-metric-icon bg-success text-white">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="kpi-metric-body">
                <div class="kpi-metric-label">Espacio Libre</div>
                <div class="kpi-metric-val text-success" id="kpi-free-u">-- U Libres</div>
                <div class="kpi-metric-sub"><i class="fas fa-arrow-<?php echo $numbering_dir === 'UP' ? 'down' : 'up'; ?> mr-1"></i><?php echo $numbering_dir === 'UP' ? 'U1 Arriba (UP)' : 'U1 Abajo (DOWN)'; ?></div>
            </div>
        </div>

        <!-- Inventario de Equipos -->
        <div class="kpi-card-metric">
            <div class="kpi-metric-icon bg-warning text-dark">
                <i class="fas fa-server"></i>
            </div>
            <div class="kpi-metric-body">
                <div class="kpi-metric-label">Equipos en Rack</div>
                <div class="kpi-metric-val" id="kpi-total-devices">--</div>
                <div class="kpi-metric-sub" id="kpi-devices-breakdown">Cargando catálogo...</div>
            </div>
        </div>

        <!-- Carga Energética Estimada -->
        <div class="kpi-card-metric">
            <div class="kpi-metric-icon bg-danger text-white">
                <i class="fas fa-bolt"></i>
            </div>
            <div class="kpi-metric-body">
                <div class="kpi-metric-label">Carga & Peso Estimado</div>
                <div class="kpi-metric-val text-danger" id="kpi-total-power">0 W / 0 A</div>
                <div class="kpi-metric-sub" id="kpi-total-weight">Peso acumulado: 0 Kg</div>
            </div>
        </div>
    </div>

    <!-- ÁREA PRINCIPAL: CANVAS VISUAL DEL RACK + PANEL DE DETALLES -->
    <div class="builder-layout">
        <!-- COLUMNA IZQUIERDA: CANVAS VISUAL -->
        <div class="rack-main-column">
            <!-- Barra de Herramientas del Canvas -->
            <div class="rack-canvas-toolbar">
                <!-- Selector de Vistas -->
                <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
                    <span class="small font-weight-bold text-muted mr-1"><i class="fas fa-eye mr-1"></i>Vista:</span>
                    <div class="btn-group btn-group-sm view-mode-pills" role="group">
                        <button type="button" class="btn btn-outline-dark active" id="btn-view-both" onclick="setRackView('both')">
                            <i class="fas fa-columns mr-1"></i> Ambos Lados
                        </button>
                        <button type="button" class="btn btn-outline-dark" id="btn-view-front" onclick="setRackView('front')">
                            <i class="fas fa-desktop mr-1"></i> Frente
                        </button>
                        <button type="button" class="btn btn-outline-dark" id="btn-view-rear" onclick="setRackView('rear')">
                            <i class="fas fa-tools mr-1"></i> Detrás
                        </button>
                    </div>

                    <!-- Escala / Zoom -->
                    <div class="btn-group btn-group-sm ml-2" role="group">
                        <button type="button" class="btn btn-outline-secondary" onclick="setZoom(18)" title="Ajustar Altura Completa (Compacto)">
                            <i class="fas fa-compress-arrows-alt mr-1"></i>Ajustar
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="setZoom(22)" title="Zoom 85%">
                            85%
                        </button>
                        <button type="button" class="btn btn-outline-secondary active" id="btn-zoom-100" onclick="setZoom(28)" title="Zoom 100% (Normal)">
                            100%
                        </button>
                    </div>
                </div>

                <!-- Buscador Rápido y Agregar -->
                <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
                    <div class="input-group input-group-sm" style="width: 220px;">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-light border-right-0"><i class="fas fa-search text-muted"></i></span>
                        </div>
                        <input type="text" id="rack-filter-search" class="form-control border-left-0" placeholder="Buscar equipo o IP..." onkeyup="filterRackDevices(this.value)">
                    </div>
                    <button type="button" class="btn btn-success btn-sm font-weight-bold shadow-sm" onclick="openCreateForm(1, 'front')">
                        <i class="fas fa-plus mr-1"></i> + Agregar Equipo
                    </button>
                </div>
            </div>

            <!-- Viewport Scrollable del Canvas -->
            <div class="rack-canvas-card">
                <div class="rack-scroll-viewport" id="rack-viewport">
                    <div class="racks-wrapper-flex" id="racks-wrapper">
                        <!-- VISTA FRENTE -->
                        <div class="rack-view-panel" id="panel-rack-front">
                            <div class="rack-view-header">
                                <span class="badge badge-primary"><i class="fas fa-desktop mr-1"></i> FRENTE (FRONT)</span>
                            </div>
                            <div class="cabinet-3d-wrapper">
                                <div class="cabinet-top-face">
                                    <div class="top-vent-grill">VENTILACIÓN SUPERIOR · EXTRACTORES</div>
                                </div>
                                <div class="cabinet-main-frame">
                                    <div class="cabinet-front">
                                        <div class="cabinet-header-plate">
                                            <span><?php echo htmlspecialchars($rack['name'] . ' · ' . ($rack['location'] ?: $rack['city'])); ?></span>
                                            <span class="badge badge-primary ml-1" style="font-size: 8px;">FRONTAL</span>
                                        </div>
                                        <div class="rack-container" id="rack-container-front">
                                            <!-- Generado con JS -->
                                        </div>
                                        <div class="cabinet-footer-plate">
                                            <span><?php echo htmlspecialchars($total_u . 'U · ' . ($rack['location'] ?: $rack['city']) . ' · VISTA FRONTAL'); ?></span>
                                        </div>
                                    </div>
                                    <div class="cabinet-right-side"></div>
                                </div>
                            </div>
                        </div>

                        <!-- VISTA DETRÁS -->
                        <div class="rack-view-panel" id="panel-rack-rear">
                            <div class="rack-view-header">
                                <span class="badge badge-secondary"><i class="fas fa-tools mr-1"></i> DETRÁS (REAR)</span>
                            </div>
                            <div class="cabinet-3d-wrapper">
                                <div class="cabinet-top-face">
                                    <div class="top-vent-grill">VENTILACIÓN TRASERA · EXTRACTORES</div>
                                </div>
                                <div class="cabinet-main-frame">
                                    <div class="cabinet-front rear-cabinet">
                                        <div class="cabinet-header-plate">
                                            <span><?php echo htmlspecialchars($rack['name'] . ' · ' . ($rack['location'] ?: $rack['city'])); ?></span>
                                            <span class="badge badge-info ml-1" style="font-size: 8px;">POSTERIOR</span>
                                        </div>
                                        <div class="rack-container-wrapper">
                                            <div class="rack-container" id="rack-container-rear">
                                                <!-- Generado con JS -->
                                            </div>
                                        </div>
                                        <div class="cabinet-footer-plate">
                                            <span><?php echo htmlspecialchars($total_u . 'U · ACCESO TRASERO'); ?></span>
                                        </div>
                                    </div>
                                    <div class="cabinet-right-side"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- COLUMNA DERECHA: PANEL INTELIGENTE DUAL (INVENTARIO & EDITOR) -->
        <div class="details-area">
            <div class="card h-100">
                <!-- Pestañas Superiores del Panel -->
                <div class="panel-tab-nav">
                    <button type="button" class="panel-tab-btn active" id="tab-btn-inventory" onclick="switchRightPanelTab('inventory')">
                        <i class="fas fa-list-ul mr-1"></i> Inventario (<span id="tab-inventory-count">0</span>)
                    </button>
                    <button type="button" class="panel-tab-btn" id="tab-btn-editor" onclick="switchRightPanelTab('editor')">
                        <i class="fas fa-sliders-h mr-1"></i> Detalle / Editor
                    </button>
                </div>

                <div class="panel-body-scroll">
                    <!-- VISTA 1: INVENTARIO DE EQUIPOS EN EL RACK -->
                    <div id="panel-view-inventory">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="small font-weight-bold text-muted">Equipos colocados en el bastidor:</span>
                            <button type="button" class="btn btn-outline-primary btn-xs font-weight-bold" onclick="openCreateForm(1, 'front')">
                                <i class="fas fa-plus mr-1"></i> Nuevo Equipo
                            </button>
                        </div>
                        <div id="inventory-list-container">
                            <!-- Se llena con JS -->
                        </div>
                    </div>

                    <!-- VISTA 2: FORMULARIO DE EDICIÓN / DETALLE -->
                    <div id="panel-view-editor" class="d-none">
                        <!-- Encabezado del Formulario -->
                        <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                            <h6 class="mb-0 font-weight-bold text-dark" id="form-title">
                                <i class="fas fa-info-circle mr-1 text-primary"></i> Seleccione un equipo o slot
                            </h6>
                            <button type="button" class="btn btn-outline-danger btn-xs d-none" id="btn-delete-device">
                                <i class="fas fa-trash mr-1"></i> Eliminar
                            </button>
                        </div>

                        <!-- Mensaje de Bienvenida cuando no hay slot/equipo seleccionado -->
                        <div id="welcome-msg" class="text-center py-4 text-muted">
                            <i class="fas fa-hand-pointer fa-2x mb-2 text-primary"></i>
                            <h6 class="font-weight-bold text-dark">¿Cómo agregar o editar equipos?</h6>
                            <p class="small text-muted px-2">Haz clic en cualquier <strong>U libre</strong> del bastidor visual para crear un equipo, o en un <strong>equipo existente</strong> para editar sus propiedades.</p>
                            <button type="button" class="btn btn-primary btn-sm font-weight-bold mt-2" onclick="openCreateForm(1, 'front')">
                                <i class="fas fa-plus mr-1"></i> Crear en U1
                            </button>
                        </div>

                        <!-- Formulario de Guardado / Edición -->
                        <form id="device-form" class="d-none">
                            <input type="hidden" name="action" value="save_device">
                            <input type="hidden" name="id" id="dev_id" value="0">
                            <input type="hidden" name="rack_id" value="<?php echo $rack_id; ?>">

                            <!-- Sub-tabs del Formulario -->
                            <div class="form-pill-nav">
                                <button type="button" class="btn btn-outline-primary active" id="pill-btn-general" onclick="switchFormPill('general')">
                                    <i class="fas fa-th mr-1"></i> General
                                </button>
                                <button type="button" class="btn btn-outline-primary" id="pill-btn-specs" onclick="switchFormPill('specs')">
                                    <i class="fas fa-microchip mr-1"></i> Activo & Red
                                </button>
                                <button type="button" class="btn btn-outline-primary" id="pill-btn-power" onclick="switchFormPill('power')">
                                    <i class="fas fa-bolt mr-1"></i> Energía
                                </button>
                            </div>

                            <!-- SECCIÓN 1: GENERAL & UBICACIÓN -->
                            <div id="form-section-general">
                                <div class="form-group mb-2">
                                    <label class="small font-weight-bold mb-1">Nombre del Equipo / Hostname <span class="text-danger">*</span></label>
                                    <input type="text" name="name" id="dev_name" class="form-control form-control-sm font-weight-bold" required placeholder="Ej. SW-CORE-01">
                                </div>

                                <div class="form-group mb-2">
                                    <label class="small font-weight-bold mb-1">Vincular con CI CMDB</label>
                                    <select name="cmdb_reference" id="dev_cmdb_reference" class="form-control form-control-sm select2-cmdb">
                                        <option value="">-- No vinculado (Manual) --</option>
                                    </select>
                                    <small class="form-text text-muted" style="font-size: 0.72rem;">Sincroniza automáticamente imágenes frontales, marca y atributos.</small>
                                </div>

                                <div class="row">
                                    <div class="col-6 form-group mb-2">
                                        <label class="small font-weight-bold mb-1">Posición Inicial (U)</label>
                                        <input type="number" name="start_u" id="dev_start_u" class="form-control form-control-sm font-weight-bold" required min="1" max="<?php echo $total_u; ?>">
                                    </div>
                                    <div class="col-6 form-group mb-2">
                                        <label class="small font-weight-bold mb-1">Altura (Cantidad U)</label>
                                        <input type="number" name="height_u" id="dev_height_u" class="form-control form-control-sm font-weight-bold" required min="1" max="<?php echo $total_u; ?>">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-6 form-group mb-2">
                                        <label class="small font-weight-bold mb-1">Lado / Orientación</label>
                                        <select name="orientation" id="dev_orientation" class="form-control form-control-sm">
                                            <option value="front">Frente (Front)</option>
                                            <option value="rear">Detrás (Rear)</option>
                                            <option value="both">Ambos Lados (Both)</option>
                                        </select>
                                    </div>
                                    <div class="col-6 form-group mb-2">
                                        <label class="small font-weight-bold mb-1">Profundidad</label>
                                        <select name="depth" id="dev_depth" class="form-control form-control-sm">
                                            <option value="full">Completa (Full)</option>
                                            <option value="half">Media (1/2)</option>
                                            <option value="third">Un Tercio (1/3)</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group mb-2">
                                    <label class="small font-weight-bold mb-1">Tipo de Montaje</label>
                                    <select name="mounting" id="dev_mounting" class="form-control form-control-sm">
                                        <option value="horizontal">Horizontal Estándar (19")</option>
                                        <option value="vertical_left">Vertical A (PDU Lateral Externa)</option>
                                        <option value="vertical_right">Vertical B (PDU Lateral Interna)</option>
                                    </select>
                                </div>

                                <div class="form-group mb-2">
                                    <label class="small font-weight-bold mb-1">Color Frontal</label>
                                    <div class="d-flex align-items-center" style="gap: 8px;">
                                        <input type="color" name="color" id="dev_color" class="form-control form-control-sm p-0 border-0" style="width: 38px; height: 31px; cursor: pointer;" value="#2a2a2a">
                                        <span class="small text-muted">Personaliza el fondo visual del bastidor</span>
                                    </div>
                                </div>
                            </div>

                            <!-- SECCIÓN 2: DATOS DEL ACTIVO & RED -->
                            <div id="form-section-specs" class="d-none">
                                <div class="mb-2">
                                    <div class="btn-group btn-group-toggle w-100 btn-group-sm" data-toggle="buttons">
                                        <label class="btn btn-outline-secondary active" id="lbl-source-manual">
                                            <input type="radio" name="source" id="source_manual" value="manual" checked> <i class="fas fa-keyboard mr-1"></i> Manual
                                        </label>
                                        <label class="btn btn-outline-secondary" id="lbl-source-zabbix">
                                            <input type="radio" name="source" id="source_zabbix" value="zabbix"> <i class="fas fa-cloud-download-alt mr-1"></i> Importar Zabbix
                                        </label>
                                    </div>
                                </div>

                                <div id="zabbix-selection-area" class="d-none border p-2 bg-light mb-2 rounded">
                                    <div class="form-group mb-1">
                                        <label class="small font-weight-bold mb-0">Hostgroup</label>
                                        <select id="zabbix_hostgroup" class="form-control form-control-sm">
                                            <option value="">Cargando hostgroups...</option>
                                        </select>
                                    </div>
                                    <div class="form-group mb-2">
                                        <label class="small font-weight-bold mb-0">Host</label>
                                        <select id="zabbix_host" class="form-control form-control-sm select2-zabbix" disabled>
                                            <option value="">Seleccione grupo primero</option>
                                        </select>
                                    </div>
                                    <button type="button" class="btn btn-xs btn-info w-100" id="btn-fetch-zabbix" disabled>
                                        <i class="fas fa-download mr-1"></i> Traer Datos de Zabbix
                                    </button>
                                </div>

                                <div class="row">
                                    <div class="col-6 form-group mb-2">
                                        <label class="small font-weight-bold mb-1">Marca</label>
                                        <input type="text" name="make" id="dev_make" class="form-control form-control-sm" placeholder="Ej. Cisco, HP">
                                    </div>
                                    <div class="col-6 form-group mb-2">
                                        <label class="small font-weight-bold mb-1">Modelo</label>
                                        <input type="text" name="model" id="dev_model" class="form-control form-control-sm" placeholder="Ej. Catalyst 9200">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-6 form-group mb-2">
                                        <label class="small font-weight-bold mb-1">Dirección IP</label>
                                        <input type="text" name="ip_address" id="dev_ip" class="form-control form-control-sm font-weight-bold" placeholder="192.168.1.1">
                                    </div>
                                    <div class="col-6 form-group mb-2">
                                        <label class="small font-weight-bold mb-1">Asset Tag</label>
                                        <input type="text" name="asset_tag" id="dev_asset_tag" class="form-control form-control-sm">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-6 form-group mb-2">
                                        <label class="small font-weight-bold mb-1">Número de Serie</label>
                                        <input type="text" name="serial_number" id="dev_serial_number" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-6 form-group mb-2">
                                        <label class="small font-weight-bold mb-1">Propietario</label>
                                        <input type="text" name="owner" id="dev_owner" class="form-control form-control-sm">
                                    </div>
                                </div>

                                <div class="form-group mb-2">
                                    <label class="small font-weight-bold mb-1">Función / Propósito</label>
                                    <input type="text" name="server_function" id="dev_server_function" class="form-control form-control-sm" placeholder="Ej. Switch de Distribución">
                                </div>
                            </div>

                            <!-- SECCIÓN 3: ENERGÍA & FÍSICO -->
                            <div id="form-section-power" class="d-none">
                                <div class="row">
                                    <div class="col-6 form-group mb-2">
                                        <label class="small font-weight-bold mb-1">Potencia (Watts)</label>
                                        <input type="number" name="watts" id="dev_watts" class="form-control form-control-sm" placeholder="Ej. 350">
                                    </div>
                                    <div class="col-6 form-group mb-2">
                                        <label class="small font-weight-bold mb-1">Amperaje (A)</label>
                                        <input type="number" step="0.1" name="amps" id="dev_amps" class="form-control form-control-sm" placeholder="Ej. 1.6">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-6 form-group mb-2">
                                        <label class="small font-weight-bold mb-1">Voltaje (V)</label>
                                        <input type="number" name="voltage" id="dev_voltage" class="form-control form-control-sm" placeholder="110 / 220">
                                    </div>
                                    <div class="col-6 form-group mb-2">
                                        <label class="small font-weight-bold mb-1">Peso (Kg)</label>
                                        <input type="number" step="0.1" name="weight" id="dev_weight" class="form-control form-control-sm" placeholder="Ej. 6.5">
                                    </div>
                                </div>

                                <!-- Tomas de PDU (solo visible si mounting es vertical) -->
                                <div id="pdu-outlets-section" class="border-top pt-2 mt-2 d-none">
                                    <span class="small font-weight-bold text-primary d-block mb-2"><i class="fas fa-plug mr-1"></i> Tomas de Salida (Outlets PDU)</span>
                                    <div class="row">
                                        <div class="col-4 form-group mb-2">
                                            <label class="small mb-1">C13</label>
                                            <input type="number" name="outlets_c13" id="dev_outlets_c13" class="form-control form-control-sm" placeholder="Ej. 24" min="0">
                                        </div>
                                        <div class="col-4 form-group mb-2">
                                            <label class="small mb-1">C19</label>
                                            <input type="number" name="outlets_c19" id="dev_outlets_c19" class="form-control form-control-sm" placeholder="Ej. 4" min="0">
                                        </div>
                                        <div class="col-4 form-group mb-2">
                                            <label class="small mb-1">NEMA</label>
                                            <input type="number" name="outlets_nema" id="dev_outlets_nema" class="form-control form-control-sm" placeholder="Ej. 0" min="0">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Botones de Acción del Formulario -->
                            <div class="form-group text-right mt-3 border-top pt-3 mb-0">
                                <button type="button" class="btn btn-secondary btn-sm mr-1 font-weight-bold" id="btn-cancel" onclick="resetForm()">
                                    Cancelar
                                </button>
                                <button type="submit" class="btn btn-success btn-sm font-weight-bold px-3">
                                    <i class="fas fa-save mr-1"></i> Guardar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL EDITAR PROPIEDADES DEL RACK -->
<div class="modal fade" id="editRackModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header bg-warning text-dark py-3">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-edit mr-2"></i> Editar Propiedades del Rack</h5>
                <button type="button" class="close text-dark" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_rack">
                <div class="modal-body p-4 text-left">
                    <div class="alert alert-info py-2 px-3 small mb-3">
                        <i class="fas fa-info-circle mr-1"></i> <strong>Cliente, Ciudad, Ubicación y Nombre del Rack</strong> son obligatorios y únicos en combinación.
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="text-dark font-weight-bold small">Cliente <span class="text-danger">*</span></label>
                            <input type="text" name="client" class="form-control font-weight-bold text-uppercase" 
                                   value="<?php echo htmlspecialchars($rack['client'] ?: 'VILASECA'); ?>" 
                                   <?php echo $is_vilaseca_only_user ? 'readonly' : ''; ?> required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="text-dark font-weight-bold small">Ciudad <span class="text-danger">*</span></label>
                            <input type="text" name="city" list="list_builder_cities" class="form-control font-weight-bold" 
                                   value="<?php echo htmlspecialchars($rack['city'] ?: ''); ?>" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="text-dark font-weight-bold small">Ubicación / Sede <span class="text-danger">*</span></label>
                            <input type="text" name="location" list="list_builder_locations" class="form-control font-weight-bold" 
                                   value="<?php echo htmlspecialchars($rack['location'] ?: ''); ?>" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="text-dark font-weight-bold small">Nombre del Rack <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control font-weight-bold" required value="<?php echo htmlspecialchars($rack['name']); ?>">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="text-dark font-weight-bold small">Cuarto / Sala (Room)</label>
                            <select name="room_id" class="form-control font-weight-bold">
                                <option value="0">-- Sin Asignar a Sala --</option>
                                <?php 
                                $roomsSql = "SELECT id, name, location FROM dc_rooms WHERE 1=1";
                                if (!empty($rack['client'])) {
                                    $roomsSql .= " AND (UPPER(client) = UPPER(" . $pdo->quote($rack['client']) . ") OR client IS NULL OR client = '')";
                                }
                                $roomsSql .= " ORDER BY name ASC";
                                $rooms = $pdo->query($roomsSql)->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($rooms as $rm): 
                                ?>
                                    <option value="<?php echo $rm['id']; ?>" <?php echo $rack['room_id'] == $rm['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($rm['name'] . ($rm['location'] ? ' (' . $rm['location'] . ')' : '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="text-dark font-weight-bold small">Capacidad (U) <span class="text-danger">*</span></label>
                            <input type="number" name="total_u" class="form-control font-weight-bold" min="1" max="100" required value="<?php echo $total_u; ?>">
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="text-dark font-weight-bold small">Numeración (U1)</label>
                            <select name="numbering_dir" class="form-control font-weight-bold">
                                <option value="DOWN" <?php echo $numbering_dir === 'DOWN' ? 'selected' : ''; ?>>Abajo (U1 abajo)</option>
                                <option value="UP" <?php echo $numbering_dir === 'UP' ? 'selected' : ''; ?>>Arriba (U1 arriba)</option>
                            </select>
                        </div>
                        <div class="col-md-12 form-group mb-0">
                            <label class="text-dark font-weight-bold small">Descripción / Propósito</label>
                            <textarea name="description" class="form-control" rows="2"><?php echo htmlspecialchars($description); ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm font-weight-bold" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning btn-sm text-dark font-weight-bold px-3">
                        <i class="fas fa-save mr-1"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Datalists para Autocompletado del Modal -->
<datalist id="list_builder_cities">
    <?php foreach ($knownCities as $c): ?>
        <option value="<?php echo htmlspecialchars($c); ?>"></option>
    <?php endforeach; ?>
</datalist>

<datalist id="list_builder_locations">
    <?php foreach ($knownLocations as $l): ?>
        <option value="<?php echo htmlspecialchars($l); ?>"></option>
    <?php endforeach; ?>
</datalist>

<script>
    const TOTAL_U = <?php echo $total_u; ?>;
    const RACK_ID = <?php echo $rack_id; ?>;
    const NUMBERING_DIR = '<?php echo $numbering_dir; ?>';
    let currentUHeight = 28; // Altura de U dinámica según Zoom (18, 22 o 28px)
    let devices = [];
    let cmdbInstances = [];
    let activeDeviceId = null;
    let activeViewMode = 'both';

    // Drag & Drop HTML5 Helpers
    function allowDrop(ev) {
        ev.preventDefault();
        $(ev.target).closest('.rack-slot').css('background-color', 'rgba(34, 197, 94, 0.35)');
    }

    function dragLeave(ev) {
        $(ev.target).closest('.rack-slot').css('background-color', '');
    }

    function drag(ev, devId) {
        ev.dataTransfer.setData("text", devId);
    }

    function drop(ev, targetU, side) {
        ev.preventDefault();
        $('.rack-slot').css('background-color', '');
        let devId = ev.dataTransfer.getData("text");
        if (devId) {
            $.post('api.php', {
                action: 'update_device_u_position',
                id: devId,
                start_u: targetU,
                orientation: side
            }, function(res) {
                if (res.success) {
                    toastr.success(res.message);
                    loadDevices();
                } else {
                    toastr.error(res.message);
                }
            }, 'json').fail(function() {
                toastr.error('Error al mover el equipo.');
            });
        }
    }

    $(document).ready(function() {
        $('#rack-container-front').css('position', 'relative');
        $('#rack-container-rear').css('position', 'relative');

        // Inicializar Select2 para CMDB
        $('.select2-cmdb').select2({
            theme: 'bootstrap4',
            placeholder: 'Seleccione un CI para vincular...'
        });

        // Inicializar Select2 para Zabbix
        $('.select2-zabbix').select2({
            theme: 'bootstrap4',
            placeholder: 'Seleccione un equipo de Zabbix...'
        });

        buildRackGrid();
        loadCMDBInstances();
        loadDevices();

        // Envío AJAX del formulario de equipo
        $('#device-form').on('submit', function(e) {
            e.preventDefault();
            $.ajax({
                url: 'api.php',
                method: 'POST',
                data: $(this).serialize(),
                success: function(res) {
                    if (res.success) {
                        toastr.success(res.message);
                        loadDevices();
                        resetForm();
                        switchRightPanelTab('inventory');
                    } else {
                        toastr.error(res.message);
                    }
                },
                error: function(xhr) {
                    toastr.error('Error de servidor al guardar equipo.');
                    console.error(xhr.responseText);
                }
            });
        });

        $('#btn-delete-device').click(function() {
            let id = $('#dev_id').val();
            if (!id || id == 0) return;
            let dev = devices.find(d => d.id == id);
            let name = dev ? dev.name : 'este equipo';
            if (confirm(`¿Seguro que deseas eliminar el equipo "${name}" del rack?`)) {
                $.post('api.php', {action: 'delete_device', id: id}, function(res) {
                    if (res.success) {
                        toastr.success(res.message);
                        loadDevices();
                        resetForm();
                        switchRightPanelTab('inventory');
                    } else {
                        toastr.error(res.message || 'Error desconocido al eliminar.');
                    }
                }, 'json').fail(function(xhr) {
                    toastr.error('Error de servidor al eliminar.');
                    console.error(xhr.responseText);
                });
            }
        });

        // Toggle Zabbix vs Manual
        $('input[name="source"]').change(function() {
            if ($(this).val() === 'zabbix') {
                $('#zabbix-selection-area').removeClass('d-none');
                loadZabbixHostgroups();
            } else {
                $('#zabbix-selection-area').addClass('d-none');
            }
        });

        $('#zabbix_hostgroup').change(function() {
            let gid = $(this).val();
            loadZabbixHosts(gid || '');
        });

        $('#zabbix_host').change(function() {
            $('#btn-fetch-zabbix').prop('disabled', !$(this).val());
        });

        $('#btn-fetch-zabbix').click(function() {
            let selectedOption = $('#zabbix_host option:selected');
            if (selectedOption.val()) {
                $('#dev_name').val(selectedOption.data('name') || '');
                $('#dev_ip').val(selectedOption.data('ip') || '');
                $('#dev_make').val(selectedOption.data('make') || '');
                $('#dev_model').val(selectedOption.data('model') || '');
                $('#dev_serial_number').val(selectedOption.data('serial') || '');
                $('#dev_asset_tag').val(selectedOption.data('asset-tag') || '');
                $('#dev_owner').val(selectedOption.data('owner') || '');
                $('#dev_server_function').val(selectedOption.data('notes') || '');
                toastr.success('Datos importados desde Zabbix.');
            }
        });

        // Sincronización al seleccionar CI de la CMDB
        $('#dev_cmdb_reference').on('change', function() {
            let ciId = $(this).val();
            if (ciId) {
                let ci = cmdbInstances.find(c => c.id == ciId);
                if (ci) {
                    $('#dev_name').val(ci.hostname);
                    let attrs = {};
                    try { attrs = JSON.parse(ci.attributes_json); } catch(e) {}
                    let ip = ci.ip_address || attrs.ip_address || attrs.ip || '';
                    $('#dev_ip').val(ip);
                    $('#dev_make').val(attrs.marca || attrs.make || '');
                    $('#dev_model').val(attrs.modelo || attrs.model || '');
                    $('#dev_serial_number').val(attrs.serial_number || attrs.serial || '');
                    $('#dev_asset_tag').val(attrs.asset_tag || '');
                    $('#dev_owner').val(attrs.owner || attrs.propietario || '');

                    if (attrs.rack_height_u) $('#dev_height_u').val(attrs.rack_height_u);
                    if (attrs.rack_orientation) $('#dev_orientation').val(attrs.rack_orientation);
                    if (attrs.rack_color) $('#dev_color').val(attrs.rack_color);
                    if (attrs.rack_depth) $('#dev_depth').val(attrs.rack_depth);
                    if (attrs.rack_mounting) $('#dev_mounting').val(attrs.rack_mounting);
                    if (attrs.rack_outlets_c13) $('#dev_outlets_c13').val(attrs.rack_outlets_c13);
                    if (attrs.rack_outlets_c19) $('#dev_outlets_c19').val(attrs.rack_outlets_c19);
                    if (attrs.rack_outlets_nema) $('#dev_outlets_nema').val(attrs.rack_outlets_nema);
                    togglePduOutletsSection();
                }
            }
        });

        $('#dev_mounting').on('change', togglePduOutletsSection);
    });

    // Control de Vistas (Ambos, Frente, Detrás)
    function setRackView(mode) {
        activeViewMode = mode;
        $('.view-mode-pills .btn').removeClass('active');
        $(`#btn-view-${mode}`).addClass('active');

        if (mode === 'front') {
            $('#panel-rack-front').removeClass('d-none');
            $('#panel-rack-rear').addClass('d-none');
        } else if (mode === 'rear') {
            $('#panel-rack-front').addClass('d-none');
            $('#panel-rack-rear').removeClass('d-none');
        } else {
            $('#panel-rack-front').removeClass('d-none');
            $('#panel-rack-rear').removeClass('d-none');
        }
    }

    // Control de Zoom / Escala de U
    function setZoom(uHeight) {
        currentUHeight = uHeight;
        document.documentElement.style.setProperty('--rack-u-height', uHeight + 'px');
        
        $('.zoom-pills .btn, .rack-canvas-toolbar .btn-group .btn').removeClass('active');
        if (uHeight === 28) $('#btn-zoom-100').addClass('active');

        buildRackGrid();
        renderDevices();
    }

    // Construcción de la rejilla con rieles simétricos izquierda/derecha
    function buildRackGrid() {
        let frontContainer = $('#rack-container-front');
        let rearContainer = $('#rack-container-rear');
        frontContainer.empty();
        rearContainer.empty();

        // Rieles de PDU en frontal y posterior
        frontContainer.append(`
            <div class="pdu-rail-placeholder left-rail" onclick="openCreateForm(1, 'front', 'vertical_left')" title="Click para agregar PDU A (Lateral Externa)">
                <div class="rail-text">PDU A</div>
            </div>
            <div class="pdu-rail-placeholder right-rail" onclick="openCreateForm(1, 'front', 'vertical_right')" title="Click para agregar PDU B (Lateral Interna)">
                <div class="rail-text">PDU B</div>
            </div>
        `);

        rearContainer.append(`
            <div class="pdu-rail-placeholder left-rail" onclick="openCreateForm(1, 'rear', 'vertical_left')" title="Click para agregar PDU A (Lateral Externa)">
                <div class="rail-text">PDU A (EXTERNA)</div>
            </div>
            <div class="pdu-rail-placeholder right-rail" onclick="openCreateForm(1, 'rear', 'vertical_right')" title="Click para agregar PDU B (Lateral Interna)">
                <div class="rail-text">PDU B (INTERNA)</div>
            </div>
        `);

        let units = [];
        if (NUMBERING_DIR === 'UP') {
            for (let u = 1; u <= TOTAL_U; u++) units.push(u);
        } else {
            for (let u = TOTAL_U; u >= 1; u--) units.push(u);
        }

        units.forEach(u => {
            let uHtmlFront = `
                <div class="rack-unit" data-u="${u}">
                    <div class="rack-u-label left-label">${u}</div>
                    <div class="rack-slot" onclick="openCreateForm(${u}, 'front')" ondragover="allowDrop(event)" ondragleave="dragLeave(event)" ondrop="drop(event, ${u}, 'front')">
                        <span class="rack-slot-hint">VACÍO · DISPONIBLE</span>
                    </div>
                    <div class="rack-u-label right-label">${u}</div>
                </div>
            `;
            let uHtmlRear = `
                <div class="rack-unit" data-u="${u}">
                    <div class="rack-u-label left-label">${u}</div>
                    <div class="rack-slot" onclick="openCreateForm(${u}, 'rear')" ondragover="allowDrop(event)" ondragleave="dragLeave(event)" ondrop="drop(event, ${u}, 'rear')">
                        <span class="rack-slot-hint">VACÍO [ACCESO TRASERO]</span>
                    </div>
                    <div class="rack-u-label right-label">${u}</div>
                </div>
            `;
            frontContainer.append(uHtmlFront);
            rearContainer.append(uHtmlRear);
        });
    }

    function loadDevices() {
        $.get(`api.php?action=get_devices&rack_id=${RACK_ID}`, function(res) {
            if (res.success) {
                devices = res.data;
                renderDevices();
                renderInventoryList();
                updateRackKPIs();
            }
        });
    }

    // Render de equipos en los bastidores visuales
    function renderDevices() {
        $('.device').remove();

        // Determinar presencia de PDUs verticales por cara para ajustar márgenes de equipos centrales
        let hasLeftPduFront = devices.some(d => (d.details && (d.details.mounting === 'vertical_left' || d.details.is_vertical)) && (d.orientation === 'front' || d.orientation === 'both' || !d.details.depth || d.details.depth === 'full'));
        let hasRightPduFront = devices.some(d => (d.details && (d.details.mounting === 'vertical_right')) && (d.orientation === 'front' || d.orientation === 'both' || !d.details.depth || d.details.depth === 'full'));

        let hasLeftPduRear = devices.some(d => (d.details && (d.details.mounting === 'vertical_left' || d.details.is_vertical)) && (d.orientation === 'rear' || d.orientation === 'both' || !d.details.depth || d.details.depth === 'full'));
        let hasRightPduRear = devices.some(d => (d.details && (d.details.mounting === 'vertical_right')) && (d.orientation === 'rear' || d.orientation === 'both' || !d.details.depth || d.details.depth === 'full'));

        let frontLeftInset = hasLeftPduFront ? 'calc(var(--rack-rail-width) + 38px)' : 'var(--rack-rail-width)';
        let frontRightInset = hasRightPduFront ? 'calc(var(--rack-rail-width) + 38px)' : 'var(--rack-rail-width)';

        // En REAR, las PDUs verticales se colocan por fuera del bastidor apegadas a los parantes:
        // PDU Exterior Izq -> Parante UR Izq -> Equipos Centrales -> Parante UR Der -> PDU Exterior Der
        // Los equipos centrales en Rear ocupan el espacio estándar entre los parantes sin reducirse
        let rearLeftInset = 'var(--rack-rail-width)';
        let rearRightInset = 'var(--rack-rail-width)';

        $('#rack-container-front .pdu-rail-placeholder.left-rail').toggle(!hasLeftPduFront);
        $('#rack-container-front .pdu-rail-placeholder.right-rail').toggle(!hasRightPduFront);
        $('#rack-container-rear .pdu-rail-placeholder.left-rail').toggle(!hasLeftPduRear);
        $('#rack-container-rear .pdu-rail-placeholder.right-rail').toggle(!hasRightPduRear);

        devices.forEach(dev => {
            let startU = parseInt(dev.start_u);
            let heightU = parseInt(dev.height_u) || 1;
            let orientation = dev.orientation || 'front';
            let depth = (dev.details && dev.details.depth) || 'full';
            let mounting = (dev.details && dev.details.mounting) || 'horizontal';

            if (startU > TOTAL_U) return;

            let heightPx = heightU * currentUHeight;
            let positionStyle = '';

            if (NUMBERING_DIR === 'UP') {
                let topPx = (startU - 1) * currentUHeight;
                positionStyle = `top: ${topPx}px;`;
            } else {
                let bottomPx = (startU - 1) * currentUHeight;
                positionStyle = `bottom: ${bottomPx}px;`;
            }

            let bgColor = (dev.details && dev.details.color) ? dev.details.color : '#1e293b';

            let showFront = false;
            let showRear = false;

            if (depth === 'full') {
                showFront = true;
                showRear = true;
            } else {
                if (orientation === 'front' || orientation === 'both') showFront = true;
                if (orientation === 'rear' || orientation === 'both') showRear = true;
            }

            if (mounting === 'vertical_left' || mounting === 'vertical_right') {
                let isLeft = (mounting === 'vertical_left');
                let sideClass = isLeft ? 'left-pdu' : 'right-pdu';
                let pduColorClass = (bgColor === '#2a2a2a' || bgColor === '#000000' || bgColor === 'rgb(42, 42, 42)') ? 'pdu-dark' : '';
                let pduTotalHeightPx = TOTAL_U * currentUHeight;
                let pduPositionStyle = 'top: 0px;';

                let outletCount = parseInt(dev.details.outlets_c13 || 0) + 
                                  parseInt(dev.details.outlets_c19 || 0) + 
                                  parseInt(dev.details.outlets_nema || 0);
                if (outletCount <= 0) outletCount = Math.max(6, Math.floor(TOTAL_U * 0.45));

                let outletsHtml = '';
                for (let i = 0; i < outletCount; i++) {
                    outletsHtml += '<div class="pdu-outlet" title="Toma de Energía"></div>';
                }

                if (showFront) {
                    let pduFront = $(`
                        <div class="device vertical-pdu ${sideClass} ${pduColorClass}" 
                             id="dev-elem-front-${dev.id}"
                             draggable="true"
                             ondragstart="drag(event, ${dev.id})"
                             style="${pduPositionStyle} height: ${pduTotalHeightPx}px; background-color: ${bgColor};" 
                             onclick="openEditForm(${dev.id})"
                             title="${dev.name} [PDU Vertical - Frontal]">
                             <div class="pdu-outlets-container">${outletsHtml}</div>
                             <div class="pdu-text" style="color: #ffffff !important;">${dev.name}</div>
                        </div>
                    `);
                    $('#rack-container-front').append(pduFront);
                }

                if (showRear) {
                    let pduRear = $(`
                        <div class="device vertical-pdu ${sideClass} ${pduColorClass}" 
                             id="dev-elem-rear-${dev.id}"
                             draggable="true"
                             ondragstart="drag(event, ${dev.id})"
                             style="${pduPositionStyle} height: ${pduTotalHeightPx}px; background-color: ${bgColor};" 
                             onclick="openEditForm(${dev.id})"
                             title="${dev.name} [PDU Vertical Exterior - Posterior]">
                             <div class="pdu-outlets-container">${outletsHtml}</div>
                             <div class="pdu-text" style="color: #ffffff !important;">${dev.name}</div>
                        </div>
                    `);
                    $('#rack-container-rear').append(pduRear);
                }
            } else {
                let depthLabel = '';
                let depthClass = '';
                if (depth === 'half') {
                    depthLabel = ' [1/2]';
                    depthClass = 'depth-half';
                } else if (depth === 'third') {
                    depthLabel = ' [1/3]';
                    depthClass = 'depth-third';
                }

                let devModel = (dev.details && dev.details.model) ? dev.details.model : '';
                let devMake = (dev.details && dev.details.make) ? dev.details.make : '';
                let devIp = (dev.details && dev.details.ip_address) ? dev.details.ip_address : '';
                let metaText = [devMake, devModel].filter(Boolean).join(' ') + (devIp ? ` (${devIp})` : '') + depthLabel;

                // Render Front
                if (showFront) {
                    let frontImage = dev.cmdb_imagen_frontal || '';
                    if (frontImage && !frontImage.startsWith('http') && !frontImage.startsWith('/')) frontImage = '../' + frontImage;

                    let devElem = $(`
                        <div class="device ${depthClass}" 
                             id="dev-elem-front-${dev.id}"
                             draggable="true"
                             ondragstart="drag(event, ${dev.id})"
                             style="${positionStyle} height: ${heightPx}px; left: ${frontLeftInset}; right: ${frontRightInset};" 
                             onclick="openEditForm(${dev.id})"
                             title="${dev.name} - U${startU} (${heightU}U)">
                        </div>
                    `);

                    if (frontImage) {
                        devElem.css({
                            'background-image': `url('${frontImage}')`,
                            'background-size': 'cover',
                            'background-position': 'center',
                            'border': '1px solid #38bdf8'
                        });
                        devElem.html(`
                            <div class="dev-content-row" style="position:relative; z-index:5; background:rgba(0,0,0,0.65); padding:2px 6px; border-radius:3px;">
                                <div class="dev-name-badge">
                                    <span class="dev-status-led"></span>
                                    <span>${escapeHtml(dev.name)}</span>
                                </div>
                                <span class="dev-u-tag">${heightU}U</span>
                            </div>
                        `);
                    } else if (typeof createDeviceFrontFaceplate === 'function') {
                        let face = createDeviceFrontFaceplate(dev, heightPx);
                        face.append(`<span class="dev-u-tag-floating">${heightU}U</span>`);
                        devElem.append(face);
                    } else {
                        devElem.css('background-color', bgColor);
                        devElem.html(`
                            <div class="dev-content-row">
                                <div class="dev-name-badge">
                                    <span class="dev-status-led"></span>
                                    <span>${dev.name}</span>
                                </div>
                                <span class="dev-u-tag">${heightU}U</span>
                            </div>
                        `);
                    }

                    $('#rack-container-front').append(devElem);
                }

                // Render Rear
                if (showRear) {
                    let rearImage = dev.cmdb_imagen_trasera || '';
                    if (rearImage && !rearImage.startsWith('http') && !rearImage.startsWith('/')) rearImage = '../' + rearImage;

                    let devElem = $(`
                        <div class="device ${depthClass}" 
                             id="dev-elem-rear-${dev.id}"
                             draggable="true"
                             ondragstart="drag(event, ${dev.id})"
                             style="${positionStyle} height: ${heightPx}px; left: ${rearLeftInset}; right: ${rearRightInset};" 
                             onclick="openEditForm(${dev.id})"
                             title="${dev.name} [REAR] - U${startU} (${heightU}U)">
                        </div>
                    `);

                    if (rearImage) {
                        devElem.css({
                            'background-image': `url('${rearImage}')`,
                            'background-size': 'cover',
                            'background-position': 'center',
                            'border': '1px solid #10b981'
                        });
                        devElem.html(`
                            <div class="dev-content-row" style="position:relative; z-index:5; background:rgba(0,0,0,0.65); padding:2px 6px; border-radius:3px;">
                                <div class="dev-name-badge">
                                    <span class="dev-status-led" style="background:#38bdf8; box-shadow:0 0 6px #38bdf8;"></span>
                                    <span>${escapeHtml(dev.name)}</span>
                                </div>
                                <span class="dev-u-tag">${heightU}U</span>
                            </div>
                        `);
                    } else if (typeof createDeviceRearFaceplate === 'function') {
                        let face = createDeviceRearFaceplate(dev, heightPx);
                        face.append(`<span class="dev-u-tag-floating" style="background:#047857; color:#fff;">${heightU}U</span>`);
                        devElem.append(face);
                    } else {
                        devElem.css('background-color', bgColor);
                        devElem.html(`
                            <div class="dev-content-row">
                                <div class="dev-name-badge">
                                    <span class="dev-status-led" style="background:#38bdf8; box-shadow:0 0 6px #38bdf8;"></span>
                                    <span>${dev.name}</span>
                                </div>
                                <span class="dev-u-tag">${heightU}U</span>
                            </div>
                        `);
                    }

                    $('#rack-container-rear').append(devElem);
                }
            }
        });
    }

    // Renderizado del Listado de Inventario en el Panel Derecho
    function renderInventoryList() {
        let container = $('#inventory-list-container');
        container.empty();
        $('#tab-inventory-count').text(devices.length);

        if (devices.length === 0) {
            container.html(`
                <div class="text-center py-4 text-muted">
                    <i class="fas fa-inbox fa-2x mb-2 text-secondary"></i>
                    <p class="small mb-2">No hay equipos instalados en este rack.</p>
                    <button class="btn btn-outline-primary btn-xs" onclick="openCreateForm(1, 'front')">
                        <i class="fas fa-plus mr-1"></i> Agregar Primer Equipo
                    </button>
                </div>
            `);
            return;
        }

        // Ordenar según numeración del rack
        let sorted = [...devices].sort((a, b) => {
            return NUMBERING_DIR === 'UP' ? (a.start_u - b.start_u) : (b.start_u - a.start_u);
        });

        sorted.forEach(dev => {
            let startU = parseInt(dev.start_u);
            let hU = parseInt(dev.height_u) || 1;
            let endU = startU + hU - 1;
            let uLabel = (hU > 1) ? `U${startU}-${endU}` : `U${startU}`;
            let model = (dev.details && dev.details.model) ? dev.details.model : '';
            let ip = (dev.details && dev.details.ip_address) ? dev.details.ip_address : '';
            let subText = [model, ip].filter(Boolean).join(' • ') || 'Sin detalles de red';

            let itemHtml = `
                <div class="device-inventory-item ${activeDeviceId == dev.id ? 'active-item' : ''}" 
                     id="inv-item-${dev.id}" 
                     onclick="focusAndSelectDevice(${dev.id})">
                    <div class="d-flex align-items-center" style="min-width: 0; flex: 1;">
                        <span class="dev-item-u">${uLabel}</span>
                        <div class="dev-item-info">
                            <div class="dev-item-title">${dev.name}</div>
                            <div class="dev-item-sub">${subText}</div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center ml-2" style="gap: 4px;">
                        <button type="button" class="btn btn-outline-primary btn-xs" onclick="event.stopPropagation(); openEditForm(${dev.id})" title="Editar">
                            <i class="fas fa-pen"></i>
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-xs" onclick="event.stopPropagation(); deleteDeviceDirect(${dev.id})" title="Eliminar">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
            container.append(itemHtml);
        });
    }

    // Cálculo y Actualización de KPIs
    function updateRackKPIs() {
        let occupiedUnits = new Set();
        let totalWatts = 0;
        let totalAmps = 0;
        let totalWeight = 0;
        let counts = { sw: 0, fw: 0, srv: 0, pdu: 0, others: 0 };

        devices.forEach(d => {
            let start = parseInt(d.start_u);
            let h = parseInt(d.height_u) || 1;
            let mounting = (d.details && d.details.mounting) || 'horizontal';

            if (mounting === 'horizontal') {
                for (let u = start; u < start + h; u++) {
                    if (u <= TOTAL_U) occupiedUnits.add(u);
                }
            }

            let w = parseFloat((d.details && d.details.watts) || 0);
            if (!isNaN(w) && w > 0) totalWatts += w;
            let a = parseFloat((d.details && d.details.amps) || 0);
            if (!isNaN(a) && a > 0) totalAmps += a;
            let kg = parseFloat((d.details && d.details.weight) || 0);
            if (!isNaN(kg) && kg > 0) totalWeight += kg;

            let nameLower = (d.name || '').toLowerCase();
            if (mounting.startsWith('vertical') || nameLower.includes('pdu') || nameLower.includes('multitoma')) counts.pdu++;
            else if (nameLower.includes('sw') || nameLower.includes('switch') || nameLower.includes('c9200')) counts.sw++;
            else if (nameLower.includes('fw') || nameLower.includes('firewall') || nameLower.includes('forti')) counts.fw++;
            else if (nameLower.includes('srv') || nameLower.includes('server') || h >= 2) counts.srv++;
            else counts.others++;
        });

        let usedCount = occupiedUnits.size;
        let freeCount = Math.max(0, TOTAL_U - usedCount);
        let pct = TOTAL_U > 0 ? ((usedCount / TOTAL_U) * 100).toFixed(1) : 0;

        $('#kpi-occupancy-text').text(`${usedCount} / ${TOTAL_U} U (${pct}%)`);
        $('#kpi-occupancy-bar').css('width', `${pct}%`);
        $('#kpi-free-u').text(`${freeCount} U Libres`);
        $('#kpi-total-devices').text(`${devices.length} Equipos`);

        let breakdownParts = [];
        if (counts.sw > 0) breakdownParts.push(`${counts.sw} SW`);
        if (counts.fw > 0) breakdownParts.push(`${counts.fw} FW`);
        if (counts.srv > 0) breakdownParts.push(`${counts.srv} Serv`);
        if (counts.pdu > 0) breakdownParts.push(`${counts.pdu} PDU`);
        $('#kpi-devices-breakdown').text(breakdownParts.join(', ') || 'Sin equipos instalados');

        $('#kpi-total-power').text(`${totalWatts} W / ${totalAmps.toFixed(1)} A`);
        $('#kpi-total-weight').text(`Peso acumulado: ${totalWeight.toFixed(1)} Kg`);
    }

    // Enfocar y resaltar equipo en el bastidor visual
    function focusAndSelectDevice(id) {
        activeDeviceId = id;
        $('.device').removeClass('selected-device');
        $(`#dev-elem-${id}, #dev-elem-front-${id}, #dev-elem-rear-${id}`).addClass('selected-device');
        $('.device-inventory-item').removeClass('active-item');
        $(`#inv-item-${id}`).addClass('active-item');

        // Scroll suave hacia el equipo en el viewport
        let elem = $(`#dev-elem-front-${id}:visible, #dev-elem-rear-${id}:visible, #dev-elem-${id}:visible`).first();
        if (elem.length) {
            let container = $('#rack-viewport');
            let offsetTop = elem.position().top + container.scrollTop() - 60;
            container.animate({ scrollTop: offsetTop }, 300);
        }
    }

    // Filtrado en vivo de equipos por nombre o IP
    function filterRackDevices(term) {
        term = (term || '').trim().toLowerCase();
        if (!term) {
            $('.device').removeClass('highlight-matched dimmed-device');
            $('.device-inventory-item').removeClass('d-none');
            return;
        }

        devices.forEach(dev => {
            let match = (dev.name || '').toLowerCase().includes(term) ||
                        ((dev.details && dev.details.ip_address) || '').toLowerCase().includes(term) ||
                        ((dev.details && dev.details.model) || '').toLowerCase().includes(term);

            let elems = $(`#dev-elem-${dev.id}, #dev-elem-front-${dev.id}, #dev-elem-rear-${dev.id}`);
            let invItem = $(`#inv-item-${dev.id}`);

            if (match) {
                elems.addClass('highlight-matched').removeClass('dimmed-device');
                invItem.removeClass('d-none');
            } else {
                elems.removeClass('highlight-matched').addClass('dimmed-device');
                invItem.addClass('d-none');
            }
        });
    }

    // Gestión de Pestañas del Panel Derecho
    function switchRightPanelTab(tab) {
        $('.panel-tab-btn').removeClass('active');
        $(`#tab-btn-${tab}`).addClass('active');

        if (tab === 'inventory') {
            $('#panel-view-inventory').removeClass('d-none');
            $('#panel-view-editor').addClass('d-none');
        } else {
            $('#panel-view-inventory').addClass('d-none');
            $('#panel-view-editor').removeClass('d-none');
        }
    }

    // Gestión de Sub-tabs del Formulario
    function switchFormPill(pill) {
        $('.form-pill-nav .btn').removeClass('active');
        $(`#pill-btn-${pill}`).addClass('active');

        $('#form-section-general, #form-section-specs, #form-section-power').addClass('d-none');
        $(`#form-section-${pill}`).removeClass('d-none');
    }

    function togglePduOutletsSection() {
        let mounting = $('#dev_mounting').val();
        if (mounting === 'vertical_left' || mounting === 'vertical_right') {
            $('#pdu-outlets-section').removeClass('d-none');
        } else {
            $('#pdu-outlets-section').addClass('d-none');
        }
    }

    // Abrir Formulario para Crear Equipo
    function openCreateForm(u, orientation, forcedMounting) {
        resetForm();
        switchRightPanelTab('editor');
        $('#welcome-msg').addClass('d-none');
        $('#device-form').removeClass('d-none');
        $('#btn-delete-device').addClass('d-none');
        $('#form-title').html(`<i class="fas fa-plus-circle text-success mr-1"></i> Agregar en U${u}`);

        $('#dev_id').val(0);
        $('#dev_start_u').val(u);
        if (forcedMounting) {
            $('#dev_height_u').val(TOTAL_U);
            $('#dev_mounting').val(forcedMounting);
        } else {
            $('#dev_height_u').val(1);
            $('#dev_mounting').val('horizontal');
        }
        $('#dev_color').val('#1e293b');
        $('#dev_orientation').val(orientation || 'front');
        $('#dev_depth').val('full');
        togglePduOutletsSection();

        switchFormPill('general');
        $('#dev_name').focus();
    }

    // Abrir Formulario para Editar Equipo
    function openEditForm(id) {
        let dev = devices.find(d => d.id == id);
        if (!dev) return;

        resetForm();
        activeDeviceId = id;
        focusAndSelectDevice(id);

        switchRightPanelTab('editor');
        $('#welcome-msg').addClass('d-none');
        $('#device-form').removeClass('d-none');
        $('#btn-delete-device').removeClass('d-none');
        $('#form-title').html(`<i class="fas fa-edit text-primary mr-1"></i> Editar: ${dev.name}`);

        $('#dev_id').val(dev.id);
        $('#dev_start_u').val(dev.start_u);
        $('#dev_height_u').val(dev.height_u);
        $('#dev_orientation').val(dev.orientation || 'front');
        $('#dev_depth').val(dev.details.depth || 'full');
        $('#dev_mounting').val(dev.details.mounting || 'horizontal');
        $('#dev_name').val(dev.name);

        $('#dev_make').val(dev.details.make || '');
        $('#dev_model').val(dev.details.model || '');
        $('#dev_asset_tag').val(dev.details.asset_tag || '');
        $('#dev_serial_number').val(dev.details.serial_number || '');
        $('#dev_owner').val(dev.details.owner || '');
        $('#dev_server_function').val(dev.details.server_function || '');
        $('#dev_ip').val(dev.details.ip_address || '');
        $('#dev_weight').val(dev.details.weight || '');
        $('#dev_watts').val(dev.details.watts || '');
        $('#dev_amps').val(dev.details.amps || '');
        $('#dev_voltage').val(dev.details.voltage || '');
        $('#dev_color').val(dev.details.color || '#1e293b');

        $('#dev_outlets_c13').val(dev.details.outlets_c13 || '');
        $('#dev_outlets_c19').val(dev.details.outlets_c19 || '');
        $('#dev_outlets_nema').val(dev.details.outlets_nema || '');
        togglePduOutletsSection();

        if (dev.cmdb_reference) {
            $('#dev_cmdb_reference').val(dev.cmdb_reference).trigger('change.select2');
        } else {
            $('#dev_cmdb_reference').val('').trigger('change.select2');
        }

        switchFormPill('general');
    }

    function deleteDeviceDirect(id) {
        let dev = devices.find(d => d.id == id);
        let name = dev ? dev.name : 'este equipo';
        if (confirm(`¿Seguro que deseas eliminar el equipo "${name}" del rack?`)) {
            $.post('api.php', {action: 'delete_device', id: id}, function(res) {
                if (res.success) {
                    toastr.success(res.message);
                    loadDevices();
                    resetForm();
                } else {
                    toastr.error(res.message || 'Error al eliminar.');
                }
            }, 'json').fail(function() {
                toastr.error('Error de servidor al eliminar.');
            });
        }
    }

    function resetForm() {
        $('#device-form')[0].reset();
        $('#welcome-msg').removeClass('d-none');
        $('#device-form').addClass('d-none');
        $('#btn-delete-device').addClass('d-none');
        $('#form-title').html('<i class="fas fa-info-circle mr-1 text-primary"></i> Seleccione un equipo o slot');

        $('#source_manual').prop('checked', true).parent().addClass('active').siblings().removeClass('active');
        $('#zabbix-selection-area').addClass('d-none');
        $('#zabbix_host').val('').trigger('change.select2').prop('disabled', true);
        $('#btn-fetch-zabbix').prop('disabled', true);

        $('#dev_cmdb_reference').val('').trigger('change.select2');
        $('#dev_depth').val('full');
        $('#dev_mounting').val('horizontal');
        togglePduOutletsSection();
    }

    function loadCMDBInstances() {
        $.get('../api_ci.php?action=get_instances', function(res) {
            if (res.success) {
                cmdbInstances = res.data;
                let sel = $('#dev_cmdb_reference');
                sel.empty().append('<option value="">-- No vinculado (Manual) --</option>');
                res.data.forEach(ci => {
                    sel.append(`<option value="${ci.id}">${ci.hostname} (${ci.category_name})</option>`);
                });
            }
        }, 'json');
    }

    function loadZabbixHostgroups() {
        let sel = $('#zabbix_hostgroup');
        sel.html('<option value="">Cargando...</option>').prop('disabled', true);
        $.get('api.php?action=get_zabbix_hostgroups', function(res) {
            if (res.success) {
                let html = '<option value="">-- Todos los Equipos --</option>';
                res.data.forEach(hg => {
                    html += `<option value="${hg.groupid}">${hg.name}</option>`;
                });
                sel.html(html).prop('disabled', false);
                loadZabbixHosts('');
            } else {
                toastr.error('Error al cargar hostgroups: ' + res.message);
                sel.html('<option value="">Error</option>');
            }
        }, 'json').fail(function() {
            toastr.error('Error de conexión al cargar hostgroups.');
            sel.html('<option value="">Error</option>');
        });
    }

    function loadZabbixHosts(groupid) {
        let sel = $('#zabbix_host');
        sel.html('<option value="">Cargando hosts...</option>').prop('disabled', true);
        $('#btn-fetch-zabbix').prop('disabled', true);

        let url = 'api.php?action=get_zabbix_hosts';
        if (groupid) url += '&groupid=' + groupid;

        $.get(url, function(res) {
            if (res.success) {
                let html = '<option value="">-- Seleccionar Host --</option>';
                res.data.forEach(h => {
                    html += `<option value="${h.hostid}" 
                        data-name="${h.name}" 
                        data-ip="${h.ip || ''}"
                        data-make="${h.make || ''}"
                        data-model="${h.model || ''}"
                        data-serial="${h.serial || ''}"
                        data-asset-tag="${h.asset_tag || ''}"
                        data-owner="${h.owner || ''}"
                        data-notes="${h.notes || ''}">${h.name} (${h.ip || 'Sin IP'})</option>`;
                });
                sel.html(html).prop('disabled', false);
                sel.trigger('change');
            } else {
                toastr.error('Error al cargar hosts: ' + res.message);
                sel.html('<option value="">Error</option>');
            }
        }, 'json').fail(function() {
            toastr.error('Error de conexión al cargar hosts.');
            sel.html('<option value="">Error</option>');
        });
    }
</script>

<?php require_once __DIR__ . '/partials/rack_infographic_modal.php'; ?>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>

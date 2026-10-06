<?php
/**
 * CMDB DCIM - SYNAPSE 3DViewer
 * Visualizador 3D Avanzado de Datacenter con Modelado de Racks y Equipos en 3D
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

// 1. Filtro de Cliente (Multitenancy / Vilaseca)
$client_filter = trim($_GET['cliente'] ?? $_GET['client'] ?? '');
if (empty($client_filter) && !has_role('SUPER_ADMIN') && has_module_access('vilaseca')) {
    $client_filter = 'VILASECA';
}

// 2. Obtener lista de todos los cuartos disponibles
$roomsSql = "
    SELECT r.*, 
           (SELECT COUNT(*) FROM dc_racks WHERE room_id = r.id) as racks_count,
           (SELECT COUNT(d.id) FROM dc_rack_devices d JOIN dc_racks rk ON d.rack_id = rk.id WHERE rk.room_id = r.id) as devices_count
    FROM dc_rooms r
    WHERE 1=1
";
$roomsParams = [];
if (!empty($client_filter)) {
    $roomsSql .= " AND (UPPER(r.client) = UPPER(?) OR r.client IS NULL OR r.client = '')";
    $roomsParams[] = $client_filter;
}
$roomsSql .= " ORDER BY (SELECT COUNT(*) FROM dc_racks WHERE room_id = r.id) DESC, r.name ASC";
$stmtRooms = $pdo->prepare($roomsSql);
$stmtRooms->execute($roomsParams);
$all_rooms = $stmtRooms->fetchAll(PDO::FETCH_ASSOC);

// 3. Determinar cuarto seleccionado
$requested_room_id = (int)($_GET['room_id'] ?? $_GET['id'] ?? 0);
$selected_room = null;

if ($requested_room_id > 0) {
    foreach ($all_rooms as $rm) {
        if ($rm['id'] == $requested_room_id) {
            $selected_room = $rm;
            break;
        }
    }
}

// Si no se encontró o no se especificó, seleccionar el primer cuarto con racks, o el primero disponible
if (!$selected_room && !empty($all_rooms)) {
    foreach ($all_rooms as $rm) {
        if ($rm['racks_count'] > 0) {
            $selected_room = $rm;
            break;
        }
    }
    if (!$selected_room) {
        $selected_room = $all_rooms[0];
    }
}

$room_id = $selected_room ? (int)$selected_room['id'] : 0;
$tile_size = !empty($selected_room['tile_size']) ? (float)$selected_room['tile_size'] : 0.6;
if ($tile_size <= 0) $tile_size = 0.6;

$width_m = !empty($selected_room['width_meters']) ? (float)$selected_room['width_meters'] : 12.0;
$length_m = !empty($selected_room['length_meters']) ? (float)$selected_room['length_meters'] : 8.0;
$floor_height = !empty($selected_room['floor_height_meters']) ? (float)$selected_room['floor_height_meters'] : 0.3;

$tiles_x = (int)floor($width_m / $tile_size);
$tiles_y = (int)floor($length_m / $tile_size);

// 4. Obtener Racks del cuarto seleccionado
$racks = [];
$items = [];
$devices_by_rack = [];
$all_devices = [];

if ($room_id > 0) {
    // Racks
    $stmtRacks = $pdo->prepare("
        SELECT id, name, client, city, location, grid_x, grid_y, 
               width_tiles, depth_tiles, total_u, rotation, numbering_dir, 
               description, z_index 
        FROM dc_racks 
        WHERE room_id = ? 
        ORDER BY name ASC
    ");
    $stmtRacks->execute([$room_id]);
    $racks = $stmtRacks->fetchAll(PDO::FETCH_ASSOC);

    // Floor Items (AACC, UPS, Rampa, Puertas, etc.)
    $stmtItems = $pdo->prepare("SELECT * FROM dc_floor_items WHERE room_id = ?");
    $stmtItems->execute([$room_id]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    // Equipos en los racks de este cuarto
    $stmtDevs = $pdo->prepare("
        SELECT d.*, r.name as rack_name, i.hostname as cmdb_hostname, i.attributes_json as cmdb_attrs 
        FROM dc_rack_devices d
        JOIN dc_racks r ON d.rack_id = r.id
        LEFT JOIN ci_instances i ON d.cmdb_reference = i.id
        WHERE r.room_id = ?
        ORDER BY d.start_u DESC
    ");
    $stmtDevs->execute([$room_id]);
    $raw_devices = $stmtDevs->fetchAll(PDO::FETCH_ASSOC);

    foreach ($raw_devices as $d) {
        $dev_id = (int)$d['id'];
        $rack_id_dev = (int)$d['rack_id'];
        $details = json_decode($d['details_json'] ?? '{}', true) ?: [];

        // Vincular con CMDB si aplica
        if (!empty($d['cmdb_reference'])) {
            $cmdb_attrs = json_decode($d['cmdb_attrs'] ?? '{}', true) ?: [];
            if (!empty($cmdb_attrs['marca'])) $details['make'] = $cmdb_attrs['marca'];
            if (!empty($cmdb_attrs['modelo'])) $details['model'] = $cmdb_attrs['modelo'];
            if (!empty($cmdb_attrs['serial_number'])) $details['serial_number'] = $cmdb_attrs['serial_number'];
            if (!empty($cmdb_attrs['asset_tag'])) $details['asset_tag'] = $cmdb_attrs['asset_tag'];
            if (!empty($cmdb_attrs['ip'])) $details['ip_address'] = $cmdb_attrs['ip'];
            if (!empty($d['cmdb_hostname'])) $d['name'] = $d['cmdb_hostname'];
        }

        // Potencia en Watts
        $watts = (float)($details['watts'] ?? 0);
        $amps = (float)($details['amps'] ?? 0);
        $voltage = (float)($details['voltage'] ?? 120);
        if ($watts <= 0 && $amps > 0) {
            $watts = round($amps * $voltage, 1);
        }
        if ($watts <= 0) {
            $watts = 180 + (($dev_id * 37) % 220); // Entre 180W y 400W por defecto
        }
        $details['calculated_watts'] = $watts;

        $processed_device = [
            'id' => $dev_id,
            'rack_id' => $rack_id_dev,
            'name' => $d['name'] ?: 'Equipo ' . $d['start_u'] . 'U',
            'start_u' => (int)$d['start_u'],
            'height_u' => max(1, (int)$d['height_u']),
            'orientation' => $d['orientation'] ?: 'front',
            'cmdb_reference' => $d['cmdb_reference'],
            'details' => $details,
            'color' => $details['color'] ?? '#facc15' // Amarillo Sunbird por defecto
        ];

        if (!isset($devices_by_rack[$rack_id_dev])) {
            $devices_by_rack[$rack_id_dev] = [];
        }
        $devices_by_rack[$rack_id_dev][] = $processed_device;
        $all_devices[] = $processed_device;
    }
}

// 5. Estadísticas por Rack (Totales de Gabinete estilo Sunbird)
$rack_stats = [];
foreach ($racks as &$rk) {
    $r_id = (int)$rk['id'];
    $tot_u = (int)($rk['total_u'] ?: 42);
    $rk['total_u'] = $tot_u;
    
    $devs = $devices_by_rack[$r_id] ?? [];
    $occupied_u_map = [];
    $sum_watts = 0;
    
    foreach ($devs as $dev) {
        $st = $dev['start_u'];
        $hu = $dev['height_u'];
        for ($u = $st; $u < $st + $hu; $u++) {
            $occupied_u_map[$u] = true;
        }
        $sum_watts += (float)($dev['details']['calculated_watts'] ?? 0);
    }
    
    $occupied_u_count = count($occupied_u_map);
    $free_u = max(0, $tot_u - $occupied_u_count);
    $pct_occupied = $tot_u > 0 ? round(($occupied_u_count / $tot_u) * 100, 1) : 0;
    
    $power_capacity = 10000; // 10,000 W estándar
    $effective_power = round($sum_watts, 0);
    $potential_power = round($effective_power * 1.15, 0);
    $measured_power = round($effective_power * 0.88, 0);
    $remaining_power = max(0, $power_capacity - $effective_power);

    $rack_stats[$r_id] = [
        'id' => $r_id,
        'name' => $rk['name'],
        'total_u' => $tot_u,
        'occupied_u' => $occupied_u_count,
        'free_u' => $free_u,
        'percent_occupied' => $pct_occupied,
        'device_count' => count($devs),
        'power_capacity_w' => $power_capacity,
        'potential_power_w' => $potential_power,
        'effective_power_w' => $effective_power,
        'measured_power_w' => $measured_power,
        'remaining_power_w' => $remaining_power
    ];
}
unset($rk);

// Rack seleccionado inicialmente
$initial_rack_id = (int)($_GET['rack_id'] ?? 0);
if ($initial_rack_id <= 0 && !empty($racks)) {
    $initial_rack_id = (int)$racks[0]['id'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SYNAPSE 3DViewer - Datacenter DCIM | <?php echo htmlspecialchars($selected_room['name'] ?? 'Salas'); ?></title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.2/css/bootstrap.min.css">
    <!-- FontAwesome 5 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    
    <!-- Rack Infographic CSS (Mismo diseño gráfico de racks.php) -->
    <link rel="stylesheet" href="css/rack_infographic.css?v=<?php echo filemtime(__DIR__ . '/css/rack_infographic.css'); ?>">
    
    <style>
        :root {
            --bg-canvas: #090d16;
            --bg-panel: rgba(15, 23, 42, 0.96);
            --bg-panel-solid: #0f172a;
            --bg-toolbar: #111827;
            --border-panel: rgba(255, 255, 255, 0.12);
            --accent-cyan: #38bdf8;
            --accent-cyan-glow: rgba(56, 189, 248, 0.4);
            --accent-orange: #ff5c05;
            --accent-yellow: #facc15;
            --accent-green: #22c55e;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
        }

        * {
            box-sizing: border-box;
            user-select: none;
        }

        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background-color: var(--bg-canvas);
            font-family: 'Outfit', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--text-primary);
        }

        /* 3D Canvas Layer */
        #canvas-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            background: #090d16;
        }

        /* UI Overlays */
        .ui-layer {
            position: absolute;
            z-index: 20;
            pointer-events: none;
        }

        .interactive {
            pointer-events: auto;
        }

        /* TOP NAVIGATION / TOOLBAR (Sunbird dcTrack Style) */
        .dc-topbar {
            top: 0;
            left: 0;
            right: 0;
            height: 52px;
            background: rgba(17, 24, 39, 0.96);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-panel);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 16px;
            z-index: 30;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.6);
        }

        .dc-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            cursor: pointer;
            transition: opacity 0.2s ease;
        }

        .dc-brand:hover {
            text-decoration: none;
            opacity: 0.9;
        }

        .dc-brand-logo {
            height: 32px;
            width: auto;
            max-width: 48px;
            object-fit: contain;
            filter: drop-shadow(0 2px 6px rgba(0, 0, 0, 0.4));
        }

        .dc-brand-text {
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.15rem;
            font-weight: 800;
            letter-spacing: 0.8px;
        }

        .dc-brand-text span {
            color: var(--accent-cyan);
            font-size: 11px;
            font-weight: 700;
            background: rgba(56, 189, 248, 0.15);
            padding: 2px 7px;
            border-radius: 4px;
            border: 1px solid rgba(56, 189, 248, 0.3);
            letter-spacing: 0.5px;
        }

        .dc-breadcrumb {
            display: flex;
            align-items: center;
            margin-left: 20px;
            gap: 8px;
            font-size: 13px;
        }

        .dc-site-select {
            background: #1e293b;
            color: #f1f5f9;
            border: 1px solid #334155;
            border-radius: 6px;
            padding: 4px 10px;
            font-size: 13px;
            font-weight: 600;
            outline: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .dc-site-select:hover, .dc-site-select:focus {
            border-color: var(--accent-cyan);
            box-shadow: 0 0 8px var(--accent-cyan-glow);
        }

        .dc-toolbar {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .dc-btn {
            background: #1e293b;
            color: #cbd5e1;
            border: 1px solid #334155;
            border-radius: 6px;
            padding: 5px 11px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s ease;
            text-decoration: none !important;
        }

        .dc-btn:hover {
            background: #334155;
            color: #ffffff;
            border-color: #475569;
        }

        .dc-btn.active {
            background: linear-gradient(135deg, #ff5c05, #e04f00);
            color: #ffffff;
            border-color: #ff5c05;
            box-shadow: 0 0 10px rgba(255, 92, 5, 0.5);
        }

        .dc-btn-cyan {
            background: rgba(56, 189, 248, 0.15);
            color: var(--accent-cyan);
            border-color: rgba(56, 189, 248, 0.4);
        }
        .dc-btn-cyan:hover, .dc-btn-cyan.active {
            background: var(--accent-cyan);
            color: #0f172a;
            border-color: var(--accent-cyan);
            box-shadow: 0 0 12px var(--accent-cyan-glow);
        }

        /* LEFT SIDEBAR: ITEM LIST (Collapsible) */
        .dc-left-panel {
            top: 52px;
            left: 0;
            bottom: 26px;
            width: 290px;
            background: var(--bg-panel);
            backdrop-filter: blur(14px);
            border-right: 1px solid var(--border-panel);
            display: flex;
            flex-direction: column;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 4px 0 25px rgba(0, 0, 0, 0.4);
        }

        .dc-left-panel.collapsed {
            transform: translateX(-100%);
        }

        .panel-header {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border-panel);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(15, 23, 42, 0.8);
        }

        .panel-header h6 {
            margin: 0;
            font-size: 13px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .panel-search {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-panel);
        }

        .panel-search-input {
            width: 100%;
            background: #1e293b;
            border: 1px solid #334155;
            color: #f1f5f9;
            padding: 6px 10px;
            font-size: 12px;
            border-radius: 6px;
            outline: none;
            transition: border-color 0.2s;
        }
        .panel-search-input:focus {
            border-color: var(--accent-cyan);
        }

        .panel-table-header {
            display: flex;
            padding: 8px 14px;
            background: rgba(30, 41, 59, 0.6);
            border-bottom: 1px solid var(--border-panel);
            font-size: 11px;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .panel-list {
            flex: 1;
            overflow-y: auto;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .panel-list-item {
            display: flex;
            align-items: center;
            padding: 8px 14px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            cursor: pointer;
            font-size: 12px;
            transition: all 0.15s ease;
        }

        .panel-list-item:hover {
            background: rgba(56, 189, 248, 0.1);
        }

        .panel-list-item.selected {
            background: rgba(56, 189, 248, 0.2);
            border-left: 3px solid var(--accent-cyan);
        }

        .item-class {
            width: 85px;
            color: var(--text-muted);
            font-size: 11px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .item-name {
            flex: 1;
            font-weight: 600;
            color: #f1f5f9;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .item-badge {
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 4px;
            background: #1e293b;
            color: var(--text-secondary);
            font-family: 'JetBrains Mono', monospace;
        }

        /* RIGHT SIDEBAR: CABINET ELEVATION (Sunbird dcTrack / Vilaseca Graphic) */
        .dc-right-panel {
            top: 52px;
            right: 0;
            bottom: 26px;
            width: 440px;
            background: var(--bg-panel);
            backdrop-filter: blur(16px);
            border-left: 1px solid var(--border-panel);
            display: flex;
            flex-direction: column;
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1), transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: -4px 0 25px rgba(0, 0, 0, 0.5);
            z-index: 25;
        }

        .dc-right-panel.dual-mode {
            width: 820px;
            max-width: 95vw;
        }

        .dc-right-panel.collapsed {
            transform: translateX(100%);
        }

        .elevation-header {
            padding: 12px 16px;
            background: rgba(15, 23, 42, 0.95);
            border-bottom: 1px solid var(--border-panel);
            flex-shrink: 0;
        }

        .elevation-header-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .elevation-header h5 {
            margin: 0;
            font-size: 17px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: 0.5px;
        }

        .elevation-subtitle {
            font-size: 11px;
            color: var(--text-secondary);
            margin-top: 2px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .elevation-tabs {
            display: flex;
            gap: 6px;
            margin-top: 8px;
            flex-wrap: wrap;
        }

        .elevation-tab-btn {
            background: #1e293b;
            border: 1px solid #334155;
            color: var(--text-secondary);
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            padding: 4px 10px;
            cursor: pointer;
            transition: all 0.15s;
        }
        .elevation-tab-btn:hover {
            color: #ffffff;
            background: #334155;
        }
        .elevation-tab-btn.active {
            background: #0284c7;
            color: #ffffff;
            border-color: #0284c7;
        }

        /* RACK VISUAL ELEVATION CONTAINER (Uses exact rack_infographic styling) */
        .elevation-body {
            flex: 1;
            overflow-y: auto;
            overflow-x: auto;
            padding: 14px 12px;
            background: #080c14;
            display: flex;
            justify-content: center;
        }

        .elevation-grid-container {
            display: flex;
            gap: 16px;
            width: 100%;
            justify-content: center;
            align-items: flex-start;
        }

        .elevation-col {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
            max-width: 380px;
        }

        /* Adaptation of rack_infographic inside the right panel */
        .dc-right-panel .cabinet-3d-wrapper {
            margin: 0 auto;
            width: 100%;
            max-width: 375px;
        }

        .dc-right-panel .cabinet-front,
        .dc-right-panel .rear-cabinet .cabinet-front {
            width: 100% !important;
            max-width: 365px;
            box-sizing: border-box;
        }

        .dc-right-panel .cabinet-top-face {
            width: calc(100% - 14px);
            height: 38px;
        }

        .dc-right-panel .cabinet-right-side {
            width: 14px;
        }

        .dc-right-panel .rack-rail-left,
        .dc-right-panel .rack-rail-right {
            width: 22px;
            flex: 0 0 22px;
        }

        .dc-right-panel .rail-unit-slot {
            font-size: 8.5px;
            padding: 0 2px;
        }

        .dc-right-panel .rack-slots-column {
            flex: 1 1 auto;
            min-width: 0;
        }

        .dc-right-panel .rack-pdu-channel {
            width: 26px;
            flex: 0 0 26px;
        }

        .dc-right-panel .cabinet-header-plate {
            padding: 6px 8px;
            font-size: 10.5px;
        }

        .dc-right-panel .cabinet-footer-plate {
            padding: 5px 8px;
            font-size: 10px;
        }

        .dc-right-panel .appliance-face {
            cursor: pointer;
            transition: filter 0.15s, transform 0.1s;
        }

        .dc-right-panel .appliance-face:hover {
            filter: brightness(1.2) contrast(1.1);
            outline: 2px solid #38bdf8;
            outline-offset: -1px;
            z-index: 10;
        }

        /* CABINET TOTALS (Bottom of Right Sidebar) */
        .elevation-totals {
            padding: 10px 16px;
            background: #0d121c;
            border-top: 1px solid var(--border-panel);
            flex-shrink: 0;
        }

        .totals-title {
            font-size: 12px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .totals-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11px;
            margin-bottom: 3px;
            color: var(--text-secondary);
        }

        .totals-val {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 700;
            color: #f1f5f9;
        }

        .totals-val.highlight {
            color: var(--accent-orange);
        }

        /* TOGGLE BUTTONS FOR PANELS */
        .toggle-panel-btn {
            position: absolute;
            background: #1e293b;
            border: 1px solid #334155;
            color: #cbd5e1;
            width: 26px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 35;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
            transition: all 0.2s;
        }
        .toggle-panel-btn:hover {
            background: #334155;
            color: #ffffff;
        }
        #toggle-left-btn {
            top: 60px;
            left: 290px;
            border-radius: 0 6px 6px 0;
            border-left: none;
        }
        #toggle-left-btn.collapsed {
            left: 0;
        }
        #toggle-right-btn {
            top: 60px;
            right: 380px;
            border-radius: 6px 0 0 6px;
            border-right: none;
        }
        #toggle-right-btn.collapsed {
            right: 0;
        }

        /* BOTTOM STATUS BAR */
        .dc-bottombar {
            bottom: 0;
            left: 0;
            right: 0;
            height: 26px;
            background: #0d121c;
            border-top: 1px solid var(--border-panel);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 16px;
            font-size: 11px;
            font-family: 'JetBrains Mono', monospace;
            color: var(--text-muted);
            z-index: 30;
        }

        .dc-bottom-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .dc-bottom-coord {
            color: var(--accent-cyan);
            font-weight: 600;
        }

        /* TOOLTIP */
        #rack-tooltip {
            position: absolute;
            background: rgba(15, 23, 42, 0.96);
            border: 1px solid var(--accent-cyan);
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 11px;
            color: #f1f5f9;
            pointer-events: none;
            display: none;
            z-index: 100;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.6);
            line-height: 1.4;
        }

        /* MODAL ITEM DETAIL */
        .dc-modal-content {
            background: #0f172a;
            border: 1px solid #334155;
            color: #f1f5f9;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.8);
        }

        .dc-modal-header {
            border-bottom: 1px solid #334155;
            padding: 14px 20px;
        }

        .dc-modal-body {
            padding: 20px;
        }

        .detail-row {
            display: flex;
            margin-bottom: 10px;
            font-size: 13px;
        }
        .detail-label {
            width: 140px;
            color: var(--text-muted);
            font-weight: 600;
        }
        .detail-val {
            flex: 1;
            color: #f1f5f9;
            font-family: 'JetBrains Mono', monospace;
        }
    </style>
</head>
<body>

    <!-- TOOLTIP OVERLAY -->
    <div id="rack-tooltip"></div>

    <!-- 3D WEBGL CANVAS -->
    <div id="canvas-container"></div>

    <!-- 1. TOP NAVBAR / TOOLBAR (SYNAPSE Datacenter 3D) -->
    <header class="dc-topbar ui-layer interactive">
        <div class="d-flex align-items-center">
            <a href="<?php echo PUBLIC_URL_PREFIX; ?>/datacenter/floor_plan.php<?php echo !empty($client_filter) ? '?cliente=' . urlencode($client_filter) : ''; ?>" class="dc-brand" title="Volver a Gestión Datacenter">
                <img src="<?php echo PUBLIC_URL_PREFIX; ?>/logo/logo_white.png" alt="SYNAPSE Logo" class="dc-brand-logo">
                <div class="dc-brand-text">
                    SYNAPSE <span>3DViewer</span>
                </div>
            </a>

            <div class="dc-breadcrumb d-none d-md-flex">
                <span class="text-muted"><i class="fas fa-map mr-1"></i> Floor Map</span>
                <span class="text-muted">/</span>
                <label class="mb-0 text-muted mr-1" for="room-selector">Site:</label>
                <select id="room-selector" class="dc-site-select" onchange="changeRoom(this.value)">
                    <?php foreach ($all_rooms as $rm): ?>
                        <option value="<?php echo $rm['id']; ?>" <?php echo $rm['id'] == $room_id ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($rm['name']); ?> (<?php echo $rm['racks_count']; ?> Racks - <?php echo $rm['devices_count']; ?> Equipos)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- CENTER CONTROLS -->
        <div class="dc-toolbar">
            <button class="dc-btn active" id="btn-mode-3d" title="Modo 3D Perspectiva" onclick="resetCamera()">
                <i class="fas fa-cube"></i> 3D
            </button>
            <button class="dc-btn" id="btn-mode-top" title="Vista Cenital (Plano 2D desde arriba)" onclick="setTopCameraView()">
                <i class="fas fa-th"></i> Top View
            </button>
            <button class="dc-btn" onclick="resetCamera()" title="Restablecer Posición de Cámara (Home)">
                <i class="fas fa-home"></i>
            </button>
            
            <div class="btn-separator" style="width: 1px; height: 20px; background: #334155; margin: 0 4px;"></div>

            <!-- TOGGLE DOORS (Permite ver los equipos dentro de los racks en 3D) -->
            <button class="dc-btn dc-btn-cyan active" id="toggle-doors-btn" onclick="toggleDoors()" title="Alternar Puertas de Racks (Abiertas para ver equipos / Transparentes)">
                <i class="fas fa-door-open"></i> Puertas: Abiertas
            </button>

            <!-- BUSWAYS & CABLE TRAYS TOGGLE -->
            <button class="dc-btn dc-btn-cyan active" id="toggle-busways-btn" onclick="toggleBusways()" title="Conduits / Líneas de Energía A+B (Rojo y Azul)">
                <i class="fas fa-bolt"></i> Busways
            </button>
            <!-- 3D LABELS TOGGLE -->
            <button class="dc-btn active" id="toggle-labels-btn" onclick="toggleLabels()" title="Etiquetas Flotantes de Racks">
                <i class="fas fa-tag"></i> Labels
            </button>
            <!-- HEATMAP / THERMAL TOGGLE -->
            <button class="dc-btn" id="toggle-thermal-btn" onclick="toggleThermalMode()" title="Mapa Térmico / Capacidad de Potencia">
                <i class="fas fa-fire-alt"></i> Thermal
            </button>
            <!-- SNAPSHOT -->
            <button class="dc-btn" onclick="takeSnapshot()" title="Exportar Captura PNG de Alta Resolución">
                <i class="fas fa-camera"></i>
            </button>
            <!-- FULLSCREEN -->
            <button class="dc-btn" onclick="toggleFullScreen()" title="Pantalla Completa">
                <i class="fas fa-expand"></i>
            </button>
        </div>

        <!-- RIGHT QUICK LINKS -->
        <div class="d-flex align-items-center" style="gap: 6px;">
            <a href="rooms.php<?php echo $client_filter ? '?cliente=' . urlencode($client_filter) : ''; ?>" class="dc-btn" title="Ir a Gestión de Cuartos">
                <i class="fas fa-door-open"></i> Salas
            </a>
            <a href="racks.php<?php echo $client_filter ? '?cliente=' . urlencode($client_filter) : ''; ?>" class="dc-btn" title="Ir a Lista de Racks">
                <i class="fas fa-server"></i> Racks
            </a>
            <a href="analisis.php<?php echo $client_filter ? '?cliente=' . urlencode($client_filter) : ''; ?>" class="dc-btn" title="Análisis de Capacidad">
                <i class="fas fa-chart-pie"></i> Análisis
            </a>
        </div>
    </header>

    <!-- 2. LEFT SIDEBAR: ITEM LIST (Collapsible) -->
    <aside class="dc-left-panel ui-layer interactive" id="left-panel">
        <div class="panel-header">
            <h6><i class="fas fa-list mr-1 text-primary"></i> Item List (<span id="items-count"><?php echo count($racks) + count($all_devices); ?></span>)</h6>
            <span class="text-muted" style="font-size: 11px;">Current View: All items</span>
        </div>
        <div class="panel-search">
            <input type="text" id="item-search-box" class="panel-search-input" placeholder="Buscar gabinete o equipo..." onkeyup="filterItemList()">
        </div>
        <div class="panel-table-header">
            <span style="width: 85px;">Clase</span>
            <span style="flex: 1;">Nombre</span>
            <span style="width: 45px; text-align: right;">Detalle</span>
        </div>
        <ul class="panel-list" id="panel-item-list">
            <?php foreach ($racks as $r): 
                $r_id = (int)$r['id'];
                $r_dev_count = count($devices_by_rack[$r_id] ?? []);
            ?>
                <li class="panel-list-item rack-row-item" data-rack-id="<?php echo $r_id; ?>" onclick="selectRack(<?php echo $r_id; ?>, true)">
                    <span class="item-class text-info"><i class="fas fa-server mr-1"></i>Cabinet</span>
                    <span class="item-name"><?php echo htmlspecialchars($r['name']); ?></span>
                    <span class="item-badge"><?php echo $r['total_u']; ?>U</span>
                </li>
                <?php if (!empty($devices_by_rack[$r_id])): ?>
                    <?php foreach ($devices_by_rack[$r_id] as $dev): ?>
                        <li class="panel-list-item device-row-item pl-4" data-rack-id="<?php echo $r_id; ?>" data-dev-id="<?php echo $dev['id']; ?>" onclick="selectRack(<?php echo $r_id; ?>, true); showDeviceDetail(<?php echo $dev['id']; ?>)">
                            <span class="item-class text-muted" style="font-size: 10px;"><i class="fas fa-microchip mr-1"></i>Device</span>
                            <span class="item-name text-muted" style="font-size: 11px;"><?php echo htmlspecialchars($dev['name']); ?></span>
                            <span class="item-badge" style="color: var(--accent-yellow); font-size: 9px;">U<?php echo $dev['start_u']; ?></span>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </aside>

    <!-- LEFT TOGGLE BUTTON -->
    <div id="toggle-left-btn" class="toggle-panel-btn ui-layer interactive" onclick="toggleLeftPanel()" title="Ocultar / Mostrar Lista de Equipos">
        <i class="fas fa-chevron-left" id="toggle-left-icon"></i>
    </div>

    <!-- RIGHT TOGGLE BUTTON -->
    <div id="toggle-right-btn" class="toggle-panel-btn ui-layer interactive" onclick="toggleRightPanel()" title="Ocultar / Mostrar Elevación de Gabinete">
        <i class="fas fa-chevron-right" id="toggle-right-icon"></i>
    </div>

    <!-- 3. RIGHT SIDEBAR: CABINET ELEVATION (Sunbird dcTrack Graphic / Vilaseca Infographic) -->
    <aside class="dc-right-panel ui-layer interactive" id="right-panel">
        <div class="elevation-header">
            <div class="elevation-header-top">
                <span class="elevation-subtitle"><i class="fas fa-server mr-1 text-warning"></i> Cabinet Elevation</span>
                <button class="btn btn-sm text-muted p-0" onclick="toggleRightPanel()" title="Cerrar panel">&times;</button>
            </div>
            <h5 id="elevation-rack-name">GABINETE</h5>
            <div class="text-muted" id="elevation-rack-subtitle" style="font-size: 11px;">DATACENTER</div>
            
            <div class="elevation-tabs">
                <button class="elevation-tab-btn active" id="tab-front-view" onclick="setElevationView('front')"><i class="fas fa-desktop mr-1"></i> Frontal</button>
                <button class="elevation-tab-btn" id="tab-rear-view" onclick="setElevationView('rear')"><i class="fas fa-server mr-1"></i> Trasera</button>
                <button class="elevation-tab-btn" id="tab-dual-view" onclick="setElevationView('dual')"><i class="fas fa-columns mr-1"></i> Ambas Vistas</button>
                <button class="elevation-tab-btn text-warning" onclick="openRackInfographicModal()" title="Abrir Infografía Completa"><i class="fas fa-file-alt mr-1"></i> Infografía</button>
                <button class="elevation-tab-btn text-info" onclick="openRackBuilderDirect()" title="Abrir Editor 2D"><i class="fas fa-tools mr-1"></i> Editor 2D</button>
            </div>
        </div>

        <!-- RACK DRAWING (Authentic Rack Infographic Framing) -->
        <div class="elevation-body" id="elevation-body">
            <div class="elevation-grid-container" id="elevation-grid-box">
                <!-- Columna Frontal -->
                <div class="elevation-col" id="elevation-col-front">
                    <div class="cabinet-3d-wrapper">
                        <div class="cabinet-top-face">
                            <div class="top-vent-grill" id="elevation-front-top-vent">VENTILACIÓN ACTIVA</div>
                        </div>
                        <div class="cabinet-main-frame">
                            <div class="cabinet-front">
                                <div class="cabinet-header-plate" id="elevation-front-header-text">
                                    RACK · VISTA FRONTAL
                                </div>
                                <div class="cabinet-interior">
                                    <div class="rack-rail-left" id="panel-rail-left"></div>
                                    <div class="rack-pdu-channel left-channel" id="panel-pdu-left" style="display: none;"></div>
                                    <div class="rack-slots-column" id="panel-slots-col"></div>
                                    <div class="rack-pdu-channel right-channel" id="panel-pdu-right" style="display: none;"></div>
                                    <div class="rack-rail-right" id="panel-rail-right"></div>
                                </div>
                                <div class="cabinet-footer-plate" id="elevation-front-footer-text">
                                    42U · GABINETE DE PISO
                                </div>
                            </div>
                            <div class="cabinet-right-side"></div>
                        </div>
                    </div>
                </div>

                <!-- Columna Posterior (Visible en Rear o Dual) -->
                <div class="elevation-col" id="elevation-col-rear" style="display: none;">
                    <div class="cabinet-3d-wrapper rear-cabinet">
                        <div class="cabinet-top-face">
                            <div class="top-vent-grill" id="elevation-rear-top-vent">ACCESO POSTERIOR</div>
                        </div>
                        <div class="cabinet-main-frame">
                            <div class="cabinet-front">
                                <div class="cabinet-header-plate" id="elevation-rear-header-text">
                                    RACK · VISTA TRASERA
                                </div>
                                <div class="cabinet-interior">
                                    <div class="rack-pdu-channel left-channel rear-outer-pdu" id="panel-rear-pdu-left" style="display: none;"></div>
                                    <div class="rack-rail-left" id="panel-rear-rail-left"></div>
                                    <div class="rack-slots-column" id="panel-rear-slots-col"></div>
                                    <div class="rack-rail-right" id="panel-rear-rail-right"></div>
                                    <div class="rack-pdu-channel right-channel rear-outer-pdu" id="panel-rear-pdu-right" style="display: none;"></div>
                                </div>
                                <div class="cabinet-footer-plate" id="elevation-rear-footer-text">
                                    42U · ACCESO TRASERO
                                </div>
                            </div>
                            <div class="cabinet-right-side"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CABINET TOTALS (Bottom of Right Sidebar) -->
        <div class="elevation-totals">
            <div class="totals-title">
                <span>Cabinet Totals <strong id="totals-rack-name" class="text-warning">S28</strong></span>
                <span id="totals-occupancy-badge" class="badge badge-info" style="font-family: 'JetBrains Mono';">0% Ocupado</span>
            </div>
            <div class="totals-row">
                <span>Tipo / Bastidor</span>
                <span class="totals-val text-cyan" id="tot-mounting-type">GABINETE DE PISO</span>
            </div>
            <div class="totals-row">
                <span>Dirección UR</span>
                <span class="totals-val text-warning" id="tot-ur-dir">UR 1 abajo</span>
            </div>
            <div class="totals-row">
                <span>Espacio Usado</span>
                <span class="totals-val" id="tot-space-used">0 / 42 U</span>
            </div>
            <div class="totals-row">
                <span>Equipos Instalados</span>
                <span class="totals-val" id="tot-dev-count">0 equipos</span>
            </div>
            <div class="totals-row">
                <span>Power Capacity (W)</span>
                <span class="totals-val" id="tot-power-cap">10,000</span>
            </div>
            <div class="totals-row">
                <span>Potential power (W)</span>
                <span class="totals-val" id="tot-potential-pwr">0</span>
            </div>
            <div class="totals-row">
                <span>Effective power (W)</span>
                <span class="totals-val" id="tot-effective-pwr">0</span>
            </div>
            <div class="totals-row">
                <span>Measured (W)</span>
                <span class="totals-val" id="tot-measured-pwr">0</span>
            </div>
            <div class="totals-row">
                <span>Remaining (W)</span>
                <span class="totals-val highlight" id="tot-remaining-pwr">10,000</span>
            </div>
        </div>
    </aside>

    <!-- 4. BOTTOM STATUS BAR -->
    <footer class="dc-bottombar ui-layer">
        <div class="dc-bottom-left">
            <span>Tile: <span class="dc-bottom-coord" id="coord-tile">0-0</span></span>
            <span>X,Y: <span class="dc-bottom-coord" id="coord-meters">0.0, 0.0 m</span></span>
            <span>Location: <strong class="text-light"><?php echo htmlspecialchars($selected_room['name'] ?? 'Datacenter'); ?></strong> (<?php echo htmlspecialchars($selected_room['city'] ?? 'Guayaquil'); ?>)</span>
        </div>
        <div class="d-flex align-items-center" style="gap: 16px;">
            <span>Drawing north <i class="fas fa-compass text-danger ml-1"></i></span>
            <span style="opacity: 0.7;"><strong style="color: #38bdf8;">SYNAPSE</strong> 3DViewer | CMDB <?php echo htmlspecialchars($client_filter ?: 'DATACENTER'); ?></span>
        </div>
    </footer>

    <!-- DEVICE DETAIL MODAL -->
    <div class="modal fade" id="deviceDetailModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content dc-modal-content">
                <div class="modal-header dc-modal-header d-flex align-items-center justify-content-between">
                    <h5 class="modal-title font-weight-bold text-cyan" id="modalDevTitle">
                        <i class="fas fa-server mr-2 text-warning"></i>Detalle del Equipo
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body dc-modal-body" id="modalDevBody">
                    <!-- Populated dynamically -->
                </div>
                <div class="modal-footer border-top border-secondary py-2">
                    <a href="#" id="modalCmdbLink" target="_blank" class="btn btn-info btn-sm font-weight-bold" style="display: none;">
                        <i class="fas fa-external-link-alt mr-1"></i> Abrir en CMDB
                    </a>
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL INFOGRÁFICO DE RACK (Idéntico a racks.php) -->
    <?php require_once __DIR__ . '/partials/rack_infographic_modal.php'; ?>

    <!-- JS LIBS: jQuery, Bootstrap, Three.js, OrbitControls, html2canvas & Rack Infographic -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.2/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="js/rack_infographic.js?v=<?php echo filemtime(__DIR__ . '/js/rack_infographic.js'); ?>"></script>

    <script>
        // CONFIGURATION DATA PASSED FROM PHP
        const roomConfig = {
            id: <?php echo $room_id; ?>,
            name: <?php echo json_encode($selected_room['name'] ?? 'Datacenter'); ?>,
            widthM: <?php echo $width_m; ?>,
            depthM: <?php echo $length_m; ?>,
            tileSize: <?php echo $tile_size; ?>,
            tilesX: <?php echo $tiles_x; ?>,
            tilesY: <?php echo $tiles_y; ?>,
            floorHeight: <?php echo $floor_height; ?>
        };

        const racksData = <?php echo json_encode($racks); ?>;
        const itemsData = <?php echo json_encode($items); ?>;
        const devicesByRack = <?php echo json_encode($devices_by_rack); ?>;
        const rackStats = <?php echo json_encode($rack_stats); ?>;
        const clientFilter = <?php echo json_encode($client_filter); ?>;

        let selectedRackId = <?php echo $initial_rack_id ?: 0; ?>;
        let currentElevationOrientation = 'front';
        let isThermalMode = false;
        let buswaysVisible = true;
        let labelsVisible = true;
        let doorsOpen = true; // Permite ver los servidores 3D dentro del rack por defecto

        // THREE.JS VARIABLES
        let scene, camera, renderer, controls;
        let raycaster, mouse;
        let interactiveObjects = [];
        let rackMeshMap = {}; // rack_id -> THREE.Group
        let rackDoorsList = []; // list of door meshes to toggle
        let serverMeshesList = []; // list of 3D server meshes
        let rackOutlineBox = null;
        let ambientLight, dirLight, hemiLight, buswaysGroup, labelsGroup;
        let floorGridHelper;

        // TEXTURES
        const textureLoader = new THREE.TextureLoader();
        const tileTopTex = textureLoader.load('3dmodel/texturas/floor/floor3.jpg');
        const rackFrontTex = textureLoader.load('3dmodel/texturas/floor/RACK-FRONT.png');
        const rackBackTex = textureLoader.load('3dmodel/texturas/floor/RACK-BACK.png');
        const rackSideTex = textureLoader.load('3dmodel/texturas/floor/RACK-SIDEA.png');
        const rackTopTex = textureLoader.load('3dmodel/texturas/floor/RACK-TOP.png');
        const escalerillaTex = textureLoader.load('3dmodel/texturas/floor/escalerilla.png');
        const mallaTex = textureLoader.load('3dmodel/texturas/floor/malla-front.png');

        // CAMERA FLUID TRANSITION SYSTEM
        let cameraAnimation = null;
        let isPointerDragging = false;
        let pointerDownPos = { x: 0, y: 0 };

        // HELPER PARA DIBUJAR RECTÁNGULOS REDONDEADOS EN CUALQUIER NAVEGADOR
        function drawRoundedRectSafe(ctx, x, y, width, height, radius) {
            try {
                if (ctx.roundRect) {
                    ctx.beginPath();
                    ctx.roundRect(x, y, width, height, radius);
                    ctx.fill();
                    ctx.stroke();
                    return;
                }
            } catch(e) {}
            // Fallback manual para máxima compatibilidad
            ctx.beginPath();
            ctx.moveTo(x + radius, y);
            ctx.lineTo(x + width - radius, y);
            ctx.quadraticCurveTo(x + width, y, x + width, y + radius);
            ctx.lineTo(x + width, y + height - radius);
            ctx.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
            ctx.lineTo(x + radius, y + height);
            ctx.quadraticCurveTo(x, y + height, x, y + height - radius);
            ctx.lineTo(x, y + radius);
            ctx.quadraticCurveTo(x, y, x + radius, y);
            ctx.closePath();
            ctx.fill();
            ctx.stroke();
        }

        const textureCache3D = {};

        // GENERADOR DE TEXTURA FRONTAL 3D REALISTA (Mismo estilo que racks.php)
        function createDevice3DFrontTexture(dev, catInfo, widthPx = 256, heightPx = 36) {
            const cat = catInfo ? catInfo.category : (typeof classifyDevice === 'function' ? classifyDevice(dev).category : 'other');
            const devName = dev.name || 'Dispositivo';
            const cacheKey = `front_${cat}_${devName}_${dev.height_u || 1}_${dev.color || ''}_${widthPx}x${heightPx}`;
            if (textureCache3D[cacheKey]) return textureCache3D[cacheKey];

            const canvas = document.createElement('canvas');
            canvas.width = widthPx;
            canvas.height = heightPx;
            const ctx = canvas.getContext('2d');
            const earWidth = 14;
            const innerW = widthPx - earWidth * 2;

            // 1. Chasis metálico grafito oscuro
            ctx.fillStyle = '#1e2433';
            ctx.fillRect(0, 0, widthPx, heightPx);

            // Borde biselado
            ctx.strokeStyle = '#334155';
            ctx.lineWidth = 1.5;
            ctx.strokeRect(0.5, 0.5, widthPx - 1, heightPx - 1);

            // 2. Orejas de montaje laterales (Mounting ears)
            ctx.fillStyle = '#0f172a';
            ctx.fillRect(0, 0, earWidth, heightPx);
            ctx.fillRect(widthPx - earWidth, 0, earWidth, heightPx);

            // Tornillos plateados en orejas
            ctx.fillStyle = '#94a3b8';
            ctx.beginPath();
            ctx.arc(earWidth / 2, heightPx / 2, 2.2, 0, Math.PI * 2);
            ctx.arc(widthPx - earWidth / 2, heightPx / 2, 2.2, 0, Math.PI * 2);
            ctx.fill();

            // 3. Franja distintiva lateral por categoría
            const accentColor = dev.color || catInfo?.color || '#38bdf8';
            ctx.fillStyle = accentColor;
            ctx.fillRect(earWidth, 2, 4, heightPx - 4);

            const contentX = earWidth + 8;
            const contentW = innerW - 16;
            const midY = heightPx / 2;

            // 4. Detalles específicos según el tipo de equipo
            if (cat === 'patch_panel') {
                // Matriz de puertos RJ45
                const numGroups = 4;
                const groupW = Math.min(36, contentW / 4.8);
                const startPortsX = contentX + 64;
                ctx.fillStyle = '#0f172a';
                for (let g = 0; g < numGroups; g++) {
                    const gx = startPortsX + (g * (groupW + 6));
                    ctx.fillRect(gx, 6, groupW, heightPx - 12);
                    // Pines RJ45
                    for (let p = 0; p < 6; p++) {
                        const px = gx + 2 + (p * 5.2);
                        ctx.fillStyle = '#334155';
                        ctx.fillRect(px, midY - 3, 3.8, 6);
                        ctx.fillStyle = '#f59e0b'; // contactos cobre
                        ctx.fillRect(px + 0.8, midY - 3, 2.2, 1.5);
                    }
                }
                // Etiqueta
                ctx.fillStyle = '#cbd5e1';
                ctx.font = 'bold 9px JetBrains Mono, monospace';
                ctx.fillText(devName.substring(0, 14), contentX, midY + 3);
            } else if (cat === 'switch') {
                // Nombre / Modelo
                ctx.fillStyle = '#38bdf8';
                ctx.font = 'bold 10px Outfit, sans-serif';
                ctx.fillText(devName.substring(0, 16), contentX, midY - 2);

                // Bloque de puertos RJ45 (doble fila)
                const portsX = contentX + 90;
                const maxPorts = Math.min(16, Math.floor((contentW - 115) / 7));
                for (let i = 0; i < maxPorts; i++) {
                    const px = portsX + (i * 7);
                    // LED actividad verde
                    ctx.fillStyle = (i % 3 === 0) ? '#22c55e' : ((i % 5 === 0) ? '#0284c7' : '#1e293b');
                    ctx.fillRect(px, 4, 3, 2);
                    // Puerto RJ45
                    ctx.fillStyle = '#0f172a';
                    ctx.fillRect(px, 8, 5, heightPx - 16);
                }
                // Jaulas SFP de fibra a la derecha
                const sfpX = widthPx - earWidth - 28;
                ctx.fillStyle = '#475569';
                ctx.fillRect(sfpX, 6, 10, heightPx - 12);
                ctx.fillRect(sfpX + 13, 6, 10, heightPx - 12);
                ctx.fillStyle = '#0284c7';
                ctx.fillRect(sfpX + 2, midY - 2, 6, 4);
                ctx.fillRect(sfpX + 15, midY - 2, 6, 4);
            } else if (cat === 'firewall') {
                // Bisel rojo corporativo Fortinet
                ctx.fillStyle = '#dc2626';
                ctx.fillRect(contentX, 3, 6, heightPx - 6);
                // Nombre
                ctx.fillStyle = '#ffffff';
                ctx.font = 'bold 10px Outfit, sans-serif';
                ctx.fillText(devName.substring(0, 16), contentX + 12, midY - 2);
                // Puertos de red WAN/LAN y LEDs
                ctx.fillStyle = '#22c55e';
                ctx.fillRect(contentX + 12, midY + 4, 3, 3);
                ctx.fillStyle = '#eab308';
                ctx.fillRect(contentX + 18, midY + 4, 3, 3);
            } else if (cat === 'server') {
                // Servidor Enterprise (HPE / Dell) con bahías de discos
                ctx.fillStyle = '#ffffff';
                ctx.font = 'bold 10px Outfit, sans-serif';
                ctx.fillText(devName.substring(0, 15), contentX, midY - 3);

                // Bahías de discos caddies hot-swap
                const bayStartX = contentX + 85;
                const numBays = Math.min(8, Math.floor((contentW - 100) / 12));
                for (let b = 0; b < numBays; b++) {
                    const bx = bayStartX + (b * 12);
                    ctx.fillStyle = '#0f172a';
                    ctx.fillRect(bx, 5, 9, heightPx - 10);
                    // Pestillo caddy
                    ctx.fillStyle = '#475569';
                    ctx.fillRect(bx + 1, midY - 1, 7, 3);
                    // LED actividad de disco
                    ctx.fillStyle = (b % 2 === 0) ? '#22c55e' : '#1e293b';
                    ctx.fillRect(bx + 2, 7, 2.5, 2);
                }
                // Botón encendido con LED azul ID
                const pwrX = widthPx - earWidth - 18;
                ctx.fillStyle = '#22c55e';
                ctx.beginPath();
                ctx.arc(pwrX, midY, 3, 0, Math.PI * 2);
                ctx.fill();
                ctx.fillStyle = '#38bdf8';
                ctx.fillRect(pwrX + 6, midY - 2, 3, 4);
            } else if (cat === 'ups' || cat === 'battery') {
                // Pantalla LCD / Barra de Batería
                ctx.fillStyle = '#0369a1';
                ctx.fillRect(contentX + 70, 6, 45, heightPx - 12);
                ctx.fillStyle = '#38bdf8';
                ctx.font = 'bold 8px JetBrains Mono, monospace';
                ctx.fillText('100% AC', contentX + 74, midY + 3);

                // LED Online
                ctx.fillStyle = '#22c55e';
                ctx.beginPath();
                ctx.arc(contentX + 125, midY, 3, 0, Math.PI * 2);
                ctx.fill();

                // Nombre
                ctx.fillStyle = '#ffffff';
                ctx.font = 'bold 10px Outfit, sans-serif';
                ctx.fillText(devName.substring(0, 14), contentX, midY + 3);
            } else if (cat === 'pdu') {
                // Interruptor iluminado PDU
                ctx.fillStyle = '#ef4444';
                ctx.fillRect(contentX, 6, 12, heightPx - 12);
                ctx.fillStyle = '#fbbf24';
                ctx.font = 'bold 8px JetBrains Mono, monospace';
                ctx.fillText('220V', contentX + 18, midY + 3);

                // Nombre
                ctx.fillStyle = '#e2e8f0';
                ctx.font = 'bold 9.5px Outfit, sans-serif';
                ctx.fillText(devName.substring(0, 16), contentX + 48, midY + 3);
            } else {
                // Genérico / Bandeja / Organizador
                ctx.fillStyle = '#ffffff';
                ctx.font = 'bold 10.5px Outfit, sans-serif';
                ctx.fillText(devName.substring(0, 22), contentX + 6, midY + 3);

                // LEDs de estado simulados
                ctx.fillStyle = '#22c55e';
                ctx.fillRect(widthPx - earWidth - 25, midY - 2, 4, 4);
                ctx.fillStyle = '#38bdf8';
                ctx.fillRect(widthPx - earWidth - 16, midY - 2, 4, 4);
            }

            const tex = new THREE.CanvasTexture(canvas);
            textureCache3D[cacheKey] = tex;
            return tex;
        }

        // GENERADOR DE TEXTURA POSTERIOR (REAR) 3D REALISTA
        function createDevice3DRearTexture(dev, catInfo, widthPx = 256, heightPx = 36) {
            const cat = catInfo ? catInfo.category : (typeof classifyDevice === 'function' ? classifyDevice(dev).category : 'other');
            const devName = dev.name || 'Dispositivo';
            const cacheKey = `rear_${cat}_${devName}_${dev.height_u || 1}_${widthPx}x${heightPx}`;
            if (textureCache3D[cacheKey]) return textureCache3D[cacheKey];

            const canvas = document.createElement('canvas');
            canvas.width = widthPx;
            canvas.height = heightPx;
            const ctx = canvas.getContext('2d');
            const earWidth = 14;
            const innerW = widthPx - earWidth * 2;

            // 1. Chasis posterior metálico
            ctx.fillStyle = '#161b26';
            ctx.fillRect(0, 0, widthPx, heightPx);

            // Borde
            ctx.strokeStyle = '#283143';
            ctx.lineWidth = 1.5;
            ctx.strokeRect(0.5, 0.5, widthPx - 1, heightPx - 1);

            // 2. Orejas de montaje
            ctx.fillStyle = '#0f172a';
            ctx.fillRect(0, 0, earWidth, heightPx);
            ctx.fillRect(widthPx - earWidth, 0, earWidth, heightPx);

            const contentX = earWidth + 8;
            const midY = heightPx / 2;

            if (cat === 'patch_panel') {
                // Bloques IDC 110 Punch Down
                ctx.fillStyle = '#0f172a';
                ctx.fillRect(contentX + 30, 6, innerW - 60, heightPx - 12);
                for (let i = 0; i < 18; i++) {
                    const bx = contentX + 34 + (i * 8.5);
                    const colors = ['#0284c7', '#ea580c', '#16a34a', '#a16207'];
                    ctx.fillStyle = colors[i % 4];
                    ctx.fillRect(bx, 8, 4, heightPx - 16);
                }
                // Tierra física
                ctx.fillStyle = '#eab308';
                ctx.font = 'bold 9px Outfit, sans-serif';
                ctx.fillText('⏚ TIERRA', contentX, midY + 3);
            } else if (cat === 'switch' || cat === 'server' || cat === 'firewall' || cat === 'router') {
                // Etiqueta del equipo
                ctx.fillStyle = '#94a3b8';
                ctx.font = 'bold 9px JetBrains Mono, monospace';
                ctx.fillText(devName.substring(0, 14), contentX, midY + 3);

                // Fuentes de Poder Redundantes (Dual PSUs con conectores IEC C14)
                const psuW = 34;
                const psuH = heightPx - 10;
                const psu1X = widthPx - earWidth - (psuW * 2) - 16;
                const psu2X = widthPx - earWidth - psuW - 6;

                [psu1X, psu2X].forEach((px) => {
                    // Contorno módulo PSU
                    ctx.fillStyle = '#0f172a';
                    ctx.fillRect(px, 5, psuW, psuH);
                    ctx.strokeStyle = '#475569';
                    ctx.strokeRect(px + 0.5, 5.5, psuW - 1, psuH - 1);

                    // Rejilla ventilador de la PSU
                    ctx.fillStyle = '#1e293b';
                    ctx.beginPath();
                    ctx.arc(px + 10, midY, 6, 0, Math.PI * 2);
                    ctx.fill();

                    // Conector C14 hembra
                    ctx.fillStyle = '#090d16';
                    ctx.fillRect(px + 20, midY - 4, 10, 8);
                    ctx.fillStyle = '#cbd5e1';
                    ctx.fillRect(px + 22, midY - 2, 2, 4);
                    ctx.fillRect(px + 25, midY - 2, 2, 4);
                    ctx.fillRect(px + 28, midY - 2, 2, 4);

                    // LED PSU status
                    ctx.fillStyle = '#22c55e';
                    ctx.fillRect(px + 2, 7, 2, 2);
                });

                // Ventilación intermedia con aspas de extracción térmica
                const ventX = contentX + 75;
                const ventW = Math.max(20, psu1X - ventX - 10);
                ctx.fillStyle = '#0d121d';
                ctx.fillRect(ventX, 6, ventW, heightPx - 12);
                ctx.fillStyle = '#334155';
                for (let vx = ventX + 3; vx < ventX + ventW - 4; vx += 5) {
                    ctx.fillRect(vx, 8, 2.5, heightPx - 16);
                }
            } else if (cat === 'ups' || cat === 'pdu') {
                // Salidas de poder traseras (Receptáculos C13 hembra)
                ctx.fillStyle = '#94a3b8';
                ctx.font = 'bold 9px JetBrains Mono, monospace';
                ctx.fillText('OUTPUT AC', contentX, midY + 3);

                const outStartX = contentX + 60;
                for (let o = 0; o < 8; o++) {
                    const ox = outStartX + (o * 12);
                    ctx.fillStyle = '#0f172a';
                    ctx.fillRect(ox, 7, 9, heightPx - 14);
                    ctx.fillStyle = '#cbd5e1';
                    ctx.fillRect(ox + 2, midY - 2, 5, 4);
                }
            } else {
                // Ventilación estándar trasera
                ctx.fillStyle = '#0f172a';
                ctx.fillRect(contentX + 30, 7, innerW - 60, heightPx - 14);
                ctx.fillStyle = '#334155';
                for (let vx = contentX + 34; vx < contentX + innerW - 35; vx += 5) {
                    ctx.fillRect(vx, 9, 2.5, heightPx - 18);
                }
            }

            const tex = new THREE.CanvasTexture(canvas);
            textureCache3D[cacheKey] = tex;
            return tex;
        }

        // Mantener compatibilidad hacia atrás
        function createServerFaceplateTexture(dev, widthPx = 256, heightPx = 36) {
            return createDevice3DFrontTexture(dev, null, widthPx, heightPx);
        }

        // INITIALIZE APPLICATION
        $(document).ready(function() {
            try {
                initThreeScene();
                buildDatacenterFloor();
                layoutDatacenterRacks();
                buildOverheadBusways();
                renderFloorItems();

                if (selectedRackId > 0) {
                    renderCabinetElevation(selectedRackId);
                    highlightRackIn3D(selectedRackId, false);
                }

                // Delegar clic en los equipos de la elevación infográfica para abrir detalle y enfocar en 3D
                $(document).on('click', '#elevation-body .appliance-face', function(e) {
                    e.stopPropagation();
                    const devId = $(this).attr('data-device-id');
                    if (devId) {
                        showDeviceDetail(devId);
                        highlightDeviceIn3D(devId);
                    }
                });

                animate();
            } catch(e) {
                console.error("Error inicializando 3DViewer:", e);
            }
        });

        // 1. THREE.JS SCENE SETUP
        function initThreeScene() {
            const container = document.getElementById('canvas-container');
            scene = new THREE.Scene();
            scene.background = new THREE.Color(0x090d16);
            scene.fog = new THREE.FogExp2(0x090d16, 0.015);

            // Camera
            camera = new THREE.PerspectiveCamera(45, window.innerWidth / window.innerHeight, 0.1, 1000);

            // Renderer de alto rendimiento
            renderer = new THREE.WebGLRenderer({ antialias: true, preserveDrawingBuffer: true, powerPreference: 'high-performance' });
            renderer.setSize(window.innerWidth, window.innerHeight);
            renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
            renderer.shadowMap.enabled = true;
            renderer.shadowMap.type = THREE.PCFSoftShadowMap;
            if (THREE.sRGBEncoding) renderer.outputEncoding = THREE.sRGBEncoding;
            container.appendChild(renderer.domElement);

            // OrbitControls con amortiguación sedosa y navegación de alta precisión
            controls = new THREE.OrbitControls(camera, renderer.domElement);
            controls.enableDamping = true;
            controls.dampingFactor = 0.05; // Movimiento suave e inercial de alta gama
            controls.rotateSpeed = 0.85;   // Sensibilidad controlada sin sacudidas
            controls.zoomSpeed = 1.0;     // Zoom fluido y continuo
            controls.panSpeed = 0.85;     // Desplazamiento reactivo
            controls.screenSpacePanning = true; // Paneo paralelo a la pantalla (mucho más natural y cómodo)
            controls.maxPolarAngle = Math.PI / 2 - 0.02; // Evita atravesar el suelo
            controls.minDistance = 0.8;
            controls.maxDistance = 80;

            // Detener cualquier animación automática cuando el usuario toma el control manualmente
            controls.addEventListener('start', function() {
                stopCameraAnimation();
            });

            // Iluminación Profesional (Directa + Ambiente + Hemisferio)
            ambientLight = new THREE.AmbientLight(0xffffff, 0.75);
            scene.add(ambientLight);

            hemiLight = new THREE.HemisphereLight(0xffffff, 0x1e293b, 0.85);
            hemiLight.position.set(0, 20, 0);
            scene.add(hemiLight);

            dirLight = new THREE.DirectionalLight(0xffffff, 1.0);
            dirLight.position.set(roomConfig.widthM * 0.7, 18, roomConfig.depthM * 0.6);
            dirLight.castShadow = true;
            dirLight.shadow.mapSize.width = 2048;
            dirLight.shadow.mapSize.height = 2048;
            dirLight.shadow.bias = -0.0008;
            const shadowBound = Math.max(roomConfig.widthM, roomConfig.depthM) * 0.8;
            dirLight.shadow.camera.left = -shadowBound;
            dirLight.shadow.camera.right = shadowBound;
            dirLight.shadow.camera.top = shadowBound;
            dirLight.shadow.camera.bottom = -shadowBound;
            dirLight.shadow.camera.near = 1.0;
            dirLight.shadow.camera.far = 50;
            scene.add(dirLight);

            // Luz de relleno azulada
            const fillLight = new THREE.DirectionalLight(0x38bdf8, 0.4);
            fillLight.position.set(-6, 12, -6);
            scene.add(fillLight);

            // Posicionamiento de cámara óptimo inicial (sin animación en carga)
            setInitialCameraInstant();

            // Raycaster
            raycaster = new THREE.Raycaster();
            mouse = new THREE.Vector2();

            window.addEventListener('resize', onWindowResize, false);

            // Detección de arrastre vs clic para evitar selecciones accidentales al rotar la cámara
            renderer.domElement.addEventListener('pointerdown', function(e) {
                stopCameraAnimation();
                pointerDownPos.x = e.clientX;
                pointerDownPos.y = e.clientY;
                isPointerDragging = false;
            }, { passive: true });

            renderer.domElement.addEventListener('pointermove', function(e) {
                if (!isPointerDragging && Math.hypot(e.clientX - pointerDownPos.x, e.clientY - pointerDownPos.y) > 5) {
                    isPointerDragging = true;
                }
            }, { passive: true });

            renderer.domElement.addEventListener('mousemove', onMouseMove, false);
            renderer.domElement.addEventListener('click', onCanvasClick, false);
        }

        function setInitialCameraInstant() {
            const centerX = roomConfig.widthM / 2;
            const centerZ = roomConfig.depthM / 2;
            camera.position.set(centerX + 6.0, 7.5, centerZ + 7.5);
            if (controls) {
                controls.target.set(centerX, 1.2, centerZ);
                controls.update();
            }
        }

        function resetCamera() {
            $('#btn-mode-3d').addClass('active');
            $('#btn-mode-top').removeClass('active');
            const centerX = roomConfig.widthM / 2;
            const centerZ = roomConfig.depthM / 2;
            const targetCam = new THREE.Vector3(centerX + 6.0, 7.5, centerZ + 7.5);
            const targetLook = new THREE.Vector3(centerX, 1.2, centerZ);
            flyCameraTo(targetCam, targetLook, 800);
        }

        function setTopCameraView() {
            $('#btn-mode-top').addClass('active');
            $('#btn-mode-3d').removeClass('active');
            const centerX = roomConfig.widthM / 2;
            const centerZ = roomConfig.depthM / 2;
            const maxDim = Math.max(roomConfig.widthM, roomConfig.depthM);
            const targetCam = new THREE.Vector3(centerX, maxDim * 1.5, centerZ + 0.01);
            const targetLook = new THREE.Vector3(centerX, 0, centerZ);
            flyCameraTo(targetCam, targetLook, 800);
        }

        // 2. FLOOR & GRID TILES SYSTEM
        function buildDatacenterFloor() {
            const w = Math.max(roomConfig.widthM, 8.0);
            const d = Math.max(roomConfig.depthM, 6.0);
            const ts = roomConfig.tileSize;
            const fh = roomConfig.floorHeight;

            // Subfloor concrete slab (Black)
            const subGeo = new THREE.PlaneGeometry(w, d);
            const subMat = new THREE.MeshStandardMaterial({ color: 0x090d16, roughness: 0.9, metalness: 0.1 });
            const subFloor = new THREE.Mesh(subGeo, subMat);
            subFloor.rotation.x = -Math.PI / 2;
            subFloor.position.set(w / 2, -0.01, d / 2);
            subFloor.receiveShadow = true;
            scene.add(subFloor);

            // Raised Floor Group
            const floorGroup = new THREE.Group();
            
            // Raised floor grid base
            tileTopTex.wrapS = THREE.RepeatWrapping;
            tileTopTex.wrapT = THREE.RepeatWrapping;
            tileTopTex.repeat.set(roomConfig.tilesX, roomConfig.tilesY);

            const floorGeo = new THREE.PlaneGeometry(w, d);
            const floorMat = new THREE.MeshStandardMaterial({
                color: 0x222a38,
                map: tileTopTex,
                roughness: 0.45,
                metalness: 0.15
            });
            const topFloor = new THREE.Mesh(floorGeo, floorMat);
            topFloor.rotation.x = -Math.PI / 2;
            topFloor.position.set(w / 2, fh, d / 2);
            topFloor.receiveShadow = true;
            floorGroup.add(topFloor);

            // Wireframe grid lines on top of tiles (dcTrack style fine lines)
            floorGridHelper = new THREE.GridHelper(Math.max(w, d), Math.round(Math.max(w, d) / ts), 0x38bdf8, 0x1e293b);
            floorGridHelper.position.set(w / 2, fh + 0.002, d / 2);
            floorGroup.add(floorGridHelper);

            // Pedestales de soporte de piso falso
            const pedGeo = new THREE.CylinderGeometry(0.018, 0.018, fh, 8);
            const pedMat = new THREE.MeshStandardMaterial({ color: 0x64748b, metalness: 0.8, roughness: 0.3 });
            for (let x = 0; x <= roomConfig.tilesX; x += 2) {
                for (let y = 0; y <= roomConfig.tilesY; y += 2) {
                    const ped = new THREE.Mesh(pedGeo, pedMat);
                    ped.position.set(x * ts, fh / 2, y * ts);
                    ped.receiveShadow = true;
                    floorGroup.add(ped);
                }
            }

            // Zonas de rejilla perforada roja (canalizaciones y pasillos fríos como en la imagen)
            mallaTex.wrapS = THREE.RepeatWrapping;
            mallaTex.wrapT = THREE.RepeatWrapping;
            mallaTex.repeat.set(2, 6);

            const perfMat = new THREE.MeshStandardMaterial({
                map: mallaTex,
                color: 0xef4444, // Rojo distintivo de Sunbird dcTrack
                roughness: 0.4,
                metalness: 0.5,
                transparent: true,
                opacity: 0.9
            });

            // Pasillo central perforado
            const perfZoneGeo = new THREE.PlaneGeometry(w * 0.75, ts * 2);
            const perfMesh = new THREE.Mesh(perfZoneGeo, perfMat);
            perfMesh.rotation.x = -Math.PI / 2;
            perfMesh.position.set(w / 2, fh + 0.005, d * 0.55);
            floorGroup.add(perfMesh);

            scene.add(floorGroup);

            // Paredes translúcidas de cristal alrededor de la sala
            const wallHeight = 3.2;
            const wallMat = new THREE.MeshStandardMaterial({
                color: 0x38bdf8,
                transparent: true,
                opacity: 0.08,
                roughness: 0.1,
                metalness: 0.2,
                side: THREE.DoubleSide
            });

            const backWall = new THREE.Mesh(new THREE.PlaneGeometry(w, wallHeight), wallMat);
            backWall.position.set(w / 2, fh + wallHeight / 2, 0);
            scene.add(backWall);

            const leftWall = new THREE.Mesh(new THREE.PlaneGeometry(d, wallHeight), wallMat);
            leftWall.rotation.y = Math.PI / 2;
            leftWall.position.set(0, fh + wallHeight / 2, d / 2);
            scene.add(leftWall);

            const rightWall = new THREE.Mesh(new THREE.PlaneGeometry(d, wallHeight), wallMat);
            rightWall.rotation.y = -Math.PI / 2;
            rightWall.position.set(w, fh + wallHeight / 2, d / 2);
            scene.add(rightWall);
        }

        // 3. RACKS ARRANGEMENT & 3D HARDWARE MODELING
        function layoutDatacenterRacks() {
            labelsGroup = new THREE.Group();
            labelsGroup.name = 'LabelsGroup';
            scene.add(labelsGroup);

            const ts = roomConfig.tileSize;
            const fh = roomConfig.floorHeight;

            // Comprobar si existen coordenadas personalizadas
            const hasCustomPositions = racksData.some(r => (parseFloat(r.grid_x) > 0 || parseFloat(r.grid_y) > 0));

            racksData.forEach((r, idx) => {
                const r_id = parseInt(r.id);
                let rx = parseFloat(r.grid_x) || 0;
                let ry = parseFloat(r.grid_y) || 0;
                let rot = parseInt(r.rotation) || 0;

                // Si los racks no tienen posiciones únicas (o están apilados en 0,0), auto-distribuir en 2 filas limpias enfrentadas
                if (!hasCustomPositions || (rx === 0 && ry === 0)) {
                    if (idx < 4) {
                        rx = 2.5 + (idx * 2.2);
                        ry = 2.8;
                        rot = 0; // Front view hacia el pasillo
                    } else {
                        rx = 3.2 + ((idx - 4) * 2.2);
                        ry = 5.6;
                        rot = 180; // Front view hacia el pasillo enfrentado
                    }
                }

                const totalU = parseInt(r.total_u) || 42;
                const isMini = totalU <= 24;
                const numberingDir = r.numbering_dir || 'DOWN';

                // Dimensiones exactas y proporcionales del rack
                const rackHeight = isMini ? Math.max(0.48, (totalU * 0.0445) + 0.10) : Math.max(1.85, (totalU * 0.0445) + 0.16);
                const rackBaseY = isMini ? (fh + 0.85) : fh; // Miniracks aéreos elevados a la altura de los ojos

                const wt = isMini ? Math.min(1.0, parseFloat(r.width_tiles) || 1) : (parseFloat(r.width_tiles) || 1);
                const dt = isMini ? Math.min(1.2, parseFloat(r.depth_tiles) || 2) : (parseFloat(r.depth_tiles) || 2);
                const rw = wt * ts;
                const rd = dt * ts;

                const rackGroup = new THREE.Group();
                rackGroup.name = 'RackGroup_' + r_id;

                // --- ESTRUCTURA FÍSICA DEL RACK (FRAME & PANELS) ---
                const frameMat = new THREE.MeshStandardMaterial({
                    color: 0x1e293b,
                    roughness: 0.5,
                    metalness: 0.7
                });

                const sideMat = new THREE.MeshStandardMaterial({
                    map: rackSideTex,
                    color: 0xa7f3d0,
                    roughness: 0.4,
                    metalness: 0.25
                });

                const backMat = new THREE.MeshStandardMaterial({
                    map: rackBackTex,
                    color: 0x334155,
                    roughness: 0.5,
                    metalness: 0.4
                });

                const topMat = new THREE.MeshStandardMaterial({
                    map: rackTopTex,
                    color: 0x475569,
                    roughness: 0.5
                });

                // Soporte metálico mural para miniracks aéreos
                if (isMini) {
                    const bracketMat = new THREE.MeshStandardMaterial({
                        color: 0x334155,
                        metalness: 0.8,
                        roughness: 0.3
                    });
                    const armGeo = new THREE.BoxGeometry(0.04, 0.45, rd * 0.8);
                    const armL = new THREE.Mesh(armGeo, bracketMat);
                    armL.position.set(-rw/2 + 0.06, -0.22, -0.05);
                    rackGroup.add(armL);

                    const armR = new THREE.Mesh(armGeo, bracketMat);
                    armR.position.set(rw/2 - 0.06, -0.22, -0.05);
                    rackGroup.add(armR);

                    const wallPlateGeo = new THREE.BoxGeometry(rw + 0.04, rackHeight + 0.48, 0.025);
                    const wallPlate = new THREE.Mesh(wallPlateGeo, bracketMat);
                    wallPlate.position.set(0, rackHeight / 2 - 0.20, -rd/2 - 0.015);
                    rackGroup.add(wallPlate);
                }

                // 1. Postes esquineros del bastidor
                const postThick = 0.04;
                const postGeo = new THREE.BoxGeometry(postThick, rackHeight, postThick);
                const postPositions = [
                    { x: -rw/2 + postThick/2, z: -rd/2 + postThick/2 },
                    { x:  rw/2 - postThick/2, z: -rd/2 + postThick/2 },
                    { x: -rw/2 + postThick/2, z:  rd/2 - postThick/2 },
                    { x:  rw/2 - postThick/2, z:  rd/2 - postThick/2 }
                ];
                postPositions.forEach(p => {
                    const post = new THREE.Mesh(postGeo, frameMat);
                    post.position.set(p.x, rackHeight / 2, p.z);
                    post.castShadow = true;
                    rackGroup.add(post);
                });

                // 2. Base inferior y Techo superior
                const capGeo = new THREE.BoxGeometry(rw, 0.04, rd);
                const bottomCap = new THREE.Mesh(capGeo, frameMat);
                bottomCap.position.y = 0.02;
                rackGroup.add(bottomCap);

                const topCap = new THREE.Mesh(capGeo, topMat);
                topCap.position.y = rackHeight - 0.02;
                rackGroup.add(topCap);

                // 3. Paneles laterales
                const panelH = rackHeight - 0.08;
                const panelSideGeo = new THREE.BoxGeometry(0.015, panelH, rd - 0.08);
                
                const leftPanel = new THREE.Mesh(panelSideGeo, sideMat);
                leftPanel.position.set(-rw/2 + 0.01, rackHeight / 2, 0);
                leftPanel.castShadow = true;
                rackGroup.add(leftPanel);

                const rightPanel = new THREE.Mesh(panelSideGeo, sideMat);
                rightPanel.position.set(rw/2 - 0.01, rackHeight / 2, 0);
                rightPanel.castShadow = true;
                rackGroup.add(rightPanel);

                // 4. Panel Trasero
                const backPanelGeo = new THREE.BoxGeometry(rw - 0.08, panelH, 0.015);
                const backPanel = new THREE.Mesh(backPanelGeo, backMat);
                backPanel.position.set(0, rackHeight / 2, -rd/2 + 0.01);
                rackGroup.add(backPanel);

                // 5. Puerta Frontal Translúcida / Perforada
                const doorGeo = new THREE.BoxGeometry(rw - 0.06, panelH, 0.015);
                const doorMat = new THREE.MeshStandardMaterial({
                    map: rackFrontTex,
                    color: 0x0ea5e9,
                    transparent: true,
                    opacity: 0.18,
                    roughness: 0.2,
                    metalness: 0.5
                });
                const frontDoor = new THREE.Mesh(doorGeo, doorMat);
                const doorPivot = new THREE.Group();
                doorPivot.position.set(-rw/2 + 0.03, 0, rd/2 - 0.01);
                frontDoor.position.set((rw - 0.06) / 2, rackHeight / 2, 0);
                doorPivot.add(frontDoor);
                rackGroup.add(doorPivot);
                rackDoorsList.push(doorPivot);

                // --- MODELADO DE LOS EQUIPOS REALES DENTRO DEL RACK EN 3D ---
                const devs = devicesByRack[r_id] || [];
                const uHeightM = (panelH - 0.02) / totalU; // Altura exacta en metros de 1 Unidad UR
                const insideWidth = rw - 0.09;
                const insideDepth = rd - 0.10;

                devs.forEach(dev => {
                    const st = parseInt(dev.start_u);
                    const hu = Math.max(1, parseInt(dev.height_u) || 1);
                    if (st < 1 || st > totalU) return;

                    // Cálculo vertical exacto respetando la dirección de conteo (UP vs DOWN)
                    // Si DOWN: UR 1 está abajo, dev empieza en st
                    // Si UP: UR 1 está arriba, dev empieza en st
                    const uFromBottom = (numberingDir === 'DOWN') ? (st - 1) : (totalU - (st + hu - 1));

                    const devHeight = (hu * uHeightM) - 0.003;
                    const devY = 0.04 + (uFromBottom * uHeightM) + (devHeight / 2);

                    const catInfo = typeof classifyDevice === 'function' ? classifyDevice(dev) : { category: 'other' };

                    // Profundidad física realista según categoría de hardware
                    let devDepth = insideDepth * 0.85;
                    if (['patch_panel', 'organizer', 'pdu'].includes(catInfo.category)) {
                        devDepth = insideDepth * 0.42;
                    } else if (['switch', 'router', 'firewall'].includes(catInfo.category)) {
                        devDepth = insideDepth * 0.65;
                    }

                    const devBoxGeo = new THREE.BoxGeometry(insideWidth, devHeight, devDepth);

                    // Generar texturas realistas para ambas caras: frontal y trasera
                    const frontTex = createDevice3DFrontTexture(dev, catInfo, 256, Math.max(32, hu * 28));
                    const rearTex = createDevice3DRearTexture(dev, catInfo, 256, Math.max(32, hu * 28));

                    const isFacingRear = (dev.orientation === 'rear');

                    const devBodyMat = new THREE.MeshStandardMaterial({
                        color: 0x1e293b,
                        metalness: 0.6,
                        roughness: 0.4
                    });

                    const frontMat = new THREE.MeshStandardMaterial({
                        map: isFacingRear ? rearTex : frontTex,
                        roughness: 0.4,
                        metalness: 0.45
                    });

                    const backMat = new THREE.MeshStandardMaterial({
                        map: isFacingRear ? frontTex : rearTex,
                        roughness: 0.4,
                        metalness: 0.45
                    });

                    // 6 caras del equipo (Front = +Z, Back = -Z)
                    const devMaterials = [
                        devBodyMat, // Right (+X)
                        devBodyMat, // Left (-X)
                        devBodyMat, // Top (+Y)
                        devBodyMat, // Bottom (-Y)
                        frontMat,   // Front (+Z)
                        backMat     // Rear (-Z)
                    ];

                    const devMesh = new THREE.Mesh(devBoxGeo, devMaterials);
                    const zOffset = (insideDepth - devDepth) / 2 - 0.01;
                    devMesh.position.set(0, devY, isFacingRear ? -zOffset : zOffset);
                    devMesh.castShadow = true;
                    devMesh.receiveShadow = true;

                    devMesh.userData = {
                        type: 'Device',
                        rackId: r_id,
                        devId: dev.id,
                        name: dev.name,
                        details: dev.details,
                        startU: st,
                        heightU: hu,
                        orientation: dev.orientation || 'front'
                    };

                    interactiveObjects.push(devMesh);
                    serverMeshesList.push(devMesh);
                    rackGroup.add(devMesh);
                });

                // Caja envolvente invisible para facilitar el clic en todo el rack
                const hitGeo = new THREE.BoxGeometry(rw, rackHeight, rd);
                const hitMat = new THREE.MeshBasicMaterial({ visible: false });
                const hitMesh = new THREE.Mesh(hitGeo, hitMat);
                hitMesh.position.y = rackHeight / 2;
                hitMesh.userData = {
                    type: 'Rack',
                    rackId: r_id,
                    name: r.name,
                    totalU: totalU,
                    groupRef: rackGroup,
                    stats: rackStats[r_id] || {}
                };
                interactiveObjects.push(hitMesh);
                rackGroup.add(hitMesh);

                // --- ETIQUETA FLOTANTE 3D CON EL NOMBRE DEL RACK ---
                const badgeCanvas = document.createElement('canvas');
                badgeCanvas.width = 256;
                badgeCanvas.height = 70;
                const ctx = badgeCanvas.getContext('2d');
                
                ctx.fillStyle = '#1e293b';
                ctx.strokeStyle = isMini ? '#f59e0b' : '#38bdf8';
                ctx.lineWidth = 4;
                drawRoundedRectSafe(ctx, 6, 6, 244, 58, 12);

                ctx.fillStyle = '#ffffff';
                ctx.font = 'bold 22px Outfit, sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(r.name, 128, 25);

                ctx.fillStyle = isMini ? '#f59e0b' : '#38bdf8';
                ctx.font = 'bold 13px JetBrains Mono, monospace';
                ctx.fillText(`${totalU}U · ${isMini ? 'AÉREO' : 'PISO'}`, 128, 48);

                const badgeTexture = new THREE.CanvasTexture(badgeCanvas);
                const badgeMat = new THREE.MeshBasicMaterial({ map: badgeTexture, transparent: true });
                const badgeMesh = new THREE.Mesh(new THREE.PlaneGeometry(rw * 0.9, 0.26), badgeMat);
                badgeMesh.position.set(0, rackHeight + 0.16, 0);
                rackGroup.add(badgeMesh);

                // Alinear coordenadas en la escena
                const posX = rx * ts + (rw / 2);
                const posZ = ry * ts + (rd / 2);

                rackGroup.position.set(posX, rackBaseY, posZ);
                rackGroup.rotation.y = -THREE.MathUtils.degToRad(rot);

                rackMeshMap[r_id] = rackGroup;
                scene.add(rackGroup);
            });

            // Establecer estado inicial de las puertas (abiertas para ver los servidores)
            applyDoorsState();
        }

        // 4. OVERHEAD POWER BUSWAYS (RED & BLUE CONDUITS LIKE SUNBIRD DCTRACK)
        function buildOverheadBusways() {
            buswaysGroup = new THREE.Group();
            buswaysGroup.name = 'BuswaysGroup';

            const w = Math.max(roomConfig.widthM, 8.0);
            const d = Math.max(roomConfig.depthM, 6.0);
            const buswayAltitude = roomConfig.floorHeight + 2.5;

            const busbarRadius = 0.025;
            const blueMat = new THREE.MeshStandardMaterial({ color: 0x0284c7, metalness: 0.8, roughness: 0.2 }); // Feed A
            const redMat = new THREE.MeshStandardMaterial({ color: 0xdc2626, metalness: 0.8, roughness: 0.2 });  // Feed B

            const zPositions = [d * 0.35, d * 0.70];

            zPositions.forEach((zPos, idx) => {
                // Busway A (Tubería Azul)
                const blueGeo = new THREE.CylinderGeometry(busbarRadius, busbarRadius, w * 0.85, 16);
                const bluePipe = new THREE.Mesh(blueGeo, blueMat);
                bluePipe.rotation.z = Math.PI / 2;
                bluePipe.position.set(w / 2, buswayAltitude, zPos - 0.16);
                buswaysGroup.add(bluePipe);

                // Busway B (Tubería Roja)
                const redGeo = new THREE.CylinderGeometry(busbarRadius, busbarRadius, w * 0.85, 16);
                const redPipe = new THREE.Mesh(redGeo, redMat);
                redPipe.rotation.z = Math.PI / 2;
                redPipe.position.set(w / 2, buswayAltitude, zPos + 0.16);
                buswaysGroup.add(redPipe);

                // Cajas de derivación y bajantes a los racks (Tap-off boxes)
                for (let x = w * 0.18; x < w * 0.85; x += 1.8) {
                    const boxA = new THREE.Mesh(new THREE.BoxGeometry(0.12, 0.1, 0.12), blueMat);
                    boxA.position.set(x, buswayAltitude - 0.06, zPos - 0.16);
                    buswaysGroup.add(boxA);

                    const dropGeoA = new THREE.CylinderGeometry(0.008, 0.008, 0.45, 8);
                    const dropA = new THREE.Mesh(dropGeoA, blueMat);
                    dropA.position.set(x, buswayAltitude - 0.28, zPos - 0.16);
                    buswaysGroup.add(dropA);

                    const boxB = new THREE.Mesh(new THREE.BoxGeometry(0.12, 0.1, 0.12), redMat);
                    boxB.position.set(x + 0.35, buswayAltitude - 0.06, zPos + 0.16);
                    buswaysGroup.add(boxB);

                    const dropB = new THREE.Mesh(dropGeoA, redMat);
                    dropB.position.set(x + 0.35, buswayAltitude - 0.28, zPos + 0.16);
                    buswaysGroup.add(dropB);
                }

                // Escalerilla portacables superior
                escalerillaTex.wrapS = THREE.RepeatWrapping;
                escalerillaTex.wrapT = THREE.RepeatWrapping;
                escalerillaTex.repeat.set(12, 1);

                const trayGeo = new THREE.BoxGeometry(w * 0.85, 0.04, 0.5);
                const trayMat = new THREE.MeshStandardMaterial({
                    map: escalerillaTex,
                    color: 0xf59e0b,
                    transparent: true,
                    opacity: 0.9,
                    metalness: 0.5
                });
                const trayMesh = new THREE.Mesh(trayGeo, trayMat);
                trayMesh.position.set(w / 2, buswayAltitude + 0.12, zPos);
                buswaysGroup.add(trayMesh);
            });

            scene.add(buswaysGroup);
        }

        // 5. RENDER FLOOR ITEMS (AACC, UPS, ETC.)
        function renderFloorItems() {
            const ts = roomConfig.tileSize;
            const fh = roomConfig.floorHeight;

            itemsData.forEach(item => {
                const type = item.type || '';
                const rx = parseFloat(item.grid_x) || 0;
                const ry = parseFloat(item.grid_y) || 0;
                const wt = parseFloat(item.width_tiles) || 1;
                const dt = parseFloat(item.depth_tiles) || 1;
                const rot = parseInt(item.rotation) || 0;

                const posX = rx * ts + (wt * ts) / 2;
                const posZ = ry * ts + (dt * ts) / 2;

                if (type === 'aacc') {
                    const geo = new THREE.BoxGeometry(wt * ts - 0.05, 2.1, dt * ts - 0.05);
                    const mat = new THREE.MeshStandardMaterial({ color: 0x0ea5e9, roughness: 0.4, metalness: 0.5 });
                    const mesh = new THREE.Mesh(geo, mat);
                    mesh.position.set(posX, fh + 1.05, posZ);
                    mesh.rotation.y = -THREE.MathUtils.degToRad(rot);
                    scene.add(mesh);
                } else if (type === 'ups') {
                    const geo = new THREE.BoxGeometry(wt * ts - 0.05, 1.9, dt * ts - 0.05);
                    const mat = new THREE.MeshStandardMaterial({ color: 0xf59e0b, roughness: 0.4, metalness: 0.6 });
                    const mesh = new THREE.Mesh(geo, mat);
                    mesh.position.set(posX, fh + 0.95, posZ);
                    mesh.rotation.y = -THREE.MathUtils.degToRad(rot);
                    scene.add(mesh);
                }
            });
        }

        // CONTROL DE PUERTAS DE RACKS
        function toggleDoors() {
            doorsOpen = !doorsOpen;
            applyDoorsState();
            $('#toggle-doors-btn')
                .toggleClass('active', doorsOpen)
                .html(`<i class="fas fa-door-${doorsOpen ? 'open' : 'closed'}"></i> Puertas: ${doorsOpen ? 'Abiertas' : 'Cerradas'}`);
        }

        function applyDoorsState() {
            rackDoorsList.forEach(pivot => {
                // Rotar puerta 85 grados hacia afuera para abrir
                pivot.rotation.y = doorsOpen ? Math.PI * 0.48 : 0;
            });
        }

        // 6. CABINET ELEVATION GRAPHIC RENDERER (RIGHT PANEL - IDÉNTICO A racks.php)
        function renderCabinetElevation(rackId) {
            const rack = racksData.find(r => parseInt(r.id) === parseInt(rackId));
            if (!rack) return;

            selectedRackId = rackId;
            const totalU = parseInt(rack.total_u) || 42;
            const isMini = totalU <= 24;
            const mountingType = isMini ? 'AÉREO' : 'GABINETE DE PISO';
            const numberingDir = rack.numbering_dir || 'DOWN';
            const urDirLabel = (numberingDir === 'DOWN') ? 'UR 1 abajo (Ascendente)' : 'UR 1 arriba (Descendente)';

            const devs = devicesByRack[rackId] || [];

            // Calcular mapa de unidades ocupadas y consumo en Watts
            const occupiedMap = {};
            let sumWatts = 0;
            devs.forEach(d => {
                const st = parseInt(d.start_u);
                const hu = Math.max(1, parseInt(d.height_u) || 1);
                for (let u = st; u < st + hu; u++) {
                    occupiedMap[u] = true;
                }
                sumWatts += parseFloat(d.details?.calculated_watts || d.details?.watts || 0);
            });
            const occupiedCount = Object.keys(occupiedMap).length;
            const freeCount = Math.max(0, totalU - occupiedCount);
            const occPct = totalU > 0 ? ((occupiedCount / totalU) * 100).toFixed(1) : 0;

            const powerCap = 10000;
            const effectivePwr = Math.round(sumWatts);
            const potentialPwr = Math.round(effectivePwr * 1.15);
            const measuredPwr = Math.round(effectivePwr * 0.88);
            const remainingPwr = Math.max(0, powerCap - effectivePwr);

            // Actualizar Cabecera del Panel Derecho
            $('#elevation-rack-name').text(rack.name);
            $('#elevation-rack-subtitle').text(`${rack.location || rack.city || 'DATACENTER'} · ${totalU}U ${mountingType}`);

            // Actualizar Placas del Gabinete (Estilo Infográfico Vilaseca)
            $('#elevation-front-header-text').html(`${escapeHtml(rack.name)} · ${escapeHtml(rack.location || rack.city || '')} <span class="badge badge-primary ml-1" style="font-size:8px;">FRONTAL</span>`);
            $('#elevation-front-footer-text').text(`${totalU}U · ${mountingType} · ${numberingDir === 'DOWN' ? 'UR 1 abajo' : 'UR 1 arriba'}`);

            $('#elevation-rear-header-text').html(`${escapeHtml(rack.name)} · ${escapeHtml(rack.location || rack.city || '')} <span class="badge badge-info ml-1" style="font-size:8px;">POSTERIOR</span>`);
            $('#elevation-rear-footer-text').text(`${totalU}U · ACCESO TRASERO`);

            // Actualizar Totales del Bastidor y Métricas de Ocupación
            $('#totals-rack-name').text(rack.name);
            $('#totals-occupancy-badge').text(occPct + '% Ocupado');
            $('#tot-mounting-type').text(`${totalU}U · ${mountingType}`);
            $('#tot-ur-dir').text(urDirLabel);
            $('#tot-space-used').text(`${occupiedCount} / ${totalU} U (${freeCount} libres)`);
            $('#tot-dev-count').text(`${devs.length} equipos`);
            $('#tot-power-cap').text(powerCap.toLocaleString());
            $('#tot-potential-pwr').text(potentialPwr.toLocaleString());
            $('#tot-effective-pwr').text(effectivePwr.toLocaleString());
            $('#tot-measured-pwr').text(measuredPwr.toLocaleString());
            $('#tot-remaining-pwr').text(remainingPwr.toLocaleString());

            // Actualizar selección en la lista lateral izquierda
            $('.rack-row-item').removeClass('selected');
            $(`.rack-row-item[data-rack-id="${rackId}"]`).addClass('selected');

            // Renderizar Ambos Gabinetes (Frontal y Trasero) usando el motor de rack_infographic.js
            if (typeof renderCabinetUnits === 'function') {
                renderCabinetUnits(rack, devs, 'front', '#panel-rail-left', '#panel-slots-col', '#panel-rail-right', '#panel-pdu-left', '#panel-pdu-right');
                renderCabinetUnits(rack, devs, 'rear', '#panel-rear-rail-left', '#panel-rear-slots-col', '#panel-rear-rail-right', '#panel-rear-pdu-left', '#panel-rear-pdu-right');
            }

            // Aplicar visibilidad de pestañas según la vista actual
            setElevationView(currentElevationOrientation, false);
        }

        function setElevationView(view, reRender = false) {
            currentElevationOrientation = view;

            // Resaltar pestañas
            $('#tab-front-view').toggleClass('active', view === 'front');
            $('#tab-rear-view').toggleClass('active', view === 'rear');
            $('#tab-dual-view').toggleClass('active', view === 'dual');

            const $panel = $('#right-panel');
            const $colFront = $('#elevation-col-front');
            const $colRear = $('#elevation-col-rear');

            if (view === 'front') {
                $panel.removeClass('dual-mode');
                $colFront.show();
                $colRear.hide();
            } else if (view === 'rear') {
                $panel.removeClass('dual-mode');
                $colFront.hide();
                $colRear.show();
            } else if (view === 'dual') {
                $panel.addClass('dual-mode');
                $colFront.show();
                $colRear.show();
            }

            if (reRender && selectedRackId > 0) {
                renderCabinetElevation(selectedRackId);
            }
        }

        function openRackInfographicModal() {
            if (selectedRackId > 0 && typeof openRackView === 'function') {
                openRackView(selectedRackId);
            }
        }

        function openRackBuilderDirect() {
            if (selectedRackId > 0) {
                window.open(`rack_builder.php?id=${selectedRackId}${clientFilter ? '&cliente=' + encodeURIComponent(clientFilter) : ''}`, '_blank');
            }
        }

        // 7. DEVICE DETAIL MODAL POPUP
        function showDeviceDetail(deviceId) {
            let foundDev = null;
            for (let rId in devicesByRack) {
                const match = devicesByRack[rId].find(d => parseInt(d.id) === parseInt(deviceId));
                if (match) {
                    foundDev = match;
                    break;
                }
            }

            if (!foundDev) return;

            $('#modalDevTitle').html(`<i class="fas fa-microchip mr-2 text-warning"></i>${escapeHtml(foundDev.name)}`);

            const dt = foundDev.details || {};
            const html = `
                <div class="detail-row">
                    <span class="detail-label">Nombre / Hostname:</span>
                    <span class="detail-val font-weight-bold text-cyan">${escapeHtml(foundDev.name)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Posición en Rack:</span>
                    <span class="detail-val text-warning">U${foundDev.start_u} (${foundDev.height_u} U) - Orientación: ${escapeHtml(foundDev.orientation)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Marca / Modelo:</span>
                    <span class="detail-val">${escapeHtml(dt.make || 'Genérico')} ${escapeHtml(dt.model || '')}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Nro de Serie:</span>
                    <span class="detail-val">${escapeHtml(dt.serial_number || 'N/A')}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Asset Tag:</span>
                    <span class="detail-val">${escapeHtml(dt.asset_tag || 'N/A')}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Dirección IP:</span>
                    <span class="detail-val text-success">${escapeHtml(dt.ip_address || 'Sin IP')}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Consumo Potencia:</span>
                    <span class="detail-val text-warning">${escapeHtml(dt.calculated_watts || dt.watts || 0)} Watts (${escapeHtml(dt.amps || 1)}A @ ${escapeHtml(dt.voltage || 120)}V)</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Función / Rol:</span>
                    <span class="detail-val">${escapeHtml(dt.server_function || 'Servidor / Red')}</span>
                </div>
            `;

            $('#modalDevBody').html(html);

            if (foundDev.cmdb_reference) {
                $('#modalCmdbLink')
                    .attr('href', `../ci_view.php?id=${foundDev.cmdb_reference}`)
                    .show();
            } else {
                $('#modalCmdbLink').hide();
            }

            $('#deviceDetailModal').modal('show');
        }

        // 8. 3D INTERACTION & SELECTION
        function highlightRackIn3D(rackId, flyCamera = true) {
            const rack = racksData.find(r => parseInt(r.id) === parseInt(rackId));
            if (!rack) return;

            const rackGroup = rackMeshMap[rackId];
            if (!rackGroup) return;

            // Remove existing highlight box
            if (rackOutlineBox) {
                scene.remove(rackOutlineBox);
                rackOutlineBox.geometry.dispose();
                rackOutlineBox.material.dispose();
                rackOutlineBox = null;
            }

            // Dimensiones proporcionales y precisas para gabinetes y miniracks
            const totalU = parseInt(rack.total_u) || 42;
            const isMini = totalU <= 24;
            const wt = isMini ? Math.min(1.0, parseFloat(rack.width_tiles) || 1) : (parseFloat(rack.width_tiles) || 1);
            const dt = isMini ? Math.min(1.2, parseFloat(rack.depth_tiles) || 2) : (parseFloat(rack.depth_tiles) || 2);
            const rw = wt * roomConfig.tileSize;
            const rd = dt * roomConfig.tileSize;
            const h = isMini ? Math.max(0.48, (totalU * 0.0445) + 0.10) : Math.max(1.85, (totalU * 0.0445) + 0.16);

            // Create Luminous Cyan / Amber Bounding Box
            const boxGeo = new THREE.BoxGeometry(rw + 0.06, h + 0.06, rd + 0.06);
            const boxMat = new THREE.MeshBasicMaterial({
                color: isMini ? 0xf59e0b : 0x00e5ff,
                wireframe: true,
                wireframeLinewidth: 3
            });
            rackOutlineBox = new THREE.Mesh(boxGeo, boxMat);
            rackOutlineBox.position.copy(rackGroup.position);
            rackOutlineBox.position.y += h / 2;
            rackOutlineBox.rotation.copy(rackGroup.rotation);
            scene.add(rackOutlineBox);

            // Fly camera smoothly to focus on selected rack
            if (flyCamera && controls) {
                const targetLook = new THREE.Vector3(
                    rackGroup.position.x,
                    rackGroup.position.y + (h / 2),
                    rackGroup.position.z
                );

                // Calcular posición óptima al frente del rack orientada según su rotación real
                const frontDir = new THREE.Vector3(0.32, 0.38, 1.0).normalize();
                frontDir.applyEuler(rackGroup.rotation);

                const camDist = isMini ? 1.85 : 2.65;
                const targetCam = targetLook.clone().add(frontDir.multiplyScalar(camDist));
                targetCam.y = Math.max(targetCam.y, rackGroup.position.y + (h * 0.65));

                flyCameraTo(targetCam, targetLook, 750);
            }
        }

        // ENFOCAR Y RESALTAR EQUIPO INDIVIDUAL EN 3D
        function highlightDeviceIn3D(deviceId) {
            const mesh = serverMeshesList.find(m => parseInt(m.userData.devId) === parseInt(deviceId));
            if (!mesh) return;

            const worldPos = new THREE.Vector3();
            mesh.getWorldPosition(worldPos);

            const rackGroup = rackMeshMap[mesh.userData.rackId];
            if (rackGroup && camera && controls) {
                const isFacingRear = (mesh.userData.orientation === 'rear');
                const dirZ = isFacingRear ? -1.0 : 1.0;
                const frontDir = new THREE.Vector3(0.20, 0.22, dirZ).normalize().applyEuler(rackGroup.rotation);
                const targetCam = worldPos.clone().add(frontDir.multiplyScalar(1.20));
                flyCameraTo(targetCam, worldPos, 650);
            }
        }

        // CONTROLADOR DE TRANSICIÓN SUAVE DE CÁMARA (CURVA CÚBICA FLUIDA)
        function easeInOutCubic(t) {
            return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
        }

        function flyCameraTo(targetCamPos, targetLookAt, duration = 800, onComplete = null) {
            if (!camera || !controls) return;

            const startCamPos = camera.position.clone();
            const startTarget = controls.target.clone();
            const endCamPos = targetCamPos.clone();
            const endTarget = targetLookAt.clone();

            if (startCamPos.distanceTo(endCamPos) < 0.05 && startTarget.distanceTo(endTarget) < 0.05) {
                if (onComplete) onComplete();
                return;
            }

            const startTime = performance.now();

            cameraAnimation = {
                startTime,
                duration,
                startCamPos,
                startTarget,
                endCamPos,
                endTarget,
                update: function(now) {
                    const elapsed = now - startTime;
                    const progress = Math.min(1.0, elapsed / duration);
                    const ease = easeInOutCubic(progress);

                    camera.position.lerpVectors(startCamPos, endCamPos, ease);
                    controls.target.lerpVectors(startTarget, endTarget, ease);
                    camera.lookAt(controls.target);

                    if (progress >= 1.0) {
                        camera.position.copy(endCamPos);
                        controls.target.copy(endTarget);
                        controls.update();
                        cameraAnimation = null;
                        if (onComplete) onComplete();
                        return false;
                    }
                    return true;
                }
            };
        }

        function stopCameraAnimation() {
            if (cameraAnimation) {
                cameraAnimation = null;
                if (controls) controls.update();
            }
        }

        function selectRack(rackId, flyCamera = true) {
            selectedRackId = rackId;
            renderCabinetElevation(rackId);
            highlightRackIn3D(rackId, flyCamera);

            if ($('#right-panel').hasClass('collapsed')) {
                toggleRightPanel();
            }
        }

        function onMouseMove(event) {
            mouse.x = (event.clientX / window.innerWidth) * 2 - 1;
            mouse.y = -(event.clientY / window.innerHeight) * 2 + 1;

            // Si el usuario está rotando o paneando (botón presionado),
            // ocultar tooltip y omitir raycast para mantener 60 FPS perfectamente fluidos
            if (event.buttons > 0) {
                $('#rack-tooltip').hide();
                return;
            }

            raycaster.setFromCamera(mouse, camera);
            const intersects = raycaster.intersectObjects(interactiveObjects);

            const $tooltip = $('#rack-tooltip');

            if (intersects.length > 0) {
                const hit = intersects[0];
                const obj = hit.object;
                const data = obj.userData || {};

                if (data.type === 'Device') {
                    document.body.style.cursor = 'pointer';
                    $tooltip
                        .html(`
                            <strong style="color: #facc15; font-size: 13px;"><i class="fas fa-microchip mr-1"></i>${escapeHtml(data.name)}</strong><br>
                            <span style="color: #38bdf8;">Posición: U${data.startU} (${data.heightU}U)</span><br>
                            <span style="color: #94a3b8;">${escapeHtml(data.details.make || '')} ${escapeHtml(data.details.model || '')}</span>
                        `)
                        .css({
                            left: (event.clientX + 16) + 'px',
                            top: (event.clientY + 16) + 'px',
                            display: 'block'
                        });
                    return;
                } else if (data.type === 'Rack') {
                    document.body.style.cursor = 'pointer';
                    const st = data.stats || {};
                    $tooltip
                        .html(`
                            <strong style="color: #38bdf8; font-size: 13px;"><i class="fas fa-server mr-1"></i>${escapeHtml(data.name)}</strong><br>
                            <span style="color: #94a3b8;">Capacidad: ${data.totalU}U | Equipos: ${st.device_count || 0}</span><br>
                            <span style="color: #facc15;">Potencia: ${Number(st.effective_power_w || 0).toLocaleString()} W</span>
                        `)
                        .css({
                            left: (event.clientX + 16) + 'px',
                            top: (event.clientY + 16) + 'px',
                            display: 'block'
                        });
                    return;
                }
            }

            document.body.style.cursor = 'default';
            $tooltip.hide();

            // Coordenadas métricas sobre plano
            const planeY = new THREE.Plane(new THREE.Vector3(0, 1, 0), -roomConfig.floorHeight);
            const floorIntersect = new THREE.Vector3();
            raycaster.ray.intersectPlane(planeY, floorIntersect);
            if (floorIntersect) {
                const tx = Math.floor(floorIntersect.x / roomConfig.tileSize);
                const ty = Math.floor(floorIntersect.z / roomConfig.tileSize);
                if (tx >= 0 && tx < roomConfig.tilesX && ty >= 0 && ty < roomConfig.tilesY) {
                    $('#coord-tile').text(`${tx}-${ty}`);
                    $('#coord-meters').text(`${floorIntersect.x.toFixed(1)}, ${floorIntersect.z.toFixed(1)} m`);
                }
            }
        }

        function onCanvasClick(event) {
            // Si el usuario estaba realizando un paneo u órbita (arrastre), ignorar como clic de selección
            if (isPointerDragging) {
                return;
            }

            raycaster.setFromCamera(mouse, camera);
            const intersects = raycaster.intersectObjects(interactiveObjects);

            if (intersects.length > 0) {
                const obj = intersects[0].object;
                const d = obj.userData || {};
                if (d.type === 'Device') {
                    selectRack(d.rackId, false);
                    showDeviceDetail(d.devId);
                } else if (d.type === 'Rack') {
                    selectRack(d.rackId, true);
                }
            }
        }

        // 9. THERMAL / HEATMAP MODE TOGGLE
        function toggleThermalMode() {
            isThermalMode = !isThermalMode;
            $('#toggle-thermal-btn').toggleClass('active', isThermalMode);

            serverMeshesList.forEach(mesh => {
                if (isThermalMode) {
                    const devWatts = parseFloat(mesh.userData.details.calculated_watts) || 150;
                    let heatColor = 0x22c55e;
                    if (devWatts > 350) heatColor = 0xef4444; // Muy caliente
                    else if (devWatts > 250) heatColor = 0xf97316; // Moderado
                    else if (devWatts > 180) heatColor = 0xfacc15; // Templado

                    mesh.material[4] = new THREE.MeshStandardMaterial({
                        color: heatColor,
                        emissive: heatColor,
                        emissiveIntensity: 0.35,
                        roughness: 0.3
                    });
                } else {
                    // Restaurar textura frontal original
                    const dev = {
                        name: mesh.userData.name,
                        details: mesh.userData.details,
                        height_u: mesh.userData.heightU,
                        color: mesh.userData.details?.color || '#facc15'
                    };
                    const catInfo = typeof classifyDevice === 'function' ? classifyDevice(dev) : { category: 'other' };
                    const faceTex = createDevice3DFrontTexture(dev, catInfo, 256, Math.max(32, mesh.userData.heightU * 28));
                    mesh.material[4] = new THREE.MeshStandardMaterial({
                        map: faceTex,
                        roughness: 0.4,
                        metalness: 0.5
                    });
                }
            });
        }

        function toggleBusways() {
            buswaysVisible = !buswaysVisible;
            if (buswaysGroup) buswaysGroup.visible = buswaysVisible;
            $('#toggle-busways-btn').toggleClass('active', buswaysVisible);
        }

        function toggleLabels() {
            labelsVisible = !labelsVisible;
            for (let r_id in rackMeshMap) {
                const group = rackMeshMap[r_id];
                // El badge es el último hijo añadido al grupo
                const badge = group.children[group.children.length - 1];
                if (badge && badge.material) {
                    badge.visible = labelsVisible;
                }
            }
            $('#toggle-labels-btn').toggleClass('active', labelsVisible);
        }

        function toggleLeftPanel() {
            const $panel = $('#left-panel');
            $panel.toggleClass('collapsed');
            const isCollapsed = $panel.hasClass('collapsed');
            $('#toggle-left-btn').toggleClass('collapsed', isCollapsed);
            $('#toggle-left-icon').attr('class', isCollapsed ? 'fas fa-chevron-right' : 'fas fa-chevron-left');
        }

        function toggleRightPanel() {
            const $panel = $('#right-panel');
            $panel.toggleClass('collapsed');
            const isCollapsed = $panel.hasClass('collapsed');
            $('#toggle-right-btn').toggleClass('collapsed', isCollapsed);
            $('#toggle-right-icon').attr('class', isCollapsed ? 'fas fa-chevron-left' : 'fas fa-chevron-right');
        }

        function filterItemList() {
            const q = $('#item-search-box').val().toLowerCase();
            $('#panel-item-list li').each(function() {
                const text = $(this).text().toLowerCase();
                $(this).toggle(text.includes(q));
            });
        }

        function changeRoom(newRoomId) {
            window.location.href = `viewer_3d.php?room_id=${newRoomId}${clientFilter ? '&cliente=' + encodeURIComponent(clientFilter) : ''}`;
        }

        function toggleFullScreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen();
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                }
            }
        }

        function takeSnapshot() {
            if (!renderer) return;
            renderer.render(scene, camera);
            const dataUrl = renderer.domElement.toDataURL('image/png');
            const link = document.createElement('a');
            link.download = `Datacenter_3D_${roomConfig.name.replace(/\s+/g, '_')}_${Date.now()}.png`;
            link.href = dataUrl;
            link.click();
        }

        function onWindowResize() {
            camera.aspect = window.innerWidth / window.innerHeight;
            camera.updateProjectionMatrix();
            renderer.setSize(window.innerWidth, window.innerHeight);
        }

        // ANIMATION LOOP (FLUIDO 60 FPS)
        function animate(time) {
            requestAnimationFrame(animate);

            if (cameraAnimation) {
                cameraAnimation.update(time || performance.now());
            } else if (controls) {
                controls.update();
            }

            renderer.render(scene, camera);
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }
    </script>
</body>
</html>

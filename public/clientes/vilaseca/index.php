<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../src/auth.php';
require_once __DIR__ . '/../../../src/permissions_helper.php';
require_once __DIR__ . '/../../../src/db.php';
require_once __DIR__ . '/../../../config.php';
require_login();
if (!has_module_access('clientes') && !has_module_access('vilaseca')) {
    header("Location: " . PUBLIC_URL_PREFIX . "/dashboard.php");
    exit;
}

$pdo = getPDO();

// 1. Estadísticas de Datacenter Vilaseca
$vl_rooms_stmt = $pdo->prepare("SELECT COUNT(*) FROM dc_rooms WHERE UPPER(client) = 'VILASECA' OR client IS NULL OR client = ''");
$vl_rooms_stmt->execute();
$vl_rooms_count = (int)$vl_rooms_stmt->fetchColumn();

$vl_racks_stmt = $pdo->prepare("SELECT r.*, rm.name as room_name, 
                                       (SELECT COUNT(*) FROM dc_rack_devices rd WHERE rd.rack_id = r.id) as device_count 
                                FROM dc_racks r 
                                LEFT JOIN dc_rooms rm ON r.room_id = rm.id 
                                WHERE (UPPER(r.client) LIKE '%VILASECA%' OR r.client IS NULL OR r.client = '')
                                ORDER BY r.city ASC, r.location ASC, r.name ASC");
$vl_racks_stmt->execute();
$vl_racks = $vl_racks_stmt->fetchAll(PDO::FETCH_ASSOC);
$vl_racks_count = count($vl_racks);

$vl_total_u = 0;
$vl_cities = [];
foreach ($vl_racks as $vr) {
    $vl_total_u += (int)($vr['total_u'] ?: 42);
    if (!empty($vr['city']) && !in_array($vr['city'], $vl_cities)) {
        $vl_cities[] = $vr['city'];
    }
}

// 2. Análisis de Infraestructura y Relevamiento Vilaseca
$vl_surveys_stmt = $pdo->prepare("SELECT id, client, location, area, device_name, device_label, device_type, ports_count, rack, ur_rack, ip_address, ports_data_json, created_at FROM manual_portmap_surveys WHERE UPPER(client) LIKE '%VILASECA%' ORDER BY location ASC, rack ASC, device_name ASC");
$vl_surveys_stmt->execute();
$vl_surveys_list = $vl_surveys_stmt->fetchAll(PDO::FETCH_ASSOC);
$vl_surveys_count = count($vl_surveys_list);

$vl_total_ports = 0;
$vl_occupied_ports = 0;
$vl_vacant_ports = 0;
$vl_types = [];
$vl_locations = [];
$vl_areas = [];
$vl_racks_list = [];
$vl_all_devices = [];
$vl_racks_agg = [];

foreach ($vl_surveys_list as $s) {
    $dev_type = trim($s['device_type'] ?: 'Switch');
    $loc = trim($s['location'] ?: 'General');
    $ar = trim($s['area'] ?: 'General');
    $rk = trim($s['rack'] ?: 'Sin Rack');
    
    $vl_types[$dev_type] = ($vl_types[$dev_type] ?? 0) + 1;
    if (!isset($vl_locations[$loc])) {
        $vl_locations[$loc] = ['devices' => 0, 'ports' => 0, 'occupied' => 0, 'vacant' => 0];
    }
    $vl_locations[$loc]['devices']++;

    if ($ar !== '' && !in_array($ar, $vl_areas)) {
        $vl_areas[] = $ar;
    }
    if ($rk !== '' && !in_array($rk, $vl_racks_list)) {
        $vl_racks_list[] = $rk;
    }

    $p_json = json_decode($s['ports_data_json'] ?: '[]', true);
    $d_total = (int)($s['ports_count'] ?: 0);
    $d_occ = 0;
    $d_vac = 0;

    if (is_array($p_json) && count($p_json) > 0) {
        $d_total = count($p_json);
        foreach ($p_json as $p) {
            $st = strtolower($p['status'] ?? '');
            $has_d = !empty($p['dest_device']) || !empty($p['dest_dev']) || !empty($p['dest_device_name']);
            if ($st === 'connected' || $st === 'conectado' || $has_d) {
                $d_occ++;
            } else {
                $d_vac++;
            }
        }
    } else {
        $d_vac = $d_total;
    }

    $vl_total_ports += $d_total;
    $vl_occupied_ports += $d_occ;
    $vl_vacant_ports += $d_vac;

    $vl_locations[$loc]['ports'] += $d_total;
    $vl_locations[$loc]['occupied'] += $d_occ;
    $vl_locations[$loc]['vacant'] += $d_vac;

    // Racks aggregation
    $rk_key = $loc . '___' . $rk;
    if (!isset($vl_racks_agg[$rk_key])) {
        $vl_racks_agg[$rk_key] = [
            'location' => $loc,
            'rack_name' => $rk,
            'devices_count' => 0,
            'ports_count' => 0
        ];
    }
    $vl_racks_agg[$rk_key]['devices_count']++;
    $vl_racks_agg[$rk_key]['ports_count'] += $d_total;

    $rate = $d_total > 0 ? round(($d_occ / $d_total) * 100, 1) : 0;
    $vl_all_devices[] = [
        'id' => (int)$s['id'],
        'device_name' => $s['device_name'],
        'location' => $loc,
        'area' => $ar,
        'rack' => $rk,
        'device_type' => $dev_type,
        'ip_address' => $s['ip_address'] ?: 'Sin IP',
        'total' => $d_total,
        'ports_count' => $d_total,
        'occupied' => $d_occ,
        'vacant' => $d_vac,
        'rate' => $rate
    ];
}
sort($vl_areas);
sort($vl_racks_list);

$vl_locations_count = count($vl_locations);
$vl_occupancy_rate = $vl_total_ports > 0 ? round(($vl_occupied_ports / $vl_total_ports) * 100, 1) : 0;

// Sort Tops
$top_vacant = $vl_all_devices;
usort($top_vacant, fn($a, $b) => $b['vacant'] <=> $a['vacant']);
$top_vacant = array_slice($top_vacant, 0, 5);

$top_occupied = $vl_all_devices;
usort($top_occupied, fn($a, $b) => $b['occupied'] <=> $a['occupied']);
$top_occupied = array_slice($top_occupied, 0, 5);

// Sort Racks
uasort($vl_racks_agg, fn($a, $b) => $b['devices_count'] <=> $a['devices_count']);

$page_title = "VILASECA - Dashboard (Manage)";
require_once __DIR__ . '/../../partials/header.php';
?>
<script src="<?php echo PUBLIC_URL_PREFIX; ?>/clientes/vilaseca/chart.umd.min.js?v=<?php echo filemtime(__DIR__ . '/chart.umd.min.js'); ?>"></script>
<script>
if (typeof Chart === 'undefined') {
    document.write('<script src="https://cdn.jsdelivr.net/npm/chart.js"><\/script>');
}
</script>

<style>
/* PANTONE CORPORATIVO SONDA */
:root {
    --sonda-navy: #101B31;
    --sonda-navy-light: #1A2744;
    --sonda-orange: #ff5c05;
    --sonda-orange-hover: #e04e04;
    --sonda-cyan: #00B8D4;
    --sonda-green: #c0da20;
    --sonda-border: #e3e6f0;
    --sonda-bg: #f4f6f9;
}

/* CONTENEDOR PRINCIPAL */
.vl-main-shell {
    border-radius: 14px;
    border: 1px solid var(--sonda-border);
    box-shadow: 0 4px 20px rgba(16, 27, 49, 0.08);
    background: #ffffff;
    overflow: hidden;
    margin-top: 15px;
    margin-bottom: 30px;
}

/* CABECERA PANTONE SONDA */
.vl-executive-header {
    background: linear-gradient(135deg, #101B31 0%, #1A2744 50%, #243860 100%);
    color: #ffffff;
    padding: 22px 28px;
    border-left: 5px solid var(--sonda-orange);
}

.vl-header-tabs-bar {
    background: #101B31;
    padding: 10px 16px 0 16px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

/* PESTAÑAS SONDA */
.vl-nav-tabs {
    border-bottom: none;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 0;
    padding-left: 0;
    list-style: none;
}

.vl-nav-tabs .nav-item {
    flex: 1 1 0;
    min-width: 160px;
    text-align: center;
}

.vl-nav-tabs .nav-link {
    color: rgba(255, 255, 255, 0.85);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-bottom: none;
    border-top-left-radius: 10px;
    border-top-right-radius: 10px;
    padding: 12px 14px;
    font-size: 0.85rem;
    font-weight: 600;
    background-color: rgba(255, 255, 255, 0.06);
    transition: all 0.25s ease-in-out;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    white-space: nowrap;
}

.vl-nav-tabs .nav-link:hover {
    color: #ffffff;
    background-color: rgba(255, 255, 255, 0.15);
    border-color: rgba(255, 255, 255, 0.25);
    transform: translateY(-1px);
}

.vl-nav-tabs .nav-link.active {
    color: var(--sonda-navy) !important;
    background-color: #ffffff !important;
    border-color: #ffffff !important;
    font-weight: 700 !important;
    box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.15);
    position: relative;
}

.vl-nav-tabs .nav-link.active::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--sonda-orange);
    border-top-left-radius: 10px;
    border-top-right-radius: 10px;
}

/* TARJETAS KPI PANTONE SONDA */
.stat-kpi-card {
    border-radius: 12px;
    border: 1px solid var(--sonda-border);
    background: #ffffff;
    box-shadow: 0 2px 10px rgba(16, 27, 49, 0.04);
    padding: 16px 18px;
    transition: all 0.25s ease-in-out;
    position: relative;
    overflow: hidden;
    height: 100%;
}

.stat-kpi-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(16, 27, 49, 0.09);
}

.stat-kpi-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
}

.stat-kpi-card.kpi-orange::before { background: var(--sonda-orange); }
.stat-kpi-card.kpi-cyan::before { background: var(--sonda-cyan); }
.stat-kpi-card.kpi-green::before { background: var(--sonda-green); }
.stat-kpi-card.kpi-navy::before { background: var(--sonda-navy); }

.stat-kpi-num {
    font-size: 1.85rem;
    font-weight: 800;
    line-height: 1.1;
    color: var(--sonda-navy);
}

.stat-kpi-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: #64748b;
    margin-bottom: 4px;
}

/* MEDALLAS Y RANKING PILLS */
.rank-badge-pill {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 0.75rem;
}
.rank-gold { background: linear-gradient(135deg, #ffd700, #ffa500); color: #000; box-shadow: 0 2px 6px rgba(255, 215, 0, 0.4); }
.rank-silver { background: linear-gradient(135deg, #e0e0e0, #bdbdbd); color: #000; }
.rank-bronze { background: linear-gradient(135deg, #cd7f32, #a0522d); color: #fff; }
.rank-normal { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }

/* PASTILLAS DE SEDE */
.pill-loc-btn {
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 700;
    padding: 5px 14px;
    transition: all 0.2s;
    cursor: pointer;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #475569;
}
.pill-loc-btn.active {
    background: var(--sonda-navy) !important;
    color: #ffffff !important;
    border-color: var(--sonda-navy) !important;
    box-shadow: 0 2px 8px rgba(16, 27, 49, 0.25);
}

/* TABLAS Y BADGES */
.vl-table thead th {
    background: var(--sonda-navy);
    color: #ffffff;
    text-transform: uppercase;
    font-size: 0.7rem;
    letter-spacing: 0.7px;
    font-weight: 700;
    padding: 11px 14px;
    border: none;
}
.vl-table tbody td {
    padding: 10px 14px;
    vertical-align: middle;
    font-size: 0.82rem;
}
.vl-table tbody tr:hover {
    background: #f8fafc;
}

/* PAGINACIÓN PANTONE SONDA */
.sonda-pagination {
    display: flex;
    align-items: center;
    gap: 4px;
    margin: 0;
    padding: 0;
    list-style: none;
}
.sonda-page-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 32px;
    height: 32px;
    border-radius: 6px;
    padding: 0 10px;
    font-size: 0.82rem;
    font-weight: 700;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: var(--sonda-navy);
    cursor: pointer;
    transition: all 0.15s ease;
    user-select: none;
    margin: 0 2px;
}
.sonda-page-btn:hover:not(.disabled):not(.active) {
    background: #f1f5f9;
    border-color: var(--sonda-orange);
    color: var(--sonda-orange);
}
.sonda-page-btn.active {
    background: var(--sonda-orange) !important;
    border-color: var(--sonda-orange) !important;
    color: #ffffff !important;
    box-shadow: 0 2px 6px rgba(255, 92, 5, 0.35);
}
.sonda-page-btn.disabled {
    opacity: 0.4;
    cursor: not-allowed;
    background: #f8fafc;
    border-color: #e2e8f0;
}

/* SIMULADOR DE PANEL FRONTAL */
.switch-chassis {
    background: var(--sonda-navy);
    border: 1px solid #223554;
    border-radius: 12px;
    padding: 18px;
    box-shadow: inset 0 2px 10px rgba(0,0,0,0.5);
}
.port-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}
.port-cell {
    width: 38px;
    height: 30px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.65rem;
    font-weight: 800;
    color: #fff;
    cursor: default;
    transition: transform .15s, box-shadow .15s;
    border: 1px solid rgba(0,0,0,.2);
}
.port-cell.connected {
    background: var(--sonda-green);
    color: #101B31;
    cursor: pointer;
    box-shadow: 0 1px 3px rgba(192, 218, 32, 0.4);
}
.port-cell.connected:hover {
    transform: scale(1.2);
    box-shadow: 0 0 12px rgba(192, 218, 32, 0.8);
    z-index: 5;
}
.port-cell.free {
    background: #334155;
    color: #94a3b8;
}
.port-cell.damaged {
    background: #ef4444;
}
.port-detail-panel {
    background: linear-gradient(135deg, #f0fdf4, #ffffff);
    border: 1px solid #86efac;
    border-radius: 10px;
    padding: 18px;
    margin-top: 14px;
    animation: fadeIn .3s;
}

@keyframes fadeIn {
    from { opacity:0; transform: translateY(6px); }
    to { opacity:1; transform: translateY(0); }
}
</style>

<div class="container-fluid py-3">
    <!-- CONTENEDOR PRINCIPAL EJECUTIVO -->
    <div class="vl-main-shell">
        
        <!-- BANNER DE CABECERA PANTONE SONDA -->
        <div class="vl-executive-header d-flex justify-content-between align-items-center flex-wrap" style="gap: 14px;">
            <div>
                <div class="d-flex align-items-center mb-1 flex-wrap" style="gap: 8px;">
                    <span class="badge badge-pill px-2 py-1 font-weight-bold" style="background: var(--sonda-orange); color: #fff; font-size: 0.72rem; letter-spacing: 0.6px;">
                        <i class="fas fa-building mr-1"></i>VILASECA
                    </span>
                    <span class="badge badge-pill px-2 py-1 font-weight-bold" style="background: var(--sonda-cyan); color: #101B31; font-size: 0.72rem; letter-spacing: 0.6px;">
                        <i class="fas fa-network-wired mr-1"></i>SISTEMA CMDB SONDA
                    </span>
                    <span class="badge badge-pill px-2 py-1 font-weight-bold" style="background: var(--sonda-green); color: #101B31; font-size: 0.72rem;">
                        <i class="fas fa-check-circle mr-1"></i>EN VIVO
                    </span>
                </div>
                <h3 class="m-0 font-weight-bold text-white" style="letter-spacing: -0.3px;">
                    <i class="fas fa-chart-line mr-2" style="color: var(--sonda-orange);"></i>VILASECA - Dashboard (Manage)
                </h3>
                <p class="m-0 text-white-50 small mt-1" style="font-size: 0.85rem;">
                    Consola ejecutiva principal: análisis de capacidad, ocupación física de puertos, densidad de bastidores y monitoreo de infraestructura.
                </p>
            </div>
            
            <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
                <button type="button" class="btn btn-sm font-weight-bold text-white shadow-sm" style="background: var(--sonda-orange);" onclick="exportVilasecaCSV()">
                    <i class="fas fa-file-excel mr-1"></i> Exportar CSV
                </button>
                <a href="<?php echo PUBLIC_URL_PREFIX; ?>/portmapping.php?cliente=VILASECA" class="btn btn-outline-light btn-sm font-weight-bold shadow-sm">
                    <i class="fas fa-network-wired mr-1" style="color: var(--sonda-cyan);"></i> Portmapping
                </a>
                <a href="<?php echo PUBLIC_URL_PREFIX; ?>/analisis_conexiones.php?cliente=VILASECA" class="btn btn-outline-light btn-sm font-weight-bold shadow-sm">
                    <i class="fas fa-project-diagram mr-1" style="color: var(--sonda-green);"></i> Topología
                </a>
            </div>
        </div>

        <!-- CUERPO PRINCIPAL: ANÁLISIS DE INFRAESTRUCTURA (MANAGE) -->
        <div class="p-4">
                
                <!-- 🎯 FILTROS CONCATENADOS EN TIEMPO REAL (COLOCADOS PRIMERO) -->
                <div class="card border shadow-sm mb-4" style="border-radius: 12px; border-left: 5px solid var(--sonda-orange) !important; background: #ffffff;">
                    <div class="card-body p-3 bg-light" style="border-radius: 11px;">
                        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap" style="gap: 8px;">
                            <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
                                <span class="badge badge-pill text-white px-2 py-1 font-weight-bold" style="background: var(--sonda-navy); font-size: 0.72rem; letter-spacing: 0.5px;">
                                    <i class="fas fa-sliders-h mr-1" style="color: var(--sonda-orange);"></i>FILTROS CONCATENADOS
                                </span>
                                <small class="text-muted font-weight-bold">Filtros inteligentes en cascada: la selección de cualquier criterio actualiza KPIs, gráficos, racks y tablas en tiempo real.</small>
                                <span class="badge badge-pill badge-warning text-dark font-weight-bold ml-1" id="vl-active-filters-count" style="display:none; font-size: 0.72rem;">0 activos</span>
                            </div>
                            <div>
                                <button type="button" class="btn btn-xs btn-outline-danger font-weight-bold px-2 py-1 shadow-xs" id="vl-btn-reset-filters" onclick="resetAllVilasecaFilters()">
                                    <i class="fas fa-undo-alt mr-1"></i> Limpiar Filtros
                                </button>
                            </div>
                        </div>
                        <div class="row align-items-center" style="row-gap: 10px;">
                            <!-- 1. SEDE / LOCALIDAD -->
                            <div class="col-md-3 col-sm-6">
                                <label class="small font-weight-bold text-dark mb-1">
                                    <i class="fas fa-map-marker-alt text-danger mr-1"></i> 1. Sede / Localidad:
                                </label>
                                <select id="vl-filter-location" class="custom-select custom-select-sm font-weight-bold border-secondary">
                                    <option value="all">Todas las Sedes (<?php echo $vl_locations_count; ?>)</option>
                                    <?php foreach ($vl_locations as $loc_name => $loc_data): ?>
                                        <option value="<?php echo htmlspecialchars($loc_name); ?>"><?php echo htmlspecialchars($loc_name); ?> (<?php echo $loc_data['devices']; ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- 2. ÁREA -->
                            <div class="col-md-2 col-sm-6">
                                <label class="small font-weight-bold text-dark mb-1">
                                    <i class="fas fa-vector-square text-info mr-1"></i> 2. Área:
                                </label>
                                <select id="vl-filter-area" class="custom-select custom-select-sm font-weight-bold border-secondary">
                                    <option value="all">Todas las Áreas (<?php echo count($vl_areas); ?>)</option>
                                    <?php foreach ($vl_areas as $ar_name): ?>
                                        <option value="<?php echo htmlspecialchars($ar_name); ?>"><?php echo htmlspecialchars($ar_name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- 3. RACK / BASTIDOR -->
                            <div class="col-md-2 col-sm-6">
                                <label class="small font-weight-bold text-dark mb-1">
                                    <i class="fas fa-cube text-warning mr-1"></i> 3. Rack / Bastidor:
                                </label>
                                <select id="vl-filter-rack" class="custom-select custom-select-sm font-weight-bold border-secondary">
                                    <option value="all">Todos los Racks (<?php echo count($vl_racks_list); ?>)</option>
                                    <?php foreach ($vl_racks_list as $rk_name): ?>
                                        <option value="<?php echo htmlspecialchars($rk_name); ?>"><?php echo htmlspecialchars($rk_name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- 4. TIPO DE EQUIPAMIENTO -->
                            <div class="col-md-2 col-sm-6">
                                <label class="small font-weight-bold text-dark mb-1">
                                    <i class="fas fa-layer-group text-primary mr-1"></i> 4. Tipo de Equipo:
                                </label>
                                <select id="vl-filter-type" class="custom-select custom-select-sm font-weight-bold border-secondary">
                                    <option value="all">Todos los Tipos (<?php echo count($vl_types); ?>)</option>
                                    <?php foreach ($vl_types as $t_name => $t_count): ?>
                                        <option value="<?php echo htmlspecialchars($t_name); ?>"><?php echo htmlspecialchars($t_name); ?> (<?php echo $t_count; ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- 5. BÚSQUEDA RÁPIDA -->
                            <div class="col-md-3 col-sm-12">
                                <label class="small font-weight-bold text-dark mb-1">
                                    <i class="fas fa-search text-secondary mr-1"></i> 5. Búsqueda Rápida:
                                </label>
                                <div class="input-group input-group-sm">
                                    <input type="text" id="vl-filter-search" class="form-control font-weight-bold border-secondary" placeholder="Buscar switch, IP, modelo...">
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary" type="button" onclick="$('#vl-filter-search').val('').trigger('input');" title="Limpiar texto">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 5 TARJETAS KPI PANTONE SONDA (ACTUALIZADAS DINÁMICAMENTE) -->
                <div class="row mb-4" style="row-gap: 12px;">
                    <div class="col-6 col-md-4 col-lg">
                        <div class="stat-kpi-card kpi-orange">
                            <div class="stat-kpi-label"><i class="fas fa-server mr-1" style="color: var(--sonda-orange);"></i>Equipos Relevados</div>
                            <div class="stat-kpi-num" id="kpi-devices-count"><?php echo $vl_surveys_count; ?></div>
                            <div class="small text-muted mt-1" id="kpi-devices-sub">Switches, Routers, Patch</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg">
                        <div class="stat-kpi-card kpi-cyan">
                            <div class="stat-kpi-label"><i class="fas fa-ethernet mr-1" style="color: var(--sonda-cyan);"></i>Puertos Físicos</div>
                            <div class="stat-kpi-num" id="kpi-ports-total"><?php echo number_format($vl_total_ports); ?></div>
                            <div class="small text-muted mt-1" id="kpi-ports-sub">Capacidad relevada</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg">
                        <div class="stat-kpi-card kpi-green">
                            <div class="stat-kpi-label"><i class="fas fa-plug mr-1" style="color: #65a30d;"></i>Ocupación Puertos</div>
                            <div class="stat-kpi-num" id="kpi-occupancy-rate" style="color: #65a30d;"><?php echo $vl_occupancy_rate; ?>%</div>
                            <div class="small text-muted mt-1" id="kpi-occupancy-sub"><?php echo number_format($vl_occupied_ports); ?> usados / <?php echo number_format($vl_vacant_ports); ?> libres</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-6 col-lg">
                        <div class="stat-kpi-card kpi-navy">
                            <div class="stat-kpi-label"><i class="fas fa-map-marker-alt mr-1" style="color: var(--sonda-navy);"></i>Sedes / Localidades</div>
                            <div class="stat-kpi-num" id="kpi-locations-count"><?php echo $vl_locations_count; ?></div>
                            <div class="small text-muted mt-1" id="kpi-locations-sub">Infraestructura distribuida</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg">
                        <div class="stat-kpi-card kpi-orange">
                            <div class="stat-kpi-label"><i class="fas fa-cubes mr-1" style="color: var(--sonda-orange);"></i>Racks Datacenter</div>
                            <div class="stat-kpi-num" id="kpi-racks-count" style="color: var(--sonda-orange);"><?php echo $vl_racks_count; ?></div>
                            <div class="small text-muted mt-1" id="kpi-racks-sub"><?php echo $vl_rooms_count; ?> Salas / <?php echo $vl_total_u; ?> U Totales</div>
                        </div>
                    </div>
                </div>

                <!-- GRÁFICOS EJECUTIVOS EN PANTONE SONDA -->
                <div class="row mb-4">
                    <!-- DISTRIBUCIÓN POR TIPO -->
                    <div class="col-lg-6 mb-3">
                        <div class="card border-0 shadow-sm" style="border-radius: 12px; height: 100%;">
                            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap" style="gap: 8px;">
                                <h6 class="font-weight-bold text-dark m-0">
                                    <i class="fas fa-chart-pie mr-2" style="color: var(--sonda-orange);"></i>Distribución de Equipos por Tipo
                                </h6>
                                <span class="badge badge-pill badge-light border text-muted font-weight-bold" id="vl-chart-types-badge"><?php echo count($vl_all_devices); ?> Equipos</span>
                            </div>
                            <div class="card-body p-3">
                                <div class="row align-items-center">
                                    <div class="col-sm-6 mb-3 mb-sm-0">
                                        <div style="height: 230px; position: relative;">
                                            <canvas id="vl-chart-types"></canvas>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="vl-types-legend-list pl-sm-2" id="vl-types-legend-list">
                                            <?php 
                                            $sonda_palette = ['#ff5c05', '#00B8D4', '#101B31', '#c0da20', '#2a4365', '#e04e04', '#00838f', '#64748b'];
                                            $c_idx = 0;
                                            $tot_devs = count($vl_all_devices);
                                            foreach ($vl_types as $t_name => $t_count): 
                                                $col = $sonda_palette[$c_idx % count($sonda_palette)];
                                                $pct = $tot_devs > 0 ? round(($t_count / $tot_devs) * 100) : 0;
                                                $c_idx++;
                                            ?>
                                            <div class="mb-2">
                                                <div class="d-flex justify-content-between align-items-center small font-weight-bold mb-1">
                                                    <span class="text-truncate mr-1"><span style="display:inline-block;width:9px;height:9px;border-radius:2px;background:<?php echo $col; ?>;margin-right:6px;"></span><?php echo htmlspecialchars($t_name); ?></span>
                                                    <span class="text-dark whitespace-nowrap"><strong><?php echo $t_count; ?></strong> <small class="text-muted">(<?php echo $pct; ?>%)</small></span>
                                                </div>
                                                <div class="progress" style="height: 6px; border-radius: 3px; background: #e2e8f0;">
                                                    <div class="progress-bar" style="width: <?php echo $pct; ?>%; background: <?php echo $col; ?>;"></div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- EQUIPOS Y PUERTOS POR SEDE -->
                    <div class="col-lg-6 mb-3">
                        <div class="card border-0 shadow-sm" style="border-radius: 12px; height: 100%;">
                            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap" style="gap: 8px;">
                                <h6 class="font-weight-bold text-dark m-0">
                                    <i class="fas fa-chart-bar mr-2" style="color: var(--sonda-cyan);"></i>Equipos y Puertos por Sede
                                </h6>
                                <span class="badge badge-pill badge-light border text-muted font-weight-bold" id="vl-chart-locations-badge"><?php echo $vl_locations_count; ?> Sedes</span>
                            </div>
                            <div class="card-body p-3">
                                <div class="row align-items-center">
                                    <div class="col-sm-6 mb-3 mb-sm-0">
                                        <div style="height: 230px; position: relative;">
                                            <canvas id="vl-chart-locations"></canvas>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="vl-locs-legend-list pl-sm-2" id="vl-locs-legend-list" style="max-height: 240px; overflow-y: auto;">
                                            <?php foreach ($vl_locations as $loc_name => $loc_data): 
                                                $occ_rate = $loc_data['ports'] > 0 ? round(($loc_data['occupied'] / $loc_data['ports']) * 100) : 0;
                                            ?>
                                            <div class="p-2 mb-2 rounded bg-light border">
                                                <div class="d-flex justify-content-between align-items-center small font-weight-bold mb-1">
                                                    <span class="text-dark text-truncate mr-1"><i class="fas fa-map-marker-alt text-danger mr-1"></i><?php echo htmlspecialchars($loc_name); ?></span>
                                                    <span class="badge badge-pill text-white px-2" style="background:var(--sonda-orange);"><?php echo $loc_data['devices']; ?> eq</span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center" style="font-size: 0.75rem;">
                                                    <span class="text-muted"><i class="fas fa-ethernet mr-1" style="color:var(--sonda-cyan);"></i><?php echo $loc_data['ports']; ?> puertos</span>
                                                    <span class="font-weight-bold" style="color: #65a30d;"><?php echo $loc_data['vacant']; ?> libres (<?php echo 100 - $occ_rate; ?>%)</span>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- LEADERBOARDS: TOP VACÍOS Y TOP LLENOS -->
                <div class="row mb-4">
                    <!-- TOP PUERTOS VACÍOS (DISPONIBILIDAD) -->
                    <div class="col-lg-6 mb-3">
                        <div class="card border-0 shadow-sm" style="border-radius: 12px; height: 100%;">
                            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                                <h6 class="font-weight-bold m-0 text-dark">
                                    <i class="fas fa-check-circle mr-2" style="color: var(--sonda-green);"></i>Top Equipos con Mayor Disponibilidad (Puertos Vacíos)
                                </h6>
                                <span class="badge badge-pill badge-light border text-success font-weight-bold">Mayor Capacidad Libre</span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover table-sm mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th style="width: 40px;" class="text-center">#</th>
                                            <th>Equipo</th>
                                            <th>Sede / Ubicación</th>
                                            <th class="text-center">Total</th>
                                            <th class="text-center font-weight-bold text-success">Vacíos</th>
                                            <th style="width: 110px;">Disponibilidad</th>
                                        </tr>
                                    </thead>
                                    <tbody id="vl-top-vacant-tbody">
                                        <?php foreach ($top_vacant as $idx => $tv): 
                                            $rankCls = $idx === 0 ? 'rank-gold' : ($idx === 1 ? 'rank-silver' : ($idx === 2 ? 'rank-bronze' : 'rank-normal'));
                                            $tot = (int)($tv['total'] ?? $tv['ports_count'] ?? 0);
                                            $vac = (int)($tv['vacant'] ?? 0);
                                            $vacPct = $tot > 0 ? round(($vac / $tot) * 100) : 0;
                                        ?>
                                        <tr>
                                            <td class="text-center"><span class="rank-badge-pill <?php echo $rankCls; ?>"><?php echo $idx + 1; ?></span></td>
                                            <td class="font-weight-bold text-dark"><?php echo htmlspecialchars($tv['device_name']); ?></td>
                                            <td><small class="text-muted"><i class="fas fa-map-marker-alt text-danger mr-1"></i><?php echo htmlspecialchars($tv['location']); ?></small></td>
                                            <td class="text-center"><span class="badge badge-light border"><?php echo $tot; ?></span></td>
                                            <td class="text-center font-weight-bold" style="color: #65a30d;"><?php echo $vac; ?></td>
                                            <td>
                                                <div class="progress" style="height: 7px; border-radius: 4px;">
                                                    <div class="progress-bar" style="width: <?php echo $vacPct; ?>%; background: var(--sonda-green);"></div>
                                                </div>
                                                <small class="text-muted font-weight-bold" style="font-size: 0.68rem;"><?php echo $vacPct; ?>% libre</small>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- TOP PUERTOS LLENOS (MAYOR OCUPACIÓN) -->
                    <div class="col-lg-6 mb-3">
                        <div class="card border-0 shadow-sm" style="border-radius: 12px; height: 100%;">
                            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                                <h6 class="font-weight-bold m-0 text-dark">
                                    <i class="fas fa-fire mr-2" style="color: var(--sonda-orange);"></i>Top Equipos con Mayor Ocupación (Puertos Llenos)
                                </h6>
                                <span class="badge badge-pill badge-light border text-danger font-weight-bold">Mayor Demanda</span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover table-sm mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th style="width: 40px;" class="text-center">#</th>
                                            <th>Equipo</th>
                                            <th>Sede / Ubicación</th>
                                            <th class="text-center">Total</th>
                                            <th class="text-center font-weight-bold text-danger">Ocupados</th>
                                            <th style="width: 110px;">Ocupación</th>
                                        </tr>
                                    </thead>
                                    <tbody id="vl-top-occupied-tbody">
                                        <?php foreach ($top_occupied as $idx => $to): 
                                            $rankCls = $idx === 0 ? 'rank-gold' : ($idx === 1 ? 'rank-silver' : ($idx === 2 ? 'rank-bronze' : 'rank-normal'));
                                            $tot = (int)($to['total'] ?? $to['ports_count'] ?? 0);
                                            $occ = (int)($to['occupied'] ?? 0);
                                            $occPct = (float)($to['rate'] ?? ($tot > 0 ? round(($occ / $tot) * 100, 1) : 0));
                                        ?>
                                        <tr>
                                            <td class="text-center"><span class="rank-badge-pill <?php echo $rankCls; ?>"><?php echo $idx + 1; ?></span></td>
                                            <td class="font-weight-bold text-dark"><?php echo htmlspecialchars($to['device_name']); ?></td>
                                            <td><small class="text-muted"><i class="fas fa-map-marker-alt text-danger mr-1"></i><?php echo htmlspecialchars($to['location']); ?></small></td>
                                            <td class="text-center"><span class="badge badge-light border"><?php echo $tot; ?></span></td>
                                            <td class="text-center font-weight-bold text-danger"><?php echo $occ; ?></td>
                                            <td>
                                                <div class="progress" style="height: 7px; border-radius: 4px;">
                                                    <div class="progress-bar" style="width: <?php echo $occPct; ?>%; background: var(--sonda-orange);"></div>
                                                </div>
                                                <small class="text-muted font-weight-bold" style="font-size: 0.68rem;"><?php echo $occPct; ?>% ocupado</small>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RACKS CON MAYOR CANTIDAD DE EQUIPOS POR LOCALIDAD -->
                <div class="card border-0 shadow-sm mb-4" style="border-radius: 12px; overflow: hidden;">
                    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap" style="gap: 10px;">
                        <div>
                            <h6 class="font-weight-bold text-dark m-0">
                                <i class="fas fa-cubes mr-2" style="color: var(--sonda-orange);"></i>Racks con Mayor Cantidad de Equipos por Localidad
                            </h6>
                            <small class="text-muted">Distribución de bastidores y concentración de hardware por sede.</small>
                        </div>
                        <div class="d-flex align-items-center flex-wrap" id="vl-racks-pills-bar" style="gap: 6px;">
                            <button type="button" class="pill-loc-btn active" onclick="onRackPillClick('all', this)">Todas las Sedes</button>
                            <?php foreach ($vl_locations as $loc_name => $loc_data): ?>
                                <button type="button" class="pill-loc-btn" onclick="onRackPillClick('<?php echo htmlspecialchars($loc_name); ?>', this)"><?php echo htmlspecialchars($loc_name); ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="card-body p-4 bg-light">
                        <div class="row" id="vl-racks-grid" style="row-gap: 16px;">
                            <?php foreach ($vl_racks_agg as $rk): ?>
                                <div class="col-md-4 col-lg-3 rack-item-col" data-loc="<?php echo htmlspecialchars($rk['location']); ?>">
                                    <div class="card border shadow-xs h-100 p-3" style="border-radius: 10px; background: #ffffff;">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <span class="badge badge-light border text-dark font-weight-bold"><i class="fas fa-server mr-1" style="color: var(--sonda-orange);"></i><?php echo htmlspecialchars($rk['rack_name']); ?></span>
                                            <span class="badge badge-pill badge-info" style="background: var(--sonda-cyan); color: #101B31;"><?php echo $rk['devices_count']; ?> equipos</span>
                                        </div>
                                        <div class="small text-muted mb-2">
                                            <i class="fas fa-map-marker-alt text-danger mr-1"></i><?php echo htmlspecialchars($rk['location']); ?>
                                        </div>
                                        <div class="mt-auto pt-2 border-top d-flex justify-content-between align-items-center">
                                            <span class="small font-weight-bold text-dark"><?php echo $rk['ports_count']; ?> puertos</span>
                                            <a href="<?php echo PUBLIC_URL_PREFIX; ?>/datacenter/racks.php?cliente=VILASECA" class="btn btn-xs btn-outline-secondary font-weight-bold">
                                                <i class="fas fa-eye mr-1"></i>Ver Rack
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- INVENTARIO ANALÍTICO DETALLADO CON PAGINACIÓN Y FILTRO DE BÚSQUEDA -->
                <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap" style="gap: 10px;">
                        <h6 class="font-weight-bold text-dark m-0">
                            <i class="fas fa-list mr-2" style="color: var(--sonda-navy);"></i>Inventario Analítico de Equipos y Capacidad de Puertos
                        </h6>
<?php 
$vl_total_devs = count($vl_all_devices);
$vl_initial_page_size = 10;
$vl_total_pages = $vl_total_devs > 0 ? (int)ceil($vl_total_devs / $vl_initial_page_size) : 1;
?>
                        <span class="badge badge-light border font-weight-bold text-muted" id="vl-table-count"><?php echo $vl_total_devs; ?> Equipos Listados</span>
                    </div>

                    <!-- BARRA DE HERRAMIENTAS: PAGINACIÓN SUPERIOR Y BÚSQUEDA DEDICADA -->
                    <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center flex-wrap" style="gap: 12px;">
                        <div class="d-flex align-items-center flex-wrap" style="gap: 12px;">
                            <div class="d-flex align-items-center" style="gap: 6px;">
                                <label class="small text-muted font-weight-bold mb-0">Mostrar:</label>
                                <select id="vl-inv-page-size" class="custom-select custom-select-sm font-weight-bold" style="width: auto;">
                                    <option value="10" selected>10 por página</option>
                                    <option value="25">25 por página</option>
                                    <option value="50">50 por página</option>
                                    <option value="-1">Todos los registros</option>
                                </select>
                            </div>

                            <!-- Paginador Superior Inmediatamente Visible -->
                            <div id="vl-inv-pagination-top" class="vl-inv-pagination-target d-flex align-items-center flex-wrap" style="gap: 3px;">
                                <button type="button" class="sonda-page-btn disabled" data-page="1" title="Primera página"><i class="fas fa-angle-double-left"></i></button>
                                <button type="button" class="sonda-page-btn disabled" data-page="1" title="Página anterior"><i class="fas fa-chevron-left"></i></button>
                                <?php for ($p = 1; $p <= min(5, $vl_total_pages); $p++): ?>
                                    <button type="button" class="sonda-page-btn <?php echo $p === 1 ? 'active' : ''; ?>" data-page="<?php echo $p; ?>"><?php echo $p; ?></button>
                                <?php endfor; ?>
                                <?php if ($vl_total_pages > 5): ?>
                                    <span class="px-1 text-muted font-weight-bold" style="user-select:none; line-height:30px;">...</span>
                                    <button type="button" class="sonda-page-btn" data-page="<?php echo $vl_total_pages; ?>"><?php echo $vl_total_pages; ?></button>
                                <?php endif; ?>
                                <button type="button" class="sonda-page-btn <?php echo $vl_total_pages <= 1 ? 'disabled' : ''; ?>" data-page="<?php echo min(2, $vl_total_pages); ?>" title="Página siguiente"><i class="fas fa-chevron-right"></i></button>
                                <button type="button" class="sonda-page-btn <?php echo $vl_total_pages <= 1 ? 'disabled' : ''; ?>" data-page="<?php echo $vl_total_pages; ?>" title="Última página"><i class="fas fa-angle-double-right"></i></button>
                            </div>
                        </div>

                        <div class="input-group input-group-sm" style="max-width: 320px;">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-white border-right-0"><i class="fas fa-search text-muted"></i></span>
                            </div>
                            <input type="text" id="vl-inv-table-search" class="form-control border-left-0 font-weight-bold" placeholder="Buscar en la tabla (equipo, sede, rack, IP)...">
                            <div class="input-group-append">
                                <button class="btn btn-outline-secondary" type="button" title="Limpiar búsqueda" onclick="$('#vl-inv-table-search').val('').trigger('input');">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table vl-table mb-0" id="vl-inventory-table">
                            <thead>
                                <tr>
                                    <th>Equipo</th>
                                    <th>Sede / Ubicación</th>
                                    <th>Rack</th>
                                    <th>Tipo</th>
                                    <th class="text-center">Puertos Totales</th>
                                    <th class="text-center">Conectados</th>
                                    <th class="text-center">Vacíos</th>
                                    <th class="text-center">% Ocupación</th>
                                    <th class="text-right">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vl_all_devices as $dev_idx => $dev): 
                                    $badgeRateCls = $dev['rate'] >= 75 ? 'badge-danger' : ($dev['rate'] >= 40 ? 'badge-warning' : 'badge-success');
                                    $isInitVisible = ($dev_idx < 10);
                                ?>
                                <tr style="<?php echo $isInitVisible ? '' : 'display: none;'; ?>" data-id="<?php echo $dev['id']; ?>" data-loc="<?php echo htmlspecialchars($dev['location']); ?>" data-area="<?php echo htmlspecialchars($dev['area'] ?? 'General'); ?>" data-type="<?php echo htmlspecialchars($dev['device_type']); ?>" data-name="<?php echo htmlspecialchars($dev['device_name']); ?>" data-ip="<?php echo htmlspecialchars($dev['ip_address']); ?>" data-rack="<?php echo htmlspecialchars($dev['rack']); ?>">
                                    <td class="font-weight-bold text-dark">
                                        <i class="fas fa-server mr-2" style="color: var(--sonda-orange);"></i><?php echo htmlspecialchars($dev['device_name']); ?>
                                        <small class="d-block text-muted">IP: <code><?php echo htmlspecialchars($dev['ip_address']); ?></code></small>
                                    </td>
                                    <td><i class="fas fa-map-marker-alt text-danger mr-1 small"></i><?php echo htmlspecialchars($dev['location']); ?></td>
                                    <td><span class="badge badge-light border font-weight-bold"><?php echo htmlspecialchars($dev['rack']); ?></span></td>
                                    <td><span class="badge badge-info" style="background: var(--sonda-cyan); color: #101B31;"><?php echo htmlspecialchars($dev['device_type']); ?></span></td>
                                    <td class="text-center font-weight-bold"><?php echo $dev['ports_count']; ?></td>
                                    <td class="text-center font-weight-bold text-danger"><?php echo $dev['occupied']; ?></td>
                                    <td class="text-center font-weight-bold" style="color: #65a30d;"><?php echo $dev['vacant']; ?></td>
                                    <td class="text-center"><span class="badge <?php echo $badgeRateCls; ?> font-weight-bold"><?php echo $dev['rate']; ?>%</span></td>
                                    <td class="text-right">
                                        <a href="<?php echo PUBLIC_URL_PREFIX; ?>/portmapping.php?cliente=VILASECA" class="btn btn-xs btn-outline-primary font-weight-bold" title="Abrir en Portmapping">
                                            <i class="fas fa-network-wired mr-1"></i>Ver Mapeo
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- PIE DE TABLA CON INFORMACIÓN Y PAGINADOR INFERIOR -->
                    <div class="card-footer bg-white border-top py-2 px-3 d-flex justify-content-between align-items-center flex-wrap" style="gap: 10px;">
                        <div id="vl-inv-table-info" class="vl-inv-table-info-target small text-muted font-weight-bold">
                            Mostrando <strong>1</strong> a <strong><?php echo min(10, $vl_total_devs); ?></strong> de <strong><?php echo $vl_total_devs; ?></strong> registros
                        </div>
                        <div id="vl-inv-pagination" class="vl-inv-pagination-target d-flex align-items-center flex-wrap" style="gap: 3px;">
                            <button type="button" class="sonda-page-btn disabled" data-page="1" title="Primera página"><i class="fas fa-angle-double-left"></i></button>
                            <button type="button" class="sonda-page-btn disabled" data-page="1" title="Página anterior"><i class="fas fa-chevron-left"></i></button>
                            <?php for ($p = 1; $p <= min(5, $vl_total_pages); $p++): ?>
                                <button type="button" class="sonda-page-btn <?php echo $p === 1 ? 'active' : ''; ?>" data-page="<?php echo $p; ?>"><?php echo $p; ?></button>
                            <?php endfor; ?>
                            <?php if ($vl_total_pages > 5): ?>
                                <span class="px-1 text-muted font-weight-bold" style="user-select:none; line-height:30px;">...</span>
                                <button type="button" class="sonda-page-btn" data-page="<?php echo $vl_total_pages; ?>"><?php echo $vl_total_pages; ?></button>
                            <?php endif; ?>
                            <button type="button" class="sonda-page-btn <?php echo $vl_total_pages <= 1 ? 'disabled' : ''; ?>" data-page="<?php echo min(2, $vl_total_pages); ?>" title="Página siguiente"><i class="fas fa-chevron-right"></i></button>
                            <button type="button" class="sonda-page-btn <?php echo $vl_total_pages <= 1 ? 'disabled' : ''; ?>" data-page="<?php echo $vl_total_pages; ?>" title="Última página"><i class="fas fa-angle-double-right"></i></button>
                        </div>
                    </div>
                </div>

        </div><!-- /p-4 -->
    </div><!-- /vl-main-shell -->
</div>

<script>
// Datos del servidor para gráficos ejecutivos y filtros (Pantone Sonda)
const VL_SERVER_DATA = {
    types: <?php echo json_encode($vl_types, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
    locations: <?php echo json_encode($vl_locations, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
    areas: <?php echo json_encode($vl_areas, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
    racks: <?php echo json_encode($vl_racks_list, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
    all_devices: <?php echo json_encode($vl_all_devices, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
};
</script>
<script src="<?php echo PUBLIC_URL_PREFIX; ?>/clientes/vilaseca/app.js?v=<?php echo filemtime(__DIR__ . '/app.js'); ?>"></script>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>

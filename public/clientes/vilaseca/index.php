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
$vl_surveys_stmt = $pdo->prepare("SELECT id, client, location, area, device_name, device_label, device_type, vendor, model, serial, ports_count, rack, ur_rack, ip_address, ports_data_json, created_at FROM manual_portmap_surveys WHERE UPPER(client) LIKE '%VILASECA%' ORDER BY location ASC, rack ASC, device_name ASC");
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
$vl_brands = [];
$vl_models = [];
$vl_vlans = [];
$vl_vlan_port_counts = [];

foreach ($vl_surveys_list as $s) {
    $dev_type = mb_strtoupper(trim($s['device_type'] ?: 'SWITCH'), 'UTF-8');
    if ($dev_type === '') {
        $dev_type = 'SWITCH';
    }
    $loc = trim($s['location'] ?: 'General');
    $ar = trim($s['area'] ?: 'General');
    $rk = trim($s['rack'] ?: 'Sin Rack');

    // Normalización de Marca / Vendor
    $raw_brand = trim((string)($s['vendor'] ?? ''));
    $dev_brand = mb_strtoupper($raw_brand, 'UTF-8');
    if ($dev_brand === '' || $dev_brand === 'N/A' || $dev_brand === '-' || $dev_brand === 'NULL' || $dev_brand === 'NINGUNO' || $dev_brand === 'SIN MARCA') {
        $dev_brand = 'SIN MARCA';
    }

    // Normalización de Modelo / Model
    $raw_model = trim((string)($s['model'] ?? ''));
    $dev_model = mb_strtoupper($raw_model, 'UTF-8');
    if ($dev_model === '' || $dev_model === 'N/A' || $dev_model === '-' || $dev_model === 'NULL' || $dev_model === 'NINGUNO' || $dev_model === 'SIN MODELO') {
        $dev_model = 'SIN MODELO';
    }
    
    $vl_types[$dev_type] = ($vl_types[$dev_type] ?? 0) + 1;
    $vl_brands[$dev_brand] = ($vl_brands[$dev_brand] ?? 0) + 1;
    $vl_models[$dev_model] = ($vl_models[$dev_model] ?? 0) + 1;

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
    $dev_vlans_set = [];
    $dev_ports_with_vlan = 0;

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

            // Detección de VLANs por puerto
            $v_raw = trim((string)($p['vlan'] ?? ''));
            if ($v_raw !== '' && $v_raw !== '0' && strtoupper($v_raw) !== 'N/A' && $v_raw !== '-') {
                $parts = preg_split('/[,;\/\s]+/', $v_raw);
                $has_port_vlan = false;
                foreach ($parts as $vp) {
                    $vp = trim($vp);
                    if ($vp !== '' && $vp !== '0' && is_numeric($vp)) {
                        $v_lbl = 'VLAN ' . $vp;
                        $dev_vlans_set[$v_lbl] = true;
                        $vl_vlan_port_counts[$v_lbl] = ($vl_vlan_port_counts[$v_lbl] ?? 0) + 1;
                        $has_port_vlan = true;
                    }
                }
                if ($has_port_vlan) {
                    $dev_ports_with_vlan++;
                }
            }
        }
    } else {
        $d_vac = $d_total;
    }

    // Clasificación de VLAN a nivel de equipo
    $dev_vlan_list = array_keys($dev_vlans_set);
    if (empty($dev_vlan_list)) {
        $dev_vlan_list = ['SIN VLAN'];
        $vl_vlans['SIN VLAN'] = ($vl_vlans['SIN VLAN'] ?? 0) + 1;
        $vl_vlan_port_counts['SIN VLAN'] = ($vl_vlan_port_counts['SIN VLAN'] ?? 0) + $d_total;
    } else {
        foreach ($dev_vlan_list as $vl_lbl) {
            $vl_vlans[$vl_lbl] = ($vl_vlans[$vl_lbl] ?? 0) + 1;
        }
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
        'vendor' => $dev_brand,
        'model' => $dev_model,
        'vlans' => $dev_vlan_list,
        'ports_with_vlan' => $dev_ports_with_vlan,
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
arsort($vl_brands);
arsort($vl_models);
arsort($vl_vlans);

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

$page_title = "VILASECA - Centro de Infraestructura";
$hide_content_header = true;
require_once __DIR__ . '/../../partials/header.php';
?>
<script src="<?php echo PUBLIC_URL_PREFIX; ?>/clientes/vilaseca/chart.umd.min.js?v=<?php echo filemtime(__DIR__ . '/chart.umd.min.js'); ?>"></script>
<script>
if (typeof Chart === 'undefined') {
    document.write('<script src="https://cdn.jsdelivr.net/npm/chart.js"><\/script>');
}
</script>

<style>
/* PALETA SOBRIA Y FUNCIONAL (ANTI-FATIGA VISUAL PARA PROYECTISTAS E INGENIEROS) */
:root {
    --bg-canvas: #F8FAFC;        /* Slate 50: Lienzo principal */
    --bg-surface: #FFFFFF;       /* Blanco Puro: Tarjetas, formularios y paneles */
    --color-accent: #1E40AF;     /* Azul Cobalto Técnico: Botones primarios, estados activos */
    --border-grid: #CBD5E1;      /* Gris Neutro: División, baldosas de 60x60 cm */
    --status-metric: #0F766E;    /* Verde Azulado: Semáforo de validación TIA-942 */

    /* Regla Tipográfica Estricta - Modo Claro (WCAG AAA) */
    --text-color: #0F172A;       /* Negro profundo con contraste WCAG AAA */
    --text-muted: #334155;       /* Slate oscuro de alto contraste */

    --sonda-border: var(--border-grid);
    --sonda-navy: #0F172A;
    --sonda-orange: var(--color-accent);
    --sonda-cyan: #0284C7;
    --sonda-green: var(--status-metric);
}

body.dark-mode {
    --bg-canvas: #0B1120;        /* Obsidian Deep: Lienzo principal */
    --bg-surface: #1E293B;       /* Slate 800: Tarjetas, formularios y paneles */
    --color-accent: #38BDF8;     /* Sky Blue: Botones primarios, estados activos */
    --border-grid: #334155;      /* Slate 700: División, baldosas de 60x60 cm */
    --status-metric: #14B8A6;    /* Teal Calidad: Semáforo de validación TIA-942 */

    /* Regla Tipográfica Estricta - Modo Oscuro */
    --text-color: #F8FAFC;       /* Blanco nítido */
    --text-muted: #94A3B8;       /* Slate claro */

    --sonda-border: var(--border-grid);
    --sonda-navy: #0B1120;
    --sonda-orange: var(--color-accent);
    --sonda-cyan: #38BDF8;
    --sonda-green: var(--status-metric);
}

/* CONTENEDOR PRINCIPAL */
.vl-main-shell {
    border-radius: 12px;
    border: 1px solid var(--border-grid);
    box-shadow: 0 4px 18px rgba(15, 23, 42, 0.05);
    background: var(--bg-surface);
    overflow: hidden;
    margin-top: 10px;
    margin-bottom: 30px;
}

/* CABECERA EJECUTIVA SOBRIA */
.vl-executive-header {
    background: linear-gradient(135deg, #0B1120 0%, #0F172A 60%, #1E293B 100%);
    color: #F8FAFC;
    padding: 20px 24px;
    border-left: 5px solid var(--color-accent);
}

.vl-header-tabs-bar {
    background: #0B1120;
    padding: 10px 16px 0 16px;
    border-top: 1px solid #1E293B;
}

/* PESTAÑAS TÉCNICAS */
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
    color: #CBD5E1;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-bottom: none;
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
    padding: 11px 14px;
    font-size: 0.85rem;
    font-weight: 600;
    background-color: rgba(255, 255, 255, 0.04);
    transition: all 0.2s ease-in-out;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    white-space: nowrap;
}

.vl-nav-tabs .nav-link:hover {
    color: #FFFFFF;
    background-color: rgba(255, 255, 255, 0.08);
    border-color: rgba(255, 255, 255, 0.2);
}

.vl-nav-tabs .nav-link.active {
    color: var(--text-color) !important;
    background-color: var(--bg-surface) !important;
    border-color: var(--border-grid) !important;
    font-weight: 700 !important;
    box-shadow: 0 -3px 10px rgba(0, 0, 0, 0.08);
    position: relative;
}

.vl-nav-tabs .nav-link.active::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--color-accent);
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
}

/* TARJETAS KPI SOBRIAS */
.stat-kpi-card {
    border-radius: 10px;
    border: 1px solid var(--border-grid);
    background: var(--bg-surface);
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
    padding: 15px 18px;
    transition: all 0.2s ease-in-out;
    position: relative;
    overflow: hidden;
    height: 100%;
}

.stat-kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(15, 23, 42, 0.08);
}

.stat-kpi-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
}

.stat-kpi-card.kpi-orange::before,
.stat-kpi-card.kpi-blue::before { background: var(--color-accent); }
.stat-kpi-card.kpi-cyan::before { background: #0284C7; }
.stat-kpi-card.kpi-green::before { background: var(--status-metric); }
.stat-kpi-card.kpi-navy::before { background: #334155; }

.stat-kpi-num {
    font-size: 1.85rem;
    font-weight: 800;
    line-height: 1.1;
    color: var(--text-color);
}

.stat-kpi-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted);
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
.rank-gold { background: #F59E0B; color: #FFFFFF; }
.rank-silver { background: #94A3B8; color: #FFFFFF; }
.rank-bronze { background: #D97706; color: #FFFFFF; }
.rank-normal { background: var(--bg-canvas); color: var(--text-muted); border: 1px solid var(--border-grid); }

/* PASTILLAS DE SEDE */
.pill-loc-btn {
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 700;
    padding: 5px 14px;
    transition: all 0.2s;
    cursor: pointer;
    border: 1px solid var(--border-grid);
    background: var(--bg-surface);
    color: var(--text-muted);
}
.pill-loc-btn.active {
    background: var(--color-accent) !important;
    color: #FFFFFF !important;
    border-color: var(--color-accent) !important;
    box-shadow: 0 2px 8px rgba(30, 64, 175, 0.25);
}

/* TABLAS Y BADGES */
.vl-table thead th {
    background: #0F172A;
    color: #F8FAFC;
    text-transform: uppercase;
    font-size: 0.72rem;
    letter-spacing: 0.6px;
    font-weight: 700;
    padding: 10px 14px;
    border: none;
}
body.dark-mode .vl-table thead th {
    background: #0B1120;
}
.vl-table tbody td {
    padding: 9px 14px;
    vertical-align: middle;
    font-size: 0.84rem;
    border-top: 1px solid var(--border-grid);
    color: var(--text-color);
}
.vl-table tbody tr:hover {
    background: rgba(30, 64, 175, 0.05);
}

/* PAGINACIÓN SOBRIA */
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
    background: var(--bg-surface);
    border: 1px solid var(--border-grid);
    color: var(--text-color);
    cursor: pointer;
    transition: all 0.15s ease;
    user-select: none;
    margin: 0 2px;
}
.sonda-page-btn:hover:not(.disabled):not(.active) {
    background: var(--bg-canvas);
    border-color: var(--color-accent);
    color: var(--color-accent);
}
.sonda-page-btn.active {
    background: var(--color-accent) !important;
    border-color: var(--color-accent) !important;
    color: #FFFFFF !important;
    box-shadow: 0 2px 6px rgba(30, 64, 175, 0.25);
}
.sonda-page-btn.disabled {
    opacity: 0.4;
    cursor: not-allowed;
    background: var(--bg-canvas);
    border-color: var(--border-grid);
}

/* SIMULADOR DE PANEL FRONTAL */
.switch-chassis {
    background: #0F172A;
    border: 1px solid #1E293B;
    border-radius: 10px;
    padding: 16px;
    box-shadow: inset 0 2px 8px rgba(0,0,0,0.5);
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
    background: var(--status-metric);
    color: #FFFFFF;
    cursor: pointer;
    box-shadow: 0 1px 3px rgba(15, 118, 110, 0.4);
}
.port-cell.connected:hover {
    transform: scale(1.15);
    box-shadow: 0 0 10px rgba(15, 118, 110, 0.8);
    z-index: 5;
}
.port-cell.free {
    background: #334155;
    color: #94A3B8;
}
.port-cell.damaged {
    background: #DC2626;
}
.port-detail-panel {
    background: var(--bg-surface);
    border: 1px solid var(--border-grid);
    border-radius: 8px;
    padding: 16px;
    margin-top: 12px;
    animation: fadeIn .25s;
}

@keyframes fadeIn {
    from { opacity:0; transform: translateY(4px); }
    to { opacity:1; transform: translateY(0); }
}
</style>

<div class="container-fluid py-3">
    <!-- CONTENEDOR PRINCIPAL EJECUTIVO -->
    <div class="vl-main-shell">
        
        <!-- BANNER DE CABECERA SOBRIO -->
        <div class="vl-executive-header d-flex justify-content-between align-items-center flex-wrap" style="gap: 14px;">
            <div>
                <div class="d-flex align-items-center mb-1 flex-wrap" style="gap: 8px;">
                    <span class="badge badge-pill px-2 py-1 font-weight-bold" style="background: var(--color-accent); color: #FFFFFF; font-size: 0.72rem; letter-spacing: 0.5px;">
                        <i class="fas fa-building mr-1"></i>VILASECA
                    </span>
                    <span class="badge badge-pill px-2 py-1 font-weight-bold" style="background: #1E293B; color: #38BDF8; border: 1px solid #334155; font-size: 0.72rem; letter-spacing: 0.5px;">
                        <i class="fas fa-network-wired mr-1"></i>SISTEMA CMDB
                    </span>
                    <span class="badge badge-pill px-2 py-1 font-weight-bold" style="background: var(--status-metric); color: #FFFFFF; font-size: 0.72rem;">
                        <i class="fas fa-check-circle mr-1"></i>EN VIVO
                    </span>
                </div>
                <h4 class="m-0 font-weight-bold text-white" style="letter-spacing: -0.3px;">
                    <i class="fas fa-microchip mr-2" style="color: var(--color-accent);"></i>Gestión Técnica de Infraestructura y Capacidad
                </h4>
                <p class="m-0 text-white-50 small mt-1" style="font-size: 0.85rem;">
                    Consola ejecutiva principal: análisis de capacidad, ocupación física de puertos, densidad de bastidores y monitoreo de infraestructura.
                </p>
            </div>
            
            <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
                <button type="button" class="btn btn-sm font-weight-bold text-white shadow-sm" style="background: var(--color-accent);" onclick="exportVilasecaCSV()">
                    <i class="fas fa-file-excel mr-1"></i> Exportar CSV
                </button>
                <a href="<?php echo PUBLIC_URL_PREFIX; ?>/portmapping.php?cliente=VILASECA&tab=analisis" class="btn btn-outline-light btn-sm font-weight-bold shadow-sm" title="Ir al Centro de Análisis y Mapeo de Puertos de Vilaseca">
                    <i class="fas fa-network-wired mr-1" style="color: #38BDF8;"></i> Portmapping
                </a>
                <a href="<?php echo PUBLIC_URL_PREFIX; ?>/analisis_conexiones.php?cliente=VILASECA" class="btn btn-outline-light btn-sm font-weight-bold shadow-sm">
                    <i class="fas fa-project-diagram mr-1" style="color: #14B8A6;"></i> Topología
                </a>
            </div>
        </div>

        <!-- CUERPO PRINCIPAL: ANÁLISIS DE INFRAESTRUCTURA (MANAGE) -->
        <div class="p-4">
                
                <!-- 🎯 FILTROS CONCATENADOS EN TIEMPO REAL (COLOCADOS PRIMERO) -->
                <div class="card border shadow-sm mb-4" style="border-radius: 10px; border-left: 4px solid var(--color-accent) !important; background: var(--bg-surface); border-color: var(--border-grid);">
                    <div class="card-body p-3" style="border-radius: 9px; background: var(--bg-canvas);">
                        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap" style="gap: 8px;">
                            <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
                                <span class="badge badge-pill text-white px-2 py-1 font-weight-bold" style="background: var(--sonda-navy); font-size: 0.72rem; letter-spacing: 0.5px;">
                                    <i class="fas fa-sliders-h mr-1" style="color: var(--color-accent);"></i>FILTROS CONCATENADOS
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

                <!-- GRÁFICOS DE MARCA, MODELO Y VLANS ENCONTRADAS (PANTONE SONDA) -->
                <div class="row mb-4">
                    <!-- 1. EQUIPOS POR MARCA -->
                    <div class="col-lg-4 col-md-6 mb-3">
                        <div class="card border-0 shadow-sm" style="border-radius: 12px; height: 100%;">
                            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap" style="gap: 8px;">
                                <h6 class="font-weight-bold text-dark m-0">
                                    <i class="fas fa-industry mr-2" style="color: var(--sonda-orange);"></i>Equipos por Marca
                                </h6>
                                <span class="badge badge-pill badge-light border text-muted font-weight-bold" id="vl-chart-brands-badge"><?php echo count($vl_brands); ?> Marcas</span>
                            </div>
                            <div class="card-body p-3">
                                <div style="height: 220px; position: relative;" class="mb-3">
                                    <canvas id="vl-chart-brands"></canvas>
                                </div>
                                <div class="vl-brands-legend-list pl-1" id="vl-brands-legend-list" style="max-height: 200px; overflow-y: auto;">
                                    <?php 
                                    $b_idx = 0;
                                    $tot_devs = count($vl_all_devices);
                                    foreach ($vl_brands as $b_name => $b_count): 
                                        $col = $sonda_palette[$b_idx % count($sonda_palette)];
                                        $pct = $tot_devs > 0 ? round(($b_count / $tot_devs) * 100) : 0;
                                        $b_idx++;
                                    ?>
                                    <div class="mb-2">
                                        <div class="d-flex justify-content-between align-items-center small font-weight-bold mb-1">
                                            <span class="text-truncate mr-1"><span style="display:inline-block;width:9px;height:9px;border-radius:2px;background:<?php echo $col; ?>;margin-right:6px;"></span><?php echo htmlspecialchars((string)$b_name); ?></span>
                                            <span class="text-dark whitespace-nowrap"><strong><?php echo $b_count; ?></strong> <small class="text-muted">(<?php echo $pct; ?>%)</small></span>
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

                    <!-- 2. EQUIPOS POR MODELO -->
                    <div class="col-lg-4 col-md-6 mb-3">
                        <div class="card border-0 shadow-sm" style="border-radius: 12px; height: 100%;">
                            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap" style="gap: 8px;">
                                <h6 class="font-weight-bold text-dark m-0">
                                    <i class="fas fa-microchip mr-2" style="color: var(--sonda-cyan);"></i>Equipos por Modelo
                                </h6>
                                <span class="badge badge-pill badge-light border text-muted font-weight-bold" id="vl-chart-models-badge"><?php echo count($vl_models); ?> Modelos</span>
                            </div>
                            <div class="card-body p-3">
                                <div style="height: 220px; position: relative;" class="mb-3">
                                    <canvas id="vl-chart-models"></canvas>
                                </div>
                                <div class="vl-models-legend-list pl-1" id="vl-models-legend-list" style="max-height: 200px; overflow-y: auto;">
                                    <?php 
                                    $m_idx = 0;
                                    foreach ($vl_models as $m_name => $m_count): 
                                        $col = $sonda_palette[$m_idx % count($sonda_palette)];
                                        $pct = $tot_devs > 0 ? round(($m_count / $tot_devs) * 100) : 0;
                                        $m_idx++;
                                    ?>
                                    <div class="mb-2">
                                        <div class="d-flex justify-content-between align-items-center small font-weight-bold mb-1">
                                            <span class="text-truncate mr-1"><span style="display:inline-block;width:9px;height:9px;border-radius:2px;background:<?php echo $col; ?>;margin-right:6px;"></span><?php echo htmlspecialchars((string)$m_name); ?></span>
                                            <span class="text-dark whitespace-nowrap"><strong><?php echo $m_count; ?></strong> <small class="text-muted">(<?php echo $pct; ?>%)</small></span>
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

                    <!-- 3. VLANS ENCONTRADAS -->
                    <div class="col-lg-4 col-md-12 mb-3">
                        <div class="card border-0 shadow-sm" style="border-radius: 12px; height: 100%;">
                            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap" style="gap: 8px;">
                                <h6 class="font-weight-bold text-dark m-0">
                                    <i class="fas fa-network-wired mr-2" style="color: #65a30d;"></i>VLANs Encontradas
                                </h6>
                                <span class="badge badge-pill badge-light border text-muted font-weight-bold" id="vl-chart-vlans-badge"><?php echo count($vl_vlans); ?> Segmentos</span>
                            </div>
                            <div class="card-body p-3">
                                <div style="height: 220px; position: relative;" class="mb-3">
                                    <canvas id="vl-chart-vlans"></canvas>
                                </div>
                                <div class="vl-vlans-legend-list pl-1" id="vl-vlans-legend-list" style="max-height: 200px; overflow-y: auto;">
                                    <?php 
                                    $v_idx = 0;
                                    foreach ($vl_vlans as $v_name => $v_count): 
                                        $col = ((string)$v_name === 'SIN VLAN') ? '#64748B' : $sonda_palette[$v_idx % count($sonda_palette)];
                                        $pct = $tot_devs > 0 ? round(($v_count / $tot_devs) * 100) : 0;
                                        $v_ports = $vl_vlan_port_counts[$v_name] ?? 0;
                                        $v_idx++;
                                    ?>
                                    <div class="mb-2">
                                        <div class="d-flex justify-content-between align-items-center small font-weight-bold mb-1">
                                            <span class="text-truncate mr-1"><span style="display:inline-block;width:9px;height:9px;border-radius:2px;background:<?php echo $col; ?>;margin-right:6px;"></span><?php echo htmlspecialchars((string)$v_name); ?></span>
                                            <span class="text-dark whitespace-nowrap"><strong><?php echo $v_count; ?> eq</strong> <small class="text-muted">(<?php echo $v_ports; ?> ptos)</small></span>
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
$vl_initial_page_size = 30;
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
                                    <option value="10">10 por página</option>
                                    <option value="20">20 por página</option>
                                    <option value="30" selected>30 por página (Máximo)</option>
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
                                    $isInitVisible = ($dev_idx < 30);
                                    $vlansStr = implode(', ', $dev['vlans'] ?? ['SIN VLAN']);
                                ?>
                                <tr style="<?php echo $isInitVisible ? '' : 'display: none;'; ?>" data-id="<?php echo $dev['id']; ?>" data-loc="<?php echo htmlspecialchars($dev['location']); ?>" data-area="<?php echo htmlspecialchars($dev['area'] ?? 'General'); ?>" data-type="<?php echo htmlspecialchars($dev['device_type']); ?>" data-vendor="<?php echo htmlspecialchars($dev['vendor']); ?>" data-model="<?php echo htmlspecialchars($dev['model']); ?>" data-vlans="<?php echo htmlspecialchars($vlansStr); ?>" data-name="<?php echo htmlspecialchars($dev['device_name']); ?>" data-ip="<?php echo htmlspecialchars($dev['ip_address']); ?>" data-rack="<?php echo htmlspecialchars($dev['rack']); ?>">
                                    <td class="font-weight-bold text-dark">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-server mr-2" style="color: var(--sonda-orange);"></i>
                                            <span><?php echo htmlspecialchars($dev['device_name']); ?></span>
                                        </div>
                                        <div class="small text-muted mt-1">
                                            IP: <code><?php echo htmlspecialchars($dev['ip_address']); ?></code>
                                            &bull;
                                            <span class="badge badge-light border text-dark font-weight-bold" title="Marca"><i class="fas fa-industry text-primary mr-1"></i><?php echo htmlspecialchars($dev['vendor']); ?></span>
                                            <span class="badge badge-light border text-muted" title="Modelo"><i class="fas fa-microchip text-secondary mr-1"></i><?php echo htmlspecialchars($dev['model']); ?></span>
                                            <?php if (!empty($dev['vlans']) && $dev['vlans'][0] !== 'SIN VLAN'): ?>
                                                &bull;
                                                <span class="badge badge-info" style="font-size:0.7rem;"><i class="fas fa-network-wired mr-1"></i><?php echo htmlspecialchars($vlansStr); ?></span>
                                            <?php else: ?>
                                                &bull;
                                                <span class="badge badge-light border text-muted" style="font-size:0.7rem;">SIN VLAN</span>
                                            <?php endif; ?>
                                        </div>
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
    brands: <?php echo json_encode($vl_brands, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
    models: <?php echo json_encode($vl_models, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
    vlans: <?php echo json_encode($vl_vlans, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
    vlan_port_counts: <?php echo json_encode($vl_vlan_port_counts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
    all_devices: <?php echo json_encode($vl_all_devices, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
};
</script>
<script src="<?php echo PUBLIC_URL_PREFIX; ?>/clientes/vilaseca/app.js?v=<?php echo filemtime(__DIR__ . '/app.js'); ?>"></script>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>

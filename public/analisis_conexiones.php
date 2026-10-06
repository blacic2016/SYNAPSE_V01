<?php
/**
 * Módulo de Análisis de Conexiones de Red y Topología de Interconexiones (VILASECA)
 * Versión Técnica Avanzada - Diagrama Estilizado Estilo Visio Blueprint
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/permissions_helper.php';

require_login();
if (!has_module_access('portmapping') && !has_module_access('clientes') && !has_module_access('vilaseca')) {
    header("Location: " . PUBLIC_URL_PREFIX . "/dashboard.php");
    exit();
}

$client_filter = $_GET['cliente'] ?? $_GET['client'] ?? 'VILASECA';
$is_embed = (isset($_GET['embed']) && $_GET['embed'] == '1');
if ($is_embed) {
    $hide_sidebar = true;
    $hide_content_header = true;
}
$page_title = "Topología Técnica de Red (" . htmlspecialchars($client_filter) . ") - Estilo Visio";
include 'partials/header.php';
?>

<!-- Vis-Network Local & CDN Fallback -->
<script type="text/javascript" src="cmdb_sonda/vendor/vis/vis-network.min.js"></script>
<script>
if (typeof vis === 'undefined') {
    document.write('<script type="text/javascript" src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"><\/script>');
}
</script>

<style>
<?php if ($is_embed): ?>
.main-header, .main-sidebar, .main-footer { display: none !important; }
.content-wrapper { margin-left: 0 !important; padding: 0 !important; background: transparent !important; }
body { background: transparent !important; }
<?php endif; ?>
:root {
    --visio-navy: #0f172a;
    --visio-navy-light: #1e293b;
    --visio-border: #334155;
    --visio-blue: #0284c7;
    --visio-cyan: #38bdf8;
    --visio-orange: #f97316;
    --visio-green: #10b981;
    --visio-purple: #8b5cf6;
    --visio-red: #ef4444;
}

/* Card & Layout */
.visio-shell {
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 8px 30px rgba(0,0,0,0.06);
    background: #ffffff;
    overflow: hidden;
}

.visio-header {
    background: linear-gradient(135deg, #002B49 0%, #003a6c 50%, #0056b3 100%);
    color: #ffffff;
    padding: 1.25rem 1.75rem;
    border-left: 5px solid #00A3E0;
    border-bottom: 2px solid #00A3E0;
}

/* Visio Blueprint Canvas Themes */
.canvas-wrapper {
    position: relative;
    width: 100%;
    height: 720px;
    transition: height 0.3s ease;
    overflow: hidden;
}

.canvas-wrapper.fullscreen-mode {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    z-index: 99999 !important;
    border-radius: 0 !important;
}

/* Theme 1: NOC Dark Blueprint */
.theme-dark {
    background-color: #090d16;
    background-image: 
        radial-gradient(circle, rgba(56, 189, 248, 0.22) 1.1px, transparent 1.1px),
        linear-gradient(to right, rgba(255, 255, 255, 0.04) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(255, 255, 255, 0.04) 1px, transparent 1px);
    background-size: 24px 24px, 120px 120px, 120px 120px;
}

/* Theme 2: Visio Technical Light Paper */
.theme-light {
    background-color: #f8fafc;
    background-image: 
        radial-gradient(circle, #94a3b8 1px, transparent 1px),
        linear-gradient(to right, rgba(0, 0, 0, 0.04) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(0, 0, 0, 0.04) 1px, transparent 1px);
    background-size: 24px 24px, 120px 120px, 120px 120px;
}

/* Theme 3: Classic Blue CAD Blueprint */
.theme-blueprint {
    background-color: #072746;
    background-image: 
        linear-gradient(rgba(255,255,255,0.08) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.08) 1px, transparent 1px),
        linear-gradient(rgba(255,255,255,0.025) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.025) 1px, transparent 1px);
    background-size: 100px 100px, 100px 100px, 20px 20px, 20px 20px;
}

.no-grid {
    background-image: none !important;
}

#network_canvas {
    width: 100%;
    height: 100%;
}

/* Stats Cards */
.stat-card-visio {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 0.9rem 1.2rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: all 0.2s ease;
}

.stat-card-visio:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.08);
}

.stat-card-num {
    font-size: 1.65rem;
    font-weight: 800;
    line-height: 1.1;
    font-family: 'JetBrains Mono', monospace, sans-serif;
}

.stat-card-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748b;
}

/* Floating HUD Toolbar */
.visio-hud-top {
    position: absolute;
    top: 14px;
    left: 14px;
    z-index: 100;
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
    background: rgba(15, 23, 42, 0.88);
    backdrop-filter: blur(8px);
    padding: 6px 12px;
    border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.12);
    box-shadow: 0 10px 25px rgba(0,0,0,0.3);
}

.visio-hud-search {
    position: absolute;
    top: 14px;
    right: 14px;
    z-index: 100;
    width: 280px;
}

.visio-hud-controls {
    position: absolute;
    bottom: 20px;
    right: 20px;
    z-index: 100;
    display: flex;
    flex-direction: column;
    gap: 6px;
    background: rgba(15, 23, 42, 0.88);
    backdrop-filter: blur(8px);
    padding: 6px;
    border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.12);
    box-shadow: 0 10px 25px rgba(0,0,0,0.3);
}

.hud-btn {
    width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.15);
    color: #ffffff;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.85rem;
    transition: all 0.2s ease;
}

.hud-btn:hover {
    background: #0284c7;
    color: #ffffff;
    border-color: #38bdf8;
    transform: scale(1.05);
}

.hud-btn.active {
    background: #0284c7;
    border-color: #38bdf8;
    color: #ffffff;
}

/* Layer Filter Chips */
.filter-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.76rem;
    font-weight: 700;
    cursor: pointer;
    user-select: none;
    transition: all 0.2s ease;
    border: 1px solid rgba(255,255,255,0.15);
    background: rgba(255,255,255,0.06);
    color: #e2e8f0;
}

.filter-chip:hover {
    filter: brightness(1.2);
    transform: translateY(-1px);
}

.filter-chip.active {
    box-shadow: 0 0 10px rgba(56, 189, 248, 0.4);
    border-color: #38bdf8;
}

/* Visio Inspector Slide Drawer */
.inspector-drawer {
    position: absolute;
    top: 0;
    right: -420px;
    width: 400px;
    height: 100%;
    background: #ffffff;
    box-shadow: -8px 0 25px rgba(0,0,0,0.15);
    border-left: 1px solid #cbd5e1;
    z-index: 150;
    transition: right 0.3s cubic-bezier(0.25, 1, 0.5, 1);
    display: flex;
    flex-direction: column;
}

.inspector-drawer.open {
    right: 0;
}

.inspector-header {
    background: #0f172a;
    color: #ffffff;
    padding: 1rem 1.25rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 2px solid #0284c7;
}

.inspector-body {
    flex: 1;
    overflow-y: auto;
    padding: 1.25rem;
    font-size: 0.85rem;
}

/* Legend bar */
.visio-legend-bar {
    position: absolute;
    bottom: 14px;
    left: 14px;
    z-index: 100;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    background: rgba(15, 23, 42, 0.90);
    backdrop-filter: blur(8px);
    padding: 6px 14px;
    border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.12);
    max-width: calc(100% - 120px);
}

.legend-item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.74rem;
    color: #cbd5e1;
    font-weight: 600;
}

.legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 2px;
}
</style>

<div class="container-fluid py-3">
    <div class="visio-shell mb-4">
        <!-- HEADER -->
        <div class="visio-header d-flex justify-content-between align-items-center flex-wrap" style="gap: 14px;">
            <div>
                <div class="d-flex align-items-center" style="gap: 10px;">
                    <span class="badge badge-pill badge-info px-2 py-1 font-weight-bold" style="background: #0284c7; font-size: 0.72rem; letter-spacing: 0.5px;">
                        <i class="fas fa-drafting-compass mr-1"></i>VISIO BLUEPRINT
                    </span>
                    <span class="text-white-50 small">|</span>
                    <span class="text-white small font-weight-bold">
                        <i class="fas fa-building text-warning mr-1"></i>Cliente: <span class="text-white"><?php echo htmlspecialchars($client_filter); ?></span>
                    </span>
                </div>
                <h4 class="m-0 font-weight-bold mt-1" style="letter-spacing: -0.3px;">
                    <i class="fas fa-project-diagram text-cyan mr-2" style="color: #38bdf8;"></i>Topología Técnica e Interconexiones de Red
                </h4>
                <p class="m-0 text-muted small mt-1" style="color: #94a3b8 !important;">
                    Diagrama arquitectónico estructurado con detección recíproca de puertos, jerarquía de niveles y enlaces físicos/lógicos.
                </p>
            </div>
            
            <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
                <?php if (strtoupper($client_filter) === 'VILASECA'): ?>
                <a href="<?php echo PUBLIC_URL_PREFIX; ?>/clientes/vilaseca/index.php" class="btn btn-outline-light btn-sm font-weight-bold px-3">
                    <i class="fas fa-chart-line mr-1" style="color: #ff5c05;"></i> Dashboard (Manage)
                </a>
                <?php endif; ?>
                <button class="btn btn-outline-light btn-sm font-weight-bold px-3" onclick="exportVisioDrawio()" title="Abrir en editor de diagramas">
                    <i class="fas fa-external-link-alt mr-1 text-warning"></i> Exportar a Visio / Draw.io
                </button>
                <button class="btn btn-outline-success btn-sm font-weight-bold px-3" onclick="exportHighResBlueprint()" title="Descargar imagen PNG de ingeniería">
                    <i class="fas fa-camera mr-1"></i> Exportar Imagen HD
                </button>
            </div>
        </div>

        <!-- CONTROLES Y FILTROS TÉCNICOS -->
        <div class="card-body bg-light border-bottom py-3">
            <div class="row align-items-end" style="row-gap: 12px;">
                <!-- UBICACIÓN -->
                <div class="col-md-2 col-lg-2">
                    <label class="font-weight-bold text-dark small mb-1">
                        <i class="fas fa-map-marker-alt text-danger mr-1"></i>Ubicación:
                    </label>
                    <select id="sel_location" class="form-control form-control-sm font-weight-bold" onchange="onLocationChange()">
                        <option value="">-- Cargar Ubicaciones... --</option>
                    </select>
                </div>

                <!-- EQUIPO ESPECÍFICO -->
                <div class="col-md-2 col-lg-2">
                    <label class="font-weight-bold text-dark small mb-1">
                        <i class="fas fa-server text-primary mr-1"></i>Aislar Equipo:
                    </label>
                    <select id="sel_device" class="form-control form-control-sm font-weight-bold" onchange="runConnectionAnalysis()">
                        <option value="">-- Ver Toda la Red --</option>
                    </select>
                </div>

                <!-- DISEÑO VISIO / LAYOUT -->
                <div class="col-md-3 col-lg-3">
                    <label class="font-weight-bold text-dark small mb-1">
                        <i class="fas fa-layer-group text-info mr-1"></i>Diseño de Topología:
                    </label>
                    <select id="sel_layout" class="form-control form-control-sm font-weight-bold" onchange="changeTopologyLayout(this.value)">
                        <option value="hierarchical_ud">📐 Jerárquico Visio (Niveles de Red)</option>
                        <option value="hierarchical_lr">➡️ Jerárquico Horizontal (Borde a Acceso)</option>
                        <option value="free_stabilized">🕸️ Topología Libre (Fuerza Estabilizada)</option>
                    </select>
                </div>

                <!-- ESTILO DE SÍMBOLOS -->
                <div class="col-md-3 col-lg-3">
                    <label class="font-weight-bold text-dark small mb-1">
                        <i class="fas fa-shapes text-warning mr-1"></i>Estilo de Símbolos:
                    </label>
                    <select id="sel_stencil_style" class="form-control form-control-sm font-weight-bold" onchange="changeStencilStyle(this.value)">
                        <option value="visio_hardware" selected>🖥️ Símbolos Visio (Cisco 3D / Hardware)</option>
                        <option value="visio_cards">📋 Fichas Blueprint Técnicas</option>
                    </select>
                </div>

                <!-- ACCIONES -->
                <div class="col-md-2 col-lg-2 d-flex align-items-center" style="gap: 8px;">
                    <button id="btn_analyze" class="btn btn-primary btn-sm font-weight-bold px-2 shadow-sm flex-fill" onclick="runConnectionAnalysis()">
                        <i class="fas fa-sync-alt mr-1"></i> ACTUALIZAR
                    </button>
                    <button class="btn btn-outline-secondary btn-sm font-weight-bold" onclick="resetFilters()" title="Restablecer">
                        <i class="fas fa-undo"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- TARJETAS KPI DE RED -->
        <div class="card-body border-bottom py-2 bg-white">
            <div class="row" style="row-gap: 8px;">
                <div class="col-6 col-md-3">
                    <div class="stat-card-visio">
                        <div>
                            <div class="stat-card-label">Equipos / Nodos</div>
                            <div class="stat-card-num text-primary" id="stat_total_nodes">0</div>
                        </div>
                        <i class="fas fa-network-wired fa-2x text-muted opacity-50"></i>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card-visio">
                        <div>
                            <div class="stat-card-label">Interconexiones Físicas</div>
                            <div class="stat-card-num text-info" id="stat_total_edges">0</div>
                        </div>
                        <i class="fas fa-bezier-curve fa-2x text-muted opacity-50"></i>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card-visio">
                        <div>
                            <div class="stat-card-label">Troncales / Múltiples</div>
                            <div class="stat-card-num text-warning" id="stat_multi_edges">0</div>
                        </div>
                        <i class="fas fa-random fa-2x text-muted opacity-50"></i>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card-visio">
                        <div>
                            <div class="stat-card-label">Puertos Físicos Mapeados</div>
                            <div class="stat-card-num text-success" id="stat_total_ports">0</div>
                        </div>
                        <i class="fas fa-plug fa-2x text-muted opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- CANVAS PRINCIPAL VISIO BLUEPRINT -->
        <div class="p-0 position-relative">
            <div id="canvas_wrapper" class="canvas-wrapper theme-dark">
                <!-- FLOATING TOP HUD (BARRA DE HERRAMIENTAS VISIO) -->
                <div class="visio-hud-top">
                    <!-- Theme Selector -->
                    <div class="btn-group btn-group-sm mr-2">
                        <button class="btn btn-xs btn-outline-light active" id="btn_theme_dark" onclick="setCanvasTheme('theme-dark')" title="Blueprint Oscuro (NOC)">
                            <i class="fas fa-moon mr-1"></i>Oscuro
                        </button>
                        <button class="btn btn-xs btn-outline-light" id="btn_theme_light" onclick="setCanvasTheme('theme-light')" title="Visio Técnico Claro">
                            <i class="fas fa-sun mr-1"></i>Claro
                        </button>
                        <button class="btn btn-xs btn-outline-light" id="btn_theme_blueprint" onclick="setCanvasTheme('theme-blueprint')" title="Blueprint CAD Azul">
                            <i class="fas fa-drafting-compass mr-1"></i>CAD
                        </button>
                    </div>

                    <!-- Grid Toggle -->
                    <button class="hud-btn" id="btn_toggle_grid" onclick="toggleGrid()" title="Mostrar / Ocultar Cuadrícula Visio">
                        <i class="fas fa-border-all"></i>
                    </button>

                    <!-- Physics Freeze Toggle -->
                    <button class="hud-btn active" id="btn_physics_lock" onclick="togglePhysicsLock()" title="Fijar / Congelar Nodos (Modo Plano)">
                        <i class="fas fa-lock"></i>
                    </button>

                    <!-- Stencil Style Toggle (Visio 3D vs Cards) -->
                    <button class="hud-btn active" id="btn_toggle_stencil_style" onclick="toggleStencilStyle()" title="Alternar: Símbolos Visio (Cisco 3D) / Fichas Blueprint">
                        <i class="fas fa-shapes"></i>
                    </button>

                    <!-- Separator -->
                    <div style="width: 1px; height: 20px; background: rgba(255,255,255,0.2); margin: 0 4px;"></div>

                    <!-- Category Filter Chips -->
                    <div class="d-flex align-items-center flex-wrap" style="gap: 5px;">
                        <span class="filter-chip active" id="chip_all" onclick="filterByCategory('all')">
                            <i class="fas fa-cubes"></i> Todos (<span id="count_all">0</span>)
                        </span>
                        <span class="filter-chip" id="chip_router" onclick="filterByCategory('router')">
                            <span class="legend-dot" style="background: #dc2626;"></span> Routers (<span id="count_router">0</span>)
                        </span>
                        <span class="filter-chip" id="chip_firewall" onclick="filterByCategory('firewall')">
                            <span class="legend-dot" style="background: #7c3aed;"></span> Firewalls (<span id="count_firewall">0</span>)
                        </span>
                        <span class="filter-chip" id="chip_switch" onclick="filterByCategory('switch')">
                            <span class="legend-dot" style="background: #0284c7;"></span> Switches (<span id="count_switch">0</span>)
                        </span>
                        <span class="filter-chip" id="chip_patch_panel" onclick="filterByCategory('patch_panel')">
                            <span class="legend-dot" style="background: #d97706;"></span> Paneles (<span id="count_patch">0</span>)
                        </span>
                        <span class="filter-chip" id="chip_server" onclick="filterByCategory('server')">
                            <span class="legend-dot" style="background: #059669;"></span> Servidores (<span id="count_server">0</span>)
                        </span>
                        <span class="filter-chip" id="chip_converter" onclick="filterByCategory('converter')">
                            <span class="legend-dot" style="background: #0891b2;"></span> Medios / PoE (<span id="count_cvm">0</span>)
                        </span>
                    </div>
                </div>

                <!-- BUSCADOR RÁPIDO SPOTLIGHT -->
                <div class="visio-hud-search">
                    <div class="input-group input-group-sm shadow">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-dark border-0 text-cyan"><i class="fas fa-search"></i></span>
                        </div>
                        <input type="text" id="input_device_search" class="form-control bg-dark border-0 text-white" placeholder="Buscar equipo o IP..." oninput="onSearchDevice(this.value)">
                        <div class="input-group-append">
                            <button class="btn btn-dark border-0 text-white-50" onclick="clearSearch()" title="Limpiar"><i class="fas fa-times"></i></button>
                        </div>
                    </div>
                </div>

                <!-- FLOATING ZOOM CONTROLS -->
                <div class="visio-hud-controls">
                    <button class="hud-btn" onclick="zoomIn()" title="Acercar (Zoom +)"><i class="fas fa-plus"></i></button>
                    <button class="hud-btn" onclick="zoomOut()" title="Alejar (Zoom -)"><i class="fas fa-minus"></i></button>
                    <button class="hud-btn" onclick="fitGraph()" title="Centrar y Ajustar Diagrama"><i class="fas fa-compress-arrows-alt"></i></button>
                    <button class="hud-btn" onclick="toggleFullscreenCanvas()" title="Pantalla Completa"><i class="fas fa-expand"></i></button>
                </div>

                <!-- CANVAS DE DIBUJO DE RED -->
                <div id="network_canvas"></div>

                <!-- LEYENDA TÉCNICA VISIO INFERIOR -->
                <div class="visio-legend-bar">
                    <span class="text-white-50 small mr-2 font-weight-bold"><i class="fas fa-info-circle text-cyan mr-1"></i>Enlaces:</span>
                    <span class="legend-item"><span class="legend-dot" style="background: #38bdf8;"></span> Cobre UTP (Cat6A)</span>
                    <span class="legend-item"><span class="legend-dot" style="background: #f97316;"></span> Fibra Óptica (OS2/OM4)</span>
                    <span class="legend-item"><span class="legend-dot" style="background: #fbbf24; height: 4px; width: 14px;"></span> Troncal / Múltiple</span>
                    <span class="legend-item"><span class="legend-dot" style="background: #c084fc; border: 1px dashed #fff;"></span> Alta Disponibilidad (HA)</span>
                </div>

                <!-- PANEL LATERAL DE INSPECCIÓN TÉCNICA (DRAWER) -->
                <div id="inspector_drawer" class="inspector-drawer">
                    <div class="inspector-header">
                        <div class="d-flex align-items-center" style="gap: 8px;">
                            <i class="fas fa-microchip text-cyan" style="color: #38bdf8;"></i>
                            <h6 class="m-0 font-weight-bold" id="inspector_title">Inspección Técnica</h6>
                        </div>
                        <button type="button" class="btn btn-sm text-white" onclick="closeInspector()">&times;</button>
                    </div>
                    <div class="inspector-body" id="inspector_content">
                        <p class="text-muted text-center py-4">Seleccione un equipo o cable para ver detalles técnicos.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL COMPLETO DE DETALLE -->
<div class="modal fade" id="modalConnectionDetail" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header bg-dark text-white py-3">
                <h5 class="modal-title font-weight-bold" id="modalDetailTitle">
                    <i class="fas fa-plug text-warning mr-2"></i>Detalle Técnico
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3" id="modalDetailContent"></div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm font-weight-bold" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
const CLIENT_FILTER = '<?php echo addslashes($client_filter); ?>';
let network = null;
let rawNodesData = [];
let rawEdgesData = [];
let rawLocationsList = [];
let rawDevicesList = [];
let currentTheme = 'theme-dark';
let currentLayout = 'hierarchical_ud';
let currentStencilStyle = 'visio_hardware'; // 'visio_hardware' (Símbolos Cisco 3D) o 'visio_cards' (Fichas Blueprint)
let isGridVisible = true;
let isPhysicsLocked = true;
let activeCategoryFilter = 'all';
let selectedNodeId = null;

document.addEventListener('DOMContentLoaded', () => {
    loadAnalysisFilters();
    setTimeout(() => {
        runConnectionAnalysis();
    }, 300);
});

// 1. Cargar filtros de Ubicación y Equipos
async function loadAnalysisFilters() {
    try {
        const resp = await fetch(`api_portmapping.php?action=get_analysis_filters&client=${encodeURIComponent(CLIENT_FILTER)}`);
        const res = await resp.json();
        if (res.success) {
            rawLocationsList = res.locations || [];
            rawDevicesList = res.devices || [];

            const locSelect = document.getElementById('sel_location');
            locSelect.innerHTML = '<option value="">-- Todas las Ubicaciones --</option>';
            rawLocationsList.forEach(loc => {
                const opt = document.createElement('option');
                opt.value = loc;
                opt.textContent = loc;
                locSelect.appendChild(opt);
            });

            updateDeviceDropdown('');
        }
    } catch (e) {
        console.error("Error cargando filtros:", e);
    }
}

function onLocationChange() {
    const loc = document.getElementById('sel_location').value;
    updateDeviceDropdown(loc);
    runConnectionAnalysis();
}

function updateDeviceDropdown(selectedLoc) {
    const devSelect = document.getElementById('sel_device');
    devSelect.innerHTML = '<option value="">-- Todos los equipos de la ubicación --</option>';

    let filteredDevs = rawDevicesList;
    if (selectedLoc && selectedLoc.trim() !== '') {
        filteredDevs = rawDevicesList.filter(d => d.location === selectedLoc);
    }

    filteredDevs.forEach(dev => {
        const opt = document.createElement('option');
        opt.value = dev.device_name;
        opt.textContent = `${dev.device_name} (${dev.device_type || 'Hardware'})`;
        devSelect.appendChild(opt);
    });
}

function resetFilters() {
    document.getElementById('sel_location').value = '';
    updateDeviceDropdown('');
    document.getElementById('sel_layout').value = 'hierarchical_ud';
    currentLayout = 'hierarchical_ud';
    const selSt = document.getElementById('sel_stencil_style');
    if (selSt) selSt.value = 'visio_hardware';
    currentStencilStyle = 'visio_hardware';
    const btnSt = document.getElementById('btn_toggle_stencil_style');
    if (btnSt) btnSt.classList.add('active');
    activeCategoryFilter = 'all';
    clearSearch();
    runConnectionAnalysis();
}

function changeStencilStyle(styleVal) {
    currentStencilStyle = styleVal;
    const btn = document.getElementById('btn_toggle_stencil_style');
    if (btn) btn.classList.toggle('active', currentStencilStyle === 'visio_hardware');
    const sel = document.getElementById('sel_stencil_style');
    if (sel) sel.value = styleVal;
    renderVisioNetwork();
}

function toggleStencilStyle() {
    const nextStyle = currentStencilStyle === 'visio_hardware' ? 'visio_cards' : 'visio_hardware';
    changeStencilStyle(nextStyle);
}

// 2. Consulta y Obtención de Datos de Red
async function runConnectionAnalysis() {
    const locVal = document.getElementById('sel_location').value;
    const devVal = document.getElementById('sel_device').value;

    const container = document.getElementById('network_canvas');
    container.innerHTML = '<div class="d-flex h-100 justify-content-center align-items-center text-white"><i class="fas fa-spinner fa-spin fa-2x mr-3 text-cyan"></i> Generando Visio Blueprint de Red...</div>';

    try {
        const url = `api_portmapping.php?action=get_connection_graph&client=${encodeURIComponent(CLIENT_FILTER)}&location=${encodeURIComponent(locVal)}&device_name=${encodeURIComponent(devVal)}`;
        const resp = await fetch(url);
        const res = await resp.json();

        if (res.success && res.nodes && res.nodes.length > 0) {
            rawNodesData = res.nodes;
            rawEdgesData = res.edges;
            renderVisioNetwork();
            updateStatsAndCounters(res.nodes, res.edges);
        } else {
            container.innerHTML = '<div class="d-flex h-100 justify-content-center align-items-center text-muted"><i class="fas fa-exclamation-circle fa-2x mr-2"></i> No se encontraron interconexiones para los filtros seleccionados.</div>';
            updateStatsAndCounters([], []);
        }
    } catch (e) {
        console.error("Error obteniendo análisis de red:", e);
        container.innerHTML = '<div class="d-flex h-100 justify-content-center align-items-center text-danger"><i class="fas fa-exclamation-triangle fa-2x mr-2"></i> Error procesando el análisis de red.</div>';
    }
}

// 3. Generador de Stencils SVG Estilo Visio / Cisco (Símbolos 3D de Hardware de Red)
function getSvgGradientDefs() {
    return `
      <defs>
        <linearGradient id="swTopGrad" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stop-color="#0284c7"/>
          <stop offset="100%" stop-color="#0369a1"/>
        </linearGradient>
        <linearGradient id="swSideGrad" x1="0%" y1="0%" x2="0%" y2="100%">
          <stop offset="0%" stop-color="#0369a1"/>
          <stop offset="100%" stop-color="#0f172a"/>
        </linearGradient>
        <linearGradient id="swFrontGrad" x1="0%" y1="0%" x2="0%" y2="100%">
          <stop offset="0%" stop-color="#1e293b"/>
          <stop offset="100%" stop-color="#0f172a"/>
        </linearGradient>

        <linearGradient id="rtTopGrad" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stop-color="#ef4444"/>
          <stop offset="100%" stop-color="#991b1b"/>
        </linearGradient>
        <linearGradient id="rtBodyGrad" x1="0%" y1="0%" x2="0%" y2="100%">
          <stop offset="0%" stop-color="#7f1d1d"/>
          <stop offset="100%" stop-color="#1e293b"/>
        </linearGradient>

        <linearGradient id="fwTopGrad" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stop-color="#9333ea"/>
          <stop offset="100%" stop-color="#581c87"/>
        </linearGradient>
        <linearGradient id="fwSideGrad" x1="0%" y1="0%" x2="0%" y2="100%">
          <stop offset="0%" stop-color="#581c87"/>
          <stop offset="100%" stop-color="#1e1b4b"/>
        </linearGradient>
        <linearGradient id="fwFrontGrad" x1="0%" y1="0%" x2="0%" y2="100%">
          <stop offset="0%" stop-color="#2e1065"/>
          <stop offset="100%" stop-color="#0f172a"/>
        </linearGradient>

        <linearGradient id="ppTopGrad" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stop-color="#475569"/>
          <stop offset="100%" stop-color="#1e293b"/>
        </linearGradient>
        <linearGradient id="ppFrontGrad" x1="0%" y1="0%" x2="0%" y2="100%">
          <stop offset="0%" stop-color="#1e293b"/>
          <stop offset="100%" stop-color="#0f172a"/>
        </linearGradient>

        <linearGradient id="srvTopGrad" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stop-color="#10b981"/>
          <stop offset="100%" stop-color="#047857"/>
        </linearGradient>
        <linearGradient id="srvSideGrad" x1="0%" y1="0%" x2="0%" y2="100%">
          <stop offset="0%" stop-color="#047857"/>
          <stop offset="100%" stop-color="#022c22"/>
        </linearGradient>
        <linearGradient id="srvFrontGrad" x1="0%" y1="0%" x2="0%" y2="100%">
          <stop offset="0%" stop-color="#1e293b"/>
          <stop offset="100%" stop-color="#0f172a"/>
        </linearGradient>

        <linearGradient id="apDomeGrad" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stop-color="#f8fafc"/>
          <stop offset="100%" stop-color="#cbd5e1"/>
        </linearGradient>

        <linearGradient id="cvTopGrad" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stop-color="#06b6d4"/>
          <stop offset="100%" stop-color="#0e7490"/>
        </linearGradient>
        <linearGradient id="cvFrontGrad" x1="0%" y1="0%" x2="0%" y2="100%">
          <stop offset="0%" stop-color="#0891b2"/>
          <stop offset="100%" stop-color="#0f172a"/>
        </linearGradient>

        <linearGradient id="dmFrontGrad" x1="0%" y1="0%" x2="0%" y2="100%">
          <stop offset="0%" stop-color="#334155"/>
          <stop offset="100%" stop-color="#0f172a"/>
        </linearGradient>
      </defs>`;
}

function getNetworkHardwareIconSvg(type, isDark = true) {
    switch (type) {
        case 'switch':
            return `
            <g id="sw_3d" transform="translate(10, 6)">
                <ellipse cx="70" cy="46" rx="55" ry="6" fill="rgba(0,0,0,0.35)"/>
                <path d="M 16,22 L 48,8 L 124,8 L 92,22 Z" fill="url(#swTopGrad)" stroke="#38bdf8" stroke-width="0.8"/>
                <g stroke="#ffffff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" fill="none" opacity="0.95">
                    <path d="M 42,14 L 66,14 M 62,11 L 67,14 L 62,17" />
                    <path d="M 96,17 L 72,17 M 76,14 L 71,17 L 76,20" />
                </g>
                <path d="M 92,22 L 124,8 L 124,24 L 92,38 Z" fill="url(#swSideGrad)" stroke="#0369a1" stroke-width="0.6"/>
                <line x1="100" y1="21" x2="100" y2="29" stroke="#0f172a" stroke-width="1.5" stroke-linecap="round"/>
                <line x1="106" y1="18" x2="106" y2="26" stroke="#0f172a" stroke-width="1.5" stroke-linecap="round"/>
                <line x1="112" y1="15" x2="112" y2="23" stroke="#0f172a" stroke-width="1.5" stroke-linecap="round"/>
                <path d="M 16,22 L 92,22 L 92,38 L 16,38 Z" fill="url(#swFrontGrad)" stroke="#334155" stroke-width="0.8"/>
                <rect x="11" y="23" width="5" height="14" rx="1" fill="#64748b" stroke="#334155" stroke-width="0.5"/>
                <circle cx="13.5" cy="30" r="1.2" fill="#0f172a"/>
                <rect x="92" y="23" width="5" height="14" rx="1" fill="#64748b" stroke="#334155" stroke-width="0.5"/>
                <circle cx="94.5" cy="30" r="1.2" fill="#0f172a"/>
                <g fill="#0284c7" stroke="#38bdf8" stroke-width="0.4">
                    <rect x="20" y="25" width="4" height="4" rx="0.5"/>
                    <rect x="26" y="25" width="4" height="4" rx="0.5"/>
                    <rect x="32" y="25" width="4" height="4" rx="0.5"/>
                    <rect x="38" y="25" width="4" height="4" rx="0.5"/>
                    <rect x="46" y="25" width="4" height="4" rx="0.5"/>
                    <rect x="52" y="25" width="4" height="4" rx="0.5"/>
                    <rect x="58" y="25" width="4" height="4" rx="0.5"/>
                    <rect x="64" y="25" width="4" height="4" rx="0.5"/>
                    <rect x="20" y="31" width="4" height="4" rx="0.5"/>
                    <rect x="26" y="31" width="4" height="4" rx="0.5"/>
                    <rect x="32" y="31" width="4" height="4" rx="0.5"/>
                    <rect x="38" y="31" width="4" height="4" rx="0.5"/>
                    <rect x="46" y="31" width="4" height="4" rx="0.5"/>
                    <rect x="52" y="31" width="4" height="4" rx="0.5"/>
                    <rect x="58" y="31" width="4" height="4" rx="0.5"/>
                    <rect x="64" y="31" width="4" height="4" rx="0.5"/>
                </g>
                <rect x="73" y="26" width="6.5" height="9" rx="1" fill="#0f172a" stroke="#fbbf24" stroke-width="0.8"/>
                <rect x="81.5" y="26" width="6.5" height="9" rx="1" fill="#0f172a" stroke="#fbbf24" stroke-width="0.8"/>
                <circle cx="19" cy="24" r="1" fill="#22c55e"/>
                <circle cx="19" cy="36" r="1" fill="#38bdf8"/>
                <line x1="18" y1="37.5" x2="90" y2="37.5" stroke="#ff5c05" stroke-width="1"/>
            </g>`;

        case 'router':
            return `
            <g id="rt_3d" transform="translate(10, 6)">
                <ellipse cx="70" cy="46" rx="48" ry="6" fill="rgba(0,0,0,0.35)"/>
                <path d="M 26,18 A 44 12 0 0 0 114,18 L 114,34 A 44 12 0 0 1 26,34 Z" fill="url(#rtBodyGrad)" stroke="#991b1b" stroke-width="0.8"/>
                <rect x="58" y="23" width="7" height="6" rx="1" fill="#0284c7" stroke="#38bdf8" stroke-width="0.5"/>
                <rect x="68" y="23" width="7" height="6" rx="1" fill="#0f172a" stroke="#22c55e" stroke-width="0.8"/>
                <rect x="78" y="23" width="7" height="6" rx="1" fill="#0f172a" stroke="#22c55e" stroke-width="0.8"/>
                <circle cx="44" cy="26" r="1.2" fill="#22c55e"/>
                <circle cx="49" cy="26" r="1.2" fill="#38bdf8"/>
                <circle cx="94" cy="26" r="1.2" fill="#fbbf24"/>
                <ellipse cx="70" cy="18" rx="44" ry="12" fill="url(#rtTopGrad)" stroke="#f87171" stroke-width="1"/>
                <g stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none" opacity="0.95">
                    <path d="M 70,8 L 70,14 M 67,11 L 70,14 L 73,11"/>
                    <path d="M 70,22 L 70,27 M 67,24 L 70,27 L 73,24"/>
                    <path d="M 58,18 L 44,18 M 48,15 L 43,18 L 48,21"/>
                    <path d="M 96,18 L 82,18 M 86,15 L 81,18 L 86,21"/>
                </g>
                <circle cx="70" cy="18" r="2.5" fill="#ffffff"/>
            </g>`;

        case 'firewall':
            return `
            <g id="fw_3d" transform="translate(10, 6)">
                <ellipse cx="70" cy="46" rx="52" ry="6" fill="rgba(0,0,0,0.35)"/>
                <path d="M 18,22 L 48,8 L 124,8 L 94,22 Z" fill="url(#fwTopGrad)" stroke="#c084fc" stroke-width="0.8"/>
                <path d="M 94,22 L 124,8 L 124,24 L 94,38 Z" fill="url(#fwSideGrad)" stroke="#581c87" stroke-width="0.6"/>
                <line x1="102" y1="21" x2="102" y2="29" stroke="#3b0764" stroke-width="1.5"/>
                <line x1="108" y1="18" x2="108" y2="26" stroke="#3b0764" stroke-width="1.5"/>
                <line x1="114" y1="15" x2="114" y2="23" stroke="#3b0764" stroke-width="1.5"/>
                <path d="M 18,22 L 94,22 L 94,38 L 18,38 Z" fill="url(#fwFrontGrad)" stroke="#7c3aed" stroke-width="0.8"/>
                <g stroke="#9333ea" stroke-width="0.8" opacity="0.65">
                    <line x1="18" y1="27" x2="56" y2="27"/>
                    <line x1="18" y1="33" x2="56" y2="33"/>
                    <line x1="28" y1="22" x2="28" y2="27"/>
                    <line x1="42" y1="22" x2="42" y2="27"/>
                    <line x1="22" y1="27" x2="22" y2="33"/>
                    <line x1="35" y1="27" x2="35" y2="33"/>
                    <line x1="49" y1="27" x2="49" y2="33"/>
                    <line x1="28" y1="33" x2="28" y2="38"/>
                    <line x1="42" y1="33" x2="42" y2="38"/>
                </g>
                <g transform="translate(62, 23)">
                    <path d="M 0,1 L 6,3 L 6,8 C 6,11 0,13 0,13 C 0,13 -6,11 -6,8 L -6,3 Z" fill="#7c3aed" stroke="#38bdf8" stroke-width="1"/>
                    <circle cx="0" cy="5.5" r="1.2" fill="#38bdf8"/>
                    <rect x="-1" y="6" width="2" height="3" fill="#ffffff"/>
                </g>
                <rect x="73" y="25" width="4.5" height="4" fill="#dc2626" stroke="#fca5a5" stroke-width="0.5"/>
                <rect x="79" y="25" width="4.5" height="4" fill="#dc2626" stroke="#fca5a5" stroke-width="0.5"/>
                <rect x="85" y="25" width="4.5" height="4" fill="#f59e0b" stroke="#fde68a" stroke-width="0.5"/>
                <rect x="73" y="31" width="4.5" height="4" fill="#0284c7" stroke="#93c5fd" stroke-width="0.5"/>
                <rect x="79" y="31" width="4.5" height="4" fill="#0284c7" stroke="#93c5fd" stroke-width="0.5"/>
                <rect x="85" y="31" width="4.5" height="4" fill="#22c55e" stroke="#86efac" stroke-width="0.5"/>
            </g>`;

        case 'patch_panel':
            return `
            <g id="pp_3d" transform="translate(10, 6)">
                <ellipse cx="70" cy="46" rx="55" ry="5" fill="rgba(0,0,0,0.35)"/>
                <path d="M 12,20 L 32,13 L 128,13 L 108,20 Z" fill="url(#ppTopGrad)" stroke="#64748b" stroke-width="0.6"/>
                <path d="M 12,20 L 108,20 L 108,36 L 12,36 Z" fill="url(#ppFrontGrad)" stroke="#334155" stroke-width="0.8"/>
                <rect x="7" y="21" width="5" height="14" rx="1" fill="#475569"/>
                <circle cx="9.5" cy="28" r="1.2" fill="#0f172a"/>
                <rect x="108" y="21" width="5" height="14" rx="1" fill="#475569"/>
                <circle cx="110.5" cy="28" r="1.2" fill="#0f172a"/>
                <rect x="15" y="21.5" width="90" height="3" rx="0.5" fill="#f8fafc" opacity="0.85"/>
                <g fill="#1e293b" stroke="#d97706" stroke-width="0.6">
                    <rect x="15" y="26" width="20" height="8" rx="1"/>
                    <rect x="38" y="26" width="20" height="8" rx="1"/>
                    <rect x="62" y="26" width="20" height="8" rx="1"/>
                    <rect x="85" y="26" width="20" height="8" rx="1"/>
                </g>
                <g fill="#0f172a" stroke="#fbbf24" stroke-width="0.4">
                    <rect x="16.5" y="28" width="2.2" height="4"/>
                    <rect x="19.5" y="28" width="2.2" height="4"/>
                    <rect x="22.5" y="28" width="2.2" height="4"/>
                    <rect x="25.5" y="28" width="2.2" height="4"/>
                    <rect x="28.5" y="28" width="2.2" height="4"/>
                    <rect x="31.5" y="28" width="2.2" height="4"/>
                    
                    <rect x="39.5" y="28" width="2.2" height="4"/>
                    <rect x="42.5" y="28" width="2.2" height="4"/>
                    <rect x="45.5" y="28" width="2.2" height="4"/>
                    <rect x="48.5" y="28" width="2.2" height="4"/>
                    <rect x="51.5" y="28" width="2.2" height="4"/>
                    <rect x="54.5" y="28" width="2.2" height="4"/>

                    <rect x="63.5" y="28" width="2.2" height="4"/>
                    <rect x="66.5" y="28" width="2.2" height="4"/>
                    <rect x="69.5" y="28" width="2.2" height="4"/>
                    <rect x="72.5" y="28" width="2.2" height="4"/>
                    <rect x="75.5" y="28" width="2.2" height="4"/>
                    <rect x="78.5" y="28" width="2.2" height="4"/>

                    <rect x="86.5" y="28" width="2.2" height="4"/>
                    <rect x="89.5" y="28" width="2.2" height="4"/>
                    <rect x="92.5" y="28" width="2.2" height="4"/>
                    <rect x="95.5" y="28" width="2.2" height="4"/>
                    <rect x="98.5" y="28" width="2.2" height="4"/>
                    <rect x="101.5" y="28" width="2.2" height="4"/>
                </g>
            </g>`;

        case 'server':
            return `
            <g id="srv_3d" transform="translate(10, 6)">
                <ellipse cx="70" cy="46" rx="54" ry="6" fill="rgba(0,0,0,0.35)"/>
                <path d="M 16,20 L 44,8 L 124,8 L 96,20 Z" fill="url(#srvTopGrad)" stroke="#34d399" stroke-width="0.8"/>
                <path d="M 96,20 L 124,8 L 124,26 L 96,38 Z" fill="url(#srvSideGrad)" stroke="#065f46" stroke-width="0.6"/>
                <path d="M 16,20 L 96,20 L 96,38 L 16,38 Z" fill="url(#srvFrontGrad)" stroke="#334155" stroke-width="0.8"/>
                <rect x="11" y="21" width="5" height="16" rx="1" fill="#64748b"/>
                <circle cx="13.5" cy="29" r="1.2" fill="#0f172a"/>
                <rect x="96" y="21" width="5" height="16" rx="1" fill="#64748b"/>
                <circle cx="98.5" cy="29" r="1.2" fill="#0f172a"/>
                <g fill="#1e293b" stroke="#475569" stroke-width="0.6">
                    <rect x="19" y="23" width="10" height="12" rx="0.5"/>
                    <rect x="31" y="23" width="10" height="12" rx="0.5"/>
                    <rect x="43" y="23" width="10" height="12" rx="0.5"/>
                    <rect x="55" y="23" width="10" height="12" rx="0.5"/>
                    <rect x="67" y="23" width="10" height="12" rx="0.5"/>
                    <rect x="79" y="23" width="10" height="12" rx="0.5"/>
                </g>
                <circle cx="27" cy="25" r="0.8" fill="#22c55e"/>
                <circle cx="39" cy="25" r="0.8" fill="#22c55e"/>
                <circle cx="51" cy="25" r="0.8" fill="#22c55e"/>
                <circle cx="63" cy="25" r="0.8" fill="#22c55e"/>
                <circle cx="75" cy="25" r="0.8" fill="#22c55e"/>
                <circle cx="87" cy="25" r="0.8" fill="#22c55e"/>
                <circle cx="92" cy="25" r="1.5" fill="#22c55e"/>
                <rect x="90.5" y="29" width="3" height="4" rx="0.5" fill="#38bdf8"/>
            </g>`;

        case 'ap':
            return `
            <g id="ap_3d" transform="translate(10, 6)">
                <ellipse cx="70" cy="46" rx="38" ry="6" fill="rgba(0,0,0,0.3)"/>
                <path d="M 46,16 A 26 26 0 0 0 34,30" fill="none" stroke="#38bdf8" stroke-width="2" stroke-linecap="round" opacity="0.8"/>
                <path d="M 38,10 A 38 38 0 0 0 22,30" fill="none" stroke="#0284c7" stroke-width="1.8" stroke-linecap="round" opacity="0.6"/>
                <path d="M 94,16 A 26 26 0 0 1 106,30" fill="none" stroke="#38bdf8" stroke-width="2" stroke-linecap="round" opacity="0.8"/>
                <path d="M 102,10 A 38 38 0 0 1 118,30" fill="none" stroke="#0284c7" stroke-width="1.8" stroke-linecap="round" opacity="0.6"/>
                <rect x="46" y="14" width="48" height="26" rx="13" ry="13" fill="url(#apDomeGrad)" stroke="#2563eb" stroke-width="1.2"/>
                <circle cx="70" cy="27" r="7" fill="#0f172a" stroke="#38bdf8" stroke-width="1.2"/>
                <circle cx="70" cy="27" r="3.5" fill="#22c55e"/>
                <circle cx="70" cy="27" r="1.5" fill="#ffffff"/>
            </g>`;

        case 'converter':
        case 'poe':
            return `
            <g id="cv_3d" transform="translate(10, 6)">
                <ellipse cx="70" cy="46" rx="42" ry="5" fill="rgba(0,0,0,0.3)"/>
                <path d="M 32,20 L 52,10 L 108,10 L 88,20 Z" fill="url(#cvTopGrad)" stroke="#06b6d4" stroke-width="0.6"/>
                <path d="M 88,20 L 108,10 L 108,26 L 88,36 Z" fill="#0e7490" stroke="#155e75" stroke-width="0.6"/>
                <path d="M 32,20 L 88,20 L 88,36 L 32,36 Z" fill="url(#cvFrontGrad)" stroke="#0891b2" stroke-width="0.8"/>
                <rect x="36" y="24" width="8" height="8" rx="1" fill="#0f172a" stroke="#f97316" stroke-width="0.8"/>
                <circle cx="39" cy="28" r="1.2" fill="#f97316"/>
                <circle cx="41" cy="28" r="1.2" fill="#f97316"/>
                <circle cx="50" cy="25" r="1" fill="#22c55e"/>
                <circle cx="50" cy="28" r="1" fill="#38bdf8"/>
                <circle cx="50" cy="31" r="1" fill="#fbbf24"/>
                <rect x="74" y="24" width="9" height="8" rx="1" fill="#0284c7" stroke="#38bdf8" stroke-width="0.6"/>
            </g>`;

        case 'demarcation':
            return `
            <g id="dm_3d" transform="translate(10, 6)">
                <ellipse cx="70" cy="46" rx="46" ry="5" fill="rgba(0,0,0,0.3)"/>
                <path d="M 24,18 L 46,10 L 116,10 L 94,18 Z" fill="#475569" stroke="#94a3b8" stroke-width="0.6"/>
                <path d="M 94,18 L 116,10 L 116,26 L 94,34 Z" fill="#1e293b" stroke="#334155" stroke-width="0.6"/>
                <path d="M 24,18 L 94,18 L 94,34 L 24,34 Z" fill="url(#dmFrontGrad)" stroke="#334155" stroke-width="0.8"/>
                <circle cx="44" cy="26" r="5" fill="none" stroke="#f97316" stroke-width="1.5"/>
                <rect x="54" y="23" width="5" height="6" fill="#22c55e" stroke="#16a34a" stroke-width="0.5"/>
                <rect x="62" y="23" width="5" height="6" fill="#22c55e" stroke="#16a34a" stroke-width="0.5"/>
                <rect x="70" y="23" width="5" height="6" fill="#0284c7" stroke="#0369a1" stroke-width="0.5"/>
                <rect x="78" y="23" width="5" height="6" fill="#0284c7" stroke="#0369a1" stroke-width="0.5"/>
            </g>`;

        default:
            return `
            <g id="gen_3d" transform="translate(10, 6)">
                <ellipse cx="70" cy="46" rx="48" ry="5" fill="rgba(0,0,0,0.3)"/>
                <path d="M 20,20 L 46,10 L 120,10 L 94,20 Z" fill="#334155" stroke="#64748b" stroke-width="0.6"/>
                <path d="M 94,20 L 120,10 L 120,26 L 94,36 Z" fill="#0f172a" stroke="#1e293b" stroke-width="0.6"/>
                <path d="M 20,20 L 94,20 L 94,36 L 20,36 Z" fill="#1e293b" stroke="#475569" stroke-width="0.8"/>
                <circle cx="30" cy="28" r="2" fill="#22c55e"/>
                <circle cx="36" cy="28" r="2" fill="#38bdf8"/>
                <rect x="75" y="24" width="12" height="8" rx="1" fill="#0284c7"/>
            </g>`;
    }
}

function generateVisioSvgStencil(node, isSelected = false) {
    const isDark = (currentTheme === 'theme-dark' || currentTheme === 'theme-blueprint');
    const bgColor = isDark ? '#0f172a' : '#ffffff';
    const borderColor = isSelected ? '#38bdf8' : (isDark ? '#334155' : '#cbd5e1');
    const borderWidth = isSelected ? 2.5 : 1.2;
    const nameColor = isDark ? '#f8fafc' : '#0f172a';
    const subColor = isDark ? '#94a3b8' : '#64748b';
    const badgeBg = isDark ? '#1e293b' : '#f1f5f9';
    const badgeBorder = isDark ? '#334155' : '#e2e8f0';

    const typeConfigs = {
        'router': { grad1: '#dc2626', grad2: '#991b1b', title: 'ROUTER WAN / BORDE' },
        'firewall': { grad1: '#7c3aed', grad2: '#5b21b6', title: 'FIREWALL PERIMETRAL' },
        'switch': { grad1: '#0284c7', grad2: '#0369a1', title: 'SWITCH DE RED' },
        'patch_panel': { grad1: '#d97706', grad2: '#92400e', title: 'PATCH PANEL PASIVO' },
        'server': { grad1: '#059669', grad2: '#065f46', title: 'COMPUTO / CONTROLADOR' },
        'converter': { grad1: '#0891b2', grad2: '#155e75', title: 'CONVERSOR DE MEDIOS' },
        'poe': { grad1: '#0d9488', grad2: '#115e59', title: 'INYECTOR POE' },
        'ap': { grad1: '#2563eb', grad2: '#1d4ed8', title: 'ACCESS POINT WIFI' },
        'demarcation': { grad1: '#475569', grad2: '#334155', title: 'ACOMETIDA FIBRA (ODF)' },
        'drop': { grad1: '#334155', grad2: '#1e293b', title: 'SALIDAS HORIZONTALES' },
        'device': { grad1: '#475569', grad2: '#334155', title: 'EQUIPO DE RED' }
    };

    const cfg = typeConfigs[node.device_type] || typeConfigs['device'];
    const rackText = node.rack ? `${node.rack}${node.ur_rack ? ' · U' + node.ur_rack : ''}` : (node.area || 'Sin Rack');
    const portText = node.ports_count > 0 ? `${node.active_ports || 0}/${node.ports_count} P` : `${node.active_ports || 0} Enl`;
    const ipText = node.ip_address ? `IP: ${node.ip_address}` : (node.location ? node.location.slice(0, 18) : 'Conectado');

    if (currentStencilStyle === 'visio_hardware') {
        // MODO 1: Símbolos de Red Visio (Cisco 3D) - Auténtica Topología de Red
        let displayName = node.id || 'Equipo';
        if (displayName.length > 20) displayName = displayName.slice(0, 19) + '…';

        const svg = `
        <svg xmlns="http://www.w3.org/2000/svg" width="160" height="114" viewBox="0 0 160 114">
          ${getSvgGradientDefs()}

          ${isSelected ? `
          <!-- Visio Selection Box & Handles -->
          <rect x="2" y="2" width="156" height="110" rx="6" fill="rgba(56,189,248,0.08)" stroke="#38bdf8" stroke-width="2" stroke-dasharray="4,2"/>
          <rect x="0" y="0" width="5" height="5" fill="#38bdf8" rx="1"/>
          <rect x="155" y="0" width="5" height="5" fill="#38bdf8" rx="1"/>
          <rect x="0" y="109" width="5" height="5" fill="#38bdf8" rx="1"/>
          <rect x="155" y="109" width="5" height="5" fill="#38bdf8" rx="1"/>
          <rect x="78" y="0" width="4" height="4" fill="#38bdf8" rx="1"/>
          <rect x="78" y="110" width="4" height="4" fill="#38bdf8" rx="1"/>
          ` : ''}

          <!-- Floating Port Count Badge (Top-Right) -->
          <rect x="104" y="3" width="52" height="15" rx="3" fill="${badgeBg}" stroke="${badgeBorder}" stroke-width="0.8"/>
          <circle cx="110" cy="10.5" r="2" fill="#22c55e"/>
          <text x="116" y="13.5" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="8" font-weight="700" fill="${isDark ? '#34d399' : '#059669'}">${portText}</text>

          <!-- 3D Hardware Stencil -->
          ${getNetworkHardwareIconSvg(node.device_type, isDark)}

          <!-- Hostname Label Pill -->
          <rect x="5" y="66" width="150" height="23" rx="4" fill="${bgColor}" stroke="${borderColor}" stroke-width="${borderWidth}"/>
          <text x="80" y="81.5" text-anchor="middle" font-family="'JetBrains Mono', 'Segoe UI Mono', monospace" font-size="10" font-weight="700" fill="${nameColor}">${displayName}</text>

          <!-- Subtitle Tag (Rack & IP) -->
          <rect x="5" y="91" width="150" height="18" rx="3" fill="${badgeBg}" stroke="${badgeBorder}" stroke-width="0.8"/>
          <text x="10" y="103" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="8" font-weight="600" fill="${isDark ? '#38bdf8' : '#0284c7'}">${rackText}</text>
          <text x="150" y="103" text-anchor="end" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="8" font-weight="600" fill="${subColor}">${ipText}</text>
        </svg>`;

        return "data:image/svg+xml;charset=utf-8," + encodeURIComponent(svg);
    } else {
        // MODO 2: Fichas Blueprint Técnicas (Con Miniatura 3D)
        let displayName = node.id || 'Equipo';
        if (displayName.length > 18) displayName = displayName.slice(0, 17) + '…';

        const svg = `
        <svg xmlns="http://www.w3.org/2000/svg" width="240" height="84" viewBox="0 0 240 84">
          ${getSvgGradientDefs()}
          <defs>
            <linearGradient id="hdrGrad" x1="0%" y1="0%" x2="100%" y2="0%">
              <stop offset="0%" stop-color="${cfg.grad1}"/>
              <stop offset="100%" stop-color="${cfg.grad2}"/>
            </linearGradient>
          </defs>
          
          <!-- Card Body -->
          <rect x="2" y="2" width="236" height="80" rx="6" fill="${bgColor}" stroke="${borderColor}" stroke-width="${borderWidth}"/>
          
          <!-- Header Banner -->
          <path d="M 2 8 Q 2 2 8 2 L 232 2 Q 238 2 238 8 L 238 22 L 2 22 Z" fill="url(#hdrGrad)"/>
          
          <!-- Header Title -->
          <text x="8" y="15" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="9" font-weight="800" fill="#ffffff" letter-spacing="0.5">${cfg.title}</text>
          
          <!-- Status LED -->
          <circle cx="228" cy="12" r="3.5" fill="#22c55e" stroke="#16a34a" stroke-width="1"/>

          <!-- 3D Hardware Miniature Icon on Left -->
          <g transform="translate(6, 22) scale(0.55)">
              ${getNetworkHardwareIconSvg(node.device_type, isDark)}
          </g>
          
          <!-- Device Name Monospace -->
          <text x="88" y="38" font-family="'JetBrains Mono', 'Segoe UI Mono', monospace" font-size="11" font-weight="700" fill="${nameColor}">${displayName}</text>
          
          <!-- IP / Area Subtext -->
          <text x="88" y="52" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="8.5" font-weight="500" fill="${subColor}">${ipText}</text>
          
          <!-- Rack Badge -->
          <rect x="88" y="60" width="70" height="16" rx="3" fill="${badgeBg}" stroke="${badgeBorder}" stroke-width="0.8"/>
          <text x="92" y="71" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="7.5" font-weight="600" fill="${isDark ? '#38bdf8' : '#0284c7'}">${rackText.slice(0, 12)}</text>
          
          <!-- Ports Badge -->
          <rect x="162" y="60" width="72" height="16" rx="3" fill="${badgeBg}" stroke="${badgeBorder}" stroke-width="0.8"/>
          <text x="166" y="71" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="7.5" font-weight="600" fill="${isDark ? '#34d399' : '#059669'}">${portText}</text>
        </svg>`;

        return "data:image/svg+xml;charset=utf-8," + encodeURIComponent(svg);
    }
}

// Actualiza sólo los estilos de selección de los nodos sin resetear la vista
function updateNodeSelectionStyles() {
    if (!network) return;
    const nodesDataset = network.body.data.nodes;
    const updates = [];
    nodesDataset.forEach(n => {
        const rawNode = n.raw || rawNodesData.find(rn => rn.id === n.id);
        if (rawNode) {
            updates.push({
                id: n.id,
                image: generateVisioSvgStencil(rawNode, n.id === selectedNodeId)
            });
        }
    });
    nodesDataset.update(updates);
}

// 4. Renderizar Diagrama en Vis-Network
function renderVisioNetwork() {
    const container = document.getElementById('network_canvas');
    container.innerHTML = '';

    // Filtrar nodos por categoría si aplica
    let filteredNodes = rawNodesData;
    if (activeCategoryFilter !== 'all') {
        filteredNodes = rawNodesData.filter(n => n.device_type === activeCategoryFilter);
    }

    const visibleNodeIds = new Set(filteredNodes.map(n => n.id));
    const filteredEdges = rawEdgesData.filter(e => visibleNodeIds.has(e.from) && visibleNodeIds.has(e.to));

    // Crear DataSet de Nodos
    const visNodes = filteredNodes.map(n => {
        return {
            id: n.id,
            shape: 'image',
            image: generateVisioSvgStencil(n, n.id === selectedNodeId),
            level: n.tier !== undefined ? n.tier : 2,
            shapeProperties: {
                useImageSize: true,
                interpolation: false
            },
            raw: n
        };
    });

    // Crear DataSet de Conectores (Edges)
    const visEdges = filteredEdges.map(e => {
        const isFiber = e.is_fiber || (e.cable_type && e.cable_type.toLowerCase().includes('fibra'));
        const isMulti = e.count > 1;
        const color = isFiber ? '#f97316' : (isMulti ? '#fbbf24' : '#38bdf8');
        const countLabel = isMulti ? `[${e.count}x Enlaces]` : '';

        return {
            id: e.id,
            from: e.from,
            to: e.to,
            label: countLabel,
            width: isMulti ? Math.min(3 + e.count * 1.5, 8) : 2,
            color: {
                color: color,
                highlight: '#ffffff',
                hover: '#fbbf24',
                opacity: 0.85
            },
            font: {
                color: '#f8fafc',
                size: 9,
                face: 'Inter, sans-serif',
                background: '#0f172a',
                strokeWidth: 0
            },
            smooth: {
                type: currentLayout.startsWith('hierarchical') ? 'cubicBezier' : 'continuous',
                roundness: 0.5
            },
            raw: e
        };
    });

    const data = {
        nodes: new vis.DataSet(visNodes),
        edges: new vis.DataSet(visEdges)
    };

    // Configuración de Opciones y Física adaptada al estilo de stencil
    const isHardware = (currentStencilStyle === 'visio_hardware');
    let options = {
        interaction: {
            hover: true,
            tooltipDelay: 150,
            zoomView: true,
            dragView: true,
            navigationButtons: false
        }
    };

    if (currentLayout === 'hierarchical_ud') {
        options.layout = {
            hierarchical: {
                direction: 'UD',
                sortMethod: 'directed',
                levelSeparation: isHardware ? 190 : 170,
                nodeSpacing: isHardware ? 180 : 250,
                treeSpacing: isHardware ? 200 : 260,
                blockShifting: true,
                edgeMinimization: true
            }
        };
        options.physics = {
            enabled: false
        };
    } else if (currentLayout === 'hierarchical_lr') {
        options.layout = {
            hierarchical: {
                direction: 'LR',
                sortMethod: 'directed',
                levelSeparation: isHardware ? 240 : 280,
                nodeSpacing: isHardware ? 140 : 160,
                treeSpacing: isHardware ? 180 : 200,
                blockShifting: true,
                edgeMinimization: true
            }
        };
        options.physics = {
            enabled: false
        };
    } else {
        // Free layout with controlled stabilization
        options.layout = {
            hierarchical: { enabled: false }
        };
        options.physics = {
            enabled: !isPhysicsLocked,
            stabilization: {
                enabled: true,
                iterations: 200,
                updateInterval: 25
            },
            barnesHut: {
                gravitationalConstant: isHardware ? -3500 : -4500,
                centralGravity: 0.25,
                springLength: isHardware ? 150 : 200,
                springConstant: 0.04,
                damping: 0.09
            }
        };
    }

    network = new vis.Network(container, data, options);

    // Eventos
    network.on("click", function (params) {
        if (params.nodes.length > 0) {
            const nodeId = params.nodes[0];
            const nodeObj = rawNodesData.find(n => n.id === nodeId);
            if (nodeObj) {
                selectedNodeId = nodeId;
                updateNodeSelectionStyles();
                spotlightNeighborhood(nodeId);
                showNodeInspector(nodeObj);
            }
        } else if (params.edges.length > 0) {
            const edgeId = params.edges[0];
            const edgeObj = rawEdgesData.find(e => e.id === edgeId);
            if (edgeObj) {
                showEdgeInspector(edgeObj);
            }
        } else {
            // Clic en vacío: deseleccionar
            selectedNodeId = null;
            updateNodeSelectionStyles();
            clearSpotlight();
        }
    });

    network.on("doubleClick", function (params) {
        if (params.nodes.length > 0) {
            const nodeId = params.nodes[0];
            const nodeObj = rawNodesData.find(n => n.id === nodeId);
            if (nodeObj) showNodeDetailModal(nodeObj);
        } else if (params.edges.length > 0) {
            const edgeId = params.edges[0];
            const edgeObj = rawEdgesData.find(e => e.id === edgeId);
            if (edgeObj) showEdgeDetailModal(edgeObj);
        }
    });

    // Fit canvas after stabilization
    network.once("stabilizationIterationsDone", function () {
        network.setOptions({ physics: { enabled: false } });
        network.fit({ animation: { duration: 600, easingFunction: 'easeInOutQuad' } });
    });
}

// 5. Spotlight / Resaltado Inteligente de Vecinos
function spotlightNeighborhood(nodeId) {
    if (!network) return;

    const connectedNodes = new Set(network.getConnectedNodes(nodeId));
    connectedNodes.add(nodeId);
    const connectedEdges = new Set(network.getConnectedEdges(nodeId));

    const nodesDataset = network.body.data.nodes;
    const edgesDataset = network.body.data.edges;

    const updateNodes = [];
    nodesDataset.forEach(n => {
        const isNeighbor = connectedNodes.has(n.id);
        updateNodes.push({
            id: n.id,
            opacity: isNeighbor ? 1.0 : 0.15
        });
    });
    nodesDataset.update(updateNodes);

    const updateEdges = [];
    edgesDataset.forEach(e => {
        const isConnected = connectedEdges.has(e.id);
        updateEdges.push({
            id: e.id,
            opacity: isConnected ? 1.0 : 0.08,
            width: isConnected ? 3 : 1
        });
    });
    edgesDataset.update(updateEdges);
}

function clearSpotlight() {
    if (!network) return;
    const nodesDataset = network.body.data.nodes;
    const edgesDataset = network.body.data.edges;

    const updateNodes = [];
    nodesDataset.forEach(n => updateNodes.push({ id: n.id, opacity: 1.0 }));
    nodesDataset.update(updateNodes);

    const updateEdges = [];
    edgesDataset.forEach(e => updateEdges.push({ id: e.id, opacity: 0.85, width: e.raw && e.raw.count > 1 ? 4 : 2 }));
    edgesDataset.update(updateEdges);
}

// 6. Controles de Zoom, Centrado y Pantalla Completa
function zoomIn() {
    if (!network) return;
    const scale = network.getScale();
    network.moveTo({ scale: scale * 1.3, animation: { duration: 250 } });
}

function zoomOut() {
    if (!network) return;
    const scale = network.getScale();
    network.moveTo({ scale: scale / 1.3, animation: { duration: 250 } });
}

function fitGraph() {
    if (!network) return;
    network.fit({ animation: { duration: 500, easingFunction: 'easeInOutQuad' } });
}

function toggleFullscreenCanvas() {
    const wrap = document.getElementById('canvas_wrapper');
    wrap.classList.toggle('fullscreen-mode');
    setTimeout(() => {
        if (network) network.fit();
    }, 200);
}

// 7. Temas de Fondo y Cuadrícula Visio
function setCanvasTheme(themeName) {
    currentTheme = themeName;
    const wrap = document.getElementById('canvas_wrapper');
    wrap.classList.remove('theme-dark', 'theme-light', 'theme-blueprint');
    wrap.classList.add(themeName);

    document.getElementById('btn_theme_dark').classList.toggle('active', themeName === 'theme-dark');
    document.getElementById('btn_theme_light').classList.toggle('active', themeName === 'theme-light');
    document.getElementById('btn_theme_blueprint').classList.toggle('active', themeName === 'theme-blueprint');

    renderVisioNetwork();
}

function toggleGrid() {
    isGridVisible = !isGridVisible;
    const wrap = document.getElementById('canvas_wrapper');
    wrap.classList.toggle('no-grid', !isGridVisible);
    document.getElementById('btn_toggle_grid').classList.toggle('active', isGridVisible);
}

function togglePhysicsLock() {
    isPhysicsLocked = !isPhysicsLocked;
    const btn = document.getElementById('btn_physics_lock');
    btn.classList.toggle('active', isPhysicsLocked);
    btn.innerHTML = isPhysicsLocked ? '<i class="fas fa-lock"></i>' : '<i class="fas fa-lock-open"></i>';

    if (network) {
        network.setOptions({ physics: { enabled: !isPhysicsLocked } });
    }
}

function changeTopologyLayout(layoutVal) {
    currentLayout = layoutVal;
    renderVisioNetwork();
}

// 8. Búsqueda y Spotlight en Vivo
function onSearchDevice(query) {
    if (!query || query.trim() === '') {
        clearSpotlight();
        return;
    }
    const q = query.toLowerCase().trim();
    const found = rawNodesData.find(n => n.id.toLowerCase().includes(q) || (n.ip_address && n.ip_address.includes(q)));
    if (found && network) {
        spotlightNeighborhood(found.id);
        network.focus(found.id, {
            scale: 1.25,
            animation: { duration: 500, easingFunction: 'easeInOutQuad' }
        });
        showNodeInspector(found);
    }
}

function clearSearch() {
    document.getElementById('input_device_search').value = '';
    clearSpotlight();
}

// 9. Filtro por Categorías
function filterByCategory(cat) {
    activeCategoryFilter = cat;
    document.querySelectorAll('.filter-chip').forEach(el => el.classList.remove('active'));
    const chip = document.getElementById(`chip_${cat}`);
    if (chip) chip.classList.add('active');
    renderVisioNetwork();
}

// 10. Actualización de Tarjetas y Contadores
function updateStatsAndCounters(nodes, edges) {
    document.getElementById('stat_total_nodes').textContent = nodes.length;
    document.getElementById('stat_total_edges').textContent = edges.length;

    let multiCount = 0;
    let portsMapped = 0;

    const counts = {
        router: 0,
        firewall: 0,
        switch: 0,
        patch_panel: 0,
        server: 0,
        converter: 0
    };

    nodes.forEach(n => {
        if (counts[n.device_type] !== undefined) counts[n.device_type]++;
        else if (n.device_type === 'poe') counts.converter++;
        portsMapped += (n.active_ports || 0);
    });

    edges.forEach(e => {
        if (e.count > 1) multiCount++;
    });

    document.getElementById('stat_multi_edges').textContent = multiCount;
    document.getElementById('stat_total_ports').textContent = portsMapped;

    document.getElementById('count_all').textContent = nodes.length;
    document.getElementById('count_router').textContent = counts.router;
    document.getElementById('count_firewall').textContent = counts.firewall;
    document.getElementById('count_switch').textContent = counts.switch;
    document.getElementById('count_patch').textContent = counts.patch_panel;
    document.getElementById('count_server').textContent = counts.server;
    document.getElementById('count_cvm').textContent = counts.converter;
}

// 11. Panel de Inspección Lateral (Drawer)
function showNodeInspector(node) {
    const drawer = document.getElementById('inspector_drawer');
    document.getElementById('inspector_title').innerHTML = `Detalle: <span class="text-cyan">${node.id}</span>`;

    const connectedEdges = rawEdgesData.filter(e => e.from === node.id || e.to === node.id);

    let html = `
        <div class="mb-3 text-center p-3" style="background: #0f172a; border-radius: 8px; border: 1px solid #334155;">
            <div class="d-flex justify-content-center mb-2">
                <img src="${generateVisioSvgStencil(node, false)}" style="width: 140px; height: auto;" alt="${node.id}">
            </div>
            <div class="font-weight-bold h6 m-0 text-white">${node.id}</div>
            <div class="badge badge-info px-2 py-1 mt-1 text-uppercase">${node.device_type_raw || 'Hardware'}</div>
        </div>

        <h6 class="font-weight-bold text-navy small mb-2"><i class="fas fa-info-circle mr-1 text-primary"></i>Propiedades Físicas:</h6>
        <table class="table table-sm table-bordered small mb-3">
            <tr><th class="bg-light" style="width: 40%;">Ubicación:</th><td>${node.location}</td></tr>
            <tr><th class="bg-light">Área / Zona:</th><td>${node.area || 'Datacenter'}</td></tr>
            <tr><th class="bg-light">Rack / UR:</th><td>${node.rack || '-'} (U${node.ur_rack || '-'})</td></tr>
            <tr><th class="bg-light">Dirección IP:</th><td><code>${node.ip_address || 'No registrada'}</code></td></tr>
            <tr><th class="bg-light">Capacidad Puertos:</th><td><strong>${node.active_ports || 0}</strong> activos / <strong>${node.ports_count || 0}</strong> total</td></tr>
        </table>

        <h6 class="font-weight-bold text-navy small mb-2"><i class="fas fa-link mr-1 text-info"></i>Enlaces Vecinos (${connectedEdges.length}):</h6>
        <div class="list-group list-group-flush border rounded mb-3" style="max-height: 240px; overflow-y: auto;">
    `;

    connectedEdges.forEach(e => {
        const neighbor = (e.from === node.id) ? e.to : e.from;
        const isFiber = e.is_fiber || (e.cable_type && e.cable_type.toLowerCase().includes('fibra'));
        html += `
            <div class="list-group-item list-group-item-action p-2 d-flex justify-content-between align-items-center" onclick="showEdgeInspectorById('${e.id}')" style="cursor: pointer;">
                <div>
                    <div class="font-weight-bold text-primary small">${neighbor}</div>
                    <small class="text-muted">${e.cable_type} (${e.count} enlace${e.count > 1 ? 's' : ''})</small>
                </div>
                <span class="badge ${isFiber ? 'badge-warning' : 'badge-info'}">${isFiber ? 'Fibra' : 'Cobre'}</span>
            </div>
        `;
    });

    html += `
        </div>
        <button class="btn btn-sm btn-primary btn-block font-weight-bold" onclick="showNodeDetailModalById('${node.id}')">
            <i class="fas fa-table mr-1"></i> Ver Tabla Completa de Puertos
        </button>
    `;

    document.getElementById('inspector_content').innerHTML = html;
    drawer.classList.add('open');
}

function showEdgeInspector(edge) {
    const drawer = document.getElementById('inspector_drawer');
    document.getElementById('inspector_title').innerHTML = `Interconexión Física`;

    const isFiber = edge.is_fiber || (edge.cable_type && edge.cable_type.toLowerCase().includes('fibra'));

    let html = `
        <div class="alert alert-info py-2 px-3 mb-3 small d-flex justify-content-between align-items-center">
            <span><i class="fas fa-random mr-1"></i> ${edge.count} enlace(s) físico(s)</span>
            <span class="badge ${isFiber ? 'badge-warning' : 'badge-primary'}">${isFiber ? 'Fibra Óptica' : 'UTP Cobre'}</span>
        </div>

        <div class="text-center p-2 mb-3 bg-light rounded border">
            <div class="font-weight-bold text-primary">${edge.from}</div>
            <div class="text-muted small">&uarr;&darr;</div>
            <div class="font-weight-bold text-success">${edge.to}</div>
        </div>

        <h6 class="font-weight-bold text-navy small mb-2"><i class="fas fa-list mr-1 text-primary"></i>Puertos Interconectados:</h6>
        <div class="table-responsive">
            <table class="table table-sm table-bordered small">
                <thead class="bg-light">
                    <tr><th>#</th><th>Puerto ${edge.from}</th><th>Puerto ${edge.to}</th><th>Medio</th></tr>
                </thead>
                <tbody>
    `;

    edge.details.forEach((d, i) => {
        html += `
            <tr>
                <td class="text-center font-weight-bold">${i + 1}</td>
                <td><span class="badge badge-primary px-2">${d.src_port || 'P-Auto'}</span></td>
                <td><span class="badge badge-success px-2">${d.dest_port || 'P-Auto'}</span></td>
                <td><span class="badge badge-light border">${d.cable_type || 'Cat6A'}</span></td>
            </tr>
        `;
    });

    html += `
                </tbody>
            </table>
        </div>
        <button class="btn btn-sm btn-outline-primary btn-block font-weight-bold mt-2" onclick="showEdgeDetailModalById('${edge.id}')">
            <i class="fas fa-expand mr-1"></i> Ver Detalle en Modal Extendido
        </button>
    `;

    document.getElementById('inspector_content').innerHTML = html;
    drawer.classList.add('open');
}

function showEdgeInspectorById(edgeId) {
    const edge = rawEdgesData.find(e => e.id === edgeId);
    if (edge) showEdgeInspector(edge);
}

function closeInspector() {
    document.getElementById('inspector_drawer').classList.remove('open');
}

// 12. Modales Detallados
function showNodeDetailModal(node) {
    document.getElementById('modalDetailTitle').innerHTML = `<i class="fas fa-server text-cyan mr-2"></i>Dossier Técnico: ${node.id}`;
    const connectedEdges = rawEdgesData.filter(e => e.from === node.id || e.to === node.id);

    let html = `
        <div class="row mb-3">
            <div class="col-md-6">
                <ul class="list-group list-group-flush small border rounded">
                    <li class="list-group-item d-flex justify-content-between"><strong>Equipo:</strong> <span class="font-weight-bold">${node.id}</span></li>
                    <li class="list-group-item d-flex justify-content-between"><strong>Tipo de Dispositivo:</strong> <span class="badge badge-info">${node.device_type_raw}</span></li>
                    <li class="list-group-item d-flex justify-content-between"><strong>Dirección IP:</strong> <code>${node.ip_address || 'N/A'}</code></li>
                </ul>
            </div>
            <div class="col-md-6">
                <ul class="list-group list-group-flush small border rounded">
                    <li class="list-group-item d-flex justify-content-between"><strong>Ubicación:</strong> <span>${node.location}</span></li>
                    <li class="list-group-item d-flex justify-content-between"><strong>Rack / UR:</strong> <span>${node.rack || '-'} (U${node.ur_rack || '-'})</span></li>
                    <li class="list-group-item d-flex justify-content-between"><strong>Interconexiones Vecinas:</strong> <span class="badge badge-success">${connectedEdges.length} Vecinos</span></li>
                </ul>
            </div>
        </div>

        <h6 class="font-weight-bold text-navy mb-2"><i class="fas fa-network-wired text-primary mr-1"></i>Detalle de Puertos y Equipos Conectados:</h6>
        <div class="table-responsive">
            <table class="table table-sm table-hover table-bordered small">
                <thead class="bg-light">
                    <tr>
                        <th>#</th>
                        <th>Puerto Local (${node.id})</th>
                        <th>Equipo Destino</th>
                        <th>Puerto Destino</th>
                        <th>Tipo de Cable</th>
                        <th>VLAN</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
    `;

    let rowIdx = 1;
    connectedEdges.forEach(e => {
        const neighbor = (e.from === node.id) ? e.to : e.from;
        e.details.forEach(d => {
            const localPort = (d.src_device === node.id) ? d.src_port : d.dest_port;
            const remotePort = (d.src_device === node.id) ? d.dest_port : d.src_port;
            html += `
                <tr>
                    <td class="text-center font-weight-bold">${rowIdx++}</td>
                    <td><span class="badge badge-primary px-2 py-1">${localPort || 'P-Auto'}</span></td>
                    <td class="font-weight-bold text-navy">${neighbor}</td>
                    <td><span class="badge badge-success px-2 py-1">${remotePort || 'P-Auto'}</span></td>
                    <td><span class="badge badge-light border">${d.cable_type}</span></td>
                    <td><span class="badge badge-info">${d.vlan ? 'VLAN ' + d.vlan : 'N/A'}</span></td>
                    <td><span class="badge badge-success">${d.status || 'Activo'}</span></td>
                </tr>
            `;
        });
    });

    html += `
                </tbody>
            </table>
        </div>
    `;

    document.getElementById('modalDetailContent').innerHTML = html;
    $('#modalConnectionDetail').modal('show');
}

function showNodeDetailModalById(nodeId) {
    const node = rawNodesData.find(n => n.id === nodeId);
    if (node) showNodeDetailModal(node);
}

function showEdgeDetailModal(edge) {
    document.getElementById('modalDetailTitle').innerHTML = `<i class="fas fa-project-diagram text-warning mr-2"></i>Interconexión: ${edge.from} &larr;&rarr; ${edge.to}`;

    let html = `
        <div class="alert alert-info py-2 px-3 mb-3 small d-flex justify-content-between align-items-center">
            <span><i class="fas fa-info-circle mr-1"></i> Se registraron <strong>${edge.count} enlace(s) físicos</strong> entre estos equipos.</span>
            <span class="badge badge-dark">${edge.from} &hArr; ${edge.to}</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover table-bordered small">
                <thead class="bg-light text-navy">
                    <tr>
                        <th>#</th>
                        <th>Puerto Origen (${edge.from})</th>
                        <th>Puerto Destino (${edge.to})</th>
                        <th>Tipo de Cable</th>
                        <th>Medio</th>
                        <th>VLAN</th>
                        <th>Patch Panel / Trayectoria</th>
                    </tr>
                </thead>
                <tbody>
    `;

    edge.details.forEach((d, idx) => {
        const isFiber = d.is_fiber || (d.cable_type && d.cable_type.toLowerCase().includes('fibra'));
        html += `
            <tr>
                <td class="text-center font-weight-bold">${idx + 1}</td>
                <td><span class="badge badge-primary px-2 py-1"><i class="fas fa-plug mr-1"></i>${d.src_port || 'P-Auto'}</span></td>
                <td><span class="badge badge-success px-2 py-1"><i class="fas fa-plug mr-1"></i>${d.dest_port || 'P-Auto'}</span></td>
                <td><span class="badge badge-light border">${d.cable_type || 'Cat6A'}</span></td>
                <td><span class="badge ${isFiber ? 'badge-warning' : 'badge-primary'}">${isFiber ? 'Fibra' : 'Cobre'}</span></td>
                <td><span class="badge badge-info">${d.vlan ? 'VLAN ' + d.vlan : 'N/A'}</span></td>
                <td class="text-muted small">${d.patch_panel_src || 'Conexión Directa'}</td>
            </tr>
        `;
    });

    html += `
                </tbody>
            </table>
        </div>
    `;

    document.getElementById('modalDetailContent').innerHTML = html;
    $('#modalConnectionDetail').modal('show');
}

function showEdgeDetailModalById(edgeId) {
    const edge = rawEdgesData.find(e => e.id === edgeId);
    if (edge) showEdgeDetailModal(edge);
}

// 13. Exportación PNG de Alta Resolución con Membrete Técnico de Ingeniería
function exportHighResBlueprint() {
    if (!network) return;
    const canvas = document.querySelector('#network_canvas canvas');
    if (!canvas) return;

    // Crear canvas compuesto con membrete oficial
    const exportCanvas = document.createElement('canvas');
    const ctx = exportCanvas.getContext('2d');

    const padding = 40;
    const titleBlockHeight = 90;

    exportCanvas.width = canvas.width;
    exportCanvas.height = canvas.height + titleBlockHeight;

    // Fondo según tema
    ctx.fillStyle = currentTheme === 'theme-light' ? '#f8fafc' : (currentTheme === 'theme-blueprint' ? '#072746' : '#090d16');
    ctx.fillRect(0, 0, exportCanvas.width, exportCanvas.height);

    // Dibujar diagrama principal
    ctx.drawImage(canvas, 0, 0);

    // Dibujar Membrete Técnico de Ingeniería en la parte inferior
    const tbY = canvas.height;
    ctx.fillStyle = '#0f172a';
    ctx.fillRect(0, tbY, exportCanvas.width, titleBlockHeight);

    ctx.strokeStyle = '#38bdf8';
    ctx.lineWidth = 3;
    ctx.beginPath();
    ctx.moveTo(0, tbY);
    ctx.lineTo(exportCanvas.width, tbY);
    ctx.stroke();

    // Texto del Membrete
    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 16px Inter, sans-serif';
    ctx.fillText(`DIAGRAMA TÉCNICO DE TOPOLOGÍA DE RED · ${CLIENT_FILTER}`, 30, tbY + 30);

    const loc = document.getElementById('sel_location').value || 'Todas las Ubicaciones';
    ctx.fillStyle = '#94a3b8';
    ctx.font = '12px Inter, sans-serif';
    ctx.fillText(`Ubicación: ${loc}  |  Fecha: ${new Date().toLocaleDateString('es-ES')}  |  Equipos: ${rawNodesData.length}  |  Interconexiones: ${rawEdgesData.length}`, 30, tbY + 55);

    ctx.fillStyle = '#38bdf8';
    ctx.font = 'bold 11px monospace';
    ctx.fillText('CMDB VILASECA · SONDA NETWORK ENGINEERING SYSTEM', 30, tbY + 75);

    // Descargar imagen
    const image = exportCanvas.toDataURL("image/png");
    const link = document.createElement('a');
    link.download = `Topologia_Red_${CLIENT_FILTER}_${new Date().toISOString().slice(0,10)}.png`;
    link.href = image;
    link.click();
}

// 14. Exportar a Visio / Draw.io (.drawio XML)
function exportVisioDrawio() {
    if (rawNodesData.length === 0) {
        Swal.fire('Atención', 'No hay nodos cargados para exportar.', 'warning');
        return;
    }

    // Generar XML en formato Draw.io / Visio estándar
    let xml = `<mxfile host="app.diagrams.net" modified="${new Date().toISOString()}" agent="CMDB-Visio" version="20.0.0" type="device">\n`;
    xml += `  <diagram id="cmdb-topology" name="Topología ${CLIENT_FILTER}">\n`;
    xml += `    <mxGraphModel dx="1422" dy="794" grid="1" gridSize="10" guides="1" tooltips="1" connect="1" arrows="1" fold="1" page="1" pageScale="1" pageWidth="1654" pageHeight="1169" math="0" shadow="0">\n`;
    xml += `      <root>\n`;
    xml += `        <mxCell id="0" />\n`;
    xml += `        <mxCell id="1" parent="0" />\n`;

    // Map positions from vis-network
    const positions = network ? network.getPositions() : {};

    rawNodesData.forEach((n, idx) => {
        const pos = positions[n.id] || { x: 100 + (idx % 5) * 260, y: 100 + Math.floor(idx / 5) * 160 };
        const x = Math.round(pos.x + 800);
        const y = Math.round(pos.y + 400);

        let shapeStyle = '';
        let width = 140;
        let height = 80;
        if (n.device_type === 'router') {
            shapeStyle = 'shape=mxgraph.cisco.routers.router;fillColor=#dc2626;strokeColor=#ffffff;';
            width = 110; height = 90;
        } else if (n.device_type === 'firewall') {
            shapeStyle = 'shape=mxgraph.cisco.firewalls.firewall;fillColor=#7c3aed;strokeColor=#ffffff;';
            width = 130; height = 90;
        } else if (n.device_type === 'switch') {
            shapeStyle = 'shape=mxgraph.cisco.switches.workgroup_switch;fillColor=#0284c7;strokeColor=#ffffff;';
            width = 130; height = 80;
        } else if (n.device_type === 'patch_panel') {
            shapeStyle = 'shape=mxgraph.cisco.switches.patch_panel;fillColor=#d97706;strokeColor=#ffffff;';
            width = 150; height = 65;
        } else if (n.device_type === 'server') {
            shapeStyle = 'shape=mxgraph.cisco.servers.standard_host;fillColor=#059669;strokeColor=#ffffff;';
            width = 110; height = 100;
        } else if (n.device_type === 'ap') {
            shapeStyle = 'shape=mxgraph.cisco.wireless.access_point;fillColor=#2563eb;strokeColor=#ffffff;';
            width = 100; height = 90;
        } else if (n.device_type === 'demarcation') {
            shapeStyle = 'shape=mxgraph.cisco.modems_and_phones.optical_services_router;fillColor=#475569;strokeColor=#ffffff;';
            width = 120; height = 80;
        } else {
            shapeStyle = 'shape=mxgraph.cisco.switches.workgroup_switch;fillColor=#0284c7;strokeColor=#ffffff;';
            width = 130; height = 80;
        }

        const label = `${n.id}&#xa;(${n.device_type_raw || 'Red'})&#xa;${n.ip_address || ''}`;
        xml += `        <mxCell id="node_${idx}" value="${label}" style="verticalLabelPosition=bottom;html=1;verticalAlign=top;aspect=fixed;align=center;pointerEvents=1;${shapeStyle}fontColor=#ffffff;fontStyle=1;fontSize=10;" vertex="1" parent="1">\n`;
        xml += `          <mxGeometry x="${x}" y="${y}" width="${width}" height="${height}" as="geometry" />\n`;
        xml += `        </mxCell>\n`;
    });

    const nodeIndexMap = {};
    rawNodesData.forEach((n, idx) => { nodeIndexMap[n.id] = `node_${idx}`; });

    rawEdgesData.forEach((e, idx) => {
        const srcId = nodeIndexMap[e.from];
        const destId = nodeIndexMap[e.to];
        if (srcId && destId) {
            const isFiber = e.is_fiber || (e.cable_type && e.cable_type.toLowerCase().includes('fibra'));
            const edgeColor = isFiber ? '#f97316' : '#38bdf8';
            xml += `        <mxCell id="edge_${idx}" value="${e.count > 1 ? e.count + ' Enlaces' : ''}" style="edgeStyle=orthogonalEdgeStyle;rounded=0;orthogonalLoop=1;jettySize=auto;html=1;strokeColor=${edgeColor};strokeWidth=2;fontColor=#ffffff;" edge="1" parent="1" source="${srcId}" target="${destId}">\n`;
            xml += `          <mxGeometry relative="1" as="geometry" />\n`;
            xml += `        </mxCell>\n`;
        }
    });

    xml += `      </root>\n`;
    xml += `    </mxGraphModel>\n`;
    xml += `  </diagram>\n`;
    xml += `</mxfile>`;

    // Descargar archivo .drawio
    const blob = new Blob([xml], { type: 'application/vnd.jgraph.mxfile' });
    const link = document.createElement('a');
    link.download = `Topologia_Visio_${CLIENT_FILTER}_${new Date().toISOString().slice(0,10)}.drawio`;
    link.href = URL.createObjectURL(blob);
    link.click();

    Swal.fire({
        title: 'Modelo Visio Generado',
        html: `Se ha descargado el archivo <strong>Topologia_Visio_${CLIENT_FILTER}.drawio</strong>.<br><br>¿Deseas abrir el <strong>Módulo de Visio</strong> para visualizarlo e interactuar con él?`,
        icon: 'success',
        showCancelButton: true,
        confirmButtonColor: '#0284c7',
        confirmButtonText: '<i class="fas fa-external-link-alt mr-1"></i> Abrir en Editor Visio',
        cancelButtonText: 'Permanecer aquí'
    }).then((result) => {
        if (result.isConfirmed) {
            window.open('visio.php', '_blank');
        }
    });
}
</script>

<?php include 'partials/footer.php'; ?>

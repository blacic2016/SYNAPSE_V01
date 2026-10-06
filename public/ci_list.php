<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/permissions_helper.php';

require_login();
if (!has_module_access('ci_list')) {
    header("Location: dashboard.php");
    exit();
}

$page_title = 'Inventario CMDB';
$hide_content_header = true;
require_once __DIR__ . '/partials/header.php';

$pdo = getPDO();

$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$category_name_title = 'Todos los CIs';

if ($category_id > 0) {
    $stmt_cat = $pdo->prepare("SELECT name FROM ci_categories WHERE id = ?");
    $stmt_cat->execute([$category_id]);
    $cat = $stmt_cat->fetch(PDO::FETCH_ASSOC);
    if ($cat) {
        $category_name_title = $cat['name'];
    }
}

// Métricas clave (KPIs) instantáneas para la cabecera
$total_cis_count = (int)$pdo->query("SELECT COUNT(*) FROM ci_instances")->fetchColumn();
$total_cats_count = (int)$pdo->query("SELECT COUNT(*) FROM ci_categories")->fetchColumn();
$cis_with_ip_count = (int)$pdo->query("SELECT COUNT(*) FROM ci_instances WHERE ip_address IS NOT NULL AND TRIM(ip_address) != ''")->fetchColumn();
$cis_zabbix_count = (int)$pdo->query("SELECT COUNT(*) FROM ci_instances WHERE source = 'zabbix' OR (zabbix_host_id IS NOT NULL AND zabbix_host_id > 0)")->fetchColumn();

// CIs iniciales para renderizado inmediato en servidor
$stmt_initial_cis = $pdo->query("
    SELECT i.*, c.name as category_name, c.icon as category_icon, u.username as creator_name, p.hostname as parent_ci_name
    FROM ci_instances i 
    JOIN ci_categories c ON i.category_id = c.id 
    LEFT JOIN users u ON i.created_by = u.id
    LEFT JOIN ci_instances p ON i.parent_ci_id = p.id
    ORDER BY i.hostname ASC
");
$initial_cis = $stmt_initial_cis->fetchAll(PDO::FETCH_ASSOC);

// Categorías completas con conteo directo
$stmt_all_cats = $pdo->query("
    SELECT c.*, (SELECT COUNT(*) FROM ci_instances WHERE category_id = c.id) as direct_ci_count, u.username as creator_name 
    FROM ci_categories c 
    LEFT JOIN users u ON c.created_by = u.id 
    ORDER BY c.name ASC
");
$initial_categories = $stmt_all_cats->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Contenedor Principal con Distribución y Estilos Premium -->
<div class="container-fluid pt-3 pb-5 px-3 px-md-4">
    
    <!-- Barra Superior: Título, Breadcrumb y Botones de Acción Global -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-2 border-bottom">
        <div class="mb-2 mb-md-0">
            <div class="d-flex align-items-center">
                <div class="ci-header-icon mr-3">
                    <i class="fas fa-cubes text-primary fa-2x"></i>
                </div>
                <div>
                    <h4 class="font-weight-bold mb-0 text-dark">Inventario CMDB</h4>
                    <p class="text-muted small mb-0">Gestión unificada de Elementos de Configuración, dependencias y relaciones técnicas</p>
                </div>
            </div>
        </div>
        <div class="d-flex align-items-center flex-wrap gap-2">
            <a href="ci_business_view.php" class="btn btn-outline-info shadow-sm mr-2 btn-action-top">
                <i class="fas fa-project-diagram mr-1"></i> Business View
            </a>
            <?php if (has_role('SUPER_ADMIN')): ?>
                <a href="ci_categories.php" class="btn btn-outline-secondary shadow-sm mr-2 btn-action-top">
                    <i class="fas fa-layer-group mr-1"></i> Categorías CMDB
                </a>
            <?php endif; ?>
            <button type="button" id="btn-nuevo-ci" onclick="openCreateCIModal(<?php echo $category_id; ?>)" class="btn btn-success shadow-sm btn-action-top font-weight-bold">
                <i class="fas fa-plus-circle mr-1.5"></i> Nuevo CI
            </button>
        </div>
    </div>

    <!-- Fila de Tarjetas de Métricas (KPIs Interactivos) -->
    <div class="row mb-3" id="kpi-cards-row">
        <!-- KPI 1: Total CIs -->
        <div class="col-xl-3 col-sm-6 mb-2 mb-xl-0">
            <div class="kpi-card shadow-sm cursor-pointer kpi-active" id="kpi-card-all" onclick="applyQuickFilter('all')" title="Mostrar todos los CIs">
                <div class="kpi-body d-flex align-items-center">
                    <div class="kpi-icon-wrapper bg-primary-soft text-primary mr-3">
                        <i class="fas fa-server"></i>
                    </div>
                    <div>
                        <div class="kpi-label">Total CIs</div>
                        <div class="kpi-value" id="kpi-val-total"><?php echo number_format($total_cis_count, 0, ',', '.'); ?></div>
                    </div>
                </div>
                <div class="kpi-footer text-muted small">
                    <i class="fas fa-layer-group mr-1 text-primary"></i> <span id="kpi-sub-total">Equipos registrados</span>
                </div>
            </div>
        </div>
        <!-- KPI 2: Total Categorías -->
        <div class="col-xl-3 col-sm-6 mb-2 mb-xl-0">
            <div class="kpi-card shadow-sm" id="kpi-card-cats" title="Total de clases en el árbol">
                <div class="kpi-body d-flex align-items-center">
                    <div class="kpi-icon-wrapper bg-info-soft text-info mr-3">
                        <i class="fas fa-sitemap"></i>
                    </div>
                    <div>
                        <div class="kpi-label">Categorías</div>
                        <div class="kpi-value" id="kpi-val-cats"><?php echo number_format($total_cats_count, 0, ',', '.'); ?></div>
                    </div>
                </div>
                <div class="kpi-footer text-muted small">
                    <i class="fas fa-folder-tree mr-1 text-info"></i> Estructura jerárquica
                </div>
            </div>
        </div>
        <!-- KPI 3: CIs con IP -->
        <div class="col-xl-3 col-sm-6 mb-2 mb-xl-0">
            <div class="kpi-card shadow-sm cursor-pointer" id="kpi-card-ip" onclick="applyQuickFilter('with_ip')" title="Filtrar CIs con dirección IP">
                <div class="kpi-body d-flex align-items-center">
                    <div class="kpi-icon-wrapper bg-success-soft text-success mr-3">
                        <i class="fas fa-network-wired"></i>
                    </div>
                    <div>
                        <div class="kpi-label">Con IP Asignada</div>
                        <div class="kpi-value" id="kpi-val-ip"><?php echo number_format($cis_with_ip_count, 0, ',', '.'); ?></div>
                    </div>
                </div>
                <div class="kpi-footer text-muted small">
                    <i class="fas fa-globe mr-1 text-success"></i> Direccionables en red
                </div>
            </div>
        </div>
        <!-- KPI 4: Zabbix Monitoreados -->
        <div class="col-xl-3 col-sm-6 mb-2 mb-xl-0">
            <div class="kpi-card shadow-sm cursor-pointer" id="kpi-card-zabbix" onclick="applyQuickFilter('zabbix')" title="Filtrar CIs sincronizados con Zabbix">
                <div class="kpi-body d-flex align-items-center">
                    <div class="kpi-icon-wrapper bg-danger-soft text-danger mr-3">
                        <i class="fas fa-heartbeat"></i>
                    </div>
                    <div>
                        <div class="kpi-label">Monitoreo Zabbix</div>
                        <div class="kpi-value" id="kpi-val-zabbix"><?php echo number_format($cis_zabbix_count, 0, ',', '.'); ?></div>
                    </div>
                </div>
                <div class="kpi-footer text-muted small">
                    <i class="fas fa-shield-alt mr-1 text-danger"></i> Integración activa
                </div>
            </div>
        </div>
    </div>

    <!-- Estilos CSS Modernizados y Pulidos -->
    <style>
    /* Design Tokens */
    :root {
        --cmdb-primary: #007bff;
        --cmdb-primary-soft: #e7f1ff;
        --cmdb-dark: #1e293b;
        --cmdb-gray-100: #f8fafc;
        --cmdb-gray-200: #f1f5f9;
        --cmdb-gray-300: #e2e8f0;
        --cmdb-gray-400: #cbd5e1;
        --cmdb-gray-500: #94a3b8;
        --cmdb-gray-600: #64748b;
        --cmdb-success-soft: #dcfce7;
        --cmdb-info-soft: #e0f2fe;
        --cmdb-warning-soft: #fef3c7;
        --cmdb-danger-soft: #fee2e2;
    }

    /* KPI Cards */
    .kpi-card {
        background: #ffffff;
        border: 1px solid var(--cmdb-gray-300);
        border-radius: 10px;
        padding: 14px 16px;
        transition: all 0.2s ease;
        cursor: pointer;
    }
    .kpi-card:hover {
        border-color: #3b82f6;
        box-shadow: 0 4px 12px rgba(0, 123, 255, 0.08);
        transform: translateY(-1px);
    }
    .kpi-card.kpi-active {
        border-color: #2563eb;
        background: linear-gradient(to bottom, #ffffff, #f0f7ff);
        box-shadow: 0 0 0 1px #2563eb;
    }
    .kpi-icon-wrapper {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }
    .bg-primary-soft { background-color: var(--cmdb-primary-soft); color: #0284c7; }
    .bg-info-soft { background-color: var(--cmdb-info-soft); color: #0284c7; }
    .bg-success-soft { background-color: var(--cmdb-success-soft); color: #16a34a; }
    .bg-danger-soft { background-color: var(--cmdb-danger-soft); color: #dc2626; }
    .kpi-label {
        font-size: 0.76rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--cmdb-gray-600);
        line-height: 1.1;
    }
    .kpi-value {
        font-size: 1.45rem;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.2;
    }
    .kpi-footer {
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px dashed var(--cmdb-gray-200);
    }

    /* Category Tree Sidebar */
    .tree-card-wrapper {
        background: #ffffff;
        border: 1px solid var(--cmdb-gray-300);
        border-radius: 10px;
        overflow: hidden;
    }
    .tree-search-box {
        position: relative;
    }
    .tree-search-box i {
        position: absolute;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--cmdb-gray-500);
        font-size: 0.78rem;
    }
    .tree-search-input {
        padding-left: 28px !important;
        font-size: 0.8rem !important;
        border-radius: 6px;
    }
    .ci-tree-container {
        max-height: 680px;
        min-height: 520px;
        overflow-y: auto;
        padding: 8px 12px;
    }
    .ci-tree ul {
        list-style: none;
        padding-left: 18px;
        margin: 0;
    }
    .ci-tree > ul {
        padding-left: 0;
    }
    .ci-tree li {
        margin: 0;
        padding: 3px 0 3px 6px;
        position: relative;
    }
    .ci-tree li::before {
        content: "";
        position: absolute;
        top: 0;
        left: -4px;
        border-left: 1px dashed var(--cmdb-gray-400);
        height: 100%;
    }
    .ci-tree li::after {
        content: "";
        position: absolute;
        top: 14px;
        left: -4px;
        border-top: 1px dashed var(--cmdb-gray-400);
        width: 8px;
    }
    .ci-tree li:last-child::before {
        height: 14px;
    }
    .ci-tree-node-wrapper {
        display: inline-flex;
        align-items: center;
        width: calc(100% - 20px);
    }
    .ci-tree-node {
        display: inline-flex;
        align-items: center;
        padding: 5px 8px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.82rem;
        transition: all 0.15s ease;
        user-select: none;
        color: #334155;
        width: 100%;
    }
    .ci-tree-node:hover {
        background-color: var(--cmdb-gray-200);
        color: #0284c7;
    }
    .ci-tree-node.active {
        background-color: #e0f2fe !important;
        color: #0369a1 !important;
        font-weight: 700;
        box-shadow: inset 2px 0 0 #0284c7;
    }
    .ci-tree-toggle {
        margin-right: 4px;
        cursor: pointer;
        width: 16px;
        text-align: center;
        display: inline-block;
        font-size: 0.7rem;
        color: var(--cmdb-gray-600);
    }
    .ci-tree-toggle-icon.collapsed {
        transform: rotate(-90deg);
    }

    /* Table & Card Right */
    .ci-table-card {
        background: #ffffff;
        border: 1px solid var(--cmdb-gray-300);
        border-radius: 10px;
        overflow: hidden;
    }
    .filter-chip-btn {
        border-radius: 20px;
        font-size: 0.78rem;
        font-weight: 600;
        padding: 4px 12px;
        border: 1px solid var(--cmdb-gray-300);
        background: #ffffff;
        color: var(--cmdb-gray-600);
        transition: all 0.15s ease;
    }
    .filter-chip-btn:hover {
        background: var(--cmdb-gray-200);
        color: #0f172a;
    }
    .filter-chip-btn.active {
        background: #0284c7;
        color: #ffffff;
        border-color: #0284c7;
        box-shadow: 0 2px 6px rgba(2, 132, 199, 0.25);
    }

    /* Table Styles */
    #configitem-ci-table {
        font-size: 0.84rem !important;
        margin-bottom: 0;
    }
    #configitem-ci-table thead th {
        font-size: 0.76rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        background-color: #f8fafc !important;
        border-bottom: 2px solid var(--cmdb-gray-300) !important;
        color: #334155 !important;
        padding: 10px 12px !important;
        white-space: nowrap;
        vertical-align: middle;
    }
    #configitem-ci-table tbody td {
        padding: 10px 12px !important;
        vertical-align: middle;
        border-bottom: 1px solid var(--cmdb-gray-200);
        line-height: 1.35;
    }
    #configitem-ci-table tbody tr {
        transition: background-color 0.15s ease;
    }
    #configitem-ci-table tbody tr:hover {
        background-color: #f8fafc !important;
    }

    /* Status Indicator */
    .status-indicator {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-right: 6px;
    }
    .status-active { background-color: #22c55e; box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.2); }
    .status-inactive { background-color: #ef4444; }

    /* Badges & Chips */
    .ci-code-badge {
        font-family: 'SFMono-Regular', Menlo, Monaco, Consolas, monospace;
        font-size: 0.78rem;
        font-weight: 600;
        background: #f1f5f9;
        border: 1px solid #cbd5e1;
        color: #1e293b;
        padding: 3px 8px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
    }
    .ip-chip {
        font-family: 'SFMono-Regular', Menlo, Monaco, Consolas, monospace;
        font-size: 0.8rem;
        font-weight: 600;
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        color: #1d4ed8;
        padding: 3px 8px;
        border-radius: 6px;
    }
    .badge-soft-primary { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-weight: 600; }
    .badge-soft-secondary { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
    .badge-soft-danger { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
    .badge-soft-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }

    .ci-avatar-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: #e0f2fe;
        color: #0284c7;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.95rem;
        flex-shrink: 0;
    }

    .btn-copy-hover {
        opacity: 0.4;
        transition: opacity 0.2s;
    }
    .btn-copy-hover:hover {
        opacity: 1;
        color: #007bff !important;
    }

    /* Dark Mode Adaptations */
    .dark-mode .kpi-card,
    .dark-mode .tree-card-wrapper,
    .dark-mode .ci-table-card {
        background-color: #1e293b;
        border-color: #334155;
        color: #f8fafc;
    }
    .dark-mode .kpi-value { color: #f8fafc; }
    .dark-mode .kpi-card.kpi-active {
        background: #0f172a;
        border-color: #38bdf8;
    }
    .dark-mode #configitem-ci-table thead th {
        background-color: #0f172a !important;
        border-bottom-color: #334155 !important;
        color: #cbd5e1 !important;
    }
    .dark-mode #configitem-ci-table tbody td {
        border-bottom-color: #334155;
    }
    .dark-mode #configitem-ci-table tbody tr:hover {
        background-color: #334155 !important;
    }
    .dark-mode .ci-code-badge {
        background: #334155;
        border-color: #475569;
        color: #f8fafc;
    }
    .dark-mode .filter-chip-btn {
        background: #334155;
        border-color: #475569;
        color: #cbd5e1;
    }
    .dark-mode .filter-chip-btn.active {
        background: #0284c7;
        color: #ffffff;
    }
    .dark-mode .ci-tree-container {
        background-color: #1e293b;
    }
    .dark-mode .ci-tree-node {
        color: #cbd5e1;
    }
    .dark-mode .ci-tree-node:hover {
        background-color: #334155;
        color: #38bdf8;
    }
    .dark-mode .ci-tree-node.active {
        background-color: #0c4a6e !important;
        color: #38bdf8 !important;
    }

    /* Modal details styles */
    .detail-card-label {
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #6c757d;
        margin-bottom: 0.2rem;
    }
    .detail-card-value {
        font-size: 0.95rem;
        color: #212529;
        font-weight: 500;
    }
    .detail-attr-box {
        background-color: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        transition: all 0.2s ease;
    }
    .detail-attr-box:hover {
        background-color: #ffffff;
        box-shadow: 0 4px 10px rgba(0,0,0,0.06);
        border-color: #007bff;
    }
    .sortable-header {
        user-select: none;
        transition: background-color 0.2s ease;
    }
    .sortable-header:hover {
        background-color: rgba(0, 123, 255, 0.05) !important;
    }
    .sortable-header i {
        font-size: 0.85em;
        transition: color 0.2s ease;
    }

    /* Fullscreen modal tweaks */
    .modal-fullscreen .modal-dialog {
        max-width: 100vw !important;
        width: 100vw !important;
        margin: 0 !important;
        height: 100vh !important;
    }
    .modal-fullscreen .modal-content {
        height: 100vh !important;
        border-radius: 0 !important;
    }
    .modal-fullscreen .modal-body {
        height: calc(100vh - 56px) !important;
        overflow-y: auto !important;
    }
    .map-wrapper { height: 350px; border-radius: 12px; overflow: hidden; background: #e9ecef; }
    </style>

    <!-- Layout Grid: Panel Izquierdo (Árbol) + Panel Derecho (Tabla) -->
    <div class="row">
        <!-- COLUMNA IZQUIERDA: Árbol de Categorías con Buscador en tiempo real -->
        <div class="col-xl-3 col-lg-4 col-md-5 mb-3" id="col-categories-tree">
            <div class="tree-card-wrapper shadow-sm">
                <!-- Cabecera del Árbol -->
                <div class="p-3 border-bottom bg-light d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="font-weight-bold mb-0 text-dark">
                            <i class="fas fa-layer-group text-primary mr-1.5"></i> Categorías
                        </h6>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-xs btn-outline-secondary" onclick="expandAllCategories()" title="Expandir todo el árbol">
                            <i class="fas fa-expand-alt"></i>
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-secondary" onclick="collapseAllCategories()" title="Contraer todo el árbol">
                            <i class="fas fa-compress-alt"></i>
                        </button>
                    </div>
                </div>

                <!-- Buscador rápido dentro del árbol -->
                <div class="p-2 border-bottom bg-white">
                    <div class="tree-search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="cat-tree-search" class="form-control form-control-sm tree-search-input" placeholder="Filtrar categorías...">
                    </div>
                </div>

                <!-- Contenedor del Árbol de Categorías -->
                <div class="ci-tree-container" id="category-tree-scroll-container">
                    <div id="category-relations-tree" class="ci-tree">
                        <div class="text-center py-4 text-muted">
                            <div class="spinner-border spinner-border-sm text-primary mr-2" role="status"></div>
                            Cargando árbol de categorías...
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- COLUMNA DERECHA: Listado y Controles del Inventario -->
        <div class="col-xl-9 col-lg-8 col-md-7" id="col-ci-table">
            <div class="ci-table-card shadow-sm">
                
                <!-- Cabecera de la Tabla: Categoría Seleccionada y Acciones Rápidas -->
                <div class="p-3 border-bottom bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="d-flex align-items-center">
                        <button type="button" class="btn btn-sm btn-outline-secondary mr-2.5 shadow-2xs" id="btn-toggle-tree-sidebar" onclick="toggleCategorySidebar()" title="Ocultar / Mostrar árbol de categorías">
                            <i class="fas fa-columns"></i>
                        </button>
                        <div>
                            <div class="d-flex align-items-center">
                                <h5 class="font-weight-bold mb-0 text-dark" id="selected-cat-title">
                                    <i class="fas fa-layer-group text-primary mr-1.5"></i> Todos los CIs
                                </h5>
                                <span class="badge badge-light border text-muted font-weight-bold ml-2" id="selected-cat-counter">
                                    Total: 0
                                </span>
                            </div>
                            <small class="text-muted" id="selected-cat-path">Inventario global sin filtro jerárquico</small>
                        </div>
                    </div>

                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary mr-1" onclick="refreshCurrentCategoryData()" title="Recargar datos">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-success mr-1" onclick="exportCIsToCSV()" title="Exportar vista a CSV">
                            <i class="fas fa-file-csv mr-1"></i> Exportar
                        </button>
                    </div>
                </div>

                <!-- Barra de Búsqueda y Filtros Rápidos -->
                <div class="p-3 bg-light border-bottom">
                    <div class="row align-items-center">
                        <!-- Buscador Principal -->
                        <div class="col-lg-6 mb-2 mb-lg-0">
                            <div class="input-group shadow-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-white border-right-0 text-muted"><i class="fas fa-search"></i></span>
                                </div>
                                <input type="text" id="configitem-ci-search-input" class="form-control border-left-0" placeholder="Buscar por Nombre, Código Único, IP o Sigla...">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary border-left-0 bg-white" type="button" id="configitem-ci-search-clear-btn" title="Limpiar búsqueda">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Selector de Registros por Página -->
                        <div class="col-lg-6 d-flex justify-content-lg-end align-items-center flex-wrap gap-2">
                            <span class="text-muted small mr-2">Mostrar:</span>
                            <div class="btn-group btn-group-sm mr-3 shadow-2xs" role="group">
                                <button type="button" class="btn btn-outline-secondary btn-pagesize" data-size="10" onclick="changePageSize(10)">10</button>
                                <button type="button" class="btn btn-outline-secondary btn-pagesize active" data-size="25" onclick="changePageSize(25)">25</button>
                                <button type="button" class="btn btn-outline-secondary btn-pagesize" data-size="50" onclick="changePageSize(50)">50</button>
                                <button type="button" class="btn btn-outline-secondary btn-pagesize" data-size="100" onclick="changePageSize(100)">100</button>
                                <button type="button" class="btn btn-outline-secondary btn-pagesize" data-size="all" onclick="changePageSize('all')">Todos</button>
                            </div>
                            <span class="badge badge-light border text-dark font-weight-bold px-3 py-1.5" id="configitem-ci-counter-label" style="font-size: 0.8rem;">
                                Mostrando 0 de 0 CIs
                            </span>
                        </div>
                    </div>

                    <!-- Fila de Filtros Rápidos (Chips/Pills) -->
                    <div class="d-flex align-items-center flex-wrap gap-1.5 mt-2.5 pt-2 border-top">
                        <span class="text-muted small font-weight-bold mr-2"><i class="fas fa-filter mr-1"></i> Filtro:</span>
                        <button type="button" class="filter-chip-btn active mr-1 mb-1" data-filter="all" onclick="applyQuickFilter('all')">
                            Todos <span class="badge badge-light ml-1" id="chip-count-all">0</span>
                        </button>
                        <button type="button" class="filter-chip-btn mr-1 mb-1" data-filter="with_ip" onclick="applyQuickFilter('with_ip')">
                            <i class="fas fa-network-wired mr-1 text-success"></i> Con IP <span class="badge badge-light ml-1" id="chip-count-ip">0</span>
                        </button>
                        <button type="button" class="filter-chip-btn mr-1 mb-1" data-filter="no_ip" onclick="applyQuickFilter('no_ip')">
                            Sin IP <span class="badge badge-light ml-1" id="chip-count-no-ip">0</span>
                        </button>
                        <button type="button" class="filter-chip-btn mr-1 mb-1" data-filter="zabbix" onclick="applyQuickFilter('zabbix')">
                            <i class="fas fa-server mr-1 text-danger"></i> Zabbix <span class="badge badge-light ml-1" id="chip-count-zabbix">0</span>
                        </button>
                        <button type="button" class="filter-chip-btn mb-1" data-filter="manual" onclick="applyQuickFilter('manual')">
                            <i class="fas fa-user-edit mr-1 text-secondary"></i> Manual <span class="badge badge-light ml-1" id="chip-count-manual">0</span>
                        </button>
                    </div>
                </div>

                <!-- Tabla de Datos Principal -->
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="configitem-ci-table">
                            <thead>
                                <tr>
                                    <th style="width: 140px; cursor: pointer;" class="sortable-header" data-sort="ci_unique" onclick="sortDynamicCIs('ci_unique')">
                                        Código Único <i class="fas fa-sort text-muted opacity-50 ml-1"></i>
                                    </th>
                                    <th style="min-width: 200px; cursor: pointer;" class="sortable-header" data-sort="hostname" onclick="sortDynamicCIs('hostname')">
                                        Elemento / Hostname <i class="fas fa-sort-up ml-1 text-primary"></i>
                                    </th>
                                    <th style="width: 160px; cursor: pointer;" class="sortable-header" data-sort="ip" onclick="sortDynamicCIs('ip')">
                                        IP Address <i class="fas fa-sort text-muted opacity-50 ml-1"></i>
                                    </th>
                                    <th style="min-width: 130px; cursor: pointer;" class="sortable-header" data-sort="class" onclick="sortDynamicCIs('class')">
                                        Clase / Categoría <i class="fas fa-sort text-muted opacity-50 ml-1"></i>
                                    </th>
                                    <th style="width: 100px; cursor: pointer;" class="sortable-header" data-sort="sigla" onclick="sortDynamicCIs('sigla')">
                                        Sigla <i class="fas fa-sort text-muted opacity-50 ml-1"></i>
                                    </th>
                                    <th style="min-width: 150px; cursor: pointer;" class="sortable-header" data-sort="created_at" onclick="sortDynamicCIs('created_at')">
                                        Origen & Registro <i class="fas fa-sort text-muted opacity-50 ml-1"></i>
                                    </th>
                                    <th style="width: 120px; text-align: right;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="configitem-ci-tbody">
                                <?php if (empty($initial_cis)): ?>
                                    <tr>
                                        <td colspan="100%" class="text-center py-5 text-muted">
                                            <i class="fas fa-server fa-3x mb-3 text-muted opacity-50"></i>
                                            <h6>No hay Elementos de Configuración registrados</h6>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($initial_cis as $inst): 
                                        $ciUnique = !empty($inst['ci_unique']) ? $inst['ci_unique'] : 'SND-XXXXXXXXXX';
                                        $catIcon = !empty($inst['category_icon']) ? $inst['category_icon'] : 'fa-cube';
                                        if (strpos($catIcon, 'fa-') === false) $catIcon = 'fa-' . $catIcon;
                                        $attrs = [];
                                        if (!empty($inst['attributes_json'])) {
                                            $attrs = is_string($inst['attributes_json']) ? json_decode($inst['attributes_json'], true) : $inst['attributes_json'];
                                        }
                                        if (!is_array($attrs)) $attrs = [];
                                        $attrCount = count($attrs);
                                        $marca = $attrs['marca'] ?? $attrs['Marca'] ?? '';
                                        $modelo = $attrs['modelo'] ?? $attrs['Modelo'] ?? '';
                                        $rack = $attrs['rack'] ?? $attrs['Rack'] ?? '';
                                        $createdAt = '-';
                                        if (!empty($inst['created_at']) && $inst['created_at'] !== '0000-00-00 00:00:00') {
                                            $time = strtotime($inst['created_at']);
                                            $createdAt = $time ? date('d/m/Y H:i', $time) : $inst['created_at'];
                                        }
                                        $isZabbix = ($inst['source'] === 'zabbix' || (!empty($inst['zabbix_host_id']) && (int)$inst['zabbix_host_id'] > 0));
                                    ?>
                                    <tr class="ci-row animate__animated animate__fadeIn">
                                        <td>
                                            <div class="d-inline-flex align-items-center">
                                                <span class="ci-code-badge"><?php echo htmlspecialchars($ciUnique); ?></span>
                                                <button type="button" class="btn btn-link btn-xs text-muted p-0 ml-1.5 btn-copy-hover" onclick="copyToClipboard('<?php echo htmlspecialchars($ciUnique, ENT_QUOTES); ?>', this)" title="Copiar código">
                                                    <i class="far fa-copy"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="ci-avatar-icon mr-2.5">
                                                    <i class="fas <?php echo htmlspecialchars($catIcon); ?>"></i>
                                                </span>
                                                <div>
                                                    <div class="d-flex align-items-center flex-wrap">
                                                        <span class="status-indicator status-active" title="Estado: <?php echo htmlspecialchars($inst['status'] ?? 'Activo'); ?>"></span>
                                                        <a href="javascript:void(0)" onclick="viewCIDetails(<?php echo (int)$inst['id']; ?>)" class="font-weight-bold text-primary" style="font-size: 0.9rem;" title="Ver Ficha Técnica Completa">
                                                            <?php echo htmlspecialchars($inst['hostname'] ?? ''); ?>
                                                        </a>
                                                        <span class="badge badge-pill badge-light border text-secondary px-2 py-0.5 ml-2 cursor-pointer" title="<?php echo $attrCount; ?> atributos registrados en la ficha técnica" onclick="viewCIDetails(<?php echo (int)$inst['id']; ?>)" style="font-size: 0.7rem;">
                                                            <i class="fas fa-sliders-h mr-1 text-primary"></i><?php echo $attrCount; ?> attrs
                                                        </span>
                                                    </div>
                                                    <?php if (!empty($inst['description'])): ?>
                                                        <div class="text-muted small text-truncate mt-0.5" style="max-width: 280px;" title="<?php echo htmlspecialchars($inst['description']); ?>">
                                                            <?php echo htmlspecialchars($inst['description']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($inst['parent_ci_name'])): ?>
                                                        <div class="mt-0.5 text-muted small d-flex align-items-center">
                                                            <i class="fas fa-level-up-alt fa-rotate-90 text-primary mr-1" style="font-size: 0.75rem;"></i>
                                                            <span>Padre: <strong class="text-dark"><?php echo htmlspecialchars($inst['parent_ci_name']); ?></strong></span>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($marca || $modelo || $rack): ?>
                                                        <div class="mt-1 d-flex flex-wrap align-items-center">
                                                            <?php if ($marca || $modelo): ?>
                                                                <span class="badge badge-light border text-dark font-weight-normal mr-1" title="Marca / Modelo"><i class="fas fa-tag text-muted mr-1"></i><?php echo htmlspecialchars(trim($marca . ' ' . $modelo)); ?></span>
                                                            <?php endif; ?>
                                                            <?php if ($rack): ?>
                                                                <span class="badge badge-light border text-dark font-weight-normal" title="Rack / Ubicación"><i class="fas fa-server text-muted mr-1"></i><?php echo htmlspecialchars($rack); ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($inst['ip_address']) && trim($inst['ip_address']) !== ''): ?>
                                                <div class="d-inline-flex align-items-center">
                                                    <span class="ip-chip"><?php echo htmlspecialchars($inst['ip_address']); ?></span>
                                                    <button type="button" class="btn btn-link btn-xs text-muted p-0 ml-1.5 btn-copy-hover" onclick="copyToClipboard('<?php echo htmlspecialchars($inst['ip_address'], ENT_QUOTES); ?>', this)" title="Copiar dirección IP">
                                                        <i class="far fa-copy"></i>
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted small fst-italic"><i class="fas fa-minus mr-1 opacity-50"></i>Sin IP</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-soft-primary px-2.5 py-1" style="font-size: 0.78rem;">
                                                <i class="fas <?php echo htmlspecialchars($catIcon); ?> mr-1"></i> <?php echo htmlspecialchars($inst['category_name'] ?? '-'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-soft-secondary font-weight-bold px-2 py-1"><?php echo htmlspecialchars($inst['sigla'] ?? '-'); ?></span>
                                        </td>
                                        <td>
                                            <div>
                                                <?php if ($isZabbix): ?>
                                                    <span class="badge badge-soft-danger font-weight-bold" title="Zabbix Host ID: <?php echo htmlspecialchars($inst['zabbix_host_id'] ?? ''); ?>"><i class="fas fa-heartbeat mr-1"></i>Zabbix (<?php echo htmlspecialchars($inst['zabbix_host_id'] ?? 'ID'); ?>)</span>
                                                <?php else: ?>
                                                    <span class="badge badge-soft-secondary" title="Registro Manual"><i class="fas fa-user-edit mr-1"></i>Manual</span>
                                                <?php endif; ?>
                                                <small class="text-muted d-block mt-1" title="Fecha de Creación"><i class="far fa-calendar-alt mr-1"></i><?php echo htmlspecialchars($createdAt); ?></small>
                                                <small class="text-muted d-block" title="Registrado por"><i class="far fa-user mr-1"></i><?php echo htmlspecialchars($inst['creator_name'] ?? 'Sistema'); ?></small>
                                            </div>
                                        </td>
                                        <td style="text-align: right;">
                                            <div class="btn-group btn-group-sm shadow-2xs">
                                                <button type="button" class="btn btn-outline-primary" onclick="viewCIDetails(<?php echo (int)$inst['id']; ?>)" title="Ficha Técnica">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <a href="ci_business_view.php?ci_id=<?php echo (int)$inst['id']; ?>" class="btn btn-outline-info" title="Business View (Topología)">
                                                    <i class="fas fa-project-diagram"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-secondary btn-edit-ci" data-id="<?php echo (int)$inst['id']; ?>" title="Editar CI">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger" onclick="deleteCI(<?php echo (int)$inst['id']; ?>)" title="Eliminar CI">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Footer de la Tabla con Paginación -->
                <div class="p-3 bg-white border-top d-flex flex-wrap justify-content-between align-items-center" id="ci-table-footer">
                    <div class="text-muted small mb-2 mb-md-0" id="ci-pagination-info">
                        Cargando información...
                    </div>
                    <nav aria-label="Navegación de páginas">
                        <ul class="pagination pagination-sm mb-0 justify-content-center" id="ci-pagination-controls">
                            <!-- Los botones de página se insertan dinámicamente -->
                        </ul>
                    </nav>
                </div>

            </div>
        </div>
    </div>
</div>


<!-- Modal rediseñado a tamaño Extra Grande (modal-xl) con diseño Técnico/Gerencial -->
<div class="modal fade modal-fullscreen" id="attrModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header bg-gradient-primary text-white border-bottom-0 py-3 d-flex align-items-center">
                <h5 class="modal-title font-weight-bold text-white mb-0" id="attrModalTitle">
                    <i class="fas fa-server mr-2"></i> Detalles del CI
                </h5>
                <div class="ml-auto d-flex align-items-center">
                    <button type="button" class="btn btn-sm btn-outline-light mr-2 border-0" id="btn-maximize-modal" title="Pantalla Completa" style="opacity: 0.8; outline: none; background: transparent; color: white;">
                        <i class="fas fa-compress"></i>
                    </button>
                    <button type="button" class="close text-white border-0 bg-transparent" data-dismiss="modal" aria-label="Close" style="font-size: 1.5rem; opacity: 0.8; outline: none; line-height: 1;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div>
            <div class="modal-body p-0 bg-light" id="attrModalBody">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;"></div>
                    <p class="mt-2 text-muted">Cargando ficha técnica...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Relaciones Modal -->
<div class="modal fade" id="relationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold text-white"><i class="fas fa-project-diagram mr-2"></i> Añadir Relación</h5>
                <button type="button" class="close text-white border-0 bg-transparent" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Categoría Destino</label>
                    <select id="rel_cat_1" class="form-control form-control-sm mb-2">
                        <option value="">-- Nivel 1 --</option>
                    </select>
                    <select id="rel_cat_2" class="form-control form-control-sm mb-2" disabled>
                        <option value="">-- Nivel 2 --</option>
                    </select>
                    <select id="rel_cat_3" class="form-control form-control-sm" disabled>
                        <option value="">-- Nivel 3 --</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>CI Destino <span class="text-danger">*</span></label>
                    <select id="rel_target_id" class="form-control" disabled>
                        <option value="">Seleccione Categoría Primero...</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Tipo de Relación <span class="text-danger">*</span></label>
                    <select id="rel_type" class="form-control">
                        <optgroup label="Dependencia Técnica">
                            <option value="Runs on">Runs on (Se ejecuta en)</option>
                            <option value="Communicates with">Communicates with (Se comunica con)</option>
                            <option value="Storage provided by">Storage provided by (Almacenamiento provisto por)</option>
                        </optgroup>
                        <optgroup label="Composición (Jerárquicas)">
                            <option value="Contains">Contains (Contiene)</option>
                            <option value="Is Member of">Is Member of (Es miembro de)</option>
                        </optgroup>
                        <optgroup label="Despliegue de Software">
                            <option value="Instantiated from">Instantiated from (Instanciado de)</option>
                            <option value="Depends on">Depends on (Depende de)</option>
                        </optgroup>
                        <optgroup label="Negocio / Servicios">
                            <option value="Supports">Supports (Soporta a)</option>
                            <option value="Owned by">Owned by (Propiedad de)</option>
                            <option value="Used by">Used by (Usado por)</option>
                        </optgroup>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Impacto si Destino falla <span class="text-danger">*</span></label>
                    <select id="rel_impact" class="form-control">
                        <option value="Sí">Sí (Fallo total)</option>
                        <option value="Parcial">Parcial (Degradación)</option>
                        <option value="No">No (Independiente)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="addRelation()">Añadir</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
window.preloadedCIs = <?php echo json_encode($initial_cis, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.preloadedCategories = <?php echo json_encode($initial_categories, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
function toggleCIBranch(event, element) {
    event.stopPropagation();
    const branchDiv = $(element).closest('.ci-tree-item').find('> .ci-tree-branch');
    const icon = $(element).find('.ci-tree-toggle-icon');
    if (branchDiv.is(':visible')) {
        branchDiv.slideUp(200);
        icon.addClass('collapsed');
    } else {
        branchDiv.slideDown(200);
        icon.removeClass('collapsed');
    }
}

function selectCITreeNode(ciId, hostname) {
    // Desmarcar nodos anteriores e iluminar el actual
    $('.ci-tree-node').removeClass('active');
    $(`.ci-tree-item[data-id="${ciId}"] > .ci-tree-node-wrapper .ci-tree-node`).addClass('active');
    
    // Poner el hostname en el buscador para filtrar la lista
    const searchInput = document.getElementById('ci-search-input');
    if (searchInput) {
        searchInput.value = hostname;
        // Lanzar eventos para filtrar usando jQuery
        $('#ci-search-input').val(hostname).trigger('input');
    }
}

let categories = [];
let pendingRelations = [];
let currentCIData = null;
let isEditMode = false;
let isCreateMode = false;

function getCategoryLineage(catId) {
    let lineage = [];
    if (typeof categories === 'undefined' || !categories) return lineage;
    let curr = categories.find(c => c.id == catId);
    let visited = new Set();
    while (curr) {
        if (visited.has(curr.id)) break;
        visited.add(curr.id);
        lineage.unshift(curr);
        curr = curr.parent_id ? categories.find(c => c.id == curr.parent_id) : null;
    }
    return lineage;
}

function renderCategoryOptionsTree(cats, selectedId, parentId = null, depth = 0) {
    if (!cats) return '';
    let html = '';
    let children = cats.filter(c => parentId ? c.parent_id == parentId : !c.parent_id);
    children.sort((a,b) => (a.name || '').localeCompare(b.name || ''));
    children.forEach(c => {
        let prefix = depth > 0 ? '│  '.repeat(depth - 1) + '└─ ' : '';
        let sel = (selectedId && selectedId == c.id) ? 'selected' : '';
        let codeBadge = c.cat_code ? ` [${c.cat_code}]` : '';
        html += `<option value="${c.id}" ${sel}>${prefix}${c.name}${codeBadge}</option>`;
        if (cats.some(child => child.parent_id == c.id)) {
            html += renderCategoryOptionsTree(cats, selectedId, c.id, depth + 1);
        }
    });
    return html;
}

function renderCategoryTreePickerHTML(selectedCatId) {
    if (!categories || categories.length === 0) {
        return '<div class="alert alert-info py-4 text-center"><i class="fas fa-spinner fa-spin mr-2"></i> Cargando árbol de categorías...</div>';
    }

    function buildBranch(parentId, depth) {
        let children = categories.filter(c => parentId ? c.parent_id == parentId : !c.parent_id);
        if (children.length === 0) return '';
        children.sort((a,b) => (a.name || '').localeCompare(b.name || ''));

        let html = '<ul class="list-group list-group-flush ' + (depth > 0 ? 'ml-4 border-left pl-2' : '') + '" style="list-style:none;">';
        children.forEach(c => {
            let isSelected = selectedCatId && selectedCatId == c.id;
            let iconClass = c.icon || (depth === 0 ? 'fa-folder-open' : 'fa-cubes');
            let codeBadge = c.cat_code ? `<span class="badge badge-light border text-monospace text-muted ml-2">${c.cat_code}</span>` : '';
            let hasChildren = categories.some(child => child.parent_id == c.id);
            
            html += `
                <li class="list-group-item bg-transparent border-0 px-1 py-1">
                    <div class="d-flex align-items-center p-2 rounded cursor-pointer cat-picker-item ${isSelected ? 'bg-primary text-white shadow-sm font-weight-bold' : 'hover-bg-light border'}" 
                         onclick="selectCategoryForCreation(${c.id})" style="cursor: pointer; transition: all 0.2s;">
                        <i class="fas ${iconClass} ${isSelected ? 'text-white' : 'text-primary'} mr-2.5"></i>
                        <span class="flex-grow-1 font-weight-bold" style="font-size: 0.95rem;">${escapeHtml(c.name)}</span>
                        ${codeBadge}
                        <i class="fas fa-chevron-right ml-2 text-muted small"></i>
                    </div>
                    ${hasChildren ? buildBranch(c.id, depth + 1) : ''}
                </li>
            `;
        });
        html += '</ul>';
        return html;
    }

    return `
        <div class="card border-primary shadow-sm mb-4" style="border-radius: 10px;">
            <div class="card-header bg-white border-bottom pt-3 pb-3 px-4 d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="mb-0 font-weight-bold text-primary"><i class="fas fa-sitemap mr-2"></i> Paso 1: Seleccione la Categoría del Elemento</h5>
                    <p class="text-muted small mb-0">Elija la categoría o subcategoría correspondiente en el árbol de la CMDB</p>
                </div>
                <span class="badge badge-primary px-3 py-2 font-weight-bold">CMDB Categories</span>
            </div>
            <div class="card-body p-4">
                <div class="form-group mb-3">
                    <div class="input-group input-group-lg">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-light border-right-0"><i class="fas fa-search text-muted"></i></span>
                        </div>
                        <input type="text" id="cat-tree-search-input" class="form-control border-left-0 bg-light" placeholder="Filtrar categorías por nombre o código (ej: Servidores, Switches, Cuartos...)" onkeyup="filterCategoryPickerTree(this.value)">
                    </div>
                </div>
                <div id="cat-tree-picker-container" style="max-height: 420px; overflow-y: auto;" class="border rounded bg-white p-3 shadow-inner">
                    ${buildBranch(null, 0)}
                </div>
            </div>
        </div>
    `;
}

function selectCategoryForCreation(catId) {
    if (!currentCIData || !currentCIData.ci) return;
    currentCIData.ci.category_id = catId;
    currentCIData.lineage = getCategoryLineage(catId);
    let targetCat = currentCIData.lineage[currentCIData.lineage.length - 1];
    let catName = targetCat ? targetCat.name : 'General';
    $('#attrModalTitle').html('<i class="fas fa-plus-circle text-success mr-2"></i> Crear Nuevo CI: <span class="badge badge-success font-weight-normal text-white ml-2">' + catName + '</span>');
    renderEditView(currentCIData.ci, currentCIData.lineage, [], [], []);
}

function resetCategorySelection() {
    if (!currentCIData || !currentCIData.ci) return;
    currentCIData.ci.category_id = 0;
    currentCIData.lineage = [];
    $('#attrModalTitle').html('<i class="fas fa-plus-circle text-success mr-2"></i> Crear Nuevo CI: <span class="badge badge-warning font-weight-normal text-dark ml-2">Seleccionar Categoría</span>');
    renderEditView(currentCIData.ci, [], [], [], []);
}

function filterCategoryPickerTree(term) {
    term = (term || '').toLowerCase().trim();
    $('#cat-tree-picker-container .cat-picker-item').each(function() {
        let text = $(this).text().toLowerCase();
        if (!term || text.includes(term)) {
            $(this).closest('li').show();
        } else {
            $(this).closest('li').hide();
        }
    });
}

function openCreateCIModal(catId) {
    catId = catId ? parseInt(catId) : (typeof category_id !== 'undefined' && category_id ? parseInt(category_id) : 0);
    
    isEditMode = true;
    isCreateMode = true;

    if (!categories || categories.length === 0) {
        $('#attrModalTitle').html('<i class="fas fa-plus-circle text-success mr-2"></i> Cargando Categorías...');
        $('#attrModalBody').html(`
            <div class="text-center py-5">
                <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;"></div>
                <p class="mt-3 text-muted">Cargando árbol de categorías de la CMDB...</p>
            </div>
        `);
        $('#attrModal').modal('show');

        $.get('api_ci.php?action=get_categories', function(res) {
            if (res.success) {
                categories = res.data;
                openCreateCIModal(catId);
            } else {
                $('#attrModalBody').html('<div class="alert alert-danger m-4">Error al cargar categorías de la CMDB.</div>');
            }
        }, 'json');
        return;
    }

    let nextCiUnique = 'SND-' + Math.floor(1000000000 + Math.random() * 9000000000);

    let ci = {
        id: 0,
        category_id: catId,
        hostname: '',
        sigla: '',
        ip_address: '',
        status: 'Activo',
        source: 'manual',
        description: '',
        ci_unique: nextCiUnique,
        created_at: new Date().toISOString().replace('T', ' ').substring(0, 19),
        zabbix_host_id: null
    };

    let lineage = [];
    if (catId > 0) {
        lineage = getCategoryLineage(catId);
    }
    
    currentCIData = {
        ci: ci,
        lineage: lineage,
        relations: [],
        parent_chain: [],
        images: []
    };

    let catName = lineage.length > 0 ? lineage[lineage.length - 1].name : 'Seleccionar Categoría';
    $('#attrModalTitle').html('<i class="fas fa-plus-circle text-success mr-2"></i> Crear Nuevo CI: <span class="badge badge-success font-weight-normal text-white ml-2">' + catName + '</span>');
    $('#attrModal').modal('show');

    renderEditView(ci, lineage, [], [], []);
}

function viewCIDetails(id) {
    $('#attrModalTitle').html('<i class="fas fa-server mr-2"></i> Cargando detalles...');
    $('#attrModalBody').html(`
        <div class="text-center py-5">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;"></div>
            <p class="mt-3 text-muted">Obteniendo expediente técnico...</p>
        </div>
    `);
    $('#attrModal').modal('show');
    
    $.get('api_ci.php?action=get_ci_details&id=' + id, function(res) {
        if (res.success) {
            currentCIData = res.data;
            isEditMode = false;
            isCreateMode = false;
            $('#attrModalTitle').html('<i class="fas fa-server mr-2"></i>' + currentCIData.ci.hostname + ' <span class="badge badge-info ml-2 font-weight-normal text-white">' + currentCIData.ci.category_name + '</span>');
            renderCIModal();
        } else {
            $('#attrModalBody').html('<div class="alert alert-danger m-4"><i class="fas fa-exclamation-triangle mr-2"></i>' + res.message + '</div>');
        }
    }, 'json').fail(function() {
        $('#attrModalBody').html('<div class="alert alert-danger m-4"><i class="fas fa-exclamation-triangle mr-2"></i>Error al consultar el endpoint api_ci.php.</div>');
    });
}

function editCIDetailsDirectly(id) {
    $('#attrModalTitle').html('<i class="fas fa-edit mr-2"></i> Cargando editor...');
    $('#attrModalBody').html(`
        <div class="text-center py-5">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;"></div>
            <p class="mt-3 text-muted">Abriendo editor de expediente técnico...</p>
        </div>
    `);
    $('#attrModal').modal('show');
    
    $.get('api_ci.php?action=get_ci_details&id=' + id, function(res) {
        if (res.success) {
            currentCIData = res.data;
            isEditMode = true;
            isCreateMode = false;
            $('#attrModalTitle').html('<i class="fas fa-edit mr-2"></i> Editar: ' + currentCIData.ci.hostname);
            renderCIModal();
        } else {
            $('#attrModalBody').html('<div class="alert alert-danger m-4"><i class="fas fa-exclamation-triangle mr-2"></i>' + res.message + '</div>');
        }
    }, 'json').fail(function() {
        $('#attrModalBody').html('<div class="alert alert-danger m-4"><i class="fas fa-exclamation-triangle mr-2"></i>Error al consultar el endpoint api_ci.php.</div>');
    });
}

function toggleCIEditMode(edit) {
    isEditMode = edit;
    if (isEditMode) {
        $('#attrModalTitle').html('<i class="fas fa-edit mr-2"></i> Editar: ' + currentCIData.ci.hostname);
    } else {
        $('#attrModalTitle').html('<i class="fas fa-server mr-2"></i>' + currentCIData.ci.hostname + ' <span class="badge badge-info ml-2 font-weight-normal text-white">' + currentCIData.ci.category_name + '</span>');
    }
    renderCIModal();
}

let globalAttributes = [];

function loadGlobalAttributes(callback) {
    if (globalAttributes && globalAttributes.length > 0) {
        if (typeof callback === 'function') callback();
        return;
    }
    $.get('api_ci.php?action=get_attributes', function(res) {
        if (typeof res === 'string') {
            try { res = JSON.parse(res); } catch(e) {}
        }
        if (res && res.success && Array.isArray(res.data)) {
            globalAttributes = res.data;
        }
        if (typeof callback === 'function') callback();
    }, 'json').fail(function() {
        if (typeof callback === 'function') callback();
    });
}

function getGroupIcon(groupName) {
    if (!groupName) return 'fa-layer-group text-info';
    let g = groupName.toLowerCase();
    if (g.includes('monitoreo')) return 'fa-heartbeat text-danger';
    if (g.includes('propiedad') || g.includes('identifica')) return 'fa-id-card text-warning';
    if (g.includes('licencia')) return 'fa-certificate text-success';
    if (g.includes('imagen') || g.includes('foto')) return 'fa-camera text-primary';
    if (g.includes('ubicaci') || g.includes('geogr')) return 'fa-map-marker-alt text-danger';
    if (g.includes('hardware') || g.includes('infra')) return 'fa-microchip text-info';
    if (g.includes('red') || g.includes('comunic')) return 'fa-network-wired text-primary';
    if (g.includes('general') || g.includes('especifica')) return 'fa-sliders-h text-secondary';
    return 'fa-cubes text-info';
}

function getAllCIProperties(ci, lineage) {
    let allProps = {};
    let requiredFields = [];

    // 1. System/Global attributes from database
    if (globalAttributes && globalAttributes.length > 0) {
        globalAttributes.forEach(attr => {
            if (['nombre', 'sigla', 'fecha_creacion', 'ci_unique', 'hostname', 'ip_address'].includes(attr.name)) return;
            
            let choices = [];
            if (attr.multiselect_values) {
                choices = attr.multiselect_values.split(',').map(s => s.trim()).filter(s => s);
            }
            
            allProps[attr.name] = {
                title: attr.name.charAt(0).toUpperCase() + attr.name.slice(1).replace(/_/g, ' '),
                type: attr.type || 'string',
                group: attr.group_name || 'General',
                description: attr.description || '',
                choices: choices,
                enum: (attr.type === 'enum' || attr.type === 'select') ? choices : null
            };
            if (attr.is_required == 1) {
                requiredFields.push(attr.name);
            }
        });
    }

    // 2. Category schema attributes from lineage
    if (lineage && Array.isArray(lineage)) {
        lineage.forEach(cat => {
            let schema = {};
            try {
                schema = typeof cat.schema_json === 'string' ? JSON.parse(cat.schema_json) : cat.schema_json;
            } catch(e) { }

            if (schema && schema.properties) {
                for (let key in schema.properties) {
                    if (['nombre', 'sigla', 'fecha_creacion', 'ci_unique', 'hostname', 'ip_address'].includes(key)) continue;
                    let prop = schema.properties[key];
                    allProps[key] = {
                        ...allProps[key],
                        ...prop,
                        group: prop.group || (allProps[key] ? allProps[key].group : 'Especificaciones')
                    };
                }
                if (schema.required) {
                    requiredFields = requiredFields.concat(schema.required);
                }
            }
        });
    }

    // 3. Additional attributes stored in ci.attributes_json
    if (ci && ci.attributes_json) {
        let attrs = {};
        try { attrs = typeof ci.attributes_json === 'string' ? JSON.parse(ci.attributes_json) : (ci.attributes_json || {}); } catch(e) {}
        for (let key in attrs) {
            if (['nombre', 'sigla', 'fecha_creacion', 'ci_unique', 'hostname', 'ip_address', 'status', 'source', 'description', 'zabbix_host_id', 'parent_ci_id', 'rack_id', 'rack_start_u', 'rack_height_u', 'rack_orientation', 'rack_color', 'rack_depth', 'manual_survey_id'].includes(key)) continue;
            if (!allProps[key]) {
                let val = attrs[key];
                allProps[key] = {
                    title: key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' '),
                    type: typeof val === 'boolean' ? 'boolean' : (Array.isArray(val) ? 'multiselect' : 'string'),
                    group: 'General',
                    description: ''
                };
            }
        }
    }

    return { allProps, requiredFields };
}

function renderCIModal() {
    loadGlobalAttributes(function() {
        let ci = currentCIData.ci;
        let lineage = currentCIData.lineage;
        let relations = currentCIData.relations;
        let parent_chain = currentCIData.parent_chain;
        let images = currentCIData.images;
        
        if (isEditMode) {
            renderEditView(ci, lineage, relations, parent_chain, images);
        } else {
            renderDetailView(ci, lineage, relations, parent_chain, images);
        }
    });
}

function renderDetailView(ci, lineage, relations, parent_chain, images) {
    // Setup Prev/Next Navigation
    let visibleIds = $('.ci-row:visible').map(function() { return $(this).data('id'); }).get();
    let currentIndex = visibleIds.indexOf(ci.id);
    let prevId = currentIndex > 0 ? visibleIds[currentIndex - 1] : null;
    let nextId = currentIndex < visibleIds.length - 1 ? visibleIds[currentIndex + 1] : null;

    let navHtml = `
        <button type="button" class="btn btn-sm btn-outline-light mr-2 font-weight-bold px-3" id="btn-prev-ci" style="border-radius: 20px;" ${prevId ? '' : 'disabled'}>
            <i class="fas fa-chevron-left mr-1"></i> Anterior
        </button>
        <button type="button" class="btn btn-sm btn-outline-light mr-2 font-weight-bold px-3" id="btn-next-ci" style="border-radius: 20px;" ${nextId ? '' : 'disabled'}>
            Siguiente <i class="fas fa-chevron-right ml-1"></i>
        </button>
        <button type="button" class="btn btn-sm btn-outline-light mr-2 border-0" id="btn-maximize-modal" title="Pantalla Completa" style="opacity: 0.8; outline: none; background: transparent; color: white;">
            <i class="fas fa-compress"></i>
        </button>
        <button type="button" class="btn btn-sm btn-danger text-white font-weight-bold px-4" data-dismiss="modal" style="border-radius: 20px;">
            <i class="fas fa-times mr-1"></i> Cerrar
        </button>
    `;
    $('#attrModal .ml-auto').html(navHtml);
    
    // Re-bind navigation events
    $('#btn-prev-ci').off('click').on('click', function() { if (prevId) viewCIDetails(prevId); });
    $('#btn-next-ci').off('click').on('click', function() { if (nextId) viewCIDetails(nextId); });

    // Extract all properties from lineage schema and system attributes
    let { allProps } = getAllCIProperties(ci, lineage);
    let attrs = {};
    try { attrs = typeof ci.attributes_json === 'string' ? JSON.parse(ci.attributes_json) : (ci.attributes_json || {}); } catch(e) {}
    
    let groups = {};
    for(let key in allProps) {
        if (['nombre', 'sigla', 'fecha_creacion', 'ci_unique', 'hostname', 'ip_address', 'status', 'source', 'description'].includes(key)) continue;
        let prop = allProps[key];
        let groupName = prop.group || 'General';
        if(!groups[groupName]) groups[groupName] = {};
        groups[groupName][key] = prop;
    }
    
    let groupKeys = Object.keys(groups).sort();
    
    // Base Attributes UI
    let baseFieldsHtml = `
        <div class="row pt-2">
            <div class="col-md-4 mb-3 pb-2 border-bottom">
                <div class="detail-card-label text-muted font-weight-bold" style="font-size:0.75rem; text-transform:uppercase;">Código Único CI</div>
                <div class="detail-card-value font-weight-bold text-monospace"><span class="badge badge-dark px-2.5 py-1.5" style="font-size: 0.85rem;">${ci.ci_unique || 'SND-XXXXXXXXXX'}</span></div>
            </div>
            <div class="col-md-4 mb-3 pb-2 border-bottom">
                <div class="detail-card-label text-muted font-weight-bold" style="font-size:0.75rem; text-transform:uppercase;">Nombre / Hostname</div>
                <div class="detail-card-value font-weight-bold text-dark" style="font-size: 0.95rem;">${ci.hostname}</div>
            </div>
            <div class="col-md-4 mb-3 pb-2 border-bottom">
                <div class="detail-card-label text-muted font-weight-bold" style="font-size:0.75rem; text-transform:uppercase;">Dirección IP</div>
                <div class="detail-card-value font-weight-bold text-primary" style="font-size: 0.95rem;"><i class="fas fa-network-wired mr-1.5"></i>${ci.ip_address || '<span class="text-muted font-italic">N/D</span>'}</div>
            </div>
            <div class="col-md-4 mb-3 pb-2 border-bottom">
                <div class="detail-card-label text-muted font-weight-bold" style="font-size:0.75rem; text-transform:uppercase;">Categoría</div>
                <div class="detail-card-value"><span class="badge badge-info px-2.5 py-1.5 text-uppercase" style="font-size: 0.8rem;">${ci.category_name}</span></div>
            </div>
            <div class="col-md-4 mb-3 pb-2 border-bottom">
                <div class="detail-card-label text-muted font-weight-bold" style="font-size:0.75rem; text-transform:uppercase;">Sigla / Código</div>
                <div class="detail-card-value"><span class="badge badge-secondary px-2.5 py-1.5" style="font-size: 0.8rem;">${ci.sigla || '-'}</span></div>
            </div>
            <div class="col-md-4 mb-3 pb-2 border-bottom">
                <div class="detail-card-label text-muted font-weight-bold" style="font-size:0.75rem; text-transform:uppercase;">Estado</div>
                <div class="detail-card-value"><span class="badge badge-success px-2.5 py-1.5" style="font-size: 0.8rem;"><i class="fas fa-check-circle mr-1"></i>${ci.status || 'Activo'}</span></div>
            </div>
            <div class="col-md-4 mb-3 pb-2 border-bottom">
                <div class="detail-card-label text-muted font-weight-bold" style="font-size:0.75rem; text-transform:uppercase;">Origen</div>
                <div class="detail-card-value">${ci.source === 'zabbix' ? '<span class="badge badge-danger px-2.5 py-1.5" style="font-size: 0.8rem;"><i class="fas fa-server mr-1"></i> Zabbix</span>' : '<span class="badge badge-primary px-2.5 py-1.5" style="font-size: 0.8rem;"><i class="fas fa-keyboard mr-1"></i> Manual</span>'}</div>
            </div>
            <div class="col-md-4 mb-3 pb-2 border-bottom">
                <div class="detail-card-label text-muted font-weight-bold" style="font-size:0.75rem; text-transform:uppercase;">Fecha Registro</div>
                <div class="detail-card-value text-muted" style="font-size: 0.9rem;"><i class="far fa-calendar-alt mr-1"></i>${ci.created_at ? new Date(ci.created_at).toLocaleString('es-ES') : '-'}</div>
            </div>
            <div class="col-md-4 mb-3 pb-2 border-bottom">
                <div class="detail-card-label text-muted font-weight-bold" style="font-size:0.75rem; text-transform:uppercase;">Registrado Por</div>
                <div class="detail-card-value text-muted" style="font-size: 0.9rem;"><i class="far fa-user mr-1"></i>${ci.creator_name || 'Desconocido'}</div>
            </div>
            <div class="col-12 mt-2">
                <div class="detail-card-label text-muted font-weight-bold" style="font-size:0.75rem; text-transform:uppercase;">Descripción</div>
                <div class="p-3 bg-light rounded text-muted" style="font-size: 0.9rem; border: 1px solid #e9ecef;">${ci.description || 'Sin descripción registrada.'}</div>
            </div>
        </div>
    `;

    let tabsHtml = '<ul class="nav modal-nav-tabs w-100" role="tablist" id="modalDetailTabs">';
    let contentHtml = '<div class="tab-content w-100" id="modalDetailTabsContent">';
    
    tabsHtml += `
        <li class="nav-item">
            <a class="nav-link active" data-toggle="tab" href="#view-base-attrs" role="tab">
                <i class="fas fa-info-circle mr-1 text-primary"></i> Atributos Base
            </a>
        </li>`;
    
    contentHtml += `
        <div class="tab-pane fade show active" id="view-base-attrs" role="tabpanel">
            ${baseFieldsHtml}
        </div>`;
    
    groupKeys.forEach((groupName, index) => {
        let safeId = groupName.replace(/[^a-zA-Z0-9]/g, '-').toLowerCase() + '-' + index;
        let iconClass = getGroupIcon(groupName);
        tabsHtml += `
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#view-${safeId}" role="tab">
                    <i class="fas ${iconClass} mr-1"></i> ${groupName}
                </a>
            </li>`;
        
        contentHtml += `<div class="tab-pane fade" id="view-${safeId}" role="tabpanel">`;
        contentHtml += `<div class="row pt-2">`;
        
        let props = groups[groupName] || {};
        for(let key in props) {
            let prop = props[key];
            let rawVal = attrs[key];
            let val = '<span class="text-muted font-italic">N/D</span>';
            
            if (rawVal !== undefined && rawVal !== '') {
                if (prop.type === 'boolean') {
                    val = (rawVal == 1 || rawVal === '1' || rawVal === true) ? '<span class="badge badge-success px-2.5 py-1.5"><i class="fas fa-check mr-1"></i>Sí</span>' : '<span class="badge badge-secondary px-2.5 py-1.5"><i class="fas fa-times mr-1"></i>No</span>';
                } else if (prop.type === 'image') {
                    val = `<div class="my-1"><a href="${rawVal}" target="_blank" class="shadow-sm rounded"><img src="${rawVal}" style="max-height: 80px; border-radius: 4px;"></a></div>`;
                } else if (prop.type === 'multiselect') {
                    let arr = Array.isArray(rawVal) ? rawVal : (typeof rawVal === 'string' ? rawVal.split(',') : []);
                    val = '';
                    arr.forEach(item => {
                        item = item.trim();
                        if (item) val += `<span class="badge badge-dark mr-1 mb-1 px-2 py-1">${item}</span>`;
                    });
                    if (!val) val = '<span class="text-muted font-italic">N/D</span>';
                } else {
                    val = Array.isArray(rawVal) ? rawVal.join(', ') : rawVal;
                }
            }
            
            let label = prop.title || key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' ');
            contentHtml += `
                <div class="col-md-6 mb-3">
                    <div class="p-3 border rounded h-100 bg-white shadow-xs">
                        <div class="detail-card-label text-muted font-weight-bold mb-1.5" style="font-size:0.75rem; text-transform:uppercase;">${label}</div>
                        <div class="detail-card-value font-weight-bold text-dark" style="font-size:0.9rem;">${val}</div>
                    </div>
                </div>`;
        }
        contentHtml += `</div></div>`;
    });

    // Thematic groups mapping
    let thematicTabs = {
        'Ubicación': [],
        'Personal / Contacto': [],
        'Facility': [],
        'Hardware / Infraestructura': [],
        'Servicios / Software': [],
        'Otros / Relacionados': []
    };

    function getThematicGroup(categoryName) {
        if (!categoryName) return 'Otros / Relacionados';
        let catLower = categoryName.toLowerCase();
        const groupMappings = {
            'Ubicación': ['país', 'pais', 'ciudad', 'datacenter', 'rack', 'ubicación', 'geografía', 'sector', 'edificio', 'localidad', 'área', 'area', 'cuarto', 'localidades'],
            'Personal / Contacto': ['personal', 'soporte', 'propietario', 'contacto', 'proveedor', 'usuario'],
            'Facility': ['facility', 'eléctrico', 'aire', 'climatización', 'energía', 'ups', 'pdu', 'batería', 'chiller', 'tablero'],
            'Hardware / Infraestructura': ['servidor', 'storage', 'switch', 'router', 'firewall', 'chasis', 'blade', 'hardware', 'equipo', 'monitoreo', 'red'],
            'Servicios / Software': ['servicio', 'software', 'sistema operativo', 'base de datos', 'aplicación', 'api', 'licencia', 'vlan']
        };
        for (let group in groupMappings) {
            if (groupMappings[group].some(keyword => catLower.includes(keyword))) return group;
        }
        return 'Otros / Relacionados';
    }

    if (parent_chain && parent_chain.length > 0) {
        parent_chain.forEach(pci => {
            thematicTabs[getThematicGroup(pci.category_name)].push({type: 'parent', ...pci});
        });
    }
    if (relations && relations.length > 0) {
        relations.forEach(r => {
            thematicTabs[getThematicGroup(r.target_category_name)].push({
                type: 'relation', 
                id: r.target_id, 
                hostname: r.target_name, 
                category_name: r.target_category_name || 'Relación',
                relation_type: r.relation_type
            });
        });
    }

    Object.keys(thematicTabs).forEach((groupName) => {
        let items = thematicTabs[groupName];
        if (items.length === 0 && !['Ubicación', 'Personal / Contacto', 'Facility'].includes(groupName)) return;
        
        let safeGroupId = groupName.replace(/[^a-zA-Z0-9]/g, '-').toLowerCase();
        let iconClass = 'fa-link';
        if (groupName === 'Ubicación') iconClass = 'fa-map-marker-alt text-danger';
        else if (groupName === 'Personal / Contacto') iconClass = 'fa-users text-primary';
        else if (groupName === 'Facility') iconClass = 'fa-building text-warning';
        else if (groupName === 'Hardware / Infraestructura') iconClass = 'fa-server text-info';
        else if (groupName === 'Servicios / Software') iconClass = 'fa-laptop-code text-success';

        tabsHtml += `
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#view-theme-${safeGroupId}" role="tab">
                    <i class="fas ${iconClass} mr-1"></i> ${groupName} (${items.length})
                </a>
            </li>`;
        
        contentHtml += `<div class="tab-pane fade" id="view-theme-${safeGroupId}" role="tabpanel">`;
        contentHtml += `<div class="row pt-2">`;
        
        if (items.length === 0) {
            contentHtml += `
                <div class="col-12 text-center py-4 text-muted bg-light rounded border m-2" style="border-style: dashed !important;">
                    <i class="fas fa-info-circle mr-1 text-warning"></i> N/A (No seleccionado / asociado)
                </div>`;
        } else {
            items.forEach((item) => {
                let relBadge = item.type === 'relation' ? ` <span class="badge badge-light border text-monospace ml-1.5">${item.relation_type || 'Relación'}</span>` : '';
                contentHtml += `
                    <div class="col-md-6 mb-3">
                        <div class="p-3 border rounded h-100 bg-white shadow-xs">
                            <div class="detail-card-label text-muted font-weight-bold mb-1.5" style="font-size:0.75rem; text-transform:uppercase;">${item.category_name}</div>
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="font-weight-bold text-primary" style="font-size: 0.95rem;">${item.hostname}${relBadge}</span>
                                <a href="javascript:void(0)" onclick="viewCIDetails(${item.id})" class="text-muted" title="Ver CI"><i class="fas fa-search-plus"></i></a>
                            </div>
                        </div>
                    </div>`;
            });
        }
        contentHtml += `</div></div>`;
    });
    
    // Class Hierarchy
    tabsHtml += `
        <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#view-hierarchy" role="tab">
                <i class="fas fa-sitemap mr-1 text-secondary"></i> Jerarquía
            </a>
        </li>`;
    
    contentHtml += `<div class="tab-pane fade" id="view-hierarchy" role="tabpanel"><div class="pt-2"><div class="card border p-4 shadow-xs">`;
    lineage.forEach((cat, idx) => {
        let isLast = idx === lineage.length - 1;
        contentHtml += `
            <div class="d-flex align-items-center mb-2">
                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center shadow-sm font-weight-bold" style="width:32px; height:32px; min-width:32px;">${idx + 1}</div>
                <div class="ml-3">
                    <span class="${isLast ? 'text-primary font-weight-bold' : 'text-muted'}" style="font-size: 1.05rem;">${cat.name}</span>
                </div>
            </div>
            ${!isLast ? '<div class="border-left ml-3 my-1" style="height: 20px; border-width: 2px !important; border-color: #dee2e6 !important;"></div>' : ''}
        `;
    });
    contentHtml += `</div></div></div>`;
    
    // Zabbix monitoring tab
    if (ci.zabbix_host_id) {
        tabsHtml += `
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#view-zabbix-monitoring" role="tab" id="tab-zabbix-monit">
                    <i class="fas fa-heartbeat text-danger"></i> Monitoreo Real-time
                </a>
            </li>`;
        
        contentHtml += `
            <div class="tab-pane fade" id="view-zabbix-monitoring" role="tabpanel">
                <div class="pt-3">
                    <div id="zabbix-modal-loading" class="text-center py-5">
                        <i class="fas fa-spinner fa-spin fa-3x text-primary mb-3"></i>
                        <h5>Consultando métricas en Zabbix...</h5>
                    </div>
                    <div id="zabbix-modal-content" style="display:none;">
                        <div class="row">
                            <div class="col-lg-6 mb-3">
                                <h6 class="font-weight-bold text-dark mb-3"><i class="fas fa-exclamation-triangle text-danger mr-2"></i>Alarmas Activas</h6>
                                <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                    <table class="table table-sm table-striped table-bordered mb-0" id="tbl-zabbix-modal-triggers" style="font-size: 0.8rem;">
                                        <thead class="thead-dark">
                                            <tr>
                                                <th>Descripción</th>
                                                <th style="width: 30%">Severidad</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr><td colspan="2" class="text-center py-3 text-muted">Cargando...</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-lg-6 mb-3">
                                <h6 class="font-weight-bold text-dark mb-3"><i class="fas fa-chart-line text-info mr-2"></i>Últimas Métricas</h6>
                                <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                    <table class="table table-sm table-striped table-bordered mb-0" id="tbl-zabbix-modal-items" style="font-size: 0.8rem;">
                                        <thead class="thead-dark">
                                            <tr>
                                                <th>Métrica</th>
                                                <th>Valor</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr><td colspan="2" class="text-center py-3 text-muted">Cargando...</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>`;
    }
    
    // Portmapping tab
    if (ci.zabbix_host_id || ci.category_id == 45 || (ci.category_name && (ci.category_name.toLowerCase().includes('switch') || ci.category_name.toLowerCase().includes('router') || ci.category_name.toLowerCase().includes('firewall') || ci.category_name.toLowerCase().includes('red') || ci.category_name.toLowerCase().includes('comunicaciones')))) {
        tabsHtml += `
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#view-portmapping" role="tab" id="tab-portmapping-detail">
                    <i class="fas fa-network-wired text-primary"></i> Portmapping
                </a>
            </li>`;
        
        contentHtml += `
            <div class="tab-pane fade" id="view-portmapping" role="tabpanel">
                <div class="pt-3">
                    <div id="portmapping-modal-loading" class="text-center py-5">
                        <i class="fas fa-spinner fa-spin fa-3x text-primary mb-3"></i>
                        <h5>Cargando portmapping...</h5>
                    </div>
                    <div id="portmapping-modal-content" style="display:none;">
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                            <h6 class="font-weight-bold text-dark mb-0"><i class="fas fa-project-diagram text-primary mr-2"></i>Mapeo de Puertos</h6>
                            <div>
                                <button class="btn btn-xs btn-outline-success font-weight-bold mr-1" id="btn-pm-export-excel"><i class="fas fa-file-excel mr-1"></i> Excel</button>
                                <button class="btn btn-xs btn-outline-danger font-weight-bold" id="btn-pm-export-pdf"><i class="fas fa-file-pdf mr-1"></i> PDF</button>
                            </div>
                        </div>
                        <div id="portmapping-groups-accordion">
                            <!-- Puertos agrupados por Gi, Fa, Te, Vlan, Power, etc. -->
                        </div>
                    </div>
                </div>
            </div>`;
    }

    // Levantamiento Físico tab if attrs.manual_survey_id exists
    if (attrs.manual_survey_id) {
        tabsHtml += `
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#view-manual-survey" role="tab" id="tab-manual-survey-detail">
                    <i class="fas fa-camera text-success"></i> Levantamiento Físico
                </a>
            </li>`;
        
        contentHtml += `
            <div class="tab-pane fade" id="view-manual-survey" role="tabpanel">
                <div class="pt-3">
                    <div id="manual-survey-modal-loading" class="text-center py-5">
                        <i class="fas fa-spinner fa-spin fa-3x text-primary mb-3"></i>
                        <h5>Cargando levantamiento físico...</h5>
                    </div>
                    <div id="manual-survey-modal-content" style="display:none;">
                        <!-- Observations & General Info -->
                        <div class="card p-3 mb-4 bg-light border rounded">
                            <h6 class="font-weight-bold text-dark mb-2"><i class="fas fa-comment-alt text-muted mr-1"></i> Observaciones Generales</h6>
                            <p class="mb-0 text-muted" id="ms-obs-detail">Sin observaciones.</p>
                        </div>
                        <!-- Images Gallery -->
                        <h6 class="font-weight-bold text-dark mb-3"><i class="fas fa-images text-muted mr-1"></i> Imágenes del Levantamiento</h6>
                        <div class="row" id="ms-images-gallery-detail">
                            <!-- Dynamically loaded -->
                        </div>
                    </div>
                </div>
            </div>`;
    }
    
    tabsHtml += '</ul>';
    contentHtml += '</div>';

    let googlemapsLink = ci.googlemaps || attrs.googlemaps || attrs.google_maps || attrs.mapa || '';
    let imagesHtml = '';
    if (images && images.length > 0) {
        imagesHtml = `
            <div class="gallery-container">
                ${images.map(img => `
                    <div class="gallery-item" onclick="window.open('${img.filepath}', '_blank')">
                        <img src="${img.filepath}" alt="Foto">
                        <button class="btn btn-danger btn-xs btn-floating-delete delete-ci-image-btn" data-image-id="${img.id}" onclick="event.stopPropagation()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `).join('')}
            </div>
        `;
    } else {
        imagesHtml = `
            <div class="text-center py-4 text-muted w-100 opacity-50">
                <i class="fas fa-image fa-2x mb-2"></i>
                <p class="small mb-0">Sin fotos adjuntas</p>
            </div>
        `;
    }

    let rightColHtml = `
        <div class="card premium-card mb-4 border shadow-sm">
            <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 font-weight-bold text-dark" style="font-size:1.05rem;"><i class="fas fa-camera mr-2 text-primary"></i> Galería de Fotos</h5>
                <button class="btn btn-xs btn-outline-primary" onclick="document.getElementById('ci-image-input').click()"><i class="fas fa-plus mr-1"></i>Subir</button>
            </div>
            <div class="card-body p-4">
                <div id="ci-image-gallery">${imagesHtml}</div>
                <form id="ci-image-upload-form" class="d-none">
                    <input type="file" id="ci-image-input" name="image" accept="image/*">
                </form>
            </div>
        </div>
        <div id="ci-map-section" style="${googlemapsLink ? 'display:block;' : 'display:none;'}" class="card premium-card border mb-4 shadow-sm">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="mb-0 font-weight-bold text-dark" style="font-size:1.05rem;"><i class="fas fa-map-marker-alt mr-2 text-danger"></i> Geolocalización</h5>
            </div>
            <div class="card-body p-0">
                <div id="ci-map-container" class="map-wrapper"></div>
            </div>
        </div>
    `;

    // Main layout: Tabs at top (full-width), data/card on left, gallery/map on right
    let mainHtml = `
        <div class="container-fluid p-4 text-left">
            <div class="row align-items-center mb-4 border-bottom pb-3">
                <div class="col">
                    <h2 class="h3 mb-0 font-weight-bold text-primary"><i class="fas fa-microchip mr-2"></i> Expediente Técnico: ${ci.hostname}</h2>
                    <p class="text-muted small mb-0">Detalles de CI en la categoría <strong>${ci.category_name}</strong></p>
                </div>
                <div class="col-auto">
                    <div class="btn-group shadow-sm">
                        <button class="btn btn-primary font-weight-bold px-3" onclick="toggleCIEditMode(true)"><i class="fas fa-edit mr-1.5"></i> Editar CI</button>
                        <button class="btn btn-danger font-weight-bold px-3" onclick="deleteCI(${ci.id})"><i class="fas fa-trash-alt mr-1.5"></i> Eliminar</button>
                        <button class="btn btn-secondary font-weight-bold px-3" data-dismiss="modal"><i class="fas fa-times mr-1.5"></i> Cerrar</button>
                    </div>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-12">
                    ${tabsHtml}
                </div>
            </div>
            <div class="row">
                <div class="col-lg-8 mb-4">
                    <div class="card border shadow-sm h-100" style="border-radius: 12px;">
                        <div class="card-body">
                            ${contentHtml}
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    ${rightColHtml}
                </div>
            </div>
        </div>
    `;
    
    $('#attrModalBody').html(mainHtml);
    updateCIGoogleMapsView(googlemapsLink);

    // Bind shown Zabbix tab event
    if (ci.zabbix_host_id) {
        $(document).off('shown.bs.tab', '#tab-zabbix-monit');
        $(document).on('shown.bs.tab', '#tab-zabbix-monit', function() {
            $('#zabbix-modal-loading').show();
            $('#zabbix-modal-content').hide();
            
            $.getJSON('informes/process_alcance.php', { action: 'get_host_items_triggers', hostid: ci.zabbix_host_id }, function(resp) {
                $('#zabbix-modal-loading').hide();
                if (!resp.success) {
                    $('#tbl-zabbix-modal-triggers tbody').html('<tr><td colspan="2" class="text-center text-danger">Error al consultar datos</td></tr>');
                    $('#tbl-zabbix-modal-items tbody').html('<tr><td colspan="2" class="text-center text-danger">Error al consultar datos</td></tr>');
                    $('#zabbix-modal-content').show();
                    return;
                }

                const getSeverityBadgeLocal = (priority) => {
                    const p = parseInt(priority);
                    const severities = {
                        0: { name: 'No clasificado', class: 'badge-secondary' },
                        1: { name: 'Información',   class: 'badge-info' },
                        2: { name: 'Advertencia',   class: 'badge-warning' },
                        3: { name: 'Promedio',      class: 'badge-primary' },
                        4: { name: 'Alta',          class: 'badge-danger' },
                        5: { name: 'Desastre',      class: 'badge-dark' }
                    };
                    const sev = severities[p] || { name: 'Desconocida', class: 'badge-secondary' };
                    return `<span class="badge ${sev.class} px-2 py-1 font-weight-bold text-uppercase" style="font-size: 0.75rem">${sev.name}</span>`;
                };

                let trigHtml = '';
                if (resp.triggers && resp.triggers.length > 0) {
                    resp.triggers.forEach(t => {
                        trigHtml += `
                            <tr>
                                <td>${t.description}</td>
                                <td class="text-center">${getSeverityBadgeLocal(t.priority)}</td>
                            </tr>`;
                    });
                } else {
                    trigHtml = '<tr><td colspan="2" class="text-center py-3 text-success"><i class="fas fa-check-circle mr-1"></i> Sin alarmas activas</td></tr>';
                }
                $('#tbl-zabbix-modal-triggers tbody').html(trigHtml);
                
                let itemHtml = '';
                if (resp.items && resp.items.length > 0) {
                    resp.items.slice(0, 30).forEach(it => {
                        let val = it.lastvalue !== undefined ? `${it.lastvalue} ${it.units || ''}` : 'N/A';
                        itemHtml += `
                            <tr>
                                <td class="font-weight-bold">${it.name}</td>
                                <td class="text-primary font-weight-bold text-monospace">${val}</td>
                            </tr>`;
                    });
                } else {
                    itemHtml = '<tr><td colspan="2" class="text-center py-3 text-muted">Sin métricas registradas</td></tr>';
                }
                $('#tbl-zabbix-modal-items tbody').html(itemHtml);
                
                $('#zabbix-modal-content').fadeIn();
            }).fail(function() {
                $('#zabbix-modal-loading').hide();
                $('#tbl-zabbix-modal-triggers tbody').html('<tr><td colspan="2" class="text-center text-danger">Error de comunicación</td></tr>');
                $('#tbl-zabbix-modal-items tbody').html('<tr><td colspan="2" class="text-center text-danger">Error de comunicación</td></tr>');
                $('#zabbix-modal-content').show();
            });
        });
    }
    
    // Bind shown Portmapping tab event
    if (ci.zabbix_host_id || ci.category_id == 45 || (ci.category_name && (ci.category_name.toLowerCase().includes('switch') || ci.category_name.toLowerCase().includes('router') || ci.category_name.toLowerCase().includes('firewall') || ci.category_name.toLowerCase().includes('red') || ci.category_name.toLowerCase().includes('comunicaciones')))) {
        $(document).off('shown.bs.tab', '#tab-portmapping-detail');
        $(document).on('shown.bs.tab', '#tab-portmapping-detail', function() {
            $('#portmapping-modal-loading').show();
            $('#portmapping-modal-content').hide();
            loadPortmappingDetail(ci.id, ci.hostname);
        });
    }

    // Bind shown Levantamiento Físico event
    if (attrs.manual_survey_id) {
        $(document).off('shown.bs.tab', '#tab-manual-survey-detail');
        $(document).on('shown.bs.tab', '#tab-manual-survey-detail', function() {
            $('#manual-survey-modal-loading').show();
            $('#manual-survey-modal-content').hide();
            loadManualSurveyDetail(attrs.manual_survey_id);
        });
    }

    // Photo uploading setup
    setupPhotoUploadEvents(ci.id);
}

function renderEditView(ci, lineage, relations, parent_chain, images) {
    pendingRelations = relations.map(r => ({
        target_id: r.target_id,
        target_name: r.target_name,
        type: r.relation_type,
        impact: r.impact
    }));

    let isCreateMode = !ci.id;

    if (isCreateMode && (!ci.category_id || ci.category_id == 0)) {
        let mainPickerHtml = `
            <div class="container-fluid p-4 text-left">
                ${renderCategoryTreePickerHTML(0)}
            </div>
        `;
        $('#attrModalBody').html(mainPickerHtml);
        $('#attrModal .ml-auto').html(`
            <button type="button" class="btn btn-sm btn-secondary font-weight-bold px-4" onclick="$('#attrModal').modal('hide')" style="border-radius: 20px;">
                <i class="fas fa-times mr-1.5"></i> Cancelar
            </button>
        `);
        return;
    }

    // Setup action buttons in modal header area
    let navHtml = `
        <button type="button" class="btn btn-sm btn-success font-weight-bold px-4 mr-2" onclick="saveCIChanges()" style="border-radius: 20px;">
            <i class="fas fa-save mr-1.5"></i> ${isCreateMode ? 'Crear CI' : 'Guardar'}
        </button>
        <button type="button" class="btn btn-sm btn-secondary font-weight-bold px-4" onclick="${isCreateMode ? "$('#attrModal').modal('hide')" : 'toggleCIEditMode(false)'}" style="border-radius: 20px;">
            <i class="fas fa-times mr-1.5"></i> Cancelar
        </button>
    `;
    $('#attrModal .ml-auto').html(navHtml);

    // Extract all properties from lineage schema and system attributes
    let { allProps: allProperties, requiredFields } = getAllCIProperties(ci, lineage);

    let targetCat = lineage[lineage.length - 1];
    let dependenciesList = [];
    if (targetCat && targetCat.dependencies && targetCat.dependencies.length > 0) {
        targetCat.dependencies.forEach(dep => {
            let rootCat = categories.find(c => c.id == dep.target_category_id);
            if (rootCat) {
                let depChain = getDependencyChain(dep.target_category_id);
                dependenciesList.push({
                    root: rootCat,
                    chain: depChain,
                    depType: dep.dependency_type
                });
            }
        });
    }

    let attrValues = {};
    if (ci && ci.attributes_json) {
        try {
            attrValues = typeof ci.attributes_json === 'string' ? JSON.parse(ci.attributes_json) : (ci.attributes_json || {});
        } catch(e) {}
    }

    let groups = {};
    for (let key in allProperties) {
        if (['nombre', 'sigla', 'fecha_creacion', 'ci_unique', 'hostname', 'ip_address', 'status', 'source', 'description'].includes(key)) continue;
        let prop = allProperties[key];
        let groupName = prop.group || 'General';
        if (!groups[groupName]) groups[groupName] = {};
        groups[groupName][key] = prop;
    }

    function buildGroupInputsHtml(propsObj) {
        let html = '';
        for (let key in propsObj) {
            if (['nombre', 'sigla', 'fecha_creacion', 'ci_unique', 'hostname', 'ip_address', 'status', 'source', 'description'].includes(key)) continue;
            
            let prop = propsObj[key];
            let isRequired = requiredFields.includes(key) || prop.required;
            let reqMark = isRequired ? '<span class="text-danger">*</span>' : '';
            let label = prop.title || (key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' '));
            if (prop.description) label += ` <small class="text-muted">(${escapeHtml(prop.description)})</small>`;
            
            let curVal = (attrValues && attrValues[key] !== undefined) ? attrValues[key] : ((ci && ci[key] !== undefined) ? ci[key] : '');
            if (curVal === null || curVal === undefined) curVal = '';

            let inputHtml = '';
            let choices = prop.choices || prop.enum || [];
            if (typeof choices === 'string') {
                choices = choices.split(',').map(s => s.trim()).filter(s => s);
            }

            if (choices && choices.length > 0) {
                let isMulti = prop.type === 'multiselect';
                let selectedVals = Array.isArray(curVal) ? curVal : (typeof curVal === 'string' ? curVal.split(',').map(s => s.trim()) : [curVal]);
                inputHtml = `<select name="${key}${isMulti ? '[]' : ''}" class="form-control ${isMulti ? 'select2-multi' : ''}" ${isMulti ? 'multiple' : ''} ${isRequired ? 'data-required="true"' : ''}>`;
                if (!isMulti) inputHtml += `<option value="">Seleccionar...</option>`;
                choices.forEach(val => {
                    let sel = selectedVals.includes(val) ? 'selected' : '';
                    inputHtml += `<option value="${escapeHtml(val)}" ${sel}>${escapeHtml(val)}</option>`;
                });
                inputHtml += `</select>`;
            } else if (prop.type === 'boolean') {
                let sel1 = (curVal === '1' || curVal === 1 || curVal === true || curVal === 'true' || curVal === 'Sí') ? 'selected' : '';
                let sel0 = (curVal === '0' || curVal === 0 || curVal === false || curVal === 'false' || curVal === 'No') ? 'selected' : '';
                inputHtml = `<select name="${key}" class="form-control" ${isRequired ? 'data-required="true"' : ''}>
                    <option value="">Seleccionar...</option>
                    <option value="1" ${sel1}>Sí</option>
                    <option value="0" ${sel0}>No</option>
                </select>`;
            } else if (prop.type === 'integer' || prop.type === 'number') {
                inputHtml = `<input type="number" name="${key}" class="form-control" value="${escapeHtml(curVal)}" ${isRequired ? 'data-required="true"' : ''}>`;
            } else if (prop.type === 'textarea') {
                inputHtml = `<textarea name="${key}" class="form-control" rows="2" ${isRequired ? 'data-required="true"' : ''}>${escapeHtml(curVal)}</textarea>`;
            } else if (prop.type === 'date' || prop.format === 'date') {
                inputHtml = `<input type="date" name="${key}" class="form-control" value="${escapeHtml(curVal)}" ${isRequired ? 'data-required="true"' : ''}>`;
            } else if (prop.type === 'image') {
                let hasImg = typeof curVal === 'string' && curVal.trim() !== '';
                inputHtml = `
                    <input type="hidden" name="${key}" id="img_val_${key}" value="${escapeHtml(curVal)}" class="image-filepath-input" ${isRequired ? 'data-required="true"' : ''}>
                    <input type="file" class="form-control-file attr-image-file-input" id="img_file_${key}" data-key="${key}" accept="image/*" style="${hasImg ? 'display:none;' : ''}">
                    <div id="img_preview_container_${key}" class="mt-2" style="${hasImg ? '' : 'display:none;'} position: relative; max-width: 180px;">
                        <img src="${hasImg ? escapeHtml(curVal) : ''}" id="img_preview_img_${key}" class="img-thumbnail img-fluid" style="max-height: 150px; border: 2px solid #ddd; border-radius: 6px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                        <button type="button" class="btn btn-sm btn-danger position-absolute btn-remove-attr-image" data-key="${key}" style="top: 5px; right: 5px; border-radius: 50%; width: 28px; height: 28px; padding: 0;" title="Eliminar Imagen"><i class="fas fa-trash-alt" style="font-size: 0.8rem;"></i></button>
                    </div>
                    <div id="img_spinner_${key}" class="mt-2 text-primary font-weight-bold" style="display:none;">
                        <i class="fas fa-spinner fa-spin mr-1"></i> Subiendo imagen...
                    </div>
                `;
            } else {
                inputHtml = `<input type="text" name="${key}" class="form-control" value="${escapeHtml(curVal)}" ${isRequired ? 'data-required="true"' : ''}>`;
            }

            html += `
                <div class="col-md-6 form-group mt-2">
                    <label>${label} ${reqMark}</label>
                    ${inputHtml}
                </div>
            `;
        }
        return html;
    }

    let tabsHtml = '<ul class="nav modal-nav-tabs w-100" role="tablist" id="modalEditTabs">';
    let contentHtml = '<div class="tab-content w-100" id="modalEditTabsContent">';

    // Tab 1: Atributos Base
    tabsHtml += `
        <li class="nav-item">
            <a class="nav-link active" data-toggle="tab" href="#edit-base-attrs" role="tab">
                <i class="fas fa-info-circle mr-1 text-primary"></i> Atributos Base
            </a>
        </li>`;

    let zabbixAreaHtml = `
        <div class="row mb-4 bg-light p-3 rounded border">
            <div class="col-md-12 mb-3">
                <label class="font-weight-bold">Origen de Datos:</label>
                <div class="btn-group btn-group-toggle w-100" data-toggle="buttons">
                    <label class="btn btn-outline-primary ${ci.source !== 'zabbix' ? 'active' : ''}" style="width:50%">
                        <input type="radio" name="source" value="manual" ${ci.source !== 'zabbix' ? 'checked' : ''} autocomplete="off"> <i class="fas fa-keyboard"></i> Manual
                    </label>
                    <label class="btn btn-outline-primary ${ci.source === 'zabbix' ? 'active' : ''}" style="width:50%">
                        <input type="radio" name="source" value="zabbix" ${ci.source === 'zabbix' ? 'checked' : ''} autocomplete="off"> <i class="fas fa-server"></i> Zabbix
                    </label>
                </div>
            </div>
            
            <div id="zabbix-area-modal" class="col-md-12 ${ci.source === 'zabbix' ? '' : 'd-none'}">
                <div class="row">
                    <div class="col-md-5 form-group">
                        <label>Hostgroup Zabbix</label>
                        <select id="zabbix_hg_modal" class="form-control"></select>
                    </div>
                    <div class="col-md-5 form-group">
                        <label>Host Zabbix</label>
                        <select id="zabbix_h_modal" class="form-control" disabled></select>
                    </div>
                    <div class="col-md-2 form-group d-flex align-items-end">
                        <button type="button" class="btn btn-info w-100" id="btn-fetch-zabbix-modal" disabled><i class="fas fa-download"></i> Cargar</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    let categoryBannerHtml = `
        <div class="alert alert-light border border-primary d-flex align-items-center justify-content-between mb-4 shadow-sm" style="border-radius: 8px;">
            <div>
                <small class="text-muted d-block font-weight-bold uppercase" style="letter-spacing:0.5px;">Categoría del Elemento (CI):</small>
                <span class="h5 mb-0 font-weight-bold text-primary">
                    <i class="fas fa-cubes text-primary mr-1"></i> ${lineage.map(c => c.name).join(' <i class="fas fa-chevron-right mx-1 text-muted" style="font-size:0.75rem;"></i> ')}
                </span>
            </div>
            ${isCreateMode ? `<button type="button" class="btn btn-outline-primary btn-sm font-weight-bold" onclick="resetCategorySelection()"><i class="fas fa-exchange-alt mr-1"></i> Cambiar Categoría</button>` : ''}
        </div>
    `;

    let generalAttrsHtml = '';
    if (groups['Atributos Base']) generalAttrsHtml += buildGroupInputsHtml(groups['Atributos Base']);

    let baseFieldsEditHtml = `
        ${categoryBannerHtml}
        <input type="hidden" name="parent_ci_id" value="${ci.parent_ci_id || ''}">
        <div class="row">
            <div class="col-md-4 form-group">
                <label>Código Único (ci_unique)</label>
                <input type="text" class="form-control text-monospace font-weight-bold" name="ci_unique" readonly value="${ci.ci_unique || ''}">
            </div>
            <div class="col-md-4 form-group">
                <label>Nombre del CI <span class="text-danger">*</span></label>
                <input type="text" name="hostname" id="f_hostname" class="form-control" placeholder="Ej. Switch Principal" data-required="true" value="${ci.hostname || ''}">
            </div>
            <div class="col-md-4 form-group">
                <label>Sigla / Etiqueta <small class="text-muted">(Opcional)</small></label>
                <input type="text" name="sigla" id="f_sigla" class="form-control" placeholder="Ej. SW-CORE-01" value="${ci.sigla || ''}">
            </div>
            <div class="col-md-4 form-group mt-2">
                <label>Dirección IP</label>
                <input type="text" name="ip_address" id="f_ip" class="form-control" placeholder="Ej. 192.168.1.1" value="${ci.ip_address || ''}">
            </div>
            <div class="col-md-4 form-group mt-2">
                <label>Estado</label>
                <select name="status" class="form-control">
                    <option value="Activo" ${ci.status === 'Activo' ? 'selected' : ''}>Activo</option>
                    <option value="Inactivo" ${ci.status === 'Inactivo' ? 'selected' : ''}>Inactivo</option>
                    <option value="En Mantenimiento" ${ci.status === 'En Mantenimiento' ? 'selected' : ''}>En Mantenimiento</option>
                </select>
            </div>
            <div class="col-md-4 form-group mt-2">
                <label>Fecha de Creación</label>
                <input type="text" class="form-control" readonly value="${ci.created_at || ''}">
            </div>
            <div class="col-md-12 form-group mt-2">
                <label>Descripción</label>
                <textarea name="description" class="form-control" rows="2" placeholder="Información adicional del CI...">${ci.description || ''}</textarea>
            </div>
            ${generalAttrsHtml}
        </div>
    `;

    contentHtml = `
        <div class="tab-pane fade show active" id="edit-base-attrs" role="tabpanel">
            ${zabbixAreaHtml}
            ${baseFieldsEditHtml}
        </div>
    ` + contentHtml;

    // Dynamic schema tabs grouped strictly by 'group'
    let tabIndex = 1;
    let groupKeys = Object.keys(groups).sort();
    
    groupKeys.forEach((groupName) => {
        if (groupName === 'Atributos Base') return;
        
        let fieldsHtml = buildGroupInputsHtml(groups[groupName]);
        if (!fieldsHtml.trim()) return;
        
        let safeId = 'group-' + groupName.replace(/\s+/g, '-').toLowerCase() + '-' + tabIndex;
        let iconClass = getGroupIcon(groupName);
        tabsHtml += `
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#content-${safeId}" role="tab">
                    <i class="fas ${iconClass} mr-1"></i> ${groupName}
                </a>
            </li>`;

        contentHtml += `
            <div class="tab-pane fade" id="content-${safeId}" role="tabpanel">
                <div class="row pt-3 px-3">
                    ${fieldsHtml}
                </div>
            </div>`;
        tabIndex++;
    });

    // Thematic dependencies tabs (Ubicación, Personal, Facility, etc.)
    let thematicDeps = {
        'Ubicación': [],
        'Personal / Contacto': [],
        'Facility': [],
        'Hardware / Infraestructura': [],
        'Servicios / Software': [],
        'Otros / Relacionados': []
    };

    dependenciesList.forEach((dep) => {
        let grp = getThematicGroup(dep.root.name);
        thematicDeps[grp].push(dep);
    });

    let thematicKeys = Object.keys(thematicDeps);
    thematicKeys.forEach((groupName) => {
        let depsInGroup = thematicDeps[groupName];
        if (depsInGroup.length === 0) return;

        let safeGroupId = 'dep-theme-' + groupName.replace(/[^a-zA-Z0-9]/g, '-').toLowerCase() + '-' + tabIndex;
        let iconClass = 'fa-link';
        if (groupName === 'Ubicación') iconClass = 'fa-map-marker-alt';
        else if (groupName === 'Personal / Contacto') iconClass = 'fa-users';
        else if (groupName === 'Hardware / Infraestructura') iconClass = 'fa-laptop-house';
        else if (groupName === 'Servicios / Software') iconClass = 'fa-code-branch';

        tabsHtml += `
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#content-${safeGroupId}" role="tab">
                    <i class="fas ${iconClass} mr-1"></i> ${groupName}
                </a>
            </li>`;

        contentHtml += `<div class="tab-pane fade" id="content-${safeGroupId}" role="tabpanel">`;
        contentHtml += `<div class="row pt-3 px-3">`;

        depsInGroup.forEach((dep) => {
            let isRequired = dep.depType === 'required';
            contentHtml += `
                <div class="col-12 mb-2">
                    <h6 class="text-secondary font-weight-bold border-bottom pb-1">
                        <i class="fas fa-sitemap mr-1"></i> ${dep.root.name.replace(/^\d+\s+/, '')}
                    </h6>
                </div>`;

            dep.chain.forEach((levelCat) => {
                let selectId = 'dep_select_' + levelCat.id;
                let reqMark = isRequired ? '<span class="text-danger">*</span>' : '';
                
                contentHtml += `
                    <div class="col-md-4 form-group mb-3">
                        <label class="font-weight-bold ${isRequired ? 'text-primary' : 'text-secondary'}">
                            ${levelCat.name} ${reqMark}
                        </label>
                        <select id="${selectId}" class="form-control dep-ci-select border-primary" data-target-cat="${levelCat.id}" data-type="${dep.depType}" ${isRequired ? 'data-required="true"' : ''} disabled>
                            <option value="">Seleccione el nivel anterior...</option>
                        </select>
                        <small class="form-text text-muted">Dependencia de ${levelCat.name}.</small>
                    </div>
                `;
            });
        });

        contentHtml += `</div></div>`;
        tabIndex++;
    });

    // Determine if category or its lineage requires physical rack placement
    let isRackCategory = lineage.some(cat => {
        let name = (cat.name || '').toLowerCase();
        let catId = parseInt(cat.id);
        return catId === 39 || catId === 41 || catId === 35 || catId === 36 || 
               name.includes('hardware') || name.includes('rack') || name.includes('switch') || 
               name.includes('router') || name.includes('server') || name.includes('servidor') || 
               name.includes('pdu') || name.includes('patch') || name.includes('ups') || name.includes('storage');
    });

    if (isRackCategory) {
        // Racks / Ubicación tab
        let safeGroupId = 'racks-ubicacion-modal';
        tabsHtml += `
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#content-${safeGroupId}" role="tab">
                    <i class="fas fa-server mr-1"></i> Racks / Ubicación
                </a>
            </li>`;

        contentHtml += `<div class="tab-pane fade" id="content-${safeGroupId}" role="tabpanel">`;
        contentHtml += `<div class="row pt-3 px-3">`;
        contentHtml += `
            <div class="col-md-6 form-group">
                <label>Rack / Gabinete</label>
                <select name="rack_id" id="rack_id_select_modal" class="form-control">
                    <option value="">-- No asignado a Rack --</option>
                </select>
            </div>
            <div class="col-md-6 form-group">
                <label>Posición U Inicial (Start U)</label>
                <input type="number" name="rack_start_u" id="rack_start_u_input_modal" class="form-control" min="1" value="">
                <small class="form-text text-muted">Ej: 1 para la base del rack (o depende del orden).</small>
            </div>
            <div class="col-md-6 form-group mt-2">
                <label>Alto en U (U Height)</label>
                <input type="number" name="rack_height_u" id="rack_height_u_input_modal" class="form-control" min="1" value="1">
            </div>
            <div class="col-md-6 form-group mt-2">
                <label>Orientación / Lado</label>
                <select name="rack_orientation" id="rack_orientation_select_modal" class="form-control">
                    <option value="front">Frente (Front)</option>
                    <option value="rear">Atrás (Rear)</option>
                    <option value="both">Ambos Lados (Both)</option>
                </select>
            </div>
            <div class="col-md-6 form-group mt-2">
                <label>Color en el Rack</label>
                <input type="color" name="rack_color" id="rack_color_input_modal" class="form-control" style="height: 38px;" value="#2a2a2a">
            </div>
            <div class="col-md-6 form-group mt-2">
                <label>Profundidad (Depth)</label>
                <select name="rack_depth" id="rack_depth_select_modal" class="form-control">
                    <option value="full">Completa (Full)</option>
                    <option value="half">Media (1/2)</option>
                    <option value="third">Un Tercio (1/3)</option>
                </select>
            </div>
        `;
        contentHtml += `</div></div>`;
    }

    // Relaciones Tab
    let relTabId = 'relations-tab-modal';
    tabsHtml += `
        <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#content-${relTabId}" role="tab">
                <i class="fas fa-project-diagram mr-1"></i> Relaciones
            </a>
        </li>`;

    contentHtml += `
        <div class="tab-pane fade" id="content-${relTabId}" role="tabpanel">
            <div class="row pt-3 px-3">
                <div class="col-12 mt-2 mb-3">
                    <button type="button" class="btn btn-outline-primary btn-sm mb-2" onclick="openRelationModal()"><i class="fas fa-project-diagram mr-1"></i> Añadir Relación</button>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered" id="relations-table">
                            <thead class="bg-light">
                                <tr><th>Relación</th><th>Destino</th><th>Impacto</th><th>Acción</th></tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="4" class="text-center text-muted small">Sin relaciones.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>`;

    tabsHtml += '</ul>';
    contentHtml += '</div>';

    let rightColHtml = `
        <div class="card premium-card mb-4 border shadow-sm">
            <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 font-weight-bold text-dark" style="font-size:1.05rem;"><i class="fas fa-camera mr-2 text-primary"></i> Galería de Fotos</h5>
            </div>
            <div class="card-body p-4 text-center py-5 opacity-75">
                <i class="fas fa-lock fa-3x mb-3 text-muted"></i>
                <h5>Galería Deshabilitada</h5>
                <p class="small text-muted mb-0">Guarde los cambios primero para poder subir o eliminar fotos.</p>
            </div>
        </div>
    `;

    // Build form tag around everything
    let mainHtml = `
        <form id="modal-edit-form" novalidate>
            <input type="hidden" name="action" value="save_instance">
            <input type="hidden" name="id" value="${ci.id}">
            <input type="hidden" name="category_id" value="${ci.category_id}">
            <input type="hidden" name="zabbix_host_id" id="f_zabbix_id" value="${ci.zabbix_host_id || ''}">
            <input type="hidden" name="ci_relations" id="ci_relations_input" value="[]">

            <div class="container-fluid p-4 text-left">
                <div class="row align-items-center mb-4 border-bottom pb-3">
                    <div class="col">
                        <h2 class="h3 mb-0 font-weight-bold text-primary"><i class="fas fa-edit mr-2"></i> Modo Edición: ${ci.hostname}</h2>
                        <p class="text-muted small mb-0">Editando detalles en la categoría <strong>${ci.category_name}</strong></p>
                    </div>
                    <div class="col-auto">
                        <div class="btn-group shadow-sm">
                            <button type="button" class="btn btn-success font-weight-bold px-3" onclick="saveCIChanges()"><i class="fas fa-save mr-1.5"></i> Guardar</button>
                            <button type="button" class="btn btn-secondary font-weight-bold px-3" onclick="toggleCIEditMode(false)"><i class="fas fa-times mr-1.5"></i> Cancelar</button>
                        </div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-12">
                        ${tabsHtml}
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-8 mb-4">
                        <div class="card border shadow-sm h-100" style="border-radius: 12px;">
                            <div class="card-body">
                                ${contentHtml}
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        ${rightColHtml}
                    </div>
                </div>
            </div>
        </form>
    `;

    $('#attrModalBody').html(mainHtml);

    // Load Geo Parent
    checkGeoHierarchyInModal(lineage, ci);

    // Prepopulate Dynamic Data
    let attrs = {};
    try { attrs = JSON.parse(ci.attributes_json); } catch(e) {}
    for (let key in attrs) {
        let el = $(`#modal-edit-form [name="${key}"]`);
        if (el.length) {
            let val = attrs[key];
            if (Array.isArray(val)) val = val[0];
            el.val(val);
            
            if (el.hasClass('image-filepath-input') && val) {
                $(`#img_preview_img_${key}`).attr('src', val);
                $(`#img_preview_container_${key}`).show();
                $(`#img_file_${key}`).hide();
            }
        }
    }

    // Load Racks List
    $.get('api_ci.php?action=get_racks', function(res) {
        if (res.success) {
            let sel = $('#rack_id_select_modal');
            res.data.forEach(r => {
                sel.append(`<option value="${r.id}">${r.name} (${r.room_name || 'Sin sala'})</option>`);
            });
            if (attrs.rack_id) sel.val(attrs.rack_id);
            if (attrs.rack_start_u) $('#rack_start_u_input_modal').val(attrs.rack_start_u);
            if (attrs.rack_height_u) $('#rack_height_u_input_modal').val(attrs.rack_height_u);
            if (attrs.rack_orientation) $('#rack_orientation_select_modal').val(attrs.rack_orientation);
            if (attrs.rack_color) $('#rack_color_input_modal').val(attrs.rack_color);
            if (attrs.rack_depth) $('#rack_depth_select_modal').val(attrs.rack_depth);
        }
    }, 'json');

    // Load cascaded dependencies
    dependenciesList.forEach((dep) => {
        if (dep.chain.length > 0) {
            setupDependencyCascades(dep.chain);
        }
    });

    // Populate relations list
    updateRelationsUI();
}

function refreshCIDetailsAndRender(id) {
    $.get('api_ci.php?action=get_ci_details&id=' + id, function(res) {
        if (res.success) {
            currentCIData = res.data;
            renderCIModal();
        }
    }, 'json');
}

function setupPhotoUploadEvents(ciId) {
    $(document).off('change', '#ci-image-input');
    $(document).on('change', '#ci-image-input', function() {
        if (!this.files.length) return;
        const fd = new FormData();
        fd.append('image', this.files[0]);
        fd.append('table', 'ci_instances');
        fd.append('id', ciId);
        
        Swal.fire({ title: 'Subiendo imagen...', didOpen: () => Swal.showLoading() });
        fetch('api_upload_image.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Éxito', 'Imagen subida correctamente', 'success').then(() => {
                        refreshCIDetailsAndRender(ciId);
                    });
                } else {
                    Swal.fire('Error', data.error || 'Error al subir la imagen', 'error');
                }
            });
    });

    $(document).off('click', '.delete-ci-image-btn');
    $(document).on('click', '.delete-ci-image-btn', function(e) {
        e.stopPropagation();
        const imageId = $(this).data('image-id');
        Swal.fire({
            title: '¿Eliminar fotografía?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Eliminar',
            cancelButtonText: 'Cancelar'
        }).then(res => {
            if (res.isConfirmed) {
                const fd = new FormData();
                fd.append('action', 'delete_image');
                fd.append('id', imageId);
                fetch('api_action.php', { method: 'POST', body: fd }).then(r => r.json()).then(js => {
                    if (js.success) {
                        Swal.fire('Éxito', 'Imagen eliminada', 'success').then(() => {
                            refreshCIDetailsAndRender(ciId);
                        });
                    } else {
                        Swal.fire('Error', js.error || 'No se pudo eliminar la imagen', 'error');
                    }
                });
            }
        });
    });
}

function renderTreeParentCIOptions(selectElId, targetCat, currentCiId, selectedParentId) {
    let container = selectElId === 'f_geo_parent_id' && $('#geo-parent-container-modal').length ? $('#geo-parent-container-modal') : $('#geo-parent-container');
    let labelEl = selectElId === 'f_geo_parent_id' && $('#geo-parent-label-modal').length ? $('#geo-parent-label-modal') : $('#geo-parent-label');
    let selectEl = $('#' + selectElId);
    if (!selectEl.length) return;

    if (container.length) {
        if (targetCat && (!targetCat.parent_id || targetCat.parent_id == 0) && targetCat.requires_parent_instance == 0) {
            container.hide();
        } else {
            container.show();
        }
    }

    let isRequired = targetCat && targetCat.requires_parent_instance == 1;
    selectEl.attr('data-required', isRequired ? 'true' : 'false');

    if (isRequired) {
        if (labelEl.length) labelEl.html(`<i class="fas fa-sitemap text-primary mr-1"></i> CI Padre / Pertenece a <span class="text-danger">* (Obligatorio por categoría)</span>`);
    } else {
        if (labelEl.length) labelEl.html(`<i class="fas fa-sitemap text-primary mr-1"></i> CI Padre / Pertenece a <small class="text-muted">(Opcional - Jerarquía en Árbol)</small>`);
    }

    $.get('api_ci.php?action=get_instances', function(res) {
        if (!res.success || !res.data) return;
        let allCis = res.data;

        let ciByParent = {};
        let ciMap = {};
        allCis.forEach(ci => {
            ciMap[ci.id] = ci;
            let pid = ci.parent_ci_id || 0;
            if (!ciByParent[pid]) ciByParent[pid] = [];
            ciByParent[pid].push(ci);
        });

        function isSelfOrDescendant(candidateId, selfId) {
            if (!selfId) return false;
            if (candidateId == selfId) return true;
            let curr = ciMap[candidateId];
            let visited = new Set();
            while (curr && curr.parent_ci_id) {
                if (curr.parent_ci_id == selfId) return true;
                if (visited.has(curr.parent_ci_id)) break;
                visited.add(curr.parent_ci_id);
                curr = ciMap[curr.parent_ci_id];
            }
            return false;
        }

        function buildTree(parentId, depth, visited) {
            let html = '';
            let children = ciByParent[parentId] || [];
            children.sort((a, b) => (a.hostname || '').localeCompare(b.hostname || ''));

            children.forEach(ci => {
                if (visited.has(ci.id)) return;
                if (currentCiId && isSelfOrDescendant(ci.id, currentCiId)) return;

                visited.add(ci.id);

                let prefix = '';
                if (depth > 0) {
                    prefix = '│  '.repeat(depth - 1) + '└─ ';
                }
                
                let isSel = (selectedParentId && selectedParentId == ci.id) ? 'selected' : '';
                let label = `${prefix}[${ci.category_name}] ${ci.hostname}`;
                if (ci.sigla) label += ` (${ci.sigla})`;
                else if (ci.ip_address) label += ` (${ci.ip_address})`;

                html += `<option value="${ci.id}" ${isSel}>${label}</option>`;

                if (ciByParent[ci.id]) {
                    html += buildTree(ci.id, depth + 1, visited);
                }
            });
            return html;
        }

        let visited = new Set();
        let optionsHtml = '';
        if (!isRequired) {
            optionsHtml += `<option value="">-- Ninguno (CI Raíz / Principal) --</option>`;
        } else {
            optionsHtml += `<option value="">-- Seleccione CI Padre --</option>`;
        }

        optionsHtml += buildTree(0, 0, visited);

        allCis.forEach(ci => {
            if (!visited.has(ci.id)) {
                optionsHtml += buildTree(ci.parent_ci_id || 0, 0, visited);
            }
        });

        selectEl.html(optionsHtml);
    }, 'json');
}

function checkGeoHierarchyInModal(lineage, ci) {
    let targetCat = lineage[lineage.length - 1]; 
    let currentCiId = ci ? ci.id : null;
    let selectedParentId = ci ? ci.parent_ci_id : null;
    renderTreeParentCIOptions('f_geo_parent_id', targetCat, currentCiId, selectedParentId);
}

function getDependencyChain(rootCatId) {
    let rootCat = categories.find(c => c.id == rootCatId);
    if (!rootCat) return [];
    
    let chain = [];
    let children = categories.filter(c => c.parent_id == rootCatId);
    if (children.length === 0) {
        chain.push(rootCat);
    } else {
        function traverse(parentCatId) {
            let subChildren = categories.filter(c => c.parent_id == parentCatId);
            subChildren.forEach(child => {
                chain.push(child);
                traverse(child.id);
            });
        }
        traverse(rootCatId);
    }
    return chain;
}

function setupDependencyCascades(depLevels) {
    if (depLevels.length === 0) return;
    
    let rootLevel = depLevels[0];
    
    loadCIsForLevel(rootLevel.id, null, function() {
        if (currentCIData.ci || (currentCIData.relations && currentCIData.relations.length > 0)) {
            preselectDependencyChain(depLevels, 0);
        }
    });
    
    $(`#dep_select_${rootLevel.id}`).prop('disabled', false);
    
    for (let i = 0; i < depLevels.length - 1; i++) {
        let currentLevel = depLevels[i];
        let nextLevel = depLevels[i+1];
        
        $(`#dep_select_${currentLevel.id}`).on('change', function() {
            let val = $(this).val();
            let nextSelect = $(`#dep_select_${nextLevel.id}`);
            
            for (let j = i + 1; j < depLevels.length; j++) {
                let subSelect = $(`#dep_select_${depLevels[j].id}`);
                subSelect.val('').prop('disabled', true).html('<option value="">Seleccione el nivel anterior...</option>');
            }
            
            if (val) {
                nextSelect.prop('disabled', false).html('<option value="">Cargando...</option>');
                loadCIsForLevel(nextLevel.id, val);
            }
        });
    }
}

function loadCIsForLevel(catId, parentCiId, callback) {
    let selectEl = $(`#dep_select_${catId}`);
    $.get(`api_ci.php?action=get_ci_by_category&category_id=${catId}&parent_ci_id=${parentCiId || ''}`, function(res) {
        if (res.success) {
            let catObj = categories.find(c => c.id == catId);
            selectEl.html(`<option value="">-- Seleccione ${catObj.name} --</option>`);
            res.data.forEach(ci => {
                selectEl.append(`<option value="${ci.id}">${ci.hostname}</option>`);
            });
            if (callback) callback();
        }
    }, 'json');
}

function preselectDependencyChain(depLevels, currentIndex) {
    if (currentIndex >= depLevels.length) return;
    
    let currentLevel = depLevels[currentIndex];
    let selectEl = $(`#dep_select_${currentLevel.id}`);
    
    let matchedOptionId = null;
    let relsData = currentCIData.relations || [];
    if (relsData.length > 0) {
        selectEl.find('option').each(function() {
            let optId = $(this).val();
            if (optId && relsData.find(r => r.target_id == optId)) {
                matchedOptionId = optId;
                return false; // break loop
            }
        });
    }
    
    if (matchedOptionId) {
        selectEl.val(matchedOptionId);
        selectEl.prop('disabled', false);
        
        if (currentIndex + 1 < depLevels.length) {
            let nextLevel = depLevels[currentIndex + 1];
            let nextSelect = $(`#dep_select_${nextLevel.id}`);
            nextSelect.prop('disabled', false).html('<option value="">Cargando...</option>');
            
            loadCIsForLevel(nextLevel.id, matchedOptionId, function() {
                preselectDependencyChain(depLevels, currentIndex + 1);
            });
        }
    }
}

function loadZabbixHGModal() {
    let sel = $('#zabbix_hg_modal');
    sel.html('<option>Cargando Hostgroups...</option>').prop('disabled', true);
    $.get('datacenter/api.php?action=get_zabbix_hostgroups', function(res) {
        if (res.success) {
            sel.html('<option value="">-- Todos los Hostgroups --</option>');
            res.data.forEach(hg => sel.append(`<option value="${hg.groupid}">${hg.name}</option>`));
            sel.prop('disabled', false);
            loadZabbixHostsModal('');
        }
    }, 'json');
}

function loadZabbixHostsModal(gid) {
    let sel = $('#zabbix_h_modal');
    sel.html('<option>Cargando Equipos...</option>').prop('disabled', true);
    let url = (gid !== null && gid !== undefined && gid !== '') ? `datacenter/api.php?action=get_zabbix_hosts&groupid=${gid}` : `datacenter/api.php?action=get_zabbix_hosts`;
    $.get(url, function(res) {
        if (res.success) {
            sel.html('<option value="">-- Seleccione Equipo Zabbix --</option>');
            res.data.forEach(h => {
                let safeName = (h.name || '').replace(/"/g, '&quot;');
                let safeMake = (h.make || '').replace(/"/g, '&quot;');
                let safeModel = (h.model || '').replace(/"/g, '&quot;');
                let safeSerial = (h.serial || '').replace(/"/g, '&quot;');
                let safeAsset = (h.asset_tag || '').replace(/"/g, '&quot;');
                sel.append(`<option value="${h.hostid}" data-name="${safeName}" data-ip="${h.ip || ''}" data-make="${safeMake}" data-model="${safeModel}" data-serial="${safeSerial}" data-asset_tag="${safeAsset}">${h.name} ${h.ip ? '(' + h.ip + ')' : ''}</option>`);
            });
            sel.prop('disabled', false);
        }
    }, 'json');
}

function openRelationModal() {
    $('#rel_target_id').html('<option value="">Seleccione Categoría Primero...</option>').prop('disabled', true);
    
    let sel = $('#rel_cat_1');
    sel.html('<option value="">-- Nivel 1 --</option>');
    let l1 = categories.filter(c => !c.parent_id);
    l1.forEach(c => sel.append(`<option value="${c.id}">${c.name}</option>`));
    
    $('#rel_cat_2').html('<option value="">-- Nivel 2 --</option>').prop('disabled', true);
    $('#rel_cat_3').html('<option value="">-- Nivel 3 --</option>').prop('disabled', true);
    
    $('#relationModal').modal('show');
}

function fetchCIsForCategory(catId) {
    let sel = $('#rel_target_id');
    sel.html('<option>Cargando...</option>').prop('disabled', true);
    $.get(`api_ci.php?action=get_ci_by_category&category_id=${catId}`, function(res) {
        if (res.success) {
            sel.html('<option value="">-- Seleccione CI --</option>');
            res.data.forEach(ci => sel.append(`<option value="${ci.id}">${ci.hostname} (${ci.ip_address || 'Sin IP'})</option>`));
            sel.prop('disabled', false);
        }
    }, 'json');
}

function addRelation() {
    let targetId = $('#rel_target_id').val();
    let targetName = $('#rel_target_id option:selected').text();
    let type = $('#rel_type').val();
    let impact = $('#rel_impact').val();
    
    if (!targetId) {
        Swal.fire('Atención', 'Debe seleccionar un CI destino', 'warning');
        return;
    }
    
    if (pendingRelations.find(r => r.target_id == targetId && r.type == type)) {
        Swal.fire('Atención', 'Ya existe esta relación', 'warning');
        return;
    }
    
    pendingRelations.push({
        target_id: targetId,
        target_name: targetName,
        type: type,
        impact: impact
    });
    
    updateRelationsUI();
    $('#relationModal').modal('hide');
}

function removeRelation(index) {
    pendingRelations.splice(index, 1);
    updateRelationsUI();
}

function updateRelationsUI() {
    $('#ci_relations_input').val(JSON.stringify(pendingRelations));
    let tbody = $('#relations-table tbody');
    tbody.empty();
    
    if (pendingRelations.length === 0) {
        tbody.append('<tr><td colspan="4" class="text-center text-muted small">Sin relaciones.</td></tr>');
        return;
    }
    
    pendingRelations.forEach((rel, idx) => {
        let impBadge = rel.impact == 'Sí' ? 'danger' : (rel.impact == 'Parcial' ? 'warning' : 'info');
        tbody.append(`
            <tr>
                <td class="font-weight-bold text-primary">${rel.type}</td>
                <td><i class="fas fa-server text-muted mr-1"></i> ${rel.target_name}</td>
                <td><span class="badge badge-${impBadge}">${rel.impact}</span></td>
                <td><button type="button" class="btn btn-xs btn-danger" onclick="removeRelation(${idx})"><i class="fas fa-times"></i></button></td>
            </tr>
        `);
    });
}

function saveCIChanges() {
    let form = $('#modal-edit-form');
    
    // Validate required fields
    let firstInvalid = null;
    form.find('[data-required="true"]').each(function() {
        if (!$(this).val() || $(this).val().toString().trim() === '') {
            firstInvalid = this;
            return false;
        }
    });
    
    if (firstInvalid) {
        let label = $(firstInvalid).closest('.form-group').find('label').text().replace(/\*|\(Opcional\)/g, '').trim();
        let tabPane = $(firstInvalid).closest('.tab-pane');
        if (tabPane.length && !tabPane.hasClass('active')) {
            let tabId = tabPane.attr('id');
            $(`a[href="#${tabId}"]`).tab('show');
        }
        setTimeout(() => {
            $(firstInvalid).focus();
        }, 100);
        Swal.fire('Atención', `El campo "${label}" es obligatorio.`, 'warning');
        return;
    }
    
    let finalRelations = [...pendingRelations];
    
    // Strict parent geo-dependency
    if ($('#geo-parent-container-modal').is(':visible')) {
        let parentId = $('#f_geo_parent_id').val();
        let parentName = $('#f_geo_parent_id option:selected').text();
        let isRequired = $('#f_geo_parent_id').attr('data-required') === 'true';
        
        form.find('input[name="parent_ci_id"]').remove();
        
        if (isRequired && !parentId) {
            Swal.fire('Atención', 'Debe seleccionar la Dependencia Padre obligatoria', 'warning');
            return;
        }
        
        if (parentId) {
            $('<input>').attr({type: 'hidden', name: 'parent_ci_id', value: parentId}).appendTo(form);
            finalRelations.push({
                target_id: parentId,
                target_name: parentName,
                type: 'Contains',
                impact: 'Sí'
            });
        } else {
            $('<input>').attr({type: 'hidden', name: 'parent_ci_id', value: ''}).appendTo(form);
        }
    }
    
    // Dependency cascades
    let depValidationPassed = true;
    form.find('.dep-ci-select').each(function() {
        let val = $(this).val();
        let labelText = $(this).closest('.form-group').find('label').text().replace(/\*|\(Opcional\)/g, '').trim();
        let isRequired = $(this).attr('data-required') === 'true';
        
        if (isRequired && !val) {
            Swal.fire('Atención', 'Debe seleccionar ' + labelText, 'warning');
            depValidationPassed = false;
            return false;
        }
        
        if (val) {
            if (!finalRelations.find(r => r.target_id == val)) {
                finalRelations.push({
                    target_id: val,
                    target_name: $(this).find('option:selected').text(),
                    type: 'Depends on',
                    impact: $(this).data('type') === 'required' ? 'Sí' : 'No'
                });
            }
        }
    });
    
    if (!depValidationPassed) return;
    
    $('#ci_relations_input').val(JSON.stringify(finalRelations));
    
    Swal.fire({ title: 'Guardando...', didOpen: () => Swal.showLoading() });
    
    $.post('api_ci.php', form.serialize(), function(res) {
        if (res.success) {
            Swal.fire('Éxito', res.message, 'success').then(() => {
                location.reload();
            });
        } else {
            Swal.fire('Error', res.message, 'error');
        }
    }, 'json').fail(function(xhr) {
        console.error(xhr.responseText);
        Swal.fire('Error del Servidor', 'Hubo un error fatal al intentar guardar en la base de datos.', 'error');
    });
}

function updateCIGoogleMapsView(link) {
    const sec = document.getElementById('ci-map-section');
    const cnt = document.getElementById('ci-map-container');
    if (!sec || !cnt) return;
    if (!link || link.trim() === "") { sec.style.display = 'none'; return; }
    sec.style.display = 'block';
    if (link.includes('<iframe')) {
        cnt.innerHTML = link.replace(/width="\d+"/, 'width="100%"').replace(/height="\d+"/, 'height="350"');
    } else if (link.includes('maps.app.goo.gl')) {
        cnt.innerHTML = `<div class="p-4 text-center"><a href="${link}" target="_blank" class="btn btn-primary btn-sm"><i class="fas fa-external-link-alt mr-1"></i>Abrir Mapa</a></div>`;
    } else {
        cnt.innerHTML = `<iframe width="100%" height="350" frameborder="0" src="${link}" allowfullscreen></iframe>`;
    }
}

function deleteCI(id) {
    Swal.fire({
        title: '¿Eliminar CI?',
        text: 'Esta acción no se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('api_ci.php', {action: 'delete_instance', id: id}, function(res) {
                if (res.success) {
                    Swal.fire('Eliminado', res.message, 'success').then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }, 'json');
        }
    });
}



    // Manejador delegado para origen de datos (Manual vs Zabbix)
    $(document).on('change', 'input[name="source"]', function() {
        let val = $(this).val();
        if (val === 'zabbix') {
            $('#zabbix-area-modal').removeClass('d-none');
            loadZabbixHGModal();
        } else {
            $('#zabbix-area-modal').addClass('d-none');
        }
    });

    $(document).on('change', '#zabbix_hg_modal', function() {
        let gid = $(this).val();
        loadZabbixHostsModal(gid);
    });

    $(document).on('change', '#zabbix_h_modal', function() {
        let val = $(this).val();
        if (val) {
            $('#btn-fetch-zabbix-modal').prop('disabled', false);
        } else {
            $('#btn-fetch-zabbix-modal').prop('disabled', true);
        }
    });

    $(document).on('click', '#btn-fetch-zabbix-modal', function() {
        let opt = $('#zabbix_h_modal option:selected');
        if (!opt.val()) return;

        let hostId = opt.val();
        let hostName = opt.attr('data-name') || '';
        let hostIp = opt.attr('data-ip') || '';
        let make = opt.attr('data-make') || '';
        let model = opt.attr('data-model') || '';
        let serial = opt.attr('data-serial') || '';
        let assetTag = opt.attr('data-asset_tag') || '';

        if (hostId) $('#f_zabbix_id').val(hostId);
        if (hostName) $('#f_hostname').val(hostName);
        if (hostIp) $('#f_ip').val(hostIp);
        if (make && $('input[name="marca"]').length) $('input[name="marca"]').val(make);
        if (model && $('input[name="modelo"]').length) $('input[name="modelo"]').val(model);
        if (serial && $('input[name="serial_number"]').length) $('input[name="serial_number"]').val(serial);
        if (assetTag && $('input[name="asset_tag"]').length) $('input[name="asset_tag"]').val(assetTag);

        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Datos cargados desde Zabbix',
            showConfirmButton: false,
            timer: 2000
        });
    });

    // Manejador delegado para maximizar/restaurar modal
    $(document).on('click', '#btn-maximize-modal', function() {
        $('#attrModal').toggleClass('modal-fullscreen');
        let icon = $(this).find('i');
        if ($('#attrModal').hasClass('modal-fullscreen')) {
            icon.removeClass('fa-expand').addClass('fa-compress');
        } else {
            icon.removeClass('fa-compress').addClass('fa-expand');
        }
    });

    $('#attrModal').on('hidden.bs.modal', function () {
        $(this).addClass('modal-fullscreen');
    });


    // Category selection handler inside creation modal
    $(document).on('change', '#modal_select_category_id', function() {
        let newCatId = parseInt($(this).val());
        if (newCatId && currentCIData && currentCIData.ci) {
            currentCIData.ci.category_id = newCatId;
            currentCIData.lineage = getCategoryLineage(newCatId);
            let targetCat = currentCIData.lineage[currentCIData.lineage.length - 1];
            let catName = targetCat ? targetCat.name : 'General';
            $('#attrModalTitle').html('<i class="fas fa-plus-circle text-success mr-2"></i> Crear Nuevo CI: <span class="badge badge-success font-weight-normal text-white ml-2">' + catName + '</span>');
            renderEditView(currentCIData.ci, currentCIData.lineage, [], [], []);
        }
    });

    // Auto-open CI details / edit / create modal if URL params provided
    const urlParams = new URLSearchParams(window.location.search);
    const showCiId = urlParams.get('show_ci_id');
    const actionParam = urlParams.get('action');
    const catParam = urlParams.get('category_id');

    if (actionParam === 'create') {
        openCreateCIModal(catParam);
    } else if (showCiId) {
        if (actionParam === 'edit') {
            editCIDetailsDirectly(showCiId);
        } else {
            viewCIDetails(showCiId);
        }
    }



    // Delegated click for edit button in list
    $(document).on('click', '.btn-edit-ci', function(e) {
        e.preventDefault();
        let id = $(this).data('id');
        editCIDetailsDirectly(id);
    });

    // Relation category cascading select change handlers
    $(document).on('change', '#rel_cat_1', function() {
        let val = $(this).val();
        $('#rel_cat_2').html('<option value="">-- Nivel 2 --</option>').prop('disabled', true);
        $('#rel_cat_3').html('<option value="">-- Nivel 3 --</option>').prop('disabled', true);
        if (val) {
            let children = categories.filter(c => c.parent_id == val);
            if (children.length > 0) {
                let sel = $('#rel_cat_2');
                children.forEach(c => sel.append(`<option value="${c.id}">${c.name}</option>`));
                sel.prop('disabled', false);
            }
            fetchCIsForCategory(val);
        }
    });

    $(document).on('change', '#rel_cat_2', function() {
        let val = $(this).val();
        $('#rel_cat_3').html('<option value="">-- Nivel 3 --</option>').prop('disabled', true);
        if (val) {
            let children = categories.filter(c => c.parent_id == val);
            if (children.length > 0) {
                let sel = $('#rel_cat_3');
                children.forEach(c => sel.append(`<option value="${c.id}">${c.name}</option>`));
                sel.prop('disabled', false);
            }
            fetchCIsForCategory(val);
        } else {
            fetchCIsForCategory($('#rel_cat_1').val());
        }
    });

    $(document).on('change', '#rel_cat_3', function() {
        let val = $(this).val();
        if (val) {
            fetchCIsForCategory(val);
        } else {
            fetchCIsForCategory($('#rel_cat_2').val());
        }
    });

    // Evento para subir imagen de atributo dinámico
    $(document).on('change', '.attr-image-file-input', function() {
        let file = this.files[0];
        if (!file) return;

        let key = $(this).data('key');
        let formData = new FormData();
        formData.append('image', file);
        formData.append('table', 'ci_attributes');
        formData.append('id', '0');

        $(`#img_preview_container_${key}`).hide();
        $(this).hide();

        let spinnerId = `img_spinner_${key}`;
        if ($(`#${spinnerId}`).length === 0) {
            $(this).after(`<div id="${spinnerId}" class="spinner-border text-primary spinner-border-sm mt-1" role="status"><span class="sr-only">Cargando...</span></div>`);
        } else {
            $(`#${spinnerId}`).show();
        }

        $.ajax({
            url: 'upload_image.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                $(`#${spinnerId}`).hide();
                if (res.success) {
                    $(`#img_val_${key}`).val(res.filepath);
                    $(`#img_preview_img_${key}`).attr('src', res.filepath);
                    $(`#img_preview_container_${key}`).show();
                    
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Imagen subida correctamente',
                        showConfirmButton: false,
                        timer: 1500
                    });
                } else {
                    $(`#img_file_${key}`).show();
                    Swal.fire('Error', res.error || 'No se pudo subir la imagen', 'error');
                }
            },
            error: function() {
                $(`#${spinnerId}`).hide();
                $(`#img_file_${key}`).show();
                Swal.fire('Error', 'Error técnico al intentar subir la imagen.', 'error');
            }
        });
    });

    // Evento para quitar imagen de atributo dinámico
    $(document).on('click', '.btn-remove-attr-image', function() {
        let key = $(this).data('key');
        $(`#img_val_${key}`).val('');
        $(`#img_preview_img_${key}`).attr('src', '');
        $(`#img_preview_container_${key}`).hide();
        $(`#img_file_${key}`).val('').show();
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'info',
            title: 'Imagen removida',
            showConfirmButton: false,
            timer: 1500
        });
    });


// --- PORTMAPPING INTEGRATION JS ---
function loadManualSurveyDetail(surveyId) {
    $.get('api_portmapping.php', {
        action: 'get_manual_survey',
        id: surveyId
    }, function(resp) {
        if (!resp.success || !resp.data) {
            $('#manual-survey-modal-loading').html(`
                <div class="text-danger py-4 text-center">
                    <i class="fas fa-exclamation-circle fa-2x mb-2"></i>
                    <p>Error al cargar el levantamiento físico: ${resp.error || 'No encontrado'}</p>
                </div>
            `);
            return;
        }
        
        const s = resp.data;
        $('#ms-obs-detail').text(s.description || 'Sin observaciones registradas.');
        
        let imgs = [];
        try {
            imgs = JSON.parse(s.images_json) || [];
        } catch(e) {
            imgs = [];
        }
        
        let galleryHtml = '';
        if (imgs.length === 0) {
            galleryHtml = `
                <div class="col-12 text-center py-4 text-muted w-100">
                    <i class="far fa-images fa-2x mb-2"></i>
                    <p class="mb-0">No se adjuntaron imágenes en este levantamiento.</p>
                </div>
            `;
        } else {
            imgs.forEach(img => {
                const path = typeof img === 'string' ? img : (img.path || '');
                const title = typeof img === 'string' ? '' : (img.title || '');
                const tagsList = typeof img === 'object' && Array.isArray(img.tags) && img.tags.length > 0 ? img.tags : (title ? title.split(',').map(t => t.trim()) : []);
                
                let badgesHtml = '';
                tagsList.forEach((t, i) => {
                    if (i === 0 && /\d{4}-\d{2}-\d{2}/.test(t)) {
                        badgesHtml += `<span class="badge badge-success px-2 py-1 mr-1 mb-1" style="font-size: 10px;"><i class="far fa-calendar-alt mr-1"></i> ${t}</span>`;
                    } else {
                        badgesHtml += `<span class="badge badge-info px-2 py-1 mr-1 mb-1" style="font-size: 10px;"><i class="fas fa-tag mr-1"></i> ${t}</span>`;
                    }
                });

                galleryHtml += `
                    <div class="col-md-6 mb-3">
                        <div class="card h-100 shadow-sm border" style="border-radius: 6px; overflow: hidden;">
                            <a href="${path}" target="_blank">
                                <img src="${path}" class="card-img-top" style="height: 180px; object-fit: contain; background: #f8f9fa;">
                            </a>
                            <div class="card-body p-2 bg-white text-center border-top">
                                ${badgesHtml || (title ? `<span class="font-weight-bold small text-dark">${title}</span>` : '')}
                            </div>
                        </div>
                    </div>
                `;
            });
        }
        
        $('#ms-images-gallery-detail').html(galleryHtml);
        $('#manual-survey-modal-loading').hide();
        $('#manual-survey-modal-content').fadeIn();
    });
}

function formatBits(bits) {
    if (!bits || bits == 0) return '0 bps';
    const k = 1000;
    const sizes = ['bps', 'Kbps', 'Mbps', 'Gbps', 'Tbps'];
    const i = Math.floor(Math.log(bits) / Math.log(k));
    return parseFloat((bits / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function loadPortmappingDetail(ciId, hostname) {
    $.get('api_portmapping.php', {
        action: 'get_device_ports_and_connections',
        device_id: ciId
    }, function(resp) {
        if (!resp.success) {
            $('#portmapping-modal-loading').html(`
                <div class="text-danger py-4">
                    <i class="fas fa-exclamation-circle fa-2x mb-2"></i>
                    <p>Error al cargar el portmapping: ${resp.error || 'Error desconocido'}</p>
                </div>
            `);
            return;
        }

        const networkPorts = resp.network_ports || [];
        const powerPorts = resp.power_ports || [];
        const allPorts = [...networkPorts, ...powerPorts];

        if (allPorts.length === 0) {
            $('#portmapping-modal-loading').html(`
                <div class="text-muted py-5 text-center">
                    <i class="fas fa-info-circle fa-2x mb-2 text-warning"></i>
                    <p>No se encontraron puertos registrados ni monitoreados para este CI.</p>
                </div>
            `);
            return;
        }

        // Group ports
        const groups = {
            'Gi': [],
            'Fa': [],
            'Te': [],
            'Vlan': [],
            'Power': [],
            'Otros': []
        };

        allPorts.forEach(p => {
            const name = p.port_name || '';
            const lower = name.toLowerCase();
            if (p.connection_type === 'power' || lower.startsWith('pwr') || lower.startsWith('power')) {
                groups['Power'].push(p);
            } else if (lower.startsWith('gi') || lower.includes('gigabit')) {
                groups['Gi'].push(p);
            } else if (lower.startsWith('fa') || lower.includes('fast')) {
                groups['Fa'].push(p);
            } else if (lower.startsWith('te') || lower.includes('tengigabit') || lower.startsWith('tw')) {
                groups['Te'].push(p);
            } else if (lower.startsWith('vl') || lower.includes('vlan')) {
                groups['Vlan'].push(p);
            } else {
                groups['Otros'].push(p);
            }
        });

        // Build HTML
        let accordionHtml = '';
        let hasData = false;

        const groupTitles = {
            'Gi': 'GigabitEthernet (Gi)',
            'Fa': 'FastEthernet (Fa)',
            'Te': 'TenGigabitEthernet (Te)',
            'Vlan': 'Vlan',
            'Power': 'Power / Alimentación',
            'Otros': 'Otros Puertos'
        };

        window.currentPortmappingData = {
            hostname: hostname,
            ports: allPorts,
            groups: groups
        };

        Object.keys(groups).forEach((key, index) => {
            const portsInGroup = groups[key];
            if (portsInGroup.length === 0) return;
            hasData = true;

            const title = groupTitles[key];
            const collapseId = `collapse-pm-group-${key}`;
            const headerId = `heading-pm-group-${key}`;

            let tableRows = '';
            portsInGroup.forEach((p, idx) => {
                const destDevice = p.dest_device_name ? 
                    `<span class="font-weight-bold text-dark"><i class="fas fa-server mr-1 text-muted"></i> ${p.dest_device_name}</span>` : 
                    '<span class="text-muted small">Sin conexión</span>';
                const destPort = p.dest_port_name ? 
                    `<span class="badge badge-light border"><i class="fas fa-ethernet text-muted mr-1"></i> ${p.dest_port_name}</span>` : 
                    '-';
                
                let statusBadge = '<span class="badge badge-secondary">Manual</span>';
                let trafficText = '-';
                
                if (p.is_zabbix) {
                    const dotClass = p.status === 'Up' ? 'status-dot-up' : 'status-dot-down';
                    statusBadge = `<div><span class="status-dot ${dotClass}"></span> <span class="font-weight-bold">${p.status}</span></div>`;
                    trafficText = `<div class="small text-muted"><i class="fas fa-arrow-down text-success mr-1"></i>${formatBits(p.bits_received)}</div>
                                   <div class="small text-muted"><i class="fas fa-arrow-up text-primary mr-1"></i>${formatBits(p.bits_sent)}</div>`;
                }

                const colorPill = p.mapping_id ? `<span class="color-pill" style="background-color: ${p.color_code};" title="${p.color_code}"></span>` : '-';
                
                tableRows += `
                    <tr>
                        <td>${idx + 1}</td>
                        <td><span class="font-weight-bold text-primary">${p.port_name}</span></td>
                        <td><small class="text-muted">${p.alias || '-'}</small></td>
                        <td>${statusBadge}</td>
                        <td class="text-monospace">${trafficText}</td>
                        <td><small>${p.cable_type || '-'}</small></td>
                        <td style="border-right: 3px double #94a3b8;">${colorPill}</td>
                        <td>${destDevice}</td>
                        <td>${destPort}</td>
                        <td><small class="text-muted">${p.notes || '-'}</small></td>
                    </tr>
                `;
            });

            accordionHtml += `
                <div class="card mb-2 border shadow-xs" style="border-radius: 8px; overflow: hidden;">
                    <div class="card-header bg-light py-2 px-3" id="${headerId}" style="cursor: pointer;" data-toggle="collapse" data-target="#${collapseId}">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="font-weight-bold text-dark" style="font-size: 0.85rem;">
                                <i class="fas fa-chevron-right mr-2 text-muted" style="transition: transform 0.2s;"></i>
                                ${title}
                            </span>
                            <span class="badge badge-primary px-2 py-1" style="font-size: 0.75rem;">${portsInGroup.length}</span>
                        </div>
                    </div>
                    <div id="${collapseId}" class="collapse ${index === 0 ? 'show' : ''}" data-parent="#portmapping-groups-accordion">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover table-sm table-premium mb-0 border-0" style="font-size: 0.8rem;">
                                    <thead>
                                        <tr class="text-center bg-light font-weight-bold" style="font-size: 0.72rem; border-bottom: 1px solid #dee2e6;">
                                            <th colspan="7" class="text-center py-1 font-weight-bold text-uppercase" style="background-color: #f1f5f9; border-right: 3px double #94a3b8; color: #475569; letter-spacing: 0.8px;">Equipo Origen</th>
                                            <th colspan="3" class="text-center py-1 font-weight-bold text-uppercase" style="background-color: #fef9c3; color: #854d0e; letter-spacing: 0.8px;">Equipo Destino</th>
                                        </tr>
                                        <tr>
                                            <th style="width: 5%">#</th>
                                            <th style="width: 15%">Puerto Origen</th>
                                            <th style="width: 15%">Alias / Descripción</th>
                                            <th style="width: 10%">Estado</th>
                                            <th style="width: 12%">Tráfico</th>
                                            <th style="width: 12%">Cable</th>
                                            <th style="width: 6%; border-right: 3px double #94a3b8;">Color</th>
                                            <th style="width: 15%">Equipo Destino</th>
                                            <th style="width: 10%">Puerto Destino</th>
                                            <th style="width: 10%">Notas</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${tableRows}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        if (!hasData) {
            $('#portmapping-modal-loading').html(`
                <div class="text-muted py-5 text-center">
                    <i class="fas fa-info-circle fa-2x mb-2 text-warning"></i>
                    <p>No se encontraron puertos con datos en este equipo.</p>
                </div>
            `);
            return;
        }

        $('#portmapping-groups-accordion').html(accordionHtml);
        $('#portmapping-modal-loading').hide();
        $('#portmapping-modal-content').show();

        // Rotate arrow on collapse show/hide
        $('#portmapping-groups-accordion').on('show.bs.collapse', function(e) {
            $(e.target).prev('.card-header').find('.fa-chevron-right').css('transform', 'rotate(90deg)');
        }).on('hide.bs.collapse', function(e) {
            $(e.target).prev('.card-header').find('.fa-chevron-right').css('transform', 'rotate(0deg)');
        });
        
        // Ensure default rotation is set for the shown collapse
        $('#portmapping-groups-accordion .collapse.show').prev('.card-header').find('.fa-chevron-right').css('transform', 'rotate(90deg)');

        // Bind exports
        $('#btn-pm-export-excel').off('click').on('click', exportPortmappingExcel);
        $('#btn-pm-export-pdf').off('click').on('click', exportPortmappingPDF);
    });
}

function exportPortmappingExcel() {
    const data = window.currentPortmappingData;
    if (!data) return;

    let html = `
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 11px; }
                th { background-color: #1e3c72; color: white; font-weight: bold; border: 1px solid #dddddd; padding: 8px; text-align: left; }
                td { border: 1px solid #dddddd; padding: 6px; text-align: left; }
                .title-row { font-size: 16px; font-weight: bold; color: #1e3c72; }
                .group-row { background-color: #f2f2f2; font-weight: bold; font-size: 12px; }
            </style>
        </head>
        <body>
            <table>
                <tr>
                    <td colspan="9" class="title-row">REPORTE DE PORT MAPPING - ${data.hostname}</td>
                </tr>
                <tr>
                    <td colspan="9">Fecha de Generación: ${new Date().toLocaleString()}</td>
                </tr>
                <tr><td colspan="9"></td></tr>
    `;

    Object.keys(data.groups).forEach(key => {
        const ports = data.groups[key];
        if (ports.length === 0) return;

        html += `
            <tr class="group-row">
                <td colspan="9">${key === 'Gi' ? 'GigabitEthernet (Gi)' : key === 'Fa' ? 'FastEthernet (Fa)' : key === 'Te' ? 'TenGigabitEthernet (Te)' : key === 'Vlan' ? 'Vlan' : key === 'Power' ? 'Power / Alimentación' : 'Otros Puertos'} (${ports.length})</td>
            </tr>
            <tr>
                <th>#</th>
                <th>Puerto Origen</th>
                <th>Alias / Descripción</th>
                <th>Estado</th>
                <th>Tráfico Recibido (Rx)</th>
                <th>Tráfico Enviado (Tx)</th>
                <th>Cable</th>
                <th>Dispositivo Destino</th>
                <th>Puerto Destino</th>
            </tr>
        `;

        ports.forEach((p, idx) => {
            let trafficRx = '-';
            let trafficTx = '-';
            let status = p.is_zabbix ? p.status : 'Manual';
            if (p.is_zabbix) {
                trafficRx = formatBits(p.bits_received);
                trafficTx = formatBits(p.bits_sent);
            }

            html += `
                <tr>
                    <td>${idx + 1}</td>
                    <td><b>${p.port_name}</b></td>
                    <td>${p.alias || ''}</td>
                    <td>${status}</td>
                    <td>${trafficRx}</td>
                    <td>${trafficTx}</td>
                    <td>${p.cable_type || ''} (${p.color_code || ''})</td>
                    <td>${p.dest_device_name || ''}</td>
                    <td>${p.dest_port_name || ''}</td>
                </tr>
            `;
        });
        
        html += `<tr><td colspan="9"></td></tr>`;
    });

    html += `
            </table>
        </body>
        </html>
    `;

    const blob = new Blob(['\\ufeff' + html], { type: 'application/vnd.ms-excel' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `portmapping_${data.hostname.replace(/[^a-zA-Z0-9]/g, '_')}_${new Date().toISOString().slice(0, 10)}.xls`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

function exportPortmappingPDF() {
    const data = window.currentPortmappingData;
    if (!data) return;

    const printWin = window.open('', '_blank');
    if (!printWin) {
        alert('Por favor habilite las ventanas emergentes (pop-ups) para generar el PDF.');
        return;
    }

    let groupsHtml = '';
    Object.keys(data.groups).forEach(key => {
        const ports = data.groups[key];
        if (ports.length === 0) return;

        let rows = '';
        ports.forEach((p, idx) => {
            let statusBadge = p.is_zabbix ? 
                `<span class="status-dot ${p.status === 'Up' ? 'status-dot-up' : 'status-dot-down'}"></span> ${p.status}` : 
                '<span class="badge badge-secondary">Manual</span>';
            let traffic = '-';
            if (p.is_zabbix) {
                traffic = `Rx: ${formatBits(p.bits_received)}<br>Tx: ${formatBits(p.bits_sent)}`;
            }
            const colorPill = p.mapping_id ? `<span class="color-pill" style="background-color: ${p.color_code}; border: 1px solid #ccc;"></span> ${p.color_code}` : '-';

            rows += `
                <tr>
                    <td>${idx + 1}</td>
                    <td><strong>${p.port_name}</strong></td>
                    <td>${p.alias || '-'}</td>
                    <td>${statusBadge}</td>
                    <td>${traffic}</td>
                    <td style="border-right: 3px double #495057;">${p.cable_type || '-'} ${colorPill !== '-' ? '<br>' + colorPill : ''}</td>
                    <td>${p.dest_device_name ? '<strong>' + p.dest_device_name + '</strong>' : '<span class="text-muted">Sin conexión</span>'}</td>
                    <td>${p.dest_port_name ? '<span class="badge badge-light border">' + p.dest_port_name + '</span>' : '-'}</td>
                    <td>${p.notes || '-'}</td>
                </tr>
            `;
        });

        groupsHtml += `
            <div class="group-section">
                <h3>${key === 'Gi' ? 'GigabitEthernet (Gi)' : key === 'Fa' ? 'FastEthernet (Fa)' : key === 'Te' ? 'TenGigabitEthernet (Te)' : key === 'Vlan' ? 'Vlan' : key === 'Power' ? 'Power / Alimentación' : 'Otros Puertos'} (${ports.length})</h3>
                <table>
                    <thead>
                        <tr style="font-size: 8px; text-transform: uppercase;">
                            <th colspan="6" style="text-align: center; background: #e9ecef; border-right: 3px double #495057; color: #495057; font-weight: bold;">EQUIPO ORIGEN</th>
                            <th colspan="3" style="text-align: center; background: #fff3cd; color: #854d0e; font-weight: bold;">EQUIPO DESTINO</th>
                        </tr>
                        <tr>
                            <th style="width: 5%">#</th>
                            <th style="width: 15%">Puerto Origen</th>
                            <th style="width: 15%">Alias / Descripción</th>
                            <th style="width: 10%">Estado</th>
                            <th style="width: 12%">Tráfico</th>
                            <th style="width: 13%; border-right: 3px double #495057;">Cable / Color</th>
                            <th style="width: 15%">Equipo Destino</th>
                            <th style="width: 10%">Puerto Destino</th>
                            <th style="width: 10%">Notas</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rows}
                    </tbody>
                </table>
            </div>
        `;
    });

    const docHtml = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Reporte Port Mapping - ${data.hostname}</title>
            <style>
                body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #333; margin: 30px; font-size: 11px; line-height: 1.4; }
                .header-container { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #1e3c72; padding-bottom: 15px; margin-bottom: 20px; }
                .logo-text { font-size: 24px; font-weight: bold; color: #1e3c72; letter-spacing: 1px; }
                .logo-sub { font-size: 10px; color: #777; text-transform: uppercase; margin-top: 2px; }
                .report-title { text-align: right; }
                .report-title h1 { margin: 0; font-size: 18px; color: #222; text-transform: uppercase; }
                .report-title p { margin: 5px 0 0 0; color: #666; font-size: 10px; }
                
                .meta-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; background: #f8f9fa; border: 1px solid #e9ecef; padding: 12px; border-radius: 6px; margin-bottom: 25px; }
                .meta-item { display: flex; flex-direction: column; }
                .meta-label { font-size: 9px; text-transform: uppercase; color: #777; font-weight: bold; margin-bottom: 3px; }
                .meta-value { font-size: 11px; font-weight: bold; color: #111; }
                
                .group-section { margin-bottom: 30px; page-break-inside: avoid; }
                .group-section h3 { background: #1e3c72; color: white; padding: 6px 12px; margin: 0 0 8px 0; font-size: 12px; border-radius: 4px; display: inline-block; }
                
                table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
                th { background-color: #f1f3f5; color: #495057; font-weight: bold; border: 1px solid #dee2e6; padding: 8px 10px; text-align: left; font-size: 9px; text-transform: uppercase; }
                td { border: 1px solid #dee2e6; padding: 8px 10px; text-align: left; vertical-align: middle; }
                tr:nth-child(even) { background-color: #fafafa; }
                
                .badge { display: inline-block; padding: 2px 5px; font-size: 9px; font-weight: bold; border-radius: 3px; background: #e9ecef; border: 1px solid #ced4da; }
                .badge-secondary { background: #6c757d; color: white; border-color: #6c757d; }
                
                .status-dot { height: 7px; width: 7px; border-radius: 50%; display: inline-block; margin-right: 4px; }
                .status-dot-up { background-color: #28a745; }
                .status-dot-down { background-color: #dc3545; }
                
                .color-pill { display: inline-block; width: 15px; height: 7px; border-radius: 4px; vertical-align: middle; margin-right: 3px; }
                
                @media print {
                    body { margin: 20px; }
                    .no-print { display: none; }
                    .group-section { page-break-inside: avoid; }
                }
            </style>
        </head>
        <body>
            <div class="no-print" style="margin-bottom: 20px; text-align: right;">
                <button onclick="window.print();" style="padding: 8px 16px; background-color: #1e3c72; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 11px;">
                    Imprimir / Guardar PDF
                </button>
                <button onclick="window.close();" style="padding: 8px 16px; background-color: #6c757d; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 11px; margin-left: 10px;">
                    Cerrar
                </button>
            </div>
            
            <div class="header-container">
                <div>
                    <div class="logo-text">VILASECA</div>
                    <div class="logo-sub">CMDB Port Mapping System</div>
                </div>
                <div class="report-title">
                    <h1>Mapeo de Interfaces</h1>
                    <p>Documento de Conectividad Oficial</p>
                </div>
            </div>
            
            <div class="meta-grid">
                <div class="meta-item">
                    <span class="meta-label">Dispositivo CI</span>
                    <span class="meta-value">${data.hostname}</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Fecha de Emisión</span>
                    <span class="meta-value">${new Date().toLocaleString('es-ES')}</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Total Interfaces</span>
                    <span class="meta-value">${data.ports.length}</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Estado de Sistema</span>
                    <span class="meta-value">OPERATIVO</span>
                </div>
            </div>
            
            ${groupsHtml}
            
            \x3cscript\x3e
                window.onload = function() {
                    setTimeout(function() {
                        window.print();
                    }, 500);
                }
            \x3c/script\x3e
        </body>
        </html>
    `;

    printWin.document.write(docHtml);
    printWin.document.close();
}

function loadCategories(callback) {
    if (window.preloadedCategories && Array.isArray(window.preloadedCategories) && window.preloadedCategories.length > 0) {
        categories = window.preloadedCategories;
        try {
            renderConfigItemCategoryTree();
        } catch(e) {
            console.error('Error rendering category tree:', e);
        }
        if (typeof callback === 'function') {
            callback();
            callback = null;
        }
    }
    $.get('api_ci.php?action=get_categories', function(res) {
        if (typeof res === 'string') {
            try { res = JSON.parse(res); } catch(e) {}
        }
        if (res && res.success && Array.isArray(res.data)) {
            categories = res.data;
            window.preloadedCategories = res.data;
            try {
                renderConfigItemCategoryTree();
            } catch(e) {
                console.error('Error rendering category tree:', e);
            }
        } else if (!window.preloadedCategories || !window.preloadedCategories.length) {
            console.error('Failed to load categories:', res);
            $('#category-relations-tree').html('<div class="alert alert-warning m-2 p-2 small"><i class="fas fa-exclamation-circle mr-1"></i> No se pudieron cargar las categorías.</div>');
        }
        if (typeof callback === 'function') callback();
    }, 'json').fail(function(xhr, status, err) {
        if (!window.preloadedCategories || !window.preloadedCategories.length) {
            console.error('AJAX error loading categories:', status, err);
            $('#category-relations-tree').html('<div class="alert alert-danger m-2 p-2 small"><i class="fas fa-exclamation-triangle mr-1"></i> Error de red al cargar categorías.</div>');
            if (typeof callback === 'function') callback();
        }
    });
}

function escapeHtml(str) {
    if (!str) return '';
    return str.toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

let currentCategoryCIs = [];
let selectedCurrentCategoryId = 0;
let selectedCurrentCategoryName = 'Todos los CIs';
let currentSortCol = 'hostname';
let currentSortAsc = true;
let currentQuickFilter = 'all';
let currentPage = 1;
let pageSize = 25;

function renderConfigItemCategoryTree() {
    let container = $('#category-relations-tree');
    if (!container.length) return;
    container.empty();
    
    // Actualizar KPI de total categorías
    if (categories && Array.isArray(categories)) {
        $('#kpi-val-cats').text(categories.length.toLocaleString('es-ES'));
    }
    
    // Add "Todos los CIs" as a root node at the top
    container.append(`
        <ul class="ci-tree-branch-ul mb-1">
            <li class="ci-tree-item" data-id="all" data-name="todos los cis">
                <span class="ci-tree-node-wrapper">
                    <span class="ci-tree-toggle-placeholder" style="display:inline-block; width:16px;"></span>
                    <span class="ci-tree-node ${selectedCurrentCategoryId === 0 ? 'active' : ''}" onclick="selectConfigItemCategory(0, 'Todos los CIs')" title="Todos los CIs">
                        <i class="fas fa-layer-group mr-2 text-primary"></i>
                        <span class="ci-tree-node-name font-weight-bold">Todos los CIs</span>
                    </span>
                </span>
            </li>
        </ul>
    `);
    
    if (!categories || !Array.isArray(categories) || categories.length === 0) {
        return;
    }

    // Estructurar árbol en JS
    let map = {};
    categories.forEach(c => {
        if (c && c.id) {
            map[c.id] = { ...c, children: [] };
        }
    });
    
    let tree = [];
    categories.forEach(c => {
        if (!c || !c.id) return;
        if (c.parent_id && map[c.parent_id]) {
            map[c.parent_id].children.push(map[c.id]);
        } else {
            tree.push(map[c.id]);
        }
    });

    // Calcular totales recursivos
    function calculateRecursiveCounts(node) {
        if (!node) return 0;
        let total = parseInt(node.direct_ci_count) || 0;
        if (node.children && Array.isArray(node.children) && node.children.length > 0) {
            node.children.forEach(child => {
                total += calculateRecursiveCounts(child);
            });
        }
        node.total_ci_count = total;
        return total;
    }

    tree.forEach(root => {
        calculateRecursiveCounts(root);
    });

    function buildHtml(nodes, level) {
        if (!nodes || !Array.isArray(nodes)) return '';
        let h = '<ul class="ci-tree-branch-ul">';
        nodes.forEach(node => {
            if (!node) return;
            let hasChildren = node.children && node.children.length > 0;
            let icon = node.icon || (hasChildren ? 'fa-folder' : 'fa-cube');
            if (icon.indexOf('fa-') === -1) icon = 'fa-' + icon;
            
            let caret = hasChildren ? 
                `<span class="ci-tree-toggle" onclick="toggleConfigItemCategoryBranch(event, this)">
                    <i class="fas fa-chevron-down ci-tree-toggle-icon"></i>
                 </span>` : 
                `<span class="ci-tree-toggle-placeholder" style="display:inline-block; width:16px;"></span>`;
            
            let countBadgeClass = (node.total_ci_count || 0) > 0 ? 'badge-primary' : 'badge-light border text-muted';
            let isActive = selectedCurrentCategoryId == node.id;
            
            h += `
            <li class="ci-tree-item" data-id="${node.id}" data-name="${escapeHtml((node.name || '').toLowerCase())}">
                <span class="ci-tree-node-wrapper">
                    ${caret}
                    <span class="ci-tree-node ${isActive ? 'active' : ''}" onclick="selectConfigItemCategory(${node.id}, '${escapeHtml(node.name)}')" title="Categoría: ${escapeHtml(node.name)}">
                        <i class="fas ${icon} mr-2 text-muted"></i>
                        <span class="ci-tree-node-name text-truncate" style="max-width: 170px;">${escapeHtml(node.name)}</span>
                        <span class="badge ${countBadgeClass} ml-auto" style="font-size: 0.68rem; padding: 2px 6px;">${node.total_ci_count || 0}</span>
                    </span>
                </span>
            `;
            if (hasChildren) {
                h += `<div class="ci-tree-branch" id="config-children-of-${node.id}">`;
                h += buildHtml(node.children, level + 1);
                h += `</div>`;
            }
            h += `</li>`;
        });
        h += '</ul>';
        return h;
    }

    container.append(buildHtml(tree, 0));
    
    // Re-aplicar filtro de búsqueda si había algo escrito
    let currentTreeFilter = $('#cat-tree-search').val();
    if (currentTreeFilter) {
        $('#cat-tree-search').trigger('input');
    }
}

function toggleConfigItemCategoryBranch(event, element) {
    event.stopPropagation();
    const branchDiv = $(element).closest('.ci-tree-item').find('> .ci-tree-branch');
    const icon = $(element).find('.ci-tree-toggle-icon');
    if (branchDiv.is(':visible')) {
        branchDiv.slideUp(180);
        icon.addClass('collapsed');
    } else {
        branchDiv.slideDown(180);
        icon.removeClass('collapsed');
    }
}

function expandAllCategories() {
    $('#category-relations-tree .ci-tree-branch').slideDown(150);
    $('#category-relations-tree .ci-tree-toggle-icon').removeClass('collapsed');
}

function collapseAllCategories() {
    $('#category-relations-tree .ci-tree-branch').slideUp(150);
    $('#category-relations-tree .ci-tree-toggle-icon').addClass('collapsed');
}

function toggleCategorySidebar() {
    let $sidebar = $('#col-categories-tree');
    let $tableCol = $('#col-ci-table');
    let $btn = $('#btn-toggle-tree-sidebar');
    
    if ($sidebar.is(':visible')) {
        $sidebar.hide();
        $tableCol.removeClass('col-xl-9 col-lg-8 col-md-7').addClass('col-12');
        $btn.html('<i class="fas fa-columns mr-1"></i> Mostrar Árbol').addClass('btn-primary').removeClass('btn-outline-secondary');
    } else {
        $sidebar.show();
        $tableCol.removeClass('col-12').addClass('col-xl-9 col-lg-8 col-md-7');
        $btn.html('<i class="fas fa-columns"></i>').removeClass('btn-primary').addClass('btn-outline-secondary');
    }
}

function selectConfigItemCategory(nodeId, nodeName) {
    selectedCurrentCategoryId = nodeId;
    selectedCurrentCategoryName = nodeName;
    currentPage = 1;

    // Desmarcar nodos anteriores e iluminar el actual
    $('#category-relations-tree .ci-tree-node').removeClass('active');
    if (nodeId === 0) {
        $(`#category-relations-tree .ci-tree-item[data-id="all"] > .ci-tree-node-wrapper .ci-tree-node`).addClass('active');
        $('#selected-cat-title').html(`<i class="fas fa-layer-group text-primary mr-2"></i> Todos los CIs`);
        $('#selected-cat-path').html('<i class="fas fa-globe mr-1 text-muted"></i> Inventario global sin filtro jerárquico');
    } else {
        $(`#category-relations-tree .ci-tree-item[data-id="${nodeId}"] > .ci-tree-node-wrapper .ci-tree-node`).addClass('active');
        $('#selected-cat-title').html(`<i class="fas fa-folder-open text-primary mr-2"></i> ${escapeHtml(nodeName)}`);
        
        let lineage = getCategoryLineage(nodeId);
        if (lineage && lineage.length > 0) {
            let pathHtml = lineage.map((c, idx) => {
                let isLast = idx === lineage.length - 1;
                return isLast 
                    ? `<strong class="text-dark">${escapeHtml(c.name)}</strong>` 
                    : `<span class="text-muted cursor-pointer" onclick="selectConfigItemCategory(${c.id}, '${escapeHtml(c.name)}')">${escapeHtml(c.name)}</span>`;
            }).join(' <i class="fas fa-chevron-right mx-1 text-muted small" style="font-size: 0.65rem;"></i> ');
            $('#selected-cat-path').html(pathHtml);
        } else {
            $('#selected-cat-path').text('Categoría: ' + nodeName);
        }
    }
    
    // Si es "Todos los CIs" y tenemos preloadedCIs, renderizar al instante
    if (nodeId === 0 && window.preloadedCIs && Array.isArray(window.preloadedCIs) && window.preloadedCIs.length > 0) {
        currentCategoryCIs = window.preloadedCIs;
        renderConfigItemCIs();
    } else {
        $('#configitem-ci-tbody').html(`
            <tr>
                <td colspan="100%" class="text-center py-5">
                    <div class="spinner-border text-primary" style="width: 2.2rem; height: 2.2rem;"></div>
                    <p class="mt-2.5 text-muted mb-0 font-weight-medium">Cargando inventario de equipos...</p>
                </td>
            </tr>
        `);
        $('#selected-cat-counter').text('Cargando...');
    }

    let url = 'api_ci.php?action=get_instances';
    if (nodeId > 0) {
        url += '&category_id=' + nodeId;
    }
    
    $.get(url, function(res) {
        if (typeof res === 'string') {
            try { res = JSON.parse(res); } catch(e) {}
        }
        if (res && res.success && Array.isArray(res.data)) {
            currentCategoryCIs = res.data;
            if (nodeId === 0) {
                window.preloadedCIs = res.data;
            }
            try {
                renderConfigItemCIs();
            } catch(err) {
                console.error('Error rendering CIs:', err);
                $('#configitem-ci-tbody').html(`<tr><td colspan="100%" class="text-center py-4 text-danger"><i class="fas fa-exclamation-triangle mr-2"></i> Error al renderizar equipos: ${escapeHtml(err.message)}</td></tr>`);
            }
        } else {
            if (!window.preloadedCIs || nodeId !== 0) {
                $('#selected-cat-counter').text('Error');
                $('#configitem-ci-tbody').html(`
                    <tr>
                        <td colspan="100%" class="text-center py-5 text-danger">
                            <i class="fas fa-exclamation-triangle fa-2x mb-2 d-block"></i>
                            Error al cargar los datos: ${escapeHtml((res && res.message) ? res.message : 'Respuesta inválida')}
                        </td>
                    </tr>
                `);
            }
        }
    }, 'json').fail(function(xhr, status, err) {
        if (!window.preloadedCIs || nodeId !== 0) {
            $('#selected-cat-counter').text('Error');
            $('#configitem-ci-tbody').html(`
                <tr>
                    <td colspan="100%" class="text-center py-5 text-danger">
                        <i class="fas fa-exclamation-triangle fa-2x mb-2 d-block"></i>
                        Error de conexión con el servidor (${status}).
                    </td>
                </tr>
            `);
        }
    });
}

function applyQuickFilter(filter) {
    currentQuickFilter = filter;
    currentPage = 1;
    $('.filter-chip-btn').removeClass('active');
    $(`.filter-chip-btn[data-filter="${filter}"]`).addClass('active');
    
    $('.kpi-card').removeClass('kpi-active');
    if (filter === 'all') $('#kpi-card-all').addClass('kpi-active');
    else if (filter === 'with_ip') $('#kpi-card-ip').addClass('kpi-active');
    else if (filter === 'zabbix') $('#kpi-card-zabbix').addClass('kpi-active');
    
    renderConfigItemCIs();
}

function changePageSize(size) {
    pageSize = size;
    currentPage = 1;
    $('.btn-pagesize').removeClass('active');
    $(`.btn-pagesize[data-size="${size}"]`).addClass('active');
    renderConfigItemCIs();
}

function goToPage(page) {
    currentPage = page;
    renderConfigItemCIs();
}

function resetFilters() {
    $('#configitem-ci-search-input').val('');
    applyQuickFilter('all');
}

function refreshCurrentCategoryData() {
    selectConfigItemCategory(selectedCurrentCategoryId, selectedCurrentCategoryName);
}

function copyToClipboard(text, btnElement) {
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(notify).catch(fallback);
    } else {
        fallback();
    }
    function fallback() {
        let input = document.createElement('textarea');
        input.value = text;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        notify();
    }
    function notify() {
        if (typeof toastr !== 'undefined') {
            toastr.success(`Copiado: <strong>${escapeHtml(text)}</strong>`, '', {timeOut: 1800, positionClass: 'toast-bottom-right'});
        } else if (typeof Swal !== 'undefined') {
            Swal.fire({toast: true, position: 'bottom-end', icon: 'success', title: 'Copiado: ' + text, showConfirmButton: false, timer: 1200});
        }
    }
}

function exportCIsToCSV() {
    if (!currentCategoryCIs || currentCategoryCIs.length === 0) {
        if (typeof Swal !== 'undefined') {
            Swal.fire('Información', 'No hay datos en la vista actual para exportar.', 'info');
        } else {
            alert('No hay datos en la vista actual para exportar.');
        }
        return;
    }
    let headers = ['Código Único', 'Hostname', 'IP Address', 'Categoría', 'Sigla', 'Origen', 'Fecha Creación', 'Creado Por'];
    let csvContent = '\uFEFF' + headers.join(';') + '\n';
    
    currentCategoryCIs.forEach(c => {
        let row = [
            c.ci_unique || '',
            c.hostname || '',
            c.ip_address || '',
            c.category_name || '',
            c.sigla || '',
            c.source || 'manual',
            c.created_at || '',
            c.creator_name || ''
        ].map(val => '"' + (val ? val.toString().replace(/"/g, '""') : '') + '"');
        csvContent += row.join(';') + '\n';
    });
    
    let blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    let link = document.createElement('a');
    let url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    let catSlug = (selectedCurrentCategoryName || 'todos').toLowerCase().replace(/[^a-z0-9]+/g, '_');
    link.setAttribute('download', `CMDB_${catSlug}_${new Date().toISOString().slice(0,10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function renderConfigItemCIs() {
    let allCount = (currentCategoryCIs || []).length;
    let withIpCount = 0;
    let zabbixCount = 0;
    
    (currentCategoryCIs || []).forEach(inst => {
        if (inst && inst.ip_address && inst.ip_address.trim() !== '') withIpCount++;
        if (inst && (inst.source === 'zabbix' || (inst.zabbix_host_id && inst.zabbix_host_id > 0))) zabbixCount++;
    });
    let noIpCount = allCount - withIpCount;
    let manualCount = allCount - zabbixCount;

    // Actualizar contadores de las píldoras de filtro
    $('#chip-count-all').text(allCount);
    $('#chip-count-ip').text(withIpCount);
    $('#chip-count-no-ip').text(noIpCount);
    $('#chip-count-zabbix').text(zabbixCount);
    $('#chip-count-manual').text(manualCount);

    // Actualizar contadores KPI si estamos en "Todos los CIs"
    if (selectedCurrentCategoryId === 0) {
        $('#kpi-val-total').text(allCount.toLocaleString('es-ES'));
        $('#kpi-val-ip').text(withIpCount.toLocaleString('es-ES'));
        $('#kpi-val-zabbix').text(zabbixCount.toLocaleString('es-ES'));
    }

    let query = $('#configitem-ci-search-input').val().toLowerCase().trim();
    
    // Filtrado combinado: Texto de búsqueda + Chip activo
    let filtered = (currentCategoryCIs || []).filter(inst => {
        if (!inst) return false;

        // Filtro rápido por chip
        if (currentQuickFilter === 'with_ip' && (!inst.ip_address || inst.ip_address.trim() === '')) return false;
        if (currentQuickFilter === 'no_ip' && (inst.ip_address && inst.ip_address.trim() !== '')) return false;
        if (currentQuickFilter === 'zabbix' && !(inst.source === 'zabbix' || (inst.zabbix_host_id && inst.zabbix_host_id > 0))) return false;
        if (currentQuickFilter === 'manual' && (inst.source === 'zabbix' || (inst.zabbix_host_id && inst.zabbix_host_id > 0))) return false;

        // Búsqueda por texto
        if (!query) return true;
        let ciUnique = (inst.ci_unique || '').toLowerCase();
        let hostname = (inst.hostname || '').toLowerCase();
        let ipAddress = (inst.ip_address || '').toLowerCase();
        let categoryName = (inst.category_name || '').toLowerCase();
        let sigla = (inst.sigla || '').toLowerCase();
        let description = (inst.description || '').toLowerCase();
        let creator = (inst.creator_name || '').toLowerCase();
        let parentName = (inst.parent_ci_name || '').toLowerCase();
        let attrsStr = '';
        if (inst.attributes_json) {
            attrsStr = typeof inst.attributes_json === 'string' ? inst.attributes_json.toLowerCase() : JSON.stringify(inst.attributes_json).toLowerCase();
        }
        
        return ciUnique.includes(query) || 
               hostname.includes(query) || 
               ipAddress.includes(query) || 
               categoryName.includes(query) || 
               sigla.includes(query) || 
               description.includes(query) || 
               creator.includes(query) ||
               parentName.includes(query) ||
               attrsStr.includes(query);
    });
    
    // Ordenamiento dinámico
    filtered.sort((a, b) => {
        let valA = '', valB = '';
        if (currentSortCol === 'ci_unique') {
            valA = a.ci_unique || '';
            valB = b.ci_unique || '';
        } else if (currentSortCol === 'hostname') {
            valA = a.hostname || '';
            valB = b.hostname || '';
        } else if (currentSortCol === 'ip') {
            valA = a.ip_address || '';
            valB = b.ip_address || '';
            if (!valA && !valB) return 0;
            if (!valA) return currentSortAsc ? 1 : -1;
            if (!valB) return currentSortAsc ? -1 : 1;
            let numA = valA.split('.').reduce((acc, octet) => (acc << 8) + (parseInt(octet, 10) || 0), 0) >>> 0;
            let numB = valB.split('.').reduce((acc, octet) => (acc << 8) + (parseInt(octet, 10) || 0), 0) >>> 0;
            return currentSortAsc ? (numA - numB) : (numB - numA);
        } else if (currentSortCol === 'class') {
            valA = a.category_name || '';
            valB = b.category_name || '';
        } else if (currentSortCol === 'sigla') {
            valA = a.sigla || '';
            valB = b.sigla || '';
        } else if (currentSortCol === 'created_at') {
            valA = a.created_at || '';
            valB = b.created_at || '';
        }
        
        return currentSortAsc 
            ? valA.toString().localeCompare(valB.toString(), 'es', {numeric: true}) 
            : valB.toString().localeCompare(valA.toString(), 'es', {numeric: true});
    });

    let totalFiltered = filtered.length;
    $('#selected-cat-counter').text(`Total: ${allCount}`);
    $('#configitem-ci-counter-label').text(`Mostrando ${totalFiltered} de ${allCount} CIs`);
    
    if (totalFiltered === 0) {
        $('#configitem-ci-tbody').html(`
            <tr>
                <td colspan="100%" class="text-center py-5 text-muted">
                    <div class="mb-3">
                        <i class="fas fa-search fa-3x text-muted opacity-50"></i>
                    </div>
                    <h6 class="font-weight-bold text-dark mb-1">No se encontraron equipos</h6>
                    <p class="small text-muted mb-3">No hay ningún Elemento de Configuración que coincida con los criterios de búsqueda.</p>
                    <button type="button" class="btn btn-sm btn-outline-primary shadow-sm" onclick="resetFilters()">
                        <i class="fas fa-undo mr-1"></i> Restablecer Filtros
                    </button>
                </td>
            </tr>
        `);
        $('#ci-pagination-info').text('0 registros');
        $('#ci-pagination-controls').empty();
        return;
    }
    
    // Paginación
    let totalPages = pageSize === 'all' ? 1 : Math.max(1, Math.ceil(totalFiltered / parseInt(pageSize)));
    if (currentPage > totalPages) currentPage = totalPages;
    let startIndex = pageSize === 'all' ? 0 : (currentPage - 1) * parseInt(pageSize);
    let endIndex = pageSize === 'all' ? totalFiltered : Math.min(startIndex + parseInt(pageSize), totalFiltered);
    let pageItems = filtered.slice(startIndex, endIndex);

    let rowsHtml = '';
    pageItems.forEach(inst => {
        if (!inst) return;
        let ciUnique = inst.ci_unique || 'SND-XXXXXXXXXX';
        let ipAddress = inst.ip_address || '';
        let sigla = inst.sigla || '-';
        let creator = inst.creator_name || 'Sistema';
        
        let createdAt = '-';
        if (inst.created_at && inst.created_at !== '0000-00-00 00:00:00') {
            try {
                let d = new Date(inst.created_at.replace(/-/g, '/'));
                if (!isNaN(d.getTime())) {
                    createdAt = d.toLocaleString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
                } else {
                    createdAt = inst.created_at;
                }
            } catch(e) {
                createdAt = inst.created_at;
            }
        }
        
        // Atributos extendidos
        let attrs = {};
        try { attrs = typeof inst.attributes_json === 'string' ? JSON.parse(inst.attributes_json) : inst.attributes_json; } catch(e) {}
        if (!attrs || typeof attrs !== 'object') attrs = {};
        let attrCount = Object.keys(attrs).length;
        
        // Icono de categoría
        let catIcon = inst.category_icon || 'fa-cube';
        if (catIcon.indexOf('fa-') === -1) catIcon = 'fa-' + catIcon;

        // Visualización de IP Address
        let ipDisplay = '';
        if (ipAddress && ipAddress.trim() !== '') {
            ipDisplay = `
                <div class="d-inline-flex align-items-center">
                    <span class="ip-chip">${escapeHtml(ipAddress)}</span>
                    <button type="button" class="btn btn-link btn-xs text-muted p-0 ml-1.5 btn-copy-hover" onclick="copyToClipboard('${escapeHtml(ipAddress)}', this)" title="Copiar dirección IP">
                        <i class="far fa-copy"></i>
                    </button>
                </div>
            `;
        } else {
            ipDisplay = `<span class="text-muted small fst-italic"><i class="fas fa-minus mr-1 opacity-50"></i>Sin IP</span>`;
        }

        // Subtítulos e indicadores
        let parentBadge = '';
        if (inst.parent_ci_name) {
            parentBadge = `
                <div class="mt-0.5 text-muted small d-flex align-items-center">
                    <i class="fas fa-level-up-alt fa-rotate-90 text-primary mr-1" style="font-size: 0.75rem;"></i>
                    <span>Padre: <strong class="text-dark">${escapeHtml(inst.parent_ci_name)}</strong></span>
                </div>
            `;
        }

        let descHtml = inst.description ? `<div class="text-muted small text-truncate mt-0.5" style="max-width: 280px;" title="${escapeHtml(inst.description)}">${escapeHtml(inst.description)}</div>` : '';
        let attrBadge = `<span class="badge badge-pill badge-light border text-secondary px-2 py-0.5 ml-2 cursor-pointer" title="${attrCount} atributos registrados en la ficha técnica" onclick="viewCIDetails(${inst.id})" style="font-size: 0.7rem;"><i class="fas fa-sliders-h mr-1 text-primary"></i>${attrCount} attrs</span>`;
        let statusDot = `<span class="status-indicator status-active" title="Estado: ${escapeHtml(inst.status || 'Activo')}"></span>`;

        let marca = attrs.marca || attrs.Marca || '';
        let modelo = attrs.modelo || attrs.Modelo || '';
        let rack = attrs.rack || attrs.Rack || '';
        let hwBadge = '';
        if (marca || modelo || rack) {
            let hwParts = [];
            if (marca || modelo) {
                hwParts.push(`<span class="badge badge-light border text-dark font-weight-normal mr-1" title="Marca / Modelo"><i class="fas fa-tag text-muted mr-1"></i>${escapeHtml((marca + ' ' + modelo).trim())}</span>`);
            }
            if (rack) {
                hwParts.push(`<span class="badge badge-light border text-dark font-weight-normal" title="Rack / Ubicación"><i class="fas fa-server text-muted mr-1"></i>${escapeHtml(rack)}</span>`);
            }
            hwBadge = `<div class="mt-1 d-flex flex-wrap align-items-center">${hwParts.join('')}</div>`;
        }

        // Origen
        let sourceBadge = '';
        if (inst.source === 'zabbix' || (inst.zabbix_host_id && inst.zabbix_host_id > 0)) {
            sourceBadge = `<span class="badge badge-soft-danger font-weight-bold" title="Zabbix Host ID: ${escapeHtml(inst.zabbix_host_id)}"><i class="fas fa-heartbeat mr-1"></i>Zabbix (${escapeHtml(inst.zabbix_host_id || 'ID')})</span>`;
        } else {
            sourceBadge = `<span class="badge badge-soft-secondary" title="Registro Manual"><i class="fas fa-user-edit mr-1"></i>Manual</span>`;
        }

        rowsHtml += `
            <tr class="ci-row animate__animated animate__fadeIn">
                <!-- Código Único -->
                <td>
                    <div class="d-inline-flex align-items-center">
                        <span class="ci-code-badge">${escapeHtml(ciUnique)}</span>
                        <button type="button" class="btn btn-link btn-xs text-muted p-0 ml-1.5 btn-copy-hover" onclick="copyToClipboard('${escapeHtml(ciUnique)}', this)" title="Copiar código">
                            <i class="far fa-copy"></i>
                        </button>
                    </div>
                </td>

                <!-- Elemento / Hostname -->
                <td>
                    <div class="d-flex align-items-center">
                        <span class="ci-avatar-icon mr-2.5">
                            <i class="fas ${catIcon}"></i>
                        </span>
                        <div>
                            <div class="d-flex align-items-center flex-wrap">
                                ${statusDot}
                                <a href="javascript:void(0)" onclick="viewCIDetails(${inst.id})" class="font-weight-bold text-primary" style="font-size: 0.9rem;" title="Ver Ficha Técnica Completa">
                                    ${escapeHtml(inst.hostname)}
                                </a>
                                ${attrBadge}
                            </div>
                            ${descHtml}
                            ${parentBadge}
                            ${hwBadge}
                        </div>
                    </div>
                </td>

                <!-- IP Address -->
                <td>
                    ${ipDisplay}
                </td>

                <!-- Clase / Categoría -->
                <td>
                    <span class="badge badge-soft-primary px-2.5 py-1" style="font-size: 0.78rem;">
                        <i class="fas ${catIcon} mr-1"></i> ${escapeHtml(inst.category_name || '-')}
                    </span>
                </td>

                <!-- Sigla -->
                <td>
                    <span class="badge badge-soft-secondary font-weight-bold px-2 py-1">${escapeHtml(sigla)}</span>
                </td>

                <!-- Origen & Registro -->
                <td>
                    <div>
                        ${sourceBadge}
                        <small class="text-muted d-block mt-1" title="Fecha de Creación"><i class="far fa-calendar-alt mr-1"></i>${createdAt}</small>
                        <small class="text-muted d-block" title="Registrado por"><i class="far fa-user mr-1"></i>${escapeHtml(creator)}</small>
                    </div>
                </td>

                <!-- Acciones -->
                <td style="text-align: right;">
                    <div class="btn-group btn-group-sm shadow-2xs">
                        <button type="button" class="btn btn-outline-primary" onclick="viewCIDetails(${inst.id})" title="Ficha Técnica">
                            <i class="fas fa-eye"></i>
                        </button>
                        <a href="ci_business_view.php?ci_id=${inst.id}" class="btn btn-outline-info" title="Business View (Topología)">
                            <i class="fas fa-project-diagram"></i>
                        </a>
                        <button type="button" class="btn btn-outline-secondary btn-edit-ci" data-id="${inst.id}" title="Editar CI">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="btn btn-outline-danger" onclick="deleteCI(${inst.id})" title="Eliminar CI">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    $('#configitem-ci-tbody').html(rowsHtml);

    // Actualizar Información de Paginación
    $('#ci-pagination-info').html(`Mostrando del <strong>${startIndex + 1}</strong> al <strong>${endIndex}</strong> de <strong>${totalFiltered}</strong> CIs`);

    // Renderizar Botones de Paginación
    let paginationHtml = '';
    if (totalPages > 1) {
        let prevDisabled = currentPage === 1 ? 'disabled' : '';
        paginationHtml += `
            <li class="page-item ${prevDisabled}">
                <a class="page-link" href="javascript:void(0)" onclick="goToPage(${currentPage - 1})"><i class="fas fa-chevron-left"></i></a>
            </li>
        `;
        
        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, currentPage + 2);
        
        if (startPage > 1) {
            paginationHtml += `<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="goToPage(1)">1</a></li>`;
            if (startPage > 2) paginationHtml += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
        
        for (let p = startPage; p <= endPage; p++) {
            let active = p === currentPage ? 'active' : '';
            paginationHtml += `<li class="page-item ${active}"><a class="page-link" href="javascript:void(0)" onclick="goToPage(${p})">${p}</a></li>`;
        }
        
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) paginationHtml += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            paginationHtml += `<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="goToPage(${totalPages})">${totalPages}</a></li>`;
        }
        
        let nextDisabled = currentPage === totalPages ? 'disabled' : '';
        paginationHtml += `
            <li class="page-item ${nextDisabled}">
                <a class="page-link" href="javascript:void(0)" onclick="goToPage(${currentPage + 1})"><i class="fas fa-chevron-right"></i></a>
            </li>
        `;
    }
    $('#ci-pagination-controls').html(paginationHtml);
}

function sortDynamicCIs(col) {
    if (currentSortCol === col) {
        currentSortAsc = !currentSortAsc;
    } else {
        currentSortCol = col;
        currentSortAsc = true;
    }
    
    // Update sort icons in table headers
    $('#configitem-ci-table th').each(function() {
        let sortType = $(this).data('sort');
        if (sortType) {
            $(this).find('i').remove();
            if (sortType === currentSortCol) {
                $(this).append(currentSortAsc ? ' <i class="fas fa-sort-up ml-1 text-primary"></i>' : ' <i class="fas fa-sort-down ml-1 text-primary"></i>');
            } else {
                $(this).append(' <i class="fas fa-sort text-muted opacity-50 ml-1"></i>');
            }
        }
    });
    
    renderConfigItemCIs();
}

function selectCITreeNode(ciId, hostname) {
    // If not showing "Todos los CIs" already, load all CIs first
    if (selectedCurrentCategoryId !== 0) {
        selectConfigItemCategory(0, 'Todos los CIs');
        setTimeout(function() {
            $('#configitem-ci-search-input').val(hostname);
            renderConfigItemCIs();
        }, 300);
    } else {
        $('#configitem-ci-search-input').val(hostname);
        renderConfigItemCIs();
    }
}

$(document).ready(function() {
    let initialCategoryId = <?php echo $category_id; ?>;

    // Filtro instantáneo del árbol de categorías
    $(document).on('input', '#cat-tree-search', function() {
        let term = $(this).val().toLowerCase().trim();
        if (!term) {
            $('#category-relations-tree li').show();
            return;
        }
        $('#category-relations-tree li').each(function() {
            let name = $(this).attr('data-name') || $(this).find('> .ci-tree-node-wrapper .ci-tree-node-name').text().toLowerCase();
            if (name.includes(term)) {
                $(this).show();
                $(this).parents('li').show();
                $(this).parents('.ci-tree-branch').show();
                $(this).parents('li').find('> .ci-tree-node-wrapper .ci-tree-toggle-icon').removeClass('collapsed');
            } else {
                $(this).hide();
            }
        });
    });

    loadCategories(function() {
        if (initialCategoryId > 0 && Array.isArray(categories)) {
            let catObj = categories.find(c => c.id == initialCategoryId);
            if (catObj) {
                let parentId = catObj.parent_id;
                while (parentId) {
                    let parentBranch = $(`#config-children-of-${parentId}`);
                    if (parentBranch.length) {
                        parentBranch.show();
                        $(`.ci-tree-item[data-id="${parentId}"] > .ci-tree-node-wrapper .ci-tree-toggle-icon`).removeClass('collapsed');
                    }
                    let pObj = categories.find(c => c.id == parentId);
                    parentId = pObj ? pObj.parent_id : null;
                }
                selectConfigItemCategory(initialCategoryId, catObj.name);
                return;
            }
        }
        selectConfigItemCategory(0, 'Todos los CIs');
    });

    // Fallback: If for any reason categories AJAX takes > 1.2s, trigger CI loading anyway
    setTimeout(function() {
        if ($('#configitem-ci-tbody tr').length === 1 && $('#configitem-ci-tbody').text().includes('Cargando')) {
            selectConfigItemCategory(0, 'Todos los CIs');
        }
    }, 1200);
    
    // Search input handler
    $('#configitem-ci-search-input').on('input', function() {
        currentPage = 1;
        renderConfigItemCIs();
    });
    
    // Clear search button handler
    $('#configitem-ci-search-clear-btn').on('click', function() {
        $('#configitem-ci-search-input').val('');
        currentPage = 1;
        renderConfigItemCIs();
    });
});
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>

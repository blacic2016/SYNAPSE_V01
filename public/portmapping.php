<?php
/**
 * Módulo de Portmapping (Gestión y Conectividad Física)
 * Ubicación: /var/www/html/PROYECTOSONDA/PREPODUCCION/CMDBPRnew/public/portmapping.php
 * Soporte completo para Equipos Activos (Switch/Router) y Pasivos (Patch Panel)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/permissions_helper.php';

require_login();

if (!has_module_access('portmapping') && !has_module_access('vilaseca')) {
    header('Location: dashboard.php');
    exit;
}

if (isset($_GET['tab']) && $_GET['tab'] === 'analisis' && isset($_GET['cliente']) && strtoupper($_GET['cliente']) === 'VILASECA') {
    header("Location: " . PUBLIC_URL_PREFIX . "/clientes/vilaseca/index.php");
    exit;
}

$page_title = "Portmapping";
$hide_content_header = true;
require_once __DIR__ . '/partials/header.php';
?>

<!-- Chart.js para Análisis Visual Ejecutivo -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
/* SISTEMA DE DISEÑO DE ALTA FIDELIDAD Y PALETA CORPORATIVA CMDB */
:root {
    --navy-primary: #002B49;
    --navy-secondary: #004080;
    --navy-accent: #0056b3;
    --passive-purple: #6f42c1;
    --success-green: #28a745;
}

/* ESTILOS EXCLUSIVOS DE LA PESTAÑA DE ANÁLISIS VILASECA */
.analysis-header-card {
    background: linear-gradient(135deg, #002B49 0%, #003a6c 50%, #0056b3 100%);
    border-radius: 14px;
    color: #ffffff;
    box-shadow: 0 8px 24px rgba(0, 43, 73, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.stat-kpi-card {
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    box-shadow: 0 2px 10px rgba(0, 43, 73, 0.04);
    padding: 16px 18px;
    transition: all 0.25s ease-in-out;
    position: relative;
    overflow: hidden;
}

.stat-kpi-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0, 43, 73, 0.08);
}

.stat-kpi-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
}

.stat-kpi-card.kpi-blue::before { background: #0056b3; }
.stat-kpi-card.kpi-green::before { background: #28a745; }
.stat-kpi-card.kpi-orange::before { background: #fd7e14; }
.stat-kpi-card.kpi-purple::before { background: #6f42c1; }
.stat-kpi-card.kpi-cyan::before { background: #17a2b8; }

.stat-kpi-num {
    font-size: 2rem;
    font-weight: 800;
    line-height: 1.1;
    color: #002B49;
}

.stat-kpi-label {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: #64748b;
    margin-bottom: 4px;
}

.rank-badge-pill {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 0.8rem;
}

.rank-gold { background: linear-gradient(135deg, #ffd700, #ffa500); color: #000; box-shadow: 0 2px 6px rgba(255, 215, 0, 0.4); }
.rank-silver { background: linear-gradient(135deg, #e0e0e0, #bdbdbd); color: #000; }
.rank-bronze { background: linear-gradient(135deg, #cd7f32, #a0522d); color: #fff; }
.rank-normal { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }

.rack-card-box {
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    box-shadow: 0 3px 12px rgba(0,0,0,0.03);
    transition: all 0.25s ease;
}

.rack-card-box:hover {
    border-color: #0056b3;
    box-shadow: 0 6px 18px rgba(0, 86, 179, 0.1);
    transform: translateY(-2px);
}

.pill-loc-btn {
    border-radius: 20px;
    font-size: 0.82rem;
    font-weight: 700;
    padding: 6px 16px;
    transition: all 0.2s;
    cursor: pointer;
}

.pill-loc-btn.active {
    background-color: var(--navy-primary) !important;
    color: #ffffff !important;
    box-shadow: 0 3px 10px rgba(0, 43, 73, 0.25);
}

.bg-navy {
    background-color: var(--navy-primary) !important;
    color: #ffffff;
}

.text-navy {
    color: var(--navy-primary) !important;
}

.bg-gradient-navy {
    background: linear-gradient(135deg, var(--navy-primary) 0%, var(--navy-secondary) 100%) !important;
    color: #ffffff;
}

.bg-passive {
    background-color: var(--passive-purple) !important;
    color: #ffffff;
}

.text-passive {
    color: var(--passive-purple) !important;
}

/* CONTENEDOR PRINCIPAL Y BARRA DE CABECERA CON PESTAÑAS Y ACCIONES */
.pm-main-card {
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 16px rgba(0, 43, 73, 0.06);
    overflow: hidden;
    margin-top: 15px;
    margin-bottom: 25px;
}

.pm-header-bar {
    background: linear-gradient(135deg, #002B49 0%, #004080 100%);
    padding: 12px 16px 0 16px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}

/* DISTRIBUCIÓN EQUITATIVA Y MODERNA DE PESTAÑAS */
.pm-nav-tabs {
    border-bottom: none;
    display: flex;
    flex: 1 1 auto;
    gap: 6px;
    margin-bottom: 0;
    padding-left: 0;
    list-style: none;
}

.pm-nav-tabs .nav-item {
    flex: 1 1 0;
    min-width: 140px;
    text-align: center;
}

.pm-nav-tabs .nav-link {
    color: rgba(255, 255, 255, 0.85);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-bottom: none;
    border-top-left-radius: 10px;
    border-top-right-radius: 10px;
    padding: 12px 14px;
    font-size: 0.92rem;
    font-weight: 600;
    background-color: rgba(255, 255, 255, 0.08);
    transition: all 0.25s ease-in-out;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    white-space: nowrap;
}

.pm-nav-tabs .nav-link:hover {
    color: #ffffff;
    background-color: rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.3);
    transform: translateY(-1px);
}

.pm-nav-tabs .nav-link.active {
    color: var(--navy-primary) !important;
    background-color: #ffffff !important;
    border-color: #ffffff !important;
    font-weight: 700 !important;
    box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.12);
    position: relative;
}

.pm-nav-tabs .nav-link.active::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--navy-accent);
    border-top-left-radius: 10px;
    border-top-right-radius: 10px;
}

/* BOTONES DE ACCIÓN EN CABECERA */
.pm-action-buttons {
    display: flex;
    align-items: center;
    gap: 10px;
    padding-bottom: 10px;
}

.pm-action-buttons .btn {
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.88rem;
    padding: 7px 16px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    transition: all 0.2s ease;
}

.pm-action-buttons .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(0,0,0,0.25);
}

/* ESTILIZACIÓN DE TABLAS MEJORADA */
.table-custom-pm {
    border-collapse: separate;
    border-spacing: 0;
    width: 100% !important;
}

.table-custom-pm thead th {
    background-color: var(--navy-primary);
    color: #ffffff;
    font-size: 0.82rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 12px 14px;
    border: none;
    vertical-align: middle;
}

.table-custom-pm tbody tr {
    transition: background-color 0.15s ease-in-out;
}

.table-custom-pm tbody tr:nth-of-type(even) {
    background-color: #f8fafc;
}

.table-custom-pm tbody tr:hover {
    background-color: #eef6ff !important;
}

.table-custom-pm tbody tr.row-selected {
    background-color: #fff8e6 !important;
}

.table-custom-pm tbody tr.row-selected:hover {
    background-color: #fef0c7 !important;
}

.table-custom-pm tbody tr.row-selected td {
    border-top-color: #fde68a !important;
}

.table-custom-pm tbody td {
    padding: 11px 14px;
    vertical-align: middle;
    font-size: 0.88rem;
    border-top: 1px solid #e9ecef;
}

/* BADGES DE DISPOSITIVOS Y ESTADOS */
.badge-dev-type {
    padding: 5px 10px;
    border-radius: 6px;
    font-weight: 600;
    font-size: 0.78rem;
    letter-spacing: 0.3px;
}

.badge-dev-switch { background-color: #e3f2fd; color: #0d47a1; border: 1px solid #bbdefb; }
.badge-dev-patch { background-color: #f3ebff; color: #6f42c1; border: 1px solid #d6bbf4; }
.badge-dev-router { background-color: #e0f7fa; color: #006064; border: 1px solid #b2ebf2; }
.badge-dev-server { background-color: #e8f5e9; color: #1b5e20; border: 1px solid #c8e6c9; }

/* SWATCH DE COLOR */
.color-swatch-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 6px;
    font-weight: 600;
    font-size: 0.8rem;
    color: #ffffff;
    box-shadow: 0 2px 4px rgba(0,0,0,0.15);
    text-shadow: 0 1px 2px rgba(0,0,0,0.6);
}

/* INDICADORES DE PASO DEL WIZARD */
.step-indicator {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8f9fa;
    padding: 10px 14px;
    border-radius: 10px;
    border: 1px solid #e9ecef;
    margin-bottom: 20px;
    gap: 8px;
}

.step-item {
    flex: 1;
    text-align: center;
    padding: 10px 12px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    color: #495057;
    background: #ffffff;
    border: 1px solid #ced4da;
    transition: all 0.25s ease-in-out;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.03);
}

.step-item.active {
    color: #ffffff !important;
    background: linear-gradient(135deg, var(--navy-primary) 0%, var(--navy-accent) 100%) !important;
    border-color: var(--navy-primary) !important;
    box-shadow: 0 4px 10px rgba(0, 43, 73, 0.3) !important;
}

.step-item.completed {
    color: #155724 !important;
    background: #d4edda !important;
    border-color: #c3e6cb !important;
}

/* DIAGRAMA DE CONECTIVIDAD VISUAL EN PASO 3 */
.topology-flow-container {
    background: #ffffff;
    border: 1px solid #e3e6f0;
    border-radius: 10px;
    padding: 16px 20px;
    margin-bottom: 24px;
    box-shadow: 0 3px 8px rgba(0,0,0,0.04);
}

.topology-flow-steps {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.topology-node {
    flex: 1;
    background: #f8f9fc;
    border: 2px solid #eaecf4;
    border-radius: 8px;
    padding: 12px 10px;
    text-align: center;
    transition: all 0.3s ease;
}

.topology-node.active-node {
    border-color: var(--navy-accent);
    background: #eef5ff;
}

.topology-node.passive-node {
    border-color: var(--passive-purple);
    background: #f3ebff;
}

.topology-arrow {
    padding: 0 12px;
    color: var(--navy-secondary);
    font-size: 1.2rem;
}

.pm-form-card {
    border-radius: 10px;
    border: 1px solid #e3e6f0;
    transition: all 0.2s ease;
}

.pm-form-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
}

.shadow-xs {
    box-shadow: 0 2px 6px rgba(0,0,0,0.05) !important;
}

.badge-purple {
    background-color: var(--passive-purple);
    color: #ffffff;
}

/* MINIMALIST EXECUTIVE SWITCH FACEPLATE & TOPOLOGY (PASO 4) */
.switch-faceplate-chassis {
    background: #0f172a;
    border: 1px solid #334155;
    border-radius: 6px;
    padding: 8px 12px;
    color: #f8fafc;
    position: relative;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}

.switch-brand-label {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.8px;
    color: #94a3b8;
    text-transform: uppercase;
}

.switch-led-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    display: inline-block;
}

.switch-led-green { background-color: #22c55e; box-shadow: 0 0 4px rgba(34, 197, 94, 0.6); }
.switch-led-off { background-color: #475569; }
.switch-led-amber { background-color: #f59e0b; box-shadow: 0 0 4px rgba(245, 158, 11, 0.6); }

.switch-port-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(44px, 1fr));
    gap: 4px;
}

.switch-port-item {
    background: #1e293b;
    border: 1px solid #334155;
    border-radius: 3px;
    padding: 3px 4px;
    text-align: center;
    cursor: pointer;
    transition: all 0.15s ease-in-out;
    user-select: none;
}

.switch-port-item:hover {
    border-color: #38bdf8;
    background: #182232;
}

.switch-port-item.active-selected {
    border-color: #22c55e !important;
    background: #022c22 !important;
    box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.3) !important;
}

.switch-port-num {
    font-weight: 600;
    font-size: 9.5px;
    color: #cbd5e1;
    display: block;
    line-height: 1;
}

.switch-port-icon {
    font-size: 10px;
    margin: 1px 0;
}

.mini-kpi-pill {
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 6px 12px;
    font-size: 12px;
    color: #334155;
}

.mini-kpi-pill strong {
    color: #0f172a;
}

/* DIAGRAMA VISUAL DE CAMINOS DE CONEXIÓN (PATHWAY FLOW) */
.pathway-flow-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 10px 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}

.pathway-node {
    flex: 1;
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 8px 10px;
    text-align: center;
    position: relative;
    transition: all 0.2s ease;
}

.pathway-node.node-src { border-left: 3px solid #002B49; }
.pathway-node.node-pp-src { border-left: 3px solid #6f42c1; }
.pathway-node.node-pp-tgt { border-left: 3px solid #0891b2; }
.pathway-node.node-dest { border-left: 3px solid #22c55e; }

.pathway-node-title {
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    color: #64748b;
    margin-bottom: 2px;
    display: block;
}

.pathway-node-name {
    font-size: 11.5px;
    font-weight: 700;
    color: #0f172a;
    display: block;
}

.pathway-node-sub {
    font-size: 10px;
    color: #475569;
    display: block;
}

.pathway-arrow {
    color: #94a3b8;
    font-size: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
}

.topo-table-minimal th {
    background-color: #f8fafc;
    color: #475569;
    font-size: 10.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #e2e8f0;
    padding: 8px 10px;
}

.topo-table-minimal td {
    padding: 7px 10px;
    font-size: 11.5px;
    vertical-align: middle;
    border-color: #f1f5f9;
}

.topo-row-highlight {
    background-color: #f0fdf4 !important;
}

/* SECCIONES COGNITIVAS DEL FORMULARIO DE LEVANTAMIENTO (PASO 1) */
.pm-form-section {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    margin-bottom: 20px;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.03);
    overflow: hidden;
    transition: all 0.2s ease;
}

.pm-form-section:hover {
    border-color: #cbd5e1;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
}

.pm-section-header {
    background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
    border-bottom: 1px solid #e2e8f0;
    padding: 11px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.pm-section-title {
    font-size: 0.84rem;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.pm-section-title i {
    font-size: 1rem;
    opacity: 0.9;
}

.pm-section-badge {
    font-size: 10.5px;
    font-weight: 700;
    color: #64748b;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    padding: 3px 8px;
    border-radius: 4px;
}

.pm-section-body {
    padding: 18px 20px;
}

.badge-num-step1 {
    font-size: 10.5px;
    font-weight: 800;
    padding: 2px 6px;
    border-radius: 4px;
    letter-spacing: 0.3px;
    background: #1e293b;
    color: #ffffff;
}
</style>

<!-- CONTENEDOR MÓDULO PORTMAPPING -->
<div class="px-2">
    <div class="card pm-main-card">
        <div class="pm-header-bar">
            <!-- PESTAÑAS EXCLUSIVAS DEL MÓDULO -->
            <ul class="nav pm-nav-tabs" id="pm-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="tab-survey-list" href="#survey-list-content" role="tab" onclick="loadManualSurveys(); return false;">
                        <i class="fas fa-folder-open"></i> <span>01 Levantamientos Registrados</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="tab-survey-wizard" href="#survey-wizard-content" role="tab" onclick="switchToNewPmSurveyTab(); return false;">
                        <i class="fas fa-magic"></i> <span>02 Asistente de Mapeo</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="tab-report-config" href="#report-config-content" role="tab" onclick="loadReportConfigTab(); return false;">
                        <i class="fas fa-sliders-h"></i> <span>03 Configuración del Informe</span>
                    </a>
                </li>
            </ul>

            <!-- BOTONES DE ACCIÓN GLOBAL -->
            <div class="pm-action-buttons">
                <button type="button" class="btn btn-outline-success btn-sm font-weight-bold" onclick="openExcelImportModal()">
                    <i class="fas fa-file-excel mr-1"></i> Importar Excel
                </button>
                <button type="button" class="btn btn-light btn-sm text-navy" onclick="downloadPmTemplate()">
                    <i class="fas fa-download mr-1 text-success"></i> Plantilla Excel
                </button>
                <button type="button" class="btn btn-success btn-sm font-weight-bold" onclick="switchToNewPmSurveyTab()">
                    <i class="fas fa-plus mr-1"></i> Nuevo Levantamiento
                </button>
            </div>
        </div>
        
        <div class="card-body bg-white p-4">
            <div class="tab-content" id="pm-tab-content">
                
                <!-- PESTAÑA 1: LEVANTAMIENTOS REGISTRADOS (Pestaña Principal por Defecto) -->
                <div class="tab-pane fade show active" id="survey-list-content" role="tabpanel" style="display: block;">
                    <div id="pm_survey_list_card" class="card border shadow-xs mb-4" style="border-radius: 10px; overflow: hidden;">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center py-3 flex-wrap" style="gap: 10px;">
                            <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
                                <h5 class="card-title font-weight-bold m-0 text-navy">
                                    <i class="fas fa-folder-open text-primary mr-2"></i> Levantamientos de Portmapping Registrados
                                </h5>
                                <span id="survey_count_badge" class="badge badge-primary px-2 py-1 font-weight-bold">0</span>
                                <div id="client_filter_badge_wrapper" style="display: none;">
                                    <span class="badge badge-warning text-dark px-2 py-1 font-weight-bold" id="client_filter_badge_text">
                                        <i class="fas fa-filter mr-1"></i> Cliente: VILASECA
                                        <a href="portmapping.php" class="text-dark ml-1 font-weight-bold" title="Quitar filtro de cliente" style="text-decoration: none;">&times;</a>
                                    </span>
                                </div>
                            </div>
                            <div class="d-flex align-items-center flex-wrap" style="gap: 10px;">
                                <!-- BARRA DE ACCIÓN GLOBAL / BATCH ACTIONS -->
                                <div id="pm_surveys_batch_bar" class="d-none align-items-center" style="gap: 8px;">
                                    <span class="badge badge-warning text-dark px-3 py-2 font-weight-bold shadow-xs" style="font-size: 0.85rem; border: 1px solid #d97706;">
                                        <i class="fas fa-check-square mr-1"></i> <span id="lbl_selected_surveys_count">0</span> seleccionados
                                    </span>
                                    <button type="button" class="btn btn-danger btn-sm shadow-sm font-weight-bold px-3" onclick="bulkDeleteSelectedSurveys()" title="Eliminar los levantamientos seleccionados">
                                        <i class="fas fa-trash-alt mr-1"></i> Borrar Seleccionados
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm font-weight-bold" onclick="deselectAllSurveys()" title="Deseleccionar todo">
                                        <i class="fas fa-times mr-1"></i> Deseleccionar
                                    </button>
                                </div>

                                <!-- BUSCADOR GENERAL EN 01 LEVANTAMIENTO DE REGISTRO -->
                                <div class="input-group input-group-sm" style="width: 280px;">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-white border-right-0 text-primary"><i class="fas fa-search"></i></span>
                                    </div>
                                    <input type="text" id="pm_survey_search_input" class="form-control border-left-0 font-weight-normal" placeholder="Buscar general..." oninput="filterAndSortSurveysTable()">
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="clearPmSurveySearch()" title="Limpiar búsqueda"><i class="fas fa-times"></i></button>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-outline-success btn-sm shadow-sm font-weight-bold" onclick="openExcelImportModal()">
                                    <i class="fas fa-file-excel mr-1"></i> Importar Excel
                                </button>
                                <button class="btn btn-primary btn-sm shadow-sm font-weight-bold" onclick="switchToNewPmSurveyTab()">
                                    <i class="fas fa-plus mr-1"></i> Nuevo Levantamiento
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-custom-pm table-hover align-middle m-0" id="tbl_manual_surveys">
                                    <thead>
                                        <tr style="user-select: none;">
                                            <th class="text-center sortable-col" onclick="sortSurveysByColumn('id')" style="width: 85px; cursor: pointer;" title="Haga clic para ordenar por ID">
                                                ID <i class="fas fa-sort text-white-50 ml-1" id="sort_icon_id"></i>
                                            </th>
                                            <th class="sortable-col" onclick="sortSurveysByColumn('client')" style="width: 17%; cursor: pointer;" title="Haga clic para ordenar por Cliente">
                                                Cliente <i class="fas fa-sort text-white-50 ml-1" id="sort_icon_client"></i>
                                            </th>
                                            <th class="sortable-col" onclick="sortSurveysByColumn('location')" style="width: 20%; cursor: pointer;" title="Haga clic para ordenar por Ubicación">
                                                Ubicación <i class="fas fa-sort text-white-50 ml-1" id="sort_icon_location"></i>
                                            </th>
                                            <th class="sortable-col" onclick="sortSurveysByColumn('device_name')" style="width: 18%; cursor: pointer;" title="Haga clic para ordenar por Equipo Origen">
                                                Equipo Origen <i class="fas fa-sort text-white-50 ml-1" id="sort_icon_device_name"></i>
                                            </th>
                                            <th class="sortable-col" onclick="sortSurveysByColumn('device_type')" style="width: 13%; cursor: pointer;" title="Haga clic para ordenar por Tipo Hardware">
                                                Tipo Hardware <i class="fas fa-sort text-white-50 ml-1" id="sort_icon_device_type"></i>
                                            </th>
                                            <th class="text-center sortable-col" onclick="sortSurveysByColumn('creation_date')" style="width: 12%; cursor: pointer;" title="Haga clic para ordenar por Fecha">
                                                Fecha <i class="fas fa-sort text-white-50 ml-1" id="sort_icon_creation_date"></i>
                                            </th>
                                            <th class="text-center" style="width: 145px; cursor: default;">Acciones</th>
                                            <th class="text-center" style="width: 48px; cursor: default;" title="Seleccionar todos">
                                                <div class="custom-control custom-checkbox d-inline-block">
                                                    <input type="checkbox" class="custom-control-input" id="chk_select_all_surveys" onchange="toggleSelectAllSurveys(this)">
                                                    <label class="custom-control-label cursor-pointer" for="chk_select_all_surveys" title="Seleccionar todos"></label>
                                                </div>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbl_manual_surveys_body">
                                        <tr>
                                            <td colspan="8" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin mr-2"></i> Cargando levantamientos...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Floating Sticky Batch Bar -->
                    <div id="pm_floating_batch_bar" class="shadow-lg position-fixed d-none" style="bottom: 25px; right: 35px; z-index: 1050; border-radius: 30px; background: #002B49; color: #fff; padding: 10px 22px; box-shadow: 0 10px 30px rgba(0, 43, 73, 0.45) !important;">
                        <div class="d-flex align-items-center" style="gap: 15px;">
                            <div class="d-flex align-items-center">
                                <span class="badge badge-warning text-dark font-weight-bold mr-2" id="lbl_floating_count" style="font-size: 0.9rem; border-radius: 12px; padding: 4px 10px;">0</span>
                                <span class="font-weight-bold small text-white">seleccionados</span>
                            </div>
                            <div style="height: 20px; width: 1px; background: rgba(255,255,255,0.25);"></div>
                            <button type="button" class="btn btn-danger btn-sm font-weight-bold shadow-sm rounded-pill px-3" onclick="bulkDeleteSelectedSurveys()">
                                <i class="fas fa-trash-alt mr-1"></i> Borrar Seleccionados
                            </button>
                            <button type="button" class="btn btn-outline-light btn-sm rounded-pill px-2" onclick="deselectAllSurveys()" title="Cancelar selección">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- PESTAÑA 2: ASISTENTE DE MAPEO (Wizard de Creación / Edición) -->
                <div class="tab-pane fade" id="survey-wizard-content" role="tabpanel" style="display: none;">
                    <div id="pm_survey_wizard_card" class="card border shadow-sm" style="display: block; border-radius: 12px;">
                        <div class="card-header bg-gradient-navy text-white d-flex justify-content-between align-items-center">
                            <h5 class="card-title font-weight-bold m-0" id="pm_survey_title"><i class="fas fa-tools mr-2"></i> Asistente de Levantamiento de Puerto</h5>
                            <button class="btn btn-sm btn-outline-light font-weight-bold" onclick="openNewPmSurveyModal()"><i class="fas fa-redo mr-1"></i> Reiniciar / Nuevo</button>
                        </div>
                        
                        <div class="card-body p-4">
                            <div class="step-indicator mb-4">
                                <div class="step-item active" id="pm_ind_step1" onclick="goToPmSurveyStep(1)" style="cursor:pointer;"><i class="fas fa-info-circle mr-1"></i> 1. Información del Equipo</div>
                                <div class="step-item" id="pm_ind_step2" onclick="goToPmSurveyStep(2)" style="cursor:pointer;"><i class="fas fa-network-wired mr-1"></i> 2. Asignación de Puertos</div>
                                <div class="step-item" id="pm_ind_step3" onclick="goToPmSurveyStep(3)" style="cursor:pointer;"><i class="fas fa-camera mr-1"></i> 3. Registro Fotográfico</div>
                                <div class="step-item" id="pm_ind_step4" onclick="goToPmSurveyStep(4)" style="cursor:pointer;"><i class="fas fa-microchip mr-1"></i> 4. Gráfico del Equipo</div>
                                <div class="step-item" id="pm_ind_step5" onclick="goToPmSurveyStep(5)" style="cursor:pointer;"><i class="fas fa-file-alt mr-1"></i> 5. Archivo de configuraciones</div>
                                <div class="step-item" id="pm_ind_step6" onclick="goToPmSurveyStep(6)" style="cursor:pointer;"><i class="fas fa-sitemap mr-1"></i> 6. Diagrama</div>
                                <div class="step-item" id="pm_ind_step7" onclick="goToPmSurveyStep(7)" style="cursor:pointer;"><i class="fas fa-file-pdf mr-1"></i> 7. Informe y PDF</div>
                            </div>

                            <form id="form_pm_survey">
                                <input type="hidden" id="pm_survey_id" value="">

                                <!-- PASO 1: INFORMACIÓN GENERAL -->
                                <div class="pm-survey-step" id="pm_step1">
                                    <div class="callout callout-info py-2 px-3 mb-4 d-flex justify-content-between align-items-center" style="border-left-width: 4px;">
                                        <div>
                                            <h6 class="font-weight-bold mb-1 text-navy"><i class="fas fa-list-ol mr-1"></i> Formulario de Levantamiento del Equipo (Campos 00 - 13)</h6>
                                            <small class="text-muted">Complete los datos de la infraestructura organizados en secciones o autocomplete la información trayéndola directamente desde Zabbix.</small>
                                        </div>
                                        <div>
                                            <button type="button" class="btn btn-sm shadow-xs font-weight-bold text-white px-3" style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); border:none;" onclick="openZabbixSelectModal()">
                                                <i class="fas fa-bolt text-warning mr-1"></i> Cargar desde Zabbix
                                            </button>
                                        </div>
                                    </div>

                                    <!-- 📍 SECCIÓN 1: CONTEXTO GENERAL Y UBICACIÓN -->
                                    <div class="pm-form-section">
                                        <div class="pm-section-header">
                                            <h6 class="pm-section-title">
                                                <i class="fas fa-map-marker-alt text-danger"></i> SECCIÓN 1: CONTEXTO GENERAL Y UBICACIÓN
                                            </h6>
                                            <span class="pm-section-badge">Campos 00 - 03</span>
                                        </div>
                                        <div class="pm-section-body">
                                            <div class="form-row mb-3">
                                                <div class="form-group col-md-6 mb-0">
                                                    <label class="font-weight-bold small text-navy"><span class="badge-num-step1 mr-1">00</span> Fecha de Levantamiento *</label>
                                                    <input type="date" id="pm-survey-date" class="form-control font-weight-bold" value="<?php echo date('Y-m-d'); ?>" required>
                                                </div>
                                                <div class="form-group col-md-6 mb-0">
                                                    <label class="font-weight-bold small text-navy"><span class="badge-num-step1 mr-1">01</span> Cliente / Empresa *</label>
                                                    <input type="text" id="pm-survey-client" class="form-control font-weight-bold" value="<?php echo htmlspecialchars($_GET['cliente'] ?? 'VILASECA'); ?>" placeholder="Ej. VILASECA" required>
                                                </div>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group col-md-6 mb-0">
                                                    <label class="font-weight-bold small text-navy"><span class="badge-num-step1 mr-1">02</span> Ubicación / Site *</label>
                                                    <input type="text" id="pm-survey-location" class="form-control" placeholder="Ej. Data Center Principal - Piso 2" required>
                                                </div>
                                                <div class="form-group col-md-6 mb-0">
                                                    <label class="font-weight-bold small text-navy"><span class="badge-num-step1 mr-1">03</span> Área *</label>
                                                    <input type="text" id="pm-survey-area" class="form-control" placeholder="Ej. Telecomunicaciones / Servidores" required>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- 🔌 SECCIÓN 2: UBICACIÓN FÍSICA EN EL RACK -->
                                    <div class="pm-form-section">
                                        <div class="pm-section-header">
                                            <h6 class="pm-section-title">
                                                <i class="fas fa-plug text-primary"></i> SECCIÓN 2: UBICACIÓN FÍSICA EN EL RACK
                                            </h6>
                                            <span class="pm-section-badge">Campos 04 - 05</span>
                                        </div>
                                        <div class="pm-section-body">
                                            <div class="form-row">
                                                <div class="form-group col-md-6 mb-0">
                                                    <label class="font-weight-bold small text-navy"><span class="badge-num-step1 mr-1">04</span> Rack Origen *</label>
                                                    <input type="text" id="pm-survey-rack" class="form-control font-weight-bold" placeholder="Ej. RACK-A01" required oninput="autofillPpOrigen(); renderPmPortsTable();">
                                                </div>
                                                <div class="form-group col-md-6 mb-0">
                                                    <label class="font-weight-bold small text-navy"><span class="badge-num-step1 mr-1">05</span> UR - Rack (1 - 48) *</label>
                                                    <input type="number" id="pm-survey-ur" class="form-control font-weight-bold" min="1" max="48" placeholder="Ej. 12" required oninput="autofillPpOrigen(); renderPmPortsTable();">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- 🏷️ SECCIÓN 3: IDENTIFICACIÓN DEL EQUIPO -->
                                    <div class="pm-form-section">
                                        <div class="pm-section-header">
                                            <h6 class="pm-section-title">
                                                <i class="fas fa-tag text-warning"></i> SECCIÓN 3: IDENTIFICACIÓN DEL EQUIPO
                                            </h6>
                                            <span class="pm-section-badge">Campos 06 - 09</span>
                                        </div>
                                        <div class="pm-section-body">
                                            <div class="form-row mb-3">
                                                <div class="form-group col-md-6 mb-0">
                                                    <label class="font-weight-bold small text-navy"><span class="badge-num-step1 mr-1">06</span> Tipo de Equipamiento *</label>
                                                    <select id="pm-survey-device-type" class="form-control font-weight-bold text-navy border-primary" onchange="updatePatchPanelFormVisibility(); renderPmPortsTable();">
                                                        <option value="Switch">Switch</option>
                                                        <option value="Router">Router</option>
                                                        <option value="Firewall">Firewall</option>
                                                        <option value="Patch Panel">Patch Panel (Pasivo)</option>
                                                        <option value="Servidor">Servidor</option>
                                                        <option value="UPS">UPS</option>
                                                        <option value="PDU">PDU</option>
                                                        <option value="Otro">Otro</option>
                                                    </select>
                                                </div>
                                                <div class="form-group col-md-6 mb-0">
                                                    <label class="font-weight-bold small text-navy"><span class="badge-num-step1 mr-1">07</span> Nombre del Equipo *</label>
                                                    <input type="text" id="pm-survey-device" class="form-control font-weight-bold text-primary" placeholder="Ej. SW-CORE-01 o PP-01" required oninput="autofillPpOrigen(); renderPmPortsTable();">
                                                </div>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group col-md-6 mb-0">
                                                    <label class="font-weight-bold small text-navy"><span class="badge-num-step1 mr-1">08</span> Etiqueta Equipo *</label>
                                                    <input type="text" id="pm-survey-device-label" class="form-control" placeholder="Ej. LBL-SW-01">
                                                </div>
                                                <div class="form-group col-md-6 mb-0">
                                                    <label class="font-weight-bold small text-navy"><span class="badge-num-step1 mr-1">09</span> Hostname</label>
                                                    <input type="text" id="pm-survey-hostname" class="form-control" placeholder="Ej. sw-core-01.vilaseca.local">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- ⚙️ SECCIÓN 4: ESPECIFICACIONES TÉCNICAS Y HARDWARE -->
                                    <div class="pm-form-section">
                                        <div class="pm-section-header">
                                            <h6 class="pm-section-title">
                                                <i class="fas fa-cogs text-info"></i> SECCIÓN 4: ESPECIFICACIONES TÉCNICAS Y HARDWARE
                                            </h6>
                                            <span class="pm-section-badge">Campos 10 - 12</span>
                                        </div>
                                        <div class="pm-section-body">
                                            <div class="form-row mb-3">
                                                <div class="form-group col-md-6 mb-0">
                                                    <label class="font-weight-bold small text-navy"><span class="badge-num-step1 mr-1">10</span> Fabricante</label>
                                                    <input type="text" id="pm-survey-vendor" class="form-control" placeholder="Ej. Cisco, Panduit, CommScope">
                                                </div>
                                                <div class="form-group col-md-6 mb-0">
                                                    <label class="font-weight-bold small text-navy"><span class="badge-num-step1 mr-1">11</span> Número de Serie</label>
                                                    <input type="text" id="pm-survey-serial" class="form-control" placeholder="Ej. SN123456789">
                                                </div>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group col-md-6 mb-0">
                                                    <label class="font-weight-bold small text-navy"><span class="badge-num-step1 mr-1" style="background:#0284c7;">12</span> Número de Puertos (1-96) *</label>
                                                    <input type="number" id="pm-survey-ports-count" class="form-control font-weight-bold text-success border-success" min="1" max="96" value="24" placeholder="Ej. 24" required onchange="syncPortsFromStep1(true)" oninput="if(this.value > 96) this.value = 96; if(this.value < 1 && this.value !== '') this.value = 1; syncPortsFromStep1(true);">
                                                    <small class="text-muted"><i class="fas fa-info-circle mr-1"></i> Cantidad de puertos que se inicializarán en el mapeo.</small>
                                                </div>
                                                <div class="form-group col-md-6 mb-0 d-flex align-items-center">
                                                    <div class="alert alert-light border w-100 mb-0 py-2 px-3 small text-muted">
                                                        <i class="fas fa-network-wired text-primary mr-1"></i> El asistente configurará automáticamente las interfaces con los datos de rack y equipo.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- 📝 SECCIÓN 5: OBSERVACIONES -->
                                    <div class="pm-form-section">
                                        <div class="pm-section-header">
                                            <h6 class="pm-section-title">
                                                <i class="fas fa-sticky-note text-secondary"></i> SECCIÓN 5: OBSERVACIONES
                                            </h6>
                                            <span class="pm-section-badge">Campo 13</span>
                                        </div>
                                        <div class="pm-section-body">
                                            <div class="form-group mb-0">
                                                <label class="font-weight-bold small text-navy"><span class="badge-num-step1 mr-1">13</span> Observaciones / Notas del Equipo</label>
                                                <textarea id="pm-survey-description" class="form-control" rows="2" placeholder="Notas sobre el estado del gabinete, ventilación, parches, advertencias técnicas..."></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-end mt-4">
                                        <button type="button" class="btn btn-primary px-4 shadow-sm font-weight-bold" onclick="goToPmSurveyStep(2)">Siguiente: Asignación de Puertos (Paso 2) <i class="fas fa-arrow-right ml-1"></i></button>
                                    </div>
                                </div>

                                <!-- PASO 2: ASIGNACIÓN DE PUERTOS Y MATRIZ 4 DIVISIONES -->
                                <div class="pm-survey-step" id="pm_step2" style="display: none;">
                                    
                                    <!-- DIAGRAMA DINÁMICO DE CONECTIVIDAD DE 4 DIVISIONES -->
                                    <div class="topology-flow-container mb-4 p-3 bg-light border rounded shadow-xs">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h6 class="font-weight-bold m-0 text-navy"><i class="fas fa-project-diagram mr-1"></i> Cadena de Conectividad Física (4 Divisiones de Red)</h6>
                                            <span id="topology_mode_badge" class="badge badge-primary px-3 py-1 font-weight-bold">Modo Hardware Activo</span>
                                        </div>
                                        <div class="d-flex align-items-center justify-content-around text-center py-2 bg-white rounded border">
                                            <div class="px-3 py-2 border rounded bg-light shadow-2xs">
                                                <i class="fas fa-server text-navy fa-2x mb-1 d-block"></i>
                                                <span class="font-weight-bold d-block text-navy" id="topo_dev_src">1. Equipamiento Origen</span>
                                                <small class="badge badge-navy">Obligatorio</small>
                                            </div>
                                            <div class="font-weight-bold text-muted px-2"><i class="fas fa-arrow-right fa-lg text-primary"></i></div>
                                            <div class="px-3 py-2 border rounded bg-light shadow-2xs">
                                                <i class="fas fa-network-wired text-purple fa-2x mb-1 d-block"></i>
                                                <span class="font-weight-bold d-block text-purple" id="topo_pp_src">2. Patch Panel Origen</span>
                                                <small class="badge badge-secondary">(Opcional)</small>
                                            </div>
                                            <div class="font-weight-bold text-muted px-2"><i class="fas fa-arrow-right fa-lg text-info"></i></div>
                                            <div class="px-3 py-2 border rounded bg-light shadow-2xs">
                                                <i class="fas fa-network-wired text-info fa-2x mb-1 d-block"></i>
                                                <span class="font-weight-bold d-block text-info" id="topo_pp_tgt">3. Patch Panel Destino</span>
                                                <small class="badge badge-secondary">(Opcional)</small>
                                            </div>
                                            <div class="font-weight-bold text-muted px-2"><i class="fas fa-arrow-right fa-lg text-success"></i></div>
                                            <div class="px-3 py-2 border rounded bg-light shadow-2xs">
                                                <i class="fas fa-desktop text-success fa-2x mb-1 d-block"></i>
                                                <span class="font-weight-bold d-block text-success" id="topo_dev_tgt">4. Equipamiento Destino</span>
                                                <small class="badge badge-secondary">(Opcional)</small>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- BARRA DE HERRAMIENTAS Y MATRIZ 1-N -->
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div>
                                            <h6 class="font-weight-bold text-navy mb-0"><i class="fas fa-table mr-1"></i> Matriz Completa de Mapeo de Puertos (1 - N)</h6>
                                            <small class="text-muted">Puerto origen estrictamente numérico (1, 2, 3...). Modifique cualquier celda individualmente.</small>
                                        </div>
                                        <div class="d-flex align-items-center" style="gap:10px;">
                                            <!-- FILTRO DE ESTADO DE PUERTOS (TODOS / UP / DOWN) -->
                                            <div class="input-group input-group-sm" style="width: 230px;">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text font-weight-bold bg-white text-navy"><i class="fas fa-filter text-primary mr-1"></i> Estado:</span>
                                                </div>
                                                <select id="pm_step2_status_filter" class="custom-select custom-select-sm font-weight-bold text-navy border-primary" onchange="renderPmPortsTable()">
                                                    <option value="all">Todos los Puertos</option>
                                                    <option value="Up">&bull; Solo Activos (Up)</option>
                                                    <option value="Down">&bull; Solo Inactivos (Down)</option>
                                                </select>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-info font-weight-bold px-3 shadow-2xs" onclick="addSinglePortToMatrix()">
                                                <i class="fas fa-plus mr-1"></i> Añadir Puerto Extra
                                            </button>
                                        </div>
                                    </div>

                                    <!-- VISTA PREVIA MATRIZ DE PUERTOS EN 4 DIVISIONES -->
                                    <div id="pm-survey-ports-preview">
                                        <!-- Tabla 4 divisiones renderizada por JS -->
                                    </div>

                                    <div class="d-flex justify-content-between mt-4">
                                        <button type="button" class="btn btn-secondary font-weight-bold" onclick="goToPmSurveyStep(1)"><i class="fas fa-arrow-left mr-1"></i> Atrás: Información del Equipo</button>
                                        <button type="button" class="btn btn-primary px-4 shadow-sm font-weight-bold" onclick="goToPmSurveyStep(3)">Siguiente: Registro Fotográfico (Paso 3) <i class="fas fa-arrow-right ml-1"></i></button>
                                    </div>
                                </div>

                                <!-- PASO 3: REGISTRO FOTOGRÁFICO DE EVIDENCIA -->
                                <div class="pm-survey-step" id="pm_step3" style="display: none;">
                                    <div class="callout callout-info py-2 px-3 mb-4" style="border-left-width: 4px;">
                                        <h6 class="font-weight-bold mb-1 text-navy"><i class="fas fa-camera mr-1"></i> Registro Fotográfico de Evidencia de Infraestructura</h6>
                                        <small class="text-muted">Cargue las fotografías del equipo. Se asociará automáticamente la <strong>Fecha de Levantamiento (00)</strong> como tag permanente a cada imagen subida.</small>
                                    </div>

                                    <div class="card pm-form-card mb-4 shadow-xs border">
                                        <div class="card-body">
                                            <div class="form-group mb-3">
                                                <label class="font-weight-bold small text-navy"><i class="fas fa-tags mr-1"></i> Etiquetas / Tags de Imágenes (Separados por Comas)</label>
                                                <input type="text" id="pm-bulk-image-tags" class="form-control font-weight-bold" value="SWBW, TWNNWR" placeholder="Ej. SWBW, TWNNWR, FRONTAL, RACK-A">
                                                <small class="form-text text-muted">Los tags ingresados por comas se aplicarán al lote de fotos. La <strong>fecha de levantamiento</strong> siempre se añadirá como el primer tag de la foto.</small>
                                            </div>

                                            <div class="custom-file mb-3">
                                                <input type="file" class="custom-file-input" id="pm-survey-images" multiple accept="image/*" onchange="handlePmImageSelect(event)">
                                                <label class="custom-file-label font-weight-bold" for="pm-survey-images">Seleccionar fotografías del equipo (Bulk Upload)...</label>
                                            </div>

                                            <div class="row mt-3" id="pm_image_preview_container">
                                                <!-- Vistas previas con tags automáticos renderizadas por JS -->
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between mt-4">
                                        <button type="button" class="btn btn-secondary font-weight-bold" onclick="goToPmSurveyStep(2)"><i class="fas fa-arrow-left mr-1"></i> Atrás: Asignación de Puertos</button>
                                        <button type="button" class="btn btn-primary px-4 shadow-sm font-weight-bold" onclick="goToPmSurveyStep(4)">Siguiente: Gráfico del Equipo (Paso 4) <i class="fas fa-arrow-right ml-1"></i></button>
                                    </div>
                                </div>

                                <!-- PASO 4: VISUALIZACIÓN GRÁFICA DEL EQUIPO Y CAMINOS -->
                                <div class="pm-survey-step" id="pm_step4" style="display: none;">
                                    <div class="callout callout-success py-2 px-3 mb-4" style="border-left-width: 4px;">
                                        <h6 class="font-weight-bold mb-1 text-navy"><i class="fas fa-microchip mr-2 text-primary"></i> Paso 4: Visualización Gráfica del Equipo y Caminos de Conexión</h6>
                                        <small class="text-muted">Representación interactiva del chasis (Switch/Patch Panel), traza de caminos de 4 nodos al hacer clic y matriz de conectividad.</small>
                                    </div>

                                    <div id="pm-survey-final-preview">
                                        <!-- Resumen final y gráficos renderizados por JS -->
                                    </div>

                                    <div class="d-flex justify-content-between mt-4">
                                        <button type="button" class="btn btn-secondary font-weight-bold" onclick="goToPmSurveyStep(3)"><i class="fas fa-arrow-left mr-1"></i> Atrás: Registro Fotográfico</button>
                                        <button type="button" class="btn btn-primary px-4 shadow-sm font-weight-bold" onclick="goToPmSurveyStep(5)">Siguiente: Archivo de configuraciones (Paso 5) <i class="fas fa-arrow-right ml-1"></i></button>
                                    </div>
                                </div>

                                <!-- PASO 5: ARCHIVO DE CONFIGURACIONES DEL EQUIPO -->
                                <div class="pm-survey-step" id="pm_step5" style="display: none;">
                                    <div class="callout callout-warning py-2 px-3 mb-4" style="border-left-width: 4px; border-left-color: #f59e0b;">
                                        <h6 class="font-weight-bold mb-1 text-navy"><i class="fas fa-file-alt mr-2 text-warning"></i> Paso 5: Archivos de Configuración del Equipo (.txt / .cfg)</h6>
                                        <small class="text-muted">Suba, almacene y gestione los archivos de configuración, respaldos, scripts o logs asociados al equipo en este levantamiento.</small>
                                    </div>

                                    <div class="card pm-form-card mb-4 shadow-xs border">
                                        <div class="card-body">
                                            <div class="form-group mb-3">
                                                <label class="font-weight-bold small text-navy"><i class="fas fa-upload mr-1"></i> Subir Archivo de Configuración (.txt, .cfg, .conf, .log, .json, .ini)</label>
                                                <div class="custom-file">
                                                    <input type="file" class="custom-file-input" id="pm_config_file_input" accept=".txt,.cfg,.conf,.log,.json,.ini,.xml,.yaml,.yml" onchange="uploadPmConfigFile(event)">
                                                    <label class="custom-file-label font-weight-bold text-muted" for="pm_config_file_input">Seleccionar archivo de configuración desde el equipo local...</label>
                                                </div>
                                            </div>

                                            <div class="table-responsive rounded border shadow-2xs mt-4">
                                                <table class="table table-hover align-middle mb-0" style="font-size: 12px;">
                                                    <thead class="bg-navy text-white">
                                                        <tr>
                                                            <th>#</th>
                                                            <th>Nombre del Archivo</th>
                                                            <th>Tamaño</th>
                                                            <th>Fecha de Carga</th>
                                                            <th class="text-center">Vista Previa</th>
                                                            <th class="text-center">Acciones</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="pm_config_files_table_body">
                                                        <tr><td colspan="6" class="text-center py-4 text-muted"><i class="fas fa-folder-open fa-2x mb-2 d-block text-secondary"></i>No se han adjuntado archivos de configuración a este equipo.</td></tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between mt-4">
                                        <button type="button" class="btn btn-secondary font-weight-bold" onclick="goToPmSurveyStep(4)"><i class="fas fa-arrow-left mr-1"></i> Atrás: Gráfico del Equipo (Paso 4)</button>
                                        <button type="button" class="btn btn-primary px-4 shadow-sm font-weight-bold" onclick="goToPmSurveyStep(6)">Siguiente: Diagrama (Paso 6) <i class="fas fa-arrow-right ml-1"></i></button>
                                    </div>
                                </div>

                                <!-- PASO 6: DIAGRAMA DE ARQUITECTURA Y RED -->
                                <div class="pm-survey-step" id="pm_step6" style="display: none;">
                                    <div class="callout callout-info py-2 px-3 mb-4" style="border-left-width: 4px;">
                                        <h6 class="font-weight-bold mb-1 text-navy"><i class="fas fa-sitemap mr-2 text-info"></i> Paso 6: Diagrama de Arquitectura y Conectividad (Visio / Imagen)</h6>
                                        <small class="text-muted">Adjunte un diagrama de red (Visio .vsdx, .vdx, SVG, PNG, JPG, PDF) o vincule un modelo de diagrama Visio existente creado en la plataforma.</small>
                                    </div>

                                    <div class="card pm-form-card mb-4 shadow-xs border">
                                        <div class="card-body">
                                            <ul class="nav nav-pills mb-3" id="diagram-source-tabs" style="gap:5px;">
                                                <li class="nav-item">
                                                    <a class="nav-link active font-weight-bold py-2 px-3" id="tab-diag-upload" data-toggle="pill" href="#diag-upload-pane"><i class="fas fa-upload mr-1"></i> Subir Archivo de Diagrama (Visio / Imagen)</a>
                                                </li>
                                                <li class="nav-item">
                                                    <a class="nav-link font-weight-bold py-2 px-3" id="tab-diag-visio" data-toggle="pill" href="#diag-visio-pane" onclick="loadVisioModelsList()"><i class="fas fa-project-diagram mr-1"></i> Seleccionar Modelo Visio Existente</a>
                                                </li>
                                            </ul>

                                            <div class="tab-content border rounded p-3 bg-light">
                                                <!-- PANEL 1: SUBIR ARCHIVO -->
                                                <div class="tab-pane fade show active" id="diag-upload-pane">
                                                    <div class="form-group mb-2">
                                                        <label class="font-weight-bold small text-navy">Seleccionar Diagrama de Red (Visio .vsdx, .vdx, SVG, PNG, JPG, PDF)</label>
                                                        <div class="custom-file">
                                                            <input type="file" class="custom-file-input" id="pm_diagram_file_input" accept=".vsd,.vsdx,.vdx,.svg,.png,.jpg,.jpeg,.pdf" onchange="uploadPmDiagramFile(event)">
                                                            <label class="custom-file-label font-weight-bold text-muted" for="pm_diagram_file_input">Buscar diagrama en el equipo local...</label>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- PANEL 2: SELECCIONAR VISIO EXISTENTE -->
                                                <div class="tab-pane fade" id="diag-visio-pane">
                                                    <div class="form-group mb-2">
                                                        <label class="font-weight-bold small text-navy">Modelos de Diagramas Visio Registrados en CMDB</label>
                                                        <select id="select_visio_model" class="custom-select font-weight-bold text-navy" onchange="selectVisioModelForPm(this.value)">
                                                            <option value="">Cargando modelos Visio...</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- OPCION DE INCLUIR EN PDF -->
                                            <div class="custom-control custom-checkbox mt-3">
                                                <input type="checkbox" class="custom-control-input" id="cfg_inc_diagram" checked onchange="toggleIncludeDiagramInPdf(this.checked)">
                                                <label class="custom-control-label font-weight-bold text-navy" for="cfg_inc_diagram">
                                                    <i class="fas fa-file-pdf text-danger mr-1"></i> Incluir este Diagrama en el Informe Final PDF del Equipo
                                                </label>
                                            </div>

                                            <!-- CONTENEDOR DE PREVISUALIZACIÓN DE DIAGRAMA -->
                                            <div class="mt-4" id="pm_diagram_preview_container">
                                                <div class="alert alert-light border text-center py-4 text-muted" style="border-radius:8px;">
                                                    <i class="fas fa-sitemap fa-2x mb-2 d-block text-secondary"></i>
                                                    No se ha adjuntado ni vinculado ningún diagrama aún.
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between mt-4">
                                        <button type="button" class="btn btn-secondary font-weight-bold" onclick="goToPmSurveyStep(5)"><i class="fas fa-arrow-left mr-1"></i> Atrás: Archivos de Configuración (Paso 5)</button>
                                        <button type="button" class="btn btn-primary px-4 shadow-sm font-weight-bold" onclick="goToPmSurveyStep(7)">Siguiente: Informe y PDF (Paso 7) <i class="fas fa-arrow-right ml-1"></i></button>
                                    </div>
                                </div>

                                 <!-- PASO 7: CONFIGURACIÓN DE INFORME, PREVISUALIZACIÓN Y IMPRESIÓN/PDF -->
                                <div class="pm-survey-step" id="pm_step7" style="display: none;">
                                    <div class="callout callout-info py-2 px-3 mb-4" style="border-left-width: 4px;">
                                        <h6 class="font-weight-bold mb-1 text-navy"><i class="fas fa-file-pdf mr-2 text-danger"></i> Paso 7: Configuración del Informe Técnico, Previsualización y PDF</h6>
                                        <small class="text-muted">Personalice la plantilla del reporte (Título, Subtítulo, Logo corporativo) y previsualice el informe ejecutivo listo para impresión/exportación o guardado.</small>
                                    </div>

                                    <div id="pm-survey-step5-preview">
                                        <!-- Renderizado por renderPmStep5ReportAndPreview() -->
                                    </div>

                                    <div class="d-flex justify-content-between mt-4">
                                        <button type="button" class="btn btn-secondary font-weight-bold" onclick="goToPmSurveyStep(6)"><i class="fas fa-arrow-left mr-1"></i> Atrás: Diagrama (Paso 6)</button>
                                        <div class="d-flex" style="gap:8px;">
                                            <button type="button" class="btn btn-outline-danger font-weight-bold px-3 shadow-2xs" onclick="triggerPmExecutivePrint()">
                                                <i class="fas fa-file-pdf mr-1"></i> Exportar / Imprimir PDF
                                            </button>
                                            <button type="button" class="btn btn-outline-success font-weight-bold px-3 shadow-2xs" onclick="exportPmSurveyExcel()">
                                                <i class="fas fa-file-excel mr-1"></i> Exportar Excel
                                            </button>
                                            <button type="button" class="btn btn-success px-4 shadow-sm font-weight-bold" onclick="savePmSurvey()">
                                                <i class="fas fa-save mr-1"></i> Guardar Levantamiento
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- PESTAÑA 3: CONFIGURACIÓN DEL INFORME (PDF & CARÁTULA) -->
                <div class="tab-pane fade" id="report-config-content" role="tabpanel" style="display: none;">
                    <div class="card border shadow-sm" style="border-radius: 12px; overflow: hidden;">
                        <div class="card-header bg-gradient-navy text-white d-flex justify-content-between align-items-center">
                            <h5 class="card-title font-weight-bold m-0 text-white">
                                <i class="fas fa-file-pdf text-danger mr-2"></i> 03 Configuración General para la Creación del Informe Ejecutivo PDF
                            </h5>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-light font-weight-bold mr-2" onclick="resetReportConfigDefaults()">
                                    <i class="fas fa-undo mr-1"></i> Restablecer Valores
                                </button>
                                <button type="button" class="btn btn-sm btn-success font-weight-bold px-3 shadow-sm" onclick="saveReportConfigSettings(true)">
                                    <i class="fas fa-save mr-1"></i> Guardar Configuración
                                </button>
                            </div>
                        </div>

                        <div class="card-body p-4 bg-light">
                            <div class="callout callout-info bg-white border shadow-2xs py-3 px-4 mb-4" style="border-left: 4px solid #002B49; border-radius: 8px;">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <h6 class="font-weight-bold text-navy mb-1"><i class="fas fa-cog text-primary mr-2"></i> Ajustes de Generación de Informes y Carátula Corporativa</h6>
                                        <small class="text-muted">Personalice los títulos, logotipos (SONDA, SynapseCMDB, FEMSA/Cliente o imágenes PNG personalizadas), secciones y tema visual que se aplicarán al exportar informes en formato PDF.</small>
                                    </div>
                                    <button type="button" class="btn btn-primary font-weight-bold shadow-sm px-3" onclick="previewConfiguredPDF()">
                                        <i class="fas fa-eye mr-1"></i> Probar Generación PDF
                                    </button>
                                </div>
                            </div>

                            <div class="row">
                                <!-- COLUMNA IZQUIERDA: FORMULARIOS DE CONFIGURACIÓN -->
                                <div class="col-lg-7 mb-4">
                                    
                                    <!-- BLOQUE 1: DATOS Y METADATOS DE LA CARÁTULA -->
                                    <div class="card border shadow-2xs mb-4" style="border-radius: 10px;">
                                        <div class="card-header bg-white py-2 font-weight-bold text-navy">
                                            <i class="fas fa-heading text-info mr-2"></i> 1. Información y Texto de la Carátula / Portada
                                        </div>
                                        <div class="card-body p-3 bg-white">
                                            <div class="form-row mb-2">
                                                <div class="col-md-12 mb-2">
                                                    <label class="font-weight-bold small text-dark mb-1">Título Principal del Informe *</label>
                                                    <input type="text" id="cfg_report_title" class="form-control font-weight-bold" oninput="updateReportConfigLivePreview()">
                                                </div>
                                                <div class="col-md-12 mb-2">
                                                    <label class="font-weight-bold small text-dark mb-1">Subtítulo / Descripción del Proyecto *</label>
                                                    <input type="text" id="cfg_report_subtitle" class="form-control" oninput="updateReportConfigLivePreview()">
                                                </div>
                                            </div>
                                            <div class="form-row mb-2">
                                                <div class="col-md-6 mb-2">
                                                    <label class="font-weight-bold small text-dark mb-1">Empresa / Cliente por Defecto</label>
                                                    <input type="text" id="cfg_report_client" class="form-control" oninput="updateReportConfigLivePreview()">
                                                </div>
                                                <div class="col-md-6 mb-2">
                                                    <label class="font-weight-bold small text-dark mb-1">Autor / Responsable Técnico</label>
                                                    <input type="text" id="cfg_report_author" class="form-control" oninput="updateReportConfigLivePreview()">
                                                </div>
                                            </div>
                                            <div class="form-row">
                                                <div class="col-md-6 mb-2">
                                                    <label class="font-weight-bold small text-dark mb-1">Área / Departamento</label>
                                                    <input type="text" id="cfg_report_department" class="form-control" oninput="updateReportConfigLivePreview()">
                                                </div>
                                                <div class="col-md-6 mb-2">
                                                    <label class="font-weight-bold small text-dark mb-1">Versión del Documento</label>
                                                    <input type="text" id="cfg_report_version" class="form-control" oninput="updateReportConfigLivePreview()">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- BLOQUE 2: SELECCIÓN DE 3 LOGOTIPOS / IMÁGENES PNG -->
                                    <div class="card border shadow-2xs mb-4" style="border-radius: 10px;">
                                        <div class="card-header bg-white py-2 font-weight-bold text-navy d-flex justify-content-between align-items-center">
                                            <span><i class="fas fa-images text-primary mr-2"></i> 2. Configuración de Logotipos / Imágenes (3 Posiciones)</span>
                                            <span class="badge badge-primary">Formato PNG / SVG</span>
                                        </div>
                                        <div class="card-body p-3 bg-white">
                                            <p class="small text-muted mb-3">
                                                Seleccione las 3 imágenes o logotipos corporativos que aparecerán en la carátula y encabezados (SONDA, SynapseCMDB, FEMSA, Cliente o cargue una imagen PNG personalizada):
                                            </p>

                                            <div class="row">
                                                <!-- POSICIÓN 1: LOGO IZQUIERDO -->
                                                <!-- POSICIÓN 1: LOGO IZQUIERDO -->
                                                <div class="col-md-4 mb-3">
                                                    <div class="border rounded p-2 text-center bg-light shadow-2xs position-relative" style="min-height: 200px;">
                                                        <span class="badge badge-navy text-white d-block mb-2 font-weight-bold">Logo 1: Izquierdo / Principal</span>
                                                        <div id="preview_logo1_box" class="my-2 d-flex align-items-center justify-content-center border rounded bg-white p-1" style="height: 65px; cursor: pointer;" title="Haz clic para cambiar o subir imagen al servidor" onclick="triggerLogoSlotUpload(1)">
                                                            <!-- JS Live Preview -->
                                                        </div>
                                                        <button type="button" class="btn btn-xs btn-outline-primary font-weight-bold w-100 mb-2 shadow-2xs" onclick="triggerLogoSlotUpload(1)">
                                                            <i class="fas fa-cloud-upload-alt mr-1"></i> Clic para Subir Imagen (Logo 1)
                                                        </button>
                                                        <select id="cfg_logo1_type" class="custom-select custom-select-sm font-weight-bold mb-1" onchange="handleLogoChange(1)">
                                                            <option value="sonda">SONDA (Oficial)</option>
                                                            <option value="synapse">Synapse CMDB</option>
                                                            <option value="femsa">FEMSA</option>
                                                            <option value="client">Cliente</option>
                                                            <option value="custom">Imagen Personalizada (Servidor)</option>
                                                        </select>
                                                        <div id="cfg_logo1_file_wrapper" style="display:none;">
                                                            <input type="file" id="cfg_logo1_file" accept="image/png,image/jpeg,image/svg+xml,image/webp" class="form-control-file form-control-sm mt-1" onchange="handleCustomLogoUpload(1, event)">
                                                            <small id="cfg_logo1_status" class="form-text mt-1 font-weight-bold" style="font-size:10.5px;"></small>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- POSICIÓN 2: LOGO CENTRAL -->
                                                <div class="col-md-4 mb-3">
                                                    <div class="border rounded p-2 text-center bg-light shadow-2xs position-relative" style="min-height: 200px;">
                                                        <span class="badge badge-info text-white d-block mb-2 font-weight-bold">Logo 2: Centro / Sistema</span>
                                                        <div id="preview_logo2_box" class="my-2 d-flex align-items-center justify-content-center border rounded bg-white p-1" style="height: 65px; cursor: pointer;" title="Haz clic para cambiar o subir imagen al servidor" onclick="triggerLogoSlotUpload(2)">
                                                            <!-- JS Live Preview -->
                                                        </div>
                                                        <button type="button" class="btn btn-xs btn-outline-info font-weight-bold w-100 mb-2 shadow-2xs" onclick="triggerLogoSlotUpload(2)">
                                                            <i class="fas fa-cloud-upload-alt mr-1"></i> Clic para Subir Imagen (Logo 2)
                                                        </button>
                                                        <select id="cfg_logo2_type" class="custom-select custom-select-sm font-weight-bold mb-1" onchange="handleLogoChange(2)">
                                                            <option value="synapse">Synapse CMDB</option>
                                                            <option value="sonda">SONDA (Oficial)</option>
                                                            <option value="femsa">FEMSA</option>
                                                            <option value="client">Cliente</option>
                                                            <option value="custom">Imagen Personalizada (Servidor)</option>
                                                        </select>
                                                        <div id="cfg_logo2_file_wrapper" style="display:none;">
                                                            <input type="file" id="cfg_logo2_file" accept="image/png,image/jpeg,image/svg+xml,image/webp" class="form-control-file form-control-sm mt-1" onchange="handleCustomLogoUpload(2, event)">
                                                            <small id="cfg_logo2_status" class="form-text mt-1 font-weight-bold" style="font-size:10.5px;"></small>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- POSICIÓN 3: LOGO DERECHO -->
                                                <div class="col-md-4 mb-3">
                                                    <div class="border rounded p-2 text-center bg-light shadow-2xs position-relative" style="min-height: 200px;">
                                                        <span class="badge badge-warning text-white d-block mb-2 font-weight-bold" style="background:#D9272E !important;">Logo 3: Derecho / Cliente</span>
                                                        <div id="preview_logo3_box" class="my-2 d-flex align-items-center justify-content-center border rounded bg-white p-1" style="height: 65px; cursor: pointer;" title="Haz clic para cambiar o subir imagen al servidor" onclick="triggerLogoSlotUpload(3)">
                                                            <!-- JS Live Preview -->
                                                        </div>
                                                        <button type="button" class="btn btn-xs btn-outline-warning font-weight-bold w-100 mb-2 shadow-2xs" style="color:#D9272E; border-color:#D9272E;" onclick="triggerLogoSlotUpload(3)">
                                                            <i class="fas fa-cloud-upload-alt mr-1"></i> Clic para Subir Imagen (Logo 3)
                                                        </button>
                                                        <select id="cfg_logo3_type" class="custom-select custom-select-sm font-weight-bold mb-1" onchange="handleLogoChange(3)">
                                                            <option value="femsa">FEMSA</option>
                                                            <option value="client">Cliente</option>
                                                            <option value="sonda">SONDA (Oficial)</option>
                                                            <option value="synapse">Synapse CMDB</option>
                                                            <option value="custom">Imagen Personalizada (Servidor)</option>
                                                        </select>
                                                        <div id="cfg_logo3_file_wrapper" style="display:none;">
                                                            <input type="file" id="cfg_logo3_file" accept="image/png,image/jpeg,image/svg+xml,image/webp" class="form-control-file form-control-sm mt-1" onchange="handleCustomLogoUpload(3, event)">
                                                            <small id="cfg_logo3_status" class="form-text mt-1 font-weight-bold" style="font-size:10.5px;"></small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row pt-2 border-top">
                                                <div class="col-md-4">
                                                    <div class="custom-control custom-checkbox">
                                                        <input type="checkbox" class="custom-control-input" id="cfg_show_logo_cover" checked onchange="updateReportConfigLivePreview()">
                                                        <label class="custom-control-label small font-weight-bold text-dark" for="cfg_show_logo_cover">Mostrar en Carátula</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="custom-control custom-checkbox">
                                                        <input type="checkbox" class="custom-control-input" id="cfg_show_logo_header" checked onchange="updateReportConfigLivePreview()">
                                                        <label class="custom-control-label small font-weight-bold text-dark" for="cfg_show_logo_header">Mostrar en Encabezados</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="custom-control custom-checkbox">
                                                        <input type="checkbox" class="custom-control-input" id="cfg_show_logo_footer" checked onchange="updateReportConfigLivePreview()">
                                                        <label class="custom-control-label small font-weight-bold text-dark" for="cfg_show_logo_footer">Mostrar en Pie de Firma</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- BLOQUE 3: SECCIONES A INCLUIR EN EL INFORME PDF -->
                                    <div class="card border shadow-2xs mb-4" style="border-radius: 10px;">
                                        <div class="card-header bg-white py-2 font-weight-bold text-navy">
                                            <i class="fas fa-tasks text-success mr-2"></i> 3. Secciones y Contenidos a Incluir en el PDF
                                        </div>
                                        <div class="card-body p-3 bg-white">
                                            <div class="row">
                                                <div class="col-md-6 mb-2">
                                                    <div class="custom-control custom-switch">
                                                        <input type="checkbox" class="custom-control-input" id="cfg_inc_cover" checked onchange="updateReportConfigLivePreview()">
                                                        <label class="custom-control-label font-weight-bold small text-navy" for="cfg_inc_cover">1. Carátula Ejecutiva con Logotipos</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-6 mb-2">
                                                    <div class="custom-control custom-switch">
                                                        <input type="checkbox" class="custom-control-input" id="cfg_inc_toc" checked onchange="updateReportConfigLivePreview()">
                                                        <label class="custom-control-label font-weight-bold small text-navy" for="cfg_inc_toc">2. Tabla de Índice de Contenido</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-6 mb-2">
                                                    <div class="custom-control custom-switch">
                                                        <input type="checkbox" class="custom-control-input" id="cfg_inc_metadata" checked onchange="updateReportConfigLivePreview()">
                                                        <label class="custom-control-label font-weight-bold small text-navy" for="cfg_inc_metadata">3. Ficha Técnica y Metadatos (Campos 00-13)</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-6 mb-2">
                                                    <div class="custom-control custom-switch">
                                                        <input type="checkbox" class="custom-control-input" id="cfg_inc_matrix" checked onchange="updateReportConfigLivePreview()">
                                                        <label class="custom-control-label font-weight-bold small text-navy" for="cfg_inc_matrix">4. Matriz Completa de Puertos (1 - N)</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-6 mb-2">
                                                    <div class="custom-control custom-switch">
                                                        <input type="checkbox" class="custom-control-input" id="cfg_inc_topology" checked onchange="updateReportConfigLivePreview()">
                                                        <label class="custom-control-label font-weight-bold small text-navy" for="cfg_inc_topology">5. Diagrama de Nodos y Faceplate Gráfico</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-6 mb-2">
                                                    <div class="custom-control custom-switch">
                                                        <input type="checkbox" class="custom-control-input" id="cfg_inc_photos" checked onchange="updateReportConfigLivePreview()">
                                                        <label class="custom-control-label font-weight-bold small text-navy" for="cfg_inc_photos">6. Galería Evidencia Fotográfica</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-6 mb-2">
                                                    <div class="custom-control custom-switch">
                                                        <input type="checkbox" class="custom-control-input" id="cfg_inc_signatures" checked onchange="updateReportConfigLivePreview()">
                                                        <label class="custom-control-label font-weight-bold small text-navy" for="cfg_inc_signatures">7. Observaciones y Firmas de Conformidad</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- BLOQUE 4: ESTILO Y FORMATO VISUAL -->
                                    <div class="card border shadow-2xs" style="border-radius: 10px;">
                                        <div class="card-header bg-white py-2 font-weight-bold text-navy">
                                            <i class="fas fa-palette text-warning mr-2"></i> 4. Estilo, Colores y Formato de Impresión
                                        </div>
                                        <div class="card-body p-3 bg-white">
                                            <div class="form-row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="font-weight-bold small text-dark mb-1">Paleta de Colores del Tema PDF</label>
                                                    <select id="cfg_theme_color" class="custom-select custom-select-sm font-weight-bold" onchange="updateReportConfigLivePreview()">
                                                        <option value="#002B49">Azul Corporativo SONDA (#002B49)</option>
                                                        <option value="#D9272E">Rojo Corporativo FEMSA (#D9272E)</option>
                                                        <option value="#0284c7">Azul Tech Synapse (#0284c7)</option>
                                                        <option value="#0f172a">Ejecutivo Dark Slate (#0f172a)</option>
                                                        <option value="#059669">Verde Operaciones TI (#059669)</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="font-weight-bold small text-dark mb-1">Orientación de Páginas</label>
                                                    <select id="cfg_page_orientation" class="custom-select custom-select-sm font-weight-bold" onchange="updateReportConfigLivePreview()">
                                                        <option value="landscape">Horizontal (Landscape - Recomendado para Matrices)</option>
                                                        <option value="portrait">Vertical (Portrait)</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-12">
                                                    <label class="font-weight-bold small text-dark mb-1">Leyenda Pie de Página (Confidencialidad)</label>
                                                    <input type="text" id="cfg_confidential_notice" class="form-control form-control-sm" oninput="updateReportConfigLivePreview()">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                </div>

                                <!-- COLUMNA DERECHA: MAQUETA / PREVISUALIZACIÓN INTERACTIVA DE LA CARÁTULA EN TIEMPO REAL -->
                                <div class="col-lg-5 mb-4">
                                    <div class="sticky-top" style="top: 20px; z-index: 10;">
                                        <div class="card border shadow" style="border-radius: 12px; overflow: hidden; border: 2px solid #002B49 !important;">
                                            <div class="card-header bg-navy text-white d-flex justify-content-between align-items-center py-2">
                                                <span class="font-weight-bold" style="font-size: 13px;">
                                                    <i class="fas fa-desktop mr-1"></i> Vista Previa en Vivo de la Carátula (Portada PDF)
                                                </span>
                                                <span class="badge badge-light text-navy font-weight-bold" id="cfg_preview_page_count">7 Páginas</span>
                                            </div>

                                            <div class="card-body p-4 bg-white" id="cfg_live_cover_preview_box" style="min-height: 480px; box-sizing: border-box; position: relative;">
                                                <!-- Live Cover Mockup HTML rendered by JS -->
                                            </div>

                                            <div class="card-footer bg-light d-flex justify-content-between align-items-center py-2">
                                                <small class="text-muted"><i class="fas fa-sync-alt fa-spin text-primary mr-1"></i> Actualización automática</small>
                                                <button type="button" class="btn btn-sm btn-primary font-weight-bold shadow-sm" onclick="saveReportConfigSettings(true)">
                                                    <i class="fas fa-save mr-1"></i> Guardar y Aplicar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                <!-- PESTAÑA 4: ANÁLISIS VILASECA (Métricas, Tops, Racks y Distribución) -->
                <div class="tab-pane fade" id="survey-analysis-content" role="tabpanel" style="display: none;">
                    
                    <!-- BANNER EJECUTIVO DE CABECERA -->
                    <div class="analysis-header-card p-4 mb-4">
                        <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap: 15px;">
                            <div>
                                <div class="d-flex align-items-center flex-wrap" style="gap: 10px;">
                                    <h4 class="font-weight-bold m-0 text-white">
                                        <i class="fas fa-chart-pie text-warning mr-2"></i> Centro de Análisis de Infraestructura - VILASECA
                                    </h4>
                                    <span class="badge badge-warning text-dark font-weight-bold px-2 py-1">
                                        <i class="fas fa-building mr-1"></i> CLIENTE: VILASECA
                                    </span>
                                    <span class="badge badge-info px-2 py-1" id="an_badge_status">
                                        <i class="fas fa-check-circle mr-1"></i> Datos Auditados en Vivo
                                    </span>
                                </div>
                                <p class="text-white-50 small mb-0 mt-1">
                                    Resumen cuantitativo de activos creados, análisis de ocupación física de puertos, densidades por bastidor y distribución por localidades.
                                </p>
                            </div>
                            <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
                                <a href="<?php echo PUBLIC_URL_PREFIX; ?>/clientes/vilaseca/index.php" class="btn btn-outline-light btn-sm font-weight-bold">
                                    <i class="fas fa-tachometer-alt mr-1"></i> Centro de Control
                                </a>
                                <a href="<?php echo PUBLIC_URL_PREFIX; ?>/analisis_conexiones.php?cliente=VILASECA" class="btn btn-outline-light btn-sm font-weight-bold">
                                    <i class="fas fa-project-diagram mr-1"></i> Topología
                                </a>
                                <button type="button" class="btn btn-light btn-sm text-navy font-weight-bold shadow-sm" onclick="exportVilasecaAnalysisCSV()">
                                    <i class="fas fa-file-excel mr-1 text-success"></i> Exportar Informe CSV
                                </button>
                                <button type="button" class="btn btn-outline-light btn-sm font-weight-bold" onclick="loadSurveyAnalysisTab(true)">
                                    <i class="fas fa-sync-alt mr-1"></i> Actualizar Métricas
                                </button>
                                <button type="button" class="btn btn-success btn-sm font-weight-bold shadow-sm" onclick="switchToNewPmSurveyTab()">
                                    <i class="fas fa-plus mr-1"></i> Nuevo Levantamiento
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- BARRA DE FILTROS EN TIEMPO REAL -->
                    <div class="card border shadow-xs mb-4" style="border-radius: 10px;">
                        <div class="card-body p-3 bg-light">
                            <div class="row align-items-center">
                                <div class="col-md-3 mb-2 mb-md-0">
                                    <label class="small font-weight-bold text-navy mb-1"><i class="fas fa-map-marker-alt text-danger mr-1"></i> Filtrar por Sede / Localidad:</label>
                                    <select id="an_filter_location" class="custom-select custom-select-sm font-weight-bold" onchange="applyAnalysisFilters()">
                                        <option value="all">Todas las Localidades</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-2 mb-md-0">
                                    <label class="small font-weight-bold text-navy mb-1"><i class="fas fa-layer-group text-primary mr-1"></i> Filtrar por Tipo de Equipo:</label>
                                    <select id="an_filter_type" class="custom-select custom-select-sm font-weight-bold" onchange="applyAnalysisFilters()">
                                        <option value="all">Todos los Tipos</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-2 mb-md-0">
                                    <label class="small font-weight-bold text-navy mb-1"><i class="fas fa-search text-secondary mr-1"></i> Búsqueda Rápida en Análisis:</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" id="an_filter_search" class="form-control" placeholder="Buscar por equipo, rack, IP..." oninput="applyAnalysisFilters()">
                                        <div class="input-group-append">
                                            <button class="btn btn-outline-secondary" type="button" onclick="clearAnalysisSearch()"><i class="fas fa-times"></i></button>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2 text-right pt-md-3">
                                    <button class="btn btn-outline-navy btn-sm btn-block font-weight-bold" onclick="resetAnalysisFilters()">
                                        <i class="fas fa-undo mr-1"></i> Restablecer
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 5 TARJETAS KPI DE IMPACTO -->
                    <div class="row mb-4">
                        <div class="col-xl col-md-4 col-sm-6 mb-3">
                            <div class="stat-kpi-card kpi-blue h-100">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="stat-kpi-label">Total de Equipos</div>
                                    <i class="fas fa-cubes text-primary" style="font-size: 1.4rem; opacity: 0.85;"></i>
                                </div>
                                <div class="stat-kpi-num" id="an_kpi_total_devices">0</div>
                                <div class="small text-muted font-weight-bold mt-1" id="an_kpi_devices_breakdown">Activos y Pasivos</div>
                            </div>
                        </div>
                        <div class="col-xl col-md-4 col-sm-6 mb-3">
                            <div class="stat-kpi-card kpi-orange h-100">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="stat-kpi-label">Localidades / Sedes</div>
                                    <i class="fas fa-building text-warning" style="font-size: 1.4rem; opacity: 0.85;"></i>
                                </div>
                                <div class="stat-kpi-num" id="an_kpi_total_locations">0</div>
                                <div class="small text-muted font-weight-bold mt-1" id="an_kpi_locations_breakdown">Sedes registradas</div>
                            </div>
                        </div>
                        <div class="col-xl col-md-4 col-sm-6 mb-3">
                            <div class="stat-kpi-card kpi-purple h-100">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="stat-kpi-label">Racks / Bastidores</div>
                                    <i class="fas fa-server text-purple" style="font-size: 1.4rem; opacity: 0.85; color: #6f42c1;"></i>
                                </div>
                                <div class="stat-kpi-num" id="an_kpi_total_racks">0</div>
                                <div class="small text-muted font-weight-bold mt-1" id="an_kpi_racks_breakdown">En producción</div>
                            </div>
                        </div>
                        <div class="col-xl col-md-6 col-sm-6 mb-3">
                            <div class="stat-kpi-card kpi-cyan h-100">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="stat-kpi-label">Total de Puertos</div>
                                    <i class="fas fa-ethernet text-info" style="font-size: 1.4rem; opacity: 0.85;"></i>
                                </div>
                                <div class="stat-kpi-num" id="an_kpi_total_ports">0</div>
                                <div class="small font-weight-bold text-muted mt-1" id="an_kpi_ports_breakdown">0 Conectados | 0 Libres</div>
                            </div>
                        </div>
                        <div class="col-xl col-md-6 col-sm-12 mb-3">
                            <div class="stat-kpi-card kpi-green h-100">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="stat-kpi-label">Ocupación Global</div>
                                    <i class="fas fa-chart-line text-success" style="font-size: 1.4rem; opacity: 0.85;"></i>
                                </div>
                                <div class="stat-kpi-num text-success" id="an_kpi_occupancy_pct">0%</div>
                                <div class="progress mt-2" style="height: 6px; border-radius: 4px; background: #e2e8f0;">
                                    <div class="progress-bar bg-success" id="an_kpi_occupancy_bar" role="progressbar" style="width: 0%;"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- FILA 1: DISTRIBUCIÓN POR TIPO Y POR LOCALIDAD (GRÁFICOS) -->
                    <div class="row mb-4">
                        <!-- GRÁFICO 1: TOTAL DE EQUIPOS DIVIDIDO POR TIPO -->
                        <div class="col-lg-6 mb-4">
                            <div class="card border shadow-xs h-100" style="border-radius: 12px; overflow: hidden;">
                                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                                    <h6 class="font-weight-bold m-0 text-navy">
                                        <i class="fas fa-layer-group text-primary mr-2"></i> Total de Equipos Dividido por Tipo
                                    </h6>
                                    <span class="badge badge-primary px-2 py-1" id="an_badge_types_count">0 tipos</span>
                                </div>
                                <div class="card-body p-3">
                                    <div class="row align-items-center">
                                        <div class="col-sm-6 text-center mb-3 mb-sm-0" style="min-height: 220px; position: relative;">
                                            <canvas id="chart_an_types" height="220"></canvas>
                                        </div>
                                        <div class="col-sm-6">
                                            <div id="an_types_list_container" class="table-responsive" style="max-height: 230px; overflow-y: auto;">
                                                <!-- Lista interactiva de tipos de equipos -->
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- GRÁFICO 2: CANTIDAD DE EQUIPOS Y PUERTOS POR LOCALIDAD -->
                        <div class="col-lg-6 mb-4">
                            <div class="card border shadow-xs h-100" style="border-radius: 12px; overflow: hidden;">
                                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                                    <h6 class="font-weight-bold m-0 text-navy">
                                        <i class="fas fa-map-marked-alt text-danger mr-2"></i> Cantidad de Equipos por Localidad
                                    </h6>
                                    <span class="badge badge-secondary px-2 py-1" id="an_badge_locations_count">0 sedes</span>
                                </div>
                                <div class="card-body p-3">
                                    <div style="min-height: 220px; position: relative;">
                                        <canvas id="chart_an_locations" height="220"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- FILA 2: TOPS DE EQUIPOS (PUERTOS VACÍOS VS LLENOS) -->
                    <div class="row mb-4">
                        <!-- TOP DE EQUIPOS CON PUERTOS VACÍOS (MAYOR DISPONIBILIDAD) -->
                        <div class="col-lg-6 mb-4">
                            <div class="card border shadow-xs h-100" style="border-radius: 12px; overflow: hidden;">
                                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center" style="border-bottom: 2px solid #28a745;">
                                    <div>
                                        <h6 class="font-weight-bold m-0 text-success">
                                            <i class="fas fa-battery-empty mr-2"></i> Top de Equipos con Puertos Vacíos
                                        </h6>
                                        <small class="text-muted">Mayor disponibilidad de puertos para nuevas conexiones</small>
                                    </div>
                                    <span class="badge badge-success px-2 py-1 font-weight-bold">Mayor Disponibilidad</span>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                                        <table class="table table-hover table-sm align-middle m-0" id="tbl_an_top_vacant">
                                            <thead class="bg-light text-navy" style="font-size: 11px; text-transform: uppercase;">
                                                <tr>
                                                    <th class="text-center" style="width: 45px;">#</th>
                                                    <th>Equipo / Hostname</th>
                                                    <th>Sede / Rack</th>
                                                    <th class="text-center" style="width: 80px;">Puertos</th>
                                                    <th class="text-center" style="width: 100px;">Disponibles</th>
                                                    <th class="text-center" style="width: 70px;">Acción</th>
                                                </tr>
                                            </thead>
                                            <tbody id="tbl_an_top_vacant_body" style="font-size: 12px;">
                                                <tr><td colspan="6" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin mr-2"></i> Analizando equipos...</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TOP DE EQUIPOS LLENOS (MAYOR OCUPACIÓN / SATURACIÓN) -->
                        <div class="col-lg-6 mb-4">
                            <div class="card border shadow-xs h-100" style="border-radius: 12px; overflow: hidden;">
                                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center" style="border-bottom: 2px solid #0056b3;">
                                    <div>
                                        <h6 class="font-weight-bold m-0 text-navy">
                                            <i class="fas fa-battery-full text-primary mr-2"></i> Top de Equipos Llenos / Mayor Ocupación
                                        </h6>
                                        <small class="text-muted">Mayor saturación de puertos utilizados o en estado Up</small>
                                    </div>
                                    <span class="badge badge-primary px-2 py-1 font-weight-bold">Mayor Ocupación</span>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                                        <table class="table table-hover table-sm align-middle m-0" id="tbl_an_top_occupied">
                                            <thead class="bg-light text-navy" style="font-size: 11px; text-transform: uppercase;">
                                                <tr>
                                                    <th class="text-center" style="width: 45px;">#</th>
                                                    <th>Equipo / Hostname</th>
                                                    <th>Sede / Rack</th>
                                                    <th class="text-center" style="width: 80px;">Puertos</th>
                                                    <th class="text-center" style="width: 100px;">Ocupados</th>
                                                    <th class="text-center" style="width: 70px;">Acción</th>
                                                </tr>
                                            </thead>
                                            <tbody id="tbl_an_top_occupied_body" style="font-size: 12px;">
                                                <tr><td colspan="6" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin mr-2"></i> Analizando equipos...</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- FILA 3: RACKS CON MAYOR CANTIDAD DE EQUIPOS POR LOCALIDAD -->
                    <div class="card border shadow-xs mb-4" style="border-radius: 12px; overflow: hidden;">
                        <div class="card-header bg-light py-3 d-flex justify-content-between align-items-center flex-wrap" style="gap: 10px;">
                            <div>
                                <h6 class="font-weight-bold m-0 text-navy">
                                    <i class="fas fa-server text-warning mr-2"></i> Racks con Mayor Cantidad de Equipos por Localidad
                                </h6>
                                <small class="text-muted">Concentración de activos y distribución de bastidores ordenados por densidad en cada sede</small>
                            </div>
                            <!-- Botones de píldora para filtrar localidad de racks -->
                            <div class="d-flex align-items-center flex-wrap" id="an_rack_loc_pills_container" style="gap: 6px;">
                                <!-- Píldoras inyectadas por JS -->
                            </div>
                        </div>
                        <div class="card-body p-3">
                            <div class="row" id="an_racks_grid_container">
                                <!-- Tarjetas de racks inyectadas por JS -->
                            </div>
                        </div>
                    </div>

                    <!-- FILA 4: INVENTARIO CONSOLIDADO Y ANÁLISIS DETALLADO DE EQUIPOS -->
                    <div class="card border shadow-xs mb-4" style="border-radius: 12px; overflow: hidden;">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap" style="gap: 10px;">
                            <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
                                <h6 class="font-weight-bold m-0 text-navy">
                                    <i class="fas fa-list-alt text-primary mr-2"></i> Inventario Detallado y Capacidad de Activos (Vilaseca)
                                </h6>
                                <span class="badge badge-navy px-2 py-1 font-weight-bold" id="an_inventory_count_badge">0 equipos</span>
                            </div>
                            <small class="text-muted">Haga clic en "Ver Mapeo" para acceder directamente a la matriz física de puertos en el Asistente</small>
                        </div>
                        <!-- BARRA DE HERRAMIENTAS: PAGINACIÓN Y BÚSQUEDA DEDICADA -->
                        <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center flex-wrap" style="gap: 12px;">
                            <div class="d-flex align-items-center" style="gap: 8px;">
                                <label class="small text-muted font-weight-bold mb-0">Mostrar:</label>
                                <select id="an_inv_page_size" class="custom-select custom-select-sm font-weight-bold" style="width: auto;" onchange="g_anCurrentPage = 1; renderAnalysisInventoryTable(g_anLastFilteredDevs || []);">
                                    <option value="10" selected>10 registros por página</option>
                                    <option value="25">25 registros por página</option>
                                    <option value="50">50 registros por página</option>
                                    <option value="-1">Todos los registros</option>
                                </select>
                            </div>
                            <div class="input-group input-group-sm" style="max-width: 320px;">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-white border-right-0"><i class="fas fa-search text-muted"></i></span>
                                </div>
                                <input type="text" id="an_inv_table_search" class="form-control border-left-0 font-weight-bold" placeholder="Buscar en la tabla..." oninput="onAnInventorySearchChange()">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('an_inv_table_search').value = ''; onAnInventorySearchChange();"><i class="fas fa-times"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped table-sm align-middle m-0" id="tbl_an_inventory">
                                    <thead class="bg-navy text-white" style="font-size: 11px; text-transform: uppercase;">
                                        <tr>
                                            <th class="text-center" style="width: 55px;">ID</th>
                                            <th>Equipo / Hostname</th>
                                            <th>Tipo</th>
                                            <th>Localidad / Sede</th>
                                            <th>Rack & UR</th>
                                            <th>IP</th>
                                            <th class="text-center" style="width: 75px;">Puertos</th>
                                            <th class="text-center" style="width: 75px;">Ocupados</th>
                                            <th class="text-center" style="width: 75px;">Vacíos</th>
                                            <th class="text-center" style="width: 130px;">Ocupación</th>
                                            <th class="text-center" style="width: 90px;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbl_an_inventory_body" style="font-size: 12px;">
                                        <tr><td colspan="11" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin mr-2"></i> Cargando inventario analítico...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <!-- PIE DE TABLA: INFORMACIÓN Y PAGINACIÓN -->
                        <div class="card-footer bg-white border-top py-2 px-3 d-flex justify-content-between align-items-center flex-wrap" style="gap: 10px;">
                            <div id="an_inv_table_info" class="small text-muted font-weight-bold">Mostrando registros...</div>
                            <div id="an_inv_pagination" class="d-flex align-items-center flex-wrap" style="gap: 4px;"></div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: SELECCIONAR EQUIPO DESDE ZABBIX -->
<div class="modal fade" id="modal_select_zabbix_host" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden; border:none;">
            <div class="modal-header bg-navy text-white py-3">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-bolt text-warning mr-2"></i> Seleccionar Equipo desde Zabbix</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="input-group mb-3 shadow-2xs">
                    <input type="text" id="zabbix_host_search_input" class="form-control font-weight-bold" placeholder="Buscar por Nombre, Hostname, IP o Grupo en Zabbix..." onkeyup="filterZabbixHostList()">
                    <div class="input-group-append">
                        <button class="btn btn-primary font-weight-bold" type="button" onclick="loadZabbixHostsList()"><i class="fas fa-sync-alt mr-1"></i> Recargar</button>
                    </div>
                </div>
                <div class="table-responsive bg-white rounded border shadow-2xs" style="max-height: 380px; overflow-y: auto;">
                    <table class="table table-hover table-bordered align-middle mb-0" style="font-size: 12px;">
                        <thead class="bg-navy text-white">
                            <tr>
                                <th>Host / Equipo Zabbix</th>
                                <th>IP / Interface</th>
                                <th>Grupo / Categoría</th>
                                <th>Estado</th>
                                <th class="text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="zabbix_hosts_table_body">
                            <tr><td colspan="5" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin mr-2"></i>Cargando equipos de Zabbix...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: VISUALIZAR ARCHIVO DE CONFIGURACIÓN -->
<div class="modal fade" id="modal_view_config_file" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header bg-navy text-white py-3">
                <h5 class="modal-title font-weight-bold" id="modal_cfg_file_title"><i class="fas fa-code text-warning mr-2"></i> Archivo de Configuración</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body p-0 bg-dark">
                <div style="max-height: 520px; overflow: auto; padding: 20px;">
                    <pre id="modal_cfg_file_content" class="text-light m-0" style="font-family: 'Courier New', monospace; font-size: 13px; line-height: 1.4; white-space: pre-wrap; word-break: break-all;"></pre>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-sm btn-outline-info font-weight-bold" onclick="copyConfigFileContent()"><i class="fas fa-copy mr-1"></i> Copiar Texto</button>
                <button type="button" class="btn btn-sm btn-secondary font-weight-bold" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: IMPORTAR PORTMAPPING & RACK DESDE ARCHIVO EXCEL                   -->
<!-- ========================================================================= -->
<div class="modal fade" id="modal_import_excel_portmapping" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 14px; overflow: hidden; border: none; box-shadow: 0 15px 35px rgba(16,27,49,0.25);">
            
            <!-- Cabecera Corporativa Sonda -->
            <div class="modal-header d-flex justify-content-between align-items-center text-white py-3 px-4" style="background: linear-gradient(135deg, #101B31 0%, #1e293b 100%); border-bottom: 3px solid var(--sonda-orange);">
                <div class="d-flex align-items-center" style="gap: 12px;">
                    <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(255,92,5,0.15); display: flex; align-items: center; justify-content: center; border: 1px solid rgba(255,92,5,0.3);">
                        <i class="fas fa-file-excel fa-lg" style="color: var(--sonda-orange);"></i>
                    </div>
                    <div>
                        <h5 class="modal-title font-weight-bold m-0 text-white" style="letter-spacing: 0.3px;">Importación Automatizada de Portmapping & Racks</h5>
                        <small class="text-white-50">Lectura de plantilla Excel, generación de Datacenter Rack, hardware y conexiones físicas</small>
                    </div>
                </div>
                <button type="button" class="close text-white opacity-75" data-dismiss="modal" aria-label="Cerrar" style="outline: none;">
                    <span aria-hidden="true" style="font-size: 1.5rem;">&times;</span>
                </button>
            </div>

            <!-- Cuerpo del Modal -->
            <div class="modal-body p-4 bg-light">
                
                <!-- PASO 1: ZONA DE CARGA DEL ARCHIVO -->
                <div id="pm_import_step_upload">
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card border-0 shadow-sm p-4 h-100 text-center" style="border-radius: 12px; background: #ffffff; border: 2px dashed #cbd5e1 !important; transition: all .2s;" id="pm_excel_dropzone">
                                <i class="fas fa-cloud-upload-alt fa-3x mb-3" style="color: var(--sonda-cyan);"></i>
                                <h6 class="font-weight-bold text-dark mb-1">Arrastre su archivo Excel (.xlsx) aquí o haga clic para seleccionar</h6>
                                <p class="text-muted small mb-3">Compatible con la plantilla oficial de relevamiento (Hojas: Portmapping de Puertos y Elevación de Rack)</p>
                                
                                <input type="file" id="pm_excel_file_input" accept=".xlsx, .xls" style="display: none;">
                                <div>
                                    <button type="button" class="btn btn-primary px-4 py-2 font-weight-bold shadow-sm" style="background: var(--sonda-orange); border-color: var(--sonda-orange); border-radius: 8px;" onclick="$('#pm_excel_file_input').click()">
                                        <i class="fas fa-folder-open mr-2"></i> Seleccionar Documento Excel
                                    </button>
                                </div>
                                <div id="pm_excel_selected_file_badge" class="mt-3" style="display: none;">
                                    <span class="badge badge-pill badge-light border px-3 py-2 text-dark font-weight-bold" style="font-size: 0.85rem;">
                                        <i class="fas fa-file-excel text-success mr-2"></i><span id="pm_excel_file_name">-</span>
                                        <i class="fas fa-times-circle text-danger ml-2 cursor-pointer" onclick="clearSelectedExcelFile()" title="Quitar archivo"></i>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4 mt-3 mt-lg-0">
                            <div class="card border shadow-sm p-3 h-100" style="border-radius: 12px; background: #ffffff;">
                                <h6 class="font-weight-bold text-navy mb-2" style="font-size: 0.88rem;">
                                    <i class="fas fa-info-circle text-info mr-1"></i> Reglas de Procesamiento
                                </h6>
                                <ul class="small text-secondary pl-3 mb-3" style="line-height: 1.6;">
                                    <li><strong>Agrupación:</strong> Cliente ➔ Sede ➔ Área ➔ Rack.</li>
                                    <li><strong>Carga Masiva (Bulk):</strong> Permite registrar múltiples equipos y switches con sus puertos en el mismo archivo.</li>
                                    <li><strong>Rack Automático:</strong> Crea o vincula el Rack en Datacenter con su elevación en U y observaciones.</li>
                                    <li><strong>Portmapping:</strong> Registra el levantamiento y mapeo físico de cada interfaz (UTP, RJ45, 1G, Patch Panels, etc.).</li>
                                    <li><strong>Cero Duplicados:</strong> Detecta si algún equipo o serie ya fue subido para evitar redundancia.</li>
                                </ul>

                                <div class="mt-auto pt-2 border-top">
                                    <a href="api_portmapping.php?action=download_excel_template" class="btn btn-outline-secondary btn-block btn-sm font-weight-bold text-dark" style="border-radius: 6px;">
                                        <i class="fas fa-file-excel mr-1 text-success"></i> Descargar Plantilla Oficial Bulk (.xlsx)
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botón para Iniciar Análisis -->
                    <div class="text-right mt-3">
                        <button type="button" class="btn btn-secondary px-3 mr-2 font-weight-bold" data-dismiss="modal">Cancelar</button>
                        <button type="button" id="btn_pm_analyze_excel" class="btn btn-info px-4 font-weight-bold shadow-sm" style="background: var(--sonda-cyan); color: #101B31; border: none; border-radius: 8px;" onclick="analyzePortmappingExcel()" disabled>
                            <i class="fas fa-search mr-1"></i> Analizar y Validar Documento
                        </button>
                    </div>
                </div>

                <!-- SPINNER DE CARGA Y ANÁLISIS -->
                <div id="pm_import_step_loading" class="text-center py-5" style="display: none;">
                    <div class="spinner-border text-primary mb-3" style="width: 3.5rem; height: 3.5rem; color: var(--sonda-orange) !important;" role="status"></div>
                    <h5 class="font-weight-bold text-navy" id="pm_import_loading_text">Analizando estructura y validando duplicados...</h5>
                    <p class="text-muted small">Leyendo hojas de portmapping, inventario de rack y puertos físicos...</p>
                </div>

                <!-- PASO 2: PREVISUALIZACIÓN, VALIDACIÓN Y CONFIRMACIÓN -->
                <div id="pm_import_step_preview" style="display: none;">
                    
                    <!-- BANNER Y SELECTOR MODO CARGA MASIVA (BULK IMPORT) -->
                    <div id="pm_bulk_selector_container" class="mb-3" style="display: none;">
                        <div class="card border-0 shadow-sm p-3" style="border-radius: 10px; background: linear-gradient(135deg, #101B31 0%, #1e3a8a 100%); color: #ffffff;">
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                                <div>
                                    <div class="d-flex align-items-center mb-1">
                                        <span class="badge badge-warning text-dark font-weight-bold px-2 py-1 mr-2" style="border-radius: 6px;">
                                            <i class="fas fa-bolt mr-1"></i> MODO CARGA MASIVA (BULK)
                                        </span>
                                        <span class="font-weight-bold" style="font-size: 0.95rem;">
                                            <span id="pm_bulk_total_devices">0</span> Equipos Detectados en el Excel
                                        </span>
                                    </div>
                                    <p class="small mb-2 mb-md-0" style="color: #cbd5e1;">
                                        El archivo contiene múltiples dispositivos. Puede alternar entre ellos para auditar sus puertos o importar todos de forma simultánea.
                                    </p>
                                </div>
                                <div class="d-flex align-items-center" style="gap: 8px;">
                                    <label class="small mb-0 text-white font-weight-bold text-nowrap">Auditar Equipo:</label>
                                    <select id="pm_bulk_device_selector" class="form-control form-control-sm font-weight-bold" style="border-radius: 6px; min-width: 260px; background: #ffffff; color: #101B31;" onchange="switchBulkPreviewDevice(this.value)">
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ALERT BOX (SI HAY ERRORES O ALERTAS) -->
                    <div id="pm_import_alert_box"></div>

                    <!-- TARJETAS RESUMEN DE LA IMPORTACIÓN -->
                    <div class="row mb-3" id="pm_preview_cards">
                        <!-- Tarjeta 1: Jerarquía y Ubicación -->
                        <div class="col-md-3">
                            <div class="card border shadow-sm p-3 h-100" style="border-radius: 10px; background: #fff; border-left: 4px solid var(--sonda-orange) !important;">
                                <small class="text-muted font-weight-bold text-uppercase d-block mb-1">Jerarquía Detectada</small>
                                <div class="font-weight-bold text-dark text-truncate" id="prev_client" title="Cliente">-</div>
                                <div class="small text-secondary mt-1"><i class="fas fa-map-marker-alt text-danger mr-1"></i><span id="prev_location">-</span></div>
                                <div class="small text-muted"><i class="fas fa-layer-group text-primary mr-1"></i>Área: <span id="prev_area" class="font-weight-bold">-</span></div>
                            </div>
                        </div>

                        <!-- Tarjeta 2: Rack en Datacenter -->
                        <div class="col-md-3">
                            <div class="card border shadow-sm p-3 h-100" style="border-radius: 10px; background: #fff; border-left: 4px solid var(--sonda-cyan) !important;">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <small class="text-muted font-weight-bold text-uppercase">Rack / Bastidor</small>
                                    <span id="prev_rack_badge" class="badge badge-light border font-weight-bold">-</span>
                                </div>
                                <div class="font-weight-bold text-dark" id="prev_rack_name">-</div>
                                <div class="small text-secondary mt-1"><i class="fas fa-ruler-vertical text-info mr-1"></i><span id="prev_rack_total_u">-</span> UR (<span id="prev_rack_type">-</span>)</div>
                                <div class="small text-muted"><i class="fas fa-server mr-1"></i><span id="prev_rack_dev_count">0</span> equipos en elevación</div>
                            </div>
                        </div>

                        <!-- Tarjeta 3: Equipo a Relevar -->
                        <div class="col-md-3">
                            <div class="card border shadow-sm p-3 h-100" style="border-radius: 10px; background: #fff; border-left: 4px solid var(--sonda-green) !important;">
                                <small class="text-muted font-weight-bold text-uppercase d-block mb-1">Equipo Relevado</small>
                                <div class="font-weight-bold text-dark text-truncate" id="prev_eq_name">-</div>
                                <div class="small text-secondary mt-1"><i class="fas fa-barcode text-muted mr-1"></i>Serie: <code id="prev_eq_serial">-</code></div>
                                <div class="small text-muted"><i class="fas fa-tag mr-1"></i>Tipo: <span id="prev_eq_type" class="font-weight-bold">-</span> (UR <span id="prev_eq_ur">-</span>)</div>
                            </div>
                        </div>

                        <!-- Tarjeta 4: Resumen de Puertos -->
                        <div class="col-md-3">
                            <div class="card border shadow-sm p-3 h-100" style="border-radius: 10px; background: #fff; border-left: 4px solid #101B31 !important;">
                                <small class="text-muted font-weight-bold text-uppercase d-block mb-1">Capacidad de Puertos</small>
                                <div class="font-weight-bold text-dark" style="font-size: 1.15rem;"><span id="prev_ports_detected">0</span> / <span id="prev_ports_capacity">0</span> Puertos</div>
                                <div class="d-flex justify-content-between align-items-center mt-1" style="font-size: 0.78rem;">
                                    <span class="text-success font-weight-bold"><i class="fas fa-circle mr-1" style="font-size: 0.6rem;"></i><span id="prev_ports_connected">0</span> Conectados</span>
                                    <span class="text-secondary font-weight-bold"><i class="far fa-circle mr-1" style="font-size: 0.6rem;"></i><span id="prev_ports_vacant">0</span> Libres</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PANEL DE AJUSTES Y PERSONALIZACIÓN PREVIA DE DATOS (OPCIONAL) -->
                    <div class="card border shadow-xs mb-3" style="border-radius: 10px; background: #ffffff; border-left: 4px solid var(--sonda-cyan) !important;">
                        <div class="card-header bg-white py-2 px-3 d-flex justify-content-between align-items-center cursor-pointer" data-toggle="collapse" data-target="#pm_custom_overrides_collapse" aria-expanded="false" style="user-select: none;">
                            <div class="d-flex align-items-center" style="gap: 8px;">
                                <i class="fas fa-sliders-h text-info"></i>
                                <span class="font-weight-bold text-dark small">¿Desea cambiar algún dato antes de guardar en la BDD? (Personalización de Campos)</span>
                                <span class="badge badge-light border text-muted" style="font-size: 0.72rem;">Clic para desplegar / editar</span>
                            </div>
                            <i class="fas fa-chevron-down text-muted small"></i>
                        </div>
                        <div class="collapse" id="pm_custom_overrides_collapse">
                            <div class="card-body p-3 bg-light border-top">
                                <div class="alert alert-light border py-2 px-3 mb-3 small d-flex align-items-center" style="border-radius: 6px; background: #f8fafc;">
                                    <i class="fas fa-info-circle text-info mr-2 fa-lg"></i>
                                    <div>Los valores detectados en el Excel están prellenados. Si edita cualquiera de estos campos (ej: nombre de rack, área o serie), el sistema guardará sus valores personalizados en la base de datos.</div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3 mb-2">
                                        <label class="small font-weight-bold text-secondary mb-1">Cliente / Empresa:</label>
                                        <input type="text" class="form-control form-control-sm font-weight-bold" id="cust_client" placeholder="Cliente">
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label class="small font-weight-bold text-secondary mb-1">Ubicación / Sede:</label>
                                        <input type="text" class="form-control form-control-sm font-weight-bold" id="cust_location" placeholder="Ubicación">
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label class="small font-weight-bold text-secondary mb-1">Área:</label>
                                        <input type="text" class="form-control form-control-sm font-weight-bold" id="cust_area" placeholder="Área">
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label class="small font-weight-bold text-secondary mb-1">Nombre de Rack:</label>
                                        <input type="text" class="form-control form-control-sm font-weight-bold text-primary" id="cust_rack_name" placeholder="RACK 01">
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <label class="small font-weight-bold text-secondary mb-1">UR Totales Rack:</label>
                                        <input type="number" min="1" max="52" class="form-control form-control-sm font-weight-bold text-center" id="cust_rack_total_u" placeholder="12">
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <label class="small font-weight-bold text-secondary mb-1">Tipo de Rack:</label>
                                        <select class="custom-select custom-select-sm font-weight-bold" id="cust_rack_type">
                                            <option value="AEREO">AEREO</option>
                                            <option value="PISO">PISO</option>
                                            <option value="GABINETE">GABINETE</option>
                                            <option value="BASTIDOR">BASTIDOR</option>
                                            <option value="MURAL">MURAL</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label class="small font-weight-bold text-secondary mb-1">Nombre del Equipo:</label>
                                        <input type="text" class="form-control form-control-sm font-weight-bold text-dark" id="cust_device_name" placeholder="Switch HP 1920S">
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <label class="small font-weight-bold text-secondary mb-1">Fabricante:</label>
                                        <input type="text" class="form-control form-control-sm font-weight-bold" id="cust_vendor" placeholder="Cisco, Aruba, etc.">
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label class="small font-weight-bold text-secondary mb-1">Número de Serie:</label>
                                        <input type="text" class="form-control form-control-sm font-weight-bold" id="cust_serial" placeholder="Serie">
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <label class="small font-weight-bold text-secondary mb-1">UR en Rack:</label>
                                        <input type="number" min="1" max="52" class="form-control form-control-sm font-weight-bold text-center" id="cust_ur" placeholder="7">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN DETALLADA: PESTAÑAS DE REVISIÓN -->
                    <div class="card border shadow-sm mb-3" style="border-radius: 10px; background: #ffffff;">
                        <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap" style="gap: 8px;">
                            <ul class="nav nav-pills" id="pm_prev_tabs" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active py-1 px-3 small font-weight-bold" id="prev_tab_ports_link" data-toggle="pill" href="#prev_tab_ports">
                                        <i class="fas fa-ethernet mr-1"></i> Puertos & Estados Link (<span id="prev_badge_ports_count">0</span>)
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link py-1 px-3 small font-weight-bold" id="prev_tab_rack_link" data-toggle="pill" href="#prev_tab_rack">
                                        <i class="fas fa-cubes mr-1"></i> Hardware en Rack (<span id="prev_badge_rack_count">0</span>)
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link py-1 px-3 small font-weight-bold" id="prev_tab_mapping_link" data-toggle="pill" href="#prev_tab_mapping" style="background: rgba(0, 184, 212, 0.1); color: #00838f;">
                                        <i class="fas fa-project-diagram mr-1"></i> Mapeo de Conexión: Excel ➔ Base de Datos (BDD)
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <div class="card-body p-0">
                            <div class="tab-content" id="pm_prev_tabs_content">
                                <!-- Tab Puertos -->
                                <div class="tab-pane fade show active" id="prev_tab_ports" role="tabpanel">
                                    <div class="p-2 bg-light border-bottom d-flex justify-content-between align-items-center flex-wrap small px-3">
                                        <span class="text-secondary"><i class="fas fa-info-circle text-info mr-1"></i> Reconoce automáticamente estados <strong>UP / DOWN</strong> y <strong>ON / OFF</strong> de la hoja de cálculo.</span>
                                        <span class="badge badge-light border text-dark font-weight-bold" id="prev_ports_status_legend">-</span>
                                    </div>
                                    <div class="table-responsive" style="max-height: 270px; overflow-y: auto;">
                                        <table class="table table-sm table-striped table-hover mb-0" style="font-size: 0.8rem;">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th class="text-center" style="width: 70px;">Puerto</th>
                                                    <th>Estado Link (Excel)</th>
                                                    <th>Velocidad / Tipo</th>
                                                    <th>Destino / Patch Panel</th>
                                                    <th>Módulo / Conector</th>
                                                    <th>Notas / Observaciones</th>
                                                </tr>
                                            </thead>
                                            <tbody id="prev_ports_tbody"></tbody>
                                        </table>
                                    </div>
                                </div>
                                <!-- Tab Rack Hardware -->
                                <div class="tab-pane fade" id="prev_tab_rack" role="tabpanel">
                                    <div class="p-2 bg-light border-bottom d-flex justify-content-between align-items-center flex-wrap small px-3">
                                        <div id="pm_prev_rack_selector_container" class="d-flex align-items-center" style="gap: 8px; display: none !important;">
                                            <span class="font-weight-bold text-dark"><i class="fas fa-server text-info mr-1"></i> Rack a Auditar:</span>
                                            <select id="pm_prev_rack_selector" class="custom-select custom-select-sm font-weight-bold" style="min-width: 260px;" onchange="switchPreviewRack(this.value)">
                                            </select>
                                        </div>
                                        <div id="pm_prev_rack_meta_info">
                                            <span class="badge badge-light border text-dark font-weight-bold" id="pm_prev_rack_meta_text">-</span>
                                        </div>
                                    </div>
                                    <div class="table-responsive" style="max-height: 270px; overflow-y: auto;">
                                        <table class="table table-sm table-striped table-hover mb-0" style="font-size: 0.8rem;">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th class="text-center" style="width: 90px;">Unidad (UR)</th>
                                                    <th class="text-center" style="width: 80px;">Altura</th>
                                                    <th>Dispositivo Físico</th>
                                                    <th>Orientación</th>
                                                </tr>
                                            </thead>
                                            <tbody id="prev_rack_tbody"></tbody>
                                        </table>
                                    </div>
                                </div>
                                <!-- Tab Mapeo BDD -->
                                <div class="tab-pane fade" id="prev_tab_mapping" role="tabpanel">
                                    <div class="p-2 bg-light border-bottom d-flex justify-content-between align-items-center flex-wrap small px-3">
                                        <span class="text-secondary"><i class="fas fa-database text-info mr-1"></i> Correspondencia exacta entre columnas del archivo Excel y las tablas relacionales del sistema.</span>
                                        <span class="badge badge-info px-2 py-1"><i class="fas fa-check-double mr-1"></i>Sincronización Total</span>
                                    </div>
                                    <div class="table-responsive" style="max-height: 270px; overflow-y: auto;">
                                        <table class="table table-sm table-striped table-hover mb-0" style="font-size: 0.78rem;">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th style="width: 170px;">Hoja & Columna Excel</th>
                                                    <th style="width: 150px;">Tabla BDD Destino</th>
                                                    <th style="width: 130px;">Campo BDD</th>
                                                    <th>Regla de Transformación / Conexión</th>
                                                    <th style="width: 170px;">Valor Extraído en este Archivo</th>
                                                    <th class="text-center" style="width: 90px;">Ajustable</th>
                                                </tr>
                                            </thead>
                                            <tbody id="prev_mapping_tbody"></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ACCIONES DE CONFIRMACIÓN -->
                    <div class="d-flex justify-content-between align-items-center flex-wrap pt-2 border-top" style="gap: 10px;">
                        <div>
                            <button type="button" class="btn btn-outline-secondary btn-sm font-weight-bold" onclick="backToImportUpload()">
                                <i class="fas fa-arrow-left mr-1"></i> Seleccionar Otro Archivo
                            </button>
                        </div>
                        <div class="d-flex align-items-center" style="gap: 10px;">
                            <button type="button" class="btn btn-secondary btn-sm px-3 font-weight-bold" data-dismiss="modal">Cancelar</button>
                            <button type="button" id="btn_pm_execute_import" class="btn btn-success btn-sm px-4 font-weight-bold shadow-sm" onclick="executePortmappingImport()">
                                <i class="fas fa-check-circle mr-1"></i> Confirmar e Importar al Sistema
                            </button>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </div>
</div>

<!-- MODAL: REGISTRAR / CONECTAR PUERTO (GESTIÓN POR EQUIPO) -->
<div class="modal fade" id="modalConnection" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-link mr-2"></i> Registrar Conexión de Puerto</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body p-4">
                <form id="form_save_connection">
                    <input type="hidden" id="modal_device_id">
                    <input type="hidden" id="modal_port_name">
                    <input type="hidden" id="modal_connection_type">

                    <div class="form-group mb-3">
                        <label class="small font-weight-bold text-navy">Equipo Origen</label>
                        <input type="text" id="modal_display_src_device" class="form-control bg-light font-weight-bold" readonly>
                    </div>
                    <div class="form-group mb-3">
                        <label class="small font-weight-bold text-navy">Puerto Origen</label>
                        <input type="text" id="modal_display_src_port" class="form-control bg-light font-weight-bold" readonly>
                    </div>

                    <div class="form-group mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="small font-weight-bold mb-0 text-navy">Equipo Destino</label>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="chk_manual_device" onchange="toggleManualDevice(this.checked)">
                                <label class="custom-control-label small font-weight-bold" for="chk_manual_device">Escribir equipo manualmente</label>
                            </div>
                        </div>
                        <div id="wrapper_select_device">
                            <select id="modal_dest_device" class="form-control" style="width:100%;">
                                <option value="">Buscar equipo destino...</option>
                            </select>
                        </div>
                        <div id="wrapper_text_device" style="display: none;">
                            <input type="text" id="modal_dest_device_text" class="form-control" placeholder="Ej. SW-PISO2-MANUAL o SRV-BD-01">
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="small font-weight-bold mb-0 text-navy">Puerto / Toma Destino</label>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="chk_manual_port" onchange="toggleManualPort(this.checked)">
                                <label class="custom-control-label small font-weight-bold" for="chk_manual_port">Escribir manualmente</label>
                            </div>
                        </div>
                        
                        <div id="wrapper_select_port">
                            <select id="modal_dest_port" class="form-control" style="width:100%;">
                                <option value="">Seleccione Equipo Destino Primero</option>
                            </select>
                        </div>
                        <div id="wrapper_text_port" style="display: none;">
                            <input type="text" id="modal_dest_port_text" class="form-control" placeholder="Ej. Gi1/0/2 o PSU-1-In">
                        </div>
                    </div>

                    <div class="form-row mb-3">
                        <div class="form-group col-md-6 mb-0">
                            <label class="small font-weight-bold text-navy">Medio / Tipo Cable</label>
                            <select id="modal_cable_type" class="form-control">
                                <option>UTP Cat6A</option>
                                <option>UTP Cat6</option>
                                <option>Fibra OM4</option>
                                <option>Fibra OS2</option>
                                <option>DAC / Twinax</option>
                            </select>
                        </div>
                        <div class="form-group col-md-6 mb-0">
                            <label class="small font-weight-bold text-navy">Color del Cable</label>
                            <input type="color" id="modal_color_code" class="form-control" value="#0000FF" style="height: 38px;">
                        </div>
                    </div>

                    <div class="form-group mb-4">
                        <label class="small font-weight-bold text-navy">Observación / Notas</label>
                        <textarea id="modal_notes" class="form-control" rows="2" placeholder="Notas sobre el cableado, parcheo o distribución..."></textarea>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-secondary font-weight-bold mr-2" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary px-4 shadow-sm font-weight-bold">Guardar Conexión</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// VARIABLES GLOBALES
let pmSurveyPorts = [];
let pmSurveyImages = [];
let pmSurveyConfigFiles = [];
let pmSurveyDiagram = null;
let zabbixHostsList = [];
let cmdbDevices = [];
let selectedDevice = null;
let cachedManualSurveys = [];

// CONFIGURACIÓN GLOBAL DEL INFORME PDF Y CARÁTULA (PESTAÑA 03)
let g_pmReportConfig = {
    title: 'INFORME TÉCNICO DE AUDITORÍA Y MAPEO DE PUERTOS',
    subtitle: 'Documentación de Infraestructura y Matriz de Conectividad Física',
    client: 'VILASECA',
    author: 'Ing. Especialista en Infraestructura',
    department: 'Servicios de TI & Datacenter',
    version: 'v1.0 - Oficial',
    
    logo1: 'sonda',
    logo1Custom: '',
    logo2: 'synapse',
    logo2Custom: '',
    logo3: 'femsa',
    logo3Custom: '',
    
    showLogoInCover: true,
    showLogoInHeader: true,
    showLogoInFooter: true,
    
    incCoverPage: true,
    incToc: true,
    incMetadata: true,
    incMatrixTable: true,
    incTopologyGraph: true,
    incPhotoEvidence: true,
    incSignatures: true,
    
    themeColor: '#002B49',
    pageOrientation: 'landscape',
    confidentialNotice: 'CONFIDENCIAL • DOCUMENTO TÉCNICO OFICIAL DE INFRAESTRUCTURA SONDA / SYNAPSE',
    notes: 'Sin observaciones adicionales registradas.'
};

function getReportLogoHtml(logoType, customDataUrl, defaultLabel = 'SONDA') {
    if (logoType === 'sonda') {
        return `<div style="font-size:15px; font-weight:900; color:#ffffff; background:#002B49; border-radius:6px; padding:6px 14px; letter-spacing:1px; display:inline-flex; align-items:center; box-shadow:0 2px 4px rgba(0,0,0,0.15);"><i class="fas fa-network-wired mr-1" style="color:#38bdf8;"></i> SONDA</div>`;
    }
    if (logoType === 'synapse') {
        return `<div style="font-size:15px; font-weight:900; color:#ffffff; background:#0284c7; border-radius:6px; padding:6px 14px; letter-spacing:1px; display:inline-flex; align-items:center; box-shadow:0 2px 4px rgba(0,0,0,0.15);"><i class="fas fa-cubes mr-1" style="color:#e0f2fe;"></i> SYNAPSE CMDB</div>`;
    }
    if (logoType === 'femsa') {
        return `<div style="font-size:15px; font-weight:900; color:#ffffff; background:#D9272E; border-radius:6px; padding:6px 14px; letter-spacing:1px; display:inline-flex; align-items:center; box-shadow:0 2px 4px rgba(0,0,0,0.15);"><i class="fas fa-building mr-1" style="color:#fee2e2;"></i> FEMSA</div>`;
    }
    if (logoType === 'client') {
        const clientText = (g_pmReportConfig.client || defaultLabel || 'CLIENTE').toUpperCase();
        return `<div style="font-size:14px; font-weight:800; color:#ffffff; background:#475569; border-radius:6px; padding:6px 14px; letter-spacing:1px; display:inline-flex; align-items:center;"><i class="fas fa-user-shield mr-1" style="color:#cbd5e1;"></i> ${clientText}</div>`;
    }
    if (logoType === 'custom' && customDataUrl) {
        return `<img src="${customDataUrl}" style="max-height:45px; max-width:150px; object-fit:contain; border-radius:4px;">`;
    }
    return `<div style="font-size:14px; font-weight:800; color:#ffffff; background:#64748b; border-radius:6px; padding:6px 14px; letter-spacing:1px;">${defaultLabel.toUpperCase()}</div>`;
}

function activatePmTab(targetId) {
    const panes = document.querySelectorAll('#pm-tab-content > .tab-pane');
    panes.forEach(pane => {
        pane.classList.remove('show', 'active');
        pane.style.display = 'none';
    });

    const links = document.querySelectorAll('#pm-tabs .nav-link');
    links.forEach(link => {
        link.classList.remove('active');
    });

    const targetPane = document.getElementById(targetId);
    if (targetPane) {
        targetPane.classList.add('show', 'active');
        targetPane.style.display = 'block';
    }

    const targetLink = document.querySelector(`#pm-tabs .nav-link[href="#${targetId}"]`);
    if (targetLink) {
        targetLink.classList.add('active');
    }
}

function loadReportConfigTab() {
    activatePmTab('report-config-content');
    initReportConfig();
}

function initReportConfig() {
    const saved = localStorage.getItem('pm_report_config_global');
    if (saved) {
        try {
            const parsed = JSON.parse(saved);
            g_pmReportConfig = Object.assign({}, g_pmReportConfig, parsed);
        } catch(e) {}
    }

    // Poblar inputs de la Pestaña 03
    const setVal = (id, val) => { const el = document.getElementById(id); if (el) el.value = val || ''; };
    const setCheck = (id, val) => { const el = document.getElementById(id); if (el) el.checked = !!val; };

    setVal('cfg_report_title', g_pmReportConfig.title);
    setVal('cfg_report_subtitle', g_pmReportConfig.subtitle);
    setVal('cfg_report_client', g_pmReportConfig.client);
    setVal('cfg_report_author', g_pmReportConfig.author);
    setVal('cfg_report_department', g_pmReportConfig.department);
    setVal('cfg_report_version', g_pmReportConfig.version);
    setVal('cfg_logo1_type', g_pmReportConfig.logo1);
    setVal('cfg_logo2_type', g_pmReportConfig.logo2);
    setVal('cfg_logo3_type', g_pmReportConfig.logo3);

    setCheck('cfg_show_logo_cover', g_pmReportConfig.showLogoInCover);
    setCheck('cfg_show_logo_header', g_pmReportConfig.showLogoInHeader);
    setCheck('cfg_show_logo_footer', g_pmReportConfig.showLogoInFooter);

    setCheck('cfg_inc_cover', g_pmReportConfig.incCoverPage);
    setCheck('cfg_inc_toc', g_pmReportConfig.incToc);
    setCheck('cfg_inc_metadata', g_pmReportConfig.incMetadata);
    setCheck('cfg_inc_matrix', g_pmReportConfig.incMatrixTable);
    setCheck('cfg_inc_topology', g_pmReportConfig.incTopologyGraph);
    setCheck('cfg_inc_photos', g_pmReportConfig.incPhotoEvidence);
    setCheck('cfg_inc_signatures', g_pmReportConfig.incSignatures);

    setVal('cfg_theme_color', g_pmReportConfig.themeColor);
    setVal('cfg_page_orientation', g_pmReportConfig.pageOrientation);
    setVal('cfg_confidential_notice', g_pmReportConfig.confidentialNotice);

    handleLogoChange(1);
    handleLogoChange(2);
    handleLogoChange(3);

    updateReportConfigLivePreview();
}

function triggerLogoSlotUpload(num) {
    const sel = document.getElementById(`cfg_logo${num}_type`);
    if (sel) {
        sel.value = 'custom';
        handleLogoChange(num);
    }
    const fileInput = document.getElementById(`cfg_logo${num}_file`);
    if (fileInput) {
        fileInput.click();
    }
}

function handleLogoChange(num) {
    const sel = document.getElementById(`cfg_logo${num}_type`);
    const fileWrapper = document.getElementById(`cfg_logo${num}_file_wrapper`);
    if (sel && fileWrapper) {
        fileWrapper.style.display = sel.value === 'custom' ? 'block' : 'none';
    }
    updateReportConfigLivePreview();
}

async function handleCustomLogoUpload(num, event) {
    const file = event.target.files[0];
    if (!file) return;

    const statusEl = document.getElementById(`cfg_logo${num}_status`);
    if (statusEl) {
        statusEl.innerHTML = '<span class="text-primary"><i class="fas fa-spinner fa-spin mr-1"></i> Subiendo al servidor...</span>';
    }

    const formData = new FormData();
    formData.append('action', 'upload_report_logo');
    formData.append('slot', num);
    formData.append('logo_file', file);

    try {
        const resp = await fetch('api_portmapping.php', {
            method: 'POST',
            body: formData
        });
        const result = await resp.json();

        if (result.success && result.url) {
            g_pmReportConfig[`logo${num}Custom`] = result.url;
            if (statusEl) {
                statusEl.innerHTML = '<span class="text-success"><i class="fas fa-check-circle mr-1"></i> Guardado en servidor</span>';
            }
            saveReportConfigSettings();
            updateReportConfigLivePreview();
            if (window.toastr) {
                toastr.success(`Logotipo ${num} cargado en el servidor`);
            }
        } else {
            throw new Error(result.error || 'Error al guardar la imagen en el servidor');
        }
    } catch (err) {
        console.warn('Fallback a FileReader Base64:', err.message);
        const reader = new FileReader();
        reader.onload = function(e) {
            g_pmReportConfig[`logo${num}Custom`] = e.target.result;
            if (statusEl) {
                statusEl.innerHTML = '<span class="text-warning"><i class="fas fa-exclamation-triangle mr-1"></i> Modo local (Base64)</span>';
            }
            saveReportConfigSettings();
            updateReportConfigLivePreview();
        };
        reader.readAsDataURL(file);
    }
}

function updateReportConfigLivePreview() {
    const getVal = (id, fallback) => document.getElementById(id)?.value || fallback;
    const getCheck = (id, fallback) => {
        const el = document.getElementById(id);
        return el ? el.checked : fallback;
    };

    g_pmReportConfig.title = getVal('cfg_report_title', 'INFORME TÉCNICO DE AUDITORÍA Y MAPEO DE PUERTOS');
    g_pmReportConfig.subtitle = getVal('cfg_report_subtitle', 'Documentación de Infraestructura y Matriz de Conectividad Física');
    g_pmReportConfig.client = getVal('cfg_report_client', 'VILASECA');
    g_pmReportConfig.author = getVal('cfg_report_author', 'Ing. Especialista en Infraestructura');
    g_pmReportConfig.department = getVal('cfg_report_department', 'Servicios de TI & Datacenter');
    g_pmReportConfig.version = getVal('cfg_report_version', 'v1.0 - Oficial');

    g_pmReportConfig.logo1 = getVal('cfg_logo1_type', 'sonda');
    g_pmReportConfig.logo2 = getVal('cfg_logo2_type', 'synapse');
    g_pmReportConfig.logo3 = getVal('cfg_logo3_type', 'femsa');

    g_pmReportConfig.showLogoInCover = getCheck('cfg_show_logo_cover', true);
    g_pmReportConfig.showLogoInHeader = getCheck('cfg_show_logo_header', true);
    g_pmReportConfig.showLogoInFooter = getCheck('cfg_show_logo_footer', true);

    g_pmReportConfig.incCoverPage = getCheck('cfg_inc_cover', true);
    g_pmReportConfig.incToc = getCheck('cfg_inc_toc', true);
    g_pmReportConfig.incMetadata = getCheck('cfg_inc_metadata', true);
    g_pmReportConfig.incMatrixTable = getCheck('cfg_inc_matrix', true);
    g_pmReportConfig.incTopologyGraph = getCheck('cfg_inc_topology', true);
    g_pmReportConfig.incPhotoEvidence = getCheck('cfg_inc_photos', true);
    g_pmReportConfig.incSignatures = getCheck('cfg_inc_signatures', true);

    g_pmReportConfig.themeColor = getVal('cfg_theme_color', '#002B49');
    g_pmReportConfig.pageOrientation = getVal('cfg_page_orientation', 'landscape');
    g_pmReportConfig.confidentialNotice = getVal('cfg_confidential_notice', 'CONFIDENCIAL • DOCUMENTO TÉCNICO OFICIAL DE INFRAESTRUCTURA SONDA / SYNAPSE');

    // Renderizar Previews de Logotipos en las 3 tarjetas de selección
    const p1 = document.getElementById('preview_logo1_box');
    if (p1) p1.innerHTML = getReportLogoHtml(g_pmReportConfig.logo1, g_pmReportConfig.logo1Custom, 'SONDA');

    const p2 = document.getElementById('preview_logo2_box');
    if (p2) p2.innerHTML = getReportLogoHtml(g_pmReportConfig.logo2, g_pmReportConfig.logo2Custom, 'SYNAPSE');

    const p3 = document.getElementById('preview_logo3_box');
    if (p3) p3.innerHTML = getReportLogoHtml(g_pmReportConfig.logo3, g_pmReportConfig.logo3Custom, 'FEMSA');

    // Calcular total de páginas habilitadas
    let pageCount = 0;
    if (g_pmReportConfig.incCoverPage) pageCount++;
    if (g_pmReportConfig.incToc) pageCount++;
    if (g_pmReportConfig.incMetadata) pageCount++;
    if (g_pmReportConfig.incMatrixTable) pageCount++;
    if (g_pmReportConfig.incTopologyGraph) pageCount++;
    if (g_pmReportConfig.incPhotoEvidence) pageCount++;
    if (g_pmReportConfig.incSignatures) pageCount++;

    const pageBadge = document.getElementById('cfg_preview_page_count');
    if (pageBadge) pageBadge.innerText = `${pageCount} Páginas Habilitadas`;

    // Renderizar Maqueta Ejecutiva en el Box de Vista Previa Derecha
    const coverBox = document.getElementById('cfg_live_cover_preview_box');
    if (coverBox) {
        const themeColor = g_pmReportConfig.themeColor;
        const logo1Html = g_pmReportConfig.showLogoInCover ? getReportLogoHtml(g_pmReportConfig.logo1, g_pmReportConfig.logo1Custom, 'SONDA') : '';
        const logo2Html = g_pmReportConfig.showLogoInCover ? getReportLogoHtml(g_pmReportConfig.logo2, g_pmReportConfig.logo2Custom, 'SYNAPSE') : '';
        const logo3Html = g_pmReportConfig.showLogoInCover ? getReportLogoHtml(g_pmReportConfig.logo3, g_pmReportConfig.logo3Custom, 'FEMSA') : '';

        coverBox.innerHTML = `
            <div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 18px; background: #ffffff; min-height: 440px; display: flex; flex-direction: column; justify-content: space-between; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                <div>
                    <!-- CABECERA CON HASTA 3 LOGOTIPOS -->
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid ${themeColor}; padding-bottom: 12px; margin-bottom: 24px;">
                        <div>${logo1Html}</div>
                        <div>${logo2Html}</div>
                        <div>${logo3Html}</div>
                    </div>

                    <!-- BLOQUE DE TÍTULO PRINCIPAL DE LA CARÁTULA -->
                    <div style="text-align: center; margin: 25px 0 20px 0;">
                        <h4 style="font-weight: 900; color: ${themeColor}; text-transform: uppercase; margin: 0 0 6px 0; font-size: 16px; letter-spacing: 0.5px;">
                            ${g_pmReportConfig.title}
                        </h4>
                        <h6 style="font-weight: 600; color: #475569; margin: 0; font-size: 11.5px;">
                            ${g_pmReportConfig.subtitle}
                        </h6>
                        <span style="display: inline-block; margin-top: 10px; padding: 3px 10px; background: #f1f5f9; border-radius: 4px; font-size: 10px; font-weight: 700; color: #64748b;">
                            VERSIÓN: ${g_pmReportConfig.version} &bull; ORIENTACIÓN: ${(g_pmReportConfig.pageOrientation || 'landscape').toUpperCase()}
                        </span>
                    </div>

                    <!-- TABLA METADATOS DE CARÁTULA -->
                    <table style="width: 100%; border-collapse: collapse; font-size: 10.5px; margin-top: 15px; border-radius: 6px; overflow: hidden; border: 1px solid #cbd5e1;">
                        <tr style="background: #f8fafc;"><td style="padding: 6px 10px; font-weight: 700; color: #334155; width: 40%; border-bottom: 1px solid #e2e8f0;">CLIENTE / EMPRESA:</td><td style="padding: 6px 10px; font-weight: 800; color: ${themeColor}; border-bottom: 1px solid #e2e8f0;">${g_pmReportConfig.client}</td></tr>
                        <tr><td style="padding: 6px 10px; font-weight: 700; color: #334155; border-bottom: 1px solid #e2e8f0;">DEPARTAMENTO:</td><td style="padding: 6px 10px; font-weight: 600; color: #0f172a; border-bottom: 1px solid #e2e8f0;">${g_pmReportConfig.department}</td></tr>
                        <tr style="background: #f8fafc;"><td style="padding: 6px 10px; font-weight: 700; color: #334155; border-bottom: 1px solid #e2e8f0;">RESPONSABLE TÉCNICO:</td><td style="padding: 6px 10px; font-weight: 700; color: #0284c7; border-bottom: 1px solid #e2e8f0;">${g_pmReportConfig.author}</td></tr>
                        <tr><td style="padding: 6px 10px; font-weight: 700; color: #334155;">FECHA GENERACIÓN:</td><td style="padding: 6px 10px; font-weight: 600; color: #475569;">${new Date().toISOString().slice(0, 10)}</td></tr>
                    </table>
                </div>

                <!-- PIE DE CARÁTULA CONFIDENCIAL -->
                <div style="text-align: center; font-size: 9px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 8px; margin-top: 15px; font-weight: 600;">
                    ${g_pmReportConfig.confidentialNotice}
                </div>
            </div>
        `;
    }
}

function saveReportConfigSettings(showToast = true) {
    updateReportConfigLivePreview();
    localStorage.setItem('pm_report_config_global', JSON.stringify(g_pmReportConfig));

    // Sincronizar inputs en el Paso 5 si están en pantalla
    const title5 = document.getElementById('pm_report_title_custom');
    if (title5) title5.value = g_pmReportConfig.title;

    const sub5 = document.getElementById('pm_report_subtitle_custom');
    if (sub5) sub5.value = g_pmReportConfig.subtitle;

    const aut5 = document.getElementById('pm_report_author_custom');
    if (aut5) aut5.value = g_pmReportConfig.author;

    if (showToast) {
        if (window.Swal) {
            Swal.fire({
                icon: 'success',
                title: 'Configuración Guardada',
                text: 'Los parámetros de informes PDF, carátula y logotipos han sido guardados correctamente.',
                timer: 1800,
                showConfirmButton: false
            });
        } else if (window.toastr) {
            toastr.success('Configuración de informe guardada.');
        }
    }
}

function resetReportConfigDefaults() {
    localStorage.removeItem('pm_report_config_global');
    g_pmReportConfig = {
        title: 'INFORME TÉCNICO DE AUDITORÍA Y MAPEO DE PUERTOS',
        subtitle: 'Documentación de Infraestructura y Matriz de Conectividad Física',
        client: 'VILASECA',
        author: 'Ing. Especialista en Infraestructura',
        department: 'Servicios de TI & Datacenter',
        version: 'v1.0 - Oficial',
        logo1: 'sonda', logo1Custom: '',
        logo2: 'synapse', logo2Custom: '',
        logo3: 'femsa', logo3Custom: '',
        showLogoInCover: true, showLogoInHeader: true, showLogoInFooter: true,
        incCoverPage: true, incToc: true, incMetadata: true, incMatrixTable: true,
        incTopologyGraph: true, incPhotoEvidence: true, incSignatures: true,
        themeColor: '#002B49', pageOrientation: 'landscape',
        confidentialNotice: 'CONFIDENCIAL • DOCUMENTO TÉCNICO OFICIAL DE INFRAESTRUCTURA SONDA / SYNAPSE',
        notes: 'Sin observaciones adicionales registradas.'
    };
    initReportConfig();
    if (window.toastr) toastr.info('Valores predeterminados restablecidos.');
}

function previewConfiguredPDF() {
    saveReportConfigSettings(false);
    triggerPmExecutivePrint();
}

function clampPp1To48(val) {
    if (val === '' || val === null || val === undefined) return '';
    let num = parseInt(String(val).replace(/[^0-9]/g, ''), 10);
    if (isNaN(num)) return '';
    if (num < 1) return '1';
    if (num > 48) return '48';
    return String(num);
}

function normalizePortName(val, fallbackIndex) {
    if (!val) return String(fallbackIndex || 1);
    return String(val).trim();
}

function updatePatchPanelFormVisibility() {
    const devTypeElem = document.getElementById('pm-survey-device-type');
    const deviceType = devTypeElem ? devTypeElem.value : '';
    const isPatchPanel = (deviceType === 'Patch Panel' || deviceType.toLowerCase().includes('patch'));
    
    // Ocultar / Mostrar elementos de hardware activo
    const activeElements = document.querySelectorAll('.pm-form-active-only');
    activeElements.forEach(el => {
        el.style.display = isPatchPanel ? 'none' : '';
    });

    const passiveElements = document.querySelectorAll('.pm-form-passive-only');
    passiveElements.forEach(el => {
        el.style.display = isPatchPanel ? '' : 'none';
    });

    // Actualizar badges e indicativo de modo
    const badge = document.getElementById('topology_mode_badge');
    if (badge) {
        if (isPatchPanel) {
            badge.className = 'badge badge-purple px-3 py-1 font-weight-bold';
            badge.innerHTML = '<i class="fas fa-ethernet mr-1"></i> Modo Infraestructura Pasiva (Patch Panel)';
        } else {
            badge.className = 'badge badge-primary px-3 py-1 font-weight-bold';
            badge.innerHTML = '<i class="fas fa-server mr-1"></i> Modo Equipamiento Activo (Switch/Router)';
        }
    }

    // Actualizar diagramas dinámicos de conectividad
    renderTopologyDiagram(isPatchPanel);
    autofillPpOrigen();
}

function renderTopologyDiagram(isPatchPanel) {
    const container = document.getElementById('topology_flow_steps');
    if (!container) return;

    if (isPatchPanel) {
        container.innerHTML = `
            <div class="topology-node passive-node">
                <span class="badge badge-purple mb-1 text-white font-weight-bold" style="background:#6f42c1;">1. Patch Panel Origen (Pasivo)</span>
                <div class="font-weight-bold text-navy small" id="diag_pp_src_name">Equipo Origen (Puertos 1..48)</div>
            </div>
            <div class="topology-arrow"><i class="fas fa-exchange-alt text-purple" style="color:#6f42c1;"></i> ══════════►</div>
            <div class="topology-node passive-node">
                <span class="badge badge-purple mb-1 text-white font-weight-bold" style="background:#6f42c1;">2. Patch Panel Destino</span>
                <div class="font-weight-bold text-navy small">Cruzada / Cableado Estructurado</div>
            </div>
        `;
    } else {
        container.innerHTML = `
            <div class="topology-node active-node">
                <span class="badge badge-primary mb-1 font-weight-bold">1. Equipamiento Origen</span>
                <div class="font-weight-bold text-navy small">Switch / Router Activo</div>
            </div>
            <div class="topology-arrow"><i class="fas fa-arrow-right"></i></div>
            <div class="topology-node">
                <span class="badge badge-secondary mb-1 font-weight-bold">2. Patch Panel Origen</span>
                <div class="font-weight-bold text-muted small">Gabinete / UR Origen</div>
            </div>
            <div class="topology-arrow"><i class="fas fa-arrow-right"></i></div>
            <div class="topology-node">
                <span class="badge badge-secondary mb-1 font-weight-bold">3. Patch Panel Destino</span>
                <div class="font-weight-bold text-muted small">Gabinete / UR Destino</div>
            </div>
            <div class="topology-arrow"><i class="fas fa-arrow-right"></i></div>
            <div class="topology-node active-node">
                <span class="badge badge-primary mb-1 font-weight-bold">4. Equipamiento Destino</span>
                <div class="font-weight-bold text-navy small">Endpoint / Servidor</div>
            </div>
        `;
    }
}

function autofillPpOrigen() {
    const devTypeElem = document.getElementById('pm-survey-device-type');
    const deviceType = devTypeElem ? devTypeElem.value : '';
    const isPatchPanel = (deviceType === 'Patch Panel' || deviceType.toLowerCase().includes('patch'));

    const deviceName = document.getElementById('pm-survey-device')?.value.trim() || 'PP-ORIGEN';
    const rackName = document.getElementById('pm-survey-rack')?.value.trim() || '';
    const urVal = document.getElementById('pm-survey-ur')?.value.trim() || '';
    const portName = document.getElementById('pm-port-name')?.value.trim() || '1';

    const ppSrcInput = document.getElementById('pm-port-pp-src');
    if (!ppSrcInput) return;

    if (isPatchPanel) {
        let text = deviceName;
        let details = [];
        if (rackName) details.push(rackName);
        if (urVal) details.push('U' + urVal);
        if (portName) details.push('P-' + String(portName).padStart(2, '0'));

        if (details.length > 0) {
            text += ' (' + details.join(' / ') + ')';
        }
        ppSrcInput.value = text;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    if (tabParam === 'analisis' || window.location.hash === '#analisis' || window.location.hash === '#survey-analysis-content') {
        loadSurveyAnalysisTab();
    } else {
        activatePmTab('survey-list-content');
        loadManualSurveys();
    }
    initDeviceSelectors();
    loadMappings();
    updatePatchPanelFormVisibility();
    initReportConfig();
});

let currentSurveySortCol = 'id';
let currentSurveySortDir = 'desc';
let currentSurveySearchQuery = '';
let selectedSurveyIds = new Set();

// 1. CARGAR TABLA DE LEVANTAMIENTOS MANUALES
async function loadManualSurveys() {
    activatePmTab('survey-list-content');
    const tbody = document.getElementById('tbl_manual_surveys_body');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin mr-2"></i> Cargando levantamientos...</td></tr>';
    
    // Check if client parameter exists in URL (e.g. ?cliente=VILASECA)
    const urlParams = new URLSearchParams(window.location.search);
    const clientUrlParam = urlParams.get('cliente');
    const clientBadgeWrapper = document.getElementById('client_filter_badge_wrapper');
    const clientBadgeText = document.getElementById('client_filter_badge_text');
    if (clientUrlParam && clientBadgeWrapper && clientBadgeText) {
        clientBadgeWrapper.style.display = 'inline-block';
        clientBadgeText.innerHTML = `<i class="fas fa-filter mr-1"></i> Cliente: ${clientUrlParam} <a href="portmapping.php" class="text-dark ml-1 font-weight-bold" title="Quitar filtro de cliente" style="text-decoration: none;">&times;</a>`;
    } else if (clientBadgeWrapper) {
        clientBadgeWrapper.style.display = 'none';
    }

    try {
        const resp = await fetch('api_portmapping.php?action=get_manual_surveys');
        const res = await resp.json();
        if (res.success && res.data && res.data.length > 0) {
            cachedManualSurveys = res.data;
            renderManualSurveysTable();
        } else {
            cachedManualSurveys = [];
            selectedSurveyIds.clear();
            updateBatchActionBar();
            tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted"><i class="fas fa-folder-open mr-2"></i> No hay levantamientos registrados. Haga clic en "+ Nuevo Levantamiento".</td></tr>';
            const countBadge = document.getElementById('survey_count_badge');
            if (countBadge) countBadge.textContent = '0';
        }
    } catch (err) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-danger"><i class="fas fa-exclamation-triangle mr-2"></i> Error al obtener levantamientos.</td></tr>';
    }
}

function renderManualSurveysTable() {
    const tbody = document.getElementById('tbl_manual_surveys_body');
    if (!tbody) return;
    
    if (!cachedManualSurveys || cachedManualSurveys.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted"><i class="fas fa-folder-open mr-2"></i> No hay levantamientos registrados. Haga clic en "+ Nuevo Levantamiento".</td></tr>';
        const countBadge = document.getElementById('survey_count_badge');
        if (countBadge) countBadge.textContent = '0';
        updateBatchActionBar();
        return;
    }

    const urlParams = new URLSearchParams(window.location.search);
    const clientUrlParam = urlParams.get('cliente');

    // Filter data
    let filtered = cachedManualSurveys.filter(item => {
        if (clientUrlParam && clientUrlParam.trim() !== '') {
            const itemClient = (item.client || '').toUpperCase();
            if (!itemClient.includes(clientUrlParam.toUpperCase())) {
                return false;
            }
        }

        if (currentSurveySearchQuery && currentSurveySearchQuery.trim() !== '') {
            const q = currentSurveySearchQuery.toLowerCase().trim();
            const idStr = String(item.id || '').toLowerCase();
            const clientStr = String(item.client || '').toLowerCase();
            const locStr = String(item.location || '').toLowerCase();
            const devStr = String(item.device_name || '').toLowerCase();
            const typeStr = String(item.device_type || '').toLowerCase();
            const dateStr = String(item.creation_date || item.created_at || '').toLowerCase();
            const vendorStr = String(item.vendor || '').toLowerCase();
            const serialStr = String(item.serial || item.device_label || '').toLowerCase();
            
            const match = idStr.includes(q) || clientStr.includes(q) || locStr.includes(q) || devStr.includes(q) || typeStr.includes(q) || dateStr.includes(q) || vendorStr.includes(q) || serialStr.includes(q);
            if (!match) return false;
        }

        return true;
    });

    // Sort data
    filtered.sort((a, b) => {
        let valA = a[currentSurveySortCol] ?? '';
        let valB = b[currentSurveySortCol] ?? '';

        if (currentSurveySortCol === 'id') {
            valA = parseInt(valA, 10) || 0;
            valB = parseInt(valB, 10) || 0;
        } else {
            valA = String(valA).toLowerCase();
            valB = String(valB).toLowerCase();
        }

        if (valA < valB) return currentSurveySortDir === 'asc' ? -1 : 1;
        if (valA > valB) return currentSurveySortDir === 'asc' ? 1 : -1;
        return 0;
    });

    // Update column sort icons
    const cols = ['id', 'client', 'location', 'device_name', 'device_type', 'creation_date'];
    cols.forEach(col => {
        const iconEl = document.getElementById(`sort_icon_${col}`);
        if (iconEl) {
            if (col === currentSurveySortCol) {
                iconEl.className = currentSurveySortDir === 'asc' ? 'fas fa-sort-up text-primary ml-1' : 'fas fa-sort-down text-primary ml-1';
            } else {
                iconEl.className = 'fas fa-sort text-muted opacity-50 ml-1';
            }
        }
    });

    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted"><i class="fas fa-search mr-2"></i> No se encontraron levantamientos que coincidan con los criterios.</td></tr>';
        const countBadge = document.getElementById('survey_count_badge');
        if (countBadge) countBadge.textContent = `0 / ${cachedManualSurveys.length}`;
        updateBatchActionBar();
        return;
    }

    let html = '';
    filtered.forEach(item => {
        const isPP = (item.device_type === 'Patch Panel' || (item.device_type && item.device_type.toLowerCase().includes('patch')));
        const typeBadgeClass = isPP ? 'badge-dev-patch' : (item.device_type === 'Router' ? 'badge-dev-router' : 'badge-dev-switch');
        const itemId = Number(item.id);
        const isChecked = selectedSurveyIds.has(itemId);
        const rowClass = isChecked ? 'row-selected' : '';
        const serialVal = item.serial || item.device_label || '';
        const vendorVal = item.vendor || '';
        const metaSub = (vendorVal || serialVal) ? `
            <div class="mt-1 d-flex flex-wrap align-items-center" style="gap: 4px; font-size: 0.74rem;">
                ${vendorVal ? `<span class="badge badge-light border text-dark py-0 px-1" title="Fabricante"><i class="fas fa-industry text-primary mr-1"></i>${escapeHtml(vendorVal)}</span>` : ''}
                ${serialVal ? `<span class="badge badge-light border text-muted py-0 px-1" title="Número de Serie"><i class="fas fa-barcode text-secondary mr-1"></i>${escapeHtml(serialVal)}</span>` : ''}
            </div>` : '';
        
        html += `
            <tr class="${rowClass}" id="survey_row_${itemId}">
                <td class="text-center"><span class="badge badge-dark px-2 py-1 font-weight-bold">#${item.id}</span></td>
                <td><strong class="text-dark"><i class="fas fa-building text-muted mr-1"></i> ${escapeHtml(item.client || '-')}</strong></td>
                <td><span class="text-secondary"><i class="fas fa-map-marker-alt text-danger mr-1"></i> ${escapeHtml(item.location || '-')}</span></td>
                <td>
                    <span class="badge ${isPP ? 'bg-passive text-white' : 'bg-navy text-white'} font-weight-bold py-1 px-2"><i class="fas ${isPP ? 'fa-ethernet' : 'fa-server'} mr-1"></i> ${escapeHtml(item.device_name || '-')}</span>
                    ${metaSub}
                </td>
                <td><span class="badge ${typeBadgeClass}">${escapeHtml(item.device_type || 'Hardware')}</span></td>
                <td class="text-center"><span class="badge badge-light border text-dark font-weight-normal"><i class="far fa-calendar-alt text-primary mr-1"></i> ${escapeHtml(item.creation_date || '-')}</span></td>
                <td class="text-center">
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-outline-primary font-weight-bold" onclick="loadManualSurveyDetail(${item.id})" title="Editar"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-outline-danger font-weight-bold" onclick="exportPmSurveyPDF(${item.id})" title="Exportar PDF"><i class="fas fa-file-pdf"></i></button>
                        <button class="btn btn-outline-success font-weight-bold" onclick="exportPmSurveyExcel(${item.id})" title="Exportar Excel"><i class="fas fa-file-excel"></i></button>
                        <button class="btn btn-outline-secondary font-weight-bold" onclick="deletePmSurvey(${item.id})" title="Eliminar"><i class="fas fa-trash"></i></button>
                    </div>
                </td>
                <td class="text-center">
                    <div class="custom-control custom-checkbox d-inline-block">
                        <input type="checkbox" class="custom-control-input chk-survey-item" id="chk_survey_${item.id}" value="${item.id}" ${isChecked ? 'checked' : ''} onchange="onSurveyCheckboxChange(this, ${item.id})">
                        <label class="custom-control-label cursor-pointer" for="chk_survey_${item.id}"></label>
                    </div>
                </td>
            </tr>
        `;
    });
    tbody.innerHTML = html;

    const countBadge = document.getElementById('survey_count_badge');
    if (countBadge) {
        if (clientUrlParam || currentSurveySearchQuery) {
            countBadge.textContent = `${filtered.length} / ${cachedManualSurveys.length}`;
        } else {
            countBadge.textContent = `${filtered.length}`;
        }
    }

    updateBatchActionBar();
}

function onSurveyCheckboxChange(chk, id) {
    id = Number(id);
    if (chk.checked) {
        selectedSurveyIds.add(id);
    } else {
        selectedSurveyIds.delete(id);
    }
    const row = document.getElementById(`survey_row_${id}`);
    if (row) {
        if (chk.checked) {
            row.classList.add('row-selected');
        } else {
            row.classList.remove('row-selected');
        }
    }
    updateBatchActionBar();
}

function toggleSelectAllSurveys(masterChk) {
    const isChecked = masterChk.checked;
    const checkboxes = document.querySelectorAll('.chk-survey-item');
    checkboxes.forEach(chk => {
        chk.checked = isChecked;
        const id = Number(chk.value);
        if (isChecked) {
            selectedSurveyIds.add(id);
        } else {
            selectedSurveyIds.delete(id);
        }
        const row = document.getElementById(`survey_row_${id}`);
        if (row) {
            if (isChecked) {
                row.classList.add('row-selected');
            } else {
                row.classList.remove('row-selected');
            }
        }
    });
    updateBatchActionBar();
}

function deselectAllSurveys() {
    selectedSurveyIds.clear();
    const masterChk = document.getElementById('chk_select_all_surveys');
    if (masterChk) {
        masterChk.checked = false;
        masterChk.indeterminate = false;
    }
    const checkboxes = document.querySelectorAll('.chk-survey-item');
    checkboxes.forEach(chk => {
        chk.checked = false;
        const row = document.getElementById(`survey_row_${chk.value}`);
        if (row) row.classList.remove('row-selected');
    });
    updateBatchActionBar();
}

function updateBatchActionBar() {
    const count = selectedSurveyIds.size;
    const batchBar = document.getElementById('pm_surveys_batch_bar');
    const floatingBar = document.getElementById('pm_floating_batch_bar');
    const lblCount = document.getElementById('lbl_selected_surveys_count');
    const lblFloatingCount = document.getElementById('lbl_floating_count');
    const masterChk = document.getElementById('chk_select_all_surveys');

    if (lblCount) lblCount.textContent = count;
    if (lblFloatingCount) lblFloatingCount.textContent = count;

    if (count > 0) {
        if (batchBar) {
            batchBar.classList.remove('d-none');
            batchBar.classList.add('d-flex');
        }
        if (floatingBar) {
            floatingBar.classList.remove('d-none');
        }
    } else {
        if (batchBar) {
            batchBar.classList.remove('d-flex');
            batchBar.classList.add('d-none');
        }
        if (floatingBar) {
            floatingBar.classList.add('d-none');
        }
    }

    if (masterChk) {
        const itemCheckboxes = document.querySelectorAll('.chk-survey-item');
        if (itemCheckboxes.length > 0) {
            const allChecked = Array.from(itemCheckboxes).every(c => c.checked);
            const someChecked = Array.from(itemCheckboxes).some(c => c.checked);
            if (allChecked) {
                masterChk.checked = true;
                masterChk.indeterminate = false;
            } else if (someChecked) {
                masterChk.checked = false;
                masterChk.indeterminate = true;
            } else {
                masterChk.checked = false;
                masterChk.indeterminate = false;
            }
        } else {
            masterChk.checked = false;
            masterChk.indeterminate = false;
        }
    }
}

async function bulkDeleteSelectedSurveys() {
    const count = selectedSurveyIds.size;
    if (count === 0) {
        toastr.warning('No ha seleccionado ningún levantamiento para eliminar.');
        return;
    }

    const confirm = await Swal.fire({
        title: `¿Eliminar ${count} levantamiento${count > 1 ? 's' : ''}?`,
        html: `<p>Se eliminarán permanentemente <strong>${count}</strong> levantamientos seleccionados y todos sus detalles/puertos asociados.</p><p class="text-danger font-weight-bold mb-0"><i class="fas fa-exclamation-triangle mr-1"></i> Esta acción no se puede deshacer.</p>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: `<i class="fas fa-trash-alt mr-1"></i> Sí, eliminar ${count}`,
        cancelButtonText: 'Cancelar'
    });

    if (confirm.isConfirmed) {
        try {
            Swal.fire({
                title: 'Eliminando...',
                text: 'Por favor espere mientras se eliminan los registros.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const idsArray = Array.from(selectedSurveyIds);
            const data = new FormData();
            data.append('action', 'bulk_delete_manual_surveys');
            data.append('ids', JSON.stringify(idsArray));

            const resp = await fetch('api_portmapping.php', { method: 'POST', body: data });
            const res = await resp.json();

            Swal.close();

            if (res.success) {
                toastr.success(res.message || `Se eliminaron ${res.deleted_count || count} levantamientos correctamente.`);
                selectedSurveyIds.clear();
                updateBatchActionBar();
                loadManualSurveys();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: res.error || 'No se pudieron eliminar los levantamientos.'
                });
            }
        } catch (err) {
            Swal.close();
            toastr.error('Error de comunicación con el servidor al eliminar los levantamientos.');
        }
    }
}

function sortSurveysByColumn(col) {
    if (currentSurveySortCol === col) {
        currentSurveySortDir = (currentSurveySortDir === 'asc') ? 'desc' : 'asc';
    } else {
        currentSurveySortCol = col;
        currentSurveySortDir = 'asc';
    }
    renderManualSurveysTable();
}

function filterAndSortSurveysTable() {
    const input = document.getElementById('pm_survey_search_input');
    if (input) {
        currentSurveySearchQuery = input.value;
    }
    renderManualSurveysTable();
}

function clearPmSurveySearch() {
    const input = document.getElementById('pm_survey_search_input');
    if (input) {
        input.value = '';
    }
    currentSurveySearchQuery = '';
    renderManualSurveysTable();
}

function switchToNewPmSurveyTab() {
    activatePmTab('survey-wizard-content');
    openNewPmSurveyModal();
}

async function fetchCachedManualSurveys() {
    try {
        const resp = await fetch('api_portmapping.php?action=get_manual_surveys');
        const res = await resp.json();
        if (res.success && res.data) {
            cachedManualSurveys = res.data;
            renderPmPortsTable();
        }
    } catch(e) {}
}

// 2. ABRIR FORMULARIO NUEVO LEVANTAMIENTO
function openNewPmSurveyModal() {
    const idEl = document.getElementById('pm_survey_id');
    if (idEl) idEl.value = '';
    
    const formEl = document.getElementById('form_pm_survey');
    if (formEl) formEl.reset();
    
    pmSurveyPorts = [];
    pmSurveyImages = [];
    pmSurveyConfigFiles = [];
    pmSurveyDiagram = null;
    
    if (!cachedManualSurveys || cachedManualSurveys.length === 0) {
        fetchCachedManualSurveys();
    }
    
    const imgPreview = document.getElementById('pm_image_preview_container');
    if (imgPreview) imgPreview.innerHTML = '';

    renderPmConfigFilesList();
    renderPmDiagramPreview();
    
    const wizardCard = document.getElementById('pm_survey_wizard_card');
    if (wizardCard) wizardCard.style.display = 'block';

    const dateEl = document.getElementById('pm-survey-date');
    if (dateEl) dateEl.value = new Date().toISOString().slice(0, 10);
    
    // Auto-fill client if cliente URL parameter is set, defaulting to VILASECA
    const urlParams = new URLSearchParams(window.location.search);
    const clientUrlParam = urlParams.get('cliente');
    const clientInput = document.getElementById('pm-survey-client');
    if (clientInput) {
        clientInput.value = clientUrlParam || 'VILASECA';
    }
    
    renderPmPortsTable();
    updatePatchPanelFormVisibility();
    
    const tabEl = document.getElementById('tab-survey-wizard');
    if (tabEl && window.jQuery && typeof $(tabEl).tab === 'function') {
        $(tabEl).tab('show');
    }
    
    goToPmSurveyStep(1);
}

function cancelPmSurvey() {
    const tabListEl = document.getElementById('tab-survey-list');
    if (tabListEl) {
        if (window.jQuery && typeof $(tabListEl).tab === 'function') {
            $(tabListEl).tab('show');
        } else {
            tabListEl.click();
        }
    }
    loadManualSurveys();
}

// 3. CAMBIO DE PASOS Y MANEJO DE MATRIZ
function goToPmSurveyStep(step) {
    for (let i = 1; i <= 7; i++) {
        const stepEl = document.getElementById(`pm_step${i}`);
        const indEl = document.getElementById(`pm_ind_step${i}`);
        if (stepEl) stepEl.style.display = (i === step) ? 'block' : 'none';
        if (indEl) {
            if (i === step) {
                indEl.className = 'step-item active';
            } else if (i < step) {
                indEl.className = 'step-item completed';
            } else {
                indEl.className = 'step-item';
            }
        }
    }

    if (step === 2) {
        // Paso 2: Asignación de Puertos
        syncPortsFromStep1(false);
    }
    if (step === 4) {
        // Paso 4: Visualización Gráfica del Chasis y Caminos
        renderPmStep4GraphicAndSummary();
    }
    if (step === 5) {
        // Paso 5: Archivo de configuraciones
        renderPmConfigFilesList();
    }
    if (step === 6) {
        // Paso 6: Diagrama
        loadVisioModelsList();
        renderPmDiagramPreview();
    }
    if (step === 7) {
        // Paso 7: Configuración de Informe, Previsualización y Generación de PDF
        renderPmStep5ReportAndPreview();
    }
}

function sanitizeNumericPort(val, fallbackIndex) {
    if (val === null || val === undefined) return String(fallbackIndex || 1);
    let clean = String(val).replace(/[^0-9]/g, '');
    if (clean === '') return String(fallbackIndex || 1);
    let num = parseInt(clean, 10);
    if (num > 48 || num >= 1000) {
        num = fallbackIndex || 1;
    }
    return String(num);
}

// 4. MANEJO DE FOTOGRAFÍAS EN PASO 3 (TAG AUTOMÁTICO DE FECHA + TAGS EN BULK)
function handlePmImageSelect(e) {
    const files = e.target.files;
    const dateTag = document.getElementById('pm-survey-date').value || new Date().toISOString().slice(0, 10);
    const rawBulkTags = document.getElementById('pm-bulk-image-tags').value || '';
    const userTags = rawBulkTags.split(',').map(t => t.trim()).filter(t => t.length > 0);
    const defaultTags = Array.from(new Set([dateTag, ...userTags]));

    Array.from(files).forEach(file => {
        const reader = new FileReader();
        reader.onload = function(evt) {
            const imgData = {
                file: file,
                src: evt.target.result,
                title: file.name,
                tags: [...defaultTags]
            };
            pmSurveyImages.push(imgData);
            renderPmImagesPreview();
        };
        reader.readAsDataURL(file);
    });
}

function renderPmImagesPreview() {
    const container = document.getElementById('pm_image_preview_container');
    if (!container) return;
    let html = '';
    const dateTag = document.getElementById('pm-survey-date').value || new Date().toISOString().slice(0, 10);

    // Opciones base para selección de puerto asociado
    let basePortOptions = `<option value="">-- Sin puerto asociado --</option>`;
    basePortOptions += `<option value="General / Rack">General / Vista de Rack</option>`;
    basePortOptions += `<option value="Patch Panel">Patch Panel</option>`;
    pmSurveyPorts.forEach((p, pIdx) => {
        const pNum = sanitizeNumericPort(p.port_name, pIdx + 1);
        const pVal = `Puerto ${pNum}`;
        const pLabel = `Puerto ${pNum}` + (p.nomenclature ? ` (${p.nomenclature})` : '');
        basePortOptions += `<option value="${pVal}">${pLabel}</option>`;
    });

    pmSurveyImages.forEach((img, idx) => {
        let tagsList = Array.isArray(img.tags) ? img.tags : [];
        if (!tagsList.includes(dateTag)) {
            tagsList.unshift(dateTag);
        }
        img.tags = tagsList;

        let assocPortBadge = img.associated_port ? 
            `<span class="badge badge-info mr-1 py-1 px-2 mb-1" style="font-size:11px;"><i class="fas fa-plug mr-1"></i>${img.associated_port}</span>` : '';

        let badgesHtml = assocPortBadge + tagsList.map((t) => {
            if (t === dateTag) {
                return `<span class="badge badge-success mr-1 py-1 px-2 mb-1" style="font-size:11px;"><i class="fas fa-calendar-alt mr-1"></i>${t}</span>`;
            }
            return `<span class="badge badge-primary mr-1 py-1 px-2 mb-1" style="font-size:11px;"><i class="fas fa-tag mr-1"></i>${t}</span>`;
        }).join('');

        let currentOptions = basePortOptions;
        if (img.associated_port) {
            currentOptions = currentOptions.replace(`value="${img.associated_port}"`, `value="${img.associated_port}" selected`);
        }

        html += `
            <div class="col-md-3 mb-3">
                <div class="card shadow-xs border" style="border-radius:8px; overflow:hidden;">
                    <div style="position:relative;">
                        <img src="${img.src || img.path}" class="card-img-top" style="height: 140px; object-fit: cover;">
                        <span class="badge badge-dark" style="position:absolute; top:6px; right:6px; opacity:0.8;">#${idx + 1}</span>
                    </div>
                    <div class="card-body p-2 bg-light">
                        <small class="text-truncate d-block font-weight-bold mb-1 text-navy">${img.title || 'Foto ' + (idx + 1)}</small>
                        <div class="mb-2" style="min-height: 26px;">${badgesHtml}</div>
                        <div class="form-group mb-2">
                            <label class="font-weight-bold small text-navy mb-1" style="font-size:10px;"><i class="fas fa-plug text-info mr-1"></i> Puerto Asociado:</label>
                            <select class="custom-select custom-select-sm font-weight-bold text-navy border-info" onchange="updateImageAssociatedPort(${idx}, this.value)">
                                ${currentOptions}
                            </select>
                        </div>
                        <input type="text" class="form-control form-control-sm mb-2" value="${tagsList.join(', ')}" placeholder="Tags (separados por coma)" onchange="updateImageTags(${idx}, this.value)">
                        <button type="button" class="btn btn-xs btn-outline-danger btn-block font-weight-bold" onclick="removePmImage(${idx})"><i class="fas fa-trash mr-1"></i> Eliminar</button>
                    </div>
                </div>
            </div>
        `;
    });

    if (pmSurveyImages.length === 0) {
        html = '<div class="col-12 text-center text-muted py-4"><i class="fas fa-images fa-2x mb-2 d-block"></i>No se han adjuntado imágenes. Suba fotografías en el control superior.</div>';
    }
    container.innerHTML = html;
}

function updateImageAssociatedPort(idx, portVal) {
    if (pmSurveyImages[idx]) {
        pmSurveyImages[idx].associated_port = portVal;
        renderPmImagesPreview();
    }
}

function updateImageTags(idx, tagsString) {
    if (pmSurveyImages[idx]) {
        const dateTag = document.getElementById('pm-survey-date').value || new Date().toISOString().slice(0, 10);
        let parsed = tagsString.split(',').map(t => t.trim()).filter(t => t.length > 0);
        if (!parsed.includes(dateTag)) {
            parsed.unshift(dateTag);
        }
        pmSurveyImages[idx].tags = Array.from(new Set(parsed));
        renderPmImagesPreview();
    }
}

function removePmImage(idx) {
    pmSurveyImages.splice(idx, 1);
    renderPmImagesPreview();
}

// 5. SINCRONIZACIÓN DE MATRIZ DE PUERTOS (4 DIVISIONES) CON DATOS DEL PASO 1
function syncPortsFromStep1(forceRebuild = false) {
    let count = parseInt(document.getElementById('pm-survey-ports-count').value, 10);
    if (isNaN(count) || count < 1) count = 1;
    if (count > 96) {
        count = 96;
        document.getElementById('pm-survey-ports-count').value = 96;
    }
    const rackSrc = document.getElementById('pm-survey-rack').value.trim() || 'RACK-A01';
    const locSrc = document.getElementById('pm-survey-location').value.trim() || 'Datacenter';
    const devSrc = document.getElementById('pm-survey-device').value.trim() || 'SW-CORE-01';
    const vendorSrc = document.getElementById('pm-survey-vendor').value.trim() || 'Cisco';
    const devTypeSrc = document.getElementById('pm-survey-device-type').value.trim() || 'Switch';
    const serialSrc = document.getElementById('pm-survey-serial').value.trim() || 'N/A';
    const urSrc = document.getElementById('pm-survey-ur').value.trim() || '12';
    const hostSrc = document.getElementById('pm-survey-hostname').value.trim() || '';

    if (pmSurveyPorts.length > 0 && !forceRebuild) {
        pmSurveyPorts.forEach((p, idx) => {
            p.rack_src = rackSrc;
            p.location_src = locSrc;
            p.device_src = devSrc;
            p.vendor_src = vendorSrc;
            p.model_src = devTypeSrc;
            p.serial_src = serialSrc;
            p.ur_src = urSrc;
            p.hostname_src = hostSrc;
            // Garantizar puerto origen numérico
            p.port_name = sanitizeNumericPort(p.port_name, idx + 1);
        });
    }

    if (forceRebuild || pmSurveyPorts.length === 0 || pmSurveyPorts.length !== count) {
        pmSurveyPorts = [];
        for (let i = 1; i <= count; i++) {
            pmSurveyPorts.push({
                rack_src: rackSrc,
                location_src: locSrc,
                device_src: devSrc,
                vendor_src: vendorSrc,
                model_src: devTypeSrc,
                serial_src: serialSrc,
                ur_src: urSrc,
                hostname_src: hostSrc,
                port_name: String(i), // PUERTO ORIGEN SOLO NÚMERO
                nomenclature: '', // NOMENCLATURA DEL PUERTO (TEXTO LIBRE A RECORDAR/ALMACENAR)
                cable_type: 'UTP Cat6A',
                connector_type: 'RJ45',
                speed: '1 Gbps',
                link_status: 'Down', // INICIALMENTE DOWN HASTA IN GRESAR DATOS
                sfp_installed: 'No',
                transceiver_type: 'N/A',

                patch_panel_src: '',
                ur_pp_src: '',
                mod_pp_src: '',
                port_pp_src: '',

                patch_panel_tgt: '',
                ur_pp_tgt: '',
                mod_pp_tgt: '',
                port_pp_tgt: '',

                rack_tgt: '',
                dest_device: '',
                vendor_tgt: '',
                model_tgt: '',
                serial_tgt: '',
                ur_tgt: '',
                hostname_tgt: '',
                dest_port: ''
            });
        }
        if (forceRebuild) toastr.success(`Matriz sincronizada con ${count} puertos (numeración 1 a ${count}). Todos los puertos inician en Down.`);
    }

    renderPmPortsTable();
}

function autoGenerateNumericPortNames() {
    pmSurveyPorts.forEach((p, idx) => {
        p.port_name = String(idx + 1);
    });
    renderPmPortsTable();
    toastr.info('Puerto Origen configurado estrictamente con números (1, 2, 3...).');
}

function autoFillDestPortsCisco() {
    pmSurveyPorts.forEach((p, idx) => {
        p.dest_port = `Gi1/0/${idx + 1}`;
    });
    renderPmPortsTable();
    toastr.info('Puerto Destino autocompletado con nomenclatura Cisco (Gi1/0/x). Puerto Origen permanece numérico.');
}

function addSinglePortToMatrix() {
    const idx = pmSurveyPorts.length + 1;
    const rackSrc = document.getElementById('pm-survey-rack').value.trim() || 'RACK-A01';
    const locSrc = document.getElementById('pm-survey-location').value.trim() || 'Datacenter';
    const devSrc = document.getElementById('pm-survey-device').value.trim() || 'SW-CORE-01';
    const devTypeSrc = document.getElementById('pm-survey-device-type').value.trim() || 'Switch';
    const vendorSrc = document.getElementById('pm-survey-vendor').value.trim() || 'Cisco';
    const serialSrc = document.getElementById('pm-survey-serial').value.trim() || 'N/A';
    const urSrc = document.getElementById('pm-survey-ur').value.trim() || '12';
    const hostSrc = document.getElementById('pm-survey-hostname').value.trim() || '';

    pmSurveyPorts.push({
        rack_src: rackSrc,
        location_src: locSrc,
        device_src: devSrc,
        vendor_src: vendorSrc,
        model_src: devTypeSrc,
        serial_src: serialSrc,
        ur_src: urSrc,
        hostname_src: hostSrc,
        port_name: String(idx), // STRICTLY NUMERIC
        nomenclature: '',
        cable_type: 'UTP Cat6A',
        connector_type: 'RJ45',
        speed: '1 Gbps',
        link_status: 'Down',
        sfp_installed: 'No',
        transceiver_type: 'N/A',
        patch_panel_src: '',
        ur_pp_src: '',
        mod_pp_src: '',
        port_pp_src: '',
        patch_panel_tgt: '',
        ur_pp_tgt: '',
        mod_pp_tgt: '',
        port_pp_tgt: '',
        rack_tgt: '',
        dest_device: '',
        vendor_tgt: '',
        model_tgt: '',
        serial_tgt: '',
        ur_tgt: '',
        hostname_tgt: '',
        dest_port: ''
    });

    renderPmPortsTable();
    toastr.success('Puerto individual agregado a la matriz (Estado inicial Down).');
}

function updatePmPortField(idx, field, value) {
    if (pmSurveyPorts[idx]) {
        pmSurveyPorts[idx][field] = value;
    }
}

function removePmPort(idx) {
    pmSurveyPorts.splice(idx, 1);
    renderPmPortsTable();
}

// PUNTO 1: HANDLER PARA SELECCIÓN DE PATCH PANEL DESTINO
function handlePpDestinoSelectChange(idx, selectEl) {
    const selectedValue = selectEl.value;
    updatePmPortField(idx, 'patch_panel_tgt', selectedValue);

    const opt = selectEl.options[selectEl.selectedIndex];
    if (opt && selectedValue !== '') {
        const urVal = opt.getAttribute('data-ur');
        const maxPorts = opt.getAttribute('data-ports') || 48;

        if (urVal) {
            updatePmPortField(idx, 'ur_pp_tgt', urVal);
            const urInput = document.getElementById(`pp_ur_tgt_${idx}`);
            if (urInput) urInput.value = urVal;
        }

        // Actualizar opciones del selector de puerto de destino
        const portSelect = document.getElementById(`pp_port_tgt_${idx}`);
        if (portSelect) {
            let opts = `<option value="">-- N° --</option>`;
            const maxLp = parseInt(maxPorts) || 48;
            for (let i = 1; i <= maxLp; i++) {
                const isSel = (parseInt(pmSurveyPorts[idx]?.port_pp_tgt) === i);
                opts += `<option value="${i}" ${isSel ? 'selected' : ''}>${i}</option>`;
            }
            portSelect.innerHTML = opts;
        }
    }
}

// 6. RENDERIZAR MATRIZ DE PUERTOS EN 4 DIVISIONES O MODO PASIVO (PUNTO 1)
function renderPmPortsTable() {
    const container = document.getElementById('pm-survey-ports-preview');
    if (!container) return;

    if (pmSurveyPorts.length === 0) {
        container.innerHTML = '<div class="alert alert-light border text-center py-4 shadow-xs" style="border-radius: 8px;"><i class="fas fa-info-circle text-info mr-2"></i> No hay puertos en la matriz. Utilice el botón superior para regenerar.</div>';
        return;
    }

    const rackSrc = document.getElementById('pm-survey-rack').value.trim() || 'RACK-A01';
    const locSrc = document.getElementById('pm-survey-location').value.trim() || 'Datacenter';
    const devSrc = document.getElementById('pm-survey-device').value.trim() || 'SW-CORE-01';
    const vendorSrc = document.getElementById('pm-survey-vendor').value.trim() || 'Cisco';
    const devTypeSrc = document.getElementById('pm-survey-device-type').value.trim() || 'Switch';
    const serialSrc = document.getElementById('pm-survey-serial').value.trim() || 'N/A';
    const urSrc = document.getElementById('pm-survey-ur').value.trim() || '12';
    const hostSrc = document.getElementById('pm-survey-hostname').value.trim() || 'N/A';

    const isPatchPanel = (devTypeSrc === 'Patch Panel' || devTypeSrc.toLowerCase().includes('patch'));

    let html = `
        <div class="card mb-3 border-0 shadow-sm text-white" style="border-radius: 10px; background: linear-gradient(135deg, #002B49 0%, #004080 100%);">
            <div class="card-body p-3">
                <div class="row align-items-center">
                    <div class="col-md-3 border-right border-secondary">
                        <small class="text-uppercase text-cyan font-weight-bold d-block mb-1" style="color: #00B8D4; letter-spacing: 0.5px;">Equipamiento Origen (Paso 1)</small>
                        <h6 class="m-0 font-weight-bold text-white"><i class="fas ${isPatchPanel ? 'fa-ethernet' : 'fa-server'} mr-2 text-warning"></i>${devSrc}</h6>
                        <small class="text-white-50">${devTypeSrc} &bull; ${vendorSrc}</small>
                    </div>
                    <div class="col-md-3 border-right border-secondary">
                        <small class="text-uppercase text-cyan font-weight-bold d-block mb-1" style="color: #00B8D4; letter-spacing: 0.5px;">Ubicación & Rack</small>
                        <span class="badge badge-light text-navy font-weight-bold mr-1"><i class="fas fa-map-marker-alt text-primary mr-1"></i>${locSrc}</span>
                        <span class="badge badge-warning text-dark font-weight-bold"><i class="fas fa-cubes mr-1"></i>${rackSrc} (UR: ${urSrc})</span>
                    </div>
                    <div class="col-md-3 border-right border-secondary">
                        <small class="text-uppercase text-cyan font-weight-bold d-block mb-1" style="color: #00B8D4; letter-spacing: 0.5px;">Identificación Técnica</small>
                        <small class="d-block text-white"><strong>Serie:</strong> ${serialSrc}</small>
                        <small class="d-block text-white-50"><strong>Hostname:</strong> ${hostSrc}</small>
                    </div>
                    <div class="col-md-3 text-right">
                        <span class="badge badge-success px-3 py-2 font-weight-bold shadow-xs" style="font-size: 12px; background-color: #28a745;"><i class="fas fa-plug mr-1"></i> ${pmSurveyPorts.length} Puertos Mapeados</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive shadow-sm" style="border-radius: 8px; border: 1px solid #cbd5e1; max-height: 580px; overflow-y: auto;">
            <table class="table table-bordered table-hover align-middle mb-0" style="font-size: 11px; white-space: nowrap;">
                <thead style="position: sticky; top: 0; z-index: 10;">
                    <tr style="font-size: 11px; font-weight: bold; text-transform: uppercase;">
`;

    if (isPatchPanel) {
        html += `
                        <th colspan="4" class="text-white text-center py-2" style="background: linear-gradient(135deg, #581c87, #7e22ce); border-right: 3px solid #64748b !important;">
                            <i class="fas fa-network-wired mr-1"></i> 2. PATCH PANEL ORIGEN
                        </th>
                        <th colspan="4" class="text-white text-center py-2" style="background: linear-gradient(135deg, #0e7490, #0369a1); border-right: 3px solid #64748b !important;">
                            <i class="fas fa-network-wired mr-1"></i> 3. PATCH PANEL DESTINO
                        </th>
                        <th rowspan="2" class="text-center bg-dark text-white align-middle" style="width:40px;">Acc.</th>
                    </tr>
                    <tr class="bg-light text-navy font-weight-bold text-center">
                        <th style="background-color: #f3ebff; color: #5a32a3;">PP Origen</th>
                        <th style="background-color: #f3ebff; color: #5a32a3;">UR (1-48)</th>
                        <th style="background-color: #f3ebff; color: #5a32a3;">Etiqueta</th>
                        <th style="background-color: #f3ebff; color: #5a32a3; border-right: 3px solid #64748b !important;">Puerto PP (1-48)</th>

                        <th style="background-color: #e6f7f9; color: #007a87;">PP Destino</th>
                        <th style="background-color: #e6f7f9; color: #007a87;">UR (1-48)</th>
                        <th style="background-color: #e6f7f9; color: #007a87;">Etiqueta</th>
                        <th style="background-color: #e6f7f9; color: #007a87; border-right: 3px solid #64748b !important;">Puerto PP (1-48)</th>
                    </tr>
        `;
    } else {
        html += `
                        <th colspan="16" class="text-white text-center py-2" style="background: linear-gradient(135deg, #0f172a, #1e293b); border-right: 3px solid #64748b !important;">
                            <i class="fas fa-server mr-1"></i> 1. EQUIPAMIENTO ORIGEN (PASO 1 + CONEXIÓN)
                        </th>
                        <th colspan="4" class="text-white text-center py-2" style="background: linear-gradient(135deg, #581c87, #7e22ce); border-right: 3px solid #64748b !important;">
                            <i class="fas fa-network-wired mr-1"></i> 2. PATCH PANEL ORIGEN
                        </th>
                        <th colspan="4" class="text-white text-center py-2" style="background: linear-gradient(135deg, #0e7490, #0369a1); border-right: 3px solid #64748b !important;">
                            <i class="fas fa-network-wired mr-1"></i> 3. PATCH PANEL DESTINO
                        </th>
                        <th colspan="8" class="text-white text-center py-2" style="background: linear-gradient(135deg, #15803d, #166534); border-right: 3px solid #64748b !important;">
                            <i class="fas fa-desktop mr-1"></i> 4. EQUIPAMIENTO DESTINO
                        </th>
                        <th rowspan="2" class="text-center bg-dark text-white align-middle" style="width:40px;">Acc.</th>
                    </tr>
                    <tr class="bg-light text-navy font-weight-bold text-center">
                        <th>Rack Orig.</th>
                        <th>Ubicación</th>
                        <th>Equipo Origen</th>
                        <th>Fabricante</th>
                        <th>Modelo</th>
                        <th>Serie</th>
                        <th>UR</th>
                        <th>Hostname</th>
                        <th style="background-color: #fff3cd; color: #856404; border-bottom: 2px solid #ffebaa;">Puerto Origen (Sólo N°)</th>
                        <th style="background-color: #e2e8f0; color: #1e293b; border-bottom: 2px solid #cbd5e1;">Nomenclatura</th>
                        <th>Tipo Cable</th>
                        <th>Tipo Conector</th>
                        <th>Velocidad</th>
                        <th>Estado Link</th>
                        <th>SFP</th>
                        <th style="border-right: 3px solid #64748b !important;">Transceiver</th>

                        <th style="background-color: #f3ebff; color: #5a32a3;">PP Origen</th>
                        <th style="background-color: #f3ebff; color: #5a32a3;">UR (1-48)</th>
                        <th style="background-color: #f3ebff; color: #5a32a3;">Etiqueta</th>
                        <th style="background-color: #f3ebff; color: #5a32a3; border-right: 3px solid #64748b !important;">Puerto PP (1-48)</th>

                        <th style="background-color: #e6f7f9; color: #007a87;">PP Destino</th>
                        <th style="background-color: #e6f7f9; color: #007a87;">UR (1-48)</th>
                        <th style="background-color: #e6f7f9; color: #007a87;">Etiqueta</th>
                        <th style="background-color: #e6f7f9; color: #007a87; border-right: 3px solid #64748b !important;">Puerto PP (1-48)</th>

                        <th style="background-color: #e8f5e9; color: #1e7e34;">Rack Dest.</th>
                        <th style="background-color: #e8f5e9; color: #1e7e34;">Equipo Destino</th>
                        <th style="background-color: #e8f5e9; color: #1e7e34;">Fabricante</th>
                        <th style="background-color: #e8f5e9; color: #1e7e34;">Modelo</th>
                        <th style="background-color: #e8f5e9; color: #1e7e34;">Serie</th>
                        <th style="background-color: #e8f5e9; color: #1e7e34;">UR</th>
                        <th style="background-color: #e8f5e9; color: #1e7e34;">Hostname</th>
                        <th style="background-color: #d4edda; color: #155724; font-weight:bold; border-right: 3px solid #64748b !important;">Puerto Destino</th>
                    </tr>
        `;
    }

    html += `</thead><tbody>`;

    const statusFilter = document.getElementById('pm_step2_status_filter')?.value || 'all';

    pmSurveyPorts.forEach((p, idx) => {
        const isUp = (p.link_status || 'Down') === 'Up';
        if (statusFilter === 'Up' && !isUp) return;
        if (statusFilter === 'Down' && isUp) return;

        const numPort = sanitizeNumericPort(p.port_name, idx + 1);

        // Pre-poblar origen si estamos en modo Patch Panel
        const ppSrcVal = p.patch_panel_src || devSrc;
        const urPpSrcVal = p.ur_pp_src || urSrc;
        const modPpSrcVal = p.mod_pp_src || 'M1';
        const portPpSrcVal = p.port_pp_src || numPort;

        // Construir selector dinámico de PP Destino a partir de levantamientos previos
        let ppTgtSelectHTML = `<select class="custom-select custom-select-sm p-1 font-weight-bold text-info" style="min-width:120px; border-color: #a5f3fc;" onchange="handlePpDestinoSelectChange(${idx}, this)">
            <option value="">-- Seleccionar PP --</option>`;
        
        let targetFound = false;
        let selectedMaxPorts = 48;

        if (Array.isArray(cachedManualSurveys)) {
            cachedManualSurveys.forEach(s => {
                // Filtrar para mostrar ÚNICAMENTE equipos que sean de tipo Patch Panel
                const isPP = (s.device_type === 'Patch Panel' || (s.device_type && s.device_type.toLowerCase().includes('patch')));
                if (!isPP) return;

                const sName = s.device_name || s.device_label || `PP-${s.id}`;
                const sUr = s.ur_rack || 1;
                const sPorts = s.ports_count || 48;
                const isSelected = (p.patch_panel_tgt === sName);
                if (isSelected) {
                    targetFound = true;
                    selectedMaxPorts = sPorts;
                }
                ppTgtSelectHTML += `<option value="${sName}" data-ur="${sUr}" data-ports="${sPorts}" ${isSelected ? 'selected' : ''}>${sName} (UR: ${sUr}, ${sPorts} Ports)</option>`;
            });
        }

        if (p.patch_panel_tgt && !targetFound) {
            ppTgtSelectHTML += `<option value="${p.patch_panel_tgt}" selected>${p.patch_panel_tgt} (Manual)</option>`;
        }
        ppTgtSelectHTML += `</select>`;

        // Construir selector dinámico de Puerto PP Destino (1 a maxPorts)
        let portTgtSelectHTML = `<select id="pp_port_tgt_${idx}" class="custom-select custom-select-sm p-1 text-center font-weight-bold text-info" style="min-width:65px; border-color: #a5f3fc;" onchange="updatePmPortField(${idx}, 'port_pp_tgt', this.value)">
            <option value="">-- N° --</option>`;
        const maxPortLoop = parseInt(selectedMaxPorts) || 48;
        for (let i = 1; i <= maxPortLoop; i++) {
            const isPortSel = (parseInt(p.port_pp_tgt) === i);
            portTgtSelectHTML += `<option value="${i}" ${isPortSel ? 'selected' : ''}>${i}</option>`;
        }
        portTgtSelectHTML += `</select>`;

        if (isPatchPanel) {
            html += `
                <tr style="background-color: ${idx % 2 === 0 ? '#ffffff' : '#f8fafc'};">
                    <!-- 2. PATCH PANEL ORIGEN -->
                    <td class="align-middle" style="background-color: #faf5ff;">
                        <input type="text" class="form-control form-control-sm p-1 font-weight-bold text-purple" style="min-width:110px; border-color: #c4b5fd;" value="${ppSrcVal}" onchange="updatePmPortField(${idx}, 'patch_panel_src', this.value)">
                    </td>
                    <td class="align-middle" style="background-color: #faf5ff;">
                        <input type="number" min="1" max="48" class="form-control form-control-sm p-1 text-center font-weight-bold" style="min-width:55px; border-color: #c4b5fd;" value="${urPpSrcVal}" onchange="this.value = clampPp1To48(this.value); updatePmPortField(${idx}, 'ur_pp_src', this.value)">
                    </td>
                    <td class="align-middle" style="background-color: #faf5ff;">
                        <input type="text" class="form-control form-control-sm p-1 text-center" style="min-width:55px; border-color: #c4b5fd;" value="${modPpSrcVal}" onchange="updatePmPortField(${idx}, 'mod_pp_src', this.value)">
                    </td>
                    <td class="align-middle" style="background-color: #faf5ff; border-right: 3px solid #64748b !important;">
                        <input type="number" min="1" max="48" class="form-control form-control-sm p-1 text-center font-weight-bold text-purple" style="min-width:65px; border-color: #c4b5fd;" value="${portPpSrcVal}" onchange="this.value = clampPp1To48(this.value); updatePmPortField(${idx}, 'port_pp_src', this.value)">
                    </td>

                    <!-- 3. PATCH PANEL DESTINO -->
                    <td class="align-middle" style="background-color: #f0f9ff;">
                        ${ppTgtSelectHTML}
                    </td>
                    <td class="align-middle" style="background-color: #f0f9ff;">
                        <input type="number" id="pp_ur_tgt_${idx}" min="1" max="48" class="form-control form-control-sm p-1 text-center font-weight-bold" style="min-width:55px; border-color: #a5f3fc;" value="${p.ur_pp_tgt || ''}" onchange="this.value = clampPp1To48(this.value); updatePmPortField(${idx}, 'ur_pp_tgt', this.value)">
                    </td>
                    <td class="align-middle" style="background-color: #f0f9ff;">
                        <input type="text" class="form-control form-control-sm p-1 text-center" style="min-width:55px; border-color: #a5f3fc;" value="${p.mod_pp_tgt || 'M1'}" onchange="updatePmPortField(${idx}, 'mod_pp_tgt', this.value)">
                    </td>
                    <td class="align-middle" style="background-color: #f0f9ff; border-right: 3px solid #64748b !important;">
                        ${portTgtSelectHTML}
                    </td>

                    <td class="text-center align-middle">
                        <button type="button" class="btn btn-xs btn-outline-danger" onclick="removePmPort(${idx})" title="Eliminar fila"><i class="fas fa-times"></i></button>
                    </td>
                </tr>
            `;
        } else {
            html += `
                <tr style="background-color: ${idx % 2 === 0 ? '#ffffff' : '#f8fafc'};">
                    <td class="align-middle text-center"><span class="badge badge-light border text-navy font-weight-bold py-1 px-2">${p.rack_src || rackSrc}</span></td>
                    <td class="align-middle text-center"><small class="text-secondary font-weight-bold">${p.location_src || locSrc}</small></td>
                    <td class="align-middle text-center"><strong class="text-navy">${p.device_src || devSrc}</strong></td>
                    <td class="align-middle text-center"><small class="text-dark">${p.vendor_src || vendorSrc}</small></td>
                    <td class="align-middle text-center"><small class="text-muted">${p.model_src || devTypeSrc}</small></td>
                    <td class="align-middle text-center"><small class="text-muted">${p.serial_src || serialSrc}</small></td>
                    <td class="align-middle text-center"><span class="badge badge-secondary py-1 px-2">${p.ur_src || urSrc}</span></td>
                    <td class="align-middle text-center"><small class="text-primary font-weight-bold">${p.hostname_src || hostSrc}</small></td>
                    
                    <!-- PUERTO ORIGEN: CAMPO EXCLUSIVAMENTE NUMÉRICO -->
                    <td class="align-middle">
                        <input type="number" min="1" max="96" class="form-control form-control-sm text-center font-weight-bold text-navy" style="min-width:70px; background-color:#fffdf5; border:1.5px solid #f59e0b;" value="${numPort}" placeholder="${idx + 1}" oninput="this.value = this.value.replace(/[^0-9]/g, ''); updatePmPortField(${idx}, 'port_name', this.value);">
                    </td>

                    <!-- NOMENCLATURA: CAMPO DE TEXTO EDITABLE Y GUARDABLE -->
                    <td class="align-middle">
                        <input type="text" class="form-control form-control-sm font-weight-bold text-navy p-1" style="min-width:110px;" value="${p.nomenclature || p.nomenclatura || ''}" placeholder="Ej. Gi1/0/${numPort}" oninput="updatePmPortField(${idx}, 'nomenclature', this.value);">
                    </td>

                    <td class="align-middle">
                        <select class="custom-select custom-select-sm p-1" style="min-width:95px; font-size:11px;" onchange="updatePmPortField(${idx}, 'cable_type', this.value)">
                            <option value="UTP Cat6A" ${p.cable_type === 'UTP Cat6A' ? 'selected' : ''}>Cat6A</option>
                            <option value="UTP Cat6" ${p.cable_type === 'UTP Cat6' ? 'selected' : ''}>Cat6</option>
                            <option value="Fibra OM4" ${p.cable_type === 'Fibra OM4' ? 'selected' : ''}>OM4</option>
                            <option value="Fibra OS2" ${p.cable_type === 'Fibra OS2' ? 'selected' : ''}>OS2</option>
                            <option value="DAC" ${p.cable_type === 'DAC' ? 'selected' : ''}>DAC</option>
                        </select>
                    </td>
                    <td class="align-middle">
                        <select class="custom-select custom-select-sm p-1" style="min-width:80px; font-size:11px;" onchange="updatePmPortField(${idx}, 'connector_type', this.value)">
                            <option value="RJ45" ${p.connector_type === 'RJ45' ? 'selected' : ''}>RJ45</option>
                            <option value="LC" ${p.connector_type === 'LC' ? 'selected' : ''}>LC</option>
                            <option value="SC" ${p.connector_type === 'SC' ? 'selected' : ''}>SC</option>
                            <option value="MPO" ${p.connector_type === 'MPO' ? 'selected' : ''}>MPO</option>
                            <option value="SFP+" ${p.connector_type === 'SFP+' ? 'selected' : ''}>SFP+</option>
                        </select>
                    </td>
                    <td class="align-middle">
                        <select class="custom-select custom-select-sm p-1 font-weight-bold" style="min-width:95px; font-size:11px;" onchange="updatePmPortField(${idx}, 'speed', this.value)">
                            <option value="100 Mbps" ${p.speed === '100 Mbps' ? 'selected' : ''}>100 Mbps</option>
                            <option value="1 Gbps" ${p.speed === '1 Gbps' || !p.speed ? 'selected' : ''}>1 Gbps</option>
                            <option value="2.5 Gbps" ${p.speed === '2.5 Gbps' ? 'selected' : ''}>2.5 Gbps</option>
                            <option value="5 Gbps" ${p.speed === '5 Gbps' ? 'selected' : ''}>5 Gbps</option>
                            <option value="10 Gbps" ${p.speed === '10 Gbps' ? 'selected' : ''}>10 Gbps</option>
                            <option value="25 Gbps" ${p.speed === '25 Gbps' ? 'selected' : ''}>25 Gbps</option>
                            <option value="40 Gbps" ${p.speed === '40 Gbps' ? 'selected' : ''}>40 Gbps</option>
                            <option value="100 Gbps" ${p.speed === '100 Gbps' ? 'selected' : ''}>100 Gbps</option>
                            <option value="400 Gbps" ${p.speed === '400 Gbps' ? 'selected' : ''}>400 Gbps</option>
                            <option value="Auto" ${p.speed === 'Auto' ? 'selected' : ''}>Auto</option>
                        </select>
                    </td>
                    <td class="align-middle">
                        <select class="custom-select custom-select-sm font-weight-bold p-1 ${isUp ? 'text-success' : 'text-danger'}" style="min-width:75px; font-size:11px;" onchange="updatePmPortField(${idx}, 'link_status', this.value); renderPmPortsTable();">
                            <option value="Up" ${isUp ? 'selected' : ''}>&bull; Up</option>
                            <option value="Down" ${!isUp ? 'selected' : ''}>&bull; Down</option>
                        </select>
                    </td>
                    <td class="align-middle">
                        <select class="custom-select custom-select-sm p-1" style="min-width:60px; font-size:11px;" onchange="updatePmPortField(${idx}, 'sfp_installed', this.value)">
                            <option value="No" ${p.sfp_installed === 'No' ? 'selected' : ''}>No</option>
                            <option value="Si" ${p.sfp_installed === 'Si' ? 'selected' : ''}>Sí</option>
                        </select>
                    </td>
                    <td class="align-middle" style="border-right: 3px solid #64748b !important;">
                        <select class="custom-select custom-select-sm p-1" style="min-width:85px; font-size:11px;" onchange="updatePmPortField(${idx}, 'transceiver_type', this.value)">
                            <option value="N/A" ${p.transceiver_type === 'N/A' || !p.transceiver_type ? 'selected' : ''}>N/A</option>
                            <option value="No" ${p.transceiver_type === 'No' ? 'selected' : ''}>No</option>
                            <option value="Si" ${p.transceiver_type === 'Si' ? 'selected' : ''}>Sí</option>
                            <option value="Si (SFP 1G)" ${p.transceiver_type === 'Si (SFP 1G)' ? 'selected' : ''}>Sí (SFP 1G)</option>
                            <option value="Si (SFP+ 10G)" ${p.transceiver_type === 'Si (SFP+ 10G)' ? 'selected' : ''}>Sí (SFP+ 10G)</option>
                            <option value="Si (QSFP+ 40G)" ${p.transceiver_type === 'Si (QSFP+ 40G)' ? 'selected' : ''}>Sí (QSFP+ 40G)</option>
                            <option value="Si (QSFP28 100G)" ${p.transceiver_type === 'Si (QSFP28 100G)' ? 'selected' : ''}>Sí (QSFP28 100G)</option>
                        </select>
                    </td>

                    <!-- 2. PATCH PANEL ORIGEN (UR Y PUERTO 1 A 48) -->
                    <td class="align-middle" style="background-color: #faf5ff;"><input type="text" class="form-control form-control-sm p-1 font-weight-bold text-purple" style="min-width:110px; border-color: #c4b5fd;" value="${p.patch_panel_src || ''}" onchange="updatePmPortField(${idx}, 'patch_panel_src', this.value)"></td>
                    <td class="align-middle" style="background-color: #faf5ff;"><input type="number" min="1" max="48" class="form-control form-control-sm p-1 text-center font-weight-bold" style="min-width:55px; border-color: #c4b5fd;" value="${p.ur_pp_src || ''}" onchange="this.value = clampPp1To48(this.value); updatePmPortField(${idx}, 'ur_pp_src', this.value)"></td>
                    <td class="align-middle" style="background-color: #faf5ff;"><input type="text" class="form-control form-control-sm p-1 text-center" style="min-width:55px; border-color: #c4b5fd;" value="${p.mod_pp_src || 'M1'}" onchange="updatePmPortField(${idx}, 'mod_pp_src', this.value)"></td>
                    <td class="align-middle" style="background-color: #faf5ff; border-right: 3px solid #64748b !important;"><input type="number" min="1" max="48" class="form-control form-control-sm p-1 text-center font-weight-bold text-purple" style="min-width:65px; border-color: #c4b5fd;" value="${p.port_pp_src || ''}" onchange="this.value = clampPp1To48(this.value); updatePmPortField(${idx}, 'port_pp_src', this.value)"></td>

                    <!-- 3. PATCH PANEL DESTINO (UR Y PUERTO 1 A 48) -->
                    <td class="align-middle" style="background-color: #f0f9ff;">${ppTgtSelectHTML}</td>
                    <td class="align-middle" style="background-color: #f0f9ff;"><input type="number" id="pp_ur_tgt_${idx}" min="1" max="48" class="form-control form-control-sm p-1 text-center font-weight-bold" style="min-width:55px; border-color: #a5f3fc;" value="${p.ur_pp_tgt || ''}" onchange="this.value = clampPp1To48(this.value); updatePmPortField(${idx}, 'ur_pp_tgt', this.value)"></td>
                    <td class="align-middle" style="background-color: #f0f9ff;"><input type="text" class="form-control form-control-sm p-1 text-center" style="min-width:55px; border-color: #a5f3fc;" value="${p.mod_pp_tgt || 'M1'}" onchange="updatePmPortField(${idx}, 'mod_pp_tgt', this.value)"></td>
                    <td class="align-middle" style="background-color: #f0f9ff; border-right: 3px solid #64748b !important;">${portTgtSelectHTML}</td>

                    <!-- 4. EQUIPAMIENTO DESTINO -->
                    <td class="align-middle" style="background-color: #f0fdf4;"><input type="text" class="form-control form-control-sm p-1" style="min-width:90px; border-color: #a7f3d0;" value="${p.rack_tgt || ''}" onchange="updatePmPortField(${idx}, 'rack_tgt', this.value)"></td>
                    <td class="align-middle" style="background-color: #f0fdf4;"><input type="text" class="form-control form-control-sm p-1 font-weight-bold text-success" style="min-width:110px; border-color: #a7f3d0;" value="${p.dest_device || ''}" onchange="updatePmPortField(${idx}, 'dest_device', this.value)"></td>
                    <td class="align-middle" style="background-color: #f0fdf4;"><input type="text" class="form-control form-control-sm p-1" style="min-width:80px; border-color: #a7f3d0;" value="${p.vendor_tgt || ''}" onchange="updatePmPortField(${idx}, 'vendor_tgt', this.value)"></td>
                    <td class="align-middle" style="background-color: #f0fdf4;"><input type="text" class="form-control form-control-sm p-1" style="min-width:80px; border-color: #a7f3d0;" value="${p.model_tgt || ''}" onchange="updatePmPortField(${idx}, 'model_tgt', this.value)"></td>
                    <td class="align-middle" style="background-color: #f0fdf4;"><input type="text" class="form-control form-control-sm p-1" style="min-width:90px; border-color: #a7f3d0;" value="${p.serial_tgt || ''}" onchange="updatePmPortField(${idx}, 'serial_tgt', this.value)"></td>
                    <td class="align-middle" style="background-color: #f0fdf4;"><input type="text" class="form-control form-control-sm p-1 text-center" style="min-width:55px; border-color: #a7f3d0;" value="${p.ur_tgt || ''}" onchange="updatePmPortField(${idx}, 'ur_tgt', this.value)"></td>
                    <td class="align-middle" style="background-color: #f0fdf4;"><input type="text" class="form-control form-control-sm p-1" style="min-width:100px; border-color: #a7f3d0;" value="${p.hostname_tgt || ''}" onchange="updatePmPortField(${idx}, 'hostname_tgt', this.value)"></td>
                    <td class="align-middle" style="background-color: #f0fdf4; border-right: 3px solid #64748b !important;"><input type="text" class="form-control form-control-sm p-1 font-weight-bold text-success" style="min-width:90px; border-color: #28a745; background-color:#e8f5e9;" value="${p.dest_port || ''}" onchange="updatePmPortField(${idx}, 'dest_port', this.value)"></td>

                    <td class="text-center align-middle">
                        <button type="button" class="btn btn-xs btn-outline-danger" onclick="removePmPort(${idx})" title="Eliminar fila"><i class="fas fa-times"></i></button>
                    </td>
                </tr>
            `;
        }
    });

    html += '</tbody></table></div>';
    container.innerHTML = html;
}

// 7. RENDERIZAR PASO 4: VISTA GRÁFICA DEL CHASIS + CAMINOS DE CONEXIÓN + RESUMEN DE TOPOLOGÍA
function renderPmStep4GraphicAndSummary() {
    const finalContainer = document.getElementById('pm-survey-final-preview');
    if (!finalContainer) return;

    const date = document.getElementById('pm-survey-date').value || new Date().toISOString().slice(0, 10);
    const client = document.getElementById('pm-survey-client').value || 'VILASECA';
    const location = document.getElementById('pm-survey-location').value || 'Datacenter';
    const area = document.getElementById('pm-survey-area').value || 'Telecomunicaciones';
    const device = document.getElementById('pm-survey-device').value || 'SW-CORE-01';
    const label = document.getElementById('pm-survey-device-label').value || 'LBL-01';
    const devType = document.getElementById('pm-survey-device-type').value || 'Switch';
    const rack = document.getElementById('pm-survey-rack').value || 'RACK-A01';
    const ur = document.getElementById('pm-survey-ur').value || '12';
    const vendor = document.getElementById('pm-survey-vendor').value || 'Cisco';

    const totalPorts = pmSurveyPorts.length;
    const upPorts = pmSurveyPorts.filter(p => (p.link_status || 'Down') === 'Up').length;
    const portsPerRow = totalPorts >= 8 ? Math.ceil(totalPorts / 2) : Math.max(totalPorts, 1);

    let chassisMaxWidth = '960px';
    if (totalPorts <= 8) chassisMaxWidth = '380px';
    else if (totalPorts <= 12) chassisMaxWidth = '500px';
    else if (totalPorts <= 16) chassisMaxWidth = '620px';
    else if (totalPorts <= 24) chassisMaxWidth = '780px';
    else if (totalPorts <= 48) chassisMaxWidth = '980px';
    else chassisMaxWidth = '100%';

    // 1. CHASSIS FACEPLATE PORTS GRID (COMPACTO Y CENTRADO)
    let faceplatePortsHtml = '';
    
    if (totalPorts >= 8) {
        let topRowHtml = '';
        let bottomRowHtml = '';
        
        pmSurveyPorts.forEach((p, idx) => {
            const portNum = sanitizeNumericPort(p.port_name, idx + 1);
            const isUp = (p.link_status || 'Down') === 'Up';
            const ledClass = isUp ? 'switch-led-green' : 'switch-led-off';
            const destText = p.dest_device ? `${p.dest_device} (${p.dest_port || 'N/A'})` : 'Sin Conexión';

            const cardHtml = `
                <div class="switch-port-item" id="gfx_port_item_${idx}" onclick="highlightGraphicPort(${idx})" title="Puerto ${portNum} | Estado: ${p.link_status || 'Down'} | Destino: ${destText}">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="switch-port-num">P${portNum}</span>
                        <span class="switch-led-dot ${ledClass}"></span>
                    </div>
                    <div class="switch-port-icon text-${isUp ? 'success' : 'secondary'}">
                        <i class="fas fa-ethernet"></i>
                    </div>
                </div>
            `;

            if ((idx + 1) % 2 !== 0) {
                topRowHtml += cardHtml;
            } else {
                bottomRowHtml += cardHtml;
            }
        });

        faceplatePortsHtml = `
            <div class="mb-1">
                <small class="text-uppercase font-weight-bold d-block mb-1" style="color:#64748b; font-size:9px; letter-spacing:0.5px;">Fila Superior (Puertos Impares)</small>
                <div class="switch-port-grid" style="grid-template-columns: repeat(${portsPerRow}, minmax(32px, 1fr));">
                    ${topRowHtml}
                </div>
            </div>
            <div>
                <small class="text-uppercase font-weight-bold d-block mb-1" style="color:#64748b; font-size:9px; letter-spacing:0.5px;">Fila Inferior (Puertos Pares)</small>
                <div class="switch-port-grid" style="grid-template-columns: repeat(${portsPerRow}, minmax(32px, 1fr));">
                    ${bottomRowHtml}
                </div>
            </div>
        `;
    } else {
        let singleRowHtml = '';
        pmSurveyPorts.forEach((p, idx) => {
            const portNum = sanitizeNumericPort(p.port_name, idx + 1);
            const isUp = (p.link_status || 'Down') === 'Up';
            const ledClass = isUp ? 'switch-led-green' : 'switch-led-off';

            singleRowHtml += `
                <div class="switch-port-item" id="gfx_port_item_${idx}" onclick="highlightGraphicPort(${idx})" title="Puerto ${portNum} | Estado: ${p.link_status || 'Down'}">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="switch-port-num">P${portNum}</span>
                        <span class="switch-led-dot ${ledClass}"></span>
                    </div>
                    <div class="switch-port-icon text-${isUp ? 'success' : 'secondary'}">
                        <i class="fas fa-ethernet"></i>
                    </div>
                </div>
            `;
        });

        faceplatePortsHtml = `
            <div class="switch-port-grid" style="grid-template-columns: repeat(${portsPerRow}, minmax(32px, 1fr));">
                ${singleRowHtml}
            </div>
        `;
    }

    // 2. HIGH-DENSITY MINIMALIST TOPOLOGY TABLE ROWS (SIN STRINGS DE PLACEHOLDER FALSOS)
    let topologyRowsHtml = '';
    pmSurveyPorts.forEach((p, idx) => {
        const portNum = sanitizeNumericPort(p.port_name, idx + 1);
        const isUp = (p.link_status || 'Down') === 'Up';

        const ppSrcDisplay = p.patch_panel_src ? 
            `<span class="text-purple font-weight-bold" style="font-size:11px;">${p.patch_panel_src}</span><small class="text-muted d-block" style="font-size:10px;">UR:${p.ur_pp_src || '-'} (P:${p.port_pp_src || '-'})</small>` :
            `<span class="text-muted font-italic" style="font-size:10.5px;">-</span>`;

        const ppTgtDisplay = p.patch_panel_tgt ? 
            `<span class="text-info font-weight-bold" style="font-size:11px;">${p.patch_panel_tgt}</span><small class="text-muted d-block" style="font-size:10px;">UR:${p.ur_pp_tgt || '-'} (P:${p.port_pp_tgt || '-'})</small>` :
            `<span class="text-muted font-italic" style="font-size:10.5px;">-</span>`;

        const destDeviceDisplay = p.dest_device ? 
            `<strong class="text-success">${p.dest_device}</strong><small class="text-muted d-block" style="font-size:10px;">${p.dest_port || '-'} &bull; ${p.rack_tgt || '-'}</small>` :
            `<span class="badge badge-light border text-muted" style="font-size:10px;">Libre / Sin Conexión</span>`;

        topologyRowsHtml += `
            <tr class="topology-item-row" id="topo_row_${idx}" onclick="highlightGraphicPort(${idx})" style="cursor:pointer;">
                <td class="text-center align-middle font-weight-bold"><span class="badge badge-secondary" style="font-size:10px;">P-${portNum}</span></td>
                <td class="align-middle">
                    <strong class="text-dark">${device}</strong>
                    <small class="text-muted d-block" style="font-size:10px;">UR:${ur} &bull; ${vendor}</small>
                </td>
                <td class="align-middle text-center">${ppSrcDisplay}</td>
                <td class="align-middle text-center">${ppTgtDisplay}</td>
                <td class="align-middle">${destDeviceDisplay}</td>
                <td class="text-center align-middle">
                    <span class="badge badge-${isUp ? 'success' : 'secondary'} px-2 py-1" style="font-size:10px;">${p.link_status || 'Down'}</span>
                </td>
            </tr>
        `;
    });

    // 3. EVIDENCIA FOTOGRÁFICA
    let photosHtml = '';
    if (pmSurveyImages.length > 0) {
        photosHtml = '<div class="row mt-2">';
        pmSurveyImages.forEach((img, idx) => {
            let tagsList = Array.isArray(img.tags) ? img.tags : [];
            let assocBadge = img.associated_port ? `<span class="badge badge-info mr-1" style="font-size:9px;"><i class="fas fa-plug mr-1"></i>${img.associated_port}</span>` : '';
            let tagsBadges = tagsList.map(t => `<span class="badge badge-light border text-dark mr-1" style="font-size:9px;">${t}</span>`).join('');
            photosHtml += `
                <div class="col-md-3 col-sm-6 mb-2">
                    <div class="border rounded p-1 bg-white shadow-2xs">
                        <img src="${img.src || img.path}" style="height:90px; width:100%; object-fit:cover; border-radius:4px;">
                        <div class="p-1">
                            <small class="font-weight-bold text-dark d-block text-truncate" style="font-size:10.5px;">${img.title || 'Foto ' + (idx + 1)}</small>
                            <div>${assocBadge}${tagsBadges}</div>
                        </div>
                    </div>
                </div>
            `;
        });
        photosHtml += '</div>';
    } else {
        photosHtml = '<small class="text-muted italic">Sin evidencia fotográfica adjunta.</small>';
    }

    let html = `
        <!-- BANDA DE RESUMEN METRICAS (KPI MINIMALISTA) -->
        <div class="row mb-3">
            <div class="col-md-3 col-6 mb-2">
                <div class="mini-kpi-pill">
                    <small class="text-uppercase text-muted font-weight-bold d-block" style="font-size:9.5px;">Equipo Origen</small>
                    <strong class="d-block text-truncate">${device}</strong>
                    <small class="text-muted">${devType} &bull; UR: ${ur}</small>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-2">
                <div class="mini-kpi-pill">
                    <small class="text-uppercase text-muted font-weight-bold d-block" style="font-size:9.5px;">Ubicación & Rack</small>
                    <strong class="d-block text-truncate">${location}</strong>
                    <small class="text-muted">${rack}</small>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-2">
                <div class="mini-kpi-pill">
                    <small class="text-uppercase text-muted font-weight-bold d-block" style="font-size:9.5px;">Cliente / Área</small>
                    <strong class="d-block text-truncate">${client}</strong>
                    <small class="text-muted">${area}</small>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-2">
                <div class="mini-kpi-pill">
                    <small class="text-uppercase text-muted font-weight-bold d-block" style="font-size:9.5px;">Estado de Puertos</small>
                    <strong class="text-success">${upPorts} Activos (Up)</strong> / <span class="text-muted">${totalPorts - upPorts} Down</span>
                </div>
            </div>
        </div>

        <!-- PANEL COMPACTO DE VISTA FRONTAL DEL EQUIPO -->
        <div class="card mb-3 border shadow-2xs overflow-hidden" style="border-radius:6px;">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-1 px-3">
                <div class="d-flex align-items-center">
                    <i class="fas fa-microchip text-info mr-2" style="font-size:12px;"></i>
                    <span class="font-weight-bold" style="font-size:11.5px;">Chasis Frontal: ${device}</span>
                </div>
                <div class="d-flex align-items-center" style="gap:10px; font-size:9.5px;">
                    <span><span class="switch-led-dot switch-led-green mr-1"></span> PWR</span>
                    <span><span class="switch-led-dot switch-led-green mr-1"></span> SYS</span>
                    <span><span class="switch-led-dot switch-led-green mr-1"></span> ACT</span>
                </div>
            </div>
            <div class="card-body bg-dark p-3 d-flex justify-content-center align-items-center">
                <div class="switch-faceplate-chassis text-center" style="max-width: ${chassisMaxWidth}; width: 100%; margin: 0 auto;">
                    ${faceplatePortsHtml}
                </div>
            </div>
        </div>

        <!-- DIAGRAMA VISUAL DE CAMINO DE CONEXIÓN (TRAZA PUNTA A PUNTA) -->
        <div class="card mb-3 border shadow-2xs" style="border-radius:8px;">
            <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                <span class="font-weight-bold text-navy" style="font-size:12px;">
                    <i class="fas fa-route text-success mr-1"></i> Camino de Conexión Activo (Puerto: <span id="active_path_port_label" class="badge badge-primary">P1</span>)
                </span>
                <small class="text-muted" style="font-size:11px;">Haga clic en cualquier puerto del chasis para actualizar la traza</small>
            </div>
            <div class="card-body p-2 bg-white" id="active_pathway_container">
                <!-- Renderizado por updatePathwayDiagram -->
            </div>
        </div>

        <!-- MATRIZ DE CONEXIONES MINIMALISTA -->
        <div class="card mb-3 border shadow-2xs" style="border-radius:8px;">
            <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
                <span class="font-weight-bold text-navy" style="font-size:12px;"><i class="fas fa-network-wired text-primary mr-1"></i> Matriz de Conectividad Traza Completa</span>
                <input type="text" class="form-control form-control-sm" style="max-width:200px; font-size:11px;" placeholder="Filtrar puertos..." onkeyup="filterPmTopologyTable(this.value)">
            </div>
            <div class="table-responsive" style="max-height: 320px; overflow-y: auto;">
                <table class="table table-sm table-hover align-middle mb-0 topo-table-minimal" id="pm_topo_table">
                    <thead>
                        <tr>
                            <th class="text-center" style="width:70px;">Puerto</th>
                            <th>Equipo Origen</th>
                            <th class="text-center">Patch Panel Origen</th>
                            <th class="text-center">Patch Panel Destino</th>
                            <th>Equipo Destino</th>
                            <th class="text-center" style="width:80px;">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${topologyRowsHtml}
                    </tbody>
                </table>
            </div>
        </div>

        <!-- DIAGRAMA DE RED Y ARQUITECTURA VISIO -->
        ${(pmSurveyDiagram && (pmSurveyDiagram.path || pmSurveyDiagram.visio_id) && pmSurveyDiagram.include_in_pdf !== false) ? `
            <div class="card border shadow-2xs mb-3" style="border-radius:8px; overflow:hidden;">
                <div class="card-header bg-navy text-white py-2 d-flex justify-content-between align-items-center">
                    <span class="font-weight-bold" style="font-size:12px;">
                        <i class="fas fa-sitemap text-warning mr-1"></i> Diagrama de Red y Arquitectura (Visio)
                    </span>
                    <span class="badge badge-success font-weight-bold" style="font-size:11px;"><i class="fas fa-check-circle mr-1"></i>Incluido en PDF</span>
                </div>
                <div class="card-body p-3 text-center bg-light">
                    <div class="p-2 bg-white rounded border d-inline-block text-center mb-2 shadow-2xs">
                        <img src="${pmSurveyDiagram.path}" style="max-height: 250px; width: auto; max-width: 100%; border-radius: 6px;" class="shadow-sm border">
                    </div>
                    <div class="text-navy font-weight-bold small"><i class="fas fa-project-diagram mr-1 text-info"></i> ${pmSurveyDiagram.title || pmSurveyDiagram.name}</div>
                </div>
            </div>
        ` : ''}

        <!-- RESUMEN Y FOTOGRAFÍAS -->
        <div class="card border shadow-2xs mb-2" style="border-radius:8px;">
            <div class="card-header bg-light py-2">
                <span class="font-weight-bold text-navy" style="font-size:12px;"><i class="fas fa-camera text-secondary mr-1"></i> Evidencia Fotográfica Adjunta (${pmSurveyImages.length})</span>
            </div>
            <div class="card-body p-2">
                ${photosHtml}
            </div>
        </div>
    `;

    finalContainer.innerHTML = html;

    // Inicializar el camino para el puerto 0
    updatePathwayDiagram(0);
}

// Function to update preview elements live as user types without breaking input focus
function updatePmStep5LivePreview() {
    const title = document.getElementById('pm_report_title_custom')?.value || 'REPORTE TÉCNICO DE PUERTOS E INFRAESTRUCTURA';
    const subtitle = document.getElementById('pm_report_subtitle_custom')?.value || 'SONDA IT & DATACENTER MANAGED SERVICES';
    const logoChoice = document.getElementById('pm_report_logo_select')?.value || 'sonda';
    const author = document.getElementById('pm_report_author_custom')?.value || 'Ing. Técnico de Soporte - SONDA';
    const notes = document.getElementById('pm_report_notes_custom')?.value || 'Sin observaciones adicionales registradas.';
    const client = document.getElementById('pm-survey-client')?.value || 'CLIENTE GENERAL';

    const titleEl = document.getElementById('pm_preview_title_display');
    const subtitleEl = document.getElementById('pm_preview_subtitle_display');
    const authorEl = document.getElementById('pm_preview_author_display');
    const notesEl = document.getElementById('pm_preview_notes_display');
    const logoEl = document.getElementById('pm_preview_logo_display');

    if (titleEl) titleEl.innerText = title.toUpperCase();
    if (subtitleEl) subtitleEl.innerText = subtitle;
    if (authorEl) authorEl.innerText = author;
    if (notesEl) notesEl.innerText = notes || 'Sin observaciones adicionales registradas.';

    if (logoEl) {
        if (logoChoice === 'vilaseca') {
            logoEl.innerHTML = '<div class="badge badge-primary px-3 py-2 font-weight-bold" style="font-size:16px; background:#1e3c72; letter-spacing:1px;">VILASECA</div>';
        } else if (logoChoice === 'femsa') {
            logoEl.innerHTML = '<div class="badge badge-warning text-white px-3 py-2 font-weight-bold" style="font-size:16px; background:#d97706; letter-spacing:1px;">FEMSA</div>';
        } else if (logoChoice === 'sonda') {
            logoEl.innerHTML = '<div class="badge badge-navy text-white px-3 py-2 font-weight-bold" style="font-size:16px; background:#002B49; letter-spacing:1px;">SONDA</div>';
        } else {
            logoEl.innerHTML = '<div class="badge badge-secondary px-3 py-2 font-weight-bold" style="font-size:15px; letter-spacing:1px;">' + client.toUpperCase() + '</div>';
        }
    }
}

// 8. RENDERIZAR PASO 5: CONFIGURACIÓN COMPLETA DE INFORME, PREVISUALIZACIÓN Y IMPRESIÓN / PDF
function renderPmStep5ReportAndPreview() {
    const container = document.getElementById('pm-survey-step5-preview');
    if (!container) return;

    const reportTitle = document.getElementById('pm_report_title_custom')?.value || 'REPORTE TÉCNICO DE PUERTOS E INFRAESTRUCTURA';
    const reportSubtitle = document.getElementById('pm_report_subtitle_custom')?.value || 'SONDA IT & DATACENTER MANAGED SERVICES';
    const logoChoice = document.getElementById('pm_report_logo_select')?.value || 'sonda';
    const authorName = document.getElementById('pm_report_author_custom')?.value || 'Ing. Técnico de Soporte - SONDA';
    const reportNotes = document.getElementById('pm_report_notes_custom')?.value || document.getElementById('pm-survey-description')?.value || 'Sin observaciones adicionales durante el levantamiento técnico.';

    const date = document.getElementById('pm-survey-date')?.value || new Date().toISOString().slice(0, 10);
    const client = document.getElementById('pm-survey-client')?.value || 'VILASECA';
    const location = document.getElementById('pm-survey-location')?.value || 'Datacenter';
    const area = document.getElementById('pm-survey-area')?.value || 'Telecomunicaciones';
    const device = document.getElementById('pm-survey-device')?.value || 'SW-CORE-01';
    const devType = document.getElementById('pm-survey-device-type')?.value || 'Switch';
    const rack = document.getElementById('pm-survey-rack')?.value || 'RACK-A01';
    const ur = document.getElementById('pm-survey-ur')?.value || '12';

    let logoBadgeHtml = '';
    if (logoChoice === 'vilaseca') {
        logoBadgeHtml = '<div class="badge badge-primary px-3 py-2 font-weight-bold" style="font-size:16px; background:#1e3c72; letter-spacing:1px;">VILASECA</div>';
    } else if (logoChoice === 'femsa') {
        logoBadgeHtml = '<div class="badge badge-warning text-white px-3 py-2 font-weight-bold" style="font-size:16px; background:#d97706; letter-spacing:1px;">FEMSA</div>';
    } else if (logoChoice === 'sonda') {
        logoBadgeHtml = '<div class="badge badge-navy text-white px-3 py-2 font-weight-bold" style="font-size:16px; background:#002B49; letter-spacing:1px;">SONDA</div>';
    } else {
        logoBadgeHtml = '<div class="badge badge-secondary px-3 py-2 font-weight-bold" style="font-size:15px; letter-spacing:1px;">MARCA BLANCA</div>';
    }

    let rowsHtml = '';
    let upCount = 0;
    let downCount = 0;

    pmSurveyPorts.forEach((p, idx) => {
        const portNum = sanitizeNumericPort(p.port_name, idx + 1);
        const isUp = (p.link_status || 'Down') === 'Up';
        if (isUp) upCount++; else downCount++;

        rowsHtml += `
            <tr>
                <td style="text-align:center; font-weight:bold; background:#f8fafc;">P-${portNum}</td>
                <td>${device} (UR:${ur})</td>
                <td>${p.patch_panel_src || '-'} ${p.port_pp_src ? '(P:' + p.port_pp_src + ')' : ''}</td>
                <td>${p.patch_panel_tgt || '-'} ${p.port_pp_tgt ? '(P:' + p.port_pp_tgt + ')' : ''}</td>
                <td style="font-weight:bold; color:${isUp ? '#166534' : '#64748b'};">${p.dest_device || 'Libre / Sin conexión'} ${p.dest_port ? '(' + p.dest_port + ')' : ''}</td>
                <td style="text-align:center; font-weight:bold; color:${isUp ? '#166534' : '#991b1b'};">${p.link_status || 'Down'}</td>
            </tr>
        `;
    });

    let photosHtml = '';
    if (pmSurveyImages.length > 0) {
        photosHtml = '<div class="mt-3"><h6 class="font-weight-bold text-dark mb-2"><i class="fas fa-camera text-secondary mr-1"></i> EVIDENCIA FOTOGRÁFICA REGISTRADA (' + pmSurveyImages.length + ')</h6><div class="d-flex flex-wrap" style="gap:12px;">';
        pmSurveyImages.forEach(img => {
            let tagStr = Array.isArray(img.tags) ? img.tags.join(', ') : '';
            photosHtml += `
                <div class="border rounded p-2 bg-white shadow-2xs text-center" style="width:200px;">
                    <img src="${img.src || img.path}" style="max-height:110px; width:100%; object-fit:cover; border-radius:4px;"><br>
                    <small class="font-weight-bold text-dark d-block mt-1" style="font-size:10px;">${img.title || 'Evidencia'}</small>
                    <small class="text-primary d-block" style="font-size:9px;">TAGS: ${tagStr}</small>
                </div>
            `;
        });
        photosHtml += '</div></div>';
    }

    container.innerHTML = `
        <!-- CARD SUPERIOR: CONFIGURACIÓN DEL INFORME TÉCNICO -->
        <div class="card mb-3 border shadow-2xs" style="border-radius:8px; background:#f8fafc;">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
                <span class="font-weight-bold text-navy" style="font-size:12.5px;">
                    <i class="fas fa-sliders-h text-primary mr-1"></i> Parámetros del Reporte Técnico Ejecutivo (7 Páginas)
                </span>
                <div class="d-flex align-items-center" style="gap:8px;">
                    <button type="button" class="btn btn-sm btn-success font-weight-bold px-3 shadow-2xs" onclick="savePmSurvey()">
                        <i class="fas fa-save mr-1"></i> Guardar Levantamiento
                    </button>
                    <button type="button" class="btn btn-sm btn-primary font-weight-bold px-3 shadow-2xs" onclick="triggerPmExecutivePrint()">
                        <i class="fas fa-print mr-1"></i> Previsualizar / Imprimir PDF (7 Págs)
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-dark font-weight-bold px-3" onclick="exportPmSurveyExcel()">
                        <i class="fas fa-file-excel text-success mr-1"></i> Exportar Excel
                    </button>
                </div>
            </div>
            <div class="card-body p-3">
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <label class="small font-weight-bold text-dark mb-1">Título del Reporte:</label>
                        <input type="text" id="pm_report_title_custom" class="form-control form-control-sm" value="${reportTitle}" oninput="updatePmStep5LivePreview()">
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="small font-weight-bold text-dark mb-1">Subtítulo / Encabezado:</label>
                        <input type="text" id="pm_report_subtitle_custom" class="form-control form-control-sm" value="${reportSubtitle}" oninput="updatePmStep5LivePreview()">
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="small font-weight-bold text-dark mb-1">Logo Corporativo / Entidad:</label>
                        <select id="pm_report_logo_select" class="form-control form-control-sm" onchange="updatePmStep5LivePreview()">
                            <option value="sonda" ${logoChoice==='sonda'?'selected':''}>SONDA (Oficial)</option>
                            <option value="vilaseca" ${logoChoice==='vilaseca'?'selected':''}>VILASECA</option>
                            <option value="femsa" ${logoChoice==='femsa'?'selected':''}>FEMSA</option>
                            <option value="custom" ${logoChoice==='custom'?'selected':''}>Sin Logo / Marca Blanca</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="small font-weight-bold text-dark mb-1">Firma / Responsable Técnico:</label>
                        <input type="text" id="pm_report_author_custom" class="form-control form-control-sm" value="${authorName}" oninput="updatePmStep5LivePreview()">
                    </div>
                    <div class="col-12 mt-2">
                        <label class="small font-weight-bold text-dark mb-1"><i class="fas fa-clipboard-list text-warning mr-1"></i> Observaciones y Hallazgos Técnicos (Página 7):</label>
                        <textarea id="pm_report_notes_custom" class="form-control form-control-sm" rows="2" placeholder="Ingrese observaciones, novedades del cableado o recomendaciones encontradas durante el levantamiento..." oninput="updatePmStep5LivePreview()">${reportNotes}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- PREVISUALIZACIÓN EN TIEMPO REAL DEL REPORTE -->
        <div class="card border shadow-sm p-4 bg-white" style="border-radius:10px; min-height:450px; border:2px dashed #0284c7 !important;">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3" style="border-bottom: 3px solid #0f172a !important;">
                <div>
                    <h4 class="font-weight-bold text-dark mb-1 text-uppercase" id="pm_preview_title_display">${reportTitle}</h4>
                    <p class="text-muted mb-0 font-weight-bold" style="font-size:11.5px;"><span id="pm_preview_subtitle_display">${reportSubtitle}</span> &bull; Fecha: ${date}</p>
                </div>
                <div id="pm_preview_logo_display">
                    ${logoBadgeHtml}
                </div>
            </div>

            <!-- METADATOS Y KPIS EN GRILA -->
            <div class="row bg-light p-3 rounded mb-3 border">
                <div class="col-md-3 col-6 mb-1">
                    <span class="text-muted text-uppercase d-block" style="font-size:9.5px; font-weight:700;">Cliente / Área</span>
                    <strong class="text-dark">${client} (${area})</strong>
                </div>
                <div class="col-md-3 col-6 mb-1">
                    <span class="text-muted text-uppercase d-block" style="font-size:9.5px; font-weight:700;">Ubicación / Site</span>
                    <strong class="text-dark">${location} (${rack})</strong>
                </div>
                <div class="col-md-3 col-6 mb-1">
                    <span class="text-muted text-uppercase d-block" style="font-size:9.5px; font-weight:700;">Equipo Origen</span>
                    <strong class="text-dark">${device} (${devType})</strong>
                </div>
                <div class="col-md-3 col-6 mb-1">
                    <span class="text-muted text-uppercase d-block" style="font-size:9.5px; font-weight:700;">Estado de Puertos</span>
                    <strong class="text-dark"><span class="text-success">${upCount} UP</span> / <span class="text-secondary">${downCount} DOWN</span></strong>
                </div>
            </div>

            <!-- RESUMEN DE OBSERVACIONES EN TIEMPO REAL -->
            <div class="alert alert-light border mb-3 p-3">
                <small class="font-weight-bold text-navy d-block mb-1"><i class="fas fa-sticky-note text-warning mr-1"></i> Observaciones y Hallazgos Técnicos Registrados (Página 7):</small>
                <div id="pm_preview_notes_display" class="text-dark italic" style="font-size:11.5px; white-space:pre-wrap;">${reportNotes || 'Sin observaciones registradas.'}</div>
            </div>

            <!-- TABLA EJECUTIVA DE TOPOLOGÍA -->
            <h6 class="font-weight-bold text-navy mb-2"><i class="fas fa-network-wired text-primary mr-1"></i> MATRIZ DE CONECTIVIDAD TRAZA COMPLETA</h6>
            <div class="table-responsive mb-3">
                <table class="table table-sm table-bordered align-middle mb-0" style="font-size:11px;">
                    <thead class="thead-dark" style="font-size:9.5px; text-transform:uppercase;">
                        <tr>
                            <th class="text-center" style="width:70px;">Puerto</th>
                            <th>Equipo Origen</th>
                            <th>Patch Panel Origen</th>
                            <th>Patch Panel Destino</th>
                            <th>Equipo Destino</th>
                            <th class="text-center" style="width:80px;">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rowsHtml}
                    </tbody>
                </table>
            </div>

            <!-- EVIDENCIA FOTOGRÁFICA -->
            ${photosHtml}

            <!-- PIE DE FIRMA Y RESPONSABILIDAD TÉCNICA -->
            <div class="row mt-4 pt-4 border-top">
                <div class="col-4 text-center">
                    <div class="border-top pt-2 mx-auto" style="max-width:200px; border-color:#94a3b8 !important;">
                        <strong class="d-block text-dark" style="font-size:10.5px;" id="pm_preview_author_display">${authorName}</strong>
                        <small class="text-muted d-block" style="font-size:9px;">Elaborado por (SONDA)</small>
                    </div>
                </div>
                <div class="col-4 text-center">
                    <div class="border-top pt-2 mx-auto" style="max-width:200px; border-color:#94a3b8 !important;">
                        <strong class="d-block text-dark" style="font-size:10.5px;">Supervisión SYNAPSE</strong>
                        <small class="text-muted d-block" style="font-size:9px;">Líder de Infraestructura</small>
                    </div>
                </div>
                <div class="col-4 text-center">
                    <div class="border-top pt-2 mx-auto" style="max-width:200px; border-color:#94a3b8 !important;">
                        <strong class="d-block text-dark" style="font-size:10.5px;">Conformidad Cliente</strong>
                        <small class="text-muted d-block" style="font-size:9px;">Firma y Sello Aceptación</small>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function updatePathwayDiagram(idx) {
    const container = document.getElementById('active_pathway_container');
    const label = document.getElementById('active_path_port_label');
    if (!container) return;

    if (!pmSurveyPorts || pmSurveyPorts.length === 0 || !pmSurveyPorts[idx]) {
        container.innerHTML = '<div class="text-center text-muted py-2">Sin puertos asignados para visualizar el camino.</div>';
        return;
    }

    const p = pmSurveyPorts[idx];
    const portNum = sanitizeNumericPort(p.port_name, idx + 1);
    if (label) label.innerText = `P${portNum}`;

    const devSrc = document.getElementById('pm-survey-device').value || 'SW-CORE-01';
    const rackSrc = document.getElementById('pm-survey-rack').value || 'RACK-A01';
    const urSrc = document.getElementById('pm-survey-ur').value || '12';

    const ppSrcName = p.patch_panel_src ? p.patch_panel_src : '<span class="text-muted font-italic">(Sin PP Origen)</span>';
    const ppSrcSub = p.port_pp_src ? `Puerto PP: <strong>${p.port_pp_src}</strong> &bull; UR:${p.ur_pp_src || '-'}` : '<span class="text-muted">Sin conexión asignada</span>';

    const ppTgtName = p.patch_panel_tgt ? p.patch_panel_tgt : '<span class="text-muted font-italic">(Sin PP Destino)</span>';
    const ppTgtSub = p.port_pp_tgt ? `Puerto PP: <strong>${p.port_pp_tgt}</strong> &bull; UR:${p.ur_pp_tgt || '-'}` : '<span class="text-muted">Sin conexión asignada</span>';

    const destDevName = p.dest_device ? p.dest_device : '<span class="text-muted font-italic">(Sin Conexión / Libre)</span>';
    const destSub = p.dest_port ? `Puerto Destino: <strong>${p.dest_port}</strong> &bull; ${p.rack_tgt || '-'}` : '<span class="text-muted">Sin conexión asignada</span>';

    container.innerHTML = `
        <div class="pathway-flow-container">
            <!-- NODO 1: EQUIPO ORIGEN -->
            <div class="pathway-node node-src">
                <span class="pathway-node-title"><i class="fas fa-server text-primary mr-1"></i> 1. Equipo Origen</span>
                <span class="pathway-node-name">${devSrc}</span>
                <span class="pathway-node-sub">Puerto Origen: <strong>P${portNum}</strong> &bull; ${rackSrc} (UR:${urSrc})</span>
            </div>

            <div class="pathway-arrow"><i class="fas fa-chevron-right"></i></div>

            <!-- NODO 2: PATCH PANEL ORIGEN -->
            <div class="pathway-node node-pp-src">
                <span class="pathway-node-title"><i class="fas fa-network-wired text-purple mr-1"></i> 2. Patch Panel Origen</span>
                <span class="pathway-node-name">${ppSrcName}</span>
                <span class="pathway-node-sub">${ppSrcSub}</span>
            </div>

            <div class="pathway-arrow"><i class="fas fa-chevron-right"></i></div>

            <!-- NODO 3: PATCH PANEL DESTINO -->
            <div class="pathway-node node-pp-tgt">
                <span class="pathway-node-title"><i class="fas fa-network-wired text-info mr-1"></i> 3. Patch Panel Destino</span>
                <span class="pathway-node-name">${ppTgtName}</span>
                <span class="pathway-node-sub">${ppTgtSub}</span>
            </div>

            <div class="pathway-arrow"><i class="fas fa-chevron-right"></i></div>

            <!-- NODO 4: EQUIPO DESTINO -->
            <div class="pathway-node node-dest">
                <span class="pathway-node-title"><i class="fas fa-desktop text-success mr-1"></i> 4. Equipo Destino</span>
                <span class="pathway-node-name">${destDevName}</span>
                <span class="pathway-node-sub">${destSub}</span>
            </div>
        </div>
    `;
}

function highlightGraphicPort(idx) {
    document.querySelectorAll('.switch-port-item').forEach(el => el.classList.remove('active-selected'));
    document.querySelectorAll('.topology-item-row').forEach(el => el.classList.remove('topo-row-highlight'));

    const item = document.getElementById(`gfx_port_item_${idx}`);
    if (item) item.classList.add('active-selected');

    const row = document.getElementById(`topo_row_${idx}`);
    if (row) {
        row.classList.add('topo-row-highlight');
        row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    updatePathwayDiagram(idx);
}

function triggerPmExecutivePrint() {
    if (!pmSurveyDiagram) {
        const selectVisio = document.getElementById('select_visio_model');
        if (selectVisio && selectVisio.value) {
            const visioId = selectVisio.value;
            const selectedOpt = selectVisio.options[selectVisio.selectedIndex];
            const selectedText = selectedOpt ? selectedOpt.text.replace('⭐ [DIAGRAMA ASOCIADO AL EQUIPO] ', '') : ('Visio #' + visioId);
            pmSurveyDiagram = {
                type: 'visio',
                visio_id: visioId,
                title: selectedText,
                name: selectedText,
                path: `api_visio.php?action=get_preview&id=${visioId}`,
                include_in_pdf: document.getElementById('cfg_inc_diagram')?.checked ?? true
            };
        }
    }

    const reportTitle = document.getElementById('pm_report_title_custom')?.value || g_pmReportConfig.title || 'REPORTE TÉCNICO DE PUERTOS E INFRAESTRUCTURA';
    const reportSubtitle = document.getElementById('pm_report_subtitle_custom')?.value || g_pmReportConfig.subtitle || 'SONDA IT & DATACENTER MANAGED SERVICES';
    const authorName = document.getElementById('pm_report_author_custom')?.value || g_pmReportConfig.author || 'Ing. Técnico de Soporte - SONDA';
    const reportNotes = document.getElementById('pm_report_notes_custom')?.value || document.getElementById('pm-survey-description')?.value || g_pmReportConfig.notes || 'Sin observaciones adicionales registradas.';

    const client = document.getElementById('pm-survey-client')?.value || g_pmReportConfig.client || 'VILASECA';
    const location = document.getElementById('pm-survey-location')?.value || 'Datacenter';
    const area = document.getElementById('pm-survey-area')?.value || 'Telecomunicaciones';
    const device = document.getElementById('pm-survey-device')?.value || 'SW-CORE-01';
    const devType = document.getElementById('pm-survey-device-type')?.value || 'Switch';
    const rack = document.getElementById('pm-survey-rack')?.value || 'RACK-A01';
    const ur = document.getElementById('pm-survey-ur')?.value || '12';
    const vendor = document.getElementById('pm-survey-vendor')?.value || 'Cisco Systems';
    const serial = document.getElementById('pm-survey-serial')?.value || 'SN-99887711';
    const hostname = document.getElementById('pm-survey-hostname')?.value || device;
    const date = document.getElementById('pm-survey-date')?.value || new Date().toISOString().slice(0, 10);

    const themeColor = g_pmReportConfig.themeColor || '#002B49';
    const logo1Html = g_pmReportConfig.showLogoInCover ? getReportLogoHtml(g_pmReportConfig.logo1, g_pmReportConfig.logo1Custom, 'SONDA') : '';
    const logo2Html = g_pmReportConfig.showLogoInCover ? getReportLogoHtml(g_pmReportConfig.logo2, g_pmReportConfig.logo2Custom, 'SYNAPSE') : '';
    const logo3Html = g_pmReportConfig.showLogoInCover ? getReportLogoHtml(g_pmReportConfig.logo3, g_pmReportConfig.logo3Custom, 'FEMSA') : '';

    let upCount = 0;
    let downCount = 0;
    let fullMatrixRows = '';
    let rowsHtml = '';

    pmSurveyPorts.forEach((p, idx) => {
        const portNum = sanitizeNumericPort(p.port_name, idx + 1);
        const isUp = (p.link_status || 'Down') === 'Up';
        if (isUp) upCount++; else downCount++;

        rowsHtml += `
            <tr>
                <td style="text-align:center; font-weight:bold; background:#f8fafc;">P-${portNum}</td>
                <td>${device} (UR:${ur})</td>
                <td>${p.patch_panel_src || '-'} ${p.port_pp_src ? '(P:' + p.port_pp_src + ')' : ''}</td>
                <td>${p.patch_panel_tgt || '-'} ${p.port_pp_tgt ? '(P:' + p.port_pp_tgt + ')' : ''}</td>
                <td style="font-weight:bold; color:${isUp ? '#166534' : '#64748b'};">${p.dest_device || 'Libre / Sin conexión'} ${p.dest_port ? '(' + p.dest_port + ')' : ''}</td>
                <td style="text-align:center; font-weight:bold; color:${isUp ? '#166534' : '#991b1b'};">${p.link_status || 'Down'}</td>
            </tr>
        `;

        fullMatrixRows += `
            <tr>
                <td style="text-align:center; font-weight:bold; background:#f1f5f9;">P-${portNum}</td>
                <td>${p.rack_src || rack}</td>
                <td>${p.device_src || device}</td>
                <td style="font-weight:bold; color:#0f172a;">P-${portNum}</td>
                <td>${p.nomenclature || p.nomenclatura || '-'}</td>
                <td>${p.cable_type || 'UTP Cat6'}</td>
                <td>${p.connector_type || 'RJ45'}</td>
                <td>${p.speed || '1 Gbps'}</td>
                <td style="text-align:center; font-weight:bold; color:${isUp ? '#166534' : '#dc2626'};">${p.link_status || 'Down'}</td>
                <td style="font-weight:bold; color:#6b21a8; background:#faf5ff;">${p.patch_panel_src || '-'}</td>
                <td style="text-align:center; background:#faf5ff;">${p.port_pp_src || '-'}</td>
                <td style="text-align:center; background:#faf5ff;">${p.ur_pp_src || '-'}</td>
                <td style="font-weight:bold; color:#0369a1; background:#f0f9ff;">${p.patch_panel_tgt || '-'}</td>
                <td style="text-align:center; background:#f0f9ff;">${p.port_pp_tgt || '-'}</td>
                <td style="text-align:center; background:#f0f9ff;">${p.ur_pp_tgt || '-'}</td>
                <td style="font-weight:bold; color:#15803d; background:#f0fdf4;">${p.dest_device || 'Libre / Sin Conexión'}</td>
                <td style="text-align:center; background:#f0fdf4;">${p.dest_port || '-'}</td>
                <td style="text-align:center; background:#f0fdf4;">${p.rack_tgt || '-'}</td>
            </tr>
        `;
    });

    let photosGridHtml = '';
    if (pmSurveyImages.length > 0) {
        photosGridHtml = '<div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:15px; margin-top:20px;">';
        pmSurveyImages.forEach((img, idx) => {
            let tagStr = Array.isArray(img.tags) ? img.tags.map(t => `<span style="display:inline-block; background:#e2e8f0; color:#334155; font-size:9px; padding:2px 6px; border-radius:3px; margin-right:4px;">${t}</span>`).join('') : '';
            let assocBadgeStr = img.associated_port ? `<span style="display:inline-block; background:#0284c7; color:#fff; font-size:9px; font-weight:bold; padding:2px 6px; border-radius:3px; margin-right:4px;">${img.associated_port}</span>` : '';
            photosGridHtml += `
                <div style="border:1px solid #cbd5e1; border-radius:6px; padding:8px; background:#fff; text-align:center;">
                    <img src="${img.src || img.path}" style="height:140px; width:100%; object-fit:cover; border-radius:4px;"><br>
                    <strong style="font-size:11px; color:#0f172a; display:block; margin-top:6px;">${img.title || 'Evidencia ' + (idx + 1)}</strong>
                    <div style="margin-top:4px;">${assocBadgeStr}${tagStr}</div>
                </div>
            `;
        });
        photosGridHtml += '</div>';
    } else {
        photosGridHtml = '<p style="font-style:italic; color:#64748b; margin-top:20px;">No se registraron fotografías durante el levantamiento.</p>';
    }

    let configFilesPdfHtml = '';
    if (pmSurveyConfigFiles && pmSurveyConfigFiles.length > 0) {
        configFilesPdfHtml = `
            <div class="pdf-page">
                <div class="section-header">
                    <h2 class="section-title">ARCHIVOS DE CONFIGURACIÓN DEL EQUIPO</h2>
                    <span style="color:#64748b; font-size:11px;">ARCHIVOS: ${pmSurveyConfigFiles.length} ADJUNTOS</span>
                </div>
                <table style="width:100%; border-collapse:collapse; font-size:10px; margin-top:15px;">
                    <thead>
                        <tr style="background:#0f172a; color:#fff;">
                            <th style="padding:8px; border:1px solid #cbd5e1;">#</th>
                            <th style="padding:8px; border:1px solid #cbd5e1; text-align:left;">NOMBRE DEL ARCHIVO</th>
                            <th style="padding:8px; border:1px solid #cbd5e1;">TAMAÑO</th>
                            <th style="padding:8px; border:1px solid #cbd5e1;">FECHA CARGA</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${pmSurveyConfigFiles.map((f, i) => `
                            <tr>
                                <td style="padding:6px; border:1px solid #cbd5e1; text-align:center;">${i+1}</td>
                                <td style="padding:6px; border:1px solid #cbd5e1; font-weight:bold;">${f.name}</td>
                                <td style="padding:6px; border:1px solid #cbd5e1; text-align:center;">${(f.size/1024).toFixed(1)} KB</td>
                                <td style="padding:6px; border:1px solid #cbd5e1; text-align:center;">${f.uploaded_at || date}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    let diagramPdfHtml = '';
    if (pmSurveyDiagram && (pmSurveyDiagram.path || pmSurveyDiagram.visio_id) && pmSurveyDiagram.include_in_pdf !== false) {
        let diagramImgUrl = pmSurveyDiagram.path || '';
        if (pmSurveyDiagram.type === 'visio' && pmSurveyDiagram.visio_id) {
            diagramImgUrl = `api_visio.php?action=get_preview&id=${pmSurveyDiagram.visio_id}`;
        } else if (diagramImgUrl && (diagramImgUrl.endsWith('.vsdx') || diagramImgUrl.endsWith('.vsd') || diagramImgUrl.endsWith('.vdx'))) {
            const lastDot = diagramImgUrl.lastIndexOf('.');
            diagramImgUrl = diagramImgUrl.substring(0, lastDot) + '_thumb.jpg';
        }

        if (diagramImgUrl && !diagramImgUrl.startsWith('http://') && !diagramImgUrl.startsWith('https://') && !diagramImgUrl.startsWith('data:')) {
            const currentDir = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1);
            diagramImgUrl = window.location.origin + currentDir + diagramImgUrl.replace(/^\//, '');
        }

        diagramPdfHtml = `
            <div class="pdf-page" style="page-break-before: always; page-break-after: always; min-height: 270mm; box-sizing: border-box; display: flex; flex-direction: column; justify-content: space-between; align-items: center; padding: 10mm 5mm;">
                <div class="section-header" style="width: 100%; text-align: center; margin-bottom: 10px;">
                    <h2 class="section-title" style="font-size: 15px; font-weight: bold; color: #002B49; margin: 0; text-transform: uppercase; border-bottom: 2px solid #002B49; padding-bottom: 4px;">DIAGRAMA DE RED Y ARQUITECTURA DE INFRAESTRUCTURA (VISIO)</h2>
                    <div style="color: #64748b; font-size: 11px; margin-top: 4px;"><strong>EQUIPO:</strong> ${device} &nbsp;|&nbsp; <strong>DIAGRAMA:</strong> ${pmSurveyDiagram.title || pmSurveyDiagram.name || 'Diagrama Adjunto'}</div>
                </div>
                <div style="width: 100%; flex-grow: 1; min-height: 220mm; max-height: 245mm; display: flex; align-items: center; justify-content: center; overflow: hidden; border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px; background: #ffffff; box-sizing: border-box;">
                    <img src="${diagramImgUrl}" style="max-width: 100%; max-height: 240mm; width: auto; height: auto; object-fit: contain; display: block; margin: auto;">
                </div>
                <div style="width: 100%; text-align: right; font-size: 10px; color: #94a3b8; margin-top: 6px;">
                    CMDB Infrastructure Survey &mdash; Página de Diagrama Visio (Hoja Tama&ntilde;o A4)
                </div>
            </div>
        `;
    }

    const printWin = window.open('', '_blank');
    const pageBaseUrl = window.location.protocol + '//' + window.location.host + window.location.pathname;
    printWin.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <base href="${pageBaseUrl}">
            <title>${reportTitle} - ${device}</title>
            <style>
                @page { size: A4 ${g_pmReportConfig.pageOrientation || 'portrait'}; margin: 12mm; }
                @page landscape-page { size: A4 landscape; margin: 10mm; }
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 11px; margin: 0; padding: 0; color: #0f172a; }
                .pdf-page { page-break-after: always; break-after: page; min-height: 260mm; box-sizing: border-box; position: relative; padding: 10px; }
                .pdf-page-landscape { page-break-after: always; break-after: page; page: landscape-page; box-sizing: border-box; padding: 10px; width: 100%; }
                
                .cover-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 4px solid ${themeColor}; padding-bottom: 15px; margin-bottom: 30px; }

                .cover-title-block { text-align: center; margin: 60px 0 40px 0; }
                .cover-title-block h1 { font-size: 26px; color: ${themeColor}; font-weight: 900; margin: 0 0 10px 0; text-transform: uppercase; letter-spacing: 0.5px; }
                .cover-title-block h3 { font-size: 15px; color: #475569; font-weight: 600; margin: 0; }

                .cover-meta-table { width: 100%; border-collapse: collapse; margin-top: 30px; background: #f8fafc; border-radius: 8px; border: 1px solid #cbd5e1; }
                .cover-meta-table td { padding: 10px 14px; border-bottom: 1px solid #e2e8f0; font-size: 11px; }
                .cover-meta-table td.label { font-weight: 700; color: #334155; width: 35%; background: #edf2f7; text-transform: uppercase; font-size: 10px; }

                .toc-list { list-style: none; padding: 0; margin-top: 30px; }
                .toc-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px dashed #cbd5e1; font-weight: 600; font-size: 12px; }
                .toc-dots { flex: 1; border-bottom: 1px dotted #94a3b8; margin: 0 10px; height: 14px; }

                .section-header { border-bottom: 3px solid ${themeColor}; padding-bottom: 6px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; }
                .section-title { font-size: 15px; font-weight: 800; color: ${themeColor}; text-transform: uppercase; margin: 0; }

                table.matrix-table { width: 100%; border-collapse: collapse; font-size: 9.5px; margin-top: 10px; }
                table.matrix-table th, table.matrix-table td { border: 1px solid #94a3b8; padding: 5px 6px; text-align: left; }
                table.matrix-table th { background: #0f172a; color: #fff; text-transform: uppercase; font-size: 8.5px; text-align: center; }
                
                .signature-box { border-top: 2px solid #0f172a; text-align: center; padding-top: 8px; width: 220px; }
            </style>
        </head>
        <body>
            <!-- PÁGINA 1: CARÁTULA PRINCIPAL -->
            ${g_pmReportConfig.incCoverPage ? `
            <div class="pdf-page">
                <div class="cover-header">
                    <div>${logo1Html}</div>
                    <div>${logo2Html}</div>
                    <div>${logo3Html}</div>
                </div>

                <div class="cover-title-block">
                    <h1>${reportTitle}</h1>
                    <h3>${reportSubtitle}</h3>
                    <p style="color:#64748b; font-size:12px; margin-top:15px; font-weight:bold;">SISTEMA DE GESTIÓN Y MAPEADO DE PUERTOS DE INFRAESTRUCTURA</p>
                </div>

                <table class="cover-meta-table">
                    <tr><td class="label">1. CLIENTE / ENTIDAD:</td><td><strong style="font-size:13px; color:${themeColor};">${client}</strong></td></tr>
                    <tr><td class="label">2. UBICACIÓN / DATACENTER:</td><td><strong style="font-size:12px; color:#0f172a;">${location} ${area ? '(' + area + ')' : ''}</strong></td></tr>
                    <tr><td class="label">3. EQUIPO RELEVADO:</td><td><strong style="font-size:12px; color:#0f172a;">${device} (${devType})</strong></td></tr>
                    <tr><td class="label">4. FECHA DE LEVANTAMIENTO:</td><td><strong style="font-size:12px; color:#0f172a;">${date}</strong></td></tr>
                    <tr><td class="label">5. RESPONSABLE TÉCNICO:</td><td><strong style="font-size:12px; color:#0284c7;">${authorName}</strong></td></tr>
                    <tr><td class="label">DETALLES ADICIONALES:</td><td><span style="color:#64748b;">Rack: ${rack} (UR: ${ur}) &bull; S/N: ${serial} &bull; Vendor: ${vendor}</span></td></tr>
                </table>

                <div style="position:absolute; bottom:20px; left:10px; right:10px; text-align:center; color:#94a3b8; font-size:10px; border-top:1px solid #e2e8f0; padding-top:10px;">
                    ${g_pmReportConfig.confidentialNotice || 'CONFIDENCIAL • DOCUMENTO TÉCNICO OFICIAL DE INFRAESTRUCTURA SONDA / SYNAPSE'}
                </div>
            </div>` : ''}

            <!-- PÁGINA 2: ÍNDICE DE CONTENIDO -->
            ${g_pmReportConfig.incToc ? `
            <div class="pdf-page">
                <div class="section-header">
                    <h2 class="section-title">ÍNDICE DE CONTENIDO DEL INFORME</h2>
                    <span style="color:#64748b; font-size:11px;">FECHA: ${date}</span>
                </div>

                <ul class="toc-list">
                    <li class="toc-item"><span>1. CARÁTULA Y DATOS GENERALES DEL PROYECTO</span><span class="toc-dots"></span><span>PÁG. 1</span></li>
                    <li class="toc-item"><span>2. ÍNDICE DE CONTENIDO DEL DOCUMENTO</span><span class="toc-dots"></span><span>PÁG. 2</span></li>
                    <li class="toc-item"><span>3. FICHA TÉCNICA Y METADATOS DEL EQUIPO ORIGEN</span><span class="toc-dots"></span><span>PÁG. 3</span></li>
                    <li class="toc-item"><span>4. MATRIZ COMPLETA DE ASIGNACIÓN DE PUERTOS (HOJA HORIZONTAL)</span><span class="toc-dots"></span><span>PÁG. 4</span></li>
                    <li class="toc-item"><span>5. DIAGRAMA DE TOPOLOGÍA Y VISTA GRÁFICA DEL CHASIS</span><span class="toc-dots"></span><span>PÁG. 5</span></li>
                    <li class="toc-item"><span>6. REGISTRO Y EVIDENCIA FOTOGRÁFICA DE TERRENO</span><span class="toc-dots"></span><span>PÁG. 6</span></li>
                    <li class="toc-item"><span>7. OBSERVACIONES, HALLAZGOS TÉCNICOS Y FIRMAS DE CONFORMIDAD</span><span class="toc-dots"></span><span>PÁG. 7</span></li>
                </ul>

                <div style="margin-top:50px; background:#f8fafc; padding:15px; border-radius:6px; border:1px solid #e2e8f0;">
                    <strong style="color:${themeColor}; display:block; margin-bottom:5px;">ALCANCE TÉCNICO DEL INFORME:</strong>
                    <p style="margin:0; font-size:10.5px; color:#475569; line-height:1.5;">
                        El presente informe consolidado documenta la arquitectura de conexiones de puerto a puerto del equipamiento activo y pasivo seleccionado. Incluye traza punta a punta desde el equipo origen, parches de interconexión, parches destino hasta los terminales y servidores conectados.
                    </p>
                </div>
            </div>` : ''}

            <!-- PÁGINA 3: FICHA TÉCNICA E INFORMACIÓN DEL EQUIPO -->
            ${g_pmReportConfig.incMetadata ? `
            <div class="pdf-page">
                <div class="section-header">
                    <h2 class="section-title">3. FICHA TÉCNICA Y METADATOS DEL EQUIPO ORIGEN</h2>
                    <span style="color:#64748b; font-size:11px;">EQUIPO: ${device}</span>
                </div>

                <table class="cover-meta-table" style="margin-top:10px;">
                    <tr><td class="label">HOSTNAME REGISTRADO:</td><td><strong>${hostname}</strong></td></tr>
                    <tr><td class="label">TIPO DE DISPOSITIVO:</td><td><strong>${devType}</strong></td></tr>
                    <tr><td class="label">MARCA / VENDOR:</td><td><strong>${vendor}</strong></td></tr>
                    <tr><td class="label">NÚMERO DE SERIE:</td><td><strong>${serial}</strong></td></tr>
                    <tr><td class="label">CLIENTE:</td><td><strong>${client}</strong></td></tr>
                    <tr><td class="label">DATACENTER / UBICACIÓN:</td><td><strong>${location} (${area})</strong></td></tr>
                    <tr><td class="label">RACK Y UNIDAD (UR):</td><td><strong>${rack} &bull; UR: ${ur}</strong></td></tr>
                    <tr><td class="label">TOTAL PUERTOS MAPEADOS:</td><td><strong>${pmSurveyPorts.length} Puertos</strong></td></tr>
                    <tr><td class="label">PUERTOS EN ESTADO UP:</td><td><strong style="color:#166534;">${upCount} Puertos Activos</strong></td></tr>
                    <tr><td class="label">PUERTOS EN ESTADO DOWN:</td><td><strong style="color:#dc2626;">${downCount} Puertos Inactivos / Libres</strong></td></tr>
                </table>

                <div style="margin-top:30px; background:#edf2f7; padding:15px; border-radius:6px; border-left:4px solid ${themeColor};">
                    <strong style="color:${themeColor}; font-size:11px; display:block; margin-bottom:4px;">DESCRIPCIÓN DE INFRAESTRUCTURA:</strong>
                    <p style="margin:0; font-size:10.5px; color:#334155;">${document.getElementById('pm-survey-description')?.value || 'Sin descripción adicional registrada para el equipo.'}</p>
                </div>
            </div>` : ''}

            <!-- PÁGINA 4: MATRIZ COMPLETA DE ASIGNACIÓN DE PUERTOS (HOJA HORIZONTAL) -->
            ${g_pmReportConfig.incMatrixTable ? `
            <div class="pdf-page-landscape">
                <div class="section-header">
                    <h2 class="section-title">4. MATRIZ COMPLETA DE ASIGNACIÓN Y CONECTIVIDAD DE PUERTOS</h2>
                    <span style="color:#64748b; font-size:11px;">ORIENTACIÓN HORIZONTAL</span>
                </div>

                <table class="matrix-table">
                    <thead>
                        <tr>
                            <th colspan="9" style="background:#0f172a; color:#fff;">1. EQUIPAMIENTO ORIGEN (${device})</th>
                            <th colspan="3" style="background:#581c87; color:#fff;">2. PATCH PANEL ORIGEN</th>
                            <th colspan="3" style="background:#0369a1; color:#fff;">3. PATCH PANEL DESTINO</th>
                            <th colspan="3" style="background:#15803d; color:#fff;">4. EQUIPAMIENTO DESTINO</th>
                        </tr>
                        <tr>
                            <th>PTO</th>
                            <th>RACK</th>
                            <th>EQUIPO</th>
                            <th>PUERTO</th>
                            <th>NOMENCLATURA</th>
                            <th>CABLE</th>
                            <th>CONECTOR</th>
                            <th>VELOCIDAD</th>
                            <th>ESTADO</th>
                            <th style="background:#6b21a8;">PATCH PANEL</th>
                            <th style="background:#6b21a8;">PTO</th>
                            <th style="background:#6b21a8;">UR</th>
                            <th style="background:#0284c7;">PATCH PANEL</th>
                            <th style="background:#0284c7;">PTO</th>
                            <th style="background:#0284c7;">UR</th>
                            <th style="background:#166534;">EQUIPO DESTINO</th>
                            <th style="background:#166534;">PTO</th>
                            <th style="background:#166534;">RACK</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${fullMatrixRows}
                    </tbody>
                </table>
            </div>` : ''}

            <!-- PÁGINA 5: DIAGRAMA DE TOPOLOGÍA Y CHASSIS -->
            ${g_pmReportConfig.incTopologyGraph ? `
            <div class="pdf-page">
                <div class="section-header">
                    <h2 class="section-title">5. DIAGRAMA DE TOPOLOGÍA Y VISTA GRÁFICA DEL CHASIS</h2>
                    <span style="color:#64748b; font-size:11px;">EQUIPO: ${device}</span>
                </div>

                <div style="background:#0f172a; color:#fff; padding:15px; border-radius:8px; margin-top:20px; text-align:center;">
                    <h3 style="margin:0 0 10px 0; color:#38bdf8; font-size:14px;">VISTA FRONTAL DEL CHASIS Y ESTADO DE LEDS</h3>
                    <div style="display:flex; flex-wrap:wrap; justify-content:center; gap:6px; max-width:650px; margin:0 auto; padding:10px; background:#1e293b; border-radius:6px;">
                        ${pmSurveyPorts.map((p, idx) => {
                            const isUp = (p.link_status || 'Down') === 'Up';
                            return `<div style="width:28px; height:28px; border-radius:4px; background:${isUp ? '#15803d' : '#334155'}; border:1px solid #475569; display:flex; align-items:center; justify-content:center; font-size:9px; font-weight:bold; color:#fff;">P${idx+1}</div>`;
                        }).join('')}
                    </div>
                </div>

                <div style="margin-top:25px; background:#f8fafc; padding:15px; border-radius:8px; border:1px solid #cbd5e1;">
                    <h4 style="margin:0 0 12px 0; color:${themeColor};">ESQUEMA DE TRAZA Y CAMINO PUNTA A PUNTA</h4>
                    <div style="display:flex; justify-content:space-between; align-items:center; text-align:center;">
                        <div style="flex:1; background:#fff; padding:8px; border-radius:6px; border:1px solid #94a3b8;">
                            <strong style="color:#0284c7; display:block;">1. EQUIPO ORIGEN</strong>
                            <span style="font-size:10px;">${device} (UR:${ur})</span>
                        </div>
                        <div style="font-weight:bold; color:#94a3b8; font-size:16px; margin:0 8px;">&rarr;</div>
                        <div style="flex:1; background:#fff; padding:8px; border-radius:6px; border:1px solid #94a3b8;">
                            <strong style="color:#6b21a8; display:block;">2. PP ORIGEN</strong>
                            <span style="font-size:10px;">Interconexión Rack</span>
                        </div>
                        <div style="font-weight:bold; color:#94a3b8; font-size:16px; margin:0 8px;">&rarr;</div>
                        <div style="flex:1; background:#fff; padding:8px; border-radius:6px; border:1px solid #94a3b8;">
                            <strong style="color:#0369a1; display:block;">3. PP DESTINO</strong>
                            <span style="font-size:10px;">Distribución Datacenter</span>
                        </div>
                        <div style="font-weight:bold; color:#94a3b8; font-size:16px; margin:0 8px;">&rarr;</div>
                        <div style="flex:1; background:#fff; padding:8px; border-radius:6px; border:1px solid #94a3b8;">
                            <strong style="color:#15803d; display:block;">4. EQUIPO DESTINO</strong>
                            <span style="font-size:10px;">Servidores / Switches</span>
                        </div>
                    </div>
                </div>

                <!-- MATRIZ DE CONECTIVIDAD TRAZA COMPLETA -->
                <div style="margin-top:25px;">
                    <h4 style="margin:0 0 8px 0; color:${themeColor}; font-size:13px; text-transform:uppercase;"><i class="fas fa-network-wired"></i> MATRIZ DE CONECTIVIDAD TRAZA COMPLETA</h4>
                    <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                        <thead>
                            <tr style="background:#0f172a; color:#fff; text-transform:uppercase;">
                                <th style="padding:6px; border:1px solid #94a3b8; text-align:center; width:50px;">PTO</th>
                                <th style="padding:6px; border:1px solid #94a3b8;">EQUIPO ORIGEN</th>
                                <th style="padding:6px; border:1px solid #94a3b8;">PP ORIGEN</th>
                                <th style="padding:6px; border:1px solid #94a3b8;">PP DESTINO</th>
                                <th style="padding:6px; border:1px solid #94a3b8;">EQUIPO DESTINO</th>
                                <th style="padding:6px; border:1px solid #94a3b8; text-align:center; width:65px;">ESTADO</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rowsHtml}
                        </tbody>
                    </table>
                </div>
            </div>` : ''}

            <!-- PÁGINA 6: REGISTRO Y EVIDENCIA FOTOGRÁFICA -->
            ${g_pmReportConfig.incPhotoEvidence ? `
            <div class="pdf-page">
                <div class="section-header">
                    <h2 class="section-title">6. REGISTRO Y EVIDENCIA FOTOGRÁFICA DE TERRENO</h2>
                    <span style="color:#64748b; font-size:11px;">EVIDENCIAS: ${pmSurveyImages.length} ADJUNTAS</span>
                </div>

                ${photosGridHtml}
            </div>` : ''}

            ${configFilesPdfHtml}

            ${diagramPdfHtml}

            <!-- PÁGINA 7: OBSERVACIONES Y FIRMAS DE CONFORMIDAD -->
            ${g_pmReportConfig.incSignatures ? `
            <div class="pdf-page">
                <div class="section-header">
                    <h2 class="section-title">7. OBSERVACIONES, HALLAZGOS TÉCNICOS Y FIRMAS DE CONFORMIDAD</h2>
                    <span style="color:#64748b; font-size:11px;">FECHA: ${date}</span>
                </div>

                <div style="background:#fff7ed; padding:15px; border-radius:6px; border:1px solid #fdba74; margin-top:20px;">
                    <strong style="color:#9a3412; font-size:12px; display:block; margin-bottom:6px;"><i class="fas fa-exclamation-circle"></i> OBSERVACIONES Y NOVEDADES TÉCNICAS REGISTRADAS:</strong>
                    <p style="margin:0; font-size:11px; color:#431407; white-space:pre-wrap;">${reportNotes}</p>
                </div>

                <div style="margin-top:140px; display:flex; justify-content:space-between;">
                    <div class="signature-box">
                        <strong style="display:block; font-size:11px; color:#0f172a;">${authorName}</strong>
                        <span style="font-size:9.5px; color:#64748b; display:block;">ESPECIALISTA TÉCNICO DE CAMPO</span>
                        <strong style="font-size:10px; color:${themeColor}; display:block; margin-top:4px;">SONDA IT SERVICES</strong>
                    </div>

                    <div class="signature-box">
                        <strong style="display:block; font-size:11px; color:#0f172a;">LÍDER DE INFRAESTRUCTURA</strong>
                        <span style="font-size:9.5px; color:#64748b; display:block;">SUPERVISIÓN DE CALIDAD</span>
                        <strong style="font-size:10px; color:#0284c7; display:block; margin-top:4px;">SYNAPSE PLATFORM</strong>
                    </div>

                    <div class="signature-box">
                        <strong style="display:block; font-size:11px; color:#0f172a;">ACEPTACIÓN DEL CLIENTE</strong>
                        <span style="font-size:9.5px; color:#64748b; display:block;">RECEPCIÓN TÉCNICO-OPERATIVA</span>
                        <strong style="font-size:10px; color:#15803d; display:block; margin-top:4px;">${client.toUpperCase()}</strong>
                    </div>
                </div>
            </div>` : ''}
        </body>
        </html>
    `);
    printWin.document.close();
    setTimeout(() => {
        if (printWin && !printWin.closed) {
            printWin.focus();
            printWin.print();
        }
    }, 1000);
}

function filterPmTopologyTable(term) {
    const filter = term.toLowerCase().trim();
    const rows = document.querySelectorAll('#pm_topo_table tbody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
}

// 8. GUARDAR LEVANTAMIENTO COMPLETO CON JSON DE PUERTOS E IMÁGENES CON METADATOS
async function savePmSurvey() {
    const creationDate = document.getElementById('pm-survey-date').value;
    const client = document.getElementById('pm-survey-client').value.trim();
    const location = document.getElementById('pm-survey-location').value.trim();
    const area = document.getElementById('pm-survey-area').value.trim();
    const deviceName = document.getElementById('pm-survey-device').value.trim();

    if (!creationDate || !client || !location || !area || !deviceName) {
        Swal.fire('Campos requeridos', 'Debe completar Fecha, Cliente, Ubicación, Área y Nombre del Equipo.', 'warning');
        return;
    }

    const data = new FormData();
    data.append('action', 'save_manual_survey');
    data.append('id', document.getElementById('pm_survey_id').value);
    data.append('creation_date', creationDate);
    data.append('client', client);
    data.append('location', location);
    data.append('area', area);
    data.append('rack', document.getElementById('pm-survey-rack').value.trim());
    data.append('ur_rack', document.getElementById('pm-survey-ur').value.trim());
    data.append('device_name', deviceName);
    data.append('device_label', document.getElementById('pm-survey-device-label').value.trim());
    data.append('device_type', document.getElementById('pm-survey-device-type').value);
    data.append('ports_count', document.getElementById('pm-survey-ports-count').value);
    data.append('vendor', document.getElementById('pm-survey-vendor').value.trim());
    data.append('serial', document.getElementById('pm-survey-serial').value.trim());
    data.append('hostname', document.getElementById('pm-survey-hostname').value.trim());
    data.append('description', document.getElementById('pm-survey-description').value.trim());
    data.append('ports_data_json', JSON.stringify(pmSurveyPorts));

    // Adjuntar imágenes y metadatos de tags y puerto asociado
    const imagesMeta = pmSurveyImages.map((img, idx) => {
        if (img.file) {
            data.append(`image_${idx}`, img.file);
            return { file_index: idx, title: img.title || '', tags: img.tags || [], associated_port: img.associated_port || '', is_existing: false };
        } else {
            return { path: img.path || img.src, title: img.title || '', tags: img.tags || [], associated_port: img.associated_port || '', is_existing: true };
        }
    });

    data.append('images_metadata_json', JSON.stringify(imagesMeta));
    data.append('config_files_json', JSON.stringify(pmSurveyConfigFiles));
    data.append('diagram_json', JSON.stringify(pmSurveyDiagram || {}));

    try {
        const resp = await fetch('api_portmapping.php', { method: 'POST', body: data });
        const res = await resp.json();
        if (res.success) {
            Swal.fire('Guardado Exitoso', 'El levantamiento de portmapping ha sido registrado correctamente.', 'success');
            cancelPmSurvey();
            loadManualSurveys();
        } else {
            Swal.fire('Error al Guardar', res.error || 'No se pudo guardar el levantamiento.', 'error');
        }
    } catch (err) {
        toastr.error('Error de comunicación con el servidor.');
    }
}

// 9. EXPORTAR MATRIZ EN PDF Y EXCEL CON LAS 4 DIVISIONES
function exportPmSurveyPDF(surveyId) {
    const client = document.getElementById('pm-survey-client').value || 'VILASECA';
    const location = document.getElementById('pm-survey-location').value || 'Datacenter';
    const device = document.getElementById('pm-survey-device').value || 'SW-CORE-01';
    const devType = document.getElementById('pm-survey-device-type').value || 'Switch';
    const date = document.getElementById('pm-survey-date').value || new Date().toISOString().slice(0, 10);

    let rowsHtml = '';
    pmSurveyPorts.forEach((p, idx) => {
        rowsHtml += `
            <tr>
                <td style="text-align:center; font-weight:bold;">${idx + 1}</td>
                <td>${p.rack_src || '-'}</td>
                <td>${p.device_src || '-'}</td>
                <td style="font-weight:bold; color:#002B49;">${p.port_name || '-'}</td>
                <td>${p.cable_type || '-'}</td>
                <td>${p.speed || '-'}</td>
                <td style="font-weight:bold; color:#6f42c1;">${p.patch_panel_src || '-'}</td>
                <td style="text-align:center;">${p.port_pp_src || '-'}</td>
                <td style="font-weight:bold; color:#007a87;">${p.patch_panel_tgt || '-'}</td>
                <td style="text-align:center;">${p.port_pp_tgt || '-'}</td>
                <td>${p.rack_tgt || '-'}</td>
                <td style="font-weight:bold; color:#28a745;">${p.dest_device || '-'}</td>
                <td style="font-weight:bold; color:#28a745;">${p.dest_port || '-'}</td>
            </tr>
        `;
    });

    let imagesHtml = '';
    if (pmSurveyImages.length > 0) {
        imagesHtml = '<div style="margin-top:20px;"><h3>EVIDENCIA FOTOGRÁFICA REGISTRADA</h3><div style="display:flex; flex-wrap:wrap; gap:10px;">';
        pmSurveyImages.forEach(img => {
            let tagStr = Array.isArray(img.tags) ? img.tags.join(', ') : '';
            imagesHtml += `
                <div style="border:1px solid #ccc; padding:6px; border-radius:6px; background:#fff; text-align:center; width:220px;">
                    <img src="${img.src || img.path}" style="max-height:140px; width:100%; object-fit:cover; border-radius:4px;"><br>
                    <small style="font-size:10px; font-weight:bold; color:#002B49; display:block; margin-top:3px;">${img.title}</small>
                    <small style="font-size:9px; color:#28a745; display:block;">TAGS: ${tagStr}</small>
                </div>
            `;
        });
        imagesHtml += '</div></div>';
    }

    const docHtml = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Reporte Portmapping - ${device}</title>
            <style>
                @page { size: landscape; margin: 10mm; }
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 10px; margin: 5px; color: #333; }
                .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #002B49; padding-bottom: 8px; margin-bottom: 12px; }
                .brand { font-size: 18px; font-weight: bold; color: #002B49; }
                .meta { margin-bottom: 12px; padding: 10px; background: #f4f6f9; border-radius: 6px; border-left: 5px solid #002B49; }
                table { width: 100%; border-collapse: collapse; margin-top: 8px; }
                th, td { border: 1px solid #ccc; padding: 5px 6px; font-size: 10px; }
                th { background-color: #002B49; color: white; text-align: left; }
                tr:nth-child(even) { background-color: #f8f9fa; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="brand">SONDA / FEMSA - PORTMAPPING INFRAESTRUCTURA</div>
                <div>FECHA LEVANTAMIENTO: ${date}</div>
            </div>
            <div class="meta">
                <strong>Cliente:</strong> ${client} | <strong>Ubicación:</strong> ${location} | <strong>Equipo Origen:</strong> ${device} | <strong>Tipo:</strong> ${devType}
            </div>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Rack Orig.</th>
                        <th>Equipo Origen</th>
                        <th>Puerto Origen</th>
                        <th>Cable</th>
                        <th>Velocidad</th>
                        <th>PP Origen</th>
                        <th>Puerto PP Orig.</th>
                        <th>PP Destino</th>
                        <th>Puerto PP Dest.</th>
                        <th>Rack Dest.</th>
                        <th>Equipo Destino</th>
                        <th>Puerto Destino</th>
                    </tr>
                </thead>
                <tbody>
                    ${rowsHtml}
                </tbody>
            </table>
            ${imagesHtml}
        </body>
        </html>
    `;

    const printWin = window.open('', '_blank');
    printWin.document.write(docHtml);
    printWin.document.close();
    setTimeout(() => { printWin.print(); }, 500);
}

function exportPmSurveyExcel(surveyId) {
    let csv = "\uFEFF"; // BOM UTF-8
    csv += "EQUIPAMIENTO ORIGEN,,,,,,,,,,,,,,,,PATCH PANEL ORIGEN,,,,PATCH PANEL DESTINO,,,,EQUIPAMIENTO DESTINO\n";
    csv += "Rack Origen,Ubicacion,Equipo Origen,Fabricante,Modelo,Serie,UR Rack,Hostname,Puerto Origen,Nomenclatura,Tipo Cable,Tipo Conector,Velocidad,Estado Link,SFP,Transceiver,Patch Panel Origen,UR Rack,Modulo,Puerto PAR Origen,Patch Panel Destino,UR Rack,Modulo,Puerto PAR Destino,Rack Destino,Equipo Destino,Fabricante,Modelo,Serie,UR Rack,Hostname,Puerto Destino\n";

    pmSurveyPorts.forEach((p) => {
        csv += `"${p.rack_src||''}","${p.location_src||''}","${p.device_src||''}","${p.vendor_src||''}","${p.model_src||''}","${p.serial_src||''}","${p.ur_src||''}","${p.hostname_src||''}","${p.port_name||''}","${p.nomenclature||p.nomenclatura||''}","${p.cable_type||''}","${p.connector_type||''}","${p.speed||''}","${p.link_status||''}","${p.sfp_installed||''}","${p.transceiver_type||''}","${p.patch_panel_src||''}","${p.ur_pp_src||''}","${p.mod_pp_src||''}","${p.port_pp_src||''}","${p.patch_panel_tgt||''}","${p.ur_pp_tgt||''}","${p.mod_pp_tgt||''}","${p.port_pp_tgt||''}","${p.rack_tgt||''}","${p.dest_device||''}","${p.vendor_tgt||''}","${p.model_tgt||''}","${p.serial_tgt||''}","${p.ur_tgt||''}","${p.hostname_tgt||''}","${p.dest_port||''}"\n`;
    });

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.setAttribute('download', `portmapping_matriz_${Date.now()}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function downloadPmTemplate() {
    window.location.href = 'api_portmapping.php?action=download_excel_template';
}

// =========================================================================
// MOTOR DE IMPORTACIÓN EXCEL PARA PORTMAPPING & RACKS
// =========================================================================
let g_pmSelectedExcelFile = null;
let g_pmExcelAnalysisData = null;

function openExcelImportModal() {
    clearSelectedExcelFile();
    $('#pm_import_step_upload').show();
    $('#pm_import_step_loading').hide();
    $('#pm_import_step_preview').hide();
    $('#modal_import_excel_portmapping').modal('show');
}

function clearSelectedExcelFile() {
    g_pmSelectedExcelFile = null;
    g_pmExcelAnalysisData = null;
    $('#pm_excel_file_input').val('');
    $('#pm_excel_selected_file_badge').hide();
    $('#btn_pm_analyze_excel').prop('disabled', true);
}

function backToImportUpload() {
    $('#pm_import_step_preview').hide();
    $('#pm_import_step_loading').hide();
    $('#pm_import_step_upload').show();
}

// Drag & drop dropzone handling
$(document).ready(function() {
    const dropzone = $('#pm_excel_dropzone');
    if (dropzone.length) {
        dropzone.on('dragover dragenter', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).css({ 'background': '#f0fdf4', 'border-color': 'var(--sonda-green)' });
        });
        dropzone.on('dragleave dragend drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).css({ 'background': '#ffffff', 'border-color': '#cbd5e1' });
        });
        dropzone.on('drop', function(e) {
            const files = e.originalEvent.dataTransfer.files;
            if (files && files.length > 0) {
                handleSelectedExcelFile(files[0]);
            }
        });
    }

    $('#pm_excel_file_input').on('change', function(e) {
        if (this.files && this.files.length > 0) {
            handleSelectedExcelFile(this.files[0]);
        }
    });
});

function handleSelectedExcelFile(file) {
    if (!file.name.match(/\.(xlsx|xls)$/i)) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Formato no soportado',
                text: 'Por favor seleccione un archivo con formato Excel (.xlsx o .xls).'
            });
        } else {
            alert('Por favor seleccione un archivo con formato Excel (.xlsx o .xls).');
        }
        return;
    }
    g_pmSelectedExcelFile = file;
    $('#pm_excel_file_name').text(file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)');
    $('#pm_excel_selected_file_badge').show();
    $('#btn_pm_analyze_excel').prop('disabled', false);
}

function analyzePortmappingExcel() {
    if (!g_pmSelectedExcelFile) {
        if (typeof Swal !== 'undefined') {
            Swal.fire('Atención', 'Debe seleccionar un archivo Excel primero.', 'warning');
        } else {
            alert('Debe seleccionar un archivo Excel primero.');
        }
        return;
    }

    $('#pm_import_step_upload').hide();
    $('#pm_import_step_loading').show();
    $('#pm_import_step_preview').hide();

    const formData = new FormData();
    formData.append('excel_file', g_pmSelectedExcelFile);

    $.ajax({
        url: 'api_portmapping.php?action=preview_excel_portmapping',
        type: 'POST',
        data: formData,
        contentType: false,
        processData: false,
        dataType: 'json',
        success: function(res) {
            $('#pm_import_step_loading').hide();
            if (!res.success) {
                $('#pm_import_step_upload').show();
                let errHtml = '';
                if (res.errors && res.errors.length) {
                    errHtml = '<ul class="mb-0 pl-3 text-left">' + res.errors.map(e => `<li>${e}</li>`).join('') + '</ul>';
                } else {
                    errHtml = res.error || 'Ocurrió un error al procesar el archivo.';
                }
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Validación de Archivo Fallida',
                        html: errHtml,
                        confirmButtonColor: '#101B31'
                    });
                } else {
                    alert('Validación de Archivo Fallida: ' + errHtml);
                }
                return;
            }

            // Exitoso: Renderizar Previsualización
            g_pmExcelAnalysisData = res.summary;
            renderExcelPreview(res.summary);
            $('#pm_import_step_preview').show();
        },
        error: function(xhr, status, error) {
            $('#pm_import_step_loading').hide();
            $('#pm_import_step_upload').show();
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error de Conexión',
                    text: 'No se pudo contactar al servidor para analizar el archivo: ' + error
                });
            } else {
                alert('Error de conexión: ' + error);
            }
        }
    });
}

function renderExcelPreview(s) {
    // 1. Jerarquía
    $('#prev_client').text(s.client || 'General').attr('title', s.client);
    $('#prev_location').text(s.location || 'General');
    $('#prev_area').text(s.area || 'General');

    // 2. Rack (Soporte individual y Multi-Rack)
    if (s.racks && s.racks.length > 1) {
        $('#prev_rack_name').text(`${s.racks_count} Racks (${s.areas_count} Áreas)`).attr('title', (s.areas || []).join(', '));
        let totalURs = s.racks.reduce((acc, r) => acc + (parseInt(r.total_u) || 0), 0);
        $('#prev_rack_total_u').text(totalURs);
        $('#prev_rack_type').text('Multi-Rack');
        let totalHardwareCount = s.racks.reduce((acc, r) => acc + (r.devices ? r.devices.length : 0), 0);
        $('#prev_rack_dev_count').text(totalHardwareCount);
        $('#prev_rack_badge').removeClass('badge-light text-dark').addClass('badge-primary text-white').text(`${s.racks_count} Racks Detectados`);
    } else {
        $('#prev_rack_name').text(s.rack.name);
        $('#prev_rack_total_u').text(s.rack.total_u);
        $('#prev_rack_type').text(s.rack.type);
        $('#prev_rack_dev_count').text(s.rack.devices_count);
        if (s.rack.is_existing) {
            $('#prev_rack_badge').removeClass('badge-light text-dark').addClass('badge-info text-white').text('Rack Existente #' + s.rack.existing_id);
        } else {
            $('#prev_rack_badge').removeClass('badge-info text-white').addClass('badge-light text-dark').text('Nuevo Rack');
        }
    }

    // 3. Manejo de Carga Masiva (Bulk) vs Individual
    if (s.is_bulk && s.devices && s.devices.length > 1) {
        $('#pm_bulk_selector_container').show();
        $('#pm_bulk_total_devices').text(s.devices_count);
        let optHtml = '';
        s.devices.forEach((dev, idx) => {
            let dupFlag = dev.is_duplicate ? ' ⚠️ [Existente]' : '';
            optHtml += `<option value="${idx}">${idx + 1}. ${dev.device_name} (Serie: ${dev.serial || 'S/N'}, ${dev.ports ? dev.ports.length : 0}p)${dupFlag}</option>`;
        });
        $('#pm_bulk_device_selector').html(optHtml).val(0);
    } else {
        $('#pm_bulk_selector_container').hide();
    }

    // 4. Equipo Activo
    $('#prev_eq_name').text(s.equipment.device_name).attr('title', s.equipment.device_name);
    $('#prev_eq_serial').text(s.equipment.serial || 'Sin Serie');
    $('#prev_eq_type').text(s.equipment.device_type);
    $('#prev_eq_ur').text(s.equipment.ur);

    // 5. Puertos
    $('#prev_ports_detected').text(s.equipment.ports_detected);
    $('#prev_ports_capacity').text(s.equipment.ports_count);
    $('#prev_ports_connected').text(s.equipment.connected_ports);
    $('#prev_ports_vacant').text(s.equipment.vacant_ports);

    // 6. Inicializar campos editables para personalización previa
    $('#cust_client').val(s.client || 'VILASECA');
    $('#cust_location').val(s.location || '');
    $('#cust_area').val(s.area || '');
    $('#cust_rack_name').val(s.rack.name || '');
    $('#cust_rack_total_u').val(s.rack.total_u || 12);
    $('#cust_rack_type').val(s.rack.type || 'AEREO');
    $('#cust_device_name').val(s.equipment.device_name || '');
    $('#cust_vendor').val(s.equipment.manufacturer || s.equipment.vendor || '');
    $('#cust_serial').val(s.equipment.serial || '');
    $('#cust_ur').val(s.equipment.ur || 1);

    // Sincronización en vivo entre campos editables y tarjetas de previsualización
    $('#cust_client').off('input').on('input', function() { $('#prev_client').text($(this).val() || '-'); });
    $('#cust_location').off('input').on('input', function() { $('#prev_location').text($(this).val() || '-'); });
    $('#cust_area').off('input').on('input', function() { $('#prev_area').text($(this).val() || '-'); });
    $('#cust_rack_name').off('input').on('input', function() { $('#prev_rack_name').text($(this).val() || '-'); });
    $('#cust_rack_total_u').off('input').on('input', function() { $('#prev_rack_total_u').text($(this).val() || '-'); });
    $('#cust_rack_type').off('change').on('change', function() { $('#prev_rack_type').text($(this).val() || '-'); });
    $('#cust_device_name').off('input').on('input', function() { $('#prev_eq_name').text($(this).val() || '-'); });
    $('#cust_serial').off('input').on('input', function() { $('#prev_eq_serial').text($(this).val() || '-'); });
    $('#cust_ur').off('input').on('input', function() { $('#prev_eq_ur').text($(this).val() || '-'); });

    // 7. Alerta de Conflictos / Inconsistencias en Racks (Reportar antes de cargar)
    let conflictAlertHtml = '';
    if (s.has_conflicts && s.conflicts && s.conflicts.length > 0) {
        let confItems = s.conflicts.map(c => `<li class="mb-1"><i class="fas fa-times-circle text-danger mr-1"></i> ${c}</li>`).join('');
        conflictAlertHtml = `
            <div class="alert alert-danger border shadow-sm mb-3" style="border-radius: 8px; border-left: 5px solid #dc2626 !important; background: #fff5f5;">
                <div class="d-flex align-items-start">
                    <i class="fas fa-exclamation-triangle fa-2x text-danger mr-3 mt-1"></i>
                    <div class="flex-grow-1">
                        <h6 class="font-weight-bold text-danger mb-1"><i class="fas fa-cubes mr-1"></i> Inconsistencias de Espacio / Solapamiento Detectadas en Racks</h6>
                        <p class="small text-dark mb-2">Durante el análisis se detectaron unidades de rack (UR) solapadas o componentes que exceden la capacidad configurada para el bastidor. Revise estos detalles antes de importar:</p>
                        <ul class="small text-danger font-weight-bold mb-2 pl-3" style="line-height: 1.5;">${confItems}</ul>
                        <small class="text-muted"><i class="fas fa-info-circle mr-1"></i> Nota: Al confirmar la importación, el sistema registrará los componentes respetando la orientación física calculada para permitir su posterior ajuste en Datacenter / Rack Builder.</small>
                    </div>
                </div>
            </div>`;
    }

    // 8. Duplicate Banner & Alert Box
    let alertHtml = '';
    const hasDup = s.is_bulk ? s.has_duplicates : s.equipment.is_duplicate;

    if (hasDup) {
        let dupMsg = s.is_bulk 
            ? `Se detectaron equipos ya existentes en la base de datos dentro del archivo: ${(s.duplicate_reasons || []).join(' | ')}`
            : s.equipment.duplicate_reason;
        
        let titleMsg = s.is_bulk ? '¡Equipos Existentes Detectados en Archivo Bulk!' : '¡Equipo Ya Registrado en el Sistema!';
        alertHtml = `
            <div class="alert alert-warning border shadow-xs d-flex align-items-center mb-3" style="border-radius: 8px; border-left: 5px solid #f59e0b !important;">
                <i class="fas fa-exclamation-triangle fa-2x text-warning mr-3"></i>
                <div class="flex-grow-1">
                    <h6 class="font-weight-bold text-dark mb-1">${titleMsg}</h6>
                    <p class="small text-secondary mb-2">${dupMsg}</p>
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="chk_overwrite_duplicate">
                        <label class="custom-control-label font-weight-bold text-dark small cursor-pointer" for="chk_overwrite_duplicate">
                            Sobrescribir / Actualizar equipos existentes en la base de datos (evita crear registros duplicados)
                        </label>
                    </div>
                </div>
            </div>`;
        
        let btnText = s.is_bulk 
            ? `<i class="fas fa-layer-group mr-1"></i> Actualizar ${s.devices_count} Equipos en Bulk`
            : '<i class="fas fa-sync-alt mr-1"></i> Actualizar Equipo Existente';
        $('#btn_pm_execute_import').removeClass('btn-success').addClass('btn-warning text-dark').html(btnText);
    } else {
        let successTitle = s.is_bulk 
            ? `Carga Masiva Validada: ${s.devices_count} Equipos y ${s.racks_count || 1} Rack(s) listos.`
            : 'Estructura validada correctamente y sin duplicados.';
        let successSub = s.is_bulk 
            ? 'Todos los equipos y sus puertos fueron analizados con éxito y no presentan duplicados en BDD.'
            : 'El equipo y su rack están listos para ser creados automáticamente en el inventario.';

        alertHtml = `
            <div class="alert alert-success border shadow-xs d-flex align-items-center mb-3" style="border-radius: 8px; border-left: 5px solid var(--sonda-green) !important;">
                <i class="fas fa-check-circle fa-2x text-success mr-3"></i>
                <div>
                    <h6 class="font-weight-bold text-dark mb-0">${successTitle}</h6>
                    <small class="text-secondary">${successSub}</small>
                </div>
            </div>`;
        
        let btnText = s.is_bulk 
            ? `<i class="fas fa-layer-group mr-1"></i> Confirmar e Importar ${s.devices_count} Equipos en Bulk`
            : '<i class="fas fa-check-circle mr-1"></i> Confirmar e Importar al Sistema';
        $('#btn_pm_execute_import').removeClass('btn-warning text-dark').addClass('btn-success text-white').html(btnText);
    }

    $('#pm_import_alert_box').html(conflictAlertHtml + alertHtml);

    // 9. Leyenda y Tabla de Puertos con soporte explícito de UP/DOWN y ON/OFF
    $('#prev_ports_status_legend').html(
        `<span class="text-success font-weight-bold mr-2"><i class="fas fa-circle mr-1"></i>${s.equipment.connected_ports} Conectados (UP/ON)</span>` +
        `<span class="text-secondary font-weight-bold"><i class="far fa-circle mr-1"></i>${s.equipment.vacant_ports} Libres (DOWN/OFF)</span>`
    );

    $('#prev_badge_ports_count').text(s.raw_ports ? s.raw_ports.length : 0);
    let portsHtml = '';
    (s.raw_ports || []).forEach(p => {
        let isUp = (p.is_up === true || (p.link_status || '').toLowerCase() === 'up' || (p.led || '').toUpperCase() === 'ON' || (p.led || '').toUpperCase() === 'UP');
        let ledBadge = isUp 
            ? '<span class="badge badge-success px-2 py-1"><i class="fas fa-link mr-1"></i>' + (p.status_display || 'UP / ON (Conectado)') + '</span>' 
            : '<span class="badge badge-secondary px-2 py-1"><i class="far fa-circle mr-1"></i>' + (p.status_display || 'DOWN / OFF (Libre)') + '</span>';
        
        let dest = p.dest_patch_panel || p.dest_device || '-';
        if (p.dest_ur) dest += ` (UR ${p.dest_ur})`;
        let mod = p.dest_module ? `Mod ${p.dest_module} ` : '';
        let portPar = p.dest_par || p.dest_port || '-';
        let modStr = (mod || portPar !== '-') ? `${mod}Puerto: ${portPar}` : (p.connector || 'RJ45');
        let rawExcelText = p.led ? `<small class="text-muted d-block" style="font-size:0.72rem;">Excel: <code>${p.led}</code></small>` : '';

        portsHtml += `
            <tr>
                <td class="text-center font-weight-bold text-dark">${p.port}</td>
                <td>${ledBadge}${rawExcelText}</td>
                <td><code>${p.speed || '1GB'} ${p.cable || 'UTP'}</code></td>
                <td class="font-weight-bold text-navy">${dest}</td>
                <td><small class="text-muted">${modStr}</small></td>
                <td><small class="text-secondary">${p.observations || '-'}</small></td>
            </tr>`;
    });
    $('#prev_ports_tbody').html(portsHtml || '<tr><td colspan="6" class="text-center py-3 text-muted">Sin puertos registrados</td></tr>');

    // 10. Tabla de Hardware en Rack y Selector Multi-Rack
    if (s.racks && s.racks.length > 1) {
        $('#pm_prev_rack_selector_container').attr('style', 'display: flex !important;');
        let rkOptions = '';
        s.racks.forEach((r, idx) => {
            let rkDevCount = r.devices ? r.devices.length : 0;
            let confIcon = (r.conflicts && r.conflicts.length > 0) ? ' ⚠️ [Inconsistencia]' : '';
            rkOptions += `<option value="${idx}">${idx + 1}. ${r.name} - Área: ${r.area} (${r.total_u}U, ${rkDevCount} equipos)${confIcon}</option>`;
        });
        $('#pm_prev_rack_selector').html(rkOptions).val(0);
        switchPreviewRack(0);
    } else {
        $('#pm_prev_rack_selector_container').attr('style', 'display: none !important;');
        switchPreviewRack(0);
    }

    // 9. Tabla de Mapeo de Conexión: Excel ➔ Base de Datos (BDD)
    let mapList = s.database_mapping || [];
    $('#prev_badge_mapping_count').text(mapList.length);
    let mapHtml = '';
    mapList.forEach(m => {
        let editableBadge = m.editable 
            ? `<span class="badge badge-warning text-dark"><i class="fas fa-edit mr-1"></i>Ajustable</span>` 
            : `<span class="badge badge-light border text-muted">Automático</span>`;
        mapHtml += `
            <tr>
                <td class="font-weight-bold text-dark">
                    <div>${m.excel_col}</div>
                    <small class="text-muted">${m.origin_sheet}</small>
                </td>
                <td><code class="text-primary font-weight-bold" style="font-size:0.75rem;">${m.db_table}</code></td>
                <td><code class="text-navy font-weight-bold" style="font-size:0.75rem;">${m.db_field}</code></td>
                <td><small class="text-secondary">${m.rule}</small></td>
                <td><span class="badge badge-light border font-weight-bold p-1 text-dark" style="font-size:0.78rem;">${m.detected_value || '-'}</span></td>
                <td class="text-center">${editableBadge}</td>
            </tr>`;
    });
    $('#prev_mapping_tbody').html(mapHtml || '<tr><td colspan="6" class="text-center py-3 text-muted">Sin datos de mapeo</td></tr>');
}

function switchPreviewRack(idx) {
    if (!g_pmExcelAnalysisData) return;
    const racks = g_pmExcelAnalysisData.racks || [g_pmExcelAnalysisData.rack];
    const rk = racks[idx] || racks[0];
    if (!rk) return;

    let rkDevCount = rk.devices ? rk.devices.length : 0;
    $('#pm_prev_rack_meta_text').text(`${rk.name} · Área: ${rk.area || 'General'} · ${rk.total_u || 12} UR (${rkDevCount} equipos)`);
    $('#prev_badge_rack_count').text(rkDevCount);

    let rackHtml = '';
    (rk.devices || []).forEach(d => {
        let isConflict = false;
        if (rk.conflicts && rk.conflicts.length > 0) {
            isConflict = rk.conflicts.some(c => c.includes(d.name));
        }
        let trClass = isConflict ? 'table-warning' : '';
        let warnBadge = isConflict ? '<span class="badge badge-warning text-dark ml-2"><i class="fas fa-exclamation-triangle mr-1"></i>Revisar UR</span>' : '';

        let faceBadge = '<span class="badge badge-pill badge-primary"><i class="fas fa-eye mr-1"></i>Frontal</span>';
        if (d.orientation === 'rear') {
            faceBadge = '<span class="badge badge-pill badge-secondary text-white"><i class="fas fa-undo-alt mr-1"></i>Posterior (Rear)</span>';
        } else if (d.orientation === 'both') {
            faceBadge = '<span class="badge badge-pill badge-info text-white"><i class="fas fa-arrows-alt-h mr-1"></i>Ambas Caras</span>';
        }

        let typeBadge = '';
        if (d.is_vertical) {
            let sideLabel = (d.mounting === 'vertical_right') ? 'Lateral B (Der)' : 'Lateral A (Izq)';
            typeBadge = `<span class="badge badge-pill badge-dark ml-1"><i class="fas fa-arrows-alt-v mr-1"></i>PDU Vert. [${sideLabel}]</span>`;
        }

        rackHtml += `
            <tr class="${trClass}">
                <td class="text-center"><span class="badge badge-light border font-weight-bold">${d.is_vertical ? '0U Lateral' : 'UR ' + d.start_u}</span></td>
                <td class="text-center font-weight-bold">${d.is_vertical ? '0U' : d.height_u + ' U'}</td>
                <td class="font-weight-bold text-dark"><i class="fas fa-server mr-2 text-warning"></i>${d.name}${warnBadge}</td>
                <td>${faceBadge} ${typeBadge}</td>
            </tr>`;
    });
    $('#prev_rack_tbody').html(rackHtml || '<tr><td colspan="4" class="text-center py-3 text-muted">Sin hardware de rack registrado</td></tr>');
}

function switchBulkPreviewDevice(devIndex) {
    if (!g_pmExcelAnalysisData || !g_pmExcelAnalysisData.devices) return;
    const dev = g_pmExcelAnalysisData.devices[devIndex];
    if (!dev) return;

    // Actualizar Tarjeta 3: Equipo
    $('#prev_eq_name').text(dev.device_name).attr('title', dev.device_name);
    $('#prev_eq_serial').text(dev.serial || 'Sin Serie');
    $('#prev_eq_type').text(dev.device_type);
    $('#prev_eq_ur').text(dev.ur);

    // Actualizar Tarjeta 4: Puertos
    const pCount = dev.ports ? dev.ports.length : 0;
    $('#prev_ports_detected').text(pCount);
    $('#prev_ports_capacity').text(dev.ports_count || pCount);
    $('#prev_ports_connected').text(dev.connected_count || 0);
    $('#prev_ports_vacant').text(dev.vacant_count || 0);

    // Campos personalizables
    $('#cust_device_name').val(dev.device_name);
    $('#cust_vendor').val(dev.manufacturer || dev.vendor || '');
    $('#cust_serial').val(dev.serial || '');
    $('#cust_ur').val(dev.ur || 1);

    // Actualizar leyenda y tabla de puertos
    $('#prev_ports_status_legend').html(
        `<span class="text-success font-weight-bold mr-2"><i class="fas fa-circle mr-1"></i>${dev.connected_count || 0} Conectados (UP/ON)</span>` +
        `<span class="text-secondary font-weight-bold"><i class="far fa-circle mr-1"></i>${dev.vacant_count || 0} Libres (DOWN/OFF)</span>`
    );
    $('#prev_badge_ports_count').text(pCount);

    let portsHtml = '';
    (dev.ports || []).forEach(p => {
        let isUp = (p.is_up === true || (p.link_status || '').toLowerCase() === 'up' || (p.led || '').toUpperCase() === 'ON' || (p.led || '').toUpperCase() === 'UP');
        let ledBadge = isUp 
            ? '<span class="badge badge-success px-2 py-1"><i class="fas fa-link mr-1"></i>' + (p.status_display || 'UP / ON (Conectado)') + '</span>' 
            : '<span class="badge badge-secondary px-2 py-1"><i class="far fa-circle mr-1"></i>' + (p.status_display || 'DOWN / OFF (Libre)') + '</span>';
        
        let dest = p.dest_patch_panel || p.dest_device || '-';
        if (p.dest_ur) dest += ` (UR ${p.dest_ur})`;
        let mod = p.dest_module ? `Mod ${p.dest_module} ` : '';
        let portPar = p.dest_par || p.dest_port || '-';
        let modStr = (mod || portPar !== '-') ? `${mod}Puerto: ${portPar}` : (p.connector || 'RJ45');
        let rawExcelText = p.led ? `<small class="text-muted d-block" style="font-size:0.72rem;">Excel: <code>${p.led}</code></small>` : '';

        portsHtml += `
            <tr>
                <td class="text-center font-weight-bold text-dark">${p.port}</td>
                <td>${ledBadge}${rawExcelText}</td>
                <td><code>${p.speed || '1GB'} ${p.cable || 'UTP'}</code></td>
                <td class="font-weight-bold text-navy">${dest}</td>
                <td><small class="text-muted">${modStr}</small></td>
                <td><small class="text-secondary">${p.observations || '-'}</small></td>
            </tr>`;
    });
    $('#prev_ports_tbody').html(portsHtml || '<tr><td colspan="6" class="text-center py-3 text-muted">Sin puertos registrados</td></tr>');
}

function executePortmappingImport() {
    if (!g_pmSelectedExcelFile || !g_pmExcelAnalysisData) {
        if (typeof Swal !== 'undefined') {
            Swal.fire('Error', 'No hay datos de análisis activos para importar.', 'error');
        } else {
            alert('No hay datos de análisis activos para importar.');
        }
        return;
    }

    const isDuplicate = g_pmExcelAnalysisData.is_bulk 
        ? g_pmExcelAnalysisData.has_duplicates 
        : (g_pmExcelAnalysisData.equipment && g_pmExcelAnalysisData.equipment.is_duplicate);
    const chkOverwrite = $('#chk_overwrite_duplicate').is(':checked');

    if (isDuplicate && !chkOverwrite) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: 'Equipos Existentes Detectados',
                text: 'Para evitar inconsistencias o duplicados, debe marcar la casilla "Sobrescribir / Actualizar equipos existentes en la base de datos", o cancelar la importación.',
                confirmButtonColor: '#101B31'
            });
        } else {
            alert('Para evitar duplicados, debe marcar la casilla de sobrescribir.');
        }
        return;
    }

    const formData = new FormData();
    formData.append('excel_file', g_pmSelectedExcelFile);
    formData.append('overwrite_duplicate', (isDuplicate && chkOverwrite) ? '1' : '0');

    // Adjuntar datos de personalización previa
    formData.append('custom_client', $('#cust_client').val() || '');
    formData.append('custom_location', $('#cust_location').val() || '');
    formData.append('custom_area', $('#cust_area').val() || '');
    formData.append('custom_rack_name', $('#cust_rack_name').val() || '');
    formData.append('custom_rack_total_u', $('#cust_rack_total_u').val() || '');
    formData.append('custom_rack_type', $('#cust_rack_type').val() || '');
    formData.append('custom_device_name', $('#cust_device_name').val() || '');
    formData.append('custom_vendor', $('#cust_vendor').val() || '');
    formData.append('custom_serial', $('#cust_serial').val() || '');
    formData.append('custom_ur', $('#cust_ur').val() || '');

    $('#btn_pm_execute_import').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Guardando...');

    $.ajax({
        url: 'api_portmapping.php?action=process_excel_portmapping',
        type: 'POST',
        data: formData,
        contentType: false,
        processData: false,
        dataType: 'json',
        success: function(res) {
            $('#btn_pm_execute_import').prop('disabled', false);
            if (!res.success) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error al Guardar',
                        text: res.error || 'Ocurrió un error inesperado al registrar en la base de datos.'
                    });
                } else {
                    alert('Error al Guardar: ' + (res.error || 'Error inesperado'));
                }
                return;
            }

            $('#modal_import_excel_portmapping').modal('hide');

            let detailsHtml = '';
            if (res.is_bulk) {
                let devItems = (res.imported_devices || []).map(d => `<li><strong>${d.device_name}</strong> (Serie: <code>${d.serial || 'S/N'}</code>, ${d.ports_count} puertos, UR ${d.ur})</li>`).join('');
                detailsHtml = `
                    <div class="small bg-light p-2 rounded border mt-2">
                        <div><strong>Modo:</strong> <span class="badge badge-primary">Carga Masiva (Bulk)</span></div>
                        <div><strong>Equipos Procesados:</strong> ${res.devices_count} dispositivos</div>
                        <div><strong>Total Puertos:</strong> ${res.total_ports} interfaces físicas</div>
                        <div><strong>Cliente:</strong> ${res.client || ''}</div>
                        <div><strong>Sede / Ubicación:</strong> ${res.location || ''}</div>
                        <div><strong>Rack:</strong> ${res.rack_name || ''}</div>
                        <div class="mt-2 font-weight-bold text-navy">Dispositivos registrados:</div>
                        <ul class="pl-3 mb-0">${devItems}</ul>
                    </div>`;
            } else {
                detailsHtml = `
                    <div class="small bg-light p-2 rounded border mt-2">
                        <div><strong>Cliente:</strong> ${res.client}</div>
                        <div><strong>Sede / Ubicación:</strong> ${res.location}</div>
                        <div><strong>Rack ID:</strong> #${res.rack_id} (${res.rack_name})</div>
                        <div><strong>Levantamiento ID:</strong> #${res.survey_id}</div>
                        <div><strong>Puertos Registrados:</strong> ${res.ports_count} (${res.connected_count} conectados / ${res.vacant_count} libres)</div>
                    </div>`;
            }

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: '¡Importación Completada!',
                    html: `
                        <div class="text-left py-2">
                            <p class="mb-1 text-success font-weight-bold"><i class="fas fa-check-circle mr-1"></i> ${res.message}</p>
                            ${detailsHtml}
                        </div>`,
                    confirmButtonText: 'Aceptar',
                    confirmButtonColor: '#101B31'
                }).then(() => {
                    if (typeof loadManualSurveys === 'function') {
                        loadManualSurveys();
                    }
                });
            } else {
                alert(res.message);
                if (typeof loadManualSurveys === 'function') {
                    loadManualSurveys();
                }
            }
        },
        error: function(xhr, status, error) {
            $('#btn_pm_execute_import').prop('disabled', false).html('<i class="fas fa-check-circle mr-1"></i> Confirmar e Importar al Sistema');
            if (typeof Swal !== 'undefined') {
                Swal.fire('Error', 'Fallo de comunicación al guardar: ' + error, 'error');
            } else {
                alert('Fallo de comunicación al guardar: ' + error);
            }
        }
    });
}

// FUNCIONALIDADES EXTRA CMDB & ZABBIX
async function initDeviceSelectors() {
    try {
        const resp = await fetch('api_portmapping.php?action=get_all_devices');
        const res = await resp.json();
        if (res.success) {
            cmdbDevices = res.data;
            const selector = $('#device_selector');
            selector.empty().append('<option value="">Buscar y seleccionar equipo...</option>');
            cmdbDevices.forEach(d => {
                selector.append(`<option value="${d.id}">${d.name} (${d.category_name})</option>`);
            });
            selector.select2({ theme: 'bootstrap4', placeholder: 'Buscar y seleccionar equipo...' }).on('change', function() {
                const val = $(this).val();
                selectedDevice = val;
                if (val) {
                    $('#device_manager_empty').hide();
                    $('#device_manager_body').show();
                    loadDevicePortsAndConnections(val);
                } else {
                    $('#device_manager_empty').show();
                    $('#device_manager_body').hide();
                }
            });
        }
    } catch (err) {}
}

async function loadDevicePortsAndConnections(deviceId) {
    const netBody = document.getElementById('tbl_net_connections_body');
    if (!netBody) return;
    netBody.innerHTML = '<tr><td colspan="10" class="text-center py-4"><i class="fas fa-spinner fa-spin mr-2"></i> Cargando interfaces de red...</td></tr>';
    try {
        const resp = await fetch(`api_portmapping.php?action=get_device_ports_and_connections&device_id=${deviceId}`);
        const res = await resp.json();
        if (res.success) {
            document.getElementById('lbl_dev_name').innerText = res.device.hostname;
            document.getElementById('lbl_dev_cat').innerText = $('#device_selector option:selected').text().match(/\(([^)]+)\)/)?.[1] || 'Hardware';
            
            let html = '';
            if (res.network_ports && res.network_ports.length > 0) {
                res.network_ports.forEach((p, idx) => {
                    html += `
                        <tr>
                            <td class="text-center font-weight-bold text-muted">${idx + 1}</td>
                            <td><strong class="text-navy">${res.device.hostname}</strong></td>
                            <td><span class="badge badge-primary font-weight-bold">${p.port_name}</span></td>
                            <td><span class="badge badge-light border">${p.connection_type || 'Red'}</span></td>
                            <td>${p.cable_type || '-'}</td>
                            <td class="text-center"><span class="color-swatch-badge" style="background-color:${p.color_code || '#007bff'};">${p.color_code || '#007bff'}</span></td>
                            <td><small class="text-muted">${p.notes || '-'}</small></td>
                            <td><strong class="text-dark">${p.dest_device_name || '-'}</strong></td>
                            <td><span class="badge badge-secondary">${p.dest_port_name || '-'}</span></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-primary font-weight-bold" onclick="openConnectModal(${deviceId}, '${p.port_name}', 'network')"><i class="fas fa-link mr-1"></i> Conectar</button>
                            </td>
                        </tr>
                    `;
                });
            } else {
                html = '<tr><td colspan="10" class="text-center py-4 text-muted"><i class="fas fa-info-circle mr-2"></i> No se encontraron interfaces registradas para este equipo.</td></tr>';
            }
            netBody.innerHTML = html;
        }
    } catch (err) {}
}

async function loadMappings() {
    const tbody = document.getElementById('tbl_global_mappings_body');
    if (!tbody) return;
    try {
        const resp = await fetch('api_portmapping.php?action=get_all_mappings');
        const res = await resp.json();
        if (res.success && res.data && res.data.length > 0) {
            let html = '';
            res.data.forEach((m, idx) => {
                html += `
                    <tr>
                        <td class="text-center font-weight-bold text-muted">${idx + 1}</td>
                        <td><strong class="text-navy">${m.src_device}</strong></td>
                        <td><span class="badge badge-primary font-weight-bold">${m.src_port}</span></td>
                        <td><span class="badge badge-light border">${m.connection_type}</span></td>
                        <td>${m.cable_type}</td>
                        <td class="text-center"><span class="color-swatch-badge" style="background-color:${m.color_code};">${m.color_code}</span></td>
                        <td><small class="text-muted">${m.notes || '-'}</small></td>
                        <td><strong class="text-navy">${m.tgt_device}</strong></td>
                        <td><span class="badge badge-success font-weight-bold">${m.tgt_port}</span></td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        } else {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted"><i class="fas fa-folder-open mr-2"></i> No hay conexiones registradas en el directorio global.</td></tr>';
        }
    } catch (err) {}
}

function toggleManualDevice(isManual) {
    document.getElementById('wrapper_select_device').style.display = isManual ? 'none' : 'block';
    document.getElementById('wrapper_text_device').style.display = isManual ? 'block' : 'none';
}

function toggleManualPort(isManual) {
    document.getElementById('wrapper_select_port').style.display = isManual ? 'none' : 'block';
    document.getElementById('wrapper_text_port').style.display = isManual ? 'block' : 'none';
}

// FUNCIONES ADICIONALES DE GESTIÓN Y ASISTENTE
async function loadManualSurveyDetail(id) {
    try {
        const resp = await fetch(`api_portmapping.php?action=get_manual_survey_detail&id=${id}`);
        const res = await resp.json();
        if (res.success && res.data) {
            const survey = res.data;
            document.getElementById('pm_survey_id').value = survey.id || '';
            document.getElementById('pm-survey-date').value = survey.creation_date || '<?php echo date('Y-m-d'); ?>';
            document.getElementById('pm-survey-client').value = survey.client || '';
            document.getElementById('pm-survey-location').value = survey.location || '';
            document.getElementById('pm-survey-area').value = survey.area || '';
            document.getElementById('pm-survey-rack').value = survey.rack || '';
            document.getElementById('pm-survey-ur').value = survey.ur_rack || '';
            document.getElementById('pm-survey-device').value = survey.device_name || '';
            document.getElementById('pm-survey-device-label').value = survey.device_label || '';
            document.getElementById('pm-survey-device-type').value = survey.device_type || 'Switch';
            document.getElementById('pm-survey-ports-count').value = survey.ports_count || 24;
            document.getElementById('pm-survey-vendor').value = survey.vendor || (survey.ports && survey.ports[0] ? survey.ports[0].vendor_src : '') || '';
            document.getElementById('pm-survey-serial').value = survey.serial || survey.device_label || (survey.ports && survey.ports[0] ? survey.ports[0].serial_src : '') || '';
            document.getElementById('pm-survey-hostname').value = survey.hostname || '';
            document.getElementById('pm-survey-description').value = survey.description || '';

            if (Array.isArray(survey.ports)) {
                pmSurveyPorts = survey.ports;
            } else if (survey.ports_data_json) {
                try { pmSurveyPorts = typeof survey.ports_data_json === 'string' ? JSON.parse(survey.ports_data_json) : survey.ports_data_json; } catch(e) { pmSurveyPorts = []; }
            } else {
                pmSurveyPorts = [];
            }

            // Normalizar estado link (UP/DOWN u ON/OFF) para visualización estandarizada
            if (Array.isArray(pmSurveyPorts)) {
                pmSurveyPorts.forEach(p => {
                    let rawSt = (p.link_status || p.status || p.estado_link || p.led || '').toString().trim().toUpperCase();
                    if (rawSt === 'UP' || rawSt === 'ON' || rawSt === 'ACTIVO' || rawSt === 'CONECTADO') {
                        p.link_status = 'Up';
                    } else if (rawSt === 'DOWN' || rawSt === 'OFF' || rawSt === 'INACTIVO' || rawSt === 'LIBRE') {
                        p.link_status = 'Down';
                    }
                });
            }

            let rawImgs = [];
            if (Array.isArray(survey.images)) {
                rawImgs = survey.images;
            } else if (survey.images_json) {
                try { rawImgs = typeof survey.images_json === 'string' ? JSON.parse(survey.images_json) : survey.images_json; } catch(e) { rawImgs = []; }
            }
            if (!Array.isArray(rawImgs)) rawImgs = [];
            pmSurveyImages = rawImgs.map(img => {
                if (typeof img === 'string') {
                    return { src: img, path: img, title: '', tags: [], associated_port: '', is_existing: true };
                }
                const pathStr = img.src || img.path || '';
                return {
                    src: pathStr,
                    path: pathStr,
                    title: img.title || '',
                    tags: Array.isArray(img.tags) ? img.tags : [],
                    associated_port: img.associated_port || '',
                    is_existing: true
                };
            });

            if (Array.isArray(survey.config_files)) {
                pmSurveyConfigFiles = survey.config_files;
            } else if (survey.config_files_json) {
                try { pmSurveyConfigFiles = typeof survey.config_files_json === 'string' ? JSON.parse(survey.config_files_json) : survey.config_files_json; } catch(e) { pmSurveyConfigFiles = []; }
            } else {
                pmSurveyConfigFiles = [];
            }

            if (survey.diagram && typeof survey.diagram === 'object') {
                pmSurveyDiagram = survey.diagram;
            } else if (survey.diagram_json) {
                try { pmSurveyDiagram = typeof survey.diagram_json === 'string' ? JSON.parse(survey.diagram_json) : survey.diagram_json; } catch(e) { pmSurveyDiagram = null; }
            } else {
                pmSurveyDiagram = null;
            }

            const wizardCard = document.getElementById('pm_survey_wizard_card');
            if (wizardCard) wizardCard.style.display = 'block';

            activatePmTab('survey-wizard-content');

            goToPmSurveyStep(1);
            renderPmImagesPreview();
            renderPmPortsTable();
            renderPmConfigFilesList();
            renderPmDiagramPreview();
        } else {
            toastr.error(res.error || 'No se pudo cargar el detalle del levantamiento.');
        }
    } catch (err) {
        toastr.error('Error al conectar con el servidor.');
    }
}

async function deletePmSurvey(id) {
    const confirm = await Swal.fire({
        title: '¿Eliminar Levantamiento?',
        text: "Esta acción no se puede deshacer.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    });

    if (confirm.isConfirmed) {
        try {
            const data = new FormData();
            data.append('action', 'delete_manual_survey');
            data.append('id', id);
            const resp = await fetch('api_portmapping.php', { method: 'POST', body: data });
            const res = await resp.json();
            if (res.success) {
                toastr.success('Levantamiento eliminado.');
                selectedSurveyIds.delete(Number(id));
                updateBatchActionBar();
                loadManualSurveys();
            } else {
                toastr.error(res.error || 'No se pudo eliminar el levantamiento.');
            }
        } catch (err) {
            toastr.error('Error al eliminar levantamiento.');
        }
    }
}

function openConnectModal(deviceId, portName, connectionType) {
    document.getElementById('modal_device_id').value = deviceId;
    document.getElementById('modal_port_name').value = portName;
    document.getElementById('modal_connection_type').value = connectionType;
    document.getElementById('modal_display_src_device').value = $('#lbl_dev_name').text() || 'Equipo Origen';
    document.getElementById('modal_display_src_port').value = portName;
    $('#modalConnection').modal('show');
}

async function loadPorts(deviceId, targetSelectId) {
    const select = document.getElementById(targetSelectId);
    if (!select) return;
    select.innerHTML = '<option value="">Cargando puertos...</option>';
    if (!deviceId) {
        select.innerHTML = '<option value="">Seleccione equipo primero</option>';
        return;
    }
    try {
        const resp = await fetch(`api_portmapping.php?action=get_device_ports&device_id=${deviceId}`);
        const res = await resp.json();
        if (res.success && res.data) {
            let html = '<option value="">Seleccionar puerto...</option>';
            res.data.forEach(p => {
                html += `<option value="${p.id}">${p.port_name} (${p.status || 'Disponible'})</option>`;
            });
            select.innerHTML = html;
        } else {
            select.innerHTML = '<option value="">Sin puertos disponibles</option>';
        }
    } catch (err) {
        select.innerHTML = '<option value="">Error al cargar puertos</option>';
    }
}
// =========================================================================
// ZABBIX INTEGRATION FUNCTIONS
// =========================================================================
function openZabbixSelectModal() {
    $('#modal_select_zabbix_host').modal('show');
    loadZabbixHostsList();
}

async function loadZabbixHostsList() {
    const tbody = document.getElementById('zabbix_hosts_table_body');
    if (tbody) tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin mr-2"></i>Cargando equipos desde Zabbix...</td></tr>';
    
    try {
        const resp = await fetch('api_zabbix.php?action=get_hosts');
        const res = await resp.json();
        if (res.success && Array.isArray(res.data)) {
            zabbixHostsList = res.data;
            renderZabbixHostsTable(zabbixHostsList);
        } else {
            if (tbody) tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-danger"><i class="fas fa-exclamation-triangle mr-2"></i>${res.error || 'No se pudieron cargar los equipos de Zabbix.'}</td></tr>`;
        }
    } catch(e) {
        if (tbody) tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-danger"><i class="fas fa-exclamation-triangle mr-2"></i>Error de conexión con el servicio Zabbix.</td></tr>';
    }
}

function renderZabbixHostsTable(hosts) {
    const tbody = document.getElementById('zabbix_hosts_table_body');
    if (!tbody) return;
    
    if (!hosts || hosts.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No hay equipos disponibles en Zabbix.</td></tr>';
        return;
    }
    
    let html = '';
    hosts.forEach(h => {
        const statusBadge = (h.status == 0) ? '<span class="badge badge-success">Monitoreado</span>' : '<span class="badge badge-secondary">Inactivo</span>';
        const hostName = h.name || h.host;
        const hostIp = h.interfaces?.[0]?.ip || h.ip || '0.0.0.0';
        const hostGroup = Array.isArray(h.groups) ? h.groups.map(g => g.name).join(', ') : (h.group_name || 'Sin Grupo');
        
        html += `
            <tr>
                <td><strong>${hostName}</strong><br><small class="text-muted">${h.host}</small></td>
                <td><code class="text-primary">${hostIp}</code></td>
                <td><small>${hostGroup}</small></td>
                <td>${statusBadge}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-xs btn-primary font-weight-bold px-2 shadow-2xs" onclick="selectZabbixHostForPm('${h.hostid}')">
                        <i class="fas fa-check mr-1"></i> Seleccionar
                    </button>
                </td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
}

function filterZabbixHostList() {
    const query = document.getElementById('zabbix_host_search_input')?.value.toLowerCase().trim() || '';
    if (!query) {
        renderZabbixHostsTable(zabbixHostsList);
        return;
    }
    const filtered = zabbixHostsList.filter(h => {
        const name = (h.name || '').toLowerCase();
        const host = (h.host || '').toLowerCase();
        const ip = (h.interfaces?.[0]?.ip || h.ip || '').toLowerCase();
        const group = (Array.isArray(h.groups) ? h.groups.map(g => g.name).join(' ') : (h.group_name || '')).toLowerCase();
        return name.includes(query) || host.includes(query) || ip.includes(query) || group.includes(query);
    });
    renderZabbixHostsTable(filtered);
}

async function selectZabbixHostForPm(hostid) {
    const hostObj = zabbixHostsList.find(h => h.hostid == hostid);
    if (!hostObj) return;

    // Rellenar campos del formulario en Paso 1
    const deviceInput = document.getElementById('pm-survey-device');
    const hostnameInput = document.getElementById('pm-survey-hostname');
    const descInput = document.getElementById('pm-survey-description');

    if (deviceInput) deviceInput.value = hostObj.name || hostObj.host;
    if (hostnameInput) hostnameInput.value = hostObj.host;
    
    const hostIp = hostObj.interfaces?.[0]?.ip || hostObj.ip || '';
    const zabbixDesc = `Importado de Zabbix (ID: ${hostObj.hostid}${hostIp ? ', IP: ' + hostIp : ''}). ${hostObj.description || ''}`;
    if (descInput) descInput.value = zabbixDesc.trim();

    // Consultar interfaces/puertos del host
    toastr.info('Obteniendo detalles e interfaces desde Zabbix...');
    try {
        const resp = await fetch(`api_zabbix.php?action=get_interfaces_data&hostid=${hostid}`);
        const res = await resp.json();
        
        let interfaces = [];
        if (res.success && Array.isArray(res.data)) {
            interfaces = res.data;
        }

        // Helper para comprobar si una interfaz es física (excluir VLANs, Loopbacks, subinterfaces)
        function isPhysicalInterface(iface) {
            if (iface.is_physical === false) return false;
            const name = String(iface.interface_name || iface.name || iface.description || '').trim().toLowerCase();
            if (!name) return true;
            if (/\.[0-9]+$/.test(name)) return false; // Subinterfaces
            if (/^(vlan|vl|loopback|lo|null|tunnel|tun|bridge|br|docker|veth|virbr|stack|control|processor|virtual)[0-9_\-\:]*$/i.test(name)) return false;
            if (name.startsWith('vlan') || name.startsWith('loopback')) return false;
            return true;
        }

        // Filtrar SOLO interfaces físicas
        let physicalInterfaces = interfaces.filter(isPhysicalInterface);
        if (physicalInterfaces.length === 0 && interfaces.length > 0) {
            physicalInterfaces = interfaces; // Fallback si no hay físicas estrictas
        }

        if (physicalInterfaces.length > 0) {
            const countInput = document.getElementById('pm-survey-ports-count');
            if (countInput) countInput.value = Math.min(physicalInterfaces.length, 96);

            // Poblar arreglo de puertos pmSurveyPorts
            pmSurveyPorts = [];
            physicalInterfaces.forEach((iface, idx) => {
                const portIndex = idx + 1;
                if (portIndex > 96) return;
                
                const ifName = iface.interface_name || iface.name || iface.description || `Puerto ${portIndex}`;
                const rawStatus = String(iface.status || iface.oper_status || iface.link_status || '').toLowerCase();
                
                // Evaluar si el puerto tiene Link activo / conectado
                const isUp = (rawStatus === '1' || rawStatus === 'up' || rawStatus === 'connected' || rawStatus === 'monitored' || (iface.bits_sent && iface.bits_sent > 0) || (iface.bits_received && iface.bits_received > 0));
                
                let nomenclature = ifName;
                if (iface.alias && iface.alias.trim() !== '') {
                    nomenclature += ` (${iface.alias.trim()})`;
                }

                let destDevice = iface.connected_host_name || '';
                let destPort = iface.connected_port_name || '';

                if (isUp && !destDevice && iface.alias) {
                    destDevice = iface.alias;
                }

                pmSurveyPorts.push({
                    port_name: String(portIndex),
                    nomenclature: nomenclature,
                    rack_src: document.getElementById('pm-survey-rack')?.value || 'RACK-A01',
                    device_src: hostObj.name || hostObj.host,
                    cable_type: iface.cable_type || 'UTP Cat6A',
                    connector_type: iface.connector_type || 'RJ45',
                    speed: iface.speed || '1 Gbps',
                    link_status: isUp ? 'Up' : 'Down',
                    patch_panel_src: '',
                    port_pp_src: '',
                    ur_pp_src: '',
                    patch_panel_tgt: '',
                    port_pp_tgt: '',
                    ur_pp_tgt: '',
                    dest_device: destDevice,
                    dest_port: destPort,
                    rack_tgt: ''
                });
            });

            toastr.success(`Se importaron ${pmSurveyPorts.length} puertos físicos de Zabbix (excluyendo VLANs).`);
        } else {
            toastr.success('Información del equipo Zabbix cargada exitosamente.');
        }
    } catch(e) {
        toastr.success('Información general del equipo Zabbix cargada.');
    }

    renderPmPortsTable();
    autofillPpOrigen();
    $('#modal_select_zabbix_host').modal('hide');
}

// =========================================================================
// CONFIG FILES MANAGEMENT FUNCTIONS (STEP 5)
// =========================================================================
async function uploadPmConfigFile(e) {
    const file = e.target.files[0];
    if (!file) return;

    const data = new FormData();
    data.append('action', 'upload_config_file');
    data.append('config_file', file);

    toastr.info('Subiendo archivo de configuración...');
    try {
        const resp = await fetch('api_portmapping.php', { method: 'POST', body: data });
        const res = await resp.json();
        if (res.success && res.file) {
            pmSurveyConfigFiles.push(res.file);
            renderPmConfigFilesList();
            toastr.success('Archivo de configuración subido exitosamente.');
        } else {
            Swal.fire('Error', res.error || 'No se pudo subir el archivo de configuración.', 'error');
        }
    } catch(err) {
        toastr.error('Error al comunicarse con el servidor.');
    }
    e.target.value = '';
}

function renderPmConfigFilesList() {
    const tbody = document.getElementById('pm_config_files_table_body');
    if (!tbody) return;

    if (!pmSurveyConfigFiles || pmSurveyConfigFiles.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted"><i class="fas fa-folder-open fa-2x mb-2 d-block text-secondary"></i>No se han adjuntado archivos de configuración a este equipo.</td></tr>';
        return;
    }

    let html = '';
    pmSurveyConfigFiles.forEach((f, idx) => {
        const sizeKb = (f.size / 1024).toFixed(1);
        html += `
            <tr>
                <td><strong>${idx + 1}</strong></td>
                <td><i class="fas fa-file-code text-warning mr-2"></i><strong>${f.name}</strong></td>
                <td><span class="badge badge-light border">${sizeKb} KB</span></td>
                <td><small class="text-muted">${f.uploaded_at || new Date().toISOString().slice(0, 10)}</small></td>
                <td class="text-center">
                    <button type="button" class="btn btn-xs btn-outline-primary font-weight-bold" onclick="viewConfigFileContent(${idx})">
                        <i class="fas fa-eye mr-1"></i> Ver Contenido
                    </button>
                </td>
                <td class="text-center">
                    <a href="${f.path}" download="${f.name}" class="btn btn-xs btn-outline-success font-weight-bold mr-1">
                        <i class="fas fa-download"></i>
                    </a>
                    <button type="button" class="btn btn-xs btn-outline-danger font-weight-bold" onclick="removeConfigFile(${idx})">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
}

function viewConfigFileContent(idx) {
    const file = pmSurveyConfigFiles[idx];
    if (!file) return;

    document.getElementById('modal_cfg_file_title').innerHTML = `<i class="fas fa-code text-warning mr-2"></i> ${file.name}`;
    const preEl = document.getElementById('modal_cfg_file_content');
    
    if (file.content !== undefined && file.content !== null) {
        preEl.textContent = file.content;
        $('#modal_view_config_file').modal('show');
    } else if (file.path) {
        preEl.textContent = 'Cargando contenido del archivo...';
        $('#modal_view_config_file').modal('show');
        fetch(file.path)
            .then(res => res.text())
            .then(text => {
                preEl.textContent = text;
                file.content = text;
            })
            .catch(err => {
                preEl.textContent = 'No se pudo leer el contenido del archivo.';
            });
    }
}

function copyConfigFileContent() {
    const text = document.getElementById('modal_cfg_file_content')?.textContent;
    if (text) {
        navigator.clipboard.writeText(text);
        toastr.success('Contenido copiado al portapapeles.');
    }
}

function removeConfigFile(idx) {
    pmSurveyConfigFiles.splice(idx, 1);
    renderPmConfigFilesList();
    toastr.info('Archivo removido.');
}

// =========================================================================
// DIAGRAM MANAGEMENT FUNCTIONS (STEP 6)
// =========================================================================
async function uploadPmDiagramFile(e) {
    const file = e.target.files[0];
    if (!file) return;

    const data = new FormData();
    data.append('action', 'upload_diagram_file');
    data.append('diagram_file', file);

    toastr.info('Subiendo diagrama de red...');
    try {
        const resp = await fetch('api_portmapping.php', { method: 'POST', body: data });
        const res = await resp.json();
        if (res.success && res.file) {
            pmSurveyDiagram = {
                type: 'upload',
                title: file.name,
                name: file.name,
                path: res.file.path,
                ext: res.file.ext,
                include_in_pdf: document.getElementById('cfg_inc_diagram')?.checked ?? true
            };
            renderPmDiagramPreview();
            toastr.success('Diagrama subido exitosamente.');
        } else {
            Swal.fire('Error', res.error || 'No se pudo subir el diagrama.', 'error');
        }
    } catch(err) {
        toastr.error('Error al comunicarse con el servidor.');
    }
    e.target.value = '';
}

async function loadVisioModelsList() {
    const selectEl = document.getElementById('select_visio_model');
    if (!selectEl) return;

    selectEl.innerHTML = '<option value="">Cargando modelos Visio registrados...</option>';
    try {
        const resp = await fetch('api_visio.php?action=list');
        const res = await resp.json();
        const diagramsList = res.diagrams || res.data || [];

        if (res.success && Array.isArray(diagramsList) && diagramsList.length > 0) {
            // Obtener hostname/dispositivo actual del formulario
            const currentHostName = (document.getElementById('pm-survey-hostname')?.value || document.getElementById('pm-survey-device')?.value || '').trim().toLowerCase();
            const currentClientName = (new URLSearchParams(window.location.search).get('cliente') || '').trim().toLowerCase();

            let matchedDiagram = null;
            let optionsHtml = '<option value="">-- Seleccionar un diagrama Visio existente --</option>';

            diagramsList.forEach(v => {
                let isMatch = false;
                if (currentHostName && (
                    (v.ci_hostname && v.ci_hostname.toLowerCase() === currentHostName) ||
                    (v.title && v.title.toLowerCase().includes(currentHostName))
                )) {
                    isMatch = true;
                    if (!matchedDiagram) matchedDiagram = v;
                }

                let details = [];
                if (v.ci_hostname) details.push(`CI: [${v.category_name || 'CI'}] ${v.ci_hostname}`);
                if (v.client_name) details.push(`Cliente: ${v.client_name}`);

                let label = (v.title || 'Visio #' + v.id);
                if (details.length > 0) label += ` (${details.join(' - ')})`;
                if (isMatch) label = `⭐ [DIAGRAMA ASOCIADO AL EQUIPO] ${label}`;

                optionsHtml += `<option value="${v.id}" ${isMatch ? 'selected' : ''}>${label}</option>`;
            });

            selectEl.innerHTML = optionsHtml;

            // Si se encontró un diagrama asociado al equipo y aún no hay diagrama seleccionado en el survey, vincularlo automáticamente
            if (matchedDiagram && (!pmSurveyDiagram || pmSurveyDiagram.type !== 'visio')) {
                selectVisioModelForPm(matchedDiagram.id, false);
            }
        } else {
            selectEl.innerHTML = '<option value="">No hay modelos Visio disponibles en el sistema.</option>';
        }
    } catch(e) {
        selectEl.innerHTML = '<option value="">Error al cargar modelos Visio.</option>';
    }
}

function selectVisioModelForPm(visioId, showToast = true) {
    if (!visioId) {
        pmSurveyDiagram = null;
        renderPmDiagramPreview();
        return;
    }

    const selectEl = document.getElementById('select_visio_model');
    const selectedOpt = selectEl ? selectEl.options[selectEl.selectedIndex] : null;
    const selectedText = selectedOpt ? selectedOpt.text.replace('⭐ [DIAGRAMA ASOCIADO AL EQUIPO] ', '') : ('Visio #' + visioId);

    pmSurveyDiagram = {
        type: 'visio',
        visio_id: visioId,
        title: selectedText,
        name: selectedText,
        path: `api_visio.php?action=get_preview&id=${visioId}`,
        include_in_pdf: document.getElementById('cfg_inc_diagram')?.checked ?? true
    };
    renderPmDiagramPreview();
    if (showToast) toastr.success('Modelo Visio vinculado.');
}

function toggleIncludeDiagramInPdf(checked) {
    if (pmSurveyDiagram) {
        pmSurveyDiagram.include_in_pdf = checked;
    }
}

function renderPmDiagramPreview() {
    const container = document.getElementById('pm_diagram_preview_container');
    if (!container) return;

    if (!pmSurveyDiagram || (!pmSurveyDiagram.path && !pmSurveyDiagram.visio_id)) {
        container.innerHTML = `
            <div class="alert alert-light border text-center py-4 text-muted" style="border-radius:8px;">
                <i class="fas fa-sitemap fa-2x mb-2 d-block text-secondary"></i>
                No se ha adjuntado ni vinculado ningún diagrama aún.
            </div>
        `;
        return;
    }

    const ext = (pmSurveyDiagram.ext || '').toLowerCase();
    const isVisio = pmSurveyDiagram.type === 'visio';
    const isImage = ['png', 'jpg', 'jpeg', 'svg', 'gif', 'webp'].includes(ext) || isVisio;

    let previewMedia = '';
    if (isImage) {
        previewMedia = `
            <div class="p-2 bg-white rounded border shadow-2xs d-inline-block text-center">
                <img src="${pmSurveyDiagram.path}" style="max-height: 350px; width: auto; max-width: 100%; border-radius: 6px;" class="shadow-sm">
            </div>
        `;
    } else {
        previewMedia = `
            <div class="p-4 bg-white border rounded text-center shadow-2xs">
                <i class="fas fa-file-invoice fa-3x text-info mb-2"></i>
                <h6 class="font-weight-bold text-navy mb-1">${pmSurveyDiagram.name || pmSurveyDiagram.title}</h6>
                <span class="badge badge-light border mb-3">Archivo formato .${(pmSurveyDiagram.ext || 'vsdx').toUpperCase()}</span>
                <div>
                    <a href="${pmSurveyDiagram.path}" download="${pmSurveyDiagram.name}" class="btn btn-sm btn-primary font-weight-bold px-3">
                        <i class="fas fa-download mr-1"></i> Descargar Diagrama Visio
                    </a>
                </div>
            </div>
        `;
    }

    let visioEditorBtn = isVisio ? `
        <a href="visio.php?id=${pmSurveyDiagram.visio_id}" target="_blank" class="btn btn-xs btn-outline-info font-weight-bold mr-2 px-2" title="Abrir y editar en el modelador Visio / Draw.io">
            <i class="fas fa-external-link-alt mr-1"></i> Abrir en Editor Visio
        </a>
    ` : '';

    container.innerHTML = `
        <div class="card border shadow-sm" style="border-radius: 10px; overflow: hidden;">
            <div class="card-header bg-navy text-white d-flex justify-content-between align-items-center py-2">
                <span class="font-weight-bold small">
                    <i class="fas fa-project-diagram text-warning mr-2"></i> ${pmSurveyDiagram.title || pmSurveyDiagram.name}
                    ${isVisio ? '<span class="badge badge-success ml-2 px-2"><i class="fas fa-check-circle mr-1"></i>Diagrama Visio CMDB</span>' : ''}
                </span>
                <div>
                    ${visioEditorBtn}
                    <button type="button" class="btn btn-xs btn-outline-danger font-weight-bold" onclick="removePmDiagram()">
                        <i class="fas fa-times mr-1"></i> Quitar Diagrama
                    </button>
                </div>
            </div>
            <div class="card-body p-3 bg-light text-center">
                ${previewMedia}
            </div>
        </div>
    `;
}

function removePmDiagram() {
    pmSurveyDiagram = null;
    renderPmDiagramPreview();
    toastr.info('Diagrama removido.');
}

/* ==========================================================================
   MÓDULO DE ANÁLISIS VILASECA (TAB 04)
   ========================================================================== */

let g_vilasecaAnalysisData = null;
let g_anFilterLocation = 'all';
let g_anFilterType = 'all';
let g_anFilterSearch = '';
let g_anSelectedRackLocation = 'all';
let g_chartAnTypes = null;
let g_chartAnLocations = null;

const AN_TYPE_COLORS = {
    'Switch': '#0056b3',
    'Patch Panel': '#6f42c1',
    'Router': '#fd7e14',
    'Firewall': '#dc3545',
    'Otro': '#17a2b8',
    'Servidor': '#20c997',
    'Access Point': '#e83e8c'
};

const AN_TYPE_ICONS = {
    'Switch': 'fa-network-wired',
    'Patch Panel': 'fa-ethernet',
    'Router': 'fa-route',
    'Firewall': 'fa-shield-alt',
    'Otro': 'fa-microchip',
    'Servidor': 'fa-server',
    'Access Point': 'fa-wifi'
};

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function getAnTypeColor(type) {
    return AN_TYPE_COLORS[type] || '#6c757d';
}

function getAnTypeIcon(type) {
    return AN_TYPE_ICONS[type] || 'fa-hdd';
}

async function loadSurveyAnalysisTab(forceReload = false) {
    activatePmTab('survey-analysis-content');
    
    // Actualizar parámetro en la URL sin recargar
    try {
        const url = new URL(window.location);
        url.searchParams.set('tab', 'analisis');
        if (!url.searchParams.get('cliente')) {
            url.searchParams.set('cliente', 'VILASECA');
        }
        window.history.replaceState({}, '', url);
    } catch(e) {}

    if (g_vilasecaAnalysisData && !forceReload) {
        renderSurveyAnalysisDashboard();
        return;
    }

    const badgeStatus = document.getElementById('an_badge_status');
    if (badgeStatus) {
        badgeStatus.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Sincronizando...';
    }

    try {
        const urlParams = new URLSearchParams(window.location.search);
        const clientParam = urlParams.get('cliente') || 'VILASECA';
        const resp = await fetch(`api_portmapping.php?action=get_vilaseca_analysis&client=${encodeURIComponent(clientParam)}`);
        const res = await resp.json();
        
        if (res.success) {
            g_vilasecaAnalysisData = res;
            if (badgeStatus) {
                badgeStatus.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Datos Auditados en Vivo';
            }
            populateAnalysisDropdowns();
            renderSurveyAnalysisDashboard();
        } else {
            if (typeof toastr !== 'undefined') toastr.error(res.error || 'Error cargando datos de análisis.');
        }
    } catch (err) {
        console.error('Error fetching Vilaseca analysis:', err);
        if (typeof toastr !== 'undefined') toastr.error('Error de conexión al cargar análisis.');
    }
}

function populateAnalysisDropdowns() {
    if (!g_vilasecaAnalysisData) return;

    // Localidades
    const locSelect = document.getElementById('an_filter_location');
    if (locSelect) {
        let currentLoc = locSelect.value || 'all';
        let locOptions = '<option value="all">Todas las Localidades (' + (g_vilasecaAnalysisData.by_location.length) + ')</option>';
        g_vilasecaAnalysisData.by_location.forEach(l => {
            locOptions += `<option value="${escapeHtml(l.location)}">${escapeHtml(l.location)} (${l.devices} equipos)</option>`;
        });
        locSelect.innerHTML = locOptions;
        locSelect.value = currentLoc;
    }

    // Tipos
    const typeSelect = document.getElementById('an_filter_type');
    if (typeSelect) {
        let currentType = typeSelect.value || 'all';
        let typeOptions = '<option value="all">Todos los Tipos (' + (g_vilasecaAnalysisData.by_type.length) + ')</option>';
        g_vilasecaAnalysisData.by_type.forEach(t => {
            typeOptions += `<option value="${escapeHtml(t.type)}">${escapeHtml(t.type)} (${t.devices} equipos)</option>`;
        });
        typeSelect.innerHTML = typeOptions;
        typeSelect.value = currentType;
    }
}

function applyAnalysisFilters() {
    const locSelect = document.getElementById('an_filter_location');
    const typeSelect = document.getElementById('an_filter_type');
    const searchInput = document.getElementById('an_filter_search');

    g_anFilterLocation = locSelect ? locSelect.value : 'all';
    g_anFilterType = typeSelect ? typeSelect.value : 'all';
    g_anFilterSearch = searchInput ? searchInput.value.toLowerCase().trim() : '';

    renderSurveyAnalysisDashboard();
}

function resetAnalysisFilters() {
    const locSelect = document.getElementById('an_filter_location');
    const typeSelect = document.getElementById('an_filter_type');
    const searchInput = document.getElementById('an_filter_search');

    if (locSelect) locSelect.value = 'all';
    if (typeSelect) typeSelect.value = 'all';
    if (searchInput) searchInput.value = '';

    g_anFilterLocation = 'all';
    g_anFilterType = 'all';
    g_anFilterSearch = '';
    g_anSelectedRackLocation = 'all';

    renderSurveyAnalysisDashboard();
}

function clearAnalysisSearch() {
    const searchInput = document.getElementById('an_filter_search');
    if (searchInput) searchInput.value = '';
    g_anFilterSearch = '';
    applyAnalysisFilters();
}

function quickFilterAnalysisByType(type) {
    const typeSelect = document.getElementById('an_filter_type');
    if (typeSelect) {
        typeSelect.value = type;
        applyAnalysisFilters();
    }
}

function quickFilterAnalysisByLocation(location) {
    const locSelect = document.getElementById('an_filter_location');
    if (locSelect) {
        locSelect.value = location;
        applyAnalysisFilters();
    }
}

function renderSurveyAnalysisDashboard() {
    if (!g_vilasecaAnalysisData) return;

    // Filtrar equipos
    const allDevs = g_vilasecaAnalysisData.all_devices || [];
    let filteredDevs = allDevs.filter(d => {
        if (g_anFilterLocation !== 'all' && d.location !== g_anFilterLocation) return false;
        if (g_anFilterType !== 'all' && d.device_type !== g_anFilterType) return false;
        if (g_anFilterSearch) {
            const str = (d.device_name + ' ' + d.device_type + ' ' + d.location + ' ' + d.rack + ' ' + (d.ip_address || '')).toLowerCase();
            if (!str.includes(g_anFilterSearch)) return false;
        }
        return true;
    });

    renderAnalysisKPIs(filteredDevs);
    renderAnalysisTypeDistribution();
    renderAnalysisLocationDistribution();
    renderAnalysisTopVacant(filteredDevs);
    renderAnalysisTopOccupied(filteredDevs);
    renderAnalysisRacks();
    renderAnalysisInventoryTable(filteredDevs);
}

function renderAnalysisKPIs(devsList) {
    const totalDevs = devsList.length;
    let totalPorts = 0;
    let totalOcc = 0;
    let totalVac = 0;
    const locationsSet = new Set();
    const racksSet = new Set();
    let switchesCount = 0;
    let patchPanelsCount = 0;

    devsList.forEach(d => {
        totalPorts += (parseInt(d.total_ports) || 0);
        totalOcc += (parseInt(d.occupied_ports) || 0);
        totalVac += (parseInt(d.vacant_ports) || 0);
        if (d.location) locationsSet.add(d.location);
        if (d.location && d.rack) racksSet.add(d.location + '___' + d.rack);
        if (d.device_type === 'Switch') switchesCount++;
        if (d.device_type === 'Patch Panel' || (d.device_type && d.device_type.toLowerCase().includes('patch'))) patchPanelsCount++;
    });

    const occPct = totalPorts > 0 ? ((totalOcc / totalPorts) * 100).toFixed(1) : 0;

    const elTotalDevs = document.getElementById('an_kpi_total_devices');
    const elDevsBreakdown = document.getElementById('an_kpi_devices_breakdown');
    if (elTotalDevs) elTotalDevs.textContent = totalDevs;
    if (elDevsBreakdown) elDevsBreakdown.textContent = `${switchesCount} Switches | ${patchPanelsCount} Patch Panels | ${totalDevs - switchesCount - patchPanelsCount} Otros`;

    const elLocations = document.getElementById('an_kpi_total_locations');
    const elLocsBreakdown = document.getElementById('an_kpi_locations_breakdown');
    if (elLocations) elLocations.textContent = locationsSet.size;
    if (elLocsBreakdown) elLocsBreakdown.textContent = `${locationsSet.size} Sedes Registradas`;

    const elRacks = document.getElementById('an_kpi_total_racks');
    const elRacksBreakdown = document.getElementById('an_kpi_racks_breakdown');
    if (elRacks) elRacks.textContent = racksSet.size;
    if (elRacksBreakdown) {
        const avg = racksSet.size > 0 ? (totalDevs / racksSet.size).toFixed(1) : 0;
        elRacksBreakdown.textContent = `Prom. ${avg} equipos / rack`;
    }

    const elPorts = document.getElementById('an_kpi_total_ports');
    const elPortsBreakdown = document.getElementById('an_kpi_ports_breakdown');
    if (elPorts) elPorts.textContent = totalPorts;
    if (elPortsBreakdown) elPortsBreakdown.innerHTML = `<span class="text-success font-weight-bold">${totalVac} Libres</span> | <span class="text-primary font-weight-bold">${totalOcc} Ocupados</span>`;

    const elOccPct = document.getElementById('an_kpi_occupancy_pct');
    const elOccBar = document.getElementById('an_kpi_occupancy_bar');
    if (elOccPct) elOccPct.textContent = `${occPct}%`;
    if (elOccBar) {
        elOccBar.style.width = `${occPct}%`;
        if (occPct > 80) elOccBar.className = 'progress-bar bg-danger';
        else if (occPct > 50) elOccBar.className = 'progress-bar bg-warning';
        else elOccBar.className = 'progress-bar bg-success';
    }
}

function renderAnalysisTypeDistribution() {
    const typesData = g_vilasecaAnalysisData.by_type || [];
    const badgeTypesCount = document.getElementById('an_badge_types_count');
    if (badgeTypesCount) badgeTypesCount.textContent = `${typesData.length} tipos`;

    // Tabla / Lista de Tipos
    const listContainer = document.getElementById('an_types_list_container');
    if (listContainer) {
        let listHtml = '<table class="table table-sm table-borderless align-middle m-0">';
        typesData.forEach(t => {
            const color = getAnTypeColor(t.type);
            const icon = getAnTypeIcon(t.type);
            const isSelected = g_anFilterType === t.type;
            listHtml += `
                <tr style="cursor: pointer; ${isSelected ? 'background: #eff6ff; font-weight: bold;' : ''}" onclick="quickFilterAnalysisByType('${escapeHtml(t.type)}')">
                    <td style="width: 25px;"><i class="fas ${icon}" style="color: ${color};"></i></td>
                    <td><strong class="text-dark">${escapeHtml(t.type)}</strong></td>
                    <td class="text-center"><span class="badge badge-pill text-white px-2 py-1" style="background:${color}; font-weight:700;">${t.devices} eq.</span></td>
                    <td class="text-right text-muted small">${t.total_ports} pts (${t.occupancy_pct}%)</td>
                </tr>
            `;
        });
        listHtml += '</table>';
        listContainer.innerHTML = listHtml;
    }

    // Render Doughnut Chart
    if (typeof Chart === 'undefined') return;
    const canvas = document.getElementById('chart_an_types');
    if (!canvas) return;

    if (g_chartAnTypes) {
        g_chartAnTypes.destroy();
        g_chartAnTypes = null;
    }

    const labels = typesData.map(t => t.type);
    const counts = typesData.map(t => t.devices);
    const bgColors = typesData.map(t => getAnTypeColor(t.type));

    g_chartAnTypes = new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: counts,
                backgroundColor: bgColors,
                borderWidth: 2,
                borderColor: '#ffffff',
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const pct = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                            return ` ${label}: ${value} equipos (${pct}%)`;
                        }
                    }
                }
            },
            onClick: (e, elements) => {
                if (elements && elements.length > 0) {
                    const idx = elements[0].index;
                    const clickedType = labels[idx];
                    quickFilterAnalysisByType(clickedType);
                }
            }
        }
    });
}

function renderAnalysisLocationDistribution() {
    const locData = g_vilasecaAnalysisData.by_location || [];
    const badgeLocCount = document.getElementById('an_badge_locations_count');
    if (badgeLocCount) badgeLocCount.textContent = `${locData.length} sedes`;

    if (typeof Chart === 'undefined') return;
    const canvas = document.getElementById('chart_an_locations');
    if (!canvas) return;

    if (g_chartAnLocations) {
        g_chartAnLocations.destroy();
        g_chartAnLocations = null;
    }

    const labels = locData.map(l => l.location);
    const devCounts = locData.map(l => l.devices);
    const portCounts = locData.map(l => l.total_ports);

    g_chartAnLocations = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Equipos Registrados',
                    data: devCounts,
                    backgroundColor: '#002B49',
                    borderRadius: 6,
                    barPercentage: 0.6
                },
                {
                    label: 'Total Puertos',
                    data: portCounts,
                    backgroundColor: '#17a2b8',
                    borderRadius: 6,
                    barPercentage: 0.6
                }
            ]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        boxWidth: 12,
                        font: { weight: 'bold', size: 11 }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    grid: { color: '#f1f5f9' }
                },
                y: {
                    grid: { display: false },
                    ticks: {
                        font: { weight: 'bold', size: 11 }
                    }
                }
            },
            onClick: (e, elements) => {
                if (elements && elements.length > 0) {
                    const idx = elements[0].index;
                    const clickedLoc = labels[idx];
                    quickFilterAnalysisByLocation(clickedLoc);
                }
            }
        }
    });
}

function renderAnalysisTopVacant(devsList) {
    const tbody = document.getElementById('tbl_an_top_vacant_body');
    if (!tbody) return;

    const sorted = [...devsList].sort((a, b) => {
        if (b.vacant_ports === a.vacant_ports) {
            return b.vacant_pct - a.vacant_pct;
        }
        return b.vacant_ports - a.vacant_ports;
    }).slice(0, 10);

    if (sorted.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No hay equipos que coincidan con los filtros.</td></tr>';
        return;
    }

    let html = '';
    sorted.forEach((d, idx) => {
        const rank = idx + 1;
        const rankClass = rank === 1 ? 'rank-gold' : (rank === 2 ? 'rank-silver' : (rank === 3 ? 'rank-bronze' : 'rank-normal'));
        const typeColor = getAnTypeColor(d.device_type);
        const vacPct = d.total_ports > 0 ? ((d.vacant_ports / d.total_ports) * 100).toFixed(0) : 0;

        html += `
            <tr>
                <td class="text-center"><span class="rank-badge-pill ${rankClass}">${rank}</span></td>
                <td>
                    <div class="font-weight-bold text-navy">${escapeHtml(d.device_name)}</div>
                    <span class="badge text-white" style="background:${typeColor}; font-size:10px;">${escapeHtml(d.device_type)}</span>
                </td>
                <td>
                    <div class="small font-weight-bold text-dark"><i class="fas fa-map-marker-alt text-danger mr-1"></i>${escapeHtml(d.location)}</div>
                    <div class="small text-muted"><i class="fas fa-server mr-1"></i>${escapeHtml(d.rack)} ${d.ur_rack ? '(UR ' + escapeHtml(d.ur_rack) + ')' : ''}</div>
                </td>
                <td class="text-center font-weight-bold text-secondary">${d.total_ports}</td>
                <td class="text-center">
                    <span class="badge badge-success px-2 py-1 font-weight-bold" style="font-size:12px;">
                        <i class="fas fa-check mr-1"></i>${d.vacant_ports} Libres
                    </span>
                    <div class="progress mt-1" style="height: 4px; background: #e2e8f0;">
                        <div class="progress-bar bg-success" style="width: ${vacPct}%;"></div>
                    </div>
                </td>
                <td class="text-center">
                    <button class="btn btn-xs btn-outline-success font-weight-bold" onclick="openDeviceSurveyFromAnalysis(${d.id})" title="Ver Puertos en Asistente">
                        <i class="fas fa-eye mr-1"></i> Ver
                    </button>
                </td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
}

function renderAnalysisTopOccupied(devsList) {
    const tbody = document.getElementById('tbl_an_top_occupied_body');
    if (!tbody) return;

    const sorted = [...devsList].sort((a, b) => {
        if (b.occupied_ports === a.occupied_ports) {
            return b.occupancy_pct - a.occupancy_pct;
        }
        return b.occupied_ports - a.occupied_ports;
    }).slice(0, 10);

    if (sorted.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No hay equipos que coincidan con los filtros.</td></tr>';
        return;
    }

    let html = '';
    sorted.forEach((d, idx) => {
        const rank = idx + 1;
        const rankClass = rank === 1 ? 'rank-gold' : (rank === 2 ? 'rank-silver' : (rank === 3 ? 'rank-bronze' : 'rank-normal'));
        const typeColor = getAnTypeColor(d.device_type);
        const occPct = d.total_ports > 0 ? ((d.occupied_ports / d.total_ports) * 100).toFixed(0) : 0;
        const badgeColor = occPct >= 90 ? 'badge-danger' : (occPct >= 50 ? 'badge-warning text-dark' : 'badge-primary');

        html += `
            <tr>
                <td class="text-center"><span class="rank-badge-pill ${rankClass}">${rank}</span></td>
                <td>
                    <div class="font-weight-bold text-navy">${escapeHtml(d.device_name)}</div>
                    <span class="badge text-white" style="background:${typeColor}; font-size:10px;">${escapeHtml(d.device_type)}</span>
                </td>
                <td>
                    <div class="small font-weight-bold text-dark"><i class="fas fa-map-marker-alt text-danger mr-1"></i>${escapeHtml(d.location)}</div>
                    <div class="small text-muted"><i class="fas fa-server mr-1"></i>${escapeHtml(d.rack)} ${d.ur_rack ? '(UR ' + escapeHtml(d.ur_rack) + ')' : ''}</div>
                </td>
                <td class="text-center font-weight-bold text-secondary">${d.total_ports}</td>
                <td class="text-center">
                    <span class="badge ${badgeColor} px-2 py-1 font-weight-bold" style="font-size:12px;">
                        <i class="fas fa-link mr-1"></i>${d.occupied_ports} Ocupados (${occPct}%)
                    </span>
                    <div class="progress mt-1" style="height: 4px; background: #e2e8f0;">
                        <div class="progress-bar ${occPct >= 80 ? 'bg-danger' : 'bg-primary'}" style="width: ${occPct}%;"></div>
                    </div>
                </td>
                <td class="text-center">
                    <button class="btn btn-xs btn-outline-primary font-weight-bold" onclick="openDeviceSurveyFromAnalysis(${d.id})" title="Ver Puertos en Asistente">
                        <i class="fas fa-eye mr-1"></i> Ver
                    </button>
                </td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
}

function renderAnalysisRacks() {
    const pillsContainer = document.getElementById('an_rack_loc_pills_container');
    const gridContainer = document.getElementById('an_racks_grid_container');
    if (!pillsContainer || !gridContainer || !g_vilasecaAnalysisData) return;

    const locs = g_vilasecaAnalysisData.by_location || [];
    const allRacks = g_vilasecaAnalysisData.top_racks_by_location || [];

    // Render Pills
    let pillsHtml = `
        <button type="button" class="btn btn-xs btn-outline-navy pill-loc-btn ${g_anSelectedRackLocation === 'all' ? 'active' : ''}" onclick="filterRacksByLocPill('all')">
            Todas (${allRacks.length} Racks)
        </button>
    `;
    locs.forEach(l => {
        const isAct = g_anSelectedRackLocation === l.location;
        const rCount = (l.racks_detail || []).length;
        pillsHtml += `
            <button type="button" class="btn btn-xs btn-outline-navy pill-loc-btn ${isAct ? 'active' : ''}" onclick="filterRacksByLocPill('${escapeHtml(l.location)}')">
                ${escapeHtml(l.location)} (${rCount})
            </button>
        `;
    });
    pillsContainer.innerHTML = pillsHtml;

    // Filter Racks
    let filteredRacks = allRacks;
    if (g_anSelectedRackLocation !== 'all') {
        filteredRacks = allRacks.filter(r => r.location === g_anSelectedRackLocation);
    }

    if (filteredRacks.length === 0) {
        gridContainer.innerHTML = '<div class="col-12 text-center py-4 text-muted"><i class="fas fa-info-circle mr-1"></i> No hay racks registrados para la localidad seleccionada.</div>';
        return;
    }

    const maxDevs = Math.max(...allRacks.map(r => r.device_count || 1), 1);

    let gridHtml = '';
    filteredRacks.forEach((r, idx) => {
        const rank = idx + 1;
        const rankClass = rank === 1 ? 'rank-gold' : (rank === 2 ? 'rank-silver' : (rank === 3 ? 'rank-bronze' : 'rank-normal'));
        const densityPct = Math.round((r.device_count / maxDevs) * 100);

        let typesChips = '';
        if (r.types) {
            Object.keys(r.types).forEach(tName => {
                const color = getAnTypeColor(tName);
                typesChips += `<span class="badge text-white mr-1 mb-1" style="background:${color}; font-size:10px;">${r.types[tName]} ${escapeHtml(tName)}</span>`;
            });
        }

        let devNamesList = (r.devices || []).map(d => d.name).slice(0, 5).join(', ');
        if ((r.devices || []).length > 5) devNamesList += ` y ${(r.devices.length - 5)} más...`;

        gridHtml += `
            <div class="col-xl-4 col-md-6 mb-3">
                <div class="rack-card-box p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="d-flex align-items-center">
                            <span class="rank-badge-pill ${rankClass} mr-2">${rank}</span>
                            <div>
                                <h6 class="font-weight-bold m-0 text-navy">
                                    <i class="fas fa-server text-warning mr-1"></i> ${escapeHtml(r.rack)}
                                </h6>
                                <span class="badge badge-light border text-secondary small" style="font-size:10px;">
                                    <i class="fas fa-map-marker-alt text-danger mr-1"></i> ${escapeHtml(r.location)}
                                </span>
                            </div>
                        </div>
                        <span class="badge badge-navy text-white px-2 py-1 font-weight-bold" style="font-size:13px;">
                            ${r.device_count} Equipos
                        </span>
                    </div>

                    <div class="d-flex justify-content-between text-muted small mb-1">
                        <span>Densidad en Localidad</span>
                        <strong class="text-navy">${r.total_ports || 0} Puertos Alojados</strong>
                    </div>
                    <div class="progress mb-2" style="height: 6px; background: #e2e8f0; border-radius: 4px;">
                        <div class="progress-bar bg-gradient-navy" style="width: ${densityPct}%;"></div>
                    </div>

                    <div class="mb-2">
                        ${typesChips}
                    </div>

                    <div class="p-2 rounded bg-light border small text-muted text-truncate" title="${escapeHtml(devNamesList)}">
                        <strong class="text-dark">Equipos:</strong> ${escapeHtml(devNamesList || 'Sin equipos asignados')}
                    </div>
                </div>
            </div>
        `;
    });
    gridContainer.innerHTML = gridHtml;
}

function filterRacksByLocPill(location) {
    g_anSelectedRackLocation = location;
    renderAnalysisRacks();
}

let g_anCurrentPage = 1;
let g_anLastFilteredDevs = [];

function onAnInventorySearchChange() {
    g_anCurrentPage = 1;
    renderAnalysisInventoryTable(g_anLastFilteredDevs || []);
}

function changeAnInvPage(page) {
    g_anCurrentPage = page;
    renderAnalysisInventoryTable(g_anLastFilteredDevs || []);
}

function renderAnalysisInventoryTable(devsList) {
    g_anLastFilteredDevs = devsList;
    const tbody = document.getElementById('tbl_an_inventory_body');
    const badgeCount = document.getElementById('an_inventory_count_badge');
    const infoContainer = document.getElementById('an_inv_table_info');
    const paginationContainer = document.getElementById('an_inv_pagination');
    const searchInput = document.getElementById('an_inv_table_search');
    const pageSizeSelect = document.getElementById('an_inv_page_size');

    if (!tbody) return;

    let searchTerm = searchInput ? (searchInput.value || '').toLowerCase().trim() : '';
    let pageSize = pageSizeSelect ? parseInt(pageSizeSelect.value) : 10;
    if (isNaN(pageSize)) pageSize = 10;

    let filtered = devsList.filter(item => {
        if (!searchTerm) return true;
        const text = `${item.id} ${item.device_name || ''} ${item.device_type || ''} ${item.location || ''} ${item.rack || ''} ${item.ip_address || ''}`.toLowerCase();
        return text.includes(searchTerm);
    });

    if (badgeCount) {
        badgeCount.textContent = `${filtered.length} de ${devsList.length} equipos`;
    }

    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="11" class="text-center py-4 text-muted"><i class="fas fa-search mr-2"></i> No se encontraron equipos con los criterios actuales.</td></tr>';
        if (infoContainer) infoContainer.innerHTML = '<span class="text-muted"><i class="fas fa-info-circle mr-1"></i>0 registros encontrados</span>';
        if (paginationContainer) paginationContainer.innerHTML = '';
        return;
    }

    const totalPages = pageSize === -1 ? 1 : Math.max(1, Math.ceil(filtered.length / pageSize));
    if (g_anCurrentPage > totalPages) g_anCurrentPage = totalPages;
    if (g_anCurrentPage < 1) g_anCurrentPage = 1;

    const startIndex = pageSize === -1 ? 0 : (g_anCurrentPage - 1) * pageSize;
    const endIndex = pageSize === -1 ? filtered.length : Math.min(startIndex + pageSize, filtered.length);
    const pageItems = filtered.slice(startIndex, endIndex);

    let html = '';
    pageItems.forEach(item => {
        const typeColor = getAnTypeColor(item.device_type);
        const icon = getAnTypeIcon(item.device_type);
        const occPct = item.total_ports > 0 ? ((item.occupied_ports / item.total_ports) * 100).toFixed(0) : 0;
        const barColor = occPct >= 90 ? 'bg-danger' : (occPct >= 50 ? 'bg-warning' : 'bg-success');

        html += `
            <tr>
                <td class="text-center font-weight-bold"><span class="badge badge-dark">#${item.id}</span></td>
                <td>
                    <div class="font-weight-bold text-navy">
                        <i class="fas ${icon} mr-1" style="color:${typeColor};"></i> ${escapeHtml(item.device_name)}
                    </div>
                    ${item.device_label ? `<small class="text-muted">${escapeHtml(item.device_label)}</small>` : ''}
                </td>
                <td>
                    <span class="badge text-white px-2 py-1" style="background:${typeColor};">
                        ${escapeHtml(item.device_type)}
                    </span>
                </td>
                <td>
                    <i class="fas fa-map-marker-alt text-danger mr-1 small"></i>
                    <strong class="text-dark">${escapeHtml(item.location)}</strong>
                    ${item.area ? `<br><small class="text-muted">${escapeHtml(item.area)}</small>` : ''}
                </td>
                <td>
                    <strong class="text-navy"><i class="fas fa-server mr-1 text-warning"></i>${escapeHtml(item.rack)}</strong>
                    ${item.ur_rack ? `<br><small class="text-muted">UR: ${escapeHtml(item.ur_rack)}</small>` : ''}
                </td>
                <td><code>${escapeHtml(item.ip_address || '-')}</code></td>
                <td class="text-center font-weight-bold text-dark">${item.total_ports}</td>
                <td class="text-center font-weight-bold text-primary">${item.occupied_ports}</td>
                <td class="text-center font-weight-bold text-success">${item.vacant_ports}</td>
                <td>
                    <div class="d-flex justify-content-between small font-weight-bold mb-1">
                        <span>${occPct}%</span>
                        <span class="text-muted">${item.occupied_ports}/${item.total_ports}</span>
                    </div>
                    <div class="progress" style="height: 5px; background: #e2e8f0;">
                        <div class="progress-bar ${barColor}" style="width: ${occPct}%;"></div>
                    </div>
                </td>
                <td class="text-center">
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary font-weight-bold" onclick="openDeviceSurveyFromAnalysis(${item.id})" title="Abrir y mapear puertos">
                            <i class="fas fa-tools"></i> Mapear
                        </button>
                        <button class="btn btn-outline-danger font-weight-bold" onclick="exportPmSurveyPDF(${item.id})" title="Exportar PDF">
                            <i class="fas fa-file-pdf"></i>
                        </button>
                        <button class="btn btn-outline-success font-weight-bold" onclick="exportPmSurveyExcel(${item.id})" title="Exportar Excel">
                            <i class="fas fa-file-excel"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    tbody.innerHTML = html;

    // Renderizar pie de tabla
    if (infoContainer) {
        const startNum = pageSize === -1 ? 1 : startIndex + 1;
        const endNum = endIndex;
        infoContainer.innerHTML = `Mostrando <strong>${startNum}</strong> a <strong>${endNum}</strong> de <strong>${filtered.length}</strong> registros ${filtered.length !== devsList.length ? '<span class="text-muted">(filtrados de ' + devsList.length + ' totales)</span>' : ''}`;
    }

    if (paginationContainer) {
        if (pageSize === -1 || totalPages <= 1) {
            paginationContainer.innerHTML = '';
        } else {
            let pagHtml = '';
            pagHtml += `<button type="button" class="sonda-page-btn ${g_anCurrentPage === 1 ? 'disabled' : ''}" ${g_anCurrentPage > 1 ? 'onclick="changeAnInvPage(' + (g_anCurrentPage - 1) + ')"' : ''}><i class="fas fa-chevron-left"></i></button>`;
            for (let p = 1; p <= totalPages; p++) {
                if (p === 1 || p === totalPages || (p >= g_anCurrentPage - 2 && p <= g_anCurrentPage + 2)) {
                    pagHtml += `<button type="button" class="sonda-page-btn ${p === g_anCurrentPage ? 'active' : ''}" onclick="changeAnInvPage(${p})">${p}</button>`;
                } else if (p === g_anCurrentPage - 3 || p === g_anCurrentPage + 3) {
                    pagHtml += `<span class="px-1 text-muted" style="user-select:none;">...</span>`;
                }
            }
            pagHtml += `<button type="button" class="sonda-page-btn ${g_anCurrentPage === totalPages ? 'disabled' : ''}" ${g_anCurrentPage < totalPages ? 'onclick="changeAnInvPage(' + (g_anCurrentPage + 1) + ')"' : ''}><i class="fas fa-chevron-right"></i></button>`;
            paginationContainer.innerHTML = pagHtml;
        }
    }
}

function openDeviceSurveyFromAnalysis(surveyId) {
    if (typeof loadManualSurveyDetail === 'function') {
        loadManualSurveyDetail(surveyId);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

function exportVilasecaAnalysisCSV() {
    if (!g_vilasecaAnalysisData) {
        if (typeof toastr !== 'undefined') toastr.warning('No hay datos de análisis para exportar.');
        return;
    }

    const s = g_vilasecaAnalysisData.summary || {};
    const devs = g_vilasecaAnalysisData.all_devices || [];

    let csv = '\uFEFF'; // UTF-8 BOM
    csv += 'INFORME DE ANALISIS DE INFRAESTRUCTURA - CMDB VILASECA\n';
    csv += `Generado el,${new Date().toLocaleString()}\n`;
    csv += `Total Equipos,${s.total_devices || 0}\n`;
    csv += `Total Localidades,${s.total_locations || 0}\n`;
    csv += `Total Racks,${s.total_racks || 0}\n`;
    csv += `Total Puertos,${s.total_ports || 0}\n`;
    csv += `Puertos Ocupados,${s.total_occupied || 0}\n`;
    csv += `Puertos Vacios,${s.total_vacant || 0}\n`;
    csv += `Porcentaje Ocupacion,${s.occupancy_pct || 0}%\n\n`;

    csv += '--- DISTRIBUCION POR TIPO DE EQUIPO ---\n';
    csv += 'Tipo,Cantidad Equipos,Total Puertos,Puertos Ocupados,Puertos Vacios,Ocupacion %\n';
    (g_vilasecaAnalysisData.by_type || []).forEach(t => {
        csv += `"${t.type}",${t.devices},${t.total_ports},${t.occupied_ports},${t.vacant_ports},${t.occupancy_pct}%\n`;
    });
    csv += '\n';

    csv += '--- DISTRIBUCION POR LOCALIDAD ---\n';
    csv += 'Localidad,Cantidad Equipos,Cantidad Racks,Total Puertos,Puertos Ocupados,Puertos Vacios,Ocupacion %\n';
    (g_vilasecaAnalysisData.by_location || []).forEach(l => {
        csv += `"${l.location}",${l.devices},${l.racks_count},${l.total_ports},${l.occupied_ports},${l.vacant_ports},${l.occupancy_pct}%\n`;
    });
    csv += '\n';

    csv += '--- RACKS CON MAYOR CANTIDAD DE EQUIPOS POR LOCALIDAD ---\n';
    csv += 'Localidad,Rack,Cantidad Equipos,Total Puertos,Puertos Ocupados,Puertos Vacios\n';
    (g_vilasecaAnalysisData.top_racks_by_location || []).forEach(r => {
        csv += `"${r.location}","${r.rack}",${r.device_count},${r.total_ports},${r.occupied_ports},${r.vacant_ports}\n`;
    });
    csv += '\n';

    csv += '--- TOP EQUIPOS CON PUERTOS VACIOS (MAYOR DISPONIBILIDAD) ---\n';
    csv += 'ID,Equipo,Tipo,Localidad,Rack,Total Puertos,Puertos Vacios,Puertos Ocupados,Disponibilidad %\n';
    (g_vilasecaAnalysisData.top_vacant || []).forEach(d => {
        csv += `${d.id},"${d.device_name}","${d.device_type}","${d.location}","${d.rack}",${d.total_ports},${d.vacant_ports},${d.occupied_ports},${d.vacant_pct}%\n`;
    });
    csv += '\n';

    csv += '--- TOP EQUIPOS LLENOS (MAYOR OCUPACION) ---\n';
    csv += 'ID,Equipo,Tipo,Localidad,Rack,Total Puertos,Puertos Ocupados,Puertos Vacios,Ocupacion %\n';
    (g_vilasecaAnalysisData.top_occupied || []).forEach(d => {
        csv += `${d.id},"${d.device_name}","${d.device_type}","${d.location}","${d.rack}",${d.total_ports},${d.occupied_ports},${d.vacant_ports},${d.occupancy_pct}%\n`;
    });
    csv += '\n';

    csv += '--- INVENTARIO CONSOLIDADO COMPLETO ---\n';
    csv += 'ID,Equipo,Tipo,Localidad,Area,Rack,UR,IP,Total Puertos,Ocupados,Vacios,Ocupacion %\n';
    devs.forEach(d => {
        csv += `${d.id},"${d.device_name}","${d.device_type}","${d.location}","${d.area}","${d.rack}","${d.ur_rack}","${d.ip_address}",${d.total_ports},${d.occupied_ports},${d.vacant_ports},${d.occupancy_pct}%\n`;
    });

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `Analisis_Infraestructura_VILASECA_${new Date().toISOString().substring(0, 10)}.csv`;
    link.click();
    if (typeof toastr !== 'undefined') toastr.success('Informe analítico CSV exportado correctamente.');
}
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>

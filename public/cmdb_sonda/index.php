<?php
/**
 * Módulo Autónomo CMDB_SONDA - Gestión Relacional de Infraestructura y Servicios de IT (ITIL)
 * Ubicación: /var/www/html/PROYECTOSONDA/PREPODUCCION/CMDBPRnew/public/cmdb_sonda/index.php
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/permissions_helper.php';
require_once __DIR__ . '/../../src/helpers.php';

require_login();
$user = current_user();
if (!has_role('SUPER_ADMIN') && !has_module_access('cmdb_sonda')) {
    header("Location: ../dashboard.php");
    exit();
}

$page_title = 'CMDB SONDA - Gestión de CIs y Servicios de Negocio';
$page_icon = 'fas fa-cubes text-info';
$hide_content_header = true;

require_once __DIR__ . '/../partials/header.php';
?>

<!-- Estilos Específicos para CMDB_SONDA (Vis.js Local) -->
<link rel="stylesheet" href="vendor/vis/vis-network.min.css" />
<style>
    :root {
        --sonda-primary: #002b49;
        --sonda-accent: #0052cc;
        --sonda-cyan: #00b4d8;
        --sonda-success: #28a745;
        --sonda-warning: #ffc107;
        --sonda-danger: #dc3545;
        --sonda-card-bg: #ffffff;
        --sonda-border: #e2e8f0;
    }

    body.dark-mode {
        --sonda-card-bg: #2d3748;
        --sonda-border: #4a5568;
    }

    .cmdb-header-banner {
        background: linear-gradient(135deg, #002b49 0%, #0052cc 60%, #00b4d8 100%);
        color: #ffffff;
        border-radius: 14px;
        padding: 24px 28px;
        margin-bottom: 24px;
        box-shadow: 0 8px 24px rgba(0, 43, 73, 0.22);
        position: relative;
        overflow: hidden;
    }

    .cmdb-header-banner::after {
        content: '\f1b3';
        font-family: 'Font Awesome 5 Free';
        font-weight: 900;
        position: absolute;
        right: 25px;
        bottom: -20px;
        font-size: 8rem;
        opacity: 0.08;
        pointer-events: none;
    }

    .kpi-card-sonda {
        background: var(--sonda-card-bg);
        border: 1px solid var(--sonda-border);
        border-radius: 12px;
        padding: 18px 20px;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        position: relative;
        overflow: hidden;
    }

    .kpi-card-sonda:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 18px rgba(0,0,0,0.08);
    }

    .kpi-card-sonda .kpi-icon {
        width: 46px;
        height: 46px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
    }

    .nav-pills-sonda .nav-link {
        font-weight: 600;
        font-size: 0.9rem;
        color: #4a5568;
        border-radius: 10px;
        padding: 10px 18px;
        margin-right: 8px;
        background-color: #e2e8f0;
        transition: all 0.2s ease;
        border: none;
    }

    body.dark-mode .nav-pills-sonda .nav-link {
        color: #e2e8f0;
        background-color: #4a5568;
    }

    .nav-pills-sonda .nav-link.active {
        background: linear-gradient(135deg, #002b49 0%, #0052cc 100%) !important;
        color: #ffffff !important;
        box-shadow: 0 4px 14px rgba(0, 82, 204, 0.35);
    }

    .badge-fase1 {
        background-color: #0052cc;
        color: #ffffff;
        font-size: 0.72rem;
        font-weight: 700;
        padding: 3px 7px;
        border-radius: 6px;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }

    .badge-fase2 {
        background-color: #64748b;
        color: #ffffff;
        font-size: 0.72rem;
        font-weight: 600;
        padding: 3px 7px;
        border-radius: 6px;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }

    .badge-criticidad-critica { background-color: #dc3545; color: #fff; }
    .badge-criticidad-alta { background-color: #fd7e14; color: #fff; }
    .badge-criticidad-media { background-color: #ffc107; color: #212529; }
    .badge-criticidad-baja { background-color: #28a745; color: #fff; }

    /* ============================================================== */
    /* ALARMAS EN ROJO Y TITILANDO (BLINK / PULSE ANIMATIONS)         */
    /* ============================================================== */
    @keyframes alarm-blink-red {
        0%, 100% {
            background-color: #dc3545 !important;
            color: #ffffff !important;
            box-shadow: 0 0 6px 1px rgba(220, 53, 69, 0.7);
            opacity: 1;
        }
        50% {
            background-color: #ff001e !important;
            color: #ffffff !important;
            box-shadow: 0 0 18px 6px rgba(255, 0, 30, 0.95);
            opacity: 0.65;
        }
    }

    @keyframes alarm-text-blink {
        0%, 100% {
            color: #dc3545 !important;
            text-shadow: 0 0 4px rgba(220, 53, 69, 0.5);
            opacity: 1;
        }
        50% {
            color: #ff001e !important;
            text-shadow: 0 0 16px rgba(255, 0, 30, 1);
            opacity: 0.35;
        }
    }

    @keyframes alarm-beacon-pulse {
        0% {
            transform: scale(0.85);
            box-shadow: 0 0 0 0 rgba(255, 0, 30, 0.9);
        }
        70% {
            transform: scale(1.25);
            box-shadow: 0 0 0 8px rgba(255, 0, 30, 0);
        }
        100% {
            transform: scale(0.85);
            box-shadow: 0 0 0 0 rgba(255, 0, 30, 0);
        }
    }

    @keyframes alarm-card-pulse {
        0%, 100% {
            border-color: #dc3545 !important;
            box-shadow: 0 0 6px 1px rgba(220, 53, 69, 0.35) !important;
        }
        50% {
            border-color: #ff001e !important;
            box-shadow: 0 0 20px 6px rgba(255, 0, 30, 0.65) !important;
        }
    }

    @keyframes alarm-row-pulse {
        0%, 100% {
            background-color: rgba(220, 53, 69, 0.05) !important;
            border-left: 5px solid #dc3545 !important;
        }
        50% {
            background-color: rgba(255, 0, 30, 0.15) !important;
            border-left: 5px solid #ff001e !important;
        }
    }

    /* Clase para badge de alarma titilando en rojo */
    .badge-alarm-titilando {
        background-color: #dc3545 !important;
        color: #ffffff !important;
        font-weight: 800 !important;
        animation: alarm-blink-red 0.9s infinite ease-in-out !important;
        border: 1px solid rgba(255, 255, 255, 0.6) !important;
        padding: 4px 9px !important;
        border-radius: 6px !important;
        letter-spacing: 0.3px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        vertical-align: middle;
        box-shadow: 0 2px 6px rgba(220, 53, 69, 0.4);
    }

    /* Punto baliza luminoso titilando */
    .beacon-alarm-titilando {
        display: inline-block;
        width: 8px;
        height: 8px;
        background-color: #ffffff;
        border-radius: 50%;
        animation: alarm-beacon-pulse 0.9s infinite ease-in-out;
        box-shadow: 0 0 6px #fff;
    }

    /* Texto titilando en rojo */
    .text-alarm-titilando {
        color: #dc3545 !important;
        font-weight: 900 !important;
        animation: alarm-text-blink 0.9s infinite ease-in-out !important;
    }

    /* Icono titilando */
    .icon-alarm-titilando {
        animation: alarm-blink-red 0.9s infinite ease-in-out !important;
        border-radius: 50%;
    }

    /* Tarjeta KPI con alarma activa */
    .kpi-card-alarm-active {
        border: 2px solid #dc3545 !important;
        animation: alarm-card-pulse 1.2s infinite ease-in-out !important;
        background: rgba(220, 53, 69, 0.06) !important;
    }

    /* Fila de tabla con alarma */
    .row-alarm-titilando {
        animation: alarm-row-pulse 1.4s infinite ease-in-out !important;
    }

    .table-cmdb {
        font-size: 0.88rem;
    }
    
    .table-cmdb thead th {
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.76rem;
        letter-spacing: 0.5px;
        border-top: none;
        background-color: rgba(0, 43, 73, 0.04);
    }

    body.dark-mode .table-cmdb thead th {
        background-color: rgba(255, 255, 255, 0.05);
    }

    #network-graph-container {
        height: 720px;
        width: 100%;
        border: 1px solid var(--sonda-border);
        border-radius: 12px;
        background-color: #f8fafc;
        background-image: radial-gradient(#cbd5e1 1.5px, transparent 1.5px);
        background-size: 28px 28px;
        position: relative;
    }

    body.dark-mode #network-graph-container {
        background-color: #0f172a;
        background-image: radial-gradient(#334155 1.5px, transparent 1.5px);
        background-size: 28px 28px;
    }

    .code-console {
        background: #1e1e1e;
        color: #d4d4d4;
        border-radius: 10px;
        padding: 16px;
        font-family: 'Consolas', 'Courier New', monospace;
        font-size: 0.85rem;
        overflow-x: auto;
        position: relative;
    }

    .btn-copy-code {
        position: absolute;
        top: 10px;
        right: 10px;
        font-size: 0.75rem;
    }

    /* Estilos estrictos para Dashboard Analítico BI (evitan crecimiento desmedido de gráficos) */
    .bi-chart-container-wrapper {
        position: relative !important;
        width: 100% !important;
        height: 250px !important;
        max-height: 250px !important;
        overflow: hidden !important;
    }

    .bi-chart-container-wrapper-sm {
        position: relative !important;
        width: 100% !important;
        height: 220px !important;
        max-height: 220px !important;
        overflow: hidden !important;
    }

    .bi-chart-container-wrapper canvas,
    .bi-chart-container-wrapper-sm canvas {
        max-height: 100% !important;
        max-width: 100% !important;
        height: 100% !important;
        width: 100% !important;
        display: block !important;
    }

    .bi-chart-card {
        background-color: var(--sonda-card-bg);
        border: 1px solid var(--sonda-border);
        border-radius: 12px;
        padding: 16px 18px;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.04);
        margin-bottom: 20px;
        display: flex;
        flex-direction: column;
        height: 100%;
    }

        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        padding-bottom: 8px;
        border-bottom: 1px solid var(--sonda-border);
    }

    /* Estilos para Bitácora de Historial y Diff de Auditoría */
    .badge-audit-create { background-color: #198754; color: #ffffff; font-weight: 600; padding: 4px 8px; border-radius: 6px; }
    .badge-audit-update { background-color: #0d6efd; color: #ffffff; font-weight: 600; padding: 4px 8px; border-radius: 6px; }
    .badge-audit-delete { background-color: #dc3545; color: #ffffff; font-weight: 600; padding: 4px 8px; border-radius: 6px; }
    .badge-audit-relations { background-color: #6f42c1; color: #ffffff; font-weight: 600; padding: 4px 8px; border-radius: 6px; }
    .badge-audit-images { background-color: #0dcaf0; color: #002b49; font-weight: 600; padding: 4px 8px; border-radius: 6px; }
    .badge-audit-services { background-color: #20c997; color: #ffffff; font-weight: 600; padding: 4px 8px; border-radius: 6px; }
    .badge-audit-system { background-color: #495057; color: #ffffff; font-weight: 600; padding: 4px 8px; border-radius: 6px; }

    .diff-table {
        border-collapse: separate;
        border-spacing: 0;
        width: 100%;
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid #dee2e6;
    }
    .diff-table th {
        background: #f8fafc;
        color: #475569;
        font-weight: 700;
        font-size: 0.82rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 10px 14px;
        border-bottom: 2px solid #e2e8f0;
    }
    .diff-table td {
        padding: 10px 14px;
        vertical-align: middle;
        font-size: 0.88rem;
        border-bottom: 1px solid #f1f5f9;
    }
    .diff-val-old {
        background-color: #fff1f2;
        color: #be123c;
        padding: 4px 8px;
        border-radius: 6px;
        border: 1px solid #fecdd3;
        display: inline-block;
        font-family: monospace;
        word-break: break-all;
    }
    .diff-val-new {
        background-color: #f0fdf4;
        color: #15803d;
        padding: 4px 8px;
        border-radius: 6px;
        border: 1px solid #bbf7d0;
        display: inline-block;
        font-family: monospace;
        font-weight: 600;
        word-break: break-all;
    }
    .audit-json-box {
        background: #0f172a;
        color: #38bdf8;
        font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 0.78rem;
        padding: 14px 16px;
        border-radius: 8px;
        max-height: 280px;
        overflow-y: auto;
        white-space: pre-wrap;
        word-break: break-all;
    }
</style>

<div class="container-fluid pt-3 pb-5 px-3 px-md-4">

    <!-- Header Banner SONDA CMDB -->
    <div class="cmdb-header-banner d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <div class="d-flex align-items-center mb-1">
                <span class="badge badge-light text-primary font-weight-bold mr-2 px-2 py-1" style="font-size: 0.8rem;">
                    <i class="fas fa-cubes text-primary mr-1"></i> ITIL CMDB 2.0
                </span>
                <span class="badge badge-warning text-dark font-weight-bold px-2 py-1" style="font-size: 0.8rem;">
                    Módulo Autónomo SONDA
                </span>
            </div>
            <h2 class="font-weight-bold mb-1" style="letter-spacing: -0.5px;">CMDB_SONDA</h2>
            <p class="mb-0 text-white-50" style="max-width: 750px; font-size: 0.95rem;">
                Gestión unificada de Elementos de Configuración (CIs) clasificados en 5 categorías funcionales, modelado de dependencias entre servicios críticos, análisis de impacto y automatización de soporte.
            </p>
        </div>
        <div class="mt-3 mt-md-0 d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-warning font-weight-bold shadow-sm mr-2" onclick="openNewCiModal()">
                <i class="fas fa-plus-circle mr-1"></i> Nuevo CI
            </button>
            <button type="button" class="btn btn-outline-light font-weight-bold shadow-sm mr-2" onclick="openNewServiceModal()">
                <i class="fas fa-briefcase mr-1"></i> Nuevo Servicio
            </button>
            <button type="button" class="btn btn-light font-weight-bold text-primary shadow-sm" onclick="exportCsv()">
                <i class="fas fa-file-excel mr-1 text-success"></i> Exportar CSV
            </button>
        </div>
    </div>

    <!-- KPIs Row -->
    <div class="row mb-4" id="kpi-cards-row">
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="kpi-card-sonda d-flex align-items-center">
                <div class="kpi-icon bg-primary text-white mr-3">
                    <i class="fas fa-server"></i>
                </div>
                <div>
                    <span class="text-muted small font-weight-bold text-uppercase">Total CIs</span>
                    <h4 class="font-weight-bolder mb-0" id="kpi-total-cis">--</h4>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="kpi-card-sonda d-flex align-items-center">
                <div class="kpi-icon bg-info text-white mr-3">
                    <i class="fas fa-sitemap"></i>
                </div>
                <div>
                    <span class="text-muted small font-weight-bold text-uppercase">Servicios</span>
                    <h4 class="font-weight-bolder mb-0" id="kpi-total-services">--</h4>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="kpi-card-sonda d-flex align-items-center" id="kpi-card-expired" style="cursor: pointer;" onclick="$('#filter-support').val('expired'); loadCIs();" title="Filtrar CIs con soporte vencido">
                <div class="kpi-icon bg-danger text-white mr-3" id="kpi-expired-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div>
                    <span class="text-muted small font-weight-bold text-uppercase">Soporte Vencido</span>
                    <h4 class="font-weight-bolder mb-0 text-danger" id="kpi-expired-support">--</h4>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="kpi-card-sonda d-flex align-items-center">
                <div class="kpi-icon bg-warning text-dark mr-3">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <span class="text-muted small font-weight-bold text-uppercase">Por Vencer ≤30d</span>
                    <h4 class="font-weight-bolder mb-0 text-warning" id="kpi-due-soon">--</h4>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="kpi-card-sonda d-flex align-items-center">
                <div class="kpi-icon bg-success text-white mr-3">
                    <i class="fas fa-heartbeat"></i>
                </div>
                <div>
                    <span class="text-muted small font-weight-bold text-uppercase">Monitoreados</span>
                    <h4 class="font-weight-bolder mb-0 text-success" id="kpi-monitored-pct">--%</h4>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="kpi-card-sonda d-flex align-items-center">
                <div class="kpi-icon bg-secondary text-white mr-3">
                    <i class="fas fa-check-double"></i>
                </div>
                <div>
                    <span class="text-muted small font-weight-bold text-uppercase">Integridad Fase 1</span>
                    <h4 class="font-weight-bolder mb-0 text-primary" id="kpi-fase1-compliance">--%</h4>
                </div>
            </div>
        </div>
    </div>

    <!-- BANNER DE ALARMA CRÍTICA (TITILANDO EN ROJO CUANDO HAY SOPORTES VENCIDOS) -->
    <div id="top-alarm-banner" class="alert shadow-sm mb-3 py-2 px-3 align-items-center justify-content-between" style="display: none; background: rgba(220, 53, 69, 0.1); border: 1.5px solid #dc3545; border-left: 6px solid #dc3545; border-radius: 10px;">
        <div class="d-flex align-items-center">
            <span class="beacon-alarm-titilando mr-2" style="width: 11px; height: 11px; background-color: #ff001e;"></span>
            <span class="text-alarm-titilando mr-2 text-uppercase font-weight-bold" style="letter-spacing: 0.5px;">
                <i class="fas fa-bell mr-1"></i> Alarma de Soporte:
            </span>
            <span class="font-weight-bold text-dark" id="top-alarm-text">--</span>
        </div>
        <div>
            <button class="btn btn-xs btn-danger font-weight-bold shadow-sm" onclick="$('#filter-support').val('expired'); loadCIs(); $('#tab-inventario-link').tab('show');">
                <i class="fas fa-filter mr-1"></i> Filtrar CIs en Alarma
            </button>
        </div>
    </div>

    <!-- Navegación Principal por Pestañas -->
    <ul class="nav nav-pills nav-pills-sonda mb-4" id="cmdbTab" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="tab-inventario-link" data-toggle="pill" href="#tab-inventario" role="tab">
                <i class="fas fa-list-ul mr-1"></i> Inventario de CIs
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="tab-servicios-link" data-toggle="pill" href="#tab-servicios" role="tab">
                <i class="fas fa-briefcase mr-1"></i> Servicios Críticos & Apps
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="tab-mapa-link" data-toggle="pill" href="#tab-mapa" role="tab" onclick="initTopologyGraph()">
                <i class="fas fa-project-diagram mr-1"></i> Mapa de Dependencias & Impacto
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="tab-ciclo-link" data-toggle="pill" href="#tab-ciclo" role="tab">
                <i class="fas fa-hourglass-half mr-1"></i> Ciclo de Vida & Soporte
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="tab-auditoria-link" data-toggle="pill" href="#tab-auditoria" role="tab" onclick="loadAuditData()">
                <i class="fas fa-shield-alt mr-1"></i> Auditoría de Integridad
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-warning font-weight-bold" id="tab-historial-link" data-toggle="pill" href="#tab-historial" role="tab" onclick="loadAuditLogs()">
                <i class="fas fa-history mr-1 text-warning"></i> Historial & Bitácora de Cambios
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="tab-imagenes-link" data-toggle="pill" href="#tab-imagenes" role="tab" onclick="loadAllImages()">
                <i class="fas fa-camera mr-1"></i> Evidencias & Registro Fotográfico
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="tab-sql-link" data-toggle="pill" href="#tab-sql" role="tab">
                <i class="fas fa-code mr-1"></i> Scripts SQL & Automatización
            </a>
        </li>
    </ul>

    <!-- Contenido de las Pestañas -->
    <div class="tab-content" id="cmdbTabContent">

        <!-- ============================================================== -->
        <!-- PESTAÑA 1: INVENTARIO DE CIs (5 CATEGORÍAS) -->
        <!-- ============================================================== -->
        <div class="tab-pane fade show active" id="tab-inventario" role="tabpanel">
            <div class="card card-outline card-primary shadow-sm border-0 mb-4" style="border-radius: 12px;">
                <div class="card-body p-3">
                    
                    <!-- BARRA DE FILTROS PRIMARIOS: CLIENTE Y SERVICIO (JERARQUÍA PRINCIPAL) -->
                    <div class="p-3 mb-3 rounded" style="background: rgba(0, 43, 73, 0.04); border: 1px solid var(--sonda-border);">
                        <div class="row align-items-center">
                            <!-- 1° FILTRO PRINCIPAL: CLIENTE -->
                            <div class="col-lg-4 col-md-6 mb-2 mb-lg-0">
                                <label class="font-weight-bold small text-uppercase text-primary mb-1">
                                    <i class="fas fa-building mr-1"></i> 1. Filtro Principal: Cliente
                                </label>
                                <select id="filter-cliente" class="form-control font-weight-bold shadow-sm" onchange="onClienteFilterChange()">
                                    <option value="">-- Todos los Clientes --</option>
                                </select>
                            </div>

                            <!-- 2° FILTRO PRINCIPAL: SERVICIO (EN CASCADA) -->
                            <div class="col-lg-5 col-md-6 mb-2 mb-lg-0">
                                <label class="font-weight-bold small text-uppercase text-info mb-1">
                                    <i class="fas fa-briefcase mr-1"></i> 2. Filtro Secundario: Servicio de Negocio
                                </label>
                                <select id="filter-servicio" class="form-control font-weight-bold shadow-sm" onchange="onServicioFilterChange()">
                                    <option value="">-- Todos los Servicios --</option>
                                </select>
                            </div>

                            <!-- BÚSQUEDA RÁPIDA -->
                            <div class="col-lg-3 col-md-12">
                                <label class="font-weight-bold small text-uppercase text-secondary mb-1">
                                    <i class="fas fa-search mr-1"></i> Búsqueda por CI / IP / Serie
                                </label>
                                <div class="input-group shadow-sm">
                                    <input type="text" id="filter-search" class="form-control" placeholder="Buscar..." onkeyup="debounceFilter()">
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary" type="button" onclick="loadCIs()"><i class="fas fa-search"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- BARRA DE FILTROS TÉCNICOS Y VISTAS -->
                    <div class="row align-items-center mb-3">
                        <div class="col-lg-3 col-md-3 mb-2 mb-lg-0">
                            <select id="filter-view-mode" class="form-control form-control-sm" onchange="changeViewMode()">
                                <option value="all">Modo Vista: Todos los Campos (5 Cat.)</option>
                                <option value="fase1">Modo Vista: Fase 1 (Core Obligatorios)</option>
                                <option value="fase2">Modo Vista: Fase 2 (Enriquecimiento)</option>
                            </select>
                        </div>

                        <div class="col-lg-3 col-md-3 mb-2 mb-lg-0">
                            <select id="filter-support" class="form-control form-control-sm" onchange="loadCIs()">
                                <option value="all">Soporte Contractual: Todos</option>
                                <option value="expired">🔴 Vencidos (&lt; 0 d)</option>
                                <option value="due_30">🟡 Por Vencer (≤ 30 d)</option>
                                <option value="due_60">🟠 Próximos (31-60 d)</option>
                                <option value="active">🟢 Vigentes (&gt; 60 d)</option>
                            </select>
                        </div>

                        <div class="col-lg-2 col-md-3 mb-2 mb-lg-0">
                            <select id="filter-tipo" class="form-control form-control-sm" onchange="loadCIs()">
                                <option value="">Tipo CI: Todos</option>
                                <option value="Servidor Físico">Servidor Físico</option>
                                <option value="Servidor Virtual">Servidor Virtual</option>
                                <option value="Switch Core">Switch Core</option>
                                <option value="Switch Borde">Switch Borde</option>
                                <option value="Router">Router</option>
                                <option value="Firewall">Firewall</option>
                                <option value="Storage / Datastore">Storage / Datastore</option>
                                <option value="Aplicación / Middleware">Aplicación / Middleware</option>
                            </select>
                        </div>

                        <div class="col-lg-2 col-md-3 mb-2 mb-lg-0">
                            <select id="filter-ambiente" class="form-control form-control-sm" onchange="loadCIs()">
                                <option value="">Ambiente: Todos</option>
                                <option value="Producción">Producción</option>
                                <option value="Contingencia / DR">Contingencia / DR</option>
                                <option value="Preproducción">Preproducción</option>
                                <option value="Desarrollo">Desarrollo</option>
                                <option value="QA">QA</option>
                            </select>
                        </div>

                        <div class="col-lg-2 col-md-12 text-right">
                            <button type="button" class="btn btn-outline-secondary btn-sm btn-block font-weight-bold" title="Reiniciar Filtros" onclick="resetFilters()">
                                <i class="fas fa-undo mr-1"></i> Limpiar Filtros
                            </button>
                        </div>
                    </div>

                    <!-- Tabla de CIs Reactiva -->
                    <div class="table-responsive">
                        <table class="table table-hover table-cmdb align-middle mb-0" id="table-cis">
                            <thead>
                                <tr id="table-cis-header">
                                    <!-- Inyectado dinámicamente según modo de vista -->
                                </tr>
                            </thead>
                            <tbody id="table-cis-body">
                                <tr>
                                    <td colspan="10" class="text-center py-4 text-muted">
                                        <i class="fas fa-spinner fa-spin mr-2"></i> Cargando Elementos de Configuración...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                        <span class="text-muted small" id="cis-table-count">Mostrando 0 registros</span>
                        <div class="d-flex align-items-center">
                            <span class="badge badge-fase1 mr-2"><i class="fas fa-shield-alt mr-1"></i> Fase 1 = Core Obligatorio</span>
                            <span class="badge badge-fase2"><i class="fas fa-layer-group mr-1"></i> Fase 2 = Enriquecimiento</span>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- PESTAÑA 2: SERVICIOS CRÍTICOS & APLICACIONES EMPRESARIALES -->
        <!-- ============================================================== -->
        <div class="tab-pane fade" id="tab-servicios" role="tabpanel">
            <div class="row mb-3 align-items-center">
                <div class="col-md-8">
                    <h5 class="font-weight-bold mb-1 text-primary"><i class="fas fa-briefcase mr-2"></i> Servicios Críticos y Aplicaciones Empresariales</h5>
                    <p class="text-muted small mb-0">
                        Capa de abstracción ITIL: Los servicios alinean la tecnología con el negocio, agrupando los componentes de infraestructura que sostienen las operaciones críticas.
                    </p>
                </div>
                <div class="col-md-4 text-md-right mt-2 mt-md-0">
                    <button type="button" class="btn btn-primary font-weight-bold" onclick="openNewServiceModal()">
                        <i class="fas fa-plus mr-1"></i> Registrar Nuevo Servicio
                    </button>
                </div>
            </div>

            <div class="row" id="services-cards-container">
                <!-- Se cargan dinámicamente los servicios -->
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- PESTAÑA 3: MAPA DE DEPENDENCIAS & ANÁLISIS DE IMPACTO -->
        <!-- ============================================================== -->
        <div class="tab-pane fade" id="tab-mapa" role="tabpanel">
            <div class="card card-outline card-info shadow-sm border-0 mb-4" style="border-radius: 12px;">
                <div class="card-body p-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-2 border-bottom">
                        <div>
                            <h5 class="font-weight-bold text-dark mb-0">
                                <i class="fas fa-project-diagram text-info mr-2"></i> Topología de Relaciones y Dependencias (CMDB Graph)
                            </h5>
                            <span class="text-muted small">Mapeo de punta a punta: Servicio &rarr; Servidores &rarr; Red &rarr; Storage</span>
                        </div>
                        <div class="d-flex align-items-center flex-wrap gap-2 mt-2 mt-md-0">
                            <div class="btn-group btn-group-sm mr-2" role="group">
                                <button type="button" class="btn btn-primary font-weight-bold" id="btn-layout-hierarchical" onclick="toggleMapLayout('hierarchical')" title="Vista Jerárquica Vertical (Arriba-Abajo)">
                                    <i class="fas fa-sitemap mr-1"></i> Vertical
                                </button>
                                <button type="button" class="btn btn-outline-primary font-weight-bold" id="btn-layout-horizontal" onclick="toggleMapLayout('horizontal')" title="Vista Jerárquica Horizontal (Izquierda-Derecha)">
                                    <i class="fas fa-stream mr-1"></i> Horizontal
                                </button>
                                <button type="button" class="btn btn-outline-primary font-weight-bold" id="btn-layout-organic" onclick="toggleMapLayout('organic')" title="Vista Orgánica (Red Libre Dinámica)">
                                    <i class="fas fa-project-diagram mr-1"></i> Orgánico
                                </button>
                            </div>
                            <select id="select-map-service" class="form-control form-control-sm mr-2 font-weight-bold text-dark border-primary" style="width: 240px;" onchange="initTopologyGraph()">
                                <option value="">-- Todos los Servicios --</option>
                            </select>
                            <div class="btn-group btn-group-sm mr-2" role="group">
                                <button type="button" class="btn btn-outline-secondary" onclick="zoomInNetworkGraph()" title="Acercar (Zoom In)">
                                    <i class="fas fa-search-plus"></i>
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="zoomOutNetworkGraph()" title="Alejar (Zoom Out)">
                                    <i class="fas fa-search-minus"></i>
                                </button>
                                <button type="button" class="btn btn-secondary font-weight-bold" onclick="fitNetworkGraph()" title="Centrar y Ajustar">
                                    <i class="fas fa-expand-arrows-alt mr-1"></i> Centrar
                                </button>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary mr-1" onclick="openNewRelationshipModal()">
                                <i class="fas fa-link mr-1"></i> Conectar CIs
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger font-weight-bold mr-1" onclick="openImpactSimulatorModal()">
                                <i class="fas fa-bolt mr-1"></i> Simular Falla
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="initTopologyGraph()" title="Recargar Grafo">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Barra Ejecutiva de Capas Arquitectónicas ITIL -->
                    <div class="card bg-light border p-2 mb-3 shadow-none" style="border-radius: 10px;">
                        <div class="d-flex flex-wrap align-items-center justify-content-between">
                            <div class="d-flex align-items-center mb-1 mb-lg-0">
                                <span class="badge badge-dark px-2 py-1 mr-2"><i class="fas fa-layer-group mr-1"></i> ARQUITECTURA ITIL EN CAPAS</span>
                                <span class="small text-muted font-weight-bold">Flujo de Impacto Jerárquico de Negocio a Infraestructura</span>
                            </div>
                            <div class="d-flex flex-wrap align-items-center small">
                                <div class="d-flex align-items-center mr-3 my-1">
                                    <span class="badge mr-1" style="background:#0f172a; color:#fbbf24; border: 1px solid #f59e0b;">Capa 0</span>
                                    <span class="font-weight-bold text-dark">Servicio Negocio</span>
                                </div>
                                <span class="text-muted mr-3 d-none d-md-inline">&rarr;</span>
                                <div class="d-flex align-items-center mr-3 my-1">
                                    <span class="badge mr-1" style="background:#78350f; color:#fde68a; border: 1px solid #f59e0b;">Capa 1</span>
                                    <span class="font-weight-bold text-dark">Apps & Portales</span>
                                </div>
                                <span class="text-muted mr-3 d-none d-md-inline">&rarr;</span>
                                <div class="d-flex align-items-center mr-3 my-1">
                                    <span class="badge mr-1" style="background:#1e3a8a; color:#bfdbfe; border: 1px solid #60a5fa;">Capa 2</span>
                                    <span class="font-weight-bold text-dark">Cómputo & Servidores</span>
                                </div>
                                <span class="text-muted mr-3 d-none d-md-inline">&rarr;</span>
                                <div class="d-flex align-items-center mr-3 my-1">
                                    <span class="badge mr-1" style="background:#4c1d95; color:#e9d5ff; border: 1px solid #a78bfa;">Capa 3</span>
                                    <span class="font-weight-bold text-dark">Datos & Storage</span>
                                </div>
                                <span class="text-muted mr-3 d-none d-md-inline">&rarr;</span>
                                <div class="d-flex align-items-center my-1">
                                    <span class="badge mr-1" style="background:#064e3b; color:#a7f3d0; border: 1px solid #34d399;">Capa 4</span>
                                    <span class="font-weight-bold text-dark">Red & Seguridad</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contenedor del grafo interactivo -->
                    <div id="network-graph-container"></div>
                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- PESTAÑA 4: CICLO DE VIDA, SOPORTE Y LICENCIAMIENTO -->
        <!-- ============================================================== -->
        <div class="tab-pane fade" id="tab-ciclo" role="tabpanel">
            <div class="card card-outline card-warning shadow-sm border-0 mb-4" style="border-radius: 12px;">
                <div class="card-body p-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-2 border-bottom">
                        <div>
                            <h5 class="font-weight-bold text-dark mb-0">
                                <i class="fas fa-hourglass-half text-warning mr-2"></i> Gestión de Ciclo de Vida Contractual & Licencias
                            </h5>
                            <span class="text-muted small">Control automatizado de vigencia, prevención de obsolescencia (EOL/EOS) y cálculo automático de días</span>
                        </div>
                        <div>
                            <button type="button" class="btn btn-warning font-weight-bold" onclick="recalculateSupportNow()">
                                <i class="fas fa-sync-alt mr-1"></i> Recalcular Días de Soporte Ahora (SQL)
                            </button>
                        </div>
                    </div>

                    <!-- 4 Columnas de Semáforos -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="p-3 bg-light border border-danger rounded text-center" id="cycle-card-expired">
                                <span class="badge badge-alarm-titilando font-weight-bold mb-2">
                                    <span class="beacon-alarm-titilando"></span><i class="fas fa-bell mr-1"></i> VENCIDOS (&lt; 0 días)
                                </span>
                                <h3 class="font-weight-bold text-danger mb-1" id="cycle-expired-count">--</h3>
                                <p class="small text-muted mb-0">Riesgo crítico contractual. Requiere renovación inmediata.</p>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="p-3 bg-light border border-warning rounded text-center">
                                <span class="badge badge-warning font-weight-bold mb-2">POR VENCER (0 - 30 días)</span>
                                <h3 class="font-weight-bold text-warning mb-1" id="cycle-due-soon-count">--</h3>
                                <p class="small text-muted mb-0">Alerta temprana. Gestión de orden de compra o renovación.</p>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="p-3 bg-light border border-info rounded text-center">
                                <span class="badge badge-info font-weight-bold mb-2">PRÓXIMO (31 - 60 días)</span>
                                <h3 class="font-weight-bold text-info mb-1" id="cycle-due-60-count">--</h3>
                                <p class="small text-muted mb-0">Planificación de presupuesto y validación con el cliente.</p>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="p-3 bg-light border border-success rounded text-center">
                                <span class="badge badge-success font-weight-bold mb-2">VIGENTES (&gt; 60 días)</span>
                                <h3 class="font-weight-bold text-success mb-1" id="cycle-active-count">--</h3>
                                <p class="small text-muted mb-0">Cobertura óptima y soporte técnico vigente.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Tabla de Ciclo de Vida -->
                    <h6 class="font-weight-bold text-primary mb-2"><i class="fas fa-table mr-1"></i> Monitoreo de Fechas Contractuales y Obsolescencia</h6>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm table-bordered text-center align-middle" id="table-cycle">
                            <thead class="bg-light">
                                <tr>
                                    <th>ID CI</th>
                                    <th>Hostname</th>
                                    <th>Tipo CI</th>
                                    <th>Inicio Soporte</th>
                                    <th>Fin Soporte</th>
                                    <th>Días Restantes</th>
                                    <th>Semáforo</th>
                                    <th>Garantía Hasta</th>
                                    <th>Fin Licencia</th>
                                    <th>Fecha EOL</th>
                                    <th>Fecha EOS</th>
                                </tr>
                            </thead>
                            <tbody id="table-cycle-body">
                                <!-- Datos inyectados -->
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- PESTAÑA 5: AUDITORÍA DE INTEGRIDAD (FASE 1 VS FASE 2) -->
        <!-- ============================================================== -->
        <div class="tab-pane fade" id="tab-auditoria" role="tabpanel">
            <div class="card card-outline card-success shadow-sm border-0 mb-4" style="border-radius: 12px;">
                <div class="card-body p-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-2 border-bottom">
                        <div>
                            <h5 class="font-weight-bold text-dark mb-0">
                                <i class="fas fa-shield-alt text-success mr-2"></i> Auditoría de Integridad y Calidad de Datos CMDB
                            </h5>
                            <span class="text-muted small">Evita el error #1 y #4 de la CMDB: garantiza que ningún CI quede sin campos obligatorios o sin dueño asignado</span>
                        </div>
                        <div>
                            <button type="button" class="btn btn-outline-success font-weight-bold" onclick="loadAuditData()">
                                <i class="fas fa-sync mr-1"></i> Re-auditar Ahora
                            </button>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-lg-4 mb-3">
                            <div class="card bg-light border p-3 text-center h-100">
                                <h6 class="font-weight-bold text-muted text-uppercase mb-2">Índice Global de Cumplimiento Fase 1</h6>
                                <div class="my-2">
                                    <h1 class="font-weight-bolder text-primary display-4 mb-0" id="audit-compliance-pct">--%</h1>
                                </div>
                                <div class="progress progress-sm mb-2">
                                    <div class="progress-bar bg-success" id="audit-progress-bar" style="width: 0%"></div>
                                </div>
                                <span class="small text-muted" id="audit-compliance-summary">Evaluando calidad de datos...</span>
                            </div>
                        </div>

                        <div class="col-lg-8 mb-3">
                            <div class="card bg-light border p-3 h-100">
                                <h6 class="font-weight-bold text-dark mb-2"><i class="fas fa-clipboard-check mr-1 text-info"></i> Campos Obligatorios de Fase 1 (Core) Faltantes en la Base</h6>
                                <p class="small text-muted mb-3">Los campos de Fase 1 son indispensables para la trazabilidad operativa desde el primer día:</p>
                                <div class="row" id="audit-missing-fields-badges">
                                    <!-- Badges con conteos de faltantes -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <h6 class="font-weight-bold text-danger mb-2">
                        <i class="fas fa-exclamation-circle mr-1"></i> CIs con Campos Obligatorios Incompletos
                    </h6>
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered table-sm align-middle" id="table-audit-incomplete">
                            <thead class="bg-light">
                                <tr>
                                    <th>ID CI</th>
                                    <th>Hostname</th>
                                    <th>Tipo CI</th>
                                    <th>Cliente</th>
                                    <th>Campos Faltantes de Fase 1</th>
                                    <th class="text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="table-audit-incomplete-body">
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-3">Todos los CIs cumplen con el 100% de los campos obligatorios de Fase 1.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- PESTAÑA 6: SCRIPTS SQL & AUTOMATIZACIÓN -->
        <!-- ============================================================== -->
        <div class="tab-pane fade" id="tab-sql" role="tabpanel">
            <div class="card card-outline card-secondary shadow-sm border-0 mb-4" style="border-radius: 12px;">
                <div class="card-body p-3">
                    <h5 class="font-weight-bold text-dark mb-1">
                        <i class="fas fa-code text-secondary mr-2"></i> Scripts SQL de Automatización y Validación de Integridad
                    </h5>
                    <p class="text-muted small mb-4">
                        Consultas y rutinas listas para programar en tareas automáticas (Cron Jobs, MariaDB Events o triggers del sistema) para mantener la CMDB sincronizada y validada.
                    </p>

                    <!-- Script 1: Cálculo de Días de Soporte -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <h6 class="font-weight-bold text-primary mb-0">
                                1. Consulta para Automatizar el Cálculo de Días Fin de Soporte
                            </h6>
                            <button type="button" class="btn btn-xs btn-primary font-weight-bold" onclick="recalculateSupportNow()">
                                <i class="fas fa-play mr-1"></i> Probar Ejecución Ahora
                            </button>
                        </div>
                        <p class="text-muted small mb-2">
                            Actualiza el campo calculado <code>dias_fin_soporte</code> de forma masiva en base a la fecha del sistema <code>CURDATE()</code>.
                        </p>
                        <div class="code-console">
                            <button class="btn btn-sm btn-outline-light btn-copy-code" onclick="copyToClipboard('sql-code-1')">Copiar</button>
                            <pre class="mb-0" id="sql-code-1">-- ===============================================================
-- TAREA PROGRAMADA (CRON DIARIO / EVENTO MARIADB)
-- Cálculo automático de los días restantes de soporte contractual
-- ===============================================================
UPDATE cmdb_sonda_cis 
SET dias_fin_soporte = DATEDIFF(fin_soporte, CURDATE())
WHERE fin_soporte IS NOT NULL;

-- Semáforo de Alertas Contractuales:
-- - Vencido:             dias_fin_soporte &lt; 0
-- - Por Vencer Crítico:  dias_fin_soporte BETWEEN 0 AND 30
-- - Próximo a Renovar:   dias_fin_soporte BETWEEN 31 AND 60
-- - Vigente / Al día:    dias_fin_soporte &gt; 60</pre>
                        </div>
                    </div>

                    <!-- Script 2: Auditoría de Integridad -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <h6 class="font-weight-bold text-primary mb-0">
                                2. Consulta para Validar Integridad de Campos Obligatorios (Fase 1 Core)
                            </h6>
                        </div>
                        <p class="text-muted small mb-2">
                            Evalúa el porcentaje de completitud de los 13 campos mínimos obligatorios del inventario viable para auditorías y cumplimiento.
                        </p>
                        <div class="code-console">
                            <button class="btn btn-sm btn-outline-light btn-copy-code" onclick="copyToClipboard('sql-code-2')">Copiar</button>
                            <pre class="mb-0" id="sql-code-2">-- ===============================================================
-- AUDITORÍA DE INTEGRIDAD FASE 1 (CORE / OBLIGATORIOS)
-- Retorna el porcentaje exacto de cumplimiento por Elemento de Configuración
-- ===============================================================
SELECT 
    id,
    id_ci,
    hostname_nombre,
    tipo_ci,
    cliente,
    servicio,
    ROUND((
      (CASE WHEN id_ci IS NOT NULL AND TRIM(id_ci) != '' THEN 1 ELSE 0 END) +
      (CASE WHEN hostname_nombre IS NOT NULL AND TRIM(hostname_nombre) != '' THEN 1 ELSE 0 END) +
      (CASE WHEN tipo_ci IS NOT NULL AND TRIM(tipo_ci) != '' THEN 1 ELSE 0 END) +
      (CASE WHEN cliente IS NOT NULL AND TRIM(cliente) != '' THEN 1 ELSE 0 END) +
      (CASE WHEN servicio IS NOT NULL AND TRIM(servicio) != '' THEN 1 ELSE 0 END) +
      (CASE WHEN sede_site IS NOT NULL AND TRIM(sede_site) != '' THEN 1 ELSE 0 END) +
      (CASE WHEN ip_administracion IS NOT NULL AND TRIM(ip_administracion) != '' THEN 1 ELSE 0 END) +
      (CASE WHEN ambiente IS NOT NULL AND TRIM(ambiente) != '' THEN 1 ELSE 0 END) +
      (CASE WHEN estado_ci IS NOT NULL AND TRIM(estado_ci) != '' THEN 1 ELSE 0 END) +
      (CASE WHEN criticidad IS NOT NULL AND TRIM(criticidad) != '' THEN 1 ELSE 0 END) +
      (CASE WHEN monitoreado IS NOT NULL AND TRIM(monitoreado) != '' THEN 1 ELSE 0 END) +
      (CASE WHEN inicio_soporte IS NOT NULL THEN 1 ELSE 0 END) +
      (CASE WHEN fin_soporte IS NOT NULL THEN 1 ELSE 0 END)
    ) * 100.0 / 13, 1) AS pct_integridad_fase1,
    CASE 
      WHEN (
        (CASE WHEN id_ci IS NOT NULL AND TRIM(id_ci) != '' THEN 1 ELSE 0 END) +
        (CASE WHEN hostname_nombre IS NOT NULL AND TRIM(hostname_nombre) != '' THEN 1 ELSE 0 END) +
        (CASE WHEN tipo_ci IS NOT NULL AND TRIM(tipo_ci) != '' THEN 1 ELSE 0 END) +
        (CASE WHEN cliente IS NOT NULL AND TRIM(cliente) != '' THEN 1 ELSE 0 END) +
        (CASE WHEN servicio IS NOT NULL AND TRIM(servicio) != '' THEN 1 ELSE 0 END) +
        (CASE WHEN sede_site IS NOT NULL AND TRIM(sede_site) != '' THEN 1 ELSE 0 END) +
        (CASE WHEN ip_administracion IS NOT NULL AND TRIM(ip_administracion) != '' THEN 1 ELSE 0 END) +
        (CASE WHEN ambiente IS NOT NULL AND TRIM(ambiente) != '' THEN 1 ELSE 0 END) +
        (CASE WHEN estado_ci IS NOT NULL AND TRIM(estado_ci) != '' THEN 1 ELSE 0 END) +
        (CASE WHEN criticidad IS NOT NULL AND TRIM(criticidad) != '' THEN 1 ELSE 0 END) +
        (CASE WHEN monitoreado IS NOT NULL AND TRIM(monitoreado) != '' THEN 1 ELSE 0 END) +
        (CASE WHEN inicio_soporte IS NOT NULL THEN 1 ELSE 0 END) +
        (CASE WHEN fin_soporte IS NOT NULL THEN 1 ELSE 0 END)
      ) = 13 THEN 'COMPLETO' ELSE 'INCOMPLETO'
    END AS estado_fase1
FROM cmdb_sonda_cis
ORDER BY pct_integridad_fase1 ASC, hostname_nombre ASC;</pre>
                        </div>
                    </div>

                    <!-- Script 3: DDL y Triggers -->
                    <div>
                        <h6 class="font-weight-bold text-primary mb-1">
                            3. Triggers Automáticos en MariaDB (Inserción y Modificación en Tiempo Real)
                        </h6>
                        <p class="text-muted small mb-2">
                            Garantizan que cualquier registro insertado o modificado calcule automáticamente <code>dias_fin_soporte</code> sin depender de la aplicación web.
                        </p>
                        <div class="code-console">
                            <button class="btn btn-sm btn-outline-light btn-copy-code" onclick="copyToClipboard('sql-code-3')">Copiar</button>
                            <pre class="mb-0" id="sql-code-3">-- TRIGGER BEFORE INSERT
DELIMITER $$
CREATE TRIGGER `trg_cmdb_sonda_before_insert` 
BEFORE INSERT ON `cmdb_sonda_cis`
FOR EACH ROW
BEGIN
  IF NEW.fin_soporte IS NOT NULL THEN
    SET NEW.dias_fin_soporte = DATEDIFF(NEW.fin_soporte, CURDATE());
  ELSE
    SET NEW.dias_fin_soporte = NULL;
  END IF;
END$$
DELIMITER ;

-- TRIGGER BEFORE UPDATE
DELIMITER $$
CREATE TRIGGER `trg_cmdb_sonda_before_update` 
BEFORE UPDATE ON `cmdb_sonda_cis`
FOR EACH ROW
BEGIN
  IF NEW.fin_soporte IS NOT NULL THEN
    SET NEW.dias_fin_soporte = DATEDIFF(NEW.fin_soporte, CURDATE());
  ELSE
    SET NEW.dias_fin_soporte = NULL;
  END IF;
END$$
DELIMITER ;</pre>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- PESTAÑA: EVIDENCIAS & REGISTRO FOTOGRÁFICO DE INFRAESTRUCTURA -->
        <!-- ============================================================== -->
        <div class="tab-pane fade" id="tab-imagenes" role="tabpanel">
            <div class="card card-outline card-info shadow-sm border-0 mb-4" style="border-radius: 12px;">
                <div class="card-body p-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-2 border-bottom">
                        <div>
                            <h5 class="font-weight-bold text-dark mb-0">
                                <i class="fas fa-camera text-info mr-2"></i> Registro Fotográfico, Evidencias & Ubicación Física
                            </h5>
                            <span class="text-muted small">Evidencias visuales de racks, salas, etiquetas de seriales, cableado y componentes con tags de identificación, fecha y observaciones</span>
                        </div>
                        <div>
                            <button type="button" class="btn btn-info font-weight-bold shadow-sm" onclick="openUploadImageModal()">
                                <i class="fas fa-upload mr-1"></i> Subir Nueva Evidencia
                            </button>
                        </div>
                    </div>

                    <!-- Filtros de Galería de Fotos -->
                    <div class="p-3 mb-3 rounded" style="background: rgba(0, 82, 204, 0.04); border: 1px solid var(--sonda-border);">
                        <div class="row align-items-center">
                            <div class="col-md-4 mb-2 mb-md-0">
                                <label class="font-weight-bold small text-muted mb-1"><i class="fas fa-server mr-1"></i> Filtrar por CI:</label>
                                <select id="filter-img-ci" class="form-control form-control-sm font-weight-bold" onchange="loadAllImages()">
                                    <option value="">-- Todos los CIs --</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-2 mb-md-0">
                                <label class="font-weight-bold small text-muted mb-1"><i class="fas fa-map-marker-alt mr-1"></i> Ubicación Física de la Foto:</label>
                                <select id="filter-img-ubicacion" class="form-control form-control-sm font-weight-bold" onchange="loadAllImages()">
                                    <option value="">-- Todas las Ubicaciones --</option>
                                    <option value="Frontal del Rack">Frontal del Rack</option>
                                    <option value="Posterior / Cableado">Posterior / Cableado</option>
                                    <option value="Etiqueta / Serial / Placa">Etiqueta / Serial / Placa</option>
                                    <option value="Sala Datacenter / Site">Sala Datacenter / Site</option>
                                    <option value="PDU / Conexión Eléctrica">PDU / Conexión Eléctrica</option>
                                    <option value="Interior / Componentes">Interior / Componentes</option>
                                    <option value="General">General</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-2 mb-md-0">
                                <label class="font-weight-bold small text-muted mb-1"><i class="fas fa-tag mr-1"></i> Buscar por Tag / Observación:</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" id="filter-img-tag" class="form-control" placeholder="ej: #FRONTAL, #RACK, #SERIAL..." onkeyup="debounceLoadImages()">
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary" type="button" onclick="loadAllImages()"><i class="fas fa-search"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Grid de Imágenes / Galería -->
                    <div class="row" id="images-gallery-container">
                        <div class="col-12 text-center text-muted py-5">
                            <i class="fas fa-images fa-3x mb-2 text-muted"></i>
                            <p>Cargando evidencias fotográficas...</p>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- PESTAÑA: HISTORIAL & BITÁCORA DE CAMBIOS (AUDITORÍA 360) -->
        <!-- ============================================================== -->
        <div class="tab-pane fade" id="tab-historial" role="tabpanel">
            
            <!-- Banner Explicativo de la Bitácora -->
            <div class="card bg-gradient-dark text-white border-0 shadow-sm mb-4" style="border-radius: 12px; background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);">
                <div class="card-body p-4 d-flex flex-wrap justify-content-between align-items-center">
                    <div>
                        <h4 class="font-weight-bold mb-1 text-white">
                            <i class="fas fa-history text-warning mr-2"></i> Bitácora de Auditoría & Trazabilidad de Cambios
                        </h4>
                        <p class="mb-0 text-white-50 small" style="max-width: 850px;">
                            Supervise qué se está haciendo en el módulo en tiempo real: registro completo de creaciones, modificaciones con detalle comparativo (valor anterior vs. nuevo), bajas de inventario, adición/eliminación de dependencias y evidencias fotográficas.
                        </p>
                    </div>
                    <div class="mt-3 mt-md-0 d-flex gap-2">
                        <button type="button" class="btn btn-outline-light btn-sm font-weight-bold mr-2" onclick="loadAuditLogs(1)">
                            <i class="fas fa-sync-alt mr-1"></i> Actualizar
                        </button>
                        <button type="button" class="btn btn-warning btn-sm font-weight-bold text-dark shadow-sm" onclick="exportAuditCsv()">
                            <i class="fas fa-file-excel mr-1 text-dark"></i> Exportar a Excel (CSV)
                        </button>
                    </div>
                </div>
            </div>

            <!-- KPIs de Bitácora -->
            <div class="row mb-4">
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3 mb-xl-0">
                    <div class="kpi-card-sonda d-flex align-items-center">
                        <div class="kpi-icon bg-light text-primary mr-3">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <div>
                            <div class="text-muted small text-uppercase font-weight-bold">Total Eventos</div>
                            <h3 class="font-weight-bold mb-0 text-dark" id="audit-kpi-total">0</h3>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3 mb-xl-0">
                    <div class="kpi-card-sonda d-flex align-items-center" style="border-left: 4px solid #28a745;">
                        <div class="kpi-icon mr-3" style="background: rgba(40, 167, 69, 0.12); color: #28a745;">
                            <i class="fas fa-plus-circle"></i>
                        </div>
                        <div>
                            <div class="text-muted small text-uppercase font-weight-bold">Creaciones</div>
                            <h3 class="font-weight-bold mb-0 text-success" id="audit-kpi-creates">0</h3>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-4 col-sm-6 mb-3 mb-xl-0">
                    <div class="kpi-card-sonda d-flex align-items-center" style="border-left: 4px solid #0052cc;">
                        <div class="kpi-icon mr-3" style="background: rgba(0, 82, 204, 0.12); color: #0052cc;">
                            <i class="fas fa-edit"></i>
                        </div>
                        <div>
                            <div class="text-muted small text-uppercase font-weight-bold">Modificaciones (Diff)</div>
                            <h3 class="font-weight-bold mb-0 text-primary" id="audit-kpi-updates">0</h3>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3 mb-xl-0">
                    <div class="kpi-card-sonda d-flex align-items-center" style="border-left: 4px solid #dc3545;">
                        <div class="kpi-icon mr-3" style="background: rgba(220, 53, 69, 0.12); color: #dc3545;">
                            <i class="fas fa-trash-alt"></i>
                        </div>
                        <div>
                            <div class="text-muted small text-uppercase font-weight-bold">Bajas / Eliminados</div>
                            <h3 class="font-weight-bold mb-0 text-danger" id="audit-kpi-deletes">0</h3>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-8 col-sm-12 mb-3 mb-xl-0">
                    <div class="kpi-card-sonda d-flex align-items-center" style="border-left: 4px solid #17a2b8;">
                        <div class="kpi-icon mr-3" style="background: rgba(23, 162, 184, 0.12); color: #17a2b8;">
                            <i class="fas fa-users-cog"></i>
                        </div>
                        <div>
                            <div class="text-muted small text-uppercase font-weight-bold">Usuarios Responsables</div>
                            <h3 class="font-weight-bold mb-0 text-info" id="audit-kpi-users">0</h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Barra de Filtros de la Bitácora -->
            <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px;">
                <div class="card-body p-3">
                    <div class="row align-items-center">
                        <div class="col-lg-3 col-md-6 mb-2 mb-lg-0">
                            <label class="small text-muted font-weight-bold mb-1"><i class="fas fa-search mr-1"></i> Búsqueda libre:</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-white border-right-0"><i class="fas fa-search text-muted"></i></span>
                                </div>
                                <input type="text" class="form-control border-left-0" id="audit-filter-q" placeholder="CI, hostname, usuario, IP, detalle..." onkeyup="if(event.key==='Enter') loadAuditLogs(1)">
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-3 col-sm-6 mb-2 mb-lg-0">
                            <label class="small text-muted font-weight-bold mb-1"><i class="fas fa-filter mr-1"></i> Acción:</label>
                            <select class="form-control form-control-sm" id="audit-filter-action" onchange="loadAuditLogs(1)">
                                <option value="">Todas las Acciones</option>
                                <option value="CREATE">Solo Creaciones (CREATE)</option>
                                <option value="UPDATE">Solo Modificaciones (UPDATE)</option>
                                <option value="DELETE">Solo Bajas / Eliminaciones (DELETE)</option>
                                <option value="RELATIONS">Relaciones de Dependencia</option>
                                <option value="IMAGES">Evidencias Fotográficas</option>
                                <option value="SERVICES">Servicios de Negocio</option>
                                <option value="RECALCULATE_SUPPORT">Recálculo Contractual</option>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-3 col-sm-6 mb-2 mb-lg-0">
                            <label class="small text-muted font-weight-bold mb-1"><i class="fas fa-user mr-1"></i> Operador:</label>
                            <select class="form-control form-control-sm" id="audit-filter-user" onchange="loadAuditLogs(1)">
                                <option value="">Todos los Usuarios</option>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-4 col-sm-6 mb-2 mb-lg-0">
                            <label class="small text-muted font-weight-bold mb-1"><i class="fas fa-calendar mr-1"></i> Desde:</label>
                            <input type="date" class="form-control form-control-sm" id="audit-filter-from" onchange="loadAuditLogs(1)">
                        </div>
                        <div class="col-lg-2 col-md-4 col-sm-6 mb-2 mb-lg-0">
                            <label class="small text-muted font-weight-bold mb-1"><i class="fas fa-calendar mr-1"></i> Hasta:</label>
                            <input type="date" class="form-control form-control-sm" id="audit-filter-to" onchange="loadAuditLogs(1)">
                        </div>
                        <div class="col-lg-1 col-md-4 col-sm-12 text-right align-self-end">
                            <button type="button" class="btn btn-sm btn-outline-secondary w-100" title="Limpiar todos los filtros" onclick="resetAuditFilters()">
                                <i class="fas fa-eraser mr-1"></i> Limpiar
                            </button>
                        </div>
                    </div>

                    <!-- Badge indicador de filtro de CI específico -->
                    <div id="audit-ci-filter-badge-container" class="mt-2" style="display: none;">
                        <span class="badge badge-warning text-dark px-3 py-2 shadow-sm" style="font-size: 0.85rem;">
                            <i class="fas fa-filter mr-1"></i> Mostrando exclusivamente historial del CI: <b id="audit-ci-filter-name">--</b>
                            <a href="javascript:void(0)" onclick="clearAuditCiFilter()" class="ml-2 text-danger font-weight-bold" style="font-size: 1.1rem; text-decoration: none;" title="Quitar filtro de CI">&times;</a>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Tabla de Registros de Auditoría -->
            <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px; overflow: hidden;">
                <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center">
                    <h6 class="font-weight-bold text-primary mb-0">
                        <i class="fas fa-stream mr-2"></i> Eventos y Modificaciones Registradas
                    </h6>
                    <div class="d-flex align-items-center">
                        <span class="small text-muted mr-3" id="audit-table-count">Cargando bitácora...</span>
                        <select class="form-control form-control-sm" id="audit-page-limit" style="width: 95px;" onchange="loadAuditLogs(1)">
                            <option value="15">15 / pág</option>
                            <option value="25" selected>25 / pág</option>
                            <option value="50">50 / pág</option>
                            <option value="100">100 / pág</option>
                        </select>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0" id="table-audit-logs" style="font-size: 0.88rem;">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width: 60px;" class="text-center"># ID</th>
                                    <th style="width: 155px;">Fecha & Hora</th>
                                    <th style="width: 135px;">Usuario</th>
                                    <th style="width: 145px;">Acción</th>
                                    <th style="width: 210px;">Activo / CI Afectado</th>
                                    <th>Resumen del Cambio / Operación</th>
                                    <th style="width: 125px;" class="text-center">Inspección</th>
                                    <th style="width: 110px;" class="text-muted small">IP Origen</th>
                                </tr>
                            </thead>
                            <tbody id="audit-table-body">
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="fas fa-spinner fa-spin fa-2x mb-2 text-primary"></i>
                                        <div>Cargando historial de auditoría...</div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white border-top py-3 d-flex flex-wrap justify-content-between align-items-center">
                    <div class="small text-muted" id="audit-pagination-info">
                        Página 1 de 1
                    </div>
                    <nav aria-label="Paginación de Auditoría">
                        <ul class="pagination pagination-sm mb-0" id="audit-pagination-ul">
                            <!-- Inyectado dinámicamente -->
                        </ul>
                    </nav>
                </div>
            </div>

        </div>

    </div>

<!-- ============================================================== -->
<!-- MODAL: CREAR / EDITAR CI (6 CATEGORÍAS FUNCIONALES) -->
<!-- ============================================================== -->
<div class="modal fade" id="modal-ci-form" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 14px;">
            <div class="modal-header bg-primary text-white" style="border-top-left-radius: 14px; border-top-right-radius: 14px;">
                <h5 class="modal-title font-weight-bold" id="modal-ci-title">
                    <i class="fas fa-cube mr-2"></i> Registrar Elemento de Configuración (CI)
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="form-ci" onsubmit="saveCi(event)">
                <input type="hidden" id="ci-id" name="id" value="">

                <div class="modal-body p-4">
                    <!-- Navegación por las 6 Categorías Funcionales -->
                    <ul class="nav nav-tabs nav-justified mb-4" id="ciFormTab" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active font-weight-bold" id="ciform-cat1-tab" data-toggle="tab" href="#ciform-cat1" role="tab">
                                <i class="fas fa-fingerprint mr-1"></i> 1. Identificación & Técnicas
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link font-weight-bold" id="ciform-cat2-tab" data-toggle="tab" href="#ciform-cat2" role="tab">
                                <i class="fas fa-building mr-1"></i> 2. Negocio & Gobierno
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link font-weight-bold" id="ciform-cat3-tab" data-toggle="tab" href="#ciform-cat3" role="tab">
                                <i class="fas fa-map-marker-alt mr-1"></i> 3. Ubicación & Topología
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link font-weight-bold" id="ciform-cat4-tab" data-toggle="tab" href="#ciform-cat4" role="tab">
                                <i class="fas fa-tachometer-alt mr-1"></i> 4. Operaciones & Monitoreo
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link font-weight-bold" id="ciform-cat5-tab" data-toggle="tab" href="#ciform-cat5" role="tab">
                                <i class="fas fa-calendar-alt mr-1"></i> 5. Ciclo de Vida & Soporte
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link font-weight-bold text-info" id="ciform-cat6-tab" data-toggle="tab" href="#ciform-cat6" role="tab">
                                <i class="fas fa-camera mr-1"></i> 6. Fotos & Evidencias
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content" id="ciFormTabContent">
                        
                        <!-- CATEGORÍA 1: Identificación y Características técnicas -->
                        <div class="tab-pane fade show active" id="ciform-cat1" role="tabpanel">
                            <div class="alert alert-info py-2 small mb-3">
                                <i class="fas fa-info-circle mr-1"></i> <b>Propósito:</b> Definir de forma inequívoca el activo físico o lógico y sus especificaciones técnicas de base.
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="font-weight-bold small">ID_CI <span class="badge badge-fase1 ml-1">Fase 1</span> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="ci-id_ci" name="id_ci" placeholder="ej: CI-SND-SRV-001" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="font-weight-bold small">Hostname / Nombre <span class="badge badge-fase1 ml-1">Fase 1</span> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="ci-hostname_nombre" name="hostname_nombre" placeholder="ej: SRV-SAP-APP01" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="font-weight-bold small">Tipo de CI <span class="badge badge-fase1 ml-1">Fase 1</span> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="ci-tipo_ci" name="tipo_ci" list="tipos-ci-list" placeholder="Seleccionar o escribir tipo..." required>
                                    <datalist id="tipos-ci-list">
                                        <option value="Servidor Físico">
                                        <option value="Servidor Virtual">
                                        <option value="Switch Core">
                                        <option value="Switch Borde">
                                        <option value="Router">
                                        <option value="Firewall">
                                        <option value="Storage / Datastore">
                                        <option value="Access Point">
                                        <option value="Balanceador de Carga">
                                        <option value="Aplicación / Middleware">
                                        <option value="Base de Datos">
                                    </datalist>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="font-weight-bold small">Fabricante <span class="badge badge-fase2 ml-1">Fase 2</span></label>
                                    <input type="text" class="form-control" id="ci-fabricante" name="fabricante" placeholder="ej: Cisco, Dell, HPE, VMware">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="font-weight-bold small">Modelo <span class="badge badge-fase2 ml-1">Fase 2</span></label>
                                    <input type="text" class="form-control" id="ci-modelo" name="modelo" placeholder="ej: PowerEdge R750">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="font-weight-bold small">Número de Serie <span class="badge badge-fase2 ml-1">Fase 2</span></label>
                                    <input type="text" class="form-control" id="ci-numero_serie" name="numero_serie" placeholder="ej: 7XYZ982-CL">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="font-weight-bold small">Versión Firmware / SO <span class="badge badge-fase2 ml-1">Fase 2</span></label>
                                    <input type="text" class="form-control" id="ci-version_firmware_so" name="version_firmware_so" placeholder="ej: RHEL 9.2 / IOS XE 17.9">
                                </div>
                            </div>
                        </div>

                        <!-- CATEGORÍA 2: Negocio, Organización y Gobierno -->
                        <div class="tab-pane fade" id="ciform-cat2" role="tabpanel">
                            <div class="alert alert-info py-2 small mb-3">
                                <i class="fas fa-info-circle mr-1"></i> <b>Propósito:</b> Alinear la infraestructura tecnológica con los servicios de negocio y los acuerdos contractuales asociados.
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="font-weight-bold small">Cliente <span class="badge badge-fase1 ml-1">Fase 1</span> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="ci-cliente" name="cliente" placeholder="ej: SONDA Corp / Clientes Corporativos" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="font-weight-bold small">Servicio de Negocio <span class="badge badge-fase1 ml-1">Fase 1</span> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="ci-servicio" name="servicio" placeholder="ej: Servicio de Facturación Electrónica SAP" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="font-weight-bold small">Servicio Crítico (Vínculo CMDB) <span class="badge badge-fase2 ml-1">Fase 2</span></label>
                                    <select class="form-control" id="ci-service_id" name="service_id">
                                        <option value="">-- Sin Servicio Asignado --</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="font-weight-bold small">Responsable Cliente (Dueño de CI) <span class="badge badge-fase2 ml-1">Fase 2</span></label>
                                    <input type="text" class="form-control" id="ci-responsable_cliente" name="responsable_cliente" placeholder="ej: Juan Pérez (Jefe Operaciones)">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="font-weight-bold small">Contrato / Proyecto <span class="badge badge-fase2 ml-1">Fase 2</span></label>
                                    <input type="text" class="form-control" id="ci-contrato_proyecto" name="contrato_proyecto" placeholder="ej: CT-SONDA-2025-089">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="font-weight-bold small">Propietario Técnico (Especialista SONDA) <span class="badge badge-fase2 ml-1">Fase 2</span></label>
                                    <input type="text" class="form-control" id="ci-propietario_tecnico" name="propietario_tecnico" placeholder="ej: Equipo Cloud & SysAdmin SONDA">
                                </div>
                            </div>
                        </div>

                        <!-- CATEGORÍA 3: Ubicación y Topología -->
                        <div class="tab-pane fade" id="ciform-cat3" role="tabpanel">
                            <div class="alert alert-info py-2 small mb-3">
                                <i class="fas fa-info-circle mr-1"></i> <b>Propósito:</b> Ubicar geográficamente y físicamente el activo dentro de la arquitectura de la organización.
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="font-weight-bold small">Sede / Site <span class="badge badge-fase1 ml-1">Fase 1</span> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="ci-sede_site" name="sede_site" placeholder="ej: Datacenter Santiago - Sonda / Datacenter Guayaquil" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="font-weight-bold small">País <span class="badge badge-fase2 ml-1">Fase 2</span></label>
                                    <input type="text" class="form-control" id="ci-pais" name="pais" placeholder="ej: Chile / Ecuador / Colombia">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="font-weight-bold small">Ciudad <span class="badge badge-fase2 ml-1">Fase 2</span></label>
                                    <input type="text" class="form-control" id="ci-ciudad" name="ciudad" placeholder="ej: Santiago / Guayaquil / Quito">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="font-weight-bold small">Rack y Posición U <span class="badge badge-fase2 ml-1">Fase 2</span></label>
                                    <input type="text" class="form-control" id="ci-rack" name="rack" placeholder="ej: RACK-DC01 U14-U16">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="font-weight-bold small">Notas de Ubicación</label>
                                    <input type="text" class="form-control" id="ci-notas_ubicacion" placeholder="ej: Sala A, Fila 2, Gabinete Principal">
                                </div>
                            </div>
                        </div>

                        <!-- CATEGORÍA 4: Operaciones, Monitoreo y Estado -->
                        <div class="tab-pane fade" id="ciform-cat4" role="tabpanel">
                            <div class="alert alert-info py-2 small mb-3">
                                <i class="fas fa-info-circle mr-1"></i> <b>Propósito:</b> Proveer visibilidad del estado operativo, nivel de riesgo e integración con las herramientas de monitoreo.
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="font-weight-bold small">IP de Administración <span class="badge badge-fase1 ml-1">Fase 1</span> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="ci-ip_administracion" name="ip_administracion" placeholder="ej: 10.200.10.15" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="font-weight-bold small">Ambiente <span class="badge badge-fase1 ml-1">Fase 1</span> <span class="text-danger">*</span></label>
                                    <select class="form-control" id="ci-ambiente" name="ambiente" required>
                                        <option value="Producción">Producción</option>
                                        <option value="Contingencia / DR">Contingencia / DR</option>
                                        <option value="Preproducción">Preproducción</option>
                                        <option value="Desarrollo">Desarrollo</option>
                                        <option value="QA">QA</option>
                                        <option value="Laboratorio">Laboratorio</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="font-weight-bold small">Estado del CI <span class="badge badge-fase1 ml-1">Fase 1</span> <span class="text-danger">*</span></label>
                                    <select class="form-control" id="ci-estado_ci" name="estado_ci" required>
                                        <option value="Operativo">Operativo</option>
                                        <option value="Mantenimiento">Mantenimiento</option>
                                        <option value="Planificación">Planificación</option>
                                        <option value="Retirado / Decomisado">Retirado / Decomisado</option>
                                        <option value="Falla">Falla</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="font-weight-bold small">Criticidad <span class="badge badge-fase1 ml-1">Fase 1</span> <span class="text-danger">*</span></label>
                                    <select class="form-control" id="ci-criticidad" name="criticidad" required>
                                        <option value="Crítica">Crítica (Misión Crítica)</option>
                                        <option value="Alta">Alta</option>
                                        <option value="Media">Media</option>
                                        <option value="Baja">Baja</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="font-weight-bold small">Monitoreado (Zabbix / SNMP) <span class="badge badge-fase1 ml-1">Fase 1</span> <span class="text-danger">*</span></label>
                                    <select class="form-control" id="ci-monitoreado" name="monitoreado" required>
                                        <option value="Sí">Sí (Monitoreado en Zabbix / NOC)</option>
                                        <option value="No">No (Sin agente de monitoreo)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- CATEGORÍA 5: Ciclo de Vida, Soporte y Licenciamiento -->
                        <div class="tab-pane fade" id="ciform-cat5" role="tabpanel">
                            <div class="alert alert-info py-2 small mb-3">
                                <i class="fas fa-info-circle mr-1"></i> <b>Propósito:</b> Controlar la vigencia contractual, prevenir riesgos de obsolescencia tecnológica (EOL/EOS) y gestionar licencias.
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="font-weight-bold small">Inicio de Soporte <span class="badge badge-fase1 ml-1">Fase 1</span> <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="ci-inicio_soporte" name="inicio_soporte" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="font-weight-bold small">Fin de Soporte <span class="badge badge-fase1 ml-1">Fase 1</span> <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="ci-fin_soporte" name="fin_soporte" required onchange="calculateLiveSupportDays()">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="font-weight-bold small">Días Fin Soporte (Calculado Auto)</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control font-weight-bold" id="ci-dias_fin_soporte_calc" readonly placeholder="Auto-cálculo...">
                                        <div class="input-group-append">
                                            <span class="input-group-text" id="ci-support-live-badge"><i class="fas fa-calculator"></i></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="font-weight-bold small">Garantía Hasta <span class="badge badge-fase2 ml-1">Fase 2</span></label>
                                    <input type="date" class="form-control" id="ci-garantia_hasta" name="garantia_hasta">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="font-weight-bold small">Licencia <span class="badge badge-fase2 ml-1">Fase 2</span></label>
                                    <input type="text" class="form-control" id="ci-licencia" name="licencia" placeholder="ej: RHEL Server / Windows Svr">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="font-weight-bold small">Fin de Licencia <span class="badge badge-fase2 ml-1">Fase 2</span></label>
                                    <input type="date" class="form-control" id="ci-fin_licencia" name="fin_licencia">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="font-weight-bold small">Fecha EOL (End of Life) <span class="badge badge-fase2 ml-1">Fase 2</span></label>
                                    <input type="date" class="form-control" id="ci-fecha_eol" name="fecha_eol">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="font-weight-bold small">Fecha EOS (End of Support) <span class="badge badge-fase2 ml-1">Fase 2</span></label>
                                    <input type="date" class="form-control" id="ci-fecha_eos" name="fecha_eos">
                                </div>
                                <div class="col-md-8 mb-3">
                                    <label class="font-weight-bold small">Notas Adicionales / Observaciones</label>
                                    <input type="text" class="form-control" id="ci-notas_adicionales" name="notas_adicionales" placeholder="Comentarios de auditoría o mantenimiento">
                                </div>
                            </div>
                        </div> <!-- /#ciform-cat5 -->

                        <!-- CATEGORÍA 6: Fotos, Evidencias & Ubicación Física -->
                        <div class="tab-pane fade" id="ciform-cat6" role="tabpanel">
                            <div class="alert alert-info py-2 small mb-3">
                                <i class="fas fa-camera mr-1"></i> <b>Propósito:</b> Documentar fotográficamente la instalación física del activo (frontal del rack, cableado posterior, etiqueta de serial, sala de datacenter o PDU) con tags de identificación, fecha y observaciones técnicas almacenadas en el servidor.
                            </div>
                            
                            <!-- Subida de Imagen al CI -->
                            <div class="card p-3 bg-light border mb-3" style="border-radius: 10px;">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="font-weight-bold text-primary mb-0">
                                        <i class="fas fa-upload mr-1"></i> Adjuntar Fotografía / Evidencia de Ubicación
                                    </h6>
                                    <span class="badge badge-pill badge-primary px-3 py-1">Categoría 6 Funcional</span>
                                </div>
                                <div class="row">
                                    <div class="col-md-4 mb-2">
                                        <label class="font-weight-bold small">Ubicación Física de la Toma *</label>
                                        <select class="form-control form-control-sm font-weight-bold" id="ciform-img-ubicacion" name="ci_image_ubicacion">
                                            <option value="Frontal del Rack">Frontal del Rack</option>
                                            <option value="Posterior / Cableado">Posterior / Cableado</option>
                                            <option value="Etiqueta / Serial / Placa">Etiqueta / Serial / Placa</option>
                                            <option value="Sala Datacenter / Site">Sala Datacenter / Site</option>
                                            <option value="PDU / Conexión Eléctrica">PDU / Conexión Eléctrica</option>
                                            <option value="Interior / Componentes">Interior / Componentes</option>
                                            <option value="General">General</option>
                                        </select>
                                    </div>
                                    <div class="col-md-5 mb-2">
                                        <label class="font-weight-bold small">Tags de Identificación</label>
                                        <input type="text" class="form-control form-control-sm" id="ciform-img-tags" name="ci_image_tags" placeholder="ej: #FRONTAL, #RACK-01, #SERIAL">
                                        <div class="mt-1 small">
                                            <span class="text-muted mr-1">Sugeridos:</span>
                                            <a href="javascript:void(0)" class="badge badge-light border text-primary mr-1" onclick="addTagToCiform('#FRONTAL')">#FRONTAL</a>
                                            <a href="javascript:void(0)" class="badge badge-light border text-primary mr-1" onclick="addTagToCiform('#POSTERIOR')">#POSTERIOR</a>
                                            <a href="javascript:void(0)" class="badge badge-light border text-primary mr-1" onclick="addTagToCiform('#SERIAL')">#SERIAL</a>
                                            <a href="javascript:void(0)" class="badge badge-light border text-primary mr-1" onclick="addTagToCiform('#RACK')">#RACK</a>
                                            <a href="javascript:void(0)" class="badge badge-light border text-primary mr-1" onclick="addTagToCiform('#PDU')">#PDU</a>
                                            <a href="javascript:void(0)" class="badge badge-light border text-primary mr-1" onclick="addTagToCiform('#CABLEADO')">#CABLEADO</a>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label class="font-weight-bold small">Fecha de Captura / Toma *</label>
                                        <input type="date" class="form-control form-control-sm" id="ciform-img-fecha" name="ci_image_fecha" value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-7 mb-2">
                                        <label class="font-weight-bold small">Observaciones Técnicas de la Foto</label>
                                        <textarea class="form-control form-control-sm" id="ciform-img-obs" name="ci_image_observaciones" rows="2" placeholder="Notas sobre el estado físico, rotulación, puertos, transceivers de fibra o condiciones del rack..."></textarea>
                                    </div>
                                    <div class="col-md-5 mb-2">
                                        <label class="font-weight-bold small">Seleccionar Archivo de Imagen (JPG, PNG, WEBP) *</label>
                                        <input type="file" class="form-control-file form-control-sm p-1 border rounded bg-white" id="ciform-img-file" name="ci_image" accept="image/*" onchange="previewCiformImage(event)">
                                        <div id="ciform-img-preview-box" class="mt-2 text-center" style="display: none;">
                                            <img id="ciform-img-preview" src="" style="max-height: 120px; border-radius: 6px; border: 1px solid #ccc; box-shadow: 0 2px 6px rgba(0,0,0,0.15);">
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                                    <small class="text-muted"><i class="fas fa-info-circle mr-1"></i> Si guarda el CI completo, esta foto se adjuntará automáticamente.</small>
                                    <button type="button" class="btn btn-sm btn-info font-weight-bold" id="btn-ciform-upload" onclick="uploadImageFromForm()">
                                        <i class="fas fa-upload mr-1"></i> Subir Imagen Ahora
                                    </button>
                                </div>
                            </div>

                            <!-- Galería de Fotos Ya Subidas a este CI -->
                            <h6 class="font-weight-bold text-dark mb-2"><i class="fas fa-images mr-1"></i> Evidencias Registradas para este CI:</h6>
                            <div class="row" id="ciform-existing-images">
                                <div class="col-12 text-center text-muted py-3">
                                    <span class="small">Guarde el CI o seleccione un CI existente para visualizar sus evidencias fotográficas.</span>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="modal-footer bg-light" style="border-bottom-left-radius: 14px; border-bottom-right-radius: 14px;">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary font-weight-bold">
                        <i class="fas fa-save mr-1"></i> Guardar Elemento de Configuración
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================== -->
<!-- MODAL: FICHA TÉCNICA 360° (DETALLE DEL CI) -->
<!-- ============================================================== -->
<div class="modal fade" id="modal-ci-detail" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 14px;">
            <div class="modal-header bg-dark text-white" style="border-top-left-radius: 14px; border-top-right-radius: 14px;">
                <h5 class="modal-title font-weight-bold" id="detail-modal-title">
                    <i class="fas fa-file-invoice mr-2 text-info"></i> Ficha Técnica del CI: <span id="detail-ci-hostname">--</span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4" id="detail-modal-body">
                <!-- Se inyecta dinámicamente -->
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-warning font-weight-bold text-dark" id="btn-detail-history" onclick="viewHistoryFromDetail()">
                    <i class="fas fa-history mr-1"></i> Historial de Cambios
                </button>
                <button type="button" class="btn btn-info font-weight-bold" id="btn-detail-impact" onclick="runImpactFromDetail()">
                    <i class="fas fa-bolt mr-1"></i> Análisis de Impacto
                </button>
                <button type="button" class="btn btn-primary font-weight-bold" id="btn-detail-edit" onclick="editFromDetail()">
                    <i class="fas fa-edit mr-1"></i> Editar CI
                </button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================== -->
<!-- MODAL: CREAR / EDITAR SERVICIO DE NEGOCIO -->
<!-- ============================================================== -->
<div class="modal fade" id="modal-service-form" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 14px;">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-briefcase mr-2"></i> Servicio Crítico / Aplicación Empresarial</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="form-service" onsubmit="saveService(event)">
                <input type="hidden" id="service-id" name="id" value="">
                <div class="modal-body p-4">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="font-weight-bold small">Código del Servicio *</label>
                            <input type="text" class="form-control" id="service-code" name="service_code" placeholder="ej: SVC-FACT-01" required>
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="font-weight-bold small">Nombre del Servicio *</label>
                            <input type="text" class="form-control" id="service-name" name="nombre_servicio" placeholder="ej: Servicio de Facturación Electrónica SAP" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold small">Cliente *</label>
                            <input type="text" class="form-control" id="service-client" name="cliente" placeholder="ej: SONDA Corp" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="font-weight-bold small">Criticidad de Negocio *</label>
                            <select class="form-control" id="service-criticality" name="criticidad_negocio" required>
                                <option value="Crítica">Crítica</option>
                                <option value="Alta">Alta</option>
                                <option value="Media">Media</option>
                                <option value="Baja">Baja</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="font-weight-bold small">Estado *</label>
                            <select class="form-control" id="service-state" name="estado" required>
                                <option value="Operativo">Operativo</option>
                                <option value="Degradado">Degradado</option>
                                <option value="Mantenimiento">Mantenimiento</option>
                                <option value="Inactivo">Inactivo</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold small">Propietario de Negocio (Cliente)</label>
                            <input type="text" class="form-control" id="service-owner-biz" name="propietario_negocio" placeholder="ej: Gerente de Finanzas">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold small">Propietario Técnico (SONDA)</label>
                            <input type="text" class="form-control" id="service-owner-tech" name="propietario_tecnico" placeholder="ej: Líder Técnico Infraestructura">
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-bold small">Descripción del Servicio</label>
                        <textarea class="form-control" id="service-desc" name="descripcion" rows="3" placeholder="Detalle funcional del servicio y acuerdos SLA"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary font-weight-bold"><i class="fas fa-save mr-1"></i> Guardar Servicio</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================== -->
<!-- MODAL: CONECTAR CIs (NUEVA DEPENDENCIA / RELACIÓN) -->
<!-- ============================================================== -->
<div class="modal fade" id="modal-relationship-form" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 14px;">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-link mr-2"></i> Crear Relación de Dependencia</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="form-relationship" onsubmit="saveRelationship(event)">
                <div class="modal-body p-4">
                    <div class="form-group">
                        <label class="font-weight-bold small">CI Origen (Dependiente) *</label>
                        <select class="form-control" id="rel-source-ci" name="source_ci_id" required>
                            <!-- Opciones cargadas dinámicamente -->
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold small">Tipo de Relación *</label>
                        <select class="form-control" id="rel-type" name="relationship_type" required>
                            <option value="depende_de">depende de (consumo lógico)</option>
                            <option value="conecta_con">conecta con (enlace de red / puerto)</option>
                            <option value="aloja_a">aloja a (host / hipervisor)</option>
                            <option value="ejecuta_en">ejecuta en (plataforma / servidor)</option>
                            <option value="respalda_a">respalda a (storage / backup)</option>
                            <option value="alimenta_electricamente">alimenta eléctricamente (PDU / UPS)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold small">CI Destino (Del que depende) *</label>
                        <select class="form-control" id="rel-target-ci" name="target_ci_id" required>
                            <!-- Opciones cargadas dinámicamente -->
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold small">Impacto ante Falla *</label>
                            <select class="form-control" id="rel-impact" name="impacto_falla" required>
                                <option value="Crítico">Crítico (Indisponibilidad total)</option>
                                <option value="Alto">Alto (Degradación severa)</option>
                                <option value="Medio">Medio (Riesgo moderado)</option>
                                <option value="Baja">Baja (Sin impacto directo)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold small">Descripción del Enlace</label>
                            <input type="text" class="form-control" id="rel-desc" name="descripcion" placeholder="ej: Puerto Gi0/1 / VLAN 200">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-info font-weight-bold"><i class="fas fa-link mr-1"></i> Vincular Dependencia</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================== -->
<!-- MODAL: SIMULADOR DE ANÁLISIS DE IMPACTO EN TIEMPO REAL -->
<!-- ============================================================== -->
<div class="modal fade" id="modal-impact-simulator" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 14px;">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title font-weight-bold">
                    <i class="fas fa-bolt mr-2"></i> Simulador de Falla & Análisis de Impacto de Negocio
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-3">
                    Seleccione un Elemento de Configuración para simular su caída y calcular el efecto cascada sobre otros CIs y servicios de negocio:
                </p>
                <div class="row mb-3">
                    <div class="col-md-9">
                        <select class="form-control font-weight-bold" id="select-impact-ci">
                            <!-- Opciones cargadas dinámicamente -->
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="button" class="btn btn-danger btn-block font-weight-bold" onclick="executeImpactAnalysis()">
                            <i class="fas fa-radiation mr-1"></i> Calcular
                        </button>
                    </div>
                </div>

                <div id="impact-results-container" style="display: none;">
                    <div class="alert alert-danger py-2 mb-3 d-flex justify-content-between align-items-center">
                        <div>
                            <b>Nivel Máximo de Impacto:</b> <span id="impact-max-level">--</span>
                        </div>
                        <span class="badge badge-light font-weight-bold" id="impact-total-count">0 CIs afectados</span>
                    </div>

                    <h6 class="font-weight-bold text-danger mb-2"><i class="fas fa-briefcase mr-1"></i> Servicios de Negocio Comprometidos:</h6>
                    <ul class="list-group mb-3" id="impact-services-list">
                        <!-- Inyectado -->
                    </ul>

                    <h6 class="font-weight-bold text-dark mb-2"><i class="fas fa-cubes mr-1"></i> CIs Afectados en Cascada:</h6>
                    <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                        <table class="table table-sm table-bordered">
                            <thead class="bg-light">
                                <tr>
                                    <th>ID CI</th>
                                    <th>Hostname</th>
                                    <th>Tipo</th>
                                    <th>Relación de Dependencia</th>
                                    <th>Impacto</th>
                                </tr>
                            </thead>
                            <tbody id="impact-cis-table-body">
                                <!-- Inyectado -->
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<!-- ============================================================== -->
<!-- MODAL: SUBIR IMAGEN / EVIDENCIA A UN CI -->
<!-- ============================================================== -->
<div class="modal fade" id="modal-upload-image" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 14px;">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-camera mr-2"></i> Adjuntar Evidencia Fotográfica al CI</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="form-upload-image" onsubmit="submitUploadImage(event)" enctype="multipart/form-data">
                <div class="modal-body p-4">
                    <div class="form-group mb-3">
                        <label class="font-weight-bold small">Elemento de Configuración (CI) Asociado *</label>
                        <select class="form-control font-weight-bold" id="upload-img-ci" name="ci_id" required>
                            <!-- Opciones cargadas dinámicamente -->
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold small">Ubicación Física de la Foto *</label>
                            <select class="form-control" id="upload-img-ubicacion" name="ubicacion_foto" required>
                                <option value="Frontal del Rack">Frontal del Rack</option>
                                <option value="Posterior / Cableado">Posterior / Cableado</option>
                                <option value="Etiqueta / Serial / Placa">Etiqueta / Serial / Placa</option>
                                <option value="Sala Datacenter / Site">Sala Datacenter / Site</option>
                                <option value="PDU / Conexión Eléctrica">PDU / Conexión Eléctrica</option>
                                <option value="Interior / Componentes">Interior / Componentes</option>
                                <option value="General">General</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold small">Fecha de Toma / Creación *</label>
                            <input type="date" class="form-control" id="upload-img-fecha" name="fecha_creacion_foto" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold small">Tags de Identificación</label>
                        <input type="text" class="form-control" id="upload-img-tags" name="tags" placeholder="ej: #FRONTAL, #RACK-01, #PUERTOS, #SERIAL">
                        <small class="text-muted">Etiquetas separadas por comas para clasificar y filtrar visualmente la foto.</small>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold small">Observaciones Técnicas</label>
                        <textarea class="form-control" id="upload-img-obs" name="observaciones" rows="2" placeholder="Notas sobre el estado físico, conexiones, puertos o rotulación del CI..."></textarea>
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-bold small">Seleccionar Archivo de Imagen *</label>
                        <input type="file" class="form-control-file p-2 border rounded bg-light" id="upload-img-file" name="image" accept="image/*" required onchange="previewUploadImage(event)">
                        <div id="upload-img-preview-box" class="mt-2 text-center" style="display: none;">
                            <img id="upload-img-preview" src="" style="max-height: 180px; border-radius: 8px; border: 1px solid #ddd;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-info font-weight-bold" id="btn-submit-upload-image">
                        <i class="fas fa-upload mr-1"></i> Subir y Registrar Imagen
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================== -->
<!-- MODAL: LIGHTBOX / VISOR DE IMAGEN EN ALTA RESOLUCIÓN -->
<!-- ============================================================== -->
<div class="modal fade" id="modal-image-lightbox" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content bg-dark text-white" style="border-radius: 14px;">
            <div class="modal-header border-secondary">
                <h5 class="modal-title font-weight-bold" id="lightbox-title">Evidencia Fotográfica</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body text-center p-2">
                <img id="lightbox-img" src="" class="img-fluid rounded shadow" style="max-height: 75vh; object-fit: contain;">
                <div class="mt-3 text-left p-3 rounded" style="background: rgba(255,255,255,0.08);" id="lightbox-meta">
                    <!-- Meta inyectada -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================== -->
<!-- MODAL: DETALLE / INSPECCIÓN FORENSE DE AUDITORÍA (DIFF VIEWER) -->
<!-- ============================================================== -->
<div class="modal fade" id="modal-audit-detail" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 14px; overflow: hidden;">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title font-weight-bold" id="modal-audit-detail-title">
                    <i class="fas fa-history mr-2 text-warning"></i> Inspección de Auditoría #<span id="audit-detail-id">--</span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4" id="modal-audit-detail-body">
                <!-- Se inyecta dinámicamente con inspectAuditLog() -->
            </div>
            <div class="modal-footer bg-light d-flex justify-content-between">
                <div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="copyAuditJsonToClipboard()">
                        <i class="fas fa-copy mr-1"></i> Copiar Registro JSON Crudo
                    </button>
                </div>
                <div>
                    <button type="button" class="btn btn-secondary btn-sm px-4" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- Scripts Necesarios (Vis.js y Chart.js Local) -->
<script src="vendor/vis/vis-network.min.js"></script>
<script src="vendor/chartjs/chart.umd.min.js"></script>
<script>
    if (typeof Chart === 'undefined') {
        document.write('<script src="https://cdn.jsdelivr.net/npm/chart.js"><\/script>');
    }
</script>

<script>
    const API_URL = 'api.php';
    let globalCIs = [];
    let globalServices = [];
    let globalClientsList = [];
    let globalServicesList = [];
    let currentViewMode = 'all'; // all, fase1, fase2
    let networkGraph = null;
    let currentMapLayout = 'hierarchical'; // 'hierarchical' | 'organic'
    let debounceTimer = null;
    let debounceImgTimer = null;

    $(document).ready(function() {
        loadCIs();
        loadServices();

        // Inicializar componentes cuando se active su pestaña
        $('a[data-toggle="pill"]').on('shown.bs.tab', function(e) {
            if (e.target.id === 'tab-mapa-link') {
                setTimeout(initTopologyGraph, 100);
            }
            if (e.target.id === 'tab-historial-link') {
                loadAuditLogs(1);
            }
        });
    });

    function debounceFilter() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            loadCIs();
        }, 300);
    }

    function onClienteFilterChange() {
        populateServiceFilter(globalServicesList);
        loadCIs();
    }

    function onServicioFilterChange() {
        loadCIs();
    }

    function populateClientFilter(clients) {
        const select = $('#filter-cliente');
        const currentVal = select.val();
        if (select.find('option').length <= 1 && clients && clients.length > 0) {
            clients.forEach(c => {
                select.append(`<option value="${escapeHtml(c)}">${escapeHtml(c)}</option>`);
            });
            if (currentVal) select.val(currentVal);
        }
    }

    function populateServiceFilter(services) {
        const selectedClient = $('#filter-cliente').val();
        const select = $('#filter-servicio');
        const currentVal = select.val();
        select.empty();
        select.append('<option value="">-- Todos los Servicios --</option>');

        if (services && services.length > 0) {
            const filtered = selectedClient ? services.filter(s => s.cliente === selectedClient) : services;
            const uniqueNames = [...new Set(filtered.map(s => s.nombre_servicio))];
            uniqueNames.forEach(name => {
                select.append(`<option value="${escapeHtml(name)}">${escapeHtml(name)}</option>`);
            });
        }
        if (currentVal) select.val(currentVal);
    }

    function resetFilters() {
        $('#filter-cliente').val('');
        $('#filter-servicio').val('');
        $('#filter-search').val('');
        $('#filter-support').val('all');
        $('#filter-tipo').val('');
        $('#filter-ambiente').val('');
        $('#filter-view-mode').val('all');
        currentViewMode = 'all';
        onClienteFilterChange();
    }

    function changeViewMode() {
        currentViewMode = $('#filter-view-mode').val();
        renderCIsTable(globalCIs);
    }

    // ====================================================================
    // CARGAR CIs Y ACTUALIZAR KPIS (CON FILTROS PRIMARIOS CLIENTE / SERVICIO)
    // ====================================================================
    function loadCIs() {
        const cliente = $('#filter-cliente').val();
        const servicio = $('#filter-servicio').val();
        const q = $('#filter-search').val();
        const support = $('#filter-support').val();
        const tipo = $('#filter-tipo').val();
        const ambiente = $('#filter-ambiente').val();

        $.getJSON(API_URL, {
            action: 'list_cis',
            cliente: cliente,
            servicio: servicio,
            q: q,
            support_status: support,
            tipo_ci: tipo,
            ambiente: ambiente
        }, function(res) {
            if (res.success) {
                globalCIs = res.cis;
                updateKPIs(res.kpis);
                renderCIsTable(res.cis);
                renderCycleTable(res.cis);
                populateCiSelects(res.cis);

                if (res.clients) {
                    globalClientsList = res.clients;
                    populateClientFilter(res.clients);
                }
                if (res.services) {
                    globalServicesList = res.services;
                    populateServiceFilter(res.services);
                }
            } else {
                toastr.error(res.error || 'Error al cargar inventario');
            }
        }).fail(function() {
            toastr.error('Error de conexión con el backend de CMDB_SONDA');
        });
    }

    function updateKPIs(kpis) {
        if (!kpis) return;
        $('#kpi-total-cis').text(kpis.total_cis || 0);
        $('#kpi-total-services').text(kpis.total_services || 0);
        $('#kpi-expired-support').text(kpis.expired_support || 0);
        $('#kpi-due-soon').text(kpis.due_soon_support || 0);
        $('#kpi-monitored-pct').text((kpis.monitored_pct || 0) + '%');
        $('#kpi-fase1-compliance').text((kpis.fase1_compliance_pct || 0) + '%');

        // Pestaña Ciclo de Vida
        $('#cycle-expired-count').text(kpis.expired_support || 0);
        $('#cycle-due-soon-count').text(kpis.due_soon_support || 0);
        $('#cycle-due-60-count').text(globalCIs.filter(c => c.dias_fin_soporte > 30 && c.dias_fin_soporte <= 60).length);
        $('#cycle-active-count').text(kpis.active_support || 0);

        // Activación de Alarmas Rojas Titilantes
        const expiredCount = parseInt(kpis.expired_support) || 0;
        if (expiredCount > 0) {
            $('#kpi-card-expired').addClass('kpi-card-alarm-active');
            $('#cycle-card-expired').addClass('kpi-card-alarm-active');
            $('#kpi-expired-icon').addClass('icon-alarm-titilando');
            $('#kpi-expired-support').addClass('text-alarm-titilando').html(`<i class="fas fa-bell mr-1"></i>${expiredCount}`);
            $('#cycle-expired-count').addClass('text-alarm-titilando').html(`<i class="fas fa-bell mr-1"></i>${expiredCount}`);
            $('#top-alarm-banner-text').text(`Existen ${expiredCount} CIs con soporte contractual vencido. Requieren regularización urgente.`);
            $('#top-alarm-banner').fadeIn(300).css('display', 'flex');
        } else {
            $('#kpi-card-expired').removeClass('kpi-card-alarm-active');
            $('#cycle-card-expired').removeClass('kpi-card-alarm-active');
            $('#kpi-expired-icon').removeClass('icon-alarm-titilando');
            $('#kpi-expired-support').removeClass('text-alarm-titilando').text('0');
            $('#cycle-expired-count').removeClass('text-alarm-titilando').text('0');
            $('#top-alarm-banner').hide();
        }
    }

    // ====================================================================
    // RENDERIZADO DE LA TABLA SEGÚN VISTA (TODOS, FASE 1, FASE 2)
    // ====================================================================
    function renderCIsTable(cis) {
        const thead = $('#table-cis-header');
        const tbody = $('#table-cis-body');
        tbody.empty();

        if (currentViewMode === 'fase1') {
            // Cabecera Fase 1 (Core / Obligatorios)
            thead.html(`
                <th>ID CI</th>
                <th>Hostname</th>
                <th>Tipo CI</th>
                <th>Cliente</th>
                <th>Servicio</th>
                <th>Sede / Site</th>
                <th>IP Admin</th>
                <th>Ambiente</th>
                <th>Estado</th>
                <th>Criticidad</th>
                <th>Soporte</th>
                <th class="text-center">Integridad F1</th>
                <th class="text-right">Acciones</th>
            `);

            if (cis.length === 0) {
                tbody.html('<tr><td colspan="13" class="text-center text-muted py-4">No se encontraron CIs con los filtros seleccionados.</td></tr>');
                $('#cis-table-count').text('Mostrando 0 registros');
                return;
            }

            cis.forEach(ci => {
                const isAlarm = ci.support_badge_class === 'danger' || (ci.dias_fin_soporte !== null && parseInt(ci.dias_fin_soporte) < 0);
                const supportBadge = isAlarm 
                    ? `<span class="badge badge-alarm-titilando"><span class="beacon-alarm-titilando"></span><i class="fas fa-bell mr-1"></i>${escapeHtml(ci.support_label)}</span>`
                    : `<span class="badge badge-${ci.support_badge_class}">${ci.support_label}</span>`;
                const rowClass = isAlarm ? 'class="row-alarm-titilando"' : '';

                const tr = $(`
                    <tr ${rowClass}>
                        <td><span class="font-weight-bold text-primary">${escapeHtml(ci.id_ci)}</span></td>
                        <td>
                            <a href="javascript:void(0)" class="font-weight-bold text-dark" onclick="viewCiDetail(${ci.id})">
                                ${escapeHtml(ci.hostname_nombre)}
                            </a>
                        </td>
                        <td><span class="badge badge-light border">${escapeHtml(ci.tipo_ci)}</span></td>
                        <td>${escapeHtml(ci.cliente)}</td>
                        <td>${escapeHtml(ci.servicio)}</td>
                        <td>${escapeHtml(ci.sede_site)}</td>
                        <td><code>${escapeHtml(ci.ip_administracion)}</code></td>
                        <td><span class="badge badge-secondary">${escapeHtml(ci.ambiente)}</span></td>
                        <td><span class="badge badge-success">${escapeHtml(ci.estado_ci)}</span></td>
                        <td><span class="badge badge-criticidad-${escapeHtml(ci.criticidad.toLowerCase())}">${escapeHtml(ci.criticidad)}</span></td>
                        <td>${supportBadge}</td>
                        <td class="text-center">
                            <span class="badge badge-${ci.fase1_complete ? 'success' : 'danger'}">${ci.fase1_score}%</span>
                        </td>
                        <td class="text-right text-nowrap">
                            <button class="btn btn-xs btn-outline-info mr-1" title="Ficha Técnica" onclick="viewCiDetail(${ci.id})"><i class="fas fa-eye"></i></button>
                            <button class="btn btn-xs btn-outline-warning mr-1" title="Ver Historial de Cambios" onclick="filterAuditByCi(${ci.id}, '${escapeHtml(ci.id_ci)}', '${escapeHtml(ci.hostname_nombre)}')"><i class="fas fa-history"></i></button>
                            <button class="btn btn-xs btn-outline-primary mr-1" title="Editar" onclick="editCi(${ci.id})"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-xs btn-outline-danger" title="Eliminar" onclick="deleteCi(${ci.id}, '${escapeHtml(ci.hostname_nombre)}')"><i class="fas fa-trash-alt"></i></button>
                        </td>
                    </tr>
                `);
                tbody.append(tr);
            });

        } else if (currentViewMode === 'fase2') {
            // Cabecera Fase 2 (Enriquecimiento)
            thead.html(`
                <th>ID CI</th>
                <th>Hostname</th>
                <th>Fabricante</th>
                <th>Modelo</th>
                <th>Número Serie</th>
                <th>Versión SO/FW</th>
                <th>Rack</th>
                <th>Garantía Hasta</th>
                <th>Licencia</th>
                <th>EOL</th>
                <th>EOS</th>
                <th class="text-right">Acciones</th>
            `);

            if (cis.length === 0) {
                tbody.html('<tr><td colspan="12" class="text-center text-muted py-4">No se encontraron CIs con los filtros seleccionados.</td></tr>');
                $('#cis-table-count').text('Mostrando 0 registros');
                return;
            }

            cis.forEach(ci => {
                const tr = $(`
                    <tr>
                        <td><span class="font-weight-bold text-primary">${escapeHtml(ci.id_ci)}</span></td>
                        <td>
                            <a href="javascript:void(0)" class="font-weight-bold text-dark" onclick="viewCiDetail(${ci.id})">
                                ${escapeHtml(ci.hostname_nombre)}
                            </a>
                        </td>
                        <td>${escapeHtml(ci.fabricante || '--')}</td>
                        <td>${escapeHtml(ci.modelo || '--')}</td>
                        <td><code>${escapeHtml(ci.numero_serie || '--')}</code></td>
                        <td>${escapeHtml(ci.version_firmware_so || '--')}</td>
                        <td>${escapeHtml(ci.rack || '--')}</td>
                        <td>${escapeHtml(ci.garantia_hasta || '--')}</td>
                        <td>${escapeHtml(ci.licencia || '--')}</td>
                        <td>${escapeHtml(ci.fecha_eol || '--')}</td>
                        <td>${escapeHtml(ci.fecha_eos || '--')}</td>
                        <td class="text-right text-nowrap">
                            <button class="btn btn-xs btn-outline-info mr-1" title="Ficha Técnica" onclick="viewCiDetail(${ci.id})"><i class="fas fa-eye"></i></button>
                            <button class="btn btn-xs btn-outline-warning mr-1" title="Ver Historial de Cambios" onclick="filterAuditByCi(${ci.id}, '${escapeHtml(ci.id_ci)}', '${escapeHtml(ci.hostname_nombre)}')"><i class="fas fa-history"></i></button>
                            <button class="btn btn-xs btn-outline-primary mr-1" title="Editar" onclick="editCi(${ci.id})"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-xs btn-outline-danger" title="Eliminar" onclick="deleteCi(${ci.id}, '${escapeHtml(ci.hostname_nombre)}')"><i class="fas fa-trash-alt"></i></button>
                        </td>
                    </tr>
                `);
                tbody.append(tr);
            });

        } else {
            // Cabecera Vista Completa (5 Categorías integradas)
            thead.html(`
                <th>ID CI</th>
                <th>Hostname</th>
                <th>Tipo CI</th>
                <th>Cliente / Servicio</th>
                <th>IP Admin</th>
                <th>Ambiente</th>
                <th>Sede</th>
                <th>Soporte Contractual</th>
                <th>Monitoreo</th>
                <th class="text-center">Dependencias</th>
                <th class="text-right">Acciones</th>
            `);

            if (cis.length === 0) {
                tbody.html('<tr><td colspan="11" class="text-center text-muted py-4">No se encontraron CIs con los filtros seleccionados.</td></tr>');
                $('#cis-table-count').text('Mostrando 0 registros');
                return;
            }

            cis.forEach(ci => {
                const totalRels = (parseInt(ci.outgoing_relations_count) || 0) + (parseInt(ci.incoming_relations_count) || 0);
                const isAlarm = ci.support_badge_class === 'danger' || (ci.dias_fin_soporte !== null && parseInt(ci.dias_fin_soporte) < 0);
                const supportBadge = isAlarm 
                    ? `<span class="badge badge-alarm-titilando"><span class="beacon-alarm-titilando"></span><i class="fas fa-bell mr-1"></i>${escapeHtml(ci.support_label)}</span>`
                    : `<span class="badge badge-${ci.support_badge_class}">${ci.support_label}</span>`;
                const rowClass = isAlarm ? 'class="row-alarm-titilando"' : '';

                const tr = $(`
                    <tr ${rowClass}>
                        <td><span class="font-weight-bold text-primary">${escapeHtml(ci.id_ci)}</span></td>
                        <td>
                            <a href="javascript:void(0)" class="font-weight-bold text-dark" onclick="viewCiDetail(${ci.id})">
                                ${escapeHtml(ci.hostname_nombre)}
                            </a>
                            <div class="small text-muted">${escapeHtml(ci.fabricante || '')} ${escapeHtml(ci.modelo || '')}</div>
                        </td>
                        <td><span class="badge badge-light border">${escapeHtml(ci.tipo_ci)}</span></td>
                        <td>
                            <div class="font-weight-bold">${escapeHtml(ci.cliente)}</div>
                            <div class="small text-muted">${escapeHtml(ci.servicio)}</div>
                        </td>
                        <td><code>${escapeHtml(ci.ip_administracion)}</code></td>
                        <td><span class="badge badge-secondary">${escapeHtml(ci.ambiente)}</span></td>
                        <td><small>${escapeHtml(ci.sede_site)}</small></td>
                        <td>
                            ${supportBadge}
                            <div class="small text-muted">Fin: ${escapeHtml(ci.fin_soporte || '--')}</div>
                        </td>
                        <td>
                            <span class="badge badge-${ci.monitoreado === 'Sí' ? 'success' : 'secondary'}">
                                <i class="fas fa-${ci.monitoreado === 'Sí' ? 'check' : 'times'} mr-1"></i> ${escapeHtml(ci.monitoreado)}
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="badge badge-info" title="${ci.outgoing_relations_count} dependencias directas">
                                <i class="fas fa-link mr-1"></i> ${totalRels}
                            </span>
                        </td>
                        <td class="text-right text-nowrap">
                            <button class="btn btn-xs btn-outline-info mr-1" title="Ficha Técnica 360°" onclick="viewCiDetail(${ci.id})"><i class="fas fa-eye"></i></button>
                            <button class="btn btn-xs btn-outline-warning mr-1" title="Ver Historial de Cambios" onclick="filterAuditByCi(${ci.id}, '${escapeHtml(ci.id_ci)}', '${escapeHtml(ci.hostname_nombre)}')"><i class="fas fa-history"></i></button>
                            <button class="btn btn-xs btn-outline-primary mr-1" title="Editar" onclick="editCi(${ci.id})"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-xs btn-outline-danger" title="Eliminar" onclick="deleteCi(${ci.id}, '${escapeHtml(ci.hostname_nombre)}')"><i class="fas fa-trash-alt"></i></button>
                        </td>
                    </tr>
                `);
                tbody.append(tr);
            });
        }

        $('#cis-table-count').text(`Mostrando ${cis.length} Elementos de Configuración`);
    }

    // ====================================================================
    // RENDERIZADO DE TABLA DE CICLO DE VIDA Y SOPORTE
    // ====================================================================
    function renderCycleTable(cis) {
        const tbody = $('#table-cycle-body');
        tbody.empty();

        if (cis.length === 0) {
            tbody.html('<tr><td colspan="11" class="text-muted py-3">Sin datos registrados</td></tr>');
            return;
        }

        cis.forEach(ci => {
            const isAlarm = ci.support_badge_class === 'danger' || (ci.dias_fin_soporte !== null && parseInt(ci.dias_fin_soporte) < 0);
            const supportBadge = isAlarm 
                ? `<span class="badge badge-alarm-titilando"><span class="beacon-alarm-titilando"></span><i class="fas fa-bell mr-1"></i>${escapeHtml(ci.support_label)}</span>`
                : `<span class="badge badge-${ci.support_badge_class}">${ci.support_label}</span>`;
            const rowClass = isAlarm ? 'class="row-alarm-titilando"' : '';

            const tr = $(`
                <tr ${rowClass}>
                    <td class="font-weight-bold text-primary">${escapeHtml(ci.id_ci)}</td>
                    <td class="text-left font-weight-bold">${escapeHtml(ci.hostname_nombre)}</td>
                    <td>${escapeHtml(ci.tipo_ci)}</td>
                    <td>${escapeHtml(ci.inicio_soporte || '--')}</td>
                    <td>${escapeHtml(ci.fin_soporte || '--')}</td>
                    <td class="font-weight-bold ${isAlarm ? 'text-alarm-titilando' : ''}">${ci.dias_fin_soporte !== null ? ci.dias_fin_soporte + ' d' : '--'}</td>
                    <td>${supportBadge}</td>
                    <td>${escapeHtml(ci.garantia_hasta || '--')}</td>
                    <td>${escapeHtml(ci.fin_licencia || '--')}</td>
                    <td>${escapeHtml(ci.fecha_eol || '--')}</td>
                    <td>${escapeHtml(ci.fecha_eos || '--')}</td>
                </tr>
            `);
            tbody.append(tr);
        });
    }

    // ====================================================================
    // SERVICIOS CRÍTICOS & APLICACIONES
    // ====================================================================
    function loadServices() {
        $.getJSON(API_URL, { action: 'services_list' }, function(res) {
            if (res.success) {
                globalServices = res.services;
                renderServicesCards(res.services);
                populateServiceSelects(res.services);
            }
        });
    }

    function renderServicesCards(services) {
        const container = $('#services-cards-container');
        container.empty();

        if (services.length === 0) {
            container.html('<div class="col-12 text-center text-muted py-4">No hay servicios de negocio registrados aún.</div>');
            return;
        }

        services.forEach(svc => {
            const card = $(`
                <div class="col-lg-6 mb-4">
                    <div class="card card-outline card-primary shadow-sm h-100" style="border-radius: 12px;">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <div>
                                <span class="badge badge-criticidad-${escapeHtml(svc.criticidad_negocio.toLowerCase())} mr-2">${escapeHtml(svc.criticidad_negocio)}</span>
                                <span class="font-weight-bold text-primary">${escapeHtml(svc.service_code)}</span>
                            </div>
                            <span class="badge badge-${svc.estado === 'Operativo' ? 'success' : 'warning'}">${escapeHtml(svc.estado)}</span>
                        </div>
                        <div class="card-body">
                            <h5 class="font-weight-bold text-dark mb-1">${escapeHtml(svc.nombre_servicio)}</h5>
                            <p class="text-muted small mb-3">${escapeHtml(svc.descripcion || 'Sin descripción adicional.')}</p>
                            
                            <div class="row small mb-3">
                                <div class="col-6">
                                    <b>Cliente:</b> ${escapeHtml(svc.cliente)}<br>
                                    <b>Dueño Negocio:</b> ${escapeHtml(svc.propietario_negocio || 'No asignado')}
                                </div>
                                <div class="col-6">
                                    <b>Dueño Técnico SONDA:</b> ${escapeHtml(svc.propietario_tecnico || 'No asignado')}<br>
                                    <b>CIs Sustentadores:</b> <span class="badge badge-info">${svc.cis_count} activos</span>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-light d-flex justify-content-between align-items-center">
                            <span class="small text-muted">
                                ${svc.risk_cis_count > 0 ? `<span class="text-danger font-weight-bold"><i class="fas fa-exclamation-triangle"></i> ${svc.risk_cis_count} CIs con riesgo soporte</span>` : '<span class="text-success"><i class="fas fa-check-circle"></i> Todos los CIs al día</span>'}
                            </span>
                            <button class="btn btn-sm btn-outline-primary" onclick="filterByService(${svc.id})">
                                <i class="fas fa-eye mr-1"></i> Ver CIs Asociados
                            </button>
                        </div>
                    </div>
                </div>
            `);
            container.append(card);
        });
    }

    function filterByService(serviceId) {
        $('#tab-inventario-link').tab('show');
        $.getJSON(API_URL, { action: 'list_cis', service_id: serviceId }, function(res) {
            if (res.success) {
                renderCIsTable(res.cis);
            }
        });
    }

    function populateServiceSelects(services) {
        const select = $('#ci-service_id');
        const mapSelect = $('#select-map-service');
        select.find('option:not(:first)').remove();
        if (mapSelect.length) mapSelect.find('option:not(:first)').remove();
        services.forEach(s => {
            select.append(`<option value="${s.id}">${escapeHtml(s.service_code)} - ${escapeHtml(s.nombre_servicio)}</option>`);
            if (mapSelect.length) mapSelect.append(`<option value="${s.id}">${escapeHtml(s.service_code)} - ${escapeHtml(s.nombre_servicio)}</option>`);
        });
    }

    function populateCiSelects(cis) {
        const srcSelect = $('#rel-source-ci');
        const tgtSelect = $('#rel-target-ci');
        const impactSelect = $('#select-impact-ci');
        const imgFilterSelect = $('#filter-img-ci');
        const imgUploadSelect = $('#upload-img-ci');

        srcSelect.empty();
        tgtSelect.empty();
        impactSelect.empty();

        const curImgFilter = imgFilterSelect.val();
        imgFilterSelect.empty();
        imgFilterSelect.append('<option value="">-- Todos los CIs --</option>');

        const curImgUpload = imgUploadSelect.val();
        imgUploadSelect.empty();
        imgUploadSelect.append('<option value="">-- Seleccione un CI --</option>');

        cis.forEach(c => {
            const opt = `<option value="${c.id}">${escapeHtml(c.hostname_nombre)} [${escapeHtml(c.tipo_ci)}] - ${escapeHtml(c.ip_administracion)}</option>`;
            srcSelect.append(opt);
            tgtSelect.append(opt);
            impactSelect.append(opt);
            imgFilterSelect.append(`<option value="${c.id}">${escapeHtml(c.hostname_nombre)} (${escapeHtml(c.id_ci)})</option>`);
            imgUploadSelect.append(`<option value="${c.id}">${escapeHtml(c.hostname_nombre)} (${escapeHtml(c.id_ci)}) - ${escapeHtml(c.sede_site)}</option>`);
        });

        if (curImgFilter) imgFilterSelect.val(curImgFilter);
        if (curImgUpload) imgUploadSelect.val(curImgUpload);
    }

    // ====================================================================
    // MAPA DE DEPENDENCIAS INTERACTIVO (VIS.JS GRAPH)
    // ====================================================================
    function initTopologyGraph() {
        const container = document.getElementById('network-graph-container');
        if (!container) return;

        if (typeof vis === 'undefined' || !vis.Network) {
            $(container).html(`
                <div class="alert alert-warning m-4 text-center">
                    <h5><i class="fas fa-exclamation-triangle mr-2"></i> Librería Vis.js no disponible</h5>
                    <p class="mb-0">Cargando biblioteca de grafos local... Si este mensaje persiste, refresque la página con Ctrl + F5.</p>
                </div>
            `);
            return;
        }

        $(container).html('<div class="d-flex justify-content-center align-items-center h-100 text-muted"><i class="fas fa-spinner fa-spin fa-2x mr-2"></i> Cargando topología y conexiones de red...</div>');

        const serviceId = $('#select-map-service').val();
        const params = { action: 'topology_graph' };
        if (serviceId) params.service_id = serviceId;

        $.getJSON(API_URL, params, function(res) {
            if (res.success) {
                if (!res.nodes || res.nodes.length === 0) {
                    $(container).html(`
                        <div class="d-flex flex-column justify-content-center align-items-center h-100 text-muted">
                            <i class="fas fa-project-diagram fa-3x mb-3 text-secondary"></i>
                            <h5 class="font-weight-bold">Sin elementos para graficar</h5>
                            <p class="small">No se encontraron CIs o dependencias registradas para los filtros seleccionados.</p>
                        </div>
                    `);
                    return;
                }

                $(container).empty();

                if (networkGraph !== null) {
                    try { networkGraph.destroy(); } catch (e) {}
                    networkGraph = null;
                }

                const data = {
                    nodes: new vis.DataSet(res.nodes),
                    edges: new vis.DataSet(res.edges)
                };

                const isHierarchical = currentMapLayout === 'hierarchical' || currentMapLayout === 'horizontal';

                const options = {
                    layout: {
                        hierarchical: isHierarchical ? {
                            enabled: true,
                            direction: currentMapLayout === 'horizontal' ? 'LR' : 'UD',
                            sortMethod: 'directed', // Respeta estrictamente los niveles arquitectónicos (Level 0, 1, 2, 3, 4)
                            levelSeparation: 250,   // Distancia vertical amplia entre capas gerenciales (250px)
                            nodeSpacing: 340,       // Distancia horizontal generosa entre tarjetas de la misma capa (340px)
                            treeSpacing: 380,
                            blockShifting: true,
                            edgeMinimization: true,
                            parentCentralization: true
                        } : {
                            enabled: false
                        }
                    },
                    physics: isHierarchical ? {
                        enabled: true,
                        hierarchicalRepulsion: {
                            nodeDistance: 320,
                            centralGravity: 0.0,
                            springLength: 220,
                            springConstant: 0.01,
                            damping: 0.1
                        },
                        solver: 'hierarchicalRepulsion',
                        stabilization: {
                            enabled: true,
                            iterations: 150
                        }
                    } : {
                        enabled: true,
                        solver: 'forceAtlas2Based',
                        forceAtlas2Based: {
                            gravitationalConstant: -120,
                            centralGravity: 0.01,
                            springLength: 240,
                            springConstant: 0.04,
                            damping: 0.5
                        },
                        stabilization: { iterations: 150 }
                    },
                    interaction: {
                        hover: true,
                        tooltipDelay: 100,
                        navigationButtons: true,
                        keyboard: true,
                        zoomView: true,
                        dragView: true,
                        multiselect: false
                    },
                    nodes: {
                        shape: 'box',
                        borderWidth: 2,
                        shapeProperties: {
                            borderRadius: 6
                        },
                        font: {
                            color: '#ffffff',
                            size: 12,
                            face: 'Kumbh Sans, system-ui, sans-serif'
                        }
                    },
                    edges: {
                        smooth: isHierarchical ? {
                            type: 'cubicBezier',
                            forceDirection: currentMapLayout === 'horizontal' ? 'horizontal' : 'vertical',
                            roundness: 0.5
                        } : {
                            type: 'continuous',
                            roundness: 0.4
                        },
                        arrows: {
                            to: {
                                enabled: true,
                                scaleFactor: 1.2
                            }
                        },
                        font: {
                            align: 'middle',
                            size: 11,
                            color: '#1a202c',
                            background: '#ffffff',
                            strokeWidth: 1,
                            strokeColor: '#cbd5e0'
                        }
                    }
                };

                networkGraph = new vis.Network(container, data, options);

                networkGraph.on('doubleClick', function(params) {
                    if (params.nodes.length > 0) {
                        const nodeId = params.nodes[0];
                        if (typeof nodeId === 'number' && nodeId > 0) {
                            viewCiDetail(nodeId);
                        } else if (nodeId < 0) {
                            // Nodo de servicio de negocio
                            const svcId = Math.abs(nodeId);
                            toastr.info(`Nodo raíz: Servicio de Negocio #${svcId}`);
                        }
                    }
                });

                // Auto ajustar y congelar la física para mantener la cuadrícula gerencial fija
                networkGraph.once('stabilizationIterationsDone', function() {
                    if (isHierarchical) {
                        networkGraph.setOptions({ physics: { enabled: false } });
                    }
                    networkGraph.fit({ animation: { duration: 600, easingFunction: 'easeInOutQuad' } });
                });
            } else {
                $(container).html(`<div class="alert alert-danger m-3">Error al cargar datos del mapa: ${escapeHtml(res.error || 'Desconocido')}</div>`);
            }
        }).fail(function(xhr) {
            $(container).html(`<div class="alert alert-danger m-3">Error de conexión al obtener la topología (HTTP ${xhr.status})</div>`);
        });
    }

    function toggleMapLayout(layout) {
        currentMapLayout = layout;
        $('#btn-layout-hierarchical').toggleClass('btn-primary', layout === 'hierarchical').toggleClass('btn-outline-primary', layout !== 'hierarchical');
        $('#btn-layout-horizontal').toggleClass('btn-primary', layout === 'horizontal').toggleClass('btn-outline-primary', layout !== 'horizontal');
        $('#btn-layout-organic').toggleClass('btn-primary', layout === 'organic').toggleClass('btn-outline-primary', layout !== 'organic');
        initTopologyGraph();
    }

    function fitNetworkGraph() {
        if (networkGraph) networkGraph.fit({ animation: { duration: 500, easingFunction: 'easeInOutQuad' } });
    }

    function zoomInNetworkGraph() {
        if (!networkGraph) return;
        const scale = networkGraph.getScale();
        networkGraph.moveTo({ scale: scale * 1.3, animation: { duration: 300, easingFunction: 'easeInOutQuad' } });
    }

    function zoomOutNetworkGraph() {
        if (!networkGraph) return;
        const scale = networkGraph.getScale();
        networkGraph.moveTo({ scale: scale * 0.75, animation: { duration: 300, easingFunction: 'easeInOutQuad' } });
    }

    // ====================================================================
    // ANÁLISIS DE IMPACTO DE FALLAS
    // ====================================================================
    function openImpactSimulatorModal(ciId = null) {
        if (ciId) {
            $('#select-impact-ci').val(ciId);
            executeImpactAnalysis();
        }
        $('#modal-impact-simulator').modal('show');
    }

    function runImpactFromDetail() {
        const ciId = $('#detail-modal-body').data('ci-id');
        $('#modal-ci-detail').modal('hide');
        openImpactSimulatorModal(ciId);
    }

    function executeImpactAnalysis() {
        const ciId = $('#select-impact-ci').val();
        if (!ciId) return;

        $.getJSON(API_URL, { action: 'impact_analysis', ci_id: ciId }, function(res) {
            if (res.success) {
                $('#impact-results-container').slideDown(200);
                $('#impact-max-level').text(res.max_impact);
                $('#impact-total-count').text(`${res.total_impacted_cis} CIs dependientes`);

                // Servicios afectados
                const svcList = $('#impact-services-list');
                svcList.empty();
                if (res.affected_services.length === 0) {
                    svcList.append('<li class="list-group-item text-muted small">No hay servicios directamente vinculados.</li>');
                } else {
                    res.affected_services.forEach(s => {
                        svcList.append(`
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                                <div><b>${escapeHtml(s.nombre)}</b></div>
                                <span class="badge badge-criticidad-${escapeHtml(s.criticidad.toLowerCase())}">${escapeHtml(s.criticidad)}</span>
                            </li>
                        `);
                    });
                }

                // CIs afectados en cascada
                const tbody = $('#impact-cis-table-body');
                tbody.empty();
                if (res.impacted_cis.length === 0) {
                    tbody.html('<tr><td colspan="5" class="text-center text-muted py-2">Ningún otro CI depende directamente de este componente.</td></tr>');
                } else {
                    res.impacted_cis.forEach(c => {
                        tbody.append(`
                            <tr>
                                <td class="font-weight-bold text-primary">${escapeHtml(c.id_ci)}</td>
                                <td class="font-weight-bold">${escapeHtml(c.hostname_nombre)}</td>
                                <td>${escapeHtml(c.tipo_ci)}</td>
                                <td><span class="badge badge-light border">${escapeHtml(c.relationship_type)}</span></td>
                                <td><span class="badge badge-danger">${escapeHtml(c.impacto_falla)}</span></td>
                            </tr>
                        `);
                    });
                }
            }
        });
    }

    // ====================================================================
    // MODAL CREAR / EDITAR CI CON CÁLCULO EN VIVO
    // ====================================================================
    function openNewCiModal() {
        $('#form-ci')[0].reset();
        $('#ci-id').val('');
        $('#modal-ci-title').html('<i class="fas fa-cube mr-2"></i> Registrar Nuevo Elemento de Configuración (CI)');
        $('#ciform-cat1-tab').tab('show');
        $('#ci-dias_fin_soporte_calc').val('');
        $('#ci-support-live-badge').html('<i class="fas fa-calculator"></i>');
        $('#ciform-existing-images').html('<div class="col-12 text-center text-muted py-3"><span class="small"><i class="fas fa-info-circle mr-1"></i> Guarde primero el CI para poder adjuntarle fotos y evidencias.</span></div>');
        $('#ciform-img-file').val('');
        $('#ciform-img-tags').val('');
        $('#ciform-img-obs').val('');
        $('#ciform-img-preview-box').hide();
        $('#ciform-img-preview').attr('src', '');
        $('#modal-ci-form').modal('show');
    }

    function editCi(id) {
        $.getJSON(API_URL, { action: 'get_ci', id: id }, function(res) {
            if (res.success) {
                const ci = res.ci;
                $('#ci-id').val(ci.id);
                $('#ci-id_ci').val(ci.id_ci);
                $('#ci-hostname_nombre').val(ci.hostname_nombre);
                $('#ci-tipo_ci').val(ci.tipo_ci);
                $('#ci-fabricante').val(ci.fabricante);
                $('#ci-modelo').val(ci.modelo);
                $('#ci-numero_serie').val(ci.numero_serie);
                $('#ci-version_firmware_so').val(ci.version_firmware_so);

                $('#ci-cliente').val(ci.cliente);
                $('#ci-servicio').val(ci.servicio);
                $('#ci-service_id').val(ci.service_id || '');
                $('#ci-responsable_cliente').val(ci.responsable_cliente);
                $('#ci-contrato_proyecto').val(ci.contrato_proyecto);
                $('#ci-propietario_tecnico').val(ci.propietario_tecnico);

                $('#ci-sede_site').val(ci.sede_site);
                $('#ci-pais').val(ci.pais);
                $('#ci-ciudad').val(ci.ciudad);
                $('#ci-rack').val(ci.rack);

                $('#ci-ip_administracion').val(ci.ip_administracion);
                $('#ci-ambiente').val(ci.ambiente);
                $('#ci-estado_ci').val(ci.estado_ci);
                $('#ci-criticidad').val(ci.criticidad);
                $('#ci-monitoreado').val(ci.monitoreado);

                $('#ci-inicio_soporte').val(ci.inicio_soporte);
                $('#ci-fin_soporte').val(ci.fin_soporte);
                $('#ci-garantia_hasta').val(ci.garantia_hasta);
                $('#ci-licencia').val(ci.licencia);
                $('#ci-fin_licencia').val(ci.fin_licencia);
                $('#ci-fecha_eol').val(ci.fecha_eol);
                $('#ci-fecha_eos').val(ci.fecha_eos);
                $('#ci-notas_adicionales').val(ci.notas_adicionales);

                calculateLiveSupportDays();
                loadCiImagesInForm(ci.id);
                $('#ciform-img-file').val('');
                $('#ciform-img-tags').val('');
                $('#ciform-img-obs').val('');
                $('#ciform-img-preview-box').hide();
                $('#ciform-img-preview').attr('src', '');

                $('#modal-ci-title').html(`<i class="fas fa-edit mr-2"></i> Editar CI: ${escapeHtml(ci.hostname_nombre)}`);
                $('#ciform-cat1-tab').tab('show');
                $('#modal-ci-form').modal('show');
            }
        });
    }

    function calculateLiveSupportDays() {
        const endDateStr = $('#ci-fin_soporte').val();
        if (!endDateStr) {
            $('#ci-dias_fin_soporte_calc').val('');
            $('#ci-support-live-badge').html('<i class="fas fa-calculator"></i>');
            return;
        }

        const endDate = new Date(endDateStr);
        const today = new Date();
        // Reset hours for accurate diff
        endDate.setHours(0,0,0,0);
        today.setHours(0,0,0,0);

        const diffTime = endDate - today;
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

        let badgeHtml = '';
        if (diffDays < 0) {
            badgeHtml = `<span class="badge badge-alarm-titilando"><span class="beacon-alarm-titilando"></span><i class="fas fa-bell mr-1"></i> ${Math.abs(diffDays)} días vencido</span>`;
        } else if (diffDays <= 30) {
            badgeHtml = `<span class="badge badge-warning">${diffDays} días (Por vencer)</span>`;
        } else {
            badgeHtml = `<span class="badge badge-success">${diffDays} días (Vigente)</span>`;
        }

        $('#ci-dias_fin_soporte_calc').val(diffDays + ' días');
        $('#ci-support-live-badge').html(badgeHtml);
    }

    function saveCi(e) {
        e.preventDefault();
        const formEl = document.getElementById('form-ci');
        const formData = new FormData(formEl);

        const submitBtn = $(formEl).find('button[type="submit"]');
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Guardando...');

        $.ajax({
            url: API_URL + '?action=save_ci',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json',
            success: function(res) {
                submitBtn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Guardar Elemento de Configuración');
                if (res.success) {
                    toastr.success(res.message);
                    $('#modal-ci-form').modal('hide');
                    loadCIs();
                    loadAllImages();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Validación de Campos',
                        text: res.error || 'Error al guardar CI'
                    });
                }
            },
            error: function(xhr) {
                submitBtn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Guardar Elemento de Configuración');
                const err = xhr.responseJSON ? xhr.responseJSON.error : 'Error del servidor';
                Swal.fire({
                    icon: 'error',
                    title: 'Error de Validación',
                    text: err
                });
            }
        });
    }

    function deleteCi(id, hostname) {
        Swal.fire({
            title: `¿Eliminar CI '${hostname}'?`,
            text: 'Esta acción removerá el elemento de configuración y sus dependencias de la CMDB.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post(API_URL + '?action=delete_ci', { id: id }, function(res) {
                    if (res.success) {
                        toastr.success(res.message);
                        loadCIs();
                    } else {
                        toastr.error(res.error || 'Error al eliminar');
                    }
                }, 'json');
            }
        });
    }

    // ====================================================================
    // FICHA TÉCNICA 360° (DETALLE DEL CI)
    // ====================================================================
    function viewCiDetail(id) {
        $.getJSON(API_URL, { action: 'get_ci', id: id }, function(res) {
            if (res.success) {
                const ci = res.ci;
                $('#detail-ci-hostname').text(ci.hostname_nombre);
                $('#detail-modal-body').data('ci-id', ci.id);

                let outDeps = '';
                if (ci.outgoing_relations.length === 0) {
                    outDeps = '<span class="text-muted small">Sin dependencias directas</span>';
                } else {
                    outDeps = ci.outgoing_relations.map(r => `
                        <div class="small p-1 border-bottom d-flex justify-content-between align-items-center">
                            <span><b>${escapeHtml(r.target_hostname)}</b> <span class="badge badge-light border">${r.relationship_type}</span></span>
                            <span class="badge badge-danger">${r.impacto_falla}</span>
                        </div>
                    `).join('');
                }

                let inDeps = '';
                if (ci.incoming_relations.length === 0) {
                    inDeps = '<span class="text-muted small">Ningún CI depende de este nodo</span>';
                } else {
                    inDeps = ci.incoming_relations.map(r => `
                        <div class="small p-1 border-bottom d-flex justify-content-between align-items-center">
                            <span><b>${escapeHtml(r.source_hostname)}</b> <span class="badge badge-light border">${r.relationship_type}</span></span>
                            <span class="badge badge-danger">${r.impacto_falla}</span>
                        </div>
                    `).join('');
                }

                $('#detail-modal-body').html(`
                    <div class="row">
                        <!-- Columna 1: Identificación y Negocio -->
                        <div class="col-lg-6 mb-3">
                            <div class="card bg-light border p-3 h-100">
                                <h6 class="font-weight-bold text-primary border-bottom pb-2 mb-2">
                                    <i class="fas fa-fingerprint mr-1"></i> Identificación & Características Técnicas
                                </h6>
                                <table class="table table-sm table-borderless small mb-3">
                                    <tr><td class="text-muted" style="width: 40%">ID_CI:</td><td class="font-weight-bold">${escapeHtml(ci.id_ci)}</td></tr>
                                    <tr><td class="text-muted">Hostname:</td><td class="font-weight-bold">${escapeHtml(ci.hostname_nombre)}</td></tr>
                                    <tr><td class="text-muted">Tipo CI:</td><td><span class="badge badge-light border">${escapeHtml(ci.tipo_ci)}</span></td></tr>
                                    <tr><td class="text-muted">Fabricante:</td><td>${escapeHtml(ci.fabricante || '--')}</td></tr>
                                    <tr><td class="text-muted">Modelo:</td><td>${escapeHtml(ci.modelo || '--')}</td></tr>
                                    <tr><td class="text-muted">Número de Serie:</td><td><code>${escapeHtml(ci.numero_serie || '--')}</code></td></tr>
                                    <tr><td class="text-muted">Versión SO / FW:</td><td>${escapeHtml(ci.version_firmware_so || '--')}</td></tr>
                                </table>

                                <h6 class="font-weight-bold text-primary border-bottom pb-2 mb-2">
                                    <i class="fas fa-building mr-1"></i> Negocio, Organización & Gobierno
                                </h6>
                                <table class="table table-sm table-borderless small mb-0">
                                    <tr><td class="text-muted" style="width: 40%">Cliente:</td><td class="font-weight-bold">${escapeHtml(ci.cliente)}</td></tr>
                                    <tr><td class="text-muted">Servicio Sustentado:</td><td class="font-weight-bold text-dark">${escapeHtml(ci.servicio)}</td></tr>
                                    <tr><td class="text-muted">Servicio Crítico:</td><td>${escapeHtml(ci.service_name || 'No vinculado')}</td></tr>
                                    <tr><td class="text-muted">Responsable Cliente:</td><td>${escapeHtml(ci.responsable_cliente || '--')}</td></tr>
                                    <tr><td class="text-muted">Contrato / Proyecto:</td><td>${escapeHtml(ci.contrato_proyecto || '--')}</td></tr>
                                    <tr><td class="text-muted">Propietario Técnico:</td><td>${escapeHtml(ci.propietario_tecnico || '--')}</td></tr>
                                </table>
                            </div>
                        </div>

                        <!-- Columna 2: Ubicación, Operaciones y Soporte -->
                        <div class="col-lg-6 mb-3">
                            <div class="card bg-light border p-3 h-100">
                                <h6 class="font-weight-bold text-primary border-bottom pb-2 mb-2">
                                    <i class="fas fa-map-marker-alt mr-1"></i> Ubicación & Operaciones
                                </h6>
                                <table class="table table-sm table-borderless small mb-3">
                                    <tr><td class="text-muted" style="width: 40%">Sede / Site:</td><td class="font-weight-bold">${escapeHtml(ci.sede_site)}</td></tr>
                                    <tr><td class="text-muted">País / Ciudad:</td><td>${escapeHtml(ci.pais || '--')} / ${escapeHtml(ci.ciudad || '--')}</td></tr>
                                    <tr><td class="text-muted">Rack / Posición:</td><td>${escapeHtml(ci.rack || '--')}</td></tr>
                                    <tr><td class="text-muted">IP Administración:</td><td><code>${escapeHtml(ci.ip_administracion)}</code></td></tr>
                                    <tr><td class="text-muted">Ambiente:</td><td><span class="badge badge-secondary">${escapeHtml(ci.ambiente)}</span></td></tr>
                                    <tr><td class="text-muted">Estado Operativo:</td><td><span class="badge badge-success">${escapeHtml(ci.estado_ci)}</span></td></tr>
                                    <tr><td class="text-muted">Criticidad:</td><td><span class="badge badge-criticidad-${escapeHtml(ci.criticidad.toLowerCase())}">${escapeHtml(ci.criticidad)}</span></td></tr>
                                    <tr><td class="text-muted">Monitoreado:</td><td><span class="badge badge-${ci.monitoreado === 'Sí' ? 'success' : 'secondary'}">${escapeHtml(ci.monitoreado)}</span></td></tr>
                                </table>

                                <h6 class="font-weight-bold text-primary border-bottom pb-2 mb-2">
                                    <i class="fas fa-calendar-alt mr-1"></i> Ciclo de Vida & Soporte
                                </h6>
                                <table class="table table-sm table-borderless small mb-0">
                                    <tr><td class="text-muted" style="width: 40%">Vigencia Soporte:</td><td>${escapeHtml(ci.inicio_soporte)} &rarr; ${escapeHtml(ci.fin_soporte)}</td></tr>
                                    <tr><td class="text-muted">Días Restantes:</td><td>${ci.dias_fin_soporte !== null && parseInt(ci.dias_fin_soporte) < 0 ? `<span class="badge badge-alarm-titilando"><span class="beacon-alarm-titilando"></span><i class="fas fa-bell mr-1"></i> ${ci.dias_fin_soporte} días (VENCIDO)</span>` : `<span class="badge badge-${ci.dias_fin_soporte <= 30 ? 'warning' : 'success'}">${ci.dias_fin_soporte} días</span>`}</td></tr>
                                    <tr><td class="text-muted">Garantía Hasta:</td><td>${escapeHtml(ci.garantia_hasta || '--')}</td></tr>
                                    <tr><td class="text-muted">Licencia:</td><td>${escapeHtml(ci.licencia || '--')} (Fin: ${escapeHtml(ci.fin_licencia || '--')})</td></tr>
                                    <tr><td class="text-muted">Fechas EOL / EOS:</td><td>EOL: ${escapeHtml(ci.fecha_eol || '--')} | EOS: ${escapeHtml(ci.fecha_eos || '--')}</td></tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Mapeo de dependencias de este CI -->
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <div class="p-3 border rounded bg-white">
                                <h6 class="font-weight-bold text-info mb-2"><i class="fas fa-arrow-circle-right mr-1"></i> Este CI Depende de (Salientes):</h6>
                                ${outDeps}
                            </div>
                        </div>
                        <div class="col-md-6 mb-2">
                            <div class="p-3 border rounded bg-white">
                                <h6 class="font-weight-bold text-danger mb-2"><i class="fas fa-arrow-circle-left mr-1"></i> CIs que Dependen de este CI (Entrantes):</h6>
                                ${inDeps}
                            </div>
                        </div>
                    </div>

                    <!-- Evidencias Fotográficas de este CI -->
                    <div class="row mt-2">
                        <div class="col-12">
                            <div class="p-3 border rounded bg-white">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="font-weight-bold text-dark mb-0">
                                        <i class="fas fa-camera text-info mr-1"></i> Evidencias Fotográficas & Ubicación Física
                                    </h6>
                                    <button class="btn btn-xs btn-info font-weight-bold" onclick="openUploadImageModal(${ci.id})">
                                        <i class="fas fa-upload mr-1"></i> Subir Foto
                                    </button>
                                </div>
                                <div class="row" id="detail-ci-images-container">
                                    <div class="col-12 text-center text-muted py-2 small">Cargando fotos...</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Trazabilidad & Historial de Cambios de este CI -->
                    <div class="row mt-2">
                        <div class="col-12">
                            <div class="p-3 border rounded bg-white d-flex flex-wrap justify-content-between align-items-center" style="border-left: 4px solid #ffc107 !important;">
                                <div>
                                    <h6 class="font-weight-bold text-dark mb-1">
                                        <i class="fas fa-history text-warning mr-2"></i> Trazabilidad & Bitácora de Cambios de este CI
                                    </h6>
                                    <p class="text-muted small mb-0">Consulte el registro histórico detallado de altas, modificaciones con diff (valores anteriores vs nuevos) y bajas registradas para este activo.</p>
                                </div>
                                <div class="mt-2 mt-md-0">
                                    <button type="button" class="btn btn-sm btn-warning font-weight-bold text-dark shadow-sm" onclick="$('#modal-ci-detail').modal('hide'); filterAuditByCi(${ci.id}, '${escapeHtml(ci.id_ci)}', '${escapeHtml(ci.hostname_nombre)}');">
                                        <i class="fas fa-search mr-1"></i> Ver Historial de este CI
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `);

                $('#modal-ci-detail').modal('show');
                loadCiImagesInDetail(ci.id);
            }
        });
    }

    function editFromDetail() {
        const ciId = $('#detail-modal-body').data('ci-id');
        $('#modal-ci-detail').modal('hide');
        editCi(ciId);
    }

    function viewHistoryFromDetail() {
        const ciId = $('#detail-modal-body').data('ci-id');
        const hostname = $('#detail-ci-hostname').text();
        $('#modal-ci-detail').modal('hide');
        filterAuditByCi(ciId, '', hostname);
    }

    // ====================================================================
    // AUDITORÍA DE INTEGRIDAD (FASE 1 VS FASE 2)
    // ====================================================================
    function loadAuditData() {
        $.getJSON(API_URL, { action: 'audit_integrity' }, function(res) {
            if (res.success) {
                $('#audit-compliance-pct').text(res.compliance_percentage + '%');
                $('#audit-progress-bar').css('width', res.compliance_percentage + '%');
                $('#audit-compliance-summary').text(`${res.phase1_compliant_count} de ${res.total_cis} CIs cumplen con el 100% de campos de Fase 1.`);

                // Badges de campos faltantes
                const badgesDiv = $('#audit-missing-fields-badges');
                badgesDiv.empty();

                const labels = {
                    'id_ci': 'ID_CI', 'hostname_nombre': 'Hostname', 'tipo_ci': 'Tipo CI',
                    'cliente': 'Cliente', 'servicio': 'Servicio', 'sede_site': 'Sede Site',
                    'ip_administracion': 'IP Admin', 'ambiente': 'Ambiente', 'estado_ci': 'Estado CI',
                    'criticidad': 'Criticidad', 'monitoreado': 'Monitoreo', 'inicio_soporte': 'Inicio Soporte',
                    'fin_soporte': 'Fin Soporte', 'numero_serie': 'Nro Serie (F2)', 'fabricante': 'Fabricante (F2)',
                    'rack': 'Rack (F2)', 'licencia': 'Licencia (F2)'
                };

                for (let key in labels) {
                    const count = res.missing_field_stats[key] || 0;
                    const badgeClass = count === 0 ? 'badge-light border' : (key.includes('F2') ? 'badge-secondary' : 'badge-danger');
                    badgesDiv.append(`
                        <div class="col-md-3 col-6 mb-2">
                            <span class="badge ${badgeClass} p-2 btn-block text-left">
                                <span class="badge badge-pill badge-dark mr-1">${count}</span> ${labels[key]}
                            </span>
                        </div>
                    `);
                }

                // Tabla de CIs incompletos
                const tbody = $('#table-audit-incomplete-body');
                tbody.empty();

                if (res.incomplete_cis.length === 0) {
                    tbody.html('<tr><td colspan="6" class="text-center text-success py-3 font-weight-bold"><i class="fas fa-check-circle mr-1"></i> ¡Excelente! Todos los CIs cumplen al 100% con los campos obligatorios de Fase 1.</td></tr>');
                } else {
                    res.incomplete_cis.forEach(c => {
                        const tr = $(`
                            <tr>
                                <td class="font-weight-bold text-primary">${escapeHtml(c.id_ci)}</td>
                                <td class="font-weight-bold">${escapeHtml(c.hostname_nombre)}</td>
                                <td>${escapeHtml(c.tipo_ci)}</td>
                                <td>${escapeHtml(c.cliente)}</td>
                                <td><span class="badge badge-danger">${escapeHtml(c.missing_fields.join(', '))}</span></td>
                                <td class="text-center">
                                    <button class="btn btn-xs btn-primary font-weight-bold" onclick="editCi(${c.id})">
                                        <i class="fas fa-tools mr-1"></i> Completar
                                    </button>
                                </td>
                            </tr>
                        `);
                        tbody.append(tr);
                    });
                }
            }
        });
    }

    // ====================================================================
    // RECÁLCULO AUTOMÁTICO DE DÍAS DE SOPORTE
    // ====================================================================
    function recalculateSupportNow() {
        Swal.fire({
            title: 'Recalculando días de soporte...',
            text: 'Ejecutando consulta SQL UPDATE cmdb_sonda_cis SET dias_fin_soporte = DATEDIFF(...)',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        $.post(API_URL + '?action=recalculate_support', {}, function(res) {
            Swal.close();
            if (res.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Recálculo Exitoso',
                    html: `
                        <p>${res.message}</p>
                        <div class="text-left small bg-light p-3 rounded">
                            <b>Filas actualizadas:</b> ${res.rows_affected}<br>
                            <b>CIs con soporte vencido:</b> <span class="text-danger font-weight-bold">${res.stats.expired || 0}</span><br>
                            <b>CIs por vencer (≤30d):</b> <span class="text-warning font-weight-bold">${res.stats.due_soon || 0}</span><br>
                            <b>CIs vigentes (>30d):</b> <span class="text-success font-weight-bold">${res.stats.active || 0}</span>
                        </div>
                    `
                });
                loadCIs();
            } else {
                toastr.error(res.error || 'Error en el recálculo');
            }
        }, 'json');
    }

    // ====================================================================
    // SERVICIOS Y RELACIONES
    // ====================================================================
    function openNewServiceModal() {
        $('#form-service')[0].reset();
        $('#service-id').val('');
        $('#modal-service-form').modal('show');
    }

    function saveService(e) {
        e.preventDefault();
        const data = $('#form-service').serialize();

        $.post(API_URL + '?action=service_save', data, function(res) {
            if (res.success) {
                toastr.success(res.message);
                $('#modal-service-form').modal('hide');
                loadServices();
                loadCIs();
            } else {
                toastr.error(res.error || 'Error al guardar servicio');
            }
        }, 'json');
    }

    function openNewRelationshipModal() {
        $('#form-relationship')[0].reset();
        $('#modal-relationship-form').modal('show');
    }

    function saveRelationship(e) {
        e.preventDefault();
        const data = $('#form-relationship').serialize();

        $.post(API_URL + '?action=relationship_save', data, function(res) {
            if (res.success) {
                toastr.success(res.message);
                $('#modal-relationship-form').modal('hide');
                loadCIs();
                initTopologyGraph();
            } else {
                toastr.error(res.error || 'Error al vincular dependencias');
            }
        }, 'json');
    }

    function exportCsv() {
        window.location.href = API_URL + '?action=export_csv';
    }

    function copyToClipboard(elementId) {
        const text = document.getElementById(elementId).innerText;
        navigator.clipboard.writeText(text).then(() => {
            toastr.info('Consulta SQL copiada al portapapeles');
        });
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // ====================================================================
    // GESTIÓN DE EVIDENCIAS FOTOGRÁFICAS (6TA CATEGORÍA FUNCIONAL)
    // ====================================================================
    let globalImagesList = [];

    function debounceLoadImages() {
        clearTimeout(debounceImgTimer);
        debounceImgTimer = setTimeout(() => {
            loadAllImages();
        }, 350);
    }

    function loadAllImages() {
        const ciId = $('#filter-img-ci').val() || '';
        const ubicacion = $('#filter-img-ubicacion').val() || '';
        const tag = $('#filter-img-tag').val() || '';

        const container = $('#images-gallery-container');
        container.html(`
            <div class="col-12 text-center text-muted py-5">
                <i class="fas fa-spinner fa-spin fa-2x mb-2 text-primary"></i>
                <p>Cargando evidencias fotográficas...</p>
            </div>
        `);

        $.getJSON(API_URL, {
            action: 'list_images',
            ci_id: ciId,
            ubicacion: ubicacion,
            tag: tag
        }, function(res) {
            if (res.success) {
                globalImagesList = res.images;
                renderImagesGallery(res.images);
            } else {
                container.html(`<div class="col-12 text-center text-danger py-4">${escapeHtml(res.error || 'Error al cargar fotos')}</div>`);
            }
        }).fail(function() {
            container.html('<div class="col-12 text-center text-danger py-4">Error de comunicación con el servidor.</div>');
        });
    }

    function renderImagesGallery(images) {
        const container = $('#images-gallery-container');
        container.empty();

        if (!images || images.length === 0) {
            container.html(`
                <div class="col-12 text-center text-muted py-5">
                    <i class="fas fa-camera-retro fa-3x mb-3 text-muted"></i>
                    <h5>No se encontraron evidencias fotográficas</h5>
                    <p class="small">Suba fotos de los racks, frontales, cableado o placas de seriales asociadas a los CIs.</p>
                    <button class="btn btn-sm btn-info font-weight-bold" onclick="openUploadImageModal()">
                        <i class="fas fa-upload mr-1"></i> Subir Primera Evidencia
                    </button>
                </div>
            `);
            return;
        }

        images.forEach(img => {
            let tagsHtml = '';
            if (img.tags_array && img.tags_array.length > 0) {
                tagsHtml = img.tags_array.map(t => {
                    const clean = t.startsWith('#') ? t : '#' + t;
                    return `<span class="badge badge-secondary mr-1 mb-1" style="font-size: 0.72rem;">${escapeHtml(clean)}</span>`;
                }).join('');
            }

            const card = $(`
                <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
                    <div class="card h-100 shadow-sm border" style="border-radius: 12px; overflow: hidden; transition: transform 0.2s, box-shadow 0.2s;">
                        <div style="position: relative; height: 190px; background: #212529; cursor: pointer; overflow: hidden;" onclick="viewFullImage(${img.id})">
                            <img src="${escapeHtml(img.url)}" alt="${escapeHtml(img.file_name)}" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                            <span class="badge badge-dark" style="position: absolute; top: 8px; left: 8px; background: rgba(0,0,0,0.7); backdrop-filter: blur(4px);">
                                <i class="fas fa-map-marker-alt text-warning mr-1"></i> ${escapeHtml(img.ubicacion_foto)}
                            </span>
                            <span class="badge badge-info" style="position: absolute; bottom: 8px; right: 8px; font-size: 0.7rem; background: rgba(0, 82, 204, 0.85);">
                                <i class="fas fa-calendar mr-1"></i> ${escapeHtml(img.fecha_creacion_foto || '')}
                            </span>
                        </div>
                        <div class="card-body p-3 d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <h6 class="font-weight-bold text-dark mb-0 text-truncate" title="${escapeHtml(img.hostname_nombre)}">
                                        <a href="javascript:void(0)" onclick="viewCiDetail(${img.ci_id})">${escapeHtml(img.hostname_nombre)}</a>
                                    </h6>
                                    <span class="badge badge-light border text-muted small">${escapeHtml(img.id_ci)}</span>
                                </div>
                                <div class="small text-muted mb-2">
                                    <i class="fas fa-building mr-1"></i> ${escapeHtml(img.cliente)} &bull; 
                                    <i class="fas fa-server mr-1"></i> ${escapeHtml(img.tipo_ci)}
                                </div>
                                ${tagsHtml ? `<div class="mb-2">${tagsHtml}</div>` : ''}
                                ${img.observaciones ? `<p class="small text-secondary mb-2" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;" title="${escapeHtml(img.observaciones)}"><i class="fas fa-comment-alt mr-1 text-muted"></i> ${escapeHtml(img.observaciones)}</p>` : ''}
                            </div>
                            <div class="pt-2 border-top d-flex justify-content-between align-items-center">
                                <span class="small text-muted" title="${escapeHtml(img.file_name)}">
                                    <i class="fas fa-paperclip mr-1"></i> ${(img.file_size / 1024).toFixed(1)} KB
                                </span>
                                <div>
                                    <button class="btn btn-xs btn-outline-info mr-1" title="Ver en Pantalla Completa" onclick="viewFullImage(${img.id})">
                                        <i class="fas fa-expand"></i>
                                    </button>
                                    <button class="btn btn-xs btn-outline-danger" title="Eliminar Imagen" onclick="deleteImage(${img.id})">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `);
            container.append(card);
        });
    }

    function openUploadImageModal(ciId = null) {
        $('#form-upload-image')[0].reset();
        $('#upload-img-preview-box').hide();
        $('#upload-img-preview').attr('src', '');
        $('#upload-img-fecha').val(new Date().toISOString().split('T')[0]);

        if (ciId) {
            $('#upload-img-ci').val(ciId);
        } else {
            const activeFilterCi = $('#filter-img-ci').val();
            if (activeFilterCi) {
                $('#upload-img-ci').val(activeFilterCi);
            }
        }
        $('#modal-upload-image').modal('show');
    }

    function previewUploadImage(event) {
        const input = event.target;
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#upload-img-preview').attr('src', e.target.result);
                $('#upload-img-preview-box').fadeIn();
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function submitUploadImage(e) {
        e.preventDefault();
        const form = document.getElementById('form-upload-image');
        const formData = new FormData(form);

        const btn = $('#btn-submit-upload-image');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Subiendo...');

        $.ajax({
            url: API_URL + '?action=upload_image',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json',
            success: function(res) {
                btn.prop('disabled', false).html('<i class="fas fa-upload mr-1"></i> Subir y Registrar Imagen');
                if (res.success) {
                    toastr.success(res.message);
                    $('#modal-upload-image').modal('hide');
                    loadAllImages();

                    // Si la modal del CI está abierta y coincide el CI, refrescar
                    const currentCiId = $('#ci-id').val();
                    if (currentCiId && currentCiId == formData.get('ci_id')) {
                        loadCiImagesInForm(currentCiId);
                    }
                    // Si el detalle del CI está abierto
                    const detailCiId = $('#detail-modal-body').data('ci-id');
                    if (detailCiId && detailCiId == formData.get('ci_id')) {
                        loadCiImagesInDetail(detailCiId);
                    }
                } else {
                    toastr.error(res.error || 'Error al subir imagen');
                }
            },
            error: function(xhr) {
                btn.prop('disabled', false).html('<i class="fas fa-upload mr-1"></i> Subir y Registrar Imagen');
                const err = xhr.responseJSON ? xhr.responseJSON.error : 'Error del servidor al procesar la imagen.';
                toastr.error(err);
            }
        });
    }

    function addTagToCiform(tag) {
        const input = $('#ciform-img-tags');
        let val = input.val().trim();
        if (val) {
            const tags = val.split(',').map(t => t.trim());
            if (!tags.includes(tag)) {
                input.val(val + ', ' + tag);
            }
        } else {
            input.val(tag);
        }
    }

    function previewCiformImage(event) {
        const input = event.target;
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#ciform-img-preview').attr('src', e.target.result);
                $('#ciform-img-preview-box').fadeIn();
            }
            reader.readAsDataURL(input.files[0]);
        } else {
            $('#ciform-img-preview-box').hide();
        }
    }

    // Subir desde la pestaña 6 del modal de CI
    function uploadImageFromForm() {
        const ciId = $('#ci-id').val();
        const fileInput = document.getElementById('ciform-img-file');

        if (!fileInput.files || fileInput.files.length === 0) {
            toastr.warning('Por favor seleccione un archivo de imagen para subir.');
            return;
        }

        if (!ciId) {
            Swal.fire({
                title: '¿Guardar CI y registrar foto?',
                text: 'El CI aún no está guardado en el sistema. Para almacenar la evidencia en el servidor, guardaremos el CI con los datos del formulario.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#0052cc',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, guardar y registrar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $('#form-ci').submit();
                }
            });
            return;
        }

        const formData = new FormData();
        formData.append('ci_id', ciId);
        formData.append('ubicacion_foto', $('#ciform-img-ubicacion').val());
        formData.append('tags', $('#ciform-img-tags').val());
        formData.append('fecha_creacion_foto', $('#ciform-img-fecha').val());
        formData.append('observaciones', $('#ciform-img-obs').val());
        formData.append('image', fileInput.files[0]);

        const btn = $('#btn-ciform-upload');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Subiendo...');

        $.ajax({
            url: API_URL + '?action=upload_image',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json',
            success: function(res) {
                btn.prop('disabled', false).html('<i class="fas fa-upload mr-1"></i> Subir Imagen Ahora');
                if (res.success) {
                    toastr.success(res.message);
                    $('#ciform-img-file').val('');
                    $('#ciform-img-tags').val('');
                    $('#ciform-img-obs').val('');
                    $('#ciform-img-preview-box').hide();
                    $('#ciform-img-preview').attr('src', '');
                    loadCiImagesInForm(ciId);
                    loadAllImages();
                } else {
                    toastr.error(res.error || 'Error al subir imagen');
                }
            },
            error: function(xhr) {
                btn.prop('disabled', false).html('<i class="fas fa-upload mr-1"></i> Subir Imagen Ahora');
                const err = xhr.responseJSON ? xhr.responseJSON.error : 'Error del servidor al procesar la imagen.';
                toastr.error(err);
            }
        });
    }

    function loadCiImagesInForm(ciId) {
        const container = $('#ciform-existing-images');
        container.html('<div class="col-12 text-center text-muted py-2 small"><i class="fas fa-spinner fa-spin mr-1"></i> Cargando evidencias...</div>');

        $.getJSON(API_URL, { action: 'list_images', ci_id: ciId }, function(res) {
            container.empty();
            if (res.success && res.images && res.images.length > 0) {
                res.images.forEach(img => {
                    let tagsHtml = '';
                    if (img.tags_array && img.tags_array.length > 0) {
                        tagsHtml = img.tags_array.map(t => `<span class="badge badge-secondary mr-1" style="font-size:0.65rem;">${escapeHtml(t.startsWith('#')?t:'#'+t)}</span>`).join('');
                    }

                    const card = $(`
                        <div class="col-md-4 col-sm-6 mb-3">
                            <div class="card border h-100 shadow-sm" style="border-radius: 8px; overflow: hidden;">
                                <div style="height: 120px; background: #212529; position: relative; cursor: pointer;" onclick="viewFullImage(${img.id})">
                                    <img src="${escapeHtml(img.url)}" style="width: 100%; height: 100%; object-fit: cover;">
                                    <span class="badge badge-dark" style="position: absolute; top: 4px; left: 4px; font-size: 0.65rem;">
                                        ${escapeHtml(img.ubicacion_foto)}
                                    </span>
                                </div>
                                <div class="card-body p-2 d-flex flex-column justify-content-between">
                                    <div>
                                        <div class="small text-muted mb-1"><i class="fas fa-calendar mr-1"></i> ${escapeHtml(img.fecha_creacion_foto || '')}</div>
                                        ${tagsHtml ? `<div class="mb-1">${tagsHtml}</div>` : ''}
                                        ${img.observaciones ? `<div class="small text-dark font-italic text-truncate" title="${escapeHtml(img.observaciones)}">${escapeHtml(img.observaciones)}</div>` : ''}
                                    </div>
                                    <div class="text-right pt-2 border-top mt-1">
                                        <button type="button" class="btn btn-xs btn-outline-info mr-1" onclick="viewFullImage(${img.id})"><i class="fas fa-eye"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-danger" onclick="deleteImage(${img.id}, ${ciId})"><i class="fas fa-trash-alt"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `);
                    container.append(card);
                });
            } else {
                container.html('<div class="col-12 text-center text-muted py-3 small">Este CI aún no cuenta con evidencias fotográficas registradas. Utilice el formulario superior para adjuntar fotos.</div>');
            }
        });
    }

    function loadCiImagesInDetail(ciId) {
        const container = $('#detail-ci-images-container');
        if (!container.length) return;

        $.getJSON(API_URL, { action: 'list_images', ci_id: ciId }, function(res) {
            container.empty();
            if (res.success && res.images && res.images.length > 0) {
                res.images.forEach(img => {
                    const col = $(`
                        <div class="col-md-3 col-sm-6 mb-2">
                            <div class="border rounded p-1 text-center bg-light" style="cursor: pointer;" onclick="viewFullImage(${img.id})">
                                <img src="${escapeHtml(img.url)}" style="height: 90px; width: 100%; object-fit: cover; border-radius: 4px;">
                                <div class="small font-weight-bold text-truncate mt-1">${escapeHtml(img.ubicacion_foto)}</div>
                                <div class="text-muted" style="font-size: 0.7rem;">${escapeHtml(img.fecha_creacion_foto || '')}</div>
                            </div>
                        </div>
                    `);
                    container.append(col);
                });
            } else {
                container.html('<div class="col-12 text-center text-muted py-2 small">Sin evidencias fotográficas registradas para este CI.</div>');
            }
        });
    }

    function deleteImage(imageId, fromFormCiId = null) {
        Swal.fire({
            title: '¿Eliminar evidencia fotográfica?',
            text: 'El archivo físico y su registro en la CMDB serán removidos permanentemente.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post(API_URL + '?action=delete_image', { id: imageId }, function(res) {
                    if (res.success) {
                        toastr.success(res.message);
                        loadAllImages();
                        if (fromFormCiId) {
                            loadCiImagesInForm(fromFormCiId);
                        }
                        const currentCiId = $('#ci-id').val();
                        if (currentCiId) {
                            loadCiImagesInForm(currentCiId);
                        }
                        const detailCiId = $('#detail-modal-body').data('ci-id');
                        if (detailCiId) {
                            loadCiImagesInDetail(detailCiId);
                        }
                    } else {
                        toastr.error(res.error || 'Error al eliminar imagen');
                    }
                }, 'json').fail(function() {
                    toastr.error('Error al procesar la solicitud.');
                });
            }
        });
    }

    function viewFullImage(imageId) {
        let img = globalImagesList.find(i => i.id == imageId);

        const openLightbox = (image) => {
            $('#lightbox-title').html(`<i class="fas fa-image text-info mr-2"></i> ${escapeHtml(image.hostname_nombre)} - ${escapeHtml(image.ubicacion_foto)}`);
            $('#lightbox-img').attr('src', image.url);

            let tagsHtml = '';
            if (image.tags_array && image.tags_array.length > 0) {
                tagsHtml = image.tags_array.map(t => `<span class="badge badge-info mr-1">${escapeHtml(t.startsWith('#')?t:'#'+t)}</span>`).join('');
            }

            $('#lightbox-meta').html(`
                <div class="row small text-white-50">
                    <div class="col-md-6 mb-2">
                        <b class="text-white"><i class="fas fa-server mr-1"></i> CI:</b> ${escapeHtml(image.hostname_nombre)} (<code>${escapeHtml(image.id_ci)}</code>)<br>
                        <b class="text-white"><i class="fas fa-building mr-1"></i> Cliente / Servicio:</b> ${escapeHtml(image.cliente)} &bull; ${escapeHtml(image.servicio)}<br>
                        <b class="text-white"><i class="fas fa-map-marker-alt mr-1"></i> Ubicación / Rack:</b> ${escapeHtml(image.ubicacion_foto)} (Rack: ${escapeHtml(image.rack || 'N/A')})
                    </div>
                    <div class="col-md-6 mb-2">
                        <b class="text-white"><i class="fas fa-calendar mr-1"></i> Fecha de Toma:</b> ${escapeHtml(image.fecha_creacion_foto || 'No especificada')}<br>
                        <b class="text-white"><i class="fas fa-user mr-1"></i> Registrado Por:</b> ${escapeHtml(image.uploaded_by || 'Sistema')}<br>
                        <b class="text-white"><i class="fas fa-file mr-1"></i> Archivo:</b> ${escapeHtml(image.file_name)} (${(image.file_size / 1024).toFixed(1)} KB)
                    </div>
                    <div class="col-12 mt-1">
                        ${tagsHtml ? `<div class="mb-2"><b class="text-white">Tags:</b> ${tagsHtml}</div>` : ''}
                        ${image.observaciones ? `<div class="p-2 rounded" style="background: rgba(255,255,255,0.05);"><b class="text-white"><i class="fas fa-comment mr-1"></i> Observaciones:</b> <span class="text-white">${escapeHtml(image.observaciones)}</span></div>` : ''}
                    </div>
                </div>
            `);

            $('#modal-image-lightbox').modal('show');
        };

        if (img) {
            openLightbox(img);
        } else {
            // Si no está en globalImagesList (ej: se abrió desde detail), buscar por API
            $.getJSON(API_URL, { action: 'list_images' }, function(res) {
                if (res.success) {
                    globalImagesList = res.images;
                    img = globalImagesList.find(i => i.id == imageId);
                    if (img) openLightbox(img);
                }
            });
        }
    }

    function escapeJs(text) {
        if (!text) return '';
        return String(text).replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"');
    }

    // ====================================================================
    // BITÁCORA DE HISTORIAL & AUDITORÍA DE CAMBIOS (360)
    // ====================================================================
    let currentAuditPage = 1;
    let currentAuditCiFilter = null;
    let currentAuditLogsList = [];
    let currentAuditStats = {};
    let lastInspectedAuditJson = '';

    function loadAuditLogs(page = 1) {
        currentAuditPage = page;
        const tbody = $('#audit-table-body');
        tbody.html(`
            <tr>
                <td colspan="8" class="text-center py-5 text-muted">
                    <i class="fas fa-spinner fa-spin fa-2x mb-2 text-primary"></i>
                    <div>Cargando bitácora de auditoría...</div>
                </td>
            </tr>
        `);

        const q = $('#audit-filter-q').val() || '';
        const actionType = $('#audit-filter-action').val() || '';
        const user = $('#audit-filter-user').val() || '';
        const dateFrom = $('#audit-filter-from').val() || '';
        const dateTo = $('#audit-filter-to').val() || '';
        const limit = $('#audit-page-limit').val() || 25;
        const ciId = currentAuditCiFilter ? currentAuditCiFilter.id : '';

        $.getJSON(API_URL, {
            action: 'audit_logs',
            q: q,
            action_type: actionType,
            user: user,
            date_from: dateFrom,
            date_to: dateTo,
            limit: limit,
            page: page,
            ci_id: ciId
        }, function(res) {
            if (res.success) {
                currentAuditLogsList = res.data || [];
                currentAuditStats = res.stats || {};
                updateAuditKPIs(res.stats);
                populateAuditUsersDropdown(res.users);
                renderAuditLogsTable(res);
            } else {
                tbody.html(`<tr><td colspan="8" class="text-center text-danger py-4"><i class="fas fa-exclamation-triangle mr-2"></i>${escapeHtml(res.error || 'Error al cargar bitácora')}</td></tr>`);
                toastr.error(res.error || 'Error al cargar auditoría');
            }
        }).fail(function() {
            tbody.html('<tr><td colspan="8" class="text-center text-danger py-4"><i class="fas fa-plug mr-2"></i>Error de conexión al obtener bitácora de auditoría.</td></tr>');
        });
    }

    function updateAuditKPIs(stats) {
        if (!stats) return;
        $('#audit-kpi-total').text(stats.total_events || 0);
        $('#audit-kpi-creates').text(stats.total_creates || 0);
        $('#audit-kpi-updates').text(stats.total_updates || 0);
        $('#audit-kpi-deletes').text(stats.total_deletes || 0);
        $('#audit-kpi-users').text(stats.unique_users_count || 0);
    }

    function populateAuditUsersDropdown(users) {
        const select = $('#audit-filter-user');
        const currentVal = select.val();
        let options = '<option value="">Todos los Usuarios</option>';
        (users || []).forEach(u => {
            const sel = (u === currentVal) ? 'selected' : '';
            options += `<option value="${escapeHtml(u)}" ${sel}>${escapeHtml(u)}</option>`;
        });
        select.html(options);
    }

    function renderAuditLogsTable(res) {
        const tbody = $('#audit-table-body');
        const items = res.data || [];
        const pag = res.pagination || { total: 0, page: 1, limit: 25, pages: 1 };

        $('#audit-table-count').text(`Mostrando ${items.length} de ${pag.total} registros`);
        $('#audit-pagination-info').text(`Página ${pag.page} de ${pag.pages} (${pag.total} eventos en total)`);

        if (items.length === 0) {
            tbody.html('<tr><td colspan="8" class="text-center py-5 text-muted"><i class="fas fa-inbox fa-2x mb-2 text-muted"></i><div>No se encontraron registros de auditoría con los filtros aplicados.</div></td></tr>');
            $('#audit-pagination-ul').empty();
            return;
        }

        let html = '';
        items.forEach(log => {
            let badgeStyle = 'badge-secondary';
            if (log.action === 'CREATE') badgeStyle = 'badge-audit-create';
            else if (log.action === 'UPDATE') badgeStyle = 'badge-audit-update';
            else if (log.action === 'DELETE') badgeStyle = 'badge-audit-delete';
            else if (log.action.includes('RELATION')) badgeStyle = 'badge-audit-relations';
            else if (log.action.includes('IMAGE')) badgeStyle = 'badge-audit-images';
            else if (log.action.includes('SERVICE')) badgeStyle = 'badge-audit-services';
            else badgeStyle = 'badge-audit-system';

            const formattedDate = log.created_at || '--';
            const displayHost = escapeHtml(log.display_hostname || 'N/A');
            const displayIdCi = escapeHtml(log.display_id_ci || '');
            const user = escapeHtml(log.user_name || 'sistema');
            const ip = escapeHtml(log.ip_address || '127.0.0.1');

            let diffBtn = '';
            if (log.action === 'UPDATE' && log.changes_count > 0) {
                diffBtn = `
                    <button class="btn btn-xs btn-primary font-weight-bold" onclick="inspectAuditLog(${log.id})" title="Ver campos modificados">
                        <i class="fas fa-exchange-alt mr-1"></i> ${log.changes_count} Diff
                    </button>
                `;
            } else {
                diffBtn = `
                    <button class="btn btn-xs btn-outline-info font-weight-bold" onclick="inspectAuditLog(${log.id})" title="Inspeccionar detalle">
                        <i class="fas fa-search mr-1"></i> Detalle
                    </button>
                `;
            }

            let ciBadge = '';
            if (log.ci_id) {
                ciBadge = `<a href="javascript:void(0)" class="font-weight-bold text-primary" onclick="filterAuditByCi(${log.ci_id}, '${escapeJs(displayIdCi)}', '${escapeJs(displayHost)}')">${displayHost}</a><div class="small text-muted font-monospace">${displayIdCi}</div>`;
            } else {
                ciBadge = `<span class="font-weight-bold text-dark">${displayHost}</span>`;
            }

            html += `
                <tr>
                    <td class="text-center font-weight-bold text-muted">#${log.id}</td>
                    <td class="text-nowrap"><small><i class="far fa-clock text-muted mr-1"></i>${formattedDate}</small></td>
                    <td><span class="badge badge-light border text-dark font-weight-bold px-2 py-1"><i class="fas fa-user-circle mr-1 text-secondary"></i>${user}</span></td>
                    <td><span class="${badgeStyle} shadow-sm">${escapeHtml(log.action_label || log.action)}</span></td>
                    <td>${ciBadge}</td>
                    <td><span class="small">${escapeHtml(log.summary)}</span></td>
                    <td class="text-center">${diffBtn}</td>
                    <td><code class="small">${ip}</code></td>
                </tr>
            `;
        });
        tbody.html(html);

        // Renderizar Paginador
        renderAuditPagination(pag);
    }

    function renderAuditPagination(pag) {
        const ul = $('#audit-pagination-ul');
        ul.empty();
        if (pag.pages <= 1) return;

        const prevDisabled = pag.page <= 1 ? 'disabled' : '';
        ul.append(`
            <li class="page-item ${prevDisabled}">
                <a class="page-link" href="javascript:void(0)" onclick="loadAuditLogs(${pag.page - 1})"><i class="fas fa-chevron-left"></i> Anterior</a>
            </li>
        `);

        let start = Math.max(1, pag.page - 2);
        let end = Math.min(pag.pages, pag.page + 2);
        if (start > 1) {
            ul.append(`<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="loadAuditLogs(1)">1</a></li>`);
            if (start > 2) ul.append(`<li class="page-item disabled"><span class="page-link">...</span></li>`);
        }

        for (let p = start; p <= end; p++) {
            const active = p === pag.page ? 'active' : '';
            ul.append(`
                <li class="page-item ${active}">
                    <a class="page-link" href="javascript:void(0)" onclick="loadAuditLogs(${p})">${p}</a>
                </li>
            `);
        }

        if (end < pag.pages) {
            if (end < pag.pages - 1) ul.append(`<li class="page-item disabled"><span class="page-link">...</span></li>`);
            ul.append(`<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="loadAuditLogs(${pag.pages})">${pag.pages}</a></li>`);
        }

        const nextDisabled = pag.page >= pag.pages ? 'disabled' : '';
        ul.append(`
            <li class="page-item ${nextDisabled}">
                <a class="page-link" href="javascript:void(0)" onclick="loadAuditLogs(${pag.page + 1})">Siguiente <i class="fas fa-chevron-right"></i></a>
            </li>
        `);
    }

    function inspectAuditLog(logId) {
        const log = currentAuditLogsList.find(l => l.id == logId);
        if (!log) {
            toastr.warning('No se pudo cargar el registro seleccionado.');
            return;
        }

        $('#audit-detail-id').text(log.id);
        const modalBody = $('#modal-audit-detail-body');
        const details = log.details || {};
        lastInspectedAuditJson = JSON.stringify(log, null, 4);

        let contentHtml = '';

        // Tarjeta Superior de Metadatos
        contentHtml += `
            <div class="card bg-light border mb-3 p-3" style="border-radius: 10px;">
                <div class="row align-items-center">
                    <div class="col-md-3 border-right">
                        <small class="text-muted d-block text-uppercase font-weight-bold">Tipo de Operación</small>
                        <h6 class="mb-0 font-weight-bold text-primary mt-1">
                            <span class="badge ${log.badge_class === 'success' ? 'badge-success' : (log.badge_class === 'primary' ? 'badge-primary' : (log.badge_class === 'danger' ? 'badge-danger' : 'badge-dark'))} px-2 py-1">${escapeHtml(log.action_label || log.action)}</span>
                        </h6>
                    </div>
                    <div class="col-md-3 border-right">
                        <small class="text-muted d-block text-uppercase font-weight-bold">Activo / CI</small>
                        <div class="font-weight-bold text-dark mt-1">${escapeHtml(log.display_hostname)}</div>
                        <div class="small text-muted font-monospace">${escapeHtml(log.display_id_ci)}</div>
                    </div>
                    <div class="col-md-3 border-right">
                        <small class="text-muted d-block text-uppercase font-weight-bold">Operador Responsable</small>
                        <div class="font-weight-bold text-dark mt-1"><i class="fas fa-user-circle mr-1 text-secondary"></i>${escapeHtml(log.user_name)}</div>
                        <div class="small text-muted"><i class="fas fa-network-wired mr-1"></i>IP: ${escapeHtml(log.ip_address)}</div>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block text-uppercase font-weight-bold">Marca de Tiempo</small>
                        <div class="font-weight-bold text-dark mt-1"><i class="far fa-calendar-alt mr-1 text-secondary"></i>${log.created_at}</div>
                    </div>
                </div>
                <div class="border-top mt-3 pt-2">
                    <b>Resumen:</b> <span class="text-dark">${escapeHtml(log.summary)}</span>
                </div>
            </div>
        `;

        // Si es un UPDATE y contiene cambios diff
        if (log.action === 'UPDATE' && log.changes && Object.keys(log.changes).length > 0) {
            contentHtml += `
                <div class="mb-3">
                    <h6 class="font-weight-bold text-primary mb-2">
                        <i class="fas fa-exchange-alt mr-1"></i> Desglose Comparativo de Campos Modificados (Diff Antes / Después):
                    </h6>
                    <div class="table-responsive">
                        <table class="diff-table">
                            <thead>
                                <tr>
                                    <th style="width: 25%;">Campo Modificado</th>
                                    <th style="width: 37.5%; color: #be123c;"><i class="fas fa-arrow-left mr-1"></i> Valor Anterior</th>
                                    <th style="width: 37.5%; color: #15803d;"><i class="fas fa-arrow-right mr-1"></i> Valor Nuevo</th>
                                </tr>
                            </thead>
                            <tbody>
            `;

            for (const [key, change] of Object.entries(log.changes)) {
                const label = change.label || key;
                const oldVal = change.old !== '' && change.old !== null ? escapeHtml(change.old) : '<i class="text-muted small">(vacío)</i>';
                const newVal = change.new !== '' && change.new !== null ? escapeHtml(change.new) : '<i class="text-muted small">(vacío)</i>';

                contentHtml += `
                    <tr>
                        <td class="font-weight-bold text-dark">
                            <i class="fas fa-tag text-muted mr-1"></i> ${escapeHtml(label)}
                            <div class="small text-muted font-monospace">${escapeHtml(key)}</div>
                        </td>
                        <td>
                            <span class="diff-val-old">${oldVal}</span>
                        </td>
                        <td>
                            <span class="diff-val-new">${newVal}</span>
                        </td>
                    </tr>
                `;
            }

            contentHtml += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        } else if (log.action === 'CREATE') {
            // Detalle de Alta
            contentHtml += `
                <div class="card border mb-3" style="border-radius: 10px;">
                    <div class="card-header bg-success text-white py-2 font-weight-bold">
                        <i class="fas fa-check-circle mr-1"></i> Parámetros de Alta del Elemento de Configuración
                    </div>
                    <div class="card-body p-3">
                        <div class="row">
                            <div class="col-md-4 mb-2"><b>Hostname:</b> ${escapeHtml(details.hostname || 'N/A')}</div>
                            <div class="col-md-4 mb-2"><b>ID CI:</b> ${escapeHtml(details.id_ci || 'N/A')}</div>
                            <div class="col-md-4 mb-2"><b>Tipo de CI:</b> ${escapeHtml(details.tipo_ci || 'N/A')}</div>
                            <div class="col-md-4 mb-2"><b>Cliente:</b> ${escapeHtml(details.cliente || 'N/A')}</div>
                            <div class="col-md-4 mb-2"><b>Servicio:</b> ${escapeHtml(details.servicio || 'N/A')}</div>
                            <div class="col-md-4 mb-2"><b>IP Administración:</b> <code>${escapeHtml(details.ip_administracion || 'N/A')}</code></div>
                            <div class="col-md-4 mb-2"><b>Ambiente:</b> ${escapeHtml(details.ambiente || 'N/A')}</div>
                            <div class="col-md-4 mb-2"><b>Estado:</b> ${escapeHtml(details.estado_ci || 'N/A')}</div>
                            <div class="col-md-4 mb-2"><b>Criticidad:</b> ${escapeHtml(details.criticidad || 'N/A')}</div>
                        </div>
                    </div>
                </div>
            `;
        } else if (log.action === 'DELETE') {
            // Snapshot Forense de Respaldo
            const snap = details.snapshot || {};
            contentHtml += `
                <div class="card border mb-3" style="border-radius: 10px;">
                    <div class="card-header bg-danger text-white py-2 font-weight-bold">
                        <i class="fas fa-archive mr-1"></i> Snapshot Forense del CI al Momento de su Eliminación
                    </div>
                    <div class="card-body p-3">
                        <div class="row small">
                            <div class="col-md-4 mb-2"><b>Hostname:</b> ${escapeHtml(snap.hostname_nombre || 'N/A')}</div>
                            <div class="col-md-4 mb-2"><b>ID CI:</b> ${escapeHtml(snap.id_ci || 'N/A')}</div>
                            <div class="col-md-4 mb-2"><b>Tipo CI:</b> ${escapeHtml(snap.tipo_ci || 'N/A')}</div>
                            <div class="col-md-4 mb-2"><b>Fabricante / Modelo:</b> ${escapeHtml(snap.fabricante || '')} ${escapeHtml(snap.modelo || '')}</div>
                            <div class="col-md-4 mb-2"><b>Serial:</b> ${escapeHtml(snap.numero_serie || 'N/A')}</div>
                            <div class="col-md-4 mb-2"><b>IP Admin:</b> <code>${escapeHtml(snap.ip_administracion || 'N/A')}</code></div>
                            <div class="col-md-4 mb-2"><b>Cliente:</b> ${escapeHtml(snap.cliente || 'N/A')}</div>
                            <div class="col-md-4 mb-2"><b>Sede / Site:</b> ${escapeHtml(snap.sede_site || 'N/A')}</div>
                            <div class="col-md-4 mb-2"><b>Fin Soporte:</b> ${escapeHtml(snap.fin_soporte || 'N/A')}</div>
                        </div>
                    </div>
                </div>
            `;
        } else if (log.action.includes('RELATION')) {
            contentHtml += `
                <div class="card border mb-3" style="border-radius: 10px;">
                    <div class="card-header bg-info text-white py-2 font-weight-bold">
                        <i class="fas fa-link mr-1"></i> Detalle de la Relación de Dependencia
                    </div>
                    <div class="card-body p-3">
                        <div class="row">
                            <div class="col-md-6 mb-2"><b>CI Origen:</b> ${escapeHtml(details.source_hostname || ('CI #' + details.source_ci_id))}</div>
                            <div class="col-md-6 mb-2"><b>CI Destino:</b> ${escapeHtml(details.target_hostname || ('CI #' + details.target_ci_id))}</div>
                            <div class="col-md-6 mb-2"><b>Tipo de Relación:</b> <span class="badge badge-light border">${escapeHtml(details.type || 'N/A')}</span></div>
                            <div class="col-md-6 mb-2"><b>Impacto de Falla:</b> <span class="badge badge-danger">${escapeHtml(details.impact || 'N/A')}</span></div>
                        </div>
                    </div>
                </div>
            `;
        }

        // Bloque Técnico JSON Colapsable
        contentHtml += `
            <div class="accordion" id="auditJsonAccordion">
                <div class="card border" style="border-radius: 8px; overflow: hidden;">
                    <div class="card-header p-2 bg-light" id="headingJson">
                        <button class="btn btn-link btn-sm text-dark font-weight-bold collapsed text-decoration-none" type="button" data-toggle="collapse" data-target="#collapseJson" aria-expanded="false" aria-controls="collapseJson">
                            <i class="fas fa-code mr-1"></i> Inspeccionar Registro JSON Crudo (Para Auditoría Técnica)
                        </button>
                    </div>
                    <div id="collapseJson" class="collapse" aria-labelledby="headingJson" data-parent="#auditJsonAccordion">
                        <div class="card-body p-2 bg-dark">
                            <pre class="audit-json-box mb-0" id="audit-raw-json-box">${escapeHtml(lastInspectedAuditJson)}</pre>
                        </div>
                    </div>
                </div>
            </div>
        `;

        modalBody.html(contentHtml);
        $('#modal-audit-detail').modal('show');
    }

    function filterAuditByCi(ciId, idCi, hostname) {
        currentAuditCiFilter = {
            id: ciId,
            id_ci: idCi,
            hostname: hostname
        };

        $('#audit-ci-filter-name').text(hostname ? `${hostname} (${idCi})` : `CI #${ciId}`);
        $('#audit-ci-filter-badge-container').show();

        // Limpiar otros filtros para enfocar en este CI
        $('#audit-filter-q').val('');
        $('#audit-filter-action').val('');
        $('#audit-filter-user').val('');
        $('#audit-filter-from').val('');
        $('#audit-filter-to').val('');

        // Cambiar a la pestaña de historial
        $('#tab-historial-link').tab('show');
        loadAuditLogs(1);
    }

    function clearAuditCiFilter() {
        currentAuditCiFilter = null;
        $('#audit-ci-filter-badge-container').hide();
        loadAuditLogs(1);
    }

    function resetAuditFilters() {
        currentAuditCiFilter = null;
        $('#audit-ci-filter-badge-container').hide();
        $('#audit-filter-q').val('');
        $('#audit-filter-action').val('');
        $('#audit-filter-user').val('');
        $('#audit-filter-from').val('');
        $('#audit-filter-to').val('');
        loadAuditLogs(1);
    }

    function exportAuditCsv() {
        const q = $('#audit-filter-q').val() || '';
        const actionType = $('#audit-filter-action').val() || '';
        const user = $('#audit-filter-user').val() || '';
        const dateFrom = $('#audit-filter-from').val() || '';
        const dateTo = $('#audit-filter-to').val() || '';
        const ciId = currentAuditCiFilter ? currentAuditCiFilter.id : '';

        const url = `${API_URL}?action=export_audit_csv&q=${encodeURIComponent(q)}&action_type=${encodeURIComponent(actionType)}&user=${encodeURIComponent(user)}&date_from=${encodeURIComponent(dateFrom)}&date_to=${encodeURIComponent(dateTo)}&ci_id=${encodeURIComponent(ciId)}`;
        window.location.href = url;
    }

    function copyAuditJsonToClipboard() {
        if (!lastInspectedAuditJson) return;
        navigator.clipboard.writeText(lastInspectedAuditJson).then(() => {
            toastr.success('Registro JSON de auditoría copiado al portapapeles');
        }).catch(() => {
            toastr.info('No se pudo copiar automáticamente al portapapeles.');
        });
    }
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>

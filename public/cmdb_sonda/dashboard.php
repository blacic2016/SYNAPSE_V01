<?php
/**
 * CMDB_SONDA - Dashboard Analítico y de Inteligencia de Infraestructura (BI)
 * Ubicación: /var/www/html/PROYECTOSONDA/PREPODUCCION/CMDBPRnew/public/cmdb_sonda/dashboard.php
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

$page_title = 'CMDB SONDA - Dashboard Analítico & BI';
$page_icon = 'fas fa-chart-line text-info';
$hide_content_header = true;

require_once __DIR__ . '/../partials/header.php';
?>

<!-- Estilos para Dashboard Analítico CMDB_SONDA -->
<style>
    :root {
        --sonda-primary: #002b49;
        --sonda-secondary: #0052cc;
        --sonda-accent: #00b4d8;
        --sonda-bg: #f4f6f9;
        --sonda-card-bg: #ffffff;
        --sonda-border: #e2e8f0;
    }

    body.dark-mode {
        --sonda-bg: #0f172a;
        --sonda-card-bg: #1e293b;
        --sonda-border: #334155;
    }

    .bi-banner-header {
        background: linear-gradient(135deg, #002b49 0%, #0052cc 60%, #00b4d8 100%);
        color: #ffffff;
        padding: 22px 26px;
        border-radius: 14px;
        box-shadow: 0 10px 25px rgba(0, 43, 73, 0.22);
        margin-bottom: 24px;
        position: relative;
        overflow: hidden;
    }

    .bi-banner-header::before {
        content: '';
        position: absolute;
        top: -60px;
        right: -60px;
        width: 220px;
        height: 220px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.08);
        pointer-events: none;
    }

    .bi-banner-header::after {
        content: '';
        position: absolute;
        bottom: -80px;
        right: 140px;
        width: 180px;
        height: 180px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.05);
        pointer-events: none;
    }

    .filter-card-sonda {
        background-color: var(--sonda-card-bg);
        border: 1px solid var(--sonda-border);
        border-radius: 12px;
        padding: 16px 20px;
        box-shadow: 0 3px 12px rgba(0,0,0,0.03);
        margin-bottom: 22px;
        transition: all 0.2s ease;
    }

    .kpi-card-sonda {
        background-color: var(--sonda-card-bg);
        border: 1px solid var(--sonda-border);
        border-radius: 12px;
        padding: 18px 20px;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.04);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        height: 100%;
    }

    .kpi-card-sonda:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
    }

    .kpi-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        flex-shrink: 0;
    }

    .chart-card-sonda {
        background-color: var(--sonda-card-bg);
        border: 1px solid var(--sonda-border);
        border-radius: 12px;
        padding: 18px 20px;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
        margin-bottom: 22px;
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .chart-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 14px;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--sonda-border);
    }

    .chart-card-title {
        font-size: 0.98rem;
        font-weight: 700;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .chart-container-wrapper {
        position: relative !important;
        width: 100% !important;
        height: 250px !important;
        max-height: 250px !important;
        overflow: hidden !important;
    }

    .chart-container-wrapper-sm {
        position: relative !important;
        width: 100% !important;
        height: 220px !important;
        max-height: 220px !important;
        overflow: hidden !important;
    }

    .chart-container-wrapper canvas,
    .chart-container-wrapper-sm canvas {
        max-height: 100% !important;
        max-width: 100% !important;
        height: 100% !important;
        width: 100% !important;
        display: block !important;
    }

    /* Alarmas titilando en rojo */
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

    .beacon-alarm-titilando {
        display: inline-block;
        width: 8px;
        height: 8px;
        background-color: #ffffff;
        border-radius: 50%;
        animation: alarm-beacon-pulse 0.9s infinite ease-in-out;
        box-shadow: 0 0 6px #fff;
    }

    .kpi-card-alarm-active {
        border: 2px solid #dc3545 !important;
        animation: alarm-card-pulse 1.2s infinite ease-in-out !important;
        background: rgba(220, 53, 69, 0.06) !important;
    }

    .table-bi thead th {
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 700;
        background-color: rgba(0, 43, 73, 0.04);
        border-top: none;
    }

    body.dark-mode .table-bi thead th {
        background-color: rgba(255, 255, 255, 0.05);
    }

    .btn-bi-filter-pill {
        border-radius: 20px;
        font-size: 0.8rem;
        padding: 4px 14px;
        font-weight: 600;
    }
</style>

<!-- Chart.js Local con fallback CDN -->
<script src="vendor/chartjs/chart.umd.min.js"></script>
<script>
    if (typeof Chart === 'undefined') {
        document.write('<script src="https://cdn.jsdelivr.net/npm/chart.js"><\/script>');
    }
</script>

<div class="container-fluid pt-3 pb-5 px-3 px-md-4">

    <!-- Header Banner Ejecutivo -->
    <div class="bi-banner-header d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <div class="d-flex align-items-center mb-1">
                <span class="badge badge-light text-primary font-weight-bold mr-2 px-2 py-1" style="font-size: 0.8rem;">
                    <i class="fas fa-chart-pie text-primary mr-1"></i> Business Intelligence & Analytics
                </span>
                <span class="badge badge-warning text-dark font-weight-bold px-2 py-1" style="font-size: 0.8rem;">
                    CMDB_SONDA 2.0
                </span>
            </div>
            <h2 class="font-weight-bold mb-1" style="letter-spacing: -0.5px;">Dashboard Analítico de Infraestructura</h2>
            <p class="mb-0 text-white-50" style="max-width: 820px; font-size: 0.95rem;">
                Análisis multidimensional de configuración: distribución por cuentas de clientes, servicios críticos de negocio, tipos de CI, ambientes, criticidad y ciclo de vida de soporte.
            </p>
        </div>
        <div class="mt-3 mt-md-0 d-flex flex-wrap gap-2">
            <a href="index.php" class="btn btn-light font-weight-bold text-primary shadow-sm mr-2">
                <i class="fas fa-cubes mr-1 text-primary"></i> Volver a CMDB_SONDA
            </a>
            <button type="button" class="btn btn-warning font-weight-bold shadow-sm mr-2" onclick="loadAnalyticsData()">
                <i class="fas fa-sync-alt mr-1"></i> Actualizar Métricas
            </button>
            <button type="button" class="btn btn-outline-light font-weight-bold shadow-sm" onclick="window.print()">
                <i class="fas fa-print mr-1"></i> Imprimir Reporte
            </button>
        </div>
    </div>

    <!-- Barra de Filtros Interactivos Globales -->
    <div class="filter-card-sonda">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="font-weight-bold mb-0 text-navy">
                <i class="fas fa-filter text-primary mr-1"></i> Filtros de Análisis Multidimensional
            </h6>
            <button class="btn btn-xs btn-outline-secondary" onclick="resetFilters()">
                <i class="fas fa-undo mr-1"></i> Restablecer Filtros
            </button>
        </div>
        <div class="row">
            <div class="col-md-2 col-sm-6 mb-2">
                <label class="small text-muted font-weight-bold mb-1">Cliente / Cuenta:</label>
                <select id="filter-cliente" class="form-control form-control-sm" onchange="onClienteFilterChange()">
                    <option value="">Todos los Clientes</option>
                </select>
            </div>
            <div class="col-md-2 col-sm-6 mb-2">
                <label class="small text-muted font-weight-bold mb-1">Servicio de Negocio:</label>
                <select id="filter-servicio" class="form-control form-control-sm" onchange="loadAnalyticsData()">
                    <option value="">Todos los Servicios</option>
                </select>
            </div>
            <div class="col-md-2 col-sm-6 mb-2">
                <label class="small text-muted font-weight-bold mb-1">Tipo de CI / Categoría:</label>
                <select id="filter-tipo-ci" class="form-control form-control-sm" onchange="loadAnalyticsData()">
                    <option value="">Todos los Tipos</option>
                </select>
            </div>
            <div class="col-md-2 col-sm-6 mb-2">
                <label class="small text-muted font-weight-bold mb-1">Ambiente:</label>
                <select id="filter-ambiente" class="form-control form-control-sm" onchange="loadAnalyticsData()">
                    <option value="">Todos los Ambientes</option>
                </select>
            </div>
            <div class="col-md-2 col-sm-6 mb-2">
                <label class="small text-muted font-weight-bold mb-1">Criticidad:</label>
                <select id="filter-criticidad" class="form-control form-control-sm" onchange="loadAnalyticsData()">
                    <option value="">Todas las Criticidades</option>
                    <option value="Crítica">Crítica</option>
                    <option value="Alta">Alta</option>
                    <option value="Media">Media</option>
                    <option value="Baja">Baja</option>
                </select>
            </div>
            <div class="col-md-2 col-sm-6 mb-2">
                <label class="small text-muted font-weight-bold mb-1">Estado de Soporte:</label>
                <select id="filter-support-status" class="form-control form-control-sm" onchange="loadAnalyticsData()">
                    <option value="all">Todos los Estados</option>
                    <option value="expired">Soporte Vencido (Alarma)</option>
                    <option value="due30">Por Vencer (≤30 días)</option>
                    <option value="due60">Por Vencer (≤60 días)</option>
                    <option value="active">Soporte Vigente (>60 días)</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Banner de Alerta de Soporte Vencido (Si aplica) -->
    <div id="alarm-banner" class="alert shadow-sm mb-4 py-2 px-3 align-items-center justify-content-between" style="display: none; background: rgba(220, 53, 69, 0.1); border: 1.5px solid #dc3545; border-left: 6px solid #dc3545; border-radius: 10px;">
        <div class="d-flex align-items-center">
            <span class="beacon-alarm-titilando mr-2" style="width: 11px; height: 11px; background-color: #ff001e;"></span>
            <span class="text-danger mr-2 text-uppercase font-weight-bold" style="letter-spacing: 0.5px;">
                <i class="fas fa-bell mr-1"></i> Alarma de Infraestructura:
            </span>
            <span class="font-weight-bold text-dark" id="alarm-banner-text">Se detectaron CIs con soporte o contrato vencido.</span>
        </div>
        <div>
            <button class="btn btn-xs btn-danger font-weight-bold shadow-sm" onclick="$('#filter-support-status').val('expired'); loadAnalyticsData();">
                <i class="fas fa-filter mr-1"></i> Aislar CIs Vencidos
            </button>
        </div>
    </div>

    <!-- KPIs Row (6 Indicadores Ejecutivos) -->
    <div class="row mb-4">
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
                    <i class="fas fa-building"></i>
                </div>
                <div>
                    <span class="text-muted small font-weight-bold text-uppercase">Clientes</span>
                    <h4 class="font-weight-bolder mb-0" id="kpi-total-clients">--</h4>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="kpi-card-sonda d-flex align-items-center">
                <div class="kpi-icon bg-indigo text-white mr-3" style="background-color: #4f46e5;">
                    <i class="fas fa-sitemap"></i>
                </div>
                <div>
                    <span class="text-muted small font-weight-bold text-uppercase">Servicios</span>
                    <h4 class="font-weight-bolder mb-0" id="kpi-total-services">--</h4>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="kpi-card-sonda d-flex align-items-center">
                <div class="kpi-icon bg-cyan text-white mr-3">
                    <i class="fas fa-layer-group"></i>
                </div>
                <div>
                    <span class="text-muted small font-weight-bold text-uppercase">En Producción</span>
                    <h4 class="font-weight-bolder mb-0" id="kpi-prod-cis">--%</h4>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="kpi-card-sonda d-flex align-items-center" id="kpi-card-expired" style="cursor: pointer;" onclick="$('#filter-support-status').val('expired'); loadAnalyticsData();" title="Filtrar CIs con soporte vencido">
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
                <div class="kpi-icon bg-success text-white mr-3">
                    <i class="fas fa-heartbeat"></i>
                </div>
                <div>
                    <span class="text-muted small font-weight-bold text-uppercase">Monitoreo ZBX</span>
                    <h4 class="font-weight-bolder mb-0 text-success" id="kpi-monitored-pct">--%</h4>
                </div>
            </div>
        </div>
    </div>

    <!-- FILA 1 DE GRÁFICOS: POR CLIENTE Y POR TIPO DE CI -->
    <div class="row">
        <!-- Gráfico 1: Análisis por Cliente -->
        <div class="col-lg-7 col-md-12 mb-4">
            <div class="chart-card-sonda">
                <div class="chart-card-header">
                    <h6 class="chart-card-title text-navy">
                        <i class="fas fa-building text-primary"></i> 1. Análisis de CIs por Cliente / Cuenta
                    </h6>
                    <span class="badge badge-light border text-muted">Barras Comparativas</span>
                </div>
                <div class="chart-container-wrapper">
                    <canvas id="chart-by-client"></canvas>
                </div>
            </div>
        </div>

        <!-- Gráfico 2: Desglose por Tipo de CI -->
        <div class="col-lg-5 col-md-12 mb-4">
            <div class="chart-card-sonda">
                <div class="chart-card-header">
                    <h6 class="chart-card-title text-navy">
                        <i class="fas fa-cubes text-info"></i> 2. Distribución por Tipo de CI
                    </h6>
                    <span class="badge badge-light border text-muted">Donut Categorías</span>
                </div>
                <div class="chart-container-wrapper-sm">
                    <canvas id="chart-by-type"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- FILA 2 DE GRÁFICOS: POR SERVICIO Y POR CRITICIDAD -->
    <div class="row">
        <!-- Gráfico 3: CIs por Servicio de Negocio -->
        <div class="col-lg-8 col-md-12 mb-4">
            <div class="chart-card-sonda">
                <div class="chart-card-header">
                    <h6 class="chart-card-title text-navy">
                        <i class="fas fa-sitemap text-indigo"></i> 3. CIs Dependientes por Servicio Crítico de Negocio
                    </h6>
                    <span class="badge badge-light border text-muted">Catálogo ITIL</span>
                </div>
                <div class="chart-container-wrapper">
                    <canvas id="chart-by-service"></canvas>
                </div>
            </div>
        </div>

        <!-- Gráfico 4: Matriz de Criticidad -->
        <div class="col-lg-4 col-md-12 mb-4">
            <div class="chart-card-sonda">
                <div class="chart-card-header">
                    <h6 class="chart-card-title text-navy">
                        <i class="fas fa-shield-alt text-danger"></i> 4. Matriz de Criticidad
                    </h6>
                    <span class="badge badge-light border text-muted">Nivel de Impacto</span>
                </div>
                <div class="chart-container-wrapper-sm">
                    <canvas id="chart-by-criticidad"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- FILA 3 DE GRÁFICOS: AMBIENTE, SOPORTE Y VENDORS -->
    <div class="row">
        <!-- Gráfico 5: Distribución por Ambiente -->
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="chart-card-sonda">
                <div class="chart-card-header">
                    <h6 class="chart-card-title text-navy">
                        <i class="fas fa-layer-group text-success"></i> 5. Distribución por Ambiente
                    </h6>
                    <span class="badge badge-light border text-muted">PROD vs DR vs DEV</span>
                </div>
                <div class="chart-container-wrapper-sm">
                    <canvas id="chart-by-environment"></canvas>
                </div>
            </div>
        </div>

        <!-- Gráfico 6: Estado de Soporte y Garantías -->
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="chart-card-sonda">
                <div class="chart-card-header">
                    <h6 class="chart-card-title text-navy">
                        <i class="fas fa-hourglass-half text-warning"></i> 6. Estado de Soporte & Garantías
                    </h6>
                    <span class="badge badge-light border text-muted">Aging de Vencimiento</span>
                </div>
                <div class="chart-container-wrapper">
                    <canvas id="chart-by-support"></canvas>
                </div>
            </div>
        </div>

        <!-- Gráfico 7: Top Fabricantes (Vendors) -->
        <div class="col-lg-4 col-md-12 mb-4">
            <div class="chart-card-sonda">
                <div class="chart-card-header">
                    <h6 class="chart-card-title text-navy">
                        <i class="fas fa-industry text-secondary"></i> 7. Top Fabricantes (Vendors)
                    </h6>
                    <span class="badge badge-light border text-muted">Cuota de Hardware</span>
                </div>
                <div class="chart-container-wrapper">
                    <canvas id="chart-by-vendor"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- TABLAS DE RESUMEN EJECUTIVO CON DRILL-DOWN -->
    <div class="card card-outline card-primary shadow-sm mb-4" style="border-radius: 12px;">
        <div class="card-header p-2 bg-light d-flex justify-content-between align-items-center">
            <ul class="nav nav-pills" id="biTableTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active font-weight-bold py-1 px-3" id="tab-table-client-link" data-toggle="pill" href="#tab-table-client" role="tab">
                        <i class="fas fa-building mr-1 text-primary"></i> Resumen por Cliente
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link font-weight-bold py-1 px-3" id="tab-table-service-link" data-toggle="pill" href="#tab-table-service" role="tab">
                        <i class="fas fa-sitemap mr-1 text-indigo"></i> Resumen por Servicio
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link font-weight-bold py-1 px-3" id="tab-table-type-link" data-toggle="pill" href="#tab-table-type" role="tab">
                        <i class="fas fa-cubes mr-1 text-info"></i> Resumen por Tipo de CI
                    </a>
                </li>
            </ul>
            <div class="small text-muted mr-2">
                <i class="fas fa-mouse-pointer mr-1"></i> Haz clic en "Filtrar" para aislar en el dashboard
            </div>
        </div>
        <div class="card-body p-0">
            <div class="tab-content" id="biTableTabsContent">
                <!-- Tabla por Cliente -->
                <div class="tab-pane fade show active" id="tab-table-client" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped table-bi mb-0" id="table-clients-summary">
                            <thead>
                                <tr>
                                    <th>Cliente / Entidad</th>
                                    <th class="text-center">Total CIs</th>
                                    <th class="text-center">Servicios</th>
                                    <th class="text-center">CIs Críticos</th>
                                    <th class="text-center">Soporte Vencido</th>
                                    <th class="text-center">Por Vencer (≤30d)</th>
                                    <th class="text-center">Soporte Vigente</th>
                                    <th class="text-center">Monitoreados ZBX</th>
                                    <th class="text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-clients-summary">
                                <tr><td colspan="9" class="text-center py-3 text-muted"><i class="fas fa-spinner fa-spin mr-1"></i> Cargando métricas por cliente...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tabla por Servicio -->
                <div class="tab-pane fade" id="tab-table-service" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped table-bi mb-0" id="table-services-summary">
                            <thead>
                                <tr>
                                    <th>Servicio de Negocio</th>
                                    <th>Cliente Propietario</th>
                                    <th class="text-center">CIs Dependientes</th>
                                    <th class="text-center">CIs Críticos</th>
                                    <th class="text-center">Soporte Vencido</th>
                                    <th class="text-center">Monitoreados ZBX</th>
                                    <th class="text-center">Salud del Servicio</th>
                                    <th class="text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-services-summary">
                                <tr><td colspan="8" class="text-center py-3 text-muted"><i class="fas fa-spinner fa-spin mr-1"></i> Cargando métricas por servicio...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tabla por Tipo de CI -->
                <div class="tab-pane fade" id="tab-table-type" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped table-bi mb-0" id="table-types-summary">
                            <thead>
                                <tr>
                                    <th>Tipo de CI / Categoría Tecnológica</th>
                                    <th class="text-center">Total Unidades</th>
                                    <th class="text-center">CIs Críticos</th>
                                    <th class="text-center">Soporte Vencido</th>
                                    <th class="text-center">Monitoreados ZBX</th>
                                    <th class="text-center">Cobertura de Monitoreo</th>
                                    <th class="text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-types-summary">
                                <tr><td colspan="7" class="text-center py-3 text-muted"><i class="fas fa-spinner fa-spin mr-1"></i> Cargando métricas por tipo...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>

<!-- Script Lógica Reactiva y Renderizado de Gráficos (Chart.js) -->
<script>
// Diccionario de instancias activas de Chart.js para evitar solapamiento
const chartInstances = {};

// Cache de opciones de filtrado
let cachedFilterOptions = null;

$(document).ready(function() {
    loadAnalyticsData(true);
});

function getActiveFilters() {
    return {
        cliente: $('#filter-cliente').val() || '',
        servicio: $('#filter-servicio').val() || '',
        tipo_ci: $('#filter-tipo-ci').val() || '',
        ambiente: $('#filter-ambiente').val() || '',
        criticidad: $('#filter-criticidad').val() || '',
        support_status: $('#filter-support-status').val() || 'all'
    };
}

function resetFilters() {
    $('#filter-cliente').val('');
    $('#filter-servicio').val('');
    $('#filter-tipo-ci').val('');
    $('#filter-ambiente').val('');
    $('#filter-criticidad').val('');
    $('#filter-support-status').val('all');
    if (cachedFilterOptions) {
        populateFilterDropdowns(cachedFilterOptions, false);
    }
    loadAnalyticsData();
}

function onClienteFilterChange() {
    const selectedCliente = $('#filter-cliente').val();
    if (cachedFilterOptions && cachedFilterOptions.services) {
        const serviceSelect = $('#filter-servicio');
        const currentSvc = serviceSelect.val();
        serviceSelect.empty().append('<option value="">Todos los Servicios</option>');
        
        cachedFilterOptions.services.forEach(s => {
            if (!selectedCliente || s.cliente === selectedCliente) {
                serviceSelect.append(`<option value="${escapeHtml(s.nombre_servicio)}">${escapeHtml(s.nombre_servicio)}</option>`);
            }
        });
        serviceSelect.val(currentSvc || '');
    }
    loadAnalyticsData();
}

function populateFilterDropdowns(opts, resetSelection = false) {
    cachedFilterOptions = opts;

    // 1. Clientes
    const clienteSelect = $('#filter-cliente');
    const curCliente = resetSelection ? '' : clienteSelect.val();
    clienteSelect.empty().append('<option value="">Todos los Clientes</option>');
    if (opts.clients && Array.isArray(opts.clients)) {
        opts.clients.forEach(c => {
            clienteSelect.append(`<option value="${escapeHtml(c)}">${escapeHtml(c)}</option>`);
        });
    }
    clienteSelect.val(curCliente);

    // 2. Servicios
    const serviceSelect = $('#filter-servicio');
    const curService = resetSelection ? '' : serviceSelect.val();
    serviceSelect.empty().append('<option value="">Todos los Servicios</option>');
    if (opts.services && Array.isArray(opts.services)) {
        opts.services.forEach(s => {
            if (!curCliente || s.cliente === curCliente) {
                serviceSelect.append(`<option value="${escapeHtml(s.nombre_servicio)}">${escapeHtml(s.nombre_servicio)}</option>`);
            }
        });
    }
    serviceSelect.val(curService);

    // 3. Tipos de CI
    const typeSelect = $('#filter-tipo-ci');
    const curType = resetSelection ? '' : typeSelect.val();
    typeSelect.empty().append('<option value="">Todos los Tipos</option>');
    if (opts.types && Array.isArray(opts.types)) {
        opts.types.forEach(t => {
            typeSelect.append(`<option value="${escapeHtml(t)}">${escapeHtml(t)}</option>`);
        });
    }
    typeSelect.val(curType);

    // 4. Ambientes
    const envSelect = $('#filter-ambiente');
    const curEnv = resetSelection ? '' : envSelect.val();
    envSelect.empty().append('<option value="">Todos los Ambientes</option>');
    if (opts.environments && Array.isArray(opts.environments)) {
        opts.environments.forEach(e => {
            envSelect.append(`<option value="${escapeHtml(e)}">${escapeHtml(e)}</option>`);
        });
    }
    envSelect.val(curEnv);
}

function loadAnalyticsData(isInitial = false) {
    const filters = getActiveFilters();

    $.ajax({
        url: 'api.php?action=get_analytics_dashboard',
        method: 'GET',
        data: filters,
        dataType: 'json',
        success: function(res) {
            if (!res.success) {
                Swal.fire('Error', res.error || 'No se pudo cargar la analítica', 'error');
                return;
            }

            if (isInitial && res.filter_options) {
                populateFilterDropdowns(res.filter_options, false);
            }

            renderKpis(res.kpis);
            renderCharts(res);
            renderTables(res);
        },
        error: function() {
            Swal.fire('Error de Conexión', 'No se pudo comunicar con el servidor REST de CMDB_SONDA.', 'error');
        }
    });
}

function renderKpis(k) {
    $('#kpi-total-cis').text(k.total_cis || 0);
    $('#kpi-total-clients').text(k.total_clients || 0);
    $('#kpi-total-services').text(k.total_services || 0);
    $('#kpi-prod-cis').text((k.prod_pct || 0) + '%');
    $('#kpi-monitored-pct').text((k.monitored_pct || 0) + '%');

    const expired = parseInt(k.expired_support || 0);
    const expiredCard = $('#kpi-card-expired');
    const expiredH4 = $('#kpi-expired-support');

    if (expired > 0) {
        expiredH4.html(`<span class="badge-alarm-titilando"><span class="beacon-alarm-titilando"></span> ${expired} ALARMA</span>`);
        expiredCard.addClass('kpi-card-alarm-active');
        $('#alarm-banner').slideDown(200);
        $('#alarm-banner-text').html(`Hay <strong>${expired} CI(s)</strong> con soporte de fábrica o garantía vencidos que requieren atención inmediata.`);
    } else {
        expiredH4.text('0 Vigentes');
        expiredCard.removeClass('kpi-card-alarm-active');
        $('#alarm-banner').slideUp(200);
    }
}

function destroyChart(id) {
    if (chartInstances[id]) {
        chartInstances[id].destroy();
        delete chartInstances[id];
    }
}

function renderCharts(data) {
    const isDark = $('body').hasClass('dark-mode');
    const textColor = isDark ? '#e2e8f0' : '#1e293b';
    const gridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';

    // -------------------------------------------------------------
    // GRÁFICO 1: ANÁLISIS POR CLIENTE (Barras Horizontales)
    // -------------------------------------------------------------
    destroyChart('chart-by-client');
    const clientLabels = (data.by_client || []).map(item => item.cliente);
    const clientTotals = (data.by_client || []).map(item => item.total);
    const clientCriticos = (data.by_client || []).map(item => item.criticos);
    const clientVencidos = (data.by_client || []).map(item => item.vencidos);

    const ctxClient = document.getElementById('chart-by-client').getContext('2d');
    chartInstances['chart-by-client'] = new Chart(ctxClient, {
        type: 'bar',
        data: {
            labels: clientLabels.length ? clientLabels : ['Sin datos'],
            datasets: [
                {
                    label: 'Total CIs',
                    data: clientTotals.length ? clientTotals : [0],
                    backgroundColor: 'rgba(0, 82, 204, 0.85)',
                    borderColor: '#0052cc',
                    borderWidth: 1.5,
                    borderRadius: 6
                },
                {
                    label: 'CIs Críticos',
                    data: clientCriticos.length ? clientCriticos : [0],
                    backgroundColor: 'rgba(220, 53, 69, 0.85)',
                    borderColor: '#dc3545',
                    borderWidth: 1.5,
                    borderRadius: 6
                },
                {
                    label: 'Soporte Vencido',
                    data: clientVencidos.length ? clientVencidos : [0],
                    backgroundColor: 'rgba(255, 193, 7, 0.85)',
                    borderColor: '#ffc107',
                    borderWidth: 1.5,
                    borderRadius: 6
                }
            ]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { labels: { color: textColor, font: { weight: 'bold' } } },
                tooltip: {
                    callbacks: {
                        afterBody: function(items) {
                            const idx = items[0].dataIndex;
                            const d = data.by_client[idx];
                            if (d) {
                                return `Servicios: ${d.servicios_count} | Monitoreados: ${d.monitoreados}`;
                            }
                            return '';
                        }
                    }
                }
            },
            scales: {
                x: { ticks: { color: textColor, precision: 0 }, grid: { color: gridColor } },
                y: { ticks: { color: textColor, font: { weight: '600' } }, grid: { display: false } }
            },
            onClick: (e, elements) => {
                if (elements.length > 0) {
                    const idx = elements[0].index;
                    const cliente = clientLabels[idx];
                    if (cliente && cliente !== 'Sin datos') {
                        $('#filter-cliente').val(cliente);
                        onClienteFilterChange();
                    }
                }
            }
        }
    });

    // -------------------------------------------------------------
    // GRÁFICO 2: DESGLOSE POR TIPO DE CI (Donut)
    // -------------------------------------------------------------
    destroyChart('chart-by-type');
    const typeLabels = (data.by_type || []).map(i => i.tipo_ci);
    const typeTotals = (data.by_type || []).map(i => i.total);
    const typePalette = ['#0052cc', '#00b4d8', '#4f46e5', '#10b981', '#f59e0b', '#dc3545', '#7c3aed', '#64748b'];

    const ctxType = document.getElementById('chart-by-type').getContext('2d');
    chartInstances['chart-by-type'] = new Chart(ctxType, {
        type: 'doughnut',
        data: {
            labels: typeLabels.length ? typeLabels : ['Sin datos'],
            datasets: [{
                data: typeTotals.length ? typeTotals : [0],
                backgroundColor: typePalette.slice(0, typeLabels.length || 1),
                borderWidth: 2,
                borderColor: isDark ? '#1e293b' : '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { color: textColor, font: { size: 11 } } },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            const val = ctx.parsed;
                            const total = ctx.dataset.data.reduce((a,b) => a + b, 0);
                            const pct = total > 0 ? Math.round((val / total) * 100) : 0;
                            return ` ${ctx.label}: ${val} CIs (${pct}%)`;
                        }
                    }
                }
            },
            cutout: '58%',
            onClick: (e, elements) => {
                if (elements.length > 0) {
                    const idx = elements[0].index;
                    const tipo = typeLabels[idx];
                    if (tipo && tipo !== 'Sin datos') {
                        $('#filter-tipo-ci').val(tipo);
                        loadAnalyticsData();
                    }
                }
            }
        }
    });

    // -------------------------------------------------------------
    // GRÁFICO 3: CIs POR SERVICIO CRÍTICO (Barras Verticales)
    // -------------------------------------------------------------
    destroyChart('chart-by-service');
    const serviceLabels = (data.by_service || []).map(i => i.servicio);
    const serviceTotals = (data.by_service || []).map(i => i.total);
    const serviceVencidos = (data.by_service || []).map(i => i.vencidos);

    const ctxService = document.getElementById('chart-by-service').getContext('2d');
    chartInstances['chart-by-service'] = new Chart(ctxService, {
        type: 'bar',
        data: {
            labels: serviceLabels.length ? serviceLabels : ['Sin datos'],
            datasets: [
                {
                    label: 'CIs Dependientes',
                    data: serviceTotals.length ? serviceTotals : [0],
                    backgroundColor: 'rgba(79, 70, 229, 0.85)',
                    borderColor: '#4f46e5',
                    borderWidth: 1.5,
                    borderRadius: 6
                },
                {
                    label: 'Con Soporte Vencido',
                    data: serviceVencidos.length ? serviceVencidos : [0],
                    backgroundColor: 'rgba(220, 53, 69, 0.85)',
                    borderColor: '#dc3545',
                    borderWidth: 1.5,
                    borderRadius: 6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { labels: { color: textColor, font: { weight: 'bold' } } },
                tooltip: {
                    callbacks: {
                        afterLabel: function(item) {
                            const idx = item.dataIndex;
                            const s = data.by_service[idx];
                            return s ? `Cliente: ${s.cliente} | Críticos: ${s.criticos}` : '';
                        }
                    }
                }
            },
            scales: {
                x: {
                    ticks: {
                        color: textColor,
                        maxRotation: 20,
                        callback: function(val) {
                            const text = this.getLabelForValue(val);
                            return text.length > 20 ? text.substr(0, 18) + '...' : text;
                        }
                    },
                    grid: { display: false }
                },
                y: { ticks: { color: textColor, precision: 0 }, grid: { color: gridColor } }
            },
            onClick: (e, elements) => {
                if (elements.length > 0) {
                    const idx = elements[0].index;
                    const servicio = serviceLabels[idx];
                    if (servicio && servicio !== 'Sin datos') {
                        $('#filter-servicio').val(servicio);
                        loadAnalyticsData();
                    }
                }
            }
        }
    });

    // -------------------------------------------------------------
    // GRÁFICO 4: MATRIZ DE CRITICIDAD (Pie Chart)
    // -------------------------------------------------------------
    destroyChart('chart-by-criticidad');
    const critLabels = (data.by_criticidad || []).map(i => i.criticidad);
    const critTotals = (data.by_criticidad || []).map(i => i.total);
    const critColorsMap = {
        'Crítica': '#dc3545',
        'Alta': '#fd7e14',
        'Media': '#ffc107',
        'Baja': '#28a745'
    };
    const critColors = critLabels.map(l => critColorsMap[l] || '#6c757d');

    const ctxCrit = document.getElementById('chart-by-criticidad').getContext('2d');
    chartInstances['chart-by-criticidad'] = new Chart(ctxCrit, {
        type: 'pie',
        data: {
            labels: critLabels.length ? critLabels : ['Sin datos'],
            datasets: [{
                data: critTotals.length ? critTotals : [0],
                backgroundColor: critColors,
                borderWidth: 2,
                borderColor: isDark ? '#1e293b' : '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { color: textColor } }
            },
            onClick: (e, elements) => {
                if (elements.length > 0) {
                    const idx = elements[0].index;
                    const crit = critLabels[idx];
                    if (crit && crit !== 'Sin datos') {
                        $('#filter-criticidad').val(crit);
                        loadAnalyticsData();
                    }
                }
            }
        }
    });

    // -------------------------------------------------------------
    // GRÁFICO 5: DISTRIBUCIÓN POR AMBIENTE (Polar Area)
    // -------------------------------------------------------------
    destroyChart('chart-by-environment');
    const envLabels = (data.by_environment || []).map(i => i.ambiente);
    const envTotals = (data.by_environment || []).map(i => i.total);
    const envPalette = ['#0052cc', '#10b981', '#f59e0b', '#6366f1', '#ec4899', '#8b5cf6'];

    const ctxEnv = document.getElementById('chart-by-environment').getContext('2d');
    chartInstances['chart-by-environment'] = new Chart(ctxEnv, {
        type: 'polarArea',
        data: {
            labels: envLabels.length ? envLabels : ['Sin datos'],
            datasets: [{
                data: envTotals.length ? envTotals : [0],
                backgroundColor: envPalette.slice(0, envLabels.length || 1).map(c => c + 'cc')
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { color: textColor, font: { size: 10 } } }
            },
            scales: {
                r: { ticks: { color: textColor, backdropColor: 'transparent' }, grid: { color: gridColor } }
            },
            onClick: (e, elements) => {
                if (elements.length > 0) {
                    const idx = elements[0].index;
                    const env = envLabels[idx];
                    if (env && env !== 'Sin datos') {
                        $('#filter-ambiente').val(env);
                        loadAnalyticsData();
                    }
                }
            }
        }
    });

    // -------------------------------------------------------------
    // GRÁFICO 6: ESTADO DE SOPORTE Y GARANTÍAS (Barra de Estados)
    // -------------------------------------------------------------
    destroyChart('chart-by-support');
    const sup = data.by_support_status || {};
    const supLabels = ['Vencido (Alarma)', 'Por Vencer (≤30d)', 'Por Vencer (31-90d)', 'Vigente (>90d)'];
    const supValues = [
        parseInt(sup.vencido || 0),
        parseInt(sup.vence_30d || 0),
        parseInt(sup.vence_90d || 0),
        parseInt(sup.vigente || 0)
    ];
    const supColors = ['#dc3545', '#ffc107', '#0dcaf0', '#198754'];

    const ctxSupport = document.getElementById('chart-by-support').getContext('2d');
    chartInstances['chart-by-support'] = new Chart(ctxSupport, {
        type: 'bar',
        data: {
            labels: supLabels,
            datasets: [{
                label: 'CIs en este estado',
                data: supValues,
                backgroundColor: supColors,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => ` ${ctx.parsed.y} CIs (${ctx.label})`
                    }
                }
            },
            scales: {
                x: { ticks: { color: textColor, font: { size: 10 } }, grid: { display: false } },
                y: { ticks: { color: textColor, precision: 0 }, grid: { color: gridColor } }
            }
        }
    });

    // -------------------------------------------------------------
    // GRÁFICO 7: TOP FABRICANTES / VENDORS (Barras Horizontales)
    // -------------------------------------------------------------
    destroyChart('chart-by-vendor');
    const vendorLabels = (data.by_vendor || []).map(i => i.fabricante);
    const vendorTotals = (data.by_vendor || []).map(i => i.total);

    const ctxVendor = document.getElementById('chart-by-vendor').getContext('2d');
    chartInstances['chart-by-vendor'] = new Chart(ctxVendor, {
        type: 'bar',
        data: {
            labels: vendorLabels.length ? vendorLabels : ['Sin datos'],
            datasets: [{
                label: 'CIs por Fabricante',
                data: vendorTotals.length ? vendorTotals : [0],
                backgroundColor: 'rgba(0, 180, 216, 0.85)',
                borderColor: '#00b4d8',
                borderWidth: 1.5,
                borderRadius: 5
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: { ticks: { color: textColor, precision: 0 }, grid: { color: gridColor } },
                y: { ticks: { color: textColor, font: { weight: '600', size: 11 } }, grid: { display: false } }
            }
        }
    });
}

function renderTables(data) {
    // 1. Tabla Clientes
    let htmlClients = '';
    if (!data.by_client || data.by_client.length === 0) {
        htmlClients = '<tr><td colspan="9" class="text-center py-3 text-muted">No hay registros con los filtros seleccionados</td></tr>';
    } else {
        data.by_client.forEach(c => {
            const vencidos = parseInt(c.vencidos || 0);
            const vencidosBadge = vencidos > 0 
                ? `<span class="badge-alarm-titilando"><span class="beacon-alarm-titilando"></span> ${vencidos}</span>` 
                : `<span class="badge badge-success">0</span>`;

            htmlClients += `
                <tr>
                    <td class="font-weight-bold">
                        <i class="fas fa-building text-primary mr-2"></i>${escapeHtml(c.cliente)}
                    </td>
                    <td class="text-center font-weight-bolder">${c.total}</td>
                    <td class="text-center"><span class="badge badge-info">${c.servicios_count}</span></td>
                    <td class="text-center"><span class="badge badge-danger">${c.criticos}</span></td>
                    <td class="text-center">${vencidosBadge}</td>
                    <td class="text-center"><span class="badge badge-warning">${c.por_vencer}</span></td>
                    <td class="text-center"><span class="badge badge-success">${c.vigentes}</span></td>
                    <td class="text-center"><span class="badge badge-secondary">${c.monitoreados}</span></td>
                    <td class="text-center">
                        <button class="btn btn-xs btn-outline-primary" onclick="$('#filter-cliente').val('${escapeJs(c.cliente)}'); onClienteFilterChange();" title="Filtrar por este cliente">
                            <i class="fas fa-filter mr-1"></i> Filtrar
                        </button>
                    </td>
                </tr>
            `;
        });
    }
    $('#tbody-clients-summary').html(htmlClients);

    // 2. Tabla Servicios
    let htmlServices = '';
    if (!data.by_service || data.by_service.length === 0) {
        htmlServices = '<tr><td colspan="8" class="text-center py-3 text-muted">No hay registros con los filtros seleccionados</td></tr>';
    } else {
        data.by_service.forEach(s => {
            const vencidos = parseInt(s.vencidos || 0);
            const healthBadge = vencidos > 0 
                ? `<span class="badge badge-danger"><i class="fas fa-exclamation-circle mr-1"></i> En Riesgo (${vencidos} vencidos)</span>` 
                : `<span class="badge badge-success"><i class="fas fa-check-circle mr-1"></i> Saludable</span>`;

            htmlServices += `
                <tr>
                    <td class="font-weight-bold">
                        <i class="fas fa-sitemap text-indigo mr-2"></i>${escapeHtml(s.servicio)}
                    </td>
                    <td class="text-muted"><i class="fas fa-building mr-1"></i>${escapeHtml(s.cliente)}</td>
                    <td class="text-center font-weight-bolder">${s.total}</td>
                    <td class="text-center"><span class="badge badge-danger">${s.criticos}</span></td>
                    <td class="text-center">${vencidos > 0 ? `<span class="badge badge-danger">${vencidos}</span>` : `<span class="badge badge-success">0</span>`}</td>
                    <td class="text-center"><span class="badge badge-secondary">${s.monitoreados}</span></td>
                    <td class="text-center">${healthBadge}</td>
                    <td class="text-center">
                        <button class="btn btn-xs btn-outline-primary" onclick="$('#filter-servicio').val('${escapeJs(s.servicio)}'); loadAnalyticsData();" title="Filtrar por este servicio">
                            <i class="fas fa-filter mr-1"></i> Filtrar
                        </button>
                    </td>
                </tr>
            `;
        });
    }
    $('#tbody-services-summary').html(htmlServices);

    // 3. Tabla Tipos de CI
    let htmlTypes = '';
    if (!data.by_type || data.by_type.length === 0) {
        htmlTypes = '<tr><td colspan="7" class="text-center py-3 text-muted">No hay registros con los filtros seleccionados</td></tr>';
    } else {
        data.by_type.forEach(t => {
            const total = parseInt(t.total || 0);
            const mon = parseInt(t.monitoreados || 0);
            const pct = total > 0 ? Math.round((mon / total) * 100) : 0;

            htmlTypes += `
                <tr>
                    <td class="font-weight-bold">
                        <i class="fas fa-cube text-info mr-2"></i>${escapeHtml(t.tipo_ci)}
                    </td>
                    <td class="text-center font-weight-bolder">${total}</td>
                    <td class="text-center"><span class="badge badge-danger">${t.criticos}</span></td>
                    <td class="text-center">${parseInt(t.vencidos || 0) > 0 ? `<span class="badge badge-danger">${t.vencidos}</span>` : `<span class="badge badge-success">0</span>`}</td>
                    <td class="text-center"><span class="badge badge-secondary">${mon}</span></td>
                    <td class="text-center">
                        <div class="progress progress-xs mb-1" style="height: 6px;">
                            <div class="progress-bar bg-success" style="width: ${pct}%;"></div>
                        </div>
                        <small class="text-muted font-weight-bold">${pct}%</small>
                    </td>
                    <td class="text-center">
                        <button class="btn btn-xs btn-outline-primary" onclick="$('#filter-tipo-ci').val('${escapeJs(t.tipo_ci)}'); loadAnalyticsData();" title="Filtrar por este tipo">
                            <i class="fas fa-filter mr-1"></i> Filtrar
                        </button>
                    </td>
                </tr>
            `;
        });
    }
    $('#tbody-types-summary').html(htmlTypes);
}

function escapeHtml(text) {
    if (!text) return '';
    return $('<div>').text(text).html();
}

function escapeJs(text) {
    if (!text) return '';
    return text.replace(/'/g, "\\'").replace(/"/g, '\\"');
}
</script>

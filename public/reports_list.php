<?php
/**
 * Panel de Informes - SYNAPSE / CMDB VILASECA
 * Ubicación: public/reports_list.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/permissions_helper.php';

require_login(); 

$page_title = 'Módulo de Informes de Monitoreo';
require_once __DIR__ . '/partials/header.php'; 
?>

<!-- Librerías de Apoyo para Reportes Dinámicos -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.5.2/dist/select2-bootstrap4.min.css">
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<!-- Marked para Renderizado de Markdown de IA -->
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>

<style>
    /* Estilos Generales y Tarjetas del Catálogo */
    .report-card {
        border-radius: 12px;
        border: 1px solid var(--border-color);
        background: var(--card-bg);
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        overflow: hidden;
    }
    .report-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 30px rgba(0,0,0,0.12) !important;
        border-color: var(--sonda-cyan);
    }
    body.dark-mode .report-card {
        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    }
    body.dark-mode .report-card:hover {
        box-shadow: 0 8px 30px rgba(0, 184, 212, 0.2) !important;
    }
    .icon-wrapper {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        margin-bottom: 20px;
        background: rgba(255, 92, 5, 0.1);
        color: var(--sonda-orange);
    }
    .icon-wrapper-cyan {
        background: rgba(0, 184, 212, 0.1);
        color: var(--sonda-cyan);
    }
    .icon-wrapper-green {
        background: rgba(192, 218, 32, 0.1);
        color: var(--sonda-green);
    }
    .report-title {
        font-size: 1.25rem;
        font-weight: 700;
        margin-bottom: 10px;
        color: var(--text-color);
    }
    .report-description {
        font-size: 0.9rem;
        color: var(--text-muted);
        line-height: 1.5;
        flex-grow: 1;
        margin-bottom: 20px;
    }
    .card-footer-action {
        border-top: 1px solid var(--border-color);
        padding-top: 15px;
        background: transparent;
    }

    /* Pestañas de Navegación Personalizadas */
    .custom-report-nav {
        background: #fff;
        padding: 8px;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        border: 1px solid #e9ecef;
    }
    .dark-mode .custom-report-nav {
        background: #343a40;
        border-color: #495057;
    }
    .custom-report-nav .nav-link {
        border-radius: 8px;
        font-weight: 600;
        color: #495057;
        padding: 10px 22px;
        transition: all 0.25s ease;
    }
    .dark-mode .custom-report-nav .nav-link {
        color: #ced4da;
    }
    .custom-report-nav .nav-link.active {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        color: #fff !important;
        box-shadow: 0 4px 12px rgba(0, 123, 255, 0.35);
    }
    .custom-report-nav .nav-link.tab-creacion.active {
        background: linear-gradient(135deg, #17a2b8 0%, #007bff 100%);
    }

    /* Formularios y Creador */
    .filter-card-header {
        background: linear-gradient(135deg, #2b3a4a 0%, #1e2936 100%);
        color: #fff;
        padding: 18px 24px;
        border-radius: 12px 12px 0 0;
    }
    .creator-section-title {
        font-size: 0.9rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #495057;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
    }
    .dark-mode .creator-section-title {
        color: #adb5bd;
    }
    .creator-section-title i {
        margin-right: 8px;
        color: #007bff;
    }

    /* Interfaces Cards / Accordions */
    .host-interface-card {
        border: 1px solid #dee2e6;
        border-radius: 10px;
        margin-bottom: 15px;
        background: #fff;
        transition: all 0.2s ease;
    }
    .dark-mode .host-interface-card {
        background: #2b3035;
        border-color: #495057;
    }
    .host-header {
        padding: 14px 18px;
        background: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
        border-radius: 10px 10px 0 0;
        cursor: pointer;
    }
    .dark-mode .host-header {
        background: #343a40;
        border-color: #495057;
    }
    .iface-badge-metric {
        font-size: 0.72rem;
        padding: 3px 8px;
        border-radius: 12px;
        margin-right: 4px;
        font-weight: 600;
    }
    .badge-in { background-color: #e3f2fd; color: #0d47a1; }
    .badge-out { background-color: #e8f5e9; color: #1b5e20; }
    .badge-err { background-color: #ffebee; color: #b71c1c; }

    /* Visor de Informe Corporativo */
    .report-viewer-container {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        border: 1px solid #e2e8f0;
        padding: 35px;
        margin-top: 25px;
    }
    .dark-mode .report-viewer-container {
        background: #1f2429;
        border-color: #3b4249;
    }
    .report-header-banner {
        border-bottom: 2px solid #007bff;
        padding-bottom: 20px;
        margin-bottom: 25px;
    }
    .report-kpi-box {
        border-radius: 12px;
        padding: 18px;
        text-align: center;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
    }
    .dark-mode .report-kpi-box {
        background: #2b3035;
        border-color: #495057;
    }
    .report-kpi-val {
        font-size: 1.8rem;
        font-weight: 800;
        color: #007bff;
        line-height: 1.2;
    }
    .report-kpi-lbl {
        font-size: 0.8rem;
        text-transform: uppercase;
        font-weight: 600;
        color: #64748b;
        margin-top: 5px;
    }
    .ai-analysis-box {
        background: linear-gradient(145deg, #f0f7ff 0%, #e8f4fd 100%);
        border: 1px solid #b8daff;
        border-left: 6px solid #007bff;
        border-radius: 10px;
        padding: 22px;
        margin-bottom: 25px;
    }
    .dark-mode .ai-analysis-box {
        background: linear-gradient(145deg, #1e293b 0%, #0f172a 100%);
        border-color: #1e3a8a;
        border-left-color: #38bdf8;
    }
    .chart-container-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
    }
    .dark-mode .chart-container-card {
        background: #2b3035;
        border-color: #495057;
    }

    /* Print Stylesheet */
    @media print {
        body { background: #fff !important; color: #000 !important; }
        .main-header, .main-sidebar, .main-footer, .custom-report-nav, #report-creator-form, .btn-no-print, .actions-toolbar {
            display: none !important;
        }
        .content-wrapper { margin: 0 !important; padding: 0 !important; background: #fff !important; }
        .container-fluid { width: 100% !important; padding: 0 !important; }
        .report-viewer-container {
            box-shadow: none !important;
            border: none !important;
            padding: 0 !important;
            margin: 0 !important;
        }
        .chart-container-card {
            border: 1px solid #ccc !important;
            page-break-inside: avoid !important;
        }
        .host-interface-card {
            page-break-inside: avoid !important;
        }
    }
</style>

<div class="container-fluid pt-4">
    <!-- Header Principal -->
    <div class="row mb-3 animate__animated animate__fadeIn">
        <div class="col-12">
            <div class="d-flex align-items-center justify-content-between flex-wrap">
                <div>
                    <h1 class="h3 font-weight-bold text-dark mb-1">
                        <i class="fas fa-file-invoice mr-2 text-primary"></i> Módulo de Informes de Monitoreo
                    </h1>
                    <p class="text-muted mb-0">Gestión, creación y análisis telemétrico avanzado de enlaces e infraestructura con Zabbix y soporte de IA.</p>
                </div>
                <div class="mt-2 mt-md-0">
                    <span class="badge badge-success px-3 py-2 shadow-sm">
                        <i class="fas fa-check-circle mr-1"></i> Zabbix API 7.0 Conectado
                    </span>
                    <button class="btn btn-outline-info btn-sm ml-2 shadow-sm" id="btn-open-ai-modal">
                        <i class="fas fa-robot mr-1"></i> Configurar IA
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Pestañas de Navegación -->
    <div class="row mb-4">
        <div class="col-12">
            <ul class="nav nav-pills custom-report-nav" id="reportTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="tab-catalogo-link" data-toggle="pill" href="#tab-catalogo" role="tab">
                        <i class="fas fa-th-large mr-2 text-primary"></i> Catálogo de Informes
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link tab-creacion" id="tab-creacion-link" data-toggle="pill" href="#tab-creacion" role="tab">
                        <i class="fas fa-magic mr-2 text-warning"></i> Creación de Informe (Tráfico e Interfaces)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="tab-guardados-link" data-toggle="pill" href="#tab-guardados" role="tab">
                        <i class="fas fa-history mr-2 text-info"></i> Informes Guardados (<span id="saved-reports-count">0</span>)
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Contenido de las Pestañas -->
    <div class="tab-content" id="reportTabsContent">

        <!-- ============================================================== -->
        <!-- PESTAÑA 1: CATÁLOGO DE INFORMES EXISTENTES                     -->
        <!-- ============================================================== -->
        <div class="tab-pane fade show active" id="tab-catalogo" role="tabpanel">
            <div class="row">
                <!-- Report 1: Availability by Groups -->
                <div class="col-lg-3 col-md-6 mb-4 animate__animated animate__fadeInLeft">
                    <div class="report-card p-4">
                        <div>
                            <div class="icon-wrapper">
                                <i class="fas fa-layer-group"></i>
                            </div>
                            <h5 class="report-title">Disponibilidad-Ping (Por Grupos)</h5>
                            <p class="report-description">
                                Informe consolidado que calcula el porcentaje de disponibilidad promedio de los principales canales o grupos de hosts utilizando estados de trigger de ping ICMP. Permite exportar a PDF y visualizar comparaciones en gráficos.
                            </p>
                        </div>
                        <div class="card-footer-action">
                            <a href="informes/grupos.php" class="btn btn-block btn-primary">
                                <i class="fas fa-chart-bar mr-2"></i> Abrir Informe
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Report 2: Availability by Hosts -->
                <div class="col-lg-3 col-md-6 mb-4 animate__animated animate__fadeInUp">
                    <div class="report-card p-4">
                        <div>
                            <div class="icon-wrapper icon-wrapper-cyan">
                                <i class="fas fa-desktop"></i>
                            </div>
                            <h5 class="report-title">Disponibilidad-Ping (Por Equipos)</h5>
                            <p class="report-description">
                                Análisis detallado día por día de cada host perteneciente a un grupo seleccionado. Permite identificar de manera delgada fallos en hosts específicos, tiempos caídos y lista completa de eventos de caída por ICMP ping.
                            </p>
                        </div>
                        <div class="card-footer-action">
                            <a href="informes/index.php" class="btn btn-block btn-info">
                                <i class="fas fa-search-plus mr-2"></i> Abrir Informe
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Report 3: Alarm Distribution by Hosts -->
                <div class="col-lg-3 col-md-6 mb-4 animate__animated animate__fadeInUp">
                    <div class="report-card p-4">
                        <div>
                            <div class="icon-wrapper" style="background: rgba(220, 53, 69, 0.1); color: var(--danger);">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <h5 class="report-title">Distribución de Alarmas</h5>
                            <p class="report-description">
                                Informe cuantitativo de alarmas por equipo y tipo. Visualice la cantidad de eventos y su severidad (Advertencia, Promedio, Alta, Desastre) en gráficos interactivos distribuidos.
                            </p>
                        </div>
                        <div class="card-footer-action">
                            <a href="informes/alarmas.php" class="btn btn-block btn-danger">
                                <i class="fas fa-chart-pie mr-2"></i> Abrir Informe
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Report 4: Scope & Templates Monitoring -->
                <div class="col-lg-3 col-md-6 mb-4 animate__animated animate__fadeInUp">
                    <div class="report-card p-4">
                        <div>
                            <div class="icon-wrapper" style="background: rgba(156, 39, 176, 0.1); color: #9c27b0;">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <h5 class="report-title">Alcance y Plantillas</h5>
                            <p class="report-description">
                                Informe integral que clasifica los dispositivos por tipo (routers, switches, servidores, etc.) y estado. Incluye consulta de métricas por equipo y las alertas de la plantilla principal más utilizada.
                            </p>
                        </div>
                        <div class="card-footer-action">
                            <a href="informes/alcance.php" class="btn btn-block text-white" style="background-color: #9c27b0; border-color: #9c27b0;">
                                <i class="fas fa-tasks mr-2"></i> Abrir Informe
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Report 5: Existing Customized Technical Reports -->
                <div class="col-lg-3 col-md-6 mb-4 animate__animated animate__fadeInRight">
                    <div class="report-card p-4">
                        <div>
                            <div class="icon-wrapper icon-wrapper-green">
                                <i class="fas fa-file-invoice"></i>
                            </div>
                            <h5 class="report-title">Reportes Técnicos Personalizados</h5>
                            <p class="report-description">
                                Generador interactivo basado en consultas generales a Zabbix. Filtre por nombres, hostgroups, tags y tipos de verificaciones de estado (ICMP, SNMP, Agent) para generar reportes dinámicos.
                            </p>
                        </div>
                        <div class="card-footer-action">
                            <a href="reports_zabbix.php" class="btn btn-block btn-dark" style="background-color: var(--sonda-navy); border-color: var(--sonda-navy);">
                                <i class="fas fa-file-excel mr-2"></i> Abrir Generador
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Nuevo Acceso Rápido al Creador -->
                <div class="col-lg-3 col-md-6 mb-4 animate__animated animate__fadeInRight">
                    <div class="report-card p-4" style="border: 2px dashed #007bff; background: rgba(0, 123, 255, 0.03);">
                        <div>
                            <div class="icon-wrapper" style="background: rgba(0, 123, 255, 0.1); color: #007bff;">
                                <i class="fas fa-magic"></i>
                            </div>
                            <h5 class="report-title text-primary">Creación de Informe de Interfaces</h5>
                            <p class="report-description">
                                Nuevo creador modular con selección jerárquica de Grupos, Hosts e Interfaces de red. Obtiene métricas horarias/diarias de Trends (Tráfico In/Out y Errores), alarmas del período y diagnóstico automatizado con IA.
                            </p>
                        </div>
                        <div class="card-footer-action">
                            <button type="button" class="btn btn-block btn-primary shadow-sm" onclick="$('#tab-creacion-link').tab('show');">
                                <i class="fas fa-plus-circle mr-2"></i> Crear Nuevo Informe
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- PESTAÑA 2: CREACIÓN DE INFORME (NUEVO MÓDULO SOLICITADO)       -->
        <!-- ============================================================== -->
        <div class="tab-pane fade" id="tab-creacion" role="tabpanel">
            
            <!-- FORMULARIO DE FILTROS Y CONFIGURACIÓN -->
            <div class="card shadow-sm border-0 mb-4" id="report-creator-form">
                <div class="filter-card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-1 font-weight-bold"><i class="fas fa-sliders-h mr-2"></i> Filtros y Parámetros del Informe</h4>
                        <p class="mb-0 small opacity-8">Defina los grupos, equipos, interfaces y ventana temporal a consultar en Zabbix.</p>
                    </div>
                    <button class="btn btn-light btn-sm text-dark font-weight-bold shadow-sm" id="btn-reset-filters">
                        <i class="fas fa-undo mr-1"></i> Restablecer Filtros
                    </button>
                </div>
                
                <div class="card-body p-4">
                    <!-- Fila 1: Grupos y Hosts -->
                    <div class="row">
                        <div class="col-lg-6 mb-3">
                            <label class="creator-section-title">
                                <i class="fas fa-layer-group"></i> 1. Grupos de Zabbix (Hostgroups)
                            </label>
                            <select id="select-groups" class="form-control select2bs4" multiple="multiple" style="width: 100%;">
                                <!-- Dinámico vía AJAX -->
                            </select>
                            <small class="form-text text-muted">Seleccione uno o más grupos para cargar automáticamente los equipos asociados.</small>
                        </div>

                        <div class="col-lg-6 mb-3">
                            <label class="creator-section-title">
                                <i class="fas fa-server"></i> 2. Equipos (Hosts por Grupo Seleccionado)
                            </label>
                            <select id="select-hosts" class="form-control select2bs4" multiple="multiple" style="width: 100%;" disabled>
                                <!-- Dinámico vía AJAX -->
                            </select>
                            <small class="form-text text-muted" id="hosts-help-text">Primero seleccione al menos un grupo de Zabbix.</small>
                        </div>
                    </div>

                    <hr class="my-3">

                    <!-- Fila 2: Ventana de Tiempo y Título -->
                    <div class="row">
                        <div class="col-lg-6 mb-3">
                            <label class="creator-section-title">
                                <i class="fas fa-calendar-alt"></i> 3. Rango de Fechas / Período del Informe
                            </label>
                            <div class="row">
                                <div class="col-sm-6 mb-2">
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text font-weight-bold">Desde</span></div>
                                        <input type="datetime-local" id="date-from" class="form-control">
                                    </div>
                                </div>
                                <div class="col-sm-6 mb-2">
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text font-weight-bold">Hasta</span></div>
                                        <input type="datetime-local" id="date-to" class="form-control">
                                    </div>
                                </div>
                            </div>
                            <!-- Botones Rápidos de Fecha -->
                            <div class="d-flex flex-wrap mt-2" style="gap: 5px;">
                                <button type="button" class="btn btn-xs btn-outline-secondary btn-range" data-days="1">Últimas 24h</button>
                                <button type="button" class="btn btn-xs btn-outline-secondary btn-range active" data-days="7">Últimos 7 días</button>
                                <button type="button" class="btn btn-xs btn-outline-secondary btn-range" data-days="15">Últimos 15 días</button>
                                <button type="button" class="btn btn-xs btn-outline-secondary btn-range" data-days="30">Últimos 30 días</button>
                                <button type="button" class="btn btn-xs btn-outline-secondary btn-range" data-range="month">Mes Actual</button>
                            </div>
                        </div>

                        <div class="col-lg-6 mb-3">
                            <label class="creator-section-title">
                                <i class="fas fa-heading"></i> 4. Título y Modalidad del Informe
                            </label>
                            <input type="text" id="report-title-input" class="form-control form-control-sm mb-2" value="Informe de Rendimiento y Tráfico de Enlaces de Red" placeholder="Título descriptivo del informe">
                            <div class="d-flex align-items-center flex-wrap" style="gap: 15px;">
                                <label class="small font-weight-bold mb-0 text-muted">Estructura a Generar:</label>
                                <div class="custom-control custom-radio">
                                    <input type="radio" id="mode-both" name="report_type_mode" class="custom-control-input" value="both" checked>
                                    <label class="custom-control-label small font-weight-bold" for="mode-both">Consolidado + Individual</label>
                                </div>
                                <div class="custom-control custom-radio">
                                    <input type="radio" id="mode-cons" name="report_type_mode" class="custom-control-input" value="consolidated">
                                    <label class="custom-control-label small font-weight-bold" for="mode-cons">Solo Consolidado Total</label>
                                </div>
                                <div class="custom-control custom-radio">
                                    <input type="radio" id="mode-ind" name="report_type_mode" class="custom-control-input" value="individual">
                                    <label class="custom-control-label small font-weight-bold" for="mode-ind">Solo por Equipo</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-3">

                    <!-- Fila 3: IA y Observaciones Generales -->
                    <div class="row">
                        <div class="col-lg-5 mb-3">
                            <label class="creator-section-title">
                                <i class="fas fa-brain"></i> 5. Análisis con Inteligencia Artificial (IA)
                            </label>
                            <div class="input-group input-group-sm mb-2">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-microchip"></i></span>
                                </div>
                                <select id="select-ai-provider" class="form-control">
                                    <option value="gemini">Google Gemini (Cloud AI Inteligente)</option>
                                    <option value="ollama">Ollama (Servidor Local / On-Premise)</option>
                                    <option value="none">Motor Experto Heurístico SYNAPSE (Sin IA externa)</option>
                                </select>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small text-muted" id="ai-status-indicator">
                                    <i class="fas fa-info-circle text-info mr-1"></i> Conexión lista
                                </span>
                                <button type="button" class="btn btn-xs btn-outline-info" id="btn-quick-config-ai">
                                    <i class="fas fa-sliders-h mr-1"></i> Opciones de Modelo
                                </button>
                            </div>
                        </div>

                        <div class="col-lg-7 mb-3">
                            <label class="creator-section-title">
                                <i class="fas fa-comment-alt"></i> 6. Observaciones Generales del Informe
                            </label>
                            <textarea id="general-observations-input" class="form-control form-control-sm" rows="2" placeholder="Ingrese notas ejecutivas, contexto del cliente, motivos de la auditoría o comentarios relevantes..."></textarea>
                        </div>
                    </div>

                    <hr class="my-3">

                    <!-- SECCIÓN DINÁMICA: SELECCIÓN DE INTERFACES POR CADA EQUIPO -->
                    <div class="row">
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <label class="creator-section-title mb-0">
                                    <i class="fas fa-network-wired text-success"></i> 7. Interfaces de Red por Cada Equipo Seleccionado
                                </label>
                                <div id="interfaces-global-actions" class="d-none">
                                    <button type="button" class="btn btn-xs btn-outline-primary mr-1" id="btn-select-all-ifaces">
                                        <i class="fas fa-check-double mr-1"></i> Marcar Todas
                                    </button>
                                    <button type="button" class="btn btn-xs btn-outline-secondary" id="btn-deselect-all-ifaces">
                                        <i class="fas fa-times mr-1"></i> Desmarcar Todas
                                    </button>
                                </div>
                            </div>

                            <!-- Estado Vacío Inicial -->
                            <div id="interfaces-empty-state" class="text-center py-5 border rounded bg-light">
                                <i class="fas fa-network-wired fa-3x text-muted opacity-5 mb-3"></i>
                                <h6 class="font-weight-bold text-dark">No se han seleccionado equipos</h6>
                                <p class="text-muted small mb-0">Elija uno o más grupos y equipos en los filtros superiores para desplegar sus interfaces de red detectadas.</p>
                            </div>

                            <!-- Spinner de Carga de Interfaces -->
                            <div id="interfaces-loading-state" class="text-center py-5 border rounded bg-light d-none">
                                <div class="spinner-border text-primary" role="status"></div>
                                <h6 class="font-weight-bold text-dark mt-3">Consultando interfaces en Zabbix...</h6>
                                <p class="text-muted small mb-0">Detectando ítems de tráfico de entrada, tráfico de salida y errores por puerto.</p>
                            </div>

                            <!-- Contenedor Dinámico de Interfaces por Equipo -->
                            <div id="interfaces-accordion-container" class="d-none">
                                <!-- Se inyectan tarjetas por cada equipo mediante JS -->
                            </div>
                        </div>
                    </div>

                    <!-- BOTÓN PRINCIPAL DE GENERACIÓN -->
                    <div class="row mt-4 pt-3 border-top">
                        <div class="col-12 text-center">
                            <div class="d-inline-flex align-items-center flex-column">
                                <div class="custom-control custom-checkbox mb-2">
                                    <input type="checkbox" class="custom-control-input" id="chk-save-report-db" checked>
                                    <label class="custom-control-label small font-weight-bold text-dark" for="chk-save-report-db">
                                        <i class="fas fa-database text-primary mr-1"></i> Guardar automáticamente este informe en la base de datos para consulta posterior
                                    </label>
                                </div>
                                <button type="button" class="btn btn-primary btn-lg shadow-lg px-5 py-3 font-weight-bold" id="btn-generar-informe" disabled>
                                    <i class="fas fa-play mr-2"></i> GENERAR Y VISUALIZAR INFORME
                                </button>
                                <span class="small text-muted mt-2" id="generate-btn-hint">Seleccione al menos una interfaz de red para habilitar la generación.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SPINNER DE GENERACIÓN DE REPORTE -->
            <div id="report-generating-spinner" class="text-center py-5 d-none">
                <div class="spinner-grow text-primary mb-3" style="width: 3.5rem; height: 3.5rem;" role="status"></div>
                <h4 class="font-weight-bold text-dark">Compilando Informe de Rendimiento...</h4>
                <p class="text-muted">Consultando métricas de Trends en Zabbix, calculando estadísticas horarias/diarias y ejecutando análisis de IA.</p>
            </div>

            <!-- ============================================================== -->
            <!-- VISOR DEL INFORME GENERADO (RESULTADOS EN PANTALLA)            -->
            <!-- ============================================================== -->
            <div id="report-viewer-section" class="d-none">
                <!-- Barra de Acciones Superior -->
                <div class="d-flex justify-content-between align-items-center flex-wrap mb-3 p-3 bg-white rounded shadow-sm actions-toolbar border">
                    <button class="btn btn-outline-secondary font-weight-bold" id="btn-back-to-filters">
                        <i class="fas fa-arrow-left mr-1"></i> Modificar Parámetros / Filtros
                    </button>
                    <div class="d-flex flex-wrap" style="gap: 8px;">
                        <button class="btn btn-success font-weight-bold shadow-sm" id="btn-save-current-report">
                            <i class="fas fa-save mr-1"></i> <span id="save-btn-text">Guardar en Base de Datos</span>
                        </button>
                        <button class="btn btn-danger font-weight-bold shadow-sm" id="btn-print-report">
                            <i class="fas fa-print mr-1"></i> Imprimir / PDF
                        </button>
                        <button class="btn btn-dark font-weight-bold shadow-sm" id="btn-export-csv">
                            <i class="fas fa-file-csv mr-1"></i> Exportar Datos (CSV)
                        </button>
                    </div>
                </div>

                <!-- Contenedor Imprimible del Informe -->
                <div class="report-viewer-container" id="printable-report-area">
                    <!-- Membrete Corporativo -->
                    <div class="report-header-banner d-flex justify-content-between align-items-center flex-wrap">
                        <div class="d-flex align-items-center mb-2 mb-md-0">
                            <div style="background: linear-gradient(135deg, #007bff, #0056b3); width: 54px; height: 54px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 26px; font-weight: bold; margin-right: 18px;">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div>
                                <h3 class="font-weight-bold text-dark mb-1" id="view-report-title">Informe de Rendimiento y Tráfico de Red</h3>
                                <p class="text-muted mb-0 small">
                                    <i class="fas fa-building mr-1"></i> Plataforma SYNAPSE - SONDA Monitoreo NOC | 
                                    <i class="fas fa-calendar-alt mr-1 ml-2"></i> Período: <span class="font-weight-bold text-dark" id="view-report-period">--</span>
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="badge badge-primary px-3 py-2 font-weight-bold mb-1" id="view-report-status-badge">
                                INFORME TÉCNICO OFICIAL
                            </span>
                            <div class="small text-muted">Generado: <strong id="view-report-generated-at">--</strong></div>
                            <div class="small text-muted">Por: <strong id="view-report-user">--</strong></div>
                        </div>
                    </div>

                    <!-- Fila de KPIs Globales -->
                    <div class="row mb-4" id="kpi-cards-row">
                        <div class="col-md-2 col-6 mb-2">
                            <div class="report-kpi-box">
                                <div class="report-kpi-val" id="kpi-hosts">0</div>
                                <div class="report-kpi-lbl">Equipos</div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6 mb-2">
                            <div class="report-kpi-box">
                                <div class="report-kpi-val text-info" id="kpi-interfaces">0</div>
                                <div class="report-kpi-lbl">Interfaces</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-2">
                            <div class="report-kpi-box">
                                <div class="report-kpi-val text-success" id="kpi-peak-in">0 bps</div>
                                <div class="report-kpi-lbl">Pico Tráfico IN</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-2">
                            <div class="report-kpi-box">
                                <div class="report-kpi-val text-primary" id="kpi-peak-out">0 bps</div>
                                <div class="report-kpi-lbl">Pico Tráfico OUT</div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6 mb-2">
                            <div class="report-kpi-box">
                                <div class="report-kpi-val text-danger" id="kpi-errors">0</div>
                                <div class="report-kpi-lbl">Total Errores</div>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN: ANÁLISIS DE INTELIGENCIA ARTIFICIAL -->
                    <div class="ai-analysis-box mb-4" id="view-ai-section">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="font-weight-bold mb-0 text-primary d-flex align-items-center">
                                <i class="fas fa-robot mr-2"></i> Diagnóstico y Recomendaciones Ejecutivas con IA
                            </h5>
                            <span class="badge badge-light border px-2 py-1 small font-weight-bold" id="view-ai-model-badge">
                                Gemini 1.5 Flash
                            </span>
                        </div>
                        <div class="ai-analysis-content" id="view-ai-text">
                            <!-- Inyectado vía JS con render Markdown -->
                        </div>
                    </div>

                    <!-- SECCIÓN: ALARMAS Y EVENTOS DEL PERÍODO -->
                    <div class="chart-container-card mb-4" id="view-alarms-section">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="font-weight-bold mb-0 text-dark">
                                <i class="fas fa-exclamation-triangle text-warning mr-2"></i> Alarmas y Problemas Registrados en Zabbix durante el Período
                            </h5>
                            <span class="badge badge-secondary px-2 py-1" id="view-alarms-count-badge">0 Alarmas</span>
                        </div>
                        <div id="view-alarms-content">
                            <!-- Inyectado vía JS -->
                        </div>
                    </div>

                    <!-- SECCIÓN: INFORME TOTAL CONSOLIDADO -->
                    <div id="view-consolidated-section" class="mb-5">
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                            <h4 class="font-weight-bold text-dark mb-0">
                                <i class="fas fa-chart-area text-info mr-2"></i> Informe Consolidado Total (Todos los Equipos)
                            </h4>
                            <span class="badge badge-info px-3 py-2">Métricas Combinadas</span>
                        </div>

                        <!-- Gráfico Consolidado de Ancho de Banda -->
                        <div class="chart-container-card mb-4">
                            <h6 class="font-weight-bold text-dark mb-3">
                                <i class="fas fa-chart-line mr-1 text-primary"></i> Tráfico Diario Combinado (Mbps - Inbound vs Outbound)
                            </h6>
                            <div style="height: 320px; position: relative;">
                                <canvas id="chart-consolidated-daily"></canvas>
                            </div>
                        </div>

                        <!-- Gráfico Consolidado de Errores -->
                        <div class="chart-container-card mb-4">
                            <h6 class="font-weight-bold text-dark mb-3">
                                <i class="fas fa-bug mr-1 text-danger"></i> Errores de Red Acumulados por Día (In / Out)
                            </h6>
                            <div style="height: 250px; position: relative;">
                                <canvas id="chart-consolidated-errors"></canvas>
                            </div>
                        </div>

                        <!-- Tabla Resumen Consolidada de Interfaces -->
                        <div class="chart-container-card mb-4">
                            <h6 class="font-weight-bold text-dark mb-3">
                                <i class="fas fa-table mr-1 text-secondary"></i> Matriz Comparativa de Interfaces Analizadas
                            </h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped table-bordered text-center mb-0" id="table-consolidated-summary">
                                    <thead class="thead-dark small">
                                        <tr>
                                            <th>Equipo</th>
                                            <th>Interfaz</th>
                                            <th>Alias / Descripción</th>
                                            <th>Capacidad</th>
                                            <th>Promedio IN</th>
                                            <th>Pico IN</th>
                                            <th>Promedio OUT</th>
                                            <th>Pico OUT</th>
                                            <th>Errores IN</th>
                                            <th>Errores OUT</th>
                                            <th>Utilización Pico</th>
                                            <th>Observación</th>
                                        </tr>
                                    </thead>
                                    <tbody class="small" id="tbody-consolidated-summary">
                                        <!-- Inyectado vía JS -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN: INFORME INDIVIDUAL POR EQUIPO -->
                    <div id="view-individual-section" class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                            <h4 class="font-weight-bold text-dark mb-0">
                                <i class="fas fa-desktop text-success mr-2"></i> Detalle Individual por Equipo e Interfaces
                            </h4>
                            <span class="badge badge-success px-3 py-2">Desglose Detallado</span>
                        </div>

                        <!-- Contenedor dinámico de equipos individuales -->
                        <div id="view-individual-hosts-container">
                            <!-- Inyectado dinámicamente vía JS -->
                        </div>
                    </div>

                    <!-- SECCIÓN: OBSERVACIONES GENERALES FINALES -->
                    <div class="card border rounded p-4 bg-light mb-4" id="view-observations-card">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="font-weight-bold text-dark mb-0">
                                <i class="fas fa-sticky-note text-warning mr-2"></i> Observaciones Generales Registradas
                            </h6>
                            <button class="btn btn-xs btn-outline-primary btn-no-print" id="btn-edit-observations">
                                <i class="fas fa-edit mr-1"></i> Modificar Observación
                            </button>
                        </div>
                        <p class="text-dark mb-0 font-italic" id="view-general-observations-text">
                            Sin observaciones adicionales registradas.
                        </p>
                    </div>

                    <!-- Pie del Informe -->
                    <div class="text-center pt-4 border-top text-muted small">
                        <p class="mb-1"><strong>SYNAPSE Monitoring System</strong> - CMDB VILASECA / SONDA NOC.</p>
                        <p class="mb-0">Este documento fue generado automáticamente a partir de datos telemétricos de Zabbix API Trends y procesado bajo estándares de auditoría de red.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- PESTAÑA 3: HISTORIAL DE INFORMES GUARDADOS                     -->
        <!-- ============================================================== -->
        <div class="tab-pane fade" id="tab-guardados" role="tabpanel">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-history text-info mr-2"></i> Historial de Informes Generados y Guardados</h5>
                        <p class="mb-0 small text-muted">Consulte, descargue, imprima o elimine informes almacenados previamente.</p>
                    </div>
                    <button class="btn btn-outline-primary btn-sm" id="btn-refresh-saved-list">
                        <i class="fas fa-sync-alt mr-1"></i> Actualizar Lista
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0 text-center" id="table-saved-reports">
                            <thead class="thead-light small font-weight-bold">
                                <tr>
                                    <th>ID</th>
                                    <th class="text-left">Título del Informe</th>
                                    <th>Período Analizado</th>
                                    <th>Modalidad</th>
                                    <th>Motor IA</th>
                                    <th>Fecha de Generación</th>
                                    <th>Generado Por</th>
                                    <th style="min-width: 180px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-saved-reports">
                                <tr>
                                    <td colspan="8" class="py-4 text-muted">
                                        <div class="spinner-border spinner-border-sm text-primary mr-2" role="status"></div>
                                        Cargando historial de informes guardados...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ============================================================== -->
<!-- MODAL: CONFIGURACIÓN DE INTELIGENCIA ARTIFICIAL (GEMINI / OLLAMA)-->
<!-- ============================================================== -->
<div class="modal fade" id="modalConfigAI" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
            <div class="modal-header bg-dark text-white" style="border-radius: 14px 14px 0 0;">
                <h5 class="modal-title font-weight-bold">
                    <i class="fas fa-robot text-info mr-2"></i> Configuración de Conexión IA
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4">
                <p class="small text-muted mb-3">
                    Configure las credenciales de <strong>Google Gemini</strong> o el endpoint de <strong>Ollama</strong> para habilitar diagnósticos automatizados en los informes.
                </p>

                <!-- Proveedor por Defecto -->
                <div class="form-group">
                    <label class="small font-weight-bold">Proveedor de IA Preferido</label>
                    <select id="cfg-ai-default" class="form-control form-control-sm">
                        <option value="gemini">Google Gemini (Cloud AI)</option>
                        <option value="ollama">Ollama (Servidor Local)</option>
                    </select>
                </div>

                <hr>

                <!-- Sección Gemini -->
                <h6 class="font-weight-bold text-primary mb-2"><i class="fab fa-google mr-1"></i> Google Gemini</h6>
                <div class="form-group mb-2">
                    <label class="small font-weight-bold mb-1">Gemini API Key</label>
                    <div class="input-group input-group-sm">
                        <input type="password" id="cfg-gemini-key" class="form-control" placeholder="AIzaSy...">
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" id="btn-toggle-gemini-key">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="form-group mb-2">
                    <label class="small font-weight-bold mb-1">Modelo de Gemini</label>
                    <select id="cfg-gemini-model" class="form-control form-control-sm">
                        <option value="gemini-1.5-flash">gemini-1.5-flash (Rápido y Óptimo)</option>
                        <option value="gemini-2.5-flash">gemini-2.5-flash (Alta Precisión)</option>
                        <option value="gemini-1.5-pro">gemini-1.5-pro (Avanzado)</option>
                    </select>
                </div>
                <button type="button" class="btn btn-xs btn-outline-primary mb-3" id="btn-test-gemini">
                    <i class="fas fa-vial mr-1"></i> Probar Conexión con Gemini
                </button>

                <hr>

                <!-- Sección Ollama -->
                <h6 class="font-weight-bold text-success mb-2"><i class="fas fa-server mr-1"></i> Ollama (Local)</h6>
                <div class="form-group mb-2">
                    <label class="small font-weight-bold mb-1">URL Endpoint de Ollama</label>
                    <input type="text" id="cfg-ollama-url" class="form-control form-control-sm" value="http://localhost:11434" placeholder="http://172.32.1.50:11434">
                </div>
                <div class="form-group mb-2">
                    <label class="small font-weight-bold mb-1">Modelo Instalado en Ollama</label>
                    <input type="text" id="cfg-ollama-model" class="form-control form-control-sm" value="llama3:latest" placeholder="ej: llama3:latest, mistral">
                </div>
                <button type="button" class="btn btn-xs btn-outline-success mb-2" id="btn-test-ollama">
                    <i class="fas fa-vial mr-1"></i> Probar Conexión con Ollama
                </button>
            </div>
            <div class="modal-footer bg-light p-3">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm font-weight-bold" id="btn-save-ai-settings">
                    <i class="fas fa-save mr-1"></i> Guardar Configuración
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================== -->
<!-- JAVASCRIPT DEL CREADOR DE INFORMES                             -->
<!-- ============================================================== -->
<script>
$(document).ready(function() {
    // Variables de Estado Globales
    let allGroups = [];
    let currentHosts = [];
    let discoveredInterfaces = {}; // hostid => [ {ifname, alias, traffic_in, traffic_out, errors_in, errors_out, speed...} ]
    let selectedInterfacesMap = {}; // hostid => { ifname => if_object }
    let interfaceObservations = {}; // 'hostid_ifname' => text
    let activeCharts = {}; // id => Chart instance
    let currentCompiledReport = null; // Reporte actualmente generado o cargado

    // Inicializar Select2 con Tema Bootstrap 4
    $('.select2bs4').select2({
        theme: 'bootstrap4',
        allowClear: true
    });

    // Cargar Fechas Iniciales (Últimos 7 días por defecto)
    setQuickDateRange(7);

    // Cargar Grupos de Zabbix y Configuración Inicial
    loadZabbixGroups();
    loadAIConfig();
    loadSavedReportsList();

    // -------------------------------------------------------------
    // EVENTOS DE FILTROS SUPERIORES
    // -------------------------------------------------------------

    // Cambio en Grupos Seleccionados -> Cargar Hosts asociados
    $('#select-groups').on('change', function() {
        const selectedGroupIds = $(this).val();
        if (selectedGroupIds && selectedGroupIds.length > 0) {
            loadHostsForGroups(selectedGroupIds);
        } else {
            $('#select-hosts').empty().prop('disabled', true).trigger('change');
            $('#hosts-help-text').text('Primero seleccione al menos un grupo de Zabbix.');
            resetInterfacesSection();
        }
    });

    // Cambio en Equipos Seleccionados -> Cargar Interfaces de Red
    $('#select-hosts').on('change', function() {
        const selectedHostIds = $(this).val();
        if (selectedHostIds && selectedHostIds.length > 0) {
            loadInterfacesForHosts(selectedHostIds);
        } else {
            resetInterfacesSection();
        }
    });

    // Botones Rápidos de Fecha
    $('.btn-range').on('click', function() {
        $('.btn-range').removeClass('active');
        $(this).addClass('active');

        const range = $(this).data('range');
        if (range === 'month') {
            const now = new Date();
            const firstDay = new Date(now.getFullYear(), now.getMonth(), 1, 0, 0, 0);
            $('#date-from').val(formatDateForInput(firstDay));
            $('#date-to').val(formatDateForInput(now));
        } else {
            const days = parseInt($(this).data('days')) || 7;
            setQuickDateRange(days);
        }
    });

    // Reset de Filtros
    $('#btn-reset-filters').on('click', function() {
        $('#select-groups').val(null).trigger('change');
        $('#general-observations-input').val('');
        $('#report-title-input').val('Informe de Rendimiento y Tráfico de Enlaces de Red');
        $('.btn-range[data-days="7"]').click();
        resetInterfacesSection();
    });

    // -------------------------------------------------------------
    // GESTIÓN DE INTERFACES Y CHECKBOXES
    // -------------------------------------------------------------

    // Marcar/Desmarcar todas las interfaces de un host específico
    $(document).on('click', '.btn-host-toggle-all', function(e) {
        e.stopPropagation();
        const hid = $(this).data('hostid');
        const state = $(this).data('action') === 'check';
        
        $(`.iface-chk[data-hostid="${hid}"]`).each(function() {
            $(this).prop('checked', state).trigger('change');
        });
    });

    // Marcar solo interfaces Activas (Up) de un host
    $(document).on('click', '.btn-host-select-up', function(e) {
        e.stopPropagation();
        const hid = $(this).data('hostid');
        
        $(`.iface-chk[data-hostid="${hid}"]`).each(function() {
            const isUp = $(this).data('status') === '1' || $(this).data('status') === 1;
            $(this).prop('checked', isUp).trigger('change');
        });
    });

    // Acciones globales: Marcar / Desmarcar todas las de todos los hosts
    $('#btn-select-all-ifaces').on('click', function() {
        $('.iface-chk').prop('checked', true).trigger('change');
    });

    $('#btn-deselect-all-ifaces').on('click', function() {
        $('.iface-chk').prop('checked', false).trigger('change');
    });

    // Cambio en un Checkbox Individual de Interfaz
    $(document).on('change', '.iface-chk', function() {
        const hid = $(this).data('hostid');
        const ifname = $(this).data('ifname');
        const isChecked = $(this).is(':checked');

        if (!selectedInterfacesMap[hid]) {
            selectedInterfacesMap[hid] = {};
        }

        if (isChecked) {
            // Buscar objeto completo de la interfaz
            const hostIfaces = discoveredInterfaces[hid] || [];
            const ifObj = hostIfaces.find(x => x.ifname === ifname);
            if (ifObj) {
                selectedInterfacesMap[hid][ifname] = ifObj;
            }
            $(`#row-iface-${hid}-${sanitizeId(ifname)}`).addClass('table-primary');
        } else {
            delete selectedInterfacesMap[hid][ifname];
            if (Object.keys(selectedInterfacesMap[hid]).length === 0) {
                delete selectedInterfacesMap[hid];
            }
            $(`#row-iface-${hid}-${sanitizeId(ifname)}`).removeClass('table-primary');
        }

        updateGenerateButtonState();
    });

    // Cambio en el input de Observación por Interfaz
    $(document).on('input', '.if-obs-input', function() {
        const hid = $(this).data('hostid');
        const ifname = $(this).data('ifname');
        const key = hid + '_' + ifname;
        interfaceObservations[key] = $(this).val();
    });

    // -------------------------------------------------------------
    // GENERACIÓN Y VISUALIZACIÓN DEL INFORME
    // -------------------------------------------------------------
    $('#btn-generar-informe').on('click', function() {
        generateReportAction();
    });

    $('#btn-back-to-filters').on('click', function() {
        $('#report-viewer-section').addClass('d-none');
        $('#report-creator-form').removeClass('d-none');
        $('html, body').animate({ scrollTop: $('#report-creator-form').offset().top - 20 }, 300);
    });

    $('#btn-save-current-report').on('click', function() {
        if (!currentCompiledReport) return;
        saveReportToDatabase(currentCompiledReport);
    });

    $('#btn-print-report').on('click', function() {
        window.print();
    });

    $('#btn-export-csv').on('click', function() {
        if (!currentCompiledReport) return;
        exportReportToCSV(currentCompiledReport);
    });

    $('#btn-edit-observations').on('click', function() {
        if (!currentCompiledReport) return;
        editReportObservationsPrompt();
    });

    // -------------------------------------------------------------
    // MODAL Y GESTIÓN DE CONFIGURACIÓN DE IA
    // -------------------------------------------------------------
    $('#btn-open-ai-modal, #btn-quick-config-ai').on('click', function() {
        $('#modalConfigAI').modal('show');
    });

    $('#btn-toggle-gemini-key').on('click', function() {
        const input = $('#cfg-gemini-key');
        const type = input.attr('type') === 'password' ? 'text' : 'password';
        input.attr('type', type);
        $(this).find('i').toggleClass('fa-eye fa-eye-slash');
    });

    $('#btn-test-gemini').on('click', function() {
        const key = $('#cfg-gemini-key').val();
        const model = $('#cfg-gemini-model').val();
        testAIConnection('gemini', key, model, '');
    });

    $('#btn-test-ollama').on('click', function() {
        const url = $('#cfg-ollama-url').val();
        const model = $('#cfg-ollama-model').val();
        testAIConnection('ollama', '', model, url);
    });

    $('#btn-save-ai-settings').on('click', function() {
        saveAISettings();
    });

    // -------------------------------------------------------------
    // HISTORIAL DE INFORMES GUARDADOS
    // -------------------------------------------------------------
    $('#btn-refresh-saved-list').on('click', function() {
        loadSavedReportsList();
    });

    $(document).on('click', '.btn-view-saved-report', function() {
        const reportId = $(this).data('id');
        loadSingleSavedReport(reportId);
    });

    $(document).on('click', '.btn-delete-saved-report', function() {
        const reportId = $(this).data('id');
        deleteSavedReport(reportId);
    });

    $(document).on('click', '.btn-edit-obs-saved', function() {
        const reportId = $(this).data('id');
        editSavedReportObservations(reportId);
    });

    // =============================================================
    // FUNCIONES AJAX Y LÓGICA DE NEGOCIO
    // =============================================================

    function loadZabbixGroups() {
        $.getJSON('api_report_creator.php', { action: 'get_groups' }, function(resp) {
            if (resp.success && resp.data) {
                allGroups = resp.data;
                const select = $('#select-groups');
                select.empty();
                resp.data.forEach(g => {
                    select.append(new Option(g.name, g.groupid, false, false));
                });
            } else {
                toastr.error(resp.error || 'No se pudieron cargar los grupos de Zabbix');
            }
        });
    }

    function loadHostsForGroups(groupIds) {
        $('#select-hosts').empty().prop('disabled', true);
        $('#hosts-help-text').html('<div class="spinner-border spinner-border-sm text-primary mr-1"></div> Consultando equipos de los grupos seleccionados...');

        $.getJSON('api_report_creator.php', { 
            action: 'get_hosts', 
            groupids: groupIds.join(',') 
        }, function(resp) {
            if (resp.success && resp.data) {
                currentHosts = resp.data;
                const select = $('#select-hosts');
                select.empty();
                
                resp.data.forEach(h => {
                    const statusText = (h.status === '0' || h.status === 0) ? '🟢' : '⚪';
                    const ipText = h.ip ? ` (${h.ip})` : '';
                    const label = `${statusText} ${h.name}${ipText}`;
                    select.append(new Option(label, h.hostid, false, false));
                });

                select.prop('disabled', false).trigger('change');
                $('#hosts-help-text').text(`Se encontraron ${resp.data.length} equipos en los grupos seleccionados.`);
            } else {
                toastr.error(resp.error || 'Error al obtener equipos de Zabbix');
                $('#hosts-help-text').text('Error al consultar equipos.');
            }
        });
    }

    function loadInterfacesForHosts(hostIds) {
        $('#interfaces-empty-state').addClass('d-none');
        $('#interfaces-accordion-container').addClass('d-none');
        $('#interfaces-loading-state').removeClass('d-none');

        $.getJSON('api_report_creator.php', {
            action: 'get_interfaces',
            hostids: hostIds.join(',')
        }, function(resp) {
            $('#interfaces-loading-state').addClass('d-none');
            
            if (resp.success && resp.data) {
                discoveredInterfaces = resp.data;
                renderInterfacesAccordion(hostIds, resp.data);
            } else {
                toastr.error(resp.error || 'No se pudieron detectar las interfaces de los equipos');
                $('#interfaces-empty-state').removeClass('d-none');
            }
        }).fail(function() {
            $('#interfaces-loading-state').addClass('d-none');
            toastr.error('Error de comunicación al consultar interfaces');
            $('#interfaces-empty-state').removeClass('d-none');
        });
    }

    function renderInterfacesAccordion(hostIds, interfacesByHost) {
        const container = $('#interfaces-accordion-container');
        container.empty().removeClass('d-none');
        $('#interfaces-global-actions').removeClass('d-none');

        let totalInterfacesCount = 0;

        hostIds.forEach((hid, index) => {
            const hostInfo = currentHosts.find(h => h.hostid === hid) || { name: 'Host ' + hid, ip: '', status: '0' };
            const ifaces = interfacesByHost[hid] || [];
            totalInterfacesCount += ifaces.length;

            const isExpanded = index === 0 ? 'show' : '';
            const statusBadge = (hostInfo.status === '0' || hostInfo.status === 0) 
                ? '<span class="badge badge-success px-2 py-1">En Línea (Monitoreado)</span>' 
                : '<span class="badge badge-secondary px-2 py-1">Inactivo</span>';

            let ifacesTableRows = '';
            if (ifaces.length === 0) {
                ifacesTableRows = `<tr><td colspan="7" class="text-center text-muted py-3">No se detectaron ítems de red net.if en este equipo.</td></tr>`;
            } else {
                ifaces.forEach(iface => {
                    const rowId = `row-iface-${hid}-${sanitizeId(iface.ifname)}`;
                    const isChecked = (selectedInterfacesMap[hid] && selectedInterfacesMap[hid][iface.ifname]) ? 'checked' : '';
                    const isRowHighlight = isChecked ? 'table-primary' : '';
                    
                    const speedFmt = iface.speed ? formatSpeed(iface.speed.lastvalue) : '1 Gbps';
                    const obsVal = interfaceObservations[`${hid}_${iface.ifname}`] || '';

                    // Badges de métricas encontradas
                    const tinBadge = iface.traffic_in ? '<span class="iface-badge-metric badge-in" title="Ítem Inbound Detectado">Inbound ✓</span>' : '<span class="badge badge-light text-muted">Inbound ✗</span>';
                    const toutBadge = iface.traffic_out ? '<span class="iface-badge-metric badge-out" title="Ítem Outbound Detectado">Outbound ✓</span>' : '<span class="badge badge-light text-muted">Outbound ✗</span>';
                    const einBadge = iface.errors_in ? '<span class="iface-badge-metric badge-err" title="Errores In Detectados">Err In ✓</span>' : '';
                    const eoutBadge = iface.errors_out ? '<span class="iface-badge-metric badge-err" title="Errores Out Detectados">Err Out ✓</span>' : '';

                    const statusVal = iface.status ? iface.status.lastvalue : '1';
                    const operBadge = (statusVal === '1' || statusVal === 1) 
                        ? '<span class="badge badge-success">Up</span>' 
                        : '<span class="badge badge-danger">Down</span>';

                    ifacesTableRows += `
                        <tr id="${rowId}" class="${isRowHighlight} transition-all">
                            <td class="text-center align-middle" style="width: 45px;">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input iface-chk" id="chk-${hid}-${sanitizeId(iface.ifname)}" 
                                           data-hostid="${hid}" data-ifname="${iface.ifname}" data-status="${statusVal}" ${isChecked}>
                                    <label class="custom-control-label" for="chk-${hid}-${sanitizeId(iface.ifname)}"></label>
                                </div>
                            </td>
                            <td class="align-middle text-left font-weight-bold">
                                <code>${iface.ifname}</code>
                            </td>
                            <td class="align-middle text-left text-muted small">
                                ${iface.alias ? `<strong>${iface.alias}</strong>` : '<em>Sin alias</em>'}
                            </td>
                            <td class="align-middle text-center">${operBadge}</td>
                            <td class="align-middle text-center small">${speedFmt}</td>
                            <td class="align-middle text-center">
                                ${tinBadge} ${toutBadge} ${einBadge} ${eoutBadge}
                            </td>
                            <td class="align-middle" style="min-width: 200px;">
                                <input type="text" class="form-control form-control-sm if-obs-input" 
                                       data-hostid="${hid}" data-ifname="${iface.ifname}" 
                                       placeholder="Observación para esta interfaz..." value="${obsVal}">
                            </td>
                        </tr>
                    `;
                });
            }

            const cardHtml = `
                <div class="host-interface-card">
                    <div class="host-header d-flex justify-content-between align-items-center flex-wrap" data-toggle="collapse" data-target="#collapse-host-${hid}">
                        <div class="d-flex align-items-center mb-1 mb-md-0">
                            <i class="fas fa-server text-primary mr-2"></i>
                            <strong class="text-dark mr-2">${hostInfo.name}</strong>
                            <span class="text-muted small mr-3">(${hostInfo.ip || 'Sin IP'})</span>
                            ${statusBadge}
                        </div>
                        <div class="d-flex align-items-center" style="gap: 6px;">
                            <span class="badge badge-light border text-muted mr-2">${ifaces.length} Interfaces</span>
                            <button type="button" class="btn btn-xs btn-outline-primary btn-host-toggle-all" data-hostid="${hid}" data-action="check" title="Marcar todas las de este equipo">
                                <i class="fas fa-check"></i> Todas
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-success btn-host-select-up" data-hostid="${hid}" title="Marcar solo interfaces Up">
                                <i class="fas fa-bolt"></i> Solo Up
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-secondary btn-host-toggle-all" data-hostid="${hid}" data-action="uncheck" title="Desmarcar todas">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div id="collapse-host-${hid}" class="collapse ${isExpanded}">
                        <div class="table-responsive p-0">
                            <table class="table table-sm table-hover mb-0 text-center">
                                <thead class="thead-light small">
                                    <tr>
                                        <th style="width: 45px;">Sel</th>
                                        <th class="text-left">Interfaz</th>
                                        <th class="text-left">Alias / Descripción</th>
                                        <th>Estado</th>
                                        <th>Velocidad</th>
                                        <th>Métricas Vinculadas</th>
                                        <th>Observación Específica del Enlace</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${ifacesTableRows}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;

            container.append(cardHtml);
        });

        updateGenerateButtonState();
    }

    function resetInterfacesSection() {
        discoveredInterfaces = {};
        selectedInterfacesMap = {};
        $('#interfaces-accordion-container').empty().addClass('d-none');
        $('#interfaces-global-actions').addClass('d-none');
        $('#interfaces-empty-state').removeClass('d-none');
        updateGenerateButtonState();
    }

    function updateGenerateButtonState() {
        let count = 0;
        for (const hid in selectedInterfacesMap) {
            count += Object.keys(selectedInterfacesMap[hid]).length;
        }

        if (count > 0) {
            $('#btn-generar-informe').prop('disabled', false);
            $('#generate-btn-hint').html(`<strong class="text-success">${count} interface(s) seleccionada(s)</strong> listas para compilar.`);
        } else {
            $('#btn-generar-informe').prop('disabled', true);
            $('#generate-btn-hint').text('Seleccione al menos una interfaz de red para habilitar la generación.');
        }
    }

    // -------------------------------------------------------------
    // ACCIÓN: GENERAR INFORME
    // -------------------------------------------------------------
    function generateReportAction() {
        const title = $('#report-title-input').val().trim() || 'Informe de Rendimiento y Tráfico de Enlaces de Red';
        const dateFrom = $('#date-from').val();
        const dateTo = $('#date-to').val();
        const aiProvider = $('#select-ai-provider').val();
        const reportType = $('input[name="report_type_mode"]:checked').val() || 'both';
        const generalObs = $('#general-observations-input').val().trim();
        const saveReportDb = $('#chk-save-report-db').is(':checked') ? 1 : 0;

        if (!dateFrom || !dateTo) {
            Swal.fire('Atención', 'Debe especificar el rango completo de fechas (Desde y Hasta).', 'warning');
            return;
        }

        // Validar que haya al menos una interfaz seleccionada
        let count = 0;
        for (const hid in selectedInterfacesMap) {
            count += Object.keys(selectedInterfacesMap[hid]).length;
        }
        if (count === 0) {
            Swal.fire('Atención', 'Debe seleccionar al menos una interfaz de red para generar el informe.', 'warning');
            return;
        }

        // Mostrar pantalla de carga
        $('#report-creator-form').addClass('d-none');
        $('#report-viewer-section').addClass('d-none');
        $('#report-generating-spinner').removeClass('d-none');

        const postData = {
            action: 'generate_report',
            title: title,
            date_from: dateFrom.replace('T', ' ') + ':00',
            date_to: dateTo.replace('T', ' ') + ':00',
            selected_interfaces: JSON.stringify(selectedInterfacesMap),
            interface_observations: JSON.stringify(interfaceObservations),
            general_observations: generalObs,
            ai_provider: aiProvider,
            report_type: reportType,
            save_report: saveReportDb
        };

        $.post('api_report_creator.php', postData, function(resp) {
            $('#report-generating-spinner').addClass('d-none');

            if (resp.success && resp.data) {
                currentCompiledReport = resp.data;
                renderReportViewer(resp.data);
                loadSavedReportsList(); // Refrescar lista de guardados en segundo plano
                toastr.success('Informe generado correctamente');
            } else {
                $('#report-creator-form').removeClass('d-none');
                Swal.fire('Error', resp.error || 'No se pudo generar el informe', 'error');
            }
        }, 'json').fail(function(xhr) {
            $('#report-generating-spinner').addClass('d-none');
            $('#report-creator-form').removeClass('d-none');
            Swal.fire('Error de Conexión', 'Hubo una falla al procesar la solicitud en el servidor.', 'error');
        });
    }

    // -------------------------------------------------------------
    // RENDERIZADO DEL VISOR DE INFORME
    // -------------------------------------------------------------
    function renderReportViewer(report) {
        destroyAllCharts();

        $('#view-report-title').text(report.title);
        $('#view-report-period').text(`${report.period.from_formatted} al ${report.period.to_formatted}`);
        $('#view-report-generated-at').text(report.generated_at_formatted);
        $('#view-report-user').text(report.generated_by);

        // Estado del botón Guardar en BD
        if (report.report_id) {
            $('#btn-save-current-report').removeClass('btn-success').addClass('btn-outline-success')
                .html(`<i class="fas fa-check mr-1"></i> Guardado en BD (#${report.report_id})`).prop('disabled', true);
        } else {
            $('#btn-save-current-report').removeClass('btn-outline-success').addClass('btn-success')
                .html(`<i class="fas fa-save mr-1"></i> Guardar en Base de Datos`).prop('disabled', false);
        }

        // KPIs
        $('#kpi-hosts').text(report.summary_kpis.total_hosts);
        $('#kpi-interfaces').text(report.summary_kpis.total_interfaces);
        $('#kpi-peak-in').text(report.summary_kpis.peak_traffic_in_formatted);
        $('#kpi-peak-out').text(report.summary_kpis.peak_traffic_out_formatted);
        $('#kpi-errors').text(report.summary_kpis.total_errors_in + report.summary_kpis.total_errors_out);

        // Sección IA
        if (report.ai_analysis && report.ai_analysis.trim() !== '') {
            $('#view-ai-section').removeClass('d-none');
            $('#view-ai-model-badge').text(report.ai_model || 'Motor de Análisis');
            // Renderizado Markdown
            const renderedMarkdown = (typeof marked !== 'undefined' && marked.parse) 
                ? marked.parse(report.ai_analysis) 
                : report.ai_analysis.replace(/\n/g, '<br>');
            $('#view-ai-text').html(renderedMarkdown);
        } else {
            $('#view-ai-section').addClass('d-none');
        }

        // Sección Alarmas
        renderAlarmsSection(report.alarms || []);

        // Sección Consolidada
        if (report.report_type === 'individual') {
            $('#view-consolidated-section').addClass('d-none');
        } else {
            $('#view-consolidated-section').removeClass('d-none');
            renderConsolidatedChartsAndTable(report);
        }

        // Sección Individual
        if (report.report_type === 'consolidated') {
            $('#view-individual-section').addClass('d-none');
        } else {
            $('#view-individual-section').removeClass('d-none');
            renderIndividualHosts(report);
        }

        // Observaciones Generales
        if (report.general_observations && report.general_observations.trim() !== '') {
            $('#view-general-observations-text').html(escapeHtml(report.general_observations).replace(/\n/g, '<br>'));
        } else {
            $('#view-general-observations-text').html('<em>Sin observaciones adicionales registradas.</em>');
        }

        // Mostrar sección y scrollear
        $('#report-viewer-section').removeClass('d-none');
        $('html, body').animate({ scrollTop: $('#report-viewer-section').offset().top - 20 }, 300);
    }

    function renderAlarmsSection(alarms) {
        const container = $('#view-alarms-content');
        container.empty();
        $('#view-alarms-count-badge').text(`${alarms.length} Alarma(s)`);

        if (alarms.length === 0) {
            container.html(`
                <div class="alert alert-success d-flex align-items-center mb-0" role="alert">
                    <i class="fas fa-check-circle fa-2x mr-3 text-success"></i>
                    <div>
                        <strong>Excelente operatividad:</strong> No se registraron incidentes ni alarmas activas en Zabbix para los equipos analizados durante este período.
                    </div>
                </div>
            `);
        } else {
            let rows = '';
            alarms.forEach(a => {
                rows += `
                    <tr>
                        <td class="text-left font-weight-bold small">${a.name}</td>
                        <td><span class="badge ${a.severity_class} px-2 py-1 font-weight-bold">${a.severity_name}</span></td>
                        <td class="small">${a.date_formatted}</td>
                        <td class="small">${a.r_date_formatted}</td>
                        <td class="small font-weight-bold">${a.duration}</td>
                    </tr>
                `;
            });

            container.html(`
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover text-center mb-0">
                        <thead class="thead-light small">
                            <tr>
                                <th class="text-left">Problema / Evento</th>
                                <th>Severidad</th>
                                <th>Fecha Inicio</th>
                                <th>Fecha Resolución</th>
                                <th>Duración</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
            `);
        }
    }

    function renderConsolidatedChartsAndTable(report) {
        const dailyData = report.consolidated.daily || [];

        // 1. Gráfico Diario Consolidado
        const ctxDaily = document.getElementById('chart-consolidated-daily').getContext('2d');
        const dailyLabels = dailyData.map(d => d.date_formatted);
        const dailyInAvg = dailyData.map(d => d.in_avg_mbps);
        const dailyInMax = dailyData.map(d => d.in_max_mbps);
        const dailyOutAvg = dailyData.map(d => d.out_avg_mbps);
        const dailyOutMax = dailyData.map(d => d.out_max_mbps);

        activeCharts['chart-consolidated-daily'] = new Chart(ctxDaily, {
            type: 'line',
            data: {
                labels: dailyLabels,
                datasets: [
                    {
                        label: 'Tráfico IN Promedio (Mbps)',
                        data: dailyInAvg,
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'Tráfico IN Pico (Mbps)',
                        data: dailyInMax,
                        borderColor: '#0056b3',
                        borderDash: [5, 5],
                        borderWidth: 1.5,
                        pointRadius: 3
                    },
                    {
                        label: 'Tráfico OUT Promedio (Mbps)',
                        data: dailyOutAvg,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'Tráfico OUT Pico (Mbps)',
                        data: dailyOutMax,
                        borderColor: '#1e7e34',
                        borderDash: [5, 5],
                        borderWidth: 1.5,
                        pointRadius: 3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: { mode: 'index', intersect: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Megabits por Segundo (Mbps)' }
                    }
                }
            }
        });

        // 2. Gráfico de Errores Consolidado
        const ctxErrors = document.getElementById('chart-consolidated-errors').getContext('2d');
        const dailyErrIn = dailyData.map(d => d.errors_in);
        const dailyErrOut = dailyData.map(d => d.errors_out);

        activeCharts['chart-consolidated-errors'] = new Chart(ctxErrors, {
            type: 'bar',
            data: {
                labels: dailyLabels,
                datasets: [
                    {
                        label: 'Errores Inbound',
                        data: dailyErrIn,
                        backgroundColor: 'rgba(220, 53, 69, 0.7)',
                        borderColor: '#dc3545',
                        borderWidth: 1
                    },
                    {
                        label: 'Errores Outbound',
                        data: dailyErrOut,
                        backgroundColor: 'rgba(255, 193, 7, 0.7)',
                        borderColor: '#ffc107',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'top' } },
                scales: {
                    y: { beginAtZero: true, title: { display: true, text: 'Paquetes con Error' } }
                }
            }
        });

        // 3. Matriz Resumen Consolidada
        const tbody = $('#tbody-consolidated-summary');
        tbody.empty();

        report.hosts.forEach(h => {
            h.interfaces.forEach(iface => {
                const badgeUsage = iface.totals.usage_pct_in >= 75 || iface.totals.usage_pct_out >= 75
                    ? '<span class="badge badge-danger">' + Math.max(iface.totals.usage_pct_in, iface.totals.usage_pct_out) + '%</span>'
                    : '<span class="badge badge-success">' + Math.max(iface.totals.usage_pct_in, iface.totals.usage_pct_out) + '%</span>';

                tbody.append(`
                    <tr>
                        <td class="text-left font-weight-bold">${h.name}</td>
                        <td class="text-left"><code>${iface.ifname}</code></td>
                        <td class="text-left text-muted small">${iface.alias || '-'}</td>
                        <td>${iface.speed_formatted}</td>
                        <td>${iface.totals.avg_in_mbps} Mbps</td>
                        <td class="font-weight-bold text-primary">${iface.totals.max_in_mbps} Mbps</td>
                        <td>${iface.totals.avg_out_mbps} Mbps</td>
                        <td class="font-weight-bold text-success">${iface.totals.max_out_mbps} Mbps</td>
                        <td class="${iface.totals.total_errors_in > 0 ? 'text-danger font-weight-bold' : 'text-muted'}">${iface.totals.total_errors_in}</td>
                        <td class="${iface.totals.total_errors_out > 0 ? 'text-danger font-weight-bold' : 'text-muted'}">${iface.totals.total_errors_out}</td>
                        <td>${badgeUsage}</td>
                        <td class="text-left font-italic small">${iface.observation || '-'}</td>
                    </tr>
                `);
            });
        });
    }

    function renderIndividualHosts(report) {
        const container = $('#view-individual-hosts-container');
        container.empty();

        report.hosts.forEach((h, hIdx) => {
            let ifacesHtml = '';

            h.interfaces.forEach((iface, ifIdx) => {
                const chartHourlyId = `chart-hourly-${h.hostid}-${sanitizeId(iface.ifname)}`;
                const chartErrorsId = `chart-errors-${h.hostid}-${sanitizeId(iface.ifname)}`;

                // Filas de la tabla diaria
                let dailyRows = '';
                iface.daily.forEach(d => {
                    dailyRows += `
                        <tr>
                            <td>${d.date_formatted}</td>
                            <td>${d.in_avg_mbps} Mbps</td>
                            <td class="font-weight-bold text-primary">${d.in_max_mbps} Mbps</td>
                            <td>${d.out_avg_mbps} Mbps</td>
                            <td class="font-weight-bold text-success">${d.out_max_mbps} Mbps</td>
                            <td class="${d.errors_in > 0 ? 'text-danger font-weight-bold' : 'text-muted'}">${d.errors_in}</td>
                            <td class="${d.errors_out > 0 ? 'text-danger font-weight-bold' : 'text-muted'}">${d.errors_out}</td>
                            <td>${d.usage_pct_in}%</td>
                        </tr>
                    `;
                });

                ifacesHtml += `
                    <div class="card border rounded mb-4 shadow-sm" style="background: #fafbfc;">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap">
                            <div>
                                <h5 class="mb-0 font-weight-bold text-dark">
                                    <i class="fas fa-network-wired text-info mr-2"></i> Interfaz: <code>${iface.ifname}</code>
                                    ${iface.alias ? `<span class="text-muted small ml-2 font-weight-normal">(${iface.alias})</span>` : ''}
                                </h5>
                                <span class="small text-muted">Capacidad: <strong>${iface.speed_formatted}</strong></span>
                            </div>
                            <div>
                                <span class="badge badge-light border mr-2 font-weight-bold">
                                    Pico IN: ${iface.totals.max_in_mbps} Mbps (${iface.totals.usage_pct_in}%)
                                </span>
                                <span class="badge badge-light border font-weight-bold">
                                    Pico OUT: ${iface.totals.max_out_mbps} Mbps (${iface.totals.usage_pct_out}%)
                                </span>
                            </div>
                        </div>
                        <div class="card-body p-3">
                            <!-- Gráficos de la Interfaz -->
                            <div class="row mb-3">
                                <div class="col-lg-8 mb-3">
                                    <div class="chart-container-card mb-0 h-100">
                                        <h6 class="small font-weight-bold text-dark mb-2">
                                            <i class="fas fa-chart-line text-primary mr-1"></i> Tráfico Hora por Hora (Trends Zabbix)
                                        </h6>
                                        <div style="height: 240px; position: relative;">
                                            <canvas id="${chartHourlyId}"></canvas>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-4 mb-3">
                                    <div class="chart-container-card mb-0 h-100">
                                        <h6 class="small font-weight-bold text-dark mb-2">
                                            <i class="fas fa-bug text-danger mr-1"></i> Errores Horarios
                                        </h6>
                                        <div style="height: 240px; position: relative;">
                                            <canvas id="${chartErrorsId}"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tabla diaria detallada -->
                            <div class="table-responsive mb-2">
                                <table class="table table-sm table-bordered table-striped text-center mb-0 small">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Promedio IN</th>
                                            <th>Pico IN</th>
                                            <th>Promedio OUT</th>
                                            <th>Pico OUT</th>
                                            <th>Errores IN</th>
                                            <th>Errores OUT</th>
                                            <th>% Saturación IN</th>
                                        </tr>
                                    </thead>
                                    <tbody>${dailyRows}</tbody>
                                </table>
                            </div>

                            <!-- Observación de la interfaz -->
                            <div class="p-2 border rounded bg-white small mt-2">
                                <strong>Observación del Enlace:</strong> 
                                <span class="font-italic text-muted">${iface.observation || 'Sin observaciones para esta interfaz.'}</span>
                            </div>
                        </div>
                    </div>
                `;
            });

            const hostCard = `
                <div class="card border rounded mb-4 shadow-sm">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 font-weight-bold">
                            <i class="fas fa-server mr-2 text-warning"></i> Equipo: ${h.name}
                        </h5>
                        <div>
                            <span class="badge badge-light mr-2 font-weight-bold">IP: ${h.ip || 'N/A'}</span>
                            <span class="badge badge-success font-weight-bold">${h.status_label}</span>
                        </div>
                    </div>
                    <div class="card-body p-3">
                        ${ifacesHtml}
                    </div>
                </div>
            `;

            container.append(hostCard);

            // Inicializar Chart.js para cada interfaz
            h.interfaces.forEach(iface => {
                const chartHourlyId = `chart-hourly-${h.hostid}-${sanitizeId(iface.ifname)}`;
                const chartErrorsId = `chart-errors-${h.hostid}-${sanitizeId(iface.ifname)}`;

                // Gráfico Horario de Tráfico
                const ctxH = document.getElementById(chartHourlyId);
                if (ctxH) {
                    activeCharts[chartHourlyId] = new Chart(ctxH.getContext('2d'), {
                        type: 'line',
                        data: {
                            labels: iface.hourly.labels,
                            datasets: [
                                {
                                    label: 'IN Avg (Mbps)',
                                    data: iface.hourly.in_avg,
                                    borderColor: '#007bff',
                                    borderWidth: 1.5,
                                    pointRadius: 1,
                                    tension: 0.2
                                },
                                {
                                    label: 'OUT Avg (Mbps)',
                                    data: iface.hourly.out_avg,
                                    borderColor: '#28a745',
                                    borderWidth: 1.5,
                                    pointRadius: 1,
                                    tension: 0.2
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { position: 'top', labels: { boxWidth: 12 } } },
                            scales: {
                                y: { beginAtZero: true, title: { display: true, text: 'Mbps' } },
                                x: { ticks: { maxTicksLimit: 12 } }
                            }
                        }
                    });
                }

                // Gráfico Horario de Errores
                const ctxE = document.getElementById(chartErrorsId);
                if (ctxE) {
                    activeCharts[chartErrorsId] = new Chart(ctxE.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: iface.hourly.labels,
                            datasets: [
                                {
                                    label: 'Err In',
                                    data: iface.hourly.in_errors,
                                    backgroundColor: '#dc3545'
                                },
                                {
                                    label: 'Err Out',
                                    data: iface.hourly.out_errors,
                                    backgroundColor: '#ffc107'
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { position: 'top', labels: { boxWidth: 12 } } },
                            scales: {
                                y: { beginAtZero: true },
                                x: { ticks: { maxTicksLimit: 6 } }
                            }
                        }
                    });
                }
            });
        });
    }

    function destroyAllCharts() {
        for (const cid in activeCharts) {
            if (activeCharts[cid]) {
                activeCharts[cid].destroy();
            }
        }
        activeCharts = {};
    }

    // -------------------------------------------------------------
    // GUARDADO Y HISTORIAL EN BASE DE DATOS
    // -------------------------------------------------------------
    function saveReportToDatabase(report) {
        $.post('api_report_creator.php', {
            action: 'generate_report',
            title: report.title,
            date_from: report.period.from,
            date_to: report.period.to,
            selected_interfaces: JSON.stringify(selectedInterfacesMap),
            interface_observations: JSON.stringify(interfaceObservations),
            general_observations: report.general_observations,
            ai_provider: report.ai_provider,
            report_type: report.report_type,
            save_report: 1
        }, function(resp) {
            if (resp.success && resp.data) {
                currentCompiledReport.report_id = resp.data.report_id;
                $('#btn-save-current-report').removeClass('btn-success').addClass('btn-outline-success')
                    .html(`<i class="fas fa-check mr-1"></i> Guardado en BD (#${resp.data.report_id})`).prop('disabled', true);
                loadSavedReportsList();
                Swal.fire('Guardado', `El informe #${resp.data.report_id} ha sido almacenado correctamente.`, 'success');
            } else {
                Swal.fire('Error', resp.error || 'No se pudo guardar el informe', 'error');
            }
        }, 'json');
    }

    function loadSavedReportsList() {
        $.getJSON('api_report_creator.php', { action: 'list_saved' }, function(resp) {
            const tbody = $('#tbody-saved-reports');
            tbody.empty();

            if (resp.success && resp.data) {
                $('#saved-reports-count').text(resp.data.length);

                if (resp.data.length === 0) {
                    tbody.html(`<tr><td colspan="8" class="text-center py-4 text-muted">No hay informes guardados aún en el historial.</td></tr>`);
                    return;
                }

                resp.data.forEach(r => {
                    const aiBadge = r.ai_provider === 'gemini' 
                        ? '<span class="badge badge-primary">Gemini</span>'
                        : (r.ai_provider === 'ollama' ? '<span class="badge badge-success">Ollama</span>' : '<span class="badge badge-secondary">Heurístico/Sin IA</span>');

                    tbody.append(`
                        <tr>
                            <td class="font-weight-bold">#${r.id}</td>
                            <td class="text-left font-weight-bold">${escapeHtml(r.title)}</td>
                            <td class="small">${r.period_formatted}</td>
                            <td><span class="badge badge-light border">${r.report_type}</span></td>
                            <td>${aiBadge}</td>
                            <td class="small text-muted">${r.created_at_formatted}</td>
                            <td class="small font-weight-bold">${r.created_by || 'admin'}</td>
                            <td>
                                <button type="button" class="btn btn-xs btn-primary btn-view-saved-report" data-id="${r.id}" title="Ver informe">
                                    <i class="fas fa-eye mr-1"></i> Ver
                                </button>
                                <button type="button" class="btn btn-xs btn-outline-info btn-edit-obs-saved" data-id="${r.id}" title="Editar Observaciones">
                                    <i class="fas fa-comment-alt"></i>
                                </button>
                                <button type="button" class="btn btn-xs btn-outline-danger btn-delete-saved-report" data-id="${r.id}" title="Eliminar">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `);
                });
            }
        });
    }

    function loadSingleSavedReport(id) {
        Swal.fire({
            title: 'Cargando informe...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        $.getJSON('api_report_creator.php', { action: 'get_saved', id: id }, function(resp) {
            Swal.close();
            if (resp.success && resp.data) {
                currentCompiledReport = resp.data;
                $('#tab-creacion-link').tab('show');
                renderReportViewer(resp.data);
            } else {
                Swal.fire('Error', resp.error || 'No se pudo cargar el informe', 'error');
            }
        }).fail(function() {
            Swal.close();
            Swal.fire('Error', 'Falla de conexión al cargar el informe guardado', 'error');
        });
    }

    function deleteSavedReport(id) {
        Swal.fire({
            title: '¿Eliminar informe #' + id + '?',
            text: 'Esta acción no se puede deshacer.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('api_report_creator.php', { action: 'delete_saved', id: id }, function(resp) {
                    if (resp.success) {
                        toastr.success('Informe eliminado correctamente');
                        loadSavedReportsList();
                    } else {
                        Swal.fire('Error', resp.error || 'No se pudo eliminar el informe', 'error');
                    }
                }, 'json');
            }
        });
    }

    function editSavedReportObservations(id) {
        $.getJSON('api_report_creator.php', { action: 'get_saved', id: id }, function(resp) {
            if (resp.success && resp.data) {
                const currentObs = resp.data.general_observations || '';
                Swal.fire({
                    title: 'Editar Observaciones Generales',
                    input: 'textarea',
                    inputValue: currentObs,
                    inputPlaceholder: 'Ingrese las observaciones...',
                    showCancelButton: true,
                    confirmButtonText: 'Guardar',
                    cancelButtonText: 'Cancelar'
                }).then((res) => {
                    if (res.isConfirmed) {
                        $.post('api_report_creator.php', {
                            action: 'update_observations',
                            id: id,
                            general_observations: res.value
                        }, function(upResp) {
                            if (upResp.success) {
                                toastr.success('Observaciones actualizadas');
                                loadSavedReportsList();
                            } else {
                                Swal.fire('Error', upResp.error || 'No se pudo actualizar', 'error');
                            }
                        }, 'json');
                    }
                });
            }
        });
    }

    function editReportObservationsPrompt() {
        const cur = currentCompiledReport.general_observations || '';
        Swal.fire({
            title: 'Modificar Observaciones del Informe',
            input: 'textarea',
            inputValue: cur,
            showCancelButton: true,
            confirmButtonText: 'Actualizar',
            cancelButtonText: 'Cancelar'
        }).then((res) => {
            if (res.isConfirmed) {
                currentCompiledReport.general_observations = res.value;
                $('#view-general-observations-text').html(escapeHtml(res.value).replace(/\n/g, '<br>'));
                if (currentCompiledReport.report_id) {
                    $.post('api_report_creator.php', {
                        action: 'update_observations',
                        id: currentCompiledReport.report_id,
                        general_observations: res.value
                    }, function(upResp) {
                        toastr.success('Observaciones sincronizadas en base de datos');
                    }, 'json');
                }
            }
        });
    }

    // -------------------------------------------------------------
    // EXPORTACIÓN A CSV
    // -------------------------------------------------------------
    function exportReportToCSV(report) {
        let csvContent = "data:text/csv;charset=utf-8,";
        csvContent += `"INFORME:","${report.title}"\n`;
        csvContent += `"PERIODO:","${report.period.from} al ${report.period.to}"\n`;
        csvContent += `"GENERADO:","${report.generated_at}"\n\n`;

        csvContent += `"Equipo","Interfaz","Alias","Velocidad","Promedio IN (Mbps)","Pico IN (Mbps)","Promedio OUT (Mbps)","Pico OUT (Mbps)","Errores IN","Errores OUT","Utilizacion Pico %","Observacion"\n`;

        report.hosts.forEach(h => {
            h.interfaces.forEach(iface => {
                const maxUsage = Math.max(iface.totals.usage_pct_in, iface.totals.usage_pct_out);
                csvContent += `"${h.name}","${iface.ifname}","${iface.alias}","${iface.speed_formatted}","${iface.totals.avg_in_mbps}","${iface.totals.max_in_mbps}","${iface.totals.avg_out_mbps}","${iface.totals.max_out_mbps}","${iface.totals.total_errors_in}","${iface.totals.total_errors_out}","${maxUsage}","${iface.observation}"\n`;
            });
        });

        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", `informe_rendimiento_${report.report_id || 'export'}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    // -------------------------------------------------------------
    // GESTIÓN DE CONFIGURACIÓN DE IA
    // -------------------------------------------------------------
    function loadAIConfig() {
        $.getJSON('api_report_creator.php', { action: 'get_ai_config' }, function(resp) {
            if (resp.success && resp.data) {
                const d = resp.data;
                $('#cfg-ai-default').val(d.default_provider || 'gemini');
                $('#select-ai-provider').val(d.default_provider || 'gemini');
                if (d.gemini_api_key_masked) {
                    $('#cfg-gemini-key').val(d.gemini_api_key_masked);
                }
                $('#cfg-gemini-model').val(d.gemini_model || 'gemini-1.5-flash');
                $('#cfg-ollama-url').val(d.ollama_url || 'http://localhost:11434');
                $('#cfg-ollama-model').val(d.ollama_model || 'llama3:latest');

                if (d.has_gemini_key) {
                    $('#ai-status-indicator').html('<i class="fas fa-check-circle text-success mr-1"></i> Gemini listo');
                }
            }
        });
    }

    function testAIConnection(provider, key, model, endpoint) {
        Swal.fire({
            title: 'Probando conexión con ' + (provider === 'gemini' ? 'Google Gemini' : 'Ollama') + '...',
            text: 'Enviando petición de prueba...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        $.post('api_report_creator.php', {
            action: 'test_ai',
            provider: provider,
            gemini_api_key: key,
            model: model,
            ollama_url: endpoint
        }, function(resp) {
            Swal.close();
            if (resp.success) {
                Swal.fire('Conexión Exitosa', resp.message, 'success');
            } else {
                Swal.fire('Fallo de Conexión', resp.error || 'No se pudo comunicar con el proveedor', 'error');
            }
        }, 'json').fail(function() {
            Swal.close();
            Swal.fire('Error', 'Tiempo de espera agotado al conectar con el servicio de IA.', 'error');
        });
    }

    function saveAISettings() {
        $.post('api_report_creator.php', {
            action: 'save_ai_config',
            default_provider: $('#cfg-ai-default').val(),
            gemini_api_key: $('#cfg-gemini-key').val(),
            gemini_model: $('#cfg-gemini-model').val(),
            ollama_url: $('#cfg-ollama-url').val(),
            ollama_model: $('#cfg-ollama-model').val()
        }, function(resp) {
            if (resp.success) {
                $('#modalConfigAI').modal('hide');
                toastr.success('Configuración de IA guardada');
                loadAIConfig();
            } else {
                Swal.fire('Error', resp.error || 'No se pudo guardar la configuración', 'error');
            }
        }, 'json');
    }

    // -------------------------------------------------------------
    // HELPERS DE FECHA Y FORMATEO
    // -------------------------------------------------------------
    function setQuickDateRange(days) {
        const now = new Date();
        const from = new Date(now.getTime() - (days * 24 * 3600 * 1000));
        $('#date-from').val(formatDateForInput(from));
        $('#date-to').val(formatDateForInput(now));
    }

    function formatDateForInput(date) {
        const pad = n => n < 10 ? '0' + n : n;
        return date.getFullYear() + '-' +
               pad(date.getMonth() + 1) + '-' +
               pad(date.getDate()) + 'T' +
               pad(date.getHours()) + ':' +
               pad(date.getMinutes());
    }

    function formatSpeed(bps) {
        const b = parseFloat(bps) || 0;
        if (b >= 1000000000) return (b / 1000000000).toFixed(0) + ' Gbps';
        if (b >= 1000000) return (b / 1000000).toFixed(0) + ' Mbps';
        if (b >= 1000) return (b / 1000).toFixed(0) + ' Kbps';
        return b + ' bps';
    }

    function sanitizeId(str) {
        return (str || '').replace(/[^a-zA-Z0-9_\-]/g, '_');
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
});
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>

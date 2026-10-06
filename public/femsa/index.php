<?php
/**
 * Módulo FEMSA - Control, Entrega de Servicios y Ciclo de Proceso de Automatización
 * Ubicación: /var/www/html/VILASECA/CMDBPRnew/public/femsa/index.php
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/permissions_helper.php';
require_once __DIR__ . '/../../src/helpers.php';

require_login();

$user = current_user();
if (!has_role('SUPER_ADMIN') && !has_module_access('femsa')) {
    header("Location: ../dashboard.php");
    exit();
}

$page_title = 'Módulo FEMSA - Control y Entrega de Servicios';
$page_icon = 'fas fa-building text-danger';
$hide_content_header = true;

require_once __DIR__ . '/../partials/header.php';
?>

<style>
    .femsa-card-stats {
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        border: none;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .femsa-card-stats:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    .femsa-header-bg {
        background: linear-gradient(135deg, #ce1126 0%, #8b0000 100%);
        color: #ffffff;
        border-radius: 12px;
        padding: 22px 25px;
        margin-bottom: 25px;
        box-shadow: 0 6px 20px rgba(206,17,38,0.25);
    }
    .nav-pills-femsa .nav-link {
        font-weight: 600;
        color: #495057;
        border-radius: 8px;
        padding: 10px 20px;
        margin-right: 8px;
        background-color: #e9ecef;
        transition: all 0.2s ease;
    }
    .nav-pills-femsa .nav-link.active {
        background-color: #ce1126 !important;
        color: #ffffff !important;
        box-shadow: 0 4px 12px rgba(206,17,38,0.3);
    }
    
    /* Stepper CSS */
    .femsa-stepper-wrapper {
        background: #ffffff;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        margin-bottom: 25px;
        border-top: 4px solid #ce1126;
    }
    .femsa-stepper-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: relative;
        margin-bottom: 15px;
    }
    .femsa-stepper-bar::before {
        content: '';
        position: absolute;
        top: 20px;
        left: 30px;
        right: 30px;
        height: 4px;
        background: #e9ecef;
        z-index: 1;
    }
    .femsa-stepper-progress {
        position: absolute;
        top: 20px;
        left: 30px;
        height: 4px;
        background: linear-gradient(90deg, #ce1126, #27ae60);
        z-index: 2;
        transition: width 0.4s ease;
        width: 0%;
    }
    .femsa-step-item {
        position: relative;
        z-index: 3;
        text-align: center;
        cursor: pointer;
        flex: 1;
    }
    .femsa-step-circle {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: #ffffff;
        border: 3px solid #ced4da;
        color: #6c757d;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.95rem;
        margin: 0 auto 8px auto;
        transition: all 0.3s ease;
        box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    }
    .femsa-step-item.active .femsa-step-circle {
        border-color: #ce1126;
        background: #ce1126;
        color: #ffffff;
        transform: scale(1.15);
        box-shadow: 0 4px 12px rgba(206,17,38,0.4);
    }
    .femsa-step-item.completed .femsa-step-circle {
        border-color: #27ae60;
        background: #27ae60;
        color: #ffffff;
    }
    .femsa-step-title {
        font-size: 0.73rem;
        font-weight: 700;
        color: #6c757d;
        text-transform: uppercase;
        line-height: 1.2;
    }
    .femsa-step-item.active .femsa-step-title {
        color: #ce1126;
    }
    .femsa-step-item.completed .femsa-step-title {
        color: #27ae60;
    }
    
    /* CUADRADO ANEXO BITÁCORA - FUERA DEL CICLO DE 9 ETAPAS */
    .femsa-anexo-divider {
        width: 3px;
        height: 45px;
        background: repeating-linear-gradient(to bottom, #ce1126, #ce1126 5px, transparent 5px, transparent 10px);
        margin: 0 12px;
        align-self: center;
        z-index: 3;
    }
    .femsa-step-square {
        width: 44px;
        height: 44px;
        border-radius: 8px; /* Cuadrado con bordes suavizados */
        background: linear-gradient(135deg, #1a1a2e 0%, #ce1126 100%);
        border: 3px solid #ce1126;
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 1.1rem;
        margin: 0 auto 8px auto;
        transition: all 0.3s ease;
        box-shadow: 0 4px 10px rgba(206,17,38,0.3);
    }
    .femsa-anexo-item:hover .femsa-step-square {
        transform: scale(1.2) rotate(2deg);
        background: #ce1126;
        box-shadow: 0 6px 16px rgba(206,17,38,0.6);
    }
    .femsa-anexo-title {
        font-size: 0.75rem;
        font-weight: 800;
        color: #ce1126;
        text-transform: uppercase;
        line-height: 1.2;
        background: #fff5f5;
        padding: 2px 6px;
        border-radius: 4px;
        border: 1px solid #ffd8d8;
    }
    .bitacora-glow {
        animation: bitacoraGlowPulse 1.5s ease-in-out 3;
    }
    @keyframes bitacoraGlowPulse {
        0% { box-shadow: 0 0 0 0 rgba(206,17,38,0.7); }
        50% { box-shadow: 0 0 25px 8px rgba(206,17,38,0.5); }
        100% { box-shadow: 0 0 0 0 rgba(206,17,38,0); }
    }
    
    .femsa-form-section {
        background: #ffffff;
        border-radius: 10px;
        border-left: 5px solid #ce1126;
        padding: 18px 22px;
        margin-bottom: 22px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.03);
    }
    .femsa-form-section-title {
        font-size: 1.05rem;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
    }
    .table-femsa th {
        background-color: #f8f9fa;
        color: #343a40;
        font-weight: 700;
        border-top: none;
        text-transform: uppercase;
        font-size: 0.78rem;
        letter-spacing: 0.5px;
    }
    .timeline-item-femsa {
        border-left: 3px solid #ce1126;
        padding-left: 15px;
        margin-bottom: 20px;
        position: relative;
    }
    .timeline-item-femsa::before {
        content: '';
        position: absolute;
        left: -7px;
        top: 2px;
        width: 11px;
        height: 11px;
        border-radius: 50%;
        background-color: #ce1126;
    }
    .code-editor-box {
        font-family: 'Fira Code', 'Courier New', Courier, monospace;
        background-color: #1e1e1e;
        color: #d4d4d4;
        border-radius: 8px;
        padding: 15px;
        font-size: 0.88rem;
        line-height: 1.5;
        border: 1px solid #333;
    }
    .terminal-output {
        font-family: 'Courier New', Courier, monospace;
        background-color: #0d1117;
        color: #58a6ff;
        padding: 12px;
        border-radius: 6px;
        max-height: 200px;
        overflow-y: auto;
        font-size: 0.82rem;
        white-space: pre-wrap;
    }

    /* ===== BITÁCORA DE TRABAJOS DIARIOS ===== */
    .bitacora-link { cursor: pointer; text-decoration: underline; transition: all 0.2s; }
    .bitacora-link:hover { color: #8b0000 !important; transform: scale(1.03); text-decoration: underline; }
    .bitacora-entry-card {
        background: #fff; border-radius: 10px; border-left: 4px solid #ce1126;
        padding: 16px 20px; margin-bottom: 16px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.06); transition: transform 0.15s;
    }
    .bitacora-entry-card:hover { transform: translateY(-2px); box-shadow: 0 4px 18px rgba(0,0,0,0.1); }
    .bitacora-paste-zone {
        border: 2px dashed #ced4da; border-radius: 8px; padding: 20px;
        text-align: center; background: #f8f9fa; min-height: 80px;
        cursor: pointer; transition: all 0.2s; position: relative;
    }
    .bitacora-paste-zone:hover, .bitacora-paste-zone.drag-over {
        border-color: #ce1126; background: #fff5f5;
    }
    .bitacora-paste-zone.drag-over::after {
        content: 'Suelte la imagen aquí'; position: absolute; inset: 0;
        display: flex; align-items: center; justify-content: center;
        background: rgba(206,17,38,0.08); font-weight: 700; color: #ce1126;
        border-radius: 8px; font-size: 1rem;
    }
    .bitacora-img-gallery { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; }
    .bitacora-img-thumb {
        position: relative; width: 120px; height: 90px; border-radius: 6px;
        overflow: hidden; border: 2px solid #e9ecef; cursor: pointer;
        transition: transform 0.2s;
    }
    .bitacora-img-thumb:hover { transform: scale(1.05); border-color: #ce1126; }
    .bitacora-img-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .bitacora-img-thumb .btn-del-img {
        position: absolute; top: 2px; right: 2px; background: rgba(206,17,38,0.85);
        color: #fff; border: none; border-radius: 50%; width: 20px; height: 20px;
        font-size: 10px; line-height: 20px; text-align: center; padding: 0;
        cursor: pointer; opacity: 0; transition: opacity 0.2s;
    }
    .bitacora-img-thumb:hover .btn-del-img { opacity: 1; }
    .bitacora-badge-count {
        background: linear-gradient(135deg, #ce1126, #8b0000); color: #fff;
        border-radius: 50%; width: 20px; height: 20px; display: inline-flex;
        align-items: center; justify-content: center; font-size: 0.7rem;
        font-weight: 700; margin-left: 4px; vertical-align: middle;
    }
</style>

<div class="content-header p-0 mb-3">
    <div class="container-fluid">
        <!-- Banner FEMSA -->
        <div class="femsa-header-bg d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h3 class="font-weight-bold mb-1"><i class="fas fa-building mr-2"></i>FEMSA - Control, Entrega & GitHub Automation Pipeline</h3>
                <p class="mb-0 text-white-50" style="font-size: 0.95rem;">Gestión automática de requerimientos, adjuntos hasta 320MB y Git Push automático a GitHub</p>
            </div>
            <div class="mt-2 mt-md-0">
                <button class="btn btn-light font-weight-bold text-danger shadow-sm" onclick="showCreateForm()">
                    <i class="fas fa-plus-circle mr-1"></i> Nueva Plantilla de Servicio
                </button>
            </div>
        </div>

        <!-- Tarjetas KPI -->
        <div class="row" id="kpi-cards-container">
            <div class="col-lg-2 col-6 mb-3">
                <div class="card femsa-card-stats p-3 bg-white text-center">
                    <span class="text-muted text-uppercase font-weight-bold" style="font-size: 0.75rem;">Total Peticiones</span>
                    <h2 class="font-weight-bold text-dark mb-0 mt-1" id="kpi-total">0</h2>
                </div>
            </div>
            <div class="col-lg-2 col-6 mb-3">
                <div class="card femsa-card-stats p-3 bg-white text-center border-left border-warning" style="border-left-width: 4px !important;">
                    <span class="text-warning text-uppercase font-weight-bold" style="font-size: 0.75rem;">Borradores</span>
                    <h2 class="font-weight-bold text-warning mb-0 mt-1" id="kpi-borrador">0</h2>
                </div>
            </div>
            <div class="col-lg-2 col-6 mb-3">
                <div class="card femsa-card-stats p-3 bg-white text-center border-left border-info" style="border-left-width: 4px !important;">
                    <span class="text-info text-uppercase font-weight-bold" style="font-size: 0.75rem;">En Proceso</span>
                    <h2 class="font-weight-bold text-info mb-0 mt-1" id="kpi-enproceso">0</h2>
                </div>
            </div>
            <div class="col-lg-2 col-6 mb-3">
                <div class="card femsa-card-stats p-3 bg-white text-center border-left border-primary" style="border-left-width: 4px !important;">
                    <span class="text-primary text-uppercase font-weight-bold" style="font-size: 0.75rem;">Entregados</span>
                    <h2 class="font-weight-bold text-primary mb-0 mt-1" id="kpi-entregado">0</h2>
                </div>
            </div>
            <div class="col-lg-2 col-6 mb-3">
                <div class="card femsa-card-stats p-3 bg-white text-center border-left border-success" style="border-left-width: 4px !important;">
                    <span class="text-success text-uppercase font-weight-bold" style="font-size: 0.75rem;">Aprobados</span>
                    <h2 class="font-weight-bold text-success mb-0 mt-1" id="kpi-aprobado">0</h2>
                </div>
            </div>
            <div class="col-lg-2 col-6 mb-3">
                <div class="card femsa-card-stats p-3 bg-white text-center border-left border-secondary" style="border-left-width: 4px !important;">
                    <span class="text-secondary text-uppercase font-weight-bold" style="font-size: 0.75rem;">Cancelados</span>
                    <h2 class="font-weight-bold text-secondary mb-0 mt-1" id="kpi-cancelado">0</h2>
                </div>
            </div>
        </div>

        <!-- Pestañas Principales -->
        <ul class="nav nav-pills nav-pills-femsa mb-4" id="femsa-tabs-control">
            <li class="nav-item">
                <a class="nav-link active" href="#" data-tab="manager-pane"><i class="fas fa-list-alt mr-2"></i>Gestor de Requerimientos</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" data-tab="form-pane" id="tab-form-link"><i class="fas fa-tasks mr-2"></i>Ciclo del Proceso & Plantilla FEMSA</a>
            </li>
        </ul>

        <!-- PANE 1: GESTOR DE REQUERIMIENTOS -->
        <div id="manager-pane" class="femsa-tab-pane">
            <div class="card shadow-sm border-0 rounded-lg">
                <div class="card-header bg-white py-3">
                    <div class="row align-items-center">
                        <div class="col-md-4 mb-2 mb-md-0">
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light border-0"><i class="fas fa-search"></i></span>
                                </div>
                                <input type="text" id="search-filter" class="form-control form-control-sm border-0 bg-light" placeholder="Buscar por ticket, solicitante, analista, script...">
                            </div>
                        </div>
                        <div class="col-md-3 mb-2 mb-md-0">
                            <select id="status-filter" class="form-control form-control-sm bg-light border-0">
                                <option value="">Todos los Estados</option>
                                <option value="Borrador">Borrador</option>
                                <option value="En Proceso">En Proceso</option>
                                <option value="Entregado">Entregado</option>
                                <option value="Aprobado">Aprobado</option>
                                <option value="Cancelado">Cancelado</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2 mb-md-0">
                            <select id="activity-filter" class="form-control form-control-sm bg-light border-0">
                                <option value="">Todos los Tipos</option>
                                <option value="Soporte">Soporte</option>
                                <option value="Incidente">Incidente</option>
                                <option value="Automatización">Automatización</option>
                            </select>
                        </div>
                        <div class="col-md-2 text-right">
                            <button class="btn btn-sm btn-outline-secondary" onclick="loadRequirements()"><i class="fas fa-sync-alt mr-1"></i> Refrescar</button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-femsa align-middle mb-0" id="requirements-table">
                            <thead>
                                <tr>
                                    <th style="width: 175px;">Código REQ</th>
                                    <th style="width: 180px;">Solicitante (FEMSA)</th>
                                    <th style="width: 220px;">Analista SONDA</th>
                                    <th style="width: 135px;" class="text-center">Tipo</th>
                                    <th style="width: 220px;">Avance del Ciclo</th>
                                    <th style="width: 110px;" class="text-center">Fecha</th>
                                    <th style="width: 110px;" class="text-center">Estado</th>
                                    <th style="width: 250px;" class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="requirements-tbody">
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted"><i class="fas fa-spinner fa-spin mr-2"></i>Cargando requerimientos...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- PANE 2: CICLO DE PROCESO & FORMULARIO -->
        <div id="form-pane" class="femsa-tab-pane" style="display: none;">
            
            <!-- STEPPER DE 9 ETAPAS -->
            <div class="femsa-stepper-wrapper">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="font-weight-bold text-dark mb-0">
                        <i class="fas fa-stream text-danger mr-2"></i>Ciclo de Proceso de Automatización FEMSA / SONDA
                    </h6>
                    <span class="badge badge-danger p-2" id="current-stage-badge" style="font-size: 0.85rem;">Etapa 1 de 9: Petición de Creación</span>
                </div>

                <div class="femsa-stepper-bar">
                    <div class="femsa-stepper-progress" id="stepper-progress-bar"></div>
                    
                    <div class="femsa-step-item active" onclick="switchStage(1)">
                        <div class="femsa-step-circle"><i class="fas fa-plus"></i></div>
                        <div class="femsa-step-title">1. Petición</div>
                    </div>
                    <div class="femsa-step-item" onclick="switchStage(2)">
                        <div class="femsa-step-circle"><i class="fas fa-search"></i></div>
                        <div class="femsa-step-title">2. Análisis</div>
                    </div>
                    <div class="femsa-step-item" onclick="switchStage(3)">
                        <div class="femsa-step-circle"><i class="fas fa-users"></i></div>
                        <div class="femsa-step-title">3. Reunión</div>
                    </div>
                    <div class="femsa-step-item" onclick="switchStage(4)">
                        <div class="femsa-step-circle"><i class="fas fa-sitemap"></i></div>
                        <div class="femsa-step-title">4. Maquetación</div>
                    </div>
                    <div class="femsa-step-item" onclick="switchStage(5)">
                        <div class="femsa-step-circle"><i class="fas fa-desktop"></i></div>
                        <div class="femsa-step-title">5. Presentación</div>
                    </div>
                    <div class="femsa-step-item" onclick="switchStage(6)">
                        <div class="femsa-step-circle"><i class="fas fa-check-double"></i></div>
                        <div class="femsa-step-title">6. Revisión</div>
                    </div>
                    <div class="femsa-step-item" onclick="switchStage(7)">
                        <div class="femsa-step-circle"><i class="fas fa-rocket"></i></div>
                        <div class="femsa-step-title">7. Aprobación Prod</div>
                    </div>
                    <div class="femsa-step-item" onclick="switchStage(8)">
                        <div class="femsa-step-circle"><i class="fas fa-paperclip"></i></div>
                        <div class="femsa-step-title">8. Adjuntos & Entregables</div>
                    </div>
                    <div class="femsa-step-item" onclick="switchStage(9)">
                        <div class="femsa-step-circle"><i class="fab fa-github"></i></div>
                        <div class="femsa-step-title">9. SondaAdmin & GitHub</div>
                    </div>

                    <!-- DIVIDER: CIERRA EL CAMINO DE 9 ETAPAS -->
                    <div class="femsa-anexo-divider" title="Fin del ciclo de 9 etapas de automatización"></div>

                    <!-- CUADRADO: ANEXO BITÁCORA DEL PROYECTO (FUERA DEL CICLO) -->
                    <div class="femsa-step-item femsa-anexo-item" onclick="openBitacoraFromStepper()" title="Anexo Bitácora del Proyecto (Registro Diario)">
                        <div class="femsa-step-square"><i class="fas fa-book-open"></i></div>
                        <div class="femsa-anexo-title"><i class="fas fa-paperclip mr-1"></i>ANEXO BITÁCORA</div>
                    </div>
                </div>
            </div>

            <!-- FORMULARIO PRINCIPAL -->
            <form id="femsa-form" onsubmit="saveRequirement(event)">
                <input type="hidden" id="req_id" name="id" value="0">
                <input type="hidden" id="current_stage_input" name="current_stage" value="1">
                <input type="hidden" id="stage_data_json_input" name="stage_data_json" value="">
                
                <div class="card shadow-sm border-0 rounded-lg">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                        <h5 class="font-weight-bold text-dark mb-0" id="form-title-text">
                            <i class="fas fa-file-signature text-danger mr-2"></i>PLANTILLA DE CONTROL Y ENTREGA DE SERVICIO
                        </h5>
                        <div>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="cancelForm()"><i class="fas fa-times mr-1"></i> Cancelar</button>
                            <button type="button" class="btn btn-outline-danger btn-sm ml-1" onclick="advanceNextStage()"><i class="fas fa-arrow-right mr-1"></i> Avanzar Etapa</button>
                            <button type="submit" class="btn btn-danger btn-sm font-weight-bold ml-1"><i class="fas fa-save mr-1"></i> Guardar Requerimiento</button>
                        </div>
                    </div>
                    <div class="card-body p-4 bg-light">

                        <!-- ETAPA 1: DATOS GENERALES -->
                        <div id="stage-content-1" class="stage-view-panel">
                            
                            <!-- 1. DATOS GENERALES -->
                            <div class="femsa-form-section">
                                <div class="femsa-form-section-title">
                                    <span class="badge badge-danger mr-2" style="font-size: 0.9rem;">1</span>
                                    Datos Generales de la Solicitud / Actividad (Generación Automática)
                                </div>
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label font-weight-bold text-secondary">Fecha de Emisión <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="emission_date" name="emission_date" value="<?php echo date('Y-m-d'); ?>" required readonly style="background-color: #e9ecef;">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label font-weight-bold text-secondary">Código REQ Automático <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="text" class="form-control font-weight-bold text-danger" id="ticket_code" name="ticket_code" placeholder="REQ-FEMSA-2026-0001" required readonly style="background-color: #fff0f0;">
                                            <div class="input-group-append">
                                                <button type="button" class="btn btn-outline-secondary" onclick="fetchNextTicketCode()" title="Generar nuevo código"><i class="fas fa-sync-alt"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label font-weight-bold text-secondary">Solicitante (FEMSA) <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="femsa_requester" name="femsa_requester" placeholder="Nombre del Usuario / Líder FEMSA" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label font-weight-bold text-secondary">Analista Asignado (SONDA)</label>
                                        <input type="text" class="form-control" id="sonda_analyst" name="sonda_analyst" value="Marco Vizcaíno / Recurso en Sitio">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label font-weight-bold text-secondary">Supervisor SONDA</label>
                                        <input type="text" class="form-control" id="sonda_supervisor" name="sonda_supervisor" placeholder="Supervisor de Servicios">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label font-weight-bold text-secondary">Tipo de Actividad <span class="text-danger">*</span></label>
                                        <select class="form-control" id="activity_type" name="activity_type" required>
                                            <option value="Soporte">Soporte</option>
                                            <option value="Incidente">Incidente</option>
                                            <option value="Automatización">Automatización</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- 2. DESCRIPCIÓN DEL TRABAJO -->
                            <div class="femsa-form-section">
                                <div class="femsa-form-section-title">
                                    <span class="badge badge-danger mr-2" style="font-size: 0.9rem;">2</span>
                                    Descripción del Trabajo / Requerimiento
                                </div>
                                <div class="form-group mb-0">
                                    <label class="form-label font-weight-bold text-secondary">Detalle del caso o tarea a realizar <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="work_description" name="work_description" rows="4" placeholder="Describir detalladamente el síntoma, el análisis técnico realizado, el impacto en la operación y la solución aplicada o script desarrollado..." required></textarea>
                                </div>
                            </div>

                            <!-- 3. REGISTRO DE AUTOMATIZACIÓN -->
                            <div class="femsa-form-section">
                                <div class="femsa-form-section-title">
                                    <span class="badge badge-danger mr-2" style="font-size: 0.9rem;">3</span>
                                    Registro de Automatización o Scripts (Si aplica)
                                </div>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label font-weight-bold text-secondary">Nombre del Script / Herramienta</label>
                                        <input type="text" class="form-control" id="script_name" name="script_name" placeholder="Ej: script_automatizacion_fep.py">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label font-weight-bold text-secondary">Lenguaje / Entorno</label>
                                        <input type="text" class="form-control" id="script_language" name="script_language" placeholder="Python / PowerShell / SQL / Otro">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label font-weight-bold text-secondary">Ruta del Repositorio / Documentación</label>
                                        <input type="text" class="form-control" id="repository_url" name="repository_url" placeholder="https://github.com/blacic2016/femsa-sonda.git">
                                    </div>
                                </div>
                            </div>

                            <!-- 4. VALIDACIÓN DE LÍMITES Y ALCANCE -->
                            <div class="femsa-form-section">
                                <div class="femsa-form-section-title">
                                    <span class="badge badge-danger mr-2" style="font-size: 0.9rem;">4</span>
                                    Validación de Límites y Alcance (Premisas del Contrato)
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered bg-white mb-0">
                                        <thead class="bg-light">
                                            <tr>
                                                <th>Verificación de Frontera de Servicio</th>
                                                <th style="width: 150px;" class="text-center">Cumple</th>
                                                <th>Observación</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td class="align-middle">¿La actividad se encuentra dentro del soporte operativo y automatización estándar?</td>
                                                <td>
                                                    <select class="form-control form-control-sm" id="limit_soporte_estandar" name="limit_soporte_estandar">
                                                        <option value="SI" selected>SI</option>
                                                        <option value="NO">NO</option>
                                                        <option value="N/A">N/A</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control form-control-sm" id="obs_soporte_estandar" name="obs_soporte_estandar" value="N/A">
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="align-middle">¿Se trata de un desarrollo evolutivo mayor o integración desde cero? (Requiere cotización adicional)</td>
                                                <td>
                                                    <select class="form-control form-control-sm" id="limit_desarrollo_evolutivo" name="limit_desarrollo_evolutivo">
                                                        <option value="SI">SI</option>
                                                        <option value="NO" selected>NO</option>
                                                        <option value="N/A">N/A</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control form-control-sm" id="obs_desarrollo_evolutivo" name="obs_desarrollo_evolutivo" value="Fuera de alcance si aplica">
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="align-middle">¿Se utilizaron las herramientas, VPN y accesos provistos por FEMSA?</td>
                                                <td>
                                                    <select class="form-control form-control-sm" id="limit_herramientas_femsa" name="limit_herramientas_femsa">
                                                        <option value="SI" selected>SI</option>
                                                        <option value="NO">NO</option>
                                                        <option value="N/A">N/A</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control form-control-sm" id="obs_herramientas_femsa" name="obs_herramientas_femsa" value="N/A">
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- 5. CONFORMIDAD Y ACEPTACIÓN -->
                            <div class="femsa-form-section">
                                <div class="femsa-form-section-title">
                                    <span class="badge badge-danger mr-2" style="font-size: 0.9rem;">5</span>
                                    Conformidad y Aceptación del Servicio
                                </div>
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label font-weight-bold text-secondary">Estado del Requerimiento</label>
                                        <select class="form-control" id="status" name="status">
                                            <option value="Borrador">Borrador</option>
                                            <option value="En Proceso">En Proceso</option>
                                            <option value="Entregado">Entregado</option>
                                            <option value="Aprobado">Aprobado</option>
                                            <option value="Finalizado">Finalizado</option>
                                            <option value="Cancelado">Cancelado</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label font-weight-bold text-secondary">Aprobado Por (FEMSA)</label>
                                        <input type="text" class="form-control" id="femsa_approved_by" name="femsa_approved_by" placeholder="Nombre Responsable FEMSA">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label font-weight-bold text-secondary">Fecha Aprobación FEMSA</label>
                                        <input type="date" class="form-control" id="femsa_approval_date" name="femsa_approval_date">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label font-weight-bold text-secondary">Entregado Por (SONDA)</label>
                                        <input type="text" class="form-control" id="sonda_delivered_by" name="sonda_delivered_by" value="Marco Vizcaíno">
                                    </div>
                                </div>
                            </div>

                        </div>

                        <!-- ETAPA 2: ANÁLISIS DEL REQUERIMIENTO -->
                        <div id="stage-content-2" class="stage-view-panel" style="display: none;">
                            <div class="femsa-form-section">
                                <div class="femsa-form-section-title">
                                    <i class="fas fa-microscope text-danger mr-2"></i>Etapa 2: Análisis del Requerimiento & Diagnóstico Técnico
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label font-weight-bold text-secondary">Diagnóstico Técnico de Factibilidad</label>
                                        <textarea class="form-control stage-field" id="stg2_diagnosis" rows="4" placeholder="Evaluación técnica previa de la necesidad, factibilidad de automatización y requerimientos de entorno..."></textarea>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label font-weight-bold text-secondary">Definición de Alcance & Frontera de Servicio</label>
                                        <textarea class="form-control stage-field" id="stg2_scope" rows="4" placeholder="Especificación formal del alcance acordado, límites de intervención y prerequisitos de infraestructura..."></textarea>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label font-weight-bold text-secondary">Nivel de Complejidad Estimado</label>
                                        <select class="form-control stage-field" id="stg2_complexity">
                                            <option value="Baja (Soporte Estándar)">Baja (Soporte Estándar)</option>
                                            <option value="Media (Automatización de Proceso)">Media (Automatización de Proceso)</option>
                                            <option value="Alta (Integración de Sistemas)">Alta (Integración de Sistemas)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label font-weight-bold text-secondary">Horas Estimadas de Desarrollo</label>
                                        <input type="number" class="form-control stage-field" id="stg2_estimated_hours" placeholder="Ej: 16">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label font-weight-bold text-secondary">Fecha Finalización de Análisis</label>
                                        <input type="date" class="form-control stage-field" id="stg2_date">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ETAPA 3: REUNIÓN DE ANÁLISIS CON CLIENTE -->
                        <div id="stage-content-3" class="stage-view-panel" style="display: none;">
                            <div class="femsa-form-section">
                                <div class="femsa-form-section-title">
                                    <i class="fas fa-handshake text-danger mr-2"></i>Etapa 3: Reunión de Análisis con Cliente de Solución (FEMSA / SONDA)
                                </div>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label font-weight-bold text-secondary">Fecha y Hora de la Reunión</label>
                                        <input type="datetime-local" class="form-control stage-field" id="stg3_meeting_date">
                                    </div>
                                    <div class="col-md-8 mb-3">
                                        <label class="form-label font-weight-bold text-secondary">Asistentes / Participantes de la Sesión</label>
                                        <input type="text" class="form-control stage-field" id="stg3_attendees" placeholder="Ej: Juan Pérez (FEMSA), Marco Vizcaíno (SONDA), Supervisor SONDA">
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label font-weight-bold text-secondary">Minuta, Acuerdos y Ajustes de la Reunión</label>
                                        <textarea class="form-control stage-field" id="stg3_minutes" rows="5" placeholder="Registrar los puntos acordados en la sesión de alineación con el cliente, observaciones planteadas por FEMSA y validaciones realizadas..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ETAPA 4: MAQUETACIÓN DE LA SOLUCIÓN -->
                        <div id="stage-content-4" class="stage-view-panel" style="display: none;">
                            <div class="femsa-form-section">
                                <div class="femsa-form-section-title">
                                    <i class="fas fa-cubes text-danger mr-2"></i>Etapa 4: Maquetación & Arquitectura de la Solución
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label font-weight-bold text-secondary">Diagrama de Flujo / Lógica del Proceso (Maquetación)</label>
                                        <textarea class="form-control stage-field" id="stg4_layout_diagram" rows="5" placeholder="Describir los bloques lógicos de entrada, procesamiento, validaciones de seguridad y salida de la automatización..."></textarea>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label font-weight-bold text-secondary">Definición de Entradas, Parámetros y Salidas</label>
                                        <textarea class="form-control stage-field" id="stg4_specifications" rows="5" placeholder="Especificación técnica de APIs, bases de datos objetivo, archivos de configuración e insumos requeridos..."></textarea>
                                    </div>
                                </div>

                                <hr class="my-3">

                                <!-- SUBIDA DE IMAGEN DE MAQUETACIÓN Y CONEXIÓN A DIAGRAMAS -->
                                <div class="row">
                                    <!-- Col 1: Adjuntar Imagen de Maquetación -->
                                    <div class="col-md-6 mb-3">
                                        <div class="card h-100 border shadow-sm p-3 bg-white">
                                            <h6 class="font-weight-bold text-dark mb-2"><i class="fas fa-image text-danger mr-2"></i>Adjuntar Imagen de Maquetación / Mockup</h6>
                                            <p class="text-muted small mb-3">Sube una captura de pantalla, diseño conceptual o diagrama gráfico de la maquetación.</p>
                                            <input type="hidden" class="stage-field" id="stg4_image_path" name="stg4_image_path" value="">
                                            
                                            <div class="input-group mb-2">
                                                <input type="file" id="stg4_image_file" class="form-control-file border p-1 rounded" accept="image/*,.vsd,.pdf">
                                                <div class="input-group-append">
                                                    <button type="button" class="btn btn-sm btn-danger font-weight-bold" onclick="uploadLayoutImage()"><i class="fas fa-upload mr-1"></i> Subir Imagen</button>
                                                </div>
                                            </div>

                                            <div id="stg4_image_preview" class="mt-2 text-center p-2 border rounded bg-light" style="display: none;">
                                                <img id="stg4_img_element" src="" class="img-fluid rounded mb-2 shadow-sm" style="max-height: 180px; object-fit: contain;">
                                                <div>
                                                    <a id="stg4_img_link" href="#" target="_blank" class="btn btn-xs btn-outline-primary"><i class="fas fa-external-link-alt mr-1"></i> Ver Imagen Completa</a>
                                                    <button type="button" class="btn btn-xs btn-outline-danger ml-1" onclick="removeLayoutImage()"><i class="fas fa-trash-alt mr-1"></i> Quitar</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Col 2: Conectar con Diagramas de la Plataforma -->
                                    <div class="col-md-6 mb-3">
                                        <div class="card h-100 border shadow-sm p-3 bg-white">
                                            <h6 class="font-weight-bold text-dark mb-2"><i class="fas fa-sitemap text-primary mr-2"></i>Conectar con Diagrama Registrado (Visio / BPMN)</h6>
                                            <p class="text-muted small mb-3">Vincule esta maquetación directamente con uno de los diagramas creados en el sistema.</p>
                                            
                                            <input type="hidden" class="stage-field" id="stg4_diagram_id" value="">
                                            <input type="hidden" class="stage-field" id="stg4_diagram_type" value="">

                                            <div class="form-group mb-2">
                                                <select class="form-control form-control-sm" id="stg4_diagram_select" onchange="onDiagramSelected()">
                                                    <option value="">-- Seleccionar Diagrama Creado --</option>
                                                </select>
                                            </div>

                                            <div id="stg4_diagram_info_box" class="p-2 border rounded bg-light mt-2" style="display: none;">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="font-weight-bold text-dark" id="stg4_diag_title">Titulo</span>
                                                    <span class="badge badge-info" id="stg4_diag_badge">Visio</span>
                                                </div>
                                                <p class="text-muted small mb-2" id="stg4_diag_desc">Descripción...</p>
                                                <a id="stg4_diag_open_btn" href="#" target="_blank" class="btn btn-xs btn-success btn-block font-weight-bold">
                                                    <i class="fas fa-external-link-alt mr-1"></i> Abrir Diagrama en Editor/Visualizador
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ETAPA 5: PRIMERA PRESENTACIÓN -->
                        <div id="stage-content-5" class="stage-view-panel" style="display: none;">
                            <div class="femsa-form-section">
                                <div class="femsa-form-section-title">
                                    <i class="fas fa-chalkboard-teacher text-danger mr-2"></i>Etapa 5: Primera Presentación de Solución de Requerimiento
                                </div>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label font-weight-bold text-secondary">Fecha de Presentación Demo</label>
                                        <input type="date" class="form-control stage-field" id="stg5_demo_date">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label font-weight-bold text-secondary">Resultado de la Presentación</label>
                                        <select class="form-control stage-field" id="stg5_result">
                                            <option value="Aprobado Sin Cambios">Aprobado Sin Cambios</option>
                                            <option value="Aprobado Con Ajustes Menores">Aprobado Con Ajustes Menores</option>
                                            <option value="Requiere Nueva Revisión">Requiere Nueva Revisión</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label font-weight-bold text-secondary">Líder Evaluador FEMSA</label>
                                        <input type="text" class="form-control stage-field" id="stg5_evaluator" placeholder="Nombre del Evaluador">
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label font-weight-bold text-secondary">Feedback & Ajustes Solicitados en la Demo</label>
                                        <textarea class="form-control stage-field" id="stg5_feedback" rows="4" placeholder="Comentarios recogidos durante la primera demostración interactiva de la automatización..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ETAPA 6: REVISIÓN DE RESULTADOS -->
                        <div id="stage-content-6" class="stage-view-panel" style="display: none;">
                            <div class="femsa-form-section">
                                <div class="femsa-form-section-title">
                                    <i class="fas fa-clipboard-check text-danger mr-2"></i>Etapa 6: Revisión de Resultados & Pruebas QA
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label font-weight-bold text-secondary">Resumen de Pruebas Ejecutadas</label>
                                        <textarea class="form-control stage-field" id="stg6_tests" rows="4" placeholder="Detalle de casos de prueba ejecutados en ambiente de prueba o staging..."></textarea>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label font-weight-bold text-secondary">Métricas de Eficiencia / Tiempo Ahorrado</label>
                                        <textarea class="form-control stage-field" id="stg6_metrics" rows="4" placeholder="Reducción de tiempos operativos, eliminación de errores manuales y eficiencia lograda..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ETAPA 7: APROBACIÓN PRODUCCIÓN -->
                        <div id="stage-content-7" class="stage-view-panel" style="display: none;">
                            <div class="femsa-form-section">
                                <div class="femsa-form-section-title">
                                    <i class="fas fa-paper-plane text-danger mr-2"></i>Etapa 7: Aprobación de Puesta en Producción
                                </div>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label font-weight-bold text-secondary">Fecha Programada Pase a Producción</label>
                                        <input type="date" class="form-control stage-field" id="stg7_deploy_date">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label font-weight-bold text-secondary">Aprobador Oficial FEMSA</label>
                                        <input type="text" class="form-control stage-field" id="stg7_approver" placeholder="Nombre y Cargo del Aprobador">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label font-weight-bold text-secondary">Estado Ventana de Cambio</label>
                                        <select class="form-control stage-field" id="stg7_window_status">
                                            <option value="Aprobada">Aprobada</option>
                                            <option value="Pendiente Ventana">Pendiente Ventana</option>
                                            <option value="Ejecutada en Producción">Ejecutada en Producción</option>
                                        </select>
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label font-weight-bold text-secondary">Checklist y Consideraciones de Despliegue</label>
                                        <textarea class="form-control stage-field" id="stg7_checklist" rows="3" placeholder="Verificación de permisos, accesos VPN, respaldos y rollback plan en producción..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ETAPA 8: ADJUNTOS & ENTREGABLES -->
                        <div id="stage-content-8" class="stage-view-panel" style="display: none;">
                            <div class="femsa-form-section">
                                <div class="femsa-form-section-title d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-paperclip text-danger mr-2"></i>Etapa 8: Archivos Adjuntos & Entregables (Soporta hasta 320MB por archivo)</span>
                                    <span class="badge badge-info p-2" style="font-size: 0.8rem;">msg, txt, pdf, jpg, png, vsd, py, json, ps1, zip...</span>
                                </div>

                                <div class="row mb-4">
                                    <!-- Carga de Archivos Locales/Físicos -->
                                    <div class="col-md-7 mb-3 mb-md-0">
                                        <div class="card p-3 h-100 bg-white border">
                                            <h6 class="font-weight-bold text-dark mb-2"><i class="fas fa-file-upload text-danger mr-1"></i> Adjuntar Archivo del Equipo (Máx 320MB)</h6>
                                            <div class="form-group mb-2">
                                                <input type="file" id="attachment_file" class="form-control-file border p-1 rounded w-100">
                                            </div>
                                            <div class="form-group mb-2">
                                                <input type="text" id="attachment_desc" class="form-control form-control-sm" placeholder="Ej: Diagrama Visio (.vsd), script Python o correo (.msg)">
                                            </div>
                                            <button type="button" class="btn btn-danger btn-sm font-weight-bold mt-auto" onclick="uploadAttachment()">
                                                <i class="fas fa-upload mr-1"></i> Adjuntar Archivo
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Enlazar Archivo de GitLab -->
                                    <div class="col-md-5">
                                        <div class="card p-3 h-100 bg-white border">
                                            <h6 class="font-weight-bold text-dark mb-2"><i class="fab fa-gitlab text-warning mr-1"></i> Enlazar Archivo desde Repositorio GitLab</h6>
                                            <p class="text-muted small mb-2">Asocie un código fuente existente en el módulo de GitLab como entregable del requerimiento.</p>
                                            <div class="form-group mb-2">
                                                <select class="form-control form-control-sm" id="gitlab_file_select">
                                                    <option value="">-- Seleccionar Archivo de GitLab --</option>
                                                </select>
                                            </div>
                                            <div class="form-group mb-2">
                                                <input type="text" id="gitlab_link_desc" class="form-control form-control-sm" placeholder="Descripción breve del script/entregable...">
                                            </div>
                                            <button type="button" class="btn btn-warning btn-sm font-weight-bold text-dark mt-auto" onclick="attachGitlabFile()">
                                                <i class="fas fa-link mr-1"></i> Enlazar Archivo GitLab
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Tabla de Archivos Adjuntos -->
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover bg-white mb-0">
                                        <thead class="bg-light">
                                            <tr>
                                                <th>Nombre del Archivo / Enlace</th>
                                                <th>Origen / Tamaño</th>
                                                <th>Descripción</th>
                                                <th>Subido Por</th>
                                                <th>Fecha / Hora</th>
                                                <th style="width: 130px;" class="text-center">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody id="attachments-tbody">
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">Guarde o cargue el requerimiento para ver archivos adjuntos.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- ETAPA 9: PLATAFORMA SONDAADMIN & GIT PUSH A GITHUB -->
                        <div id="stage-content-9" class="stage-view-panel" style="display: none;">
                            <div class="femsa-form-section bg-dark text-white border-left-0" style="border-top: 4px solid #ce1126;">
                                <div class="femsa-form-section-title text-white d-flex justify-content-between align-items-center">
                                    <span><i class="fab fa-github text-danger mr-2"></i>Etapa 9: Plataforma SondaAdmin - Desarrollo & Git Push a GitHub</span>
                                    <span class="badge badge-danger p-2" style="font-size: 0.8rem;"><i class="fas fa-code-branch mr-1"></i>https://github.com/blacic2016/femsa-sonda.git</span>
                                </div>
                                <p class="text-white-50 small mb-3">Publicación automática de scripts de automatización (Python, PowerShell, SQL, JSON) con control de versiones y confirmación de commit en GitHub.</p>

                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label font-weight-bold text-white-50">Nombre del Archivo / Script</label>
                                        <input type="text" class="form-control bg-secondary text-white border-0 stage-field" id="stg9_script_filename" placeholder="script_automatizacion_fep.py">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label font-weight-bold text-white-50">Etiqueta de Versión</label>
                                        <input type="text" class="form-control bg-secondary text-white border-0 stage-field" id="stg9_version_tag" value="v1.0.0" placeholder="v1.0.0">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label font-weight-bold text-white-50">Entorno Runner SondaAdmin</label>
                                        <select class="form-control bg-secondary text-white border-0 stage-field" id="stg9_environment">
                                            <option value="SondaAdmin Python 3.10 Execution Node">SondaAdmin Python 3.10 Node</option>
                                            <option value="SondaAdmin PowerShell Core 7.3 Runner">SondaAdmin PowerShell Core 7.3</option>
                                            <option value="SondaAdmin SQL Server Automation Agent">SondaAdmin SQL Agent</option>
                                        </select>
                                    </div>

                                    <div class="col-md-12 mb-3">
                                        <label class="form-label font-weight-bold text-white-50">Mensaje de Commit para GitHub</label>
                                        <input type="text" class="form-control bg-secondary text-white border-0 stage-field" id="stg9_commit_msg" placeholder="Ej: [REQ-FEMSA-2026-0001] Actualización de script de automatización fep v1.0.0">
                                    </div>

                                    <div class="col-md-12 mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="form-label font-weight-bold text-white-50 mb-0">Código Completo de la Automatización</label>
                                            <button type="button" class="btn btn-sm btn-danger font-weight-bold" onclick="executeGitPush()">
                                                <i class="fab fa-github mr-1"></i> Ejecutar Git Push a GitHub
                                            </button>
                                        </div>
                                        <textarea class="form-control code-editor-box stage-field" id="stg9_code_content" rows="10" placeholder="# Inserta aquí el código completo desarrollado para el servicio FEMSA / SONDA&#10;import os&#10;import sys&#10;import datetime&#10;&#10;def main():&#10;    print('[SondaAdmin] Iniciando proceso de automatizacion FEMSA...')&#10;    # Logica de ejecucion&#10;&#10;if __name__ == '__main__':&#10;    main()"></textarea>
                                    </div>

                                    <!-- Consola de Salida Git -->
                                    <div class="col-md-12 mb-3" id="git-output-box" style="display: none;">
                                        <label class="form-label font-weight-bold text-success"><i class="fas fa-terminal mr-1"></i>Respuesta / Log de Git Push en Servidor:</label>
                                        <pre class="terminal-output" id="git-terminal-text"></pre>
                                    </div>

                                    <!-- Historial de Commits / Pushes -->
                                    <div class="col-md-12 mb-2">
                                        <h6 class="font-weight-bold text-white mb-2"><i class="fas fa-history text-danger mr-2"></i>Historial de Pushes a GitHub para este Requerimiento</h6>
                                        <div class="table-responsive">
                                            <table class="table table-dark table-striped table-bordered text-white mb-0" style="font-size: 0.85rem;">
                                                <thead>
                                                    <tr>
                                                        <th>Versión</th>
                                                        <th>Script</th>
                                                        <th>Commit Hash</th>
                                                        <th>Mensaje</th>
                                                        <th>Autor</th>
                                                        <th>Fecha / Hora</th>
                                                        <th>Estado</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="git-logs-tbody">
                                                    <tr>
                                                        <td colspan="7" class="text-center text-muted py-3">No hay registros de git push para este requerimiento.</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    <!-- APROBACIÓN Y CIERRE DE TICKET (ESTADO FINALIZADO) -->
                                    <div class="col-md-12 mt-4">
                                        <div class="card p-3 bg-white border text-dark rounded shadow-sm">
                                            <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                                                <h5 class="font-weight-bold text-success mb-0">
                                                    <i class="fas fa-check-circle mr-2"></i>Conformidad, Aprobación Final y Cierre del Servicio
                                                </h5>
                                                <span class="badge badge-success p-2 font-weight-bold" style="font-size: 0.85rem;">Etapa Final (9/9)</span>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="font-weight-bold text-dark">Aprobado Por (Responsable / Solicitante FEMSA)</label>
                                                    <input type="text" class="form-control stage-field" id="stg9_femsa_approver" placeholder="Nombre del Usuario / Responsable FEMSA que aprueba">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="font-weight-bold text-dark">Fecha de Aprobación FEMSA</label>
                                                    <input type="date" class="form-control stage-field" id="stg9_approval_date">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="font-weight-bold text-dark">Entregado Por (Analista / Técnico SONDA)</label>
                                                    <input type="text" class="form-control stage-field" id="stg9_sonda_deliverer" placeholder="Marco Vizcaíno / Recurso en Sitio">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="font-weight-bold text-dark">Fecha de Entrega SONDA</label>
                                                    <input type="date" class="form-control stage-field" id="stg9_delivery_date">
                                                </div>
                                                <div class="col-md-12 mb-3">
                                                    <label class="font-weight-bold text-dark">Observaciones / Comentarios Finales de Cierre</label>
                                                    <textarea class="form-control stage-field" id="stg9_closing_notes" rows="2" placeholder="Describa la conformidad de la entrega, visto bueno o comentarios finales del servicio..."></textarea>
                                                </div>
                                                <div class="col-md-12 text-right">
                                                    <button type="button" class="btn btn-success btn-lg font-weight-bold shadow-sm" onclick="approveAndFinalizeRequirement()">
                                                        <i class="fas fa-check-circle mr-2"></i> Aprobar y Finalizar Requerimiento (Cierre de Ticket)
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>

                    </div>
                    <div class="card-footer bg-white d-flex justify-content-between align-items-center py-3">
                        <button type="button" class="btn btn-outline-secondary" onclick="prevStage()"><i class="fas fa-arrow-left mr-1"></i> Etapa Anterior</button>
                        <div>
                            <button type="button" class="btn btn-secondary" onclick="cancelForm()"><i class="fas fa-times mr-1"></i> Cancelar</button>
                            <button type="button" class="btn btn-outline-danger font-weight-bold ml-1" onclick="advanceNextStage()"><i class="fas fa-arrow-right mr-1"></i> Avanzar Etapa</button>
                            <button type="submit" class="btn btn-danger font-weight-bold ml-1"><i class="fas fa-save mr-1"></i> Guardar Requerimiento</button>
                        </div>
                    </div>
                </div>
        </div>
    </div>
</div>

<!-- Modal Bitácora del Proyecto -->
<div class="modal fade" id="bitacoraModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document" style="max-width: 90vw;">
        <div class="modal-content border-0 shadow-lg rounded-lg">
            <div class="modal-header py-3 text-white" style="background: linear-gradient(135deg, #1a1a2e 0%, #ce1126 100%);">
                <div class="d-flex align-items-center">
                    <i class="fas fa-book-open fa-2x mr-3 text-white"></i>
                    <div>
                        <h5 class="modal-title font-weight-bold mb-0">
                            BITÁCORA DEL PROYECTO — REGISTRO DE ACTIVIDADES DIARIAS
                        </h5>
                        <small class="text-white-50">
                            Requerimiento: <strong id="bitacora-ticket-code" class="text-warning">---</strong> | Se presenta como ANEXO al final del informe en la impresión
                        </small>
                    </div>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4" style="background: #f8f9fa; max-height: 78vh; overflow-y: auto;">
                
                <!-- Botón Toggle Formulario de Nueva Entrada -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="font-weight-bold text-dark mb-0">
                        <i class="fas fa-history text-danger mr-2"></i>Historial de Actividades (Organizado por Fecha)
                    </h6>
                    <div>
                        <button type="button" class="btn btn-danger btn-sm font-weight-bold shadow-sm" onclick="toggleBitacoraForm()">
                            <i class="fas fa-plus-circle mr-1"></i><span id="btn-toggle-form-text">Nueva Entrada de Bitácora</span>
                        </button>
                    </div>
                </div>

                <!-- Formulario Nueva Entrada (Colapsable) -->
                <div class="card border-0 shadow-sm mb-4 rounded-lg" id="bitacora-form-container" style="display:none; border-top: 4px solid #ce1126 !important;">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="font-weight-bold text-dark mb-0">
                            <i class="fas fa-edit text-danger mr-2"></i>Registrar Actividad Diaria
                        </h6>
                        <button type="button" class="btn btn-xs btn-outline-secondary" onclick="resetBitacoraForm()">
                            <i class="fas fa-eraser mr-1"></i>Limpiar
                        </button>
                    </div>
                    <div class="card-body">
                        <input type="hidden" id="bitacora_entry_id" value="0">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="font-weight-bold text-secondary"><i class="far fa-calendar-alt mr-1"></i>Fecha de Actividad</label>
                                <input type="date" class="form-control" id="bitacora_fecha" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-9 mb-3">
                                <label class="font-weight-bold text-secondary"><i class="fas fa-tag mr-1"></i>Tema / Asunto <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="bitacora_tema" placeholder="Ej: Configuración de servidor, Revisión de scripts, Reunión técnica con FEMSA...">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="font-weight-bold text-secondary"><i class="fas fa-align-left mr-1"></i>Descripción de Trabajo Realizado <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="bitacora_descripcion" rows="3" placeholder="Detalle las actividades realizadas, acuerdos, hallazgos o novedades del día..."></textarea>
                            </div>

                            <!-- ZONA DE IMÁGENES -->
                            <div class="col-md-6 mb-3">
                                <label class="font-weight-bold text-secondary"><i class="fas fa-image mr-1 text-success"></i>Imágenes (Pegue Ctrl+V o arrastre)</label>
                                <div class="bitacora-paste-zone" id="bitacora-paste-zone">
                                    <i class="fas fa-paste fa-2x text-muted mb-2 d-block"></i>
                                    <span class="text-muted small">Ctrl+V para pegar capturas de pantalla o arrastre imágenes aquí</span>
                                    <input type="file" id="bitacora_image_file" accept="image/*" class="d-none" multiple>
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-xs btn-outline-success" onclick="$('#bitacora_image_file').click()">
                                            <i class="fas fa-file-image mr-1"></i>Seleccionar imágenes
                                        </button>
                                    </div>
                                </div>
                                <div class="bitacora-img-gallery" id="bitacora-pending-images"></div>
                            </div>

                            <!-- ZONA DE DOCUMENTOS -->
                            <div class="col-md-6 mb-3">
                                <label class="font-weight-bold text-secondary"><i class="fas fa-file-alt mr-1 text-primary"></i>Documentos (Excel, Word, PPT, PDF, TXT, Scripts...)</label>
                                <div class="bitacora-paste-zone" id="bitacora-doc-zone" style="border-color: #007bff40;">
                                    <i class="fas fa-file-upload fa-2x text-muted mb-2 d-block"></i>
                                    <span class="text-muted small">Arrastre documentos aquí (Excel, Word, PPT, PDF, CFG, Python, etc.)</span>
                                    <input type="file" id="bitacora_doc_file" class="d-none" multiple
                                           accept=".xlsx,.xls,.csv,.doc,.docx,.ppt,.pptx,.pdf,.txt,.cfg,.config,.r,.json,.xml,.yaml,.yml,.log,.ini,.sql,.py,.ps1,.sh,.bat,.vsd,.msg,.eml,.zip,.rar,.7z,.tar,.gz">
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-xs btn-outline-primary" onclick="$('#bitacora_doc_file').click()">
                                            <i class="fas fa-folder-open mr-1"></i>Seleccionar documentos
                                        </button>
                                    </div>
                                </div>
                                <div id="bitacora-pending-docs" class="mt-2"></div>
                            </div>
                        </div>
                        <div class="text-right mt-2">
                            <button type="button" class="btn btn-secondary btn-sm mr-1" onclick="toggleBitacoraForm(false)">Cancelar</button>
                            <button type="button" class="btn btn-danger font-weight-bold px-4" onclick="saveBitacoraEntry()">
                                <i class="fas fa-save mr-1"></i>Guardar Entrada de Bitácora
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Lista de Entradas Organizada por Fecha -->
                <div id="bitacora-entries-container">
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-book-open fa-3x mb-3 d-block" style="opacity:0.15;"></i>
                        No hay actividades registradas en la bitácora de este proyecto
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white py-2 justify-content-between">
                <span class="text-muted small"><i class="fas fa-info-circle text-danger mr-1"></i>Total de Actividades: <strong id="bitacora-count">0</strong></span>
                <button type="button" class="btn btn-secondary btn-sm px-4" data-dismiss="modal">Cerrar Modal</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Historial de Cambios -->
<div class="modal fade" id="historyModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white py-3">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-history text-danger mr-2"></i>Historial de Cambios - Ticket <span id="modal-ticket-code"></span></h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4" style="max-height: 500px; overflow-y: auto;">
                <div id="history-timeline-container">
                    <!-- Contenido dinámico -->
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Visor de Imagen -->
<div class="modal fade" id="imageViewerModal" tabindex="-1" role="dialog" style="z-index: 1060;">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg bg-dark">
            <div class="modal-header border-0 py-2">
                <h6 class="modal-title text-white font-weight-bold"><i class="fas fa-image mr-2"></i>Vista de Imagen</h6>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body text-center p-2">
                <img id="imageViewerImg" src="" class="img-fluid rounded" style="max-height: 70vh;">
            </div>
            <div class="modal-footer border-0 py-1">
                <button type="button" class="btn btn-sm btn-outline-light" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentRequirements = [];
let currentActiveStage = 1;
let currentStageData = {};

const STAGE_TITLES = {
    1: "Etapa 1 de 9: Petición de Creación de Requerimiento",
    2: "Etapa 2 de 9: Análisis del Requerimiento & Diagnóstico",
    3: "Etapa 3 de 9: Reunión de Análisis con Cliente",
    4: "Etapa 4 de 9: Maquetación & Arquitectura de la Solución",
    5: "Etapa 5 de 9: Primera Presentación de Solución",
    6: "Etapa 6 de 9: Revisión de Resultados & Pruebas QA",
    7: "Etapa 7 de 9: Aprobación de Puesta en Producción",
    8: "Etapa 8 de 9: Archivos Adjuntos & Entregables (Hasta 320MB)",
    9: "Etapa 9 de 9: Plataforma SondaAdmin & Git Push a GitHub"
};

$(document).ready(function() {
    loadRequirements();
    loadDiagramsCatalog();
    loadGitlabFilesCatalog();

    // Filtros dinámicos
    $('#search-filter, #status-filter, #activity-filter').on('input change', function() {
        filterTable();
    });

    // Cambio de Pestañas Principales
    $('#femsa-tabs-control .nav-link').on('click', function(e) {
        e.preventDefault();
        $('#femsa-tabs-control .nav-link').removeClass('active');
        $(this).addClass('active');

        let targetTab = $(this).data('tab');
        $('.femsa-tab-pane').hide();
        $('#' + targetTab).show();
    });

    // Evento al modificar cualquier campo de etapa
    $('.stage-field').on('input change', function() {
        serializeStageData();
    });
});

// Obtener código de ticket automático desde el servidor
function fetchNextTicketCode() {
    $.get('api.php?action=get_next_code', function(res) {
        if (res.success) {
            $('#ticket_code').val(res.next_code);
            if (!$('#emission_date').val()) {
                $('#emission_date').val(res.today);
            }
        }
    });
}

// Cargar requerimientos desde backend API
function loadRequirements() {
    $('#requirements-tbody').html('<tr><td colspan="8" class="text-center py-5 text-muted"><i class="fas fa-spinner fa-spin mr-2"></i>Cargando requerimientos...</td></tr>');
    
    $.get('api.php?action=list', function(res) {
        if (res.success) {
            currentRequirements = res.data;
            updateKPIs(res.stats);
            renderRequirementsTable(currentRequirements);
        } else {
            toastr.error('Error al cargar datos: ' + res.error);
        }
    }).fail(function() {
        toastr.error('Error de comunicación con el servidor');
    });
}

// Actualizar tarjetas de KPI
function updateKPIs(stats) {
    if (!stats) return;
    $('#kpi-total').text(stats.total || 0);
    $('#kpi-borrador').text(stats.borrador || 0);
    $('#kpi-enproceso').text(stats.en_proceso || 0);
    $('#kpi-entregado').text(stats.entregado || 0);
    $('#kpi-aprobado').text(stats.aprobado || 0);
    $('#kpi-cancelado').text(stats.cancelado || 0);
}

// Renderizar tabla
function renderRequirementsTable(data) {
    if (!data || data.length === 0) {
        $('#requirements-tbody').html('<tr><td colspan="8" class="text-center py-5 text-muted"><i class="fas fa-folder-open fa-2x mb-2 d-block"></i>No se encontraron requerimientos registrados</td></tr>');
        return;
    }

    let html = '';
    data.forEach(item => {
        let badgeClass = 'badge-secondary';
        if (item.status === 'Borrador') badgeClass = 'badge-warning';
        if (item.status === 'En Proceso') badgeClass = 'badge-info';
        if (item.status === 'Entregado') badgeClass = 'badge-primary';
        if (item.status === 'Aprobado' || item.status === 'Finalizado') badgeClass = 'badge-success';
        if (item.status === 'Cancelado') badgeClass = 'badge-dark';

        let actBadge = 'badge-light border';
        if (item.activity_type === 'Automatización') actBadge = 'badge-danger';
        if (item.activity_type === 'Incidente') actBadge = 'badge-warning text-dark';
        if (item.activity_type === 'Soporte') actBadge = 'badge-info';

        let stageNum = parseInt(item.current_stage || 1);
        let stagePercent = Math.round((stageNum / 9) * 100);
        let dateFormatted = item.emission_date ? new Date(item.emission_date.replace(/-/g, '/')).toLocaleDateString('es-ES') : '-';

        let safeCode = escapeHtml(item.ticket_code).replace(/'/g, "\\'");
        html += `
        <tr>
            <td class="align-middle font-weight-bold text-danger text-nowrap cursor-pointer" onclick="editRequirement(${item.id})" title="Click para abrir Requerimiento"><i class="fas fa-file-alt mr-1" style="font-size:0.8rem;"></i>${escapeHtml(item.ticket_code)}</td>
            <td class="align-middle">
                <strong>${escapeHtml(item.femsa_requester)}</strong>
            </td>
            <td class="align-middle">
                <small class="d-block font-weight-bold text-dark"><i class="fas fa-user-check mr-1 text-success"></i>${escapeHtml(item.sonda_analyst)}</small>
            </td>
            <td class="align-middle text-center">
                <span class="badge ${actBadge}">${escapeHtml(item.activity_type)}</span>
            </td>
            <td class="align-middle">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <small class="font-weight-bold text-dark">Etapa ${stageNum}/9: ${getShortStageName(stageNum)}</small>
                    <small class="text-danger font-weight-bold">${stagePercent}%</small>
                </div>
                <div class="progress" style="height: 6px; border-radius: 4px;">
                    <div class="progress-bar bg-danger" role="progressbar" style="width: ${stagePercent}%;"></div>
                </div>
            </td>
            <td class="align-middle text-center"><small class="font-weight-bold text-secondary">${dateFormatted}</small></td>
            <td class="align-middle text-center">
                <span class="badge ${badgeClass} p-1 px-2">${escapeHtml(item.status)}</span>
            </td>
            <td class="align-middle text-center">
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-danger font-weight-bold shadow-sm" onclick="openBitacora(${item.id}, '${safeCode}')" title="Abrir Bitácora de Trabajos Diarios">
                        <i class="fas fa-book-open mr-1"></i>Bitácora
                    </button>
                    <a href="print.php?id=${item.id}" target="_blank" class="btn btn-light text-danger border" title="Imprimir / PDF">
                        <i class="fas fa-print"></i>
                    </a>
                    <button type="button" class="btn btn-light text-primary border" onclick="editRequirement(${item.id})" title="Abrir / Modificar Requerimiento">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="btn btn-light text-info border" onclick="viewHistory(${item.id}, '${safeCode}')" title="Historial de Cambios">
                        <i class="fas fa-history"></i>
                    </button>
                    <button type="button" class="btn btn-light text-secondary border" onclick="deleteRequirement(${item.id}, '${safeCode}')" title="Eliminar">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            </td>
        </tr>`;
    });

    $('#requirements-tbody').html(html);
}

function getShortStageName(stage) {
    const names = {
        1: "Petición",
        2: "Análisis",
        3: "Reunión Cliente",
        4: "Maquetación",
        5: "Presentación",
        6: "Revisión QA",
        7: "Aprobación Prod",
        8: "Adjuntos",
        9: "SondaAdmin / Git"
    };
    return names[stage] || "Petición";
}

// Filtrar tabla en cliente
function filterTable() {
    let q = $('#search-filter').val().toLowerCase();
    let status = $('#status-filter').val();
    let act = $('#activity-filter').val();

    let filtered = currentRequirements.filter(item => {
        let matchesQ = !q || (
            item.ticket_code.toLowerCase().includes(q) ||
            item.femsa_requester.toLowerCase().includes(q) ||
            item.sonda_analyst.toLowerCase().includes(q) ||
            item.work_description.toLowerCase().includes(q) ||
            (item.script_name && item.script_name.toLowerCase().includes(q))
        );
        let matchesStatus = !status || item.status === status;
        let matchesAct = !act || item.activity_type === act;
        return matchesQ && matchesStatus && matchesAct;
    });

    renderRequirementsTable(filtered);
}

// Cambiar de Etapa en el Stepper
function switchStage(stageNum) {
    currentActiveStage = stageNum;
    $('#current_stage_input').val(stageNum);
    $('#current-stage-badge').text(STAGE_TITLES[stageNum] || `Etapa ${stageNum} de 9`);

    // Actualizar stepper UI (excluyendo el cuadrado de anexo bitácora)
    $('.femsa-step-item:not(.femsa-anexo-item)').removeClass('active completed');
    $('.femsa-step-item:not(.femsa-anexo-item)').each(function(idx) {
        let step = idx + 1;
        if (step === stageNum) {
            $(this).addClass('active');
        } else if (step < stageNum) {
            $(this).addClass('completed');
        }
    });

    let progressPercent = ((stageNum - 1) / 8) * 100;
    $('#stepper-progress-bar').css('width', progressPercent + '%');

    // Cambiar panel visible
    $('.stage-view-panel').hide();
    $('#stage-content-' + stageNum).show();

    let reqId = $('#req_id').val();
    if (reqId > 0) {
        if (stageNum === 8) loadAttachments(reqId);
        if (stageNum === 9) loadGitLogs(reqId);
    }
}

// Acción al hacer clic en el Cuadrado "ANEXO BITÁCORA" del Stepper
function openBitacoraFromStepper() {
    let reqId = $('#req_id').val();
    let ticketCode = $('#ticket_code').val() || '';
    
    $('#bitacora-project-section').show();
    if (reqId > 0) {
        $('#bitacora_req_id').val(reqId);
        $('#bitacora-ticket-code').text(ticketCode);
        loadBitacoraEntries(reqId);
    }
    
    let $card = $('#bitacora-project-section .card').first();
    $card.addClass('bitacora-glow');
    setTimeout(() => $card.removeClass('bitacora-glow'), 4500);

    $('html, body').animate({
        scrollTop: $("#bitacora-project-section").offset().top - 80
    }, 600);
}

function advanceNextStage() {
    if (currentActiveStage < 9) {
        switchStage(currentActiveStage + 1);
        toastr.info(`Avanzado a la ${STAGE_TITLES[currentActiveStage]}`);
    } else {
        toastr.success('¡El requerimiento ya se encuentra en la etapa final de SondaAdmin y GitHub!');
    }
}

function prevStage() {
    if (currentActiveStage > 1) {
        switchStage(currentActiveStage - 1);
    }
}

let diagramsList = [];

// Cargar catálogo de diagramas (Visio / BPMN)
function loadDiagramsCatalog() {
    $.get('api.php?action=list_diagrams', function(res) {
        if (res.success) {
            diagramsList = res.data || [];
            let options = '<option value="">-- Seleccionar Diagrama Creado --</option>';
            diagramsList.forEach(d => {
                let icon = d.type === 'bpmn' ? '⚙️' : '📐';
                options += `<option value="${d.type}_${d.id}">${icon} [${d.type_label}] ${escapeHtml(d.title)}</option>`;
            });
            $('#stg4_diagram_select').html(options);

            if (currentStageData && currentStageData.stg4_diagram_id && currentStageData.stg4_diagram_type) {
                $('#stg4_diagram_select').val(`${currentStageData.stg4_diagram_type}_${currentStageData.stg4_diagram_id}`);
                renderDiagramInfoBox(currentStageData.stg4_diagram_type, currentStageData.stg4_diagram_id);
            }
        }
    });
}

function onDiagramSelected() {
    let val = $('#stg4_diagram_select').val();
    if (!val) {
        $('#stg4_diagram_id').val('');
        $('#stg4_diagram_type').val('');
        $('#stg4_diagram_info_box').hide();
        serializeStageData();
        return;
    }
    let parts = val.split('_');
    let type = parts[0];
    let id = parts[1];
    $('#stg4_diagram_type').val(type);
    $('#stg4_diagram_id').val(id);
    renderDiagramInfoBox(type, id);
    serializeStageData();
}

function renderDiagramInfoBox(type, id) {
    let diag = diagramsList.find(d => d.type === type && parseInt(d.id) === parseInt(id));
    if (diag) {
        $('#stg4_diag_title').text(diag.title);
        $('#stg4_diag_badge').text(diag.type_label);
        $('#stg4_diag_desc').text(diag.description || 'Sin descripción adicional');
        let openUrl = type === 'bpmn' ? `../bpmn.php?id=${id}` : `../visio.php?id=${id}`;
        $('#stg4_diag_open_btn').attr('href', openUrl);
        $('#stg4_diagram_info_box').show();
    }
}

// Subir imagen de maquetación en Etapa 4
function uploadLayoutImage() {
    let reqId = $('#req_id').val();
    let ticketCode = $('#ticket_code').val() || 'REQ-FEMSA';
    let fileInput = $('#stg4_image_file')[0];

    if (!fileInput.files || fileInput.files.length === 0) {
        toastr.warning('Por favor seleccione una imagen para la maquetación');
        return;
    }

    let formData = new FormData();
    formData.append('action', 'upload_layout_image');
    formData.append('requirement_id', reqId);
    formData.append('ticket_code', ticketCode);
    formData.append('layout_image', fileInput.files[0]);

    toastr.info('Subiendo imagen de maquetación...');

    $.ajax({
        url: 'api.php',
        type: 'POST',
        data: formData,
        contentType: false,
        processData: false,
        success: function(res) {
            if (res.success) {
                toastr.success(res.message);
                $('#stg4_image_path').val(res.file_path);
                let imgUrl = `../../${res.file_path}`;
                $('#stg4_img_element').attr('src', imgUrl);
                $('#stg4_img_link').attr('href', imgUrl);
                $('#stg4_image_preview').show();
                serializeStageData();
                if (reqId && reqId > 0) {
                    loadAttachments(reqId);
                }
            } else {
                toastr.error('Error al cargar imagen: ' + res.error);
            }
        },
        error: function() {
            toastr.error('Error de red al cargar la imagen');
        }
    });
}

function removeLayoutImage() {
    $('#stg4_image_path').val('');
    $('#stg4_image_file').val('');
    $('#stg4_image_preview').hide();
    serializeStageData();
}

// Cargar catálogo de archivos GitLab en Etapa 8
function loadGitlabFilesCatalog() {
    $.get('api.php?action=list_gitlab_files', function(res) {
        if (res.success) {
            let files = res.data || [];
            let options = '<option value="">-- Seleccionar Archivo de GitLab --</option>';
            files.forEach(f => {
                options += `<option value="${f.id}">📄 ${escapeHtml(f.filename)} (${(f.file_type || 'code').toUpperCase()})</option>`;
            });
            $('#gitlab_file_select').html(options);
        }
    });
}

// Enlazar archivo de GitLab en Etapa 8
function attachGitlabFile() {
    let reqId = $('#req_id').val();
    let gitlabFileId = $('#gitlab_file_select').val();
    let desc = $('#gitlab_link_desc').val();

    if (!reqId || reqId <= 0) {
        toastr.warning('Guarde o seleccione un requerimiento antes de enlazar archivos de GitLab');
        return;
    }

    if (!gitlabFileId) {
        toastr.warning('Por favor seleccione un archivo de GitLab para enlazar');
        return;
    }

    $.post('api.php', {
        action: 'add_gitlab_attachment',
        requirement_id: reqId,
        gitlab_file_id: gitlabFileId,
        description: desc
    }, function(res) {
        if (res.success) {
            toastr.success(res.message);
            $('#gitlab_file_select').val('');
            $('#gitlab_link_desc').val('');
            loadAttachments(reqId);
        } else {
            toastr.error('Error al enlazar: ' + res.error);
        }
    });
}

// Serializar todos los datos de etapas a JSON
function serializeStageData() {
    let data = {
        stg2_diagnosis: $('#stg2_diagnosis').val(),
        stg2_scope: $('#stg2_scope').val(),
        stg2_complexity: $('#stg2_complexity').val(),
        stg2_estimated_hours: $('#stg2_estimated_hours').val(),
        stg2_date: $('#stg2_date').val(),
        
        stg3_meeting_date: $('#stg3_meeting_date').val(),
        stg3_attendees: $('#stg3_attendees').val(),
        stg3_minutes: $('#stg3_minutes').val(),

        stg4_layout_diagram: $('#stg4_layout_diagram').val(),
        stg4_specifications: $('#stg4_specifications').val(),
        stg4_image_path: $('#stg4_image_path').val(),
        stg4_diagram_id: $('#stg4_diagram_id').val(),
        stg4_diagram_type: $('#stg4_diagram_type').val(),

        stg5_demo_date: $('#stg5_demo_date').val(),
        stg5_result: $('#stg5_result').val(),
        stg5_evaluator: $('#stg5_evaluator').val(),
        stg5_feedback: $('#stg5_feedback').val(),

        stg6_tests: $('#stg6_tests').val(),
        stg6_metrics: $('#stg6_metrics').val(),

        stg7_deploy_date: $('#stg7_deploy_date').val(),
        stg7_approver: $('#stg7_approver').val(),
        stg7_window_status: $('#stg7_window_status').val(),
        stg7_checklist: $('#stg7_checklist').val(),

        stg9_script_filename: $('#stg9_script_filename').val(),
        stg9_version_tag: $('#stg9_version_tag').val(),
        stg9_environment: $('#stg9_environment').val(),
        stg9_commit_msg: $('#stg9_commit_msg').val(),
        stg9_code_content: $('#stg9_code_content').val(),
        stg9_femsa_approver: $('#stg9_femsa_approver').val(),
        stg9_approval_date: $('#stg9_approval_date').val(),
        stg9_sonda_deliverer: $('#stg9_sonda_deliverer').val(),
        stg9_delivery_date: $('#stg9_delivery_date').val(),
        stg9_closing_notes: $('#stg9_closing_notes').val()
    };

    currentStageData = data;
    $('#stage_data_json_input').val(JSON.stringify(data));
}

// Cargar datos de etapas desde JSON
function deserializeStageData(jsonString) {
    if (!jsonString) return;
    try {
        let data = typeof jsonString === 'string' ? JSON.parse(jsonString) : jsonString;
        currentStageData = data;

        for (let key in data) {
            if ($('#' + key).length > 0) {
                $('#' + key).val(data[key]);
            }
        }

        // Actualizar vista previa de imagen en Etapa 4
        if (data.stg4_image_path) {
            let imgUrl = `../../${data.stg4_image_path}`;
            $('#stg4_img_element').attr('src', imgUrl);
            $('#stg4_img_link').attr('href', imgUrl);
            $('#stg4_image_preview').show();
        } else {
            $('#stg4_image_preview').hide();
        }

        // Actualizar vista previa de diagrama en Etapa 4
        if (data.stg4_diagram_id && data.stg4_diagram_type) {
            $('#stg4_diagram_select').val(`${data.stg4_diagram_type}_${data.stg4_diagram_id}`);
            renderDiagramInfoBox(data.stg4_diagram_type, data.stg4_diagram_id);
        } else {
            $('#stg4_diagram_info_box').hide();
        }
    } catch (e) {
        console.error('Error deserializing stage data', e);
    }
}

// Mostrar formulario en modo creación con ticket_code automático
function showCreateForm() {
    $('#femsa-form')[0].reset();
    $('.stage-field').val('');
    $('#stg4_image_preview').hide();
    $('#stg4_diagram_info_box').hide();
    $('#req_id').val(0);
    $('#form-title-text').html('<i class="fas fa-file-signature text-danger mr-2"></i>NUEVA PLANTILLA DE CONTROL Y ENTREGA DE SERVICIO');

    fetchNextTicketCode();
    switchStage(1);

    // Resetear formulario de bitácora
    $('#bitacora_req_id').val(0);
    resetBitacoraForm();

    // Cambiar tab
    $('#femsa-tabs-control a[data-tab="form-pane"]').click();
}

// Cancelar Formulario
function cancelForm() {
    $('#femsa-tabs-control a[data-tab="manager-pane"]').click();
}

// Editar Requerimiento
function editRequirement(id) {
    $.get(`api.php?action=get&id=${id}`, function(res) {
        if (res.success) {
            let data = res.data;
            $('#req_id').val(data.id);
            $('#ticket_code').val(data.ticket_code);
            $('#emission_date').val(data.emission_date);
            $('#femsa_requester').val(data.femsa_requester);
            $('#sonda_analyst').val(data.sonda_analyst);
            $('#sonda_supervisor').val(data.sonda_supervisor);
            $('#activity_type').val(data.activity_type);
            $('#work_description').val(data.work_description);
            $('#script_name').val(data.script_name);
            $('#script_language').val(data.script_language);
            $('#repository_url').val(data.repository_url);
            
            $('#limit_soporte_estandar').val(data.limit_soporte_estandar);
            $('#obs_soporte_estandar').val(data.obs_soporte_estandar);
            $('#limit_desarrollo_evolutivo').val(data.limit_desarrollo_evolutivo);
            $('#obs_desarrollo_evolutivo').val(data.obs_desarrollo_evolutivo);
            $('#limit_herramientas_femsa').val(data.limit_herramientas_femsa);
            $('#obs_herramientas_femsa').val(data.obs_herramientas_femsa);

            $('#status').val(data.status);
            $('#femsa_approved_by').val(data.femsa_approved_by);
            $('#femsa_approval_date').val(data.femsa_approval_date);
            $('#sonda_delivered_by').val(data.sonda_delivered_by);

            // Cargar datos de etapas
            deserializeStageData(data.stage_data_json);

            let reqStage = parseInt(data.current_stage || 1);
            switchStage(reqStage);

            // Cargar adjuntos y logs git
            loadAttachments(data.id);
            loadGitLogs(data.id);

            // Cargar Bitácora de Trabajos Diarios para Modal
            $('#bitacora_req_id').val(data.id);
            $('#bitacora-ticket-code').text(data.ticket_code);
            resetBitacoraForm();

            $('#form-title-text').html(`<i class="fas fa-edit text-danger mr-2"></i>REQUERIMIENTO ${escapeHtml(data.ticket_code)} - ETAPA ${reqStage}/9`);

            // Activar tab formulario
            $('#femsa-tabs-control a[data-tab="form-pane"]').click();
        } else {
            toastr.error('Error al obtener datos: ' + res.error);
        }
    });
}

// Guardar Requerimiento
function saveRequirement(e) {
    e.preventDefault();
    serializeStageData();
    let formData = $('#femsa-form').serialize() + '&action=save';

    $.post('api.php', formData, function(res) {
        if (res.success) {
            toastr.success(res.message || 'Requerimiento guardado correctamente');
            loadRequirements();
            $('#femsa-tabs-control a[data-tab="manager-pane"]').click();
        } else {
            toastr.error('Error: ' + res.error);
        }
    }).fail(function() {
        toastr.error('Error de comunicación al guardar');
    });
}

// Subir Adjunto (Soporta hasta 320MB)
function uploadAttachment() {
    let reqId = $('#req_id').val();
    let ticketCode = $('#ticket_code').val();
    let desc = $('#attachment_desc').val();
    let fileInput = $('#attachment_file')[0];

    if (!fileInput.files || fileInput.files.length === 0) {
        toastr.warning('Por favor seleccione un archivo para adjuntar');
        return;
    }

    let formData = new FormData();
    formData.append('action', 'upload_attachment');
    formData.append('requirement_id', reqId);
    formData.append('ticket_code', ticketCode);
    formData.append('description', desc);
    formData.append('attachment_file', fileInput.files[0]);

    toastr.info('Subiendo archivo al servidor...');

    $.ajax({
        url: 'api.php',
        type: 'POST',
        data: formData,
        contentType: false,
        processData: false,
        success: function(res) {
            if (res.success) {
                toastr.success(res.message);
                $('#attachment_file').val('');
                $('#attachment_desc').val('');
                loadAttachments(reqId);
            } else {
                toastr.error('Error al subir: ' + res.error);
            }
        },
        error: function() {
            toastr.error('Error de red al subir el archivo');
        }
    });
}

// Cargar Tabla de Adjuntos
function loadAttachments(reqId) {
    if (!reqId || reqId <= 0) return;
    $.get(`api.php?action=list_attachments&requirement_id=${reqId}`, function(res) {
        if (res.success) {
            let files = res.data;
            if (!files || files.length === 0) {
                $('#attachments-tbody').html('<tr><td colspan="6" class="text-center text-muted py-4">No hay archivos adjuntos en este requerimiento</td></tr>');
                return;
            }
            let html = '';
            files.forEach(f => {
                let dateFmt = new Date(f.created_at.replace(/-/g, '/')).toLocaleString('es-ES');
                let isGitlab = f.mime_type === 'gitlab/link';

                let fileTitle = isGitlab 
                    ? `<span class="badge badge-dark mr-1"><i class="fab fa-gitlab text-warning"></i> GitLab</span> <strong class="text-dark">${escapeHtml(f.original_name.replace('[GitLab] ', ''))}</strong>`
                    : `<i class="fas fa-file-alt text-danger mr-2"></i><span class="font-weight-bold text-dark">${escapeHtml(f.original_name)}</span>`;

                let sizeCol = isGitlab 
                    ? `<span class="badge badge-warning text-dark font-weight-bold"><i class="fas fa-code mr-1"></i>Código GitLab</span>`
                    : `<small>${(f.file_size / (1024 * 1024)).toFixed(2)} MB</small>`;

                let gitlabFileId = f.stored_name.replace('gitlab_file_', '');
                let actionButtons = isGitlab
                    ? `<a href="../plugins/gitlab/index.php?file_id=${gitlabFileId}" target="_blank" class="btn btn-xs btn-warning font-weight-bold text-dark border shadow-sm" title="Abrir y editar en el editor de GitLab"><i class="fas fa-external-link-alt mr-1"></i> Abrir GitLab</a>
                       <button type="button" class="btn btn-xs btn-light border text-danger ml-1" onclick="deleteAttachment(${f.id})" title="Eliminar"><i class="fas fa-trash-alt"></i></button>`
                    : `<a href="../../${f.file_path}" target="_blank" download="${escapeHtml(f.original_name)}" class="btn btn-xs btn-light border text-primary" title="Descargar"><i class="fas fa-download"></i></a>
                       <button type="button" class="btn btn-xs btn-light border text-danger ml-1" onclick="deleteAttachment(${f.id})" title="Eliminar"><i class="fas fa-trash-alt"></i></button>`;

                html += `
                <tr>
                    <td class="align-middle">${fileTitle}</td>
                    <td class="align-middle">${sizeCol}</td>
                    <td class="align-middle"><small>${escapeHtml(f.description || '-')}</small></td>
                    <td class="align-middle"><small><i class="fas fa-user mr-1 text-muted"></i>${escapeHtml(f.uploaded_by)}</small></td>
                    <td class="align-middle"><small>${dateFmt}</small></td>
                    <td class="align-middle text-center">${actionButtons}</td>
                </tr>
                `;
            });
            $('#attachments-tbody').html(html);
        }
    });
}

// Eliminar Adjunto
function deleteAttachment(attId) {
    if (confirm('¿Desea eliminar este archivo adjunto?')) {
        $.post('api.php', { action: 'delete_attachment', attachment_id: attId }, function(res) {
            if (res.success) {
                toastr.success(res.message);
                loadAttachments($('#req_id').val());
            } else {
                toastr.error('Error: ' + res.error);
            }
        });
    }
}

// Ejecutar Git Push a GitHub
function executeGitPush() {
    let reqId = $('#req_id').val();
    let ticketCode = $('#ticket_code').val();
    let scriptName = $('#stg9_script_filename').val() || $('#script_name').val() || 'script_automatizacion.py';
    let versionTag = $('#stg9_version_tag').val() || 'v1.0.0';
    let commitMsg = $('#stg9_commit_msg').val() || `[${ticketCode}] Actualización de script ${scriptName} ${versionTag}`;
    let codeContent = $('#stg9_code_content').val();

    if (!codeContent) {
        toastr.warning('Por favor escriba o pegue el código de la automatización antes de hacer push');
        return;
    }

    toastr.info('Ejecutando Git Push a GitHub (https://github.com/blacic2016/femsa-sonda.git)...');
    $('#git-output-box').show();
    $('#git-terminal-text').text('Iniciando git add, git commit y git push origin main...\nEsperando respuesta de GitHub...');

    $.post('api.php', {
        action: 'git_push',
        requirement_id: reqId,
        ticket_code: ticketCode,
        script_name: scriptName,
        version_tag: versionTag,
        commit_message: commitMsg,
        code_content: codeContent
    }, function(res) {
        if (res.success) {
            toastr.success(res.message);
            $('#git-terminal-text').text(`SUCCESS! Commit Hash: ${res.commit_hash}\n\n[LOG DE RESPUESTA DE GITHUB]:\n${res.git_output}`);
            loadGitLogs(reqId);
            loadAttachments(reqId);
        } else {
            toastr.error('Error en Git Push');
            $('#git-terminal-text').text(`ERROR AL PUBLICAR EN GITHUB:\n${res.error}\n\n[LOG COMPLETO]:\n${res.git_output}`);
        }
    }).fail(function() {
        toastr.error('Error de comunicación con el servidor al ejecutar Git Push');
        $('#git-terminal-text').text('Error de conexión con el servidor.');
    });
}

// Cargar Historial de Pushes Git
function loadGitLogs(reqId) {
    if (!reqId || reqId <= 0) return;
    $.get(`api.php?action=get_git_logs&requirement_id=${reqId}`, function(res) {
        if (res.success) {
            let logs = res.data;
            if (!logs || logs.length === 0) {
                $('#git-logs-tbody').html('<tr><td colspan="7" class="text-center text-muted py-3">No hay registros de git push para este requerimiento.</td></tr>');
                return;
            }
            let html = '';
            logs.forEach(l => {
                let badgeStatus = l.status === 'SUCCESS' ? 'badge-success' : 'badge-danger';
                let dateFmt = new Date(l.created_at.replace(/-/g, '/')).toLocaleString('es-ES');

                html += `
                <tr>
                    <td><span class="badge badge-info">${escapeHtml(l.version_tag)}</span></td>
                    <td class="font-weight-bold text-danger">${escapeHtml(l.script_name)}</td>
                    <td><code>${escapeHtml(l.commit_hash || 'HEAD')}</code></td>
                    <td>${escapeHtml(l.commit_message)}</td>
                    <td><small><i class="fas fa-user mr-1 text-muted"></i>${escapeHtml(l.pushed_by)}</small></td>
                    <td><small>${dateFmt}</small></td>
                    <td><span class="badge ${badgeStatus}">${l.status}</span></td>
                </tr>
                `;
            });
            $('#git-logs-tbody').html(html);
        }
    });
}

// Eliminar Requerimiento
function deleteRequirement(id, code) {
    Swal.fire({
        title: '¿Confirmar eliminación?',
        text: `¿Está seguro de eliminar el requerimiento ${code}? Esta acción quedará registrada en el historial.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ce1126',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('api.php', { action: 'delete', id: id }, function(res) {
                if (res.success) {
                    toastr.success(res.message);
                    loadRequirements();
                } else {
                    toastr.error('Error: ' + res.error);
                }
            });
        }
    });
}

// Ver Historial de Cambios
function viewHistory(id, code) {
    $('#modal-ticket-code').text(code);
    $('#history-timeline-container').html('<div class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin mr-2"></i>Cargando historial...</div>');
    $('#historyModal').modal('show');

    $.get(`api.php?action=get&id=${id}`, function(res) {
        if (res.success) {
            let history = res.history;
            if (!history || history.length === 0) {
                $('#history-timeline-container').html('<div class="text-center py-4 text-muted">No se registran cambios en el historial.</div>');
                return;
            }

            let html = '';
            history.forEach(h => {
                let actionBadge = 'badge-info';
                if (h.action === 'CREACION') actionBadge = 'badge-success';
                if (h.action === 'MODIFICACION') actionBadge = 'badge-primary';
                if (h.action === 'CAMBIO_ESTADO' || h.action === 'CAMBIO_ETAPA') actionBadge = 'badge-warning text-dark';
                if (h.action === 'ELIMINACION') actionBadge = 'badge-danger';

                let dateFmt = new Date(h.created_at.replace(/-/g, '/')).toLocaleString('es-ES');

                html += `
                <div class="timeline-item-femsa">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="badge ${actionBadge}">${h.action}</span>
                        <small class="text-muted"><i class="far fa-clock mr-1"></i>${dateFmt}</small>
                    </div>
                    <div class="font-weight-bold text-dark" style="font-size: 0.9rem;">
                        <i class="fas fa-user-circle text-secondary mr-1"></i>${escapeHtml(h.changed_by_user)}
                    </div>
                    <div class="text-secondary mt-1" style="font-size: 0.88rem;">
                        ${escapeHtml(h.change_details)}
                    </div>
                </div>
                `;
            });

            $('#history-timeline-container').html(html);
        } else {
            $('#history-timeline-container').html('<div class="text-center text-danger py-4">Error al cargar historial.</div>');
        }
    });
}

function approveAndFinalizeRequirement() {
    let approver = $('#stg9_femsa_approver').val() || $('#femsa_approved_by').val();
    let appDate = $('#stg9_approval_date').val() || $('#femsa_approval_date').val() || new Date().toISOString().split('T')[0];
    let deliverer = $('#stg9_sonda_deliverer').val() || $('#sonda_delivered_by').val() || $('#sonda_analyst').val();
    let delDate = $('#stg9_delivery_date').val() || $('#sonda_delivery_date').val() || new Date().toISOString().split('T')[0];

    if (!approver || approver.trim() === '') {
        Swal.fire('Atención', 'Por favor ingrese el nombre del responsable de FEMSA que aprueba el requerimiento.', 'warning');
        return;
    }

    Swal.fire({
        title: '¿Aprobar y Finalizar Requerimiento?',
        text: `El ticket pasará a estado FINALIZADO y completará el 100% del flujo de trabajo (Etapa 9/9).`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-check-circle mr-1"></i> Sí, Aprobar y Finalizar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            // Sincronizar campos principales
            $('#status').val('Finalizado');
            $('#current_stage').val(9);
            $('#femsa_approved_by').val(approver);
            $('#femsa_approval_date').val(appDate);
            $('#sonda_delivered_by').val(deliverer);
            $('#sonda_delivery_date').val(delDate);

            // Sincronizar campos de etapa 9
            $('#stg9_femsa_approver').val(approver);
            $('#stg9_approval_date').val(appDate);
            $('#stg9_sonda_deliverer').val(deliverer);
            $('#stg9_delivery_date').val(delDate);

            serializeStageData();
            
            // Enviar formulario principal
            $('#femsa-form').submit();
        }
    });
}

function escapeHtml(text) {
    if (!text) return '';
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// ===== BITÁCORA DEL PROYECTO (REGISTRO DE ACTIVIDADES DIARIAS) =====
let pendingBitacoraImages = []; // Array de Data URLs base64
let pendingBitacoraDocs = [];   // Array de objetos File

function openBitacora(reqId, ticketCode) {
    openBitacoraModal(reqId, ticketCode);
}

function openBitacoraModal(reqId, ticketCode) {
    let currentReqId = reqId || $('#req_id').val();
    let currentCode = ticketCode || $('#ticket_code').val() || '---';

    if (!currentReqId || currentReqId <= 0) {
        toastr.warning('Por favor seleccione o guarde primero un Requerimiento para abrir su Bitácora de Trabajos Diarios.');
        return;
    }

    $('#bitacora_req_id').val(currentReqId);
    $('#bitacora-ticket-code').text(currentCode);
    resetBitacoraForm();

    loadBitacoraEntries(currentReqId);
    $('#bitacoraModal').modal('show');
}

function toggleBitacoraForm(show) {
    let $container = $('#bitacora-form-container');
    if (show === undefined) show = !$container.is(':visible');
    
    if (show) {
        $container.removeClass('d-none').slideDown(300);
        $('#btn-toggle-form-text').text('Ocultar Formulario');
    } else {
        $container.slideUp(300);
        $('#btn-toggle-form-text').text('Nueva Entrada de Bitácora');
    }
}

function openBitacoraFromStepper() {
    let reqId = $('#req_id').val();
    let ticketCode = $('#ticket_code').val() || '---';
    if (!reqId || reqId <= 0) {
        toastr.warning('Seleccione o guarde primero el Requerimiento para abrir la Bitácora.');
        return;
    }
    openBitacoraModal(reqId, ticketCode);
}

function resetBitacoraForm() {
    $('#bitacora_entry_id').val(0);
    $('#bitacora_fecha').val(new Date().toISOString().split('T')[0]);
    $('#bitacora_tema').val('');
    $('#bitacora_descripcion').val('');
    $('#bitacora-pending-images').html('');
    $('#bitacora-pending-docs').html('');
    pendingBitacoraImages = [];
    pendingBitacoraDocs = [];
}

function loadBitacoraEntries(reqId) {
    if (!reqId || reqId <= 0) return;
    $.get(`api.php?action=list_bitacora&requirement_id=${reqId}`, function(res) {
        if (!res.success) { toastr.error(res.error); return; }
        let entries = res.data || [];
        $('#bitacora-count').text(entries.length);
        if (entries.length === 0) {
            $('#bitacora-entries-container').html('<div class="text-center py-5 text-muted"><i class="fas fa-book-open fa-3x mb-3 d-block" style="opacity:0.2;"></i>No hay actividades registradas en la bitácora de este proyecto</div>');
            return;
        }

        // Agrupar entradas por fecha
        let groupedByDate = {};
        entries.forEach(e => {
            let f = e.fecha || 'Sin Fecha';
            if (!groupedByDate[f]) groupedByDate[f] = [];
            groupedByDate[f].push(e);
        });

        // Ordenar fechas en orden descendente (más recientes primero)
        let sortedDates = Object.keys(groupedByDate).sort().reverse();

        let html = '';
        sortedDates.forEach(dateStr => {
            let dateObj = new Date(dateStr.replace(/-/g, '/'));
            let fechaFmt = dateObj.toLocaleDateString('es-ES', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            fechaFmt = fechaFmt.charAt(0).toUpperCase() + fechaFmt.slice(1);

            html += `
            <div class="bitacora-date-group mb-4">
                <div class="p-2 px-3 rounded mb-3 d-flex align-items-center justify-content-between shadow-sm" style="background: linear-gradient(135deg, #1a1a2e 0%, #2b2e4a 100%); color: #fff;">
                    <span class="font-weight-bold" style="font-size: 0.95rem;">
                        <i class="far fa-calendar-alt text-danger mr-2"></i>${fechaFmt}
                    </span>
                    <span class="badge badge-danger p-2" style="font-size: 0.8rem;">
                        ${groupedByDate[dateStr].length} ${groupedByDate[dateStr].length === 1 ? 'actividad' : 'actividades'}
                    </span>
                </div>
                <div class="pl-2">
            `;

            groupedByDate[dateStr].forEach(e => {
                let createdFmt = e.created_at ? new Date(e.created_at.replace(/-/g, '/')).toLocaleString('es-ES') : '';
                
                let attachments = e.images || [];
                let imagesList = attachments.filter(a => (a.doc_type || 'image') === 'image');
                let docsList = attachments.filter(a => (a.doc_type || 'image') === 'document');

                let imagesHtml = '';
                if (imagesList.length > 0) {
                    imagesHtml = '<div class="bitacora-img-gallery mt-2">';
                    imagesList.forEach(img => {
                        imagesHtml += `<div class="bitacora-img-thumb" onclick="viewBitacoraImage('../../${img.file_path}')">
                            <img src="../../${img.file_path}" alt="Bitácora imagen">
                            <button type="button" class="btn-del-img" onclick="event.stopPropagation(); deleteBitacoraImage(${img.id}, ${reqId})" title="Eliminar imagen">&times;</button>
                        </div>`;
                    });
                    imagesHtml += '</div>';
                }

                let docsHtml = '';
                if (docsList.length > 0) {
                    docsHtml = '<div class="mt-2 d-flex flex-wrap gap-2">';
                    docsList.forEach(doc => {
                        let ext = (doc.original_name || doc.file_name).split('.').pop().toLowerCase();
                        let iconClass = 'fa-file-alt text-info';
                        if (['xlsx','xls','csv'].includes(ext)) iconClass = 'fa-file-excel text-success';
                        else if (['doc','docx'].includes(ext)) iconClass = 'fa-file-word text-primary';
                        else if (['ppt','pptx'].includes(ext)) iconClass = 'fa-file-powerpoint text-warning';
                        else if (['pdf'].includes(ext)) iconClass = 'fa-file-pdf text-danger';
                        else if (['cfg','config','r','py','sh','txt','json','sql','xml','log','ini','yaml','yml'].includes(ext)) iconClass = 'fa-file-code text-dark';

                        let sizeKb = doc.file_size ? (doc.file_size / 1024).toFixed(1) + ' KB' : '';

                        docsHtml += `<div class="badge badge-light border p-2 mr-2 mb-2 shadow-sm d-inline-flex align-items-center" style="font-size:0.85rem;">
                            <i class="fas ${iconClass} fa-lg mr-2"></i>
                            <div class="text-left mr-2">
                                <a href="../../${doc.file_path}" target="_blank" download="${escapeHtml(doc.original_name || doc.file_name)}" class="font-weight-bold text-dark text-decoration-none">
                                    ${escapeHtml(doc.original_name || doc.file_name)}
                                </a>
                                <small class="text-muted d-block">${sizeKb}</small>
                            </div>
                            <a href="../../${doc.file_path}" target="_blank" download="${escapeHtml(doc.original_name || doc.file_name)}" class="btn btn-xs btn-outline-primary mr-1" title="Descargar"><i class="fas fa-download"></i></a>
                            <button type="button" class="btn btn-xs btn-outline-danger" onclick="deleteBitacoraImage(${doc.id}, ${reqId})" title="Eliminar documento"><i class="fas fa-trash-alt"></i></button>
                        </div>`;
                    });
                    docsHtml += '</div>';
                }

                html += `<div class="bitacora-entry-card mb-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <span class="badge badge-light border text-dark font-weight-bold"><i class="fas fa-user text-secondary mr-1"></i>${escapeHtml(e.created_by)}</span>
                        </div>
                        <div>
                            <button type="button" class="btn btn-xs btn-outline-danger" onclick="deleteBitacoraEntry(${e.id})" title="Eliminar entrada"><i class="fas fa-trash-alt"></i></button>
                        </div>
                    </div>
                    <h6 class="font-weight-bold text-dark mb-1"><i class="fas fa-tag text-danger mr-2"></i>${escapeHtml(e.tema)}</h6>
                    <p class="text-secondary mb-1" style="white-space:pre-wrap;font-size:0.92rem;">${escapeHtml(e.descripcion)}</p>
                    ${imagesHtml}
                    ${docsHtml}
                    <div class="text-right mt-1"><small class="text-muted"><i class="far fa-clock mr-1"></i>Hora de registro: ${createdFmt}</small></div>
                </div>`;
            });

            html += `
                </div>
            </div>
            `;
        });

        $('#bitacora-entries-container').html(html);
    });
}

function saveBitacoraEntry() {
    let reqId = $('#bitacora_req_id').val() || $('#req_id').val();
    if (!reqId || reqId <= 0) {
        toastr.warning('Debe seleccionar o guardar primero el Requerimiento antes de registrar entradas de bitácora.');
        return;
    }
    let entryId = $('#bitacora_entry_id').val();
    let fecha = $('#bitacora_fecha').val();
    let tema = $('#bitacora_tema').val().trim();
    let descripcion = $('#bitacora_descripcion').val().trim();

    if (!tema || !descripcion) {
        toastr.warning('Complete los campos obligatorios: Tema y Descripción');
        return;
    }

    $.post('api.php', {
        action: 'save_bitacora',
        bitacora_id: entryId,
        requirement_id: reqId,
        fecha: fecha,
        tema: tema,
        descripcion: descripcion
    }, function(res) {
        if (res.success) {
            toastr.success(res.message);
            let savedId = res.id;

            let uploadPromises = [];

            // Subir imágenes base64 pendientes
            if (pendingBitacoraImages.length > 0) {
                pendingBitacoraImages.forEach(b64 => {
                    uploadPromises.push(
                        $.post('api.php', {
                            action: 'upload_bitacora_image',
                            bitacora_id: savedId,
                            requirement_id: reqId,
                            image_base64: b64
                        })
                    );
                });
            }

            // Subir documentos pendientes
            if (pendingBitacoraDocs.length > 0) {
                pendingBitacoraDocs.forEach(fileObj => {
                    if (!fileObj) return;
                    let formData = new FormData();
                    formData.append('action', 'upload_bitacora_document');
                    formData.append('bitacora_id', savedId);
                    formData.append('requirement_id', reqId);
                    formData.append('bitacora_doc', fileObj);

                    uploadPromises.push(
                        $.ajax({
                            url: 'api.php',
                            type: 'POST',
                            data: formData,
                            contentType: false,
                            processData: false
                        })
                    );
                });
            }

            if (uploadPromises.length > 0) {
                Promise.all(uploadPromises).then(() => {
                    loadBitacoraEntries(reqId);
                    resetBitacoraForm();
                }).catch(() => {
                    loadBitacoraEntries(reqId);
                    resetBitacoraForm();
                });
            } else {
                loadBitacoraEntries(reqId);
                resetBitacoraForm();
            }
        } else {
            toastr.error(res.error);
        }
    });
}

function deleteBitacoraEntry(entryId) {
    if (!confirm('¿Desea eliminar esta entrada de la bitácora?')) return;
    let reqId = $('#bitacora_req_id').val() || $('#req_id').val();
    $.post('api.php', { action: 'delete_bitacora', bitacora_id: entryId }, function(res) {
        if (res.success) { toastr.success(res.message); loadBitacoraEntries(reqId); }
        else toastr.error(res.error);
    });
}

function deleteBitacoraImage(imageId, reqId) {
    if (!confirm('¿Desea eliminar este archivo de la bitácora?')) return;
    $.post('api.php', { action: 'delete_bitacora_image', image_id: imageId }, function(res) {
        if (res.success) { toastr.success(res.message); loadBitacoraEntries(reqId); }
        else toastr.error(res.error);
    });
}

function viewBitacoraImage(src) {
    $('#imageViewerImg').attr('src', src);
    $('#imageViewerModal').modal('show');
}

function addPendingImage(base64Data) {
    pendingBitacoraImages.push(base64Data);
    let idx = pendingBitacoraImages.length - 1;
    $('#bitacora-pending-images').append(`
        <div class="bitacora-img-thumb" id="pending-img-${idx}">
            <img src="${base64Data}" alt="Imagen pendiente">
            <button type="button" class="btn-del-img" onclick="removePendingImage(${idx})" title="Quitar">&times;</button>
        </div>
    `);
}

function removePendingImage(idx) {
    pendingBitacoraImages[idx] = null;
    $(`#pending-img-${idx}`).remove();
}

function addPendingDoc(fileObj) {
    pendingBitacoraDocs.push(fileObj);
    let idx = pendingBitacoraDocs.length - 1;
    let ext = fileObj.name.split('.').pop().toLowerCase();
    let iconClass = 'fa-file-alt text-info';
    if (['xlsx','xls','csv'].includes(ext)) iconClass = 'fa-file-excel text-success';
    else if (['doc','docx'].includes(ext)) iconClass = 'fa-file-word text-primary';
    else if (['ppt','pptx'].includes(ext)) iconClass = 'fa-file-powerpoint text-warning';
    else if (['pdf'].includes(ext)) iconClass = 'fa-file-pdf text-danger';
    else if (['cfg','config','r','py','sh','txt','json','sql','xml','log','ini','yaml','yml'].includes(ext)) iconClass = 'fa-file-code text-dark';

    let sizeKb = (fileObj.size / 1024).toFixed(1) + ' KB';

    $('#bitacora-pending-docs').append(`
        <div class="badge badge-light border p-2 mr-2 mb-2 shadow-sm d-inline-flex align-items-center" id="pending-doc-${idx}">
            <i class="fas ${iconClass} mr-2"></i>
            <span class="mr-2">${escapeHtml(fileObj.name)} <small class="text-muted">(${sizeKb})</small></span>
            <button type="button" class="btn btn-xs btn-outline-danger border-0" onclick="removePendingDoc(${idx})" title="Quitar">&times;</button>
        </div>
    `);
}

function removePendingDoc(idx) {
    pendingBitacoraDocs[idx] = null;
    $(`#pending-doc-${idx}`).remove();
}

// Clipboard paste handler (Auto-activa el formulario al pegar imágenes)
$(document).on('paste', function(e) {
    if (!$('#bitacoraModal').is(':visible')) return;
    let clipData = (e.originalEvent || e).clipboardData;
    if (!clipData || !clipData.items) return;
    for (let i = 0; i < clipData.items.length; i++) {
        let item = clipData.items[i];
        if (item.type.indexOf('image') !== -1) {
            e.preventDefault();
            toggleBitacoraForm(true);
            let blob = item.getAsFile();
            let reader = new FileReader();
            reader.onload = function(ev) { addPendingImage(ev.target.result); };
            reader.readAsDataURL(blob);
            toastr.info('Imagen pegada del portapapeles en la Bitácora');
            break;
        }
    }
});

// Drag & drop handlers
$(document).ready(function() {
    let pasteZone = document.getElementById('bitacora-paste-zone');
    if (pasteZone) {
        pasteZone.addEventListener('dragover', function(e) { e.preventDefault(); this.classList.add('drag-over'); });
        pasteZone.addEventListener('dragleave', function() { this.classList.remove('drag-over'); });
        pasteZone.addEventListener('drop', function(e) {
            e.preventDefault(); this.classList.remove('drag-over');
            toggleBitacoraForm(true);
            let files = e.dataTransfer.files;
            for (let i = 0; i < files.length; i++) {
                if (files[i].type.startsWith('image/')) {
                    let reader = new FileReader();
                    reader.onload = function(ev) { addPendingImage(ev.target.result); };
                    reader.readAsDataURL(files[i]);
                }
            }
        });
    }

    $('#bitacora_image_file').on('change', function() {
        toggleBitacoraForm(true);
        let files = this.files;
        for (let i = 0; i < files.length; i++) {
            if (files[i].type.startsWith('image/')) {
                let reader = new FileReader();
                reader.onload = function(ev) { addPendingImage(ev.target.result); };
                reader.readAsDataURL(files[i]);
            }
        }
        this.value = '';
    });

    let docZone = document.getElementById('bitacora-doc-zone');
    if (docZone) {
        docZone.addEventListener('dragover', function(e) { e.preventDefault(); this.classList.add('drag-over'); });
        docZone.addEventListener('dragleave', function() { this.classList.remove('drag-over'); });
        docZone.addEventListener('drop', function(e) {
            e.preventDefault(); this.classList.remove('drag-over');
            toggleBitacoraForm(true);
            let files = e.dataTransfer.files;
            for (let i = 0; i < files.length; i++) {
                addPendingDoc(files[i]);
            }
        });
    }

    $('#bitacora_doc_file').on('change', function() {
        toggleBitacoraForm(true);
        let files = this.files;
        for (let i = 0; i < files.length; i++) {
            addPendingDoc(files[i]);
        }
        this.value = '';
    });
});
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>

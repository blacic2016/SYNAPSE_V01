<?php
/**
 * Módulo de Visualización y Edición de Archivos Visio (VSDX) - CMDB VILASECA
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/permissions_helper.php';

// Validar login
require_login();
if (!has_module_access('diagrams') && !has_module_access('vilaseca')) {
    header("Location: dashboard.php");
    exit();
}

$page_title = "Diagramas de Red (Visio VSDX)";
$hide_content_header = true;
include 'partials/header.php';
?>

<style>
/* Modern styling for Visio Viewer */
.visio-container {
    padding: 0.5rem;
}

.visio-card-item {
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    border-radius: 8px;
    border: 1px solid var(--border-color, #e9ecef);
    background-color: var(--card-bg, #ffffff);
}

.visio-card-item:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 16px rgba(0, 42, 84, 0.15);
    border-color: var(--sonda-orange, #FF5C05);
}

/* Nav tabs styling matching other modules */
.nav-tabs .nav-link {
    font-weight: 600;
    border: none;
    color: var(--text-color, #333);
    opacity: 0.8;
}

.nav-tabs .nav-link.active {
    color: var(--sonda-orange, #FF5C05) !important;
    border-bottom: 3px solid var(--sonda-orange, #FF5C05);
    background-color: transparent !important;
    opacity: 1;
}

/* Drop Zone styling */
.drop-zone {
    border: 2px dashed #002A54;
    border-radius: 8px;
    background-color: rgba(0, 42, 84, 0.02);
    transition: all 0.3s ease;
    cursor: pointer;
}

.drop-zone:hover, .drop-zone.dragover {
    background-color: rgba(0, 42, 84, 0.06);
    border-color: var(--sonda-orange, #FF5C05);
}

/* Fullscreen styling for Draw.io editor */
#editor-tab-content:fullscreen {
    width: 100vw !important;
    height: 100vh !important;
    border: none !important;
    border-radius: 0 !important;
    background-color: #f8f9fa !important;
    padding: 15px !important;
    overflow: hidden !important;
    display: flex !important;
    flex-direction: column !important;
}

#editor-tab-content:fullscreen #drawio-iframe-wrapper {
    flex: 1 !important;
    height: auto !important;
}

#editor-tab-content:fullscreen #drawio-iframe {
    height: calc(100vh - 120px) !important;
}
</style>

<div class="visio-container">
    <div class="card card-orange card-outline card-outline-tabs shadow-sm">
        <div class="card-header p-0 border-bottom-0 d-flex justify-content-between align-items-center flex-wrap" style="background: #ffffff; padding: 4px 10px !important;">
            <ul class="nav nav-tabs border-bottom-0" id="visio-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="list-tab" data-toggle="tab" href="#list-tab-content" role="tab" aria-controls="list-tab-content" aria-selected="true" style="padding: 8px 12px; font-size: 0.85rem;">
                        <i class="fas fa-sitemap mr-1 text-info"></i>Diagramas Guardados
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="editor-tab" data-toggle="tab" href="#editor-tab-content" role="tab" aria-controls="editor-tab-content" aria-selected="false" style="padding: 8px 12px; font-size: 0.85rem;">
                        <i class="fas fa-pencil-ruler mr-1 text-warning"></i>Modelador / Visor
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="import-tab" data-toggle="tab" href="#import-tab-content" role="tab" aria-controls="import-tab-content" aria-selected="false" style="padding: 8px 12px; font-size: 0.85rem;">
                        <i class="fas fa-file-import mr-1 text-success"></i>Importar Visio (.vsdx)
                    </a>
                </li>
            </ul>

            <!-- Compact Title and Description integrated in the header (Right side) -->
            <div id="integrated-title-bar" class="d-none d-flex flex-column align-items-end mr-3" style="gap: 2px; max-width: 450px; min-width: 250px;">
                <input type="hidden" id="diagram-id" value="0">
                <div class="d-flex align-items-center" style="gap: 4px; width: 100%;">
                    <i class="fas fa-project-diagram text-orange" style="font-size: 0.8rem;"></i>
                    <input type="text" id="diagram-title" class="form-control form-control-sm border-0 font-weight-bold p-0 text-right" style="font-size: 0.88rem; background: transparent; box-shadow: none; height: auto;" placeholder="Título del Diagrama *">
                </div>
                <input type="text" id="diagram-desc" class="form-control form-control-sm border-0 text-muted p-0 text-right" style="font-size: 0.75rem; background: transparent; box-shadow: none; height: auto;" placeholder="Descripción / Notas de este modelo">
            </div>
        </div>

        <div class="card-body">
            <div class="tab-content" id="visio-tabs-tabContent">
                
                <!-- TAB 1: SAVED DIAGRAMS CATALOG -->
                <div class="tab-pane fade show active" id="list-tab-content" role="tabpanel" aria-labelledby="list-tab">
                    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap" style="gap: 10px;">
                        <div class="input-group shadow-sm" style="max-width: 400px;">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-transparent border-right-0"><i class="fas fa-search"></i></span>
                            </div>
                            <input type="text" id="search-diagram" class="form-control border-left-0" placeholder="Buscar modelos por título, descripción o CI...">
                        </div>
                        <div class="d-flex" style="gap: 8px;">
                            <button class="btn btn-success font-weight-bold shadow-sm" onclick="createNewDiagram()">
                                <i class="fas fa-plus mr-2"></i>Crear Diagrama Vacío
                            </button>
                            <button class="btn btn-outline-primary font-weight-bold shadow-sm" onclick="$('#import-tab').tab('show')">
                                <i class="fas fa-file-import mr-2"></i>Subir / Importar Visio
                            </button>
                        </div>
                    </div>

                    <!-- Grid of diagram cards -->
                    <div class="row" id="diagrams-grid-container">
                        <!-- Dynamic content via AJAX -->
                        <div class="col-12 text-center py-5">
                            <i class="fas fa-spinner fa-spin fa-2x text-muted mb-2"></i>
                            <p class="text-muted">Cargando modelos Visio...</p>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: DRAW.IO VISUAL EDITOR & VIEWER -->
                <div class="tab-pane fade" id="editor-tab-content" role="tabpanel" aria-labelledby="editor-tab">
                    
                    <!-- History version warning banner -->
                    <div class="alert alert-warning d-none mb-3 shadow-sm" id="history-view-banner" style="font-size: 0.9rem;">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <div>
                                <i class="fas fa-exclamation-triangle mr-2 text-dark"></i>
                                <span>Estás visualizando una <strong>versión del historial</strong> (Guardada el <span id="history-date-span"></span>).</span>
                            </div>
                            <div>
                                <button class="btn btn-xs btn-primary font-weight-bold mr-1" onclick="restoreHistoricalVersion()"><i class="fas fa-check mr-1"></i>Restaurar versión como actual</button>
                                <button class="btn btn-xs btn-secondary font-weight-bold" onclick="exitHistoryView()"><i class="fas fa-times mr-1"></i>Salir de historial</button>
                            </div>
                        </div>
                    </div>

                    <!-- Toolbar -->
                    <div class="card shadow-sm mb-2 border" style="border-radius: 4px; margin-top: -2px;">
                        <div class="card-body p-2 d-flex justify-content-between align-items-center flex-wrap" style="gap: 8px; background-color: #f8f9fa;">
                            <div class="d-flex align-items-center flex-wrap" style="gap: 8px; width: 100%;">
                                <span id="save-status" class="badge badge-secondary px-2 py-2" style="font-size:0.75rem; height: 28px; display: inline-flex; align-items: center;">Sin Guardar</span>
                                
                                <div class="input-group input-group-sm" style="max-width: 320px;">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-white"><i class="fas fa-link text-info"></i></span>
                                    </div>
                                    <select id="diagram-ci-assoc" class="form-control" style="font-size: 0.8rem; height: 28px;">
                                        <option value="">-- Vincular con Componente (CI) --</option>
                                    </select>
                                </div>

                                <!-- History Revision Dropdown -->
                                <div class="dropdown d-none" id="history-dropdown-wrapper">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="historyDropdownBtn" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="height: 28px; font-size: 0.8rem; display: inline-flex; align-items: center;">
                                        <i class="fas fa-history mr-1"></i> Historial
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right shadow-lg" id="history-items-container" aria-labelledby="historyDropdownBtn" style="max-height: 250px; overflow-y: auto; width: 280px;">
                                        <!-- Dynamic history items via AJAX -->
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="btn-group ml-auto">
                                    <button class="btn btn-sm btn-primary font-weight-bold" onclick="triggerSave()" title="Guardar cambios en base de datos" style="height: 28px; font-size: 0.8rem; display: inline-flex; align-items: center;">
                                        <i class="fas fa-save mr-1"></i>Guardar
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger d-none" id="delete-diagram-btn" onclick="deleteCurrentDiagram()" title="Eliminar este diagrama" style="height: 28px; font-size: 0.8rem; display: inline-flex; align-items: center;">
                                        <i class="fas fa-trash-alt mr-1"></i>Eliminar
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary" onclick="toggleFullscreen()" title="Pantalla Completa" style="height: 28px; font-size: 0.8rem; display: inline-flex; align-items: center;">
                                        <i class="fas fa-expand mr-1"></i>Pantalla Completa
                                    </button>
                                    <button class="btn btn-sm btn-outline-info" onclick="downloadDrawioFile()" title="Descargar como archivo local" style="height: 28px; font-size: 0.8rem; display: inline-flex; align-items: center;">
                                        <i class="fas fa-download mr-1"></i>Descargar Archivo
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary font-weight-bold" onclick="exitEditor()" style="height: 28px; font-size: 0.8rem; display: inline-flex; align-items: center;">
                                        <i class="fas fa-times mr-1"></i>Salir
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Draw.io Iframe Workspace -->
                    <div id="drawio-iframe-wrapper" style="position: relative; width: 100%; border: 1px solid #dee2e6; border-radius: 4px; overflow: hidden; background: #ffffff;">
                        <iframe id="drawio-iframe" src="" style="width: 100%; height: 720px; border: none; display: block;"></iframe>
                    </div>
                </div>

                <!-- TAB 3: IMPORT / UPLOAD VSDX -->
                <div class="tab-pane fade" id="import-tab-content" role="tabpanel" aria-labelledby="import-tab">
                    <div class="row">
                        <div class="col-md-5">
                            <div class="card card-primary card-outline shadow-sm">
                                <div class="card-header">
                                    <h3 class="card-title font-weight-bold"><i class="fas fa-info-circle mr-1 text-primary"></i>Detalles del Nuevo Modelo</h3>
                                </div>
                                <div class="card-body">
                                    <div class="form-group mb-3">
                                        <label class="font-weight-bold">Título del Modelo *</label>
                                        <input type="text" id="new-diagram-title" class="form-control" placeholder="Ej: Red de Core Sonda Vilaseca">
                                    </div>
                                    <div class="form-group mb-3">
                                        <label class="font-weight-bold">Descripción / Notas</label>
                                        <textarea id="new-diagram-desc" class="form-control" rows="3" placeholder="Información técnica, alcance o notas sobre el diagrama..."></textarea>
                                    </div>
                                    <div class="form-group mb-3">
                                        <label class="font-weight-bold">Asociar a Componente (CI)</label>
                                        <select id="new-diagram-ci-assoc" class="form-control select2-ci">
                                            <option value="">-- Sin Vincular --</option>
                                        </select>
                                        <small class="text-muted">Permite ligar este diagrama de Visio directamente a un servidor, switch o rack en la CMDB.</small>
                                    </div>
                                    <div class="form-group mb-0">
                                        <label class="font-weight-bold">Archivo Seleccionado</label>
                                        <div class="p-2 border rounded bg-light d-flex align-items-center">
                                            <i class="fas fa-file text-info mr-2" style="font-size: 1.2rem;"></i>
                                            <span id="import-filename-label" class="text-muted">Ninguno seleccionado</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-7">
                            <div class="card shadow-sm h-100">
                                <div class="card-body d-flex flex-column justify-content-center p-0">
                                    <div id="drop-zone" class="drop-zone p-5 text-center d-flex flex-column align-items-center justify-content-center m-3" style="min-height: 320px;">
                                        <i class="fas fa-cloud-upload-alt fa-4x text-info mb-3"></i>
                                        <h4 class="font-weight-bold">Arrastra tu archivo Visio (.vsdx) o Draw.io (.drawio/.xml)</h4>
                                        <p class="text-muted">Soporta importación automática de diagramas nativos de MS Visio</p>
                                        <button class="btn btn-primary btn-sm font-weight-bold px-4 mt-2" onclick="$('#file-input').click()">
                                            Seleccionar Archivo Local
                                        </button>
                                        <input type="file" id="file-input" class="d-none" accept=".vsdx,.drawio,.xml">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include 'partials/footer.php'; ?>

<script>
// Application State
let activeDiagramId = 0;
let activeDiagramXML = '';
let activeOriginalFilename = '';
let isIframeLoaded = false;
let pendingXMLToLoad = '';
let cisList = [];

// Fallback blank diagram XML in Draw.io format
const blankDrawioXML = '<mxfile><diagram id="1" name="Página-1"><mxGraphModel><root><mxCell id="0"/><mxCell id="1" parent="0"/></root></mxGraphModel></diagram></mxfile>';

$(document).ready(function() {
    // Cargar listas
    loadCIsList();
    loadDiagramsGrid();

    // Auto-filter by URL client parameter if specified (e.g. ?cliente=VILASECA)
    const urlParams = new URLSearchParams(window.location.search);
    const clientUrlParam = urlParams.get('cliente');
    if (clientUrlParam) {
        $('#search-diagram').val(clientUrlParam);
        setTimeout(() => { filterDiagrams(clientUrlParam); }, 300);
    }

    // Configurar selectores de búsqueda
    $('#search-diagram').on('input', function() {
        filterDiagrams($(this).val());
    });

    // Configurar Drag & Drop
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('file-input');

    if (dropZone) {
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('dragover');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            if (e.dataTransfer.files.length > 0) {
                handleImportFile(e.dataTransfer.files[0]);
            }
        });

        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleImportFile(e.target.files[0]);
            }
        });
    }

    // Configurar título del editor para autoguardar/mantener cambios
    $('#diagram-title, #diagram-desc, #diagram-ci-assoc').on('change input', function() {
        $('#save-status').text('Modificado (Sin Guardar)').removeClass('badge-success badge-secondary').addClass('badge-warning');
    });
});

// Cargar listado de CIs para vincular
function loadCIsList() {
    $.getJSON('api_visio.php', { action: 'list_cis' }, function(res) {
        if (res.success) {
            cisList = res.cis;
            let options = '<option value="">-- Sin Vincular --</option>';
            cisList.forEach(ci => {
                let clientTag = ci.client_name ? ` (Cliente: ${ci.client_name})` : '';
                options += `<option value="${ci.id}">[${ci.category_name}] - ${ci.hostname}${clientTag}</option>`;
            });
            $('#diagram-ci-assoc, #new-diagram-ci-assoc').html(options);
        }
    });
}

// Cargar catálogo de diagramas guardados
function loadDiagramsGrid() {
    $('#diagrams-grid-container').html(`
        <div class="col-12 text-center py-5">
            <i class="fas fa-spinner fa-spin fa-2x text-muted mb-2"></i>
            <p class="text-muted">Cargando modelos Visio...</p>
        </div>
    `);

    $.getJSON('api_visio.php', { action: 'list' }, function(res) {
        if (res.success) {
            renderDiagramsGrid(res.diagrams);
        } else {
            $('#diagrams-grid-container').html(`
                <div class="col-12 alert alert-danger">
                    <i class="fas fa-exclamation-circle mr-2"></i> Error al cargar diagramas: ${res.error}
                </div>
            `);
        }
    });
}

// Renderizar las tarjetas de diagramas
function renderDiagramsGrid(diagrams) {
    if (diagrams.length === 0) {
        $('#diagrams-grid-container').html(`
            <div class="col-12 text-center py-5 bg-light rounded border border-dashed">
                <i class="fas fa-sitemap fa-3x text-muted mb-3"></i>
                <h5>No hay diagramas Visio guardados aún</h5>
                <p class="text-muted">Sube un archivo .vsdx o crea un diagrama vacío para empezar.</p>
                <div class="mt-3">
                    <button class="btn btn-sm btn-success mr-2" onclick="createNewDiagram()"><i class="fas fa-plus mr-1"></i>Crear Vacío</button>
                    <button class="btn btn-sm btn-primary" onclick="$('#import-tab').tab('show')"><i class="fas fa-file-import mr-1"></i>Importar .vsdx</button>
                </div>
            </div>
        `);
        return;
    }

    let html = '';
    diagrams.forEach(diag => {
        let ciBadge = '';
        if (diag.ci_hostname) {
            let clientBadge = diag.client_name ? `
                <div class="mt-1 pt-1 border-top text-muted d-flex align-items-center justify-content-between" style="font-size: 0.76rem;">
                    <span><i class="fas fa-building text-primary mr-1"></i><strong>Cliente:</strong></span>
                    <span class="badge badge-primary px-2 py-1 font-weight-bold" style="font-size: 0.72rem; letter-spacing: 0.3px;">${diag.client_name}</span>
                </div>
            ` : '';

            ciBadge = `
                <div class="mt-2 p-2 rounded border small" style="background-color: rgba(23, 162, 184, 0.08); border-color: #bfe5ec !important;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-link text-info mr-1"></i>
                            <strong>Componente CI:</strong><br>
                            <a href="ci_builder.php?id=${diag.ci_instance_id}" target="_blank" class="text-info font-weight-bold">
                                [${diag.category_name}] - ${diag.ci_hostname}
                            </a>
                        </div>
                        <button class="btn btn-xs btn-outline-info font-weight-bold ml-1" onclick="editDiagramMeta(${diag.id}, event)" title="Cambiar CI o Metadatos">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                    ${clientBadge}
                </div>
            `;
        } else {
            ciBadge = `
                <div class="mt-2 p-2 rounded border small d-flex justify-content-between align-items-center bg-light">
                    <span class="text-muted"><i class="fas fa-unlink mr-1"></i> Sin vincular a CI</span>
                    <button class="btn btn-xs btn-outline-primary font-weight-bold" onclick="editDiagramMeta(${diag.id}, event)">
                        <i class="fas fa-link mr-1"></i> Vincular CI
                    </button>
                </div>
            `;
        }

        let origFile = diag.filename_original ? `
            <div class="text-muted small mt-1 text-truncate" title="${diag.filename_original}">
                <i class="fas fa-file-alt mr-1"></i> <strong>Origen:</strong> ${diag.filename_original}
            </div>
        ` : '';

        // Formatear fecha
        let updatedDate = new Date(diag.updated_at).toLocaleString();

        html += `
            <div class="col-md-4 mb-4 diagram-card" data-title="${diag.title.toLowerCase()}" data-desc="${(diag.description || '').toLowerCase()}" data-ci="${(diag.ci_hostname || '').toLowerCase()}" data-client="${(diag.client_name || '').toLowerCase()}">
                <div class="card h-100 shadow-sm visio-card-item d-flex flex-column overflow-hidden" style="border-radius:10px;">
                    <div style="height:145px; background:#0f172a; border-bottom:1px solid #1e293b; display:flex; align-items:center; justify-content:center; overflow:hidden; position:relative;" class="p-2">
                        <img src="api_visio.php?action=get_preview&id=${diag.id}" style="max-height:100%; max-width:100%; object-fit:contain; border-radius:4px;" class="shadow-2xs">
                    </div>
                    <div class="card-body d-flex flex-column flex-grow-1 p-3">
                        <div class="d-flex align-items-start justify-content-between mb-1">
                            <h5 class="card-title font-weight-bold text-dark m-0" style="font-size: 1.05rem;">
                                <i class="fas fa-project-diagram text-orange mr-2"></i>${diag.title}
                            </h5>
                        </div>
                        <p class="card-text text-muted small mt-2 flex-grow-1" style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; min-height: 44px;">
                            ${diag.description || 'Sin descripción.'}
                        </p>
                        ${ciBadge}
                        <hr class="my-2">
                        <div class="text-muted small">
                            <i class="far fa-clock mr-1"></i> <strong>Modificado:</strong> ${updatedDate}
                        </div>
                        ${origFile}
                    </div>
                    <div class="card-footer bg-transparent border-top-0 d-flex justify-content-between p-3" style="gap: 6px;">
                        <button class="btn btn-sm btn-outline-secondary font-weight-bold" onclick="editDiagramMeta(${diag.id}, event)" title="Modificar datos / Asociar CI">
                            <i class="fas fa-edit mr-1"></i>Editar / CI
                        </button>
                        <button class="btn btn-sm btn-primary font-weight-bold shadow-sm px-2" onclick="loadDiagram(${diag.id})">
                            <i class="fas fa-folder-open mr-1"></i>Abrir Diagrama
                        </button>
                        <button class="btn btn-sm btn-outline-danger font-weight-bold px-2" onclick="deleteDiagram(${diag.id}, event)" title="Eliminar diagrama">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    });
    $('#diagrams-grid-container').html(html);
}

// Editar metadatos y asociación a CI de un diagrama existente
function editDiagramMeta(diagramId, event) {
    if (event) event.stopPropagation();

    $.getJSON('api_visio.php', { action: 'get', id: diagramId }, function(res) {
        if (!res.success || !res.diagram) {
            Swal.fire('Error', 'No se pudieron obtener los datos del diagrama.', 'error');
            return;
        }

        const diag = res.diagram;
        let ciOptions = '<option value="">-- Sin Vincular --</option>';
        cisList.forEach(ci => {
            const selected = (diag.ci_instance_id == ci.id) ? 'selected' : '';
            ciOptions += `<option value="${ci.id}" ${selected}>[${ci.category_name}] - ${ci.hostname} (${ci.ci_unique || 'S/UID'})</option>`;
        });

        Swal.fire({
            title: '<i class="fas fa-edit text-orange mr-2"></i>Modificar Diagrama & Componente CI',
            html: `
                <div class="text-left font-weight-normal" style="font-size: 0.9rem;">
                    <div class="form-group mb-3">
                        <label class="font-weight-bold">Título del Diagrama *</label>
                        <input type="text" id="swal-edit-title" class="form-control" value="${diag.title.replace(/"/g, '&quot;')}">
                    </div>
                    <div class="form-group mb-3">
                        <label class="font-weight-bold">Descripción / Notas</label>
                        <textarea id="swal-edit-desc" class="form-control" rows="3">${diag.description || ''}</textarea>
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-bold">Asociar a Componente (CI)</label>
                        <select id="swal-edit-ci" class="form-control">
                            ${ciOptions}
                        </select>
                        <small class="text-muted mt-1 d-block">Ligue este diagrama directamente a un Servidor, Switch, Rack o Componente en la CMDB.</small>
                    </div>
                </div>
            `,
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-save mr-1"></i> Guardar Cambios',
            cancelButtonText: 'Cancelar',
            customClass: {
                confirmButton: 'btn btn-primary font-weight-bold px-4',
                cancelButton: 'btn btn-secondary font-weight-bold px-3'
            },
            preConfirm: () => {
                let t = document.getElementById('swal-edit-title').value.trim();
                let d = document.getElementById('swal-edit-desc').value.trim();
                let c = document.getElementById('swal-edit-ci').value;
                if (!t) {
                    Swal.showValidationMessage('El título es obligatorio.');
                    return false;
                }
                return { title: t, desc: d, ci: c };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('api_visio.php', {
                    action: 'update_meta',
                    id: diagramId,
                    title: result.value.title,
                    description: result.value.desc,
                    ci_instance_id: result.value.ci
                }, function(updRes) {
                    if (updRes.success) {
                        toastr.success(updRes.message);
                        loadDiagramsGrid();
                    } else {
                        Swal.fire('Error', updRes.error || 'No se pudo actualizar.', 'error');
                    }
                }, 'json');
            }
        });
    });
}

// Filtrar tarjetas por búsqueda
function filterDiagrams(q) {
    let query = q.toLowerCase().trim();
    $('.diagram-card').each(function() {
        let title = $(this).data('title') || '';
        let desc = $(this).data('desc') || '';
        let ci = $(this).data('ci') || '';
        let client = $(this).data('client') || '';
        if (title.includes(query) || desc.includes(query) || ci.includes(query) || client.includes(query)) {
            $(this).show();
        } else {
            $(this).hide();
        }
    });
}

// Crear un nuevo diagrama vacío
function createNewDiagram() {
    Swal.fire({
        title: 'Crear Nuevo Diagrama Vacío',
        html: `
            <input type="text" id="swal-title" class="form-control mb-2" placeholder="Título del diagrama *">
            <input type="text" id="swal-desc" class="form-control mb-2" placeholder="Descripción (Opcional)">
            <select id="swal-ci" class="form-control">
                <option value="">-- Sin Vincular --</option>
                ${cisList.map(ci => `<option value="${ci.id}">[${ci.category_name}] - ${ci.hostname}</option>`).join('')}
            </select>
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Crear y Abrir',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            let t = document.getElementById('swal-title').value.trim();
            let d = document.getElementById('swal-desc').value.trim();
            let c = document.getElementById('swal-ci').value;
            if (!t) {
                Swal.showValidationMessage('El título es obligatorio.');
                return false;
            }
            return { title: t, desc: d, ci: c };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            activeDiagramId = 0;
            activeDiagramXML = blankDrawioXML;
            activeOriginalFilename = '';

            $('#diagram-id').val(0);
            $('#diagram-title').val(result.value.title);
            $('#diagram-desc').val(result.value.desc);
            $('#diagram-ci-assoc').val(result.value.ci);

            $('#integrated-title-bar').removeClass('d-none');
            $('#delete-diagram-btn').addClass('d-none');
            $('#history-dropdown-wrapper').addClass('d-none');
            $('#save-status').text('Nuevo Diagrama').removeClass('badge-success badge-warning').addClass('badge-secondary');

            $('#editor-tab').tab('show');
            loadIframeWorkspace();
        }
    });
}

// Cargar un diagrama guardado
function loadDiagram(id) {
    $.getJSON('api_visio.php', { action: 'get', id: id }, function(res) {
        if (res.success) {
            activeDiagramId = res.diagram.id;
            activeDiagramXML = res.diagram.xml_content;
            activeOriginalFilename = res.diagram.filename_original || '';

            $('#diagram-id').val(res.diagram.id);
            $('#diagram-title').val(res.diagram.title);
            $('#diagram-desc').val(res.diagram.description || '');
            $('#diagram-ci-assoc').val(res.diagram.ci_instance_id || '');

            $('#integrated-title-bar').removeClass('d-none');
            $('#delete-diagram-btn').removeClass('d-none');
            $('#history-dropdown-wrapper').removeClass('d-none');
            $('#save-status').text('Cargado').removeClass('badge-warning badge-secondary').addClass('badge-success');

            $('#editor-tab').tab('show');
            loadIframeWorkspace();
            loadHistoryDropdown(id);
        } else {
            Swal.fire('Error', 'No se pudo abrir el diagrama: ' + res.error, 'error');
        }
    });
}

// Cargar el iframe del editor con el contenido
function loadIframeWorkspace() {
    isIframeLoaded = false;
    pendingXMLToLoad = activeDiagramXML;
    let iframe = document.getElementById('drawio-iframe');
    // Forzar inicialización limpia
    iframe.src = 'https://embed.diagrams.net/?embed=1&spin=1&proto=json';
}

// Recibir mensajes del iframe de diagrams.net
window.addEventListener('message', function(evt) {
    if (evt.origin !== 'https://embed.diagrams.net') return;

    try {
        let msg = JSON.parse(evt.data);

        if (msg.event === 'init') {
            isIframeLoaded = true;
            if (pendingXMLToLoad) {
                let loadAction = {
                    action: 'load',
                    xml: pendingXMLToLoad,
                    autosave: 1
                };
                document.getElementById('drawio-iframe').contentWindow.postMessage(JSON.stringify(loadAction), 'https://embed.diagrams.net');
                pendingXMLToLoad = '';
            }
        } else if (msg.event === 'load') {
            // El diagrama ha terminado de cargarse y renderizarse en el lienzo
            setTimeout(function() {
                if (isIframeLoaded) {
                    let exportAction = {
                        action: 'export',
                        format: 'xmlsvg',
                        spinKey: 'saving'
                    };
                    let iframe = document.getElementById('drawio-iframe');
                    if (iframe && iframe.contentWindow) {
                        iframe.contentWindow.postMessage(JSON.stringify(exportAction), 'https://embed.diagrams.net');
                    }
                }
            }, 800);
        } else if (msg.event === 'save') {
            // El usuario hizo clic en "Save" dentro del editor
            saveDiagramToServer(msg.xml, msg.data || null);
        } else if (msg.event === 'export') {
            // Recibido como respuesta a nuestra petición de exportación (con captura gráfica en msg.data)
            saveDiagramToServer(msg.xml || activeDiagramXML, msg.data || null);
        } else if (msg.event === 'exit') {
            exitEditor();
        }
    } catch (e) {
        // Ignorar mensajes no válidos de draw.io (como pings)
    }
});

// Guardar cambios programáticamente pidiendo la exportación SVG al iframe
function triggerSave() {
    let iframe = document.getElementById('drawio-iframe');
    if (iframe && iframe.contentWindow && isIframeLoaded) {
        let exportAction = {
            action: 'export',
            format: 'xmlsvg',
            spinKey: 'saving'
        };
        iframe.contentWindow.postMessage(JSON.stringify(exportAction), 'https://embed.diagrams.net');
    } else {
        if (activeDiagramXML) {
            saveDiagramToServer(activeDiagramXML);
        } else {
            Swal.fire('Atención', 'El visor de diagramas no está totalmente cargado.', 'warning');
        }
    }
}

// Realizar llamada AJAX para guardar el diagrama en base de datos
function saveDiagramToServer(xml, imageData = null) {
    let id = $('#diagram-id').val();
    let title = $('#diagram-title').val().trim();
    let description = $('#diagram-desc').val().trim();
    let ci_instance_id = $('#diagram-ci-assoc').val();

    let xmlToSave = xml || activeDiagramXML;

    if (!title) {
        Swal.fire('Atención', 'El título es obligatorio.', 'warning');
        return;
    }
    if (!xmlToSave) {
        Swal.fire('Atención', 'El contenido del diagrama no puede estar vacío.', 'warning');
        return;
    }

    $('#save-status').text('Guardando...').removeClass('badge-success badge-secondary').addClass('badge-warning');

    $.post('api_visio.php', {
        action: 'save',
        id: id,
        title: title,
        description: description,
        xml_content: xmlToSave,
        filename_original: activeOriginalFilename,
        ci_instance_id: ci_instance_id,
        image_data: imageData
    }, function(res) {
        if (res.success) {
            toastr.success(res.message);
            $('#save-status').text('Guardado').removeClass('badge-warning badge-secondary').addClass('badge-success');
            $('#diagram-id').val(res.id);
            activeDiagramId = res.id;
            activeDiagramXML = xmlToSave;

            $('#delete-diagram-btn').removeClass('d-none');
            $('#history-dropdown-wrapper').removeClass('d-none');

            loadDiagramsGrid();
            loadHistoryDropdown(res.id);
        } else {
            $('#save-status').text('Error').removeClass('badge-warning').addClass('badge-danger');
            Swal.fire('Error', 'No se pudo guardar el diagrama: ' + res.error, 'error');
        }
    }, 'json');
}

// Descargar archivo .drawio o .vsdx local
function downloadDrawioFile() {
    if (!activeDiagramXML) {
        Swal.fire('Atención', 'No hay información en el lienzo para descargar.', 'warning');
        return;
    }

    let title = $('#diagram-title').val().trim() || 'diagrama_visio';
    
    // Si contiene un archivo Visio codificado directo que no se guardó, descargamos como .vsdx
    if (activeDiagramXML.startsWith('data:application/vnd.visio;base64,')) {
        let base64Data = activeDiagramXML.split(',')[1];
        let binaryString = atob(base64Data);
        let len = binaryString.length;
        let bytes = new Uint8Array(len);
        for (let i = 0; i < len; i++) {
            bytes[i] = binaryString.charCodeAt(i);
        }
        let blob = new Blob([bytes], {type: "application/vnd.visio"});
        let link = document.createElement('a');
        link.href = window.URL.createObjectURL(blob);
        link.download = (activeOriginalFilename || title + '.vsdx');
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        return;
    }

    // De lo contrario descargamos como Drawio XML estándar
    let blob = new Blob([activeDiagramXML], {type: "application/xml"});
    let link = document.createElement('a');
    link.href = window.URL.createObjectURL(blob);
    link.download = title + '.drawio';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Eliminar un diagrama desde el catálogo (Tab 1)
function deleteDiagram(id, event) {
    if (event) event.stopPropagation();

    Swal.fire({
        title: '¿Confirmas que deseas eliminar este diagrama?',
        text: "Esta acción no se puede deshacer y borrará todo su historial.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('api_visio.php', { action: 'delete', id: id }, function(res) {
                if (res.success) {
                    toastr.success(res.message);
                    loadDiagramsGrid();
                } else {
                    Swal.fire('Error', res.error, 'error');
                }
            }, 'json');
        }
    });
}

// Eliminar diagrama activo desde el editor (Tab 2)
function deleteCurrentDiagram() {
    if (activeDiagramId <= 0) return;
    deleteDiagram(activeDiagramId);
    exitEditor();
}

// Salir del editor
function exitEditor() {
    $('#integrated-title-bar').addClass('d-none');
    $('#list-tab').tab('show');
    // Limpiar iframe para liberar RAM
    document.getElementById('drawio-iframe').src = '';
    isIframeLoaded = false;
}

// Pantalla Completa
function toggleFullscreen() {
    let elem = document.getElementById('editor-tab-content');
    if (!document.fullscreenElement) {
        elem.requestFullscreen().catch(err => {
            console.error(`Error al activar pantalla completa: ${err.message}`);
        });
    } else {
        document.exitFullscreen();
    }
}

// Procesar el archivo que se sube (Tab 3)
function handleImportFile(file) {
    if (!file) return;

    let isVsdx = file.name.endsWith('.vsdx');
    let isDrawio = file.name.endsWith('.drawio') || file.name.endsWith('.xml');

    if (!isVsdx && !isDrawio) {
        Swal.fire('Archivo inválido', 'Por favor selecciona un archivo Visio (.vsdx) o Draw.io (.drawio/.xml)', 'error');
        return;
    }

    activeOriginalFilename = file.name;
    $('#import-filename-label').text(file.name);

    let titleInput = $('#new-diagram-title');
    if (!titleInput.val().trim()) {
        let cleanName = file.name.substring(0, file.name.lastIndexOf('.')) || file.name;
        titleInput.val(cleanName);
    }

    let reader = new FileReader();

    if (isVsdx) {
        reader.onload = function(e) {
            // Guardamos el base64 crudo en el formato que diagrams.net puede importar
            let base64 = e.target.result.split(',')[1];
            activeDiagramXML = 'data:application/vnd.visio;base64,' + base64;
            
            Swal.fire({
                title: 'Visio Importado Correctamente',
                text: 'El archivo .vsdx ha sido cargado. Haz clic en "Abrir en Editor" para visualizar e interactuar con el modelo.',
                icon: 'success',
                confirmButtonText: 'Abrir en Editor'
            }).then(() => {
                // Sincronizar datos al editor
                $('#diagram-id').val(0);
                $('#diagram-title').val($('#new-diagram-title').val());
                $('#diagram-desc').val($('#new-diagram-desc').val());
                $('#diagram-ci-assoc').val($('#new-diagram-ci-assoc').val());

                $('#integrated-title-bar').removeClass('d-none');
                $('#save-status').text('Visio Importado (Sin Guardar)').removeClass('badge-success').addClass('badge-warning');

                $('#editor-tab').tab('show');
                loadIframeWorkspace();
            });
        };
        reader.readAsDataURL(file);
    } else {
        reader.onload = function(e) {
            activeDiagramXML = e.target.result;
            
            Swal.fire({
                title: 'Diagrama cargado correctamente',
                text: 'El archivo .drawio/.xml ha sido importado.',
                icon: 'success',
                confirmButtonText: 'Abrir en Editor'
            }).then(() => {
                // Sincronizar datos al editor
                $('#diagram-id').val(0);
                $('#diagram-title').val($('#new-diagram-title').val());
                $('#diagram-desc').val($('#new-diagram-desc').val());
                $('#diagram-ci-assoc').val($('#new-diagram-ci-assoc').val());

                $('#integrated-title-bar').removeClass('d-none');
                $('#save-status').text('Importado (Sin Guardar)').removeClass('badge-success').addClass('badge-warning');

                $('#editor-tab').tab('show');
                loadIframeWorkspace();
            });
        };
        reader.readAsText(file);
    }
}

// Cargar dropdown del historial de versiones
function loadHistoryDropdown(diagramId) {
    if (diagramId <= 0) {
        $('#history-dropdown-wrapper').addClass('d-none');
        return;
    }

    $.getJSON('api_visio.php', { action: 'history', diagram_id: diagramId }, function(res) {
        if (res.success && res.history.length > 0) {
            let html = '<span class="dropdown-header font-weight-bold px-3 py-1">Versiones Guardadas</span>';
            res.history.forEach((h, index) => {
                let dateStr = new Date(h.created_at).toLocaleString();
                html += `
                    <a class="dropdown-item d-flex align-items-center py-2" href="javascript:void(0)" onclick="loadHistoryVersion(${h.id}, '${dateStr}')" style="font-size:0.78rem;">
                        <i class="fas fa-history mr-2 text-muted"></i>
                        <div>
                            <strong>v${res.history.length - index}</strong> - ${dateStr}<br>
                            <span class="text-muted text-truncate d-inline-block" style="max-width:200px;">${h.description || 'Sin notas'}</span>
                        </div>
                    </a>
                `;
            });
            $('#history-items-container').html(html);
            $('#history-dropdown-wrapper').removeClass('d-none');
        } else {
            $('#history-dropdown-wrapper').addClass('d-none');
        }
    });
}

// Cargar una versión del historial
function loadHistoryVersion(historyId, dateStr) {
    $.getJSON('api_visio.php', { action: 'get_history', id: historyId }, function(res) {
        if (res.success) {
            // Mostrar banner de visualización del historial
            $('#history-date-span').text(dateStr);
            $('#history-view-banner').removeClass('d-none');

            // Cargar el XML temporalmente en el iframe
            pendingXMLToLoad = res.version.xml_content;
            loadIframeWorkspace();
            
            toastr.info("Visualizando versión del " + dateStr);
        } else {
            Swal.fire('Error', res.error, 'error');
        }
    });
}

// Restaurar versión histórica como actual
function restoreHistoricalVersion() {
    if (!pendingXMLToLoad && isIframeLoaded) {
        // Si ya está cargado en el iframe, solicitamos exportarlo
        let iframe = document.getElementById('drawio-iframe');
        iframe.contentWindow.postMessage(JSON.stringify({ action: 'export', format: 'xml' }), '*');
        
        $('#history-view-banner').addClass('d-none');
        toastr.success("La versión del historial ha sido restaurada. Recuerda hacer clic en Guardar para persistir los cambios.");
    }
}

// Cancelar vista de historial
function exitHistoryView() {
    $('#history-view-banner').addClass('d-none');
    loadDiagram(activeDiagramId);
}
</script>

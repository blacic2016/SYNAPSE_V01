<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../src/auth.php';
require_once __DIR__ . '/../../../src/db.php';
require_once __DIR__ . '/../../../src/permissions_helper.php';

// Control de acceso
require_login();
if (!has_role('SUPER_ADMIN') && !has_module_access('gitlab')) {
    header("Location: " . PUBLIC_URL_PREFIX . "/dashboard.php");
    exit();
}

$page_title = 'GitLab - Control de Versiones & Push a GitHub';
$page_icon = 'fab fa-gitlab';
$hide_content_header = true;

// Load GitLab Configuration
$gitlab_config_file = __DIR__ . '/../../assets/gitlab_config.json';
$gitlab_config = [
    'remote_url' => 'https://github.com/blacic2016/femsa-sonda.git',
    'pat_token' => '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',
    'user_name' => 'blacic2016',
    'user_email' => 'blacic2016@gmail.com'
];
if (file_exists($gitlab_config_file)) {
    $loaded_config = json_decode(file_get_contents($gitlab_config_file), true) ?: [];
    $gitlab_config = array_merge($gitlab_config, $loaded_config);
}

require_once __DIR__ . '/../../partials/header.php';
?>

<!-- CodeMirror CSS & Themes para Resaltado de Sintaxis Estilo Notepad++ -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/codemirror.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/theme/eclipse.min.css">

<style>
.gitlab-main-nav {
    background: #ffffff;
    padding: 8px 12px;
    border-radius: 8px;
    border: 1px solid #dee2e6;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.gitlab-main-nav .nav-link {
    font-size: 0.85rem;
    padding: 8px 14px;
    color: #495057;
    font-weight: 600;
    border-radius: 6px;
    transition: all 0.2s ease;
    margin-right: 3px;
}

.gitlab-main-nav .nav-link:hover {
    background-color: #f8f9fa;
    color: #007bff;
}

.gitlab-main-nav .nav-link.active {
    background-color: #007bff;
    color: #ffffff !important;
    box-shadow: 0 3px 8px rgba(0,123,255,0.25);
}

.gitlab-container {
    display: flex;
    height: calc(100vh - 175px);
    background: #ffffff;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    overflow: hidden;
    margin-top: 10px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
}

.gitlab-sidebar {
    width: 320px;
    background: #ffffff;
    border-right: 1px solid #dee2e6;
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
}

.gitlab-workspace {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: #ffffff;
    min-width: 0;
}

.gitlab-history-sidebar {
    width: 300px;
    background: #ffffff;
    border-left: 1px solid #dee2e6;
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
}

.sidebar-header, .history-header, .workspace-header {
    padding: 12px 15px;
    border-bottom: 1px solid #dee2e6;
    background: #fdfdfd;
    flex-shrink: 0;
}

.file-list, .version-list {
    flex: 1;
    overflow-y: auto;
}

.file-item {
    padding: 12px 15px;
    border-bottom: 1px solid #f1f3f5;
    cursor: pointer;
    transition: all 0.2s ease;
    border-left: 4px solid transparent;
}

.file-item:hover {
    background: #f8f9fa;
}

.file-item.active {
    background: #e8f4fd;
    border-left-color: #007bff;
}

.version-item {
    padding: 10px 12px;
    border-bottom: 1px solid #f1f3f5;
    cursor: pointer;
    transition: all 0.15s ease;
    border-left: 3px solid transparent;
}

.version-item:hover {
    background: #f8f9fa;
}

.version-item.active {
    background: #eef9f2;
    border-left-color: #28a745;
}

/* CodeMirror Notepad++ Style */
.CodeMirror {
    height: 100% !important;
    font-family: 'Fira Code', 'Courier New', Consolas, monospace !important;
    font-size: 0.92rem !important;
    line-height: 1.5 !important;
    background-color: #ffffff !important;
    color: #111111 !important;
}

.CodeMirror-gutters {
    background-color: #f4f6f9 !important;
    border-right: 2px solid #e9ecef !important;
}

.CodeMirror-linenumber {
    color: #888888 !important;
    font-weight: 600 !important;
    padding: 0 5px !important;
}

/* Sintaxis Notepad++ */
.cm-keyword { color: #0000ff !important; font-weight: bold; }
.cm-string  { color: #800000 !important; }
.cm-comment { color: #008000 !important; font-style: italic; }
.cm-number  { color: #ff0000 !important; }
.cm-def, .cm-variable-2 { color: #000080 !important; font-weight: bold; }
.cm-operator { color: #000000 !important; font-weight: bold; }

.doc-split {
    display: flex;
    height: 100%;
    width: 100%;
}

.doc-editor-pane {
    width: 50%;
    height: 100%;
    border-right: 1px solid #dee2e6;
    display: flex;
    flex-direction: column;
}

.doc-preview-pane {
    width: 50%;
    height: 100%;
    padding: 20px;
    overflow-y: auto;
    background: #fafafa;
}

.doc-textarea {
    flex: 1;
    border: none;
    resize: none;
    outline: none;
    padding: 15px;
    font-family: inherit;
    font-size: 0.9rem;
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #adb5bd;
    text-align: center;
    padding: 30px;
}

.terminal-box {
    background-color: #0d1117;
    color: #58a6ff;
    font-family: 'Fira Code', 'Courier New', monospace;
    padding: 15px;
    border-radius: 8px;
    max-height: 380px;
    overflow-y: auto;
    font-size: 0.85rem;
    white-space: pre-wrap;
    border: 1px solid #30363d;
}

.cmd-badge {
    background: #21262d;
    color: #79c0ff;
    padding: 4px 8px;
    border-radius: 4px;
    font-family: monospace;
    font-size: 0.85rem;
    display: block;
    margin-bottom: 5px;
}

.diff-line-added { background-color: #e6ffec !important; }
.diff-line-removed { background-color: #ffebe9 !important; }
.diff-line-number {
    width: 45px;
    text-align: right;
    color: #6e7781;
    background-color: #f6f8fa;
    user-select: none;
    font-weight: 600;
}
</style>

<div class="container-fluid pt-2">

    <!-- NAVEGACIÓN SUPERIOR DE 6 PESTAÑAS ESTRUCTURADAS -->
    <ul class="nav nav-pills gitlab-main-nav mb-2">
        <li class="nav-item">
            <a class="nav-link active" id="tab-01-files" href="#" onclick="switchTab('01-files')">
                <i class="fas fa-folder-open text-primary mr-1"></i> 01. Listado de Archivos Creados
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="tab-02-editor" href="#" onclick="switchTab('02-editor')">
                <i class="fas fa-edit text-success mr-1"></i> 02. Editor de Código
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="tab-03-cmd" href="#" onclick="switchTab('03-cmd')">
                <i class="fas fa-terminal text-danger mr-1"></i> 03. Comandos Git & Push
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="tab-04-doc" href="#" onclick="switchTab('04-doc')">
                <i class="fab fa-markdown text-info mr-1"></i> 04. Documentación (.md)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="tab-05-github" href="#" onclick="switchTab('05-github')">
                <i class="fab fa-github text-dark mr-1"></i> 05. Histórico GitHub
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="tab-06-diff" href="#" onclick="switchTab('06-diff')">
                <i class="fas fa-exchange-alt text-warning mr-1"></i> 06. Comparador (Git Diff)
            </a>
        </li>
    </ul>

    <!-- 01. PESTAÑA: LISTADO DE ARCHIVOS CREADOS -->
    <div id="pane-01-files" class="main-pane-view">
        <div class="card shadow-sm border-0" style="min-height: calc(100vh - 175px); background: #ffffff;">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-2">
                <span class="font-weight-bold"><i class="fas fa-list text-warning mr-2"></i> Listado de Desarrollos & Archivos Creados en Servidor</span>
                <button class="btn btn-sm btn-success font-weight-bold" onclick="createNewFileModal()"><i class="fas fa-plus mr-1"></i> Crear Nuevo Archivo</button>
            </div>
            <div class="card-body p-3 bg-light">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="input-group">
                            <input type="text" id="files-tab-search" class="form-control" placeholder="Buscar archivo por nombre o extensión...">
                            <div class="input-group-append">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 text-right">
                        <button class="btn btn-outline-secondary font-weight-bold" onclick="loadFilesList()"><i class="fas fa-sync-alt mr-1"></i> Recargar Archivos</button>
                    </div>
                </div>
                <div class="table-responsive bg-white border rounded shadow-sm">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>Nombre del Archivo</th>
                                <th>Tipo / Extensión</th>
                                <th>Versiones</th>
                                <th>Última Actualización</th>
                                <th class="text-center">Acciones del Archivo</th>
                            </tr>
                        </thead>
                        <tbody id="files-table-tbody">
                            <tr><td colspan="6" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin mr-2"></i> Cargando archivos...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 02. PESTAÑA: EDITOR DE CÓDIGO -->
    <div id="pane-02-editor" class="main-pane-view" style="display: none;">
        <div class="gitlab-container" style="height: calc(100vh - 175px);">
            <div class="gitlab-workspace" style="display: flex; flex-direction: column; height: 100%; overflow: hidden;">
                
                <!-- ÚNICA BARRA DE ACCIÓN Y ARCHIVO ACTIVO (EN EDITOR) -->
                <div class="workspace-header py-2 px-3 bg-white border-bottom" id="global-active-file-bar" style="flex-shrink: 0;">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <div class="d-flex align-items-center mb-1 mb-md-0">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mr-3 shadow-sm" style="width: 38px; height: 38px; flex-shrink: 0;">
                                <i class="fas fa-code-branch" id="global-active-file-icon" style="font-size: 1.1rem;"></i>
                            </div>
                            <div>
                                <div class="d-flex align-items-center flex-wrap">
                                    <span class="font-weight-bold text-dark mr-2" id="global-active-filename" style="font-size: 1.05rem;">Ningún archivo seleccionado</span>
                                    <span class="badge badge-primary font-weight-bold" id="global-active-version-badge" style="display:none; font-size: 0.8rem;">v1</span>
                                </div>
                                <small class="text-muted" id="global-active-meta">Selecciona un archivo para modificar su código fuente con colores.</small>
                            </div>
                        </div>
                        <div class="d-flex align-items-center flex-wrap" id="global-action-buttons">
                            <button class="btn btn-sm btn-outline-success font-weight-bold mr-2 my-1 shadow-sm" onclick="createNewFileModal()" id="btn-global-new" title="Crear Nuevo Archivo de Código">
                                <i class="fas fa-plus mr-1"></i> Nuevo Archivo
                            </button>
                            <button class="btn btn-sm btn-primary font-weight-bold mr-2 my-1 shadow-sm" onclick="saveFileContent()" id="btn-global-save" title="Guardar Cambios y Crear Nueva Versión">
                                <i class="fas fa-save mr-1"></i> Guardar Cambios
                            </button>
                            <button class="btn btn-sm btn-success font-weight-bold mr-2 my-1 shadow-sm" onclick="triggerGitPushDirect()" id="btn-global-push" title="Sincronizar y Ejecutar Git Push a GitHub">
                                <i class="fab fa-github mr-1"></i> Git Push
                            </button>
                            <button class="btn btn-sm btn-danger font-weight-bold my-1 shadow-sm" onclick="deleteActiveFile()" id="btn-global-delete" title="Eliminar el Archivo Seleccionado">
                                <i class="fas fa-trash-alt mr-1"></i> Eliminar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Editor de Código con Colores Notepad++ (CodeMirror) -->
                <div class="flex-grow-1 d-flex flex-column" style="position: relative; min-height: 0; overflow: hidden; flex: 1;">
                    <div id="editor-empty-prompt" class="empty-state">
                        <i class="fas fa-code fa-4x mb-3 text-light"></i>
                        <h5>Editor de Código Fuente con Colores</h5>
                        <p class="text-muted max-width-350">Ve al <strong>Tab 01. Listado de Archivos Creados</strong> y haz clic en cualquier archivo para cargarlo aquí con resaltado sintáctico de colores estilo Notepad++.</p>
                    </div>

                    <div id="editor-wrapper-box" class="flex-grow-1" style="height: 100%; min-height: 0; display: none;">
                        <textarea id="main-code-textarea"></textarea>
                    </div>
                </div>

            </div>

            <!-- Columna Derecha: Historial de Versiones del Archivo Seleccionado -->
            <div class="gitlab-history-sidebar">
                <div class="history-header bg-light">
                    <h6 class="font-weight-bold text-dark mb-0"><i class="fas fa-history text-success mr-1"></i> Versiones Guardadas</h6>
                </div>
                <div class="version-list" id="version-list-container">
                    <div class="empty-state" style="padding: 20px;">
                        <i class="fas fa-info-circle fa-2x mb-2 text-muted"></i>
                        <p class="text-xs text-muted mb-0">Selecciona un archivo para consultar sus versiones guardadas.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 03. PESTAÑA: COMANDOS GIT & PUSH (EXCLUSIVO) -->
    <div id="pane-03-cmd" class="main-pane-view" style="display: none;">
        <div class="card shadow-sm border-0" style="min-height: calc(100vh - 175px); background: #ffffff;">
            <div class="card-header bg-dark text-white font-weight-bold d-flex justify-content-between align-items-center py-2">
                <span><i class="fab fa-github text-danger mr-2"></i>Comandos Git Push Remoto & Consola de Publicación</span>
                <span class="badge badge-success"><?php echo htmlspecialchars($gitlab_config['remote_url']); ?></span>
            </div>
            <div class="card-body bg-light p-3">
                <div class="row">
                    <!-- Formulario de Comandos Git Push -->
                    <div class="col-md-5 mb-3">
                        <div class="card border shadow-sm">
                            <div class="card-header bg-secondary text-white font-weight-bold py-2">
                                <i class="fas fa-upload mr-1"></i> Parámetros de Publicación Git
                            </div>
                            <div class="card-body p-3">
                                <div class="form-group mb-2">
                                    <label class="font-weight-bold text-xs">Repositorio Remoto (URL):</label>
                                    <input type="text" class="form-control form-control-sm" id="git_remote_url" value="<?php echo htmlspecialchars($gitlab_config['remote_url']); ?>" readonly>
                                </div>
                                <div class="form-group mb-2">
                                    <label class="font-weight-bold text-xs">Mensaje de Commit:</label>
                                    <input type="text" class="form-control form-control-sm" id="git_commit_message" placeholder="Ej: Actualización de código o corrección de bugs...">
                                </div>
                                <div class="form-group mb-3">
                                    <label class="font-weight-bold text-xs">Comandos a Ejecutar:</label>
                                    <div class="cmd-badge">git add <span class="git-target-filename text-warning">archivo</span></div>
                                    <div class="cmd-badge">git commit -m "<span class="git-target-msg text-info">Mensaje de commit</span>"</div>
                                    <div class="cmd-badge">git push origin main</div>
                                </div>
                                <button type="button" class="btn btn-success btn-block font-weight-bold shadow-sm" onclick="runGitPushFromPane()">
                                    <i class="fab fa-github mr-1"></i> 🚀 Ejecutar Git Push en Servidor
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Terminal Output Grande -->
                    <div class="col-md-7 mb-3">
                        <div class="card border shadow-sm" style="height: 100%;">
                            <div class="card-header bg-dark text-white font-weight-bold py-2">
                                <i class="fas fa-terminal mr-1"></i> Consola Terminal de Resultados en Tiempo Real
                            </div>
                            <div class="card-body p-3 bg-dark">
                                <pre class="terminal-box mb-0" id="pane-git-terminal-text" style="height: 350px;">Esperando ejecución de Git Push...</pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 04. PESTAÑA: DOCUMENTACIÓN (.MD) -->
    <div id="pane-04-doc" class="main-pane-view" style="display: none;">
        <div class="card shadow-sm border-0" style="min-height: calc(100vh - 175px); background: #ffffff;">
            <div class="card-header bg-dark text-white font-weight-bold d-flex justify-content-between align-items-center py-2">
                <span><i class="fab fa-markdown text-info mr-2"></i> Documentación Técnica (.md) del Archivo Seleccionado</span>
                <button class="btn btn-sm btn-success font-weight-bold" onclick="saveMarkdownDescription()"><i class="fas fa-save mr-1"></i> Guardar Documentación</button>
            </div>
            <div class="card-body p-0">
                <div class="doc-split" style="height: calc(100vh - 235px);">
                    <div class="doc-editor-pane">
                        <div class="bg-light p-2 border-bottom font-weight-bold text-xs text-muted">
                            <i class="fas fa-edit mr-1"></i> Editor Markdown:
                        </div>
                        <textarea id="doc-editor-textarea" class="doc-textarea" placeholder="Escribe o modifica la documentación del archivo..."></textarea>
                    </div>
                    <div class="doc-preview-pane markdown-body" id="doc-preview-container">
                        <div class="text-muted py-4 text-center">Selecciona un archivo del Tab 01 para redactar y previsualizar su documentación.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 05. PESTAÑA: HISTÓRICO GITHUB -->
    <div id="pane-05-github" class="main-pane-view" style="display: none;">
        <div class="card shadow-sm border-0" style="min-height: calc(100vh - 175px); background: #ffffff;">
            <div class="card-header bg-dark text-white font-weight-bold d-flex justify-content-between align-items-center py-3">
                <div class="d-flex align-items-center">
                    <i class="fab fa-github fa-2x text-warning mr-3"></i>
                    <div>
                        <h5 class="mb-0 font-weight-bold text-white">Histórico Global de Commits & Pushes a GitHub</h5>
                        <small class="text-muted">Registro auditado almacenado en servidor y repositorio remoto</small>
                    </div>
                </div>
                <div>
                    <button class="btn btn-sm btn-outline-light font-weight-bold" onclick="loadGitHistory()"><i class="fas fa-sync-alt mr-1"></i> Actualizar Histórico</button>
                </div>
            </div>
            <div class="card-body p-4 bg-light">
                <div class="row">
                    <div class="col-md-12 mb-4">
                        <h6 class="font-weight-bold text-dark"><i class="fas fa-network-wired text-info mr-2"></i>1. Árbol Gráfico de Commits (Git Log del Servidor):</h6>
                        <pre class="terminal-box" id="github-git-log-raw" style="max-height: 250px;">Cargando árbol de commits...</pre>
                    </div>
                    <div class="col-md-12">
                        <h6 class="font-weight-bold text-dark"><i class="fas fa-list-alt text-success mr-2"></i>2. Registro de Envíos Realizados a GitHub (Database Audit Logs):</h6>
                        <div class="table-responsive bg-white border rounded shadow-sm">
                            <table class="table table-hover table-striped mb-0" style="font-size: 0.85rem;">
                                <thead class="thead-light">
                                    <tr>
                                        <th># Commit Hash</th>
                                        <th>Archivo Sincronizado</th>
                                        <th>Mensaje de Commit</th>
                                        <th>Fecha y Hora</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody id="github-history-tbody">
                                    <tr><td colspan="5" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin mr-2"></i>Cargando historial...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 06. PESTAÑA: COMPARADOR (GIT DIFF) EXCLUSIVO -->
    <div id="pane-06-diff" class="main-pane-view" style="display: none;">
        <div class="card shadow-sm border-0" style="min-height: calc(100vh - 175px); background: #ffffff;">
            <div class="card-header bg-dark text-white font-weight-bold d-flex justify-content-between align-items-center py-2">
                <span><i class="fas fa-exchange-alt text-warning mr-2"></i> Comparador de Versiones de Código (Git Diff Visual)</span>
                <div class="d-flex align-items-center">
                    <span class="mr-2 text-xs">Selecciona Versiones:</span>
                    <select class="form-control form-control-sm mr-2" id="diff-select-base" style="width: 150px;" onchange="renderDiffComparison()"></select>
                    <span class="font-weight-bold mr-2">vs</span>
                    <select class="form-control form-control-sm mr-2" id="diff-select-target" style="width: 150px;" onchange="renderDiffComparison()"></select>
                    <button class="btn btn-sm btn-info font-weight-bold" onclick="renderDiffComparison()"><i class="fas fa-sync-alt mr-1"></i> Comparar Versiones</button>
                </div>
            </div>
            <div class="card-body p-3 bg-light">
                <div class="table-responsive bg-white border rounded shadow-sm" style="max-height: calc(100vh - 250px); overflow-y: auto;">
                    <table class="table table-bordered bg-white mb-0" style="font-family: 'Fira Code', 'Courier New', monospace; font-size: 0.85rem;">
                        <tbody id="visual-diff-tbody">
                            <tr><td colspan="4" class="text-center py-5 text-muted">Selecciona un archivo del Tab 01 para comparar sus versiones guardadas.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Scripts requeridos: Marked.js & CodeMirror con Modos Python, SQL, Shell, JS, Properties -->
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/codemirror.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/python/python.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/sql/sql.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/shell/shell.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/javascript/javascript.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/properties/properties.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/clike/clike.min.js"></script>

<script>
let filesList = [];
let activeFile = null;
let currentVersionData = null;
let codeEditor = null;

// Inicializar CodeMirror Editor con Tema Notepad++
$(document).ready(function() {
    codeEditor = CodeMirror.fromTextArea(document.getElementById('main-code-textarea'), {
        lineNumbers: true,
        theme: "eclipse",
        mode: "python",
        lineWrapping: false,
        tabSize: 4,
        indentUnit: 4,
        matchBrackets: true
    });

    loadFilesList();

    $('#git_commit_message').on('input', function() {
        let msg = $(this).val() || 'Mensaje de commit';
        $('.git-target-msg').text(msg);
    });

    $('#files-tab-search').on('keyup', function() {
        let q = $(this).val().toLowerCase();
        $('.file-table-row').each(function() {
            let name = $(this).data('name').toLowerCase();
            if (name.indexOf(q) !== -1) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    $('#doc-editor-textarea').on('input', function() {
        renderMarkdownPreview($(this).val());
    });
});

// Cambiar entre las 6 Pestañas Principales
function switchTab(tabId) {
    $('.gitlab-main-nav .nav-link').removeClass('active');
    $(`#tab-${tabId}`).addClass('active');

    $('.main-pane-view').hide();
    $(`#pane-${tabId}`).show();

    if (tabId === '05-github') {
        loadGitHistory();
    } else if (tabId === '04-doc') {
        onDocTabActive();
    } else if (tabId === '06-diff') {
        renderDiffComparison();
    } else if (tabId === '02-editor' && codeEditor) {
        setTimeout(() => { codeEditor.refresh(); }, 100);
    }
}

function updateGlobalActiveFileBar(file, versionData = null) {
    if (!file) {
        $('#global-active-filename').text('Ningún archivo seleccionado');
        $('#global-active-version-badge').hide();
        $('#global-active-meta').text('Selecciona o crea un archivo del listado para comenzar a modificarlo y sincronizarlo.');
        $('#global-active-file-icon').attr('class', 'fas fa-code-branch text-muted');
        return;
    }

    $('#global-active-filename').text(file.filename);
    
    let verNum = versionData ? versionData.version_number : (file.version_count || 1);
    $('#global-active-version-badge').text('v' + verNum).show();

    let iconClass = 'fas fa-file-code';
    if (file.file_type === 'python' || file.filename.endsWith('.py')) iconClass = 'fab fa-python text-warning';
    else if (file.file_type === 'config' || file.filename.endsWith('.ini')) iconClass = 'fas fa-cog text-secondary';
    else if (file.filename.endsWith('.sql')) iconClass = 'fas fa-database text-info';
    else iconClass = 'fas fa-file-alt text-primary';

    $('#global-active-file-icon').attr('class', iconClass);

    let updateTime = (versionData && versionData.created_at) ? versionData.created_at : (file.last_version_date || file.updated_at || '');
    let formattedDate = updateTime ? new Date(updateTime.replace(/-/g, '/')).toLocaleString('es-ES') : 'Reciente';
    $('#global-active-meta').text(`Tipo: ${file.file_type.toUpperCase()} | Versión Activa: v${verNum} | Última mod: ${formattedDate}`);
}

function loadFilesList(selectId = null) {
    $.get('api.php?action=list_files', function(res) {
        if (res.success) {
            filesList = res.data;
            let h = '';
            
            if (filesList.length === 0) {
                h = '<tr><td colspan="6" class="text-center py-5 text-muted"><i class="fas fa-file-code fa-2x mb-2 d-block"></i> No hay archivos creados aún</td></tr>';
                updateGlobalActiveFileBar(null);
            } else {
                filesList.forEach((f, idx) => {
                    let iconClass = 'fa-file-alt text-info';
                    if (f.file_type === 'python' || f.filename.endsWith('.py')) iconClass = 'fab fa-python text-warning';
                    if (f.file_type === 'config' || f.filename.endsWith('.ini')) iconClass = 'fas fa-cog text-secondary';
                    
                    let activeClass = (activeFile && activeFile.id === f.id) ? 'table-primary font-weight-bold' : '';
                    let updateDate = f.last_version_date ? new Date(f.last_version_date.replace(/-/g, '/')).toLocaleString('es-ES') : '-';
                    
                    h += `
                    <tr class="file-table-row ${activeClass}" data-id="${f.id}" data-name="${escapeHtml(f.filename)}">
                        <td>${idx + 1}</td>
                        <td class="font-weight-bold text-dark"><i class="${iconClass} mr-2"></i>${escapeHtml(f.filename)}</td>
                        <td><span class="badge badge-light border">${f.file_type.toUpperCase()}</span></td>
                        <td><span class="badge badge-success">v${f.version_count}</span></td>
                        <td class="text-muted">${updateDate}</td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm" role="group">
                                <button class="btn btn-primary font-weight-bold" onclick="selectFileAndOpenEditor(${f.id})" title="Abrir y editar en el editor de código">
                                    <i class="fas fa-edit mr-1"></i> Abrir Editor
                                </button>
                                <button class="btn btn-success font-weight-bold" onclick="quickGitPushFromFileList(${f.id})" title="Ejecutar Git Push a GitHub">
                                    <i class="fab fa-github mr-1"></i> Git Push
                                </button>
                                <button class="btn btn-danger font-weight-bold" onclick="deleteFileFromList(${f.id}, '${escapeHtml(f.filename)}')" title="Eliminar archivo">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    `;
                });

                if (selectId) {
                    selectFile(selectId);
                } else if (!activeFile && filesList.length > 0) {
                    // Auto-seleccionar el primer archivo por defecto para cargar código y activar botones
                    selectFile(filesList[0].id);
                } else if (activeFile) {
                    updateGlobalActiveFileBar(activeFile, currentVersionData);
                }
            }
            $('#files-table-tbody').html(h);
        }
    });
}

function selectFileAndOpenEditor(id) {
    selectFile(id);
    switchTab('02-editor');
}

function selectFile(id) {
    $('.file-table-row').removeClass('table-primary font-weight-bold');
    $(`.file-table-row[data-id="${id}"]`).addClass('table-primary font-weight-bold');

    $.get(`api.php?action=file_details&id=${id}`, function(res) {
        if (res.success) {
            activeFile = res.file;
            currentVersionData = res.current_version;
            
            $('#editor-empty-prompt').hide();
            $('#editor-wrapper-box').show();
            $('#editor-header-actions').css('display', 'flex');

            let iconClass = 'fa-file-alt text-info';
            if (activeFile.file_type === 'python' || activeFile.filename.endsWith('.py')) iconClass = 'fab fa-python text-warning';
            if (activeFile.file_type === 'config' || activeFile.filename.endsWith('.ini')) iconClass = 'fas fa-cog text-secondary';
            $('#active-file-icon').html(`<i class="${iconClass}"></i>`);
            $('#active-file-name').text(activeFile.filename);
            
            let createdAt = activeFile.created_at ? new Date(activeFile.created_at.replace(/-/g, '/')).toLocaleDateString('es-ES') : '-';
            let verNum = currentVersionData ? currentVersionData.version_number : 1;
            $('#active-file-meta').text(`Tipo: ${activeFile.file_type.toUpperCase()} | Creado: ${createdAt} | Viendo/Editando Versión: v${verNum}`);

            let fileCode = currentVersionData ? currentVersionData.content : '';
            
            // Determinar modo sintáctico de CodeMirror
            let cmMode = 'python';
            if (activeFile.file_type === 'python' || activeFile.filename.endsWith('.py')) cmMode = 'python';
            else if (activeFile.filename.endsWith('.sql')) cmMode = 'sql';
            else if (activeFile.filename.endsWith('.sh') || activeFile.filename.endsWith('.bash')) cmMode = 'shell';
            else if (activeFile.filename.endsWith('.js') || activeFile.filename.endsWith('.json')) cmMode = 'javascript';
            else if (activeFile.file_type === 'config' || activeFile.filename.endsWith('.ini')) cmMode = 'properties';
            else cmMode = 'text/plain';

            if (codeEditor) {
                codeEditor.setOption('mode', cmMode);
                codeEditor.setValue(fileCode);
                setTimeout(() => { codeEditor.refresh(); }, 50);
            }

            $('.git-target-filename').text(activeFile.filename);
            let defaultCommitMsg = `Actualización de ${activeFile.filename} v${verNum}`;
            $('#git_commit_message').val(defaultCommitMsg);
            $('.git-target-msg').text(defaultCommitMsg);

            updateGlobalActiveFileBar(activeFile, currentVersionData);
            onDocTabActive();
            renderVersionHistory(res.versions);
        } else {
            Swal.fire('Error', res.message || 'No se pudieron cargar los detalles del archivo', 'error');
        }
    });
}

function onDocTabActive() {
    if (!activeFile) return;

    let md = activeFile.description_md;
    if (!md || md.trim() === '') {
        md = `# Documentación de ${activeFile.filename}\n\n## Descripción General\nEste desarrollo forma parte de la automatización para FEMSA / SONDA.\n\n## Estructura y Métodos\nDescriba los componentes principales, lógica y parámetros de este script.\n\n\`\`\`bash\n# Ejemplo de ejecución\npython ${activeFile.filename}\n\`\`\`\n`;
        $('#doc-editor-textarea').val(md);
        activeFile.description_md = md;
    } else {
        $('#doc-editor-textarea').val(md);
    }
    renderMarkdownPreview(md);
}

function renderVersionHistory(versions) {
    let h = '';
    let selectAOptions = '';
    let selectBOptions = '';

    if (versions.length === 0) {
        h = '<div class="text-center py-4 text-muted">No hay versiones registradas</div>';
    } else {
        versions.forEach((v, index) => {
            let activeClass = (currentVersionData && currentVersionData.version_number === v.version_number) ? 'active' : '';
            let date = v.created_at ? new Date(v.created_at.replace(/-/g, '/')).toLocaleString('es-ES', {day: '2-digit', month: '2-digit', year:'numeric', hour: '2-digit', minute:'2-digit'}) : '-';
            
            h += `
            <div class="version-item ${activeClass}" onclick="loadSpecificVersion(${v.version_number})">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="font-weight-bold text-success" style="font-size: 0.85rem;"><i class="fas fa-tag mr-1"></i>v${v.version_number}</span>
                    <div class="d-flex align-items-center">
                        <span class="text-muted text-xs mr-2">${date}</span>
                        <button class="btn btn-xs btn-link text-danger p-0" onclick="deleteVersion(event, ${v.id}, ${v.version_number})" title="Eliminar esta versión"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </div>
                <div class="text-xs text-dark mt-1 text-truncate" title="${escapeHtml(v.change_summary)}">${escapeHtml(v.change_summary || 'Sin comentarios')}</div>
            </div>
            `;

            let isSelectedBase = (index === 1 || (versions.length === 1 && index === 0)) ? 'selected' : '';
            let isSelectedTarget = (index === 0) ? 'selected' : '';
            selectAOptions += `<option value="${v.version_number}" ${isSelectedBase}>v${v.version_number}</option>`;
            selectBOptions += `<option value="${v.version_number}" ${isSelectedTarget}>v${v.version_number}</option>`;
        });
    }

    $('#version-list-container').html(h);
    $('#diff-select-base').html(selectAOptions);
    $('#diff-select-target').html(selectBOptions);
}

function getActiveCodeContent() {
    if (codeEditor) {
        return codeEditor.getValue();
    }
    return $('#main-code-textarea').val();
}

function saveFileContent() {
    if (!activeFile) {
        Swal.fire('Atención', 'Selecciona o crea primero un archivo para guardar cambios.', 'warning');
        return;
    }
    let content = getActiveCodeContent();

    Swal.fire({
        title: 'Guardar Nueva Versión',
        input: 'text',
        inputLabel: 'Describe los cambios realizados:',
        inputPlaceholder: 'Ej: Se agregaron nuevos métodos y validaciones',
        showCancelButton: true,
        confirmButtonText: 'Guardar Versión',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            let summary = result.value;

            $.post('api.php?action=save_file', {
                id: activeFile.id,
                filename: activeFile.filename,
                file_type: activeFile.file_type,
                content: content,
                change_summary: summary
            }, function(res) {
                if (res.success) {
                    if (res.no_changes) {
                        Swal.fire('Sin cambios', res.message, 'info');
                    } else {
                        Swal.fire('Guardado', res.message, 'success');
                        let savedId = activeFile.id;
                        loadFilesList(savedId);
                    }
                } else {
                    Swal.fire('Error', res.message || 'Error al guardar.', 'error');
                }
            });
        }
    });
}

function triggerGitPushDirect() {
    if (!activeFile) {
        Swal.fire('Atención', 'Selecciona o crea primero un archivo para realizar Git Push.', 'warning');
        return;
    }
    switchTab('03-cmd');
    runGitPushFromPane();
}

function quickGitPushFromFileList(id) {
    selectFile(id);
    switchTab('03-cmd');
    setTimeout(() => {
        runGitPushFromPane();
    }, 300);
}

function deleteFileFromList(id, filename) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: `Esta acción eliminará permanentemente el archivo "${filename}" y todas sus versiones.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('api.php?action=delete_file', { id: id }, function(res) {
                if (res.success) {
                    Swal.fire('Eliminado', res.message, 'success');
                    if (activeFile && activeFile.id === id) {
                        activeFile = null;
                        currentVersionData = null;
                        updateGlobalActiveFileBar(null);
                        $('#editor-wrapper-box').hide();
                        $('#editor-header-actions').hide();
                        $('#editor-empty-prompt').show();
                    }
                    loadFilesList();
                } else {
                    Swal.fire('Error', res.message || 'No se pudo eliminar.', 'error');
                }
            });
        }
    });
}

function deleteActiveFile() {
    if (!activeFile) {
        Swal.fire('Atención', 'Selecciona primero un archivo para eliminar.', 'warning');
        return;
    }
    deleteFileFromList(activeFile.id, activeFile.filename);
}

function deleteVersion(event, versionId, versionNum) {
    event.stopPropagation();
    Swal.fire({
        title: '¿Eliminar versión?',
        text: `¿Deseas eliminar la versión v${versionNum}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Sí, eliminar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('api.php?action=delete_version', { version_id: versionId }, function(res) {
                if (res.success) {
                    toastr.success(res.message);
                    if (activeFile) {
                        selectFile(activeFile.id);
                        loadFilesList();
                    }
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            });
        }
    });
}

function loadSpecificVersion(verNum) {
    if (!activeFile) return;
    $.get(`api.php?action=file_details&id=${activeFile.id}&version=${verNum}`, function(res) {
        if (res.success) {
            currentVersionData = res.current_version;
            $('.version-item').removeClass('active');
            $(`.version-item:contains("v${verNum}")`).addClass('active');

            let createdAt = activeFile.created_at ? new Date(activeFile.created_at.replace(/-/g, '/')).toLocaleDateString('es-ES') : '-';
            $('#active-file-meta').text(`Tipo: ${activeFile.file_type.toUpperCase()} | Creado: ${createdAt} | Viendo Versión: v${currentVersionData.version_number}`);

            let fileCode = currentVersionData ? currentVersionData.content : '';
            if (codeEditor) {
                codeEditor.setValue(fileCode);
                setTimeout(() => { codeEditor.refresh(); }, 50);
            }
            $('#editor-header-actions').css('display', 'flex');
            updateGlobalActiveFileBar(activeFile, currentVersionData);
        }
    });
}

function runGitPushFromPane() {
    if (!activeFile) {
        Swal.fire('Atención', 'Selecciona primero un archivo del Tab 01 para realizar Git Push.', 'warning');
        return;
    }

    let content = getActiveCodeContent();
    let commitMsg = $('#git_commit_message').val() || `Actualización de ${activeFile.filename}`;

    $('#pane-git-terminal-text').text('Iniciando comandos Git Push en servidor...\ngit add ' + activeFile.filename + '\ngit commit -m "' + commitMsg + '"\ngit push origin main\n\nConectando con el repositorio remoto (' + $('#git_remote_url').val() + ')...');

    $.ajax({
        url: 'api.php?action=git_push',
        type: 'POST',
        data: {
            action: 'git_push',
            file_id: activeFile.id,
            filename: activeFile.filename,
            content: content,
            commit_message: commitMsg,
            remote_url: $('#git_remote_url').val()
        },
        timeout: 120000,
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                toastr.success(res.message);
                $('#pane-git-terminal-text').text(`¡ÉXITO EN GIT PUSH!\nCommit Hash: ${res.commit_hash || 'OK'}\nComando: ${res.command_run}\n\n[OUTPUT LOG DE GITHUB]:\n${res.git_output}`);
            } else {
                toastr.error('Error en Git Push');
                $('#pane-git-terminal-text').text(`ERROR EN GIT PUSH:\n${res.message}\nComando: ${res.command_run}\n\n[LOG COMPLETO]:\n${res.git_output}`);
            }
        },
        error: function(xhr, status, error) {
            toastr.error('Respuesta o error de servidor en Git Push');
            let serverErrorDetails = xhr.responseText ? xhr.responseText : (error || status);
            $('#pane-git-terminal-text').text(`DETALLE DEL ERROR DEL SERVIDOR (${status}):\n${serverErrorDetails}`);
        }
    });
}

function loadGitHistory() {
    $('#github-history-tbody').html('<tr><td colspan="5" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin mr-2"></i>Cargando historial de GitHub...</td></tr>');
    
    $.get('api.php?action=git_history', function(res) {
        if (res.success) {
            $('#github-git-log-raw').text(res.git_log_raw || 'No se registraron commits aún.');

            let logs = res.db_logs || [];
            let h = '';
            if (logs.length === 0) {
                h = '<tr><td colspan="5" class="text-center py-4 text-muted">No hay publicaciones a GitHub registradas aún.</td></tr>';
            } else {
                logs.forEach(l => {
                    let badgeClass = l.status === 'success' ? 'badge-success' : 'badge-danger';
                    let dateStr = l.created_at ? new Date(l.created_at.replace(/-/g, '/')).toLocaleString('es-ES') : '-';
                    h += `
                    <tr>
                        <td><span class="badge badge-dark font-weight-bold">${escapeHtml(l.commit_hash || 'HEAD')}</span></td>
                        <td class="font-weight-bold text-primary">${escapeHtml(l.filename || 'General')}</td>
                        <td>${escapeHtml(l.commit_message || 'Sin mensaje')}</td>
                        <td class="text-muted">${dateStr}</td>
                        <td><span class="badge ${badgeClass}">${escapeHtml(l.status.toUpperCase())}</span></td>
                    </tr>
                    `;
                });
            }
            $('#github-history-tbody').html(h);
        }
    });
}

function renderDiffComparison() {
    if (!activeFile) return;

    let verA = $('#diff-select-base').val();
    let verB = $('#diff-select-target').val();

    if (!verA || !verB) return;

    $('#visual-diff-tbody').html('<tr><td colspan="4" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin mr-2"></i>Generando comparación de código...</td></tr>');

    $.get(`api.php?action=file_details&id=${activeFile.id}&version=${verA}`, function(resA) {
        if (resA.success) {
            let contentA = resA.current_version.content;
            
            $.get(`api.php?action=file_details&id=${activeFile.id}&version=${verB}`, function(resB) {
                if (resB.success) {
                    let contentB = resB.current_version.content;
                    buildVisualDiffTable(contentA, contentB);
                }
            });
        }
    });
}

function buildVisualDiffTable(textA, textB) {
    let linesA = textA.split(/\r?\n/);
    let linesB = textB.split(/\r?\n/);
    let maxLines = Math.max(linesA.length, linesB.length);
    let html = '';

    for (let i = 0; i < maxLines; i++) {
        let lineA = linesA[i] !== undefined ? linesA[i] : null;
        let lineB = linesB[i] !== undefined ? linesB[i] : null;

        let classA = '';
        let classB = '';

        if (lineA !== lineB) {
            if (lineA !== null && lineB === null) {
                classA = 'diff-line-removed';
            } else if (lineA === null && lineB !== null) {
                classB = 'diff-line-added';
            } else {
                classA = 'diff-line-removed';
                classB = 'diff-line-added';
            }
        }

        let numA = lineA !== null ? (i + 1) : '';
        let numB = lineB !== null ? (i + 1) : '';
        let textDisplayA = lineA !== null ? escapeHtml(lineA) : '';
        let textDisplayB = lineB !== null ? escapeHtml(lineB) : '';

        html += `
        <tr>
            <td class="diff-line-number">${numA}</td>
            <td class="${classA}" style="width: 48%; white-space: pre-wrap; word-break: break-all;">${textDisplayA}</td>
            <td class="diff-line-number">${numB}</td>
            <td class="${classB}" style="width: 48%; white-space: pre-wrap; word-break: break-all;">${textDisplayB}</td>
        </tr>
        `;
    }

    $('#visual-diff-tbody').html(html);
}

function saveMarkdownDescription() {
    if (!activeFile) {
        toastr.info('Selecciona un archivo para guardar su documentación');
        return;
    }
    let desc = $('#doc-editor-textarea').val();

    $.post('api.php?action=save_description', {
        id: activeFile.id,
        description_md: desc
    }, function(res) {
        if (res.success) {
            toastr.success(res.message);
            activeFile.description_md = desc;
        } else {
            Swal.fire('Error', res.message || 'Error al guardar documentación', 'error');
        }
    });
}

function renderMarkdownPreview(mdText) {
    if (typeof marked !== 'undefined') {
        let cleanHtml = marked.parse(mdText || '*No hay documentación registrada.*');
        $('#doc-preview-container').html(cleanHtml);
    }
}

function createNewFileModal() {
    Swal.fire({
        title: 'Crear Nuevo Archivo',
        html: `
            <div class="text-left">
                <div class="form-group">
                    <label>Nombre del archivo (con extensión):</label>
                    <input type="text" id="swal-filename" class="form-control" placeholder="ej: script_zabbix.py, settings.ini, logs.txt">
                </div>
                <div class="form-group">
                    <label>Tipo de archivo:</label>
                    <select id="swal-filetype" class="form-control">
                        <option value="python">Python Script (.py)</option>
                        <option value="config">Configuration / Properties (.ini, .conf, .json)</option>
                        <option value="txt">Plain Text / Logs (.txt, .sql)</option>
                    </select>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Crear Archivo',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            let filename = $('#swal-filename').val().trim();
            let file_type = $('#swal-filetype').val();
            if (!filename) {
                Swal.showValidationMessage('El nombre de archivo es requerido');
            }
            return { filename: filename, file_type: file_type };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            let data = result.value;
            $.post('api.php?action=save_file', {
                filename: data.filename,
                file_type: data.file_type,
                content: '# Nuevo archivo ' + data.filename
            }, function(res) {
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Archivo Creado',
                        text: res.message,
                        timer: 1200,
                        showConfirmButton: false
                    });
                    loadFilesList(res.file_id);
                    selectFileAndOpenEditor(res.file_id);
                } else {
                    Swal.fire('Error', res.message || 'Error al crear el archivo.', 'error');
                }
            });
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
</script>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>

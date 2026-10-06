<?php
/**
 * Aranda API Module - CMDB VILASECA
 * Location: /var/www/html/VILASECA/CMDBPRnew/public/aranda.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/helpers.php';

// Require login
require_login();

$page_title = 'Módulo Aranda API';
$page_icon = 'fas fa-project-diagram';

// Read pass.xt credentials
$pass_file = __DIR__ . '/../aranda/pass.xt';
$credentials = [
    'user' => 'srvcl_qa49@sndint63loc.cl',
    'token' => '',
    'id' => '1252367' // default fallback
];

if (file_exists($pass_file)) {
    $content = file_get_contents($pass_file);
    // Extract token
    if (preg_match('/(eyJ[a-zA-Z0-9_\-\.]+)/', $content, $m)) {
        $credentials['token'] = $m[1];
        
        // Decode JWT payload to get SubjectIdentifier
        $parts = explode('.', $m[1]);
        if (count($parts) === 3) {
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
            if (isset($payload['SubjectIdentifier'])) {
                $credentials['id'] = $payload['SubjectIdentifier'];
            }
        }
    }
    // Extract user
    if (preg_match('/usuario\s+(\S+)/i', $content, $m)) {
        $credentials['user'] = $m[1];
    }
}


// Read apis.json
$apis_file = __DIR__ . '/../aranda/apis.json';
$apis_data = [];
if (file_exists($apis_file)) {
    $apis_data = json_decode(file_get_contents($apis_file), true);
}

require_once __DIR__ . '/partials/header.php';
?>

<!-- Animate.css for subtle entry effects -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

<style>
    :root {
        --aranda-blue: #0056b3;
        --aranda-indigo: #4e73df;
        --aranda-success: #1cc88a;
        --aranda-warning: #f6c23e;
        --aranda-danger: #e74a3b;
        --aranda-dark: #5a5c69;
        --card-border-radius: 12px;
        --glass-bg: rgba(255, 255, 255, 0.85);
        --glass-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.08);
    }

    /* Dark mode overrides */
    .dark-mode {
        --glass-bg: rgba(30, 41, 59, 0.85);
        --glass-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
    }

    .glass-card {
        background: var(--glass-bg);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        border: 1px solid rgba(255, 255, 255, 0.18);
        border-radius: var(--card-border-radius);
        box-shadow: var(--glass-shadow);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .glass-card:hover {
        box-shadow: 0 12px 40px 0 rgba(31, 38, 135, 0.12);
    }

    .dark-mode .glass-card {
        border-color: rgba(255, 255, 255, 0.05);
    }

    .nav-tabs-custom {
        border-bottom: 2px solid #e2e8f0;
    }
    .dark-mode .nav-tabs-custom {
        border-bottom-color: #334155;
    }

    .nav-tabs-custom .nav-link {
        border: none;
        color: #64748b;
        font-weight: 600;
        padding: 12px 20px;
        transition: all 0.2s;
        border-bottom: 3px solid transparent;
    }

    .nav-tabs-custom .nav-link:hover {
        color: var(--aranda-indigo);
    }

    .nav-tabs-custom .nav-link.active {
        color: var(--aranda-indigo);
        background: transparent;
        border-bottom-color: var(--aranda-indigo);
    }

    .method-badge {
        font-size: 0.7rem;
        font-weight: 800;
        padding: 4px 8px;
        border-radius: 4px;
        color: #fff;
        text-transform: uppercase;
        display: inline-block;
        min-width: 60px;
        text-align: center;
    }

    .method-get { background-color: var(--aranda-success); }
    .method-post { background-color: var(--aranda-indigo); }
    .method-put { background-color: var(--aranda-warning); color: #212529 !important; }
    .method-delete { background-color: var(--aranda-danger); }

    .api-item {
        cursor: pointer;
        padding: 12px;
        border-radius: 8px;
        border: 1px solid #f1f5f9;
        margin-bottom: 8px;
        transition: all 0.15s ease;
    }
    .dark-mode .api-item {
        border-color: #334155;
    }

    .api-item:hover, .api-item.active {
        background-color: rgba(78, 115, 223, 0.08);
        border-color: var(--aranda-indigo);
    }

    .api-list-container {
        max-height: 600px;
        overflow-y: auto;
    }

    .json-block {
        background-color: #0f172a;
        color: #38bdf8;
        padding: 15px;
        border-radius: 8px;
        font-family: 'Courier New', Courier, monospace;
        font-size: 0.85rem;
        overflow-x: auto;
        border: 1px solid #1e293b;
        max-height: 350px;
    }

    .doc-section-title {
        font-size: 0.95rem;
        font-weight: 700;
        color: #475569;
        margin-top: 1.5rem;
        margin-bottom: 0.5rem;
        border-left: 3px solid var(--aranda-indigo);
        padding-left: 8px;
    }
    .dark-mode .doc-section-title {
        color: #cbd5e1;
    }

    .credential-box {
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 12px;
    }
    .dark-mode .credential-box {
        background-color: #1e293b;
        border-color: #334155;
    }

    /* Classification CSS Cards */
    .class-card {
        border-top: 4px solid var(--aranda-indigo);
        height: 100%;
    }
    .class-card.casos { border-top-color: var(--aranda-indigo); }
    .class-card.tareas { border-top-color: var(--aranda-success); }
    .class-card.usuarios { border-top-color: var(--aranda-warning); }
    .class-card.archivos { border-top-color: var(--aranda-danger); }
    .class-card.catalogos { border-top-color: var(--aranda-dark); }

    /* Flow diagram styled boxes */
    .flow-step {
        background: #f8fafc;
        border: 2px solid #cbd5e1;
        border-radius: 8px;
        padding: 15px;
        text-align: center;
        position: relative;
    }
    .dark-mode .flow-step {
        background: #1e293b;
        border-color: #475569;
    }
</style>

<div class="container-fluid pt-3">
    <!-- Header Summary Box -->
    <div class="glass-card p-4 mb-4 animate__animated animate__fadeIn">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h4 class="font-weight-bold text-dark mb-1">
                    <i class="fas fa-network-wired text-primary mr-2"></i>Módulo Aranda Service Management API
                </h4>
                <p class="text-muted mb-0">
                    Este es un panel de control independiente para explorar la documentación técnica, realizar pruebas de acceso reales utilizando las credenciales provistas y revisar la clasificación operativa de la API de Aranda.
                </p>
            </div>
            <div class="col-md-4 text-right">
                <span class="badge badge-light border text-muted px-3 py-2">
                    <i class="fas fa-file-pdf text-danger mr-1"></i> PDF: asms-api.pdf (100 Pág)
                </span>
                <span class="badge badge-primary px-3 py-2 ml-1">
                    <i class="fas fa-key mr-1"></i> Token: Activo (Dec. 2026)
                </span>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs nav-tabs-custom mb-4" id="arandaTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="apis-tab" data-toggle="tab" href="#apis" role="tab" aria-controls="apis" aria-selected="true">
                <i class="fas fa-book-open mr-2"></i>Catálogo de APIs
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="tests-tab" data-toggle="tab" href="#tests" role="tab" aria-controls="tests" aria-selected="false">
                <i class="fas fa-vial mr-2"></i>Pruebas de Acceso
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="classification-tab" data-toggle="tab" href="#classification" role="tab" aria-controls="classification" aria-selected="false">
                <i class="fas fa-sitemap mr-2"></i>Clasificación de Uso
            </a>
        </li>
    </ul>

    <!-- Tabs Content -->
    <div class="tab-content" id="arandaTabsContent">
        
        <!-- TAB 1: APIs CATALOGUE -->
        <div class="tab-pane fade show active" id="apis" role="tabpanel" aria-labelledby="apis-tab">
            <div class="row">
                <!-- Left Sidebar: API List -->
                <div class="col-md-5">
                    <div class="glass-card card mb-4">
                        <div class="card-header bg-white py-3">
                            <h6 class="mb-0 font-weight-bold text-dark">
                                <i class="fas fa-list mr-1"></i>Lista de Endpoints (<?php echo count($apis_data); ?>)
                            </h6>
                        </div>
                        <div class="card-body">
                            <!-- Filters and Search -->
                            <div class="input-group input-group-sm mb-3">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light"><i class="fas fa-search"></i></span>
                                </div>
                                <input type="text" id="apiSearch" class="form-control" placeholder="Buscar por título, ruta o descripción...">
                            </div>

                            <div class="d-flex justify-content-between mb-3">
                                <button class="btn btn-xs btn-outline-secondary btn-filter active" data-filter="ALL">Todos</button>
                                <button class="btn btn-xs btn-outline-success btn-filter" data-filter="GET">GET</button>
                                <button class="btn btn-xs btn-outline-primary btn-filter" data-filter="POST">POST</button>
                                <button class="btn btn-xs btn-outline-warning btn-filter" data-filter="PUT">PUT</button>
                                <button class="btn btn-xs btn-outline-danger btn-filter" data-filter="DELETE">DELETE</button>
                            </div>

                            <div class="api-list-container pr-1" id="apiList">
                                <?php if (empty($apis_data)): ?>
                                    <div class="text-center text-muted p-5">
                                        <i class="fas fa-exclamation-circle fa-2x mb-2"></i>
                                        <p>No se encontraron APIs registradas. Ejecute la base de datos de APIs.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($apis_data as $index => $api): ?>
                                        <div class="api-item" data-index="<?php echo $index; ?>" data-method="<?php echo $api['method']; ?>" data-title="<?php echo htmlspecialchars($api['title']); ?>" data-uri="<?php echo htmlspecialchars($api['uri']); ?>">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <span class="method-badge method-<?php echo strtolower($api['method']); ?>"><?php echo $api['method']; ?></span>
                                                <small class="text-muted">Pág. <?php echo $api['page']; ?></small>
                                            </div>
                                            <div class="font-weight-bold text-dark mt-2 truncate" style="font-size:0.9rem;">
                                                <?php echo htmlspecialchars($api['title']); ?>
                                            </div>
                                            <div class="text-muted small truncate" style="font-family: monospace;">
                                                <?php echo htmlspecialchars($api['uri']); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Pane: API Details -->
                <div class="col-md-7">
                    <div class="glass-card card mb-4" id="apiDetailsPlaceholder">
                        <div class="card-body text-center py-5 text-muted">
                            <i class="fas fa-arrow-left fa-3x mb-3 text-secondary animate__animated animate__pulse animate__infinite"></i>
                            <h5>Seleccione un endpoint del listado</h5>
                            <p class="small">Haga clic en cualquiera de las APIs de la izquierda para ver su especificación detallada, parámetros y datos de ejemplo.</p>
                        </div>
                    </div>

                    <div class="glass-card card mb-4 d-none" id="apiDetailsPanel">
                        <div class="card-header bg-white d-flex align-items-center justify-content-between py-3">
                            <h5 class="mb-0 font-weight-bold text-dark" id="detTitle">API Title</h5>
                            <button class="btn btn-sm btn-outline-primary" id="btnLoadInTester">
                                <i class="fas fa-vial mr-1"></i>Probar API
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <span class="method-badge mr-2" id="detMethod">GET</span>
                                <code class="h6 font-weight-bold text-primary mb-0" id="detUri" style="font-family: monospace;">/api/v9/item</code>
                            </div>

                            <p class="text-secondary" id="detDesc">Description here</p>

                            <div class="doc-section-title">Encabezados Requeridos</div>
                            <table class="table table-sm table-bordered bg-light">
                                <thead>
                                    <tr>
                                        <th>Header</th>
                                        <th>Valor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><code>Content-Type</code></td>
                                        <td><code>application/json</code></td>
                                    </tr>
                                    <tr>
                                        <td><code>X-Authorization</code></td>
                                        <td><code>Bearer {token_de_acceso}</code></td>
                                    </tr>
                                    <tr id="tenantHeaderRow" class="d-none">
                                        <td><code>x-aranda-tenant-alias</code></td>
                                        <td><code>{tenant_alias}</code> <small class="text-muted">(Solo ambiente multitenant)</small></td>
                                    </tr>
                                </tbody>
                            </table>

                            <div class="doc-section-title">Parámetros del Endpoint</div>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped" id="detParamsTable">
                                    <thead>
                                        <tr>
                                            <th>Nombre</th>
                                            <th>Tipo</th>
                                            <th>Obligatorio</th>
                                            <th>Descripción</th>
                                        </tr>
                                    </thead>
                                    <tbody id="detParamsBody">
                                        <!-- Dinámico -->
                                    </tbody>
                                </table>
                            </div>

                            <div id="detRequestBodySection" class="d-none">
                                <div class="doc-section-title">Ejemplo del Cuerpo de Petición (JSON)</div>
                                <pre class="json-block" id="detRequestBodyJSON">{}</pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: ACCESS TESTING PLATFORM -->
        <div class="tab-pane fade" id="tests" role="tabpanel" aria-labelledby="tests-tab">
            <!-- Warning Banner for Token/Host mismatch -->
            <div id="tokenWarningAlert" class="alert alert-warning d-none mb-3">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong>Advertencia de Credenciales:</strong> Estás intentando probar contra el servidor de <strong>EP Petroecuador</strong> utilizando el token de pruebas de <strong>Sonda/QA</strong> (<em>asms.arandasoft.com</em>). Para evitar un error <code>HTTP 500 (Internal Server Error)</code>, por favor ingresa un token de integración válido para Petroecuador.
            </div>

            <div class="row">
                <!-- Credentials settings -->
                <div class="col-md-4">
                    <div class="glass-card card mb-4">
                        <div class="card-header bg-white py-3">
                            <h6 class="mb-0 font-weight-bold text-dark">
                                <i class="fas fa-lock mr-1"></i>Credenciales de Acceso
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="small font-weight-bold">Host / Servidor Aranda</label>
                                <input type="text" id="testHost" class="form-control form-control-sm" value="https://centroservicios.eppetroecuador.ec" placeholder="https://dominio-aranda.com">
                                <small class="text-muted">Ruta Base API: <code id="basePathLabel">/</code></small>
                            </div>

                            <div class="form-group">
                                <label class="small font-weight-bold">Usuario de Integración</label>
                                <input type="text" id="testUser" class="form-control form-control-sm" value="<?php echo htmlspecialchars($credentials['user']); ?>" readonly>
                            </div>

                            <div class="form-group">
                                <label class="small font-weight-bold">Token de Integración (JWT)</label>
                                <textarea id="testToken" class="form-control form-control-sm" rows="6" style="font-family: monospace; font-size:0.75rem;"><?php echo htmlspecialchars($credentials['token']); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label class="small font-weight-bold">Alias de Tenant (Multitenant - Opcional)</label>
                                <input type="text" id="testTenant" class="form-control form-control-sm" placeholder="Ej. petroecuador">
                            </div>

                            <div class="alert alert-info py-2 px-3 small">
                                <i class="fas fa-info-circle mr-1"></i> Estas credenciales se recuperan automáticamente del archivo <code>pass.xt</code> dentro de la carpeta <code>aranda/</code>.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Test Execution Panel -->
                <div class="col-md-8">
                    <!-- Request Configuration -->
                    <div class="glass-card card mb-4">
                        <div class="card-header bg-white py-3">
                            <h6 class="mb-0 font-weight-bold text-dark">
                                <i class="fas fa-paper-plane mr-1"></i>Configurar Petición
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="small font-weight-bold">Método</label>
                                        <select id="reqMethod" class="form-control">
                                            <option value="GET">GET</option>
                                            <option value="POST">POST</option>
                                            <option value="PUT">PUT</option>
                                            <option value="DELETE">DELETE</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-9">
                                    <div class="form-group">
                                        <label class="small font-weight-bold">URL Destino</label>
                                        <input type="text" id="reqUrl" class="form-control" value="https://centroservicios.eppetroecuador.ec/ASMSAPI/api/v9/user?id=1252367" placeholder="https://...">
                                    </div>
                                </div>
                            </div>


                            <div class="form-group d-none" id="reqBodyGroup">
                                <label class="small font-weight-bold">Cuerpo de la Petición (JSON)</label>
                                <textarea id="reqBody" class="form-control" rows="6" style="font-family: monospace; font-size:0.85rem;" placeholder="{}"></textarea>
                            </div>

                            <button class="btn btn-primary" id="btnSendRequest">
                                <i class="fas fa-play mr-1"></i>Enviar Petición (Ejecutar Prueba)
                            </button>
                        </div>
                    </div>

                    <!-- Response Display Console -->
                    <div class="glass-card card mb-4">
                        <div class="card-header bg-white d-flex align-items-center justify-content-between py-3">
                            <h6 class="mb-0 font-weight-bold text-dark">
                                <i class="fas fa-terminal mr-1"></i>Consola de Respuesta
                            </h6>
                            <span id="respStatus" class="badge p-2 d-none">Status</span>
                        </div>
                        <div class="card-body p-0">
                            <!-- Preloader -->
                            <div id="respLoading" class="p-5 text-center d-none">
                                <i class="fas fa-spinner fa-spin fa-2x text-primary mb-2"></i>
                                <p class="mb-0">Esperando respuesta del servidor...</p>
                            </div>

                            <div id="respInitial" class="p-5 text-center text-muted">
                                <i class="fas fa-laptop-code fa-2x mb-2"></i>
                                <p class="mb-0">Configure su petición y presione "Enviar Petición" para visualizar la respuesta aquí.</p>
                            </div>

                            <div id="respResult" class="d-none">
                                <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center">
                                    <span class="small font-weight-bold">HTTP Response Payload</span>
                                    <button class="btn btn-xs btn-outline-secondary" onclick="copyResponse()">Copiar</button>
                                </div>
                                <pre class="json-block mb-0 rounded-0" style="max-height: 400px;" id="respBody">{}</pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 3: CLASSIFICATION AND OPERATIONAL USE CASES -->
        <div class="tab-pane fade" id="classification" role="tabpanel" aria-labelledby="classification-tab">
            <h5 class="font-weight-bold text-dark mb-3">Clasificación Operativa de la API de Aranda</h5>
            
            <div class="row">
                <!-- Group 1 -->
                <div class="col-md-4 mb-4">
                    <div class="card glass-card class-card casos">
                        <div class="card-body">
                            <h5 class="card-title font-weight-bold text-primary mb-2">
                                <i class="fas fa-ticket-alt mr-2"></i>1. Gestión de Casos
                            </h5>
                            <p class="card-text small text-muted">
                                Permite la manipulación del ciclo de vida de los casos (Incidentes, Cambios, Problemas, Requerimientos).
                            </p>
                            <ul class="small pl-3 text-secondary">
                                <li><code>POST /api/v9/item</code> (Creación)</li>
                                <li><code>GET /api/v9/item/{id}</code> (Detalle)</li>
                                <li><code>PUT /api/v9/item/{id}</code> (Edición)</li>
                                <li><code>POST /api/v9/item/search</code> (Búsqueda)</li>
                            </ul>
                            <div class="alert alert-light border py-1 px-2 small mb-0 mt-3 text-secondary">
                                <strong>Caso de uso:</strong> Integrar alarmas de Zabbix para levantar automáticamente incidentes en Aranda.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Group 2 -->
                <div class="col-md-4 mb-4">
                    <div class="card glass-card class-card tareas">
                        <div class="card-body">
                            <h5 class="card-title font-weight-bold text-success mb-2">
                                <i class="fas fa-tasks mr-2"></i>2. Tareas y Relaciones
                            </h5>
                            <p class="card-text small text-muted">
                                Permite vincular casos entre sí o crear tareas secundarias asignadas a especialistas.
                            </p>
                            <ul class="small pl-3 text-secondary">
                                <li><code>POST /api/v9/task</code> (Crear Tarea)</li>
                                <li><code>POST /api/v9/item/{id}/relation</code> (Vincular)</li>
                                <li><code>GET /api/v9/item/list/parent/{id}/close</code> (Verificar dependencias)</li>
                            </ul>
                            <div class="alert alert-light border py-1 px-2 small mb-0 mt-3 text-secondary">
                                <strong>Caso de uso:</strong> Relacionar múltiples incidentes de usuarios a un problema maestro de infraestructura.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Group 3 -->
                <div class="col-md-4 mb-4">
                    <div class="card glass-card class-card usuarios">
                        <div class="card-body">
                            <h5 class="card-title font-weight-bold text-warning mb-2">
                                <i class="fas fa-users mr-2"></i>3. Usuarios y Compañías
                            </h5>
                            <p class="card-text small text-muted">
                                Administra el directorio de usuarios, especialistas y asignación de compañías cliente.
                            </p>
                            <ul class="small pl-3 text-secondary">
                                <li><code>POST /api/v9/user</code> (Crear Usuario)</li>
                                <li><code>GET /api/v9/user/searchAll</code> (Filtrar Especialistas)</li>
                                <li><code>POST /api/v9/company</code> (Crear Compañía/Cliente)</li>
                            </ul>
                            <div class="alert alert-light border py-1 px-2 small mb-0 mt-3 text-secondary">
                                <strong>Caso de uso:</strong> Sincronizar especialistas del Directorio Activo (AD) con Aranda.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Group 4 -->
                <div class="col-md-6 mb-4">
                    <div class="card glass-card class-card archivos">
                        <div class="card-body">
                            <h5 class="card-title font-weight-bold text-danger mb-2">
                                <i class="fas fa-file-upload mr-2"></i>4. Archivos y Adjuntos
                            </h5>
                            <p class="card-text small text-muted">
                                Facilita la carga y descarga de evidencias adjuntas a los casos.
                            </p>
                            <ul class="small pl-3 text-secondary">
                                <li><code>POST /api/v9/file</code> (Subida Temporal)</li>
                                <li><code>GET /api/v9/item/{id}/files</code> (Listar adjuntos)</li>
                                <li><code>DELETE /api/v9/file/{id}</code> (Eliminar adjunto)</li>
                            </ul>
                            <div class="alert alert-light border py-1 px-2 small mb-0 mt-3 text-secondary">
                                <strong>Caso de uso:</strong> Adjuntar capturas de pantalla de errores generadas por scripts de monitoreo a los casos.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Group 5 -->
                <div class="col-md-6 mb-4">
                    <div class="card glass-card class-card catalogos">
                        <div class="card-body">
                            <h5 class="card-title font-weight-bold text-dark mb-2">
                                <i class="fas fa-database mr-2"></i>5. Catálogos e Información Maestro
                            </h5>
                            <p class="card-text small text-muted">
                                Consulta la taxonomía del sistema: categorías, servicios, campos adicionales obligatorios y proyectos.
                            </p>
                            <ul class="small pl-3 text-secondary">
                                <li><code>GET /api/v9/item/{itemType}/services/{serviceId}/categories</code></li>
                                <li><code>POST /api/v9/item/additionalfields</code></li>
                                <li><code>GET /api/v9/catalog/{id}</code></li>
                            </ul>
                            <div class="alert alert-light border py-1 px-2 small mb-0 mt-3 text-secondary">
                                <strong>Caso de uso:</strong> Cargar las categorías correctas en formularios de autogestión de clientes externos.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Workflow Diagram card -->
            <div class="glass-card card mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 font-weight-bold text-dark">
                        <i class="fas fa-exchange-alt mr-1"></i>Diagrama de Integración Típica (Monitoreo ➔ Aranda)
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-3">
                            <div class="flow-step mb-3">
                                <div class="badge badge-danger position-absolute" style="top:-10px; left:10px;">1</div>
                                <h6 class="font-weight-bold text-danger"><i class="fas fa-bell mr-1"></i>Alarma Zabbix</h6>
                                <p class="small text-muted mb-0">Un servidor o servicio crítico entra en estado DOWN.</p>
                            </div>
                        </div>
                        <div class="col-md-1 text-center d-none d-md-block">
                            <i class="fas fa-arrow-right fa-2x text-muted"></i>
                        </div>
                        <div class="col-md-4">
                            <div class="flow-step mb-3">
                                <div class="badge badge-primary position-absolute" style="top:-10px; left:10px;">2</div>
                                <h6 class="font-weight-bold text-primary"><i class="fas fa-code mr-1"></i>Consumo API (POST)</h6>
                                <p class="small text-muted mb-0">
                                    Script PHP realiza petición a <code>POST /api/v9/item</code> pre-llenando la categoría, el servicio y descripción de la falla.
                                </p>
                            </div>
                        </div>
                        <div class="col-md-1 text-center d-none d-md-block">
                            <i class="fas fa-arrow-right fa-2x text-muted"></i>
                        </div>
                        <div class="col-md-3">
                            <div class="flow-step mb-3">
                                <div class="badge badge-success position-absolute" style="top:-10px; left:10px;">3</div>
                                <h6 class="font-weight-bold text-success"><i class="fas fa-check-double mr-1"></i>Ticket Creado</h6>
                                <p class="small text-muted mb-0">Aranda retorna 200 OK con el ID del caso y lo asigna al grupo correspondiente.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- API Catalogue JS Controller -->
<script>
    // Global API list parsed from PHP
    const apis = <?php echo json_encode($apis_data); ?>;
    const credentials = <?php echo json_encode($credentials); ?>;

    // Helper to decode JWT token payload on client side
    function parseJwt(token) {
        try {
            const base64Url = token.split('.')[1];
            const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
            const jsonPayload = decodeURIComponent(atob(base64).split('').map(function(c) {
                return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
            }).join(''));
            return JSON.parse(jsonPayload);
        } catch (e) {
            return null;
        }
    }

    // Check if the current Host is Petroecuador but the Token belongs to Sonda QA
    function validateTokenHostMismatch() {
        const host = $('#testHost').val().trim();
        const token = $('#testToken').val().trim();
        let isMismatch = false;
        
        if (host.includes('eppetroecuador.ec')) {
            const payload = parseJwt(token);
            if (payload && payload.IssuerIdentifier && payload.IssuerIdentifier.includes('asms.arandasoft.com')) {
                isMismatch = true;
            }
        }
        
        if (isMismatch) {
            $('#tokenWarningAlert').removeClass('d-none');
        } else {
            $('#tokenWarningAlert').addClass('d-none');
        }
    }
    
    $(document).ready(function() {
        // Initial token/host validation and event listeners
        $('#testHost, #testToken').on('input change', function() {
            validateTokenHostMismatch();
        });
        validateTokenHostMismatch();

        // Search Filter
        $('#apiSearch').on('input', function() {
            filterApis();
        });

        // Method Filter Buttons
        $('.btn-filter').on('click', function() {
            $('.btn-filter').removeClass('active');
            $(this).addClass('active');
            filterApis();
        });

        function filterApis() {
            const query = $('#apiSearch').val().toLowerCase();
            const methodFilter = $('.btn-filter.active').data('filter');

            $('.api-item').each(function() {
                const item = $(this);
                const title = item.data('title').toLowerCase();
                const uri = item.data('uri').toLowerCase();
                const method = item.data('method').toUpperCase();

                const matchesSearch = title.includes(query) || uri.includes(query);
                const matchesMethod = (methodFilter === 'ALL' || method === methodFilter);

                if (matchesSearch && matchesMethod) {
                    item.removeClass('d-none');
                } else {
                    item.addClass('d-none');
                }
            });
        }

        // Click API Item to view details
        $('.api-item').on('click', function() {
            $('.api-item').removeClass('active');
            $(this).addClass('active');

            const index = $(this).data('index');
            const api = apis[index];

            // Fill details
            $('#apiDetailsPlaceholder').addClass('d-none');
            $('#apiDetailsPanel').removeClass('d-none');

            $('#detTitle').text(api.title);
            $('#detMethod').text(api.method).attr('class', 'method-badge mr-2 method-' + api.method.toLowerCase());
            $('#detUri').text(api.uri);
            $('#detDesc').text(api.description || 'Sin descripción disponible.');

            // Build Params table
            const paramsBody = $('#detParamsBody');
            paramsBody.empty();

            if (api.params && api.params.length > 0) {
                api.params.forEach(p => {
                    const reqBadge = p.required === 'Sí' ? '<span class="badge badge-danger">Sí</span>' : '<span class="badge badge-secondary">No</span>';
                    paramsBody.append(`
                        <tr>
                            <td><code>${p.name}</code></td>
                            <td><span class="badge badge-light text-muted">${p.type}</span></td>
                            <td>${reqBadge}</td>
                            <td>${p.description}</td>
                        </tr>
                    `);
                });
                $('#detParamsTable').removeClass('d-none');
            } else {
                paramsBody.append('<tr><td colspan="4" class="text-center text-muted">No requiere parámetros adicionales.</td></tr>');
            }

            // Body Example
            if (api.request_body) {
                $('#detRequestBodyJSON').text(JSON.stringify(api.request_body, null, 4));
                $('#detRequestBodySection').removeClass('d-none');
            } else {
                $('#detRequestBodySection').addClass('d-none');
            }
            
            // Adjust tenant header
            if (api.uri.includes('tenant') || api.uri.includes('item')) {
                $('#tenantHeaderRow').removeClass('d-none');
            } else {
                $('#tenantHeaderRow').addClass('d-none');
            }
        });

        // Load in Tester Button
        $('#btnLoadInTester').on('click', function() {
            const activeItem = $('.api-item.active');
            if (activeItem.length === 0) return;

            const index = activeItem.data('index');
            const api = apis[index];

            // Prefill Tester
            $('#reqMethod').val(api.method);
            
            // Build full URL and replace path placeholders
            const host = $('#testHost').val().trim();
            let uri = api.uri;
            
            // Replace standard path parameter placeholders
            const userId = credentials.id || '1252367';
            
            // Build map of path param replacements
            const pathReplacements = {
                'id': userId,
                'user_id': userId,
                'Id': userId,
                'ItemId': '1',
                'itemId': '1',
                'id_item': '1',
                'company_id': '1',
                'companyId': '1',
                'childId': '2',
                'relatedItemId': '2',
                'relatedItemType': '1',
                'itemType': '1',
                'categoryId': '1941',
                'serviceId': '1',
                'fieldType': '1',
                'type': '1'
            };
            
            // Perform path parameter replacements
            Object.keys(pathReplacements).forEach(key => {
                const regex = new RegExp('\\{' + key + '\\}', 'gi');
                uri = uri.replace(regex, pathReplacements[key]);
            });
            
            // Build Query Parameters for GET requests
            let queryParams = [];
            if (api.method.toUpperCase() === 'GET' && api.params && api.params.length > 0) {
                api.params.forEach(p => {
                    // Check if this parameter is NOT in the path (i.e. it is a query parameter)
                    const isPathParam = api.uri.match(new RegExp('\\{' + p.name + '\\}', 'i'));
                    if (!isPathParam) {
                        let val = '';
                        if (p.name.toLowerCase() === 'projectid') {
                            val = '2'; // Default project ID for EP Petroecuador (Mg==)
                        } else if (p.name.toLowerCase() === 'itemtype') {
                            val = 'specialist'; // Default for searchAll/etc.
                        } else if (p.name.toLowerCase() === 'active') {
                            val = 'true';
                        } else {
                            val = '1'; // Default fallback
                        }
                        queryParams.push(encodeURIComponent(p.name) + '=' + encodeURIComponent(val));
                    }
                });
            }
            
            // Determine base path (Petroecuador uses direct /api/v9/ through APIM gateway)
            const basePath = host.includes('eppetroecuador.ec') ? '' : '/ASMSAPI';
            let fullUrl = host + basePath + uri;
            if (queryParams.length > 0) {
                fullUrl += '?' + queryParams.join('&');
            }

            $('#reqUrl').val(fullUrl);


            // Prefill Body
            if (api.request_body) {
                let bodyStr = JSON.stringify(api.request_body, null, 4);
                // Substitute hardcoded credential user id in template with dynamic id
                bodyStr = bodyStr.replace(/1252367/g, userId);
                $('#reqBody').val(bodyStr);
                $('#reqBodyGroup').removeClass('d-none');
            } else {
                $('#reqBody').val('');
                $('#reqBodyGroup').addClass('d-none');
            }

            // Switch Tab to Tester
            $('#tests-tab').tab('show');

            // Automatically execute request after short layout transition delay
            setTimeout(function() {
                $('#btnSendRequest').trigger('click');
            }, 350);
        });

        // Update Base Path label dynamically when Host changes
        $('#testHost').on('input', function() {
            const host = $(this).val().trim();
            const basePath = host.includes('eppetroecuador.ec') ? '/' : '/ASMSAPI';
            $('#basePathLabel').text(basePath);
        });



        // Watch method selector in tester to show/hide body textarea
        $('#reqMethod').on('change', function() {
            const m = $(this).val();
            if (m === 'POST' || m === 'PUT') {
                $('#reqBodyGroup').removeClass('d-none');
            } else {
                $('#reqBodyGroup').addClass('d-none');
            }
        });

        // Send Request to Proxy
        $('#btnSendRequest').on('click', function() {
            const method = $('#reqMethod').val();
            const url = $('#reqUrl').val().trim();
            const token = $('#testToken').val().trim();
            const body = $('#reqBody').val().trim();

            $('#respInitial').addClass('d-none');
            $('#respResult').addClass('d-none');
            $('#respLoading').removeClass('d-none');
            $('#respStatus').addClass('d-none');

            // Send ajax to proxy.php
            $.ajax({
                url: 'api_aranda_proxy.php',
                method: 'POST',
                data: JSON.stringify({
                    method: method,
                    url: url,
                    token: token,
                    tenant: $('#testTenant').val().trim(),
                    body: body
                }),
                contentType: 'application/json',
                success: function(res) {
                    $('#respLoading').addClass('d-none');
                    $('#respResult').removeClass('d-none');

                    // Status Badge color
                    const statusBadge = $('#respStatus');
                    statusBadge.removeClass('d-none').text(res.status_code);
                    if (res.status_code >= 200 && res.status_code < 300) {
                        statusBadge.attr('class', 'badge p-2 badge-success').text('HTTP ' + res.status_code + ' OK');
                    } else {
                        statusBadge.attr('class', 'badge p-2 badge-danger').text('HTTP ' + res.status_code + ' Error');
                    }

                    if (res.success) {
                        let responseText = typeof res.response === 'object' ? JSON.stringify(res.response, null, 4) : res.raw_response;
                        
                        // If it's a 500 error on Petroecuador and the token has the QA issuer, append a helpful diagnostic tip
                        if (res.status_code === 500 && url.includes('eppetroecuador.ec')) {
                            const payload = parseJwt(token);
                            if (payload && payload.IssuerIdentifier && payload.IssuerIdentifier.includes('asms.arandasoft.com')) {
                                responseText += '\n\n--------------------------------------------------\n';
                                responseText += '⚠️ SUGERENCIA DE DIAGNÓSTICO:\n';
                                responseText += 'El servidor de EP Petroecuador retornó un error 500 (Internal Server Error).\n';
                                responseText += 'Esto ocurre porque estás usando el token de pruebas de Sonda (asms.arandasoft.com).\n';
                                responseText += 'Por favor, copia tu Token de Integración de Petroecuador de tu sesión activa\n';
                                responseText += 'y pégalo en el campo "Token de Integración (JWT)" a la izquierda.';
                            }
                        }
                        $('#respBody').text(responseText);
                    } else {
                        $('#respBody').text('Error en la comunicación:\n' + res.error);
                    }
                },
                error: function(xhr) {
                    $('#respLoading').addClass('d-none');
                    $('#respResult').removeClass('d-none');
                    $('#respStatus').attr('class', 'badge p-2 badge-danger').removeClass('d-none').text('Error de Red');
                    $('#respBody').text('Error HTTP al invocar el proxy local.');
                }
            });
        });
    });

    function copyResponse() {
        const text = $('#respBody').text();
        navigator.clipboard.writeText(text);
        toastr.success('Copiado al portapapeles');
    }
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>

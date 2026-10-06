<?php
/**
 * Gestión de Usuarios y Permisos - CMDB VILASECA
 * Control unificado de accesos basado en las pestañas y módulos del Menú SYNAPSE
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/permissions_helper.php';

require_login();
if (!has_role(['SUPER_ADMIN']) && !has_module_access('user_management')) {
    header("Location: dashboard.php");
    exit();
}

$page_title = "Gestión de Usuarios y Permisos";
$page_icon = "fas fa-user-shield text-danger";
include 'partials/header.php';

$pdo = getPDO();
$roles = $pdo->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$all_sheets = listSheetTables();

// Mapeo detallado de hojas de PRECMDB con nombres legibles e iconos
$sheet_labels = [
    'sheet_routers'     => ['label' => 'Routers', 'icon' => 'fas fa-route text-primary'],
    'sheet_switches'    => ['label' => 'Switches', 'icon' => 'fas fa-network-wired text-info'],
    'sheet_aps'         => ['label' => 'Access Points (APs)', 'icon' => 'fas fa-wifi text-warning'],
    'sheet_laptops'     => ['label' => 'Laptops / Equipos', 'icon' => 'fas fa-laptop text-success'],
    'sheet_servers'     => ['label' => 'Servidores Físicos', 'icon' => 'fas fa-server text-indigo'],
    'sheet_datastores'  => ['label' => 'Datastores / Storage', 'icon' => 'fas fa-hdd text-cyan'],
    'sheet_vms'         => ['label' => 'Máquinas Virtuales (VMs)', 'icon' => 'fas fa-cloud text-purple'],
    'sheet_pasivos'     => ['label' => 'Equipos Pasivos / Patch', 'icon' => 'fas fa-plug text-orange'],
    'sheet_equipos'     => ['label' => 'Equipos Generales', 'icon' => 'fas fa-desktop text-secondary'],
    'sheet_firewall'    => ['label' => 'Firewalls / Seguridad', 'icon' => 'fas fa-shield-alt text-danger'],
    'sheet_ups'         => ['label' => 'Sistemas UPS / Energía', 'icon' => 'fas fa-battery-three-quarters text-warning'],
    'sheet_localidades' => ['label' => 'Localidades / Sedes', 'icon' => 'fas fa-map-marker-alt text-danger'],
    'sheet_servicios'   => ['label' => 'Servicios y Plataformas', 'icon' => 'fas fa-concierge-bell text-info'],
    'sheet_distrib_rack'=> ['label' => 'Distribución de Racks', 'icon' => 'fas fa-th text-teal']
];

$activos_list = ['sheet_routers', 'sheet_switches', 'sheet_aps', 'sheet_laptops', 'sheet_servers', 'sheet_datastores', 'sheet_vms'];
$main_sheets_order = array_merge($activos_list, ['sheet_pasivos']);

$pestañas_principales = [];
foreach ($main_sheets_order as $ms) {
    if (in_array($ms, $all_sheets)) {
        $pestañas_principales[] = $ms;
    }
}
$otras_pestañas = [];
foreach ($all_sheets as $s) {
    if (!in_array($s, $main_sheets_order)) {
        $otras_pestañas[] = $s;
    }
}

// Estructura completa de Módulos y Pestañas del Menú SYNAPSE
$all_modules_categorized = [
    'Pestañas Principales (Top Tabs)' => [
        'femsa' => [
            'label' => 'FEMSA',
            'desc'  => 'Control, Requerimientos & Ciclo de Automatización',
            'icon'  => 'fas fa-building text-danger',
            'badge' => 'Top Tab'
        ],
        'actividades' => [
            'label' => 'ACTIVIDADES',
            'desc'  => 'Control, Tareas y Entrega de Servicios',
            'icon'  => 'fas fa-tasks text-primary',
            'badge' => 'Nuevo'
        ],
        'cmdb_sonda' => [
            'label' => 'CMDB_SONDA',
            'desc'  => 'Gestión de CIs, Topología de Servicios, Dependencias ITIL, Alertas y Evidencias',
            'icon'  => 'fas fa-cubes text-info',
            'badge' => 'Módulo SONDA',
            'subitems' => [
                'Gestión de CIs & Ciclo de Vida',
                'Servicios de Negocio',
                'Topología de Dependencias',
                'Mapa Gráfico Vis.js',
                'Alertas & Monitoreo Zabbix',
                'Fotos y Evidencias'
            ]
        ],
        'vilaseca' => [
            'label' => 'Vilaseca (Operaciones & Levantamientos)',
            'desc'  => 'Habilita acceso completo a sus 4 subpestañas secundarias:',
            'icon'  => 'fas fa-building text-warning',
            'badge' => '4 Subpestañas',
            'subitems' => [
                'Portmapping',
                'Modelos Visio',
                'Análisis Conexiones',
                'Centro de Operaciones'
            ]
        ]
    ],
    'Paneles de Control y Analítica' => [
        'dashboard' => [
            'label' => 'Dashboard General',
            'desc'  => 'Métricas globales, resumen de red y accesos rápidos',
            'icon'  => 'fas fa-tachometer-alt text-info'
        ],
        'novaiops_dashboard' => [
            'label' => 'NovaIOPS Dashboard',
            'desc'  => 'Distribución de carga laboral, tareas e indicadores BI',
            'icon'  => 'fas fa-chart-pie text-cyan'
        ]
    ],
    'CMDB, Arquitectura e Infraestructura' => [
        'ci_list' => [
            'label' => 'CMDB (Gestión de CIs)',
            'desc'  => 'Inventario relacional de CIs y mapa de dependencias',
            'icon'  => 'fas fa-project-diagram text-indigo'
        ],
        'datacenter' => [
            'label' => 'Datacenter (DCIM)',
            'desc'  => 'Habilita acceso completo a sus 3 subpestañas:',
            'icon'  => 'fas fa-server text-success',
            'badge' => '3 Subpestañas',
            'subitems' => [
                'Cuartos / Rooms',
                'Racks (Gabinetes)',
                'Análisis Datacenter'
            ]
        ],
        'gitlab' => [
            'label' => 'GitLab Workspace',
            'desc'  => 'Control de versiones, repositorios y diffs de código',
            'icon'  => 'fab fa-gitlab text-orange'
        ],
        'aranda' => [
            'label' => 'Aranda Service Desk',
            'desc'  => 'Integración API, incidentes y requerimientos',
            'icon'  => 'fas fa-ticket-alt text-navy'
        ]
    ],
    'Monitoreo y Redes' => [
        'monitoreo' => [
            'label' => 'Zabbix Monitoreo',
            'desc'  => 'Habilita acceso a todas las subpestañas de Zabbix:',
            'icon'  => 'fas fa-desktop text-danger',
            'badge' => 'Suite Zabbix',
            'subitems' => [
                'Kanban Zabbix',
                'Dashboard',
                'Equipos',
                'Informes',
                'Gestión Interfaces',
                'Costos ZBX',
                'Storage',
                'Asistentes'
            ]
        ],
        'snmp' => [
            'label' => 'Módulo SNMP',
            'desc'  => 'Habilita acceso a herramientas de escaneo y MIBs:',
            'icon'  => 'fas fa-network-wired text-purple',
            'subitems' => [
                'Gestión / Escaneo',
                'SNMP Builder',
                'Repositorio MIBs',
                'Análisis MIB'
            ]
        ]
    ],
    'Gestión, Servicios y Clientes' => [
        'project' => [
            'label' => 'Gestión de Proyectos',
            'desc'  => 'Planificación de proyectos, hitos, tareas y cronograma',
            'icon'  => 'fas fa-tasks text-blue'
        ],
        'password' => [
            'label' => 'PASSWORD (Bóveda)',
            'desc'  => 'Bóveda centralizada y segura de contraseñas y accesos',
            'icon'  => 'fas fa-key text-warning'
        ],
        'cotizador' => [
            'label' => 'Cotizador de Servicios',
            'desc'  => 'Habilita acceso a las 3 subpestañas del cotizador:',
            'icon'  => 'fas fa-calculator text-info',
            'subitems' => [
                'Configurador',
                'Diseño Cotización',
                'Historial'
            ]
        ],
        'clientes' => [
            'label' => 'CLIENTES (Multisede)',
            'desc'  => 'Habilita acceso a portales dedicados por cliente:',
            'icon'  => 'fas fa-users text-primary',
            'subitems' => [
                'SONDA (iin)',
                'GPF',
                'Vilaseca'
            ]
        ]
    ],
    'Diagramas, Topologías y Levantamientos' => [
        'diagrams' => [
            'label' => 'Diagramas y Procesos',
            'desc'  => 'Habilita visualización y edición de diagramas:',
            'icon'  => 'fas fa-project-diagram text-success',
            'subitems' => [
                'Flujos Mermaid',
                'Procesos BPMN',
                'Modelos Visio (VSDX)'
            ]
        ],
        'topology' => [
            'label' => 'Topología de Red',
            'desc'  => 'Visualizador interactivo de red en 2D y 3D',
            'icon'  => 'fas fa-sitemap text-primary'
        ],
        'portmapping' => [
            'label' => 'Portmapping Wizard',
            'desc'  => 'Mapeo paso a paso de puertos físicos y conectividad',
            'icon'  => 'fas fa-ethernet text-danger'
        ]
    ],
    'Datos, Reportes e Imágenes' => [
        'import' => [
            'label' => 'Importar Excel',
            'desc'  => 'Carga masiva de inventario y datos mediante plantillas',
            'icon'  => 'fas fa-file-excel text-success'
        ],
        'reports' => [
            'label' => 'Informes y Reportes',
            'desc'  => 'Generación y repositorio de informes de gestión técnica',
            'icon'  => 'fas fa-file-invoice text-teal'
        ],
        'log_analysis' => [
            'label' => 'Análisis de Logs',
            'desc'  => 'Visor y auditoría de eventos de sistema y errores',
            'icon'  => 'fas fa-terminal text-dark'
        ],
        'distribrack' => [
            'label' => 'Galería de Imágenes',
            'desc'  => 'Distribrack y fotos de racks y salas de telecomunicaciones',
            'icon'  => 'fas fa-images text-secondary'
        ]
    ],
    'Administración del Sistema' => [
        'user_management' => [
            'label' => 'Gestión de Usuarios y Permisos',
            'desc'  => 'Creación de usuarios, asignación de roles y permisos',
            'icon'  => 'fas fa-user-shield text-danger'
        ],
        'ci_admin' => [
            'label' => 'CMDB Admin',
            'desc'  => 'Categorías, Atributos Globales, Relaciones y CI Builder',
            'icon'  => 'fas fa-layer-group text-primary'
        ],
        'sheet_configs' => [
            'label' => 'Configuración de Claves',
            'desc'  => 'Configuración de claves únicas y vinculación de hojas',
            'icon'  => 'fas fa-cogs text-info'
        ],
        'system_health' => [
            'label' => 'Salud del Sistema',
            'desc'  => 'Diagnóstico técnico, verificación y respaldo de plataforma',
            'icon'  => 'fas fa-heartbeat text-danger'
        ],
        'activated_modules' => [
            'label' => 'Módulos Activados',
            'desc'  => 'Interruptores globales de habilitación/deshabilitación',
            'icon'  => 'fas fa-toggle-on text-warning'
        ]
    ]
];
?>

<style>
.perm-card-category {
    border: 1px solid #e9ecef;
    border-radius: 10px;
    transition: all 0.2s ease;
    background: #ffffff;
}
.dark-mode .perm-card-category {
    background: #343a40;
    border-color: #4b545c;
}
.perm-item-box {
    padding: 10px 12px;
    border-radius: 8px;
    background: #f8f9fa;
    border: 1px solid #edf2f7;
    margin-bottom: 8px;
    transition: all 0.15s ease;
}
.perm-item-box:hover {
    background: #eef4fc;
    border-color: #cce0fc;
}
.dark-mode .perm-item-box {
    background: #2b3035;
    border-color: #3f474e;
}
.dark-mode .perm-item-box:hover {
    background: #384047;
    border-color: #4f5a63;
}
.nav-pills .nav-link.active {
    background-color: var(--sonda-cyan, #007bff) !important;
}
</style>

<div class="row">
    <div class="col-12">
        <div class="card card-primary card-outline shadow-sm" style="border-radius: 12px;">
            <div class="card-header d-flex justify-content-between align-items-center py-3">
                <h3 class="card-title font-weight-bold text-navy mb-0">
                    <i class="fas fa-users-cog text-primary mr-2"></i> Usuarios del Sistema y Control de Accesos
                </h3>
                <div class="card-tools ml-auto">
                    <button class="btn btn-success btn-sm px-3 shadow-sm" style="border-radius: 6px;" onclick="showCreateModal()">
                        <i class="fas fa-user-plus mr-1"></i> Nuevo Usuario
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover table-striped mb-0" id="users-table">
                    <thead class="bg-light">
                        <tr>
                            <th style="width: 80px;">ID</th>
                            <th>Usuario</th>
                            <th>Rol del Sistema</th>
                            <th>Fecha de Registro</th>
                            <th class="text-right" style="width: 280px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="users-list">
                        <tr><td colspan="5" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin mr-2"></i> Cargando usuarios...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Crear/Editar Usuario -->
<div class="modal fade" id="userModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold" id="userModalTitle">Usuario</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="userForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="id" id="userId">
                    <div class="form-group mb-3">
                        <label class="font-weight-bold">Nombre de Usuario</label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-user"></i></span></div>
                            <input type="text" name="username" id="userName" class="form-control" required placeholder="ej: usuario.apellido">
                        </div>
                    </div>
                    <div id="passwordSection" class="form-group mb-3">
                        <label class="font-weight-bold">Contraseña</label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-lock"></i></span></div>
                            <input type="password" name="password" id="userPass" class="form-control" placeholder="••••••••">
                        </div>
                        <small class="text-muted" id="passHelp">Mínimo 6 caracteres.</small>
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-bold">Rol en el Sistema</label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-user-tag"></i></span></div>
                            <select name="role_id" id="userRoleId" class="form-control">
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3 font-weight-bold">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Gestión de Permisos Basado en Pestañas del Menú -->
<div class="modal fade" id="permsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content" style="border-radius: 14px; overflow: hidden; box-shadow: 0 15px 35px rgba(0,0,0,0.2);">
            <div class="modal-header bg-navy text-white py-3">
                <div class="d-flex align-items-center">
                    <i class="fas fa-shield-alt fa-lg text-cyan mr-3"></i>
                    <div>
                        <h5 class="modal-title font-weight-bold mb-0">
                            Gestión de Permisos: <span id="permsUser" class="text-warning"></span>
                        </h5>
                        <small class="text-white-50">Control granular de acceso por pestañas del menú y hojas de la CMDB</small>
                    </div>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            
            <div class="modal-body p-3">
                <input type="hidden" id="permsUserId">

                <!-- Navegación por Pestañas del Modal -->
                <ul class="nav nav-pills mb-3 border-bottom pb-2" id="permsNavTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active font-weight-bold py-2 px-3" id="tab-modules-btn" data-toggle="pill" href="#tab-modules-content" role="tab">
                            <i class="fas fa-th-large mr-2"></i> Pestañas del Menú SYNAPSE
                            <span class="badge badge-light ml-2 border" id="badge-count-modules">0 / <?php echo array_sum(array_map('count', $all_modules_categorized)); ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link font-weight-bold py-2 px-3" id="tab-sheets-btn" data-toggle="pill" href="#tab-sheets-content" role="tab">
                            <i class="fas fa-table mr-2"></i> PRECMDB - Hojas de Datos (Excel)
                            <span class="badge badge-light ml-2 border" id="badge-count-sheets">0 / <?php echo count($all_sheets); ?></span>
                        </a>
                    </li>
                </ul>

                <div class="tab-content" id="permsTabContent">
                    <!-- PESTAÑA 1: Módulos y Pestañas del Menú Lateral -->
                    <div class="tab-pane fade show active" id="tab-modules-content" role="tabpanel">
                        <!-- Barra de Herramientas de Módulos -->
                        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 bg-light p-2 rounded border">
                            <div class="d-flex align-items-center mb-2 mb-md-0" style="flex: 1; max-width: 400px;">
                                <div class="input-group input-group-sm">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-white border-right-0"><i class="fas fa-search text-muted"></i></span>
                                    </div>
                                    <input type="text" id="filter-modules-input" class="form-control border-left-0" placeholder="Buscar pestaña o módulo...">
                                </div>
                            </div>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-success font-weight-bold" onclick="bulkToggleModules(true)">
                                    <i class="fas fa-check-double mr-1"></i> Marcar Todo el Menú
                                </button>
                                <button type="button" class="btn btn-outline-secondary font-weight-bold" onclick="bulkToggleModules(false)">
                                    <i class="fas fa-times mr-1"></i> Desmarcar Todo
                                </button>
                            </div>
                        </div>

                        <!-- Alerta informativa de Herencia de Subpestañas -->
                        <div class="alert alert-info py-2 px-3 mb-3 small d-flex align-items-center" style="border-radius: 8px; background-color: #e8f4fd; border-color: #b8daff; color: #0c5460;">
                            <i class="fas fa-info-circle fa-lg mr-2 text-primary"></i>
                            <div>
                                <strong>Herencia Automática de Subpestañas:</strong> Al otorgar permiso a una pestaña principal (como <strong>Vilaseca</strong>, <strong>Datacenter</strong>, <strong>Zabbix</strong> o <strong>Cotizador</strong>), el usuario recibe automáticamente acceso completo a todas sus subpestañas secundarias y funcionalidades asociadas.
                            </div>
                        </div>

                        <!-- Tarjetas de Categorías del Menú -->
                        <div id="modules-cards-container">
                            <?php $cat_idx = 0; ?>
                            <?php foreach ($all_modules_categorized as $cat_title => $modules): ?>
                            <?php $cat_idx++; ?>
                            <div class="card card-outline card-secondary mb-3 shadow-none perm-card-category" data-cat="<?php echo htmlspecialchars($cat_title); ?>">
                                <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center" style="border-top-left-radius: 10px; border-top-right-radius: 10px;">
                                    <h6 class="card-title font-weight-bold text-navy mb-0" style="font-size: 0.9rem;">
                                        <i class="fas fa-folder-open text-primary mr-2"></i> <?php echo $cat_title; ?>
                                        <span class="badge badge-secondary ml-2 font-weight-normal"><?php echo count($modules); ?> items</span>
                                    </h6>
                                    <div class="card-tools">
                                        <button type="button" class="btn btn-xs btn-outline-primary" onclick="toggleCategoryModules('cat_<?php echo $cat_idx; ?>', true)" title="Activar todos en este grupo">
                                            <i class="fas fa-check"></i> Activar
                                        </button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary ml-1" onclick="toggleCategoryModules('cat_<?php echo $cat_idx; ?>', false)" title="Desactivar todos en este grupo">
                                            <i class="fas fa-times"></i> Desactivar
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body py-2 px-3 cat-body-<?php echo $cat_idx; ?>">
                                    <div class="row">
                                        <?php foreach ($modules as $key => $info): ?>
                                        <div class="col-lg-6 col-md-12 module-item-wrapper" data-name="<?php echo strtolower($info['label'] . ' ' . ($info['desc'] ?? '') . ' ' . (isset($info['subitems']) ? implode(' ', $info['subitems']) : '') . ' ' . $key); ?>">
                                            <div class="perm-item-box d-flex flex-column justify-content-between" style="min-height: 72px;">
                                                <div class="d-flex align-items-center justify-content-between">
                                                    <div class="d-flex align-items-center mr-2" style="min-width: 0;">
                                                        <div style="width: 34px; height: 34px; border-radius: 6px; background: rgba(0,0,0,0.04); display: flex; align-items: center; justify-content: center; margin-right: 10px; flex-shrink: 0;">
                                                            <i class="<?php echo $info['icon']; ?>" style="font-size: 1.1rem;"></i>
                                                        </div>
                                                        <div style="min-width: 0;">
                                                            <label class="font-weight-bold mb-0 text-truncate d-block" for="mod_<?php echo $key; ?>" style="cursor: pointer; font-size: 0.88rem;">
                                                                <?php echo htmlspecialchars($info['label']); ?>
                                                                <?php if (!empty($info['badge'])): ?>
                                                                    <span class="badge badge-info ml-1" style="font-size: 0.65rem;"><?php echo htmlspecialchars($info['badge']); ?></span>
                                                                <?php endif; ?>
                                                            </label>
                                                            <?php if (!empty($info['desc'])): ?>
                                                                <small class="text-muted d-block text-truncate" style="font-size: 0.76rem;"><?php echo htmlspecialchars($info['desc']); ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div class="custom-control custom-switch custom-switch-md flex-shrink-0">
                                                        <input type="checkbox" class="custom-control-input module-perm cat-<?php echo $cat_idx; ?> cat_<?php echo $cat_idx; ?>" id="mod_<?php echo $key; ?>" data-module="<?php echo $key; ?>" onchange="updateModulesCount()">
                                                        <label class="custom-control-label" for="mod_<?php echo $key; ?>" style="cursor: pointer;"></label>
                                                    </div>
                                                </div>
                                                <?php if (!empty($info['subitems'])): ?>
                                                <div class="mt-2 pt-2 border-top d-flex flex-wrap align-items-center" style="border-color: rgba(0,0,0,0.06) !important;">
                                                    <span class="text-muted mr-1 font-italic" style="font-size: 0.7rem;"><i class="fas fa-level-down-alt fa-rotate-270 mr-1 text-primary"></i>Subpestañas:</span>
                                                    <?php foreach ($info['subitems'] as $sub): ?>
                                                        <span class="badge badge-light border text-navy mr-1 mb-1" style="font-size: 0.68rem; font-weight: 500;">
                                                            <i class="far fa-circle text-primary mr-1" style="font-size: 0.5rem;"></i><?php echo htmlspecialchars($sub); ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- PESTAÑA 2: PRECMDB (Hojas de Datos Excel) -->
                    <div class="tab-pane fade" id="tab-sheets-content" role="tabpanel">
                        <!-- Barra de Herramientas de Sheets -->
                        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 bg-light p-2 rounded border">
                            <div class="d-flex align-items-center mb-2 mb-md-0" style="flex: 1; max-width: 360px;">
                                <div class="input-group input-group-sm">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-white border-right-0"><i class="fas fa-search text-muted"></i></span>
                                    </div>
                                    <input type="text" id="filter-sheets-input" class="form-control border-left-0" placeholder="Buscar hoja de datos...">
                                </div>
                            </div>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-primary" onclick="bulkToggleSheets('view', true)">
                                    <i class="fas fa-eye mr-1"></i> Ver Todos
                                </button>
                                <button type="button" class="btn btn-outline-info" onclick="bulkToggleSheets('edit', true)">
                                    <i class="fas fa-edit mr-1"></i> Editar Todos
                                </button>
                                <button type="button" class="btn btn-outline-warning" onclick="bulkToggleSheets('delete', true)">
                                    <i class="fas fa-trash mr-1"></i> Eliminar Todos
                                </button>
                                <button type="button" class="btn btn-outline-success font-weight-bold" onclick="bulkToggleSheets('all', true)">
                                    <i class="fas fa-check-double mr-1"></i> Acceso Total
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="bulkToggleSheets('all', false)">
                                    <i class="fas fa-times mr-1"></i> Desmarcar Todo
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive border rounded" style="max-height: 520px; overflow-y: auto;">
                            <table class="table table-sm table-hover mb-0" id="sheets-table">
                                <thead class="bg-light text-navy sticky-top" style="z-index: 10;">
                                    <tr>
                                        <th>Hoja de Datos (Tabla Técnica)</th>
                                        <th class="text-center" style="width: 110px;">
                                            <i class="fas fa-eye text-primary mr-1"></i> Ver
                                        </th>
                                        <th class="text-center" style="width: 110px;">
                                            <i class="fas fa-edit text-info mr-1"></i> Editar
                                        </th>
                                        <th class="text-center" style="width: 110px;">
                                            <i class="fas fa-trash text-danger mr-1"></i> Eliminar
                                        </th>
                                        <th class="text-center" style="width: 100px;">Acción Rápida</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Pestañas Principales (Activos y Pasivos) -->
                                    <tr class="bg-light text-primary font-weight-bold sheet-header-row">
                                        <td colspan="5" style="background-color: #ebf3fa !important;">
                                            <i class="fas fa-star text-warning mr-2"></i> Pestañas Principales de PRECMDB (Activos & Pasivos)
                                        </td>
                                    </tr>
                                    <?php foreach ($pestañas_principales as $s): ?>
                                    <?php 
                                        $info = $sheet_labels[$s] ?? [
                                            'label' => ucfirst(str_replace(['sheet_', '_'], ['', ' '], $s)),
                                            'icon'  => 'fas fa-cube text-primary'
                                        ];
                                    ?>
                                    <tr class="sheet-row" data-sheet-name="<?php echo strtolower($info['label'] . ' ' . $s); ?>">
                                        <td class="align-middle">
                                            <div class="d-flex align-items-center">
                                                <i class="<?php echo $info['icon']; ?> mr-2" style="width: 20px; text-align: center;"></i>
                                                <div>
                                                    <span class="font-weight-bold text-navy"><?php echo htmlspecialchars($info['label']); ?></span>
                                                    <small class="text-muted d-block" style="font-size: 0.72rem;"><code><?php echo $s; ?></code></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center align-middle">
                                            <input type="checkbox" class="sheet-perm-view" data-sheet="<?php echo $s; ?>" onchange="updateSheetsCount()">
                                        </td>
                                        <td class="text-center align-middle">
                                            <input type="checkbox" class="sheet-perm-edit" data-sheet="<?php echo $s; ?>" onchange="updateSheetsCount()">
                                        </td>
                                        <td class="text-center align-middle">
                                            <input type="checkbox" class="sheet-perm-delete" data-sheet="<?php echo $s; ?>" onchange="updateSheetsCount()">
                                        </td>
                                        <td class="text-center align-middle">
                                            <button type="button" class="btn btn-xs btn-outline-secondary" onclick="toggleSingleSheetRow('<?php echo $s; ?>')">
                                                Invertir
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>

                                    <!-- Otras Pestañas / Tablas de CMDB -->
                                    <tr class="bg-light text-muted font-weight-bold sheet-header-row">
                                        <td colspan="5" style="background-color: #f7f9fa !important;">
                                            <i class="fas fa-list text-secondary mr-2"></i> Otras Tablas / Pestañas de CMDB
                                        </td>
                                    </tr>
                                    <?php foreach ($otras_pestañas as $s): ?>
                                    <?php 
                                        $info = $sheet_labels[$s] ?? [
                                            'label' => ucfirst(str_replace(['sheet_', '_'], ['', ' '], $s)),
                                            'icon'  => 'fas fa-table text-secondary'
                                        ];
                                    ?>
                                    <tr class="sheet-row" data-sheet-name="<?php echo strtolower($info['label'] . ' ' . $s); ?>">
                                        <td class="align-middle">
                                            <div class="d-flex align-items-center">
                                                <i class="<?php echo $info['icon']; ?> mr-2" style="width: 20px; text-align: center;"></i>
                                                <div>
                                                    <span class="font-weight-bold text-dark"><?php echo htmlspecialchars($info['label']); ?></span>
                                                    <small class="text-muted d-block" style="font-size: 0.72rem;"><code><?php echo $s; ?></code></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center align-middle">
                                            <input type="checkbox" class="sheet-perm-view" data-sheet="<?php echo $s; ?>" onchange="updateSheetsCount()">
                                        </td>
                                        <td class="text-center align-middle">
                                            <input type="checkbox" class="sheet-perm-edit" data-sheet="<?php echo $s; ?>" onchange="updateSheetsCount()">
                                        </td>
                                        <td class="text-center align-middle">
                                            <input type="checkbox" class="sheet-perm-delete" data-sheet="<?php echo $s; ?>" onchange="updateSheetsCount()">
                                        </td>
                                        <td class="text-center align-middle">
                                            <button type="button" class="btn btn-xs btn-outline-secondary" onclick="toggleSingleSheetRow('<?php echo $s; ?>')">
                                                Invertir
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-light d-flex justify-content-between py-2">
                <div class="text-muted small">
                    <i class="fas fa-info-circle text-info mr-1"></i> Los cambios se aplicarán inmediatamente tras guardar.
                </div>
                <div>
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-info btn-sm px-4 font-weight-bold" id="btn-save-perms" onclick="savePermissions()">
                        <i class="fas fa-save mr-1"></i> Aplicar Permisos
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'partials/footer.php'; ?>

<script>
$(function() {
    loadUsers();

    $('#userForm').on('submit', function(e) {
        e.preventDefault();
        const id = $('#userId').val();
        const action = id ? 'update_user' : 'create_user';
        const data = $(this).serialize() + '&action=' + action;

        $.post('api_users.php', data, function(res) {
            if (res.success) {
                Swal.fire({
                    icon: 'success',
                    title: id ? 'Usuario actualizado' : 'Usuario creado',
                    text: 'Los datos del usuario se guardaron con éxito',
                    timer: 1800,
                    showConfirmButton: false
                });
                $('#userModal').modal('hide');
                loadUsers();
            } else {
                Swal.fire('Error', res.error || 'No se pudo procesar la solicitud', 'error');
            }
        });
    });

    // Filtro en tiempo real para Pestañas del Menú
    $('#filter-modules-input').on('keyup', function() {
        const val = $(this).val().toLowerCase().trim();
        $('.module-item-wrapper').each(function() {
            const name = $(this).data('name') || '';
            if (name.indexOf(val) !== -1) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });

        // Ocultar categorías vacías si no hay coincidencias
        $('.perm-card-category').each(function() {
            const visibleItems = $(this).find('.module-item-wrapper:visible').length;
            if (visibleItems === 0 && val !== '') {
                $(this).hide();
            } else {
                $(this).show();
            }
        });
    });

    // Filtro en tiempo real para Hojas de Datos
    $('#filter-sheets-input').on('keyup', function() {
        const val = $(this).val().toLowerCase().trim();
        $('.sheet-row').each(function() {
            const name = $(this).data('sheet-name') || '';
            if (name.indexOf(val) !== -1) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
});

function loadUsers() {
    $.post('api_users.php', { action: 'list_users' }, function(res) {
        if (res.success) {
            let html = '';
            res.users.forEach(u => {
                html += `
                <tr>
                    <td class="align-middle text-muted font-weight-bold">${u.id}</td>
                    <td class="align-middle">
                        <div class="d-flex align-items-center">
                            <div class="mr-2" style="width: 30px; height: 30px; border-radius: 50%; background: #e9ecef; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-user text-secondary"></i>
                            </div>
                            <strong>${escapeHtml(u.username)}</strong>
                        </div>
                    </td>
                    <td class="align-middle">
                        <span class="badge ${u.role === 'SUPER_ADMIN' ? 'badge-danger' : (u.role === 'ADMIN' ? 'badge-primary' : 'badge-info')} px-2 py-1">
                            ${escapeHtml(u.role)}
                        </span>
                    </td>
                    <td class="align-middle text-muted">${u.created_at || 'N/A'}</td>
                    <td class="text-right align-middle">
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-info font-weight-bold shadow-sm" onclick="showPermsModal(${u.id}, '${escapeJs(u.username)}')" title="Gestionar Permisos de Menú y CMDB">
                                <i class="fas fa-shield-alt mr-1"></i> Permisos
                            </button>
                            <button class="btn btn-sm btn-outline-primary" onclick="showEditModal(${u.id}, '${escapeJs(u.username)}', ${u.role_id})" title="Editar Rol">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-warning" onclick="resetPassword(${u.id})" title="Restablecer Contraseña">
                                <i class="fas fa-key"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(${u.id})" title="Eliminar Usuario">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>`;
            });
            $('#users-list').html(html);
        } else {
            $('#users-list').html(`<tr><td colspan="5" class="text-center py-4 text-danger">${res.error || 'Error al cargar usuarios'}</td></tr>`);
        }
    });
}

function showCreateModal() {
    $('#userModalTitle').text('Nuevo Usuario');
    $('#userId').val('');
    $('#userName').val('').prop('readonly', false);
    $('#userPass').val('').prop('required', true);
    $('#passwordSection').show();
    $('#userModal').modal('show');
}

function showEditModal(id, name, roleId) {
    $('#userModalTitle').text('Editar Usuario');
    $('#userId').val(id);
    $('#userName').val(name).prop('readonly', true);
    $('#userPass').val('').prop('required', false);
    $('#passwordSection').hide();
    $('#userRoleId').val(roleId);
    $('#userModal').modal('show');
}

function deleteUser(id) {
    Swal.fire({
        title: '¿Confirmar eliminación?',
        text: "Esta acción borrará al usuario y todos sus permisos permanentemente.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('api_users.php', { action: 'delete_user', id: id }, function(res) {
                if (res.success) {
                    Swal.fire('Eliminado', 'Usuario borrado con éxito', 'success');
                    loadUsers();
                } else {
                    Swal.fire('Error', res.error, 'error');
                }
            });
        }
    });
}

function resetPassword(id) {
    Swal.fire({
        title: 'Nueva Contraseña',
        input: 'password',
        inputLabel: 'Introduce la nueva contraseña para el usuario',
        inputAttributes: {
            autocapitalize: 'off',
            autocorrect: 'off'
        },
        showCancelButton: true,
        confirmButtonText: 'Actualizar Contraseña',
        cancelButtonText: 'Cancelar',
        showLoaderOnConfirm: true,
        preConfirm: (pass) => {
            if (!pass || pass.length < 4) {
                Swal.showValidationMessage('La contraseña debe tener al menos 4 caracteres');
                return false;
            }
            return $.post('api_users.php', { action: 'reset_password', id: id, password: pass });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result && result.value && result.value.success) {
            Swal.fire('¡Éxito!', 'Contraseña actualizada correctamente', 'success');
        } else if (result && result.value) {
            Swal.fire('Error', result.value.error || 'No se pudo actualizar', 'error');
        }
    });
}

// Lógica de Gestión de Permisos
function showPermsModal(id, name) {
    $('#permsUserId').val(id);
    $('#permsUser').text(name);
    
    // Limpiar campos de búsqueda y filtros
    $('#filter-modules-input, #filter-sheets-input').val('').trigger('keyup');

    // Resetear checkboxes
    $('.module-perm, .sheet-perm-view, .sheet-perm-edit, .sheet-perm-delete').prop('checked', false);

    $.post('api_users.php', { action: 'get_permissions', user_id: id }, function(res) {
        if (res.success) {
            // Aplicar permisos de módulos
            if (res.modules && Array.isArray(res.modules)) {
                res.modules.forEach(m => {
                    const cb = $(`#mod_${m.module_name}`);
                    if (cb.length) {
                        cb.prop('checked', parseInt(m.can_view) === 1);
                    }
                });
            }

            // Aplicar permisos de hojas
            if (res.sheets && Array.isArray(res.sheets)) {
                res.sheets.forEach(s => {
                    $(`.sheet-perm-view[data-sheet="${s.sheet_name}"]`).prop('checked', parseInt(s.can_view) === 1);
                    $(`.sheet-perm-edit[data-sheet="${s.sheet_name}"]`).prop('checked', parseInt(s.can_edit) === 1);
                    $(`.sheet-perm-delete[data-sheet="${s.sheet_name}"]`).prop('checked', parseInt(s.can_delete) === 1);
                });
            }

            updateModulesCount();
            updateSheetsCount();
            $('#permsModal').modal('show');
        } else {
            Swal.fire('Error', res.error || 'No se pudieron cargar los permisos', 'error');
        }
    });
}

function updateModulesCount() {
    const total = $('.module-perm').length;
    const active = $('.module-perm:checked').length;
    $('#badge-count-modules').text(`${active} / ${total}`);
}

function updateSheetsCount() {
    const total = [...new Set($('.sheet-perm-view').map(function() { return $(this).data('sheet'); }).get())].length;
    let active = 0;
    const sheetNames = [...new Set($('.sheet-perm-view').map(function() { return $(this).data('sheet'); }).get())];
    sheetNames.forEach(name => {
        const v = $(`.sheet-perm-view[data-sheet="${name}"]`).is(':checked');
        const e = $(`.sheet-perm-edit[data-sheet="${name}"]`).is(':checked');
        const d = $(`.sheet-perm-delete[data-sheet="${name}"]`).is(':checked');
        if (v || e || d) active++;
    });
    $('#badge-count-sheets').text(`${active} / ${total}`);
}

function bulkToggleModules(status) {
    $('.module-perm').prop('checked', status);
    updateModulesCount();
}

function toggleCategoryModules(catClass, status) {
    $(`.${catClass}`).prop('checked', status);
    updateModulesCount();
}

function bulkToggleSheets(action, status) {
    if (action === 'view') {
        $('.sheet-perm-view').prop('checked', status);
    } else if (action === 'edit') {
        $('.sheet-perm-edit').prop('checked', status);
    } else if (action === 'delete') {
        $('.sheet-perm-delete').prop('checked', status);
    } else if (action === 'all') {
        $('.sheet-perm-view, .sheet-perm-edit, .sheet-perm-delete').prop('checked', status);
    }
    updateSheetsCount();
}

function toggleSingleSheetRow(sheetName) {
    const v = $(`.sheet-perm-view[data-sheet="${sheetName}"]`);
    const e = $(`.sheet-perm-edit[data-sheet="${sheetName}"]`);
    const d = $(`.sheet-perm-delete[data-sheet="${sheetName}"]`);
    const anyChecked = v.is(':checked') || e.is(':checked') || d.is(':checked');
    v.prop('checked', !anyChecked);
    e.prop('checked', !anyChecked);
    d.prop('checked', !anyChecked);
    updateSheetsCount();
}

function savePermissions() {
    const id = $('#permsUserId').val();
    const modules = [];
    $('.module-perm').each(function() {
        modules.push({
            name: $(this).data('module'),
            view: $(this).is(':checked') ? 1 : 0
        });
    });

    const sheets = [];
    const sheetNames = [...new Set($('.sheet-perm-view').map(function() { return $(this).data('sheet'); }).get())];
    
    sheetNames.forEach(name => {
        sheets.push({
            name: name,
            view: $(`.sheet-perm-view[data-sheet="${name}"]`).is(':checked') ? 1 : 0,
            edit: $(`.sheet-perm-edit[data-sheet="${name}"]`).is(':checked') ? 1 : 0,
            delete: $(`.sheet-perm-delete[data-sheet="${name}"]`).is(':checked') ? 1 : 0
        });
    });

    const btn = $('#btn-save-perms');
    const originalText = btn.html();
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Guardando...');

    $.post('api_users.php', {
        action: 'save_permissions',
        user_id: id,
        modules: JSON.stringify(modules),
        sheets: JSON.stringify(sheets)
    }, function(res) {
        btn.prop('disabled', false).html(originalText);
        if (res.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Permisos Guardados!',
                text: 'La configuración de accesos a las pestañas y CMDB se actualizó correctamente.',
                timer: 2000,
                showConfirmButton: false
            });
            $('#permsModal').modal('hide');
        } else {
            Swal.fire('Error', res.error || 'Error al guardar permisos', 'error');
        }
    }).fail(function() {
        btn.prop('disabled', false).html(originalText);
        Swal.fire('Error', 'Fallo de comunicación con el servidor', 'error');
    });
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

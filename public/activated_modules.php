<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/helpers.php';

// Asegurar que solo administradores accedan
require_login();
if (!has_role(['SUPER_ADMIN'])) {
    header("Location: dashboard.php");
    exit();
}

$config_file = __DIR__ . '/assets/modules_config.json';
$deactivated_modules = [];
if (file_exists($config_file)) {
    $deactivated_modules = json_decode(file_get_contents($config_file), true) ?: [];
}

// Manejar petición AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'toggle_module') {
        $module_key = $_POST['module'] ?? '';
        if ($module_key) {
            if (in_array($module_key, $deactivated_modules)) {
                // Activar
                $deactivated_modules = array_values(array_diff($deactivated_modules, [$module_key]));
                $new_status = 'active';
            } else {
                // Desactivar
                $deactivated_modules[] = $module_key;
                $new_status = 'inactive';
            }
            file_put_contents($config_file, json_encode($deactivated_modules, JSON_PRETTY_PRINT));
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'new_status' => $new_status]);
            exit();
        }
    }

    if ($_POST['action'] === 'save_gitlab_config') {
        $gitlab_config_file = __DIR__ . '/assets/gitlab_config.json';
        $config_data = [
            'remote_url' => trim($_POST['remote_url'] ?? ''),
            'pat_token' => trim($_POST['pat_token'] ?? ''),
            'user_name' => trim($_POST['user_name'] ?? ''),
            'user_email' => trim($_POST['user_email'] ?? '')
        ];
        
        file_put_contents($gitlab_config_file, json_encode($config_data, JSON_PRETTY_PRINT));
        @chmod($gitlab_config_file, 0777);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Configuración de GitLab guardada correctamente.']);
        exit();
    }
}

// Cargar configuración de GitLab
$gitlab_config_file = __DIR__ . '/assets/gitlab_config.json';
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

$page_title = "Módulos Activados";
$page_icon = "fas fa-toggle-on text-success";
$hide_content_header = true;
include 'partials/header.php';

// Definición de módulos de la plataforma SYNAPSE
$modules_details = [
    'femsa' => [
        'title' => 'FEMSA',
        'icon' => 'fas fa-building',
        'color' => 'linear-gradient(135deg, #ce1126 0%, #8b0000 100%)',
        'desc' => 'Control de requerimientos, seguimiento de entregables y ciclo de automatización de procesos para FEMSA.',
        'link' => 'femsa/index.php'
    ],
    'actividades' => [
        'title' => 'ACTIVIDADES',
        'icon' => 'fas fa-tasks',
        'color' => 'linear-gradient(135deg, #0052cc 0%, #0747a6 100%)',
        'desc' => 'Módulo centralizado para control de requerimientos, tareas técnicas, bitácora de ejecución y ciclo de servicio.',
        'link' => 'actividades/index.php'
    ],
    'cmdb_sonda' => [
        'title' => 'CMDB_SONDA',
        'icon' => 'fas fa-cubes',
        'color' => 'linear-gradient(135deg, #002b49 0%, #0052cc 60%, #00b4d8 100%)',
        'desc' => 'Gestión relacional de CIs en 5 categorías funcionales, servicios críticos, dependencias de infraestructura, análisis de impacto y automatización de soporte.',
        'link' => 'cmdb_sonda/index.php'
    ],
    'vilaseca' => [
        'title' => 'Vilaseca',
        'icon' => 'fas fa-building',
        'color' => 'linear-gradient(135deg, #f7971e 0%, #ffd200 100%)',
        'desc' => 'Centro de operaciones exclusivo Vilaseca: Portmapping, Modelos Visio, análisis de enlaces y documentación física.',
        'link' => 'clientes/vilaseca/index.php'
    ],
    'dashboard' => [
        'title' => 'Dashboard General',
        'icon' => 'fas fa-tachometer-alt',
        'color' => 'linear-gradient(135deg, #1e3c72 0%, #2a5298 100%)',
        'desc' => 'Panel de control unificado con métricas clave de la plataforma, resumen de dispositivos, estado de la red y accesos rápidos.',
        'link' => 'dashboard.php'
    ],
    'novaiops_dashboard' => [
        'title' => 'NovaIOPS Dashboard',
        'icon' => 'fas fa-chart-pie',
        'color' => 'linear-gradient(135deg, #00c6ff 0%, #0072ff 100%)',
        'desc' => 'Dashboard avanzado de desempeño de especialistas, distribución de tareas, control de carga laboral e indicadores de nivel de servicio.',
        'link' => 'novaiops_dashboard.php'
    ],
    'ci_list' => [
        'title' => 'CMDB (Gestión de CIs)',
        'icon' => 'fas fa-project-diagram',
        'color' => 'linear-gradient(135deg, #11998e 0%, #38ef7d 100%)',
        'desc' => 'Gestión completa de Elementos de Configuración (CIs), mapa de relaciones, historial técnico e inventario relacional estructurado.',
        'link' => 'ci_list.php'
    ],
    'gitlab' => [
        'title' => 'GitLab Dev Workspace',
        'icon' => 'fab fa-gitlab',
        'color' => 'linear-gradient(135deg, #f857a6 0%, #ff5858 100%)',
        'desc' => 'Espacio de trabajo integrado para administración de repositorios GitLab, control de ramas, confirmación de cambios y visualización de diffs.',
        'link' => 'plugins/gitlab/index.php'
    ],
    'import' => [
        'title' => 'Importador Excel',
        'icon' => 'fas fa-file-excel',
        'color' => 'linear-gradient(135deg, #134e5e 0%, #71b280 100%)',
        'desc' => 'Carga masiva de datos e inventarios mediante plantillas estructuradas de hojas de cálculo con validación automática de campos.',
        'link' => 'import.php'
    ],
    'distribrack' => [
        'title' => 'Galería de Imágenes',
        'icon' => 'fas fa-images',
        'color' => 'linear-gradient(135deg, #654ea3 0%, #eaafc8 100%)',
        'desc' => 'Gestor visual de imágenes asociadas a equipamiento técnico, fotografías de racks instalados y vistas físicas de salas de datos.',
        'link' => 'distribrack.php'
    ],
    'topology' => [
        'title' => 'Topología de Red',
        'icon' => 'fas fa-network-wired',
        'color' => 'linear-gradient(135deg, #8a2387 0%, #e94057 100%, #f27121 100%)',
        'desc' => 'Renderizador interactivo de mapas lógicos y topologías de conexión física en dos dimensiones (2D) y tres dimensiones (3D).',
        'link' => 'topology.php'
    ],
    'monitoreo' => [
        'title' => 'Zabbix Monitoreo',
        'icon' => 'fas fa-server',
        'color' => 'linear-gradient(135deg, #ed213a 0%, #93291e 100%)',
        'desc' => 'Integración completa con la API de Zabbix para consultar alertas en tiempo real, estado de hosts, interfaces de red e informes de disponibilidad.',
        'link' => 'monitoreo.php'
    ],
    'project' => [
        'title' => 'Gestión de Proyectos',
        'icon' => 'fas fa-tasks',
        'color' => 'linear-gradient(135deg, #3a7bd5 0%, #3a6073 100%)',
        'desc' => 'Planificación de proyectos técnicos, definición de hitos, asignación de tareas a especialistas y control de presupuestos.',
        'link' => 'project.php'
    ],
    'portmapping' => [
        'title' => 'Portmapping Wizard',
        'icon' => 'fas fa-plug',
        'color' => 'linear-gradient(135deg, #f12711 0%, #f5af19 100%)',
        'desc' => 'Asistente interactivo paso a paso para documentar y mapear conexiones físicas de red y energía entre dispositivos y gabinetes.',
        'link' => 'portmapping.php'
    ],
    'diagrams' => [
        'title' => 'Diagramas y Modelos',
        'icon' => 'fas fa-project-diagram',
        'color' => 'linear-gradient(135deg, #00c6ff 0%, #0072ff 100%)',
        'desc' => 'Visor de modelos de procesos de negocio BPMN, editor/generador de flujos mediante sintaxis Mermaid e integración con esquemas de Visio.',
        'link' => 'flujos.php'
    ],
    'password' => [
        'title' => 'PASSWORD (Bóveda)',
        'icon' => 'fas fa-key',
        'color' => 'linear-gradient(135deg, #833ab4 0%, #fd1d1d 50%, #fcb045 100%)',
        'desc' => 'Almacenamiento encriptado seguro y bóveda centralizada para el control de contraseñas de administración de la infraestructura.',
        'link' => 'password.php'
    ],
    'cotizador' => [
        'title' => 'Cotizador de Servicios',
        'icon' => 'fas fa-calculator',
        'color' => 'linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%)',
        'desc' => 'Diseño rápido de ofertas técnicas de soporte y proyectos, estimación de viáticos, análisis de costos de especialistas y cálculo de margen PVP.',
        'link' => 'cotizador/index.php'
    ],
    'snmp' => [
        'title' => 'Módulo SNMP Avanzado',
        'icon' => 'fas fa-network-wired',
        'color' => 'linear-gradient(135deg, #1e3c72 0%, #2a5298 100%)',
        'desc' => 'Descubrimiento automático de OIDs de red, analizador y constructor de MIBs y escaneo directo de dispositivos activos de la red.',
        'link' => 'snmp_management.php'
    ],
    'reports' => [
        'title' => 'Generador de Informes',
        'icon' => 'fas fa-file-invoice',
        'color' => 'linear-gradient(135deg, #2193b0 0%, #6dd5ed 100%)',
        'desc' => 'Repositorio integrado de informes generados para clientes, reportes de inventario general y resúmenes ejecutivos.',
        'link' => 'reports_list.php'
    ],
    'log_analysis' => [
        'title' => 'Análisis de Logs',
        'icon' => 'fas fa-terminal',
        'color' => 'linear-gradient(135deg, #141e30 0%, #243b55 100%)',
        'desc' => 'Auditoría en tiempo real y analizador de registros de actividad del sistema, mensajes de error y diagnóstico de salud general.',
        'link' => 'log_analysis.php'
    ],
    'datacenter' => [
        'title' => 'Datacenter (DCIM)',
        'icon' => 'fas fa-building',
        'color' => 'linear-gradient(135deg, #36d1dc 0%, #5b86e5 100%)',
        'desc' => 'Gestión espacial y física de Centros de Datos (salas, filas, aire acondicionado) y modelamiento interactivo de gabinetes (Racks).',
        'link' => 'datacenter/rooms.php'
    ]
];
?>

<style>
    .module-card {
        border: 1px solid var(--border-color);
        background: var(--card-bg);
        border-radius: 16px;
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        overflow: hidden;
        position: relative;
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    .module-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15) !important;
        border-color: var(--sonda-cyan);
    }
    body.dark-mode .module-card:hover {
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4) !important;
    }
    .module-header-gradient {
        padding: 1.5rem;
        color: #ffffff;
        display: flex;
        align-items: center;
        gap: 1rem;
        position: relative;
    }
    .module-icon-wrapper {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        backdrop-filter: blur(5px);
        border: 1px solid rgba(255, 255, 255, 0.3);
    }
    .module-body {
        padding: 1.5rem;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .module-desc {
        color: var(--text-color);
        font-size: 0.9rem;
        line-height: 1.5;
        margin-bottom: 1.5rem;
        opacity: 0.85;
    }
    .module-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: auto;
        border-top: 1px solid var(--border-color);
        padding-top: 1rem;
    }
    .status-badge {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 5px 10px;
        border-radius: 20px;
        background-color: rgba(40, 167, 69, 0.15);
        color: #28a745;
        border: 1px solid rgba(40, 167, 69, 0.25);
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .status-badge.status-inactive {
        background-color: rgba(220, 53, 69, 0.15);
        color: #dc3545;
        border: 1px solid rgba(220, 53, 69, 0.25);
    }
    .module-card.module-inactive {
        border-color: rgba(220, 53, 69, 0.2);
        opacity: 0.75;
    }
    .platform-header-card {
        background: linear-gradient(135deg, var(--sonda-navy) 0%, #060b17 100%);
        border-radius: 16px;
        padding: 2.5rem;
        color: #ffffff;
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(0, 184, 212, 0.15);
    }
    .platform-header-card::before {
        content: '';
        position: absolute;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(0, 184, 212, 0.1) 0%, rgba(0, 184, 212, 0) 70%);
        top: -100px;
        right: -100px;
        border-radius: 50%;
    }
    .platform-logo-img {
        max-height: 80px;
        width: auto;
        filter: drop-shadow(0 4px 8px rgba(0,0,0,0.3));
    }
    .search-filter-wrapper {
        margin-bottom: 2rem;
    }
    .search-input-custom {
        padding: 0.85rem 1.25rem 0.85rem 2.75rem;
        border-radius: 12px;
        font-size: 1rem;
    }
</style>

<div class="container-fluid pb-5">
    <!-- Header Card -->
    <div class="platform-header-card shadow">
        <div class="row align-items-center">
            <div class="col-12">
                <p class="lead text-white-50 mb-0">
                    Directorio de capacidades y módulos integrados para operaciones de TI, monitoreo, automatización y gestión de la configuración (CMDB).
                </p>
            </div>
        </div>
    </div>

    <!-- Stats summary and search filter -->
    <?php
      $active_count = count($modules_details) - count($deactivated_modules);
      $percentage = count($modules_details) > 0 ? round(($active_count / count($modules_details)) * 100) : 100;
    ?>
    <div class="row align-items-center mb-4">
        <div class="col-lg-4 col-md-6 mb-3 mb-md-0">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-light px-3 py-2 rounded-lg border">
                    <span class="text-muted d-block small">MÓDULOS TOTALES</span>
                    <strong class="h4 mb-0"><?php echo count($modules_details); ?></strong>
                </div>
                <div class="bg-light px-3 py-2 rounded-lg border">
                    <span class="text-muted d-block small">ESTADO</span>
                    <span class="status-badge" id="platform-status-badge">
                        <span class="spinner-grow spinner-grow-sm" role="status" style="width: 8px; height: 8px;"></span> 
                        <?php echo $percentage; ?>% Activos
                    </span>
                </div>
            </div>
        </div>
        <div class="col-lg-8 col-md-6 text-md-right search-filter-wrapper">
            <div class="position-relative d-inline-block w-100" style="max-width: 400px;">
                <input type="text" id="module-search" class="form-control form-control-custom search-input-custom" placeholder="Buscar módulo...">
                <i class="fas fa-search position-absolute text-muted" style="left: 1.2rem; top: 50%; transform: translateY(-50%);"></i>
            </div>
        </div>
    </div>

    <!-- Modules Grid -->
    <div class="row" id="modules-container">
        <?php foreach ($modules_details as $key => $mod): 
            $is_active = !in_array($key, $deactivated_modules);
        ?>
            <div class="col-xl-4 col-lg-6 col-md-6 mb-4 module-item-card" data-title="<?php echo htmlspecialchars(strtolower($mod['title'])); ?>" data-desc="<?php echo htmlspecialchars(strtolower($mod['desc'])); ?>">
                <div class="module-card shadow-sm <?php echo $is_active ? '' : 'module-inactive'; ?>">
                    <div class="module-header-gradient" style="background: <?php echo $is_active ? $mod['color'] : 'linear-gradient(135deg, #5a6268 0%, #343a40 100%)'; ?>;">
                        <div class="module-icon-wrapper">
                            <i class="<?php echo $mod['icon']; ?>"></i>
                        </div>
                        <div>
                            <h5 class="font-weight-bold text-white mb-0"><?php echo htmlspecialchars($mod['title']); ?></h5>
                            <span class="text-white-50 small"><?php echo htmlspecialchars($key); ?></span>
                        </div>
                    </div>
                    <div class="module-body">
                        <p class="module-desc"><?php echo htmlspecialchars($mod['desc']); ?></p>
                        <div class="module-footer">
                            <?php if ($is_active): ?>
                                <span class="status-badge"><i class="fas fa-check-circle"></i> Activo</span>
                                <div class="d-flex gap-1 align-items-center">
                                    <?php if ($key === 'gitlab'): ?>
                                        <button class="btn btn-xs btn-info mr-1 gitlab-config-btn" type="button" data-toggle="modal" data-target="#gitlabConfigModal" title="Configurar Repositorio">
                                            <i class="fas fa-cog"></i> Configuración
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn btn-xs btn-outline-danger toggle-module-btn mr-1" data-module="<?php echo $key; ?>" data-title="<?php echo htmlspecialchars($mod['title']); ?>">
                                        <i class="fas fa-power-off"></i> Desactivar
                                    </button>
                                    <a href="<?php echo PUBLIC_URL_PREFIX; ?>/<?php echo $mod['link']; ?>" class="btn btn-xs btn-primary">
                                        <i class="fas fa-external-link-alt"></i> Ir
                                    </a>
                                </div>
                            <?php else: ?>
                                <span class="status-badge status-inactive"><i class="fas fa-times-circle"></i> Inactivo</span>
                                <div class="d-flex gap-1 align-items-center">
                                    <?php if ($key === 'gitlab'): ?>
                                        <button class="btn btn-xs btn-info mr-1 gitlab-config-btn" type="button" data-toggle="modal" data-target="#gitlabConfigModal" title="Configurar Repositorio">
                                            <i class="fas fa-cog"></i> Configuración
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn btn-xs btn-success toggle-module-btn mr-1" data-module="<?php echo $key; ?>" data-title="<?php echo htmlspecialchars($mod['title']); ?>">
                                        <i class="fas fa-play"></i> Activar
                                    </button>
                                    <button class="btn btn-xs btn-secondary" disabled>
                                        <i class="fas fa-external-link-alt"></i> Ir
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
$(document).ready(function() {
    // Dynamic search filtering
    $('#module-search').on('input', function() {
        const query = $(this).val().toLowerCase().trim();
        $('.module-item-card').each(function() {
            const title = $(this).data('title');
            const desc = $(this).data('desc');
            if (title.includes(query) || desc.includes(query)) {
                $(this).fadeIn(200);
            } else {
                $(this).fadeOut(200);
            }
        });
    });

    // Toggle activation state of modules
    $('.toggle-module-btn').on('click', function() {
        const btn = $(this);
        const moduleKey = btn.data('module');
        const moduleTitle = btn.data('title');
        const isActivating = btn.hasClass('btn-success');
        const actionText = isActivating ? 'activar' : 'desactivar';
        const confirmButtonColor = isActivating ? '#28a745' : '#dc3545';

        const performToggle = function() {
            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: {
                    action: 'toggle_module',
                    module: moduleKey
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                title: '¡Éxito!',
                                text: `Módulo "${moduleTitle}" ${isActivating ? 'activado' : 'desactivado'} con éxito.`,
                                icon: 'success',
                                timer: 1000,
                                showConfirmButton: false
                            });
                        } else if (typeof toastr !== 'undefined') {
                            toastr.success(`Módulo ${moduleTitle} ${isActivating ? 'activado' : 'desactivado'} con éxito.`);
                        }
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        alert('Error al cambiar el estado del módulo.');
                    }
                },
                error: function() {
                    alert('Error de conexión con el servidor.');
                }
            });
        };

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: `¿Confirmar acción?`,
                text: `¿Estás seguro de que deseas ${actionText} el módulo "${moduleTitle}"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: confirmButtonColor,
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, continuar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    performToggle();
                }
            });
        } else {
            if (confirm(`¿Estás seguro de que deseas ${actionText} el módulo "${moduleTitle}"?`)) {
                performToggle();
            }
        }
        // Guardar configuración de GitLab
        $('#gitlab-config-form').on('submit', function(e) {
            e.preventDefault();
            const formData = $(this).serialize() + '&action=save_gitlab_config';
            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        $('#gitlabConfigModal').modal('hide');
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                title: '¡Guardado!',
                                text: response.message,
                                icon: 'success',
                                timer: 1500,
                                showConfirmButton: false
                            });
                        } else if (typeof toastr !== 'undefined') {
                            toastr.success(response.message);
                        }
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        alert('Error al guardar la configuración.');
                    }
                },
                error: function() {
                    alert('Error de conexión con el servidor.');
                }
            });
        });
    });
});
</script>

<!-- Modal de Configuración de GitLab -->
<div class="modal fade" id="gitlabConfigModal" tabindex="-1" role="dialog" aria-labelledby="gitlabConfigModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content shadow-lg border-0" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header bg-dark text-white border-0 py-3">
                <h5 class="modal-title font-weight-bold" id="gitlabConfigModalLabel">
                    <i class="fab fa-gitlab text-warning mr-2"></i> Configuración GitLab Dev Workspace
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="gitlab-config-form">
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">
                        Establezca las credenciales y el repositorio remoto de Git. Estos parámetros son persistidos en el servidor para realizar las tareas de control de versiones y push directo.
                    </p>
                    <div class="form-group mb-3">
                        <label for="gl_remote_url" class="font-weight-bold text-xs text-secondary">URL del Repositorio Remoto (HTTPS):</label>
                        <input type="url" class="form-control form-control-sm rounded" id="gl_remote_url" name="remote_url" required value="<?php echo htmlspecialchars($gitlab_config['remote_url']); ?>" placeholder="https://github.com/usuario/repo.git">
                    </div>
                    <div class="form-group mb-3">
                        <label for="gl_pat_token" class="font-weight-bold text-xs text-secondary">Token de Acceso Personal (PAT):</label>
                        <input type="password" class="form-control form-control-sm rounded" id="gl_pat_token" name="pat_token" required value="<?php echo htmlspecialchars($gitlab_config['pat_token']); ?>" placeholder="ghp_xxxxxxxxxxxxxxxxxxxx">
                    </div>
                    <div class="form-group mb-3">
                        <label for="gl_user_name" class="font-weight-bold text-xs text-secondary">Nombre de Usuario de Git:</label>
                        <input type="text" class="form-control form-control-sm rounded" id="gl_user_name" name="user_name" required value="<?php echo htmlspecialchars($gitlab_config['user_name']); ?>" placeholder="ej: blacic2016">
                    </div>
                    <div class="form-group mb-0">
                        <label for="gl_user_email" class="font-weight-bold text-xs text-secondary">Correo Electrónico de Git:</label>
                        <input type="email" class="form-control form-control-sm rounded" id="gl_user_email" name="user_email" required value="<?php echo htmlspecialchars($gitlab_config['user_email']); ?>" placeholder="ej: email@dominio.com">
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3">
                    <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-success font-weight-bold shadow-sm">
                        <i class="fas fa-save mr-1"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'partials/footer.php'; ?>

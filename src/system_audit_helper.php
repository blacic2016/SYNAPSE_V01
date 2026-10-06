<?php
/**
 * CMDB VILASECA - Lógica de Auditoría del Sistema Integral
 * Auditoría completa de:
 * 1. Entorno PHP, Extensiones y Herramientas CLI
 * 2. Librerías Composer (Vendor) y Frontend
 * 3. Base de Datos: Métricas, Estadísticas y 94 Tablas Maestras por Módulos
 * 4. Permisos de Directorios y Almacenamiento
 * 5. Sistema de Respaldo a BACK con Registro Persistente de Fecha y Estado Final
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

/**
 * Obtiene el estado y fecha del último respaldo a BACK
 */
function getLastBackupStatus()
{
    $statusFile = STORAGE_DIR . '/backup_status.json';
    $backDir = '/var/www/html/PROYECTOSONDA/BACK';
    $backCmdbDir = is_dir($backDir . '/SYNAPSE') ? ($backDir . '/SYNAPSE') : ($backDir . '/CMDBPRnew');

    $data = null;
    if (file_exists($statusFile)) {
        $content = file_get_contents($statusFile);
        $data = json_decode($content, true);
    }

    // Si no hay archivo JSON, intentar consultar la base de datos
    if (!$data) {
        try {
            $pdo = getPDO();
            if ($pdo) {
                // Verificar si existe la tabla system_backup_logs
                $check = $pdo->query("SHOW TABLES LIKE 'system_backup_logs'")->fetch();
                if ($check) {
                    $stmt = $pdo->query("SELECT * FROM system_backup_logs ORDER BY id DESC LIMIT 1");
                    $dbRow = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($dbRow) {
                        $data = [
                            'last_backup_date' => $dbRow['backup_date'],
                            'timestamp' => strtotime($dbRow['backup_date']),
                            'status' => $dbRow['status'],
                            'status_code' => ($dbRow['status'] === 'EXITOSO') ? 'success' : 'danger',
                            'duration_seconds' => (float)$dbRow['duration_seconds'],
                            'items_count' => (int)$dbRow['items_count'],
                            'source' => $dbRow['source_path'],
                            'target' => $dbRow['target_path'],
                            'db_dump_size' => $dbRow['db_dump_size'],
                            'executed_by' => $dbRow['executed_by'],
                            'details' => $dbRow['log_output']
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            // Silencioso en auditoría
        }
    }

    // Fallback: detectar última modificación del directorio BACK si aún no hay registros formales
    if (!$data) {
        if (is_dir($backCmdbDir)) {
            $mtime = filemtime($backCmdbDir);
            $itemCount = 0;
            try {
                $fi = new FilesystemIterator($backCmdbDir, FilesystemIterator::SKIP_DOTS);
                $itemCount = iterator_count($fi);
            } catch (Exception $e) {}

            $data = [
                'last_backup_date' => date('Y-m-d H:i:s', $mtime),
                'timestamp' => $mtime,
                'status' => 'EXITOSO (Detectado en Disco)',
                'status_code' => 'success',
                'duration_seconds' => null,
                'items_count' => $itemCount,
                'source' => '/var/www/html/PROYECTOSONDA/PREPODUCCION',
                'target' => $backDir,
                'db_dump_size' => 'N/D',
                'executed_by' => 'Sistema / Previo',
                'details' => 'Copia detectada directamente en el sistema de archivos del servidor.'
            ];
        } else {
            $data = [
                'last_backup_date' => 'Nunca / No realizado',
                'timestamp' => 0,
                'status' => 'PENDIENTE',
                'status_code' => 'warning',
                'duration_seconds' => null,
                'items_count' => 0,
                'source' => '/var/www/html/PROYECTOSONDA/PREPODUCCION',
                'target' => $backDir,
                'db_dump_size' => '0 MB',
                'executed_by' => 'N/A',
                'details' => 'Aún no se ha realizado ninguna copia de respaldo a BACK.'
            ];
        }
    }

    // Calcular tiempo relativo ("Hace X minutos/horas/días")
    $timeAgo = 'Desconocido';
    if (!empty($data['timestamp'])) {
        $diff = time() - $data['timestamp'];
        if ($diff < 60) {
            $timeAgo = "Hace " . max(1, $diff) . " segundo" . ($diff != 1 ? 's' : '');
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            $timeAgo = "Hace $mins minuto" . ($mins != 1 ? 's' : '');
        } elseif ($diff < 86400) {
            $hrs = floor($diff / 3600);
            $timeAgo = "Hace $hrs hora" . ($hrs != 1 ? 's' : '');
        } else {
            $days = floor($diff / 86400);
            $timeAgo = "Hace $days día" . ($days != 1 ? 's' : '');
        }
    }
    $data['time_ago'] = $timeAgo;

    // Obtener historial de últimos respaldos si existe
    $history = [];
    try {
        $pdo = getPDO();
        if ($pdo) {
            $check = $pdo->query("SHOW TABLES LIKE 'system_backup_logs'")->fetch();
            if ($check) {
                $stmt = $pdo->query("SELECT * FROM system_backup_logs ORDER BY id DESC LIMIT 5");
                $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } catch (Exception $e) {}
    $data['history'] = $history;

    return $data;
}

/**
 * Ejecuta la auditoría exhaustiva del sistema
 */
function runSystemAudit() {
    $results = [
        'php' => [
            'version' => PHP_VERSION,
            'status' => version_compare(PHP_VERSION, '7.4.0', '>=') ? 'success' : 'warning',
            'message' => 'PHP ' . PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time') . 's',
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size')
        ],
        'extensions' => [],
        'cli_tools' => [],
        'composer_libraries' => [],
        'frontend_libraries' => [],
        'directories' => [],
        'database' => [
            'connected' => false,
            'host' => 'N/A',
            'name' => 'N/A',
            'version' => 'N/A',
            'size_mb' => 0,
            'total_rows' => 0,
            'tables_count' => 0,
            'other_databases' => [],
            'message' => '',
            'status' => 'error'
        ],
        'zabbix' => [
            'url' => 'N/A',
            'status' => 'info'
        ],
        'backup_status' => getLastBackupStatus()
    ];

    // 1. Extensiones PHP Requeridas
    $required_extensions = [
        'pdo' => 'Capa de abstracción de datos PDO',
        'pdo_mysql' => 'Controlador MySQL / MariaDB para PDO',
        'curl' => 'Cliente HTTP / API Zabbix & Integraciones',
        'json' => 'Manipulación y decodificación de datos JSON',
        'mbstring' => 'Gestión de cadenas multi-byte UTF-8',
        'gd' => 'Manipulación de imágenes, diagramas y avatars',
        'zip' => 'Importación/Exportación Excel y compresión ZIP',
        'xml' => 'Lectura y parsing de esquemas XML / BPMN / Visio',
        'dom' => 'Procesamiento de documentos XML y HTML',
        'snmp' => 'Descubrimiento de red y recolección SNMP',
        'openssl' => 'Cifrado Bóveda de Contraseñas y HTTPS',
        'fileinfo' => 'Validación MIME y seguridad de adjuntos'
    ];

    foreach ($required_extensions as $ext => $desc) {
        $loaded = extension_loaded($ext);
        $results['extensions'][$ext] = [
            'loaded' => $loaded,
            'description' => $desc,
            'status' => $loaded ? 'success' : 'error'
        ];
    }

    // 2. Herramientas CLI de Sistema Operativo
    $cli_tools = [
        'snmptranslate' => 'Traducción de MIBs y OIDs SNMP',
        'snmpwalk' => 'Escaneo y Recorrido de MIBs de Red',
        'snmpget' => 'Consulta directa de OIDs SNMP',
        'rsync' => 'Sincronización incremental para Respaldo a BACK',
        'mysqldump' => 'Exportación y volcado de Bases de Datos',
        'git' => 'Control de versiones y repositorio GitLab/GitHub'
    ];
    foreach ($cli_tools as $tool => $desc) {
        $path = trim(shell_exec("which $tool 2>&1") ?: '');
        $exists = !empty($path) && file_exists($path);
        $results['cli_tools'][$tool] = [
            'exists' => $exists,
            'path' => $exists ? $path : 'No instalado',
            'description' => $desc,
            'status' => $exists ? 'success' : ($tool === 'rsync' || $tool === 'mysqldump' ? 'error' : 'warning')
        ];
    }

    // 3. Librerías Composer (Vendor)
    $installedJson = ROOT_PATH . '/vendor/composer/installed.json';
    $installedPackages = [];
    if (file_exists($installedJson)) {
        $pData = json_decode(file_get_contents($installedJson), true);
        $pkgs = isset($pData['packages']) ? $pData['packages'] : (is_array($pData) ? $pData : []);
        foreach ($pkgs as $p) {
            if (isset($p['name'])) {
                $installedPackages[$p['name']] = $p['version'] ?? 'N/D';
            }
        }
    }

    $required_vendor = [
        'phpoffice/phpspreadsheet' => [
            'desc' => 'Motor de importación y exportación de archivos Excel (.xlsx)',
            'critical' => true
        ],
        'vlucas/phpdotenv' => [
            'desc' => 'Gestor de carga de configuraciones y variables de entorno',
            'critical' => true
        ],
        'ezyang/htmlpurifier' => [
            'desc' => 'Sanitizador de HTML y mitigación de ataques XSS',
            'critical' => true
        ],
        'maennchen/zipstream-php' => [
            'desc' => 'Generador dinámico de descargas ZIP sin colapso de memoria RAM',
            'critical' => false
        ],
        'symfony/console' => [
            'desc' => 'Componente de ejecución CLI y comandos de Symfony',
            'critical' => false
        ]
    ];

    foreach ($required_vendor as $pkg => $cfg) {
        $isInstalled = isset($installedPackages[$pkg]) || file_exists(ROOT_PATH . '/vendor/' . $pkg);
        $ver = $installedPackages[$pkg] ?? ($isInstalled ? 'Instalado' : 'No instalado');
        $results['composer_libraries'][$pkg] = [
            'installed' => $isInstalled,
            'version' => $ver,
            'description' => $cfg['desc'],
            'critical' => $cfg['critical'],
            'status' => $isInstalled ? 'success' : ($cfg['critical'] ? 'error' : 'warning')
        ];
    }

    // 4. Librerías Frontend y Frameworks del Sistema
    $frontend_libs = [
        'Bootstrap 4.6' => ['desc' => 'Framework CSS/JS de interfaz de usuario', 'type' => 'CDN / Assets'],
        'AdminLTE 3.2' => ['desc' => 'Plantilla administrativa y componentes visuales', 'type' => 'CDN / Assets'],
        'Font Awesome 5.15' => ['desc' => 'Paquete tipográfico de iconos vectoriales', 'type' => 'CDN / Assets'],
        'jQuery 3.6' => ['desc' => 'Librería DOM reactiva para manipulación UI', 'type' => 'CDN / Assets'],
        'SweetAlert2' => ['desc' => 'Modales y alertas interactivas de alta fidelidad', 'type' => 'CDN / Assets'],
        'Select2' => ['desc' => 'Selectores inteligentes con búsqueda instantánea', 'type' => 'CDN / Assets'],
        'Vis.js / vis-network' => ['desc' => 'Renderizador interactivo de grafos de red y CI', 'type' => 'CDN / Assets'],
        'BPMN.io (BPMN Viewer)' => ['desc' => 'Modelado y diagramación de procesos BPMN 2.0', 'type' => 'CDN / Assets'],
        'Mermaid.js' => ['desc' => 'Generador dinámico de diagramas de flujo y arquitectura', 'type' => 'CDN / Assets'],
        'Three.js' => ['desc' => 'Motor 3D para salas y racks de Datacenter', 'type' => 'CDN / Assets']
    ];
    foreach ($frontend_libs as $name => $finfo) {
        $results['frontend_libraries'][$name] = [
            'name' => $name,
            'description' => $finfo['desc'],
            'type' => $finfo['type'],
            'status' => 'success'
        ];
    }

    // 5. Directorios y Permisos del Sistema de Archivos
    $mibs_path = defined('SNMP_MIBS_PATH') ? SNMP_MIBS_PATH : ROOT_PATH . '/public/snmpbuilder/mibs';
    $dirs = [
        'storage' => ROOT_PATH . '/storage',
        'logs' => ROOT_PATH . '/storage/logs',
        'sessions' => ROOT_PATH . '/storage/sessions_fix',
        'backups' => ROOT_PATH . '/storage/backups',
        'uploads' => ROOT_PATH . '/public/uploads',
        'snmp_builder' => ROOT_PATH . '/public/snmpbuilder',
        'snmp_mibs' => $mibs_path,
        'vendor' => ROOT_PATH . '/vendor',
        'gitlab_repo' => ROOT_PATH . '/public/femsa/repository',
        'femsa_uploads' => ROOT_PATH . '/public/femsa/uploads',
        'backup_dest' => '/var/www/html/PROYECTOSONDA/BACK'
    ];

    foreach ($dirs as $name => $path) {
        $exists = is_dir($path);
        $writable = $exists && is_writable($path);
        $results['directories'][$name] = [
            'path' => $path,
            'exists' => $exists,
            'writable' => $writable,
            'status' => $writable ? 'success' : 'error'
        ];
    }

    // 6. Auditoría Exhaustiva de la Base de Datos
    if (defined('DB_CONFIG')) {
        $results['database']['host'] = DB_CONFIG['host'];
        $results['database']['name'] = DB_CONFIG['database'];
        try {
            $pdo = getPDO();
            if ($pdo) {
                $results['database']['connected'] = true;

                // Versión de MySQL / MariaDB
                $versionStmt = $pdo->query("SELECT VERSION()");
                $results['database']['version'] = $versionStmt->fetchColumn();

                // Tamaño de la Base de Datos y Total de Registros
                $dbName = DB_CONFIG['database'];
                $sizeQuery = $pdo->prepare("SELECT SUM(data_length + index_length) / 1024 / 1024 AS size_mb, SUM(table_rows) AS total_rows, COUNT(*) AS tables_count FROM information_schema.TABLES WHERE table_schema = ?");
                $sizeQuery->execute([$dbName]);
                $sizeRow = $sizeQuery->fetch(PDO::FETCH_ASSOC);
                if ($sizeRow) {
                    $results['database']['size_mb'] = round((float)($sizeRow['size_mb'] ?? 0), 2);
                    $results['database']['total_rows'] = (int)($sizeRow['total_rows'] ?? 0);
                    $results['database']['tables_count'] = (int)($sizeRow['tables_count'] ?? 0);
                }

                // Otras bases de datos existentes en el servidor
                $dbsStmt = $pdo->query("SHOW DATABASES");
                $allDbs = $dbsStmt->fetchAll(PDO::FETCH_COLUMN);
                $cmdbDbs = array_values(array_filter($allDbs, function($d) {
                    return stripos($d, 'cmdb') !== false || stripos($d, 'sonda') !== false || stripos($d, 'vilaseca') !== false;
                }));
                $results['database']['other_databases'] = $cmdbDbs;

                // Definición exhaustiva de las 94 Tablas del Sistema agrupadas por Módulo
                $modules = [
                    'Core & Seguridad' => [
                        'roles' => 'Roles de Usuarios del Sistema',
                        'users' => 'Usuarios y Credenciales de Acceso',
                        'user_sheet_permissions' => 'Matriz de Permisos de Hojas Excel',
                        'user_module_permissions' => 'Matriz de Permisos Granulares de Módulos',
                        'import_logs' => 'Historial de Auditoría de Cargas Masivas',
                        'images' => 'Catálogo Centralizado de Imágenes y Evidencias',
                        'asset_sequence' => 'Control de Consecutivos y Códigos de Activos'
                    ],
                    'Bóveda de Contraseñas' => [
                        'password_entries' => 'Credenciales y Cuentas Cifradas (AES-256)',
                        'password_history' => 'Historial y Registro de Rotación de Contraseñas',
                        'password_vault_settings' => 'Políticas y Parámetros de Seguridad de la Bóveda'
                    ],
                    'CI Graph & Topología CMDB' => [
                        'ci_attributes' => 'Definición de Atributos Dinámicos de CI',
                        'ci_categories' => 'Taxonomía y Jerarquía de Categorías de CI',
                        'ci_instances' => 'Instancias de Elementos de Configuración (CIs)',
                        'ci_components' => 'Subcomponentes Físicos y Lógicos de CIs',
                        'ci_relationships' => 'Relaciones de Dependencia e Impacto entre CIs'
                    ],
                    'CMDB SONDA Servicios' => [
                        'cmdb_sonda_cis' => 'Inventario Maestro de CIs Sonda con Ciclo de Vida',
                        'cmdb_sonda_relationships' => 'Topología y Mapeo de Relaciones entre Activos Sonda',
                        'cmdb_sonda_services' => 'Servicios de Negocio y Criticidad Operativa Sonda',
                        'cmdb_sonda_images' => 'Diagramas y Archivos Multimedia Asociados a CIs',
                        'cmdb_sonda_audit_logs' => 'Trazabilidad y Auditoría de Cambios en CIs Sonda',
                        'cmdb_sequences' => 'Secuenciador Automático de Códigos de Activos Sonda',
                        'cmdb_category_dependencies' => 'Reglas de Compatibilidad y Dependencias entre Categorías',
                        'cmdb_relationship_types' => 'Catálogo de Tipos de Relación (Aloja, Depende, Conecta)'
                    ],
                    'Datacenter 2D/3D' => [
                        'dc_rooms' => 'Salas de Datacenter y Dimensionamiento Físico',
                        'dc_racks' => 'Racks de Telecomunicaciones y Servidores (Coordenadas y U)',
                        'dc_rack_devices' => 'Dispositivos y Equipamiento Montado en Racks',
                        'dc_floor_layers' => 'Capas de Planta de Sala (Piso, Clima, Racks, UPS)',
                        'dc_floor_items' => 'Elementos y Obstáculos en Piso de Sala'
                    ],
                    'Mapeo de Puertos y Cableado' => [
                        'port_mappings' => 'Conexiones Puerto a Puerto y Cableado Físico',
                        'manual_portmap_surveys' => 'Levantamientos Técnicos en Terreno de Puertos'
                    ],
                    'Diagramas y Flujos' => [
                        'visio_diagrams' => 'Diagramas de Red y Arquitectura Estilo Visio',
                        'visio_diagram_history' => 'Historial de Revisiones de Diagramas Visio',
                        'bpmn_diagrams' => 'Modelado de Procesos de Negocio BPMN 2.0',
                        'bpmn_diagram_history' => 'Historial de Modificaciones BPMN',
                        'mermaid_flows' => 'Diagramas de Flujo y Código Mermaid',
                        'mermaid_flow_history' => 'Snapshots de Versiones de Diagramas Mermaid'
                    ],
                    'Zabbix & Monitoreo' => [
                        'zabbix_api_config' => 'Configuración de Conexión y Token API Zabbix',
                        'zabbix_cmdb_config' => 'Parámetros Operativos de Sincronización Zabbix',
                        'zabbix_keywords' => 'Palabras Clave para Descubrimiento de Interfaces',
                        'zabbix_mappings' => 'Reglas de Mapeo de Templates y Tags Zabbix',
                        'zabbix_costs_rules' => 'Reglas de Costos por Capacidad y Consumo',
                        'host_interfaces' => 'Interfaces de Red Sincronizadas desde Zabbix/SNMP',
                        'snmp_communities' => 'Catálogo de Cadenas de Comunidad SNMP',
                        'snmp_scan_results' => 'Resultados de Barrido y Escaneo SNMP'
                    ],
                    'FEMSA Bitácora & Requerimientos' => [
                        'femsa_requirements' => 'Requerimientos, Tareas y Desarrollos FEMSA',
                        'femsa_requirement_history' => 'Trazabilidad y Auditoría de Estados FEMSA',
                        'femsa_bitacora' => 'Bitácora Operativa Diaria de Actividades FEMSA',
                        'femsa_bitacora_images' => 'Evidencias Fotográficas de Bitácora FEMSA',
                        'femsa_attachments' => 'Documentación y Archivos Adjuntos FEMSA',
                        'femsa_git_logs' => 'Logs de Commits y Sincronización Git FEMSA'
                    ],
                    'NovaIOps & Reportes' => [
                        'novaiops_informacion_reportes' => 'Reportes Consolidados de Tareas NovaIOps',
                        'novaiops_meta_columns' => 'Metadatos de Columnas Dinámicas de Reportes',
                        'novaiops_reporte_tareas' => 'Detalle y Tiempos Efectivos de Tareas Operativas',
                        'novaiops_seguimientos' => 'Seguimiento y Registro de Novedades de Tareas',
                        'novaiops_upload_history' => 'Historial de Procesamiento de Reportes Excel'
                    ],
                    'Gestión de Proyectos' => [
                        'projects' => 'Catálogo Maestro de Proyectos de Infraestructura',
                        'project_milestones' => 'Hitos y Entregables Clave de Proyectos',
                        'project_tasks' => 'Tareas y Asignaciones Operativas de Proyectos',
                        'project_observations' => 'Bitácora de Observaciones de Proyecto',
                        'project_milestone_templates' => 'Plantillas Predefinidas de Hitos de Proyecto',
                        'project_task_templates' => 'Plantillas de Tareas Estándar para Proyectos'
                    ],
                    'Cotizador de Servicios' => [
                        'cotizador_cotizaciones' => 'Cotizaciones Comerciales y Versiones',
                        'cotizador_cotizaciones_detalles' => 'Detalle de Partidas, Precios y Costos',
                        'cotizador_cotizaciones_adjuntos' => 'Propuestas Comerciales y Adjuntos',
                        'cotizador_equipment_categories' => 'Categorías de Equipamiento Homologado',
                        'cotizador_pool_brands' => 'Marcas y Fabricantes Registrados',
                        'cotizador_pool_servicios' => 'Catálogo de Servicios Profesionales Tarifados',
                        'cotizador_specialists' => 'Registro de Especialistas Técnicos',
                        'cotizador_specialist_levels' => 'Niveles de Séniority y Tarifas Horarias'
                    ],
                    'GitLab & Control de Versiones' => [
                        'gitlab_files' => 'Archivos y Scripts en Repositorio Interno',
                        'gitlab_versions' => 'Historial de Versiones de Código',
                        'gitlab_git_logs' => 'Auditoría de Envíos Git Push a GitHub/GitLab'
                    ],
                    'Hojas e Inventarios Dinámicos' => [
                        'sheet_configs' => 'Configuración de Hojas y Tablas Dinámicas',
                        'sheet_history' => 'Historial de Auditoría Campo por Campo de Hojas',
                        'sheet_ap' => 'Inventario de Access Points (AP)',
                        'sheet_aps' => 'Inventario Alternativo de Access Points',
                        'sheet_datastores' => 'Inventario de Datastores y Almacenamiento',
                        'sheet_distrib_rack' => 'Distribución de Racks de Datacenter',
                        'sheet_equipos' => 'Inventario General de Equipos',
                        'sheet_firewall' => 'Inventario de Firewalls y Seguridad',
                        'sheet_laptops' => 'Inventario de Equipos Portátiles / Laptops',
                        'sheet_localidades' => 'Sedes, Agencias y Localidades Geográficas',
                        'sheet_pasivos' => 'Inventario de Elementos Pasivos de Red',
                        'sheet_plataformas_' => 'Inventario de Plataformas de Sistemas',
                        'sheet_relaciones' => 'Relaciones Físicas y Lógicas de Inventario',
                        'sheet_routers' => 'Inventario de Enrutadores / Routers',
                        'sheet_servers' => 'Inventario de Servidores Generales',
                        'sheet_servers_f_sicos' => 'Inventario de Servidores Físicos',
                        'sheet_servers_virtuales' => 'Inventario de Servidores Virtuales',
                        'sheet_servicios' => 'Inventario de Servicios de TI',
                        'sheet_switches' => 'Inventario de Switches de Telecomunicaciones',
                        'sheet_ups' => 'Inventario de Sistemas de Energía Ininterrumpida (UPS)',
                        'sheet_vmplataformas_' => 'Plataformas de Máquinas Virtuales',
                        'sheet_vms' => 'Inventario de Máquinas Virtuales (VMs)'
                    ]
                ];

                // Esquemas esperados para validación profunda de integridad
                $expected_schema = [
                    'roles' => ['id', 'name'],
                    'users' => ['id', 'username', 'password', 'role_id'],
                    'password_entries' => ['id', 'name', 'password'],
                    'ci_attributes' => ['id', 'name', 'type', 'group_name'],
                    'ci_categories' => ['id', 'name', 'description'],
                    'ci_instances' => ['id', 'category_id', 'hostname', 'status'],
                    'ci_components' => ['id', 'parent_ci_id', 'name'],
                    'ci_relationships' => ['id', 'source_type', 'source_id', 'target_type', 'target_id', 'relation_type', 'impact'],
                    'cmdb_sonda_cis' => ['id', 'id_ci', 'hostname_nombre', 'tipo_ci'],
                    'cmdb_sonda_services' => ['id', 'service_code', 'nombre_servicio'],
                    'dc_rooms' => ['id', 'name', 'floor_height_meters'],
                    'dc_racks' => ['id', 'room_id', 'rotation', 'z_index'],
                    'dc_rack_devices' => ['id', 'rack_id', 'start_u', 'height_u'],
                    'dc_floor_items' => ['id', 'room_id', 'height_meters', 'rotation'],
                    'port_mappings' => ['id', 'source_component_id', 'target_component_id'],
                    'manual_portmap_surveys' => ['id', 'client', 'device_name', 'ports_data_json'],
                    'visio_diagrams' => ['id', 'title', 'xml_content'],
                    'bpmn_diagrams' => ['id', 'title', 'xml_content'],
                    'mermaid_flows' => ['id', 'title', 'code'],
                    'femsa_requirements' => ['id', 'ticket_code', 'activity_type'],
                    'femsa_bitacora' => ['id', 'requirement_id', 'tema', 'descripcion'],
                    'projects' => ['id', 'code', 'name'],
                    'cotizador_cotizaciones' => ['id', 'cliente', 'estado'],
                    'gitlab_files' => ['id', 'filename', 'file_type'],
                    'gitlab_versions' => ['id', 'file_id', 'version_number'],
                    'snmp_scan_results' => ['id', 'ip', 'community_ok', 'interfaces_up_json', 'status'],
                    'host_interfaces' => ['id', 'hostid', 'interface_name', 'connected_hostid'],
                    'zabbix_costs_rules' => ['id', 'groupid', 'hourly_rate_capacity', 'hourly_rate_utilized']
                ];

                $existingTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

                $tableStatus = [];
                $moduleSummaries = [];
                $missingTables = [];
                $outdatedTables = [];

                foreach ($modules as $modName => $tblList) {
                    $modTotal = count($tblList);
                    $modOk = 0;

                    foreach ($tblList as $tbl => $desc) {
                        $is_present = in_array($tbl, $existingTables);
                        $columns_ok = true;
                        $missing_cols = [];
                        $rowCount = 0;

                        if ($is_present) {
                            $cols = $pdo->query("DESCRIBE `$tbl`")->fetchAll(PDO::FETCH_COLUMN);
                            if (isset($expected_schema[$tbl])) {
                                foreach ($expected_schema[$tbl] as $req_col) {
                                    if (!in_array($req_col, $cols)) {
                                        $columns_ok = false;
                                        $missing_cols[] = $req_col;
                                    }
                                }
                            }

                            // Obtener cantidad de filas
                            try {
                                $rc = $pdo->query("SELECT COUNT(*) FROM `$tbl`")->fetchColumn();
                                $rowCount = (int)$rc;
                            } catch (Exception $e) {
                                $rowCount = 0;
                            }
                        }

                        $isFullyOk = $is_present && $columns_ok;
                        if ($isFullyOk) $modOk++;

                        $tableStatus[$tbl] = [
                            'table' => $tbl,
                            'module' => $modName,
                            'description' => $desc,
                            'exists' => $is_present,
                            'columns_ok' => $columns_ok,
                            'missing_cols' => $missing_cols,
                            'row_count' => $rowCount,
                            'status' => $isFullyOk ? 'success' : (!$is_present ? 'error' : 'warning')
                        ];

                        if (!$is_present) {
                            $missingTables[] = $tbl;
                        } elseif (!$columns_ok) {
                            $outdatedTables[] = $tbl . ' (Falta: ' . implode(', ', $missing_cols) . ')';
                        }
                    }

                    $moduleSummaries[$modName] = [
                        'total' => $modTotal,
                        'ok' => $modOk,
                        'status' => ($modOk === $modTotal) ? 'success' : ($modOk > 0 ? 'warning' : 'error')
                    ];
                }

                $results['database']['modules'] = $moduleSummaries;
                $results['database']['table_analysis'] = $tableStatus;
                $results['database']['missing_tables'] = $missingTables;
                $results['database']['outdated_tables'] = $outdatedTables;

                if (!empty($missingTables) || !empty($outdatedTables)) {
                    $results['database']['status'] = 'warning';
                    $results['database']['message'] = "Estructura incompleta o desactualizada (" . (count($missingTables) + count($outdatedTables)) . " elementos requieren atención)";
                } else {
                    $results['database']['status'] = 'success';
                    $results['database']['message'] = "Base de datos íntegra: 94 tablas operativas y actualizadas (" . number_format($results['database']['total_rows']) . " registros en total).";
                }
            } else {
                $results['database']['status'] = 'error';
                $results['database']['message'] = "No se pudo establecer la conexión PDO con la base de datos.";
            }
        } catch (Exception $e) {
            $results['database']['message'] = $e->getMessage();
            $results['database']['status'] = 'error';
        }
    }

    // 7. Zabbix API
    if (defined('ZABBIX_API_URL')) {
        $results['zabbix']['url'] = ZABBIX_API_URL;
        $results['zabbix']['status'] = (strpos(ZABBIX_API_URL, '172.32.1.50') !== false) ? 'warning' : 'success';
    }

    return $results;
}

/**
 * Corrige problemas detectados (permisos, directorios faltantes y estructura de base de datos)
 */
function fixSystemIssues() {
    $mibs_path = defined('SNMP_MIBS_PATH') ? SNMP_MIBS_PATH : ROOT_PATH . '/public/snmpbuilder/mibs';
    $dirs = [
        'Storage Root' => ROOT_PATH . '/storage',
        'Logs' => ROOT_PATH . '/storage/logs',
        'Sessions' => ROOT_PATH . '/storage/sessions_fix',
        'Backups Locales' => ROOT_PATH . '/storage/backups',
        'Uploads' => ROOT_PATH . '/public/uploads',
        'SNMP Builder Root' => ROOT_PATH . '/public/snmpbuilder',
        'Repositorio MIBs SNMP' => $mibs_path,
        'GitLab Repository' => ROOT_PATH . '/public/femsa/repository',
        'FEMSA Uploads' => ROOT_PATH . '/public/femsa/uploads',
        'Destino BACK' => '/var/www/html/PROYECTOSONDA/BACK'
    ];
    $log = [];
    foreach ($dirs as $name => $dir) {
        if (!is_dir($dir)) {
            if (@mkdir($dir, 0777, true)) {
                $log[] = "✅ Carpeta '$name' creada con éxito (<code>$dir</code>).";
            } else {
                $log[] = "❌ No se pudo crear la carpeta '$name' (<code>$dir</code>).";
                continue;
            }
        }
        
        if (@chmod($dir, 0777)) {
            $log[] = "✅ Permisos corregidos (777) en '$name' (<code>$dir</code>).";
        } else {
            $log[] = "⚠️ No se pudo cambiar permisos en '$name' (posiblemente falta de privilegios del usuario web).";
        }
    }

    // Ejecutar inicialización y comprobación de base de datos
    $dbLogs = initializeDatabase();
    $log = array_merge($log, $dbLogs);

    return $log;
}

/**
 * Inicializa y valida la estructura completa de las tablas en la Base de Datos
 */
function initializeDatabase()
{
    $pdo = getPDO();
    if (!$pdo) return ["❌ No se pudo conectar a la base de datos para inicializar."];

    $log = [];

    // Tabla de auditoría y logs de respaldos
    $queries = [
        "system_backup_logs" => "CREATE TABLE IF NOT EXISTS `system_backup_logs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `backup_date` datetime NOT NULL,
            `status` varchar(20) NOT NULL DEFAULT 'EXITOSO',
            `duration_seconds` decimal(8,2) DEFAULT 0.00,
            `items_count` int(11) DEFAULT 0,
            `source_path` varchar(255) DEFAULT NULL,
            `target_path` varchar(255) DEFAULT NULL,
            `db_dump_size` varchar(50) DEFAULT NULL,
            `executed_by` varchar(100) DEFAULT NULL,
            `log_output` text DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        "roles" => "CREATE TABLE IF NOT EXISTS `roles` (`id` int(11) NOT NULL AUTO_INCREMENT, `name` varchar(50) NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `name` (`name`))",
        "users" => "CREATE TABLE IF NOT EXISTS `users` (`id` int(11) NOT NULL AUTO_INCREMENT, `username` varchar(100) NOT NULL, `password` varchar(255) NOT NULL, `role_id` int(11) NOT NULL, `created_at` datetime DEFAULT current_timestamp(), PRIMARY KEY (`id`), UNIQUE KEY `username` (`username`), CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`))",
        "asset_sequence" => "CREATE TABLE IF NOT EXISTS `asset_sequence` (`id` int(11) NOT NULL AUTO_INCREMENT, `prefix` varchar(10) NOT NULL DEFAULT 'AE', `last_id` int(11) NOT NULL DEFAULT 0, PRIMARY KEY (`id`))",
        "sheet_configs" => "CREATE TABLE IF NOT EXISTS `sheet_configs` (`id` int(11) NOT NULL AUTO_INCREMENT, `sheet_name` varchar(255) NOT NULL, `table_name` varchar(255) NOT NULL, `unique_columns` text DEFAULT NULL, `created_at` datetime DEFAULT current_timestamp(), PRIMARY KEY (`id`), UNIQUE KEY `sheet_name` (`sheet_name`), UNIQUE KEY `table_name` (`table_name`))",
        "user_sheet_permissions" => "CREATE TABLE IF NOT EXISTS `user_sheet_permissions` (`id` INT AUTO_INCREMENT PRIMARY KEY, `user_id` INT NOT NULL, `sheet_name` VARCHAR(100) NOT NULL, `can_view` TINYINT(1) DEFAULT 1, `can_edit` TINYINT(1) DEFAULT 0, `can_delete` TINYINT(1) DEFAULT 0, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, UNIQUE KEY (user_id, sheet_name))",
        "user_module_permissions" => "CREATE TABLE IF NOT EXISTS `user_module_permissions` (`id` INT AUTO_INCREMENT PRIMARY KEY, `user_id` INT NOT NULL, `module_name` VARCHAR(100) NOT NULL, `can_access` TINYINT(1) DEFAULT 1, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, UNIQUE KEY (user_id, module_name))",
        "import_logs" => "CREATE TABLE IF NOT EXISTS `import_logs` (`id` INT AUTO_INCREMENT PRIMARY KEY, `filename` VARCHAR(255), `table_name` VARCHAR(100), `total_rows` INT, `imported_rows` INT, `errors` TEXT, `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP)",
        "zabbix_api_config" => "CREATE TABLE IF NOT EXISTS `zabbix_api_config` (`id` INT AUTO_INCREMENT PRIMARY KEY, `url` VARCHAR(255) NOT NULL, `token` VARCHAR(255) NOT NULL, `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)",
        "snmp_communities" => "CREATE TABLE IF NOT EXISTS `snmp_communities` (`id` INT AUTO_INCREMENT PRIMARY KEY, `name` VARCHAR(100) NOT NULL, `community` VARCHAR(255) NOT NULL, `description` TEXT, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY (name))",
        "snmp_scan_results" => "CREATE TABLE IF NOT EXISTS `snmp_scan_results` (`id` INT AUTO_INCREMENT PRIMARY KEY, `ip` VARCHAR(50) NOT NULL, `table_source` VARCHAR(255) NOT NULL, `row_id` VARCHAR(255) NOT NULL, `community_ok` VARCHAR(255), `interfaces_up_json` LONGTEXT, `status` VARCHAR(20) DEFAULT 'PENDING', `last_success` DATETIME, `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY idx_ip_rel (ip, table_source, row_id))",
        "host_interfaces" => "CREATE TABLE IF NOT EXISTS `host_interfaces` (`id` INT AUTO_INCREMENT PRIMARY KEY, `hostid` VARCHAR(50) NOT NULL, `interface_index` VARCHAR(100), `interface_name` VARCHAR(255), `interface_type` VARCHAR(50), `alias` TEXT, `vlan` VARCHAR(50), `status` VARCHAR(20), `bits_received` BIGINT DEFAULT 0, `bits_sent` BIGINT DEFAULT 0, `connected_hostid` VARCHAR(50), `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY (hostid, interface_name))",
        "zabbix_cmdb_config" => "CREATE TABLE IF NOT EXISTS `zabbix_cmdb_config` (`table_name` VARCHAR(255) PRIMARY KEY, `is_enabled` TINYINT(1) DEFAULT 0)",
        "zabbix_keywords" => "CREATE TABLE IF NOT EXISTS `zabbix_keywords` (`id` INT AUTO_INCREMENT PRIMARY KEY, `keyword` VARCHAR(100) NOT NULL, `category` VARCHAR(50), UNIQUE KEY (keyword))",
        "zabbix_mappings" => "CREATE TABLE IF NOT EXISTS `zabbix_mappings` (`cmdb_table_name` VARCHAR(255) PRIMARY KEY, `hostname_template` VARCHAR(255), `visible_name_template` VARCHAR(255), `hostgroup_template` VARCHAR(255), `ip_field` VARCHAR(100), `snmp_community_field` VARCHAR(100), `template_name` VARCHAR(255), `inventory_fields_json` TEXT, `tags_json` TEXT, `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)",
        "images" => "CREATE TABLE IF NOT EXISTS `images` (`id` INT AUTO_INCREMENT PRIMARY KEY, `entity_type` VARCHAR(100), `entity_id` INT, `filepath` VARCHAR(255), `filename` VARCHAR(255), `uploaded_at` DATETIME DEFAULT CURRENT_TIMESTAMP)",
        "sheet_history" => "CREATE TABLE IF NOT EXISTS `sheet_history` (`id` INT AUTO_INCREMENT PRIMARY KEY, `table_name` VARCHAR(255), `row_id` INT, `action` VARCHAR(20), `changed_by` VARCHAR(255), `old_data` LONGTEXT, `new_data` LONGTEXT, `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP)",
        "zabbix_costs_rules" => "CREATE TABLE IF NOT EXISTS `zabbix_costs_rules` (`id` INT AUTO_INCREMENT PRIMARY KEY, `groupid` VARCHAR(50), `hourly_rate_capacity` DECIMAL(10,4), `hourly_rate_utilized` DECIMAL(10,4), `currency` VARCHAR(10) DEFAULT 'USD', `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP, `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)",
        "port_mappings" => "CREATE TABLE IF NOT EXISTS `port_mappings` (`id` INT AUTO_INCREMENT PRIMARY KEY, `source_component_id` INT NOT NULL, `target_component_id` INT NOT NULL, `cable_type` VARCHAR(50) DEFAULT 'UTP Cat6A', `color_code` VARCHAR(20) DEFAULT '#0000FF', `notes` TEXT, `connection_type` VARCHAR(50) DEFAULT 'network', `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "manual_portmap_surveys" => "CREATE TABLE IF NOT EXISTS `manual_portmap_surveys` (`id` INT AUTO_INCREMENT PRIMARY KEY, `client` VARCHAR(255), `location` VARCHAR(255), `area` VARCHAR(255), `device_name` VARCHAR(255), `device_label` VARCHAR(255), `device_type` VARCHAR(100), `ports_count` INT, `rack` VARCHAR(100), `ur_rack` VARCHAR(100), `ip_address` VARCHAR(100), `snmp_community` VARCHAR(255), `creation_date` DATE, `description` TEXT, `ports_data_json` LONGTEXT, `images_json` LONGTEXT, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        // CI Graph Tables
        "ci_attributes" => "CREATE TABLE IF NOT EXISTS `ci_attributes` (`id` INT AUTO_INCREMENT PRIMARY KEY, `name` VARCHAR(100) NOT NULL, `type` VARCHAR(50) NOT NULL DEFAULT 'string', `group_name` VARCHAR(100) DEFAULT 'General', `description` TEXT, `is_required` TINYINT(1) DEFAULT 0, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, `created_by` INT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "ci_categories" => "CREATE TABLE IF NOT EXISTS `ci_categories` (`id` INT(11) NOT NULL AUTO_INCREMENT, `parent_id` INT(11) DEFAULT NULL, `name` VARCHAR(100) NOT NULL, `description` TEXT DEFAULT NULL, `schema_json` JSON DEFAULT NULL, `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, `created_by` INT DEFAULT NULL, `icon` VARCHAR(50) DEFAULT 'fa-cube', PRIMARY KEY (`id`), FOREIGN KEY (`parent_id`) REFERENCES `ci_categories`(`id`) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "ci_instances" => "CREATE TABLE IF NOT EXISTS `ci_instances` (`id` INT(11) NOT NULL AUTO_INCREMENT, `category_id` INT(11) NOT NULL, `hostname` VARCHAR(255) NOT NULL, `ip_address` VARCHAR(50) DEFAULT NULL, `source` ENUM('manual', 'zabbix') DEFAULT 'manual', `zabbix_host_id` VARCHAR(100) DEFAULT NULL, `attributes_json` JSON DEFAULT NULL, `status` ENUM('Planificación', 'Activo', 'Mantenimiento', 'Retirado') DEFAULT 'Activo', `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, `description` TEXT DEFAULT NULL, `created_by` INT DEFAULT NULL, PRIMARY KEY (`id`), FOREIGN KEY (`category_id`) REFERENCES `ci_categories`(`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "ci_components" => "CREATE TABLE IF NOT EXISTS `ci_components` (`id` INT(11) NOT NULL AUTO_INCREMENT, `parent_ci_id` INT(11) NOT NULL, `name` VARCHAR(255) NOT NULL, `attributes_json` JSON DEFAULT NULL, `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`), FOREIGN KEY (`parent_ci_id`) REFERENCES `ci_instances`(`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "ci_relationships" => "CREATE TABLE IF NOT EXISTS `ci_relationships` (`id` INT(11) NOT NULL AUTO_INCREMENT, `source_type` VARCHAR(50) NOT NULL, `source_id` INT(11) NOT NULL, `target_type` VARCHAR(50) NOT NULL, `target_id` INT(11) NOT NULL, `relation_type` VARCHAR(50) NOT NULL, `impact` VARCHAR(50) DEFAULT 'Desconocido', `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`), UNIQUE KEY `idx_relation` (`source_type`, `source_id`, `target_type`, `target_id`, `relation_type`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Datacenter Tables
        "dc_rooms" => "CREATE TABLE IF NOT EXISTS `dc_rooms` (`id` INT AUTO_INCREMENT PRIMARY KEY, `name` VARCHAR(255) NOT NULL, `location_detail` VARCHAR(255) DEFAULT NULL, `width_meters` DECIMAL(10,2) DEFAULT 6.00, `length_meters` DECIMAL(10,2) DEFAULT 6.00, `tile_size` DECIMAL(4,2) DEFAULT 0.60, `floor_height_meters` DECIMAL(10,2) DEFAULT NULL, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "dc_racks" => "CREATE TABLE IF NOT EXISTS `dc_racks` (`id` INT AUTO_INCREMENT PRIMARY KEY, `room_id` INT NOT NULL, `name` VARCHAR(255) NOT NULL, `total_u` INT DEFAULT 42, `grid_x` INT DEFAULT 0, `grid_y` INT DEFAULT 0, `width_tiles` INT DEFAULT 1, `depth_tiles` INT DEFAULT 2, `rotation` INT NOT NULL DEFAULT 0, `numbering_dir` ENUM('UP','DOWN') NOT NULL DEFAULT 'DOWN', `description` TEXT DEFAULT NULL, `z_index` INT DEFAULT NULL, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "dc_rack_devices" => "CREATE TABLE IF NOT EXISTS `dc_rack_devices` (`id` INT AUTO_INCREMENT PRIMARY KEY, `rack_id` INT NOT NULL, `name` VARCHAR(255) NOT NULL, `start_u` INT NOT NULL, `height_u` INT NOT NULL, `orientation` VARCHAR(50) DEFAULT 'front', `cmdb_reference` VARCHAR(255) DEFAULT NULL, `details_json` TEXT DEFAULT NULL, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "dc_floor_layers" => "CREATE TABLE IF NOT EXISTS `dc_floor_layers` (`id` INT AUTO_INCREMENT PRIMARY KEY, `name` VARCHAR(255) NOT NULL, `z_index` INT NOT NULL DEFAULT 10) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "dc_floor_items" => "CREATE TABLE IF NOT EXISTS `dc_floor_items` (`id` INT AUTO_INCREMENT PRIMARY KEY, `room_id` INT NOT NULL, `name` VARCHAR(255) NOT NULL, `type` VARCHAR(50) NOT NULL, `layer_id` INT DEFAULT NULL, `grid_x` INT DEFAULT 0, `grid_y` INT DEFAULT 0, `width_tiles` INT DEFAULT 1, `depth_tiles` INT DEFAULT 1, `height_meters` DECIMAL(10,2) DEFAULT 0.00, `rotation` INT NOT NULL DEFAULT 0, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // GitLab & GitHub Repository Tables
        "gitlab_files" => "CREATE TABLE IF NOT EXISTS `gitlab_files` (`id` INT AUTO_INCREMENT PRIMARY KEY, `filename` VARCHAR(255) NOT NULL UNIQUE, `file_type` VARCHAR(50) DEFAULT 'txt', `description_md` LONGTEXT NULL, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "gitlab_versions" => "CREATE TABLE IF NOT EXISTS `gitlab_versions` (`id` INT AUTO_INCREMENT PRIMARY KEY, `file_id` INT NOT NULL, `version_number` INT NOT NULL, `content` LONGTEXT, `change_summary` VARCHAR(255) NULL, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (file_id) REFERENCES gitlab_files(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "gitlab_git_logs" => "CREATE TABLE IF NOT EXISTS `gitlab_git_logs` (`id` INT AUTO_INCREMENT PRIMARY KEY, `commit_hash` VARCHAR(100) NULL, `filename` VARCHAR(255) NULL, `commit_message` TEXT NULL, `git_output` LONGTEXT NULL, `status` VARCHAR(20) DEFAULT 'success', `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // Bóveda de Contraseñas
        "password_entries" => "CREATE TABLE IF NOT EXISTS `password_entries` (`id` INT(11) NOT NULL AUTO_INCREMENT, `name` VARCHAR(255) NOT NULL, `url` VARCHAR(1000) NOT NULL, `username` VARCHAR(255) NOT NULL, `password` VARCHAR(255) NOT NULL, `username_sec` VARCHAR(255) DEFAULT NULL, `password_sec` VARCHAR(255) DEFAULT NULL, `observations` TEXT DEFAULT NULL, `tags` VARCHAR(255) DEFAULT NULL, `screenshot_path` VARCHAR(255) DEFAULT NULL, `created_by` INT(11) NOT NULL, `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        "password_history" => "CREATE TABLE IF NOT EXISTS `password_history` (`id` INT(11) NOT NULL AUTO_INCREMENT, `password_id` INT(11) NOT NULL, `field_changed` VARCHAR(50) NOT NULL, `old_value` TEXT DEFAULT NULL, `new_value` TEXT DEFAULT NULL, `changed_by` INT(11) NOT NULL, `changed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        "password_vault_settings" => "CREATE TABLE IF NOT EXISTS `password_vault_settings` (`id` INT(11) NOT NULL AUTO_INCREMENT, `setting_key` VARCHAR(100) NOT NULL, `setting_value` TEXT DEFAULT NULL, PRIMARY KEY (`id`), UNIQUE KEY `setting_key` (`setting_key`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        // Diagramas y Modelado Visual
        "visio_diagrams" => "CREATE TABLE IF NOT EXISTS `visio_diagrams` (`id` INT(11) NOT NULL AUTO_INCREMENT, `title` VARCHAR(255) NOT NULL, `description` TEXT DEFAULT NULL, `xml_content` LONGTEXT NOT NULL, `filename_original` VARCHAR(255) DEFAULT NULL, `image_data` LONGTEXT DEFAULT NULL, `ci_instance_id` INT(11) DEFAULT NULL, `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP, `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        "visio_diagram_history" => "CREATE TABLE IF NOT EXISTS `visio_diagram_history` (`id` INT(11) NOT NULL AUTO_INCREMENT, `diagram_id` INT(11) NOT NULL, `xml_content` LONGTEXT NOT NULL, `comment` VARCHAR(255) DEFAULT NULL, `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        "bpmn_diagrams" => "CREATE TABLE IF NOT EXISTS `bpmn_diagrams` (`id` INT(11) NOT NULL AUTO_INCREMENT, `title` VARCHAR(255) NOT NULL, `description` TEXT DEFAULT NULL, `xml_content` LONGTEXT NOT NULL, `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP, `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        "bpmn_diagram_history" => "CREATE TABLE IF NOT EXISTS `bpmn_diagram_history` (`id` INT(11) NOT NULL AUTO_INCREMENT, `diagram_id` INT(11) NOT NULL, `xml_content` LONGTEXT NOT NULL, `comment` VARCHAR(255) DEFAULT NULL, `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        "mermaid_flows" => "CREATE TABLE IF NOT EXISTS `mermaid_flows` (`id` INT(11) NOT NULL AUTO_INCREMENT, `title` VARCHAR(255) NOT NULL, `description` TEXT DEFAULT NULL, `code` TEXT NOT NULL, `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP, `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        "mermaid_flow_history" => "CREATE TABLE IF NOT EXISTS `mermaid_flow_history` (`id` INT(11) NOT NULL AUTO_INCREMENT, `flow_id` INT(11) NOT NULL, `code` TEXT NOT NULL, `comment` VARCHAR(255) DEFAULT NULL, `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        // Proyectos
        "projects" => "CREATE TABLE IF NOT EXISTS `projects` (`id` INT(11) NOT NULL AUTO_INCREMENT, `code` VARCHAR(50) NOT NULL, `name` VARCHAR(255) NOT NULL, `client_ci_id` INT(11) DEFAULT NULL, `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00, `start_date` DATE NOT NULL, `end_date` DATE NOT NULL, `execution_date` DATE DEFAULT NULL, `assigned_personnel` TEXT DEFAULT NULL, `work_type` VARCHAR(100) NOT NULL DEFAULT 'horas normales', `working_days` VARCHAR(255) NOT NULL DEFAULT 'Lunes,Martes,Miércoles,Jueves,Viernes', `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (`id`), UNIQUE KEY `code` (`code`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        "project_milestones" => "CREATE TABLE IF NOT EXISTS `project_milestones` (`id` INT(11) NOT NULL AUTO_INCREMENT, `project_id` INT(11) NOT NULL, `name` VARCHAR(255) NOT NULL, `due_date` DATE NOT NULL, `status` VARCHAR(50) DEFAULT 'Pendiente', PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        "project_tasks" => "CREATE TABLE IF NOT EXISTS `project_tasks` (`id` INT(11) NOT NULL AUTO_INCREMENT, `project_id` INT(11) NOT NULL, `title` VARCHAR(255) NOT NULL, `status` VARCHAR(50) DEFAULT 'Pendiente', PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        // Cotizador
        "cotizador_cotizaciones" => "CREATE TABLE IF NOT EXISTS `cotizador_cotizaciones` (`id` INT(11) NOT NULL AUTO_INCREMENT, `parent_id` INT(11) DEFAULT NULL, `version` INT(11) DEFAULT 1, `cliente` VARCHAR(255) NOT NULL, `contrato` VARCHAR(255) DEFAULT '', `fecha` DATE NOT NULL, `estado` VARCHAR(50) DEFAULT 'Borrador', `aprobado_por` VARCHAR(100) DEFAULT NULL, `aprobado_fecha` DATETIME DEFAULT NULL, `total_costo` DECIMAL(12,2) DEFAULT 0.00, `total_precio` DECIMAL(12,2) DEFAULT 0.00, `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        // FEMSA
        "femsa_requirements" => "CREATE TABLE IF NOT EXISTS `femsa_requirements` (`id` INT(11) NOT NULL AUTO_INCREMENT, `ticket_code` VARCHAR(50) NOT NULL, `emission_date` DATE NOT NULL, `femsa_requester` VARCHAR(150) NOT NULL, `sonda_analyst` VARCHAR(150) NOT NULL DEFAULT 'Marco Vizcaíno / Recurso en Sitio', `activity_type` ENUM('Soporte','Incidente','Automatización') NOT NULL DEFAULT 'Soporte', `work_description` TEXT NOT NULL, `status` ENUM('Borrador','En Proceso','Entregado','Aprobado','Finalizado','Cancelado') DEFAULT 'Borrador', `current_stage` INT(11) NOT NULL DEFAULT 1, `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        "femsa_bitacora" => "CREATE TABLE IF NOT EXISTS `femsa_bitacora` (`id` INT(11) NOT NULL AUTO_INCREMENT, `requirement_id` INT(11) NOT NULL, `fecha` DATE NOT NULL, `tema` VARCHAR(255) NOT NULL, `descripcion` TEXT NOT NULL, `created_by` INT(11) DEFAULT NULL, `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    ];

    foreach ($queries as $name => $sql) {
        try {
            $pdo->exec($sql);
            
            // Verificaciones puntuales de columnas
            if ($name === 'snmp_scan_results') {
                $cols = $pdo->query("DESCRIBE `$name`")->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array('interfaces_up_json', $cols)) {
                    $pdo->exec("ALTER TABLE `$name` ADD COLUMN interfaces_up_json LONGTEXT AFTER row_id");
                    $log[] = "✅ Columna 'interfaces_up_json' añadida a $name.";
                }
                if (!in_array('status', $cols)) {
                    $pdo->exec("ALTER TABLE `$name` ADD COLUMN status VARCHAR(20) DEFAULT 'PENDING' AFTER interfaces_up_json");
                    $log[] = "✅ Columna 'status' añadida a $name.";
                }
                if (!in_array('community_ok', $cols)) {
                    $pdo->exec("ALTER TABLE `$name` ADD COLUMN community_ok VARCHAR(255) AFTER ip");
                    $log[] = "✅ Columna 'community_ok' añadida a $name.";
                }
            }

            if ($name === 'host_interfaces') {
                $cols = $pdo->query("DESCRIBE `$name`")->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array('connected_hostid', $cols)) {
                    $pdo->exec("ALTER TABLE `$name` ADD COLUMN connected_hostid VARCHAR(50) AFTER bits_sent");
                    $log[] = "✅ Columna 'connected_hostid' añadida a $name.";
                }
                if (in_array('name', $cols) && !in_array('interface_name', $cols)) {
                    $pdo->exec("ALTER TABLE `$name` CHANGE COLUMN name interface_name VARCHAR(255)");
                    $log[] = "✅ Columna 'name' renombrada a 'interface_name' en $name.";
                } elseif (!in_array('interface_name', $cols)) {
                    $pdo->exec("ALTER TABLE `$name` ADD COLUMN interface_name VARCHAR(255) AFTER interface_index");
                    $log[] = "✅ Columna 'interface_name' añadida a $name.";
                }
            }

            if ($name === 'dc_rooms') {
                $cols = $pdo->query("DESCRIBE `$name`")->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array('floor_height_meters', $cols)) {
                    $pdo->exec("ALTER TABLE `$name` ADD COLUMN floor_height_meters DECIMAL(10,2) DEFAULT NULL AFTER tile_size");
                    $log[] = "✅ Columna 'floor_height_meters' añadida a $name.";
                }
            }

            if ($name === 'dc_racks') {
                $cols = $pdo->query("DESCRIBE `$name`")->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array('rotation', $cols)) {
                    $pdo->exec("ALTER TABLE `$name` ADD COLUMN rotation INT NOT NULL DEFAULT 0 AFTER depth_tiles");
                    $log[] = "✅ Columna 'rotation' añadida a $name.";
                }
                if (!in_array('z_index', $cols)) {
                    $pdo->exec("ALTER TABLE `$name` ADD COLUMN z_index INT DEFAULT NULL AFTER description");
                    $log[] = "✅ Columna 'z_index' añadida a $name.";
                }
            }

            if ($name === 'dc_floor_items') {
                $cols = $pdo->query("DESCRIBE `$name`")->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array('height_meters', $cols)) {
                    $pdo->exec("ALTER TABLE `$name` ADD COLUMN height_meters DECIMAL(10,2) DEFAULT 0.00 AFTER depth_tiles");
                    $log[] = "✅ Columna 'height_meters' añadida a $name.";
                }
                if (!in_array('rotation', $cols)) {
                    $pdo->exec("ALTER TABLE `$name` ADD COLUMN rotation INT NOT NULL DEFAULT 0 AFTER height_meters");
                    $log[] = "✅ Columna 'rotation' añadida a $name.";
                }
            }

            if ($name === 'ci_categories') {
                $cols = $pdo->query("DESCRIBE `$name`")->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array('icon', $cols)) {
                    $pdo->exec("ALTER TABLE `$name` ADD COLUMN icon VARCHAR(50) DEFAULT 'fa-cube' AFTER description");
                    $log[] = "✅ Columna 'icon' añadida a $name.";
                }
            }
        } catch (Exception $e) {
            $log[] = "⚠️ Advertencia en $name: " . $e->getMessage();
        }
    }

    // Datos base iniciales (Seeds)
    try {
        $pdo->exec("INSERT IGNORE INTO roles (id, name) VALUES (1, 'SUPER_ADMIN'), (2, 'ADMIN'), (3, 'USER')");
        
        $countUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ($countUsers == 0) {
            $pass = password_hash('admin123', PASSWORD_DEFAULT);
            $pdo->exec("INSERT INTO users (username, password, role_id) VALUES ('admin', '$pass', 1)");
            $log[] = "👤 Usuario inicial 'admin' creado (Clave: admin123).";
        }
        
        $countLayers = $pdo->query("SELECT COUNT(*) FROM dc_floor_layers")->fetchColumn();
        if ($countLayers == 0) {
            $pdo->exec("INSERT INTO dc_floor_layers (name, z_index) VALUES 
                ('Piso Perforado', 1), 
                ('Aire Acondicionado', 5), 
                ('Racks', 10), 
                ('UPS', 11), 
                ('Escalerillas', 20)");
            $log[] = "✅ Capas de piso por defecto (dc_floor_layers) insertadas.";
        }
        
        $log[] = "✅ Estructura y datos base de la Base de Datos confirmados.";
    } catch (Exception $e) {
        $log[] = "⚠️ Error al insertar datos base: " . $e->getMessage();
    }

    return $log;
}

/**
 * Genera sugerencias de comandos de terminal para resolver dependencias faltantes
 */
function getTerminalSuggestions($audit)
{
    $cmds = [];

    // Extensiones faltantes
    $missing_exts = [];
    foreach ($audit['extensions'] as $ext => $info) {
        if (!$info['loaded']) {
            if ($ext === 'pdo_mysql') continue;
            $missing_exts[] = "php-$ext";
        }
    }
    if (!empty($missing_exts)) {
        $cmds[] = "# Instalación de extensiones PHP faltantes:";
        $cmds[] = "sudo apt-get update && sudo apt-get install -y " . implode(' ', $missing_exts) . " && sudo systemctl restart apache2";
    }

    // Herramientas CLI faltantes
    $missing_cli = [];
    if (isset($audit['cli_tools'])) {
        foreach ($audit['cli_tools'] as $tool => $info) {
            if (!$info['exists']) {
                if (in_array($tool, ['snmptranslate', 'snmpwalk', 'snmpget'])) {
                    $missing_cli['snmp'] = 'snmp snmp-mibs-downloader';
                } elseif ($tool === 'rsync') {
                    $missing_cli['rsync'] = 'rsync';
                } elseif ($tool === 'mysqldump') {
                    $missing_cli['mysql'] = 'mariadb-client';
                } elseif ($tool === 'git') {
                    $missing_cli['git'] = 'git';
                }
            }
        }
    }
    if (!empty($missing_cli)) {
        $cmds[] = "# Instalación de utilidades del sistema requeridas:";
        $cmds[] = "sudo apt-get update && sudo apt-get install -y " . implode(' ', array_values($missing_cli));
    }

    // Permisos de carpetas
    $broken_dirs = [];
    foreach ($audit['directories'] as $name => $info) {
        if (!$info['writable']) $broken_dirs[] = $info['path'];
    }
    if (!empty($broken_dirs)) {
        $cmds[] = "# Corrección integral de permisos y propietarios en directorios del sistema:";
        $cmds[] = "sudo chown -R www-data:www-data " . implode(' ', array_map('escapeshellarg', $broken_dirs));
        $cmds[] = "sudo chmod -R 777 " . implode(' ', array_map('escapeshellarg', $broken_dirs));
    }

    return $cmds;
}

/**
 * Realiza la copia completa y sincronización del proyecto desde PREPODUCCION hacia BACK
 * Incluye volcado integral de la base de datos MySQL / MariaDB
 * Registra fecha y estado final de forma persistente
 */
function backupPreproduccionToBack()
{
    $source = '/var/www/html/PROYECTOSONDA/PREPODUCCION';
    $target = '/var/www/html/PROYECTOSONDA/BACK';
    $log = [];
    $startTime = microtime(true);
    $backupDate = date('Y-m-d H:i:s');
    $executedBy = 'admin';
    if (function_exists('current_user')) {
        $u = current_user();
        if (!empty($u['username'])) {
            $executedBy = $u['username'];
        }
    }

    if (!is_dir($source)) {
        $errorMsg = "❌ El directorio de origen '$source' no existe.";
        return [$errorMsg];
    }

    if (!is_dir($target)) {
        @mkdir($target, 0777, true);
        @chmod($target, 0777);
    }

    $dbDumpIncluded = false;
    $dbDumpSizeFormatted = '0 MB';
    $dumpFile = STORAGE_DIR . '/backups/CMDBVilaseca2_latest.sql';
    
    // 1. Volcado de la Base de Datos
    if (defined('DB_CONFIG')) {
        $db = DB_CONFIG['database'];
        $u = DB_CONFIG['user'];
        $p = DB_CONFIG['password'];
        $h = DB_CONFIG['host'];
        
        $backupDir = dirname($dumpFile);
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0777, true);
            @chmod($backupDir, 0777);
        }

        $dumpCmd = "mysqldump -h " . escapeshellarg($h) . " -u " . escapeshellarg($u) . " -p" . escapeshellarg($p) . " " . escapeshellarg($db) . " > " . escapeshellarg($dumpFile) . " 2>&1";
        $dumpOutput = [];
        $dumpCode = 0;
        exec($dumpCmd, $dumpOutput, $dumpCode);

        if ($dumpCode === 0 && file_exists($dumpFile)) {
            $dbDumpIncluded = true;
            $dbSizeMb = round(filesize($dumpFile) / 1024 / 1024, 2);
            $dbDumpSizeFormatted = $dbSizeMb . ' MB';
            $log[] = "💾 <strong>VOLCADO DE BASE DE DATOS EXITOSO:</strong> Exportado esquema y datos completos de <code>$db</code> ($dbDumpSizeFormatted).";
        } else {
            $log[] = "⚠️ No se pudo generar volcado de base de datos automático: " . implode(' ', $dumpOutput);
        }
    }

    // 2. Sincronización rsync PREPODUCCION -> BACK
    $cmd = "rsync -a --delete --exclude='storage/sessions_fix/*' " . escapeshellarg($source . '/') . " " . escapeshellarg($target . '/') . " 2>&1";
    $output = [];
    $returnCode = 0;
    exec($cmd, $output, $returnCode);

    if ($returnCode !== 0) {
        // Fallback usando cp -rf
        $cmdCp = "cp -rf " . escapeshellarg($source) . "/. " . escapeshellarg($target) . "/ 2>&1";
        $output = [];
        exec($cmdCp, $output, $returnCode);
    }

    // Copiar también el dump directamente a la raíz de BACK para redundancia
    if ($dbDumpIncluded && file_exists($dumpFile)) {
        @copy($dumpFile, $target . '/CMDBVilaseca2_latest.sql');
    }

    // Aplicar permisos 777 a la carpeta de destino
    @chmod($target, 0777);

    // Contar elementos respaldados en el destino
    $itemCount = 0;
    if (is_dir($target)) {
        try {
            $fi = new FilesystemIterator($target, FilesystemIterator::SKIP_DOTS);
            $itemCount = iterator_count($fi);
        } catch (Exception $e) {}
    }

    $duration = round(microtime(true) - $startTime, 2);
    $statusFinal = ($returnCode === 0) ? 'EXITOSO' : 'FALLIDO';
    $statusCode = ($returnCode === 0) ? 'success' : 'danger';

    // 3. Persistir metadata en archivo JSON en PREPODUCCION y en BACK
    $meta = [
        'last_backup_date' => $backupDate,
        'timestamp' => time(),
        'status' => $statusFinal,
        'status_code' => $statusCode,
        'duration_seconds' => $duration,
        'items_count' => $itemCount,
        'source' => $source,
        'target' => $target,
        'db_dump_included' => $dbDumpIncluded,
        'db_dump_size' => $dbDumpSizeFormatted,
        'executed_by' => $executedBy,
        'message' => ($statusFinal === 'EXITOSO') 
            ? "Copia total de PREPODUCCION a BACK completada con éxito ($itemCount elementos, Base de Datos $dbDumpSizeFormatted en {$duration}s)."
            : "La copia presentó errores durante la sincronización rsync/cp."
    ];

    $metaJson = json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    @file_put_contents(STORAGE_DIR . '/backup_status.json', $metaJson);
    @file_put_contents($target . '/.backup_status.json', $metaJson);
    @chmod(STORAGE_DIR . '/backup_status.json', 0777);
    @chmod($target . '/.backup_status.json', 0777);

    // 4. Registrar en la base de datos (system_backup_logs)
    try {
        $pdo = getPDO();
        if ($pdo) {
            // Asegurar que la tabla exista
            $pdo->exec("CREATE TABLE IF NOT EXISTS `system_backup_logs` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `backup_date` datetime NOT NULL,
                `status` varchar(20) NOT NULL DEFAULT 'EXITOSO',
                `duration_seconds` decimal(8,2) DEFAULT 0.00,
                `items_count` int(11) DEFAULT 0,
                `source_path` varchar(255) DEFAULT NULL,
                `target_path` varchar(255) DEFAULT NULL,
                `db_dump_size` varchar(50) DEFAULT NULL,
                `executed_by` varchar(100) DEFAULT NULL,
                `log_output` text DEFAULT NULL,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

            $ins = $pdo->prepare("INSERT INTO system_backup_logs (backup_date, status, duration_seconds, items_count, source_path, target_path, db_dump_size, executed_by, log_output) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([
                $backupDate,
                $statusFinal,
                $duration,
                $itemCount,
                $source,
                $target,
                $dbDumpSizeFormatted,
                $executedBy,
                implode("\n", $log)
            ]);
        }
    } catch (Exception $e) {
        // Registro en log
    }

    if ($statusFinal === 'EXITOSO') {
        $log[] = "📦 <strong>RESPALDO A BACK COMPLETADO EXITOSAMENTE</strong>";
        $log[] = "🕒 <strong>Fecha Última del Respaldo:</strong> <code>$backupDate</code>";
        $log[] = "🏁 <strong>Estado Final:</strong> <span class='badge badge-success px-2 py-1'>EXITOSO</span>";
        $log[] = "📁 <strong>Directorio Destino:</strong> <code>$target</code> ($itemCount carpetas y módulos maestros sincronizados).";
        $log[] = "⏱️ <strong>Tiempo de Ejecución:</strong> {$duration} segundos.";
        $log[] = "👤 <strong>Ejecutado por:</strong> $executedBy";
    } else {
        $log[] = "❌ <strong>RESPALDO A BACK CON ERRORES:</strong> Código de salida $returnCode. " . implode(' ', $output);
    }

    return $log;
}

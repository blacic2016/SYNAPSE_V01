<?php
/**
 * Backend REST API Autónomo: Módulo CMDB_SONDA
 * Ubicación: /var/www/html/PROYECTOSONDA/PREPODUCCION/CMDBPRnew/public/cmdb_sonda/api.php
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/permissions_helper.php';

// Verificación de autenticación y autorización
$user = current_user();
if (!$user) {
    echo json_encode(['success' => false, 'error' => 'No autorizado. Debe iniciar sesión.']);
    exit;
}

if (!has_role('SUPER_ADMIN') && !has_module_access('cmdb_sonda')) {
    echo json_encode(['success' => false, 'error' => 'Acceso denegado al módulo CMDB_SONDA']);
    exit;
}

$pdo = getPDO();
if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'No se pudo conectar a la base de datos.']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$current_username = $user['username'] ?? 'usuario_sonda';

// Helper para responder JSON
function json_response($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Helper para registrar auditoría
function log_audit($pdo, $ci_id, $action, $username, $details = []) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $pdo->prepare("INSERT INTO cmdb_sonda_audit_logs (ci_id, action, user_name, details_json, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$ci_id, $action, $username, json_encode($details, JSON_UNESCAPED_UNICODE), $ip]);
    } catch (Exception $e) {}
}

// Helper para guardar imagen física y registrar en DB
function handle_save_image($pdo, $ci_id, $file, $ubicacion_foto, $tags, $fecha_creacion_foto, $observaciones, $current_username) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No se recibió un archivo válido de imagen o hubo un error en la transmisión.');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];
    if (!in_array($ext, $allowedExts)) {
        throw new Exception("Formato de imagen no permitido ($ext). Formatos aceptados: JPG, PNG, WEBP, GIF.");
    }

    $uploadDir = __DIR__ . '/../uploads/cmdb_sonda';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }

    $newFileName = 'ci_' . $ci_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destination = $uploadDir . '/' . $newFileName;

    if (!@move_uploaded_file($file['tmp_name'], $destination)) {
        if (!@copy($file['tmp_name'], $destination)) {
            throw new Exception('Error al almacenar la imagen en el servidor de archivos.');
        }
        @unlink($file['tmp_name']);
    }

    $relativePath = 'uploads/cmdb_sonda/' . $newFileName;
    $stmt = $pdo->prepare("
        INSERT INTO cmdb_sonda_images (ci_id, file_path, file_name, file_size, ubicacion_foto, tags, fecha_creacion_foto, observaciones, uploaded_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $ci_id,
        $relativePath,
        $file['name'],
        $file['size'],
        trim($ubicacion_foto ?: 'General'),
        trim($tags),
        trim($fecha_creacion_foto ?: date('Y-m-d')),
        trim($observaciones),
        $current_username
    ]);

    $newImgId = (int)$pdo->lastInsertId();
    log_audit($pdo, $ci_id, 'UPLOAD_IMAGE', $current_username, [
        'image_id' => $newImgId,
        'file_name' => $file['name'],
        'ubicacion' => $ubicacion_foto,
        'tags' => $tags
    ]);

    return [
        'image_id' => $newImgId,
        'file_path' => $relativePath
    ];
}

switch ($action) {

    // ====================================================================
    // 1. LISTADO DE CIs CON FILTROS AVANZADOS Y KPIs
    // ====================================================================
    case 'list_cis':
        try {
            $q = trim($_GET['q'] ?? '');
            $phase = trim($_GET['phase'] ?? 'all'); // all, phase1, phase2
            $support_status = trim($_GET['support_status'] ?? 'all'); // all, expired, due_30, due_60, active
            $cliente = trim($_GET['cliente'] ?? '');
            $tipo_ci = trim($_GET['tipo_ci'] ?? '');
            $ambiente = trim($_GET['ambiente'] ?? '');
            $criticidad = trim($_GET['criticidad'] ?? '');
            $monitoreado = trim($_GET['monitoreado'] ?? '');
            $service_id = isset($_GET['service_id']) && $_GET['service_id'] !== '' ? (int)$_GET['service_id'] : null;

            $where = [];
            $params = [];

            if ($q !== '') {
                $where[] = "(c.id_ci LIKE ? OR c.hostname_nombre LIKE ? OR c.ip_administracion LIKE ? OR c.fabricante LIKE ? OR c.modelo LIKE ? OR c.numero_serie LIKE ? OR c.servicio LIKE ? OR c.cliente LIKE ? OR c.sede_site LIKE ?)";
                $paramQ = '%' . $q . '%';
                for ($i = 0; $i < 9; $i++) {
                    $params[] = $paramQ;
                }
            }

            if ($cliente !== '') {
                $where[] = "c.cliente = ?";
                $params[] = $cliente;
            }

            $servicio = trim($_GET['servicio'] ?? '');
            if ($servicio !== '') {
                $where[] = "(c.servicio = ? OR s.nombre_servicio = ?)";
                $params[] = $servicio;
                $params[] = $servicio;
            }

            if ($tipo_ci !== '') {
                $where[] = "c.tipo_ci = ?";
                $params[] = $tipo_ci;
            }

            if ($ambiente !== '') {
                $where[] = "c.ambiente = ?";
                $params[] = $ambiente;
            }

            if ($criticidad !== '') {
                $where[] = "c.criticidad = ?";
                $params[] = $criticidad;
            }

            if ($monitoreado !== '') {
                $where[] = "c.monitoreado = ?";
                $params[] = $monitoreado;
            }

            if ($service_id !== null) {
                $where[] = "c.service_id = ?";
                $params[] = $service_id;
            }

            // Filtro por semáforo de soporte
            if ($support_status === 'expired') {
                $where[] = "c.dias_fin_soporte < 0";
            } elseif ($support_status === 'due_30') {
                $where[] = "c.dias_fin_soporte >= 0 AND c.dias_fin_soporte <= 30";
            } elseif ($support_status === 'due_60') {
                $where[] = "c.dias_fin_soporte > 30 AND c.dias_fin_soporte <= 60";
            } elseif ($support_status === 'active') {
                $where[] = "c.dias_fin_soporte > 60";
            }

            $whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

            $sql = "
                SELECT c.*, 
                       s.nombre_servicio as service_name,
                       s.criticidad_negocio as service_criticality,
                       (SELECT COUNT(*) FROM cmdb_sonda_relationships WHERE source_ci_id = c.id) as outgoing_relations_count,
                       (SELECT COUNT(*) FROM cmdb_sonda_relationships WHERE target_ci_id = c.id) as incoming_relations_count
                FROM cmdb_sonda_cis c
                LEFT JOIN cmdb_sonda_services s ON c.service_id = s.id
                $whereSql
                ORDER BY c.dias_fin_soporte ASC, c.hostname_nombre ASC
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $cis = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calcular métricas de completitud por cada CI
            foreach ($cis as &$ci) {
                // Cálculo de integridad Fase 1 (13 campos clave)
                $fase1_fields = [
                    $ci['id_ci'], $ci['hostname_nombre'], $ci['tipo_ci'], $ci['cliente'],
                    $ci['servicio'], $ci['sede_site'], $ci['ip_administracion'], $ci['ambiente'],
                    $ci['estado_ci'], $ci['criticidad'], $ci['monitoreado'], $ci['inicio_soporte'],
                    $ci['fin_soporte']
                ];
                $f1_completed = 0;
                foreach ($fase1_fields as $val) {
                    if ($val !== null && trim((string)$val) !== '') $f1_completed++;
                }
                $ci['fase1_score'] = round(($f1_completed / 13) * 100);
                $ci['fase1_complete'] = ($f1_completed === 13);

                // Cálculo de integridad Fase 2 (14 campos enriquecimiento)
                $fase2_fields = [
                    $ci['fabricante'], $ci['modelo'], $ci['numero_serie'], $ci['version_firmware_so'],
                    $ci['responsable_cliente'], $ci['contrato_proyecto'], $ci['propietario_tecnico'],
                    $ci['pais'], $ci['ciudad'], $ci['rack'], $ci['garantia_hasta'], $ci['licencia'],
                    $ci['fin_licencia'], $ci['fecha_eol']
                ];
                $f2_completed = 0;
                foreach ($fase2_fields as $val) {
                    if ($val !== null && trim((string)$val) !== '') $f2_completed++;
                }
                $ci['fase2_score'] = round(($f2_completed / 14) * 100);

                // Estado de soporte en texto y semáforo
                $days = (int)$ci['dias_fin_soporte'];
                if ($ci['fin_soporte'] === null) {
                    $ci['support_badge_class'] = 'secondary';
                    $ci['support_label'] = 'Sin Fecha';
                } elseif ($days < 0) {
                    $ci['support_badge_class'] = 'danger';
                    $ci['support_label'] = 'Vencido (' . abs($days) . ' d)';
                } elseif ($days <= 30) {
                    $ci['support_badge_class'] = 'warning';
                    $ci['support_label'] = 'Por Vencer (' . $days . ' d)';
                } elseif ($days <= 60) {
                    $ci['support_badge_class'] = 'info';
                    $ci['support_label'] = 'Próximo (' . $days . ' d)';
                } else {
                    $ci['support_badge_class'] = 'success';
                    $ci['support_label'] = 'Vigente (' . $days . ' d)';
                }
            }

            // KPIs globales calculados en base a toda la tabla
            $kpiStmt = $pdo->query("
                SELECT 
                    COUNT(*) as total_cis,
                    SUM(CASE WHEN dias_fin_soporte < 0 THEN 1 ELSE 0 END) as expired_support,
                    SUM(CASE WHEN dias_fin_soporte >= 0 AND dias_fin_soporte <= 30 THEN 1 ELSE 0 END) as due_soon_support,
                    SUM(CASE WHEN dias_fin_soporte > 60 THEN 1 ELSE 0 END) as active_support,
                    SUM(CASE WHEN monitoreado = 'Sí' THEN 1 ELSE 0 END) as monitored_cis,
                    (SELECT COUNT(*) FROM cmdb_sonda_services) as total_services,
                    (SELECT COUNT(*) FROM cmdb_sonda_relationships) as total_relationships
                FROM cmdb_sonda_cis
            ");
            $kpis = $kpiStmt->fetch(PDO::FETCH_ASSOC);

            // Porcentajes para dashboards
            $totalCis = (int)$kpis['total_cis'];
            $kpis['monitored_pct'] = $totalCis > 0 ? round(((int)$kpis['monitored_cis'] / $totalCis) * 100) : 0;
            
            // Promedio global de cumplimiento Fase 1
            $f1AllCount = 0;
            foreach ($cis as $ci) {
                if ($ci['fase1_complete']) $f1AllCount++;
            }
            $kpis['fase1_compliance_pct'] = count($cis) > 0 ? round(($f1AllCount / count($cis)) * 100) : 100;

            // Listado de clientes únicos para filtro primario
            $clientsStmt = $pdo->query("SELECT DISTINCT cliente FROM cmdb_sonda_cis WHERE cliente IS NOT NULL AND cliente != '' ORDER BY cliente ASC");
            $clientsList = $clientsStmt->fetchAll(PDO::FETCH_COLUMN);

            // Listado de servicios únicos (con cliente asociado) para filtro secundario en cascada
            $servicesStmt = $pdo->query("SELECT DISTINCT servicio as nombre_servicio, cliente FROM cmdb_sonda_cis WHERE servicio IS NOT NULL AND servicio != '' ORDER BY servicio ASC");
            $servicesList = $servicesStmt->fetchAll(PDO::FETCH_ASSOC);

            json_response([
                'success' => true,
                'cis' => $cis,
                'kpis' => $kpis,
                'clients' => $clientsList,
                'services' => $servicesList,
                'total_count' => count($cis)
            ]);

        } catch (Exception $e) {
            json_response(['success' => false, 'error' => 'Error al listar CIs: ' . $e->getMessage()], 500);
        }
        break;

    // ====================================================================
    // 2. DETALLE DE UN CI (FICHA TÉCNICA 360° Y DEPENDENCIAS)
    // ====================================================================
    case 'get_ci':
        try {
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) {
                json_response(['success' => false, 'error' => 'ID de CI inválido'], 400);
            }

            $stmt = $pdo->prepare("
                SELECT c.*, s.nombre_servicio as service_name, s.service_code, s.criticidad_negocio as service_criticality
                FROM cmdb_sonda_cis c
                LEFT JOIN cmdb_sonda_services s ON c.service_id = s.id
                WHERE c.id = ?
            ");
            $stmt->execute([$id]);
            $ci = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$ci) {
                json_response(['success' => false, 'error' => 'CI no encontrado'], 404);
            }

            // Dependencias salientes (CIs de los que este CI depende o a los que conecta)
            $stmtOut = $pdo->prepare("
                SELECT r.*, t.id_ci as target_id_ci, t.hostname_nombre as target_hostname, t.tipo_ci as target_tipo, t.ip_administracion as target_ip, t.criticidad as target_criticidad, t.estado_ci as target_estado
                FROM cmdb_sonda_relationships r
                JOIN cmdb_sonda_cis t ON r.target_ci_id = t.id
                WHERE r.source_ci_id = ?
            ");
            $stmtOut->execute([$id]);
            $ci['outgoing_relations'] = $stmtOut->fetchAll(PDO::FETCH_ASSOC);

            // Dependencias entrantes (CIs que dependen de este CI)
            $stmtIn = $pdo->prepare("
                SELECT r.*, s.id_ci as source_id_ci, s.hostname_nombre as source_hostname, s.tipo_ci as source_tipo, s.ip_administracion as source_ip, s.criticidad as source_criticidad, s.estado_ci as source_estado
                FROM cmdb_sonda_relationships r
                JOIN cmdb_sonda_cis s ON r.source_ci_id = s.id
                WHERE r.target_ci_id = ?
            ");
            $stmtIn->execute([$id]);
            $ci['incoming_relations'] = $stmtIn->fetchAll(PDO::FETCH_ASSOC);

            json_response(['success' => true, 'ci' => $ci]);

        } catch (Exception $e) {
            json_response(['success' => false, 'error' => 'Error al obtener CI: ' . $e->getMessage()], 500);
        }
        break;

    // ====================================================================
    // 3. GUARDAR / ACTUALIZAR CI (CON VALIDACIÓN DE FASE 1 Y RECÁLCULO)
    // ====================================================================
    case 'save_ci':
        try {
            $data = $_POST;
            if (empty($data)) {
                $rawInput = file_get_contents('php://input');
                $data = json_decode($rawInput, true) ?: [];
            }

            $id = isset($data['id']) && (int)$data['id'] > 0 ? (int)$data['id'] : null;

            // Validación de obligatorios Fase 1
            $requiredPhase1 = [
                'id_ci' => 'ID del CI',
                'hostname_nombre' => 'Hostname / Nombre',
                'tipo_ci' => 'Tipo de CI',
                'cliente' => 'Cliente',
                'servicio' => 'Servicio',
                'sede_site' => 'Sede / Site',
                'ip_administracion' => 'IP de Administración',
                'ambiente' => 'Ambiente',
                'estado_ci' => 'Estado del CI',
                'criticidad' => 'Criticidad',
                'monitoreado' => 'Monitoreado',
                'inicio_soporte' => 'Fecha Inicio Soporte',
                'fin_soporte' => 'Fecha Fin Soporte'
            ];

            $missing = [];
            foreach ($requiredPhase1 as $field => $label) {
                if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
                    $missing[] = $label;
                }
            }

            if (!empty($missing)) {
                json_response([
                    'success' => false,
                    'error' => 'Faltan campos obligatorios de Fase 1 (Core): ' . implode(', ', $missing)
                ], 422);
            }

            // Normalización y preparación de campos
            $id_ci = trim($data['id_ci']);
            $hostname_nombre = trim($data['hostname_nombre']);
            $tipo_ci = trim($data['tipo_ci']);
            $fabricante = trim($data['fabricante'] ?? '');
            $modelo = trim($data['modelo'] ?? '');
            $numero_serie = trim($data['numero_serie'] ?? '');
            $version_firmware_so = trim($data['version_firmware_so'] ?? '');

            $cliente = trim($data['cliente']);
            $servicio = trim($data['servicio']);
            $responsable_cliente = trim($data['responsable_cliente'] ?? '');
            $contrato_proyecto = trim($data['contrato_proyecto'] ?? '');
            $propietario_tecnico = trim($data['propietario_tecnico'] ?? '');
            $service_id = !empty($data['service_id']) ? (int)$data['service_id'] : null;

            $sede_site = trim($data['sede_site']);
            $pais = trim($data['pais'] ?? '');
            $ciudad = trim($data['ciudad'] ?? '');
            $rack = trim($data['rack'] ?? '');

            $ip_administracion = trim($data['ip_administracion']);
            $ambiente = trim($data['ambiente']);
            $estado_ci = trim($data['estado_ci']);
            $criticidad = trim($data['criticidad']);
            $monitoreado = trim($data['monitoreado']);

            $inicio_soporte = trim($data['inicio_soporte']);
            $fin_soporte = trim($data['fin_soporte']);
            $garantia_hasta = !empty($data['garantia_hasta']) ? trim($data['garantia_hasta']) : null;
            $licencia = trim($data['licencia'] ?? '');
            $fin_licencia = !empty($data['fin_licencia']) ? trim($data['fin_licencia']) : null;
            $fecha_eol = !empty($data['fecha_eol']) ? trim($data['fecha_eol']) : null;
            $fecha_eos = !empty($data['fecha_eos']) ? trim($data['fecha_eos']) : null;
            $notas_adicionales = trim($data['notas_adicionales'] ?? '');

            // Validar unicidad de id_ci
            if ($id) {
                $checkStmt = $pdo->prepare("SELECT id FROM cmdb_sonda_cis WHERE id_ci = ? AND id != ?");
                $checkStmt->execute([$id_ci, $id]);
            } else {
                $checkStmt = $pdo->prepare("SELECT id FROM cmdb_sonda_cis WHERE id_ci = ?");
                $checkStmt->execute([$id_ci]);
            }
            if ($checkStmt->fetch()) {
                json_response(['success' => false, 'error' => "El ID_CI '$id_ci' ya se encuentra registrado."], 409);
            }

            if ($id) {
                // UPDATE - Consultar estado previo del CI para auditar diferencias (Diff)
                $oldCiStmt = $pdo->prepare("SELECT * FROM cmdb_sonda_cis WHERE id = ?");
                $oldCiStmt->execute([$id]);
                $oldCi = $oldCiStmt->fetch(PDO::FETCH_ASSOC);

                if (!$oldCi) {
                    json_response(['success' => false, 'error' => 'El CI que intenta actualizar no existe.'], 404);
                }

                $sql = "UPDATE cmdb_sonda_cis SET 
                    id_ci = :id_ci,
                    hostname_nombre = :hostname_nombre,
                    tipo_ci = :tipo_ci,
                    fabricante = :fabricante,
                    modelo = :modelo,
                    numero_serie = :numero_serie,
                    version_firmware_so = :version_firmware_so,
                    cliente = :cliente,
                    servicio = :servicio,
                    responsable_cliente = :responsable_cliente,
                    contrato_proyecto = :contrato_proyecto,
                    propietario_tecnico = :propietario_tecnico,
                    service_id = :service_id,
                    sede_site = :sede_site,
                    pais = :pais,
                    ciudad = :ciudad,
                    rack = :rack,
                    ip_administracion = :ip_administracion,
                    ambiente = :ambiente,
                    estado_ci = :estado_ci,
                    criticidad = :criticidad,
                    monitoreado = :monitoreado,
                    inicio_soporte = :inicio_soporte,
                    fin_soporte = :fin_soporte,
                    dias_fin_soporte = DATEDIFF(:fin_soporte, CURDATE()),
                    garantia_hasta = :garantia_hasta,
                    licencia = :licencia,
                    fin_licencia = :fin_licencia,
                    fecha_eol = :fecha_eol,
                    fecha_eos = :fecha_eos,
                    notas_adicionales = :notas_adicionales
                WHERE id = :id";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':id_ci' => $id_ci,
                    ':hostname_nombre' => $hostname_nombre,
                    ':tipo_ci' => $tipo_ci,
                    ':fabricante' => $fabricante,
                    ':modelo' => $modelo,
                    ':numero_serie' => $numero_serie,
                    ':version_firmware_so' => $version_firmware_so,
                    ':cliente' => $cliente,
                    ':servicio' => $servicio,
                    ':responsable_cliente' => $responsable_cliente,
                    ':contrato_proyecto' => $contrato_proyecto,
                    ':propietario_tecnico' => $propietario_tecnico,
                    ':service_id' => $service_id,
                    ':sede_site' => $sede_site,
                    ':pais' => $pais,
                    ':ciudad' => $ciudad,
                    ':rack' => $rack,
                    ':ip_administracion' => $ip_administracion,
                    ':ambiente' => $ambiente,
                    ':estado_ci' => $estado_ci,
                    ':criticidad' => $criticidad,
                    ':monitoreado' => $monitoreado,
                    ':inicio_soporte' => $inicio_soporte,
                    ':fin_soporte' => $fin_soporte,
                    ':garantia_hasta' => $garantia_hasta,
                    ':licencia' => $licencia,
                    ':fin_licencia' => $fin_licencia,
                    ':fecha_eol' => $fecha_eol,
                    ':fecha_eos' => $fecha_eos,
                    ':notas_adicionales' => $notas_adicionales,
                    ':id' => $id
                ]);

                // Comparar diferencias campo por campo
                $fieldLabels = [
                    'id_ci' => 'ID del CI',
                    'hostname_nombre' => 'Hostname / Nombre',
                    'tipo_ci' => 'Tipo de CI',
                    'fabricante' => 'Fabricante',
                    'modelo' => 'Modelo',
                    'numero_serie' => 'Número de Serie',
                    'version_firmware_so' => 'Versión SO / Firmware',
                    'cliente' => 'Cliente',
                    'servicio' => 'Servicio',
                    'responsable_cliente' => 'Responsable Cliente',
                    'contrato_proyecto' => 'Contrato / Proyecto',
                    'propietario_tecnico' => 'Propietario Técnico',
                    'service_id' => 'ID Servicio Vinculado',
                    'sede_site' => 'Sede / Site',
                    'pais' => 'País',
                    'ciudad' => 'Ciudad',
                    'rack' => 'Rack / Bahía',
                    'ip_administracion' => 'IP de Administración',
                    'ambiente' => 'Ambiente',
                    'estado_ci' => 'Estado del CI',
                    'criticidad' => 'Criticidad',
                    'monitoreado' => 'Monitoreado',
                    'inicio_soporte' => 'Inicio Soporte',
                    'fin_soporte' => 'Fin Soporte',
                    'garantia_hasta' => 'Garantía Hasta',
                    'licencia' => 'Licencia',
                    'fin_licencia' => 'Fin Licencia',
                    'fecha_eol' => 'Fecha EOL',
                    'fecha_eos' => 'Fecha EOS',
                    'notas_adicionales' => 'Notas Adicionales'
                ];

                $newDataMap = [
                    'id_ci' => $id_ci,
                    'hostname_nombre' => $hostname_nombre,
                    'tipo_ci' => $tipo_ci,
                    'fabricante' => $fabricante,
                    'modelo' => $modelo,
                    'numero_serie' => $numero_serie,
                    'version_firmware_so' => $version_firmware_so,
                    'cliente' => $cliente,
                    'servicio' => $servicio,
                    'responsable_cliente' => $responsable_cliente,
                    'contrato_proyecto' => $contrato_proyecto,
                    'propietario_tecnico' => $propietario_tecnico,
                    'service_id' => $service_id !== null ? (string)$service_id : '',
                    'sede_site' => $sede_site,
                    'pais' => $pais,
                    'ciudad' => $ciudad,
                    'rack' => $rack,
                    'ip_administracion' => $ip_administracion,
                    'ambiente' => $ambiente,
                    'estado_ci' => $estado_ci,
                    'criticidad' => $criticidad,
                    'monitoreado' => $monitoreado,
                    'inicio_soporte' => $inicio_soporte,
                    'fin_soporte' => $fin_soporte,
                    'garantia_hasta' => $garantia_hasta ?: '',
                    'licencia' => $licencia,
                    'fin_licencia' => $fin_licencia ?: '',
                    'fecha_eol' => $fecha_eol ?: '',
                    'fecha_eos' => $fecha_eos ?: '',
                    'notas_adicionales' => $notas_adicionales
                ];

                $changes = [];
                foreach ($fieldLabels as $fKey => $fLabel) {
                    $oldVal = trim((string)($oldCi[$fKey] ?? ''));
                    $newVal = trim((string)($newDataMap[$fKey] ?? ''));
                    if ($oldVal !== $newVal) {
                        $changes[$fKey] = [
                            'label' => $fLabel,
                            'old' => $oldVal,
                            'new' => $newVal
                        ];
                    }
                }

                $imgMsg = '';
                $attached = $_FILES['ci_image'] ?? $_FILES['image'] ?? null;
                if ($attached && isset($attached['error']) && $attached['error'] === UPLOAD_ERR_OK) {
                    try {
                        handle_save_image(
                            $pdo,
                            $id,
                            $attached,
                            $_POST['ci_image_ubicacion'] ?? 'General',
                            $_POST['ci_image_tags'] ?? '',
                            $_POST['ci_image_fecha'] ?? date('Y-m-d'),
                            $_POST['ci_image_observaciones'] ?? '',
                            $current_username
                        );
                        $imgMsg = ' Evidencia fotográfica almacenada en el servidor.';
                    } catch (Exception $eImg) {
                        $imgMsg = ' (Nota imagen: ' . $eImg->getMessage() . ')';
                    }
                }

                $changedCount = count($changes);
                $changeSummary = $changedCount > 0
                    ? "Se modificaron $changedCount campos: " . implode(', ', array_slice(array_column($changes, 'label'), 0, 3)) . ($changedCount > 3 ? '...' : '')
                    : "Actualización sin cambios en los valores de atributos";

                log_audit($pdo, $id, 'UPDATE', $current_username, [
                    'id_ci' => $id_ci,
                    'hostname' => $hostname_nombre,
                    'changes_count' => $changedCount,
                    'summary' => $changeSummary,
                    'changes' => $changes
                ]);

                json_response(['success' => true, 'message' => "CI '$hostname_nombre' actualizado con éxito.$imgMsg", 'id' => $id]);

            } else {
                // INSERT
                $sql = "INSERT INTO cmdb_sonda_cis (
                    id_ci, hostname_nombre, tipo_ci, fabricante, modelo, numero_serie, version_firmware_so,
                    cliente, servicio, responsable_cliente, contrato_proyecto, propietario_tecnico, service_id,
                    sede_site, pais, ciudad, rack, ip_administracion, ambiente, estado_ci, criticidad, monitoreado,
                    inicio_soporte, fin_soporte, dias_fin_soporte, garantia_hasta, licencia, fin_licencia,
                    fecha_eol, fecha_eos, notas_adicionales, created_by
                ) VALUES (
                    :id_ci, :hostname_nombre, :tipo_ci, :fabricante, :modelo, :numero_serie, :version_firmware_so,
                    :cliente, :servicio, :responsable_cliente, :contrato_proyecto, :propietario_tecnico, :service_id,
                    :sede_site, :pais, :ciudad, :rack, :ip_administracion, :ambiente, :estado_ci, :criticidad, :monitoreado,
                    :inicio_soporte, :fin_soporte, DATEDIFF(:fin_soporte, CURDATE()), :garantia_hasta, :licencia, :fin_licencia,
                    :fecha_eol, :fecha_eos, :notas_adicionales, :created_by
                )";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':id_ci' => $id_ci,
                    ':hostname_nombre' => $hostname_nombre,
                    ':tipo_ci' => $tipo_ci,
                    ':fabricante' => $fabricante,
                    ':modelo' => $modelo,
                    ':numero_serie' => $numero_serie,
                    ':version_firmware_so' => $version_firmware_so,
                    ':cliente' => $cliente,
                    ':servicio' => $servicio,
                    ':responsable_cliente' => $responsable_cliente,
                    ':contrato_proyecto' => $contrato_proyecto,
                    ':propietario_tecnico' => $propietario_tecnico,
                    ':service_id' => $service_id,
                    ':sede_site' => $sede_site,
                    ':pais' => $pais,
                    ':ciudad' => $ciudad,
                    ':rack' => $rack,
                    ':ip_administracion' => $ip_administracion,
                    ':ambiente' => $ambiente,
                    ':estado_ci' => $estado_ci,
                    ':criticidad' => $criticidad,
                    ':monitoreado' => $monitoreado,
                    ':inicio_soporte' => $inicio_soporte,
                    ':fin_soporte' => $fin_soporte,
                    ':garantia_hasta' => $garantia_hasta,
                    ':licencia' => $licencia,
                    ':fin_licencia' => $fin_licencia,
                    ':fecha_eol' => $fecha_eol,
                    ':fecha_eos' => $fecha_eos,
                    ':notas_adicionales' => $notas_adicionales,
                    ':created_by' => $current_username
                ]);

                $newId = (int)$pdo->lastInsertId();

                $imgMsg = '';
                $attached = $_FILES['ci_image'] ?? $_FILES['image'] ?? null;
                if ($attached && isset($attached['error']) && $attached['error'] === UPLOAD_ERR_OK) {
                    try {
                        handle_save_image(
                            $pdo,
                            $newId,
                            $attached,
                            $_POST['ci_image_ubicacion'] ?? 'General',
                            $_POST['ci_image_tags'] ?? '',
                            $_POST['ci_image_fecha'] ?? date('Y-m-d'),
                            $_POST['ci_image_observaciones'] ?? '',
                            $current_username
                        );
                        $imgMsg = ' Evidencia fotográfica almacenada en el servidor.';
                    } catch (Exception $eImg) {
                        $imgMsg = ' (Nota imagen: ' . $eImg->getMessage() . ')';
                    }
                }

                log_audit($pdo, $newId, 'CREATE', $current_username, [
                    'id_ci' => $id_ci,
                    'hostname' => $hostname_nombre,
                    'tipo_ci' => $tipo_ci,
                    'cliente' => $cliente,
                    'servicio' => $servicio,
                    'ip_administracion' => $ip_administracion,
                    'ambiente' => $ambiente,
                    'estado_ci' => $estado_ci,
                    'criticidad' => $criticidad,
                    'sede_site' => $sede_site,
                    'propietario_tecnico' => $propietario_tecnico,
                    'inicio_soporte' => $inicio_soporte,
                    'fin_soporte' => $fin_soporte,
                    'summary' => "CI creado con éxito: '$hostname_nombre' ($id_ci) - Tipo: $tipo_ci"
                ]);

                json_response(['success' => true, 'message' => "CI '$hostname_nombre' creado exitosamente.$imgMsg", 'id' => $newId]);
            }

        } catch (Exception $e) {
            json_response(['success' => false, 'error' => 'Error al guardar CI: ' . $e->getMessage()], 500);
        }
        break;

    // ====================================================================
    // 4. ELIMINAR CI
    // ====================================================================
    case 'delete_ci':
        try {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                json_response(['success' => false, 'error' => 'ID inválido'], 400);
            }

            // Obtener datos completos antes de borrar
            $ciStmt = $pdo->prepare("SELECT * FROM cmdb_sonda_cis WHERE id = ?");
            $ciStmt->execute([$id]);
            $ci = $ciStmt->fetch(PDO::FETCH_ASSOC);

            if (!$ci) {
                json_response(['success' => false, 'error' => 'El CI no existe'], 404);
            }

            $delStmt = $pdo->prepare("DELETE FROM cmdb_sonda_cis WHERE id = ?");
            $delStmt->execute([$id]);

            log_audit($pdo, $id, 'DELETE', $current_username, [
                'id_ci' => $ci['id_ci'],
                'hostname' => $ci['hostname_nombre'],
                'tipo_ci' => $ci['tipo_ci'],
                'cliente' => $ci['cliente'],
                'servicio' => $ci['servicio'],
                'summary' => "CI eliminado del inventario: '{$ci['hostname_nombre']}' ({$ci['id_ci']})",
                'snapshot' => $ci
            ]);
            json_response(['success' => true, 'message' => "CI '{$ci['hostname_nombre']}' eliminado correctamente."]);

        } catch (Exception $e) {
            json_response(['success' => false, 'error' => 'Error al eliminar CI: ' . $e->getMessage()], 500);
        }
        break;

    // ====================================================================
    // 5. SERVICIOS CRÍTICOS (GESTIÓN DE APLICACIONES EMPRESARIALES)
    // ====================================================================
    case 'services_list':
        try {
            $stmt = $pdo->query("
                SELECT s.*, 
                       COUNT(c.id) as cis_count,
                       SUM(CASE WHEN c.dias_fin_soporte < 0 THEN 1 ELSE 0 END) as expired_cis_count,
                       SUM(CASE WHEN c.dias_fin_soporte <= 30 THEN 1 ELSE 0 END) as risk_cis_count
                FROM cmdb_sonda_services s
                LEFT JOIN cmdb_sonda_cis c ON s.id = c.service_id
                GROUP BY s.id
                ORDER BY s.criticidad_negocio ASC, s.nombre_servicio ASC
            ");
            $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
            json_response(['success' => true, 'services' => $services]);
        } catch (Exception $e) {
            json_response(['success' => false, 'error' => 'Error al listar servicios: ' . $e->getMessage()], 500);
        }
        break;

    case 'service_save':
        try {
            $id = isset($_POST['id']) && (int)$_POST['id'] > 0 ? (int)$_POST['id'] : null;
            $service_code = trim($_POST['service_code'] ?? '');
            $nombre_servicio = trim($_POST['nombre_servicio'] ?? '');
            $cliente = trim($_POST['cliente'] ?? '');
            $propietario_negocio = trim($_POST['propietario_negocio'] ?? '');
            $propietario_tecnico = trim($_POST['propietario_tecnico'] ?? '');
            $criticidad_negocio = trim($_POST['criticidad_negocio'] ?? 'Alta');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $estado = trim($_POST['estado'] ?? 'Operativo');

            if ($service_code === '' || $nombre_servicio === '' || $cliente === '') {
                json_response(['success' => false, 'error' => 'Código, Nombre del Servicio y Cliente son obligatorios.'], 422);
            }

            if ($id) {
                // Obtener datos previos del servicio
                $oldSvcStmt = $pdo->prepare("SELECT * FROM cmdb_sonda_services WHERE id = ?");
                $oldSvcStmt->execute([$id]);
                $oldSvc = $oldSvcStmt->fetch(PDO::FETCH_ASSOC);

                $stmt = $pdo->prepare("UPDATE cmdb_sonda_services SET 
                    service_code = ?, nombre_servicio = ?, cliente = ?, propietario_negocio = ?,
                    propietario_tecnico = ?, criticidad_negocio = ?, descripcion = ?, estado = ?
                    WHERE id = ?");
                $stmt->execute([$service_code, $nombre_servicio, $cliente, $propietario_negocio, $propietario_tecnico, $criticidad_negocio, $descripcion, $estado, $id]);

                // Comparar cambios en el servicio
                $svcLabels = [
                    'service_code' => 'Código de Servicio',
                    'nombre_servicio' => 'Nombre del Servicio',
                    'cliente' => 'Cliente',
                    'propietario_negocio' => 'Propietario de Negocio',
                    'propietario_tecnico' => 'Propietario Técnico',
                    'criticidad_negocio' => 'Criticidad de Negocio',
                    'descripcion' => 'Descripción',
                    'estado' => 'Estado'
                ];
                $newSvcMap = [
                    'service_code' => $service_code,
                    'nombre_servicio' => $nombre_servicio,
                    'cliente' => $cliente,
                    'propietario_negocio' => $propietario_negocio,
                    'propietario_tecnico' => $propietario_tecnico,
                    'criticidad_negocio' => $criticidad_negocio,
                    'descripcion' => $descripcion,
                    'estado' => $estado
                ];
                $svcChanges = [];
                if ($oldSvc) {
                    foreach ($svcLabels as $k => $lbl) {
                        $o = trim((string)($oldSvc[$k] ?? ''));
                        $n = trim((string)($newSvcMap[$k] ?? ''));
                        if ($o !== $n) {
                            $svcChanges[$k] = ['label' => $lbl, 'old' => $o, 'new' => $n];
                        }
                    }
                }

                log_audit($pdo, null, 'UPDATE_SERVICE', $current_username, [
                    'service_id' => $id,
                    'service_code' => $service_code,
                    'nombre_servicio' => $nombre_servicio,
                    'changes_count' => count($svcChanges),
                    'changes' => $svcChanges,
                    'summary' => "Servicio '$nombre_servicio' ($service_code) actualizado" . (count($svcChanges) > 0 ? ' (' . count($svcChanges) . ' campos modificados)' : '')
                ]);

                json_response(['success' => true, 'message' => 'Servicio de negocio actualizado correctamente.', 'id' => $id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO cmdb_sonda_services 
                    (service_code, nombre_servicio, cliente, propietario_negocio, propietario_tecnico, criticidad_negocio, descripcion, estado)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$service_code, $nombre_servicio, $cliente, $propietario_negocio, $propietario_tecnico, $criticidad_negocio, $descripcion, $estado]);
                $newSvcId = (int)$pdo->lastInsertId();

                log_audit($pdo, null, 'CREATE_SERVICE', $current_username, [
                    'service_id' => $newSvcId,
                    'service_code' => $service_code,
                    'nombre_servicio' => $nombre_servicio,
                    'cliente' => $cliente,
                    'criticidad' => $criticidad_negocio,
                    'summary' => "Nuevo servicio creado: '$nombre_servicio' ($service_code) para cliente '$cliente'"
                ]);

                json_response(['success' => true, 'message' => 'Servicio de negocio creado exitosamente.', 'id' => $newSvcId]);
            }
        } catch (Exception $e) {
            json_response(['success' => false, 'error' => 'Error al guardar servicio: ' . $e->getMessage()], 500);
        }
        break;

    // ====================================================================
    // 6. MAPA DE DEPENDENCIAS Y GRAFO DE TOPOLOGÍA (VIS.JS READY)
    // ====================================================================
    case 'topology_graph':
        try {
            $service_id = isset($_GET['service_id']) && $_GET['service_id'] !== '' ? (int)$_GET['service_id'] : null;
            $whereCi = $service_id ? "WHERE service_id = $service_id" : "";

            // Nodos (CIs)
            $cisStmt = $pdo->query("
                SELECT id, id_ci, hostname_nombre, tipo_ci, ip_administracion, ambiente, estado_ci, criticidad, dias_fin_soporte, service_id
                FROM cmdb_sonda_cis $whereCi
            ");
            $rawCis = $cisStmt->fetchAll(PDO::FETCH_ASSOC);

            $nodes = [];
            $validNodeIds = [];

            foreach ($rawCis as $ci) {
                $validNodeIds[] = (int)$ci['id'];

                $tipoLower = strtolower($ci['tipo_ci']);
                $hostLower = strtolower($ci['hostname_nombre']);
                $isExpired = ($ci['dias_fin_soporte'] !== null && (int)$ci['dias_fin_soporte'] < 0);

                // Clasificación Arquitectónica ITIL en 4 Capas Gerenciales:
                // Capa 1: Aplicaciones, Portales Web y Servicios de Software
                // Capa 2: Servidores de Procesamiento / Cómputo (Virtual / Físico)
                // Capa 3: Bases de Datos y Almacenamiento (SAN / Storage)
                // Capa 4: Networking y Seguridad Perimetral (Switches, Routers, Firewalls)
                $level = 2;
                $categoryTag = "🖥️ CAPA 2: SERVIDOR DE CÓMPUTO";
                $bgColor = '#1e3a8a';   // Deep Royal Blue
                $borderColor = '#60a5fa';

                if (strpos($tipoLower, 'base de datos') !== false || strpos($tipoLower, 'db') !== false || strpos($hostLower, '-db') !== false) {
                    $level = 3;
                    $categoryTag = "🗄️ CAPA 3: BASE DE DATOS EMPRESARIAL";
                    $bgColor = '#4c1d95';   // Purple
                    $borderColor = '#a78bfa';
                } elseif (strpos($tipoLower, 'storage') !== false || strpos($tipoLower, 'san') !== false || strpos($tipoLower, 'almacenamiento') !== false || strpos($hostLower, 'storage') !== false || strpos($hostLower, 'san-') !== false) {
                    $level = 3;
                    $categoryTag = "💾 CAPA 3: ALMACENAMIENTO SAN / STORAGE";
                    $bgColor = '#3b0764';   // Plum
                    $borderColor = '#c084fc';
                } elseif (strpos($tipoLower, 'switch') !== false || strpos($tipoLower, 'router') !== false || strpos($hostLower, 'sw-') !== false || strpos($hostLower, 'switch') !== false) {
                    $level = 4;
                    $categoryTag = "🔀 CAPA 4: SWITCH / RED CORE";
                    $bgColor = '#064e3b';   // Emerald
                    $borderColor = '#34d399';
                } elseif (strpos($tipoLower, 'firewall') !== false || strpos($tipoLower, 'seguridad') !== false || strpos($hostLower, 'fw-') !== false) {
                    $level = 4;
                    $categoryTag = "🛡️ CAPA 4: SEGURIDAD & FIREWALL";
                    $bgColor = '#4c0519';   // Deep Rose
                    $borderColor = '#fb7185';
                } elseif (strpos($tipoLower, 'aplicaci') !== false || strpos($tipoLower, 'portal') !== false || strpos($tipoLower, 'web') !== false || strpos($tipoLower, 'software') !== false || (strpos($hostLower, 'app-') === 0 && strpos($tipoLower, 'servidor') === false)) {
                    $level = 1;
                    $categoryTag = "📱 CAPA 1: APLICACIÓN WEB / PORTAL";
                    $bgColor = '#78350f';   // Warm Amber
                    $borderColor = '#f59e0b';
                } elseif (strpos($tipoLower, 'servidor') !== false || strpos($tipoLower, 'server') !== false) {
                    $level = 2;
                    $categoryTag = "🖥️ CAPA 2: SERVIDOR DE APLICACIONES";
                    $bgColor = '#1e3a8a';
                    $borderColor = '#60a5fa';
                }

                // Alarma si soporte está vencido: Alerta de Nivel Ejecutivo
                if ($isExpired) {
                    $categoryTag = "🚨 ALARMA: SOPORTE VENCIDO (" . abs((int)$ci['dias_fin_soporte']) . " DÍAS)";
                    $bgColor = '#7f1d1d';   // Crimson Red
                    $borderColor = '#ff001e'; // Neon Alert Red
                }

                $label = "{$categoryTag}\n{$ci['hostname_nombre']}\n{$ci['tipo_ci']} • {$ci['ip_administracion']}\nCriticidad: {$ci['criticidad']} • {$ci['ambiente']}";

                $nodes[] = [
                    'id' => (int)$ci['id'],
                    'label' => $label,
                    'level' => $level,
                    'title' => "<b>{$ci['hostname_nombre']}</b><br>Capa Arquitectónica: Nivel {$level}<br>ID CI: {$ci['id_ci']}<br>IP: {$ci['ip_administracion']}<br>Tipo: {$ci['tipo_ci']}<br>Ambiente: {$ci['ambiente']}<br>Criticidad: {$ci['criticidad']}<br>Días Soporte: {$ci['dias_fin_soporte']}",
                    'color' => [
                        'background' => $bgColor,
                        'border' => $borderColor,
                        'highlight' => ['background' => '#0f172a', 'border' => '#f59e0b'],
                        'hover' => ['background' => '#1e293b', 'border' => '#38bdf8']
                    ],
                    'borderWidth' => 2,
                    'borderWidthSelected' => 3,
                    'shape' => 'box',
                    'margin' => [
                        'top' => 14,
                        'bottom' => 14,
                        'left' => 18,
                        'right' => 18
                    ],
                    'widthConstraint' => [
                        'minimum' => 250,
                        'maximum' => 280
                    ],
                    'shadow' => [
                        'enabled' => true,
                        'color' => 'rgba(0,0,0,0.25)',
                        'size' => 10,
                        'x' => 0,
                        'y' => 5
                    ],
                    'font' => [
                        'color' => '#ffffff',
                        'size' => 12,
                        'face' => 'Kumbh Sans, system-ui, sans-serif'
                    ],
                    'ci_data' => $ci
                ];
            }

            // Aristas (Relaciones de Dependencia)
            $relStmt = $pdo->query("
                SELECT id, source_ci_id, target_ci_id, relationship_type, descripcion, impacto_falla
                FROM cmdb_sonda_relationships
            ");
            $rawRels = $relStmt->fetchAll(PDO::FETCH_ASSOC);

            $edges = [];
            foreach ($rawRels as $r) {
                $src = (int)$r['source_ci_id'];
                $tgt = (int)$r['target_ci_id'];

                if (in_array($src, $validNodeIds) && in_array($tgt, $validNodeIds)) {
                    $edgeColor = '#4a5568';
                    $dashes = false;
                    $width = 2.5;

                    if ($r['impacto_falla'] === 'Crítico') {
                        $edgeColor = '#dc3545';
                        $width = 3.5;
                    } elseif ($r['impacto_falla'] === 'Alto') {
                        $edgeColor = '#fd7e14';
                        $width = 3.0;
                    }

                    if ($r['relationship_type'] === 'ejecuta_en') {
                        $dashes = [6, 4];
                    }

                    $edges[] = [
                        'id' => (int)$r['id'],
                        'from' => $src,
                        'to' => $tgt,
                        'label' => ' ' . str_replace('_', ' ', $r['relationship_type']) . ' ',
                        'title' => "Tipo: {$r['relationship_type']}<br>Impacto: {$r['impacto_falla']}<br>Detalle: {$r['descripcion']}",
                        'arrows' => [
                            'to' => [
                                'enabled' => true,
                                'scaleFactor' => 1.2
                            ]
                        ],
                        'color' => [
                            'color' => $edgeColor,
                            'highlight' => '#0d6efd',
                            'hover' => '#0d6efd',
                            'inherit' => false
                        ],
                        'width' => $width,
                        'dashes' => $dashes,
                        'font' => [
                            'align' => 'middle',
                            'size' => 11,
                            'color' => '#1a202c',
                            'background' => '#ffffff',
                            'strokeWidth' => 1,
                            'strokeColor' => '#cbd5e0'
                        ],
                        'smooth' => [
                            'enabled' => true,
                            'type' => 'cubicBezier',
                            'forceDirection' => 'vertical',
                            'roundness' => 0.5
                        ]
                    ];
                }
            }

            // Si se filtra por un Servicio de Negocio, inyectar el nodo raíz de Nivel 0 (Capa de Negocio)
            if ($service_id) {
                $svcStmt = $pdo->prepare("SELECT * FROM cmdb_sonda_services WHERE id = ?");
                $svcStmt->execute([$service_id]);
                $svc = $svcStmt->fetch(PDO::FETCH_ASSOC);
                if ($svc) {
                    $svcNodeId = -1 * (int)$svc['id'];
                    $nodes[] = [
                        'id' => $svcNodeId,
                        'label' => "💼 CAPA 0: SERVICIO CRÍTICO DE NEGOCIO\n{$svc['nombre_servicio']}\nCódigo: {$svc['service_code']} • SLA: {$svc['criticidad_negocio']}\nPropietario: {$svc['propietario_negocio']}",
                        'level' => 0,
                        'title' => "<b>Servicio de Negocio: {$svc['nombre_servicio']}</b><br>Código: {$svc['service_code']}<br>Propietario: {$svc['propietario_negocio']}<br>Criticidad: {$svc['criticidad_negocio']}",
                        'color' => [
                            'background' => '#0f172a',
                            'border' => '#f59e0b',
                            'highlight' => ['background' => '#1e293b', 'border' => '#fbbf24'],
                            'hover' => ['background' => '#1e293b', 'border' => '#fde68a']
                        ],
                        'borderWidth' => 3,
                        'shape' => 'box',
                        'margin' => ['top' => 16, 'bottom' => 16, 'left' => 20, 'right' => 20],
                        'widthConstraint' => ['minimum' => 280, 'maximum' => 340],
                        'shadow' => ['enabled' => true, 'color' => 'rgba(245, 158, 11, 0.4)', 'size' => 14, 'x' => 0, 'y' => 6],
                        'font' => ['color' => '#ffffff', 'size' => 13, 'face' => 'Kumbh Sans, system-ui, sans-serif'],
                        'is_service' => true
                    ];

                    // Conectar el nodo de servicio a las aplicaciones o CIs principales
                    foreach ($nodes as $nd) {
                        if (isset($nd['level']) && $nd['level'] === 1) {
                            $edges[] = [
                                'id' => 'svc_edge_' . $nd['id'],
                                'from' => $svcNodeId,
                                'to' => (int)$nd['id'],
                                'label' => ' sustenta a ',
                                'title' => 'El Servicio de Negocio sustenta esta Aplicación',
                                'arrows' => ['to' => ['enabled' => true, 'scaleFactor' => 1.3]],
                                'color' => ['color' => '#f59e0b', 'highlight' => '#fbbf24'],
                                'width' => 3.5,
                                'dashes' => false,
                                'font' => ['align' => 'middle', 'size' => 11, 'color' => '#78350f', 'background' => '#fffbeb', 'strokeWidth' => 1, 'strokeColor' => '#fde68a'],
                                'smooth' => ['enabled' => true, 'type' => 'cubicBezier', 'forceDirection' => 'vertical', 'roundness' => 0.5]
                            ];
                        }
                    }
                }
            }

            json_response([
                'success' => true,
                'nodes' => $nodes,
                'edges' => $edges,
                'count_nodes' => count($nodes),
                'count_edges' => count($edges)
            ]);

        } catch (Exception $e) {
            json_response(['success' => false, 'error' => 'Error al generar grafo de dependencias: ' . $e->getMessage()], 500);
        }
        break;

    // ====================================================================
    // 7. ANÁLISIS DE IMPACTO DE FALLA (IMPACT ANALYSIS TOOL)
    // ====================================================================
    case 'impact_analysis':
        try {
            $ci_id = (int)($_GET['ci_id'] ?? 0);
            if ($ci_id <= 0) {
                json_response(['success' => false, 'error' => 'ID de CI requerido'], 400);
            }

            // CI Raíz afectado
            $stmtRoot = $pdo->prepare("
                SELECT c.*, s.nombre_servicio, s.criticidad_negocio as service_criticality
                FROM cmdb_sonda_cis c
                LEFT JOIN cmdb_sonda_services s ON c.service_id = s.id
                WHERE c.id = ?
            ");
            $stmtRoot->execute([$ci_id]);
            $rootCi = $stmtRoot->fetch(PDO::FETCH_ASSOC);

            if (!$rootCi) {
                json_response(['success' => false, 'error' => 'CI no encontrado'], 404);
            }

            // Algoritmo de BFS para encontrar todos los CIs dependientes (upstream: que dependen de este nodo)
            $queue = [$ci_id];
            $visited = [$ci_id => true];
            $impactedCIs = [];
            $affectedServices = [];

            if (!empty($rootCi['nombre_servicio'])) {
                $affectedServices[$rootCi['service_id']] = [
                    'id' => $rootCi['service_id'],
                    'nombre' => $rootCi['nombre_servicio'],
                    'criticidad' => $rootCi['service_criticality']
                ];
            }

            while (!empty($queue)) {
                $currId = array_shift($queue);

                // CIs que tienen como target a este $currId (es decir, dependen de él)
                $stmtDep = $pdo->prepare("
                    SELECT r.id as rel_id, r.relationship_type, r.impacto_falla, r.descripcion as rel_desc,
                           c.*, s.nombre_servicio, s.criticidad_negocio as service_criticality
                    FROM cmdb_sonda_relationships r
                    JOIN cmdb_sonda_cis c ON r.source_ci_id = c.id
                    LEFT JOIN cmdb_sonda_services s ON c.service_id = s.id
                    WHERE r.target_ci_id = ?
                ");
                $stmtDep->execute([$currId]);
                $deps = $stmtDep->fetchAll(PDO::FETCH_ASSOC);

                foreach ($deps as $dep) {
                    $depId = (int)$dep['id'];
                    if (!isset($visited[$depId])) {
                        $visited[$depId] = true;
                        $queue[] = $depId;
                        $impactedCIs[] = $dep;

                        if (!empty($dep['nombre_servicio']) && !isset($affectedServices[$dep['service_id']])) {
                            $affectedServices[$dep['service_id']] = [
                                'id' => $dep['service_id'],
                                'nombre' => $dep['nombre_servicio'],
                                'criticidad' => $dep['service_criticality']
                            ];
                        }
                    }
                }
            }

            json_response([
                'success' => true,
                'root_ci' => $rootCi,
                'total_impacted_cis' => count($impactedCIs),
                'impacted_cis' => $impactedCIs,
                'affected_services' => array_values($affectedServices),
                'max_impact' => count($impactedCIs) > 0 ? 'Crítico (Afectación en Cadena)' : 'Local (Sin dependientes directos)'
            ]);

        } catch (Exception $e) {
            json_response(['success' => false, 'error' => 'Error al ejecutar análisis de impacto: ' . $e->getMessage()], 500);
        }
        break;

    // ====================================================================
    // 8. GUARDAR / ELIMINAR RELACIÓN DE DEPENDENCIA ENTRE CIs
    // ====================================================================
    case 'relationship_save':
        try {
            $source_ci_id = (int)($_POST['source_ci_id'] ?? 0);
            $target_ci_id = (int)($_POST['target_ci_id'] ?? 0);
            $relationship_type = trim($_POST['relationship_type'] ?? 'depende_de');
            $impacto_falla = trim($_POST['impacto_falla'] ?? 'Alto');
            $descripcion = trim($_POST['descripcion'] ?? '');

            if ($source_ci_id <= 0 || $target_ci_id <= 0) {
                json_response(['success' => false, 'error' => 'Debe seleccionar un CI Origen y un CI Destino válidos.'], 422);
            }

            if ($source_ci_id === $target_ci_id) {
                json_response(['success' => false, 'error' => 'Un CI no puede depender de sí mismo.'], 422);
            }

            $stmt = $pdo->prepare("
                INSERT INTO cmdb_sonda_relationships (source_ci_id, target_ci_id, relationship_type, descripcion, impacto_falla)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), impacto_falla = VALUES(impacto_falla)
            ");
            $stmt->execute([$source_ci_id, $target_ci_id, $relationship_type, $descripcion, $impacto_falla]);

            // Obtener datos de origen y destino para auditoría descriptiva
            $srcStmt = $pdo->prepare("SELECT id_ci, hostname_nombre FROM cmdb_sonda_cis WHERE id = ?");
            $srcStmt->execute([$source_ci_id]);
            $srcCi = $srcStmt->fetch(PDO::FETCH_ASSOC);

            $tgtStmt = $pdo->prepare("SELECT id_ci, hostname_nombre FROM cmdb_sonda_cis WHERE id = ?");
            $tgtStmt->execute([$target_ci_id]);
            $tgtCi = $tgtStmt->fetch(PDO::FETCH_ASSOC);

            $srcHost = $srcCi['hostname_nombre'] ?? "CI #$source_ci_id";
            $tgtHost = $tgtCi['hostname_nombre'] ?? "CI #$target_ci_id";

            log_audit($pdo, $source_ci_id, 'ADD_RELATION', $current_username, [
                'id_ci' => $srcCi['id_ci'] ?? '',
                'hostname' => $srcHost,
                'source_ci_id' => $source_ci_id,
                'source_hostname' => $srcHost,
                'target_ci_id' => $target_ci_id,
                'target_hostname' => $tgtHost,
                'target_id_ci' => $tgtCi['id_ci'] ?? '',
                'type' => $relationship_type,
                'impact' => $impacto_falla,
                'summary' => "Relación registrada: '$srcHost' $relationship_type '$tgtHost' (Impacto: $impacto_falla)"
            ]);

            json_response(['success' => true, 'message' => 'Relación de dependencia registrada con éxito.']);

        } catch (Exception $e) {
            json_response(['success' => false, 'error' => 'Error al guardar relación: ' . $e->getMessage()], 500);
        }
        break;

    case 'relationship_delete':
        try {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                json_response(['success' => false, 'error' => 'ID de relación inválido'], 400);
            }

            // Consultar datos de la relación antes de borrar
            $relStmt = $pdo->prepare("
                SELECT r.*, 
                       s.id_ci as source_id_ci, s.hostname_nombre as source_hostname,
                       t.id_ci as target_id_ci, t.hostname_nombre as target_hostname
                FROM cmdb_sonda_relationships r
                LEFT JOIN cmdb_sonda_cis s ON r.source_ci_id = s.id
                LEFT JOIN cmdb_sonda_cis t ON r.target_ci_id = t.id
                WHERE r.id = ?
            ");
            $relStmt->execute([$id]);
            $rel = $relStmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("DELETE FROM cmdb_sonda_relationships WHERE id = ?");
            $stmt->execute([$id]);

            if ($rel) {
                $sHost = $rel['source_hostname'] ?? "CI #{$rel['source_ci_id']}";
                $tHost = $rel['target_hostname'] ?? "CI #{$rel['target_ci_id']}";
                log_audit($pdo, $rel['source_ci_id'], 'DELETE_RELATION', $current_username, [
                    'id_ci' => $rel['source_id_ci'] ?? '',
                    'hostname' => $sHost,
                    'source_ci_id' => $rel['source_ci_id'],
                    'source_hostname' => $sHost,
                    'target_ci_id' => $rel['target_ci_id'],
                    'target_hostname' => $tHost,
                    'type' => $rel['relationship_type'],
                    'summary' => "Relación eliminada: '$sHost' ya no está vinculado con '$tHost'"
                ]);
            }

            json_response(['success' => true, 'message' => 'Relación eliminada exitosamente.']);
        } catch (Exception $e) {
            json_response(['success' => false, 'error' => 'Error al eliminar relación: ' . $e->getMessage()], 500);
        }
        break;

    // ====================================================================
    // 9. AUDITORÍA DE INTEGRIDAD (FASE 1 CORE VS FASE 2 ENRIQUECIMIENTO)
    // ====================================================================
    case 'audit_integrity':
        try {
            $stmt = $pdo->query("SELECT * FROM cmdb_sonda_cis ORDER BY hostname_nombre ASC");
            $cis = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $total = count($cis);
            $phase1_incomplete = [];
            $missingFieldCounts = [
                'id_ci' => 0, 'hostname_nombre' => 0, 'tipo_ci' => 0, 'cliente' => 0,
                'servicio' => 0, 'sede_site' => 0, 'ip_administracion' => 0, 'ambiente' => 0,
                'estado_ci' => 0, 'criticidad' => 0, 'monitoreado' => 0, 'inicio_soporte' => 0,
                'fin_soporte' => 0, 'fabricante' => 0, 'modelo' => 0, 'numero_serie' => 0,
                'version_firmware_so' => 0, 'rack' => 0, 'licencia' => 0, 'fin_licencia' => 0
            ];

            foreach ($cis as $ci) {
                $missingF1 = [];
                $fase1Check = [
                    'id_ci' => 'ID_CI',
                    'hostname_nombre' => 'Hostname / Nombre',
                    'tipo_ci' => 'Tipo de CI',
                    'cliente' => 'Cliente',
                    'servicio' => 'Servicio',
                    'sede_site' => 'Sede / Site',
                    'ip_administracion' => 'IP Administración',
                    'ambiente' => 'Ambiente',
                    'estado_ci' => 'Estado CI',
                    'criticidad' => 'Criticidad',
                    'monitoreado' => 'Monitoreado',
                    'inicio_soporte' => 'Inicio Soporte',
                    'fin_soporte' => 'Fin Soporte'
                ];

                foreach ($fase1Check as $col => $lbl) {
                    if ($ci[$col] === null || trim((string)$ci[$col]) === '') {
                        $missingF1[] = $lbl;
                        $missingFieldCounts[$col]++;
                    }
                }

                // Conteo Fase 2
                foreach (['fabricante', 'modelo', 'numero_serie', 'version_firmware_so', 'rack', 'licencia', 'fin_licencia'] as $col2) {
                    if ($ci[$col2] === null || trim((string)$ci[$col2]) === '') {
                        $missingFieldCounts[$col2]++;
                    }
                }

                if (!empty($missingF1)) {
                    $phase1_incomplete[] = [
                        'id' => $ci['id'],
                        'id_ci' => $ci['id_ci'],
                        'hostname_nombre' => $ci['hostname_nombre'],
                        'tipo_ci' => $ci['tipo_ci'],
                        'cliente' => $ci['cliente'],
                        'missing_fields' => $missingF1
                    ];
                }
            }

            $compliancePct = $total > 0 ? round((($total - count($phase1_incomplete)) / $total) * 100, 1) : 100;

            json_response([
                'success' => true,
                'total_cis' => $total,
                'phase1_compliant_count' => $total - count($phase1_incomplete),
                'phase1_incomplete_count' => count($phase1_incomplete),
                'compliance_percentage' => $compliancePct,
                'incomplete_cis' => $phase1_incomplete,
                'missing_field_stats' => $missingFieldCounts
            ]);

        } catch (Exception $e) {
            json_response(['success' => false, 'error' => 'Error al auditar integridad: ' . $e->getMessage()], 500);
        }
        break;

    // ====================================================================
    // 10. RECÁLCULO AUTOMÁTICO EN LOTE DE DÍAS DE FIN DE SOPORTE
    // ====================================================================
    case 'recalculate_support':
        try {
            $updated = $pdo->exec("
                UPDATE cmdb_sonda_cis 
                SET dias_fin_soporte = DATEDIFF(fin_soporte, CURDATE())
                WHERE fin_soporte IS NOT NULL
            ");

            // Estadísticas resultantes
            $statStmt = $pdo->query("
                SELECT 
                    SUM(CASE WHEN dias_fin_soporte < 0 THEN 1 ELSE 0 END) as expired,
                    SUM(CASE WHEN dias_fin_soporte >= 0 AND dias_fin_soporte <= 30 THEN 1 ELSE 0 END) as due_soon,
                    SUM(CASE WHEN dias_fin_soporte > 30 THEN 1 ELSE 0 END) as active
                FROM cmdb_sonda_cis
            ");
            $stats = $statStmt->fetch(PDO::FETCH_ASSOC);

            log_audit($pdo, null, 'RECALCULATE_SUPPORT', $current_username, $stats);

            json_response([
                'success' => true,
                'message' => "Recálculo de días de soporte ejecutado exitosamente.",
                'rows_affected' => $updated,
                'stats' => $stats
            ]);

        } catch (Exception $e) {
            json_response(['success' => false, 'error' => 'Error al recalcular soporte: ' . $e->getMessage()], 500);
        }
        break;

    // ====================================================================
    // 11. EXPORTACIÓN COMPLETA A CSV (EXCEL COMPATIBLE UTF-8)
    // ====================================================================
    case 'export_csv':
        try {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=CMDB_SONDA_Inventario_' . date('Ymd_His') . '.csv');

            $output = fopen('php://output', 'w');
            // BOM UTF-8 para visualización correcta en Excel
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

            // Encabezados con las 5 categorías
            fputcsv($output, [
                // 1. Identificación y Técnicas
                'ID CI', 'Hostname / Nombre', 'Tipo CI', 'Fabricante', 'Modelo', 'Número Serie', 'Versión SO / Firmware',
                // 2. Negocio y Gobierno
                'Cliente', 'Servicio', 'Responsable Cliente', 'Contrato / Proyecto', 'Propietario Técnico',
                // 3. Ubicación y Topología
                'Sede / Site', 'País', 'Ciudad', 'Rack',
                // 4. Operaciones y Monitoreo
                'IP Administración', 'Ambiente', 'Estado CI', 'Criticidad', 'Monitoreado',
                // 5. Ciclo de Vida y Soporte
                'Inicio Soporte', 'Fin Soporte', 'Días Fin Soporte', 'Garantía Hasta', 'Licencia', 'Fin Licencia', 'Fecha EOL', 'Fecha EOS',
                // Metadatos
                'Notas'
            ], ';');

            $stmt = $pdo->query("SELECT * FROM cmdb_sonda_cis ORDER BY hostname_nombre ASC");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, [
                    $row['id_ci'], $row['hostname_nombre'], $row['tipo_ci'], $row['fabricante'], $row['modelo'], $row['numero_serie'], $row['version_firmware_so'],
                    $row['cliente'], $row['servicio'], $row['responsable_cliente'], $row['contrato_proyecto'], $row['propietario_tecnico'],
                    $row['sede_site'], $row['pais'], $row['ciudad'], $row['rack'],
                    $row['ip_administracion'], $row['ambiente'], $row['estado_ci'], $row['criticidad'], $row['monitoreado'],
                    $row['inicio_soporte'], $row['fin_soporte'], $row['dias_fin_soporte'], $row['garantia_hasta'], $row['licencia'], $row['fin_licencia'], $row['fecha_eol'], $row['fecha_eos'],
                    $row['notas_adicionales']
                ], ';');
            }

            fclose($output);
            exit;

        } catch (Exception $e) {
            json_response(['success' => false, 'error' => 'Error al exportar CSV: ' . $e->getMessage()], 500);
        }
        break;

    // ====================================================================
    // 12. GESTIÓN FOTOGRÁFICA Y EVIDENCIAS DE UBICACIÓN (6TA CATEGORÍA)
    // ====================================================================
    case 'upload_image':
        try {
            $ci_id = (int)($_POST['ci_id'] ?? 0);
            if ($ci_id <= 0) {
                json_response(['success' => false, 'error' => 'Debe asociar la imagen a un CI válido.'], 400);
            }

            $file = $_FILES['image'] ?? $_FILES['ci_image'] ?? null;
            if (!$file || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
                json_response(['success' => false, 'error' => 'No se recibió ningún archivo de imagen o hubo un error en la carga.'], 400);
            }

            $ubicacion_foto = trim($_POST['ubicacion_foto'] ?? $_POST['ci_image_ubicacion'] ?? 'General');
            $tags = trim($_POST['tags'] ?? $_POST['ci_image_tags'] ?? '');
            $fecha_creacion_foto = trim($_POST['fecha_creacion_foto'] ?? $_POST['ci_image_fecha'] ?? date('Y-m-d'));
            $observaciones = trim($_POST['observaciones'] ?? $_POST['ci_image_observaciones'] ?? '');

            $res = handle_save_image($pdo, $ci_id, $file, $ubicacion_foto, $tags, $fecha_creacion_foto, $observaciones, $current_username);

            json_response([
                'success' => true,
                'message' => 'Imagen y evidencia registrada y almacenada en el servidor con éxito.',
                'image_id' => $res['image_id'],
                'file_path' => $res['file_path']
            ]);

        } catch (Exception $e) {
            json_response(['success' => false, 'error' => 'Error al subir imagen: ' . $e->getMessage()], 500);
        }
        break;

    case 'list_images':
        try {
            $ci_id = isset($_GET['ci_id']) && (int)$_GET['ci_id'] > 0 ? (int)$_GET['ci_id'] : 0;
            $tag = trim($_GET['tag'] ?? '');
            $ubicacion = trim($_GET['ubicacion'] ?? '');

            $where = [];
            $params = [];

            if ($ci_id > 0) {
                $where[] = "i.ci_id = ?";
                $params[] = $ci_id;
            }

            if ($tag !== '') {
                $where[] = "i.tags LIKE ?";
                $params[] = '%' . $tag . '%';
            }

            if ($ubicacion !== '') {
                $where[] = "i.ubicacion_foto = ?";
                $params[] = $ubicacion;
            }

            $whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

            $stmt = $pdo->prepare("
                SELECT i.*, c.hostname_nombre, c.id_ci, c.tipo_ci, c.cliente, c.servicio, c.sede_site, c.rack
                FROM cmdb_sonda_images i
                JOIN cmdb_sonda_cis c ON i.ci_id = c.id
                $whereSql
                ORDER BY i.id DESC
            ");
            $stmt->execute($params);
            $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Generar URL completa relativa al servidor
            foreach ($images as &$img) {
                $img['url'] = PUBLIC_URL_PREFIX . '/' . $img['file_path'];
                $img['tags_array'] = !empty($img['tags']) ? array_filter(array_map('trim', explode(',', $img['tags']))) : [];
            }

            json_response(['success' => true, 'images' => $images, 'count' => count($images)]);

        } catch (Exception $e) {
            json_response(['success' => false, 'error' => 'Error al listar imágenes: ' . $e->getMessage()], 500);
        }
        break;

    case 'delete_image':
        try {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                json_response(['success' => false, 'error' => 'ID de imagen inválido.'], 400);
            }

            $stmt = $pdo->prepare("SELECT * FROM cmdb_sonda_images WHERE id = ?");
            $stmt->execute([$id]);
            $img = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$img) {
                json_response(['success' => false, 'error' => 'La imagen no existe.'], 404);
            }

            // Eliminar archivo físico
            $fullPath = __DIR__ . '/../' . $img['file_path'];
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }

            $delStmt = $pdo->prepare("DELETE FROM cmdb_sonda_images WHERE id = ?");
            $delStmt->execute([$id]);

            log_audit($pdo, $img['ci_id'], 'DELETE_IMAGE', $current_username, ['image_id' => $id, 'file_name' => $img['file_name']]);

            json_response(['success' => true, 'message' => 'Imagen eliminada correctamente.']);

        } catch (Exception $e) {
            json_response(['success' => false, 'error' => 'Error al eliminar imagen: ' . $e->getMessage()], 500);
        }
        break;

    // ====================================================================
    // DASHBOARD ANALÍTICO MULTIDIMENSIONAL (BI & MÉTRICAS DE INFRAESTRUCTURA)
    // ====================================================================
    case 'get_analytics_dashboard':
        try {
            $cliente = trim($_GET['cliente'] ?? '');
            $servicio = trim($_GET['servicio'] ?? '');
            $tipo_ci = trim($_GET['tipo_ci'] ?? '');
            $ambiente = trim($_GET['ambiente'] ?? '');
            $criticidad = trim($_GET['criticidad'] ?? '');
            $support_status = trim($_GET['support_status'] ?? 'all');

            $where = ["1=1"];
            $params = [];

            if (!empty($cliente)) {
                $where[] = "cliente = ?";
                $params[] = $cliente;
            }
            if (!empty($servicio)) {
                $where[] = "servicio = ?";
                $params[] = $servicio;
            }
            if (!empty($tipo_ci)) {
                $where[] = "tipo_ci = ?";
                $params[] = $tipo_ci;
            }
            if (!empty($ambiente)) {
                $where[] = "ambiente = ?";
                $params[] = $ambiente;
            }
            if (!empty($criticidad)) {
                $where[] = "criticidad = ?";
                $params[] = $criticidad;
            }
            if ($support_status === 'expired') {
                $where[] = "dias_fin_soporte < 0";
            } elseif ($support_status === 'due30') {
                $where[] = "dias_fin_soporte >= 0 AND dias_fin_soporte <= 30";
            } elseif ($support_status === 'due60') {
                $where[] = "dias_fin_soporte >= 0 AND dias_fin_soporte <= 60";
            } elseif ($support_status === 'active') {
                $where[] = "dias_fin_soporte > 60";
            }

            $whereSql = implode(" AND ", $where);

            // 1. KPIs Globales con Filtros
            $kpiStmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_cis,
                    COUNT(DISTINCT cliente) as total_clients,
                    COUNT(DISTINCT servicio) as total_services,
                    SUM(CASE WHEN ambiente = 'Producción' THEN 1 ELSE 0 END) as prod_cis,
                    SUM(CASE WHEN dias_fin_soporte < 0 THEN 1 ELSE 0 END) as expired_support,
                    SUM(CASE WHEN dias_fin_soporte >= 0 AND dias_fin_soporte <= 30 THEN 1 ELSE 0 END) as due_soon_support,
                    SUM(CASE WHEN dias_fin_soporte > 60 THEN 1 ELSE 0 END) as active_support,
                    SUM(CASE WHEN monitoreado = 'Sí' THEN 1 ELSE 0 END) as monitored_cis,
                    SUM(CASE WHEN criticidad = 'Crítica' THEN 1 ELSE 0 END) as critical_cis
                FROM cmdb_sonda_cis
                WHERE $whereSql
            ");
            $kpiStmt->execute($params);
            $kpis = $kpiStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $totalCis = (int)($kpis['total_cis'] ?? 0);
            $kpis['prod_pct'] = $totalCis > 0 ? round(((int)($kpis['prod_cis'] ?? 0) / $totalCis) * 100) : 0;
            $kpis['monitored_pct'] = $totalCis > 0 ? round(((int)($kpis['monitored_cis'] ?? 0) / $totalCis) * 100) : 0;
            $kpis['critical_pct'] = $totalCis > 0 ? round(((int)($kpis['critical_cis'] ?? 0) / $totalCis) * 100) : 0;

            // 2. Análisis por Cliente
            $byClientStmt = $pdo->prepare("
                SELECT 
                    COALESCE(cliente, 'Sin Asignar') as cliente,
                    COUNT(*) as total,
                    SUM(CASE WHEN criticidad = 'Crítica' THEN 1 ELSE 0 END) as criticos,
                    SUM(CASE WHEN dias_fin_soporte < 0 THEN 1 ELSE 0 END) as vencidos,
                    SUM(CASE WHEN dias_fin_soporte >= 0 AND dias_fin_soporte <= 30 THEN 1 ELSE 0 END) as por_vencer,
                    SUM(CASE WHEN dias_fin_soporte > 30 THEN 1 ELSE 0 END) as vigentes,
                    SUM(CASE WHEN monitoreado = 'Sí' THEN 1 ELSE 0 END) as monitoreados,
                    COUNT(DISTINCT servicio) as servicios_count
                FROM cmdb_sonda_cis
                WHERE $whereSql
                GROUP BY cliente
                ORDER BY total DESC
            ");
            $byClientStmt->execute($params);
            $byClient = $byClientStmt->fetchAll(PDO::FETCH_ASSOC);

            // 3. Análisis por Servicio
            $byServiceStmt = $pdo->prepare("
                SELECT 
                    COALESCE(servicio, 'Sin Servicio Asignado') as servicio,
                    COALESCE(cliente, 'Sin Cliente') as cliente,
                    COUNT(*) as total,
                    SUM(CASE WHEN criticidad = 'Crítica' THEN 1 ELSE 0 END) as criticos,
                    SUM(CASE WHEN dias_fin_soporte < 0 THEN 1 ELSE 0 END) as vencidos,
                    SUM(CASE WHEN monitoreado = 'Sí' THEN 1 ELSE 0 END) as monitoreados
                FROM cmdb_sonda_cis
                WHERE $whereSql
                GROUP BY servicio, cliente
                ORDER BY total DESC
            ");
            $byServiceStmt->execute($params);
            $byService = $byServiceStmt->fetchAll(PDO::FETCH_ASSOC);

            // 4. Análisis por Tipo de CI
            $byTypeStmt = $pdo->prepare("
                SELECT 
                    COALESCE(tipo_ci, 'No Especificado') as tipo_ci,
                    COUNT(*) as total,
                    SUM(CASE WHEN criticidad = 'Crítica' THEN 1 ELSE 0 END) as criticos,
                    SUM(CASE WHEN dias_fin_soporte < 0 THEN 1 ELSE 0 END) as vencidos,
                    SUM(CASE WHEN monitoreado = 'Sí' THEN 1 ELSE 0 END) as monitoreados
                FROM cmdb_sonda_cis
                WHERE $whereSql
                GROUP BY tipo_ci
                ORDER BY total DESC
            ");
            $byTypeStmt->execute($params);
            $byType = $byTypeStmt->fetchAll(PDO::FETCH_ASSOC);

            // 5. Análisis por Ambiente
            $byEnvStmt = $pdo->prepare("
                SELECT 
                    COALESCE(ambiente, 'Sin Definir') as ambiente,
                    COUNT(*) as total
                FROM cmdb_sonda_cis
                WHERE $whereSql
                GROUP BY ambiente
                ORDER BY total DESC
            ");
            $byEnvStmt->execute($params);
            $byEnv = $byEnvStmt->fetchAll(PDO::FETCH_ASSOC);

            // 6. Análisis por Criticidad
            $byCritStmt = $pdo->prepare("
                SELECT 
                    COALESCE(criticidad, 'Media') as criticidad,
                    COUNT(*) as total
                FROM cmdb_sonda_cis
                WHERE $whereSql
                GROUP BY criticidad
                ORDER BY FIELD(criticidad, 'Crítica', 'Alta', 'Media', 'Baja')
            ");
            $byCritStmt->execute($params);
            $byCrit = $byCritStmt->fetchAll(PDO::FETCH_ASSOC);

            // 7. Análisis por Estado de Soporte
            $bySupportStmt = $pdo->prepare("
                SELECT 
                    SUM(CASE WHEN dias_fin_soporte < 0 THEN 1 ELSE 0 END) as vencido,
                    SUM(CASE WHEN dias_fin_soporte >= 0 AND dias_fin_soporte <= 30 THEN 1 ELSE 0 END) as vence_30d,
                    SUM(CASE WHEN dias_fin_soporte > 30 AND dias_fin_soporte <= 90 THEN 1 ELSE 0 END) as vence_90d,
                    SUM(CASE WHEN dias_fin_soporte > 90 THEN 1 ELSE 0 END) as vigente,
                    SUM(CASE WHEN fin_soporte IS NULL THEN 1 ELSE 0 END) as sin_fecha
                FROM cmdb_sonda_cis
                WHERE $whereSql
            ");
            $bySupportStmt->execute($params);
            $bySupport = $bySupportStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            // 8. Top Fabricantes (Vendors)
            $byVendorStmt = $pdo->prepare("
                SELECT 
                    COALESCE(fabricante, 'Sin Fabricante') as fabricante,
                    COUNT(*) as total
                FROM cmdb_sonda_cis
                WHERE $whereSql
                GROUP BY fabricante
                ORDER BY total DESC
                LIMIT 10
            ");
            $byVendorStmt->execute($params);
            $byVendor = $byVendorStmt->fetchAll(PDO::FETCH_ASSOC);

            // 9. Opciones de Filtros Dinámicos (Sin restricción de filtros actuales para permitir expandir)
            $clientsOptStmt = $pdo->query("SELECT DISTINCT cliente FROM cmdb_sonda_cis WHERE cliente IS NOT NULL AND cliente != '' ORDER BY cliente ASC");
            $clientsOpt = $clientsOptStmt->fetchAll(PDO::FETCH_COLUMN);

            $servicesOptStmt = $pdo->query("SELECT DISTINCT servicio as nombre_servicio, cliente FROM cmdb_sonda_cis WHERE servicio IS NOT NULL AND servicio != '' ORDER BY servicio ASC");
            $servicesOpt = $servicesOptStmt->fetchAll(PDO::FETCH_ASSOC);

            $typesOptStmt = $pdo->query("SELECT DISTINCT tipo_ci FROM cmdb_sonda_cis WHERE tipo_ci IS NOT NULL AND tipo_ci != '' ORDER BY tipo_ci ASC");
            $typesOpt = $typesOptStmt->fetchAll(PDO::FETCH_COLUMN);

            $envsOptStmt = $pdo->query("SELECT DISTINCT ambiente FROM cmdb_sonda_cis WHERE ambiente IS NOT NULL AND ambiente != '' ORDER BY ambiente ASC");
            $envsOpt = $envsOptStmt->fetchAll(PDO::FETCH_COLUMN);

            json_response([
                'success' => true,
                'kpis' => $kpis,
                'by_client' => $byClient,
                'by_service' => $byService,
                'by_type' => $byType,
                'by_environment' => $byEnv,
                'by_criticidad' => $byCrit,
                'by_support_status' => $bySupport,
                'by_vendor' => $byVendor,
                'filter_options' => [
                    'clients' => $clientsOpt,
                    'services' => $servicesOpt,
                    'types' => $typesOpt,
                    'environments' => $envsOpt,
                    'criticalities' => ['Crítica', 'Alta', 'Media', 'Baja']
                ]
            ]);

        } catch (Exception $e) {
            json_response(['success' => false, 'error' => 'Error al generar analítica BI: ' . $e->getMessage()], 500);
        }
        break;

    // ====================================================================
    // 15. BITÁCORA DE HISTORIAL & AUDITORÍA DE CAMBIOS (TRAZABILIDAD 360)
    // ====================================================================
    case 'audit_logs':
        try {
            $ci_id = isset($_GET['ci_id']) && $_GET['ci_id'] !== '' ? (int)$_GET['ci_id'] : null;
            $action_type = trim($_GET['action_type'] ?? '');
            $user_filter = trim($_GET['user'] ?? '');
            $date_from = trim($_GET['date_from'] ?? '');
            $date_to = trim($_GET['date_to'] ?? '');
            $q = trim($_GET['q'] ?? '');
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = (int)($_GET['limit'] ?? 25);
            if ($limit < 5 || $limit > 200) $limit = 25;
            $offset = ($page - 1) * $limit;

            $where = ["1=1"];
            $params = [];

            if ($ci_id !== null && $ci_id > 0) {
                $where[] = "a.ci_id = ?";
                $params[] = $ci_id;
            }

            if ($action_type !== '') {
                if ($action_type === 'CREATE') {
                    $where[] = "a.action = 'CREATE'";
                } elseif ($action_type === 'UPDATE') {
                    $where[] = "a.action = 'UPDATE'";
                } elseif ($action_type === 'DELETE') {
                    $where[] = "a.action = 'DELETE'";
                } elseif ($action_type === 'RELATIONS') {
                    $where[] = "a.action IN ('ADD_RELATION', 'DELETE_RELATION')";
                } elseif ($action_type === 'IMAGES') {
                    $where[] = "a.action IN ('UPLOAD_IMAGE', 'DELETE_IMAGE')";
                } elseif ($action_type === 'SERVICES') {
                    $where[] = "a.action IN ('CREATE_SERVICE', 'UPDATE_SERVICE', 'DELETE_SERVICE')";
                } else {
                    $where[] = "a.action = ?";
                    $params[] = $action_type;
                }
            }

            if ($user_filter !== '') {
                $where[] = "a.user_name = ?";
                $params[] = $user_filter;
            }

            if ($date_from !== '') {
                $where[] = "DATE(a.created_at) >= ?";
                $params[] = $date_from;
            }

            if ($date_to !== '') {
                $where[] = "DATE(a.created_at) <= ?";
                $params[] = $date_to;
            }

            if ($q !== '') {
                $where[] = "(
                    a.user_name LIKE ? 
                    OR a.action LIKE ? 
                    OR a.details_json LIKE ? 
                    OR a.ip_address LIKE ? 
                    OR c.id_ci LIKE ? 
                    OR c.hostname_nombre LIKE ?
                )";
                $searchWildcard = "%$q%";
                $params[] = $searchWildcard;
                $params[] = $searchWildcard;
                $params[] = $searchWildcard;
                $params[] = $searchWildcard;
                $params[] = $searchWildcard;
                $params[] = $searchWildcard;
            }

            $whereSql = implode(' AND ', $where);

            // Conteo total para paginación con filtros activos
            $countSql = "SELECT COUNT(*) FROM cmdb_sonda_audit_logs a 
                         LEFT JOIN cmdb_sonda_cis c ON a.ci_id = c.id 
                         WHERE $whereSql";
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($params);
            $totalCount = (int)$countStmt->fetchColumn();

            // Estadísticas generales (KPIs de Bitácora)
            $statsSql = "
                SELECT 
                    COUNT(*) as total_events,
                    SUM(CASE WHEN action = 'CREATE' THEN 1 ELSE 0 END) as total_creates,
                    SUM(CASE WHEN action = 'UPDATE' THEN 1 ELSE 0 END) as total_updates,
                    SUM(CASE WHEN action = 'DELETE' THEN 1 ELSE 0 END) as total_deletes,
                    SUM(CASE WHEN action IN ('ADD_RELATION', 'DELETE_RELATION') THEN 1 ELSE 0 END) as total_relations,
                    SUM(CASE WHEN action IN ('UPLOAD_IMAGE', 'DELETE_IMAGE') THEN 1 ELSE 0 END) as total_images,
                    SUM(CASE WHEN action IN ('CREATE_SERVICE', 'UPDATE_SERVICE') THEN 1 ELSE 0 END) as total_services,
                    COUNT(DISTINCT user_name) as unique_users_count
                FROM cmdb_sonda_audit_logs
            ";
            $stats = $pdo->query($statsSql)->fetch(PDO::FETCH_ASSOC) ?: [];

            // Lista de usuarios únicos para el selector de filtro
            $usersList = $pdo->query("SELECT DISTINCT user_name FROM cmdb_sonda_audit_logs WHERE user_name IS NOT NULL AND user_name != '' ORDER BY user_name ASC")->fetchAll(PDO::FETCH_COLUMN);

            // Consulta paginada
            $querySql = "
                SELECT 
                    a.id,
                    a.ci_id,
                    a.action,
                    a.user_name,
                    a.details_json,
                    a.ip_address,
                    a.created_at,
                    c.id_ci as current_id_ci,
                    c.hostname_nombre as current_hostname,
                    c.tipo_ci as current_tipo_ci,
                    c.cliente as current_cliente
                FROM cmdb_sonda_audit_logs a
                LEFT JOIN cmdb_sonda_cis c ON a.ci_id = c.id
                WHERE $whereSql
                ORDER BY a.id DESC
                LIMIT $limit OFFSET $offset
            ";
            $stmt = $pdo->prepare($querySql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Procesar y enriquecer logs
            $logs = [];
            foreach ($rows as $row) {
                $details = [];
                if (!empty($row['details_json'])) {
                    $details = json_decode($row['details_json'], true) ?: [];
                }

                $displayIdCi = $row['current_id_ci'] ?? ($details['id_ci'] ?? ($details['snapshot']['id_ci'] ?? ($details['service_code'] ?? 'N/A')));
                $displayHostname = $row['current_hostname'] ?? ($details['hostname'] ?? ($details['snapshot']['hostname_nombre'] ?? ($details['nombre_servicio'] ?? 'Sistema / Módulo')));

                $badgeClass = 'secondary';
                $actionLabel = $row['action'];
                switch ($row['action']) {
                    case 'CREATE':
                        $badgeClass = 'success';
                        $actionLabel = 'Creación de CI';
                        break;
                    case 'UPDATE':
                        $badgeClass = 'primary';
                        $actionLabel = 'Modificación de CI';
                        break;
                    case 'DELETE':
                        $badgeClass = 'danger';
                        $actionLabel = 'Baja de CI';
                        break;
                    case 'ADD_RELATION':
                        $badgeClass = 'info';
                        $actionLabel = 'Nueva Relación';
                        break;
                    case 'DELETE_RELATION':
                        $badgeClass = 'warning';
                        $actionLabel = 'Relación Eliminada';
                        break;
                    case 'UPLOAD_IMAGE':
                        $badgeClass = 'teal';
                        $actionLabel = 'Evidencia Fotográfica';
                        break;
                    case 'DELETE_IMAGE':
                        $badgeClass = 'secondary';
                        $actionLabel = 'Imagen Eliminada';
                        break;
                    case 'CREATE_SERVICE':
                        $badgeClass = 'success';
                        $actionLabel = 'Nuevo Servicio';
                        break;
                    case 'UPDATE_SERVICE':
                        $badgeClass = 'primary';
                        $actionLabel = 'Servicio Modificado';
                        break;
                    case 'RECALCULATE_SUPPORT':
                        $badgeClass = 'dark';
                        $actionLabel = 'Recálculo Soporte';
                        break;
                }

                $summary = $details['summary'] ?? '';
                if (empty($summary)) {
                    if ($row['action'] === 'UPDATE') {
                        $cnt = isset($details['changes']) ? count($details['changes']) : (isset($details['changes_count']) ? (int)$details['changes_count'] : 0);
                        $summary = $cnt > 0 ? "Se modificaron $cnt campos en el CI." : "Actualización de atributos del CI.";
                    } elseif ($row['action'] === 'CREATE') {
                        $summary = "Alta de nuevo Elemento de Configuración ($displayHostname).";
                    } elseif ($row['action'] === 'DELETE') {
                        $summary = "Baja definitiva del CI del inventario ($displayHostname).";
                    } elseif ($row['action'] === 'UPLOAD_IMAGE') {
                        $summary = "Carga de evidencia fotográfica: " . ($details['file_name'] ?? '');
                    } elseif ($row['action'] === 'ADD_RELATION') {
                        $summary = "Registro de relación de dependencia (" . ($details['type'] ?? 'dependencia') . ").";
                    } else {
                        $summary = "Operación del sistema registrada.";
                    }
                }

                $logs[] = [
                    'id' => (int)$row['id'],
                    'ci_id' => $row['ci_id'] ? (int)$row['ci_id'] : null,
                    'action' => $row['action'],
                    'action_label' => $actionLabel,
                    'badge_class' => $badgeClass,
                    'user_name' => $row['user_name'] ?: 'sistema',
                    'ip_address' => $row['ip_address'] ?: '127.0.0.1',
                    'created_at' => $row['created_at'],
                    'display_id_ci' => $displayIdCi,
                    'display_hostname' => $displayHostname,
                    'summary' => $summary,
                    'changes_count' => isset($details['changes']) ? count($details['changes']) : (isset($details['changes_count']) ? (int)$details['changes_count'] : 0),
                    'changes' => $details['changes'] ?? [],
                    'details' => $details
                ];
            }

            json_response([
                'success' => true,
                'stats' => $stats,
                'users' => $usersList,
                'pagination' => [
                    'total' => $totalCount,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => max(1, ceil($totalCount / $limit))
                ],
                'data' => $logs
            ]);

        } catch (Exception $e) {
            json_response(['success' => false, 'error' => 'Error al consultar bitácora de auditoría: ' . $e->getMessage()], 500);
        }
        break;

    // ====================================================================
    // 16. EXPORTAR BITÁCORA DE HISTORIAL A CSV (EXCEL COMPATIBLE UTF-8)
    // ====================================================================
    case 'export_audit_csv':
        try {
            $ci_id = isset($_GET['ci_id']) && $_GET['ci_id'] !== '' ? (int)$_GET['ci_id'] : null;
            $action_type = trim($_GET['action_type'] ?? '');
            $user_filter = trim($_GET['user'] ?? '');
            $date_from = trim($_GET['date_from'] ?? '');
            $date_to = trim($_GET['date_to'] ?? '');
            $q = trim($_GET['q'] ?? '');

            $where = ["1=1"];
            $params = [];

            if ($ci_id !== null && $ci_id > 0) {
                $where[] = "a.ci_id = ?";
                $params[] = $ci_id;
            }

            if ($action_type !== '') {
                if ($action_type === 'CREATE') {
                    $where[] = "a.action = 'CREATE'";
                } elseif ($action_type === 'UPDATE') {
                    $where[] = "a.action = 'UPDATE'";
                } elseif ($action_type === 'DELETE') {
                    $where[] = "a.action = 'DELETE'";
                } elseif ($action_type === 'RELATIONS') {
                    $where[] = "a.action IN ('ADD_RELATION', 'DELETE_RELATION')";
                } elseif ($action_type === 'IMAGES') {
                    $where[] = "a.action IN ('UPLOAD_IMAGE', 'DELETE_IMAGE')";
                } elseif ($action_type === 'SERVICES') {
                    $where[] = "a.action IN ('CREATE_SERVICE', 'UPDATE_SERVICE', 'DELETE_SERVICE')";
                } else {
                    $where[] = "a.action = ?";
                    $params[] = $action_type;
                }
            }

            if ($user_filter !== '') {
                $where[] = "a.user_name = ?";
                $params[] = $user_filter;
            }

            if ($date_from !== '') {
                $where[] = "DATE(a.created_at) >= ?";
                $params[] = $date_from;
            }

            if ($date_to !== '') {
                $where[] = "DATE(a.created_at) <= ?";
                $params[] = $date_to;
            }

            if ($q !== '') {
                $where[] = "(
                    a.user_name LIKE ? 
                    OR a.action LIKE ? 
                    OR a.details_json LIKE ? 
                    OR a.ip_address LIKE ? 
                    OR c.id_ci LIKE ? 
                    OR c.hostname_nombre LIKE ?
                )";
                $searchWildcard = "%$q%";
                $params[] = $searchWildcard;
                $params[] = $searchWildcard;
                $params[] = $searchWildcard;
                $params[] = $searchWildcard;
                $params[] = $searchWildcard;
                $params[] = $searchWildcard;
            }

            $whereSql = implode(' AND ', $where);

            $querySql = "
                SELECT 
                    a.id,
                    a.created_at,
                    a.user_name,
                    a.action,
                    a.ip_address,
                    a.details_json,
                    c.id_ci as current_id_ci,
                    c.hostname_nombre as current_hostname
                FROM cmdb_sonda_audit_logs a
                LEFT JOIN cmdb_sonda_cis c ON a.ci_id = c.id
                WHERE $whereSql
                ORDER BY a.id DESC
                LIMIT 5000
            ";
            $stmt = $pdo->prepare($querySql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $filename = 'cmdb_sonda_bitacora_auditoria_' . date('Ymd_His') . '.csv';
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');

            // BOM UTF-8 para apertura directa en Microsoft Excel
            echo "\xEF\xBB\xBF";

            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Fecha y Hora', 'Usuario Responsable', 'Acción', 'ID CI', 'Hostname', 'Resumen / Detalle del Cambio', 'Dirección IP'], ';');

            foreach ($rows as $r) {
                $details = !empty($r['details_json']) ? (json_decode($r['details_json'], true) ?: []) : [];
                $idCi = $r['current_id_ci'] ?? ($details['id_ci'] ?? ($details['snapshot']['id_ci'] ?? ($details['service_code'] ?? 'N/A')));
                $host = $r['current_hostname'] ?? ($details['hostname'] ?? ($details['snapshot']['hostname_nombre'] ?? ($details['nombre_servicio'] ?? 'N/A')));
                $sum = $details['summary'] ?? ($details['file_name'] ?? $r['action']);

                if ($r['action'] === 'UPDATE' && !empty($details['changes'])) {
                    $diffParts = [];
                    foreach ($details['changes'] as $f => $ch) {
                        $lbl = $ch['label'] ?? $f;
                        $diffParts[] = "$lbl: '{$ch['old']}' -> '{$ch['new']}'";
                    }
                    $sum .= ' | Cambios: ' . implode('; ', $diffParts);
                }

                fputcsv($out, [
                    $r['id'],
                    $r['created_at'],
                    $r['user_name'] ?: 'sistema',
                    $r['action'],
                    $idCi,
                    $host,
                    $sum,
                    $r['ip_address'] ?: '127.0.0.1'
                ], ';');
            }
            fclose($out);
            exit;

        } catch (Exception $e) {
            json_response(['success' => false, 'error' => 'Error al exportar bitácora: ' . $e->getMessage()], 500);
        }
        break;

    default:
        json_response(['success' => false, 'error' => "Acción desconocida: '$action'"], 400);
        break;
}

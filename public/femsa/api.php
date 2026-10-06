<?php
/**
 * Backend API para el Módulo FEMSA - Control y Entrega de Servicio
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/permissions_helper.php';

$user = current_user();
if (!$user) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

if (!has_role('SUPER_ADMIN') && !has_module_access('femsa')) {
    echo json_encode(['success' => false, 'error' => 'Acceso denegado al módulo FEMSA']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$pdo = getPDO();

if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión a la base de datos']);
    exit;
}

switch ($action) {
    case 'list':
        try {
            $q = trim($_GET['q'] ?? '');
            $status = trim($_GET['status'] ?? '');
            $activity_type = trim($_GET['activity_type'] ?? '');
            
            $where = [];
            $params = [];

            if ($q !== '') {
                $where[] = "(ticket_code LIKE ? OR femsa_requester LIKE ? OR sonda_analyst LIKE ? OR work_description LIKE ? OR script_name LIKE ?)";
                $param_q = '%' . $q . '%';
                $params = array_merge($params, [$param_q, $param_q, $param_q, $param_q, $param_q]);
            }

            if ($status !== '') {
                $where[] = "status = ?";
                $params[] = $status;
            }

            if ($activity_type !== '') {
                $where[] = "activity_type = ?";
                $params[] = $activity_type;
            }

            $whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

            $stmt = $pdo->prepare("SELECT * FROM femsa_requirements $whereSql ORDER BY id DESC");
            $stmt->execute($params);
            $items = $stmt->fetchAll();

            // Stats
            $stats_stmt = $pdo->query("SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'Borrador' THEN 1 ELSE 0 END) as borrador,
                SUM(CASE WHEN status = 'En Proceso' THEN 1 ELSE 0 END) as en_proceso,
                SUM(CASE WHEN status = 'Entregado' THEN 1 ELSE 0 END) as entregado,
                SUM(CASE WHEN status IN ('Aprobado', 'Finalizado') THEN 1 ELSE 0 END) as aprobado,
                SUM(CASE WHEN status = 'Cancelado' THEN 1 ELSE 0 END) as cancelado
            FROM femsa_requirements");
            $stats = $stats_stmt->fetch();

            echo json_encode(['success' => true, 'data' => $items, 'stats' => $stats]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'get':
        try {
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) {
                echo json_encode(['success' => false, 'error' => 'ID inválido']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT * FROM femsa_requirements WHERE id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch();

            if (!$item) {
                echo json_encode(['success' => false, 'error' => 'Requerimiento no encontrado']);
                exit;
            }

            // Historial
            $hist_stmt = $pdo->prepare("SELECT * FROM femsa_requirement_history WHERE requirement_id = ? ORDER BY id DESC");
            $hist_stmt->execute([$id]);
            $history = $hist_stmt->fetchAll();

            echo json_encode(['success' => true, 'data' => $item, 'history' => $history]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'save':
        try {
            $id = (int)($_POST['id'] ?? 0);
            $ticket_code = trim($_POST['ticket_code'] ?? '');
            $emission_date = trim($_POST['emission_date'] ?? date('Y-m-d'));
            $femsa_requester = trim($_POST['femsa_requester'] ?? '');
            $sonda_analyst = trim($_POST['sonda_analyst'] ?? 'Marco Vizcaíno / Recurso en Sitio');
            $sonda_supervisor = trim($_POST['sonda_supervisor'] ?? '');
            $activity_type = trim($_POST['activity_type'] ?? 'Soporte');
            $work_description = trim($_POST['work_description'] ?? '');
            $script_name = trim($_POST['script_name'] ?? '');
            $script_language = trim($_POST['script_language'] ?? '');
            $repository_url = trim($_POST['repository_url'] ?? '');
            
            $limit_soporte_estandar = $_POST['limit_soporte_estandar'] ?? 'SI';
            $obs_soporte_estandar = trim($_POST['obs_soporte_estandar'] ?? 'N/A');
            $limit_desarrollo_evolutivo = $_POST['limit_desarrollo_evolutivo'] ?? 'NO';
            $obs_desarrollo_evolutivo = trim($_POST['obs_desarrollo_evolutivo'] ?? 'Fuera de alcance si aplica');
            $limit_herramientas_femsa = $_POST['limit_herramientas_femsa'] ?? 'SI';
            $obs_herramientas_femsa = trim($_POST['obs_herramientas_femsa'] ?? 'N/A');
            
            $status = $_POST['status'] ?? 'Borrador';
            $current_stage = (int)($_POST['current_stage'] ?? 1);
            if ($current_stage < 1) $current_stage = 1;
            if ($current_stage > 9) $current_stage = 9;

            $stage_data_raw = $_POST['stage_data_json'] ?? '';
            $stage_data_json = !empty($stage_data_raw) ? $stage_data_raw : null;

            $femsa_approved_by = trim($_POST['femsa_approved_by'] ?? '');
            $femsa_approval_date = !empty($_POST['femsa_approval_date']) ? $_POST['femsa_approval_date'] : null;
            $sonda_delivered_by = trim($_POST['sonda_delivered_by'] ?? 'Marco Vizcaíno');
            $sonda_delivery_date = !empty($_POST['sonda_delivery_date']) ? $_POST['sonda_delivery_date'] : null;

            if (empty($ticket_code) || empty($femsa_requester) || empty($work_description)) {
                echo json_encode(['success' => false, 'error' => 'Por favor complete todos los campos obligatorios (Ticket, Solicitante, Descripción)']);
                exit;
            }

            $current_username = $user['username'] ?? 'Usuario';

            if ($id > 0) {
                // Obtener snapshot anterior
                $old_stmt = $pdo->prepare("SELECT * FROM femsa_requirements WHERE id = ?");
                $old_stmt->execute([$id]);
                $old_data = $old_stmt->fetch();

                if (!$old_data) {
                    echo json_encode(['success' => false, 'error' => 'Requerimiento no encontrado']);
                    exit;
                }

                $stmt = $pdo->prepare("UPDATE femsa_requirements SET
                    ticket_code = ?,
                    emission_date = ?,
                    femsa_requester = ?,
                    sonda_analyst = ?,
                    sonda_supervisor = ?,
                    activity_type = ?,
                    work_description = ?,
                    script_name = ?,
                    script_language = ?,
                    repository_url = ?,
                    limit_soporte_estandar = ?,
                    obs_soporte_estandar = ?,
                    limit_desarrollo_evolutivo = ?,
                    obs_desarrollo_evolutivo = ?,
                    limit_herramientas_femsa = ?,
                    obs_herramientas_femsa = ?,
                    status = ?,
                    current_stage = ?,
                    stage_data_json = ?,
                    femsa_approved_by = ?,
                    femsa_approval_date = ?,
                    sonda_delivered_by = ?,
                    sonda_delivery_date = ?
                WHERE id = ?");

                $stmt->execute([
                    $ticket_code,
                    $emission_date,
                    $femsa_requester,
                    $sonda_analyst,
                    $sonda_supervisor,
                    $activity_type,
                    $work_description,
                    $script_name,
                    $script_language,
                    $repository_url,
                    $limit_soporte_estandar,
                    $obs_soporte_estandar,
                    $limit_desarrollo_evolutivo,
                    $obs_desarrollo_evolutivo,
                    $limit_herramientas_femsa,
                    $obs_herramientas_femsa,
                    $status,
                    $current_stage,
                    $stage_data_json,
                    $femsa_approved_by,
                    $femsa_approval_date,
                    $sonda_delivered_by,
                    $sonda_delivery_date,
                    $id
                ]);

                // Registrar cambios
                $changes = [];
                if ($old_data['ticket_code'] !== $ticket_code) $changes[] = "Código Ticket: {$old_data['ticket_code']} ➔ {$ticket_code}";
                if ($old_data['status'] !== $status) $changes[] = "Estado: {$old_data['status']} ➔ {$status}";
                if ((int)$old_data['current_stage'] !== $current_stage) $changes[] = "Etapa Proceso: Etapa {$old_data['current_stage']} ➔ Etapa {$current_stage}";
                if ($old_data['femsa_requester'] !== $femsa_requester) $changes[] = "Solicitante FEMSA: {$old_data['femsa_requester']} ➔ {$femsa_requester}";
                if ($old_data['activity_type'] !== $activity_type) $changes[] = "Tipo Actividad: {$old_data['activity_type']} ➔ {$activity_type}";
                if ($old_data['script_name'] !== $script_name) $changes[] = "Script: {$old_data['script_name']} ➔ {$script_name}";

                $detailText = count($changes) > 0 ? "Actualizado: " . implode(' | ', $changes) : "Modificación general del requerimiento y etapa de proceso";

                $hist_stmt = $pdo->prepare("INSERT INTO femsa_requirement_history (requirement_id, action, changed_by_user, change_details, snapshot_json) VALUES (?, 'MODIFICACION', ?, ?, ?)");
                $hist_stmt->execute([$id, $current_username, $detailText, json_encode($old_data, JSON_UNESCAPED_UNICODE)]);

                echo json_encode(['success' => true, 'id' => $id, 'message' => 'Requerimiento y etapa actualizados con éxito']);

            } else {
                // Insertar nuevo requerimiento
                $stmt = $pdo->prepare("INSERT INTO femsa_requirements (
                    ticket_code, emission_date, femsa_requester, sonda_analyst, sonda_supervisor,
                    activity_type, work_description, script_name, script_language, repository_url,
                    limit_soporte_estandar, obs_soporte_estandar, limit_desarrollo_evolutivo, obs_desarrollo_evolutivo,
                    limit_herramientas_femsa, obs_herramientas_femsa, status, current_stage, stage_data_json,
                    femsa_approved_by, femsa_approval_date, sonda_delivered_by, sonda_delivery_date, created_by
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?
                )");

                $stmt->execute([
                    $ticket_code,
                    $emission_date,
                    $femsa_requester,
                    $sonda_analyst,
                    $sonda_supervisor,
                    $activity_type,
                    $work_description,
                    $script_name,
                    $script_language,
                    $repository_url,
                    $limit_soporte_estandar,
                    $obs_soporte_estandar,
                    $limit_desarrollo_evolutivo,
                    $obs_desarrollo_evolutivo,
                    $limit_herramientas_femsa,
                    $obs_herramientas_femsa,
                    $status,
                    $current_stage,
                    $stage_data_json,
                    $femsa_approved_by,
                    $femsa_approval_date,
                    $sonda_delivered_by,
                    $sonda_delivery_date,
                    $user['id'] ?? null
                ]);

                $new_id = $pdo->lastInsertId();

                $hist_stmt = $pdo->prepare("INSERT INTO femsa_requirement_history (requirement_id, action, changed_by_user, change_details) VALUES (?, 'CREACION', ?, ?)");
                $hist_stmt->execute([$new_id, $current_username, "Creación de requerimiento Ticket #{$ticket_code} en Etapa 1 (Petición)"]);

                echo json_encode(['success' => true, 'id' => $new_id, 'message' => 'Requerimiento creado exitosamente']);
            }

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'update_stage':
        try {
            $id = (int)($_POST['id'] ?? 0);
            $stage = (int)($_POST['stage'] ?? 1);
            $stage_data_raw = $_POST['stage_data_json'] ?? '';

            if (!$id || $stage < 1 || $stage > 9) {
                echo json_encode(['success' => false, 'error' => 'Parámetros de etapa inválidos']);
                exit;
            }

            $old_stmt = $pdo->prepare("SELECT current_stage, ticket_code, stage_data_json FROM femsa_requirements WHERE id = ?");
            $old_stmt->execute([$id]);
            $req = $old_stmt->fetch();

            if (!$req) {
                echo json_encode(['success' => false, 'error' => 'Requerimiento no encontrado']);
                exit;
            }

            $old_stage = (int)$req['current_stage'];
            $stage_data = !empty($stage_data_raw) ? $stage_data_raw : $req['stage_data_json'];

            $stmt = $pdo->prepare("UPDATE femsa_requirements SET current_stage = ?, stage_data_json = ? WHERE id = ?");
            $stmt->execute([$stage, $stage_data, $id]);

            $current_username = $user['username'] ?? 'Usuario';
            $hist_stmt = $pdo->prepare("INSERT INTO femsa_requirement_history (requirement_id, action, changed_by_user, change_details) VALUES (?, 'CAMBIO_ETAPA', ?, ?)");
            $hist_stmt->execute([$id, $current_username, "Etapa del proceso actualizada de Etapa {$old_stage} a Etapa {$stage}"]);

            echo json_encode(['success' => true, 'message' => "Etapa de proceso del ticket #{$req['ticket_code']} actualizada a la Etapa {$stage}"]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'change_status':
        try {
            $id = (int)($_POST['id'] ?? 0);
            $new_status = $_POST['status'] ?? '';
            $valid_statuses = ['Borrador', 'En Proceso', 'Entregado', 'Aprobado', 'Cancelado'];

            if (!$id || !in_array($new_status, $valid_statuses)) {
                echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
                exit;
            }

            $old_stmt = $pdo->prepare("SELECT status, ticket_code FROM femsa_requirements WHERE id = ?");
            $old_stmt->execute([$id]);
            $req = $old_stmt->fetch();

            if (!$req) {
                echo json_encode(['success' => false, 'error' => 'Requerimiento no encontrado']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE femsa_requirements SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $id]);

            $current_username = $user['username'] ?? 'Usuario';
            $hist_stmt = $pdo->prepare("INSERT INTO femsa_requirement_history (requirement_id, action, changed_by_user, change_details) VALUES (?, 'CAMBIO_ESTADO', ?, ?)");
            $hist_stmt->execute([$id, $current_username, "Estado cambiado de '{$req['status']}' a '{$new_status}'"]);

            echo json_encode(['success' => true, 'message' => "Estado de ticket #{$req['ticket_code']} actualizado a {$new_status}"]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'get_next_code':
        try {
            $year = date('Y');
            $stmt = $pdo->query("SELECT MAX(id) as max_id FROM femsa_requirements");
            $res = $stmt->fetch();
            $nextNum = ((int)($res['max_id'] ?? 0)) + 1;
            $nextCode = sprintf("REQ-FEMSA-%s-%04d", $year, $nextNum);
            echo json_encode(['success' => true, 'next_code' => $nextCode, 'today' => date('Y-m-d')]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'upload_attachment':
        try {
            $req_id = (int)($_POST['requirement_id'] ?? 0);
            $ticket_code = trim($_POST['ticket_code'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if (!isset($_FILES['attachment_file']) || $_FILES['attachment_file']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'error' => 'No se recibió ningún archivo o hubo un error en la transferencia']);
                exit;
            }

            $file = $_FILES['attachment_file'];
            $original_name = basename($file['name']);
            $file_size = $file['size'];
            $mime_type = $file['type'];

            if (empty($ticket_code)) {
                $ticket_code = 'REQ-FEMSA-' . date('Ymd');
            }

            $sanitized_ticket = preg_replace('/[^A-Za-z0-9_\-]/', '_', $ticket_code);
            $upload_dir = ROOT_PATH . '/storage/femsa_attachments/' . $sanitized_ticket;
            if (!is_dir($upload_dir)) {
                @mkdir($upload_dir, 0777, true);
            }

            $timestamp = date('Ymd_His');
            $stored_name = $timestamp . '_' . preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $original_name);
            $target_path = $upload_dir . '/' . $stored_name;

            if (!move_uploaded_file($file['tmp_name'], $target_path)) {
                echo json_encode(['success' => false, 'error' => 'No se pudo guardar el archivo en el servidor']);
                exit;
            }

            $rel_path = 'storage/femsa_attachments/' . $sanitized_ticket . '/' . $stored_name;
            $current_username = $user['username'] ?? 'Usuario';

            $stmt = $pdo->prepare("INSERT INTO femsa_attachments (requirement_id, original_name, stored_name, file_path, file_size, mime_type, description, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$req_id, $original_name, $stored_name, $rel_path, $file_size, $mime_type, $description, $current_username]);

            echo json_encode(['success' => true, 'message' => 'Archivo adjuntado correctamente']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'list_attachments':
        try {
            $req_id = (int)($_GET['requirement_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM femsa_attachments WHERE requirement_id = ? ORDER BY id DESC");
            $stmt->execute([$req_id]);
            $attachments = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $attachments]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'delete_attachment':
        try {
            $att_id = (int)($_POST['attachment_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM femsa_attachments WHERE id = ?");
            $stmt->execute([$att_id]);
            $att = $stmt->fetch();

            if ($att) {
                if ($att['mime_type'] !== 'gitlab/link') {
                    $full_path = ROOT_PATH . '/' . $att['file_path'];
                    if (file_exists($full_path)) {
                        @unlink($full_path);
                    }
                }
                $del = $pdo->prepare("DELETE FROM femsa_attachments WHERE id = ?");
                $del->execute([$att_id]);
            }

            echo json_encode(['success' => true, 'message' => 'Adjunto eliminado correctamente']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'list_diagrams':
        try {
            $diagrams = [];
            
            // Diagramas Visio / Draw.io
            $stmt1 = $pdo->query("SELECT id, title, description, 'visio' as type, updated_at FROM visio_diagrams ORDER BY updated_at DESC");
            if ($stmt1) {
                while ($row = $stmt1->fetch()) {
                    $diagrams[] = [
                        'id' => $row['id'],
                        'title' => $row['title'],
                        'description' => $row['description'],
                        'type' => 'visio',
                        'type_label' => 'Visio / Draw.io',
                        'updated_at' => $row['updated_at']
                    ];
                }
            }

            // Diagramas BPMN
            $stmt2 = $pdo->query("SELECT id, title, description, 'bpmn' as type, updated_at FROM bpmn_diagrams ORDER BY updated_at DESC");
            if ($stmt2) {
                while ($row = $stmt2->fetch()) {
                    $diagrams[] = [
                        'id' => $row['id'],
                        'title' => $row['title'],
                        'description' => $row['description'],
                        'type' => 'bpmn',
                        'type_label' => 'Proceso BPMN',
                        'updated_at' => $row['updated_at']
                    ];
                }
            }

            echo json_encode(['success' => true, 'data' => $diagrams]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'list_gitlab_files':
        try {
            $stmt = $pdo->query("SELECT f.id, f.filename, f.file_type, f.updated_at, (SELECT COUNT(*) FROM gitlab_versions v WHERE v.file_id = f.id) as version_count FROM gitlab_files f ORDER BY f.filename ASC");
            $files = $stmt ? $stmt->fetchAll() : [];
            echo json_encode(['success' => true, 'data' => $files]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'add_gitlab_attachment':
        try {
            $req_id = (int)($_POST['requirement_id'] ?? 0);
            $gitlab_file_id = (int)($_POST['gitlab_file_id'] ?? 0);
            $description = trim($_POST['description'] ?? '');

            if (!$req_id || !$gitlab_file_id) {
                echo json_encode(['success' => false, 'error' => 'Requerimiento o archivo de GitLab no seleccionado']);
                exit;
            }

            $stmt_f = $pdo->prepare("SELECT * FROM gitlab_files WHERE id = ?");
            $stmt_f->execute([$gitlab_file_id]);
            $gfile = $stmt_f->fetch();

            if (!$gfile) {
                echo json_encode(['success' => false, 'error' => 'Archivo de GitLab no encontrado']);
                exit;
            }

            // Obtener datos del requerimiento
            $stmt_req = $pdo->prepare("SELECT * FROM femsa_requirements WHERE id = ?");
            $stmt_req->execute([$req_id]);
            $req = $stmt_req->fetch();
            $ticket_code = $req ? $req['ticket_code'] : 'REQ-FEMSA';
            $sanitized_ticket = preg_replace('/[^A-Za-z0-9_\-]/', '_', $ticket_code);

            // Obtener contenido de código de la última versión de GitLab
            $stmt_v = $pdo->prepare("SELECT content FROM gitlab_versions WHERE file_id = ? ORDER BY version_number DESC LIMIT 1");
            $stmt_v->execute([$gitlab_file_id]);
            $vrow = $stmt_v->fetch();
            $code_content = $vrow ? $vrow['content'] : ($gfile['description_md'] ?? '');

            $script_name = $gfile['filename'];
            $file_basename = pathinfo($script_name, PATHINFO_FILENAME);
            if (empty($file_basename)) $file_basename = 'archivo_gitlab';

            $repo_dir = ROOT_PATH . '/public/femsa/repository';
            if (!is_dir($repo_dir)) {
                @mkdir($repo_dir, 0777, true);
            }

            // 1. Crear carpeta con el mismo nombre del archivo
            $script_folder = $repo_dir . '/' . $file_basename;
            if (!is_dir($script_folder)) {
                @mkdir($script_folder, 0777, true);
            }

            // 2. Crear archivo fuente dentro de la carpeta
            $file_target = $script_folder . '/' . basename($script_name);
            file_put_contents($file_target, $code_content);

            // 3. Crear archivo .md de documentación en la carpeta
            $md_filename = $file_basename . '.md';
            $md_repo_target = $script_folder . '/' . $md_filename;
            $readme_repo_target = $script_folder . '/README.md';

            $current_username = $user['username'] ?? 'Usuario';
            $md_content = "# Documentación de Entregable: {$script_name}\n\n";
            $md_content .= "**Código de Ticket:** `{$ticket_code}`  \n";
            $md_content .= "**Nombre de Archivo:** `{$script_name}`  \n";
            $md_content .= "**Carpeta en Repositorio:** `{$file_basename}/`  \n";
            $md_content .= "**Solicitante FEMSA:** " . htmlspecialchars($req['femsa_requester'] ?? 'N/A') . "  \n";
            $md_content .= "**Analista SONDA:** " . htmlspecialchars($req['sonda_analyst'] ?? 'Marco Vizcaíno') . "  \n";
            $md_content .= "**Fecha de Generación:** " . date('Y-m-d H:i:s') . "  \n";
            $md_content .= "**Registrado por:** `{$current_username}`  \n\n";
            $md_content .= "## Descripción del Requerimiento\n";
            $md_content .= ($req['work_description'] ?? 'Integración de archivo GitLab al requerimiento FEMSA.') . "\n\n";
            $md_content .= "## Código Fuente\n";
            $md_content .= "```" . strtolower($gfile['file_type'] ?? 'text') . "\n";
            $md_content .= $code_content . "\n";
            $md_content .= "```\n";

            file_put_contents($md_repo_target, $md_content);
            file_put_contents($readme_repo_target, $md_content);

            // 4. Guardar archivo .md en storage/femsa_attachments y adjuntar al requerimiento
            $upload_dir = ROOT_PATH . '/storage/femsa_attachments/' . $sanitized_ticket;
            if (!is_dir($upload_dir)) {
                @mkdir($upload_dir, 0777, true);
            }
            $md_storage_path = $upload_dir . '/' . $md_filename;
            file_put_contents($md_storage_path, $md_content);
            $rel_md_path = 'storage/femsa_attachments/' . $sanitized_ticket . '/' . $md_filename;

            // Insertar adjunto MD primero
            $stmt_md_att = $pdo->prepare("INSERT INTO femsa_attachments (requirement_id, original_name, stored_name, file_path, file_size, mime_type, description, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_md_att->execute([
                $req_id,
                '[MD Documentación] ' . $md_filename,
                $md_filename,
                $rel_md_path,
                strlen($md_content),
                'text/markdown',
                'Documentación Markdown generada automáticamente en carpeta ' . $file_basename . '/',
                $current_username
            ]);

            // Insertar enlace directo a GitLab
            $original_name = '[GitLab] ' . $gfile['filename'];
            $stored_name = 'gitlab_file_' . $gfile['id'];
            $file_path = '../plugins/gitlab/index.php?file_id=' . $gfile['id'];
            $mime_type = 'gitlab/link';
            if (empty($description)) {
                $description = 'Enlace directo a archivo fuente de GitLab (' . strtoupper($gfile['file_type'] ?? 'TXT') . ')';
            }

            $stmt = $pdo->prepare("INSERT INTO femsa_attachments (requirement_id, original_name, stored_name, file_path, file_size, mime_type, description, uploaded_by) VALUES (?, ?, ?, ?, 0, ?, ?, ?)");
            $stmt->execute([$req_id, $original_name, $stored_name, $file_path, $mime_type, $description, $current_username]);

            // 5. Enviar cambios a GitHub
            $git_remote = "https://blacic2016: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@github.com/blacic2016/femsa-sonda.git";
            $commit_msg = "[{$ticket_code}] Creación de carpeta {$file_basename}/ con {$script_name} y {$md_filename}";

            $cmds = [
                "cd " . escapeshellarg($repo_dir),
                "git init 2>&1",
                "git config user.name " . escapeshellarg("blacic2016"),
                "git config user.email " . escapeshellarg("blacic2016@gmail.com"),
                "git remote remove origin 2>/dev/null || true",
                "git remote add origin " . escapeshellarg($git_remote),
                "git branch -M main 2>&1",
                "git add . 2>&1",
                "git commit -m " . escapeshellarg($commit_msg) . " 2>&1",
                "git push -u origin main 2>&1"
            ];
            $full_command = implode(' && ', $cmds);
            @exec($full_command);

            echo json_encode(['success' => true, 'message' => 'Archivo MD (' . $md_filename . ') creado en carpeta ' . $file_basename . '/ y adjuntado correctamente']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'upload_layout_image':
        try {
            $req_id = (int)($_POST['requirement_id'] ?? 0);
            $ticket_code = trim($_POST['ticket_code'] ?? 'REQ-FEMSA');
            if (empty($ticket_code)) {
                $ticket_code = 'REQ-FEMSA';
            }

            if (!isset($_FILES['layout_image'])) {
                echo json_encode(['success' => false, 'error' => 'No se recibió ningún archivo de imagen']);
                exit;
            }

            $err_code = $_FILES['layout_image']['error'];
            if ($err_code !== UPLOAD_ERR_OK) {
                $err_msg = 'Error al subir la imagen (Código ' . $err_code . ')';
                if ($err_code === UPLOAD_ERR_INI_SIZE || $err_code === UPLOAD_ERR_FORM_SIZE) {
                    $err_msg = 'El archivo supera el tamaño máximo permitido por el servidor.';
                } elseif ($err_code === UPLOAD_ERR_NO_FILE) {
                    $err_msg = 'No se seleccionó ningún archivo.';
                }
                echo json_encode(['success' => false, 'error' => $err_msg]);
                exit;
            }

            $file = $_FILES['layout_image'];
            $original_name = basename($file['name']);
            $sanitized_ticket = preg_replace('/[^A-Za-z0-9_\-]/', '_', $ticket_code);
            $base_dir = ROOT_PATH . '/storage/femsa_attachments';
            if (!is_dir($base_dir)) {
                @mkdir($base_dir, 0777, true);
            }
            $upload_dir = $base_dir . '/' . $sanitized_ticket;
            if (!is_dir($upload_dir)) {
                if (!@mkdir($upload_dir, 0777, true) && !is_dir($upload_dir)) {
                    echo json_encode(['success' => false, 'error' => 'No se pudo crear el directorio de almacenamiento en el servidor']);
                    exit;
                }
            }

            $timestamp = date('Ymd_His');
            $stored_name = 'layout_' . $timestamp . '_' . preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $original_name);
            $target_path = $upload_dir . '/' . $stored_name;

            if (!move_uploaded_file($file['tmp_name'], $target_path)) {
                echo json_encode(['success' => false, 'error' => 'No se pudo guardar la imagen en la carpeta de destino']);
                exit;
            }

            $rel_path = 'storage/femsa_attachments/' . $sanitized_ticket . '/' . $stored_name;

            if ($req_id > 0) {
                $current_username = $user['username'] ?? 'Usuario';
                $stmt_att = $pdo->prepare("INSERT INTO femsa_attachments (requirement_id, original_name, stored_name, file_path, file_size, mime_type, description, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_att->execute([
                    $req_id,
                    '[Maquetación] ' . $original_name,
                    $stored_name,
                    $rel_path,
                    $file['size'],
                    $file['type'],
                    'Imagen de maquetación de arquitectura (Etapa 4)',
                    $current_username
                ]);
            }

            echo json_encode(['success' => true, 'file_path' => $rel_path, 'message' => 'Imagen de maquetación cargada exitosamente']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'git_push':
        try {
            $req_id = (int)($_POST['requirement_id'] ?? 0);
            $ticket_code = trim($_POST['ticket_code'] ?? 'REQ-FEMSA');
            $script_name = trim($_POST['script_name'] ?? 'script_automatizacion.py');
            $code_content = $_POST['code_content'] ?? '';
            $commit_message = trim($_POST['commit_message'] ?? '');
            $version_tag = trim($_POST['version_tag'] ?? 'v1.0.0');

            if (empty($script_name)) {
                $script_name = 'script_automatizacion.py';
            }

            $file_basename = pathinfo($script_name, PATHINFO_FILENAME);
            if (empty($file_basename)) $file_basename = 'script_automatizacion';

            if (empty($commit_message)) {
                $commit_message = "[{$ticket_code}] Actualización de script {$script_name} {$version_tag} en carpeta {$file_basename}/";
            }

            $repo_dir = ROOT_PATH . '/public/femsa/repository';
            if (!is_dir($repo_dir)) {
                @mkdir($repo_dir, 0777, true);
            }

            $sanitized_ticket = preg_replace('/[^A-Za-z0-9_\-]/', '_', $ticket_code);

            // 1. Crear carpeta específica con el nombre del archivo
            $script_folder = $repo_dir . '/' . $file_basename;
            if (!is_dir($script_folder)) {
                @mkdir($script_folder, 0777, true);
            }

            // 2. Guardar el archivo fuente dentro de la carpeta
            $file_target = $script_folder . '/' . basename($script_name);
            file_put_contents($file_target, $code_content);

            // 3. Crear el archivo MD de documentación dentro de la misma carpeta
            $md_filename = $file_basename . '.md';
            $md_repo_target = $script_folder . '/' . $md_filename;
            $readme_repo_target = $script_folder . '/README.md';

            // Datos del requerimiento para enriquecer el MD
            $stmt_req = $pdo->prepare("SELECT * FROM femsa_requirements WHERE id = ?");
            $stmt_req->execute([$req_id]);
            $req = $stmt_req->fetch();

            $current_username = $user['username'] ?? 'Usuario';
            $md_content = "# Documentación de Script - FEMSA / SONDA\n\n";
            $md_content .= "**Código de Ticket:** `{$ticket_code}`  \n";
            $md_content .= "**Nombre de Archivo:** `{$script_name}`  \n";
            $md_content .= "**Etiqueta de Versión:** `{$version_tag}`  \n";
            $md_content .= "**Carpeta en Repositorio:** `{$file_basename}/`  \n";
            $md_content .= "**Fecha de Push:** " . date('Y-m-d H:i:s') . "  \n";
            $md_content .= "**Autor:** `{$current_username}`  \n\n";
            $md_content .= "## Descripción del Requerimiento\n";
            $md_content .= ($req['work_description'] ?? 'Automatización y soporte para servicios FEMSA') . "\n\n";
            $md_content .= "## Mensaje de Commit\n";
            $md_content .= "```\n{$commit_message}\n```\n\n";
            $md_content .= "## Código Desarrollado\n";
            $md_content .= "```python\n{$code_content}\n```\n";

            file_put_contents($md_repo_target, $md_content);
            file_put_contents($readme_repo_target, $md_content);

            // 4. Copiar a femsa_attachments y registrar en BD si no existe
            $upload_dir = ROOT_PATH . '/storage/femsa_attachments/' . $sanitized_ticket;
            if (!is_dir($upload_dir)) {
                @mkdir($upload_dir, 0777, true);
            }
            $md_storage_path = $upload_dir . '/' . $md_filename;
            file_put_contents($md_storage_path, $md_content);
            $rel_md_path = 'storage/femsa_attachments/' . $sanitized_ticket . '/' . $md_filename;

            // Verificar si ya existe este adjunto MD
            $check_att = $pdo->prepare("SELECT id FROM femsa_attachments WHERE requirement_id = ? AND stored_name = ?");
            $check_att->execute([$req_id, $md_filename]);
            if (!$check_att->fetch()) {
                $stmt_md_att = $pdo->prepare("INSERT INTO femsa_attachments (requirement_id, original_name, stored_name, file_path, file_size, mime_type, description, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_md_att->execute([
                    $req_id,
                    '[MD Documentación] ' . $md_filename,
                    $md_filename,
                    $rel_md_path,
                    strlen($md_content),
                    'text/markdown',
                    'Documentación Markdown generada automáticamente en carpeta ' . $file_basename . '/',
                    $current_username
                ]);
            }

            // Guardar copia en la raíz del repo
            file_put_contents($repo_dir . '/' . basename($script_name), $code_content);

            $git_remote = "https://blacic2016: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@github.com/blacic2016/femsa-sonda.git";

            $cmds = [
                "cd " . escapeshellarg($repo_dir),
                "git init 2>&1",
                "git config user.name " . escapeshellarg("blacic2016"),
                "git config user.email " . escapeshellarg("blacic2016@gmail.com"),
                "git remote remove origin 2>/dev/null || true",
                "git remote add origin " . escapeshellarg($git_remote),
                "git branch -M main 2>&1",
                "git add . 2>&1",
                "git commit -m " . escapeshellarg($commit_message) . " 2>&1",
                "git push -u origin main 2>&1"
            ];

            $full_command = implode(' && ', $cmds);
            $output_lines = [];
            $return_code = 0;
            exec($full_command, $output_lines, $return_code);

            $git_output = implode("\n", $output_lines);
            $status = ($return_code === 0) ? 'SUCCESS' : 'ERROR';

            $commit_hash = '';
            $hash_output = [];
            exec("cd " . escapeshellarg($repo_dir) . " && git rev-parse --short HEAD 2>&1", $hash_output);
            if (count($hash_output) > 0) {
                $commit_hash = trim($hash_output[0]);
            }

            $stmt = $pdo->prepare("INSERT INTO femsa_git_logs (requirement_id, script_name, version_tag, commit_hash, commit_message, git_output, status, pushed_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$req_id, $script_name, $version_tag, $commit_hash, $commit_message, $git_output, $status, $current_username]);

            if ($return_code === 0) {
                echo json_encode([
                    'success' => true,
                    'message' => '¡Git Push realizado exitosamente! Archivo MD creado en carpeta ' . $file_basename . '/',
                    'commit_hash' => $commit_hash,
                    'git_output' => $git_output
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Error al ejecutar git push: ' . $git_output,
                    'git_output' => $git_output
                ]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'get_git_logs':
        try {
            $req_id = (int)($_GET['requirement_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM femsa_attachments WHERE 1=1");
            $stmt = $pdo->prepare("SELECT * FROM femsa_git_logs WHERE requirement_id = ? ORDER BY id DESC");
            $stmt->execute([$req_id]);
            $logs = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $logs]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'delete':
        try {
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) {
                echo json_encode(['success' => false, 'error' => 'ID inválido']);
                exit;
            }

            $old_stmt = $pdo->prepare("SELECT * FROM femsa_requirements WHERE id = ?");
            $old_stmt->execute([$id]);
            $req = $old_stmt->fetch();

            if (!$req) {
                echo json_encode(['success' => false, 'error' => 'Requerimiento no encontrado']);
                exit;
            }

            $current_username = $user['username'] ?? 'Usuario';
            
            // Guardar registro de eliminación
            $hist_stmt = $pdo->prepare("INSERT INTO femsa_requirement_history (requirement_id, action, changed_by_user, change_details, snapshot_json) VALUES (?, 'ELIMINACION', ?, ?, ?)");
            $hist_stmt->execute([$id, $current_username, "Eliminación del requerimiento Ticket #{$req['ticket_code']}", json_encode($req, JSON_UNESCAPED_UNICODE)]);

            $del_stmt = $pdo->prepare("DELETE FROM femsa_requirements WHERE id = ?");
            $del_stmt->execute([$id]);

            echo json_encode(['success' => true, 'message' => "Requerimiento Ticket #{$req['ticket_code']} eliminado correctamente"]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    // ===== BITÁCORA DE TRABAJOS DIARIOS =====
    case 'list_bitacora':
        try {
            $req_id = (int)($_GET['requirement_id'] ?? 0);
            if (!$req_id) {
                echo json_encode(['success' => false, 'error' => 'ID de requerimiento inválido']);
                exit;
            }
            $stmt = $pdo->prepare("SELECT * FROM femsa_bitacora WHERE requirement_id = ? ORDER BY fecha DESC, id DESC");
            $stmt->execute([$req_id]);
            $entries = $stmt->fetchAll();

            // Cargar imágenes de cada entrada
            foreach ($entries as &$entry) {
                $img_stmt = $pdo->prepare("SELECT * FROM femsa_bitacora_images WHERE bitacora_id = ? ORDER BY id ASC");
                $img_stmt->execute([$entry['id']]);
                $entry['images'] = $img_stmt->fetchAll();
            }
            unset($entry);

            echo json_encode(['success' => true, 'data' => $entries]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'save_bitacora':
        try {
            $bitacora_id = (int)($_POST['bitacora_id'] ?? 0);
            $req_id = (int)($_POST['requirement_id'] ?? 0);
            $fecha = trim($_POST['fecha'] ?? date('Y-m-d'));
            $tema = trim($_POST['tema'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $current_username = $user['username'] ?? 'Usuario';

            if (!$req_id || empty($tema) || empty($descripcion)) {
                echo json_encode(['success' => false, 'error' => 'Complete todos los campos obligatorios (Tema y Descripción)']);
                exit;
            }

            if ($bitacora_id > 0) {
                $stmt = $pdo->prepare("UPDATE femsa_bitacora SET fecha = ?, tema = ?, descripcion = ? WHERE id = ? AND requirement_id = ?");
                $stmt->execute([$fecha, $tema, $descripcion, $bitacora_id, $req_id]);
                echo json_encode(['success' => true, 'id' => $bitacora_id, 'message' => 'Entrada de bitácora actualizada']);
            } else {
                $stmt = $pdo->prepare("INSERT INTO femsa_bitacora (requirement_id, fecha, tema, descripcion, created_by) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$req_id, $fecha, $tema, $descripcion, $current_username]);
                $new_id = $pdo->lastInsertId();

                // Register in history
                $req_stmt = $pdo->prepare("SELECT ticket_code FROM femsa_requirements WHERE id = ?");
                $req_stmt->execute([$req_id]);
                $req_data = $req_stmt->fetch();
                $ticket_code = $req_data ? $req_data['ticket_code'] : 'N/A';

                $hist_stmt = $pdo->prepare("INSERT INTO femsa_requirement_history (requirement_id, action, changed_by_user, change_details) VALUES (?, 'BITACORA', ?, ?)");
                $hist_stmt->execute([$req_id, $current_username, "Nueva entrada de bitácora: \"{$tema}\" (Fecha: {$fecha})"]);

                echo json_encode(['success' => true, 'id' => $new_id, 'message' => 'Entrada de bitácora registrada exitosamente']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'delete_bitacora':
        try {
            $bitacora_id = (int)($_POST['bitacora_id'] ?? 0);
            if (!$bitacora_id) {
                echo json_encode(['success' => false, 'error' => 'ID de entrada inválido']);
                exit;
            }

            // Delete associated images from disk
            $img_stmt = $pdo->prepare("SELECT file_path FROM femsa_bitacora_images WHERE bitacora_id = ?");
            $img_stmt->execute([$bitacora_id]);
            while ($img = $img_stmt->fetch()) {
                $full_path = ROOT_PATH . '/' . $img['file_path'];
                if (file_exists($full_path)) {
                    @unlink($full_path);
                }
            }

            $pdo->prepare("DELETE FROM femsa_bitacora_images WHERE bitacora_id = ?")->execute([$bitacora_id]);
            $pdo->prepare("DELETE FROM femsa_bitacora WHERE id = ?")->execute([$bitacora_id]);

            echo json_encode(['success' => true, 'message' => 'Entrada de bitácora eliminada']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'upload_bitacora_image':
        try {
            $bitacora_id = (int)($_POST['bitacora_id'] ?? 0);
            $req_id = (int)($_POST['requirement_id'] ?? 0);

            if (!$bitacora_id || !$req_id) {
                echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
                exit;
            }

            $upload_dir = ROOT_PATH . '/storage/femsa_bitacora/' . $req_id;
            if (!is_dir($upload_dir)) { @mkdir($upload_dir, 0777, true); }

            // Handle base64 pasted image
            $base64_data = $_POST['image_base64'] ?? '';
            if (!empty($base64_data)) {
                if (preg_match('/^data:(image\/[\w\+]+);base64,(.+)$/', $base64_data, $matches)) {
                    $mime_type = $matches[1]; $decoded = base64_decode($matches[2]);
                    $ext = 'png';
                    if (strpos($mime_type, 'jpeg') !== false) $ext = 'jpg';
                    elseif (strpos($mime_type, 'gif') !== false) $ext = 'gif';
                    elseif (strpos($mime_type, 'webp') !== false) $ext = 'webp';
                } else {
                    $decoded = base64_decode($base64_data); $mime_type = 'image/png'; $ext = 'png';
                }

                $timestamp = date('Ymd_His') . '_' . uniqid();
                $file_name = "bitacora_{$bitacora_id}_{$timestamp}.{$ext}";
                $target_path = $upload_dir . '/' . $file_name;
                file_put_contents($target_path, $decoded);

                $rel_path = 'storage/femsa_bitacora/' . $req_id . '/' . $file_name;
                $stmt = $pdo->prepare("INSERT INTO femsa_bitacora_images (bitacora_id, requirement_id, file_name, original_name, file_path, file_size, mime_type, doc_type) VALUES (?, ?, ?, ?, ?, ?, ?, 'image')");
                $stmt->execute([$bitacora_id, $req_id, $file_name, 'Captura pegada', $rel_path, strlen($decoded), $mime_type]);

                echo json_encode(['success' => true, 'image_id' => $pdo->lastInsertId(), 'file_path' => $rel_path, 'message' => 'Imagen pegada correctamente']);
            }
            // Handle file upload (image)
            elseif (isset($_FILES['bitacora_image']) && $_FILES['bitacora_image']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['bitacora_image'];
                $original_name = basename($file['name']);
                $timestamp = date('Ymd_His') . '_' . uniqid();
                $stored_name = "bitacora_{$bitacora_id}_{$timestamp}_" . preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $original_name);
                $target_path = $upload_dir . '/' . $stored_name;
                move_uploaded_file($file['tmp_name'], $target_path);
                $rel_path = 'storage/femsa_bitacora/' . $req_id . '/' . $stored_name;

                $stmt = $pdo->prepare("INSERT INTO femsa_bitacora_images (bitacora_id, requirement_id, file_name, original_name, file_path, file_size, mime_type, doc_type) VALUES (?, ?, ?, ?, ?, ?, ?, 'image')");
                $stmt->execute([$bitacora_id, $req_id, $stored_name, $original_name, $rel_path, $file['size'], $file['type']]);

                echo json_encode(['success' => true, 'image_id' => $pdo->lastInsertId(), 'file_path' => $rel_path, 'message' => 'Imagen subida correctamente']);
            } else {
                echo json_encode(['success' => false, 'error' => 'No se recibió imagen válida']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'upload_bitacora_document':
        try {
            $bitacora_id = (int)($_POST['bitacora_id'] ?? 0);
            $req_id = (int)($_POST['requirement_id'] ?? 0);

            if (!$bitacora_id || !$req_id) {
                echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
                exit;
            }
            if (!isset($_FILES['bitacora_doc']) || $_FILES['bitacora_doc']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'error' => 'No se recibió archivo válido']);
                exit;
            }

            $file = $_FILES['bitacora_doc'];
            $original_name = basename($file['name']);
            $upload_dir = ROOT_PATH . '/storage/femsa_bitacora/' . $req_id;
            if (!is_dir($upload_dir)) { @mkdir($upload_dir, 0777, true); }

            $timestamp = date('Ymd_His') . '_' . uniqid();
            $stored_name = "doc_{$bitacora_id}_{$timestamp}_" . preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $original_name);
            $target_path = $upload_dir . '/' . $stored_name;

            if (!move_uploaded_file($file['tmp_name'], $target_path)) {
                echo json_encode(['success' => false, 'error' => 'No se pudo guardar el documento']);
                exit;
            }

            $rel_path = 'storage/femsa_bitacora/' . $req_id . '/' . $stored_name;
            $stmt = $pdo->prepare("INSERT INTO femsa_bitacora_images (bitacora_id, requirement_id, file_name, original_name, file_path, file_size, mime_type, doc_type) VALUES (?, ?, ?, ?, ?, ?, ?, 'document')");
            $stmt->execute([$bitacora_id, $req_id, $stored_name, $original_name, $rel_path, $file['size'], $file['type']]);

            echo json_encode(['success' => true, 'doc_id' => $pdo->lastInsertId(), 'file_path' => $rel_path, 'message' => 'Documento adjuntado correctamente: ' . $original_name]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'delete_bitacora_image':
        try {
            $image_id = (int)($_POST['image_id'] ?? 0);
            if (!$image_id) {
                echo json_encode(['success' => false, 'error' => 'ID de imagen inválido']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT * FROM femsa_bitacora_images WHERE id = ?");
            $stmt->execute([$image_id]);
            $img = $stmt->fetch();

            if ($img) {
                $full_path = ROOT_PATH . '/' . $img['file_path'];
                if (file_exists($full_path)) {
                    @unlink($full_path);
                }
                $pdo->prepare("DELETE FROM femsa_bitacora_images WHERE id = ?")->execute([$image_id]);
            }

            echo json_encode(['success' => true, 'message' => 'Imagen eliminada de la bitácora']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Acción no válida']);
        break;
}

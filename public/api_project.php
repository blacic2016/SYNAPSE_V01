<?php
/**
 * API de Gestión de Proyectos - CMDB VILASECA
 */
if (php_sapi_name() !== 'cli' && !headers_sent()) {
    header('Content-Type: application/json');
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/permissions_helper.php';

// Validar login
require_login();
if (!has_module_access('project')) {
    echo json_encode(['success' => false, 'error' => 'No tiene permisos para acceder a este módulo.']);
    exit();
}

require_once __DIR__ . '/../src/db.php';
$pdo = getPDO();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Helper para recalcular el progreso de un hito y de su proyecto
if (!function_exists('recalculateMilestoneProgress')) {
    function recalculateMilestoneProgress($pdo, $milestone_id) {
        // 1. Calcular promedio de tareas
        $stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(progress_percentage) as suma FROM project_tasks WHERE milestone_id = ?");
        $stmt->execute([$milestone_id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $progress = 0;
        if ($res && $res['total'] > 0) {
            $progress = round($res['suma'] / $res['total'], 2);
        }
        
        // Actualizar hito
        $stmtUpdate = $pdo->prepare("UPDATE project_milestones SET progress_percentage = ? WHERE id = ?");
        $stmtUpdate->execute([$progress, $milestone_id]);
        
        // Obtener project_id
        $stmtProj = $pdo->prepare("SELECT project_id FROM project_milestones WHERE id = ?");
        $stmtProj->execute([$milestone_id]);
        $project_id = $stmtProj->fetchColumn();
        
        if ($project_id) {
            // Actualizar estado del hito si todas las tareas están cerradas
            $stmtTasksStatus = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'Closed' THEN 1 ELSE 0 END) as closed FROM project_tasks WHERE milestone_id = ?");
            $stmtTasksStatus->execute([$milestone_id]);
            $statusRes = $stmtTasksStatus->fetch(PDO::FETCH_ASSOC);
            if ($statusRes && $statusRes['total'] > 0) {
                if ($statusRes['total'] == $statusRes['closed']) {
                    $stmtUpdateStatus = $pdo->prepare("UPDATE project_milestones SET status = 'Closed' WHERE id = ?");
                    $stmtUpdateStatus->execute([$milestone_id]);
                }
            }
        }
    }
}

try {
    switch ($action) {
        case 'list_projects':
            // Obtener todos los proyectos con el nombre del cliente (CI)
            $stmt = $pdo->query("
                SELECT p.*, ci.hostname as client_name 
                FROM projects p 
                LEFT JOIN ci_instances ci ON p.client_ci_id = ci.id 
                ORDER BY p.id DESC
            ");
            $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Para cada proyecto, calcular su progreso dinámico basado en hitos
            foreach ($projects as &$p) {
                $stmtMil = $pdo->prepare("SELECT COUNT(*) as total, SUM(progress_percentage) as suma FROM project_milestones WHERE project_id = ?");
                $stmtMil->execute([$p['id']]);
                $milRes = $stmtMil->fetch(PDO::FETCH_ASSOC);
                $p['progress'] = 0;
                if ($milRes && $milRes['total'] > 0) {
                    $p['progress'] = round($milRes['suma'] / $milRes['total'], 2);
                }
            }
            
            echo json_encode(['success' => true, 'projects' => $projects]);
            break;

        case 'get_project':
            $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
            
            $stmt = $pdo->prepare("
                SELECT p.*, ci.hostname as client_name 
                FROM projects p 
                LEFT JOIN ci_instances ci ON p.client_ci_id = ci.id 
                WHERE p.id = ?
            ");
            $stmt->execute([$id]);
            $project = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$project) {
                echo json_encode(['success' => false, 'error' => 'Proyecto no encontrado']);
                exit();
            }
            
            // Obtener Hitos
            $stmtMil = $pdo->prepare("SELECT * FROM project_milestones WHERE project_id = ? ORDER BY code ASC");
            $stmtMil->execute([$id]);
            $milestones = $stmtMil->fetchAll(PDO::FETCH_ASSOC);
            
            // Obtener Tareas y Observaciones de cada hito
            foreach ($milestones as &$mil) {
                // Tareas
                $stmtTasks = $pdo->prepare("SELECT * FROM project_tasks WHERE milestone_id = ? ORDER BY code ASC");
                $stmtTasks->execute([$mil['id']]);
                $mil['tasks'] = $stmtTasks->fetchAll(PDO::FETCH_ASSOC);
                
                // Observaciones del Hito (sin tarea asociada)
                $stmtObsM = $pdo->prepare("SELECT * FROM project_observations WHERE milestone_id = ? AND task_id IS NULL ORDER BY code ASC");
                $stmtObsM->execute([$mil['id']]);
                $mil['observations'] = $stmtObsM->fetchAll(PDO::FETCH_ASSOC);
                
                // Observaciones por tarea
                foreach ($mil['tasks'] as &$task) {
                    $stmtObsT = $pdo->prepare("SELECT * FROM project_observations WHERE task_id = ? ORDER BY code ASC");
                    $stmtObsT->execute([$task['id']]);
                    $task['observations'] = $stmtObsT->fetchAll(PDO::FETCH_ASSOC);
                }
            }
            
            echo json_encode([
                'success' => true, 
                'project' => $project, 
                'milestones' => $milestones
            ]);
            break;

        case 'save_project':
            $id = (int)($_POST['id'] ?? 0);
            $name = $_POST['name'] ?? '';
            $client_ci_id = !empty($_POST['client_ci_id']) ? (int)$_POST['client_ci_id'] : null;
            $amount = (float)($_POST['amount'] ?? 0);
            $start_date = $_POST['start_date'] ?? '';
            $end_date = $_POST['end_date'] ?? '';
            $execution_date = !empty($_POST['execution_date']) ? $_POST['execution_date'] : null;
            $assigned_personnel = $_POST['assigned_personnel'] ?? '';
            $work_type = $_POST['work_type'] ?? 'horas normales';
            $working_days = isset($_POST['working_days']) ? (is_array($_POST['working_days']) ? implode(',', $_POST['working_days']) : $_POST['working_days']) : 'Lunes,Martes,Miércoles,Jueves,Viernes';
            
            if (empty($name) || empty($start_date) || empty($end_date)) {
                echo json_encode(['success' => false, 'error' => 'Faltan campos obligatorios.']);
                exit();
            }
            
            if ($id === 0) {
                // Nuevo proyecto: Generar código proj-XX
                $stmt = $pdo->query("SELECT code FROM projects ORDER BY id DESC LIMIT 1");
                $last = $stmt->fetchColumn();
                $next_num = 1;
                if ($last && preg_match('/proj-(\d+)/i', $last, $m)) {
                    $next_num = intval($m[1]) + 1;
                }
                $code = 'proj-' . sprintf("%02d", $next_num);
                
                $stmtIns = $pdo->prepare("
                    INSERT INTO projects (code, name, client_ci_id, amount, start_date, end_date, execution_date, assigned_personnel, work_type, working_days) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtIns->execute([$code, $name, $client_ci_id, $amount, $start_date, $end_date, $execution_date, $assigned_personnel, $work_type, $working_days]);
                $id = $pdo->lastInsertId();
            } else {
                // Editar
                $stmtUp = $pdo->prepare("
                    UPDATE projects SET 
                        name = ?, client_ci_id = ?, amount = ?, start_date = ?, end_date = ?, 
                        execution_date = ?, assigned_personnel = ?, work_type = ?, working_days = ? 
                    WHERE id = ?
                ");
                $stmtUp->execute([$name, $client_ci_id, $amount, $start_date, $end_date, $execution_date, $assigned_personnel, $work_type, $working_days, $id]);
            }
            
            echo json_encode(['success' => true, 'project_id' => $id]);
            break;

        case 'delete_project':
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        case 'save_milestone':
            $id = (int)($_POST['id'] ?? 0);
            $project_id = (int)$_POST['project_id'];
            $name = $_POST['name'] ?? '';
            $priority = $_POST['priority'] ?? 'Media';
            $importance = $_POST['importance'] ?? 'Media';
            $average_execution_time = (int)($_POST['average_execution_time'] ?? 0);
            
            $estimated_start_date = !empty($_POST['estimated_start_date']) ? $_POST['estimated_start_date'] : null;
            $estimated_end_date = !empty($_POST['estimated_end_date']) ? $_POST['estimated_end_date'] : null;
            $real_start_date = !empty($_POST['real_start_date']) ? $_POST['real_start_date'] : null;
            $real_end_date = !empty($_POST['real_end_date']) ? $_POST['real_end_date'] : null;
            
            if (empty($name)) {
                echo json_encode(['success' => false, 'error' => 'El nombre del hito es obligatorio.']);
                exit();
            }
            
            if ($id === 0) {
                // Generar código hit-XXYY
                $stmtProj = $pdo->prepare("SELECT code FROM projects WHERE id = ?");
                $stmtProj->execute([$project_id]);
                $projCode = $stmtProj->fetchColumn();
                $xx = '00';
                if ($projCode && preg_match('/proj-(\d+)/i', $projCode, $m)) {
                    $xx = $m[1];
                }
                
                $stmtMil = $pdo->prepare("SELECT code FROM project_milestones WHERE project_id = ? ORDER BY id DESC LIMIT 1");
                $stmtMil->execute([$project_id]);
                $lastMil = $stmtMil->fetchColumn();
                $next_yy = 1;
                if ($lastMil && preg_match('/hit-\d+(\d{2})/i', $lastMil, $m)) {
                    $next_yy = intval($m[1]) + 1;
                }
                $code = 'hit-' . $xx . sprintf("%02d", $next_yy);
                
                $stmtIns = $pdo->prepare("
                    INSERT INTO project_milestones (project_id, code, name, estimated_start_date, estimated_end_date, real_start_date, real_end_date, priority, importance, average_execution_time, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'New')
                ");
                $stmtIns->execute([$project_id, $code, $name, $estimated_start_date, $estimated_end_date, $real_start_date, $real_end_date, $priority, $importance, $average_execution_time]);
                $id = $pdo->lastInsertId();
            } else {
                $stmtUp = $pdo->prepare("
                    UPDATE project_milestones SET 
                        name = ?, estimated_start_date = ?, estimated_end_date = ?, 
                        real_start_date = ?, real_end_date = ?, priority = ?, 
                        importance = ?, average_execution_time = ? 
                    WHERE id = ?
                ");
                $stmtUp->execute([$name, $estimated_start_date, $estimated_end_date, $real_start_date, $real_end_date, $priority, $importance, $average_execution_time, $id]);
            }
            
            echo json_encode(['success' => true, 'milestone_id' => $id]);
            break;

        case 'delete_milestone':
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM project_milestones WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        case 'save_task':
            $id = (int)($_POST['id'] ?? 0);
            $milestone_id = (int)$_POST['milestone_id'];
            $title = $_POST['title'] ?? '';
            $estimated_start_date = $_POST['estimated_start_date'] ?? '';
            $estimated_end_date = $_POST['estimated_end_date'] ?? '';
            $real_start_date = !empty($_POST['real_start_date']) ? $_POST['real_start_date'] : null;
            $real_end_date = !empty($_POST['real_end_date']) ? $_POST['real_end_date'] : null;
            $assigned_person = $_POST['assigned_person'] ?? '';
            $priority = $_POST['priority'] ?? 'Media';
            $importance = $_POST['importance'] ?? 'Media';
            $progress_percentage = (float)($_POST['progress_percentage'] ?? 0);
            $average_execution_time = (float)($_POST['average_execution_time'] ?? 0);
            $status = $_POST['status'] ?? 'New';
            
            if (empty($title) || empty($estimated_start_date) || empty($estimated_end_date)) {
                echo json_encode(['success' => false, 'error' => 'Faltan campos obligatorios.']);
                exit();
            }
            
            if ($id === 0) {
                // Generar código req-XXYYZZZ
                $stmtMil = $pdo->prepare("SELECT code FROM project_milestones WHERE id = ?");
                $stmtMil->execute([$milestone_id]);
                $milCode = $stmtMil->fetchColumn();
                $xxyy = '0000';
                if ($milCode && preg_match('/hit-(\d{4})/i', $milCode, $m)) {
                    $xxyy = $m[1];
                }
                
                $stmtTask = $pdo->prepare("SELECT code FROM project_tasks WHERE milestone_id = ? ORDER BY id DESC LIMIT 1");
                $stmtTask->execute([$milestone_id]);
                $lastTask = $stmtTask->fetchColumn();
                $next_zzz = 1;
                if ($lastTask && preg_match('/req-\d{4}(\d{3})/i', $lastTask, $m)) {
                    $next_zzz = intval($m[1]) + 1;
                }
                $code = 'req-' . $xxyy . sprintf("%03d", $next_zzz);
                
                $stmtIns = $pdo->prepare("
                    INSERT INTO project_tasks (milestone_id, code, title, estimated_start_date, estimated_end_date, real_start_date, real_end_date, assigned_person, priority, importance, progress_percentage, average_execution_time, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtIns->execute([$milestone_id, $code, $title, $estimated_start_date, $estimated_end_date, $real_start_date, $real_end_date, $assigned_person, $priority, $importance, $progress_percentage, $average_execution_time, $status]);
                $id = $pdo->lastInsertId();
            } else {
                // Si pasa a cerrado y el progreso es < 100, forzarlo a 100
                if ($status === 'Closed' && $progress_percentage < 100) {
                    $progress_percentage = 100;
                }
                $stmtUp = $pdo->prepare("
                    UPDATE project_tasks SET 
                        title = ?, estimated_start_date = ?, estimated_end_date = ?, 
                        real_start_date = ?, real_end_date = ?, assigned_person = ?, 
                        priority = ?, importance = ?, progress_percentage = ?, 
                        average_execution_time = ?, status = ? 
                    WHERE id = ?
                ");
                $stmtUp->execute([$title, $estimated_start_date, $estimated_end_date, $real_start_date, $real_end_date, $assigned_person, $priority, $importance, $progress_percentage, $average_execution_time, $status, $id]);
            }
            
            // Recalcular progreso
            recalculateMilestoneProgress($pdo, $milestone_id);
            
            echo json_encode(['success' => true, 'task_id' => $id]);
            break;

        case 'delete_task':
            $id = (int)($_POST['id'] ?? 0);
            // Obtener milestone id antes de borrar para recalcular
            $stmtMil = $pdo->prepare("SELECT milestone_id FROM project_tasks WHERE id = ?");
            $stmtMil->execute([$id]);
            $milestone_id = $stmtMil->fetchColumn();
            
            $stmt = $pdo->prepare("DELETE FROM project_tasks WHERE id = ?");
            $stmt->execute([$id]);
            
            if ($milestone_id) {
                recalculateMilestoneProgress($pdo, $milestone_id);
            }
            
            echo json_encode(['success' => true]);
            break;

        case 'save_observation':
            $id = (int)($_POST['id'] ?? 0);
            $milestone_id = !empty($_POST['milestone_id']) ? (int)$_POST['milestone_id'] : null;
            $task_id = !empty($_POST['task_id']) ? (int)$_POST['task_id'] : null;
            $comment = $_POST['comment'] ?? '';
            
            if (empty($comment) || (!$milestone_id && !$task_id)) {
                echo json_encode(['success' => false, 'error' => 'Faltan datos obligatorios.']);
                exit();
            }
            
            if ($id === 0) {
                // Calcular código obs-XXYYZZZWWW
                $code = '';
                if ($task_id) {
                    $stmtTask = $pdo->prepare("SELECT code, milestone_id FROM project_tasks WHERE id = ?");
                    $stmtTask->execute([$task_id]);
                    $taskRow = $stmtTask->fetch(PDO::FETCH_ASSOC);
                    $xxyyzzz = '0000000';
                    if ($taskRow && preg_match('/req-(\d{7})/i', $taskRow['code'], $m)) {
                        $xxyyzzz = $m[1];
                    }
                    $milestone_id = $taskRow['milestone_id']; // Forzar el hito correcto
                    
                    // Buscar el siguiente WWW para la tarea
                    $stmtObs = $pdo->prepare("SELECT code FROM project_observations WHERE task_id = ? ORDER BY id DESC LIMIT 1");
                    $stmtObs->execute([$task_id]);
                    $lastObs = $stmtObs->fetchColumn();
                    $next_www = 1;
                    if ($lastObs && preg_match('/obs-\d{7}(\d{3})/i', $lastObs, $m)) {
                        $next_www = intval($m[1]) + 1;
                    }
                    $code = 'obs-' . $xxyyzzz . sprintf("%03d", $next_www);
                } else {
                    $stmtMil = $pdo->prepare("SELECT code FROM project_milestones WHERE id = ?");
                    $stmtMil->execute([$milestone_id]);
                    $milCode = $stmtMil->fetchColumn();
                    $xxyy = '0000';
                    if ($milCode && preg_match('/hit-(\d{4})/i', $milCode, $m)) {
                        $xxyy = $m[1];
                    }
                    
                    // Buscar el siguiente WWW para el hito (donde task_id es nulo)
                    $stmtObs = $pdo->prepare("SELECT code FROM project_observations WHERE milestone_id = ? AND task_id IS NULL ORDER BY id DESC LIMIT 1");
                    $stmtObs->execute([$milestone_id]);
                    $lastObs = $stmtObs->fetchColumn();
                    $next_www = 1;
                    if ($lastObs && preg_match('/obs-\d{4}000(\d{3})/i', $lastObs, $m)) {
                        $next_www = intval($m[1]) + 1;
                    }
                    $code = 'obs-' . $xxyy . '000' . sprintf("%03d", $next_www);
                }
                
                $stmtIns = $pdo->prepare("
                    INSERT INTO project_observations (milestone_id, task_id, code, comment) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmtIns->execute([$milestone_id, $task_id, $code, $comment]);
                $id = $pdo->lastInsertId();
            } else {
                $stmtUp = $pdo->prepare("UPDATE project_observations SET comment = ? WHERE id = ?");
                $stmtUp->execute([$comment, $id]);
            }
            
            echo json_encode(['success' => true, 'observation_id' => $id]);
            break;

        case 'delete_observation':
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM project_observations WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        case 'play_milestone':
            // "cada hito debe tener un play para la ejecucion... al dar play todo este hito y las requerimientos internos pasan dentro de un kanban"
            $milestone_id = (int)$_POST['id'];
            
            // Cambiar hito a 'In progress' si está 'New' o 'Scheduled'
            $stmtMil = $pdo->prepare("UPDATE project_milestones SET status = 'In progress' WHERE id = ?");
            $stmtMil->execute([$milestone_id]);
            
            // Opcionalmente, cambiar todas las tareas del hito que estén en "New" a "Scheduled" o mantenerlas
            $stmtTasks = $pdo->prepare("UPDATE project_tasks SET status = 'Scheduled' WHERE milestone_id = ? AND status = 'New'");
            $stmtTasks->execute([$milestone_id]);
            
            echo json_encode(['success' => true]);
            break;

        case 'update_task_status':
            // Usado en el tablero Kanban
            $id = (int)$_POST['id'];
            $status = $_POST['status'] ?? 'In progress';
            
            // Si es Closed, setear progreso al 100% y establecer fecha real de fin si no la tiene
            if ($status === 'Closed') {
                $stmtUp = $pdo->prepare("
                    UPDATE project_tasks SET 
                        status = ?, 
                        progress_percentage = 100,
                        real_end_date = COALESCE(real_end_date, NOW()) 
                    WHERE id = ?
                ");
                $stmtUp->execute([$status, $id]);
            } elseif ($status === 'In progress') {
                $stmtUp = $pdo->prepare("
                    UPDATE project_tasks SET 
                        status = ?, 
                        real_start_date = COALESCE(real_start_date, NOW()) 
                    WHERE id = ?
                ");
                $stmtUp->execute([$status, $id]);
            } else {
                $stmtUp = $pdo->prepare("UPDATE project_tasks SET status = ? WHERE id = ?");
                $stmtUp->execute([$status, $id]);
            }
            
            // Obtener milestone_id para recalcular
            $stmtMil = $pdo->prepare("SELECT milestone_id FROM project_tasks WHERE id = ?");
            $stmtMil->execute([$id]);
            $milestone_id = $stmtMil->fetchColumn();
            
            if ($milestone_id) {
                recalculateMilestoneProgress($pdo, $milestone_id);
            }
            
            echo json_encode(['success' => true]);
            break;

        case 'save_milestone_template':
            // Guarda un hito y sus tareas como plantilla
            $milestone_id = (int)$_POST['milestone_id'];
            
            // Obtener datos del hito
            $stmtMil = $pdo->prepare("SELECT * FROM project_milestones WHERE id = ?");
            $stmtMil->execute([$milestone_id]);
            $mil = $stmtMil->fetch(PDO::FETCH_ASSOC);
            
            if (!$mil) {
                echo json_encode(['success' => false, 'error' => 'Hito no encontrado']);
                exit();
            }
            
            // Nombre de plantilla
            $template_name = $_POST['template_name'] ?? $mil['name'] . ' (Plantilla)';
            
            // Guardar plantilla de hito
            $stmtIns = $pdo->prepare("INSERT INTO project_milestone_templates (name, priority, importance) VALUES (?, ?, ?)");
            $stmtIns->execute([$template_name, $mil['priority'], $mil['importance']]);
            $template_id = $pdo->lastInsertId();
            
            // Obtener tareas del hito
            $stmtTasks = $pdo->prepare("SELECT * FROM project_tasks WHERE milestone_id = ?");
            $stmtTasks->execute([$milestone_id]);
            $tasks = $stmtTasks->fetchAll(PDO::FETCH_ASSOC);
            
            // Guardar plantillas de tareas
            $stmtInsTask = $pdo->prepare("
                INSERT INTO project_task_templates (milestone_template_id, title, priority, importance, average_execution_time) 
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($tasks as $t) {
                $stmtInsTask->execute([
                    $template_id, 
                    $t['title'], 
                    $t['priority'], 
                    $t['importance'], 
                    $t['average_execution_time']
                ]);
            }
            
            echo json_encode(['success' => true, 'template_id' => $template_id]);
            break;

        case 'list_milestone_templates':
            $stmt = $pdo->query("SELECT * FROM project_milestone_templates ORDER BY name ASC");
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($templates)) {
                try {
                    $pdo->beginTransaction();
                    
                    // Template 1
                    $stmt1 = $pdo->prepare("INSERT INTO project_milestone_templates (name, priority, importance) VALUES (?, ?, ?)");
                    $stmt1->execute(['Instalación de Servidor', 'Alta', 'Alta']);
                    $tid1 = $pdo->lastInsertId();
                    
                    $stmt2 = $pdo->prepare("INSERT INTO project_task_templates (milestone_template_id, title, priority, importance, average_execution_time) VALUES (?, ?, ?, ?, ?)");
                    $stmt2->execute([$tid1, 'Revisión del equipo físico', 'Media', 'Alta', 4.00]);
                    $stmt2->execute([$tid1, 'Revisión de configuración de red', 'Media', 'Media', 6.00]);
                    $stmt2->execute([$tid1, 'Revisión de licencias y activación', 'Alta', 'Alta', 2.00]);
                    
                    // Template 2
                    $stmt1->execute(['Despliegue de Base de Datos', 'Alta', 'Alta']);
                    $tid2 = $pdo->lastInsertId();
                    
                    $stmt2->execute([$tid2, 'Instalación del motor de BD', 'Alta', 'Alta', 3.00]);
                    $stmt2->execute([$tid2, 'Ajustes de seguridad y usuarios', 'Media', 'Media', 4.00]);
                    $stmt2->execute([$tid2, 'Creación de esquemas y tablas', 'Alta', 'Alta', 5.00]);
                    
                    $pdo->commit();
                    
                    // Volver a consultar
                    $stmt = $pdo->query("SELECT * FROM project_milestone_templates ORDER BY name ASC");
                    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $txEx) {
                    $pdo->rollBack();
                }
            }
            echo json_encode(['success' => true, 'templates' => $templates]);
            break;

        case 'apply_milestone_template':
            // Carga un hito de plantilla en un proyecto
            $project_id = (int)$_POST['project_id'];
            $template_id = (int)$_POST['template_id'];
            
            // Obtener plantilla
            $stmtTpl = $pdo->prepare("SELECT * FROM project_milestone_templates WHERE id = ?");
            $stmtTpl->execute([$template_id]);
            $tpl = $stmtTpl->fetch(PDO::FETCH_ASSOC);
            
            if (!$tpl) {
                echo json_encode(['success' => false, 'error' => 'Plantilla no encontrada.']);
                exit();
            }
            
            // Generar código hit-XXYY
            $stmtProj = $pdo->prepare("SELECT code FROM projects WHERE id = ?");
            $stmtProj->execute([$project_id]);
            $projCode = $stmtProj->fetchColumn();
            $xx = '00';
            if ($projCode && preg_match('/proj-(\d+)/i', $projCode, $m)) {
                $xx = $m[1];
            }
            
            $stmtMil = $pdo->prepare("SELECT code FROM project_milestones WHERE project_id = ? ORDER BY id DESC LIMIT 1");
            $stmtMil->execute([$project_id]);
            $lastMil = $stmtMil->fetchColumn();
            $next_yy = 1;
            if ($lastMil && preg_match('/hit-\d+(\d{2})/i', $lastMil, $m)) {
                $next_yy = intval($m[1]) + 1;
            }
            $mil_code = 'hit-' . $xx . sprintf("%02d", $next_yy);
            
            // Crear Hito
            $stmtInsMil = $pdo->prepare("
                INSERT INTO project_milestones (project_id, code, name, estimated_start_date, estimated_end_date, real_start_date, real_end_date, priority, importance, average_execution_time, status) 
                VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 1 DAY), NULL, NULL, ?, ?, 0, 'New')
            ");
            $stmtInsMil->execute([$project_id, $mil_code, $tpl['name'], $tpl['priority'], $tpl['importance']]);
            $new_milestone_id = $pdo->lastInsertId();
            
            // Obtener tareas de la plantilla
            $stmtTplTasks = $pdo->prepare("SELECT * FROM project_task_templates WHERE milestone_template_id = ?");
            $stmtTplTasks->execute([$template_id]);
            $tplTasks = $stmtTplTasks->fetchAll(PDO::FETCH_ASSOC);
            
            // Insertar tareas calculando req-XXYYZZZ
            $next_zzz = 1;
            $stmtInsTask = $pdo->prepare("
                INSERT INTO project_tasks (milestone_id, code, title, estimated_start_date, estimated_end_date, priority, importance, average_execution_time, status) 
                VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 1 DAY), ?, ?, ?, 'New')
            ");
            
            foreach ($tplTasks as $t) {
                $task_code = 'req-' . $xx . sprintf("%02d", $next_yy) . sprintf("%03d", $next_zzz);
                $stmtInsTask->execute([
                    $new_milestone_id, 
                    $task_code, 
                    $t['title'], 
                    $t['priority'], 
                    $t['importance'], 
                    $t['average_execution_time']
                ]);
                $next_zzz++;
            }
            
            // Recalcular hito recién creado
            recalculateMilestoneProgress($pdo, $new_milestone_id);
            
            echo json_encode(['success' => true, 'milestone_id' => $new_milestone_id]);
            break;

        case 'search_clients':
            // Buscar CIs para el selector de clientes
            $term = $_POST['term'] ?? $_GET['term'] ?? '';
            // Buscar en ci_instances por hostname. Si está vacío, listar los primeros 50
            if (empty($term)) {
                $stmt = $pdo->query("SELECT id, hostname, ci_unique FROM ci_instances ORDER BY hostname ASC LIMIT 50");
            } else {
                $stmt = $pdo->prepare("SELECT id, hostname, ci_unique FROM ci_instances WHERE hostname LIKE ? ORDER BY hostname ASC LIMIT 50");
                $stmt->execute(['%' . $term . '%']);
            }
            $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'clients' => $clients]);
            break;

        case 'quick_create_client':
            $name = trim($_POST['name'] ?? '');
            if (empty($name)) {
                echo json_encode(['success' => false, 'error' => 'El nombre del cliente es obligatorio.']);
                exit();
            }

            // Buscar categoría de Clientes (ID 48/49 o por nombre)
            $stmtCat = $pdo->query("SELECT id FROM ci_categories WHERE name LIKE '%Cliente%' ORDER BY id ASC LIMIT 1");
            $cat_id = $stmtCat->fetchColumn() ?: 48;

            // Generar código único SND-XXXXXXXXXX
            $ci_unique = 'SND-' . sprintf("%010d", rand(1000000, 999999999));

            // Insertar nuevo CI para el cliente
            $stmtIns = $pdo->prepare("INSERT INTO ci_instances (category_id, hostname, status, source, ci_unique) VALUES (?, ?, 'Activo', 'manual', ?)");
            $stmtIns->execute([$cat_id, $name, $ci_unique]);
            $new_client_id = (int)$pdo->lastInsertId();

            echo json_encode([
                'success' => true,
                'client' => [
                    'id' => $new_client_id,
                    'hostname' => $name,
                    'ci_unique' => $ci_unique
                ]
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida.']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

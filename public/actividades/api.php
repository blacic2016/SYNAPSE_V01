<?php
/**
 * Backend API Autónomo para el Módulo ACTIVIDADES - Control y Entrega de Servicios
 * 100% Independiente: Almacenamiento local estructurado en JSON (Sin conexión a MySQL / BDD)
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/permissions_helper.php';

$user = current_user();
if (!$user || (!has_role('SUPER_ADMIN') && !has_module_access('actividades'))) {
    echo json_encode(['success' => false, 'error' => 'Acceso denegado al módulo ACTIVIDADES']);
    exit;
}

$current_username = $user['username'] ?? 'Usuario Actividades';
$current_user_id = $user['id'] ?? 1;

class ActividadesStore {
    private static $dataFile = __DIR__ . '/data/actividades_store.json';

    public static function getData() {
        if (!file_exists(self::$dataFile)) {
            $initial = [
                'next_requirement_id' => 1,
                'next_history_id' => 1,
                'next_attachment_id' => 1,
                'next_bitacora_id' => 1,
                'next_image_id' => 1,
                'next_git_id' => 1,
                'requirements' => [],
                'history' => [],
                'attachments' => [],
                'bitacora' => [],
                'bitacora_images' => [],
                'git_logs' => []
            ];
            self::saveData($initial);
            return $initial;
        }

        $content = file_get_contents(self::$dataFile);
        $data = json_decode($content, true);
        if (!is_array($data)) {
            $data = [
                'next_requirement_id' => 1,
                'next_history_id' => 1,
                'next_attachment_id' => 1,
                'next_bitacora_id' => 1,
                'next_image_id' => 1,
                'next_git_id' => 1,
                'requirements' => [],
                'history' => [],
                'attachments' => [],
                'bitacora' => [],
                'bitacora_images' => [],
                'git_logs' => []
            ];
        }
        return $data;
    }

    public static function saveData($data) {
        $dir = dirname(self::$dataFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        file_put_contents(self::$dataFile, $json, LOCK_EX);
    }
}

function getClientIp() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

function logRequirementEvent(&$data, $requirement_id, $action, $username, $details, $snapshot = null) {
    if (!isset($data['next_history_id'])) {
        $data['next_history_id'] = 1;
    }
    $histId = $data['next_history_id']++;
    $ip = getClientIp();
    $event = [
        'id' => $histId,
        'requirement_id' => (int)$requirement_id,
        'action' => $action,
        'changed_by_user' => $username,
        'change_details' => $details,
        'ip_address' => $ip,
        'snapshot_json' => is_string($snapshot) ? $snapshot : ($snapshot !== null ? json_encode($snapshot, JSON_UNESCAPED_UNICODE) : ''),
        'created_at' => date('Y-m-d H:i:s')
    ];
    if (!isset($data['history']) || !is_array($data['history'])) {
        $data['history'] = [];
    }
    $data['history'][] = $event;
    return $event;
}

function logRequirementView(&$data, $requirement_id, $username, $context = 'Detalle de Actividad') {
    $now = time();
    $throttleSeconds = 180; // 3 minutos de throttle para el mismo usuario y requerimiento
    
    // Verificar si el mismo usuario ya registró vista en los últimos 3 minutos
    if (!empty($data['history'])) {
        foreach (array_reverse($data['history']) as $h) {
            if ((int)($h['requirement_id'] ?? 0) === (int)$requirement_id
                && in_array($h['action'] ?? '', ['VISTA', 'LECTURA', 'ACCESO_DETALLE'])
                && ($h['changed_by_user'] ?? '') === $username) {
                $lastTime = strtotime($h['created_at'] ?? '');
                if ($lastTime && ($now - $lastTime) < $throttleSeconds) {
                    return false; // Throttled: evitar duplicados
                }
                break;
            }
        }
    }
    
    // Buscar datos del requerimiento para enriquecer el detalle
    $ticketCode = '';
    $creator = '';
    foreach ($data['requirements'] ?? [] as $r) {
        if ((int)$r['id'] === (int)$requirement_id) {
            $ticketCode = $r['ticket_code'] ?? '';
            $creator = $r['sonda_analyst'] ?? '';
            break;
        }
    }
    
    $detail = "Visualización y consulta de la actividad ({$context})";
    if ($ticketCode !== '') {
        $detail .= " para Ticket #{$ticketCode}";
    }
    
    logRequirementEvent($data, $requirement_id, 'VISTA', $username, $detail);
    ActividadesStore::saveData($data);
    return true;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$data = ActividadesStore::getData();

switch ($action) {
    case 'list':
        try {
            $q = mb_strtolower(trim($_GET['q'] ?? ''), 'UTF-8');
            $status = trim($_GET['status'] ?? '');
            $activity_type = trim($_GET['activity_type'] ?? '');

            $requirements = $data['requirements'] ?? [];
            $filtered = [];

            foreach ($requirements as $item) {
                if ($q !== '') {
                    $matchText = mb_strtolower(
                        ($item['ticket_code'] ?? '') . ' ' .
                        ($item['femsa_requester'] ?? '') . ' ' .
                        ($item['sonda_analyst'] ?? '') . ' ' .
                        ($item['work_description'] ?? '') . ' ' .
                        ($item['script_name'] ?? ''),
                        'UTF-8'
                    );
                    if (strpos($matchText, $q) === false) {
                        continue;
                    }
                }

                if ($status !== '' && ($item['status'] ?? '') !== $status) {
                    continue;
                }

                if ($activity_type !== '' && ($item['activity_type'] ?? '') !== $activity_type) {
                    continue;
                }

                $filtered[] = $item;
            }

            // Ordenar id DESC
            usort($filtered, function ($a, $b) {
                return ($b['id'] ?? 0) - ($a['id'] ?? 0);
            });

            // Stats
            $stats = [
                'total' => count($requirements),
                'borrador' => 0,
                'en_proceso' => 0,
                'entregado' => 0,
                'aprobado' => 0,
                'cancelado' => 0
            ];

            foreach ($requirements as $r) {
                $st = $r['status'] ?? 'Borrador';
                if ($st === 'Borrador') $stats['borrador']++;
                elseif ($st === 'En Proceso') $stats['en_proceso']++;
                elseif ($st === 'Entregado') $stats['entregado']++;
                elseif ($st === 'Aprobado' || $st === 'Finalizado') $stats['aprobado']++;
                elseif ($st === 'Cancelado') $stats['cancelado']++;
            }

            // Calcular métricas de visualizaciones e historial por requerimiento
            $historyByReq = [];
            foreach ($data['history'] ?? [] as $h) {
                $rid = (int)($h['requirement_id'] ?? 0);
                if ($rid > 0) {
                    if (!isset($historyByReq[$rid])) {
                        $historyByReq[$rid] = [
                            'total_events' => 0,
                            'views_count' => 0,
                            'viewers' => [],
                            'last_view' => null,
                            'last_event' => null,
                            'creator' => null
                        ];
                    }
                    $historyByReq[$rid]['total_events']++;
                    $u = $h['changed_by_user'] ?? 'Desconocido';

                    if (($h['action'] ?? '') === 'CREACION' && $historyByReq[$rid]['creator'] === null) {
                        $historyByReq[$rid]['creator'] = [
                            'user' => $u,
                            'date' => $h['created_at'] ?? ''
                        ];
                    }

                    if (in_array($h['action'] ?? '', ['VISTA', 'IMPRESION_PDF', 'LECTURA'])) {
                        $historyByReq[$rid]['views_count']++;
                        if (!in_array($u, $historyByReq[$rid]['viewers'])) {
                            $historyByReq[$rid]['viewers'][] = $u;
                        }
                        if ($historyByReq[$rid]['last_view'] === null || strtotime($h['created_at'] ?? '') > strtotime($historyByReq[$rid]['last_view']['date'] ?? '')) {
                            $historyByReq[$rid]['last_view'] = [
                                'user' => $u,
                                'date' => $h['created_at'] ?? '',
                                'action' => $h['action']
                            ];
                        }
                    }

                    if ($historyByReq[$rid]['last_event'] === null || strtotime($h['created_at'] ?? '') > strtotime($historyByReq[$rid]['last_event']['date'] ?? '')) {
                        $historyByReq[$rid]['last_event'] = [
                            'user' => $u,
                            'action' => $h['action'] ?? '',
                            'details' => $h['change_details'] ?? '',
                            'date' => $h['created_at'] ?? ''
                        ];
                    }
                }
            }

            foreach ($filtered as &$item) {
                $rid = (int)$item['id'];
                $reqStats = $historyByReq[$rid] ?? null;
                $item['history_count'] = $reqStats ? $reqStats['total_events'] : 0;
                $item['views_count'] = $reqStats ? $reqStats['views_count'] : 0;
                $item['unique_viewers'] = $reqStats ? $reqStats['viewers'] : [];
                $item['unique_viewers_count'] = $reqStats ? count($reqStats['viewers']) : 0;
                $item['last_viewed_by'] = $reqStats && $reqStats['last_view'] ? $reqStats['last_view']['user'] : null;
                $item['last_viewed_at'] = $reqStats && $reqStats['last_view'] ? $reqStats['last_view']['date'] : null;
                $item['creator_user'] = $reqStats && $reqStats['creator'] ? $reqStats['creator']['user'] : ($item['sonda_analyst'] ?? 'Admin');
                $item['last_event_action'] = $reqStats && $reqStats['last_event'] ? $reqStats['last_event']['action'] : null;
                $item['last_event_by'] = $reqStats && $reqStats['last_event'] ? $reqStats['last_event']['user'] : null;
                $item['last_event_at'] = $reqStats && $reqStats['last_event'] ? $reqStats['last_event']['date'] : null;
            }
            unset($item);

            echo json_encode(['success' => true, 'data' => $filtered, 'stats' => $stats]);
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

            $item = null;
            foreach ($data['requirements'] as $r) {
                if ((int)$r['id'] === $id) {
                    $item = $r;
                    break;
                }
            }

            if (!$item) {
                echo json_encode(['success' => false, 'error' => 'Actividad no encontrada']);
                exit;
            }

            // Registrar vista automáticamente a menos que log_view sea 0 (ej. modal historial)
            $logView = isset($_GET['log_view']) ? (int)$_GET['log_view'] : 1;
            $viewContext = trim($_GET['view_context'] ?? 'Detalle del Ciclo');
            if ($logView === 1) {
                logRequirementView($data, $id, $current_username, $viewContext);
            }

            // Historial y métricas de auditoría
            $history = [];
            $viewsCount = 0;
            $uniqueViewers = [];
            $creatorInfo = null;
            $lastViewInfo = null;

            foreach ($data['history'] as $h) {
                if ((int)$h['requirement_id'] === $id) {
                    $history[] = $h;
                    $u = $h['changed_by_user'] ?? 'Desconocido';
                    $act = $h['action'] ?? '';

                    if ($act === 'CREACION' && $creatorInfo === null) {
                        $creatorInfo = [
                            'user' => $u,
                            'date' => $h['created_at'] ?? ''
                        ];
                    }

                    if (in_array($act, ['VISTA', 'IMPRESION_PDF', 'LECTURA'])) {
                        $viewsCount++;
                        if (!in_array($u, $uniqueViewers)) {
                            $uniqueViewers[] = $u;
                        }
                        if ($lastViewInfo === null || strtotime($h['created_at'] ?? '') > strtotime($lastViewInfo['date'] ?? '')) {
                            $lastViewInfo = [
                                'user' => $u,
                                'date' => $h['created_at'] ?? '',
                                'action' => $act,
                                'ip' => $h['ip_address'] ?? '127.0.0.1'
                            ];
                        }
                    }
                }
            }
            usort($history, function ($a, $b) {
                return ($b['id'] ?? 0) - ($a['id'] ?? 0);
            });

            // Adjuntos
            $attachments = [];
            foreach ($data['attachments'] as $a) {
                if ((int)$a['requirement_id'] === $id) {
                    $attachments[] = $a;
                }
            }
            usort($attachments, function ($a, $b) {
                return ($b['id'] ?? 0) - ($a['id'] ?? 0);
            });

            // Bitácora
            $bitacora = [];
            foreach ($data['bitacora'] as $b) {
                if ((int)$b['requirement_id'] === $id) {
                    $bItem = $b;
                    $bItem['images'] = [];
                    foreach ($data['bitacora_images'] as $img) {
                        if ((int)$img['bitacora_id'] === (int)$b['id']) {
                            $bItem['images'][] = $img;
                        }
                    }
                    $bitacora[] = $bItem;
                }
            }
            usort($bitacora, function ($a, $b) {
                return strcmp($b['fecha'] ?? '', $a['fecha'] ?? '');
            });

            // Git logs
            $git_logs = [];
            foreach ($data['git_logs'] as $g) {
                if ((int)$g['requirement_id'] === $id) {
                    $git_logs[] = $g;
                }
            }
            usort($git_logs, function ($a, $b) {
                return ($b['id'] ?? 0) - ($a['id'] ?? 0);
            });

            echo json_encode([
                'success' => true,
                'data' => $item,
                'history' => $history,
                'attachments' => $attachments,
                'bitacora' => $bitacora,
                'git_logs' => $git_logs,
                'audit_summary' => [
                    'views_count' => $viewsCount,
                    'total_events' => count($history),
                    'unique_viewers' => $uniqueViewers,
                    'unique_viewers_count' => count($uniqueViewers),
                    'creator' => $creatorInfo ?: ['user' => $item['sonda_analyst'] ?? 'Admin', 'date' => $item['created_at'] ?? ''],
                    'last_view' => $lastViewInfo
                ]
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'log_view':
        try {
            $req_id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
            $context = trim($_POST['context'] ?? $_GET['context'] ?? 'Consulta de Actividad');
            if (!$req_id) {
                echo json_encode(['success' => false, 'error' => 'ID de actividad requerido']);
                exit;
            }

            $logged = logRequirementView($data, $req_id, $current_username, $context);
            echo json_encode(['success' => true, 'logged' => $logged]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'audit_logs':
        try {
            $user_filter = trim($_GET['user'] ?? '');
            $action_filter = trim($_GET['action_type'] ?? '');
            $req_id_filter = (int)($_GET['requirement_id'] ?? 0);
            $q = mb_strtolower(trim($_GET['q'] ?? ''), 'UTF-8');
            $limit = (int)($_GET['limit'] ?? 350);
            if ($limit < 1 || $limit > 1000) $limit = 350;

            $reqMap = [];
            foreach ($data['requirements'] ?? [] as $r) {
                $reqMap[(int)$r['id']] = $r;
            }

            $allHistory = $data['history'] ?? [];
            $filteredHistory = [];

            // Ordenar historial DESC por id/creación
            usort($allHistory, function ($a, $b) {
                return ($b['id'] ?? 0) - ($a['id'] ?? 0);
            });

            $allUsers = [];
            $allActions = [];
            $totalViews = 0;

            foreach ($allHistory as $h) {
                $u = $h['changed_by_user'] ?? 'Desconocido';
                $act = $h['action'] ?? 'EVENTO';
                if (!in_array($u, $allUsers) && !empty($u)) $allUsers[] = $u;
                if (!in_array($act, $allActions) && !empty($act)) $allActions[] = $act;
                if (in_array($act, ['VISTA', 'IMPRESION_PDF', 'LECTURA'])) $totalViews++;

                $rid = (int)($h['requirement_id'] ?? 0);
                $req = $reqMap[$rid] ?? null;
                $ticketCode = $req ? ($req['ticket_code'] ?? '') : "ACT-#{$rid}";

                if ($req_id_filter > 0 && $rid !== $req_id_filter) {
                    continue;
                }

                if ($user_filter !== '' && strcasecmp($u, $user_filter) !== 0) {
                    continue;
                }

                if ($action_filter !== '' && strcasecmp($act, $action_filter) !== 0) {
                    continue;
                }

                if ($q !== '') {
                    $searchHaystack = mb_strtolower($ticketCode . ' ' . $u . ' ' . $act . ' ' . ($h['change_details'] ?? '') . ' ' . ($h['ip_address'] ?? '') . ' ' . ($req['femsa_requester'] ?? ''), 'UTF-8');
                    if (strpos($searchHaystack, $q) === false) {
                        continue;
                    }
                }

                $hItem = $h;
                $hItem['ticket_code'] = $ticketCode;
                $hItem['femsa_requester'] = $req ? ($req['femsa_requester'] ?? '') : '';
                $hItem['sonda_analyst'] = $req ? ($req['sonda_analyst'] ?? '') : '';
                $hItem['req_status'] = $req ? ($req['status'] ?? '') : '';
                $filteredHistory[] = $hItem;
            }

            $pagedHistory = array_slice($filteredHistory, 0, $limit);

            sort($allUsers);
            sort($allActions);

            echo json_encode([
                'success' => true,
                'data' => $pagedHistory,
                'total_filtered' => count($filteredHistory),
                'total_all' => count($allHistory),
                'stats' => [
                    'total_events' => count($allHistory),
                    'total_views' => $totalViews,
                    'unique_users_count' => count($allUsers),
                    'users' => $allUsers,
                    'actions' => $allActions
                ]
            ]);
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

            if ($id > 0) {
                // Actualización
                $foundIndex = -1;
                $old_data = null;
                foreach ($data['requirements'] as $idx => $r) {
                    if ((int)$r['id'] === $id) {
                        $foundIndex = $idx;
                        $old_data = $r;
                        break;
                    }
                }

                if ($foundIndex === -1) {
                    echo json_encode(['success' => false, 'error' => 'Actividad no encontrada para actualizar']);
                    exit;
                }

                $data['requirements'][$foundIndex] = array_merge($data['requirements'][$foundIndex], [
                    'ticket_code' => $ticket_code,
                    'emission_date' => $emission_date,
                    'femsa_requester' => $femsa_requester,
                    'sonda_analyst' => $sonda_analyst,
                    'sonda_supervisor' => $sonda_supervisor,
                    'activity_type' => $activity_type,
                    'work_description' => $work_description,
                    'script_name' => $script_name,
                    'script_language' => $script_language,
                    'repository_url' => $repository_url,
                    'limit_soporte_estandar' => $limit_soporte_estandar,
                    'obs_soporte_estandar' => $obs_soporte_estandar,
                    'limit_desarrollo_evolutivo' => $limit_desarrollo_evolutivo,
                    'obs_desarrollo_evolutivo' => $obs_desarrollo_evolutivo,
                    'limit_herramientas_femsa' => $limit_herramientas_femsa,
                    'obs_herramientas_femsa' => $obs_herramientas_femsa,
                    'status' => $status,
                    'current_stage' => $current_stage,
                    'stage_data_json' => $stage_data_json,
                    'femsa_approved_by' => $femsa_approved_by,
                    'femsa_approval_date' => $femsa_approval_date,
                    'sonda_delivered_by' => $sonda_delivered_by,
                    'sonda_delivery_date' => $sonda_delivery_date,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

                // Registrar historial
                $changes = [];
                if ($old_data['ticket_code'] !== $ticket_code) $changes[] = "Ticket: {$old_data['ticket_code']} ➔ {$ticket_code}";
                if ($old_data['status'] !== $status) $changes[] = "Estado: {$old_data['status']} ➔ {$status}";
                if ((int)$old_data['current_stage'] !== $current_stage) $changes[] = "Etapa: {$old_data['current_stage']} ➔ {$current_stage}";

                $detailText = count($changes) > 0 ? "Actualizado: " . implode(' | ', $changes) : "Modificación general del requerimiento y etapa";
                logRequirementEvent($data, $id, 'MODIFICACION', $current_username, $detailText, $old_data);

                ActividadesStore::saveData($data);
                echo json_encode(['success' => true, 'id' => $id, 'message' => 'Actividad y etapa actualizadas con éxito']);
            } else {
                // Nuevo requerimiento
                $newId = $data['next_requirement_id']++;
                $newRecord = [
                    'id' => $newId,
                    'ticket_code' => $ticket_code,
                    'emission_date' => $emission_date,
                    'femsa_requester' => $femsa_requester,
                    'sonda_analyst' => $sonda_analyst,
                    'sonda_supervisor' => $sonda_supervisor,
                    'activity_type' => $activity_type,
                    'work_description' => $work_description,
                    'script_name' => $script_name,
                    'script_language' => $script_language,
                    'repository_url' => $repository_url,
                    'limit_soporte_estandar' => $limit_soporte_estandar,
                    'obs_soporte_estandar' => $obs_soporte_estandar,
                    'limit_desarrollo_evolutivo' => $limit_desarrollo_evolutivo,
                    'obs_desarrollo_evolutivo' => $obs_desarrollo_evolutivo,
                    'limit_herramientas_femsa' => $limit_herramientas_femsa,
                    'obs_herramientas_femsa' => $obs_herramientas_femsa,
                    'status' => $status,
                    'current_stage' => $current_stage,
                    'stage_data_json' => $stage_data_json,
                    'femsa_approved_by' => $femsa_approved_by,
                    'femsa_approval_date' => $femsa_approval_date,
                    'sonda_delivered_by' => $sonda_delivered_by,
                    'sonda_delivery_date' => $sonda_delivery_date,
                    'created_by' => $current_user_id,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];

                $data['requirements'][] = $newRecord;

                logRequirementEvent($data, $newId, 'CREACION', $current_username, "Creación de requerimiento Ticket #{$ticket_code} en Etapa 1 (Petición)");

                ActividadesStore::saveData($data);
                echo json_encode(['success' => true, 'id' => $newId, 'message' => 'Actividad creada exitosamente']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'update_stage':
        try {
            $id = (int)($_POST['id'] ?? 0);
            $stage = (int)($_POST['stage'] ?? 1);
            $stage_data_raw = $_POST['stage_data'] ?? '';

            if (!$id) {
                echo json_encode(['success' => false, 'error' => 'ID inválido']);
                exit;
            }

            $foundIndex = -1;
            foreach ($data['requirements'] as $idx => $r) {
                if ((int)$r['id'] === $id) {
                    $foundIndex = $idx;
                    break;
                }
            }

            if ($foundIndex === -1) {
                echo json_encode(['success' => false, 'error' => 'Actividad no encontrada']);
                exit;
            }

            $currentReq = $data['requirements'][$foundIndex];
            $currentData = !empty($currentReq['stage_data_json']) ? json_decode($currentReq['stage_data_json'], true) : [];
            if (!is_array($currentData)) $currentData = [];

            if (!empty($stage_data_raw)) {
                $incomingData = json_decode($stage_data_raw, true);
                if (is_array($incomingData)) {
                    $currentData = array_merge($currentData, $incomingData);
                }
            }

            $newJson = json_encode($currentData, JSON_UNESCAPED_UNICODE);
            $data['requirements'][$foundIndex]['current_stage'] = $stage;
            $data['requirements'][$foundIndex]['stage_data_json'] = $newJson;
            $data['requirements'][$foundIndex]['updated_at'] = date('Y-m-d H:i:s');

            logRequirementEvent($data, $id, 'AVANCE_ETAPA', $current_username, "Avance/Cambio a Etapa {$stage}");

            ActividadesStore::saveData($data);
            echo json_encode(['success' => true, 'message' => 'Etapa actualizada exitosamente']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'change_status':
        try {
            $id = (int)($_POST['id'] ?? 0);
            $status = trim($_POST['status'] ?? '');

            $valid = ['Borrador', 'En Proceso', 'Entregado', 'Aprobado', 'Finalizado', 'Cancelado'];
            if (!in_array($status, $valid)) {
                echo json_encode(['success' => false, 'error' => 'Estado no válido']);
                exit;
            }

            $foundIndex = -1;
            foreach ($data['requirements'] as $idx => $r) {
                if ((int)$r['id'] === $id) {
                    $foundIndex = $idx;
                    break;
                }
            }

            if ($foundIndex === -1) {
                echo json_encode(['success' => false, 'error' => 'Actividad no encontrada']);
                exit;
            }

            $oldStatus = $data['requirements'][$foundIndex]['status'];
            $data['requirements'][$foundIndex]['status'] = $status;
            $data['requirements'][$foundIndex]['updated_at'] = date('Y-m-d H:i:s');

            logRequirementEvent($data, $id, 'CAMBIO_ESTADO', $current_username, "Estado: {$oldStatus} ➔ {$status}");

            ActividadesStore::saveData($data);
            echo json_encode(['success' => true, 'message' => "Estado actualizado a '{$status}'"]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'get_next_code':
        try {
            $year = date('Y');
            $nextId = $data['next_requirement_id'] ?? 1;
            $code = sprintf('ACT-%s-%04d', $year, $nextId);
            echo json_encode(['success' => true, 'next_code' => $code, 'code' => $code, 'today' => date('Y-m-d')]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'upload_attachment':
        try {
            $req_id = (int)($_POST['requirement_id'] ?? 0);
            $ticket_code = trim($_POST['ticket_code'] ?? 'ACT-' . date('Ymd'));
            $description = trim($_POST['description'] ?? '');

            if (!$req_id) {
                echo json_encode(['success' => false, 'error' => 'Debe guardar el requerimiento antes de adjuntar archivos']);
                exit;
            }

            $fileKey = isset($_FILES['attachment_file']) ? 'attachment_file' : (isset($_FILES['file']) ? 'file' : null);
            if (!$fileKey || !isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'error' => 'Error al subir el archivo o ningún archivo seleccionado']);
                exit;
            }

            $file = $_FILES[$fileKey];
            $original_name = basename($file['name']);
            $file_size = $file['size'];
            $mime_type = $file['type'];

            $sanitized_ticket = preg_replace('/[^A-Za-z0-9_\-]/', '_', $ticket_code);
            $upload_dir = __DIR__ . '/uploads/attachments/' . $sanitized_ticket;
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

            $rel_path = 'uploads/attachments/' . $sanitized_ticket . '/' . $stored_name;

            $attId = $data['next_attachment_id']++;
            $data['attachments'][] = [
                'id' => $attId,
                'requirement_id' => $req_id,
                'original_name' => $original_name,
                'stored_name' => $stored_name,
                'file_path' => $rel_path,
                'file_size' => $file_size,
                'mime_type' => $mime_type,
                'description' => $description,
                'uploaded_by' => $current_username,
                'created_at' => date('Y-m-d H:i:s')
            ];

            logRequirementEvent($data, $req_id, 'ADJUNTO', $current_username, "Carga de archivo adjunto: {$original_name}");
            ActividadesStore::saveData($data);
            echo json_encode(['success' => true, 'message' => 'Archivo adjuntado correctamente', 'file_path' => $rel_path]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'list_attachments':
        try {
            $req_id = (int)($_GET['requirement_id'] ?? 0);
            $items = [];
            foreach ($data['attachments'] as $a) {
                if ((int)$a['requirement_id'] === $req_id) {
                    $items[] = $a;
                }
            }
            usort($items, function ($a, $b) {
                return ($b['id'] ?? 0) - ($a['id'] ?? 0);
            });
            echo json_encode(['success' => true, 'data' => $items]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'delete_attachment':
        try {
            $att_id = (int)($_POST['attachment_id'] ?? 0);
            $foundIndex = -1;
            foreach ($data['attachments'] as $idx => $a) {
                if ((int)$a['id'] === $att_id) {
                    $foundIndex = $idx;
                    break;
                }
            }

            if ($foundIndex !== -1) {
                $delAtt = $data['attachments'][$foundIndex];
                $reqId = (int)($delAtt['requirement_id'] ?? 0);
                $origName = $delAtt['original_name'] ?? 'adjunto';

                $filePath = __DIR__ . '/' . $delAtt['file_path'];
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
                array_splice($data['attachments'], $foundIndex, 1);
                if ($reqId > 0) {
                    logRequirementEvent($data, $reqId, 'ADJUNTO', $current_username, "Eliminación de archivo adjunto: {$origName}");
                }
                ActividadesStore::saveData($data);
                echo json_encode(['success' => true, 'message' => 'Adjunto eliminado correctamente']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Adjunto no encontrado']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'list_diagrams':
        try {
            // Muestras de diagramas para maquetación / integración
            $diagrams = [
                ['id' => 1, 'title' => 'Diagrama de Flujo General de Automatizaciones', 'description' => 'Flujo BPMN de ejecución de procesos', 'type' => 'bpmn', 'updated_at' => date('Y-m-d')],
                ['id' => 2, 'title' => 'Topología de Servidores y Agentes Zabbix', 'description' => 'Arquitectura de monitoreo', 'type' => 'visio', 'updated_at' => date('Y-m-d')],
                ['id' => 3, 'title' => 'Esquema de Red y Enlaces Core', 'description' => 'Diagrama de conectividad y switches', 'type' => 'visio', 'updated_at' => date('Y-m-d')]
            ];
            echo json_encode(['success' => true, 'data' => $diagrams]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'list_gitlab_files':
        try {
            $files = [
                ['id' => 1, 'filename' => 'backup_monitor_v1.py', 'file_type' => 'python', 'updated_at' => date('Y-m-d'), 'version_count' => 3],
                ['id' => 2, 'filename' => 'db_maintenance_cleaner.sh', 'file_type' => 'bash', 'updated_at' => date('Y-m-d'), 'version_count' => 2],
                ['id' => 3, 'filename' => 'audit_permissions.py', 'file_type' => 'python', 'updated_at' => date('Y-m-d'), 'version_count' => 4],
                ['id' => 4, 'filename' => 'zabbix_snmp_traps.py', 'file_type' => 'python', 'updated_at' => date('Y-m-d'), 'version_count' => 1]
            ];
            echo json_encode(['success' => true, 'data' => $files]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'add_gitlab_attachment':
        try {
            $req_id = (int)($_POST['requirement_id'] ?? 0);
            $file_id = (int)($_POST['file_id'] ?? 0);

            if (!$req_id || !$file_id) {
                echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
                exit;
            }

            $files = [
                1 => ['name' => 'backup_monitor_v1.py', 'content' => "#!/usr/bin/env python3\n# Script de monitoreo y respaldo\nprint('Respaldos OK')\n"],
                2 => ['name' => 'db_maintenance_cleaner.sh', 'content' => "#!/bin/bash\n# Limpieza DB\necho 'Mantenimiento ejecutado'\n"],
                3 => ['name' => 'audit_permissions.py', 'content' => "#!/usr/bin/env python3\n# Auditoría de permisos\nprint('Auditoría completada')\n"],
                4 => ['name' => 'zabbix_snmp_traps.py', 'content' => "#!/usr/bin/env python3\n# Traps SNMP\nprint('Snmp listener ready')\n"]
            ];

            $fInfo = $files[$file_id] ?? ['name' => "script_{$file_id}.py", 'content' => "# Script automatización\n"];
            $upload_dir = __DIR__ . '/uploads/attachments/ACT_' . $req_id;
            if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);

            $stored_name = date('Ymd_His') . '_' . $fInfo['name'];
            file_put_contents($upload_dir . '/' . $stored_name, $fInfo['content']);
            $rel_path = 'uploads/attachments/ACT_' . $req_id . '/' . $stored_name;

            $attId = $data['next_attachment_id']++;
            $data['attachments'][] = [
                'id' => $attId,
                'requirement_id' => $req_id,
                'original_name' => $fInfo['name'],
                'stored_name' => $stored_name,
                'file_path' => $rel_path,
                'file_size' => strlen($fInfo['content']),
                'mime_type' => 'text/x-script',
                'description' => 'Archivo importado desde repositorio de scripts',
                'uploaded_by' => $current_username,
                'created_at' => date('Y-m-d H:i:s')
            ];

            ActividadesStore::saveData($data);
            echo json_encode(['success' => true, 'message' => "Archivo {$fInfo['name']} adjuntado exitosamente"]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'upload_layout_image':
        try {
            $fileKey = isset($_FILES['layout_image']) ? 'layout_image' : (isset($_FILES['layout_file']) ? 'layout_file' : null);
            if (!$fileKey || !isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'error' => 'Error al subir la imagen o ningún archivo seleccionado']);
                exit;
            }

            $file = $_FILES[$fileKey];
            $req_id = (int)($_POST['requirement_id'] ?? 0);
            $stage_num = (int)($_POST['stage_num'] ?? 4);

            $upload_dir = __DIR__ . '/uploads/layouts';
            if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (empty($ext)) $ext = 'png';
            $name = 'layout_req_' . $req_id . '_stg_' . $stage_num . '_' . time() . '_' . rand(100, 999) . '.' . $ext;
            $target = $upload_dir . '/' . $name;

            if (move_uploaded_file($file['tmp_name'], $target)) {
                $rel_path = 'uploads/layouts/' . $name;

                if ($req_id > 0) {
                    $attId = $data['next_attachment_id']++;
                    $data['attachments'][] = [
                        'id' => $attId,
                        'requirement_id' => $req_id,
                        'original_name' => basename($file['name']),
                        'stored_name' => $name,
                        'file_path' => $rel_path,
                        'file_size' => $file['size'],
                        'mime_type' => $file['type'],
                        'description' => 'Imagen de maquetación (Etapa 4)',
                        'uploaded_by' => $current_username,
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    logRequirementEvent($data, $req_id, 'MAQUETACION', $current_username, "Carga de diagrama / maqueta para Etapa 4 (" . basename($file['name']) . ")");
                    ActividadesStore::saveData($data);
                }

                echo json_encode([
                    'success' => true,
                    'file_path' => $rel_path,
                    'url' => $rel_path,
                    'message' => 'Imagen de maquetación subida correctamente'
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al mover el archivo']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'git_push':
        try {
            $req_id = (int)($_POST['requirement_id'] ?? 0);
            $ticket_code = trim($_POST['ticket_code'] ?? 'ACT-' . date('Ymd'));
            $script_name = trim($_POST['script_name'] ?? 'script_actividad.py');
            $code_content = $_POST['code_content'] ?? '';
            $commit_message = trim($_POST['commit_message'] ?? '');
            $version_tag = trim($_POST['version_tag'] ?? 'v1.0.0');

            if (empty($script_name)) $script_name = 'script_actividad.py';
            $file_basename = pathinfo($script_name, PATHINFO_FILENAME);
            if (empty($commit_message)) {
                $commit_message = "[{$ticket_code}] Actualización de script {$script_name} {$version_tag} en carpeta {$file_basename}/";
            }

            $repo_dir = __DIR__ . '/repository';
            if (!is_dir($repo_dir)) @mkdir($repo_dir, 0777, true);

            $script_folder = $repo_dir . '/' . $file_basename;
            if (!is_dir($script_folder)) @mkdir($script_folder, 0777, true);

            $target_file = $script_folder . '/' . $script_name;
            file_put_contents($target_file, $code_content);

            $commit_hash = substr(md5(time() . $script_name), 0, 10);

            $gitId = $data['next_git_id']++;
            $data['git_logs'][] = [
                'id' => $gitId,
                'requirement_id' => $req_id,
                'ticket_code' => $ticket_code,
                'script_name' => $script_name,
                'commit_hash' => $commit_hash,
                'commit_message' => $commit_message,
                'branch' => 'main',
                'pushed_by' => $current_username,
                'created_at' => date('Y-m-d H:i:s')
            ];

            if ($req_id > 0) {
                logRequirementEvent($data, $req_id, 'GIT_PUSH', $current_username, "Push de código a repositorio (Commit {$commit_hash}, Tag {$version_tag}): {$commit_message}");
            }

            ActividadesStore::saveData($data);

            echo json_encode([
                'success' => true,
                'commit_hash' => $commit_hash,
                'folder' => $file_basename . '/',
                'message' => "Código registrado exitosamente en el repositorio local (Commit: {$commit_hash})"
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'get_git_logs':
        try {
            $req_id = (int)($_GET['requirement_id'] ?? 0);
            $logs = [];
            foreach ($data['git_logs'] as $g) {
                if ((int)$g['requirement_id'] === $req_id) {
                    $logs[] = $g;
                }
            }
            usort($logs, function ($a, $b) {
                return ($b['id'] ?? 0) - ($a['id'] ?? 0);
            });
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

            $foundIndex = -1;
            foreach ($data['requirements'] as $idx => $r) {
                if ((int)$r['id'] === $id) {
                    $foundIndex = $idx;
                    break;
                }
            }

            if ($foundIndex === -1) {
                echo json_encode(['success' => false, 'error' => 'Actividad no encontrada']);
                exit;
            }

            array_splice($data['requirements'], $foundIndex, 1);

            // Eliminar historial, adjuntos y bitácoras asociadas
            $data['history'] = array_values(array_filter($data['history'], function ($h) use ($id) {
                return (int)$h['requirement_id'] !== $id;
            }));

            $data['attachments'] = array_values(array_filter($data['attachments'], function ($a) use ($id) {
                return (int)$a['requirement_id'] !== $id;
            }));

            $data['bitacora'] = array_values(array_filter($data['bitacora'], function ($b) use ($id) {
                return (int)$b['requirement_id'] !== $id;
            }));

            $data['git_logs'] = array_values(array_filter($data['git_logs'], function ($g) use ($id) {
                return (int)$g['requirement_id'] !== $id;
            }));

            ActividadesStore::saveData($data);
            echo json_encode(['success' => true, 'message' => 'Actividad eliminada exitosamente']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'list_bitacora':
        try {
            $req_id = (int)($_GET['requirement_id'] ?? 0);
            $entries = [];

            foreach ($data['bitacora'] as $b) {
                if ((int)$b['requirement_id'] === $req_id) {
                    $item = $b;
                    $item['images'] = [];
                    foreach ($data['bitacora_images'] as $img) {
                        if ((int)$img['bitacora_id'] === (int)$b['id']) {
                            $item['images'][] = $img;
                        }
                    }
                    $entries[] = $item;
                }
            }

            usort($entries, function ($a, $b) {
                return strcmp($b['fecha'] ?? '', $a['fecha'] ?? '');
            });

            echo json_encode(['success' => true, 'data' => $entries]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'save_bitacora':
        try {
            $bit_id = (int)($_POST['bitacora_id'] ?? 0);
            $req_id = (int)($_POST['requirement_id'] ?? 0);
            $fecha = trim($_POST['fecha'] ?? date('Y-m-d'));
            $hora = trim($_POST['hora'] ?? date('H:i'));
            $usuario = trim($_POST['usuario'] ?? $current_username);
            $tema = trim($_POST['tema'] ?? $_POST['tipo_trabajo'] ?? 'Actividad');
            $tipo_trabajo = $tema;
            $descripcion = trim($_POST['descripcion'] ?? '');

            if (!$req_id || empty($descripcion)) {
                echo json_encode(['success' => false, 'error' => 'Descripción obligatoria para registrar en bitácora']);
                exit;
            }

            if ($bit_id > 0) {
                $found = false;
                foreach ($data['bitacora'] as &$b) {
                    if ((int)$b['id'] === $bit_id) {
                        $b['fecha'] = $fecha;
                        $b['hora'] = $hora;
                        $b['usuario'] = $usuario;
                        $b['created_by'] = $usuario;
                        $b['tema'] = $tema;
                        $b['tipo_trabajo'] = $tipo_trabajo;
                        $b['descripcion'] = $descripcion;
                        $found = true;
                        break;
                    }
                }
                unset($b);
                $bitId = $bit_id;
                $descSnippet = mb_substr(strip_tags($descripcion), 0, 70);
                logRequirementEvent($data, $req_id, 'BITACORA', $current_username, "Actualización en bitácora [{$tema}]: {$descSnippet}...");
            } else {
                $bitId = $data['next_bitacora_id']++;
                $data['bitacora'][] = [
                    'id' => $bitId,
                    'requirement_id' => $req_id,
                    'fecha' => $fecha,
                    'hora' => $hora,
                    'usuario' => $usuario,
                    'created_by' => $usuario,
                    'tema' => $tema,
                    'tipo_trabajo' => $tipo_trabajo,
                    'descripcion' => $descripcion,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                $descSnippet = mb_substr(strip_tags($descripcion), 0, 70);
                logRequirementEvent($data, $req_id, 'BITACORA', $current_username, "Nueva actividad en bitácora [{$tema}]: {$descSnippet}...");
            }

            ActividadesStore::saveData($data);
            echo json_encode(['success' => true, 'id' => $bitId, 'message' => 'Entrada de bitácora registrada correctamente']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'delete_bitacora':
        try {
            $bit_id = (int)($_POST['bitacora_id'] ?? 0);
            $foundIndex = -1;
            $reqId = 0;
            foreach ($data['bitacora'] as $idx => $b) {
                if ((int)$b['id'] === $bit_id) {
                    $foundIndex = $idx;
                    $reqId = (int)($b['requirement_id'] ?? 0);
                    break;
                }
            }

            if ($foundIndex !== -1) {
                array_splice($data['bitacora'], $foundIndex, 1);
                // Eliminar imagenes de esa bitacora
                $data['bitacora_images'] = array_values(array_filter($data['bitacora_images'], function ($img) use ($bit_id) {
                    if ((int)$img['bitacora_id'] === $bit_id) {
                        $p = __DIR__ . '/' . $img['file_path'];
                        if (file_exists($p)) @unlink($p);
                        return false;
                    }
                    return true;
                }));

                if ($reqId > 0) {
                    logRequirementEvent($data, $reqId, 'BITACORA', $current_username, "Eliminación de entrada de bitácora ID #{$bit_id}");
                }

                ActividadesStore::saveData($data);
                echo json_encode(['success' => true, 'message' => 'Entrada de bitácora eliminada']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Entrada no encontrada']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'upload_bitacora_image':
        try {
            $bit_id = (int)($_POST['bitacora_id'] ?? 0);
            $req_id = (int)($_POST['requirement_id'] ?? 0);

            if (!$bit_id) {
                echo json_encode(['success' => false, 'error' => 'ID de bitácora no especificado']);
                exit;
            }

            $upload_dir = __DIR__ . '/uploads/bitacora/' . $bit_id;
            if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);

            // 1. Imagen base64 (portapapeles / drag & drop / selector)
            if (!empty($_POST['image_base64'])) {
                $base64_str = $_POST['image_base64'];
                $ext = 'png';
                if (preg_match('/^data:image\/(\w+);base64,/', $base64_str, $type)) {
                    $data_b64 = substr($base64_str, strpos($base64_str, ',') + 1);
                    $ext = strtolower($type[1]);
                    if ($ext === 'jpeg') $ext = 'jpg';
                } else {
                    $data_b64 = $base64_str;
                }

                $binary = base64_decode($data_b64);
                if ($binary === false) {
                    echo json_encode(['success' => false, 'error' => 'Error al decodificar imagen base64']);
                    exit;
                }

                $stored_name = date('Ymd_His') . '_' . rand(1000, 9999) . '.' . $ext;
                $target = $upload_dir . '/' . $stored_name;

                if (file_put_contents($target, $binary) !== false) {
                    $rel_path = 'uploads/bitacora/' . $bit_id . '/' . $stored_name;
                    $imgId = $data['next_image_id']++;

                    $data['bitacora_images'][] = [
                        'id' => $imgId,
                        'bitacora_id' => $bit_id,
                        'requirement_id' => $req_id,
                        'file_path' => $rel_path,
                        'original_name' => 'captura_' . date('His') . '.' . $ext,
                        'doc_type' => 'image',
                        'file_size' => strlen($binary),
                        'mime_type' => 'image/' . $ext,
                        'created_at' => date('Y-m-d H:i:s')
                    ];

                    if ($req_id > 0) {
                        logRequirementEvent($data, $req_id, 'BITACORA', $current_username, "Subida de imagen/evidencia a bitácora");
                    }

                    ActividadesStore::saveData($data);
                    echo json_encode(['success' => true, 'id' => $imgId, 'path' => $rel_path, 'file_path' => $rel_path, 'message' => 'Imagen de bitácora subida correctamente']);
                    exit;
                } else {
                    echo json_encode(['success' => false, 'error' => 'No se pudo guardar la imagen']);
                    exit;
                }
            }

            // 2. Archivo normal subido
            $fileKey = isset($_FILES['bitacora_image']) ? 'bitacora_image' : (isset($_FILES['image']) ? 'image' : null);
            if ($fileKey && isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES[$fileKey];
                $stored_name = date('Ymd_His') . '_' . preg_replace('/[^A-Za-z0-9_\-\.]/', '_', basename($file['name']));
                $target = $upload_dir . '/' . $stored_name;

                if (move_uploaded_file($file['tmp_name'], $target)) {
                    $rel_path = 'uploads/bitacora/' . $bit_id . '/' . $stored_name;
                    $imgId = $data['next_image_id']++;

                    $data['bitacora_images'][] = [
                        'id' => $imgId,
                        'bitacora_id' => $bit_id,
                        'requirement_id' => $req_id,
                        'file_path' => $rel_path,
                        'original_name' => basename($file['name']),
                        'doc_type' => 'image',
                        'file_size' => $file['size'],
                        'mime_type' => $file['type'],
                        'created_at' => date('Y-m-d H:i:s')
                    ];

                    if ($req_id > 0) {
                        logRequirementEvent($data, $req_id, 'BITACORA', $current_username, "Subida de evidencia gráfica a bitácora (" . basename($file['name']) . ")");
                    }

                    ActividadesStore::saveData($data);
                    echo json_encode(['success' => true, 'id' => $imgId, 'path' => $rel_path, 'file_path' => $rel_path, 'message' => 'Imagen de bitácora subida correctamente']);
                    exit;
                }
            }

            echo json_encode(['success' => false, 'error' => 'No se recibió ninguna imagen válida']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'upload_bitacora_document':
        try {
            $bit_id = (int)($_POST['bitacora_id'] ?? 0);
            $req_id = (int)($_POST['requirement_id'] ?? 0);

            if (!$bit_id) {
                echo json_encode(['success' => false, 'error' => 'ID de bitácora no especificado']);
                exit;
            }

            $fileKey = isset($_FILES['bitacora_doc']) ? 'bitacora_doc' : (isset($_FILES['document']) ? 'document' : (isset($_FILES['doc']) ? 'doc' : null));
            if (!$fileKey || !isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'error' => 'Error en el documento cargado o no seleccionado']);
                exit;
            }

            $file = $_FILES[$fileKey];
            $upload_dir = __DIR__ . '/uploads/bitacora/' . $bit_id;
            if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);

            $stored_name = date('Ymd_His') . '_' . preg_replace('/[^A-Za-z0-9_\-\.]/', '_', basename($file['name']));
            $target = $upload_dir . '/' . $stored_name;

            if (move_uploaded_file($file['tmp_name'], $target)) {
                $rel_path = 'uploads/bitacora/' . $bit_id . '/' . $stored_name;
                $docId = $data['next_image_id']++;

                $data['bitacora_images'][] = [
                    'id' => $docId,
                    'bitacora_id' => $bit_id,
                    'requirement_id' => $req_id,
                    'file_path' => $rel_path,
                    'original_name' => basename($file['name']),
                    'doc_type' => 'document',
                    'file_size' => $file['size'],
                    'mime_type' => $file['type'],
                    'created_at' => date('Y-m-d H:i:s')
                ];

                if ($req_id > 0) {
                    logRequirementEvent($data, $req_id, 'BITACORA', $current_username, "Subida de documento a bitácora (" . basename($file['name']) . ")");
                }

                ActividadesStore::saveData($data);
                echo json_encode(['success' => true, 'id' => $docId, 'path' => $rel_path, 'file_path' => $rel_path, 'message' => 'Documento de bitácora subido correctamente']);
            } else {
                echo json_encode(['success' => false, 'error' => 'No se pudo guardar el documento']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'delete_bitacora_image':
        try {
            $img_id = (int)($_POST['image_id'] ?? 0);
            $foundIndex = -1;
            foreach ($data['bitacora_images'] as $idx => $img) {
                if ((int)$img['id'] === $img_id) {
                    $foundIndex = $idx;
                    break;
                }
            }

            if ($foundIndex !== -1) {
                $p = __DIR__ . '/' . $data['bitacora_images'][$foundIndex]['file_path'];
                if (file_exists($p)) @unlink($p);
                array_splice($data['bitacora_images'], $foundIndex, 1);
                ActividadesStore::saveData($data);
                echo json_encode(['success' => true, 'message' => 'Imagen eliminada']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Imagen no encontrada']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Acción no válida o no especificada']);
        break;
}

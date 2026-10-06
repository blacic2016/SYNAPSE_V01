<?php
/**
 * Vista de Impresión / Formato PDF - Plantilla de Control y Entrega de ACTIVIDADES
 * Impresión Completa de Todas las Etapas del Requerimiento (Etapas 1 a 9)
 * Soporta impresión individual (?id=X) o consolidada de todas las actividades (?all=1 / ?id=all)
 * 100% Standalone (Sin conexión a BDD / MySQL)
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/permissions_helper.php';

require_login();
$user = current_user();
if (!has_role('SUPER_ADMIN') && !has_module_access('actividades')) {
    die("Acceso denegado al módulo ACTIVIDADES.");
}

$dataFile = __DIR__ . '/data/actividades_store.json';
if (!file_exists($dataFile)) {
    die("Almacén de datos no encontrado");
}

$store = json_decode(file_get_contents($dataFile), true) ?: [];

$is_all = isset($_GET['all']) || ($_GET['id'] ?? '') === 'all';
$single_id = (int)($_GET['id'] ?? 0);

$requirements_list = [];

if ($is_all) {
    $requirements_list = $store['requirements'] ?? [];
    if (empty($requirements_list)) {
        die("No existen actividades registradas para imprimir");
    }
} else {
    if (!$single_id) {
        // Si no se pasa ID, tomar la primera actividad disponible
        if (!empty($store['requirements'])) {
            $single_id = (int)$store['requirements'][0]['id'];
        } else {
            die("ID de actividad inválido");
        }
    }

    foreach ($store['requirements'] ?? [] as $r) {
        if ((int)$r['id'] === $single_id) {
            $requirements_list[] = $r;
            break;
        }
    }

    if (empty($requirements_list)) {
        die("Actividad no encontrada");
    }
}

// Registrar evento de impresión/visualización PDF en el historial
$print_user = $user['username'] ?? 'Usuario';
$client_ip = !empty($_SERVER['HTTP_CLIENT_IP']) ? $_SERVER['HTTP_CLIENT_IP'] : (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]) : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));
$now_ts = time();
$should_save_store = false;

foreach ($requirements_list as $r_item) {
    $req_id_to_log = (int)($r_item['id'] ?? 0);
    $req_code_to_log = $r_item['ticket_code'] ?? "ACT-{$req_id_to_log}";
    if ($req_id_to_log <= 0) continue;
    
    // Throttle: no duplicar si el mismo usuario imprimió hace menos de 2 minutos
    $already_logged = false;
    if (!empty($store['history'])) {
        foreach (array_reverse($store['history']) as $h_check) {
            if ((int)($h_check['requirement_id'] ?? 0) === $req_id_to_log
                && ($h_check['action'] ?? '') === 'IMPRESION_PDF'
                && ($h_check['changed_by_user'] ?? '') === $print_user) {
                $last_ts = strtotime($h_check['created_at'] ?? '');
                if ($last_ts && ($now_ts - $last_ts) < 120) {
                    $already_logged = true;
                }
                break;
            }
        }
    }
    
    if (!$already_logged) {
        $next_hist_id = $store['next_history_id'] ?? (count($store['history'] ?? []) + 1);
        $store['next_history_id'] = $next_hist_id + 1;
        if (!isset($store['history']) || !is_array($store['history'])) {
            $store['history'] = [];
        }
        $store['history'][] = [
            'id' => $next_hist_id,
            'requirement_id' => $req_id_to_log,
            'action' => 'IMPRESION_PDF',
            'changed_by_user' => $print_user,
            'change_details' => "Generación / Impresión de informe PDF de actividad Ticket #{$req_code_to_log}" . ($is_all ? ' (Modo consolidado)' : ''),
            'ip_address' => $client_ip,
            'snapshot_json' => '',
            'created_at' => date('Y-m-d H:i:s')
        ];
        $should_save_store = true;
    }
}

if ($should_save_store) {
    @file_put_contents($dataFile, json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

// Cargar Logo Oficial
$logo_file = ROOT_PATH . '/logo/sondalogo.png';
$sonda_logo_src = '../../logo/sondalogo.png';
if (file_exists($logo_file)) {
    $logo_data = file_get_contents($logo_file);
    $sonda_logo_src = 'data:image/png;base64,' . base64_encode($logo_data);
}

// Resolver rutas de archivos y subidas
function resolveUploadPathPhp($path) {
    if (empty($path)) return '';
    $path = trim($path);
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0 || strpos($path, 'data:') === 0 || strpos($path, '/') === 0) {
        return $path;
    }
    $cleaned = preg_replace('#^(\.\./)+#', '', $path);
    $cleaned = preg_replace('#^\./#', '', $cleaned);
    if (strpos($cleaned, 'uploads/') === 0) {
        return $cleaned;
    }
    if (strpos($cleaned, 'storage/') === 0) {
        return '../../' . $cleaned;
    }
    return $cleaned;
}

$page_title_doc = $is_all 
    ? "Informe Consolidado - Todas las Actividades" 
    : "Informe Completo ACTIVIDADES - Ticket " . htmlspecialchars($requirements_list[0]['ticket_code']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title_doc; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        @page {
            size: letter;
            margin: 8mm 10mm 10mm 10mm;
        }
        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color-adjust: exact !important;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #222;
            background-color: #f4f6f9;
            margin: 0;
            padding: 15px;
            font-size: 12px;
            line-height: 1.4;
        }
        .page-container {
            max-width: 900px;
            margin: 0 auto 30px auto;
            background: #ffffff;
            padding: 25px 35px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-radius: 4px;
            position: relative;
        }
        .print-actions {
            max-width: 900px;
            margin: 0 auto 15px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            border: 2px solid #002B49;
            border-top: 5px solid #002B49;
        }
        .header-table td {
            padding: 10px;
            vertical-align: middle;
        }
        .brand-header {
            font-size: 18px;
            font-weight: bold;
            color: #002B49;
            letter-spacing: 0.5px;
        }
        .doc-title {
            font-size: 13.5px;
            font-weight: 800;
            text-align: center;
            text-transform: uppercase;
            color: #002B49;
            background-color: #f8fafd;
            border-left: 2px solid #00A3E0;
            border-right: 2px solid #00A3E0;
            line-height: 1.3;
        }
        .doc-meta {
            font-size: 11px;
            color: #444;
            text-align: right;
        }
        
        .section-header {
            background-color: #002B49;
            color: #ffffff;
            font-size: 12px;
            font-weight: bold;
            padding: 7px 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 16px;
            margin-bottom: 0;
            border-radius: 2px 2px 0 0;
            border-top: 3px solid #00A3E0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .section-header-blue {
            background-color: #0d6efd;
            border-top: 3px solid #00A3E0;
        }
        .section-header-green {
            background-color: #27ae60;
            border-top: 3px solid #2ecc71;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 11.5px;
        }
        .data-table th, .data-table td {
            border: 1px solid #dcdcdc;
            padding: 7px 9px;
            vertical-align: top;
        }
        .data-table th {
            background-color: #f2f4f7;
            font-weight: 600;
            text-align: left;
            color: #333;
            width: 25%;
        }

        .desc-box {
            border: 1px solid #dcdcdc;
            border-top: none;
            padding: 10px 12px;
            min-height: 50px;
            background-color: #fafafa;
            font-size: 11.5px;
            line-height: 1.5;
            margin-bottom: 12px;
        }

        .check-icon {
            display: inline-block;
            width: 13px;
            height: 13px;
            border: 1px solid #555;
            text-align: center;
            line-height: 12px;
            font-size: 9px;
            font-weight: bold;
            margin-right: 3px;
            vertical-align: middle;
        }
        .check-icon.checked {
            background-color: #0d6efd;
            color: #ffffff;
            border-color: #0d6efd;
        }

        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
            margin-bottom: 10px;
        }
        .signatures-table td {
            width: 50%;
            padding: 10px 20px;
            vertical-align: top;
        }
        .signature-title {
            font-weight: bold;
            font-size: 11.5px;
            color: #002B49;
            border-bottom: 1px solid #ccc;
            padding-bottom: 4px;
            margin-bottom: 45px;
            text-transform: uppercase;
        }
        .signature-line {
            border-top: 1px dotted #555;
            padding-top: 4px;
            font-size: 11px;
            color: #333;
            line-height: 1.4;
        }

        .badge-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-finalizado { background-color: #27ae60; color: #fff; }
        .badge-aprobado { background-color: #00A3E0; color: #fff; }
        .badge-proceso { background-color: #0d6efd; color: #fff; }
        .badge-borrador { background-color: #7f8c8d; color: #fff; }

        .footer-note {
            margin-top: 25px;
            padding-top: 8px;
            border-top: 1px solid #ddd;
            font-size: 10px;
            color: #777;
            display: flex;
            justify-content: space-between;
        }

        @media print {
            @page {
                margin: 5mm 8mm;
            }
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            html, body {
                background: #fff !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .print-actions {
                display: none !important;
            }
            .page-container {
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 0 20px 0 !important;
                width: 100% !important;
                max-width: 100% !important;
            }
            .section-header {
                background-color: #002B49 !important;
                color: #ffffff !important;
            }
            .section-header-blue {
                background-color: #0d6efd !important;
            }
            .section-header-green {
                background-color: #27ae60 !important;
            }
            .badge-finalizado { background-color: #27ae60 !important; color: #fff !important; }
            .badge-aprobado { background-color: #00A3E0 !important; color: #fff !important; }
            .badge-proceso { background-color: #0d6efd !important; color: #fff !important; }
            .badge-borrador { background-color: #7f8c8d !important; color: #fff !important; }
            .page-break {
                page-break-before: always;
            }
        }
    </style>
</head>
<body>

    <div class="print-actions">
        <div>
            <a href="index.php" style="text-decoration: none; color: #0d6efd; font-weight: bold;">
                <i class="fas fa-arrow-left"></i> Volver a Gestión de Actividades
            </a>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
            <button onclick="window.print()" style="background-color: #0d6efd; color: #fff; border: none; padding: 8px 16px; border-radius: 4px; font-weight: bold; cursor: pointer;">
                <i class="fas fa-print mr-1" style="color: #ffffff;"></i> Imprimir / Guardar PDF
            </button>
            <?php if (!$is_all): ?>
            <a href="print.php?all=1" style="background-color: #f0f7ff; color: #0d6efd; border: 1px solid #0d6efd; padding: 7px 14px; border-radius: 4px; font-weight: bold; text-decoration: none;">
                <i class="fas fa-layer-group mr-1"></i> Ver / Imprimir Todas las Actividades
            </a>
            <?php else: ?>
            <span style="background-color: #e7f1ff; color: #0d6efd; border: 1px solid #b6d4fe; padding: 7px 14px; border-radius: 4px; font-weight: bold;">
                <i class="fas fa-check-double mr-1"></i> Modo Consolidado (Todas las Actividades)
            </span>
            <?php endif; ?>
        </div>
    </div>

    <?php foreach ($requirements_list as $index => $req): ?>
        <?php
        $req_id = (int)$req['id'];

        // Cargar datos JSON de las etapas
        $stage_data = [];
        if (!empty($req['stage_data_json'])) {
            $stage_data = json_decode($req['stage_data_json'], true) ?: [];
        }

        // Cargar adjuntos
        $attachments = [];
        foreach ($store['attachments'] ?? [] as $a) {
            if ((int)$a['requirement_id'] === $req_id) {
                $attachments[] = $a;
            }
        }

        // Cargar historial de git push
        $git_logs = [];
        foreach ($store['git_logs'] ?? [] as $g) {
            if ((int)$g['requirement_id'] === $req_id) {
                $git_logs[] = $g;
            }
        }

        // Cargar bitácora vinculada con sus imágenes y documentos
        $bitacora_entries = [];
        foreach ($store['bitacora'] ?? [] as $b) {
            if ((int)$b['requirement_id'] === $req_id) {
                $b['images'] = [];
                foreach ($store['bitacora_images'] ?? [] as $img) {
                    if ((int)$img['bitacora_id'] === (int)$b['id']) {
                        $b['images'][] = $img;
                    }
                }
                $bitacora_entries[] = $b;
            }
        }

        // Diagrama
        $diagram_info = null;
        if (!empty($stage_data['stg4_diagram_selected'])) {
            $diagram_info = [
                'title' => $stage_data['stg4_diagram_selected'],
                'description' => 'Diagrama arquitectónico y flujo de actividad',
                'type_label' => 'Arquitectura / Flujo'
            ];
        }

        $emission_date_formatted = !empty($req['emission_date']) ? date('d / m / Y', strtotime($req['emission_date'])) : '___ / ___ / ______';
        $femsa_app_date = !empty($req['femsa_approval_date']) ? date('d / m / Y', strtotime($req['femsa_approval_date'])) : (!empty($stage_data['stg9_approval_date']) ? date('d / m / Y', strtotime($stage_data['stg9_approval_date'])) : '___ / ___ / ______');
        $sonda_del_date = !empty($req['sonda_delivery_date']) ? date('d / m / Y', strtotime($req['sonda_delivery_date'])) : (!empty($stage_data['stg9_delivery_date']) ? date('d / m / Y', strtotime($stage_data['stg9_delivery_date'])) : '___ / ___ / ______');

        $femsa_approver_name = !empty($req['femsa_approved_by']) ? $req['femsa_approved_by'] : (!empty($stage_data['stg9_femsa_approver']) ? $stage_data['stg9_femsa_approver'] : '');
        $sonda_deliverer_name = !empty($req['sonda_delivered_by']) ? $req['sonda_delivered_by'] : (!empty($stage_data['stg9_sonda_deliverer']) ? $stage_data['stg9_sonda_deliverer'] : $req['sonda_analyst']);

        $current_stage_num = (int)($req['current_stage'] ?: 1);
        $current_status = $req['status'] ?: 'En Proceso';
        ?>

        <?php if ($index > 0): ?>
        <div class="page-break"></div>
        <?php endif; ?>

        <div class="page-container">
            
            <!-- Header Documento con Logo Oficial SONDA -->
            <table class="header-table">
                <tr>
                    <td style="width: 32%; background-color: #ffffff; padding: 8px 12px; vertical-align: middle;">
                        <div style="display: flex; align-items: center;">
                            <img src="<?php echo $sonda_logo_src; ?>" alt="SONDA Logo" style="max-height: 48px; width: auto; max-width: 170px; display: block; object-fit: contain;">
                        </div>
                        <div style="font-size: 8.5px; color: #0d6efd; font-weight: bold; margin-top: 4px; letter-spacing: 0.5px; text-transform: uppercase;">
                            IT Services & Digital Transformation | ACTIVIDADES
                        </div>
                    </td>
                    <td class="doc-title" style="width: 40%;">
                        INFORME DE CONTROL Y ENTREGA DE ACTIVIDAD
                        <div style="font-size: 9.5px; color: #002B49; font-weight: bold; margin-top: 3px; letter-spacing: 0.3px;">
                            AUDITORÍA DE TRAZABILIDAD Y CIERRE (ETAPAS 1 A 9)
                        </div>
                    </td>
                    <td class="doc-meta" style="width: 28%; padding: 10px;">
                        <strong>Código Ticket:</strong> <span style="color: #0d6efd; font-weight: bold; font-size: 13px;"><?php echo htmlspecialchars($req['ticket_code']); ?></span><br>
                        <strong>Estado:</strong> 
                        <span class="badge-status <?php echo $current_status === 'Finalizado' ? 'badge-finalizado' : ($current_status === 'Aprobado' ? 'badge-aprobado' : 'badge-proceso'); ?>">
                            <?php echo strtoupper(htmlspecialchars($current_status)); ?>
                        </span><br>
                        <strong>Fecha Emisión:</strong> <?php echo htmlspecialchars($emission_date_formatted); ?>
                    </td>
                </tr>
            </table>

            <!-- ETAPA 1: DATOS GENERALES -->
            <div class="section-header">1. Etapa 1: Petición Inicial y Datos Generales de la Actividad</div>
            <table class="data-table">
                <tr>
                    <th>Código de Ticket:</th>
                    <td><strong style="color: #0d6efd; font-size: 13px;"><?php echo htmlspecialchars($req['ticket_code']); ?></strong></td>
                    <th>Fecha de Emisión:</th>
                    <td>[ <?php echo htmlspecialchars($emission_date_formatted); ?> ]</td>
                </tr>
                <tr>
                    <th>Solicitante:</th>
                    <td><strong><?php echo htmlspecialchars($req['femsa_requester']); ?></strong></td>
                    <th>Analista Asignado (SONDA):</th>
                    <td><strong><?php echo htmlspecialchars($req['sonda_analyst']); ?></strong></td>
                </tr>
                <tr>
                    <th>Supervisor SONDA:</th>
                    <td><?php echo htmlspecialchars($req['sonda_supervisor'] ?: 'Supervisor de Servicios'); ?></td>
                    <th>Tipo de Actividad:</th>
                    <td>
                        <span class="check-icon <?php echo $req['activity_type'] === 'Soporte' ? 'checked' : ''; ?>"><?php echo $req['activity_type'] === 'Soporte' ? '✓' : ''; ?></span> Soporte &nbsp;
                        <span class="check-icon <?php echo $req['activity_type'] === 'Incidente' ? 'checked' : ''; ?>"><?php echo $req['activity_type'] === 'Incidente' ? '✓' : ''; ?></span> Incidente &nbsp;
                        <span class="check-icon <?php echo $req['activity_type'] === 'Automatización' ? 'checked' : ''; ?>"><?php echo $req['activity_type'] === 'Automatización' ? '✓' : ''; ?></span> Automatización
                    </td>
                </tr>
                <tr>
                    <th>Etapa Actual Alcanzada:</th>
                    <td colspan="3"><strong style="color: #27ae60;">Etapa <?php echo $current_stage_num; ?> / 9 (<?php echo round(($current_stage_num / 9) * 100); ?>% completado)</strong></td>
                </tr>
            </table>
            <div style="font-weight: bold; font-size: 11px; margin-top: 4px; color: #444;">Descripción Detallada de la Actividad:</div>
            <div class="desc-box"><?php echo nl2br(htmlspecialchars($req['work_description'])); ?></div>

            <!-- ETAPA 2: ANÁLISIS & ALCANCE -->
            <div class="section-header">2. Etapa 2: Diagnóstico Técnico y Análisis de Alcance</div>
            <table class="data-table">
                <tr>
                    <th>Diagnóstico Técnico:</th>
                    <td colspan="3"><?php echo htmlspecialchars($stage_data['stg2_diagnosis'] ?? 'Diagnóstico inicial completado por el equipo técnico.'); ?></td>
                </tr>
                <tr>
                    <th>Alcance Definido:</th>
                    <td colspan="3"><?php echo htmlspecialchars($stage_data['stg2_scope'] ?? 'Alcance delimitado según especificaciones de la actividad.'); ?></td>
                </tr>
                <tr>
                    <th>Nivel de Complejidad:</th>
                    <td><strong><?php echo htmlspecialchars($stage_data['stg2_complexity'] ?? 'Media'); ?></strong></td>
                    <th>Horas Estimadas:</th>
                    <td><strong><?php echo htmlspecialchars($stage_data['stg2_estimated_hours'] ?? '16'); ?> hrs</strong></td>
                </tr>
            </table>

            <!-- ETAPA 3: DISEÑO DE SOLUCIÓN -->
            <div class="section-header">3. Etapa 3: Diseño de Solución Técnica & Acuerdos de Reunión</div>
            <table class="data-table">
                <tr>
                    <th>Fecha de Reunión:</th>
                    <td><?php echo htmlspecialchars($stage_data['stg3_meeting_date'] ?? $emission_date_formatted); ?></td>
                    <th>Asistentes Acordados:</th>
                    <td><?php echo htmlspecialchars($stage_data['stg3_attendees'] ?? 'Solicitante & Analista SONDA'); ?></td>
                </tr>
                <tr>
                    <th>Minuta y Acuerdos de Diseño:</th>
                    <td colspan="3"><?php echo htmlspecialchars($stage_data['stg3_minutes'] ?? 'Diseño técnico revisado y acordado.'); ?></td>
                </tr>
            </table>

            <!-- ETAPA 4: MAQUETACIÓN & DIAGRAMAS -->
            <div class="section-header">4. Etapa 4: Maquetación, Prototipado & Diagramación de Proceso</div>
            <table class="data-table">
                <tr>
                    <th>Especificaciones de Maquetación:</th>
                    <td colspan="3"><?php echo htmlspecialchars($stage_data['stg4_specifications'] ?? 'Estructura y prototipado preparado.'); ?></td>
                </tr>
                <?php if ($diagram_info): ?>
                <tr>
                    <th>Diagrama Vinculado:</th>
                    <td colspan="3">
                        <strong style="color: #0d6efd;"><?php echo htmlspecialchars($diagram_info['title']); ?></strong> 
                        (<?php echo htmlspecialchars($diagram_info['type_label']); ?>) - <?php echo htmlspecialchars($diagram_info['description']); ?>
                    </td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($stage_data['stg4_image_path'])): ?>
                <tr>
                    <th>Diseño / Prototipo (Mockup):</th>
                    <td colspan="3">
                        <div style="text-align: center; background-color: #f8fafc; padding: 10px; border: 1px dashed #0d6efd; border-radius: 4px;">
                            <img src="<?php echo htmlspecialchars(resolveUploadPathPhp($stage_data['stg4_image_path'])); ?>" alt="Maquetación de la Actividad" style="max-width: 100%; max-height: 380px; object-fit: contain; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                            <div style="font-size: 10.5px; color: #555; margin-top: 6px;"><i class="fas fa-image mr-1 text-primary"></i>Captura de Maquetación y Prototipado de la Solución</div>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            </table>

            <!-- ETAPA 5: PROTOTIPO & DEMO -->
            <div class="section-header">5. Etapa 5: Prototipo y Pruebas Demostrativas</div>
            <table class="data-table">
                <tr>
                    <th>Fecha de Demostración:</th>
                    <td><?php echo htmlspecialchars($stage_data['stg5_demo_date'] ?? '-'); ?></td>
                    <th>Evaluador:</th>
                    <td><?php echo htmlspecialchars($stage_data['stg5_evaluator'] ?? $req['femsa_requester']); ?></td>
                </tr>
                <tr>
                    <th>Resultado de la Demo:</th>
                    <td><strong style="color: #27ae60;"><?php echo htmlspecialchars($stage_data['stg5_result'] ?? 'Aprobado A Satisfacción'); ?></strong></td>
                    <th>Retroalimentación:</th>
                    <td><?php echo htmlspecialchars($stage_data['stg5_feedback'] ?? 'Sin observaciones'); ?></td>
                </tr>
            </table>

            <!-- ETAPA 6: PRUEBAS & QA -->
            <div class="section-header">6. Etapa 6: Aseguramiento de Calidad & Pruebas (QA)</div>
            <table class="data-table">
                <tr>
                    <th>Casos de Prueba Ejecutados:</th>
                    <td colspan="3"><?php echo htmlspecialchars($stage_data['stg6_tests'] ?? 'Pruebas funcionales ejecutadas sin incidencias.'); ?></td>
                </tr>
                <tr>
                    <th>Métricas & Cobertura QA:</th>
                    <td colspan="3"><?php echo htmlspecialchars($stage_data['stg6_metrics'] ?? '100% de casos de prueba exitosos.'); ?></td>
                </tr>
            </table>

            <!-- ETAPA 7: DESPLIEGUE EN PRODUCCIÓN -->
            <div class="section-header">7. Etapa 7: Despliegue en Producción & Ventana de Mantenimiento</div>
            <table class="data-table">
                <tr>
                    <th>Fecha de Despliegue:</th>
                    <td><?php echo htmlspecialchars($stage_data['stg7_deploy_date'] ?? '-'); ?></td>
                    <th>Aprobador del Pase:</th>
                    <td><?php echo htmlspecialchars($stage_data['stg7_approver'] ?? $req['sonda_analyst']); ?></td>
                </tr>
                <tr>
                    <th>Estado de Ventana:</th>
                    <td><strong style="color: #27ae60;"><?php echo htmlspecialchars($stage_data['stg7_window_status'] ?? 'Exitoso'); ?></strong></td>
                    <th>Checklist de Verificación:</th>
                    <td><?php echo htmlspecialchars($stage_data['stg7_checklist'] ?? 'Verificación post-despliegue OK.'); ?></td>
                </tr>
            </table>

            <!-- ETAPA 8: ADJUNTOS & ENTREGABLES -->
            <div class="section-header">8. Etapa 8: Archivos Adjuntos & Entregables Enlazados</div>
            <?php if (count($attachments) > 0): ?>
            <table class="data-table">
                <thead>
                    <tr style="background-color: #eaeaea;">
                        <th style="width: 30%;">Nombre del Archivo</th>
                        <th style="width: 15%;">Tamaño</th>
                        <th style="width: 30%;">Descripción</th>
                        <th style="width: 15%;">Subido Por</th>
                        <th style="width: 10%;">Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attachments as $att): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($att['original_name']); ?></strong></td>
                        <td><span><?php echo round(($att['file_size'] ?? 0) / 1024, 1); ?> KB</span></td>
                        <td><?php echo htmlspecialchars($att['description'] ?: 'Entregable del servicio'); ?></td>
                        <td><?php echo htmlspecialchars($att['uploaded_by']); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($att['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="desc-box" style="min-height: 30px; font-style: italic; color: #777;">No se registraron archivos adjuntos físicamente para esta actividad.</div>
            <?php endif; ?>

            <!-- ETAPA 9: PLATAFORMA SONDAADMIN & GIT PUSH -->
            <div class="section-header">9. Etapa 9: Plataforma SondaAdmin - Script & Commit a Repositorio</div>
            <table class="data-table">
                <tr>
                    <th>Script / Automatización:</th>
                    <td><strong><?php echo htmlspecialchars(!empty($stage_data['stg9_script_filename']) ? $stage_data['stg9_script_filename'] : ($req['script_name'] ?: '[ N/A ]')); ?></strong></td>
                    <th>Etiqueta de Versión:</th>
                    <td><strong style="color: #0d6efd;"><?php echo htmlspecialchars($stage_data['stg9_version_tag'] ?? 'v1.0.0'); ?></strong></td>
                </tr>
                <tr>
                    <th>Entorno Runner:</th>
                    <td><?php echo htmlspecialchars($stage_data['stg9_environment'] ?? 'SondaAdmin Python 3.10 Node'); ?></td>
                    <th>Repositorio:</th>
                    <td><code>https://github.com/sonda-latam/actividades.git</code></td>
                </tr>
            </table>

            <?php if (count($git_logs) > 0): ?>
            <div style="font-weight: bold; font-size: 11px; margin-top: 5px; color: #444;">Registro Auditado de Git Pushes Ejecutados:</div>
            <table class="data-table">
                <thead>
                    <tr style="background-color: #eaeaea;">
                        <th style="width: 15%;">Versión</th>
                        <th style="width: 20%;">Commit Hash</th>
                        <th style="width: 40%;">Mensaje de Commit</th>
                        <th style="width: 15%;">Autor</th>
                        <th style="width: 10%;">Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($git_logs as $glog): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($glog['version_tag'] ?? 'v1.0.0'); ?></strong></td>
                        <td><code><?php echo htmlspecialchars($glog['commit_hash'] ?? 'HEAD'); ?></code></td>
                        <td><?php echo htmlspecialchars($glog['commit_message']); ?></td>
                        <td><?php echo htmlspecialchars($glog['pushed_by'] ?? 'Sistema'); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($glog['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <!-- ANEXO: BITÁCORA DE TRABAJOS DIARIOS -->
            <?php if (count($bitacora_entries) > 0): ?>
            <div class="page-break"></div>
            <div class="section-header section-header-blue" style="margin-top: 16px;">
                <span><i class="fas fa-book" style="margin-right: 6px;"></i>ANEXO — Bitácora de Trabajos Diarios (<?php echo count($bitacora_entries); ?> registros)</span>
                <span style="font-size: 11px; text-transform: none;">Ticket <?php echo htmlspecialchars($req['ticket_code']); ?></span>
            </div>
            <div style="border: 1px solid #dcdcdc; border-top: none; padding: 0;">
                <?php
                $entries_by_date = [];
                foreach ($bitacora_entries as $be) {
                    $f = $be['fecha'] ?: 'Sin Fecha';
                    $entries_by_date[$f][] = $be;
                }
                krsort($entries_by_date);
                ?>
                <?php foreach ($entries_by_date as $date_str => $day_entries): ?>
                <div style="background: #1e3a8a; color: #fff; padding: 6px 12px; font-weight: bold; font-size: 11px; border-bottom: 1px solid #3b82f6; display: flex; justify-content: space-between;">
                    <span><i class="far fa-calendar-alt" style="color: #60a5fa; margin-right: 6px;"></i>FECHA: <?php echo date('d/m/Y', strtotime($date_str)); ?></span>
                    <span style="font-weight: normal; opacity: 0.9;"><?php echo count($day_entries); ?> <?php echo count($day_entries) === 1 ? 'registro' : 'registros'; ?></span>
                </div>
                <?php foreach ($day_entries as $idx => $bentry): ?>
                <div style="padding: 10px 15px; border-bottom: 1px solid #eee; <?php echo $idx % 2 === 0 ? 'background:#fafafa;' : 'background:#fff;'; ?>">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                        <div>
                            <strong style="color: #1e40af; font-size: 12px;">
                                <i class="fas fa-tag" style="color: #0d6efd; margin-right: 4px;"></i>
                                <?php echo htmlspecialchars($bentry['tipo_trabajo'] ?? 'Actividad'); ?>
                            </strong>
                        </div>
                        <small style="color: #666; font-size: 10.5px;">
                            <i class="fas fa-user" style="margin-right: 3px;"></i><?php echo htmlspecialchars($bentry['usuario'] ?? 'Técnico'); ?> | 
                            <?php echo htmlspecialchars($bentry['hora'] ?? ''); ?> hrs
                        </small>
                    </div>
                    <div style="font-size: 11.5px; color: #444; white-space: pre-wrap; line-height: 1.5;"><?php echo nl2br(htmlspecialchars($bentry['descripcion'])); ?></div>
                    <?php
                    $b_imgs = array_filter($bentry['images'] ?? [], function($x) { return ($x['doc_type'] ?? 'image') === 'image'; });
                    $b_docs = array_filter($bentry['images'] ?? [], function($x) { return ($x['doc_type'] ?? 'image') === 'document'; });
                    ?>
                    <?php if (!empty($b_imgs)): ?>
                    <div style="margin-top: 8px; display: flex; flex-wrap: wrap; gap: 8px;">
                        <?php foreach ($b_imgs as $bimg): ?>
                        <div style="border: 1px solid #ccd0d5; border-radius: 4px; padding: 3px; background: #fff; display: inline-block;">
                            <img src="<?php echo htmlspecialchars(resolveUploadPathPhp($bimg['file_path'])); ?>" alt="Evidencia Bitácora" style="max-width: 180px; max-height: 120px; object-fit: cover; display: block; border-radius: 2px;">
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($b_docs)): ?>
                    <div style="margin-top: 6px; display: flex; flex-wrap: wrap; gap: 6px;">
                        <?php foreach ($b_docs as $bdoc): ?>
                        <span style="display: inline-flex; align-items: center; background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 3px; padding: 3px 8px; font-size: 10px; color: #334155;">
                            <i class="fas fa-paperclip" style="color: #0d6efd; margin-right: 4px;"></i>
                            <strong><?php echo htmlspecialchars($bdoc['original_name']); ?></strong>
                            <?php if (!empty($bdoc['file_size'])): ?>
                            <span style="color: #64748b; margin-left: 4px;">(<?php echo round($bdoc['file_size'] / 1024, 1); ?> KB)</span>
                            <?php endif; ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- VALIDACIÓN DE LÍMITES Y FRONTERAS -->
            <div class="section-header" style="margin-top: 16px;">10. Validación de Límites y Alcance</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 55%;">Verificación de Frontera de Servicio</th>
                        <th style="width: 15%; text-align: center;">Cumple</th>
                        <th style="width: 30%;">Observación</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>¿La actividad se encuentra dentro del soporte operativo estándar?</td>
                        <td style="text-align: center;"><strong>[ <?php echo htmlspecialchars($req['limit_soporte_estandar']); ?> ]</strong></td>
                        <td><?php echo htmlspecialchars($req['obs_soporte_estandar']); ?></td>
                    </tr>
                    <tr>
                        <td>¿Se trata de un desarrollo evolutivo mayor o integración desde cero?</td>
                        <td style="text-align: center;"><strong>[ <?php echo htmlspecialchars($req['limit_desarrollo_evolutivo']); ?> ]</strong></td>
                        <td><?php echo htmlspecialchars($req['obs_desarrollo_evolutivo']); ?></td>
                    </tr>
                    <tr>
                        <td>¿Se utilizaron las herramientas y accesos oficiales aprobados?</td>
                        <td style="text-align: center;"><strong>[ <?php echo htmlspecialchars($req['limit_herramientas_femsa']); ?> ]</strong></td>
                        <td><?php echo htmlspecialchars($req['obs_herramientas_femsa']); ?></td>
                    </tr>
                </tbody>
            </table>

            <!-- CONFORMIDAD Y FIRMAS DE CIERRE -->
            <div class="section-header section-header-green" style="margin-top: 16px;">
                <span>11. Conformidad, Aprobación Final y Cierre del Servicio</span>
                <span style="font-size: 11px; text-transform: none;">ESTADO: <?php echo strtoupper(htmlspecialchars($current_status)); ?></span>
            </div>

            <p style="font-size: 11px; color: #444; margin: 10px 0 15px 0; font-style: italic;">
                Con este documento se certifica que la actividad descrita ha completado las etapas del flujo de trabajo, siendo revisada, probada y entregada a satisfacción.
            </p>

            <table class="signatures-table">
                <tr>
                    <td>
                        <div class="signature-title">APROBADO POR (CLIENTE / SOLICITANTE)</div>
                        <div class="signature-line">
                            Firma del Solicitante / Aprobador<br>
                            <strong>Nombre:</strong> <?php echo htmlspecialchars($femsa_approver_name ?: '________________________________'); ?><br>
                            <strong>Fecha Aprobación:</strong> [ <?php echo htmlspecialchars($femsa_app_date); ?> ]
                        </div>
                    </td>
                    <td>
                        <div class="signature-title">ENTREGADO POR (SONDA)</div>
                        <div class="signature-line">
                            Firma del Analista / Técnico SONDA<br>
                            <strong>Nombre:</strong> <?php echo htmlspecialchars($sonda_deliverer_name ?: 'Marco Vizcaíno'); ?><br>
                            <strong>Fecha Entrega:</strong> [ <?php echo htmlspecialchars($sonda_del_date); ?> ]
                        </div>
                    </td>
                </tr>
            </table>

            <div class="footer-note">
                <span>Provisión de Recurso Técnico en Sitio | Gestión y Control de Actividades (SONDA)</span>
                <span>SONDA Ecuador - Reporte Completo de Actividades &nbsp;|&nbsp; Ticket <?php echo htmlspecialchars($req['ticket_code']); ?></span>
            </div>

        </div>

    <?php endforeach; ?>

</body>
</html>

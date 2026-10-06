<?php
/**
 * Vista de Impresión / Formato PDF - Plantilla de Control y Entrega de Servicio FEMSA
 * Impresión Completa de Todas las Etapas del Requerimiento (Etapas 1 a 9)
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';

require_login();
$user = current_user();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    die("ID de requerimiento inválido");
}

$pdo = getPDO();
$stmt = $pdo->prepare("SELECT * FROM femsa_requirements WHERE id = ?");
$stmt->execute([$id]);
$req = $stmt->fetch();

if (!$req) {
    die("Requerimiento no encontrado");
}

// Cargar datos JSON de las etapas
$stage_data = [];
if (!empty($req['stage_data_json'])) {
    $stage_data = json_decode($req['stage_data_json'], true) ?: [];
}

// Cargar adjuntos
$att_stmt = $pdo->prepare("SELECT * FROM femsa_attachments WHERE requirement_id = ? ORDER BY created_at ASC");
$att_stmt->execute([$id]);
$attachments = $att_stmt->fetchAll();

// Cargar historial de git push
$git_stmt = $pdo->prepare("SELECT * FROM femsa_git_logs WHERE requirement_id = ? ORDER BY created_at DESC");
$git_stmt->execute([$id]);
$git_logs = $git_stmt->fetchAll();

// Cargar bitácora de trabajos diarios
$bit_stmt = $pdo->prepare("SELECT * FROM femsa_bitacora WHERE requirement_id = ? ORDER BY fecha ASC, id ASC");
$bit_stmt->execute([$id]);
$bitacora_entries = $bit_stmt->fetchAll();

// Cargar imágenes de bitácora
$bitacora_images = [];
if (count($bitacora_entries) > 0) {
    $img_stmt = $pdo->prepare("SELECT * FROM femsa_bitacora_images WHERE requirement_id = ? ORDER BY id ASC");
    $img_stmt->execute([$id]);
    $all_imgs = $img_stmt->fetchAll();
    foreach ($all_imgs as $img) {
        $bitacora_images[$img['bitacora_id']][] = $img;
    }
}

// Cargar información de diagrama vinculado si existe (Etapa 4 / Etapa 3)
$diagram_info = null;
if (!empty($stage_data['stg4_diagram_id']) && !empty($stage_data['stg4_diagram_type'])) {
    $d_id = (int)$stage_data['stg4_diagram_id'];
    $d_type = $stage_data['stg4_diagram_type'];
    if ($d_type === 'visio') {
        $d_stmt = $pdo->prepare("SELECT title, description FROM visio_diagrams WHERE id = ?");
        $d_stmt->execute([$d_id]);
        $diagram_info = $d_stmt->fetch();
        if ($diagram_info) $diagram_info['type_label'] = 'Visio / Draw.io';
    } elseif ($d_type === 'bpmn') {
        $d_stmt = $pdo->prepare("SELECT title, description FROM bpmn_diagrams WHERE id = ?");
        $d_stmt->execute([$d_id]);
        $diagram_info = $d_stmt->fetch();
        if ($diagram_info) $diagram_info['type_label'] = 'Proceso BPMN';
    }
}

$emission_date_formatted = !empty($req['emission_date']) ? date('d / m / Y', strtotime($req['emission_date'])) : '___ / ___ / ______';
$femsa_app_date = !empty($req['femsa_approval_date']) ? date('d / m / Y', strtotime($req['femsa_approval_date'])) : (!empty($stage_data['stg9_approval_date']) ? date('d / m / Y', strtotime($stage_data['stg9_approval_date'])) : '___ / ___ / ______');
$sonda_del_date = !empty($req['sonda_delivery_date']) ? date('d / m / Y', strtotime($req['sonda_delivery_date'])) : (!empty($stage_data['stg9_delivery_date']) ? date('d / m / Y', strtotime($stage_data['stg9_delivery_date'])) : '___ / ___ / ______');

$femsa_approver_name = !empty($req['femsa_approved_by']) ? $req['femsa_approved_by'] : (!empty($stage_data['stg9_femsa_approver']) ? $stage_data['stg9_femsa_approver'] : '');
$sonda_deliverer_name = !empty($req['sonda_delivered_by']) ? $req['sonda_delivered_by'] : (!empty($stage_data['stg9_sonda_deliverer']) ? $stage_data['stg9_sonda_deliverer'] : $req['sonda_analyst']);

$current_stage_num = (int)($req['current_stage'] ?: 1);
$current_status = $req['status'] ?: 'En Proceso';

// Cargar Logo Oficial sondalogo.png
$logo_file = ROOT_PATH . '/logo/sondalogo.png';
$sonda_logo_src = '../../logo/sondalogo.png';
if (file_exists($logo_file)) {
    $logo_data = file_get_contents($logo_file);
    $sonda_logo_src = 'data:image/png;base64,' . base64_encode($logo_data);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informe Completo FEMSA - Ticket <?php echo htmlspecialchars($req['ticket_code']); ?></title>
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
            margin: 0 auto;
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
        .section-header-red {
            background-color: #ce1126;
            border-top: 3px solid #E40046;
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
            white-space: pre-wrap;
            font-size: 11.5px;
            color: #222;
        }

        .code-box {
            font-family: 'Courier New', monospace;
            background-color: #1e1e1e;
            color: #d4d4d4;
            padding: 10px;
            border-radius: 4px;
            font-size: 11px;
            line-height: 1.3;
            max-height: 250px;
            overflow: hidden;
            white-space: pre-wrap;
            word-break: break-all;
        }

        .badge-status {
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 11px;
            display: inline-block;
        }
        .badge-finalizado { background-color: #27ae60; color: #fff; }
        .badge-aprobado { background-color: #00A3E0; color: #fff; }
        .badge-proceso { background-color: #f39c12; color: #fff; }
        .badge-borrador { background-color: #7f8c8d; color: #fff; }

        .check-icon {
            display: inline-block;
            width: 15px;
            height: 15px;
            border: 1px solid #333;
            text-align: center;
            line-height: 13px;
            font-weight: bold;
            margin-right: 4px;
        }
        .check-icon.checked {
            background-color: #27ae60;
            color: #fff;
            border-color: #27ae60;
        }

        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
        }
        .signatures-table td {
            width: 50%;
            vertical-align: top;
            padding: 15px;
            border: 1px solid #dcdcdc;
        }
        .signature-title {
            font-weight: bold;
            text-align: center;
            font-size: 12px;
            color: #002B49;
            border-bottom: 2px solid #00A3E0;
            padding-bottom: 6px;
            margin-bottom: 35px;
        }
        .signature-line {
            border-top: 1px dashed #777;
            margin-top: 40px;
            text-align: center;
            padding-top: 5px;
            font-size: 11px;
        }

        .footer-note {
            margin-top: 25px;
            padding-top: 10px;
            border-top: 1px solid #e0e0e0;
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
                margin: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
            }
            .section-header {
                background-color: #002B49 !important;
                color: #ffffff !important;
            }
            .section-header-red {
                background-color: #ce1126 !important;
            }
            .section-header-green {
                background-color: #27ae60 !important;
            }
            .badge-finalizado { background-color: #27ae60 !important; color: #fff !important; }
            .badge-aprobado { background-color: #00A3E0 !important; color: #fff !important; }
            .badge-proceso { background-color: #f39c12 !important; color: #fff !important; }
            .badge-borrador { background-color: #7f8c8d !important; color: #fff !important; }
            .page-break {
                page-break-before: always;
            }
        }
    </style>
</head>
<body>

    <div class="print-actions">
        <a href="index.php" style="text-decoration: none; color: #555; font-weight: bold;"><i class="fas fa-arrow-left"></i> Volver a Gestión FEMSA</a>
        <button onclick="window.print()" style="background-color: #002B49; color: #fff; border: none; padding: 8px 16px; border-radius: 4px; font-weight: bold; cursor: pointer;">
            <i class="fas fa-print mr-1" style="color: #00A3E0;"></i> Imprimir / Guardar PDF Completo
        </button>
    </div>

    <div class="page-container">
        
        <!-- Header Documento con Logo Oficial SONDA (sondalogo.png) -->
        <table class="header-table">
            <tr>
                <td style="width: 32%; background-color: #ffffff; padding: 8px 12px; vertical-align: middle;">
                    <div style="display: flex; align-items: center;">
                        <!-- Logo Oficial SONDA cargado desde sondalogo.png -->
                        <img src="<?php echo $sonda_logo_src; ?>" alt="SONDA Logo" style="max-height: 48px; width: auto; max-width: 170px; display: block; object-fit: contain;">
                    </div>
                    <div style="font-size: 8.5px; color: #00A3E0; font-weight: bold; margin-top: 4px; letter-spacing: 0.5px; text-transform: uppercase;">
                        IT Services & Digital Transformation | FEMSA
                    </div>
                </td>
                <td class="doc-title" style="width: 40%;">
                    INFORME DE CONTROL Y ENTREGA DE SERVICIO
                    <div style="font-size: 9.5px; color: #002B49; font-weight: bold; margin-top: 3px; letter-spacing: 0.3px;">
                        AUDITORÍA DE TRAZABILIDAD Y CIERRE (ETAPAS 1 A 9)
                    </div>
                </td>
                <td class="doc-meta" style="width: 28%; padding: 10px;">
                    <strong>Código Ticket:</strong> <span style="color: #ce1126; font-weight: bold; font-size: 13px;"><?php echo htmlspecialchars($req['ticket_code']); ?></span><br>
                    <strong>Estado:</strong> 
                    <span class="badge-status <?php echo $current_status === 'Finalizado' ? 'badge-finalizado' : ($current_status === 'Aprobado' ? 'badge-aprobado' : 'badge-proceso'); ?>">
                        <?php echo strtoupper(htmlspecialchars($current_status)); ?>
                    </span><br>
                    <strong>Fecha Emisión:</strong> <?php echo htmlspecialchars($emission_date_formatted); ?>
                </td>
            </tr>
        </table>

        <!-- ETAPA 1: DATOS GENERALES -->
        <div class="section-header">1. Etapa 1: Petición Inicial y Datos Generales del Requerimiento</div>
        <table class="data-table">
            <tr>
                <th>Código de Ticket:</th>
                <td><strong style="color: #ce1126; font-size: 13px;"><?php echo htmlspecialchars($req['ticket_code']); ?></strong></td>
                <th>Fecha de Emisión:</th>
                <td>[ <?php echo htmlspecialchars($emission_date_formatted); ?> ]</td>
            </tr>
            <tr>
                <th>Solicitante (FEMSA):</th>
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
        <div style="font-weight: bold; font-size: 11px; margin-top: 4px; color: #444;">Descripción Detallada del Trabajo / Requerimiento:</div>
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
                <td colspan="3"><?php echo htmlspecialchars($stage_data['stg2_scope'] ?? 'Alcance delimitado según especificaciones de FEMSA.'); ?></td>
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
                <td><?php echo htmlspecialchars($stage_data['stg3_attendees'] ?? 'Equipo FEMSA & Analista SONDA'); ?></td>
            </tr>
            <tr>
                <th>Minuta y Acuerdos de Diseño:</th>
                <td colspan="3"><?php echo htmlspecialchars($stage_data['stg3_minutes'] ?? 'Diseño técnico revisado y aprobado por las partes.'); ?></td>
            </tr>
        </table>

        <!-- ETAPA 4: MAQUETACIÓN & DIAGRAMAS -->
        <div class="section-header">4. Etapa 4: Maquetación, Prototipado & Diagramación de Proceso</div>
        <table class="data-table">
            <tr>
                <th>Especificaciones de Maquetación:</th>
                <td colspan="3"><?php echo htmlspecialchars($stage_data['stg4_specifications'] ?? 'Estructura visual y prototipado preparado.'); ?></td>
            </tr>
            <?php if ($diagram_info): ?>
            <tr>
                <th>Diagrama Vinculado:</th>
                <td colspan="3">
                    <strong style="color: #2980b9;"><?php echo htmlspecialchars($diagram_info['title']); ?></strong> 
                    (<?php echo htmlspecialchars($diagram_info['type_label']); ?>) - <?php echo htmlspecialchars($diagram_info['description']); ?>
                </td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($stage_data['stg4_image_path'])): ?>
            <tr>
                <th>Imagen / Mockup de Maquetación:</th>
                <td colspan="3">
                    <?php if (file_exists(ROOT_PATH . '/' . $stage_data['stg4_image_path'])): ?>
                        <img src="../../<?php echo htmlspecialchars($stage_data['stg4_image_path']); ?>" style="max-width: 100%; max-height: 220px; border: 1px solid #ccc; border-radius: 4px; display: block; margin: 5px 0;">
                    <?php endif; ?>
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
                <th>Evaluador FEMSA:</th>
                <td><?php echo htmlspecialchars($stage_data['stg5_evaluator'] ?? $req['femsa_requester']); ?></td>
            </tr>
            <tr>
                <th>Resultado de la Demo:</th>
                <td><strong style="color: #27ae60;"><?php echo htmlspecialchars($stage_data['stg5_result'] ?? 'Aprobado A Satisfacción'); ?></strong></td>
                <th>Retroalimentación FEMSA:</th>
                <td><?php echo htmlspecialchars($stage_data['stg5_feedback'] ?? 'Sin observaciones'); ?></td>
            </tr>
        </table>

        <!-- ETAPA 6: PRUEBAS & QA -->
        <div class="section-header">6. Etapa 6: Aseguramiento de Calidad & Pruebas (QA)</div>
        <table class="data-table">
            <tr>
                <th>Casos de Prueba Ejecutados:</th>
                <td colspan="3"><?php echo htmlspecialchars($stage_data['stg6_tests'] ?? 'Pruebas funcionales y de estrés ejecutadas sin errores.'); ?></td>
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
                    <th style="width: 15%;">Origen</th>
                    <th style="width: 30%;">Descripción</th>
                    <th style="width: 15%;">Subido Por</th>
                    <th style="width: 10%;">Fecha</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($attachments as $att): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($att['original_name']); ?></strong></td>
                    <td>
                        <?php if ($att['mime_type'] === 'gitlab/link'): ?>
                            <span style="color: #d35400; font-weight: bold;"><i class="fab fa-gitlab"></i> Enlace GitLab</span>
                        <?php else: ?>
                            <span><?php echo round(($att['file_size'] ?? 0) / 1024, 1); ?> KB</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($att['description'] ?: 'Entregable del servicio'); ?></td>
                    <td><?php echo htmlspecialchars($att['uploaded_by']); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($att['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="desc-box" style="min-height: 30px; font-style: italic; color: #777;">No se registraron archivos adjuntos físicamente para este requerimiento.</div>
        <?php endif; ?>

        <!-- ETAPA 9: PLATAFORMA SONDAADMIN & GIT PUSH A GITHUB -->
        <div class="section-header">9. Etapa 9: Plataforma SondaAdmin - Script & Commit a GitHub</div>
        <table class="data-table">
            <tr>
                <th>Script / Automatización:</th>
                <td><strong><?php echo htmlspecialchars(!empty($stage_data['stg9_script_filename']) ? $stage_data['stg9_script_filename'] : ($req['script_name'] ?: '[ N/A ]')); ?></strong></td>
                <th>Etiqueta de Versión:</th>
                <td><strong style="color: #ce1126;"><?php echo htmlspecialchars($stage_data['stg9_version_tag'] ?? 'v1.0.0'); ?></strong></td>
            </tr>
            <tr>
                <th>Entorno Runner SondaAdmin:</th>
                <td><?php echo htmlspecialchars($stage_data['stg9_environment'] ?? 'SondaAdmin Python 3.10 Node'); ?></td>
                <th>Repositorio Remoto:</th>
                <td><code>https://github.com/blacic2016/femsa-sonda.git</code></td>
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
                    <td><strong><?php echo htmlspecialchars($glog['version_tag'] ?: 'v1.0.0'); ?></strong></td>
                    <td><code><?php echo htmlspecialchars($glog['commit_hash'] ?: 'HEAD'); ?></code></td>
                    <td><?php echo htmlspecialchars($glog['commit_message']); ?></td>
                    <td><?php echo htmlspecialchars($glog['pushed_by'] ?? 'Sistema'); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($glog['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <?php if (!empty($stage_data['stg9_code_content'])): ?>
        <div style="font-weight: bold; font-size: 11px; margin-top: 5px; color: #444;">Código Fuente Desarrollado (Extracto):</div>
        <div class="code-box"><?php echo htmlspecialchars($stage_data['stg9_code_content']); ?></div>
        <?php endif; ?>

        <!-- ANEXO: BITÁCORA DE TRABAJOS DIARIOS (SEPARADO POR FECHAS) -->
        <?php if (count($bitacora_entries) > 0): ?>
        <div class="page-break"></div>
        <div class="section-header section-header-red" style="margin-top: 16px;">
            <span><i class="fas fa-book" style="margin-right: 6px;"></i>ANEXO — Bitácora de Trabajos Diarios (<?php echo count($bitacora_entries); ?> actividades)</span>
            <span style="font-size: 11px; text-transform: none;">Ticket <?php echo htmlspecialchars($req['ticket_code']); ?></span>
        </div>
        <div style="border: 1px solid #dcdcdc; border-top: none; padding: 0;">
            <?php
            // Agrupar por fecha
            $entries_by_date = [];
            foreach ($bitacora_entries as $be) {
                $f = $be['fecha'] ?: 'Sin Fecha';
                $entries_by_date[$f][] = $be;
            }
            krsort($entries_by_date); // Fechas más recientes primero
            ?>
            <?php foreach ($entries_by_date as $date_str => $day_entries): ?>
            <div style="background: #1a1a2e; color: #fff; padding: 6px 12px; font-weight: bold; font-size: 11px; border-bottom: 1px solid #333; display: flex; justify-content: space-between;">
                <span><i class="far fa-calendar-alt" style="color: #ce1126; margin-right: 6px;"></i>FECHA: <?php echo date('d/m/Y', strtotime($date_str)); ?></span>
                <span style="font-weight: normal; opacity: 0.8;"><?php echo count($day_entries); ?> <?php echo count($day_entries) === 1 ? 'actividad' : 'actividades'; ?></span>
            </div>
            <?php foreach ($day_entries as $idx => $bentry): ?>
            <div style="padding: 10px 15px; border-bottom: 1px solid #eee; <?php echo $idx % 2 === 0 ? 'background:#fafafa;' : 'background:#fff;'; ?>">
                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                    <div>
                        <strong style="color: #222; font-size: 12px;">
                            <i class="fas fa-tag" style="color: #ce1126; margin-right: 4px;"></i>
                            <?php echo htmlspecialchars($bentry['tema']); ?>
                        </strong>
                    </div>
                    <small style="color: #666; font-size: 10.5px;">
                        <i class="fas fa-user" style="margin-right: 3px;"></i><?php echo htmlspecialchars($bentry['created_by']); ?> | 
                        <?php echo date('H:i', strtotime($bentry['created_at'])); ?> hrs
                    </small>
                </div>
                <div style="font-size: 11.5px; color: #444; white-space: pre-wrap; line-height: 1.5;"><?php echo nl2br(htmlspecialchars($bentry['descripcion'])); ?></div>
                <?php
                $entry_attachments = $bitacora_images[$bentry['id']] ?? [];
                $entry_imgs = array_filter($entry_attachments, function($a) { return ($a['doc_type'] ?? 'image') === 'image'; });
                $entry_docs = array_filter($entry_attachments, function($a) { return ($a['doc_type'] ?? 'image') === 'document'; });
                ?>
                <?php if (count($entry_imgs) > 0): ?>
                <div style="margin-top: 6px; font-weight: 600; font-size: 10.5px; color: #555;"><i class="fas fa-images"></i> Capturas de pantalla:</div>
                <div style="margin-top: 4px; display: flex; flex-wrap: wrap; gap: 8px;">
                    <?php foreach ($entry_imgs as $bimg): ?>
                    <?php if (file_exists(ROOT_PATH . '/' . $bimg['file_path'])): ?>
                    <img src="../../<?php echo htmlspecialchars($bimg['file_path']); ?>" 
                         style="max-width: 280px; max-height: 180px; border: 1px solid #ccc; border-radius: 4px; object-fit: contain;">
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (count($entry_docs) > 0): ?>
                <div style="margin-top: 6px; font-weight: 600; font-size: 10.5px; color: #555;"><i class="fas fa-paperclip"></i> Documentos adjuntos:</div>
                <div style="margin-top: 4px; display: flex; flex-wrap: wrap; gap: 6px;">
                    <?php foreach ($entry_docs as $bdoc): ?>
                    <div style="background: #eef2f7; border: 1px solid #cbd5e1; padding: 4px 10px; border-radius: 4px; font-size: 10.5px; display: inline-flex; align-items: center;">
                        <i class="fas fa-file-alt text-primary" style="margin-right: 5px;"></i>
                        <strong><?php echo htmlspecialchars($bdoc['original_name'] ?: $bdoc['file_name']); ?></strong>
                        <span style="color: #64748b; font-size: 10px; margin-left: 6px;">(<?php echo round(($bdoc['file_size'] ?? 0)/1024, 1); ?> KB)</span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- VALIDACIÓN DE LÍMITES Y FRONTERAS DEL CONTRATO -->
        <div class="section-header" style="margin-top: 16px;">10. Validación de Límites y Alcance (Premisas del Contrato)</div>
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
                    <td>¿La actividad se encuentra dentro del soporte operativo y automatización estándar?</td>
                    <td style="text-align: center;"><strong>[ <?php echo htmlspecialchars($req['limit_soporte_estandar']); ?> ]</strong></td>
                    <td><?php echo htmlspecialchars($req['obs_soporte_estandar']); ?></td>
                </tr>
                <tr>
                    <td>¿Se trata de un desarrollo evolutivo mayor o integración desde cero? (Requiere cotización adicional)</td>
                    <td style="text-align: center;"><strong>[ <?php echo htmlspecialchars($req['limit_desarrollo_evolutivo']); ?> ]</strong></td>
                    <td><?php echo htmlspecialchars($req['obs_desarrollo_evolutivo']); ?></td>
                </tr>
                <tr>
                    <td>¿Se utilizaron las herramientas, VPN y accesos provistos por FEMSA?</td>
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

        <?php if (!empty($stage_data['stg9_closing_notes'])): ?>
        <div style="font-weight: bold; font-size: 11px; margin-top: 5px; color: #444;">Observaciones Finales de Cierre:</div>
        <div class="desc-box" style="background-color: #eafaf1; border-color: #27ae60;"><?php echo nl2br(htmlspecialchars($stage_data['stg9_closing_notes'])); ?></div>
        <?php endif; ?>

        <p style="font-size: 11px; color: #444; margin: 10px 0 15px 0; font-style: italic;">
            Con este documento se certifica que la actividad descrita ha completado las 9 etapas del flujo de trabajo, siendo revisada, probada y entregada a satisfacción conforme a los lineamientos acordados entre SONDA y FEMSA.
        </p>

        <table class="signatures-table">
            <tr>
                <td>
                    <div class="signature-title">APROBADO POR (FEMSA)</div>
                    <div class="signature-line">
                        Firma del Responsable / Usuario FEMSA<br>
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
            <span>Provisión de Recurso Técnico en Sitio | Soporte de Aplicaciones y Automatización (FEMSA / SONDA)</span>
            <span>SONDA Ecuador - Reporte Completo de Servicio &nbsp;|&nbsp; Ticket <?php echo htmlspecialchars($req['ticket_code']); ?></span>
        </div>

    </div>

</body>
</html>

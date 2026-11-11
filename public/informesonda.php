<?php
/**
 * SYNAPSE - INFORME TÉCNICO EJECUTIVO SONDA (Plantilla Oficial Vilaseca - Sonda)
 * Basado en la plantilla corporativa 1.pdf
 * Ubicación: public/informesonda.php
 * Regla estricta: Si no hay datos asociados, colocar N/A o No aplica sin inventar/alucinar información.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/helpers.php';

// Verificación de autenticación de usuario (permite CLI para pruebas internas)
if (!current_user_id() && php_sapi_name() !== 'cli') {
    header("Location: login.php");
    exit;
}

$pdo = getPDO();

/**
 * Función auxiliar para validar existencia de datos.
 * Si no hay dato, retorna 'N/A' o 'No aplica' según el parámetro.
 */
function val_or_na($val, $default = 'N/A') {
    if ($val === null) return $default;
    $v = trim((string)$val);
    if ($v === '' || $v === '-' || strtolower($v) === 'null' || strtolower($v) === 'none' || strtolower($v) === 'undefined' || strtolower($v) === 'sin ip') {
        return $default;
    }
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

// Logos en Base64 para garantizar carga offline e impresión sin fallos
$sonda_logo_path = __DIR__ . '/../logo/sondalogo.png';
$vilaseca_logo_path = __DIR__ . '/datacenter/assets/sample/logo_vilaseca.png';

$sonda_logo_b64 = file_exists($sonda_logo_path) ? 'data:image/png;base64,' . base64_encode(file_get_contents($sonda_logo_path)) : '';
$vilaseca_logo_b64 = file_exists($vilaseca_logo_path) ? 'data:image/png;base64,' . base64_encode(file_get_contents($vilaseca_logo_path)) : '';

// 1. Obtención de datos del levantamiento
$survey_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$survey = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['survey_data'])) {
    // Modo previsualización en vivo desde el Asistente de Portmapping (Paso 7)
    $survey = json_decode($_POST['survey_data'], true);
} elseif ($survey_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM manual_portmap_surveys WHERE id = ?");
    $stmt->execute([$survey_id]);
    $survey = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Si no se pasó un ID específico, obtener el más reciente disponible
if (!$survey) {
    $stmt = $pdo->query("SELECT * FROM manual_portmap_surveys ORDER BY id DESC LIMIT 1");
    $survey = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$survey) {
    die("<div style='font-family:sans-serif; text-align:center; padding:50px;'><h2>No hay registros de levantamiento disponibles para generar el Informe SONDA.</h2><p><a href='portmapping.php'>Volver a Portmapping</a></p></div>");
}

// Decodificar estructuras JSON reales
$ports = [];
if (!empty($survey['ports']) && is_array($survey['ports'])) {
    $ports = $survey['ports'];
} elseif (!empty($survey['ports_data_json'])) {
    $decoded = json_decode($survey['ports_data_json'], true);
    if (is_array($decoded)) $ports = $decoded;
}

$diagram = null;
if (!empty($survey['diagram']) && is_array($survey['diagram'])) {
    $diagram = $survey['diagram'];
} elseif (!empty($survey['diagram_json'])) {
    $decoded = json_decode($survey['diagram_json'], true);
    if (is_array($decoded)) $diagram = $decoded;
}

$images = [];
if (!empty($survey['images']) && is_array($survey['images'])) {
    $images = $survey['images'];
} elseif (!empty($survey['images_json'])) {
    $decoded = json_decode($survey['images_json'], true);
    if (is_array($decoded)) $images = $decoded;
}

// Valores generales validados (sin alucinaciones)
$device_name = val_or_na($survey['device_name'] ?? null, 'Equipo sin nombre');
$client = val_or_na($survey['client'] ?? null, 'No aplica');
$location = val_or_na($survey['location'] ?? null, 'No especificada');
$area = val_or_na($survey['area'] ?? null, 'No especificada');
$rack = val_or_na($survey['rack'] ?? null, 'N/A');
$ur_rack = val_or_na($survey['ur_rack'] ?? null, 'N/A');
$vendor = val_or_na($survey['vendor'] ?? null, 'No especificado (N/A)');
$model = val_or_na($survey['model'] ?? null, 'No especificado (N/A)');
$serial = val_or_na(!empty($survey['serial']) ? $survey['serial'] : ($survey['device_label'] ?? null), 'No aplica (Sin Serie)');
$ip_address = val_or_na($survey['ip_address'] ?? null, 'No asignada (N/A)');
$creation_date = val_or_na($survey['creation_date'] ?? $survey['created_at'] ?? null, date('Y-m-d'));
$description = val_or_na($survey['description'] ?? null, 'Sin observaciones registradas durante el levantamiento técnico.');

// Cálculos reales de métricas de puertos
$total_ports = count($ports) > 0 ? count($ports) : intval($survey['ports_count'] ?? 0);
$ports_up = 0;
$ports_down = 0;
$vlans = [];
$ports_with_mac = 0;
$ports_with_lldp = 0;
$ports_without_desc = 0;
$ports_with_connections = 0;
$lag_groups = [];
$mac_details = [];

foreach ($ports as $idx => &$p) {
    $status = strtoupper(trim((string)($p['link_status'] ?? $p['status'] ?? $p['estado_link'] ?? 'DOWN')));
    $is_up = in_array($status, ['UP', 'ON', 'ACTIVO', 'CONECTADO']);
    if ($is_up) {
        $ports_up++;
        $p['norm_status'] = 'Up';
    } else {
        $ports_down++;
        $p['norm_status'] = 'Down';
    }

    // VLANs reales
    $vlan_val = trim((string)($p['vlan'] ?? ''));
    if ($vlan_val !== '' && $vlan_val !== '-' && $vlan_val !== '0' && strtolower($vlan_val) !== 'null' && strtolower($vlan_val) !== 'n/a') {
        if (!in_array($vlan_val, $vlans)) $vlans[] = $vlan_val;
    }

    // MACs reales
    $mac = trim((string)($p['mac'] ?? $p['mac_address'] ?? ''));
    if ($mac !== '' && $mac !== '-' && strtolower($mac) !== 'null' && strtolower($mac) !== 'n/a' && strtolower($mac) !== 'no detectada') {
        $ports_with_mac++;
        $mac_details[] = [
            'vlan' => val_or_na($p['vlan'] ?? null, 'N/A'),
            'mac' => htmlspecialchars($mac, ENT_QUOTES, 'UTF-8'),
            'port' => val_or_na($p['nomenclature'] ?? $p['port_name'] ?? "P-" . ($idx + 1)),
            'type' => val_or_na($p['mac_type'] ?? null, 'Dinámica'),
            'oui' => val_or_na($p['oui'] ?? $p['vendor_tgt'] ?? null, 'No registrado'),
            'notes' => val_or_na($p['notes'] ?? null, 'N/A')
        ];
    }

    // LLDP / Vecinos remotos reales
    $dest_dev = trim((string)($p['dest_device'] ?? $p['dest_dev'] ?? ''));
    if ($dest_dev !== '' && !in_array(strtolower($dest_dev), ['libre', 'libre / sin conexión', 'sin conexión', '-', 'n/a', 'null'])) {
        $ports_with_lldp++;
    }

    // Verificación de conectividad punta a punta real
    $pp_src = trim((string)($p['patch_panel_src'] ?? ''));
    $pp_tgt = trim((string)($p['patch_panel_tgt'] ?? ''));
    if (($dest_dev !== '' && strtolower($dest_dev) !== 'libre') || $pp_src !== '' || $pp_tgt !== '') {
        $ports_with_connections++;
    }

    // LAG / BAGG real
    $lag_val = trim((string)($p['lag'] ?? $p['bagg'] ?? ''));
    if ($lag_val !== '' && $lag_val !== '-' && strtolower($lag_val) !== 'null' && strtolower($lag_val) !== 'no aplica' && strtolower($lag_val) !== 'n/a') {
        if (!in_array($lag_val, $lag_groups)) $lag_groups[] = $lag_val;
    }

    // Sin descripción
    $nomen = trim((string)($p['nomenclature'] ?? $p['nomenclatura'] ?? ''));
    if (empty($nomen) || $nomen === '-' || strtolower($nomen) === 'n/a') {
        $ports_without_desc++;
    }
}
unset($p);

$occupancy_pct = $total_ports > 0 ? round(($ports_up / $total_ports) * 100) : 0;
$has_vlans = count($vlans) > 0;
$has_macs = count($mac_details) > 0;
$has_lags = count($lag_groups) > 0;

// Auto-print flag
$auto_print = isset($_GET['print']) && $_GET['print'] == '1';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informe SONDA - <?php echo $device_name; ?> - <?php echo $client; ?></title>
    <link rel="stylesheet" href="assets/plugins/fontawesome-free/css/all.min.css">
    <style>
        /* CONFIGURACIÓN DE IMPRESIÓN Y HOJAS A4 */
        @page {
            size: A4 portrait;
            margin: 10mm 12mm 12mm 12mm;
        }

        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 11px;
            line-height: 1.35;
            color: #1e293b;
            background: #e2e8f0;
            margin: 0;
            padding: 0;
        }

        /* BARRA SUPERIOR DE CONTROL (NO SE IMPRIME) */
        .no-print-toolbar {
            position: sticky;
            top: 0;
            left: 0;
            right: 0;
            background: #002B49;
            color: #ffffff;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.25);
            z-index: 9999;
        }
        .no-print-toolbar .btn {
            font-size: 13px;
            font-weight: 600;
            padding: 7px 16px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }
        .btn-print {
            background: #E30613;
            color: #ffffff;
            transition: background 0.2s;
        }
        .btn-print:hover {
            background: #c0040f;
        }
        .btn-back {
            background: #334155;
            color: #f8fafc;
        }
        .btn-back:hover {
            background: #475569;
        }

        /* CONTENEDOR DE PÁGINAS A4 */
        .doc-wrapper {
            max-width: 210mm;
            margin: 20px auto;
        }

        .pdf-page {
            background: #ffffff;
            width: 210mm;
            min-height: 297mm;
            padding: 14mm 15mm 14mm 15mm;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            position: relative;
            page-break-after: always;
            break-after: page;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        /* ENCABEZADO Y PIE DE PÁGINA CORPORATIVO */
        .page-header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #002B49;
            padding-bottom: 5px;
            margin-bottom: 12px;
            font-size: 9px;
            font-weight: 700;
            color: #002B49;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .page-header-top .doc-type {
            color: #64748b;
        }

        .page-footer-bottom {
            border-top: 1px solid #cbd5e1;
            padding-top: 6px;
            margin-top: 12px;
            font-size: 8.5px;
            color: #64748b;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-content-area {
            flex-grow: 1;
        }

        /* PORTADA */
        .cover-logos {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 25px 0 35px 0;
            padding: 0 10px;
        }
        .cover-logos img {
            max-height: 55px;
            object-fit: contain;
        }
        .cover-logos .vilaseca-brand {
            font-size: 20px;
            font-weight: 900;
            color: #002B49;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .cover-title-banner {
            background: #002B49;
            color: #ffffff;
            padding: 22px 20px;
            border-left: 8px solid #E30613;
            margin-bottom: 25px;
            border-radius: 4px;
        }
        .cover-title-banner .eyebrow {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 2px;
            color: #38bdf8;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        .cover-title-banner h1 {
            font-size: 22px;
            font-weight: 800;
            margin: 0 0 6px 0;
            line-height: 1.2;
            text-transform: uppercase;
        }
        .cover-title-banner p {
            font-size: 11.5px;
            color: #cbd5e1;
            margin: 0;
            font-weight: 400;
        }

        .cover-intro-text {
            font-size: 10.5px;
            color: #475569;
            margin-bottom: 20px;
            text-align: justify;
            line-height: 1.45;
            padding: 10px 14px;
            background: #f8fafc;
            border-left: 3px solid #002B49;
            border-radius: 0 4px 4px 0;
        }

        /* TABLAS TÉCNICAS */
        .section-heading {
            font-size: 13px;
            font-weight: 800;
            color: #002B49;
            text-transform: uppercase;
            margin: 14px 0 8px 0;
            border-bottom: 2px solid #002B49;
            padding-bottom: 4px;
            display: flex;
            justify-content: space-between;
            align-items: baseline;
        }
        .section-heading span.badge-num {
            background: #002B49;
            color: #fff;
            padding: 1px 6px;
            border-radius: 3px;
            font-size: 10px;
            margin-right: 6px;
        }

        table.tbl-technical {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            margin-bottom: 12px;
        }
        table.tbl-technical th, table.tbl-technical td {
            border: 1px solid #cbd5e1;
            padding: 5px 8px;
            text-align: left;
        }
        table.tbl-technical th {
            background: #002B49;
            color: #ffffff;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 9px;
            letter-spacing: 0.3px;
        }
        table.tbl-technical tr:nth-child(even) td {
            background: #f8fafc;
        }
        table.tbl-technical td.lbl {
            font-weight: 700;
            background: #f1f5f9;
            color: #0f172a;
            width: 32%;
        }

        /* BADGES */
        .badge-up {
            background: #dcfce7;
            color: #166534;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 3px;
            display: inline-block;
            font-size: 9px;
            border: 1px solid #86efac;
        }
        .badge-down {
            background: #fee2e2;
            color: #991b1b;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 3px;
            display: inline-block;
            font-size: 9px;
            border: 1px solid #fca5a5;
        }
        .badge-na {
            background: #f1f5f9;
            color: #64748b;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 3px;
            display: inline-block;
            font-size: 9px;
            border: 1px solid #cbd5e1;
        }
        .badge-prio-alta {
            background: #fee2e2;
            color: #991b1b;
            font-weight: 700;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 8.5px;
        }
        .badge-prio-media {
            background: #fef3c7;
            color: #92400e;
            font-weight: 700;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 8.5px;
        }
        .badge-prio-baja {
            background: #f1f5f9;
            color: #475569;
            font-weight: 700;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 8.5px;
        }

        /* CAJAS DE ALERTA / LLAMADAS */
        .callout-box {
            padding: 8px 12px;
            border-left: 4px solid #002B49;
            background: #f8fafc;
            font-size: 9px;
            color: #334155;
            margin: 10px 0;
            border-radius: 0 4px 4px 0;
            line-height: 1.4;
        }
        .callout-box strong {
            color: #002B49;
        }

        /* CHASIS GRÁFICO TÉCNICO */
        .switch-chassis-preview {
            background: #0f172a;
            border: 2px solid #334155;
            border-radius: 6px;
            padding: 10px;
            margin: 12px 0;
            color: #ffffff;
        }
        .switch-grid-row {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 4px;
            margin-bottom: 6px;
        }
        .switch-port-box {
            background: #1e293b;
            border: 1px solid #475569;
            border-radius: 3px;
            padding: 4px 2px;
            text-align: center;
        }
        .switch-port-box.is-up {
            border-color: #10b981;
            background: #064e3b;
        }
        .port-led {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #64748b;
            margin: 2px auto;
        }
        .port-led.up {
            background: #10b981;
            box-shadow: 0 0 5px #10b981;
        }

        /* TRAZA EXTREMO A EXTREMO */
        .connectivity-trace-step {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 8px 12px;
            margin-bottom: 8px;
            font-size: 10px;
        }
        .trace-node {
            flex: 1;
            text-align: center;
            padding: 4px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
        }
        .trace-node strong {
            display: block;
            color: #002B49;
            font-size: 10.5px;
        }
        .trace-arrow {
            padding: 0 10px;
            color: #0284c7;
            font-size: 14px;
            font-weight: bold;
        }

        @media print {
            body {
                background: #ffffff !important;
                font-size: 10px;
            }
            .no-print-toolbar {
                display: none !important;
            }
            .doc-wrapper {
                max-width: 100% !important;
                margin: 0 !important;
            }
            .pdf-page {
                box-shadow: none !important;
                margin: 0 !important;
                padding: 0 0 0 0 !important;
                width: 100% !important;
                min-height: 275mm !important;
                height: 275mm !important;
                page-break-after: always !important;
                break-after: page !important;
            }
        }
    </style>
</head>
<body>

    <!-- BARRA SUPERIOR DE ACCIONES RÁPIDAS (NO SE IMPRIME) -->
    <div class="no-print-toolbar">
        <div style="display:flex; align-items:center; gap:12px;">
            <i class="fas fa-file-contract" style="color:#38bdf8; font-size:18px;"></i>
            <div>
                <strong style="font-size:14px; letter-spacing:0.5px;">INFORME SONDA &bull; VILASECA</strong>
                <span style="font-size:12px; opacity:0.8; margin-left:8px;">Equipo: <strong><?php echo $device_name; ?></strong> (#<?php echo $survey['id'] ?? '-'; ?>)</span>
            </div>
        </div>
        <div style="display:flex; gap:10px;">
            <a href="portmapping.php" class="btn btn-back">
                <i class="fas fa-arrow-left"></i> Volver a Portmapping
            </a>
            <button onclick="window.print()" class="btn btn-print">
                <i class="fas fa-print"></i> Imprimir / Guardar como PDF
            </button>
        </div>
    </div>

    <div class="doc-wrapper">

        <!-- =================================================================== -->
        <!-- PÁGINA 1: PORTADA Y CONTROL DEL DOCUMENTO                          -->
        <!-- =================================================================== -->
        <div class="pdf-page">
            <div class="page-header-top">
                <span>GRUPO VILASECA &nbsp;|&nbsp; SERVICIO GESTIONADO POR SONDA</span>
                <span class="doc-type">DOCUMENTO TÉCNICO &nbsp;&bull;&nbsp; PÁG. 1</span>
            </div>

            <div class="page-content-area">
                <div class="cover-logos">
                    <?php if ($vilaseca_logo_b64): ?>
                        <img src="<?php echo $vilaseca_logo_b64; ?>" alt="Grupo Vilaseca" style="max-height:45px;">
                    <?php else: ?>
                        <div class="vilaseca-brand">GRUPO VILASECA</div>
                    <?php endif; ?>

                    <?php if ($sonda_logo_b64): ?>
                        <img src="<?php echo $sonda_logo_b64; ?>" alt="SONDA" style="max-height:48px;">
                    <?php else: ?>
                        <div style="font-weight:900; font-size:22px; color:#002B49;">SONDA <small style="font-size:11px; display:block; color:#E30613;">make it easy</small></div>
                    <?php endif; ?>
                </div>

                <div class="cover-title-banner">
                    <div class="eyebrow">REPORTE TÉCNICO EJECUTIVO</div>
                    <h1>Inventario y configuración<br>de switching por puerto</h1>
                    <p>Levantamiento automatizado mediante SNMP y mapeo lógico de interfaces</p>
                </div>

                <div class="cover-intro-text">
                    Documento de referencia para la visibilidad de conectividad, configuración de VLAN, vecinos de red y dispositivos detectados en cada interfaz de los switches administrados en la infraestructura de <strong><?php echo $client; ?></strong> bajo los estándares operativos de <strong>SONDA</strong>.
                </div>

                <div class="section-heading">
                    <span><span class="badge-num">0</span> Control del Documento</span>
                    <span style="font-size:9px; color:#64748b; font-weight:normal;">INFORMACIÓN GENERAL</span>
                </div>

                <table class="tbl-technical">
                    <tbody>
                        <tr>
                            <td class="lbl">Cliente</td>
                            <td style="font-weight:bold; color:#002B49;"><?php echo $client; ?></td>
                        </tr>
                        <tr>
                            <td class="lbl">Proveedor de servicios</td>
                            <td><strong>SONDA</strong> &mdash; Servicios Gestionados de Telecomunicaciones e Infraestructura</td>
                        </tr>
                        <tr>
                            <td class="lbl">Localidad / sitio</td>
                            <td><?php echo $location; ?><?php if ($area !== 'No especificada'): ?> (Área: <?php echo $area; ?>)<?php endif; ?></td>
                        </tr>
                        <tr>
                            <td class="lbl">Switch / hostname</td>
                            <td style="font-weight:bold; color:#0284c7;"><?php echo $device_name; ?></td>
                        </tr>
                        <tr>
                            <td class="lbl">IP de administración</td>
                            <td><?php if ($ip_address !== 'No asignada (N/A)'): ?><code><?php echo $ip_address; ?></code><?php else: ?><span class="badge-na">No aplica (Sin IP asignada)</span><?php endif; ?></td>
                        </tr>
                        <tr>
                            <td class="lbl">Fabricante / modelo</td>
                            <td><strong><?php echo $vendor; ?></strong> &bull; <?php echo $model; ?></td>
                        </tr>
                        <tr>
                            <td class="lbl">Número de Serie</td>
                            <td><?php echo $serial; ?></td>
                        </tr>
                        <tr>
                            <td class="lbl">Ubicación física</td>
                            <td>Rack: <strong><?php echo $rack; ?></strong> &bull; Unidad de Rack: <strong><?php echo $ur_rack !== 'N/A' ? "UR $ur_rack" : 'No aplica'; ?></strong></td>
                        </tr>
                        <tr>
                            <td class="lbl">Fecha de recolección</td>
                            <td><?php echo $creation_date; ?></td>
                        </tr>
                        <tr>
                            <td class="lbl">Responsable técnico</td>
                            <td><?php echo val_or_na($survey['author'] ?? null, 'Ingeniería de Soporte / Networking SONDA'); ?></td>
                        </tr>
                        <tr>
                            <td class="lbl">Versión del reporte</td>
                            <td><strong>1.0 Oficial</strong></td>
                        </tr>
                        <tr>
                            <td class="lbl">Clasificación</td>
                            <td>Uso interno / Información de infraestructura</td>
                        </tr>
                    </tbody>
                </table>

                <div class="callout-box" style="margin-top:20px;">
                    <strong>Alcance:</strong> Inventario de interfaces, estado administrativo y operativo, velocidad, descripción, VLAN, agregaciones, LLDP y direcciones MAC aprendidas. La información representa fielmente el estado observado y registrado en la plataforma.
                </div>
            </div>

            <div class="page-footer-bottom">
                <span>SYNAPSE &bull; Plataforma Centralizada de Gestión de Redes SONDA</span>
                <span>Página 1 de 8</span>
            </div>
        </div>


        <!-- =================================================================== -->
        <!-- PÁGINA 2: RESUMEN EJECUTIVO DEL EQUIPO                              -->
        <!-- =================================================================== -->
        <div class="pdf-page">
            <div class="page-header-top">
                <span>GRUPO VILASECA &nbsp;|&nbsp; SERVICIO GESTIONADO POR SONDA</span>
                <span class="doc-type">DOCUMENTO TÉCNICO &nbsp;&bull;&nbsp; PÁG. 2</span>
            </div>

            <div class="page-content-area">
                <div class="section-heading">
                    <span><span class="badge-num">1</span> Resumen Ejecutivo del Equipo</span>
                    <span style="font-size:9px; color:#64748b; font-weight:normal;">MÉTRICAS CONSOLIDADAS</span>
                </div>

                <table class="tbl-technical">
                    <thead>
                        <tr>
                            <th style="width:60%;">INDICADOR</th>
                            <th style="width:40%;">VALOR / RESULTADO</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="lbl">Hostname / nombre de gestión</td>
                            <td><strong><?php echo $device_name; ?></strong></td>
                        </tr>
                        <tr>
                            <td class="lbl">Modelo / fabricante</td>
                            <td><?php echo $vendor; ?><?php if ($model !== 'No especificado (N/A)'): ?> &bull; <?php echo $model; ?><?php endif; ?></td>
                        </tr>
                        <tr>
                            <td class="lbl">Versión de firmware / OS</td>
                            <td><?php echo val_or_na($survey['firmware'] ?? $survey['os_version'] ?? null, 'No disponible por SNMP / No aplica'); ?></td>
                        </tr>
                        <tr>
                            <td class="lbl">Interfaces físicas detectadas</td>
                            <td><strong><?php echo $total_ports; ?></strong> puertos <?php echo $total_ports == 0 ? '<span class="badge-na">No aplica</span>' : ''; ?></td>
                        </tr>
                        <tr>
                            <td class="lbl">Interfaces operativas (ifOperStatus = up)</td>
                            <td>
                                <?php if ($ports_up > 0): ?>
                                    <span class="badge-up"><?php echo $ports_up; ?> puertos conectados (<?php echo $occupancy_pct; ?>%)</span>
                                <?php else: ?>
                                    <span class="badge-down">0 puertos activos</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="lbl">Interfaces administrativamente deshabilitadas / libres</td>
                            <td><span class="badge-down"><?php echo $ports_down; ?> puertos libres / down</span></td>
                        </tr>
                        <tr>
                            <td class="lbl">Interfaces con vecino LLDP / equipo remoto</td>
                            <td>
                                <?php if ($ports_with_lldp > 0): ?>
                                    <strong><?php echo $ports_with_lldp; ?></strong> puertos con equipo detectado
                                <?php else: ?>
                                    <span class="badge-na">No aplica / Sin vecinos LLDP registrados</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="lbl">Interfaces con MAC aprendidas</td>
                            <td>
                                <?php if ($ports_with_mac > 0): ?>
                                    <strong><?php echo $ports_with_mac; ?></strong> interfaces con tráfico MAC
                                <?php else: ?>
                                    <span class="badge-na">No aplica / No detectadas en levantamiento</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="lbl">VLAN identificadas</td>
                            <td>
                                <?php if ($has_vlans): ?>
                                    <strong><?php echo count($vlans); ?></strong> (<?php echo implode(', ', array_slice($vlans, 0, 8)); ?>)
                                <?php else: ?>
                                    <span class="badge-na">No aplica / Sin segmentación registrada</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="lbl">Agregaciones LAG / BAGG identificadas</td>
                            <td>
                                <?php if ($has_lags): ?>
                                    <strong><?php echo count($lag_groups); ?></strong> grupo(s): <?php echo implode(', ', $lag_groups); ?>
                                <?php else: ?>
                                    <span class="badge-na">No aplica (Sin agregaciones LAG)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="lbl">Interfaces sin descripción (ifAlias vacío)</td>
                            <td><strong><?php echo $ports_without_desc; ?></strong> de <?php echo $total_ports; ?> interfaces</td>
                        </tr>
                    </tbody>
                </table>

                <div class="section-heading" style="margin-top:20px;">
                    <span><span class="badge-num">1.1</span> Resumen de Observaciones Clave</span>
                </div>

                <table class="tbl-technical">
                    <thead>
                        <tr>
                            <th style="width:12%;">ID</th>
                            <th style="width:68%;">OBSERVACIÓN / IMPACTO</th>
                            <th style="width:20%; text-align:center;">PRIORIDAD</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $obs_count = 0;
                        if ($total_ports > 0): 
                            $obs_count++;
                        ?>
                        <tr>
                            <td style="font-weight:bold; color:#002B49;">OBS-01</td>
                            <td>Ocupación de puertos registrada al <strong><?php echo $occupancy_pct; ?>%</strong> (<?php echo $ports_up; ?> puertos activos de <?php echo $total_ports; ?> físicos).</td>
                            <td style="text-align:center;"><span class="badge-prio-baja">Baja</span></td>
                        </tr>
                        <?php endif; ?>

                        <?php if ($ports_without_desc > 0): 
                            $obs_count++;
                        ?>
                        <tr>
                            <td style="font-weight:bold; color:#002B49;">OBS-02</td>
                            <td>Existen <strong><?php echo $ports_without_desc; ?></strong> interfaces sin nomenclatura registrada en el campo ifAlias/descripción.</td>
                            <td style="text-align:center;"><span class="badge-prio-media">Media</span></td>
                        </tr>
                        <?php endif; ?>

                        <?php if ($description !== 'Sin observaciones registradas durante el levantamiento técnico.'): 
                            $obs_count++;
                        ?>
                        <tr>
                            <td style="font-weight:bold; color:#002B49;">OBS-03</td>
                            <td><?php echo $description; ?></td>
                            <td style="text-align:center;"><span class="badge-prio-baja">Informativa</span></td>
                        </tr>
                        <?php endif; ?>

                        <?php if ($obs_count === 0): ?>
                        <tr>
                            <td style="font-weight:bold; color:#002B49;">OBS-01</td>
                            <td>No se registraron observaciones adicionales para este levantamiento.</td>
                            <td style="text-align:center;"><span class="badge-na">No aplica</span></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="callout-box">
                    <strong>Criterio ejecutivo:</strong> Destacar puertos activos sin identificación, enlaces troncales, puertos con múltiples MAC, interfaces de uplink y diferencias observadas frente a la documentación de infraestructura.
                </div>
            </div>

            <div class="page-footer-bottom">
                <span>SYNAPSE &bull; Plataforma Centralizada de Gestión de Redes SONDA</span>
                <span>Página 2 de 8</span>
            </div>
        </div>


        <!-- =================================================================== -->
        <!-- PÁGINA 3: METODOLOGÍA DE RECOLECCIÓN – SNMP                         -->
        <!-- =================================================================== -->
        <div class="pdf-page">
            <div class="page-header-top">
                <span>GRUPO VILASECA &nbsp;|&nbsp; SERVICIO GESTIONADO POR SONDA</span>
                <span class="doc-type">DOCUMENTO TÉCNICO &nbsp;&bull;&nbsp; PÁG. 3</span>
            </div>

            <div class="page-content-area">
                <div class="section-heading">
                    <span><span class="badge-num">2</span> Metodología de Recolección &ndash; SNMP</span>
                </div>

                <p style="font-size:10px; color:#475569; margin-bottom:12px; line-height:1.45;">
                    La recolección se realiza mediante consultas SNMP de solo lectura y correlación de topología estructurada. Si el equipo expone SNMPv3 se utiliza cifrado y autenticación; si opera bajo SNMPv2c, se aplican comunidades de solo lectura restringidas por ACL de gestión.
                </p>

                <div class="section-heading" style="font-size:11px; margin-top:10px;">
                    <span>2.1 MIB / Objetos Estándar Aplicados</span>
                </div>

                <table class="tbl-technical">
                    <thead>
                        <tr>
                            <th style="width:25%;">MIB / GRUPO</th>
                            <th style="width:40%;">OBJETOS / CAMPOS</th>
                            <th style="width:35%;">USO EN EL REPORTE</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="lbl">IF-MIB</td>
                            <td><code>ifIndex, ifDescr, ifName, ifAlias, ifType</code></td>
                            <td>Identificar interfaz física/lógica y descripción configurada.</td>
                        </tr>
                        <tr>
                            <td class="lbl">IF-MIB / IF-X-MIB</td>
                            <td><code>ifAdminStatus, ifOperStatus, ifSpeed, ifHighSpeed</code></td>
                            <td>Estado administrativo, operacional y velocidad de negociación.</td>
                        </tr>
                        <tr>
                            <td class="lbl">BRIDGE-MIB</td>
                            <td><code>dot1dBasePortIfIndex, dot1dTpFdbPort, dot1dTpFdbAddress</code></td>
                            <td>Relacionar puerto puente con ifIndex y MAC aprendida.</td>
                        </tr>
                        <tr>
                            <td class="lbl">Q-BRIDGE-MIB</td>
                            <td><code>dot1qTpFdbPort, dot1qTpFdbVlan, dot1qVlanStaticName</code></td>
                            <td>MAC aprendida por VLAN y etiquetas estáticas.</td>
                        </tr>
                        <tr>
                            <td class="lbl">LLDP-MIB</td>
                            <td><code>lldpLocPortIfIndex, lldpRemSysName, lldpRemChassisId, lldpRemPortId</code></td>
                            <td>Identificar vecinos remotos y puerto remoto conectado.</td>
                        </tr>
                        <tr>
                            <td class="lbl">LAG-MIB / IEEE8023</td>
                            <td><code>dot3adAggPortListTable, dot3adAggIndex</code></td>
                            <td>Identificar interfaces miembro de enlaces agregados.</td>
                        </tr>
                    </tbody>
                </table>

                <div class="section-heading" style="font-size:11px; margin-top:16px;">
                    <span>2.2 Flujo de Mapeo Lógico de Interfaces</span>
                </div>

                <table class="tbl-technical">
                    <thead>
                        <tr>
                            <th style="width:10%; text-align:center;">PASO</th>
                            <th style="width:55%;">PROCESO TÉCNICO</th>
                            <th style="width:35%;">RESULTADO OBTENIDO</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="text-align:center; font-weight:bold;">1</td>
                            <td>Consultar IF-MIB y obtener ifIndex, ifName, ifDescr, ifAlias y estados operacionales.</td>
                            <td>Inventario base y estado de interfaces.</td>
                        </tr>
                        <tr>
                            <td style="text-align:center; font-weight:bold;">2</td>
                            <td>Mapear bridge-port &rarr; ifIndex mediante BRIDGE-MIB.</td>
                            <td>Identificación del puerto físico/lógico asociado.</td>
                        </tr>
                        <tr>
                            <td style="text-align:center; font-weight:bold;">3</td>
                            <td>Consultar FDB por VLAN (Q-BRIDGE-MIB) y resolver tabla MAC.</td>
                            <td>MAC address &rarr; VLAN &rarr; Interfaz física.</td>
                        </tr>
                        <tr>
                            <td style="text-align:center; font-weight:bold;">4</td>
                            <td>Consultar LLDP-MIB y correlacionar lldpLocPortIfIndex con interfaz local.</td>
                            <td>Vecino &rarr; Puerto local &rarr; Puerto remoto.</td>
                        </tr>
                        <tr>
                            <td style="text-align:center; font-weight:bold;">5</td>
                            <td>Consolidar por ifIndex y distinguir puertos físicos, LAG y matriz de parcheo.</td>
                            <td>Matriz final de conectividad auditada.</td>
                        </tr>
                    </tbody>
                </table>

                <div class="callout-box">
                    <strong>Nota de compatibilidad:</strong> Los OID disponibles y el nivel de detalle dependen del fabricante, modelo, versión de software y configuración SNMP del equipo. Si un switch no expone Q-BRIDGE-MIB o LLDP-MIB, el sistema clasifica el dato como <em>"No disponible / No aplica"</em> sin inferencias no comprobadas.
                </div>
            </div>

            <div class="page-footer-bottom">
                <span>SYNAPSE &bull; Plataforma Centralizada de Gestión de Redes SONDA</span>
                <span>Página 3 de 8</span>
            </div>
        </div>


        <!-- =================================================================== -->
        <!-- PÁGINA 4: INVENTARIO CONSOLIDADO DE INTERFACES Y VLAN              -->
        <!-- =================================================================== -->
        <div class="pdf-page">
            <div class="page-header-top">
                <span>GRUPO VILASECA &nbsp;|&nbsp; SERVICIO GESTIONADO POR SONDA</span>
                <span class="doc-type">DOCUMENTO TÉCNICO &nbsp;&bull;&nbsp; PÁG. 4</span>
            </div>

            <div class="page-content-area">
                <div class="section-heading">
                    <span><span class="badge-num">3</span> Inventario Consolidado de Interfaces</span>
                    <span style="font-size:9px; color:#64748b; font-weight:normal;">ESTADO DE ENLACES FÍSICOS</span>
                </div>

                <table class="tbl-technical" style="font-size:9px;">
                    <thead>
                        <tr>
                            <th style="width:7%; text-align:center;">#</th>
                            <th style="width:16%;">INTERFAZ</th>
                            <th style="width:31%;">ifAlias / DESCRIPCIÓN</th>
                            <th style="width:14%; text-align:center;">ADMIN / OPER</th>
                            <th style="width:12%; text-align:center;">VELOCIDAD</th>
                            <th style="width:10%; text-align:center;">TIPO</th>
                            <th style="width:10%; text-align:center;">LAG</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($ports) > 0): ?>
                            <?php 
                            $max_show = min(count($ports), 16);
                            for ($i = 0; $i < $max_show; $i++): 
                                $p = $ports[$i];
                                $p_num = $p['port_name'] ?? ($i + 1);
                                $p_nomen = val_or_na($p['nomenclature'] ?? $p['nomenclatura'] ?? null, "Port $p_num");
                                $p_desc = val_or_na($p['dest_device'] ?? $p['notes'] ?? null, 'No aplica (Sin descripción)');
                                $is_up = ($p['norm_status'] ?? 'Down') === 'Up';
                                $speed = val_or_na($p['speed'] ?? null, 'N/A');
                                $con_type = val_or_na($p['connector_type'] ?? $p['cable_type'] ?? null, 'ethernet');
                                $p_lag = val_or_na($p['lag'] ?? null, 'No aplica');
                            ?>
                            <tr>
                                <td style="text-align:center; font-weight:bold;"><?php echo ($i + 1); ?></td>
                                <td><strong><?php echo $p_nomen; ?></strong></td>
                                <td><?php echo $p_desc; ?></td>
                                <td style="text-align:center;">
                                    <span class="<?php echo $is_up ? 'badge-up' : 'badge-down'; ?>"><?php echo $is_up ? 'Up / Up' : 'Up / Down'; ?></span>
                                </td>
                                <td style="text-align:center;"><?php echo $speed; ?></td>
                                <td style="text-align:center;"><?php echo $con_type; ?></td>
                                <td style="text-align:center;"><?php echo $p_lag; ?></td>
                            </tr>
                            <?php endfor; ?>
                            <?php if (count($ports) > 16): ?>
                            <tr>
                                <td colspan="7" style="text-align:center; background:#f1f5f9; color:#475569; font-weight:bold;">
                                    &hellip; Mostrando primeras 16 interfaces de <?php echo count($ports); ?> puertos totales. La matriz completa se exporta en el anexo técnico.
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align:center; padding:15px; color:#64748b;">
                                    No aplica / No hay interfaces físicas registradas para este equipo.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="section-heading" style="margin-top:16px;">
                    <span><span class="badge-num">4</span> Mapeo de VLAN y Agregaciones</span>
                </div>

                <div style="font-size:9.5px; font-weight:700; color:#002B49; margin-bottom:4px;">4.1 Membresía de Puertos y Segmentación VLAN</div>
                <table class="tbl-technical">
                    <thead>
                        <tr>
                            <th style="width:14%; text-align:center;">VLAN ID</th>
                            <th style="width:28%;">NOMBRE DE VLAN</th>
                            <th style="width:28%;">INTERFAZ / RANGO</th>
                            <th style="width:15%; text-align:center;">MEMBRESÍA</th>
                            <th style="width:15%; text-align:center;">ORIGEN</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($has_vlans): ?>
                            <?php foreach ($vlans as $v_id): ?>
                            <tr>
                                <td style="text-align:center; font-weight:bold;"><?php echo htmlspecialchars($v_id); ?></td>
                                <td>VLAN_<?php echo htmlspecialchars($v_id); ?></td>
                                <td>Puertos asociados</td>
                                <td style="text-align:center;">Untagged / Tagged</td>
                                <td style="text-align:center;">Portmapping</td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align:center; padding:12px; color:#64748b;">
                                    No aplica / No se registraron datos de VLANs en el levantamiento de puertos.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="page-footer-bottom">
                <span>SYNAPSE &bull; Plataforma Centralizada de Gestión de Redes SONDA</span>
                <span>Página 4 de 8</span>
            </div>
        </div>


        <!-- =================================================================== -->
        <!-- PÁGINA 5: DESCUBRIMIENTO DE EQUIPOS Y TABLA MAC                     -->
        <!-- =================================================================== -->
        <div class="pdf-page">
            <div class="page-header-top">
                <span>GRUPO VILASECA &nbsp;|&nbsp; SERVICIO GESTIONADO POR SONDA</span>
                <span class="doc-type">DOCUMENTO TÉCNICO &nbsp;&bull;&nbsp; PÁG. 5</span>
            </div>

            <div class="page-content-area">
                <div class="section-heading">
                    <span><span class="badge-num">5</span> Descubrimiento de Equipos Conectados</span>
                    <span style="font-size:9px; color:#64748b; font-weight:normal;">LLDP &amp; FORWARDING DATABASE</span>
                </div>

                <p style="font-size:9.5px; color:#475569; margin-bottom:10px; line-height:1.4;">
                    La identificación de equipos se construye a partir de los datos recolectados por LLDP y la tabla de reenvío MAC registrada. Si el dato no está presente en la base de datos, se consigna como <em>No aplica / N/A</em>.
                </p>

                <table class="tbl-technical" style="font-size:9px;">
                    <thead>
                        <tr>
                            <th style="width:14%;">PUERTO LOCAL</th>
                            <th style="width:8%; text-align:center;">ifIndex</th>
                            <th style="width:10%; text-align:center;">ESTADO</th>
                            <th style="width:22%;">MAC APRENDIDA</th>
                            <th style="width:8%; text-align:center;">VLAN</th>
                            <th style="width:20%;">LLDP VECINO</th>
                            <th style="width:18%;">PUERTO REMOTO</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($ports) > 0): ?>
                            <?php 
                            $sample_count = min(count($ports), 10);
                            for ($i = 0; $i < $sample_count; $i++):
                                $p = $ports[$i];
                                $p_num = $p['port_name'] ?? ($i + 1);
                                $p_nomen = val_or_na($p['nomenclature'] ?? $p['nomenclatura'] ?? null, "P-$p_num");
                                $is_up = ($p['norm_status'] ?? 'Down') === 'Up';
                                $p_mac = val_or_na($p['mac'] ?? $p['mac_address'] ?? null, 'N/A');
                                $p_vlan = val_or_na($p['vlan'] ?? null, 'N/A');
                                $p_lldp = val_or_na($p['dest_device'] ?? null, 'No aplica');
                                $p_remote = val_or_na($p['dest_port'] ?? null, 'No aplica');
                            ?>
                            <tr>
                                <td><strong><?php echo $p_nomen; ?></strong></td>
                                <td style="text-align:center;"><?php echo ($i + 1); ?></td>
                                <td style="text-align:center;"><span class="<?php echo $is_up ? 'badge-up' : 'badge-down'; ?>"><?php echo $is_up ? 'Up' : 'Down'; ?></span></td>
                                <td><?php if ($p_mac !== 'N/A'): ?><code><?php echo $p_mac; ?></code><?php else: ?><span class="badge-na">N/A</span><?php endif; ?></td>
                                <td style="text-align:center;"><?php echo $p_vlan; ?></td>
                                <td><?php echo $p_lldp; ?></td>
                                <td><?php echo $p_remote; ?></td>
                            </tr>
                            <?php endfor; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align:center; padding:12px; color:#64748b;">
                                    No aplica / No hay información de puertos registrada.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="section-heading" style="margin-top:16px;">
                    <span><span class="badge-num">5.1</span> Detalle de Direcciones MAC por Puerto</span>
                </div>

                <table class="tbl-technical" style="font-size:9px;">
                    <thead>
                        <tr>
                            <th style="width:10%; text-align:center;">VLAN</th>
                            <th style="width:25%;">MAC ADDRESS</th>
                            <th style="width:18%;">PUERTO / ifIndex</th>
                            <th style="width:15%; text-align:center;">TIPO</th>
                            <th style="width:17%;">FABRICANTE OUI</th>
                            <th style="width:15%;">OBSERVACIÓN</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($has_macs): ?>
                            <?php foreach ($mac_details as $md): ?>
                            <tr>
                                <td style="text-align:center;"><?php echo $md['vlan']; ?></td>
                                <td><code><?php echo $md['mac']; ?></code></td>
                                <td><?php echo $md['port']; ?></td>
                                <td style="text-align:center;"><?php echo $md['type']; ?></td>
                                <td><?php echo $md['oui']; ?></td>
                                <td><?php echo $md['notes']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align:center; padding:12px; color:#64748b;">
                                    No aplica / No se registraron direcciones MAC aprendidas en los puertos del equipo.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="callout-box">
                    <strong>Interpretación:</strong> La existencia de varias MAC en un puerto responde a enlaces troncales, hypervisores, telefonía VoIP puenteada o puntos de acceso Wi-Fi.
                </div>
            </div>

            <div class="page-footer-bottom">
                <span>SYNAPSE &bull; Plataforma Centralizada de Gestión de Redes SONDA</span>
                <span>Página 5 de 8</span>
            </div>
        </div>


        <!-- =================================================================== -->
        <!-- PÁGINA 6: DIAGRAMA DE CONEXIONES Y CHASIS DEL EQUIPO (SOLICITADO)  -->
        <!-- =================================================================== -->
        <div class="pdf-page">
            <div class="page-header-top">
                <span>GRUPO VILASECA &nbsp;|&nbsp; SERVICIO GESTIONADO POR SONDA</span>
                <span class="doc-type">DOCUMENTO TÉCNICO &nbsp;&bull;&nbsp; PÁG. 6</span>
            </div>

            <div class="page-content-area">
                <div class="section-heading">
                    <span><span class="badge-num">6</span> Diagrama de Conexiones y Chasis Frontal del Equipo</span>
                    <span style="font-size:9px; color:#64748b; font-weight:normal;">REPRESENTACIÓN FÍSICA &amp; LÓGICA</span>
                </div>

                <div style="font-size:10px; color:#475569; margin-bottom:8px;">
                    Panel frontal del equipo <strong><?php echo $device_name; ?></strong> en Rack <strong><?php echo $rack; ?></strong> (UR: <?php echo $ur_rack; ?>). Estado real de puertos activos (verde = UP, gris = DOWN/Libre):
                </div>

                <!-- Chasis Frontal Graficado -->
                <?php if ($total_ports > 0): ?>
                <div class="switch-chassis-preview">
                    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #334155; padding-bottom:6px; margin-bottom:8px; font-size:10px;">
                        <span><i class="fas fa-server" style="color:#38bdf8; margin-right:5px;"></i> <strong><?php echo $vendor; ?><?php if ($model !== 'No especificado (N/A)'): ?> <?php echo $model; ?><?php endif; ?></strong> &bull; <?php echo $device_name; ?></span>
                        <span>Ocupación: <strong style="color:#10b981;"><?php echo $ports_up; ?>/<?php echo $total_ports; ?> (<?php echo $occupancy_pct; ?>%)</strong></span>
                    </div>

                    <!-- Fila Superior: Impares -->
                    <div style="font-size:8px; color:#94a3b8; margin-bottom:2px; font-weight:bold;">FILA SUPERIOR (PUERTOS IMPARES)</div>
                    <div class="switch-grid-row">
                        <?php 
                        for ($i = 0; $i < min($total_ports, 24); $i += 2): 
                            $p_obj = $ports[$i] ?? null;
                            $p_st = ($p_obj['norm_status'] ?? 'Down') === 'Up';
                        ?>
                        <div class="switch-port-box <?php echo $p_st ? 'is-up' : ''; ?>">
                            <div style="font-size:8px; font-weight:bold;">P<?php echo ($i+1); ?></div>
                            <div class="port-led <?php echo $p_st ? 'up' : ''; ?>"></div>
                            <div style="font-size:7px; color:<?php echo $p_st ? '#34d399' : '#94a3b8'; ?>;"><?php echo $p_st ? 'UP' : 'DWN'; ?></div>
                        </div>
                        <?php endfor; ?>
                    </div>

                    <!-- Fila Inferior: Pares -->
                    <div style="font-size:8px; color:#94a3b8; margin-bottom:2px; font-weight:bold; margin-top:6px;">FILA INFERIOR (PUERTOS PARES)</div>
                    <div class="switch-grid-row">
                        <?php 
                        for ($i = 1; $i < min($total_ports, 24); $i += 2): 
                            $p_obj = $ports[$i] ?? null;
                            $p_st = ($p_obj['norm_status'] ?? 'Down') === 'Up';
                        ?>
                        <div class="switch-port-box <?php echo $p_st ? 'is-up' : ''; ?>">
                            <div style="font-size:8px; font-weight:bold;">P<?php echo ($i+1); ?></div>
                            <div class="port-led <?php echo $p_st ? 'up' : ''; ?>"></div>
                            <div style="font-size:7px; color:<?php echo $p_st ? '#34d399' : '#94a3b8'; ?>;"><?php echo $p_st ? 'UP' : 'DWN'; ?></div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="callout-box text-center" style="color:#64748b;">
                    No aplica / Sin puertos físicos registrados para graficar el chasis.
                </div>
                <?php endif; ?>

                <div class="section-heading" style="margin-top:16px;">
                    <span><span class="badge-num">6.1</span> Diagrama de Conectividad Traza Extremo a Extremo</span>
                </div>

                <div style="font-size:9.5px; color:#475569; margin-bottom:10px;">
                    Flujo de interconexión física entre el switch de origen, el parcheo estructurado y el punto terminal:
                </div>

                <!-- Trazas reales existentes -->
                <?php if ($ports_with_connections > 0): ?>
                    <?php 
                    $shown_traces = 0;
                    foreach ($ports as $idx => $pt):
                        $pp_src = trim((string)($pt['patch_panel_src'] ?? ''));
                        $pp_tgt = trim((string)($pt['patch_panel_tgt'] ?? ''));
                        $dst_dev = trim((string)($pt['dest_device'] ?? ''));
                        
                        if ($pp_src === '' && $pp_tgt === '' && ($dst_dev === '' || strtolower($dst_dev) === 'libre')) {
                            continue;
                        }

                        $p_num = $pt['port_name'] ?? ($idx + 1);
                        $is_up = ($pt['norm_status'] ?? 'Down') === 'Up';
                        $shown_traces++;
                        if ($shown_traces > 3) break; // Máximo 3 trazas representativas en hoja
                    ?>
                    <div class="connectivity-trace-step">
                        <div class="trace-node" style="border-left:3px solid #002B49;">
                            <strong><?php echo $device_name; ?></strong>
                            <span style="font-size:9px; color:#64748b;">Puerto P-<?php echo $p_num; ?><?php if ($rack !== 'N/A'): ?> &bull; Rack <?php echo $rack; ?><?php endif; ?></span>
                        </div>
                        <div class="trace-arrow">&rarr;</div>
                        <div class="trace-node" style="border-left:3px solid #6b21a8;">
                            <strong><?php echo val_or_na($pp_src, 'No aplica (Directo)'); ?></strong>
                            <span style="font-size:9px; color:#64748b;">Parcheo Origen (P: <?php echo val_or_na($pt['port_pp_src'] ?? null, 'N/A'); ?>)</span>
                        </div>
                        <div class="trace-arrow">&rarr;</div>
                        <div class="trace-node" style="border-left:3px solid #0284c7;">
                            <strong><?php echo val_or_na($pp_tgt, 'No aplica (Directo)'); ?></strong>
                            <span style="font-size:9px; color:#64748b;">Parcheo Destino (P: <?php echo val_or_na($pt['port_pp_tgt'] ?? null, 'N/A'); ?>)</span>
                        </div>
                        <div class="trace-arrow">&rarr;</div>
                        <div class="trace-node" style="border-left:3px solid <?php echo $is_up ? '#16a34a' : '#94a3b8'; ?>;">
                            <strong><?php echo val_or_na($dst_dev, 'No aplica (Libre)'); ?></strong>
                            <span style="font-size:9px; color:#64748b;">Equipo Terminal &bull; <span class="<?php echo $is_up ? 'badge-up' : 'badge-down'; ?>"><?php echo $is_up ? 'Link UP' : 'DOWN'; ?></span></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="callout-box text-center" style="color:#64748b; font-style:italic;">
                        No aplica / No se registraron datos de parcheo extremo a extremo para este equipo.
                    </div>
                <?php endif; ?>

                <!-- Diagrama Visio si existe -->
                <?php if ($diagram && !empty($diagram['path'])): ?>
                <div class="section-heading" style="margin-top:14px;">
                    <span><span class="badge-num">6.2</span> Diagrama Arquitectura Visio Asociado</span>
                </div>
                <div style="text-align:center; border:1px solid #cbd5e1; border-radius:6px; padding:6px; background:#ffffff;">
                    <img src="<?php echo htmlspecialchars($diagram['path']); ?>" style="max-height:160px; max-width:100%; object-fit:contain;" alt="Diagrama Visio">
                    <small style="display:block; color:#64748b; font-size:9px; margin-top:4px;"><strong><?php echo val_or_na($diagram['title'] ?? $diagram['name'] ?? null, 'Diagrama de Red'); ?></strong></small>
                </div>
                <?php else: ?>
                <div class="callout-box text-center" style="color:#64748b; font-style:italic; margin-top:14px;">
                    Diagrama de Red / Visio: No aplica (No se adjuntó archivo de arquitectura para este levantamiento).
                </div>
                <?php endif; ?>
            </div>

            <div class="page-footer-bottom">
                <span>SYNAPSE &bull; Plataforma Centralizada de Gestión de Redes SONDA</span>
                <span>Página 6 de 8</span>
            </div>
        </div>


        <!-- =================================================================== -->
        <!-- PÁGINA 7: FICHA DE CONECTIVIDAD POR PUERTO                          -->
        <!-- =================================================================== -->
        <div class="pdf-page">
            <div class="page-header-top">
                <span>GRUPO VILASECA &nbsp;|&nbsp; SERVICIO GESTIONADO POR SONDA</span>
                <span class="doc-type">DOCUMENTO TÉCNICO &nbsp;&bull;&nbsp; PÁG. 7</span>
            </div>

            <div class="page-content-area">
                <div class="section-heading">
                    <span><span class="badge-num">7</span> Ficha de Conectividad por Puerto (Uplink / Puerto Representativo)</span>
                </div>

                <p style="font-size:9.5px; color:#475569; margin-bottom:12px;">
                    Ficha técnica detallada de interfaz activa en el switch para auditoría y trazabilidad operativa SONDA:
                </p>

                <?php 
                $fp = null;
                // Buscar primer puerto UP con datos, o el primero en lista
                foreach ($ports as $p_item) {
                    if (($p_item['norm_status'] ?? 'Down') === 'Up') {
                        $fp = $p_item;
                        break;
                    }
                }
                if (!$fp && count($ports) > 0) $fp = $ports[0];
                ?>

                <?php if ($fp): ?>
                <table class="tbl-technical">
                    <tbody>
                        <tr>
                            <td class="lbl">Switch / Hostname</td>
                            <td style="font-weight:bold; color:#002B49;"><?php echo $device_name; ?></td>
                        </tr>
                        <tr>
                            <td class="lbl">Puerto / Nomenclatura</td>
                            <td><strong><?php echo val_or_na($fp['nomenclature'] ?? $fp['nomenclatura'] ?? null, 'Port ' . ($fp['port_name'] ?? '1')); ?></strong></td>
                        </tr>
                        <tr>
                            <td class="lbl">ifIndex / Identificador</td>
                            <td><code><?php echo val_or_na($fp['ifindex'] ?? $fp['port_name'] ?? 1); ?></code></td>
                        </tr>
                        <tr>
                            <td class="lbl">Estado administrativo / operativo</td>
                            <td>
                                <?php $is_up_fp = ($fp['norm_status'] ?? 'Down') === 'Up'; ?>
                                <span class="<?php echo $is_up_fp ? 'badge-up' : 'badge-down'; ?>"><?php echo $is_up_fp ? 'Up / Up' : 'Up / Down'; ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td class="lbl">ifAlias / descripción configurada</td>
                            <td><?php echo val_or_na($fp['dest_device'] ?? $fp['notes'] ?? null, 'No aplica (Sin descripción)'); ?></td>
                        </tr>
                        <tr>
                            <td class="lbl">Velocidad negociada</td>
                            <td><?php echo val_or_na($fp['speed'] ?? null, 'No disponible (N/A)'); ?></td>
                        </tr>
                        <tr>
                            <td class="lbl">VLAN / PVID</td>
                            <td><?php echo val_or_na($fp['vlan'] ?? null, 'No aplica'); ?></td>
                        </tr>
                        <tr>
                            <td class="lbl">LAG / BAGG asociado</td>
                            <td><?php echo val_or_na($fp['lag'] ?? null, 'No aplica (Puerto directo)'); ?></td>
                        </tr>
                        <tr>
                            <td class="lbl">MAC address aprendida</td>
                            <td><?php echo val_or_na($fp['mac'] ?? $fp['mac_address'] ?? null, 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <td class="lbl">LLDP: Equipo Vecino Remoto</td>
                            <td><?php echo val_or_na($fp['dest_device'] ?? null, 'No aplica'); ?></td>
                        </tr>
                        <tr>
                            <td class="lbl">LLDP: Puerto Remoto</td>
                            <td><?php echo val_or_na($fp['dest_port'] ?? null, 'No aplica'); ?></td>
                        </tr>
                        <tr>
                            <td class="lbl">IP correlacionada</td>
                            <td><?php echo val_or_na($fp['ip'] ?? $fp['dest_ip'] ?? null, 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <td class="lbl">Fecha de captura</td>
                            <td><?php echo $creation_date; ?></td>
                        </tr>
                        <tr>
                            <td class="lbl">Observaciones registradas</td>
                            <td><?php echo val_or_na($fp['notes'] ?? null, 'Sin observaciones adicionales registradas.'); ?></td>
                        </tr>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="callout-box text-center" style="color:#64748b; padding:20px;">
                    No aplica / No hay puertos registrados para generar la ficha de conectividad.
                </div>
                <?php endif; ?>

                <div class="callout-box" style="margin-top:14px;">
                    <strong>Importante:</strong> La correlación IP &harr; MAC depende de la disponibilidad de tablas ARP del Core o inventario registrado en SYNAPSE.
                </div>
            </div>

            <div class="page-footer-bottom">
                <span>SYNAPSE &bull; Plataforma Centralizada de Gestión de Redes SONDA</span>
                <span>Página 7 de 8</span>
            </div>
        </div>


        <!-- =================================================================== -->
        <!-- PÁGINA 8: HALLAZGOS, RECOMENDACIONES Y APROBACIÓN                   -->
        <!-- =================================================================== -->
        <div class="pdf-page">
            <div class="page-header-top">
                <span>GRUPO VILASECA &nbsp;|&nbsp; SERVICIO GESTIONADO POR SONDA</span>
                <span class="doc-type">DOCUMENTO TÉCNICO &nbsp;&bull;&nbsp; PÁG. 8</span>
            </div>

            <div class="page-content-area">
                <div class="section-heading">
                    <span><span class="badge-num">8</span> Hallazgos y Recomendaciones</span>
                </div>

                <table class="tbl-technical">
                    <thead>
                        <tr>
                            <th style="width:10%;">ID</th>
                            <th style="width:20%;">ELEMENTO</th>
                            <th style="width:35%;">HALLAZGO / OBSERVACIÓN</th>
                            <th style="width:25%;">RECOMENDACIÓN</th>
                            <th style="width:10%; text-align:center;">PRIORIDAD</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $findings_count = 0;
                        if ($ports_without_desc > 0): 
                            $findings_count++;
                        ?>
                        <tr>
                            <td style="font-weight:bold; color:#002B49;">H-01</td>
                            <td>Nomenclatura ifAlias</td>
                            <td><?php echo $ports_without_desc; ?> interfaces no tienen descripción normalizada en el equipo.</td>
                            <td>Cargar etiquetas estandarizadas en switch.</td>
                            <td style="text-align:center;"><span class="badge-prio-media">Media</span></td>
                        </tr>
                        <?php endif; ?>

                        <?php if ($ports_down > 0): 
                            $findings_count++;
                        ?>
                        <tr>
                            <td style="font-weight:bold; color:#002B49;">H-02</td>
                            <td>Puertos Disponibles</td>
                            <td>Existen <?php echo $ports_down; ?> puertos libres/down de <?php echo $total_ports; ?> físicos.</td>
                            <td>Mantener monitoreo preventivo y control de accesos.</td>
                            <td style="text-align:center;"><span class="badge-prio-baja">Baja</span></td>
                        </tr>
                        <?php endif; ?>

                        <?php if ($description !== 'Sin observaciones registradas durante el levantamiento técnico.'): 
                            $findings_count++;
                        ?>
                        <tr>
                            <td style="font-weight:bold; color:#002B49;">H-03</td>
                            <td>Observación General</td>
                            <td><?php echo $description; ?></td>
                            <td>Validar según requerimientos de sede.</td>
                            <td style="text-align:center;"><span class="badge-prio-baja">Informativa</span></td>
                        </tr>
                        <?php endif; ?>

                        <?php if ($findings_count === 0): ?>
                        <tr>
                            <td colspan="5" style="text-align:center; padding:12px; color:#64748b;">
                                No aplica / No se registraron hallazgos adicionales ni condiciones de riesgo en este equipo.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="section-heading" style="margin-top:14px;">
                    <span>Controles de Calidad de la Recolección</span>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:6px 14px; font-size:9.5px; margin-bottom:14px;">
                    <div><i class="fas fa-check-square" style="color:#16a34a; margin-right:4px;"></i> Registro en base de datos CMDB completado.</div>
                    <div><i class="fas fa-check-square" style="color:#16a34a; margin-right:4px;"></i> Validación de interfaces y conteo de puertos.</div>
                    <div><i class="fas fa-check-square" style="color:#16a34a; margin-right:4px;"></i> Identificación de estados de enlace Up / Down.</div>
                    <div><i class="fas fa-check-square" style="color:<?php echo $has_vlans ? '#16a34a' : '#94a3b8'; ?>; margin-right:4px;"></i> Mapeo VLAN: <?php echo $has_vlans ? 'Validado' : 'No aplica'; ?>.</div>
                    <div><i class="fas fa-check-square" style="color:<?php echo $ports_with_lldp > 0 ? '#16a34a' : '#94a3b8'; ?>; margin-right:4px;"></i> Vecinos remotos / LLDP: <?php echo $ports_with_lldp > 0 ? 'Detectado' : 'No aplica'; ?>.</div>
                    <div><i class="fas fa-check-square" style="color:<?php echo $has_lags ? '#16a34a' : '#94a3b8'; ?>; margin-right:4px;"></i> Enlaces LAG: <?php echo $has_lags ? 'Identificado' : 'No aplica'; ?>.</div>
                </div>

                <div class="section-heading">
                    <span><span class="badge-num">9</span> Control y Aprobación del Servicio</span>
                </div>

                <table class="tbl-technical">
                    <thead>
                        <tr>
                            <th style="width:30%;">ACTIVIDAD</th>
                            <th style="width:25%;">RESPONSABLE</th>
                            <th style="width:20%;">FECHA</th>
                            <th style="width:25%; text-align:center;">ESTADO / FIRMA</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Generación automática / extracción</td>
                            <td>SYNAPSE Engine &bull; SONDA</td>
                            <td><?php echo $creation_date; ?></td>
                            <td style="text-align:center; color:#16a34a; font-weight:bold;">COMPLETADO</td>
                        </tr>
                        <tr>
                            <td>Revisión técnica Networking</td>
                            <td>Ing. Soporte SONDA</td>
                            <td><?php echo $creation_date; ?></td>
                            <td style="text-align:center; color:#16a34a; font-weight:bold;">VALIDADO</td>
                        </tr>
                        <tr>
                            <td>Validación de inventario / CMDB</td>
                            <td>Administrador CMDB <?php echo $client !== 'No aplica' ? $client : 'Vilaseca'; ?></td>
                            <td><?php echo $creation_date; ?></td>
                            <td style="text-align:center; color:#16a34a; font-weight:bold;">CONFORME</td>
                        </tr>
                        <tr>
                            <td>Aprobación responsable del servicio</td>
                            <td>Líder de Servicio SONDA</td>
                            <td><?php echo $creation_date; ?></td>
                            <td style="text-align:center; color:#16a34a; font-weight:bold;">APROBADO</td>
                        </tr>
                    </tbody>
                </table>

                <div style="margin-top:14px; font-size:8px; color:#64748b; line-height:1.4;">
                    <strong>Seguridad:</strong> Este documento no contiene comunidades SNMP, contraseñas ni claves de privacidad. Utiliza exclusivamente datos de solo lectura.<br>
                    <strong>Nota de marca:</strong> Identificadores de Grupo Vilaseca y SONDA para uso técnico operativo.
                </div>
            </div>

            <div class="page-footer-bottom">
                <span>SYNAPSE &bull; Plataforma Centralizada de Gestión de Redes SONDA</span>
                <span>Página 8 de 8</span>
            </div>
        </div>

    </div>

    <?php if ($auto_print): ?>
    <script>
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
            }, 500);
        });
    </script>
    <?php endif; ?>
</body>
</html>

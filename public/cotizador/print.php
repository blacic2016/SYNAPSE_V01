<?php
/**
 * Ficha de Cotización Impresion / PDF - CMDB VILASECA
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/permissions_helper.php';
require_once __DIR__ . '/../../src/helpers.php';

require_login();
if (!has_module_access('cotizador')) {
    header("Location: dashboard.php");
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    die("ID de cotización no válido.");
}

$pdo = getPDO();

// Fetch quote main record
$stmt = $pdo->prepare("SELECT * FROM cotizador_cotizaciones WHERE id = ?");
$stmt->execute([$id]);
$quote = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quote) {
    die("La cotización solicitada no existe.");
}

// Fetch quote details
$stmt = $pdo->prepare("SELECT * FROM cotizador_cotizaciones_detalles WHERE cotizacion_id = ? ORDER BY id ASC");
$stmt->execute([$id]);
$details = $stmt->fetchAll(PDO::FETCH_ASSOC);

$adicionales = json_decode($quote['adicionales_json'], true);
if (!$adicionales) {
    $adicionales = [];
}

// Separate details by section
$sections = [
    'Implementacion' => [],
    'MantPrev' => [],
    'MantCorr' => [],
    'BolsaHoras' => []
];

foreach ($details as $d) {
    $sections[$d['seccion']][] = $d;
}

// Help format money
function formatMoney($val) {
    return '$' . number_format(floatval($val), 2, '.', ',');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cotización - <?php echo htmlspecialchars($quote['cliente']); ?> - <?php echo htmlspecialchars($quote['contrato']); ?></title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            color: #333;
            background-color: #f8f9fa;
            margin: 0;
            padding: 20px;
            font-size: 13px;
            line-height: 1.5;
        }
        
        .no-print-bar {
            background-color: #101b31;
            color: white;
            padding: 15px 30px;
            margin-bottom: 30px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .btn-print {
            background-color: #ff5c05;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }

        .btn-print:hover {
            background-color: #e04e00;
        }

        .btn-back {
            color: white;
            text-decoration: none;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
        }

        .btn-back i {
            margin-right: 5px;
        }

        .quote-sheet {
            background-color: white;
            max-width: 900px;
            margin: 0 auto;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .header-table td {
            vertical-align: top;
        }

        .logo-area {
            font-size: 24px;
            font-weight: 800;
            color: #101b31;
            letter-spacing: -1px;
        }

        .logo-area span {
            color: #ff5c05;
        }

        .quote-meta-title {
            font-size: 18px;
            font-weight: 700;
            color: #ff5c05;
            text-align: right;
            text-transform: uppercase;
            margin: 0 0 5px 0;
        }

        .quote-meta-text {
            text-align: right;
            font-size: 12px;
            color: #6c757d;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            border-top: 2px solid #dee2e6;
            border-bottom: 2px solid #dee2e6;
            padding: 15px 0;
            margin-bottom: 35px;
        }

        .info-item {
            font-size: 13px;
        }

        .info-item strong {
            color: #101b31;
            display: inline-block;
            width: 130px;
        }

        .section-title {
            font-size: 14px;
            font-weight: 700;
            color: #101b31;
            border-bottom: 2px solid #101b31;
            padding-bottom: 5px;
            margin-top: 30px;
            margin-bottom: 12px;
            text-transform: uppercase;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .data-table th {
            background-color: #f1f3f5;
            color: #495057;
            text-transform: uppercase;
            font-size: 10px;
            font-weight: 700;
            padding: 8px 10px;
            border-bottom: 1px solid #dee2e6;
            text-align: left;
        }

        .data-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e9ecef;
            font-size: 12px;
            vertical-align: top;
        }

        .data-table .text-right {
            text-align: right;
        }

        .data-table .text-center {
            text-align: center;
        }

        .subtotal-row td {
            font-weight: bold;
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
            border-bottom: 2px solid #dee2e6;
        }

        .totals-table-wrapper {
            display: flex;
            justify-content: flex-end;
            margin-top: 30px;
        }

        .totals-table {
            width: 350px;
            border-collapse: collapse;
        }

        .totals-table td {
            padding: 10px;
            font-size: 13px;
            border-bottom: 1px solid #e9ecef;
        }

        .totals-table tr.grand-total {
            font-size: 16px;
            font-weight: 700;
            color: #ff5c05;
            border-top: 2px solid #ff5c05;
            border-bottom: 2px solid #ff5c05;
            background-color: rgba(255, 92, 5, 0.05);
        }

        .observaciones-box {
            background-color: #f8f9fa;
            border-left: 4px solid #101b31;
            padding: 15px;
            margin-top: 30px;
            border-radius: 4px;
        }

        .observaciones-title {
            font-weight: 700;
            margin-bottom: 5px;
            color: #101b31;
        }

        .signature-section {
            margin-top: 60px;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 50px;
            text-align: center;
        }

        .signature-box {
            border-top: 1px solid #adb5bd;
            padding-top: 10px;
            margin-top: 50px;
            font-size: 12px;
        }

        @media print {
            body {
                background-color: white;
                padding: 0;
            }
            .no-print-bar {
                display: none;
            }
            .quote-sheet {
                box-shadow: none;
                border: none;
                padding: 0;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>

    <!-- Top bar for print action and navigation -->
    <div class="no-print-bar">
        <a href="index.php" class="btn-back"><i class="fas fa-chevron-left"></i> Volver al Cotizador</a>
        <div>
            <span class="mr-3 text-white"><i class="fas fa-info-circle mr-1"></i> Use el botón para guardar como PDF o imprimir físicamente.</span>
            <button class="btn-print" onclick="window.print()"><i class="fas fa-print mr-2"></i> Imprimir / Descargar PDF</button>
        </div>
    </div>

    <!-- The actual Printable Quotation Sheet -->
    <div class="quote-sheet">
        <table class="header-table">
            <tr>
                <td>
                    <div class="logo-area">SYNAPSE</div>
                    <div style="font-size:11px; color:#6c757d; margin-top:5px;">
                        Vilaseca S.A. | Servicios Cloud, Soporte & Conectividad<br>
                        Guayaquil - Quito, Ecuador
                    </div>
                </td>
                <td>
                    <h2 class="quote-meta-title">Resumen de Cotización</h2>
                    <div class="quote-meta-text">
                        <strong>Referencia:</strong> COT-<?php echo str_pad($quote['id'], 6, '0', STR_PAD_LEFT); ?><br>
                        <strong>Versión:</strong> v<?php echo htmlspecialchars($quote['version']); ?><br>
                        <strong>Estado:</strong> <?php echo $quote['estado'] === 'Enviada' ? 'Enviada / Aprobada' : 'Borrador'; ?><br>
                        <strong>Fecha Emisión:</strong> <?php echo date('d/m/Y', strtotime($quote['fecha'])); ?>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Main client and contract information -->
        <div class="info-grid">
            <div class="info-item">
                <div><strong>Cliente:</strong> <?php echo htmlspecialchars($quote['cliente']); ?></div>
                <div><strong>Proyecto / Contrato:</strong> <?php echo htmlspecialchars($quote['contrato']); ?></div>
            </div>
            <div class="info-item">
                <div><strong>Margen Global (PVP):</strong> <?php echo round($quote['margen_global'] * 100); ?>%</div>
                <div><strong>Riesgo Técnico:</strong> <?php echo round($quote['risk_percentage'] * 100); ?>% horas base</div>
            </div>
        </div>

        <!-- 1. IMPLEMENTACIÓN SECTION DETAILS -->
        <?php if (!empty($sections['Implementacion'])): ?>
            <div class="section-title">1. Detalles de Implementación y Migración</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Marca/Categoria</th>
                        <th>Actividad (Grupo)</th>
                        <th>Detalle de la Tarea</th>
                        <th class="text-center">Esp</th>
                        <th class="text-center">H. Lab</th>
                        <th class="text-center">H. 50%</th>
                        <th class="text-center">H. 100%</th>
                        <th class="text-right">Precio PVP Unit</th>
                        <th class="text-right">Precio PVP Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $subH_lab = 0; $subH_50 = 0; $subH_100 = 0; $subPvp = 0;
                    foreach ($sections['Implementacion'] as $d): 
                        $subH_lab += $d['horas_laborables'];
                        $subH_50 += $d['horas_no_laborables_50'];
                        $subH_100 += $d['horas_no_laborables_100'];
                        $subPvp += $d['pvp_total'];
                    ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($d['marca_categoria']); ?></strong></td>
                            <td><?php echo htmlspecialchars($d['actividad']); ?></td>
                            <td><?php echo htmlspecialchars($d['detalle']); ?></td>
                            <td class="text-center"><?php echo $d['especialista_nivel']; ?></td>
                            <td class="text-center"><?php echo number_format($d['horas_laborables'], 1); ?></td>
                            <td class="text-center"><?php echo number_format($d['horas_no_laborables_50'], 1); ?></td>
                            <td class="text-center"><?php echo number_format($d['horas_no_laborables_100'], 1); ?></td>
                            <td class="text-right"><?php echo formatMoney($d['pvp_hora']); ?></td>
                            <td class="text-right font-weight-bold"><?php echo formatMoney($d['pvp_total']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="subtotal-row">
                        <td colspan="4">Subtotal Horas de Servicios</td>
                        <td class="text-center"><?php echo number_format($subH_lab, 1); ?></td>
                        <td class="text-center"><?php echo number_format($subH_50, 1); ?></td>
                        <td class="text-center"><?php echo number_format($subH_100, 1); ?></td>
                        <td colspan="1"></td>
                        <td class="text-right"><?php echo formatMoney($subPvp); ?></td>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- 2. MANTENIMIENTO PREVENTIVO SECTION DETAILS -->
        <?php if (!empty($sections['MantPrev'])): ?>
            <div class="section-title">2. Mantenimiento Preventivo Periódico</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Marca/Categoria</th>
                        <th>Actividad (Grupo)</th>
                        <th>Detalle de la Tarea</th>
                        <th class="text-center">Esp</th>
                        <th class="text-center">H. Lab</th>
                        <th class="text-center">H. 50%</th>
                        <th class="text-center">H. 100%</th>
                        <th class="text-right">Precio PVP Unit</th>
                        <th class="text-right">Precio PVP Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $subH_lab = 0; $subH_50 = 0; $subH_100 = 0; $subPvp = 0;
                    foreach ($sections['MantPrev'] as $d): 
                        $subH_lab += $d['horas_laborables'];
                        $subH_50 += $d['horas_no_laborables_50'];
                        $subH_100 += $d['horas_no_laborables_100'];
                        $subPvp += $d['pvp_total'];
                    ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($d['marca_categoria']); ?></strong></td>
                            <td><?php echo htmlspecialchars($d['actividad']); ?></td>
                            <td><?php echo htmlspecialchars($d['detalle']); ?></td>
                            <td class="text-center"><?php echo $d['especialista_nivel']; ?></td>
                            <td class="text-center"><?php echo number_format($d['horas_laborables'], 1); ?></td>
                            <td class="text-center"><?php echo number_format($d['horas_no_laborables_50'], 1); ?></td>
                            <td class="text-center"><?php echo number_format($d['horas_no_laborables_100'], 1); ?></td>
                            <td class="text-right"><?php echo formatMoney($d['pvp_hora']); ?></td>
                            <td class="text-right font-weight-bold"><?php echo formatMoney($d['pvp_total']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="subtotal-row">
                        <td colspan="4">Subtotal Horas de Preventivos</td>
                        <td class="text-center"><?php echo number_format($subH_lab, 1); ?></td>
                        <td class="text-center"><?php echo number_format($subH_50, 1); ?></td>
                        <td class="text-center"><?php echo number_format($subH_100, 1); ?></td>
                        <td colspan="1"></td>
                        <td class="text-right"><?php echo formatMoney($subPvp); ?></td>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- 3. MANTENIMIENTO CORRECTIVO SECTION DETAILS -->
        <?php if ($adicionales && isset($adicionales['corr_method']) && $adicionales['corr_method'] === 'cases'): ?>
            <div class="section-title">3. Mantenimiento Correctivo (Cálculo por Casos de Atención)</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Descripción Métrica</th>
                        <th class="text-center">Equipos</th>
                        <th class="text-center">% Daño</th>
                        <th class="text-center">Casos Est.</th>
                        <th class="text-center">Años Contrato</th>
                        <th class="text-center">Horas/Caso</th>
                        <th class="text-center">Nivel Esp.</th>
                        <th class="text-right">Precio PVP Estimado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $equipos = intval($adicionales['corr_case_equipos']);
                    $dmg = floatval($adicionales['corr_case_dmg_pct']) * 100;
                    $casos = ceil($equipos * floatval($adicionales['corr_case_dmg_pct']));
                    $years = intval($adicionales['corr_case_years']);
                    $hrs = floatval($adicionales['corr_case_hours_per_case']);
                    
                    // Fetch intermediate rate to calculate the exact case PVP
                    $sp_lvl = $adicionales['corr_case_level'];
                    
                    // Standard cost values from database
                    $stmt = $pdo->prepare("SELECT costo_hora_lab FROM cotizador_specialists WHERE tipo = ? LIMIT 1");
                    $stmt->execute([$sp_lvl]);
                    $rate_row = $stmt->fetch(PDO::FETCH_ASSOC);
                    $rate_cost = $rate_row ? floatval($rate_row['costo_hora_lab']) : 25;
                    $rate_pvp = $rate_cost / (1 - floatval($quote['margen_global']));
                    
                    $total_case_pvp = $casos * $years * $hrs * $rate_pvp;
                    $total_mov_pvp = $casos * $years * floatval($adicionales['corr_case_mov_pvp']);
                    $grand_corr_pvp = $total_case_pvp + $total_mov_pvp;
                    ?>
                    <tr>
                        <td>Soporte Correctivo Técnico (Incidencias)</td>
                        <td class="text-center"><?php echo $equipos; ?></td>
                        <td class="text-center"><?php echo number_format($dmg, 1); ?>%</td>
                        <td class="text-center"><?php echo $casos; ?></td>
                        <td class="text-center"><?php echo $years; ?></td>
                        <td class="text-center"><?php echo number_format($hrs, 1); ?></td>
                        <td class="text-center"><?php echo $sp_lvl; ?></td>
                        <td class="text-right"><?php echo formatMoney($total_case_pvp); ?></td>
                    </tr>
                    <tr>
                        <td>Viáticos y Movilización por Evento</td>
                        <td colspan="5">Tarifa unitaria de movilización: <?php echo formatMoney($adicionales['corr_case_mov_pvp']); ?></td>
                        <td class="text-center"><?php echo $casos * $years; ?> viajes</td>
                        <td class="text-right"><?php echo formatMoney($total_mov_pvp); ?></td>
                    </tr>
                    <tr class="subtotal-row">
                        <td colspan="7">Subtotal Mantenimiento Correctivo</td>
                        <td class="text-right"><?php echo formatMoney($grand_corr_pvp); ?></td>
                    </tr>
                </tbody>
            </table>
        <?php elseif (!empty($sections['MantCorr'])): ?>
            <div class="section-title">3. Mantenimiento Correctivo (Cálculo por Horas del Pool)</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Marca/Categoria</th>
                        <th>Actividad (Grupo)</th>
                        <th>Detalle de la Tarea</th>
                        <th class="text-center">Esp</th>
                        <th class="text-center">H. Lab</th>
                        <th class="text-center">H. 50%</th>
                        <th class="text-center">H. 100%</th>
                        <th class="text-right">Precio PVP Unit</th>
                        <th class="text-right">Precio PVP Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $subH_lab = 0; $subH_50 = 0; $subH_100 = 0; $subPvp = 0;
                    foreach ($sections['MantCorr'] as $d): 
                        $subH_lab += $d['horas_laborables'];
                        $subH_50 += $d['horas_no_laborables_50'];
                        $subH_100 += $d['horas_no_laborables_100'];
                        $subPvp += $d['pvp_total'];
                    ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($d['marca_categoria']); ?></strong></td>
                            <td><?php echo htmlspecialchars($d['actividad']); ?></td>
                            <td><?php echo htmlspecialchars($d['detalle']); ?></td>
                            <td class="text-center"><?php echo $d['especialista_nivel']; ?></td>
                            <td class="text-center"><?php echo number_format($d['horas_laborables'], 1); ?></td>
                            <td class="text-center"><?php echo number_format($d['horas_no_laborables_50'], 1); ?></td>
                            <td class="text-center"><?php echo number_format($d['horas_no_laborables_100'], 1); ?></td>
                            <td class="text-right"><?php echo formatMoney($d['pvp_hora']); ?></td>
                            <td class="text-right font-weight-bold"><?php echo formatMoney($d['pvp_total']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="subtotal-row">
                        <td colspan="4">Subtotal Horas de Correctivos</td>
                        <td class="text-center"><?php echo number_format($subH_lab, 1); ?></td>
                        <td class="text-center"><?php echo number_format($subH_50, 1); ?></td>
                        <td class="text-center"><?php echo number_format($subH_100, 1); ?></td>
                        <td colspan="1"></td>
                        <td class="text-right"><?php echo formatMoney($subPvp); ?></td>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- 4. BOLSA DE HORAS SECTION DETAILS -->
        <?php if (!empty($sections['BolsaHoras'])): ?>
            <div class="section-title">4. Bolsa de Horas de Soporte</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Marca/Categoria</th>
                        <th>Actividad (Grupo)</th>
                        <th>Detalle de la Tarea</th>
                        <th class="text-center">Esp</th>
                        <th class="text-center">H. Lab</th>
                        <th class="text-center">H. 50%</th>
                        <th class="text-center">H. 100%</th>
                        <th class="text-right">Precio PVP Unit</th>
                        <th class="text-right">Precio PVP Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $subH_lab = 0; $subH_50 = 0; $subH_100 = 0; $subPvp = 0;
                    foreach ($sections['BolsaHoras'] as $d): 
                        $subH_lab += $d['horas_laborables'];
                        $subH_50 += $d['horas_no_laborables_50'];
                        $subH_100 += $d['horas_no_laborables_100'];
                        $subPvp += $d['pvp_total'];
                    ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($d['marca_categoria']); ?></strong></td>
                            <td><?php echo htmlspecialchars($d['actividad']); ?></td>
                            <td><?php echo htmlspecialchars($d['detalle']); ?></td>
                            <td class="text-center"><?php echo $d['especialista_nivel']; ?></td>
                            <td class="text-center"><?php echo number_format($d['horas_laborables'], 1); ?></td>
                            <td class="text-center"><?php echo number_format($d['horas_no_laborables_50'], 1); ?></td>
                            <td class="text-center"><?php echo number_format($d['horas_no_laborables_100'], 1); ?></td>
                            <td class="text-right"><?php echo formatMoney($d['pvp_hora']); ?></td>
                            <td class="text-right font-weight-bold"><?php echo formatMoney($d['pvp_total']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="subtotal-row">
                        <td colspan="4">Subtotal Horas de Bolsa</td>
                        <td class="text-center"><?php echo number_format($subH_lab, 1); ?></td>
                        <td class="text-center"><?php echo number_format($subH_50, 1); ?></td>
                        <td class="text-center"><?php echo number_format($subH_100, 1); ?></td>
                        <td colspan="1"></td>
                        <td class="text-right"><?php echo formatMoney($subPvp); ?></td>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- 5. ADDITIONAL COSTS DETAILS -->
        <?php if ($adicionales): ?>
            <div class="section-title">5. Gastos Adicionales y Costos Indirectos</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Descripción Gasto</th>
                        <th>Sección</th>
                        <th>Detalle de Cantidad / Factor</th>
                        <th class="text-right">Precio PVP</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Implementation Additions -->
                    <?php if (floatval($adicionales['impl_kt_hours']) > 0): 
                        // Fetch KT pvp
                        $kt_lvl = $adicionales['impl_kt_level'];
                        $stmt = $pdo->prepare("SELECT costo_hora_lab FROM cotizador_specialists WHERE tipo = ? LIMIT 1");
                        $stmt->execute([$kt_lvl]);
                        $r_row = $stmt->fetch(PDO::FETCH_ASSOC);
                        $r_cost = $r_row ? floatval($r_row['costo_hora_lab']) : 25;
                        $r_pvp = $r_cost / (1 - floatval($quote['margen_global']));
                    ?>
                        <tr>
                            <td>Transferencia de Conocimiento (KT)</td>
                            <td>Implementación</td>
                            <td><?php echo number_format($adicionales['impl_kt_hours'], 1); ?> horas con Especialista <?php echo $kt_lvl; ?></td>
                            <td class="text-right"><?php echo formatMoney($adicionales['impl_kt_hours'] * $r_pvp); ?></td>
                        </tr>
                    <?php endif; ?>
                    
                    <?php if (intval($adicionales['impl_travel_nights']) > 0 || intval($adicionales['impl_flights_qty']) > 0): ?>
                        <tr>
                            <td>Movilización y Viáticos (Provincias)</td>
                            <td>Implementación</td>
                            <td>
                                <?php echo intval($adicionales['impl_travel_nights']); ?> noches (<?php echo formatMoney($adicionales['impl_travel_cost_night']); ?>/noche) +
                                <?php echo intval($adicionales['impl_flights_qty']); ?> vuelos nacionales (<?php echo formatMoney($adicionales['impl_flight_cost']); ?>/vuelo)
                            </td>
                            <td class="text-right">
                                <?php 
                                $t_cost = (intval($adicionales['impl_travel_nights']) * floatval($adicionales['impl_travel_cost_night'])) + 
                                          (intval($adicionales['impl_flights_qty']) * floatval($adicionales['impl_flight_cost']));
                                echo formatMoney($t_cost);
                                ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                    
                    <?php if (floatval($adicionales['impl_pss_val']) > 0): ?>
                        <tr>
                            <td>Soporte PSS Fabricante (Directo)</td>
                            <td>Implementación</td>
                            <td>PSS del Fabricante</td>
                            <td class="text-right"><?php echo formatMoney($adicionales['impl_pss_val']); ?></td>
                        </tr>
                    <?php endif; ?>

                    <?php if (floatval($adicionales['impl_ext_prov_pvp']) > 0): ?>
                        <tr>
                            <td>Apoyo de Proveedor Externo</td>
                            <td>Implementación</td>
                            <td>PVP del proveedor externo</td>
                            <td class="text-right"><?php echo formatMoney($adicionales['impl_ext_prov_pvp']); ?></td>
                        </tr>
                    <?php endif; ?>

                    <?php if (intval($adicionales['impl_boc_months']) > 0): 
                        // BOC PVP
                        $boc_lvl = $adicionales['impl_boc_level'];
                        $stmt = $pdo->prepare("SELECT costo_hora_lab FROM cotizador_specialists WHERE tipo = ? LIMIT 1");
                        $stmt->execute([$boc_lvl]);
                        $r_row = $stmt->fetch(PDO::FETCH_ASSOC);
                        $r_cost = $r_row ? floatval($r_row['costo_hora_lab']) : 25;
                        $r_pvp = $r_cost / (1 - floatval($quote['margen_global']));
                    ?>
                        <tr>
                            <td>Gestión BOC Post-Implementación</td>
                            <td>Implementación</td>
                            <td><?php echo intval($adicionales['impl_boc_months']); ?> meses x <?php echo floatval($adicionales['impl_boc_hours']); ?> h/mes (Nivel <?php echo $boc_lvl; ?>)</td>
                            <td class="text-right"><?php echo formatMoney(intval($adicionales['impl_boc_months']) * floatval($adicionales['impl_boc_hours']) * $r_pvp); ?></td>
                        </tr>
                    <?php endif; ?>

                    <?php if (intval($adicionales['impl_pm_months']) > 0): 
                        // PM PVP
                        $pm_lvl = $adicionales['impl_pm_level'];
                        $stmt = $pdo->prepare("SELECT costo_hora_lab FROM cotizador_specialists WHERE tipo = ? LIMIT 1");
                        $stmt->execute([$pm_lvl]);
                        $r_row = $stmt->fetch(PDO::FETCH_ASSOC);
                        $r_cost = $r_row ? floatval($r_row['costo_hora_lab']) : 25;
                        $r_pvp = $r_cost / (1 - floatval($quote['margen_global']));
                    ?>
                        <tr>
                            <td>Elaboración Mensual Informes PM</td>
                            <td>Implementación</td>
                            <td><?php echo intval($adicionales['impl_pm_months']); ?> meses x <?php echo floatval($adicionales['impl_pm_hours']); ?> h/mes (Nivel <?php echo $pm_lvl; ?>)</td>
                            <td class="text-right"><?php echo formatMoney(intval($adicionales['impl_pm_months']) * floatval($adicionales['impl_pm_hours']) * $r_pvp); ?></td>
                        </tr>
                    <?php endif; ?>

                    <?php 
                    $impl_cons = floatval($adicionales['impl_consumables_screws']) + floatval($adicionales['impl_consumables_labels']) + floatval($adicionales['impl_consumables_vaccines']) + floatval($adicionales['impl_consumables_epp']);
                    if ($impl_cons > 0):
                    ?>
                        <tr>
                            <td>Consumibles y EPPs (Tornillos, etiquetas, vacunas, equipos de prot. personal)</td>
                            <td>Implementación</td>
                            <td>Gastos varios de consumibles</td>
                            <td class="text-right"><?php echo formatMoney($impl_cons); ?></td>
                        </tr>
                    <?php endif; ?>

                    <!-- Preventive Additions -->
                    <?php if (intval($adicionales['prev_travel_nights']) > 0 || intval($adicionales['prev_flights_qty']) > 0 || floatval($adicionales['prev_materials_cost']) > 0 || floatval($adicionales['prev_pss_cost']) > 0): ?>
                        <tr>
                            <td>Viáticos y Materiales Mantenimiento Preventivo</td>
                            <td>Mantenimiento Prev.</td>
                            <td>
                                <?php echo intval($adicionales['prev_travel_nights']); ?> noches viaje +
                                <?php echo intval($adicionales['prev_flights_qty']); ?> vuelos +
                                Materiales (<?php echo formatMoney($adicionales['prev_materials_cost']); ?>) +
                                Soporte PSS (<?php echo formatMoney($adicionales['prev_pss_cost']); ?>)
                            </td>
                            <td class="text-right">
                                <?php 
                                $prev_tot_ads = (intval($adicionales['prev_travel_nights']) * floatval($adicionales['impl_travel_cost_night'])) +
                                                (intval($adicionales['prev_flights_qty']) * floatval($adicionales['impl_flight_cost'])) +
                                                floatval($adicionales['prev_materials_cost']) +
                                                floatval($adicionales['prev_pss_cost']);
                                echo formatMoney($prev_tot_ads);
                                ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- SUMMARY OF SECTIONS -->
        <div class="section-title">Resumen de Totales Generales</div>
        <div class="totals-table-wrapper">
            <table class="totals-table">
                <tr>
                    <td><strong>Costo Total del Proyecto:</strong></td>
                    <td class="text-right"><?php echo formatMoney($quote['total_costo']); ?></td>
                </tr>
                <tr>
                    <td><strong>Margen Neto Promedio:</strong></td>
                    <td class="text-right"><?php 
                        $rent = floatval($quote['total_precio']) - floatval($quote['total_costo']);
                        $marg_real = floatval($quote['total_precio']) > 0 ? ($rent / floatval($quote['total_precio'])) * 100 : 0;
                        echo round($marg_real); 
                    ?>%</td>
                </tr>
                <tr>
                    <td><strong>Rentabilidad Estimada:</strong></td>
                    <td class="text-right"><?php echo formatMoney($rent); ?></td>
                </tr>
                <tr class="grand-total">
                    <td><strong>PRECIO DE VENTA (PVP):</strong></td>
                    <td class="text-right"><?php echo formatMoney($quote['total_precio']); ?></td>
                </tr>
            </table>
        </div>

        <!-- OBSERVACIONES -->
        <?php if (!empty($quote['observaciones'])): ?>
            <div class="observaciones-box">
                <div class="observaciones-title">Observaciones / Comentarios Internos</div>
                <div style="font-style: italic; color: #495057;">
                    <?php echo nl2br(htmlspecialchars($quote['observaciones'])); ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- SIGNATURES AND APPROVALS -->
        <div class="signature-section">
            <div>
                <div class="signature-box">
                    <strong>Cotizado Por:</strong><br>
                    Vilaseca S.A. Preventa TI<br>
                    Ecuador
                </div>
            </div>
            <div>
                <div class="signature-box">
                    <strong>Aprobado Por (Firma Digital):</strong><br>
                    <?php if (!empty($quote['aprobado_por'])): ?>
                        <span style="color:#28a745; font-weight:700; text-transform:uppercase;"><i class="fas fa-check-circle mr-1"></i> APROBADO</span><br>
                        <strong><?php echo htmlspecialchars($quote['aprobado_por']); ?></strong><br>
                        Fecha: <?php echo date('d/m/Y H:i:s', strtotime($quote['aprobado_fecha'])); ?>
                    <?php else: ?>
                        <span style="color:#dc3545; font-weight:700;"><i class="fas fa-clock mr-1"></i> PENDIENTE DE APROBACIÓN</span><br>
                        Jefe de Pre-venta / Director TI
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</body>
</html>

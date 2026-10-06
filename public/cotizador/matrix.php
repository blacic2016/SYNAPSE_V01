<?php
/**
 * Execution & Implementation Matrix (Direct Costs) - CMDB VILASECA
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/permissions_helper.php';
require_once __DIR__ . '/../../src/helpers.php';

require_login();
if (!has_module_access('cotizador')) {
    header("Location: ../dashboard.php");
    exit();
}

$pdo = getPDO();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die("ID de cotización inválido.");
}

// Fetch quote
$stmt = $pdo->prepare("SELECT * FROM cotizador_cotizaciones WHERE id = ?");
$stmt->execute([$id]);
$quote = $stmt->fetch();
if (!$quote) {
    die("Cotización no encontrada.");
}

// Fetch details
$stmt_det = $pdo->prepare("SELECT * FROM cotizador_cotizaciones_detalles WHERE cotizacion_id = ? ORDER BY seccion ASC, id ASC");
$stmt_det->execute([$id]);
$details = $stmt_det->fetchAll();

// Fetch specialists
$specialists = $pdo->query("SELECT * FROM cotizador_specialists")->fetchAll(PDO::FETCH_ASSOC);
$specialists_by_type = [];
foreach ($specialists as $sp) {
    $specialists_by_type[$sp['tipo']] = $sp;
}

// Decode JSON config
$adicionales = json_decode($quote['adicionales_json'] ?? '{}', true);
if (!is_array($adicionales)) {
    $adicionales = [];
}

$page_title = "Matriz de Ejecución - " . htmlspecialchars($quote['cliente']);
$hide_content_header = true;
include '../partials/header.php';

// CALCULATE DETAILED DIRECT COSTS
$total_specialist_hours = 0;
$total_specialist_cost = 0;

foreach ($details as $item) {
    $total_specialist_hours += ($item['horas_laborables'] + $item['horas_no_laborables_50'] + $item['horas_no_laborables_100']);
    $total_specialist_cost += $item['costo_total'];
}

// Extras Direct Cost Calculation
$impl_travel_cost = ((int)($adicionales['impl_travel_nights'] ?? 0) * (float)($adicionales['impl_travel_cost_night'] ?? 25)) +
                   ((int)($adicionales['impl_flights_qty'] ?? 0) * (float)($adicionales['impl_flight_cost'] ?? 150));

$impl_pss_cost = (float)($adicionales['impl_pss_val'] ?? 0);
$impl_ext_prov_cost = (float)($adicionales['impl_ext_prov_cost'] ?? 0);

// BOC hours direct cost
$boc_cost = 0;
$boc_hours = 0;
$boc_sp = $specialists_by_type[$adicionales['impl_boc_level'] ?? 'BOC'] ?? null;
if ($boc_sp) {
    $boc_hours = (int)($adicionales['impl_boc_months'] ?? 0) * (float)($adicionales['impl_boc_hours'] ?? 0);
    $boc_cost = $boc_hours * (float)$boc_sp['costo_hora_lab'];
}

// PM hours direct cost
$pm_cost = 0;
$pm_hours = 0;
$pm_sp = $specialists_by_type[$adicionales['impl_pm_level'] ?? 'GP1'] ?? null;
if ($pm_sp) {
    $pm_hours = (int)($adicionales['impl_pm_months'] ?? 0) * (float)($adicionales['impl_pm_hours'] ?? 0);
    $pm_cost = $pm_hours * (float)$pm_sp['costo_hora_lab'];
}

// Consumables cost
$consumables_cost = (float)($adicionales['impl_consumables_screws'] ?? 0) +
                   (float)($adicionales['impl_consumables_labels'] ?? 0) +
                   (float)($adicionales['impl_consumables_vaccines'] ?? 0) +
                   (float)($adicionales['impl_consumables_epp'] ?? 0);

// Knowledge Transfer direct cost
$kt_cost = 0;
$kt_hours = 0;
if (($adicionales['impl_kt_incluye'] ?? 'No') === 'Si') {
    $kt_sp = $specialists_by_type[$adicionales['impl_kt_level'] ?? 'N3'] ?? null;
    if ($kt_sp) {
        $kt_hours = (float)($adicionales['impl_kt_hours'] ?? 0);
        $kt_cost = $kt_hours * (float)$kt_sp['costo_hora_lab'];
    }
    $kt_cost += (float)($adicionales['impl_breaks_cost'] ?? 0);
}

// Preventive Travel & Materials cost
$prev_travel_cost = ((int)($adicionales['prev_travel_nights'] ?? 0) * 25) + ((int)($adicionales['prev_flights_qty'] ?? 0) * 150);
$prev_materials_cost = (float)($adicionales['prev_materials_cost'] ?? 0);
$prev_pss_cost = (float)($adicionales['prev_pss_cost'] ?? 0);

// Corrective direct cost
$corr_travel_cost = 0;
$corr_materials_cost = 0;
$corr_pss_cost = 0;
$corr_cases_cost = 0;
$corr_cases_hours = 0;

$corr_method = $adicionales['corr_method'] ?? 'hours';
if ($corr_method === 'hours') {
    $corr_travel_cost = ((int)($adicionales['corr_travel_nights'] ?? 0) * 25) + ((int)($adicionales['corr_flights_qty'] ?? 0) * 150);
    $corr_materials_cost = (float)($adicionales['corr_materials_cost'] ?? 0);
    $corr_pss_cost = (float)($adicionales['corr_pss_cost'] ?? 0);
} else {
    // Cases method direct cost
    $dmg_equipos = (int)($adicionales['corr_case_equipos'] ?? 0);
    $dmg_pct = (float)($adicionales['corr_case_dmg_pct'] ?? 0.10);
    $years = (int)($adicionales['corr_case_years'] ?? 1);
    $cases_calc = ceil($dmg_equipos * $dmg_pct * $years);
    $hours_case = (float)($adicionales['corr_case_hours_per_case'] ?? 4);
    
    $case_sp = $specialists_by_type[$adicionales['corr_case_level'] ?? 'N2'] ?? null;
    if ($case_sp) {
        $corr_cases_hours = $cases_calc * $hours_case;
        $corr_cases_cost = $corr_cases_hours * (float)$case_sp['costo_hora_lab'];
    }
    $corr_cases_cost += $cases_calc * (float)($adicionales['corr_case_mov_cost'] ?? 0);
}

// Bolsa travel direct cost
$bolsa_travel_cost = ((int)($adicionales['bolsa_travel_extra'] ?? 0) * 15) + ((int)($adicionales['bolsa_flights_qty'] ?? 0) * 150);

// Direct project risk cost
$risk_cost = 0;
$impl_only_items_cost = 0;
foreach ($details as $item) {
    if ($item['seccion'] === 'Implementacion') {
        $impl_only_items_cost += $item['costo_total'];
    }
}
$risk_cost = $impl_only_items_cost * $quote['risk_percentage'];

// Total Direct Cost Sum
$direct_cost_specialists = $total_specialist_cost + $boc_cost + $pm_cost + $kt_cost + $corr_cases_cost;
$direct_cost_travel = $impl_travel_cost + $prev_travel_cost + $corr_travel_cost + $bolsa_travel_cost;
$direct_cost_materials_pss = $impl_pss_cost + $impl_ext_prov_cost + $consumables_cost + $prev_materials_cost + $prev_pss_cost + $corr_materials_cost + $corr_pss_cost;

$calculated_direct_total = $direct_cost_specialists + $direct_cost_travel + $direct_cost_materials_pss + $risk_cost;
?>

<style>
  .matrix-header-gradient {
    background: linear-gradient(135deg, #1d3557 0%, #457b9d 100%);
    color: white;
  }
  .table-matrix th {
    background-color: #1d3557 !important;
    color: white !important;
    font-size: 0.8rem;
    text-transform: uppercase;
    font-weight: 700;
  }
  .table-matrix td {
    font-size: 0.82rem;
    vertical-align: middle;
  }
  .kpi-card-matrix {
    border-left: 4px solid #1d3557;
    border-radius: 6px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.05);
  }
  .kpi-title {
    font-size: 0.72rem;
    text-transform: uppercase;
    color: #6c757d;
    font-weight: 700;
  }
  .kpi-value {
    font-size: 1.3rem;
    font-weight: 800;
    color: #1d3557;
  }
  .section-badge-matrix {
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 0.72rem;
    font-weight: bold;
  }
  .bg-impl { background-color: rgba(29, 53, 87, 0.1); color: #1d3557; }
  .bg-prev { background-color: rgba(40, 167, 69, 0.1); color: #28a745; }
  .bg-corr { background-color: rgba(220, 53, 69, 0.1); color: #dc3545; }
  .bg-bolsa { background-color: rgba(255, 193, 7, 0.15); color: #b28900; }
  
  @media print {
    .no-print { display: none !important; }
    body { background-color: white; color: black; }
    .card { box-shadow: none !important; border: 1px solid #ddd !important; }
  }
</style>

<div class="row mb-3 no-print">
  <div class="col-12 d-flex justify-content-between align-items-center">
    <div>
      <a href="view.php?id=<?= $quote['id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i> Volver al Detalle</a>
      <h3 class="m-0 d-inline-block align-middle ml-3 font-weight-bold text-dark">Matriz de Ejecución e Implementación</h3>
    </div>
    <div>
      <button class="btn btn-success btn-sm mr-2" onclick="exportMatrixToExcel()"><i class="fas fa-file-excel mr-1"></i> Exportar a Excel</button>
      <button class="btn btn-primary btn-sm" onclick="window.print()"><i class="fas fa-print mr-1"></i> Imprimir Matriz</button>
    </div>
  </div>
</div>

<!-- TOP KPI CARDS -->
<div class="row mb-4">
  <div class="col-md-3">
    <div class="card p-3 kpi-card-matrix" style="border-left-color: #457b9d;">
      <div class="kpi-title">Especialistas y Soporte</div>
      <div class="kpi-value">$<?= number_format($direct_cost_specialists, 2) ?></div>
      <span class="small text-muted"><?= number_format($total_specialist_hours + $boc_hours + $pm_hours + $kt_hours + $corr_cases_hours, 1) ?> horas estimadas</span>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card p-3 kpi-card-matrix" style="border-left-color: #28a745;">
      <div class="kpi-title">Viáticos y Movilización</div>
      <div class="kpi-value">$<?= number_format($direct_cost_travel, 2) ?></div>
      <span class="small text-muted">Hospedajes, vuelos y transporte</span>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card p-3 kpi-card-matrix" style="border-left-color: #e63946;">
      <div class="kpi-title">Materiales, PSS e Integraciones</div>
      <div class="kpi-value">$<?= number_format($direct_cost_materials_pss, 2) ?></div>
      <span class="small text-muted">Consumibles y soporte fabricante</span>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card p-3 kpi-card-matrix" style="border-left-color: #ff5c05; background-color: rgba(255, 92, 5, 0.02);">
      <div class="kpi-title">Presupuesto de Costo Directo</div>
      <div class="kpi-value" style="color: #ff5c05;">$<?= number_format($calculated_direct_total, 2) ?></div>
      <span class="small text-muted">Costo total de ejecución interna</span>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-12">
    <!-- GENERAL METADATA CARD -->
    <div class="card mb-4">
      <div class="card-body py-3">
        <div class="row">
          <div class="col-md-3">
            <span class="small text-muted d-block">Cliente</span>
            <strong class="text-dark"><?= htmlspecialchars($quote['cliente']) ?></strong>
          </div>
          <div class="col-md-3">
            <span class="small text-muted d-block">Contrato / Proyecto</span>
            <strong class="text-dark"><?= htmlspecialchars($quote['contrato'] ?: '-') ?></strong>
          </div>
          <div class="col-md-3">
            <span class="small text-muted d-block">Versión de Origen</span>
            <strong class="text-dark">v<?= $quote['version'] ?> (ID: <?= $quote['id'] ?>)</strong>
          </div>
          <div class="col-md-3">
            <span class="small text-muted d-block">Fecha de Emisión</span>
            <strong class="text-dark"><?= htmlspecialchars($quote['fecha']) ?></strong>
          </div>
        </div>
      </div>
    </div>

    <!-- MAIN MATRIX TABLE -->
    <div class="card shadow-sm">
      <div class="card-header matrix-header-gradient d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-white"><i class="fas fa-table mr-2"></i> Matriz Detallada de Tareas de Ejecución</h6>
        <span class="small">Valores expresados exclusivamente en COSTO DIRECTO (PVP excluido)</span>
      </div>
      <div class="card-body p-0">
        <table class="table table-bordered table-striped table-matrix mb-0" id="matrix-table">
          <thead>
            <tr>
              <th style="width: 130px;">Sección</th>
              <th>Marca / Categoría</th>
              <th>Actividad</th>
              <th>Detalle de la Tarea / Ejecución</th>
              <th style="width: 80px;" class="text-center">Esp.</th>
              <th style="width: 90px;" class="text-center">Mult.</th>
              <th style="width: 80px;" class="text-center">H. Lab</th>
              <th style="width: 80px;" class="text-center">H. 50%</th>
              <th style="width: 80px;" class="text-center">H. 100%</th>
              <th style="width: 100px;" class="text-right">Costo Hora</th>
              <th style="width: 120px;" class="text-right">Costo Total</th>
            </tr>
          </thead>
          <tbody>
            <?php 
            $current_sec = '';
            foreach ($details as $item): 
              $badge_class = 'bg-secondary text-white';
              if ($item['seccion'] === 'Implementacion') { $badge_class = 'bg-impl'; $sec_lbl = 'Implementación'; }
              elseif ($item['seccion'] === 'MantPrev') { $badge_class = 'bg-prev'; $sec_lbl = 'Prev. Mantenimiento'; }
              elseif ($item['seccion'] === 'MantCorr') { $badge_class = 'bg-corr'; $sec_lbl = 'Corr. Mantenimiento'; }
              else { $badge_class = 'bg-bolsa'; $sec_lbl = 'Bolsa de Horas'; }
            ?>
              <tr>
                <td><span class="section-badge-matrix <?= $badge_class ?>"><?= $sec_lbl ?></span></td>
                <td><strong><?= htmlspecialchars($item['marca_categoria']) ?></strong></td>
                <td><?= htmlspecialchars($item['actividad']) ?></td>
                <td>
                  <?= htmlspecialchars($item['detalle']) ?>
                  <?php if(!empty($item['observaciones'])): ?>
                    <div class="text-muted font-italic small mt-1"><i class="far fa-comment mr-1"></i><?= htmlspecialchars($item['observaciones']) ?></div>
                  <?php endif; ?>
                </td>
                <td class="text-center"><span class="badge badge-secondary"><?= htmlspecialchars($item['especialista_nivel']) ?></span></td>
                <td class="text-center"><span class="small text-muted"><?= htmlspecialchars($item['multiplier_type']) ?></span></td>
                <td class="text-center"><?= number_format($item['horas_laborables'], 1) ?></td>
                <td class="text-center"><?= number_format($item['horas_no_laborables_50'], 1) ?></td>
                <td class="text-center"><?= number_format($item['horas_no_laborables_100'], 1) ?></td>
                <td class="text-right">$<?= number_format($item['costo_hora'], 2) ?></td>
                <td class="text-right font-weight-bold">$<?= number_format($item['costo_total'], 2) ?></td>
              </tr>
            <?php endforeach; ?>

            <!-- Row: Specialist Direct Costs Subtotal -->
            <tr class="table-info font-weight-bold">
              <td colspan="6" class="text-right">Subtotal Directo de Tareas del Listado:</td>
              <td class="text-center"><?= number_format(array_sum(array_column($details, 'horas_laborables')), 1) ?></td>
              <td class="text-center"><?= number_format(array_sum(array_column($details, 'horas_no_laborables_50')), 1) ?></td>
              <td class="text-center"><?= number_format(array_sum(array_column($details, 'horas_no_laborables_100')), 1) ?></td>
              <td></td>
              <td class="text-right">$<?= number_format($total_specialist_cost, 2) ?></td>
            </tr>

            <!-- ADDITIONAL VARIABLE DIRECT COSTS -->
            <tr class="bg-light font-weight-bold"><td colspan="11" class="text-uppercase text-secondary small py-2">Costos Directos Adicionales de Ejecución</td></tr>
            
            <?php if ($impl_travel_cost > 0): ?>
              <tr>
                <td><span class="section-badge-matrix bg-impl">Implementación</span></td>
                <td colspan="3">Gastos de Viaje y Viáticos (<?= (int)$adicionales['impl_travel_nights'] ?> noches / <?= (int)$adicionales['impl_flights_qty'] ?> vuelos)</td>
                <td colspan="6"></td>
                <td class="text-right">$<?= number_format($impl_travel_cost, 2) ?></td>
              </tr>
            <?php endif; ?>
            
            <?php if ($impl_pss_cost > 0): ?>
              <tr>
                <td><span class="section-badge-matrix bg-impl">Implementación</span></td>
                <td colspan="3">Soporte Directo Fabricante (PSS)</td>
                <td colspan="6"></td>
                <td class="text-right">$<?= number_format($impl_pss_cost, 2) ?></td>
              </tr>
            <?php endif; ?>

            <?php if ($impl_ext_prov_cost > 0): ?>
              <tr>
                <td><span class="section-badge-matrix bg-impl">Implementación</span></td>
                <td colspan="3">Proveedor Externo Directo</td>
                <td colspan="6"></td>
                <td class="text-right">$<?= number_format($impl_ext_prov_cost, 2) ?></td>
              </tr>
            <?php endif; ?>

            <?php if ($boc_cost > 0): ?>
              <tr>
                <td><span class="section-badge-matrix bg-impl">Implementación</span></td>
                <td colspan="3">Soporte Monitoreo BOC (Directo: <?= (int)$adicionales['impl_boc_months'] ?> meses x <?= $adicionales['impl_boc_hours'] ?> horas/mes)</td>
                <td class="text-center"><span class="badge badge-secondary"><?= htmlspecialchars($adicionales['impl_boc_level'] ?? 'BOC') ?></span></td>
                <td></td>
                <td colspan="3" class="text-center"><?= number_format($boc_hours, 1) ?> h</td>
                <td class="text-right">$<?= number_format($boc_sp ? $boc_sp['costo_hora_lab'] : 0, 2) ?></td>
                <td class="text-right">$<?= number_format($boc_cost, 2) ?></td>
              </tr>
            <?php endif; ?>

            <?php if ($pm_cost > 0): ?>
              <tr>
                <td><span class="section-badge-matrix bg-impl">Implementación</span></td>
                <td colspan="3">Informes y Reportes PM (Directo: <?= (int)$adicionales['impl_pm_months'] ?> meses x <?= $adicionales['impl_pm_hours'] ?> horas/mes)</td>
                <td class="text-center"><span class="badge badge-secondary"><?= htmlspecialchars($adicionales['impl_pm_level'] ?? 'GP1') ?></span></td>
                <td></td>
                <td colspan="3" class="text-center"><?= number_format($pm_hours, 1) ?> h</td>
                <td class="text-right">$<?= number_format($pm_sp ? $pm_sp['costo_hora_lab'] : 0, 2) ?></td>
                <td class="text-right">$<?= number_format($pm_cost, 2) ?></td>
              </tr>
            <?php endif; ?>

            <?php if ($kt_cost > 0): ?>
              <tr>
                <td><span class="section-badge-matrix bg-impl">Implementación</span></td>
                <td colspan="3">Transferencia de Conocimiento (KT) (Horas directas de capacitación + Breaks)</td>
                <td class="text-center"><span class="badge badge-secondary"><?= htmlspecialchars($adicionales['impl_kt_level'] ?? 'N3') ?></span></td>
                <td></td>
                <td colspan="3" class="text-center"><?= number_format($kt_hours, 1) ?> h</td>
                <td></td>
                <td class="text-right">$<?= number_format($kt_cost, 2) ?></td>
              </tr>
            <?php endif; ?>

            <?php if ($consumables_cost > 0): ?>
              <tr>
                <td><span class="section-badge-matrix bg-impl">Implementación</span></td>
                <td colspan="3">Materiales Consumibles directos (Tornillos, etiquetas, vacunas, EPP)</td>
                <td colspan="6"></td>
                <td class="text-right">$<?= number_format($consumables_cost, 2) ?></td>
              </tr>
            <?php endif; ?>

            <?php if ($risk_cost > 0): ?>
              <tr>
                <td><span class="section-badge-matrix bg-impl">Implementación</span></td>
                <td colspan="3">Presupuesto de Mitigación de Riesgos de Implementación (<?= round($quote['risk_percentage'] * 100) ?>%)</td>
                <td colspan="6"></td>
                <td class="text-right">$<?= number_format($risk_cost, 2) ?></td>
              </tr>
            <?php endif; ?>

            <?php if ($prev_travel_cost > 0): ?>
              <tr>
                <td><span class="section-badge-matrix bg-prev">Prev. Mantenimiento</span></td>
                <td colspan="3">Gastos de Viaje de Mantenimiento Preventivo (<?= (int)$adicionales['prev_travel_nights'] ?> noches / <?= (int)$adicionales['prev_flights_qty'] ?> vuelos)</td>
                <td colspan="6"></td>
                <td class="text-right">$<?= number_format($prev_travel_cost, 2) ?></td>
              </tr>
            <?php endif; ?>

            <?php if ($prev_materials_cost > 0 || $prev_pss_cost > 0): ?>
              <tr>
                <td><span class="section-badge-matrix bg-prev">Prev. Mantenimiento</span></td>
                <td colspan="3">Materiales Preventivos ($<?= number_format($prev_materials_cost, 2) ?>) y Soporte PSS ($<?= number_format($prev_pss_cost, 2) ?>)</td>
                <td colspan="6"></td>
                <td class="text-right">$<?= number_format($prev_materials_cost + $prev_pss_cost, 2) ?></td>
              </tr>
            <?php endif; ?>

            <?php if ($corr_method === 'hours'): ?>
              <?php if ($corr_travel_cost > 0): ?>
                <tr>
                  <td><span class="section-badge-matrix bg-corr">Corr. Mantenimiento</span></td>
                  <td colspan="3">Gastos de Viaje de Mantenimiento Correctivo (<?= (int)$adicionales['corr_travel_nights'] ?> noches / <?= (int)$adicionales['corr_flights_qty'] ?> vuelos)</td>
                  <td colspan="6"></td>
                  <td class="text-right">$<?= number_format($corr_travel_cost, 2) ?></td>
                </tr>
              <?php endif; ?>
              <?php if ($corr_materials_cost > 0 || $corr_pss_cost > 0): ?>
                <tr>
                  <td><span class="section-badge-matrix bg-corr">Corr. Mantenimiento</span></td>
                  <td colspan="3">Repuestos directos ($<?= number_format($corr_materials_cost, 2) ?>) y Soporte PSS ($<?= number_format($corr_pss_cost, 2) ?>)</td>
                  <td colspan="6"></td>
                  <td class="text-right">$<?= number_format($corr_materials_cost + $corr_pss_cost, 2) ?></td>
                </tr>
              <?php endif; ?>
            <?php else: ?>
              <?php if ($corr_cases_cost > 0): ?>
                <tr>
                  <td><span class="section-badge-matrix bg-corr">Corr. Mantenimiento</span></td>
                  <td colspan="3">Mantenimiento Correctivo por Casos (<?= (int)$cases_calc ?> casos x <?= $hours_case ?>h c/u + viáticos unitarios)</td>
                  <td class="text-center"><span class="badge badge-secondary"><?= htmlspecialchars($adicionales['corr_case_level'] ?? 'N2') ?></span></td>
                  <td></td>
                  <td colspan="3" class="text-center"><?= number_format($corr_cases_hours, 1) ?> h</td>
                  <td></td>
                  <td class="text-right">$<?= number_format($corr_cases_cost, 2) ?></td>
                </tr>
              <?php endif; ?>
            <?php endif; ?>

            <?php if ($bolsa_travel_cost > 0): ?>
              <tr>
                <td><span class="section-badge-matrix bg-bolsa">Bolsa de Horas</span></td>
                <td colspan="3">Desplazamientos y Viáticos Extra de Bolsa (<?= (int)$adicionales['bolsa_travel_extra'] ?> viajes / <?= (int)$adicionales['bolsa_flights_qty'] ?> vuelos)</td>
                <td colspan="6"></td>
                <td class="text-right">$<?= number_format($bolsa_travel_cost, 2) ?></td>
              </tr>
            <?php endif; ?>

            <!-- Row: TOTAL DIRECT COST -->
            <tr class="table-success font-weight-bold" style="font-size: 0.95rem;">
              <td colspan="10" class="text-right text-uppercase">Presupuesto de Costo Directo de Ejecución (Total General):</td>
              <td class="text-right text-success">$<?= number_format($calculated_direct_total, 2) ?></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
function exportMatrixToExcel() {
  let table = document.getElementById("matrix-table");
  let html = table.outerHTML;
  
  // Basic xls formatting
  let excelTemplate = `
    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
    <head>
      <meta charset="utf-8">
      <style>
        table { border-collapse: collapse; }
        th { background-color: #1d3557; color: #ffffff; border: 1px solid #000000; font-weight: bold; }
        td { border: 1px solid #000000; }
        .table-info { background-color: #d1ecf1; font-weight: bold; }
        .table-success { background-color: #d4edda; font-weight: bold; }
        .bg-light { background-color: #f8f9fa; }
      </style>
    </head>
    <body>
      <h3>Matriz de Ejecución e Implementación - Costos Directos</h3>
      <p><strong>Cliente:</strong> <?= htmlspecialchars($quote['cliente']) ?></p>
      <p><strong>Contrato / Proyecto:</strong> <?= htmlspecialchars($quote['contrato'] ?: '-') ?></p>
      <p><strong>Fecha Emisión:</strong> <?= htmlspecialchars($quote['fecha']) ?></p>
      <br/>
      ${html}
    </body>
    </html>
  `;

  let blob = new Blob([excelTemplate], { type: "application/vnd.ms-excel" });
  let link = document.createElement("a");
  link.href = URL.createObjectURL(blob);
  link.download = "Matriz_Ejecucion_<?= str_replace(' ', '_', htmlspecialchars($quote['cliente'])) ?>_v<?= $quote['version'] ?>.xls";
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}
</script>

<?php
include '../partials/footer.php';
?>

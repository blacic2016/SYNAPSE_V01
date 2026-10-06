<?php
/**
 * View & Compare Quotation - CMDB VILASECA
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

// 1. Fetch current quote
$stmt = $pdo->prepare("SELECT * FROM cotizador_cotizaciones WHERE id = ?");
$stmt->execute([$id]);
$quote = $stmt->fetch();
if (!$quote) {
    die("Cotización no encontrada.");
}

// 2. Fetch sibling versions
$parentId = $quote['parent_id'] ? $quote['parent_id'] : $quote['id'];
$stmt_vers = $pdo->prepare("SELECT id, version, cliente, contrato, fecha, total_precio, estado FROM cotizador_cotizaciones WHERE id = ? OR parent_id = ? ORDER BY version DESC");
$stmt_vers->execute([$parentId, $parentId]);
$sibling_versions = $stmt_vers->fetchAll();

// 3. Fetch all other quotes for comparison dropdown
$stmt_all = $pdo->prepare("SELECT id, version, cliente, contrato, fecha, total_precio FROM cotizador_cotizaciones WHERE id != ? ORDER BY cliente ASC, contrato ASC, version DESC");
$stmt_all->execute([$id]);
$all_other_quotes = $stmt_all->fetchAll();

// 4. Fetch details
$stmt_det = $pdo->prepare("SELECT * FROM cotizador_cotizaciones_detalles WHERE cotizacion_id = ?");
$stmt_det->execute([$id]);
$details = $stmt_det->fetchAll();

// 5. Fetch specialists to calculate rates or labels
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

$page_title = "Detalle de Cotización - " . htmlspecialchars($quote['cliente']);
$hide_content_header = true;
include '../partials/header.php';
?>

<!-- Premium styling extensions -->
<style>
  :root {
    --primary-gradient: linear-gradient(135deg, #101b31 0%, #1a305c 100%);
    --accent-color: #ff5c05;
    --success-color: #28a745;
    --danger-color: #dc3545;
    --warning-color: #ffc107;
    --info-color: #17a2b8;
    --border-radius: 8px;
  }
  
  .card-view {
    border-radius: var(--border-radius);
    border: 1px solid rgba(0,0,0,0.1);
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    background: var(--card-bg);
    margin-bottom: 20px;
    overflow: hidden;
  }
  
  .card-view .card-header {
    background: var(--primary-gradient);
    color: #fff;
    border-bottom: none;
    padding: 12px 18px;
  }
  
  .table-view th {
    background-color: rgba(16, 27, 49, 0.04) !important;
    color: #101b31 !important;
    font-size: 0.8rem;
    text-transform: uppercase;
    font-weight: 700;
    padding: 6px 10px !important;
  }
  
  body.dark-mode .table-view th {
    background-color: rgba(255, 255, 255, 0.05) !important;
    color: #fff !important;
  }

  .table-view td {
    padding: 6px 10px !important;
    font-size: 0.82rem !important;
    vertical-align: middle !important;
  }
  
  .badge-status {
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.78rem;
    font-weight: 600;
  }
  .badge-borrador { background-color: #ffc107; color: #1f2d3d; }
  .badge-enviada { background-color: #28a745; color: #fff; }

  /* Diff coloring */
  .diff-added {
    background-color: rgba(40, 167, 69, 0.12) !important;
  }
  .diff-removed {
    background-color: rgba(220, 53, 69, 0.12) !important;
    text-decoration: line-through;
  }
  .diff-modified {
    background-color: rgba(255, 193, 7, 0.15) !important;
  }
  .diff-highlight {
    background-color: #ffe8a1;
    font-weight: bold;
    padding: 1px 4px;
    border-radius: 3px;
    color: #000;
  }
  body.dark-mode .diff-highlight {
    background-color: #8c6b00;
    color: #fff;
  }
  
  .calc-label-small {
    font-size: 0.75rem;
    color: #6c757d;
  }

  .sub-total-view {
    font-size: 0.95rem;
    font-weight: bold;
    color: var(--accent-color);
    background-color: rgba(255, 92, 5, 0.05);
  }
</style>

<div class="row">
  <div class="col-12">
    <!-- Back to lists -->
    <div class="d-flex justify-content-between align-items-center mb-3">
      <a href="index.php?tab=list" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i> Volver a Historial</a>
      <div>
        <h4 class="m-0 font-weight-bold text-dark d-inline-block align-middle">Cotización: <?= htmlspecialchars($quote['cliente']) ?></h4>
        <span class="badge badge-secondary ml-2 align-middle">v<?= $quote['version'] ?></span>
        <span class="badge-status <?= $quote['estado'] === 'Enviada' ? 'badge-enviada' : 'badge-borrador' ?> ml-2 align-middle">
          <?= $quote['estado'] === 'Enviada' ? 'Enviada (Aprobada)' : 'Borrador' ?>
        </span>
      </div>
      <div>
        <?php if ($quote['estado'] === 'Enviada'): ?>
          <button onclick="convertToProject(<?= $quote['id'] ?>)" class="btn btn-primary btn-sm mr-2"><i class="fas fa-project-diagram mr-1"></i> Traspasar a Proyecto</button>
        <?php endif; ?>
        <a href="matrix.php?id=<?= $quote['id'] ?>" target="_blank" class="btn btn-success btn-sm mr-2"><i class="fas fa-table mr-1"></i> Matriz de Ejecución</a>
        <a href="print.php?id=<?= $quote['id'] ?>" target="_blank" class="btn btn-info btn-sm"><i class="fas fa-print mr-1"></i> Imprimir / PDF</a>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <!-- Left Column: Details of Quote -->
  <div class="col-lg-8" id="quote-details-panel">
    
    <!-- CARD 1: GENERAL METADATA -->
    <div class="card card-view">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold"><i class="fas fa-info-circle mr-2"></i> Datos Generales</h6>
        <span class="small">Creado el: <?= htmlspecialchars($quote['created_at']) ?></span>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-4 form-group mb-2">
            <span class="calc-label-small d-block">Cliente</span>
            <strong><?= htmlspecialchars($quote['cliente']) ?></strong>
          </div>
          <div class="col-md-4 form-group mb-2">
            <span class="calc-label-small d-block">Contrato / Proyecto</span>
            <strong><?= htmlspecialchars($quote['contrato'] ?: '-') ?></strong>
          </div>
          <div class="col-md-4 form-group mb-2">
            <span class="calc-label-small d-block">Fecha Propuesta</span>
            <strong><?= htmlspecialchars($quote['fecha']) ?></strong>
          </div>
          <div class="col-md-4 form-group mb-0 mt-2">
            <span class="calc-label-small d-block">Margen Global</span>
            <strong><?= round($quote['margen_global'] * 100) ?>%</strong>
          </div>
          <div class="col-md-4 form-group mb-0 mt-2">
            <span class="calc-label-small d-block">Riesgo Configurado</span>
            <strong><?= round($quote['risk_percentage'] * 100) ?>%</strong>
          </div>
          <div class="col-md-4 form-group mb-0 mt-2">
            <span class="calc-label-small d-block">Aprobado Por</span>
            <strong><?= htmlspecialchars($quote['aprobado_por'] ?: 'No aprobado') ?> <?= $quote['aprobado_fecha'] ? '(' . $quote['aprobado_fecha'] . ')' : '' ?></strong>
          </div>
        </div>
        
        <?php if (!empty($quote['observaciones'])): ?>
          <hr class="my-2">
          <div>
            <span class="calc-label-small d-block">Observaciones:</span>
            <p class="mb-0 bg-light p-2 rounded small" style="white-space: pre-wrap;"><?= htmlspecialchars($quote['observaciones']) ?></p>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- CARD 2: EQUIPMENT INVENTORY -->
    <div class="card card-view">
      <div class="card-header">
        <h6 class="m-0 font-weight-bold"><i class="fas fa-network-wired mr-2"></i> Inventario de Equipos (Multiplicadores)</h6>
      </div>
      <div class="card-body p-0">
        <?php 
        $eq_rows = $adicionales['eq_rows'] ?? [];
        if (empty($eq_rows)): 
        ?>
          <div class="p-3 text-muted text-center small">No se definieron multiplicadores de equipos en el inventario.</div>
        <?php else: ?>
          <table class="table table-bordered table-striped table-view mb-0">
            <thead>
              <tr>
                <th>Categoría / Tipo de Equipo</th>
                <th style="width: 150px;" class="text-center">Cantidad</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($eq_rows as $row): ?>
                <tr>
                  <td><?= htmlspecialchars($row['type']) ?></td>
                  <td class="text-center font-weight-bold"><?= (int)$row['qty'] ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>

    <!-- CARD 3: IMPLEMENTATION DETAILS -->
    <div class="card card-view">
      <div class="card-header">
        <h6 class="m-0 font-weight-bold"><i class="fas fa-tools mr-2"></i> 03. Servicios de Implementación</h6>
      </div>
      <div class="card-body p-0">
        <?php 
        $impl_items = array_filter($details, function($item) { return $item['seccion'] === 'Implementacion'; });
        if (empty($impl_items)): 
        ?>
          <div class="p-3 text-muted text-center small">No hay servicios de implementación en esta cotización.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-bordered table-view mb-0">
              <thead>
                <tr>
                  <th>Código</th>
                  <th>Marca</th>
                  <th>Actividad / Detalle</th>
                  <th>Mult.</th>
                  <th>Esp.</th>
                  <th class="text-center">H. Lab</th>
                  <th class="text-center">H. 50%</th>
                  <th class="text-center">H. 100%</th>
                  <th class="text-right">Costo Total</th>
                  <th class="text-right">PVP Total</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($impl_items as $item): ?>
                  <tr>
                    <td><code class="small"><?= htmlspecialchars($item['codigo_unico'] ?: '-') ?></code></td>
                    <td><?= htmlspecialchars($item['marca_categoria']) ?></td>
                    <td>
                      <strong><?= htmlspecialchars($item['actividad']) ?></strong>
                      <div class="text-muted small"><?= htmlspecialchars($item['detalle']) ?></div>
                      <?php if(!empty($item['observaciones'])): ?>
                        <div class="text-info font-italic small"><i class="fas fa-comment-dots mr-1"></i><?= htmlspecialchars($item['observaciones']) ?></div>
                      <?php endif; ?>
                    </td>
                    <td class="text-center"><span class="badge badge-secondary"><?= htmlspecialchars($item['multiplier_type']) ?></span></td>
                    <td><?= htmlspecialchars($item['especialista_nivel']) ?></td>
                    <td class="text-center"><?= number_format($item['horas_laborables'], 1) ?></td>
                    <td class="text-center"><?= number_format($item['horas_no_laborables_50'], 1) ?></td>
                    <td class="text-center"><?= number_format($item['horas_no_laborables_100'], 1) ?></td>
                    <td class="text-right">$<?= number_format($item['costo_total'], 2) ?></td>
                    <td class="text-right font-weight-bold">$<?= number_format($item['pvp_total'], 2) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

        <!-- Adicionales de Implementación -->
        <div class="p-3 bg-light border-top">
          <h6 class="font-weight-bold text-secondary mb-2 small text-uppercase">Adicionales de Implementación:</h6>
          <div class="row small">
            <div class="col-md-4">
              <i class="fas fa-graduation-cap text-warning mr-1"></i> Transferencia: 
              <strong><?= ($adicionales['impl_kt_incluye'] ?? 'No') === 'Si' ? 'Sí (' . ($adicionales['impl_kt_hours'] ?? 0) . 'h)' : 'No' ?></strong>
            </div>
            <div class="col-md-4">
              <i class="fas fa-plane-departure text-info mr-1"></i> Viáticos: 
              <strong><?= (int)($adicionales['impl_travel_nights'] ?? 0) ?> noches / <?= (int)($adicionales['impl_flights_qty'] ?? 0) ?> vuelos</strong>
            </div>
            <div class="col-md-4">
              <i class="fas fa-ticket-alt text-success mr-1"></i> PSS Fabricante: 
              <strong>$<?= number_format($adicionales['impl_pss_val'] ?? 0, 2) ?></strong>
            </div>
            <div class="col-md-4 mt-1">
              <i class="fas fa-headset text-danger mr-1"></i> BOC: 
              <strong><?= (int)($adicionales['impl_boc_months'] ?? 0) ?> meses x <?= ($adicionales['impl_boc_hours'] ?? 0) ?>h</strong>
            </div>
            <div class="col-md-4 mt-1">
              <i class="fas fa-file-alt text-secondary mr-1"></i> PM Reportes: 
              <strong><?= (int)($adicionales['impl_pm_months'] ?? 0) ?> meses x <?= ($adicionales['impl_pm_hours'] ?? 0) ?>h</strong>
            </div>
            <div class="col-md-4 mt-1">
              <i class="fas fa-box-open text-primary mr-1"></i> Consumibles: 
              <strong>$<?= number_format(($adicionales['impl_consumables_screws'] ?? 0) + ($adicionales['impl_consumables_labels'] ?? 0) + ($adicionales['impl_consumables_vaccines'] ?? 0) + ($adicionales['impl_consumables_epp'] ?? 0), 2) ?></strong>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- CARD 4: PREVENTIVE MAINTENANCE -->
    <div class="card card-view">
      <div class="card-header">
        <h6 class="m-0 font-weight-bold"><i class="fas fa-shield-alt mr-2"></i> 04. Mantenimiento Preventivo</h6>
      </div>
      <div class="card-body p-0">
        <?php 
        $prev_items = array_filter($details, function($item) { return $item['seccion'] === 'MantPrev'; });
        if (empty($prev_items)): 
        ?>
          <div class="p-3 text-muted text-center small">No hay mantenimiento preventivo en esta cotización.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-bordered table-view mb-0">
              <thead>
                <tr>
                  <th>Código</th>
                  <th>Marca</th>
                  <th>Actividad / Detalle</th>
                  <th>Mult.</th>
                  <th>Esp.</th>
                  <th class="text-center">H. Lab</th>
                  <th class="text-center">H. 50%</th>
                  <th class="text-center">H. 100%</th>
                  <th class="text-right">Costo Total</th>
                  <th class="text-right">PVP Total</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($prev_items as $item): ?>
                  <tr>
                    <td><code class="small"><?= htmlspecialchars($item['codigo_unico'] ?: '-') ?></code></td>
                    <td><?= htmlspecialchars($item['marca_categoria']) ?></td>
                    <td>
                      <strong><?= htmlspecialchars($item['actividad']) ?></strong>
                      <div class="text-muted small"><?= htmlspecialchars($item['detalle']) ?></div>
                    </td>
                    <td class="text-center"><span class="badge badge-secondary"><?= htmlspecialchars($item['multiplier_type']) ?></span></td>
                    <td><?= htmlspecialchars($item['especialista_nivel']) ?></td>
                    <td class="text-center"><?= number_format($item['horas_laborables'], 1) ?></td>
                    <td class="text-center"><?= number_format($item['horas_no_laborables_50'], 1) ?></td>
                    <td class="text-center"><?= number_format($item['horas_no_laborables_100'], 1) ?></td>
                    <td class="text-right">$<?= number_format($item['costo_total'], 2) ?></td>
                    <td class="text-right font-weight-bold">$<?= number_format($item['pvp_total'], 2) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
        
        <!-- Adicionales de Preventivo -->
        <div class="p-3 bg-light border-top">
          <h6 class="font-weight-bold text-secondary mb-2 small text-uppercase">Adicionales de Preventivo:</h6>
          <div class="row small">
            <div class="col-md-4">
              <i class="fas fa-plane-departure text-info mr-1"></i> Viáticos Prev: 
              <strong><?= (int)($adicionales['prev_travel_nights'] ?? 0) ?> noches / <?= (int)($adicionales['prev_flights_qty'] ?? 0) ?> vuelos</strong>
            </div>
            <div class="col-md-4">
              <i class="fas fa-toolbox text-warning mr-1"></i> Materiales Mant: 
              <strong>$<?= number_format($adicionales['prev_materials_cost'] ?? 0, 2) ?></strong>
            </div>
            <div class="col-md-4">
              <i class="fas fa-ticket-alt text-success mr-1"></i> PSS Preventivo: 
              <strong>$<?= number_format($adicionales['prev_pss_cost'] ?? 0, 2) ?></strong>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- CARD 5: CORRECTIVE MAINTENANCE -->
    <div class="card card-view">
      <div class="card-header">
        <h6 class="m-0 font-weight-bold"><i class="fas fa-wrench mr-2"></i> 05. Mantenimiento Correctivo</h6>
      </div>
      <div class="card-body p-0">
        <?php 
        $corr_method = $adicionales['corr_method'] ?? 'hours';
        if ($corr_method === 'hours'):
          $corr_items = array_filter($details, function($item) { return $item['seccion'] === 'MantCorr'; });
          if (empty($corr_items)): 
        ?>
            <div class="p-3 text-muted text-center small">No hay mantenimiento correctivo basado en horas en esta cotización.</div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-bordered table-view mb-0">
                <thead>
                  <tr>
                    <th>Código</th>
                    <th>Marca</th>
                    <th>Actividad / Detalle</th>
                    <th>Mult.</th>
                    <th>Esp.</th>
                    <th class="text-center">H. Lab</th>
                    <th class="text-center">H. 50%</th>
                    <th class="text-center">H. 100%</th>
                    <th class="text-right">Costo Total</th>
                    <th class="text-right">PVP Total</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($corr_items as $item): ?>
                    <tr>
                      <td><code class="small"><?= htmlspecialchars($item['codigo_unico'] ?: '-') ?></code></td>
                      <td><?= htmlspecialchars($item['marca_categoria']) ?></td>
                      <td>
                        <strong><?= htmlspecialchars($item['actividad']) ?></strong>
                        <div class="text-muted small"><?= htmlspecialchars($item['detalle']) ?></div>
                      </td>
                      <td class="text-center"><span class="badge badge-secondary"><?= htmlspecialchars($item['multiplier_type']) ?></span></td>
                      <td><?= htmlspecialchars($item['especialista_nivel']) ?></td>
                      <td class="text-center"><?= number_format($item['horas_laborables'], 1) ?></td>
                      <td class="text-center"><?= number_format($item['horas_no_laborables_50'], 1) ?></td>
                      <td class="text-center"><?= number_format($item['horas_no_laborables_100'], 1) ?></td>
                      <td class="text-right">$<?= number_format($item['costo_total'], 2) ?></td>
                      <td class="text-right font-weight-bold">$<?= number_format($item['pvp_total'], 2) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
          
          <!-- Adicionales de Correctivo Horas -->
          <div class="p-3 bg-light border-top">
            <h6 class="font-weight-bold text-secondary mb-2 small text-uppercase">Adicionales de Correctivo (Opción Horas):</h6>
            <div class="row small">
              <div class="col-md-4">
                <i class="fas fa-plane-departure text-info mr-1"></i> Viáticos: 
                <strong><?= (int)($adicionales['corr_travel_nights'] ?? 0) ?> noches / <?= (int)($adicionales['corr_flights_qty'] ?? 0) ?> vuelos</strong>
              </div>
              <div class="col-md-4">
                <i class="fas fa-toolbox text-warning mr-1"></i> Materiales: 
                <strong>$<?= number_format($adicionales['corr_materials_cost'] ?? 0, 2) ?></strong>
              </div>
              <div class="col-md-4">
                <i class="fas fa-ticket-alt text-success mr-1"></i> PSS Correctivo: 
                <strong>$<?= number_format($adicionales['corr_pss_cost'] ?? 0, 2) ?></strong>
              </div>
            </div>
          </div>
        <?php else: ?>
          <!-- Case method -->
          <div class="p-3">
            <div class="alert alert-info border mb-2 py-2 text-center small font-weight-bold">Calculado por Casos Estimados de Soporte</div>
            <div class="row small">
              <div class="col-md-3">Equipos: <strong><?= (int)($adicionales['corr_case_equipos'] ?? 0) ?></strong></div>
              <div class="col-md-3">% Daño: <strong><?= ($adicionales['corr_case_dmg_pct'] ?? 0) * 100 ?>%</strong></div>
              <div class="col-md-3">Años Contrato: <strong><?= (int)($adicionales['corr_case_years'] ?? 1) ?></strong></div>
              <div class="col-md-3">Horas/Caso: <strong><?= ($adicionales['corr_case_hours_per_case'] ?? 0) ?>h</strong></div>
              <div class="col-md-3 mt-2">Especialista: <strong><?= htmlspecialchars($adicionales['corr_case_level'] ?? 'N2') ?></strong></div>
              <div class="col-md-3 mt-2">Costo Movil. Unit: <strong>$<?= number_format($adicionales['corr_case_mov_cost'] ?? 0, 2) ?></strong></div>
              <div class="col-md-3 mt-2">PVP Movil. Unit: <strong>$<?= number_format($adicionales['corr_case_mov_pvp'] ?? 0, 2) ?></strong></div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- CARD 6: BOLSA DE HORAS -->
    <div class="card card-view">
      <div class="card-header">
        <h6 class="m-0 font-weight-bold"><i class="fas fa-business-time mr-2"></i> 06. Bolsa de Horas</h6>
      </div>
      <div class="card-body p-0">
        <?php 
        $bolsa_items = array_filter($details, function($item) { return $item['seccion'] === 'BolsaHoras'; });
        if (empty($bolsa_items)): 
        ?>
          <div class="p-3 text-muted text-center small">No hay bolsa de horas en esta cotización.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-bordered table-view mb-0">
              <thead>
                <tr>
                  <th>Código</th>
                  <th>Marca</th>
                  <th>Actividad / Detalle</th>
                  <th>Mult.</th>
                  <th>Esp.</th>
                  <th class="text-center">H. Lab</th>
                  <th class="text-center">H. 50%</th>
                  <th class="text-center">H. 100%</th>
                  <th class="text-right">Costo Total</th>
                  <th class="text-right">PVP Total</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($bolsa_items as $item): ?>
                  <tr>
                    <td><code class="small"><?= htmlspecialchars($item['codigo_unico'] ?: '-') ?></code></td>
                    <td><?= htmlspecialchars($item['marca_categoria']) ?></td>
                    <td>
                      <strong><?= htmlspecialchars($item['actividad']) ?></strong>
                      <div class="text-muted small"><?= htmlspecialchars($item['detalle']) ?></div>
                    </td>
                    <td class="text-center"><span class="badge badge-secondary"><?= htmlspecialchars($item['multiplier_type']) ?></span></td>
                    <td><?= htmlspecialchars($item['especialista_nivel']) ?></td>
                    <td class="text-center"><?= number_format($item['horas_laborables'], 1) ?></td>
                    <td class="text-center"><?= number_format($item['horas_no_laborables_50'], 1) ?></td>
                    <td class="text-center"><?= number_format($item['horas_no_laborables_100'], 1) ?></td>
                    <td class="text-right">$<?= number_format($item['costo_total'], 2) ?></td>
                    <td class="text-right font-weight-bold">$<?= number_format($item['pvp_total'], 2) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
        
        <!-- Adicionales de Bolsa -->
        <div class="p-3 bg-light border-top">
          <h6 class="font-weight-bold text-secondary mb-2 small text-uppercase">Adicionales Bolsa de Horas:</h6>
          <div class="row small">
            <div class="col-md-6">
              <i class="fas fa-car-side text-warning mr-1"></i> Movilizaciones Extra: 
              <strong><?= (int)($adicionales['bolsa_travel_extra'] ?? 0) ?> viajes</strong>
            </div>
            <div class="col-md-6">
              <i class="fas fa-plane-departure text-info mr-1"></i> Vuelos Nacionales Extra: 
              <strong><?= (int)($adicionales['bolsa_flights_qty'] ?? 0) ?> vuelos</strong>
            </div>
          </div>
        </div>
      </div>
    </div>
    
  </div>

  <!-- Right Column: Sidebar & Comparison & Attachments -->
  <div class="col-lg-4">
    
    <!-- CARD 7: RESUMEN FINANCIERO -->
    <div class="card card-view">
      <div class="card-header bg-success">
        <h6 class="m-0 font-weight-bold text-white"><i class="fas fa-file-invoice-dollar mr-2"></i> Resumen de Costo y PVP</h6>
      </div>
      <div class="card-body p-0">
        <table class="table table-bordered mb-0 table-view">
          <thead>
            <tr>
              <th>Sección</th>
              <th class="text-right">Costo</th>
              <th class="text-right">PVP</th>
            </tr>
          </thead>
          <tbody>
            <?php
            // Calculate totals by section
            $sections = [
                'Implementacion' => 'Implementación',
                'MantPrev' => 'Mantenimiento Prev.',
                'MantCorr' => 'Mantenimiento Corr.',
                'BolsaHoras' => 'Bolsa de Horas'
            ];
            
            // Add custom totals for extras
            // Implementacion extra costs
            $impl_extra_cost = 0;
            $impl_extra_pvp = 0;
            
            // Travel
            $impl_travel_cost = ((int)($adicionales['impl_travel_nights'] ?? 0) * (float)($adicionales['impl_travel_cost_night'] ?? 25)) +
                               ((int)($adicionales['impl_flights_qty'] ?? 0) * (float)($adicionales['impl_flight_cost'] ?? 150));
            $impl_extra_cost += $impl_travel_cost;
            $impl_extra_pvp += $impl_travel_cost / (1 - $quote['margen_global']);
            
            // PSS and Ext Support
            $impl_extra_cost += (float)($adicionales['impl_pss_val'] ?? 0);
            $impl_extra_pvp += (float)($adicionales['impl_pss_val'] ?? 0) / (1 - $quote['margen_global']);
            
            $impl_extra_cost += (float)($adicionales['impl_ext_prov_cost'] ?? 0);
            $impl_extra_pvp += (float)($adicionales['impl_ext_prov_pvp'] ?? 0);
            
            // BOC
            $boc_sp = $specialists_by_type[$adicionales['impl_boc_level'] ?? 'BOC'] ?? null;
            if ($boc_sp) {
                $boc_h_cost = (float)$boc_sp['costo_hora_lab'];
                $boc_h_pvp = $boc_h_cost / (1 - $quote['margen_global']);
                $boc_tot_h = (int)($adicionales['impl_boc_months'] ?? 0) * (float)($adicionales['impl_boc_hours'] ?? 0);
                
                $impl_extra_cost += $boc_tot_h * $boc_h_cost;
                $impl_extra_pvp += $boc_tot_h * $boc_h_pvp;
            }
            
            // PM Reports
            $pm_sp = $specialists_by_type[$adicionales['impl_pm_level'] ?? 'GP1'] ?? null;
            if ($pm_sp) {
                $pm_h_cost = (float)$pm_sp['costo_hora_lab'];
                $pm_h_pvp = $pm_h_cost / (1 - $quote['margen_global']);
                $pm_tot_h = (int)($adicionales['impl_pm_months'] ?? 0) * (float)($adicionales['impl_pm_hours'] ?? 0);
                
                $impl_extra_cost += $pm_tot_h * $pm_h_cost;
                $impl_extra_pvp += $pm_tot_h * $pm_h_pvp;
            }
            
            // Consumables
            $consumables = (float)($adicionales['impl_consumables_screws'] ?? 0) +
                           (float)($adicionales['impl_consumables_labels'] ?? 0) +
                           (float)($adicionales['impl_consumables_vaccines'] ?? 0) +
                           (float)($adicionales['impl_consumables_epp'] ?? 0);
            $impl_extra_cost += $consumables;
            $impl_extra_pvp += $consumables / (1 - $quote['margen_global']);
            
            // KT (Knowledge Transfer)
            if (($adicionales['impl_kt_incluye'] ?? 'No') === 'Si') {
                $kt_sp = $specialists_by_type[$adicionales['impl_kt_level'] ?? 'N3'] ?? null;
                if ($kt_sp) {
                    $kt_h_cost = (float)$kt_sp['costo_hora_lab'];
                    $kt_h_pvp = $kt_h_cost / (1 - $quote['margen_global']);
                    $kt_tot_h = (float)($adicionales['impl_kt_hours'] ?? 0);
                    
                    $impl_extra_cost += $kt_tot_h * $kt_h_cost;
                    $impl_extra_pvp += $kt_tot_h * $kt_h_pvp;
                }
                
                $breaks = (float)($adicionales['impl_breaks_cost'] ?? 0);
                $impl_extra_cost += $breaks;
                $impl_extra_pvp += $breaks / (1 - $quote['margen_global']);
            }
            
            // Preventive extra costs
            $prev_extra_cost = 0;
            $prev_extra_pvp = 0;
            
            $prev_travel = ((int)($adicionales['prev_travel_nights'] ?? 0) * 25) + ((int)($adicionales['prev_flights_qty'] ?? 0) * 150);
            $prev_extra_cost += $prev_travel + (float)($adicionales['prev_materials_cost'] ?? 0) + (float)($adicionales['prev_pss_cost'] ?? 0);
            $prev_extra_pvp += ($prev_travel + (float)($adicionales['prev_materials_cost'] ?? 0) + (float)($adicionales['prev_pss_cost'] ?? 0)) / (1 - $quote['margen_global']);
            
            // Corrective extra costs
            $corr_extra_cost = 0;
            $corr_extra_pvp = 0;
            if ($corr_method === 'hours') {
                $corr_travel = ((int)($adicionales['corr_travel_nights'] ?? 0) * 25) + ((int)($adicionales['corr_flights_qty'] ?? 0) * 150);
                $corr_extra_cost += $corr_travel + (float)($adicionales['corr_materials_cost'] ?? 0) + (float)($adicionales['corr_pss_cost'] ?? 0);
                $corr_extra_pvp += ($corr_travel + (float)($adicionales['corr_materials_cost'] ?? 0) + (float)($adicionales['corr_pss_cost'] ?? 0)) / (1 - $quote['margen_global']);
            } else {
                // Cases formula
                $dmg_equipos = (int)($adicionales['corr_case_equipos'] ?? 0);
                $dmg_pct = (float)($adicionales['corr_case_dmg_pct'] ?? 0.10);
                $years = (int)($adicionales['corr_case_years'] ?? 1);
                $cases_calc = ceil($dmg_equipos * $dmg_pct * $years);
                
                $hours_case = (float)($adicionales['corr_case_hours_per_case'] ?? 4);
                $case_sp = $specialists_by_type[$adicionales['corr_case_level'] ?? 'N2'] ?? null;
                if ($case_sp) {
                    $case_h_cost = (float)$case_sp['costo_hora_lab'];
                    $case_h_pvp = $case_h_cost / (1 - $quote['margen_global']);
                    
                    $corr_extra_cost += $cases_calc * $hours_case * $case_h_cost;
                    $corr_extra_pvp += $cases_calc * $hours_case * $case_h_pvp;
                }
                
                $mov_cost_tot = $cases_calc * (float)($adicionales['corr_case_mov_cost'] ?? 0);
                $mov_pvp_tot = $cases_calc * (float)($adicionales['corr_case_mov_pvp'] ?? 0);
                
                $corr_extra_cost += $mov_cost_tot;
                $corr_extra_pvp += $mov_pvp_tot;
            }
            
            // Bolsa extra costs
            $bolsa_extra_cost = 0;
            $bolsa_extra_pvp = 0;
            $bolsa_travel = ((int)($adicionales['bolsa_travel_extra'] ?? 0) * 15) + ((int)($adicionales['bolsa_flights_qty'] ?? 0) * 150);
            $bolsa_extra_cost += $bolsa_travel;
            $bolsa_extra_pvp += $bolsa_travel / (1 - $quote['margen_global']);

            foreach ($sections as $sec => $label) {
                // Get items total
                $sec_items = array_filter($details, function($item) use ($sec) { return $item['seccion'] === $sec; });
                $sec_cost = array_sum(array_column($sec_items, 'costo_total'));
                $sec_pvp = array_sum(array_column($sec_items, 'pvp_total'));
                
                // Add extras
                if ($sec === 'Implementacion') {
                    $sec_cost += $impl_extra_cost;
                    $sec_pvp += $impl_extra_pvp;
                } elseif ($sec === 'MantPrev') {
                    $sec_cost += $prev_extra_cost;
                    $sec_pvp += $prev_extra_pvp;
                } elseif ($sec === 'MantCorr') {
                    $sec_cost += $corr_extra_cost;
                    $sec_pvp += $corr_extra_pvp;
                } elseif ($sec === 'BolsaHoras') {
                    $sec_cost += $bolsa_extra_cost;
                    $sec_pvp += $bolsa_extra_pvp;
                }
                
                // Apply project risk to implementation cost if configured
                if ($sec === 'Implementacion') {
                    // Risk is calculated on hours cost
                    $impl_hours_cost = array_sum(array_column($sec_items, 'costo_total'));
                    $risk_val = $impl_hours_cost * $quote['risk_percentage'];
                    $sec_cost += $risk_val;
                    $sec_pvp += $risk_val / (1 - $quote['margen_global']);
                }
                
                echo "<tr>";
                echo "<td>{$label}</td>";
                echo "<td class='text-right'>$" . number_format($sec_cost, 2) . "</td>";
                echo "<td class='text-right font-weight-bold'>$" . number_format($sec_pvp, 2) . "</td>";
                echo "</tr>";
            }
            ?>
            <tr class="total-row table-success">
              <td><strong>TOTAL GENERAL</strong></td>
              <td class="text-right"><strong>$<?= number_format($quote['total_costo'], 2) ?></strong></td>
              <td class="text-right font-weight-bold"><strong>$<?= number_format($quote['total_precio'], 2) ?></strong></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- CARD 8: ATTACHMENTS (ADJUNTOS) -->
    <div class="card card-view">
      <div class="card-header bg-info">
        <h6 class="m-0 font-weight-bold text-white"><i class="fas fa-paperclip mr-2"></i> Archivos Adjuntos / Respaldos</h6>
      </div>
      <div class="card-body p-3">
        <!-- Upload form -->
        <form id="upload-attachment-form" enctype="multipart/form-data" class="mb-3">
          <input type="hidden" name="quote_id" value="<?= $quote['id'] ?>">
          <div class="form-group mb-2">
            <label class="small font-weight-bold text-muted">Adjuntar Documento (PDF, Excel, Imagen, Zip...)</label>
            <div class="input-group input-group-sm">
              <div class="custom-file">
                <input type="file" name="attachment_file" id="attachment_file" class="custom-file-input" required>
                <label class="custom-file-label text-truncate" for="attachment_file">Elegir archivo...</label>
              </div>
              <div class="input-group-append">
                <button class="btn btn-primary" type="submit" id="btn-upload-file">Subir</button>
              </div>
            </div>
          </div>
        </form>

        <!-- Attachments List -->
        <div id="attachments-container">
          <div class="text-center py-3 text-muted small"><i class="fas fa-spinner fa-spin mr-1"></i> Cargando adjuntos...</div>
        </div>
      </div>
    </div>

    <!-- CARD 9: VERSION SELECTOR & COMPARE CONTROLS -->
    <div class="card card-view border-primary">
      <div class="card-header bg-primary">
        <h6 class="m-0 font-weight-bold"><i class="fas fa-columns mr-2"></i> Comparar Versión</h6>
      </div>
      <div class="card-body">
        <p class="small text-muted mb-3">
          Seleccione otra cotización o versión guardada para analizar los cambios línea por línea y ver la diferencia en costos, PVP e inventario.
        </p>
        
        <div class="form-group mb-3">
          <label class="small font-weight-bold text-muted">Seleccionar cotización a comparar:</label>
          <select id="compare-target-select" class="form-control form-control-sm">
            <optgroup label="Versiones de esta Cotización">
              <?php foreach ($sibling_versions as $sib): ?>
                <option value="<?= $sib['id'] ?>" <?= $sib['id'] == $quote['id'] ? 'disabled style="color:#ccc;"' : '' ?>>
                  v<?= $sib['id'] == $parentId && !in_array($sib['id'], array_column($sibling_versions, 'parent_id')) ? $sib['version'] . ' (Original)' : $sib['version'] ?> 
                  - <?= $sib['fecha'] ?> ($<?= number_format($sib['total_precio'], 2) ?>) <?= $sib['id'] == $quote['id'] ? '[Actual]' : '' ?>
                </option>
              <?php endforeach; ?>
            </optgroup>
            <optgroup label="Otras Cotizaciones">
              <?php foreach ($all_other_quotes as $oth): ?>
                <?php if ($oth['id'] != $parentId && $oth['parent_id'] != $parentId): ?>
                  <option value="<?= $oth['id'] ?>">
                    <?= htmlspecialchars($oth['cliente']) ?> - <?= htmlspecialchars($oth['contrato']) ?> (v<?= $oth['version'] ?>) - <?= $oth['fecha'] ?> ($<?= number_format($oth['total_precio'], 2) ?>)
                  </option>
                <?php endif; ?>
              <?php endforeach; ?>
            </optgroup>
          </select>
        </div>

        <button class="btn btn-primary btn-sm btn-block" onclick="startComparison()"><i class="fas fa-exchange-alt mr-1"></i> Comparar con Seleccionado</button>
        <button class="btn btn-outline-secondary btn-sm btn-block d-none" id="btn-clear-compare" onclick="clearComparison()"><i class="fas fa-times mr-1"></i> Limpiar Comparación</button>
      </div>
    </div>

  </div>
</div>

<!-- Dynamic Full-Screen Side-by-Side Comparison Container -->
<div class="row d-none" id="comparison-results-panel">
  <div class="col-12">
    <div class="card card-view border-warning">
      <div class="card-header bg-warning d-flex justify-content-between align-items-center">
        <h5 class="m-0 font-weight-bold text-dark"><i class="fas fa-balance-scale mr-2"></i> Análisis Comparativo: Cotización A (v<?= $quote['version'] ?>) vs Cotización B (<span id="compare-ver-b-label">v?</span>)</h5>
        <button class="btn btn-dark btn-xs font-weight-bold text-white px-2 py-1" onclick="clearComparison()"><i class="fas fa-times mr-1"></i> Cerrar Comparativa</button>
      </div>
      <div class="card-body">
        
        <!-- Summary comparison -->
        <div class="row mb-4">
          <div class="col-md-6">
            <h6 class="font-weight-bold text-primary mb-2">Resumen General de Diferencias</h6>
            <table class="table table-bordered table-view table-striped">
              <thead>
                <tr>
                  <th>Métrica</th>
                  <th class="text-right">A (Actual)</th>
                  <th class="text-right">B (Comparada)</th>
                  <th class="text-right">Diferencia</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>Costo Total</td>
                  <td class="text-right" id="diff-cost-a">$0.00</td>
                  <td class="text-right" id="diff-cost-b">$0.00</td>
                  <td class="text-right font-weight-bold" id="diff-cost-delta">$0.00</td>
                </tr>
                <tr>
                  <td>Precio de Venta (PVP)</td>
                  <td class="text-right" id="diff-pvp-a">$0.00</td>
                  <td class="text-right" id="diff-pvp-b">$0.00</td>
                  <td class="text-right font-weight-bold" id="diff-pvp-delta">$0.00</td>
                </tr>
                <tr>
                  <td>Margen</td>
                  <td class="text-right" id="diff-margin-a">0%</td>
                  <td class="text-right" id="diff-margin-b">0%</td>
                  <td class="text-right font-weight-bold" id="diff-margin-delta">0%</td>
                </tr>
              </tbody>
            </table>
          </div>
          
          <div class="col-md-6">
            <h6 class="font-weight-bold text-info mb-2">Información de Cotización B</h6>
            <div class="p-3 bg-light rounded border">
              <div class="row">
                <div class="col-6 mb-2">
                  <span class="calc-label-small d-block">Cliente</span>
                  <strong id="compare-client-b">-</strong>
                </div>
                <div class="col-6 mb-2">
                  <span class="calc-label-small d-block">Proyecto / Contrato</span>
                  <strong id="compare-contract-b">-</strong>
                </div>
                <div class="col-6">
                  <span class="calc-label-small d-block">Fecha</span>
                  <strong id="compare-date-b">-</strong>
                </div>
                <div class="col-6">
                  <span class="calc-label-small d-block">Estado</span>
                  <strong id="compare-status-b">-</strong>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Details Line-by-Line Difference Analysis -->
        <h6 class="font-weight-bold text-secondary border-bottom pb-2 mb-3"><i class="fas fa-list-ol mr-2"></i> Comparación de Líneas de Servicios y Tareas</h6>
        <p class="small text-muted mb-2">
          El listado a continuación compara las actividades entre ambas versiones. Las filas coloreadas indican diferencias:
          <span class="badge badge-success">Verde</span> = Solo en B (Agregada en B), 
          <span class="badge badge-danger">Rojo</span> = Solo en A (Eliminada en B),
          <span class="badge badge-warning text-dark">Amarillo</span> = Modificada (Cambios en horas, tarifa o multiplicador).
        </p>

        <div class="table-responsive">
          <table class="table table-bordered table-view" id="compare-lines-table">
            <thead>
              <tr class="bg-dark text-white">
                <th>Sección</th>
                <th>Código ID</th>
                <th>Marca / Actividad</th>
                <th>Detalle Tarea</th>
                <th>Esp.</th>
                <th>Mult. (A vs B)</th>
                <th class="text-center">H. Lab (A / B)</th>
                <th class="text-center">H. Ext (A / B)</th>
                <th class="text-right">Costo unit (A / B)</th>
                <th class="text-right">PVP unit (A / B)</th>
                <th class="text-right">Total PVP A</th>
                <th class="text-right">Total PVP B</th>
                <th class="text-right">Diferencia</th>
              </tr>
            </thead>
            <tbody id="compare-lines-tbody">
              <!-- Rendered via JS -->
            </tbody>
          </table>
        </div>

        <!-- Inventory Comparison -->
        <h6 class="font-weight-bold text-secondary border-bottom pb-2 mt-4 mb-3"><i class="fas fa-network-wired mr-2"></i> Comparación de Inventario (Multiplicadores)</h6>
        <div class="table-responsive mb-0" style="max-width: 600px;">
          <table class="table table-bordered table-striped table-view" id="compare-inventory-table">
            <thead>
              <tr>
                <th>Categoría / Tipo de Equipo</th>
                <th class="text-center" style="width: 100px;">Cantidad A</th>
                <th class="text-center" style="width: 100px;">Cantidad B</th>
                <th class="text-center" style="width: 100px;">Diferencia</th>
              </tr>
            </thead>
            <tbody id="compare-inventory-tbody">
              <!-- Rendered via JS -->
            </tbody>
          </table>
        </div>

      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
  loadAttachments();
  
  <?php if (isset($_GET['compare_with'])): ?>
  // Auto compare if requested in URL
  setTimeout(function() {
    const compareWithId = <?= (int)$_GET['compare_with'] ?>;
    $('#compare-target-select').val(compareWithId);
    startComparison();
  }, 300);
  <?php endif; ?>
  
  // Custom file input label display
  $('#attachment_file').on('change', function() {
    const fileName = $(this).val().split('\\').pop();
    $(this).next('.custom-file-label').html(fileName || 'Elegir archivo...');
  });

  // Handle upload submit
  $('#upload-attachment-form').on('submit', function(e) {
    e.preventDefault();
    const btn = $('#btn-upload-file');
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Subiendo...');
    
    const formData = new FormData(this);
    $.ajax({
      url: 'api.php?action=upload_attachment',
      type: 'POST',
      data: formData,
      contentType: false,
      processData: false,
      success: function(res) {
        btn.prop('disabled', false).html('Subir');
        if (res.success) {
          toastr.success(res.message);
          $('#upload-attachment-form')[0].reset();
          $('#attachment_file').next('.custom-file-label').html('Elegir archivo...');
          loadAttachments();
        } else {
          toastr.error(res.message);
        }
      },
      error: function() {
        btn.prop('disabled', false).html('Subir');
        toastr.error('Error de red al subir el archivo.');
      }
    });
  });
});

function loadAttachments() {
  const container = $('#attachments-container');
  const quoteId = <?= $quote['id'] ?>;
  
  $.getJSON('api.php?action=get_attachments', { quote_id: quoteId }, function(res) {
    if (res.success) {
      if (res.data.length === 0) {
        container.html('<div class="text-center py-2 text-muted small"><i class="fas fa-folder-open mr-1"></i> Sin archivos adjuntos.</div>');
      } else {
        let html = '<ul class="list-group list-group-flush border rounded">';
        res.data.forEach(att => {
          const sizeKb = (parseInt(att.filesize) / 1024).toFixed(1);
          const icon = getFileIcon(att.filename);
          
          html += `
            <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 small">
              <div class="text-truncate" style="max-width: 240px;">
                <i class="${icon} text-muted mr-2"></i>
                <a href="../../${att.filepath}" target="_blank" title="Descargar ${escapeHtml(att.filename)}" class="font-weight-bold">${escapeHtml(att.filename)}</a>
                <div class="text-muted" style="font-size:0.7rem;">${sizeKb} KB - Subido: ${att.uploaded_at}</div>
              </div>
              <button class="btn btn-link text-danger p-0" onclick="deleteAttachment(${att.id})" title="Eliminar adjunto"><i class="fas fa-trash-alt"></i></button>
            </li>
          `;
        });
        html += '</ul>';
        container.html(html);
      }
    } else {
      container.html('<div class="text-danger text-center small">Error al cargar adjuntos.</div>');
    }
  });
}

function deleteAttachment(id) {
  Swal.fire({
    title: '¿Eliminar archivo?',
    text: "Esta acción no se puede deshacer.",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      $.post('api.php?action=delete_attachment', { id }, function(res) {
        if (res.success) {
          toastr.success(res.message);
          loadAttachments();
        } else {
          toastr.error(res.message);
        }
      });
    }
  });
}

function getFileIcon(filename) {
  const ext = filename.split('.').pop().toLowerCase();
  switch (ext) {
    case 'pdf': return 'far fa-file-pdf text-danger';
    case 'xlsx':
    case 'xls':
    case 'csv': return 'far fa-file-excel text-success';
    case 'docx':
    case 'doc': return 'far fa-file-word text-primary';
    case 'jpg':
    case 'jpeg':
    case 'png':
    case 'gif':
    case 'webp': return 'far fa-file-image text-info';
    case 'zip':
    case 'rar': return 'far fa-file-archive text-warning';
    default: return 'far fa-file';
  }
}

function escapeHtml(string) {
  return String(string || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// COMPARISON LOGIC
function startComparison() {
  const targetId = $('#compare-target-select').val();
  if (!targetId) {
    toastr.warning('Por favor seleccione una cotización para comparar.');
    return;
  }
  
  const currentId = <?= $quote['id'] ?>;
  
  // 1. Fetch current quote details
  $.getJSON('api.php?action=get_quote_detail', { id: currentId }, function(resCurrent) {
    if (!resCurrent.success) {
      toastr.error('Error al cargar cotización actual: ' + resCurrent.message);
      return;
    }
    
    // 2. Fetch target quote details
    $.getJSON('api.php?action=get_quote_detail', { id: targetId }, function(resTarget) {
      if (!resTarget.success) {
        toastr.error('Error al cargar cotización de destino: ' + resTarget.message);
        return;
      }
      
      renderQuoteDiff(resCurrent, resTarget);
    });
  });
}

function clearComparison() {
  $('#comparison-results-panel').addClass('d-none');
  $('#btn-clear-compare').addClass('d-none');
  // Scroll back to top
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function renderQuoteDiff(resA, resB) {
  const qA = resA.quote;
  const qB = resB.quote;
  const detA = resA.details;
  const detB = resB.details;
  
  // Set Quote B info labels
  $('#compare-ver-b-label').text('v' + qB.version);
  $('#compare-client-b').text(qB.cliente);
  $('#compare-contract-b').text(qB.contrato || '-');
  $('#compare-date-b').text(qB.fecha);
  $('#compare-status-b').html(qB.estado === 'Enviada' ? '<span class="badge badge-success">Enviada</span>' : '<span class="badge badge-warning">Borrador</span>');
  
  // Summary calculations
  const costA = parseFloat(qA.total_costo);
  const costB = parseFloat(qB.total_costo);
  const costDelta = costB - costA;
  
  const pvpA = parseFloat(qA.total_precio);
  const pvpB = parseFloat(qB.total_precio);
  const pvpDelta = pvpB - pvpA;
  
  const marginA = parseFloat(qA.margen_global);
  const marginB = parseFloat(qB.margen_global);
  const marginDelta = marginB - marginA;
  
  // Update UI Summary Table
  $('#diff-cost-a').text('$' + costA.toFixed(2));
  $('#diff-cost-b').text('$' + costB.toFixed(2));
  $('#diff-cost-delta').html(formatDeltaVal(costDelta, '$'));
  
  $('#diff-pvp-a').text('$' + pvpA.toFixed(2));
  $('#diff-pvp-b').text('$' + pvpB.toFixed(2));
  $('#diff-pvp-delta').html(formatDeltaVal(pvpDelta, '$'));
  
  $('#diff-margin-a').text(Math.round(marginA * 100) + '%');
  $('#diff-margin-b').text(Math.round(marginB * 100) + '%');
  $('#diff-margin-delta').html(formatDeltaVal(marginDelta * 100, '', '%'));
  
  // 1. COMPARE SERVICE LINES
  // We match lines by (seccion + codigo_unico) or if code is null, by (seccion + marca_categoria + actividad + detalle)
  const mapKey = (item) => {
    if (item.codigo_unico) {
      return item.seccion + '||' + item.codigo_unico;
    }
    return item.seccion + '||' + item.marca_categoria + '||' + item.actividad + '||' + item.detalle;
  };
  
  const dictA = {};
  detA.forEach(item => {
    dictA[mapKey(item)] = item;
  });
  
  const dictB = {};
  detB.forEach(item => {
    dictB[mapKey(item)] = item;
  });
  
  const allKeys = new Set([...Object.keys(dictA), ...Object.keys(dictB)]);
  
  let lineHtml = '';
  
  // Convert set to array and sort to keep sections grouped
  const sortedKeys = [...allKeys].sort((a, b) => {
    const getSecOrder = (key) => {
      const sec = key.split('||')[0];
      if (sec === 'Implementacion') return 1;
      if (sec === 'MantPrev') return 2;
      if (sec === 'MantCorr') return 3;
      return 4;
    };
    const orderA = getSecOrder(a);
    const orderB = getSecOrder(b);
    if (orderA !== orderB) return orderA - orderB;
    return a.localeCompare(b);
  });
  
  if (sortedKeys.length === 0) {
    lineHtml = '<tr><td colspan="13" class="text-center text-muted">No hay líneas de servicios para comparar.</td></tr>';
  } else {
    sortedKeys.forEach(key => {
      const itemA = dictA[key];
      const itemB = dictB[key];
      
      let secName = '';
      let code = '';
      let activity = '';
      let detail = '';
      let specialist = '';
      let multA = '-', multB = '-';
      let hLabA = 0, hLabB = 0;
      let hExtA = 0, hExtB = 0;
      let costUnitA = 0, costUnitB = 0;
      let pvpUnitA = 0, pvpUnitB = 0;
      let totalPvpA = 0, totalPvpB = 0;
      
      let rowClass = '';
      
      if (itemA && !itemB) {
        // Removed in B
        rowClass = 'diff-removed';
        secName = itemA.seccion;
        code = itemA.codigo_unico || '-';
        activity = itemA.marca_categoria + ' / ' + itemA.actividad;
        detail = itemA.detalle;
        specialist = itemA.especialista_nivel;
        multA = itemA.multiplier_type;
        hLabA = parseFloat(itemA.horas_laborables);
        hExtA = parseFloat(itemA.horas_no_laborables_50) + parseFloat(itemA.horas_no_laborables_100);
        costUnitA = parseFloat(itemA.costo_hora);
        pvpUnitA = parseFloat(itemA.pvp_hora);
        totalPvpA = parseFloat(itemA.pvp_total);
      } else if (!itemA && itemB) {
        // Added in B
        rowClass = 'diff-added';
        secName = itemB.seccion;
        code = itemB.codigo_unico || '-';
        activity = itemB.marca_categoria + ' / ' + itemB.actividad;
        detail = itemB.detalle;
        specialist = itemB.especialista_nivel;
        multB = itemB.multiplier_type;
        hLabB = parseFloat(itemB.horas_laborables);
        hExtB = parseFloat(itemB.horas_no_laborables_50) + parseFloat(itemB.horas_no_laborables_100);
        costUnitB = parseFloat(itemB.costo_hora);
        pvpUnitB = parseFloat(itemB.pvp_hora);
        totalPvpB = parseFloat(itemB.pvp_total);
      } else {
        // Exists in both: Check if modified
        secName = itemA.seccion;
        code = itemA.codigo_unico || '-';
        activity = itemA.marca_categoria + ' / ' + itemA.actividad;
        detail = itemA.detalle;
        specialist = itemA.especialista_nivel;
        multA = itemA.multiplier_type;
        multB = itemB.multiplier_type;
        hLabA = parseFloat(itemA.horas_laborables);
        hLabB = parseFloat(itemB.horas_laborables);
        hExtA = parseFloat(itemA.horas_no_laborables_50) + parseFloat(itemA.horas_no_laborables_100);
        hExtB = parseFloat(itemB.horas_no_laborables_50) + parseFloat(itemB.horas_no_laborables_100);
        costUnitA = parseFloat(itemA.costo_hora);
        costUnitB = parseFloat(itemB.costo_hora);
        pvpUnitA = parseFloat(itemA.pvp_hora);
        pvpUnitB = parseFloat(itemB.pvp_hora);
        totalPvpA = parseFloat(itemA.pvp_total);
        totalPvpB = parseFloat(itemB.pvp_total);
        
        const isModified = (hLabA !== hLabB) || (hExtA !== hExtB) || (costUnitA !== costUnitB) || (pvpUnitA !== pvpUnitB) || (multA !== multB) || (specialist !== itemB.especialista_nivel);
        if (isModified) {
          rowClass = 'diff-modified';
        }
      }
      
      const deltaTotal = totalPvpB - totalPvpA;
      
      // Formatting helpers for diff cells
      const formatDiffCell = (valA, valB, isPrice = false) => {
        if (valA === valB) return isPrice ? '$' + valA.toFixed(2) : valA;
        return `
          <span class="text-muted small">${isPrice ? '$' : ''}${isPrice ? valA.toFixed(2) : valA}</span> &rarr;
          <span class="diff-highlight">${isPrice ? '$' : ''}${isPrice ? valB.toFixed(2) : valB}</span>
        `;
      };
      
      const formatMultDiff = (mA, mB) => {
        if (mA === mB) return mA;
        return `<span class="text-muted small">${mA}</span> &rarr; <span class="diff-highlight">${mB}</span>`;
      };
      
      lineHtml += `
        <tr class="${rowClass}">
          <td><span class="small font-weight-bold text-uppercase">${secName.substring(0, 10)}</span></td>
          <td><code>${code}</code></td>
          <td><strong>${activity}</strong></td>
          <td style="max-width: 250px;" class="small">${detail}</td>
          <td>${itemA && itemB && specialist !== itemB.especialista_nivel ? `<span class="text-muted">${specialist}</span>&rarr;<span class="diff-highlight">${itemB.especialista_nivel}</span>` : specialist}</td>
          <td>${formatMultDiff(multA, multB)}</td>
          <td class="text-center">${formatDiffCell(hLabA, hLabB)}</td>
          <td class="text-center">${formatDiffCell(hExtA, hExtB)}</td>
          <td class="text-right">${formatDiffCell(costUnitA, costUnitB, true)}</td>
          <td class="text-right">${formatDiffCell(pvpUnitA, pvpUnitB, true)}</td>
          <td class="text-right">$${totalPvpA.toFixed(2)}</td>
          <td class="text-right font-weight-bold">$${totalPvpB.toFixed(2)}</td>
          <td class="text-right font-weight-bold">${formatDeltaVal(deltaTotal, '$')}</td>
        </tr>
      `;
    });
  }
  $('#compare-lines-tbody').html(lineHtml);
  
  // 2. COMPARE INVENTORY MULTIPLIERS
  const jsonA = JSON.parse(qA.adicionales_json || '{}');
  const jsonB = JSON.parse(qB.adicionales_json || '{}');
  const eqA = jsonA.eq_rows || [];
  const eqB = jsonB.eq_rows || [];
  
  const invMap = {};
  eqA.forEach(row => {
    invMap[row.type] = { type: row.type, qtyA: parseInt(row.qty), qtyB: 0 };
  });
  
  eqB.forEach(row => {
    if (invMap[row.type]) {
      invMap[row.type].qtyB = parseInt(row.qty);
    } else {
      invMap[row.type] = { type: row.type, qtyA: 0, qtyB: parseInt(row.qty) };
    }
  });
  
  let invHtml = '';
  const invTypes = Object.keys(invMap);
  if (invTypes.length === 0) {
    invHtml = '<tr><td colspan="4" class="text-center text-muted">No hay inventario definido para comparar.</td></tr>';
  } else {
    invTypes.forEach(type => {
      const row = invMap[type];
      const deltaQty = row.qtyB - row.qtyA;
      let rowClass = '';
      if (row.qtyA === 0 && row.qtyB > 0) rowClass = 'diff-added';
      else if (row.qtyA > 0 && row.qtyB === 0) rowClass = 'diff-removed';
      else if (deltaQty !== 0) rowClass = 'diff-modified';
      
      invHtml += `
        <tr class="${rowClass}">
          <td><strong>${type}</strong></td>
          <td class="text-center">${row.qtyA}</td>
          <td class="text-center">${row.qtyB}</td>
          <td class="text-center font-weight-bold">${formatDeltaVal(deltaQty, '', '', true)}</td>
        </tr>
      `;
    });
  }
  $('#compare-inventory-tbody').html(invHtml);
  
  // Show Comparison Results Container
  $('#comparison-results-panel').removeClass('d-none');
  $('#btn-clear-compare').removeClass('d-none');
  
  // Scroll smoothly to the comparison results
  document.getElementById('comparison-results-panel').scrollIntoView({ behavior: 'smooth' });
}

function formatDeltaVal(val, prefix = '', suffix = '', zeroAsDash = false) {
  if (val === 0) return zeroAsDash ? '-' : '<span class="text-muted">0</span>';
  const formatted = Math.abs(val).toFixed(2).replace('.00', '');
  if (val > 0) {
    return `<span class="text-success"><i class="fas fa-arrow-up mr-1 small"></i>+${prefix}${formatted}${suffix}</span>`;
  } else {
    return `<span class="text-danger"><i class="fas fa-arrow-down mr-1 small"></i>-${prefix}${formatted}${suffix}</span>`;
  }
}

function convertToProject(id) {
  Swal.fire({
    title: '¿Traspasar a Proyecto?',
    text: "Se creará un nuevo proyecto en el módulo de Proyectos con todos sus hitos y tareas internas, y el presupuesto correspondiente. Esta acción no se puede deshacer.",
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Sí, crear proyecto',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      Swal.fire({
        title: 'Procesando...',
        html: 'Creando proyecto y tareas...',
        allowOutsideClick: false,
        didOpen: () => {
          Swal.showLoading();
        }
      });
      
      $.post('api.php?action=convert_to_project', { id: id }, function(res) {
        Swal.close();
        if (res.success) {
          Swal.fire({
            title: '¡Proyecto Creado!',
            text: res.message,
            icon: 'success',
            showCancelButton: true,
            confirmButtonText: 'Ir a Proyectos',
            cancelButtonText: 'Permanecer aquí'
          }).then((r) => {
            if (r.isConfirmed) {
              window.open('../project.php?id=' + res.project_id, '_blank');
            }
          });
        } else {
          Swal.fire('Error', res.message || 'No se pudo realizar el traspaso.', 'error');
        }
      }, 'json').fail(function() {
        Swal.close();
        Swal.fire('Error', 'Error de comunicación con el servidor.', 'error');
      });
    }
  });
}
</script>

<?php
include '../partials/footer.php';
?>

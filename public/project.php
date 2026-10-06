<?php
/**
 * Módulo de Gestión de Proyectos - CMDB VILASECA
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/permissions_helper.php';
require_once __DIR__ . '/../src/helpers.php';

// Validar login e inicio de sesión
require_login();
if (!has_module_access('project')) {
    header("Location: dashboard.php");
    exit();
}

$page_title = "Gestión de Proyectos";
include 'partials/header.php';

$pdo = getPDO();
// Cargar los clientes (CI instances) para el selector
$clients = $pdo->query("SELECT id, hostname, ci_unique FROM ci_instances ORDER BY hostname ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Custom CSS for the Premium Project Module -->
<style>
/* Custom project variables */
:root {
  --milestone-color: #ffd700;
  --task-new: #6c757d;
  --task-sched: #007bff;
  --task-prog: #ff5c05;
  --task-closed: #28a745;
}

/* WBS and Gantt Split Layout */
.project-gantt-wrapper {
  display: flex;
  flex-direction: row;
  border: 1px solid var(--border-color);
  border-radius: 12px;
  background-color: var(--card-bg);
  overflow: hidden;
  margin-top: 15px;
  box-shadow: 0 4px 15px rgba(0,0,0,0.05);
}

.wbs-panel {
  width: 60%;
  overflow-x: auto;
  border-right: 1px solid var(--border-color);
}

.gantt-panel {
  width: 40%;
  overflow-x: auto;
  background-color: rgba(0,0,0,0.015);
  position: relative;
  min-width: 300px;
}
body.dark-mode .gantt-panel {
  background-color: rgba(255,255,255,0.015);
}

/* Row Alignment */
.wbs-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 0;
}
.wbs-table th {
  height: 50px;
  padding: 8px 12px;
  background-color: var(--table-header-bg) !important;
  color: var(--table-header-color) !important;
  font-size: 0.85rem;
  text-transform: uppercase;
  font-weight: 700;
  border-bottom: 2px solid var(--border-color);
  white-space: nowrap;
}
.wbs-table td {
  height: 54px;
  padding: 8px 12px;
  border-bottom: 1px solid var(--border-color);
  vertical-align: middle;
  font-size: 0.9rem;
}

/* Gantt Chart Styling */
.gantt-timeline-header {
  height: 80px;
  border-bottom: 2px solid var(--border-color);
  position: relative;
  display: flex;
  flex-direction: column;
  background-color: var(--sonda-navy);
  color: #fff;
}
.gantt-header-row {
  display: flex;
  width: 100%;
  flex-shrink: 0;
}
.gantt-header-row.months-row {
  height: 30px;
  border-bottom: 1px solid rgba(255,255,255,0.15);
  background-color: #0c1c2e;
}
.gantt-header-row.weeks-row {
  height: 26px;
  border-bottom: 1px solid rgba(255,255,255,0.15);
  background-color: #122840;
}
.gantt-header-row.days-row {
  height: 24px;
  background-color: #173352;
}
.gantt-header-cell {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  white-space: nowrap;
  border-right: 1px solid rgba(255,255,255,0.15);
  color: #fff !important;
}
.gantt-grid {
  position: absolute;
  top: 80px;
  left: 0;
  width: 100%;
  height: calc(100% - 80px);
  display: flex;
  pointer-events: none;
  z-index: 1;
}
.gantt-grid-column {
  flex-shrink: 0;
  border-right: 1px dashed var(--border-color);
  height: 100%;
}
.gantt-rows-container {
  position: relative;
  z-index: 2;
}
.gantt-row {
  border-bottom: 1px solid var(--border-color);
  display: flex;
  align-items: center;
  position: relative;
  box-sizing: border-box;
}
.gantt-bar {
  position: absolute;
  height: 24px;
  border-radius: 6px;
  background-color: var(--sonda-orange);
  box-shadow: 0 2px 5px rgba(0,0,0,0.15);
  display: flex;
  align-items: center;
  transition: all 0.3s ease;
  overflow: hidden;
  border: 1px solid rgba(0,0,0,0.1);
}
.gantt-bar-progress {
  height: 100%;
  background-color: var(--sonda-cyan);
  position: absolute;
  left: 0;
  top: 0;
  z-index: 1;
}
.gantt-bar-label {
  position: relative;
  z-index: 2;
  padding: 0 8px;
  font-size: 0.7rem;
  color: #fff;
  font-weight: 700;
  white-space: nowrap;
  text-shadow: 0 1px 2px rgba(0,0,0,0.6);
}

/* WBS Row Levels Indentations */
.row-level-1 { background-color: rgba(16, 27, 49, 0.05); font-weight: 700; }
.row-level-2 { background-color: rgba(0, 184, 212, 0.03); font-weight: 600; }
.row-level-3 { }
.row-level-4 { font-style: italic; color: #555; background-color: rgba(0,0,0,0.01); }

body.dark-mode .row-level-1 { background-color: rgba(255, 255, 255, 0.04); }
body.dark-mode .row-level-2 { background-color: rgba(0, 184, 212, 0.08); }
body.dark-mode .row-level-4 { color: #aaa; background-color: rgba(255,255,255,0.01); }

.indent-1 { padding-left: 8px !important; }
.indent-2 { padding-left: 25px !important; }
.indent-3 { padding-left: 45px !important; }
.indent-4 { padding-left: 65px !important; }

/* Gantt Bar Types styling */
.gantt-bar.project-bar {
  background-color: var(--sonda-navy);
  border-radius: 0;
  height: 8px;
  border: none;
}
body.dark-mode .gantt-bar.project-bar {
  background-color: #fff;
}
.gantt-bar.milestone-diamond {
  background-color: var(--milestone-color);
  width: 14px !important;
  height: 14px !important;
  border-radius: 0;
  transform: rotate(45deg);
  box-shadow: 0 2px 4px rgba(0,0,0,0.25);
  border: 1px solid #d4af37;
}

/* Kanban Board Styling */
.kanban-board {
  display: flex;
  gap: 15px;
  overflow-x: auto;
  padding: 15px 0;
}
.kanban-col {
  flex: 1;
  min-width: 250px;
  background-color: rgba(16, 27, 49, 0.03);
  border-radius: 12px;
  padding: 15px;
  display: flex;
  flex-direction: column;
  border: 1px solid var(--border-color);
  transition: all 0.3s ease;
}
body.dark-mode .kanban-col {
  background-color: rgba(255, 255, 255, 0.02);
}
.kanban-col-header {
  font-weight: 700;
  font-size: 1rem;
  margin-bottom: 15px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-bottom: 8px;
  border-bottom: 2px solid var(--border-color);
}
.kanban-col-header .badge {
  font-size: 0.8rem;
  border-radius: 20px;
  padding: 4px 10px;
}
.kanban-cards {
  flex-grow: 1;
  min-height: 250px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.kanban-card {
  background-color: var(--card-bg);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  padding: 12px;
  box-shadow: 0 2px 5px rgba(0,0,0,0.05);
  cursor: grab;
  transition: transform 0.2s, box-shadow 0.2s;
  user-select: none;
}
.kanban-card:active {
  cursor: grabbing;
}
.kanban-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 10px rgba(0,0,0,0.1);
  border-color: var(--sonda-cyan);
}
.kanban-card-title {
  font-weight: 600;
  font-size: 0.95rem;
  margin-bottom: 8px;
}
.kanban-card-meta {
  font-size: 0.75rem;
  display: flex;
  justify-content: space-between;
  color: var(--text-muted);
  margin-top: 8px;
}

/* Drag Over Animation */
.kanban-col.drag-over {
  background-color: rgba(0, 184, 212, 0.08);
  border-color: var(--sonda-cyan);
}

/* Collapsible Row Animation */
.row-collapsed {
  display: none !important;
}

.toggle-arrow {
  cursor: pointer;
  margin-right: 6px;
  transition: transform 0.2s;
}
.toggle-arrow.collapsed {
  transform: rotate(-90deg);
}

.priority-badge {
  font-size: 0.7rem;
  text-transform: uppercase;
  font-weight: 700;
}
</style>

<div class="row" id="projects-list-section">
  <!-- Section 1: Project List -->
  <div class="col-12">
    <div class="card card-primary card-outline">
      <div class="card-header d-flex align-items-center">
        <h3 class="card-title"><i class="fas fa-briefcase mr-1 text-primary"></i> Proyectos CMDB Internos</h3>
        <div class="card-tools ml-auto">
          <button class="btn btn-primary btn-sm" onclick="showProjectModal()">
            <i class="fas fa-plus mr-1"></i> Crear Proyecto
          </button>
        </div>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover table-striped mb-0">
            <thead>
              <tr>
                <th>Código</th>
                <th>Nombre del Proyecto</th>
                <th>Cliente (CI)</th>
                <th>Monto</th>
                <th>F. Inicio</th>
                <th>F. Fin</th>
                <th>Progreso</th>
                <th class="text-right">Acciones</th>
              </tr>
            </thead>
            <tbody id="projects-table-body">
              <tr>
                <td colspan="8" class="text-center py-4">
                  <i class="fas fa-spinner fa-spin mr-1"></i> Cargando proyectos...
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Section 2: Detailed Project Board -->
<div class="row d-none" id="project-detail-section">
  <div class="col-12">
    <div class="card card-primary card-outline">
      <div class="card-header d-flex align-items-center">
        <button class="btn btn-outline-secondary btn-sm mr-3" onclick="hideProjectDetails()">
          <i class="fas fa-arrow-left mr-1"></i> Volver
        </button>
        <h3 class="card-title font-weight-bold" id="detail-project-title">proj-01: Carga de Proyecto</h3>
        <span class="badge badge-info ml-3" id="detail-project-client">Cliente</span>
        
        <div class="card-tools ml-auto">
          <button class="btn btn-success btn-sm" onclick="showMilestoneModal()">
            <i class="fas fa-flag mr-1"></i> Agregar Hito
          </button>
          <button class="btn btn-info btn-sm ml-1" onclick="showApplyTemplateModal()">
            <i class="fas fa-file-import mr-1"></i> Cargar Hito Plantilla
          </button>
        </div>
      </div>
      
      <!-- Nav Tabs -->
      <div class="card-body p-0 border-bottom">
        <ul class="nav nav-tabs px-3 pt-2" id="project-detail-tabs" role="tablist">
          <li class="nav-item">
            <a class="nav-link active" id="tab-wbs-link" data-toggle="tab" href="#tab-wbs" role="tab"><i class="fas fa-stream mr-1"></i> EDT / Gantt</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" id="tab-kanban-link" data-toggle="tab" href="#tab-kanban" role="tab"><i class="fas fa-columns mr-1"></i> Tablero Kanban</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" id="tab-info-link" data-toggle="tab" href="#tab-info" role="tab"><i class="fas fa-info-circle mr-1"></i> Ficha Técnica</a>
          </li>
        </ul>
      </div>
      
      <!-- Tab Contents -->
      <div class="card-body p-3">
        <div class="tab-content">
          
          <!-- Tab 1: WBS & Gantt -->
          <div class="tab-pane fade show active" id="tab-wbs" role="tabpanel">
            <div class="project-gantt-wrapper">
              
              <!-- Left: WBS Hierarchical Grid -->
              <div class="wbs-panel">
                <table class="wbs-table">
                  <thead>
                    <tr>
                      <th style="width: 220px;">Código / Nombre</th>
                      <th>Encargado</th>
                      <th>Progreso</th>
                      <th>Prioridad</th>
                      <th>Inicio / Fin</th>
                      <th style="width: 140px;" class="text-right">Acciones</th>
                    </tr>
                  </thead>
                  <tbody id="wbs-table-body">
                    <!-- Dinámico -->
                  </tbody>
                </table>
              </div>
              
              <!-- Right: Interactive Gantt Chart Timeline -->
              <div class="gantt-panel" id="gantt-chart-container">
                <!-- Timeline Header (populated dynamically) -->
                <div class="gantt-timeline-header" id="gantt-timeline-header">
                  <!-- Weeks generated in JS -->
                </div>
                <!-- Vertical Grid lines (populated dynamically) -->
                <div class="gantt-grid" id="gantt-grid-lines">
                  <!-- Columns generated in JS -->
                </div>
                <!-- Rows matching WBS rows (populated dynamically) -->
                <div class="gantt-rows-container" id="gantt-rows-container">
                  <!-- Gantt bars generated in JS -->
                </div>
              </div>
              
            </div>
          </div>
          
          <!-- Tab 2: Kanban Action Board -->
          <div class="tab-pane fade" id="tab-kanban" role="tabpanel">
            <div class="alert alert-info py-2" id="kanban-help-banner">
              <i class="fas fa-info-circle mr-1"></i> Mueva las tarjetas entre columnas para actualizar sus estados. Las tareas completadas se recalculan en la barra de progreso del hito.
            </div>
            
            <div class="kanban-board">
              <!-- Column 1: New -->
              <div class="kanban-col" id="col-New" ondragover="allowDrop(event)" ondragleave="dragLeaveCol(event)" ondrop="dropTask(event, 'New')">
                <div class="kanban-col-header">
                  <span>Nuevas</span>
                  <span class="badge bg-secondary" id="count-New">0</span>
                </div>
                <div class="kanban-cards" id="cards-New"></div>
              </div>
              
              <!-- Column 2: Scheduled -->
              <div class="kanban-col" id="col-Scheduled" ondragover="allowDrop(event)" ondragleave="dragLeaveCol(event)" ondrop="dropTask(event, 'Scheduled')">
                <div class="kanban-col-header">
                  <span>Planificadas</span>
                  <span class="badge bg-primary" id="count-Scheduled">0</span>
                </div>
                <div class="kanban-cards" id="cards-Scheduled"></div>
              </div>
              
              <!-- Column 3: In progress -->
              <div class="kanban-col" id="col-In-progress" ondragover="allowDrop(event)" ondragleave="dragLeaveCol(event)" ondrop="dropTask(event, 'In progress')">
                <div class="kanban-col-header">
                  <span>En Progreso</span>
                  <span class="badge bg-warning text-dark" id="count-In-progress">0</span>
                </div>
                <div class="kanban-cards" id="cards-In-progress"></div>
              </div>
              
              <!-- Column 4: Closed -->
              <div class="kanban-col" id="col-Closed" ondragover="allowDrop(event)" ondragleave="dragLeaveCol(event)" ondrop="dropTask(event, 'Closed')">
                <div class="kanban-col-header">
                  <span>Cerradas</span>
                  <span class="badge bg-success" id="count-Closed">0</span>
                </div>
                <div class="kanban-cards" id="cards-Closed"></div>
              </div>
            </div>
          </div>
          
          <!-- Tab 3: General Info -->
          <div class="tab-pane fade" id="tab-info" role="tabpanel">
            <div class="row">
              <div class="col-md-6">
                <div class="card p-3 h-100 shadow-none border">
                  <h5 class="text-primary border-bottom pb-2 font-weight-bold"><i class="fas fa-receipt mr-1"></i> Detalles Financieros y Operativos</h5>
                  <table class="table table-sm table-borderless mt-2">
                    <tr>
                      <th style="width: 150px;">Monto del Proyecto:</th>
                      <td><strong class="text-success" id="info-amount">$0.00</strong></td>
                    </tr>
                    <tr>
                      <th>Tipo de Jornada:</th>
                      <td><span class="badge badge-info" id="info-work-type">-</span></td>
                    </tr>
                    <tr>
                      <th>Días Laborales:</th>
                      <td id="info-working-days">-</td>
                    </tr>
                    <tr>
                      <th>Personal Asignado:</th>
                      <td id="info-personnel">-</td>
                    </tr>
                  </table>
                </div>
              </div>
              <div class="col-md-6">
                <div class="card p-3 h-100 shadow-none border">
                  <h5 class="text-primary border-bottom pb-2 font-weight-bold"><i class="fas fa-calendar-alt mr-1"></i> Fechas del Ciclo de Vida</h5>
                  <table class="table table-sm table-borderless mt-2">
                    <tr>
                      <th style="width: 150px;">Fecha de Inicio:</th>
                      <td id="info-start-date">-</td>
                    </tr>
                    <tr>
                      <th>Fecha de Fin:</th>
                      <td id="info-end-date">-</td>
                    </tr>
                    <tr>
                      <th>Fecha de Ejecución:</th>
                      <td id="info-exec-date">-</td>
                    </tr>
                  </table>
                </div>
              </div>
            </div>
          </div>
          
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ================= MODALS ================= -->

<!-- Modal: Proyecto -->
<div class="modal fade" id="projectModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title font-weight-bold" id="projectModalTitle"><i class="fas fa-briefcase mr-1"></i> Proyecto</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form id="projectForm">
        <div class="modal-body">
          <input type="hidden" name="id" id="projectId">
          
          <div class="row">
            <div class="col-md-6 form-group">
              <label>Nombre del Proyecto *</label>
              <input type="text" name="name" id="projectName" class="form-control" required placeholder="Escriba el nombre del proyecto...">
            </div>
            <div class="col-md-6 form-group">
              <label>Cliente (CI) *</label>
              <div class="input-group">
                <select name="client_ci_id" id="projectClientCi" class="form-control" required>
                  <option value="">-- Seleccionar Cliente --</option>
                  <?php foreach ($clients as $c): ?>
                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['hostname'] . ' (' . $c['ci_unique'] . ')'); ?></option>
                  <?php endforeach; ?>
                </select>
                <div class="input-group-append">
                  <button type="button" class="btn btn-outline-success" onclick="showQuickCreateClientModal()" title="Registrar Nuevo Cliente en la CMDB">
                    <i class="fas fa-plus mr-1"></i> Nuevo
                  </button>
                </div>
              </div>
            </div>
          </div>
          
          <div class="row">
            <div class="col-md-4 form-group">
              <label>Fecha Inicio *</label>
              <input type="date" name="start_date" id="projectStartDate" class="form-control" required>
            </div>
            <div class="col-md-4 form-group">
              <label>Fecha Fin *</label>
              <input type="date" name="end_date" id="projectEndDate" class="form-control" required>
            </div>
            <div class="col-md-4 form-group">
              <label>Fecha Ejecución</label>
              <input type="date" name="execution_date" id="projectExecDate" class="form-control">
            </div>
          </div>
          
          <div class="row">
            <div class="col-md-6 form-group">
              <label>Monto / Presupuesto ($)</label>
              <input type="number" step="0.01" name="amount" id="projectAmount" class="form-control" placeholder="0.00">
            </div>
            <div class="col-md-6 form-group">
              <label>Tipo de Trabajo</label>
              <select name="work_type" id="projectWorkType" class="form-control">
                <option value="horas normales">Horas Normales</option>
                <option value="horas suplementarias">Horas Suplementarias</option>
                <option value="horas extras">Horas Extras</option>
              </select>
            </div>
          </div>
          
          <div class="form-group">
            <label>Personal Asignado</label>
            <input type="text" name="assigned_personnel" id="projectPersonnel" class="form-control" placeholder="Ej. Juan Pérez, María Gómez (separados por coma)">
          </div>
          
          <div class="form-group">
            <label>Días Laborales</label>
            <div class="d-flex flex-wrap gap-3">
              <?php 
              $days = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
              foreach ($days as $day): 
              ?>
                <div class="custom-control custom-checkbox mr-3">
                  <input type="checkbox" class="custom-control-input project-day-cb" id="day_<?php echo $day; ?>" value="<?php echo $day; ?>" checked>
                  <label class="custom-control-label" for="day_<?php echo $day; ?>"><?php echo $day; ?></label>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar Proyecto</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Hito -->
<div class="modal fade" id="milestoneModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title font-weight-bold" id="milestoneModalTitle"><i class="fas fa-flag mr-1"></i> Hito</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form id="milestoneForm">
        <div class="modal-body">
          <input type="hidden" name="project_id" id="milestoneProjectId">
          <input type="hidden" name="id" id="milestoneId">
          
          <div class="form-group">
            <label>Nombre del Hito *</label>
            <input type="text" name="name" id="milestoneName" class="form-control" required placeholder="Ej. Instalación de Servidor...">
          </div>
          
          <div class="row">
            <div class="col-md-6 form-group">
              <label>Prioridad</label>
              <select name="priority" id="milestonePriority" class="form-control">
                <option value="Baja">Baja</option>
                <option value="Media" selected>Media</option>
                <option value="Alta">Alta</option>
              </select>
            </div>
            <div class="col-md-6 form-group">
              <label>Importancia</label>
              <select name="importance" id="milestoneImportance" class="form-control">
                <option value="Baja">Baja</option>
                <option value="Media" selected>Media</option>
                <option value="Alta">Alta</option>
              </select>
            </div>
          </div>
          
          <div class="row">
            <div class="col-md-6 form-group">
              <label>Fecha Inicio Estimada *</label>
              <input type="datetime-local" name="estimated_start_date" id="milestoneEstStart" class="form-control" required>
            </div>
            <div class="col-md-6 form-group">
              <label>Fecha Fin Estimada *</label>
              <input type="datetime-local" name="estimated_end_date" id="milestoneEstEnd" class="form-control" required>
            </div>
          </div>
          
          <div class="row">
            <div class="col-md-6 form-group">
              <label>Fecha Inicio Real</label>
              <input type="datetime-local" name="real_start_date" id="milestoneRealStart" class="form-control">
            </div>
            <div class="col-md-6 form-group">
              <label>Fecha Fin Real</label>
              <input type="datetime-local" name="real_end_date" id="milestoneRealEnd" class="form-control">
            </div>
          </div>
          
          <div class="form-group">
            <label>Tiempo Ejecución Promedio (horas)</label>
            <input type="number" name="average_execution_time" id="milestoneAvgTime" class="form-control" value="0">
          </div>
          
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-success">Guardar Hito</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Tarea / Requerimiento -->
<div class="modal fade" id="taskModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title font-weight-bold" id="taskModalTitle"><i class="fas fa-check-square mr-1"></i> Tarea / Requerimiento</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form id="taskForm">
        <div class="modal-body">
          <input type="hidden" name="milestone_id" id="taskMilestoneId">
          <input type="hidden" name="id" id="taskId">
          
          <div class="form-group">
            <label>Título de la Tarea *</label>
            <input type="text" name="title" id="taskTitle" class="form-control" required placeholder="Ej. Revisión del equipo...">
          </div>
          
          <div class="row">
            <div class="col-md-6 form-group">
              <label>Persona Asignada</label>
              <input type="text" name="assigned_person" id="taskAssignedPerson" class="form-control" placeholder="Nombre del responsable...">
            </div>
            <div class="col-md-6 form-group">
              <label>Estado de Ejecución</label>
              <select name="status" id="taskStatus" class="form-control">
                <option value="New">Nueva</option>
                <option value="Scheduled">Planificada</option>
                <option value="In progress">En Progreso</option>
                <option value="Closed">Cerrada</option>
              </select>
            </div>
          </div>
          
          <div class="row">
            <div class="col-md-6 form-group">
              <label>Fecha Inicio Estimada *</label>
              <input type="datetime-local" name="estimated_start_date" id="taskEstStart" class="form-control" required>
            </div>
            <div class="col-md-6 form-group">
              <label>Fecha Fin Estimada *</label>
              <input type="datetime-local" name="estimated_end_date" id="taskEstEnd" class="form-control" required>
            </div>
          </div>
          
          <div class="row">
            <div class="col-md-6 form-group">
              <label>Fecha Inicio Real</label>
              <input type="datetime-local" name="real_start_date" id="taskRealStart" class="form-control">
            </div>
            <div class="col-md-6 form-group">
              <label>Fecha Fin Real</label>
              <input type="datetime-local" name="real_end_date" id="taskRealEnd" class="form-control">
            </div>
          </div>
          
          <div class="row">
            <div class="col-md-6 form-group">
              <label>Prioridad</label>
              <select name="priority" id="taskPriority" class="form-control">
                <option value="Baja">Baja</option>
                <option value="Media" selected>Media</option>
                <option value="Alta">Alta</option>
              </select>
            </div>
            <div class="col-md-6 form-group">
              <label>Importancia</label>
              <select name="importance" id="taskImportance" class="form-control">
                <option value="Baja">Baja</option>
                <option value="Media" selected>Media</option>
                <option value="Alta">Alta</option>
              </select>
            </div>
          </div>
          
          <div class="row">
            <div class="col-md-6 form-group">
              <label>Porcentaje Finalización (0 - 100 %)</label>
              <div class="d-flex align-items-center">
                <input type="range" class="custom-range flex-grow-1" min="0" max="100" id="taskProgressRange" oninput="$('#taskProgressText').val(this.value)">
                <input type="number" name="progress_percentage" id="taskProgressText" class="form-control ml-3" style="width: 80px;" min="0" max="100" value="0" oninput="$('#taskProgressRange').val(this.value)">
                <span class="ml-2 font-weight-bold">%</span>
              </div>
            </div>
            <div class="col-md-6 form-group">
              <label>Tiempo Ejecución Promedio (horas)</label>
              <input type="number" step="0.1" name="average_execution_time" id="taskAvgTime" class="form-control" value="0">
            </div>
          </div>
          
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-info">Guardar Tarea</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Observación -->
<div class="modal fade" id="obsModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title font-weight-bold" id="obsModalTitle"><i class="fas fa-comment-alt mr-1"></i> Agregar Observación</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form id="obsForm">
        <div class="modal-body">
          <input type="hidden" name="milestone_id" id="obsMilestoneId">
          <input type="hidden" name="task_id" id="obsTaskId">
          <input type="hidden" name="id" id="obsId">
          
          <div class="form-group">
            <label>Observación / Comentario *</label>
            <textarea name="comment" id="obsComment" rows="4" class="form-control" required placeholder="Escriba aquí los detalles u observaciones..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-dark">Guardar Observación</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Cargar Hito desde Plantilla -->
<div class="modal fade" id="applyTemplateModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title font-weight-bold"><i class="fas fa-file-import mr-1"></i> Cargar Hito desde Plantilla</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form id="applyTemplateForm">
        <div class="modal-body">
          <input type="hidden" name="project_id" id="applyTplProjectId">
          
          <div class="form-group">
            <label>Seleccionar Hito Preconfigurado (Plantilla) *</label>
            <select name="template_id" id="applyTplSelect" class="form-control" required>
              <!-- Dinámico -->
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-info">Aplicar Plantilla</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include 'partials/footer.php'; ?>

<!-- Javascript Logic -->
<script>
let currentProjectId = 0;
let currentProjectData = null;
let collapsedMilestones = new Set();

$(function() {
    loadProjects();
    
    // Bind Project Form Submit
    $('#projectForm').on('submit', function(e) {
        e.preventDefault();
        
        // Colectar días laborales
        let days = [];
        $('.project-day-cb:checked').each(function() {
            days.push($(this).val());
        });
        
        let formData = $(this).serialize() + '&action=save_project&working_days=' + days.join(',');
        
        $.post('api_project.php', formData, function(res) {
            if (res.success) {
                Swal.fire('Éxito', 'Proyecto guardado con éxito', 'success');
                $('#projectModal').modal('hide');
                loadProjects();
                if (currentProjectId > 0 && currentProjectId == res.project_id) {
                    loadProjectDetails(currentProjectId);
                }
            } else {
                Swal.fire('Error', res.error, 'error');
            }
        });
    });
    
    // Bind Milestone Form Submit
    $('#milestoneForm').on('submit', function(e) {
        e.preventDefault();
        let formData = $(this).serialize() + '&action=save_milestone';
        $.post('api_project.php', formData, function(res) {
            if (res.success) {
                Swal.fire('Éxito', 'Hito guardado correctamente', 'success');
                $('#milestoneModal').modal('hide');
                loadProjectDetails(currentProjectId);
            } else {
                Swal.fire('Error', res.error, 'error');
            }
        });
    });
    
    // Bind Task Form Submit
    $('#taskForm').on('submit', function(e) {
        e.preventDefault();
        let formData = $(this).serialize() + '&action=save_task';
        $.post('api_project.php', formData, function(res) {
            if (res.success) {
                Swal.fire('Éxito', 'Tarea guardada correctamente', 'success');
                $('#taskModal').modal('hide');
                loadProjectDetails(currentProjectId);
            } else {
                Swal.fire('Error', res.error, 'error');
            }
        });
    });
    
    // Bind Observation Form Submit
    $('#obsForm').on('submit', function(e) {
        e.preventDefault();
        let formData = $(this).serialize() + '&action=save_observation';
        $.post('api_project.php', formData, function(res) {
            if (res.success) {
                Swal.fire('Éxito', 'Observación guardada correctamente', 'success');
                $('#obsModal').modal('hide');
                loadProjectDetails(currentProjectId);
            } else {
                Swal.fire('Error', res.error, 'error');
            }
        });
    });
    
    // Bind Apply Template Form Submit
    $('#applyTemplateForm').on('submit', function(e) {
        e.preventDefault();
        let formData = $(this).serialize() + '&action=apply_milestone_template';
        $.post('api_project.php', formData, function(res) {
            if (res.success) {
                Swal.fire('Éxito', 'Plantilla aplicada con éxito', 'success');
                $('#applyTemplateModal').modal('hide');
                loadProjectDetails(currentProjectId);
            } else {
                Swal.fire('Error', res.error, 'error');
            }
        });
    });
// Quick Create Client Modal
function showQuickCreateClientModal() {
    Swal.fire({
        title: 'Registrar Nuevo Cliente (CMDB)',
        text: 'Ingrese el nombre o razón social del cliente para agregarlo a la CMDB:',
        input: 'text',
        inputPlaceholder: 'Ej. Corporación Favorita, Sonda Ecuador...',
        showCancelButton: true,
        confirmButtonText: 'Guardar Cliente',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#00b8d4',
        inputValidator: (value) => {
            if (!value || !value.trim()) {
                return '¡Debe ingresar el nombre del cliente!';
            }
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            let clientName = result.value.trim();
            $.post('api_project.php', { action: 'quick_create_client', name: clientName }, function(res) {
                if (res.success && res.client) {
                    let newOption = new Option(`${res.client.hostname} (${res.client.ci_unique})`, res.client.id, true, true);
                    $('#projectClientCi').append(newOption).trigger('change');
                    Swal.fire('Cliente Registrado', `El cliente "${res.client.hostname}" ha sido registrado en la CMDB con código ${res.client.ci_unique}.`, 'success');
                } else {
                    Swal.fire('Error', res.error || 'No se pudo crear el cliente.', 'error');
                }
            }, 'json');
        }
    });
}

// Load Project List
function loadProjects() {
    $.post('api_project.php', { action: 'list_projects' }, function(res) {
        if (res.success) {
            let html = '';
            if (res.projects.length === 0) {
                html = `<tr><td colspan="8" class="text-center py-4 text-muted"><i class="fas fa-folder-open mr-1"></i> No hay proyectos registrados.</td></tr>`;
            } else {
                res.projects.forEach(p => {
                    let client = p.client_name ? `${p.client_name} (${p.code})` : 'Sin asignar';
                    let amount = parseFloat(p.amount).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    html += `
                    <tr>
                      <td><span class="badge badge-navy px-2 py-1 font-weight-bold" style="background-color: var(--sonda-navy); color: #fff;">${p.code}</span></td>
                      <td><strong>${p.name}</strong></td>
                      <td><i class="fas fa-building text-muted mr-1"></i> ${p.client_name || 'Sin asignar'}</td>
                      <td><strong class="text-success">$${amount}</strong></td>
                      <td>${p.start_date}</td>
                      <td>${p.end_date}</td>
                      <td>
                        <div class="progress progress-xs" style="height: 6px; border-radius: 4px; overflow: hidden; background-color: rgba(0,0,0,0.1);">
                          <div class="progress-bar bg-success" style="width: ${p.progress}%"></div>
                        </div>
                        <small class="font-weight-bold text-success">${p.progress}%</small>
                      </td>
                      <td class="text-right">
                        <div class="btn-group">
                          <button class="btn btn-xs btn-primary" onclick="viewProjectDetails(${p.id})"><i class="fas fa-eye"></i> Tablero</button>
                          <button class="btn btn-xs btn-outline-primary" onclick="showProjectModal(${p.id})"><i class="fas fa-edit"></i></button>
                          <button class="btn btn-xs btn-outline-danger" onclick="deleteProject(${p.id})"><i class="fas fa-trash"></i></button>
                        </div>
                      </td>
                    </tr>`;
                });
            }
            $('#projects-table-body').html(html);
        } else {
            $('#projects-table-body').html(`<tr><td colspan="8" class="text-center text-danger font-weight-bold">${res.error}</td></tr>`);
        }
    });
}

// Show project creation / editing Modal
function showProjectModal(id = 0) {
    $('#projectForm')[0].reset();
    $('#projectId').val(id);
    $('.project-day-cb').prop('checked', true); // Check all days by default
    
    if (id === 0) {
        $('#projectModalTitle').html('<i class="fas fa-plus-circle mr-1"></i> Crear Nuevo Proyecto');
        $('#projectModal').modal('show');
    } else {
        $('#projectModalTitle').html('<i class="fas fa-edit mr-1"></i> Editar Proyecto');
        $.post('api_project.php', { action: 'get_project', id: id }, function(res) {
            if (res.success) {
                let p = res.project;
                $('#projectName').val(p.name);
                $('#projectClientCi').val(p.client_ci_id);
                $('#projectStartDate').val(p.start_date);
                $('#projectEndDate').val(p.end_date);
                $('#projectExecDate').val(p.execution_date);
                $('#projectAmount').val(p.amount);
                $('#projectWorkType').val(p.work_type);
                $('#projectPersonnel').val(p.assigned_personnel);
                
                // Set working days checkboxes
                if (p.working_days) {
                    $('.project-day-cb').prop('checked', false);
                    let days = p.working_days.split(',');
                    days.forEach(day => {
                        $(`#day_${day}`).prop('checked', true);
                    });
                }
                
                $('#projectModal').modal('show');
            }
        });
    }
}

// Delete project
function deleteProject(id) {
    Swal.fire({
        title: '¿Confirmar eliminación?',
        text: "Se borrará permanentemente el proyecto, sus hitos, tareas y observaciones asociadas.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('api_project.php', { action: 'delete_project', id: id }, function(res) {
                if (res.success) {
                    Swal.fire('Eliminado', 'Proyecto borrado con éxito', 'success');
                    loadProjects();
                    if (currentProjectId === id) hideProjectDetails();
                } else {
                    Swal.fire('Error', res.error, 'error');
                }
            });
        }
    });
}

// VIEW PROJECT DETAILS (EDT / KANBAN / INFO)
function viewProjectDetails(id) {
    currentProjectId = id;
    loadProjectDetails(id);
    $('#projects-list-section').addClass('d-none');
    $('#project-detail-section').removeClass('d-none');
    // Force active tab to WBS/Gantt on load
    $('#tab-wbs-link').tab('show');
}

function hideProjectDetails() {
    currentProjectId = 0;
    currentProjectData = null;
    $('#project-detail-section').addClass('d-none');
    $('#projects-list-section').removeClass('d-none');
    loadProjects();
}

function loadProjectDetails(id) {
    $.post('api_project.php', { action: 'get_project', id: id }, function(res) {
        if (res.success) {
            currentProjectData = res;
            
            // Render Headers
            $('#detail-project-title').text(`${res.project.code}: ${res.project.name}`);
            $('#detail-project-client').html(`<i class="fas fa-building mr-1"></i> Cliente CI: ${res.project.client_name || 'Ninguno'}`);
            
            // Populate General Info Tab
            let p = res.project;
            let formattedAmount = parseFloat(p.amount).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            $('#info-amount').text('$' + formattedAmount);
            $('#info-work-type').text(p.work_type);
            $('#info-working-days').text(p.working_days ? p.working_days.replace(/,/g, ', ') : '-');
            $('#info-personnel').text(p.assigned_personnel || 'Sin personal asignado');
            $('#info-start-date').text(p.start_date);
            $('#info-end-date').text(p.end_date);
            $('#info-exec-date').text(p.execution_date || 'Sin fecha de ejecución registrada');
            
            // Build WBS & Gantt
            renderWbsAndGantt(res);
            
            // Build Kanban Board
            renderKanban(res);
        } else {
            Swal.fire('Error', res.error, 'error');
            hideProjectDetails();
        }
    });
}

// RENDER EDT / WBS GRID AND GANTT TIMELINE BARS
function renderWbsAndGantt(data) {
    let p = data.project;
    let milestones = data.milestones;
    
    // 1. Calculate project start and end range for Gantt scale
    let pStart = new Date(p.start_date + 'T00:00:00');
    let pEnd = new Date(p.end_date + 'T23:59:59');
    
    // Fallback if dates are invalid
    if (isNaN(pStart.getTime())) pStart = new Date();
    if (isNaN(pEnd.getTime())) pEnd = new Date(pStart.getTime() + (30 * 86400000));
    
    // Generate dates array day by day
    let daysArray = [];
    let tempDate = new Date(pStart);
    while (tempDate <= pEnd) {
        daysArray.push(new Date(tempDate));
        tempDate.setDate(tempDate.getDate() + 1);
    }
    
    // If project duration is empty/invalid, force at least 15 days
    if (daysArray.length === 0) {
        for (let i = 0; i < 15; i++) {
            let d = new Date(pStart);
            d.setDate(d.getDate() + i);
            daysArray.push(d);
        }
    }
    
    let totalDaysCount = daysArray.length;
    let dayWidth = 35; // pixels per day column
    let totalTimelineWidth = totalDaysCount * dayWidth;
    
    // Group days by Month & Year
    let monthGroups = [];
    let currentMonthKey = '';
    let currentMonthGroup = null;
    const monthNames = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
    
    daysArray.forEach((day) => {
        let y = day.getFullYear();
        let m = day.getMonth();
        let key = y + '-' + m;
        if (key !== currentMonthKey) {
            currentMonthKey = key;
            currentMonthGroup = {
                label: `${y} - ${monthNames[m]}`,
                daysCount: 0
            };
            monthGroups.push(currentMonthGroup);
        }
        currentMonthGroup.daysCount++;
    });
    
    // Group days by Week (every 7 days starting from pStart)
    let weekGroups = [];
    let currentWeekGroup = null;
    daysArray.forEach((day, index) => {
        let weekIndex = Math.floor(index / 7);
        if (index % 7 === 0) {
            currentWeekGroup = {
                label: `Semana ${weekIndex + 1}`,
                daysCount: 0
            };
            weekGroups.push(currentWeekGroup);
        }
        currentWeekGroup.daysCount++;
    });
    
    // Render Multi-Tier Header Rows
    let monthsHtml = `<div class="gantt-header-row months-row">`;
    monthGroups.forEach(mg => {
        monthsHtml += `<div class="gantt-header-cell" style="width: ${mg.daysCount * dayWidth}px;">${mg.label}</div>`;
    });
    monthsHtml += `</div>`;
    
    let weeksHtml = `<div class="gantt-header-row weeks-row">`;
    weekGroups.forEach(wg => {
        weeksHtml += `<div class="gantt-header-cell" style="width: ${wg.daysCount * dayWidth}px;">${wg.label}</div>`;
    });
    weeksHtml += `</div>`;
    
    let daysHtml = `<div class="gantt-header-row days-row">`;
    const dayLabels = ["D", "L", "M", "M", "J", "V", "S"];
    daysArray.forEach(day => {
        let label = dayLabels[day.getDay()] + ' ' + day.getDate();
        daysHtml += `<div class="gantt-header-cell" style="width: ${dayWidth}px; font-size: 0.65rem;">${label}</div>`;
    });
    daysHtml += `</div>`;
    
    $('#gantt-timeline-header').html(monthsHtml + weeksHtml + daysHtml);
    
    // Render Grid Lines
    let gridHtml = '';
    daysArray.forEach(() => {
        gridHtml += `<div class="gantt-grid-column" style="width: ${dayWidth}px;"></div>`;
    });
    $('#gantt-grid-lines').html(gridHtml);
    
    // Set Gantt panel width dynamically to match timeline columns
    $('#gantt-timeline-header, #gantt-grid-lines, #gantt-rows-container').css('width', totalTimelineWidth + 'px');
    
    // WBS and Gantt Rows HTML builders
    let wbsHtml = '';
    let ganttHtml = '';
    
    // Auxiliary function to render progress bar in grid
    const getProgressBar = (pct, color = 'bg-success') => `
        <div class="progress progress-xs" style="height: 6px; border-radius: 4px; background-color: rgba(0,0,0,0.1); width: 80px;">
          <div class="progress-bar ${color}" style="width: ${pct}%"></div>
        </div>
        <small class="font-weight-bold text-muted ml-2">${Math.round(pct)}%</small>
    `;
    
    // Level 1: Project Summary Row
    wbsHtml += `
    <tr class="row-level-1">
      <td class="indent-1">
        <span class="text-primary mr-1"><i class="fas fa-briefcase"></i></span>
        ${p.code}: <strong>${p.name}</strong>
      </td>
      <td>-</td>
      <td class="d-flex align-items-center">
        ${getProgressBar(calculateProjectProgress(milestones), 'bg-primary')}
      </td>
      <td>-</td>
      <td>${p.start_date} / ${p.end_date}</td>
      <td class="text-right">
        <button class="btn btn-xs btn-outline-primary" onclick="showProjectModal(${p.id})" title="Editar"><i class="fas fa-edit"></i></button>
      </td>
    </tr>`;
    
    // Render Project Summary Gantt Bar (black summary bracket)
    ganttHtml += `
    <div class="gantt-row">
      <div class="gantt-bar project-bar" style="left: 0px; width: ${totalTimelineWidth}px; background-color: #343a40; border: 1px solid #111;" title="${p.name} (0-100%)">
        <div class="gantt-bar-label">${p.code}</div>
      </div>
    </div>`;
    
    // Loop Level 2: Milestones
    milestones.forEach((mil, milIndex) => {
        let isMilCollapsed = collapsedMilestones.has(mil.id);
        let arrowClass = isMilCollapsed ? 'collapsed' : '';
        
        let playBtn = '';
        if (mil.status === 'New') {
            playBtn = `<button class="btn btn-xs btn-success" onclick="playMilestone(${mil.id}, event)" title="Iniciar Ejecución (Play)"><i class="fas fa-play"></i> Iniciar</button>`;
        } else {
            let badgeClass = mil.status === 'Closed' ? 'badge-success' : 'badge-warning';
            let badgeLabel = mil.status === 'Closed' ? 'Cerrado' : 'Ejecutando';
            playBtn = `<span class="badge ${badgeClass}"><i class="fas ${mil.status === 'Closed' ? 'fa-check-circle' : 'fa-spinner fa-spin'} mr-1"></i> ${badgeLabel}</span>`;
        }
        
        // Calculate milestone timeline position if dates are set
        let leftPxMil = 5;
        let widthPxMil = 2;
        let isMilestoneTimelineAvailable = false;
        
        if (mil.estimated_start_date && mil.estimated_end_date) {
            let mStart = new Date(mil.estimated_start_date.replace(' ', 'T'));
            let mEnd = new Date(mil.estimated_end_date.replace(' ', 'T'));
            if (!isNaN(mStart.getTime()) && !isNaN(mEnd.getTime())) {
                let offsetDaysMil = (mStart - pStart) / 86400000;
                let durationDaysMil = (mEnd - mStart) / 86400000;
                if (durationDaysMil < 0.5) durationDaysMil = 0.5;
                
                leftPxMil = offsetDaysMil * dayWidth;
                widthPxMil = durationDaysMil * dayWidth;
                
                if (leftPxMil < 0) leftPxMil = 0;
                if (leftPxMil > totalTimelineWidth) leftPxMil = totalTimelineWidth - 20;
                if (leftPxMil + widthPxMil > totalTimelineWidth) widthPxMil = totalTimelineWidth - leftPxMil;
                isMilestoneTimelineAvailable = true;
            }
        }
        
        wbsHtml += `
        <tr class="row-level-2" data-milestone-id="${mil.id}">
          <td class="indent-2">
            <i class="fas fa-caret-down toggle-arrow ${arrowClass}" onclick="toggleMilestoneCollapse(${mil.id})"></i>
            <span class="text-success mr-1"><i class="fas fa-flag"></i></span>
            ${mil.code}: <strong>${mil.name}</strong>
          </td>
          <td>${playBtn}</td>
          <td class="d-flex align-items-center">
            ${getProgressBar(mil.progress_percentage, 'bg-success')}
          </td>
          <td><span class="badge bg-light priority-badge">${mil.priority}</span></td>
          <td>
            <div style="font-size: 0.8rem; line-height: 1.2;">
              <div><strong>Prev:</strong> ${mil.estimated_start_date ? mil.estimated_start_date.substring(0,10) : '-'} / ${mil.estimated_end_date ? mil.estimated_end_date.substring(0,10) : '-'}</div>
              ${mil.real_start_date ? `<div><strong>Real:</strong> ${mil.real_start_date.substring(0,10)} / ${mil.real_end_date ? mil.real_end_date.substring(0,10) : '-'}</div>` : ''}
              <small class="text-success">${mil.average_execution_time} hrs</small>
            </div>
          </td>
          <td class="text-right">
            <div class="btn-group">
              <button class="btn btn-xs btn-outline-info" onclick="showTaskModal(${mil.id}, 0)" title="Agregar Tarea"><i class="fas fa-plus"></i> Tarea</button>
              <button class="btn btn-xs btn-outline-dark" onclick="showObsModal(${mil.id}, 0)" title="Agregar Observación"><i class="fas fa-comment"></i> Obs</button>
              <button class="btn btn-xs btn-outline-success" onclick="saveMilestoneAsTemplate(${mil.id})" title="Guardar como Plantilla"><i class="fas fa-save"></i> Tpl</button>
              <button class="btn btn-xs btn-outline-primary" onclick="showMilestoneModal(${mil.id})" title="Editar"><i class="fas fa-edit"></i></button>
              <button class="btn btn-xs btn-outline-danger" onclick="deleteMilestone(${mil.id})" title="Eliminar"><i class="fas fa-trash"></i></button>
            </div>
          </td>
        </tr>`;
        
        let milestoneGanttStyle = '';
        if (isMilestoneTimelineAvailable) {
            milestoneGanttStyle = `left: ${leftPxMil}px; width: ${widthPxMil}px; background-color: rgba(255, 215, 0, 0.35); border: 2px dashed #ffd700; border-radius: 6px;`;
        } else {
            milestoneGanttStyle = `left: calc(2% + ${milIndex * 5}%); width: 14px; height: 14px; transform: rotate(45deg); background-color: #ffd700; border: 1px solid #d4af37; border-radius: 0;`;
        }
        
        let textLeftMil = isMilestoneTimelineAvailable ? (leftPxMil + widthPxMil + 8) : 55;
        
        ganttHtml += `
        <div class="gantt-row ${isMilCollapsed ? 'row-collapsed' : ''}" data-milestone-child="${mil.id}">
          <div class="gantt-bar" style="${milestoneGanttStyle}" title="Hito: ${mil.name}">
            ${isMilestoneTimelineAvailable ? `<div class="gantt-bar-label" style="color: #ffd700; font-size: 0.65rem;">${mil.code}</div>` : ''}
          </div>
          <span style="position: absolute; left: ${textLeftMil}px; white-space: nowrap; font-size: 0.72rem; color: #ffd700; font-weight: 700; text-shadow: 0 1px 3px rgba(0,0,0,0.8); pointer-events: none; z-index: 5;">
            ${mil.code} - ${mil.name} (${Math.round(mil.progress_percentage)}%)
          </span>
        </div>`;
        
        // Loop Level 3: Tasks/Requirements within this milestone
        if (mil.tasks) {
            mil.tasks.forEach(task => {
                let isTaskCollapsed = isMilCollapsed;
                let tStart = new Date(task.estimated_start_date.replace(' ', 'T'));
                let tEnd = new Date(task.estimated_end_date.replace(' ', 'T'));
                
                // Calculate position and width of Task Bar in Gantt chart (absolute pixels)
                let offsetDays = (tStart - pStart) / 86400000;
                let durationDays = (tEnd - tStart) / 86400000;
                if (durationDays < 0.5) durationDays = 0.5; // minimum block width
                
                let leftPx = offsetDays * dayWidth;
                let widthPx = durationDays * dayWidth;
                
                // Bound check
                if (leftPx < 0) leftPx = 0;
                if (leftPx > totalTimelineWidth) leftPx = totalTimelineWidth - 20;
                if (leftPx + widthPx > totalTimelineWidth) widthPx = totalTimelineWidth - leftPx;
                if (widthPx < 15) widthPx = 15; // minimum bar visible
                
                let pBadgeColor = task.priority === 'Alta' ? 'badge-danger' : (task.priority === 'Media' ? 'badge-warning' : 'badge-success');
                let statusBadgeColor = 'badge-secondary';
                if (task.status === 'Scheduled') statusBadgeColor = 'badge-primary';
                if (task.status === 'In progress') statusBadgeColor = 'badge-warning';
                if (task.status === 'Closed') statusBadgeColor = 'badge-success';
                
                wbsHtml += `
                <tr class="row-level-3 ${isTaskCollapsed ? 'row-collapsed' : ''}" data-milestone-child="${mil.id}">
                  <td class="indent-3">
                    <span class="text-info mr-1"><i class="fas fa-check-square"></i></span>
                    ${task.code}: <strong>${task.title}</strong>
                  </td>
                  <td>
                    <strong>${task.assigned_person || 'Sin asignar'}</strong><br>
                    <span class="badge ${statusBadgeColor}">${translateStatus(task.status)}</span>
                  </td>
                  <td class="d-flex align-items-center">
                    ${getProgressBar(task.progress_percentage, 'bg-info')}
                  </td>
                  <td><span class="badge ${pBadgeColor} priority-badge">${task.priority}</span></td>
                  <td>
                    <div style="font-size: 0.8rem; line-height: 1.2;">
                      <div><strong>Prev:</strong> ${task.estimated_start_date ? task.estimated_start_date.substring(0,10) : '-'} / ${task.estimated_end_date ? task.estimated_end_date.substring(0,10) : '-'}</div>
                      ${task.real_start_date ? `<div><strong>Real:</strong> ${task.real_start_date.substring(0,10)} / ${task.real_end_date ? task.real_end_date.substring(0,10) : '-'}</div>` : ''}
                      <small class="text-success">${task.average_execution_time} hrs</small>
                    </div>
                  </td>
                  <td class="text-right">
                    <div class="btn-group">
                      <button class="btn btn-xs btn-outline-dark" onclick="showObsModal(${mil.id}, ${task.id})" title="Agregar Observación"><i class="fas fa-comment"></i> Obs</button>
                      <button class="btn btn-xs btn-outline-primary" onclick="showTaskModal(${mil.id}, ${task.id})" title="Editar"><i class="fas fa-edit"></i></button>
                      <button class="btn btn-xs btn-outline-danger" onclick="deleteTask(${task.id})" title="Eliminar"><i class="fas fa-trash"></i></button>
                    </div>
                  </td>
                </tr>`;
                
                let textLeftTask = leftPx + widthPx + 8;
                
                // Render Task Gantt Bar with progress inside
                ganttHtml += `
                <div class="gantt-row ${isTaskCollapsed ? 'row-collapsed' : ''}" data-milestone-child="${mil.id}">
                  <div class="gantt-bar" style="left: ${leftPx}px; width: ${widthPx}px;" title="${task.code} (${task.progress_percentage}%)">
                    <div class="gantt-bar-progress" style="width: ${task.progress_percentage}%"></div>
                    <div class="gantt-bar-label">${task.code}</div>
                  </div>
                  <span class="gantt-text-label" style="position: absolute; left: ${textLeftTask}px; white-space: nowrap; font-size: 0.72rem; font-weight: 600; text-shadow: 0 1px 2px rgba(255,255,255,0.7); pointer-events: none; z-index: 5;">
                    ${task.code} - ${task.title} (${Math.round(task.progress_percentage)}%)
                  </span>
                </div>`;
                
                // Loop Level 4: Observations under Task
                if (task.observations) {
                    task.observations.forEach(obs => {
                        wbsHtml += `
                        <tr class="row-level-4 ${isTaskCollapsed ? 'row-collapsed' : ''}" data-milestone-child="${mil.id}">
                          <td class="indent-4">
                            <span class="text-muted mr-1"><i class="fas fa-comment-dots"></i></span>
                            ${obs.code}: "${obs.comment}"
                          </td>
                          <td>-</td>
                          <td>-</td>
                          <td>-</td>
                          <td><small>${obs.created_at.substring(0, 16)}</small></td>
                          <td class="text-right">
                            <button class="btn btn-xs btn-outline-danger" onclick="deleteObservation(${obs.id})" title="Eliminar"><i class="fas fa-trash"></i></button>
                          </td>
                        </tr>`;
                        
                        ganttHtml += `
                        <div class="gantt-row ${isTaskCollapsed ? 'row-collapsed' : ''}" data-milestone-child="${mil.id}">
                          <!-- Spacer row aligning with WBS -->
                        </div>`;
                    });
                }
            });
        }
        
        // Loop Level 4: Direct Observations under Milestone (without task)
        if (mil.observations) {
            mil.observations.forEach(obs => {
                wbsHtml += `
                <tr class="row-level-4 ${isMilCollapsed ? 'row-collapsed' : ''}" data-milestone-child="${mil.id}">
                  <td class="indent-4">
                    <span class="text-muted mr-1"><i class="fas fa-comment-dots"></i></span>
                    ${obs.code}: "${obs.comment}"
                  </td>
                  <td>-</td>
                  <td>-</td>
                  <td>-</td>
                  <td><small>${obs.created_at.substring(0, 16)}</small></td>
                  <td class="text-right">
                    <button class="btn btn-xs btn-outline-danger" onclick="deleteObservation(${obs.id})" title="Eliminar"><i class="fas fa-trash"></i></button>
                  </td>
                </tr>`;
                
                ganttHtml += `
                <div class="gantt-row ${isMilCollapsed ? 'row-collapsed' : ''}" data-milestone-child="${mil.id}">
                  <!-- Spacer row aligning with WBS -->
                </div>`;
            });
        }
    });
    
    $('#wbs-table-body').html(wbsHtml);
    $('#gantt-rows-container').html(ganttHtml);
    
    // Sync heights after DOM update
    setTimeout(syncGanttRowHeights, 50);
}

// Sync WBS table row heights with Gantt row heights dynamically
function syncGanttRowHeights() {
    let wbsRows = $('#wbs-table-body tr');
    let ganttRows = $('#gantt-rows-container .gantt-row');
    
    wbsRows.each(function(idx) {
        let wbsRow = $(this);
        let ganttRow = $(ganttRows[idx]);
        if (ganttRow.length) {
            // Match height exactly (including borders and padding)
            let h = wbsRow.outerHeight();
            ganttRow.css('height', h + 'px');
        }
    });
}

// Sync on resize
$(window).off('resize.gantt').on('resize.gantt', function() {
    syncGanttRowHeights();
});

// Calculate project progress percentage based on milestone progress average
function calculateProjectProgress(milestones) {
    if (!milestones || milestones.length === 0) return 0;
    let sum = 0;
    milestones.forEach(m => sum += parseFloat(m.progress_percentage));
    return Math.round(sum / milestones.length);
}

// Toggle milestone expansion in tree view
function toggleMilestoneCollapse(milestoneId) {
    if (collapsedMilestones.has(milestoneId)) {
        collapsedMilestones.delete(milestoneId);
    } else {
        collapsedMilestones.add(milestoneId);
    }
    
    // Toggle class on toggle arrows
    let row = $(`.wbs-table tr[data-milestone-id="${milestoneId}"]`);
    row.find('.toggle-arrow').toggleClass('collapsed');
    
    // Hide/show children rows in WBS and Gantt
    $(`.wbs-table tr[data-milestone-child="${milestoneId}"]`).toggleClass('row-collapsed');
    $(`.gantt-rows-container div[data-milestone-child="${milestoneId}"]`).toggleClass('row-collapsed');
    
    // Sync heights after toggling visibility
    setTimeout(syncGanttRowHeights, 50);
}

// Save milestone as Template
function saveMilestoneAsTemplate(milestoneId) {
    Swal.fire({
        title: 'Crear Hito Plantilla',
        input: 'text',
        inputLabel: 'Nombre de la plantilla para reuso',
        placeholder: 'Ej. Instalación de un servidor físico...',
        showCancelButton: true,
        confirmButtonText: 'Crear Plantilla',
        cancelButtonText: 'Cancelar',
        preConfirm: (name) => {
            if (!name) {
                Swal.showValidationMessage('El nombre de la plantilla es obligatorio');
                return false;
            }
            return $.post('api_project.php', {
                action: 'save_milestone_template',
                milestone_id: milestoneId,
                template_name: name
            });
        }
    }).then(result => {
        if (result && result.value && result.value.success) {
            Swal.fire('Guardado', 'Plantilla de hito creada correctamente. Ahora puede ser cargada en otros proyectos.', 'success');
        } else if (result && result.value) {
            Swal.fire('Error', result.value.error, 'error');
        }
    });
}

// Milestone Play execution triggers
function playMilestone(id, event) {
    event.stopPropagation();
    Swal.fire({
        title: '¿Iniciar ejecución del Hito?',
        text: "Se habilitarán los requerimientos internos para ser gestionados en el tablero Kanban.",
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'Play',
        cancelButtonText: 'Cancelar'
    }).then(result => {
        if (result.isConfirmed) {
            $.post('api_project.php', { action: 'play_milestone', id: id }, function(res) {
                if (res.success) {
                    Swal.fire('Iniciado', 'El hito ha entrado en fase de ejecución y está disponible en el Kanban.', 'success');
                    loadProjectDetails(currentProjectId);
                } else {
                    Swal.fire('Error', res.error, 'error');
                }
            });
        }
    });
}

// Show Milestone Creation modal
function showMilestoneModal(id = 0) {
    $('#milestoneForm')[0].reset();
    $('#milestoneId').val(id);
    $('#milestoneProjectId').val(currentProjectId);
    
    let getLocalIso = (d) => new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
    let formatForInput = (dateStr) => {
        if (!dateStr) return '';
        let d = new Date(dateStr);
        if (isNaN(d.getTime())) return '';
        return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
    };
    
    if (id === 0) {
        $('#milestoneModalTitle').html('<i class="fas fa-plus-circle mr-1"></i> Agregar Hito');
        let now = new Date();
        $('#milestoneEstStart').val(getLocalIso(now));
        $('#milestoneEstEnd').val(getLocalIso(new Date(now.getTime() + 86400000)));
        $('#milestoneRealStart').val('');
        $('#milestoneRealEnd').val('');
        $('#milestoneModal').modal('show');
    } else {
        $('#milestoneModalTitle').html('<i class="fas fa-edit mr-1"></i> Editar Hito');
        
        // Find local object
        let mil = currentProjectData.milestones.find(m => m.id == id);
        if (mil) {
            $('#milestoneName').val(mil.name);
            $('#milestonePriority').val(mil.priority);
            $('#milestoneImportance').val(mil.importance);
            $('#milestoneAvgTime').val(mil.average_execution_time);
            $('#milestoneEstStart').val(formatForInput(mil.estimated_start_date));
            $('#milestoneEstEnd').val(formatForInput(mil.estimated_end_date));
            $('#milestoneRealStart').val(formatForInput(mil.real_start_date));
            $('#milestoneRealEnd').val(formatForInput(mil.real_end_date));
            $('#milestoneModal').modal('show');
        }
    }
}

// Delete milestone
function deleteMilestone(id) {
    Swal.fire({
        title: '¿Eliminar Hito?',
        text: "Esta acción borrará las tareas y observaciones dentro de este hito de manera irreversible.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(result => {
        if (result.isConfirmed) {
            $.post('api_project.php', { action: 'delete_milestone', id: id }, function(res) {
                if (res.success) {
                    Swal.fire('Eliminado', 'Hito eliminado', 'success');
                    loadProjectDetails(currentProjectId);
                } else {
                    Swal.fire('Error', res.error, 'error');
                }
            });
        }
    });
}

// Show Task Form Modal
function showTaskModal(milestoneId, id = 0) {
    $('#taskForm')[0].reset();
    $('#taskId').val(id);
    $('#taskMilestoneId').val(milestoneId);
    
    // Set default dates based on today's datetime local format
    let now = new Date();
    let getLocalIso = (d) => new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
    $('#taskEstStart').val(getLocalIso(now));
    $('#taskEstEnd').val(getLocalIso(new Date(now.getTime() + 86400000))); // +24 hours
    $('#taskProgressRange').val(0);
    $('#taskProgressText').val(0);
    
    if (id === 0) {
        $('#taskModalTitle').html('<i class="fas fa-plus-circle mr-1"></i> Agregar Tarea / Requerimiento');
        $('#taskModal').modal('show');
    } else {
        $('#taskModalTitle').html('<i class="fas fa-edit mr-1"></i> Editar Tarea / Requerimiento');
        // Find task
        let mil = currentProjectData.milestones.find(m => m.id == milestoneId);
        let task = mil.tasks.find(t => t.id == id);
        if (task) {
            $('#taskTitle').val(task.title);
            $('#taskAssignedPerson').val(task.assigned_person);
            $('#taskStatus').val(task.status);
            
            // Format dates
            if (task.estimated_start_date) $('#taskEstStart').val(task.estimated_start_date.substring(0, 16).replace(' ', 'T'));
            if (task.estimated_end_date) $('#taskEstEnd').val(task.estimated_end_date.substring(0, 16).replace(' ', 'T'));
            if (task.real_start_date) $('#taskRealStart').val(task.real_start_date.substring(0, 16).replace(' ', 'T'));
            if (task.real_end_date) $('#taskRealEnd').val(task.real_end_date.substring(0, 16).replace(' ', 'T'));
            
            $('#taskPriority').val(task.priority);
            $('#taskImportance').val(task.importance);
            
            let progress = Math.round(task.progress_percentage);
            $('#taskProgressRange').val(progress);
            $('#taskProgressText').val(progress);
            
            $('#taskAvgTime').val(task.average_execution_time);
            
            $('#taskModal').modal('show');
        }
    }
}

// Delete Task
function deleteTask(id) {
    Swal.fire({
        title: '¿Eliminar Tarea?',
        text: "Se perderán todas las observaciones relacionadas.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(result => {
        if (result.isConfirmed) {
            $.post('api_project.php', { action: 'delete_task', id: id }, function(res) {
                if (res.success) {
                    Swal.fire('Eliminada', 'Tarea eliminada con éxito', 'success');
                    loadProjectDetails(currentProjectId);
                } else {
                    Swal.fire('Error', res.error, 'error');
                }
            });
        }
    });
}

// Show Observation modal
function showObsModal(milestoneId, taskId = 0) {
    $('#obsForm')[0].reset();
    $('#obsId').val(0);
    $('#obsMilestoneId').val(milestoneId);
    $('#obsTaskId').val(taskId === 0 ? '' : taskId);
    
    let targetText = taskId === 0 ? 'Hito' : 'Tarea';
    $('#obsModalTitle').html(`<i class="fas fa-comment-alt mr-1"></i> Agregar Observación al ${targetText}`);
    $('#obsModal').modal('show');
}

// Delete Observation
function deleteObservation(id) {
    Swal.fire({
        title: '¿Eliminar Observación?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, borrar',
        cancelButtonText: 'Cancelar'
    }).then(result => {
        if (result.isConfirmed) {
            $.post('api_project.php', { action: 'delete_observation', id: id }, function(res) {
                if (res.success) {
                    loadProjectDetails(currentProjectId);
                } else {
                    Swal.fire('Error', res.error, 'error');
                }
            });
        }
    });
}

// Show apply templates modal
function showApplyTemplateModal() {
    $('#applyTplProjectId').val(currentProjectId);
    $('#applyTplSelect').html('<option>Cargando plantillas...</option>');
    
    $.post('api_project.php', { action: 'list_milestone_templates' }, function(res) {
        if (res.success) {
            let html = '<option value="">-- Seleccionar Plantilla --</option>';
            if (res.templates.length === 0) {
                html = '<option value="">No hay plantillas preconfiguradas</option>';
            } else {
                res.templates.forEach(t => {
                    html += `<option value="${t.id}">${t.name} (${t.priority})</option>`;
                });
            }
            $('#applyTplSelect').html(html);
            $('#applyTemplateModal').modal('show');
        }
    });
}

// KANBAN DRAG & DROP ENGINE
function renderKanban(data) {
    // Clear columns
    $('.kanban-cards').empty();
    
    let tasksMap = {
        'New': [],
        'Scheduled': [],
        'In progress': [],
        'Closed': []
    };
    
    // Fetch all active milestone tasks in execution phase (i.e. milestone status != New)
    data.milestones.forEach(mil => {
        if (mil.status !== 'New') {
            if (mil.tasks) {
                mil.tasks.forEach(t => {
                    let colKey = t.status;
                    // Fix in-progress mapping
                    if (colKey === 'In progress' || colKey === 'In-progress') colKey = 'In progress';
                    if (tasksMap[colKey]) {
                        tasksMap[colKey].push({
                            task: t,
                            milestoneName: mil.name
                        });
                    }
                });
            }
        }
    });
    
    // Render counts and cards
    Object.keys(tasksMap).forEach(key => {
        let list = tasksMap[key];
        let domKey = key.replace(' ', '-');
        $(`#count-${domKey}`).text(list.length);
        
        let html = '';
        if (list.length === 0) {
            html = `<div class="text-center py-4 text-muted border border-dashed rounded" style="font-size: 0.8rem; opacity: 0.6;">Ninguna tarea aquí</div>`;
        } else {
            list.forEach(item => {
                let t = item.task;
                let priorityClass = t.priority === 'Alta' ? 'badge-danger' : (t.priority === 'Media' ? 'badge-warning' : 'badge-success');
                
                let cardStyle = '';
                let progressDisplay = '';
                if (t.status === 'Closed') {
                    cardStyle = 'border-left: 5px solid #28a745; background-color: rgba(40, 167, 69, 0.03);';
                    progressDisplay = '<span class="badge badge-success"><i class="fas fa-check-circle mr-1"></i> 100% Terminado</span>';
                } else if (t.status === 'In progress') {
                    cardStyle = 'border-left: 5px solid #ffc107; background-color: rgba(255, 193, 7, 0.03);';
                    progressDisplay = `<span class="badge badge-warning text-dark"><i class="fas fa-running mr-1"></i> ${Math.round(t.progress_percentage)}% Ejecución</span>`;
                } else if (t.status === 'Scheduled') {
                    cardStyle = 'border-left: 5px solid #007bff; background-color: rgba(0, 123, 255, 0.02);';
                    progressDisplay = `<span class="badge badge-primary">${Math.round(t.progress_percentage)}% Planificado</span>`;
                } else {
                    cardStyle = 'border-left: 5px solid #6c757d;';
                    progressDisplay = `<span class="badge badge-secondary">${Math.round(t.progress_percentage)}% Nueva</span>`;
                }
                
                html += `
                <div class="kanban-card" draggable="true" ondragstart="dragTaskStart(event, ${t.id})" id="kcard-${t.id}" style="${cardStyle}">
                  <div class="kanban-card-title">${t.title}</div>
                  <div style="font-size: 0.75rem; color: var(--sonda-cyan); font-weight: 700;">
                     <i class="fas fa-flag mr-1"></i> ${item.milestoneName}
                  </div>
                  <div class="kanban-card-meta">
                     <span class="badge ${priorityClass} priority-badge">${t.priority}</span>
                     <span class="badge badge-navy" style="background-color: var(--sonda-navy); color:#fff;">${t.code}</span>
                  </div>
                  <div class="d-flex align-items-center mt-2 justify-content-between">
                     <small><i class="far fa-user mr-1"></i> ${t.assigned_person || 'Sin asignar'}</small>
                     ${progressDisplay}
                  </div>
                </div>`;
            });
        }
        $(`#cards-${domKey}`).html(html);
    });
}

// Drag & Drop HTML5 APIs wrapper
function dragTaskStart(ev, taskId) {
    ev.dataTransfer.setData("text/plain", taskId);
    // Add opacity feedback
    setTimeout(() => {
        $(`#kcard-${taskId}`).css('opacity', '0.4');
    }, 0);
}

// Overwrite drag events to clear opacity
document.addEventListener("dragend", function(event) {
    $('.kanban-card').css('opacity', '1');
    $('.kanban-col').removeClass('drag-over');
});

function allowDrop(ev) {
    ev.preventDefault();
    let col = $(ev.target).closest('.kanban-col');
    col.addClass('drag-over');
}

function dragLeaveCol(ev) {
    let col = $(ev.target).closest('.kanban-col');
    col.removeClass('drag-over');
}

function dropTask(ev, targetStatus) {
    ev.preventDefault();
    let col = $(ev.target).closest('.kanban-col');
    col.removeClass('drag-over');
    
    let taskId = ev.dataTransfer.getData("text");
    if (!taskId) return;
    
    // Trigger API state change
    $.post('api_project.php', {
        action: 'update_task_status',
        id: taskId,
        status: targetStatus
    }, function(res) {
        if (res.success) {
            loadProjectDetails(currentProjectId);
        } else {
            Swal.fire('Error', res.error, 'error');
        }
    });
}

// Helper: Status translator
function translateStatus(s) {
    switch (s) {
        case 'New': return 'Nueva';
        case 'Scheduled': return 'Planificada';
        case 'In progress': return 'En Progreso';
        case 'Closed': return 'Cerrada';
        default: return s;
    }
}
</script>

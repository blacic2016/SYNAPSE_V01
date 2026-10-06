<?php
/**
 * Datacenter Rooms Management - CMDB VILASECA / DCIM
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/permissions_helper.php';

require_login();
if (!has_role('SUPER_ADMIN') && !has_module_access('datacenter') && !has_module_access('vilaseca')) {
    header("Location: " . PUBLIC_URL_PREFIX . "/dashboard.php");
    exit();
}

$pdo = getPDO();
$page_title = 'Datacenter - Cuartos y Salas';

// Filtro de Cliente (Multitenancy / Vilaseca)
$client_filter = trim($_GET['cliente'] ?? $_GET['client'] ?? '');
if (empty($client_filter) && !has_role('SUPER_ADMIN') && has_module_access('vilaseca')) {
    $client_filter = 'VILASECA';
}

// Manejo de formulario (Crear/Editar/Eliminar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $client = trim($_POST['client'] ?? $client_filter ?? 'VILASECA');
        $city = trim($_POST['city'] ?? '');
        $location = trim($_POST['location'] ?? $_POST['location_detail'] ?? '');
        $location_detail = $location;
        $width = (float)($_POST['width_meters'] ?? 6.0);
        $length = (float)($_POST['length_meters'] ?? 6.0);
        $floor_height = !empty($_POST['floor_height_meters']) ? (float)$_POST['floor_height_meters'] : null;
        
        if (empty($name) || empty($client)) {
            $_SESSION['flash_error'] = "Error: El Nombre del Cuarto y el Cliente son obligatorios.";
        } else {
            // Verificar duplicados de cuartos
            $stmtCheck = $pdo->prepare("SELECT id FROM dc_rooms WHERE UPPER(TRIM(client)) = UPPER(TRIM(?)) AND UPPER(TRIM(name)) = UPPER(TRIM(?)) LIMIT 1");
            $stmtCheck->execute([$client, $name]);
            if ($stmtCheck->fetch()) {
                $_SESSION['flash_error'] = "No se puede crear el cuarto: Ya existe una sala con el nombre '{$name}' para el cliente '{$client}'.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO dc_rooms (name, client, city, location, location_detail, width_meters, length_meters, floor_height_meters) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $client, $city, $location, $location_detail, $width, $length, $floor_height]);
                $_SESSION['flash_msg'] = "Cuarto '{$name}' creado exitosamente para el cliente {$client}.";
            }
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $client = trim($_POST['client'] ?? $client_filter ?? 'VILASECA');
        $city = trim($_POST['city'] ?? '');
        $location = trim($_POST['location'] ?? $_POST['location_detail'] ?? '');
        $location_detail = $location;
        $width = (float)($_POST['width_meters'] ?? 6.0);
        $length = (float)($_POST['length_meters'] ?? 6.0);
        $floor_height = !empty($_POST['floor_height_meters']) ? (float)$_POST['floor_height_meters'] : null;
        
        if ($id > 0 && !empty($name) && !empty($client)) {
            $stmtCheck = $pdo->prepare("SELECT id FROM dc_rooms WHERE UPPER(TRIM(client)) = UPPER(TRIM(?)) AND UPPER(TRIM(name)) = UPPER(TRIM(?)) AND id != ? LIMIT 1");
            $stmtCheck->execute([$client, $name, $id]);
            if ($stmtCheck->fetch()) {
                $_SESSION['flash_error'] = "No se puede actualizar: Ya existe otra sala con el nombre '{$name}' para el cliente '{$client}'.";
            } else {
                $stmt = $pdo->prepare("UPDATE dc_rooms SET name = ?, client = ?, city = ?, location = ?, location_detail = ?, width_meters = ?, length_meters = ?, floor_height_meters = ? WHERE id = ?");
                $stmt->execute([$name, $client, $city, $location, $location_detail, $width, $length, $floor_height, $id]);
                $_SESSION['flash_msg'] = "Cuarto '{$name}' actualizado exitosamente.";
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM dc_rooms WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['flash_msg'] = "Cuarto eliminado correctamente.";
            } catch (Exception $e) {
                $_SESSION['flash_error'] = "Error al eliminar cuarto: " . $e->getMessage();
            }
        }
    }
    
    $redirect = "rooms.php" . ($client_filter ? "?cliente=" . urlencode($client_filter) : "");
    header("Location: " . $redirect);
    exit;
}

// Cargar cuartos (filtrados por cliente si aplica)
$roomsQuery = "SELECT r.*, (SELECT COUNT(*) FROM dc_racks WHERE room_id = r.id) as rack_count 
               FROM dc_rooms r WHERE 1=1";
$params = [];
if (!empty($client_filter)) {
    $roomsQuery .= " AND (UPPER(r.client) = UPPER(?) OR r.client IS NULL OR r.client = '')";
    $params[] = $client_filter;
}
$roomsQuery .= " ORDER BY r.client ASC, r.name ASC";
$stmt = $pdo->prepare($roomsQuery);
$stmt->execute($params);
$rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../partials/header.php';
?>

<div class="container-fluid pt-4">
    <!-- MENSAJES FLASH -->
    <?php if (isset($_SESSION['flash_msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm">
            <i class="fas fa-check-circle mr-1"></i> <?php echo htmlspecialchars($_SESSION['flash_msg']); unset($_SESSION['flash_msg']); ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm">
            <i class="fas fa-exclamation-triangle mr-1"></i> <?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

    <!-- HEADER DE MÓDULO -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap: 12px;">
        <div>
            <h4 class="font-weight-bold m-0 text-navy">
                <i class="fas fa-door-open text-primary mr-2"></i>Gestión de Salas y Cuartos de Datacenter
            </h4>
            <p class="text-muted small m-0">
                Definición dimensional de salas, piso falso elevado y cuadrícula para planos 2D/3D.
            </p>
        </div>
        <div class="d-flex align-items-center" style="gap: 8px;">
            <?php if (!empty($client_filter)): ?>
                <span class="badge badge-warning text-dark font-weight-bold px-3 py-2 shadow-sm" style="font-size: 0.85rem;">
                    <i class="fas fa-building mr-1"></i>Cliente: <?php echo htmlspecialchars($client_filter); ?>
                </span>
                <?php if (strtoupper($client_filter) === 'VILASECA'): ?>
                    <a href="<?php echo PUBLIC_URL_PREFIX; ?>/clientes/vilaseca/index.php" class="btn btn-outline-secondary btn-sm font-weight-bold">
                        <i class="fas fa-arrow-left mr-1"></i>Centro Vilaseca
                    </a>
                <?php endif; ?>
            <?php endif; ?>
            <a href="racks.php<?php echo $client_filter ? '?cliente=' . urlencode($client_filter) : ''; ?>" class="btn btn-outline-warning text-dark btn-sm font-weight-bold">
                <i class="fas fa-server mr-1 text-warning"></i>Ver Racks / Gabinetes
            </a>
            <a href="viewer_3d.php<?php echo $client_filter ? '?cliente=' . urlencode($client_filter) : ''; ?>" class="btn btn-info btn-sm font-weight-bold shadow-sm">
                <i class="fas fa-cube mr-1"></i>3DViewer
            </a>
            <a href="analisis.php<?php echo $client_filter ? '?cliente=' . urlencode($client_filter) : ''; ?>" class="btn btn-outline-primary btn-sm font-weight-bold">
                <i class="fas fa-chart-pie mr-1"></i>Análisis Capacidad DC
            </a>
            <button class="btn btn-primary btn-sm font-weight-bold shadow-sm" data-toggle="modal" data-target="#createRoomModal">
                <i class="fas fa-plus mr-1"></i> Crear Nuevo Cuarto
            </button>
        </div>
    </div>

    <!-- TARJETA PRINCIPAL DE CUARTOS -->
    <div class="card card-outline card-primary shadow-sm" style="border-radius: 10px; overflow: hidden;">
        <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap" style="gap: 10px;">
            <div class="font-weight-bold text-dark small">
                <i class="fas fa-list text-muted mr-1"></i>Salas Registradas (<?php echo count($rooms); ?>)
            </div>
        </div>

        <div class="card-body p-0 table-responsive">
            <table class="table table-striped table-hover m-0" style="font-size: 0.85rem;">
                <thead class="bg-light">
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th>Cliente</th>
                        <th>Ciudad</th>
                        <th>Ubicación / Sede</th>
                        <th>Nombre del Cuarto</th>
                        <th>Dimensiones Físicas</th>
                        <th>Racks Asignados</th>
                        <th>Cálculo de Baldosas</th>
                        <th class="text-right pr-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($rooms) > 0): ?>
                        <?php foreach ($rooms as $room): 
                            $w = $room['width_meters'] ?? 6;
                            $l = $room['length_meters'] ?? 6;
                            $ts = $room['tile_size'] ?? 0.6;
                            $tiles_x = floor($w / $ts);
                            $tiles_y = floor($l / $ts);
                            $total_tiles = $tiles_x * $tiles_y;
                        ?>
                            <tr>
                                <td class="font-weight-bold text-muted"><?php echo $room['id']; ?></td>
                                <td>
                                    <span class="badge badge-warning text-dark font-weight-bold">
                                        <i class="fas fa-building mr-1"></i><?php echo htmlspecialchars($room['client'] ?: 'VILASECA'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-light border text-dark">
                                        <?php echo htmlspecialchars($room['city'] ?: 'No asignada'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="font-weight-bold text-primary">
                                        <i class="fas fa-map-marker-alt text-danger mr-1"></i><?php echo htmlspecialchars($room['location'] ?: $room['location_detail'] ?: 'General'); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong class="text-navy" style="font-size: 0.92rem;">
                                        <i class="fas fa-door-closed text-muted mr-1"></i><?php echo htmlspecialchars($room['name']); ?>
                                    </strong>
                                </td>
                                <td>
                                    <span class="badge badge-light border"><?php echo htmlspecialchars($w); ?>m x <?php echo htmlspecialchars($l); ?>m</span>
                                </td>
                                <td>
                                    <a href="racks.php?room_id=<?php echo $room['id']; ?><?php echo $client_filter ? '&cliente=' . urlencode($client_filter) : ''; ?>" class="badge badge-info px-2 py-1">
                                        <i class="fas fa-server mr-1"></i><?php echo $room['rack_count'] ?? 0; ?> Racks
                                    </a>
                                </td>
                                <td>
                                    <strong><?php echo $total_tiles; ?></strong> baldosas 
                                    <small class="text-muted">(<?php echo $tiles_x; ?> x <?php echo $tiles_y; ?>)</small>
                                </td>
                                <td class="text-right pr-4">
                                    <div class="btn-group btn-group-sm">
                                        <a href="viewer_3d.php?room_id=<?php echo $room['id']; ?><?php echo $client_filter ? '&cliente=' . urlencode($client_filter) : ''; ?>" class="btn btn-info font-weight-bold" title="Ver en 3DViewer (Sunbird Style)">
                                            <i class="fas fa-cube"></i> 3D
                                        </a>
                                        <a href="floor_plan.php?room_id=<?php echo $room['id']; ?><?php echo $client_filter ? '&cliente=' . urlencode($client_filter) : ''; ?>" class="btn btn-primary font-weight-bold" title="Ver Plano 2D">
                                            <i class="fas fa-th"></i> Plano 2D
                                        </a>
                                        <button type="button" class="btn btn-warning font-weight-bold" title="Editar" onclick='openEditModal(<?php echo htmlspecialchars(json_encode($room), ENT_QUOTES, "UTF-8"); ?>)'>
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-danger font-weight-bold" title="Eliminar" onclick="deleteRoom(<?php echo $room['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-5">
                                <i class="fas fa-door-open fa-3x mb-3 text-muted opacity-50 d-block"></i>
                                No se encontraron salas o cuartos registrados.<br>
                                <button class="btn btn-primary btn-sm font-weight-bold mt-3" data-toggle="modal" data-target="#createRoomModal">
                                    <i class="fas fa-plus mr-1"></i> Crear Primer Cuarto
                                </button>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL CREAR CUARTO -->
<div class="modal fade" id="createRoomModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-plus mr-2"></i> Crear Nuevo Cuarto (Data Center Room)</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-body p-4">
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-navy small">Cliente <span class="text-danger">*</span></label>
                            <input type="text" name="client" class="form-control font-weight-bold text-uppercase" 
                                   value="<?php echo htmlspecialchars($client_filter ?: 'VILASECA'); ?>" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-navy small">Ciudad</label>
                            <input type="text" name="city" class="form-control" placeholder="Ej. Quito, Guayaquil">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-navy small">Ubicación / Sede</label>
                            <input type="text" name="location" class="form-control" placeholder="Ej. FADESA QUITO, SEMVRA QUITO">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-navy small">Nombre del Cuarto / Sala <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control font-weight-bold" required placeholder="Ej. Datacenter Principal, Sala Servidores">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold text-navy small">Ancho (m) <span class="text-danger">*</span></label>
                            <input type="number" step="0.1" name="width_meters" id="form_width" class="form-control font-weight-bold" required value="12.0">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold text-navy small">Largo (m) <span class="text-danger">*</span></label>
                            <input type="number" step="0.1" name="length_meters" id="form_length" class="form-control font-weight-bold" required value="8.0">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold text-navy small">Altura Piso Elevado (m)</label>
                            <input type="number" step="0.01" name="floor_height_meters" class="form-control" placeholder="Ej. 0.40">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm font-weight-bold" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-sm font-weight-bold px-3">
                        <i class="fas fa-save mr-1"></i> Guardar Cuarto
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EDITAR CUARTO -->
<div class="modal fade" id="editRoomModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header bg-warning text-dark py-3">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-edit mr-2"></i> Editar Cuarto (Data Center Room)</h5>
                <button type="button" class="close text-dark" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body p-4">
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-navy small">Cliente <span class="text-danger">*</span></label>
                            <input type="text" name="client" id="edit_client" class="form-control font-weight-bold text-uppercase" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-navy small">Ciudad</label>
                            <input type="text" name="city" id="edit_city" class="form-control">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-navy small">Ubicación / Sede</label>
                            <input type="text" name="location" id="edit_location" class="form-control">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-navy small">Nombre del Cuarto <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="edit_name" class="form-control font-weight-bold" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold text-navy small">Ancho (m) <span class="text-danger">*</span></label>
                            <input type="number" step="0.1" name="width_meters" id="edit_width" class="form-control font-weight-bold" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold text-navy small">Largo (m) <span class="text-danger">*</span></label>
                            <input type="number" step="0.1" name="length_meters" id="edit_length" class="form-control font-weight-bold" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold text-navy small">Altura Piso Elevado (m)</label>
                            <input type="number" step="0.01" name="floor_height_meters" id="edit_floor_height" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm font-weight-bold" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning btn-sm font-weight-bold px-3">
                        <i class="fas fa-save mr-1"></i> Actualizar Cuarto
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function deleteRoom(id) {
        if(confirm('¿Seguro que deseas eliminar este cuarto? Los racks asociados podrían quedar huérfanos.')) {
            let form = document.createElement('form');
            form.method = 'POST';
            form.action = 'rooms.php<?php echo $client_filter ? "?cliente=" . urlencode($client_filter) : ""; ?>';
            
            let act = document.createElement('input');
            act.type = 'hidden';
            act.name = 'action';
            act.value = 'delete';
            form.appendChild(act);
            
            let idInp = document.createElement('input');
            idInp.type = 'hidden';
            idInp.name = 'id';
            idInp.value = id;
            form.appendChild(idInp);
            
            document.body.appendChild(form);
            form.submit();
        }
    }

    function openEditModal(room) {
        $("#edit_id").val(room.id);
        $("#edit_client").val(room.client || 'VILASECA');
        $("#edit_city").val(room.city || '');
        $("#edit_location").val(room.location || room.location_detail || '');
        $("#edit_name").val(room.name);
        $("#edit_width").val(room.width_meters);
        $("#edit_length").val(room.length_meters);
        $("#edit_floor_height").val(room.floor_height_meters || '');
        $("#editRoomModal").modal('show');
    }
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>

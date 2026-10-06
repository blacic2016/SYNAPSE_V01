<?php
/**
 * Datacenter Racks Management - CMDB VILASECA / DCIM
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
$page_title = 'Datacenter - Racks / Gabinetes';

// Filtro de Cliente (Multitenancy / Vilaseca)
$client_filter = trim($_GET['cliente'] ?? $_GET['client'] ?? '');
if (empty($client_filter) && !has_role('SUPER_ADMIN') && has_module_access('vilaseca')) {
    $client_filter = 'VILASECA';
}

$room_filter = (int)($_GET['room_id'] ?? 0);
$city_filter = trim($_GET['city'] ?? '');
$location_filter = trim($_GET['location'] ?? '');

// Manejo de formulario (Crear/Editar/Eliminar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $client = trim($_POST['client'] ?? $client_filter ?? 'VILASECA');
        $city = trim($_POST['city'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $room_id = (int)($_POST['room_id'] ?? 0);
        $total_u = (int)($_POST['total_u'] ?? 42);
        $numbering_dir = $_POST['numbering_dir'] ?? 'DOWN';
        $description = trim($_POST['description'] ?? '');
        
        // 1. VALIDACIÓN DE CAMPOS OBLIGATORIOS (Cliente, Ciudad, Ubicación, Nombre)
        if (empty($client) || empty($city) || empty($location) || empty($name)) {
            $_SESSION['flash_error'] = "Error: Para crear el rack son OBLIGATORIOS: Cliente, Ciudad, Ubicación y Nombre del Rack.";
        } else {
            // 2. REGLA DE UNICIDAD / PREVENCIÓN DE DUPLICADOS:
            // "no se cree esque pertenezcan al mismo ciudad , mims aubicaicon y minsmo nombre"
            $stmtCheck = $pdo->prepare("SELECT id, name FROM dc_racks 
                                         WHERE UPPER(TRIM(client)) = UPPER(TRIM(?)) 
                                           AND UPPER(TRIM(city)) = UPPER(TRIM(?)) 
                                           AND UPPER(TRIM(location)) = UPPER(TRIM(?)) 
                                           AND UPPER(TRIM(name)) = UPPER(TRIM(?)) 
                                         LIMIT 1");
            $stmtCheck->execute([$client, $city, $location, $name]);
            $existingRack = $stmtCheck->fetch();

            if ($existingRack) {
                $_SESSION['flash_error'] = "No se puede crear el rack: Ya existe un rack con el nombre '{$name}' en la ciudad '{$city}' y ubicación '{$location}' para el cliente '{$client}'.";
            } else {
                $roomIdVal = ($room_id > 0) ? $room_id : null;
                $stmt = $pdo->prepare("INSERT INTO dc_racks (name, room_id, client, city, location, total_u, numbering_dir, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $roomIdVal, $client, $city, $location, $total_u, $numbering_dir, $description]);
                $_SESSION['flash_msg'] = "Rack '{$name}' creado exitosamente para {$client} en {$city} - {$location}.";
            }
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $client = trim($_POST['client'] ?? $client_filter ?? 'VILASECA');
        $city = trim($_POST['city'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $room_id = (int)($_POST['room_id'] ?? 0);
        $total_u = (int)($_POST['total_u'] ?? 42);
        $numbering_dir = $_POST['numbering_dir'] ?? 'DOWN';
        $description = trim($_POST['description'] ?? '');
        
        if ($id > 0) {
            if (empty($client) || empty($city) || empty($location) || empty($name)) {
                $_SESSION['flash_error'] = "Error: Cliente, Ciudad, Ubicación y Nombre del Rack son campos obligatorios.";
            } else {
                // Validación de duplicados para actualización
                $stmtCheck = $pdo->prepare("SELECT id FROM dc_racks 
                                             WHERE UPPER(TRIM(client)) = UPPER(TRIM(?)) 
                                               AND UPPER(TRIM(city)) = UPPER(TRIM(?)) 
                                               AND UPPER(TRIM(location)) = UPPER(TRIM(?)) 
                                               AND UPPER(TRIM(name)) = UPPER(TRIM(?)) 
                                               AND id != ? 
                                             LIMIT 1");
                $stmtCheck->execute([$client, $city, $location, $name, $id]);
                if ($stmtCheck->fetch()) {
                    $_SESSION['flash_error'] = "No se puede actualizar: Ya existe otro rack con el nombre '{$name}' en la ciudad '{$city}' y ubicación '{$location}' para el cliente '{$client}'.";
                } else {
                    $roomIdVal = ($room_id > 0) ? $room_id : null;
                    $stmt = $pdo->prepare("UPDATE dc_racks SET name=?, room_id=?, client=?, city=?, location=?, total_u=?, numbering_dir=?, description=? WHERE id=?");
                    $stmt->execute([$name, $roomIdVal, $client, $city, $location, $total_u, $numbering_dir, $description, $id]);
                    $_SESSION['flash_msg'] = "Rack '{$name}' actualizado exitosamente.";
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM dc_racks WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['flash_msg'] = "Rack eliminado correctamente.";
            } catch (Exception $e) {
                $_SESSION['flash_error'] = "Error al eliminar rack: " . $e->getMessage();
            }
        }
    }
    
    $redirectUrl = "racks.php";
    $qParams = [];
    if ($client_filter) $qParams[] = "cliente=" . urlencode($client_filter);
    if ($room_filter) $qParams[] = "room_id=" . urlencode((string)$room_filter);
    if (!empty($qParams)) $redirectUrl .= "?" . implode("&", $qParams);

    header("Location: " . $redirectUrl);
    exit;
}

// Cargar Cuartos disponibles (filtrados por cliente si aplica)
$roomsQuery = "SELECT id, name, location FROM dc_rooms WHERE 1=1";
$roomsParams = [];
if (!empty($client_filter)) {
    $roomsQuery .= " AND (UPPER(client) = UPPER(?) OR client IS NULL OR client = '')";
    $roomsParams[] = $client_filter;
}
$roomsQuery .= " ORDER BY name ASC";
$stmtRooms = $pdo->prepare($roomsQuery);
$stmtRooms->execute($roomsParams);
$rooms = $stmtRooms->fetchAll(PDO::FETCH_ASSOC);

// Cargar ciudades conocidas para autocompletado (separado para evitar error 1271 de colaciones MySQL)
$cities1 = [];
$cities2 = [];
try {
    $cities1 = $pdo->query("SELECT DISTINCT city FROM dc_racks WHERE city IS NOT NULL AND city != ''")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}
try {
    $cities2 = $pdo->query("SELECT DISTINCT i.hostname FROM ci_instances i JOIN ci_categories c ON i.category_id = c.id WHERE c.name = 'Ciudad' AND i.hostname IS NOT NULL AND i.hostname != ''")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}
$knownCities = array_values(array_unique(array_filter(array_merge($cities1, $cities2))));
sort($knownCities, SORT_NATURAL | SORT_FLAG_CASE);
if (empty($knownCities)) {
    $knownCities = ['Quito', 'Guayaquil', 'Cuenca', 'Daule', 'Amaguaña'];
}

// Cargar ubicaciones conocidas para autocompletado (separado para evitar error 1271 de colaciones MySQL)
$locs1 = [];
$locs2 = [];
try {
    $sqlLocsSurveys = "SELECT DISTINCT location FROM manual_portmap_surveys WHERE location IS NOT NULL AND location != ''";
    if (!empty($client_filter)) {
        $stmtLocs = $pdo->prepare($sqlLocsSurveys . " AND UPPER(client) LIKE ?");
        $stmtLocs->execute(['%' . strtoupper($client_filter) . '%']);
        $locs1 = $stmtLocs->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $locs1 = $pdo->query($sqlLocsSurveys)->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (Exception $e) {}
try {
    $locs2 = $pdo->query("SELECT DISTINCT location FROM dc_racks WHERE location IS NOT NULL AND location != ''")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}
$knownLocations = array_values(array_unique(array_filter(array_merge($locs1, $locs2))));
sort($knownLocations, SORT_NATURAL | SORT_FLAG_CASE);
if (empty($knownLocations)) {
    $knownLocations = ['FADESA QUITO', 'SEMVRA QUITO AMAGUAÑA', 'DATACENTER PRINCIPAL'];
}

// Cargar Clientes conocidos
$knownClients = ['VILASECA', 'BANCO PICHINCHA', 'CLARO', 'SONDA INTERNO'];
try {
    $dbClients = $pdo->query("SELECT DISTINCT JSON_UNQUOTE(JSON_EXTRACT(attributes_json, '$.client')) as cl FROM ci_instances WHERE attributes_json IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($dbClients as $dbc) {
        if (!empty($dbc) && !in_array(strtoupper($dbc), $knownClients)) $knownClients[] = strtoupper($dbc);
    }
} catch (Exception $e) {}

// Cargar Racks aplicando filtros de cliente, cuarto, etc.
$query = "SELECT r.*, rm.name as room_name, 
                 (SELECT COUNT(*) FROM dc_rack_devices rd WHERE rd.rack_id = r.id) as device_count 
          FROM dc_racks r 
          LEFT JOIN dc_rooms rm ON r.room_id = rm.id
          WHERE 1=1";
$params = [];

if (!empty($client_filter)) {
    $query .= " AND (UPPER(r.client) LIKE UPPER(?) OR UPPER(rm.client) LIKE UPPER(?))";
    $params[] = '%' . $client_filter . '%';
    $params[] = '%' . $client_filter . '%';
}

if ($room_filter > 0) {
    $query .= " AND r.room_id = ?";
    $params[] = $room_filter;
}

if (!empty($city_filter)) {
    $query .= " AND UPPER(r.city) = UPPER(?)";
    $params[] = $city_filter;
}

if (!empty($location_filter)) {
    $query .= " AND UPPER(r.location) = UPPER(?)";
    $params[] = $location_filter;
}

$query .= " ORDER BY r.client ASC, r.city ASC, r.location ASC, rm.name ASC, r.name ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$racks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$hide_content_header = true;
require_once __DIR__ . '/../partials/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs4@1.11.5/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="css/rack_infographic.css?v=<?php echo filemtime(__DIR__ . '/css/rack_infographic.css'); ?>">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<style>
    /* Estilos personalizados para DataTables en Racks */
    .dataTables_wrapper .dataTables_filter {
        text-align: right;
        margin-bottom: 0.5rem;
    }
    .dataTables_wrapper .dataTables_filter label {
        font-weight: 600;
        color: #495057;
        margin-bottom: 0;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .dataTables_wrapper .dataTables_filter input {
        border-radius: 6px;
        border: 1px solid #ced4da;
        padding: 5px 12px;
        font-size: 0.875rem;
        width: 260px !important;
        background-color: #fff;
        transition: all 0.2s ease-in-out;
    }
    .dataTables_wrapper .dataTables_filter input:focus {
        border-color: #ffc107;
        box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25);
        outline: none;
    }
    .dataTables_wrapper .dataTables_length {
        margin-bottom: 0.5rem;
    }
    .dataTables_wrapper .dataTables_length label {
        font-weight: 500;
        color: #495057;
        margin-bottom: 0;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .dataTables_wrapper .dataTables_length select {
        border-radius: 6px;
        border: 1px solid #ced4da;
        padding: 4px 8px;
        font-size: 0.875rem;
    }
    table.dataTable thead th {
        vertical-align: middle !important;
        user-select: none;
    }
    table.dataTable thead th:not(.no-sort) {
        cursor: pointer;
    }
    table.dataTable thead th:not(.no-sort):hover {
        background-color: #f1f3f5 !important;
    }
    .dataTables_wrapper .dataTables_info {
        font-size: 0.85rem;
        color: #6c757d;
        padding-top: 0.75rem;
    }
    .dataTables_wrapper .dataTables_paginate {
        padding-top: 0.5rem;
    }
    .dataTables_wrapper .pagination .page-item .page-link {
        font-size: 0.85rem;
        font-weight: 500;
    }
    .dataTables_wrapper .pagination .page-item.active .page-link {
        background-color: #ffc107;
        border-color: #ffc107;
        color: #212529 !important;
    }
</style>

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
                <i class="fas fa-server text-warning mr-2"></i>Gestión de Racks / Gabinetes de Datacenter
            </h4>
            <p class="text-muted small m-0">
                Administración de bastidores físicos, asignación geográfica por ciudad y ubicación, y mapeo de unidades U.
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
            <a href="viewer_3d.php<?php echo $client_filter ? '?cliente=' . urlencode($client_filter) : ''; ?>" class="btn btn-info btn-sm font-weight-bold shadow-sm">
                <i class="fas fa-cube mr-1"></i> 3DViewer
            </a>
            <button class="btn btn-primary btn-sm font-weight-bold shadow-sm" data-toggle="modal" data-target="#createRackModal">
                <i class="fas fa-plus mr-1"></i> Crear Nuevo Rack
            </button>
        </div>
    </div>

    <!-- TARJETA PRINCIPAL -->
    <div class="card card-outline card-warning shadow-sm" style="border-radius: 10px; overflow: hidden;">
        <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap" style="gap: 10px;">
            <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
                <span class="font-weight-bold text-dark small"><i class="fas fa-filter text-muted mr-1"></i>Filtros:</span>
                
                <?php if ($room_filter): ?>
                    <span class="badge badge-info px-2 py-1">
                        Cuarto ID: <?php echo $room_filter; ?>
                        <a href="racks.php<?php echo $client_filter ? '?cliente=' . urlencode($client_filter) : ''; ?>" class="text-white ml-1">&times;</a>
                    </span>
                <?php endif; ?>

                <?php if (!empty($client_filter)): ?>
                    <span class="badge badge-warning text-dark px-2 py-1">
                        Cliente: <?php echo htmlspecialchars($client_filter); ?>
                        <?php if (has_role('SUPER_ADMIN')): ?>
                            <a href="racks.php" class="text-dark ml-1">&times;</a>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="text-muted small">
                Total Racks registrados: <strong><?php echo count($racks); ?></strong>
            </div>
        </div>

        <div class="card-body p-3">
            <div class="table-responsive">
                <table id="racksTable" class="table table-striped table-hover table-bordered m-0" style="font-size: 0.85rem; width: 100%;">
                    <thead class="bg-light">
                        <tr>
                            <th style="width: 50px;">ID</th>
                            <th>Cliente</th>
                            <th>Ciudad</th>
                            <th>Ubicación</th>
                            <th>Cuarto / Sala</th>
                            <th>Nombre Rack</th>
                            <th style="width: 110px;">Capacidad (U)</th>
                            <th style="width: 100px;">Equipos</th>
                            <th style="width: 220px;" class="text-center no-sort">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($racks as $rack): ?>
                            <tr>
                                <td class="font-weight-bold text-muted" data-order="<?php echo (int)$rack['id']; ?>"><?php echo $rack['id']; ?></td>
                                <td data-order="<?php echo htmlspecialchars($rack['client'] ?: 'VILASECA'); ?>">
                                    <span class="badge badge-warning text-dark font-weight-bold">
                                        <i class="fas fa-building mr-1"></i><?php echo htmlspecialchars($rack['client'] ?: 'VILASECA'); ?>
                                    </span>
                                </td>
                                <td data-order="<?php echo htmlspecialchars($rack['city'] ?: ''); ?>">
                                    <span class="badge badge-light border text-dark font-weight-bold">
                                        <i class="fas fa-city mr-1 text-info"></i><?php echo htmlspecialchars($rack['city'] ?: 'No asignada'); ?>
                                    </span>
                                </td>
                                <td data-order="<?php echo htmlspecialchars($rack['location'] ?: ''); ?>">
                                    <span class="font-weight-bold text-primary">
                                        <i class="fas fa-map-marker-alt mr-1 text-danger"></i><?php echo htmlspecialchars($rack['location'] ?: 'General'); ?>
                                    </span>
                                </td>
                                <td data-order="<?php echo htmlspecialchars($rack['room_name'] ?? ''); ?>">
                                    <span class="badge badge-info">
                                        <i class="fas fa-door-open mr-1"></i><?php echo htmlspecialchars($rack['room_name'] ?? 'Sin Cuarto'); ?>
                                    </span>
                                </td>
                                <td data-order="<?php echo htmlspecialchars($rack['name']); ?>">
                                    <strong class="text-navy" style="font-size: 0.92rem;">
                                        <i class="fas fa-server text-muted mr-1"></i><?php echo htmlspecialchars($rack['name']); ?>
                                    </strong>
                                    <?php if (!empty($rack['description'])): ?>
                                        <small class="text-muted d-block"><?php echo htmlspecialchars($rack['description']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td data-order="<?php echo (int)$rack['total_u']; ?>">
                                    <span class="badge badge-dark"><?php echo htmlspecialchars($rack['total_u']); ?>U</span>
                                    <small class="d-block text-muted">
                                        <i class="fas fa-arrow-<?php echo ($rack['numbering_dir'] ?? 'DOWN') === 'UP' ? 'down' : 'up'; ?>"></i>
                                        <?php echo ($rack['numbering_dir'] ?? 'DOWN') === 'UP' ? 'U1 Arriba' : 'U1 Abajo'; ?>
                                    </small>
                                </td>
                                <td data-order="<?php echo (int)$rack['device_count']; ?>">
                                    <span class="badge badge-success px-2 py-1">
                                        <i class="fas fa-microchip mr-1"></i><?php echo $rack['device_count']; ?> equipos
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-primary font-weight-bold" title="Ver Diagrama Infográfico (Solo Lectura)" onclick="openRackView(<?php echo $rack['id']; ?>)">
                                            <i class="fas fa-eye mr-1"></i> View
                                        </button>
                                        <a href="viewer_3d.php?room_id=<?php echo $rack['room_id']; ?>&rack_id=<?php echo $rack['id']; ?><?php echo $client_filter ? '&cliente=' . urlencode($client_filter) : ''; ?>" class="btn btn-outline-info font-weight-bold" title="Ver en 3DViewer">
                                            <i class="fas fa-cube"></i>
                                        </a>
                                        <a href="rack_builder.php?id=<?php echo $rack['id']; ?><?php echo $client_filter ? '&cliente=' . urlencode($client_filter) : ''; ?>" class="btn btn-outline-secondary font-weight-bold" title="Abrir Rack Builder 2D">
                                            <i class="fas fa-cubes"></i>
                                        </a>
                                        <button type="button" class="btn btn-info font-weight-bold" title="Editar Propiedades" onclick="editRack(<?php echo htmlspecialchars(json_encode($rack), ENT_QUOTES, 'UTF-8'); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-danger font-weight-bold" title="Eliminar Rack" onclick="deleteRack(<?php echo $rack['id']; ?>, '<?php echo addslashes($rack['name']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- DATALISTS PARA AUTOCOMPLETADO DE CIUDADES Y UBICACIONES -->
<datalist id="list_cities">
    <?php foreach ($knownCities as $kc): ?>
        <option value="<?php echo htmlspecialchars($kc); ?>">
    <?php endforeach; ?>
</datalist>

<datalist id="list_locations">
    <?php foreach ($knownLocations as $kl): ?>
        <option value="<?php echo htmlspecialchars($kl); ?>">
    <?php endforeach; ?>
</datalist>

<!-- MODAL CREAR RACK -->
<div class="modal fade" id="createRackModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title font-weight-bold">
                    <i class="fas fa-plus-circle mr-2"></i> Crear Nuevo Rack / Gabinete
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-body p-4">
                    <div class="alert alert-info py-2 px-3 small mb-3">
                        <i class="fas fa-info-circle mr-1"></i> Para garantizar la integridad técnica y prevenir duplicados, <strong>Cliente, Ciudad, Ubicación y Nombre del Rack son obligatorios</strong>. No se permite crear dos racks con el mismo nombre en la misma ciudad y ubicación.
                    </div>

                    <div class="row">
                        <!-- CLIENTE (OBLIGATORIO) -->
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-navy small">Cliente <span class="text-danger">*</span></label>
                            <input type="text" name="client" class="form-control font-weight-bold text-uppercase" 
                                   value="<?php echo htmlspecialchars($client_filter ?: 'VILASECA'); ?>" 
                                   required placeholder="Ej. VILASECA">
                            <small class="text-muted">Asigna este rack al cliente específico.</small>
                        </div>

                        <!-- CIUDAD (OBLIGATORIO) -->
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-navy small">Ciudad <span class="text-danger">*</span></label>
                            <input type="text" name="city" list="list_cities" class="form-control font-weight-bold" 
                                   required placeholder="Ej. Quito, Guayaquil...">
                            <small class="text-muted">Ciudad física donde se ubica el bastidor.</small>
                        </div>

                        <!-- UBICACIÓN (OBLIGATORIO) -->
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-navy small">Ubicación / Sede <span class="text-danger">*</span></label>
                            <input type="text" name="location" list="list_locations" class="form-control font-weight-bold" 
                                   required placeholder="Ej. FADESA QUITO, SEMVRA QUITO...">
                            <small class="text-muted">Edificio, planta o sede técnica.</small>
                        </div>

                        <!-- NOMBRE DEL RACK (OBLIGATORIO) -->
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-navy small">Nombre del Rack <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control font-weight-bold" 
                                   required placeholder="Ej. RACK 01, R01, Gabinete Core">
                            <small class="text-muted">Identificador único dentro de la sede.</small>
                        </div>

                        <!-- CUARTO / SALA (ROOM) -->
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-navy small">Cuarto / Sala (Opcional)</label>
                            <select name="room_id" class="form-control font-weight-bold">
                                <option value="0">-- Sin Asignar a Sala --</option>
                                <?php foreach ($rooms as $rm): ?>
                                    <option value="<?php echo $rm['id']; ?>" <?php echo $room_filter == $rm['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($rm['name'] . ($rm['location'] ? ' (' . $rm['location'] . ')' : '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Permite ubicar el rack en el plano 2D de Datacenter.</small>
                        </div>

                        <!-- CAPACIDAD EN U -->
                        <div class="col-md-3 form-group">
                            <label class="font-weight-bold text-navy small">Capacidad (U) <span class="text-danger">*</span></label>
                            <input type="number" name="total_u" class="form-control font-weight-bold" value="42" min="1" max="100" required>
                        </div>

                        <!-- DIRECCIÓN DE NUMERACIÓN -->
                        <div class="col-md-3 form-group">
                            <label class="font-weight-bold text-navy small">Numeración U1</label>
                            <select name="numbering_dir" class="form-control">
                                <option value="DOWN">Abajo (U1 inferior)</option>
                                <option value="UP">Arriba (U1 superior)</option>
                            </select>
                        </div>

                        <!-- DESCRIPCIÓN -->
                        <div class="col-md-12 form-group mb-0">
                            <label class="font-weight-bold text-navy small">Descripción / Notas</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Propósito del rack, equipos principales, etc."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm font-weight-bold" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-sm font-weight-bold px-3">
                        <i class="fas fa-save mr-1"></i> Guardar Rack
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EDITAR RACK -->
<div class="modal fade" id="editRackModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header bg-info text-white py-3">
                <h5 class="modal-title font-weight-bold">
                    <i class="fas fa-edit mr-2"></i> Editar Propiedades del Rack
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_rack_id">
                <div class="modal-body p-4">
                    <div class="row">
                        <!-- CLIENTE -->
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-navy small">Cliente <span class="text-danger">*</span></label>
                            <input type="text" name="client" id="edit_client" class="form-control font-weight-bold text-uppercase" required>
                        </div>

                        <!-- CIUDAD -->
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-navy small">Ciudad <span class="text-danger">*</span></label>
                            <input type="text" name="city" id="edit_city" list="list_cities" class="form-control font-weight-bold" required>
                        </div>

                        <!-- UBICACIÓN -->
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-navy small">Ubicación / Sede <span class="text-danger">*</span></label>
                            <input type="text" name="location" id="edit_location" list="list_locations" class="form-control font-weight-bold" required>
                        </div>

                        <!-- NOMBRE -->
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-navy small">Nombre del Rack <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="edit_name" class="form-control font-weight-bold" required>
                        </div>

                        <!-- CUARTO -->
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-navy small">Cuarto / Sala</label>
                            <select name="room_id" id="edit_room_id" class="form-control font-weight-bold">
                                <option value="0">-- Sin Asignar a Sala --</option>
                                <?php foreach ($rooms as $rm): ?>
                                    <option value="<?php echo $rm['id']; ?>">
                                        <?php echo htmlspecialchars($rm['name'] . ($rm['location'] ? ' (' . $rm['location'] . ')' : '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- CAPACIDAD U -->
                        <div class="col-md-3 form-group">
                            <label class="font-weight-bold text-navy small">Capacidad (U) <span class="text-danger">*</span></label>
                            <input type="number" name="total_u" id="edit_total_u" class="form-control font-weight-bold" min="1" max="100" required>
                        </div>

                        <!-- NUMERACIÓN -->
                        <div class="col-md-3 form-group">
                            <label class="font-weight-bold text-navy small">Numeración U1</label>
                            <select name="numbering_dir" id="edit_numbering_dir" class="form-control">
                                <option value="DOWN">Abajo (U1 inferior)</option>
                                <option value="UP">Arriba (U1 superior)</option>
                            </select>
                        </div>

                        <!-- DESCRIPCIÓN -->
                        <div class="col-md-12 form-group mb-0">
                            <label class="font-weight-bold text-navy small">Descripción / Notas</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm font-weight-bold" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-info btn-sm font-weight-bold px-3">
                        <i class="fas fa-save mr-1"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function deleteRack(id, name) {
        if (confirm(`¿Estás seguro de que deseas eliminar el rack "${name}"? Se perderán las asignaciones de equipos dentro del bastidor.`)) {
            let form = document.createElement('form');
            form.method = 'POST';
            form.action = 'racks.php<?php echo $client_filter ? "?cliente=" . urlencode($client_filter) : ""; ?>';
            
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

    function editRack(rack) {
        $("#edit_rack_id").val(rack.id);
        $("#edit_client").val(rack.client || 'VILASECA');
        $("#edit_city").val(rack.city || '');
        $("#edit_location").val(rack.location || '');
        $("#edit_name").val(rack.name);
        $("#edit_room_id").val(rack.room_id || 0);
        $("#edit_total_u").val(rack.total_u);
        $("#edit_numbering_dir").val(rack.numbering_dir || 'DOWN');
        $("#edit_description").val(rack.description || '');
        $("#editRackModal").modal('show');
    }
</script>

<?php require_once __DIR__ . '/partials/rack_infographic_modal.php'; ?>

<!-- DataTables JS & Bootstrap 4 integration -->
<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs4@1.11.5/js/dataTables.bootstrap4.min.js"></script>

<script>
$(document).ready(function() {
    $('#racksTable').DataTable({
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Todos"]],
        order: [[5, 'asc']], // Ordenar por Nombre de Rack ascendente por defecto
        columnDefs: [
            { targets: 'no-sort', orderable: false, searchable: false }
        ],
        dom: "<'row mb-2 align-items-center'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6 text-md-right'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row mt-3 align-items-center'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7 d-flex justify-content-md-end'p>>",
        language: {
            search: "<i class='fas fa-search text-muted mr-1'></i> Buscar:",
            searchPlaceholder: "Nombre, ciudad, sede, cliente...",
            lengthMenu: "Mostrar _MENU_ racks por página",
            info: "Mostrando _START_ a _END_ de _TOTAL_ racks",
            infoEmpty: "Mostrando 0 a 0 de 0 racks",
            infoFiltered: "(filtrado de un total de _MAX_ racks)",
            zeroRecords: "<div class='py-4 text-center text-muted'><i class='fas fa-search fa-2x mb-2 text-muted opacity-50 d-block'></i>No se encontraron racks que coincidan con la búsqueda.</div>",
            emptyTable: "<div class='py-4 text-center text-muted'><i class='fas fa-server fa-2x mb-2 text-muted opacity-50 d-block'></i>No hay racks registrados en el sistema.</div>",
            paginate: {
                first: '<i class="fas fa-angle-double-left"></i>',
                previous: '<i class="fas fa-chevron-left"></i> Anterior',
                next: 'Siguiente <i class="fas fa-chevron-right"></i>',
                last: '<i class="fas fa-angle-double-right"></i>'
            }
        },
        autoWidth: false
    });
});
</script>

<script src="js/rack_infographic.js?v=<?php echo filemtime(__DIR__ . '/js/rack_infographic.js'); ?>"></script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>

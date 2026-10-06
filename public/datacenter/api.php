<?php
/**
 * Datacenter API (AJAX endpoints for Rack Builder)
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$pdo = getPDO();
$action = $_REQUEST['action'] ?? '';

try {
    if ($action === 'get_devices') {
        $rack_id = $_GET['rack_id'] ?? 0;
        $stmt = $pdo->prepare("
            SELECT d.*, i.hostname as cmdb_hostname, i.attributes_json as cmdb_attrs 
            FROM dc_rack_devices d
            LEFT JOIN ci_instances i ON d.cmdb_reference = i.id
            WHERE d.rack_id = ?
            ORDER BY d.start_u ASC
        ");
        $stmt->execute([$rack_id]);
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decodificar JSON
        foreach ($devices as &$dev) {
            $dev['details'] = json_decode($dev['details_json'], true) ?: [];
            unset($dev['details_json']);
            
            if ($dev['cmdb_reference']) {
                $cmdb_attrs = json_decode($dev['cmdb_attrs'], true) ?: [];
                $dev['cmdb_imagen_frontal'] = $cmdb_attrs['imagen_frontal'] ?? '';
                $dev['cmdb_imagen_trasera'] = $cmdb_attrs['imagen_trasera'] ?? '';
                
                // Sobrescribir campos si vienen del CI para mantener consistencia
                if (!empty($cmdb_attrs['marca'])) $dev['details']['make'] = $cmdb_attrs['marca'];
                if (!empty($cmdb_attrs['modelo'])) $dev['details']['model'] = $cmdb_attrs['modelo'];
                if (!empty($cmdb_attrs['serial_number'])) $dev['details']['serial_number'] = $cmdb_attrs['serial_number'];
                if (!empty($cmdb_attrs['asset_tag'])) $dev['details']['asset_tag'] = $cmdb_attrs['asset_tag'];
                if (!empty($dev['cmdb_hostname'])) $dev['name'] = $dev['cmdb_hostname'];
            }
            unset($dev['cmdb_attrs']);
        }
        
        echo json_encode(['success' => true, 'data' => $devices]);
        
    } elseif ($action === 'save_device') {
        $id = $_POST['id'] ?? 0;
        $rack_id = $_POST['rack_id'] ?? 0;
        $name = $_POST['name'] ?? 'Dispositivo Desconocido';
        $start_u = (int)($_POST['start_u'] ?? 1);
        $height_u = (int)($_POST['height_u'] ?? 1);
        $orientation = $_POST['orientation'] ?? 'front';
        $cmdb_ref = $_POST['cmdb_reference'] ?? '';
        
        // Campos dinámicos del JSON
        $details = [
            'make' => $_POST['make'] ?? '',
            'model' => $_POST['model'] ?? '',
            'serial_number' => $_POST['serial_number'] ?? '',
            'asset_tag' => $_POST['asset_tag'] ?? '',
            'server_function' => $_POST['server_function'] ?? '',
            'owner' => $_POST['owner'] ?? '',
            'ip_address' => $_POST['ip_address'] ?? '',
            'mac_address' => $_POST['mac_address'] ?? '',
            'warranty' => $_POST['warranty'] ?? '',
            'weight' => $_POST['weight'] ?? '',
            'watts' => $_POST['watts'] ?? '',
            'amps' => $_POST['amps'] ?? '',
            'voltage' => $_POST['voltage'] ?? '',
            'color' => $_POST['color'] ?? '#2a2a2a', // Color de fondo visual en el rack
            'depth' => $_POST['depth'] ?? 'full',
            'mounting' => $_POST['mounting'] ?? 'horizontal',
            'outlets_c13' => $_POST['outlets_c13'] ?? '',
            'outlets_c19' => $_POST['outlets_c19'] ?? '',
            'outlets_nema' => $_POST['outlets_nema'] ?? ''
        ];
        
        $details_json = json_encode($details);
        
        $pdo->beginTransaction();
        try {
            if ($id > 0) {
                // Obtener referencia cmdb anterior
                $stmt_prev = $pdo->prepare("SELECT cmdb_reference FROM dc_rack_devices WHERE id = ?");
                $stmt_prev->execute([$id]);
                $prev_cmdb_ref = $stmt_prev->fetchColumn();
                
                // Si la referencia CMDB cambió, desvincular la anterior
                if ($prev_cmdb_ref && $prev_cmdb_ref != $cmdb_ref) {
                    $stmt_ci = $pdo->prepare("SELECT attributes_json FROM ci_instances WHERE id = ?");
                    $stmt_ci->execute([$prev_cmdb_ref]);
                    $ci = $stmt_ci->fetch(PDO::FETCH_ASSOC);
                    if ($ci) {
                        $ci_attrs = json_decode($ci['attributes_json'], true) ?: [];
                        unset($ci_attrs['rack_id'], $ci_attrs['rack_start_u'], $ci_attrs['rack_height_u'], $ci_attrs['rack_orientation'], $ci_attrs['rack_color'], $ci_attrs['rack_depth'], $ci_attrs['rack_mounting'], $ci_attrs['rack_outlets_c13'], $ci_attrs['rack_outlets_c19'], $ci_attrs['rack_outlets_nema']);
                        $pdo->prepare("UPDATE ci_instances SET attributes_json = ? WHERE id = ?")->execute([json_encode($ci_attrs), $prev_cmdb_ref]);
                    }
                }

                // Actualizar
                $stmt = $pdo->prepare("UPDATE dc_rack_devices SET name=?, start_u=?, height_u=?, orientation=?, cmdb_reference=?, details_json=? WHERE id=? AND rack_id=?");
                $stmt->execute([$name, $start_u, $height_u, $orientation, $cmdb_ref, $details_json, $id, $rack_id]);
                $device_db_id = $id;
                $msg = 'Dispositivo actualizado';
            } else {
                // Insertar
                $stmt = $pdo->prepare("INSERT INTO dc_rack_devices (rack_id, name, start_u, height_u, orientation, cmdb_reference, details_json) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$rack_id, $name, $start_u, $height_u, $orientation, $cmdb_ref, $details_json]);
                $device_db_id = $pdo->lastInsertId();
                $msg = 'Dispositivo agregado';
            }

            // Sincronizar bidireccionalmente con CMDB si está vinculado
            if (!empty($cmdb_ref)) {
                $stmt_ci = $pdo->prepare("SELECT hostname, attributes_json FROM ci_instances WHERE id = ?");
                $stmt_ci->execute([$cmdb_ref]);
                $ci = $stmt_ci->fetch(PDO::FETCH_ASSOC);
                if ($ci) {
                    $ci_attrs = json_decode($ci['attributes_json'], true) ?: [];
                    $ci_attrs['rack_id'] = (string)$rack_id;
                    $ci_attrs['rack_start_u'] = (string)$start_u;
                    $ci_attrs['rack_height_u'] = (string)$height_u;
                    $ci_attrs['rack_orientation'] = $orientation;
                    $ci_attrs['rack_color'] = $details['color'];
                    $ci_attrs['rack_depth'] = $details['depth'];
                    $ci_attrs['rack_mounting'] = $details['mounting'];
                    $ci_attrs['rack_outlets_c13'] = $details['outlets_c13'] ?? '';
                    $ci_attrs['rack_outlets_c19'] = $details['outlets_c19'] ?? '';
                    $ci_attrs['rack_outlets_nema'] = $details['outlets_nema'] ?? '';
                    
                    // Sincronizar campos hacia el CI
                    if (!empty($details['make'])) $ci_attrs['marca'] = $details['make'];
                    if (!empty($details['model'])) $ci_attrs['modelo'] = $details['model'];
                    if (!empty($details['serial_number'])) $ci_attrs['serial_number'] = $details['serial_number'];
                    if (!empty($details['asset_tag'])) $ci_attrs['asset_tag'] = $details['asset_tag'];
                    
                    $updated_ci_attrs = json_encode($ci_attrs);
                    
                    $stmt_up_ci = $pdo->prepare("UPDATE ci_instances SET hostname = ?, attributes_json = ? WHERE id = ?");
                    $stmt_up_ci->execute([$name, $updated_ci_attrs, $cmdb_ref]);
                }
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => $msg, 'id' => $device_db_id]);
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } elseif ($action === 'delete_device') {
        $id = $_POST['id'] ?? 0;
        $pdo->beginTransaction();
        try {
            // Desvincular de la CMDB primero si estaba vinculado
            $stmt_ref = $pdo->prepare("SELECT cmdb_reference FROM dc_rack_devices WHERE id = ?");
            $stmt_ref->execute([$id]);
            $cmdb_ref = $stmt_ref->fetchColumn();
            
            if ($cmdb_ref) {
                $stmt_ci = $pdo->prepare("SELECT attributes_json FROM ci_instances WHERE id = ?");
                $stmt_ci->execute([$cmdb_ref]);
                $ci = $stmt_ci->fetch(PDO::FETCH_ASSOC);
                if ($ci) {
                    $ci_attrs = json_decode($ci['attributes_json'], true) ?: [];
                    unset($ci_attrs['rack_id'], $ci_attrs['rack_start_u'], $ci_attrs['rack_height_u'], $ci_attrs['rack_orientation'], $ci_attrs['rack_color'], $ci_attrs['rack_depth'], $ci_attrs['rack_mounting'], $ci_attrs['rack_outlets_c13'], $ci_attrs['rack_outlets_c19'], $ci_attrs['rack_outlets_nema']);
                    $pdo->prepare("UPDATE ci_instances SET attributes_json = ? WHERE id = ?")->execute([json_encode($ci_attrs), $cmdb_ref]);
                }
            }
            
            $stmt = $pdo->prepare("DELETE FROM dc_rack_devices WHERE id = ?");
            $stmt->execute([$id]);
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Dispositivo eliminado']);
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } elseif ($action === 'update_device_u_position') {
        $id = (int)($_POST['id'] ?? 0);
        $start_u = (int)($_POST['start_u'] ?? 1);
        $orientation = $_POST['orientation'] ?? null;
        
        if ($id > 0) {
            $pdo->beginTransaction();
            try {
                // Obtener datos actuales del dispositivo
                $stmt = $pdo->prepare("SELECT rack_id, cmdb_reference, details_json, orientation, height_u FROM dc_rack_devices WHERE id = ?");
                $stmt->execute([$id]);
                $dev = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($dev) {
                    $details = json_decode($dev['details_json'], true) ?: [];
                    $depth = $details['depth'] ?? 'full';
                    
                    if ($depth !== 'full' && $orientation !== null) {
                        $stmt_up = $pdo->prepare("UPDATE dc_rack_devices SET start_u = ?, orientation = ? WHERE id = ?");
                        $stmt_up->execute([$start_u, $orientation, $id]);
                    } else {
                        $stmt_up = $pdo->prepare("UPDATE dc_rack_devices SET start_u = ? WHERE id = ?");
                        $stmt_up->execute([$start_u, $id]);
                    }
                    
                    // Sincronizar con CMDB
                    $cmdb_ref = $dev['cmdb_reference'];
                    if ($cmdb_ref) {
                        $stmt_ci = $pdo->prepare("SELECT attributes_json FROM ci_instances WHERE id = ?");
                        $stmt_ci->execute([$cmdb_ref]);
                        $ci = $stmt_ci->fetch(PDO::FETCH_ASSOC);
                        if ($ci) {
                            $ci_attrs = json_decode($ci['attributes_json'], true) ?: [];
                            $ci_attrs['rack_start_u'] = (string)$start_u;
                            if ($depth !== 'full' && $orientation !== null) {
                                $ci_attrs['rack_orientation'] = $orientation;
                            }
                            
                            $pdo->prepare("UPDATE ci_instances SET attributes_json = ? WHERE id = ?")->execute([json_encode($ci_attrs), $cmdb_ref]);
                        }
                    }
                }
                
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Posición U actualizada']);
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'ID de dispositivo inválido']);
        }
        
    } elseif ($action === 'update_rack_position') {
        $rack_id = (int)($_POST['rack_id'] ?? 0);
        $grid_x = (int)($_POST['grid_x'] ?? 0);
        $grid_y = (int)($_POST['grid_y'] ?? 0);
        
        if ($rack_id > 0) {
            $stmt = $pdo->prepare("UPDATE dc_racks SET grid_x = ?, grid_y = ? WHERE id = ?");
            $stmt->execute([$grid_x, $grid_y, $rack_id]);
            echo json_encode(['success' => true, 'message' => 'Posición actualizada']);
        } else {
            echo json_encode(['success' => false, 'message' => 'ID de rack inválido']);
        }
    } elseif ($action === 'update_rack_rotation') {
        $rack_id = (int)($_POST['rack_id'] ?? 0);
        $rotation = (int)($_POST['rotation'] ?? 0);
        
        if ($rack_id > 0) {
            $stmt = $pdo->prepare("UPDATE dc_racks SET rotation = ? WHERE id = ?");
            $stmt->execute([$rotation, $rack_id]);
            echo json_encode(['success' => true, 'message' => 'Rotación actualizada']);
        } else {
            echo json_encode(['success' => false, 'message' => 'ID de rack inválido']);
        }
        
    } elseif ($action === 'save_rack_drag') {
        $room_id = (int)($_POST['room_id'] ?? 0);
        $name = $_POST['name'] ?? 'Nuevo Rack';
        $grid_x = (int)($_POST['grid_x'] ?? 0);
        $grid_y = (int)($_POST['grid_y'] ?? 0);
        $width_tiles = (int)($_POST['width_tiles'] ?? 1);
        $depth_tiles = (int)($_POST['depth_tiles'] ?? 2);
        $total_u = (int)($_POST['total_u'] ?? 42);
        $rotation = (int)($_POST['rotation'] ?? 0);
        
        if ($room_id > 0) {
            $stmt = $pdo->prepare("INSERT INTO dc_racks (room_id, name, grid_x, grid_y, width_tiles, depth_tiles, total_u, rotation) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$room_id, $name, $grid_x, $grid_y, $width_tiles, $depth_tiles, $total_u, $rotation]);
            echo json_encode(['success' => true, 'message' => 'Rack creado', 'id' => $pdo->lastInsertId()]);
        } else {
            echo json_encode(['success' => false, 'message' => 'ID de cuarto inválido']);
        }

    } elseif ($action === 'get_floor_items') {
        $room_id = (int)($_GET['room_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT i.*, l.name as layer_name, l.z_index 
                               FROM dc_floor_items i 
                               LEFT JOIN dc_floor_layers l ON i.layer_id = l.id 
                               WHERE i.room_id = ?");
        $stmt->execute([$room_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $items]);
        
    } elseif ($action === 'save_floor_item') {
        $id = (int)($_POST['id'] ?? 0);
        $room_id = (int)($_POST['room_id'] ?? 0);
        $name = $_POST['name'] ?? 'Nuevo Ítem';
        $type = $_POST['type'] ?? 'unknown';
        $layer_id = !empty($_POST['layer_id']) ? (int)$_POST['layer_id'] : null;
        $width_tiles = (float)($_POST['width_tiles'] ?? 1);
        $depth_tiles = (float)($_POST['depth_tiles'] ?? 1);
        $height_meters = (float)($_POST['height_meters'] ?? 0);
        $grid_x = (float)($_POST['grid_x'] ?? 0);
        $grid_y = (float)($_POST['grid_y'] ?? 0);
        $rotation = (int)($_POST['rotation'] ?? 0);
        
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE dc_floor_items SET name=?, type=?, layer_id=?, width_tiles=?, depth_tiles=?, height_meters=?, rotation=? WHERE id=?");
            $stmt->execute([$name, $type, $layer_id, $width_tiles, $depth_tiles, $height_meters, $rotation, $id]);
            echo json_encode(['success' => true, 'message' => 'Ítem actualizado']);
        } else {
            $stmt = $pdo->prepare("INSERT INTO dc_floor_items (room_id, name, type, layer_id, grid_x, grid_y, width_tiles, depth_tiles, height_meters, rotation) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$room_id, $name, $type, $layer_id, $grid_x, $grid_y, $width_tiles, $depth_tiles, $height_meters, $rotation]);
            echo json_encode(['success' => true, 'message' => 'Ítem creado', 'id' => $pdo->lastInsertId()]);
        }
        
    } elseif ($action === 'delete_floor_item') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM dc_floor_items WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Ítem eliminado']);
        
    } elseif ($action === 'update_floor_item_position') {
        $id = (int)($_POST['id'] ?? 0);
        $grid_x = (float)($_POST['grid_x'] ?? 0);
        $grid_y = (float)($_POST['grid_y'] ?? 0);
        
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE dc_floor_items SET grid_x = ?, grid_y = ? WHERE id = ?");
            $stmt->execute([$grid_x, $grid_y, $id]);
            echo json_encode(['success' => true, 'message' => 'Posición de ítem actualizada']);
        } else {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
        }
        
    } elseif ($action === 'update_floor_item_rotation') {
        $id = (int)($_POST['id'] ?? 0);
        $rotation = (int)($_POST['rotation'] ?? 0);
        
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE dc_floor_items SET rotation = ? WHERE id = ?");
            $stmt->execute([$rotation, $id]);
            echo json_encode(['success' => true, 'message' => 'Rotación de ítem actualizada']);
        } else {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
        }
        
    } elseif ($action === 'update_item_z_index') {
        $id = (int)($_POST['id'] ?? 0);
        $type = $_POST['type'] ?? '';
        $z_index = (int)($_POST['z_index'] ?? 0);
        
        if ($id > 0 && ($type === 'rack' || $type === 'item')) {
            if ($type === 'rack') {
                $stmt = $pdo->prepare("UPDATE dc_racks SET z_index = ? WHERE id = ?");
            } else {
                $stmt = $pdo->prepare("UPDATE dc_floor_items SET z_index = ? WHERE id = ?");
            }
            $stmt->execute([$z_index, $id]);
            echo json_encode(['success' => true, 'message' => 'Orden de capa (z-index) actualizado']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Parámetros inválidos']);
        }
        
    } elseif ($action === 'get_layers') {
        $stmt = $pdo->query("SELECT * FROM dc_floor_layers ORDER BY z_index ASC");
        $layers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $layers]);
        
    } elseif ($action === 'save_layer') {
        $id = (int)($_POST['id'] ?? 0);
        $name = $_POST['name'] ?? 'Nueva Capa';
        $z_index = (int)($_POST['z_index'] ?? 10);
        
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE dc_floor_layers SET name=?, z_index=? WHERE id=?");
            $stmt->execute([$name, $z_index, $id]);
            echo json_encode(['success' => true, 'message' => 'Capa actualizada']);
        } else {
            $stmt = $pdo->prepare("INSERT INTO dc_floor_layers (name, z_index) VALUES (?, ?)");
            $stmt->execute([$name, $z_index]);
            echo json_encode(['success' => true, 'message' => 'Capa creada', 'id' => $pdo->lastInsertId()]);
        }
        
    } elseif ($action === 'delete_layer') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM dc_floor_layers WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Capa eliminada']);
        
    } elseif ($action === 'get_zabbix_hostgroups') {
        require_once __DIR__ . '/../../src/zabbix_api.php';
        $response = call_zabbix_api('hostgroup.get', [
            'output' => ['groupid', 'name'],
            'sortfield' => 'name'
        ]);
        if (isset($response['error'])) {
            echo json_encode(['success' => false, 'message' => $response['error']]);
        } else {
            echo json_encode(['success' => true, 'data' => $response['result']]);
        }
        
    } elseif ($action === 'get_zabbix_hosts') {
        require_once __DIR__ . '/../../src/zabbix_api.php';
        $groupid = $_GET['groupid'] ?? null;
        if ($groupid === '') {
            $groupid = null;
        }
        $params = [
            'output' => ['hostid', 'host', 'name'],
            'selectInterfaces' => ['ip'],
            'selectInventory' => 'extend',
            'sortfield' => 'name'
        ];
        if ($groupid) {
            $params['groupids'] = $groupid;
        }
        $response = call_zabbix_api('host.get', $params);
        if (isset($response['error'])) {
            echo json_encode(['success' => false, 'message' => $response['error']]);
        } else {
            $hosts = array_map(function($h) {
                $ip = '';
                if (!empty($h['interfaces'])) {
                    $ip = $h['interfaces'][0]['ip'];
                }
                $inv = $h['inventory'] ?? [];
                return [
                    'hostid' => $h['hostid'],
                    'name' => $h['name'],
                    'ip' => $ip,
                    'make' => $inv['vendor'] ?? '',
                    'model' => $inv['model'] ?? '',
                    'serial' => $inv['serialno_a'] ?? '',
                    'asset_tag' => $inv['tag'] ?? '',
                    'owner' => $inv['contact'] ?? '',
                    'notes' => $inv['notes'] ?? ''
                ];
            }, $response['result']);
            echo json_encode(['success' => true, 'data' => $hosts]);
        }
        
    } elseif ($action === 'get_rack_infographic') {
        $rack_id = (int)($_GET['rack_id'] ?? 0);
        if ($rack_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de rack inválido']);
            exit;
        }

        // Obtener datos del rack
        $stmtRack = $pdo->prepare("
            SELECT r.*, rm.name as room_name 
            FROM dc_racks r 
            LEFT JOIN dc_rooms rm ON r.room_id = rm.id 
            WHERE r.id = ?
        ");
        $stmtRack->execute([$rack_id]);
        $rack = $stmtRack->fetch(PDO::FETCH_ASSOC);

        if (!$rack) {
            echo json_encode(['success' => false, 'message' => 'Rack no encontrado']);
            exit;
        }

        // Obtener equipos del rack
        $stmtDevs = $pdo->prepare("
            SELECT d.*, i.hostname as cmdb_hostname, i.attributes_json as cmdb_attrs 
            FROM dc_rack_devices d
            LEFT JOIN ci_instances i ON d.cmdb_reference = i.id
            WHERE d.rack_id = ?
            ORDER BY d.start_u ASC
        ");
        $stmtDevs->execute([$rack_id]);
        $devices = $stmtDevs->fetchAll(PDO::FETCH_ASSOC);

        // Decodificar JSON y formatear
        $occupiedUnits = [];
        foreach ($devices as &$dev) {
            $dev['details'] = json_decode($dev['details_json'] ?? '{}', true) ?: [];
            unset($dev['details_json']);

            if ($dev['cmdb_reference']) {
                $cmdb_attrs = json_decode($dev['cmdb_attrs'] ?? '{}', true) ?: [];
                $dev['cmdb_imagen_frontal'] = $cmdb_attrs['imagen_frontal'] ?? '';
                $dev['cmdb_imagen_trasera'] = $cmdb_attrs['imagen_trasera'] ?? '';
                if (!empty($cmdb_attrs['marca'])) $dev['details']['make'] = $cmdb_attrs['marca'];
                if (!empty($cmdb_attrs['modelo'])) $dev['details']['model'] = $cmdb_attrs['modelo'];
                if (!empty($cmdb_attrs['serial_number'])) $dev['details']['serial_number'] = $cmdb_attrs['serial_number'];
                if (!empty($cmdb_attrs['asset_tag'])) $dev['details']['asset_tag'] = $cmdb_attrs['asset_tag'];
                if (!empty($dev['cmdb_hostname'])) $dev['name'] = $dev['cmdb_hostname'];
            }
            unset($dev['cmdb_attrs']);

            $isVertical = (!empty($dev['details']['mounting']) && in_array($dev['details']['mounting'], ['vertical_left', 'vertical_right'])) || !empty($dev['details']['is_vertical']) || (int)$dev['start_u'] === 0;
            if (!$isVertical) {
                $startU = (int)$dev['start_u'];
                $heightU = max(1, (int)$dev['height_u']);
                for ($u = $startU; $u < $startU + $heightU; $u++) {
                    if ($u <= (int)$rack['total_u'] && $u >= 1) {
                        $occupiedUnits[$u] = true;
                    }
                }
            }
        }

        $totalU = max(1, (int)$rack['total_u']);
        $occupiedCount = count($occupiedUnits);
        $freeCount = max(0, $totalU - $occupiedCount);
        $occupancyPct = round(($occupiedCount / $totalU) * 100);

        // Fotos de evidencia específicas y exclusivas de este Rack
        $photos = [];
        if (!empty($rack['photos_json'])) {
            $photos = json_decode($rack['photos_json'], true) ?: [];
        }

        // Observaciones
        $observations = [];
        if (!empty($rack['observations_json'])) {
            $observations = json_decode($rack['observations_json'], true) ?: [];
        }
        if (empty($observations) && !empty($rack['description'])) {
            $lines = preg_split('/[\r\n]+/', $rack['description']);
            foreach ($lines as $line) {
                $line = trim($line, " \t\n\r\0\x0B•-*");
                if (!empty($line)) $observations[] = $line;
            }
        }
        if (empty($observations)) {
            $observations = [
                'Gabinete operativo en producción',
                $occupancyPct . '% de ocupación física (' . $freeCount . ' UR disponibles)',
                count($devices) . ' equipos registrados en inventario DCIM'
            ];
        }

        echo json_encode([
            'success' => true,
            'rack' => [
                'id' => (int)$rack['id'],
                'name' => $rack['name'],
                'client' => $rack['client'] ?: 'VILASECA',
                'city' => $rack['city'] ?: 'No asignada',
                'location' => $rack['location'] ?: 'General',
                'room_name' => $rack['room_name'] ?? 'Sin Cuarto Asignado',
                'total_u' => $totalU,
                'numbering_dir' => $rack['numbering_dir'] ?? 'UP',
                'description' => $rack['description'] ?? '',
                'photos' => $photos,
                'observations' => $observations
            ],
            'devices' => $devices,
            'stats' => [
                'total_u' => $totalU,
                'occupied_u' => $occupiedCount,
                'free_u' => $freeCount,
                'occupancy_pct' => $occupancyPct,
                'device_count' => count($devices)
            ]
        ]);

    } elseif ($action === 'save_rack_evidence') {
        $rack_id = (int)($_POST['rack_id'] ?? 0);
        if ($rack_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de rack inválido']);
            exit;
        }

        $observations = $_POST['observations'] ?? null;
        $photos = $_POST['photos'] ?? null;

        $updates = [];
        $params = [];

        if ($observations !== null) {
            $obsArray = is_array($observations) ? $observations : json_decode($observations, true);
            $updates[] = "observations_json = ?";
            $params[] = json_encode($obsArray ?: []);
        }

        if ($photos !== null) {
            $photosArray = is_array($photos) ? $photos : json_decode($photos, true);
            $updates[] = "photos_json = ?";
            $params[] = json_encode($photosArray ?: []);
        }

        // Subida de nueva fotografía (soporta new_photo, photo o file)
        $fileKey = null;
        if (!empty($_FILES['new_photo']['name']) && $_FILES['new_photo']['error'] === UPLOAD_ERR_OK) {
            $fileKey = 'new_photo';
        } elseif (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $fileKey = 'photo';
        } elseif (!empty($_FILES['file']['name']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $fileKey = 'file';
        }

        $stmtCur = $pdo->prepare("SELECT photos_json FROM dc_racks WHERE id = ?");
        $stmtCur->execute([$rack_id]);
        $curPhotos = json_decode($stmtCur->fetchColumn() ?: '[]', true) ?: [];

        if ($fileKey !== null) {
            $uploadDir = __DIR__ . '/../../storage/uploads/racks/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }
            $ext = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $filename = 'RACK_' . $rack_id . '_' . uniqid() . '.' . $ext;
                $targetFile = $uploadDir . $filename;
                if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $targetFile)) {
                    $curPhotos[] = [
                        'title' => trim($_POST['photo_title'] ?? 'Evidencia fotográfica de gabinete'),
                        'url' => 'storage/uploads/racks/' . $filename
                    ];
                    $updates[] = "photos_json = ?";
                    $params[] = json_encode($curPhotos);
                }
            }
        }

        if (!empty($updates)) {
            $params[] = $rack_id;
            $stmtUp = $pdo->prepare("UPDATE dc_racks SET " . implode(', ', $updates) . " WHERE id = ?");
            $stmtUp->execute($params);
            echo json_encode(['success' => true, 'message' => 'Evidencia fotográfica guardada exitosamente', 'photos' => $curPhotos]);
        } else {
            echo json_encode(['success' => true, 'message' => 'Sin cambios', 'photos' => $curPhotos]);
        }

    } elseif ($action === 'delete_rack_photo') {
        $rack_id = (int)($_POST['rack_id'] ?? 0);
        $photo_url = trim($_POST['photo_url'] ?? '');
        if ($rack_id <= 0 || empty($photo_url)) {
            echo json_encode(['success' => false, 'message' => 'Parámetros inválidos para eliminar fotografía']);
            exit;
        }

        $stmtCur = $pdo->prepare("SELECT photos_json FROM dc_racks WHERE id = ?");
        $stmtCur->execute([$rack_id]);
        $curPhotos = json_decode($stmtCur->fetchColumn() ?: '[]', true) ?: [];

        $updatedPhotos = [];
        foreach ($curPhotos as $p) {
            if (trim($p['url']) === $photo_url) {
                // Eliminar archivo físico de disco si está en la carpeta de uploads
                $filePath = __DIR__ . '/../../' . ltrim($photo_url, '/');
                if (file_exists($filePath) && strpos($filePath, 'storage/uploads/racks/') !== false) {
                    @unlink($filePath);
                }
            } else {
                $updatedPhotos[] = $p;
            }
        }

        $stmtUp = $pdo->prepare("UPDATE dc_racks SET photos_json = ? WHERE id = ?");
        $stmtUp->execute([json_encode($updatedPhotos), $rack_id]);
        echo json_encode(['success' => true, 'message' => 'Fotografía eliminada exitosamente', 'photos' => $updatedPhotos]);

    } else {
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

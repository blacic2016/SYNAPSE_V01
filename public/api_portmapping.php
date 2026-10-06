<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/zabbix_api.php';

header('Content-Type: application/json');

require_once __DIR__ . '/../src/permissions_helper.php';

if (!current_user_id() || (!has_module_access('portmapping') && !has_module_access('vilaseca'))) {
    echo json_encode(['success' => false, 'error' => 'No session or unauthorized']);
    exit;
}

if (!function_exists('extractVisioThumbnailFromFile')) {
    function extractVisioThumbnailFromFile($fullFilePath) {
    if (!class_exists('ZipArchive') || !file_exists($fullFilePath)) return null;
    $zip = new ZipArchive();
    if ($zip->open($fullFilePath) === TRUE) {
        $thumbnailFiles = [
            'docProps/thumbnail.jpeg',
            'docProps/thumbnail.jpg',
            'docProps/thumbnail.png',
            'visio/media/thumbnail.jpeg',
            'visio/media/thumbnail.png',
            'visio/media/image1.png',
            'visio/media/image1.jpeg',
            'visio/media/image1.jpg'
        ];
        foreach ($thumbnailFiles as $fileInZip) {
            $data = $zip->getFromName($fileInZip);
            if ($data !== false && strlen($data) > 0) {
                $ext = strtolower(pathinfo($fileInZip, PATHINFO_EXTENSION));
                if ($ext === 'jpg') $ext = 'jpeg';
                $dir = dirname($fullFilePath);
                $basename = pathinfo($fullFilePath, PATHINFO_FILENAME);
                $thumbFileName = $basename . "_thumb." . ($ext === 'jpeg' ? 'jpg' : $ext);
                $thumbFullPath = $dir . '/' . $thumbFileName;
                file_put_contents($thumbFullPath, $data);
                $zip->close();
                
                $base_dir = dirname(__DIR__);
                $relPath = str_replace([$base_dir . '/public/', $base_dir . '/'], '', $thumbFullPath);
                return [
                    'path' => $relPath,
                    'ext' => ($ext === 'jpeg' ? 'jpg' : $ext),
                    'data_url' => 'data:image/' . $ext . ';base64,' . base64_encode($data)
                ];
            }
        }
        $zip->close();
    }
    return null;
    }
}

$action = $_REQUEST['action'] ?? '';
$pdo = getPDO();

switch ($action) {
    case 'get_hierarchy':
        $category_name = $_GET['category'] ?? '';
        $parent_id = $_GET['parent_id'] ?? null;
        
        if (empty($category_name)) {
            echo json_encode(['success' => false, 'error' => 'Category is required']);
            break;
        }
        
        $sql = "SELECT i.id, i.hostname as name 
                FROM ci_instances i 
                JOIN ci_categories c ON i.category_id = c.id 
                WHERE c.name = :cat";
        $params = [':cat' => $category_name];

        if ($parent_id) {
            $sql .= " AND i.id IN (SELECT source_id FROM ci_relationships WHERE target_id = :parent_id AND source_type = 'ci_instances' AND target_type = 'ci_instances')";
            $params[':parent_id'] = $parent_id;
        }
        
        $sql .= " ORDER BY i.hostname ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $results]);
        break;

    case 'get_devices':
        $room_id = $_GET['room_id'] ?? null;
        $arch_type = $_GET['arch_type'] ?? '';
        
        // Simplified category filter based on arch_type
        // For 'pasivo_activo': We could return Patch Panels AND Switches and let the UI filter, or just return all devices in the room.
        $sql = "SELECT i.id, i.hostname as name, c.name as category_name
                FROM ci_instances i
                JOIN ci_categories c ON i.category_id = c.id
                WHERE 1=1";
                
        $params = [];
        if ($room_id) {
            $sql .= " AND i.id IN (SELECT source_id FROM ci_relationships WHERE target_id = :room_id AND relation_type='ubicado_en')";
            $params[':room_id'] = $room_id;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $devices]);
        break;

    case 'get_device_ports':
        $device_id = $_GET['device_id'] ?? null;
        if (!$device_id) {
            echo json_encode(['success' => false, 'error' => 'Device ID required']);
            break;
        }
        
        // 1. Get device details to see if monitored
        $stmt = $pdo->prepare("SELECT hostname, zabbix_host_id FROM ci_instances WHERE id = ?");
        $stmt->execute([$device_id]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$device) {
            echo json_encode(['success' => false, 'error' => 'Device not found']);
            break;
        }

        // 2. Load ports from ci_components
        $sql = "SELECT c.id, c.name, c.attributes_json,
                (SELECT COUNT(*) FROM port_mappings WHERE source_component_id = c.id OR target_component_id = c.id) as is_mapped
                FROM ci_components c 
                WHERE c.parent_ci_id = ? 
                ORDER BY c.name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$device_id]);
        $cmdb_ports = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $ports_by_name = [];
        foreach ($cmdb_ports as $p) {
            $ports_by_name[$p['name']] = [
                'id' => $p['id'],
                'name' => $p['name'],
                'is_mapped' => ($p['is_mapped'] > 0)
            ];
        }

        // 3. If monitored, load from host_interfaces cache
        $zabbix_ports = [];
        if (!empty($device['zabbix_host_id'])) {
            $stmt = $pdo->prepare("SELECT interface_name FROM host_interfaces WHERE hostid = ? ORDER BY interface_name ASC");
            $stmt->execute([$device['zabbix_host_id']]);
            $zabbix_ports = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        // Merge them
        $final_ports = [];
        // Add Zabbix ports first
        foreach ($zabbix_ports as $iname) {
            if (isset($ports_by_name[$iname])) {
                $final_ports[] = $ports_by_name[$iname];
                unset($ports_by_name[$iname]);
            } else {
                $final_ports[] = [
                    'id' => null,
                    'name' => $iname,
                    'is_mapped' => false
                ];
            }
        }
        // Add any remaining CMDB-only ports
        foreach ($ports_by_name as $p) {
            $final_ports[] = $p;
        }

        echo json_encode(['success' => true, 'data' => $final_ports]);
        break;
        
    case 'create_port':
        // Explicit CMDB port creation
        $device_id = $_POST['device_id'] ?? null;
        $port_name = $_POST['port_name'] ?? '';
        
        if (!$device_id || !$port_name) {
            echo json_encode(['success' => false, 'error' => 'Device ID and Port Name are required']);
            break;
        }
        
        // Check if port already exists
        $stmt = $pdo->prepare("SELECT id FROM ci_components WHERE parent_ci_id = ? AND name = ?");
        $stmt->execute([$device_id, $port_name]);
        if($stmt->fetchColumn()) {
            echo json_encode(['success' => false, 'error' => 'El puerto ya existe en este equipo']);
            break;
        }
        
        $attr = json_encode(['created_via' => 'portmapping_wizard', 'created_by' => current_user_id()]);
        $stmt = $pdo->prepare("INSERT INTO ci_components (parent_ci_id, name, attributes_json) VALUES (?, ?, ?)");
        if($stmt->execute([$device_id, $port_name, $attr])) {
            echo json_encode(['success' => true, 'port_id' => $pdo->lastInsertId()]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error insertando en BD']);
        }
        break;

    case 'save_portmapping':
        $source_port_id = $_POST['source_port_id'] ?? null;
        $target_port_id = $_POST['target_port_id'] ?? null;
        $cable_type = $_POST['cable_type'] ?? 'UTP Cat6A';
        $color_code = $_POST['color_code'] ?? '#0000FF';
        
        if (!$source_port_id || !$target_port_id) {
            echo json_encode(['success' => false, 'error' => 'Missing required fields']);
            break;
        }
        
        // Verify they aren't already mapped
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM port_mappings WHERE source_component_id IN (?,?) OR target_component_id IN (?,?)");
        $stmt->execute([$source_port_id, $target_port_id, $source_port_id, $target_port_id]);
        if($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'error' => 'Uno de los puertos ya está en uso. Verifique la CMDB.']);
            break;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO port_mappings (source_component_id, target_component_id, cable_type, color_code) VALUES (?, ?, ?, ?)");
            $stmt->execute([$source_port_id, $target_port_id, $cable_type, $color_code]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'get_mappings':
        $sql = "SELECT pm.id, 
                       c1.name as source_port, i1.hostname as source_device,
                       c2.name as target_port, i2.hostname as target_device,
                       pm.cable_type, pm.color_code, pm.created_at,
                       c2.id as target_component_id,
                       pm.notes,
                       COALESCE(pm.connection_type, 'network') as connection_type
                FROM port_mappings pm
                JOIN ci_components c1 ON pm.source_component_id = c1.id
                JOIN ci_instances i1 ON c1.parent_ci_id = i1.id
                JOIN ci_components c2 ON pm.target_component_id = c2.id
                JOIN ci_instances i2 ON c2.parent_ci_id = i2.id
                ORDER BY pm.created_at DESC";
        $stmt = $pdo->query($sql);
        $mappings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $mappings]);
        break;

    case 'get_hybrid_port_data':
        $port_component_id = $_GET['port_id'] ?? null;
        if (!$port_component_id) {
            echo json_encode(['success' => false, 'error' => 'Missing port_id']);
            break;
        }
        
        $stmt = $pdo->prepare("SELECT c.name as port_name, i.zabbix_host_id, i.hostname as switch_name 
                               FROM ci_components c 
                               JOIN ci_instances i ON c.parent_ci_id = i.id 
                               WHERE c.id = ?");
        $stmt->execute([$port_component_id]);
        $cmdb_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cmdb_data) {
            echo json_encode(['success' => false, 'error' => 'Port not found in CMDB']);
            break;
        }

        $response = ['cmdb_inventory' => $cmdb_data, 'zabbix_telemetry' => null];

        if (!empty($cmdb_data['zabbix_host_id'])) {
            $zbx_items = call_zabbix_api('item.get', [
                'hostids' => $cmdb_data['zabbix_host_id'],
                'search' => ['key_' => '*[' . $cmdb_data['port_name'] . ']'],
                'searchWildcardsEnabled' => true,
                'output' => ['name', 'key_', 'lastvalue', 'units']
            ]);
            
            if (!isset($zbx_items['error']) && !empty($zbx_items['result'])) {
                $telemetry = ['status' => 'DOWN', 'traffic_in' => 0, 'traffic_out' => 0];
                foreach ($zbx_items['result'] as $item) {
                    if (stripos($item['key_'], 'OperStatus') !== false) {
                        $telemetry['status'] = ($item['lastvalue'] == 1) ? 'UP' : 'DOWN';
                    }
                    if (stripos($item['key_'], 'InOctets') !== false) {
                        $telemetry['traffic_in'] = $item['lastvalue'];
                    }
                    if (stripos($item['key_'], 'OutOctets') !== false) {
                        $telemetry['traffic_out'] = $item['lastvalue'];
                    }
                }
                $response['zabbix_telemetry'] = $telemetry;
            }
        }
        
        echo json_encode(['success' => true, 'data' => $response]);
        break;

    case 'get_all_devices':
        try {
            $sql = "SELECT i.id, i.hostname as name, i.zabbix_host_id, c.name as category_name 
                    FROM ci_instances i 
                    JOIN ci_categories c ON i.category_id = c.id 
                    ORDER BY i.hostname ASC";
            $stmt = $pdo->query($sql);
            $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $devices]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'get_device_ports_and_connections':
        $device_id = $_GET['device_id'] ?? null;
        if (!$device_id) {
            echo json_encode(['success' => false, 'error' => 'Missing device_id']);
            break;
        }

        try {
            // Get device details
            $stmt = $pdo->prepare("SELECT hostname, zabbix_host_id FROM ci_instances WHERE id = ?");
            $stmt->execute([$device_id]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$device) {
                echo json_encode(['success' => false, 'error' => 'Device not found']);
                break;
            }

            // Fetch current CMDB components and their mappings
            $sql = "SELECT 
                        c.id as component_id,
                        c.name as port_name,
                        c.attributes_json,
                        pm.id as mapping_id,
                        pm.cable_type,
                        pm.color_code,
                        pm.notes,
                        COALESCE(pm.connection_type, 'network') as connection_type,
                        CASE WHEN pm.source_component_id = c.id THEN c_tgt.id ELSE c_src.id END as dest_port_id,
                        CASE WHEN pm.source_component_id = c.id THEN c_tgt.name ELSE c_src.name END as dest_port_name,
                        CASE WHEN pm.source_component_id = c.id THEN i_tgt.id ELSE i_src.id END as dest_device_id,
                        CASE WHEN pm.source_component_id = c.id THEN i_tgt.hostname ELSE i_src.hostname END as dest_device_name
                    FROM ci_components c
                    LEFT JOIN port_mappings pm ON (pm.source_component_id = c.id OR pm.target_component_id = c.id)
                    LEFT JOIN ci_components c_src ON pm.source_component_id = c_src.id
                    LEFT JOIN ci_instances i_src ON c_src.parent_ci_id = i_src.id
                    LEFT JOIN ci_components c_tgt ON pm.target_component_id = c_tgt.id
                    LEFT JOIN ci_instances i_tgt ON c_tgt.parent_ci_id = i_tgt.id
                    WHERE c.parent_ci_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$device_id]);
            $cmdb_ports = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Parse attributes_json
            $ports_by_name = [];
            foreach ($cmdb_ports as $p) {
                $attrs = json_decode($p['attributes_json'], true) ?: [];
                $p['connection_type'] = $attrs['connection_type'] ?? $p['connection_type'] ?? 'network';
                $ports_by_name[$p['port_name']] = $p;
            }

            $zabbix_interfaces = [];
            if (!empty($device['zabbix_host_id'])) {
                // Fetch interfaces from host_interfaces local cache table
                $stmt = $pdo->prepare("SELECT * FROM host_interfaces WHERE hostid = ? ORDER BY interface_type ASC, interface_name ASC");
                $stmt->execute([$device['zabbix_host_id']]);
                $zabbix_interfaces = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // Merge Zabbix interfaces
            $final_network_ports = [];
            $final_power_ports = [];

            // Add Zabbix ports first to preserve Zabbix ordering
            $processed_names = [];
            foreach ($zabbix_interfaces as $zi) {
                $iname = $zi['interface_name'];
                $processed_names[$iname] = true;

                $port_data = [
                    'component_id' => null,
                    'port_name' => $iname,
                    'mapping_id' => null,
                    'cable_type' => 'UTP Cat6A',
                    'color_code' => '#0000FF',
                    'notes' => '',
                    'connection_type' => 'network',
                    'dest_port_id' => null,
                    'dest_port_name' => '',
                    'dest_device_id' => null,
                    'dest_device_name' => '',
                    'status' => $zi['status'] ?? 'Unknown',
                    'vlan' => $zi['vlan'] ?? '',
                    'alias' => $zi['alias'] ?? '',
                    'bits_received' => $zi['bits_received'] ?? 0,
                    'bits_sent' => $zi['bits_sent'] ?? 0,
                    'is_zabbix' => true
                ];

                if (isset($ports_by_name[$iname])) {
                    $cmdb_p = $ports_by_name[$iname];
                    $port_data['component_id'] = $cmdb_p['component_id'];
                    $port_data['mapping_id'] = $cmdb_p['mapping_id'];
                    $port_data['cable_type'] = $cmdb_p['cable_type'] ?: 'UTP Cat6A';
                    $port_data['color_code'] = $cmdb_p['color_code'] ?: '#0000FF';
                    $port_data['notes'] = $cmdb_p['notes'] ?: '';
                    $port_data['dest_port_id'] = $cmdb_p['dest_port_id'];
                    $port_data['dest_port_name'] = $cmdb_p['dest_port_name'] ?: '';
                    $port_data['dest_device_id'] = $cmdb_p['dest_device_id'];
                    $port_data['dest_device_name'] = $cmdb_p['dest_device_name'] ?: '';
                }

                $final_network_ports[] = $port_data;
            }

            // Add remaining CMDB components that are not in Zabbix
            foreach ($cmdb_ports as $p) {
                if (isset($processed_names[$p['port_name']])) {
                    continue;
                }

                $port_data = [
                    'component_id' => $p['component_id'],
                    'port_name' => $p['port_name'],
                    'mapping_id' => $p['mapping_id'],
                    'cable_type' => $p['cable_type'] ?: 'UTP Cat6A',
                    'color_code' => $p['color_code'] ?: '#0000FF',
                    'notes' => $p['notes'] ?: '',
                    'connection_type' => $p['connection_type'],
                    'dest_port_id' => $p['dest_port_id'],
                    'dest_port_name' => $p['dest_port_name'] ?: '',
                    'dest_device_id' => $p['dest_device_id'],
                    'dest_device_name' => $p['dest_device_name'] ?: '',
                    'status' => 'Active', // Default for manual
                    'vlan' => '',
                    'alias' => '',
                    'bits_received' => 0,
                    'bits_sent' => 0,
                    'is_zabbix' => false
                ];

                if ($p['connection_type'] === 'power') {
                    $final_power_ports[] = $port_data;
                } else {
                    $final_network_ports[] = $port_data;
                }
            }

            echo json_encode([
                'success' => true,
                'device' => $device,
                'network_ports' => $final_network_ports,
                'power_ports' => $final_power_ports
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'save_device_connection':
        $device_id = $_POST['device_id'] ?? null;
        $port_name = $_POST['port_name'] ?? null;
        $connection_type = $_POST['connection_type'] ?? 'network';
        $dest_device_id = $_POST['dest_device_id'] ?? null;
        $dest_port_name = $_POST['dest_port_name'] ?? null;
        $cable_type = $_POST['cable_type'] ?? 'UTP Cat6A';
        $color_code = $_POST['color_code'] ?? '#0000FF';
        $notes = $_POST['notes'] ?? '';

        if (!$device_id || !$port_name || !$dest_device_id || !$dest_port_name) {
            echo json_encode(['success' => false, 'error' => 'Parámetros incompletos. Todos los campos de los extremos son obligatorios.']);
            break;
        }

        try {
            $pdo->beginTransaction();

            // Check if dest_device_id is a manual device name
            if (!is_numeric($dest_device_id)) {
                $dest_device_name_str = trim($dest_device_id);
                if (empty($dest_device_name_str)) {
                    echo json_encode(['success' => false, 'error' => 'El nombre del equipo destino no puede estar vacío.']);
                    break;
                }
                
                // Find if a device with this hostname already exists
                $stmt = $pdo->prepare("SELECT id FROM ci_instances WHERE hostname = ?");
                $stmt->execute([$dest_device_name_str]);
                $found_id = $stmt->fetchColumn();
                
                if ($found_id) {
                    $dest_device_id = $found_id;
                } else {
                    // Create new manual device
                    $stmt = $pdo->prepare("SELECT category_id FROM ci_instances WHERE id = ?");
                    $stmt->execute([$device_id]);
                    $src_cat_id = $stmt->fetchColumn() ?: 39;
                    
                    $stmt = $pdo->prepare("INSERT INTO ci_instances (category_id, hostname, source, status) VALUES (?, ?, 'manual', 'Activo')");
                    $stmt->execute([$src_cat_id, $dest_device_name_str]);
                    $dest_device_id = $pdo->lastInsertId();
                }
            }

            // 1. Find or create source component
            $stmt = $pdo->prepare("SELECT id FROM ci_components WHERE parent_ci_id = ? AND name = ?");
            $stmt->execute([$device_id, $port_name]);
            $src_id = $stmt->fetchColumn();

            if (!$src_id) {
                $attr = json_encode(['connection_type' => $connection_type, 'created_via' => 'portmapping_manager']);
                $stmt = $pdo->prepare("INSERT INTO ci_components (parent_ci_id, name, attributes_json) VALUES (?, ?, ?)");
                $stmt->execute([$device_id, $port_name, $attr]);
                $src_id = $pdo->lastInsertId();
            } else {
                // Update component connection_type if needed
                $stmt = $pdo->prepare("SELECT attributes_json FROM ci_components WHERE id = ?");
                $stmt->execute([$src_id]);
                $old_attr = json_decode($stmt->fetchColumn(), true) ?: [];
                if (!isset($old_attr['connection_type']) || $old_attr['connection_type'] !== $connection_type) {
                    $old_attr['connection_type'] = $connection_type;
                    $stmt = $pdo->prepare("UPDATE ci_components SET attributes_json = ? WHERE id = ?");
                    $stmt->execute([json_encode($old_attr), $src_id]);
                }
            }

            // 2. Find or create destination component
            $stmt = $pdo->prepare("SELECT id FROM ci_components WHERE parent_ci_id = ? AND name = ?");
            $stmt->execute([$dest_device_id, $dest_port_name]);
            $dest_id = $stmt->fetchColumn();

            if (!$dest_id) {
                $attr = json_encode(['connection_type' => $connection_type, 'created_via' => 'portmapping_manager']);
                $stmt = $pdo->prepare("INSERT INTO ci_components (parent_ci_id, name, attributes_json) VALUES (?, ?, ?)");
                $stmt->execute([$dest_device_id, $dest_port_name, $attr]);
                $dest_id = $pdo->lastInsertId();
            } else {
                // Update component connection_type if needed
                $stmt = $pdo->prepare("SELECT attributes_json FROM ci_components WHERE id = ?");
                $stmt->execute([$dest_id]);
                $old_attr = json_decode($stmt->fetchColumn(), true) ?: [];
                if (!isset($old_attr['connection_type']) || $old_attr['connection_type'] !== $connection_type) {
                    $old_attr['connection_type'] = $connection_type;
                    $stmt = $pdo->prepare("UPDATE ci_components SET attributes_json = ? WHERE id = ?");
                    $stmt->execute([json_encode($old_attr), $dest_id]);
                }
            }

            // 3. Delete any existing mapping for these components (to prevent duplicates or loops)
            $stmt = $pdo->prepare("DELETE FROM port_mappings WHERE source_component_id IN (?, ?) OR target_component_id IN (?, ?)");
            $stmt->execute([$src_id, $dest_id, $src_id, $dest_id]);

            // 4. Insert new mapping
            $stmt = $pdo->prepare("INSERT INTO port_mappings (source_component_id, target_component_id, cable_type, color_code, notes, connection_type) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$src_id, $dest_id, $cable_type, $color_code, $notes, $connection_type]);

            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'delete_device_connection':
        $mapping_id = $_POST['mapping_id'] ?? null;
        if (!$mapping_id) {
            echo json_encode(['success' => false, 'error' => 'Missing mapping_id']);
            break;
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM port_mappings WHERE id = ?");
            $success = $stmt->execute([$mapping_id]);
            echo json_encode(['success' => $success]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'create_manual_port':
        $device_id = $_POST['device_id'] ?? null;
        $port_name = $_POST['port_name'] ?? null;
        $connection_type = $_POST['connection_type'] ?? 'network';

        if (!$device_id || !$port_name) {
            echo json_encode(['success' => false, 'error' => 'Faltan parámetros']);
            break;
        }

        try {
            // Check if it already exists
            $stmt = $pdo->prepare("SELECT id FROM ci_components WHERE parent_ci_id = ? AND name = ?");
            $stmt->execute([$device_id, $port_name]);
            if ($stmt->fetchColumn()) {
                echo json_encode(['success' => false, 'error' => 'El puerto/conexión ya existe']);
                break;
            }

            $attr = json_encode(['connection_type' => $connection_type, 'created_via' => 'portmapping_manual']);
            $stmt = $pdo->prepare("INSERT INTO ci_components (parent_ci_id, name, attributes_json) VALUES (?, ?, ?)");
            $success = $stmt->execute([$device_id, $port_name, $attr]);
            echo json_encode(['success' => $success, 'port_id' => $pdo->lastInsertId()]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'save_manual_survey':
        $id = $_POST['id'] ?? null;
        $client = $_POST['client'] ?? '';
        $location = $_POST['location'] ?? '';
        $area = $_POST['area'] ?? '';
        $device_name = $_POST['device_name'] ?? '';
        $device_label = $_POST['device_label'] ?? '';
        $device_type = $_POST['device_type'] ?? '';
        $vendor = trim($_POST['vendor'] ?? '');
        $serial = trim($_POST['serial'] ?? '');
        if ($serial !== '' && empty($device_label)) {
            $device_label = $serial;
        } elseif ($device_label !== '' && empty($serial)) {
            $serial = $device_label;
        }
        $ports_count = !empty($_POST['ports_count']) ? (int)$_POST['ports_count'] : null;
        $rack = $_POST['rack'] ?? '';
        $ur_rack = $_POST['ur_rack'] ?? '';
        $ip_address = $_POST['ip_address'] ?? '';
        $snmp_community = $_POST['snmp_community'] ?? '';
        $creation_date = $_POST['creation_date'] ?? '';
        $description = $_POST['description'] ?? '';
        $ports_data_json = $_POST['ports_data_json'] ?? '[]';
        $ports_data_raw = json_decode($ports_data_json, true);
        if (is_array($ports_data_raw)) {
            foreach ($ports_data_raw as $idx => &$p) {
                if (isset($p['port_name'])) {
                    $numOnly = preg_replace('/[^0-9]/', '', (string)$p['port_name']);
                    $p['port_name'] = $numOnly !== '' ? $numOnly : (string)($idx + 1);
                }
                if (!empty($vendor) && empty($p['vendor_src'])) {
                    $p['vendor_src'] = $vendor;
                }
                if (!empty($serial) && empty($p['serial_src'])) {
                    $p['serial_src'] = $serial;
                }
            }
            unset($p);
            $ports_data_json = json_encode($ports_data_raw);
        }

        $metadata_json = $_POST['images_metadata_json'] ?? '';
        $metadata = json_decode($metadata_json, true);

        $final_images = [];
        if (is_array($metadata)) {
            $base_dir = dirname(__DIR__); 
            $primary_upload_path = $base_dir . '/storage/uploads/';
            $alt_upload_path = $base_dir . '/public/uploads/';

            // Ensure destination directories exist
            if (!is_dir($primary_upload_path)) {
                @mkdir($primary_upload_path, 0777, true);
                @chmod($primary_upload_path, 0777);
            }
            if (!is_dir($alt_upload_path)) {
                @mkdir($alt_upload_path, 0777, true);
                @chmod($alt_upload_path, 0777);
            }

            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

            foreach ($metadata as $item) {
                $title = $item['title'] ?? '';
                $tags = $item['tags'] ?? [];
                $associated_port = $item['associated_port'] ?? '';
                if (empty($tags) && !empty($title)) {
                    $tags = array_map('trim', explode(',', $title));
                }

                $saved_path = null;

                if ($item['is_existing']) {
                    $saved_path = $item['path'];
                    // Si la imagen existente es DataURL (Base64), convertirla a archivo físico en disco
                    if (strpos($saved_path, 'data:image/') === 0) {
                        if (preg_match('/^data:image\/(\w+);base64,/', $saved_path, $typeMatch)) {
                            $img_data = substr($saved_path, strpos($saved_path, ',') + 1);
                            $ext = strtolower($typeMatch[1]);
                            if ($ext === 'jpeg') $ext = 'jpg';
                            $decoded_bytes = base64_decode($img_data);
                            if ($decoded_bytes !== false) {
                                $b64_filename = "IMG_PM_SURVEY_" . uniqid() . "." . $ext;
                                $target_full = $primary_upload_path . $b64_filename;
                                if (@file_put_contents($target_full, $decoded_bytes) !== false) {
                                    $saved_path = "storage/uploads/" . $b64_filename;
                                } else {
                                    $target_alt = $alt_upload_path . $b64_filename;
                                    if (@file_put_contents($target_alt, $decoded_bytes) !== false) {
                                        $saved_path = "uploads/" . $b64_filename;
                                    }
                                }
                            }
                        }
                    }
                } else {
                    $file_idx = $item['file_index'] ?? 0;
                    $key = "image_$file_idx";
                    if (isset($_FILES[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK) {
                        $filename = $_FILES[$key]['name'];
                        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                        if (in_array($ext, $allowed)) {
                            $new_name = "IMG_PM_SURVEY_" . uniqid() . "_$file_idx." . $ext;
                            $primary_dest = $primary_upload_path . $new_name;
                            $alt_dest = $alt_upload_path . $new_name;

                            // Intentar guardar primero por move_uploaded_file o copy
                            if (@move_uploaded_file($_FILES[$key]['tmp_name'], $primary_dest) || @copy($_FILES[$key]['tmp_name'], $primary_dest)) {
                                $saved_path = "storage/uploads/" . $new_name;
                            } else if (@move_uploaded_file($_FILES[$key]['tmp_name'], $alt_dest) || @copy($_FILES[$key]['tmp_name'], $alt_dest)) {
                                $saved_path = "uploads/" . $new_name;
                            } else {
                                // Guardar stream de bytes directamente al disco
                                $raw_bytes = @file_get_contents($_FILES[$key]['tmp_name']);
                                if ($raw_bytes !== false) {
                                    if (@file_put_contents($primary_dest, $raw_bytes) !== false) {
                                        $saved_path = "storage/uploads/" . $new_name;
                                    } else if (@file_put_contents($alt_dest, $raw_bytes) !== false) {
                                        $saved_path = "uploads/" . $new_name;
                                    }
                                }
                            }
                        }
                    }
                }

                if ($saved_path) {
                    $final_images[] = [
                        'path' => $saved_path,
                        'title' => $title,
                        'tags' => $tags,
                        'associated_port' => $associated_port
                    ];
                }
            }
        } else {
            // Fallback for legacy requests
            $existing_images_json = $_POST['existing_images_json'] ?? '[]';
            $existing_images = json_decode($existing_images_json, true);
            if (!is_array($existing_images)) {
                $existing_images = [];
            }
            $normalized_existing = [];
            foreach ($existing_images as $img) {
                if (is_string($img)) {
                    $normalized_existing[] = ['path' => $img, 'title' => ''];
                } else if (is_array($img) && isset($img['path'])) {
                    $normalized_existing[] = $img;
                }
            }

            $new_images = [];
            $base_dir = dirname(__DIR__); 
            $primary_upload_path = $base_dir . '/storage/uploads/';
            $alt_upload_path = $base_dir . '/public/uploads/';

            if (!is_dir($primary_upload_path)) {
                @mkdir($primary_upload_path, 0777, true);
                @chmod($primary_upload_path, 0777);
            }
            if (!is_dir($alt_upload_path)) {
                @mkdir($alt_upload_path, 0777, true);
                @chmod($alt_upload_path, 0777);
            }

            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            for ($i = 0; $i < 4; $i++) {
                $key = "image_$i";
                if (isset($_FILES[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK) {
                    $filename = $_FILES[$key]['name'];
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    if (in_array($ext, $allowed)) {
                        $new_name = "IMG_PM_SURVEY_" . uniqid() . "_$i." . $ext;
                        $primary_dest = $primary_upload_path . $new_name;
                        $alt_dest = $alt_upload_path . $new_name;

                        if (@move_uploaded_file($_FILES[$key]['tmp_name'], $primary_dest) || @copy($_FILES[$key]['tmp_name'], $primary_dest)) {
                            $new_images[] = ['path' => "storage/uploads/" . $new_name, 'title' => ''];
                        } else if (@move_uploaded_file($_FILES[$key]['tmp_name'], $alt_dest) || @copy($_FILES[$key]['tmp_name'], $alt_dest)) {
                            $new_images[] = ['path' => "uploads/" . $new_name, 'title' => ''];
                        } else {
                            $raw_bytes = @file_get_contents($_FILES[$key]['tmp_name']);
                            if ($raw_bytes !== false) {
                                if (@file_put_contents($primary_dest, $raw_bytes) !== false) {
                                    $new_images[] = ['path' => "storage/uploads/" . $new_name, 'title' => ''];
                                } else if (@file_put_contents($alt_dest, $raw_bytes) !== false) {
                                    $new_images[] = ['path' => "uploads/" . $new_name, 'title' => ''];
                                }
                            }
                        }
                    }
                }
            }
            $final_images = array_merge($normalized_existing, $new_images);
        }

        $final_images = array_slice($final_images, 0, 4);
        $final_images_json = json_encode($final_images);

        $config_files_json = $_POST['config_files_json'] ?? '[]';
        $diagram_json = $_POST['diagram_json'] ?? '{}';

        if (empty($client) || empty($location) || empty($device_name) || empty($creation_date)) {
            echo json_encode(['success' => false, 'error' => 'Cliente, Ubicación, Equipo Origen y Fecha son requeridos']);
            break;
        }

        try {
            $pdo->beginTransaction();

            if ($id) {
                $stmt = $pdo->prepare("UPDATE manual_portmap_surveys SET client = ?, location = ?, area = ?, device_name = ?, device_label = ?, device_type = ?, vendor = ?, serial = ?, ports_count = ?, rack = ?, ur_rack = ?, ip_address = ?, snmp_community = ?, creation_date = ?, description = ?, ports_data_json = ?, images_json = ?, config_files_json = ?, diagram_json = ? WHERE id = ?");
                $stmt->execute([$client, $location, $area, $device_name, $device_label, $device_type, $vendor, $serial, $ports_count, $rack, $ur_rack, $ip_address, $snmp_community, $creation_date, $description, $ports_data_json, $final_images_json, $config_files_json, $diagram_json, $id]);
                $survey_db_id = $id;
            } else {
                $stmt = $pdo->prepare("INSERT INTO manual_portmap_surveys (client, location, area, device_name, device_label, device_type, vendor, serial, ports_count, rack, ur_rack, ip_address, snmp_community, creation_date, description, ports_data_json, images_json, config_files_json, diagram_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$client, $location, $area, $device_name, $device_label, $device_type, $vendor, $serial, $ports_count, $rack, $ur_rack, $ip_address, $snmp_community, $creation_date, $description, $ports_data_json, $final_images_json, $config_files_json, $diagram_json]);
                $survey_db_id = $pdo->lastInsertId();
            }

            // Sincronizar con CMDB ci_instances
            $stmt_ci = $pdo->prepare("SELECT id FROM ci_instances WHERE hostname = ?");
            $stmt_ci->execute([$device_name]);
            $ci_id = $stmt_ci->fetchColumn();

            $category_id = 39; // 03 Hardware CI
            $type_lower = strtolower(trim($device_type));
            if ($type_lower === 'switch') {
                $category_id = 1;
            } elseif ($type_lower === 'router') {
                $category_id = 43;
            }

            $attributes = json_encode([
                'rack' => $rack,
                'ur_rack' => $ur_rack,
                'area' => $area,
                'device_label' => $device_label,
                'client' => $client,
                'location' => $location,
                'snmp_community' => $snmp_community,
                'ports_count' => $ports_count,
                'device_type' => $device_type,
                'vendor' => $vendor,
                'serial' => $serial,
                'manual_survey_id' => $survey_db_id
            ]);

            if ($ci_id) {
                // Actualizar CI existente
                $stmt_up = $pdo->prepare("UPDATE ci_instances SET category_id = ?, ip_address = ?, description = ?, attributes_json = ? WHERE id = ?");
                $stmt_up->execute([$category_id, $ip_address, $description, $attributes, $ci_id]);
            } else {
                // Insertar nuevo CI
                $stmt_in = $pdo->prepare("INSERT INTO ci_instances (category_id, hostname, ip_address, source, status, description, attributes_json) VALUES (?, ?, ?, 'manual', 'Activo', ?, ?)");
                $stmt_in->execute([$category_id, $device_name, $ip_address, $description, $attributes]);
                $ci_id = $pdo->lastInsertId();
            }

            // Crear/actualizar componentes (puertos) para este CI
            $ports_arr = json_decode($ports_data_json, true) ?: [];
            foreach ($ports_arr as $p_item) {
                $p_name = trim($p_item['port_name'] ?? '');
                if (!empty($p_name)) {
                    $stmt_comp = $pdo->prepare("SELECT id FROM ci_components WHERE parent_ci_id = ? AND name = ?");
                    $stmt_comp->execute([$ci_id, $p_name]);
                    $comp_id = $stmt_comp->fetchColumn();
                    
                    if (!$comp_id) {
                        $attr_comp = json_encode(['created_via' => 'manual_survey']);
                        $stmt_ins_comp = $pdo->prepare("INSERT INTO ci_components (parent_ci_id, name, attributes_json) VALUES (?, ?, ?)");
                        $stmt_ins_comp->execute([$ci_id, $p_name, $attr_comp]);
                    }
                }
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'id' => $survey_db_id]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'get_manual_surveys':
        try {
            $stmt = $pdo->query("SELECT id, client, location, area, device_name, device_label, device_type, vendor, serial, ports_count, rack, ur_rack, ip_address, snmp_community, creation_date, description, created_at FROM manual_portmap_surveys ORDER BY created_at DESC");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'get_vilaseca_analysis':
    case 'get_survey_analysis':
        try {
            $client_param = trim($_GET['client'] ?? $_GET['cliente'] ?? 'VILASECA');
            $sql = "SELECT id, client, location, area, device_name, device_label, device_type, ports_count, rack, ur_rack, ip_address, ports_data_json, created_at FROM manual_portmap_surveys WHERE 1=1";
            $params = [];
            if (!empty($client_param)) {
                $sql .= " AND UPPER(client) LIKE UPPER(:client)";
                $params[':client'] = '%' . $client_param . '%';
            }
            $sql .= " ORDER BY location ASC, rack ASC, device_name ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $surveys = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $total_devices = count($surveys);
            $type_map = [];
            $location_map = [];
            $racks_map = [];
            $all_devices = [];
            $total_ports_all = 0;
            $total_occupied_all = 0;
            $total_vacant_all = 0;

            foreach ($surveys as $s) {
                $dev_type = trim($s['device_type'] ?: 'Otro');
                $loc = trim($s['location'] ?: 'Sin Ubicación');
                $rack = trim($s['rack'] ?: 'Sin Rack');
                $declared_ports = (int)($s['ports_count'] ?: 0);
                $ports = !empty($s['ports_data_json']) ? (json_decode($s['ports_data_json'], true) ?: []) : [];
                $actual_ports_count = max($declared_ports, count($ports));

                $occ = 0;
                $vac = 0;
                foreach ($ports as $p) {
                    $is_up = strtolower($p['link_status'] ?? $p['status'] ?? 'down') === 'up';
                    $dest = trim($p['dest_device'] ?? $p['dest_device_name'] ?? $p['patch_panel_tgt'] ?? $p['dest_port'] ?? '');
                    $has_dest = !empty($dest) && $dest !== '-' && $dest !== 'N/A';
                    if ($is_up || $has_dest) {
                        $occ++;
                    } else {
                        $vac++;
                    }
                }
                if ($actual_ports_count > ($occ + $vac)) {
                    $vac += ($actual_ports_count - ($occ + $vac));
                }

                $total_ports_all += $actual_ports_count;
                $total_occupied_all += $occ;
                $total_vacant_all += $vac;

                $occ_pct = $actual_ports_count > 0 ? round(($occ / $actual_ports_count) * 100, 1) : 0;
                $vac_pct = $actual_ports_count > 0 ? round(($vac / $actual_ports_count) * 100, 1) : 0;

                // Group by type
                if (!isset($type_map[$dev_type])) {
                    $type_map[$dev_type] = [
                        'type' => $dev_type,
                        'devices' => 0,
                        'total_ports' => 0,
                        'occupied_ports' => 0,
                        'vacant_ports' => 0
                    ];
                }
                $type_map[$dev_type]['devices']++;
                $type_map[$dev_type]['total_ports'] += $actual_ports_count;
                $type_map[$dev_type]['occupied_ports'] += $occ;
                $type_map[$dev_type]['vacant_ports'] += $vac;

                // Group by location
                if (!isset($location_map[$loc])) {
                    $location_map[$loc] = [
                        'location' => $loc,
                        'devices' => 0,
                        'racks' => [],
                        'total_ports' => 0,
                        'occupied_ports' => 0,
                        'vacant_ports' => 0,
                        'types' => []
                    ];
                }
                $location_map[$loc]['devices']++;
                if (!in_array($rack, $location_map[$loc]['racks'])) {
                    $location_map[$loc]['racks'][] = $rack;
                }
                $location_map[$loc]['total_ports'] += $actual_ports_count;
                $location_map[$loc]['occupied_ports'] += $occ;
                $location_map[$loc]['vacant_ports'] += $vac;
                $location_map[$loc]['types'][$dev_type] = ($location_map[$loc]['types'][$dev_type] ?? 0) + 1;

                // Group by Rack and Location
                $rack_key = $loc . '___' . $rack;
                if (!isset($racks_map[$rack_key])) {
                    $racks_map[$rack_key] = [
                        'location' => $loc,
                        'rack' => $rack,
                        'device_count' => 0,
                        'devices' => [],
                        'total_ports' => 0,
                        'occupied_ports' => 0,
                        'vacant_ports' => 0,
                        'types' => []
                    ];
                }
                $racks_map[$rack_key]['device_count']++;
                $racks_map[$rack_key]['devices'][] = [
                    'id' => $s['id'],
                    'name' => $s['device_name'],
                    'type' => $dev_type,
                    'ports' => $actual_ports_count,
                    'occupied' => $occ,
                    'vacant' => $vac
                ];
                $racks_map[$rack_key]['total_ports'] += $actual_ports_count;
                $racks_map[$rack_key]['occupied_ports'] += $occ;
                $racks_map[$rack_key]['vacant_ports'] += $vac;
                $racks_map[$rack_key]['types'][$dev_type] = ($racks_map[$rack_key]['types'][$dev_type] ?? 0) + 1;

                $dev_item = [
                    'id' => $s['id'],
                    'device_name' => $s['device_name'],
                    'device_label' => $s['device_label'] ?: '',
                    'device_type' => $dev_type,
                    'location' => $loc,
                    'area' => $s['area'] ?: '',
                    'rack' => $rack,
                    'ur_rack' => $s['ur_rack'] ?: '',
                    'ip_address' => $s['ip_address'] ?: '',
                    'total_ports' => $actual_ports_count,
                    'occupied_ports' => $occ,
                    'vacant_ports' => $vac,
                    'occupancy_pct' => $occ_pct,
                    'vacant_pct' => $vac_pct
                ];
                $all_devices[] = $dev_item;
            }

            // Post-process type_map
            $by_type = [];
            foreach ($type_map as $t => $data) {
                $data['occupancy_pct'] = $data['total_ports'] > 0 ? round(($data['occupied_ports'] / $data['total_ports']) * 100, 1) : 0;
                $data['vacant_pct'] = $data['total_ports'] > 0 ? round(($data['vacant_ports'] / $data['total_ports']) * 100, 1) : 0;
                $data['device_pct'] = $total_devices > 0 ? round(($data['devices'] / $total_devices) * 100, 1) : 0;
                $by_type[] = $data;
            }
            usort($by_type, function($a, $b) {
                return $b['devices'] <=> $a['devices'];
            });

            // Post-process location_map
            $by_location = [];
            foreach ($location_map as $loc => $data) {
                $data['racks_count'] = count($data['racks']);
                $data['occupancy_pct'] = $data['total_ports'] > 0 ? round(($data['occupied_ports'] / $data['total_ports']) * 100, 1) : 0;
                $data['vacant_pct'] = $data['total_ports'] > 0 ? round(($data['vacant_ports'] / $data['total_ports']) * 100, 1) : 0;
                
                // Get racks for this location sorted by device_count DESC
                $loc_racks = [];
                foreach ($racks_map as $rk => $rdata) {
                    if ($rdata['location'] === $loc) {
                        $loc_racks[] = $rdata;
                    }
                }
                usort($loc_racks, function($ra, $rb) {
                    return $rb['device_count'] <=> $ra['device_count'];
                });
                $data['racks_detail'] = $loc_racks;
                $by_location[] = $data;
            }
            usort($by_location, function($a, $b) {
                return $b['devices'] <=> $a['devices'];
            });

            // Post-process racks_map (Racks with highest device count by location, sorted by location size then rack density)
            $racks_density = array_values($racks_map);
            usort($racks_density, function($a, $b) use ($location_map) {
                $locCountA = $location_map[$a['location']]['devices'] ?? 0;
                $locCountB = $location_map[$b['location']]['devices'] ?? 0;
                if ($locCountA !== $locCountB) {
                    return $locCountB <=> $locCountA; // Largest locations first
                }
                if ($a['location'] === $b['location']) {
                    return $b['device_count'] <=> $a['device_count'];
                }
                return strcmp($a['location'], $b['location']);
            });

            // Global top racks by device count
            $top_racks_global = $racks_density;
            usort($top_racks_global, function($a, $b) {
                return $b['device_count'] <=> $a['device_count'];
            });

            // Tops: Top vacant ports
            $top_vacant = $all_devices;
            usort($top_vacant, function($a, $b) {
                if ($b['vacant_ports'] === $a['vacant_ports']) {
                    return $b['vacant_pct'] <=> $a['vacant_pct'];
                }
                return $b['vacant_ports'] <=> $a['vacant_ports'];
            });
            $top_vacant = array_slice($top_vacant, 0, 15);

            // Tops: Top occupied/full ports
            $top_occupied = $all_devices;
            usort($top_occupied, function($a, $b) {
                if ($b['occupied_ports'] === $a['occupied_ports']) {
                    return $b['occupancy_pct'] <=> $a['occupancy_pct'];
                }
                return $b['occupied_ports'] <=> $a['occupied_ports'];
            });
            $top_occupied = array_slice($top_occupied, 0, 15);

            $global_occ_pct = $total_ports_all > 0 ? round(($total_occupied_all / $total_ports_all) * 100, 1) : 0;
            $global_vac_pct = $total_ports_all > 0 ? round(($total_vacant_all / $total_ports_all) * 100, 1) : 0;

            echo json_encode([
                'success' => true,
                'client' => $client_param,
                'summary' => [
                    'total_devices' => $total_devices,
                    'total_locations' => count($by_location),
                    'total_racks' => count($racks_map),
                    'total_ports' => $total_ports_all,
                    'total_occupied' => $total_occupied_all,
                    'total_vacant' => $total_vacant_all,
                    'occupancy_pct' => $global_occ_pct,
                    'vacant_pct' => $global_vac_pct,
                ],
                'by_type' => $by_type,
                'by_location' => $by_location,
                'top_racks_by_location' => $racks_density,
                'top_racks_global' => $top_racks_global,
                'top_vacant' => $top_vacant,
                'top_occupied' => $top_occupied,
                'all_devices' => $all_devices
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'get_manual_survey':
    case 'get_manual_survey_detail':
        $id = $_GET['id'] ?? null;
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'Missing id']);
            break;
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM manual_portmap_surveys WHERE id = ?");
            $stmt->execute([$id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($data) {
                if (empty($data['vendor']) && !empty($data['ports_data_json'])) {
                    $p = json_decode($data['ports_data_json'], true);
                    if (!empty($p[0]['vendor_src'])) $data['vendor'] = $p[0]['vendor_src'];
                }
                if (empty($data['serial'])) {
                    if (!empty($data['device_label']) && strtoupper($data['device_label']) !== 'N/A') {
                        $data['serial'] = $data['device_label'];
                    } elseif (!empty($data['ports_data_json'])) {
                        $p = json_decode($data['ports_data_json'], true);
                        if (!empty($p[0]['serial_src'])) $data['serial'] = $p[0]['serial_src'];
                    }
                }
                $data['ports'] = !empty($data['ports_data_json']) ? (json_decode($data['ports_data_json'], true) ?: []) : [];
                $data['images'] = !empty($data['images_json']) ? (json_decode($data['images_json'], true) ?: []) : [];
                $data['config_files'] = !empty($data['config_files_json']) ? (json_decode($data['config_files_json'], true) ?: []) : [];
                $data['diagram'] = !empty($data['diagram_json']) ? (json_decode($data['diagram_json'], true) ?: null) : null;
                echo json_encode(['success' => true, 'data' => $data]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Survey not found']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'upload_config_file':
        if (!isset($_FILES['config_file']) || $_FILES['config_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'No file received or upload error']);
            break;
        }

        $base_dir = dirname(__DIR__);
        $primary_upload_path = $base_dir . '/storage/uploads/configs/';
        $alt_upload_path = $base_dir . '/public/uploads/configs/';

        if (!is_dir($primary_upload_path)) {
            @mkdir($primary_upload_path, 0777, true);
            @chmod($primary_upload_path, 0777);
        }
        if (!is_dir($alt_upload_path)) {
            @mkdir($alt_upload_path, 0777, true);
            @chmod($alt_upload_path, 0777);
        }

        $filename = $_FILES['config_file']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowed = ['txt', 'cfg', 'conf', 'log', 'json', 'ini', 'xml', 'yaml', 'yml'];

        if (!in_array($ext, $allowed)) {
            echo json_encode(['success' => false, 'error' => 'Formato de archivo no permitido. Use .txt, .cfg, .conf, .log, .json o .ini']);
            break;
        }

        $new_name = "CFG_EQUIPO_" . uniqid() . "." . $ext;
        $primary_dest = $primary_upload_path . $new_name;
        $alt_dest = $alt_upload_path . $new_name;
        $saved_path = null;

        if (@move_uploaded_file($_FILES['config_file']['tmp_name'], $primary_dest) || @copy($_FILES['config_file']['tmp_name'], $primary_dest)) {
            $saved_path = "storage/uploads/configs/" . $new_name;
            $full_path = $primary_dest;
        } else if (@move_uploaded_file($_FILES['config_file']['tmp_name'], $alt_dest) || @copy($_FILES['config_file']['tmp_name'], $alt_dest)) {
            $saved_path = "uploads/configs/" . $new_name;
            $full_path = $alt_dest;
        }

        if ($saved_path) {
            $content = @file_get_contents($full_path) ?: '';
            echo json_encode([
                'success' => true,
                'file' => [
                    'name' => $filename,
                    'path' => $saved_path,
                    'size' => $_FILES['config_file']['size'],
                    'uploaded_at' => date('Y-m-d H:i:s'),
                    'content' => mb_substr($content, 0, 50000)
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No se pudo guardar el archivo de configuración en el servidor']);
        }
        break;

    case 'upload_diagram_file':
        if (!isset($_FILES['diagram_file']) || $_FILES['diagram_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'No file received or upload error']);
            break;
        }

        $base_dir = dirname(__DIR__);
        $primary_upload_path = $base_dir . '/storage/uploads/diagrams/';
        $alt_upload_path = $base_dir . '/public/uploads/diagrams/';

        if (!is_dir($primary_upload_path)) {
            @mkdir($primary_upload_path, 0777, true);
            @chmod($primary_upload_path, 0777);
        }
        if (!is_dir($alt_upload_path)) {
            @mkdir($alt_upload_path, 0777, true);
            @chmod($alt_upload_path, 0777);
        }

        $filename = $_FILES['diagram_file']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowed = ['vsd', 'vsdx', 'vdx', 'svg', 'png', 'jpg', 'jpeg', 'pdf', 'webp'];

        if (!in_array($ext, $allowed)) {
            echo json_encode(['success' => false, 'error' => 'Formato de diagrama no permitido. Use .vsdx, .vdx, .svg, .png, .jpg o .pdf']);
            break;
        }

        $new_name = "DIAGRAM_PM_" . uniqid() . "." . $ext;
        $primary_dest = $primary_upload_path . $new_name;
        $alt_dest = $alt_upload_path . $new_name;
        $saved_path = null;
        $full_dest_path = null;

        if (@move_uploaded_file($_FILES['diagram_file']['tmp_name'], $primary_dest) || @copy($_FILES['diagram_file']['tmp_name'], $primary_dest)) {
            $saved_path = "storage/uploads/diagrams/" . $new_name;
            $full_dest_path = $primary_dest;
        } else if (@move_uploaded_file($_FILES['diagram_file']['tmp_name'], $alt_dest) || @copy($_FILES['diagram_file']['tmp_name'], $alt_dest)) {
            $saved_path = "uploads/diagrams/" . $new_name;
            $full_dest_path = $alt_dest;
        }

        if ($saved_path) {
            $preview_path = $saved_path;
            $preview_ext = $ext;
            $vsdx_thumb = null;

            if (in_array($ext, ['vsdx', 'vdx', 'vsd']) && $full_dest_path) {
                $vsdx_thumb = extractVisioThumbnailFromFile($full_dest_path);
                if ($vsdx_thumb && !empty($vsdx_thumb['path'])) {
                    $preview_path = $vsdx_thumb['path'];
                    $preview_ext = $vsdx_thumb['ext'];
                }
            }

            echo json_encode([
                'success' => true,
                'diagram' => [
                    'type' => 'upload',
                    'title' => $filename,
                    'name' => $filename,
                    'path' => $preview_path,
                    'original_file' => $saved_path,
                    'ext' => $preview_ext,
                    'original_ext' => $ext,
                    'size' => $_FILES['diagram_file']['size'],
                    'uploaded_at' => date('Y-m-d H:i:s'),
                    'include_in_pdf' => true
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No se pudo guardar el diagrama en el servidor']);
        }
        break;

    case 'delete_manual_survey':
        $id = $_POST['id'] ?? null;
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'Missing id']);
            break;
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM manual_portmap_surveys WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'bulk_delete_manual_surveys':
        $rawIds = $_POST['ids'] ?? null;
        if (!$rawIds) {
            echo json_encode(['success' => false, 'error' => 'No se proporcionaron levantamientos para eliminar']);
            break;
        }
        if (is_string($rawIds)) {
            $parsed = json_decode($rawIds, true);
            $ids = is_array($parsed) ? $parsed : explode(',', $rawIds);
        } else {
            $ids = (array)$rawIds;
        }
        $ids = array_filter(array_map('intval', $ids));
        if (empty($ids)) {
            echo json_encode(['success' => false, 'error' => 'IDs de levantamiento no válidos']);
            break;
        }

        try {
            $inClause = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("DELETE FROM manual_portmap_surveys WHERE id IN ($inClause)");
            $stmt->execute(array_values($ids));
            $deletedCount = $stmt->rowCount();

            echo json_encode([
                'success' => true,
                'deleted_count' => $deletedCount,
                'message' => "Se eliminaron {$deletedCount} levantamientos correctamente."
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Error al eliminar levantamientos en base de datos: ' . $e->getMessage()]);
        }
        break;

    case 'import_ports_from_survey':
        $device_id = $_POST['device_id'] ?? null;
        $survey_id = $_POST['survey_id'] ?? null;

        if (!$device_id || !$survey_id) {
            echo json_encode(['success' => false, 'error' => 'Missing device_id or survey_id']);
            break;
        }

        try {
            $pdo->beginTransaction();

            // 1. Fetch the survey details
            $stmt = $pdo->prepare("SELECT ports_data_json FROM manual_portmap_surveys WHERE id = ?");
            $stmt->execute([$survey_id]);
            $ports_data_json = $stmt->fetchColumn();

            if (!$ports_data_json) {
                echo json_encode(['success' => false, 'error' => 'Survey not found or has no ports']);
                $pdo->rollBack();
                break;
            }

            $ports_arr = json_decode($ports_data_json, true) ?: [];

            // 2. Insert components/ports into the selected CMDB CI
            $imported_count = 0;
            foreach ($ports_arr as $p_item) {
                $p_name = trim($p_item['port_name'] ?? '');
                if (!empty($p_name)) {
                    // Check if component already exists for this CI
                    $stmt_comp = $pdo->prepare("SELECT id FROM ci_components WHERE parent_ci_id = ? AND name = ?");
                    $stmt_comp->execute([$device_id, $p_name]);
                    $comp_id = $stmt_comp->fetchColumn();
                    
                    if (!$comp_id) {
                        // Determine connection type based on port name or default to 'network'
                        $connection_type = 'network';
                        $p_name_lower = strtolower($p_name);
                        if (strpos($p_name_lower, 'pwr') !== false || strpos($p_name_lower, 'psu') !== false || strpos($p_name_lower, 'power') !== false || strpos($p_name_lower, 'energia') !== false || strpos($p_name_lower, 'toma') !== false) {
                            $connection_type = 'power';
                        }
                        
                        $attr_comp = json_encode([
                            'created_via' => 'manual_survey_import',
                            'connection_type' => $connection_type,
                            'description' => $p_item['description'] ?? ''
                        ]);
                        $stmt_ins_comp = $pdo->prepare("INSERT INTO ci_components (parent_ci_id, name, attributes_json) VALUES (?, ?, ?)");
                        $stmt_ins_comp->execute([$device_id, $p_name, $attr_comp]);
                        $imported_count++;
                    }
                }
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'imported_count' => $imported_count]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'upload_report_logo':
        if (!isset($_FILES['logo_file']) || $_FILES['logo_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'No file received or upload error']);
            break;
        }

        $base_dir = dirname(__DIR__);
        $primary_upload_path = $base_dir . '/storage/uploads/';
        $alt_upload_path = $base_dir . '/public/uploads/';

        if (!is_dir($primary_upload_path)) {
            @mkdir($primary_upload_path, 0777, true);
            @chmod($primary_upload_path, 0777);
        }
        if (!is_dir($alt_upload_path)) {
            @mkdir($alt_upload_path, 0777, true);
            @chmod($alt_upload_path, 0777);
        }

        $filename = $_FILES['logo_file']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowed = ['png', 'jpg', 'jpeg', 'svg', 'webp', 'gif'];

        if (!in_array($ext, $allowed)) {
            echo json_encode(['success' => false, 'error' => 'Formato de imagen no permitido. Use PNG, JPG, SVG o WEBP.']);
            break;
        }

        $slot = $_POST['slot'] ?? '1';
        $new_name = "LOGO_REPORT_SLOT" . intval($slot) . "_" . uniqid() . "." . $ext;
        $primary_dest = $primary_upload_path . $new_name;
        $alt_dest = $alt_upload_path . $new_name;
        $saved_path = null;

        if (@move_uploaded_file($_FILES['logo_file']['tmp_name'], $primary_dest) || @copy($_FILES['logo_file']['tmp_name'], $primary_dest)) {
            $saved_path = "storage/uploads/" . $new_name;
        } else if (@move_uploaded_file($_FILES['logo_file']['tmp_name'], $alt_dest) || @copy($_FILES['logo_file']['tmp_name'], $alt_dest)) {
            $saved_path = "uploads/" . $new_name;
        } else {
            $raw_bytes = @file_get_contents($_FILES['logo_file']['tmp_name']);
            if ($raw_bytes !== false) {
                if (@file_put_contents($primary_dest, $raw_bytes) !== false) {
                    $saved_path = "storage/uploads/" . $new_name;
                } else if (@file_put_contents($alt_dest, $raw_bytes) !== false) {
                    $saved_path = "uploads/" . $new_name;
                }
            }
        }

        if ($saved_path) {
            echo json_encode(['success' => true, 'url' => $saved_path, 'filename' => $new_name]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No se pudo guardar la imagen del logotipo en el servidor']);
        }
        break;

    case 'get_analysis_filters':
        try {
            $client_param = trim($_GET['client'] ?? 'VILASECA');
            
            // 1. Get distinct locations for client from active manual_portmap_surveys
            $sql_loc = "SELECT DISTINCT location FROM manual_portmap_surveys WHERE UPPER(client) LIKE UPPER(:client) AND location IS NOT NULL AND location != '' ORDER BY location ASC";
            $stmt_loc = $pdo->prepare($sql_loc);
            $stmt_loc->execute([':client' => '%' . $client_param . '%']);
            $locations = $stmt_loc->fetchAll(PDO::FETCH_COLUMN);

            // Also check ci_instances associated with active surveys
            $sql_ci_loc = "SELECT DISTINCT JSON_UNQUOTE(JSON_EXTRACT(i.attributes_json, '$.location')) as loc 
                           FROM ci_instances i 
                           JOIN manual_portmap_surveys s ON JSON_UNQUOTE(JSON_EXTRACT(i.attributes_json, '$.manual_survey_id')) = s.id
                           WHERE UPPER(s.client) LIKE UPPER(:client) AND i.attributes_json IS NOT NULL";
            $stmt_ci_loc = $pdo->prepare($sql_ci_loc);
            $stmt_ci_loc->execute([':client' => '%' . $client_param . '%']);
            $ci_locs = $stmt_ci_loc->fetchAll(PDO::FETCH_COLUMN);
            foreach ($ci_locs as $cl) {
                if (!empty($cl) && !in_array($cl, $locations)) {
                    $locations[] = $cl;
                }
            }
            sort($locations);

            // 2. Get distinct devices with location for client from active manual_portmap_surveys
            $sql_dev = "SELECT DISTINCT device_name, location, device_type FROM manual_portmap_surveys WHERE UPPER(client) LIKE UPPER(:client) AND device_name IS NOT NULL AND device_name != '' ORDER BY device_name ASC";
            $stmt_dev = $pdo->prepare($sql_dev);
            $stmt_dev->execute([':client' => '%' . $client_param . '%']);
            $devices = $stmt_dev->fetchAll(PDO::FETCH_ASSOC);

            // Also check ci_instances associated with active surveys
            $sql_ci_dev = "SELECT i.hostname as device_name, c.name as device_type, JSON_UNQUOTE(JSON_EXTRACT(i.attributes_json, '$.location')) as location 
                           FROM ci_instances i 
                           JOIN ci_categories c ON i.category_id = c.id 
                           JOIN manual_portmap_surveys s ON JSON_UNQUOTE(JSON_EXTRACT(i.attributes_json, '$.manual_survey_id')) = s.id
                           WHERE UPPER(s.client) LIKE UPPER(:client)";
            $stmt_ci_dev = $pdo->prepare($sql_ci_dev);
            $stmt_ci_dev->execute([':client' => '%' . $client_param . '%']);
            $ci_devs = $stmt_ci_dev->fetchAll(PDO::FETCH_ASSOC);

            $existing_names = array_map(function($d){ return $d['device_name']; }, $devices);
            foreach ($ci_devs as $cd) {
                if (!empty($cd['device_name']) && !in_array($cd['device_name'], $existing_names)) {
                    $devices[] = $cd;
                }
            }

            echo json_encode([
                'success' => true,
                'client' => $client_param,
                'locations' => array_values($locations),
                'devices' => array_values($devices)
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'get_connection_graph':
        try {
            $client_param = trim($_GET['client'] ?? 'VILASECA');
            $location_param = trim($_GET['location'] ?? '');
            $device_param = trim($_GET['device_name'] ?? '');

            // Fetch surveys matching client and filters
            $sql = "SELECT id, client, location, area, device_name, device_type, ports_count, rack, ur_rack, ip_address, ports_data_json FROM manual_portmap_surveys WHERE 1=1";
            $params = [];

            if (!empty($client_param)) {
                $sql .= " AND UPPER(client) LIKE UPPER(:client)";
                $params[':client'] = '%' . $client_param . '%';
            }

            if (!empty($location_param)) {
                $sql .= " AND location = :location";
                $params[':location'] = $location_param;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $surveys = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch CMDB port mappings strictly for this client
            $sql_pm = "SELECT 
                        i1.hostname as src_device, i1.category_id as src_cat,
                        c1.name as src_port,
                        i2.hostname as dest_device, i2.category_id as dest_cat,
                        c2.name as dest_port,
                        pm.cable_type, pm.color_code, pm.notes,
                        COALESCE(pm.connection_type, 'network') as connection_type
                       FROM port_mappings pm
                       JOIN ci_components c1 ON pm.source_component_id = c1.id
                       JOIN ci_instances i1 ON c1.parent_ci_id = i1.id
                       JOIN ci_components c2 ON pm.target_component_id = c2.id
                       JOIN ci_instances i2 ON c2.parent_ci_id = i2.id";

            $pm_params = [];
            if (!empty($client_param)) {
                $sql_pm .= " WHERE (UPPER(JSON_UNQUOTE(JSON_EXTRACT(i1.attributes_json, '$.client'))) LIKE UPPER(:client) 
                                OR UPPER(JSON_UNQUOTE(JSON_EXTRACT(i2.attributes_json, '$.client'))) LIKE UPPER(:client))";
                $pm_params[':client'] = '%' . $client_param . '%';
            }

            $stmt_pm = $pdo->prepare($sql_pm);
            $stmt_pm->execute($pm_params);
            $cmdb_mappings = $stmt_pm->fetchAll(PDO::FETCH_ASSOC);

            // Device categorization helper
            $dev_categorizer = function($type_str, $name_str) {
                $t = strtolower(($type_str ?? '') . ' ' . ($name_str ?? ''));
                if (strpos($t, 'firewall') !== false || strpos($t, 'forti') !== false || strpos($t, '_fw') !== false) return 'firewall';
                if (strpos($t, 'router') !== false || strpos($t, '_rt') !== false) return 'router';
                if (strpos($t, 'switch') !== false || strpos($t, '_sw') !== false) return 'switch';
                if (strpos($t, 'patch') !== false || strpos($t, '_pp') !== false) return 'patch_panel';
                if (strpos($t, 'convertidor') !== false || strpos($t, 'cvm') !== false || strpos($t, 'transceiver') !== false) return 'converter';
                if (strpos($t, 'inyector') !== false || strpos($t, 'poe') !== false || strpos($t, 'ipoe') !== false) return 'poe';
                if (strpos($t, 'access point') !== false || strpos($t, '_ap') !== false || preg_match('/\bap\d*\b/i', $name_str ?? '')) return 'ap';
                if (strpos($t, 'server') !== false || strpos($t, 'servidor') !== false || strpos($t, '_srv') !== false || strpos($t, 'ctl') !== false || strpos($t, 'central') !== false) return 'server';
                if (strpos($t, 'caja terminal') !== false || strpos($t, 'caja terminar') !== false || strpos($t, 'roseta') !== false || strpos($t, 'odf') !== false) return 'demarcation';
                if (strpos($t, 'sale dc') !== false || strpos($t, 'puesto') !== false || strpos($t, 'usuario') !== false || strpos($t, 'drop') !== false) return 'drop';
                return 'device';
            };

            // Network tier assigner for Visio hierarchical layout
            $tier_assigner = function($cat) {
                switch ($cat) {
                    case 'demarcation': return 0; // External WAN / Demarcation
                    case 'router': return 1;      // Edge Routing
                    case 'firewall': return 1;    // Perimeter Security
                    case 'switch': return 2;      // Core & Distribution Switching
                    case 'patch_panel': return 3; // Passive Distribution
                    case 'converter': return 3;   // Media Conversion
                    case 'poe': return 3;         // Power Injection
                    case 'server': return 4;      // Servers & Controllers
                    case 'ap': return 4;          // Wireless APs
                    case 'drop': return 4;        // Horizontal Outlets / Users
                    default: return 3;
                }
            };

            // Index surveys by location and device name
            $devsByLoc = [];
            $realDeviceNames = [];
            $nodes_map = [];

            foreach ($surveys as $srv) {
                $src_name = trim($srv['device_name']);
                if (empty($src_name)) continue;
                $loc = $srv['location'] ?: 'General';
                $devsByLoc[$loc][$src_name] = $srv;
                $realDeviceNames[strtoupper($src_name)] = $src_name;

                $cat = $dev_categorizer($srv['device_type'], $src_name);
                $tier = $tier_assigner($cat);

                $nodes_map[$src_name] = [
                    'id' => $src_name,
                    'label' => $src_name,
                    'client' => $srv['client'] ?: $client_param,
                    'location' => $loc,
                    'area' => $srv['area'] ?: '',
                    'device_type' => $cat,
                    'device_type_raw' => $srv['device_type'] ?: 'Hardware',
                    'tier' => $tier,
                    'ip_address' => $srv['ip_address'] ?: '',
                    'rack' => $srv['rack'] ?: '',
                    'ur_rack' => $srv['ur_rack'] ?: '',
                    'ports_count' => (int)($srv['ports_count'] ?: 0),
                    'active_ports' => 0
                ];
            }

            $genericWords = ['SWITCH', 'ROUTER', 'FIREWALL', 'CONVERTIDOR', 'AP', 'SERVER', 'SERVIDOR', 'PATCH PANEL', 'INYECTOR POE', 'POE', 'TRANSCEIVER'];

            // Build edges and resolve reciprocal connections
            $edges_map = [];
            $addedPortPairs = []; // key: devA:portA<->devB:portB to prevent duplicate reciprocal cables

            foreach ($surveys as $srv) {
                $src_name = trim($srv['device_name']);
                if (empty($src_name)) continue;
                $loc = $srv['location'] ?: 'General';

                $ports = !empty($srv['ports_data_json']) ? json_decode($srv['ports_data_json'], true) : [];
                if (!is_array($ports)) continue;

                foreach ($ports as $p) {
                    $dest_raw = trim($p['dest_device'] ?? $p['destination_device'] ?? '');
                    $dest_port = trim($p['dest_port'] ?? $p['destination_port'] ?? '');
                    $src_port = trim($p['port_name'] ?? '');
                    $cable = trim($p['cable_type'] ?? 'UTP Cat6A');
                    $vlan = trim($p['vlan'] ?? '');
                    $status = trim($p['status'] ?? 'Activo');
                    $patch_src = trim($p['patch_panel_src'] ?? '');

                    // Ignore disconnected or empty ports
                    if (empty($dest_raw) || in_array(strtoupper($dest_raw), ['N/A', 'DISPONIBLE', 'SIN CONEXION', 'LIBRE', 'NONE', '-'])) {
                        continue;
                    }

                    // 1. Resolve target device
                    $targetDev = null;
                    $isGeneric = in_array(strtoupper($dest_raw), $genericWords);

                    if (!$isGeneric && isset($devsByLoc[$loc][$dest_raw])) {
                        // Direct match with surveyed device in same location
                        $targetDev = $dest_raw;
                    } elseif (!$isGeneric && isset($realDeviceNames[strtoupper($dest_raw)])) {
                        // Direct match with surveyed device globally
                        $targetDev = $realDeviceNames[strtoupper($dest_raw)];
                    } else {
                        // Check reciprocal port match in same location
                        $destCat = $dev_categorizer($dest_raw, $dest_raw);

                        foreach ($devsByLoc[$loc] as $candName => $candSrv) {
                            if ($candName === $src_name) continue;
                            $cCat = $dev_categorizer($candSrv['device_type'], $candName);

                            // Only consider candidates matching the target category or name
                            if ($destCat !== 'device' && $cCat !== $destCat && stripos($candName, $dest_raw) === false) {
                                continue;
                            }

                            $candPorts = json_decode($candSrv['ports_data_json'] ?? '[]', true);
                            if (!is_array($candPorts)) continue;

                            foreach ($candPorts as $cp) {
                                $cPort = trim($cp['port_name'] ?? '');
                                $cDestPort = trim($cp['dest_port'] ?? $cp['destination_port'] ?? '');
                                $cDestDev = trim($cp['dest_device'] ?? $cp['destination_device'] ?? '');

                                // Either exact reciprocal port pair, or candidate explicitly points to our src_name/src_port
                                if ($cPort !== '' && $cPort === $dest_port && ($cDestPort === $src_port || stripos($cDestDev, $src_name) !== false)) {
                                    $targetDev = $candName;
                                    break 2;
                                }
                                if (stripos($cDestDev, $src_name) !== false && $cDestPort === $src_port) {
                                    $targetDev = $candName;
                                    break 2;
                                }
                                if ($cPort !== '' && $cPort === $dest_port && !in_array($dest_port, ['LAN', 'IN', 'POE', 'WAN1', 'WAN2'])) {
                                    $cTargetCat = $dev_categorizer($cDestDev, $cDestDev);
                                    $srcCat = $dev_categorizer($srv['device_type'], $src_name);
                                    if ($cTargetCat === $srcCat || stripos($cDestDev, $src_name) !== false || $cDestDev === '') {
                                        $targetDev = $candName;
                                        break 2;
                                    }
                                }
                            }
                        }

                        // If not matched, try matching same rack or default device of requested category
                        if (!$targetDev && $destCat !== 'device') {
                            $srcRack = $srv['rack'] ?? '';
                            // Priority 1: Same rack
                            foreach ($devsByLoc[$loc] as $candName => $candSrv) {
                                if ($candName === $src_name) continue;
                                $cCat = $dev_categorizer($candSrv['device_type'], $candName);
                                if ($cCat === $destCat && !empty($srcRack) && ($candSrv['rack'] ?? '') === $srcRack) {
                                    $targetDev = $candName;
                                    break;
                                }
                            }
                            // Priority 2: Primary unit (e.g. SW01, RT01, FW01)
                            if (!$targetDev) {
                                foreach ($devsByLoc[$loc] as $candName => $candSrv) {
                                    if ($candName === $src_name) continue;
                                    if ($dev_categorizer($candSrv['device_type'], $candName) === $destCat) {
                                        if (strpos($candName, '01') !== false) {
                                            $targetDev = $candName;
                                            break;
                                        }
                                    }
                                }
                            }
                            // Priority 3: Any candidate of same category
                            if (!$targetDev) {
                                foreach ($devsByLoc[$loc] as $candName => $candSrv) {
                                    if ($candName === $src_name) continue;
                                    if ($dev_categorizer($candSrv['device_type'], $candName) === $destCat) {
                                        $targetDev = $candName;
                                        break;
                                    }
                                }
                            }
                        }
                    }

                    // Format clean external endpoint names
                    $destName = $targetDev;
                    if (!$destName) {
                        $upperDest = strtoupper($dest_raw);
                        if (strpos($upperDest, 'CAJA TERMINA') !== false || strpos($upperDest, 'ROSETA') !== false || strpos($upperDest, 'ODF') !== false) {
                            $destName = "Acometida Fibra Óptica (ODF)";
                        } elseif (strpos($upperDest, 'SALE DC') !== false || strpos($upperDest, 'PUESTO') !== false) {
                            $destName = "Salidas Horizontales DC";
                        } elseif (strpos($upperDest, 'CENTRAL TELEF') !== false) {
                            $destName = "Central Telefónica (PBX)";
                        } else {
                            $destName = $dest_raw;
                        }
                    }

                    // Register destination node if not present
                    if (!isset($nodes_map[$destName])) {
                        $cat = $dev_categorizer($destName, $destName);
                        $nodes_map[$destName] = [
                            'id' => $destName,
                            'label' => $destName,
                            'client' => $client_param,
                            'location' => $loc,
                            'area' => 'Infraestructura',
                            'device_type' => $cat,
                            'device_type_raw' => $destName,
                            'tier' => $tier_assigner($cat),
                            'ip_address' => '',
                            'rack' => '',
                            'ur_rack' => '',
                            'ports_count' => 0,
                            'active_ports' => 0
                        ];
                    }

                    // Check deduplication key for this physical cable
                    $pairEndpoints = [
                        $src_name . '::' . ($src_port ?: 'p'),
                        $destName . '::' . ($dest_port ?: 'p')
                    ];
                    sort($pairEndpoints);
                    $cableKey = $pairEndpoints[0] . '<==>' . $pairEndpoints[1];

                    if (isset($addedPortPairs[$cableKey])) {
                        continue;
                    }
                    $addedPortPairs[$cableKey] = true;

                    // Edge key between the two devices
                    $edge_pair = [$src_name, $destName];
                    sort($edge_pair);
                    $edge_key = $edge_pair[0] . '___' . $edge_pair[1];

                    $is_fiber = (stripos($cable, 'fibra') !== false || stripos($cable, 'os2') !== false || stripos($cable, 'om3') !== false || stripos($cable, 'om4') !== false);

                    if (!isset($edges_map[$edge_key])) {
                        $edges_map[$edge_key] = [
                            'id' => $edge_key,
                            'from' => $edge_pair[0],
                            'to' => $edge_pair[1],
                            'count' => 0,
                            'cable_type' => $cable,
                            'is_fiber' => $is_fiber,
                            'details' => []
                        ];
                    }

                    $edges_map[$edge_key]['count']++;
                    if ($is_fiber) {
                        $edges_map[$edge_key]['is_fiber'] = true;
                    }
                    $edges_map[$edge_key]['details'][] = [
                        'src_device' => $src_name,
                        'src_port' => $src_port,
                        'dest_device' => $destName,
                        'dest_port' => $dest_port,
                        'cable_type' => $cable,
                        'is_fiber' => $is_fiber,
                        'vlan' => $vlan,
                        'status' => $status,
                        'patch_panel_src' => $patch_src
                    ];

                    $nodes_map[$src_name]['active_ports']++;
                    if (isset($nodes_map[$destName])) {
                        $nodes_map[$destName]['active_ports']++;
                    }
                }
            }

            // Process CMDB Mappings if any
            foreach ($cmdb_mappings as $m) {
                $src = trim($m['src_device']);
                $dest = trim($m['dest_device']);
                if (empty($src) || empty($dest)) continue;

                if (!empty($location_param) && isset($nodes_map[$src]) && $nodes_map[$src]['location'] !== $location_param && isset($nodes_map[$dest]) && $nodes_map[$dest]['location'] !== $location_param) {
                    continue;
                }

                if (!isset($nodes_map[$src])) {
                    $cat = $dev_categorizer($m['src_cat'] ?? '', $src);
                    $nodes_map[$src] = [
                        'id' => $src,
                        'label' => $src,
                        'client' => $client_param,
                        'location' => 'CMDB Inventory',
                        'area' => 'CMDB',
                        'device_type' => $cat,
                        'device_type_raw' => 'CMDB CI',
                        'tier' => $tier_assigner($cat),
                        'ip_address' => '',
                        'rack' => '',
                        'ur_rack' => '',
                        'ports_count' => 0,
                        'active_ports' => 0
                    ];
                }

                if (!isset($nodes_map[$dest])) {
                    $cat = $dev_categorizer($m['dest_cat'] ?? '', $dest);
                    $nodes_map[$dest] = [
                        'id' => $dest,
                        'label' => $dest,
                        'client' => $client_param,
                        'location' => 'CMDB Inventory',
                        'area' => 'CMDB',
                        'device_type' => $cat,
                        'device_type_raw' => 'CMDB CI',
                        'tier' => $tier_assigner($cat),
                        'ip_address' => '',
                        'rack' => '',
                        'ur_rack' => '',
                        'ports_count' => 0,
                        'active_ports' => 0
                    ];
                }

                $pairEndpoints = [
                    $src . '::' . ($m['src_port'] ?: 'p'),
                    $dest . '::' . ($m['dest_port'] ?: 'p')
                ];
                sort($pairEndpoints);
                $cableKey = $pairEndpoints[0] . '<==>' . $pairEndpoints[1];

                if (isset($addedPortPairs[$cableKey])) {
                    continue;
                }
                $addedPortPairs[$cableKey] = true;

                $edge_pair = [$src, $dest];
                sort($edge_pair);
                $edge_key = $edge_pair[0] . '___' . $edge_pair[1];

                $cable = $m['cable_type'] ?: 'UTP Cat6A';
                $is_fiber = (stripos($cable, 'fibra') !== false || stripos($cable, 'os2') !== false || stripos($cable, 'om3') !== false || stripos($cable, 'om4') !== false);

                if (!isset($edges_map[$edge_key])) {
                    $edges_map[$edge_key] = [
                        'id' => $edge_key,
                        'from' => $edge_pair[0],
                        'to' => $edge_pair[1],
                        'count' => 0,
                        'cable_type' => $cable,
                        'is_fiber' => $is_fiber,
                        'details' => []
                    ];
                }

                $edges_map[$edge_key]['count']++;
                if ($is_fiber) {
                    $edges_map[$edge_key]['is_fiber'] = true;
                }
                $edges_map[$edge_key]['details'][] = [
                    'src_device' => $src,
                    'src_port' => $m['src_port'],
                    'dest_device' => $dest,
                    'dest_port' => $m['dest_port'],
                    'cable_type' => $cable,
                    'is_fiber' => $is_fiber,
                    'vlan' => '',
                    'status' => 'Activo',
                    'patch_panel_src' => $m['notes'] ?: ''
                ];

                $nodes_map[$src]['active_ports']++;
                $nodes_map[$dest]['active_ports']++;
            }

            // Filter graph by device if device_param is selected
            if (!empty($device_param) && isset($nodes_map[$device_param])) {
                $connected_nodes = [$device_param => true];
                $filtered_edges = [];

                foreach ($edges_map as $ekey => $edge) {
                    if ($edge['from'] === $device_param || $edge['to'] === $device_param) {
                        $connected_nodes[$edge['from']] = true;
                        $connected_nodes[$edge['to']] = true;
                        $filtered_edges[$ekey] = $edge;
                    }
                }

                $nodes_map = array_intersect_key($nodes_map, $connected_nodes);
                $edges_map = $filtered_edges;
            }

            // Sort nodes deterministically by tier then id
            uasort($nodes_map, function($a, $b) {
                if ($a['tier'] !== $b['tier']) {
                    return $a['tier'] <=> $b['tier'];
                }
                return strcmp($a['id'], $b['id']);
            });

            echo json_encode([
                'success' => true,
                'client' => $client_param,
                'nodes' => array_values($nodes_map),
                'edges' => array_values($edges_map)
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'download_excel_template':
        $templatePath = __DIR__ . '/../portmapping/Plantilla_Oficial_Portmapping_Bulk.xlsx';
        if (!file_exists($templatePath)) {
            $templatePath = __DIR__ . '/../portmapping/Plantilla port - Bodega JUTECERO 1.xlsx';
        }
        if (file_exists($templatePath)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="Plantilla_Oficial_Portmapping_Bulk.xlsx"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($templatePath));
            readfile($templatePath);
            exit;
        } else {
            echo json_encode(['success' => false, 'error' => 'Plantilla no encontrada en el servidor.']);
            exit;
        }
        break;

    case 'preview_excel_portmapping':
        require_once __DIR__ . '/../src/PortmappingExcelImporter.php';
        if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'No se recibió ningún archivo o hubo un error en la carga.']);
            break;
        }
        $tmpName = $_FILES['excel_file']['tmp_name'];
        $analysis = PortmappingExcelImporter::analyzeExcel($tmpName);
        echo json_encode($analysis);
        break;

    case 'process_excel_portmapping':
        require_once __DIR__ . '/../src/PortmappingExcelImporter.php';
        if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'No se recibió ningún archivo o hubo un error en la carga.']);
            break;
        }
        $tmpName = $_FILES['excel_file']['tmp_name'];
        $overwrite = !empty($_POST['overwrite_duplicate']) && ($_POST['overwrite_duplicate'] === '1' || $_POST['overwrite_duplicate'] === 'true');
        
        $customOverrides = [
            'client' => $_POST['custom_client'] ?? '',
            'location' => $_POST['custom_location'] ?? '',
            'area' => $_POST['custom_area'] ?? '',
            'rack_name' => $_POST['custom_rack_name'] ?? '',
            'rack_total_u' => $_POST['custom_rack_total_u'] ?? '',
            'rack_type' => $_POST['custom_rack_type'] ?? '',
            'device_name' => $_POST['custom_device_name'] ?? '',
            'vendor' => $_POST['custom_vendor'] ?? '',
            'serial' => $_POST['custom_serial'] ?? '',
            'device_type' => $_POST['custom_device_type'] ?? '',
            'ur' => $_POST['custom_ur'] ?? '',
            'ip_address' => $_POST['custom_ip_address'] ?? ''
        ];

        $res = PortmappingExcelImporter::executeImport($tmpName, $overwrite, $customOverrides);
        echo json_encode($res);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}

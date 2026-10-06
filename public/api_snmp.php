<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/permissions_helper.php';

header('Content-Type: application/json');

if (!current_user_id() || !has_module_access('snmp')) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'list_mibs':
        $mibs_dir = SNMP_MIBS_PATH;
        if (!is_dir($mibs_dir)) {
            echo json_encode(['success' => false, 'error' => 'Directorios de MIBs no encontrado']);
            exit;
        }

        $files = scandir($mibs_dir);
        $result = [];
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            $path = $mibs_dir . '/' . $file;
            $result[] = [
                'filename' => $file,
                'size' => filesize($path),
                'mtime' => filemtime($path),
                'type' => pathinfo($file, PATHINFO_EXTENSION) ?: 'MIB'
            ];
        }

        // Sort by mtime desc
        usort($result, fn($a, $b) => $b['mtime'] - $a['mtime']);

        echo json_encode(['success' => true, 'data' => $result]);
        break;

    case 'upload_mib':
        if (!isset($_FILES['mib_file'])) {
            echo json_encode(['success' => false, 'error' => 'No se recibió ningún archivo']);
            exit;
        }

        $file = $_FILES['mib_file'];
        if (empty($file['name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errCode = $file['error'] ?? 'desconocido';
            echo json_encode(['success' => false, 'error' => 'Error en la transferencia del archivo (Código PHP: ' . $errCode . ')']);
            exit;
        }

        $mibs_dir = SNMP_MIBS_PATH;

        if (!is_dir($mibs_dir)) {
            @mkdir($mibs_dir, 0777, true);
            @chmod($mibs_dir, 0777);
        }

        if (!is_writable($mibs_dir)) {
            echo json_encode(['success' => false, 'error' => 'El directorio de MIBs (' . $mibs_dir . ') no tiene permisos de escritura']);
            exit;
        }

        $filename = basename($file['name']);
        $target = $mibs_dir . '/' . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $target)) {
            @chmod($target, 0666);
            echo json_encode(['success' => true, 'message' => 'Archivo MIB subido correctamente: ' . $filename]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al mover el archivo al repositorio (' . $mibs_dir . ')']);
        }
        break;

    case 'delete_mib':
        $filename = $_POST['filename'] ?? '';
        if (!$filename) exit(json_encode(['success' => false, 'error' => 'Falta nombre de archivo']));
        
        // Security check: no path traversal
        $filename = basename($filename);
        $target = SNMP_MIBS_PATH . '/' . $filename;

        if (is_file($target)) {
            if (unlink($target)) {
                echo json_encode(['success' => true, 'message' => 'MIB eliminada']);
            } else {
                echo json_encode(['success' => false, 'error' => 'No se pudo eliminar el archivo']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Archivo no encontrado']);
        }
        break;

    case 'analyze_mib':
        $filename = $_GET['filename'] ?? '';
        if (!$filename) exit(json_encode(['success' => false, 'error' => 'Falta nombre de archivo']));
        
        $filename = basename($filename);
        $path = SNMP_MIBS_PATH . '/' . $filename;

        if (!is_file($path)) {
            exit(json_encode(['success' => false, 'error' => 'Archivo no encontrado']));
        }

        $content = file_get_contents($path);
        
        // 1. Detectar Módulo e Imports
        $module_name = 'ALL';
        $imports = [];
        if (preg_match('/^\s*([A-Za-z0-9\-]+)\s+DEFINITIONS/m', $content, $matches)) {
            $module_name = $matches[1];
        }
        if (preg_match('/IMPORTS\s+(.*?);/s', $content, $matches)) {
            $imports_raw = $matches[1];
            // Buscar palabras mayúsculas seguidas de FROM
            preg_match_all('/FROM\s+([A-Za-z0-9\-]+)/', $imports_raw, $imp_matches);
            $imports = array_unique($imp_matches[1]);
        }

        // 2. Obtener Estructura Jerárquica Completa (-Tp incluye tipos y acceso)
        $mibs_dir = SNMP_MIBS_PATH;
        $cmd_tree = "snmptranslate -M +$mibs_dir -m $module_name -Tp 2>&1";
        $output_tree = shell_exec($cmd_tree);
        
        $tree_data = [];
        $lines = explode("\n", $output_tree);
        $oid_stack = []; // Stack para reconstruir OIDs numéricos completos

        foreach ($lines as $line) {
            $line_clean = str_replace('|', ' ', $line);
            if (empty(trim($line_clean)) || strpos($line, 'Cannot find') !== false) continue;
            
            // Regex más flexible para capturar nombres correctamente
            $regex = '/(?:\+--\s*)?(?:(\-[RW\-]+\-)\s+)?(?:([A-Za-z0-9\-_ ]+)\s+)?([A-Za-z0-9\-_]+)\(([0-9]+)\)/';
            
            if (preg_match($regex, trim($line_clean), $m)) {
                $depth = 0;
                if (preg_match('/^(\s*)/', $line_clean, $depth_m)) {
                    $depth = strlen($depth_m[1]);
                }

                $access = $m[1] ?? '';
                $type = trim($m[2] ?? '');
                $name = $m[3];
                $oid_num = $m[4];
                
                // Reconstruir Full OID numérico usando un stack basado en profundidad
                $oid_stack[$depth] = $oid_num;
                foreach (array_keys($oid_stack) as $k) {
                    if ($k > $depth) unset($oid_stack[$k]);
                }
                ksort($oid_stack);
                $current_full_oid = '.' . implode('.', $oid_stack);

                // Iconos estilo Observium
                $icon = 'fas fa-folder text-warning'; 
                $color = 'text-dark';

                if (preg_match('/(String|DisplayString|Octet)/i', $type)) { $icon = 'fas fa-font'; $color = 'text-info'; }
                elseif (preg_match('/(Counter|Gauge|Integer|Unsigned|NetAddress)/i', $type)) { $icon = 'fas fa-hashtag'; $color = 'text-primary'; }
                elseif (strpos($type, 'Enum') !== false) { $icon = 'fas fa-list-ul'; $color = 'text-indigo'; }
                elseif ($type == 'TimeTicks') { $icon = 'fas fa-clock'; $color = 'text-secondary'; }
                elseif ($type == 'ObjID') { $icon = 'fas fa-link'; $color = 'text-muted'; }
                
                // Sobrescribir por nombre (Tablas y Entradas)
                if (preg_match('/Table$/i', $name)) { $icon = 'fas fa-table'; $color = 'text-dark'; }
                if (preg_match('/Entry$/i', $name)) { $icon = 'fas fa-th'; $color = 'text-danger'; }
                if ($depth == 0) { $icon = 'fas fa-project-diagram'; $color = 'text-primary'; }

                $tree_data[] = [
                    'level' => $depth,
                    'name' => $name,
                    'oid' => $oid_num,
                    'full_oid' => $current_full_oid,
                    'type' => $type ?: 'Node',
                    'access' => $access,
                    'icon' => $icon,
                    'color' => $color
                ];
            }
        }

        echo json_encode([
            'success' => true,
            'module' => $module_name,
            'imports' => $imports,
            'tree_data' => $tree_data, // Lista plana pero con niveles para fácil renderizado
            'raw_tree' => $output_tree,
            'raw' => $content,
            'filename' => $filename
        ]);
        break;
    
    case 'get_oid_details':
        $oid = $_GET['oid'] ?? '';
        if (!$oid) exit(json_encode(['success' => false]));
        $mibs_dir = SNMP_MIBS_PATH;
        $cmd = "snmptranslate -M +$mibs_dir -m ALL -Td $oid 2>&1";
        $output = shell_exec($cmd);
        echo json_encode(['success' => true, 'details' => $output]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Acción no válida']);
        break;
}

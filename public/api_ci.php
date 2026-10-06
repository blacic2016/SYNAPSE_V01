<?php
/**
 * API for Graph-Based CI Management
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$pdo = getPDO();
$action = $_REQUEST['action'] ?? '';

// Función recursiva para calcular y propagar cat_unique en formato jerárquico posicional: CAT-XXYYZZ (2 dígitos del 01 al 99 por nivel)
function recalculateCategoryCodes($cat_id, $pdo) {
    // Obtener datos actuales de la categoría
    $stmt = $pdo->prepare("SELECT id, parent_id, cat_unique FROM ci_categories WHERE id = ?");
    $stmt->execute([$cat_id]);
    $cat = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cat) return;
    
    $parent_id = $cat['parent_id'];
    $current_unique = $cat['cat_unique'] ?? '';
    
    // Si tiene padre, primero asegurarse de que el padre tenga un cat_unique válido
    $parent_unique = '';
    if ($parent_id) {
        $stmt_p = $pdo->prepare("SELECT cat_unique FROM ci_categories WHERE id = ?");
        $stmt_p->execute([$parent_id]);
        $parent_unique = $stmt_p->fetchColumn() ?: '';
        
        // Si el padre no tiene cat_unique válido o es TEMP-, recalcular primero el padre
        if (empty($parent_unique) || strpos($parent_unique, 'TEMP-') === 0) {
            recalculateCategoryCodes($parent_id, $pdo);
            $stmt_p->execute([$parent_id]);
            $parent_unique = $stmt_p->fetchColumn() ?: '';
        }
    }
    
    // Verificar si el cat_unique actual es completamente válido para su posición jerárquica actual
    $is_valid = false;
    if (!empty($current_unique) && strpos($current_unique, 'TEMP-') === false) {
        if (!$parent_id) {
            // Para nivel raíz, debe tener formato CAT-XXXX (4 dígitos)
            if (preg_match('/^CAT-\d{4}$/', $current_unique)) {
                $is_valid = true;
            }
        } else {
            // Para categorías hijas, debe empezar exactamente con $parent_unique y tener sufijo de 2 dígitos
            if (!empty($parent_unique) && strpos($current_unique, $parent_unique) === 0 && strlen($current_unique) === strlen($parent_unique) + 2) {
                $suffix = substr($current_unique, strlen($parent_unique));
                if (preg_match('/^\d{2}$/', $suffix)) {
                    $is_valid = true;
                }
            }
        }
    }
    
    // Si NO es válido, calcular el nuevo cat_unique
    if (!$is_valid) {
        if (!$parent_id) {
            // Es raíz. Obtener todos los cat_unique de nivel raíz
            $stmt_seq = $pdo->prepare("SELECT cat_unique FROM ci_categories WHERE parent_id IS NULL AND id != ? AND cat_unique LIKE 'CAT-%'");
            $stmt_seq->execute([$cat_id]);
            $raices = $stmt_seq->fetchAll(PDO::FETCH_COLUMN);
            
            $existing_nums = [];
            foreach ($raices as $r_uniq) {
                if (preg_match('/^CAT-(\d+)$/', $r_uniq, $m)) {
                    $existing_nums[] = (int)$m[1];
                }
            }
            
            $next_val = 1;
            for ($i = 1; $i <= 9999; $i++) {
                if (!in_array($i, $existing_nums)) {
                    $next_val = $i;
                    break;
                }
            }
            
            $new_unique = 'CAT-' . str_pad($next_val, 4, '0', STR_PAD_LEFT);
        } else {
            // Obtener todas las categorías hijas directas de este padre (excepto la actual)
            $stmt_children = $pdo->prepare("SELECT cat_unique FROM ci_categories WHERE parent_id = ? AND id != ?");
            $stmt_children->execute([$parent_id, $cat_id]);
            $children_uniques = $stmt_children->fetchAll(PDO::FETCH_COLUMN);
            
            $existing_nums = [];
            foreach ($children_uniques as $c_uniq) {
                if (!empty($parent_unique) && strpos($c_uniq, $parent_unique) === 0) {
                    $suffix = substr($c_uniq, strlen($parent_unique));
                    if (ctype_digit($suffix)) {
                        $existing_nums[] = (int)$suffix;
                    }
                }
            }
            
            $next_child_seq = 1;
            for ($i = 1; $i <= 99; $i++) {
                if (!in_array($i, $existing_nums)) {
                    $next_child_seq = $i;
                    break;
                }
            }
            
            if ($next_child_seq > 99) {
                throw new Exception("Límite de 99 subcategorías hijas para este padre excedido.");
            }
            $new_unique = $parent_unique . str_pad($next_child_seq, 2, '0', STR_PAD_LEFT);
        }
        
        // Actualizar categoría actual
        $stmt_update = $pdo->prepare("UPDATE ci_categories SET cat_unique = ? WHERE id = ?");
        $stmt_update->execute([$new_unique, $cat_id]);
    }
    
    // Propagar siempre la recalculación a todos sus hijos directos
    $stmt_children_ids = $pdo->prepare("SELECT id FROM ci_categories WHERE parent_id = ?");
    $stmt_children_ids->execute([$cat_id]);
    $children_ids = $stmt_children_ids->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($children_ids as $child_id) {
        recalculateCategoryCodes($child_id, $pdo);
    }
}

try {
    if ($action === 'get_categories') {
        $stmt = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM ci_instances WHERE category_id = c.id) as direct_ci_count, u.username as creator_name FROM ci_categories c LEFT JOIN users u ON c.created_by = u.id ORDER BY c.name ASC");
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt_dep = $pdo->query("SELECT * FROM cmdb_category_dependencies");
        $deps = $stmt_dep->fetchAll(PDO::FETCH_ASSOC);
        
        // Mapear dependencias
        $dep_map = [];
        foreach ($deps as $d) {
            $dep_map[$d['source_category_id']][] = [
                'target_category_id' => $d['target_category_id'],
                'dependency_type' => $d['dependency_type'],
                'line_color' => $d['line_color'] ?? '',
                'line_style' => $d['line_style'] ?? '',
                'relationship_type_id' => $d['relationship_type_id'] ? (int)$d['relationship_type_id'] : null
            ];
        }
        
        foreach ($categories as &$c) {
            $c['dependencies'] = $dep_map[$c['id']] ?? [];
        }
        
        echo json_encode(['success' => true, 'data' => $categories]);
        
    } elseif ($action === 'save_category') {
        if (!has_role('SUPER_ADMIN')) {
            throw new Exception("Permiso denegado");
        }
        
        $id = (int)($_POST['id'] ?? 0);
        $name = $_POST['name'] ?? 'Nueva Categoría';
        $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $schema_json = $_POST['schema_json'] ?? '{}';
        $icon = $_POST['icon'] ?? 'fa-cube';
        $description = $_POST['description'] ?? null;
        $created_by = $_SESSION['user_id'] ?? null;
        $requires_parent_instance = isset($_POST['requires_parent_instance']) && $_POST['requires_parent_instance'] == '1' ? 1 : 0;
        
        // Validar JSON
        json_decode($schema_json);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("El Schema JSON no es válido.");
        }
        
        // Validar jerarquía y profundidad (Max 10 niveles)
        if ($parent_id) {
            if ($id > 0 && $parent_id == $id) {
                throw new Exception("Una categoría no puede ser su propio padre.");
            }
            
            // Comprobar referencia circular
            if ($id > 0) {
                $curr = $parent_id;
                $visited = [];
                while ($curr) {
                    if (isset($visited[$curr])) throw new Exception("Referencia circular en la jerarquía.");
                    $visited[$curr] = true;
                    if ($curr == $id) {
                        throw new Exception("El padre asignado no puede ser un descendiente de esta misma categoría.");
                    }
                    $stmt = $pdo->prepare("SELECT parent_id FROM ci_categories WHERE id = ?");
                    $stmt->execute([$curr]);
                    $curr = $stmt->fetchColumn();
                }
            }
            
            // Calcular nivel del nuevo padre
            $parent_level = 1;
            $curr = $parent_id;
            while ($curr) {
                $stmt = $pdo->prepare("SELECT parent_id FROM ci_categories WHERE id = ?");
                $stmt->execute([$curr]);
                $p = $stmt->fetchColumn();
                if ($p) {
                    $parent_level++;
                    $curr = $p;
                } else {
                    break;
                }
            }
            
            $new_level = $parent_level + 1;
            
            // Calcular profundidad del subárbol de la categoría actual (si existe)
            $getSubtreeDepth = function($cat_id, $pdo) use (&$getSubtreeDepth) {
                $stmt = $pdo->prepare("SELECT id FROM ci_categories WHERE parent_id = ?");
                $stmt->execute([$cat_id]);
                $children = $stmt->fetchAll(PDO::FETCH_COLUMN);
                if (empty($children)) return 1;
                $maxDepth = 0;
                foreach ($children as $child) {
                    $d = $getSubtreeDepth($child, $pdo);
                    if ($d > $maxDepth) $maxDepth = $d;
                }
                return $maxDepth + 1;
            };
            
            $subtree_depth = ($id > 0) ? $getSubtreeDepth($id, $pdo) : 1;
            $total_depth = $new_level + $subtree_depth - 1;
            
            if ($total_depth > 10) {
                throw new Exception("Límite de profundidad máxima excedido. La jerarquía no puede superar los 10 niveles (el nivel actual resultaría en " . $total_depth . " niveles).");
            }
        }
        
        $pdo->beginTransaction();
        try {
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE ci_categories SET name=?, parent_id=?, schema_json=?, icon=?, description=?, requires_parent_instance=?, ultima_actualizacion=NOW() WHERE id=?");
                $stmt->execute([$name, $parent_id, $schema_json, $icon, $description, $requires_parent_instance, $id]);
                $category_id = $id;
            } else {
                $stmt = $pdo->prepare("INSERT INTO ci_categories (name, parent_id, schema_json, icon, description, created_by, requires_parent_instance, cat_unique, ultima_actualizacion) VALUES (?, ?, ?, ?, ?, ?, ?, NULL, NOW())");
                $stmt->execute([$name, $parent_id, $schema_json, $icon, $description, $created_by, $requires_parent_instance]);
                $category_id = $pdo->lastInsertId();
            }
            
            // Recalcular los códigos jerárquicos posicionales
            recalculateCategoryCodes($category_id, $pdo);
            
            // Procesar dependencias inter-categoría
            $dependencies_json = $_POST['dependencies_json'] ?? '[]';
            $dependencies = json_decode($dependencies_json, true);
            if (!is_array($dependencies)) {
                $dependencies = [];
            }
            
            // Eliminar anteriores
            $pdo->prepare("DELETE FROM cmdb_category_dependencies WHERE source_category_id = ?")->execute([$category_id]);
            
            // Insertar nuevas
            if (!empty($dependencies)) {
                $stmt_dep = $pdo->prepare("INSERT INTO cmdb_category_dependencies (source_category_id, target_category_id, dependency_type, line_color, line_style, relationship_type_id) VALUES (?, ?, ?, ?, ?, ?)");
                foreach ($dependencies as $dep) {
                    if (!empty($dep['target_category_id'])) {
                        $stmt_dep->execute([
                            $category_id,
                            (int)$dep['target_category_id'],
                            $dep['dependency_type'] === 'required' ? 'required' : 'optional',
                            !empty($dep['line_color']) ? $dep['line_color'] : null,
                            !empty($dep['line_style']) ? $dep['line_style'] : null,
                            !empty($dep['relationship_type_id']) ? (int)$dep['relationship_type_id'] : null
                        ]);
                    }
                }
            }
            
            $pdo->commit();
            
            // Retornar información completa
            $stmt_info = $pdo->prepare("SELECT cat_unique, ultima_actualizacion FROM ci_categories WHERE id = ?");
            $stmt_info->execute([$category_id]);
            $info = $stmt_info->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'message' => $id > 0 ? 'Categoría actualizada' : 'Categoría creada',
                'id' => $category_id,
                'cat_unique' => $info['cat_unique'] ?? '',
                'ultima_actualizacion' => $info['ultima_actualizacion'] ?? ''
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } elseif ($action === 'delete_category') {
        if (!has_role('SUPER_ADMIN')) {
            throw new Exception("Permiso denegado");
        }
        $id = (int)($_POST['id'] ?? 0);
        
        // 1. Validar si tiene subcategorías hijas directas
        $stmt_has_children = $pdo->prepare("SELECT COUNT(*) FROM ci_categories WHERE parent_id = ?");
        $stmt_has_children->execute([$id]);
        if ($stmt_has_children->fetchColumn() > 0) {
            throw new Exception("No se puede eliminar la categoría porque tiene subcategorías hijas asociadas. Elimine primero las subcategorías.");
        }
        
        // 2. Validar si tiene CIs asociados
        $stmt_check_cis = $pdo->prepare("SELECT COUNT(*) FROM ci_instances WHERE category_id = ?");
        $stmt_check_cis->execute([$id]);
        if ($stmt_check_cis->fetchColumn() > 0) {
            throw new Exception("No se puede eliminar la categoría porque tiene CIs (instancias de activos) asociados a ella.");
        }
        
        $stmt = $pdo->prepare("DELETE FROM ci_categories WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Categoría eliminada']);
        
    } elseif ($action === 'get_attributes') {
        $stmt = $pdo->query("SELECT * FROM ci_attributes ORDER BY name ASC");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        
    } elseif ($action === 'save_attribute') {
        if (!has_role('SUPER_ADMIN')) throw new Exception("Permiso denegado");
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $type = $_POST['type'] ?? 'string';
        $group_name = $_POST['group_name'] ?? 'General';
        $description = $_POST['description'] ?? null;
        $is_required = !empty($_POST['is_required']) ? 1 : 0;
        $multiselect_values = $_POST['multiselect_values'] ?? null;
        $created_by = $_SESSION['user_id'] ?? null;
        
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE ci_attributes SET name=?, type=?, group_name=?, description=?, is_required=?, multiselect_values=? WHERE id=?");
            $stmt->execute([$name, $type, $group_name, $description, $is_required, $multiselect_values, $id]);
            echo json_encode(['success' => true, 'message' => 'Atributo actualizado']);
        } else {
            $stmt = $pdo->prepare("INSERT INTO ci_attributes (name, type, group_name, description, is_required, created_by, multiselect_values) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $type, $group_name, $description, $is_required, $created_by, $multiselect_values]);
            echo json_encode(['success' => true, 'message' => 'Atributo creado']);
        }
        
    } elseif ($action === 'delete_attribute') {
        if (!has_role('SUPER_ADMIN')) throw new Exception("Permiso denegado");
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM ci_attributes WHERE id=?")->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Atributo eliminado']);
        
    } elseif ($action === 'get_relationship_types') {
        $stmt = $pdo->query("SELECT * FROM cmdb_relationship_types ORDER BY name_direct ASC");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        
    } elseif ($action === 'save_relationship_type') {
        if (!has_role('SUPER_ADMIN')) throw new Exception("Permiso denegado");
        $id = (int)($_POST['id'] ?? 0);
        $name_direct = trim($_POST['name_direct'] ?? '');
        $name_inverse = trim($_POST['name_inverse'] ?? '');
        $description = trim($_POST['description'] ?? null);
        
        if (empty($name_direct) || empty($name_inverse)) {
            throw new Exception("El nombre directo y el inverso son obligatorios.");
        }
        
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE cmdb_relationship_types SET name_direct=?, name_inverse=?, description=? WHERE id=?");
            $stmt->execute([$name_direct, $name_inverse, $description, $id]);
            echo json_encode(['success' => true, 'message' => 'Tipo de relación actualizado']);
        } else {
            $stmt = $pdo->prepare("INSERT INTO cmdb_relationship_types (name_direct, name_inverse, description) VALUES (?, ?, ?)");
            $stmt->execute([$name_direct, $name_inverse, $description]);
            echo json_encode(['success' => true, 'message' => 'Tipo de relación creado']);
        }
        
    } elseif ($action === 'delete_relationship_type') {
        if (!has_role('SUPER_ADMIN')) throw new Exception("Permiso denegado");
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM cmdb_relationship_types WHERE id=?")->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Tipo de relación eliminado']);
        
    } elseif ($action === 'get_racks') {
        $stmt = $pdo->query("SELECT r.id, r.name, rm.name as room_name FROM dc_racks r JOIN dc_rooms rm ON r.room_id = rm.id ORDER BY r.name ASC");
        $racks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $racks]);
        
    } elseif ($action === 'get_instances') {
        $category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : null;
        $include_descendants = isset($_GET['include_descendants']) ? (int)$_GET['include_descendants'] : 1;
        if ($category_id) {
            if ($include_descendants) {
                $all_cats = $pdo->query("SELECT id, parent_id FROM ci_categories")->fetchAll(PDO::FETCH_ASSOC);
                $cat_ids = [$category_id];
                $collect_descendants = function($pId) use (&$collect_descendants, $all_cats, &$cat_ids) {
                    foreach ($all_cats as $cat) {
                        if ((int)$cat['parent_id'] === (int)$pId) {
                            $cat_ids[] = (int)$cat['id'];
                            $collect_descendants($cat['id']);
                        }
                    }
                };
                $collect_descendants($category_id);
                $cat_ids = array_values(array_unique($cat_ids));
                $in_placeholders = implode(',', array_fill(0, count($cat_ids), '?'));
                $stmt = $pdo->prepare("
                    SELECT i.*, c.name as category_name, c.icon as category_icon, u.username as creator_name, p.hostname as parent_ci_name
                    FROM ci_instances i 
                    JOIN ci_categories c ON i.category_id = c.id 
                    LEFT JOIN users u ON i.created_by = u.id
                    LEFT JOIN ci_instances p ON i.parent_ci_id = p.id
                    WHERE i.category_id IN ($in_placeholders) 
                    ORDER BY i.hostname ASC
                ");
                $stmt->execute($cat_ids);
            } else {
                $stmt = $pdo->prepare("
                    SELECT i.*, c.name as category_name, c.icon as category_icon, u.username as creator_name, p.hostname as parent_ci_name
                    FROM ci_instances i 
                    JOIN ci_categories c ON i.category_id = c.id 
                    LEFT JOIN users u ON i.created_by = u.id
                    LEFT JOIN ci_instances p ON i.parent_ci_id = p.id
                    WHERE i.category_id = ? 
                    ORDER BY i.hostname ASC
                ");
                $stmt->execute([$category_id]);
            }
        } else {
            $stmt = $pdo->query("
                SELECT i.*, c.name as category_name, c.icon as category_icon, u.username as creator_name, p.hostname as parent_ci_name
                FROM ci_instances i 
                JOIN ci_categories c ON i.category_id = c.id 
                LEFT JOIN users u ON i.created_by = u.id
                LEFT JOIN ci_instances p ON i.parent_ci_id = p.id
                ORDER BY i.hostname ASC
            ");
        }
        $instances = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $instances]);
        
    } elseif ($action === 'get_ci_by_category') {
        $category_id = (int)($_GET['category_id'] ?? 0);
        $parent_ci_id = isset($_GET['parent_ci_id']) && $_GET['parent_ci_id'] !== '' ? (int)$_GET['parent_ci_id'] : null;
        
        if ($parent_ci_id !== null) {
            $stmt = $pdo->prepare("SELECT id, hostname, ip_address FROM ci_instances WHERE category_id = ? AND parent_ci_id = ? ORDER BY hostname ASC");
            $stmt->execute([$category_id, $parent_ci_id]);
        } else {
            $stmt = $pdo->prepare("SELECT id, hostname, ip_address FROM ci_instances WHERE category_id = ? ORDER BY hostname ASC");
            $stmt->execute([$category_id]);
        }
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        
    } elseif ($action === 'save_instance') {
        $id = (int)($_POST['id'] ?? 0);
        $category_id = (int)($_POST['category_id'] ?? 0);
        $ip_address = $_POST['ip_address'] ?? null;
        $source = $_POST['source'] ?? 'manual';
        $zabbix_host_id = !empty($_POST['zabbix_host_id']) ? (int)$_POST['zabbix_host_id'] : null;
        $parent_ci_id = !empty($_POST['parent_ci_id']) ? (int)$_POST['parent_ci_id'] : null;
        $status = $_POST['status'] ?? 'Activo';
        $description = $_POST['description'] ?? null;
        $created_by = $_SESSION['user_id'] ?? null;
        
        // Attributes is all other POST data except the standard fields
        $standard_fields = ['action', 'id', 'category_id', 'parent_ci_id', 'hostname', 'ip_address', 'source', 'zabbix_host_id', 'status', 'description', 'ci_relations'];
        $attributes = [];
        foreach ($_POST as $key => $val) {
            if (!in_array($key, $standard_fields)) {
                $attributes[$key] = $val;
            }
        }
        $attributes_json = json_encode($attributes);
        
        // Extract sigla/siglas
        $sigla = null;
        foreach (['sigla', 'siglas', 'codigo', 'code'] as $k) {
            if (!empty($attributes[$k])) {
                $sigla = trim($attributes[$k]);
                break;
            }
        }
        
        $pdo->beginTransaction();
        
        try {
            // Validar requerimiento de CI Padre
            $stmt_parent_req = $pdo->prepare("SELECT requires_parent_instance, name FROM ci_categories WHERE id = ?");
            $stmt_parent_req->execute([$category_id]);
            $cat_data = $stmt_parent_req->fetch(PDO::FETCH_ASSOC);
            $requires_parent = $cat_data ? (int)$cat_data['requires_parent_instance'] : 0;
            $category_name = $cat_data ? $cat_data['name'] : "CI";

            if ($requires_parent === 1 && empty($parent_ci_id)) {
                echo json_encode(['success' => false, 'message' => "Error: La categoría '{$category_name}' requiere obligatoriamente asociar un CI Padre superior."]);
                $pdo->rollBack();
                exit;
            }

            // Generate ci_unique sequence if not existing
            $ci_unique = null;
            if ($id > 0) {
                $stmt_check = $pdo->prepare("SELECT ci_unique FROM ci_instances WHERE id = ?");
                $stmt_check->execute([$id]);
                $ci_unique = $stmt_check->fetchColumn();
            }
            
            if (empty($ci_unique)) {
                $pdo->exec("UPDATE cmdb_sequences SET ci_last_seq = ci_last_seq + 1 WHERE id = 1");
                $seq_val = $pdo->query("SELECT ci_last_seq FROM cmdb_sequences WHERE id = 1")->fetchColumn();
                $ci_unique = 'SND-' . str_pad($seq_val, 10, '0', STR_PAD_LEFT);
            }
            
            // Prioridad de autogeneración de hostname en backend (regla de negocio 4.B)
            $hostname = '';
            if (!empty($_POST['hostname'])) {
                $hostname = trim($_POST['hostname']);
            }
            
            if (empty($hostname)) {
                // 1. Campo nombre ->
                foreach (['nombre', 'name', 'hostname'] as $k) {
                    if (!empty($attributes[$k])) {
                        $hostname = trim($attributes[$k]);
                        break;
                    }
                }
            }
            
            // 2. Campo siglas ->
            if (empty($hostname)) {
                foreach (['siglas', 'sigla', 'codigo', 'code'] as $k) {
                    if (!empty($attributes[$k])) {
                        $hostname = trim($attributes[$k]);
                        break;
                    }
                }
            }
            
            // 3. Concatenación de marca + modelo ->
            if (empty($hostname)) {
                $marca = !empty($attributes['marca']) ? trim($attributes['marca']) : '';
                $modelo = !empty($attributes['modelo']) ? trim($attributes['modelo']) : '';
                if ($marca || $modelo) {
                    $hostname = trim($marca . ' ' . $modelo);
                }
            }
            
            // 4. Primer campo con texto válido
            if (empty($hostname)) {
                foreach ($attributes as $k => $v) {
                    if (is_string($v) && trim($v) !== '') {
                        $hostname = trim($v);
                        break;
                    }
                }
            }
            
            // 5. Fallback a Categoría + ci_unique
            if (empty($hostname)) {
                $hostname = $category_name . '-' . $ci_unique;
            }
            
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE ci_instances SET category_id=?, parent_ci_id=?, hostname=?, ip_address=?, source=?, zabbix_host_id=?, attributes_json=?, status=?, description=?, sigla=?, ci_unique=? WHERE id=?");
                $stmt->execute([$category_id, $parent_ci_id, $hostname, $ip_address, $source, $zabbix_host_id, $attributes_json, $status, $description, $sigla, $ci_unique, $id]);
                
                // Limpiar relaciones anteriores donde este CI es origen
                $stmtDel = $pdo->prepare("DELETE FROM ci_relationships WHERE source_type='ci_instances' AND source_id=?");
                $stmtDel->execute([$id]);
                
                $instance_id = $id;
                $msg = 'CI actualizado';
            } else {
                $stmt = $pdo->prepare("INSERT INTO ci_instances (category_id, parent_ci_id, hostname, ip_address, source, zabbix_host_id, attributes_json, status, description, created_by, sigla, ci_unique) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$category_id, $parent_ci_id, $hostname, $ip_address, $source, $zabbix_host_id, $attributes_json, $status, $description, $created_by, $sigla, $ci_unique]);
                
                $instance_id = $pdo->lastInsertId();
                $msg = 'CI creado';
            }

            // Vincular imágenes subidas a este CI en la tabla images
            if ($instance_id > 0) {
                foreach ($attributes as $attr_val) {
                    if (is_string($attr_val) && strpos($attr_val, 'storage/uploads/') !== false) {
                        $stmt_img_check = $pdo->prepare("SELECT id FROM images WHERE filepath = ?");
                        $stmt_img_check->execute([$attr_val]);
                        $img_id = $stmt_img_check->fetchColumn();
                        if ($img_id) {
                            $stmt_img_link = $pdo->prepare("UPDATE images SET entity_type = 'ci_instances', entity_id = ? WHERE id = ?");
                            $stmt_img_link->execute([$instance_id, $img_id]);
                        } else {
                            $filename = basename($attr_val);
                            $stmt_img_ins = $pdo->prepare("INSERT INTO images (entity_type, entity_id, filepath, filename, uploaded_at) VALUES ('ci_instances', ?, ?, ?, NOW())");
                            $stmt_img_ins->execute([$instance_id, $attr_val, $filename]);
                        }
                    }
                }
            }

            // Insertar nuevas relaciones
            if (!empty($_POST['ci_relations'])) {
                $relations = json_decode($_POST['ci_relations'], true);
                if (is_array($relations) && count($relations) > 0) {
                    $stmtRel = $pdo->prepare("INSERT INTO ci_relationships (source_type, source_id, target_type, target_id, relation_type, impact) VALUES (?, ?, ?, ?, ?, ?)");
                    foreach ($relations as $rel) {
                        if (!empty($rel['target_id']) && !empty($rel['type'])) {
                            $stmtRel->execute([
                                'ci_instances',
                                $instance_id,
                                'ci_instances', // Plural para consistencia con seed
                                (int)$rel['target_id'],
                                $rel['type'],
                                $rel['impact'] ?? 'Desconocido'
                            ]);
                        }
                    }
                }
            }
            
            // Sincronización bidireccional con dc_rack_devices
            $rack_id = !empty($attributes['rack_id']) ? (int)$attributes['rack_id'] : null;
            $rack_start_u = !empty($attributes['rack_start_u']) ? (int)$attributes['rack_start_u'] : null;
            $rack_height_u = !empty($attributes['rack_height_u']) ? (int)$attributes['rack_height_u'] : 1;
            $rack_orientation = !empty($attributes['rack_orientation']) ? $attributes['rack_orientation'] : 'front';
            $rack_color = !empty($attributes['rack_color']) ? $attributes['rack_color'] : '#2a2a2a';
            $rack_depth = !empty($attributes['rack_depth']) ? $attributes['rack_depth'] : 'full';

            if ($rack_id && $rack_start_u) {
                // Verificar si ya existe en dc_rack_devices
                $stmt_dev = $pdo->prepare("SELECT id FROM dc_rack_devices WHERE cmdb_reference = ?");
                $stmt_dev->execute([$instance_id]);
                $rack_dev_id = $stmt_dev->fetchColumn();

                $details_dev = [
                    'make' => $attributes['marca'] ?? '',
                    'model' => $attributes['modelo'] ?? '',
                    'serial_number' => $attributes['serial_number'] ?? '',
                    'asset_tag' => $attributes['asset_tag'] ?? '',
                    'server_function' => $attributes['server_function'] ?? '',
                    'owner' => $attributes['owner'] ?? '',
                    'ip_address' => $ip_address,
                    'color' => $rack_color,
                    'depth' => $rack_depth
                ];
                $details_json = json_encode($details_dev);

                if ($rack_dev_id) {
                    $stmt_up_dev = $pdo->prepare("UPDATE dc_rack_devices SET rack_id=?, name=?, start_u=?, height_u=?, orientation=?, details_json=? WHERE id=?");
                    $stmt_up_dev->execute([$rack_id, $hostname, $rack_start_u, $rack_height_u, $rack_orientation, $details_json, $rack_dev_id]);
                } else {
                    $stmt_in_dev = $pdo->prepare("INSERT INTO dc_rack_devices (rack_id, name, start_u, height_u, orientation, cmdb_reference, details_json) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt_in_dev->execute([$rack_id, $hostname, $rack_start_u, $rack_height_u, $rack_orientation, $instance_id, $details_json]);
                }
            } else {
                // Si no está asignado a rack, eliminar de dc_rack_devices si existía
                $stmt_del_dev = $pdo->prepare("DELETE FROM dc_rack_devices WHERE cmdb_reference = ?");
                $stmt_del_dev->execute([$instance_id]);
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => $msg, 'id' => $instance_id, 'ci_unique' => $ci_unique, 'hostname' => $hostname]);
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Exception in save_instance: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            echo json_encode(['success' => false, 'message' => 'Error de Base de Datos al guardar CI: ' . $e->getMessage()]);
            exit;
        }
        
    } elseif ($action === 'delete_instance') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->beginTransaction();
        try {
            // Eliminar de dc_rack_devices
            $stmt_del_dev = $pdo->prepare("DELETE FROM dc_rack_devices WHERE cmdb_reference = ?");
            $stmt_del_dev->execute([$id]);

            $stmt = $pdo->prepare("DELETE FROM ci_instances WHERE id = ?");
            $stmt->execute([$id]);
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'CI eliminado']);
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } elseif ($action === 'get_ci_business_view') {
        $ci_id = isset($_GET['ci_id']) ? (int)$_GET['ci_id'] : 0;
        
        if ($ci_id > 0) {
            // Fetch ALL relationships to construct the connected component
            $stmtAllRels = $pdo->query("SELECT r.source_id, r.target_id, r.relation_type, r.impact FROM ci_relationships r JOIN ci_instances s ON r.source_id = s.id JOIN ci_instances t ON r.target_id = t.id WHERE r.source_type='ci_instances' AND r.target_type='ci_instances'");
            $allRels = $stmtAllRels->fetchAll(PDO::FETCH_ASSOC);
            
            // Build undirected adjacency list for graph traversal
            $adj = [];
            foreach ($allRels as $r) {
                $u = (int)$r['source_id'];
                $v = (int)$r['target_id'];
                $adj[$u][] = $v;
                $adj[$v][] = $u;
            }
            
            // BFS traversal starting from the selected CI to find all connected CIs
            $visited = [$ci_id => true];
            $queue = [$ci_id];
            while (!empty($queue)) {
                $curr = array_shift($queue);
                if (isset($adj[$curr])) {
                    foreach ($adj[$curr] as $neighbor) {
                        if (!isset($visited[$neighbor])) {
                            $visited[$neighbor] = true;
                            $queue[] = $neighbor;
                        }
                    }
                }
            }
            
            $ci_ids = array_keys($visited);
            
            // Fetch the details for all connected CIs
            $in_placeholders = implode(',', array_fill(0, count($ci_ids), '?'));
            $stmtCIs = $pdo->prepare("SELECT i.id, i.hostname, i.category_id, i.ip_address, i.status, c.icon, c.name AS category_name FROM ci_instances i JOIN ci_categories c ON i.category_id = c.id WHERE i.id IN ($in_placeholders)");
            $stmtCIs->execute($ci_ids);
            $cis = $stmtCIs->fetchAll(PDO::FETCH_ASSOC);
            
            // Filter relationships to only include those where both ends are in our connected component
            $rels = [];
            foreach ($allRels as $r) {
                $src = (int)$r['source_id'];
                $tgt = (int)$r['target_id'];
                if (isset($visited[$src]) && isset($visited[$tgt])) {
                    $rels[] = $r;
                }
            }
            
            // Get unique category IDs from the connected CIs
            $cat_ids = [];
            foreach ($cis as $c) {
                if (!in_array($c['category_id'], $cat_ids)) $cat_ids[] = $c['category_id'];
            }
            
            // Recursively get parent categories to build the folder/group hierarchy properly
            $categories = [];
            if (!empty($cat_ids)) {
                $stmtCats = $pdo->query("SELECT * FROM ci_categories ORDER BY parent_id ASC, name ASC");
                $allCatsDB = $stmtCats->fetchAll(PDO::FETCH_ASSOC);
                $catMap = [];
                foreach ($allCatsDB as $c) { $catMap[$c['id']] = $c; }
                
                $needed_cats = [];
                foreach ($cat_ids as $cid) {
                    $curr = $cid;
                    $visited_cats = [];
                    while ($curr && isset($catMap[$curr]) && !isset($visited_cats[$curr])) {
                        $visited_cats[$curr] = true;
                        if (!isset($needed_cats[$curr])) {
                            $needed_cats[$curr] = $catMap[$curr];
                        }
                        $curr = $catMap[$curr]['parent_id'];
                    }
                }
                $categories = array_values($needed_cats);
            }
            
            $stmtCatDeps = $pdo->query("SELECT * FROM cmdb_category_dependencies");
            $catDeps = $stmtCatDeps->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Fetch ALL (fallback, joining categories for icons and filtering orphans)
            $stmtCats = $pdo->query("SELECT * FROM ci_categories ORDER BY parent_id ASC, name ASC");
            $categories = $stmtCats->fetchAll(PDO::FETCH_ASSOC);
            $stmtCIs = $pdo->query("SELECT i.id, i.hostname, i.category_id, i.ip_address, i.status, c.icon, c.name AS category_name FROM ci_instances i JOIN ci_categories c ON i.category_id = c.id");
            $cis = $stmtCIs->fetchAll(PDO::FETCH_ASSOC);
            $stmtRels = $pdo->query("SELECT r.source_id, r.target_id, r.relation_type, r.impact FROM ci_relationships r JOIN ci_instances s ON r.source_id = s.id JOIN ci_instances t ON r.target_id = t.id WHERE r.source_type='ci_instances' AND r.target_type='ci_instances'");
            $rels = $stmtRels->fetchAll(PDO::FETCH_ASSOC);
            
            $stmtCatDeps = $pdo->query("SELECT * FROM cmdb_category_dependencies");
            $catDeps = $stmtCatDeps->fetchAll(PDO::FETCH_ASSOC);
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'categories' => $categories,
                'cis' => $cis,
                'relationships' => $rels,
                'category_dependencies' => $catDeps
            ]
        ]);
        
    } elseif ($action === 'get_ci_details') {
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("
            SELECT i.*, c.name as category_name, c.schema_json,
                   p.hostname as parent_ci_name, pc.name as parent_category_name,
                   u.username as creator_name
            FROM ci_instances i 
            JOIN ci_categories c ON i.category_id = c.id 
            LEFT JOIN ci_instances p ON i.parent_ci_id = p.id
            LEFT JOIN ci_categories pc ON p.category_id = pc.id
            LEFT JOIN users u ON i.created_by = u.id
            WHERE i.id = ?
        ");
        $stmt->execute([$id]);
        $ci = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($ci) {
            $catMap = [];
            $stmtCats = $pdo->query("SELECT * FROM ci_categories");
            foreach ($stmtCats->fetchAll(PDO::FETCH_ASSOC) as $c) { $catMap[$c['id']] = $c; }
            
            $lineage = [];
            $curr = $ci['category_id'];
            $visited = [];
            while ($curr && isset($catMap[$curr]) && !isset($visited[$curr])) {
                $visited[$curr] = true;
                array_unshift($lineage, $catMap[$curr]);
                $curr = $catMap[$curr]['parent_id'];
            }
            
            $stmtRel = $pdo->prepare("
                SELECT r.*, c.hostname as target_name, c.ci_unique as target_unique, c.sigla as target_sigla, 
                       c.ip_address as target_ip, c.description as target_desc, 
                       cat.name as target_category_name, cat.schema_json as target_schema_json,
                       c.attributes_json as target_attributes_json
                FROM ci_relationships r 
                JOIN ci_instances c ON r.target_id = c.id 
                JOIN ci_categories cat ON c.category_id = cat.id
                WHERE r.source_type='ci_instances' AND r.source_id=? 
                ORDER BY c.hostname ASC
            ");
            $stmtRel->execute([$id]);
            $relations = $stmtRel->fetchAll(PDO::FETCH_ASSOC);
            
            // Recursively fetch parent CI instances
            $parent_chain = [];
            $curr_parent_id = $ci['parent_ci_id'];
            $visited_parents = [];
            while ($curr_parent_id && !isset($visited_parents[$curr_parent_id])) {
                $visited_parents[$curr_parent_id] = true;
                $stmt_p = $pdo->prepare("
                    SELECT i.*, c.name as category_name, c.schema_json
                    FROM ci_instances i
                    JOIN ci_categories c ON i.category_id = c.id
                    WHERE i.id = ?
                ");
                $stmt_p->execute([$curr_parent_id]);
                $p_inst = $stmt_p->fetch(PDO::FETCH_ASSOC);
                if ($p_inst) {
                    $parent_chain[] = $p_inst;
                    $curr_parent_id = $p_inst['parent_ci_id'];
                } else {
                    break;
                }
            }
            // Fetch images associated with this CI instance
            $stmt_images = $pdo->prepare("SELECT id, filepath FROM images WHERE entity_type = 'ci_instances' AND entity_id = ? ORDER BY uploaded_at DESC");
            $stmt_images->execute([$id]);
            $images = $stmt_images->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true, 
                'data' => [
                    'ci' => $ci, 
                    'lineage' => $lineage, 
                    'relations' => $relations,
                    'parent_chain' => array_reverse($parent_chain),
                    'images' => $images
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'CI no encontrado']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

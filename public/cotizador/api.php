<?php
/**
 * Backend API for Cotizador Module
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/permissions_helper.php';
require_once __DIR__ . '/../../src/db.php';

// Auth Check
if (!current_user_id() || !has_module_access('cotizador')) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$pdo = getPDO();
if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos']);
    exit();
}

$action = $_GET['action'] ?? '';

// Helper to calculate specialist rates
if (!function_exists('calculateSpecialistRates')) {
    function calculateSpecialistRates($tipo, $salario, $utilizable, $costo_hora_manual) {
        $base_type = $tipo;
        global $pdo;
        if ($pdo) {
            $stmt = $pdo->prepare("SELECT base_type FROM cotizador_specialist_levels WHERE code = ?");
            $stmt->execute([$tipo]);
            $res = $stmt->fetchColumn();
            if ($res) {
                $base_type = $res;
            }
        }

        if (in_array($base_type, ['N1', 'N2', 'N3', 'BOC'])) {
            $costo_empresa = $salario * 1.48;
            $horas_laborables = 21 * 8 * $utilizable;
            $costo_hora_lab = ($costo_empresa / $horas_laborables) / 0.95;
            $pvp_hora_lab = $costo_hora_lab / 0.80;
        } elseif (in_array($base_type, ['GP1', 'GP2'])) {
            $costo_empresa = $salario * 1.48;
            $horas_laborables = 20 * 8 * $utilizable;
            $costo_hora_lab = $costo_empresa / $horas_laborables;
            $pvp_hora_lab = $costo_hora_lab / 0.80;
        } else {
            $costo_empresa = 0;
            $horas_laborables = 0;
            $costo_hora_lab = $costo_hora_manual;
            $pvp_hora_lab = $costo_hora_lab / 0.80;
        }

        return [
            'costo_empresa' => $costo_empresa,
            'horas_laborables' => $horas_laborables,
            'costo_hora_lab' => $costo_hora_lab,
            'pvp_hora_lab' => $pvp_hora_lab
        ];
    }
}


switch ($action) {
    // -------------------------------------------------------------
    // SPECIALISTS CRUD
    // -------------------------------------------------------------
    case 'get_specialists':
        try {
            $stmt = $pdo->query("SELECT * FROM cotizador_specialists ORDER BY tipo, nombre");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'save_specialist':
        try {
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $nombre = trim($_POST['nombre'] ?? '');
            $tipo = trim($_POST['tipo'] ?? '');
            $rango_salarial = trim($_POST['rango_salarial'] ?? '');
            $salario = floatval($_POST['salario'] ?? 0);
            $utilizable = floatval($_POST['utilizable'] ?? 0.80);
            $costo_hora_manual = floatval($_POST['costo_hora_manual'] ?? 0);

            if ($nombre === '' || $tipo === '') {
                throw new Exception("El nombre y el tipo son obligatorios.");
            }

            $rates = calculateSpecialistRates($tipo, $salario, $utilizable, $costo_hora_manual);

            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE cotizador_specialists SET 
                    nombre = ?, tipo = ?, rango_salarial = ?, salario = ?, utilizable = ?, costo_hora_manual = ?, 
                    costo_empresa = ?, horas_laborables = ?, costo_hora_lab = ?, pvp_hora_lab = ? 
                    WHERE id = ?");
                $stmt->execute([
                    $nombre, $tipo, $rango_salarial, $salario, $utilizable, $costo_hora_manual,
                    $rates['costo_empresa'], $rates['horas_laborables'], $rates['costo_hora_lab'], $rates['pvp_hora_lab'],
                    $id
                ]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO cotizador_specialists 
                    (nombre, tipo, rango_salarial, salario, utilizable, costo_hora_manual, costo_empresa, horas_laborables, costo_hora_lab, pvp_hora_lab) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $nombre, $tipo, $rango_salarial, $salario, $utilizable, $costo_hora_manual,
                    $rates['costo_empresa'], $rates['horas_laborables'], $rates['costo_hora_lab'], $rates['pvp_hora_lab']
                ]);
            }
            echo json_encode(['success' => true, 'message' => 'Especialista guardado correctamente.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'delete_specialist':
        try {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM cotizador_specialists WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Especialista eliminado.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_levels':
        try {
            $stmt = $pdo->query("SELECT * FROM cotizador_specialist_levels ORDER BY base_type, code");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'save_level':
        try {
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $code = trim($_POST['code'] ?? '');
            $base_type = trim($_POST['base_type'] ?? '');
            $min_salary = floatval($_POST['min_salary'] ?? 0);
            $max_salary = floatval($_POST['max_salary'] ?? 0);

            if ($code === '' || $base_type === '') {
                throw new Exception("El código/nombre del nivel y el tipo base son obligatorios.");
            }
            if ($min_salary > $max_salary) {
                throw new Exception("El salario mínimo no puede ser mayor que el salario máximo.");
            }

            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE cotizador_specialist_levels SET 
                    code = ?, min_salary = ?, max_salary = ?, base_type = ? 
                    WHERE id = ?");
                $stmt->execute([$code, $min_salary, $max_salary, $base_type, $id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO cotizador_specialist_levels 
                    (code, min_salary, max_salary, base_type) 
                    VALUES (?, ?, ?, ?)");
                $stmt->execute([$code, $min_salary, $max_salary, $base_type]);
            }
            echo json_encode(['success' => true, 'message' => 'Nivel guardado correctamente.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'delete_level':
        try {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM cotizador_specialist_levels WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Nivel de especialista eliminado.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // -------------------------------------------------------------
    // EQUIPMENT CATEGORIES CRUD
    // -------------------------------------------------------------
    case 'get_eq_categories':
        try {
            $stmt = $pdo->query("SELECT * FROM cotizador_equipment_categories ORDER BY name");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'save_eq_category':
        try {
            $id   = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            if ($name === '') throw new Exception('El nombre de la categoría es obligatorio.');
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE cotizador_equipment_categories SET name = ? WHERE id = ?");
                $stmt->execute([$name, $id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO cotizador_equipment_categories (name) VALUES (?)");
                $stmt->execute([$name]);
            }
            echo json_encode(['success' => true, 'message' => 'Categoría guardada correctamente.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'delete_eq_category':
        try {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM cotizador_equipment_categories WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Categoría eliminada.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // -------------------------------------------------------------
    // POOL BRANDS (Grupos / Marcas — Nivel 1 de actividades) CRUD
    // -------------------------------------------------------------
    case 'get_pool_brands':
        try {
            $stmt = $pdo->query("SELECT * FROM cotizador_pool_brands ORDER BY name");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'save_pool_brand':
        try {
            $id   = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            if ($name === '') throw new Exception('El nombre del grupo/marca es obligatorio.');
            if ($id > 0) {
                // Also update existing pool services that use old name
                $oldName = $pdo->query("SELECT name FROM cotizador_pool_brands WHERE id = $id")->fetchColumn();
                $stmt = $pdo->prepare("UPDATE cotizador_pool_brands SET name = ? WHERE id = ?");
                $stmt->execute([$name, $id]);
                if ($oldName && $oldName !== $name) {
                    $stmtUp = $pdo->prepare("UPDATE cotizador_pool_servicios SET marca_categoria = ? WHERE marca_categoria = ?");
                    $stmtUp->execute([$name, $oldName]);
                }
            } else {
                $stmt = $pdo->prepare("INSERT INTO cotizador_pool_brands (name) VALUES (?)");
                $stmt->execute([$name]);
            }
            echo json_encode(['success' => true, 'message' => 'Grupo/marca guardado correctamente.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'delete_pool_brand':
        try {
            $id = (int)($_POST['id'] ?? 0);
            // Check if brand is in use
            $name = $pdo->query("SELECT name FROM cotizador_pool_brands WHERE id = $id")->fetchColumn();
            $inUse = $pdo->query("SELECT COUNT(*) FROM cotizador_pool_servicios WHERE marca_categoria = " . $pdo->quote($name))->fetchColumn();
            if ($inUse > 0) {
                throw new Exception("No se puede eliminar: hay $inUse actividad(es) con esta marca en el pool.");
            }
            $stmt = $pdo->prepare("DELETE FROM cotizador_pool_brands WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Grupo/marca eliminado.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // -------------------------------------------------------------
    // POOL OF SERVICES CRUD
    // -------------------------------------------------------------
    case 'get_pool':
        try {
            $brand = $_GET['brand'] ?? '';
            $query = "SELECT * FROM cotizador_pool_servicios WHERE activo = 1";
            $params = [];
            if ($brand !== '') {
                $query .= " AND marca_categoria = ?";
                $params[] = $brand;
            }
            $query .= " ORDER BY marca_categoria, actividad, detalle";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'save_pool_item':
        try {
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $marca = trim($_POST['marca_categoria'] ?? '');
            $actividad = trim($_POST['actividad'] ?? '');
            $detalle = trim($_POST['detalle'] ?? '');
            $n1 = isset($_POST['n1']) ? 1 : 0;
            $n2 = isset($_POST['n2']) ? 1 : 0;
            $n3 = isset($_POST['n3']) ? 1 : 0;
            $e1 = isset($_POST['e1']) ? 1 : 0;
            $e2 = isset($_POST['e2']) ? 1 : 0;
            $tipo = trim($_POST['tipo'] ?? '');
            $horas_lab = floatval($_POST['horas_laborables'] ?? 0);
            $horas_50 = floatval($_POST['horas_no_laborables_50'] ?? 0);
            $horas_100 = floatval($_POST['horas_no_laborables_100'] ?? 0);
            $obs = trim($_POST['observaciones'] ?? '');

            if ($marca === '' || $detalle === '') {
                throw new Exception("Marca/Categoría y Detalle de Actividad son obligatorios.");
            }

            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE cotizador_pool_servicios SET 
                    marca_categoria = ?, actividad = ?, detalle = ?, n1 = ?, n2 = ?, n3 = ?, e1 = ?, e2 = ?, 
                    tipo = ?, horas_laborables = ?, horas_no_laborables_50 = ?, horas_no_laborables_100 = ?, observaciones = ? 
                    WHERE id = ?");
                $stmt->execute([
                    $marca, $actividad, $detalle, $n1, $n2, $n3, $e1, $e2,
                    $tipo, $horas_lab, $horas_50, $horas_100, $obs, $id
                ]);
            } else {
                // Generate unique code
                $brandPrefix = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $marca));
                if ($brandPrefix === '') {
                    $brandPrefix = 'serv';
                }
                
                // Get next number
                $stmt_max = $pdo->prepare("SELECT codigo_unico FROM cotizador_pool_servicios WHERE codigo_unico LIKE ? ORDER BY codigo_unico DESC LIMIT 1");
                $stmt_max->execute([$brandPrefix . '_%']);
                $maxRow = $stmt_max->fetch(PDO::FETCH_ASSOC);
                
                $nextNum = 1;
                if ($maxRow) {
                    $lastCode = $maxRow['codigo_unico'];
                    $parts = explode('_', $lastCode);
                    $lastNum = (int)end($parts);
                    $nextNum = $lastNum + 1;
                }
                $codigo_unico = $brandPrefix . '_' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

                $stmt = $pdo->prepare("INSERT INTO cotizador_pool_servicios 
                    (codigo_unico, marca_categoria, actividad, detalle, n1, n2, n3, e1, e2, tipo, horas_laborables, horas_no_laborables_50, horas_no_laborables_100, observaciones, activo) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([
                    $codigo_unico, $marca, $actividad, $detalle, $n1, $n2, $n3, $e1, $e2,
                    $tipo, $horas_lab, $horas_50, $horas_100, $obs
                ]);
            }
            echo json_encode(['success' => true, 'message' => 'Servicio guardado correctamente en el pool.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'delete_pool_item':
        try {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("UPDATE cotizador_pool_servicios SET activo = 0 WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Servicio desactivado correctamente.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // -------------------------------------------------------------
    // QUOTES (COTIZACIONES) CRUD
    // -------------------------------------------------------------
    case 'get_quotes':
        try {
            $client = $_GET['client'] ?? '';
            $status = $_GET['status'] ?? '';
            $month = $_GET['month'] ?? ''; // Format 'YYYY-MM'

            $query = "SELECT c.*, 
                      (SELECT COUNT(*) FROM cotizador_cotizaciones v WHERE v.parent_id = c.id OR v.id = c.id) as versions_count 
                      FROM cotizador_cotizaciones c 
                      WHERE c.parent_id IS NULL";
            $params = [];

            if ($client !== '') {
                $query .= " AND c.cliente LIKE ?";
                $params[] = "%$client%";
            }
            if ($status !== '') {
                $query .= " AND c.estado = ?";
                $params[] = $status;
            }
            if ($month !== '') {
                $query .= " AND DATE_FORMAT(c.fecha, '%Y-%m') = ?";
                $params[] = $month;
            }
            $query .= " ORDER BY c.fecha DESC, c.id DESC";

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $quotes = $stmt->fetchAll();

            // Load child versions if requested
            echo json_encode(['success' => true, 'data' => $quotes]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_quote_versions':
        try {
            $parentId = (int)($_GET['parent_id'] ?? 0);
            if ($parentId <= 0) {
                throw new Exception("ID de cotización padre inválido.");
            }
            $stmt = $pdo->prepare("SELECT * FROM cotizador_cotizaciones WHERE id = ? OR parent_id = ? ORDER BY version DESC");
            $stmt->execute([$parentId, $parentId]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_quote_detail':
        try {
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception("ID de cotización inválido.");
            }

            // Get Master
            $stmt = $pdo->prepare("SELECT * FROM cotizador_cotizaciones WHERE id = ?");
            $stmt->execute([$id]);
            $quote = $stmt->fetch();
            if (!$quote) {
                throw new Exception("Cotización no encontrada.");
            }

            // Get Details
            $stmt_det = $pdo->prepare("SELECT * FROM cotizador_cotizaciones_detalles WHERE cotizacion_id = ?");
            $stmt_det->execute([$id]);
            $details = $stmt_det->fetchAll();

            echo json_encode([
                'success' => true,
                'quote' => $quote,
                'details' => $details
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'save_quote':
        try {
            // Read JSON input
            $raw_data = file_get_contents('php://input');
            $data = json_decode($raw_data, true);
            if (!$data) {
                throw new Exception("Datos inválidos.");
            }

            $id = isset($data['id']) ? (int)$data['id'] : 0;
            $save_mode = $data['save_mode'] ?? 'update'; // 'update' (overwrite) or 'new_version' (increment version)
            $cliente = trim($data['cliente'] ?? '');
            $contrato = trim($data['contrato'] ?? '');
            $fecha = trim($data['fecha'] ?? date('Y-m-d'));
            $margen_global = floatval($data['margen_global'] ?? 0.20);
            $risk_percentage = floatval($data['risk_percentage'] ?? 0.10);
            $total_costo = floatval($data['total_costo'] ?? 0);
            $total_precio = floatval($data['total_precio'] ?? 0);
            $adicionales = $data['adicionales'] ?? []; // Array containing travel, PSS, BOC, etc.
            $observaciones = trim($data['observaciones'] ?? '');
            $details_items = $data['details'] ?? []; // Array of tasks

            if ($cliente === '') {
                throw new Exception("El cliente es obligatorio.");
            }

            $pdo->beginTransaction();

            $parentId = null;
            $version = 1;
            $estado = 'Borrador';

            if ($id > 0) {
                // We are editing an existing quote
                $stmt_orig = $pdo->prepare("SELECT parent_id, version, estado, aprobado_por, aprobado_fecha FROM cotizador_cotizaciones WHERE id = ?");
                $stmt_orig->execute([$id]);
                $orig = $stmt_orig->fetch();
                if (!$orig) {
                    throw new Exception("Cotización original no encontrada.");
                }

                $estado = $orig['estado'];

                if ($save_mode === 'new_version') {
                    // Create new version
                    $parentId = $orig['parent_id'] ? $orig['parent_id'] : $id;
                    
                    // Find highest version number
                    $stmt_ver = $pdo->prepare("SELECT MAX(version) FROM cotizador_cotizaciones WHERE id = ? OR parent_id = ?");
                    $stmt_ver->execute([$parentId, $parentId]);
                    $maxVer = (int)$stmt_ver->fetchColumn();
                    $version = $maxVer + 1;

                    // Insert new quote record
                    $stmt = $pdo->prepare("INSERT INTO cotizador_cotizaciones 
                        (parent_id, version, cliente, contrato, fecha, estado, margen_global, risk_percentage, total_costo, total_precio, adicionales_json, observaciones) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $parentId, $version, $cliente, $contrato, $fecha, 'Borrador', $margen_global, $risk_percentage,
                        $total_costo, $total_precio, json_encode($adicionales), $observaciones
                    ]);
                    $newId = $pdo->lastInsertId();

                    // Insert details
                    $stmt_ins_det = $pdo->prepare("INSERT INTO cotizador_cotizaciones_detalles 
                        (cotizacion_id, seccion, codigo_unico, marca_categoria, actividad, detalle, especialista_nivel, horas_laborables, horas_no_laborables_50, horas_no_laborables_100, costo_hora, pvp_hora, costo_total, pvp_total, multiplier_type, observaciones) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    foreach ($details_items as $item) {
                        $stmt_ins_det->execute([
                            $newId, $item['seccion'], !empty($item['codigo_unico']) ? $item['codigo_unico'] : null, $item['marca_categoria'], $item['actividad'], $item['detalle'],
                            $item['especialista_nivel'], $item['horas_laborables'], $item['horas_no_laborables_50'], $item['horas_no_laborables_100'],
                            $item['costo_hora'], $item['pvp_hora'], $item['costo_total'], $item['pvp_total'], !empty($item['multiplier_type']) ? $item['multiplier_type'] : 'Ninguno', $item['observaciones'] ?? ''
                        ]);
                    }

                    // Copy attachments if any from old ID to new ID
                    $stmt_att = $pdo->prepare("SELECT filename, filepath, filesize FROM cotizador_cotizaciones_adjuntos WHERE cotizacion_id = ?");
                    $stmt_att->execute([$id]);
                    $attachments = $stmt_att->fetchAll();
                    if (!empty($attachments)) {
                        $stmt_ins_att = $pdo->prepare("INSERT INTO cotizador_cotizaciones_adjuntos (cotizacion_id, filename, filepath, filesize) VALUES (?, ?, ?, ?)");
                        $base_dir = dirname(dirname(__DIR__));
                        foreach ($attachments as $att) {
                            $old_path = $base_dir . '/' . $att['filepath'];
                            $ext = pathinfo($att['filename'], PATHINFO_EXTENSION);
                            $new_name = "ADJ_" . uniqid() . "." . $ext;
                            $new_relative = "storage/uploads/" . $new_name;
                            $new_path = $base_dir . '/' . $new_relative;
                            if (file_exists($old_path)) {
                                copy($old_path, $new_path);
                                $stmt_ins_att->execute([$newId, $att['filename'], $new_relative, $att['filesize']]);
                            } else {
                                $stmt_ins_att->execute([$newId, $att['filename'], $att['filepath'], $att['filesize']]);
                            }
                        }
                    }

                    $id = $newId;
                } else {
                    // Just update current version
                    $stmt = $pdo->prepare("UPDATE cotizador_cotizaciones SET 
                        cliente = ?, contrato = ?, fecha = ?, margen_global = ?, risk_percentage = ?, 
                        total_costo = ?, total_precio = ?, adicionales_json = ?, observaciones = ? 
                        WHERE id = ?");
                    $stmt->execute([
                        $cliente, $contrato, $fecha, $margen_global, $risk_percentage,
                        $total_costo, $total_precio, json_encode($adicionales), $observaciones, $id
                    ]);

                    // Remove old details and insert new ones
                    $pdo->prepare("DELETE FROM cotizador_cotizaciones_detalles WHERE cotizacion_id = ?")->execute([$id]);

                    $stmt_ins_det = $pdo->prepare("INSERT INTO cotizador_cotizaciones_detalles 
                        (cotizacion_id, seccion, codigo_unico, marca_categoria, actividad, detalle, especialista_nivel, horas_laborables, horas_no_laborables_50, horas_no_laborables_100, costo_hora, pvp_hora, costo_total, pvp_total, multiplier_type, observaciones) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    foreach ($details_items as $item) {
                        $stmt_ins_det->execute([
                            $id, $item['seccion'], !empty($item['codigo_unico']) ? $item['codigo_unico'] : null, $item['marca_categoria'], $item['actividad'], $item['detalle'],
                            $item['especialista_nivel'], $item['horas_laborables'], $item['horas_no_laborables_50'], $item['horas_no_laborables_100'],
                            $item['costo_hora'], $item['pvp_hora'], $item['costo_total'], $item['pvp_total'], !empty($item['multiplier_type']) ? $item['multiplier_type'] : 'Ninguno', $item['observaciones'] ?? ''
                        ]);
                    }
                }
            } else {
                // New Quote
                $stmt = $pdo->prepare("INSERT INTO cotizador_cotizaciones 
                    (parent_id, version, cliente, contrato, fecha, estado, margen_global, risk_percentage, total_costo, total_precio, adicionales_json, observaciones) 
                    VALUES (NULL, 1, ?, ?, ?, 'Borrador', ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $cliente, $contrato, $fecha, $margen_global, $risk_percentage,
                    $total_costo, $total_precio, json_encode($adicionales), $observaciones
                ]);
                $id = $pdo->lastInsertId();

                // Insert details
                $stmt_ins_det = $pdo->prepare("INSERT INTO cotizador_cotizaciones_detalles 
                    (cotizacion_id, seccion, codigo_unico, marca_categoria, actividad, detalle, especialista_nivel, horas_laborables, horas_no_laborables_50, horas_no_laborables_100, costo_hora, pvp_hora, costo_total, pvp_total, multiplier_type, observaciones) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                foreach ($details_items as $item) {
                    $stmt_ins_det->execute([
                        $id, $item['seccion'], !empty($item['codigo_unico']) ? $item['codigo_unico'] : null, $item['marca_categoria'], $item['actividad'], $item['detalle'],
                        $item['especialista_nivel'], $item['horas_laborables'], $item['horas_no_laborables_50'], $item['horas_no_laborables_100'],
                        $item['costo_hora'], $item['pvp_hora'], $item['costo_total'], $item['pvp_total'], !empty($item['multiplier_type']) ? $item['multiplier_type'] : 'Ninguno', $item['observaciones'] ?? ''
                    ]);
                }
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'id' => $id, 'message' => 'Cotización guardada exitosamente.']);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'delete_quote':
        try {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception("ID inválido.");
            }

            // Get quote to see if it is a parent
            $stmt = $pdo->prepare("SELECT parent_id FROM cotizador_cotizaciones WHERE id = ?");
            $stmt->execute([$id]);
            $q = $stmt->fetch();
            if (!$q) {
                throw new Exception("Cotización no encontrada.");
            }

            $pdo->beginTransaction();
            if ($q['parent_id'] === null) {
                // If it is parent, delete all versions!
                $stmt_del = $pdo->prepare("DELETE FROM cotizador_cotizaciones WHERE id = ? OR parent_id = ?");
                $stmt_del->execute([$id, $id]);
            } else {
                // Just delete this version
                $stmt_del = $pdo->prepare("DELETE FROM cotizador_cotizaciones WHERE id = ?");
                $stmt_del->execute([$id]);
            }
            $pdo->commit();

            echo json_encode(['success' => true, 'message' => 'Cotización eliminada correctamente.']);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'approve_quote':
        try {
            $id = (int)($_POST['id'] ?? 0);
            $aprobador = trim($_POST['aprobador'] ?? 'Jefe de Preventa');
            if ($id <= 0) {
                throw new Exception("ID inválido.");
            }

            $stmt = $pdo->prepare("UPDATE cotizador_cotizaciones SET 
                estado = 'Enviada', aprobado_por = ?, aprobado_fecha = NOW() 
                WHERE id = ?");
            $stmt->execute([$aprobador, $id]);

            echo json_encode(['success' => true, 'message' => 'Cotización aprobada y marcada como Enviada.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'convert_to_project':
        try {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception("ID de cotización inválido.");
            }

            // Fetch quote
            $stmt = $pdo->prepare("SELECT * FROM cotizador_cotizaciones WHERE id = ?");
            $stmt->execute([$id]);
            $quote = $stmt->fetch();
            if (!$quote) {
                throw new Exception("Cotización no encontrada.");
            }

            if ($quote['estado'] !== 'Enviada') {
                throw new Exception("La cotización debe estar Aprobada (Enviada) para pasarse a proyecto.");
            }

            $pdo->beginTransaction();

            // Try to find client CI or auto-create one
            $client_ci_id = null;
            $stmt_ci = $pdo->prepare("SELECT id FROM ci_instances WHERE hostname LIKE ? OR ci_unique LIKE ? LIMIT 1");
            $stmt_ci->execute(['%' . $quote['cliente'] . '%', '%' . $quote['cliente'] . '%']);
            $client_ci_id = $stmt_ci->fetchColumn();
            if (!$client_ci_id && !empty($quote['cliente'])) {
                $stmtCat = $pdo->query("SELECT id FROM ci_categories WHERE name LIKE '%Cliente%' ORDER BY id ASC LIMIT 1");
                $cat_id = $stmtCat->fetchColumn() ?: 48;
                $ci_code = 'SND-' . sprintf("%010d", rand(1000000, 999999999));
                $stmt_ins_c = $pdo->prepare("INSERT INTO ci_instances (category_id, hostname, status, source, ci_unique) VALUES (?, ?, 'Activo', 'manual', ?)");
                $stmt_ins_c->execute([$cat_id, $quote['cliente'], $ci_code]);
                $client_ci_id = (int)$pdo->lastInsertId();
            }

            // Generate project code (proj-XX)
            $stmt_code = $pdo->query("SELECT code FROM projects ORDER BY id DESC LIMIT 1");
            $last_code = $stmt_code->fetchColumn();
            $next_num = 1;
            if ($last_code && preg_match('/proj-(\d+)/i', $last_code, $m)) {
                $next_num = intval($m[1]) + 1;
            }
            $proj_code = 'proj-' . sprintf("%02d", $next_num);

            $project_name = "Proyecto: " . $quote['cliente'] . ($quote['contrato'] ? " - " . $quote['contrato'] : "");
            
            // Insert project
            $stmt_proj = $pdo->prepare("INSERT INTO projects 
                (code, name, client_ci_id, amount, start_date, end_date, execution_date, assigned_personnel, work_type, working_days) 
                VALUES (?, ?, ?, ?, ?, ?, NULL, ?, 'horas normales', 'Lunes,Martes,Miércoles,Jueves,Viernes')");
            
            $stmt_proj->execute([
                $proj_code,
                $project_name,
                $client_ci_id,
                $quote['total_precio'],
                $quote['fecha'], // start date
                date('Y-m-d', strtotime($quote['fecha'] . ' + 3 months')), // end date (default 3 months)
                $quote['aprobado_por'] ?? ''
            ]);
            $project_id = $pdo->lastInsertId();

            // Fetch quote details
            $stmt_det = $pdo->prepare("SELECT * FROM cotizador_cotizaciones_detalles WHERE cotizacion_id = ? ORDER BY seccion ASC, id ASC");
            $stmt_det->execute([$id]);
            $details = $stmt_det->fetchAll();

            // Group details by Section (Milestones)
            $sections = [];
            foreach ($details as $det) {
                $sec = $det['seccion'];
                if (!isset($sections[$sec])) {
                    $sections[$sec] = [];
                }
                $sections[$sec][] = $det;
            }

            // Map sections to readable milestone names
            $milestone_names = [
                'Implementacion' => 'Implementación',
                'MantPrev' => 'Mantenimiento Preventivo',
                'MantCorr' => 'Mantenimiento Correctivo',
                'Bolsa' => 'Bolsa de Horas'
            ];

            if (!function_exists('recalculateMilestoneProgressLocal')) {
                function recalculateMilestoneProgressLocal($pdo, $milestone_id) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(progress_percentage) as suma FROM project_tasks WHERE milestone_id = ?");
                    $stmt->execute([$milestone_id]);
                    $res = $stmt->fetch(PDO::FETCH_ASSOC);
                    $progress = 0;
                    if ($res && $res['total'] > 0) {
                        $progress = round($res['suma'] / $res['total'], 2);
                    }
                    $pdo->prepare("UPDATE project_milestones SET progress_percentage = ? WHERE id = ?")->execute([$progress, $milestone_id]);
                }
            }

            // Insert milestones and tasks
            $hit_index = 1;
            foreach ($sections as $sec_code => $items) {
                $mil_name = $milestone_names[$sec_code] ?? $sec_code;
                
                // hit-XXYY code
                $hit_code = 'hit-' . sprintf("%02d", $next_num) . sprintf("%02d", $hit_index);
                
                $stmt_mil = $pdo->prepare("INSERT INTO project_milestones 
                    (project_id, code, name, estimated_start_date, estimated_end_date, real_start_date, real_end_date, priority, importance, average_execution_time, status, progress_percentage) 
                    VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), NULL, NULL, 'Media', 'Media', 0, 'New', 0)");
                $stmt_mil->execute([
                    $project_id,
                    $hit_code,
                    $mil_name
                ]);
                $milestone_id = $pdo->lastInsertId();

                // Insert tasks (req-XXYYZZZ)
                $task_index = 1;
                foreach ($items as $item) {
                    $task_code = 'req-' . sprintf("%02d", $next_num) . sprintf("%02d", $hit_index) . sprintf("%03d", $task_index);
                    $task_title = $item['marca_categoria'] . ' - ' . $item['actividad'] . ' (' . $item['detalle'] . ')';
                    $task_hours = (float)($item['horas_laborables'] + $item['horas_no_laborables_50'] + $item['horas_no_laborables_100']);
                    
                    $stmt_task = $pdo->prepare("INSERT INTO project_tasks 
                        (milestone_id, code, title, estimated_start_date, estimated_end_date, real_start_date, real_end_date, assigned_person, priority, importance, progress_percentage, average_execution_time, status) 
                        VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), NULL, NULL, ?, 'Media', 'Media', 0, ?, 'New')");
                    $stmt_task->execute([
                        $milestone_id,
                        $task_code,
                        $task_title,
                        $item['especialista_nivel'],
                        $task_hours
                    ]);
                    $task_index++;
                }

                // Recalculate progress for this milestone
                recalculateMilestoneProgressLocal($pdo, $milestone_id);
                $hit_index++;
            }

            $pdo->commit();

            echo json_encode(['success' => true, 'project_id' => $project_id, 'message' => 'Cotización convertida en Proyecto exitosamente. Código: ' . $proj_code]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'compare_quotes':
        try {
            $id1 = (int)($_GET['id1'] ?? 0);
            $id2 = (int)($_GET['id2'] ?? 0);

            if ($id1 <= 0 || $id2 <= 0) {
                throw new Exception("Debe especificar dos IDs válidos para comparar.");
            }

            // Get both quotes
            $stmt = $pdo->prepare("SELECT * FROM cotizador_cotizaciones WHERE id = ?");
            $stmt->execute([$id1]);
            $q1 = $stmt->fetch();

            $stmt->execute([$id2]);
            $q2 = $stmt->fetch();

            if (!$q1 || !$q2) {
                throw new Exception("Una o ambas cotizaciones no existen.");
            }

            // Get sum of hours by specialist level
            $stmt_h = $pdo->prepare("SELECT seccion, especialista_nivel, SUM(horas_laborables) as total_lab, 
                SUM(horas_no_laborables_50) as total_50, SUM(horas_no_laborables_100) as total_100,
                SUM(costo_total) as cost, SUM(pvp_total) as pvp 
                FROM cotizador_cotizaciones_detalles 
                WHERE cotizacion_id = ? 
                GROUP BY seccion, especialista_nivel");
            
            $stmt_h->execute([$id1]);
            $h1 = $stmt_h->fetchAll();

            $stmt_h->execute([$id2]);
            $h2 = $stmt_h->fetchAll();

            echo json_encode([
                'success' => true,
                'q1' => [
                    'meta' => $q1,
                    'adicionales' => json_decode($q1['adicionales_json'], true),
                    'summary' => $h1
                ],
                'q2' => [
                    'meta' => $q2,
                    'adicionales' => json_decode($q2['adicionales_json'], true),
                    'summary' => $h2
                ]
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'import_pool_excel':
        try {
            if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Error al subir el archivo o archivo no seleccionado.");
            }

            $fileTmpPath = $_FILES['excel_file']['tmp_name'];
            $fileName = $_FILES['excel_file']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if ($fileExtension !== 'xlsx' && $fileExtension !== 'xls' && $fileExtension !== 'xlsm') {
                throw new Exception("El archivo debe ser un archivo de Excel (.xlsx, .xls o .xlsm).");
            }

            require_once __DIR__ . '/../../vendor/autoload.php';

            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($fileTmpPath);
            $spreadsheet = $reader->load($fileTmpPath);
            
            $sheetNames = $spreadsheet->getSheetNames();
            $skippedSheets = ['Índice', 'Extras'];

            // Drop and recreate the table to avoid primary key/unique code conflicts
            $pdo->exec("DROP TABLE IF EXISTS `cotizador_pool_servicios`");
            $pdo->exec("CREATE TABLE `cotizador_pool_servicios` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `codigo_unico` VARCHAR(50) NOT NULL UNIQUE,
              `marca_categoria` VARCHAR(100) NOT NULL,
              `actividad` VARCHAR(255) DEFAULT '',
              `detalle` TEXT,
              `n1` TINYINT(1) DEFAULT 0,
              `n2` TINYINT(1) DEFAULT 0,
              `n3` TINYINT(1) DEFAULT 0,
              `e1` TINYINT(1) DEFAULT 0,
              `e2` TINYINT(1) DEFAULT 0,
              `tipo` VARCHAR(50) DEFAULT '',
              `horas_laborables` DECIMAL(10,2) DEFAULT 0.00,
              `horas_no_laborables_50` DECIMAL(10,2) DEFAULT 0.00,
              `horas_no_laborables_100` DECIMAL(10,2) DEFAULT 0.00,
              `observaciones` TEXT,
              `activo` TINYINT(1) DEFAULT 1,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $stmt_ins_pool = $pdo->prepare("INSERT INTO `cotizador_pool_servicios` 
                (codigo_unico, marca_categoria, actividad, detalle, n1, n2, n3, e1, e2, tipo, horas_laborables, horas_no_laborables_50, horas_no_laborables_100, observaciones, activo) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
                
            $insertedCount = 0;
            $brandCounters = [];
            
            foreach ($sheetNames as $sName) {
                if (in_array($sName, $skippedSheets)) continue;
                
                $sheet = $spreadsheet->getSheetByName($sName);
                $highestRow = $sheet->getHighestRow();
                
                // Find header row and map columns dynamically
                $headerRow = null;
                $colMap = [
                    'actividad' => null,
                    'detalle' => null,
                    'n1' => null,
                    'n2' => null,
                    'n3' => null,
                    'e1' => null,
                    'e2' => null,
                    'tipo' => null,
                    'h_lab' => null,
                    'h_50' => null,
                    'h_100' => null,
                    'obs' => null
                ];
                
                // Scan first 10 rows for a header
                for ($r = 1; $r <= 10; $r++) {
                    $hasDetalle = false;
                    $rowVals = [];
                    for ($c = 1; $c <= 20; $c++) {
                        $val = trim((string)$sheet->getCellByColumnAndRow($c, $r)->getValue());
                        $rowVals[$c] = $val;
                        if (stripos($val, 'Detalle') !== false) {
                            $hasDetalle = true;
                        }
                    }
                    if ($hasDetalle) {
                        $headerRow = $r;
                        foreach ($rowVals as $c => $val) {
                            $valLower = strtolower($val);
                            if (stripos($valLower, 'actividad') !== false) {
                                $colMap['actividad'] = $c;
                            } elseif (stripos($valLower, 'detalle') !== false) {
                                $colMap['detalle'] = $c;
                            } elseif ($valLower === 'n1') {
                                $colMap['n1'] = $c;
                            } elseif ($valLower === 'n2') {
                                $colMap['n2'] = $c;
                            } elseif ($valLower === 'n3') {
                                $colMap['n3'] = $c;
                            } elseif ($valLower === 'e1') {
                                $colMap['e1'] = $c;
                            } elseif ($valLower === 'e2') {
                                $colMap['e2'] = $c;
                            } elseif ($valLower === 'tipo') {
                                $colMap['tipo'] = $c;
                            } elseif (stripos($valLower, 'horas laborables') !== false || stripos($valLower, 'h. lab') !== false || stripos($valLower, 'horas lab') !== false) {
                                $colMap['h_lab'] = $c;
                            } elseif (stripos($valLower, '50%') !== false || stripos($valLower, 'no laborables 50') !== false) {
                                $colMap['h_50'] = $c;
                            } elseif (stripos($valLower, '100%') !== false || stripos($valLower, 'no laborables 100') !== false) {
                                $colMap['h_100'] = $c;
                            } elseif (stripos($valLower, 'observaciones') !== false) {
                                $colMap['obs'] = $c;
                            }
                        }
                        
                        // Handle case where "Horas no Laborables" is present but without explicit 50%/100% in header
                        if (!$colMap['h_50'] || !$colMap['h_100']) {
                            foreach ($rowVals as $c => $val) {
                                $valLower = strtolower($val);
                                if (stripos($valLower, 'no laborables') !== false || stripos($valLower, 'no lab') !== false) {
                                    if (!$colMap['h_50']) {
                                        $colMap['h_50'] = $c;
                                    } elseif (!$colMap['h_100'] && $c !== $colMap['h_50']) {
                                        $colMap['h_100'] = $c;
                                    }
                                }
                            }
                        }
                        break;
                    }
                }
                
                // If no header found, it's not a service sheet. Skip it.
                if ($headerRow === null) {
                    continue;
                }
                
                $currentGroup = '';
                
                // Normalize brand prefix for code
                $brandPrefix = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $sName));
                if ($brandPrefix === '') {
                    $brandPrefix = 'serv';
                }
                if (!isset($brandCounters[$brandPrefix])) {
                    $brandCounters[$brandPrefix] = 0;
                }
                
                for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
                    $n1 = ($colMap['n1'] && trim(strtolower((string)$sheet->getCellByColumnAndRow($colMap['n1'], $row)->getValue())) === 'x') ? 1 : 0;
                    $n2 = ($colMap['n2'] && trim(strtolower((string)$sheet->getCellByColumnAndRow($colMap['n2'], $row)->getValue())) === 'x') ? 1 : 0;
                    $n3 = ($colMap['n3'] && trim(strtolower((string)$sheet->getCellByColumnAndRow($colMap['n3'], $row)->getValue())) === 'x') ? 1 : 0;
                    $e1 = ($colMap['e1'] && trim(strtolower((string)$sheet->getCellByColumnAndRow($colMap['e1'], $row)->getValue())) === 'x') ? 1 : 0;
                    $e2 = ($colMap['e2'] && trim(strtolower((string)$sheet->getCellByColumnAndRow($colMap['e2'], $row)->getValue())) === 'x') ? 1 : 0;
                    
                    $tipo = $colMap['tipo'] ? trim((string)$sheet->getCellByColumnAndRow($colMap['tipo'], $row)->getValue()) : '';
                    
                    $h_lab = 0.0;
                    $h_50 = 0.0;
                    $h_100 = 0.0;
                    
                    if ($colMap['h_lab']) {
                        try {
                            $h_lab = floatval($sheet->getCellByColumnAndRow($colMap['h_lab'], $row)->getCalculatedValue());
                        } catch (Exception $ex) {
                            $h_lab = floatval($sheet->getCellByColumnAndRow($colMap['h_lab'], $row)->getValue());
                        }
                    }
                    if ($colMap['h_50']) {
                        try {
                            $h_50 = floatval($sheet->getCellByColumnAndRow($colMap['h_50'], $row)->getCalculatedValue());
                        } catch (Exception $ex) {
                            $h_50 = floatval($sheet->getCellByColumnAndRow($colMap['h_50'], $row)->getValue());
                        }
                    }
                    if ($colMap['h_100']) {
                        try {
                            $h_100 = floatval($sheet->getCellByColumnAndRow($colMap['h_100'], $row)->getCalculatedValue());
                        } catch (Exception $ex) {
                            $h_100 = floatval($sheet->getCellByColumnAndRow($colMap['h_100'], $row)->getValue());
                        }
                    }
                    
                    $obs = $colMap['obs'] ? trim((string)$sheet->getCellByColumnAndRow($colMap['obs'], $row)->getValue()) : '';
                    
                    // Determine values based on column mappings
                    $groupVal = '';
                    $detailVal = '';
                    
                    if ($colMap['actividad']) {
                        $groupVal = trim((string)$sheet->getCellByColumnAndRow($colMap['actividad'], $row)->getValue());
                    }
                    if ($colMap['detalle']) {
                        $detailVal = trim((string)$sheet->getCellByColumnAndRow($colMap['detalle'], $row)->getValue());
                    }
                    
                    $hasCheckboxesOrHours = ($n1 || $n2 || $n3 || $e1 || $e2 || $h_lab > 0 || $h_50 > 0 || $h_100 > 0);
                    
                    if ($colMap['actividad'] !== null) {
                        if ($groupVal === '' && $detailVal === '') {
                            continue;
                        }
                        if ($groupVal !== '') {
                            $currentGroup = $groupVal;
                        }
                        $detail = $detailVal;
                        if ($detail === '') {
                            $detail = $currentGroup;
                        }
                    } else {
                        // JCV001-style sheet: only Detalle column
                        $val = $detailVal !== '' ? $detailVal : $groupVal;
                        if ($val === '') {
                            continue;
                        }
                        if ($hasCheckboxesOrHours) {
                            $detail = $val;
                        } else {
                            $currentGroup = $val;
                            continue; // Skip inserting the group header row itself as a leaf item
                        }
                    }
                    
                    // Generate unique code
                    $brandCounters[$brandPrefix]++;
                    $codigo_unico = $brandPrefix . '_' . str_pad($brandCounters[$brandPrefix], 3, '0', STR_PAD_LEFT);
                    
                    $stmt_ins_pool->execute([
                        $codigo_unico, $sName, $currentGroup, $detail, $n1, $n2, $n3, $e1, $e2, $tipo, $h_lab, $h_50, $h_100, $obs
                    ]);
                    $insertedCount++;
                }
            }

            echo json_encode([
                'success' => true,
                'message' => "Catálogo importado exitosamente. Se cargaron {$insertedCount} servicios."
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_attachments':
        try {
            $quote_id = (int)($_GET['quote_id'] ?? 0);
            if ($quote_id <= 0) {
                throw new Exception("ID de cotización inválido.");
            }
            $stmt = $pdo->prepare("SELECT * FROM cotizador_cotizaciones_adjuntos WHERE cotizacion_id = ? ORDER BY uploaded_at DESC");
            $stmt->execute([$quote_id]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'upload_attachment':
        try {
            $quote_id = (int)($_POST['quote_id'] ?? 0);
            if ($quote_id <= 0) {
                throw new Exception("ID de cotización inválido.");
            }
            if (!isset($_FILES['attachment_file']) || $_FILES['attachment_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Error al cargar el archivo.");
            }
            
            $base_dir = dirname(dirname(__DIR__)); 
            $upload_path = $base_dir . '/storage/uploads/';

            if (!is_dir($upload_path)) {
                if (!mkdir($upload_path, 0777, true)) {
                    throw new Exception("No se pudo crear la carpeta de subidas.");
                }
            }

            $filename = $_FILES['attachment_file']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'xlsx', 'xls', 'docx', 'doc', 'txt', 'zip', 'rar', 'csv'];
            if (!in_array($ext, $allowed)) {
                throw new Exception("Formato no permitido. Formatos válidos: JPG, PNG, PDF, Excel, Word, TXT, ZIP, CSV.");
            }

            $new_name = "ADJ_" . uniqid() . "." . $ext;
            $full_dest = $upload_path . $new_name;
            $db_relative_path = "storage/uploads/" . $new_name;
            $filesize = $_FILES['attachment_file']['size'];

            if (!move_uploaded_file($_FILES['attachment_file']['tmp_name'], $full_dest)) {
                throw new Exception("Error al mover el archivo al destino.");
            }

            $stmt = $pdo->prepare("INSERT INTO cotizador_cotizaciones_adjuntos (cotizacion_id, filename, filepath, filesize, uploaded_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$quote_id, $filename, $db_relative_path, $filesize]);

            echo json_encode(['success' => true, 'message' => 'Archivo adjunto guardado correctamente.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'delete_attachment':
        try {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception("ID de archivo inválido.");
            }
            
            $stmt = $pdo->prepare("SELECT * FROM cotizador_cotizaciones_adjuntos WHERE id = ?");
            $stmt->execute([$id]);
            $att = $stmt->fetch();
            if (!$att) {
                throw new Exception("Archivo no encontrado.");
            }

            $base_dir = dirname(dirname(__DIR__));
            $filepath = $base_dir . '/' . $att['filepath'];
            
            $stmt_del = $pdo->prepare("DELETE FROM cotizador_cotizaciones_adjuntos WHERE id = ?");
            $stmt_del->execute([$id]);

            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM cotizador_cotizaciones_adjuntos WHERE filepath = ?");
            $stmt_check->execute([$att['filepath']]);
            $inUse = $stmt_check->fetchColumn();
            if ($inUse == 0 && file_exists($filepath)) {
                unlink($filepath);
            }

            echo json_encode(['success' => true, 'message' => 'Archivo adjunto eliminado.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Acción no soportada.']);
        break;
}

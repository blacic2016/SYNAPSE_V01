<?php
/**
 * API para el Módulo de Modelos Visio (VSDX) - CMDB VILASECA
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/permissions_helper.php';
require_once __DIR__ . '/../src/db.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Si se requiere únicamente la vista previa gráfica del diagrama (imágenes en <img> o PDF), servir directamente sin redireccionar
if ($action === 'get_preview') {
    $pdo = getPDO();
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT image_data, ci_instance_id FROM visio_diagrams WHERE id = ?");
        $stmt->execute([$id]);
        $diag = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($diag && !empty($diag['image_data'])) {
            $img = trim($diag['image_data']);
            if (strpos($img, 'data:image/png;base64,') === 0) {
                header('Content-Type: image/png');
                echo base64_decode(substr($img, strlen('data:image/png;base64,')));
                exit();
            } else if (strpos($img, 'data:image/jpeg;base64,') === 0) {
                header('Content-Type: image/jpeg');
                echo base64_decode(substr($img, strlen('data:image/jpeg;base64,')));
                exit();
            } else if (strpos($img, 'data:image/svg+xml;base64,') === 0) {
                header('Content-Type: image/svg+xml');
                echo base64_decode(substr($img, strlen('data:image/svg+xml;base64,')));
                exit();
            } else if (strpos($img, 'data:image/svg+xml,') === 0) {
                header('Content-Type: image/svg+xml');
                echo rawurldecode(substr($img, strlen('data:image/svg+xml,')));
                exit();
            } else if (strpos($img, '<svg') !== false || strpos($img, '<?xml') !== false) {
                header('Content-Type: image/svg+xml');
                echo $img;
                exit();
            }
        }
    }
    header('Content-Type: image/svg+xml');
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="200"><rect width="100%" height="100%" fill="#0f172a"/><text x="50%" y="50%" fill="#fff" font-family="sans-serif" font-size="14" text-anchor="middle">Diagrama Visio CMDB</text></svg>';
    exit();
}

if (php_sapi_name() !== 'cli' && !headers_sent()) {
    header('Content-Type: application/json');
}

// Validar login
require_login();
if (!has_module_access('diagrams') && !has_module_access('vilaseca')) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$pdo = getPDO();

// Auto-creación de tablas de Visio si no existen
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS visio_diagrams (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT NULL,
        xml_content LONGTEXT NOT NULL,
        filename_original VARCHAR(255) NULL,
        ci_instance_id INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_visio_ci (ci_instance_id),
        CONSTRAINT fk_visio_ci FOREIGN KEY (ci_instance_id) REFERENCES ci_instances(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS visio_diagram_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        diagram_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NULL,
        xml_content LONGTEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_visio_history_diagram FOREIGN KEY (diagram_id) REFERENCES visio_diagrams(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
} catch (PDOException $e) {
    // Si la creación con llave foránea falla por alguna razón del motor, creamos sin restricción dura
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS visio_diagrams (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            xml_content LONGTEXT NOT NULL,
            filename_original VARCHAR(255) NULL,
            ci_instance_id INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS visio_diagram_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            diagram_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            xml_content LONGTEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    } catch (PDOException $ex) {
        // Fallback final
    }
}

// Función para obtener la información del cliente asociado a un CI
function getCIClientInfo($pdo, $ci_id) {
    if (!$ci_id) return null;

    // 1. Jerarquía de CI por parent_ci_id
    $curr = $ci_id;
    $visited = [];
    while ($curr && !in_array($curr, $visited)) {
        $visited[] = $curr;
        $stmt = $pdo->prepare("
            SELECT ci.id, ci.hostname, ci.parent_ci_id, ci.category_id, ci.attributes_json, cat.name AS category_name 
            FROM ci_instances ci 
            JOIN ci_categories cat ON ci.category_id = cat.id 
            WHERE ci.id = ?
        ");
        $stmt->execute([$curr]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) break;

        if (stripos($row["category_name"], "client") !== false || $row["category_id"] == 48 || $row["category_id"] == 49) {
            return ["id" => $row["id"], "name" => $row["hostname"]];
        }

        if (!empty($row["attributes_json"])) {
            $attrs = json_decode($row["attributes_json"], true);
            if (is_array($attrs)) {
                foreach (["cliente", "client", "empresa", "organizacion"] as $key) {
                    if (!empty($attrs[$key])) {
                        return ["id" => null, "name" => $attrs[$key]];
                    }
                }
            }
        }

        if (!$row["parent_ci_id"]) {
            if ($row["id"] != $ci_id) {
                return ["id" => $row["id"], "name" => $row["hostname"]];
            }
            break;
        }
        $curr = $row["parent_ci_id"];
    }

    // 2. Buscar en relaciones por un CI de categoría Cliente
    $stmtRel = $pdo->prepare("
        SELECT r.target_id, ci_target.hostname 
        FROM ci_relationships r 
        JOIN ci_instances ci_target ON r.target_id = ci_target.id 
        JOIN ci_categories cat ON ci_target.category_id = cat.id 
        WHERE r.source_id = ? AND (cat.name LIKE '%client%' OR cat.id IN (48, 49))
        LIMIT 1
    ");
    $stmtRel->execute([$ci_id]);
    $rel = $stmtRel->fetch(PDO::FETCH_ASSOC);
    if ($rel) {
        return ["id" => $rel["target_id"], "name" => $rel["hostname"]];
    }

    return null;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            // Listar diagramas con info de CIs si están asociados
            $stmt = $pdo->query("
                SELECT vd.id, vd.title, vd.description, vd.filename_original, vd.updated_at, vd.ci_instance_id,
                       ci.hostname AS ci_hostname, ci.ci_unique AS ci_unique, cat.name AS category_name
                FROM visio_diagrams vd
                LEFT JOIN ci_instances ci ON vd.ci_instance_id = ci.id
                LEFT JOIN ci_categories cat ON ci.category_id = cat.id
                ORDER BY vd.updated_at DESC
            ");
            $diagrams = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($diagrams as &$diag) {
                if (!empty($diag['ci_instance_id'])) {
                    $cli = getCIClientInfo($pdo, $diag['ci_instance_id']);
                    $diag['client_name'] = $cli ? $cli['name'] : null;
                    $diag['client_ci_id'] = $cli ? $cli['id'] : null;
                } else {
                    $diag['client_name'] = null;
                    $diag['client_ci_id'] = null;
                }
            }
            echo json_encode(['success' => true, 'diagrams' => $diagrams]);
            break;

        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID de diagrama inválido.']);
                exit();
            }
            $stmt = $pdo->prepare("
                SELECT vd.*, ci.hostname AS ci_hostname, ci.ci_unique AS ci_unique, cat.name AS category_name
                FROM visio_diagrams vd
                LEFT JOIN ci_instances ci ON vd.ci_instance_id = ci.id
                LEFT JOIN ci_categories cat ON ci.category_id = cat.id
                WHERE vd.id = ?
            ");
            $stmt->execute([$id]);
            $diagram = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$diagram) {
                echo json_encode(['success' => false, 'error' => 'Diagrama no encontrado.']);
                exit();
            }
            if (!empty($diagram['ci_instance_id'])) {
                $cli = getCIClientInfo($pdo, $diagram['ci_instance_id']);
                $diagram['client_name'] = $cli ? $cli['name'] : null;
                $diagram['client_ci_id'] = $cli ? $cli['id'] : null;
            } else {
                $diagram['client_name'] = null;
                $diagram['client_ci_id'] = null;
            }
            echo json_encode(['success' => true, 'diagram' => $diagram]);
            break;

        case 'save':
            $id = (int)($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $xml = $_POST['xml_content'] ?? '';
            $filename_original = trim($_POST['filename_original'] ?? '');
            $ci_instance_id = (isset($_POST['ci_instance_id']) && $_POST['ci_instance_id'] !== '') ? (int)$_POST['ci_instance_id'] : null;
            $imageData = $_POST['image_data'] ?? null;

            if (empty($title)) {
                echo json_encode(['success' => false, 'error' => 'El título es requerido.']);
                exit();
            }
            if (empty($xml)) {
                echo json_encode(['success' => false, 'error' => 'El contenido del diagrama es requerido.']);
                exit();
            }

            if ($id > 0) {
                // Actualizar
                $stmt = $pdo->prepare("
                    UPDATE visio_diagrams 
                    SET title = ?, description = ?, xml_content = ?, filename_original = ?, ci_instance_id = ?,
                        image_data = COALESCE(?, image_data) 
                    WHERE id = ?
                ");
                $stmt->execute([$title, $description, $xml, $filename_original, $ci_instance_id, $imageData, $id]);
                $diagramId = $id;
                $msg = 'Diagrama actualizado correctamente.';
            } else {
                // Crear
                $stmt = $pdo->prepare("
                    INSERT INTO visio_diagrams (title, description, xml_content, filename_original, ci_instance_id, image_data) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$title, $description, $xml, $filename_original, $ci_instance_id, $imageData]);
                $diagramId = $pdo->lastInsertId();
                $msg = 'Diagrama creado y guardado correctamente.';
            }

            // Registrar en el historial de versiones
            $stmtHistory = $pdo->prepare("
                INSERT INTO visio_diagram_history (diagram_id, title, description, xml_content) 
                VALUES (?, ?, ?, ?)
            ");
            $stmtHistory->execute([$diagramId, $title, $description, $xml]);

            echo json_encode(['success' => true, 'message' => $msg, 'id' => $diagramId]);
            break;

        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID inválido.']);
                exit();
            }
            $stmt = $pdo->prepare("DELETE FROM visio_diagrams WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Diagrama eliminado correctamente.']);
            break;

        case 'history':
            $diagramId = (int)($_GET['diagram_id'] ?? 0);
            if ($diagramId <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID de diagrama inválido.']);
                exit();
            }
            $stmt = $pdo->prepare("SELECT id, title, description, created_at FROM visio_diagram_history WHERE diagram_id = ? ORDER BY created_at DESC");
            $stmt->execute([$diagramId]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'history' => $history]);
            break;

        case 'get_history':
            $historyId = (int)($_GET['id'] ?? 0);
            if ($historyId <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID de historial inválido.']);
                exit();
            }
            $stmt = $pdo->prepare("SELECT * FROM visio_diagram_history WHERE id = ?");
            $stmt->execute([$historyId]);
            $version = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$version) {
                echo json_encode(['success' => false, 'error' => 'Versión histórica no encontrada.']);
                exit();
            }
            echo json_encode(['success' => true, 'version' => $version]);
            break;

        case 'update_meta':
            $id = (int)($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $ci_instance_id = (isset($_POST['ci_instance_id']) && $_POST['ci_instance_id'] !== '') ? (int)$_POST['ci_instance_id'] : null;

            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID de diagrama inválido.']);
                exit();
            }
            if (empty($title)) {
                echo json_encode(['success' => false, 'error' => 'El título es requerido.']);
                exit();
            }

            $stmt = $pdo->prepare("
                UPDATE visio_diagrams 
                SET title = ?, description = ?, ci_instance_id = ? 
                WHERE id = ?
            ");
            $stmt->execute([$title, $description, $ci_instance_id, $id]);
            echo json_encode(['success' => true, 'message' => 'Diagrama y asociación a CI actualizados correctamente.']);
            break;

        case 'list_cis':
            // Listar CIs disponibles para asociar
            $stmt = $pdo->query("
                SELECT ci.id, ci.hostname, ci.ci_unique, cat.name AS category_name 
                FROM ci_instances ci 
                JOIN ci_categories cat ON ci.category_id = cat.id 
                ORDER BY cat.name ASC, ci.hostname ASC
            ");
            $cis = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cis as &$c) {
                $cli = getCIClientInfo($pdo, $c['id']);
                $c['client_name'] = $cli ? $cli['name'] : null;
            }
            echo json_encode(['success' => true, 'cis' => $cis]);
            break;

        case 'get_by_ci':
            $ci_id = (int)($_GET['ci_id'] ?? 0);
            if ($ci_id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID de CI inválido.']);
                exit();
            }
            $stmt = $pdo->prepare("SELECT id, title, description, updated_at FROM visio_diagrams WHERE ci_instance_id = ? ORDER BY updated_at DESC");
            $stmt->execute([$ci_id]);
            $diagrams = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'diagrams' => $diagrams]);
            break;

        case 'get_preview':
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) {
                header('Content-Type: image/svg+xml');
                echo '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="200"><rect width="100%" height="100%" fill="#f8fafc"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="#64748b">Diagrama no encontrado</text></svg>';
                exit();
            }
            $stmt = $pdo->prepare("
                SELECT vd.id, vd.title, vd.description, vd.ci_instance_id, vd.image_data, ci.hostname AS ci_hostname, cat.name AS category_name 
                FROM visio_diagrams vd 
                LEFT JOIN ci_instances ci ON vd.ci_instance_id = ci.id 
                LEFT JOIN ci_categories cat ON ci.category_id = cat.id 
                WHERE vd.id = ?
            ");
            $stmt->execute([$id]);
            $diag = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($diag && !empty($diag['image_data'])) {
                $img = trim($diag['image_data']);
                if (strpos($img, 'data:image/png;base64,') === 0) {
                    header('Content-Type: image/png');
                    echo base64_decode(substr($img, strlen('data:image/png;base64,')));
                    exit();
                } else if (strpos($img, 'data:image/jpeg;base64,') === 0) {
                    header('Content-Type: image/jpeg');
                    echo base64_decode(substr($img, strlen('data:image/jpeg;base64,')));
                    exit();
                } else if (strpos($img, 'data:image/svg+xml;base64,') === 0) {
                    header('Content-Type: image/svg+xml');
                    echo base64_decode(substr($img, strlen('data:image/svg+xml;base64,')));
                    exit();
                } else if (strpos($img, 'data:image/svg+xml,') === 0) {
                    header('Content-Type: image/svg+xml');
                    echo rawurldecode(substr($img, strlen('data:image/svg+xml,')));
                    exit();
                } else if (strpos($img, '<svg') !== false || strpos($img, '<?xml') !== false) {
                    header('Content-Type: image/svg+xml');
                    echo $img;
                    exit();
                }
            }

            $client_name = '';
            if ($diag && !empty($diag['ci_instance_id'])) {
                $cli = getCIClientInfo($pdo, $diag['ci_instance_id']);
                $client_name = $cli ? $cli['name'] : '';
            }

            $title = htmlspecialchars($diag['title'] ?? ('Diagrama Visio #' . $id));
            $ci = htmlspecialchars($diag['ci_hostname'] ? ('[' . ($diag['category_name'] ?? 'CI') . '] ' . $diag['ci_hostname']) : 'Sin CI Específico');
            $client = htmlspecialchars($client_name ?: 'General / Sin Cliente');

            header('Content-Type: image/svg+xml');
            echo '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="200" viewBox="0 0 600 200">
                <rect width="600" height="200" rx="12" fill="#0f172a"/>
                <rect x="15" y="15" width="570" height="170" rx="8" fill="#1e293b" stroke="#334155" stroke-width="1.5"/>
                <circle cx="50" cy="50" r="18" fill="#0284c7"/>
                <text x="50" y="55" text-anchor="middle" fill="#ffffff" font-family="sans-serif" font-size="14" font-weight="bold">VS</text>
                <text x="80" y="46" fill="#f8fafc" font-family="sans-serif" font-size="16" font-weight="bold">' . $title . '</text>
                <text x="80" y="64" fill="#94a3b8" font-family="sans-serif" font-size="12">Diagrama de Arquitectura y Red (Visio VSDX)</text>
                <line x1="30" y1="85" x2="570" y2="85" stroke="#334155" stroke-width="1"/>
                <rect x="30" y="100" width="260" height="65" rx="6" fill="#0f172a" stroke="#0284c7" stroke-width="1"/>
                <text x="45" y="120" fill="#38bdf8" font-family="sans-serif" font-size="10" font-weight="bold">COMPONENTE CI ASOCIADO</text>
                <text x="45" y="145" fill="#f1f5f9" font-family="sans-serif" font-size="12" font-weight="bold">' . $ci . '</text>
                <rect x="310" y="100" width="260" height="65" rx="6" fill="#0f172a" stroke="#6366f1" stroke-width="1"/>
                <text x="325" y="120" fill="#818cf8" font-family="sans-serif" font-size="10" font-weight="bold">CLIENTE CMDB</text>
                <text x="325" y="145" fill="#f1f5f9" font-family="sans-serif" font-size="12" font-weight="bold">' . $client . '</text>
            </svg>';
            exit();

        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida.']);
            break;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error en la base de datos: ' . $e->getMessage()]);
}

<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../src/auth.php';
require_once __DIR__ . '/../../../src/db.php';
require_once __DIR__ . '/../../../src/permissions_helper.php';

// Control de acceso
require_login();
if (!has_role('SUPER_ADMIN') && !has_module_access('gitlab')) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Acceso denegado. Sin permisos para el módulo GitLab.']);
    exit();
}

$pdo = getPDO();

// Auto-inicialización de base de datos
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS gitlab_files (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255) NOT NULL UNIQUE,
        file_type VARCHAR(50) DEFAULT 'txt',
        description_md LONGTEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS gitlab_versions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        file_id INT NOT NULL,
        version_number INT NOT NULL,
        content LONGTEXT NOT NULL,
        change_summary TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (file_id) REFERENCES gitlab_files(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS gitlab_git_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        file_id INT NULL,
        filename VARCHAR(255) NULL,
        commit_hash VARCHAR(50) NULL,
        commit_message TEXT NULL,
        git_output LONGTEXT NULL,
        status VARCHAR(20) DEFAULT 'success',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error al inicializar base de datos: ' . $e->getMessage()]);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
header('Content-Type: application/json');

if ($action === 'list_files') {
    try {
        $stmt = $pdo->query("
            SELECT f.id, f.filename, f.file_type, f.created_at, f.updated_at,
                   (SELECT COUNT(*) FROM gitlab_versions WHERE file_id = f.id) as version_count,
                   (SELECT created_at FROM gitlab_versions WHERE file_id = f.id ORDER BY version_number DESC LIMIT 1) as last_version_date
            FROM gitlab_files f
            ORDER BY f.updated_at DESC
        ");
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $files]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

if ($action === 'file_details') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de archivo inválido.']);
        exit();
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM gitlab_files WHERE id = ?");
        $stmt->execute([$id]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$file) {
            echo json_encode(['success' => false, 'message' => 'Archivo no encontrado.']);
            exit();
        }

        // Obtener versiones
        $stmt_vers = $pdo->prepare("SELECT id, version_number, change_summary, created_at FROM gitlab_versions WHERE file_id = ? ORDER BY version_number DESC");
        $stmt_vers->execute([$id]);
        $versions = $stmt_vers->fetchAll(PDO::FETCH_ASSOC);

        // Cargar contenido de versión solicitada o última
        $req_version = isset($_GET['version']) ? (int)$_GET['version'] : 0;
        if ($req_version > 0) {
            $stmt_content = $pdo->prepare("SELECT content, version_number, change_summary, created_at FROM gitlab_versions WHERE file_id = ? AND version_number = ?");
            $stmt_content->execute([$id, $req_version]);
        } else {
            $stmt_content = $pdo->prepare("SELECT content, version_number, change_summary, created_at FROM gitlab_versions WHERE file_id = ? ORDER BY version_number DESC LIMIT 1");
            $stmt_content->execute([$id]);
        }
        $version_data = $stmt_content->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'file' => $file,
            'versions' => $versions,
            'current_version' => $version_data
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

if ($action === 'save_file') {
    $file_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $filename = trim($_POST['filename'] ?? '');
    $content = $_POST['content'] ?? '';
    $change_summary = trim($_POST['change_summary'] ?? '');
    $file_type = trim($_POST['file_type'] ?? 'txt');

    if (empty($filename)) {
        echo json_encode(['success' => false, 'message' => 'El nombre del archivo es requerido.']);
        exit();
    }

    $pdo->beginTransaction();
    try {
        if ($file_id > 0) {
            $stmt = $pdo->prepare("SELECT id, filename FROM gitlab_files WHERE id = ?");
            $stmt->execute([$file_id]);
            $file = $stmt->fetch();
        } else {
            $stmt = $pdo->prepare("SELECT id, filename FROM gitlab_files WHERE filename = ?");
            $stmt->execute([$filename]);
            $file = $stmt->fetch();
        }

        if (!$file) {
            // Guardar nuevo
            $stmt = $pdo->prepare("INSERT INTO gitlab_files (filename, file_type, description_md) VALUES (?, ?, ?)");
            $stmt->execute([$filename, $file_type, "# " . htmlspecialchars($filename) . "\n\nEscribe aquí la documentación de este archivo en formato Markdown.\n\n## Historial de cambios\n- **v1**: Creación inicial."]);
            $file_id = $pdo->lastInsertId();
            $version_num = 1;

            $stmt_ver = $pdo->prepare("INSERT INTO gitlab_versions (file_id, version_number, content, change_summary) VALUES (?, ?, ?, ?)");
            $stmt_ver->execute([$file_id, $version_num, $content, 'Versión inicial']);

            // Crear carpeta y archivo MD en repositorio local
            $file_basename = pathinfo($filename, PATHINFO_FILENAME);
            if (!empty($file_basename)) {
                $repo_dir = ROOT_PATH . '/public/femsa/repository';
                $script_folder = $repo_dir . '/' . $file_basename;
                if (!is_dir($script_folder)) @mkdir($script_folder, 0777, true);
                @file_put_contents($script_folder . '/' . basename($filename), $content);
                
                $md_content = "# Documentación del Archivo: {$filename}\n\n";
                $md_content .= "**Nombre de Archivo:** `{$filename}`  \n";
                $md_content .= "**Carpeta en Repositorio:** `{$file_basename}/`  \n";
                $md_content .= "**Tipo de Archivo:** `" . strtoupper($file_type) . "`  \n";
                $md_content .= "**Fecha de Creación:** " . date('Y-m-d H:i:s') . "  \n\n";
                $md_content .= "## Código Fuente\n```" . strtolower($file_type) . "\n{$content}\n```\n";
                
                @file_put_contents($script_folder . '/' . $file_basename . '.md', $md_content);
                @file_put_contents($script_folder . '/README.md', $md_content);
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Archivo creado con versión 1 y carpeta ' . $file_basename . '/ generada.', 'file_id' => $file_id, 'version_number' => $version_num]);
        } else {
            $file_id = $file['id'];
            $stmt_last = $pdo->prepare("SELECT content, version_number FROM gitlab_versions WHERE file_id = ? ORDER BY version_number DESC LIMIT 1");
            $stmt_last->execute([$file_id]);
            $last_ver = $stmt_last->fetch();

            if ($last_ver && $last_ver['content'] === $content) {
                $pdo->rollBack();
                echo json_encode([
                    'success' => true,
                    'no_changes' => true,
                    'message' => 'No se detectaron modificaciones con respecto a la versión anterior.',
                    'file_id' => $file_id,
                    'version_number' => $last_ver['version_number']
                ]);
                exit();
            }

            $new_version_num = $last_ver ? ($last_ver['version_number'] + 1) : 1;
            $stmt_ver = $pdo->prepare("INSERT INTO gitlab_versions (file_id, version_number, content, change_summary) VALUES (?, ?, ?, ?)");
            $stmt_ver->execute([$file_id, $new_version_num, $content, $change_summary ?: "Cambios en versión $new_version_num"]);

            // Actualizar carpeta y archivo MD en repositorio local
            $file_basename = pathinfo($filename, PATHINFO_FILENAME);
            if (!empty($file_basename)) {
                $repo_dir = ROOT_PATH . '/public/femsa/repository';
                $script_folder = $repo_dir . '/' . $file_basename;
                if (!is_dir($script_folder)) @mkdir($script_folder, 0777, true);
                @file_put_contents($script_folder . '/' . basename($filename), $content);

                $md_content = "# Documentación del Archivo: {$filename}\n\n";
                $md_content .= "**Nombre de Archivo:** `{$filename}`  \n";
                $md_content .= "**Carpeta en Repositorio:** `{$file_basename}/`  \n";
                $md_content .= "**Versión:** `v{$new_version_num}`  \n";
                $md_content .= "**Fecha de Actualización:** " . date('Y-m-d H:i:s') . "  \n\n";
                $md_content .= "## Resumen del Cambio\n" . ($change_summary ?: "Actualización a versión {$new_version_num}") . "\n\n";
                $md_content .= "## Código Fuente\n```" . strtolower($file_type) . "\n{$content}\n```\n";

                @file_put_contents($script_folder . '/' . $file_basename . '.md', $md_content);
                @file_put_contents($script_folder . '/README.md', $md_content);
            }

            // Actualizar la fecha de modificación del archivo principal
            $pdo->prepare("UPDATE gitlab_files SET updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$file_id]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => "Versión $new_version_num guardada exitosamente en carpeta {$file_basename}/.", 'file_id' => $file_id, 'version_number' => $new_version_num]);
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error al guardar el archivo: ' . $e->getMessage()]);
    }
    exit();
}

if ($action === 'save_description') {
    $id = (int)($_POST['id'] ?? 0);
    $description_md = $_POST['description_md'] ?? '';

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de archivo inválido.']);
        exit();
    }

    try {
        $stmt = $pdo->prepare("UPDATE gitlab_files SET description_md = ? WHERE id = ?");
        $stmt->execute([$description_md, $id]);
        echo json_encode(['success' => true, 'message' => 'Documentación actualizada con éxito.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

if ($action === 'delete_file') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de archivo inválido.']);
        exit();
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM gitlab_files WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Archivo y sus versiones eliminados permanentemente.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

if ($action === 'delete_version') {
    $version_id = (int)($_POST['version_id'] ?? 0);
    if ($version_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de versión inválido.']);
        exit();
    }

    $pdo->beginTransaction();
    try {
        $stmt_info = $pdo->prepare("SELECT file_id, version_number FROM gitlab_versions WHERE id = ?");
        $stmt_info->execute([$version_id]);
        $ver_info = $stmt_info->fetch(PDO::FETCH_ASSOC);

        if (!$ver_info) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Versión no encontrada.']);
            exit();
        }

        $file_id = $ver_info['file_id'];

        // Contar versiones totales para no permitir borrar la última si es la única
        $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM gitlab_versions WHERE file_id = ?");
        $stmt_count->execute([$file_id]);
        $total_versions = (int)$stmt_count->fetchColumn();

        if ($total_versions <= 1) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'No puedes eliminar la única versión de este archivo. Si deseas eliminar el archivo completo, usa la opción de eliminar archivo.']);
            exit();
        }

        $stmt_del = $pdo->prepare("DELETE FROM gitlab_versions WHERE id = ?");
        $stmt_del->execute([$version_id]);

        // Actualizar la fecha de modificación del archivo
        $pdo->prepare("UPDATE gitlab_files SET updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$file_id]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Versión eliminada con éxito.']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error al eliminar la versión: ' . $e->getMessage()]);
    }
    exit();
}

if ($action === 'git_push') {
    @set_time_limit(120);

    $file_id = (int)($_POST['file_id'] ?? 0);
    $filename = trim($_POST['filename'] ?? '');
    $content = $_POST['content'] ?? '';
    $commit_msg = trim($_POST['commit_message'] ?? '');
    $gitlab_config_file = __DIR__ . '/../../assets/gitlab_config.json';
    $gitlab_config = [];
    if (file_exists($gitlab_config_file)) {
        $gitlab_config = json_decode(file_get_contents($gitlab_config_file), true) ?: [];
    }

    $remote_url = trim($_POST['remote_url'] ?? $gitlab_config['remote_url'] ?? 'https://github.com/blacic2016/femsa-sonda.git');
    $pat_token = trim($_POST['pat_token'] ?? $gitlab_config['pat_token'] ?? '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');
    $user_name = trim($_POST['user_name'] ?? $gitlab_config['user_name'] ?? 'blacic2016');
    $user_email = trim($_POST['user_email'] ?? $gitlab_config['user_email'] ?? 'blacic2016@gmail.com');

    if (empty($filename)) {
        echo json_encode(['success' => false, 'message' => 'El nombre del archivo es requerido.']);
        exit();
    }

    $file_basename = pathinfo($filename, PATHINFO_FILENAME);
    if (empty($file_basename)) $file_basename = 'archivo_gitlab';

    if (empty($commit_msg)) {
        $commit_msg = "Creación/Actualización de carpeta {$file_basename}/ con {$filename} y su archivo MD de documentación";
    }

    $repo_dir = ROOT_PATH . '/public/femsa/repository';
    if (!is_dir($repo_dir)) {
        @mkdir($repo_dir, 0777, true);
    }
    @chmod($repo_dir, 0777);

    // 1. Crear carpeta específica con el nombre del archivo
    $script_folder = $repo_dir . '/' . $file_basename;
    if (!is_dir($script_folder)) {
        @mkdir($script_folder, 0777, true);
    }
    @chmod($script_folder, 0777);

    // 2. Escribir archivo fuente en la carpeta y en la raíz
    $file_path = $script_folder . '/' . basename($filename);
    @file_put_contents($file_path, $content);
    @chmod($file_path, 0777);

    @file_put_contents($repo_dir . '/' . basename($filename), $content);

    // 3. Crear el archivo .md de documentación dentro de la misma carpeta
    $file_type = pathinfo($filename, PATHINFO_EXTENSION) ?: 'txt';
    $md_content = "# Documentación del Archivo: {$filename}\n\n";
    $md_content .= "**Nombre de Archivo:** `{$filename}`  \n";
    $md_content .= "**Carpeta en Repositorio:** `{$file_basename}/`  \n";
    $md_content .= "**Fecha de Git Push:** " . date('Y-m-d H:i:s') . "  \n\n";
    $md_content .= "## Mensaje de Commit\n```\n{$commit_msg}\n```\n\n";
    $md_content .= "## Código Fuente\n```" . strtolower($file_type) . "\n{$content}\n```\n";

    @file_put_contents($script_folder . '/' . $file_basename . '.md', $md_content);
    @file_put_contents($script_folder . '/README.md', $md_content);

    if (is_dir($repo_dir . '/.git')) {
        @exec("chmod -R 777 " . escapeshellarg($repo_dir));
    }

    // Formatear URL remota autenticada
    if (strpos($remote_url, '://') !== false && strpos($remote_url, '@') === false) {
        $parts = explode('://', $remote_url, 2);
        $authenticated_url = $parts[0] . '://' . $user_name . ':' . $pat_token . '@' . $parts[1];
    } else {
        $authenticated_url = $remote_url;
    }

    $cmds = [
        "export GIT_TERMINAL_PROMPT=0",
        "cd " . escapeshellarg($repo_dir),
        "git -c safe.directory='*' init 2>&1",
        "git -c safe.directory='*' config user.name " . escapeshellarg($user_name),
        "git -c safe.directory='*' config user.email " . escapeshellarg($user_email),
        "git -c safe.directory='*' remote set-url origin " . escapeshellarg($authenticated_url) . " 2>/dev/null || git -c safe.directory='*' remote add origin " . escapeshellarg($authenticated_url) . " 2>&1",
        "git -c safe.directory='*' branch -M main 2>&1",
        "git -c safe.directory='*' add . 2>&1",
        "git -c safe.directory='*' commit -m " . escapeshellarg($commit_msg) . " 2>&1 || true",
        "git -c safe.directory='*' push -u origin main 2>&1"
    ];

    $full_command = implode('; ', $cmds);
    $output_lines = [];
    $return_code = 0;
    exec($full_command, $output_lines, $return_code);

    $git_output = implode("\n", $output_lines);
    // Enmascarar Token PAT en los logs para seguridad
    @umask(0000);
    @exec("chmod -R 777 " . escapeshellarg($repo_dir) . " 2>/dev/null");

    $commit_hash = '';
    $hash_output = [];
    exec("cd " . escapeshellarg($repo_dir) . " && git -c safe.directory='*' rev-parse --short HEAD 2>&1", $hash_output);
    if (count($hash_output) > 0) {
        $commit_hash = trim($hash_output[0]);
    }

    $status = ($return_code === 0) ? 'success' : 'error';

    try {
        $stmt_log = $pdo->prepare("INSERT INTO gitlab_git_logs (file_id, filename, commit_hash, commit_message, git_output, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_log->execute([$file_id, $filename, $commit_hash, $commit_msg, $git_output, $status]);
    } catch (Exception $e) {
        // Ignorar si falla el log secundario
    }

    if ($return_code === 0) {
        echo json_encode([
            'success' => true,
            'message' => '¡Git Push realizado con éxito a GitHub!',
            'commit_hash' => $commit_hash,
            'git_output' => $git_output,
            'command_run' => "git add . && git commit -m \"{$commit_msg}\" && git push origin main"
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error al ejecutar Git Push.',
            'git_output' => $git_output,
            'command_run' => "git add . && git commit -m \"{$commit_msg}\" && git push origin main"
        ]);
    }
    exit();
}

if ($action === 'git_history') {
    $repo_dir = ROOT_PATH . '/public/femsa/repository';
    
    // 1. Obtener logs desde Base de Datos
    $db_logs = [];
    try {
        $stmt = $pdo->query("SELECT * FROM gitlab_git_logs ORDER BY id DESC LIMIT 30");
        $db_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}

    // 2. Obtener git log desde el repositorio local
    $git_log_raw = '';
    if (is_dir($repo_dir . '/.git')) {
        $log_lines = [];
        exec("cd " . escapeshellarg($repo_dir) . " && git -c safe.directory='*' log -n 25 --graph --pretty=format:'%h - (%ar) %s [%an]' 2>&1", $log_lines);
        $git_log_raw = implode("\n", $log_lines);
    }

    echo json_encode([
        'success' => true,
        'db_logs' => $db_logs,
        'git_log_raw' => $git_log_raw
    ]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Acción no válida.']);
exit();

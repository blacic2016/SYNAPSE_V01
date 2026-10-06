<?php
/**
 * API para Gestión de Usuarios - CMDB VILASECA
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/helpers.php';

require_login();
require_once __DIR__ . '/../src/permissions_helper.php';
if (!has_role(['SUPER_ADMIN']) && !has_module_access('user_management')) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$action = $_POST['action'] ?? '';
$pdo = getPDO();

header('Content-Type: application/json');

switch ($action) {
    case 'list_users':
        $stmt = $pdo->query("SELECT u.id, u.username, r.name as role, r.id as role_id, u.created_at FROM users u JOIN roles r ON u.role_id = r.id");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'users' => $users]);
        break;

    case 'create_user':
        $user = $_POST['username'] ?? '';
        $pass = $_POST['password'] ?? '';
        $role = $_POST['role_id'] ?? 3; // Default USER

        if (!$user || !$pass) {
            echo json_encode(['success' => false, 'error' => 'Faltan datos']);
            break;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role_id) VALUES (?, ?, ?)");
            $stmt->execute([$user, password_hash($pass, PASSWORD_DEFAULT), $role]);
            echo json_encode(['success' => true, 'message' => 'Usuario creado']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Error al crear o usuario ya existe']);
        }
        break;

    case 'update_user':
        $id = $_POST['id'] ?? 0;
        $user = $_POST['username'] ?? '';
        $role = $_POST['role_id'] ?? '';

        if (!$id || !$user || !$role) {
            echo json_encode(['success' => false, 'error' => 'Faltan datos']);
            break;
        }

        $stmt = $pdo->prepare("UPDATE users SET username = ?, role_id = ? WHERE id = ?");
        if ($stmt->execute([$user, $role, $id])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al actualizar']);
        }
        break;

    case 'reset_password':
        $id = $_POST['id'] ?? 0;
        $pass = $_POST['password'] ?? '';

        if (!$id || !$pass) {
            echo json_encode(['success' => false, 'error' => 'Falta ID o contraseña']);
            break;
        }

        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        if ($stmt->execute([password_hash($pass, PASSWORD_DEFAULT), $id])) {
            echo json_encode(['success' => true, 'message' => 'Contraseña actualizada']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al actualizar']);
        }
        break;

    case 'delete_user':
        $id = $_POST['id'] ?? 0;
        if ($id == current_user_id()) {
            echo json_encode(['success' => false, 'error' => 'No puedes eliminarte a ti mismo']);
            break;
        }
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$id])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al eliminar']);
        }
        break;

    case 'get_permissions':
        $userId = $_POST['user_id'] ?? 0;
        
        $sheet_perms = $pdo->prepare("SELECT sheet_name, can_view, can_edit, can_delete FROM user_sheet_permissions WHERE user_id = ?");
        $sheet_perms->execute([$userId]);
        
        $module_perms = $pdo->prepare("SELECT module_name, can_view FROM user_module_permissions WHERE user_id = ?");
        $module_perms->execute([$userId]);
        
        echo json_encode([
            'success' => true, 
            'sheets' => $sheet_perms->fetchAll(PDO::FETCH_ASSOC),
            'modules' => $module_perms->fetchAll(PDO::FETCH_ASSOC)
        ]);
        break;

    case 'save_permissions':
        $userId = $_POST['user_id'] ?? 0;
        $sheets = json_decode($_POST['sheets'] ?? '[]', true);
        $modules = json_decode($_POST['modules'] ?? '[]', true);

        $pdo->beginTransaction();
        try {
            // Limpiar anteriores
            $pdo->prepare("DELETE FROM user_sheet_permissions WHERE user_id = ?")->execute([$userId]);
            $pdo->prepare("DELETE FROM user_module_permissions WHERE user_id = ?")->execute([$userId]);

            // Insertar sheets
            $stmtS = $pdo->prepare("INSERT INTO user_sheet_permissions (user_id, sheet_name, can_view, can_edit, can_delete) VALUES (?, ?, ?, ?, ?)");
            foreach ($sheets as $s) {
                $stmtS->execute([$userId, $s['name'], $s['view'], $s['edit'], $s['delete']]);
            }

            // Insertar modules
            $stmtM = $pdo->prepare("INSERT INTO user_module_permissions (user_id, module_name, can_view) VALUES (?, ?, ?)");
            foreach ($modules as $m) {
                $stmtM->execute([$userId, $m['name'], $m['view']]);
            }

            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Acción no válida']);
        break;
}

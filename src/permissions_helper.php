<?php
/**
 * Helper para chequeo de permisos dinámicos
 */

function has_module_access($module)
{
    // Global deactivation check
    $configFile = __DIR__ . '/../public/assets/modules_config.json';
    if (file_exists($configFile)) {
        $deactivated = json_decode(file_get_contents($configFile), true) ?: [];
        if (in_array($module, $deactivated)) {
            return false;
        }
    }

    if (has_role('SUPER_ADMIN')) return true;
    
    $userId = current_user_id();
    if (!$userId) return false;
    
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT can_view FROM user_module_permissions WHERE user_id = ? AND module_name = ? LIMIT 1");
        $stmt->execute([$userId, $module]);
        $res = $stmt->fetch();
        if ($res !== false) {
            return (int)$res['can_view'] === 1;
        }
        // Fallback: Si no hay registro explícito, los administradores tienen acceso por defecto
        if (has_role('ADMIN')) return true;
        return false;
    } catch (Exception $e) {
        return has_role(['SUPER_ADMIN', 'ADMIN']);
    }
}

function has_sheet_access($sheet, $action = 'view')
{
    if (has_role('SUPER_ADMIN')) return true;
    
    $userId = current_user_id();
    if (!$userId) return false;
    
    $column = 'can_' . $action;
    $validColumns = ['can_view', 'can_edit', 'can_delete'];
    if (!in_array($column, $validColumns)) return false;

    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT $column FROM user_sheet_permissions WHERE user_id = ? AND sheet_name = ? LIMIT 1");
        $stmt->execute([$userId, $sheet]);
        $res = $stmt->fetch();
        return $res && (int)$res[$column] === 1;
    } catch (Exception $e) { return false; }
}

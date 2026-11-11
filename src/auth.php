<?php
// Ubicación: /var/www/html/Sonda/src/auth.php

if (defined("AUTH_INCLUDED")) {
    return;
}
define("AUTH_INCLUDED", true);

// Aseguramos que db.php esté presente en la misma carpeta src/
require_once __DIR__ . "/db.php";

if (session_status() === PHP_SESSION_NONE) session_start();

if (!function_exists("current_user")) {
    function current_user()
    {
        if (isset($_SESSION["user_id"])) {
            try {
                $pdo = getPDO();
                $stmt = $pdo->prepare("SELECT u.id, u.username, r.name as role FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = :id");
                $stmt->execute([":id" => $_SESSION["user_id"]]);
                return $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                // Si la base de datos falla (ej. IP 172.32.1.51 no responde), evitamos el crash
                return null;
            }
        }
        return null;
    }
}

if (!function_exists("current_user_id")) {
    function current_user_id() {
        return $_SESSION["user_id"] ?? null;
    }
}

if (!function_exists("require_login")) {
    function require_login()
    {
        if (!isset($_SESSION["user_id"])) {
            // Cambiamos a una ruta relativa directa para evitar errores con PUBLIC_URL_PREFIX
            header("Location: login.php");
            exit();
        }

        // Validación CSRF global desactivada por requerimiento
    }
}

if (!function_exists("has_role")) {
    function has_role($roles)
    {
        if (!is_array($roles)) $roles = [$roles];
        $user = current_user();
        if (!$user) return false;
        return in_array(strtoupper($user["role"]), array_map("strtoupper", $roles), true);
    }
}

if (!function_exists("require_role")) {
    function require_role($roles)
    {
        if (!has_role($roles)) {
            http_response_code(403);
            exit("Acceso denegado. No tienes permisos suficientes.");
        }
    }
}

if (!function_exists("login_user")) {
    function login_user($username, $password)
    {
        try {
            $pdo = getPDO();
            if (!$pdo) {
                error_log("Login fallido: No se pudo conectar a la base de datos.");
                return false;
            }
            $stmt = $pdo->prepare("SELECT id, password FROM users WHERE username = :u LIMIT 1");
            $stmt->execute([":u" => $username]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verifica la contraseña hash de la base de datos
            if ($row && password_verify($password, $row["password"])) {
                $_SESSION["user_id"] = $row["id"];
                return true;
            }
        } catch (Exception $e) {
            error_log("Excepción en login_user: " . $e->getMessage());
            return false;
        }
        return false;
    }
}

if (!function_exists("logout_user")) {
    function logout_user()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), "", time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            session_destroy();
        }
    }
}

<?php
/**
 * Archivo de Configuración Central - CMDB VILASECA
 * Ubicación: /var/www/html/Sonda/config.php
 */

// 1. Definición de Rutas del Sistema de Archivos (DEBE IR PRIMERO)
define('ROOT_PATH', __DIR__);
define('STORAGE_DIR', ROOT_PATH . '/storage');
define('UPLOAD_DIR_PUBLIC', ROOT_PATH . '/public/uploads');

// Token para acceso a salud y remediación sin login (Acceso Técnico)
define('SECURITY_TOKEN', 'vls_tech_2026');

// Ruta portable de MIBs para el SNMP Builder
define('SNMP_MIBS_PATH', ROOT_PATH . '/public/snmpbuilder/mibs');

// 2. Configuración de Errores (Ahora ya existe STORAGE_DIR)
ini_set('display_errors', 1); // Cambiar a 1 para debugear el NS_ERROR si persiste
ini_set('log_errors', 1);
ini_set('error_log', STORAGE_DIR . '/logs/api_errors.log');

// 3. Configuración de Base de Datos
define('DB_CONFIG', [
    'host'     => 'localhost',
    'user'     => 'root',
    'password' => 'zabbix',
    'database' => 'CMDBVilaseca2'
]);

// 4. Configuración de Zabbix API (Carga Dinámica desde BBDD)
require_once __DIR__ . '/src/db.php';
try {
    if (function_exists('getPDO')) {
        $pdo_cfg = getPDO();
        if ($pdo_cfg) {
            $stmt_cfg = $pdo_cfg->query("SELECT url, token FROM zabbix_api_config LIMIT 1");
            $db_cfg = $stmt_cfg->fetch(PDO::FETCH_ASSOC);
            if ($db_cfg) {
                define('ZABBIX_API_URL', $db_cfg['url']);
                define('ZABBIX_API_TOKEN', $db_cfg['token']);
            }
        }
    }
} catch (Exception $e) { /* Error en carga, se usan fallbacks abajo */ }

// Fallbacks de seguridad si la tabla no existe o está vacía
if (!defined('ZABBIX_API_URL')) {
    define('ZABBIX_API_URL', 'http://172.32.1.50/zabbix/api_jsonrpc.php');
}
if (!defined('ZABBIX_API_TOKEN')) {
    define('ZABBIX_API_TOKEN', '23c5e835efd1c26742b6848ee63b2547ce5349efb88b4ecefee83fa27683cb9a');
}

// 5. Detección Dinámica de la URL (Web Paths)
if (!defined('PUBLIC_URL_PREFIX')) {
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']) : '';
    $currentDir = str_replace('\\', '/', __DIR__);
    
    if (!empty($docRoot)) {
        $baseUrl = str_replace($docRoot, '', $currentDir);
    } else {
        $baseUrl = '/'; // Fallback para CLI
    }
    
    // Definimos el prefijo público para la web
    $public_prefix = rtrim($baseUrl, '/') . '/public';
    define('PUBLIC_URL_PREFIX', $public_prefix);
}

// URL para acceder a los archivos subidos
define('UPLOAD_URL_PUBLIC', PUBLIC_URL_PREFIX . '/uploads');

// 6. Configuración de Seguridad y Sesión
if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
    // FORZAR RUTA LOCAL (Solución para servidores nuevos/restringidos)
    $local_sessions = ROOT_PATH . '/storage/sessions_fix';
    if (!is_dir($local_sessions)) {
        @mkdir($local_sessions, 0777, true);
    }
    if (is_writable($local_sessions)) {
        session_save_path($local_sessions);
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => false, // Cambiar a true si se usa HTTPS
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Global Security Headers
if (php_sapi_name() !== 'cli') {
    header("X-Frame-Options: SAMEORIGIN");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("X-XSS-Protection: 1; mode=block");

    // CSP ajustada para AdminLTE/CDNs y Topología
    $csp = "default-src 'self' 'unsafe-inline' 'unsafe-eval' data: blob: https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://unpkg.com https://cdn.datatables.net https://code.jquery.com https://fonts.googleapis.com https://fonts.gstatic.com; " .
           "script-src 'self' 'unsafe-inline' 'unsafe-eval' blob: https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://unpkg.com https://cdn.datatables.net https://code.jquery.com; " .
           "script-src-elem 'self' 'unsafe-inline' blob: https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://unpkg.com https://cdn.datatables.net https://code.jquery.com; " .
           "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://cdn.datatables.net; " .
           "style-src-elem 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://cdn.datatables.net; " .
           "font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; " .
           "img-src 'self' data: blob: https://img.icons8.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; " .
           "connect-src 'self' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://unpkg.com https://cdn.datatables.net http://172.32.1.50 http://172.32.1.51; " .
           "worker-src 'self' blob:; " .
           "frame-src 'self' https://embed.diagrams.net; " .
           "frame-ancestors 'self';";
    header("Content-Security-Policy: " . $csp);

    // Eliminar cabecera de versión de PHP si está habilitada
    header_remove("X-Powered-By");
}

// 7. Configuraciones Adicionales
define('IMAGE_MAX_BYTES', 32 * 1024 * 1024);
date_default_timezone_set('America/Santiago');

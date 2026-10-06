<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/system_audit_helper.php';

// Permitir acceso sin login si se provee el token de seguridad correcto
$token_valido = false;
if (defined('SECURITY_TOKEN') && isset($_GET['token']) && $_GET['token'] === SECURITY_TOKEN) {
    $token_valido = true;
}
if (defined('SECURITY_TOKEN') && isset($_POST['token']) && $_POST['token'] === SECURITY_TOKEN) {
    $token_valido = true;
}

if (!$token_valido) {
    // Asegurar que solo administradores vean esto dentro de la plataforma
    require_login();
    if (!has_role(['SUPER_ADMIN'])) {
        header("Location: dashboard.php");
        exit();
    }
}

// Lógica de remediación y respaldo
$log = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['fix_issues'])) {
        $log = fixSystemIssues();
    } elseif (isset($_POST['backup_back'])) {
        $log = backupPreproduccionToBack();
    }
}

$audit = runSystemAudit();
$page_title = "Salud del Sistema";

if ($token_valido) {
    // Renderizar versión auto-contenida (Modo Recuperación) sin usar header.php/sidebar.php
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title>Modo Recuperación: Salud del Sistema</title>
      <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
      <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
      <!-- Custom CSS -->
      <link rel="stylesheet" href="<?php echo defined('PUBLIC_URL_PREFIX') ? PUBLIC_URL_PREFIX : '/public'; ?>/assets/css/app.css">
    </head>
    <body class="hold-transition bg-light">
    <div class="container py-4">
        <div class="row mb-3">
            <div class="col-12 d-flex justify-content-between align-items-center">
                <h2><i class="fas fa-heartbeat text-danger mr-2"></i> Salud del Sistema (Modo Recuperación)</h2>
                <span class="badge badge-warning">Acceso vía Token Técnico</span>
            </div>
        </div>
        
        <?php include __DIR__ . '/partials/health_content.php'; ?>
        
    </div>
    <!-- REQUIRED SCRIPTS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    exit();
}

include 'partials/header.php';
include __DIR__ . '/partials/health_content.php';
include 'partials/footer.php';
?>

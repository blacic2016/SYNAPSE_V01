<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = isset($_POST['username']) ? trim($_POST['username']) : '';
    $p = isset($_POST['password']) ? $_POST['password'] : '';
    if (login_user($u, $p)) {
        // Redirigir al dashboard.php que está en la misma carpeta
        header('Location: dashboard.php');
        exit();
    } else {
        $err = 'Usuario o contraseña incorrectos.';
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Acceso - SYNAPSE</title>
  
  <!-- Google Font: Kumbh Sans & Inter -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Kumbh+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  
  <!-- Bootstrap 5 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  
  <style>
    :root {
      --sonda-navy: #101B31;
      --sonda-navy-dark: #080d18;
      --sonda-orange: #ff5c05;
      --sonda-orange-hover: #e04e04;
      --sonda-cyan: #00B8D4;
      --sonda-cyan-hover: #009eb8;
      --sonda-gray: #f4f6f9;
      --text-main: #1f2937;
      --text-muted: #6b7280;
    }
    
    body {
      font-family: 'Kumbh Sans', 'Inter', sans-serif;
      background-color: var(--sonda-gray);
      margin: 0;
      padding: 0;
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }
    
    .login-container {
      display: flex;
      width: 100%;
      height: 100vh;
    }
    
    /* Left Side: Brand Panel */
    .brand-panel {
      flex: 1.2;
      background: linear-gradient(135deg, var(--sonda-navy) 0%, var(--sonda-navy-dark) 100%);
      position: relative;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 4rem;
      color: #ffffff;
      overflow: hidden;
    }
    
    .brand-panel::before {
      content: '';
      position: absolute;
      width: 300px;
      height: 300px;
      background: radial-gradient(circle, rgba(255, 92, 5, 0.15) 0%, rgba(255, 92, 5, 0) 70%);
      top: -50px;
      left: -50px;
      border-radius: 50%;
    }
    
    .brand-panel::after {
      content: '';
      position: absolute;
      width: 400px;
      height: 400px;
      background: radial-gradient(circle, rgba(0, 184, 212, 0.12) 0%, rgba(0, 184, 212, 0) 70%);
      bottom: -100px;
      right: -100px;
      border-radius: 50%;
    }
    
    .brand-content {
      position: relative;
      z-index: 10;
      text-align: center;
      max-width: 500px;
    }
    
    .brand-title {
      font-size: 2.75rem;
      font-weight: 800;
      letter-spacing: 1.5px;
      margin-bottom: 1rem;
      background: linear-gradient(90deg, #ffffff, var(--sonda-cyan));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      text-transform: uppercase;
      margin-top: 1rem;
    }
    
    .brand-subtitle {
      font-size: 1.15rem;
      color: rgba(255, 255, 255, 0.85);
      line-height: 1.6;
      margin-bottom: 2rem;
    }
    
    /* Right Side: Form Panel */
    .form-panel {
      flex: 1;
      background-color: #ffffff;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 4rem;
      box-shadow: -10px 0 30px rgba(0,0,0,0.05);
      position: relative;
      z-index: 5;
    }
    
    .form-content {
      width: 100%;
      max-width: 400px;
    }
    
    .form-header {
      margin-bottom: 2.5rem;
    }
    
    .form-title {
      font-size: 2rem;
      font-weight: 700;
      color: var(--sonda-navy);
      margin-bottom: 0.5rem;
    }
    
    .form-subtitle {
      color: var(--text-muted);
      font-size: 0.95rem;
    }
    
    .input-group-custom {
      position: relative;
      margin-bottom: 1.5rem;
    }
    
    .input-icon {
      position: absolute;
      left: 1rem;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-muted);
      z-index: 10;
      transition: color 0.3s;
    }
    
    .form-control-custom {
      width: 100%;
      padding: 0.85rem 1rem 0.85rem 2.75rem;
      font-size: 1rem;
      border: 1.5px solid #e5e7eb;
      border-radius: 12px;
      outline: none;
      transition: all 0.3s;
      background-color: #f9fafb;
    }
    
    .form-control-custom:focus {
      border-color: var(--sonda-orange);
      background-color: #ffffff;
      box-shadow: 0 0 0 4px rgba(255, 92, 5, 0.15);
    }
    
    .form-control-custom:focus + .input-icon {
      color: var(--sonda-orange);
    }
    
    .btn-login {
      width: 100%;
      padding: 0.85rem;
      font-size: 1.05rem;
      font-weight: 600;
      color: #ffffff;
      background: linear-gradient(135deg, var(--sonda-orange) 0%, var(--sonda-orange-hover) 100%);
      border: none;
      border-radius: 12px;
      cursor: pointer;
      box-shadow: 0 4px 15px rgba(255, 92, 5, 0.3);
      transition: all 0.3s;
      margin-top: 1rem;
    }
    
    .btn-login:hover {
      background: linear-gradient(135deg, var(--sonda-orange-hover) 0%, #c43c00 100%);
      box-shadow: 0 6px 20px rgba(255, 92, 5, 0.4);
      transform: translateY(-2px);
    }
    
    .btn-login:active {
      transform: translateY(0);
    }
    
    .demo-users-card {
      background-color: #f8fafc;
      border: 1px dashed #cbd5e1;
      border-radius: 12px;
      padding: 1rem;
      margin-top: 2rem;
      font-size: 0.85rem;
    }
    
    .demo-title {
      font-weight: 700;
      color: var(--sonda-navy);
      margin-bottom: 0.5rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .demo-user-item {
      margin-bottom: 0.25rem;
      font-family: monospace;
      color: #475569;
    }
    
    .alert-danger-custom {
      background-color: #fef2f2;
      border: 1px solid #fca5a5;
      color: #991b1b;
      border-radius: 12px;
      padding: 1rem;
      margin-bottom: 1.5rem;
      font-size: 0.9rem;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }
    
    /* Responsive */
    @media (max-width: 991px) {
      .login-container {
        flex-direction: column;
      }
      .brand-panel {
        flex: 0.8;
        padding: 2.5rem;
      }
      .brand-title {
        font-size: 2.25rem;
      }
      .brand-subtitle {
        font-size: 1rem;
        margin-bottom: 1rem;
      }
      .form-panel {
        flex: 1.2;
        padding: 3rem 2rem;
      }
      body {
        height: auto;
        overflow: auto;
      }
    }
  </style>
</head>
<body>
  <div class="login-container">
    <!-- Left Side: Brand Panel -->
    <div class="brand-panel">
      <div class="brand-content">
        <div class="mb-4">
          <img src="<?php echo PUBLIC_URL_PREFIX; ?>/logo/logo_white.png" alt="SYNAPSE Logo" style="max-height: 220px; width: auto; filter: drop-shadow(0 12px 24px rgba(0,0,0,0.4));">
        </div>
        <p class="brand-subtitle" style="margin-top: 1.5rem; font-size: 1.25rem; color: rgba(255, 255, 255, 0.85); line-height: 1.6;">Plataforma Inteligente de Operaciones, Monitoreo y Gestión de Configuración (CMDB)</p>
      </div>
    </div>
    
    <!-- Right Side: Form Panel -->
    <div class="form-panel">
      <div class="form-content">
        <div class="form-header">
          <h2 class="form-title">Bienvenido</h2>
          <p class="form-subtitle">Ingresa tus credenciales para acceder a la plataforma</p>
        </div>
        
        <?php if ($err): ?>
          <div class="alert-danger-custom">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <div><?php echo htmlspecialchars($err); ?></div>
          </div>
        <?php endif; ?>
        
        <form method="post">
          <div class="input-group-custom">
            <input type="text" class="form-control-custom" name="username" placeholder="Usuario" required autocomplete="username">
            <i class="fa-regular fa-user input-icon"></i>
          </div>
          
          <div class="input-group-custom">
            <input type="password" class="form-control-custom" name="password" placeholder="Contraseña" required autocomplete="current-password">
            <i class="fa-solid fa-lock input-icon"></i>
          </div>
          
          <button type="submit" class="btn-login">
            <i class="fa-solid fa-right-to-bracket me-2"></i> Ingresar
          </button>
        </form>
        
        <div class="text-center mt-4">
          <a href="<?php echo PUBLIC_URL_PREFIX; ?>/" class="text-decoration-none text-muted" style="font-size: 0.9rem; transition: color 0.2s;">
            <i class="fa-solid fa-arrow-left me-1"></i> Volver al inicio
          </a>
        </div>
      </div>
    </div>
  </div>
</body>
</html>

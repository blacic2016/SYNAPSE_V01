<?php
require_once __DIR__ . '/../../src/auth.php';
$user = current_user();
$page_title = $page_title ?? 'CMDB Vilaseca';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($page_title); ?></title>

  <!-- Google Font: Kumbh Sans (Sonda Brand Font) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Kumbh+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <!-- Font Awesome Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" integrity="sha384-DyZ88mC6Up2uqS4h/KRgHuoeGwBcD4Ng9SiP4dIRy0EXTlnuz47vAwmeGwVChigm" crossorigin="anonymous">
  <!-- Theme style -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css" integrity="sha384-qrt37eUXKQgF1p6OlpdB29OTyKryxbxdJHkvfVN4suujWnn6PibIvbnygcK4uJfA" crossorigin="anonymous">
  <!-- Custom CSS -->
  <link rel="stylesheet" href="<?php echo defined('PUBLIC_URL_PREFIX') ? PUBLIC_URL_PREFIX : '/public'; ?>/assets/css/app.css">
  <!-- SweetAlert2 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <!-- Toastr -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

  <!-- REQUIRED SCRIPTS IN HEADER TO PREVENT $ UNDEFINED -->
  <!-- jQuery -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
  <!-- Bootstrap 4 -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/js/bootstrap.bundle.min.js"></script>
  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <!-- Select2 -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <!-- Global Search Styles -->
  <style>
    .navbar-search-block-custom .input-group:focus-within {
      border-color: #007bff !important;
      box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.15);
      background-color: #fff !important;
    }
    .dark-mode .navbar-search-block-custom .input-group:focus-within {
      background-color: #343a40 !important;
      border-color: #3f6791 !important;
    }
    #global-search-results-dropdown {
      background-color: #ffffff;
      color: #212529;
    }
    .dark-mode #global-search-results-dropdown {
      background-color: #343a40;
      color: #ffffff;
      border-color: #4b545c;
    }
    .search-group-header {
      font-size: 0.75rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: #6c757d;
      background-color: #f8f9fa;
      padding: 6px 12px;
      border-top: 1px solid #e9ecef;
    }
    .dark-mode .search-group-header {
      color: #adb5bd;
      background-color: #2b3035;
      border-top-color: #4b545c;
    }
    .search-group-header:first-of-type {
      border-top: none;
    }
    .search-item-link {
      display: flex;
      align-items: center;
      padding: 8px 12px;
      text-decoration: none !important;
      color: inherit !important;
      transition: background-color 0.15s ease;
    }
    .search-item-link:hover {
      background-color: #f1f3f5;
    }
    .dark-mode .search-item-link:hover {
      background-color: #3f474e;
    }
    .search-item-icon {
      width: 28px;
      height: 28px;
      border-radius: 4px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-right: 10px;
      font-size: 0.9rem;
    }
    .search-item-title {
      font-weight: 600;
      font-size: 0.9rem;
    }
    .search-item-meta {
      font-size: 0.75rem;
      color: #6c757d;
    }
    .dark-mode .search-item-meta {
      color: #adb5bd;
    }
  </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

  <!-- Navbar -->
  <nav class="main-header navbar navbar-expand navbar-white navbar-light border-bottom-0 shadow-sm">
    <!-- Left navbar links -->
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
      </li>
    </ul>

    <?php if (!empty($page_title)): ?>
      <span class="navbar-nav-text font-weight-bold ml-2" style="font-size: 1.25rem; align-self: center; color: inherit;">
        <?php if (!empty($page_icon)): ?>
          <i class="<?php echo htmlspecialchars($page_icon); ?> mr-2"></i>
        <?php endif; ?>
        <?php echo htmlspecialchars($page_title); ?>
      </span>
    <?php endif; ?>

    <!-- Global Search Input -->
    <div class="navbar-search-block-custom ml-3 d-none d-md-block" style="flex: 1; max-width: 420px; position: relative;">
      <div class="input-group input-group-sm bg-light rounded-pill border" style="padding: 2px 10px; transition: all 0.2s;">
        <div class="input-group-prepend border-0 bg-transparent">
          <span class="input-group-text border-0 bg-transparent text-muted"><i class="fas fa-search"></i></span>
        </div>
        <input class="form-control border-0 bg-transparent pl-1" type="search" id="global-search-input" placeholder="Buscar host, IP, puerto, CI, sigla..." autocomplete="off" style="box-shadow: none; outline: none; height: 28px;">
        <div class="input-group-append border-0 bg-transparent" id="global-search-clear-wrapper" style="display: none;">
          <button class="btn btn-sm text-muted border-0 bg-transparent" type="button" id="global-search-clear-btn" style="box-shadow: none; outline: none;"><i class="fas fa-times"></i></button>
        </div>
      </div>
      <!-- Floating results dropdown -->
      <div id="global-search-results-dropdown" class="dropdown-menu shadow-lg p-0" style="position: absolute; width: 100%; top: 38px; left: 0; display: none; max-height: 400px; overflow-y: auto; border-radius: 8px; border: 1px solid rgba(0,0,0,.15); z-index: 1050;">
        <!-- Filled dynamically -->
      </div>
    </div>


    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">
      <!-- Current Date -->
      <li class="nav-item d-none d-md-inline-block mr-3" style="align-self: center;">
        <span class="text-muted" style="cursor: default; font-size: 0.9rem; font-weight: 500;">
          <i class="far fa-calendar-alt mr-2" style="color: var(--sonda-cyan);"></i>
          <span id="navbar-current-date"></span>
        </span>
      </li>
      <script>
        $(document).ready(function() {
          function updateNavbarDate() {
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const today = new Date();
            let formattedDate = today.toLocaleDateString("es-ES", options);
            formattedDate = formattedDate.charAt(0).toUpperCase() + formattedDate.slice(1);
            $('#navbar-current-date').text(formattedDate);
          }
          updateNavbarDate();
          setInterval(updateNavbarDate, 3600000);
        });
      </script>
      <!-- Dark Mode Toggle -->
      <li class="nav-item">
        <a class="nav-link" id="dark-mode-toggle" href="#" role="button">
          <i class="fas fa-moon"></i>
        </a>
      </li>
      <li class="nav-item dropdown">
        <a class="nav-link" data-toggle="dropdown" href="#">
          <i class="far fa-user"></i>
          <?php echo htmlspecialchars($user['username'] ?? 'Usuario'); ?>
        </a>
        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
          <span class="dropdown-item dropdown-header">Sesión</span>
          <div class="dropdown-divider"></div>
          <a href="logout.php" class="dropdown-item">
            <i class="fas fa-sign-out-alt mr-2"></i> Cerrar Sesión
          </a>
        </div>
      </li>
    </ul>
  </nav>
  <!-- /.navbar -->

  <script>
    (function() {
      const theme = localStorage.getItem('theme') || 'light';
      if (theme === 'dark') {
        document.body.classList.add('dark-mode');
        const navbar = document.querySelector('.main-header');
        if (navbar) {
          navbar.classList.remove('navbar-white', 'navbar-light');
          navbar.classList.add('navbar-dark', 'navbar-primary');
        }
      }
    })();
  </script>

  <!-- Global Search JS Logic -->
  <script>
    $(document).ready(function() {
      const searchInput = $('#global-search-input');
      const clearBtn = $('#global-search-clear-btn');
      const clearWrapper = $('#global-search-clear-wrapper');
      const dropdown = $('#global-search-results-dropdown');
      let debounceTimeout = null;

      function performSearch(query) {
        if (query.length < 2) {
          dropdown.hide().empty();
          clearWrapper.hide();
          return;
        }
        clearWrapper.show();
        
        dropdown.html('<div class="p-3 text-center text-muted"><i class="fas fa-spinner fa-spin mr-2"></i>Buscando...</div>').show();

        $.ajax({
          url: 'api_search.php',
          method: 'GET',
          data: { q: query },
          dataType: 'json',
          success: function(response) {
            if (!response.success) {
              dropdown.html('<div class="p-3 text-center text-danger"><i class="fas fa-exclamation-circle mr-2"></i>Error: ' + response.error + '</div>');
              return;
            }

            const results = response.results;
            let html = '';
            let totalCount = 0;

            // Group 1: CMDB (Graph CIs)
            if (results.ci_instances && results.ci_instances.length > 0) {
              html += '<div class="search-group-header"><i class="fas fa-sitemap mr-1"></i> Estructura CMDB (CIs)</div>';
              results.ci_instances.forEach(item => {
                totalCount++;
                const icon = item.category_icon || 'fa-cube';
                const siglaBadge = item.sigla ? '<span class="badge badge-secondary ml-1">' + item.sigla + '</span>' : '';
                const ipBadge = item.ip_address ? '<span class="badge badge-primary ml-1">' + item.ip_address + '</span>' : '';
                const locText = item.location ? ' | <i class="fas fa-map-marker-alt text-danger"></i> ' + item.location : '';
                html += `
                  <a href="ci_list.php?category_id=${item.category_id}&show_ci_id=${item.id}" class="search-item-link">
                    <div class="search-item-icon bg-light text-primary"><i class="fas ${icon}"></i></div>
                    <div style="flex:1;">
                      <div class="search-item-title">${item.hostname} ${siglaBadge} ${ipBadge}</div>
                      <div class="search-item-meta">${item.category_name} | Código Único: ${item.ci_unique || 'SND-XXXXXXXXXX'}${locText}</div>
                    </div>
                  </a>
                `;
              });
            }

            // Group 2: Port Mappings
            if (results.port_mappings && results.port_mappings.length > 0) {
              html += '<div class="search-group-header"><i class="fas fa-network-wired mr-1"></i> Conectividad (Portmapping)</div>';
              results.port_mappings.forEach(item => {
                totalCount++;
                const isPower = item.connection_type === 'power';
                const icon = isPower ? 'fa-plug text-warning' : 'fa-ethernet text-success';
                const labelType = isPower ? 'Energía' : 'Red';
                
                html += `
                  <a href="portmapping.php?device_id=${item.source_device_id}" class="search-item-link">
                    <div class="search-item-icon bg-light"><i class="fas ${icon}"></i></div>
                    <div style="flex:1;">
                      <div class="search-item-title">${item.source_device} [${item.source_port}] ➔ ${item.target_device} [${item.target_port}]</div>
                      <div class="search-item-meta">${labelType} | Ubicación: ${item.location} | Obs: ${item.notes || '-'}</div>
                    </div>
                  </a>
                `;
              });
            }

            // Group 3: PRECMDB (Spreadsheet tables)
            if (results.precmdb_equipos && results.precmdb_equipos.length > 0) {
              html += '<div class="search-group-header"><i class="fas fa-table mr-1"></i> Equipos / Activos (PRECMDB)</div>';
              results.precmdb_equipos.forEach(item => {
                totalCount++;
                const tableNice = item.table_name.replace('sheet_', '').replace(/_/g, ' ').toUpperCase();
                const codeBadge = item.code ? '<span class="badge badge-dark ml-1">' + item.code + '</span>' : '';
                const ipBadge = item.ip_address ? '<span class="badge badge-info ml-1">' + item.ip_address + '</span>' : '';
                const locText = item.location ? ' | <i class="fas fa-map-marker-alt text-danger"></i> ' + item.location : '';
                html += `
                  <a href="item_detail.php?table=${item.table_name}&id=${item.id}" class="search-item-link">
                    <div class="search-item-icon bg-light text-secondary"><i class="fas fa-file-alt"></i></div>
                    <div style="flex:1;">
                      <div class="search-item-title">${item.display_name} ${codeBadge} ${ipBadge}</div>
                      <div class="search-item-meta">Hoja: ${tableNice}${locText}</div>
                    </div>
                  </a>
                `;
              });
            }

            if (totalCount === 0) {
              dropdown.html('<div class="p-3 text-center text-muted"><i class="fas fa-search-minus mr-2"></i>No se encontraron resultados</div>');
            } else {
              dropdown.html(html);
            }
          },
          error: function() {
            dropdown.html('<div class="p-3 text-center text-danger"><i class="fas fa-times-circle mr-2"></i>Error al consultar el servidor</div>');
          }
        });
      }

      searchInput.on('input focus', function(e) {
        const val = $(this).val().trim();
        
        if (debounceTimeout) clearTimeout(debounceTimeout);
        
        if (val.length < 2) {
          dropdown.hide().empty();
          clearWrapper.hide();
          return;
        }
        
        clearWrapper.show();
        
        debounceTimeout = setTimeout(() => {
          performSearch(val);
        }, 250);
      });

      clearBtn.on('click', function() {
        searchInput.val('').focus();
        dropdown.hide().empty();
        clearWrapper.hide();
      });

      // Hide dropdown on click outside
      $(document).on('click', function(e) {
        if (!$(e.target).closest('.navbar-search-block-custom').length) {
          dropdown.hide();
        }
      });
    });
  </script>

  <?php include __DIR__ . '/sidebar.php'; ?>

  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <?php if (empty($hide_content_header)): ?>
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <?php endif; ?>

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">

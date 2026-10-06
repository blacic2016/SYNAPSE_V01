<?php
/**
 * NovaIOPS Dashboard - CMDB VILASECA
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/helpers.php';

require_login();

// Check if user has permission
if (!has_role('SUPER_ADMIN') && !has_module_access('novaiops_dashboard')) {
    die("No tienes permisos para acceder a esta página.");
}

$page_title = 'NovaIOPS Dashboard';
$hide_content_header = true;
require_once __DIR__ . '/partials/header.php';
?>

<!-- Tailwind CSS Local (Loaded from Self to bypass CSP limits and work offline) -->
<script src="<?php echo defined('PUBLIC_URL_PREFIX') ? PUBLIC_URL_PREFIX : '/public'; ?>/assets/js/tailwind.js"></script>
<script>
  // Tailor Tailwind to not clash with AdminLTE body classes
  tailwind.config = {
    corePlugins: {
      preflight: false,
    },
    theme: {
      extend: {
        colors: {
          sondaNavy: '#0f172a',
          sondaIndigo: '#4f46e5',
        }
      }
    }
  }
</script>

<!-- Libraries CDNs in Head/Body -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">

<!-- PivotTable.js CSS & jQuery UI CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/base/jquery-ui.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/pivottable/2.23.0/pivot.min.css">

<!-- Modern UI Custom Styles -->
<style>
    /* Scoped styling to avoid breaking AdminLTE global styling */
    #novaiops-scope {
        font-family: 'Kumbh Sans', 'Inter', sans-serif;
    }
    
    /* Scoped base button resets so they do not look like default browser buttons */
    #novaiops-scope button {
        border: 0;
        margin: 0;
        padding: 0;
        background: transparent;
        font-family: inherit;
        font-size: inherit;
        line-height: inherit;
        cursor: pointer;
        outline: none;
    }
    
    #novaiops-scope .grid-custom-2-1 {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 1.5rem;
    }
    @media (max-width: 991px) {
        #novaiops-scope .grid-custom-2-1 {
            grid-template-columns: 1fr;
        }
    }
    
    #novaiops-scope .grid-custom-1-2 {
        display: grid;
        grid-template-columns: 1fr 2fr;
        gap: 1.5rem;
    }
    @media (max-width: 991px) {
        #novaiops-scope .grid-custom-1-2 {
            grid-template-columns: 1fr;
        }
    }

    @media (min-width: 992px) {
        #novaiops-scope .col-span-2-custom {
            grid-column: span 2 / span 2 !important;
        }
    }
    
    #novaiops-scope .grid-custom-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
    }
    @media (max-width: 991px) {
        #novaiops-scope .grid-custom-2 {
            grid-template-columns: 1fr;
        }
    }
    
    #novaiops-scope .grid-custom-4 {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1.5rem;
    }
    @media (max-width: 1199px) {
        #novaiops-scope .grid-custom-4 {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    @media (max-width: 575px) {
        #novaiops-scope .grid-custom-4 {
            grid-template-columns: 1fr;
        }
    }
    
    #novaiops-scope .grid-custom-6 {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 1rem;
    }
    @media (max-width: 1199px) {
        #novaiops-scope .grid-custom-6 {
            grid-template-columns: repeat(3, 1fr);
        }
    }
    @media (max-width: 767px) {
        #novaiops-scope .grid-custom-6 {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    @media (max-width: 480px) {
        #novaiops-scope .grid-custom-6 {
            grid-template-columns: 1fr;
        }
    }

    /* Custom Premium Card styling */
    #novaiops-scope .bi-card {
        background-color: #ffffff !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 0.75rem !important;
        padding: 1.5rem !important;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px 0 rgba(0, 0, 0, 0.03) !important;
    }
    .dark-mode #novaiops-scope .bi-card {
        background-color: #182235 !important;
        border-color: #334155 !important;
        box-shadow: none !important;
    }
    
    #novaiops-scope .bi-filter-bar {
        background-color: #f8fafc !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 0.75rem !important;
        padding: 1.5rem !important;
    }
    .dark-mode #novaiops-scope .bi-filter-bar {
        background-color: #0f172a !important;
        border-color: #334155 !important;
    }
    
    #novaiops-scope select.bi-select {
        background-color: #ffffff !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 0.375rem !important;
        padding: 0.5rem !important;
        font-size: 0.75rem !important;
        width: 100% !important;
        color: #0f172a !important;
        outline: none;
    }
    .dark-mode #novaiops-scope select.bi-select {
        background-color: #1e293b !important;
        border-color: #475569 !important;
        color: #f8fafc !important;
    }

    #novaiops-scope input.bi-search-input {
        background-color: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 0.375rem;
        padding: 0.45rem 1.8rem 0.45rem 0.5rem;
        font-size: 0.75rem;
        width: 100%;
        color: #0f172a;
        outline: none;
        box-sizing: border-box;
        transition: border-color 0.15s, box-shadow 0.15s;
    }
    #novaiops-scope input.bi-search-input:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 2px rgba(99,102,241,0.18);
    }
    #novaiops-scope input.bi-search-input::placeholder {
        color: #94a3b8;
    }
    .dark-mode #novaiops-scope input.bi-search-input {
        background-color: #1e293b;
        border-color: #475569;
        color: #f8fafc;
    }
    .dark-mode #novaiops-scope input.bi-search-input::placeholder {
        color: #64748b;
    }

    #novaiops-scope .kpi-card {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(226, 232, 240, 0.8);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    #novaiops-scope .kpi-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 20px -8px rgba(0, 0, 0, 0.08);
    }
    .dark-mode #novaiops-scope .kpi-card {
        background: rgba(30, 41, 59, 0.85);
        border: 1px solid rgba(71, 85, 105, 0.5);
    }
    
    #novaiops-scope .tab-btn {
        background: transparent !important;
        border: none !important;
        border-bottom: 2px solid transparent !important;
        padding: 0.75rem 1rem !important;
        color: #6b7280 !important;
        font-weight: 500 !important;
        transition: all 0.2s ease;
        border-radius: 0 !important;
    }
    #novaiops-scope .tab-btn:hover {
        color: #374151 !important;
    }
    #novaiops-scope .tab-btn.active {
        border-bottom-color: #4f46e5 !important;
        color: #4f46e5 !important;
        font-weight: 600 !important;
    }
    .dark-mode #novaiops-scope .tab-btn {
        color: #9ca3af !important;
    }
    .dark-mode #novaiops-scope .tab-btn:hover {
        color: #e5e7eb !important;
    }
    .dark-mode #novaiops-scope .tab-btn.active {
        border-bottom-color: #818cf8 !important;
        color: #818cf8 !important;
    }
    
    /* Dropzone custom style */
    #novaiops-scope #dropzone {
        border: 2px dashed #cbd5e1 !important;
        background-color: #f8fafc !important;
        border-radius: 0.5rem !important;
        padding: 1.5rem !important;
        text-align: center !important;
        cursor: pointer !important;
        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;
        justify-content: center !important;
        transition: border-color 0.2s, background-color 0.2s;
    }
    #novaiops-scope #dropzone:hover {
        border-color: #4f46e5 !important;
        background-color: #f1f5f9 !important;
    }
    .dark-mode #novaiops-scope #dropzone {
        border-color: #475569 !important;
        background-color: #1e293b !important;
    }
    .dark-mode #novaiops-scope #dropzone:hover {
        border-color: #818cf8 !important;
        background-color: #334155 !important;
    }
    
    /* Pivot table design overrides for premium feel */
    .pvtUi {
        width: 100% !important;
        border-collapse: collapse;
        color: inherit;
    }
    .pvtVals, .pvtCols, .pvtRows, .pvtAxisContainer {
        background: #f8fafc !important;
        border: 1px solid #e2e8f0 !important;
        padding: 8px !important;
        border-radius: 6px;
    }
    .dark-mode .pvtVals, .dark-mode .pvtCols, .dark-mode .pvtRows, .dark-mode .pvtAxisContainer {
        background: #1e293b !important;
        border: 1px solid #475569 !important;
    }
    .pvtAttr {
        background: #ffffff !important;
        border: 1px solid #cbd5e1 !important;
        padding: 4px 10px !important;
        border-radius: 4px !important;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        color: #0f172a !important;
    }
    .dark-mode .pvtAttr {
        background: #334155 !important;
        border: 1px solid #475569 !important;
        color: #f8fafc !important;
    }
    table.pvtTable {
        width: 100%;
        border: 1px solid #e2e8f0 !important;
    }
    table.pvtTable thead tr th, table.pvtTable tbody tr th {
        background: #f1f5f9 !important;
        border: 1px solid #cbd5e1 !important;
        font-weight: 600;
        padding: 6px 10px;
        color: #0f172a;
    }
    .dark-mode table.pvtTable thead tr th, .dark-mode table.pvtTable tbody tr th {
        background: #334155 !important;
        border: 1px solid #475569 !important;
        color: #f8fafc;
    }
    table.pvtTable tbody tr td {
        border: 1px solid #cbd5e1 !important;
        padding: 6px 10px;
        color: inherit;
    }
    .dark-mode table.pvtTable tbody tr td {
        border: 1px solid #475569 !important;
    }
    
    /* =============================================
       TAB VISIBILITY SYSTEM
       ============================================= */
    /* Base: all tabs hidden via CSS - JS will set inline style on the active one */
    .tab-content {
        display: none;
    }

    /* DataTable customization */
    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background: #4f46e5 !important;
        color: white !important;
        border: 1px solid #4f46e5 !important;
        border-radius: 4px;
    }
    .dark-mode .dataTables_wrapper .dataTables_paginate .paginate_button {
        color: #cbd5e1 !important;
    }
    .dark-mode table.dataTable tbody tr {
        background-color: #1e293b !important;
        color: #f8fafc !important;
    }
    .dark-mode table.dataTable thead th {
        border-bottom: 2px solid #475569 !important;
        color: #f8fafc !important;
    }
    .dark-mode .dataTables_wrapper .dataTables_filter input,
    .dark-mode .dataTables_wrapper .dataTables_length select {
        background-color: #334155 !important;
        color: #f8fafc !important;
        border: 1px solid #475569 !important;
        border-radius: 4px;
        padding: 4px;
    }
</style>

<div id="novaiops-scope" class="w-full pb-8">
    <!-- Header with Sync Info -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-end mb-6 gap-4">
        <div class="flex items-center gap-3">
            <span id="sync-status" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">
                <span class="w-1.5 h-1.5 mr-1.5 rounded-full bg-red-500 animate-pulse"></span> Sin Datos
            </span>
            <button onclick="openResetModal()" class="text-xs text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 font-medium">
                <i class="fas fa-trash-alt mr-1"></i> Reiniciar Base de Datos
            </button>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="border-b border-gray-200 dark:border-slate-700 mb-6">
        <nav class="-mb-px flex space-x-8 overflow-x-auto" aria-label="Tabs">
            <button onclick="switchTab('tab-adquisicion')" id="btn-tab-adquisicion" class="tab-btn active py-4 px-1 text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
                <i class="fas fa-file-excel mr-2"></i> 1. Adquisición de Reporte
            </button>
            <button onclick="switchTab('tab-tabla-maestra')" id="btn-tab-tabla-maestra" class="tab-btn py-4 px-1 text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
                <i class="fas fa-database mr-2"></i> 2. Filtros y Tabla Maestra
            </button>
            <button onclick="switchTab('tab-dashboard')" id="btn-tab-dashboard" class="tab-btn py-4 px-1 text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
                <i class="fas fa-chart-pie mr-2"></i> 3. Dashboard Gerencial
            </button>
            <button onclick="switchTab('tab-pivot')" id="btn-tab-pivot" class="tab-btn py-4 px-1 text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
                <i class="fas fa-th mr-2"></i> 4. Matriz Pivot UI
            </button>
            <button onclick="switchTab('tab-analitica')" id="btn-tab-analitica" class="tab-btn py-4 px-1 text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
                <i class="fas fa-business-time mr-2"></i> 5. Rendimiento de Tiempos
            </button>
            <button onclick="switchTab('tab-tickets')" id="btn-tab-tickets" class="tab-btn py-4 px-1 text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
                <i class="fas fa-ticket-alt mr-2"></i> 6. Análisis Ticket
            </button>
            <button onclick="switchTab('tab-powerbi')" id="btn-tab-powerbi" class="tab-btn py-4 px-1 text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
                <i class="fas fa-chart-line mr-2"></i> 7. Vista PowerBI
            </button>
            <button onclick="switchTab('tab-especialistas')" id="btn-tab-especialistas" class="tab-btn py-4 px-1 text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
                <i class="fas fa-user-tie mr-2"></i> 8. Ocupación Especialistas
            </button>
            <button onclick="switchTab('tab-horas-recargos')" id="btn-tab-horas-recargos" class="tab-btn py-4 px-1 text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
                <i class="fas fa-calculator mr-2 text-indigo-500"></i> 9. Cálculo Horas y Saltos (Recargos)
            </button>
            <button onclick="switchTab('tab-cumplimiento')" id="btn-tab-cumplimiento" class="tab-btn py-4 px-1 text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
                <i class="fas fa-user-check mr-2 text-emerald-500"></i> 10. Control de Cumplimiento Diario
            </button>
        </nav>
    </div>

    <!-- BARRA DE FILTROS ASOCIATIVOS BI GLOBAL (Visible en todas las pestañas) -->
    <div class="bi-filter-bar" style="margin-bottom:1.5rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
            <h3 style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#374151;display:flex;align-items:center;gap:.5rem;margin:0;">
                <i class="fas fa-filter" style="color:#6366f1;"></i> Barra de Filtros Asociativos BI
            </h3>
            <button onclick="clearAllFilters()" style="font-size:0.75rem;color:#4f46e5;font-weight:600;border:none;background:none;cursor:pointer;padding:0;">
                Limpiar Filtros <i class="fas fa-sync-alt" style="margin-left:.25rem;"></i>
            </button>
        </div>
        <div class="grid-custom-6">
            <!-- Cliente Selector -->
            <div>
                <label style="display:block;font-size:.7rem;font-weight:600;color:#64748b;margin-bottom:.25rem;">Cliente</label>
                <select id="select-cliente" class="bi-select">
                    <option value="">Cargando...</option>
                </select>
            </div>
            <!-- Contrato Selector -->
            <div>
                <label style="display:block;font-size:.7rem;font-weight:600;color:#64748b;margin-bottom:.25rem;">Contrato</label>
                <select id="select-contrato" class="bi-select">
                    <option value="">Cargando...</option>
                </select>
            </div>
            <!-- Servicio Selector -->
            <div>
                <label style="display:block;font-size:.7rem;font-weight:600;color:#64748b;margin-bottom:.25rem;">Servicio</label>
                <select id="select-servicio" class="bi-select">
                    <option value="">Cargando...</option>
                </select>
            </div>
            <!-- Responsable Selector -->
            <div>
                <label style="display:block;font-size:.7rem;font-weight:600;color:#64748b;margin-bottom:.25rem;">Personal Asignado</label>
                <select id="select-responsable" class="bi-select">
                    <option value="">Cargando...</option>
                </select>
            </div>
            <!-- Estado Selector -->
            <div>
                <label style="display:block;font-size:.7rem;font-weight:600;color:#64748b;margin-bottom:.25rem;">Estado</label>
                <select id="select-estado" class="bi-select">
                    <option value="">Cargando...</option>
                </select>
            </div>
            <!-- Criticidad Selector -->
            <div>
                <label style="display:block;font-size:.7rem;font-weight:600;color:#64748b;margin-bottom:.25rem;">Criticidad</label>
                <select id="select-criticidad" class="bi-select">
                    <option value="">Cargando...</option>
                </select>
            </div>
        </div>
        <!-- FILA 2: Búsquedas de texto -->
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.75rem;margin-top:.75rem;padding-top:.75rem;border-top:1px dashed #e2e8f0;">
            <!-- Búsqueda General -->
            <div style="position:relative;">
                <label style="display:block;font-size:.7rem;font-weight:600;color:#64748b;margin-bottom:.25rem;">
                    <i class="fas fa-search" style="color:#6366f1;margin-right:.3rem;"></i>Búsqueda General
                </label>
                <input type="text" id="search-general" placeholder="Buscar en toda la base de datos..." class="bi-search-input" oninput="onSearchInput()">
                <span id="search-general-clear" onclick="clearSearchInput('search-general')" style="position:absolute;right:.5rem;top:2.1rem;cursor:pointer;color:#94a3b8;font-size:.7rem;display:none;"><i class="fas fa-times-circle"></i></span>
            </div>
            <!-- Búsqueda por Referencia / Ticket -->
            <div style="position:relative;">
                <label style="display:block;font-size:.7rem;font-weight:600;color:#64748b;margin-bottom:.25rem;">
                    <i class="fas fa-hashtag" style="color:#0369a1;margin-right:.3rem;"></i>Referencia / Ticket
                </label>
                <input type="text" id="search-referencia" placeholder="Ej: REF-001, SONDA0015..." class="bi-search-input" oninput="onSearchInput()">
                <span id="search-referencia-clear" onclick="clearSearchInput('search-referencia')" style="position:absolute;right:.5rem;top:2.1rem;cursor:pointer;color:#94a3b8;font-size:.7rem;display:none;"><i class="fas fa-times-circle"></i></span>
            </div>
            <!-- Búsqueda por Título / Descripción -->
            <div style="position:relative;">
                <label style="display:block;font-size:.7rem;font-weight:600;color:#64748b;margin-bottom:.25rem;">
                    <i class="fas fa-align-left" style="color:#16a34a;margin-right:.3rem;"></i>Título / Descripción
                </label>
                <input type="text" id="search-titulo" placeholder="Buscar por título o descripción..." class="bi-search-input" oninput="onSearchInput()">
                <span id="search-titulo-clear" onclick="clearSearchInput('search-titulo')" style="position:absolute;right:.5rem;top:2.1rem;cursor:pointer;color:#94a3b8;font-size:.7rem;display:none;"><i class="fas fa-times-circle"></i></span>
            </div>
        </div>
    </div><!-- /.bi-filter-bar GLOBAL -->

    <!-- TAB 1: Adquisición de Datos y Filtros Asociativos -->
    <div id="tab-adquisicion" class="tab-content tab-active animate__animated animate__fadeIn">
        <!-- Top Panel: Upload & Config -->
        <div class="grid-custom-2-1 mb-6">
            <!-- File Dropzone -->
            <div class="bi-card flex flex-col justify-between">
                <div>
                    <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 mb-2">Adquisición del Reporte de Tareas</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">Sube un archivo Excel (.xlsx) que contenga las 3 pestañas requeridas ("Reporte tareas", "Seguimientos", "Información reportes"). El sistema identificará y actualizará la base de datos de manera incremental.</p>
                </div>
                
                <div class="flex flex-col sm:flex-row gap-4 items-stretch">
                    <!-- Dropzone area -->
                    <div id="dropzone" class="flex-1">
                        <input type="file" id="file-input" style="display: none;" accept=".xlsx,.xls">
                        <i class="fas fa-cloud-upload-alt text-4xl text-slate-400 dark:text-slate-500 mb-2 animate-bounce"></i>
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">Arrastra o haz clic para subir</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Soporta .xlsx, .xls (Max 15MB)</p>
                    </div>
 
                    <!-- Fast Load Default File Button -->
                    <div class="flex flex-col justify-center gap-3">
                        <button onclick="loadDefaultExcel()" id="btn-load-default" class="btn-indigo flex items-center justify-center gap-2">
                            <i class="fas fa-magic"></i> Cargar Archivo por Defecto
                        </button>
                        <p class="text-xs text-slate-500 dark:text-slate-400 text-center max-w-[200px] mx-auto">Carga el reporte de tareas guardado en la carpeta <code>cotizador/novaiops/</code></p>
                    </div>
                </div>

                <!-- Staged File Panel (Temporary view before DB commit) -->
                <div id="staged-file-card" class="mt-4 p-4 border border-indigo-100 rounded-lg bg-indigo-50/30 dark:bg-slate-800/40" style="display:none;">
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                                <i class="fas fa-file-excel text-lg"></i>
                            </div>
                            <div>
                                <h4 class="text-sm font-semibold text-slate-800 dark:text-slate-200" id="staged-filename">reporte_tareas.xlsx</h4>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Listo para analizar o confirmar la subida.</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button onclick="analyzeStagedFile()" id="btn-analyze-stage" class="btn btn-sm btn-primary flex items-center gap-1.5" style="background-color: #4f46e5 !important; border-color: #4f46e5 !important; color: #ffffff !important; font-weight: 600;">
                                <i class="fas fa-search-plus"></i> Analizar
                            </button>
                            <button onclick="confirmStagedFile()" id="btn-confirm-stage" class="btn btn-sm btn-success flex items-center gap-1.5" style="background-color: #10b981 !important; border-color: #10b981 !important; color: #ffffff !important; font-weight: 600;">
                                <i class="fas fa-check-circle"></i> OK (Subir)
                            </button>
                            <button onclick="cancelStagedFile()" id="btn-cancel-stage" class="btn btn-sm btn-secondary flex items-center gap-1" style="background-color: #6b7280 !important; border-color: #6b7280 !important; color: #ffffff !important; font-weight: 500;">
                                <i class="fas fa-times"></i> Cancelar
                            </button>
                        </div>
                    </div>
                    
                    <!-- Analysis results sub-panel -->
                    <div id="analysis-results-panel" style="display:none;" class="p-3 border border-emerald-100 dark:border-emerald-900/30 rounded bg-emerald-50/20 dark:bg-emerald-950/10">
                        <h5 class="text-xs font-bold text-emerald-800 dark:text-emerald-400 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                            <i class="fas fa-chart-line"></i> Resultado del Análisis
                        </h5>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs mb-3">
                            <!-- Reporte Tareas -->
                            <div class="p-2.5 rounded bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 shadow-sm">
                                <p class="font-semibold text-slate-700 dark:text-slate-300 border-b border-slate-100 dark:border-slate-800 pb-1 mb-1.5 flex justify-between gap-1">
                                    <span class="truncate">Reporte tareas (Tickets)</span>
                                    <span class="text-indigo-600 dark:text-indigo-400 font-bold shrink-0" id="analysis-tickets-total">0 rows</span>
                                </p>
                                <div class="flex justify-between items-center mb-1">
                                    <span class="text-slate-500">Filas Nuevas:</span>
                                    <span class="px-1.5 py-0.5 rounded bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-400 font-bold" id="analysis-tickets-new">+0</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-slate-500">Filas Repetidas:</span>
                                    <span class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400" id="analysis-tickets-repeated">0</span>
                                </div>
                            </div>
                            <!-- Seguimientos -->
                            <div class="p-2.5 rounded bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 shadow-sm">
                                <p class="font-semibold text-slate-700 dark:text-slate-300 border-b border-slate-100 dark:border-slate-800 pb-1 mb-1.5 flex justify-between gap-1">
                                    <span class="truncate">Seguimientos</span>
                                    <span class="text-indigo-600 dark:text-indigo-400 font-bold shrink-0" id="analysis-seguimientos-total">0 rows</span>
                                </p>
                                <div class="flex justify-between items-center mb-1">
                                    <span class="text-slate-500">Filas Nuevas:</span>
                                    <span class="px-1.5 py-0.5 rounded bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-400 font-bold" id="analysis-seguimientos-new">+0</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-slate-500">Filas Repetidas:</span>
                                    <span class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400" id="analysis-seguimientos-repeated">0</span>
                                </div>
                            </div>
                            <!-- Informacion Reportes -->
                            <div class="p-2.5 rounded bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 shadow-sm">
                                <p class="font-semibold text-slate-700 dark:text-slate-300 border-b border-slate-100 dark:border-slate-800 pb-1 mb-1.5 flex justify-between gap-1">
                                    <span class="truncate">Información reportes</span>
                                    <span class="text-indigo-600 dark:text-indigo-400 font-bold shrink-0" id="analysis-info-total">0 rows</span>
                                </p>
                                <div class="flex justify-between items-center mb-1">
                                    <span class="text-slate-500">Filas Nuevas:</span>
                                    <span class="px-1.5 py-0.5 rounded bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-400 font-bold" id="analysis-info-new">+0</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-slate-500">Filas Repetidas:</span>
                                    <span class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400" id="analysis-info-repeated">0</span>
                                </div>
                            </div>
                        </div>
                        <p class="text-xs text-slate-600 dark:text-slate-400 font-medium">
                            <i class="fas fa-info-circle text-indigo-500"></i>
                            El análisis valida la integridad de las columnas y el volumen incremental de datos. Para registrar la importación, haga clic en el botón de arriba <b>OK (Subir)</b>.
                        </p>
                    </div>
                </div>
            </div>
 
            <!-- Upload History Logs -->
            <div class="bi-card flex flex-col">
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-3"><i class="fas fa-history mr-2"></i> Historial de Subidas</h3>
                <div class="flex-1 overflow-y-auto max-h-[180px]">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-slate-400 border-b border-slate-100 dark:border-slate-700 pb-1">
                                <th class="pb-2 font-medium">Archivo</th>
                                <th class="pb-2 font-medium">Fecha</th>
                                <th class="pb-2 font-medium text-center">Filas Nuevas</th>
                                <th class="pb-2 font-medium text-right">Filas Antiguas</th>
                            </tr>
                        </thead>
                        <tbody id="upload-history-list">
                            <tr>
                                <td colspan="4" class="py-4 text-center text-slate-400">Sin historial registrado</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div><!-- /.tab-adquisicion -->

    <!-- TAB 2: Filtros y Tabla Maestra -->
    <div id="tab-tabla-maestra" class="tab-content animate__animated animate__fadeIn">
        <!-- Master Table Container -->
        <div class="bi-card">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">Tabla Maestra de Tareas</h3>
                <span id="table-row-count" class="text-xs text-slate-500 dark:text-slate-400">Mostrando 0 de 0 registros</span>
            </div>
            <div class="overflow-x-auto">
                <table id="master-table" class="display compact w-full text-xs text-left">
                    <!-- Column headers populated dynamically -->
                </table>
            </div>
        </div>
    </div><!-- /.tab-tabla-maestra -->

    <!-- TAB 2: Dashboard Gerencial Multidimensional -->
    <div id="tab-dashboard" class="tab-content animate__animated animate__fadeIn">
        <!-- KPI Cards Grid -->
        <div class="grid-custom-4" style="margin-bottom:1.5rem;">
            <!-- KPI 1 -->
            <div class="kpi-card" style="padding:1.5rem;border-radius:.75rem;display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <p style="font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:#64748b;margin:0 0 .25rem;">Casos Totales</p>
                    <h3 id="kpi-total-casos" style="font-size:1.75rem;font-weight:700;color:#1e293b;margin:0;">0</h3>
                </div>
                <div style="width:3rem;height:3rem;border-radius:.5rem;background:#e0e7ff;display:flex;align-items:center;justify-content:center;color:#4f46e5;font-size:1.25rem;">
                    <i class="fas fa-ticket-alt"></i>
                </div>
            </div>
            <!-- KPI 2 -->
            <div class="kpi-card" style="padding:1.5rem;border-radius:.75rem;display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <p style="font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:#64748b;margin:0 0 .25rem;">Tiempo Invertido</p>
                    <h3 id="kpi-total-tiempo" style="font-size:1.75rem;font-weight:700;color:#1e293b;margin:0;">0h 0m</h3>
                </div>
                <div style="width:3rem;height:3rem;border-radius:.5rem;background:#d1fae5;display:flex;align-items:center;justify-content:center;color:#059669;font-size:1.25rem;">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            <!-- KPI 3 -->
            <div class="kpi-card" style="padding:1.5rem;border-radius:.75rem;display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <p style="font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:#64748b;margin:0 0 .25rem;">Promedio de Atención</p>
                    <h3 id="kpi-promedio-tiempo" style="font-size:1.75rem;font-weight:700;color:#1e293b;margin:0;">0m</h3>
                </div>
                <div style="width:3rem;height:3rem;border-radius:.5rem;background:#fef3c7;display:flex;align-items:center;justify-content:center;color:#d97706;font-size:1.25rem;">
                    <i class="fas fa-hourglass-half"></i>
                </div>
            </div>
            <!-- KPI 4 -->
            <div class="kpi-card" style="padding:1.5rem;border-radius:.75rem;display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <p style="font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:#64748b;margin:0 0 .25rem;">% Completado</p>
                    <h3 id="kpi-porcentaje-completado" style="font-size:1.75rem;font-weight:700;color:#1e293b;margin:0;">0%</h3>
                </div>
                <div style="width:3rem;height:3rem;border-radius:.5rem;background:#ffe4e6;display:flex;align-items:center;justify-content:center;color:#e11d48;font-size:1.25rem;">
                    <i class="fas fa-check-double"></i>
                </div>
            </div>
        </div>
        <!-- Charts Grid -->
        <div class="grid-custom-2" style="margin-bottom:1.5rem;">
            <!-- Chart 1: Top 10 Clientes con más carga de tiempo -->
            <div class="bi-card">
                <h3 style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#374151;margin-bottom:1rem;"><i class="fas fa-building" style="margin-right:.5rem;color:#6366f1;"></i> Top 10 Clientes (Tiempo Acumulado)</h3>
                <div style="position:relative;height:300px;width:100%;">
                    <canvas id="chart-top-clientes"></canvas>
                </div>
            </div>
            <!-- Chart 2: Distribución por Criticidad -->
            <div class="bi-card">
                <h3 style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#374151;margin-bottom:1rem;"><i class="fas fa-exclamation-triangle" style="margin-right:.5rem;color:#6366f1;"></i> Distribución por Criticidad</h3>
                <div style="position:relative;height:300px;width:100%;">
                    <canvas id="chart-criticidad"></canvas>
                </div>
            </div>
            <!-- Chart 3: Stacked Bar Chart Personal vs Horas -->
            <div class="bi-card col-span-2-custom">
                <h3 style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#374151;margin-bottom:1rem;"><i class="fas fa-users-cog" style="margin-right:.5rem;color:#6366f1;"></i> Carga de Trabajo de Personal (Horas ejecutadas vs. Servicio)</h3>
                <div style="position:relative;height:400px;width:100%;">
                    <canvas id="chart-personal-servicio"></canvas>
                </div>
            </div>
            <!-- Chart 4: Tendencia Temporal -->
            <div class="bi-card col-span-2-custom">
                <h3 style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#374151;margin-bottom:1rem;"><i class="fas fa-chart-line" style="margin-right:.5rem;color:#6366f1;"></i> Tendencia Temporal (Ingreso de Tareas)</h3>
                <div style="position:relative;height:300px;width:100%;">
                    <canvas id="chart-tendencia"></canvas>
                </div>
            </div>
        </div>
    </div>
 
    <!-- TAB 3: Matriz Pivot UI (Arrastrar y Soltar) -->
    <div id="tab-pivot" class="tab-content animate__animated animate__fadeIn">
        <div class="bi-card">
            <div style="margin-bottom:1rem;">
                <h3 style="font-size:1rem;font-weight:700;color:#1e293b;margin-bottom:.5rem;">Matriz Dinámica Interactiva (PivotTable.js)</h3>
                <p style="font-size:0.875rem;color:#64748b;">Arrastra y suelta campos para estructurar tus reportes cruzados. Los datos se actualizan dinámicamente según la barra de filtros de la pestaña 1.</p>
            </div>
            <!-- PivotTable Render Node -->
            <div style="overflow-x:auto;width:100%;">
                <div id="pivot-container" style="min-width:800px;padding:.5rem;background:#f8fafc;border-radius:.5rem;">
                    <!-- Rendered by PivotTable.js -->
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 4: Analítica Operativa y Rendimiento de Tiempos -->
    <div id="tab-analitica" class="tab-content animate__animated animate__fadeIn">
        <div class="grid-custom-1-2" style="margin-bottom:1.5rem;">
            <!-- Left Side: Time Split comparison -->
            <div class="bi-card">
                <h3 style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#374151;margin-bottom:1rem;">
                    <i class="fas fa-clock" style="margin-right:.5rem;color:#6366f1;"></i> Distribución de Tiempos (Prorrateo)
                </h3>
                <div style="position:relative;height:250px;margin-bottom:1rem;display:flex;justify-content:center;align-items:center;">
                    <canvas id="chart-prorrateo"></canvas>
                </div>
                <div style="margin-top:1rem;font-size:0.75rem;">
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:.5rem;border-radius:.25rem;background:#f8fafc;margin-bottom:.5rem;">
                        <span style="display:flex;align-items:center;gap:.375rem;"><span style="width:.75rem;height:.75rem;border-radius:50%;background:#0ea5e9;display:inline-block;"></span> Tiempo Estándar</span>
                        <span id="lbl-tiempo-standard" style="font-weight:700;color:#374151;">0h 0m</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:.5rem;border-radius:.25rem;background:#f8fafc;margin-bottom:.5rem;">
                        <span style="display:flex;align-items:center;gap:.375rem;"><span style="width:.75rem;height:.75rem;border-radius:50%;background:#7c3aed;display:inline-block;"></span> Tiempo Nocturno</span>
                        <span id="lbl-tiempo-nocturno" style="font-weight:700;color:#374151;">0h 0m</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:.5rem;border-radius:.25rem;background:#f8fafc;">
                        <span style="display:flex;align-items:center;gap:.375rem;"><span style="width:.75rem;height:.75rem;border-radius:50%;background:#f59e0b;display:inline-block;"></span> Tiempo Fin de Semana</span>
                        <span id="lbl-tiempo-fin-semana" style="font-weight:700;color:#374151;">0h 0m</span>
                    </div>
                </div>
            </div>
 
            <!-- Right Side: Personnel Performance scoring table -->
            <div class="bi-card">
                <h3 style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#374151;margin-bottom:1rem;">
                    <i class="fas fa-medal" style="margin-right:.5rem;color:#6366f1;"></i> Scoring de Rendimiento por Personal
                </h3>
                <p style="font-size:0.75rem;color:#64748b;margin-bottom:1rem;">
                    Evalúa la eficiencia de los técnicos basándose en el total de tareas asignadas frente a la desviación de sus tiempos medios de ejecución contra el promedio del equipo.
                </p>
                <div style="overflow-x:auto;">
                    <table style="width:100%;text-align:left;font-size:0.75rem;border-collapse:collapse;">
                        <thead>
                            <tr style="color:#94a3b8;border-bottom:2px solid #e2e8f0;">
                                <th style="padding-bottom:.75rem;font-weight:600;">Técnico</th>
                                <th style="padding-bottom:.75rem;font-weight:600;text-align:center;">Tareas</th>
                                <th style="padding-bottom:.75rem;font-weight:600;text-align:right;">Tiempo Total</th>
                                <th style="padding-bottom:.75rem;font-weight:600;text-align:right;">Tiempo Medio</th>
                                <th style="padding-bottom:.75rem;font-weight:600;text-align:right;">Desviación vs. Promedio</th>
                                <th style="padding-bottom:.75rem;font-weight:600;text-align:center;">Eficiencia</th>
                            </tr>
                        </thead>
                        <tbody id="tech-scoring-list">
                            <!-- Populated in JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 5: Análisis de Ticket (Ciclo de Vida ITIL) -->
    <div id="tab-tickets" class="tab-content animate__animated animate__fadeIn">

        <!-- KPI Row -->
        <div class="grid-custom-4" style="margin-bottom:1.5rem;">
            <div class="kpi-card" style="padding:1.25rem;border-radius:.75rem;display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <p style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;margin:0 0 .2rem;">Tickets Padre</p>
                    <h3 id="kpi-tk-padre" style="font-size:1.6rem;font-weight:800;color:#0369a1;margin:0;">0</h3>
                </div>
                <div style="width:2.75rem;height:2.75rem;border-radius:.5rem;background:#e0f2fe;display:flex;align-items:center;justify-content:center;color:#0369a1;font-size:1.1rem;">
                    <i class="fas fa-layer-group"></i>
                </div>
            </div>
            <div class="kpi-card" style="padding:1.25rem;border-radius:.75rem;display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <p style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;margin:0 0 .2rem;">Tickets Hijo</p>
                    <h3 id="kpi-tk-hijo" style="font-size:1.6rem;font-weight:800;color:#16a34a;margin:0;">0</h3>
                </div>
                <div style="width:2.75rem;height:2.75rem;border-radius:.5rem;background:#dcfce7;display:flex;align-items:center;justify-content:center;color:#16a34a;font-size:1.1rem;">
                    <i class="fas fa-code-branch"></i>
                </div>
            </div>
            <div class="kpi-card" style="padding:1.25rem;border-radius:.75rem;display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <p style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;margin:0 0 .2rem;">% SLA Completado</p>
                    <h3 id="kpi-tk-sla" style="font-size:1.6rem;font-weight:800;color:#7c3aed;margin:0;">0%</h3>
                </div>
                <div style="width:2.75rem;height:2.75rem;border-radius:.5rem;background:#ede9fe;display:flex;align-items:center;justify-content:center;color:#7c3aed;font-size:1.1rem;">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
            <div class="kpi-card" style="padding:1.25rem;border-radius:.75rem;display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <p style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;margin:0 0 .2rem;">Tiempo Vida Prom.</p>
                    <h3 id="kpi-tk-vida" style="font-size:1.6rem;font-weight:800;color:#d97706;margin:0;">0h</h3>
                </div>
                <div style="width:2.75rem;height:2.75rem;border-radius:.5rem;background:#fef3c7;display:flex;align-items:center;justify-content:center;color:#d97706;font-size:1.1rem;">
                    <i class="fas fa-hourglass-start"></i>
                </div>
            </div>
        </div>

        <!-- Legend -->
        <div style="display:flex;align-items:center;gap:1.5rem;margin-bottom:1rem;font-size:0.75rem;font-weight:600;color:#475569;">
            <span style="display:flex;align-items:center;gap:.4rem;">
                <span style="width:.85rem;height:.85rem;border-radius:3px;background:#bae6fd;border:1.5px solid #38bdf8;display:inline-block;"></span> Ticket Padre
            </span>
            <span style="display:flex;align-items:center;gap:.4rem;">
                <span style="width:.85rem;height:.85rem;border-radius:3px;background:#bbf7d0;border:1.5px solid #4ade80;display:inline-block;"></span> Ticket Hijo
            </span>
            <span style="display:flex;align-items:center;gap:.4rem;">
                <span style="width:.85rem;height:.85rem;border-radius:50%;background:#22c55e;display:inline-block;"></span> Completado
            </span>
            <span style="display:flex;align-items:center;gap:.4rem;">
                <span style="width:.85rem;height:.85rem;border-radius:50%;background:#f59e0b;display:inline-block;"></span> Pausado / Pendiente
            </span>
            <span style="display:flex;align-items:center;gap:.4rem;">
                <span style="width:.85rem;height:.85rem;border-radius:50%;background:#ef4444;display:inline-block;"></span> Cancelado
            </span>
            <span style="display:flex;align-items:center;gap:.4rem;">
                <span style="width:.85rem;height:.85rem;border-radius:50%;background:#6366f1;display:inline-block;"></span> Ejecución / Reasignado
            </span>
        </div>

        <!-- Hierarchical Ticket Table -->
        <div class="bi-card" style="padding:1rem;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem;">
                <h3 style="font-size:0.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#374151;margin:0;">
                    <i class="fas fa-sitemap" style="margin-right:.4rem;color:#6366f1;"></i> Ciclo de Vida de Tickets (Vista ITIL)
                </h3>
                <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;">
                    <button id="btn-toggle-hijas" onclick="toggleChildTasksVisibility()" style="font-size:0.75rem;font-weight:700;padding:.35rem .75rem;border-radius:.375rem;border:1px solid #cbd5e1;background:#ffffff;color:#334155;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;transition:all 0.2s;" title="Mostrar u ocultar tareas hijas del análisis ITIL">
                        <i class="fas fa-sitemap" style="color:#6366f1;"></i> Tareas Hijas: <span id="lbl-status-hijas" style="color:#16a34a;font-weight:800;">VISIBLES (ON)</span>
                    </button>
                    <input type="text" id="ticket-search" placeholder="Buscar ticket, cliente, técnico..." onkeyup="filterTicketTable()" style="font-size:0.75rem;padding:.35rem .6rem;border:1px solid #cbd5e1;border-radius:.375rem;outline:none;width:220px;">
                    <span id="ticket-count" style="font-size:0.72rem;color:#64748b;white-space:nowrap;">0 tickets</span>
                </div>
            </div>
            <div style="overflow-x:auto;">
                <table id="ticket-lifecycle-table" style="width:100%;border-collapse:collapse;font-size:0.72rem;">
                    <thead>
                        <tr style="background:#f1f5f9;border-bottom:2px solid #e2e8f0;">
                            <th style="padding:.6rem .5rem;text-align:left;font-weight:700;color:#475569;white-space:nowrap;min-width:55px;">ID</th>
                            <th style="padding:.6rem .5rem;text-align:left;font-weight:700;color:#475569;white-space:nowrap;min-width:90px;">Referencia
                                <span style="display:block;font-size:.58rem;font-weight:400;color:#94a3b8;">(click para detalle)</span>
                            </th>
                            <th style="padding:.6rem .5rem;text-align:left;font-weight:700;color:#475569;white-space:nowrap;min-width:110px;">Ticket Aranda</th>
                            <th style="padding:.6rem .5rem;text-align:left;font-weight:700;color:#475569;white-space:nowrap;min-width:140px;">T&iacute;tulo</th>
                            <th style="padding:.6rem .5rem;text-align:left;font-weight:700;color:#475569;white-space:nowrap;min-width:120px;">Cliente</th>
                            <th style="padding:.6rem .5rem;text-align:left;font-weight:700;color:#475569;white-space:nowrap;min-width:110px;">Contrato</th>
                            <th style="padding:.6rem .5rem;text-align:left;font-weight:700;color:#475569;white-space:nowrap;min-width:110px;">Servicio</th>
                            <th style="padding:.6rem .5rem;text-align:center;font-weight:700;color:#475569;white-space:nowrap;">Tipo</th>
                            <th style="padding:.6rem .5rem;text-align:left;font-weight:700;color:#475569;white-space:nowrap;min-width:100px;">T&eacute;cnico</th>
                            <th style="padding:.6rem .5rem;text-align:left;font-weight:700;color:#475569;white-space:nowrap;min-width:115px;">Inicio</th>
                            <th style="padding:.6rem .5rem;text-align:left;font-weight:700;color:#475569;white-space:nowrap;min-width:115px;">Fin</th>
                            <th style="padding:.6rem .5rem;text-align:right;font-weight:700;color:#475569;white-space:nowrap;">T.Abierto</th>
                            <th style="padding:.6rem .5rem;text-align:right;font-weight:700;color:#475569;white-space:nowrap;">T.Ejec</th>
                            <th style="padding:.6rem .5rem;text-align:right;font-weight:700;color:#0369a1;white-space:nowrap;">Std</th>
                            <th style="padding:.6rem .5rem;text-align:right;font-weight:700;color:#7c3aed;white-space:nowrap;">Noct</th>
                            <th style="padding:.6rem .5rem;text-align:right;font-weight:700;color:#d97706;white-space:nowrap;">Fin&nbsp;Sem</th>
                            <th style="padding:.6rem .5rem;text-align:center;font-weight:700;color:#475569;white-space:nowrap;min-width:90px;">Estado</th>
                        </tr>
                    </thead>
                    <tbody id="ticket-lifecycle-body">
                        <tr><td colspan="17" style="text-align:center;padding:2rem;color:#94a3b8;">Cargando datos...</td></tr>
                    </tbody>
                    <tfoot id="ticket-lifecycle-foot" style="display:none;">
                        <tr style="background:#1e293b;color:#f8fafc;font-weight:700;font-size:0.72rem;">
                            <td colspan="11" style="padding:.55rem .5rem;text-align:right;">TOTALES &rarr;</td>
                            <td id="tfoot-abierto" style="padding:.55rem .5rem;text-align:right;">0h</td>
                            <td id="tfoot-ejec" style="padding:.55rem .5rem;text-align:right;">0h</td>
                            <td id="tfoot-std" style="padding:.55rem .5rem;text-align:right;color:#7dd3fc;">0h</td>
                            <td id="tfoot-noct" style="padding:.55rem .5rem;text-align:right;color:#c4b5fd;">0h</td>
                            <td id="tfoot-finsem" style="padding:.55rem .5rem;text-align:right;color:#fcd34d;">0h</td>
                            <td style="padding:.55rem .5rem;"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div><!-- /.tab-tickets -->

    <!-- TAB 7: Dashboard PowerBI -->
    <div id="tab-powerbi" class="tab-content animate__animated animate__fadeIn" style="background-color: #f0f5fc; border-radius: 1rem; padding: 1.5rem; border: 1px solid #d0dff2; margin-top: 1rem;">
        
        <!-- TOP FILTER BAR (PowerBI Header Style) -->
        <div style="background-color: #ffffff; border-radius: 12px; padding: 1rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 1.5rem; border: 1px solid #e2e8f0; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem;">
            
            <!-- Sonda Logo Section -->
            <div style="display: flex; align-items: center; gap: 0.5rem; min-width: 180px;">
                <div style="display: flex; flex-direction: column;">
                    <span style="font-size: 1.8rem; font-weight: 900; color: #0033a0; font-family: 'Montserrat', 'Helvetica', Arial, sans-serif; letter-spacing: -1px; line-height: 1;">SONDA</span>
                    <span style="font-size: 0.65rem; font-weight: 600; color: #707070; text-transform: lowercase; letter-spacing: 0.5px; margin-top: 1px;">make it easy</span>
                </div>
            </div>

            <!-- Client Filter -->
            <div style="display: flex; flex-direction: column; min-width: 160px; flex: 1;">
                <label style="font-size: 11px; font-weight: 700; color: #475569; margin-bottom: 3px;">Cliente</label>
                <select id="pb-select-cliente" class="pb-filter-input" style="font-size: 12px; padding: 0.4rem; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #ffffff; outline: none; width: 100%; color: #0f172a;"></select>
            </div>

            <!-- Contract Filter -->
            <div style="display: flex; flex-direction: column; min-width: 140px; flex: 1;">
                <label style="font-size: 11px; font-weight: 700; color: #475569; margin-bottom: 3px;">Contrato</label>
                <select id="pb-select-contrato" class="pb-filter-input" style="font-size: 12px; padding: 0.4rem; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #ffffff; outline: none; width: 100%; color: #0f172a;"></select>
            </div>

            <!-- Service Filter -->
            <div style="display: flex; flex-direction: column; min-width: 160px; flex: 1;">
                <label style="font-size: 11px; font-weight: 700; color: #475569; margin-bottom: 3px;">Tipo Servicio</label>
                <select id="pb-select-servicio" class="pb-filter-input" style="font-size: 12px; padding: 0.4rem; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #ffffff; outline: none; width: 100%; color: #0f172a;"></select>
            </div>

            <!-- Date Range Filter -->
            <div style="display: flex; flex-direction: column; min-width: 220px; flex: 1.2;">
                <label style="font-size: 11px; font-weight: 700; color: #475569; margin-bottom: 3px;">Fecha registro</label>
                <div style="display: flex; align-items: center; gap: 0.4rem;">
                    <input type="date" id="pb-date-start" class="pb-filter-input" style="font-size: 12px; padding: 0.35rem; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #ffffff; outline: none; width: 100%; color: #0f172a;">
                    <span style="font-size: 12px; color: #94a3b8;">a</span>
                    <input type="date" id="pb-date-end" class="pb-filter-input" style="font-size: 12px; padding: 0.35rem; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #ffffff; outline: none; width: 100%; color: #0f172a;">
                </div>
            </div>

            <!-- Month/Year Filter -->
            <div style="display: flex; flex-direction: column; min-width: 110px; flex: 0.8;">
                <label style="font-size: 11px; font-weight: 700; color: #475569; margin-bottom: 3px;">MES/AÑO</label>
                <select id="pb-select-mesano" class="pb-filter-input" style="font-size: 12px; padding: 0.4rem; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #ffffff; outline: none; width: 100%; color: #0f172a;"></select>
            </div>
        </div>

        <!-- MAIN LAYOUT GRID -->
        <div style="display: grid; grid-template-columns: repeat(12, 1fr); gap: 1.5rem;">
            
            <!-- LEFT PANEL: Description, Ticket Aranda, KPI Card (span 3) -->
            <div style="grid-column: span 3; display: flex; flex-direction: column; gap: 1.25rem;">
                
                <!-- Descripción Card -->
                <div style="background-color: #ffffff; border-radius: 12px; padding: 1rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);">
                    <h4 style="font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 0.5rem; text-transform: capitalize;">Descripción</h4>
                    <div style="display: flex; align-items: center; gap: 0.4rem;">
                        <div style="position: relative; flex-grow: 1;">
                            <input type="text" id="pb-search-descripcion" placeholder="Search" style="width: 100%; font-size: 12px; padding: 0.4rem 2rem 0.4rem 0.5rem; border: 1px solid #cbd5e1; border-radius: 4px; outline: none; box-sizing: border-box;">
                            <i class="fas fa-search" style="position: absolute; right: 8px; top: 8px; color: #94a3b8; font-size: 11px;"></i>
                        </div>
                        <button onclick="pbClearSearch('pb-search-descripcion')" style="padding: 0.4rem 0.5rem; background-color: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 4px; color: #64748b; cursor: pointer; display: flex; align-items: center; justify-content: center; height: 30px;" title="Limpiar filtro">
                            <i class="fas fa-eraser" style="font-size: 11px;"></i>
                        </button>
                    </div>
                </div>

                <!-- Ticket Aranda Card -->
                <div style="background-color: #ffffff; border-radius: 12px; padding: 1rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);">
                    <h4 style="font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 0.5rem; text-transform: capitalize;">Ticket Aranda</h4>
                    <div style="display: flex; align-items: center; gap: 0.4rem;">
                        <div style="position: relative; flex-grow: 1;">
                            <input type="text" id="pb-search-aranda" placeholder="Search" style="width: 100%; font-size: 12px; padding: 0.4rem 2rem 0.4rem 0.5rem; border: 1px solid #cbd5e1; border-radius: 4px; outline: none; box-sizing: border-box;">
                            <i class="fas fa-search" style="position: absolute; right: 8px; top: 8px; color: #94a3b8; font-size: 11px;"></i>
                        </div>
                        <button onclick="pbClearSearch('pb-search-aranda')" style="padding: 0.4rem 0.5rem; background-color: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 4px; color: #64748b; cursor: pointer; display: flex; align-items: center; justify-content: center; height: 30px;" title="Limpiar filtro">
                            <i class="fas fa-eraser" style="font-size: 11px;"></i>
                        </button>
                    </div>
                </div>

                <!-- Tiempo Consumido KPI Card -->
                <div style="background-color: #ffffff; border-radius: 12px; padding: 1rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 120px;">
                    <span style="font-size: 11px; font-weight: 800; color: #0056b3; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem; text-align: center;">Tiempo Consumido</span>
                    <span id="pb-kpi-tiempo" style="font-size: 2.5rem; font-weight: 800; color: #0f172a; line-height: 1;">0.00</span>
                </div>
            </div>

            <!-- CENTER PANEL: Gestiones por contrato Bar Chart (span 4.5) -->
            <div style="grid-column: span 4.5; background-color: #ffffff; border-radius: 12px; padding: 1rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); display: flex; flex-direction: column;">
                <h4 style="font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 1rem;">Gestiones por contrato</h4>
                <div style="position: relative; height: 260px; width: 100%; flex-grow: 1;">
                    <canvas id="pb-chart-gestiones"></canvas>
                </div>
            </div>

            <!-- RIGHT PANEL: Servicios Donut Chart (span 4.5) -->
            <div style="grid-column: span 4.5; background-color: #ffffff; border-radius: 12px; padding: 1rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); display: flex; flex-direction: column;">
                <h4 style="font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 1rem;">Servicios</h4>
                <div style="position: relative; height: 260px; width: 100%; flex-grow: 1; display: flex; align-items: center; justify-content: center;">
                    <canvas id="pb-chart-servicios"></canvas>
                </div>
            </div>

            <!-- BOTTOM LEFT: Actividades registradas por Clientes (span 6) -->
            <div style="grid-column: span 6; background-color: #ffffff; border-radius: 12px; padding: 1rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); display: flex; flex-direction: column;">
                <h4 style="font-size: 12px; font-weight: 800; color: #0056b3; margin-bottom: 1rem;">Actividades registradas por Clientes</h4>
                <div style="position: relative; height: 320px; width: 100%; flex-grow: 1;">
                    <canvas id="pb-chart-actividades"></canvas>
                </div>
            </div>

            <!-- BOTTOM RIGHT: Table Grid (span 6) -->
            <div style="grid-column: span 6; background-color: #ffffff; border-radius: 12px; padding: 1rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); display: flex; flex-direction: column; justify-content: space-between;">
                <div style="flex-grow: 1;">
                    <div style="max-height: 300px; overflow-y: auto; border: 1px solid #cbd5e1; border-radius: 6px; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);">
                        <table style="width: 100%; border-collapse: collapse; font-size: 11px; text-align: left;">
                            <thead>
                                <tr style="background-color: #0070f3; color: #ffffff; font-weight: 700; border-bottom: 2px solid #0056b3; position: sticky; top: 0; z-index: 10;">
                                    <th style="padding: 0.5rem; border-right: 1px solid #0056b3;">Unique ID</th>
                                    <th style="padding: 0.5rem; border-right: 1px solid #0056b3;">Contrato</th>
                                    <th style="padding: 0.5rem; border-right: 1px solid #0056b3;">Servicio Solicitado</th>
                                    <th style="padding: 0.5rem; border-right: 1px solid #0056b3; text-align: center;">Seguimientos (nº)</th>
                                    <th style="padding: 0.5rem; border-right: 1px solid #0056b3;">Aranda</th>
                                    <th style="padding: 0.5rem;">Especialista</th>
                                </tr>
                            </thead>
                            <tbody id="pb-table-body">
                                <tr>
                                    <td colspan="6" style="padding: 2rem; text-align: center; color: #94a3b8; font-style: italic;">Cargando registros...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem; font-size: 11px; color: #64748b;">
                    <span id="pb-table-info">Mostrando 0 de 0 registros</span>
                </div>
            </div>

        </div>
    </div><!-- /.tab-powerbi -->

    <!-- TAB 8: Ocupación Especialistas -->
    <div id="tab-especialistas" class="tab-content animate__animated animate__fadeIn" style="background-color: #f0f5fc; border-radius: 1rem; padding: 1.5rem; border: 1px solid #d0dff2; margin-top: 1rem;">
        
        <!-- TOP FILTER BAR (PowerBI Header Style) -->
        <div style="background-color: #ffffff; border-radius: 12px; padding: 1rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 1.5rem; border: 1px solid #e2e8f0; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem;">
            
            <!-- Sonda Logo Section -->
            <div style="display: flex; align-items: center; gap: 0.5rem; min-width: 180px;">
                <div style="display: flex; flex-direction: column;">
                    <span style="font-size: 1.8rem; font-weight: 900; color: #0033a0; font-family: 'Montserrat', 'Helvetica', Arial, sans-serif; letter-spacing: -1px; line-height: 1;">SONDA</span>
                    <span style="font-size: 0.65rem; font-weight: 600; color: #707070; text-transform: lowercase; letter-spacing: 0.5px; margin-top: 1px;">make it easy</span>
                </div>
            </div>

            <!-- Specialist Filter -->
            <div style="display: flex; flex-direction: column; min-width: 180px; flex: 1.2;">
                <label style="font-size: 11px; font-weight: 700; color: #475569; margin-bottom: 3px;">Especialista</label>
                <select id="pe-select-especialista" class="pe-filter-input" style="font-size: 12px; padding: 0.4rem; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #ffffff; outline: none; width: 100%; color: #0f172a;"></select>
            </div>

            <!-- Year Filter -->
            <div style="display: flex; flex-direction: column; min-width: 110px; flex: 0.8;">
                <label style="font-size: 11px; font-weight: 700; color: #475569; margin-bottom: 3px;">Año</label>
                <select id="pe-select-ano" class="pe-filter-input" style="font-size: 12px; padding: 0.4rem; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #ffffff; outline: none; width: 100%; color: #0f172a;"></select>
            </div>

            <!-- Month Filter -->
            <div style="display: flex; flex-direction: column; min-width: 130px; flex: 1;">
                <label style="font-size: 11px; font-weight: 700; color: #475569; margin-bottom: 3px;">Mes</label>
                <select id="pe-select-mes" class="pe-filter-input" style="font-size: 12px; padding: 0.4rem; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #ffffff; outline: none; width: 100%; color: #0f172a;"></select>
            </div>

            <!-- Day Filter -->
            <div style="display: flex; flex-direction: column; min-width: 100px; flex: 0.8;">
                <label style="font-size: 11px; font-weight: 700; color: #475569; margin-bottom: 3px;">Día</label>
                <select id="pe-select-dia" class="pe-filter-input" style="font-size: 12px; padding: 0.4rem; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #ffffff; outline: none; width: 100%; color: #0f172a;"></select>
            </div>
        </div>

        <!-- MAIN LAYOUT GRID -->
        <div style="display: grid; grid-template-columns: repeat(12, 1fr); gap: 1.5rem; margin-bottom: 1.5rem;">
            
            <!-- LEFT CHART: Ocupación Diaria (span 6) -->
            <div style="grid-column: span 6; background-color: #ffffff; border-radius: 12px; padding: 1.25rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); display: flex; flex-direction: column;">
                <h4 style="font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 0.75rem;">Ocupación diaria</h4>
                <div style="position: relative; height: 300px; width: 100%; flex-grow: 1;">
                    <canvas id="pe-chart-diaria"></canvas>
                </div>
            </div>

            <!-- RIGHT CHART: Ocupación por Cliente (span 6) -->
            <div style="grid-column: span 6; background-color: #ffffff; border-radius: 12px; padding: 1.25rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); display: flex; flex-direction: column;">
                <h4 style="font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 0.75rem;">Ocupación por cliente</h4>
                <div style="position: relative; height: 300px; width: 100%; flex-grow: 1; display: flex; align-items: center; justify-content: center;">
                    <canvas id="pe-chart-cliente"></canvas>
                </div>
            </div>

        </div>

        <!-- BOTTOM GRID: 4 Cards -->
        <div style="display: grid; grid-template-columns: repeat(12, 1fr); gap: 1.5rem;">
            
            <!-- Card 1: Semicircle Gauge (span 3.5) -->
            <div style="grid-column: span 3.5; background-color: #ffffff; border-radius: 12px; padding: 1rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 180px; position: relative;">
                <span style="font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 0.25rem; align-self: flex-start; text-transform: capitalize;">Ocupación mensual</span>
                <div style="position: relative; height: 110px; width: 100%; display: flex; justify-content: center; align-items: center; margin-top: 0.5rem;">
                    <canvas id="pe-chart-gauge"></canvas>
                    <div style="position: absolute; bottom: 8px; display: flex; flex-direction: column; align-items: center;">
                        <span id="pe-gauge-value" style="font-size: 1.7rem; font-weight: 800; color: #dc2626; line-height: 1;">0.00</span>
                    </div>
                </div>
                <div style="width: 100%; display: flex; justify-content: space-between; font-size: 10px; color: #94a3b8; font-weight: bold; padding: 0 10%; margin-top: -5px; z-index: 10;">
                    <span>0,00</span>
                    <span id="pe-gauge-max-label">160,00</span>
                </div>
            </div>

            <!-- Card 2: Recuento de Valor (span 2.5) -->
            <div style="grid-column: span 2.5; background-color: #ffffff; border-radius: 12px; padding: 1.25rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 180px;">
                <span id="pe-kpi-recuento" style="font-size: 3rem; font-weight: 800; color: #0f172a; line-height: 1; margin-bottom: 0.5rem;">0</span>
                <span style="font-size: 11px; font-weight: 600; color: #64748b; text-align: center; text-transform: uppercase; letter-spacing: 0.5px;">Recuento de Valor</span>
            </div>

            <!-- Card 3: Hojas Registradas (span 2.5) -->
            <div style="grid-column: span 2.5; background-color: #ffffff; border-radius: 12px; padding: 1.25rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 180px;">
                <span id="pe-kpi-hojas" style="font-size: 3rem; font-weight: 800; color: #0f172a; line-height: 1; margin-bottom: 0.5rem;">0</span>
                <span style="font-size: 11px; font-weight: 600; color: #64748b; text-align: center; text-transform: uppercase; letter-spacing: 0.5px;">Hojas Registradas</span>
            </div>

            <!-- Card 4: Ocupación por especialista (span 3.5) -->
            <div style="grid-column: span 3.5; background-color: #ffffff; border-radius: 12px; padding: 1rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); display: flex; flex-direction: column;">
                <h4 style="font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 0.5rem;">Ocupación por especialista</h4>
                <div style="position: relative; height: 130px; width: 100%; flex-grow: 1;">
                    <canvas id="pe-chart-especialista"></canvas>
                </div>
            </div>

        </div>
    </div><!-- /.tab-especialistas -->

    <!-- TAB 9: Cálculo de Horas y Recargos (Saltos de Ticket) -->
    <div id="tab-horas-recargos" class="tab-content animate__animated animate__fadeIn">
        
        <!-- KPI Row Header -->
        <div class="grid-custom-4 mb-6">
            <div class="kpi-card p-4 rounded-xl flex items-center justify-between shadow-sm">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Total Saltos / Items</p>
                    <h3 id="kpi-hr-items" class="text-2xl font-black text-slate-800 dark:text-slate-100">0</h3>
                </div>
                <div class="w-11 h-11 rounded-lg bg-indigo-100 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-400 flex items-center justify-center text-lg font-bold">
                    <i class="fas fa-list-ol"></i>
                </div>
            </div>

            <div class="kpi-card p-4 rounded-xl flex items-center justify-between shadow-sm">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Minutos Base (Reales)</p>
                    <h3 id="kpi-hr-base" class="text-2xl font-black text-slate-800 dark:text-slate-100">0 min</h3>
                </div>
                <div class="w-11 h-11 rounded-lg bg-sky-100 text-sky-600 dark:bg-sky-900/40 dark:text-sky-400 flex items-center justify-center text-lg font-bold">
                    <i class="fas fa-clock"></i>
                </div>
            </div>

            <div class="kpi-card p-4 rounded-xl flex items-center justify-between shadow-sm">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Min. Ajustados (>15 min)</p>
                    <h3 id="kpi-hr-ajust" class="text-2xl font-black text-indigo-600 dark:text-indigo-400">0 min</h3>
                </div>
                <div class="w-11 h-11 rounded-lg bg-indigo-100 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-400 flex items-center justify-center text-lg font-bold">
                    <i class="fas fa-hourglass-half"></i>
                </div>
            </div>

            <div class="kpi-card p-4 rounded-xl flex items-center justify-between shadow-sm">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Alertas > 1,440 min (24h)</p>
                    <h3 id="kpi-hr-24h" class="text-2xl font-black text-slate-800 dark:text-slate-100">0</h3>
                </div>
                <div class="w-11 h-11 rounded-lg bg-red-100 text-red-600 dark:bg-red-900/40 dark:text-red-400 flex items-center justify-center text-lg font-bold">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
        </div>

        <!-- Rules Explanatory Card -->
        <div class="bi-card mb-6 border-l-4 border-indigo-500">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h3 class="text-sm font-extrabold uppercase tracking-wider text-slate-800 dark:text-slate-100 flex items-center gap-2">
                        <i class="fas fa-info-circle text-indigo-500"></i> Reglas de Negocio para el Cálculo de Tiempos en MINUTOS
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 mt-2 text-xs text-slate-600 dark:text-slate-300">
                        <div class="p-2 bg-slate-50 dark:bg-slate-800/60 rounded border border-slate-200 dark:border-slate-700">
                            <span class="font-bold text-red-600 dark:text-red-400 block mb-0.5"><i class="fas fa-exclamation-circle mr-1"></i> Tiempos > 1,440 min (24h):</span>
                            Resaltado en ROJO si la duración supera 1,440 minutos.
                        </div>
                        <div class="p-2 bg-slate-50 dark:bg-slate-800/60 rounded border border-slate-200 dark:border-slate-700">
                            <span class="font-bold text-sky-600 dark:text-sky-400 block mb-0.5"><i class="fas fa-sun mr-1"></i> Minutos Normales:</span>
                            Ventana de <b>06:00 a 18:00 h</b> (expresado en min).
                        </div>
                        <div class="p-2 bg-slate-50 dark:bg-slate-800/60 rounded border border-slate-200 dark:border-slate-700">
                            <span class="font-bold text-purple-600 dark:text-purple-400 block mb-0.5"><i class="fas fa-moon mr-1"></i> Minutos Extras:</span>
                            Ventana de <b>18:00 a 22:00 h</b> (expresado en min).
                        </div>
                        <div class="p-2 bg-slate-50 dark:bg-slate-800/60 rounded border border-slate-200 dark:border-slate-700">
                            <span class="font-bold text-amber-600 dark:text-amber-400 block mb-0.5"><i class="fas fa-cloud-moon mr-1"></i> Min. Extraordinarios:</span>
                            Ventana de <b>22:00 a 06:00 h</b> del día siguiente.
                        </div>
                    </div>
                    <div class="mt-2 text-xs text-slate-500 dark:text-slate-400 flex flex-wrap items-center gap-4">
                        <span><b><i class="fas fa-step-forward text-indigo-500 mr-1"></i>Redondeo >15 min:</b> Si los minutos fraccionales superan 15 min (ej: 196 min), se eleva a la hora completa equivalente: <b>240 min</b> (+60 min).</span>
                        <span><b><i class="fas fa-bolt text-amber-500 mr-1"></i>Minutos al Doble:</b> Si está activo, duplica (x2) todos los minutos calculados en cada ventana.</span>
                        <span class="text-indigo-600 font-bold"><b><i class="fas fa-arrows-alt-h mr-1"></i>Columnas Ajustables:</b> Haz clic y arrastra el borde derecho de cualquier encabezado para ajustar su ancho.</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Controls & Filter Toolbar -->
        <div class="bi-card mb-6">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                
                <!-- Left: View Mode Switches -->
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider mr-1">Modo de Vista:</span>
                    <button id="btn-view-hierarchical" onclick="setHorasRecargosViewMode('hierarchical')" class="px-3 py-1.5 rounded-md font-bold text-xs bg-indigo-600 text-white shadow-sm flex items-center gap-1.5">
                        <i class="fas fa-sitemap"></i> Hierárquica (Ticket + Saltos)
                    </button>
                    <button id="btn-view-saltos" onclick="setHorasRecargosViewMode('saltos')" class="px-3 py-1.5 rounded-md font-medium text-xs bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 flex items-center gap-1.5">
                        <i class="fas fa-running"></i> Detalle por Saltos
                    </button>
                </div>

                <!-- Right: Toggle Horas al Doble + Export Excel -->
                <div class="flex flex-wrap items-center gap-3">
                    <button id="btn-toggle-doble" onclick="toggleHorasDoble()" class="px-4 py-2 rounded-lg font-bold text-xs flex items-center gap-2 border shadow-sm transition-all bg-slate-100 text-slate-700 border-slate-300 hover:bg-slate-200">
                        <i class="fas fa-bolt text-amber-500"></i> Horas al Doble: 
                        <span id="lbl-status-doble" class="text-slate-500 font-extrabold uppercase">DESACTIVADO (1x)</span>
                    </button>

                    <button onclick="exportHorasRecargosExcel()" class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-lg shadow-sm flex items-center gap-1.5 transition-all">
                        <i class="fas fa-file-excel text-sm"></i> Exportar a Excel
                    </button>
                </div>
            </div>

            <!-- Search Subbar -->
            <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between gap-4">
                <div class="relative flex-1 max-w-md">
                    <input type="text" id="horas-search-input" placeholder="Buscar por ticket, cliente, técnico, servicio..." onkeyup="renderHorasRecargosTab()" class="w-full text-xs py-1.5 pl-8 pr-3 border border-slate-300 dark:border-slate-600 rounded-lg outline-none focus:border-indigo-500 dark:bg-slate-800 dark:text-slate-100">
                    <i class="fas fa-search absolute left-2.5 top-2.5 text-slate-400 text-xs"></i>
                </div>
                <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">Sincronizado con la barra global de filtros BI</span>
            </div>
        </div>

        <!-- Main Data Table Container -->
        <div class="bi-card p-0 overflow-hidden">
            <div class="overflow-x-auto max-h-[600px] overflow-y-auto">
                <table id="horas-recargos-table" class="w-full text-xs text-left border-collapse">
                    <thead class="sticky top-0 z-20 shadow-sm font-extrabold text-[11px]">
                        <!-- Row 1: Grouped Header Categories with Color Coding -->
                        <tr class="bg-slate-800 text-white uppercase text-[10px] tracking-wider text-center">
                            <th colspan="12" class="py-2 px-2 bg-slate-800 border-r border-slate-700">
                                <i class="fas fa-ticket-alt mr-1 text-indigo-400"></i> Informes & Identificación ITIL del Ticket / Salto
                            </th>
                            <th colspan="3" class="py-2 px-2 bg-blue-700 border-r border-blue-600 text-white font-black">
                                <i class="fas fa-clock mr-1"></i> Desglose Real de Tiempos (Azul)
                            </th>
                            <th colspan="2" class="py-2 px-2 bg-emerald-700 border-r border-emerald-600 text-white font-black">
                                <i class="fas fa-step-forward mr-1"></i> Regla Ajuste +15m (Verde)
                            </th>
                            <th colspan="4" class="py-2 px-2 bg-amber-600 text-white font-black">
                                <i class="fas fa-bolt mr-1"></i> Recargos Liquidados 2x (Ámbar)
                            </th>
                            <th rowspan="2" class="py-2 px-2 bg-slate-900 text-white border-l border-slate-700 min-w-[90px] text-center">Alerta >24h</th>
                        </tr>
                        <!-- Row 2: Detailed Sub-Headers -->
                        <tr class="bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 border-b-2 border-slate-300 dark:border-slate-700">
                            <th class="py-2.5 px-2.5 min-w-[95px]">ID Tarea</th>
                            <th class="py-2.5 px-2 min-w-[110px]">Referencia</th>
                            <th class="py-2.5 px-2 min-w-[120px]">Ticket Aranda</th>
                            <th class="py-2.5 px-2 min-w-[150px]">Título</th>
                            <th class="py-2.5 px-2 min-w-[120px]">Cliente</th>
                            <th class="py-2.5 px-2 min-w-[110px]">Servicio</th>
                            <th class="py-2.5 px-2 text-center min-w-[95px]">Tipo</th>
                            <th class="py-2.5 px-2 min-w-[120px]">Técnico / Resp.</th>
                            <th class="py-2.5 px-2 text-center min-w-[100px]">Estado</th>
                            <th class="py-2.5 px-2 min-w-[110px]">Inicio Efectivo</th>
                            <th class="py-2.5 px-2 min-w-[110px]">Fin Efectivo</th>
                            <th class="py-2.5 px-2 text-center min-w-[100px] border-r border-slate-300 dark:border-slate-700">Duración Real</th>

                            <!-- Grupo Azul: Desglose Real -->
                            <th class="py-2.5 px-2 text-right bg-blue-50 dark:bg-blue-950/40 text-blue-900 dark:text-blue-200 min-w-[95px]">Norm (06-18h)</th>
                            <th class="py-2.5 px-2 text-right bg-blue-50 dark:bg-blue-950/40 text-blue-900 dark:text-blue-200 min-w-[95px]">Ext (18-22h)</th>
                            <th class="py-2.5 px-2 text-right bg-blue-50 dark:bg-blue-950/40 text-blue-900 dark:text-blue-200 border-r border-blue-200 dark:border-blue-800 min-w-[95px]">Extord (22-06h)</th>

                            <!-- Grupo Verde: Regla +15m -->
                            <th class="py-2.5 px-2 text-right bg-emerald-50 dark:bg-emerald-950/40 text-emerald-900 dark:text-emerald-200 min-w-[105px]">Incremento (+15m)</th>
                            <th class="py-2.5 px-2 text-right bg-emerald-50 dark:bg-emerald-950/40 text-emerald-900 dark:text-emerald-200 border-r border-emerald-200 dark:border-emerald-800 font-bold min-w-[110px]">Min. Ajustados</th>

                            <!-- Grupo Ámbar: Recargos al Doble (2x) -->
                            <th class="py-2.5 px-2 text-right bg-amber-50 dark:bg-amber-950/40 text-amber-900 dark:text-amber-200 min-w-[105px]">Norm Liq. (06-18)</th>
                            <th class="py-2.5 px-2 text-right bg-amber-50 dark:bg-amber-950/40 text-amber-900 dark:text-amber-200 min-w-[105px]">Ext Liq. (18-22)</th>
                            <th class="py-2.5 px-2 text-right bg-amber-50 dark:bg-amber-950/40 text-amber-900 dark:text-amber-200 min-w-[105px]">Extord Liq. (22-06)</th>
                            <th class="py-2.5 px-2 text-right bg-amber-100 dark:bg-amber-900/60 text-amber-950 dark:text-amber-100 font-black min-w-[125px]">Total Liq. Final</th>
                        </tr>
                    </thead>
                    <tbody id="horas-recargos-body">
                        <tr>
                            <td colspan="22" class="py-8 text-center text-slate-400">Cargando datos del cálculo de horas y recargos...</td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-slate-900 text-white font-extrabold text-xs sticky bottom-0 z-20 shadow-lg">
                        <tr>
                            <td colspan="11" class="py-3 px-4 text-right uppercase tracking-wider text-slate-300">TOTALES ACUMULADOS &rarr;</td>
                            <td id="ft-recargos-real-dur" class="py-3 px-2 text-center text-slate-100 font-bold">0 min</td>
                            
                            <!-- Totales Grupo Azul -->
                            <td id="ft-recargos-real-norm" class="py-3 px-2 text-right text-blue-300 font-semibold">0 min</td>
                            <td id="ft-recargos-real-ext" class="py-3 px-2 text-right text-blue-300 font-semibold">0 min</td>
                            <td id="ft-recargos-real-extord" class="py-3 px-2 text-right text-blue-300 font-semibold border-r border-slate-700">0 min</td>

                            <!-- Totales Grupo Verde -->
                            <td id="ft-recargos-inc15" class="py-3 px-2 text-right text-emerald-300 font-semibold">0 min</td>
                            <td id="ft-recargos-ajustadas" class="py-3 px-2 text-right text-emerald-200 font-extrabold border-r border-slate-700">0 min</td>

                            <!-- Totales Grupo Ámbar -->
                            <td id="ft-recargos-normales" class="py-3 px-2 text-right text-amber-300">0 min</td>
                            <td id="ft-recargos-extras" class="py-3 px-2 text-right text-amber-300">0 min</td>
                            <td id="ft-recargos-extord" class="py-3 px-2 text-right text-amber-300">0 min</td>
                            <td id="ft-recargos-total" class="py-3 px-2 text-right text-amber-200 font-black text-sm bg-amber-950/60">0 min</td>

                            <td class="py-3 px-2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div><!-- /.tab-horas-recargos -->

    <!-- TAB 10: Control de Carga de Trabajo y Cumplimiento Diario por Especialista -->
    <div id="tab-cumplimiento" class="tab-content animate__animated animate__fadeIn">
        <!-- Header Summary Card & Controls -->
        <div class="bi-card mb-6 bg-slate-900 text-white rounded-xl shadow-lg border border-slate-800 p-5">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-4 pb-4 border-b border-slate-800">
                <div>
                    <h2 class="text-lg font-black text-white flex items-center gap-2">
                        <i class="fas fa-user-check text-emerald-400"></i> Control de Carga de Trabajo y Cumplimiento Diario de Jornada
                    </h2>
                    <p class="text-xs text-slate-400 mt-1">
                        Análisis tipo Pivot por Especialista, Fecha, Mes y Año. Supervisión de cumplimiento de jornada laboral (Meta 8 Horas/día) con tiempo efectivo en tareas padres e hijas.
                    </p>
                </div>
                <div class="flex items-center gap-3 bg-slate-800/80 px-3 py-1.5 rounded-lg border border-slate-700">
                    <span class="text-xs font-bold text-slate-300">Jornada Objetivo (Horas/Día):</span>
                    <input type="number" id="cmp-target-hours" value="8" min="1" max="24" step="0.5" onchange="renderCumplimientoTab()" class="w-16 bg-slate-900 text-emerald-400 font-extrabold text-center text-sm border border-emerald-500/50 rounded py-0.5 focus:outline-none focus:ring-1 focus:ring-emerald-400">
                    <span class="text-xs text-slate-400">hrs</span>
                </div>
            </div>

            <!-- KPI Summary Bar -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                <div class="bg-slate-800/60 p-3 rounded-lg border border-slate-700">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Días Evaluados</span>
                    <span id="cmp-kpi-dias" class="text-xl font-extrabold text-slate-100">0</span>
                </div>
                <div class="bg-emerald-950/40 p-3 rounded-lg border border-emerald-800/60">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-400 block">Días Cumplidos (&ge; 8h)</span>
                    <span id="cmp-kpi-cumplidos" class="text-xl font-extrabold text-emerald-400">0</span>
                </div>
                <div class="bg-red-950/40 p-3 rounded-lg border border-red-800/60">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-red-400 block">Días Incumplidos (&lt; 8h)</span>
                    <span id="cmp-kpi-incumplidos" class="text-xl font-extrabold text-red-400 flex items-center gap-1.5">
                        <i class="fas fa-exclamation-triangle text-sm"></i> <span id="cmp-kpi-incumplidos-val">0</span>
                    </span>
                </div>
                <div class="bg-blue-950/40 p-3 rounded-lg border border-blue-800/60">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-blue-300 block">Tiempo Total Trabajado</span>
                    <span id="cmp-kpi-tiempo-total" class="text-xl font-extrabold text-blue-300">0h 0m</span>
                </div>
                <div class="bg-indigo-950/40 p-3 rounded-lg border border-indigo-800/60">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-indigo-300 block">% Cumplimiento Global</span>
                    <span id="cmp-kpi-tasa" class="text-xl font-extrabold text-indigo-300">0%</span>
                </div>
            </div>
        </div>

        <!-- Filter Control Bar -->
        <div class="bi-card mb-6 p-4 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-sm">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-3 items-center">
                <div>
                    <label class="text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase block mb-1">
                        <i class="fas fa-user mr-1 text-indigo-500"></i> Especialista:
                    </label>
                    <select id="cmp-select-especialista" onchange="renderCumplimientoTab()" class="w-full text-xs bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 text-slate-800 dark:text-slate-100 rounded-lg p-2 font-medium">
                        <option value="">-- Todos los Especialistas --</option>
                    </select>
                </div>
                <div>
                    <label class="text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase block mb-1">
                        <i class="far fa-calendar-alt mr-1 text-indigo-500"></i> Año:
                    </label>
                    <select id="cmp-select-ano" onchange="renderCumplimientoTab()" class="w-full text-xs bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 text-slate-800 dark:text-slate-100 rounded-lg p-2 font-medium">
                        <option value="">-- Todos los Años --</option>
                    </select>
                </div>
                <div>
                    <label class="text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase block mb-1">
                        <i class="far fa-calendar-minus mr-1 text-indigo-500"></i> Mes:
                    </label>
                    <select id="cmp-select-mes" onchange="renderCumplimientoTab()" class="w-full text-xs bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 text-slate-800 dark:text-slate-100 rounded-lg p-2 font-medium">
                        <option value="">-- Todos los Meses --</option>
                    </select>
                </div>
                <div>
                    <label class="text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase block mb-1">
                        <i class="fas fa-filter mr-1 text-indigo-500"></i> Estado Cumplimiento:
                    </label>
                    <select id="cmp-select-estado" onchange="renderCumplimientoTab()" class="w-full text-xs bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 text-slate-800 dark:text-slate-100 rounded-lg p-2 font-medium">
                        <option value="">-- Todos los Estados --</option>
                        <option value="cumplido">Cumplido (&ge; 8h)</option>
                        <option value="incumplido">Incumplido (&lt; 8h en Rojo)</option>
                    </select>
                </div>
                <div>
                    <label class="text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase block mb-1">
                        <i class="fas fa-search mr-1 text-indigo-500"></i> Buscar en Tabla:
                    </label>
                    <input type="text" id="cmp-search-text" onkeyup="renderCumplimientoTab()" placeholder="Filtrar por nombre, fecha..." class="w-full text-xs bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 text-slate-800 dark:text-slate-100 rounded-lg p-2 font-medium">
                </div>
            </div>
        </div>

        <!-- Executive Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6">
            <!-- Chart 1: Horas Trabajadas Diarias vs Meta 8h (span 8) -->
            <div class="lg:col-span-8 bi-card p-4 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-sm">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-200 mb-3 flex items-center gap-2">
                    <i class="fas fa-chart-bar text-indigo-500"></i> Carga de Trabajo Diaria vs Meta (Horas Efectivas en Verde/Rojo)
                </h3>
                <div class="h-64 relative">
                    <canvas id="cmp-chart-diario"></canvas>
                </div>
            </div>

            <!-- Chart 2: Tasa de Cumplimiento (span 4) -->
            <div class="lg:col-span-4 bi-card p-4 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-sm">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-200 mb-3 flex items-center gap-2">
                    <i class="fas fa-chart-pie text-emerald-500"></i> Proporción de Días Cumplidos
                </h3>
                <div class="h-64 relative flex items-center justify-center">
                    <canvas id="cmp-chart-proporcion"></canvas>
                </div>
            </div>
        </div>

        <!-- Pivot Matrix Data Table Section -->
        <div class="bi-card p-5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-sm">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 mb-4 pb-3 border-b border-slate-200 dark:border-slate-700">
                <div>
                    <h3 class="text-sm font-extrabold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                        <i class="fas fa-table text-indigo-600"></i> Matriz Pivot de Carga Diaria y Cumplimiento de Jornada
                    </h3>
                    <span class="text-xs text-slate-500 dark:text-slate-400">
                        Las fechas con tiempo efectivo inferior a 8 horas se resaltan en <strong class="text-red-600 dark:text-red-400">ROJO</strong>. Haz clic en "Ver Tareas" para inspeccionar las tareas padres e hijas trabajadas ese día.
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <span id="cmp-table-row-count" class="text-xs font-bold text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-900 px-3 py-1 rounded-full border border-slate-200 dark:border-slate-700">
                        Mostrando 0 registros
                    </span>
                </div>
            </div>

            <div class="overflow-x-auto max-h-[600px] overflow-y-auto">
                <table class="w-full text-xs text-left border-collapse">
                    <thead class="sticky top-0 z-20 shadow-sm font-extrabold text-[11px] bg-slate-800 text-white uppercase tracking-wider">
                        <tr>
                            <th class="py-3 px-3 min-w-[130px]">Fecha / Día</th>
                            <th class="py-3 px-2 min-w-[100px]">Día Semana</th>
                            <th class="py-3 px-3 min-w-[160px]">Especialista</th>
                            <th class="py-3 px-2 min-w-[110px] text-center">Jornada Meta</th>
                            <th class="py-3 px-2 text-center min-w-[120px]">Tareas (Padre/Hijos)</th>
                            <th class="py-3 px-3 min-w-[140px] text-right">Tiempo Trabajado</th>
                            <th class="py-3 px-3 min-w-[120px] text-right">Diferencia</th>
                            <th class="py-3 px-3 text-center min-w-[130px]">% Cumplimiento</th>
                            <th class="py-3 px-3 text-center min-w-[130px]">Estado Jornada</th>
                            <th class="py-3 px-3 text-center min-w-[110px]">Acción / Desglose</th>
                        </tr>
                    </thead>
                    <tbody id="cmp-matrix-body" class="divide-y divide-slate-200 dark:divide-slate-700">
                        <tr>
                            <td colspan="10" class="py-8 text-center text-slate-400">Cargando matriz pivot de cumplimiento...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div><!-- /.tab-cumplimiento -->


    </div><!-- /.novaiops-scope -->

<!-- REQUIRED SCRIPTS FOR DATATABLES AND PIVOTTABLE -->
<!-- Load jQuery UI first for PivotTable dependency -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/jquery-ui.min.js"></script>

<!-- Load DataTables JS & Buttons -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>

<!-- Load PivotTable.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pivottable/2.23.0/pivot.min.js"></script>

<!-- Script Logic: Global State, Associative Filter, Re-rendering -->
<script>
    // 1. Centralized Global BI State
    window.biState = {
        dataOriginal: [],     // Raw task data
        dataFiltrada: [],     // Currently filtered task data
        filtrosActivos: {},   // Applied filters in form: { column: value }
        columnsMeta: {},      // Column list and names
        uploadHistory: []     // History of uploads
    };

    // Chart.js global instances
    let charts = {
        topClientes: null,
        criticidad: null,
        personalServicio: null,
        tendencia: null,
        prorrateo: null
    };

    // DataTable instance reference
    let dataTableInstance = null;

    $(document).ready(function() {
        // Initialize tab visibility using inline styles (most specific, beats any CSS framework)
        initTabSystem();

        // Initialize File Upload Dropzone listeners
        initDropzone();

        // Load initial data
        loadDashboardData();
    });

    /**
     * Initialize tab system: hide all tabs, show only the first one
     */
    function initTabSystem() {
        // Hide ALL tab-content divs via inline style
        document.querySelectorAll('.tab-content').forEach(el => {
            el.style.display = 'none';
        });
        // Show Tab 1 via inline style
        const firstTab = document.getElementById('tab-adquisicion');
        if (firstTab) firstTab.style.display = 'block';

        // Hide filter bar on Tab 1
        const filterBar = document.querySelector('.bi-filter-bar');
        if (filterBar) filterBar.style.display = 'none';
    }

    /**
     * Switch between top navigation tabs
     */
    function switchTab(tabId) {
        // Hide ALL tab-content divs via inline style (beats any CSS framework)
        document.querySelectorAll('.tab-content').forEach(el => {
            el.style.display = 'none';
        });
        // Show the selected tab via inline style
        const targetTab = document.getElementById(tabId);
        if (targetTab) targetTab.style.display = 'block';

        // Show/hide global filter bar based on tab
        const filterBar = document.querySelector('.bi-filter-bar');
        if (filterBar) {
            if (tabId === 'tab-adquisicion' || tabId === 'tab-powerbi' || tabId === 'tab-especialistas') {
                filterBar.style.display = 'none';
            } else {
                filterBar.style.display = 'block';
            }
        }

        // Update button active states
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        const activeBtn = document.getElementById('btn-' + tabId);
        if (activeBtn) activeBtn.classList.add('active');

        // Wait for DOM to paint, then render components for the active tab
        setTimeout(() => {
            window.dispatchEvent(new Event('resize'));
            if (tabId === 'tab-pivot') {
                renderPivotTable();
            } else if (tabId === 'tab-dashboard') {
                renderCharts();
                renderPerformanceTable();
            } else if (tabId === 'tab-analitica') {
                renderCharts();
                renderPerformanceTable();
            } else if (tabId === 'tab-tickets') {
                renderTicketAnalysis();
            } else if (tabId === 'tab-tabla-maestra') {
                renderDataTable();
                if (dataTableInstance) {
                    dataTableInstance.columns.adjust().draw(false);
                }
            } else if (tabId === 'tab-powerbi') {
                pbRenderAll();
            } else if (tabId === 'tab-especialistas') {
                peRenderAll();
            } else if (tabId === 'tab-horas-recargos') {
                renderHorasRecargosTab();
            } else if (tabId === 'tab-cumplimiento') {
                renderCumplimientoTab();
            }
        }, 200);
    }

    /**
     * Initialize Dropzone interactions
     */
    function initDropzone() {
        const dropzone = $('#dropzone');
        const fileInput = $('#file-input');

        dropzone.on('click', function(e) {
            if (e.target !== fileInput[0]) {
                fileInput.click();
            }
        });

        fileInput.on('click', function(e) {
            e.stopPropagation();
        });

        fileInput.on('change', function(e) {
            if (this.files && this.files[0]) {
                handleFileUpload(this.files[0]);
            }
        });

        dropzone.on('dragover', function(e) {
            e.preventDefault();
            dropzone.addClass('border-indigo-500 bg-indigo-50/50 dark:bg-indigo-900/10');
        });

        dropzone.on('dragleave drop', function(e) {
            e.preventDefault();
            dropzone.removeClass('border-indigo-500 bg-indigo-50/50 dark:bg-indigo-900/10');
            if (e.type === 'drop' && e.originalEvent.dataTransfer.files.length) {
                handleFileUpload(e.originalEvent.dataTransfer.files[0]);
            }
        });
    }

    /**
     * Handle manual file upload to staging
     */
    function handleFileUpload(file) {
        let formData = new FormData();
        formData.append('file', file);
        formData.append('action', 'upload_temp');

        Swal.fire({
            title: 'Cargando archivo...',
            text: 'Por favor espere mientras se sube el Excel al servidor.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: 'api_novaiops.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                Swal.close();
                if (response.success) {
                    toastr.success(response.message);
                    showStagedFilePanel(response.filename);
                } else {
                    Swal.fire('Error', response.error, 'error');
                }
            },
            error: function() {
                Swal.close();
                Swal.fire('Error', 'No se pudo comunicar con el servidor para la carga.', 'error');
            }
        });
    }

    /**
     * Fast load the default excel file to staging
     */
    function loadDefaultExcel() {
        const btn = $('#btn-load-default');
        const originalText = btn.html();
        
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i> Cargando...');
        
        Swal.fire({
            title: 'Cargando Archivo Predeterminado...',
            text: 'Por favor espere mientras cargamos el Excel por defecto.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: 'api_novaiops.php',
            type: 'POST',
            data: { action: 'import_default_temp' },
            dataType: 'json',
            success: function(response) {
                Swal.close();
                btn.prop('disabled', false).html(originalText);
                if (response.success) {
                    toastr.success(response.message);
                    showStagedFilePanel(response.filename);
                } else {
                    Swal.fire('Error al cargar archivo', response.error, 'error');
                }
            },
            error: function() {
                Swal.close();
                btn.prop('disabled', false).html(originalText);
                Swal.fire('Error', 'No se pudo cargar el archivo predeterminado.', 'error');
            }
        });
    }

    /**
     * Show staged panel
     */
    function showStagedFilePanel(filename) {
        $('#staged-filename').text(filename);
        $('#staged-file-card').fadeIn();
        $('#analysis-results-panel').hide();
        // Disable OK button until analyzed
        $('#btn-confirm-stage').prop('disabled', true).addClass('opacity-50 cursor-not-allowed');
    }

    /**
     * Run analysis on the staged file
     */
    function analyzeStagedFile() {
        Swal.fire({
            title: 'Analizando archivo...',
            text: 'El servidor está procesando el archivo para identificar filas nuevas e incrementales.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: 'api_novaiops.php',
            type: 'POST',
            data: { action: 'analyze_stage' },
            dataType: 'json',
            success: function(response) {
                Swal.close();
                if (response.success) {
                    toastr.success('Análisis completado');
                    
                    const analysis = response.analysis;
                    
                    // Fill Tareas
                    $('#analysis-tickets-total').text(analysis['Reporte tareas'].total + ' filas');
                    $('#analysis-tickets-new').text('+' + analysis['Reporte tareas'].new);
                    $('#analysis-tickets-repeated').text(analysis['Reporte tareas'].repeated);
                    
                    // Fill Seguimientos
                    $('#analysis-seguimientos-total').text(analysis['Seguimientos'].total + ' filas');
                    $('#analysis-seguimientos-new').text('+' + analysis['Seguimientos'].new);
                    $('#analysis-seguimientos-repeated').text(analysis['Seguimientos'].repeated);
                    
                    // Fill Info
                    $('#analysis-info-total').text(analysis['Información reportes'].total + ' filas');
                    $('#analysis-info-new').text('+' + analysis['Información reportes'].new);
                    $('#analysis-info-repeated').text(analysis['Información reportes'].repeated);
                    
                    $('#analysis-results-panel').slideDown();
                    // Enable OK button
                    $('#btn-confirm-stage').prop('disabled', false).removeClass('opacity-50 cursor-not-allowed');
                } else {
                    Swal.fire('Error en el Análisis', response.error, 'error');
                }
            },
            error: function(xhr) {
                Swal.close();
                let errMsg = 'No se pudo analizar el archivo en el servidor.';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    errMsg = xhr.responseJSON.error;
                }
                Swal.fire('Error', errMsg, 'error');
            }
        });
    }

    /**
     * Confirm subida (OK)
     */
    function confirmStagedFile() {
        Swal.fire({
            title: 'Confirmando e Importando...',
            text: 'Escribiendo los registros incrementales en la base de datos.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: 'api_novaiops.php',
            type: 'POST',
            data: { action: 'confirm_stage' },
            dataType: 'json',
            success: function(response) {
                Swal.close();
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Subida Confirmada',
                        html: `<b>Se guardaron los datos con éxito:</b><br>
                               Tareas nuevas: +${response.tareas_nuevas} (repetidas: ${response.tareas_repetidas})<br>
                               Seguimientos nuevos: +${response.seguimientos_nuevos} (repetidas: ${response.seguimientos_repetidos})<br>
                               Información reportes nuevos: +${response.informacion_nuevos} (repetidas: ${response.informacion_repetidas})`
                    });
                    
                    $('#staged-file-card').fadeOut();
                    loadDashboardData();
                } else {
                    Swal.fire('Error al Confirmar', response.error, 'error');
                }
            },
            error: function() {
                Swal.close();
                Swal.fire('Error', 'No se pudo confirmar la importación.', 'error');
            }
        });
    }

    /**
     * Cancel staged file
     */
    function cancelStagedFile() {
        $.ajax({
            url: 'api_novaiops.php',
            type: 'POST',
            data: { action: 'cancel_stage' },
            dataType: 'json',
            success: function() {
                $('#staged-file-card').fadeOut();
                toastr.info('Carga cancelada');
            }
        });
    }

    /**
     * Open reset confirmation modal
     */
    function openResetModal() {
        Swal.fire({
            title: '¿Estás completamente seguro?',
            text: 'Esto eliminará todas las tablas de NovaIOPS en la base de datos y limpiará el historial de subida de archivos de manera irreversible.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, borrar todo',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'api_novaiops.php',
                    type: 'POST',
                    data: { action: 'reset_database' },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Eliminado', response.message, 'success');
                            loadDashboardData();
                        } else {
                            Swal.fire('Error', response.error, 'error');
                        }
                    }
                });
            }
        });
    }

    /**
     * Load dashboard data and history from backend
     */
    function loadDashboardData() {
        // Check if there is an already staged file in session
        $.ajax({
            url: 'api_novaiops.php?action=check_stage',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.has_stage) {
                    showStagedFilePanel(response.filename);
                }
            }
        });

        // Load history logs
        $.ajax({
            url: 'api_novaiops.php?action=get_history',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    renderHistoryTable(response.history);
                }
            }
        });

        // Load tasks data
        $.ajax({
            url: 'api_novaiops.php?action=get_data',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    if (response.initialized) {
                        $('#sync-status').removeClass('bg-red-100 text-red-800 bg-yellow-100 text-yellow-800')
                            .addClass('bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400')
                            .html('<span class="w-1.5 h-1.5 mr-1.5 rounded-full bg-green-500"></span> Base de Datos Sincronizada');
                        
                        window.biState.dataOriginal = response.tareas;
                        window.biState.seguimientosOriginal = response.seguimientos || [];
                        window.biState.informacionOriginal = response.informacion || [];
                        window.biState.dataFiltrada = [...response.tareas];
                        window.biState.columnsMeta = response.columns;
                        
                        // Setup the associative selectors
                        initSelectors();
                        
                        // Setup PowerBI date ranges and listeners
                        pbInitDateRanges();
                        pbRegisterListeners();

                        // Setup Especialistas tab selectors and listeners
                        peInitFilters();
                        peRegisterListeners();
                        
                        // Render components
                        dispatchStateChange();
                    } else {
                        $('#sync-status').removeClass('bg-green-100 text-green-800 bg-red-100 text-red-800')
                            .addClass('bg-yellow-100 text-yellow-800')
                            .html('<span class="w-1.5 h-1.5 mr-1.5 rounded-full bg-yellow-500"></span> Sin Datos Cargados');
                        
                        clearAllComponents();
                    }
                } else {
                    toastr.error('Error al cargar datos: ' + response.error);
                }
            },
            error: function() {
                toastr.error('Error de conexión al cargar datos del dashboard.');
            }
        });
    }

    /**
     * Render the uploads history list
     */
    function renderHistoryTable(history) {
        const tbody = $('#upload-history-list');
        tbody.empty();

        if (history.length === 0) {
            tbody.append('<tr><td colspan="4" class="py-4 text-center text-slate-400">Sin historial registrado</td></tr>');
            return;
        }

        history.forEach(log => {
            const statusClass = log.status === 'success' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
            const statusLabel = log.status === 'success' ? 'Éxito' : 'Fallo';
            
            const totalNuevas = parseInt(log.inserted_tareas || 0) + parseInt(log.inserted_seguimientos || 0) + parseInt(log.inserted_informacion || 0);
            const totalRepetidas = parseInt(log.repeated_tareas || 0) + parseInt(log.repeated_seguimientos || 0) + parseInt(log.repeated_informacion || 0);
            
            tbody.append(`
                <tr class="border-b border-slate-100 dark:border-slate-700/60 hover:bg-slate-50 dark:hover:bg-slate-700/30">
                    <td class="py-2.5 max-w-[120px] truncate font-medium text-slate-800 dark:text-slate-200" title="${log.filename}">
                        ${log.filename}
                    </td>
                    <td class="py-2.5 text-slate-400">
                        ${log.uploaded_at}
                    </td>
                    <td class="py-2.5 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-green-150 text-green-800 dark:bg-green-950/20 dark:text-green-400" title="Tareas: +${log.inserted_tareas}, Seg.: +${log.inserted_seguimientos}, Info: +${log.inserted_informacion}">
                            +${totalNuevas}
                        </span>
                    </td>
                    <td class="py-2.5 text-right">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400" title="Tareas: ${log.repeated_tareas}, Seg.: ${log.repeated_seguimientos}, Info: ${log.repeated_informacion}">
                            ${totalRepetidas}
                        </span>
                    </td>
                </tr>
            `);
        });
    }

    /**
     * Clear all filters in biState
     */
    function clearAllFilters() {
        window.biState.filtrosActivos = {};
        $('.bi-select').val('');
        // Clear text search inputs
        ['search-general','search-referencia','search-titulo'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
            const clr = document.getElementById(id + '-clear');
            if (clr) clr.style.display = 'none';
        });
        applyFiltersAndRebuildOptions();
        dispatchStateChange();
    }

    /**
     * Clear all components when DB is empty
     */
    function clearAllComponents() {
        window.biState.dataOriginal = [];
        window.biState.dataFiltrada = [];
        window.biState.filtrosActivos = {};
        
        $('.bi-select').empty().append('<option value="">Sin datos</option>');
        
        // Clear Datatable
        if (dataTableInstance) {
            dataTableInstance.clear().draw();
        }
        
        // Clear KPI
        $('#kpi-total-casos').text('0');
        $('#kpi-total-tiempo').text('0h 0m');
        $('#kpi-promedio-tiempo').text('0m');
        $('#kpi-porcentaje-completado').text('0%');
        
        // Destroy Charts
        Object.keys(charts).forEach(key => {
            if (charts[key]) {
                charts[key].destroy();
                charts[key] = null;
            }
        });
        
        // Clear Pivot
        $('#pivot-container').empty().text('Suba un archivo Excel para habilitar la matriz dinámica.');

        // Clear PowerBI components
        $('#pb-select-cliente').empty().append('<option value="">Sin datos</option>');
        $('#pb-select-contrato').empty().append('<option value="">Sin datos</option>');
        $('#pb-select-servicio').empty().append('<option value="">Sin datos</option>');
        $('#pb-select-mesano').empty().append('<option value="">Sin datos</option>');
        $('#pb-kpi-tiempo').text('0.00');
        $('#pb-table-body').empty().append('<tr><td colspan="6" style="padding: 2rem; text-align: center; color: #94a3b8; font-style: italic;">Sin datos</td></tr>');
        
        Object.keys(window.pbCharts).forEach(key => {
            if (window.pbCharts[key]) {
                window.pbCharts[key].destroy();
                window.pbCharts[key] = null;
            }
        });

        // Clear Specialists components
        $('#pe-select-especialista').empty().append('<option value="">Sin datos</option>');
        $('#pe-select-ano').empty().append('<option value="">Sin datos</option>');
        $('#pe-select-mes').empty().append('<option value="">Sin datos</option>');
        $('#pe-select-dia').empty().append('<option value="">Sin datos</option>');
        $('#pe-kpi-recuento').text('0');
        $('#pe-kpi-hojas').text('0');
        $('#pe-gauge-value').text('0.00');
        
        Object.keys(window.peCharts).forEach(key => {
            if (window.peCharts[key]) {
                window.peCharts[key].destroy();
                window.peCharts[key] = null;
            }
        });
    }

    /**
     * Initialize selectors for the first time
     */
    function initSelectors() {
        // Register change handler for all dropdown selectors
        $('.bi-select').off('change').on('change', function() {
            const col = this.id.replace('select-', '');
            let colName = getRealColName(col);
            const val = $(this).val();
            if (val === '') {
                delete window.biState.filtrosActivos[colName];
            } else {
                window.biState.filtrosActivos[colName] = val;
            }
            applyFiltersAndRebuildOptions();
            dispatchStateChange();
        });

        applyFiltersAndRebuildOptions();
    }

    /**
     * Handle text search input — debounced
     */
    let _searchDebounce = null;
    function onSearchInput() {
        // Show/hide clear buttons
        ['search-general','search-referencia','search-titulo'].forEach(id => {
            const el = document.getElementById(id);
            const clr = document.getElementById(id + '-clear');
            if (el && clr) clr.style.display = el.value.trim() ? 'inline' : 'none';
        });
        clearTimeout(_searchDebounce);
        _searchDebounce = setTimeout(() => {
            applyFiltersAndRebuildOptions();
            dispatchStateChange();
        }, 280);
    }

    /**
     * Clear a single search input
     */
    function clearSearchInput(id) {
        const el = document.getElementById(id);
        if (el) el.value = '';
        const clr = document.getElementById(id + '-clear');
        if (clr) clr.style.display = 'none';
        applyFiltersAndRebuildOptions();
        dispatchStateChange();
    }

    /**
     * Map a select ID suffix to the actual SQL column name in window.biState.dataOriginal
     */
    function getRealColName(colSuffix) {
        switch (colSuffix) {
            case 'cliente': return 'cliente';
            case 'contrato': return 'contrato';
            case 'servicio': return 'servicio';
            case 'responsable': return 'assigned_to_fullname';
            case 'estado': return 'status_name';
            case 'criticidad': return 'criticality_name';
            default: return colSuffix;
        }
    }

    /**
     * Get Nice Display Label for SQL column
     */
    function getNiceLabel(colSuffix) {
        switch (colSuffix) {
            case 'cliente': return 'Cliente';
            case 'contrato': return 'Contrato';
            case 'servicio': return 'Servicio';
            case 'responsable': return 'Personal Asignado';
            case 'estado': return 'Estado';
            case 'criticidad': return 'Criticidad';
            default: return colSuffix;
        }
    }

    /**
     * Recompute dataFiltrada and update selectors' options keeping them associative
     */
    function applyFiltersAndRebuildOptions() {
        const { dataOriginal, filtrosActivos } = window.biState;

        // Get text search values
        const qGeneral    = (document.getElementById('search-general')?.value    || '').toLowerCase().trim();
        const qReferencia = (document.getElementById('search-referencia')?.value || '').toLowerCase().trim();
        const qTitulo     = (document.getElementById('search-titulo')?.value     || '').toLowerCase().trim();

        // Columns to search for "general": all string fields
        const GENERAL_COLS = ['id_tarea','referencia','cliente','contrato','codigo_de_contrato','servicio',
            'frecuencia','arranda_ticket_name','assigned_by_fullname','assigned_to_fullname',
            'assigned_by_username','assigned_to_username','assigned_to_area_name',
            'criticality_name','descripcion','titulo','parent_task_id','parent_task_title',
            'requirement_incident_name','type_name','status_name','user_create_fullname'];

        // 1. Calculate new dataFiltrada applying dropdown filters + text searches
        window.biState.dataFiltrada = dataOriginal.filter(row => {
            // Dropdown filters (exact match)
            for (let col in filtrosActivos) {
                if (row[col] !== filtrosActivos[col]) return false;
            }
            // General search (any field contains query)
            if (qGeneral) {
                const hit = GENERAL_COLS.some(c => String(row[c] || '').toLowerCase().includes(qGeneral));
                if (!hit) return false;
            }
            // Referencia / Ticket search
            if (qReferencia) {
                const refVal = String(row['referencia'] || row['id_tarea'] || row['arranda_ticket_name'] || '').toLowerCase();
                if (!refVal.includes(qReferencia)) return false;
            }
            // Título / Descripción search
            if (qTitulo) {
                const titVal = String(row['titulo'] || row['descripcion'] || row['arranda_ticket_name'] || '').toLowerCase();
                if (!titVal.includes(qTitulo)) return false;
            }
            return true;
        });

        // 2. Re-render each dropdown selector options
        const selectorsToUpdate = ['cliente', 'contrato', 'servicio', 'responsable', 'estado', 'criticidad'];
        selectorsToUpdate.forEach(col => {
            const realCol = getRealColName(col);
            const selectEl = $(`#select-${col}`);
            const currentSelected = filtrosActivos[realCol] || '';

            // Compatible data: all filters except this column's, but INCLUDE text searches
            const compatibleData = dataOriginal.filter(row => {
                for (let activeCol in filtrosActivos) {
                    if (activeCol === realCol) continue;
                    if (row[activeCol] !== filtrosActivos[activeCol]) return false;
                }
                if (qGeneral) {
                    const hit = GENERAL_COLS.some(c => String(row[c] || '').toLowerCase().includes(qGeneral));
                    if (!hit) return false;
                }
                if (qReferencia) {
                    const refVal = String(row['referencia'] || row['id_tarea'] || row['arranda_ticket_name'] || '').toLowerCase();
                    if (!refVal.includes(qReferencia)) return false;
                }
                if (qTitulo) {
                    const titVal = String(row['titulo'] || row['descripcion'] || row['arranda_ticket_name'] || '').toLowerCase();
                    if (!titVal.includes(qTitulo)) return false;
                }
                return true;
            });

            const compatibleSet = new Set(compatibleData.map(r => r[realCol]).filter(Boolean));
            const sortedValues = Array.from(compatibleSet).sort((a, b) => a.localeCompare(b));

            selectEl.empty();
            selectEl.append(`<option value="">Todos (${sortedValues.length})</option>`);
            sortedValues.forEach(val => {
                const isSelected = currentSelected === val;
                if (isSelected) {
                    selectEl.append(`<option value="${val}" selected style="font-weight:bold;background:#e0e7ff;">${val}</option>`);
                } else {
                    selectEl.append(`<option value="${val}">${val}</option>`);
                }
            });
        });
    }

    /**
     * Dispatch event when active filters or database changes
     */
    function dispatchStateChange() {
        const data = window.biState.dataFiltrada;
        
        $('#table-row-count').text(`Mostrando ${data.length} de ${window.biState.dataOriginal.length} registros`);

        // 1. Refresh DataTable
        renderDataTable();

        // 2. Refresh KPIs
        renderKPIs();

        // 3. Refresh Charts
        renderCharts();

        // 4. Refresh Pivot Table
        renderPivotTable();

        // 5. Refresh Performance analytics
        renderPerformanceTable();

        // 6. Refresh Ticket Lifecycle Analysis
        renderTicketAnalysis();

        // 7. Refresh Horas y Recargos Calculation (Tab 9)
        renderHorasRecargosTab();

        // 8. Refresh Workload & Daily Compliance Analysis (Tab 10)
        renderCumplimientoTab();
    }

    /**
     * Format minutes to readable HHh MMm
     */
    function formatMinutes(minVal) {
        if (isNaN(minVal) || minVal === null || minVal === undefined) return '0h 0m';
        const hrs = Math.floor(minVal / 60);
        const mins = Math.round(minVal % 60);
        return `${hrs}h ${mins}m`;
    }

    /**
     * Render KPI cards
     */
    function renderKPIs() {
        const data = window.biState.dataFiltrada;
        
        // KPI 1: Total Casos
        $('#kpi-total-casos').text(data.length);
        
        // KPI 2: Total Time (Sum of execution minutes)
        let totalMin = 0;
        let completedCount = 0;
        
        data.forEach(row => {
            totalMin += parseFloat(row.total_minutos_en_estado_ejecucion) || 0;
            if (row.status_name === 'Completado' || row.status_name === 'Finalizado') {
                completedCount++;
            }
        });
        
        $('#kpi-total-tiempo').text(formatMinutes(totalMin));
        
        // KPI 3: Promedio de atención (total minutes / count)
        const avgMin = data.length > 0 ? Math.round(totalMin / data.length) : 0;
        $('#kpi-promedio-tiempo').text(`${avgMin}m`);
        
        // KPI 4: Porcentaje completado
        const pct = data.length > 0 ? Math.round((completedCount / data.length) * 100) : 0;
        $('#kpi-porcentaje-completado').text(`${pct}%`);
    }

    /**
     * Render DataTable with custom layouts & export features
     */
    function renderDataTable() {
        const data = window.biState.dataFiltrada;
        const columnsMeta = window.biState.columnsMeta['novaiops_reporte_tareas'] || [];
        
        if (columnsMeta.length === 0) return;

        // If datatable already initialized, clear it, load new data and redraw
        if ($.fn.DataTable.isDataTable('#master-table')) {
            dataTableInstance.clear();
            dataTableInstance.rows.add(data);
            dataTableInstance.draw();
            return;
        }

        // Initialize DataTable
        const tableColumns = columnsMeta.map(col => {
            return {
                title: col.original_label,
                data: col.column_name,
                defaultContent: ''
            };
        });

        dataTableInstance = $('#master-table').DataTable({
            data: data,
            columns: tableColumns,
            pageLength: 10,
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'copyHtml5',
                    text: '<i class="far fa-copy mr-1"></i> Copiar',
                    className: 'btn btn-xs btn-outline-secondary'
                },
                {
                    extend: 'csvHtml5',
                    text: '<i class="fas fa-file-csv mr-1"></i> CSV',
                    className: 'btn btn-xs btn-outline-secondary'
                },
                {
                    extend: 'excelHtml5',
                    text: '<i class="far fa-file-excel mr-1"></i> Excel',
                    className: 'btn btn-xs btn-outline-success font-weight-bold'
                }
            ],
            language: {
                search: "Buscar:",
                lengthMenu: "Mostrar _MENU_ registros",
                info: "Mostrando _START_ a _END_ de _TOTAL_ registros",
                infoEmpty: "Mostrando 0 a 0 de 0 registros",
                infoFiltered: "(filtrado de _MAX_ registros totales)",
                paginate: {
                    first: "Primero",
                    previous: "Anterior",
                    next: "Siguiente",
                    last: "Último"
                }
            }
        });
    }

    /**
     * Render all charts
     */
    function renderCharts() {
        if (typeof Chart !== 'function') {
            console.error('Chart.js library is not loaded.');
            return;
        }

        const data = window.biState.dataFiltrada;
        const isDark = $('body').hasClass('dark-mode');
        const textColor = isDark ? '#cbd5e1' : '#334155';
        const gridColor = isDark ? 'rgba(71, 85, 105, 0.2)' : 'rgba(226, 232, 240, 0.8)';

        // Render Tab 2 charts only when Tab 2 is visible
        if ($('#tab-dashboard').is(':visible')) {
            // 1. Horizontal Bar: Top 10 Clientes (Tiempo Acumulado)
            const clientTime = {};
            data.forEach(r => {
                const cli = r.cliente || 'Sin Cliente';
                const mins = parseFloat(r.total_minutos_en_estado_ejecucion) || 0;
                clientTime[cli] = (clientTime[cli] || 0) + (mins / 60); // in hours
            });
            
            const sortedClients = Object.entries(clientTime)
                .sort((a, b) => b[1] - a[1])
                .slice(0, 10);

            if (charts.topClientes) charts.topClientes.destroy();
            charts.topClientes = new Chart(document.getElementById('chart-top-clientes'), {
                type: 'bar',
                data: {
                    labels: sortedClients.map(c => c[0].length > 25 ? c[0].substring(0, 25) + '...' : c[0]),
                    datasets: [{
                        label: 'Horas Totales',
                        data: sortedClients.map(c => Math.round(c[1] * 10) / 10),
                        backgroundColor: 'rgba(79, 70, 229, 0.85)',
                        hoverBackgroundColor: 'rgba(79, 70, 229, 1)',
                        borderRadius: 6
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                    },
                    scales: {
                        x: {
                            grid: { color: gridColor },
                            ticks: { color: textColor }
                        },
                        y: {
                            grid: { display: false },
                            ticks: { color: textColor }
                        }
                    }
                }
            });

            // 2. Donut: Criticidad
            const critCounts = {};
            data.forEach(r => {
                const crit = r.criticality_name || 'Bajo';
                critCounts[crit] = (critCounts[crit] || 0) + 1;
            });

            if (charts.criticidad) charts.criticidad.destroy();
            charts.criticidad = new Chart(document.getElementById('chart-criticidad'), {
                type: 'doughnut',
                data: {
                    labels: Object.keys(critCounts),
                    datasets: [{
                        data: Object.values(critCounts),
                        backgroundColor: [
                            'rgba(244, 63, 94, 0.8)',   // Rose (High/Critical)
                            'rgba(245, 158, 11, 0.8)',  // Amber (Medium)
                            'rgba(14, 165, 233, 0.8)',  // Sky (Low)
                            'rgba(148, 163, 184, 0.8)'  // Slate (Other)
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: { color: textColor }
                        }
                    }
                }
            });

            // 3. Stacked Horizontal Bar: Personal vs Horas segmentado por Servicio
            const techServices = {}; // { tech: { service: hours } }
            const allServices = new Set();
            
            data.forEach(r => {
                const tech = r.assigned_to_fullname || 'Sin Asignar';
                const srv = r.servicio || 'Otro';
                const hrs = (parseFloat(r.total_minutos_en_estado_ejecucion) || 0) / 60;
                
                allServices.add(srv);
                if (!techServices[tech]) techServices[tech] = {};
                techServices[tech][srv] = (techServices[tech][srv] || 0) + hrs;
            });

            // Take top 15 techs with highest total hours
            const sortedTechs = Object.entries(techServices)
                .map(([tech, srvs]) => {
                    const total = Object.values(srvs).reduce((a, b) => a + b, 0);
                    return { tech, srvs, total };
                })
                .sort((a, b) => b.total - a.total)
                .slice(0, 15);

            const serviceColors = [
                'rgba(79, 70, 229, 0.7)',  // Indigo
                'rgba(16, 185, 129, 0.7)', // Emerald
                'rgba(245, 158, 11, 0.7)', // Amber
                'rgba(239, 68, 68, 0.7)',  // Red
                'rgba(6, 182, 212, 0.7)',  // Cyan
                'rgba(139, 92, 246, 0.7)', // Purple
                'rgba(236, 72, 153, 0.7)'  // Pink
            ];

            const serviceList = Array.from(allServices);
            const datasets = serviceList.map((srv, idx) => {
                return {
                    label: srv,
                    data: sortedTechs.map(t => Math.round((t.srvs[srv] || 0) * 10) / 10),
                    backgroundColor: serviceColors[idx % serviceColors.length],
                    stack: 'Stack 0'
                };
            });

            if (charts.personalServicio) charts.personalServicio.destroy();
            charts.personalServicio = new Chart(document.getElementById('chart-personal-servicio'), {
                type: 'bar',
                data: {
                    labels: sortedTechs.map(t => t.tech.length > 20 ? t.tech.substring(0, 20) + '...' : t.tech),
                    datasets: datasets
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: { boxWidth: 12, color: textColor }
                        }
                    },
                    scales: {
                        x: {
                            stacked: true,
                            grid: { color: gridColor },
                            ticks: { color: textColor }
                        },
                        y: {
                            stacked: true,
                            grid: { display: false },
                            ticks: { color: textColor }
                        }
                    }
                }
            });

            // 4. Line Chart: Tendencia temporal del ingreso de tareas
            const dateCounts = {};
            data.forEach(r => {
                const dateObj = parseSpanishDate(r.inicio_tarea);
                if (dateObj) {
                    const key = dateObj.toISOString().split('T')[0]; // YYYY-MM-DD
                    dateCounts[key] = (dateCounts[key] || 0) + 1;
                }
            });

            const sortedDates = Object.entries(dateCounts)
                .sort((a, b) => a[0].localeCompare(b[0]));

            if (charts.tendencia) charts.tendencia.destroy();
            charts.tendencia = new Chart(document.getElementById('chart-tendencia'), {
                type: 'line',
                data: {
                    labels: sortedDates.map(d => d[0]),
                    datasets: [{
                        label: 'Tareas Ingresadas',
                        data: sortedDates.map(d => d[1]),
                        borderColor: '#4f46e5',
                        backgroundColor: 'rgba(79, 70, 229, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3,
                        pointBackgroundColor: '#4f46e5'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: {
                            grid: { color: gridColor },
                            ticks: { color: textColor }
                        },
                        y: {
                            grid: { color: gridColor },
                            ticks: { color: textColor }
                        }
                    }
                }
            });
        }

        // Render Tab 4 chart only when Tab 4 is visible
        if ($('#tab-analitica').is(':visible')) {
            // 5. Pie/Donut: Prorrateo de Horas (Tab 4)
            let standardSum = 0;
            let nocturnoSum = 0;
            let finSemanaSum = 0;

            data.forEach(r => {
                standardSum += parseFloat(r.total_minutos_ejecucion_efectivo_standard) || 0;
                nocturnoSum += parseFloat(r.total_minutos_ejecucion_efectivo_nocturno) || 0;
                finSemanaSum += parseFloat(r.total_minutos_ejecucion_efectivo_fin_semana) || 0;
            });

            $('#lbl-tiempo-standard').text(formatMinutes(standardSum));
            $('#lbl-tiempo-nocturno').text(formatMinutes(nocturnoSum));
            $('#lbl-tiempo-fin-semana').text(formatMinutes(finSemanaSum));

            if (charts.prorrateo) charts.prorrateo.destroy();
            charts.prorrateo = new Chart(document.getElementById('chart-prorrateo'), {
                type: 'doughnut',
                data: {
                    labels: ['Estándar', 'Nocturno', 'Fin de Semana'],
                    datasets: [{
                        data: [standardSum, nocturnoSum, finSemanaSum],
                        backgroundColor: [
                            '#0ea5e9', // Sky (Standard)
                            '#7c3aed', // Violet (Nocturno)
                            '#f59e0b'  // Amber (Weekend)
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        }
    }

    /**
     * Robust parser for Spanish Excel date string e.g. "6 abr 2026 10:52:26"
     */
    function parseSpanishDate(str) {
        if (!str) return null;
        const months = {
            'ene': 0, 'feb': 1, 'mar': 2, 'abr': 3, 'may': 4, 'jun': 5,
            'jul': 6, 'ago': 7, 'sep': 8, 'oct': 9, 'nov': 10, 'dic': 11
        };
        // Normalize
        const cleaned = str.toString().toLowerCase().trim();
        const parts = cleaned.split(/\s+/);
        if (parts.length >= 3) {
            const day = parseInt(parts[0]);
            const monthStr = parts[1].substring(0, 3); // take first 3 chars to handle "abr" or "abril"
            const year = parseInt(parts[2]);
            const month = months[monthStr] !== undefined ? months[monthStr] : 0;
            
            // Extract hour if present
            let hour = 0, min = 0, sec = 0;
            if (parts.length >= 4) {
                const timeParts = parts[3].split(':');
                if (timeParts.length >= 2) {
                    hour = parseInt(timeParts[0]);
                    min = parseInt(timeParts[1]);
                    if (timeParts.length >= 3) {
                        sec = parseInt(timeParts[2]);
                    }
                }
            }
            const parsedD = new Date(year, month, day, hour, min, sec);
            return isNaN(parsedD.getTime()) ? null : parsedD;
        }
        
        const d = new Date(str);
        return isNaN(d.getTime()) ? null : d;
    }

    /**
     * Render Pivot Table UI dynamically
     */
    function renderPivotTable() {
        const data = window.biState.dataFiltrada;
        
        if (data.length === 0) {
            $('#pivot-container').empty().text('Sin datos para renderizar la matriz.');
            return;
        }

        // Map database columns to friendly spanish headers for Pivot Table UI
        const mappedData = data.map(row => {
            return {
                "Cliente": row.cliente || 'Sin Cliente',
                "Contrato": row.contrato || 'Sin Contrato',
                "Código Contrato": row.codigo_de_contrato || 'N/A',
                "Servicio": row.servicio || 'Sin Servicio',
                "Responsable": row.assigned_to_fullname || 'Sin Asignar',
                "Área Técnica": row.assigned_to_area_name || 'N/A',
                "Estado": row.status_name || 'N/A',
                "Criticidad": row.criticality_name || 'N/A',
                "Tiempo Ejecución (Min)": parseFloat(row.total_minutos_en_estado_ejecucion) || 0,
                "Tiempo Estándar (Min)": parseFloat(row.total_minutos_ejecucion_efectivo_standard) || 0,
                "Tiempo Nocturno (Min)": parseFloat(row.total_minutos_ejecucion_efectivo_nocturno) || 0,
                "Tiempo Fin de Semana (Min)": parseFloat(row.total_minutos_ejecucion_efectivo_fin_semana) || 0
            };
        });

        // Try to retrieve previous options from UI container to maintain config
        let currentOptions = $("#pivot-container").data("pivotUIOptions");
        
        let config = {
            rows: ["Cliente"],
            cols: ["Servicio"],
            vals: ["Tiempo Ejecución (Min)"],
            aggregatorName: "Sum",
            rendererName: "Table Heatmap"
        };

        if (currentOptions) {
            // Keep user dragging configurations but inject new filtered data
            config = $.extend(true, {}, currentOptions);
        }

        $("#pivot-container").pivotUI(mappedData, config, true);
    }

    /**
     * Render Tab 4 Personnel Efficiency Scoring Table
     */
    function renderPerformanceTable() {
        const data = window.biState.dataFiltrada;
        
        if (data.length === 0) {
            $('#tech-scoring-list').empty().append('<tr><td colspan="6" class="py-4 text-center text-slate-400">Sin datos</td></tr>');
            return;
        }

        // Calculate global average execution time (minutes per task)
        let totalGlobalMins = 0;
        data.forEach(r => {
            totalGlobalMins += parseFloat(r.total_minutos_en_estado_ejecucion) || 0;
        });
        const globalAvg = data.length > 0 ? (totalGlobalMins / data.length) : 0;

        // Group statistics by tech
        const techStats = {}; // { name: { count, totalMins, completed } }
        data.forEach(r => {
            const tech = r.assigned_to_fullname || 'Sin Asignar';
            const mins = parseFloat(r.total_minutos_en_estado_ejecucion) || 0;
            const isCompleted = r.status_name === 'Completado' || r.status_name === 'Finalizado';

            if (!techStats[tech]) {
                techStats[tech] = { count: 0, totalMins: 0, completed: 0 };
            }
            techStats[tech].count++;
            techStats[tech].totalMins += mins;
            if (isCompleted) {
                techStats[tech].completed++;
            }
        });

        const sortedScoring = Object.entries(techStats).map(([name, stat]) => {
            const avg = stat.count > 0 ? (stat.totalMins / stat.count) : 0;
            // Deviation from global average
            const deviation = globalAvg > 0 ? ((avg - globalAvg) / globalAvg * 100) : 0;
            
            // Efficiency rating:
            // High efficiency: positive resolution rate and low average execution time compared to global average
            // If deviation is negative (takes less time), efficiency goes up.
            // If resolution rate is high, efficiency goes up.
            let rating = 'Estándar';
            let ratingClass = 'bg-slate-100 text-slate-800';
            if (deviation < -15 && stat.count >= 3) {
                rating = 'Sobresaliente';
                ratingClass = 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
            } else if (deviation > 20) {
                rating = 'Lento';
                ratingClass = 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
            } else if (stat.count < 3) {
                rating = 'En Inducción';
                ratingClass = 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400';
            }

            return { name, stat, avg, deviation, rating, ratingClass };
        }).sort((a, b) => b.stat.count - a.stat.count);

        const tbody = $('#tech-scoring-list');
        tbody.empty();

        sortedScoring.forEach(row => {
            const devText = row.deviation > 0 
                ? `+${Math.round(row.deviation)}%` 
                : `${Math.round(row.deviation)}%`;
            const devClass = row.deviation > 0 ? 'text-red-500' : 'text-green-500 font-bold';

            tbody.append(`
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/20">
                    <td class="py-3 font-medium text-slate-800 dark:text-slate-200">${row.name}</td>
                    <td class="py-3 text-center font-semibold">${row.stat.count}</td>
                    <td class="py-3 text-right text-slate-500">${formatMinutes(row.stat.totalMins)}</td>
                    <td class="py-3 text-right font-medium">${Math.round(row.avg)} min</td>
                    <td class="py-3 text-right ${devClass}">${devText}</td>
                    <td class="py-3 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold ${row.ratingClass}">
                            ${row.rating}
                        </span>
                    </td>
                </tr>
            `);
        });
    }

    /**
     * Render Tab 5: Ticket Lifecycle Analysis (ITIL-style hierarchical view)
     */
    window.showChildTasksInAnalysis = true;

    function toggleChildTasksVisibility() {
        window.showChildTasksInAnalysis = !window.showChildTasksInAnalysis;
        const btn = document.getElementById('btn-toggle-hijas');
        const lbl = document.getElementById('lbl-status-hijas');
        if (window.showChildTasksInAnalysis) {
            if (btn) btn.style.background = '#ffffff';
            if (lbl) {
                lbl.textContent = 'VISIBLES (ON)';
                lbl.style.color = '#16a34a';
            }
            if (window.toastr) toastr.info('Tareas hijas VISIBLES debajo de cada ticket padre');
        } else {
            if (btn) btn.style.background = '#fffbe6';
            if (lbl) {
                lbl.textContent = 'OCULTAS (OFF)';
                lbl.style.color = '#d97706';
            }
            if (window.toastr) toastr.warning('Tareas hijas OCULTADAS (Mostrando solo Tickets Padres)');
        }
        renderTicketAnalysis();
    }

    /**
     * Render Tab 5: Ticket Lifecycle Analysis (ITIL-style hierarchical view with parent & nested children)
     */
    window.openTicketDetailModal = function(identifier) {
    if (!identifier) return;
    const target = String(identifier).trim().toLowerCase();
    const list = (window.biState && window.biState.dataOriginal) ? window.biState.dataOriginal : [];
    
    const ticket = list.find(r => 
        String(r.referencia || '').trim().toLowerCase() === target ||
        String(r.id_tarea || '').trim().toLowerCase() === target ||
        String(r.arranda_ticket_name || '').trim().toLowerCase() === target ||
        String(r.ticket_aranda || '').trim().toLowerCase() === target ||
        String(r.aranda_ticket || '').trim().toLowerCase() === target
    );

    if (!ticket) {
        if (window.toastr) toastr.warning('No se encontraron detalles adicionales para: ' + identifier);
        else alert('No se encontraron detalles para: ' + identifier);
        return;
    }

    const id = ticket.id_tarea || '-';
    const ref = ticket.referencia || '-';
    const ticketAranda = ticket.arranda_ticket_name || ticket.ticket_aranda || ticket.aranda_ticket || ticket.aranda || '-';
    const titulo = ticket.titulo || ticket.arranda_ticket_name || ticket.ticket_aranda || 'Sin Título';
    const cliente = ticket.cliente || 'Sin Cliente';
    const contrato = ticket.contrato || ticket.codigo_de_contrato || 'Sin Contrato';
    const servicio = ticket.servicio || 'Sin Servicio';
    const tecnico = ticket.assigned_to_fullname || 'Sin Asignar';
    const status = ticket.status_name || 'Desconocido';
    const tipo = ticket.type_name || 'N/A';
    const descripcion = (ticket.descripcion || '').trim();

    function fmtMins(m) {
        const val = parseFloat(m) || 0;
        if (val === 0) return '0m';
        const h = Math.floor(val / 60), mn = Math.round(val % 60);
        return h > 0 ? `${h}h ${mn}m` : `${mn}m`;
    }

    // Find child tasks if this ticket is a parent
    const childTasks = list.filter(r => String(r.parent_task_id || r.id_tarea_padre || '').trim() === String(id).trim());

    let childrenHtml = '';
    if (childTasks.length > 0) {
        childrenHtml = `
        <div style="margin-top:.75rem;padding-top:.75rem;border-top:1px solid #cbd5e1;text-align:left;">
            <span style="font-weight:800;color:#0f172a;font-size:0.75rem;display:block;margin-bottom:.4rem;">
                <i class="fas fa-sitemap text-indigo-600 mr-1"></i> Tareas Hijas Asociadas (${childTasks.length}):
            </span>
            <table style="width:100%;border-collapse:collapse;font-size:0.7rem;background:#ffffff;border:1px solid #e2e8f0;border-radius:0.375rem;">
                <thead>
                    <tr style="background:#f1f5f9;color:#475569;font-weight:700;">
                        <th style="padding:.35rem .5rem;text-align:left;">ID Hijo</th>
                        <th style="padding:.35rem .5rem;text-align:left;">Ref</th>
                        <th style="padding:.35rem .5rem;text-align:left;">Ticket Aranda</th>
                        <th style="padding:.35rem .5rem;text-align:left;">Título / Descripción</th>
                        <th style="padding:.35rem .5rem;text-align:left;">Técnico</th>
                        <th style="padding:.35rem .5rem;text-align:right;">T. Ejecución</th>
                    </tr>
                </thead>
                <tbody>
                    ${childTasks.map(ch => {
                        const chAranda = ch.arranda_ticket_name || ch.ticket_aranda || ch.aranda_ticket || ch.aranda || '-';
                        return `
                    <tr style="border-t:1px solid #f1f5f9;">
                        <td style="padding:.35rem .5rem;font-weight:700;color:#16a34a;">#${ch.id_tarea}</td>
                        <td style="padding:.35rem .5rem;font-weight:700;color:#0369a1;">${ch.referencia||'-'}</td>
                        <td style="padding:.35rem .5rem;font-weight:700;color:#4338ca;">${chAranda}</td>
                        <td style="padding:.35rem .5rem;color:#334155;">${(ch.titulo||ch.descripcion||'-').substring(0,40)}</td>
                        <td style="padding:.35rem .5rem;color:#475569;">${ch.assigned_to_fullname||'-'}</td>
                        <td style="padding:.35rem .5rem;text-align:right;font-weight:700;">${fmtMins(ch.total_minutos_en_estado_ejecucion)}</td>
                    </tr>
                    `;}).join('')}
                </tbody>
            </table>
        </div>`;
    }

    const htmlContent = `
    <div style="text-align:left;font-family:ui-sans-serif,system-ui,sans-serif;color:#1e293b;font-size:0.8rem;line-height:1.4;">
        <div style="background:#f8fafc;padding:.6rem .8rem;border-radius:.375rem;border:1px solid #e2e8f0;margin-bottom:.75rem;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.4rem;">
                <span style="font-weight:800;font-size:0.9rem;color:#0369a1;">
                    <i class="fas fa-ticket-alt mr-1"></i> #${id} (Ref: ${ref})
                </span>
                <span style="background:#e0f2fe;color:#0369a1;padding:.2rem .6rem;border-radius:9999px;font-size:0.68rem;font-weight:700;">
                    ${status}
                </span>
            </div>
            <div style="font-weight:700;color:#0f172a;font-size:0.82rem;margin-bottom:.4rem;">
                ${titulo}
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin-bottom:.75rem;">
            <div style="background:#eef2ff;padding:.5rem .7rem;border-radius:.375rem;border:1px solid #c7d2fe;">
                <span style="font-size:0.65rem;color:#4338ca;font-weight:800;display:block;">TICKET ARANDA:</span>
                <span style="font-weight:800;color:#3730a3;font-size:0.78rem;">${ticketAranda}</span>
            </div>
            <div style="background:#f1f5f9;padding:.5rem .7rem;border-radius:.375rem;">
                <span style="font-size:0.65rem;color:#64748b;font-weight:700;display:block;">CLIENTE:</span>
                <span style="font-weight:700;color:#0f172a;font-size:0.75rem;">${cliente}</span>
            </div>
            <div style="background:#e0f2fe;padding:.5rem .7rem;border-radius:.375rem;border:1px solid #bae6fd;">
                <span style="font-size:0.65rem;color:#0369a1;font-weight:800;display:block;">CONTRATO ASOCIADO:</span>
                <span style="font-weight:800;color:#0284c7;font-size:0.78rem;">${contrato}</span>
            </div>
            <div style="background:#f0fdf4;padding:.5rem .7rem;border-radius:.375rem;border:1px solid #bbf7d0;">
                <span style="font-size:0.65rem;color:#15803d;font-weight:800;display:block;">SERVICIO ASOCIADO:</span>
                <span style="font-weight:800;color:#16a34a;font-size:0.78rem;">${servicio}</span>
            </div>
            <div style="background:#f1f5f9;padding:.5rem .7rem;border-radius:.375rem;grid-column: span 2;">
                <span style="font-size:0.65rem;color:#64748b;font-weight:700;display:block;">TÉCNICO ASIGNADO:</span>
                <span style="font-weight:700;color:#0f172a;font-size:0.75rem;">${tecnico}</span>
            </div>
        </div>

        <div style="background:#ffffff;padding:.75rem;border-radius:.375rem;border:1px solid #cbd5e1;margin-bottom:.75rem;">
            <span style="font-weight:800;color:#4338ca;font-size:0.75rem;display:block;margin-bottom:.3rem;">
                <i class="fas fa-align-left mr-1"></i> Descripción Completa del Ticket:
            </span>
            <div style="max-height:160px;overflow-y:auto;color:#334155;font-size:0.72rem;line-height:1.45;white-space:pre-wrap;background:#f8fafc;padding:.5rem .75rem;border-radius:.25rem;border:1px solid #e2e8f0;">${descripcion || 'Sin descripción registrada en la tarea.'}</div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(4, 1fr);gap:.4rem;text-align:center;">
            <div style="background:#f0f9ff;padding:.35rem;border-radius:.25rem;border:1px solid #bae6fd;">
                <span style="font-size:0.6rem;color:#0369a1;font-weight:700;display:block;">T. Abierto:</span>
                <span style="font-size:0.75rem;font-weight:800;color:#0284c7;">${fmtMins(ticket.total_minutos_abierta)}</span>
            </div>
            <div style="background:#f0fdf4;padding:.35rem;border-radius:.25rem;border:1px solid #bbf7d0;">
                <span style="font-size:0.6rem;color:#15803d;font-weight:700;display:block;">T. Ejecución:</span>
                <span style="font-size:0.75rem;font-weight:800;color:#16a34a;">${fmtMins(ticket.total_minutos_en_estado_ejecucion)}</span>
            </div>
            <div style="background:#f5f3ff;padding:.35rem;border-radius:.25rem;border:1px solid #ddd6fe;">
                <span style="font-size:0.6rem;color:#6d28d9;font-weight:700;display:block;">H. Nocturna:</span>
                <span style="font-size:0.75rem;font-weight:800;color:#7c3aed;">${fmtMins(ticket.total_minutos_ejecucion_efectivo_nocturno)}</span>
            </div>
            <div style="background:#fffbeb;padding:.35rem;border-radius:.25rem;border:1px solid #fef3c7;">
                <span style="font-size:0.6rem;color:#b45309;font-weight:700;display:block;">Fin de Semana:</span>
                <span style="font-size:0.75rem;font-weight:800;color:#d97706;">${fmtMins(ticket.total_minutos_ejecucion_efectivo_fin_semana)}</span>
            </div>
        </div>

        ${childrenHtml}
    </div>`;

    if (window.Swal) {
        Swal.fire({
            title: `<i class="fas fa-file-alt text-indigo-600 mr-2"></i> Detalle Completo del Ticket`,
            html: htmlContent,
            width: '680px',
            showCloseButton: true,
            showConfirmButton: true,
            confirmButtonText: '<i class="fas fa-check mr-1"></i> Cerrar',
            confirmButtonColor: '#6366f1'
        });
    } else {
        alert(`Ticket #${id} (Ref: ${ref})
Cliente: ${cliente}
Contrato: ${contrato}
Servicio: ${servicio}

Descripción:
${descripcion}`);
    }
};


function renderTicketAnalysis() {
        const data = window.biState ? window.biState.dataFiltrada : [];
        const tbody = document.getElementById('ticket-lifecycle-body');
        if (!tbody) return;

        if (!data || data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="17" style="text-align:center;padding:2rem;color:#94a3b8;">Sin datos. Sube un archivo Excel en la pestaña 1.</td></tr>';
            return;
        }

        const getParentId = (r) => String(r['parent_task_id'] || r['id_tarea_padre'] || r['id_parent'] || r['parent_id'] || r['id_padre'] || '').trim();

        // Build parent-child maps
        const childrenMap = {};
        const childSet = new Set();

        data.forEach(r => {
            const pid = getParentId(r);
            if (pid && pid !== '0') {
                if (!childrenMap[pid]) childrenMap[pid] = [];
                childrenMap[pid].push(r);
                childSet.add(String(r['id_tarea'] || '').trim());
            }
        });

        const parents = data.filter(r => {
            const pid = getParentId(r);
            return !pid || pid === '0';
        });

        // KPIs
        const totalPadre = parents.length;
        const totalHijo = data.length - parents.length;
        const completados = data.filter(r => (r['status_name'] || '').toLowerCase().includes('complet')).length;
        const slaPercent = data.length > 0 ? Math.round((completados / data.length) * 100) : 0;
        const totalVidaMins = data.reduce((s, r) => s + (parseFloat(r['total_minutos_abierta']) || 0), 0);
        const avgVida = data.length > 0 ? totalVidaMins / data.length : 0;

        const kpiPadre = document.getElementById('kpi-tk-padre');
        const kpiHijo = document.getElementById('kpi-tk-hijo');
        const kpiSla = document.getElementById('kpi-tk-sla');
        const kpiVida = document.getElementById('kpi-tk-vida');
        const countEl = document.getElementById('ticket-count');
        if (kpiPadre) kpiPadre.textContent = totalPadre.toLocaleString();
        if (kpiHijo) kpiHijo.textContent = totalHijo.toLocaleString();
        if (kpiSla) kpiSla.textContent = slaPercent + '%';
        if (kpiVida) kpiVida.textContent = Math.round(avgVida / 60) + 'h';

        // Helpers
        function statusBadge(status) {
            const s = (status || '').trim();
            let bg, color, icon;
            if (s.includes('Complet'))    { bg='#dcfce7'; color='#16a34a'; icon='fa-check-circle'; }
            else if (s.includes('Cancelad')) { bg='#fee2e2'; color='#dc2626'; icon='fa-times-circle'; }
            else if (s.includes('Pausad') || s.includes('Pendient')) { bg='#fef3c7'; color='#d97706'; icon='fa-pause-circle'; }
            else if (s.includes('Ejecuci') || s.includes('Reasign')) { bg='#ede9fe'; color='#7c3aed'; icon='fa-play-circle'; }
            else if (s.includes('Reprogramad')) { bg='#f0fdf4'; color='#15803d'; icon='fa-calendar-check'; }
            else { bg='#f1f5f9'; color='#475569'; icon='fa-circle'; }
            return `<span style="display:inline-flex;align-items:center;gap:.3rem;padding:.2rem .5rem;border-radius:9999px;font-size:.63rem;font-weight:700;background:${bg};color:${color};white-space:nowrap;"><i class="fas ${icon}" style="font-size:.55rem;"></i>${s||'N/A'}</span>`;
        }
        function typeBadge(type, isParent, childCount) {
            const t = (type || '').trim();
            if (!isParent) {
                return `<span style="padding:.1rem .4rem;border-radius:.25rem;font-size:.6rem;font-weight:700;background:#dcfce7;color:#15803d;"><i class="fas fa-code-branch mr-1"></i>Hijo</span>`;
            }
            if (childCount > 0) {
                return `<span style="padding:.1rem .4rem;border-radius:.25rem;font-size:.6rem;font-weight:700;background:#e0f2fe;color:#0369a1;"><i class="fas fa-layer-group mr-1"></i>Padre (${childCount})</span>`;
            }
            const bg = t==='Interna' ? '#ede9fe' : '#e0f2fe';
            const color = t==='Interna' ? '#7c3aed' : '#0369a1';
            return `<span style="padding:.1rem .35rem;border-radius:.25rem;font-size:.6rem;font-weight:700;background:${bg};color:${color};">${t||'Padre'}</span>`;
        }
        function fmtTime(mins) {
            const m = parseFloat(mins) || 0;
            if (m === 0) return '<span style="color:#cbd5e1;">—</span>';
            const h = Math.floor(m / 60);
            const mn = Math.round(m % 60);
            return h > 0 ? `${h}h&nbsp;${mn}m` : `${mn}m`;
        }
        function buildRow(r, isParent, childCount = 0) {
            const bg = isParent ? '#e0f2fe' : '#ffffff';
            const bl = isParent ? '4px solid #0284c7' : '4px solid #16a34a';
            const id = r['id_tarea'] || '';
            const ref = r['referencia'] || '-';
            const ticketAranda = r['arranda_ticket_name'] || r['ticket_aranda'] || r['aranda_ticket'] || '-';
            const titulo = (r['titulo'] || r['arranda_ticket_name'] || '-').substring(0, 55);
            const descripcion = (r['descripcion'] || '').trim();
            const cliente = (r['cliente'] || '-').substring(0, 24);
            const contrato = (r['contrato'] || r['codigo_de_contrato'] || '-').substring(0, 20);
            const servicio = (r['servicio'] || '-').substring(0, 20);
            const tipo = r['type_name'] || '';
            const tecnico = (r['assigned_to_fullname'] || '-').split(' ').slice(0,2).join(' ');
            const status = r['status_name'] || '';
            const search = `${id} ${ref} ${ticketAranda} ${titulo} ${cliente} ${contrato} ${servicio} ${tecnico} ${status}`.toLowerCase();
            const rowId = `tk-det-${String(id).replace(/[^a-z0-9]/gi,'_')}`;
            const iconId = `icon-tk-${String(id).replace(/[^a-z0-9]/gi,'_')}`;

            const chevron = `<i class="fas fa-chevron-right text-indigo-500 mr-1.5 transition-transform" id="${iconId}" style="font-size:0.65rem;"></i>`;
            const indent = isParent 
                ? `${chevron}<i class="fas fa-folder-open text-sky-600 mr-1"></i>` 
                : `&nbsp;&nbsp;&nbsp;&nbsp;${chevron}<i class="fas fa-level-up-alt rotate-90 text-emerald-600 mr-1" style="display:inline-block;"></i>&nbsp;`;

            function fmtDateFull(raw) {
                if (!raw || raw === '-') return '<span style="color:#cbd5e1;">—</span>';
                const s = String(raw).trim();
                const m = s.match(/(\d+)\s+(\S+)\s+(\d{4})\s+(\d{2}:\d{2}:\d{2})/);
                if (m) {
                    const months = {ene:'01',feb:'02',mar:'03',abr:'04',may:'05',jun:'06',
                                    jul:'07',ago:'08',sep:'09',oct:'10',nov:'11','dic':'12'};
                    const mm = months[m[2].toLowerCase()] || m[2];
                    const yy = m[3].slice(2);
                    return `<span style="white-space:nowrap;font-size:.66rem;">${m[1].padStart(2,'0')}/${mm}/${yy}</span><br><span style="color:#6366f1;font-size:.63rem;font-weight:600;">${m[4]}</span>`;
                }
                return `<span style="font-size:.66rem;">${s.substring(0,20)}</span>`;
            }

            const inicioHtml = fmtDateFull(r['inicio_tarea']);
            const finHtml = fmtDateFull(r['finalizado_seguimiento_o_actualizacion']);

            const parentChildren = childrenMap[String(id).trim()] || [];
            let childrenTableHtml = '';

            if (isParent && parentChildren.length > 0) {
                childrenTableHtml = `
                <div style="margin-top:.6rem;padding-top:.6rem;border-top:1px solid #cbd5e1;">
                    <span style="font-weight:700;color:#1e293b;font-size:.7rem;display:block;margin-bottom:.4rem;">
                        <i class="fas fa-sitemap text-indigo-500 mr-1"></i> ${parentChildren.length} Tareas Hijas Pertenecientes a este Ticket Padre:
                    </span>
                    <table style="width:100%;border-collapse:collapse;font-size:.68rem;background:#ffffff;border:1px solid #cbd5e1;border-radius:.375rem;overflow:hidden;">
                        <thead>
                            <tr style="background:#f1f5f9;color:#475569;font-weight:700;border-bottom:1px solid #cbd5e1;">
                                <th style="padding:.4rem .5rem;text-align:left;">ID Hijo</th>
                                <th style="padding:.4rem .5rem;text-align:left;">Referencia</th>
                                <th style="padding:.4rem .5rem;text-align:left;">Ticket Aranda</th>
                                <th style="padding:.4rem .5rem;text-align:left;">Título / Descripción</th>
                                <th style="padding:.4rem .5rem;text-align:left;">Contrato</th>
                                <th style="padding:.4rem .5rem;text-align:left;">Técnico</th>
                                <th style="padding:.4rem .5rem;text-align:right;">T. Abierto</th>
                                <th style="padding:.4rem .4rem;text-align:right;">T. Ejecución</th>
                                <th style="padding:.4rem .5rem;text-align:center;">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${parentChildren.map(ch => {
                                const chAranda = ch['arranda_ticket_name'] || ch['ticket_aranda'] || ch['aranda_ticket'] || '-';
                                return `
                            <tr style="border-bottom:1px solid #f1f5f9;">
                                <td style="padding:.35rem .5rem;font-weight:700;color:#16a34a;"><i class="fas fa-code-branch mr-1"></i>#${ch['id_tarea']}</td>
                                <td style="padding:.35rem .5rem;">
                                    <span onclick="event.stopPropagation();window.openTicketDetailModal('${ch['referencia']||ch['id_tarea']}')" style="color:#0369a1;font-weight:700;text-decoration:underline dotted;cursor:pointer;">${ch['referencia']||'-'}</span>
                                </td>
                                <td style="padding:.35rem .5rem;color:#4338ca;font-weight:700;">${chAranda}</td>
                                <td style="padding:.35rem .5rem;color:#334155;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${ch['titulo']||ch['descripcion']||'-'}">${ch['titulo']||ch['descripcion']||'-'}</td>
                                <td style="padding:.35rem .5rem;color:#0284c7;font-weight:600;">${ch['contrato']||ch['codigo_de_contrato']||'-'}</td>
                                <td style="padding:.35rem .5rem;color:#475569;">${ch['assigned_to_fullname']||'-'}</td>
                                <td style="padding:.35rem .5rem;text-align:right;color:#64748b;">${fmtTime(ch['total_minutos_abierta'])}</td>
                                <td style="padding:.35rem .4rem;text-align:right;font-weight:700;color:#0f172a;">${fmtTime(ch['total_minutos_en_estado_ejecucion'])}</td>
                                <td style="padding:.35rem .5rem;text-align:center;">${statusBadge(ch['status_name']||'')}</td>
                            </tr>
                            `;}).join('')}
                        </tbody>
                    </table>
                </div>`;
            }

            const detailHtml = `
            <div style="padding:.75rem 1rem .75rem 1.75rem;background:#f8fafc;border-left:4px solid #6366f1;font-size:.72rem;color:#334155;box-shadow:inset 0 2px 4px rgba(0,0,0,0.03);">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem;padding-bottom:.4rem;border-bottom:1px solid #e2e8f0;">
                    <span style="font-weight:800;color:#0f172a;font-size:.75rem;">
                        <i class="fas fa-info-circle text-indigo-500 mr-1"></i> Detalle Completo de ${isParent ? 'Ticket Padre' : 'Tarea Hija'} #${id} (Ref: ${ref} | Aranda: ${ticketAranda})
                    </span>
                    <span style="font-size:.68rem;color:#64748b;">
                        Cliente: <b style="color:#1e293b;">${r['cliente'] || '-'}</b> | Contrato: <b style="color:#0284c7;">${contrato}</b> | Servicio: <b style="color:#16a34a;">${servicio}</b>
                    </span>
                </div>
                
                <div style="background:#ffffff;padding:.6rem .8rem;border-radius:.375rem;border:1px solid #e2e8f0;margin-bottom:.5rem;">
                    <span style="font-weight:700;color:#4338ca;display:block;margin-bottom:.2rem;">
                        <i class="fas fa-align-left mr-1"></i> Descripción Completa:
                    </span>
                    <p style="margin:0;color:#334155;line-height:1.4;white-space:pre-wrap;font-size:.7rem;">${descripcion || (r['titulo'] ? 'Título: ' + r['titulo'] : 'Sin descripción registrada.')}</p>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(130px, 1fr));gap:.5rem;margin-bottom:.4rem;">
                    <div style="background:#f0f9ff;padding:.4rem .6rem;border-radius:.25rem;border:1px solid #bae6fd;">
                        <span style="font-size:.62rem;color:#0369a1;font-weight:700;display:block;">T. Abierto Total:</span>
                        <span style="font-size:.8rem;font-weight:800;color:#0284c7;">${fmtTime(r['total_minutos_abierta'])}</span>
                    </div>
                    <div style="background:#f0fdf4;padding:.4rem .6rem;border-radius:.25rem;border:1px solid #bbf7d0;">
                        <span style="font-size:.62rem;color:#15803d;font-weight:700;display:block;">T. Ejecución Efectivo:</span>
                        <span style="font-size:.8rem;font-weight:800;color:#16a34a;">${fmtTime(r['total_minutos_en_estado_ejecucion'])}</span>
                    </div>
                    <div style="background:#f5f3ff;padding:.4rem .6rem;border-radius:.25rem;border:1px solid #ddd6fe;">
                        <span style="font-size:.62rem;color:#6d28d9;font-weight:700;display:block;">T. Horas Nocturnas:</span>
                        <span style="font-size:.8rem;font-weight:800;color:#7c3aed;">${fmtTime(r['total_minutos_ejecucion_efectivo_nocturno'])}</span>
                    </div>
                    <div style="background:#fffbeb;padding:.4rem .6rem;border-radius:.25rem;border:1px solid #fef3c7;">
                        <span style="font-size:.62rem;color:#b45309;font-weight:700;display:block;">T. Fin de Semana:</span>
                        <span style="font-size:.8rem;font-weight:800;color:#d97706;">${fmtTime(r['total_minutos_ejecucion_efectivo_fin_semana'])}</span>
                    </div>
                </div>

                ${childrenTableHtml}
            </div>`;

            return `<tr class="ticket-row ${isParent ? 'parent-row' : 'child-row'}" style="background:${bg};border-left:${bl};border-bottom:1px solid #e2e8f0;cursor:pointer;" data-searchable="${search}" onclick="toggleTicketDetail('${rowId}', '${iconId}')"
                    onmouseenter="this.style.filter='brightness(.96)'" onmouseleave="this.style.filter=''">  
                <td style="padding:.4rem .45rem;font-weight:700;color:#0f172a;">${indent}#${id}</td>
                <td style="padding:.4rem .45rem;">
                    <span onclick="event.stopPropagation();window.openTicketDetailModal('${ref||id}')" style="color:#0369a1;font-weight:700;text-decoration:underline dotted;cursor:pointer;" title="Clic para ver modal con detalle completo">${ref}</span>
                </td>
                <td style="padding:.4rem .45rem;color:#4338ca;font-weight:700;max-width:110px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${ticketAranda}">${ticketAranda}</td>
                <td style="padding:.4rem .45rem;color:#1e293b;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${titulo}">${titulo}</td>
                <td style="padding:.4rem .45rem;color:#334155;">${cliente}</td>
                <td style="padding:.4rem .45rem;color:#0284c7;font-weight:600;" title="${contrato}">${contrato}</td>
                <td style="padding:.4rem .45rem;color:#334155;">${servicio}</td>
                <td style="padding:.4rem .45rem;text-align:center;">${typeBadge(tipo, isParent, childCount)}</td>
                <td style="padding:.4rem .45rem;color:#334155;">${tecnico}</td>
                <td style="padding:.4rem .45rem;">${inicioHtml}</td>
                <td style="padding:.4rem .45rem;">${finHtml}</td>
                <td style="padding:.4rem .45rem;text-align:right;color:#475569;">${fmtTime(r['total_minutos_abierta'])}</td>
                <td style="padding:.4rem .45rem;text-align:right;font-weight:600;color:#1e293b;">${fmtTime(r['total_minutos_en_estado_ejecucion'])}</td>
                <td style="padding:.4rem .45rem;text-align:right;color:#0369a1;">${fmtTime(r['total_minutos_ejecucion_efectivo_standard'])}</td>
                <td style="padding:.4rem .45rem;text-align:right;color:#7c3aed;">${fmtTime(r['total_minutos_ejecucion_efectivo_nocturno'])}</td>
                <td style="padding:.4rem .45rem;text-align:right;color:#d97706;">${fmtTime(r['total_minutos_ejecucion_efectivo_fin_semana'])}</td>
                <td style="padding:.4rem .45rem;text-align:center;">${statusBadge(status)}</td>
            </tr>
            <tr id="${rowId}" style="display:none;background:#f8fafc;border-left:${bl};">
                <td colspan="17" style="padding:0;">${detailHtml}</td>
            </tr>`;
        }let html = '';
        const renderedChildIds = new Set();
        let renderedCount = 0;
        let totAbierto = 0, totEjec = 0, totStd = 0, totNoct = 0, totFin = 0;

        function accumulateTotals(r) {
            totAbierto += parseFloat(r['total_minutos_abierta']) || 0;
            totEjec    += parseFloat(r['total_minutos_en_estado_ejecucion']) || 0;
            totStd     += parseFloat(r['total_minutos_ejecucion_efectivo_standard']) || 0;
            totNoct    += parseFloat(r['total_minutos_ejecucion_efectivo_nocturno']) || 0;
            totFin     += parseFloat(r['total_minutos_ejecucion_efectivo_fin_semana']) || 0;
        }

        parents.forEach(parent => {
            const pid = String(parent['id_tarea'] || '').trim();
            const childTasks = childrenMap[pid] || [];
            
            html += buildRow(parent, true, childTasks.length);
            accumulateTotals(parent);
            renderedCount++;

            if (window.showChildTasksInAnalysis && childTasks.length > 0) {
                childTasks.forEach(child => {
                    html += buildRow(child, false, 0);
                    accumulateTotals(child);
                    renderedChildIds.add(String(child['id_tarea'] || '').trim());
                    renderedCount++;
                });
            }
        });

        // Render orphan children if enabled
        if (window.showChildTasksInAnalysis) {
            data.forEach(r => {
                const pid = getParentId(r);
                const myId = String(r['id_tarea'] || '').trim();
                if (pid && pid !== '0' && !renderedChildIds.has(myId) && !parents.find(p => String(p['id_tarea']||'').trim() === myId)) {
                    html += buildRow(r, false, 0);
                    accumulateTotals(r);
                    renderedCount++;
                }
            });
        }

        tbody.innerHTML = html || '<tr><td colspan="17" style="text-align:center;padding:2rem;color:#94a3b8;">No hay tickets.</td></tr>';

        if (countEl) countEl.textContent = renderedCount + ' tickets visibles';

        // Update tfoot totals
        function fmtTotal(m) {
            const h = Math.floor(m / 60), min = Math.round(m % 60);
            return h > 0 ? `${h}h&nbsp;${min}m` : `${min}m`;
        }
        document.getElementById('tfoot-abierto').innerHTML = fmtTotal(totAbierto);
        document.getElementById('tfoot-ejec').innerHTML    = fmtTotal(totEjec);
        document.getElementById('tfoot-std').innerHTML     = fmtTotal(totStd);
        document.getElementById('tfoot-noct').innerHTML    = fmtTotal(totNoct);
        document.getElementById('tfoot-finsem').innerHTML  = fmtTotal(totFin);
        const foot = document.getElementById('ticket-lifecycle-foot');
        if (foot) foot.style.display = '';
    }

    /**
     * Toggle description detail row for a ticket
     */
    function toggleTicketDetail(rowId, iconId) {
        const row = document.getElementById(rowId);
        const icon = document.getElementById(iconId);
        if (!row) return;
        if (row.style.display === 'none') {
            row.style.display = '';
            if (icon) icon.className = 'fas fa-chevron-down text-indigo-500 mr-1.5 transition-transform';
        } else {
            row.style.display = 'none';
            if (icon) icon.className = 'fas fa-chevron-right text-indigo-500 mr-1.5 transition-transform';
        }
    }

    /**
     * Live search filter for ticket lifecycle table
     */
    function filterTicketTable() {
        const q = (document.getElementById('ticket-search')?.value || '').toLowerCase().trim();
        const rows = document.querySelectorAll('#ticket-lifecycle-body .ticket-row');
        let visible = 0;
        rows.forEach(row => {
            const match = !q || (row.dataset.searchable || '').includes(q);
            row.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        const countEl = document.getElementById('ticket-count');
        if (countEl) countEl.textContent = visible + ' tickets';
    }

    // ==========================================
    // POWERBI DASHBOARD IMPLEMENTATION
    // ==========================================
    window.pbStateLocal = {
        cliente: '',
        contrato: '',
        servicio: '',
        mesano: '',
        dateStart: '',
        dateEnd: '',
        descripcion: '',
        aranda: ''
    };
    window.pbCharts = {
        gestiones: null,
        servicios: null,
        actividades: null
    };

    function pbInitDateRanges() {
        if (!window.biState.dataOriginal || window.biState.dataOriginal.length === 0) return;
        let minDate = null;
        let maxDate = null;
        window.biState.dataOriginal.forEach(row => {
            const d = parseSpanishDate(row.inicio_tarea);
            if (d) {
                if (!minDate || d < minDate) minDate = d;
                if (!maxDate || d > maxDate) maxDate = d;
            }
        });
        
        if (minDate && maxDate) {
            const formatDate = (d) => {
                const y = d.getFullYear();
                const m = String(d.getMonth() + 1).padStart(2, '0');
                const r = String(d.getDate()).padStart(2, '0');
                return `${y}-${m}-${r}`;
            };
            $('#pb-date-start').val(formatDate(minDate));
            $('#pb-date-end').val(formatDate(maxDate));
            window.pbStateLocal.dateStart = formatDate(minDate);
            window.pbStateLocal.dateEnd = formatDate(maxDate);
        }
    }

    function pbRegisterListeners() {
        $('.pb-filter-input').off('change').on('change', function() {
            const id = this.id;
            if (id === 'pb-select-cliente') window.pbStateLocal.cliente = $(this).val();
            else if (id === 'pb-select-contrato') window.pbStateLocal.contrato = $(this).val();
            else if (id === 'pb-select-servicio') window.pbStateLocal.servicio = $(this).val();
            else if (id === 'pb-select-mesano') window.pbStateLocal.mesano = $(this).val();
            else if (id === 'pb-date-start') window.pbStateLocal.dateStart = $(this).val();
            else if (id === 'pb-date-end') window.pbStateLocal.dateEnd = $(this).val();
            
            pbRenderAll();
        });

        let deb1 = null, deb2 = null;
        $('#pb-search-descripcion').off('input').on('input', function() {
            clearTimeout(deb1);
            deb1 = setTimeout(() => {
                window.pbStateLocal.descripcion = $(this).val();
                pbRenderAll();
            }, 250);
        });

        $('#pb-search-aranda').off('input').on('input', function() {
            clearTimeout(deb2);
            deb2 = setTimeout(() => {
                window.pbStateLocal.aranda = $(this).val();
                pbRenderAll();
            }, 250);
        });
    }

    function pbClearSearch(id) {
        $(`#${id}`).val('');
        if (id === 'pb-search-descripcion') window.pbStateLocal.descripcion = '';
        else if (id === 'pb-search-aranda') window.pbStateLocal.aranda = '';
        pbRenderAll();
    }

    function pbRenderAll() {
        if (!window.biState.dataOriginal || window.biState.dataOriginal.length === 0) {
            return;
        }

        const data = window.biState.dataOriginal;
        
        window.pbFilteredData = data.filter(row => {
            const rowContract = row.codigo_de_contrato || row.contrato || 'N/A';
            const rowDate = parseSpanishDate(row.inicio_tarea);
            
            // Cliente
            if (window.pbStateLocal.cliente && row.cliente !== window.pbStateLocal.cliente) return false;
            
            // Contrato
            if (window.pbStateLocal.contrato && rowContract !== window.pbStateLocal.contrato) return false;
            
            // Tipo Servicio
            if (window.pbStateLocal.servicio && row.servicio !== window.pbStateLocal.servicio) return false;
            
            // Date Range
            if (rowDate) {
                if (window.pbStateLocal.dateStart) {
                    const start = new Date(window.pbStateLocal.dateStart + 'T00:00:00');
                    if (rowDate < start) return false;
                }
                if (window.pbStateLocal.dateEnd) {
                    const end = new Date(window.pbStateLocal.dateEnd + 'T23:59:59');
                    if (rowDate > end) return false;
                }
            }
            
            // MES/AÑO
            if (window.pbStateLocal.mesano && rowDate) {
                const monthsSpanish = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
                const rowMesAno = `${monthsSpanish[rowDate.getMonth()]} ${rowDate.getFullYear()}`;
                if (rowMesAno !== window.pbStateLocal.mesano) return false;
            }
            
            // Descripción Search
            if (window.pbStateLocal.descripcion) {
                const q = window.pbStateLocal.descripcion.toLowerCase();
                const desc = (row.descripcion || '').toLowerCase();
                const tit = (row.titulo || '').toLowerCase();
                if (!desc.includes(q) && !tit.includes(q)) return false;
            }
            
            // Ticket Aranda Search
            if (window.pbStateLocal.aranda) {
                const q = window.pbStateLocal.aranda.toLowerCase();
                const tkt = (row.arranda_ticket_name || '').toLowerCase();
                if (!tkt.includes(q)) return false;
            }
            
            return true;
        });

        // Rebuild dropdown selectors to ensure nested dependencies
        pbRebuildAllDropdowns();

        // Calculate Tiempo Consumido KPI
        let totalHours = 0;
        window.pbFilteredData.forEach(row => {
            totalHours += (parseFloat(row.total_minutos_en_estado_ejecucion) || 0) / 60;
        });
        $('#pb-kpi-tiempo').text(totalHours.toFixed(2));

        // Render Table Grid
        const tbody = $('#pb-table-body');
        tbody.empty();
        if (window.pbFilteredData.length === 0) {
            tbody.append('<tr><td colspan="6" style="padding: 2rem; text-align: center; color: #94a3b8; font-style: italic;">Sin registros coincidentes</td></tr>');
        } else {
            window.pbFilteredData.forEach(row => {
                tbody.append(`
                    <tr class="hover:bg-slate-50" style="border-bottom: 1px solid #e2e8f0; height: 28px;">
                        <td style="padding: 0.4rem 0.5rem; border-right: 1px solid #e2e8f0; font-weight: bold; color: #0f172a; white-space: nowrap;">${row.id_tarea || row.id_internal}</td>
                        <td style="padding: 0.4rem 0.5rem; border-right: 1px solid #e2e8f0; white-space: nowrap;">${row.codigo_de_contrato || row.contrato || '-'}</td>
                        <td style="padding: 0.4rem 0.5rem; border-right: 1px solid #e2e8f0; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${row.servicio || '-'}">${row.servicio || '-'}</td>
                        <td style="padding: 0.4rem 0.5rem; border-right: 1px solid #e2e8f0; text-align: center; font-weight: 600;">${row.seguimientos_n || 0}</td>
                        <td style="padding: 0.4rem 0.5rem; border-right: 1px solid #e2e8f0; white-space: nowrap;">${row.arranda_ticket_name || '-'}</td>
                        <td style="padding: 0.4rem 0.5rem; white-space: nowrap;">${row.assigned_to_fullname || '-'}</td>
                    </tr>
                `);
            });
        }
        $('#pb-table-info').text(`Mostrando ${window.pbFilteredData.length} de ${window.biState.dataOriginal.length} registros`);

        // Draw charts
        pbRenderCharts();
    }

    function pbRebuildAllDropdowns() {
        pbRebuildDropdown('pb-select-cliente', r => r.cliente, window.pbStateLocal.cliente, 'cliente');
        pbRebuildDropdown('pb-select-contrato', r => r.codigo_de_contrato || r.contrato || 'N/A', window.pbStateLocal.contrato, 'contrato');
        pbRebuildDropdown('pb-select-servicio', r => r.servicio, window.pbStateLocal.servicio, 'servicio');
        
        pbRebuildDropdown('pb-select-mesano', r => {
            const d = parseSpanishDate(r.inicio_tarea);
            if (!d) return '';
            const monthsSpanish = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
            return `${monthsSpanish[d.getMonth()]} ${d.getFullYear()}`;
        }, window.pbStateLocal.mesano, 'mesano');
    }

    function pbRebuildDropdown(elementId, getRowValueFn, activeValue, filterKey) {
        const compData = window.biState.dataOriginal.filter(row => {
            const rowContract = row.codigo_de_contrato || row.contrato || 'N/A';
            const rowDate = parseSpanishDate(row.inicio_tarea);
            
            if (filterKey !== 'cliente' && window.pbStateLocal.cliente && row.cliente !== window.pbStateLocal.cliente) return false;
            if (filterKey !== 'contrato' && window.pbStateLocal.contrato && rowContract !== window.pbStateLocal.contrato) return false;
            if (filterKey !== 'servicio' && window.pbStateLocal.servicio && row.servicio !== window.pbStateLocal.servicio) return false;
            
            if (rowDate) {
                if (window.pbStateLocal.dateStart) {
                    const start = new Date(window.pbStateLocal.dateStart + 'T00:00:00');
                    if (rowDate < start) return false;
                }
                if (window.pbStateLocal.dateEnd) {
                    const end = new Date(window.pbStateLocal.dateEnd + 'T23:59:59');
                    if (rowDate > end) return false;
                }
            }
            if (filterKey !== 'mesano' && window.pbStateLocal.mesano && rowDate) {
                const monthsSpanish = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
                const rowMesAno = `${monthsSpanish[rowDate.getMonth()]} ${rowDate.getFullYear()}`;
                if (rowMesAno !== window.pbStateLocal.mesano) return false;
            }
            if (window.pbStateLocal.descripcion) {
                const q = window.pbStateLocal.descripcion.toLowerCase();
                const desc = (row.descripcion || '').toLowerCase();
                const tit = (row.titulo || '').toLowerCase();
                if (!desc.includes(q) && !tit.includes(q)) return false;
            }
            if (window.pbStateLocal.aranda) {
                const q = window.pbStateLocal.aranda.toLowerCase();
                const tkt = (row.arranda_ticket_name || '').toLowerCase();
                if (!tkt.includes(q)) return false;
            }
            return true;
        });
        
        const uniqueValues = Array.from(new Set(compData.map(getRowValueFn).filter(Boolean))).sort();
        const select = $(`#${elementId}`);
        select.empty();
        
        if (elementId === 'pb-select-mesano') {
            select.append('<option value="">Todas</option>');
        } else {
            select.append(`<option value="">Todos (${uniqueValues.length})</option>`);
        }
        
        uniqueValues.forEach(val => {
            const selected = val === activeValue ? 'selected' : '';
            select.append(`<option value="${val}" ${selected}>${val}</option>`);
        });
    }

    function pbRenderCharts() {
        const data = window.pbFilteredData;

        // 1. Gestiones por contrato
        const contractHours = {};
        data.forEach(row => {
            const contract = row.codigo_de_contrato || row.contrato || 'N/A';
            const hours = (parseFloat(row.total_minutos_en_estado_ejecucion) || 0) / 60;
            contractHours[contract] = (contractHours[contract] || 0) + hours;
        });

        const contractLabels = Object.keys(contractHours);
        const contractData = Object.values(contractHours).map(h => parseFloat(h.toFixed(2)));

        if (window.pbCharts.gestiones) {
            window.pbCharts.gestiones.destroy();
        }

        const ctxGestiones = document.getElementById('pb-chart-gestiones').getContext('2d');
        window.pbCharts.gestiones = new Chart(ctxGestiones, {
            type: 'bar',
            data: {
                labels: contractLabels,
                datasets: [{
                    label: 'Horas',
                    data: contractData,
                    backgroundColor: '#002060',
                    borderColor: '#002060',
                    borderWidth: 1,
                    barThickness: 30
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.raw + ' horas';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Horas', font: { size: 10 } },
                        ticks: { font: { size: 9 } }
                    },
                    x: {
                        ticks: { font: { size: 9 } }
                    }
                }
            }
        });

        // 2. Servicios (Donut)
        const serviceHours = {};
        let totalServHours = 0;
        data.forEach(row => {
            const service = row.servicio || 'Sin Servicio';
            const hours = (parseFloat(row.total_minutos_en_estado_ejecucion) || 0) / 60;
            serviceHours[service] = (serviceHours[service] || 0) + hours;
            totalServHours += hours;
        });

        const serviceLabels = Object.keys(serviceHours);
        const serviceData = Object.values(serviceHours).map(h => parseFloat(h.toFixed(2)));

        if (window.pbCharts.servicios) {
            window.pbCharts.servicios.destroy();
        }

        const ctxServicios = document.getElementById('pb-chart-servicios').getContext('2d');
        const blueShades = [
            '#002060', '#0033a0', '#0070f3', '#38bdf8', '#0ea5e9', '#0284c7', '#0369a1', '#075985'
        ];

        window.pbCharts.servicios = new Chart(ctxServicios, {
            type: 'doughnut',
            data: {
                labels: serviceLabels,
                datasets: [{
                    data: serviceData,
                    backgroundColor: blueShades.slice(0, serviceLabels.length),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            font: { size: 9 },
                            boxWidth: 10,
                            generateLabels: function(chart) {
                                const data = chart.data;
                                if (data.labels.length && data.datasets.length) {
                                    return data.labels.map(function(label, i) {
                                        const value = data.datasets[0].data[i];
                                        const pct = totalServHours > 0 ? ((value / totalServHours) * 100).toFixed(2) : 0;
                                        return {
                                            text: `${label}: ${value} (${pct}%)`,
                                            fillStyle: data.datasets[0].backgroundColor[i],
                                            hidden: isNaN(data.datasets[0].data[i]) || chart.getDatasetMeta(0).data[i].hidden,
                                            index: i
                                        };
                                    });
                                }
                                return [];
                            }
                        }
                    }
                }
            }
        });

        // 3. Actividades registradas por Clientes (Grouped/Stacked Bar Chart by Month & Client)
        const monthClientHours = {};
        const allClients = new Set();
        
        data.forEach(row => {
            const rowDate = parseSpanishDate(row.inicio_tarea);
            if (rowDate) {
                const monthsSpanish = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
                const mKey = `${monthsSpanish[rowDate.getMonth()]} ${rowDate.getFullYear()}`;
                const cli = row.cliente || 'Otros';
                const hours = (parseFloat(row.total_minutos_en_estado_ejecucion) || 0) / 60;
                
                allClients.add(cli);
                if (!monthClientHours[mKey]) monthClientHours[mKey] = {};
                monthClientHours[mKey][cli] = (monthClientHours[mKey][cli] || 0) + hours;
            }
        });

        const monthOrder = {
            'enero': 0, 'febrero': 1, 'marzo': 2, 'abril': 3, 'mayo': 4, 'junio': 5,
            'julio': 6, 'agosto': 7, 'septiembre': 8, 'octubre': 9, 'noviembre': 10, 'diciembre': 11
        };

        const sortedMonthKeys = Object.keys(monthClientHours).sort((a, b) => {
            const partsA = a.split(' ');
            const partsB = b.split(' ');
            const yearA = parseInt(partsA[1]);
            const yearB = parseInt(partsB[1]);
            if (yearA !== yearB) return yearA - yearB;
            return monthOrder[partsA[0]] - monthOrder[partsB[0]];
        });

        const clientList = Array.from(allClients);
        const clientColors = [
            '#002060', '#0056b3', '#00a3ff', '#22c55e', '#a855f7', '#ec4899', '#f59e0b', '#3b82f6'
        ];

        const datasets = clientList.map((cli, idx) => {
            return {
                label: cli,
                data: sortedMonthKeys.map(mKey => parseFloat((monthClientHours[mKey][cli] || 0).toFixed(2))),
                backgroundColor: clientColors[idx % clientColors.length],
                stack: 'Stack 0'
            };
        });

        if (window.pbCharts.actividades) {
            window.pbCharts.actividades.destroy();
        }

        const ctxActividades = document.getElementById('pb-chart-actividades').getContext('2d');
        window.pbCharts.actividades = new Chart(ctxActividades, {
            type: 'bar',
            data: {
                labels: sortedMonthKeys,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: { font: { size: 9 }, boxWidth: 10 }
                    }
                },
                scales: {
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        ticks: { font: { size: 9 } }
                    },
                    x: {
                        stacked: true,
                        ticks: { font: { size: 9 } }
                    }
                }
            }
        });
    }

    // ==========================================
    // ESPECIALISTAS DASHBOARD IMPLEMENTATION
    // ==========================================
    window.peStateLocal = {
        especialista: '',
        ano: '',
        mes: '',
        dia: ''
    };
    window.peCharts = {
        diaria: null,
        cliente: null,
        gauge: null,
        especialista: null
    };

    function peInitFilters() {
        peRebuildAllDropdowns();
    }

    function peRegisterListeners() {
        $('.pe-filter-input').off('change').on('change', function() {
            const id = this.id;
            const val = $(this).val();
            if (id === 'pe-select-especialista') window.peStateLocal.especialista = val;
            else if (id === 'pe-select-ano') window.peStateLocal.ano = val;
            else if (id === 'pe-select-mes') window.peStateLocal.mes = val;
            else if (id === 'pe-select-dia') window.peStateLocal.dia = val;
            
            peRenderAll();
        });
    }

    function peRenderAll() {
        if (!window.biState.dataOriginal || window.biState.dataOriginal.length === 0) {
            return;
        }

        const data = window.biState.dataOriginal;
        
        window.peFilteredData = data.filter(row => {
            const d = parseSpanishDate(row.inicio_tarea);
            const rowYear = d ? d.getFullYear().toString() : '';
            const monthsSpanish = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
            const rowMonth = d ? monthsSpanish[d.getMonth()] : '';
            const rowDay = d ? d.getDate().toString() : '';
            
            // Especialista
            if (window.peStateLocal.especialista && row.assigned_to_fullname !== window.peStateLocal.especialista) return false;
            
            // Año
            if (window.peStateLocal.ano && rowYear !== window.peStateLocal.ano) return false;
            
            // Mes
            if (window.peStateLocal.mes && rowMonth !== window.peStateLocal.mes) return false;
            
            // Día
            if (window.peStateLocal.dia && rowDay !== window.peStateLocal.dia) return false;
            
            return true;
        });

        // Rebuild selectors
        peRebuildAllDropdowns();

        // Calculate total hours
        let totalHours = 0;
        window.peFilteredData.forEach(row => {
            totalHours += (parseFloat(row.total_minutos_en_estado_ejecucion) || 0) / 60;
        });

        // Update Gauge KPI Value in center
        $('#pe-gauge-value').text(formatSpanishNumber(totalHours));

        // Update Recuento de Valor (number of unique specialists)
        const uniqueSpecs = new Set(window.peFilteredData.map(r => r.assigned_to_fullname).filter(Boolean));
        $('#pe-kpi-recuento').text(uniqueSpecs.size);

        // Update Hojas Registradas (total rows)
        $('#pe-kpi-hojas').text(window.peFilteredData.length.toLocaleString('es-ES'));

        // Render charts
        peRenderDiariaChart();
        peRenderClienteChart();
        peRenderGaugeChart(totalHours);
        peRenderEspecialistaChart();
    }

    function formatSpanishNumber(num) {
        return num.toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function peRebuildAllDropdowns() {
        peRebuildDropdown('pe-select-especialista', r => r.assigned_to_fullname, window.peStateLocal.especialista, 'especialista');
        peRebuildDropdown('pe-select-ano', r => {
            const d = parseSpanishDate(r.inicio_tarea);
            return d ? d.getFullYear().toString() : '';
        }, window.peStateLocal.ano, 'ano');
        peRebuildDropdown('pe-select-mes', r => {
            const d = parseSpanishDate(r.inicio_tarea);
            if (!d) return '';
            const monthsSpanish = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
            return monthsSpanish[d.getMonth()];
        }, window.peStateLocal.mes, 'mes');
        peRebuildDropdown('pe-select-dia', r => {
            const d = parseSpanishDate(r.inicio_tarea);
            return d ? d.getDate().toString() : '';
        }, window.peStateLocal.dia, 'dia');
    }

    function peRebuildDropdown(elementId, getRowValueFn, activeValue, filterKey) {
        const compData = window.biState.dataOriginal.filter(row => {
            const d = parseSpanishDate(row.inicio_tarea);
            const rowYear = d ? d.getFullYear().toString() : '';
            const monthsSpanish = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
            const rowMonth = d ? monthsSpanish[d.getMonth()] : '';
            const rowDay = d ? d.getDate().toString() : '';
            
            if (filterKey !== 'especialista' && window.peStateLocal.especialista && row.assigned_to_fullname !== window.peStateLocal.especialista) return false;
            if (filterKey !== 'ano' && window.peStateLocal.ano && rowYear !== window.peStateLocal.ano) return false;
            if (filterKey !== 'mes' && window.peStateLocal.mes && rowMonth !== window.peStateLocal.mes) return false;
            if (filterKey !== 'dia' && window.peStateLocal.dia && rowDay !== window.peStateLocal.dia) return false;
            return true;
        });
        
        const uniqueValues = Array.from(new Set(compData.map(getRowValueFn).filter(Boolean)));
        
        if (elementId === 'pe-select-dia' || elementId === 'pe-select-ano') {
            uniqueValues.sort((a, b) => parseInt(a) - parseInt(b));
        } else if (elementId === 'pe-select-mes') {
            const monthOrder = {
                'enero': 0, 'febrero': 1, 'marzo': 2, 'abril': 3, 'mayo': 4, 'junio': 5,
                'julio': 6, 'agosto': 7, 'septiembre': 8, 'octubre': 9, 'noviembre': 10, 'diciembre': 11
            };
            uniqueValues.sort((a, b) => monthOrder[a] - monthOrder[b]);
        } else {
            uniqueValues.sort();
        }
        
        const select = $(`#${elementId}`);
        select.empty();
        select.append('<option value="">Todas</option>');
        
        uniqueValues.forEach(val => {
            const selected = val === activeValue ? 'selected' : '';
            select.append(`<option value="${val}" ${selected}>${val}</option>`);
        });
    }

    function peRenderDiariaChart() {
        const daysSet = new Set();
        window.peFilteredData.forEach(row => {
            const d = parseSpanishDate(row.inicio_tarea);
            if (d) daysSet.add(d.getDate());
        });
        const daysSorted = Array.from(daysSet).sort((a, b) => a - b);
        
        if (daysSorted.length === 0) {
            daysSorted.push(1);
        }

        const clientsSet = new Set();
        window.peFilteredData.forEach(row => {
            if (row.cliente) clientsSet.add(row.cliente);
        });
        const clientList = Array.from(clientsSet).sort();
        
        const clientColors = [
            '#002060', '#0056b3', '#00a3ff', '#22c55e', '#a855f7', '#ec4899', '#f59e0b', '#3b82f6', '#14b8a6', '#f43f5e'
        ];

        const datasets = clientList.map((client, idx) => {
            const dataPoints = daysSorted.map(dayNum => {
                let sum = 0;
                window.peFilteredData.forEach(row => {
                    const d = parseSpanishDate(row.inicio_tarea);
                    if (d && d.getDate() === dayNum && row.cliente === client) {
                        sum += (parseFloat(row.total_minutos_en_estado_ejecucion) || 0) / 60;
                    }
                });
                return parseFloat(sum.toFixed(2));
            });
            return {
                label: client,
                data: dataPoints,
                backgroundColor: clientColors[idx % clientColors.length],
                stack: 'Stack 0'
            };
        });

        if (window.peCharts.diaria) {
            window.peCharts.diaria.destroy();
        }

        const ctx = document.getElementById('pe-chart-diaria').getContext('2d');
        window.peCharts.diaria = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: daysSorted,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { boxWidth: 8, font: { size: 9 } }
                    }
                },
                scales: {
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        ticks: { font: { size: 9 } }
                    },
                    x: {
                        stacked: true,
                        ticks: { font: { size: 9 } },
                        title: { display: true, text: 'Día', font: { size: 10, weight: 'bold' } }
                    }
                }
            }
        });
    }

    function peRenderClienteChart() {
        const clientHours = {};
        let totalHours = 0;
        window.peFilteredData.forEach(row => {
            const client = row.cliente || 'Otros';
            const hours = (parseFloat(row.total_minutos_en_estado_ejecucion) || 0) / 60;
            clientHours[client] = (clientHours[client] || 0) + hours;
            totalHours += hours;
        });

        const sortedClients = Object.keys(clientHours).sort((a, b) => clientHours[b] - clientHours[a]);
        const clientLabels = sortedClients;
        const clientData = sortedClients.map(c => parseFloat(clientHours[c].toFixed(2)));

        if (window.peCharts.cliente) {
            window.peCharts.cliente.destroy();
        }

        const ctx = document.getElementById('pe-chart-cliente').getContext('2d');
        const colors = [
            '#002060', '#0033a0', '#0070f3', '#38bdf8', '#0ea5e9', '#0284c7', '#0369a1', '#075985', '#22c55e', '#a855f7', '#ec4899', '#f59e0b'
        ];

        window.peCharts.cliente = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: clientLabels,
                datasets: [{
                    data: clientData,
                    backgroundColor: colors.slice(0, clientLabels.length),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            font: { size: 8 },
                            boxWidth: 8,
                            generateLabels: function(chart) {
                                const data = chart.data;
                                if (data.labels.length && data.datasets.length) {
                                    return data.labels.map(function(label, i) {
                                        const value = data.datasets[0].data[i];
                                        const pct = totalHours > 0 ? ((value / totalHours) * 100).toFixed(1) : 0;
                                        return {
                                            text: `${formatSpanishNumber(value)} (${pct}%) - ${label}`,
                                            fillStyle: data.datasets[0].backgroundColor[i],
                                            hidden: isNaN(data.datasets[0].data[i]) || chart.getDatasetMeta(0).data[i].hidden,
                                            index: i
                                        };
                                    });
                                }
                                return [];
                            }
                        }
                    }
                }
            }
        });
    }

    function peRenderGaugeChart(totalHours) {
        if (window.peCharts.gauge) {
            window.peCharts.gauge.destroy();
        }

        const maxVal = 160.0;
        const cappedValue = Math.min(maxVal, totalHours);
        const remaining = Math.max(0, maxVal - totalHours);

        const gaugeColor = totalHours > 160 ? '#dc2626' : '#22c55e';

        const ctx = document.getElementById('pe-chart-gauge').getContext('2d');
        window.peCharts.gauge = new Chart(ctx, {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: [cappedValue, remaining],
                    backgroundColor: [gaugeColor, '#e2e8f0'],
                    borderWidth: 0
                }]
            },
            options: {
                rotation: -90,
                circumference: 180,
                cutout: '80%',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: false }
                }
            }
        });
    }

    function peRenderEspecialistaChart() {
        const specHours = {};
        window.peFilteredData.forEach(row => {
            const spec = row.assigned_to_fullname || 'Sin Asignar';
            const hours = (parseFloat(row.total_minutos_en_estado_ejecucion) || 0) / 60;
            specHours[spec] = (specHours[spec] || 0) + hours;
        });

        const sortedSpecs = Object.keys(specHours).sort((a, b) => specHours[b] - specHours[a]).slice(0, 15);
        const labels = sortedSpecs.map(name => name.split(' ').slice(0, 2).join(' '));
        const dataVals = sortedSpecs.map(s => parseFloat(specHours[s].toFixed(2)));

        if (window.peCharts.especialista) {
            window.peCharts.especialista.destroy();
        }

        const ctx = document.getElementById('pe-chart-especialista').getContext('2d');
        window.peCharts.especialista = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    data: dataVals,
                    backgroundColor: '#3b82f6',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.raw + ' horas';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { display: false },
                        grid: { display: false }
                    },
                    x: {
                        ticks: {
                            font: { size: 8 },
                            maxRotation: 45,
                            minRotation: 45
                        },
                        grid: { display: false }
                    }
                },
                plugins: [{
                    id: 'pe-datalabels',
                    afterDatasetsDraw(chart) {
                        const { ctx, data } = chart;
                        ctx.save();
                        ctx.font = 'bold 8px sans-serif';
                        ctx.fillStyle = '#475569';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'bottom';
                        chart.getDatasetMeta(0).data.forEach((bar, index) => {
                            const val = data.datasets[0].data[index];
                            ctx.fillText(formatSpanishNumber(val), bar.x, bar.y - 2);
                        });
                        ctx.restore();
                    }
                }]
            }
        });
    }


/* ==========================================================================
   TAB 9: CÁLCULO DE HORAS Y RECARGOS (VISTA DE GRUPOS ITIL - AZUL, VERDE, ÁMBAR)
   ========================================================================== */
window.horasDobleActive = false;
window.horasRecargosViewMode = 'hierarchical';

function toggleHorasDoble() {
    window.horasDobleActive = !window.horasDobleActive;
    const btn = document.getElementById('btn-toggle-doble');
    const lbl = document.getElementById('lbl-status-doble');
    if (window.horasDobleActive) {
        if (btn) {
            btn.className = 'px-4 py-2 rounded-lg font-bold text-xs flex items-center gap-2 border shadow-md transition-all bg-amber-500 text-white border-amber-600 hover:bg-amber-600';
        }
        if (lbl) {
            lbl.textContent = 'ACTIVADO (2x Multiplicador)';
            lbl.className = 'text-white font-extrabold uppercase bg-amber-700/50 px-1.5 py-0.5 rounded';
        }
        if (window.toastr) toastr.success('Recargos en Ventanas Horarias al DOBLE (2x) ACTIVADOS');
    } else {
        if (btn) {
            btn.className = 'px-4 py-2 rounded-lg font-bold text-xs flex items-center gap-2 border shadow-sm transition-all bg-slate-100 text-slate-700 border-slate-300 hover:bg-slate-200';
        }
        if (lbl) {
            lbl.textContent = 'DESACTIVADO (1x)';
            lbl.className = 'text-slate-500 font-extrabold uppercase';
        }
        if (window.toastr) toastr.info('Recargos al DOBLE Desactivados (Volviendo a 1x)');
    }
    renderHorasRecargosTab();
}

function setHorasRecargosViewMode(mode) {
    window.horasRecargosViewMode = mode;
    ['hierarchical', 'saltos'].forEach(m => {
        const b = document.getElementById('btn-view-' + m);
        if (b) {
            if (m === mode) {
                b.className = 'px-3 py-1.5 rounded-md font-bold text-xs bg-indigo-600 text-white shadow-sm flex items-center gap-1.5';
            } else {
                b.className = 'px-3 py-1.5 rounded-md font-medium text-xs bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 flex items-center gap-1.5';
            }
        }
    });
    renderHorasRecargosTab();
}

function parseDateFlexible(dateStr) {
    if (!dateStr || dateStr === '-' || dateStr === 'N/A' || dateStr === 'null' || dateStr === 'undefined') return null;
    
    if (dateStr instanceof Date) {
        return isNaN(dateStr.getTime()) ? null : dateStr;
    }

    let s = String(dateStr).trim();
    if (!s) return null;

    // 1. Excel numeric serial date (e.g. 46118.5969)
    if (/^\d+(\.\d+)?$/.test(s)) {
        let n = parseFloat(s);
        if (n > 20000 && n < 80000) {
            let date = new Date(Math.round((n - 25569) * 86400 * 1000));
            return isNaN(date.getTime()) ? null : date;
        }
    }

    // 2. Spanish named month (e.g., "6 abr. 2026 14:19:37", "06 de abril del 2026 14:19")
    let spanishMatch = s.match(/^(\d{1,2})\s*(?:de)?\s*([a-zA-ZáéíóúÁÉÍÓÚ\.]+)\s*(?:de|del)?\s*(\d{4})(?:[\s,]+(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?)?/i);
    if (spanishMatch) {
        const months = {
            ene:0, enero:0, feb:1, febrero:1, mar:2, marzo:2, abr:3, abril:3,
            may:4, mayo:4, jun:5, junio:5, jul:6, julio:6, ago:7, agosto:7,
            sep:8, sept:8, septiembre:8, oct:9, octubre:9, nov:10, noviembre:10, dic:11, diciembre:11
        };
        let mClean = spanishMatch[2].toLowerCase().replace('.', '').normalize("NFD").replace(/[\u0300-\u036f]/g, "");
        let monthNum = months[mClean] ?? months[mClean.substring(0, 3)] ?? null;
        if (monthNum !== null) {
            let year = parseInt(spanishMatch[3], 10);
            let day = parseInt(spanishMatch[1], 10);
            let hour = spanishMatch[4] ? parseInt(spanishMatch[4], 10) : 0;
            let min = spanishMatch[5] ? parseInt(spanishMatch[5], 10) : 0;
            let sec = spanishMatch[6] ? parseInt(spanishMatch[6], 10) : 0;
            return new Date(year, monthNum, day, hour, min, sec);
        }
    }

    // 3. Latin format: DD/MM/YYYY HH:MM:SS or DD-MM-YYYY HH:MM
    let latinMatch = s.match(/^(\d{1,2})[-/](\d{1,2})[-/](\d{4})(?:[\sT]+(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?)?/);
    if (latinMatch) {
        let day = parseInt(latinMatch[1], 10);
        let month = parseInt(latinMatch[2], 10) - 1;
        let year = parseInt(latinMatch[3], 10);
        let hour = latinMatch[4] ? parseInt(latinMatch[4], 10) : 0;
        let min = latinMatch[5] ? parseInt(latinMatch[5], 10) : 0;
        let sec = latinMatch[6] ? parseInt(latinMatch[6], 10) : 0;

        if (month >= 0 && month <= 11 && day >= 1 && day <= 31) {
            return new Date(year, month, day, hour, min, sec);
        }
    }

    // 4. ISO format: YYYY-MM-DD HH:MM:SS or YYYY/MM/DD THH:MM:SS
    let isoMatch = s.match(/^(\d{4})[-/](\d{1,2})[-/](\d{1,2})(?:[\sT]+(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?)?/);
    if (isoMatch) {
        let year = parseInt(isoMatch[1], 10);
        let month = parseInt(isoMatch[2], 10) - 1;
        let day = parseInt(isoMatch[3], 10);
        let hour = isoMatch[4] ? parseInt(isoMatch[4], 10) : 0;
        let min = isoMatch[5] ? parseInt(isoMatch[5], 10) : 0;
        let sec = isoMatch[6] ? parseInt(isoMatch[6], 10) : 0;
        return new Date(year, month, day, hour, min, sec);
    }

    // 5. Fallback native
    let d = new Date(s);
    return isNaN(d.getTime()) ? null : d;
}

function calculateTimeWindowBreakdown(startDt, endDt) {
    if (!startDt || !endDt || endDt <= startDt) {
        return { normalMins: 0, extraMins: 0, extraordinariaMins: 0, totalMins: 0 };
    }
    
    let totalMs = endDt.getTime() - startDt.getTime();
    let totalMins = Math.round(totalMs / 60000);
    if (totalMins <= 0) {
        return { normalMins: 0, extraMins: 0, extraordinariaMins: 0, totalMins: 0 };
    }

    let normalMins = 0;
    let extraMins = 0;
    let extraordinariaMins = 0;

    let curr = new Date(startDt.getTime());
    let endMs = endDt.getTime();
    let steps = 0;
    let maxSteps = totalMins + 1;

    while (curr.getTime() < endMs && steps < maxSteps) {
        let h = curr.getHours();
        if (h >= 6 && h < 18) {
            normalMins++;
        } else if (h >= 18 && h < 22) {
            extraMins++;
        } else {
            extraordinariaMins++;
        }
        curr.setMinutes(curr.getMinutes() + 1);
        steps++;
    }

    return { normalMins, extraMins, extraordinariaMins, totalMins };
}

function processCalculatedRow(startStr, endStr, explicitMins, applyDouble) {
    let startDt = parseDateFlexible(startStr);
    let endDt = parseDateFlexible(endStr);

    // If startDt is valid and endDt is missing/invalid, construct endDt = startDt + explicitMins
    if (startDt && (!endDt || isNaN(endDt.getTime())) && explicitMins > 0) {
        endDt = new Date(startDt.getTime() + Math.round(explicitMins) * 60000);
    }

    // If endDt is valid and startDt is missing/invalid, construct startDt = endDt - explicitMins
    if (endDt && (!startDt || isNaN(startDt.getTime())) && explicitMins > 0) {
        startDt = new Date(endDt.getTime() - Math.round(explicitMins) * 60000);
    }

    let breakdown = calculateTimeWindowBreakdown(startDt, endDt);

    let totalMins = breakdown.totalMins;
    if (totalMins <= 0 && explicitMins > 0) {
        totalMins = Math.round(explicitMins);
        breakdown.normalMins = totalMins;
    }

    let isOver24h = totalMins > 1440; // > 1,440 minutes (24 hours)

    // Grupo 2 (Azul): Desglose Real de Tiempos
    let realNormMins = breakdown.normalMins;
    let realExtMins = breakdown.extraMins;
    let realExtordMins = breakdown.extraordinariaMins;

    // Grupo 3 (Verde): Regla +15 min
    let baseHoursCount = Math.floor(totalMins / 60);
    let remainderMins = totalMins % 60;
    let incremento15mMins = remainderMins > 15 ? (60 - remainderMins) : 0;
    let adjustedMins = totalMins + incremento15mMins;

    // Grupo 4 (Ámbar): Recargos al Doble (2x)
    function roundCategoryMins(mins) {
        if (mins <= 0) return 0;
        let bH = Math.floor(mins / 60);
        let rM = mins % 60;
        if (rM > 15) {
            return (bH + 1) * 60;
        }
        return Math.round(mins);
    }

    let mult = applyDouble ? 2 : 1;
    let finalNormMins = Math.round(roundCategoryMins(realNormMins) * mult);
    let finalExtMins = Math.round(roundCategoryMins(realExtMins) * mult);
    let finalExtordMins = Math.round(roundCategoryMins(realExtordMins) * mult);
    let totalLiquidadasMins = finalNormMins + finalExtMins + finalExtordMins;

    return {
        startDt,
        endDt,
        totalMins,
        isOver24h,
        realNormMins,
        realExtMins,
        realExtordMins,
        incremento15mMins,
        adjustedMins,
        finalNormMins,
        finalExtMins,
        finalExtordMins,
        totalLiquidadasMins,
        multiplier: mult
    };
}

function fmtMinutesOnly(mins) {
    let m = Math.round(parseFloat(mins) || 0);
    return m.toLocaleString() + ' min';
}

function fmtDateShort(dtObj, rawFallback) {
    if (dtObj && !isNaN(dtObj.getTime())) {
        let dd = String(dtObj.getDate()).padStart(2, '0');
        let mm = String(dtObj.getMonth() + 1).padStart(2, '0');
        let yy = String(dtObj.getFullYear()).slice(2);
        let hh = String(dtObj.getHours()).padStart(2, '0');
        let min = String(dtObj.getMinutes()).padStart(2, '0');
        return `<span class="font-medium text-slate-700 dark:text-slate-300">${dd}/${mm}/${yy}</span> <span class="text-indigo-600 font-bold">${hh}:${min}</span>`;
    }
    return rawFallback ? `<span class="text-slate-400">${rawFallback}</span>` : '<span class="text-slate-300">—</span>';
}

function toggleHorasDetail(rowId) {
    const row = document.getElementById(rowId);
    const icon = document.getElementById('icon-' + rowId);
    if (!row) return;
    if (row.style.display === 'none') {
        row.style.display = '';
        if (icon) icon.className = 'fas fa-chevron-down text-indigo-500 mr-1.5 transition-transform';
    } else {
        row.style.display = 'none';
        if (icon) icon.className = 'fas fa-chevron-right text-indigo-500 mr-1.5 transition-transform';
    }
}

function updateHorasRecargosKPIs(items, baseMins, ajustMins, liqMins, over24Count) {
    const elItems = document.getElementById('kpi-hr-items');
    const elBase = document.getElementById('kpi-hr-base');
    const elAjust = document.getElementById('kpi-hr-ajust');
    const elLiq = document.getElementById('kpi-hr-liq');
    const el24h = document.getElementById('kpi-hr-24h');

    if (elItems) elItems.textContent = items.toLocaleString();
    if (elBase) elBase.textContent = Math.round(baseMins).toLocaleString() + ' min';
    if (elAjust) elAjust.textContent = Math.round(ajustMins).toLocaleString() + ' min';
    if (elLiq) elLiq.textContent = Math.round(liqMins).toLocaleString() + ' min';
    if (el24h) {
        el24h.textContent = over24Count.toLocaleString();
        if (over24Count > 0) {
            el24h.className = 'text-2xl font-black text-red-600 dark:text-red-400 animate-pulse';
        } else {
            el24h.className = 'text-2xl font-black text-slate-800 dark:text-slate-100';
        }
    }
}

function makeTableResizable(tableId) {
    const table = document.getElementById(tableId);
    if (!table) return;
    table.classList.add('resizable-table');
    const cols = table.querySelectorAll('thead th');
    
    cols.forEach(col => {
        const oldResizer = col.querySelector('.resizer');
        if (oldResizer) oldResizer.remove();

        const resizer = document.createElement('div');
        resizer.className = 'resizer';
        col.appendChild(resizer);
        
        let x = 0;
        let w = 0;
        
        const mouseDownHandler = function(e) {
            e.preventDefault();
            e.stopPropagation();
            x = e.clientX;
            const styles = window.getComputedStyle(col);
            w = parseInt(styles.width, 10);
            
            resizer.classList.add('resizing');
            
            document.addEventListener('mousemove', mouseMoveHandler);
            document.addEventListener('mouseup', mouseUpHandler);
        };
        
        const mouseMoveHandler = function(e) {
            const dx = e.clientX - x;
            const newW = Math.max(50, w + dx);
            col.style.width = `${newW}px`;
            col.style.minWidth = `${newW}px`;
        };
        
        const mouseUpHandler = function() {
            resizer.classList.remove('resizing');
            document.removeEventListener('mousemove', mouseMoveHandler);
            document.removeEventListener('mouseup', mouseUpHandler);
        };
        
        resizer.addEventListener('mousedown', mouseDownHandler);
    });
}

function exportHorasRecargosExcel() {
    const table = document.getElementById('horas-recargos-table');
    if (!table) return;
    if (typeof XLSX === 'undefined') {
        alert('Librería XLSX no cargada.');
        return;
    }
    let wb = XLSX.utils.table_to_book(table, { sheet: "Minutos_y_Recargos_ITIL" });
    XLSX.writeFile(wb, "Reporte_Minutos_Recargos_ITIL_NovaIOPS.xlsx");
    if (window.toastr) toastr.success('Reporte Excel en minutos generado correctamente.');
}

function renderHorasRecargosTab() {
    const tareas = window.biState ? window.biState.dataFiltrada : [];
    const seguimientosAll = window.biState ? (window.biState.seguimientosOriginal || []) : [];
    const isDouble = !!window.horasDobleActive;
    const viewMode = window.horasRecargosViewMode || 'hierarchical';

    const tbody = document.getElementById('horas-recargos-body');
    if (!tbody) return;

    if (!tareas || tareas.length === 0) {
        tbody.innerHTML = '<tr><td colspan="21" class="py-8 text-center text-slate-400">Sin datos. Carga un reporte en la Pestaña 1 o ajusta los filtros.</td></tr>';
        updateHorasRecargosKPIs(0, 0, 0, 0, 0);
        return;
    }

    const taskMap = {};
    const filteredTaskIds = new Set();
    tareas.forEach(t => {
        let tid = String(t.id_tarea || '').trim();
        if (tid) {
            taskMap[tid] = t;
            filteredTaskIds.add(tid);
        }
        let ref = String(t.referencia || '').trim();
        if (ref) {
            taskMap[ref] = t;
        }
    });

    const filteredSeguimientos = seguimientosAll.filter(s => {
        let tid = String(s.id_tarea || '').trim();
        let ref = String(s.referencia || '').trim();
        return filteredTaskIds.has(tid) || (ref && taskMap[ref]);
    });

    let totalItems = 0;
    let totalOver24Count = 0;
    let sumTotalDurationMins = 0;
    
    // Group Azul Totals
    let sumRealNormMins = 0;
    let sumRealExtMins = 0;
    let sumRealExtordMins = 0;
    
    // Group Verde Totals
    let sumInc15Mins = 0;
    let sumAdjustedMins = 0;

    // Group Ámbar Totals (2x)
    let sumLiqNormMins = 0;
    let sumLiqExtMins = 0;
    let sumLiqExtordMins = 0;
    let sumLiqTotalMins = 0;

    let html = '';
    const searchVal = (document.getElementById('horas-search-input')?.value || '').toLowerCase().trim();

    if (viewMode === 'hierarchical') {
        tareas.forEach(t => {
            let tid = String(t.id_tarea || '').trim();
            let ref = String(t.referencia || '').trim();
            let ticketAranda = t.arranda_ticket_name || t.ticket_aranda || t.aranda_ticket || t.aranda || '-';
            let parentSegs = filteredSeguimientos.filter(s => String(s.id_tarea || '').trim() === tid || (ref && String(s.referencia || '').trim() === ref));

            let startStr = t.inicio_tarea || '';
            let endStr = t.finalizado_seguimiento_o_actualizacion || t.ultima_actualizacion_tarea || '';
            let explicitMins = parseFloat(t.total_minutos_en_estado_ejecucion) || parseFloat(t.total_minutos_abierta) || 0;
            
            let calc = processCalculatedRow(startStr, endStr, explicitMins, isDouble);

            let statusName = t.status_name || t.estado || 'En progreso';
            let typeName = t.type_name || 'Ticket';
            let refDisplay = ref ? `<button onclick="if(window.openTicketDetailModal){openTicketDetailModal('${ref}')}else{alert('Referencia: ${ref}')}" class="text-indigo-600 dark:text-indigo-400 font-bold hover:underline">${ref}</button>` : '<span class="text-slate-400">—</span>';

            let textSearch = `${tid} ${ref} ${ticketAranda} ${t.cliente||''} ${t.servicio||''} ${t.assigned_to_fullname||''} ${t.titulo||''} ${statusName} ${typeName}`.toLowerCase();
            if (searchVal && !textSearch.includes(searchVal)) return;

            totalItems++;
            if (calc.isOver24h) totalOver24Count++;
            sumTotalDurationMins += calc.totalMins;

            sumRealNormMins += calc.realNormMins;
            sumRealExtMins += calc.realExtMins;
            sumRealExtordMins += calc.realExtordMins;

            sumInc15Mins += calc.incremento15mMins;
            sumAdjustedMins += calc.adjustedMins;

            sumLiqNormMins += calc.finalNormMins;
            sumLiqExtMins += calc.finalExtMins;
            sumLiqExtordMins += calc.finalExtordMins;
            sumLiqTotalMins += calc.totalLiquidadasMins;

            let alertBadge = calc.isOver24h 
                ? `<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-black bg-red-600 text-white animate-pulse shadow-sm"><i class="fas fa-exclamation-triangle mr-1"></i> > 1,440 MIN</span>`
                : `<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400"><i class="fas fa-check-circle mr-1"></i> Normal</span>`;

            let durationDisplay = calc.isOver24h
                ? `<span class="font-extrabold text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/40 px-2 py-0.5 rounded border border-red-200 dark:border-red-800">${fmtMinutesOnly(calc.totalMins)}</span>`
                : `<span class="font-semibold text-slate-700 dark:text-slate-300">${fmtMinutesOnly(calc.totalMins)}</span>`;

            let rowId = `hr-tk-${tid}`;

            html += `
                <tr class="bg-indigo-50/40 dark:bg-indigo-950/20 border-l-4 border-indigo-500 border-b border-slate-200 dark:border-slate-700 hover:bg-indigo-50 font-medium cursor-pointer" onclick="toggleHorasDetail('${rowId}')">
                    <!-- Informes e Identificación ITIL -->
                    <td class="py-2.5 px-2.5 font-bold text-slate-800 dark:text-slate-100">
                        <i class="fas fa-chevron-right text-indigo-500 mr-1.5 transition-transform" id="icon-${rowId}"></i> #${tid}
                    </td>
                    <td class="py-2.5 px-2" onclick="event.stopPropagation()">${refDisplay}</td>
                    <td class="py-2.5 px-2 font-bold text-indigo-700 dark:text-indigo-300 truncate max-w-[120px]" title="${ticketAranda}">${ticketAranda}</td>
                    <td class="py-2.5 px-2 text-slate-700 dark:text-slate-300 truncate max-w-[150px]" title="${t.titulo||'-'}">${t.titulo||'-'}</td>
                    <td class="py-2.5 px-2 text-slate-700 dark:text-slate-300 truncate max-w-[120px]" title="${t.cliente||'-'}">${t.cliente||'-'}</td>
                    <td class="py-2.5 px-2 text-slate-600 dark:text-slate-400 truncate max-w-[110px]" title="${t.servicio||'-'}">${t.servicio||'-'}</td>
                    <td class="py-2.5 px-2 text-center">
                        <span class="px-2 py-0.5 bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300 font-bold rounded text-[10px]">${typeName} (${parentSegs.length} Saltos)</span>
                    </td>
                    <td class="py-2.5 px-2 text-slate-700 dark:text-slate-300 truncate max-w-[120px]" title="${t.assigned_to_fullname||'-'}">${t.assigned_to_fullname||'-'}</td>
                    <td class="py-2.5 px-2 text-center">
                        <span class="px-2 py-0.5 bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200 font-semibold rounded text-[10px]">${statusName}</span>
                    </td>
                    <td class="py-2.5 px-2 text-xs text-slate-500">${fmtDateShort(calc.startDt, startStr)}</td>
                    <td class="py-2.5 px-2 text-xs text-slate-500">${fmtDateShort(calc.endDt, endStr)}</td>
                    <td class="py-2.5 px-2 text-center border-r border-slate-200 dark:border-slate-700">${durationDisplay}</td>

                    <!-- Grupo Azul: Desglose Real -->
                    <td class="py-2.5 px-2 text-right bg-blue-50/50 dark:bg-blue-950/20 text-blue-900 dark:text-blue-300 font-medium">${calc.realNormMins.toLocaleString()} min</td>
                    <td class="py-2.5 px-2 text-right bg-blue-50/50 dark:bg-blue-950/20 text-blue-900 dark:text-blue-300 font-medium">${calc.realExtMins.toLocaleString()} min</td>
                    <td class="py-2.5 px-2 text-right bg-blue-50/50 dark:bg-blue-950/20 text-blue-900 dark:text-blue-300 font-medium border-r border-blue-200 dark:border-blue-800">${calc.realExtordMins.toLocaleString()} min</td>

                    <!-- Grupo Verde: Regla +15m -->
                    <td class="py-2.5 px-2 text-right bg-emerald-50/50 dark:bg-emerald-950/20 text-emerald-900 dark:text-emerald-300 font-semibold">${calc.incremento15mMins > 0 ? '+' + calc.incremento15mMins.toLocaleString() + ' min' : '0 min'}</td>
                    <td class="py-2.5 px-2 text-right bg-emerald-50/50 dark:bg-emerald-950/20 text-emerald-900 dark:text-emerald-300 font-extrabold border-r border-emerald-200 dark:border-emerald-800">${calc.adjustedMins.toLocaleString()} min</td>

                    <!-- Grupo Ámbar: Recargos al Doble (2x) -->
                    <td class="py-2.5 px-2 text-right bg-amber-50/50 dark:bg-amber-950/20 text-amber-950 dark:text-amber-300 font-bold">${calc.finalNormMins.toLocaleString()} min</td>
                    <td class="py-2.5 px-2 text-right bg-amber-50/50 dark:bg-amber-950/20 text-amber-950 dark:text-amber-300 font-bold">${calc.finalExtMins.toLocaleString()} min</td>
                    <td class="py-2.5 px-2 text-right bg-amber-50/50 dark:bg-amber-950/20 text-amber-950 dark:text-amber-300 font-bold">${calc.finalExtordMins.toLocaleString()} min</td>
                    <td class="py-2.5 px-2 text-right bg-amber-100/80 dark:bg-amber-900/40 text-amber-950 dark:text-amber-100 font-black">${calc.totalLiquidadasMins.toLocaleString()} min</td>

                    <!-- Alerta -->
                    <td class="py-2.5 px-2 text-center">${alertBadge}</td>
                </tr>
            `;

            if (parentSegs.length > 0) {
                html += `<tr id="${rowId}" style="display:none;" class="bg-slate-50/70 dark:bg-slate-900/40"><td colspan="22" class="p-0">
                    <table class="w-full text-xs text-left border-l-4 border-indigo-300 dark:border-indigo-700">
                        <thead>
                            <tr class="bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-bold border-b border-slate-300 dark:border-slate-700">
                                <th class="py-1.5 px-2.5">Salto ID</th>
                                <th class="py-1.5 px-2">Ref</th>
                                <th class="py-1.5 px-2">Ticket Aranda</th>
                                <th class="py-1.5 px-2">Descripción Salto</th>
                                <th class="py-1.5 px-2">Cliente</th>
                                <th class="py-1.5 px-2">Servicio</th>
                                <th class="py-1.5 px-2 text-center">Tipo</th>
                                <th class="py-1.5 px-2">Especialista</th>
                                <th class="py-1.5 px-2 text-center">Estado Salto</th>
                                <th class="py-1.5 px-2">Inicio Efectivo</th>
                                <th class="py-1.5 px-2">Fin Efectivo</th>
                                <th class="py-1.5 px-2 text-center border-r border-slate-300">Duración Real</th>
                                
                                <th class="py-1.5 px-2 text-right bg-blue-100 dark:bg-blue-900/40 text-blue-900">Norm Real</th>
                                <th class="py-1.5 px-2 text-right bg-blue-100 dark:bg-blue-900/40 text-blue-900">Ext Real</th>
                                <th class="py-1.5 px-2 text-right bg-blue-100 dark:bg-blue-900/40 text-blue-900 border-r border-blue-300">Extord Real</th>

                                <th class="py-1.5 px-2 text-right bg-emerald-100 dark:bg-emerald-900/40 text-emerald-900">Inc +15m</th>
                                <th class="py-1.5 px-2 text-right bg-emerald-100 dark:bg-emerald-900/40 text-emerald-900 border-r border-emerald-300 font-bold">Min. Ajust</th>

                                <th class="py-1.5 px-2 text-right bg-amber-100 dark:bg-amber-900/40 text-amber-900">Norm Liq</th>
                                <th class="py-1.5 px-2 text-right bg-amber-100 dark:bg-amber-900/40 text-amber-900">Ext Liq</th>
                                <th class="py-1.5 px-2 text-right bg-amber-100 dark:bg-amber-900/40 text-amber-900">Extord Liq</th>
                                <th class="py-1.5 px-2 text-right bg-amber-200 dark:bg-amber-800 text-amber-950 font-black">Total Liq</th>
                                <th class="py-1.5 px-2 text-center">Alerta</th>
                            </tr>
                        </thead>
                        <tbody>`;

                parentSegs.forEach(seg => {
                    let sStart = seg.inicio || '';
                    let sEnd = seg.fin || '';
                    let sMins = parseFloat(seg.duracion_min) || 0;
                    let sCalc = processCalculatedRow(sStart, sEnd, sMins, isDouble);
                    let segAranda = seg.arranda_ticket_name || seg.ticket_aranda || seg.aranda_ticket || ticketAranda;

                    let sDurDisplay = sCalc.isOver24h
                        ? `<span class="font-extrabold text-red-600 bg-red-100 px-1.5 py-0.5 rounded animate-pulse">> 1,440 min (${fmtMinutesOnly(sCalc.totalMins)})</span>`
                        : `<span>${fmtMinutesOnly(sCalc.totalMins)}</span>`;

                    html += `
                        <tr class="border-b border-slate-200/60 dark:border-slate-800 hover:bg-slate-100/80 dark:hover:bg-slate-800/60">
                            <td class="py-2 px-2.5 font-semibold text-indigo-600 dark:text-indigo-400">
                                <i class="fas fa-level-up-alt rotate-90 text-indigo-400 mr-1"></i> Salto #${seg.id_seguimiento || '-'}
                            </td>
                            <td class="py-2 px-2 text-slate-500">${ref || '-'}</td>
                            <td class="py-2 px-2 font-bold text-indigo-700 dark:text-indigo-300 truncate max-w-[120px]">${segAranda}</td>
                            <td class="py-2 px-2 text-slate-700 dark:text-slate-300 truncate max-w-[150px]" title="${seg.descripcion||'-'}">${seg.descripcion||'-'}</td>
                            <td class="py-2 px-2 text-slate-500 truncate max-w-[120px]">${t.cliente||'-'}</td>
                            <td class="py-2 px-2 text-slate-500 truncate max-w-[110px]">${t.servicio||'-'}</td>
                            <td class="py-2 px-2 text-center"><span class="px-1.5 py-0.5 rounded text-[10px] bg-slate-200 text-slate-700 font-medium">Salto</span></td>
                            <td class="py-2 px-2 text-slate-700 dark:text-slate-300 truncate max-w-[120px]">${seg.usuario || t.assigned_to_fullname || '-'}</td>
                            <td class="py-2 px-2 text-center"><span class="px-1.5 py-0.5 rounded text-[10px] bg-slate-200 text-slate-700 font-semibold">${seg.estado || 'En curso'}</span></td>
                            <td class="py-2 px-2 text-slate-500">${fmtDateShort(sCalc.startDt, sStart)}</td>
                            <td class="py-2 px-2 text-slate-500">${fmtDateShort(sCalc.endDt, sEnd)}</td>
                            <td class="py-2 px-2 text-center border-r border-slate-200">${sDurDisplay}</td>

                            <!-- Grupo Azul -->
                            <td class="py-2 px-2 text-right bg-blue-50/30 text-blue-900">${sCalc.realNormMins.toLocaleString()} min</td>
                            <td class="py-2 px-2 text-right bg-blue-50/30 text-blue-900">${sCalc.realExtMins.toLocaleString()} min</td>
                            <td class="py-2 px-2 text-right bg-blue-50/30 text-blue-900 border-r border-blue-200">${sCalc.realExtordMins.toLocaleString()} min</td>

                            <!-- Grupo Verde -->
                            <td class="py-2 px-2 text-right bg-emerald-50/30 text-emerald-900">${sCalc.incremento15mMins > 0 ? '+' + sCalc.incremento15mMins.toLocaleString() + ' min' : '0 min'}</td>
                            <td class="py-2 px-2 text-right bg-emerald-50/30 text-emerald-900 font-bold border-r border-emerald-200">${sCalc.adjustedMins.toLocaleString()} min</td>

                            <!-- Grupo Ámbar (2x) -->
                            <td class="py-2 px-2 text-right bg-amber-50/30 text-amber-900 font-semibold">${sCalc.finalNormMins.toLocaleString()} min</td>
                            <td class="py-2 px-2 text-right bg-amber-50/30 text-amber-900 font-semibold">${sCalc.finalExtMins.toLocaleString()} min</td>
                            <td class="py-2 px-2 text-right bg-amber-50/30 text-amber-900 font-semibold">${sCalc.finalExtordMins.toLocaleString()} min</td>
                            <td class="py-2 px-2 text-right bg-amber-100/50 text-amber-950 font-black">${sCalc.totalLiquidadasMins.toLocaleString()} min</td>
                            <td class="py-2 px-2 text-center">${sCalc.isOver24h ? '⚠️' : '✓'}</td>
                        </tr>
                    `;
                });

                html += `</tbody></table></td></tr>`;
            }
        });
    } else if (viewMode === 'saltos') {
        filteredSeguimientos.forEach(seg => {
            let parentTask = taskMap[String(seg.id_tarea || '').trim()] || taskMap[String(seg.referencia || '').trim()] || {};
            let sStart = seg.inicio || '';
            let sEnd = seg.fin || '';
            let sMins = parseFloat(seg.duracion_min) || 0;
            let calc = processCalculatedRow(sStart, sEnd, sMins, isDouble);

            let statusName = seg.estado || parentTask.status_name || 'En seguimiento';
            let typeName = parentTask.type_name || 'Salto';
            let ref = seg.referencia || parentTask.referencia || '';
            let refDisplay = ref ? `<button onclick="if(window.openTicketDetailModal){openTicketDetailModal('${ref}')}else{alert('Referencia: ${ref}')}" class="text-indigo-600 dark:text-indigo-400 font-bold hover:underline">${ref}</button>` : '<span class="text-slate-400">—</span>';

            let textSearch = `${seg.id_tarea||''} ${ref} ${parentTask.cliente||''} ${seg.usuario||''} ${seg.descripcion||''} ${statusName}`.toLowerCase();
            if (searchVal && !textSearch.includes(searchVal)) return;

            totalItems++;
            if (calc.isOver24h) totalOver24Count++;
            sumTotalDurationMins += calc.totalMins;

            sumRealNormMins += calc.realNormMins;
            sumRealExtMins += calc.realExtMins;
            sumRealExtordMins += calc.realExtordMins;

            sumInc15Mins += calc.incremento15mMins;
            sumAdjustedMins += calc.adjustedMins;

            sumLiqNormMins += calc.finalNormMins;
            sumLiqExtMins += calc.finalExtMins;
            sumLiqExtordMins += calc.finalExtordMins;
            sumLiqTotalMins += calc.totalLiquidadasMins;

            let alertBadge = calc.isOver24h 
                ? `<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-black bg-red-600 text-white animate-pulse shadow-sm"><i class="fas fa-exclamation-triangle mr-1"></i> > 1,440 MIN</span>`
                : `<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400"><i class="fas fa-check-circle mr-1"></i> Normal</span>`;

            let durationDisplay = calc.isOver24h
                ? `<span class="font-extrabold text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/40 px-2 py-0.5 rounded border border-red-200 dark:border-red-800">${fmtMinutesOnly(calc.totalMins)}</span>`
                : `<span class="font-semibold text-slate-700 dark:text-slate-300">${fmtMinutesOnly(calc.totalMins)}</span>`;

            html += `
                <tr class="border-b border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800/60">
                    <td class="py-2.5 px-2.5 font-bold text-slate-800 dark:text-slate-100">#${seg.id_tarea||'-'} / Salto #${seg.id_seguimiento||'-'}</td>
                    <td class="py-2.5 px-2">${refDisplay}</td>
                    <td class="py-2.5 px-2 text-slate-700 dark:text-slate-300 truncate max-w-[150px]" title="${seg.descripcion||parentTask.titulo||'-'}">${seg.descripcion||parentTask.titulo||'-'}</td>
                    <td class="py-2.5 px-2 text-slate-700 dark:text-slate-300 truncate max-w-[120px]">${parentTask.cliente||'-'}</td>
                    <td class="py-2.5 px-2 text-slate-600 dark:text-slate-400 truncate max-w-[110px]">${parentTask.servicio||'-'}</td>
                    <td class="py-2.5 px-2 text-center"><span class="px-2 py-0.5 bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 font-semibold rounded text-[10px]">${typeName}</span></td>
                    <td class="py-2.5 px-2 text-slate-700 dark:text-slate-300 truncate max-w-[120px]">${seg.usuario || parentTask.assigned_to_fullname || '-'}</td>
                    <td class="py-2.5 px-2 text-center"><span class="px-2 py-0.5 bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200 font-semibold rounded text-[10px]">${statusName}</span></td>
                    <td class="py-2.5 px-2 text-xs text-slate-500">${fmtDateShort(calc.startDt, sStart)}</td>
                    <td class="py-2.5 px-2 text-xs text-slate-500">${fmtDateShort(calc.endDt, sEnd)}</td>
                    <td class="py-2.5 px-2 text-center border-r border-slate-200 dark:border-slate-700">${durationDisplay}</td>

                    <!-- Grupo Azul -->
                    <td class="py-2.5 px-2 text-right bg-blue-50/50 dark:bg-blue-950/20 text-blue-900 dark:text-blue-300 font-medium">${calc.realNormMins.toLocaleString()} min</td>
                    <td class="py-2.5 px-2 text-right bg-blue-50/50 dark:bg-blue-950/20 text-blue-900 dark:text-blue-300 font-medium">${calc.realExtMins.toLocaleString()} min</td>
                    <td class="py-2.5 px-2 text-right bg-blue-50/50 dark:bg-blue-950/20 text-blue-900 dark:text-blue-300 font-medium border-r border-blue-200 dark:border-blue-800">${calc.realExtordMins.toLocaleString()} min</td>

                    <!-- Grupo Verde -->
                    <td class="py-2.5 px-2 text-right bg-emerald-50/50 dark:bg-emerald-950/20 text-emerald-900 dark:text-emerald-300 font-semibold">${calc.incremento15mMins > 0 ? '+' + calc.incremento15mMins.toLocaleString() + ' min' : '0 min'}</td>
                    <td class="py-2.5 px-2 text-right bg-emerald-50/50 dark:bg-emerald-950/20 text-emerald-900 dark:text-emerald-300 font-extrabold border-r border-emerald-200 dark:border-emerald-800">${calc.adjustedMins.toLocaleString()} min</td>

                    <!-- Grupo Ámbar (2x) -->
                    <td class="py-2.5 px-2 text-right bg-amber-50/50 dark:bg-amber-950/20 text-amber-950 dark:text-amber-300 font-bold">${calc.finalNormMins.toLocaleString()} min</td>
                    <td class="py-2.5 px-2 text-right bg-amber-50/50 dark:bg-amber-950/20 text-amber-950 dark:text-amber-300 font-bold">${calc.finalExtMins.toLocaleString()} min</td>
                    <td class="py-2.5 px-2 text-right bg-amber-50/50 dark:bg-amber-950/20 text-amber-950 dark:text-amber-300 font-bold">${calc.finalExtordMins.toLocaleString()} min</td>
                    <td class="py-2.5 px-2 text-right bg-amber-100/80 dark:bg-amber-900/40 text-amber-950 dark:text-amber-100 font-black">${calc.totalLiquidadasMins.toLocaleString()} min</td>

                    <!-- Alerta -->
                    <td class="py-2.5 px-2 text-center">${alertBadge}</td>
                </tr>
            `;
        });
    }

    tbody.innerHTML = html || '<tr><td colspan="21" class="py-8 text-center text-slate-400">No se encontraron registros para la vista seleccionada.</td></tr>';

    document.getElementById('ft-recargos-real-dur').textContent = Math.round(sumTotalDurationMins).toLocaleString() + ' min';
    document.getElementById('ft-recargos-real-norm').textContent = Math.round(sumRealNormMins).toLocaleString() + ' min';
    document.getElementById('ft-recargos-real-ext').textContent = Math.round(sumRealExtMins).toLocaleString() + ' min';
    document.getElementById('ft-recargos-real-extord').textContent = Math.round(sumRealExtordMins).toLocaleString() + ' min';

    document.getElementById('ft-recargos-inc15').textContent = '+' + Math.round(sumInc15Mins).toLocaleString() + ' min';
    document.getElementById('ft-recargos-ajustadas').textContent = Math.round(sumAdjustedMins).toLocaleString() + ' min';

    document.getElementById('ft-recargos-normales').textContent = Math.round(sumLiqNormMins).toLocaleString() + ' min';
    document.getElementById('ft-recargos-extras').textContent = Math.round(sumLiqExtMins).toLocaleString() + ' min';
    document.getElementById('ft-recargos-extord').textContent = Math.round(sumLiqExtordMins).toLocaleString() + ' min';
    document.getElementById('ft-recargos-total').textContent = Math.round(sumLiqTotalMins).toLocaleString() + ' min';

    updateHorasRecargosKPIs(totalItems, sumTotalDurationMins, sumAdjustedMins, sumLiqTotalMins, totalOver24Count);
    makeTableResizable('horas-recargos-table');
}

/**
 * TAB 10: Control de Carga de Trabajo y Cumplimiento Diario por Especialista
 */
window.cmpCharts = window.cmpCharts || {};
window.cmpDailyDataStore = {};

function renderCumplimientoTab() {
    const data = window.biState ? (window.biState.dataOriginal || []) : [];
    const container = document.getElementById('tab-cumplimiento');
    if (!container) return;

    if (!data || data.length === 0) {
        const tbody = document.getElementById('cmp-matrix-body');
        if (tbody) tbody.innerHTML = '<tr><td colspan="10" class="py-8 text-center text-slate-400">No hay datos cargados en el sistema.</td></tr>';
        return;
    }

    const targetHoursInput = parseFloat(document.getElementById('cmp-target-hours')?.value) || 8;
    const targetMins = targetHoursInput * 60;

    const selectedSpec = document.getElementById('cmp-select-especialista')?.value || '';
    const selectedYear = document.getElementById('cmp-select-ano')?.value || '';
    const selectedMonth = document.getElementById('cmp-select-mes')?.value || '';
    const selectedState = document.getElementById('cmp-select-estado')?.value || '';
    const searchText = (document.getElementById('cmp-search-text')?.value || '').toLowerCase().trim();

    // 1. Populate Dropdowns if needed
    cmpRebuildDropdowns(data);

    // 2. Aggregate Data by Specialist + Date (YYYY-MM-DD)
    const dailyMap = {};
    window.cmpDailyDataStore = {};

    const daysSpanish = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    const monthsSpanish = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    data.forEach(row => {
        const specName = row.assigned_to_fullname || row.tecnico || row.usuario || 'Sin Asignar';
        const d = parseSpanishDate(row.inicio_tarea || row.inicio);
        if (!d) return;

        const yearStr = d.getFullYear().toString();
        const monthName = monthsSpanish[d.getMonth()];
        const dayNum = d.getDate();
        const dateKey = `${yearStr}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(dayNum).padStart(2, '0')}`;
        const dayOfWeek = daysSpanish[d.getDay()];

        const key = `${specName}___${dateKey}`;
        if (!dailyMap[key]) {
            dailyMap[key] = {
                specName: specName,
                dateKey: dateKey,
                dateObj: d,
                formattedDate: `${String(dayNum).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}/${yearStr}`,
                dayOfWeek: dayOfWeek,
                yearStr: yearStr,
                monthName: monthName,
                totalMins: 0,
                tasks: []
            };
        }

        const mins = parseFloat(row.total_minutos_en_estado_ejecucion) || parseFloat(row.duracion_min) || 0;
        dailyMap[key].totalMins += mins;
        dailyMap[key].tasks.push(row);
    });

    // 3. Filter Daily Map Records
    let dailyRecords = Object.values(dailyMap);

    dailyRecords = dailyRecords.filter(rec => {
        if (selectedSpec && rec.specName !== selectedSpec) return false;
        if (selectedYear && rec.yearStr !== selectedYear) return false;
        if (selectedMonth && rec.monthName !== selectedMonth) return false;

        const isCompliant = rec.totalMins >= targetMins;
        if (selectedState === 'cumplido' && !isCompliant) return false;
        if (selectedState === 'incumplido' && isCompliant) return false;

        if (searchText) {
            const rowText = `${rec.specName} ${rec.formattedDate} ${rec.dayOfWeek} ${rec.yearStr} ${rec.monthName}`.toLowerCase();
            if (!rowText.includes(searchText)) return false;
        }

        return true;
    });

    // Sort by Date descending then Specialist name
    dailyRecords.sort((a, b) => b.dateObj - a.dateObj || a.specName.localeCompare(b.specName));

    // Store in global window store for modal lookup
    dailyRecords.forEach(rec => {
        const storeKey = `${rec.specName}___${rec.dateKey}`;
        window.cmpDailyDataStore[storeKey] = rec;
    });

    // 4. Calculate KPIs
    let totalEvaluatedDays = dailyRecords.length;
    let totalCompliantDays = 0;
    let totalIncumplidosDays = 0;
    let totalWorkedMins = 0;

    dailyRecords.forEach(rec => {
        totalWorkedMins += rec.totalMins;
        if (rec.totalMins >= targetMins) {
            totalCompliantDays++;
        } else {
            totalIncumplidosDays++;
        }
    });

    const overallRate = totalEvaluatedDays > 0 ? Math.round((totalCompliantDays / totalEvaluatedDays) * 100) : 0;

    const kpiDias = document.getElementById('cmp-kpi-dias');
    if (kpiDias) kpiDias.textContent = totalEvaluatedDays.toLocaleString();

    const kpiCumplidos = document.getElementById('cmp-kpi-cumplidos');
    if (kpiCumplidos) kpiCumplidos.textContent = totalCompliantDays.toLocaleString();

    const kpiIncumplidosVal = document.getElementById('cmp-kpi-incumplidos-val');
    if (kpiIncumplidosVal) kpiIncumplidosVal.textContent = totalIncumplidosDays.toLocaleString();

    const kpiTiempoTotal = document.getElementById('cmp-kpi-tiempo-total');
    if (kpiTiempoTotal) kpiTiempoTotal.textContent = formatMinutes(totalWorkedMins);

    const kpiTasa = document.getElementById('cmp-kpi-tasa');
    if (kpiTasa) kpiTasa.textContent = `${overallRate}%`;

    const rowCountEl = document.getElementById('cmp-table-row-count');
    if (rowCountEl) rowCountEl.textContent = `Mostrando ${dailyRecords.length} días analizados`;

    // 5. Render Matrix Table
    const tbody = document.getElementById('cmp-matrix-body');
    if (tbody) {
        if (dailyRecords.length === 0) {
            tbody.innerHTML = '<tr><td colspan="10" class="py-8 text-center text-slate-400">Sin datos de carga para los filtros seleccionados.</td></tr>';
        } else {
            let html = '';
            dailyRecords.forEach(rec => {
                const storeKey = `${rec.specName}___${rec.dateKey}`;
                const isCompliant = rec.totalMins >= targetMins;
                const pct = Math.min(200, Math.round((rec.totalMins / targetMins) * 100));
                const diffMins = rec.totalMins - targetMins;

                const hrsStr = formatMinutes(rec.totalMins);
                const targetHrsStr = `${targetHoursInput}h 00m`;

                let diffDisplay = '';
                if (diffMins >= 0) {
                    diffDisplay = `<span class="font-extrabold text-emerald-600 dark:text-emerald-400">+${formatMinutes(diffMins)}</span>`;
                } else {
                    diffDisplay = `<span class="font-extrabold text-red-600 dark:text-red-400">-${formatMinutes(Math.abs(diffMins))}</span>`;
                }

                // DATE BADGE: RED if < 8h, GREEN if >= 8h
                let dateBadge = isCompliant
                    ? `<span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-extrabold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700 shadow-sm"><i class="fas fa-check-circle mr-1.5 text-emerald-600 dark:text-emerald-400"></i>${rec.formattedDate}</span>`
                    : `<span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-black bg-red-600 text-white dark:bg-red-700 dark:text-white border border-red-500 shadow-sm animate-pulse"><i class="fas fa-exclamation-circle mr-1.5"></i>${rec.formattedDate} (&lt;${targetHoursInput}h)</span>`;

                let statusBadge = isCompliant
                    ? `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-black bg-emerald-500 text-white uppercase tracking-wider shadow-sm"><i class="fas fa-check mr-1"></i> CUMPLIDO (&ge;${targetHoursInput}H)</span>`
                    : `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-black bg-red-600 text-white uppercase tracking-wider shadow-sm animate-pulse"><i class="fas fa-times-circle mr-1"></i> INCUMPLIDO (&lt;${targetHoursInput}H)</span>`;

                let progressBar = `
                    <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-2.5 overflow-hidden flex">
                        <div class="${isCompliant ? 'bg-emerald-500' : 'bg-red-500'}" style="width: ${Math.min(100, pct)}%"></div>
                    </div>
                    <span class="text-[10px] font-extrabold ${isCompliant ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'} mt-0.5 block">${pct}%</span>
                `;

                const encKey = encodeURIComponent(storeKey);

                html += `
                    <tr class="${isCompliant ? 'bg-white dark:bg-slate-800/80 hover:bg-emerald-50/30' : 'bg-red-50/40 dark:bg-red-950/20 hover:bg-red-50/70'} border-b border-slate-200 dark:border-slate-700 font-medium">
                        <td class="py-3 px-3">${dateBadge}</td>
                        <td class="py-3 px-2 font-bold text-slate-700 dark:text-slate-300">${rec.dayOfWeek}</td>
                        <td class="py-3 px-3 font-extrabold text-slate-900 dark:text-slate-100">${rec.specName}</td>
                        <td class="py-3 px-2 text-center font-bold text-slate-600 dark:text-slate-400">${targetHrsStr}</td>
                        <td class="py-3 px-2 text-center">
                            <span class="px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-300 font-black text-xs">${rec.tasks.length} tareas</span>
                        </td>
                        <td class="py-3 px-3 text-right font-black text-slate-800 dark:text-slate-100">${hrsStr}</td>
                        <td class="py-3 px-3 text-right">${diffDisplay}</td>
                        <td class="py-3 px-3 text-center min-w-[130px]">${progressBar}</td>
                        <td class="py-3 px-3 text-center">${statusBadge}</td>
                        <td class="py-3 px-3 text-center">
                            <button onclick="openDailyTasksModal('${encKey}')" class="px-2.5 py-1 text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors shadow-sm inline-flex items-center gap-1">
                                <i class="fas fa-list-ul"></i> Ver Tareas
                            </button>
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        }
    }

    // 6. Render Charts
    cmpRenderCharts(dailyRecords, targetHoursInput);
}

function cmpRebuildDropdowns(data) {
    const specSel = document.getElementById('cmp-select-especialista');
    const yearSel = document.getElementById('cmp-select-ano');
    const monthSel = document.getElementById('cmp-select-mes');

    if (!specSel || specSel.options.length > 1) return;

    const specs = new Set();
    const years = new Set();
    const monthsSpanish = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    data.forEach(r => {
        const s = r.assigned_to_fullname || r.tecnico || r.usuario;
        if (s) specs.add(s);
        const d = parseSpanishDate(r.inicio_tarea || r.inicio);
        if (d) {
            years.add(d.getFullYear().toString());
        }
    });

    Array.from(specs).sort().forEach(s => {
        const opt = document.createElement('option');
        opt.value = s;
        opt.textContent = s;
        specSel.appendChild(opt);
    });

    Array.from(years).sort().forEach(y => {
        const opt = document.createElement('option');
        opt.value = y;
        opt.textContent = y;
        yearSel.appendChild(opt);
    });

    monthsSpanish.forEach(m => {
        const opt = document.createElement('option');
        opt.value = m;
        opt.textContent = m.charAt(0).toUpperCase() + m.slice(1);
        monthSel.appendChild(opt);
    });
}

window.openDailyTasksModal = function(storeKeyEnc) {
    const storeKey = decodeURIComponent(storeKeyEnc);
    const record = window.cmpDailyDataStore ? window.cmpDailyDataStore[storeKey] : null;
    if (!record) {
        if (window.toastr) toastr.warning('No se encontraron detalles para este día.');
        else alert('No se encontraron detalles para este día.');
        return;
    }

    const targetHoursInput = parseFloat(document.getElementById('cmp-target-hours')?.value) || 8;
    const targetMins = targetHoursInput * 60;
    const isCompliant = record.totalMins >= targetMins;

    const statusBadge = isCompliant
        ? `<span style="background:#10b981;color:#ffffff;padding:.2rem .6rem;border-radius:9999px;font-size:0.7rem;font-weight:800;">CUMPLIDO (&ge;${targetHoursInput}h)</span>`
        : `<span style="background:#ef4444;color:#ffffff;padding:.2rem .6rem;border-radius:9999px;font-size:0.7rem;font-weight:800;">INCUMPLIDO (&lt;${targetHoursInput}h)</span>`;

    const html = `
        <div style="text-align:left;font-family:ui-sans-serif,system-ui,sans-serif;color:#1e293b;font-size:0.8rem;">
            <div style="background:#f8fafc;padding:.75rem;border-radius:.375rem;border:1px solid #cbd5e1;margin-bottom:.75rem;display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <span style="font-weight:800;font-size:0.95rem;color:#0f172a;display:block;">
                        <i class="fas fa-user-circle text-indigo-600 mr-1"></i> ${record.specName}
                    </span>
                    <span style="font-size:0.75rem;color:#64748b;font-weight:600;">
                        ${record.dayOfWeek}, ${record.formattedDate}
                    </span>
                </div>
                <div>${statusBadge}</div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin-bottom:.75rem;text-align:center;">
                <div style="background:#f0f9ff;padding:.5rem;border-radius:.375rem;border:1px solid #bae6fd;">
                    <span style="font-size:0.65rem;color:#0369a1;font-weight:700;display:block;">TIEMPO TOTAL TRABAJADO:</span>
                    <span style="font-size:0.9rem;font-weight:900;color:#0284c7;">${formatMinutes(record.totalMins)}</span>
                </div>
                <div style="background:${isCompliant ? '#f0fdf4' : '#fef2f2'};padding:.5rem;border-radius:.375rem;border:1px solid ${isCompliant ? '#bbf7d0' : '#fecaca'};">
                    <span style="font-size:0.65rem;color:${isCompliant ? '#15803d' : '#b91c1c'};font-weight:700;display:block;">DIFERENCIA VS META ${targetHoursInput}H:</span>
                    <span style="font-size:0.9rem;font-weight:900;color:${isCompliant ? '#16a34a' : '#dc2626'};">
                        ${record.totalMins >= targetMins ? '+' : '-'}${formatMinutes(Math.abs(record.totalMins - targetMins))}
                    </span>
                </div>
            </div>

            <span style="font-weight:800;color:#0f172a;font-size:0.75rem;display:block;margin-bottom:.4rem;">
                <i class="fas fa-tasks text-indigo-600 mr-1"></i> Tareas y Tickets Trabajados (${record.tasks.length}):
            </span>

            <div style="max-height:260px;overflow-y:auto;border:1px solid #cbd5e1;border-radius:.375rem;">
                <table style="width:100%;border-collapse:collapse;font-size:0.7rem;background:#ffffff;">
                    <thead>
                        <tr style="background:#1e293b;color:#ffffff;font-weight:700;">
                            <th style="padding:.4rem .5rem;text-align:left;">ID Tarea</th>
                            <th style="padding:.4rem .5rem;text-align:left;">Ref / Ticket Aranda</th>
                            <th style="padding:.4rem .5rem;text-align:left;">Título / Descripción</th>
                            <th style="padding:.4rem .5rem;text-align:left;">Cliente</th>
                            <th style="padding:.4rem .5rem;text-align:right;">Tiempo Ejecutado</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${record.tasks.map(t => {
                            const tid = t.id_tarea || '-';
                            const ref = t.referencia || '-';
                            const aranda = t.arranda_ticket_name || t.ticket_aranda || t.aranda_ticket || '-';
                            const mins = parseFloat(t.total_minutos_en_estado_ejecucion) || parseFloat(t.duracion_min) || 0;
                            const title = t.titulo || t.descripcion || '-';
                            const client = t.cliente || '-';
                            return `
                                <tr style="border-bottom:1px solid #f1f5f9;">
                                    <td style="padding:.4rem .5rem;font-weight:800;color:#16a34a;">#${tid}</td>
                                    <td style="padding:.4rem .5rem;">
                                        <div style="font-weight:700;color:#0369a1;">Ref: ${ref}</div>
                                        <div style="font-weight:700;color:#4338ca;">Aranda: ${aranda}</div>
                                    </td>
                                    <td style="padding:.4rem .5rem;color:#334155;max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="${title}">${title}</td>
                                    <td style="padding:.4rem .5rem;color:#64748b;">${client}</td>
                                    <td style="padding:.4rem .5rem;text-align:right;font-weight:800;color:#0f172a;">${formatMinutes(mins)}</td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        </div>
    `;

    if (window.Swal) {
        Swal.fire({
            title: `<i class="fas fa-clock text-indigo-600 mr-2"></i> Tareas del Día - ${record.specName}`,
            html: html,
            width: '720px',
            showCloseButton: true,
            confirmButtonText: '<i class="fas fa-check mr-1"></i> Entendido',
            confirmButtonColor: '#6366f1'
        });
    } else {
        alert(`Tareas de ${record.specName} el ${record.formattedDate}:\n` + record.tasks.map(t => `#${t.id_tarea}: ${t.titulo||t.descripcion}`).join('\n'));
    }
};

function cmpRenderCharts(dailyRecords, targetHours) {
    if (typeof Chart !== 'function') return;

    if (window.cmpCharts.diario) window.cmpCharts.diario.destroy();
    if (window.cmpCharts.proporcion) window.cmpCharts.proporcion.destroy();

    // Chart 1: Bar Chart of Daily Worked Hours vs Target Hours
    const chartRecs = dailyRecords.slice(0, 20).reverse();

    const labels = chartRecs.map(r => `${r.formattedDate.substring(0,5)} (${r.specName.split(' ')[0]})`);
    const hoursData = chartRecs.map(r => parseFloat((r.totalMins / 60).toFixed(2)));
    const barColors = chartRecs.map(r => (r.totalMins / 60) >= targetHours ? '#10b981' : '#ef4444');

    const ctx1 = document.getElementById('cmp-chart-diario')?.getContext('2d');
    if (ctx1) {
        window.cmpCharts.diario = new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Horas Efectivas Trabajadas',
                        data: hoursData,
                        backgroundColor: barColors,
                        borderRadius: 6,
                        borderWidth: 0
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return ` Horas Trabajadas: ${ctx.raw}h (Meta: ${targetHours}h)`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Horas Trabajadas' }
                    },
                    x: {
                        ticks: { font: { size: 10 } }
                    }
                }
            }
        });
    }

    // Chart 2: Donut Chart
    let compliantCount = 0;
    let deficitCount = 0;
    dailyRecords.forEach(r => {
        if ((r.totalMins / 60) >= targetHours) compliantCount++;
        else deficitCount++;
    });

    const ctx2 = document.getElementById('cmp-chart-proporcion')?.getContext('2d');
    if (ctx2) {
        window.cmpCharts.proporcion = new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: ['Cumplidos (>= ' + targetHours + 'h)', 'Incumplidos (< ' + targetHours + 'h en Rojo)'],
                datasets: [{
                    data: [compliantCount, deficitCount],
                    backgroundColor: ['#10b981', '#ef4444'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }
}


</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>

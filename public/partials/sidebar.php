<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../src/auth.php';
$user = current_user();
$cur = basename($_SERVER['SCRIPT_NAME']);
$current_sheet = $_GET['name'] ?? '';
?>
<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary elevation-4" style="background-color: var(--sonda-navy);">
  <!-- Brand Logo -->
  <a href="<?php echo PUBLIC_URL_PREFIX; ?>/dashboard.php" class="brand-link d-flex align-items-center" style="border-bottom: 1px solid rgba(255,255,255,0.1); padding: 12px 15px; text-decoration: none;">
    <img src="<?php echo PUBLIC_URL_PREFIX; ?>/logo/logo_white.png" alt="SYNAPSE Logo" class="brand-image" style="opacity: 1; max-height: 38px; width: auto; margin-right: 12px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));">
    <div class="d-flex flex-column">
      <span class="brand-text font-weight-bolder" style="color: #ffffff; letter-spacing: 1px; font-size: 1.35rem; line-height: 1.1;">
        SYNAPSE
      </span>
      <span class="text-white-50" style="font-size: 0.75rem; letter-spacing: 0.5px;">v1.0</span>
    </div>
  </a>

  <!-- Sidebar -->
  <div class="sidebar">

    <!-- Sidebar Menu -->
    <nav class="mt-2">
      <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
        <?php
          require_once __DIR__ . '/../../src/helpers.php';
          require_once __DIR__ . '/../../src/permissions_helper.php';
          $sheet_tables = listSheetTables();
          $is_cmdb_page = ($cur === 'cmdb.php' || $cur === 'item_detail.php' || $cur === 'history.php');
          $activos_list = ['sheet_routers', 'sheet_switches', 'sheet_aps', 'sheet_laptops', 'sheet_servers', 'sheet_datastores', 'sheet_vms'];
          $is_activos_page = ($is_cmdb_page && in_array($current_sheet, $activos_list));
          $is_pasivos_page = ($is_cmdb_page && $current_sheet === 'sheet_pasivos');
          $is_equipos_page = ($is_activos_page || $is_pasivos_page);
        ?>
        
        <!-- Módulo FEMSA (Principal - Top Tab) -->
        <?php if (has_role('SUPER_ADMIN') || has_module_access('femsa')): ?>
        <li class="nav-item">
          <a href="<?php echo PUBLIC_URL_PREFIX; ?>/femsa/index.php" class="nav-link <?php echo (strpos($_SERVER['SCRIPT_NAME'], '/femsa/') !== false) ? 'active' : ''; ?>">
            <i class="nav-icon fas fa-building text-danger"></i>
            <p>FEMSA <span class="badge badge-danger ml-1">Nuevo</span></p>
          </a>
        </li>
        <?php endif; ?>

        <!-- Módulo ACTIVIDADES (Principal - Top Tab) -->
        <?php if (has_role('SUPER_ADMIN') || has_module_access('actividades')): ?>
        <li class="nav-item">
          <a href="<?php echo PUBLIC_URL_PREFIX; ?>/actividades/index.php" class="nav-link <?php echo (strpos($_SERVER['SCRIPT_NAME'], '/actividades/') !== false) ? 'active' : ''; ?>">
            <i class="nav-icon fas fa-tasks text-primary"></i>
            <p>ACTIVIDADES <span class="badge badge-primary ml-1">Nuevo</span></p>
          </a>
        </li>
        <?php endif; ?>

        <!-- Módulo CMDB_SONDA (Principal - Top Tab Autónomo & BI) -->
        <?php if (has_role('SUPER_ADMIN') || has_module_access('cmdb_sonda')): ?>
        <?php
          $is_cmdb_sonda_root = (strpos($_SERVER['SCRIPT_NAME'], '/cmdb_sonda/') !== false);
          $is_cmdb_sonda_inv = ($cur === 'index.php' && $is_cmdb_sonda_root);
          $is_cmdb_sonda_dash = ($cur === 'dashboard.php' && $is_cmdb_sonda_root);
        ?>
        <li class="nav-item <?php echo $is_cmdb_sonda_root ? 'menu-is-opening menu-open' : ''; ?>">
          <a href="#" class="nav-link <?php echo $is_cmdb_sonda_root ? 'active' : ''; ?>" style="<?php echo $is_cmdb_sonda_root ? 'background: linear-gradient(135deg, #002b49 0%, #0052cc 100%) !important; color: #fff !important;' : ''; ?>">
            <i class="nav-icon fas fa-cubes text-info"></i>
            <p>
              CMDB_SONDA
              <i class="right fas fa-angle-left"></i>
              <span class="badge badge-info ml-1">BI</span>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/cmdb_sonda/index.php" class="nav-link <?php echo $is_cmdb_sonda_inv ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-info"></i>
                <p>Inventario & Topología</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/cmdb_sonda/dashboard.php" class="nav-link <?php echo $is_cmdb_sonda_dash ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-warning"></i>
                <p>Dashboard Analítico BI</p>
              </a>
            </li>
          </ul>
        </li>
        <?php endif; ?>

        <!-- Módulo Vilaseca (Principal - Left Menu Tab) -->
        <?php if (has_role('SUPER_ADMIN') || has_module_access('clientes') || has_module_access('vilaseca')): ?>
        <?php 
          $is_vilaseca_portmapping = ($cur === 'portmapping.php' && isset($_GET['cliente']) && strtoupper($_GET['cliente']) === 'VILASECA');
          $is_vilaseca_visio = ($cur === 'visio.php' && isset($_GET['cliente']) && strtoupper($_GET['cliente']) === 'VILASECA');
          $is_vilaseca_analisis = ($cur === 'analisis_conexiones.php');
          $is_vilaseca_root = (strpos($_SERVER['SCRIPT_NAME'], '/clientes/vilaseca/') !== false);
          
          // Datacenter Vilaseca state detection
          $is_in_datacenter = (strpos($_SERVER['SCRIPT_NAME'], '/datacenter/') !== false);
          $is_dc_vilaseca_client = (isset($_GET['cliente']) && strtoupper($_GET['cliente']) === 'VILASECA');
          $is_vilaseca_only_user = (!has_role('SUPER_ADMIN') && !has_module_access('datacenter') && has_module_access('vilaseca'));
          $is_vilaseca_dc = $is_in_datacenter && ($is_dc_vilaseca_client || $is_vilaseca_only_user);
          $is_vilaseca_rooms = ($cur === 'rooms.php' && $is_vilaseca_dc);
          $is_vilaseca_racks = (in_array($cur, ['racks.php', 'rack_builder.php', 'floor_plan.php']) && $is_vilaseca_dc);
          $is_vilaseca_3dviewer = (in_array($cur, ['viewer_3d.php', 'floor_plan_3d.php']) && $is_vilaseca_dc);
          $is_vilaseca_dc_analisis = ($cur === 'analisis.php' && $is_vilaseca_dc);

          $is_vilaseca_menu_open = ($is_vilaseca_root || $is_vilaseca_portmapping || $is_vilaseca_visio || $is_vilaseca_analisis || $is_vilaseca_dc);
        ?>
        <li class="nav-item <?php echo $is_vilaseca_menu_open ? 'menu-is-opening menu-open' : ''; ?>">
          <a href="#" class="nav-link <?php echo $is_vilaseca_menu_open ? 'active' : ''; ?>">
            <i class="nav-icon fas fa-building" style="color: #ff5c05;"></i>
            <p>
              Vilaseca
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <!-- 1. Dashboard (Manage) -->
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/clientes/vilaseca/index.php" class="nav-link <?php echo $is_vilaseca_root ? 'active' : ''; ?>">
                <i class="fas fa-chart-line nav-icon" style="color: #ff5c05; font-size: 0.88rem;"></i>
                <p>Dashboard (Manage)</p>
              </a>
            </li>

            <!-- 2. Datacenter dentro de Vilaseca -->
            <li class="nav-item <?php echo $is_vilaseca_dc ? 'menu-is-opening menu-open' : ''; ?>">
              <a href="#" class="nav-link <?php echo $is_vilaseca_dc ? 'active' : ''; ?>" style="font-weight: 600;">
                <i class="fas fa-server nav-icon" style="color: #00B8D4;"></i>
                <p>
                  Datacenter
                  <i class="right fas fa-angle-left"></i>
                </p>
              </a>
              <ul class="nav nav-treeview" style="padding-left: 10px;">
                <li class="nav-item">
                  <a href="<?php echo PUBLIC_URL_PREFIX; ?>/datacenter/rooms.php?cliente=VILASECA" class="nav-link <?php echo $is_vilaseca_rooms ? 'active' : ''; ?>">
                    <i class="fas fa-door-open nav-icon" style="color: #00B8D4; font-size: 0.85rem;"></i>
                    <p>Cuartos / Salas</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="<?php echo PUBLIC_URL_PREFIX; ?>/datacenter/racks.php?cliente=VILASECA" class="nav-link <?php echo $is_vilaseca_racks ? 'active' : ''; ?>">
                    <i class="fas fa-cubes nav-icon" style="color: #ff5c05; font-size: 0.85rem;"></i>
                    <p>Racks (Datacenter)</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="<?php echo PUBLIC_URL_PREFIX; ?>/datacenter/viewer_3d.php?cliente=VILASECA" class="nav-link <?php echo $is_vilaseca_3dviewer ? 'active' : ''; ?>">
                    <i class="fas fa-cube nav-icon" style="color: #38bdf8; font-size: 0.85rem;"></i>
                    <p>3DViewer</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="<?php echo PUBLIC_URL_PREFIX; ?>/datacenter/analisis.php?cliente=VILASECA" class="nav-link <?php echo $is_vilaseca_dc_analisis ? 'active' : ''; ?>">
                    <i class="fas fa-heartbeat nav-icon" style="color: #c0da20; font-size: 0.85rem;"></i>
                    <p>Análisis de Disponibilidad</p>
                  </a>
                </li>
              </ul>
            </li>

            <!-- 3. Portmapping -->
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/portmapping.php?cliente=VILASECA" class="nav-link <?php echo $is_vilaseca_portmapping ? 'active' : ''; ?>">
                <i class="fas fa-network-wired nav-icon" style="color: #00B8D4; font-size: 0.85rem;"></i>
                <p>Portmapping</p>
              </a>
            </li>

            <!-- 4. Diagramas de Red (Modelos Visio) -->
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/visio.php?cliente=VILASECA" class="nav-link <?php echo $is_vilaseca_visio ? 'active' : ''; ?>">
                <i class="fas fa-sitemap nav-icon" style="color: #c0da20; font-size: 0.85rem;"></i>
                <p>Diagramas de Red</p>
              </a>
            </li>

            <!-- 5. Topología de Conexiones -->
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/analisis_conexiones.php?cliente=VILASECA" class="nav-link <?php echo $is_vilaseca_analisis ? 'active' : ''; ?>">
                <i class="fas fa-project-diagram nav-icon" style="color: #ff5c05; font-size: 0.85rem;"></i>
                <p>Topología de Conexiones</p>
              </a>
            </li>
          </ul>
        </li>
        <?php endif; ?>


        <?php if (has_role('SUPER_ADMIN') || has_module_access('dashboard')): ?>
        <li class="nav-item">
          <a href="<?php echo PUBLIC_URL_PREFIX; ?>/dashboard.php" class="nav-link <?php echo $cur === 'dashboard.php' ? 'active' : ''; ?>">
            <i class="nav-icon fas fa-tachometer-alt"></i>
            <p>Dashboard General</p>
          </a>
        </li>
        <?php endif; ?>

        <?php if (has_role('SUPER_ADMIN') || has_module_access('novaiops_dashboard')): ?>
        <li class="nav-item">
          <a href="<?php echo PUBLIC_URL_PREFIX; ?>/novaiops_dashboard.php" class="nav-link <?php echo $cur === 'novaiops_dashboard.php' ? 'active' : ''; ?>">
            <i class="nav-icon fas fa-chart-pie text-info"></i>
            <p>NovaIOPS</p>
          </a>
        </li>
        <?php endif; ?>

        <!-- CMDB (Graph-Based List) -->
        <?php
          require_once __DIR__ . '/../../src/db.php';
          $pdo = getPDO();
          $cat_stmt = $pdo->query("SELECT id, name, parent_id, icon FROM ci_categories ORDER BY parent_id, name");
          $all_categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);
          
          if (!function_exists('buildSidebarTree')) {
              function buildSidebarTree(array $elements, $parentId = 0) {
                  $branch = array();
                  foreach ($elements as $element) {
                      $elementParent = $element['parent_id'] ? $element['parent_id'] : 0;
                      if ($elementParent == $parentId) {
                          $children = buildSidebarTree($elements, $element['id']);
                          if ($children) {
                              $element['children'] = $children;
                          } else {
                              $element['children'] = [];
                          }
                          $branch[$element['id']] = $element;
                      }
                  }
                  return $branch;
              }
          }
          
          if (!function_exists('renderSidebarTreeHTML')) {
              function renderSidebarTreeHTML($nodes, $active_cat_id) {
                  $html = '';
                  foreach ($nodes as $node) {
                      $has_children = !empty($node['children']);
                      
                      $check_active = function($n, $active_id) use (&$check_active) {
                          if ($n['id'] == $active_id) return true;
                          if (!empty($n['children'])) {
                              foreach ($n['children'] as $child) {
                                  if ($check_active($child, $active_id)) return true;
                              }
                          }
                          return false;
                      };
                      $is_node_open = $check_active($node, $active_cat_id);
                      $is_active = ($active_cat_id == $node['id']);
                      
                      $open_class = $is_node_open ? 'menu-is-opening menu-open' : '';
                      $active_class = $is_active ? 'active' : '';
                      $icon = !empty($node['icon']) ? $node['icon'] : ($has_children ? 'fa-folder' : 'fa-cube');
                      if (strpos($icon, 'fa-') === false) $icon = 'fa-' . $icon;
                      
                      $html .= '<li class="nav-item ' . $open_class . '">';
                      $html .= '<a href="' . PUBLIC_URL_PREFIX . '/ci_list.php?category_id=' . $node['id'] . '" class="nav-link ' . $active_class . '" onclick="window.location.href=this.href;">';
                      $html .= '<i class="fas ' . $icon . ' nav-icon ' . ($has_children ? 'text-success' : 'text-warning') . '"></i>';
                      $html .= '<p>' . htmlspecialchars($node['name']);
                      if ($has_children) {
                          $html .= '<i class="right fas fa-angle-left"></i>';
                      }
                      $html .= '</p></a>';
                      
                      if ($has_children) {
                          $html .= '<ul class="nav nav-treeview" style="margin-left: 10px;">';
                          $html .= renderSidebarTreeHTML($node['children'], $active_cat_id);
                          $html .= '</ul>';
                      }
                      $html .= '</li>';
                  }
                  return $html;
              }
          }

          $cat_tree = buildSidebarTree($all_categories);
          
          $is_cmdb_nuevo_active = ($cur === 'ci_list.php');
          $active_cat_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
        ?>
        <?php if (has_module_access('ci_list')): ?>
        <li class="nav-item">
          <a href="<?php echo PUBLIC_URL_PREFIX; ?>/ci_list.php" class="nav-link <?php echo $is_cmdb_nuevo_active ? 'active' : ''; ?>">
            <i class="nav-icon fas fa-project-diagram text-primary"></i>
            <p>CMDB</p>
          </a>
        </li>
        <?php endif; ?>

        <?php if (has_role('SUPER_ADMIN') || has_module_access('gitlab')): ?>
        <li class="nav-item">
          <a href="<?php echo PUBLIC_URL_PREFIX; ?>/plugins/gitlab/index.php" class="nav-link <?php echo ($cur === 'index.php' && strpos($_SERVER['SCRIPT_NAME'], '/plugins/gitlab/') !== false) ? 'active' : ''; ?>">
            <i class="nav-icon fab fa-gitlab text-warning"></i>
            <p>GitLab</p>
          </a>
        </li>
        <?php endif; ?>

        <?php if (has_role('SUPER_ADMIN') || has_module_access('aranda')): ?>
        <li class="nav-item">
          <a href="<?php echo PUBLIC_URL_PREFIX; ?>/aranda.php" class="nav-link <?php echo $cur === 'aranda.php' ? 'active' : ''; ?>">
            <i class="nav-icon fas fa-project-diagram text-success"></i>
            <p>Aranda API</p>
          </a>
        </li>
        <?php endif; ?>


        <?php
          $has_any_sheet_access = false;
          foreach ($sheet_tables as $table) {
              if (has_sheet_access($table)) {
                  $has_any_sheet_access = true;
                  break;
              }
          }

          $has_any_activo_access = false;
          foreach ($activos_list as $activo_type) {
              if (has_sheet_access($activo_type)) {
                  $has_any_activo_access = true;
                  break;
              }
          }

          $has_pasivos_access = has_sheet_access('sheet_pasivos');
          $has_equipos_access = $has_any_activo_access || $has_pasivos_access;
        ?>

        <?php if ($has_any_sheet_access): ?>
        <li class="nav-item <?php echo $is_cmdb_page ? 'menu-is-opening menu-open' : ''; ?>">
          <a href="#" class="nav-link <?php echo $is_cmdb_page ? 'active' : ''; ?>">
            <i class="nav-icon fas fa-database"></i>
            <p>
              PRECMDB
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <!-- Equipos Menu -->
            <?php if ($has_equipos_access): ?>
            <li class="nav-item <?php echo $is_equipos_page ? 'menu-is-opening menu-open' : ''; ?>">
              <a href="#" class="nav-link <?php echo $is_equipos_page ? 'active' : ''; ?>">
                <i class="nav-icon fas fa-desktop"></i>
                <p>
                  Equipos
                  <i class="right fas fa-angle-left"></i>
                </p>
              </a>
              <ul class="nav nav-treeview" style="margin-left: 10px;">
                <?php if ($has_any_activo_access): ?>
                <li class="nav-item <?php echo $is_activos_page ? 'menu-is-opening menu-open' : ''; ?>">
                  <a href="#" class="nav-link <?php echo $is_activos_page ? 'active' : ''; ?>">
                    <i class="nav-icon fas fa-hdd"></i>
                    <p>
                      Activos
                      <i class="right fas fa-angle-left"></i>
                    </p>
                  </a>
                  <ul class="nav nav-treeview" style="margin-left: 10px;">
                    <?php foreach ($activos_list as $activo_type): ?>
                      <?php if (has_sheet_access($activo_type)): ?>
                        <?php $sheet_name_clean = ucfirst(str_replace('sheet_', '', $activo_type)); ?>
                        <li class="nav-item">
                          <a href="<?php echo PUBLIC_URL_PREFIX; ?>/cmdb.php?name=<?php echo urlencode($activo_type); ?>" class="nav-link <?php echo $current_sheet === $activo_type ? 'active' : ''; ?>">
                            <i class="far fa-circle nav-icon text-success"></i>
                            <p><?php echo $sheet_name_clean; ?></p>
                          </a>
                        </li>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </ul>
                </li>
                <?php endif; ?>
                
                <?php if ($has_pasivos_access): ?>
                <li class="nav-item">
                  <a href="<?php echo PUBLIC_URL_PREFIX; ?>/cmdb.php?name=sheet_pasivos" class="nav-link <?php echo $is_pasivos_page ? 'active' : ''; ?>">
                    <i class="nav-icon fas fa-plug"></i>
                    <p>Pasivos</p>
                  </a>
                </li>
                <?php endif; ?>
              </ul>
            </li>
            <?php endif; ?>

            <!-- Original Sheets -->
            <?php foreach ($sheet_tables as $table): ?>
              <?php 
                $sheet_name_clean = preg_replace('/^sheet_/', '', $table);
                // Evitar duplicados si las tablas dinámicas coinciden con 'equipos'
                if (in_array($table, $activos_list) || $table === 'sheet_pasivos') continue; 
              ?>
              <?php if (has_sheet_access($table)): ?>
                <li class="nav-item">
                  <a href="<?php echo PUBLIC_URL_PREFIX; ?>/cmdb.php?name=<?php echo urlencode($table); ?>" class="nav-link <?php echo $current_sheet === $table ? 'active' : ''; ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p><?php echo htmlspecialchars(ucfirst($sheet_name_clean)); ?></p>
                  </a>
                </li>
              <?php endif; ?>
            <?php endforeach; ?>
          </ul>
        </li>
        <?php endif; ?>

        <?php
          $is_datacenter_open = in_array($cur, ['rooms.php', 'racks.php', 'rack_builder.php', 'floor_plan.php', 'analisis.php', 'viewer_3d.php', 'floor_plan_3d.php']) && (!isset($_GET['cliente']) || strtoupper($_GET['cliente']) !== 'VILASECA');
        ?>
        <?php if (has_module_access('datacenter')): ?>
        <li class="nav-item <?php echo $is_datacenter_open ? 'menu-open' : ''; ?>">
          <a href="#" class="nav-link <?php echo $is_datacenter_open ? 'active' : ''; ?>">
            <i class="nav-icon fas fa-building text-warning"></i>
            <p>
              Datacenter (DCIM)
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/datacenter/rooms.php" class="nav-link <?php echo $cur === 'rooms.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon"></i>
                <p>Cuartos / Rooms</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/datacenter/racks.php" class="nav-link <?php echo $cur === 'racks.php' || $cur === 'rack_builder.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon"></i>
                <p>Racks (Gabinetes)</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/datacenter/viewer_3d.php" class="nav-link <?php echo in_array($cur, ['viewer_3d.php', 'floor_plan_3d.php']) ? 'active' : ''; ?>">
                <i class="fas fa-cube nav-icon" style="color: #38bdf8; font-size: 0.85rem;"></i>
                <p>3DViewer</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/datacenter/analisis.php" class="nav-link <?php echo $cur === 'analisis.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-info"></i>
                <p>Análisis Datacenter</p>
              </a>
            </li>
          </ul>
        </li>
        <?php endif; ?>

        <?php
          $is_zabbix_page = in_array($cur, ['zabbix_dashboard.php', 'zabbix_hosts.php', 'reports_zabbix.php', 'monitoreo.php', 'crear_monitoreo.php', 'actualizar_monitoreo.php', 'problems.php', 'interfaces_manager.php']) || 
                            strpos($_SERVER['SCRIPT_NAME'], '/costos/') !== false || 
                            strpos($_SERVER['SCRIPT_NAME'], '/storage/') !== false ||
                            strpos($_SERVER['SCRIPT_NAME'], '/kanbanzabbix/') !== false;
        ?>
        <?php if (has_module_access('monitoreo')): ?>
        <li class="nav-item <?php echo $is_zabbix_page ? 'menu-is-opening menu-open' : ''; ?>">
          <a href="#" class="nav-link <?php echo $is_zabbix_page ? 'active' : ''; ?>">
            <i class="nav-icon fas fa-server"></i>
            <p>
              Zabbix
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/kanbanzabbix/index.php" class="nav-link <?php echo strpos($_SERVER['SCRIPT_NAME'], '/kanbanzabbix/') !== false ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-danger"></i>
                <p>Kanban Zabbix</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/monitoreo.php" class="nav-link <?php echo in_array($cur, ['monitoreo.php', 'problems.php']) ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon"></i>
                <p>Dashboard</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/zabbix_hosts.php" class="nav-link <?php echo $cur === 'zabbix_hosts.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon"></i>
                <p>Equipos</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/reports_zabbix.php" class="nav-link <?php echo $cur === 'reports_zabbix.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-success"></i>
                <p>Informes</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/interfaces_manager.php" class="nav-link <?php echo $cur === 'interfaces_manager.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-primary"></i>
                <p>Gestión Interfaces</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/costos/dashboard.php" class="nav-link <?php echo strpos($_SERVER['SCRIPT_NAME'], '/costos/') !== false ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-warning"></i>
                <p>Costos ZBX</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/storage/dashboard.php" class="nav-link <?php echo strpos($_SERVER['SCRIPT_NAME'], '/storage/') !== false ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-info"></i>
                <p>Análisis Storage</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/crear_monitoreo.php" class="nav-link <?php echo $cur === 'crear_monitoreo.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-muted"></i>
                <p><small>Asistente Creación</small></p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/actualizar_monitoreo.php" class="nav-link <?php echo $cur === 'actualizar_monitoreo.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-muted"></i>
                <p><small>Asistente Actualización</small></p>
              </a>
            </li>
          </ul>
        </li>
        <?php endif; ?>

        <!-- Módulo Proyectos -->
        <?php if (has_module_access('project')): ?>
        <li class="nav-item">
          <a href="<?php echo PUBLIC_URL_PREFIX; ?>/project.php" class="nav-link <?php echo $cur === 'project.php' ? 'active' : ''; ?>">
            <i class="nav-icon fas fa-tasks text-success"></i>
            <p>Proyectos</p>
          </a>
        </li>
        <?php endif; ?>

        <!-- Módulo Diagramas (Grupo de diagramación y topologías) -->
        <?php if (has_module_access('diagrams') || has_module_access('topology') || has_module_access('portmapping')): ?>
        <?php 
          $diag_active = in_array($cur, ['flujos.php', 'bpmn.php', 'visio.php', 'topology.php', 'topology_3d.php', 'portmapping.php']);
        ?>
        <li class="nav-item <?php echo $diag_active ? 'menu-open' : ''; ?>">
          <a href="#" class="nav-link <?php echo $diag_active ? 'active' : ''; ?>">
            <i class="nav-icon fas fa-project-diagram text-primary"></i>
            <p>
              Diagramas
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <?php if (has_module_access('diagrams')): ?>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/flujos.php" class="nav-link <?php echo $cur === 'flujos.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-info"></i>
                <p>Flujos (Mermaid)</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/bpmn.php" class="nav-link <?php echo $cur === 'bpmn.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-warning"></i>
                <p>Procesos (BPMN)</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/visio.php" class="nav-link <?php echo $cur === 'visio.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-success"></i>
                <p>Modelos Visio (VSDX)</p>
              </a>
            </li>
            <?php endif; ?>
            
            <?php if (has_module_access('topology')): ?>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/topology.php" class="nav-link <?php echo $cur === 'topology.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-primary"></i>
                <p>Topología (2D)</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/topology_3d.php" class="nav-link <?php echo $cur === 'topology_3d.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-danger"></i>
                <p>Topología (3D)</p>
              </a>
            </li>
            <?php endif; ?>
            
            <?php if (has_module_access('portmapping')): ?>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/portmapping.php" class="nav-link <?php echo $cur === 'portmapping.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-warning"></i>
                <p>Portmapping</p>
              </a>
            </li>
            <?php endif; ?>
          </ul>
        </li>
        <?php endif; ?>

        <!-- Módulo PASSWORD -->
        <?php if (has_module_access('password')): ?>
        <li class="nav-item">
          <a href="<?php echo PUBLIC_URL_PREFIX; ?>/password.php" class="nav-link <?php echo $cur === 'password.php' ? 'active' : ''; ?>">
            <i class="nav-icon fas fa-key text-warning"></i>
            <p>PASSWORD</p>
          </a>
        </li>
        <?php endif; ?>

        <!-- Módulo Cotizador -->
        <?php if (has_module_access('cotizador')): ?>
        <?php 
          $is_cotizador = (strpos($_SERVER['SCRIPT_NAME'], '/cotizador/') !== false);
          $active_sub = $is_cotizador ? ($_GET['tab'] ?? 'configurador') : '';
        ?>
        <li class="nav-item <?php echo $is_cotizador ? 'menu-open' : ''; ?>">
          <a href="#" class="nav-link <?php echo $is_cotizador ? 'active' : ''; ?>">
            <i class="nav-icon fas fa-calculator text-info"></i>
            <p>
              Cotizador
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/cotizador/index.php?tab=configurador" class="nav-link <?php echo ($is_cotizador && $active_sub === 'configurador') ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-primary"></i>
                <p>Configurador</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/cotizador/index.php?tab=editor" class="nav-link <?php echo ($is_cotizador && $active_sub === 'editor') ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-info"></i>
                <p>Diseño Cotización</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/cotizador/index.php?tab=list" class="nav-link <?php echo ($is_cotizador && $active_sub === 'list') ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-success"></i>
                <p>Historial Cotizaciones</p>
              </a>
            </li>
          </ul>
        </li>
        <?php endif; ?>

        <!-- Módulo CLIENTES -->
        <?php if (has_module_access('clientes')): ?>
        <?php 
          $is_clientes = (strpos($_SERVER['SCRIPT_NAME'], '/clientes/') !== false);
          $is_sonda = (strpos($_SERVER['SCRIPT_NAME'], '/clientes/sonda/') !== false);
          $is_gpf = (strpos($_SERVER['SCRIPT_NAME'], '/clientes/gpf/') !== false);
          $is_vilaseca = (strpos($_SERVER['SCRIPT_NAME'], '/clientes/vilaseca/') !== false);
        ?>
        <li class="nav-item <?php echo $is_clientes ? 'menu-open' : ''; ?>">
          <a href="#" class="nav-link <?php echo $is_clientes ? 'active' : ''; ?>">
            <i class="nav-icon fas fa-users text-primary"></i>
            <p>
              CLIENTES
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <!-- SONDA -->
            <li class="nav-item <?php echo $is_sonda ? 'menu-open' : ''; ?>" style="padding-left: 10px;">
              <a href="#" class="nav-link <?php echo $is_sonda ? 'active' : ''; ?>">
                <i class="far fa-folder nav-icon text-warning"></i>
                <p>
                  SONDA
                  <i class="right fas fa-angle-left"></i>
                </p>
              </a>
              <ul class="nav nav-treeview">
                <!-- iin -->
                <li class="nav-item menu-open" style="padding-left: 15px;">
                  <a href="#" class="nav-link active">
                    <i class="far fa-folder-open nav-icon text-info"></i>
                    <p>
                      iin
                      <i class="right fas fa-angle-left"></i>
                    </p>
                  </a>
                  <ul class="nav nav-treeview" style="display: block;">
                    <li class="nav-item">
                      <a href="<?php echo PUBLIC_URL_PREFIX; ?>/clientes/sonda/iin/disponibilidad.php" class="nav-link <?php echo $cur === 'disponibilidad.php' ? 'active' : ''; ?>">
                        <i class="far fa-chart-bar nav-icon text-success"></i>
                        <p style="font-size: 0.85rem;">Inf. Disponibilidad</p>
                      </a>
                    </li>
                    <li class="nav-item">
                      <a href="<?php echo PUBLIC_URL_PREFIX; ?>/clientes/sonda/iin/respaldos_veeam.php" class="nav-link <?php echo $cur === 'respaldos_veeam.php' ? 'active' : ''; ?>">
                        <i class="fas fa-hdd nav-icon text-info"></i>
                        <p style="font-size: 0.85rem;">Respaldos Veeam</p>
                      </a>
                    </li>
                    <li class="nav-item">
                      <a href="<?php echo PUBLIC_URL_PREFIX; ?>/clientes/sonda/iin/wifi_monitor.php" class="nav-link <?php echo $cur === 'wifi_monitor.php' ? 'active' : ''; ?>">
                        <i class="fas fa-wifi nav-icon text-warning"></i>
                        <p style="font-size: 0.85rem;">Wi-Fi Monitor Pro</p>
                      </a>
                    </li>
                    <li class="nav-item">
                      <a href="<?php echo PUBLIC_URL_PREFIX; ?>/clientes/sonda/iin/analisis_wireless.php" class="nav-link <?php echo $cur === 'analisis_wireless.php' ? 'active' : ''; ?>">
                        <i class="fas fa-signal nav-icon text-danger"></i>
                        <p style="font-size: 0.85rem;">Análisis Wireless</p>
                      </a>
                    </li>
                  </ul>
                </li>
              </ul>
            </li>
            <!-- GPF -->
            <li class="nav-item" style="padding-left: 10px;">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/clientes/gpf/index.php" class="nav-link <?php echo $is_gpf ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-success"></i>
                <p>GPF</p>
              </a>
            </li>
            <!-- VILASECA -->
            <li class="nav-item" style="padding-left: 10px;">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/clientes/vilaseca/index.php" class="nav-link <?php echo $is_vilaseca_root ? 'active' : ''; ?>">
                <i class="fas fa-chart-line nav-icon" style="color: #ff5c05; font-size: 0.85rem;"></i>
                <p>Vilaseca (Manage)</p>
              </a>
            </li>
          </ul>
        </li>
        <?php endif; ?>

        <?php if (has_module_access('snmp')): ?>
        <li class="nav-item <?php echo in_array($cur, ['snmp_management.php', 'snmp_builder.php', 'snmp_mibs.php']) ? 'menu-open' : ''; ?>">
          <a href="#" class="nav-link <?php echo in_array($cur, ['snmp_management.php', 'snmp_builder.php', 'snmp_mibs.php']) ? 'active' : ''; ?>">
            <i class="nav-icon fas fa-network-wired text-info"></i>
            <p>
              Módulo SNMP
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/snmp_management.php" class="nav-link <?php echo $cur === 'snmp_management.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-primary"></i>
                <p>Gestión / Escaneo</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/snmp_builder.php" class="nav-link <?php echo $cur === 'snmp_builder.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-info"></i>
                <p>SNMP Builder</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/snmp_mibs.php" class="nav-link <?php echo $cur === 'snmp_mibs.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-success"></i>
                <p>Repositorio MIBs</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/snmp_mibs_analysis.php" class="nav-link <?php echo $cur === 'snmp_mibs_analysis.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-warning"></i>
                <p>Análisis MIB</p>
              </a>
            </li>
          </ul>
        </li>
        <?php endif; ?>

        <?php if (has_module_access('import')): ?>
        <li class="nav-item">
            <a href="<?php echo PUBLIC_URL_PREFIX; ?>/import.php" class="nav-link <?php echo $cur === 'import.php' ? 'active' : ''; ?>">
                <i class="nav-icon fas fa-file-excel"></i>
                <p>Importar Excel</p>
            </a>
        </li>
        <?php endif; ?>

        <?php if (has_module_access('reports')): ?>
        <li class="nav-item">
          <a href="<?php echo PUBLIC_URL_PREFIX; ?>/reports_list.php" class="nav-link <?php echo ($cur === 'reports_list.php' || strpos($_SERVER['SCRIPT_NAME'], '/informes/') !== false) ? 'active' : ''; ?>">
            <i class="nav-icon fas fa-file-invoice text-teal"></i>
            <p>Informes</p>
          </a>
        </li>
        <?php endif; ?>

        <!-- Módulo de Análisis de Logs -->
        <?php if (has_module_access('log_analysis')): ?>
        <li class="nav-item">
          <a href="<?php echo PUBLIC_URL_PREFIX; ?>/log_analysis.php" class="nav-link <?php echo $cur === 'log_analysis.php' ? 'active' : ''; ?>">
            <i class="nav-icon fas fa-terminal text-warning"></i>
            <p>Análisis de Logs</p>
          </a>
        </li>
        <?php endif; ?>

        <?php if (has_module_access('distribrack')): ?>
        <li class="nav-item">
          <a href="<?php echo PUBLIC_URL_PREFIX; ?>/distribrack.php" class="nav-link <?php echo $cur === 'distribrack.php' ? 'active' : ''; ?>">
            <i class="nav-icon fas fa-images"></i>
            <p>Imagenes</p>
          </a>
        </li>
        <?php endif; ?>

        <?php 
          $can_see_user_mgmt = has_role('SUPER_ADMIN') || has_module_access('user_management');
          $can_see_ci_admin = has_role('SUPER_ADMIN') || has_module_access('ci_admin');
          $can_see_sheet_cfg = has_role('SUPER_ADMIN') || has_module_access('sheet_configs');
          $can_see_health = has_role('SUPER_ADMIN') || has_module_access('system_health');
          $can_see_modules = has_role('SUPER_ADMIN') || has_module_access('activated_modules');

          $has_any_admin_access = $can_see_user_mgmt || $can_see_ci_admin || $can_see_sheet_cfg || $can_see_health || $can_see_modules;
        ?>
        <?php if ($has_any_admin_access): ?>
        <?php 
          $admin_pages = ['sheet_configs.php', 'snmp_management.php', 'system_health.php', 'user_management.php', 'ci_builder.php', 'ci_categories.php', 'ci_relationships.php', 'activated_modules.php'];
          $is_admin_open = in_array($cur, $admin_pages);
        ?>
        <li class="nav-item <?php echo $is_admin_open ? 'menu-open' : ''; ?>">
          <a href="#" class="nav-link <?php echo $is_admin_open ? 'active' : ''; ?>">
            <i class="nav-icon fas fa-cogs"></i>
            <p>
              Administración
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <!-- Sub-pestaña CMDB Admin -->
            <?php if ($can_see_ci_admin): ?>
            <li class="nav-item <?php echo in_array($cur, ['ci_builder.php', 'ci_categories.php', 'ci_attributes.php', 'ci_relationships.php']) ? 'menu-is-opening menu-open' : ''; ?>">
              <a href="#" class="nav-link <?php echo in_array($cur, ['ci_builder.php', 'ci_categories.php', 'ci_attributes.php', 'ci_relationships.php']) ? 'active' : ''; ?>">
                <i class="nav-icon fas fa-layer-group text-primary"></i>
                <p>
                  CMDB Admin
                  <i class="right fas fa-angle-left"></i>
                </p>
              </a>
              <ul class="nav nav-treeview" style="margin-left: 10px;">
                <li class="nav-item">
                  <a href="<?php echo PUBLIC_URL_PREFIX; ?>/ci_categories.php" class="nav-link <?php echo $cur === 'ci_categories.php' ? 'active' : ''; ?>">
                    <i class="far fa-circle nav-icon text-warning"></i>
                    <p>CATEGORÍAS</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="<?php echo PUBLIC_URL_PREFIX; ?>/ci_relationships.php" class="nav-link <?php echo $cur === 'ci_relationships.php' ? 'active' : ''; ?>">
                    <i class="far fa-circle nav-icon text-danger"></i>
                    <p>Tipos de Relación</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="<?php echo PUBLIC_URL_PREFIX; ?>/ci_attributes.php" class="nav-link <?php echo $cur === 'ci_attributes.php' ? 'active' : ''; ?>">
                    <i class="far fa-circle nav-icon text-info"></i>
                    <p>Atributos Globales</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="<?php echo PUBLIC_URL_PREFIX; ?>/ci_builder.php" class="nav-link <?php echo $cur === 'ci_builder.php' ? 'active' : ''; ?>">
                    <i class="far fa-circle nav-icon text-success"></i>
                    <p>Crear CI</p>
                  </a>
                </li>
              </ul>
            </li>
            <?php endif; ?>

            <?php if ($can_see_user_mgmt): ?>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/user_management.php" class="nav-link <?php echo $cur === 'user_management.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-primary"></i>
                <p>Gestión Usuarios</p>
              </a>
            </li>
            <?php endif; ?>

            <?php if ($can_see_sheet_cfg): ?>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/sheet_configs.php" class="nav-link <?php echo $cur === 'sheet_configs.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-info"></i>
                <p>Config. Claves</p>
              </a>
            </li>
            <?php endif; ?>

            <?php if ($can_see_health): ?>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/system_health.php" class="nav-link <?php echo $cur === 'system_health.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-danger"></i>
                <p>Salud del Sistema</p>
              </a>
            </li>
            <?php endif; ?>

            <?php if ($can_see_modules): ?>
            <li class="nav-item">
              <a href="<?php echo PUBLIC_URL_PREFIX; ?>/activated_modules.php" class="nav-link <?php echo $cur === 'activated_modules.php' ? 'active' : ''; ?>">
                <i class="far fa-circle nav-icon text-warning"></i>
                <p>Módulos Activados</p>
              </a>
            </li>
            <?php endif; ?>
          </ul>
        </li>
        <?php endif; ?>
      </ul>
    </nav>
    <!-- /.sidebar-menu -->
  </div>
  <!-- /.sidebar -->
</aside>

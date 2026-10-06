<div class="row">
    <!-- PHP, EXTENSIONS & CLI TOOLS -->
    <div class="col-md-6">
        <div class="card card-primary card-outline shadow-sm">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-server mr-1"></i> Entorno PHP y Herramientas del Sistema</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Requisito / Herramienta</th>
                            <th style="width: 120px" class="text-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Versión de PHP (<?php echo $audit['php']['message']; ?>)</td>
                            <td class="text-center"><span class="badge bg-<?php echo $audit['php']['status']; ?>"><?php echo $audit['php']['status'] == 'success' ? 'OK' : 'Baja'; ?></span></td>
                        </tr>
                        <?php foreach ($audit['extensions'] as $ext => $info): ?>
                        <tr>
                            <td>Extensión PHP <b><?php echo $ext; ?></b> <small class="text-muted">(<?php echo $info['description']; ?>)</small></td>
                            <td class="text-center">
                                <?php if ($info['loaded']): ?>
                                    <span class="badge bg-success">Cargada</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">FALTA</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (isset($audit['cli_tools'])): ?>
                        <?php foreach ($audit['cli_tools'] as $tool => $info): ?>
                        <tr>
                            <td>Binario CLI <b><?php echo $tool; ?></b> <small class="text-muted">(<?php echo $info['description']; ?>)</small></td>
                            <td class="text-center">
                                <?php if ($info['exists']): ?>
                                    <span class="badge bg-success">Instalado</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">No hallado</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- DIRECTORIES & CONNECTION -->
    <div class="col-md-6">
        <div class="card card-info card-outline shadow-sm">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-folder-open mr-1"></i> Permisos y Archivos de Sistema</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Directorio / Repositorio</th>
                            <th style="width: 140px" class="text-center">Estado Escritura</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $dir_labels = [
                            'storage' => 'Almacenamiento (Storage)',
                            'logs' => 'Logs del Sistema',
                            'sessions' => 'Sesiones PHP',
                            'uploads' => 'Archivos Adjuntos / Uploads',
                            'snmp_builder' => 'Directorio SNMP Builder',
                            'snmp_mibs' => 'Repositorio de MIBs SNMP',
                            'vendor' => 'Librerías Composer (Vendor)',
                            'gitlab_repo' => 'Repositorio GitLab'
                        ];
                        foreach ($audit['directories'] as $name => $info): 
                            $label = $dir_labels[$name] ?? ucfirst($name);
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($label); ?></strong><br>
                                <code class="small text-dark" style="word-break: break-all;"><?php echo htmlspecialchars($info['path']); ?></code>
                            </td>
                            <td class="text-center align-middle">
                                <?php if ($info['writable']): ?>
                                    <span class="badge bg-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i> Escritura OK</span>
                                <?php elseif($info['exists']): ?>
                                    <span class="badge bg-danger px-2 py-1"><i class="fas fa-lock mr-1"></i> Sin Permisos</span>
                                <?php else: ?>
                                    <span class="badge bg-danger px-2 py-1"><i class="fas fa-exclamation-triangle mr-1"></i> No Existe</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card card-warning card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-database mr-1"></i> Conexiones Externas</h3>
            </div>
            <div class="card-body">
                <div class="callout callout-<?php echo $audit['database']['status']; ?>">
                    <h5>Base de Datos (<?php echo $audit['database']['host']; ?>)</h5>
                    <p><?php echo $audit['database']['message']; ?></p>
                    <?php 
                    $missing_tables = [];
                    $missing_columns = [];
                    if (isset($audit['database']['table_analysis'])) {
                        foreach ($audit['database']['table_analysis'] as $tbl => $info) {
                            if (!$info['exists']) {
                                $missing_tables[] = $tbl;
                            } elseif (!$info['columns_ok']) {
                                $missing_columns[] = $tbl . ' (Falta: ' . implode(', ', $info['missing_cols']) . ')';
                            }
                        }
                    }
                    ?>
                    <?php if (!empty($missing_tables) || !empty($missing_columns)): ?>
                        <div class="mt-2">
                            <?php if(!empty($missing_tables)): ?>
                                <span class="badge badge-danger">Tablas Faltantes:</span>
                                <ul class="mb-1">
                                    <?php foreach($missing_tables as $t): ?>
                                        <li><code><?php echo $t; ?></code></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <?php if(!empty($missing_columns)): ?>
                                <span class="badge badge-warning text-dark">Estructura Desactualizada (Columnas Faltantes):</span>
                                <ul class="mb-0">
                                    <?php foreach($missing_columns as $c): ?>
                                        <li><code><?php echo $c; ?></code></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="mt-2">
                            <span class="badge badge-success"><i class="fas fa-check-double mr-1"></i> Estructura de Tablas y Columnas OK</span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="callout callout-<?php echo $audit['zabbix']['status']; ?>">
                    <h5>Zabbix API</h5>
                    <p>Endpoint: <code><?php echo $audit['zabbix']['url']; ?></code></p>
                    <?php if($audit['zabbix']['status'] == 'warning'): ?>
                        <small class="text-danger"><i class="fas fa-exclamation-triangle"></i> Atención: Apunta a una IP fija interna.</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- DETAILED TABLE ANALYSIS -->
<div class="row">
    <div class="col-12">
        <div class="card card-outline card-teal">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-table mr-2"></i> Análisis Detallado de Tablas Maestras</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>Nombre de Tabla</th>
                            <th>Propósito / Descripción</th>
                            <th class="text-center" style="width: 150px">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (isset($audit['database']['table_analysis'])): ?>
                            <?php foreach ($audit['database']['table_analysis'] as $tableName => $info): ?>
                            <tr>
                                <td class="align-middle"><code><?php echo $tableName; ?></code></td>
                                <td class="align-middle text-muted small"><?php echo $info['description']; ?></td>
                                <td class="text-center align-middle">
                                    <?php if ($info['exists']): ?>
                                        <?php if ($info['columns_ok']): ?>
                                            <span class="badge badge-success px-3"><i class="fas fa-check mr-1"></i> CREADA</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning px-3"><i class="fas fa-columns mr-1"></i> COLUMNAS FALTANTES</span>
                                            <div class="small text-danger mt-1">Falta: <?php echo implode(', ', $info['missing_cols']); ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge badge-danger px-3"><i class="fas fa-exclamation-circle mr-1"></i> FALTANTE</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center p-4">
                                    <i class="fas fa-database text-muted mb-2 fa-2x"></i><br>
                                    No se pudo realizar el análisis de tablas (Sin conexión a BD).
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- RESPALDO INTEGRAL PREPODUCCION A BACK -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card card-outline card-info shadow-sm">
            <div class="card-header bg-navy">
                <h3 class="card-title font-weight-bold text-white mb-0">
                    <i class="fas fa-hdd text-info mr-2"></i> Copia de Respaldo del Proyecto (PREPODUCCION &rarr; BACK)
                </h3>
            </div>
            <div class="card-body bg-light">
                <div class="row align-items-center">
                    <div class="col-md-8 mb-2 mb-md-0">
                        <p class="mb-1 font-weight-bold text-navy">
                            Generar una copia exacta e integral de toda la suite del proyecto.
                        </p>
                        <small class="text-secondary d-block">
                            <i class="fas fa-folder text-primary mr-1"></i> Origen: <code>/var/www/html/PROYECTOSONDA/PREPODUCCION</code>
                        </small>
                        <small class="text-secondary d-block">
                            <i class="fas fa-folder-minus text-success mr-1"></i> Destino: <code>/var/www/html/PROYECTOSONDA/BACK</code>
                        </small>
                    </div>
                    <div class="col-md-4 text-md-right text-center">
                        <form method="POST" onsubmit="return confirm('¿Confirma que desea realizar la copia completa del proyecto desde PREPODUCCION hacia la carpeta BACK?');">
                            <?php if (!empty($token_valido)): ?>
                                <input type="hidden" name="token" value="<?php echo htmlspecialchars(SECURITY_TOKEN); ?>">
                            <?php endif; ?>
                            <button type="submit" name="backup_back" class="btn btn-info btn-lg font-weight-bold shadow px-4">
                                <i class="fas fa-copy mr-2"></i> RESPALDO BACK
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MIGRATION VERDICT -->
<div class="row">
    <div class="col-12">
            <?php if (!empty($log)): ?>
            <div class="alert alert-info alert-dismissible shadow-sm">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                <h5><i class="icon fas fa-info-circle"></i> Resultado de la ejecución:</h5>
                <ul class="mb-0">
                    <?php foreach($log as $line): ?>
                        <li><?php echo $line; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <div class="info-box bg-light">
            <div class="info-box-content">
                <span class="info-box-text text-center text-muted">Veredicto de Portabilidad y Base de Datos</span>
                <span class="info-box-number text-center text-muted mb-0">
                    <?php 
                    $dir_errors = count(array_filter($audit['directories'], function($d){return $d['status']=='error';}));
                    $ext_errors = count(array_filter($audit['extensions'], function($e){return $e['status']=='error';}));
                    $db_errors = 0;
                    if (isset($audit['database']['table_analysis'])) {
                        foreach ($audit['database']['table_analysis'] as $tbl => $info) {
                            if (!$info['exists'] || !$info['columns_ok']) {
                                $db_errors++;
                            }
                        }
                    }
                    $total_errors = $dir_errors + $ext_errors + $db_errors;

                    if ($total_errors > 0 || !$audit['database']['connected']) {
                        echo "<h3 class='text-danger'>⚠️ PORTABILIDAD LIMITADA / ESQUEMA DESACTUALIZADO</h3>";
                        echo "<p>El sistema requiere atención ($total_errors puntos que requieren atención o actualización de esquema).</p>";
                        
                        // Botón de remediación automática (Carpetas + BD)
                        echo '<form method="POST" class="mt-3">
                                ' . ($token_valido ? '<input type="hidden" name="token" value="' . htmlspecialchars(SECURITY_TOKEN) . '">' : '') . '
                                <button type="submit" name="fix_issues" class="btn btn-warning shadow-sm">
                                    <i class="fas fa-magic mr-1"></i> Ejecutar Remediación y Actualizar Base de Datos
                                </button>
                                </form>';
                    } else {
                        echo "<h3 class='text-success'>✅ LISTO PARA MIGRAR / ESQUEMA AL DÍA</h3>";
                        echo "<p>Todos los requisitos del servidor, permisos de carpetas y esquema de base de datos se cumplen.</p>";
                        
                        // Botón opcional para re-ejecutar inicialización
                        echo '<form method="POST" class="mt-3">
                                ' . ($token_valido ? '<input type="hidden" name="token" value="' . htmlspecialchars(SECURITY_TOKEN) . '">' : '') . '
                                <button type="submit" name="fix_issues" class="btn btn-outline-success btn-sm">
                                    <i class="fas fa-sync mr-1"></i> Re-Verificar / Forzar Inicialización de BD
                                </button>
                                </form>';
                    }
                    ?>
                </span>
            </div>
        </div>

        <?php 
        $termCmds = getTerminalSuggestions($audit);
        if (!empty($termCmds)): 
        ?>
        <div class="card card-dark bg-dark">
            <div class="card-header">
                <h3 class="card-title text-warning"><i class="fas fa-terminal mr-2"></i> Comandos de Consola Requeridos</h3>
            </div>
            <div class="card-body">
                <p class="small text-muted">Copia y pega estos comandos en tu terminal de servidor para corregir los problemas que PHP no puede resolver automáticamente:</p>
                <pre class="bg-black p-3 rounded" style="color: #00ff00; font-family: 'Courier New', Courier, monospace;"><code><?php echo implode("\n", $termCmds); ?></code></pre>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

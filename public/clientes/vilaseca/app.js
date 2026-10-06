// VILASECA Client Module - Dashboard (Manage) - app.js
// Standardized Sonda Brand Pantone & Executive Visualizations
const API_PM = '../../api_portmapping.php';
const API_BPMN = '../../api_bpmn.php';
const API_CI = '../../api_ci.php';

let vlSurveys = [];
let vizChart = null, vizTypeChart = null;
let overviewDoughnut = null, overviewBar = null;
let currentSurveyPorts = [];

// Paginadores para tablas del módulo
let invPaginator = null;
let bpmnPaginator = null;
let corrPaginator = null;
let racksPaginator = null;

// Helper: Matches Vilaseca or Vilseca or VILASECA
function isVilasecaClient(clientName) {
    if (!clientName) return false;
    let c = clientName.toLowerCase().trim();
    return c.includes('vilaseca') || c.includes('vilseca') || c.includes('vila');
}

// =========================================================================
// MOTOR GENÉRICO DE PAGINACIÓN, BÚSQUEDA Y ORDENACIÓN PANTONE SONDA
// =========================================================================
function initTablePaginator(options) {
    const table = $(options.tableSelector);
    if (!table.length) return null;

    const searchInput = $(options.searchSelector);
    const pageSizeSelect = $(options.pageSizeSelector);
    const infoContainer = $(options.infoSelector);
    const paginationContainer = $(options.paginationSelector);
    const extraFilter = options.extraFilterFn || null;
    const onFilterChange = options.onFilterChange || null;

    let currentPage = 1;
    let totalPages = 1;
    let currentSortCol = -1;
    let currentSortAsc = true;

    function getRows() {
        return table.find('tbody tr').filter(function() {
            const $r = $(this);
            if ($r.hasClass('no-records-row') || $r.hasClass('loading-row')) return false;
            if ($r.find('td[colspan]').length > 0 && ($r.text().includes('Cargando') || $r.text().includes('No hay') || $r.text().includes('No se encontraron'))) {
                return false;
            }
            return true;
        });
    }

    function sortRows(rows, colIdx, asc) {
        return rows.sort(function(a, b) {
            let valA = $(a).find('td').eq(colIdx).text().trim();
            let valB = $(b).find('td').eq(colIdx).text().trim();

            let numA = parseFloat(valA.replace(/[^0-9.-]/g, ''));
            let numB = parseFloat(valB.replace(/[^0-9.-]/g, ''));

            let isNumA = !isNaN(numA) && valA !== '';
            let isNumB = !isNaN(numB) && valB !== '';

            if (isNumA && isNumB) {
                return asc ? numA - numB : numB - numA;
            }

            return asc ? valA.localeCompare(valB, 'es', { sensitivity: 'base' }) : valB.localeCompare(valA, 'es', { sensitivity: 'base' });
        });
    }

    function update() {
        let pageSize = parseInt(pageSizeSelect.val());
        if (isNaN(pageSize)) pageSize = 10;

        const searchTerm = (searchInput.val() || '').toLowerCase().trim();
        const allRows = getRows();

        if (allRows.length === 0) {
            paginationContainer.empty();
            if (infoContainer.length) infoContainer.html('<span class="text-muted"><i class="fas fa-info-circle mr-1"></i>Sin registros disponibles</span>');
            return;
        }

        let filteredRows = [];

        allRows.each(function() {
            const $row = $(this);
            let textMatch = true;

            if (searchTerm !== '') {
                const text = $row.text().toLowerCase();
                const dLoc = ($row.data('loc') || '').toString().toLowerCase();
                const dName = ($row.data('name') || '').toString().toLowerCase();
                const dIp = ($row.data('ip') || '').toString().toLowerCase();
                const dRack = ($row.data('rack') || '').toString().toLowerCase();
                const dType = ($row.data('type') || '').toString().toLowerCase();
                const fullText = text + ' ' + dLoc + ' ' + dName + ' ' + dIp + ' ' + dRack + ' ' + dType;
                
                const tokens = searchTerm.split(/\s+/);
                textMatch = tokens.every(token => fullText.includes(token));
            }

            let extraMatch = true;
            if (extraFilter && typeof extraFilter === 'function') {
                extraMatch = extraFilter($row);
            }

            if (textMatch && extraMatch) {
                filteredRows.push($row);
            }
        });

        // Aplicar ordenación si hay columna activa
        if (currentSortCol >= 0 && filteredRows.length > 0) {
            filteredRows = sortRows(filteredRows, currentSortCol, currentSortAsc);
            const tbody = table.find('tbody');
            filteredRows.forEach(r => tbody.append(r));
        }

        const totalFiltered = filteredRows.length;
        const totalRows = allRows.length;

        totalPages = pageSize === -1 ? 1 : Math.max(1, Math.ceil(totalFiltered / (pageSize || 10)));
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        allRows.hide().css('display', 'none');
        table.find('.no-records-row').remove();

        if (totalFiltered === 0) {
            const colCount = table.find('thead th').length || 5;
            table.find('tbody').append(
                `<tr class="no-records-row"><td colspan="${colCount}" class="text-center py-4 text-muted"><i class="fas fa-search mr-2 text-secondary"></i>No se encontraron registros que coincidan con los criterios de búsqueda.</td></tr>`
            );
        } else {
            const startIndex = pageSize === -1 ? 0 : (currentPage - 1) * pageSize;
            const endIndex = pageSize === -1 ? totalFiltered : Math.min(startIndex + pageSize, totalFiltered);

            for (let i = startIndex; i < endIndex; i++) {
                filteredRows[i].show().css('display', 'table-row');
            }
        }

        // Renderizar texto de información
        if (infoContainer.length) {
            if (totalFiltered === 0) {
                infoContainer.html(`<span class="text-muted"><i class="fas fa-info-circle mr-1"></i>0 registros encontrados</span>`);
            } else {
                const startNum = pageSize === -1 ? 1 : (currentPage - 1) * pageSize + 1;
                const endNum = pageSize === -1 ? totalFiltered : Math.min(currentPage * pageSize, totalFiltered);
                let infoHtml = `Mostrando <strong>${startNum}</strong> a <strong>${endNum}</strong> de <strong>${totalFiltered}</strong> registros`;
                if (totalFiltered !== totalRows) {
                    infoHtml += ` <span class="text-muted" style="font-weight:normal;">(filtrados de ${totalRows} totales)</span>`;
                }
                infoContainer.html(infoHtml);
            }
        }

        // Renderizar botones de paginación
        renderPaginationButtons(totalPages, totalFiltered, pageSize);

        if (onFilterChange && typeof onFilterChange === 'function') {
            onFilterChange(totalFiltered, totalRows);
        }
    }

    function renderPaginationButtons(totalP, totalFiltered, pageSize) {
        if (!paginationContainer.length) return;

        if (pageSize === -1 || totalP <= 1) {
            paginationContainer.empty();
            return;
        }

        let pagHtml = '';

        // Botón Primero
        pagHtml += `<button type="button" class="sonda-page-btn ${currentPage === 1 ? 'disabled' : ''}" data-page="1" title="Primera página"><i class="fas fa-angle-double-left"></i></button>`;

        // Botón Anterior
        const prevP = Math.max(1, currentPage - 1);
        pagHtml += `<button type="button" class="sonda-page-btn ${currentPage === 1 ? 'disabled' : ''}" data-page="${prevP}" title="Página anterior"><i class="fas fa-chevron-left"></i></button>`;

        // Botones de números con ventana activa
        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalP, currentPage + 2);

        if (startPage > 1) {
            pagHtml += `<button type="button" class="sonda-page-btn" data-page="1">1</button>`;
            if (startPage > 2) {
                pagHtml += `<span class="px-1 text-muted" style="user-select:none; font-weight: bold; line-height: 28px;">...</span>`;
            }
        }

        for (let p = startPage; p <= endPage; p++) {
            pagHtml += `<button type="button" class="sonda-page-btn ${p === currentPage ? 'active' : ''}" data-page="${p}">${p}</button>`;
        }

        if (endPage < totalP) {
            if (endPage < totalP - 1) {
                pagHtml += `<span class="px-1 text-muted" style="user-select:none; font-weight: bold; line-height: 28px;">...</span>`;
            }
            pagHtml += `<button type="button" class="sonda-page-btn" data-page="${totalP}">${totalP}</button>`;
        }

        // Botón Siguiente
        const nextP = Math.min(totalP, currentPage + 1);
        pagHtml += `<button type="button" class="sonda-page-btn ${currentPage === totalP ? 'disabled' : ''}" data-page="${nextP}" title="Página siguiente"><i class="fas fa-chevron-right"></i></button>`;

        // Botón Último
        pagHtml += `<button type="button" class="sonda-page-btn ${currentPage === totalP ? 'disabled' : ''}" data-page="${totalP}" title="Última página"><i class="fas fa-angle-double-right"></i></button>`;

        paginationContainer.html(pagHtml);
    }

    // Delegación segura en document restringida a los contenedores de este paginador
    const eventNs = '.paginator_' + (options.tableSelector.replace(/[^a-zA-Z0-9]/g, '_'));
    $(document).off('click' + eventNs, '.sonda-page-btn').on('click' + eventNs, '.sonda-page-btn', function(e) {
        e.preventDefault();
        const $btn = $(this).closest('.sonda-page-btn');
        if (!$btn.length || $btn.hasClass('disabled') || $btn.hasClass('active')) return;
        
        // Comprobar que el botón pertenezca a los contenedores de este paginador
        if (!$btn.closest(options.paginationSelector).length) return;

        const targetPage = parseInt($btn.attr('data-page') || $btn.data('page'));
        if (!isNaN(targetPage) && targetPage >= 1 && targetPage <= totalPages) {
            currentPage = targetPage;
            update();

            // Scroll suave si el usuario interactuó con la paginación inferior
            if ($btn.closest('.card-footer').length > 0) {
                const tableTop = table.offset().top - 90;
                if ($(window).scrollTop() > tableTop + 250) {
                    $('html, body').animate({ scrollTop: tableTop }, 250);
                }
            }
        }
    });

    // Cabeceras ordenables
    table.find('thead th').each(function(idx) {
        const $th = $(this);
        const headerText = $th.text().toLowerCase();
        if ($th.hasClass('no-sort') || headerText.includes('acción') || headerText.includes('acciones')) {
            return;
        }
        $th.css({ 'cursor': 'pointer', 'user-select': 'none' });
        $th.attr('title', 'Clic para ordenar');
        if (!$th.find('.sort-icon').length) {
            $th.append(' <i class="fas fa-sort text-muted sort-icon" style="font-size:0.75rem; opacity:0.6;"></i>');
        }

        $th.on('click', function() {
            if (currentSortCol === idx) {
                currentSortAsc = !currentSortAsc;
            } else {
                currentSortCol = idx;
                currentSortAsc = true;
            }

            table.find('thead th .sort-icon').removeClass('fa-sort-up fa-sort-down text-warning').addClass('fa-sort text-muted').css('color', '');
            const icon = $th.find('.sort-icon');
            icon.removeClass('fa-sort text-muted').addClass(currentSortAsc ? 'fa-sort-up' : 'fa-sort-down').css('color', 'var(--sonda-orange)');

            update();
        });
    });

    // Eventos de búsqueda
    if (searchInput.length) {
        searchInput.on('input keyup change', function() {
            currentPage = 1;
            update();
        });
    }

    // Eventos de tamaño de página
    if (pageSizeSelect.length) {
        pageSizeSelect.on('change', function() {
            currentPage = 1;
            update();
        });
    }

    // Actualización inicial
    update();

    return {
        refresh: function(resetPage) {
            if (resetPage) currentPage = 1;
            update();
        },
        setPage: function(p) {
            currentPage = p;
            update();
        }
    };
}

// =========================================================================
// MOTOR DE FILTROS DINÁMICOS CONCATENADOS Y ACTUALIZACIÓN REACTIVA TOTAL
// =========================================================================
let g_currentFilteredDevices = [];
let g_filteredDeviceIds = null;
let g_isUpdatingFilters = false;

// Paleta Oficial Pantone SONDA
const SONDA_PALETTE = [
    '#ff5c05', // Sonda Orange
    '#00B8D4', // Sonda Cyan
    '#101B31', // Sonda Navy
    '#c0da20', // Sonda Lime Green
    '#2a4365', // Navy Slate
    '#e04e04', // Deep Orange
    '#00838f', // Deep Cyan
    '#64748b'  // Muted Slate
];

// Inicialización de Gráficos Chart.js (instancias persistentes para actualización dinámica)
function initOverviewCharts() {
    if (typeof VL_SERVER_DATA === 'undefined') return;
    if (typeof Chart === 'undefined') {
        console.warn('Chart.js no está disponible aún.');
        return;
    }

    // 1. Doughnut: Equipos por Tipo
    let doughnutCanvas = document.getElementById('vl-chart-types');
    if (doughnutCanvas) {
        if (overviewDoughnut) {
            overviewDoughnut.destroy();
            overviewDoughnut = null;
        }
        const doughnutCtx = doughnutCanvas.getContext ? doughnutCanvas.getContext('2d') : doughnutCanvas;
        overviewDoughnut = new Chart(doughnutCtx, {
            type: 'doughnut',
            data: {
                labels: ['Cargando...'],
                datasets: [{
                    data: [1],
                    backgroundColor: SONDA_PALETTE,
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            font: { size: 11, weight: '600', family: 'Kumbh Sans' },
                            padding: 12,
                            boxWidth: 14
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                let total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                let val = ctx.raw || 0;
                                let pct = total > 0 ? Math.round((val / total) * 100) : 0;
                                return ` ${ctx.label}: ${val} (${pct}%)`;
                            }
                        }
                    }
                }
            }
        });
    }

    // 2. Grouped Bar: Equipos y Puertos por Sede
    let barCanvas = document.getElementById('vl-chart-locations');
    if (barCanvas) {
        if (overviewBar) {
            overviewBar.destroy();
            overviewBar = null;
        }
        const barCtx = barCanvas.getContext ? barCanvas.getContext('2d') : barCanvas;
        overviewBar = new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: ['Cargando...'],
                datasets: [
                    {
                        label: 'Equipos Relevados',
                        data: [0],
                        backgroundColor: '#ff5c05',
                        borderRadius: 6,
                        barPercentage: 0.65,
                        categoryPercentage: 0.7
                    },
                    {
                        label: 'Puertos Físicos',
                        data: [0],
                        backgroundColor: '#00B8D4',
                        borderRadius: 6,
                        barPercentage: 0.65,
                        categoryPercentage: 0.7
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            font: { size: 11, weight: '600', family: 'Kumbh Sans' },
                            boxWidth: 14
                        }
                    },
                    tooltip: {
                        padding: 10,
                        titleFont: { size: 12, weight: 'bold' }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11, weight: '600' } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: { font: { size: 10 } }
                    }
                }
            }
        });
    }
}

// =========================================================================
// FUNCIÓN PRINCIPAL DE FILTRADO CONCATENADO Y ACTUALIZACIÓN TOTAL
// =========================================================================
function applyAllFilters() {
    if (g_isUpdatingFilters || typeof VL_SERVER_DATA === 'undefined') return;
    g_isUpdatingFilters = true;

    const allDevs = VL_SERVER_DATA.all_devices || [];

    // Capturar valores preliminares de los 5 filtros
    let selLoc = $('#vl-filter-location').val() || 'all';
    let selArea = $('#vl-filter-area').val() || 'all';
    let selRack = $('#vl-filter-rack').val() || 'all';
    let selType = $('#vl-filter-type').val() || 'all';
    let selSearch = ($('#vl-filter-search').val() || '').toLowerCase().trim();

    // 1. Concatenación inteligente de opciones en los selectores desplegables
    updateConcatenatedDropdowns(allDevs, selLoc, selArea, selRack, selType, selSearch);

    // Re-leer valores finales por si alguno se reseteó a 'all' al no ser válido para la combinación
    selLoc = $('#vl-filter-location').val() || 'all';
    selArea = $('#vl-filter-area').val() || 'all';
    selRack = $('#vl-filter-rack').val() || 'all';
    selType = $('#vl-filter-type').val() || 'all';

    // Actualizar indicador visual de filtros activos
    let activeCount = 0;
    if (selLoc !== 'all') activeCount++;
    if (selArea !== 'all') activeCount++;
    if (selRack !== 'all') activeCount++;
    if (selType !== 'all') activeCount++;
    if (selSearch !== '') activeCount++;

    const $badgeActive = $('#vl-active-filters-count');
    if (activeCount > 0) {
        $badgeActive.text(`${activeCount} filtro${activeCount > 1 ? 's' : ''} activo${activeCount > 1 ? 's' : ''}`).show();
    } else {
        $badgeActive.hide();
    }

    // 2. Obtener lista de equipos que cumplen TODOS los criterios
    const searchTokens = selSearch ? selSearch.split(/\s+/) : [];
    g_currentFilteredDevices = allDevs.filter(d => {
        if (selLoc !== 'all' && (d.location || '') !== selLoc) return false;
        if (selArea !== 'all' && (d.area || '') !== selArea) return false;
        if (selRack !== 'all' && (d.rack || '') !== selRack) return false;
        if (selType !== 'all' && (d.device_type || '') !== selType) return false;
        if (searchTokens.length > 0) {
            const fullStr = `${d.device_name || ''} ${d.location || ''} ${d.area || ''} ${d.rack || ''} ${d.device_type || ''} ${d.ip_address || ''}`.toLowerCase();
            if (!searchTokens.every(tok => fullStr.includes(tok))) return false;
        }
        return true;
    });

    g_filteredDeviceIds = new Set(g_currentFilteredDevices.map(d => Number(d.id)));

    // 3. Actualizar KPIs (5 tarjetas superiores)
    updateKPIs(g_currentFilteredDevices);

    // 4. Actualizar Gráficos Chart.js y sus barras de desglose
    updateCharts(g_currentFilteredDevices);

    // 5. Actualizar Leaderboards (Top Vacíos y Top Llenos)
    updateLeaderboards(g_currentFilteredDevices);

    // 6. Actualizar Tarjetas y Pastillas de Racks
    updateRacksSection(g_currentFilteredDevices, selLoc);

    // 7. Actualizar Tabla de Inventario y Paginación
    if (invPaginator) {
        invPaginator.refresh(true);
    }

    g_isUpdatingFilters = false;
}

// Concatenación inteligente de filtros (Faceted Filtering)
function updateConcatenatedDropdowns(allDevs, curLoc, curArea, curRack, curType, curSearch) {
    const searchTokens = curSearch ? curSearch.split(/\s+/) : [];
    const matchesSearch = d => {
        if (!searchTokens.length) return true;
        const fullStr = `${d.device_name || ''} ${d.location || ''} ${d.area || ''} ${d.rack || ''} ${d.device_type || ''} ${d.ip_address || ''}`.toLowerCase();
        return searchTokens.every(tok => fullStr.includes(tok));
    };

    // 1. Candidatos para Sede / Localidad (considera Area, Rack, Type, Search)
    const locCandidates = allDevs.filter(d => {
        if (curArea !== 'all' && (d.area || '') !== curArea) return false;
        if (curRack !== 'all' && (d.rack || '') !== curRack) return false;
        if (curType !== 'all' && (d.device_type || '') !== curType) return false;
        return matchesSearch(d);
    });
    const locCounts = {};
    locCandidates.forEach(d => {
        const l = d.location || 'General';
        locCounts[l] = (locCounts[l] || 0) + 1;
    });
    rebuildSelect('#vl-filter-location', locCounts, curLoc, 'Todas las Sedes');

    // 2. Candidatos para Área (considera Loc, Rack, Type, Search)
    const areaCandidates = allDevs.filter(d => {
        if (curLoc !== 'all' && (d.location || '') !== curLoc) return false;
        if (curRack !== 'all' && (d.rack || '') !== curRack) return false;
        if (curType !== 'all' && (d.device_type || '') !== curType) return false;
        return matchesSearch(d);
    });
    const areaCounts = {};
    areaCandidates.forEach(d => {
        const a = d.area || 'General';
        areaCounts[a] = (areaCounts[a] || 0) + 1;
    });
    rebuildSelect('#vl-filter-area', areaCounts, curArea, 'Todas las Áreas');

    // 3. Candidatos para Rack (considera Loc, Area, Type, Search)
    const rackCandidates = allDevs.filter(d => {
        if (curLoc !== 'all' && (d.location || '') !== curLoc) return false;
        if (curArea !== 'all' && (d.area || '') !== curArea) return false;
        if (curType !== 'all' && (d.device_type || '') !== curType) return false;
        return matchesSearch(d);
    });
    const rackCounts = {};
    rackCandidates.forEach(d => {
        const r = d.rack || 'Sin Rack';
        rackCounts[r] = (rackCounts[r] || 0) + 1;
    });
    rebuildSelect('#vl-filter-rack', rackCounts, curRack, 'Todos los Racks');

    // 4. Candidatos para Tipo (considera Loc, Area, Rack, Search)
    const typeCandidates = allDevs.filter(d => {
        if (curLoc !== 'all' && (d.location || '') !== curLoc) return false;
        if (curArea !== 'all' && (d.area || '') !== curArea) return false;
        if (curRack !== 'all' && (d.rack || '') !== curRack) return false;
        return matchesSearch(d);
    });
    const typeCounts = {};
    typeCandidates.forEach(d => {
        const t = d.device_type || 'Switch';
        typeCounts[t] = (typeCounts[t] || 0) + 1;
    });
    rebuildSelect('#vl-filter-type', typeCounts, curType, 'Todos los Tipos');
}

function rebuildSelect(selectId, countsMap, currentValue, defaultLabel) {
    const $sel = $(selectId);
    if (!$sel.length) return;

    const sortedKeys = Object.keys(countsMap).sort();
    const totalOptions = sortedKeys.length;

    let html = `<option value="all">${defaultLabel} (${totalOptions})</option>`;
    sortedKeys.forEach(k => {
        const isSelected = (k === currentValue) ? 'selected' : '';
        html += `<option value="${escH(k)}" ${isSelected}>${escH(k)} (${countsMap[k]})</option>`;
    });
    $sel.html(html);

    if (currentValue !== 'all' && !countsMap[currentValue]) {
        $sel.val('all');
    }
}

// Restablecer todos los filtros
function resetAllVilasecaFilters() {
    $('#vl-filter-location').val('all');
    $('#vl-filter-area').val('all');
    $('#vl-filter-rack').val('all');
    $('#vl-filter-type').val('all');
    $('#vl-filter-search').val('');
    $('#vl-inv-table-search').val('');
    applyAllFilters();
}

// 3. Actualizar 5 KPIs
function updateKPIs(devs) {
    const totalDevs = devs.length;
    let totalPorts = 0;
    let totalOccupied = 0;
    let totalVacant = 0;
    const locSet = new Set();
    const rackSet = new Set();

    devs.forEach(d => {
        const t = parseInt(d.total || d.ports_count) || 0;
        const occ = parseInt(d.occupied) || 0;
        const vac = parseInt(d.vacant) || 0;
        totalPorts += t;
        totalOccupied += occ;
        totalVacant += vac;
        if (d.location) locSet.add(d.location);
        if (d.rack) rackSet.add(d.rack);
    });

    const occRate = totalPorts > 0 ? ((totalOccupied / totalPorts) * 100).toFixed(1) : 0;

    $('#kpi-devices-count').text(totalDevs);
    $('#kpi-devices-sub').text(totalDevs > 0 ? `${locSet.size} sede${locSet.size !== 1 ? 's' : ''} / ${rackSet.size} rack${rackSet.size !== 1 ? 's' : ''}` : 'Sin coincidencias');

    $('#kpi-ports-total').text(totalPorts.toLocaleString());
    $('#kpi-ports-sub').text(totalDevs > 0 ? `Capacidad relevada (${totalDevs} equipos)` : 'Sin equipos');

    $('#kpi-occupancy-rate').text(`${occRate}%`);
    $('#kpi-occupancy-sub').text(`${totalOccupied.toLocaleString()} usados / ${totalVacant.toLocaleString()} libres`);

    $('#kpi-locations-count').text(locSet.size);
    $('#kpi-locations-sub').text(locSet.size === 1 ? Array.from(locSet)[0] : 'Infraestructura distribuida');

    $('#kpi-racks-count').text(rackSet.size);
    $('#kpi-racks-sub').text(rackSet.size === 1 ? `Rack: ${Array.from(rackSet)[0]}` : 'Gabinetes / Bastidores');
}

// 4. Actualizar Gráficos Chart.js
function updateCharts(devs) {
    if (typeof Chart === 'undefined') return;

    // 1. Doughnut: Equipos por Tipo
    const typesMap = {};
    devs.forEach(d => {
        const t = d.device_type || 'Switch';
        typesMap[t] = (typesMap[t] || 0) + 1;
    });

    const typeLabels = Object.keys(typesMap);
    const typeValues = Object.values(typesMap);
    const totDevs = devs.length;

    $('#vl-chart-types-badge').text(`${totDevs} Equipo${totDevs !== 1 ? 's' : ''}`);

    if (overviewDoughnut) {
        overviewDoughnut.data.labels = typeLabels.length ? typeLabels : ['Sin datos'];
        overviewDoughnut.data.datasets[0].data = typeValues.length ? typeValues : [0];
        overviewDoughnut.data.datasets[0].backgroundColor = typeLabels.length ? SONDA_PALETTE.slice(0, typeLabels.length) : ['#e2e8f0'];
        overviewDoughnut.update();
    }

    // Leyenda HTML de tipos
    let typesLegendHtml = '';
    if (typeLabels.length === 0) {
        typesLegendHtml = '<div class="text-center py-4 text-muted small"><i class="fas fa-filter mr-1"></i>Sin equipos para el filtro seleccionado</div>';
    } else {
        typeLabels.forEach((tName, idx) => {
            const count = typesMap[tName];
            const col = SONDA_PALETTE[idx % SONDA_PALETTE.length];
            const pct = totDevs > 0 ? Math.round((count / totDevs) * 100) : 0;
            typesLegendHtml += `
                <div class="mb-2">
                    <div class="d-flex justify-content-between align-items-center small font-weight-bold mb-1">
                        <span class="text-truncate mr-1"><span style="display:inline-block;width:9px;height:9px;border-radius:2px;background:${col};margin-right:6px;"></span>${escH(tName)}</span>
                        <span class="text-dark whitespace-nowrap"><strong>${count}</strong> <small class="text-muted">(${pct}%)</small></span>
                    </div>
                    <div class="progress" style="height: 6px; border-radius: 3px; background: #e2e8f0;">
                        <div class="progress-bar" style="width: ${pct}%; background: ${col};"></div>
                    </div>
                </div>`;
        });
    }
    $('#vl-types-legend-list').html(typesLegendHtml);

    // 2. Bar: Equipos y Puertos por Sede
    const locMap = {};
    devs.forEach(d => {
        const l = d.location || 'General';
        if (!locMap[l]) locMap[l] = { devices: 0, ports: 0, occupied: 0, vacant: 0 };
        locMap[l].devices++;
        locMap[l].ports += (parseInt(d.total || d.ports_count) || 0);
        locMap[l].occupied += (parseInt(d.occupied) || 0);
        locMap[l].vacant += (parseInt(d.vacant) || 0);
    });

    const locLabels = Object.keys(locMap);
    const locDevices = locLabels.map(l => locMap[l].devices);
    const locPorts = locLabels.map(l => locMap[l].ports);

    $('#vl-chart-locations-badge').text(`${locLabels.length} Sede${locLabels.length !== 1 ? 's' : ''}`);

    if (overviewBar) {
        overviewBar.data.labels = locLabels.length ? locLabels : ['Sin datos'];
        overviewBar.data.datasets[0].data = locDevices.length ? locDevices : [0];
        overviewBar.data.datasets[1].data = locPorts.length ? locPorts : [0];
        overviewBar.update();
    }

    // Leyenda HTML de sedes
    let locsLegendHtml = '';
    if (locLabels.length === 0) {
        locsLegendHtml = '<div class="text-center py-4 text-muted small"><i class="fas fa-filter mr-1"></i>Sin sedes para el filtro seleccionado</div>';
    } else {
        locLabels.forEach(lName => {
            const lData = locMap[lName];
            const occRate = lData.ports > 0 ? Math.round((lData.occupied / lData.ports) * 100) : 0;
            locsLegendHtml += `
                <div class="p-2 mb-2 rounded bg-light border">
                    <div class="d-flex justify-content-between align-items-center small font-weight-bold mb-1">
                        <span class="text-dark text-truncate mr-1"><i class="fas fa-map-marker-alt text-danger mr-1"></i>${escH(lName)}</span>
                        <span class="badge badge-pill text-white px-2" style="background:var(--sonda-orange);">${lData.devices} eq</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center" style="font-size: 0.75rem;">
                        <span class="text-muted"><i class="fas fa-ethernet mr-1" style="color:var(--sonda-cyan);"></i>${lData.ports} puertos</span>
                        <span class="font-weight-bold" style="color: #65a30d;">${lData.vacant} libres (${100 - occRate}%)</span>
                    </div>
                </div>`;
        });
    }
    $('#vl-locs-legend-list').html(locsLegendHtml);
}

// 5. Actualizar Leaderboards
function updateLeaderboards(devs) {
    // Top Vacíos
    const topVacant = [...devs].sort((a, b) => (parseInt(b.vacant) || 0) - (parseInt(a.vacant) || 0)).slice(0, 5);
    let htmlVacant = '';
    if (topVacant.length === 0) {
        htmlVacant = '<tr><td colspan="6" class="text-center py-4 text-muted"><i class="fas fa-filter mr-1"></i>Sin equipos que coincidan con los filtros.</td></tr>';
    } else {
        topVacant.forEach((tv, idx) => {
            const rankCls = idx === 0 ? 'rank-gold' : (idx === 1 ? 'rank-silver' : (idx === 2 ? 'rank-bronze' : 'rank-normal'));
            const tot = parseInt(tv.total || tv.ports_count) || 0;
            const vac = parseInt(tv.vacant) || 0;
            const vacPct = tot > 0 ? Math.round((vac / tot) * 100) : 0;
            htmlVacant += `
                <tr>
                    <td class="text-center"><span class="rank-badge-pill ${rankCls}">${idx + 1}</span></td>
                    <td class="font-weight-bold text-dark">${escH(tv.device_name)}</td>
                    <td><small class="text-muted"><i class="fas fa-map-marker-alt text-danger mr-1"></i>${escH(tv.location)}</small></td>
                    <td class="text-center"><span class="badge badge-light border">${tot}</span></td>
                    <td class="text-center font-weight-bold" style="color: #65a30d;">${vac}</td>
                    <td>
                        <div class="progress" style="height: 7px; border-radius: 4px;">
                            <div class="progress-bar" style="width: ${vacPct}%; background: var(--sonda-green);"></div>
                        </div>
                        <small class="text-muted font-weight-bold" style="font-size: 0.68rem;">${vacPct}% libre</small>
                    </td>
                </tr>`;
        });
    }
    $('#vl-top-vacant-tbody').html(htmlVacant);

    // Top Llenos
    const topOccupied = [...devs].sort((a, b) => {
        const rateB = parseFloat(b.rate) || 0;
        const rateA = parseFloat(a.rate) || 0;
        if (rateB !== rateA) return rateB - rateA;
        return (parseInt(b.occupied) || 0) - (parseInt(a.occupied) || 0);
    }).slice(0, 5);

    let htmlOccupied = '';
    if (topOccupied.length === 0) {
        htmlOccupied = '<tr><td colspan="6" class="text-center py-4 text-muted"><i class="fas fa-filter mr-1"></i>Sin equipos que coincidan con los filtros.</td></tr>';
    } else {
        topOccupied.forEach((to, idx) => {
            const rankCls = idx === 0 ? 'rank-gold' : (idx === 1 ? 'rank-silver' : (idx === 2 ? 'rank-bronze' : 'rank-normal'));
            const tot = parseInt(to.total || to.ports_count) || 0;
            const occ = parseInt(to.occupied) || 0;
            const occPct = parseFloat(to.rate) || (tot > 0 ? ((occ / tot) * 100).toFixed(1) : 0);
            htmlOccupied += `
                <tr>
                    <td class="text-center"><span class="rank-badge-pill ${rankCls}">${idx + 1}</span></td>
                    <td class="font-weight-bold text-dark">${escH(to.device_name)}</td>
                    <td><small class="text-muted"><i class="fas fa-map-marker-alt text-danger mr-1"></i>${escH(to.location)}</small></td>
                    <td class="text-center"><span class="badge badge-light border">${tot}</span></td>
                    <td class="text-center font-weight-bold text-danger">${occ}</td>
                    <td>
                        <div class="progress" style="height: 7px; border-radius: 4px;">
                            <div class="progress-bar" style="width: ${occPct}%; background: var(--sonda-orange);"></div>
                        </div>
                        <small class="text-muted font-weight-bold" style="font-size: 0.68rem;">${occPct}% ocupado</small>
                    </td>
                </tr>`;
        });
    }
    $('#vl-top-occupied-tbody').html(htmlOccupied);
}

// 6. Actualizar Sección de Racks y Pastillas
function updateRacksSection(devs, activeLoc) {
    const racksMap = {};
    const locSet = new Set();

    devs.forEach(d => {
        const loc = d.location || 'General';
        const rk = d.rack || 'Sin Rack';
        const key = loc + '___' + rk;
        locSet.add(loc);

        if (!racksMap[key]) {
            racksMap[key] = {
                location: loc,
                rack_name: rk,
                devices_count: 0,
                ports_count: 0
            };
        }
        racksMap[key].devices_count++;
        racksMap[key].ports_count += (parseInt(d.total || d.ports_count) || 0);
    });

    const racksList = Object.values(racksMap).sort((a, b) => b.devices_count - a.devices_count);

    // Pastillas de Sede para Racks (conserva todas las sedes disponibles para navegación rápida)
    const allLocations = (typeof VL_SERVER_DATA !== 'undefined' && VL_SERVER_DATA.locations && VL_SERVER_DATA.locations.length > 0)
        ? VL_SERVER_DATA.locations 
        : Array.from(locSet).sort();
    let pillsHtml = `<button type="button" class="pill-loc-btn ${activeLoc === 'all' ? 'active' : ''}" onclick="onRackPillClick('all', this)">Todas las Sedes</button>`;
    allLocations.forEach(locName => {
        const isAct = (activeLoc === locName) ? 'active' : '';
        pillsHtml += `<button type="button" class="pill-loc-btn ${isAct}" onclick="onRackPillClick('${escH(locName)}', this)">${escH(locName)}</button>`;
    });
    $('#vl-racks-pills-bar').html(pillsHtml);

    // Tarjetas de Racks
    let cardsHtml = '';
    if (racksList.length === 0) {
        cardsHtml = '<div class="col-12 text-center py-5 text-muted"><i class="fas fa-filter fa-2x mb-2 text-secondary"></i><p class="font-weight-bold mb-0">No hay racks con hardware que coincidan con los filtros seleccionados.</p></div>';
    } else {
        racksList.forEach(rk => {
            cardsHtml += `
                <div class="col-md-4 col-lg-3 rack-item-col" data-loc="${escH(rk.location)}">
                    <div class="card border shadow-xs h-100 p-3" style="border-radius: 10px; background: #ffffff;">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge badge-light border text-dark font-weight-bold"><i class="fas fa-server mr-1" style="color: var(--sonda-orange);"></i>${escH(rk.rack_name)}</span>
                            <span class="badge badge-pill badge-info" style="background: var(--sonda-cyan); color: #101B31;">${rk.devices_count} equipos</span>
                        </div>
                        <div class="small text-muted mb-2">
                            <i class="fas fa-map-marker-alt text-danger mr-1"></i>${escH(rk.location)}
                        </div>
                        <div class="mt-auto pt-2 border-top d-flex justify-content-between align-items-center">
                            <span class="small font-weight-bold text-dark">${rk.ports_count} puertos</span>
                            <a href="../../datacenter/racks.php?cliente=VILASECA" class="btn btn-xs btn-outline-secondary font-weight-bold">
                                <i class="fas fa-eye mr-1"></i>Ver Rack
                            </a>
                        </div>
                    </div>
                </div>`;
        });
    }
    $('#vl-racks-grid').html(cardsHtml);
}

function onRackPillClick(locName, btn) {
    $('#vl-racks-pills-bar .pill-loc-btn').removeClass('active');
    $(btn).addClass('active');

    $('#vl-filter-location').val(locName);
    applyAllFilters();
}

// 7. Exportar CSV Filtrado
function exportVilasecaCSV() {
    const listToExport = (g_currentFilteredDevices && g_currentFilteredDevices.length > 0) 
        ? g_currentFilteredDevices 
        : (VL_SERVER_DATA?.all_devices || []);

    if (!listToExport || listToExport.length === 0) {
        if (typeof Swal !== 'undefined') {
            Swal.fire('Atención', 'No hay datos disponibles para exportar con el filtro actual.', 'info');
        } else {
            alert('No hay datos disponibles para exportar.');
        }
        return;
    }

    let rows = [
        ['ID', 'Nombre Equipo', 'Sede / Ubicacion', 'Area', 'Rack', 'Tipo', 'IP', 'Puertos Totales', 'Puertos Ocupados', 'Puertos Vacios', 'Tasa Ocupacion %']
    ];

    listToExport.forEach(d => {
        rows.push([
            d.id,
            `"${(d.device_name || '').replace(/"/g, '""')}"`,
            `"${(d.location || '').replace(/"/g, '""')}"`,
            `"${(d.area || '').replace(/"/g, '""')}"`,
            `"${(d.rack || '').replace(/"/g, '""')}"`,
            `"${(d.device_type || '').replace(/"/g, '""')}"`,
            d.ip_address || '',
            d.ports_count || d.total || 0,
            d.occupied || 0,
            d.vacant || 0,
            `${d.rate || 0}%`
        ]);
    });

    let csvContent = 'data:text/csv;charset=utf-8,\uFEFF' + rows.map(e => e.join(';')).join('\n');
    let encodedUri = encodeURI(csvContent);
    let link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `VILASECA_Inventario_Filtrado_${new Date().toISOString().substring(0, 10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Saltar desde Inventario a Visualizador Frontal
function selectSurveyForViz(surveyId) {
    $('#vlTabs a[href="#tab-visualizacion"]').tab('show');
    $('#vl-viz-device-select').val(surveyId);
    loadVizPorts(surveyId);
}

// ========== TAB 2: VISUALIZADOR FRONTAL DE PUERTOS ==========
function loadSurveys() {
    $.get(API_PM + '?action=get_manual_surveys', function(res) {
        if (!res.success || !res.data) return;
        
        vlSurveys = res.data.filter(s => isVilasecaClient(s.client));
        if (vlSurveys.length === 0) {
            vlSurveys = res.data;
        }

        populateVizDropdown();
    }, 'json');
}

function populateVizDropdown() {
    let sel = $('#vl-viz-device-select');
    sel.empty().append('<option value="">-- Seleccione un equipo VILASECA --</option>');
    
    vlSurveys.forEach(s => {
        let pText = s.ports_count ? `${s.ports_count} puertos` : 'Puertos N/A';
        let locText = s.location ? ` - ${s.location}` : '';
        let rackText = s.rack ? ` [Rack: ${s.rack}]` : '';
        sel.append(`<option value="${s.id}">${escH(s.device_name)}${locText}${rackText} (${pText})</option>`);
    });

    if (vlSurveys.length > 0) {
        sel.val(vlSurveys[0].id);
        loadVizPorts(vlSurveys[0].id);
    }
}

function loadVizPorts(surveyId) {
    if (!surveyId) return;
    $('#vl-viz-port-area').html('<div class="text-center py-5 text-muted"><i class="fas fa-spinner fa-spin fa-2x mb-2" style="color:var(--sonda-orange);"></i><p>Cargando chasis del equipo...</p></div>');

    $.get(API_PM + '?action=get_manual_survey&id=' + surveyId, function(res) {
        if (!res.success || !res.data) {
            $('#vl-viz-port-area').html('<div class="alert alert-warning">No se encontró información del relevamiento.</div>');
            return;
        }
        let survey = res.data;
        let ports = [];
        try { ports = JSON.parse(survey.ports_data_json || '[]'); } catch(e) { ports = []; }
        currentSurveyPorts = ports;
        renderPortDiagram(survey, ports);
    }, 'json');
}

function renderPortDiagram(survey, ports) {
    let area = $('#vl-viz-port-area');
    area.empty();

    let totalDeclaredCount = parseInt(survey.ports_count) || 0;
    
    // Header Info Card
    area.append(`<div class="card p-3 mb-3 border-0 bg-light shadow-sm" style="border-radius:10px;">
        <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
            <div>
                <h5 class="font-weight-bold mb-1 text-dark"><i class="fas fa-server mr-2" style="color:var(--sonda-orange);"></i>${escH(survey.device_name)}</h5>
                <div class="small text-muted">
                    <span class="mr-3"><i class="fas fa-map-marker-alt text-danger mr-1"></i>${escH(survey.location || 'Sede Central')}</span>
                    <span class="mr-3"><i class="fas fa-cube text-info mr-1"></i>${escH(survey.device_type || 'Switch')}</span>
                    <span class="mr-3"><i class="fas fa-layer-group text-warning mr-1"></i>Rack: <strong>${escH(survey.rack || 'RACK-01')}</strong></span>
                    <span><i class="fas fa-network-wired text-secondary mr-1"></i>IP: <code>${escH(survey.ip_address || 'Sin IP')}</code></span>
                </div>
            </div>
            <div>
                <span class="badge px-3 py-2 font-weight-bold text-white shadow-sm" style="font-size:0.85rem; background: var(--sonda-navy);">
                    <i class="fas fa-plug mr-1" style="color:var(--sonda-cyan);"></i>${ports.length} puertos relevados ${totalDeclaredCount ? '/ ' + totalDeclaredCount + ' totales' : ''}
                </span>
            </div>
        </div>
    </div>`);

    let surveyedMap = {};
    ports.forEach((p, idx) => {
        let key = (p.port_name || '').toLowerCase().trim();
        if (key) surveyedMap[key] = p;
        surveyedMap['idx_' + idx] = p;
    });

    let fullPortsList = [];
    if (totalDeclaredCount > 0) {
        for (let i = 1; i <= totalDeclaredCount; i++) {
            let pName = `GigabitEthernet0/${i}`;
            let key = pName.toLowerCase();
            let matched = surveyedMap[key] || surveyedMap[`port ${i}`] || surveyedMap[`puerto ${i}`] || ports[i - 1] || null;
            if (matched) {
                fullPortsList.push(matched);
            } else {
                fullPortsList.push({ port_name: pName, status: 'free', _synthetic: true, _num: i });
            }
        }
    } else if (ports.length > 0) {
        fullPortsList = ports;
    } else {
        for (let i = 1; i <= 24; i++) {
            fullPortsList.push({ port_name: `Port ${i}`, status: 'free', _synthetic: true, _num: i });
        }
    }

    let groups = {};
    fullPortsList.forEach((p, idx) => {
        p._idx = idx;
        let name = p.port_name || `Port ${idx + 1}`;
        let groupName = 'GigabitEthernet (RJ-45)';
        if (/^fa/i.test(name)) groupName = 'FastEthernet';
        else if (/^te/i.test(name)) groupName = 'TenGigabit SFP+';
        else if (/^pwr|^psu|^power|^toma/i.test(name)) groupName = 'Power / Energía';
        
        if (!groups[groupName]) groups[groupName] = [];
        groups[groupName].push(p);
    });

    // Dark brushed chassis simulation
    let switchPanel = $('<div class="switch-chassis mb-3"></div>');
    switchPanel.append(`<div class="d-flex justify-content-between align-items-center mb-3 px-1 text-light border-bottom pb-2" style="border-color:#223554 !important;">
        <span class="font-weight-bold small text-uppercase" style="letter-spacing:1px; color:var(--sonda-cyan);">
            <i class="fas fa-microchip mr-2"></i>Panel Frontal - ${escH(survey.device_name)}
        </span>
        <span class="badge text-white" style="background:#1A2744; border:1px solid #33496e;">VILASECA HARDWARE MONITOR</span>
    </div>`);

    Object.keys(groups).forEach(gName => {
        let gPorts = groups[gName];
        let moduleDiv = $(`<div class="p-3 mb-2 rounded" style="background:#1A2744; border:1px solid #223554;"></div>`);
        moduleDiv.append(`<div class="mb-2 font-weight-bold" style="font-size:0.72rem; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;"><i class="fas fa-ethernet mr-1" style="color:var(--sonda-cyan);"></i> ${escH(gName)} (${gPorts.length} Puertos)</div>`);

        let topRow = [], bottomRow = [];
        gPorts.forEach((p, index) => {
            if (index % 2 === 0) topRow.push(p);
            else bottomRow.push(p);
        });

        let grid1 = $('<div class="port-grid mb-1"></div>');
        topRow.forEach(p => grid1.append(makePortCell(p)));
        moduleDiv.append(grid1);

        if (bottomRow.length > 0) {
            let grid2 = $('<div class="port-grid"></div>');
            bottomRow.forEach(p => grid2.append(makePortCell(p)));
            moduleDiv.append(grid2);
        }

        switchPanel.append(moduleDiv);
    });

    area.append(switchPanel);

    // Color Legend
    area.append(`<div class="d-flex align-items-center flex-wrap px-3 py-2 bg-light rounded border" style="gap:18px; font-size:.78rem;">
        <span class="font-weight-bold text-secondary">Leyenda de Estados:</span>
        <span class="d-flex align-items-center"><span class="port-cell connected mr-2" style="width:16px;height:14px;font-size:0;"></span> Conectado / Mapeado</span>
        <span class="d-flex align-items-center"><span class="port-cell free mr-2" style="width:16px;height:14px;font-size:0;"></span> Vacío / Disponible</span>
        <span class="d-flex align-items-center"><span class="port-cell damaged mr-2" style="width:16px;height:14px;font-size:0;"></span> Alerta / Dañado</span>
    </div>`);

    renderPortStats(fullPortsList);
}

function makePortCell(p) {
    let name = p.port_name || `P${(p._idx || 0) + 1}`;
    let shortLabel = name.replace(/^.*[^\d](\d+)$/, '$1');
    if (shortLabel.length > 3) shortLabel = name.substring(0, 3);
    
    let hasDest = !!(p.dest_device || p.dest_dev || p.destination_device || p.dest_device_name);
    let status = (p.status || p.port_status || '').toLowerCase();
    let isDamaged = status === 'down' || status === 'damaged' || status === 'dañado' || status === 'falla';
    
    let cls = 'free';
    if (isDamaged) cls = 'damaged';
    else if (hasDest || p.dest_device_name) cls = 'connected';
    
    let cellTitle = `${name} - ${cls === 'connected' ? 'Conectado a ' + (p.dest_device_name || p.dest_device || 'Equipo Destino') : cls === 'damaged' ? 'Falla reportada' : 'Disponible'}`;
    let cell = $(`<div class="port-cell ${cls}" title="${escH(cellTitle)}" data-idx="${p._idx}">${escH(shortLabel)}</div>`);
    
    cell.on('click', function() {
        showPortDetail(p);
    });
    return cell;
}

function showPortDetail(p) {
    let panel = $('#vl-viz-port-detail');
    let pName = p.port_name || `Puerto ${p._num || 'N/A'}`;
    let destDev = p.dest_device_name || p.dest_device || p.dest_dev || p.destination_device || 'Sin conexión activa';
    let destPort = p.dest_port_name || p.dest_port || p.destination_port || 'N/A';
    let cable = p.cable_type || p.cable || 'UTP Cat6A';
    let color = p.color_code || p.color || p.cable_color || '#ff5c05';
    let notes = p.notes || p.description || p.alias || p.physical_label || 'Sin observaciones registradas.';
    let status = (p.status || 'Activo');

    panel.html(`<div class="port-detail-panel shadow-sm">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="font-weight-bold mb-0 text-dark">
                <i class="fas fa-ethernet text-success mr-2"></i>Detalle de Puerto: <span style="color:var(--sonda-orange);">${escH(pName)}</span>
            </h6>
            <span class="badge ${status.toLowerCase() === 'down' ? 'badge-danger' : 'badge-success'} px-3 py-1 font-weight-bold">${escH(status)}</span>
        </div>
        <div class="row">
            <div class="col-md-3 mb-2"><small class="text-muted d-block font-weight-bold">EQUIPO ORIGEN</small><strong>${escH(pName)}</strong></div>
            <div class="col-md-3 mb-2"><small class="text-muted d-block font-weight-bold">EQUIPO DESTINO</small><strong style="color:var(--sonda-orange);">${escH(destDev)}</strong></div>
            <div class="col-md-3 mb-2"><small class="text-muted d-block font-weight-bold">PUERTO DESTINO</small><strong>${escH(destPort)}</strong></div>
            <div class="col-md-3 mb-2"><small class="text-muted d-block font-weight-bold">TIPO DE CABLE</small>
                <span>${escH(cable)}</span>
                <span style="display:inline-block;width:14px;height:14px;border-radius:50%;background:${color};border:1px solid rgba(0,0,0,.2);vertical-align:middle;margin-left:4px;"></span>
            </div>
        </div>
        ${p.dest_physical_label ? `<div class="mt-1"><small class="text-muted font-weight-bold">Etiqueta Física Destino:</small> <code class="bg-white px-2 py-1 rounded border">${escH(p.dest_physical_label)}</code></div>` : ''}
        <div class="mt-2 pt-2 border-top"><small class="text-muted font-weight-bold">Notas / Observaciones:</small> <span class="small text-secondary">${escH(notes)}</span></div>
    </div>`).show();

    $('html, body').animate({ scrollTop: panel.offset().top - 120 }, 300);
}

function renderPortStats(portsList) {
    let connected = 0, free = 0, damaged = 0;
    let typeCount = {};
    
    portsList.forEach(p => {
        let hasDest = !!(p.dest_device || p.dest_dev || p.destination_device || p.dest_device_name);
        let status = (p.status || p.port_status || '').toLowerCase();
        let isDamaged = status === 'down' || status === 'damaged' || status === 'dañado' || status === 'falla';
        
        if (isDamaged) damaged++;
        else if (hasDest) connected++;
        else free++;
        
        let name = (p.port_name || '');
        let type = 'RJ45 GigE';
        if (/^fa/i.test(name)) type = 'FastEthernet';
        else if (/^te/i.test(name)) type = 'TenGig SFP+';
        else if (/^pwr|^psu|^power/i.test(name)) type = 'Power';
        typeCount[type] = (typeCount[type] || 0) + 1;
    });

    let cards = $('#vl-viz-stat-cards');
    cards.html(`
        <div class="col-4"><div class="stat-kpi-card kpi-green text-center p-2"><div class="stat-kpi-num text-success" style="font-size:1.5rem;">${connected}</div><div class="stat-kpi-label" style="font-size:0.65rem;">Conectados</div></div></div>
        <div class="col-4"><div class="stat-kpi-card kpi-cyan text-center p-2"><div class="stat-kpi-num text-secondary" style="font-size:1.5rem;">${free}</div><div class="stat-kpi-label" style="font-size:0.65rem;">Disponibles</div></div></div>
        <div class="col-4"><div class="stat-kpi-card text-center p-2" style="border-left:4px solid #ef4444 !important;"><div class="stat-kpi-num text-danger" style="font-size:1.5rem;">${damaged}</div><div class="stat-kpi-label" style="font-size:0.65rem;">Falla / Down</div></div></div>
    `);

    // Doughnut Chart - Utilization
    if (vizChart) vizChart.destroy();
    let ctx = document.getElementById('vl-viz-chart');
    if (ctx) {
        vizChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Conectados', 'Disponibles', 'Falla / Down'],
                datasets: [{
                    data: [connected, free, damaged],
                    backgroundColor: ['#c0da20', '#334155', '#ef4444'], // Sonda green, dark slate, red
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: {
                    legend: { position: 'bottom', labels: { font: { size: 10, family: 'Kumbh Sans' }, boxWidth: 12 } },
                    title: { display: true, text: 'Utilización de Puertos', font: { size: 12, weight: 'bold', family: 'Kumbh Sans' } }
                }
            }
        });
    }

    // Bar Chart - Port Categories
    if (vizTypeChart) vizTypeChart.destroy();
    let ctx2 = document.getElementById('vl-viz-type-chart');
    if (ctx2) {
        let labels = Object.keys(typeCount);
        let values = Object.values(typeCount);
        let colors = ['#ff5c05', '#00B8D4', '#101B31', '#c0da20', '#2a4365'];
        vizTypeChart = new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Puertos',
                    data: values,
                    backgroundColor: colors.slice(0, labels.length),
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    title: { display: true, text: 'Tipos de Interfaces', font: { size: 12, weight: 'bold', family: 'Kumbh Sans' } }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 9 } } },
                    x: { ticks: { font: { size: 9 } } }
                }
            }
        });
    }
}

// ========== TAB 3: DIAGRAMAS BPMN ==========
function loadBPMN() {
    $.get(API_BPMN + '?action=list', function(res) {
        let tbody = $('#vl-bpmn-tbody');
        tbody.empty();
        if (!res.success || !res.diagrams || res.diagrams.length === 0) {
            tbody.html('<tr><td colspan="5" class="text-center py-4 text-muted"><i class="fas fa-info-circle mr-1"></i>No hay diagramas BPMN registrados para VILASECA.</td></tr>');
            if (bpmnPaginator) bpmnPaginator.refresh(true);
            return;
        }
        res.diagrams.forEach(d => {
            let date = d.updated_at ? new Date(d.updated_at.replace(/-/g,'/')).toLocaleString('es-ES',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}) : '-';
            tbody.append(`<tr>
                <td><span class="badge badge-dark">#${d.id}</span></td>
                <td class="font-weight-bold text-dark"><i class="fas fa-sitemap mr-2" style="color:var(--sonda-orange);"></i>${escH(d.title)}</td>
                <td class="text-muted small">${escH(d.description || 'Procedimiento operacional')}</td>
                <td><small class="text-muted"><i class="far fa-clock mr-1"></i>${date}</small></td>
                <td class="text-right">
                    <a href="../../bpmn.php?id=${d.id}" class="btn btn-xs text-white shadow-sm font-weight-bold" style="background:var(--sonda-orange);">
                        <i class="fas fa-edit mr-1"></i>Abrir Diagrama
                    </a>
                </td>
            </tr>`);
        });

        if (!bpmnPaginator) {
            bpmnPaginator = initTablePaginator({
                tableSelector: '#vl-bpmn-table',
                searchSelector: '#vl-bpmn-table-search',
                pageSizeSelector: '#vl-bpmn-page-size',
                infoSelector: '#vl-bpmn-table-info',
                paginationSelector: '#vl-bpmn-pagination'
            });
        } else {
            bpmnPaginator.refresh(true);
        }
    }, 'json').fail(function() {
        $('#vl-bpmn-tbody').html('<tr><td colspan="5" class="text-center py-4 text-danger"><i class="fas fa-exclamation-triangle mr-1"></i>Error conectando con la API BPMN.</td></tr>');
        if (bpmnPaginator) bpmnPaginator.refresh(true);
    });
}

// ========== TAB 4: CORRELACIONES CMDB ==========
function loadCorrelaciones() {
    $.get(API_CI + '?action=get_instances', function(res) {
        let tbody = $('#vl-corr-tbody');
        tbody.empty();
        if (!res.success || !res.data) {
            tbody.html('<tr><td colspan="5" class="text-center py-4 text-muted">Error cargando inventario CI.</td></tr>');
            if (corrPaginator) corrPaginator.refresh(true);
            return;
        }
        
        let devices = res.data;
        if (devices.length === 0) {
            tbody.html('<tr><td colspan="5" class="text-center py-4 text-muted">No hay equipos registrados en el inventario CMDB.</td></tr>');
            if (corrPaginator) corrPaginator.refresh(true);
            return;
        }
        
        devices.forEach(d => {
            tbody.append(`<tr>
                <td class="font-weight-bold text-dark"><i class="fas fa-network-wired mr-2" style="color:var(--sonda-cyan);"></i>${escH(d.hostname)}</td>
                <td><code>${escH(d.ip_address || 'N/A')}</code></td>
                <td><span class="badge badge-secondary">${escH(d.category_name || 'Hardware')}</span></td>
                <td>
                    <button class="btn btn-xs btn-outline-info font-weight-bold" onclick="loadDevicePorts(${d.id},'${escH(d.hostname)}')">
                        <i class="fas fa-ethernet mr-1"></i>Ver Interfaces y Mapeo
                    </button>
                </td>
                <td class="text-right">
                    <a href="../../interfaces_manager.php" class="btn btn-xs btn-outline-primary font-weight-bold">
                        <i class="fas fa-external-link-alt mr-1"></i>Gestión de Interfaces
                    </a>
                </td>
            </tr>`);
        });

        if (!corrPaginator) {
            corrPaginator = initTablePaginator({
                tableSelector: '#vl-corr-table',
                searchSelector: '#vl-corr-table-search',
                pageSizeSelector: '#vl-corr-page-size',
                infoSelector: '#vl-corr-table-info',
                paginationSelector: '#vl-corr-pagination'
            });
        } else {
            corrPaginator.refresh(true);
        }
    }, 'json');
}

function loadDevicePorts(devId, hostname) {
    Swal.fire({
        title: hostname,
        text: 'Consultando interfaces y conexiones...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    $.get(API_PM + '?action=get_device_ports_and_connections&device_id=' + devId, function(res) {
        if (!res.success) {
            Swal.fire('Información', res.error || 'No se encontraron datos para este equipo.', 'info');
            return;
        }
        let np = res.network_ports || [];
        let pp = res.power_ports || [];
        let total = np.length + pp.length;
        let mapped = np.filter(p => p.mapping_id).length + pp.filter(p => p.mapping_id).length;
        
        let html = `<div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
            <table class="table table-sm table-striped table-hover border text-left mb-0" style="font-size: 0.8rem;">
                <thead class="thead-dark"><tr><th>Puerto / Interface</th><th>Estado</th><th>Equipo Destino</th><th>Puerto Destino</th><th>Tipo Cable</th></tr></thead>
                <tbody>`;
        
        if (total === 0) {
            html += '<tr><td colspan="5" class="text-center py-3 text-muted">Sin interfaces ni mapeos registrados.</td></tr>';
        } else {
            np.concat(pp).forEach(p => {
                let statusBadge = p.mapping_id ? '<span class="badge badge-success">Mapeado</span>' : '<span class="badge badge-secondary">Disponible</span>';
                let destDev = p.dest_device_name ? escH(p.dest_device_name) : '<span class="text-muted">-</span>';
                let destPort = p.dest_port_name ? escH(p.dest_port_name) : '<span class="text-muted">-</span>';
                html += `<tr>
                    <td class="font-weight-bold">${escH(p.port_name)}</td>
                    <td>${statusBadge}</td>
                    <td>${destDev}</td>
                    <td>${destPort}</td>
                    <td><small>${escH(p.cable_type || 'UTP')}</small></td>
                </tr>`;
            });
        }
        html += '</tbody></table></div>';
        
        Swal.fire({
            title: `<i class="fas fa-server mr-2" style="color:var(--sonda-orange);"></i>${hostname}`,
            html: `<div class="mb-2 text-muted font-weight-bold" style="font-size:0.85rem;">Puertos Mapeados: ${mapped} de ${total}</div>${html}`,
            width: '780px',
            confirmButtonText: 'Cerrar',
            confirmButtonColor: '#101B31'
        });
    }, 'json');
}

// ========== HELPERS ==========
function escH(str) {
    if (!str) return '';
    return str.toString().replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ========== INITIALIZATION ==========
$(document).ready(function() {
    // 1. Inicializar Paginador y Filtro de Búsqueda para Inventario Analítico
    try {
        invPaginator = initTablePaginator({
            tableSelector: '#vl-inventory-table',
            searchSelector: '#vl-inv-table-search',
            pageSizeSelector: '#vl-inv-page-size',
            infoSelector: '.vl-inv-table-info-target, #vl-inv-table-info',
            paginationSelector: '.vl-inv-pagination-target, #vl-inv-pagination, #vl-inv-pagination-top',
            extraFilterFn: function($row) {
                if (!g_filteredDeviceIds) return true;
                const devId = Number($row.attr('data-id') || $row.data('id'));
                return g_filteredDeviceIds.has(devId);
            },
            onFilterChange: function(filteredCount, totalCount) {
                $('#vl-table-count').text(`${filteredCount} de ${totalCount} Equipos`);
            }
        });
    } catch(errInv) {
        console.error('Error al inicializar paginador de inventario:', errInv);
    }

    // 2. Render gráficos ejecutivos de infraestructura
    try {
        initOverviewCharts();
    } catch(errCharts) {
        console.error('Error al inicializar gráficos ejecutivos:', errCharts);
    }

    // 3. Inicializar y aplicar filtros concatenados desde la carga inicial
    try {
        applyAllFilters();
    } catch(errFilt) {
        console.error('Error al aplicar filtros iniciales:', errFilt);
    }

    // 4. Sincronización y disparadores de cambio de filtros
    $('#vl-filter-location, #vl-filter-area, #vl-filter-rack, #vl-filter-type').on('change', function() {
        applyAllFilters();
    });

    let searchTimer = null;
    $('#vl-filter-search').on('input keyup', function() {
        let val = $(this).val();
        $('#vl-inv-table-search').val(val);
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            applyAllFilters();
        }, 150);
    });

    $('#vl-inv-table-search').on('input keyup', function() {
        let val = $(this).val();
        $('#vl-filter-search').val(val);
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            applyAllFilters();
        }, 150);
    });
});

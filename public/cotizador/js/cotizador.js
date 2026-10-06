// Global error loggers to assist debugging in UI
window.addEventListener('error', function(e) {
  console.error("Global JS Error: ", e.message, "at", e.filename, "line", e.lineno);
  fetch('log_js_error.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      type: 'JS_ERROR',
      message: e.message,
      file: e.filename,
      line: e.lineno
    })
  }).catch(err => console.error(err));

  const errorDiv = document.createElement('div');
  errorDiv.className = 'alert alert-danger alert-dismissible fade show m-3';
  errorDiv.style.position = 'fixed';
  errorDiv.style.top = '10px';
  errorDiv.style.right = '10px';
  errorDiv.style.zIndex = '9999';
  errorDiv.innerHTML = `
    <strong>Error de Javascript Detectado:</strong><br>
    ${e.message}<br>
    <small>${e.filename}:${e.lineno}</small>
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
  `;
  document.body.appendChild(errorDiv);
});

window.addEventListener('unhandledrejection', function(e) {
  console.error("Unhandled promise rejection: ", e.reason);
  fetch('log_js_error.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      type: 'PROMISE_REJECTION',
      message: String(e.reason),
      file: '',
      line: ''
    })
  }).catch(err => console.error(err));

  const errorDiv = document.createElement('div');
  errorDiv.className = 'alert alert-danger alert-dismissible fade show m-3';
  errorDiv.style.position = 'fixed';
  errorDiv.style.top = '10px';
  errorDiv.style.right = '10px';
  errorDiv.style.zIndex = '9999';
  errorDiv.innerHTML = `
    <strong>Promesa Rechazada (Error de Red/AJAX):</strong><br>
    ${e.reason || 'Error desconocido'}<br>
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
  `;
  document.body.appendChild(errorDiv);
});

let specialists = [];
let specialistLevels = [];
let equipmentCategories = [];
let poolBrands = [];
let servicePool = [];
let poolCurrentPage = 1;
let addedItems = {
  Implementacion: [],
  MantPrev: [],
  MantCorr: [],
  BolsaHoras: []
};

$(document).ready(function() {
  // jQuery AJAX error handler
  $(document).ajaxError(function(event, jqXHR, ajaxSettings, thrownError) {
    console.error("AJAX Error: ", thrownError, "URL: ", ajaxSettings.url, "Response: ", jqXHR.responseText);
    
    fetch('log_js_error.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        type: 'AJAX_ERROR',
        message: `Llamada fallida a: ${ajaxSettings.url} - Estado: ${jqXHR.status} (${thrownError})`,
        file: jqXHR.responseText ? jqXHR.responseText.substring(0, 300) : '',
        line: ''
      })
    }).catch(err => console.error(err));

    const safeResponse = String(jqXHR.responseText || '').replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
    const errorDiv = document.createElement('div');
    errorDiv.className = 'alert alert-danger alert-dismissible fade show m-3';
    errorDiv.style.position = 'fixed';
    errorDiv.style.top = '10px';
    errorDiv.style.right = '10px';
    errorDiv.style.zIndex = '9999';
    errorDiv.innerHTML = `
      <strong>Error de Red / API:</strong><br>
      Llamada fallida a: <code>${ajaxSettings.url}</code><br>
      Estado: ${jqXHR.status} (${thrownError})<br>
      Respuesta: <pre class="bg-light p-1" style="max-height: 100px; overflow: auto; font-size: 0.75rem; white-space: pre-wrap;">${safeResponse}</pre>
      <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
    `;
    document.body.appendChild(errorDiv);
  });

  $.ajaxSetup({ cache: false });

  loadQuotesList();
  loadSpecialistLevelsList();
  loadSpecialistsList();
  loadEqCategories();
  loadPoolBrands();
  loadBrandFilters();
});

// -------------------------------------------------------------
// DATA LOADERS
// -------------------------------------------------------------
function loadQuotesList() {
  const client = $('#filter-client').val();
  const status = $('#filter-status').val();
  const month = $('#filter-month').val();
  
  $.getJSON('api.php?action=get_quotes', { client, status, month }, function(res) {
    if (res.success) {
      let html = '';
      if (res.data.length === 0) {
        html = '<tr><td colspan="10" class="text-center py-3">No se encontraron cotizaciones.</td></tr>';
      } else {
        res.data.forEach(q => {
          const hasVersions = q.versions_count > 1;
          const marginLabel = Math.round(q.margen_global * 100) + '%';
          const costFormatted = '$' + parseFloat(q.total_costo).toFixed(2);
          const pvpFormatted = '$' + parseFloat(q.total_precio).toFixed(2);
          
          const badgeClass = q.estado === 'Enviada' ? 'badge-enviada' : 'badge-borrador';
          const statusLabel = q.estado === 'Enviada' ? 'Enviada (Aprobada)' : 'Borrador';
          
          html += `
            <tr class="quote-parent-row" data-id="${q.id}">
              <td>
                <input type="checkbox" class="quote-compare-cb" value="${q.id}" onchange="checkCompareSelection()">
              </td>
              <td>
                ${hasVersions ? `<span class="mr-2 text-primary cursor-pointer toggle-versions-btn" onclick="toggleVersions(${q.id})"><i class="fas fa-plus-circle"></i></span>` : ''}
                <strong>${escapeHtml(q.cliente)}</strong>
              </td>
              <td>${escapeHtml(q.contrato)}</td>
              <td>${q.fecha}</td>
              <td>${marginLabel}</td>
              <td>${costFormatted}</td>
              <td><strong>${pvpFormatted}</strong></td>
              <td><span class="badge-status ${badgeClass}">${statusLabel}</span></td>
              <td>v${q.version}</td>
              <td class="text-right">
                <div class="btn-group btn-group-sm">
                  <a href="view.php?id=${q.id}" target="_blank" class="btn btn-outline-primary" title="Ver Detalle y Comparar"><i class="fas fa-eye"></i></a>
                  <a href="matrix.php?id=${q.id}" target="_blank" class="btn btn-outline-success" title="Matriz de Ejecución"><i class="fas fa-table"></i></a>
                  <button class="btn btn-outline-info" title="Editar (Sobrescribir)" onclick="loadQuoteForEdit(${q.id}, 'update')"><i class="fas fa-edit"></i></button>
                  <button class="btn btn-outline-warning" title="Editar (Nueva Versión)" onclick="loadQuoteForEdit(${q.id}, 'new_version')"><i class="fas fa-history"></i></button>
                  ${q.estado === 'Borrador' ? `<button class="btn btn-outline-success" title="Aprobar / Enviar" onclick="approveQuote(${q.id})"><i class="fas fa-check"></i></button>` : ''}
                  <a href="print.php?id=${q.id}" target="_blank" class="btn btn-outline-secondary" title="Generar PDF / Imprimir"><i class="fas fa-print"></i></a>
                  <button class="btn btn-outline-danger" title="Eliminar" onclick="deleteQuote(${q.id})"><i class="fas fa-trash"></i></button>
                </div>
              </td>
            </tr>
            <tr class="d-none version-tree-row" id="versions-container-${q.id}">
              <td colspan="10" class="p-3 bg-light">
                <div class="pl-4">
                  <h6 class="font-weight-bold text-secondary mb-2"><i class="fas fa-code-branch"></i> Historial de Versiones:</h6>
                  <table class="table table-sm table-bordered table-striped mb-0 bg-white">
                    <thead>
                      <tr>
                        <th style="width:40px;">Comp</th>
                        <th>Versión</th>
                        <th>Fecha Modificación</th>
                        <th>Margen</th>
                        <th>Costo</th>
                        <th>PVP</th>
                        <th>Estado</th>
                        <th>Aprobado Por</th>
                        <th class="text-right">Acciones</th>
                      </tr>
                    </thead>
                    <tbody id="versions-body-${q.id}">
                      <!-- Child versions loaded here -->
                    </tbody>
                  </table>
                </div>
              </td>
            </tr>
          `;
        });
      }
      $('#quotes-table-body').html(html);
    } else {
      toastr.error(res.message);
    }
  });
}

function toggleVersions(parentId) {
  const container = $(`#versions-container-${parentId}`);
  const btn = container.prev().find('.toggle-versions-btn i');
  
  if (container.hasClass('d-none')) {
    // Load child versions via Ajax
    $.getJSON('api.php?action=get_quote_versions', { parent_id: parentId }, function(res) {
      if (res.success) {
        let html = '';
        res.data.forEach(v => {
          const costFormatted = '$' + parseFloat(v.total_costo).toFixed(2);
          const pvpFormatted = '$' + parseFloat(v.total_precio).toFixed(2);
          const badgeClass = v.estado === 'Enviada' ? 'badge-enviada' : 'badge-borrador';
          const statusLabel = v.estado === 'Enviada' ? 'Enviada (Aprobada)' : 'Borrador';
          
          html += `
            <tr>
              <td>
                <input type="checkbox" class="quote-compare-cb" value="${v.id}" onchange="checkCompareSelection()">
              </td>
              <td><strong>v${v.version}</strong> ${v.id == parentId ? '<span class="badge badge-light">Original</span>' : ''}</td>
              <td>${v.created_at}</td>
              <td>${Math.round(v.margen_global * 100)}%</td>
              <td>${costFormatted}</td>
              <td><strong>${pvpFormatted}</strong></td>
              <td><span class="badge-status ${badgeClass}">${statusLabel}</span></td>
              <td>${v.aprobado_por ? escapeHtml(v.aprobado_por) + ' (' + v.aprobado_fecha + ')' : '-'}</td>
              <td class="text-right">
                <div class="btn-group btn-group-sm">
                  <a href="view.php?id=${v.id}" target="_blank" class="btn btn-outline-primary" title="Ver Detalle y Comparar"><i class="fas fa-eye"></i></a>
                  <a href="matrix.php?id=${v.id}" target="_blank" class="btn btn-outline-success" title="Matriz de Ejecución"><i class="fas fa-table"></i></a>
                  <button class="btn btn-outline-info" title="Editar (Sobrescribir)" onclick="loadQuoteForEdit(${v.id}, 'update')"><i class="fas fa-edit"></i></button>
                  <button class="btn btn-outline-warning" title="Editar (Nueva Versión)" onclick="loadQuoteForEdit(${v.id}, 'new_version')"><i class="fas fa-history"></i></button>
                  ${v.estado === 'Borrador' ? `<button class="btn btn-outline-success" title="Aprobar / Enviar" onclick="approveQuote(${v.id})"><i class="fas fa-check"></i></button>` : ''}
                  <a href="print.php?id=${v.id}" target="_blank" class="btn btn-outline-secondary" title="Generar PDF / Imprimir"><i class="fas fa-print"></i></a>
                  <button class="btn btn-outline-danger" title="Eliminar" onclick="deleteQuote(${v.id})"><i class="fas fa-trash"></i></button>
                </div>
              </td>
            </tr>
          `;
        });
        $(`#versions-body-${parentId}`).html(html);
        container.removeClass('d-none');
        btn.removeClass('fa-plus-circle').addClass('fa-minus-circle');
      }
    });
  } else {
    container.addClass('d-none');
    btn.removeClass('fa-minus-circle').addClass('fa-plus-circle');
  }
}

// Bind change filters
$('#filter-client, #filter-status, #filter-month').on('input change', function() {
  loadQuotesList();
});

function checkCompareSelection() {
  const selected = $('.quote-compare-cb:checked');
  if (selected.length === 2) {
    $('#btn-compare-selected').removeClass('d-none');
  } else {
    $('#btn-compare-selected').addClass('d-none');
  }
}

function compareSelectedQuotes() {
  const selected = $('.quote-compare-cb:checked');
  if (selected.length !== 2) {
    Swal.fire('Atención', 'Debe seleccionar exactamente dos cotizaciones para comparar.', 'warning');
    return;
  }
  
  const id1 = selected.eq(0).val();
  const id2 = selected.eq(1).val();
  
  window.open(`view.php?id=${id1}&compare_with=${id2}`, '_blank');
}

function renderComparison(q1, q2) {
  const m1 = q1.meta;
  const m2 = q2.meta;
  
  const cost1 = parseFloat(m1.total_costo);
  const cost2 = parseFloat(m2.total_costo);
  const diffCost = cost2 - cost1;
  const diffCostClass = diffCost > 0 ? 'comparison-diff-plus' : 'comparison-diff-minus';
  
  const pvp1 = parseFloat(m1.total_precio);
  const pvp2 = parseFloat(m2.total_precio);
  const diffPvp = pvp2 - pvp1;
  const diffPvpClass = diffPvp > 0 ? 'comparison-diff-plus' : 'comparison-diff-minus';

  // Build structural items
  let html = `
    <div class="row">
      <div class="col-md-6">
        <div class="card p-3 shadow-none border">
          <h5 class="text-primary font-weight-bold">Cotización A (v${m1.version})</h5>
          <table class="table table-sm table-borderless">
            <tr><th>Cliente:</th><td>${escapeHtml(m1.cliente)}</td></tr>
            <tr><th>Contrato:</th><td>${escapeHtml(m1.contrato)}</td></tr>
            <tr><th>Fecha:</th><td>${m1.fecha}</td></tr>
          </table>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card p-3 shadow-none border">
          <h5 class="text-info font-weight-bold">Cotización B (v${m2.version})</h5>
          <table class="table table-sm table-borderless">
            <tr><th>Cliente:</th><td>${escapeHtml(m2.cliente)}</td></tr>
            <tr><th>Contrato:</th><td>${escapeHtml(m2.contrato)}</td></tr>
            <tr><th>Fecha:</th><td>${m2.fecha}</td></tr>
          </table>
        </div>
      </div>
    </div>
    
    <div class="table-responsive mt-3">
      <table class="table table-bordered table-striped table-premium">
        <thead>
          <tr class="bg-dark text-white">
            <th>Metodología / Métrica</th>
            <th class="text-right">Cotización A</th>
            <th class="text-right">Cotización B</th>
            <th class="text-right">Diferencia</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Costo Total del Proyecto</td>
            <td class="text-right">$${cost1.toFixed(2)}</td>
            <td class="text-right">$${cost2.toFixed(2)}</td>
            <td class="text-right ${diffCostClass}">$${diffCost.toFixed(2)}</td>
          </tr>
          <tr>
            <td>Precio de Venta (PVP)</td>
            <td class="text-right">$${pvp1.toFixed(2)}</td>
            <td class="text-right">$${pvp2.toFixed(2)}</td>
            <td class="text-right ${diffPvpClass}">$${diffPvp.toFixed(2)}</td>
          </tr>
          <tr>
            <td>Margen Configurado</td>
            <td class="text-right">${Math.round(m1.margen_global*100)}%</td>
            <td class="text-right">${Math.round(m2.margen_global*100)}%</td>
            <td class="text-right">${Math.round((m2.margen_global - m1.margen_global)*100)}%</td>
          </tr>
          <tr>
            <td>Riesgo Configurado</td>
            <td class="text-right">${Math.round(m1.risk_percentage*100)}%</td>
            <td class="text-right">${Math.round(m2.risk_percentage*100)}%</td>
            <td class="text-right">${Math.round((m2.risk_percentage - m1.risk_percentage)*100)}%</td>
          </tr>
        </tbody>
      </table>
    </div>
  `;
  
  $('#comparison-modal-body').html(html);
  $('#comparisonModal').modal('show');
}

// -------------------------------------------------------------
// SPECIALISTS HANDLING
// -------------------------------------------------------------
function loadSpecialistsList() {
  $.getJSON('api.php?action=get_specialists', function(res) {
    if (res.success) {
      specialists = res.data;
      let html = '';
      specialists.forEach(sp => {
        const salaryFormatted = sp.salario > 0 ? '$' : '';
        const spSalary = sp.salario > 0 ? parseFloat(sp.salario).toFixed(2) : '-';
        const costHour = parseFloat(sp.costo_hora_lab).toFixed(2);
        const pvpHour = parseFloat(sp.pvp_hora_lab).toFixed(2);
        
        const rangeText = sp.rango_salarial ? (sp.rango_salarial.includes('$') ? sp.rango_salarial : '$' + sp.rango_salarial) : 'Tarifa Fija';
        
        html += `
          <tr>
            <td><strong>${escapeHtml(sp.nombre)}</strong></td>
            <td><span class="badge badge-secondary">${sp.tipo}</span></td>
            <td><span class="text-muted small">${rangeText}</span></td>
            <td class="text-right">${salaryFormatted}${spSalary}</td>
            <td>${sp.utilizable}</td>
            <td class="text-right">$${parseFloat(sp.costo_empresa).toFixed(2)}</td>
            <td>${parseFloat(sp.horas_laborables).toFixed(1)}</td>
            <td class="text-right"><strong>$${costHour}</strong></td>
            <td class="text-right text-success"><strong>$${pvpHour}</strong></td>
            <td class="text-right">
              <button class="btn btn-xs btn-info" onclick="editSpecialist(${sp.id})"><i class="fas fa-edit"></i></button>
              <button class="btn btn-xs btn-danger" onclick="deleteSpecialist(${sp.id})"><i class="fas fa-trash"></i></button>
            </td>
          </tr>
        `;
      });
      $('#specialists-table-body').html(html);
      
      // Populate global specialist level dropdowns dynamically
      const globalDropdowns = ['#impl-kt-level', '#impl-boc-level', '#impl-pm-level', '#corr-case-level'];
      globalDropdowns.forEach(selId => {
        const jq = $(selId);
        const currentVal = jq.val();
        let optHtml = '';
        const seenTypes = new Set();
        specialists.forEach(sp => {
          if (!seenTypes.has(sp.tipo)) {
            seenTypes.add(sp.tipo);
            const rangeText = sp.rango_salarial ? (sp.rango_salarial.includes('$') ? sp.rango_salarial : '$' + sp.rango_salarial) : 'Tarifa Fija';
            optHtml += `<option value="${escapeHtml(sp.tipo)}">${escapeHtml(sp.nombre)} [${escapeHtml(sp.tipo)}] (${escapeHtml(rangeText)})</option>`;
          }
        });
        jq.html(optHtml);
        if (currentVal) jq.val(currentVal);
      });
    } else {
      toastr.error(res.message || 'Error al cargar especialistas.');
      fetch('log_js_error.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          type: 'API_FAIL_GET_SPECIALISTS',
          message: res.message || 'Error en get_specialists',
          file: 'api.php',
          line: ''
        })
      }).catch(err => console.error(err));
    }
  });
}

function showSpecialistModal() {
  $('#specialistForm')[0].reset();
  $('#sp-id').val(0);
  $('#specialistModalTitle').text('Agregar Especialista');
  toggleSpecialistFields();
  $('#specialistModal').modal('show');
}

function toggleSpecialistFields() {
  const tipo = $('#sp-tipo').val();
  const levelObj = specialistLevels.find(x => x.code === tipo);
  
  let rangeVal = '';
  let baseType = tipo;
  
  if (levelObj) {
    rangeVal = `${parseFloat(levelObj.min_salary).toFixed(0)} - ${parseFloat(levelObj.max_salary).toFixed(0)}`;
    baseType = levelObj.base_type;
  }
  
  if (rangeVal) {
    $('#sp-salary-range-info').text('Rango configurado: $' + rangeVal).show();
    $('#sp-rango-salarial').val(rangeVal);
  } else {
    $('#sp-salary-range-info').hide();
  }
  
  if (baseType === 'E1' || baseType === 'E2') {
    $('#div-sp-salario').addClass('d-none');
    $('#div-sp-utilizable').addClass('d-none');
    $('#div-sp-manual').removeClass('d-none');
  } else {
    $('#div-sp-salario').removeClass('d-none');
    $('#div-sp-utilizable').removeClass('d-none');
    $('#div-sp-manual').addClass('d-none');
  }
  
  validateSpecialistSalary();
}

function validateSpecialistSalary() {
  const tipo = $('#sp-tipo').val();
  const levelObj = specialistLevels.find(x => x.code === tipo);
  if (!levelObj || levelObj.base_type === 'E1' || levelObj.base_type === 'E2') {
    $('#sp-salario').removeClass('is-invalid is-valid');
    return true;
  }
  
  const salario = parseFloat($('#sp-salario').val()) || 0;
  const min = parseFloat(levelObj.min_salary);
  const max = parseFloat(levelObj.max_salary);
  
  if (salario < min || salario > max) {
    $('#sp-salario').addClass('is-invalid').removeClass('is-valid');
    return false;
  } else {
    $('#sp-salario').addClass('is-valid').removeClass('is-invalid');
    return true;
  }
}

// Bind validator
$(document).on('input change', '#sp-salario', function() {
  validateSpecialistSalary();
});

function toggleKtSection() {
  const incl = $('#impl-kt-incluye').val();
  if (incl === 'Si') {
    $('#impl-kt-panel').slideDown();
  } else {
    $('#impl-kt-panel').slideUp();
  }
}

function editSpecialist(id) {
  const sp = specialists.find(x => x.id == id);
  if (!sp) return;
  
  $('#sp-id').val(sp.id);
  $('#sp-nombre').val(sp.nombre);
  $('#sp-tipo').val(sp.tipo);
  $('#sp-rango-salarial').val(sp.rango_salarial || '');
  $('#sp-salario').val(sp.salario);
  $('#sp-utilizable').val(sp.utilizable);
  $('#sp-costo-manual').val(sp.costo_hora_manual);
  
  $('#specialistModalTitle').text('Editar Especialista');
  toggleSpecialistFields();
  $('#specialistModal').modal('show');
}

function saveSpecialist(e) {
  e.preventDefault();
  if (!validateSpecialistSalary()) {
    Swal.fire('Atención', 'El salario ingresado está fuera del rango permitido para el nivel seleccionado.', 'warning');
    return;
  }
  
  $.post('api.php?action=save_specialist', $('#specialistForm').serialize(), function(res) {
    if (res.success) {
      $('#specialistModal').modal('hide');
      toastr.success(res.message);
      loadSpecialistsList();
    } else {
      toastr.error(res.message);
    }
  });
}

function deleteSpecialist(id) {
  Swal.fire({
    title: '¿Está seguro?',
    text: "Se eliminará el especialista y afectará los nuevos cálculos.",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      $.post('api.php?action=delete_specialist', { id }, function(res) {
        if (res.success) {
          toastr.success(res.message);
          loadSpecialistsList();
        } else {
          toastr.error(res.message);
        }
      });
    }
  });
}

// -------------------------------------------------------------
// POOL OF SERVICES HANDLING
// -------------------------------------------------------------
// Generate brand codes and activity codes dynamically
let brandMrcCodes = {}; // Maps brand name -> { code: 'MRC-01', num: '01' }
let activityCodesMap = {}; // Maps "brand|activity" -> "cisco_03001"

function computeMrcAndActivityCodes() {
  brandMrcCodes = {};
  activityCodesMap = {};
  
  // Sort all unique active brands alphabetically
  const brands = [...new Set(servicePool.filter(x => x.activo != 0).map(x => x.marca_categoria))].sort();
  brands.forEach((b, idx) => {
    const num = String(idx + 1).padStart(2, '0');
    brandMrcCodes[b] = {
      code: `MRC-${num}`,
      num: num
    };
  });
  
  // For each brand, get unique activities in alphabetical order
  brands.forEach(b => {
    const bInfo = brandMrcCodes[b];
    const brandPrefix = b.toLowerCase().replace(/[^a-z0-9]/g, '');
    
    const activities = [...new Set(servicePool
      .filter(x => x.marca_categoria === b && x.activo != 0)
      .map(x => x.actividad)
    )].sort();
    
    activities.forEach((act, actIdx) => {
      const actNum = String(actIdx + 1).padStart(3, '0');
      const key = `${b}|${act}`;
      activityCodesMap[key] = `${brandPrefix}_${bInfo.num}${actNum}`;
    });
  });
}

function loadPoolBrands() {
  $.getJSON('api.php?action=get_pool_brands', function(res) {
    if (res.success) {
      poolBrands = res.data;
      renderPoolBrandsTable();
      // Refresh brand dropdowns now that we have DB data
      const filterSelect     = $('#pool-brand-filter');
      const modalFilterSelect = $('#modal-pool-brand');
      const poolModalBrand   = $('#pool-modal-marca');  // in the add-activity modal

      const currentFilter = filterSelect.val();
      filterSelect.html('<option value="">-- Todas las Marcas / Cat\u00e9gor\u00edas --</option>');
      modalFilterSelect.html('<option value="">-- Todas las Marcas / Cat\u00e9gor\u00edas --</option>');
      if (poolModalBrand.length) poolModalBrand.html('<option value="">-- Seleccionar Marca --</option>');

      poolBrands.forEach((b, i) => {
        const color = BRAND_COLORS[i % BRAND_COLORS.length];
        $('<option>').val(b.name).text(b.name).appendTo(filterSelect);
        $('<option>').val(b.name).text(b.name).appendTo(modalFilterSelect);
        if (poolModalBrand.length) $('<option>').val(b.name).text(b.name).appendTo(poolModalBrand);
      });

      if (currentFilter) filterSelect.val(currentFilter);
    }
  });
}

function loadBrandFilters() {
  $.getJSON('api.php?action=get_pool', function(res) {
    if (res.success) {
      servicePool = res.data;
      computeMrcAndActivityCodes(); // Compute the dynamic activity codes
      
      // Use DB brands if loaded, otherwise derive from servicePool
      const brands = poolBrands.length > 0
        ? poolBrands.map(b => b.name)
        : [...new Set(servicePool.map(x => x.marca_categoria))].sort();
      
      let filterSelect = $('#pool-brand-filter');
      let modalFilterSelect = $('#modal-pool-brand');
      
      filterSelect.html('<option value="">-- Todas las Marcas / Categorías --</option>');
      modalFilterSelect.html('<option value="">-- Todas las Marcas / Categorías --</option>');
      
      brands.forEach(b => {
        $('<option>').val(b).text(b).appendTo(filterSelect);
        $('<option>').val(b).text(b).appendTo(modalFilterSelect);
      });
      
      loadPoolList();
    } else {
      toastr.error(res.message || 'Error al cargar el pool de servicios.');
      fetch('log_js_error.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          type: 'API_FAIL_GET_POOL',
          message: res.message || 'Error en get_pool',
          file: 'api.php',
          line: ''
        })
      }).catch(err => console.error(err));
    }
  });
}

function setPoolPage(page) {
  poolCurrentPage = page;
  loadPoolList();
}

function loadPoolList(resetPage = false) {
  if (resetPage) {
    poolCurrentPage = 1;
  }
  const brand = $('#pool-brand-filter').val();
  const activity = $('#pool-activity-filter').val();
  const search = $('#pool-search-filter').val().toLowerCase();
  
  // 1. Filter items
  const filteredItems = [];
  servicePool.forEach(p => {
    if (p.activo == 0) return; // Ignore inactive items
    
    const pBrand = (p.marca_categoria || '').trim();
    const pActivity = (p.actividad || '').trim();
    
    // Filter by brand if selected
    if (brand && pBrand !== brand.trim()) return;
    
    // Filter by activity if selected
    if (activity && pActivity !== activity.trim()) return;
    
    const key = `${pBrand}|${pActivity}`;
    const activityCode = activityCodesMap[key] || '';
    
    const textToSearch = `${activityCode.toLowerCase()} ${pBrand.toLowerCase()} ${pActivity.toLowerCase()} ${(p.detalle || '').toLowerCase()}`;
    if (search && textToSearch.indexOf(search) === -1) return;
    
    filteredItems.push(p);
  });
  
  const totalItems = filteredItems.length;
  
  // 2. Sort filtered items by Brand (L1) and then Activity (L2) to group them for separators
  filteredItems.sort((a, b) => {
    const brandA = (a.marca_categoria || '').toLowerCase();
    const brandB = (b.marca_categoria || '').toLowerCase();
    if (brandA !== brandB) return brandA.localeCompare(brandB);
    const actA = (a.actividad || '').toLowerCase();
    const actB = (b.actividad || '').toLowerCase();
    return actA.localeCompare(actB);
  });
  
  // 3. Paginate
  const pageSize = 15;
  const totalPages = Math.ceil(totalItems / pageSize) || 1;
  if (poolCurrentPage > totalPages) poolCurrentPage = totalPages;
  if (poolCurrentPage < 1) poolCurrentPage = 1;
  
  const startIndex = (poolCurrentPage - 1) * pageSize;
  const endIndex = Math.min(startIndex + pageSize, totalItems);
  const pageItems = filteredItems.slice(startIndex, endIndex);
  
  // 4. Render separators and rows
  let html = '';
  let lastBrand = null;
  let lastActivity = null;
  
  // Build brand color mapping using DB brands index if available to keep colors consistent
  const poolBrandColorMap = {};
  const uniqueBrands = [...new Set(filteredItems.map(p => p.marca_categoria || 'Sin categoría'))];
  uniqueBrands.forEach((brandName, i) => {
    const pbIndex = poolBrands.findIndex(pb => pb.name === brandName);
    const colorIndex = pbIndex !== -1 ? pbIndex : i;
    poolBrandColorMap[brandName] = BRAND_COLORS[colorIndex % BRAND_COLORS.length];
  });
  
  // Pre-calculate counts of tasks within the pageItems slice for the badge counts
  const brandTaskCountPage = {};
  const activityTaskCountPage = {};
  pageItems.forEach(p => {
    const b = p.marca_categoria || 'Sin categoría';
    const a = p.actividad || 'Sin actividad';
    brandTaskCountPage[b] = (brandTaskCountPage[b] || 0) + 1;
    const key = `${b}||${a}`;
    activityTaskCountPage[key] = (activityTaskCountPage[key] || 0) + 1;
  });
  
  pageItems.forEach(p => {
    const currentBrand = p.marca_categoria || 'Sin categoría';
    const currentActivity = p.actividad || 'Sin actividad';
    const colors = poolBrandColorMap[currentBrand] || BRAND_COLORS[0];
    const actKey = `${currentBrand}||${currentActivity}`;
    
    // Brand separator row
    if (currentBrand !== lastBrand) {
      const totalTasksPage = brandTaskCountPage[currentBrand] || 0;
      html += `
        <tr class="brand-separator-row brand-sep-l1" 
            style="background: linear-gradient(135deg, ${colors.border}22 0%, ${colors.badge}44 50%, ${colors.border}22 100%);
                   border-left: 5px solid ${colors.badge}; font-weight: bold;">
          <td colspan="14" style="padding:11px 18px; border-left:5px solid ${colors.badge};">
            <div class="d-flex align-items-center justify-content-between">
              <div class="d-flex align-items-center">
                <span style="display:inline-block;width:13px;height:13px;border-radius:50%;
                             background:${colors.badge};margin-right:10px;
                             box-shadow:0 0 0 2px rgba(255,255,255,0.8),0 0 0 4px ${colors.badge}44;"></span>
                <strong style="color:${colors.text};font-size:1rem;letter-spacing:0.08em;text-transform:uppercase;text-shadow:0 1px 2px rgba(0,0,0,0.08);">
                  ${escapeHtml(currentBrand)}
                </strong>
              </div>
              <span style="background:${colors.badge};color:#fff;font-size:0.74rem;padding:3px 12px;border-radius:14px;font-weight:700;letter-spacing:0.04em;">
                ${totalTasksPage} en esta página
              </span>
            </div>
          </td>
        </tr>
      `;
      lastBrand = currentBrand;
      lastActivity = null;
    }
    
    // Activity sub-separator row
    if (currentActivity !== lastActivity) {
      const tasksInActPage = activityTaskCountPage[actKey] || 0;
      html += `
        <tr class="brand-separator-row brand-sep-l2"
            style="background: linear-gradient(135deg, ${colors.border}14 0%, ${colors.badge}28 60%, ${colors.border}14 100%);
                   border-left: 3px solid ${colors.border};">
          <td colspan="14" style="padding:7px 18px 7px 36px; border-left:3px solid ${colors.border};">
            <div class="d-flex align-items-center justify-content-between">
              <div class="d-flex align-items-center">
                <i class="fas fa-layer-group mr-2" style="color:${colors.border};font-size:0.8rem;opacity:0.9;"></i>
                <span style="color:${colors.text};font-size:0.9rem;font-weight:700;letter-spacing:0.02em;">
                  ${escapeHtml(currentActivity)}
                </span>
              </div>
              <span style="background:${colors.border};color:#fff;font-size:0.68rem;padding:2px 9px;border-radius:10px;font-weight:700;opacity:0.9;">
                ${tasksInActPage} en esta página
              </span>
            </div>
          </td>
        </tr>
      `;
      lastActivity = currentActivity;
    }
    
    const key = `${currentBrand}|${currentActivity}`;
    const activityCode = activityCodesMap[key] || '';
    
    html += `
      <tr class="pool-row" data-brand="${escapeHtml(p.marca_categoria)}" data-activity="${escapeHtml(p.actividad)}">
        <td><span class="badge badge-secondary text-monospace">${escapeHtml(activityCode)}</span></td>
        <td><strong>${escapeHtml(p.marca_categoria)}</strong></td>
        <td>${escapeHtml(p.actividad)}</td>
        <td>${escapeHtml(p.detalle)}</td>
        <td class="text-center">${p.n1 ? '<i class="fas fa-check text-success"></i>' : '-'}</td>
        <td class="text-center">${p.n2 ? '<i class="fas fa-check text-success"></i>' : '-'}</td>
        <td class="text-center">${p.n3 ? '<i class="fas fa-check text-success"></i>' : '-'}</td>
        <td class="text-center">${p.e1 ? '<i class="fas fa-check text-success"></i>' : '-'}</td>
        <td class="text-center">${p.e2 ? '<i class="fas fa-check text-success"></i>' : '-'}</td>
        <td>${parseFloat(p.horas_laborables).toFixed(1)}h</td>
        <td>${parseFloat(p.horas_no_laborables_50 || 0).toFixed(1)}h</td>
        <td>${parseFloat(p.horas_no_laborables_100 || 0).toFixed(1)}h</td>
        <td><small>${escapeHtml(p.observaciones || '')}</small></td>
        <td class="text-right">
          <button class="btn btn-xs btn-info" onclick="editPoolItem(${p.id})"><i class="fas fa-edit"></i></button>
          <button class="btn btn-xs btn-danger" onclick="deletePoolItem(${p.id})"><i class="fas fa-trash"></i></button>
        </td>
      </tr>
    `;
  });
  
  if (totalItems === 0) {
    html = '<tr><td colspan="14" class="text-center text-muted py-3">No se encontraron tareas con los filtros aplicados.</td></tr>';
  }
  $('#pool-table-body').html(html);
  
  // 5. Render Pagination
  let paginationHtml = '';
  if (totalPages > 1) {
    paginationHtml = `
      <div class="text-muted small">
        Mostrando ${startIndex + 1} - ${endIndex} de ${totalItems} actividades
      </div>
      <nav aria-label="Navegación del Pool">
        <ul class="pagination pagination-sm mb-0">
          <li class="page-item ${poolCurrentPage === 1 ? 'disabled' : ''}">
            <a class="page-link" href="javascript:void(0)" onclick="setPoolPage(${poolCurrentPage - 1})"><i class="fas fa-chevron-left"></i></a>
          </li>
    `;
    
    const maxVisible = 5;
    let startPage = Math.max(1, poolCurrentPage - Math.floor(maxVisible / 2));
    let endPage = Math.min(totalPages, startPage + maxVisible - 1);
    if (endPage - startPage + 1 < maxVisible) {
      startPage = Math.max(1, endPage - maxVisible + 1);
    }
    
    for (let i = startPage; i <= endPage; i++) {
      paginationHtml += `
        <li class="page-item ${poolCurrentPage === i ? 'active' : ''}">
          <a class="page-link" href="javascript:void(0)" onclick="setPoolPage(${i})">${i}</a>
        </li>
      `;
    }
    
    paginationHtml += `
          <li class="page-item ${poolCurrentPage === totalPages ? 'disabled' : ''}">
            <a class="page-link" href="javascript:void(0)" onclick="setPoolPage(${poolCurrentPage + 1})"><i class="fas fa-chevron-right"></i></a>
          </li>
        </ul>
      </nav>
    `;
  } else if (totalItems > 0) {
    paginationHtml = `
      <div class="text-muted small">
        Mostrando ${totalItems} actividades
      </div>
    `;
  } else {
    paginationHtml = '';
  }
  $('#pool-pagination').html(paginationHtml);
}

function onPoolBrandFilterChange() {
  const brand = $('#pool-brand-filter').val();
  let activitySelect = $('#pool-activity-filter');
  activitySelect.html('<option value="">-- Seleccionar Actividad --</option>');
  
  if (brand) {
    const activities = [...new Set(servicePool
      .filter(x => (x.marca_categoria || '').trim() === brand.trim() && x.activo != 0)
      .map(x => x.actividad)
    )].sort();
    
    activities.forEach(act => {
      const key = `${brand}|${act}`;
      const actCode = activityCodesMap[key] || '';
      const displayText = actCode ? `[${actCode}] ${act}` : act;
      $('<option>')
        .val(act)
        .text(displayText)
        .appendTo(activitySelect);
    });
  }
  
  loadPoolList(true);
}

function showImportExcelModal() {
  $('#importExcelForm')[0].reset();
  $('#importExcelForm .custom-file-label').html('Elegir archivo...');
  $('#importExcelModal').modal('show');
}

// Handle file name label update
$(document).on('change', '#excel_file', function() {
  var fileName = $(this).val().split('\\').pop();
  $(this).next('.custom-file-label').html(fileName || 'Elegir archivo...');
});

$(document).on('submit', '#importExcelForm', function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  
  Swal.fire({
    title: 'Procesando archivo...',
    text: 'Esto puede tardar unos segundos, por favor espere.',
    allowOutsideClick: false,
    didOpen: () => {
      Swal.showLoading();
    }
  });

  $.ajax({
    url: 'api.php?action=import_pool_excel',
    type: 'POST',
    data: formData,
    processData: false,
    contentType: false,
    success: function(res) {
      Swal.close();
      if (res.success) {
        $('#importExcelModal').modal('hide');
        Swal.fire('¡Éxito!', res.message, 'success');
        loadBrandFilters(); // Reload all catalog data and drop-downs
      } else {
        Swal.fire('Error', res.message || 'Error al importar archivo.', 'error');
      }
    },
    error: function(xhr, status, error) {
      Swal.close();
      Swal.fire('Error de red', 'No se pudo comunicar con el servidor para la importación.', 'error');
    }
  });
});

function showPoolModal() {
  $('#poolForm')[0].reset();
  $('#pool-id').val(0);
  $('#poolModalTitle').text('Agregar Servicio al Pool');
  // Populate the marca dropdown from DB brands
  _populatePoolMarcaSelect('');
  $('#poolModal').modal('show');
}

function _populatePoolMarcaSelect(selectedValue) {
  const sel = $('#pool-marca');
  sel.html('<option value="">-- Seleccionar Marca / Categoría --</option>');
  const brands = poolBrands.length > 0
    ? poolBrands.map(b => b.name)
    : [...new Set(servicePool.map(x => x.marca_categoria))].sort();
  brands.forEach(name => {
    $('<option>').val(name).text(name).appendTo(sel);
  });
  if (selectedValue) sel.val(selectedValue);
}


function editPoolItem(id) {
  const p = servicePool.find(x => x.id == id);
  if (!p) return;
  
  $('#pool-id').val(p.id);
  // Populate dropdown then set value
  _populatePoolMarcaSelect(p.marca_categoria);
  $('#pool-actividad').val(p.actividad);
  $('#pool-detalle').val(p.detalle);
  
  $('#pool-n1').prop('checked', p.n1 == 1);
  $('#pool-n2').prop('checked', p.n2 == 1);
  $('#pool-n3').prop('checked', p.n3 == 1);
  $('#pool-e1').prop('checked', p.e1 == 1);
  $('#pool-e2').prop('checked', p.e2 == 1);
  
  $('#pool-horas-lab').val(p.horas_laborables);
  $('#pool-horas-50').val(p.horas_no_laborables_50);
  $('#pool-horas-100').val(p.horas_no_laborables_100);
  $('#pool-tipo').val(p.tipo);
  $('#pool-observaciones').val(p.observaciones);
  
  $('#poolModalTitle').text('Editar Servicio del Pool');
  $('#poolModal').modal('show');
}

function savePoolItem(e) {
  e.preventDefault();
  $.post('api.php?action=save_pool_item', $('#poolForm').serialize(), function(res) {
    if (res.success) {
      $('#poolModal').modal('hide');
      toastr.success(res.message);
      loadBrandFilters();
    } else {
      toastr.error(res.message);
    }
  });
}

function deletePoolItem(id) {
  Swal.fire({
    title: '¿Está seguro?',
    text: "Esta acción desactivará el servicio de la base de datos de actividades del pool.",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      $.post('api.php?action=delete_pool_item', { id }, function(res) {
        if (res.success) {
          toastr.success(res.message);
          loadBrandFilters();
        } else {
          toastr.error(res.message);
        }
      });
    }
  });
}

// -------------------------------------------------------------
// POOL SELECTION WORKFLOW (ADD TO QUOTE WORKBOOK)
// -------------------------------------------------------------
function openPoolSelectionModal(section) {
  $('#pool-target-section').val(section);
  $('#modal-pool-search').val('');
  $('#modal-pool-brand').val('');
  
  loadModalPoolList();
  $('#poolSelectionModal').modal('show');
}

function loadModalPoolList() {
  $('#modal-pool-body').html('<tr><td colspan="9" class="text-center text-muted py-3">Seleccione una Marca y una Actividad arriba para listar las tareas del pool.</td></tr>');
  $('#modal-pool-checkall').prop('checked', false);

  // Reset activity select
  $('#modal-pool-activity').html('<option value="">-- Seleccionar Actividad --</option>');
  onModalBrandChange();
}

function onModalBrandChange() {
  const brand = $('#modal-pool-brand').val();
  let activitySelect = $('#modal-pool-activity');
  activitySelect.html('<option value="">-- Seleccionar Actividad --</option>');
  
  if (brand) {
    // Find unique activities for this brand from active items
    const activities = [...new Set(servicePool
      .filter(x => (x.marca_categoria || '').trim() === brand.trim() && x.activo != 0)
      .map(x => x.actividad)
    )].sort();
    
    activities.forEach(act => {
      const key = `${brand}|${act}`;
      const actCode = activityCodesMap[key] || '';
      const displayText = actCode ? `[${actCode}] ${act}` : act;
      $('<option>')
        .val(act)
        .text(displayText)
        .appendTo(activitySelect);
    });
  }
  
  filterModalPool();
}

function toggleAllModalPool(cb) {
  $('.modal-pool-cb:visible').prop('checked', cb.checked);
}

function filterModalPool() {
  const brand = $('#modal-pool-brand').val();
  const activity = $('#modal-pool-activity').val();
  const search = $('#modal-pool-search').val().toLowerCase();
  
  let html = '';
  let matchesCount = 0;
  
  servicePool.forEach(p => {
    if (p.activo == 0) return;
    
    const pBrand = (p.marca_categoria || '').trim();
    const pActivity = (p.actividad || '').trim();
    
    // Filter by brand if selected
    if (brand && pBrand !== brand.trim()) return;
    
    // Filter by activity if selected
    if (activity && pActivity !== activity.trim()) return;
    
    const key = `${pBrand}|${pActivity}`;
    const activityCode = activityCodesMap[key] || '';
    
    let recs = [];
    if (p.n1) recs.push('N1');
    if (p.n2) recs.push('N2');
    if (p.n3) recs.push('N3');
    if (p.e1) recs.push('E1');
    if (p.e2) recs.push('E2');
    const recsText = recs.length > 0 ? recs.join(', ') : '-';
    
    const textToSearch = `${activityCode.toLowerCase()} ${pBrand.toLowerCase()} ${pActivity.toLowerCase()} ${(p.detalle || '').toLowerCase()}`;
    if (search && textToSearch.indexOf(search) === -1) return;
    
    matchesCount++;
    html += `
      <tr class="modal-pool-row" data-brand="${escapeHtml(p.marca_categoria)}" data-activity="${escapeHtml(p.actividad)}">
        <td class="text-center"><input type="checkbox" class="modal-pool-cb" value="${p.id}"></td>
        <td><span class="badge badge-secondary text-monospace">${escapeHtml(activityCode)}</span></td>
        <td><strong>${escapeHtml(p.marca_categoria)}</strong></td>
        <td>${escapeHtml(p.actividad)}</td>
        <td>${escapeHtml(p.detalle)}</td>
        <td><span class="badge badge-light">${recsText}</span></td>
        <td>${parseFloat(p.horas_laborables).toFixed(1)}h</td>
        <td>${parseFloat(p.horas_no_laborables_50 || 0).toFixed(1)}h</td>
        <td>${parseFloat(p.horas_no_laborables_100 || 0).toFixed(1)}h</td>
      </tr>
    `;
  });
  
  if (matchesCount === 0) {
    html = '<tr><td colspan="9" class="text-center text-muted py-3">No se encontraron tareas con los filtros aplicados.</td></tr>';
  }
  
  $('#modal-pool-body').html(html);
}

function addSelectedServicesToSection() {
  const section = $('#pool-target-section').val();
  const selectedIds = [];
  $('.modal-pool-cb:checked').each(function() {
    selectedIds.push(parseInt($(this).val()));
  });
  
  if (selectedIds.length === 0) {
    Swal.fire('Atención', 'Seleccione al menos una actividad para agregar.', 'warning');
    return;
  }
  
  selectedIds.forEach(id => {
    const p = servicePool.find(x => x.id == id);
    if (!p) return;
    
    const key = `${p.marca_categoria}|${p.actividad}`;
    const activityCode = activityCodesMap[key] || '';
    
    // Determine default specialist from recommendations
    let defaultLevel = 'N2';
    if (p.n2) defaultLevel = 'N2';
    else if (p.n1) defaultLevel = 'N1';
    else if (p.n3) defaultLevel = 'N3';
    else if (p.e1) defaultLevel = 'E1';
    else if (p.e2) defaultLevel = 'E2';
    
    const item = {
      pool_id: p.id,
      codigo_unico: activityCode || null,
      marca_categoria: p.marca_categoria,
      actividad: p.actividad,
      detalle: p.detalle,
      especialista_nivel: defaultLevel,
      horas_laborables: parseFloat(p.horas_laborables),
      horas_no_laborables_50: parseFloat(p.horas_no_laborables_50),
      horas_no_laborables_100: parseFloat(p.horas_no_laborables_100),
      observaciones: p.observaciones || ''
    };
    
    addedItems[section].push(item);
  });
  
  $('#poolSelectionModal').modal('hide');
  renderSectionTable(section);
  recalculateAll();
}

// -------------------------------------------------------------
// RENDERING WORKBOOK SHEETS IN DESIGNER
// -------------------------------------------------------------
// Color palette for brand/category separators (cycles through if many brands)
const BRAND_COLORS = [
  { bg: '#e8f4ff', border: '#4a9eff', text: '#1a5fa8', badge: '#4a9eff' },
  { bg: '#fff3e8', border: '#ff8c42', text: '#a8521a', badge: '#ff8c42' },
  { bg: '#e8fff0', border: '#42c46a', text: '#1a8a44', badge: '#42c46a' },
  { bg: '#f5e8ff', border: '#a042ff', text: '#6a1aa8', badge: '#a042ff' },
  { bg: '#fff8e8', border: '#ffc842', text: '#a87e1a', badge: '#ffc842' },
  { bg: '#ffe8ef', border: '#ff4276', text: '#a81a40', badge: '#ff4276' },
  { bg: '#e8fffe', border: '#42d4d4', text: '#1a7a7a', badge: '#42d4d4' },
  { bg: '#fff0f0', border: '#ff6b6b', text: '#a83030', badge: '#ff6b6b' },
];

function renderSectionTable(section) {
  const tableMap = {
    'Implementacion': 'table-impl-items',
    'MantPrev': 'table-prev-items',
    'MantCorr': 'table-corr-items',
    'BolsaHoras': 'table-bolsa-items'
  };
  const tableId = tableMap[section] || `table-${section.toLowerCase()}-items`;
  const tbody = $(`#${tableId} tbody`);
  let html = '';
  
  if (addedItems[section].length === 0) {
    html = '<tr><td colspan="14" class="text-center text-muted py-3">No hay actividades agregadas a esta sección.</td></tr>';
    tbody.html(html);
    return;
  }
  
  // Build ordered brand map to assign consistent colors
  const brandOrder = [];
  addedItems[section].forEach(item => {
    const brand = item.marca_categoria || 'Sin categoría';
    if (!brandOrder.includes(brand)) brandOrder.push(brand);
  });
  const brandColorMap = {};
  brandOrder.forEach((brand, i) => {
    brandColorMap[brand] = BRAND_COLORS[i % BRAND_COLORS.length];
  });

  // Pre-compute counts: brand → total tasks, brand+activity → tasks in that activity
  const brandTaskCount    = {};
  const activityTaskCount = {};
  // Build unique group IDs for collapsing
  const brandGroupIds    = {};
  const activityGroupIds = {};
  let bgIdCounter = 0;
  addedItems[section].forEach(item => {
    const b = item.marca_categoria || 'Sin categoría';
    const a = item.actividad       || 'Sin actividad';
    brandTaskCount[b] = (brandTaskCount[b] || 0) + 1;
    const key = `${b}||${a}`;
    activityTaskCount[key] = (activityTaskCount[key] || 0) + 1;
    if (!brandGroupIds[b]) { brandGroupIds[b] = `bg-${section}-${bgIdCounter++}`; }
    if (!activityGroupIds[key]) { activityGroupIds[key] = `ag-${section}-${bgIdCounter++}`; }
  });

  let lastBrand    = null;
  let lastActivity = null;

  addedItems[section].forEach((item, index) => {
    const currentBrand    = item.marca_categoria || 'Sin categoría';
    const currentActivity = item.actividad       || 'Sin actividad';
    const colors          = brandColorMap[currentBrand];
    const bgId  = brandGroupIds[currentBrand];
    const actKey = `${currentBrand}||${currentActivity}`;
    const agId  = activityGroupIds[actKey];

    // ── LEVEL 1: Brand separator — gradient, large text, collapsible ───
    if (currentBrand !== lastBrand) {
      const totalTasks = brandTaskCount[currentBrand] || 0;
      html += `
        <tr class="brand-separator-row brand-sep-l1 sep-collapsible" 
            data-group="${bgId}" 
            data-collapsed="0"
            onclick="toggleSepGroup('${bgId}', this)"
            style="background: linear-gradient(135deg, ${colors.border}22 0%, ${colors.badge}44 50%, ${colors.border}22 100%);
                   border-left: 5px solid ${colors.badge}; cursor:pointer;">
          <td colspan="14" style="padding:11px 18px; border-left:5px solid ${colors.badge};">
            <div class="d-flex align-items-center justify-content-between">
              <div class="d-flex align-items-center">
                <span class="sep-chevron mr-2" style="color:${colors.text};font-size:0.9rem;transition:transform 0.25s;">
                  <i class="fas fa-chevron-down"></i>
                </span>
                <span style="display:inline-block;width:13px;height:13px;border-radius:50%;
                             background:${colors.badge};margin-right:10px;
                             box-shadow:0 0 0 2px rgba(255,255,255,0.8),0 0 0 4px ${colors.badge}44;"></span>
                <strong style="color:${colors.text};font-size:1rem;letter-spacing:0.08em;text-transform:uppercase;text-shadow:0 1px 2px rgba(0,0,0,0.08);">
                  ${escapeHtml(currentBrand)}
                </strong>
              </div>
              <span style="background:${colors.badge};color:#fff;font-size:0.74rem;padding:3px 12px;border-radius:14px;font-weight:700;letter-spacing:0.04em;box-shadow:0 2px 6px ${colors.badge}55;">
                ${totalTasks} tarea${totalTasks !== 1 ? 's' : ''}
              </span>
            </div>
          </td>
        </tr>
      `;
      lastBrand    = currentBrand;
      lastActivity = null;
    }

    // ── LEVEL 2: Activity sub-separator — gradient tint, medium text, collapsible ──
    if (currentActivity !== lastActivity) {
      const tasksInAct = activityTaskCount[actKey] || 0;
      html += `
        <tr class="brand-separator-row brand-sep-l2 sep-collapsible"
            data-brand-group="${bgId}"
            data-group="${agId}"
            data-collapsed="0"
            onclick="toggleSepGroup('${agId}', this)"
            style="background: linear-gradient(135deg, ${colors.border}14 0%, ${colors.badge}28 60%, ${colors.border}14 100%);
                   border-left: 3px solid ${colors.border}; cursor:pointer;">
          <td colspan="14" style="padding:7px 18px 7px 36px; border-left:3px solid ${colors.border};">
            <div class="d-flex align-items-center justify-content-between">
              <div class="d-flex align-items-center">
                <span class="sep-chevron mr-2" style="color:${colors.text};font-size:0.78rem;transition:transform 0.25s;">
                  <i class="fas fa-chevron-down"></i>
                </span>
                <i class="fas fa-layer-group mr-2" style="color:${colors.border};font-size:0.8rem;opacity:0.9;"></i>
                <span style="color:${colors.text};font-size:0.9rem;font-weight:700;letter-spacing:0.02em;">
                  ${escapeHtml(currentActivity)}
                </span>
              </div>
              <span style="background:${colors.border};color:#fff;font-size:0.68rem;padding:2px 9px;border-radius:10px;font-weight:700;opacity:0.9;">
                ${tasksInAct} tarea${tasksInAct !== 1 ? 's' : ''}
              </span>
            </div>
          </td>
        </tr>
      `;
      lastActivity = currentActivity;
    }
    // Render the level options dynamically from the loaded specialists
    let specialistOptionsHtml = '';
    const uniqueTypes = [];
    const seenTypes = new Set();
    specialists.forEach(sp => {
      if (!seenTypes.has(sp.tipo)) {
        seenTypes.add(sp.tipo);
        uniqueTypes.push(sp);
      }
    });
    
    // Fallback if specialists not loaded yet or empty
    if (uniqueTypes.length === 0) {
      const fallbacks = [
        { tipo: 'N1', name: 'N1 Junior', range: '1200 - 1400' },
        { tipo: 'N2', name: 'N2 Intermedio', range: '1500 - 2200' },
        { tipo: 'N3', name: 'N3 Senior', range: '2500 - 3500' },
        { tipo: 'E1', name: 'E1 Externo 1', range: 'Tarifa fija' },
        { tipo: 'E2', name: 'E2 Externo 2', range: 'Tarifa fija' },
        { tipo: 'BOC', name: 'GESTION BOC', range: '1000 - 1300' },
        { tipo: 'GP1', name: 'Gestión de Proyecto 1', range: '1500 - 1800' },
        { tipo: 'GP2', name: 'Gestión de Proyecto 2', range: '2200 - 3000' }
      ];
      fallbacks.forEach(fb => {
        const isSel = item.especialista_nivel === fb.tipo ? 'selected' : '';
        const rangeFormatted = fb.range === 'Tarifa fija' ? 'Tarifa fija' : `$${fb.range}`;
        specialistOptionsHtml += `<option value="${fb.tipo}" ${isSel}>${fb.name} (${rangeFormatted})</option>`;
      });
    } else {
      uniqueTypes.forEach(sp => {
        const isSel = item.especialista_nivel === sp.tipo ? 'selected' : '';
        const rangeFormatted = sp.rango_salarial ? (sp.rango_salarial.includes('$') ? sp.rango_salarial : `$${sp.rango_salarial}`) : 'Tarifa Fija';
        specialistOptionsHtml += `<option value="${escapeHtml(sp.tipo)}" ${isSel}>${escapeHtml(sp.nombre)} [${escapeHtml(sp.tipo)}] (${escapeHtml(rangeFormatted)})</option>`;
      });
    }
    
    const multType = item.multiplier_type || 'Ninguno';
    
    // Build dynamic multiplier options from DB categories
    let multOptionsHtml = `<option value="Ninguno" ${multType === 'Ninguno' ? 'selected' : ''}>Ninguno (1x)</option>`;
    equipmentCategories.forEach(cat => {
      const sel = multType === cat.name ? 'selected' : '';
      multOptionsHtml += `<option value="${escapeHtml(cat.name)}" ${sel}>${escapeHtml(cat.name)}</option>`;
    });
    // Fallback: if DB empty show original hardcoded set
    if (equipmentCategories.length === 0) {
      const fallbackCats = ['Core','Distribución','Acceso','WLC','AP','Blades','Chasis UCS-X','Fabric','VMware','Intersight'];
      fallbackCats.forEach(c => {
        multOptionsHtml += `<option value="${c}" ${multType===c?'selected':''}>${c}</option>`;
      });
    }

    const currentBrand2 = item.marca_categoria || 'Sin categoría';
    const rowColors = brandColorMap[currentBrand2];
    const rowBorderStyle = `border-left: 3px solid ${rowColors.border};`;

    html += `
      <tr class="activity-task-row" data-brand-group="${bgId}" data-activity-group="${agId}" data-index="${index}" style="${rowBorderStyle}">
        <td><span class="badge badge-secondary text-monospace">${escapeHtml(item.codigo_unico || 'Manual')}</span></td>
        <td><span class="badge" style="background:${rowColors.badge};color:#fff;">${escapeHtml(currentBrand2)}</span></td>
        <td>${escapeHtml(item.actividad)}</td>
        <td>${escapeHtml(item.detalle)}</td>
        <td>
          <select class="form-control form-control-sm mult-type-select" onchange="updateItemField('${section}', ${index}, 'multiplier_type', this.value)">
            ${multOptionsHtml}
          </select>
        </td>
        <td>
          <select class="form-control form-control-sm sp-level-select" onchange="updateItemField('${section}', ${index}, 'especialista_nivel', this.value)">
            ${specialistOptionsHtml}
          </select>
          <div class="small text-center mt-1" id="${section}-rate-display-${index}" style="font-size: 78%; font-weight: 500;">
            <!-- Populated by recalculateAll -->
          </div>
        </td>
        <td>
          <input type="number" step="0.1" class="form-control form-control-sm text-center" value="${item.horas_laborables}" onchange="updateItemField('${section}', ${index}, 'horas_laborables', this.value)">
        </td>
        <td>
          <input type="number" step="0.1" class="form-control form-control-sm text-center" value="${item.horas_no_laborables_50}" onchange="updateItemField('${section}', ${index}, 'horas_no_laborables_50', this.value)">
        </td>
        <td>
          <input type="number" step="0.1" class="form-control form-control-sm text-center" value="${item.horas_no_laborables_100}" onchange="updateItemField('${section}', ${index}, 'horas_no_laborables_100', this.value)">
        </td>
        <td class="text-right text-muted" id="${section}-cost-u-${index}">$0.00</td>
        <td class="text-right text-muted" id="${section}-pvp-u-${index}">$0.00</td>
        <td class="text-right font-weight-bold" id="${section}-cost-t-${index}">$0.00</td>
        <td class="text-right font-weight-bold text-success" id="${section}-pvp-t-${index}">$0.00</td>
        <td class="text-center">
          <button type="button" class="btn btn-xs btn-outline-danger" onclick="removeItemFromSection('${section}', ${index})"><i class="fas fa-trash"></i></button>
        </td>
      </tr>
    `;
  });
  
  tbody.html(html);
}

function updateItemField(section, index, field, value) {
  if (field === 'horas_laborables' || field === 'horas_no_laborables_50' || field === 'horas_no_laborables_100') {
    addedItems[section][index][field] = parseFloat(value) || 0;
    
    // If we update horas_laborables, automatically evaluate 50% and 100% if they were 0 or formulaic
    if (field === 'horas_laborables') {
      addedItems[section][index]['horas_no_laborables_50'] = Math.round(parseFloat(value) * 0.5 * 10) / 10;
      addedItems[section][index]['horas_no_laborables_100'] = Math.round(parseFloat(value) * 2.0 * 10) / 10;
      renderSectionTable(section);
    }
  } else {
    addedItems[section][index][field] = value;
  }
  recalculateAll();
}

function removeItemFromSection(section, index) {
  addedItems[section].splice(index, 1);
  renderSectionTable(section);
  recalculateAll();
}

function toggleSepGroup(groupId, element) {
  const row = $(element);
  const isCollapsed = row.attr('data-collapsed') === '1';
  const newCollapsed = isCollapsed ? '0' : '1';
  row.attr('data-collapsed', newCollapsed);
  
  // Rotar chevron
  const chevron = row.find('.sep-chevron');
  if (newCollapsed === '1') {
    chevron.css('transform', 'rotate(-90deg)');
  } else {
    chevron.css('transform', 'rotate(0deg)');
  }

  const tbody = row.closest('tbody');

  if (groupId.startsWith('bg-')) {
    if (newCollapsed === '1') {
      // Ocultar sub-separadores L2 y sus tareas asociadas
      tbody.find(`tr.brand-sep-l2[data-brand-group="${groupId}"]`).hide();
      tbody.find(`tr.activity-task-row[data-brand-group="${groupId}"]`).hide();
    } else {
      // Mostrar sub-separadores L2
      tbody.find(`tr.brand-sep-l2[data-brand-group="${groupId}"]`).each(function() {
        const l2Row = $(this);
        l2Row.show();
        const l2GroupId = l2Row.attr('data-group');
        // Si el sub-separador L2 no está colapsado, mostramos sus tareas
        if (l2Row.attr('data-collapsed') !== '1') {
          tbody.find(`tr.activity-task-row[data-activity-group="${l2GroupId}"]`).show();
        }
      });
    }
  } else if (groupId.startsWith('ag-')) {
    if (newCollapsed === '1') {
      tbody.find(`tr.activity-task-row[data-activity-group="${groupId}"]`).hide();
    } else {
      const brandGroupId = row.attr('data-brand-group');
      const parentL1 = tbody.find(`tr.brand-sep-l1[data-group="${brandGroupId}"]`);
      if (parentL1.attr('data-collapsed') !== '1') {
        tbody.find(`tr.activity-task-row[data-activity-group="${groupId}"]`).show();
      }
    }
  }
}

function addEquipmentInventoryRow(type = '', qty = 0) {
  // Use DB-driven categories; fall back to hardcoded if not loaded yet
  const opts = equipmentCategories.length > 0
    ? equipmentCategories.map(c => c.name)
    : ['Core','Distribución','Acceso','WLC','AP','Blades','Chasis UCS-X','Fabric','VMware','Intersight'];
  const defaultType = type || (opts[0] || 'Acceso');
  let selectHtml = `<select class="form-control eq-type-select" onchange="recalculateAll()">`;
  opts.forEach(opt => {
    selectHtml += `<option value="${escapeHtml(opt)}" ${opt === defaultType ? 'selected' : ''}>${escapeHtml(opt)}</option>`;
  });
  selectHtml += `</select>`;
  
  const rowHtml = `
    <tr>
      <td>${selectHtml}</td>
      <td>
        <input type="number" min="0" class="form-control eq-qty-input text-center font-weight-bold" value="${qty}" onchange="recalculateAll()">
      </td>
      <td class="text-center">
        <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeEquipmentInventoryRow(this)">
          <i class="fas fa-trash-alt"></i>
        </button>
      </td>
    </tr>
  `;
  $('#eq-inventory-tbody').append(rowHtml);
  recalculateAll();
}

function removeEquipmentInventoryRow(btn) {
  $(btn).closest('tr').remove();
  // Ensure there is at least one row
  if ($('#eq-inventory-tbody tr').length === 0) {
    addEquipmentInventoryRow('Acceso', 0);
  }
  recalculateAll();
}

function getEquipmentQuantities() {
  const qties = {
    core: 0,
    dist: 0,
    access: 0,
    wlc: 0,
    ap: 0,
    blades: 0,
    chasis: 0,
    fabric: 0,
    vmware: 0,
    intersight: 0
  };
  
  // Build a dynamic map: category name => total quantity
  const categoryTotals = {};
  $('#eq-inventory-tbody tr').each(function() {
    const type = $(this).find('.eq-type-select').val();
    const qty = parseInt($(this).find('.eq-qty-input').val()) || 0;
    if (type) {
      categoryTotals[type] = (categoryTotals[type] || 0) + qty;
    }
  });

  // Legacy keys for backward compat
  return {
    core:       categoryTotals['Core'] || 0,
    dist:       categoryTotals['Distribución'] || 0,
    access:     categoryTotals['Acceso'] || 0,
    wlc:        categoryTotals['WLC'] || 0,
    ap:         categoryTotals['AP'] || 0,
    blades:     categoryTotals['Blades'] || 0,
    chasis:     categoryTotals['Chasis UCS-X'] || 0,
    fabric:     categoryTotals['Fabric'] || 0,
    vmware:     categoryTotals['VMware'] || 0,
    intersight: categoryTotals['Intersight'] || 0,
    _raw:       categoryTotals  // full dynamic map
  };
}

// -------------------------------------------------------------
// CALCULATIONS / MATH ENGINE
// -------------------------------------------------------------
// Helper to fetch multiplier value from category quantities inputs — fully dynamic
function getMultiplierValue(multType) {
  if (!multType || multType === 'Ninguno') return 1;
  const qties = getEquipmentQuantities();
  // Check dynamic map first (supports any DB category)
  if (qties._raw && qties._raw[multType] !== undefined) {
    return qties._raw[multType] || 1;
  }
  // Legacy fallback
  switch (multType) {
    case 'Core':        return qties.core;
    case 'Distribución':return qties.dist;
    case 'Acceso':      return qties.access;
    case 'WLC':         return qties.wlc;
    case 'AP':          return qties.ap;
    case 'Blades':      return qties.blades;
    case 'Chasis UCS-X':return qties.chasis;
    case 'Fabric':      return qties.fabric;
    case 'VMware':      return qties.vmware;
    case 'Intersight':  return qties.intersight;
    default:            return 1;
  }
}

function recalculateAll() {
  const globalMarginPct = (parseFloat($('#editor-margen-global').val()) || 0) / 100;
  
  // We fetch rate values based on selected specialists
  const getRates = (level) => {
    // Find a specialist of this level
    const sp = specialists.find(x => x.tipo === level);
    if (sp) {
      // Recalculate rates dynamically if margin is changed
      const cost_lab = parseFloat(sp.costo_hora_lab);
      const pvp_lab = cost_lab / (1 - globalMarginPct);
      return {
        cost: cost_lab,
        pvp: pvp_lab,
        cost_50: cost_lab * 1.5,
        pvp_50: (cost_lab * 1.5) / (1 - globalMarginPct),
        cost_100: cost_lab * 2.0,
        pvp_100: (cost_lab * 2.0) / (1 - globalMarginPct)
      };
    }
    return { cost: 0, pvp: 0, cost_50: 0, pvp_50: 0, cost_100: 0, pvp_100: 0 };
  };

  // Update global level dropdown rate badges
  const ktLevel = $('#impl-kt-level').val();
  if (ktLevel) {
    const ktRates = getRates(ktLevel);
    $('#impl-kt-level-rate-badge').text('PVP: $' + ktRates.pvp.toFixed(2) + '/h');
  }

  const bocLevel = $('#impl-boc-level').val();
  if (bocLevel) {
    const bocRates = getRates(bocLevel);
    $('#impl-boc-level-rate-badge').text('PVP: $' + bocRates.pvp.toFixed(2) + '/h');
  }

  const pmLevel = $('#impl-pm-level').val();
  if (pmLevel) {
    const pmRates = getRates(pmLevel);
    $('#impl-pm-level-rate-badge').text('PVP: $' + pmRates.pvp.toFixed(2) + '/h');
  }

  const cLevel = $('#corr-case-level').val();
  if (cLevel) {
    const corrRates = getRates(cLevel);
    $('#corr-case-level-rate-badge').text('PVP: $' + corrRates.pvp.toFixed(2) + '/h');
  }
  
  // Recalculate sections
  const calcSection = (section) => {
    let totH_lab = 0;
    let totH_50 = 0;
    let totH_100 = 0;
    let totCost = 0;
    let totPvp = 0;
    
    addedItems[section].forEach((item, index) => {
      const rates = getRates(item.especialista_nivel);
      const multiplier = getMultiplierValue(item.multiplier_type || 'Ninguno');
      
      const unitRowCost = (item.horas_laborables * rates.cost) + 
                          (item.horas_no_laborables_50 * rates.cost_50) + 
                          (item.horas_no_laborables_100 * rates.cost_100);
                          
      const unitRowPvp = (item.horas_laborables * rates.pvp) + 
                         (item.horas_no_laborables_50 * rates.pvp_50) + 
                         (item.horas_no_laborables_100 * rates.pvp_100);
                         
      const rowCost = unitRowCost * multiplier;
      const rowPvp = unitRowPvp * multiplier;
      
      totH_lab += item.horas_laborables * multiplier;
      totH_50 += item.horas_no_laborables_50 * multiplier;
      totH_100 += item.horas_no_laborables_100 * multiplier;
      
      totCost += rowCost;
      totPvp += rowPvp;
      
      // Update row visual display
      $(`#${section}-cost-u-${index}`).text('$' + unitRowCost.toFixed(2));
      $(`#${section}-pvp-u-${index}`).text('$' + unitRowPvp.toFixed(2));
      $(`#${section}-cost-t-${index}`).text('$' + rowCost.toFixed(2));
      $(`#${section}-pvp-t-${index}`).text('$' + rowPvp.toFixed(2));
      
      // Dynamic hourly rate display
      $(`#${section}-rate-display-${index}`).html(
        `<span class="badge badge-light border text-muted">C: $${rates.cost.toFixed(2)}/h</span><br>` + 
        `<span class="badge badge-success mt-1">PVP: $${rates.pvp.toFixed(2)}/h</span>`
      );
    });
    
    // Update totals
    const prefixMap = {
      'Implementacion': 'impl',
      'MantPrev': 'prev',
      'MantCorr': 'corr',
      'BolsaHoras': 'bolsa'
    };
    const prefix = prefixMap[section] || section.toLowerCase();
    
    $(`#${prefix}-tot-h-lab`).text(totH_lab.toFixed(1));
    $(`#${prefix}-tot-h-50`).text(totH_50.toFixed(1));
    $(`#${prefix}-tot-h-100`).text(totH_100.toFixed(1));
    $(`#${prefix}-tot-cost-serv`).text('$' + totCost.toFixed(2));
    $(`#${prefix}-tot-pvp-serv`).text('$' + totPvp.toFixed(2));
    
    return { cost: totCost, pvp: totPvp, h_lab: totH_lab, h_50: totH_50, h_100: totH_100 };
  };
  
  // 1. IMPLEMENTATION CALCULATIONS
  const implBase = calcSection('Implementacion');
  
  // Risk & Knowledge Transfer
  const riskPct = (parseFloat($('#impl-risk-pct').val()) || 0) / 100;
  
  // Dynamic KT Calculation
  const ktIncluye = $('#impl-kt-incluye').val();
  let ktHours = 0;
  let ktCost = 0;
  let ktPvp = 0;
  let breaksCost = 0;
  
  if (ktIncluye === 'Si') {
    const ktPersonas = parseInt($('#impl-kt-personas').val()) || 0;
    const ktDias = parseInt($('#impl-kt-dias').val()) || 0;
    const ktHorasDia = parseInt($('#impl-kt-horas-dia').val()) || 0;
    const ktLevel = $('#impl-kt-level').val();
    const ktBreaksUnit = parseFloat($('#impl-kt-breaks-unit').val()) || 0;
    
    ktHours = ktDias * ktHorasDia;
    const ktRates = getRates(ktLevel);
    ktCost = ktHours * ktRates.cost;
    ktPvp = ktHours * ktRates.pvp;
    breaksCost = ktPersonas * ktDias * ktBreaksUnit;
    
    // Update UI elements in panel
    $('#lbl-kt-total-hours').text(ktHours + ' Horas');
    $('#lbl-kt-breaks-cost').text('$' + breaksCost.toFixed(2));
    $('#lbl-kt-service-pvp').text('$' + ktPvp.toFixed(2));
    
    // Update hidden elements for DB saving consistency
    $('#impl-kt-hours').val(ktHours);
    $('#impl-breaks-cost').val(breaksCost);
  } else {
    // Reset labels and hidden fields
    $('#lbl-kt-total-hours').text('0 Horas');
    $('#lbl-kt-breaks-cost').text('$0.00');
    $('#lbl-kt-service-pvp').text('$0.00');
    $('#impl-kt-hours').val(0);
    $('#impl-breaks-cost').val(0);
  }
  
  // Project Risk calculation (10% of total implementation hours)
  const riskHours = implBase.h_lab * riskPct;
  const riskCost = riskHours * getRates('N2').cost; // Default risk is estimated using N2 rates
  const riskPvp = riskCost / (1 - globalMarginPct);
  
  // Overheads / Adicionales
  const travelNights = parseInt($('#impl-travel-nights').val()) || 0;
  const travelCostPerNight = parseFloat($('#impl-travel-cost-night').val()) || 0;
  const travelCost = travelNights * travelCostPerNight;
  
  const flightsQty = parseInt($('#impl-flights-qty').val()) || 0;
  const flightCost = parseFloat($('#impl-flight-cost').val()) || 150;
  const flightsCost = flightsQty * flightCost;
  
  // PSS
  const pssVal = parseFloat($('#impl-pss-val').val()) || 0;
  
  // Ext support
  const extProvCost = parseFloat($('#impl-ext-prov-cost').val()) || 0;
  const extProvPvp = parseFloat($('#impl-ext-prov-pvp').val()) || 0;
  
  // Consumibles
  const consumablesScrews = parseFloat($('#impl-consumables-screws').val()) || 0;
  const consumablesLabels = parseFloat($('#impl-consumables-labels').val()) || 0;
  const consumablesVaccines = parseFloat($('#impl-consumables-vaccines').val()) || 0;
  const consumablesEpp = parseFloat($('#impl-consumables-epp').val()) || 0;
  
  // BOC post-implem
  const bocMonths = parseInt($('#impl-boc-months').val()) || 0;
  const bocHours = parseFloat($('#impl-boc-hours').val()) || 0;
  const implBocLevel = $('#impl-boc-level').val();
  const bocRates = getRates(implBocLevel);
  const bocCost = bocMonths * bocHours * bocRates.cost;
  const bocPvp = bocMonths * bocHours * bocRates.pvp;
  
  // PM post-implem
  const pmMonths = parseInt($('#impl-pm-months').val()) || 0;
  const pmHours = parseFloat($('#impl-pm-hours').val()) || 0;
  const implPmLevel = $('#impl-pm-level').val();
  const pmRates = getRates(implPmLevel);
  const pmCost = pmMonths * pmHours * pmRates.cost;
  const pmPvp = pmMonths * pmHours * pmRates.pvp;
  
  // SUM FOR IMPLEMENTATION
  const totalImplCost = implBase.cost + ktCost + riskCost + travelCost + flightsCost + breaksCost + pssVal + extProvCost + consumablesScrews + consumablesLabels + consumablesVaccines + consumablesEpp + bocCost + pmCost;
  const totalImplPvp = implBase.pvp + ktPvp + riskPvp + travelCost + flightsCost + breaksCost + pssVal + extProvPvp + consumablesScrews + consumablesLabels + consumablesVaccines + consumablesEpp + bocPvp + pmPvp;
  
  // 2. PREVENTIVE CALCULATIONS
  const prevBase = calcSection('MantPrev');
  
  const prevNights = parseInt($('#prev-travel-nights').val()) || 0;
  const prevNightsCost = prevNights * travelCostPerNight;
  const prevFlights = parseInt($('#prev-flights-qty').val()) || 0;
  const prevFlightsCost = prevFlights * flightCost;
  const prevMaterials = parseFloat($('#prev-materials-cost').val()) || 0;
  const prevPss = parseFloat($('#prev-pss-cost').val()) || 0;
  
  const totalPrevCost = prevBase.cost + prevNightsCost + prevFlightsCost + prevMaterials + prevPss;
  const totalPrevPvp = prevBase.pvp + prevNightsCost + prevFlightsCost + prevMaterials + prevPss;
  
  // 3. CORRECTIVE CALCULATIONS
  let totalCorrCost = 0;
  let totalCorrPvp = 0;
  
  const corrMethod = $('input[name="corr-option"]:checked').val();
  if (corrMethod === 'hours') {
    $('#corr-div-hours').removeClass('d-none');
    $('#corr-div-cases').addClass('d-none');
    
    const corrBase = calcSection('MantCorr');
    const corrNights = parseInt($('#corr-travel-nights').val()) || 0;
    const corrNightsCost = corrNights * travelCostPerNight;
    const corrFlights = parseInt($('#corr-flights-qty').val()) || 0;
    const corrFlightsCost = corrFlights * flightCost;
    const corrMaterials = parseFloat($('#corr-materials-cost').val()) || 0;
    const corrPss = parseFloat($('#corr-pss-cost').val()) || 0;
    
    totalCorrCost = corrBase.cost + corrNightsCost + corrFlightsCost + corrMaterials + corrPss;
    totalCorrPvp = corrBase.pvp + corrNightsCost + corrFlightsCost + corrMaterials + corrPss;
  } else {
    $('#corr-div-hours').addClass('d-none');
    $('#corr-div-cases').removeClass('d-none');
    
    const nEquipos = parseInt($('#corr-case-equipos').val()) || 0;
    const dmgPct = (parseFloat($('#corr-case-dmg-pct').val()) || 0) / 100;
    const casesCalc = Math.ceil(nEquipos * dmgPct);
    $('#corr-case-cases-calc').val(casesCalc);
    
    const cYears = parseInt($('#corr-case-years').val()) || 1;
    const cHours = parseFloat($('#corr-case-hours-per-case').val()) || 0;
    const cLevel = $('#corr-case-level').val();
    
    const cRates = getRates(cLevel);
    const singleCaseCost = cHours * cRates.cost;
    const singleCasePvp = singleCaseCost / (1 - globalMarginPct);
    
    const casesCostVal = casesCalc * cYears * singleCaseCost;
    const casesPvpVal = casesCalc * cYears * singleCasePvp;
    
    const movCostPerTrip = parseFloat($('#corr-case-mov-cost').val()) || 0;
    const movPvpPerTrip = parseFloat($('#corr-case-mov-pvp').val()) || 0;
    const movTotCost = casesCalc * cYears * movCostPerTrip;
    const movTotPvp = casesCalc * cYears * movPvpPerTrip;
    
    $('#corr-case-total-cost').val(casesCostVal.toFixed(2));
    $('#corr-case-total-pvp').val(casesPvpVal.toFixed(2));
    $('#corr-case-mov-tot-cost').val(movTotCost.toFixed(2));
    $('#corr-case-mov-tot-pvp').val(movTotPvp.toFixed(2));
    
    totalCorrCost = casesCostVal + movTotCost;
    totalCorrPvp = casesPvpVal + movTotPvp;
  }
  
  // 4. BOLSA DE HORAS
  const bolsaBase = calcSection('BolsaHoras');
  const bolsaExtraTravel = parseInt($('#bolsa-travel-extra').val()) || 0;
  const bolsaExtraTravelCost = bolsaExtraTravel * travelCostPerNight;
  const bolsaFlights = parseInt($('#bolsa-flights-qty').val()) || 0;
  const bolsaFlightsCost = bolsaFlights * flightCost;
  
  const totalBolsaCost = bolsaBase.cost + bolsaExtraTravelCost + bolsaFlightsCost;
  const totalBolsaPvp = bolsaBase.pvp + bolsaExtraTravelCost + bolsaFlightsCost;
  
  // 5. SUMMARY PERSISTENCE
  const updateSummaryRow = (idPrefix, cost, pvp) => {
    const rent = pvp - cost;
    const marg = pvp > 0 ? (rent / pvp) * 100 : 0;
    
    $(`#sum-${idPrefix}-cost`).text('$' + cost.toFixed(2));
    $(`#sum-${idPrefix}-pvp`).text('$' + pvp.toFixed(2));
    $(`#sum-${idPrefix}-rent`).text('$' + rent.toFixed(2));
    $(`#sum-${idPrefix}-marg`).text(Math.round(marg) + '%');
  };
  
  updateSummaryRow('impl', totalImplCost, totalImplPvp);
  updateSummaryRow('prev', totalPrevCost, totalPrevPvp);
  updateSummaryRow('corr', totalCorrCost, totalCorrPvp);
  updateSummaryRow('bolsa', totalBolsaCost, totalBolsaPvp);
  
  const grandCost = totalImplCost + totalPrevCost + totalCorrCost + totalBolsaCost;
  const grandPvp = totalImplPvp + totalPrevPvp + totalCorrPvp + totalBolsaPvp;
  updateSummaryRow('tot', grandCost, grandPvp);
}

// -------------------------------------------------------------
// SAVE / PERSISTENCE WORKFLOW
// -------------------------------------------------------------
function startNewQuote() {
  $('#editor-quote-id').val(0);
  $('#editor-parent-id').val('');
  $('#editor-version').val(1);
  $('#editor-cliente').val('');
  $('#editor-contrato').val('');
  $('#editor-observaciones').val('');
  $('#editor-version-badge').text('');
  
  // Reset equipment quantities dynamic table
  $('#eq-inventory-tbody').empty();
  addEquipmentInventoryRow('Acceso', 0);
  
  // Reset KT inputs
  $('#impl-kt-incluye').val('No');
  $('#impl-kt-personas').val(2);
  $('#impl-kt-dias').val(2);
  $('#impl-kt-horas-dia').val(8);
  $('#impl-kt-level').val('N3');
  $('#impl-kt-breaks-unit').val(5);
  toggleKtSection();
  
  // Reset added arrays
  addedItems = {
    Implementacion: [],
    MantPrev: [],
    MantCorr: [],
    BolsaHoras: []
  };
  
  renderSectionTable('Implementacion');
  renderSectionTable('MantPrev');
  renderSectionTable('MantCorr');
  renderSectionTable('BolsaHoras');
  
  $('#btn-save-as-new-version').addClass('d-none');
  
  // Go to tab
  $('#tab-editor-link').tab('show');
  $('#sec-config-link').tab('show');
  recalculateAll();
}

function loadQuoteForEdit(id, mode) {
  $.getJSON('api.php?action=get_quote_detail', { id }, function(res) {
    if (res.success) {
      const q = res.quote;
      const details = res.details;
      const ads = JSON.parse(q.adicionales_json || '{}');
      
      $('#editor-quote-id').val(q.id);
      $('#editor-parent-id').val(q.parent_id);
      $('#editor-version').val(q.version);
      $('#editor-cliente').val(q.cliente);
      $('#editor-contrato').val(q.contrato);
      $('#editor-observaciones').val(q.observaciones);
      $('#editor-margen-global').val(Math.round(q.margen_global * 100));
      $('#editor-fecha').val(q.fecha);
      
      $('#editor-version-badge').html(`<span class="badge badge-info p-2 mb-2"><i class="fas fa-code-branch"></i> Editando Versión actual: v${q.version}</span>`);
      
      if (mode === 'new_version') {
        $('#btn-save-as-new-version').removeClass('d-none');
      } else {
        $('#btn-save-as-new-version').addClass('d-none');
      }
      
      // Reset and populate details
      addedItems = {
        Implementacion: [],
        MantPrev: [],
        MantCorr: [],
        BolsaHoras: []
      };
      
      details.forEach(item => {
        addedItems[item.seccion].push({
          codigo_unico: item.codigo_unico || null,
          marca_categoria: item.marca_categoria,
          actividad: item.actividad,
          detalle: item.detalle,
          especialista_nivel: item.especialista_nivel,
          horas_laborables: parseFloat(item.horas_laborables),
          horas_no_laborables_50: parseFloat(item.horas_no_laborables_50),
          horas_no_laborables_100: parseFloat(item.horas_no_laborables_100),
          multiplier_type: item.multiplier_type || 'Ninguno',
          observaciones: item.observaciones
        });
      });
      
      // Populate equipment quantities dynamic table
      $('#eq-inventory-tbody').empty();
      if (ads.eq_rows && ads.eq_rows.length > 0) {
        ads.eq_rows.forEach(row => {
          addEquipmentInventoryRow(row.type, row.qty);
        });
      } else {
        // Legacy support or fallback: add rows for non-zero variables
        const legacy = [
          { type: 'Core', qty: ads.eq_qty_core || 0 },
          { type: 'Distribución', qty: ads.eq_qty_dist || 0 },
          { type: 'Acceso', qty: ads.eq_qty_access || 0 },
          { type: 'WLC', qty: ads.eq_qty_wlc || 0 },
          { type: 'AP', qty: ads.eq_qty_ap || 0 },
          { type: 'Blades', qty: ads.eq_qty_blades || 0 },
          { type: 'Chasis UCS-X', qty: ads.eq_qty_chasis || 0 },
          { type: 'Fabric', qty: ads.eq_qty_fabric || 0 },
          { type: 'VMware', qty: ads.eq_qty_vmware || 0 },
          { type: 'Intersight', qty: ads.eq_qty_intersight || 0 }
        ];
        let addedAny = false;
        legacy.forEach(row => {
          if (row.qty > 0) {
            addEquipmentInventoryRow(row.type, row.qty);
            addedAny = true;
          }
        });
        if (!addedAny) {
          addEquipmentInventoryRow('Acceso', 0);
        }
      }
      
      // Populate KT inputs
      $('#impl-kt-incluye').val(ads.impl_kt_incluye || 'No');
      $('#impl-kt-personas').val(ads.impl_kt_personas || 2);
      $('#impl-kt-dias').val(ads.impl_kt_dias || 2);
      $('#impl-kt-horas-dia').val(ads.impl_kt_horas_dia || 8);
      $('#impl-kt-level').val(ads.impl_kt_level || 'N3');
      $('#impl-kt-breaks-unit').val(ads.impl_kt_breaks_unit || 5);
      toggleKtSection();
      
      // Populate additional fields
      $('#impl-risk-pct').val(Math.round((parseFloat(q.risk_percentage) || 0.10) * 100));
      $('#impl-kt-hours').val(ads.impl_kt_hours || 0);
      $('#impl-breaks-cost').val(ads.impl_breaks_cost || 0);
      
      $('#impl-travel-nights').val(ads.impl_travel_nights || 0);
      $('#impl-travel-cost-night').val(ads.impl_travel_cost_night || 25);
      $('#impl-flights-qty').val(ads.impl_flights_qty || 0);
      $('#impl-flight-cost').val(ads.impl_flight_cost || 150);
      
      $('#impl-pss-val').val(ads.impl_pss_val || 0);
      $('#impl-ext-prov-cost').val(ads.impl_ext_prov_cost || 0);
      $('#impl-ext-prov-pvp').val(ads.impl_ext_prov_pvp || 0);
      
      $('#impl-boc-months').val(ads.impl_boc_months || 12);
      $('#impl-boc-hours').val(ads.impl_boc_hours || 2);
      $('#impl-boc-level').val(ads.impl_boc_level || 'BOC');
      
      $('#impl-pm-months').val(ads.impl_pm_months || 12);
      $('#impl-pm-hours').val(ads.impl_pm_hours || 6);
      $('#impl-pm-level').val(ads.impl_pm_level || 'GP1');
      
      $('#impl-consumables-screws').val(ads.impl_consumables_screws || 0);
      $('#impl-consumables-labels').val(ads.impl_consumables_labels || 0);
      $('#impl-consumables-vaccines').val(ads.impl_consumables_vaccines || 0);
      $('#impl-consumables-epp').val(ads.impl_consumables_epp || 0);
      
      // Preventivos
      $('#prev-travel-nights').val(ads.prev_travel_nights || 0);
      $('#prev-flights-qty').val(ads.prev_flights_qty || 0);
      $('#prev-materials-cost').val(ads.prev_materials_cost || 0);
      $('#prev-pss-cost').val(ads.prev_pss_cost || 0);
      
      // Correctivos
      if (ads.corr_method === 'cases') {
        $('#corr-opt-2').prop('checked', true);
        $('#corr-case-equipos').val(ads.corr_case_equipos || 53);
        $('#corr-case-dmg-pct').val(Math.round((ads.corr_case_dmg_pct || 0.1) * 100));
        $('#corr-case-years').val(ads.corr_case_years || 1);
        $('#corr-case-hours-per-case').val(ads.corr_case_hours_per_case || 4);
        $('#corr-case-level').val(ads.corr_case_level || 'N2');
        $('#corr-case-mov-cost').val(ads.corr_case_mov_cost || 15);
        $('#corr-case-mov-pvp').val(ads.corr_case_mov_pvp || 15);
      } else {
        $('#corr-opt-1').prop('checked', true);
        $('#corr-travel-nights').val(ads.corr_travel_nights || 0);
        $('#corr-flights-qty').val(ads.corr_flights_qty || 0);
        $('#corr-materials-cost').val(ads.corr_materials_cost || 0);
        $('#corr-pss-cost').val(ads.corr_pss_cost || 0);
      }
      
      // Bolsa Horas
      $('#bolsa-travel-extra').val(ads.bolsa_travel_extra || 0);
      $('#bolsa-flights-qty').val(ads.bolsa_flights_qty || 0);
      
      // Render
      renderSectionTable('Implementacion');
      renderSectionTable('MantPrev');
      renderSectionTable('MantCorr');
      renderSectionTable('BolsaHoras');
      
      // Switch to editor tab and first sub-tab
      $('#tab-editor-link').tab('show');
      $('#sec-config-link').tab('show');
      recalculateAll();
    } else {
      toastr.error(res.message);
    }
  });
}

function submitQuoteForm(mode) {
  const id = parseInt($('#editor-quote-id').val());
  const cliente = $('#editor-cliente').val();
  const contrato = $('#editor-contrato').val();
  const fecha = $('#editor-fecha').val();
  const margen_global = parseFloat($('#editor-margen-global').val()) / 100;
  const risk_percentage = parseFloat($('#impl-risk-pct').val()) / 100;
  const observaciones = $('#editor-observaciones').val();
  
  if (cliente === '' || contrato === '') {
    Swal.fire('Atención', 'El Cliente y Contrato son campos requeridos.', 'warning');
    return;
  }
  
  const qties = getEquipmentQuantities();
  const eq_rows = [];
  $('#eq-inventory-tbody tr').each(function() {
    const type = $(this).find('.eq-type-select').val();
    const qty = parseInt($(this).find('.eq-qty-input').val()) || 0;
    eq_rows.push({ type, qty });
  });
  
  // Compile adicionales
  const corrMethod = $('input[name="corr-option"]:checked').val();
  const adicionales = {
    eq_qty_core: qties.core,
    eq_qty_dist: qties.dist,
    eq_qty_access: qties.access,
    eq_qty_wlc: qties.wlc,
    eq_qty_ap: qties.ap,
    eq_qty_blades: qties.blades,
    eq_qty_chasis: qties.chasis,
    eq_qty_fabric: qties.fabric,
    eq_qty_vmware: qties.vmware,
    eq_qty_intersight: qties.intersight,
    eq_rows: eq_rows,
    
    impl_kt_incluye: $('#impl-kt-incluye').val(),
    impl_kt_personas: parseInt($('#impl-kt-personas').val()) || 0,
    impl_kt_dias: parseInt($('#impl-kt-dias').val()) || 0,
    impl_kt_horas_dia: parseInt($('#impl-kt-horas-dia').val()) || 0,
    impl_kt_breaks_unit: parseFloat($('#impl-kt-breaks-unit').val()) || 0,
    
    impl_kt_hours: parseFloat($('#impl-kt-hours').val()) || 0,
    impl_kt_level: $('#impl-kt-level').val(),
    impl_breaks_cost: parseFloat($('#impl-breaks-cost').val()) || 0,
    impl_travel_nights: parseInt($('#impl-travel-nights').val()) || 0,
    impl_travel_cost_night: parseFloat($('#impl-travel-cost-night').val()) || 25,
    impl_flights_qty: parseInt($('#impl-flights-qty').val()) || 0,
    impl_flight_cost: parseFloat($('#impl-flight-cost').val()) || 150,
    impl_pss_val: parseFloat($('#impl-pss-val').val()) || 0,
    impl_ext_prov_cost: parseFloat($('#impl-ext-prov-cost').val()) || 0,
    impl_ext_prov_pvp: parseFloat($('#impl-ext-prov-pvp').val()) || 0,
    impl_boc_months: parseInt($('#impl-boc-months').val()) || 0,
    impl_boc_hours: parseFloat($('#impl-boc-hours').val()) || 0,
    impl_boc_level: $('#impl-boc-level').val(),
    impl_pm_months: parseInt($('#impl-pm-months').val()) || 0,
    impl_pm_hours: parseFloat($('#impl-pm-hours').val()) || 0,
    impl_pm_level: $('#impl-pm-level').val(),
    impl_consumables_screws: parseFloat($('#impl-consumables-screws').val()) || 0,
    impl_consumables_labels: parseFloat($('#impl-consumables-labels').val()) || 0,
    impl_consumables_vaccines: parseFloat($('#impl-consumables-vaccines').val()) || 0,
    impl_consumables_epp: parseFloat($('#impl-consumables-epp').val()) || 0,
    
    prev_travel_nights: parseInt($('#prev-travel-nights').val()) || 0,
    prev_flights_qty: parseInt($('#prev-flights-qty').val()) || 0,
    prev_materials_cost: parseFloat($('#prev-materials-cost').val()) || 0,
    prev_pss_cost: parseFloat($('#prev-pss-cost').val()) || 0,
    
    corr_method: corrMethod,
    corr_travel_nights: parseInt($('#corr-travel-nights').val()) || 0,
    corr_flights_qty: parseInt($('#corr-flights-qty').val()) || 0,
    corr_materials_cost: parseFloat($('#corr-materials-cost').val()) || 0,
    corr_pss_cost: parseFloat($('#corr-pss-cost').val()) || 0,
    
    corr_case_equipos: parseInt($('#corr-case-equipos').val()) || 0,
    corr_case_dmg_pct: parseFloat($('#corr-case-dmg-pct').val()) / 100,
    corr_case_years: parseInt($('#corr-case-years').val()) || 1,
    corr_case_hours_per_case: parseFloat($('#corr-case-hours-per-case').val()) || 0,
    corr_case_level: $('#corr-case-level').val(),
    corr_case_mov_cost: parseFloat($('#corr-case-mov-cost').val()) || 0,
    corr_case_mov_pvp: parseFloat($('#corr-case-mov-pvp').val()) || 0,
    
    bolsa_travel_extra: parseInt($('#bolsa-travel-extra').val()) || 0,
    bolsa_flights_qty: parseInt($('#bolsa-flights-qty').val()) || 0
  };
  
  // Compile details
  const details = [];
  const getRates = (level) => {
    const sp = specialists.find(x => x.tipo === level);
    if (sp) {
      const cost_lab = parseFloat(sp.costo_hora_lab);
      const pvp_lab = cost_lab / (1 - margen_global);
      return { cost: cost_lab, pvp: pvp_lab };
    }
    return { cost: 0, pvp: 0 };
  };
  
  const pushSectionDetails = (section) => {
    addedItems[section].forEach(item => {
      const r = getRates(item.especialista_nivel);
      const multiplier = getMultiplierValue(item.multiplier_type || 'Ninguno');
      const c_tot = ((item.horas_laborables * r.cost) + (item.horas_no_laborables_50 * r.cost * 1.5) + (item.horas_no_laborables_100 * r.cost * 2.0)) * multiplier;
      const p_tot = ((item.horas_laborables * r.pvp) + (item.horas_no_laborables_50 * r.pvp * 1.5) + (item.horas_no_laborables_100 * r.pvp * 2.0)) * multiplier;
      
      details.push({
        seccion: section,
        codigo_unico: item.codigo_unico || null,
        marca_categoria: item.marca_categoria,
        actividad: item.actividad,
        detalle: item.detalle,
        especialista_nivel: item.especialista_nivel,
        horas_laborables: item.horas_laborables,
        horas_no_laborables_50: item.horas_no_laborables_50,
        horas_no_laborables_100: item.horas_no_laborables_100,
        costo_hora: r.cost,
        pvp_hora: r.pvp,
        costo_total: c_tot,
        pvp_total: p_tot,
        multiplier_type: item.multiplier_type || 'Ninguno',
        observaciones: item.observaciones
      });
    });
  };
  
  pushSectionDetails('Implementacion');
  pushSectionDetails('MantPrev');
  if (corrMethod === 'hours') {
    pushSectionDetails('MantCorr');
  }
  pushSectionDetails('BolsaHoras');
  
  // Totals from summary fields
  const total_costo = parseFloat($('#sum-tot-cost').text().replace('$', '')) || 0;
  const total_precio = parseFloat($('#sum-tot-pvp').text().replace('$', '')) || 0;
  
  const payload = {
    id,
    save_mode: mode,
    cliente,
    contrato,
    fecha,
    margen_global,
    risk_percentage,
    total_costo,
    total_precio,
    adicionales,
    observaciones,
    details
  };
  
  $.ajax({
    url: 'api.php?action=save_quote',
    type: 'POST',
    contentType: 'application/json',
    data: JSON.stringify(payload),
    success: function(res) {
      if (res.success) {
        toastr.success(res.message);
        $('#tab-list-link').tab('show');
        loadQuotesList();
      } else {
        Swal.fire('Error', res.message, 'error');
      }
    }
  });
}

function approveQuote(id) {
  Swal.fire({
    title: 'Aprobar Cotización',
    text: "Se marcará como enviada y se registrará su firma de aprobación preventiva.",
    input: 'text',
    inputValue: 'Jefe de Preventa',
    inputPlaceholder: 'Ingrese su nombre o cargo...',
    showCancelButton: true,
    confirmButtonText: 'Sí, Aprobar y Enviar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed && result.value) {
      $.post('api.php?action=approve_quote', { id, aprobador: result.value }, function(res) {
        if (res.success) {
          toastr.success(res.message);
          loadQuotesList();
        } else {
          toastr.error(res.message);
        }
      });
    }
  });
}

function deleteQuote(id) {
  Swal.fire({
    title: '¿Está seguro de eliminar?',
    text: "¡Si elimina el registro original se borrarán todas sus versiones asociadas!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      $.post('api.php?action=delete_quote', { id }, function(res) {
        if (res.success) {
          toastr.success(res.message);
          loadQuotesList();
        } else {
          toastr.error(res.message);
        }
      });
    }
  });
}

function cancelEditor() {
  startNewQuote();
  $('#tab-list-link').tab('show');
}

function escapeHtml(string) {
  return String(string).replace(/[&<>"']/g, function (s) {
    return {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': '&quot;',
      "'": '&#39;'
    }[s];
  });
}

// -------------------------------------------------------------
// SPECIALIST LEVELS & RANGES CRUD
// -------------------------------------------------------------
function loadSpecialistLevelsList() {
  $.getJSON('api.php?action=get_levels', function(res) {
    if (res.success) {
      specialistLevels = res.data;
      
      // Populate dropdown in specialist modal
      populateSpecialistTypeDropdown();
      
      let html = '';
      if (specialistLevels.length === 0) {
        html = '<tr><td colspan="6" class="text-center py-3 text-muted">No hay niveles de especialistas configurados.</td></tr>';
      } else {
        specialistLevels.forEach(lvl => {
          html += `
            <tr>
              <td><strong>${escapeHtml(lvl.code)}</strong></td>
              <td><span class="badge badge-info">${escapeHtml(lvl.base_type)}</span></td>
              <td class="text-right">$${parseFloat(lvl.min_salary).toFixed(2)}</td>
              <td class="text-right">$${parseFloat(lvl.max_salary).toFixed(2)}</td>
              <td><span class="text-muted small">Fórmula de tipo base: <strong>${escapeHtml(lvl.base_type)}</strong></span></td>
              <td class="text-center">
                <button class="btn btn-xs btn-info" onclick="editLevel(${lvl.id})"><i class="fas fa-edit"></i></button>
                <button class="btn btn-xs btn-danger" onclick="deleteLevel(${lvl.id})"><i class="fas fa-trash"></i></button>
              </td>
            </tr>
          `;
        });
      }
      $('#levels-table-body').html(html);
    } else {
      toastr.error(res.message || 'Error al cargar los niveles.');
    }
  });
}

function populateSpecialistTypeDropdown() {
  let selectHtml = '';
  specialistLevels.forEach(lvl => {
    selectHtml += `<option value="${escapeHtml(lvl.code)}">${escapeHtml(lvl.code)} (${lvl.base_type})</option>`;
  });
  $('#sp-tipo').html(selectHtml);
}

function showLevelModal() {
  $('#levelForm')[0].reset();
  $('#lvl-id').val(0);
  $('#levelModalTitle').text('Agregar Nivel / Rango');
  $('#levelModal').modal('show');
}

function editLevel(id) {
  const lvl = specialistLevels.find(x => x.id == id);
  if (!lvl) return;
  
  $('#lvl-id').val(lvl.id);
  $('#lvl-code').val(lvl.code);
  $('#lvl-base-type').val(lvl.base_type);
  $('#lvl-min-salary').val(lvl.min_salary);
  $('#lvl-max-salary').val(lvl.max_salary);
  
  $('#levelModalTitle').text('Editar Nivel / Rango');
  $('#levelModal').modal('show');
}

function saveLevel(e) {
  e.preventDefault();
  $.post('api.php?action=save_level', $('#levelForm').serialize(), function(res) {
    if (res.success) {
      $('#levelModal').modal('hide');
      toastr.success(res.message);
      loadSpecialistLevelsList();
      loadSpecialistsList();
    } else {
      toastr.error(res.message);
    }
  });
}

function deleteLevel(id) {
  Swal.fire({
    title: '¿Está seguro?',
    text: "Se eliminará este nivel. Asegúrese de que ningún especialista esté usando este nivel.",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      $.post('api.php?action=delete_level', { id }, function(res) {
        if (res.success) {
          toastr.success(res.message);
          loadSpecialistLevelsList();
          loadSpecialistsList();
        } else {
          toastr.error(res.message);
        }
      });
    }
  });
}

// -------------------------------------------------------------
// EQUIPMENT CATEGORIES CRUD
// -------------------------------------------------------------
function loadEqCategories() {
  $.getJSON('api.php?action=get_eq_categories', function(res) {
    if (res.success) {
      equipmentCategories = res.data;
      renderEqCategoriesTable();
      // Refresh inventory rows to use new categories
      if ($('#eq-inventory-tbody tr').length > 0) {
        // Re-render only if already has rows
      }
    }
  });
}

function renderEqCategoriesTable() {
  const tbody = $('#eq-categories-tbody');
  if (!tbody.length) return;
  let html = '';
  if (equipmentCategories.length === 0) {
    html = '<tr><td colspan="3" class="text-center text-muted py-3">No hay categorías configuradas.</td></tr>';
  } else {
    equipmentCategories.forEach((cat, i) => {
      const color = BRAND_COLORS[i % BRAND_COLORS.length];
      html += `
        <tr>
          <td>
            <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${color.badge};margin-right:8px;"></span>
            <strong>${escapeHtml(cat.name)}</strong>
          </td>
          <td><span class="badge" style="background:${color.badge};color:#fff;">${escapeHtml(cat.name)}</span></td>
          <td class="text-center">
            <button class="btn btn-xs btn-info" onclick="editEqCategory(${cat.id})"><i class="fas fa-edit"></i></button>
            <button class="btn btn-xs btn-danger" onclick="deleteEqCategory(${cat.id})"><i class="fas fa-trash"></i></button>
          </td>
        </tr>
      `;
    });
  }
  tbody.html(html);
}

function showEqCategoryModal() {
  $('#eqCategoryForm')[0].reset();
  $('#eq-cat-id').val(0);
  $('#eqCategoryModalTitle').text('Agregar Categoría de Equipo');
  $('#eqCategoryModal').modal('show');
}

function editEqCategory(id) {
  const cat = equipmentCategories.find(x => x.id == id);
  if (!cat) return;
  $('#eq-cat-id').val(cat.id);
  $('#eq-cat-name').val(cat.name);
  $('#eqCategoryModalTitle').text('Editar Categoría');
  $('#eqCategoryModal').modal('show');
}

function saveEqCategory(e) {
  e.preventDefault();
  $.post('api.php?action=save_eq_category', $('#eqCategoryForm').serialize(), function(res) {
    if (res.success) {
      $('#eqCategoryModal').modal('hide');
      toastr.success(res.message);
      loadEqCategories();
    } else {
      toastr.error(res.message);
    }
  });
}

function deleteEqCategory(id) {
  Swal.fire({
    title: '¿Eliminar categoría?',
    text: 'Se eliminará esta categoría. Los equipos ya cargados en cotizaciones no se verán afectados.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar'
  }).then(result => {
    if (result.isConfirmed) {
      $.post('api.php?action=delete_eq_category', { id }, function(res) {
        if (res.success) {
          toastr.success(res.message);
          loadEqCategories();
        } else {
          toastr.error(res.message);
        }
      });
    }
  });
}

// -------------------------------------------------------------
// POOL BRANDS (Marcas/Grupos de Actividades — Nivel 1) CRUD
// -------------------------------------------------------------
function renderPoolBrandsTable() {
  const tbody = $('#pool-brands-tbody');
  if (!tbody.length) return;
  let html = '';
  if (poolBrands.length === 0) {
    html = '<tr><td colspan="3" class="text-center text-muted py-3">No hay marcas/grupos configurados.</td></tr>';
  } else {
    poolBrands.forEach((brand, i) => {
      const color = BRAND_COLORS[i % BRAND_COLORS.length];
      // Count how many pool items use this brand
      const usageCount = servicePool.filter(p => p.marca_categoria === brand.name).length;
      html += `
        <tr>
          <td>
            <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${color.badge};margin-right:8px;"></span>
            <strong>${escapeHtml(brand.name)}</strong>
          </td>
          <td>
            <span class="badge" style="background:${color.badge};color:#fff;margin-right:6px;">${escapeHtml(brand.name)}</span>
            ${usageCount > 0 ? `<small class="text-muted">${usageCount} actividad(es) en pool</small>` : '<small class="text-muted">Sin actividades</small>'}
          </td>
          <td class="text-center">
            <button class="btn btn-xs btn-info" onclick="editPoolBrand(${brand.id})"><i class="fas fa-edit"></i></button>
            <button class="btn btn-xs btn-danger" onclick="deletePoolBrand(${brand.id})" ${usageCount > 0 ? 'title="Tiene actividades asociadas"' : ''}><i class="fas fa-trash"></i></button>
          </td>
        </tr>
      `;
    });
  }
  tbody.html(html);
}

function showPoolBrandModal() {
  $('#poolBrandForm')[0].reset();
  $('#pb-id').val(0);
  $('#poolBrandModalTitle').text('Agregar Marca / Grupo de Actividades');
  $('#poolBrandModal').modal('show');
}

function editPoolBrand(id) {
  const brand = poolBrands.find(x => x.id == id);
  if (!brand) return;
  $('#pb-id').val(brand.id);
  $('#pb-name').val(brand.name);
  $('#poolBrandModalTitle').text('Editar Marca / Grupo');
  $('#poolBrandModal').modal('show');
}

function savePoolBrand(e) {
  e.preventDefault();
  $.post('api.php?action=save_pool_brand', $('#poolBrandForm').serialize(), function(res) {
    if (res.success) {
      $('#poolBrandModal').modal('hide');
      toastr.success(res.message);
      loadPoolBrands();
      loadBrandFilters(); // Refresh pool table and all brand dropdowns
    } else {
      toastr.error(res.message);
    }
  });
}

function deletePoolBrand(id) {
  Swal.fire({
    title: '\u00bfEliminar marca/grupo?',
    text: 'Solo se puede eliminar si no tiene actividades en el pool asociadas.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'S\u00ed, eliminar',
    cancelButtonText: 'Cancelar'
  }).then(result => {
    if (result.isConfirmed) {
      $.post('api.php?action=delete_pool_brand', { id }, function(res) {
        if (res.success) {
          toastr.success(res.message);
          loadPoolBrands();
          loadBrandFilters();
        } else {
          toastr.error(res.message);
        }
      });
    }
  });
}



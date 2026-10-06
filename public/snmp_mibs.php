<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/permissions_helper.php';

require_login();
if (!has_module_access('snmp')) {
    header("Location: dashboard.php");
    exit();
}

$page_title = 'Repositorio de MIBs';
include __DIR__ . '/partials/header.php';
?>

<div class="container-fluid">
    <div class="row mb-2">
        <div class="col-sm-6">
            <h1 class="m-0 font-weight-bold text-dark"><i class="fas fa-network-wired mr-2"></i> Módulo SNMP</h1>
        </div>
    </div>
    
    <div class="row">
        <!-- Panel de Subida -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0" style="border-radius: 15px;">
                <div class="card-header bg-success text-white py-3" style="border-radius: 15px 15px 0 0;">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-cloud-upload-alt mr-2"></i> Subir Nueva MIB</h6>
                </div>
                <div class="card-body p-4">
                    <p class="small text-muted">Sube archivos <code>.mib</code>, <code>.txt</code> o <code>.my</code> para expandir las capacidades de reconocimiento del SNMP Builder.</p>
                    
                    <form id="upload-form" enctype="multipart/form-data">
                        <div class="form-group">
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="mib_file" name="mib_file" accept=".mib,.txt,.my,.dic">
                                <label class="custom-file-label" for="mib_file">Elegir archivo...</label>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success btn-block shadow-sm">
                            <i class="fas fa-upload mr-2"></i> PROCESAR Y GUARDAR
                        </button>
                    </form>

                    <div class="mt-4 p-3 bg-light rounded" style="border-left: 4px solid #28a745;">
                        <small class="d-block font-weight-bold mb-1">Ruta del Repositorio:</small>
                        <code class="small text-dark" style="word-break: break-all;"><?php echo htmlspecialchars(SNMP_MIBS_PATH); ?></code>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 mt-4" style="border-radius: 15px;">
                <div class="card-body p-4">
                    <h6 class="font-weight-bold mb-3"><i class="fas fa-info-circle text-info mr-2"></i> Instrucciones</h6>
                    <ul class="small text-muted pl-3">
                        <li>Asegúrate de que el archivo mib tenga las dependencias necesarias.</li>
                        <li>Formatos recomendados: .mib o .txt.</li>
                        <li>Una vez subido, el SNMP Builder reconocerá automáticamente la nueva OID.</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Listado de MIBs -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0" style="border-radius: 15px;">
                <div class="card-header bg-white py-3 d-flex align-items-center" style="border-radius: 15px 15px 0 0;">
                    <h6 class="m-0 font-weight-bold text-dark"><i class="fas fa-list mr-2"></i> Archivos en Repositorio</h6>
                    <div class="ml-auto w-50">
                        <input type="text" id="mib-search" class="form-control form-control-sm" placeholder="Buscar MIB...">
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 600px;">
                        <table class="table table-hover mb-0" id="mibs-table">
                            <thead class="bg-light">
                                <tr>
                                    <th class="border-0 small font-weight-bold">Nombre del Archivo</th>
                                    <th class="border-0 small font-weight-bold">Tipo</th>
                                    <th class="border-0 small font-weight-bold">Tamaño</th>
                                    <th class="border-0 small font-weight-bold">Fecha</th>
                                    <th class="border-0 small font-weight-bold text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="mibs-tbody">
                                <tr><td colspan="5" class="text-center py-5">Cargando repositorio...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(function() {
    loadMibs();

    // Actualizar nombre del archivo en el input custom
    $('.custom-file-input').on('change', function() {
        let fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').addClass("selected").html(fileName);
    });

    // Subida via AJAX
    $('#upload-form').on('submit', function(e) {
        e.preventDefault();

        let fileInput = $('#mib_file')[0];
        if (!fileInput.files || fileInput.files.length === 0) {
            Swal.fire('Atención', 'Por favor selecciona un archivo MIB (.mib, .txt) para subir.', 'warning');
            return;
        }

        let formData = new FormData(this);
        formData.append('action', 'upload_mib');

        Swal.fire({
            title: 'Subiendo MIB...',
            text: 'Procesando archivo en el servidor',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        $.ajax({
            url: 'api_snmp.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json',
            success: function(resp) {
                if (resp && resp.success) {
                    Swal.fire('¡Éxito!', resp.message || 'MIB subida con éxito', 'success');
                    $('#upload-form')[0].reset();
                    $('.custom-file-label').html('Elegir archivo...');
                    loadMibs();
                } else {
                    Swal.fire('Error', (resp && resp.error) ? resp.error : 'Ocurrió un error al procesar el archivo', 'error');
                }
            },
            error: function(xhr, status, error) {
                let errorMsg = 'Error en el servidor (' + (xhr.status || status) + ')';
                try {
                    let jsonErr = JSON.parse(xhr.responseText);
                    if (jsonErr && jsonErr.error) errorMsg = jsonErr.error;
                } catch(e){}
                Swal.fire('Error de Comunicación', errorMsg, 'error');
            }
        });
    });

    function loadMibs() {
        $.get('api_snmp.php?action=list_mibs', function(resp) {
            if (resp.success) {
                renderMibs(resp.data);
            } else {
                $('#mibs-tbody').html('<tr><td colspan="5" class="text-center text-danger">'+resp.error+'</td></tr>');
            }
        });
    }

    function renderMibs(data) {
        const tbody = $('#mibs-tbody');
        tbody.empty();

        if (data.length === 0) {
            tbody.html('<tr><td colspan="5" class="text-center py-4">Repositorio vacío</td></tr>');
            return;
        }

        data.forEach(m => {
            const date = new Date(m.mtime * 1000).toLocaleString();
            const size = (m.size / 1024).toFixed(1) + ' KB';
            
            tbody.append(`
                <tr class="mib-item" data-name="${m.filename.toLowerCase()}">
                    <td class="align-middle">
                        <i class="fas fa-file-code text-muted mr-2"></i>
                        <span class="font-weight-bold small">${m.filename}</span>
                    </td>
                    <td class="align-middle"><span class="badge badge-light border text-uppercase">${m.type}</span></td>
                    <td class="align-middle small text-muted">${size}</td>
                    <td class="align-middle small text-muted">${date}</td>
                    <td class="align-middle text-center">
                        <button class="btn btn-xs btn-outline-danger btn-delete-mib" data-name="${m.filename}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `);
        });
    }

    // Buscador en vivo
    $('#mib-search').on('keyup', function() {
        let val = $(this).val().toLowerCase();
        $('.mib-item').each(function() {
            let name = $(this).data('name');
            $(this).toggle(name.includes(val));
        });
    });

    // Eliminar MIB
    $(document).on('click', '.btn-delete-mib', function() {
        const name = $(this).data('name');
        Swal.fire({
            title: '¿Confirmar eliminación?',
            text: `Se borrará "${name}" del repositorio.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Sí, borrar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('api_snmp.php', { action: 'delete_mib', filename: name }, function(resp) {
                    if (resp.success) {
                        Swal.fire('Eliminado', resp.message, 'success');
                        loadMibs();
                    } else {
                        Swal.fire('Error', resp.error, 'error');
                    }
                });
            }
        });
    });
});
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>

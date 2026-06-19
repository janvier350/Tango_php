<!-- Modal: Modificar Inf. Financiera de un movimiento -->
<div class="modal fade" id="modalEditarInfFin" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header py-2" style="background:#2c3e50; color:#fff;">
        <h6 class="modal-title mb-0"><i class="bi bi-pencil-square me-2"></i>Modificar Inf. Financiera</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="edit_if_id_movimiento">
        <label class="form-label small fw-bold">Selecciona la nueva Inf. Financiera</label>
        <select id="edit_if_select" class="form-select form-select-sm" style="width:100%">
          <option value="">Cargando...</option>
        </select>
        <div id="edit_if_feedback" class="form-text mt-2"></div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" id="edit_if_guardar" class="btn btn-success btn-sm">
          <i class="bi bi-check-lg me-1"></i>Guardar
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Nueva Inf. Financiera (catálogo, parte superior de la página) -->
<div class="modal fade" id="modalNuevaInfFin" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header py-2" style="background:#2c3e50; color:#fff;">
        <h6 class="modal-title mb-0"><i class="bi bi-journal-plus me-2"></i>Nueva Inf. Financiera</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">

        <!-- Categoría -->
        <div class="mb-3">
          <label class="form-label small fw-bold">Categoría</label>
          <div class="d-flex gap-2 align-items-center mb-2">
            <select id="if_cat_select" class="form-select form-select-sm">
              <option value="">Cargando categorías...</option>
            </select>
            <div class="form-check form-switch mb-0 ms-1 text-nowrap">
              <input class="form-check-input" type="checkbox" id="if_nueva_cat_toggle">
              <label class="form-check-label small" for="if_nueva_cat_toggle">Nueva</label>
            </div>
          </div>
          <div id="if_nueva_cat_wrap" class="d-none">
            <input type="text" id="if_nueva_cat" class="form-control form-control-sm text-uppercase"
                   placeholder="Nombre de la nueva categoría...">
            <div id="if_nueva_cat_feedback" class="form-text"></div>
          </div>
        </div>

        <!-- Reposición / Detalle -->
        <div class="mb-2">
          <label class="form-label small fw-bold">Detalle (Inf. Financiera)</label>
          <input type="text" id="if_reposicion" class="form-control form-control-sm text-uppercase"
                 placeholder="Nombre del concepto financiero...">
          <div id="if_reposicion_feedback" class="form-text"></div>
        </div>

        <div id="if_preview" class="alert alert-light py-1 px-2 small d-none">
          <i class="bi bi-tag me-1"></i><span id="if_preview_text"></span>
        </div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" id="if_guardar" class="btn btn-success btn-sm">
          <i class="bi bi-check-lg me-1"></i>Guardar
        </button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function abrirModalEditarInfFin(idMovimiento, idReposicionActual) {
    $('#edit_if_id_movimiento').val(idMovimiento);
    $('#edit_if_feedback').text('').removeClass('text-danger text-success');
    $('#edit_if_guardar').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Guardar');
    $('#edit_if_select').html('<option value="">Cargando...</option>').trigger('change');

    $.post('ajax_editar_inf_fin.php', { action: 'listar' }, function(items) {
        var opts = '<option value="">Selecciona...</option>';
        $.each(items, function(i, it) {
            var sel = (parseInt(it.id) === parseInt(idReposicionActual)) ? 'selected' : '';
            opts += '<option value="' + it.id + '" ' + sel + '>' + it.label + '</option>';
        });
        $('#edit_if_select').html(opts).trigger('change');
    }, 'json');

    var myModal = new bootstrap.Modal(document.getElementById('modalEditarInfFin'));
    myModal.show();
}

$(document).ready(function() {

    $('#edit_if_select').select2({
        placeholder: "Buscar inf. financiera...",
        width: '100%',
        dropdownParent: $('#modalEditarInfFin')
    });

    $('#edit_if_guardar').on('click', function() {
        var idMov = $('#edit_if_id_movimiento').val();
        var idRep = $('#edit_if_select').val();
        if (!idRep) {
            $('#edit_if_feedback').text('Selecciona una opción.').addClass('text-danger').removeClass('text-success');
            return;
        }
        $('#edit_if_guardar').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
        $.post('ajax_editar_inf_fin.php', { action: 'actualizar', id_movimiento: idMov, id_reposicion: idRep }, function(res) {
            $('#edit_if_guardar').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Guardar');
            if (res.success) {
                bootstrap.Modal.getInstance(document.getElementById('modalEditarInfFin')).hide();
                location.reload();
            } else {
                $('#edit_if_feedback').text(res.msg).addClass('text-danger').removeClass('text-success');
            }
        }, 'json').fail(function() {
            $('#edit_if_guardar').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Guardar');
            $('#edit_if_feedback').text('Error de conexión.').addClass('text-danger').removeClass('text-success');
        });
    });

    // --- Modal Nueva Inf. Financiera (catálogo) ---
    var checkTimerIF, checkTimerCat;

    $('#modalNuevaInfFin').on('show.bs.modal', function() {
        $('#if_cat_select').html('<option value="">Cargando...</option>');
        $('#if_nueva_cat_toggle').prop('checked', false);
        $('#if_nueva_cat_wrap').addClass('d-none');
        $('#if_nueva_cat').val('').removeClass('is-valid is-invalid');
        $('#if_nueva_cat_feedback').text('').removeClass('text-success text-danger');
        $('#if_reposicion').val('').removeClass('is-valid is-invalid');
        $('#if_reposicion_feedback').text('').removeClass('text-success text-danger');
        $('#if_preview').addClass('d-none');
        $('#if_guardar').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Guardar');

        $.post('ajax_inf_financiera.php', { action: 'categorias' }, function(cats) {
            var opts = '<option value="">Selecciona una categoría...</option>';
            $.each(cats, function(i, c) { opts += '<option value="' + c.id + '">' + c.nombre + '</option>'; });
            $('#if_cat_select').html(opts);
        }, 'json');
    });
    $('#modalNuevaInfFin').on('shown.bs.modal', function() { $('#if_reposicion').focus(); });

    $('#if_nueva_cat_toggle').on('change', function() {
        if ($(this).is(':checked')) {
            $('#if_nueva_cat_wrap').removeClass('d-none');
            $('#if_cat_select').prop('disabled', true);
            $('#if_nueva_cat').focus();
        } else {
            $('#if_nueva_cat_wrap').addClass('d-none');
            $('#if_cat_select').prop('disabled', false);
            $('#if_nueva_cat').val('').removeClass('is-valid is-invalid');
            $('#if_nueva_cat_feedback').text('').removeClass('text-success text-danger');
        }
        actualizarPreviewIF();
        validarReposicionIF();
    });

    $('#if_nueva_cat').on('input', function() {
        var val = $(this).val().trim();
        clearTimeout(checkTimerCat);
        $(this).removeClass('is-valid is-invalid');
        $('#if_nueva_cat_feedback').text('').removeClass('text-success text-danger');
        if (val.length < 2) { actualizarPreviewIF(); return; }
        checkTimerCat = setTimeout(function() {
            $.post('ajax_inf_financiera.php', { action: 'check_categoria', nombre: val }, function(res) {
                if (res.exists) {
                    $('#if_nueva_cat').addClass('is-invalid');
                    $('#if_nueva_cat_feedback').text('⚠ Ya existe — se usará la categoría existente.').addClass('text-danger');
                } else {
                    $('#if_nueva_cat').addClass('is-valid');
                    $('#if_nueva_cat_feedback').text('✓ Se creará esta categoría.').addClass('text-success');
                }
                actualizarPreviewIF();
                validarReposicionIF();
            }, 'json');
        }, 500);
    });

    $('#if_cat_select').on('change', function() {
        actualizarPreviewIF();
        validarReposicionIF();
    });

    function getCatNombreIF() {
        if ($('#if_nueva_cat_toggle').is(':checked')) return $('#if_nueva_cat').val().trim().toUpperCase();
        return $('#if_cat_select option:selected').text();
    }

    function actualizarPreviewIF() {
        var cat = getCatNombreIF();
        var rep = $('#if_reposicion').val().trim().toUpperCase();
        if (cat && cat !== 'Selecciona una categoría...' && rep.length > 1) {
            $('#if_preview_text').text(cat + ' / ' + rep);
            $('#if_preview').removeClass('d-none');
        } else {
            $('#if_preview').addClass('d-none');
        }
    }

    function validarReposicionIF() {
        var rep    = $('#if_reposicion').val().trim();
        var id_cat = $('#if_nueva_cat_toggle').is(':checked') ? 0 : parseInt($('#if_cat_select').val() || 0);
        $('#if_reposicion').removeClass('is-valid is-invalid');
        $('#if_reposicion_feedback').text('').removeClass('text-success text-danger');
        if (rep.length < 2 || id_cat === 0) return;
        $.post('ajax_inf_financiera.php', { action: 'check_reposicion', reposicion: rep, id_cat: id_cat }, function(res) {
            if (res.exists) {
                $('#if_reposicion').addClass('is-invalid');
                $('#if_reposicion_feedback').text('⚠ Ya existe en esa categoría.').addClass('text-danger');
            } else {
                $('#if_reposicion').addClass('is-valid');
                $('#if_reposicion_feedback').text('✓ Disponible.').addClass('text-success');
            }
        }, 'json');
    }

    $('#if_reposicion').on('input', function() {
        clearTimeout(checkTimerIF);
        actualizarPreviewIF();
        checkTimerIF = setTimeout(validarReposicionIF, 500);
    });

    $('#if_guardar').on('click', function() {
        var reposicion     = $('#if_reposicion').val().trim();
        var usar_nueva_cat = $('#if_nueva_cat_toggle').is(':checked') ? '1' : '0';
        var nueva_cat      = $('#if_nueva_cat').val().trim();
        var id_cat         = $('#if_cat_select').val() || 0;

        if (reposicion.length < 2) {
            $('#if_reposicion').addClass('is-invalid');
            $('#if_reposicion_feedback').text('Ingresa el nombre del concepto.').addClass('text-danger');
            return;
        }
        if ($('#if_reposicion').hasClass('is-invalid')) return;
        if (usar_nueva_cat === '0' && !id_cat) {
            $('#if_cat_select').addClass('is-invalid'); return;
        }

        $('#if_guardar').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

        $.post('ajax_inf_financiera.php', {
            action: 'crear', reposicion: reposicion,
            usar_nueva_cat: usar_nueva_cat, nueva_cat: nueva_cat, id_cat_existente: id_cat
        }, function(res) {
            $('#if_guardar').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Guardar');
            if (res.success) {
                bootstrap.Modal.getInstance(document.getElementById('modalNuevaInfFin')).hide();
                var toast = $('<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999"><div class="toast show align-items-center border-0" style="background:#2c3e50;color:#fff;"><div class="d-flex"><div class="toast-body"><i class="bi bi-check-circle me-2"></i>Inf. financiera creada correctamente.</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div></div>');
                $('body').append(toast);
                setTimeout(function() { toast.remove(); }, 3000);
            } else {
                if (res.msg.includes('categoría')) {
                    $('#if_nueva_cat').addClass('is-invalid');
                    $('#if_nueva_cat_feedback').text(res.msg).addClass('text-danger');
                } else {
                    $('#if_reposicion').addClass('is-invalid');
                    $('#if_reposicion_feedback').text(res.msg).addClass('text-danger');
                }
            }
        }, 'json').fail(function() {
            $('#if_guardar').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Guardar');
            alert('Error de conexión. Intenta nuevamente.');
        });
    });
});
</script>

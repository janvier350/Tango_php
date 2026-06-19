<div class="d-flex flex-wrap align-items-end gap-2 mb-3">
    <div class="btn-group btn-group-sm" role="group" id="filtroRevisionGroup">
        <button type="button" class="btn btn-outline-secondary active" data-filtro-rev="todos">Todos</button>
        <button type="button" class="btn btn-outline-warning" data-filtro-rev="pendiente">Pendientes por aprobar</button>
        <button type="button" class="btn btn-outline-success" data-filtro-rev="aprobado">Aprobado</button>
        <button type="button" class="btn btn-outline-danger" data-filtro-rev="anulado">Anulado</button>
    </div>

    <div>
        <label class="form-label small fw-bold mb-0">Proyecto</label>
        <select id="filtroProyecto" class="form-select form-select-sm" style="min-width:180px;">
            <option value="">Todos los proyectos</option>
        </select>
    </div>

    <div>
        <label class="form-label small fw-bold mb-0">Desde</label>
        <input type="date" id="filtroFechaDesde" class="form-control form-control-sm">
    </div>
    <div>
        <label class="form-label small fw-bold mb-0">Hasta</label>
        <input type="date" id="filtroFechaHasta" class="form-control form-control-sm">
    </div>

    <button type="button" id="filtroLimpiar" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-x-circle me-1"></i>Limpiar filtros
    </button>
</div>
<script>
$(document).ready(function() {

    // Carga el select de Proyecto con los valores únicos presentes en la tabla
    (function cargarProyectosUnicos() {
        var proyectos = [];
        $('#tablaValidacion tbody tr').each(function() {
            var p = $(this).attr('data-proyecto');
            if (p && proyectos.indexOf(p) === -1) proyectos.push(p);
        });
        proyectos.sort(function(a, b) { return a.localeCompare(b); });
        var $sel = $('#filtroProyecto');
        $.each(proyectos, function(i, p) {
            $sel.append($('<option></option>').val(p).text(p));
        });
    })();

    function aplicarFiltros() {
        var estado   = $('#filtroRevisionGroup button.active').data('filtro-rev') || 'todos';
        var proyecto = $('#filtroProyecto').val();
        var desde    = $('#filtroFechaDesde').val();
        var hasta    = $('#filtroFechaHasta').val();

        $('#tablaValidacion tbody tr').each(function() {
            var row = $(this);
            var okEstado   = (estado === 'todos' || row.attr('data-estado-rev') === estado);
            var okProyecto = (!proyecto || row.attr('data-proyecto') === proyecto);
            var fechaRow   = row.attr('data-fecha');
            var okFecha    = true;
            if (desde && fechaRow < desde) okFecha = false;
            if (hasta && fechaRow > hasta) okFecha = false;
            row.toggle(okEstado && okProyecto && okFecha);
        });
    }

    $('#filtroRevisionGroup button').on('click', function() {
        $('#filtroRevisionGroup button').removeClass('active');
        $(this).addClass('active');
        aplicarFiltros();
    });

    $('#filtroProyecto, #filtroFechaDesde, #filtroFechaHasta').on('change', aplicarFiltros);

    $('#filtroLimpiar').on('click', function() {
        $('#filtroRevisionGroup button').removeClass('active');
        $('#filtroRevisionGroup button[data-filtro-rev="todos"]').addClass('active');
        $('#filtroProyecto').val('');
        $('#filtroFechaDesde').val('');
        $('#filtroFechaHasta').val('');
        aplicarFiltros();
    });
});
</script>

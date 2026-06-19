<div class="btn-group btn-group-sm mb-3" role="group" id="filtroRevisionGroup">
    <button type="button" class="btn btn-outline-secondary active" data-filtro-rev="todos">Todos</button>
    <button type="button" class="btn btn-outline-warning" data-filtro-rev="pendiente">Pendientes por aprobar</button>
    <button type="button" class="btn btn-outline-success" data-filtro-rev="aprobado">Aprobado</button>
    <button type="button" class="btn btn-outline-danger" data-filtro-rev="anulado">Anulado</button>
</div>
<script>
$(document).ready(function() {
    $('#filtroRevisionGroup button').on('click', function() {
        var filtro = $(this).data('filtro-rev');
        $('#filtroRevisionGroup button').removeClass('active');
        $(this).addClass('active');
        $('#tablaValidacion tbody tr').each(function() {
            var estado = $(this).data('estado-rev');
            $(this).toggle(filtro === 'todos' || estado === filtro);
        });
    });
});
</script>

<?php
/*
 * Modal reutilizable para descargar el reporte PDF de movimientos por mes/anio.
 * Uso:
 *   $rep_id_oficina = <ID_OFICINA de la caja>;  // opcional
 *   include 'modal_reporte_pdf.php';
 * Si $rep_id_oficina no se define (o es 0), el reporte usa la oficina de la sesion.
 * Requiere Bootstrap 5 (bundle) cargado en la pagina. No depende de jQuery.
 * El boton que lo abre debe usar: data-bs-toggle="modal" data-bs-target="#modalReportePDF"
 */
$rep_oficina_param = isset($rep_id_oficina) ? intval($rep_id_oficina) : 0;
?>
<!-- Modal: Reporte PDF por mes/anio -->
<div class="modal fade" id="modalReportePDF" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2 bg-danger text-white">
        <h6 class="modal-title mb-0"><i class="bi bi-file-earmark-pdf me-2"></i>Reporte de Movimientos</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="small text-muted mb-2">Selecciona el rango de fechas para descargar el detalle en PDF.</p>
        <div class="mb-2">
          <label class="form-label small fw-bold">Desde</label>
          <input type="date" id="rep_desde" class="form-control form-control-sm" value="<?php echo date('Y-m-01'); ?>">
        </div>
        <div class="mb-1">
          <label class="form-label small fw-bold">Hasta</label>
          <input type="date" id="rep_hasta" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>">
        </div>
        <div id="rep_error" class="text-danger small mt-2" style="display:none;"></div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" id="rep_generar" class="btn btn-danger btn-sm">
          <i class="bi bi-download me-1"></i>Generar PDF
        </button>
      </div>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('rep_generar');
    if (!btn) return;
    btn.addEventListener('click', function () {
        var desde = document.getElementById('rep_desde').value;
        var hasta = document.getElementById('rep_hasta').value;
        var err   = document.getElementById('rep_error');
        if (!desde || !hasta) {
            err.textContent = 'Selecciona ambas fechas (Desde y Hasta).';
            err.style.display = 'block';
            return;
        }
        if (desde > hasta) {
            err.textContent = 'La fecha "Desde" no puede ser mayor que "Hasta".';
            err.style.display = 'block';
            return;
        }
        err.style.display = 'none';
        var of  = <?php echo $rep_oficina_param; ?>;
        var url = 'reporte_movimientos.php?desde=' + desde + '&hasta=' + hasta + (of > 0 ? '&id_oficina=' + of : '');
        window.open(url, '_blank');
        var mEl = document.getElementById('modalReportePDF');
        if (window.bootstrap) {
            var inst = bootstrap.Modal.getInstance(mEl);
            if (inst) inst.hide();
        }
    });
});
</script>

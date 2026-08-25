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
        <p class="small text-muted mb-2">Selecciona el mes y año para descargar el detalle en PDF.</p>
        <div class="mb-2">
          <label class="form-label small fw-bold">Mes</label>
          <select id="rep_mes" class="form-select form-select-sm">
            <?php
            $meses_rep = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
                          7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
            $mes_actual = intval(date('n'));
            foreach ($meses_rep as $num => $nom):
                $sel = ($num == $mes_actual) ? 'selected' : ''; ?>
            <option value="<?php echo $num; ?>" <?php echo $sel; ?>><?php echo $nom; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-1">
          <label class="form-label small fw-bold">Año</label>
          <select id="rep_anio" class="form-select form-select-sm">
            <?php
            $anio_actual = intval(date('Y'));
            for ($a = $anio_actual; $a >= $anio_actual - 3; $a--):
                $sel = ($a == $anio_actual) ? 'selected' : ''; ?>
            <option value="<?php echo $a; ?>" <?php echo $sel; ?>><?php echo $a; ?></option>
            <?php endfor; ?>
          </select>
        </div>
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
        var mes  = document.getElementById('rep_mes').value;
        var anio = document.getElementById('rep_anio').value;
        var of   = <?php echo $rep_oficina_param; ?>;
        var url  = 'reporte_movimientos.php?mes=' + mes + '&anio=' + anio + (of > 0 ? '&id_oficina=' + of : '');
        window.open(url, '_blank');
        var mEl = document.getElementById('modalReportePDF');
        if (window.bootstrap) {
            var inst = bootstrap.Modal.getInstance(mEl);
            if (inst) inst.hide();
        }
    });
});
</script>

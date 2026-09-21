<?php
/*
 * Modal reutilizable para exportar movimientos a Excel (CSV).
 * Uso:
 *   $export_es_admin  = ($id_rol == 3 || $id_rol == 4);  // opcional (default false)
 *   $export_id_oficina = <ID_OFICINA por defecto>;       // opcional (default sesión)
 *   include 'modal_export_excel.php';
 * El botón que lo abre: data-bs-toggle="modal" data-bs-target="#modalExportExcel"
 * Requiere Bootstrap 5. No depende de jQuery.
 */
$exp_es_admin = isset($export_es_admin) ? (bool)$export_es_admin : false;
$exp_oficina  = isset($export_id_oficina) ? intval($export_id_oficina) : intval($_SESSION['oficina_ID'] ?? 0);

// Lista de cajas para el selector (solo admin)
$exp_cajas = [];
if ($exp_es_admin && isset($conn)) {
    $rc = $conn->query("SELECT ID_OFICINA, OFICINA FROM CTR_OFICINA ORDER BY OFICINA ASC");
    if ($rc) $exp_cajas = $rc->fetch_all(MYSQLI_ASSOC);
}
?>
<div class="modal fade" id="modalExportExcel" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2 bg-success text-white">
        <h6 class="modal-title mb-0"><i class="bi bi-file-earmark-excel me-2"></i>Exportar a Excel</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-2">
          <?php if ($exp_es_admin): ?>
          <div class="col-12">
            <label class="form-label small fw-bold mb-1">Caja</label>
            <select id="exp_oficina" class="form-select form-select-sm">
              <option value="<?php echo $exp_oficina; ?>">— Esta caja —</option>
              <option value="ALL">TODAS las cajas (incluye Mensajería)</option>
              <?php foreach ($exp_cajas as $c): ?>
              <option value="<?php echo (int)$c['ID_OFICINA']; ?>"><?php echo htmlspecialchars($c['OFICINA']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
          <div class="col-6">
            <label class="form-label small fw-bold mb-1">Desde</label>
            <input type="date" id="exp_desde" class="form-control form-control-sm">
          </div>
          <div class="col-6">
            <label class="form-label small fw-bold mb-1">Hasta</label>
            <input type="date" id="exp_hasta" class="form-control form-control-sm">
          </div>
          <div class="col-6">
            <label class="form-label small fw-bold mb-1">Tipo</label>
            <select id="exp_tipo" class="form-select form-select-sm">
              <option value="">Todos</option>
              <option value="Ingreso">Ingresos (+)</option>
              <option value="Egreso">Egresos (-)</option>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label small fw-bold mb-1">Estado</label>
            <select id="exp_estado" class="form-select form-select-sm">
              <option value="A" selected>Activos</option>
              <option value="">Todos</option>
              <option value="I">Anulados</option>
            </select>
          </div>
        </div>
        <p class="small text-muted mb-0 mt-2">Deja las fechas vacías para exportar todo el histórico.</p>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" id="exp_generar" class="btn btn-success btn-sm">
          <i class="bi bi-download me-1"></i>Descargar Excel
        </button>
      </div>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('exp_generar');
    if (!btn) return;
    var ofiFija = '<?php echo $exp_oficina; ?>';
    btn.addEventListener('click', function () {
        var params = [];
        var selOf = document.getElementById('exp_oficina');
        var of = selOf ? selOf.value : ofiFija;
        if (of) params.push('id_oficina=' + encodeURIComponent(of));
        var desde = document.getElementById('exp_desde').value;
        var hasta = document.getElementById('exp_hasta').value;
        var tipo  = document.getElementById('exp_tipo').value;
        var estado= document.getElementById('exp_estado').value;
        if (desde) params.push('desde=' + desde);
        if (hasta) params.push('hasta=' + hasta);
        if (tipo)  params.push('tipo=' + encodeURIComponent(tipo));
        params.push('estado=' + encodeURIComponent(estado));
        // Descarga (no navega): el servidor responde con attachment
        window.location.href = 'exportar_movimientos.php?' + params.join('&');
        var mEl = document.getElementById('modalExportExcel');
        if (window.bootstrap) { var inst = bootstrap.Modal.getInstance(mEl); if (inst) inst.hide(); }
    });
});
</script>

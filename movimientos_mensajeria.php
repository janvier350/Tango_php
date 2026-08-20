<?php
date_default_timezone_set('America/Guayaquil');
require_once 'config.php';
require_once 'auth.php';
verificar_auth();
$conn->set_charset("utf8");
header('Content-Type: text/html; charset=utf-8');

$id_rol     = $_SESSION["user_rol"];
$id_usuario = $_SESSION["user_id"];
$id_oficina = intval($_SESSION["oficina_ID"]);

// Esta pantalla es solo para cajas independientes (ej. Caja Mensajeria).
// Si el usuario no opera una caja independiente, lo mandamos al registro normal.
$es_indep = 0;
if (col_existe($conn, 'CTR_OFICINA', 'ES_INDEPENDIENTE')) {
    $r = $conn->query("SELECT ES_INDEPENDIENTE FROM CTR_OFICINA WHERE ID_OFICINA = " . $id_oficina);
    $es_indep = $r ? (int)($r->fetch_assoc()['ES_INDEPENDIENTE'] ?? 0) : 0;
}
if (!$es_indep && !in_array($id_rol, [3, 4])) {
    header("Location: movimientos.php");
    exit;
}

// --- Insercion ---
$error = '';
if (isset($_POST['reg_mov'])) {
    $fecha    = $_POST['f'] ?? date('Y-m-d');
    $concepto = trim($_POST['c'] ?? '');
    $proveedor= trim($_POST['prov'] ?? '');
    $empresa  = trim($_POST['emp'] ?? '');
    $doc      = trim($_POST['doc'] ?? '');
    $inf_fin  = !empty($_POST['id_reposicion']) ? intval($_POST['id_reposicion']) : null;
    $monto    = floatval($_POST['m'] ?? 0);
    $tipo     = ($_POST['tipo_flujo'] ?? 'Egreso');

    if ($concepto === '' || $monto <= 0) {
        $error = 'Ingresa el concepto y un monto mayor a 0.';
    } else {
        // Duplicado de documento dentro de esta misma caja (solo si hay doc)
        if ($doc !== '') {
            $chk = $conn->prepare("SELECT id FROM movimientos WHERE doc_soporte = ? AND ID_OFICINA = ?");
            $chk->bind_param("si", $doc, $id_oficina);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $error = "El documento \"" . htmlspecialchars($doc) . "\" ya está registrado en esta caja.";
            }
        }
    }

    if ($error === '') {
        $imp_rec = ($tipo === 'Ingreso') ? $monto : 0;
        $imp_ent = ($tipo === 'Egreso')  ? $monto : 0;
        $periodo = date("Y-n", strtotime($fecha));
        $fecha_reg = date("Y-m-d H:i:s");
        $estado = 'A';
        // ID_PROYECTO es NOT NULL en la base: esta caja no usa proyecto, se guarda 0.
        $banco = ''; $cheque = ''; $vale = ''; $intermediario2 = ''; $id_proyecto = 0;

        $stmt = $conn->prepare("INSERT INTO movimientos
            (fecha, empresa, concepto, intermediario, importe_recibido, importe_entregado,
             vale_ref, doc_soporte, inf_fin, periodo, banco, cheque_num, ID_USUARIO,
             FECHA_REGISTRO_I_E, ID_PROYECTO, intermediario2, ID_OFICINA, ESTADO)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("ssssddssssssisisis",
            $fecha, $empresa, $concepto, $proveedor, $imp_rec, $imp_ent,
            $vale, $doc, $inf_fin, $periodo, $banco, $cheque, $id_usuario,
            $fecha_reg, $id_proyecto, $intermediario2, $id_oficina, $estado);

        if ($stmt->execute()) {
            header("Location: movimientos_mensajeria.php?msg=ok");
            exit;
        } else {
            $error = 'Error al guardar: ' . htmlspecialchars($stmt->error);
        }
    }
}

// Nombre de la caja
$nombre_caja = $_SESSION["oficina"];

// Cuentas (Inf. Financiera) de esta caja
$cuentas = [];
$res_c = $conn->query("SELECT b.ID_REPOSICION, b.REPOSICION
                       FROM CAT_REPOSICION b
                       INNER JOIN cat_reposiciones a ON b.ID_CAT_REPOCICIONES = a.id
                       WHERE a.nombre = 'CAJA MENSAJERIA'
                       ORDER BY b.REPOSICION ASC");
if ($res_c) $cuentas = $res_c->fetch_all(MYSQLI_ASSOC);

// Empresas (para el selector con busqueda)
$empresas = [];
$res_e = $conn->query("SELECT nombre FROM cat_empresas ORDER BY nombre ASC");
if ($res_e) $empresas = $res_e->fetch_all(MYSQLI_ASSOC);

// Movimientos de la caja (con saldo acumulado)
$movs = [];
$stmt_l = $conn->prepare("SELECT m.*, r.REPOSICION AS cuenta,
                             u.usuario AS revisor
                          FROM movimientos m
                          LEFT JOIN CAT_REPOSICION r ON m.inf_fin = r.ID_REPOSICION
                          LEFT JOIN usuarios u ON m.ID_USUARIO_REVISA = u.id
                          WHERE m.ID_OFICINA = ?
                          ORDER BY m.fecha ASC, m.id ASC");
$stmt_l->bind_param("i", $id_oficina);
$stmt_l->execute();
$movs = $stmt_l->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <title>TANGO | Caja Mensajería</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="estilos.css">
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>

    <div class="container-fluid px-3 mt-3">

        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h4 class="fw-bold mb-0"><i class="bi bi-scooter me-2"></i>Caja
                <span class="badge bg-dark"><?php echo htmlspecialchars($nombre_caja); ?></span>
            </h4>
            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#modalReportePDF">
                <i class="bi bi-file-earmark-pdf me-1"></i>Descargar PDF
            </button>
        </div>

        <?php if (isset($_GET['msg']) && $_GET['msg']==='ok'): ?>
        <div class="alert alert-success alert-dismissible fade show py-2">
            <i class="bi bi-check-circle me-1"></i>Movimiento registrado.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show py-2">
            <i class="bi bi-exclamation-triangle me-1"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Formulario simplificado -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <form method="POST" class="row g-2 align-items-end">
                    <div class="col-6 col-md-2">
                        <label class="small fw-bold">Fecha</label>
                        <input type="date" name="f" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="small fw-bold">Cuenta</label>
                        <select name="id_reposicion" class="form-select form-select-sm">
                            <option value="">— Seleccionar —</option>
                            <?php foreach ($cuentas as $c): ?>
                            <option value="<?php echo $c['ID_REPOSICION']; ?>"><?php echo htmlspecialchars($c['REPOSICION']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="small fw-bold">Concepto</label>
                        <input type="text" name="c" class="form-control form-control-sm" placeholder="Detalle del gasto/ingreso" required>
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="small fw-bold">Proveedor</label>
                        <input type="text" name="prov" class="form-control form-control-sm" placeholder="Ej: ATIMASA S.A.">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="small fw-bold">Doc. Soporte</label>
                        <input type="text" name="doc" class="form-control form-control-sm" placeholder="Factura / Vale #">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="small fw-bold">Empresa</label>
                        <select name="emp" id="sel_empresa" class="form-select form-select-sm select2-buscable">
                            <option value="">— Seleccionar —</option>
                            <?php foreach ($empresas as $e): ?>
                            <option value="<?php echo htmlspecialchars($e['nombre']); ?>"><?php echo htmlspecialchars($e['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="small fw-bold">Tipo</label>
                        <select name="tipo_flujo" class="form-select form-select-sm">
                            <option value="Egreso">Gasto (-)</option>
                            <option value="Ingreso">Ingreso / Reposición (+)</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="small fw-bold">Monto</label>
                        <input type="number" step="0.01" min="0" name="m" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-12 col-md-2 d-grid">
                        <button type="submit" name="reg_mov" class="btn btn-dark btn-sm fw-bold">
                            <i class="bi bi-plus-lg me-1"></i>Registrar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="bg-white shadow-sm p-2 mb-2 d-flex gap-3 small text-muted flex-wrap">
            <span><i class="bi bi-person me-1"></i><?php echo htmlspecialchars($_SESSION["user_name"]); ?></span>
            <span><i class="bi bi-building me-1"></i><?php echo htmlspecialchars($_SESSION["oficina"]); ?></span>
        </div>

        <!-- Listado -->
        <div class="bg-white shadow-sm p-2 table-responsive-movil">
            <table class="table table-sm table-bordered table-hover" style="font-size:.8rem;">
                <thead class="table-secondary text-center">
                    <tr>
                        <th>#</th><th>FECHA</th><th>CUENTA</th><th>CONCEPTO</th>
                        <th>PROVEEDOR</th><th>EMPRESA</th><th>DOC.</th>
                        <th>INGRESO (+)</th><th>GASTO (-)</th><th class="table-primary">SALDO</th>
                        <th>ESTADO</th><th>ACCIONES</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $saldo = 0;
                foreach ($movs as $m):
                    $anulado  = ($m['ESTADO'] == 'I');
                    $aprobado = ($m['ID_USUARIO_REVISA'] > 0);
                    if (!$anulado) $saldo += ($m['importe_recibido'] - $m['importe_entregado']);
                ?>
                    <tr style="<?php echo $anulado ? 'background:#f8d7da;opacity:.6;text-decoration:line-through;' : ''; ?>">
                        <td class="text-center"><?php echo $m['id']; ?></td>
                        <td class="text-center"><?php echo date('d-m-Y', strtotime($m['fecha'])); ?></td>
                        <td><?php echo htmlspecialchars($m['cuenta'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($m['concepto']); ?></td>
                        <td><?php echo htmlspecialchars($m['intermediario']); ?></td>
                        <td><?php echo htmlspecialchars($m['empresa']); ?></td>
                        <td class="text-center"><?php echo htmlspecialchars($m['doc_soporte']); ?></td>
                        <td class="text-end text-success"><?php echo $m['importe_recibido'] > 0 ? '$'.number_format($m['importe_recibido'],2) : '-'; ?></td>
                        <td class="text-end text-danger"><?php echo $m['importe_entregado'] > 0 ? '$'.number_format($m['importe_entregado'],2) : '-'; ?></td>
                        <td class="text-end fw-bold bg-light <?php echo $saldo>=0?'text-success':'text-danger'; ?>">$<?php echo number_format($saldo,2); ?></td>
                        <td class="text-center">
                            <?php if ($aprobado): ?>
                                <span class="badge bg-success"><i class="bi bi-check-all"></i> Aprobado</span>
                            <?php elseif ($anulado): ?>
                                <span class="badge bg-danger">Anulado</span>
                            <?php else: ?>
                                <span class="text-muted small"><i>Pendiente</i></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <div class="btn-group" role="group">
                                <a href="imprimir_vale.php?id=<?php echo $m['id']; ?>" target="_blank" title="Ver PDF"
                                   class="btn btn-outline-primary btn-sm <?php echo $anulado ? 'disabled' : ''; ?>">
                                    <i class="bi bi-file-earmark-pdf"></i>
                                </a>
                                <?php if (!$aprobado && !$anulado): ?>
                                <a href="editar_movimiento.php?id=<?php echo $m['id']; ?>" title="Editar"
                                   class="btn btn-outline-warning btn-sm">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <a href="anular_movimiento.php?id=<?php echo $m['id']; ?>&return=movimientos_mensajeria.php"
                                   title="Anular" class="btn btn-outline-danger btn-sm"
                                   onclick="return confirm('¿Seguro de ANULAR este registro? El valor ya no contará en el saldo.');">
                                    <i class="bi bi-x-circle"></i>
                                </a>
                                <?php elseif ($aprobado): ?>
                                <span class="btn btn-sm btn-light disabled" title="Aprobado: no editable">
                                    <i class="bi bi-lock-fill text-muted"></i>
                                </span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($movs)): ?>
                    <tr><td colspan="12" class="text-center text-muted py-3">Aún no hay movimientos en esta caja.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

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

<!-- Modal: Nueva Empresa -->
<div class="modal fade" id="modalNuevaEmpresa" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header py-2 bg-dark text-white">
        <h6 class="modal-title mb-0"><i class="bi bi-building me-2"></i>Nueva Empresa</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
          <label class="form-label small fw-bold">Nombre de la Empresa</label>
          <input type="text" id="ne_nombre" class="form-control form-control-sm text-uppercase"
                 placeholder="Ej: BUADNET..." autocomplete="off">
          <div id="ne_feedback" class="form-text mt-1"></div>
        </div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" id="ne_guardar" class="btn btn-dark btn-sm">
          <i class="bi bi-check-lg me-1"></i>Guardar
        </button>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    $('.select2-buscable').select2({ placeholder: "Buscar empresa...", allowClear: true, width: '100%', theme: "classic" });

    // --- Reporte PDF por mes/anio ---
    $('#rep_generar').on('click', function() {
        var mes  = $('#rep_mes').val();
        var anio = $('#rep_anio').val();
        window.open('reporte_movimientos.php?mes=' + mes + '&anio=' + anio, '_blank');
        bootstrap.Modal.getInstance(document.getElementById('modalReportePDF')).hide();
    });

    // --- Modal Nueva Empresa ---
    var checkTimerEmpresa;
    $('#modalNuevaEmpresa').on('show.bs.modal', function() {
        $('#ne_nombre').val('').removeClass('is-valid is-invalid');
        $('#ne_feedback').text('').removeClass('text-success text-danger');
        $('#ne_guardar').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Guardar');
    });
    $('#modalNuevaEmpresa').on('shown.bs.modal', function() { $('#ne_nombre').focus(); });

    $('#ne_nombre').on('input', function() {
        var val = $(this).val().trim();
        clearTimeout(checkTimerEmpresa);
        $(this).removeClass('is-valid is-invalid');
        $('#ne_feedback').text('').removeClass('text-success text-danger');
        if (val.length < 2) return;
        checkTimerEmpresa = setTimeout(function() {
            $.post('ajax_empresa.php', { action: 'check', nombre: val }, function(res) {
                if (res.exists) {
                    $('#ne_nombre').addClass('is-invalid');
                    $('#ne_feedback').text('⚠ Ya existe una empresa con ese nombre.').addClass('text-danger');
                } else {
                    $('#ne_nombre').addClass('is-valid');
                    $('#ne_feedback').text('✓ Disponible.').addClass('text-success');
                }
            }, 'json');
        }, 500);
    });

    $('#ne_guardar').on('click', function() {
        var nombre = $('#ne_nombre').val().trim();
        if (nombre.length < 2) {
            $('#ne_nombre').addClass('is-invalid');
            $('#ne_feedback').text('Ingresa el nombre de la empresa.').addClass('text-danger');
            return;
        }
        if ($('#ne_nombre').hasClass('is-invalid')) return;
        $('#ne_guardar').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
        $.post('ajax_empresa.php', { action: 'crear', nombre: nombre }, function(res) {
            if (res.success) {
                $('#sel_empresa').append('<option value="'+res.nombre+'">'+res.nombre+'</option>');
                $('#sel_empresa').val(res.nombre).trigger('change');
                bootstrap.Modal.getInstance(document.getElementById('modalNuevaEmpresa')).hide();
            } else {
                $('#ne_nombre').addClass('is-invalid');
                $('#ne_feedback').text(res.msg).addClass('text-danger');
                $('#ne_guardar').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Guardar');
            }
        }, 'json').fail(function() {
            $('#ne_guardar').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Guardar');
            alert('Error de conexión. Intenta nuevamente.');
        });
    });
});
</script>
</body>
</html>

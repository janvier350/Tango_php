<?php
date_default_timezone_set('America/Guayaquil');
require_once 'config.php';
require_once 'auth.php';
verificar_auth();

$id = intval($_GET['id']);

// 1. CARGAR DATOS ACTUALES
$stmt = $conn->prepare("SELECT * FROM movimientos WHERE id = ? AND ID_USUARIO = ?");
$stmt->bind_param("ii", $id, $_SESSION["user_id"]);
$stmt->execute();
$m = $stmt->get_result()->fetch_assoc();

if (!$m) { die("Acceso denegado o registro no encontrado."); }

// ¿El usuario opera una caja independiente (ej. Caja Mensajería)?
// En ese caso mostramos el formulario simplificado, igual al de registro.
$es_indep = !empty($_SESSION['oficina_independiente']);

// No se puede editar un movimiento ya aprobado o anulado (defensa server-side)
if ($m['ID_USUARIO_REVISA'] > 0 || $m['ESTADO'] === 'I') {
    $destino = $es_indep ? 'movimientos_mensajeria.php' : 'movimientos.php';
    header("Location: $destino?msg=no_editable");
    exit;
}

// 2. LÓGICA DE ACTUALIZACIÓN
if (isset($_POST['update_mov'])) {
    $periodo = date("Y-n", strtotime($_POST['f']));
    $imp_recibido = ($_POST['tipo_flujo'] == 'Ingreso') ? $_POST['m'] : 0;
    $imp_entregado = ($_POST['tipo_flujo'] == 'Egreso') ? $_POST['m'] : 0;

    // Campos que la caja de mensajería NO usa: se conservan los valores actuales
    // del registro cuando el formulario simplificado no los envía.
    if (isset($_POST['id_proyecto'])) {
        $id_proyecto = !empty($_POST['id_proyecto']) ? $_POST['id_proyecto'] : NULL;
    } else {
        $id_proyecto = $m['ID_PROYECTO']; // conserva 0 en cajas independientes
    }
    $intermediario2 = $_POST['intermediario2'] ?? $m['INTERMEDIARIO2'];
    $banco          = $_POST['ban']            ?? $m['banco'];
    $cheque         = $_POST['chq']            ?? $m['cheque_num'];

    $sql = "UPDATE movimientos SET
            fecha=?, empresa=?, concepto=?, intermediario=?,
            importe_recibido=?, importe_entregado=?, doc_soporte=?,
            inf_fin=?, periodo=?, banco=?, cheque_num=?,
            ID_PROYECTO=?, INTERMEDIARIO2=?
            WHERE id=? AND ID_USUARIO=?";

    $stmt_up = $conn->prepare($sql);
    $stmt_up->bind_param("ssssddsssssissi",
        $_POST['f'], $_POST['emp'], $_POST['c'], $_POST['inter'],
        $imp_recibido, $imp_entregado, $_POST['doc'],
        $_POST['id_reposicion'], $periodo, $banco, $cheque,
        $id_proyecto, $intermediario2, $id, $_SESSION["user_id"]
    );

    if ($stmt_up->execute()) {
        $destino = $es_indep ? 'movimientos_mensajeria.php' : 'movimientos.php';
        header("Location: $destino?msg=editado");
        exit();
    } else {
        $error = "Error al actualizar: " . $conn->error;
    }
}

// Datos para el formulario simplificado (caja independiente / mensajería)
if ($es_indep) {
    // Cuentas (Inf. Financiera) de la caja mensajería
    $cuentas_men = [];
    $res_cm = $conn->query("SELECT b.ID_REPOSICION, b.REPOSICION
                            FROM CAT_REPOSICION b
                            INNER JOIN cat_reposiciones a ON b.ID_CAT_REPOCICIONES = a.id
                            WHERE a.nombre = 'CAJA MENSAJERIA'
                            ORDER BY b.REPOSICION ASC");
    if ($res_cm) $cuentas_men = $res_cm->fetch_all(MYSQLI_ASSOC);

    // Empresas (para el selector con búsqueda)
    $empresas_men = [];
    $res_em = $conn->query("SELECT nombre FROM cat_empresas ORDER BY nombre ASC");
    if ($res_em) $empresas_men = $res_em->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <title>Editar Movimiento #<?php echo $id; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>
    <div class="container mt-4 mb-5">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-warning text-dark fw-bold">MODIFICAR REGISTRO #<?php echo $id; ?></div>
            <div class="card-body">
                <?php if ($es_indep): ?>
                <!-- ============ Formulario simplificado: Caja Mensajería ============ -->
                <!-- Mismos campos que el registro; se ocultan Intermediario, Proyecto, Banco y Cheque. -->
                <form method="POST" class="row g-3">

                    <div class="col-md-2">
                        <label class="small fw-bold">Fecha</label>
                        <input type="date" name="f" class="form-control form-control-sm" value="<?php echo $m['fecha']; ?>" required>
                    </div>

                    <div class="col-md-3">
                        <label class="small fw-bold">Cuenta</label>
                        <select name="id_reposicion" class="form-select form-select-sm">
                            <option value="">— Seleccionar —</option>
                            <?php foreach ($cuentas_men as $c):
                                $selected = ($c['ID_REPOSICION'] == $m['inf_fin']) ? 'selected' : ''; ?>
                            <option value="<?php echo $c['ID_REPOSICION']; ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($c['REPOSICION']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="small fw-bold">Concepto</label>
                        <input type="text" name="c" class="form-control form-control-sm" value="<?php echo htmlspecialchars($m['concepto']); ?>" placeholder="Detalle del gasto/ingreso" required>
                    </div>

                    <div class="col-md-3">
                        <label class="small fw-bold">Proveedor</label>
                        <input type="text" name="inter" class="form-control form-control-sm" value="<?php echo htmlspecialchars($m['intermediario']); ?>" placeholder="Ej: ATIMASA S.A.">
                    </div>

                    <div class="col-md-3">
                        <label class="small fw-bold d-flex align-items-center gap-1">Empresa
                            <button type="button" class="btn btn-success btn-sm py-0 px-1 lh-1" style="font-size:0.75rem;"
                                data-bs-toggle="modal" data-bs-target="#modalNuevaEmpresa"
                                title="Agregar nueva empresa">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        </label>
                        <select name="emp" id="sel_empresa" class="form-select form-select-sm select2-buscable">
                            <option value="">— Seleccionar —</option>
                            <?php foreach ($empresas_men as $e):
                                $selected = ($e['nombre'] == $m['empresa']) ? 'selected' : ''; ?>
                            <option value="<?php echo htmlspecialchars($e['nombre']); ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($e['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="small fw-bold">Doc. Soporte</label>
                        <input type="text" name="doc" class="form-control form-control-sm" value="<?php echo htmlspecialchars($m['doc_soporte']); ?>" placeholder="Factura / Vale #">
                    </div>

                    <div class="col-md-2">
                        <label class="small fw-bold">Tipo</label>
                        <select name="tipo_flujo" class="form-select form-select-sm">
                            <option value="Egreso" <?php if($m['importe_entregado'] > 0) echo 'selected'; ?>>Gasto (-)</option>
                            <option value="Ingreso" <?php if($m['importe_recibido'] > 0) echo 'selected'; ?>>Ingreso / Reposición (+)</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="small fw-bold">Monto</label>
                        <input type="number" step="0.01" min="0" name="m" class="form-control form-control-sm" value="<?php echo max($m['importe_recibido'], $m['importe_entregado']); ?>" required>
                    </div>

                    <div class="col-12 text-end border-top pt-3 mt-4">
                        <a href="movimientos_mensajeria.php" class="btn btn-sm btn-secondary px-4">Cancelar</a>
                        <button type="submit" name="update_mov" class="btn btn-sm btn-warning px-4 fw-bold">ACTUALIZAR REGISTRO</button>
                    </div>
                </form>
                <?php else: ?>
                <!-- ============ Formulario completo (oficinas normales) ============ -->
                <form method="POST" class="row g-3">

                    <div class="col-md-2">
                        <label class="small fw-bold">Fecha</label>
                        <input type="date" name="f" class="form-control form-control-sm" value="<?php echo $m['fecha']; ?>" required>
                    </div>

                    <div class="col-md-3">
                        <label class="small fw-bold text-primary">Beneficiario</label>
                        <select name="inter" class="form-select form-select-sm buscable" required>
                            <?php
                            $res_ben = $conn->query("SELECT RAZON_SOCIAL FROM BENEFICIARIO ORDER BY RAZON_SOCIAL ASC");
                            while($ben = $res_ben->fetch_assoc()) {
                                $selected = ($ben['RAZON_SOCIAL'] == $m['intermediario']) ? 'selected' : '';
                                echo "<option value='".htmlspecialchars($ben['RAZON_SOCIAL'])."' $selected>{$ben['RAZON_SOCIAL']}</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="small fw-bold text-primary">Intermediario</label>
                        <select name="intermediario2" class="form-select form-select-sm buscable" required>
                            <?php
                            $res_int = $conn->query("SELECT RAZON_SOCIAL FROM BENEFICIARIO ORDER BY RAZON_SOCIAL ASC");
                            while($int = $res_int->fetch_assoc()) {
                                $selected = ($int['RAZON_SOCIAL'] == $m['INTERMEDIARIO2']) ? 'selected' : '';
                                echo "<option value='".htmlspecialchars($int['RAZON_SOCIAL'])."' $selected>{$int['RAZON_SOCIAL']}</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="small fw-bold text-primary">Proyecto</label>
                        <select name="id_proyecto" class="form-select form-select-sm buscable">
                            <option value="">Sin Proyecto</option>
                            <?php
                            $res_proy = $conn->query("SELECT ID_PROYECTO, PROYECTO FROM PROYECTOS ORDER BY PROYECTO ASC");
                            while($p = $res_proy->fetch_assoc()) {
                                $selected = ($p['ID_PROYECTO'] == $m['ID_PROYECTO']) ? 'selected' : '';
                                echo "<option value='{$p['ID_PROYECTO']}' $selected>{$p['PROYECTO']}</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="small fw-bold">Empresa</label>
                        <select name="emp" class="form-select form-select-sm">
                            <?php
                            $res_emp = $conn->query("SELECT nombre FROM cat_empresas");
                            while($e = $res_emp->fetch_assoc()) {
                                $selected = ($e['nombre'] == $m['empresa']) ? 'selected' : '';
                                echo "<option $selected>{$e['nombre']}</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="small fw-bold">Descripción</label>
                        <input type="text" name="c" class="form-control form-control-sm" value="<?php echo htmlspecialchars($m['concepto']); ?>" required>
                    </div>

                    <div class="col-md-2">
                        <label class="small fw-bold">Tipo</label>
                        <select name="tipo_flujo" class="form-select form-select-sm">
                            <option value="Egreso" <?php if($m['importe_entregado'] > 0) echo 'selected'; ?>>Entrega (-)</option>
                            <option value="Ingreso" <?php if($m['importe_recibido'] > 0) echo 'selected'; ?>>Recibe (+)</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="small fw-bold">Monto</label>
                        <input type="number" step="0.01" name="m" class="form-control form-control-sm" value="<?php echo max($m['importe_recibido'], $m['importe_entregado']); ?>" required>
                    </div>

                    <div class="col-md-3">
                        <label class="small fw-bold">Doc. Soporte</label>
                        <input type="text" name="doc" class="form-control form-control-sm" value="<?php echo htmlspecialchars($m['doc_soporte']); ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="small fw-bold text-primary">INF. FINANCIERA</label>
                        <select name="id_reposicion" class="form-select form-select-sm buscable" required>
                            <?php
                            $sql_rep = "SELECT a.nombre AS categoria, b.REPOSICION AS detalle, b.ID_REPOSICION
                                        FROM cat_reposiciones a
                                        INNER JOIN CAT_REPOSICION b ON a.id = b.ID_CAT_REPOCICIONES
                                        ORDER BY a.nombre ASC";
                            $res_rep = $conn->query($sql_rep);
                            while($row = $res_rep->fetch_assoc()) {
                                $selected = ($row['ID_REPOSICION'] == $m['inf_fin']) ? 'selected' : '';
                                echo "<option value='{$row['ID_REPOSICION']}' $selected>{$row['categoria']} - {$row['detalle']}</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="small fw-bold">Banco</label>
                        <select name="ban" class="form-select form-select-sm">
                            <option value="EFECTIVO" <?php if($m['banco'] == 'EFECTIVO') echo 'selected'; ?>>Efectivo</option>
                            <?php
                            $res_ban = $conn->query("SELECT nombre FROM cat_bancos");
                            while($ba = $res_ban->fetch_assoc()) {
                                $selected = ($ba['nombre'] == $m['banco']) ? 'selected' : '';
                                echo "<option $selected>{$ba['nombre']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="small fw-bold">CHEQUE #</label>
                        <input type="text" name="chq" class="form-control form-control-sm" value="<?php echo $m['cheque_num']; ?>">
                    </div>

                    <div class="col-12 text-end border-top pt-3 mt-4">
                        <a href="movimientos.php" class="btn btn-sm btn-secondary px-4">Cancelar</a>
                        <button type="submit" name="update_mov" class="btn btn-sm btn-warning px-4 fw-bold">ACTUALIZAR REGISTRO</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php if ($es_indep): ?>
<!-- Modal: Nueva Empresa (igual que en la caja de mensajería) -->
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
<?php endif; ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.buscable').select2({ theme: "classic", width: '100%' });
<?php if ($es_indep): ?>
            $('.select2-buscable').select2({ placeholder: "Buscar empresa...", allowClear: true, width: '100%', theme: "classic" });

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
<?php endif; ?>
        });
    </script>
</body>
</html>
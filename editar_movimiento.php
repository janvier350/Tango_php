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

// No se puede editar un movimiento ya aprobado o anulado (defensa server-side)
if ($m['ID_USUARIO_REVISA'] > 0 || $m['ESTADO'] === 'I') {
    $destino = !empty($_SESSION['oficina_independiente']) ? 'movimientos_mensajeria.php' : 'movimientos.php';
    header("Location: $destino?msg=no_editable");
    exit;
}

// 2. LÓGICA DE ACTUALIZACIÓN
if (isset($_POST['update_mov'])) {
    $periodo = date("Y-n", strtotime($_POST['f']));
    $imp_recibido = ($_POST['tipo_flujo'] == 'Ingreso') ? $_POST['m'] : 0;
    $imp_entregado = ($_POST['tipo_flujo'] == 'Egreso') ? $_POST['m'] : 0;
    $id_proyecto = !empty($_POST['id_proyecto']) ? $_POST['id_proyecto'] : NULL;

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
        $_POST['id_reposicion'], $periodo, $_POST['ban'], $_POST['chq'],
        $id_proyecto, $_POST['intermediario2'], $id, $_SESSION["user_id"]
    );

    if ($stmt_up->execute()) {
        $destino = !empty($_SESSION['oficina_independiente']) ? 'movimientos_mensajeria.php' : 'movimientos.php';
        header("Location: $destino?msg=editado");
        exit();
    } else {
        $error = "Error al actualizar: " . $conn->error;
    }
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
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>
    <div class="container mt-4 mb-5">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-warning text-dark fw-bold">MODIFICAR REGISTRO #<?php echo $id; ?></div>
            <div class="card-body">
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
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.buscable').select2({ theme: "classic", width: '100%' });
        });
    </script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
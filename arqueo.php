<?php
require_once 'config.php';
require_once 'auth.php';
verificar_auth();
date_default_timezone_set('America/Guayaquil');

$id_rol     = $_SESSION['user_rol'];
$id_usuario = $_SESSION['user_id'];

if (!in_array($id_rol, [2, 3, 4])) {
    header("Location: index.php");
    exit;
}

// ── Cajas disponibles (mismo criterio del home: oficinas con usuario operativo) ──
$sql_cajas = "SELECT o.ID_OFICINA, o.OFICINA,
                  SUM(CASE WHEN u.ID_ROL = 2 THEN 1 ELSE 0 END) AS num_recepcion
              FROM CTR_OFICINA o
              INNER JOIN usuarios u ON u.ID_CTR_OFICINA = o.ID_OFICINA
              WHERE u.ID_ROL NOT IN (1, 3)
              GROUP BY o.ID_OFICINA, o.OFICINA
              ORDER BY o.OFICINA ASC";
$res_cajas = $conn->query($sql_cajas);
$cajas = $res_cajas ? $res_cajas->fetch_all(MYSQLI_ASSOC) : [];

// ── Caja a arquear: Recepción solo la suya; CEO/Administración eligen ──
if ($id_rol == 2) {
    $id_oficina = intval($_SESSION['oficina_ID']);
} else {
    $id_oficina = intval($_GET['id_oficina'] ?? $_POST['id_oficina'] ?? $_SESSION['oficina_ID']);
}
$ids_validos = array_column($cajas, 'ID_OFICINA');
if (!in_array($id_oficina, $ids_validos) && !empty($ids_validos)) {
    $id_oficina = $ids_validos[0];
}

$nombre_caja = 'Caja';
foreach ($cajas as $c) {
    if ($c['ID_OFICINA'] == $id_oficina) {
        $nombre_caja = $c['num_recepcion'] == 0 ? 'Caja General' : 'Oficina - ' . $c['OFICINA'];
    }
}

// ── Saldo del sistema para esa caja (movimientos activos de la oficina) ──
$stmt_saldo = $conn->prepare("SELECT COALESCE(SUM(importe_recibido - importe_entregado), 0) AS saldo
                              FROM movimientos WHERE ID_OFICINA = ? AND ESTADO = 'A'");
$stmt_saldo->bind_param("i", $id_oficina);
$stmt_saldo->execute();
$saldo_sistema = (float)($stmt_saldo->get_result()->fetch_assoc()['saldo'] ?? 0);

// ── Guardar arqueo ───────────────────────────────────────────────────
$mensaje = ""; $tipo_msg = "success";
if (isset($_POST['guardar_arqueo'])) {
    $real = round(floatval($_POST['total_efectivo'] ?? 0), 2);
    $dif  = round($real - $saldo_sistema, 2);
    $obs  = trim($_POST['obs'] ?? '');

    $stmt_ins = $conn->prepare("INSERT INTO arqueos (ID_OFICINA, ID_USUARIO, total_efectivo_real, saldo_sistema, diferencia, observaciones)
                                VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt_ins) {
        $stmt_ins->bind_param("iiddds", $id_oficina, $id_usuario, $real, $saldo_sistema, $dif, $obs);
        if ($stmt_ins->execute()) {
            header("Location: arqueo.php?id_oficina=" . $id_oficina . "&msg=ok");
            exit;
        }
        $mensaje = "Error al guardar: " . htmlspecialchars($stmt_ins->error);
        $tipo_msg = "danger";
    } else {
        $mensaje = "Falta actualizar la tabla <code>arqueos</code> (columnas ID_OFICINA e ID_USUARIO). Ejecuta el ALTER TABLE indicado.";
        $tipo_msg = "warning";
    }
}
if (isset($_GET['msg']) && $_GET['msg'] === 'ok') {
    $mensaje = "Arqueo guardado exitosamente.";
}

// ── Historial de arqueos de esta caja ────────────────────────────────
$historial = [];
$res_hist = $conn->prepare("SELECT a.fecha, a.total_efectivo_real, a.saldo_sistema, a.diferencia, a.observaciones,
                                   COALESCE(u.nombres, '---') AS usuario
                            FROM arqueos a
                            LEFT JOIN usuarios u ON a.ID_USUARIO = u.id
                            WHERE a.ID_OFICINA = ?
                            ORDER BY a.fecha DESC LIMIT 10");
if ($res_hist) {
    $res_hist->bind_param("i", $id_oficina);
    $res_hist->execute();
    $historial = $res_hist->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <title>Arqueo de Caja | TANGO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="estilos.css">
    <script>
        const SALDO_SISTEMA = <?php echo json_encode(round($saldo_sistema, 2)); ?>;
        function calcular() {
            let total = 0;
            document.querySelectorAll('.denominacion').forEach(input => {
                let valor = parseFloat(input.getAttribute('data-valor'));
                let cantidad = parseFloat(input.value) || 0;
                total += valor * cantidad;
            });
            total = Math.round(total * 100) / 100;
            document.getElementById('total_efectivo').value = total.toFixed(2);
            document.getElementById('display_total').innerText = total.toFixed(2);

            let dif = Math.round((total - SALDO_SISTEMA) * 100) / 100;
            let elDif = document.getElementById('display_dif');
            elDif.innerText = '$' + dif.toFixed(2);
            elDif.className = dif < 0 ? 'text-danger fw-bold' : (dif > 0 ? 'text-warning fw-bold' : 'text-success fw-bold');
        }
        function confirmarArqueo() {
            let total = document.getElementById('total_efectivo').value;
            return confirm('¿Cerrar el arqueo de <?php echo htmlspecialchars($nombre_caja); ?> con un efectivo contado de $' + total + '?');
        }
    </script>
</head>
<body class="bg-light">
    <?php $id_rol == 4 ? include 'navbar_control.php' : include 'navbar.php'; ?>

    <div class="container py-3">

        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h4 class="fw-bold mb-0"><i class="bi bi-calculator me-2"></i>Arqueo de Caja
                <span class="badge bg-primary"><?php echo htmlspecialchars($nombre_caja); ?></span>
            </h4>

            <?php if ($id_rol != 2 && count($cajas) > 1): ?>
            <form method="GET" class="d-flex align-items-center gap-2">
                <label class="small fw-bold mb-0">Caja:</label>
                <select name="id_oficina" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($cajas as $c):
                        $nom = $c['num_recepcion'] == 0 ? 'Caja General' : 'Oficina - ' . $c['OFICINA']; ?>
                    <option value="<?php echo $c['ID_OFICINA']; ?>" <?php echo $c['ID_OFICINA'] == $id_oficina ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($nom); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php endif; ?>
        </div>

        <?php if ($mensaje): ?>
        <div class="alert alert-<?php echo $tipo_msg; ?> alert-dismissible fade show">
            <?php echo $mensaje; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header fw-bold text-white" style="background:#2c3e50;">
                        <i class="bi bi-cash-stack me-2"></i>CALCULADORA DE EFECTIVO
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <?php
                            $billetes = [100, 50, 20, 10, 5, 1, 0.50, 0.25, 0.10, 0.05, 0.01];
                            foreach($billetes as $b): ?>
                            <div class="col-6 col-lg-4 mb-2">
                                <label class="small fw-bold">$<?php echo number_format($b, 2); ?></label>
                                <input type="number" min="0" class="form-control form-control-sm denominacion"
                                       data-valor="<?php echo $b; ?>" oninput="calcular()" placeholder="0">
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow-sm border-top border-primary border-5">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-1">TOTAL EFECTIVO CONTADO</h6>
                        <h1 class="display-4 fw-bold text-dark">$<span id="display_total">0.00</span></h1>
                        <hr>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Saldo en Sistema (<?php echo htmlspecialchars($nombre_caja); ?>):</span>
                            <span class="fw-bold">$<?php echo number_format($saldo_sistema, 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Diferencia (Faltante/Sobrante):</span>
                            <span id="display_dif" class="fw-bold text-danger">$<?php echo number_format(0 - $saldo_sistema, 2); ?></span>
                        </div>
                        <form method="POST" class="mt-4" onsubmit="return confirmarArqueo();">
                            <input type="hidden" name="id_oficina" value="<?php echo $id_oficina; ?>">
                            <input type="hidden" name="total_efectivo" id="total_efectivo" value="0">
                            <textarea name="obs" class="form-control mb-2" rows="2"
                                      placeholder="Observaciones del arqueo (opcional)"></textarea>
                            <button name="guardar_arqueo" class="btn btn-primary w-100 fw-bold">
                                <i class="bi bi-lock-fill me-1"></i>CERRAR CAJA Y GUARDAR ARQUEO
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Historial ── -->
        <div class="card shadow-sm mt-4 mb-4">
            <div class="card-header fw-bold text-white d-flex align-items-center" style="background:#2c3e50;">
                <i class="bi bi-clock-history me-2"></i>Últimos arqueos de <?php echo htmlspecialchars($nombre_caja); ?>
                <span class="ms-auto badge bg-secondary"><?php echo count($historial); ?></span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th class="ps-3">Fecha</th>
                            <th>Realizado por</th>
                            <th class="text-end">Efectivo Real</th>
                            <th class="text-end">Saldo Sistema</th>
                            <th class="text-end">Diferencia</th>
                            <th>Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historial as $h):
                            $d = (float)$h['diferencia'];
                            $clase_dif = $d < 0 ? 'text-danger' : ($d > 0 ? 'text-warning' : 'text-success');
                        ?>
                        <tr>
                            <td class="ps-3 small"><?php echo date('d/m/Y H:i', strtotime($h['fecha'])); ?></td>
                            <td class="small"><?php echo htmlspecialchars($h['usuario']); ?></td>
                            <td class="text-end small fw-bold">$<?php echo number_format($h['total_efectivo_real'], 2); ?></td>
                            <td class="text-end small">$<?php echo number_format($h['saldo_sistema'], 2); ?></td>
                            <td class="text-end small fw-bold <?php echo $clase_dif; ?>">
                                $<?php echo number_format($d, 2); ?>
                            </td>
                            <td class="small text-muted"><?php echo htmlspecialchars($h['observaciones'] ?? ''); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($historial)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">Aún no hay arqueos registrados para esta caja.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

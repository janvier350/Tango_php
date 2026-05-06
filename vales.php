<?php
require_once 'config.php';
require_once 'auth.php';
verificar_auth();

if (isset($_POST['reg_vale'])) {
    $stmt = $conn->prepare("INSERT INTO vales_caja (fecha, entregado_a, concepto, monto, estado) VALUES (?,?,?,?,'Pendiente')");
    $stmt->bind_param("sssd", $_POST['vf'], $_POST['ve'], $_POST['vc'], $_POST['vm']);
    $stmt->execute();
    header("Location: vales.php"); exit;
}

if (isset($_GET['rendir'])) {
    $v = $conn->query("SELECT * FROM vales_caja WHERE id = " . intval($_GET['rendir']))->fetch_assoc();
    if ($v) {
        $ins = $conn->prepare("INSERT INTO movimientos (fecha, concepto, tipo, categoria, monto, observaciones) VALUES (?,?,'Egreso','Gastos Varios',?,?)");
        $concepto = "Rendición Vale #" . $v['id'] . " - " . $v['entregado_a'];
        $ins->bind_param("ssds", date('Y-m-d'), $concepto, $v['monto'], $v['concepto']);
        if ($ins->execute()) $conn->query("UPDATE vales_caja SET estado = 'Rendido' WHERE id = " . $v['id']);
    }
    header("Location: vales.php"); exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Vales</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header text-center">Nuevo Vale</div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="date" name="vf" class="form-control mb-2" value="<?php echo date('Y-m-d'); ?>" required>
                            <input type="text" name="ve" class="form-control mb-2" placeholder="Responsable" required>
                            <textarea name="vc" class="form-control mb-2" placeholder="Motivo"></textarea>
                            <input type="number" step="0.01" name="vm" class="form-control mb-3" placeholder="Monto $" required>
                            <button name="reg_vale" class="btn btn-primary w-100">EMITIR VALE</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">Vales Pendientes</div>
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Fecha</th><th>Responsable</th><th>Monto</th><th class="text-center">Acción</th></tr></thead>
                        <tbody>
                            <?php
                            $vales = $conn->query("SELECT * FROM vales_caja WHERE estado = 'Pendiente'");
                            while($v = $vales->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('d/m/y', strtotime($v['fecha'])); ?></td>
                                <td><b><?php echo $v['entregado_a']; ?></b><br><small><?php echo $v['concepto']; ?></small></td>
                                <td class="fw-bold">$<?php echo number_format($v['monto'], 2); ?></td>
                                <td class="text-center"><a href="?rendir=<?php echo $v['id']; ?>" class="btn btn-success btn-sm">Rendir</a></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php
require_once '../config.php';
require_once '../auth.php';
verificar_auth();

// Tarjetas de cajas dinámicas (mismo criterio que index.php):
// una caja = una oficina con al menos un usuario operativo (rol distinto de ADMIN y CEO).
// El saldo se calcula por los movimientos activos de esa oficina.
$tiene_indep = col_existe($conn, 'CTR_OFICINA', 'ES_INDEPENDIENTE');
$col_indep   = $tiene_indep ? 'o.ES_INDEPENDIENTE' : '0';
$sql_cajas = "SELECT o.ID_OFICINA, o.OFICINA, $col_indep AS es_independiente,
                  SUM(CASE WHEN u.ID_ROL = 2 THEN 1 ELSE 0 END) AS num_recepcion,
                  COALESCE((SELECT SUM(m.importe_recibido - m.importe_entregado)
                            FROM movimientos m
                            WHERE m.ID_OFICINA = o.ID_OFICINA AND m.ESTADO = 'A'), 0) AS saldo
              FROM CTR_OFICINA o
              INNER JOIN usuarios u ON u.ID_CTR_OFICINA = o.ID_OFICINA
              WHERE u.ID_ROL NOT IN (1, 3)
              GROUP BY o.ID_OFICINA, o.OFICINA, es_independiente
              ORDER BY es_independiente ASC, o.OFICINA ASC";
$res_cajas = $conn->query($sql_cajas);
$cajas_all = $res_cajas ? $res_cajas->fetch_all(MYSQLI_ASSOC) : [];
$cajas = array_filter($cajas_all, fn($c) => !$c['es_independiente']);
$cajas_indep = array_filter($cajas_all, fn($c) => $c['es_independiente']);
$total_caja_oficinas = 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <title>TANGO | Panel de Control</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../estilos.css">
</head>
<body>

  <?php
if (isset($_SESSION["user_rol"]) && $_SESSION["user_rol"] == 4) {
    include '../navbar_control.php';
} else {
    include '../navbar.php';
}
?>

    <div class="container">
        <div class="row g-4 justify-content-center">
            <?php foreach ($cajas as $caja):
                $total_caja_oficinas += $caja['saldo'];
                // Cajas operadas por recepcion en verde; caja de administracion (general) en azul
                $es_general = ($caja['num_recepcion'] == 0);
                $color_card = $es_general ? 'bg-primary' : 'bg-success';
                $titulo_card = $es_general ? 'Caja General' : 'Oficina - ' . $caja['OFICINA'];
            ?>
            <div class="col-md-4">
                <div class="card <?php echo $color_card; ?> text-white text-center p-4">
                    <a class="dropdown-item" href="../validar_movimientos.php?id_oficina=<?php echo $caja['ID_OFICINA']; ?>">
                    <h6 class="fw-bold"><?php echo htmlspecialchars($titulo_card); ?></h6>
                    <h2 class="display-5 fw-bold">$<?php echo number_format($caja['saldo'], 2); ?></h2>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($cajas)): ?>
            <div class="col-md-6">
                <div class="card bg-secondary text-white text-center p-4">
                    <h6 class="fw-bold">Sin cajas registradas</h6>
                    <p class="mb-0 small">Asigne una oficina a un usuario operativo para que aparezca su caja.</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <br>

        <div class="row g-4 justify-content-center">
            <div class="col-md-4">
                <div class="card bg-warning text-white text-center p-4">
                    <h6 class="fw-bold">Suma Oficinas</h6>
                    <h2 class="display-5 fw-bold">$ <?php echo number_format($total_caja_oficinas, 2); ?></h2>
                </div>
            </div>
        </div>

        <?php if (!empty($cajas_indep)): ?>
        <hr class="my-4">
        <p class="text-center text-muted small mb-3">
            <i class="bi bi-box-arrow-right me-1"></i>Cajas independientes (no se suman al total)
        </p>
        <div class="row g-4 justify-content-center">
            <?php foreach ($cajas_indep as $caja): ?>
            <div class="col-md-4">
                <div class="card bg-dark text-white text-center p-4">
                    <a class="dropdown-item" href="../validar_movimientos.php?id_oficina=<?php echo $caja['ID_OFICINA']; ?>">
                    <h6 class="fw-bold"><?php echo htmlspecialchars($caja['OFICINA']); ?>
                        <span class="badge bg-secondary ms-1" style="font-size:.6rem;">independiente</span>
                    </h6>
                    <h2 class="display-5 fw-bold">$<?php echo number_format($caja['saldo'], 2); ?></h2>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="text-center mt-5">
            <a class="dropdown-item" href="#">Usuario: <?php echo $_SESSION["user_name"]; ?></a>
            <a class="dropdown-item" href="#">ROL: <?php echo $_SESSION["oficina_ROL"]; ?></a>
            <a class="dropdown-item" href="#">OFICINA: <?php echo $_SESSION["oficina"]; ?></a>
        </div>
    </div>
</body>
</html>

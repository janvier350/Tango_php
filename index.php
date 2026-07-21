<?php
require_once 'config.php';
require_once 'auth.php';
verificar_auth();

// 1. Saldo Teórico (Sistema)
$res_sis = $conn->query("SELECT SUM(CASE WHEN tipo='Ingreso' THEN monto ELSE -monto END) as saldo FROM movimientos");
$saldo_sistema = $res_sis->fetch_assoc()['saldo'] ?? 0;

// 2. Último Efectivo Real (Arqueo)
$res_arq = $conn->query("SELECT * FROM arqueos ORDER BY fecha DESC LIMIT 1");
$ultimo_arqueo = $res_arq->fetch_assoc();
$efectivo_real = $ultimo_arqueo['total_efectivo_real'] ?? 0;
$faltante = $efectivo_real - $saldo_sistema;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <title>TANGO | Panel de Control</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
    
  <?php
// Aseg��rate de que la sesi��n est�� iniciada antes de usar $_SESSION
// session_start(); // (si no est�� ya en tu c��digo)

if (isset($_SESSION["user_rol"])) {
    if ($_SESSION["user_rol"] == 4) {
        include 'navbar_control.php';
    } elseif ($_SESSION["user_rol"] == 2) {
        include 'navbar.php';
    } else {
        // Opcional: un navbar por defecto para otros roles (1, 3, etc.)
        include 'navbar.php';
    }
} else {
    // Si no hay rol definido, puedes redirigir o mostrar un navbar gen��rico
    include 'navbar.php';
}
?>
    
    <div class="container">
        <?php
        // ACCESO ADMINISTRACION (4) Y CEO (3): tarjetas de cajas dinamicas
        // Una caja = una oficina con al menos un usuario operativo (rol distinto de ADMIN y CEO).
        // El saldo se calcula por los movimientos activos de esa oficina.
        if ($_SESSION["user_rol"] == 4 || $_SESSION["user_rol"] == 3) {
            // CEO y Administracion validan las cajas: ambos con enlace a la validacion
            $puede_validar = true;

            $sql_cajas = "SELECT o.ID_OFICINA, o.OFICINA,
                              SUM(CASE WHEN u.ID_ROL = 2 THEN 1 ELSE 0 END) AS num_recepcion,
                              COALESCE((SELECT SUM(m.importe_recibido - m.importe_entregado)
                                        FROM movimientos m
                                        WHERE m.ID_OFICINA = o.ID_OFICINA AND m.ESTADO = 'A'), 0) AS saldo
                          FROM CTR_OFICINA o
                          INNER JOIN usuarios u ON u.ID_CTR_OFICINA = o.ID_OFICINA
                          WHERE u.ID_ROL NOT IN (1, 3)
                          GROUP BY o.ID_OFICINA, o.OFICINA
                          ORDER BY o.OFICINA ASC";
            $res_cajas = $conn->query($sql_cajas);
            $cajas = $res_cajas ? $res_cajas->fetch_all(MYSQLI_ASSOC) : [];
            $total_caja_oficinas = 0;
        ?>
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
                    <?php if ($puede_validar): ?><a class="dropdown-item" href="validar_movimientos.php?id_oficina=<?php echo $caja['ID_OFICINA']; ?>"><?php endif; ?>
                    <h6 class="fw-bold"><?php echo htmlspecialchars($titulo_card); ?></h6>
                    <h2 class="display-5 fw-bold">$<?php echo number_format($caja['saldo'], 2); ?></h2>
                    <?php if ($puede_validar): ?></a><?php endif; ?>
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
        <?php } ?>

    
    
    
    <?php
    // ACCESO RECEPCION
        if ($_SESSION["user_rol"] == 2) { ?>
        
          <div class="row g-4">
            

            <div class="col-md-4">
                <!--<div class="card bg-dark text-white text-center p-4">
                     <h6 class="fw-bold">SALDO CONTABLE (Sistema)</h6>
                    <h2 class="display-5 fw-bold">$<?php echo number_format($saldo_sistema, 2); ?></h2> 
                </div>-->
            </div> 
             <div class="col-md-4">
                <div class="card bg-dark text-white text-center p-4">
                    <h6 class="fw-bold">Oficina - <?php echo $_SESSION["oficina"]; ?></h6>
                     <?php
                        // Obtenemos el ID del usuario de la sesi��n
                        $id_usuario_sesion = $_SESSION["user_id"]; 
                        
                        // Query para sumar recibidos y restar entregados
                        $sql_saldo = "SELECT SUM(importe_recibido - importe_entregado) as saldo_total 
                                      FROM movimientos 
                                      WHERE ID_USUARIO = ? AND ESTADO = 'A'";
                        
                        $stmt_saldo = $conn->prepare($sql_saldo);
                        $stmt_saldo->bind_param("i", $id_usuario_sesion);
                        $stmt_saldo->execute();
                        $resultado_saldo = $stmt_saldo->get_result();
                        $datos_saldo = $resultado_saldo->fetch_assoc();
                        
                        // Guardamos el valor en una variable
                        $total_caja_usuario = $datos_saldo['saldo_total'] ?? 0;
                        
                        $stmt_saldo->close();
                        ?>
                    <h2 class="display-5 fw-bold">$ <?php echo number_format($total_caja_usuario, 2); ?></h2>
                </div>
            </div>

            <div class="col-md-4">
               <!--   <div class="card <?php echo $faltante < 0 ? 'bg-danger' : 'bg-primary'; ?> text-white text-center p-4">
                  <h6 class="fw-bold">DIFERENCIA (FALTANTE/SOBRANTE)</h6>
                    <h2 class="display-5 fw-bold">$<?php echo number_format($faltante, 2); ?></h2> 
                </div> -->
            </div>
        </div>
        
       <?php } ?>
        
        
  
    
        <div class="text-center mt-5">
           
            <!-- <a href="arqueo.php" class="btn btn-outline-dark btn-lg fw-bold">REALIZAR NUEVO ARQUEO DE CAJA</a> -->
            <a class="dropdown-item" href="#">Usuario: <?php echo $_SESSION["user_name"]; ?></a>
            <a class="dropdown-item" href="#">id: <?php echo $_SESSION["user_id"]; ?></a>
            <a class="dropdown-item" href="#">ROL-id: <?php echo $_SESSION["user_rol"]; ?></a>
            <a class="dropdown-item" href="#">OFICINA : <?php echo $_SESSION["oficina"]; ?></a>
            <a class="dropdown-item" href="#">OFICINA ID: <?php echo $_SESSION["oficina_ID"]; ?></a>
            <a class="dropdown-item" href="#">ROL : <?php echo $_SESSION["oficina_ROL"]; ?></a>
            <?php if ($_SESSION["user_rol"] == 3): ?>
            <a class="dropdown-item" href="dashboard.php"> DASHBOARD</a>
            <?php endif; ?>
        </div>
    </div>
    
    
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
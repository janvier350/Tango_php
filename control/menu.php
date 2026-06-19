<?php
require_once '../config.php';
require_once '../auth.php';
verificar_auth();

// 1. Saldo Te贸rico (Sistema)
$res_sis = $conn->query("SELECT SUM(CASE WHEN tipo='Ingreso' THEN monto ELSE -monto END) as saldo FROM movimientos");
$saldo_sistema = $res_sis->fetch_assoc()['saldo'] ?? 0;

// 2. 脷ltimo Efectivo Real (Arqueo)
$res_arq = $conn->query("SELECT * FROM arqueos ORDER BY fecha DESC LIMIT 1");
$ultimo_arqueo = $res_arq->fetch_assoc();
$efectivo_real = $ultimo_arqueo['total_efectivo_real'] ?? 0;
$faltante = $efectivo_real - $saldo_sistema;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>TANGO | Panel de Control</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../estilos.css">
</head>
<body>
    
  <?php
// Asegúrate de que la sesión esté iniciada antes de usar $_SESSION
// session_start(); // (si no está ya en tu código)

if (isset($_SESSION["user_rol"])) {
    if ($_SESSION["user_rol"] == 4) {
        include '../navbar_control.php';
    } elseif ($_SESSION["user_rol"] == 2) {
        include '../navbar.php';
    } else {
        // Opcional: un navbar por defecto para otros roles (1, 3, etc.)
        include '../navbar.php';
    }
} else {
    // Si no hay rol definido, puedes redirigir o mostrar un navbar genérico
    include 'navbar.php';
}
?>
    
    <div class="container">
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card bg-success text-white text-center p-4">
                    
                     <a class="dropdown-item" href="control_movimientos_401.php">
                         <h6 class="fw-bold">401</h6>
                         ROL: <?php echo $_SESSION["oficina"]; ?>
                         <h2 class="display-5 fw-bold">$<?php echo number_format($efectivo_real, 2); ?>
                         </a>
                    
                    </h2>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card bg-dark text-white text-center p-4">
                    
                    <a class="dropdown-item" href="control_movimientos_403.php">
                        <h6 class="fw-bold">403</h6>
                        ROL: <?php echo $_SESSION["oficina"]; ?>
                        <h2 class="display-5 fw-bold">$<?php echo number_format($saldo_sistema, 2); ?></h2>
                        </a>
                    
                </div>
            </div>

            <div class="col-md-4">
                <div class="card <?php echo $faltante < 0 ? 'bg-danger' : 'bg-primary'; ?> text-white text-center p-4">
                    <h6 class="fw-bold">DASHBOARD</h6>
                    <h2 class="display-5 fw-bold">$<?php echo number_format($faltante, 2); ?></h2>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-5">
            <!--<a href="arqueo.php" class="btn btn-outline-dark btn-lg fw-bold">REALIZAR NUEVO ARQUEO DE CAJA</a> -->
            <a class="dropdown-item" href="#">Usuario: <?php echo $_SESSION["user_name"]; ?></a>
            <!--<a class="dropdown-item" href="#">id: <?php echo $_SESSION["user_id"]; ?></a> -->
            <!-- <a class="dropdown-item" href="#">ROL-id: <?php echo $_SESSION["user_rol"]; ?></a> -->
            <a class="dropdown-item" href="#">ROL: <?php echo $_SESSION["oficina"]; ?></a>
            <a class="dropdown-item" href="#">OFICINA: <?php echo $_SESSION["oficina_ROL"]; ?></a>
        </div>
    </div>
</body>
</html>
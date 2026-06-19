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
        // ACCESO ADMINISTRACION
        if ($_SESSION["user_rol"] == 4) { ?>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card bg-success text-white text-center p-4">
                    <h6 class="fw-bold"> Oficina - 401 </h6>
                    <?php
                        // Obtenemos el ID del usuario de la sesi��n
                        $id_usuario_sesion = 3;
                        
                        
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
                        $total_caja_usuario_3 = $datos_saldo['saldo_total'] ?? 0;
                        
                        $stmt_saldo->close();
                        ?>
                    <h2 class="display-5 fw-bold">$<?php echo number_format($total_caja_usuario_3, 2);  ?></h2>
                    
                </div>
            </div>

            <div class="col-md-4">
                <div class="card bg-success text-white text-center p-4">
                    <h6 class="fw-bold">Oficina - 403 </h6>
                     <?php
                        // Obtenemos el ID del usuario de la sesi��n
                        $id_usuario_sesion = 4; 
                        
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
                        $total_caja_usuario_4 = $datos_saldo['saldo_total'] ?? 0;
                        
                        $stmt_saldo->close();
                        ?>
                    <h2 class="display-5 fw-bold">$<?php echo number_format($total_caja_usuario_4, 2); ?></h2>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card <?php echo $faltante < 0 ? 'bg-danger' : 'bg-primary'; ?> text-white text-center p-4">
                    <h6 class="fw-bold">Caja General</h6>
                    <?php
                        // Obtenemos el ID del usuario de la sesi��n
                        $id_usuario_sesion = 2; 
                        
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
                        $total_caja_usuario_2 = $datos_saldo['saldo_total'] ?? 0;
                        
                        $stmt_saldo->close();
                        ?>
                    <h2 class="display-5 fw-bold">$<?php echo number_format($total_caja_usuario_2, 2); ?></h2>
                </div>
            </div>
        </div>
        <br>

        <div class="row g-4">
            
            <div class="col-md-4">
                <!--<div class="card bg-dark text-white text-center p-4">
                     <h6 class="fw-bold">SALDO CONTABLE (Sistema)</h6>
                    <h2 class="display-5 fw-bold"></h2> 
                </div>-->
            </div> 
             <div class="col-md-4">
                <div class="card bg-warning text-white text-center p-4">
                    <h6 class="fw-bold">Suma Oficinas</h6>
                    
                    <h2 class="display-5 fw-bold">$ <?php 
                    $total_caja_oficinas = $total_caja_usuario_2 + $total_caja_usuario_4 + $total_caja_usuario_3; 
                    echo number_format($total_caja_oficinas, 2); 
                    ?></h2>
                </div>
            </div>

            <div class="col-md-4">
               <!--   <div class="card <?php echo $faltante < 0 ? 'bg-danger' : 'bg-primary'; ?> text-white text-center p-4">
                  <h6 class="fw-bold">DIFERENCIA (FALTANTE/SOBRANTE)</h6>
                    <h2 class="display-5 fw-bold">$</h2> 
                </div> -->
            </div>
        </div>
        <?php
    } else { ?>
    
    <?php
        // ACCESO CEO
        if ($_SESSION["user_rol"] == 3) { ?>
        <div class="row g-4">
            
            <div class="col-md-4">
                <div class="card bg-success text-white text-center p-4">
                     <a class="dropdown-item" href="validar_movimientos_401.php">  
                    <h6 class="fw-bold"> Oficina - 401 </h6>
                    <?php
                        // Obtenemos el ID del usuario de la sesi��n
                        $id_usuario_sesion = 3;
                        
                        
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
                        $total_caja_usuario_3 = $datos_saldo['saldo_total'] ?? 0;
                        
                        $stmt_saldo->close();
                        ?>
                    <h2 class="display-5 fw-bold">$<?php echo number_format($total_caja_usuario_3, 2);  ?></h2>
                    </a>
                </div>
            </div>
        
            <div class="col-md-4">
                <div class="card bg-success text-white text-center p-4">
                    <a class="dropdown-item" href="validar_movimientos_403.php">  
                    <h6 class="fw-bold">Oficina - 403 </h6>
                     <?php
                        // Obtenemos el ID del usuario de la sesi��n
                        $id_usuario_sesion = 4; 
                        
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
                        $total_caja_usuario_4 = $datos_saldo['saldo_total'] ?? 0;
                        
                        $stmt_saldo->close();
                        ?>
                    <h2 class="display-5 fw-bold">$<?php echo number_format($total_caja_usuario_4, 2); ?></h2>
                    </a>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card <?php echo $faltante < 0 ? 'bg-danger' : 'bg-primary'; ?> text-white text-center p-4">
                   <a class="dropdown-item" href="validar_movimientos_general.php">  
                    <h6 class="fw-bold">Caja General</h6>
                    <?php
                        // Obtenemos el ID del usuario de la sesi��n
                        $id_usuario_sesion = 2; 
                        
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
                        $total_caja_usuario_2 = $datos_saldo['saldo_total'] ?? 0;
                        
                        $stmt_saldo->close();
                        ?>
                    <h2 class="display-5 fw-bold">$<?php echo number_format($total_caja_usuario_2, 2); ?></h2>
                       </a>
                </div>
            </div>
        </div>
        <br>

        <div class="row g-4">
            
            <div class="col-md-4">
                <!--<div class="card bg-dark text-white text-center p-4">
                     <h6 class="fw-bold">SALDO CONTABLE (Sistema)</h6>
                    <h2 class="display-5 fw-bold"></h2> 
                </div>-->
            </div> 
             <div class="col-md-4">
                <div class="card bg-warning text-white text-center p-4">
                    <h6 class="fw-bold">Suma Oficinas</h6>
                    
                    <h2 class="display-5 fw-bold">$ <?php 
                    $total_caja_oficinas = $total_caja_usuario_2 + $total_caja_usuario_4 + $total_caja_usuario_3; 
                    echo number_format($total_caja_oficinas, 2); 
                    ?></h2>
                </div>
            </div>

            <div class="col-md-4">
               <!--   <div class="card <?php echo $faltante < 0 ? 'bg-danger' : 'bg-primary'; ?> text-white text-center p-4">
                  <h6 class="fw-bold">DIFERENCIA (FALTANTE/SOBRANTE)</h6>
                    <h2 class="display-5 fw-bold">$</h2> 
                </div> -->
            </div>
        </div>
        <?php
    } ?>
    
    
    
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
        
        
  
    <?php
    }
    ?>
    
        <div class="text-center mt-5">
           
            <!-- <a href="arqueo.php" class="btn btn-outline-dark btn-lg fw-bold">REALIZAR NUEVO ARQUEO DE CAJA</a> -->
            <a class="dropdown-item" href="#">Usuario: <?php echo $_SESSION["user_name"]; ?></a>
            <a class="dropdown-item" href="#">id: <?php echo $_SESSION["user_id"]; ?></a>
            <a class="dropdown-item" href="#">ROL-id: <?php echo $_SESSION["user_rol"]; ?></a>
            <a class="dropdown-item" href="#">OFICINA : <?php echo $_SESSION["oficina"]; ?></a>
            <a class="dropdown-item" href="#">OFICINA ID: <?php echo $_SESSION["oficina_ID"]; ?></a>
            <a class="dropdown-item" href="#">ROL : <?php echo $_SESSION["oficina_ROL"]; ?></a>
            <a class="dropdown-item" href="dashboard.php"> DASHBOARD</a>
        </div>
    </div>
    
    
</body>
</html>
<?php
// 1. CONFIGURACIÓN DE ZONA HORARIA (ECUADOR)
date_default_timezone_set('America/Guayaquil');
require_once '../config.php';
require_once '../auth.php';
verificar_auth();

// Asegurar que la conexión use UTF-8 para evitar caracteres extraños
$conn->set_charset("utf8");

 header('Content-Type: text/html; charset=utf-8');
$conn->set_charset("utf8");
 
 
// 1. Lógica de Inserción
if (isset($_POST['reg_mov'])) {
    // Calculamos el periodo automáticamente
    $periodo = date("Y-n", strtotime($_POST['f']));
    
    // Separamos el monto en Recibido o Entregado según la selección
    $imp_recibido = ($_POST['tipo_flujo'] == 'Ingreso') ? $_POST['m'] : 0;
    $imp_entregado = ($_POST['tipo_flujo'] == 'Egreso') ? $_POST['m'] : 0;
    
    // --- NUEVOS DATOS ---
    // Obtenemos el ID del usuario de la sesión (usando la variable que definiste)
    $id_usuario_sesion = $_SESSION["user_id"]; 
    $id_rol = $_SESSION["user_rol"];
    
    $id_proyecto = !empty($_POST['id_proyecto']) ? $_POST['id_proyecto'] : NULL;
    
    // Obtenemos la fecha y hora actual para el registro
    $fecha_registro_actual = date("Y-m-d H:i:s");

    // Preparamos la consulta (Asegúrate de que los nombres de columnas coincidan con tu SQL)
    
    $stmt = $conn->prepare("INSERT INTO movimientos (fecha, empresa, concepto, intermediario, importe_recibido, importe_entregado, vale_ref, doc_soporte, inf_fin, periodo, banco, cheque_num, ID_USUARIO, FECHA_REGISTRO_I_E,ID_PROYECTO,intermediario2) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    
    // s = string, d = double (decimal), i = integer
    // s = string, d = double (decimal)
   $stmt->bind_param("ssssddssssssisis", 
        $_POST['f'], 
        $_POST['emp'], 
        $_POST['c'], 
        $_POST['inter'], 
        $imp_recibido, 
        $imp_entregado, 
        $_POST['v_ref'], 
        $_POST['doc'], 
        $_POST['id_reposicion'], 
        $periodo, 
        $_POST['ban'], 
        $_POST['chq'],
        $id_usuario_sesion,    // ID del usuario que inicia sesión
        $fecha_registro_actual, // Fecha y hora del sistema
        $id_proyecto,
         $_POST['intermediario']
    );
    
    
    if ($stmt->execute()) {
        $stmt->close();
        // REDIRECCIÓN CRÍTICA: Debe coincidir exactamente con el nombre de tu archivo
        header("Location: ../movimientos.php");
        exit();
    } else {
        echo "Error al insertar: " . $stmt->error;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>TANGO | Sistema de Caja</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../estilos.css">
    
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    $(document).ready(function() {
        $('select[name="id_proyecto"]').select2({
            placeholder: "Buscar proyecto...",
            allowClear: true,
            width: '100%',
            theme: "classic" // Si usas un tema compatible
        });
    });
</script>

<script>
    $(document).ready(function() {
        $('select[name="inter"]').select2({
            placeholder: "Buscar beneficiario...",
            allowClear: true,
            width: '100%',
            theme: "classic" // Si usas un tema compatible
        });
    });
</script>
<script>
    $(document).ready(function() {
        $('select[name="intermediario"]').select2({
            placeholder: "Buscar intermediario...",
            allowClear: true,
            width: '100%',
            theme: "classic" // Si usas un tema compatible
        });
    });
</script>

<script>
$(document).ready(function() {
    $('.select2-buscable').select2({
        placeholder: "Seleccione una opcion",
        allowClear: true,
        width: '100%', // Para que ocupe todo el ancho de la columna de Bootstrap
        theme: "classic" // O puedes omitirlo para el estilo estándar
    });
});
</script>
</head>
<body class="bg-light">
    <?php include '../navbar_control.php'; ?>
    <div class="container-fluid px-4 mt-3">
        
        <div class="card mb-12 border-0 shadow-sm">
            <div class="card-body">
                <form method="POST" action="movimientos.php" class="row g-2">
                    <div class="col-md-2">
                        <label class="small fw-bold">Fecha</label>
                        <input type="date" name="f" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="small fw-bold  text-primary">Beneficiario</label>
                        <select name="inter" class="form-select form-select-sm buscable" required>
                            <option value="">Buscar beneficiario...</option>
                            <?php 
                                $res_ben = $conn->query("SELECT RAZON_SOCIAL FROM BENEFICIARIO ORDER BY RAZON_SOCIAL ASC");
                                while($ben = $res_ben->fetch_assoc()) {
                                    echo "<option value='".htmlspecialchars($ben['RAZON_SOCIAL'], ENT_QUOTES, 'UTF-8')."'>".htmlspecialchars($ben['RAZON_SOCIAL'], ENT_QUOTES, 'UTF-8')."</option>";
                                } 
                            ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="small fw-bold  text-primary">Intermediario</label>
                        <select name="intermediario" class="form-select form-select-sm buscable" required>
                            <option value="">Buscar intermediario...</option>
                            <?php 
                                $res_ben = $conn->query("SELECT RAZON_SOCIAL FROM BENEFICIARIO ORDER BY RAZON_SOCIAL ASC");
                                while($ben = $res_ben->fetch_assoc()) {
                                    echo "<option value='".htmlspecialchars($ben['RAZON_SOCIAL'], ENT_QUOTES, 'UTF-8')."'>".htmlspecialchars($ben['RAZON_SOCIAL'], ENT_QUOTES, 'UTF-8')."</option>";
                                } 
                            ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="small fw-bold text-primary">Proyecto</label>
                        <select name="id_proyecto" class="form-select form-select-sm buscable">
                            <option value="">Buscar proyecto...</option>
                            <?php 
                                $res = $conn->query("SELECT ID_PROYECTO, PROYECTO FROM PROYECTOS ORDER BY PROYECTO ASC"); 
                                while($p = $res->fetch_assoc()) {
                                    echo "<option value='{$p['ID_PROYECTO']}'>".htmlspecialchars($p['PROYECTO'])."</option>";
                                }
                            ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="small fw-bold">Empresa</label>
                        <select name="emp" class="form-select form-select-sm">
                            <?php $res = $conn->query("SELECT nombre FROM cat_empresas"); while($e = $res->fetch_assoc()) echo "<option>{$e['nombre']}</option>"; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="small fw-bold">Descripcion</label>
                        <input type="text" name="c" class="form-control form-control-sm" placeholder="Detalle" required>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="small fw-bold">Tipo</label>
                        <select name="tipo_flujo" class="form-select form-select-sm">
                            <option value="Egreso">Entrega (-)</option>
                            <option value="Ingreso">Recibe (+)</option>
                        </select>
                    </div>

                    <div class="col-md-1">
                        <label class="small fw-bold">Monto</label>
                        <input type="number" step="0.01" name="m" class="form-control form-control-sm" required>
                    </div>
                    
                  <!--  <div class="col-md-1">
                        <label class="small fw-bold">Vale/Ref</label>
                        <input type="text" name="v_ref" class="form-control form-control-sm">
                    </div>
                -->
                    <div class="col-md-3">
                        <label class="small fw-bold">Doc. Soporte</label>
                        <input type="text" name="doc" class="form-control form-control-sm" placeholder =" # factura ...">
                    </div>

                  
                    <div class="col-md-6">
    <label class="small fw-bold text-primary">INF. FINANCIERA </label>
    <select name="id_reposicion" id="id_reposicion" class="form-select form-select-sm select2-buscable" required>
        <option value="">Escribe para buscar...</option>
        <?php 
            $sql = "SELECT a.nombre AS categoria, b.REPOSICION AS detalle, b.ID_REPOSICION 
                    FROM cat_reposiciones a 
                    INNER JOIN CAT_REPOSICION b ON a.id = b.ID_CAT_REPOCICIONES 
                    ORDER BY a.nombre ASC, b.REPOSICION ASC";
            
            $res_rep = $conn->query($sql);
            
            while($row = $res_rep->fetch_assoc()) {
                $id = $row['ID_REPOSICION'];
                $etiqueta = $row['categoria'] . " - " . $row['detalle'];
                echo "<option value='".htmlspecialchars($id)."'>".htmlspecialchars($etiqueta)."</option>";
            } 
        ?>
    </select>
</div>
                    

                    <div class="col-md-2">
                        <label class="small fw-bold">Banco</label>
                        <select name="ban" class="form-select form-select-sm">
                            <option value="EFECTIVO">Efectivo</option>
                            <?php $res = $conn->query("SELECT nombre FROM cat_bancos"); while($ba = $res->fetch_assoc()) echo "<option>{$ba['nombre']}</option>"; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="small fw-bold">CHEQUE #</label>
                        <input type="text" name="chq" class="form-control form-control-sm" value="N/A">
                    </div>
                    
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" name="reg_mov" class="btn btn-dark btn-sm w-100 fw-bold">OK</button>
                    </div>
                </form>
            </div>
        </div>
<h1>401</h1>
        <div class="table-responsive bg-white shadow-sm p-3">
            <table class="table table-sm table-bordered table-hover" style="font-size: 0.75rem;"> <a class="dropdown-item" href="#">Usuario: <?php echo $_SESSION["user_name"]; ?> - <?php echo $_SESSION["user_rol"]; ?></a>
                <thead class="table-secondary text-center">
                    <tr>
                        <th>SECUENCIA</th>
                        <th>FECHA</th>
                         <th>PROYECTO</th>
                        <th>BENEFICIARIO</th>
                        <th>INTERMEDIARIO</th>
                        <th>DESCRIPCION</th>
                        <th>RECIBIDO (+)</th>
                        <th>ENTREGADO (-)</th>
                        <th class="table-primary">SALDO</th>
                        <th>VALE</th>
                        <th>EMPRESA</th>
                        <th>DOC. SOPORTE</th>
                        <th>INF.FIN</th>
                        <th>BANCO</th>
                        <th>CHEQUE #</th>
                        <th> # USUARIO</th>
                        <th>FECHA REGISTRO</th>
                        <th>ACCIONES</th>
                        
                    </tr>
                </thead>
                <tbody>
    <?php 
    // Obtenemos el ID del usuario de la sesión actual
    $id_usuario_sesion = $_SESSION["user_id"]; 

    // Preparamos la consulta filtrando por ID_USUARIO
    $stmt_list = $conn->prepare("SELECT 
    m.*, 
    r.REPOSICION AS nombre_clasificacion,
    c.nombre AS nombre_categoria,
    p.PROYECTO AS nombre_proyecto
FROM movimientos m
LEFT JOIN CAT_REPOSICION r ON m.inf_fin = r.ID_REPOSICION
LEFT JOIN cat_reposiciones c ON r.ID_CAT_REPOCICIONES = c.id
LEFT JOIN PROYECTOS p ON m.ID_PROYECTO = p.ID_PROYECTO
WHERE m.ID_USUARIO = 3 
ORDER BY m.fecha ASC, m.id ASC");
    $stmt_list->bind_param("i", $id_usuario_sesion);
    $stmt_list->execute();
    $movs = $stmt_list->get_result();

    $saldo_acumulado = 0;
    while($m = $movs->fetch_assoc()): 
        $saldo_acumulado += ($m['importe_recibido'] - $m['importe_entregado']);
    ?>
    <tr>
        <td class="text-center"><?php echo $m['id']; ?></td>
        <td class="text-center"><?php echo date('d-m-Y', strtotime($m['fecha'])); ?></td>
        <td class="text-center"><?php echo htmlspecialchars($m['nombre_proyecto'] ?? 'N/A'); ?></td>
        <td><?php echo strtoupper($m['intermediario']); ?></td>
        <td><?php echo strtoupper($m['INTERMEDIARIO2']); ?></td>
        <td><?php echo htmlspecialchars($m['concepto']); ?></td>
        <td class="text-end text-success"><?php echo $m['importe_recibido'] > 0 ? '$'.number_format($m['importe_recibido'], 2) : '-'; ?></td>
        <td class="text-end text-danger"><?php echo $m['importe_entregado'] > 0 ? '$'.number_format($m['importe_entregado'], 2) : '-'; ?></td>
        
<?php 
    // Determinamos la clase de color según el valor del saldo
    $clase_saldo = 'text-dark'; // Color por defecto (negro)
    if ($saldo_acumulado > 0) {
        $clase_saldo = 'text-success'; // Verde para saldo a favor
    } elseif ($saldo_acumulado < 0) {
        $clase_saldo = 'text-danger'; // Rojo para deuda o saldo negativo
    }
?>
<td class="text-end fw-bold bg-light <?php echo $clase_saldo; ?>">
    $<?php echo number_format($saldo_acumulado, 2); ?>
</td>        <td class="text-center"><?php echo $m['vale_ref']; ?></td>
        <td class="text-center"><?php echo $m['empresa']; ?></td>
        <td class="text-center"><?php echo $m['doc_soporte']; ?></td>
        <td class="text-center"><?php echo $m['nombre_categoria']; ?> / <?php echo $m['nombre_clasificacion']; ?></td>
        <td class="text-center"><?php echo $m['banco']; ?></td>
        <td class="text-center"><?php echo $m['cheque_num']; ?></td>
        <td class="text-center"><?php echo $m['ID_USUARIO']; ?></td>
        <td class="text-center"><?php echo date('d/m H:i', strtotime($m['FECHA_REGISTRO_I_E'])); ?></td>
        
   <td class="text-center">
    <div class="btn-group" role="group">
        <a href="imprimir_vale.php?id=<?php echo $m['id']; ?>" target="_blank" class="btn btn-outline-primary btn-sm" title="Imprimir">
            PDF
        </a>
        
        <a href="editar_movimiento.php?id=<?php echo $m['id']; ?>" class="btn btn-outline-warning btn-sm" title="Editar">
            EDITAR
        </a>
        
        <a href="eliminar_movimiento.php?id=<?php echo $m['id']; ?>" 
           class="btn btn-outline-danger btn-sm" 
           onclick="return confirm('¿Estás seguro de eliminar este registro?');" title="Eliminar">
            BORRAR
        </a>
    </div>
</td>

    </tr>
    <?php 
    endwhile; 
    $stmt_list->close(); // Cerramos la sentencia
    ?>
</tbody>
            </table>
        </div>
    </div>
</body>
</html>
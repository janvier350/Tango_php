<?php
// 1. CONFIGURACI�0�7N DE ZONA HORARIA (ECUADOR)
date_default_timezone_set('America/Guayaquil');
require_once 'config.php';
require_once 'auth.php';
verificar_auth();

// Asegurar que la conexi��n use UTF-8 para evitar caracteres extra�0�9os
$conn->set_charset("utf8");

 header('Content-Type: text/html; charset=utf-8');
$conn->set_charset("utf8");
 
 
// 1. L��gica de Inserci��n
if (isset($_POST['reg_mov'])) {
    // Calculamos el periodo autom��ticamente
    $periodo = date("Y-n", strtotime($_POST['f']));
    
    // Separamos el monto en Recibido o Entregado seg��n la selecci��n
    $imp_recibido = ($_POST['tipo_flujo'] == 'Ingreso') ? $_POST['m'] : 0;
    $imp_entregado = ($_POST['tipo_flujo'] == 'Egreso') ? $_POST['m'] : 0;
    
    // --- NUEVOS DATOS ---
    // Obtenemos el ID del usuario de la sesi��n (usando la variable que definiste)
    $id_usuario_sesion = $_SESSION["user_id"]; 
    $id_rol = $_SESSION["user_rol"];
    $id_oficina = $_SESSION["oficina_ID"];
    $estado = "A";
    $id_proyecto = !empty($_POST['id_proyecto']) ? $_POST['id_proyecto'] : NULL;
    
    // Obtenemos la fecha y hora actual para el registro
    $fecha_registro_actual = date("Y-m-d H:i:s");

    // Preparamos la consulta (Aseg��rate de que los nombres de columnas coincidan con tu SQL)
    
    $stmt = $conn->prepare("INSERT INTO movimientos (fecha, empresa, concepto, intermediario, importe_recibido, importe_entregado, vale_ref, doc_soporte, inf_fin, periodo, banco, cheque_num, ID_USUARIO, FECHA_REGISTRO_I_E,ID_PROYECTO,intermediario2, ID_OFICINA, ESTADO) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    
    // s = string, d = double (decimal), i = integer
    // s = string, d = double (decimal)
   $stmt->bind_param("ssssddssssssisisis", 
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
        $id_usuario_sesion,    // ID del usuario que inicia sesi��n
        $fecha_registro_actual, // Fecha y hora del sistema
        $id_proyecto,
         $_POST['intermediario'],
          $id_oficina, 
           $estado 
    );
    
    
    if ($stmt->execute()) {
        $stmt->close();
        // REDIRECCI�0�7N CR�0�1TICA: Debe coincidir exactamente con el nombre de tu archivo
        header("Location: movimientos.php");
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
    <link rel="stylesheet" href="estilos.css">
    
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
        theme: "classic" // O puedes omitirlo para el estilo est��ndar
    });
});
</script>
</head>
<body class="bg-light">
    <?php  
    if ($id_rol == 4){
        include 'navbar_control.php';
    }else {
    include 'navbar.php';
    }
    
    ?>
    
    
    <div class="container-fluid px-4 mt-3">
        
        <div class="card mb-12 border-0 shadow-sm">
            <div class="card-body d-flex justify-content-between align-items-start">
              <div>
                <!-- cabecera formulario -->
                 <h1> caja 403 </h1>
              </div>
              <?php if ($_SESSION["user_rol"] == 3 || $_SESSION["user_rol"] == 4): ?>
              <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevaInfFin">
                  <i class="bi bi-plus-lg me-1"></i>Nueva Inf. Financiera
              </button>
              <?php endif; ?>
            </div>
        </div>
        <br>
<div class="table-responsive bg-white shadow-sm p-3">
<a class="dropdown-item" href="#">Usuario: <?php echo $_SESSION["user_name"]; ?> - <?php echo $_SESSION["user_rol"]; ?></a>
            <a class="dropdown-item" href="#">OFICINA: <?php echo $_SESSION["oficina"]; ?></a>
            <a class="dropdown-item" href="#">ROL: <?php echo $_SESSION["oficina_ROL"]; ?></a>
            </div>
            
            <br>
        <div class="table-responsive bg-white shadow-sm p-3">
            
            <table class="table table-sm table-bordered table-hover" style="font-size: 0.75rem;"> 
            
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
                        <th> VALE</th>
                        <th> EMPRESA</th>
                        <th> DOC. SOPORTE</th>
                        <th> INF.FIN</th>
                        <th> BANCO</th>
                        <th> CHEQUE</th>
                        <th> USUARIO</th>
                        <th> REVISADO</th>
                        <th> FECHA REGISTRO</th>
                        <th>APROBAR</th>
                        
                        
                    </tr>
                </thead>
                <tbody>
    <?php 
    $id_usuario_sesion = $_SESSION["user_id"]; 
    $saldo_acumulado = 0;
    $id_oficina = 2;

    // Actualizamos la consulta para traer el campo ESTADO
    $stmt_list = $conn->prepare("SELECT m.*, r.REPOSICION AS nombre_clasificacion, c.nombre AS nombre_categoria, p.PROYECTO AS nombre_proyecto, u.usuario AS nombre_revisor, 
            u_registra.usuario AS nombre_quien_registra

        FROM movimientos m 
        LEFT JOIN CAT_REPOSICION r ON m.inf_fin = r.ID_REPOSICION 
        LEFT JOIN cat_reposiciones c ON r.ID_CAT_REPOCICIONES = c.id 
        LEFT JOIN PROYECTOS p ON m.ID_PROYECTO = p.ID_PROYECTO 
        LEFT JOIN usuarios u ON m.ID_USUARIO_REVISA = u.id 
        -- Join para quien Registra (columna 24)
        LEFT JOIN usuarios u_registra ON m.ID_USUARIO = u_registra.id
        WHERE m.id_oficina = ?
        ORDER BY m.fecha ASC, m.id ASC");
    $stmt_list->bind_param("i", $id_oficina);
    $stmt_list->execute();
    $movs = $stmt_list->get_result();

    while($m = $movs->fetch_assoc()): 
        $es_anulado = ($m['ESTADO'] == 'I');
        
        // CR�0�1TICO: Solo sumar al saldo si NO est�� anulado
        if (!$es_anulado) {
            $saldo_acumulado += ($m['importe_recibido'] - $m['importe_entregado']);
        }
    ?>
    <tr style="<?php echo $es_anulado ? 'background-color: #f8d7da; opacity: 0.6; text-decoration: line-through;' : ''; ?>">
        <td class="text-center"><?php echo $m['id']; ?></td>
        <td class="text-center"><?php echo date('d-m-Y', strtotime($m['fecha'])); ?></td>
        <td class="text-center"><?php echo htmlspecialchars($m['nombre_proyecto'] ?? 'N/A'); ?></td>
        <td><?php echo strtoupper($m['intermediario']); ?></td>
        <td><?php echo strtoupper($m['INTERMEDIARIO2']); ?></td>
        <td><?php echo htmlspecialchars($m['concepto']); ?></td>
        <td class="text-end text-success"><?php echo $m['importe_recibido'] > 0 ? '$'.number_format($m['importe_recibido'], 2) : '-'; ?></td>
        <td class="text-end text-danger"><?php echo $m['importe_entregado'] > 0 ? '$'.number_format($m['importe_entregado'], 2) : '-'; ?></td>
        
        <td class="text-end fw-bold bg-light <?php echo ($saldo_acumulado >= 0) ? 'text-success' : 'text-danger'; ?>">
            $<?php echo number_format($saldo_acumulado, 2); ?>
        </td>
        
        <td class="text-center"><?php echo $m['vale_ref']; ?></td>
        <td class="text-center"><?php echo $m['empresa']; ?></td>
        <td class="text-center"><?php echo $m['doc_soporte']; ?></td>
        <?php $puede_editar_if = ($_SESSION["user_rol"] == 3 || $_SESSION["user_rol"] == 4) && !$es_anulado && !($m['ID_USUARIO_REVISA'] > 0); ?>
        <td class="text-center">
            <?php if ($puede_editar_if): ?>
                <a href="#" class="link-primary text-decoration-underline" title="Click para modificar"
                   onclick="abrirModalEditarInfFin(<?php echo $m['id']; ?>, <?php echo intval($m['inf_fin']); ?>); return false;">
                    <?php echo htmlspecialchars($m['nombre_categoria'] . ' / ' . $m['nombre_clasificacion']); ?> <i class="bi bi-pencil-square small"></i>
                </a>
            <?php else: ?>
                <?php echo htmlspecialchars($m['nombre_categoria'] . ' / ' . $m['nombre_clasificacion']); ?>
            <?php endif; ?>
        </td>
        <td class="text-center"><?php echo $m['banco']; ?></td>
        <td class="text-center"><?php echo $m['cheque_num']; ?></td>
        <td class="text-center"><?php echo $m['nombre_quien_registra']; ?></td>
       <td class="text-center fw-bold text-primary">
            <?php echo htmlspecialchars($m['nombre_revisor'] ?? '---'); ?>
        </td>
        <td class="text-center"><?php echo date('d/m H:i', strtotime($m['FECHA_REGISTRO_I_E'])); ?></td>
        
        <td class="text-center">
            <?php if ($m['ID_USUARIO_REVISA'] > 0): ?>
                <span class="badge bg-success" title="Revisado el: <?php echo $m['FECHA_REVISION']; ?>">
                    <i class="bi bi-check-all"></i> Aprobado
                </span>
            <?php elseif ($_SESSION["user_rol"] == 3 || $_SESSION["user_rol"] == 4): ?> 
                <a href="aprobar_movimiento.php?id=<?php echo $m['id']; ?>&return=validar_movimientos_403.php"
                   class="btn btn-sm btn-outline-success" 
                   onclick="return confirm('�0�7Confirmar revisi��n de este movimiento?');">
                    <i class="bi bi-check-circle"></i> Aprobar
                </a>
            <?php else: ?>
                <span class="text-muted small"><i>Pendiente</i></span>
            <?php endif; ?>
        </td>

        
    </tr>
    <?php endwhile; ?>

</tbody>
            </table>
        </div>
    </div>
    
    <div class="modal fade" id="modalAnular" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">Anular Movimiento</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="anular_movimiento.php" method="POST">
        <div class="modal-body">
          <input type="hidden" name="id_anular" id="id_anular">
          <p>�0�7Seguro de que deseas anular este registro? Esta acci��n no se puede deshacer.</p>
          <div class="form-group">
            <label class="fw-bold">Motivo de Anulaci��n (Obligatorio):</label>
            <textarea name="motivo" class="form-control" rows="3" required placeholder="Ej: Error en el monto, factura anulada por el proveedor..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-danger">Confirmar Anulaci��n</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function abrirModalAnular(id) {
    document.getElementById('id_anular').value = id;
    var myModal = new bootstrap.Modal(document.getElementById('modalAnular'));
    myModal.show();
}
</script>

<?php include 'inf_fin_modales.php'; ?>
</body>
</html>
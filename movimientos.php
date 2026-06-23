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
 
 
// 1. Lógica de Inserción
$error_doc = '';
if (isset($_POST['reg_mov'])) {
    // Validación server-side: documento duplicado (global, incluye anulados)
    $doc_post = trim($_POST['doc'] ?? '');
    if ($doc_post !== '') {
        $chk = $conn->prepare("SELECT id FROM movimientos WHERE doc_soporte = ?");
        $chk->bind_param("s", $doc_post);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $error_doc = "El documento \"" . htmlspecialchars($doc_post) . "\" ya está registrado en el sistema.";
        }
    }

if ($error_doc === '') {
    // Calculamos el periodo automáticamente
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
        header("Location: movimientos.php");
        exit();
    } else {
        echo "Error al insertar: " . $stmt->error;
    }
} // fin if $error_doc === ''
}

// Variables de sesión disponibles globalmente
$id_rol            = $_SESSION["user_rol"];
$id_usuario_sesion = $_SESSION["user_id"];

// Filtros GET
$f_desde  = $_GET['f_desde']  ?? '';
$f_hasta  = $_GET['f_hasta']  ?? '';
$f_estado = $_GET['f_estado'] ?? 'A';
$f_tipo   = $_GET['f_tipo']   ?? '';
$f_texto  = $_GET['f_texto']  ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <title>TANGO | Sistema de Caja</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="estilos.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        .filtros-card { background: #f8f9fa; border-left: 4px solid #0d6efd; }
        tfoot input { width: 100%; font-size: 0.7rem; padding: 2px 4px; border: 1px solid #ced4da; border-radius: 3px; }
        .dataTables_wrapper .dataTables_filter { display: none; }

        /* Tabla responsive: scroll horizontal en pantallas chicas, columnas angostas */
        .table-responsive { overflow-x: auto; }
        #tablaMovimientos { width: 100% !important; }
        #tablaMovimientos th.col-secuencia, #tablaMovimientos td:nth-child(1) { width: 60px; white-space: nowrap; }
        #tablaMovimientos th.col-fecha, #tablaMovimientos td:nth-child(2) { width: 85px; white-space: nowrap; }

        /* Dictado por voz */
        .btn-mic { transition: all .2s; }
        .btn-mic.escuchando {
            background-color: #dc3545;
            border-color: #dc3545;
            color: #fff;
            animation: mic-pulse 1.2s infinite;
        }
        @keyframes mic-pulse {
            0%,100% { box-shadow: 0 0 0 0 rgba(220,53,69,.5); }
            50%      { box-shadow: 0 0 0 5px rgba(220,53,69,0); }
        }
        .mic-interim { min-height: 1rem; }
    </style>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        $('select[name="id_proyecto"]').select2({ placeholder: "Buscar proyecto...", allowClear: true, width: '100%', theme: "classic" });
        $('select[name="inter"]').select2({ placeholder: "Buscar beneficiario...", allowClear: true, width: '100%', theme: "classic" });
        $('select[name="intermediario"]').select2({ placeholder: "Buscar intermediario...", allowClear: true, width: '100%', theme: "classic" });
        $('.select2-buscable').select2({ placeholder: "Seleccione una opcion", allowClear: true, width: '100%', theme: "classic" });
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
            <div class="card-body">
                <?php if ($error_doc): ?>
                <div class="alert alert-danger alert-dismissible fade show py-2 mb-2" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error_doc; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                <form method="POST" action="movimientos.php" class="row g-2">
                    <div class="col-md-2">
                        <label class="small fw-bold">Fecha</label>
                        <input type="date" name="f" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="small fw-bold text-primary d-flex align-items-center gap-1">
                            Beneficiario
                            <button type="button" class="btn btn-success btn-sm py-0 px-1 lh-1" style="font-size:0.75rem;"
                                data-bs-toggle="modal" data-bs-target="#modalNuevoBeneficiario"
                                title="Agregar nuevo beneficiario">
                                <i class="bi bi-person-plus-fill"></i>
                            </button>
                        </label>
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
                                $res_ben = $conn->query("SELECT RAZON_SOCIAL FROM BENEFICIARIO WHERE TIPO = 'I' ORDER BY RAZON_SOCIAL ASC");
                                while($ben = $res_ben->fetch_assoc()) {
                                    echo "<option value='".htmlspecialchars($ben['RAZON_SOCIAL'], ENT_QUOTES, 'UTF-8')."'>".htmlspecialchars($ben['RAZON_SOCIAL'], ENT_QUOTES, 'UTF-8')."</option>";
                                } 
                            ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="small fw-bold text-primary d-flex align-items-center gap-1">
                            Proyecto
                            <button type="button" class="btn btn-success btn-sm py-0 px-1 lh-1" style="font-size:0.75rem;"
                                data-bs-toggle="modal" data-bs-target="#modalNuevoProyecto"
                                title="Agregar nuevo proyecto">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        </label>
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
                        <label class="small fw-bold d-flex align-items-center gap-1">Descripcion
                            <span id="mic_status_c" class="text-danger small d-none fw-normal">
                                <i class="bi bi-record-circle-fill"></i> Escuchando...
                            </span>
                        </label>
                        <div class="input-group input-group-sm">
                            <input type="text" name="c" id="campo_concepto" class="form-control form-control-sm" placeholder="Detalle" required>
                            <button type="button" class="btn btn-outline-secondary btn-mic" id="mic_btn_c"
                                    onclick="toggleDictado('campo_concepto','mic_btn_c','mic_status_c','interim_c')"
                                    title="Dictado por voz">
                                <i class="bi bi-mic"></i>
                            </button>
                        </div>
                        <div id="interim_c" class="mic-interim text-muted fst-italic small"></div>
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
                        <label class="small fw-bold d-flex align-items-center gap-1">Doc. Soporte
                            <span id="mic_status_doc" class="text-danger small d-none fw-normal">
                                <i class="bi bi-record-circle-fill"></i> Escuchando...
                            </span>
                        </label>
                        <div class="input-group input-group-sm">
                            <input type="text" name="doc" id="doc_soporte" class="form-control form-control-sm <?php echo $error_doc ? 'is-invalid' : ''; ?>"
                                   placeholder="# factura ..." autocomplete="off"
                                   value="<?php echo $error_doc ? htmlspecialchars($_POST['doc'] ?? '') : ''; ?>">
                            <button type="button" class="btn btn-outline-secondary btn-mic" id="mic_btn_doc"
                                    onclick="toggleDictado('doc_soporte','mic_btn_doc','mic_status_doc','interim_doc')"
                                    title="Dictado por voz">
                                <i class="bi bi-mic"></i>
                            </button>
                        </div>
                        <div id="interim_doc" class="mic-interim text-muted fst-italic small"></div>
                        <div id="doc_feedback" class="form-text" style="font-size:0.7rem;"></div>
                    </div>

                  
                    <div class="col-md-6">
    <label class="small fw-bold text-primary d-flex align-items-center gap-1">INF. FINANCIERA
        <button type="button" class="btn btn-success btn-sm py-0 px-1 lh-1" style="font-size:0.75rem;"
            data-bs-toggle="modal" data-bs-target="#modalNuevaInfFin"
            title="Agregar nueva inf. financiera">
            <i class="bi bi-plus-lg"></i>
        </button>
    </label>
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
                        <label class="small fw-bold d-flex align-items-center gap-1">Banco
                            <button type="button" class="btn btn-success btn-sm py-0 px-1 lh-1" style="font-size:0.75rem;"
                                data-bs-toggle="modal" data-bs-target="#modalNuevoBanco"
                                title="Agregar nuevo banco">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        </label>
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
        <div class="bg-white shadow-sm p-2 mb-2 d-flex gap-3 small text-muted">
            <span><i class="bi bi-person me-1"></i><?php echo $_SESSION["user_name"]; ?> - <?php echo $_SESSION["user_rol"]; ?></span>
            <span><i class="bi bi-building me-1"></i><?php echo $_SESSION["oficina"]; ?></span>
            <span><i class="bi bi-shield me-1"></i><?php echo $_SESSION["oficina_ROL"]; ?></span>
        </div>

        <!-- FILTROS -->
        <div class="card mb-3 filtros-card shadow-sm">
            <div class="card-body py-2">
                <form method="GET" action="movimientos.php" class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label small fw-bold mb-1">Desde</label>
                        <input type="date" name="f_desde" class="form-control form-control-sm" value="<?php echo htmlspecialchars($f_desde); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold mb-1">Hasta</label>
                        <input type="date" name="f_hasta" class="form-control form-control-sm" value="<?php echo htmlspecialchars($f_hasta); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold mb-1">Estado</label>
                        <select name="f_estado" class="form-select form-select-sm">
                            <option value=""  <?php echo $f_estado===''  ? 'selected':'' ?>>Todos</option>
                            <option value="A" <?php echo $f_estado==='A' ? 'selected':'' ?>>Activos</option>
                            <option value="I" <?php echo $f_estado==='I' ? 'selected':'' ?>>Anulados</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold mb-1">Tipo</label>
                        <select name="f_tipo" class="form-select form-select-sm">
                            <option value=""        <?php echo $f_tipo===''        ? 'selected':'' ?>>Todos</option>
                            <option value="Ingreso" <?php echo $f_tipo==='Ingreso' ? 'selected':'' ?>>Ingresos (+)</option>
                            <option value="Egreso"  <?php echo $f_tipo==='Egreso'  ? 'selected':'' ?>>Egresos (-)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold mb-1">Búsqueda</label>
                        <input type="text" name="f_texto" class="form-control form-control-sm" placeholder="Beneficiario, descripción, doc..." value="<?php echo htmlspecialchars($f_texto); ?>">
                    </div>
                    <div class="col-md-1 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm w-100" title="Filtrar"><i class="bi bi-search"></i></button>
                        <a href="movimientos.php" class="btn btn-outline-secondary btn-sm w-100" title="Limpiar"><i class="bi bi-x-lg"></i></a>
                    </div>
                </form>
            </div>
        </div>

        <div class="table-responsive bg-white shadow-sm p-3">

            <table id="tablaMovimientos" class="table table-sm table-bordered table-hover" style="font-size: 0.75rem;">

                <thead class="table-secondary text-center">
                    <tr>
                        <th class="col-secuencia">SECUENCIA</th>
                        <th class="col-fecha">FECHA</th>
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
                        <th># REVISADO</th>
                        <th>FECHA REGISTRO</th>
                        <th>APROBAR</th>
                        <th>ACCIONES</th>
                    </tr>
                    <tr class="table-light">
                        <th><input type="text" placeholder="Buscar..." class="col-search"></th>
                        <th><input type="text" placeholder="Buscar..." class="col-search"></th>
                        <th><input type="text" placeholder="Buscar..." class="col-search"></th>
                        <th><input type="text" placeholder="Buscar..." class="col-search"></th>
                        <th><input type="text" placeholder="Buscar..." class="col-search"></th>
                        <th><input type="text" placeholder="Buscar..." class="col-search"></th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th><input type="text" placeholder="Buscar..." class="col-search"></th>
                        <th><input type="text" placeholder="Buscar..." class="col-search"></th>
                        <th><input type="text" placeholder="Buscar..." class="col-search"></th>
                        <th><input type="text" placeholder="Buscar..." class="col-search"></th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
    <?php
    $id_usuario_sesion = $_SESSION["user_id"];
    $saldo_acumulado = 0;

    // Construir query con filtros
    $sql_base = "SELECT m.*, r.REPOSICION AS nombre_clasificacion, c.nombre AS nombre_categoria, p.PROYECTO AS nombre_proyecto, u.usuario AS nombre_revisor
        FROM movimientos m
        LEFT JOIN CAT_REPOSICION r ON m.inf_fin = r.ID_REPOSICION
        LEFT JOIN cat_reposiciones c ON r.ID_CAT_REPOCICIONES = c.id
        LEFT JOIN PROYECTOS p ON m.ID_PROYECTO = p.ID_PROYECTO
        LEFT JOIN usuarios u ON m.ID_USUARIO_REVISA = u.id
        WHERE m.ID_USUARIO = ?";
    $params = [$id_usuario_sesion];
    $types  = "i";

    if ($f_desde !== '') { $sql_base .= " AND m.fecha >= ?"; $params[] = $f_desde; $types .= "s"; }
    if ($f_hasta !== '') { $sql_base .= " AND m.fecha <= ?"; $params[] = $f_hasta; $types .= "s"; }
    if ($f_estado !== '') { $sql_base .= " AND m.ESTADO = ?"; $params[] = $f_estado; $types .= "s"; }
    if ($f_tipo === 'Ingreso') { $sql_base .= " AND m.importe_recibido > 0"; }
    elseif ($f_tipo === 'Egreso') { $sql_base .= " AND m.importe_entregado > 0"; }
    if ($f_texto !== '') {
        $like = "%$f_texto%";
        $sql_base .= " AND (m.concepto LIKE ? OR m.intermediario LIKE ? OR m.doc_soporte LIKE ? OR m.INTERMEDIARIO2 LIKE ?)";
        $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
        $types .= "ssss";
    }
    $sql_base .= " ORDER BY m.fecha ASC, m.id ASC";

    $stmt_list = $conn->prepare($sql_base);
    $stmt_list->bind_param($types, ...$params);
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
        <td class="text-center"><?php echo $m['nombre_categoria']; ?> / <?php echo $m['nombre_clasificacion']; ?></td>
        <td class="text-center"><?php echo $m['banco']; ?></td>
        <td class="text-center"><?php echo $m['cheque_num']; ?></td>
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
                <a href="aprobar_movimiento.php?id=<?php echo $m['id']; ?>&return=movimientos.php"
                   class="btn btn-sm btn-outline-success" 
                   onclick="return confirm('�0�7Confirmar revisi��n de este movimiento?');">
                    <i class="bi bi-check-circle"></i> Aprobar
                </a>
            <?php else: ?>
                <span class="text-muted small"><i>Pendiente</i></span>
            <?php endif; ?>
        </td>

        <td class="text-center">
            <div class="btn-group" role="group">
                <a href="imprimir_vale.php?id=<?php echo $m['id']; ?>" target="_blank" class="btn btn-outline-primary btn-sm <?php echo $es_anulado ? 'disabled' : ''; ?>">PDF</a>
                <a href="editar_movimiento.php?id=<?php echo $m['id']; ?>" class="btn btn-outline-warning btn-sm <?php echo $es_anulado ? 'disabled' : ''; ?>">EDITAR</a>
                
                <?php if (!$es_anulado): ?>
                <a href="anular_movimiento.php?id=<?php echo $m['id']; ?>" 
                   class="btn btn-outline-danger btn-sm" 
                   onclick="return confirm('�0�7Est��s seguro de ANULAR este registro? El valor ya no contar�� en el saldo.');">
                    ANULAR
                </a>
                <?php else: ?>
                <span class="badge bg-danger">ANULADO</span>
                <?php endif; ?>
            </div>
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

<!-- Modal: Nuevo Banco -->
<div class="modal fade" id="modalNuevoBanco" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header py-2 bg-dark text-white">
        <h6 class="modal-title mb-0"><i class="bi bi-bank me-2"></i>Nuevo Banco</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
          <label class="form-label small fw-bold">Nombre del Banco</label>
          <input type="text" id="nb2_nombre" class="form-control form-control-sm text-uppercase"
                 placeholder="Ej: BANCO PICHINCHA..." autocomplete="off">
          <div id="nb2_feedback" class="form-text mt-1"></div>
        </div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" id="nb2_guardar" class="btn btn-dark btn-sm">
          <i class="bi bi-check-lg me-1"></i>Guardar
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Nueva Inf. Financiera -->
<div class="modal fade" id="modalNuevaInfFin" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header py-2" style="background:#2c3e50; color:#fff;">
        <h6 class="modal-title mb-0"><i class="bi bi-journal-plus me-2"></i>Nueva Inf. Financiera</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">

        <!-- Categoría -->
        <div class="mb-3">
          <label class="form-label small fw-bold">Categoría</label>
          <div class="d-flex gap-2 align-items-center mb-2">
            <select id="if_cat_select" class="form-select form-select-sm">
              <option value="">Cargando categorías...</option>
            </select>
            <div class="form-check form-switch mb-0 ms-1 text-nowrap">
              <input class="form-check-input" type="checkbox" id="if_nueva_cat_toggle">
              <label class="form-check-label small" for="if_nueva_cat_toggle">Nueva</label>
            </div>
          </div>
          <div id="if_nueva_cat_wrap" class="d-none">
            <input type="text" id="if_nueva_cat" class="form-control form-control-sm text-uppercase"
                   placeholder="Nombre de la nueva categoría...">
            <div id="if_nueva_cat_feedback" class="form-text"></div>
          </div>
        </div>

        <!-- Reposición / Detalle -->
        <div class="mb-2">
          <label class="form-label small fw-bold">Detalle (Inf. Financiera)</label>
          <input type="text" id="if_reposicion" class="form-control form-control-sm text-uppercase"
                 placeholder="Nombre del concepto financiero...">
          <div id="if_reposicion_feedback" class="form-text"></div>
        </div>

        <div id="if_preview" class="alert alert-light py-1 px-2 small d-none">
          <i class="bi bi-tag me-1"></i><span id="if_preview_text"></span>
        </div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" id="if_guardar" class="btn btn-success btn-sm">
          <i class="bi bi-check-lg me-1"></i>Guardar
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Nuevo Proyecto -->
<div class="modal fade" id="modalNuevoProyecto" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white py-2">
        <h6 class="modal-title mb-0"><i class="bi bi-folder-plus me-2"></i>Nuevo Proyecto</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
          <label class="form-label small fw-bold">Nombre del Proyecto</label>
          <input type="text" id="np_nombre" class="form-control form-control-sm text-uppercase"
                 placeholder="Nombre del grupo empresarial..." autocomplete="off">
          <div id="np_feedback" class="form-text mt-1"></div>
        </div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" id="np_guardar" class="btn btn-primary btn-sm">
          <i class="bi bi-check-lg me-1"></i>Guardar
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Nuevo Beneficiario -->
<div class="modal fade" id="modalNuevoBeneficiario" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header bg-success text-white py-2">
        <h6 class="modal-title mb-0"><i class="bi bi-person-plus-fill me-2"></i>Nuevo Beneficiario</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small fw-bold">Razón Social</label>
          <input type="text" id="nb_razon" class="form-control form-control-sm text-uppercase"
                 placeholder="Nombre o razón social..." autocomplete="off">
          <div id="nb_feedback" class="form-text mt-1"></div>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-bold">Tipo</label>
          <div class="d-flex gap-3">
            <div class="form-check">
              <input class="form-check-input" type="radio" name="nb_tipo" id="nb_tipo_b" value="0" checked>
              <label class="form-check-label small" for="nb_tipo_b">Beneficiario</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="nb_tipo" id="nb_tipo_i" value="I">
              <label class="form-check-label small" for="nb_tipo_i">Intermediario</label>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" id="nb_guardar" class="btn btn-success btn-sm">
          <i class="bi bi-check-lg me-1"></i>Guardar
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Dictado por voz -->
<script>
var micRecognition  = null;
var micActivo       = false;
var micCampoActual  = null;
var micBtnActual    = null;
var micStatusActual = null;
var micInterimActual= null;
var micLang         = 'es-EC';

function toggleDictado(campoId, btnId, statusId, interimId) {
    if (micActivo && micCampoActual === campoId) {
        detenerDictado();
        return;
    }
    if (micActivo) detenerDictado(); // detener el anterior si hay uno activo
    iniciarDictado(campoId, btnId, statusId, interimId);
}

function iniciarDictado(campoId, btnId, statusId, interimId) {
    var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SR) {
        alert('El dictado por voz requiere Google Chrome o Microsoft Edge.');
        return;
    }

    micCampoActual   = campoId;
    micBtnActual     = btnId;
    micStatusActual  = statusId;
    micInterimActual = interimId;

    micRecognition = new SR();
    micRecognition.lang           = micLang;
    micRecognition.continuous     = true;
    micRecognition.interimResults = true;

    micRecognition.onresult = function(event) {
        var interim = '', finalText = '';
        for (var i = event.resultIndex; i < event.results.length; i++) {
            if (event.results[i].isFinal) finalText += event.results[i][0].transcript + ' ';
            else interim += event.results[i][0].transcript;
        }
        document.getElementById(interimId).textContent = interim;
        if (finalText) {
            var campo = document.getElementById(campoId);
            campo.value += finalText;
            campo.dispatchEvent(new Event('input')); // dispara validaciones existentes
            document.getElementById(interimId).textContent = '';
        }
    };

    micRecognition.onerror = function(e) {
        if (e.error === 'no-speech') return;
        detenerDictado();
        if (e.error === 'not-allowed') alert('Permiso de micrófono denegado. Habilítalo en la barra del navegador.');
    };

    micRecognition.onend = function() {
        if (micActivo) micRecognition.start(); // auto-reinicio por timeout
    };

    micRecognition.start();
    micActivo = true;

    var btn = document.getElementById(btnId);
    btn.classList.add('escuchando');
    btn.innerHTML = '<i class="bi bi-mic-fill"></i>';
    btn.title = 'Detener dictado';
    document.getElementById(statusId).classList.remove('d-none');
}

function detenerDictado() {
    micActivo = false;
    if (micRecognition) { micRecognition.stop(); micRecognition = null; }

    if (micBtnActual) {
        var btn = document.getElementById(micBtnActual);
        if (btn) {
            btn.classList.remove('escuchando');
            btn.innerHTML = '<i class="bi bi-mic"></i>';
            btn.title = 'Dictado por voz';
        }
    }
    if (micStatusActual)  document.getElementById(micStatusActual)?.classList.add('d-none');
    if (micInterimActual) document.getElementById(micInterimActual).textContent = '';

    micCampoActual = micBtnActual = micStatusActual = micInterimActual = null;
}

// Detener dictado si el usuario envía el formulario
document.querySelector('form[action="movimientos.php"]')?.addEventListener('submit', detenerDictado);
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
function abrirModalAnular(id) {
    document.getElementById('id_anular').value = id;
    var myModal = new bootstrap.Modal(document.getElementById('modalAnular'));
    myModal.show();
}

$(document).ready(function() {
    var table = $('#tablaMovimientos').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100, -1],
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
        },
        autoWidth: false,
        columnDefs: [
            { orderable: false, targets: [8, 17, 18] },
            { width: '60px', targets: 0 },
            { width: '85px', targets: 1 }
        ],
        order: []
    });

    // --- Validación Doc. Soporte en tiempo real ---
    var checkTimerDoc;
    $('#doc_soporte').on('input', function() {
        var val = $(this).val().trim();
        clearTimeout(checkTimerDoc);
        $(this).removeClass('is-valid is-invalid');
        $('#doc_feedback').text('').removeClass('text-success text-danger');
        if (val.length < 2) return;
        checkTimerDoc = setTimeout(function() {
            $.post('ajax_doc_soporte.php', { action: 'check', doc: val }, function(res) {
                if (res.exists) {
                    $('#doc_soporte').addClass('is-invalid');
                    var detalle = '';
                    if (res.fecha)    detalle += ' · Fecha: ' + res.fecha;
                    if (res.usuario)  detalle += ' · Usuario: ' + res.usuario;
                    if (res.oficina)  detalle += ' · Oficina: ' + res.oficina;
                    $('#doc_feedback')
                        .html('<span class="text-danger"><i class="bi bi-x-circle me-1"></i>Documento ya registrado.' + detalle + '</span>');
                } else {
                    $('#doc_soporte').addClass('is-valid');
                    $('#doc_feedback').html('<span class="text-success"><i class="bi bi-check-circle me-1"></i>Disponible.</span>');
                }
            }, 'json');
        }, 500);
    });

    // Bloquear submit si el doc está marcado como duplicado
    $('form[action="movimientos.php"]').on('submit', function(e) {
        if ($('#doc_soporte').hasClass('is-invalid')) {
            e.preventDefault();
            $('#doc_soporte').focus();
            $('#doc_feedback').html('<span class="text-danger fw-bold"><i class="bi bi-x-circle me-1"></i>Corrige el documento antes de guardar.</span>');
        }
    });

    // --- Modal Nuevo Banco ---
    var checkTimerBanco;

    $('#modalNuevoBanco').on('show.bs.modal', function() {
        $('#nb2_nombre').val('').removeClass('is-valid is-invalid');
        $('#nb2_feedback').text('').removeClass('text-success text-danger');
        $('#nb2_guardar').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Guardar');
    });
    $('#modalNuevoBanco').on('shown.bs.modal', function() { $('#nb2_nombre').focus(); });

    $('#nb2_nombre').on('input', function() {
        var val = $(this).val().trim();
        clearTimeout(checkTimerBanco);
        $(this).removeClass('is-valid is-invalid');
        $('#nb2_feedback').text('').removeClass('text-success text-danger');
        if (val.length < 2) return;
        checkTimerBanco = setTimeout(function() {
            $.post('ajax_banco.php', { action: 'check', nombre: val }, function(res) {
                if (res.exists) {
                    $('#nb2_nombre').addClass('is-invalid');
                    $('#nb2_feedback').text('⚠ Ya existe un banco con ese nombre.').addClass('text-danger');
                } else {
                    $('#nb2_nombre').addClass('is-valid');
                    $('#nb2_feedback').text('✓ Disponible.').addClass('text-success');
                }
            }, 'json');
        }, 500);
    });

    $('#nb2_guardar').on('click', function() {
        var nombre = $('#nb2_nombre').val().trim();
        if (nombre.length < 2) {
            $('#nb2_nombre').addClass('is-invalid');
            $('#nb2_feedback').text('Ingresa el nombre del banco.').addClass('text-danger');
            return;
        }
        if ($('#nb2_nombre').hasClass('is-invalid')) return;

        $('#nb2_guardar').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

        $.post('ajax_banco.php', { action: 'crear', nombre: nombre }, function(res) {
            if (res.success) {
                // Agregar como <option> al select de bancos y seleccionarlo
                $('select[name="ban"]').append('<option value="'+res.nombre+'" selected>'+res.nombre+'</option>');
                $('select[name="ban"]').val(res.nombre);

                bootstrap.Modal.getInstance(document.getElementById('modalNuevoBanco')).hide();

                var toast = $('<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999"><div class="toast show align-items-center text-bg-dark border-0"><div class="d-flex"><div class="toast-body"><i class="bi bi-check-circle me-2"></i>Banco creado y seleccionado.</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div></div>');
                $('body').append(toast);
                setTimeout(function() { toast.remove(); }, 3000);
            } else {
                $('#nb2_nombre').addClass('is-invalid');
                $('#nb2_feedback').text(res.msg).addClass('text-danger');
                $('#nb2_guardar').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Guardar');
            }
        }, 'json').fail(function() {
            $('#nb2_guardar').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Guardar');
            alert('Error de conexión. Intenta nuevamente.');
        });
    });

    // --- Modal Nueva Inf. Financiera ---
    var checkTimerIF, checkTimerCat;

    // Cargar categorías al abrir el modal
    $('#modalNuevaInfFin').on('show.bs.modal', function() {
        $('#if_cat_select').html('<option value="">Cargando...</option>');
        $('#if_nueva_cat_toggle').prop('checked', false);
        $('#if_nueva_cat_wrap').addClass('d-none');
        $('#if_nueva_cat').val('').removeClass('is-valid is-invalid');
        $('#if_nueva_cat_feedback').text('').removeClass('text-success text-danger');
        $('#if_reposicion').val('').removeClass('is-valid is-invalid');
        $('#if_reposicion_feedback').text('').removeClass('text-success text-danger');
        $('#if_preview').addClass('d-none');
        $('#if_guardar').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Guardar');

        $.post('ajax_inf_financiera.php', { action: 'categorias' }, function(cats) {
            var opts = '<option value="">Selecciona una categoría...</option>';
            $.each(cats, function(i, c) { opts += '<option value="'+c.id+'">'+c.nombre+'</option>'; });
            $('#if_cat_select').html(opts);
        }, 'json');
    });
    $('#modalNuevaInfFin').on('shown.bs.modal', function() { $('#if_reposicion').focus(); });

    // Toggle nueva categoría
    $('#if_nueva_cat_toggle').on('change', function() {
        if ($(this).is(':checked')) {
            $('#if_nueva_cat_wrap').removeClass('d-none');
            $('#if_cat_select').prop('disabled', true);
            $('#if_nueva_cat').focus();
        } else {
            $('#if_nueva_cat_wrap').addClass('d-none');
            $('#if_cat_select').prop('disabled', false);
            $('#if_nueva_cat').val('').removeClass('is-valid is-invalid');
            $('#if_nueva_cat_feedback').text('').removeClass('text-success text-danger');
        }
        actualizarPreviewIF();
        validarReposicionIF();
    });

    // Validación nueva categoría
    $('#if_nueva_cat').on('input', function() {
        var val = $(this).val().trim();
        clearTimeout(checkTimerCat);
        $(this).removeClass('is-valid is-invalid');
        $('#if_nueva_cat_feedback').text('').removeClass('text-success text-danger');
        if (val.length < 2) { actualizarPreviewIF(); return; }
        checkTimerCat = setTimeout(function() {
            $.post('ajax_inf_financiera.php', { action: 'check_categoria', nombre: val }, function(res) {
                if (res.exists) {
                    $('#if_nueva_cat').addClass('is-invalid');
                    $('#if_nueva_cat_feedback').text('⚠ Ya existe — se usará la categoría existente.').addClass('text-danger');
                } else {
                    $('#if_nueva_cat').addClass('is-valid');
                    $('#if_nueva_cat_feedback').text('✓ Se creará esta categoría.').addClass('text-success');
                }
                actualizarPreviewIF();
                validarReposicionIF();
            }, 'json');
        }, 500);
    });

    $('#if_cat_select').on('change', function() {
        actualizarPreviewIF();
        validarReposicionIF();
    });

    function getCatNombreIF() {
        if ($('#if_nueva_cat_toggle').is(':checked')) return $('#if_nueva_cat').val().trim().toUpperCase();
        return $('#if_cat_select option:selected').text();
    }

    function actualizarPreviewIF() {
        var cat = getCatNombreIF();
        var rep = $('#if_reposicion').val().trim().toUpperCase();
        if (cat && cat !== 'Selecciona una categoría...' && rep.length > 1) {
            $('#if_preview_text').text(cat + ' / ' + rep);
            $('#if_preview').removeClass('d-none');
        } else {
            $('#if_preview').addClass('d-none');
        }
    }

    function validarReposicionIF() {
        var rep    = $('#if_reposicion').val().trim();
        var id_cat = $('#if_nueva_cat_toggle').is(':checked') ? 0 : parseInt($('#if_cat_select').val() || 0);
        $('#if_reposicion').removeClass('is-valid is-invalid');
        $('#if_reposicion_feedback').text('').removeClass('text-success text-danger');
        if (rep.length < 2 || id_cat === 0) return;
        $.post('ajax_inf_financiera.php', { action: 'check_reposicion', reposicion: rep, id_cat: id_cat }, function(res) {
            if (res.exists) {
                $('#if_reposicion').addClass('is-invalid');
                $('#if_reposicion_feedback').text('⚠ Ya existe en esa categoría.').addClass('text-danger');
            } else {
                $('#if_reposicion').addClass('is-valid');
                $('#if_reposicion_feedback').text('✓ Disponible.').addClass('text-success');
            }
        }, 'json');
    }

    // Validación reposición en tiempo real
    $('#if_reposicion').on('input', function() {
        clearTimeout(checkTimerIF);
        actualizarPreviewIF();
        checkTimerIF = setTimeout(validarReposicionIF, 500);
    });

    // Guardar
    $('#if_guardar').on('click', function() {
        var reposicion     = $('#if_reposicion').val().trim();
        var usar_nueva_cat = $('#if_nueva_cat_toggle').is(':checked') ? '1' : '0';
        var nueva_cat      = $('#if_nueva_cat').val().trim();
        var id_cat         = $('#if_cat_select').val() || 0;

        if (reposicion.length < 2) {
            $('#if_reposicion').addClass('is-invalid');
            $('#if_reposicion_feedback').text('Ingresa el nombre del concepto.').addClass('text-danger');
            return;
        }
        if ($('#if_reposicion').hasClass('is-invalid')) return;
        if (usar_nueva_cat === '0' && !id_cat) {
            $('#if_cat_select').addClass('is-invalid'); return;
        }

        $('#if_guardar').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

        $.post('ajax_inf_financiera.php', {
            action: 'crear', reposicion: reposicion,
            usar_nueva_cat: usar_nueva_cat, nueva_cat: nueva_cat, id_cat_existente: id_cat
        }, function(res) {
            if (res.success) {
                var opt = new Option(res.label, res.id, true, true);
                $('#id_reposicion').append(opt).trigger('change');

                bootstrap.Modal.getInstance(document.getElementById('modalNuevaInfFin')).hide();

                var toast = $('<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999"><div class="toast show align-items-center border-0" style="background:#2c3e50;color:#fff;"><div class="d-flex"><div class="toast-body"><i class="bi bi-check-circle me-2"></i>Inf. financiera creada y seleccionada.</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div></div>');
                $('body').append(toast);
                setTimeout(function() { toast.remove(); }, 3000);
            } else {
                if (res.msg.includes('categoría')) {
                    $('#if_nueva_cat').addClass('is-invalid');
                    $('#if_nueva_cat_feedback').text(res.msg).addClass('text-danger');
                } else {
                    $('#if_reposicion').addClass('is-invalid');
                    $('#if_reposicion_feedback').text(res.msg).addClass('text-danger');
                }
                $('#if_guardar').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Guardar');
            }
        }, 'json').fail(function() {
            $('#if_guardar').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Guardar');
            alert('Error de conexión. Intenta nuevamente.');
        });
    });

    // --- Modal Nuevo Proyecto ---
    var checkTimerProy;

    $('#modalNuevoProyecto').on('show.bs.modal', function() {
        $('#np_nombre').val('').removeClass('is-valid is-invalid');
        $('#np_feedback').text('').removeClass('text-success text-danger');
        $('#np_guardar').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Guardar');
    });
    $('#modalNuevoProyecto').on('shown.bs.modal', function() {
        $('#np_nombre').focus();
    });

    $('#np_nombre').on('input', function() {
        var val = $(this).val().trim();
        clearTimeout(checkTimerProy);
        $('#np_feedback').text('').removeClass('text-success text-danger');
        $(this).removeClass('is-valid is-invalid');
        if (val.length < 2) return;
        checkTimerProy = setTimeout(function() {
            $.post('ajax_proyecto.php', { action: 'check', proyecto: val }, function(res) {
                if (res.exists) {
                    $('#np_nombre').addClass('is-invalid');
                    $('#np_feedback').text('⚠ Ya existe un proyecto con ese nombre.').addClass('text-danger');
                } else {
                    $('#np_nombre').addClass('is-valid');
                    $('#np_feedback').text('✓ Disponible.').addClass('text-success');
                }
            }, 'json');
        }, 500);
    });

    $('#np_guardar').on('click', function() {
        var nombre = $('#np_nombre').val().trim();
        if (nombre.length < 2) {
            $('#np_nombre').addClass('is-invalid');
            $('#np_feedback').text('Ingresa el nombre del proyecto.').addClass('text-danger');
            return;
        }
        if ($('#np_nombre').hasClass('is-invalid')) return;

        $('#np_guardar').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

        $.post('ajax_proyecto.php', { action: 'crear', proyecto: nombre }, function(res) {
            if (res.success) {
                var opt = new Option(res.proyecto, res.id, true, true);
                $('select[name="id_proyecto"]').append(opt).trigger('change');

                bootstrap.Modal.getInstance(document.getElementById('modalNuevoProyecto')).hide();

                var toast = $('<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999"><div class="toast show align-items-center text-bg-primary border-0"><div class="d-flex"><div class="toast-body"><i class="bi bi-check-circle me-2"></i>Proyecto creado y seleccionado.</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div></div>');
                $('body').append(toast);
                setTimeout(function() { toast.remove(); }, 3000);
            } else {
                $('#np_nombre').addClass('is-invalid');
                $('#np_feedback').text(res.msg).addClass('text-danger');
                $('#np_guardar').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Guardar');
            }
        }, 'json').fail(function() {
            $('#np_guardar').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Guardar');
            alert('Error de conexión. Intenta nuevamente.');
        });
    });

    // --- Modal Nuevo Beneficiario ---
    var checkTimer;

    // Limpiar modal al abrirse
    $('#modalNuevoBeneficiario').on('show.bs.modal', function() {
        $('#nb_razon').val('').removeClass('is-valid is-invalid');
        $('#nb_feedback').text('').removeClass('text-success text-danger');
        $('input[name="nb_tipo"][value="0"]').prop('checked', true);
        $('#nb_guardar').prop('disabled', false);
    });
    $('#modalNuevoBeneficiario').on('shown.bs.modal', function() {
        $('#nb_razon').focus();
    });

    // Validación en tiempo real (debounce 500ms)
    $('#nb_razon').on('input', function() {
        var val = $(this).val().trim();
        clearTimeout(checkTimer);
        $('#nb_feedback').text('').removeClass('text-success text-danger');
        $(this).removeClass('is-valid is-invalid');
        if (val.length < 2) return;
        checkTimer = setTimeout(function() {
            $.post('ajax_beneficiario.php', { action: 'check', razon_social: val }, function(res) {
                if (res.exists) {
                    $('#nb_razon').addClass('is-invalid');
                    $('#nb_feedback').text('⚠ Ya existe un registro con esa razón social.').addClass('text-danger');
                } else {
                    $('#nb_razon').addClass('is-valid');
                    $('#nb_feedback').text('✓ Disponible.').addClass('text-success');
                }
            }, 'json');
        }, 500);
    });

    // Guardar nuevo beneficiario
    $('#nb_guardar').on('click', function() {
        var razon = $('#nb_razon').val().trim();
        var tipo  = $('input[name="nb_tipo"]:checked').val();

        if (razon.length < 2) {
            $('#nb_razon').addClass('is-invalid');
            $('#nb_feedback').text('Ingresa la razón social.').addClass('text-danger');
            return;
        }
        if ($('#nb_razon').hasClass('is-invalid')) return;

        $('#nb_guardar').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

        $.post('ajax_beneficiario.php', { action: 'crear', razon_social: razon, tipo: tipo }, function(res) {
            if (res.success) {
                // Agregar a select Beneficiario y seleccionar
                var opt = new Option(res.razon_social, res.razon_social, true, true);
                $('select[name="inter"]').append(opt).trigger('change');

                // Si es Intermediario, también al select de Intermediario
                if (res.tipo === 'I') {
                    var opt2 = new Option(res.razon_social, res.razon_social, true, true);
                    $('select[name="intermediario"]').append(opt2).trigger('change');
                }

                bootstrap.Modal.getInstance(document.getElementById('modalNuevoBeneficiario')).hide();

                // Toast de confirmación
                var toast = $('<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999"><div class="toast show align-items-center text-bg-success border-0"><div class="d-flex"><div class="toast-body"><i class="bi bi-check-circle me-2"></i>Beneficiario creado y seleccionado.</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div></div>');
                $('body').append(toast);
                setTimeout(function() { toast.remove(); }, 3000);
            } else {
                $('#nb_razon').addClass('is-invalid');
                $('#nb_feedback').text(res.msg).addClass('text-danger');
                $('#nb_guardar').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Guardar');
            }
        }, 'json').fail(function() {
            $('#nb_guardar').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Guardar');
            alert('Error de conexión. Intenta nuevamente.');
        });
    });

    // Filtro por columna usando los inputs de la segunda fila del thead
    $('#tablaMovimientos thead tr:eq(1) th').each(function(i) {
        var input = $(this).find('input.col-search');
        if (input.length) {
            input.on('keyup change', function() {
                if (table.column(i).search() !== this.value) {
                    table.column(i).search(this.value).draw();
                }
            });
        }
    });
});
</script>
</body>
</html>
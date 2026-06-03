<?php
require_once 'config.php';
require_once 'auth.php';
verificar_auth();

// 1. GESTIÓN DE SESIÓN Y SEGURIDAD
$id_usuario_sesion = $_SESSION["user_id"];
$user_rol = $_SESSION["user_rol"];

// 2. CAPTURA DE FILTROS (Oficina y Fechas)
// Si no hay oficina seleccionada, usamos la del usuario logueado por defecto
$id_oficina_consulta = isset($_GET['id_oficina']) ? intval($_GET['id_oficina']) : $_SESSION["oficina_ID"];
$fecha_inicio = isset($_GET['desde']) ? $_GET['desde'] : date('Y-m-01');
$fecha_fin = isset($_GET['hasta']) ? $_GET['hasta'] : date('Y-m-t');

// 3. QUERY: TOTALES GENERALES (INGRESOS / EGRESOS)
$sql_resumen = "SELECT 
    SUM(CASE WHEN ESTADO = 'A' THEN importe_recibido ELSE 0 END) as ingresos,
    SUM(CASE WHEN ESTADO = 'A' THEN importe_entregado ELSE 0 END) as egresos
    FROM movimientos 
    WHERE ID_OFICINA = ? 
    AND fecha BETWEEN ? AND ?";

$stmt = $conn->prepare($sql_resumen);
$stmt->bind_param("iss", $id_oficina_consulta, $fecha_inicio, $fecha_fin);
$stmt->execute();
$resumen = $stmt->get_result()->fetch_assoc();
$t_ingresos = $resumen['ingresos'] ?? 0;
$t_egresos = $resumen['egresos'] ?? 0;
$saldo = $t_ingresos - $t_egresos;

// 3b. QUERY: TOTALES DEL PERIODO ANTERIOR (misma duración, corrido hacia atrás)
$dias_periodo = (new DateTime($fecha_inicio))->diff(new DateTime($fecha_fin))->days + 1;
$prev_fin     = (new DateTime($fecha_inicio))->modify('-1 day')->format('Y-m-d');
$prev_inicio  = (new DateTime($prev_fin))->modify('-' . ($dias_periodo - 1) . ' days')->format('Y-m-d');

$stmt_prev = $conn->prepare($sql_resumen);
$stmt_prev->bind_param("iss", $id_oficina_consulta, $prev_inicio, $prev_fin);
$stmt_prev->execute();
$resumen_prev   = $stmt_prev->get_result()->fetch_assoc();
$prev_ingresos  = $resumen_prev['ingresos'] ?? 0;
$prev_egresos   = $resumen_prev['egresos'] ?? 0;
$prev_saldo     = $prev_ingresos - $prev_egresos;

// Función auxiliar para calcular variación porcentual
function pct_change($actual, $anterior) {
    if ($anterior == 0) return $actual > 0 ? 100 : 0;
    return round((($actual - $anterior) / abs($anterior)) * 100, 1);
}

$pct_saldo    = pct_change($saldo,       $prev_saldo);
$pct_ingresos = pct_change($t_ingresos,  $prev_ingresos);
$pct_egresos  = pct_change($t_egresos,   $prev_egresos);

// 3c. QUERY: TOTAL DE TRANSACCIONES (periodo actual y anterior)
$sql_txn = "SELECT COUNT(*) as total FROM movimientos WHERE ID_OFICINA = ? AND ESTADO = 'A' AND fecha BETWEEN ? AND ?";

$stmt_txn = $conn->prepare($sql_txn);
$stmt_txn->bind_param("iss", $id_oficina_consulta, $fecha_inicio, $fecha_fin);
$stmt_txn->execute();
$t_transacciones = $stmt_txn->get_result()->fetch_assoc()['total'] ?? 0;

$stmt_txn_prev = $conn->prepare($sql_txn);
$stmt_txn_prev->bind_param("iss", $id_oficina_consulta, $prev_inicio, $prev_fin);
$stmt_txn_prev->execute();
$prev_transacciones = $stmt_txn_prev->get_result()->fetch_assoc()['total'] ?? 0;

$pct_transacciones = pct_change($t_transacciones, $prev_transacciones);

// 4. QUERY: TOP 5 CATEGORÍAS DE GASTO
$sql_cats = "SELECT c.nombre, SUM(m.importe_entregado) as total 
             FROM movimientos m
             JOIN CAT_REPOSICION r ON m.inf_fin = r.ID_REPOSICION
             JOIN cat_reposiciones c ON r.ID_CAT_REPOCICIONES = c.id
             WHERE m.ID_OFICINA = ? AND m.ESTADO = 'A' 
             AND m.fecha BETWEEN ? AND ?
             GROUP BY c.nombre ORDER BY total DESC LIMIT 5";

$stmt_cat = $conn->prepare($sql_cats);
$stmt_cat->bind_param("iss", $id_oficina_consulta, $fecha_inicio, $fecha_fin);
$stmt_cat->execute();
$res_categorias = $stmt_cat->get_result();

// 5. QUERY: NOMBRE DE LA OFICINA ACTUAL
$stmt_off_name = $conn->prepare("SELECT OFICINA FROM CTR_OFICINA WHERE ID_OFICINA = ?");
$stmt_off_name->bind_param("i", $id_oficina_consulta);
$stmt_off_name->execute();
$nombre_oficina = $stmt_off_name->get_result()->fetch_assoc()['OFICINA'] ?? "Sin Nombre";

//mapa de calor

date_default_timezone_set('America/Guayaquil');
$anio_actual = date('Y');

$sql_heatmap = "SELECT
                    MONTH(fecha) as mes,
                    DAY(fecha) as dia,
                    SUM(importe_recibido + importe_entregado) as total_diario
                FROM movimientos
                WHERE YEAR(fecha) = ? AND id_oficina = ?
                GROUP BY MONTH(fecha), DAY(fecha)";

$stmt_heat = $conn->prepare($sql_heatmap);
$stmt_heat->bind_param("ii", $anio_actual, $id_oficina_consulta);
$stmt_heat->execute();
$res_heat = $stmt_heat->get_result();

// Construimos un array indexado por [mes][dia] para dibujarlo fácil en HTML
$datos_mapa = [];
while ($row = $res_heat->fetch_assoc()) {
    $datos_mapa[$row['mes']][$row['dia']] = $row['total_diario'];
}

// Nombres de los meses para las cabeceras
$meses_nombres = [
    1 => "Ene", 2 => "Feb", 3 => "Mar", 4 => "Abr", 5 => "May", 6 => "Jun",
    7 => "Jul", 8 => "Ago", 9 => "Sep", 10 => "Oct", 11 => "Nov", 12 => "Dic"
];

// 6. QUERY: TENDENCIA DIARIA DEL PERIODO FILTRADO
$sql_tendencia = "SELECT
    DATE_FORMAT(fecha, '%Y-%m-%d') as dia,
    SUM(CASE WHEN ESTADO = 'A' THEN importe_recibido ELSE 0 END) as ingresos,
    SUM(CASE WHEN ESTADO = 'A' THEN importe_entregado ELSE 0 END) as egresos
    FROM movimientos
    WHERE ID_OFICINA = ? AND fecha BETWEEN ? AND ?
    GROUP BY DATE_FORMAT(fecha, '%Y-%m-%d')
    ORDER BY dia ASC";

$stmt_tend = $conn->prepare($sql_tendencia);
$stmt_tend->bind_param("iss", $id_oficina_consulta, $fecha_inicio, $fecha_fin);
$stmt_tend->execute();
$res_tendencia = $stmt_tend->get_result();

$labels_tend = [];
$data_ing_tend = [];
$data_egr_tend = [];

while ($row = $res_tendencia->fetch_assoc()) {
    $labels_tend[]    = $row['dia'];
    $data_ing_tend[]  = (float)$row['ingresos'];
    $data_egr_tend[]  = (float)$row['egresos'];
}

$json_labels   = json_encode($labels_tend);
$json_ingresos = json_encode($data_ing_tend);
$json_egresos  = json_encode($data_egr_tend);

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Financiero | BUADNET</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .card-dash { border: none; border-radius: 15px; transition: transform 0.2s; }
        .card-dash:hover { transform: translateY(-5px); }
        .icon-box { width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; border-radius: 12px; }
    </style>
</head>
<body class="bg-light">

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0 text-dark">Dashboard Financiero</h2>
                <p class="text-muted small">Oficina: <span class="fw-bold text-primary"><?php echo $nombre_oficina; ?></span></p>
            </div>
            <a href="movimientos.php" class="btn btn-dark shadow-sm"><i class="bi bi-arrow-left"></i> Volver</a>
        </div>

        <div class="card card-dash shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Oficina</label>
                        <select name="id_oficina" class="form-select form-select-sm">
                            <?php 
                            $off_list = $conn->query("SELECT ID_OFICINA, OFICINA FROM CTR_OFICINA ORDER BY OFICINA ASC");
                            while($o = $off_list->fetch_assoc()): ?>
                                <option value="<?php echo $o['ID_OFICINA']; ?>" <?php echo ($o['ID_OFICINA'] == $id_oficina_consulta) ? 'selected' : ''; ?>>
                                    <?php echo $o['OFICINA']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Desde</label>
                        <input type="date" name="desde" class="form-control form-control-sm" value="<?php echo $fecha_inicio; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Hasta</label>
                        <input type="date" name="hasta" class="form-control form-control-sm" value="<?php echo $fecha_fin; ?>">
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm w-100">Consultar</button>
                        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">Limpiar</a>
                    </div>
                </form>
            </div>
        </div>

        <?php
        // Badge de variación reutilizable
        function badge_var($pct, $invert = false) {
            if ($pct == 0) {
                return '<span class="badge bg-secondary bg-opacity-25 text-secondary ms-2 small"><i class="bi bi-dash"></i> Sin cambio</span>';
            }
            $sube  = $pct > 0;
            $bueno = $invert ? !$sube : $sube; // para egresos: subir es malo
            $color = $bueno ? 'success' : 'danger';
            $icon  = $sube  ? 'bi-arrow-up-short' : 'bi-arrow-down-short';
            $sign  = $sube  ? '+' : '';
            return "<span class=\"badge bg-{$color} bg-opacity-20 text-{$color} ms-2 small\"><i class=\"bi {$icon}\"></i> {$sign}{$pct}%</span>";
        }
        ?>
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card card-dash shadow-sm bg-primary text-white h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="icon-box bg-white text-primary me-3"><i class="bi bi-wallet2 fs-4"></i></div>
                        <div class="flex-grow-1">
                            <p class="mb-0 opacity-75 small">SALDO ACTUAL</p>
                            <h3 class="mb-0 fw-bold">$<?php echo number_format($saldo, 2); ?></h3>
                            <div class="mt-1 opacity-90 small">
                                vs anterior: <strong>$<?php echo number_format($prev_saldo, 2); ?></strong>
                                <?php echo badge_var($pct_saldo); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-dash shadow-sm h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="icon-box bg-success-subtle text-success me-3"><i class="bi bi-graph-up-arrow fs-4"></i></div>
                        <div class="flex-grow-1">
                            <p class="mb-0 text-muted small">TOTAL INGRESOS</p>
                            <h3 class="mb-0 fw-bold text-success">$<?php echo number_format($t_ingresos, 2); ?></h3>
                            <div class="mt-1 text-muted small">
                                vs anterior: <strong>$<?php echo number_format($prev_ingresos, 2); ?></strong>
                                <?php echo badge_var($pct_ingresos); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-dash shadow-sm h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="icon-box bg-danger-subtle text-danger me-3"><i class="bi bi-graph-down-arrow fs-4"></i></div>
                        <div class="flex-grow-1">
                            <p class="mb-0 text-muted small">TOTAL EGRESOS</p>
                            <h3 class="mb-0 fw-bold text-danger">$<?php echo number_format($t_egresos, 2); ?></h3>
                            <div class="mt-1 text-muted small">
                                vs anterior: <strong>$<?php echo number_format($prev_egresos, 2); ?></strong>
                                <?php echo badge_var($pct_egresos, true); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-dash shadow-sm h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="icon-box bg-warning-subtle text-warning me-3"><i class="bi bi-receipt fs-4"></i></div>
                        <div class="flex-grow-1">
                            <p class="mb-0 text-muted small">TRANSACCIONES</p>
                            <h3 class="mb-0 fw-bold text-dark"><?php echo number_format($t_transacciones); ?></h3>
                            <div class="mt-1 text-muted small">
                                vs anterior: <strong><?php echo number_format($prev_transacciones); ?></strong>
                                <?php echo badge_var($pct_transacciones); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-dark text-white fw-bold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-grid-3x3-gap-fill me-2"></i> Flujo de Caja: Mapa de Calor (<?php echo $anio_actual; ?>)</span>
        <span class="badge bg-secondary small">Indicador: Intensidad por volumen ($) diario</span>
    </div>
    <div class="card-body overflow-auto">
        <div class="d-flex flex-row justify-content-between text-center fw-bold text-muted mb-2 small" style="min-width: 800px;">
            <div style="width: 40px;">Día</div>
            <?php foreach ($meses_nombres as $m_num => $m_nom): ?>
                <div class="flex-fill"><?php echo $m_nom; ?></div>
            <?php endforeach; ?>
        </div>

        <div class="d-flex flex-column" style="min-width: 800px;">
            <?php for ($dia = 1; $dia <= 31; $dia++): ?>
                <div class="d-flex flex-row align-items-center mb-1">
                    <div class="text-end pe-2 small fw-bold text-muted" style="width: 40px; font-size: 0.75rem;">
                        <?php echo $dia; ?>
                    </div>
                    
                    <?php for ($mes = 1; $mes <= 12; $mes++): 
                        // Verificamos si el día existe en ese mes específico (ej: 31 de Febrero no existe)
                        if (!checkdate($mes, $dia, $anio_actual)) {
                            echo '<div class="flex-fill m-1 bg-light border-0" style="height: 24px; opacity: 0.2;"></div>';
                            continue;
                        }

                        $valor = $datos_mapa[$mes][$dia] ?? 0;
                        
                        // --- LÓGICA DE INDICADORES (COLOR SEGÚN INTENSIDAD) ---
                        $bg_color = 'bg-white text-muted'; // Sin movimientos
                        $border = 'border: 1px solid #e9ecef;';
                        $title_hint = "Sin movimientos";

                        if ($valor > 0 && $valor < 300) {
                            $bg_color = 'text-white';
                            $border = 'background-color: #d1e7dd; border: 1px solid #bcd0c7; color: #0f5132 !important;'; // Verde Bajo
                            $title_hint = "Bajo: $".number_format($valor, 2);
                        } elseif ($valor >= 300 && $valor < 1000) {
                            $bg_color = 'text-white';
                            $border = 'background-color: #ffcaf2; border: 1px solid #ffb3ed; color: #aa0088 !important;'; // Promedio Medio (Rosa)
                            $title_hint = "Medio: $".number_format($valor, 2);
                        } elseif ($valor >= 1000) {
                            $bg_color = 'text-white fw-bold';
                            $border = 'background-color: #dc3545; border: 1px solid #b02a37;'; // Promedio Alto (Rojo Alerta)
                            $title_hint = "¡ALTO VOLUMEN!: $".number_format($valor, 2);
                        }
                    ?>
                        <?php if ($valor > 0): ?>
                        <div class="flex-fill m-1 rounded text-center small d-flex align-items-center justify-content-center heatmap-cell <?php echo $bg_color; ?>"
                             style="height: 24px; font-size: 0.65rem; cursor: pointer; <?php echo $border; ?>"
                             title="<?php echo $title_hint; ?> (Día <?php echo $dia; ?>/<?php echo $mes; ?>)"
                             data-dia="<?php echo $dia; ?>"
                             data-mes="<?php echo $mes; ?>"
                             data-anio="<?php echo $anio_actual; ?>"
                             data-oficina="<?php echo $id_oficina_consulta; ?>">
                            $<?php echo round($valor); ?>
                        </div>
                        <?php else: ?>
                        <div class="flex-fill m-1 rounded <?php echo $bg_color; ?>"
                             style="height: 24px; <?php echo $border; ?>"></div>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            <?php endfor; ?>
        </div>
        
        <div class="d-flex justify-content-end gap-3 mt-3 small text-muted">
            <span><span class="badge border bg-white text-dark">■</span> Sin flujo</span>
            <span><span class="badge" style="background-color: #d1e7dd; color: #0f5132;">■</span> Flujo Bajo (< $300)</span>
            <span><span class="badge" style="background-color: #ffcaf2; color: #aa0088;">■</span> Flujo Normal ($300 - $1000)</span>
            <span><span class="badge bg-danger">■</span> Promedio Alto (> $1000)</span>
        </div>
    </div>
</div>
        <div class="row">
            <div class="col-md-8">
                <div class="card card-dash shadow-sm mb-4">
                    <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-graph-up me-2 text-primary"></i>Tendencia Diaria del Periodo</span>
                        <span class="badge bg-primary-subtle text-primary small">
                            <?php echo $fecha_inicio; ?> → <?php echo $fecha_fin; ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <canvas id="mainChart" style="max-height: 340px;"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card card-dash shadow-sm">
                    <div class="card-header bg-white fw-bold py-3">Top 5 Gastos</div>
                    <div class="list-group list-group-flush p-2">
                        <?php while($c = $res_categorias->fetch_assoc()): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center border-0 mb-1 bg-light rounded">
                            <span class="small fw-bold"><?php echo $c['nombre']; ?></span>
                            <span class="badge bg-danger text-white">$<?php echo number_format($c['total'], 2); ?></span>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
            <div class="row mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-danger text-white fw-bold">
                <i class="bi bi-pie-chart-fill me-2"></i> Resumen de Gastos (INF. FIN.)
            </div>
            <div class="card-body p-0">
                <?php
// Consulta para agrupar gastos por tipo de Información Financiera
// Sumamos solo el 'importe_entregado' ya que nos interesan los GASTOS
$id_oficina_actual = $id_oficina_consulta; // Asegúrate de tener esta variable definida
$sql_resumen = "SELECT
                    r.ID_REPOSICION,
                    r.REPOSICION AS nombre_gasto,
                    SUM(m.importe_entregado) AS total_gasto
                FROM movimientos m
                INNER JOIN CAT_REPOSICION r ON m.inf_fin = r.ID_REPOSICION
                WHERE m.id_oficina = ? AND m.importe_entregado > 0
                  AND m.fecha BETWEEN ? AND ?
                GROUP BY r.ID_REPOSICION, r.REPOSICION
                ORDER BY total_gasto DESC";

$stmt_res = $conn->prepare($sql_resumen);
$stmt_res->bind_param("iss", $id_oficina_actual, $fecha_inicio, $fecha_fin);
$stmt_res->execute();
$res_gastos = $stmt_res->get_result();
?>
                <ul class="list-group list-group-flush">
                    <?php 
                    $gran_total = 0;
                    if ($res_gastos->num_rows > 0):
                        while($gasto = $res_gastos->fetch_assoc()): 
                            $gran_total += $gasto['total_gasto'];
                    ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center small px-2 py-1">
                            <span class="me-1"><?php echo htmlspecialchars($gasto['nombre_gasto']); ?></span>
                            <div class="d-flex align-items-center gap-2">
                                <span class="fw-bold text-danger">$<?php echo number_format($gasto['total_gasto'], 2); ?></span>
                                <button class="btn btn-outline-danger btn-sm py-0 px-1 lh-1" style="font-size:0.7rem;"
                                    onclick="verDetalleInfFin(<?php echo $gasto['ID_REPOSICION']; ?>, '<?php echo htmlspecialchars(addslashes($gasto['nombre_gasto'])); ?>')"
                                    title="Ver movimientos">
                                    <i class="bi bi-list-ul"></i>
                                </button>
                            </div>
                        </li>
                    <?php 
                        endwhile; 
                    else:
                    ?>
                        <li class="list-group-item text-center text-muted small">No hay gastos registrados</li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="card-footer bg-light d-flex justify-content-between">
                <span class="fw-bold">TOTAL GASTOS:</span>
                <span class="fw-bold text-danger" style="font-size: 1.1rem;">
                    $<?php echo number_format($gran_total, 2); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Resumen de Ingresos -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-success text-white fw-bold">
                <i class="bi bi-graph-up-arrow me-2"></i> Resumen de Ingresos (INF. FIN.)
            </div>
            <div class="card-body p-0">
                <?php
                $sql_ingresos = "SELECT
                                    r.ID_REPOSICION,
                                    r.REPOSICION AS nombre_ingreso,
                                    SUM(m.importe_recibido) AS total_ingreso
                                FROM movimientos m
                                INNER JOIN CAT_REPOSICION r ON m.inf_fin = r.ID_REPOSICION
                                WHERE m.id_oficina = ? AND m.importe_recibido > 0
                                  AND m.ESTADO = 'A'
                                  AND m.fecha BETWEEN ? AND ?
                                GROUP BY r.ID_REPOSICION, r.REPOSICION
                                ORDER BY total_ingreso DESC";

                $stmt_ing = $conn->prepare($sql_ingresos);
                $stmt_ing->bind_param("iss", $id_oficina_actual, $fecha_inicio, $fecha_fin);
                $stmt_ing->execute();
                $res_ingresos = $stmt_ing->get_result();
                ?>
                <ul class="list-group list-group-flush">
                    <?php
                    $gran_total_ing = 0;
                    if ($res_ingresos->num_rows > 0):
                        while ($ingreso = $res_ingresos->fetch_assoc()):
                            $gran_total_ing += $ingreso['total_ingreso'];
                    ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center small px-2 py-1">
                            <span class="me-1"><?php echo htmlspecialchars($ingreso['nombre_ingreso']); ?></span>
                            <div class="d-flex align-items-center gap-2">
                                <span class="fw-bold text-success">$<?php echo number_format($ingreso['total_ingreso'], 2); ?></span>
                                <button class="btn btn-outline-success btn-sm py-0 px-1 lh-1" style="font-size:0.7rem;"
                                    onclick="verDetalleIngreso(<?php echo $ingreso['ID_REPOSICION']; ?>, '<?php echo htmlspecialchars(addslashes($ingreso['nombre_ingreso'])); ?>')"
                                    title="Ver movimientos">
                                    <i class="bi bi-list-ul"></i>
                                </button>
                            </div>
                        </li>
                    <?php
                        endwhile;
                    else:
                    ?>
                        <li class="list-group-item text-center text-muted small">No hay ingresos registrados</li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="card-footer bg-light d-flex justify-content-between">
                <span class="fw-bold">TOTAL INGRESOS:</span>
                <span class="fw-bold text-success" style="font-size: 1.1rem;">
                    $<?php echo number_format($gran_total_ing, 2); ?>
                </span>
            </div>
        </div>
    </div>
</div>

<?php
// ── Resumen por Proyecto ──────────────────────────────────────────────
$sql_proy = "SELECT
    p.ID_PROYECTO, p.PROYECTO,
    SUM(CASE WHEN m.ESTADO='A' THEN m.importe_recibido  ELSE 0 END) AS total_ing,
    SUM(CASE WHEN m.ESTADO='A' THEN m.importe_entregado ELSE 0 END) AS total_egr
FROM movimientos m
INNER JOIN PROYECTOS p ON m.ID_PROYECTO = p.ID_PROYECTO
WHERE m.ID_OFICINA = ? AND m.fecha BETWEEN ? AND ?
GROUP BY p.ID_PROYECTO, p.PROYECTO
ORDER BY (SUM(CASE WHEN m.ESTADO='A' THEN m.importe_entregado ELSE 0 END)) DESC";

$stmt_proy = $conn->prepare($sql_proy);
$stmt_proy->bind_param("iss", $id_oficina_actual, $fecha_inicio, $fecha_fin);
$stmt_proy->execute();
$res_proy = $stmt_proy->get_result();

$proyectos_data = [];
while ($p = $res_proy->fetch_assoc()) $proyectos_data[] = $p;

$proy_labels = json_encode(array_column($proyectos_data, 'PROYECTO'));
$proy_ing    = json_encode(array_map(fn($p) => round($p['total_ing'], 2), $proyectos_data));
$proy_egr    = json_encode(array_map(fn($p) => round($p['total_egr'], 2), $proyectos_data));
?>

<div class="row mb-4">
    <!-- Tabla resumen por proyecto -->
    <div class="col-md-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header fw-bold text-white" style="background:#2c3e50;">
                <i class="bi bi-kanban-fill me-2"></i> Resumen por Proyecto
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0" style="font-size:0.78rem;">
                    <thead class="text-center" style="background:#ecf0f1;">
                        <tr>
                            <th class="text-start ps-2">PROYECTO</th>
                            <th class="text-success">INGRESOS</th>
                            <th class="text-danger">EGRESOS</th>
                            <th>SALDO</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($proyectos_data)): ?>
                        <tr><td colspan="5" class="text-center text-muted small py-3">Sin proyectos en el período</td></tr>
                    <?php else: foreach ($proyectos_data as $p):
                        $saldo = $p['total_ing'] - $p['total_egr'];
                    ?>
                        <tr>
                            <td class="ps-2"><?php echo htmlspecialchars($p['PROYECTO']); ?></td>
                            <td class="text-end text-success fw-bold">$<?php echo number_format($p['total_ing'], 2); ?></td>
                            <td class="text-end text-danger fw-bold">$<?php echo number_format($p['total_egr'], 2); ?></td>
                            <td class="text-end fw-bold <?php echo $saldo >= 0 ? 'text-success' : 'text-danger'; ?>">
                                $<?php echo number_format($saldo, 2); ?>
                            </td>
                            <td class="text-center">
                                <button class="btn btn-sm py-0 px-1 lh-1" style="font-size:0.7rem;background:#2c3e50;color:#fff;"
                                    onclick="verDetalleProy(<?php echo $p['ID_PROYECTO']; ?>, '<?php echo htmlspecialchars(addslashes($p['PROYECTO'])); ?>')"
                                    title="Ver movimientos">
                                    <i class="bi bi-list-ul"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                    <?php if (!empty($proyectos_data)):
                        $tot_ing = array_sum(array_column($proyectos_data, 'total_ing'));
                        $tot_egr = array_sum(array_column($proyectos_data, 'total_egr'));
                        $tot_sal = $tot_ing - $tot_egr;
                    ?>
                    <tfoot style="background:#ecf0f1;" class="fw-bold">
                        <tr>
                            <td class="ps-2">TOTAL</td>
                            <td class="text-end text-success">$<?php echo number_format($tot_ing, 2); ?></td>
                            <td class="text-end text-danger">$<?php echo number_format($tot_egr, 2); ?></td>
                            <td class="text-end <?php echo $tot_sal >= 0 ? 'text-success' : 'text-danger'; ?>">
                                $<?php echo number_format($tot_sal, 2); ?>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

    <!-- Gráfico por proyecto -->
    <div class="col-md-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header fw-bold text-white" style="background:#2c3e50;">
                <i class="bi bi-bar-chart-fill me-2"></i> Distribución por Proyecto
            </div>
            <div class="card-body">
                <?php if (empty($proyectos_data)): ?>
                    <p class="text-center text-muted mt-4">Sin datos para el período seleccionado.</p>
                <?php else: ?>
                    <canvas id="chartProyectos" style="max-height:280px;"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script>
        <?php if (!empty($proyectos_data)): ?>
        (function() {
            var ctxP = document.getElementById('chartProyectos').getContext('2d');
            new Chart(ctxP, {
                type: 'bar',
                data: {
                    labels: <?php echo $proy_labels; ?>,
                    datasets: [
                        {
                            label: 'Ingresos',
                            data: <?php echo $proy_ing; ?>,
                            backgroundColor: 'rgba(40,167,69,0.75)',
                            borderColor: 'rgba(40,167,69,1)',
                            borderWidth: 1, borderRadius: 4
                        },
                        {
                            label: 'Egresos',
                            data: <?php echo $proy_egr; ?>,
                            backgroundColor: 'rgba(220,53,69,0.75)',
                            borderColor: 'rgba(220,53,69,1)',
                            borderWidth: 1, borderRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            callbacks: {
                                label: ctx => ' $' + ctx.parsed.y.toLocaleString('es-EC', {minimumFractionDigits: 2})
                            }
                        }
                    },
                    scales: {
                        x: { ticks: { font: { size: 10 } } },
                        y: {
                            beginAtZero: true,
                            ticks: { callback: v => '$' + v.toLocaleString('es-EC') }
                        }
                    }
                }
            });
        })();
        <?php endif; ?>

        const ctx = document.getElementById('mainChart').getContext('2d');

        const labelsData   = <?php echo $json_labels; ?>;
        const ingresosData = <?php echo $json_ingresos; ?>;
        const egresosData  = <?php echo $json_egresos; ?>;

        // Formato legible para las etiquetas del eje X
        const labelsFmt = labelsData.map(d => {
            const [y, m, day] = d.split('-');
            const meses = ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
            return day + ' ' + meses[parseInt(m)];
        });

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labelsFmt,
                datasets: [
                    {
                        label: 'Ingresos',
                        data: ingresosData,
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13,110,253,0.08)',
                        borderWidth: 2,
                        pointRadius: labelsData.length > 20 ? 2 : 4,
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'Egresos',
                        data: egresosData,
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220,53,69,0.08)',
                        borderWidth: 2,
                        pointRadius: labelsData.length > 20 ? 2 : 4,
                        tension: 0.3,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: ctx => ' $' + ctx.parsed.y.toLocaleString('es-EC', {minimumFractionDigits: 2})
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            maxTicksLimit: 15,
                            maxRotation: 45,
                            font: { size: 11 }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: v => '$' + v.toLocaleString('es-EC')
                        }
                    }
                }
            }
        });
    </script>

    <!-- Modal Movimientos del Día -->
    <div class="modal fade" id="modalMovDia" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title"><i class="bi bi-calendar-event me-2"></i> Movimientos del <span id="modalFechaLabel"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="modalLoading" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2 text-muted small">Cargando...</p>
                    </div>
                    <div id="modalContenido" class="d-none">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th class="small">Empresa / Concepto</th>
                                    <th class="small">Intermediario</th>
                                    <th class="small">Inf. Fin.</th>
                                    <th class="small text-success text-end">Ingreso</th>
                                    <th class="small text-danger text-end">Egreso</th>
                                    <th class="small text-center">Estado</th>
                                    <th class="small text-center">Revisado</th>
                                </tr>
                            </thead>
                            <tbody id="modalTablaBody"></tbody>
                            <tfoot class="table-light fw-bold">
                                <tr>
                                    <td colspan="3" class="small text-end">TOTAL</td>
                                    <td class="small text-success text-end" id="modalTotalIngresos"></td>
                                    <td class="small text-danger text-end" id="modalTotalEgresos"></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                        <p id="modalSinDatos" class="text-center text-muted py-3 d-none">No se encontraron movimientos.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('.heatmap-cell').forEach(function(cel) {
            cel.addEventListener('click', function() {
                const dia     = this.dataset.dia;
                const mes     = this.dataset.mes;
                const anio    = this.dataset.anio;
                const oficina = this.dataset.oficina;

                const meses = ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
                document.getElementById('modalFechaLabel').textContent =
                    dia + ' de ' + meses[parseInt(mes)] + ' ' + anio;

                document.getElementById('modalLoading').classList.remove('d-none');
                document.getElementById('modalContenido').classList.add('d-none');

                const modal = new bootstrap.Modal(document.getElementById('modalMovDia'));
                modal.show();

                fetch(`get_movimientos_dia.php?dia=${dia}&mes=${mes}&anio=${anio}&id_oficina=${oficina}`)
                    .then(r => r.json())
                    .then(function(data) {
                        document.getElementById('modalLoading').classList.add('d-none');
                        document.getElementById('modalContenido').classList.remove('d-none');

                        const tbody = document.getElementById('modalTablaBody');
                        const sinDatos = document.getElementById('modalSinDatos');
                        tbody.innerHTML = '';

                        if (!data.length) {
                            sinDatos.classList.remove('d-none');
                            return;
                        }
                        sinDatos.classList.add('d-none');

                        let totalIng = 0, totalEgr = 0;

                        data.forEach(function(m) {
                            const ing = parseFloat(m.importe_recibido) || 0;
                            const egr = parseFloat(m.importe_entregado) || 0;
                            totalIng += ing;
                            totalEgr += egr;
                           

                            const estadoBadge = m.ESTADO === 'A'
                                ? '<span class="badge bg-success">Aprobado</span>'
                                : '<span class="badge bg-secondary">Inactivo</span>';

                            tbody.innerHTML += `<tr>
                                <td class="small">
                                    <div class="fw-bold">${m.empresa || '-'}</div>
                                    <div class="text-muted">${m.concepto || '-'}</div>
                                </td>
                                <td class="small">${m.intermediario || '-'}</td>
                                <td class="small">${m.inf_fin_nombre || '-'}</td>
                                <td class="small text-success text-end">${ing > 0 ? '$' + ing.toFixed(2) : '-'}</td>
                                <td class="small text-danger text-end">${egr > 0 ? '$' + egr.toFixed(2) : '-'}</td>
                                <td class="small text-center">${estadoBadge}</td>
                                <td class="small text-center">${m.nombre_revisor || '-'}</td>
                            </tr>`;
                        });

                        document.getElementById('modalTotalIngresos').textContent =
                            totalIng > 0 ? '$' + totalIng.toFixed(2) : '-';
                        document.getElementById('modalTotalEgresos').textContent =
                            totalEgr > 0 ? '$' + totalEgr.toFixed(2) : '-';
                    })
                    .catch(function() {
                        document.getElementById('modalLoading').classList.add('d-none');
                        document.getElementById('modalContenido').classList.remove('d-none');
                        document.getElementById('modalTablaBody').innerHTML =
                            '<tr><td colspan="6" class="text-center text-danger">Error al cargar datos.</td></tr>';
                    });
            });
        });
    </script>

<!-- Modal: Detalle por Proyecto -->
<div class="modal fade" id="modalDetalleProy" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header text-white py-2" style="background:#2c3e50;">
        <h6 class="modal-title mb-0"><i class="bi bi-kanban me-2"></i><span id="proy_titulo">Proyecto</span></h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-2">
        <div id="proy_loading" class="text-center py-4">
          <div class="spinner-border" style="color:#2c3e50;" role="status"></div>
          <p class="mt-2 small text-muted">Cargando movimientos...</p>
        </div>
        <div id="proy_contenido" class="d-none">
          <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover mb-0" style="font-size:0.78rem;">
              <thead class="text-center" style="background:#2c3e50;color:#fff;">
                <tr>
                  <th>#</th><th>FECHA</th><th>BENEFICIARIO</th><th>DESCRIPCIÓN</th>
                  <th>DOC.</th><th>INF. FIN.</th>
                  <th class="text-success">INGRESO</th>
                  <th class="text-danger">EGRESO</th>
                  <th>ESTADO</th><th>USUARIO</th>
                </tr>
              </thead>
              <tbody id="proy_tbody"></tbody>
              <tfoot style="background:#ecf0f1;" class="fw-bold">
                <tr>
                  <td colspan="6" class="text-end">TOTAL ACTIVOS:</td>
                  <td class="text-end text-success" id="proy_total_ing"></td>
                  <td class="text-end text-danger" id="proy_total_egr"></td>
                  <td colspan="2" class="text-end" id="proy_saldo"></td>
                </tr>
              </tfoot>
            </table>
          </div>
          <p id="proy_vacio" class="text-center text-muted small py-3 d-none">No hay movimientos para este período.</p>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function verDetalleProy(idProy, nombre) {
    document.getElementById('proy_titulo').textContent = nombre;
    document.getElementById('proy_loading').classList.remove('d-none');
    document.getElementById('proy_contenido').classList.add('d-none');
    document.getElementById('proy_tbody').innerHTML = '';
    document.getElementById('proy_vacio').classList.add('d-none');

    new bootstrap.Modal(document.getElementById('modalDetalleProy')).show();

    var params  = new URLSearchParams(window.location.search);
    var desde   = params.get('desde')      || '<?php echo $fecha_inicio; ?>';
    var hasta   = params.get('hasta')      || '<?php echo $fecha_fin; ?>';
    var oficina = params.get('id_oficina') || '<?php echo $id_oficina_consulta; ?>';

    fetch('ajax_detalle_proyecto.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id_proyecto=' + idProy + '&id_oficina=' + oficina + '&desde=' + desde + '&hasta=' + hasta
    })
    .then(r => r.json())
    .then(function(res) {
        document.getElementById('proy_loading').classList.add('d-none');
        document.getElementById('proy_contenido').classList.remove('d-none');

        if (!res.success || res.movimientos.length === 0) {
            document.getElementById('proy_vacio').classList.remove('d-none');
            document.getElementById('proy_total_ing').textContent = '$0.00';
            document.getElementById('proy_total_egr').textContent = '$0.00';
            document.getElementById('proy_saldo').textContent = 'Saldo: $0.00';
            return;
        }

        var html = '';
        res.movimientos.forEach(function(m) {
            var anulado = m.ESTADO === 'I';
            var badge = anulado ? '<span class="badge bg-danger">Anulado</span>' : '<span class="badge bg-success">Activo</span>';
            var style = anulado ? 'opacity:0.5;text-decoration:line-through;' : '';
            var ing = parseFloat(m.importe_recibido);
            var egr = parseFloat(m.importe_entregado);
            html += '<tr style="' + style + '">'
                + '<td class="text-center">' + m.id + '</td>'
                + '<td class="text-center">' + m.fecha + '</td>'
                + '<td>' + (m.intermediario || '-') + '</td>'
                + '<td>' + (m.concepto || '-') + '</td>'
                + '<td class="text-center">' + (m.doc_soporte || '-') + '</td>'
                + '<td class="text-center">' + (m.inf_fin || '-') + '</td>'
                + '<td class="text-end text-success">' + (ing > 0 ? '$' + ing.toFixed(2) : '-') + '</td>'
                + '<td class="text-end text-danger">'  + (egr > 0 ? '$' + egr.toFixed(2) : '-') + '</td>'
                + '<td class="text-center">' + badge + '</td>'
                + '<td class="text-center">' + (m.nombre_usuario || '-') + '</td>'
                + '</tr>';
        });

        document.getElementById('proy_tbody').innerHTML = html;
        document.getElementById('proy_total_ing').textContent = '$' + parseFloat(res.total_ing).toFixed(2);
        document.getElementById('proy_total_egr').textContent = '$' + parseFloat(res.total_egr).toFixed(2);
        var saldo = parseFloat(res.saldo);
        var el = document.getElementById('proy_saldo');
        el.textContent = 'Saldo: $' + saldo.toFixed(2);
        el.className = 'text-end ' + (saldo >= 0 ? 'text-success' : 'text-danger');
    })
    .catch(function() {
        document.getElementById('proy_loading').classList.add('d-none');
        document.getElementById('proy_contenido').classList.remove('d-none');
        document.getElementById('proy_tbody').innerHTML =
            '<tr><td colspan="10" class="text-center text-danger">Error al cargar datos.</td></tr>';
    });
}
</script>

<!-- Modal: Detalle Ingresos por Inf. Financiera -->
<div class="modal fade" id="modalDetalleIngreso" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-success text-white py-2">
        <h6 class="modal-title mb-0">
          <i class="bi bi-list-ul me-2"></i>
          <span id="ing_titulo">Detalle</span>
        </h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-2">
        <div id="ing_loading" class="text-center py-4">
          <div class="spinner-border text-success" role="status"></div>
          <p class="mt-2 small text-muted">Cargando movimientos...</p>
        </div>
        <div id="ing_contenido" class="d-none">
          <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover mb-0" style="font-size:0.78rem;">
              <thead class="table-success text-center">
                <tr>
                  <th>#</th>
                  <th>FECHA</th>
                  <th>BENEFICIARIO</th>
                  <th>DESCRIPCIÓN</th>
                  <th>DOC. SOPORTE</th>
                  <th class="text-end">MONTO</th>
                  <th>ESTADO</th>
                  <th>USUARIO</th>
                </tr>
              </thead>
              <tbody id="ing_tbody"></tbody>
              <tfoot class="table-light">
                <tr>
                  <td colspan="5" class="text-end fw-bold">TOTAL INGRESOS:</td>
                  <td class="text-end fw-bold text-success" id="ing_total"></td>
                  <td colspan="2"></td>
                </tr>
              </tfoot>
            </table>
          </div>
          <p id="ing_vacio" class="text-center text-muted small py-3 d-none">No hay movimientos para este período.</p>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function verDetalleIngreso(idReposicion, nombre) {
    document.getElementById('ing_titulo').textContent = nombre;
    document.getElementById('ing_loading').classList.remove('d-none');
    document.getElementById('ing_contenido').classList.add('d-none');
    document.getElementById('ing_tbody').innerHTML = '';
    document.getElementById('ing_vacio').classList.add('d-none');

    var modal = new bootstrap.Modal(document.getElementById('modalDetalleIngreso'));
    modal.show();

    var params  = new URLSearchParams(window.location.search);
    var desde   = params.get('desde')      || '<?php echo $fecha_inicio; ?>';
    var hasta   = params.get('hasta')      || '<?php echo $fecha_fin; ?>';
    var oficina = params.get('id_oficina') || '<?php echo $id_oficina_consulta; ?>';

    fetch('ajax_detalle_ingreso.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id_reposicion=' + idReposicion + '&id_oficina=' + oficina + '&desde=' + desde + '&hasta=' + hasta
    })
    .then(r => r.json())
    .then(function(res) {
        document.getElementById('ing_loading').classList.add('d-none');
        document.getElementById('ing_contenido').classList.remove('d-none');

        if (!res.success || res.movimientos.length === 0) {
            document.getElementById('ing_vacio').classList.remove('d-none');
            document.getElementById('ing_total').textContent = '$0.00';
            return;
        }

        var html = '';
        res.movimientos.forEach(function(m) {
            var anulado = m.ESTADO === 'I';
            var badge = anulado
                ? '<span class="badge bg-danger">Anulado</span>'
                : '<span class="badge bg-success">Activo</span>';
            var rowStyle = anulado ? 'opacity:0.5;text-decoration:line-through;' : '';
            html += '<tr style="' + rowStyle + '">'
                + '<td class="text-center">' + m.id + '</td>'
                + '<td class="text-center">' + m.fecha + '</td>'
                + '<td>' + (m.intermediario || '-') + '</td>'
                + '<td>' + (m.concepto || '-') + '</td>'
                + '<td class="text-center">' + (m.doc_soporte || '-') + '</td>'
                + '<td class="text-end fw-bold text-success">$' + parseFloat(m.importe_recibido).toFixed(2) + '</td>'
                + '<td class="text-center">' + badge + '</td>'
                + '<td class="text-center">' + (m.nombre_usuario || '-') + '</td>'
                + '</tr>';
        });

        document.getElementById('ing_tbody').innerHTML = html;
        document.getElementById('ing_total').textContent = '$' + parseFloat(res.total).toFixed(2);
    })
    .catch(function() {
        document.getElementById('ing_loading').classList.add('d-none');
        document.getElementById('ing_contenido').classList.remove('d-none');
        document.getElementById('ing_tbody').innerHTML =
            '<tr><td colspan="8" class="text-center text-danger">Error al cargar datos.</td></tr>';
    });
}
</script>

<!-- Modal: Detalle Inf. Financiera -->
<div class="modal fade" id="modalDetalleInfFin" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white py-2">
        <h6 class="modal-title mb-0">
          <i class="bi bi-list-ul me-2"></i>
          <span id="dif_titulo">Detalle</span>
        </h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-2">
        <div id="dif_loading" class="text-center py-4">
          <div class="spinner-border text-danger" role="status"></div>
          <p class="mt-2 small text-muted">Cargando movimientos...</p>
        </div>
        <div id="dif_contenido" class="d-none">
          <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover mb-0" style="font-size:0.78rem;">
              <thead class="table-danger text-center">
                <tr>
                  <th>#</th>
                  <th>FECHA</th>
                  <th>BENEFICIARIO</th>
                  <th>DESCRIPCIÓN</th>
                  <th>DOC. SOPORTE</th>
                  <th class="text-end">MONTO</th>
                  <th>ESTADO</th>
                  <th>USUARIO</th>
                </tr>
              </thead>
              <tbody id="dif_tbody"></tbody>
              <tfoot class="table-light">
                <tr>
                  <td colspan="5" class="text-end fw-bold">TOTAL ACTIVOS:</td>
                  <td class="text-end fw-bold text-danger" id="dif_total"></td>
                  <td colspan="2"></td>
                </tr>
              </tfoot>
            </table>
          </div>
          <p id="dif_vacio" class="text-center text-muted small py-3 d-none">No hay movimientos para este período.</p>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function verDetalleInfFin(idReposicion, nombre) {
    document.getElementById('dif_titulo').textContent = nombre;
    document.getElementById('dif_loading').classList.remove('d-none');
    document.getElementById('dif_contenido').classList.add('d-none');
    document.getElementById('dif_tbody').innerHTML = '';
    document.getElementById('dif_vacio').classList.add('d-none');

    var modal = new bootstrap.Modal(document.getElementById('modalDetalleInfFin'));
    modal.show();

    // Tomar los filtros actuales del dashboard desde la URL
    var params = new URLSearchParams(window.location.search);
    var desde  = params.get('desde')      || '<?php echo $fecha_inicio; ?>';
    var hasta  = params.get('hasta')      || '<?php echo $fecha_fin; ?>';
    var oficina= params.get('id_oficina') || '<?php echo $id_oficina_consulta; ?>';

    fetch('ajax_detalle_inf_fin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id_reposicion=' + idReposicion + '&id_oficina=' + oficina + '&desde=' + desde + '&hasta=' + hasta
    })
    .then(r => r.json())
    .then(function(res) {
        document.getElementById('dif_loading').classList.add('d-none');
        document.getElementById('dif_contenido').classList.remove('d-none');

        if (!res.success || res.movimientos.length === 0) {
            document.getElementById('dif_vacio').classList.remove('d-none');
            document.getElementById('dif_total').textContent = '$0.00';
            return;
        }

        var html = '';
        res.movimientos.forEach(function(m) {
            var anulado = m.ESTADO === 'I';
            var badge = anulado
                ? '<span class="badge bg-danger">Anulado</span>'
                : '<span class="badge bg-success">Activo</span>';
            var rowStyle = anulado ? 'opacity:0.5;text-decoration:line-through;' : '';
            html += '<tr style="' + rowStyle + '">'
                + '<td class="text-center">' + m.id + '</td>'
                + '<td class="text-center">' + m.fecha + '</td>'
                + '<td>' + (m.intermediario || '-') + '</td>'
                + '<td>' + (m.concepto || '-') + '</td>'
                + '<td class="text-center">' + (m.doc_soporte || '-') + '</td>'
                + '<td class="text-end fw-bold text-danger">$' + parseFloat(m.importe_entregado).toFixed(2) + '</td>'
                + '<td class="text-center">' + badge + '</td>'
                + '<td class="text-center">' + (m.nombre_usuario || '-') + '</td>'
                + '</tr>';
        });

        document.getElementById('dif_tbody').innerHTML = html;
        document.getElementById('dif_total').textContent = '$' + parseFloat(res.total).toFixed(2);
    })
    .catch(function() {
        document.getElementById('dif_loading').classList.add('d-none');
        document.getElementById('dif_contenido').classList.remove('d-none');
        document.getElementById('dif_tbody').innerHTML =
            '<tr><td colspan="8" class="text-center text-danger">Error al cargar datos.</td></tr>';
    });
}
</script>

</body>
</html>
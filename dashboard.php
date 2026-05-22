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

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card card-dash shadow-sm bg-primary text-white h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="icon-box bg-white text-primary me-3"><i class="bi bi-wallet2 fs-4"></i></div>
                        <div>
                            <p class="mb-0 opacity-75 small">SALDO ACTUAL</p>
                            <h3 class="mb-0 fw-bold">$<?php echo number_format($saldo, 2); ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-dash shadow-sm h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="icon-box bg-success-subtle text-success me-3"><i class="bi bi-graph-up-arrow fs-4"></i></div>
                        <div>
                            <p class="mb-0 text-muted small">TOTAL INGRESOS</p>
                            <h3 class="mb-0 fw-bold text-success">$<?php echo number_format($t_ingresos, 2); ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-dash shadow-sm h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="icon-box bg-danger-subtle text-danger me-3"><i class="bi bi-graph-down-arrow fs-4"></i></div>
                        <div>
                            <p class="mb-0 text-muted small">TOTAL EGRESOS</p>
                            <h3 class="mb-0 fw-bold text-danger">$<?php echo number_format($t_egresos, 2); ?></h3>
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
                    <div class="card-header bg-white fw-bold py-3">Flujo de Efectivo del Periodo</div>
                    <div class="card-body">
                        <canvas id="mainChart" style="max-height: 300px;"></canvas>
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
                    r.REPOSICION AS nombre_gasto, 
                    SUM(m.importe_entregado) AS total_gasto
                FROM movimientos m
                INNER JOIN CAT_REPOSICION r ON m.inf_fin = r.ID_REPOSICION
                WHERE m.id_oficina = ? AND m.importe_entregado > 0
                GROUP BY r.REPOSICION
                ORDER BY total_gasto DESC";

$stmt_res = $conn->prepare($sql_resumen);
$stmt_res->bind_param("i", $id_oficina_actual);
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
                        <li class="list-group-item d-flex justify-content-between align-items-center small">
                            <?php echo htmlspecialchars($gasto['nombre_gasto']); ?>
                            <span class="fw-bold text-danger">
                                $<?php echo number_format($gasto['total_gasto'], 2); ?>
                            </span>
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
</div>
        </div>
        
    </div>

    <script>
        const ctx = document.getElementById('mainChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Ingresos (+)', 'Egresos (-)'],
                datasets: [{
                    label: 'Monto en USD',
                    data: [<?php echo $t_ingresos; ?>, <?php echo $t_egresos; ?>],
                    backgroundColor: ['#0d6efd', '#dc3545'],
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
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

</body>
</html>
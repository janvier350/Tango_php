<?php
require_once 'config.php';
require_once 'auth.php';
verificar_auth();

date_default_timezone_set('America/Guayaquil');

$id_oficina_consulta = isset($_GET['id_oficina']) ? intval($_GET['id_oficina']) : $_SESSION['oficina_ID'];
$fecha_inicio        = $_GET['desde'] ?? date('Y-m-01');
$fecha_fin           = $_GET['hasta'] ?? date('Y-m-t');
$user_rol            = $_SESSION['user_rol'];

// Nombre de la oficina
$stmt_on = $conn->prepare("SELECT OFICINA FROM CTR_OFICINA WHERE ID_OFICINA = ?");
$stmt_on->bind_param("i", $id_oficina_consulta);
$stmt_on->execute();
$nombre_oficina = $stmt_on->get_result()->fetch_assoc()['OFICINA'] ?? '—';

// Lista de oficinas (para selector admin)
$oficinas = $conn->query("SELECT ID_OFICINA, OFICINA FROM CTR_OFICINA ORDER BY OFICINA")->fetch_all(MYSQLI_ASSOC);

// ── Tab 1: Por Proyecto ──────────────────────────────────────────────
$sql_proy = "
    SELECT m.id, m.fecha, m.concepto AS descripcion,
           m.intermediario AS beneficiario, m.INTERMEDIARIO2 AS intermediario,
           m.doc_soporte, m.importe_recibido, m.importe_entregado,
           m.banco, m.cheque_num,
           COALESCE(p.PROYECTO, '(Sin Proyecto)') AS nombre_proyecto,
           COALESCE(r.REPOSICION, '(Sin Clasificar)') AS inf_fin
    FROM movimientos m
    LEFT JOIN PROYECTOS p      ON m.ID_PROYECTO = p.ID_PROYECTO
    LEFT JOIN CAT_REPOSICION r ON m.inf_fin = r.ID_REPOSICION
    WHERE m.ID_OFICINA = ? AND m.ESTADO = 'A' AND m.fecha BETWEEN ? AND ?
    ORDER BY nombre_proyecto ASC, m.fecha ASC, m.id ASC";

$stmt1 = $conn->prepare($sql_proy);
$stmt1->bind_param("iss", $id_oficina_consulta, $fecha_inicio, $fecha_fin);
$stmt1->execute();
$res1 = $stmt1->get_result();

$grupos_proy = [];
while ($row = $res1->fetch_assoc()) {
    $grupos_proy[$row['nombre_proyecto']][] = $row;
}

// ── Tab 2: Por INF.FIN ───────────────────────────────────────────────
$sql_inf = "
    SELECT m.id, m.fecha, m.concepto AS descripcion,
           m.intermediario AS beneficiario, m.INTERMEDIARIO2 AS intermediario,
           m.doc_soporte, m.importe_recibido, m.importe_entregado,
           COALESCE(p.PROYECTO, '(Sin Proyecto)') AS nombre_proyecto,
           COALESCE(r.REPOSICION, '(Sin Clasificar)') AS inf_fin
    FROM movimientos m
    LEFT JOIN PROYECTOS p      ON m.ID_PROYECTO = p.ID_PROYECTO
    LEFT JOIN CAT_REPOSICION r ON m.inf_fin = r.ID_REPOSICION
    WHERE m.ID_OFICINA = ? AND m.ESTADO = 'A' AND m.fecha BETWEEN ? AND ?
    ORDER BY inf_fin ASC, m.fecha ASC, m.id ASC";

$stmt2 = $conn->prepare($sql_inf);
$stmt2->bind_param("iss", $id_oficina_consulta, $fecha_inicio, $fecha_fin);
$stmt2->execute();
$res2 = $stmt2->get_result();

$grupos_inf = [];
while ($row = $res2->fetch_assoc()) {
    $grupos_inf[$row['inf_fin']][] = $row;
}

// Totales globales
$tot_ing_global = 0;
$tot_egr_global = 0;
foreach ($grupos_proy as $rows) {
    foreach ($rows as $r) {
        $tot_ing_global += $r['importe_recibido'];
        $tot_egr_global += $r['importe_entregado'];
    }
}
$tot_sal_global = $tot_ing_global - $tot_egr_global;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informe de Movimientos | CAJA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background:#f4f6f8; font-size: 0.85rem; }
        .group-header { background:#2c3e50; color:#fff; font-weight:600; }
        .group-header td { padding: 5px 10px !important; }
        .subtotal-row { background:#eaf4fb; font-weight:600; }
        .separator-row td { padding: 4px 0 !important; background:#f4f6f8; }
        .tfoot-global { background:#2c3e50; color:#fff; font-weight:700; font-size:.9rem; }
        .saldo-pos { color:#2ecc71; }
        .saldo-neg { color:#e74c3c; }
        th { white-space: nowrap; }

        /* ── Print ── */
        @media print {
            body { background:#fff; font-size:0.78rem; }
            .no-print { display:none !important; }
            .print-only { display:block !important; }
            .tab-pane { display:block !important; opacity:1 !important; }
            .nav-tabs { display:none !important; }
            .tab-content { border:none !important; }
            #tabProy  { page-break-after: always; }
            .sticky-top { position: static !important; }
            .container-fluid { padding: 0 !important; }
        }
        .print-only { display:none; }
        .print-section-title {
            font-size:1rem; font-weight:700; color:#2c3e50;
            border-bottom:2px solid #2c3e50; padding-bottom:4px; margin:12px 0 6px;
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container-fluid py-3 px-4">

    <!-- ── Encabezado ── -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0 fw-bold"><i class="bi bi-file-earmark-bar-graph me-2"></i>Informe de Movimientos</h4>
            <small class="text-muted">Oficina: <strong><?php echo htmlspecialchars($nombre_oficina); ?></strong>
                &nbsp;|&nbsp; Período: <strong><?php echo date('d/m/Y', strtotime($fecha_inicio)); ?></strong>
                al <strong><?php echo date('d/m/Y', strtotime($fecha_fin)); ?></strong>
            </small>
        </div>
        <button onclick="window.print()" class="btn btn-outline-dark btn-sm no-print">
            <i class="bi bi-printer me-1"></i> Imprimir / PDF
        </button>
    </div>

    <!-- ── Filtros ── -->
    <form method="GET" class="row g-2 mb-3 align-items-end no-print p-3 bg-white rounded shadow-sm">
        <div class="col-auto">
            <label class="form-label small mb-1 fw-semibold">Oficina</label>
            <select name="id_oficina" class="form-select form-select-sm">
                <?php foreach ($oficinas as $of): ?>
                <option value="<?php echo $of['ID_OFICINA']; ?>"
                    <?php echo $of['ID_OFICINA'] == $id_oficina_consulta ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($of['OFICINA']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <label class="form-label small mb-1 fw-semibold">Desde</label>
            <input type="date" name="desde" class="form-control form-control-sm"
                   value="<?php echo $fecha_inicio; ?>">
        </div>
        <div class="col-auto">
            <label class="form-label small mb-1 fw-semibold">Hasta</label>
            <input type="date" name="hasta" class="form-control form-control-sm"
                   value="<?php echo $fecha_fin; ?>">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-search me-1"></i> Consultar
            </button>
            <a href="informe.php" class="btn btn-outline-secondary btn-sm ms-1">Limpiar</a>
        </div>
        <!-- Totales rápidos -->
        <div class="col ms-auto text-end">
            <span class="me-3 small">
                Ingresos: <strong class="text-success">$<?php echo number_format($tot_ing_global, 2); ?></strong>
            </span>
            <span class="me-3 small">
                Egresos: <strong class="text-danger">$<?php echo number_format($tot_egr_global, 2); ?></strong>
            </span>
            <span class="small">
                Saldo: <strong class="<?php echo $tot_sal_global >= 0 ? 'text-success' : 'text-danger'; ?>">
                    $<?php echo number_format($tot_sal_global, 2); ?>
                </strong>
            </span>
        </div>
    </form>

    <!-- ── Cabecera para impresión ── -->
    <div class="print-only">
        <div class="print-section-title">
            INFORME DE MOVIMIENTOS — <?php echo htmlspecialchars($nombre_oficina); ?>
            &nbsp;|&nbsp; <?php echo date('d/m/Y', strtotime($fecha_inicio)); ?>
            al <?php echo date('d/m/Y', strtotime($fecha_fin)); ?>
        </div>
        <p class="small mb-1">
            Ingresos: <strong>$<?php echo number_format($tot_ing_global, 2); ?></strong>
            &nbsp;|&nbsp; Egresos: <strong>$<?php echo number_format($tot_egr_global, 2); ?></strong>
            &nbsp;|&nbsp; Saldo: <strong>$<?php echo number_format($tot_sal_global, 2); ?></strong>
        </p>
    </div>

    <!-- ── Tabs ── -->
    <ul class="nav nav-tabs no-print" id="reporteTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active fw-semibold" data-bs-toggle="tab" data-bs-target="#tabProy">
                <i class="bi bi-kanban me-1"></i> Por Proyecto
                <span class="badge bg-secondary ms-1"><?php echo count($grupos_proy); ?></span>
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link fw-semibold" data-bs-toggle="tab" data-bs-target="#tabInfFin">
                <i class="bi bi-tags me-1"></i> Por INF. FIN.
                <span class="badge bg-secondary ms-1"><?php echo count($grupos_inf); ?></span>
            </button>
        </li>
    </ul>

    <div class="tab-content bg-white shadow-sm border border-top-0 rounded-bottom">

        <!-- ════════════════════════════════════════
             TAB 1: POR PROYECTO
        ════════════════════════════════════════ -->
        <div class="tab-pane fade show active" id="tabProy" role="tabpanel">
            <div class="print-section-title print-only">VISTA POR PROYECTO</div>
            <?php if (empty($grupos_proy)): ?>
                <p class="text-center text-muted py-4">Sin movimientos activos en el período seleccionado.</p>
            <?php else: ?>
            <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-dark sticky-top">
                    <tr>
                        <th class="ps-2">#</th>
                        <th>Fecha</th>
                        <th>Beneficiario</th>
                        <th>Intermediario</th>
                        <th>Descripción</th>
                        <th>INF.FIN</th>
                        <th>Banco</th>
                        <th>CH#</th>
                        <th class="text-end" style="color:#6ee097;">Ingreso</th>
                        <th class="text-end" style="color:#f28b82;">Egreso</th>
                        <th class="text-end">Saldo Acum.</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $gran_ing = 0; $gran_egr = 0; $saldo_acum_global = 0;
                foreach ($grupos_proy as $nombre_proy => $filas):
                    $sub_ing = 0; $sub_egr = 0; $saldo_acum = 0;
                ?>
                    <!-- Cabecera de grupo -->
                    <tr class="group-header">
                        <td colspan="11">
                            <i class="bi bi-folder2-open me-1"></i>
                            PROYECTO: <?php echo htmlspecialchars($nombre_proy); ?>
                            <span class="ms-2 small fw-normal opacity-75">(<?php echo count($filas); ?> movimientos)</span>
                        </td>
                    </tr>
                    <!-- Filas del grupo -->
                    <?php foreach ($filas as $f):
                        $saldo_acum        += ($f['importe_recibido'] - $f['importe_entregado']);
                        $saldo_acum_global += ($f['importe_recibido'] - $f['importe_entregado']);
                        $sub_ing           += $f['importe_recibido'];
                        $sub_egr           += $f['importe_entregado'];
                        $gran_ing          += $f['importe_recibido'];
                        $gran_egr          += $f['importe_entregado'];
                    ?>
                    <tr>
                        <td class="ps-3 text-muted small"><?php echo $f['id']; ?></td>
                        <td class="small"><?php echo date('d/m/Y', strtotime($f['fecha'])); ?></td>
                        <td class="small"><?php echo htmlspecialchars(strtoupper($f['beneficiario'] ?? '')); ?></td>
                        <td class="small text-muted"><?php echo htmlspecialchars(strtoupper($f['intermediario'] ?? '')); ?></td>
                        <td class="small"><?php echo htmlspecialchars($f['descripcion'] ?? ''); ?></td>
                        <td class="small">
                            <span class="badge bg-secondary"><?php echo htmlspecialchars($f['inf_fin']); ?></span>
                        </td>
                        <td class="small text-muted"><?php echo htmlspecialchars($f['banco'] ?? ''); ?></td>
                        <td class="small text-muted"><?php echo htmlspecialchars($f['cheque_num'] ?? ''); ?></td>
                        <td class="text-end small <?php echo $f['importe_recibido'] > 0 ? 'text-success fw-bold' : 'text-muted'; ?>">
                            <?php echo $f['importe_recibido'] > 0 ? '$'.number_format($f['importe_recibido'], 2) : '—'; ?>
                        </td>
                        <td class="text-end small <?php echo $f['importe_entregado'] > 0 ? 'text-danger fw-bold' : 'text-muted'; ?>">
                            <?php echo $f['importe_entregado'] > 0 ? '$'.number_format($f['importe_entregado'], 2) : '—'; ?>
                        </td>
                        <td class="text-end small fw-semibold <?php echo $saldo_acum >= 0 ? 'text-success' : 'text-danger'; ?>">
                            $<?php echo number_format($saldo_acum, 2); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <!-- Subtotal del grupo -->
                    <tr class="subtotal-row">
                        <td colspan="8" class="text-end pe-2 small">
                            Subtotal <em><?php echo htmlspecialchars($nombre_proy); ?></em>:
                        </td>
                        <td class="text-end text-success fw-bold small">$<?php echo number_format($sub_ing, 2); ?></td>
                        <td class="text-end text-danger fw-bold small">$<?php echo number_format($sub_egr, 2); ?></td>
                        <td class="text-end fw-bold small <?php echo ($sub_ing-$sub_egr) >= 0 ? 'text-success' : 'text-danger'; ?>">
                            $<?php echo number_format($sub_ing - $sub_egr, 2); ?>
                        </td>
                    </tr>
                    <tr class="separator-row"><td colspan="11"></td></tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="tfoot-global">
                    <tr>
                        <td colspan="8" class="text-end ps-2">TOTAL GENERAL:</td>
                        <td class="text-end saldo-pos">$<?php echo number_format($gran_ing, 2); ?></td>
                        <td class="text-end saldo-neg">$<?php echo number_format($gran_egr, 2); ?></td>
                        <td class="text-end <?php echo ($gran_ing-$gran_egr) >= 0 ? 'saldo-pos' : 'saldo-neg'; ?>">
                            $<?php echo number_format($gran_ing - $gran_egr, 2); ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- ════════════════════════════════════════
             TAB 2: POR INF.FIN
        ════════════════════════════════════════ -->
        <div class="tab-pane fade" id="tabInfFin" role="tabpanel">
            <div class="print-section-title print-only" style="margin-top:20px;">VISTA POR INF. FINANCIERA</div>
            <?php if (empty($grupos_inf)): ?>
                <p class="text-center text-muted py-4">Sin movimientos activos en el período seleccionado.</p>
            <?php else: ?>
            <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-dark sticky-top">
                    <tr>
                        <th class="ps-2">#</th>
                        <th>Fecha</th>
                        <th>Proyecto</th>
                        <th>Beneficiario</th>
                        <th>Intermediario</th>
                        <th>Descripción</th>
                        <th>Documento</th>
                        <th class="text-end" style="color:#6ee097;">Ingreso</th>
                        <th class="text-end" style="color:#f28b82;">Egreso</th>
                        <th class="text-end">Saldo Acum.</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $gran_ing2 = 0; $gran_egr2 = 0;
                foreach ($grupos_inf as $nombre_inf => $filas2):
                    $sub_ing2 = 0; $sub_egr2 = 0; $saldo_acum2 = 0;
                ?>
                    <!-- Cabecera de grupo -->
                    <tr class="group-header">
                        <td colspan="10">
                            <i class="bi bi-tag-fill me-1"></i>
                            INF.FIN: <?php echo htmlspecialchars($nombre_inf); ?>
                            <span class="ms-2 small fw-normal opacity-75">(<?php echo count($filas2); ?> movimientos)</span>
                        </td>
                    </tr>
                    <!-- Filas del grupo -->
                    <?php foreach ($filas2 as $f2):
                        $saldo_acum2 += ($f2['importe_recibido'] - $f2['importe_entregado']);
                        $sub_ing2    += $f2['importe_recibido'];
                        $sub_egr2    += $f2['importe_entregado'];
                        $gran_ing2   += $f2['importe_recibido'];
                        $gran_egr2   += $f2['importe_entregado'];
                    ?>
                    <tr>
                        <td class="ps-3 text-muted small"><?php echo $f2['id']; ?></td>
                        <td class="small"><?php echo date('d/m/Y', strtotime($f2['fecha'])); ?></td>
                        <td class="small">
                            <span class="badge bg-dark"><?php echo htmlspecialchars($f2['nombre_proyecto']); ?></span>
                        </td>
                        <td class="small"><?php echo htmlspecialchars(strtoupper($f2['beneficiario'] ?? '')); ?></td>
                        <td class="small text-muted"><?php echo htmlspecialchars(strtoupper($f2['intermediario'] ?? '')); ?></td>
                        <td class="small"><?php echo htmlspecialchars($f2['descripcion'] ?? ''); ?></td>
                        <td class="small text-muted"><?php echo htmlspecialchars($f2['doc_soporte'] ?? ''); ?></td>
                        <td class="text-end small <?php echo $f2['importe_recibido'] > 0 ? 'text-success fw-bold' : 'text-muted'; ?>">
                            <?php echo $f2['importe_recibido'] > 0 ? '$'.number_format($f2['importe_recibido'], 2) : '—'; ?>
                        </td>
                        <td class="text-end small <?php echo $f2['importe_entregado'] > 0 ? 'text-danger fw-bold' : 'text-muted'; ?>">
                            <?php echo $f2['importe_entregado'] > 0 ? '$'.number_format($f2['importe_entregado'], 2) : '—'; ?>
                        </td>
                        <td class="text-end small fw-semibold <?php echo $saldo_acum2 >= 0 ? 'text-success' : 'text-danger'; ?>">
                            $<?php echo number_format($saldo_acum2, 2); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <!-- Subtotal del grupo -->
                    <tr class="subtotal-row">
                        <td colspan="7" class="text-end pe-2 small">
                            Subtotal <em><?php echo htmlspecialchars($nombre_inf); ?></em>:
                        </td>
                        <td class="text-end text-success fw-bold small">$<?php echo number_format($sub_ing2, 2); ?></td>
                        <td class="text-end text-danger fw-bold small">$<?php echo number_format($sub_egr2, 2); ?></td>
                        <td class="text-end fw-bold small <?php echo ($sub_ing2-$sub_egr2) >= 0 ? 'text-success' : 'text-danger'; ?>">
                            $<?php echo number_format($sub_ing2 - $sub_egr2, 2); ?>
                        </td>
                    </tr>
                    <tr class="separator-row"><td colspan="10"></td></tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="tfoot-global">
                    <tr>
                        <td colspan="7" class="text-end ps-2">TOTAL GENERAL:</td>
                        <td class="text-end saldo-pos">$<?php echo number_format($gran_ing2, 2); ?></td>
                        <td class="text-end saldo-neg">$<?php echo number_format($gran_egr2, 2); ?></td>
                        <td class="text-end <?php echo ($gran_ing2-$gran_egr2) >= 0 ? 'saldo-pos' : 'saldo-neg'; ?>">
                            $<?php echo number_format($gran_ing2 - $gran_egr2, 2); ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
            </div>
            <?php endif; ?>
        </div>

    </div><!-- /tab-content -->
</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Al imprimir, forzar que ambas pestañas sean visibles
window.addEventListener('beforeprint', function() {
    document.querySelectorAll('.tab-pane').forEach(function(p) {
        p.style.display = 'block';
        p.style.opacity = '1';
    });
});
window.addEventListener('afterprint', function() {
    document.querySelectorAll('.tab-pane').forEach(function(p) {
        p.style.display = '';
        p.style.opacity = '';
    });
    // Restaurar tab activa
    var active = document.querySelector('.tab-pane.show.active');
    if (active) { active.style.display = 'block'; }
});
</script>
</body>
</html>

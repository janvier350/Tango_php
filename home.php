<?php
ob_start();
session_start();
require_once("class/funciones.php");
require_once("class/conexionBD.php");
$conexion = conectarse();

if (!isset($_SESSION["rol"])) {
    header("Location: break.php");
    exit();
}
if (isset($_SESSION['expire']) && time() > $_SESSION['expire']) {
    session_destroy();
    header("Location: expirada.php");
    exit();
}

$hoy     = date('Y-m-d');
$mes     = date('m');
$anio    = date('Y');
$rol     = $_SESSION['rol']     ?? '';
$nombres = $_SESSION['nombres'] ?? '';

// ── ESTADÍSTICAS ─────────────────────────────────────────────────────

// Citas de hoy
$r = $conexion->query("SELECT COUNT(*) c FROM AG_CITA WHERE FECHA_CITA='$hoy' AND ESTADO='A'");
$citasHoy = $r->fetch_assoc()['c'] ?? 0;

// Pendientes (total activas futuras o de hoy)
$r = $conexion->query("SELECT COUNT(*) c FROM AG_CITA WHERE ESTADO_CITA='Pendiente' AND ESTADO='A' AND FECHA_CITA >= '$hoy'");
$citasPendientes = $r->fetch_assoc()['c'] ?? 0;

// Confirmadas pendientes de atender
$r = $conexion->query("SELECT COUNT(*) c FROM AG_CITA WHERE ESTADO_CITA='Confirmada' AND ESTADO='A' AND FECHA_CITA >= '$hoy'");
$citasConfirmadas = $r->fetch_assoc()['c'] ?? 0;

// Atendidas este mes
$r = $conexion->query("SELECT COUNT(*) c FROM AG_HISTORIAL WHERE MONTH(FECHA_REGISTRO)=$mes AND YEAR(FECHA_REGISTRO)=$anio");
$citasAtendidas = $r->fetch_assoc()['c'] ?? 0;

// Canceladas este mes
$r = $conexion->query("SELECT COUNT(*) c FROM AG_CITA WHERE ESTADO_CITA IN ('Cancelada','Cancelado') AND ESTADO='A' AND MONTH(FECHA_CITA)=$mes AND YEAR(FECHA_CITA)=$anio");
$citasCanceladas = $r->fetch_assoc()['c'] ?? 0;

// Total pacientes activos
$r = $conexion->query("SELECT COUNT(*) c FROM AG_PACIENTE WHERE ESTADO='A'");
$totalPacientes = $r->fetch_assoc()['c'] ?? 0;

// ── CITAS DE HOY (detalle) ────────────────────────────────────────────
$sqlHoy = "SELECT A.IDCITA, A.HORA_INICIO, A.HORA_FIN, A.ESTADO_CITA,
                  CONCAT(P.NOMBRES,' ',P.APELLIDOS) AS PACIENTE,
                  CONCAT(D.NOMBRES,' ',D.APELLIDOS) AS DOCTOR,
                  TC.NOMBRES AS TIPO_CONSULTA
           FROM AG_CITA A
           INNER JOIN AG_PACIENTE P     ON A.IDPACIENTE     = P.IDPACIENTE
           LEFT  JOIN ADM_DOCTOR D      ON A.IDDOCTOR        = D.IDDOCTOR
           LEFT  JOIN AG_TIPOCONSULTA TC ON A.IDTIPOCONSULTA = TC.IDTIPOCONSULTA
           WHERE A.FECHA_CITA = '$hoy' AND A.ESTADO = 'A'
           ORDER BY A.HORA_INICIO";
$resHoy = $conexion->query($sqlHoy);

// ── PRÓXIMAS CITAS (próximos 7 días, excl. hoy) ──────────────────────
$proximaFecha = date('Y-m-d', strtotime('+1 day'));
$limiteFecha  = date('Y-m-d', strtotime('+7 days'));
$sqlProx = "SELECT A.FECHA_CITA, A.HORA_INICIO,
                   CONCAT(P.NOMBRES,' ',P.APELLIDOS) AS PACIENTE,
                   TC.NOMBRES AS TIPO_CONSULTA,
                   A.ESTADO_CITA
            FROM AG_CITA A
            INNER JOIN AG_PACIENTE P      ON A.IDPACIENTE     = P.IDPACIENTE
            LEFT  JOIN AG_TIPOCONSULTA TC  ON A.IDTIPOCONSULTA = TC.IDTIPOCONSULTA
            WHERE A.FECHA_CITA BETWEEN '$proximaFecha' AND '$limiteFecha'
              AND A.ESTADO = 'A'
              AND A.ESTADO_CITA NOT IN ('Cancelada','Cancelado')
            ORDER BY A.FECHA_CITA, A.HORA_INICIO
            LIMIT 8";
$resProx = $conexion->query($sqlProx);

// ── ÚLTIMAS ATENCIONES ────────────────────────────────────────────────
$sqlUlt = "SELECT H.FECHA_REGISTRO, H.IMC,
                  CONCAT(P.NOMBRES,' ',P.APELLIDOS) AS PACIENTE,
                  CONCAT(D.NOMBRES,' ',D.APELLIDOS) AS DOCTOR
           FROM AG_HISTORIAL H
           INNER JOIN AG_CITA C       ON H.IDCITA      = C.IDCITA
           INNER JOIN AG_PACIENTE P   ON C.IDPACIENTE  = P.IDPACIENTE
           LEFT  JOIN ADM_DOCTOR D    ON C.IDDOCTOR     = D.IDDOCTOR
           ORDER BY H.FECHA_REGISTRO DESC
           LIMIT 5";
$resUlt = $conexion->query($sqlUlt);

// ── CITAS POR DÍA (últimos 7 días, para mini-gráfico) ────────────────
$semana = [];
for ($i = 6; $i >= 0; $i--) {
    $dia = date('Y-m-d', strtotime("-$i days"));
    $r   = $conexion->query("SELECT COUNT(*) c FROM AG_CITA WHERE FECHA_CITA='$dia' AND ESTADO='A'");
    $semana[] = ['fecha' => date('d/m', strtotime($dia)), 'total' => (int)$r->fetch_assoc()['c']];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard — HealthSchedule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="./main.css" rel="stylesheet">
    <script src="js/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        .stat-card          { border-radius: 10px; border: none; transition: transform .2s; }
        .stat-card:hover    { transform: translateY(-3px); }
        .stat-card .icon    { font-size: 1.6rem; opacity: .85; }
        .stat-card .number  { font-size: 1.6rem; font-weight: 700; line-height: 1; }
        .stat-card .label   { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; opacity: .85; }
        .stat-card .link    { font-size: .72rem; opacity: .8; }
        .badge-estado-Pendiente  { background: #ffc107; color: #000; }
        .badge-estado-Confirmada { background: #28a745; color: #fff; }
        .badge-estado-A          { background: #6f42c1; color: #fff; }
        .badge-estado-Cancelada,
        .badge-estado-Cancelado  { background: #dc3545; color: #fff; }
        .section-title { font-size: .7rem; text-transform: uppercase; letter-spacing: .08em;
                         color: #6c757d; font-weight: 600; margin-bottom: .6rem; }
    </style>
</head>
<body>
<div class="app-container app-theme-white body-tabs-shadow fixed-sidebar fixed-header">

    <!-- HEADER -->
    <div class="app-header header-shadow">
        <div class="app-header__logo">
            <div class="logo-src"></div>
            <div class="header__pane ml-auto">
                <button type="button" class="hamburger close-sidebar-btn hamburger--elastic" data-class="closed-sidebar">
                    <span class="hamburger-box"><span class="hamburger-inner"></span></span>
                </button>
            </div>
        </div>
        <div class="app-header__mobile-menu">
            <button type="button" class="hamburger hamburger--elastic mobile-toggle-nav">
                <span class="hamburger-box"><span class="hamburger-inner"></span></span>
            </button>
        </div>
        <div class="app-header__menu">
            <button type="button" class="btn-icon btn-icon-only btn btn-primary btn-sm mobile-toggle-header-nav">
                <span class="btn-icon-wrapper"><i class="fa fa-ellipsis-v fa-w-6"></i></span>
            </button>
        </div>
        <div class="app-header__content">
            <div class="app-header-left"></div>
            <div class="app-header-right">
                <div class="header-btn-lg pr-0">
                    <div class="widget-content p-0">
                        <div class="widget-content-wrapper">
                            <div class="widget-content-left ml-3 header-user-info">
                                <div class="widget-heading">
                                    <?php echo htmlspecialchars($nombres); ?>
                                </div>
                                <div class="widget-subheading">
                                    <?php echo htmlspecialchars($rol); ?> — <?php echo date('d/m/Y'); ?>
                                </div>
                            </div>
                            <div class="widget-content-left ms-3">
                                <div class="btn-group">
                                    <a data-toggle="dropdown" class="p-0 btn" href="#">
                                        <i class="fa fa-angle-down ml-2 opacity-8"></i>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-right">
                                        <a href="salir.php" class="dropdown-item">Cerrar Sesión</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="app-main">
        <!-- SIDEBAR -->
        <div class="app-sidebar sidebar-shadow">
            <?php include("./menu/menu_adm.php"); ?>
        </div>

        <div class="app-main__outer">
            <div class="app-main__inner">

                <!-- TÍTULO -->
                <div class="app-page-title mb-3">
                    <div class="page-title-wrapper">
                        <div class="page-title-heading">
                            <div class="page-title-icon">
                                <i class="pe-7s-rocket icon-gradient bg-warm-flame"></i>
                            </div>
                            <div>
                                Bienvenido, <?php echo htmlspecialchars($nombres); ?>
                                <div class="page-title-subheading">
                                    <?php echo date('l, d \d\e F \d\e Y'); ?> &nbsp;·&nbsp;
                                    Resumen general del sistema
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ══ TARJETAS DE ESTADÍSTICAS ══════════════════════ -->
                <div class="row g-3 mb-4">

                    <div class="col-6 col-md-3">
                        <div class="card stat-card text-white shadow-sm" style="background:linear-gradient(135deg,#667eea,#764ba2);">
                            <div class="card-body d-flex align-items-center gap-2 p-2">
                                <div class="icon"><i class="bi bi-calendar-day"></i></div>
                                <div class="flex-grow-1">
                                    <div class="number"><?php echo $citasHoy; ?></div>
                                    <div class="label">Citas hoy</div>
                                </div>
                                <a href="SCH_Calendar.php" class="text-white link ms-auto" style="font-size:.8rem;" title="Ver calendario">
                                    <i class="bi bi-arrow-right-circle"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md-3">
                        <div class="card stat-card text-white shadow-sm" style="background:linear-gradient(135deg,#f6d365,#fda085);">
                            <div class="card-body d-flex align-items-center gap-2 p-2">
                                <div class="icon"><i class="bi bi-hourglass-split"></i></div>
                                <div class="flex-grow-1">
                                    <div class="number"><?php echo $citasPendientes; ?></div>
                                    <div class="label">Pendientes</div>
                                </div>
                                <a href="Agenda_Pendientes.php" class="text-white link ms-auto" style="font-size:.8rem;" title="Ver pendientes">
                                    <i class="bi bi-arrow-right-circle"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md-3">
                        <div class="card stat-card text-white shadow-sm" style="background:linear-gradient(135deg,#43e97b,#38f9d7);">
                            <div class="card-body d-flex align-items-center gap-2 p-2">
                                <div class="icon"><i class="bi bi-clipboard2-pulse"></i></div>
                                <div class="flex-grow-1">
                                    <div class="number"><?php echo $citasAtendidas; ?></div>
                                    <div class="label">Atendidas (mes)</div>
                                </div>
                                <a href="historial_atenciones.php" class="text-white link ms-auto" style="font-size:.8rem;" title="Ver historial">
                                    <i class="bi bi-arrow-right-circle"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md-3">
                        <div class="card stat-card text-white shadow-sm" style="background:linear-gradient(135deg,#f093fb,#f5576c);">
                            <div class="card-body d-flex align-items-center gap-2 p-2">
                                <div class="icon"><i class="bi bi-people-fill"></i></div>
                                <div class="flex-grow-1">
                                    <div class="number"><?php echo $totalPacientes; ?></div>
                                    <div class="label">Pacientes</div>
                                </div>
                                <a href="PNC_PacienteCrear.php" class="text-white link ms-auto" style="font-size:.8rem;" title="Nuevo paciente">
                                    <i class="bi bi-arrow-right-circle"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                </div><!-- /row tarjetas -->

                <!-- ══ SEGUNDA FILA: gráfico + citas hoy ═════════════ -->
                <div class="row g-3 mb-4">

                    <!-- Gráfico citas últimos 7 días -->
                    <div class="col-md-5">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <p class="section-title"><i class="bi bi-bar-chart-line me-1"></i>Citas — últimos 7 días</p>
                                <canvas id="chartSemana" height="180"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Mini stats adicionales -->
                    <div class="col-md-3">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <p class="section-title"><i class="bi bi-pie-chart me-1"></i>Este mes</p>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <span><span class="badge bg-success me-1">●</span> Confirmadas</span>
                                        <span class="fw-bold"><?php echo $citasConfirmadas; ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <span><span class="badge bg-warning text-dark me-1">●</span> Pendientes</span>
                                        <span class="fw-bold"><?php echo $citasPendientes; ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <span><span style="color:#6f42c1" class="me-1">●</span> Atendidas</span>
                                        <span class="fw-bold"><?php echo $citasAtendidas; ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <span><span class="badge bg-danger me-1">●</span> Canceladas</span>
                                        <span class="fw-bold"><?php echo $citasCanceladas; ?></span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Últimas atenciones -->
                    <div class="col-md-4">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <p class="section-title"><i class="bi bi-clock-history me-1"></i>Últimas atenciones</p>
                                <ul class="list-group list-group-flush">
                                    <?php if ($resUlt && $resUlt->num_rows > 0): ?>
                                        <?php while ($u = $resUlt->fetch_assoc()): ?>
                                        <li class="list-group-item px-0 py-1">
                                            <div class="fw-semibold" style="font-size:.85rem;">
                                                <?php echo htmlspecialchars($u['PACIENTE']); ?>
                                            </div>
                                            <div class="text-muted" style="font-size:.75rem;">
                                                <?php echo date('d/m/Y H:i', strtotime($u['FECHA_REGISTRO'])); ?>
                                                <?php if ($u['IMC']): ?>
                                                    &nbsp;· IMC <?php echo number_format($u['IMC'],1); ?>
                                                <?php endif; ?>
                                            </div>
                                        </li>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <li class="list-group-item px-0 text-muted" style="font-size:.85rem;">Sin atenciones registradas.</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>

                </div><!-- /segunda fila -->

                <!-- ══ TERCERA FILA: citas de hoy + próximas ═════════ -->
                <div class="row g-3">

                    <!-- Citas de hoy -->
                    <div class="col-md-7">
                        <div class="card shadow-sm">
                            <div class="card-header d-flex justify-content-between align-items-center py-2">
                                <span class="section-title mb-0">
                                    <i class="bi bi-calendar-check me-1"></i>
                                    Citas de hoy — <?php echo date('d/m/Y'); ?>
                                </span>
                                <a href="SCH_Calendar.php" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-calendar3"></i> Ir al calendario
                                </a>
                            </div>
                            <div class="card-body p-0">
                                <?php if ($resHoy && $resHoy->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Hora</th>
                                                <th>Paciente</th>
                                                <th>Tipo</th>
                                                <th>Doctor</th>
                                                <th>Estado</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php while ($c = $resHoy->fetch_assoc()): ?>
                                            <?php
                                            $est = $c['ESTADO_CITA'];
                                            $badgeClass = match($est) {
                                                'Confirmada'        => 'bg-success',
                                                'Pendiente'         => 'bg-warning text-dark',
                                                'A'                 => 'bg-purple',
                                                'Cancelada','Cancelado' => 'bg-danger',
                                                default             => 'bg-secondary'
                                            };
                                            $estLabel = $est === 'A' ? 'Atendida' : $est;
                                            ?>
                                            <tr>
                                                <td class="fw-semibold">
                                                    <?php echo substr($c['HORA_INICIO'],0,5); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($c['PACIENTE']); ?></td>
                                                <td><small><?php echo htmlspecialchars($c['TIPO_CONSULTA'] ?? '—'); ?></small></td>
                                                <td><small><?php echo htmlspecialchars($c['DOCTOR'] ?? '—'); ?></small></td>
                                                <td>
                                                    <span class="badge <?php echo $badgeClass; ?>"
                                                          <?php echo $est==='A' ? 'style="background:#6f42c1"' : ''; ?>>
                                                        <?php echo $estLabel; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($est !== 'A'): ?>
                                                    <a href="atencion_paciente.php?idCita=<?php echo $c['IDCITA']; ?>"
                                                       class="btn btn-xs btn-primary btn-sm py-0 px-2">
                                                        <i class="bi bi-person-check"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                    <div class="p-3 text-muted text-center">
                                        <i class="bi bi-calendar-x fs-3 d-block mb-1"></i>
                                        No hay citas programadas para hoy.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Próximas citas (7 días) -->
                    <div class="col-md-5">
                        <div class="card shadow-sm">
                            <div class="card-header py-2">
                                <span class="section-title mb-0">
                                    <i class="bi bi-calendar-week me-1"></i>
                                    Próximas citas (7 días)
                                </span>
                            </div>
                            <div class="card-body p-0">
                                <?php if ($resProx && $resProx->num_rows > 0): ?>
                                <ul class="list-group list-group-flush">
                                    <?php while ($p = $resProx->fetch_assoc()): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-start py-2">
                                        <div>
                                            <div class="fw-semibold" style="font-size:.85rem;">
                                                <?php echo htmlspecialchars($p['PACIENTE']); ?>
                                            </div>
                                            <div class="text-muted" style="font-size:.75rem;">
                                                <?php echo date('d/m', strtotime($p['FECHA_CITA'])); ?>
                                                &nbsp;<?php echo substr($p['HORA_INICIO'],0,5); ?>
                                                <?php if ($p['TIPO_CONSULTA']): ?>
                                                    &nbsp;· <?php echo htmlspecialchars($p['TIPO_CONSULTA']); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php
                                        $est2 = $p['ESTADO_CITA'];
                                        $bc2  = $est2 === 'Confirmada' ? 'bg-success' : 'bg-warning text-dark';
                                        ?>
                                        <span class="badge <?php echo $bc2; ?>"><?php echo $est2; ?></span>
                                    </li>
                                    <?php endwhile; ?>
                                </ul>
                                <?php else: ?>
                                    <div class="p-3 text-muted text-center">
                                        <i class="bi bi-calendar-x fs-3 d-block mb-1"></i>
                                        Sin citas en los próximos 7 días.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div><!-- /tercera fila -->

            </div><!-- /app-main__inner -->
        </div><!-- /app-main__outer -->
    </div><!-- /app-main -->
</div><!-- /app-container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script type="text/javascript" src="./assets/scripts/main.js"></script>
<script>
// Gráfico de barras — citas últimos 7 días
const ctx = document.getElementById('chartSemana').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($semana, 'fecha')); ?>,
        datasets: [{
            label: 'Citas',
            data: <?php echo json_encode(array_column($semana, 'total')); ?>,
            backgroundColor: 'rgba(102,126,234,0.7)',
            borderColor:     'rgba(102,126,234,1)',
            borderWidth: 1,
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { stepSize: 1, precision: 0 }
            }
        }
    }
});
</script>
</body>
</html>

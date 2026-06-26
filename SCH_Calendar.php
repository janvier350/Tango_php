<?php
ob_start();
session_start();
require_once("class/funciones.php");
require_once("class/conexionBD.php");
require_once("class/i18n.php");
$conexion = conectarse();

if(!isset($_SESSION["rol"])){
    header("Location: break.php");
    exit();
}

$now = time();
if(isset($_SESSION['expire']) && $now > $_SESSION['expire']){
    session_destroy();
    header("Location: expirada.php");
    exit();
}

$colorTipoDefault = '#3788d8';

$query = "SELECT
            A.IDCITA,
            CONCAT(B.NOMBRES, ' ', B.APELLIDOS) AS PACIENTE,
            B.TELEFONO,
            C.NOMBRES AS TIPO_CONSULTA,
            C.COLOR AS TIPO_COLOR,
            A.FECHA_CITA,
            A.HORA_INICIO,
            A.HORA_FIN,
            A.ESTADO_CITA,
            A.COMENTARIO,
            CONCAT(D.NOMBRES, ' ', D.APELLIDOS) AS DOCTOR,
            E.DESCRIPCION AS AGENCIA
          FROM AG_CITA A
          INNER JOIN AG_PACIENTE B     ON A.IDPACIENTE      = B.IDPACIENTE
          INNER JOIN AG_TIPOCONSULTA C ON A.IDTIPOCONSULTA  = C.IDTIPOCONSULTA
          INNER JOIN ADM_USUARIO D     ON A.IDDOCTOR        = D.IDADM_USUARIO
          LEFT JOIN  ADM_AGENCIA E     ON A.IDAGENCIA       = E.IDAGENCIA
          WHERE A.ESTADO = 'A'";

$resultado = $conexion->query($query);
$eventos   = array();

while ($row = $resultado->fetch_assoc()) {
    switch($row['ESTADO_CITA']) {
        case 'Pendiente':
        case 'Reagendada':              $colorEstado = '#212529'; break; // Negro  - Pendiente / Reagendada
        case 'Confirmada':              $colorEstado = '#6f42c1'; break; // Morado - Confirmada
        case 'A':                       $colorEstado = '#28a745'; break; // Verde  - Atendida
        case 'Cancelada':
        case 'Cancelado':               $colorEstado = '#fd7e14'; break; // Naranja - Cancelada
        case 'Atrasado':
        case 'Cancelación Tardía':      $colorEstado = '#ffc107'; break; // Ámbar  - Cancelación tardía
        case 'Cancelado por Profesional': $colorEstado = '#ff8a80'; break; // Salmón - Cancelado por el profesional
        case 'No Asistió':              $colorEstado = '#dc3545'; break; // Rojo   - No asistió
        default:                        $colorEstado = '#007bff'; break; // Azul
    }

    $colorTipo = !empty($row['TIPO_COLOR']) ? $row['TIPO_COLOR'] : $colorTipoDefault;

    $eventos[] = array(
        'id'              => $row['IDCITA'],
        'title'           => $row['PACIENTE'],
        'start'           => $row['FECHA_CITA'] . 'T' . $row['HORA_INICIO'],
        'end'             => $row['FECHA_CITA'] . 'T' . $row['HORA_FIN'],
        'backgroundColor' => $colorEstado,
        'borderColor'     => $colorTipo,
        'textColor'       => '#ffffff',
        'extendedProps'   => array(
            'cita'      => $row['ESTADO_CITA'],
            'medico'    => $row['DOCTOR'],
            'consulta'  => $row['TIPO_CONSULTA'],
            'telefono'  => $row['TELEFONO'],
            'comentario'=> $row['COMENTARIO'],
            'agencia'   => $row['AGENCIA'],
        )
    );
}

$tiposConsultaActivos = array();
$resTipos = $conexion->query("SELECT NOMBRES, COLOR FROM AG_TIPOCONSULTA WHERE ESTADO = 'A' ORDER BY NOMBRES");
while ($t = $resTipos->fetch_assoc()) {
    $tiposConsultaActivos[] = array(
        'nombre' => $t['NOMBRES'],
        'color'  => !empty($t['COLOR']) ? $t['COLOR'] : $colorTipoDefault,
    );
}

$sqlDoctores = "SELECT U.IDADM_USUARIO, U.NOMBRES, U.APELLIDOS
                FROM ADM_USUARIO U
                INNER JOIN ADM_ROL R ON U.IDADM_ROL = R.IDADM_ROL
                WHERE R.CARGO = 'DOCTOR'
                  AND U.ESTADO = 'A'
                ORDER BY U.NOMBRES";
$resultDoctores  = $conexion->query($sqlDoctores);
$doctoresActivos = array();
while ($d = $resultDoctores->fetch_assoc()) {
    $doctoresActivos[] = array(
        'id'     => $d['IDADM_USUARIO'],
        'nombre' => $d['NOMBRES'] . ' ' . $d['APELLIDOS'],
    );
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, shrink-to-fit=no">
    <title>Calendario de Citas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <link href="./fullcalendar/main.css" rel="stylesheet">
    <link href="./main.css" rel="stylesheet">
    <script src="js/jquery.min.js"></script>
    <style>
        .fc-event { cursor: pointer; font-size: 0.85em; padding: 2px 5px; }
        #eventModal .btn { transition: all 0.3s ease; white-space: nowrap; }
        #eventModal .btn:hover { transform: translateY(-2px); box-shadow: 0 3px 10px rgba(0,0,0,0.1); }
        /* Franja izquierda = tipo de consulta (el fondo del evento sigue indicando el estado) */
        .fc-daygrid-event, .fc-timegrid-event, .fc-list-event-dot {
            border-width: 0 0 0 5px !important;
            border-style: solid !important;
            border-radius: 3px;
        }
        .fc-list-event-dot { border-radius: 50%; border-width: 5px !important; }
        /* Contenido completo del evento en vistas semana/día (sin truncar) */
        .fc-event-main-custom { white-space: normal; padding: 1px 2px; }
        .fc-event-time-custom { font-size: 0.72em; font-weight: 600; opacity: 0.9; }
        .fc-event-title-custom { font-weight: 700; word-break: break-word; }
        .fc-event-sub-custom { font-size: 0.72em; opacity: 0.9; word-break: break-word; }
        /* Leyenda de colores */
        .leyenda-calendario { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 6px; font-size: 0.8rem; }
        .leyenda-item { display: flex; align-items: center; gap: 5px; }
        .leyenda-dot { width: 12px; height: 12px; border-radius: 50%; display: inline-block; }
        .leyenda-dot-borde { width: 12px; height: 12px; border-radius: 50%; display: inline-block; background: #fff; border: 3px solid #999; box-sizing: border-box; }
        .leyenda-franja { width: 16px; height: 12px; display: inline-block; background: #fff; border: 1px solid #ddd; border-left: 5px solid #999; box-sizing: border-box; border-radius: 2px; }
        .leyenda-label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: .04em; color: #888; font-weight: 600; margin-right: 4px; }

        /* ── Vista por Doctor ─────────────────────────────────────── */
        .cv-scroll { overflow-x: auto; border: 1px solid #dee2e6; border-radius: 4px; }
        .cv-grid { position: relative; }
        .cv-col-head {
            position: absolute; box-sizing: border-box;
            border-right: 1px solid #dee2e6; border-bottom: 1px solid #dee2e6;
            background: #f8f9fa; font-size: 0.78rem; text-align: center;
            display: flex; align-items: center; justify-content: center; overflow: hidden;
        }
        .cv-col-head.cv-day-head { font-weight: 600; background: #eef1f5; }
        .cv-col-head.cv-day-head.cv-today { background: #fff3cd; }
        .cv-time-label {
            position: absolute; width: 64px; box-sizing: border-box;
            font-size: 0.72rem; color: #6c757d; text-align: right; padding-right: 6px;
            border-right: 1px solid #dee2e6; border-bottom: 1px solid #f1f1f1;
        }
        .cv-cell {
            position: absolute; box-sizing: border-box;
            border-right: 1px solid #f1f1f1; border-bottom: 1px solid #f1f1f1;
        }
        .cv-cell.cv-day-end { border-right: 1px solid #dee2e6; }
        .cv-event {
            position: absolute; box-sizing: border-box; border-radius: 4px;
            color: #fff; font-size: 0.72rem; padding: 2px 4px; overflow: hidden;
            cursor: pointer; line-height: 1.2; box-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }
        .cv-event b { display: block; }
        .cv-doctores { display: flex; flex-wrap: wrap; gap: 10px 18px; align-items: center; font-size: 0.85rem; }

        /* ── Lista de citas para móvil (Vista por Doctor) ───────────── */
        .cv-list { display: none; }
        .cv-list-day {
            font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: .03em;
            color: #555; background: #eef1f5; padding: 6px 10px; border-radius: 4px; margin: 12px 0 6px;
        }
        .cv-list-day.cv-today { background: #fff3cd; color: #7a5b00; }
        .cv-list-card {
            display: flex; align-items: stretch; gap: 0; border-radius: 6px; overflow: hidden;
            margin-bottom: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.15); cursor: pointer;
        }
        .cv-list-card .cv-list-color { width: 6px; flex: 0 0 6px; }
        .cv-list-card .cv-list-body { flex: 1 1 auto; padding: 8px 10px; color: #fff; min-width: 0; }
        .cv-list-card .cv-list-time { font-size: 0.78rem; font-weight: 600; opacity: 0.9; }
        .cv-list-card .cv-list-paciente { font-size: 0.95rem; font-weight: 700; word-break: break-word; }
        .cv-list-card .cv-list-sub { font-size: 0.78rem; opacity: 0.9; white-space: normal; word-break: break-word; }
        .cv-list-empty { color: #888; font-size: 0.85rem; padding: 10px; }

        @media (max-width: 768px) {
            .cv-scroll { display: none; }
            .cv-list { display: block; }
            .page-title-heading > div { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
            .page-title-heading .btn-group { flex-wrap: wrap; }
            .fc-toolbar { flex-direction: column; gap: 8px; }
            .fc-toolbar-chunk { display: flex; flex-wrap: wrap; justify-content: center; gap: 4px; }
            .fc-toolbar-title { font-size: 1.1rem !important; }
        }
        .cv-doctores label { display: flex; align-items: center; gap: 5px; margin: 0; cursor: pointer; }
    </style>
</head>
<body>

<div class="app-container app-theme-white body-tabs-shadow fixed-sidebar fixed-header">

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
            <div class="app-header-left">
                <ul class="header-menu nav">
                    <li class="nav-item">
                        <a href="javascript:void(0);" class="nav-link">
                            <i class="nav-link-icon fa fa-database"></i> <?php echo t('Estadistica'); ?>
                        </a>
                    </li>
                    <li class="dropdown nav-item">
                        <a href="javascript:void(0);" class="nav-link">
                            <i class="nav-link-icon fa fa-cog"></i> <?php echo t('Configuracion'); ?>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="app-header-right">
                <div class="header-btn-lg pr-0">
                    <div class="widget-content p-0">
                        <div class="widget-content-wrapper">
                            <div class="widget-content-left">
                                <div class="btn-group">
                                    <a data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" class="p-0 btn">
                                        <img width="42" class="rounded-circle">
                                        <i class="fa fa-angle-down ml-2 opacity-8"></i>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-right">
                                        <button type="button" class="dropdown-item"><?php echo t('Perfil de Usuario'); ?></button>
                                        <button type="button" class="dropdown-item"><?php echo t('Configuración'); ?></button>
                                        <div class="dropdown-divider"></div>
                                        <a href="salir.php" class="dropdown-item"><?php echo t('Cerrar Sesión'); ?></a>
                                    </div>
                                </div>
                            </div>
                            <div class="widget-content-left ml-3 header-user-info">
                                <div class="widget-heading">
                                    <?php echo htmlspecialchars($_SESSION["username"] ?? ''); ?>
                                </div>
                                <div class="widget-subheading">
                                    <?php echo htmlspecialchars($_SESSION["rol"] ?? 'Usuario'); ?> — <?php echo date('d/m/Y'); ?>
                                </div>
                            </div>
                            <div class="widget-content-left ml-3">
                                <?php include("lang_toggle.php"); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="app-main">
        <div class="app-sidebar sidebar-shadow">
            <?php include("./menu/menu_adm.php"); ?>
        </div>

        <div class="app-main__outer">
            <div class="app-main__inner">
                <div class="app-page-title">
                    <div class="page-title-wrapper">
                        <div class="page-title-heading">
                            <div class="page-title-icon">
                                <i class="pe-7s-date icon-gradient bg-warm-flame"></i>
                            </div>
                            <div>
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#editModal">
                                    <i class="bi bi-calendar-plus"></i> Agendar Cita
                                </button>
                                <div class="btn-group ms-2" role="group">
                                    <button type="button" id="btnVistaCalendario" class="btn btn-outline-primary active">
                                        <i class="bi bi-calendar3"></i> Calendario
                                    </button>
                                    <button type="button" id="btnVistaDoctor" class="btn btn-outline-primary">
                                        <i class="bi bi-people"></i> Vista por Doctor
                                    </button>
                                </div>
                                <div class="page-title-subheading">Citas agendadas.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="main-card mb-3 card" id="viewFullCalendar">
                    <div class="card-body">
                        <!-- Leyenda de colores -->
                        <div class="leyenda-calendario">
                            <span class="leyenda-label">Estado (relleno):</span>
                            <span class="leyenda-item"><span class="leyenda-dot" style="background:#212529"></span> Pendiente/Reagendada</span>
                            <span class="leyenda-item"><span class="leyenda-dot" style="background:#6f42c1"></span> Confirmada</span>
                            <span class="leyenda-item"><span class="leyenda-dot" style="background:#28a745"></span> Atendida</span>
                            <span class="leyenda-item"><span class="leyenda-dot" style="background:#fd7e14"></span> Cancelada</span>
                            <span class="leyenda-item"><span class="leyenda-dot" style="background:#ffc107"></span> Cancelación Tardía</span>
                            <span class="leyenda-item"><span class="leyenda-dot" style="background:#ff8a80"></span> Cancelado por Profesional</span>
                            <span class="leyenda-item"><span class="leyenda-dot" style="background:#dc3545"></span> No Asistió</span>
                        </div>
                        <div class="leyenda-calendario">
                            <span class="leyenda-label">Tipo de consulta (franja izquierda):</span>
                            <?php foreach ($tiposConsultaActivos as $tc): ?>
                                <span class="leyenda-item"><span class="leyenda-franja" style="border-left-color:<?php echo htmlspecialchars($tc['color']); ?>"></span> <?php echo htmlspecialchars($tc['nombre']); ?></span>
                            <?php endforeach; ?>
                        </div>
                        <div id="calendar1"></div>
                    </div>
                </div>

                <div class="main-card mb-3 card d-none" id="viewPorDoctor">
                    <div class="card-body">
                        <!-- Leyenda de colores -->
                        <div class="leyenda-calendario">
                            <span class="leyenda-label">Estado (relleno):</span>
                            <span class="leyenda-item"><span class="leyenda-dot" style="background:#212529"></span> Pendiente/Reagendada</span>
                            <span class="leyenda-item"><span class="leyenda-dot" style="background:#6f42c1"></span> Confirmada</span>
                            <span class="leyenda-item"><span class="leyenda-dot" style="background:#28a745"></span> Atendida</span>
                            <span class="leyenda-item"><span class="leyenda-dot" style="background:#fd7e14"></span> Cancelada</span>
                            <span class="leyenda-item"><span class="leyenda-dot" style="background:#ffc107"></span> Cancelación Tardía</span>
                            <span class="leyenda-item"><span class="leyenda-dot" style="background:#ff8a80"></span> Cancelado por Profesional</span>
                            <span class="leyenda-item"><span class="leyenda-dot" style="background:#dc3545"></span> No Asistió</span>
                        </div>
                        <div class="leyenda-calendario">
                            <span class="leyenda-label">Tipo de consulta (franja izquierda):</span>
                            <?php foreach ($tiposConsultaActivos as $tc): ?>
                                <span class="leyenda-item"><span class="leyenda-franja" style="border-left-color:<?php echo htmlspecialchars($tc['color']); ?>"></span> <?php echo htmlspecialchars($tc['nombre']); ?></span>
                            <?php endforeach; ?>
                        </div>
                        <div class="cv-toolbar d-flex align-items-center gap-2 mb-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="cvToday"><?php echo t('Hoy'); ?></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="cvPrev"><i class="bi bi-chevron-left"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="cvNext"><i class="bi bi-chevron-right"></i></button>
                            <strong id="cvRangeLabel" class="ms-2"></strong>
                        </div>
                        <div class="cv-scroll">
                            <div id="cvGrid" class="cv-grid"></div>
                        </div>
                        <div class="cv-list" id="cvList"></div>
                        <div class="cv-doctores mt-3" id="cvDoctorFiltros">
                            <span class="fw-semibold me-2"><?php echo t('Doctores'); ?>:</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── MODAL AGENDAR CITA ─────────────────────────────────────────── -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo t('Agendar Cita'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="insertCita" method="POST" action="class/Insert_cita.php">
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('Fecha Consulta'); ?></label>
                        <input type="date" class="form-control" name="fechafactura" id="fechafactura">
                        <script>document.getElementById('fechafactura').value = new Date().toISOString().substring(0, 10);</script>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('Paciente'); ?></label>
                        <select class="form-select select-busqueda" name="IdPaciente" required>
                            <option value=""><?php echo t('Buscar paciente...'); ?></option>
                            <?php
                            $queryP = $conexion->query("SELECT IDPACIENTE, NOMBRES, APELLIDOS FROM AG_PACIENTE WHERE ESTADO = 'A' ORDER BY NOMBRES");
                            while ($v = $queryP->fetch_assoc()):
                            ?>
                            <option value="<?php echo $v['IDPACIENTE']; ?>"><?php echo htmlspecialchars($v['NOMBRES'].' '.$v['APELLIDOS']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('Hora Inicio'); ?></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-clock"></i></span>
                            <select class="form-select select-busqueda" name="timeIni" id="timeIni" required>
                                <option value=""><?php echo t('Seleccione hora...'); ?></option>
                                <?php
                                $inicio    = new DateTime('07:00');
                                $fin       = new DateTime('22:30');
                                $intervalo = new DateInterval('PT30M');
                                foreach (new DatePeriod($inicio, $intervalo, $fin) as $hora):
                                    $v = $hora->format('H:i');
                                ?>
                                <option value="<?php echo $v; ?>"><?php echo $hora->format('h:i A'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('Tipo Consulta'); ?></label>
                        <select class="form-select select-busqueda" name="Idconsulta" required>
                            <option value=""><?php echo t('Buscar tipo consulta...'); ?></option>
                            <?php
                            $queryC = $conexion->query("SELECT IDTIPOCONSULTA, NOMBRES FROM AG_TIPOCONSULTA WHERE ESTADO = 'A' ORDER BY NOMBRES");
                            while ($v = $queryC->fetch_assoc()):
                            ?>
                            <option value="<?php echo $v['IDTIPOCONSULTA']; ?>"><?php echo htmlspecialchars($v['NOMBRES']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('Doctor'); ?></label>
                        <select class="form-select select-busqueda" name="IdDoctor" required>
                            <option value=""><?php echo t('Buscar doctor...'); ?></option>
                            <?php foreach ($doctoresActivos as $v): ?>
                            <option value="<?php echo $v['id']; ?>"><?php echo htmlspecialchars($v['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('Location'); ?></label>
                        <select class="form-select select-busqueda" name="IdAgencia" required>
                            <option value=""><?php echo t('Seleccione location...'); ?></option>
                            <?php
                            $queryAg = $conexion->query("SELECT IDAGENCIA, DESCRIPCION FROM ADM_AGENCIA WHERE ESTADO = 1 ORDER BY DESCRIPCION");
                            while ($v = $queryAg->fetch_assoc()):
                            ?>
                            <option value="<?php echo $v['IDAGENCIA']; ?>"><?php echo htmlspecialchars($v['DESCRIPCION']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-calendar-check"></i> <?php echo t('Agendar'); ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ── MODAL GESTIÓN CITA ─────────────────────────────────────────── -->
<div class="modal fade" id="eventModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo t('Gestión de Cita'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEstado" method="POST" action="class/actualizar_estado.php" onsubmit="return validarFormulario();">
                <div class="modal-body">
                    <div id="eventDetails" class="mb-3"></div>

                    <!-- Panel Reagendar (oculto por defecto) -->
                    <div id="reagendarSection" class="d-none border rounded p-3 bg-light mt-2">
                        <h6 class="mb-3"><i class="bi bi-calendar2-event"></i> Nueva fecha y hora</h6>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Fecha</label>
                                <input type="date" id="nuevaFecha" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Hora</label>
                                <select id="nuevaHora" class="form-select"></select>
                            </div>
                        </div>
                        <div class="d-flex gap-2 mt-3">
                            <button type="button" class="btn btn-warning flex-grow-1" onclick="guardarReagenda()">
                                <i class="bi bi-calendar-check"></i> Guardar nueva fecha
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="cerrarReagendar()">
                                Cancelar
                            </button>
                        </div>
                    </div>

                    <input type="hidden" id="idCita"     name="id">
                    <input type="hidden" id="estadoCita" name="estado">
                </div>
                <div class="modal-footer" id="modalFooterBtns">
                    <button id="btnAtender" class="btn btn-primary" type="button" onclick="irAConsulta()">
                        <i class="bi bi-person-check-fill"></i> Atender Paciente
                    </button>
                    <a id="btnHistorial" href="#" class="btn btn-purple d-none"
                       style="background:#6f42c1;color:#fff;">
                        <i class="bi bi-clipboard2-pulse"></i> Ver Historial
                    </a>
                    <button id="btnReagendar" class="btn btn-warning" type="button" onclick="toggleReagendar()">
                        <i class="bi bi-calendar2-event"></i> Reagendar
                    </button>
                    <button id="btnConfirmar" class="btn btn-success" type="submit" onclick="setEstado('Confirmada')">
                        <i class="bi bi-check-circle"></i> Confirmar
                    </button>
                    <button id="btnCancelar" class="btn btn-danger" type="submit" onclick="setEstado('Cancelada')">
                        <i class="bi bi-x-circle"></i> Cancelar
                    </button>
                    <div id="btnMasEstados" class="btn-group">
                        <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                            Otro estado
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><button class="dropdown-item" type="submit" onclick="setEstado('Reagendada')">Reagendada</button></li>
                            <li><button class="dropdown-item" type="submit" onclick="setEstado('Cancelación Tardía')">Cancelación Tardía</button></li>
                            <li><button class="dropdown-item" type="submit" onclick="setEstado('Cancelado por Profesional')">Cancelado por Profesional</button></li>
                            <li><button class="dropdown-item" type="submit" onclick="setEstado('No Asistió')">No Asistió</button></li>
                        </ul>
                    </div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Scripts — cargados UNA sola vez, en orden correcto -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="./fullcalendar/main.js"></script>
<script src="./fullcalendar/locales/es.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script type="text/javascript" src="./assets/scripts/main.js"></script>

<script>
// ── Helpers ──────────────────────────────────────────────────────────
function estadoBadge(estado) {
    const map = {
        'Pendiente':                   ['#212529', 'Pendiente'],
        'Reagendada':                  ['#212529', 'Reagendada'],
        'Confirmada':                  ['#6f42c1', 'Confirmada'],
        'A':                           ['#28a745', 'Atendida'],
        'Cancelada':                   ['#fd7e14', 'Cancelada'],
        'Cancelado':                   ['#fd7e14', 'Cancelado'],
        'Atrasado':                    ['#ffc107', 'Atrasado'],
        'Cancelación Tardía':          ['#ffc107', 'Cancelación Tardía'],
        'Cancelado por Profesional':   ['#ff8a80', 'Cancelado por Profesional'],
        'No Asistió':                  ['#dc3545', 'No Asistió'],
    };
    const [color, label] = map[estado] || ['#6c757d', estado];
    return `<span class="badge" style="background:${color}">${label}</span>`;
}

function setEstado(estado) {
    document.getElementById('estadoCita').value = estado;
}

function irAConsulta() {
    const id = document.getElementById('idCita').value;
    window.location.href = `atencion_paciente.php?idCita=${id}`;
}

// ── Reagendar ────────────────────────────────────────────────────────
function generarHorasReagendar() {
    const sel = document.getElementById('nuevaHora');
    if (sel.options.length > 0) return; // ya generadas
    let h = 7, m = 0;
    while (h < 22 || (h === 22 && m === 0)) {
        const val  = `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}`;
        const ampm = h < 12 ? 'AM' : 'PM';
        const h12  = h > 12 ? h - 12 : (h === 0 ? 12 : h);
        const lbl  = `${String(h12).padStart(2,'0')}:${String(m).padStart(2,'0')} ${ampm}`;
        sel.add(new Option(lbl, val));
        m += 30;
        if (m >= 60) { m = 0; h++; }
    }
}

function toggleReagendar() {
    const section = document.getElementById('reagendarSection');
    const hidden  = section.classList.contains('d-none');
    section.classList.toggle('d-none', !hidden);
    if (hidden) {
        generarHorasReagendar();
        document.getElementById('nuevaFecha').value = new Date().toISOString().substring(0, 10);
    }
}

function cerrarReagendar() {
    document.getElementById('reagendarSection').classList.add('d-none');
}

function guardarReagenda() {
    const id    = document.getElementById('idCita').value;
    const fecha = document.getElementById('nuevaFecha').value;
    const hora  = document.getElementById('nuevaHora').value;

    if (!fecha || !hora) {
        alert('Seleccione fecha y hora.');
        return;
    }

    // Formatear fecha para confirmar
    const [y, mo, d] = fecha.split('-');
    if (!confirm(`¿Reagendar la cita al ${d}/${mo}/${y} a las ${hora}?`)) return;

    $.post('reagendar_cita.php', { idCita: id, fecha: fecha, hora: hora }, function(res) {
        if (res.trim() === 'OK') {
            alert('Cita reagendada con éxito.');
            location.reload();
        } else {
            alert('Error al reagendar: ' + res);
        }
    }).fail(function() {
        alert('Error de conexión. Intente de nuevo.');
    });
}

function validarFormulario() {
    const id     = document.getElementById('idCita').value;
    const estado = document.getElementById('estadoCita').value;
    if (!id || !estado) {
        alert("Error: Los datos del formulario no están completos.");
        return false;
    }
    return true;
}

// ── Datos compartidos (FullCalendar + Vista por Doctor) ───────────────
const eventosAll      = <?php echo json_encode($eventos); ?>;
const doctoresActivos = <?php echo json_encode($doctoresActivos); ?>;

// ── Modal de gestión de cita (usado por ambas vistas) ──────────────────
const ESTADOS_CERRADOS = ['A', 'Cancelada', 'Cancelado', 'Cancelación Tardía', 'Cancelado por Profesional', 'No Asistió'];

function abrirModalCita(id, title, startDate, p) {
    const est      = p.cita || 'Pendiente';
    const atendida = est === 'A';
    const cerrada  = ESTADOS_CERRADOS.includes(est);

    // Limpiar y formatear teléfono para WhatsApp
    let tel = p.telefono ? p.telefono.replace(/\D/g, '') : '';
    if (tel.length === 9 && tel.startsWith('9'))        tel = '593' + tel;
    else if (tel.length === 10 && tel.startsWith('09')) tel = '593' + tel.substring(1);

    const msg = encodeURIComponent(
        `Hola, le saludamos de SROSS Nutritions. Le recordamos su cita de ${p.consulta} para el día ` +
        `${startDate.toLocaleDateString()} a las ` +
        `${startDate.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}. ¿Nos confirma su asistencia?`
    );

    document.getElementById('eventDetails').innerHTML = `
        <div class="row">
            <div class="col-md-8">
                <p><strong>Paciente:</strong> ${title}</p>
                <p><strong>Teléfono:</strong> ${p.telefono || 'No registrado'}</p>
                <p><strong>Inicio:</strong> ${startDate.toLocaleString()}</p>
                <p><strong>Doctor:</strong> ${p.medico}</p>
                <p><strong>Location:</strong> ${p.agencia || 'No registrada'}</p>
                <p><strong>Estado:</strong> ${estadoBadge(est)}</p>
            </div>
            <div class="col-md-4 text-center">
                ${tel
                    ? `<a href="https://wa.me/${tel}?text=${msg}" target="_blank"
                        class="btn btn-outline-success btn-lg mb-2">
                        <i class="bi bi-whatsapp"></i> Confirmar por WhatsApp
                       </a>`
                    : '<p class="text-danger small">Sin número para WhatsApp</p>'
                }
            </div>
        </div>
        <hr>
        <p><strong>Tipo consulta:</strong> ${p.consulta}</p>
        ${p.comentario ? `<p><strong>Comentario:</strong> ${p.comentario}</p>` : ''}
    `;

    // Controlar visibilidad de botones según estado
    document.getElementById('idCita').value     = id;
    document.getElementById('estadoCita').value = est;

    const btnAtender     = document.getElementById('btnAtender');
    const btnHistorial   = document.getElementById('btnHistorial');
    const btnReagendar    = document.getElementById('btnReagendar');
    const btnConfirmar   = document.getElementById('btnConfirmar');
    const btnCancelar    = document.getElementById('btnCancelar');
    const btnMasEstados  = document.getElementById('btnMasEstados');

    // Cerrar panel reagendar al abrir una nueva cita
    cerrarReagendar();

    if (cerrada) {
        btnAtender.classList.add('d-none');
        btnReagendar.classList.add('d-none');
        btnConfirmar.classList.add('d-none');
        btnCancelar.classList.add('d-none');
        btnMasEstados.classList.add('d-none');
    } else {
        btnAtender.classList.remove('d-none');
        btnReagendar.classList.remove('d-none');
        btnConfirmar.classList.remove('d-none');
        btnCancelar.classList.remove('d-none');
        btnMasEstados.classList.remove('d-none');
    }

    if (atendida) {
        btnHistorial.href = `historial_atenciones.php?q=${encodeURIComponent(title)}`;
        btnHistorial.classList.remove('d-none');
    } else {
        btnHistorial.classList.add('d-none');
    }

    new bootstrap.Modal(document.getElementById('eventModal')).show();
}

// ── FullCalendar ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {

    var calendarEl  = document.getElementById('calendar1');
    var esMovilInit = window.innerWidth <= 768;
    var calendar    = new FullCalendar.Calendar(calendarEl, {
        locale: 'es',
        headerToolbar: {
            left:   'prev,next today',
            center: 'title',
            right:  'dayGridMonth,timeGridWeek,timeGridDay'
        },
        initialView: esMovilInit ? 'timeGridDay' : 'dayGridMonth',
        eventDisplay: 'block',
        events: eventosAll,

        eventContent: function (arg) {
            if (!arg.view.type.startsWith('timeGrid')) return true;
            const p = arg.event.extendedProps;
            const sub = [p.consulta, p.agencia].filter(Boolean).join(' — ');
            const div = document.createElement('div');
            div.className = 'fc-event-main-custom';
            div.innerHTML = `
                <div class="fc-event-time-custom">${arg.timeText}</div>
                <div class="fc-event-title-custom">${cvEscapeHtml(arg.event.title)}</div>
                ${sub ? `<div class="fc-event-sub-custom">${cvEscapeHtml(sub)}</div>` : ''}
            `;
            return { domNodes: [div] };
        },

        eventClick: function (info) {
            abrirModalCita(info.event.id, info.event.title, info.event.start, info.event.extendedProps);
        }
    });

    calendar.render();

    inicializarVistaPorDoctor();

    document.getElementById('btnVistaCalendario').addEventListener('click', function () {
        document.getElementById('viewFullCalendar').classList.remove('d-none');
        document.getElementById('viewPorDoctor').classList.add('d-none');
        this.classList.add('active');
        document.getElementById('btnVistaDoctor').classList.remove('active');
        calendar.updateSize();
    });

    document.getElementById('btnVistaDoctor').addEventListener('click', function () {
        document.getElementById('viewFullCalendar').classList.add('d-none');
        document.getElementById('viewPorDoctor').classList.remove('d-none');
        this.classList.add('active');
        document.getElementById('btnVistaCalendario').classList.remove('active');
        renderVistaPorDoctor();
    });
});

// ── Vista por Doctor (semana en columnas por doctor) ───────────────────
const CV_START_HOUR    = 6;
const CV_END_HOUR      = 21;
const CV_SLOT_MINUTES  = 30;
const CV_SLOT_HEIGHT   = 32;
const CV_COL_WIDTH     = 140;
const CV_TIME_COL_W    = 64;
const CV_DAY_HEAD_H    = 30;
const CV_DOC_HEAD_H    = 28;
const CV_HEADER_H      = CV_DAY_HEAD_H + CV_DOC_HEAD_H;
const CV_DIAS          = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
const CV_MESES         = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];

let cvWeekStart      = startOfWeek(new Date());
const cvDoctoresOcultos = new Set();

function startOfWeek(d) {
    const r = new Date(d.getFullYear(), d.getMonth(), d.getDate());
    r.setDate(r.getDate() - r.getDay());
    return r;
}

function cvEscapeHtml(s) {
    const div = document.createElement('div');
    div.textContent = s;
    return div.innerHTML;
}

function mismaFecha(a, b) {
    return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
}

function cvRangeLabel(weekStart) {
    const end = new Date(weekStart);
    end.setDate(end.getDate() + 6);
    if (weekStart.getMonth() === end.getMonth()) {
        return `${weekStart.getDate()} - ${end.getDate()} de ${CV_MESES[end.getMonth()]} ${end.getFullYear()}`;
    }
    return `${weekStart.getDate()} de ${CV_MESES[weekStart.getMonth()]} - ${end.getDate()} de ${CV_MESES[end.getMonth()]} ${end.getFullYear()}`;
}

function inicializarVistaPorDoctor() {
    const cont = document.getElementById('cvDoctorFiltros');
    doctoresActivos.forEach(function (doc) {
        const id = 'cvDoc_' + doc.id;
        const label = document.createElement('label');
        label.innerHTML = `<input type="checkbox" id="${id}" checked> ${doc.nombre}`;
        cont.appendChild(label);
        label.querySelector('input').addEventListener('change', function (e) {
            if (e.target.checked) cvDoctoresOcultos.delete(doc.nombre);
            else cvDoctoresOcultos.add(doc.nombre);
            renderVistaPorDoctor();
        });
    });

    document.getElementById('cvToday').addEventListener('click', function () {
        cvWeekStart = startOfWeek(new Date());
        renderVistaPorDoctor();
    });
    document.getElementById('cvPrev').addEventListener('click', function () {
        cvWeekStart.setDate(cvWeekStart.getDate() - 7);
        renderVistaPorDoctor();
    });
    document.getElementById('cvNext').addEventListener('click', function () {
        cvWeekStart.setDate(cvWeekStart.getDate() + 7);
        renderVistaPorDoctor();
    });
}

function renderVistaPorDoctor() {
    const doctores = doctoresActivos.filter(d => !cvDoctoresOcultos.has(d.nombre));
    const grid      = document.getElementById('cvGrid');
    const nDoc      = Math.max(doctores.length, 1);
    const nSlots    = ((CV_END_HOUR - CV_START_HOUR) * 60) / CV_SLOT_MINUTES;
    const totalCols = 7 * nDoc;
    const gridW     = CV_TIME_COL_W + totalCols * CV_COL_WIDTH;
    const gridH      = CV_HEADER_H + nSlots * CV_SLOT_HEIGHT;

    document.getElementById('cvRangeLabel').textContent = cvRangeLabel(cvWeekStart);

    grid.innerHTML = '';
    grid.style.width  = gridW + 'px';
    grid.style.height = gridH + 'px';

    const hoy = new Date();

    // Cabeceras de día y doctor
    for (let dia = 0; dia < 7; dia++) {
        const fecha = new Date(cvWeekStart);
        fecha.setDate(fecha.getDate() + dia);
        const left      = CV_TIME_COL_W + dia * nDoc * CV_COL_WIDTH;
        const width     = nDoc * CV_COL_WIDTH;
        const esHoy     = mismaFecha(fecha, hoy);

        const dayHead = document.createElement('div');
        dayHead.className = 'cv-col-head cv-day-head' + (esHoy ? ' cv-today' : '');
        dayHead.style.left = left + 'px'; dayHead.style.top = '0px';
        dayHead.style.width = width + 'px'; dayHead.style.height = CV_DAY_HEAD_H + 'px';
        dayHead.textContent = `${CV_DIAS[fecha.getDay()]} ${fecha.getDate()}`;
        grid.appendChild(dayHead);

        doctores.forEach(function (doc, i) {
            const docHead = document.createElement('div');
            docHead.className = 'cv-col-head';
            docHead.style.left = (left + i * CV_COL_WIDTH) + 'px';
            docHead.style.top  = CV_DAY_HEAD_H + 'px';
            docHead.style.width = CV_COL_WIDTH + 'px';
            docHead.style.height = CV_DOC_HEAD_H + 'px';
            docHead.textContent = doc.nombre;
            grid.appendChild(docHead);
        });
    }

    // Etiquetas de hora + líneas de fondo
    for (let s = 0; s < nSlots; s++) {
        const minutos = CV_START_HOUR * 60 + s * CV_SLOT_MINUTES;
        const h = Math.floor(minutos / 60), m = minutos % 60;
        const ampm = h < 12 ? 'AM' : 'PM';
        const h12  = (h % 12) === 0 ? 12 : h % 12;
        const top  = CV_HEADER_H + s * CV_SLOT_HEIGHT;

        const lbl = document.createElement('div');
        lbl.className = 'cv-time-label';
        lbl.style.left = '0px'; lbl.style.top = top + 'px';
        lbl.style.height = CV_SLOT_HEIGHT + 'px';
        lbl.textContent = `${h12}:${String(m).padStart(2,'0')} ${ampm}`;
        grid.appendChild(lbl);

        for (let c = 0; c < totalCols; c++) {
            const cell = document.createElement('div');
            const esFinDia = (c + 1) % nDoc === 0;
            cell.className = 'cv-cell' + (esFinDia ? ' cv-day-end' : '');
            cell.style.left = (CV_TIME_COL_W + c * CV_COL_WIDTH) + 'px';
            cell.style.top  = top + 'px';
            cell.style.width = CV_COL_WIDTH + 'px';
            cell.style.height = CV_SLOT_HEIGHT + 'px';
            grid.appendChild(cell);
        }
    }

    // Eventos de la semana visible
    const weekEnd = new Date(cvWeekStart);
    weekEnd.setDate(weekEnd.getDate() + 7);

    eventosAll.forEach(function (ev) {
        const inicio = new Date(ev.start);
        const fin    = new Date(ev.end);
        if (inicio < cvWeekStart || inicio >= weekEnd) return;

        const docIdx = doctores.findIndex(d => d.nombre === ev.extendedProps.medico);
        if (docIdx === -1) return;

        const diaIdx = Math.floor((inicio - cvWeekStart) / 86400000);
        const minutosInicio = inicio.getHours() * 60 + inicio.getMinutes() - CV_START_HOUR * 60;
        const duracionMin   = Math.max((fin - inicio) / 60000, CV_SLOT_MINUTES / 2);

        const left   = CV_TIME_COL_W + (diaIdx * nDoc + docIdx) * CV_COL_WIDTH + 2;
        const top    = CV_HEADER_H + (minutosInicio / CV_SLOT_MINUTES) * CV_SLOT_HEIGHT;
        const height = Math.max((duracionMin / CV_SLOT_MINUTES) * CV_SLOT_HEIGHT - 2, 18);

        const div = document.createElement('div');
        div.className = 'cv-event';
        div.style.left   = left + 'px';
        div.style.top    = top + 'px';
        div.style.width  = (CV_COL_WIDTH - 4) + 'px';
        div.style.height = height + 'px';
        div.style.background  = ev.backgroundColor;
        div.style.borderLeft  = '5px solid ' + (ev.borderColor || '#333');
        div.innerHTML = `<b>${inicio.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</b>${cvEscapeHtml(ev.title)}`;
        div.addEventListener('click', function () {
            abrirModalCita(ev.id, ev.title, inicio, ev.extendedProps);
        });
        grid.appendChild(div);
    });

    renderVistaPorDoctorLista(doctores, weekEnd);
}

// ── Vista por Doctor: lista de tarjetas apiladas (móvil) ────────────────
function renderVistaPorDoctorLista(doctores, weekEnd) {
    const cont = document.getElementById('cvList');
    cont.innerHTML = '';

    const hoy = new Date();
    const citas = eventosAll
        .filter(function (ev) {
            const inicio = new Date(ev.start);
            if (inicio < cvWeekStart || inicio >= weekEnd) return false;
            return doctores.some(d => d.nombre === ev.extendedProps.medico);
        })
        .sort((a, b) => new Date(a.start) - new Date(b.start));

    if (citas.length === 0) {
        cont.innerHTML = '<div class="cv-list-empty">Sin citas en este rango.</div>';
        return;
    }

    let diaActual = null;
    citas.forEach(function (ev) {
        const inicio = new Date(ev.start);
        const p      = ev.extendedProps;

        const claveDia = inicio.toDateString();
        if (claveDia !== diaActual) {
            diaActual = claveDia;
            const esHoy = mismaFecha(inicio, hoy);
            const head  = document.createElement('div');
            head.className = 'cv-list-day' + (esHoy ? ' cv-today' : '');
            head.textContent = `${CV_DIAS[inicio.getDay()]} ${inicio.getDate()} de ${CV_MESES[inicio.getMonth()]}`;
            cont.appendChild(head);
        }

        const card = document.createElement('div');
        card.className = 'cv-list-card';
        card.innerHTML = `
            <div class="cv-list-color" style="background:${ev.borderColor || '#333'}"></div>
            <div class="cv-list-body" style="background:${ev.backgroundColor}">
                <div class="cv-list-time">${inicio.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</div>
                <div class="cv-list-paciente">${cvEscapeHtml(ev.title)}</div>
                <div class="cv-list-sub">${cvEscapeHtml(p.consulta || '')} — Dr. ${cvEscapeHtml(p.medico || '')}</div>
                <div class="cv-list-sub">L: ${cvEscapeHtml(p.agencia || 'Sin asignar')}</div>
            </div>
        `;
        card.addEventListener('click', function () {
            abrirModalCita(ev.id, ev.title, inicio, p);
        });
        cont.appendChild(card);
    });
}

// ── Select2 en modal Agendar ─────────────────────────────────────────
$(document).ready(function () {
    $('#editModal').on('shown.bs.modal', function () {
        $('.select-busqueda').select2({
            theme: 'bootstrap-5',
            dropdownParent: $('#editModal'),
            width: '100%'
        });
    });
});
</script>

</body>
</html>

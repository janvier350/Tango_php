<?php
ob_start();
session_start();
require_once("class/funciones.php");
require_once("class/conexionBD.php");
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

$query = "SELECT
            A.IDCITA,
            CONCAT(B.NOMBRES, ' ', B.APELLIDOS) AS PACIENTE,
            B.TELEFONO,
            C.NOMBRES AS TIPO_CONSULTA,
            A.FECHA_CITA,
            A.HORA_INICIO,
            A.HORA_FIN,
            A.ESTADO_CITA,
            A.COMENTARIO,
            CONCAT(D.NOMBRES, ' ', D.APELLIDOS) AS DOCTOR
          FROM AG_CITA A
          INNER JOIN AG_PACIENTE B     ON A.IDPACIENTE      = B.IDPACIENTE
          INNER JOIN AG_TIPOCONSULTA C ON A.IDTIPOCONSULTA  = C.IDTIPOCONSULTA
          INNER JOIN ADM_DOCTOR D      ON A.IDDOCTOR        = D.IDDOCTOR
          WHERE A.ESTADO = 'A'";

$resultado = $conexion->query($query);
$eventos   = array();

while ($row = $resultado->fetch_assoc()) {
    switch($row['ESTADO_CITA']) {
        case 'Confirmada': $color = '#28a745'; break; // Verde
        case 'Pendiente':  $color = '#ffc107'; break; // Amarillo
        case 'A':          $color = '#6f42c1'; break; // Morado - Atendida
        case 'Cancelada':
        case 'Cancelado':
        case 'Atrasado':   $color = '#dc3545'; break; // Rojo
        default:           $color = '#007bff'; break; // Azul
    }

    $eventos[] = array(
        'id'              => $row['IDCITA'],
        'title'           => $row['PACIENTE'],
        'start'           => $row['FECHA_CITA'] . 'T' . $row['HORA_INICIO'],
        'end'             => $row['FECHA_CITA'] . 'T' . $row['HORA_FIN'],
        'backgroundColor' => $color,
        'borderColor'     => $color,
        'textColor'       => '#ffffff',
        'extendedProps'   => array(
            'cita'      => $row['ESTADO_CITA'],
            'medico'    => $row['DOCTOR'],
            'consulta'  => $row['TIPO_CONSULTA'],
            'telefono'  => $row['TELEFONO'],
            'comentario'=> $row['COMENTARIO'],
        )
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
        /* Leyenda de colores */
        .leyenda-calendario { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 10px; font-size: 0.8rem; }
        .leyenda-item { display: flex; align-items: center; gap: 5px; }
        .leyenda-dot { width: 12px; height: 12px; border-radius: 50%; display: inline-block; }
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
                            <i class="nav-link-icon fa fa-database"></i> Estadistica
                        </a>
                    </li>
                    <li class="dropdown nav-item">
                        <a href="javascript:void(0);" class="nav-link">
                            <i class="nav-link-icon fa fa-cog"></i> Configuracion
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
                                        <button type="button" class="dropdown-item">Perfil de Usuario</button>
                                        <button type="button" class="dropdown-item">Configuración</button>
                                        <div class="dropdown-divider"></div>
                                        <a href="salir.php" class="dropdown-item">Cerrar Sesión</a>
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
                                <div class="page-title-subheading">Citas agendadas.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="main-card mb-3 card">
                    <div class="card-body">
                        <!-- Leyenda de colores -->
                        <div class="leyenda-calendario">
                            <span class="leyenda-item"><span class="leyenda-dot" style="background:#ffc107"></span> Pendiente</span>
                            <span class="leyenda-item"><span class="leyenda-dot" style="background:#28a745"></span> Confirmada</span>
                            <span class="leyenda-item"><span class="leyenda-dot" style="background:#6f42c1"></span> Atendida</span>
                            <span class="leyenda-item"><span class="leyenda-dot" style="background:#dc3545"></span> Cancelada</span>
                        </div>
                        <div id="calendar1"></div>
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
                <h5 class="modal-title">Agendar Cita</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="insertCita" method="POST" action="class/Insert_cita.php">
                    <div class="mb-3">
                        <label class="form-label">Fecha Consulta</label>
                        <input type="date" class="form-control" name="fechafactura" id="fechafactura">
                        <script>document.getElementById('fechafactura').value = new Date().toISOString().substring(0, 10);</script>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Paciente</label>
                        <select class="form-select select-busqueda" name="IdPaciente" required>
                            <option value="">Buscar paciente...</option>
                            <?php
                            $queryP = $conexion->query("SELECT IDPACIENTE, NOMBRES, APELLIDOS FROM AG_PACIENTE WHERE ESTADO = 'A' ORDER BY NOMBRES");
                            while ($v = $queryP->fetch_assoc()):
                            ?>
                            <option value="<?php echo $v['IDPACIENTE']; ?>"><?php echo htmlspecialchars($v['NOMBRES'].' '.$v['APELLIDOS']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Hora Inicio</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-clock"></i></span>
                            <select class="form-select select-busqueda" name="timeIni" id="timeIni" required>
                                <option value="">Seleccione hora...</option>
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
                        <label class="form-label">Tipo Consulta</label>
                        <select class="form-select select-busqueda" name="Idconsulta" required>
                            <option value="">Buscar tipo consulta...</option>
                            <?php
                            $queryC = $conexion->query("SELECT IDTIPOCONSULTA, NOMBRES FROM AG_TIPOCONSULTA WHERE ESTADO = 'A' ORDER BY NOMBRES");
                            while ($v = $queryC->fetch_assoc()):
                            ?>
                            <option value="<?php echo $v['IDTIPOCONSULTA']; ?>"><?php echo htmlspecialchars($v['NOMBRES']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Doctor</label>
                        <select class="form-select select-busqueda" name="IdDoctor" required>
                            <option value="">Buscar doctor...</option>
                            <?php
                            $sqlDoctores = "SELECT D.IDDOCTOR, D.NOMBRES, D.APELLIDOS
                                            FROM ADM_DOCTOR D
                                            WHERE D.ESTADO = 'A'
                                              AND EXISTS (
                                                  SELECT 1 FROM ADM_USUARIO U
                                                  INNER JOIN ADM_ROL R ON U.IDADM_ROL = R.IDADM_ROL
                                                  WHERE R.CARGO = 'DOCTOR'
                                                    AND U.ESTADO = 'A'
                                                    AND U.NOMBRES = D.NOMBRES
                                                    AND U.APELLIDOS = D.APELLIDOS
                                              )
                                            ORDER BY D.NOMBRES";
                            $queryD = $conexion->query($sqlDoctores);
                            while ($v = $queryD->fetch_assoc()):
                            ?>
                            <option value="<?php echo $v['IDDOCTOR']; ?>"><?php echo htmlspecialchars($v['NOMBRES'].' '.$v['APELLIDOS']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-calendar-check"></i> Agendar
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
                <h5 class="modal-title">Gestión de Cita</h5>
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
        'Confirmada': ['bg-success',          'Confirmada'],
        'Pendiente':  ['bg-warning text-dark', 'Pendiente'],
        'A':          ['bg-purple',            'Atendida'],
        'Cancelada':  ['bg-danger',            'Cancelada'],
        'Cancelado':  ['bg-danger',            'Cancelado'],
        'Atrasado':   ['bg-danger',            'Atrasado'],
    };
    const [cls, label] = map[estado] || ['bg-secondary', estado];
    return `<span class="badge ${cls}" style="${cls==='bg-purple'?'background:#6f42c1':''}">${label}</span>`;
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

// ── FullCalendar ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {

    var calendarEl = document.getElementById('calendar1');
    var calendar   = new FullCalendar.Calendar(calendarEl, {
        locale: 'es',
        headerToolbar: {
            left:   'prev,next today',
            center: 'title',
            right:  'dayGridMonth,timeGridWeek,timeGridDay'
        },
        initialView: 'dayGridMonth',
        events: <?php echo json_encode($eventos); ?>,

        eventClick: function (info) {
            const p    = info.event.extendedProps;
            const est  = p.cita || 'Pendiente';
            const atendida = est === 'A';

            // Limpiar y formatear teléfono para WhatsApp
            let tel = p.telefono ? p.telefono.replace(/\D/g, '') : '';
            if (tel.length === 9 && tel.startsWith('9'))        tel = '593' + tel;
            else if (tel.length === 10 && tel.startsWith('09')) tel = '593' + tel.substring(1);

            const msg = encodeURIComponent(
                `Hola, le saludamos de SROSS Nutritions. Le recordamos su cita de ${p.consulta} para el día ` +
                `${info.event.start.toLocaleDateString()} a las ` +
                `${info.event.start.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}. ¿Nos confirma su asistencia?`
            );

            document.getElementById('eventDetails').innerHTML = `
                <div class="row">
                    <div class="col-md-8">
                        <p><strong>Paciente:</strong> ${info.event.title}</p>
                        <p><strong>Teléfono:</strong> ${p.telefono || 'No registrado'}</p>
                        <p><strong>Inicio:</strong> ${info.event.start.toLocaleString()}</p>
                        <p><strong>Doctor:</strong> ${p.medico}</p>
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
            document.getElementById('idCita').value     = info.event.id;
            document.getElementById('estadoCita').value = est;

            const btnAtender   = document.getElementById('btnAtender');
            const btnHistorial = document.getElementById('btnHistorial');
            const btnReagendar = document.getElementById('btnReagendar');
            const btnConfirmar = document.getElementById('btnConfirmar');
            const btnCancelar  = document.getElementById('btnCancelar');

            // Cerrar panel reagendar al abrir una nueva cita
            cerrarReagendar();

            if (atendida) {
                btnAtender.classList.add('d-none');
                btnReagendar.classList.add('d-none');
                btnConfirmar.classList.add('d-none');
                btnCancelar.classList.add('d-none');
                btnHistorial.href = `historial_atenciones.php?q=${encodeURIComponent(info.event.title)}`;
                btnHistorial.classList.remove('d-none');
            } else {
                btnAtender.classList.remove('d-none');
                btnReagendar.classList.remove('d-none');
                btnConfirmar.classList.remove('d-none');
                btnCancelar.classList.remove('d-none');
                btnHistorial.classList.add('d-none');
            }

            new bootstrap.Modal(document.getElementById('eventModal')).show();
        }
    });

    calendar.render();
});

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

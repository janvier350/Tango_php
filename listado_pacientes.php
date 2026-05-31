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

$q = trim($_GET['q'] ?? '');
$qEsc = $conexion->real_escape_string($q);

$where = "WHERE P.ESTADO = 'A'";
if ($q !== '') {
    $where .= " AND (P.NOMBRES LIKE '%$qEsc%'
                  OR P.APELLIDOS LIKE '%$qEsc%'
                  OR P.CEDULA   LIKE '%$qEsc%'
                  OR P.TELEFONO LIKE '%$qEsc%')";
}

$sql = "SELECT
            P.IDPACIENTE,
            P.NOMBRES,
            P.APELLIDOS,
            P.CEDULA,
            P.TELEFONO,
            P.EMAIL,
            COUNT(DISTINCT C.IDCITA)       AS TOTAL_CITAS,
            SUM(C.ESTADO_CITA = 'A')       AS ATENDIDAS,
            MAX(C.FECHA_CITA)              AS ULTIMA_CITA
        FROM AG_PACIENTE P
        LEFT JOIN AG_CITA C ON C.IDPACIENTE = P.IDPACIENTE AND C.ESTADO = 'A'
        $where
        GROUP BY P.IDPACIENTE
        ORDER BY P.APELLIDOS, P.NOMBRES";

$result = $conexion->query($sql);
$totalRows = $result ? $result->num_rows : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Listado de Pacientes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="./main.css" rel="stylesheet">
    <script src="js/jquery.min.js"></script>
    <style>
        .avatar {
            width: 36px; height: 36px; border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff; font-weight: 700; font-size: .85rem;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .badge-atendida { background: #6f42c1; }
        #modalHistorial .table th { font-size: .78rem; text-transform: uppercase; color: #888; }
        #modalHistorial .table td { font-size: .85rem; vertical-align: middle; }
        .stat-pill {
            display: inline-flex; align-items: center; gap: 4px;
            background: #f0f4ff; border-radius: 20px;
            padding: 2px 10px; font-size: .8rem; font-weight: 600;
        }
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
                                <div class="widget-heading"><?php echo htmlspecialchars($_SESSION['nombres'] ?? ''); ?></div>
                                <div class="widget-subheading"><?php echo htmlspecialchars($_SESSION['rol'] ?? ''); ?></div>
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
                                <i class="pe-7s-users icon-gradient bg-plum-plate"></i>
                            </div>
                            <div>
                                Listado de Pacientes
                                <div class="page-title-subheading">
                                    Busca, consulta historial y gestiona pacientes
                                </div>
                            </div>
                        </div>
                        <div class="page-title-actions">
                            <a href="PNC_PacienteCrear.php" class="btn btn-primary btn-sm">
                                <i class="bi bi-person-plus-fill me-1"></i> Nuevo Paciente
                            </a>
                        </div>
                    </div>
                </div>

                <!-- BUSCADOR -->
                <div class="card shadow-sm mb-3">
                    <div class="card-body py-2">
                        <form method="GET" class="row g-2 align-items-center">
                            <div class="col">
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0">
                                        <i class="bi bi-search text-muted"></i>
                                    </span>
                                    <input type="text" name="q" class="form-control border-start-0"
                                           placeholder="Buscar por nombre, apellido, cédula o teléfono…"
                                           value="<?php echo htmlspecialchars($q); ?>" autofocus>
                                </div>
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-primary">Buscar</button>
                                <?php if ($q !== ''): ?>
                                    <a href="listado_pacientes.php" class="btn btn-outline-secondary ms-1">Limpiar</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- TABLA -->
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center py-2">
                        <span style="font-size:.78rem;text-transform:uppercase;letter-spacing:.06em;color:#6c757d;font-weight:600;">
                            <i class="bi bi-people-fill me-1"></i>
                            <?php echo $totalRows; ?> paciente<?php echo $totalRows !== 1 ? 's' : ''; ?>
                            <?php if ($q !== ''): ?>
                                — resultado<?php echo $totalRows !== 1 ? 's' : ''; ?> para
                                "<strong><?php echo htmlspecialchars($q); ?></strong>"
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:36px;"></th>
                                        <th>Paciente</th>
                                        <th>Cédula</th>
                                        <th>Teléfono</th>
                                        <th>Correo</th>
                                        <th class="text-center">Citas</th>
                                        <th class="text-center">Atendidas</th>
                                        <th>Última cita</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if ($result && $result->num_rows > 0): ?>
                                    <?php while ($p = $result->fetch_assoc()):
                                        $iniciales = strtoupper(
                                            substr($p['NOMBRES']   ?? '', 0, 1) .
                                            substr($p['APELLIDOS'] ?? '', 0, 1)
                                        );
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="avatar"><?php echo htmlspecialchars($iniciales); ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold" style="font-size:.9rem;">
                                                <?php echo htmlspecialchars($p['APELLIDOS'] . ', ' . $p['NOMBRES']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?php echo htmlspecialchars($p['CEDULA'] ?? '—'); ?></small>
                                        </td>
                                        <td>
                                            <small><?php echo htmlspecialchars($p['TELEFONO'] ?? '—'); ?></small>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?php echo htmlspecialchars($p['EMAIL'] ?? '—'); ?></small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary"><?php echo (int)$p['TOTAL_CITAS']; ?></span>
                                        </td>
                                        <td class="text-center">
                                            <?php if ((int)$p['ATENDIDAS'] > 0): ?>
                                                <span class="badge badge-atendida"><?php echo (int)$p['ATENDIDAS']; ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($p['ULTIMA_CITA']): ?>
                                                <small><?php echo date('d/m/Y', strtotime($p['ULTIMA_CITA'])); ?></small>
                                            <?php else: ?>
                                                <small class="text-muted">Sin citas</small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-3">
                                            <button class="btn btn-sm btn-outline-primary py-0 px-2"
                                                    onclick="verHistorial(<?php echo $p['IDPACIENTE']; ?>, '<?php echo htmlspecialchars(addslashes($p['APELLIDOS'] . ', ' . $p['NOMBRES'])); ?>')"
                                                    title="Ver historial">
                                                <i class="bi bi-clock-history"></i> Historial
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-5 text-muted">
                                            <i class="bi bi-person-x fs-2 d-block mb-2"></i>
                                            <?php echo $q !== '' ? 'No se encontraron pacientes para "' . htmlspecialchars($q) . '".' : 'No hay pacientes registrados.'; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div><!-- /app-main__inner -->
        </div><!-- /app-main__outer -->
    </div><!-- /app-main -->
</div><!-- /app-container -->

<!-- ══ MODAL HISTORIAL ══════════════════════════════════════════════════ -->
<div class="modal fade" id="modalHistorial" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2" style="background:#1a1a2e;">
                <h6 class="modal-title text-white mb-0">
                    <i class="bi bi-person-lines-fill me-2"></i>
                    <span id="modalPacienteNombre"></span>
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" id="modalHistorialBody">
                <div class="text-center py-5 text-muted">
                    <div class="spinner-border spinner-border-sm me-2"></div> Cargando…
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ══ MODAL INFORME ════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalInforme" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title"><i class="bi bi-file-earmark-text me-2"></i>Informe de Atención</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="cuerpoInforme">
                <div class="text-center py-4"><div class="spinner-border spinner-border-sm"></div></div>
            </div>
            <div class="modal-footer py-2">
                <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-printer"></i> Imprimir
                </button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script type="text/javascript" src="./assets/scripts/main.js"></script>
<script>
function verHistorial(idPaciente, nombre) {
    document.getElementById('modalPacienteNombre').textContent = nombre;
    document.getElementById('modalHistorialBody').innerHTML =
        '<div class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div> Cargando…</div>';

    new bootstrap.Modal(document.getElementById('modalHistorial')).show();

    $.get('get_historial_paciente.php', { id: idPaciente })
        .done(function(html) {
            document.getElementById('modalHistorialBody').innerHTML = html;
        })
        .fail(function(xhr) {
            document.getElementById('modalHistorialBody').innerHTML =
                '<div class="alert alert-danger m-3">Error al cargar el historial (HTTP ' + xhr.status + '). Revisa los logs de PHP.</div>';
        });
}

function verInforme(idHistorial) {
    document.getElementById('cuerpoInforme').innerHTML =
        '<div class="text-center py-4"><div class="spinner-border spinner-border-sm"></div></div>';
    new bootstrap.Modal(document.getElementById('modalInforme')).show();
    $.get('get_informe_html.php', { id: idHistorial })
        .done(function(html) {
            document.getElementById('cuerpoInforme').innerHTML = html;
        })
        .fail(function(xhr) {
            document.getElementById('cuerpoInforme').innerHTML =
                '<div class="alert alert-danger m-3">No se pudo cargar el informe (HTTP ' + xhr.status + ').</div>';
        });
}
</script>
</body>
</html>

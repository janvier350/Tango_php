<?php
$rol       = $_SESSION['rol'] ?? '';
$esSistema = ($rol === 'SISTEMA');
$esDoctor  = ($rol === 'DOCTOR');
$esUsuario = ($rol === 'USUARIO');
?>

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

<div class="scrollbar-sidebar">
    <div class="app-sidebar__inner">
        <ul class="vertical-nav-menu">

            <!-- ══ DASHBOARD ══════════════════════════════════════════ -->
            <li class="app-sidebar__heading">Dashboard</li>
            <li>
                <a href="home.php" class="mm-active">
                    <i class="metismenu-icon pe-7s-rocket"></i> General
                </a>
            </li>

            <!-- ══ AGENDA ══════════════════════════════════════════════ -->
            <li class="app-sidebar__heading">Agenda</li>
            <li>
                <a href="SCH_Calendar.php">
                    <i class="metismenu-icon pe-7s-display2"></i> Calendario
                </a>
            </li>
            <li>
                <a href="Agenda_Pendientes.php">
                    <i class="metismenu-icon pe-7s-date"></i> Pendientes
                </a>
            </li>
            <?php if ($esSistema || $esDoctor): ?>
            <li>
                <a href="historial_atenciones.php">
                    <i class="metismenu-icon pe-7s-date"></i> Atendidas
                </a>
            </li>
            <?php endif; ?>
            <?php if ($esSistema): ?>
            <li>
                <a href="VTA_Concretado.php">
                    <i class="metismenu-icon pe-7s-diamond"></i> Canceladas
                </a>
            </li>
            <?php endif; ?>
            <?php if ($esSistema || $esDoctor): ?>
            <li>
                <a href="Enviar_Notificacion.php">
                    <i class="metismenu-icon pe-7s-mail"></i> Enviar Notificación
                </a>
            </li>
            <?php endif; ?>

            <!-- ══ PACIENTES ═══════════════════════════════════════════ -->
            <li class="app-sidebar__heading">Pacientes</li>
            <li>
                <a href="#">
                    <i class="metismenu-icon pe-7s-add-user"></i>
                    Pacientes
                    <i class="metismenu-state-icon pe-7s-angle-down caret-left"></i>
                </a>
                <ul>
                    <li>
                        <a href="listado_pacientes.php">
                            <i class="metismenu-icon"></i> Listado de Pacientes
                        </a>
                    </li>
                    <li>
                        <a href="PNC_PacienteCrear.php">
                            <i class="metismenu-icon"></i> Crear Paciente
                        </a>
                    </li>
                    <?php if ($esSistema || $esDoctor): ?>
                    <li>
                        <a href="visor_plantillas.php">
                            <i class="metismenu-icon"></i> Listado Plantillas
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </li>

            <!-- ══ SÓLO SISTEMA ════════════════════════════════════════ -->
            <?php if ($esSistema): ?>
            <li>
                <a href="#">
                    <i class="metismenu-icon pe-7s-users"></i>
                    Doctor
                    <i class="metismenu-state-icon pe-7s-angle-down caret-left"></i>
                </a>
                <ul>
                    <li>
                        <a href="PNC_DoctorCrear.php">
                            <i class="metismenu-icon"></i> Crear Nuevo
                        </a>
                    </li>
                </ul>
            </li>
            <li>
                <a href="#">
                    <i class="metismenu-icon pe-7s-note2"></i>
                    Código CIE-10
                    <i class="metismenu-state-icon pe-7s-angle-down caret-left"></i>
                </a>
                <ul>
                    <li>
                        <a href="PNC_CIE-10Crear.php">
                            <i class="metismenu-icon"></i> Crear Nuevo CIE-10
                        </a>
                    </li>
                </ul>
            </li>
            <?php endif; ?>

            <!-- ══ BILLS (solo SISTEMA) ════════════════════════════════ -->
            <?php if ($esSistema): ?>
            <li class="app-sidebar__heading">Bills</li>
            <li>
                <a href="#">
                    <i class="metismenu-icon pe-7s-news-paper"></i>
                    Registrar
                    <i class="metismenu-state-icon pe-7s-angle-down caret-left"></i>
                </a>
                <ul>
                    <li>
                        <a href="BILLS_FacturaCrear.php">
                            <i class="metismenu-icon"></i> Registrar Bills
                        </a>
                    </li>
                    <li>
                        <a href="BILLS_FacturaAbonos.php">
                            <i class="metismenu-icon"></i> Registrar Abonos
                        </a>
                    </li>
                    <li>
                        <a href="DashBoardReportesCuentasPorCobrar.php">
                            <i class="metismenu-icon"></i> Reportes
                        </a>
                    </li>
                </ul>
            </li>
            <?php endif; ?>

            <!-- ══ REPORTES ════════════════════════════════════════════ -->
            <li class="app-sidebar__heading">Reportes</li>
            <li>
                <a href="RPT_Vendedor_Vta.php">
                    <i class="metismenu-icon pe-7s-monitor"></i> Mis Citas
                </a>
            </li>
            <?php if ($esSistema || $esDoctor): ?>
            <li>
                <a href="visor_plantillas.php">
                    <i class="metismenu-icon pe-7s-note2"></i> Plantillas
                </a>
            </li>
            <?php endif; ?>
            <?php if ($esSistema): ?>
            <li>
                <a href="RPT_General_vta.php">
                    <i class="metismenu-icon pe-7s-graph"></i> Citas Generales
                </a>
            </li>
            <?php endif; ?>

            <!-- ══ PANEL DE CONTROL (solo SISTEMA) ═══════════════════ -->
            <?php if ($esSistema): ?>
            <li class="app-sidebar__heading">Panel de Control</li>
            <li>
                <a href="#">
                    <i class="metismenu-icon pe-7s-users"></i>
                    Usuarios
                    <i class="metismenu-state-icon pe-7s-angle-down caret-left"></i>
                </a>
                <ul>
                    <li>
                        <a href="PNC_UsuarioCrear.php">
                            <i class="metismenu-icon"></i> Crear Nuevo
                        </a>
                    </li>
                    <li>
                        <a href="PNC_UsuarioListado.php">
                            <i class="metismenu-icon"></i> Listado
                        </a>
                    </li>
                </ul>
            </li>
            <?php endif; ?>

            <!-- ══ CUENTA ══════════════════════════════════════════════ -->
            <li class="app-sidebar__heading">Cuenta</li>
            <li>
                <a href="salir.php">
                    <i class="metismenu-icon pe-7s-power"></i> Cerrar Sesión
                </a>
            </li>

        </ul>
    </div>
</div>

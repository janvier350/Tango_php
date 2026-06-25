<?php
/**
 * Traducción ES/EN muy simple, basada en el texto español como clave.
 * t('Texto en español') devuelve la traducción al inglés si el idioma
 * activo en sesión es 'en' y existe una entrada en el diccionario;
 * si no hay traducción registrada, devuelve el texto original (en
 * español), por lo que nunca rompe una pantalla que aún no se tradujo.
 */

$I18N_EN = [
    // Login (index.php)
    'Iniciar Sesión'                          => 'Log In',
    'Bienvenido'                              => 'Welcome',
    'Ingresa tus credenciales para continuar' => 'Enter your credentials to continue',
    'Usuario'                                 => 'Username',
    'Contraseña'                              => 'Password',
    '¿Olvidaste tu contraseña?'               => 'Forgot your password?',

    // Menú lateral (menu_adm.php)
    'Dashboard'             => 'Dashboard',
    'General'               => 'Overview',
    'Agenda'                => 'Schedule',
    'Calendario'            => 'Calendar',
    'Pendientes'            => 'Pending',
    'Atendidas'             => 'Attended',
    'Canceladas'            => 'Cancelled',
    'Enviar Notificación'   => 'Send Notification',
    'Pacientes'             => 'Patients',
    'Listado de Pacientes'  => 'Patient List',
    'Crear Paciente'        => 'Add Patient',
    'Listado Plantillas'    => 'Templates List',
    'Doctor'                => 'Doctor',
    'Crear Nuevo'           => 'Create New',
    'Código CIE-10'         => 'ICD-10 Code',
    'Crear Nuevo CIE-10'    => 'Create New ICD-10',
    'Tipos de Consulta'     => 'Consultation Types',
    'Bills'                 => 'Bills',
    'Registrar'             => 'Register',
    'Registrar Bills'       => 'Register Bills',
    'Registrar Abonos'      => 'Register Payments',
    'Reportes'              => 'Reports',
    'Mis Citas'             => 'My Appointments',
    'Plantillas'            => 'Templates',
    'Citas Generales'       => 'All Appointments',
    'Panel de Control'      => 'Control Panel',
    'Usuarios'              => 'Users',
    'Listado'               => 'List',
    'Cerrar Sesión'         => 'Log Out',

    // Dashboard (home.php)
    'Citas hoy'                          => 'Appointments today',
    'Resumen general del sistema'        => 'General system overview',
    'Atendidas (mes)'                    => 'Attended (month)',
    'Citas — últimos 7 días'             => 'Appointments — last 7 days',
    'Este mes'                           => 'This month',
    'Confirmadas'                        => 'Confirmed',
    'Últimas atenciones'                 => 'Recent visits',
    'Sin atenciones registradas.'        => 'No visits recorded.',
    'Citas de hoy'                       => "Today's appointments",
    'Ir al calendario'                   => 'Go to calendar',
    'Tipo'                               => 'Type',
    'No hay citas programadas para hoy.' => 'No appointments scheduled for today.',
    'Próximas citas (7 días)'            => 'Upcoming appointments (7 days)',
    'Sin citas en los próximos 7 días.'  => 'No appointments in the next 7 days.',
    'Citas'                              => 'Appointments',
    'Paciente'                           => 'Patient',
    'Hora'                               => 'Time',
    'Estado'                             => 'Status',
    'Fecha'                              => 'Date',

    // Calendario (SCH_Calendar.php)
    'Agendar Cita'             => 'Schedule Appointment',
    'Fecha Consulta'           => 'Appointment Date',
    'Hora Inicio'              => 'Start Time',
    'Seleccione hora...'       => 'Select a time...',
    'Tipo Consulta'            => 'Consultation Type',
    'Buscar tipo consulta...'  => 'Search consultation type...',
    'Buscar paciente...'       => 'Search patient...',
    'Buscar doctor...'         => 'Search doctor...',
    'Location'                 => 'Location',
    'Seleccione location...'   => 'Select location...',
    'Agendar'                  => 'Schedule',
    'Gestión de Cita'          => 'Manage Appointment',
    'Doctores'                 => 'Doctors',
    'Hoy'                      => 'Today',
    'Estadistica'              => 'Statistics',
    'Configuracion'            => 'Settings',
    'Perfil de Usuario'        => 'User Profile',
    'Configuración'            => 'Settings',
];

function idiomaActual() {
    $l = $_SESSION['lang'] ?? 'es';
    return $l === 'en' ? 'en' : 'es';
}

function t($texto) {
    global $I18N_EN;
    if (idiomaActual() !== 'en') {
        return $texto;
    }
    return $I18N_EN[$texto] ?? $texto;
}

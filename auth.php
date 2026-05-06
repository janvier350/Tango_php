<?php
session_start();

/**
 * Verifica si el usuario está autenticado.
 */
function verificar_auth() {
    if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Redirección inteligente al iniciar sesión.
 * Rol 1: Administrador -> Va al Dashboard o Index principal.
 * Rol 2: Usuario -> Va directo a Movimientos.
 */
function redireccionar_segun_rol() {
    if (isset($_SESSION['user_rol'])) {
        $rol = $_SESSION['user_rol'];

        switch ($rol) {
            case 1: // ADMIN
                header("Location: index.php"); // O la página de reportes generales
                break;
            case 2: // USUARIO OPERATIVO
                header("Location: movimientos.php");
                break;
            case 4: // USUARIO control
                header("Location: ../control/control_movimientos.php");
                break;
            default:
                header("Location: ../login.php");
                break;
        }
        exit;
    }
}

/**
 * OPCIONAL: Función para proteger páginas exclusivas de Admin.
 * Úsala en archivos que el Rol 2 NO deba ver.
 */
function solo_admin() {
    verificar_auth();
    if ($_SESSION['user_rol'] != 1) {
        // Si no es admin, lo mandamos a su página de movimientos con un mensaje
        header("Location: ../movimientos.php?error=no_admin");
        exit;
    }
}

// Función para cerrar sesión
if (isset($_GET['logout'])) {
    // Aseguramos que la sesión esté activa para poder destruirla
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Limpiamos y destruimos
    session_unset();
    session_destroy();

    // Opción A: Ruta absoluta desde la raíz del dominio (Recomendada)
    // Esto funcionará sin importar si el script se llama desde /control/ o la raíz
    header("Location: /Flujo_caja/login.php");

    /* Opción B: URL Completa (Por si tienes configuraciones de .htaccess complejas)
    header("Location: https://www.buadnet.com.ec/Flujo_caja/login.php"); 
    */

    exit;
}
?>
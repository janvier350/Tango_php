<?php
session_start();

/**
 * Verifica si el usuario est�� autenticado.
 */
function verificar_auth() {
    if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Indica si una columna existe en una tabla (para despliegues seguros
 * mientras un ALTER TABLE aun no se ha ejecutado en la base).
 */
function col_existe($conn, $tabla, $col) {
    $t = $conn->real_escape_string($tabla);
    $c = $conn->real_escape_string($col);
    $r = $conn->query("SELECT 1 FROM information_schema.COLUMNS
                       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$t'
                       AND COLUMN_NAME = '$c' LIMIT 1");
    return $r && $r->num_rows > 0;
}

/**
 * Redirecci��n inteligente al iniciar sesi��n.
 * Rol 1: Administrador -> Va al Dashboard o Index principal.
 * Rol 2: Usuario -> Va directo a Movimientos.
 */
function redireccionar_segun_rol() {
    if (isset($_SESSION['user_rol'])) {
        $rol = $_SESSION['user_rol'];

        switch ($rol) {
            case 1: // ADMIN
                header("Location: index.php"); // O la p��gina de reportes generales
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
 * OPCIONAL: Funci��n para proteger p��ginas exclusivas de Admin.
 * �0�3sala en archivos que el Rol 2 NO deba ver.
 */
function solo_admin() {
    verificar_auth();
    if ($_SESSION['user_rol'] != 1) {
        // Si no es admin, lo mandamos a su p��gina de movimientos con un mensaje
        header("Location: ../movimientos.php?error=no_admin");
        exit;
    }
}

// Funci��n para cerrar sesi��n
if (isset($_GET['logout'])) {
    // Aseguramos que la sesi��n est�� activa para poder destruirla
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Limpiamos y destruimos
    session_unset();
    session_destroy();

    // Opci��n A: Ruta absoluta desde la ra��z del dominio (Recomendada)
    // Esto funcionar�� sin importar si el script se llama desde /control/ o la ra��z
    header("Location: /Tango/login.php");

    /* Opci��n B: URL Completa (Por si tienes configuraciones de .htaccess complejas)
    header("Location: https://www.buadnet.com.ec/Tango/login.php");
    */

    exit;
}
?>
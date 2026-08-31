<?php
// Bufferizar la salida desde el inicio: evita que cualquier espacio o salto de
// línea suelto (en este u otro include) rompa los header()/redirect y provoque
// ERR_HTTP2_PROTOCOL_ERROR al registrar, revisar o resolver.
if (function_exists('ob_get_level') && ob_get_level() === 0) { ob_start(); }
// --- CONFIGURACIÓN DE CONEXIÓN ---
// Nota: se conecta primero por "localhost" (socket local, rápido y estable).
// Conectar por el dominio público hace que cada request salga por DNS/firewall
// del hosting, lo que causaba cortes intermitentes (ERR_CONNECTION_CLOSED).
// Si localhost no estuviera disponible, cae de vuelta a las otras opciones.
$user = "buadnetc_flujo";
$pass = "]h(N{WS4ep[,}k8E";
$db   = "buadnetc_flujo_caja";

$hosts_candidatos = ["localhost", "127.0.0.1", "buadnet.com.ec"];

// Manejar los errores de conexión manualmente (sin excepciones) para poder
// probar varios hosts.
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }

$conn = null;
$ultimo_error = '';
foreach ($hosts_candidatos as $host) {
    $intento = @new mysqli($host, $user, $pass, $db);
    if ($intento && !$intento->connect_error) {
        $conn = $intento;
        break;
    }
    $ultimo_error = $intento ? $intento->connect_error : 'sin objeto de conexión';
}

// Verificar conexión
if (!$conn) {
    die("Error crítico de conexión: " . htmlspecialchars($ultimo_error));
}

$conn->set_charset("utf8"); // Para que los acentos se vean bien
// Sin etiqueta de cierre "?>" a propósito: evita enviar espacios/saltos de
// línea que romperían los redirect header("Location: ...").

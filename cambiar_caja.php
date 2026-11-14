<?php
require_once 'config.php';
require_once 'auth.php';
verificar_auth();
require_once 'multicaja.php';
require_once 'audit.php';

$target = intval($_GET['id_oficina'] ?? 0);
$ofi = usuario_puede_oficina($conn, $_SESSION['user_id'] ?? 0, $target);

if ($ofi) {
    $_SESSION['oficina_ID']            = (int)$ofi['ID_OFICINA'];
    $_SESSION['oficina']               = $ofi['OFICINA'];
    $_SESSION['oficina_independiente'] = (int)$ofi['ES_INDEPENDIENTE'];
    $_SESSION['oficina_form_simple']   = (int)$ofi['FORM_SIMPLE'];
    registrar_auditoria($conn, 'Sesion', 'EDITO', $target, 'Cambió de caja activa a: ' . $ofi['OFICINA']);
}

// Volver a la página de origen (solo del mismo sitio) o al inicio.
$dest = 'index.php';
if (!empty($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_HOST'])
    && strpos($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST']) !== false
    && strpos($_SERVER['HTTP_REFERER'], 'cambiar_caja.php') === false) {
    $dest = $_SERVER['HTTP_REFERER'];
}
header("Location: $dest");
exit;

<?php
require_once 'config.php';
require_once 'auth.php';
verificar_auth();
require_once 'audit.php';

// NOTA: El borrado FÍSICO fue deshabilitado a propósito.
// Eliminar físicamente rompía la continuidad de la secuencia por caja y no
// validaba el estado. Ahora esta acción realiza una ANULACIÓN LÓGICA
// (ESTADO='I'), que conserva el registro y la secuencia, con las mismas
// validaciones que "Anular": solo del propio usuario, no aprobado y activo.
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $motivo = trim($_GET['motivo'] ?? '');
    if ($motivo === '') { $motivo = 'Anulado (acción de borrado reemplazada por anulación)'; }

    $stmt = $conn->prepare("UPDATE movimientos SET ESTADO = 'I'
                            WHERE id = ? AND ID_USUARIO = ?
                              AND (ID_USUARIO_REVISA IS NULL OR ID_USUARIO_REVISA = 0)
                              AND ESTADO = 'A'");
    $stmt->bind_param("ii", $id, $_SESSION["user_id"]);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        registrar_auditoria($conn, 'Movimientos', 'ANULO', $id, $motivo);
    }
}

// Volver a la página de origen (solo dentro del mismo sitio) o a movimientos.
$dest = 'movimientos.php?msg=anulado';
if (!empty($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_HOST'])
    && strpos($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST']) !== false) {
    $dest = $_SERVER['HTTP_REFERER'];
}
header("Location: $dest");
exit;

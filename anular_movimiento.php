<?php
require_once 'config.php';
require_once 'auth.php';
verificar_auth();

// Página de retorno permitida (evita open redirect)
$paginas_permitidas = ['movimientos.php', 'movimientos_mensajeria.php'];
$return = $_GET['return'] ?? 'movimientos.php';
if (!in_array($return, $paginas_permitidas, true)) {
    $return = 'movimientos.php';
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    // Solo se puede anular si es del propio usuario, NO está aprobado y está activo.
    $stmt = $conn->prepare("UPDATE movimientos SET ESTADO = 'I'
                            WHERE id = ? AND ID_USUARIO = ?
                              AND (ID_USUARIO_REVISA IS NULL OR ID_USUARIO_REVISA = 0)
                              AND ESTADO = 'A'");
    $stmt->bind_param("ii", $id, $_SESSION["user_id"]);

    if ($stmt->execute()) {
        // Si no afectó filas, es porque ya estaba aprobado/anulado o no es del usuario
        $msg = ($stmt->affected_rows > 0) ? 'anulado' : 'no_permitido';
        header("Location: $return?msg=$msg");
        exit();
    } else {
        echo "Error al anular: " . $conn->error;
    }
}
?>
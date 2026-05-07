<?php
require_once 'config.php';
require_once 'auth.php';
verificar_auth();

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    // Solo permitimos eliminar si el registro pertenece al usuario (Seguridad básica)
    $stmt = $conn->prepare("DELETE FROM movimientos WHERE id = ? AND ID_USUARIO = ?");
    $stmt->bind_param("ii", $id, $_SESSION["user_id"]);
    
    if ($stmt->execute()) {
        header("Location: movimientos.php?msg=ok");
    } else {
        echo "Error: " . $conn->error;
    }
}
?>
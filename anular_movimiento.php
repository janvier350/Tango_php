<?php
require_once 'config.php';
require_once 'auth.php';
verificar_auth();

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    // Actualizamos el estado a 'I' (Inactivo/Anulado)
    $stmt = $conn->prepare("UPDATE movimientos SET ESTADO = 'I' WHERE id = ? AND ID_USUARIO = ?");
    $stmt->bind_param("ii", $id, $_SESSION["user_id"]);
    
    if ($stmt->execute()) {
        header("Location: movimientos.php?msg=anulado");
        exit();
    } else {
        echo "Error al anular: " . $conn->error;
    }
}
?>
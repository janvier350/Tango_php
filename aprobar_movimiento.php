<?php
require_once 'config.php';
require_once 'auth.php';
verificar_auth();

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $id_jefe = $_SESSION["user_id"];
    $ahora = date("Y-m-d H:i:s");

    $stmt = $conn->prepare("UPDATE movimientos SET ID_USUARIO_REVISA = ?, FECHA_REVISION = ? WHERE id = ?");
    $stmt->bind_param("isi", $id_jefe, $ahora, $id);
    
    if ($stmt->execute()) {
        header("Location: movimientos.php?msg=aprobado");
    } else {
        echo "Error al aprobar: " . $conn->error;
    }
}
?>
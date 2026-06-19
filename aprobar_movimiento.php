<?php
require_once 'config.php';
require_once 'auth.php';
verificar_auth();

// Whitelist de páginas permitidas para regresar tras aprobar (evita open redirect)
$paginas_permitidas = [
    'movimientos.php',
    'movimientos_revisor.php',
    'validar_movimientos_401.php',
    'validar_movimientos_403.php',
    'validar_movimientos_general.php',
];
$return = $_GET['return'] ?? 'movimientos.php';
if (!in_array($return, $paginas_permitidas, true)) {
    $return = 'movimientos.php';
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $id_jefe = $_SESSION["user_id"];
    $ahora = date("Y-m-d H:i:s");

    $stmt = $conn->prepare("UPDATE movimientos SET ID_USUARIO_REVISA = ?, FECHA_REVISION = ? WHERE id = ?");
    $stmt->bind_param("isi", $id_jefe, $ahora, $id);

    if ($stmt->execute()) {
        header("Location: $return?msg=aprobado");
    } else {
        echo "Error al aprobar: " . $conn->error;
    }
}
?>
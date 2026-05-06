<?php
require_once 'config.php';
require_once 'auth.php';
verificar_auth();

if (isset($_POST['registrar_user'])) {
    $cla = password_hash($_POST['clave'], PASSWORD_BCRYPT);
    $stmt = $conn->prepare("INSERT INTO usuarios (nombre_completo, usuario, clave) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $_POST['nombre'], $_POST['usuario'], $cla);
    $stmt->execute();
    header("Location: usuarios.php"); exit;
}

if (isset($_GET['borrar'])) {
    $id = intval($_GET['borrar']);
    if($id != $_SESSION['user_id']) $conn->query("DELETE FROM usuarios WHERE id = $id");
    header("Location: usuarios.php"); exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Usuarios</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header text-center">Registrar Usuario</div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="text" name="nombre" class="form-control mb-2" placeholder="Nombre Completo" required>
                            <input type="text" name="usuario" class="form-control mb-2" placeholder="Login" required>
                            <input type="password" name="clave" class="form-control mb-3" placeholder="Clave" required>
                            <button name="registrar_user" class="btn btn-primary w-100">CREAR ACCESO</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">Accesos Activos</div>
                    <table class="table mb-0">
                        <thead><tr><th>Nombre</th><th>Usuario</th><th class="text-center">Acción</th></tr></thead>
                        <tbody>
                            <?php
                            $res = $conn->query("SELECT * FROM usuarios");
                            while($u = $res->fetch_assoc()):
                            ?>
                            <tr>
                                <td><?php echo $u['nombre_completo']; ?></td>
                                <td><code><?php echo $u['usuario']; ?></code></td>
                                <td class="text-center">
                                    <?php if($u['id'] != $_SESSION['user_id']): ?>
                                        <a href="?borrar=<?php echo $u['id']; ?>" class="text-danger" onclick="return confirm('¿Borrar?')"><i class="bi bi-person-x"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php
require_once 'config.php';
session_start();

$error = "";

if (isset($_POST['login'])) {
    $u = $conn->real_escape_string($_POST['u']);
    $p = $_POST['p'];

    $res = $conn->query("SELECT * FROM usuarios U INNER JOIN CTR_ROL R ON U.ID_ROL = R.ID_ROL INNER JOIN CTR_OFICINA O ON U.ID_CTR_OFICINA = O.ID_OFICINA WHERE usuario = '$u'");
    if ($user = $res->fetch_assoc()) {
        // Verificamos si la clave coincide (funciona con texto plano o hash)
        if (password_verify($p, $user['clave']) || $p == $user['clave']) {
            $_SESSION['auth'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['usuario'];
            $_SESSION['user_rol'] = $user['ID_ROL']; // ID ROL
            $_SESSION['oficina'] = $user['OFICINA']; //  oficina
            $_SESSION['oficina_ROL'] = $user['ROL']; // 
            $_SESSION['oficina_ID'] = $user['ID_CTR_OFICINA']; // 
            header("Location: index.php");
            exit;
        } else { $error = "Contraseña incorrecta"; }
    } else { $error = "Usuario no encontrado"; }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso al Sistema</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            background: #f0f2f5; 
            height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-card { 
            width: 100%; 
            max-width: 400px; 
            padding: 2.5rem; 
            border-radius: 12px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.1); 
            background: #fff; 
        }
        .btn-primary {
            background-color: #2c3e50;
            border: none;
        }
        .btn-primary:hover {
            background-color: #1a252f;
        }
        .text-custom {
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <div class="login-card text-center">
        <h3 class="mb-4 fw-bold text-custom">CONTROL DE CAJA</h3>
        <p class="text-muted small mb-4">Ingrese sus credenciales para acceder</p>

        <?php if($error): ?> 
            <div class="alert alert-danger small"><?php echo $error; ?></div> 
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3 text-start">
                <label class="form-label small fw-bold text-muted">Usuario</label>
                <input type="text" name="u" class="form-control" placeholder="Nombre de usuario" required>
            </div>
            <div class="mb-4 text-start">
                <label class="form-label small fw-bold text-muted">Contraseña</label>
                <input type="password" name="p" class="form-control" placeholder="••••••••" required>
            </div>
            <button name="login" class="btn btn-primary w-100 py-2 fw-bold">ENTRAR</button>
        </form>
    </div>
</body>
</html>
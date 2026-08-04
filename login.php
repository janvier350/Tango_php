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
            $_SESSION['oficina_independiente'] = isset($user['ES_INDEPENDIENTE']) ? (int)$user['ES_INDEPENDIENTE'] : 0;
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
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TANGO | Iniciar Sesión</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: radial-gradient(circle at 30% 20%, #0d3b3e 0%, #04201f 55%, #020f10 100%);
        }
        .login-wrap {
            display: flex;
            width: 100%;
            max-width: 820px;
            min-height: 480px;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 25px 60px rgba(0,0,0,0.45);
        }
        .login-form-side {
            flex: 1 1 55%;
            background: #fff;
            padding: 3rem 2.75rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .login-form-side h3 { color: #0b3d3a; font-weight: 700; }
        .login-form-side p.subtitle { color: #7a8a89; font-size: .9rem; }
        .input-group-icon { position: relative; }
        .input-group-icon i {
            position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #9aa6a5;
        }
        .input-group-icon input {
            padding-left: 40px;
            border-radius: 10px;
            border: 1px solid #e2e8e7;
            padding-top: .7rem; padding-bottom: .7rem;
        }
        .input-group-icon input:focus {
            border-color: #0e7c66;
            box-shadow: 0 0 0 3px rgba(14,124,102,.15);
        }
        .btn-tango {
            background: linear-gradient(135deg, #0e7c66, #0a5c4d);
            border: none; color: #fff; font-weight: 700; border-radius: 10px;
            padding: .75rem;
        }
        .btn-tango:hover { background: linear-gradient(135deg, #0a5c4d, #084c40); color: #fff; }
        .forgot-link { font-size: .8rem; color: #5c6b6a; text-decoration: none; }
        .forgot-link:hover { color: #0e7c66; }
        .login-brand-side {
            flex: 1 1 45%;
            background: linear-gradient(160deg, #0b2e2c 0%, #06181a 100%);
            color: #fff;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 2.5rem;
        }
        .brand-badge {
            width: 84px; height: 84px; border-radius: 50%;
            border: 1px solid rgba(255,255,255,.25);
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 1.25rem;
            background: rgba(255,255,255,.05);
        }
        .brand-badge i { font-size: 2rem; color: #2ee6b8; }
        .brand-title { font-weight: 800; letter-spacing: 3px; font-size: 1.7rem; }
        .brand-sub { font-size: .65rem; letter-spacing: 3px; color: #9fd9c8; margin-bottom: 1.5rem; }
        .brand-divider { width: 40px; height: 2px; background: rgba(255,255,255,.25); margin-bottom: 1.25rem; }
        .brand-tagline { font-size: .8rem; text-align: center; color: #cfe9e2; opacity: .85; line-height: 1.5; }
        @media (max-width: 720px) {
            .login-wrap { flex-direction: column-reverse; max-width: 420px; }
            .login-brand-side { padding: 2rem 1.5rem; }
        }
    </style>
</head>
<body>
    <div class="login-wrap">
        <div class="login-form-side">
            <h3 class="mb-1">Bienvenido</h3>
            <p class="subtitle mb-4">Ingresa tus credenciales para continuar</p>

            <?php if ($error): ?>
                <div class="alert alert-danger small py-2"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3 input-group-icon">
                    <i class="bi bi-person"></i>
                    <input type="text" name="u" class="form-control" placeholder="Usuario" required>
                </div>
                <div class="mb-2 input-group-icon">
                    <i class="bi bi-lock"></i>
                    <input type="password" name="p" class="form-control" placeholder="Contraseña" required>
                </div>
                <div class="text-end mb-4">
                    <a href="#" class="forgot-link">¿Olvidaste tu contraseña?</a>
                </div>
                <button name="login" class="btn btn-tango w-100">Iniciar Sesión</button>
            </form>
        </div>

        <div class="login-brand-side">
            <div class="brand-badge"><i class="bi bi-safe2-fill"></i></div>
            <div class="brand-title">TANGO</div>
            <div class="brand-sub">CONTROL DE CAJA</div>
            <div class="brand-divider"></div>
            <p class="brand-tagline">Sistema de Gestión<br>Financiera y de Caja</p>
        </div>
    </div>
</body>
</html>

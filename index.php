<?php
ob_start();
session_start();
require_once("class/i18n.php");
if (isset($_SESSION["loggedin"])) {
    header("Location: Home.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="<?php echo idiomaActual(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sross Nutritions — Iniciar Sesión</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(ellipse at center, #0264d6 1%, #1c2b5a 100%);
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        .login-card {
            display: flex;
            width: 580px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
        }

        /* ── Panel izquierdo: formulario ─── */
        .login-form-panel {
            flex: 1;
            background: #fff;
            padding: 44px 36px;
        }

        .login-form-panel h2 {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1c2b5a;
            margin-bottom: 6px;
        }

        .login-form-panel p.subtitle {
            font-size: .83rem;
            color: #888;
            margin-bottom: 28px;
        }

        .input-group {
            display: flex;
            align-items: center;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 16px;
            overflow: hidden;
            transition: border-color .2s;
        }

        .input-group:focus-within { border-color: #5a2d82; }

        .input-group .icon {
            width: 46px;
            text-align: center;
            color: #aaa;
            font-size: .95rem;
            flex-shrink: 0;
        }

        .input-group input {
            flex: 1;
            border: none;
            outline: none;
            padding: 13px 12px 13px 0;
            font-size: .9rem;
            color: #333;
            background: transparent;
        }

        .login-extras {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            font-size: .8rem;
        }

        .login-extras a { color: #888; text-decoration: none; }
        .login-extras a:hover { color: #5a2d82; }

        .btn-login {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #5a2d82, #7b3fa8);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: .95rem;
            font-weight: 600;
            letter-spacing: .04em;
            cursor: pointer;
            transition: opacity .2s, transform .15s;
        }

        .btn-login:hover { opacity: .92; transform: translateY(-1px); }
        .btn-login:active { transform: translateY(0); }

        /* ── Panel derecho: logo ──────────── */
        .login-logo-panel {
            width: 220px;
            background: linear-gradient(160deg, #0e1f55 0%, #1a3a8c 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 32px 24px;
            gap: 20px;
        }

        /* ── SVG Logo ────────────────────── */
        .logo-svg { width: 110px; height: 110px; }

        .logo-wordmark {
            text-align: center;
            color: #fff;
        }

        .logo-wordmark .brand {
            display: block;
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: .12em;
            line-height: 1.1;
        }

        .logo-wordmark .tagline {
            display: block;
            font-size: .6rem;
            letter-spacing: .25em;
            color: rgba(255,255,255,.55);
            text-transform: uppercase;
            margin-top: 4px;
        }

        .logo-divider {
            width: 32px;
            height: 1px;
            background: rgba(255,255,255,.2);
        }

        .logo-caption {
            font-size: .68rem;
            color: rgba(255,255,255,.35);
            text-align: center;
            letter-spacing: .05em;
        }

        @media (max-width: 620px) {
            .login-card { flex-direction: column; width: 92%; }
            .login-logo-panel { width: 100%; padding: 28px; flex-direction: row; gap: 16px; }
            .logo-svg { width: 64px; height: 64px; }
        }
    </style>
</head>
<body>

<div style="position:fixed; top:16px; right:18px; z-index:1050;">
    <?php include("lang_toggle.php"); ?>
</div>

<div class="login-card">

    <!-- ── FORMULARIO ───────────────────────────────── -->
    <div class="login-form-panel">
        <h2><?php echo t('Bienvenido'); ?></h2>
        <p class="subtitle"><?php echo t('Ingresa tus credenciales para continuar'); ?></p>

        <form action="class/checkLogin.php" method="post">

            <div class="input-group">
                <span class="icon"><i class="fa fa-user"></i></span>
                <input type="text" name="user" placeholder="<?php echo t('Usuario'); ?>" required autofocus>
            </div>

            <div class="input-group">
                <span class="icon"><i class="fa fa-lock"></i></span>
                <input type="password" name="password" placeholder="<?php echo t('Contraseña'); ?>" required>
            </div>

            <div class="login-extras">
                <a href="#"><?php echo t('¿Olvidaste tu contraseña?'); ?></a>
            </div>

            <button type="submit" class="btn-login"><?php echo t('Iniciar Sesión'); ?></button>

        </form>
    </div>

    <!-- ── LOGO PANEL ───────────────────────────────── -->
    <div class="login-logo-panel">

        <!-- Logo SVG corporativo/minimalista -->
        <svg class="logo-svg" viewBox="0 0 110 110" fill="none" xmlns="http://www.w3.org/2000/svg">
            <!-- Anillo exterior -->
            <circle cx="55" cy="55" r="50" stroke="rgba(255,255,255,0.15)" stroke-width="1.5"/>
            <!-- Anillo medio -->
            <circle cx="55" cy="55" r="40" stroke="rgba(255,255,255,0.08)" stroke-width="1"/>

            <!-- Hoja estilizada -->
            <path d="M55 22
                     C55 22 32 34 32 55
                     C32 68 42 76 55 78
                     C68 76 78 68 78 55
                     C78 34 55 22 55 22Z"
                  fill="rgba(255,255,255,0.12)"
                  stroke="rgba(255,255,255,0.5)"
                  stroke-width="1.2"/>

            <!-- Nervio central de la hoja -->
            <line x1="55" y1="28" x2="55" y2="78"
                  stroke="rgba(255,255,255,0.6)"
                  stroke-width="1"
                  stroke-linecap="round"/>

            <!-- Nervios laterales -->
            <path d="M55 42 C48 46 44 52 44 58"
                  stroke="rgba(255,255,255,0.35)"
                  stroke-width="0.8"
                  stroke-linecap="round"
                  fill="none"/>
            <path d="M55 42 C62 46 66 52 66 58"
                  stroke="rgba(255,255,255,0.35)"
                  stroke-width="0.8"
                  stroke-linecap="round"
                  fill="none"/>
            <path d="M55 54 C50 57 47 62 47 66"
                  stroke="rgba(255,255,255,0.25)"
                  stroke-width="0.8"
                  stroke-linecap="round"
                  fill="none"/>
            <path d="M55 54 C60 57 63 62 63 66"
                  stroke="rgba(255,255,255,0.25)"
                  stroke-width="0.8"
                  stroke-linecap="round"
                  fill="none"/>

            <!-- Punto central superior -->
            <circle cx="55" cy="22" r="2.5" fill="white" opacity="0.8"/>

            <!-- Iniciales SR -->
            <text x="55" y="62"
                  text-anchor="middle"
                  fill="white"
                  font-family="Segoe UI, Arial, sans-serif"
                  font-size="13"
                  font-weight="700"
                  letter-spacing="2"
                  opacity="0.9">SN</text>
        </svg>

        <div class="logo-wordmark">
            <span class="brand">SROSS</span>
            <span class="tagline">Nutritions</span>
        </div>

        <div class="logo-divider"></div>

        <span class="logo-caption">Sistema de Gestión<br>de Citas</span>

    </div>

</div>

</body>
</html>
<?php ob_end_flush(); ?>

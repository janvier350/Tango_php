<?php
require_once 'config.php';
require_once 'auth.php';
verificar_auth();

$msg = '';
$tipo_msg = '';

if (isset($_POST['cambiar_clave'])) {
    $clave_actual  = $_POST['clave_actual'];
    $clave_nueva   = $_POST['clave_nueva'];
    $clave_confirm = $_POST['clave_confirm'];

    if ($clave_nueva !== $clave_confirm) {
        $msg = 'La nueva contraseña y la confirmación no coinciden.';
        $tipo_msg = 'danger';
    } elseif (strlen($clave_nueva) < 6) {
        $msg = 'La nueva contraseña debe tener al menos 6 caracteres.';
        $tipo_msg = 'danger';
    } else {
        $stmt = $conn->prepare("SELECT clave FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if ($row && (password_verify($clave_actual, $row['clave']) || $clave_actual === $row['clave'])) {
            $hash = password_hash($clave_nueva, PASSWORD_BCRYPT);
            $upd  = $conn->prepare("UPDATE usuarios SET clave = ? WHERE id = ?");
            $upd->bind_param("si", $hash, $_SESSION['user_id']);
            $upd->execute();
            $msg = 'Contraseña actualizada correctamente.';
            $tipo_msg = 'success';
        } else {
            $msg = 'La contraseña actual es incorrecta.';
            $tipo_msg = 'danger';
        }
    }
}

// Admin: cambiar clave de otro usuario
if (isset($_POST['cambiar_clave_admin']) && $_SESSION['user_rol'] == 1) {
    $uid        = intval($_POST['uid']);
    $clave_new  = $_POST['clave_nueva_admin'];
    $clave_conf = $_POST['clave_confirm_admin'];

    if ($clave_new !== $clave_conf) {
        $msg = 'Las contraseñas del administrador no coinciden.';
        $tipo_msg = 'danger';
    } elseif (strlen($clave_new) < 6) {
        $msg = 'La contraseña debe tener al menos 6 caracteres.';
        $tipo_msg = 'danger';
    } else {
        $hash = password_hash($clave_new, PASSWORD_BCRYPT);
        $upd  = $conn->prepare("UPDATE usuarios SET clave = ? WHERE id = ?");
        $upd->bind_param("si", $hash, $uid);
        $upd->execute();
        $msg = 'Contraseña del usuario actualizada correctamente.';
        $tipo_msg = 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar Contraseña</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container">
        <div class="row justify-content-center">

            <?php if ($msg): ?>
            <div class="col-12 mb-3">
                <div class="alert alert-<?php echo $tipo_msg; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($msg); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
            <?php endif; ?>

            <!-- Cambiar propia contraseña -->
            <div class="col-md-5">
                <div class="card shadow-sm">
                    <div class="card-header text-center fw-bold">
                        <i class="bi bi-key me-2"></i>Cambiar mi Contraseña
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">
                            Usuario: <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong>
                        </p>
                        <form method="POST" id="formPropia">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted">Contraseña Actual</label>
                                <div class="input-group">
                                    <input type="password" name="clave_actual" id="clave_actual"
                                           class="form-control" placeholder="••••••••" required>
                                    <button type="button" class="btn btn-outline-secondary toggle-pw" data-target="clave_actual">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted">Nueva Contraseña</label>
                                <div class="input-group">
                                    <input type="password" name="clave_nueva" id="clave_nueva"
                                           class="form-control" placeholder="••••••••" required minlength="6">
                                    <button type="button" class="btn btn-outline-secondary toggle-pw" data-target="clave_nueva">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">Mínimo 6 caracteres.</div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label small fw-bold text-muted">Confirmar Nueva Contraseña</label>
                                <div class="input-group">
                                    <input type="password" name="clave_confirm" id="clave_confirm"
                                           class="form-control" placeholder="••••••••" required minlength="6">
                                    <button type="button" class="btn btn-outline-secondary toggle-pw" data-target="clave_confirm">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div id="match-msg" class="form-text"></div>
                            </div>
                            <button name="cambiar_clave" class="btn btn-primary w-100 fw-bold">
                                <i class="bi bi-check2-circle me-1"></i>ACTUALIZAR CONTRASEÑA
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Panel admin: cambiar clave de cualquier usuario -->
            <?php if ($_SESSION['user_rol'] == 1):
                $usuarios = $conn->query("SELECT id, nombres, usuario FROM usuarios ORDER BY nombres");
            ?>
            <div class="col-md-5">
                <div class="card shadow-sm border-warning">
                    <div class="card-header text-center fw-bold text-warning bg-dark">
                        <i class="bi bi-shield-lock me-2"></i>Restablecer Contraseña de Usuario
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">Solo visible para administradores.</p>
                        <form method="POST" id="formAdmin">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted">Usuario</label>
                                <select name="uid" class="form-select" required>
                                    <option value="">Seleccione un usuario...</option>
                                    <?php while ($u = $usuarios->fetch_assoc()): ?>
                                    <option value="<?php echo $u['id']; ?>"
                                        <?php if ($u['id'] == $_SESSION['user_id']) echo 'disabled'; ?>>
                                        <?php echo htmlspecialchars($u['nombres']); ?>
                                        (<?php echo htmlspecialchars($u['usuario']); ?>)
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted">Nueva Contraseña</label>
                                <div class="input-group">
                                    <input type="password" name="clave_nueva_admin" id="clave_nueva_admin"
                                           class="form-control" placeholder="••••••••" required minlength="6">
                                    <button type="button" class="btn btn-outline-secondary toggle-pw" data-target="clave_nueva_admin">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label small fw-bold text-muted">Confirmar Contraseña</label>
                                <div class="input-group">
                                    <input type="password" name="clave_confirm_admin" id="clave_confirm_admin"
                                           class="form-control" placeholder="••••••••" required minlength="6">
                                    <button type="button" class="btn btn-outline-secondary toggle-pw" data-target="clave_confirm_admin">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div id="match-msg-admin" class="form-text"></div>
                            </div>
                            <button name="cambiar_clave_admin" class="btn btn-warning w-100 fw-bold text-dark">
                                <i class="bi bi-arrow-repeat me-1"></i>RESTABLECER CONTRASEÑA
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mostrar/ocultar contraseña
        document.querySelectorAll('.toggle-pw').forEach(btn => {
            btn.addEventListener('click', function () {
                const input = document.getElementById(this.dataset.target);
                const icon  = this.querySelector('i');
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.replace('bi-eye', 'bi-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.replace('bi-eye-slash', 'bi-eye');
                }
            });
        });

        // Validación en tiempo real: contraseñas coinciden (formulario propio)
        const nueva    = document.getElementById('clave_nueva');
        const confirm  = document.getElementById('clave_confirm');
        const matchMsg = document.getElementById('match-msg');

        function validarCoincidencia(nv, cv, msgEl) {
            if (!cv.value) { msgEl.textContent = ''; return; }
            if (nv.value === cv.value) {
                msgEl.textContent = '✓ Las contraseñas coinciden.';
                msgEl.className = 'form-text text-success';
            } else {
                msgEl.textContent = '✗ Las contraseñas no coinciden.';
                msgEl.className = 'form-text text-danger';
            }
        }

        [nueva, confirm].forEach(el => {
            el.addEventListener('input', () => validarCoincidencia(nueva, confirm, matchMsg));
        });

        // Validación formulario admin
        <?php if ($_SESSION['user_rol'] == 1): ?>
        const nuevaAdm    = document.getElementById('clave_nueva_admin');
        const confirmAdm  = document.getElementById('clave_confirm_admin');
        const matchMsgAdm = document.getElementById('match-msg-admin');

        [nuevaAdm, confirmAdm].forEach(el => {
            el.addEventListener('input', () => validarCoincidencia(nuevaAdm, confirmAdm, matchMsgAdm));
        });
        <?php endif; ?>
    </script>
</body>
</html>

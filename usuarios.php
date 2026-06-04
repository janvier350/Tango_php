<?php
require_once 'config.php';
require_once 'auth.php';
verificar_auth();

// Solo administradores
if ($_SESSION['user_rol'] != 1) {
    header("Location: index.php"); exit;
}

$mensaje = '';
$tipo_msg = '';

// ── Crear usuario ────────────────────────────────────────────────────
if (isset($_POST['registrar_user'])) {
    $nombre   = trim($_POST['nombre']   ?? '');
    $usuario  = trim($_POST['usuario']  ?? '');
    $clave    = trim($_POST['clave']    ?? '');
    $id_rol   = intval($_POST['id_rol'] ?? 0);
    $id_ofic  = intval($_POST['id_oficina'] ?? 0);

    if ($nombre === '' || $usuario === '' || $clave === '' || $id_rol === 0 || $id_ofic === 0) {
        $mensaje  = 'Todos los campos son obligatorios.';
        $tipo_msg = 'danger';
    } else {
        // Verificar usuario duplicado
        $chk = $conn->prepare("SELECT id FROM usuarios WHERE usuario = ?");
        $chk->bind_param("s", $usuario);
        $chk->execute();
        $chk->store_result();

        if ($chk->num_rows > 0) {
            $mensaje  = "El nombre de usuario <strong>$usuario</strong> ya está en uso.";
            $tipo_msg = 'warning';
        } else {
            $hash = password_hash($clave, PASSWORD_BCRYPT);

            // Intentar con columna 'nombres', si falla intentar sin ella
            $stmt = $conn->prepare("INSERT INTO usuarios (nombre_completo, nombres, usuario, clave, ID_ROL, ID_CTR_OFICINA) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("ssssii", $nombre, $nombre, $usuario, $hash, $id_rol, $id_ofic);
            } else {
                // Fallback sin columna 'nombres'
                $stmt = $conn->prepare("INSERT INTO usuarios (nombre_completo, usuario, clave, ID_ROL, ID_CTR_OFICINA) VALUES (?, ?, ?, ?, ?)");
                if ($stmt) $stmt->bind_param("sssii", $nombre, $usuario, $hash, $id_rol, $id_ofic);
            }

            if ($stmt && $stmt->execute()) {
                $mensaje  = "Usuario <strong>$usuario</strong> creado correctamente.";
                $tipo_msg = 'success';
            } else {
                $mensaje  = 'Error al crear el usuario: <code>' . htmlspecialchars($conn->error ?: ($stmt ? $stmt->error : 'prepare() falló')) . '</code>';
                $tipo_msg = 'danger';
            }
        }
    }
}

// ── Borrar usuario ───────────────────────────────────────────────────
if (isset($_GET['borrar'])) {
    $id = intval($_GET['borrar']);
    if ($id > 0 && $id !== (int)$_SESSION['user_id']) {
        $del = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
        $del->bind_param("i", $id);
        $del->execute();
    }
    header("Location: usuarios.php?ok=1"); exit;
}

// ── Catálogos ────────────────────────────────────────────────────────
$conn->set_charset("utf8");

$res_roles = $conn->query("SELECT ID_ROL, ROL FROM CTR_ROL ORDER BY ID_ROL");
$roles = $res_roles ? $res_roles->fetch_all(MYSQLI_ASSOC) : [];

$res_ofics = $conn->query("SELECT ID_OFICINA, OFICINA FROM CTR_OFICINA ORDER BY OFICINA");
$ofics = $res_ofics ? $res_ofics->fetch_all(MYSQLI_ASSOC) : [];

// Query con JOINs para mostrar rol y oficina; fallback simple si falla
$res_ulist = $conn->query("
    SELECT u.id, u.nombre_completo, u.usuario,
           COALESCE(r.ROL, 'Sin rol')        AS rol,
           COALESCE(o.OFICINA, 'Sin oficina') AS oficina
    FROM usuarios u
    LEFT JOIN CTR_ROL r     ON u.ID_ROL = r.ID_ROL
    LEFT JOIN CTR_OFICINA o ON u.ID_CTR_OFICINA = o.ID_OFICINA
    ORDER BY u.nombre_completo
");
if ($res_ulist) {
    $usuarios = $res_ulist->fetch_all(MYSQLI_ASSOC);
} else {
    // Fallback: query sin JOINs
    $res_simple = $conn->query("SELECT id, nombre_completo, usuario, '' AS rol, '' AS oficina FROM usuarios ORDER BY nombre_completo");
    $usuarios = $res_simple ? $res_simple->fetch_all(MYSQLI_ASSOC) : [];
    $mensaje  = ($mensaje ?: '') . ' [Aviso BD: ' . htmlspecialchars($conn->error) . ']';
    $tipo_msg = $tipo_msg ?: 'warning';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>

<div class="container py-4">
    <h4 class="fw-bold mb-4"><i class="bi bi-people-fill me-2"></i>Gestión de Usuarios</h4>

    <?php if ($mensaje): ?>
    <div class="alert alert-<?php echo $tipo_msg; ?> alert-dismissible fade show" role="alert">
        <?php echo $mensaje; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['ok'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        Usuario eliminado correctamente.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- ── Formulario ── -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header fw-bold text-white" style="background:#2c3e50;">
                    <i class="bi bi-person-plus me-1"></i> Registrar Usuario
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-2">
                            <label class="form-label small fw-semibold">Nombre Completo</label>
                            <input type="text" name="nombre" class="form-control form-control-sm"
                                   placeholder="Ej: Juan Pérez" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold">Usuario (login)</label>
                            <input type="text" name="usuario" class="form-control form-control-sm"
                                   placeholder="Ej: jperez" required autocomplete="off">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold">Contraseña</label>
                            <div class="input-group input-group-sm">
                                <input type="password" name="clave" id="clave_nueva"
                                       class="form-control" placeholder="Mínimo 6 caracteres" required>
                                <button type="button" class="btn btn-outline-secondary"
                                        onclick="togglePass()">
                                    <i class="bi bi-eye" id="eye_icon"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold">Rol</label>
                            <select name="id_rol" class="form-select form-select-sm" required>
                                <option value="">— Seleccionar —</option>
                                <?php foreach ($roles as $r): ?>
                                <option value="<?php echo $r['ID_ROL']; ?>">
                                    <?php echo htmlspecialchars($r['ROL']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Oficina</label>
                            <select name="id_oficina" class="form-select form-select-sm" required>
                                <option value="">— Seleccionar —</option>
                                <?php foreach ($ofics as $o): ?>
                                <option value="<?php echo $o['ID_OFICINA']; ?>">
                                    <?php echo htmlspecialchars($o['OFICINA']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button name="registrar_user" class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-person-check me-1"></i> Crear Usuario
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- ── Lista ── -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header fw-bold text-white d-flex align-items-center" style="background:#2c3e50;">
                    <i class="bi bi-list-ul me-2"></i> Usuarios Registrados
                    <span class="ms-auto badge bg-secondary"><?php echo count($usuarios); ?></span>
                </div>
                <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Nombre</th>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Oficina</th>
                            <th class="text-center">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): ?>
                        <tr>
                            <td class="small"><?php echo htmlspecialchars($u['nombre_completo']); ?></td>
                            <td><code class="small"><?php echo htmlspecialchars($u['usuario']); ?></code></td>
                            <td>
                                <span class="badge bg-secondary small">
                                    <?php echo htmlspecialchars($u['rol']); ?>
                                </span>
                            </td>
                            <td class="small"><?php echo htmlspecialchars($u['oficina']); ?></td>
                            <td class="text-center">
                                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                <a href="?borrar=<?php echo $u['id']; ?>"
                                   class="btn btn-outline-danger btn-sm py-0 px-1"
                                   onclick="return confirm('¿Eliminar al usuario <?php echo htmlspecialchars(addslashes($u['usuario'])); ?>?')"
                                   title="Eliminar">
                                    <i class="bi bi-person-x"></i>
                                </a>
                                <?php else: ?>
                                <span class="badge bg-success">Tú</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($usuarios)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">No hay usuarios registrados.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>

            <div class="alert alert-info mt-3 small">
                <i class="bi bi-info-circle me-1"></i>
                Para cambiar la contraseña de un usuario, usa la página
                <a href="cambiar_clave.php" class="alert-link">Cambiar Contraseña</a>.
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePass() {
    var f = document.getElementById('clave_nueva');
    var i = document.getElementById('eye_icon');
    if (f.type === 'password') {
        f.type = 'text';
        i.className = 'bi bi-eye-slash';
    } else {
        f.type = 'password';
        i.className = 'bi bi-eye';
    }
}
</script>
</body>
</html>

<?php
require_once 'config.php';
require_once 'auth.php';
verificar_auth();

if ($_SESSION['user_rol'] != 1) {
    header("Location: index.php"); exit;
}

$mensaje  = '';
$tipo_msg = '';

// ── Crear usuario ────────────────────────────────────────────────────
if (isset($_POST['registrar_user'])) {
    $nombre  = trim($_POST['nombre']  ?? '');
    $usuario = trim($_POST['usuario'] ?? '');
    $clave   = trim($_POST['clave']   ?? '');
    $id_rol  = intval($_POST['id_rol'] ?? 0);
    $id_ofic = intval($_POST['id_oficina'] ?? 0);

    if ($nombre === '' || $usuario === '' || $clave === '' || $id_rol === 0 || $id_ofic === 0) {
        $mensaje = 'Todos los campos son obligatorios.'; $tipo_msg = 'danger';
    } else {
        $chk = $conn->prepare("SELECT id FROM usuarios WHERE usuario = ?");
        $chk->bind_param("s", $usuario); $chk->execute(); $chk->store_result();
        if ($chk->num_rows > 0) {
            $mensaje = "El usuario <strong>$usuario</strong> ya existe."; $tipo_msg = 'warning';
        } else {
            $hash = password_hash($clave, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("INSERT INTO usuarios (nombres, usuario, clave, ID_ROL, ID_CTR_OFICINA) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) { $stmt->bind_param("sssii", $nombre, $usuario, $hash, $id_rol, $id_ofic); }
            if ($stmt && $stmt->execute()) {
                $mensaje = "Usuario <strong>$usuario</strong> creado correctamente."; $tipo_msg = 'success';
            } else {
                $mensaje = 'Error: <code>' . htmlspecialchars($conn->error ?: ($stmt ? $stmt->error : 'Error prepare')) . '</code>';
                $tipo_msg = 'danger';
            }
        }
    }
}

// ── Editar usuario (cambiar rol y/u oficina) ─────────────────────────
if (isset($_POST['editar_usuario'])) {
    $id_edit  = intval($_POST['id_edit']       ?? 0);
    $id_rol   = intval($_POST['id_rol_edit']   ?? 0);
    $id_ofic  = intval($_POST['id_ofic_edit']  ?? 0);
    if ($id_edit > 0 && $id_rol > 0 && $id_ofic > 0) {
        $upd = $conn->prepare("UPDATE usuarios SET ID_ROL = ?, ID_CTR_OFICINA = ? WHERE id = ?");
        $upd->bind_param("iii", $id_rol, $id_ofic, $id_edit);
        if ($upd->execute()) {
            $mensaje = 'Usuario actualizado correctamente.'; $tipo_msg = 'success';
        } else {
            $mensaje = 'Error al actualizar: <code>' . htmlspecialchars($conn->error) . '</code>'; $tipo_msg = 'danger';
        }
    } else {
        $mensaje = 'Datos incompletos para editar.'; $tipo_msg = 'danger';
    }
}

// ── Borrar usuario ───────────────────────────────────────────────────
if (isset($_GET['borrar'])) {
    $id = intval($_GET['borrar']);
    if ($id > 0 && $id !== (int)$_SESSION['user_id']) {
        $del = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
        $del->bind_param("i", $id); $del->execute();
        $mensaje = 'Usuario eliminado.'; $tipo_msg = 'success';
    }
}

// ── Crear oficina ────────────────────────────────────────────────────
if (isset($_POST['crear_oficina'])) {
    $nom_ofic = strtoupper(trim($_POST['nom_oficina'] ?? ''));
    if ($nom_ofic === '') {
        $mensaje = 'El nombre de la oficina no puede estar vacío.'; $tipo_msg = 'danger';
    } else {
        $chk2 = $conn->prepare("SELECT ID_OFICINA FROM CTR_OFICINA WHERE OFICINA = ?");
        $chk2->bind_param("s", $nom_ofic); $chk2->execute(); $chk2->store_result();
        if ($chk2->num_rows > 0) {
            $mensaje = "La oficina <strong>$nom_ofic</strong> ya existe."; $tipo_msg = 'warning';
        } else {
            $ins = $conn->prepare("INSERT INTO CTR_OFICINA (OFICINA) VALUES (?)");
            $ins->bind_param("s", $nom_ofic);
            if ($ins->execute()) {
                $mensaje = "Oficina <strong>$nom_ofic</strong> creada correctamente."; $tipo_msg = 'success';
            } else {
                $mensaje = 'Error: <code>' . htmlspecialchars($conn->error) . '</code>'; $tipo_msg = 'danger';
            }
        }
    }
}

// ── Borrar oficina ───────────────────────────────────────────────────
if (isset($_GET['borrar_ofic'])) {
    $id_o = intval($_GET['borrar_ofic']);
    // Verificar que no tenga usuarios asignados
    $chk3 = $conn->prepare("SELECT COUNT(*) AS cnt FROM usuarios WHERE ID_CTR_OFICINA = ?");
    $chk3->bind_param("i", $id_o); $chk3->execute();
    $cnt = $chk3->get_result()->fetch_assoc()['cnt'] ?? 1;
    if ($cnt > 0) {
        $mensaje = 'No se puede eliminar: la oficina tiene usuarios asignados.'; $tipo_msg = 'warning';
    } else {
        $del2 = $conn->prepare("DELETE FROM CTR_OFICINA WHERE ID_OFICINA = ?");
        $del2->bind_param("i", $id_o); $del2->execute();
        $mensaje = 'Oficina eliminada.'; $tipo_msg = 'success';
    }
}

// ── Catálogos ────────────────────────────────────────────────────────
$res_roles = $conn->query("SELECT ID_ROL, ROL FROM CTR_ROL ORDER BY ID_ROL");
$roles = $res_roles ? $res_roles->fetch_all(MYSQLI_ASSOC) : [];

$res_ofics = $conn->query("SELECT ID_OFICINA, OFICINA FROM CTR_OFICINA ORDER BY OFICINA");
$ofics = $res_ofics ? $res_ofics->fetch_all(MYSQLI_ASSOC) : [];

$res_ulist = $conn->query("
    SELECT u.id, u.nombres, u.usuario,
           COALESCE(r.ROL, 'Sin rol')         AS rol,
           COALESCE(o.OFICINA, 'Sin oficina')  AS oficina,
           COALESCE(u.ID_ROL, 0)              AS id_rol_actual,
           COALESCE(u.ID_CTR_OFICINA, 0)      AS id_ofic_actual
    FROM usuarios u
    LEFT JOIN CTR_ROL r     ON u.ID_ROL = r.ID_ROL
    LEFT JOIN CTR_OFICINA o ON u.ID_CTR_OFICINA = o.ID_OFICINA
    ORDER BY u.nombres
");
if ($res_ulist) {
    $usuarios = $res_ulist->fetch_all(MYSQLI_ASSOC);
} else {
    $res_s = $conn->query("SELECT id, nombres, usuario, '' AS rol, '' AS oficina, 0 AS id_rol_actual, 0 AS id_ofic_actual FROM usuarios ORDER BY nombres");
    $usuarios = $res_s ? $res_s->fetch_all(MYSQLI_ASSOC) : [];
}

// Oficinas con conteo de usuarios
$res_ofic_list = $conn->query("
    SELECT o.ID_OFICINA, o.OFICINA, COUNT(u.id) AS total_usuarios
    FROM CTR_OFICINA o
    LEFT JOIN usuarios u ON u.ID_CTR_OFICINA = o.ID_OFICINA
    GROUP BY o.ID_OFICINA, o.OFICINA
    ORDER BY o.OFICINA
");
$ofic_list = $res_ofic_list ? $res_ofic_list->fetch_all(MYSQLI_ASSOC) : [];

// Tab activa (usuarios u oficinas)
$tab_activa = $_GET['tab'] ?? 'usuarios';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios y Oficinas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>

<div class="container py-4">
    <h4 class="fw-bold mb-3"><i class="bi bi-shield-lock me-2"></i>Administración</h4>

    <?php if ($mensaje): ?>
    <div class="alert alert-<?php echo $tipo_msg; ?> alert-dismissible fade show">
        <?php echo $mensaje; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- ── Tabs ── -->
    <ul class="nav nav-tabs mb-0" id="adminTabs">
        <li class="nav-item">
            <a class="nav-link fw-semibold <?php echo $tab_activa !== 'oficinas' ? 'active' : ''; ?>"
               href="?tab=usuarios">
                <i class="bi bi-people me-1"></i> Usuarios
                <span class="badge bg-secondary ms-1"><?php echo count($usuarios); ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-semibold <?php echo $tab_activa === 'oficinas' ? 'active' : ''; ?>"
               href="?tab=oficinas">
                <i class="bi bi-building me-1"></i> Oficinas
                <span class="badge bg-secondary ms-1"><?php echo count($ofic_list); ?></span>
            </a>
        </li>
    </ul>

    <div class="bg-white border border-top-0 rounded-bottom shadow-sm p-4">

    <!-- ══════════════════════════════════════
         TAB USUARIOS
    ══════════════════════════════════════ -->
    <?php if ($tab_activa !== 'oficinas'): ?>
    <div class="row g-4">

        <!-- Formulario crear usuario -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header fw-bold text-white" style="background:#2c3e50;">
                    <i class="bi bi-person-plus me-1"></i> Registrar Usuario
                </div>
                <div class="card-body">
                    <form method="POST" action="?tab=usuarios">
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
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePass()">
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
            <div class="alert alert-info mt-3 small">
                <i class="bi bi-info-circle me-1"></i>
                Para cambiar la contraseña, usa
                <a href="cambiar_clave.php" class="alert-link">Cambiar Contraseña</a>.
            </div>
        </div>

        <!-- Lista de usuarios -->
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
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): ?>
                        <tr>
                            <td class="small"><?php echo htmlspecialchars($u['nombres']); ?></td>
                            <td><code class="small"><?php echo htmlspecialchars($u['usuario']); ?></code></td>
                            <td><span class="badge bg-secondary small"><?php echo htmlspecialchars($u['rol']); ?></span></td>
                            <td class="small"><?php echo htmlspecialchars($u['oficina']); ?></td>
                            <td class="text-center">
                                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                <!-- Botón editar -->
                                <button class="btn btn-outline-primary btn-sm py-0 px-1 me-1"
                                    onclick="abrirEditar(<?php echo $u['id']; ?>,
                                        '<?php echo htmlspecialchars(addslashes($u['nombres'])); ?>',
                                        <?php echo (int)$u['id_rol_actual']; ?>,
                                        <?php echo (int)$u['id_ofic_actual']; ?>)"
                                    title="Editar rol / oficina">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <!-- Botón eliminar -->
                                <a href="?borrar=<?php echo $u['id']; ?>&tab=usuarios"
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
        </div>
    </div>

    <!-- ══════════════════════════════════════
         TAB OFICINAS
    ══════════════════════════════════════ -->
    <?php else: ?>
    <div class="row g-4">

        <!-- Formulario crear oficina -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header fw-bold text-white" style="background:#1a6b3c;">
                    <i class="bi bi-building-add me-1"></i> Nueva Oficina
                </div>
                <div class="card-body">
                    <form method="POST" action="?tab=oficinas">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Nombre de la Oficina</label>
                            <input type="text" name="nom_oficina" class="form-control form-control-sm"
                                   placeholder="Ej: CAJA 402" required
                                   style="text-transform:uppercase;">
                            <div class="form-text">Se guardará en mayúsculas.</div>
                        </div>
                        <button name="crear_oficina" class="btn btn-success btn-sm w-100">
                            <i class="bi bi-plus-circle me-1"></i> Crear Oficina
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Lista de oficinas -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header fw-bold text-white d-flex align-items-center" style="background:#1a6b3c;">
                    <i class="bi bi-building me-2"></i> Oficinas Registradas
                    <span class="ms-auto badge bg-light text-dark"><?php echo count($ofic_list); ?></span>
                </div>
                <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Oficina</th>
                            <th class="text-center">Usuarios</th>
                            <th class="text-center">Eliminar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ofic_list as $oo): ?>
                        <tr>
                            <td class="text-muted small"><?php echo $oo['ID_OFICINA']; ?></td>
                            <td class="fw-semibold"><?php echo htmlspecialchars($oo['OFICINA']); ?></td>
                            <td class="text-center">
                                <span class="badge <?php echo $oo['total_usuarios'] > 0 ? 'bg-primary' : 'bg-secondary'; ?>">
                                    <?php echo $oo['total_usuarios']; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($oo['total_usuarios'] == 0): ?>
                                <a href="?borrar_ofic=<?php echo $oo['ID_OFICINA']; ?>&tab=oficinas"
                                   class="btn btn-outline-danger btn-sm py-0 px-1"
                                   onclick="return confirm('¿Eliminar la oficina <?php echo htmlspecialchars(addslashes($oo['OFICINA'])); ?>?')"
                                   title="Eliminar">
                                    <i class="bi bi-trash3"></i>
                                </a>
                                <?php else: ?>
                                <span class="text-muted small" title="Tiene usuarios asignados">
                                    <i class="bi bi-lock"></i>
                                </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($ofic_list)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">No hay oficinas registradas.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    </div><!-- /bg-white -->
</div>

<!-- ── Modal Editar Usuario ── -->
<div class="modal fade" id="modalEditar" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header text-white" style="background:#2c3e50;">
                <h6 class="modal-title"><i class="bi bi-pencil-square me-1"></i> Editar Usuario</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="?tab=usuarios">
                <div class="modal-body">
                    <input type="hidden" name="id_edit" id="edit_id">
                    <p class="fw-semibold mb-3" id="edit_nombre"></p>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Rol</label>
                        <select name="id_rol_edit" id="edit_rol" class="form-select form-select-sm" required>
                            <?php foreach ($roles as $r): ?>
                            <option value="<?php echo $r['ID_ROL']; ?>">
                                <?php echo htmlspecialchars($r['ROL']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-1">
                        <label class="form-label small fw-semibold">Oficina</label>
                        <select name="id_ofic_edit" id="edit_ofic" class="form-select form-select-sm" required>
                            <?php foreach ($ofics as $o): ?>
                            <option value="<?php echo $o['ID_OFICINA']; ?>">
                                <?php echo htmlspecialchars($o['OFICINA']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="editar_usuario" class="btn btn-sm btn-primary">
                        <i class="bi bi-save me-1"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePass() {
    var f = document.getElementById('clave_nueva');
    var i = document.getElementById('eye_icon');
    f.type = f.type === 'password' ? 'text' : 'password';
    i.className = f.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}

function abrirEditar(id, nombre, idRol, idOfic) {
    document.getElementById('edit_id').value    = id;
    document.getElementById('edit_nombre').textContent = nombre;
    var selRol  = document.getElementById('edit_rol');
    var selOfic = document.getElementById('edit_ofic');
    for (var i = 0; i < selRol.options.length;  i++) if (selRol.options[i].value  == idRol)  selRol.selectedIndex  = i;
    for (var j = 0; j < selOfic.options.length; j++) if (selOfic.options[j].value == idOfic) selOfic.selectedIndex = j;
    new bootstrap.Modal(document.getElementById('modalEditar')).show();
}
</script>
</body>
</html>

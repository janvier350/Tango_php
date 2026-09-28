<?php
date_default_timezone_set('America/Guayaquil');
require_once 'config.php';
require_once 'auth.php';
verificar_auth();
$conn->set_charset("utf8");

// Solo CEO (3) y Administración (4)
$id_rol = intval($_SESSION["user_rol"] ?? 0);
if (!in_array($id_rol, [3, 4])) { header("Location: index.php"); exit; }

require_once 'audit.php';
auditoria_crear_tabla($conn); // asegura que la tabla exista

// ¿Existe la tabla? (por si el usuario de BD no tuviera permiso CREATE)
$tabla_ok = (@$conn->query("SELECT 1 FROM auditoria LIMIT 1") !== false);

// --- Filtros ---
$f_user   = $_GET['f_user']   ?? '';
$f_modulo = $_GET['f_modulo'] ?? '';
$f_accion = $_GET['f_accion'] ?? '';
$f_desde  = $_GET['f_desde']  ?? '';
$f_hasta  = $_GET['f_hasta']  ?? '';
$f_texto  = trim($_GET['f_texto'] ?? '');

$re_fecha = '/^\d{4}-\d{2}-\d{2}$/';
$where = "1=1";
$params = [];
$types  = "";
if (ctype_digit((string)$f_user)) { $where .= " AND id_usuario = ?"; $params[] = intval($f_user); $types .= "i"; }
if ($f_modulo !== '') { $where .= " AND modulo = ?"; $params[] = $f_modulo; $types .= "s"; }
if ($f_accion !== '') { $where .= " AND accion = ?"; $params[] = $f_accion; $types .= "s"; }
if (preg_match($re_fecha, $f_desde)) { $where .= " AND fecha_hora >= ?"; $params[] = $f_desde . ' 00:00:00'; $types .= "s"; }
if (preg_match($re_fecha, $f_hasta)) { $where .= " AND fecha_hora <= ?"; $params[] = $f_hasta . ' 23:59:59'; $types .= "s"; }
if ($f_texto !== '') {
    $like = "%$f_texto%";
    $where .= " AND (usuario LIKE ? OR detalle LIKE ? OR id_registro LIKE ? OR oficina LIKE ?)";
    array_push($params, $like, $like, $like, $like);
    $types .= "ssss";
}

// --- Exportación a Excel (CSV) ---
if (($_GET['export'] ?? '') === 'csv' && $tabla_ok) {
    if (function_exists('ob_get_level')) { while (ob_get_level() > 0) { ob_end_clean(); } }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="auditoria_' . date('Ymd_His') . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Fecha/hora','Usuario','Rol','Oficina','Modulo','Accion','Id registro','Detalle','IP'], ';');
    $sqlx = "SELECT * FROM auditoria WHERE $where ORDER BY fecha_hora DESC, id DESC LIMIT 50000";
    $stx = $conn->prepare($sqlx);
    if ($types !== '') { $stx->bind_param($types, ...$params); }
    $stx->execute();
    $rx = $stx->get_result();
    while ($a = $rx->fetch_assoc()) {
        fputcsv($out, [
            date('d/m/Y H:i:s', strtotime($a['fecha_hora'])),
            $a['usuario'], $a['rol'], $a['oficina'], $a['modulo'], $a['accion'],
            $a['id_registro'], $a['detalle'], $a['ip'],
        ], ';');
    }
    fclose($out);
    exit;
}

// --- Datos para la tabla (últimos 500) ---
$rows = [];
$total = 0;
if ($tabla_ok) {
    $sql = "SELECT * FROM auditoria WHERE $where ORDER BY fecha_hora DESC, id DESC LIMIT 500";
    $stmt = $conn->prepare($sql);
    if ($types !== '') { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $sqlc = "SELECT COUNT(*) AS n FROM auditoria WHERE $where";
    $stc = $conn->prepare($sqlc);
    if ($types !== '') { $stc->bind_param($types, ...$params); }
    $stc->execute();
    $total = (int)($stc->get_result()->fetch_assoc()['n'] ?? 0);
}

// Opciones de los selectores
$usuarios = $tabla_ok ? $conn->query("SELECT DISTINCT id_usuario, usuario FROM auditoria WHERE id_usuario IS NOT NULL ORDER BY usuario ASC") : false;
$modulos  = $tabla_ok ? $conn->query("SELECT DISTINCT modulo FROM auditoria ORDER BY modulo ASC") : false;
$acciones = $tabla_ok ? $conn->query("SELECT DISTINCT accion FROM auditoria ORDER BY accion ASC") : false;

// Colores de badge por acción
function badge_accion($a) {
    $map = [
        'CREO' => 'success', 'REALIZO' => 'success',
        'EDITO' => 'primary', 'LOGIN' => 'info',
        'APROBO' => 'teal', 'ANULO' => 'danger', 'LOGOUT' => 'secondary',
    ];
    $c = $map[$a] ?? 'dark';
    if ($c === 'teal') return 'style="background:#0097b2" class="badge"';
    return 'class="badge bg-' . $c . '"';
}
$qs = function($extra = []) {
    $base = ['f_user'=>$_GET['f_user']??'','f_modulo'=>$_GET['f_modulo']??'','f_accion'=>$_GET['f_accion']??'',
             'f_desde'=>$_GET['f_desde']??'','f_hasta'=>$_GET['f_hasta']??'','f_texto'=>$_GET['f_texto']??''];
    return http_build_query(array_merge($base, $extra));
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <title>TANGO | Auditoría</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="estilos.css">
</head>
<body class="bg-light">
    <?php if ($id_rol == 4) { include 'navbar_control.php'; } else { include 'navbar.php'; } ?>

    <div class="container-fluid px-3 mt-3">
        <div class="d-flex align-items-center gap-2 mb-1">
            <i class="bi bi-journal-text fs-3"></i>
            <div>
                <h4 class="fw-bold mb-0">Registro de cambios (auditoría)</h4>
                <p class="text-muted small mb-0">Quién crea, edita, anula o aprueba información y en qué módulo.</p>
            </div>
        </div>

        <?php if (!$tabla_ok): ?>
        <div class="alert alert-warning small my-3">
            <i class="bi bi-exclamation-triangle me-1"></i>La tabla de auditoría aún no existe y no se pudo crear automáticamente.
            Ejecuta este SQL en la base de datos:
            <pre class="mb-0 mt-2" style="white-space:pre-wrap;">CREATE TABLE auditoria (
  id BIGINT NOT NULL AUTO_INCREMENT,
  fecha_hora DATETIME NOT NULL,
  id_usuario INT NULL,
  usuario VARCHAR(120) NULL,
  rol VARCHAR(60) NULL,
  oficina VARCHAR(120) NULL,
  modulo VARCHAR(60) NOT NULL,
  accion VARCHAR(40) NOT NULL,
  id_registro VARCHAR(60) NULL,
  detalle TEXT NULL,
  ip VARCHAR(60) NULL,
  PRIMARY KEY (id),
  KEY idx_fecha (fecha_hora),
  KEY idx_usuario (id_usuario),
  KEY idx_modulo (modulo),
  KEY idx_accion (accion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;</pre>
        </div>
        <?php endif; ?>

        <!-- Filtros -->
        <div class="card border-0 shadow-sm my-3">
            <div class="card-body py-2">
                <form method="GET" action="auditoria.php" class="row g-2 align-items-end">
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-bold mb-1">Usuario</label>
                        <select name="f_user" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <?php while ($usuarios && ($u = $usuarios->fetch_assoc())):
                                $sel = ((string)$f_user === (string)$u['id_usuario']) ? 'selected' : ''; ?>
                            <option value="<?php echo (int)$u['id_usuario']; ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($u['usuario']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-bold mb-1">Módulo</label>
                        <select name="f_modulo" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <?php while ($modulos && ($mo = $modulos->fetch_assoc())):
                                $sel = ($f_modulo === $mo['modulo']) ? 'selected' : ''; ?>
                            <option value="<?php echo htmlspecialchars($mo['modulo']); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($mo['modulo']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-bold mb-1">Acción</label>
                        <select name="f_accion" class="form-select form-select-sm">
                            <option value="">Todas</option>
                            <?php while ($acciones && ($ac = $acciones->fetch_assoc())):
                                $sel = ($f_accion === $ac['accion']) ? 'selected' : ''; ?>
                            <option value="<?php echo htmlspecialchars($ac['accion']); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($ac['accion']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-bold mb-1">Desde</label>
                        <input type="date" name="f_desde" class="form-control form-control-sm" value="<?php echo htmlspecialchars($f_desde); ?>">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-bold mb-1">Hasta</label>
                        <input type="date" name="f_hasta" class="form-control form-control-sm" value="<?php echo htmlspecialchars($f_hasta); ?>">
                    </div>
                    <div class="col-6 col-md-2 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i></button>
                        <a href="auditoria.php" class="btn btn-outline-secondary btn-sm w-100" title="Limpiar"><i class="bi bi-x-lg"></i></a>
                    </div>
                    <div class="col-12 col-md-8">
                        <input type="text" name="f_texto" class="form-control form-control-sm" placeholder="Buscar en usuario / detalle / oficina…" value="<?php echo htmlspecialchars($f_texto); ?>">
                    </div>
                </form>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-muted small">Mostrando <?php echo count($rows); ?> de <?php echo $total; ?> registros<?php echo $total > 500 ? ' (últimos 500)' : ''; ?>.</span>
            <a href="auditoria.php?<?php echo $qs(['export'=>'csv']); ?>" class="btn btn-success btn-sm">
                <i class="bi bi-file-earmark-excel me-1"></i>Exportar a Excel
            </a>
        </div>

        <div class="bg-white shadow-sm p-2 table-responsive">
            <table class="table table-sm table-hover align-middle" style="font-size:.8rem;">
                <thead class="table-secondary">
                    <tr>
                        <th>Fecha/hora</th><th>Usuario</th><th>Rol</th><th>Oficina</th>
                        <th>Módulo</th><th>Acción</th><th>Registro</th><th>Detalle</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $a): ?>
                    <tr>
                        <td class="text-nowrap"><?php echo date('d/m/Y H:i:s', strtotime($a['fecha_hora'])); ?></td>
                        <td class="fw-bold"><?php echo htmlspecialchars($a['usuario']); ?></td>
                        <td><span class="text-muted small"><?php echo htmlspecialchars($a['rol']); ?></span></td>
                        <td><?php echo htmlspecialchars($a['oficina']); ?></td>
                        <td><?php echo htmlspecialchars($a['modulo']); ?></td>
                        <td><span <?php echo badge_accion($a['accion']); ?>><?php echo htmlspecialchars($a['accion']); ?></span></td>
                        <td class="text-center"><?php echo $a['id_registro'] !== null && $a['id_registro'] !== '' ? '#'.htmlspecialchars($a['id_registro']) : '—'; ?></td>
                        <td><?php echo htmlspecialchars($a['detalle']); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-3">No hay registros de auditoría con estos filtros.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
/**
 * migrar_doctores.php — Migración ADM_DOCTOR → ADM_USUARIO (rol DOCTOR)
 *
 * Reescribe AG_CITA.IDDOCTOR para que apunte a ADM_USUARIO.IDADM_USUARIO
 * (en vez de ADM_DOCTOR.IDDOCTOR), emparejando por NOMBRES+APELLIDOS.
 *
 * IMPORTANTE: Haz un backup completo antes de ejecutar.
 * Este script solo debe usarse una vez, ANTES de subir las páginas
 * actualizadas (home.php, atencion_paciente.php, get_historial_paciente.php,
 * SCH_Calendar.php) que ya consultan ADM_USUARIO en vez de ADM_DOCTOR.
 */
session_start();
require_once("class/funciones.php");
require_once("class/conexionBD.php");
$conexion = conectarse();

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'SISTEMA') {
    die('<p style="color:red;font-family:sans-serif;padding:2rem;">Acceso restringido — solo SISTEMA.</p>');
}

// ── Catálogo ADM_DOCTOR ────────────────────────────────────────────────
$doctoresCatalogo = [];
$res = $conexion->query("SELECT IDDOCTOR, NOMBRES, APELLIDOS, ESTADO FROM ADM_DOCTOR ORDER BY NOMBRES");
while ($r = $res->fetch_assoc()) { $doctoresCatalogo[] = $r; }

// ── Usuarios con rol DOCTOR ─────────────────────────────────────────────
$doctoresUsuario = [];
$res = $conexion->query(
    "SELECT U.IDADM_USUARIO, U.NOMBRES, U.APELLIDOS, U.ESTADO
     FROM ADM_USUARIO U
     INNER JOIN ADM_ROL R ON U.IDADM_ROL = R.IDADM_ROL
     WHERE R.CARGO = 'DOCTOR'
     ORDER BY U.NOMBRES"
);
while ($r = $res->fetch_assoc()) { $doctoresUsuario[] = $r; }

$usuarioPorNombre = [];
foreach ($doctoresUsuario as $u) {
    $usuarioPorNombre[$u['NOMBRES'] . '|' . $u['APELLIDOS']] = $u;
}

// ── Diagnóstico: citas que se migrarían y citas que quedarían huérfanas ──
$citasPendientes = $conexion->query(
    "SELECT COUNT(*) c
     FROM AG_CITA C
     INNER JOIN ADM_DOCTOR D   ON C.IDDOCTOR = D.IDDOCTOR
     INNER JOIN ADM_USUARIO U  ON U.NOMBRES = D.NOMBRES AND U.APELLIDOS = D.APELLIDOS
     INNER JOIN ADM_ROL R      ON U.IDADM_ROL = R.IDADM_ROL AND R.CARGO = 'DOCTOR'"
)->fetch_assoc()['c'];

$citasHuerfanas = [];
$res = $conexion->query(
    "SELECT D.NOMBRES, D.APELLIDOS, COUNT(*) AS CITAS
     FROM AG_CITA C
     INNER JOIN ADM_DOCTOR D ON C.IDDOCTOR = D.IDDOCTOR
     WHERE NOT EXISTS (
         SELECT 1 FROM ADM_USUARIO U
         INNER JOIN ADM_ROL R ON U.IDADM_ROL = R.IDADM_ROL
         WHERE R.CARGO = 'DOCTOR' AND U.NOMBRES = D.NOMBRES AND U.APELLIDOS = D.APELLIDOS
     )
     GROUP BY D.NOMBRES, D.APELLIDOS"
);
while ($r = $res->fetch_assoc()) { $citasHuerfanas[] = $r; }

$ejecutar = isset($_POST['ejecutar']) && $_POST['ejecutar'] === '1';
$log      = [];
$migradas = null;

if ($ejecutar) {
    $ok = $conexion->query(
        "UPDATE AG_CITA C
         INNER JOIN ADM_DOCTOR D  ON C.IDDOCTOR = D.IDDOCTOR
         INNER JOIN ADM_USUARIO U ON U.NOMBRES = D.NOMBRES AND U.APELLIDOS = D.APELLIDOS
         INNER JOIN ADM_ROL R     ON U.IDADM_ROL = R.IDADM_ROL AND R.CARGO = 'DOCTOR'
         SET C.IDDOCTOR = U.IDADM_USUARIO"
    );
    if ($ok) {
        $migradas = $conexion->affected_rows;
        $log[] = ['ok', "✅ $migradas cita(s) actualizada(s): IDDOCTOR ahora apunta a ADM_USUARIO.IDADM_USUARIO."];
    } else {
        $log[] = ['err', "Error en UPDATE: " . $conexion->error];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Migración Doctores → ADM_USUARIO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        pre { background:#1e1e1e; color:#d4d4d4; padding:1rem; border-radius:6px; font-size:.8rem; }
        .log-ok  { color:#4caf50; }
        .log-err { color:#f44336; }
    </style>
</head>
<body class="bg-light">
<div class="container py-4" style="max-width:900px;">

    <div class="card shadow">
        <div class="card-header text-white" style="background:#1a3a5c;">
            <h5 class="mb-0"><i class="bi bi-database-gear me-2"></i>Migración: ADM_DOCTOR → ADM_USUARIO</h5>
        </div>
        <div class="card-body">

            <?php if (!$ejecutar): ?>

            <div class="alert alert-warning d-flex gap-2">
                <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0 mt-1"></i>
                <div>
                    <strong>Haz un backup antes de continuar.</strong>
                    En phpMyAdmin → Exportar → Quick → Go.<br>
                    No subas todavía las páginas actualizadas (home.php, atencion_paciente.php,
                    get_historial_paciente.php, SCH_Calendar.php) — ejecuta primero esta migración.
                </div>
            </div>

            <h6><i class="bi bi-person-badge me-1"></i>Catálogo ADM_DOCTOR (<?php echo count($doctoresCatalogo); ?>)</h6>
            <div class="table-responsive mb-3">
                <table class="table table-sm table-bordered align-middle mb-0">
                    <thead class="table-light"><tr><th>Doctor</th><th>Estado</th><th>¿Tiene usuario con rol DOCTOR?</th></tr></thead>
                    <tbody>
                    <?php foreach ($doctoresCatalogo as $d):
                        $coincide = isset($usuarioPorNombre[$d['NOMBRES'] . '|' . $d['APELLIDOS']]);
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($d['NOMBRES'] . ' ' . $d['APELLIDOS']); ?></td>
                            <td><?php echo htmlspecialchars($d['ESTADO']); ?></td>
                            <td>
                                <?php if ($coincide): ?>
                                    <span class="badge bg-success">Sí</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">No — sus citas quedarían sin doctor</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h6><i class="bi bi-people me-1"></i>Usuarios con rol DOCTOR (<?php echo count($doctoresUsuario); ?>)</h6>
            <div class="table-responsive mb-3">
                <table class="table table-sm table-bordered align-middle mb-0">
                    <thead class="table-light"><tr><th>Usuario</th><th>Estado</th></tr></thead>
                    <tbody>
                    <?php foreach ($doctoresUsuario as $u): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($u['NOMBRES'] . ' ' . $u['APELLIDOS']); ?></td>
                            <td><?php echo htmlspecialchars($u['ESTADO']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="alert alert-info py-2">
                <strong><?php echo $citasPendientes; ?> cita(s)</strong> se actualizarán para apuntar al usuario correspondiente.
            </div>

            <?php if (!empty($citasHuerfanas)): ?>
            <div class="alert alert-danger">
                <strong><i class="bi bi-exclamation-octagon me-1"></i>Atención:</strong>
                las citas de estos doctores del catálogo <u>no tienen</u> un usuario con rol DOCTOR
                con el mismo nombre, así que después de migrar y de subir las páginas actualizadas
                <strong>esas citas dejarán de mostrar el doctor</strong> (a menos que crees el usuario
                correspondiente con el mismo nombre antes de continuar):
                <ul class="mb-0 mt-2">
                    <?php foreach ($citasHuerfanas as $h): ?>
                        <li><?php echo htmlspecialchars($h['NOMBRES'] . ' ' . $h['APELLIDOS']); ?> — <?php echo $h['CITAS']; ?> cita(s)</li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if ($citasPendientes > 0): ?>
            <form method="POST" onsubmit="return confirm('¿Confirmas la migración? Asegúrate de tener backup.');">
                <input type="hidden" name="ejecutar" value="1">
                <button type="submit" class="btn btn-danger">
                    <i class="bi bi-play-circle me-1"></i> Ejecutar migración
                </button>
                <a href="home.php" class="btn btn-outline-secondary ms-2">Cancelar</a>
            </form>
            <?php else: ?>
            <div class="alert alert-success py-2 mb-0">No hay citas pendientes de migrar.</div>
            <a href="home.php" class="btn btn-primary mt-3">Ir al Dashboard</a>
            <?php endif; ?>

            <?php else: ?>

            <h6><i class="bi bi-terminal me-1"></i>Resultado de la migración</h6>
            <pre><?php foreach ($log as [$tipo, $msg]): ?>
<span class="log-<?php echo $tipo; ?>"><?php echo htmlspecialchars($msg); ?></span>
<?php endforeach; ?></pre>

            <?php if ($migradas !== null): ?>
            <div class="alert alert-success mt-3">
                <strong><i class="bi bi-check-circle me-1"></i>Migración completada.</strong>
                Ahora sí puedes subir las páginas actualizadas (home.php, atencion_paciente.php,
                get_historial_paciente.php, SCH_Calendar.php).
            </div>
            <?php endif; ?>

            <a href="home.php" class="btn btn-primary mt-2">
                <i class="bi bi-house me-1"></i> Ir al Dashboard
            </a>
            <a href="migrar_doctores.php" class="btn btn-outline-secondary mt-2 ms-2">Ver estado actual</a>

            <?php endif; ?>

        </div>
    </div>
</div>
</body>
</html>

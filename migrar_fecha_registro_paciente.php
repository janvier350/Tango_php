<?php
/**
 * migrar_fecha_registro_paciente.php — Agrega columna FECHA_REGISTRO a AG_PACIENTE
 *
 * Permite saber cuándo se registró cada paciente en el sistema, igual que
 * en Kalix. Los pacientes existentes se les asignará la fecha/hora actual
 * (no se conoce su fecha real de registro histórico).
 *
 * IMPORTANTE: Haz un backup completo antes de ejecutar.
 * Este script solo debe usarse una vez, ANTES de subir get_historial_paciente.php
 * actualizado.
 */
session_start();
require_once("class/funciones.php");
require_once("class/conexionBD.php");
$conexion = conectarse();

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'SISTEMA') {
    die('<p style="color:red;font-family:sans-serif;padding:2rem;">Acceso restringido — solo SISTEMA.</p>');
}

$dbRow  = $conexion->query("SELECT DATABASE() AS db")->fetch_assoc();
$dbName = $dbRow['db'];

$colRes = $conexion->query(
    "SELECT COUNT(*) c FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = 'AG_PACIENTE' AND COLUMN_NAME = 'FECHA_REGISTRO'"
);
$columnaExiste = (int)$colRes->fetch_assoc()['c'] > 0;

$totalPacientes = 0;
$resCount = $conexion->query("SELECT COUNT(*) c FROM AG_PACIENTE");
if ($resCount) { $totalPacientes = (int)$resCount->fetch_assoc()['c']; }

$ejecutar = isset($_POST['ejecutar']) && $_POST['ejecutar'] === '1';
$log      = [];
$errores  = 0;

if ($ejecutar && !$columnaExiste) {
    if ($conexion->query("ALTER TABLE AG_PACIENTE ADD COLUMN FECHA_REGISTRO DATETIME NULL DEFAULT CURRENT_TIMESTAMP AFTER ADDRESS")) {
        $log[] = ['ok', "✅ Columna FECHA_REGISTRO agregada a AG_PACIENTE (con DEFAULT CURRENT_TIMESTAMP para nuevos pacientes)."];
    } else {
        $log[] = ['err', "Error en ALTER TABLE: " . $conexion->error];
        $errores++;
    }

    if ($errores === 0) {
        if ($conexion->query("UPDATE AG_PACIENTE SET FECHA_REGISTRO = NOW() WHERE FECHA_REGISTRO IS NULL")) {
            $log[] = ['ok', "Pacientes existentes ($totalPacientes) asignados a la fecha/hora actual como FECHA_REGISTRO."];
        } else {
            $log[] = ['err', "Error asignando fecha por defecto: " . $conexion->error];
            $errores++;
        }
    }

    $log[] = $errores === 0
        ? ['ok', "✅ Migración completada. Ya puedes subir get_historial_paciente.php actualizado."]
        : ['err', "⚠️ Migración con $errores error(es). Revisa los detalles."];
} elseif ($ejecutar && $columnaExiste) {
    $log[] = ['ok', "La columna FECHA_REGISTRO ya existía — no se hizo ningún cambio."];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Migración: Fecha de Registro de Paciente</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        pre { background:#1e1e1e; color:#d4d4d4; padding:1rem; border-radius:6px; font-size:.8rem; }
        .log-ok  { color:#4caf50; }
        .log-err { color:#f44336; }
    </style>
</head>
<body class="bg-light">
<div class="container py-4" style="max-width:860px;">

    <div class="card shadow">
        <div class="card-header text-white" style="background:#1a3a5c;">
            <h5 class="mb-0"><i class="bi bi-calendar-plus-fill me-2"></i>Migración: Fecha de Registro de Paciente</h5>
        </div>
        <div class="card-body">

            <?php if (!$ejecutar): ?>

            <div class="alert alert-warning d-flex gap-2">
                <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0 mt-1"></i>
                <div>
                    <strong>Haz un backup antes de continuar.</strong>
                    En phpMyAdmin → Exportar → Quick → Go.<br>
                    No subas todavía get_historial_paciente.php actualizado — ejecuta primero esta migración.
                </div>
            </div>

            <p>Pacientes registrados actualmente: <strong><?php echo $totalPacientes; ?></strong></p>

            <?php if ($columnaExiste): ?>
            <div class="alert alert-success py-2 mb-0">
                La columna FECHA_REGISTRO ya existe en AG_PACIENTE. No hay nada que migrar.
            </div>
            <?php else: ?>
            <div class="alert alert-info py-2">
                Se agregará la columna <code>FECHA_REGISTRO</code> (DATETIME, con
                <code>DEFAULT CURRENT_TIMESTAMP</code> para que se registre automáticamente al crear un
                paciente nuevo) a <code>AG_PACIENTE</code>. Los <?php echo $totalPacientes; ?> paciente(s)
                existentes se les asignará la fecha/hora actual.
            </div>
            <form method="POST" onsubmit="return confirm('¿Confirmas la migración? Asegúrate de tener backup.');">
                <input type="hidden" name="ejecutar" value="1">
                <button type="submit" class="btn btn-danger">
                    <i class="bi bi-play-circle me-1"></i> Ejecutar migración
                </button>
                <a href="home.php" class="btn btn-outline-secondary ms-2">Cancelar</a>
            </form>
            <?php endif; ?>

            <?php else: ?>

            <h6><i class="bi bi-terminal me-1"></i>Resultado de la migración</h6>
            <pre><?php foreach ($log as [$tipo, $msg]): ?>
<span class="log-<?php echo $tipo; ?>"><?php echo htmlspecialchars($msg); ?></span>
<?php endforeach; ?></pre>

            <?php if ($errores === 0): ?>
            <div class="alert alert-success mt-3">
                <strong><i class="bi bi-check-circle me-1"></i>Migración completada.</strong>
                Ahora sí puedes subir get_historial_paciente.php actualizado.
            </div>
            <?php endif; ?>

            <a href="home.php" class="btn btn-primary mt-2">
                <i class="bi bi-house me-1"></i> Ir al Dashboard
            </a>
            <a href="migrar_fecha_registro_paciente.php" class="btn btn-outline-secondary mt-2 ms-2">Ver estado actual</a>

            <?php endif; ?>

        </div>
    </div>
</div>
</body>
</html>

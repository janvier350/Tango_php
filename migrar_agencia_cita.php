<?php
/**
 * migrar_agencia_cita.php — Agrega columna IDAGENCIA a AG_CITA
 *
 * Permite elegir la Location (ADM_AGENCIA) al agendar una cita, igual
 * que en Kalix. Las citas existentes se asignan a la agencia MATRIZ
 * (la de menor IDAGENCIA) por defecto.
 *
 * IMPORTANTE: Haz un backup completo antes de ejecutar.
 * Este script solo debe usarse una vez, ANTES de subir SCH_Calendar.php
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
     WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = 'AG_CITA' AND COLUMN_NAME = 'IDAGENCIA'"
);
$columnaExiste = (int)$colRes->fetch_assoc()['c'] > 0;

$agencias = [];
$resAg = $conexion->query("SELECT IDAGENCIA, DESCRIPCION, DIRECCION, ESTADO FROM ADM_AGENCIA ORDER BY IDAGENCIA");
while ($r = $resAg->fetch_assoc()) { $agencias[] = $r; }

$agenciaDefault = $agencias[0]['IDAGENCIA'] ?? 1;

$totalCitas = 0;
$resCount = $conexion->query("SELECT COUNT(*) c FROM AG_CITA");
if ($resCount) { $totalCitas = (int)$resCount->fetch_assoc()['c']; }

$ejecutar = isset($_POST['ejecutar']) && $_POST['ejecutar'] === '1';
$log      = [];
$errores  = 0;

if ($ejecutar && !$columnaExiste) {
    if ($conexion->query("ALTER TABLE AG_CITA ADD COLUMN IDAGENCIA INT NULL AFTER IDDOCTOR")) {
        $log[] = ['ok', "✅ Columna IDAGENCIA agregada a AG_CITA."];
    } else {
        $log[] = ['err', "Error en ALTER TABLE: " . $conexion->error];
        $errores++;
    }

    if ($errores === 0) {
        $idDef = (int)$agenciaDefault;
        if ($conexion->query("UPDATE AG_CITA SET IDAGENCIA = $idDef WHERE IDAGENCIA IS NULL")) {
            $log[] = ['ok', "Citas existentes asignadas a la agencia #$idDef por defecto."];
        } else {
            $log[] = ['err', "Error asignando agencia por defecto: " . $conexion->error];
            $errores++;
        }
    }

    $log[] = $errores === 0
        ? ['ok', "✅ Migración completada. Ya puedes subir SCH_Calendar.php actualizado."]
        : ['err', "⚠️ Migración con $errores error(es). Revisa los detalles."];
} elseif ($ejecutar && $columnaExiste) {
    $log[] = ['ok', "La columna IDAGENCIA ya existía — no se hizo ningún cambio."];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Migración: Location (Agencia) en Citas</title>
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
            <h5 class="mb-0"><i class="bi bi-geo-alt-fill me-2"></i>Migración: Location (Agencia) en Citas</h5>
        </div>
        <div class="card-body">

            <?php if (!$ejecutar): ?>

            <div class="alert alert-warning d-flex gap-2">
                <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0 mt-1"></i>
                <div>
                    <strong>Haz un backup antes de continuar.</strong>
                    En phpMyAdmin → Exportar → Quick → Go.<br>
                    No subas todavía SCH_Calendar.php actualizado — ejecuta primero esta migración.
                </div>
            </div>

            <h6><i class="bi bi-building me-1"></i>Agencias registradas (<?php echo count($agencias); ?>)</h6>
            <div class="table-responsive mb-3">
                <table class="table table-sm table-bordered align-middle mb-0">
                    <thead class="table-light"><tr><th>ID</th><th>Nombre</th><th>Dirección</th><th>Estado</th></tr></thead>
                    <tbody>
                    <?php foreach ($agencias as $a): ?>
                        <tr>
                            <td><?php echo (int)$a['IDAGENCIA']; ?></td>
                            <td><?php echo htmlspecialchars($a['DESCRIPCION']); ?></td>
                            <td><?php echo htmlspecialchars($a['DIRECCION'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($a['ESTADO']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($columnaExiste): ?>
            <div class="alert alert-success py-2 mb-0">
                La columna IDAGENCIA ya existe en AG_CITA. No hay nada que migrar.
            </div>
            <?php else: ?>
            <div class="alert alert-info py-2">
                Se agregará la columna <code>IDAGENCIA</code> (INT NULL) a <code>AG_CITA</code> y las
                <?php echo $totalCitas; ?> cita(s) existentes se asignarán a la agencia
                <strong>#<?php echo (int)$agenciaDefault; ?></strong> por defecto.
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
                Ahora sí puedes subir SCH_Calendar.php actualizado.
            </div>
            <?php endif; ?>

            <a href="home.php" class="btn btn-primary mt-2">
                <i class="bi bi-house me-1"></i> Ir al Dashboard
            </a>
            <a href="migrar_agencia_cita.php" class="btn btn-outline-secondary mt-2 ms-2">Ver estado actual</a>

            <?php endif; ?>

        </div>
    </div>
</div>
</body>
</html>

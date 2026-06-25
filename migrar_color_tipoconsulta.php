<?php
/**
 * migrar_color_tipoconsulta.php — Agrega columna COLOR a AG_TIPOCONSULTA
 *
 * Permite que cada Tipo de Consulta tenga su propio color, para que el
 * calendario coloree las citas por tipo de consulta (en vez de solo por
 * estado), igual que en Kalix.
 *
 * IMPORTANTE: Haz un backup completo antes de ejecutar.
 * Este script solo debe usarse una vez, ANTES de subir SCH_Calendar.php
 * y gestionar_tipos_consulta.php actualizados.
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
     WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = 'AG_TIPOCONSULTA' AND COLUMN_NAME = 'COLOR'"
);
$columnaExiste = (int)$colRes->fetch_assoc()['c'] > 0;

// Paleta de colores por defecto, asignada en orden a los tipos sin color
$paleta = ['#3788d8', '#28a745', '#fd7e14', '#6f42c1', '#e83e8c', '#20c997', '#ffc107', '#17a2b8', '#6610f2', '#dc3545'];

$tipos = [];
$sqlTipos = $columnaExiste
    ? "SELECT IDTIPOCONSULTA, NOMBRES, ABREVIATURA, ESTADO, COLOR FROM AG_TIPOCONSULTA ORDER BY NOMBRES"
    : "SELECT IDTIPOCONSULTA, NOMBRES, ABREVIATURA, ESTADO FROM AG_TIPOCONSULTA ORDER BY NOMBRES";
$res = $conexion->query($sqlTipos);
while ($r = $res->fetch_assoc()) { $tipos[] = $r; }

$ejecutar = isset($_POST['ejecutar']) && $_POST['ejecutar'] === '1';
$log      = [];
$errores  = 0;

if ($ejecutar && !$columnaExiste) {
    if ($conexion->query("ALTER TABLE AG_TIPOCONSULTA ADD COLUMN COLOR VARCHAR(7) NULL AFTER ABREVIATURA")) {
        $log[] = ['ok', "✅ Columna COLOR agregada a AG_TIPOCONSULTA."];
    } else {
        $log[] = ['err', "Error en ALTER TABLE: " . $conexion->error];
        $errores++;
    }

    if ($errores === 0) {
        $i = 0;
        $resAll = $conexion->query("SELECT IDTIPOCONSULTA FROM AG_TIPOCONSULTA ORDER BY IDTIPOCONSULTA");
        while ($r = $resAll->fetch_assoc()) {
            $color = $paleta[$i % count($paleta)];
            $idTc  = (int)$r['IDTIPOCONSULTA'];
            if ($conexion->query("UPDATE AG_TIPOCONSULTA SET COLOR = '$color' WHERE IDTIPOCONSULTA = $idTc")) {
                $log[] = ['ok', "Tipo #$idTc → color $color"];
            } else {
                $log[] = ['err', "Error asignando color a #$idTc: " . $conexion->error];
                $errores++;
            }
            $i++;
        }
    }

    $log[] = $errores === 0
        ? ['ok', "✅ Migración completada. Ya puedes personalizar los colores en \"Gestionar Tipos de Consulta\"."]
        : ['err', "⚠️ Migración con $errores error(es). Revisa los detalles."];
} elseif ($ejecutar && $columnaExiste) {
    $log[] = ['ok', "La columna COLOR ya existía — no se hizo ningún cambio."];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Migración: Color por Tipo de Consulta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        pre { background:#1e1e1e; color:#d4d4d4; padding:1rem; border-radius:6px; font-size:.8rem; }
        .log-ok  { color:#4caf50; }
        .log-err { color:#f44336; }
        .swatch { width:16px; height:16px; border-radius:4px; display:inline-block; vertical-align:middle; border:1px solid rgba(0,0,0,.15); }
    </style>
</head>
<body class="bg-light">
<div class="container py-4" style="max-width:860px;">

    <div class="card shadow">
        <div class="card-header text-white" style="background:#1a3a5c;">
            <h5 class="mb-0"><i class="bi bi-palette-fill me-2"></i>Migración: Color por Tipo de Consulta</h5>
        </div>
        <div class="card-body">

            <?php if (!$ejecutar): ?>

            <div class="alert alert-warning d-flex gap-2">
                <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0 mt-1"></i>
                <div>
                    <strong>Haz un backup antes de continuar.</strong>
                    En phpMyAdmin → Exportar → Quick → Go.<br>
                    No subas todavía SCH_Calendar.php ni gestionar_tipos_consulta.php actualizados —
                    ejecuta primero esta migración.
                </div>
            </div>

            <h6><i class="bi bi-list-check me-1"></i>Tipos de Consulta actuales (<?php echo count($tipos); ?>)</h6>
            <div class="table-responsive mb-3">
                <table class="table table-sm table-bordered align-middle mb-0">
                    <thead class="table-light"><tr><th>Tipo</th><th>Abreviatura</th><th>Estado</th><th>Color</th></tr></thead>
                    <tbody>
                    <?php foreach ($tipos as $t): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($t['NOMBRES']); ?></td>
                            <td><?php echo htmlspecialchars($t['ABREVIATURA'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($t['ESTADO']); ?></td>
                            <td>
                                <?php if ($columnaExiste && !empty($t['COLOR'])): ?>
                                    <span class="swatch" style="background:<?php echo htmlspecialchars($t['COLOR']); ?>"></span>
                                    <?php echo htmlspecialchars($t['COLOR']); ?>
                                <?php else: ?>
                                    <span class="text-muted">— se asignará uno automáticamente —</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($columnaExiste): ?>
            <div class="alert alert-success py-2 mb-0">
                La columna COLOR ya existe en AG_TIPOCONSULTA. No hay nada que migrar —
                puedes ir directo a <a href="gestionar_tipos_consulta.php">Gestionar Tipos de Consulta</a>.
            </div>
            <?php else: ?>
            <div class="alert alert-info py-2">
                Se agregará la columna <code>COLOR</code> (VARCHAR(7), ej. <code>#28a745</code>) a
                <code>AG_TIPOCONSULTA</code> y se asignará un color por defecto a cada tipo existente.
                Después podrás cambiar cualquier color desde "Gestionar Tipos de Consulta".
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
                Ahora sí puedes subir SCH_Calendar.php y gestionar_tipos_consulta.php actualizados,
                y personalizar los colores desde "Gestionar Tipos de Consulta".
            </div>
            <?php endif; ?>

            <a href="home.php" class="btn btn-primary mt-2">
                <i class="bi bi-house me-1"></i> Ir al Dashboard
            </a>
            <a href="migrar_color_tipoconsulta.php" class="btn btn-outline-secondary mt-2 ms-2">Ver estado actual</a>

            <?php endif; ?>

        </div>
    </div>
</div>
</body>
</html>

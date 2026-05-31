<?php
/**
 * migrar_charset.php — Migración latin1 → utf8mb4
 *
 * IMPORTANTE: Haz un backup completo antes de ejecutar.
 * Este script solo debe usarse una vez.
 */
session_start();
require_once("class/funciones.php");
require_once("class/conexionBD.php");
$conexion = conectarse();

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'SISTEMA') {
    die('<p style="color:red;font-family:sans-serif;padding:2rem;">Acceso restringido — solo SISTEMA.</p>');
}

// ── Nombre de la base de datos activa ──────────────────────────────────
$dbRow = $conexion->query("SELECT DATABASE() AS db")->fetch_assoc();
$dbName = $dbRow['db'];

// ── Obtener todas las tablas de la BD ──────────────────────────────────
$tablas = [];
$res = $conexion->query(
    "SELECT TABLE_NAME, TABLE_COLLATION
     FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = '$dbName'
     ORDER BY TABLE_NAME"
);
while ($r = $res->fetch_assoc()) {
    $tablas[] = $r;
}

// ── Columnas con charset no-utf8mb4 ───────────────────────────────────
$columnas = [];
$res2 = $conexion->query(
    "SELECT TABLE_NAME, COLUMN_NAME, CHARACTER_SET_NAME, COLLATION_NAME, COLUMN_TYPE
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = '$dbName'
       AND CHARACTER_SET_NAME IS NOT NULL
       AND CHARACTER_SET_NAME != 'utf8mb4'
     ORDER BY TABLE_NAME, COLUMN_NAME"
);
while ($r = $res2->fetch_assoc()) {
    $columnas[] = $r;
}

$ejecutar = isset($_POST['ejecutar']) && $_POST['ejecutar'] === '1';
$log      = [];
$errores  = 0;

if ($ejecutar) {
    // ── PASO 1: Charset de la base de datos ───────────────────────────
    if ($conexion->query("ALTER DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
        $log[] = ['ok', "ALTER DATABASE `$dbName` → utf8mb4"];
    } else {
        $log[] = ['err', "ALTER DATABASE: " . $conexion->error];
        $errores++;
    }

    // ── PASO 2: Cada tabla (conversión vía BINARY para preservar bytes) ─
    foreach ($tablas as $t) {
        $tabla = $conexion->real_escape_string($t['TABLE_NAME']);

        // Primero a BINARY (preserva bytes crudos sin recodificar)
        if (!$conexion->query("ALTER TABLE `$tabla` CONVERT TO CHARACTER SET binary")) {
            $log[] = ['err', "→ BINARY `$tabla`: " . $conexion->error];
            $errores++;
            continue;
        }

        // Luego a utf8mb4 (reinterpreta bytes como UTF-8)
        if ($conexion->query("ALTER TABLE `$tabla` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
            $log[] = ['ok', "ALTER TABLE `$tabla` → utf8mb4"];
        } else {
            $log[] = ['err', "→ utf8mb4 `$tabla`: " . $conexion->error];
            $errores++;
        }
    }

    $log[] = $errores === 0
        ? ['ok', "✅ Migración completada sin errores. Actualiza conexionBD.php (ver instrucción abajo)."]
        : ['err', "⚠️ Migración con $errores error(es). Revisa los detalles."];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Migración Charset → utf8mb4</title>
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
            <h5 class="mb-0"><i class="bi bi-database-gear me-2"></i>Migración Charset: latin1 → utf8mb4</h5>
            <small class="opacity-75">Base de datos: <strong><?php echo htmlspecialchars($dbName); ?></strong></small>
        </div>
        <div class="card-body">

            <?php if (!$ejecutar): ?>

            <!-- ── ALERTA DE BACKUP ──────────────────────────────────── -->
            <div class="alert alert-warning d-flex gap-2">
                <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0 mt-1"></i>
                <div>
                    <strong>Haz un backup antes de continuar.</strong><br>
                    En phpMyAdmin → Exportar → Quick → Go. O en terminal:<br>
                    <code>mysqldump -u root -p <?php echo htmlspecialchars($dbName); ?> > backup_<?php echo date('Ymd_His'); ?>.sql</code>
                </div>
            </div>

            <!-- ── RESUMEN ACTUAL ────────────────────────────────────── -->
            <h6 class="mt-3"><i class="bi bi-table me-1"></i>Tablas a migrar (<?php echo count($tablas); ?>)</h6>
            <div class="table-responsive mb-3">
                <table class="table table-sm table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Tabla</th><th>Collation actual</th><th>Estado</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tablas as $t): ?>
                        <?php $esUtf8 = str_starts_with($t['TABLE_COLLATION'] ?? '', 'utf8mb4'); ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($t['TABLE_NAME']); ?></code></td>
                            <td><?php echo htmlspecialchars($t['TABLE_COLLATION'] ?? '—'); ?></td>
                            <td>
                                <?php if ($esUtf8): ?>
                                    <span class="badge bg-success">Ya es utf8mb4</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Pendiente</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($columnas)): ?>
            <h6><i class="bi bi-columns-gap me-1"></i>Columnas de texto a convertir (<?php echo count($columnas); ?>)</h6>
            <div class="table-responsive mb-3" style="max-height:220px;overflow-y:auto;">
                <table class="table table-sm table-bordered mb-0">
                    <thead class="table-light">
                        <tr><th>Tabla</th><th>Columna</th><th>Charset</th><th>Tipo</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($columnas as $c): ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($c['TABLE_NAME']); ?></code></td>
                            <td><?php echo htmlspecialchars($c['COLUMN_NAME']); ?></td>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($c['CHARACTER_SET_NAME']); ?></span></td>
                            <td><small><?php echo htmlspecialchars($c['COLUMN_TYPE']); ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="alert alert-success py-2">Todas las columnas ya usan utf8mb4. Solo falta la BD y tablas.</div>
            <?php endif; ?>

            <!-- ── ESTRATEGIA ────────────────────────────────────────── -->
            <div class="alert alert-info py-2 mb-3">
                <strong><i class="bi bi-info-circle me-1"></i>Estrategia "binary bridge":</strong>
                Para cada tabla se ejecutan 2 pasos:<br>
                1. <code>CONVERT TO CHARACTER SET binary</code> — preserva los bytes crudos sin recodificar<br>
                2. <code>CONVERT TO CHARACTER SET utf8mb4</code> — reinterpreta esos bytes como UTF-8<br>
                Esto evita doble-encodificación si los datos ya eran UTF-8 almacenados en columnas latin1.
            </div>

            <!-- ── FORMULARIO DE EJECUCIÓN ───────────────────────────── -->
            <form method="POST" onsubmit="return confirm('¿Confirmas la migración? Asegúrate de tener backup.');">
                <input type="hidden" name="ejecutar" value="1">
                <button type="submit" class="btn btn-danger">
                    <i class="bi bi-play-circle me-1"></i> Ejecutar migración
                </button>
                <a href="home.php" class="btn btn-outline-secondary ms-2">Cancelar</a>
            </form>

            <?php else: ?>

            <!-- ── LOG DE RESULTADOS ─────────────────────────────────── -->
            <h6><i class="bi bi-terminal me-1"></i>Resultado de la migración</h6>
            <pre><?php foreach ($log as [$tipo, $msg]): ?>
<span class="log-<?php echo $tipo; ?>"><?php echo htmlspecialchars($msg); ?></span>
<?php endforeach; ?></pre>

            <?php if ($errores === 0): ?>
            <!-- ── PASO FINAL: actualizar conexionBD.php ─────────────── -->
            <div class="alert alert-success mt-3">
                <strong><i class="bi bi-check-circle me-1"></i>Migración exitosa.</strong>
                Ahora actualiza <code>class/conexionBD.php</code> para que la conexión use utf8mb4.
                Busca la función <code>conectarse()</code> y agrega justo después del <code>new mysqli(...)</code>:
                <pre class="mt-2 mb-0">$conexion->set_charset('utf8mb4');</pre>
            </div>
            <?php endif; ?>

            <a href="home.php" class="btn btn-primary mt-2">
                <i class="bi bi-house me-1"></i> Ir al Dashboard
            </a>
            <a href="migrar_charset.php" class="btn btn-outline-secondary mt-2 ms-2">Ver estado actual</a>

            <?php endif; ?>

        </div>
    </div>
</div>
</body>
</html>
